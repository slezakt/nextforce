<?php

/**
 * iBulletin - Consultingroom.php
 *
 * @author Martin Krčmář.
 */

Zend_Loader::loadClass('Ibulletin_Content_Abstract');

/**
 * Trida zprostredkovavajici zobrazeni pravni poradny
 *
 * @author Martin Krčmář.
 */
class Ibulletin_Content_Consulting extends Ibulletin_Content_Abstract
{
    /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "consulting_%d.phtml";

    /**
     * @var string  Zaklad jmena templatu pro dany content, jedna se o cast nayvu od zacatku jmena
     *              souboru, pouzivame pro nalezeni dostupnych template do vyberu.
     */
    var $tpl_name_base = "consulting";

    /**
     * @var array  name zadavacich poli ve formulari, ktera se maji ulozit mimo defaultnich
     *             "email", "dotaz"
     */
    var $extended_fields = array();

    /**
     * @var array  name zadavacich poli ve formulari, ktera maji byt overena na neprazdnost
     */
    var $required_fields = array('email', 'dotaz');

    /**
     * @var Prefix pouzivany pro atributy ukladane do users_attribs
     */
    var $usersAttribsPrefix = "consulting";

    /**
     * @var HTML kód obsahu.
     */
    var $html = "";

    /**
     *  @var HTML kód formuláře.
     */
    var $form = "";

    /**
     * @var string Název
     */
     var $name = "";

    /**
     * @var int Cislo aktualni stranky - urci se podle parametru, default je 1
     */
    var $page_number = 1;

    /**
      * @var   Defaultni poradce
      */
     var $def_consultant = null;

    /**
     * @var int Cislo dotazniku
     */
    var $questionnaire_num = null;

    /**
     *  @var Pocet odpovedi na stranku.
     */
    var $questions_per_page = 10;

    /**
     *  Počet ospovědí na první stránce.
     */
    var $questions_per_first_page = 3;

    /**
     *  @var Od ktereho zaznamu se zorbazuji dotazy.
     */
    var $recstart = 1;

    /**
     *  Akce, která se bude provádět, buď zobrazení první stránky s formulářem,
     *  nebo zorazení seznamu zobrazených dotazů.
     */
    var $action = self::FIRST_PAGE;

    const FIRST_PAGE = 'firstpage';
    const ALL_REPLIES = 'allreplies';
    const REPLY = 'reply';

    /**
     *  Jak bude nazván parametr v URL, kde bude action.
     */
    var $actionURL = 'consulting';

    /**
     *  URL contentu, bude tady pravni_poradna.
     */
    var $urlName = '';

    /**
     *  Název bulletinu, používá se potom při vytváření URL.
     */
    var $bulletinName = '';

    /**
     *  Z které stránky se sem přišlo.
     */
    var $referer = '';

    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     *
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepare($req)
    {
        Zend_Loader::loadClass('Zend_Validate_EmailAddress');
        Zend_Loader::loadClass('Ibulletin_Auth');
        Zend_Loader::loadClass('Zend_View_Helper_Url');

        // ulozi se hodnoty z URL, pro pozdejsi vytvareni odkazu
        $this->bulletinName = $req->getParam('name');
        $this->urlName = $req->getParam('article');
        $this->recstart = $req->getParam('recstart', $this->recstart);

        //referer
        isset($_SERVER['HTTP_REFERER']) ? $this->referer = $_SERVER['HTTP_REFERER'] : $this->referer = "";

        // nejdrive se bude testovat, co chce uzivatel zobrazit, podle paramteru
        // v URL, bud uvodni stranku pravni poradny s formularem pro odeslani
        // dotazu, nebo seznam vsech zodpovezenych dotazu
        $this->action = $req->getParam($this->actionURL, self::FIRST_PAGE);

        switch ($this->action)
        {
            case self::FIRST_PAGE :
                $this->prepareFirstPage($req);
                break;

            case self::ALL_REPLIES :
                $this->prepareAllReplies($req);
                break;

            case self::REPLY :
                $this->prepareReply($req);
                break;

            default :
                $this->prepareFirstPage($req);
                break;
        }
    }

