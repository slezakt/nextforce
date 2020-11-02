<?php

/**
 * iBulletin - Opinions.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}
class Ibulletin_Content_Opinions_Exception extends Exception {}

Zend_Loader::loadClass('Ibulletin_Content_Abstract');

/**
 * Trida zprostredkovavajici zobrazeni diskuzi k clankum.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Content_Opinions extends Ibulletin_Content_Abstract
{
    /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "opinions_%d.phtml";

    /**
     * @var int Cislo dotazniku
     */
    var $questionnaire_num = null;

    /**
     * @var int ID emailu, ktery je pouzit pro informaci o pridani prispevku,
     *          na konec tohoto mailu je pridan text prispevku.
     */
    var $emailNotification = null;

    /**
     * @var array ID uzivatelu, kterym se ma poslat notification
     */
    var $notificateUsers = array();

    /**
     * @var array  Name zadavacich poli ve formulari, ktera maji byt overena na neprazdnost
     */
    var $requiredFields = array('text');

    /**
     * @var array   Chybove hlasky pro vypsani uzivateli ve frontendu ze zpracovani formulare.
     */
    var $infoMessages = array();


    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     *
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     * @param int $position position objektu
     */
    public function prepare($req,$position = null)
    {
        $config = Zend_Registry::get('config');

        $this->prepareSheetNumber();

        if($req->getParam('ajax') || $req->getParam('ajaxform') ) {
            $this->ajaxaddService($req);
        }

        //pro opinions načítáme jQuery a přidáme obsluzny js
        $js = Ibulletin_Js::getInstance();
        $js->addPlainCode('var CONTENT_POSITION = "' . $position . '";');
        $js->addJsFile('opinions.js');
        $js->addJsFile('jquery.js');
        $this->_evaluate_form();

    }


    /**
     * Vrati renderovaci data contentu s ohledem na nastavenou pozici v hlavnim contentu.
     *
     * Nachysta do view data jednotlivych prispevku a pripadne predvyplneni formulare.
     */
    public function getContent()
    {
        $config = Zend_Registry::get('config');
		$urlHlpr = new Zend_View_Helper_Url();
		$baseUrl = $urlHlpr->url(array(),'default',true);

        $path = $baseUrl . $config->content_article->basepath . $this->id.'/';
        $view = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer')->view;

        // Prelozime znacky
        if(isset($this->html[$this->sheet_number-1])){
            $html = Ibulletin_Marks::translate($this->html[$this->sheet_number-1], $path, $this);
        }
        else{
            $html = '';
        }

        // Html se bude prohanet eval(), takze na zacatek pridame koncovy tag php
        return array('html' => '?> '.$html);
    }

    /**
     * Vrati dalsi data, ktera pouziva objekt ve view
     *
     * @return array/stdClass    Dalsi data, ktera pouziva obsah pro sve zobrazeni ve view
     */
    public function getData(){
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        $config = Zend_Registry::get('config');

        $data = new stdClass;


        // Paginator
        $count = Opinions::getCount($this->id);

        $url_params = $request->getUrlParams();
        // Musime odstranit controller a action z url parametruu
        unset($url_params['controller']);
        unset($url_params['action']);
        $params = new stdClass();
        $params->urlParams = $url_params;
        $params->url_param = $this->getSheetNumberUrlName();
        $paginator = new Zend_Paginator(new Zend_Paginator_Adapter_Null($count));
        $paginator->setItemCountPerPage($config->general->paging->opinions->perpage);
        //$paginator->setViewParameters($params);
        $paginator->setCurrentPageNumber($this->sheet_number);

        // Ziskame data jednotlivych nazoru
        $opinions_data = Opinions::getList($this->id, $paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());
        // Naformatujeme datum timestamp
        foreach($opinions_data as $key => $row){
            $opinions_data[$key]['timestamp'] = $row['timestamp']->toString($config->general->dateformat->short);
        }

        $data->paginatorUrlParams = $params;
        $data->paginator = $paginator;
        $data->opinions_data = $opinions_data;
        $data->user_data = $this->user_data;
        $data->form_id = $this->form_id;
        $data->message = $this->message;
        $data->requiredFields = $this->requiredFields;

        return $data;
    }

    /**
     * Funkce zajistujici vyhodnoceni formulare pro zadavani noveho nazoru, pripadne provadi
     * predvyplneni formulare.
     *
     * Pokusi se najit a predvyplnit data uzivatele. Nejprve zkusi najit posledni zaznam tohoto
     * uzivatele v tabulce opinions a nasledne jeste zkusi predvyplnit data z tabulky users.
     */
    private function _evaluate_form(){
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        $session = Zend_Registry::get('session');
        // Id formularu, ktere jiz byly odeslany
        $saved_forms = isset($session->saved_forms) ? $session->saved_forms : array();
        $user_data = new stdClass();
        $user_id = Ibulletin_Auth::getActualUserId();
        $message = null;

        $isAjax = $request->getParam('ajaxform', null);
        $isOk = false;

        // Pokud byl formular vyplnen a odeslan, pouzijeme na predvyplneni data z nej
        // a data nasledne ulozime
        if($request->has('opinions_send') || $isAjax){

            $user_data->name = strip_tags($request->getParam('opinions_name', null));
            $user_data->surname = strip_tags($request->getParam('opinions_surname', null));
            $user_data->title = strip_tags($request->getParam('opinions_title', null));
            $user_data->employment = strip_tags($request->getParam('opinions_employment', null));
            $user_data->email = $request->getParam('opinions_email', null);
            $text = strip_tags($request->getParam('opinions_text', null));
            $form_id = $request->getParam('form_id', null);

            // Kontrola spravnosti emailove adresy
            $validator = new Zend_Validate_EmailAddress();

            // Zjistime, jestli jsou rewuired fields vyplnena
            $requiredFieldsOk = $this->_evaluate_form_checkRequiredFields();

            // Ulozime data - pouze pokud formular s timto ID jeste nebyl v teto session ulozen
            if(!in_array($form_id, $saved_forms) && !empty($text) &&
                ($validator->isValid($user_data->email) || empty($user_data->email) || trim($user_data->email) == '@')
               && $requiredFieldsOk)
            {
                if(empty($user_data->email) || trim($user_data->email) == '@'){
                    $email = null;
                }
                else{
                    $email = $user_data->email;

                    // Provedeme akce spojene s nastavenim emailu uzivatelem
                    try{
                        Users::emailSet($email);
                    }
                    catch(Users_Exception $e){
                        // Pokud se nepodarilo, neresime to, jen zalogujeme
                        Phc_ErrorLog::warning('Ibulletin_Content_Opinions', $e);
                    }
                }
                try{
                    Opinions::edit($this->id, null, $user_id, $user_data->name, $user_data->surname,
                        $user_data->title, $user_data->employment, $email, $text);
                    $isOk = true;
                    $this->saveOk = true;
                }
                catch(Exception $e){
                    // Pokud zpracovavame ajax, chybu zalogujeme
                    if($isAjax){
                        Phc_ErrorLog::error('Inbulletin_Content_Opinions::_evaluate_form()', $e);
                    }
                    else{
                        throw $e;
                    }
                }

                // Ulozime do session form_id, abychom vedeli, ze uz tento form byl ulozen
                $saved_forms[] = $form_id;
                $session->saved_forms = $saved_forms;

                // Rozesleme informace o pridani prispevku
             //   $this->sendNotifications($text, $user_data);

                $message = Ibulletin_Texts::get('content.opinions.saved');
            }
            elseif(in_array($form_id, $saved_forms)){
                $message = Ibulletin_Texts::get('content.opinions.already_saved');
            }
            elseif(!$requiredFieldsOk){
                $message = join(' ', $this->infoMessages);
                $user_data->text = $text;
                $dont_hide_form = true;
            }
            elseif(!empty($user_data->email) && trim($user_data->email) != '@' && !$validator->isValid($user_data->email)){
                $message = Ibulletin_Texts::get('content.opinions.wrong_email');
                $user_data->text = $text;
                $dont_hide_form = true;
            }
        }

        // Formular nebyl odeslan, pokusime se najit data pro predvyplneni formulare
        else{
            $opinions_record = Opinions::getLastUsersRecord($user_id);

            if($opinions_record){
                $user_data->name = $opinions_record['name'];
                $user_data->surname = $opinions_record['surname'];
                $user_data->title = $opinions_record['title'];
                $user_data->employment = $opinions_record['employment'];
                $user_data->email = $opinions_record['email'];
            }
            else{
                // Pokusime se ziskat nektere udaje pro predvyplneni z users
                $users_record = Ibulletin_Auth::getActualUserData($user_id);
                if($users_record){
                    $user_data->name = $users_record['name'];
                    $user_data->surname = $users_record['surname'];
                    $user_data->title = null;
                    $user_data->employment = null;
                    $user_data->email = $users_record['email'];
                }
            }
        }
        // Predvyplnime zavinac do emailu, pokud je prazdny
        if(empty($user_data->email)){
            $user_data->email = '@';
        }

        // Pripravime nove ID pro prave generovany formular
        $rGenerator = new Ibulletin_RandomStringGenerator();
        $i = 0;
        while(1){
            // Generujeme a zkousime nova form_id
            $form_id = $rGenerator->get(10);
            if(!in_array($form_id, $saved_forms)){
                break;
            }

            if($i > 200){
               throw new Ibulletin_Content_Opinions_Exception('Nepodarilo se nagenerovat unikatni ID pro formular nazory.');
            }
            $i++;
        }
        // Pro prenos do view
        $this->form_id = $form_id;

        // Ulozime data uzivatele do objektu pro pouziti pozdeji
        $this->user_data = $user_data;

        // Pripadna zprava pro zobrazeni
        $this->message = $message;

        if(!$isAjax){
            if ($isOk) {
            	// redirect postu na get
            	$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            	$url = $request->getRequestUri();
            	// append url fragment (dependency of form action in view script)
            	$redirector->gotoUrlAndExit($url . '#' .'nazory');

            }
        }

        return $isOk;
    }

    /**
     * Kontroluje, jestli jsou ve fromulari vyplnena pole, ktera jsou pozadovana
     * v $this->requiredFields.
     *
     * @return bool     Jsou required fields spravne vyplnena?
     */
    private function _evaluate_form_checkRequiredFields()
    {
        $frontController = Zend_Controller_Front::getInstance();
        $req = $frontController->getRequest();
        $texts = Ibulletin_Texts::getSet('content.opinions.info.requiredFieldEmpty');

        $ok = true;
        foreach($this->requiredFields as $field){
            $fieldVal = $req->getParam('opinions_'.$field);
            if(empty($fieldVal)){
                $ok = false;
                if(isset($texts->{$field})){
                    // Text ukladame pod klic pole, abychom nemeli zadnou hlasku vicekrat
                    $this->infoMessages['requiredFieldEmpty'.$field] = $texts->{$field};
                }
                else{
                    $this->infoMessages['requiredFieldEmptydefault'] = $texts->default;
                }
            }
        }

        return $ok;
    }

    /**
     * Ulozeni dotazu pomoci Ajaxu a vraceni dat k aktualizaci diskuze
     */
    public function ajaxaddService($req){
        // Vypneme vsechno renderovani view skriptuu
        //$this->getHelper('viewRenderer')->setNeverRender(true);

        // Pozici contentu musime prenaset mimo, protoze v tomto requestu ji netusime
        $contentPosition = $req->getParam('contentPosition', null);

        // Pripravime tridu s XML pro odpoved.
        $xml = new Ibulletin_XmlClass();
        $xml->__rootName = 'opinions';

        // content-type na text/xml
        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();
        $response->setHeader('Content-Type', 'text/xml', true);

        if($contentPosition){
            // Pripravime renderovaci data pro vypsani tohoto contentu
            Zend_Loader::loadFile('BulletinController.php', 'controllers', true);
            $newReqest = new Zend_Controller_Request_Simple();
            $newResponse = new Zend_Controller_Response_Http();
            $bulletinController = new BulletinController($newReqest, $newResponse);
            $bulletinController->renderContentHtml($contentPosition, $this, $this->pageId);
            $body = $newResponse->getBody();

            $xml->renderCDATA = $body;
        }
        else{
            $xml->renderCDATA = '';
        }

        // Vyhodnotime formular a podle vysledku vyplnime XML
        if(!empty($this->saveOk)){
            $xml->ok = 1;
        }
        $xml->message = $this->message;

        echo $xml->getXml();
    }

    /**
     * Rozesle informaci o tom, ze byl pridan prispevek.
     *
     * @param   string $text          Text dotazu.
     * @param   StdClass $userData    Data zadana ve formulari.
     */
    public function sendNotifications($text, StdClass $user_data)
    {
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');

        // Zjistime, jestli existuji nejaci prijemci a je vybran mail
        if(!($this->emailNotification && !empty($this->notificateUsers))){
            // neni co delat...
            return;
        }

        $mailer = new Ibulletin_Mailer($config, $db);

        // Najdeme cilove uzivatele
        $filter = '1=1'; // Musime najit vsechny uzivatele, kdyby se zmenilo nastaveni
        $users = $mailer->getUsers($filter, 'email ASC');
        foreach($users as $key => $user)
        {
            // Vymazeme vsechny nechtene uzivatele
            if(!in_array($user['id'], $this->notificateUsers)){
                unset($users[$key]);
            }
        }



        // Vytvorime mail, upravime jej a posilame
        $mailer->setIgnoreRegisteredAndSendEmails(true);

        // najit email, ktery se pouziva jako notification
        try{
            $email = Ibulletin_Email::getEmail($this->emailNotification);
        }
        catch(Ibulletin_Email_Exception $e)
        {
            Phc_ErrorLog::error('Opinions::sendNotifications()',
                'Nebyl nalezen email pro upozorneni na dotaz - id emailu: "'.$this->emailNotification.'", puvodni vyjimka:'."\n$e");
            return;
        }

        try
        {
            // Pripravime hodnoty pro vlozeni do tela mailu
            // Nejprve spojime jmeno a prijmeni, pripadne pridame titul - jde o to, aby to vypadalo hezky ve vypisu
            $userInfoClass = clone $user_data;
            if(!empty($userInfoClass->surname)){
                if(!empty($userInfoClass->name)){
                    $userInfoClass->name = $userInfoClass->name.' '.$userInfoClass->surname;
                }
                else{
                    $userInfoClass->name = $userInfoClass->surname;
                }
                unset($userInfoClass->surname);
            }
            if(!empty($userInfoClass->title)){
                $userInfoClass->name = $userInfoClass->title.' '.$userInfoClass->name;
                unset($userInfoClass->title);
            }
            // Doplnime oddeleni od zbytku
            $userInfoClass->name .= ' - ';
            $infoName = $userInfoClass->name;
            unset($userInfoClass->name);
            $userInfoA = array();
            foreach($userInfoClass as $key => $data){
                if(!empty($data)){
                    $userInfoA[] = $data;
                }
            }
            $userInfo = $infoName.join(', ', $userInfoA);
            $contentInfoStr = $this->name.', ID: '.$this->id;
            $tagVals = array('opinionsNewOpinionText' => $text,
                             'opinionsContentInfo' => $contentInfoStr,
                             'opinionsUserInfo' => $userInfo);
            foreach ($tagVals as $k => $v) {
                $mailer->getMailerTags()->addTag($k, $v);
            }

            // nastaveni return path
            $mailer->prepareSending();

            // Odesleme uzivatelum
            foreach ($users as $user)
            {
                try
                {
                    $emailClone = clone $email;
                    $db->beginTransaction();

                    // zaradi se email do users_emails
                    $ue_ids = $mailer->enqueueEmail($emailClone->getId(), "id = ".$user['id']);
                    $ue_id = $ue_ids[0];
                    
                    $sel = $db->select()->from(array('ue' => 'users_emails'))->where('ue.id = ?', $ue_id);
                    //user email pro doplneni token
                    $user_email = $db->fetchRow($sel);
                    
                    // metoda sendMail potrebuje nastavenou tuto vlastnost.
                    $user['users_emails_id'] = $ue_id;
                    $user['email_id'] = $this->emailNotification;
                    $user['token'] = $user_email['token'];
                    // nahrazeni znacek v tele mailu
                    $mailer->getMailerTags()->parseTags($user, $emailClone);
                    // odeslani mailu
                    $mailer->sendMail($user, $emailClone);

                    $db->commit();
                }
                // Odchytavame vsechno, chceme, aby uzivatel nevedel, ze se neco pokazilo
                catch(Exception $e){
                    Phc_ErrorLog::error('Ibulletin_Content_Opinions::sendNotification()',
                        'Nastala chyba pri odesilani informacniho mailu na adresu "'.$user['email'].
                        '", email_id: "'.$this->emailNotification.'". Puvodni vyjimka: '."\n $e");
                }
            }
        }
        // Skryvame tyto chyby pred uzivatelem, tomu jsou jedno, nemaji na nej vliv
        catch(Exception $e){
            Phc_ErrorLog::error('Ibulletin_Content_Opinions::sendNotification()',
                        'Nastala chyba pri vytvareni informacniho mailu.'." EmailId = $this->emailNotification, ".
                        '". Puvodni vyjimka: '."\n $e");
        }
    }
}
