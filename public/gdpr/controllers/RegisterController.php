<?php
/**
 * Stranka s registraci noveho uzivatele.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class RegisterController extends Zend_Controller_Action
{
    /**
     * Regularni vyraz pouzity ke kontrole mailove adresy.
     */
    var $mailRegex = '^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.(([0-9]{1,3})|([a-zA-Z]{2,3})|(aero|coop|info|museum|name))$';


    /**
     * Stara se o to, aby nemohl byt pouzit nepovoleny zpusob registrace.
     * Pokud se uzivatel pokusi pristoupit na nepovolenou vstupni stranku, je
     * presmerovan na defaultni z configu.
     *
     * Pokud je prihlasen uzivatel, ale je nastaven parametr ignoreloggedin na pozitivni hodnotu,
     * bude uzivateli umoznen pristup na registracni akci.
     * !!ZATIM je pro toto prizpusobena pouze akce registerAction().
     */
    public function preDispatch()
    {
    $action = strtolower($this->_getParam('action'));
        $config = Zend_Registry::get('config');

        if($action != 'empty' && !$this->_getParam('ignoreloggedin')){
            $this->_checkLoggedIn();
        }

        // Zkontrolujeme, jestli vubec existuje nejaky bulletin, do ktereho
        // lze uzivatele prihlasit, pokud ne, zobrazime pouze info
        $bulletins = new Bulletins();
        if(!$bulletins->existsAnyValidBulletin() && $action != 'empty'){
            // Presmerujeme na novou akci
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('empty', 'index');

            return;
        }

        $forbidden_actions = explode(',',$config->general->forbidden_came_in_pages);
        foreach($forbidden_actions as $key => $val){
            $forbidden_actions[$key] = strtolower(trim($val));
        }

        if($action != $config->general->come_in_page && in_array($action, $forbidden_actions)){
            // Tato akce je nepovolena, presmerujeme na defaultni controller
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit(null, null);
        }
    }


    public function indexAction()
    {
         $config = Zend_Registry::get('config');
         $req = $this->getRequest();

         $action = $config->general->come_in_page;

         // Spustime zpusob registrace podle nastaveni v configu
         if($action == 'emailregister'){
             $this->_forward('emailregister');
             return;
         }
         elseif($action == 'emaillogin'){
             $this->_forward('emaillogin');
             return;
         }

         // Pokud nebyla vybrana zadna akce, vyhodime vyjimku
        throw new Zend_Controller_Action_Exception('Nepodarilo se vybrat registracni akci podle config.ini - zadana akce je "'.$action.'".', 404);
    }


    /**
     * Provede registraci uzivatele po tom, co zada email
     * a posle uzivateli registracni email. Uzivateli je ohlasen uspech/
     * neuspech akce a je informovan, ze do bulletinu se dostane pomoci
     * emailu, ktery mu byl odeslan.
     *
     * @param bool  Prihlasit uzivatele a presmerovat do aktualniho cisla?
     */
    public function emailregisterAction($redirect = false)
    {
        $config = Zend_Registry::get('config');
        $texts = Ibulletin_Texts::getSet('register');
        $req = $this->getRequest();
        $this->view->main = $texts->index->main;
        $this->view->rights = $texts->index->rights;

        //zjistime si nastaveni set send emails z config pro registraci uzivatele
        if ($config->general->register_action->set_send_emails == 0 || $config->general->register_action->set_send_emails == 2) {
            $set_send_emails = false;
        } else {
            $set_send_emails = true;
        }

        //pokusime se registrovat uzivatele
        if($req->getMethod() == 'POST'){
            $agreement = trim($req->getParam('agreement'));
            $email = trim($req->getParam('email'));

            // Zkontrolujeme validitu mailu, pokud projde registrujeme, jinak info uzivateli
            $emailValidator = new Zend_Validate_EmailAddress();
            if(!$emailValidator->isValid($email) || empty($email)){
                // Vadna adresa, vypiseme info a vyrenderujeme znovu registracni stranku
                $this->view->loginError = $texts->index->wrongemail;

                $input['email'] = $email;

                $this->view->input = $input;

                // vyskocime ven
                return;
            }

            // zkontrolujeme zaskrtnuti agreement checkboxu
            if(!$agreement){
                $this->view->loginError = $texts->index->missingagreement;

                $input['email'] = $email;

                $this->view->input = $input;
                // vyskocime ven
                return;
            }


            // Provedeme registraci noveho uzivatele (obsluhuje odeslani
            // e-mailu - je-li pozadovano
            $email = strtolower($email);



            $registrated = Ibulletin_Auth::registerUser(array('email' => $email), $redirect, $set_send_emails);

            if(!$registrated){
                $this->view->loginError = $texts->index->loginerror;

                //$this->getHelper('viewRenderer')->setScriptAction('index');
                return;
            }
            else{
                // Ziskame redirector
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                // Presmerujeme na potvrzeni registrace
                $_SESSION['email'] = $email ;
                $redirector->gotoAndExit('emaildone', 'register');

                // Pouze pro testovani odesilani mailuu
                //$this->getHelper('viewRenderer')->renderScript('info.phtml');
            }
        }else if($req->getMethod() == 'GET'){

            //vrati formular s predvyplnenym emailem
            $returnForm = function($view,$email,$hash=0) {
                if ($hash) {
                    $email = "";
                }
                $input['email'] = $email;

                $view->input = $input;
            };

            $email = strtolower(trim($req->getParam('email')));
            // Zkontrolujeme validitu mailu, pokud projde registrujeme, jinak info uzivateli
            $emailValidator = new Zend_Validate_EmailAddress();

            $md5Pattern = '/^[a-f0-9]{32}$/';

            //jestli parametr email neni platna adresa nebo hash vratime formular
            if((!preg_match($md5Pattern, $email) && !$emailValidator->isValid($email))
                    || ($config->general->allow_only_email_hash && !preg_match($md5Pattern, $email))
                    || empty($email)){

                // Vadna adresa, vypiseme info a vyrenderujeme znovu registracni stranku
                if(!$emailValidator->isValid($email)){
                    $this->view->loginError = $texts->index->wrongemail;
                }

                return $returnForm($this->view,$email);
            }

            //overeni ze uzivatel s timto emailem existuje a ma spravne nastaveni,
            //v opacnem pripade neprihlasujeme a zobrazime formular, predvyplnime email
            try {

                if (preg_match($md5Pattern, $email)) {
                    $user = Users::getUser(null, null, null, null, $email);
                    if ($user) {
                        $email = $user['email'];
                    }
                } else {
                    $user = Users::getUser(null, $email);
                }

                if ($user && ($user['target'] == false || $user['send_emails'] == false || !empty($user['deleted']) || !empty($user['unsubscribed']))) {
                    return $returnForm($this->view, $email, preg_match($md5Pattern, $email));
                }

            } catch (Exception $ex) {
                return $returnForm($this->view, $email, preg_match($md5Pattern, $email));
            }


             // Provedeme registraci noveho uzivatele (obsluhuje odeslani
            // e-mailu - je-li pozadovano
            $registrated = Ibulletin_Auth::registerUser(array('email' => $email), $redirect, $set_send_emails);

            if(!$registrated){
                $this->view->loginError = $texts->index->loginerror;

                //$this->getHelper('viewRenderer')->setScriptAction('index');
                return;
            }
            else{
                // Ziskame redirector
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                // Presmerujeme na potvrzeni registrace
                $_SESSION['email'] = $email ;
                $redirector->gotoAndExit('emaildone', 'register');
            }
        }
    }


    /**
     * Zobrazi hlasku o uspesnem odeslani e-mailu na uzivateluv email.
     */
    public function emaildoneAction()
    {

        $texts = Ibulletin_Texts::getSet();
        $msg = $texts->infomessage;
        $this->view->message = str_replace("%%email_registered%%", $_SESSION['email'], $msg );
        // Pokud je v textech i H1, pridame jej do view
        if(!empty($texts->infoH1)){
            $this->view->infoH1 = $texts->infoH1;
        }

        unset($_SESSION['email']);

        $this->getHelper('viewRenderer')->renderScript('info.phtml');
        return;
    }


    /**
     * Provede registraci uzivatele po tom, co zada email
     * a posle uzivateli registracni email. Uzivatel je po registraci
     * prihlasen a presmerovan do aktualniho cisla.
     */
    public function emailloginAction()
    {
        $this->emailregisterAction(true);
        $this->getHelper('viewRenderer')->setScriptAction('emailregister');
    }


	/**
	 * Provede registraci uzivatele s formularem pro GDPR
	 */
	public function gdprAction()
	{
		$this->registerAction();
		$this->getHelper('viewRenderer')->setScriptAction('gdpr');
	}


    /**
     * Provede registraci uzivatele zadanim jeho osobnich udaju a posle mu email.
     * Podle nastaveni v configu register_action.redirect bude provedeno presmerovani do webu
     * nebo na stranku potvrzujici odeslani emailu uzivateli.
     *
     * Pokud je použito s již přihlášeným uživatelem, provede updat jeho dat.
     *
     * Nastavuje do view:
     * (bool)passesNotSame
     * (bool)loginUsed
     * (bool)emailAlreadyExists
     * (bool)dontShowForm
     *
     * POZOR v pripade neexistence konstanty redirect v configu je brana jako TRUE
     */
    public function registerAction()
    {

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $texts = Ibulletin_Texts::getSet('register');
        $session = Zend_Registry::get('session');
        $req = $this->getRequest();

        //zjistime si nastaveni set send emails z config pro registraci uzivatele
        if ($config->general->register_action->set_send_emails == 0 || $config->general->register_action->set_send_emails == 2) {
            $set_send_emails = false;
        } else {
            $set_send_emails = true;
        }

		$this->view->main = $texts->index->main;
		$this->view->rights = $texts->index->rights;

		if($req->isGet()){

            $email = trim($req->getParam('email'));
            $specializace = trim($req->getParam('specializace'));
            // Zkontrolujeme validitu mailu, pokud projde registrujeme, jinak info uzivateli
            $emailValidator = new Zend_Validate_EmailAddress();

            if(!$emailValidator->isValid($email) || empty($email)){
                // Vadna adresa, vypiseme info a vyrenderujeme znovu registracni stranku
                if(!empty($email)){
                    $this->view->loginError = $texts->index->wrongemail;
                }

                $input['email'] = $email;

                $this->view->input = $input;

                // vyskocime ven
                return;
            }

            // Provedeme registraci noveho uzivatele (obsluhuje odeslani
            // e-mailu - je-li pozadovano
            $email = strtolower($email);
            $specializace = strtolower($specializace);

            $attribs = array('email' => $email);
            if(!empty($specializace))
                $attribs['specializace'] = $specializace;


            //overeni ze uzivatel s timto emailem existuje a ma spravne nastaveni,
            //v opacnem pripade neprihlasujeme a zobrazime formular, predvyplnime email
            try {
                $user = Users::getUser(null, $email);

                if ($user && ($user['target'] == false || $user['send_emails'] == false || !empty($user['deleted']) || !empty($user['unsubscribed']))) {
                    $user_exists = false;
                } else {
                    $user_exists = true;
                }

            } catch (Exception $ex) {
                $user_exists = false;
            }

            if (!$user_exists) {
                $input['email'] = $email;
                $this->view->input = $input;
                return;
            }


            $registrated = Ibulletin_Auth::registerUser($attribs, false, $set_send_emails);

            if(!$registrated){
                $this->view->loginError = $texts->index->loginerror;

                //$this->getHelper('viewRenderer')->setScriptAction('index');
                return;
            }
            else{
                // Ziskame redirector
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                // Presmerujeme na potvrzeni registrace
                $_SESSION['email'] = $email ;
                $redirector->gotoAndExit('emaildone', 'register');
            }
        }

        // Pokud nabizime registracni form jiz prihlasemenu uzivateli, potrebujeme jeho ID
        $actualUserData = Ibulletin_Auth::getActualUserData();

        //pokusime se registrovat uzivatele
        if($req->getParam('hash')){
            $hash = $req->getParam('hash');
            if(isset($session->existingForms) && $session->existingForms[$hash]){
                $form = unserialize($session->existingForms[$hash]);

                // Repair some passed values - this is necessary because filters (stringTrim) are done after validation
                if(isset($_POST['email'])){
                    $_POST['email'] = strtolower(trim($req->getParam('email')));
                }

                $valid = $form->isValid($_POST);
                $this->view->form = $form;

                $inValid = false;

                // Validace
                if(!$valid){
                   $inValid = true;
                }

                // Login a heslo kontrolujeme pouze pokud jsou ve formulari
                if($form->getElement('pass') && $form->getElement('login')){
                    // Kontrola shody hesel
                    if($req->getParam('pass') != $req->getParam('passAgain_exclude_elem')){
                       $this->view->passesNotSame = true;
                       $form->getElement('pass')->setValue('');
                       $form->getElement('passAgain_exclude_elem')->setValue('');
                       $inValid = true;
                    }
                    // Kontrola neexistence loginu
                    $sel = new Zend_Db_Select($db);
                    $sel->from('users', array('id'))
                        ->where('login = ?', $req->getParam('login', ''))
                        ;
                    $loginInDb = $db->fetchOne($sel);
                    if((empty($actualUserData) && $loginInDb) || ($loginInDb && $loginInDb != $actualUserData['id'])){ // Pokud je to login prave prihlaseneho usera, je to OK
                        $this->view->loginUsed = true;
                        $inValid = true;
                    }

                    // Kontrola zda dany uzivatel neni jiz registrovan s jinym loginem a heslem
                    try{
                        $userData = Users::getUser(null, $req->getParam('email'));
                        // Pokud se jedna o prave prihlaseneho uzivatele, je nam jedno, ze uz ma neco nastaveneho
                        if(!empty($actualUserData) && $actualUserData['id'] && $userData['login'] != $actualUserData['id']){
                            // Zrusime hlasku o jiz pouzivanem loginu
                            if(!empty($userData['login']) && $userData['login'] == $req->getParam('login')){
                                $this->view->loginUsed = false;
                                $inValid = false;
                            }
                            // Zkusime jestli jiz nema vyplnene jmeno a heslo
                            if(!empty($userData['login']) && !empty($userData['pass'])
                                && ($userData['login'] != $req->getParam('login')
                                || $userData['pass'] != md5($req->getParam('pass'))))
                            {
                                // Uzivatel se registruje znovu s jinym loginem ci heslem, ohlasime a konec
                                $this->view->dontShowForm = true;
                                $this->view->emailAlreadyExists = true;
                                $inValid = true;
                            }
                        }
                    }
                    catch(Users_User_Not_Found_Exception $e){
                        // Nic nedelame, protoze pokud uzivatel s danym mailem neexistuje, pokracujeme normalne dal
                    }
                }
                /*
                // Kontrola kvuli authcode, pouziva se pri vicestrankovem overovani uzivatele
                // musime zamezit krizeni emailu a authcode ruznych uzivatelu
                // pro jiny email pro zadany autchode nahlasime chybu
                if($form->getElement('authcode')){
                    try{
                        $userData = Users::getUser(null, null, null, $req->getParam('authcode'));
                        print_r($userData);
                        exit;
                        if((!empty($userData['email']) && $req->getParam('email') != null &&  $userData['email'] != $req->getParam('email')) ||
                            (!empty($userData['login']) && $req->getParam('login') != null && $userData['login'] != $req->getParam('login')))
                        {
                            $this->view->dontShowForm = true;
                            $this->view->emailAlreadyExists = true;
                            $inValid = true;
                        }
                    }
                    catch(Users_User_Not_Found_Exception $e){
                        // Nic nedelame, neexistuje kolidujici uzivatel
                        //throw $e;
                    }
                }
                */

                if($inValid){
                    return;
                }
            }
            else{
                return;
            }


            //
            // Nacteme vsechny parametry z formulare
            //
            $attribs = array();
            $usersUnqAttribs = array(); // Predavaji se unikatni klice uzivatele do registerUser
            // Authcode pokud je, musi byt na zacatku
            if($form->getElement('authcode') && trim($form->getElement('authcode')->getValue()) != ''){
                $attribs['authcode'] = $form->getElement('authcode')->getValue();
                $usersUnqAttribs[] = 'authcode';
            }
            $attribs['email'] = strtolower(trim($req->getParam('email')));
            $usersUnqAttribs[] = 'email';
            $elements = $form->getElements();
            foreach($elements as $elem){
                $name = $elem->getName();
                // Email preskocime, musi byt na prvnim miste
                if($name == 'email' || preg_match('/_exclude_elem/', $name) || $name == 'submit_' || $name == 'hash' || !strlen($elem->getValue()) ){
                    continue;
                }

                $attribs[$name] = $elem->getValue();
            }

            // Vyhodime vsechny fieldy ktere maji nazev %field%_alternate a (jeste predtim)
            // pokud  %field% = 'other' tak nastavime %field% na hodnotu %field%_alternate
            foreach($attribs as $key=>$val){
                if ($len = strpos($key, '_alternate')) {
                    $parent_key = substr($key, 0, $len);
                    if ($attribs[$parent_key] == 'other') {
                      $attribs[$parent_key] = $val;
                    }
                    unset($attribs[$key]);
                }
            }

            // donastavime subscribe, undelete, bad_addr
            $attribs['bad_addr'] = false;
            $attribs['unsubscribed'] = null;
            $attribs['deleted'] = null;

            //Phc_ErrorLog::error('Attr dump', var_export($attribs, true));

            // Podle configu zjistime, jestli se ma redirectovat do webu,
            // pokud neni promenna redirect nastavena, bereme jako TRUE kvuli kompatibilite
            if(isset($config->general->register_action->redirect)){
                $redirect = (bool)$config->general->register_action->redirect;
            }
            else{
                $redirect = true;
            }

            // Pokud neni nikdo prihlasen, muzeme provest primo registraci noveho uzivatele
            if(empty($actualUserData)){
                // Provedeme registraci noveho uzivatele (obsluhuje odeslani
                // e-mailu - je-li pozadovano
                try{
                    $registrated = Ibulletin_Auth::registerUser($attribs, $redirect, $set_send_emails, true, $usersUnqAttribs);
                }
                catch(Ibulletin_Auth_User_Unique_Violation_Exception $e){
                    // Pokud je uzivatel kolizni na vice unikatnich klicich, nastavime do view priznak pro ohlaseni chyby
                    $this->view->dontShowForm = true;
                    $this->view->emailAlreadyExists = true;
                    return;
                }
            }
            // Pokud je nekdo prihlasen, provedeme jen upravu udaju
            else{
                try{
                    $registrated = Users::updateUser($actualUserData['id'], $attribs);

                    // Pokud je v session ulozeno, kam ma byt po registraci redirectovano (napriklad z tokenacces), redirectujeme
                    $session = Zend_Registry::get('session');
                    if(isset($session->registerActionRedirectParams)){
                        $params = $session->registerActionRedirectParams;
                        unset($session->registerActionRedirectParams);
                        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                        $redirector->gotoRouteAndExit($params, null, true);
                    }
                }
                catch(Exception $e){
                    Phc_ErrorLog::warning('RegisterController::registerAction()', 'Nepodarilo se upravit data prihlaseneho uzivatele. '.
                        "Puvodni vyjimka:\n".$e);
                    $registrated = false;
                }
            }

            if(!$registrated){
                $this->view->loginError = $texts->loginerror;

                //$this->getHelper('viewRenderer')->setScriptAction('index');
                return;
            }
            /*
            else{
                // Ziskame redirector
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                // Presmerujeme na potvrzeni registrace
                $_SESSION['email'] = $email ;
                $redirector->gotoAndExit('emaildone', 'register');

                // Pouze pro testovani odesilani mailuu
                //$this->getHelper('viewRenderer')->renderScript('info.phtml');
            }
            */

            // Provedeme presmerovani na informacni stranku o zaslani emailu
            // Ziskame redirector
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            // Presmerujeme na potvrzeni registrace
            $_SESSION['email'] = $req->getParam('email') ;
            $redirector->gotoAndExit('emaildone', 'register');
        }
    }

    /**
     * Poslani zapomenuteho hesla (nastaveni noveho hesla) pomoci specialniho emailu
     */
    public function forgotpassAction(){
        $req = $this->getRequest();
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet();

        $form = new Zend_Form();
        $form->setMethod('post');

        $form->addElement('text', 'email', array(
                'required' => true,
                'validators' => array(
                    array('EmailAddress', true,
                         array('messages' => array())),
                )
        ));

        //$form->addElement('submit', 'submit_', array('value' => 'ODESLAT'));

        $this->view->form = $form;
        if($req->getParam('email', null) !== null){
            $valid = $form->isValid($_POST);

            if(!$valid){
                return;
            }

            // Najdeme data uzivatele
            try{
                $user = Users::getUser(null, $req->getParam('email'));
                $userId = $user['id'];
            }
            catch(Users_User_Not_Found_Exception $e){
                // Nenalezen uzivatel
                $this->view->userNotFound = true;
                return;
            }
            // Uzivatel nema login, taky jej zapreme
            if(empty($user['login'])){
                $this->view->userNotRegistered = true;
                return;
            }



            // Pokud neni povoleno pracovat s plain hesly
            // musime vyrobit nove heslo a nastavit jej, protoze heslo ukladame jen jako HASH
            if(empty($config->general->password_save_as_plain)){
                $rsg = new Ibulletin_RandomStringGenerator();
                $password = $rsg->get(8);
                Ibulletin_Auth::changeUsersData(array('pass' => $password), $user['id']);
            }
            else{
                $password = $user['pass_plain'];
            }


            // Najdeme email pro odeslani
            $select = new Zend_Db_Select($db);
            $select->from(array('e' => 'emails', 'email_id'))
                   ->where("e.special_function = 'forgotpass'");
            $emailId = $db->fetchOne($select);
            // Neexistuje email k pouziti jako forgotpass, musime zaznamenat error
            if(empty($emailId)){
                Phc_ErrorLog::error('RegisterController::forgotpassAction()', 'Nepodarilo se najit '.
                    'email pro zaslani hesla uzivateli.');

                    $this->view->emailSentMessage = $texts->cannotSendEmai;
                    return;
            }


            // Zaradi email do fronty pro odeslani
            try {
                $mailer = new Ibulletin_Mailer($config, $db);
                // Nastaveni pokracovat v odesilani i pri chybach
                $mailer->setKeepSendingEH();
                // Posilat email i smazanym uzivatelum
                $mailer->sendDeletedToo();
                // Ignorujeme, jestli je uzivatel registrovan a ma nastaveno send_emails
                $mailer->setIgnoreRegisteredAndSendEmails(true);
                // Nastavime FORWARD NAME a TEXT
                $mailer->getMailerTags()->addTag('forgotPassLogin', $user['login']);
                $mailer->getMailerTags()->addTag('forgotPassPass', $password);

                // Filtr pro vybrani a pridani pouze jednoho uzivatele
                $filter = "id = $userId";
                // Zaradime do fronty
                $mailer->enqueueEmail($emailId, $filter);
                // Ziskame id mailu k odeslani z users_emails
                $users_emails_id = $db->lastInsertId('users_emails', 'id');

                // Posleme jeden mail
                $mailer->sendEmails($users_emails_id);

                $this->view->emailSentMessage = $texts->emailSent;
            }
            catch (IBulletinMailerException $e) {
                $this->view->emailSentMessage = $texts->cannotSendEmai;
            }

        }

    }


    /**
     * Zkontroluje, jestli neni uzivatel prihlasen, pokud je a je zapnute
     * tvrde odhlasovani, presmeruje uzivatele na aktualni bulletin.
     */
    private function _checkLoggedIn()
    {
         $config = Zend_Registry::get('config');

        // Zjistime, jestli je nekdo prihlasen, a jestli je zapnute tvrde odhlasovani
        // pokud ano, presmerujeme na aktualni bulletin
        $user_data = Ibulletin_Auth::getActualUserData();
        if($user_data !== null && $config->general->hard_logoff){
            $bulletins = new Bulletins();
            $bulletins->redirectToActualBulletin(true);
        }
    }


	/**
	 * Validator pro registracni formular GDPR
	 * @param $value
	 * @param $formValues
	 * @return bool
	 */
	public static function specializationPhysicianValidate($value, $formValues) {
    	if ($formValues['profession'] == "Lékař" && empty($value)) {
    		return false;
		} else {
			return true;
		}

	}

	/**
	 * Validator pro registracni formular GDPR
	 * @param $value
	 * @param $formValues
	 * @return bool
	 */
	public static function specializationPharmacistValidate($value, $formValues) {
		if ($formValues['profession'] == "Lékárník" && empty($value)) {
			return false;
		} else {
			return true;
		}

	}

}