    /**
     * Vrati dalsi data, ktera pouziva objekt ve view
     *
     * @return array/stdClass    Dalsi data, ktera pouziva obsah pro sve zobrazeni ve view
     */
    public function getData(){
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();

        $data = new stdClass;


        // Seznam poradcu do selectboxu
        $data->consultants_sel = $this->getConsultantSel();

        return $data;
    }

    /**
     *  Připravi úvodní stránku právní poradny. Zpracovává formulář pro položení
     *  dotazu.
     *
     *  @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepareFirstPage($req)
    {
        $config_texts = Ibulletin_Texts::getSet('consulting.prepareFirstPage');
        // Vybirame sadu textu podle template
        $templates = $this->getAvailableTemplates();
        if(!empty($config_texts->{$templates[$this->tpl_name_schema]})){
            $texts = $config_texts->{$templates[$this->tpl_name_schema]};
        }
        else{
            $texts = $config_texts->default;
        }

        $renderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');

        // ziskaji se data o uzivateli, pro pozdejsi predvyplneni emailu
        $user = Ibulletin_Auth::getActualUserData();

        $url = new Zend_View_Helper_Url();

        // odkaz na vsechny zodpovezene dotazy, pokud jsme na prvni strance a
        // celkovy pocet zodpovezenych dotazu je vetsi nez 3
        if ($this->questions_per_first_page < $this->getQuestionsNumber())
        {
            $renderer->view->allQuestionsHref = $url->url(
                array(
                    'name' => $req->getParam('name'),
                    'article' => $req->getParam('article'),
                    'recstart' => $this->recstart,
                    $this->actionURL => self::ALL_REPLIES
                ),
                null,
                true
            );
        }

        $isAjax = $req->getParam('isAjaxConsulting');

        // uzivatel odeslal formular pro polozeni dotazu.
        if ($req->getMethod() == 'POST' || $req->getParam('isAjaxConsulting'))
        {

            if($isAjax){
                // Vypneme vsechno renderovani view skriptuu
                $renderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');
                $renderer->setNeverRender(true);
                // Pripravime tridu s XML pro odpoved.
                $xml = new Ibulletin_XmlClass();
                $xml->__rootName = 'consulting';
                // content-type na text/xml
                $frontController = Zend_Controller_Front::getInstance();
                $response = $frontController->getResponse();
                $response->setHeader('Content-Type', 'text/xml', true);
            }


            $this->error = FALSE;
            $this->errorMessages = array();

            // validace emailu
            $emailValidator = new Zend_Validate_EmailAddress();
            $email = $req->getParam('email');
            if ((!$emailValidator->isValid($email) || empty($email)) && in_array('email', $this->required_fields))
            {
                if($isAjax){
                    $xml->wrongMail = 1;
                }
                $renderer->view->emailErrorMessage =  $texts->incorrectEmail;
                $this->error = TRUE;
                $emailError = TRUE;
            }

            $question = $req->getParam('dotaz');

            // obsah nesmi byt prazdny
            if (empty($question) && in_array('dotaz', $this->required_fields))
            {
                $renderer->view->questionErrorMessage = $texts->questionErrorMessage;
                $this->error = TRUE;
                $questionError = TRUE;
            }
            // Pro Ajax jeste doplnime z ktereho bulletinu
            if($isAjax){
                $question = "Bulletin: ".$req->getParam('name')."\r\n".$question;
            }

            $show = $req->getPost('show') ? true : false;
            $consultant_id = $req->getParam('consultant_id', null);


            // Zpracujeme vsechny dalsi volitelne polozky z formulare
            // !! NEPODPORUJE AJAX
            $extendedFieldsData = array();
            foreach($this->extended_fields as $field){
                $val = isset($_POST[$field]) ? $_POST[$field]: ''; //Pouzivame $_POST, protoze $req->getParam() koliduje s route

                // Kontrola jestli je vyplneno, pokud je required
                if(in_array($field, $this->required_fields) && empty($val)){
                    $this->error = true;
                    // Zkusime najit error message v config_text
                    $this->errorMessages[] = $texts->{"incorrect".ucfirst($field)};
                }

                $extendedFieldsData[$this->usersAttribsPrefix.'_'.$field] = $val;
            }


            // nebyla zadna chyba, ulozime dotaz do DB.
            if (!$this->error)
            {
                $this->saveQuestion($email, $user['id'], $question, $show, $consultant_id, $extendedFieldsData);
                $renderer->view->questionSendInfoMessage = $texts->thanksForQuestioning;
                $renderer->view->email = $email;

                // odesle se email s potvrzenim vlozeni dotazu
                $this->sendAutoReply($email, $req);

                if($isAjax){
                    $xml->ok = 1;
                    $xml->message = $texts->ajax->thanksForQuestioning;
                }
            }
            // byla chyba, predvyplni se spravna data do formulare
            else
            {
                if(!$isAjax){
                    if (!isset($emailError))
                        $renderer->view->email = $email;
                    else
                        $renderer->view->email = $user['email'];
                    if (!isset($questionError))
                        $renderer->view->question = $question;
                    else
                        $renderer->view->question = '';
                }
                else{
                    $xml->message = $texts->ajax->problemWithSaving;
                }
            }
        }
        else
        {
            $renderer->view->email = $user['email'];
        }

        // Data pro predvyplneni do formulare
        $userData = Ibulletin_Auth::getActualUserData();
        $prefillData = array();
        foreach($this->extended_fields as $field){
            $val = '';
            // Primarne bereme data z odeslaneho formulare
            if(!empty($_POST[$field])){// Pouzivame post, protoze jinak koliduje s route
                $val = $_POST[$field];
            }
            else{
                // Dale se pokusime najit, jestli neni neco ulozno primo z minula a nakonec
                // zkusime predvyplnit primo daty uzivatele
                if(empty($userData[$this->usersAttribsPrefix.'_'.$field]) && !empty($userData[$field])){
                    $val = $userData[$field];
                }
                elseif(!empty($userData[$this->usersAttribsPrefix.'_'.$field])){
                    $val = $userData[$this->usersAttribsPrefix.'_'.$field];
                }
            }

            $prefillData[$field] = $val;
        }
        $renderer->view->prefillData = $prefillData;

        if($isAjax){
            // Vypiseme XML data Ajaxu
            echo $xml->getXml();
            //Phc_ErrorLog::debug('sad', $xml->getXml());
            //exit();
        }
        else{
            $renderer->view->action = self::FIRST_PAGE;
        }

    }

    /**
     *  Připravuje stránku s kompletním výpisem zodpovězených dotazů.
     *
     *  @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepareAllReplies($req)
    {
        Zend_Loader::loadClass('Paging');

        $renderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');

        // objekt pro strankovani vystupu zodpovezenych dotazu
        $paging = new Paging($req, new Zend_View_Helper_Url());
        /*$paging->setUrlHelperOptions(
            array('controller' => 'bulletin', 'action' => 'show'));*/
        $paging->setLimit($this->questions_per_page);

