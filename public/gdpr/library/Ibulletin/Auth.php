<?php

/**
 * iBulletin - Auth.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

class Ibulletin_Auth_Register_User_Already_Exists_Exception extends Exception {}
class Ibulletin_Auth_Register_Action_Exception extends Exception {}
class Ibulletin_Auth_Send_Special_Email_Exception extends Exception {}
class Ibulletin_Auth_No_User_Specified_Exception extends Exception {}
class Ibulletin_Auth_Invalid_Email_Exception extends Exception {}
class Ibulletin_Auth_User_Unique_Violation_Exception extends Exception {}

/**
 * Trida poskytujici funkcionality tykajici se autentizace do uzivatelskeho webu.
 * Tato trida existuje pouze v jedne instanci, k jejimu ziskani
 * se pouziva saticka metoda getInstance().
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Auth
{
    /**
     * Instance tridy.
     *
     * @var Ibulletin_Auth
     */
    private static $_inst = null;

    /**
     * Id aktualne prihlaseneho uzivatele, dostupne po provedeni self::prepare()
     *
     * @var int
     */
    public $actual_user_id = null;

    /**
     * @var array   Data aktualne prihlaseneho uzivatele
     */
    public $actualUserData = null;

    /**
     * Vrati existujici instanci Ibulletin_Auth nebo vytvori novou a tu vrati.
     *
     * @return Ibulletin_Auth Jedina instance Ibulletin_Auth
     */
    public static function getInstance()
    {
        if(self::$_inst === null){
            self::$_inst = new Ibulletin_Auth();
        }

        return self::$_inst;
    }


    /**
     * Provede overeni, jestli je uzivatel autentizovan podle session
     * a ulozi ID uzivatele do self::$user_id pro pozdejsi pouziti.
     * V pripade pokusu o vstup do casti vyzadujici overeni bez overeni
     * presmeruje uzivatele na stranku podle konfigurace.
     */
    public static function prepare()
    {
        $inst = Ibulletin_Auth::getInstance();
        $config = Zend_Registry::get('config');
        $session = Zend_Registry::get('session');
        $auth = Zend_Auth::getInstance();
        $db = Zend_Registry::get('db');

        if(isset($session->actual_user_id)){
            // Uzivatel je jiz prihlasen
            $inst->actual_user_id = $session->actual_user_id;
            return;
        }
        else{
            if($config->general->allow_cookie_auth){
                // pokusime se prihlasit uzivatele pomoci cookie
                if($inst->authByCookie()){
                    return;
                }
            }

            // Zkontrolujeme, jestli uzivatel neni prihlasen v adminu, pokud ano,
            // prihlasime uzivatele odpovidajiciho uzivateli v adminu, kdyz neni definovan,
            // pokracujeme bez prihlaseni.
            if($auth->hasIdentity()){
                $admin_auth = Ibulletin_AdminAuth::getInstance();
                $identity = $auth->getIdentity();
                $admin_id = $identity->user_id;

                // prihlasime uzivatele
                $success = $inst->setUser($admin_id);

                if($success){
                    // presmerujeme na aktualni bulletin
                    //$bulletins = new Bulletins();
                    //$bulletins->redirectToActualBulletin(true);

                    return;
                }
            }

            // Pokud jsme na strance vyzadujici autentizaci, presmerujeme
            // na forbidden_access_redir.controller s akci forbidden_access_redir.action
            // ktere jsou zadany v konfiguraci.
            $authenticated_routes = isset($config->authentized_route_names->auth_route) ?
                $config->authentized_route_names->auth_route->toArray() : array();

            $fc = Zend_Controller_Front::getInstance();
            $router = $fc->getRouter();
            $req = $fc->getRequest();

            //jmeno aktualniho controlleru
            $current_controller = $req->getControllerName();

            $controllerAuthentized = false;
            foreach($authenticated_routes as $route_name){
                $controller = $router->getRoute($route_name)->getDefault('controller');
                // pokud je aktualni controller na seznamu autentizovanych cest,
                // presmerujeme na forbidden_access_redir.controller s akci
                // forbidden_access_redir.action
                if($current_controller == $controller){
                    $controllerAuthentized = true;
                    break;
                }
            }

            // Pokud je stranka autentizovana, jeste zkontrolujeme, jestli neni pozadovana
            // autentizace lightboxem
            if($controllerAuthentized){
                if(trim($config->general->come_in_page) != 'lightboxconfirm' &&
                    !$req->getParam('islightboxconfirm', null))
                {
                    $inst->redirectToUnauth();
                }
                // Pridame JS pro autentizaci lightboxem a lightbox povolime ve view.
                // Pokud je ovsem zobrazovan bulletin a ne primo clanek
                elseif(!$req->getParam('article', false)){
                    $viewRenderer = Zend_Controller_Action_HelperBroker::getExistingHelper('ViewRenderer');
                    $view = $viewRenderer->view;
                    $view->display_lightboxauthconfirm = true;
                    Ibulletin_Js::addJsFile('lightbox.js');
                }
                // Pokud je zobrazen primo clanek, presmerujeme na bulletin
                else{
                    $inst->redirectToAuth();
                    //$inst->redirectToActualBulletin();
                }
            }

            // Stranka neni na seznamu autentizovanych routes - muze se pokracovat
            return;
        }
    }

    /**
     * Vrati data aktualniho uzivatele, nebo null, pokud neni nikd prihlasen
     *
     * @param bool $force   Vzdy nacist primo z DB, default false
     *
     * @return array Radek z tabulky users aktualniho uzivatele nebo null, pokud uzivatel neni
     */
    public static function getActualUserData($force = false)
    {
        $db = Zend_Registry::get('db');
        $inst = Ibulletin_Auth::getInstance();
        if(!is_numeric($inst->actual_user_id)){
            return null;
        }

        // Pokud jsou data vyplnena - mela by byt vzdy, vratime z vlastnosti objektu
        if($inst->actualUserData && $inst->actual_user_id == $inst->actualUserData['id']  && !$force){
            return $inst->actualUserData;
        }

        /*
        // Najdeme z DB radek uzivatele
        $q = 'SELECT * FROM users WHERE id = '.$inst->actual_user_id;
        $rows = $db->fetchAssoc($q);

        reset($rows);
        $row = current($rows);
        */
        $row = Users::getUser($inst->actual_user_id);

        // Ulozime
        $inst->actualUserData = $row;
        $inst->actual_user_id = $inst->actualUserData['id'];

        return $row;
    }

    /**
     * Nastavi uzivatele v teto session na uzivatele se zadanym ID
     *
     * @param int ID uzivatele z tabulky users
     * @return bool Povedlo se prihlasit?
     */
    public static function setUser($id)
    {
        $inst = self::getInstance();

		try {
			$userdata = Users::getUser($id);
		} catch (Users_User_Not_Found_Exception $ex) {
			return false;
		}

        $inst->actualUserData = $userdata;

        // Overime, jestli neni existujici session prohlizece vlastnena jinym uzivatelem
        // pokud ano, musime vytvorit pro tohoto uzivatele novou session
        if(!empty($inst->actual_user_id) && $inst->actual_user_id != $id){
            // Ulozime page_views timestamp z ukoncovane session
            $pvTimestamp = Ibulletin_Stats::getInstance()->getPageViewsTimestamp();
            // Odhlasime a odstranime objekt Stats
            $inst->doHardLogoff();
            Ibulletin_Stats::getInstance()->setAttrib('action', 'logged off same computer');
            Ibulletin_Stats::getInstance()->__destruct();
            // Vytvorime novy objekt Stats pomoci get instance a nastavime
            // timestamp pro sessions i page_views
            Ibulletin_Stats::getInstance()->setAttrib('sessions.timestamp', $pvTimestamp);
            Ibulletin_Stats::getInstance()->setAttrib('timestamp', $pvTimestamp);
        }

        // Pokud se zmenil uzivatel, uvedeme do statistik akci "logged in"
        if($inst->actual_user_id != $id){
            Ibulletin_Stats::getInstance()->setAttrib('action', 'logged in');
        }

        // Pokusime se najit a znovu pouzit starou cookie tohoto uzivatele
        Ibulletin_Stats::getInstance()->restoreCookie($id);

        // Prihlasime uzivatele zadanim jeho id do self::$actual_user_id
        $inst->actual_user_id = $id;
        // Zapiseme do session
        $session = Zend_Registry::get('session');
        $session->actual_user_id = $id;

        // Nastavime do statistik uzivatele pro tuto session
        Ibulletin_Stats::getInstance()->setAttrib('user_id', $id);

        return true;
    }

    /**
     * Pokusi se autentizovat uzivatele pomoci authcode, v pripade uspechu je
     * uzivatel presmerovan na aktualni bulletin, jinak metoda vraci false.
     *
     * @param string    Authcode ziskany od uzivatele
     * @param bool      Neprihlasovat uzivatele po overeni?
     * @param bool      Neprovadet presmerovani po uspesnem overeni uzivatele?
     * @return mixed    Byl uzivatel autentizovan? - vraci (string)'not_exists' pokud uzivatel neexistuje
     *                  a (string)'deleted' pokud byl uzivatel smazan, v pripade uspechu prihlaseni
     *                  je uzivatel ihned presmerovan na aktualni bulletin, nebo vraceno ID
     *                  overeneho uzivatele podle nastaveni noAuth a noRedirect.
     */
    public static function authByAuthcode($authcode, $noAuth = false, $noRedirect = false)
    {
        $inst = self::getInstance();
        $db = Zend_Registry::get('db');

        // Odstranime prazdne znaky na konci a zacatku retezce, prevedeme na mala pismena
        $authcode = trim(strtolower($authcode));
        $authcode_quot = $db->quote($authcode);

        $q = sprintf('SELECT id, deleted IS NOT NULL AS deleted_bool, unsubscribed IS NOT NULL AS unsubscribed_bool
            FROM users WHERE lower(authcode)=%s', $authcode_quot);

        $rows = $db->fetchAssoc($q);
        reset($rows);
        $row = current($rows);

        if(!is_numeric($row['id'])){
            // uzivatel se zadanym authcode nebyl nalezen
            return 'not_exists';
        }
        elseif($row['deleted_bool']){
            // uzivatel byl smazan, nastavime si do session info o totoznosti pro vyuziti pri pripadne registraci
            $session = Zend_Registry::get('session');
            $session->actual_user_id_deleted = $row['id'];
            return 'deleted';
        }
        elseif($row['unsubscribed_bool']){
            // Uzivatel se sam odhlasil z mailingu, zase jej prihlasime
            //TODO: Nejspise neodpovida politice, ze uzivatele neobnovujeme, pokud se odhlasil...
            //Users::setUnsubscribed($row['id'], false);
        }

        if(!$noAuth){
            // prihlasime uzivatele
            $inst->setUser($row['id']);
        }

        // Nastavime priznak users send_emails na TRUE
        Users::setSendEmails($row['id']);


        if(!$noRedirect){
            // presmerujeme na aktualni bulletin
            //$bulletins = new Bulletins();
            //$bulletins->redirectToActualBulletin(true);
            $inst->redirectToAuth();
        }

        return $row['id'];
    }

    /**
     * Pokusi se autentizovat uzivatele pomoci jmena a hesla, v pripade uspechu je
     * uzivatel presmerovan na aktualni bulletin, jinak metoda vraci false.
     *
     * // POZOR!! NENI RESENO, CO SE BUDE DIT SE STEJNYM LOGINEM A HESLEM PRI OPETOVNE REGISTRACI,
     * // UZIVATEL SI BUDE MUSET ZVOLIT JINY LOGIN, POKUD BYL SMAZAN
     *
     * @param string Login uzivatele
     * @param string Heslo uzivatele
     * @return int Byl uzivatel autentizovan? - vraci 0 pokud login nebo heslo neodpovidaji
     */
    public static function authByLoginAndPassword($login, $password)
    {
        $inst = self::getInstance();
        $db = Zend_Registry::get('db');

        // Odstranime prazdne znaky na konci a zacatku retezce, prevedeme na mala pismena
        $login = strtolower($login);
        $password_md5 = md5($password);

        $sel = $db->select()
            ->from('users', array('*'))
            ->where("pass = :pass AND login = :login");

        $rows = $db->fetchAll($sel, array('pass' => $password_md5, 'login' => $login));

        if(isset($rows[0]) && isset($rows[0]['id'])){
            $row = $rows[0];
        }
        else{
            return 0;
        }

        if(!empty($row['deleted'])){
            // POZOR!! NENI RESENO, CO SE BUDE DIT SE STEJNYM LOGINEM A HESLEM PRI OPETOVNE REGISTRACI,
            // UZIVATEL SI BUDE MUSET ZVOLIT JINY LOGIN, POKUD BYL SMAZAN
            // uzivatel byl smazan, nastavime si do session info o totoznosti pro vyuziti pri pripadne registraci
            $session = Zend_Registry::get('session');
            $session->actual_user_id_deleted = $row['id'];
            return 0;
        }
        elseif(!empty($row['unsubscribed'])){
            // Uzivatel se sam odhlasil z mailingu, zase jej prihlasime
            Users::setUnsubscribed($row['id'], false);
        }

        // prihlasime uzivatele
        $inst->setUser($row['id']);

        // Nastavime priznak users send_emails na TRUE
        Users::setSendEmails($row['id']);

        // presmerujeme na aktualni bulletin
        //$bulletins = new Bulletins();
        //$bulletins->redirectToActualBulletin(true);
        $inst->redirectToAuth();

        return false;
    }

    /**
     * Pokusi se podle cookie, ktera je spojena s aktualni session,
     * najit uzivatele, ktery ji pouzil naposledy a porihlasit jej.
     *
     * Po prihlaseni je uzivatel presmerovan na aktualni bulletin v pripade,
     * ze adresa, kterou pozaduje je forbidden_access_redir z configu a nejedna se o indexController,
     * ktery si redirectuje podle potreby sam. Toto presmerovani je nutne aby se zamezilo zacykleni redirectu.
     *
     * Prihlaseni neprobehne v pripade, ze je uzivatel smazany, v takovem pripade je pouze
     * poznamenane rozeznane user_id pro registracni akci, ktera spravne propoji novou registraci
     * s existujicim uctem.
     *
     * @return bool Byl uzivatel autentizovan?
     */
    public function authByCookie()
    {
        $db = Zend_Registry::get('db');
        $session_id = Ibulletin_Stats::getInstance()->session_id;

        // Nalezneme podle session ID cookie_id aktualni session a podle te
        // najdeme posledniho uzivatele, ktery se s touto cookie prihlasil.
        $q = "SELECT user_id, deleted IS NOT NULL AS deleted_bool, unsubscribed IS NOT NULL AS unsubscribed_bool
                FROM sessions s
                JOIN users u on u.id = s.user_id
                WHERE user_id IS NOT NULL
                      AND cookie_id = (SELECT cookie_id FROM sessions WHERE id = $session_id)
                ORDER BY u.id DESC LIMIT 1";
        $user = $db->fetchRow($q);
        $user_id = $user['user_id'];

        //Phc_ErrorLog::debug('auth', var_export($user, true));

        //pokud se ID naslo a uzivatel neni smazany, prihlasime jej, nebo presmerujeme
        if(is_numeric($user_id) && !$user['deleted_bool'] && !$user['unsubscribed_bool']){
            //prihlasime
            $authentized = $this->setUser($user_id);

            // Pokud uzivatel nema nastaveno send_emails, nastavime
            if(!$this->actualUserData['send_emails']){
                // Nastavime priznak users send_emails na TRUE
                // Nenastavujeme, protoze uzivatel musi s necim souhlasit, abychom mu mohli posilat
                // TODO Overit, jak ma fungovat authByCookie v pripade, ze uzivatel nema nastaveno send_emails - auth by cookie by nemelo fungovat
                //Users::setSendEmails($user_id);
            }

            // Kontorola, jestli se ma provest redirect
            $config = Zend_Registry::get('config');
            $frontController = Zend_Controller_Front::getInstance();
            $request = $frontController->getRequest();
            $controller = $request->getControllerName();
            $match = $config->general->forbidden_access_redir->controller;
            // Pokud je controller v configu prazdny retezec, najdeme default controller
            if(empty($match) && !is_numeric($match)){
                $routes = Zend_Controller_Front::getInstance()->getRouter()->getRoutes();
                $match = $routes['default']->getDefault('controller');
            }
            // Presmerujeme kvuli forbidden_access_redir jen v pripade, ze controller neni index - ten si redirectuje sam kdyz potrebuje
            if($controller == $match && $match != 'index'){
                // Presmerujeme na aktualni bulletin
                //$bulletins = new Bulletins();
                //$bulletins->redirectToActualBulletin(true);
                $this->redirectToAuth();
            }

            return $authentized;
        }
        // Pokud je uzivatel smazany, nebo unsubscribed, ulozime si jeho ID do session pro priapdnou registraci
        elseif($user['deleted_bool'] || $user['unsubscribed_bool']){
            $session = Zend_Registry::get('session');
            $session->actual_user_id_deleted = $user_id;
        }

        return false;
    }

    /**
     * Pokusi se autentizovat uzivatele pomoci tokenu z tabulky
     * users_links_tokens a v pripade uspechu po autentizaci presmeruje
     * na pozadovanou stranku, v pripade neuspechu vrati na
     * forbidden_access_redir.controller s akci forbidden_access_redir.action
     * z configu.
     *
     * Dale se postara o spravne nastaveni zvaci vlny podle e-mailu,
     * z ktereho uzivatel prisel.
     *
     * @param string Token uzivatele
     * @param bool Redirectujeme? default TRUE
     * @return bool zdarila se autentizace?
     */
    public function authByToken($token, $redirect = TRUE)
    {
        $db = Zend_Registry::get('db');

        $token_quot = $db->quote($token);
        $q = "SELECT ult.*, u.deleted, u.unsubscribed
              FROM users_links_tokens ult
              JOIN users u ON u.id = ult.user_id
              WHERE token=$token_quot";
        $data = $db->fetchRow($q);


        // Pokud se nic nenatahlo, autentizace se nezdarila, presmerujeme
        if(empty($data) || !empty($data['deleted']) || !empty($data['unsubscribed'])){
            // Zapiseme id uzivatele, ktery je smazany nebo odhlaseny pro pripad, ze by se znovu registroval
            if(!empty($data)){
                $session = Zend_Registry::get('session');
                $session->actual_user_id_deleted = $data['id'];
            }

            if ($redirect) {
                self::getInstance()->redirectToUnauth();
            } else {
                return FALSE;
            }
        }

        // Nastavime uzivatele pro tuto session
        $this->setUser($data['user_id']);

        // zrusime priznak bad_address pro nalezeneho uzivatele
        Users::updateUser($data['user_id'], array('bad_addr' => false));

        // Nastavime akci na link, pokud se nezmeni uzivatel a nebyla jiz
        // v setUser() akce nastavena na 'logged in'
        if(Ibulletin_Stats::getInstance()->getAttrib('action') == ''){
            Ibulletin_Stats::getInstance()->setAttrib('action', 'link');
        }

        // Nastavime do page_views propojeni do users_links_tokens
        Ibulletin_Stats::getInstance()->setAttrib('users_links_tokens_id', $data['id']);


        // Nastavime priznak users send_emails na TRUE
        // NENASTAVUJEME - pravdepodobne slouzi predevsim pro forward kolegovi, kde to ted nechceme
        // dale se pouziva pro znovu prihlaseni uzivatele, ktery se snmazal/odhlasil
        // TODO vyresit, jak se mame chovat k samoodhlasenym uzivatelum, kteri prokliknou nebo prijdou jinak
        //Users::setSendEmails($data['user_id']);

        // Pokusime se dohledat zvaci vlnu a bulletin podle e-mailu.
        $email_id = $data['email_id'];
        $bulletin_id = null;
        if(is_numeric($email_id)){
            $q = sprintf("SELECT e.invitation_id, iw.bulletin_id FROM emails e JOIN invitation_waves iw ON e.invitation_id = iw.id WHERE e.id = %d", $email_id);
            //Phc_ErrorLog::info('token access', "select = '$q'");
             $row = $db->fetchAll($q);
             if(!empty($row)){
                 $invitation_id = $row[0]['invitation_id'];
                 $bulletin_id = !empty($row[0]['bulletin_id']) ? $row[0]['bulletin_id'] : null;
             }
        }

        // Pokud neni zadane id bulletinu, pouzijeme aktualni bulletin
        if($bulletin_id === null){
            $bulletins = new Bulletins();
            $bulletin_row = $bulletins->getActualBulletinRow(true);
            $bulletin_id = $bulletin_row['id'];
        }

        // Nastavime akci a pouziti linku s id
        Ibulletin_Stats::getInstance()->setAttrib('link_id', $data['link_id']);

        // Pokud se nalezla zvaci vlna, doplnime ji do statistiky pro tuto session
        if(!empty($invitation_id)){
            Ibulletin_Stats::getInstance()->setAttrib('invitation_id', (int)$invitation_id);
        }

        // Najdeme podle linku, kam ma byt presmerovano
        Zend_Loader::loadClass('Ibulletin_Menu');
        $menu = new Ibulletin_Menu();

        try{
            $url = $menu->getLinkUrl($data['link_id'],true, true, null, $bulletin_id);
        }
        catch(Ibulletin_Menu_Link_Not_Found_Exception $e){
            // Problem muze byt v omezeni na bulletin, zkusime zadat bez bulletinu
            $url = $menu->getLinkUrl($data['link_id'],true, true, null);
        }

        // Ziskame vsechny parametry predane v url s tokenem a pridame je k parametrum URL
        $request = Zend_Controller_Front::getInstance()->getRequest();
        $params = $request->getParams();

        unset($params['token']);
        unset($params['action']);
        unset($params['controller']);

        // Nastavime do page_views link_xpath podle parametru posid
        if (isset($params['posid'])) {
        	Ibulletin_Stats::getInstance()->setAttrib('link_xpath', 'mail_link:'.$params['posid']);
            unset($params['posid']);
        }

        if(is_array($url['params']) && is_array($params)){
            $params = $url['params'] + $params;
        }

        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        if ($redirect) {
            //neni-li parametr vyhodíme vyjímku a 404
            if (empty($params)) {
                throw new Zend_Controller_Action_Exception('Do not found issue for this page', 404);
            }
            $redirector->gotoRouteAndExit($params, $url['route']);
        } else {
            return TRUE;
        }
    }

    /**
     * Autentizuje uzivatele podle jakehokoli atributu z users nebo users_attribs.
     * Atribut musi byt unikatni napric uzivateli, v pripade vice uzivatelu s danym atributem autentizuje
     * prvni ID ktere odpovida a zaznamenava do logu chybu.
     *
     * @param string    Nazev atributu, podle ktereho chceme overovat.
     * @param mixed     Hodnota atributu.
     * @param bool      Redirectujeme? default TRUE
     * @return bool     False pri neuspesne autentizaci a array obsahujici zaznam uzivatele pri uspechu
     */
    public function authByAttrib($key, $val, $redirect = true)
    {
        $users = Users::getUsersByAttrib($key, $val);

        // Uzivatel nenalezen
        if(empty($users)){
            return false;
        }

        // Nalezeno vice uzivatelu - musime zalogovat chybu, prihlasime prvniho
        if(count($users) > 1){
            Phc_ErrorLog::warning('Ibulletin_Auth::authByAttrib()', 'Bylo nalezeno vice uzivatelu odpovidajicich '.
                'zadanym atributum. key: "'.$key.'", val: "'.$val.'". Byl prihlasen prvni uzivatel podle id.');
        }

        $user = array_shift($users);

        $this->setUser($user['id']);

        if($redirect) {
            $this->redirectToAuth();
        }
        else{
            return $user;
        }
    }


    /**
     * Zmeni data uzivatele ulozena v tabulce users podle dat zadanych v poli attribs.
     * Pokud je nastaven parametr mergeUsers, je v pripade existence uzivatele se shodnym
     * e-mailem provedeno spojeni editovaneho uzivatele s jiz existujicim uzivatelem tak, ze
     * existujici uzivatel je ponechan a editovany uzivatel je odstranen, editace se pak provede
     * na existujicim uzivateli.
     *
     * V pripade, ze je nastaveno config->general->register_action->
     * send_email_to_users_filled_email_later posle registracni email uzivateli, ktery mel pred
     * zmenou udaju nevyplneny email a ted nove email vyplnil.
     *
     * V pripade, ze je uzivatel oznacen za smazaneho, zmenime oznaceni na nesmazaneho podle
     * parametru.
     *
     * @param array Pole atributu ke zmene - klic pole je nazev atributu a hodnota hodnotou...
     * @param int   ID uzivatele, pokud neni zadano, pouzije se aktualni uzivatel.
     * @param bool  Maji se uzivatele spojit, pokud email v attribs odpovida jiz existujicimu uctu?
     *              Lze pouzit jen pro aktualniho uzivatele...
     * @param bool  Obnovit smazane uzivatele?
     * @param bool  Vynutit odeslani registracniho mailu, pokud je povoleno v configu?
     *              Default: null - odesila reg. mail pouze pokud byl nove vlozen email do dat uzivatele
     * @param bool  Pokud uzivatel nemel email a timto bude doplnen, nenastavovat uzivateli registered
     *              aby nebyl obtezovan, dokud sam nepozada o registraci - nezaregistruje se
     * @throws Ibulletin_Auth_No_User_Specified_Exception   V pripade nezadani ID uzvatele,
     *         nebo nenalezeni uzivatele.
     * @throws Ibulletin_Auth_Invalid_Email_Exception   V pripade zadani spatne formatovaneho
     *         emailu...
     * @throws Ibulletin_Auth_User_Unique_Violation_Exception   Pokud je pri uprave zaznamu v
     *         tabulce users porusena unikatnost nektereho hlidaneho atributu.
     */
    public static function changeUsersData($attribs, $id = null, $mergeUsers = false, $undeleteUsers = true,
        $forceSendEmail = null, $dontActivate = false)
    {
        $inst = self::getInstance();
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        if($id === null){
            $id = $inst->actual_user_id;
            $is_actual_user = true;
        }
        else{
            $is_actual_user = false;
        }
        if($id === null){
            throw new Ibulletin_Auth_No_User_Specified_Exception(
                'Nebylo zadano id uzvatele k editaci a zadny uzivatel neni prihlasen.');
        }

        // Validace emailu
        if(isset($attribs['email'])){
            $attribs['email'] = strtolower($attribs['email']);
            $emailValidator = new Zend_Validate_EmailAddress();
            if(!$emailValidator->isValid($attribs['email']) || empty($attribs['email'])){
                throw new Ibulletin_Auth_Invalid_Email_Exception('Byl zadan nevalidni email.');
            }
        }

        // Ziskame aktualni radek uzivatele z DB
        $sel = $db->select()
            ->from('users')
            ->where('id = :id');
        $user_row = $db->fetchRow($sel, array('id' => $id));

        // Pokud menime mail nebo je pozadovan undelete uzivatele, odstranime priznak smazani uzivatele
        //if((!empty($attribs['email']) && !empty($user_row['deleted'])) || $undeleteUsers){
        if((!empty($attribs['email']) && $attribs['email'] != $user_row['email']) || $undeleteUsers){
            $attribs['deleted'] = null;
            //$upddata = array('deleted' => null);
            //$db->update('users', $upddata, sprintf('id = %d', $id));
        }

        // Uzivateli byl pridan mail ale uzivatel se neregistroval, proto mu nastavime registered null
            if($dontActivate && (/*$merged || */(!empty($attribs['email']) && empty($user_row['email'])))){
                $attribs['registered'] = null;
                //$db->update('users', $upddata, sprintf('id = %d', $id));
            }
            elseif(!$user_row['registered'] && !empty($attribs['email'])){
                $attribs['registered'] = new Zend_Db_Expr('current_timestamp');
            }


        //
        // SAVE
        //
        try{
            $savedId = Users::updateUser($id, $attribs, $mergeUsers);
        }
        catch(Users_Exception $e){
            $code = $e->getCode();
            if($code == 16){
                throw new Ibulletin_Auth_Invalid_Email_Exception('Byl zadan nevalidni email.');
        }
            elseif($code == 32){
                throw new Ibulletin_Auth_User_Unique_Violation_Exception($e);
            }
        }

        // Pokud bylo provedeno spojeni uzivatelu, musime zmenit uzivatele
        $merged = false;
        if($mergeUsers && $id != $savedId){
            $id = $savedId;
            $merged = true;

            // Zmenime aktualniho uzivatele, pokud je treba
            if($is_actual_user && $savedId != $user_row['id']){
                self::setUser($savedId);
                $user_row = self::getActualUserData(true);
            }
        }

        // Obnovime nacachovana data v objektu v self::actualUserData
        $user_data = self::getActualUserData(true);
        // Pokud je uzivatel aktualnim uzivatelem a nema nastaveno send_emails, nastavime
        if($user_data['id'] == $id && !$user_data['send_emails']){
            // TODO Overit, co se presne ma delat se send_emails, tohle vypada nevhodne
            Users::setSendEmails($user_data['id']);
        }

        // Pokud uzivatel mel pred zmenou dat prazdny email a nyni je nejaky zadan,
        // odesleme podle nastaveni v configu registracni email.
        if($config->general->register_action->send_email_to_users_filled_email_later
            && ((!empty($attribs['email']) && empty($user_row['email']) && $forceSendEmail !== false)
            || $forceSendEmail === true))
        {
            try{
                Ibulletin_Auth::send_special_email($id, 'registration');
            }
            catch(Ibulletin_Auth_Send_Special_Email_Exception $e){
                // Nepodarilo se odeslat email, proste jen zalogujeme
                Phc_ErrorLog::warning('Ibulletin_Auth::changeUsersData()', $e);
            }
        }
    }

    /**
     * Odhlasi aktualniho uzivatele i se smazanim jeho cookie v prohlizeci
     * podle toho, jestli je povolen v configu hard_logoff.
     * Pokud v session neni nastaven uzivatel, neprovadime nic, protoze zadny uzivatel neni prihlasen.
     */
    public static function logoffUser($redirect = true)
    {
        $inst = Ibulletin_Auth::getInstance();

        // Pokud neni nikdo prihlasen, koncime - neni potreba odhlasovat
        if(empty($inst->actual_user_id)){
            return;
        }

        $config = Zend_Registry::get('config');

        if($config->general->hard_logoff){
            $inst->doHardLogoff(!$redirect);
        }
        if($redirect){
			#Presmerujeme na forbidden_access_redir.action (predpokladame tam login page)
			//ziskame redirector
			$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
			//presmerujeme
			$redirector->gotoAndExit(
							$config->general->forbidden_access_redir->action,
							$config->general->forbidden_access_redir->controller,
							'',
							array()
							);
		}
    }

    /**
     * Provede tvrde odhlaseni uzivatele - odstrani vsechny jeho informace ze session
     * a odstrani mu cookie.
     *
     * Pouziva se pokud je povolen $config->general->hard_logoff v logoff, nebo v
     * pripade, ze se zmenil uzivatel v dane session.
     *
     * @param bool  Start new session after logoff?
     */
    public static function doHardLogoff($startNewSession = false)
    {
        $inst = Ibulletin_Auth::getInstance();

        // Odhlasime uzivatele smazanim jeho id ze self::$actual_user_id
        $inst->actual_user_id = null;
        $inst->actualUserData = null;

        // Zapiseme do session
        $session = Zend_Registry::get('session');
        unset($session->actual_user_id);

        // Uvedeme do statistik akci "logged off"
        Ibulletin_Stats::getInstance()->setAttrib('action', 'logged off');
        Ibulletin_Stats::getInstance()->saveToPageViews();

        // Odebrat cookie
        Ibulletin_Stats::getInstance()->removeUserCookie();

        // Zrusit session
        Ibulletin_Stats::getInstance()->dropSession();

        if($startNewSession){
            Ibulletin_Stats::getInstance()->newSession();
        }
    }

    /**
     * Zaregistruje zadanymi udaji noveho uzivatele do tabulky users a
     * provede akce podle konfigurace (general.register_action).
     * Napriklad odesle e-mail novemu uzivateli (odesila email oznaceny jako registracni,
     * nebo posledni vydani bulletinu), nebo presmeruje do webu,
     * pripadne kombinace.
     *
     * Nove registrovany uzivatel ma v DB users nastaveno selfregistered,
     * na rozdil od uzivatele, ktery v DB jiz existoval. Timestamp created
     * neni pri opetovne registraci stejneho uzivatele menena.
     *
     * Pokud je zadana uzivatelova e-mailova adresa, je zkontrolovano, jestli
     * neni z domeny, kde chceme uzivatele nastavovat jako test, nebo client. Domeny jsou uvedeny v
     * config->general->domains_register_as_test a config->general->domains_register_as_client.
     *
     * @param array klicem je nazev atributu z tabulky users a hodnotou je hodnota,
     *              Pokud je zadavan rozhodujici UNIQUE atribut, musi byt zadan jako prvni v poli,
     *              Jedine v takovem pripade je metoda schopna obnovit shodneho smazaneho uzivatele
     *              pripadne identifikovat, ze se jedna o duplicitu.
     * @param bool Autentizovat uzivatele a redirectovat na aktualni bulletin?
     * @param string $send_emails Nastaveni priznaku send_emails v tabulce users,
     *               defaultne nastavujeme true
     * @param bool  Nastaveni send_emails. V pripade, ze uzivatel jiz existuje, meni se send_emails
     *              pouze pokud se ma zmenit na true.
     * @param bool  Poslat registracni email?
     * @param array Unikatni autributy, ktere pevne identifikuji uzivatele
     *              tedy takove, ktere pri jejich kolizi znamenaji, ze uzivatele jiz v DB mame, default 'email'
     * @param bool  zamezi redirectovani i pres nastaveni v konfiguraci, pouzito napr. pro deeplinky
     * @return int  Id noveho uzivatele nebo false, pokud se akce nepovedla.
     * @throws Ibulletin_Auth_Register_User_Already_Exists_Exception,
     *         Ibulletin_Auth_Register_Action_Exception
     *         Ibulletin_Auth_Invalid_Email_Exception   Pokud byl zadan nevalidni email.
     */
    public static function registerUser($attribs = array(), $redirect = false, $send_emails = true,
        $send_registration_mail = true, $usersUnqAttribs = null,$disable_redirect = false)
    {
        // -------- UNIKATNI ATRIBUTY V USERS -----------
        // Unikatni autributy, ktere pevne identifikuji uzivatele
        // tedy takove, ktere pri jejich kolizi znamenaji, ze uzivatele jiz v DB mame
        if($usersUnqAttribs === null){
            $usersUnqAttribs = array('email');
        }
        // ----------------------------------------------


        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $notUnique = false;
        $session = Zend_Registry::get('session');


        // Pokud je od autentizace zadano id smazaneho uzivatele, pridame jej na zacatek $attribs
        if(!empty($session->actual_user_id_deleted)){
            $attribs = array_merge(array('id' => $session->actual_user_id_deleted), $attribs);
        }


        // Ziskame seznam atributuu users
        $q = "select column_name from INFORMATION_SCHEMA.COLUMNS where table_name = 'users'";
        $columns = $db->fetchAll($q);
        $cols = array();
        foreach($columns as $col){
            $cols[] = $col['column_name'];
        }

        if(array_key_exists('login', $attribs) && array_key_exists('pass', $attribs)){
            $credentials = array('login' => $attribs['login'], 'pass' => $attribs['pass']);
        }
        else{
            $credentials = array();
        }


        if(isset($attribs['email'])){
            // Normalizace emailove adresy na mala pismena
            $attribs['email'] = strtolower($attribs['email']);

            // Validace emailu
            $emailValidator = new Zend_Validate_EmailAddress();
            if(!$emailValidator->isValid($attribs['email']) || empty($attribs['email'])){
                throw new Ibulletin_Auth_Invalid_Email_Exception('Byl zadan nevalidni email.');
            }

            // Zjistime, jestli je treba uzivatele nastavit jako test podle jeho emailu
            if(Users::shouldBeMarkedAsTestByEmail($attribs['email'])){
                $attribs = array_merge($attribs, array('test' => new Zend_Db_Expr('true')));
                //$should_be_test = new Zend_Db_Expr('true');
            }
            else{
                $attribs = array_merge($attribs, array('test' => new Zend_Db_Expr('false')));
                //$should_be_test = new Zend_Db_Expr('false');
            }

            // Zjistime, jestli je treba uzivatele nastavit jako client podle jeho emailu
            if(Users::shouldBeMarkedAsClientByEmail($attribs['email'])){
                $attribs = array_merge($attribs, array('client' => new Zend_Db_Expr('true')));
                //$should_be_client = new Zend_Db_Expr('true');
            }
            else{
                $attribs = array_merge($attribs, array('client' => new Zend_Db_Expr('false')));
                //$should_be_client = new Zend_Db_Expr('false');
            }
        }

        // Zkusime najit uzivatele kolidujici s timto

        // Pokusime se vlozit udaje noveho uzivatele
        try
        {
            // Pokud se toto vlozeni povede, znamena to, ze uzivatel v db neexistoval a
            // proto je spravne oznacen jako selfregistered
            $user_id = Users::updateUser(null, array_merge($attribs, array('selfregistered' => true,
                'send_emails' => $send_emails)), false);

        }
        catch (Users_Exception $e)
        {
            // Jedna se o problem unikatnosti klice
            if($e->getCode() == 32){
                $notUnique = true;

                    // Je nam jedno, ze uzivatel jiz existuje, najdeme jej a posleme mu znovu e-mail
                    reset($attribs);
                    $q = $db->select()
                            ->from(array('u' => 'users'), array('*'));
                // Hledame podle vsech unikatnich atributuu identifikujicich uzivatele,
                // abychom mohli resit vsechny kolize
                    foreach($usersUnqAttribs as $attrKey => $attr){
                        if(isset($attribs[$attr])){
                            $q->orwhere($attr." = ".$db->quote($attribs[$attr]));
                        }
                        else{
                            // Odstranime neexistujici, abychom vedeli, ktery kolidujici zaznam mame
                            // pouzit pro prave registrovaneho uzivatele
                            unset($usersUnqAttribs[$attrKey]);
                        }
                    }
                    $collidingUsers = $db->fetchAll($q);

                    // Jako uzivatele, ktery bude timto uzivatelem pouzijeme toho s kolizi
                    // o nejvetsi dulezitosti
                    reset($usersUnqAttribs);
                    $choosenAttr = current($usersUnqAttribs);
                    foreach($collidingUsers as $collUser){
                        if($collUser[$choosenAttr] == $attribs[$choosenAttr]){
                            $user_id = $collUser['id'];
                        }
                        else{
                            // Pokud existuje i jiny uzivatel, ktery koliduje, musime vyhodit vyjimku
                            // TOHLE by se v podstate nemelo stat, protoze nejspise nikdy nebudeme mit jiny identifikujici atribut, nez e-mail
                            throw new Ibulletin_Auth_User_Unique_Violation_Exception('Uzivatel koliduje na identifikujicich atributech s vice uzivateli.'.
                                "Prvni kolidujici uzivatel id (nemusi byt jeste identifikovan):$user_id\n".
                                "Dalsi kolidujici uzivatel:\n".print_r($collUser,true).
                                "Vsichni kolidujici uzivatele:\n".print_r($collidingUsers,true));
                        }
                    }

                    // $send_emails = ($send_emails) ? 'true' : 'false';

                    // Nastavime atribut deleted na null a pripadne zmenime test
                    $toUpdate = array('deleted' => new Zend_Db_Expr('NULL'),
                                      'unsubscribed' => new Zend_Db_Expr('NULL'),
                                       //'test' => $should_be_test,
                                       //'client' => $should_be_client,
                                       );

                    // send_emails menime v tomto pripade pouze na true, na false pouze v pripade ze je to povoleno v config
                    if ($config->general->register_action->set_send_emails == 2) {
                        $toUpdate['send_emails'] = $send_emails;
                    } else if($send_emails){
                        $toUpdate['send_emails'] = $send_emails;
                    }

                    /* ZVAZIT POUZIVANI - NAVRZENO V INCASTU PRED MERGE S INDETAILEM
                    // Vyresetujeme bad_addr a zrusime bad_addr, pokud byl zmenen email
                    $actualUserData = self::getActualUserData();
                    if(isset($attribs['email']) && $actualUserData['email'] != $attribs['email']){
                        $toUpdate['bad_addr'] = false;
                        $toUpdate['reset_bad_addr'] = new Zend_Db_Expr('current_timestamp');
                }
                    */

                //$db->update('users', array_merge($attribs, $toUpdate), "id=$user_id");
                $user_id = Users::updateUser($user_id, array_merge($attribs, $toUpdate), false);

                //nastavime redirect na true pro registrovane uzivatele pokud je tak nastaveno v configu
                if (!$disable_redirect) {
                    if (!empty($config->general->register_action->redirect_registered)) {
                        $redirect = true;
                    }
                }
            }
            else{
                // Jedna se o jiny problem
                // TODO zalogovat problem
                //throw new Zend_Db_Statement_Exception($e);

                Phc_ErrorLog::error('Ibulletin_Auth::registerUser()', $e);

                return false;
            }
        }

        // Zaznamename do page_views akci registrovani noveho uzivatele
        Ibulletin_Stats::getInstance()->setAttrib('action', 'registrated');


        # Provedeme akce podle nastaveni v configu general.register_action
        if(!empty($config->general->register_action->send_email) &&
            (!empty($config->general->register_action->send_email_to_alredy_registred) || !$notUnique)
            && ($notUnique || isset($attribs['email']))
            && $send_registration_mail
            )
        {
            // Rozhodneme jaky mail se ma poslat - zalezi na tom, jestli bylo registrovano bez
            // loginu a hesla nebo s
            if(!empty($credentials['login']) && array_key_exists('pass', $credentials)){
                Ibulletin_Auth::send_special_email($user_id, 'registrationlogin', $redirect, $credentials);
            }
            else{
                Ibulletin_Auth::send_special_email($user_id, 'registration', $redirect);
            }
        }

        if($redirect){
            $inst = Ibulletin_Auth::getInstance();

            // Autentizujeme uzivatele - nastavime pro tuto session jeho ID
            $inst->setUser($user_id);

            // Provedeme redirect na aktualni bulletin
            //$bulletins = new Bulletins();
            //$bulletins->redirectToActualBulletin(true);
           $inst->redirectToAuth();
        }

        return $user_id;
    }


    /**
     * Deregistruje aktualniho uzivatele.
     *
     * @return bool false pokud uzivatel nebyl odhlášen (mela by byt ohlasena chyba uzivateli),
     *                    nebo pokud zadny uzivatel neni prihlasen
     *              true pokud byl uzivatel odhlasen
     */
    public static function deregisterActualUser()
    {
        $inst = Ibulletin_Auth::getInstance();
        $id = $inst->actual_user_id;

        if($id == null){
            // Zadny uzivatel neni prihlasen
            return false;
        }

        // Deregistrujeme uzivatele podle id
        return $inst->deregisterUserById($id);
    }


    /**
     * Deregistruje uzivatele pomoci tokenu z nejakeho jeho emailu.
     *
     * @param string Token z tabulky users_emails
     * @return bool false pokud uzivatel nebyl nalezen nebo odhlášen
     *              true pokud byl uzivatel odhlasen
     */
    public static function deregisterUser($token)
    {
        $id = self::getUserIdFromEmailToken($token);

        if($id == null){
            // Uzivatele se nepodarilo najit podle tokenu
            return false;
        }

        // Prihlasime uzivatele
        self::setUser($id);

        // Deregistrujeme uzivatele podle id
        return self::deregisterUserById($id);
    }

    /**
     * Odnastavi priznak send_nps u uzivatele podle id z tabulky users.
     *
     * @param string Token z tabulky users_emails
     * @return bool false pokud uzivatel nebyl nalezen nebo odhlášen
     *              true pokud byl uzivatel odhlasen
     */
    public static function deregisternpsUser($token)
    {
        $id = self::getUserIdFromEmailToken($token);

        if($id == null){
            // Uzivatele se nepodarilo najit podle tokenu
            return false;
        }

        // Prihlasime uzivatele
        self::setUser($id);

        // Deregistrujeme uzivatele podle id
        return self::deregisternpsUserById($id);
    }

    /**
     * Deregistruje uzivatele podle id z tabulky users.
     *
     * @param int Id uzivatele z tabulky users
     * @return bool false pokud uzivatel nebyl nalezen nebo odhlášen
     *              true pokud byl uzivatel odhlasen
     */
    public static function deregisterUserById($id)
    {
        # Deregistrujeme

        $db = Zend_Registry::get('db');

        $data = array('unsubscribed' => new Zend_Db_Expr('current_timestamp'),
                      'deleted' => new Zend_Db_Expr('current_timestamp'));
        /*
        //$affected = $db->update('users', $data, sprintf('id = %d', $id));

        if($affected < 1){
            // nepodarilo se provest odhlaseni z mailingu
            return false;
        }
        */
        try{
            Users::updateUser($id, $data, false);
        }
        catch(Exception $e){
            // Chybu zalogujeme
            Phc_ErrorLog::warning('Ibulletin_Auth::deregisterUserById()',
                "Uzivatele se nepodarilo odhlasit, puvodni vyjimka:\n$e");

            return false;
        }

        # Uzivatel je odhlasen z mailingu
        // Zapiseme do page_views akci deregistrovani uzivatele
        Ibulletin_Stats::getInstance()->setAttrib('action', 'deregistered');
        Ibulletin_Stats::getInstance()->saveToPageViews();

        # Odesleme deregistracni email pokud je nejaky vybran
        try{
            Ibulletin_Auth::send_special_email($id, 'deregistration');
        }
        catch(Ibulletin_Auth_Send_Special_Email_Exception $e){
            // Pokud je problem v tom, ze neexistuje deregistracni email (code 5),
            // nedelame nic, jinak zalogujeme vyjimku.
            if($e->getCode() != 5){
                Phc_ErrorLog::warning('Ibulletin_Auth::deregisterUserById()', $e);
            }
        }

        // Prihlasime uzivatele
        self::setUser($id);

        return true;
    }

    /**
     * Odnastavi priznak send_nps u uzivatele podle id z tabulky users.
     *
     * @param int Id uzivatele z tabulky users
     * @return bool false pokud uzivatel nebyl nalezen nebo odhlášen
     *              true pokud byl uzivatel odhlasen
     */
    public static function deregisternpsUserById($id) {

        $data = array('send_nps' => false);

        try{
            Users::updateUser($id, $data, false);
        }
        catch(Exception $e){
            // Chybu zalogujeme
            Phc_ErrorLog::warning('Ibulletin_Auth::deregisternpsUserById()',
                "Uzivatele se nepodarilo odhlasit z NPS emailu, puvodni vyjimka:\n$e");

            return false;
        }

        // Prihlasime uzivatele
        self::setUser($id);

        return true;

    }
    /**
     * Znovu aktivuje uzivatele pomoci tokenu z nejakeho jeho emailu.
     *
     * @param string Token z tabulky users_emails
     * @return bool false pokud uzivatel nebyl nalezen nebo obnoven
     *              true pokud byl uzivatel obnoven
     */
    public static function renewUser($token)
    {
        $db = Zend_Registry::get('db');
        //$inst = Ibulletin_Auth::getInstance();

        $id = self::getUserIdFromEmailToken($token);

        if($id == null){
            // Uzivatele se nepodarilo najit podle tokenu
            return false;
        }

        $data = array('deleted' => new Zend_Db_Expr('null'));

        /*
        $affected = $db->update('users', $data, sprintf('id = %d', $id));

        if($affected < 1){
            // nepodarilo se provest obnoveni uzivatele v mailingu
            return false;
        }
        */
        try{
            Users::updateUser($id, $data, false);
        }
        catch(Exception $e){
            // Chybu zalogujeme
            Phc_ErrorLog::warning('Ibulletin_Auth::deregisterUserById()',
                "Uzivatele se nepodarilo obnovit, puvodni vyjimka:\n$e");

            return false;
        }

        # Uzivatel je obnoven v mailingu
        // Zapiseme do page_views akci obnoveni uzivatele
        Ibulletin_Stats::getInstance()->setAttrib('action', 'reregistred');
        Ibulletin_Stats::getInstance()->saveToPageViews();


        return true;
    }

    /**
     * Vrati id aktualniho uzivatele, pokud neni uzivatel prihlasen vraci null.
     *
     * @return int  ID aktualniho uzivatele
     */
    public static function getActualUserId(){
        return Ibulletin_Auth::getInstance()->actual_user_id;
    }

    /**
     * Najde id uzivatele podle tokenu z emailu.
     *
     * @param string Token z tabulky users_emails
     * @return int id uzivatele, nebo null pokud uzivatel nebyl nalezen
     */
    public static function getUserIdFromEmailToken($token)
    {
        $db = Zend_Registry::get('db');
        $token_quot = $db->quote($token);

        // Pokusime se najit uzivatele podle tokenu z e-mailu
        $q = "SELECT user_id FROM users_emails WHERE token = $token_quot";
        $id = $db->fetchOne($q);
        if(!is_numeric($id)){
            // uzivatel s timto tokenem nebyl nalezen
            return null;
        }
        return (int)$id;
    }

    /**
     * Presmeruje uzivatele do neuatorizovane casti podle nastaveni v configu
     * (forbidden_access_redir.controller s akci forbidden_access_redir.action)
     */
    public function redirectToUnauth()
    {
        $config =  Zend_Registry::get('config');

        //ziskame redirector
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        //presmerujeme
        $redirector->gotoAndExit(
                        $config->general->forbidden_access_redir->action,
                        $config->general->forbidden_access_redir->controller
                        );
    }


    /**
     * Presmeruje do autentizovane casti. V pripade, ze je nastaveno $config->general->user_route
     * bere v potaz toto nastaveni pro presmerovani uzivatele.
     *
     * Prenasi do URL vnitrne i vse za Hashem, protoze v Safari se toto nepredava pri redirectu.
     * Prenos dalsich parametru v URL neni mozny, protoze vstup presmeruje na bulletin, kde route bulletin
     * neumoznuje pridani dalsich parametru na konec cesty, protoze by dochazelo ke kolizi s jinymi cestami.
     * Parametry se prenaseji jen pro specialni routovani pro uzivatele nastavene v configu $config->general->user_route.
     *
     * @param array         url parametre
     * @param array urlHash String za hashem (#) v URL (odkazuje napriklad na anchor, nebo slouzi pro akce v JS)
     *                      - musi byt predan vnitrne, protoze Safari tento string pri redirectu zahazuje
     *                      (nesmi obsahovat samotny #)
     */
    public function redirectToAuth($url_params = array(), $urlHash = '') {
        $urlHlpr = new Zend_View_Helper_Url();

        // ziskame uzivatele
        $inst = Ibulletin_Auth::getInstance();
        $id = $inst->actual_user_id;
        // pokud neni nastaven uzivatel, znamena to, ze provadime nejakou akci, kterou zde neresime (treba lightboxconfirm),
        // postoupime tedy k redirectToActualBulletin
        if(!$id){
            // presmerujeme na aktualni bulletin
            $bulletins = new Bulletins();
            $bulletins->redirectToActualBulletin(true, $url_params, $urlHash);
        }
        else{
            $user = Users::getUser($id);
        }

        $route_path = '';
        $params = $url_params;//array();

        // vytahneme s configu user_route mapu
        $config = Zend_Registry::get('config');
        $routes = !empty($config->general->user_route) ? $config->general->user_route : array();
        foreach ($routes as $route) {

             $match = explode(',',$route->match);
             $matched = TRUE;
             // cyklus jestli splnujeme podminku
             foreach ($match as $m) {
                 $arr = explode(':', $m);
                 $user_key = $arr[0];
                 $user_val = $arr[1];
                 if ($user[$user_key] != $user_val) {
                     $matched = FALSE;
                     break;
                 }
             }

            if ($matched) {
                $route_params = '';
                list($route_path, $route_params) = explode('#',$route->url);
                $url = explode(',',$route_params);
                // cyklus poskladani url
                foreach ($url as $u) {
                     $arr = explode(':', $u);
                     $url_key = $arr[0];
                     if (!isset($arr[1])) {
                         continue;
                     }
                     $url_val = $arr[1];
                     $params[$url_key] = $url_val;
                }
                break;
            }

        }

        if(empty($matched)){
            // presmerujeme na aktualni bulletin
            $bulletins = new Bulletins();
            $bulletins->redirectToActualBulletin(true, $url_params, $urlHash);
        }else{
            // Prepare URL with params and URL hash
            $url = $urlHlpr->url($params, $route_path);
            $url .= $urlHash ? ('#'.$urlHash) : '';
            //ziskame redirector
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            //presmerujeme na uzivatelskou routu
            $redirector->gotoUrlAndExit($url, array('prependBase' => false));
        }


    }
    /**
     * Presmeruje uzivatele na aktulani bulletin
     * DEPRECATED - premisteno do models/Bulletins.php
     *
     * @param bool  Nastavit ziskany bulletin jako aktulni
     * @param array Pole parametru, ktere se maji pridat do URL
     */
    public static function redirectToActualBulletin($setAsCurrent = false, $params = array())
    {
        $bulletins = new Bulletins();
        $bulletins->redirectToActualBulletin($setAsCurrent, $params);
        Phc_ErrorLog::notice('Ibulletin_Auth',
             'Byla pouzita deprecated metoda - Ibulletin_Auth::redirectToActualBulletin');
    }

    /**
     * Odesle email s priznakem send_after_registration , deregistration, nebo registrationlogin
     * nove registrovanemu uzivateli.
     *
     * @param int     ID uzivatele
     * @param string  Typ specialniho mailu - registration/deregistration/registrationlogin
     * @param bool  Bude po provedeni teto metody redirectovano jinam?
     *              (Ovlivnuje pouze vyhazovani vyjimek - pokud redirectujeme,
     *              vyjimky se jen loguji) True by se nemelo pouzivat, je tu jen kvuli kompatibilite
     * @param array Login a heslo uzivatele s klici login, pass, pouziva se pri registraci s loginem
     * @return bool Podarilo se odeslani mailu?
     * @throws Ibulletin_Auth_Send_Special_Email_Exception Pokud nastane nejaka chyba pri odesilani
     *         a je nastaveno, ze se neprovadi redirect po teto metode.
     *         Code 5: pokud nebylo odeslano v dusledku nenalezeni emailu
     */
    public static function send_special_email($user_id, $email_type, $redirect = false, $credentials = array())
    {
        // Odeslani e-mailu oznaceneho priznakem send_after_registration
        // z tabulky emails novemu uzivateli

        // Pokud neni mail_type spravny typ, zalogujeme a vyskocime.
        $allowed_types = array('registration', 'deregistration', 'registrationlogin');
        if(!in_array($email_type, $allowed_types)){
            $e = new Exception('Zadan nespravny typ specialniho mailu - email nebyl odeslan.');
            Phc_ErrorLog::warning('Ibulletin_Auth::send_special_email()', $e);
            return false;
        }


        $db = Zend_Registry::get('db');
        $config =  Zend_Registry::get('config');

        // Ziskame id mailu
        if($email_type == 'registration'){

            $bulRow = Bulletins::getActualBulletinRow();

            $q = "SELECT e.id from emails AS e
                  JOIN invitation_waves AS iw ON e.invitation_id = iw.id
                  JOIN bulletins AS b ON iw.bulletin_id = b.id
                  WHERE e.send_after_registration AND e.deleted IS NULL AND b.valid_from <= '".$bulRow['valid_from']."'
                  ORDER BY valid_from DESC";

        } else if($email_type == 'deregistration'){

            $q = "SELECT id FROM emails WHERE send_after_$email_type AND deleted IS NULL ORDER BY id DESC LIMIT 1";
        }
        else{

            $q = "SELECT id FROM emails WHERE special_function = '$email_type' AND deleted IS NULL ORDER BY id DESC LIMIT 1";
        }
        $email_id = $db->fetchOne($q);

        $message = "Email nebyl odeslan - nepodarilo se najit specialni email '$email_type' ".
            "- user_id = '$user_id'.";
        if(!is_numeric($email_id) && $email_type == 'registration'){
            //nemame-li id registracniho emailu, vezmeme posledni
            $q = "SELECT id FROM emails WHERE send_after_registration AND deleted IS NULL ORDER BY id DESC LIMIT 1";
            $email_id = $db->fetchOne($q);

            if(!is_numeric($email_id) && !$redirect){
                throw new Ibulletin_Auth_Send_Special_Email_Exception(
                    $message.' Nepodarilo se najit ani email patrici k aktualnimu bulletinu.', 5);
            }
            elseif(!is_numeric($email_id)){
                // Chybu pouze zalogujeme, uzivateli bohuzel neprijde mail...
                Phc_ErrorLog::warning('Ibulletin_Auth::send_special_email()', $message.
                    " Nepodarilo se najit ani email patrici k aktualnimu bulletinu. Mozna je spatne nastaven config.ini - register_action.send_email.");
            }
        }
        elseif(!is_numeric($email_id) && !$redirect){
            // neni co odesilat, dale se nepresmerovava, takze vyhodime vyjimku
            throw new Ibulletin_Auth_Send_Special_Email_Exception($message, 5);
        }
        elseif(!is_numeric($email_id)){
            // Chybu pouze zalogujeme, uzivateli bohuzel neprijde mail...
            Phc_ErrorLog::warning('Ibulletin_Auth::send_special_email()', $message.
                " Mozna je spatne nastaven config.ini - register_action.send_email.");
            // Uz tu nemame co delat, skoncime odesilani
            return false;
        }

        // Zaradime email do fronty
        try
        {
            Zend_Loader::loadClass('Ibulletin_Mailer');
            $mailer = new Ibulletin_Mailer($config, $db);
            // Nastaveni pokracovat v odesilani i pri chybach
            $mailer->setKeepSendingEH();
            // Posilat email i smazanym uzivatelum
            $mailer->sendDeletedToo();
            // Ignorovat nastaveni send_emails
            $mailer->setIgnoreRegisteredAndSendEmails(true);

            // Pokud posilame mail po registraci loginem a heslem musime nastavit jmeno a heslo
            if($email_type == 'registrationlogin'){
                if(isset($credentials['login'])){
                    $mailer->getMailerTags()->addTag('forgotPassLogin', $credentials['login']);
                }
                if(isset($credentials['pass'])){
                    $mailer->getMailerTags()->addTag('forgotPassPass', $credentials['pass']);
                }
            }

            // Filtr pro vybrani a pridani pouze jednoho uzivatele
            $filter = "id = $user_id";

            // Zaradime do fronty
            $usersEmailsIds = $mailer->enqueueEmail($email_id, $filter);
        }
        catch (IBulletinMailerException $e)
        {
            $message = "Email nebyl odeslan - nepodarilo se zaradit email do fronty - email_id = '$email_id', ".
                "user_id = '$user_id'. Puvodni vyjimka: $e";
            if(!$redirect){
                throw new Ibulletin_Auth_Send_Special_Email_Exception($message);
            }
            else{
                // Pouze zalogujeme
                Phc_ErrorLog::warning('Ibulletin_Auth::send_special_email()', $message);
            }
        }

        // Odeslani jednoho emailu z fronty
        try
        {
            if(!empty($usersEmailsIds)){
                $usersEmailsId = current($usersEmailsIds);
                $mailer->sendEmails($usersEmailsId);
            }
            else{
                $message = "Email nebyl odeslan - nepodarilo se zaradit email do fronty - ".
                    "nebylo predano id z tabulky users_emails - email_id = '$email_id', ".
                    "user_id = '$user_id'. Puvodni vyjimka: $e";
                if(!$redirect){
                    throw new Ibulletin_Auth_Send_Special_Email_Exception($message);
                }
                else{
                    // Pouze zalogujeme
                    Phc_ErrorLog::warning('Ibulletin_Auth::send_special_email()', $message);
                }
            }
        }
        catch (IBulletinMailerException $e){
            $message = "Email nebyl odeslan - Mailer selhal pri odesilani fronty - email_id = '$email_id', user_id = '$user_id'. Puvodni vyjimka: $e";

            if(!$redirect){
                throw new Ibulletin_Auth_Send_Special_Email_Exception($message);
            }
            else{
                // Pouze zalogujeme
                Phc_ErrorLog::warning('Ibulletin_Auth::send_special_email()', $message);
            }
        }
        catch(Exception $e){
            if(!$redirect){
                throw $e;
            }
            else{
                // Pouze zalogujeme
                Phc_ErrorLog::warning('Ibulletin_Auth::send_special_email()', "Registracni email uzivateli user_id = '$user_id' nebyl odeslan. \n".$e);
            }
        }

        // Kontrola, byl-li e-mail odeslan - predpokladame,
        // ze se jedna o nejnovejsi zaznam z users_emails s danym email_id a user_id
        //!!NEKONTROLUJE SE, NEVIM PROC KONTROLOVAT!!
        //      Kontrolu provadime pouze pokud v attribs byla zadana emailova adresa...
        // TATO KONTROLA JE UPLNE SCESTNA, NEKONTROLUJE V PODSTATE NIC
        // Petr Skoda
        //!!!!
        if(is_numeric($email_id)){
            $q = "SELECT sent FROM users_emails
                    WHERE user_id = $user_id AND email_id = $email_id AND sent IS NOT NULL
                    ORDER BY created DESC LIMIT 1";
            $sent = $db->fetchOne($q);

            if(empty($sent)){
                $message = "Email nebyl odeslan - Mailer selhal pri odesilani emailu - email_id = '$email_id', user_id = '$user_id'.";
                if(!$redirect){
                    throw new Ibulletin_Auth_Send_Special_Email_Exception($message);
                }
                else{
                    // Pouze zalogujeme
                    Phc_ErrorLog::warning('Ibulletin_Auth::send_special_email()', $message);
                }
                return false;
            }
            else{
                return true;
            }
        }
        else{

        }
    }
}