        // nastavi se celkovy pocet dotazu
        $paging->setNumRows($this->getQuestionsNumber());
        $this->offset = $paging->getOffset();
        $renderer->view->paging = $paging->generatePageLinks();
        $renderer->view->action = self::ALL_REPLIES;

        // odkaz zpet na pravni poradnu
        $url = new Zend_View_Helper_Url();
        $renderer->view->back = $url->url(
            array(
                'name' => $this->bulletinName,
                'article' => $this->urlName,
                $this->actionURL => self::FIRST_PAGE
            ),
            null,
            true
        );
    }

    /**
     *  Připravuje stránku pro zobrazení odpovědi na konkrétní dotaz.
     */
    public function prepareReply($req)
    {
        // ulozi se ID otazky, na kterou chceme zobrazit odpoved.
        $this->questionID = $req->getParam('questionid');
        $renderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');
        $renderer->view->action = self::REPLY;
        $renderer->view->back = $this->referer;
    }


    /**
     * Vrati renderovaci data contentu s ohledem na nastavenou pozici v hlavnim contentu
     */
    public function getContent()
    {
        // Nachystame prelozeni znacek
        $config = Zend_Registry::get('config');
		$urlHlpr = new Zend_View_Helper_Url();
		$baseUrl = $urlHlpr->url(array(),'default',true);
						
        $path = $baseUrl . $config->content_article->basepath.$this->id.'/';

        switch ($this->action)
        {
            case self::FIRST_PAGE :
                return array('consulting_html' => '?> '.Ibulletin_Marks::translate($this->html[0], $path),
                    'consulting_form' => '?> '.Ibulletin_Marks::translate($this->form, $path),
                    'consulting_questions' =>
                        $this->getQuestions(0, $this->questions_per_first_page));

            case self::ALL_REPLIES :
                return array('consulting_questions' =>
                    $this->getQuestions($this->offset, $this->questions_per_page));

            case self::REPLY :
                return array('consulting_question' =>
                    $this->getQuestion($this->questionID));

            default :
                return array('consulting_html' => '?> '.Ibulletin_Marks::translate($this->html[0], $path),
                    'consulting_form' => '?> '.Ibulletin_Marks::translate($this->form, $path),
                    'consulting_questions' =>
                        $this->getQuestions(1, $this->questions_per_first_page));
        }
    }

    /**
     *  Vrátí několik dotazů z DB. Vrací pouze zodpovězené dotazy. Tzn. ty,
     *  které mají show = true
     *
     *  @param Offset, kvůli stránkování.
     *  @param Limit, počet záznamů na stránku.
     */
    public function getQuestions($offset, $limit)
    {
        // TODO osetrit vyjimky

        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        $select = $db->select()
            ->from('consulting_questions',
                array(
                    'date' => 'to_char(replied, \''.$config->general->format->totimestamp.'\')',
                    '*')
                )
            ->where('show = TRUE')
            ->where('replied IS NOT NULL')
            ->where('content_id = ?', (int)$this->id)
            ->order('replied DESC')
            ->limit($limit, $offset);

        $result = $db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);

        $data = $this->createRepliesHrefs($this->replaceNewLines($result));

        foreach($data as $key => $val){
            try{
                $data[$key]->userData = Users::getUser($val->user_id);
            }
            catch(Users_User_Not_Found_Exception $e){
                $data[$key]->userData = array();
            }
        }

        return $data;
    }

    /**
     *  Vrátí data ke konrétnímu dotazu.
     *
     *  @param Identifikátor dotazu.
     */
    public function getQuestion($id)
    {
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        $select = $db->select()
            ->from('consulting_questions',
                array(
                    'date' => 'to_char(replied, \''.$config->general->format->totimestamp.'\')',
                    '*')
                )
            ->where('show = TRUE')
            ->where('id = ?', sprintf("%d", $id));

        $result = $db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);

        $result = $this->replaceNewLines($result);

        if(!empty($result)){
            $row = $result[0];
            $row->userData = Users::getUser($row->user_id);
            return $row;
        }
        else{
            return null;
        }
    }

    /**
     * Vrati selectbox pro vyber poradce
     *
     * @param  string $label       Label pro zadavaci pole.
     * @return Zend_Form_Element   Selectbox poradce.
     */
    public function getConsultantSel($label = null)
    {
        $db = Zend_Registry::get('db');

        // Pripravime selectbox konzultantu
        $sel = $db->select()
            ->from(array('c' => 'consulting_consultants'), 'name')
            ->join(array('n' => 'consulting_notifications'), 'c.id = n.consultant_id')
            ->where('n.content_id = ?', $this->id)
            ->order('name');
        $consultatntsA = $db->fetchAll($sel, array(), Zend_Db::FETCH_ASSOC);
        $consultatnts_sel = array();
        foreach ($consultatntsA as $var) {
            $consultatnts_sel[$var['consultant_id']] = $var['name'];
        }

        $consultants = new Zend_Form_Element_Select(array(
            'name' => 'consultant_id',
            'multioptions' => $consultatnts_sel
            ));
        $consultants->setValue($this->def_consultant);
        if(!empty($label)){
            $consultants->setLabel($label);
        }

        return $consultants;
    }

    /**
     *  Vytvoří odkaz pro zobrazení každé odpovědi.
     *  @param Pole odpovědí.
     */
    public function createRepliesHrefs($replies)
    {
        Zend_Loader::loadClass('Zend_View_Helper_Url');
        $url = new Zend_View_Helper_Url();

        for ($i = 0; $i < count($replies); $i++)
        {
            $replies[$i]->replyHref = $url->url(
                array(
                    'name' => $this->bulletinName,
                    'article' => $this->urlName,
                    $this->actionURL => self::REPLY,
                    'questionid' => $replies[$i]->id
                ),
                null,
                true
            );
        }

        return $replies;
    }

    /**
     *  Nahradí v dotazu i odpovědi znaky pro nový řádek jejich HTML
     *  ekvivalentem.
     *
     *  @param Pole odpovědí.
     */
    public function replaceNewLines($replies)
    {
        for ($i = 0; $i < count($replies); $i++)
        {
            $replies[$i]->reply = nl2br($replies[$i]->reply);
            $replies[$i]->question = nl2br($replies[$i]->question);
        }

        return $replies;
    }

    /**
     *  Vrátí počet dotazů k zobrazení. Kvůli stránkování.
     */
    public function getQuestionsNumber()
    {
        $db = Zend_Registry::get('db');
        $select = $db->select()
            ->from('consulting_questions', array('count' => 'COUNT(*)'))
            ->where('show = TRUE')
            ->where('content_id = ?', (int)$this->id)
            ->where('replied IS NOT NULL');

        try
        {
            $result = $db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
            return $result->count;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            Phc_ErrorLog::error('Consulting',
                'Nepodařilo se získat počet dotazů.'.$e);
        }
    }

    /**
     *  Uloží dotaz do DB.
     *
     *  @param Email toho kdo dotaz polozil.
     *  @param ID uživatele.
     *  @param Text dotazu.
     *  @param Id konzultanta
     *  @param Dalsi data k ulozeni do users_attribs
     */
    public function saveQuestion($email, $user_id, $question, $show = true, $consultant_id = null, $extendedData = array())
    {
        $show = $show ? 'true' : 'false';

        $ok = true;

        $db = Zend_Registry::get('db');
        $data = array(
            'content_id' => $this->id,
            'user_id' => $user_id,
            'question' => $question,
            'show' => $show
        );
        if(!empty($email)){
            $data['email'] = $email;
        }
        else{
            $data['email'] = '';
        }

        // Poradce
        if($consultant_id !== null){
            $data['consultant_id'] = (int)$consultant_id;
        }
        else{
            $data['consultant_id'] = (int)$this->def_consultant;
        }

        // Ulozeni dodatecnych dat do users_attribs
        $usersData = Ibulletin_Auth::getActualUserData();
        foreach($extendedData as $key => $val){
            try{
                if(!array_key_exists($key, $usersData)){
                    $db->insert('users_attribs', array('name' => $key, 'val' => $val, 'user_id' => $usersData['id']));
                }
                else{
                    $db->update('users_attribs', array('val' => $val),
                      'user_id = '.$usersData['id']." AND name ='".$key."'");
                    //echo "update users_attribs set val = '$val' where user_id = ".$usersData['id']." AND name ='".$key."'<br/>";
                }
            }
            catch(Exception $e){
                Phc_ErrorLog::warning('Consulting', "Nepodarilo se ulozit rozsirujici udaj '$key' s hodnotou '$val'. Puvodni vyjimka: ".$e);
                $ok = false;
            }
        }

        try
        {
            if(!$db->insert('consulting_questions', $data)){
                $ok = false;
            }
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            $ok = false;
            Phc_ErrorLog::error('Consulting', "Nepodarilo se ulozit dotaz z
            pravni poradny. Email: $email, Dotaz: $question, puvodni vyjimka:\n".$e);
        }

        return $ok;
    }

    /**
     * ajax service spracuje odeslani zpravy do poradny
     * @param object $req
     * @return xml
     */
    public function saveconsultingService($req) {

        $config_texts = Ibulletin_Texts::getSet('consulting');

        $email = $req->getParam('email');
        $message = $req->getParam('message');
        $user_id = IBulletin_Auth::getActualUserId();

        // TODO: ulozeni/update $req->getParam('username') aktualnimu uzivateli

        // Pripravime tridu s XML pro odpoved.
        $xml = new Ibulletin_XmlClass();
        $xml->__rootName = 'consulting';

        $validator = new Zend_Validate_EmailAddress();
        if (!$validator->isValid($email) || empty($email)) {
             $xml->wrongMail = 1;
             $xml->message = $config_texts->saveservice->wrongMail;

        }
        $ret = $this->saveQuestion($email, $user_id, $message);


        if ($ret) {
            $xml->message = (string)$config_texts->saveservice->ok; // Otypujeme jako string, aby se tam pri chybejicim textu nedal null a element tak nebyl odeslan
            $xml->ok = 1;
        } else {
            $xml->message = $config_texts->saveservice->error;
        }
        $content =  $xml->getXml();
        header("Content-Type: text/xml");
        echo $content;

        // odesle se email s potvrzenim vlozeni dotazu
        $this->sendAutoReply($email, $req);
    }

    public function getAjaxSaveUrl() {
        $urlHlpr = new Zend_View_Helper_Url();
        $url = $urlHlpr->url(array('srvname' => 'saveconsulting', 'contentid' => $this->id, 'page_id' => $this->page_id), 'service');
        return $url;
    }

    /**
     *  Odešle uživateli emails s potvrzením vložení dotazu do právní poradny.
     *
     *  @param Email, na který se odpověď má odeslat.
     */
    public function sendAutoReply($email, $req)
    {
        Zend_Loader::loadClass('Ibulletin_Auth');
        Zend_Loader::loadClass('Ibulletin_Mailer');
        Zend_Loader::loadClass('Ibulletin_Email');

        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');

        // Poradce
        $consultant_id = $req->getParam('consultant_id', null);
        if($consultant_id === null){
            $consultant_id = (int)$this->def_consultant;
        }

        //$auth = Ibulletin_Auth::getInstance();
        $user = Ibulletin_Auth::getActualUserData();

        try
        {
            $mailer = new Ibulletin_Mailer($config, $db);
            $mailer->setKeepSendingEH();
            $mailer->setIgnoreRegisteredAndSendEmails(true);
        }
        catch (IBulletinMailerException $e)
        {
            Phc_ErrorLog::error('Consulting',
                'nepodarilo se vytvorit instanci maileru.'.$e);
        }

        // Uzivateli mailujeme jen pokud je email jako requred pole
        if(in_array('email', $this->required_fields)){
            // pokud se adresa lisi od adresy, kterou ma uzivatel ulozenou
            if (strcmp(strtolower($email), strtolower($user['email'])))
            {
                // najdeme email k odeslani
                $select = $db->select()
                    ->from('emails')
                    ->where('id = ?', $mailer->getConsultingAutoReplayId());

                $row = $db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
                if (!empty($row->id))
                {
                    $mail = Ibulletin_Email::createInstance($row->body);
                }
                else
                {
                    Phc_ErrorLog::error('Consulting', 'nepodarilo se nalezt
                        email pro automatickou odpoved');
                    return;
                }


                // najdeme radek z users pro konkretniho uzivatele
                $select = $db->select()
                    ->from('users')
                    ->where('id = ?', $user['id']);
                $row = $db->fetchRow($select,array(), Zend_Db::FETCH_OBJ);
                if (!empty($row->email))
                {
                    // prepise se email na ten, ktery uzivatel zadal v pravni
                    // poradne
                    $row->email = $email;
                }
                else
                {
                    Phc_ErrorLog::error('Consulting',
                        "nepodarilo se nalezt uzivatele, id: $user[id]");
                }

                try
                {
                    $db->beginTransaction();
                    // nastaveni return path
                    $mailer->prepareSending();
                    // zaradi se email do users_emails
                    $ue_ids = $mailer->enqueueEmail($mail->getId(), "id = $user[id]");
                    $ue_id = $ue_ids[0];
                    
                    $sel = $db->select()->from(array('ue' => 'users_emails'))->where('ue.id = ?', $ue_id);

                    //user email pro doplneni token
                    $user_email = $db->fetchRow($sel);
                    
                    // metoda sendMail potrebuje nastavene tyto vlastnosti
                    $row->email_id = $mail->getId();
                    $row->users_emails_id = $ue_id;
                    $row->token = $user_email['token'];
                    
                    // nahrazeni znacek v tele mailu
                    $mailer->getMailerTags()->parseTags($row, $mail);
                    // vytvoreni instance Zend_Mail
                    $mail = $mail->getZendMail();
                    // odeslani mailu
                    $mailer->sendMail($row, $mail);
                    $db->commit();
                }
                catch (Exception $e)
                {
                    Phc_ErrorLog::error('Consulting',
                        'nepodařilo se odeslat email'.$e);
                    $db->rollBack();
                }
            }
            else
            {
                Phc_ErrorLog::debug('maildeb', 'addre is same');
                try
                {
                    $filter = "id = $user[id]";
                    $usersEmailsIds = $mailer->enqueueEmail($mailer->getConsultingAutoReplayId(), $filter);
                    Phc_ErrorLog::debug('maildeb', 'autoreply ID:'.$mailer->getConsultingAutoReplayId().'.');
                }
                catch (IBulletinMailerException $e)
                {
                    Phc_ErrorLog::error('Consulting',
                        'nepodařilo se zařadit email potvrzující vložení dotazu do poradny.'.$e);
                }

                try
                {
                    if(!empty($usersEmailsIds)){
                        $usersEmailsId = current($usersEmailsIds);
                        $mailer->sendEmails($usersEmailsId);
                    }
                    else{
                        Phc_ErrorLog::error('Consulting',
                        'nepodařilo se zařadit email potvrzující vložení dotazu do poradny - '.
                        'enqueueEmail() nevratilo zadne ID.'.$e);
                    }
                }
                catch (Exception $e)
                {
                    Phc_ErrorLog::error('Consulting',
                        'Během odesílání emailů v právní poradně nastaly chyby.'.$e);
                }

                if ($mailer->anyErrors())
                    Phc_ErrorLog::error('Consulting',
                        'Během odesílání emailů v právní poradně nastaly chyby.');
            }
        }

        try
        {
            // jeste se poslou emaily jako upozorneni na novy dotaz
            $email = $mailer->getConsultingEmail(Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION);
            if (!empty($email))
            {
                if(is_numeric($consultant_id)){
                     $consultantQ = sprintf('(consultant_id = %d OR consultant_id is null)', $consultant_id);
                }
                else{
                    $consultantQ = 'consultant_id is null';
                }

                $usersEmailsIds = $mailer->enqueueEmail(
                    $email->id,
                    'id IN (SELECT user_id FROM consulting_notifications WHERE '.
                    ' role = \''.Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION.'\' '.
                    " AND $consultantQ)"
                );
                if(!empty($usersEmailsIds)){
                    $usersEmailsId = current($usersEmailsIds);
                    $mailer->sendEmails($usersEmailsId);
                }
                else{
                    Phc_ErrorLog::error('Consulting',
                        'Během odesílání oznámení o novém dotazu nastaly chyby.');
                }
            }
        }
        catch (IBulletinMailerException $e)
        {
            Phc_ErrorLog::error('Consulting',
                'Během odesílání oznámení o novém dotazu nastaly chyby.'.$e);
        }

        if ($mailer->anyErrors())
            Phc_ErrorLog::error('Consulting',
                'Během odesílání oznámení o novém dotazu nastaly chyby.');
    }

    /**
     * Varati vstupni retezec s nahrazenymi znackami za spravny kod
     * Aktualne prklada znacky:
     * %%static%% - nahradi za cestu do adresare statickeho obsahu daneho contentu
     *
     * @param string vstupni retezec
     * @return string retezec s prelozenzmi znackami
     */
    public function translateMarks($string)
    {
        $config = Zend_Registry::get('config');

        // Nahradime cestu do statickych dat
        $path = $config->content_article->basepath.'/'.$this->id.'/';
        $token = '%%static%%';
        $out = str_ireplace($token, $path, $string);

        return $out;
    }
   
}
