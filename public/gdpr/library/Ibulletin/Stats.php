<?php

/**
 * iBulletin - Stats.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


class Ibulletin_Stats_Exception extends Exception {}
/**
 * Codes:
 * 16 - Nepodarilo se ulozit rating page
 * 17 - Nepodarilo se ziskat rating page
 * 19 - Nepodarilo se ziskat rating bulletinu
 * 20 - Nepodarilo se ulozit data do sessions_by_reps, neni zadan nektery z potrebnych argumentu
 * 21 - Nepodarilo se ulozit data do resources_stats
 */
class Ibulletin_Stats_No_User_Set_Exception extends Exception {}

/**
 * Stara se o vsechny akce potrebne k ukladani informaci o chovani a akcich v session,
 * daneho uzivatele a podobne. Inicializace a prvnotni logovani se provede zavolanim
 * Ibulletin_Stats::getInstance();
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Stats
{
    /**
     * Id aktualni session
     *
     * @var int
     */
    public $session_id = null;

    /**
     * Id aktualniho zobrazeni stranky (page_view)
     *
     * @var int
     */
    public $page_view_id = null;

    /**
     * Nejedna se o aktualne bezici session v aplikaci, ale jinou.
     * (nepouzivame aktualniho uzivatele ani aktualni cookie)
     */
    public $notCurrentSession = false;

    /**
     * Jedina instance tridy.
     *
     * @var Ibulletin_Stats
     */
    private static $_inst = null;

    /**
     * @var bool    Maji byt statisticka data ulozena?
     *
     * Pouziva se k zakazani ukladani nezadoucich statistickych informaci, napriklad
     * pri generovani jedineho souboru s kodem JS.
     */
    private $_noSaving = false;

    /**
     * Session namespace pro stats.
     *
     * @var Zend_Session_Namespace
     */
    private $_statsNamespace = null;

    /**
     * Invitation ID z tabulky invitation_waves
     *
     * @var int
     */
    private $_invitation_id = null;

    /**
     * User ID z tabulky users
     *
     * @var int
     */
    private $_user_id = null;

    /**
     * Cookie ID z tabulky cookies
     *
     * @var int
     */
    private $_cookie_id = null;

    /**
     * Je javascript zapnuty?
     *
     * @var bool
     */
    private $_javascript = null;

    /**
     * Jsou zapnute cookies?
     *
     * @var bool
     */
    private $_cookies = null;

    /**
     * Flashplayer ID z tabulky flashplayers
     *
     * @var int
     */
    private $_flashplayer_id = null;

    /**
     * Browser ID z tabulky browsers
     *
     * @var int
     */
    private $_browser_id = null;

    /**
     * Os ID z tabulky os
     *
     * @var int
     */
    private $_os_id = null;

    /**
     * Resolution ID z tabulky cookies
     *
     * @var int
     */
    private $_resolution_id = null;

    /**
     * Link ID z tabulky links
     *
     * @var int
     */
    private $_link_id = null;

    /**
     * Akce s kterou toto page_view souvisi
     *
     * @var string
     */
    private $_action = null;

    /**
     * IP klienta
     *
     * @var string
     */
    private $_ip = null;

    /**
     * URL zadana do prohlizece
     *
     * @var string
     */
    private $_url = null;

    /**
     * Referer
     *
     * @var string
     */
    private $_referer = null;

    /**
     * Module, controller, action - last data saved to module controller and action_name in page_views
     * This is used for accurate settings of module after forwarding and to prevent from unnescessary
     * saving to database if nothing changed.
     *
     * @var array
     */
    private $_lastModuleControllerAction = array('module' => '', 'controller' => '', 'action' => '');

    /**
     * Pole obsahujici hodnoty pro ulozeni pri pristim ukladani
     * do tabulky sessions
     *
     * @var array
     */
    private $_toSave_sessions = array();

    /**
     * Atributy v tabulce sessions
     *
     * @var array
     */
    private $_sessions_attribs = array(
       'id', 'cookie_id', 'javascript', 'cookies', 'flashplayer_id', 'browser_id',
       'os_id', 'resolution_id', 'user_agent_id', 'session_length', 'user_id', 'sessions.timestamp', 'timezone', 'comment'
       );

       /**
        * Pole obsahujici hodnoty pro ulozeni pri pristim ukladani
        * do tabulky page_views
        *
        * @var array
        */
       private $_toSave_page_views = array();

       /**
        * Atributy v tabulce page_views
        *
        * @var array
        */
       private $_page_views_attribs = array(
       'id', 'session_id', 'link_id', 'timestamp', 'action', 'page_id', 'category_id',
       'bulletin_id', 'ip', 'url', 'referer', 'view_length', 'users_links_tokens_id', 'invitation_id', 
       'users_emails_id', 'link_xpath', 'displayed_version', 'module', 'controller', 'action_name'
       );
       
       /**
        * @var Byla jiz ulozena data requestu do request_data?
        */
        private $_requestDataSaved = false;


       /**
        * Konstruktor
        *
        * @param string     Identifikator session namespace, pokud neni zacdan, pouzije se defaultni
        *                   'stats' - ten pouzivame pro aktualni session uzivatele webu (frontendu).
        *                   Jina namespace pouzivame v pripade vytvareni tridy kvuli zapisu do
        *                   page_views a sessions mimo bezne poradi zapisu (napriklad pro data prijata z iPadu)
        *                   - napriklad zapis jine session nez v ktere prave jsem.
        * @param bool       Nejedna se aktualne bezici session ani page view. Proto se napriklad nastavi uplne nova cookie
        *                   a ne ta, ktera je v $_SESSION.
        */
       public function __construct($namespace = null, $notCurrentSession = false)
       {
           
           $config = Zend_Registry::get('config');
           
           // Namespace
           if(empty($namespace)){
               $namespace = $config->sessions->namespace->stats;
           }

           $this->notCurrentSession = $notCurrentSession;

           // Inicializace session namespace
           Zend_Loader::loadClass('Zend_Session_Namespace');
           $this->_statsNamespace = new Zend_Session_Namespace($namespace);

           // Inicializace casove zony
           if(!$notCurrentSession){
               $this->initializeTimezone();
           }

           // Pokud uzivatel prave prisel, inicializujeme novou session
           if(!isset($this->_statsNamespace->session_id)){
               $this->initializeNewSession();
           }
           // Pro jiz bezici session nacteme session ID do atributu
           else{
               $this->session_id = $this->_statsNamespace->session_id;
           }

           //Provedeme zakladni zaznam zobrazeni stranky do page_views
           $this->initializePageViews();

           // Podle stavu $this->_statsNamespace->browserDetectionDone
           // nastavime do Ibulletin_HtmlHead priznak pro vypsani jvascript kodu
           // spoustejici ziskani informaci o uzivatelove prohlizeci
           if(!isset($this->_statsNamespace->browserDetectionDone) ||
           $this->_statsNamespace->browserDetectionDone === false || $this->_statsNamespace->browserDetectionDone != $this->_statsNamespace->session_id){
               Ibulletin_HtmlHead::setBrowserDetectionUndone();
               //$renderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');
               //$renderer->view->getBrowserInfo = true;
           }
           
           // Ulozime data requestu do request_data
           $this->saveRequestData();
       }


       /**
        * Vrati existujici instanci Ibulletin_Stats nebo vytvori novou a tu vrati.
        * Pouziva se pro hlavni instanci singletonu, pro vedlejsi instance nesmime nikdy
        * pouzit getInstance, protoze vraci instanci silngletonu a ne instanci objektu.
        *
        * @return Ibulletin_Stats Jedina instance Ibulletin_Stats v aplikaci
        */
       public static function getInstance()
       {
           if(self::$_inst === null){
               self::$_inst = new Ibulletin_Stats();
           }

           return self::$_inst;
       }


       /**
        * Pripravi novou session a ulozi ji do DB.
        */
       public function initializeNewSession()
       {
           $cookie_id = $this->getCookieId();
           $this->setAttrib('cookie_id', $cookie_id);

           $invitation_id = $this->getInvitationWave();
           $this->setAttrib('invitation_id', $invitation_id);
           
           // User agent
           $uaId = $this->setUserAgent();
           $this->setAttrib('user_agent_id', $uaId);

           // Rozparsujeme a ulozime informace z user_agent pomoci BrowserDetection
           require_once('Phc/BrowserDetection.php');
           $os = browser_detection('os');
           $os_ver = browser_detection('os_number');
           $ua_type = browser_detection('ua_type');
           $this->setOs($os, $os_ver,$ua_type);
           $browser = browser_detection('browser_working');
           $browser_ver = browser_detection('browser_number');
           $browser_fullname = browser_detection('browser_name');
           $this->setBrowser($browser, $browser_ver, $browser_fullname);

           $this->saveToSessions();

           $this->_statsNamespace->session_id = $this->session_id;
       }

       /**
        * Inicializuje timezone. Nastavi jednak pro DB SET TIME ZONE na casovou zonu bud podle
        * cookie uzivatele nebo na zonu podle configu. Zaroven tuto zonu nastavi do PHP.
        *
        * Pri zadani $utcOffset nastavi vyse zminene parametry, ulozi do cookie a session zonu pro
        * rychlejsi nastavovani v dalsich pozadavcich. Pro zadane offsety hleda nazev timezone s
        * preferenci pro default_timezone a awaited_timezones z configu->general.
        *
        * @param int    UTC offset v minutach
        */
       public function initializeTimezone($utcOffset = null)
       {
           $config = Zend_Registry::get('config');

           // Nastavime podle offsetu
           if(!empty($utcOffset)){
                $db = Zend_Registry::get('db');
                // Preferujeme casove zony z predepsaneho seznamu
                $timezones = $config->general->getCommaSeparated('awaited_timezones'); 
                // Pokud je jiz v session ulozena zona, pridame ji na seznam preferovanych
                if(!$this->notCurrentSession && !empty($this->_statsNamespace->timezone)){
                    array_unshift($timezones, $this->_statsNamespace->timezone);
                }
                $timezonesList = "'".join("','", $timezones)."'";
                // Dale preferujeme casove zony se stejnou oblasti
                $defaultTz = $config->general->default_timezone;
                $area = explode('/', $defaultTz);
                $area = $area[0];
                $q = "SELECT name FROM (
                    (SELECT name, 1 AS sort FROM pg_timezone_names
                    WHERE name IN ($timezonesList) AND utc_offset = '{$utcOffset}m'::interval)
                    UNION
                    (SELECT name, 2 AS sort FROM pg_timezone_names
                    WHERE name ~ '$area' AND utc_offset = '{$utcOffset}m'::interval ORDER BY name)
                    UNION
                    (SELECT name, 3 AS sort FROM pg_timezone_names
                    WHERE utc_offset = '{$utcOffset}m'::interval ORDER BY name)
                    ) AS foo ORDER BY sort, name LIMIT 1
                    ";
                $timezone = $db->fetchOne($q);

                //Phc_ErrorLog::notice('dbg', $q);

                if(empty($timezone)){
                    $timezone = $config->general->default_timezone;
                }
                elseif(!$this->notCurrentSession){
                    $this->_statsNamespace->timezone = $timezone;
                }
           }
           // Pokud je casova zona jiz nastavena v session, pouze nastavime v PHP
           elseif(!$this->notCurrentSession && !empty($this->_statsNamespace->timezone)){
                $timezone = $this->_statsNamespace->timezone;
           }
           else{
               // Zkusime ziskat posledni timezone ze sessions
               if(!$this->notCurrentSession && !empty($this->_statsNamespace->session_id)){
                   $db = Zend_Registry::get('db');
                   $q = 'SELECT s."timezone" FROM sessions s JOIN '.
                    '(SELECT user_id FROM sessions WHERE id = '.$this->_statsNamespace->session_id.') foo '.
                    ' ON s.user_id = foo.user_id WHERE s.id != '.$this->_statsNamespace->session_id.' AND '.
                    's.timezone IS NOT NULL AND s.timezone != \'\' ORDER BY s.id DESC LIMIT 1';

                   //Phc_ErrorLog::notice('dbg', $q);

                   $timezone = $db->fetchOne($q);
                   if(!empty($timezone)){
                       $this->_statsNamespace->timezone = $timezone;
                   }
               }
           }

           if(empty($timezone)){
               // Pouzijeme defaultni timezone
               $timezone = $config->general->default_timezone;
           }

           $db = Zend_Registry::get('db');

           // Nastavime nalezenou casovou zonu do PHP a pro pripojeni do DB
           if(!$this->notCurrentSession){
               date_default_timezone_set($timezone);
               $q = "SET TIME ZONE '$timezone'";
               $db->getConnection()->exec($q);
               //Phc_ErrorLog::notice('timezone', $timezone);
           }
           $this->setAttrib('timezone', $timezone);
       }

       /**
        * Zrusi aktualni session uzivatele - pristi pozadavek jiz bude do ciste session.
        */
       public function dropSession()
       {
           unset($this->_statsNamespace->session_id);
       }

       /**
        * Posbira data do page_views, ktera jsou k dispozici, a ulozi je do DB.
        * Uklada IP, URL a referer
        */
       public function initializePageViews()
       {
           //IP
           $ipRegExp = '/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/i';
           // Pokud je k dispozici remote_addr, ulozime si ji pro pripad, ze v x forwarded nenajdeme IP
           if(isset($_SERVER['REMOTE_ADDR']) && preg_match($ipRegExp, $_SERVER['REMOTE_ADDR'])){
               $remoteAddr = $_SERVER['REMOTE_ADDR'];
           }
           elseif(isset($_SERVER['HTTP_CLIENT_IP']) && preg_match($ipRegExp, $_SERVER['HTTP_CLIENT_IP'])){
               $remoteAddr = $_SERVER['HTTP_CLIENT_IP'];
           }
           if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
               $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
               if(!preg_match($ipRegExp, $ip)){
                   $ipA = explode(", ", $ip);
                   $ip1 = $ipA[(count($ipA)-1)];
                   if(!preg_match($ipRegExp, $ip1)){
                       $ip = isset($remoteAddr) ? $remoteAddr : "1.1.1.1";
                   }
                   else{
                       $ip = $ip1;
                   }
               }
           }
           else{
               $ip = isset($remoteAddr) ? $remoteAddr : "1.1.1.1";
           }
           $this->setAttrib('ip', $ip);

           //URL
           isset($_SERVER['REQUEST_URI']) ? $url = $_SERVER['REQUEST_URI'] : $url = "";
           $this->setAttrib('url', $url);

           //referer
           isset($_SERVER['HTTP_REFERER']) ? $referer = $_SERVER['HTTP_REFERER'] : $referer = "";
           $this->setAttrib('referer', $referer);

           $request = Zend_Controller_Front::getInstance()->getRequest();
           $params = $request->getParams();

           //xpath linku ze ktereho se odkazujeme
           if (isset($_COOKIE['link_xpath_cookie'])) {
               $xpath = urldecode($_COOKIE['link_xpath_cookie']);
               // v pripade delsi xpath nez 150 znaku skratime retezec a zalogujeme
               if (strlen($xpath) > 500) {
                   $xpath = substr($xpath, 0, 499);
                   Phc_ErrorLog::warning('Ibulletin_stats::initializePageViews', "Pri ukladani link_xpath_cookie : '$xpath' doslo ke zkraceni na 500 znaku.");
               }

               $this->setAttrib('link_xpath', $xpath);
               // odstranime cookie protoze sme ji zpracovali
               setCookie('link_xpath_cookie', '', 1, '/','');
           }

           //ulozime do DB (ukladame, protoze chceme mit uz od zacatku k dispozici page_views id,
           // ktere potrebujeme obcas ukladat do ruznych tabulek)
           $this->saveToPageViews();
       }

       /**
        * Vytvori v DB a zavede uzivateli do prohlizece novou cookie.
        *
        * @param int jiz existujici cookie id, ktera se ma nastavit uzivateli do prohlizece
        * @return int Nove cookie id pro tento prohlizec
        * @throws Ibulletin_Stats_Exception
        */
       public function setNewUserCookie($cookie_id = null)
       {
           $config = Zend_Registry::get('config');
           $db = Zend_Registry::get('db');
           Zend_Loader::loadClass('Ibulletin_RandomStringGenerator');
           $rGenerator = new Ibulletin_RandomStringGenerator();

           // Pokud nebyla zadana cookie, nejprve ji nagenerujeme
           if($cookie_id === null){
               for($i=0; $i<1000; $i++){
                   $break = true;
                   try
                   {
                       $string = $rGenerator->get(20);
                       $data = array('string' => $string);
                       $db->insert('cookies', $data);

                       $id = $db->lastInsertId('cookies', 'id');
                   }
                   catch(Zend_Db_Statement_Exception $e)
                   {
                       $break = false;
                   }

                   if($break){
                       break;
                   }
               }

               if(!isset($id)){
                   throw new Ibulletin_Stats_Exception(
                    'Nepodarilo se vytvorit novou unikatni cookie v DB.');
               }
           }
           else{
               // Ziskame string od cookie_id zadaneho do metody
               $q = sprintf('SELECT string FROM cookies WHERE id=%d', $cookie_id);
               $string = $db->fetchOne($q);
               $id = $cookie_id;

               // Pokud cookie nebyla nalezena vratime null
               if(empty($string)){
                   return null;
               }
           }

           if(!$this->notCurrentSession){
               // Zivotnost users cookie podle configu (kvuli politice napr. GSK) nebo 1000 dni 
           		if($config->general->cookies_timeout){
           		    $time = time()+(int)$config->general->cookies_timeout;
           		}
           		else{
           			$time = time()+60*60*24*1000;
           		}
           		
               setCookie('ibulletin_users_cookie', $string, $time, '/');
           }

           return $id;
       }

       /**
        * Smaze v uzivatelove prohlizeci ibulletin_users_cookie.
        * (pouziva se napriklad pri tvrdem odhlaseni)
        */
       public function removeUserCookie()
       {
           if(!$this->notCurrentSession){
               setCookie('ibulletin_users_cookie', '', 0, '/');
           }
       }

       /**
        * Zjisti cookie ID z cookie od uzivatele, pripadne podle id pro tuto session z DB,
        * nebo vrati nove cookie ID.
        *
        * @return int Cookie id pro tuto session
        */
       public function getCookieId()
       {
           $db = Zend_Registry::get('db');

           // Pokud uzivatel neposlal cookie, vratime novou a nastavime mu ji
           if(!isset($_COOKIE['ibulletin_users_cookie']) || $this->notCurrentSession){
               return $this->setNewUserCookie();
           }

           $cookie = $_COOKIE['ibulletin_users_cookie'];

           $cookie = $db->quote($cookie);
           $q = "SELECT id FROM cookies WHERE string = $cookie";
           $id = $db->fetchOne($q);

           //Pokud ma uzivatel neznamou cookie, priradime mu novou
           if(empty($id)){
               return $this->setNewUserCookie();
           }

           return $id;
       }

       /**
        * Spoji dva existujici uzivatele do jednoho s tim, zustane zaznam prvniho zadaneho uzivatele
        * v tabulce users a ve vsech ostatnich tabulkach zmeni user_id uzivatele z druheho
        * parametru na user_id uzivatele z prvniho parametru.
        *
        * TODO Nespojuje zaznam z tabulky users, stary zaznam pouze smaze. Mel by take vybirat lepsi z hodnot pro deleted, send_emails a podobne.
        *
        * @param int   ID uzivatele na ktereho ma byt ID zmeneno
        * @param int   ID uzivatele ktery ma byt migrovan na jineho, pri nezadani je pouzit
        *              prihlaseny uzivatel
        * @param bool  Obnovit smazane uzivatele?
        * @throws Ibulletin_Stats_No_User_Set_Exception    Pokud nebyl zadan cilovy uzivatel
        *         a pri tom neni zadny uzivatel prihlasen
        */
       public static function mergeUsers($target_uid, $from_uid = null, $undeleteUsers = true)
       {
           $db = Zend_Registry::get('db');

           $is_actual_user = false;
           if($from_uid === null){
               $from_uid = Ibulletin_Auth::getActualUserId();
           }
           if(Ibulletin_Auth::getActualUserId() == $from_uid){
               $is_actual_user = true;
           }
           if($from_uid === null){
               throw new Ibulletin_Stats_No_User_Set_Exception('Nebylo zadano ID uzivatele ani neni zadny uzivatel prihlasen.');
           }

           // Pokud jsou zadani stejni uzivatele from a target, nedelame nic
           if($from_uid == $target_uid){
               return;
           }

           // Zjistime existujici cookies uzivatele from pro pripadne pozdejsi odstraneni
           $sel = $db->select()
           ->from(array('u' => 'users'), 's.cookie_id')
           ->join(array('s' => 'sessions'), 's.user_id = u.id')
           ->where('u.id = :id AND s.cookie_id IS NOT NULL')
           ->order('s.timestamp');
           $from_cookies = $db->fetchAll($sel, array('id' => $from_uid));

           // Zjistime cookie ID stareho uzivatele
           $sel->limit(1);
           $target_cookie_id = $db->fetchOne($sel, array('id' => $target_uid));

           // Pokud existuje nejake cookie_id existujiciho uzivatele, zmenime cookie id
           // vsech sessions aktualniho uzivatele na cookie id existujiciho
           // Nahradime ve vsech session s puvodnim uzivatelem na jiz existujiciho uzivatele
           if(!empty($target_cookie_id)){
               $affected = $db->update('sessions', array('cookie_id' => $target_cookie_id), sprintf('user_id = %d', $from_uid));

               // Pokud pracujeme s aktualnim uzivatelem zmenime mu cookie v prohlizeci
               if($is_actual_user){
                   Ibulletin_Stats::getInstance()->setNewUserCookie($target_cookie_id);
                   Ibulletin_Auth::setUser($target_uid);
               }

               // Odstranime cookies z tabulky cookies ktere patrily from uzivateli
               foreach($from_cookies as $cookie_id){
                   $cookie_id = $cookie_id['cookie_id'];

                   // Pro jistotu zkusime najit zaznamy odkazujici na danou cookie,
                   // pokud nejake existuji, tak je zalogujeme a cookie_id nastavime na jinou patrici danemu uzivateli
                   // Tento problem suvisi s vice uzivateli pouzivajici stejny pocitec, tedy jednu cookie mohlo pouzivat vice uzivatelu
                   $sel = $db->select()
                       ->from('sessions')
                       ->where('cookie_id = :cid');
                   $existing_sessions = $db->fetchAll($sel, array('cid' => $cookie_id));
                   if($existing_sessions){
                       // naskladame vsechny nalezene zaznamy ze sessions do stringu
                       $to_log = '';
                       foreach($existing_sessions as $val) {
                           $to_log .= join(' | '.$val)."\r\n";
                       }
                       Phc_ErrorLog::notice('Ibulletin_stats::mergeUsers', "Pri spojovani uzivatelu $from_uid -> $target_uid zbyly v tabulce sessions nasledujici zaznamy s prave mazanou cookie:\r\n" . $to_log);
                       // Najdeme nektereho majitele teto cookie, ktery neni $from_uid
                       foreach($existing_sessions as $session){
                           if($session['user_id'] != $from_uid){
                               $newCookieOwner = $session['user_id'];
                               break;
                           }
                       }
                       $db->update('sessions', array('cookie_id' => $newCookieOwner), 'cookie_id = '.$cookie_id);
                   }

                   $db->delete('cookies', sprintf('id = %d', $cookie_id));
               }
           }

           // Nahradime postupne ve vsech tabulkach, kde se vyskytuje user_id
           // z from_id na target_id
           // ------------------------------------------------------------------------------------
           // USERS_REPS - nahrazeni repa...
           $db->update('users_reps', array('repre_id' => $target_uid), sprintf('repre_id = %d', $from_uid));
           $db->update('users_reps', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // USERS_ATTRIBS
           $db->update('users_attribs', array('user_id' => $from_uid), sprintf("user_id = %d AND id NOT IN
            (SELECT id FROM users_attribs WHERE user_id = %d)", $target_uid, $from_uid));
           $db->delete('users_attribs', sprintf('user_id = %d', $target_uid));
           $db->update('users_attribs', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // SESSIONS
           $db->update('sessions', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // USERS_LINKS_TOKENS
           $db->update('users_links_tokens', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // USERS_EMAILS
           $db->update('users_emails', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           $db->update('users_emails', array('sent_by_user' => $target_uid), sprintf('sent_by_user = %d', $from_uid));
           // CONSULTING_NOTIFICATIONS
           $sel = $db->select()
           ->from('consulting_notifications')
           ->where('user_id = :uid');
           $targetUser = $db->fetchAll($sel, array('uid' => $target_uid));
           $fromUser = $db->fetchAll($sel, array('uid' => $from_uid));
           $existing = array();
           foreach($targetUser as $row){
               $existing[] = $row['role'];
           }
           foreach($fromUser as $row){
               if(!in_array($row['role'], $existing)){
                   $row['user_id'] = $target_uid;
                   $db->insert('consulting_notifications', $row);
               }
           }
           $db->delete('consulting_notifications', sprintf('user_id = %d', $from_uid));
           // CONSULTING_QUESTIONS
           $db->update('consulting_questions', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // USERS ANSWERS
           /*
           $db->update('answers', array('user_id' => $from_uid), sprintf("user_id = %d AND question_id NOT IN
           (SELECT question_id FROM answers WHERE user_id = %d)", $target_uid, $from_uid));
           $db->delete('answers', sprintf('user_id = %d', $target_uid));
           $db->update('answers', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           */
           // Je treba presunout do logu odpovedi od kterych ma druhy uzivatel novejsi verze jeste pred zmenou UIDs
           $db->beginTransaction();
           try{
               $binds = array(array('user1' => $from_uid, 'user2' => $target_uid),
                              array('user2' => $from_uid, 'user1' => $target_uid));
               //* // Zda se, ze tato konstrukce nefunguje, neprovedou se INSERTy timto zpusobem
               $qs = array();
               $qs[1] = "INSERT INTO users_answers_log (users_answers_id, question_id, page_views_id,
                    user_id, answer_id, timestamp, type, text, indetail_stats_id, answer_int,
                    answer_double, answer_bool)
                        (SELECT ua1.id, ua.question_id, ua.page_views_id, ua.user_id, ua.answer_id,
                            ua.timestamp, ua.type, ua.text, ua.indetail_stats_id, ua.answer_int,
                            ua.answer_double, ua.answer_bool
                         FROM users_answers ua
                         JOIN users_answers ua1 ON ua.question_id = ua1.question_id
                         WHERE ua.user_id = :user1 AND ua1.user_id = :user2 AND ua.timestamp < ua1.timestamp
                        )";

               $qs[2] = "INSERT INTO users_answers_choices_log (users_answers_log_id, answer_id)
                            (SELECT ualog.id, uac.answer_id
                             FROM users_answers ua
                             JOIN users_answers ua1 ON ua.question_id = ua1.question_id
                             JOIN users_answers_log ualog ON ualog.question_id = ua.question_id
                                AND ualog.user_id = ua.user_id AND ualog.users_answers_id = ua.id
                                AND ualog.page_views_id = ua.page_views_id AND ualog.timestamp = ua.timestamp
                             JOIN users_answers_choices uac ON uac.users_answers_id = ualog.users_answers_id
                             WHERE ua.user_id = :user1 AND ua1.user_id = :user2 AND ua.timestamp < ua1.timestamp
                                AND uac.users_answers_id IS NOT NULL
                            )
                    ";
               $qs['delete'] = "DELETE FROM users_answers
                        WHERE id IN (SELECT ua.id FROM users_answers ua
                                     JOIN users_answers ua1 ON ua.question_id = ua1.question_id
                                     WHERE ua.user_id = :user1 AND ua1.user_id = :user2 AND ua.timestamp < ua1.timestamp
                                    )";

               foreach($binds as $bind){
                   foreach($qs as $key => $q){
                        $db->query($q, $bind);
                        //Phc_ErrorLog::error('uaMerge', $q);
                   }
               }

                /*
                $qs = array();
                $qs[1] = "SELECT ua.id AS users_answers_id, ua.question_id, ua.page_views_id, ua.user_id, ua.answer_id,
                            ua.timestamp, ua.type, ua.text, ua.indetail_stats_id, ua.answer_int,
                            ua.answer_double, ua.answer_bool
                         FROM users_answers ua
                         JOIN users_answers ua1 ON ua.question_id = ua1.question_id
                         WHERE ua.user_id = :user1 AND ua1.user_id = :user2 AND ua.timestamp < ua1.timestamp
                        ";

                $qs[2] = "SELECT ualog.id AS users_answers_log_id, uac.answer_id
                             FROM users_answers ua
                             JOIN users_answers ua1 ON ua.question_id = ua1.question_id
                             JOIN users_answers_log ualog ON ualog.question_id = ua.question_id
                                AND ualog.user_id = ua.user_id AND ualog.users_answers_id = ua.id
                                AND ualog.page_views_id = ua.page_views_id AND ualog.timestamp = ua.timestamp
                             JOIN users_answers_choices uac ON uac.users_answers_id = ualog.users_answers_id
                             WHERE ua.user_id = :user1 AND ua1.user_id = :user2 AND ua.timestamp < ua1.timestamp
                                AND uac.users_answers_id IS NOT NULL

                    ";
                $qs['delete'] = "DELETE FROM users_answers
                        WHERE id IN (SELECT ua.id FROM users_answers ua
                                     JOIN users_answers ua1 ON ua.question_id = ua1.question_id
                                     WHERE ua.user_id = :user1 AND ua1.user_id = :user2 AND ua.timestamp < ua1.timestamp
                                    )";

                foreach($binds as $bind){
                    $uas = $db->fetchAll($qs[1], $bind);
                    foreach($uas as $ua){
                        $db->insert('users_answers_log', $ua);
                    }

                    $uacs = $db->fetchAll($qs[2], $bind);
                    foreach($uacs as $uac){
                        $db->insert('users_answers_choices_log', $uac);
                    }

                    $db->query($qs['delete'], $bind);
                }
                */

               $db->update('users_answers', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
               $db->update('users_answers_log', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
               // INDETAIL_STATS
               $db->update('indetail_stats', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
               $db->commit();
           }
           catch(Exception $e){
               Phc_ErrorLog::error('Ibulletin_Stats::mergeUsers()', "Chyba pri pokusu o vytrideni tabulek ".
                    "kolem users_answers. from_uid: '$from_uid', target_uid: '$target_uid'  \nPuvodni vyjimka: $e");
               $db->rollBack();
           }
           // change_list
           $db->update('change_list', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // OPINIONS
           $db->update('opinions', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // PLAYERS_STATS
           $db->update('players_stats', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // VP_USERS_ANSWERS
           $db->update('vp_users_answers', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // PAGE_RATINGS
           $db->update('page_ratings', array('user_id' => $target_uid), sprintf('user_id = %d AND '
                .'page_id NOT IN (SELECT page_id FROM page_ratings WHERE user_id = %d)', $from_uid, $target_uid));
           $db->delete('page_ratings', sprintf('user_id = %d', $from_uid));
           // ADMIN_USERS
           $db->update('admin_users', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));
           // consulting_consultants
           $db->update('consulting_consultants', array('user_id' => $target_uid), sprintf('user_id = %d', $from_uid));

           // Pokud je nastaveno, ze ma byt smazany uzivatel oznacen za nesmazaneho, porvedeme
           if($undeleteUsers){
               $db->update('users', array('deleted' => null), sprintf('id = %d', $target_uid));
           }

           // Vse je hotovo, odtranime from user z users
           $db->delete('users', sprintf('id = %d', $from_uid));
       }

       /**
        * Pokusi se najit posledni cookie pouzitou prave prihlasenym uzivatelem
        * a tu mu nastavi s tim, ze zaznamy aktualni cookie jsou prevedeny na posledni
        * pouzitou timto uzivatelem a aktualni cookie je nasledne smazana z databaze
        * cookies.
        * 
        * DEPRECATED - nepouzivame kvuli vice uzivatelum na stejnem pocitaci (vice uzivatelu pak muze mit stejnou cookie ticket #49)
        *
        * @param int Id uzivatele z tabulky users
        */
       public function restoreCookie($user_id)
       {
           // NIKDY NEHLEDAME STAROU COOKIE, VZDY PRIDELIME NOVOU
           return;
           
           
           
           //$inst = Ibulletin_Stats::getInstance();
           $db = Zend_Registry::get('db');

           $session_id = $this->session_id;

           $q = sprintf('SELECT cookie_id FROM sessions WHERE id=%d', $session_id);
           $new_cookie_id = $db->fetchOne($q);

           // Zjisitme, jestli tato cookie byla jiz pouzita pro nejakeho uzivatele
           $q = sprintf('SELECT count(distinct(user_id)) FROM sessions WHERE cookie_id = %d', $new_cookie_id);
           $users_count = $db->fetchOne($q);

           // Pokud cookie jiz byla pouzita, neni treba nic menit a skoncime
           if($users_count > 0){
               return;
           }

           // Cookie nebyla pouzita
           // Pokusime se najit posledni cookie pouzitou aktualnim uzivatelem
           $q = sprintf('SELECT cookie_id FROM sessions WHERE user_id = %d
                      ORDER BY id DESC LIMIT 1', $user_id);
           $old_cookie_id = $db->fetchOne($q);

           //Pokud uzivatel nema zadnou starou cookie, neni treba nic menit a muzeme vyskocit
           if(empty($old_cookie_id)){
               return;
           }

           // Nastavime starou cookie do uzivatelova prohlizece
           if(!$this->notCurrentSession){
               $this->setNewUserCookie($old_cookie_id);
           }

           // Vsechny session s aktualni cookie priradime tomuto uzivateli
           $q = sprintf('UPDATE sessions SET cookie_id=%d
                        WHERE cookie_id=%d',
           $old_cookie_id, $new_cookie_id);
           $db->fetchOne($q);

           // zjistime, jestli je nova jiz zmenena cookie v sessions uvedena, pokud ne,
           // muzeme ji zrusit z cookies
           $q = sprintf('SELECT count(id) FROM sessions WHERE cookie_id=%d', $new_cookie_id);
           $used_times = $db->fetchOne($q);
           if($used_times == 0){
               $db->delete('cookies', sprintf('id=%d', $new_cookie_id));
           }
       }

       /**
        * Zvysi pocitadlo poctu pouziti u zadaneho linku.
        *
        * @param int Id z tabulky links
        */
       public static function increaseLinkUsed($id)
       {
           $db = Zend_Registry::get('db');
           $q = sprintf('UPDATE links SET used_times =
                        (SELECT used_times + 1 FROM links WHERE id=%d)
                      WHERE id=%d', $id, $id);
           $db->fetchOne($q);
       }

       /**
        * Ulozi rating stranky (page) do DB
        *
        * @param int $page_id  Id page.
        * @param int $rating   Rating stranky k ulozeni.
        * @param int $user_id  Id uzivatele, pokud nezadano, pouzije se aktualni
        *
        * @throws Ibulletin_Stats_No_User_Set_Exception    Pokud neni k dispozici zadny uzivatel.
        * @throws Ibulletin_Stats_Exception    Pokud se nepodari data ulozit. Code 16
        */
       public static function savePageRating($page_id, $rating, $user_id = null){
           $db = Zend_Registry::get('db');

           if($user_id === null){
               $user_id = Ibulletin_Auth::getActualUserId();
           }

           if($user_id === null){
               throw new Ibulletin_Stats_No_User_Set_Exception('Nebylo zadano ID uzivatele ani neni zadny uzivatel prihlasen.');
           }

           $select = new Zend_Db_Select($db);
           $select->from('page_ratings', new Zend_Db_Expr('avg(rating)'))
           ->where('page_id=?', $page_id)
           ->where('user_id=?', $user_id);
           $savedRating = $db->fetchOne($select);

           try{
               if($savedRating){
                   $db->update('page_ratings', array('rating' => (int)$rating),
                   sprintf('user_id = %d AND page_id=%d', $user_id, $page_id));
               }
               else{
                   $data = array('page_id' => (int)$page_id, 'user_id' => (int)$user_id, 'rating' => (int)$rating);
                   $db->insert('page_ratings', $data);
               }
           }
           catch(Zend_Db_Exception $e){
               throw new Ibulletin_Stats_Exception('Nepodarilo se ulozit data ratingu, pravdepodobne'.
            ' zadano nespravne ID uzivatele nebo page_id. user_id: '.$user_id.' page_id: '.$page_id.
            ' Puvodni vyjimka:'."\n$e", 16);
           }
       }

       /**
        * Vrati rating stranky (page) z DB - bud pro jednoho uzivatele nebo celkovy.
        * Pro uzivatele, kteri jsou TEST vaci ve vysledku i uzivatele TEST, jinak pouziva
        * pohled users_vf pro vyfiltrovani testovacich a jinych uzivatelu
        *
        * @param int $page_id  Id page.
        * @param bool $overall Vratit rating napric vsech uzivatelu
        * @param int $user_id  Id uzivatele, pokud nezadano, pouzije se aktualni
        * @return array        Pole s klici avg (prumerne hodnoceni), count (pocet hodnoceni),
        *                      rating (hodnoceni konkretniho uzivatele - pokud nehodnotil, je null),
        *                      pokud neni uzivatel zadan, pouzije se aktualni uzivatel.
        *
        * @throws Ibulletin_Stats_No_User_Set_Exception    Pokud neni k dispozici zadny uzivatel.
        * @throws Ibulletin_Stats_Exception    Pokud se nepodari ziskat data. Code 17
        */
       public static function getPageRating($page_id, $overall = false, $user_id = null){
           $db = Zend_Registry::get('db');

           if($user_id === null){
               $user_id = Ibulletin_Auth::getActualUserId();
           }

           if($user_id === null && !$overall){
               throw new Ibulletin_Stats_No_User_Set_Exception('Nebylo zadano ID uzivatele ani neni zadny uzivatel prihlasen.');
           }

           $cols = array('avg' => new Zend_Db_Expr('avg(rating)'),
                        'count' => new Zend_Db_Expr('count(*)'));
           if(!$overall){
                $cols['rating'] = new Zend_Db_Expr('(SELECT rating FROM page_ratings WHERE
                    page_id='.(int)$page_id.' AND user_id='.(int)$user_id.')');
           }

           $select = new Zend_Db_Select($db);
           $select->from(array('pr' => 'page_ratings'), $cols);
           $select->where('page_id=?', $page_id);


           // Pro uzivatele test, client, NOT target nejoinujeme s users_vf
           $actualUser = Ibulletin_Auth::getActualUserData();
           if(!$actualUser || (!$actualUser['test'] && !$actualUser['client'] && $actualUser['target'])){
               $select->join(array('u' => 'users_vf'), 'u.id = pr.user_id', array());
           }


           try{
               $ratingA = $db->fetchRow($select);
           }
           catch(Zend_Db_Exception $e){
               throw new Ibulletin_Stats_Exception('Nepodarilo se ziskat data ratingu, pravdepodobne'.
            ' zadano nespravne ID uzivatele nebo page_id. user_id: '.$user_id.' page_id: '.$page_id.
            ' Puvodni vyjimka:'."\n$e", 17);
           }

           return $ratingA;
       }


       /**
        * Vrati rating celeho bulletinu z DB.
        *
        * @param int $bulletin_id  Id bulletinu, pokud je nezadano, vrati se rating aktualniho bulletinu.
        * @return int          Rating bulletinu, vraci null, pokud neni bulletin.
        *
        * @throws Ibulletin_Stats_Exception    Pokud se nepodari ziskat data. Code 18
        */
        public static function getBulletinRating($bulletin_id = null) {
            $db = Zend_Registry::get('db');

            if($bulletin_id === null){
                $bulletin_id = Bulletins::getCurrentBulletinId();
            }
            if($bulletin_id === null){
                return null;
            }

            $pageL = Pages::getPagesList(null, (int)$bulletin_id);
            if(empty($pageL)){
                return 0;
            }
            $pageIds = array();
            foreach($pageL as $page){
                $pageIds[] = $page['page_id'];
            }

            $select = new Zend_Db_Select($db);
            $select->from('page_ratings', array('rating' => new Zend_Db_Expr('avg(rating)')))
                   ->where('page_id IN ('.join(',',$pageIds).')');

            $rating = $db->fetchOne($select);
            return round($rating);
        }


       /**
        * Ulozi statisticke informace o operacich prehravacu.
        *
        * @param int $content_id   Id contentu ve kterem je prehravac umisten
        * @param int $videoNum     Cislo videa v danem contentu - pro contenty video je vzdy 1
        *                          pro contenty inDetail prezentaci muze byt videi pro jeden content vice.
        * @param string $action    Jmeno zaznamenavane akce.
        * @param int $position     Pozice prehravace - bud slid nebo cas v ms.
        * @param string $embedName Jmeno embedu s kterym se stala udalost, default null.
        * @param int $webcast_id   Id webcastu, ve kterem je prehravac umisten, pokud takovy je,
        *                          jinak null.
        * @param int $user_id      Id uzivatele, pokud neni zadano, pouziva se aktualni uzivatel.
        */
       public static function savePlayerEvent($content_id, $videoNum, $action, $position,
       $embedName = null, $webcast_id = null, $user_id = null)
       {
           $db = Zend_Registry::get('db');

           $knownActions = array('latesttime', 'play', 'pause', 'seek', 'embed_start', 'embed_end',
        'fullscreen_start', 'fullscreen_end', 'slide');

           if($user_id === null){
               $user_id = Ibulletin_Auth::getActualUserId();
           }

           if($user_id === null){
               throw new Ibulletin_Stats_No_User_Set_Exception('Nebylo zadano ID uzivatele ani neni zadny uzivatel prihlasen.');
           }

           // Pokud mame neznamou akci, zalogujeme to
           if(!in_array($action, $knownActions)){
               Phc_ErrorLog::info('Ibulletin_Stats::savePlayerEvent()', 'Byla ulozena neznama akce prehravace: "'.
               $action.'".');
           }

           $data = array('content_id' => (int)$content_id, 'content_video_num' => (int)$videoNum, 'action' => $action, 
                'position' => (int)$position, 'user_id' => (int)$user_id);
           if(!empty($embedName)){
               $data['embed_name'] = $embedName;
           }
           if(!empty($webcast_id)){
               $data['webcast_id'] = (int)$webcast_id;
           }

           try{
               $db->insert('players_stats', $data);
           }
           catch(Zend_Db_Exception $e){
               throw new Ibulletin_Stats_Exception('Nepodarilo se ulozit statisticka data prehravace, pravdepodobne'.
            ' zadano nespravne ID uzivatele nebo content_id. user_id: '.$user_id.' content_id: '.$content_id
               .' content_video_num: '.$videoNum
               .' webcast_id: '.$webcast_id.' action: '.$action.' position: '.$position
               .' embedName: '.$embedName.' Puvodni vyjimka:'."\n$e", 18);
           }

       }


       /**
        * Vrati id aktualne bezici invitation wave, nebo null, pokud zadna nebezi.
        *
        * @return int Cookie id pro tuto session
        */
       public function getInvitationWave()
       {
           $db = Zend_Registry::get('db');

           $q = 'SELECT id FROM invitation_waves WHERE start =
                    (SELECT max(start) FROM invitation_waves WHERE start < current_timestamp AND "end" > current_timestamp)';
           $id = $db->fetchOne($q);

           if(empty($id)){
               $id = null;
           }

           return $id;
       }

       /**
        * Ulozi data patrici do tabulky sessions. Pokud data jeste nebyla ulozena
        * vytvori novy zaznam a nastavi $this->session_id. Trida uklada jen atributy,
        * ktere byly zmeneny od posledniho ulozeni metodou setAttrib.
        *
        * Pokud neni nastaveno cookie_id, ulozeni se neprovede.
        *
        * @return bool Povedla se akce?
        */
       public function saveToSessions(){
           // vycistime zaznam k ulozeni od hodnot ktere jsou empty
           foreach($this->_toSave_sessions as $key => $val){
               if(!is_numeric($val) && empty($val)){
                   unset($this->_toSave_sessions[$key]);
               }
           }

           //pokud neni co ukladat, vyskocime
           if(empty($this->_toSave_sessions)){
               return true;
           }

           //Phc_ErrorLog::notice('dbg', print_r($this->_toSave_sessions, true));

           $db = Zend_Registry::get('db');
           if($this->session_id === null){
               //Cookie_id musi byt nastaveno
               if($this->_cookie_id === null){
                   return false;
               }
               # Vytvoreni noveho zaznamu a ulozeni $this->session_id
               try{
                   $affected = $db->insert('sessions', $this->_toSave_sessions);
                   $this->session_id = $db->lastInsertId('sessions', 'id');
               }
               catch(Exception $e){
                   Phc_ErrorLog::error('Ibulletin_Stats::saveToSessions()', 
                        "Cannot insert into sessions, data: ".
                        print_r($this->_toSave_sessions, true)."\nOriginal exception: $e");
               }
           }
           else{
               # Upravime existujici zaznam
               $id = $this->session_id;
               try{
                   $affected = $db->update('sessions', $this->_toSave_sessions, "id = $id");
               }
               catch(Exception $e){
                   Phc_ErrorLog::error('Ibulletin_Stats::saveToSessions()', 
                        "Cannot update sessions, id: $id, data: ".
                        print_r($this->_toSave_sessions, true)."\nOriginal exception: $e");
               }
           }

           if($affected >= 1){
               //vynulujeme veci urcene k ulozeni
               $this->_toSave_sessions = array();
               return true;
           }
           else{
               return false;
           }
       }

       /**
        * Ulozi data patrici do tabulky page_views. Pokud data jeste nebyla ulozena
        * vytvori novy zaznam a nastavi $this->page_view_id. Trida uklada jen atributy,
        * ktere byly zmeneny od posledniho ulozeni metodou setAttrib.
        *
        * Pokud neni nastaveno session_id, ulozeni se neprovede.
        *
        * @return bool Povedla se akce?
        */
       public function saveToPageViews(){
           // Nastavime aktualni posledni module, akci a controller
           // Pri autmatickem ukladani by se vzdy melo nastaveni prepsat az pri ulozeni kvuli moznym internim forwardum 
           // (napriklad u landing pages - IndexController a RegisterController)
           $this->setModuleControllerAction();
           
           //pokud neni co ukladat, vyskocime
           if(empty($this->_toSave_page_views)){
               return true;
           }

           $db = Zend_Registry::get('db');

           // Delku zobrazeni stranky nepocitame, pokud je aktualni pozadavek
           // z AJAXU - to aktualne overujeme podle controlleru - HACK
           // Provadime jen jednou jeste pred aktualnim zaznamem do page_views
           $req = Zend_Controller_Front::getInstance()->getRequest();
           if($req->getControllerName() != 'userbrwsrinfo' && !$this->page_view_id && !empty($this->_statsNamespace->last_pv_time)){
               // Pokusime se spocitat a nastavit cas od minuleho page view
               list($lastusec, $lastsec) = explode(" ", $this->_statsNamespace->last_pv_time);
               list($nowusec, $nowsec) = explode(" ", microtime());
               $pv_len = (double)$nowusec + (double)$nowsec - (double)$lastusec - (double)$lastsec;
               $q = "UPDATE page_views SET view_length = ($pv_len * INTERVAL '1 second')::time
                    WHERE id = ".(int)$this->_statsNamespace->last_pv_id." AND view_length IS NULL";
               /* // Pomale, kupodivu ale ne zase tolik pomale
               $q = sprintf('UPDATE page_views SET view_length =
                            (SELECT cast(current_timestamp - max(timestamp) AS time) FROM page_views
                             WHERE session_id=%d)
                          WHERE session_id=%d AND timestamp = (SELECT max(timestamp) FROM page_views
                             WHERE session_id=%d)',
               $this->session_id, $this->session_id, $this->session_id);
               //*/
               $db->fetchOne($q);

               // Pokusime se spocitat a nastavit delku session do minuleho page view
               $q = sprintf('UPDATE sessions SET session_length =
                              (SELECT cast(current_timestamp - min(timestamp) AS time) FROM page_views
                               WHERE session_id=%d)
                          WHERE id=%d',
               $this->session_id, $this->session_id);
               $db->fetchOne($q);
           }

           // vycistime zaznam k ulozeni od hodnot ktere jsou empty
           foreach($this->_toSave_page_views as $key => $val){
               if(!is_numeric($val) && empty($val)){
                   unset($this->_toSave_page_views[$key]);
               }
           }

           if($this->page_view_id === null){
               //session_id musi byt nastaveno
               if($this->session_id === null){
                   return false;
               }
               # Vytvoreni noveho zaznamu a ulozeni $this->page_view_id
               //pridame k ulozeni session_id
               $this->_toSave_page_views['session_id'] = $this->session_id;
               //ulozime
               try{
                   $affected = $db->insert('page_views', $this->_toSave_page_views);
                   $this->page_view_id = $db->lastInsertId('page_views', 'id');
               }
               catch(Exception $e){
                   Phc_ErrorLog::error('Ibulletin_Stats::saveToPageViews()', 
                        "Cannot insert into page_views, data: ".
                        print_r($this->_toSave_page_views, true)."\nOriginal exception: $e");
               }
           }
           else{
               # Upravime existujici zaznam
               $id = $this->page_view_id;
               try{
                   $affected = $db->update('page_views', $this->_toSave_page_views, "id = $id");
               }
               catch(Exception $e){
                   Phc_ErrorLog::error('Ibulletin_Stats::saveToPageViews()', 
                        "Cannot update page_views, id: $id, data: ".
                        print_r($this->_toSave_page_views, true)."\nOriginal exception: $e");
               }
           }

           // Ulozime do PHP session last pv_id a aktualni cas pro vypocet delky zobrazeni stranky
           // pouze vsak pokud mame nastavene page_id, category_id, nebo bulletin_id
           if(!empty($this->_toSave_page_views['page_id']) ||
                !empty($this->_toSave_page_views['category_id']) ||
                !empty($this->_toSave_page_views['bulletin_id']))
           {
               $this->_statsNamespace->last_pv_id = $this->page_view_id;
               $this->_statsNamespace->last_pv_time = microtime();
           }

           // Pro debug...
           //$e = new Exception;
           //Phc_ErrorLog::notice('dbg - pvId:'.$this->page_view_id, "\n\n\n".$e."\n\n".print_r($this->_toSave_page_views, true));


           if($affected >= 1){
               //vynulujeme veci urcene k ulozeni
               $this->_toSave_page_views = array();
               
               return true;
           }
           else{
               return false;
           }
       }


      /**
       * Ulozi data requestu do specielni tabulky request_data.
       * 
       * Jedna se predevsim o POST a GET data ve formatu JSON.
       * Pred volanim teto metody je nutne, aby bylo nastavene $this->page_view_id.
       * 
       * Vola se jen jednou za page_view, pokud je volana vicekrat, nic se neprovede.
       */
       public function saveRequestData()
       {
           $db = Zend_Registry::get('db');
           // Kontrola, jestli je volano poprve
           if(!empty($this->_requestDataSaved)){
               return;
           }
           
           // Ulozime POST a GET data
           if(!empty($_POST) || !empty($_GET)){
               try{
                   $post = empty($_POST) ? null : Zend_Json::encode($_POST);
                   $get = empty($_GET) ? null : Zend_Json::encode($_GET);
                   
                   $db->insert('request_data', array('page_view_id' => $this->page_view_id, 'post' => $post, 'get' => $get));
               }
               catch(Exception $e){
                   Phc_ErrorLog::notice('Ibulletin_Stats::__construct()', 
                        "Nepodarilo se zakodovat nebo ulozit POST nebo GET data requestu. \nPOST: ".print_r($_POST, true).
                        "\nGET: ".print_r($_GET, true).
                        "\nPuvodni vyjimka:\n$e");
               }
           }
           
           // Nastavime priznak ulozeni dat
           $this->_requestDataSaved = true;
       }


       /**
        * Zajisti nalezeni, nebo vytvoreni odpovidajiciho zaznamu v tabulce
        * flashplayers. Data do sessions pouze pripravi k ulozeni, primo je neuklada.
        *
        * @param string Verze flashplayeru
        */
       public function setFlashplayer($ver)
       {
           if(empty($ver)){
               return;
           }

           $db = Zend_Registry::get('db');
           $ver_quoted = $db->quote($ver);
           $q = "SELECT id FROM flashplayers WHERE version = $ver_quoted";
           $id = $db->fetchOne($q);
           if(!is_numeric($id)){
               //pridame novy zaznam do flashplayers
               $data = array('version' => $ver);
               $db->insert('flashplayers', $data);
               $id = $db->lastInsertId('flashplayers', 'id');
           }

           $this->setAttrib('flashplayer_id', $id);
       }
       
       /**
        * Vrati verzi flashe v aktualni session.
        * Verze je normalizovana na desetinne cislo (8.3, 10.1), v pripade
        * nedostupnosti flashe vraci null.
        *
        * @return double Verze flashplayeru
        */
       public function getFlashplayerVersion()
       {
            $db = Zend_Registry::get('db');
           
            $sel = new Zend_Db_Select($db);
            $sel->from(array('s' => 'sessions'), array())
                ->join(array('f' => 'flashplayers'), 'f.id = s.flashplayer_id', array('version'))
                ->where('s.id = ?', $this->session_id);
            $ver = $db->fetchOne($sel);
            
            
            // Jeste nebyla provedena detekce prohlizece pomoci JS, zkusime najit posledni prohlizec, ktery uzivatel pouzival
            // rozlisujeme mobilni a normalni zarizeni pro pripad, ze uzivatel pouziva iPad i PC
            if($ver === false){
                $ua_type = $this->detectMobileBrowser() ? 'mobile' : 'bro';
                
                $sel = new Zend_Db_Select($db);
                // Hledame session s nastavenym flash_id, ktera ma stejne cookie_id jako aktualni session
                $sel->from(array('s' => 'sessions'), array())
                    ->join(array('f' => 'flashplayers'), 'f.id = s.flashplayer_id', array('version'))
                    ->join(array('s1' => 'sessions'), 's1.cookie_id = s.cookie_id', array())
                    ->join(array('os' => 'os'), 's.os_id = os.id', array())
                    ->where('s1.id = ?', $this->session_id)
                    ->where('os.ua_type = ?', $ua_type)
                    ->order('s.timestamp DESC')
                    ->limit(1);
                $ver = $db->fetchOne($sel);
            }
            
            // Neni flash
            if($ver == 'false'){ // Text "false" je ulozen ve verzi flashe, pokud flash nebyl detekovan
                return null;
            }
            
            // Problematicky retezec verze, vracime jako verzi flashe 1.
            $m = array();
            if(preg_match('/NaN_(.*)/i', $ver, $m)){
                return (float)('1.'.$m[1]);
            }
            
            // Flash verze prevedena na float cislo
            $m = array();
            if(preg_match('/([0-9]+)_([0-9]*)/i', $ver, $m)){
                return (float)($m[1].'.'.$m[2]);
            }
            
            return null;
       }

       /**
        * Zajisti nalezeni, nebo vytvoreni odpovidajiciho zaznamu v tabulce
        * resolutions. Data do sessions pouze pripravi k ulozeni, primo je neuklada.
        *
        * @param string Rozliseni
        */
       public function setResolution($res)
       {
           if(empty($res)){
               return;
           }

           $db = Zend_Registry::get('db');
           $res_quoted = $db->quote($res);
           $q = "SELECT id FROM resolutions WHERE name = $res_quoted";
           $id = $db->fetchOne($q);
           if(!is_numeric($id)){
               //pridame novy zaznam do resolutions
               $data = array('name' => $res);
               $db->insert('resolutions', $data);
               $id = $db->lastInsertId('resolutions', 'id');
           }

           $this->setAttrib('resolution_id', $id);
       }

       /**
        * Rozparsuje a spravne ulozi Os pro tuto session
        */
       public function setOs($name, $ver, $ua_type)
       {
           $db = Zend_Registry::get('db');
           $name_quot = $db->quote($name);
           $ver_quot = $db->quote((string)$ver);
           $ua_type_quot =  $db->quote((string)$ua_type);
           $q = "SELECT id FROM os WHERE name = $name_quot AND version = $ver_quot AND ua_type = $ua_type_quot";
           $id = $db->fetchOne($q);
           if(!is_numeric($id)){
               //pridame novy zaznam do os
               $data = array('name' => $name, 'version' => (string)$ver, 'ua_type' => (string)$ua_type);
               $db->insert('os', $data);
               $id = $db->lastInsertId('os', 'id');
           }

           $this->setAttrib('os_id', $id);
       }

       /**
        * Rozparsuje a spravne ulozi Browser pro tuto session
        */
       public function setBrowser($name, $ver, $full_name)
       {
           $db = Zend_Registry::get('db');
           $name_quot = $db->quote($name);
           $ver_quot = $db->quote((string)$ver);
           $full_name_quot = $db->quote((string)$full_name);
           $q = "SELECT id FROM browsers WHERE name = $name_quot AND version=$ver_quot AND full_name=$full_name_quot";
           $id = $db->fetchOne($q);
           if(!is_numeric($id)){
               //pridame novy zaznam do browsers
               $data = array('name' => $name, 'version' => (string)$ver, 'full_name' => (string)$full_name);
               $db->insert('browsers', $data);
               $id = $db->lastInsertId('browsers', 'id');
           }

           $this->setAttrib('browser_id', $id);
       }
       
       
       /**
        * Ulozi user agent uzivatele do tabulky user_agents a vrati ID z teto tabulky.
        * Pokusi se najit shodny UA a pripadne vytvori novy.
        * 
        * @return   int ID user_agent pro tuto session 
        */
       public function setUserAgent() {
        $db = Zend_Registry::get('db');

        $ua = trim($_SERVER['HTTP_USER_AGENT']);

        $sel = $db->select()->from('user_agents', array('id'))->where('user_agent = ?', $ua);
        $ua_id = $db->fetchOne($sel);

        try {
            // Neexistuje, musime zalozit novy
            if (!$ua_id) {
                $db->insert('user_agents', array('user_agent' => (string) $ua));
                $ua_id = $db->lastInsertId('user_agents', 'id');
            }
        } catch (Zend_Db_Exception $e) {
            //Logujeme pouze chyby netykajici se duplicit, ta obcas nastavala, ale je nevyznamna 
            if ($e->getCode() != 23505) {
                Phc_ErrorLog::error('user_agents', $e->getMessage());
            }
        }

        return $ua_id;
    }

       
    /**
        * Sets the Zend's module, controller and action_name 
        * to page_views according to front controller.
        * 
        * New values are set to be saved only if they have chaged from last set.
        */
        public function setModuleControllerAction()
        {
            $request = Zend_Controller_Front::getInstance()->getRequest();

            $action = $request->getActionName();
            $controller = $request->getControllerName();
            $module = $request->getModuleName();
            
            // Pridame k zapsani do DB a ulozime zmenene hodnoty do objektu
            if(!empty($module) && $module != $this->_lastModuleControllerAction['module']){
                $this->setAttrib('module', $module);
                $this->_lastModuleControllerAction['module'] = $module;
            }
            if($controller != $this->_lastModuleControllerAction['controller']){
                $this->setAttrib('controller', $controller);
                $this->_lastModuleControllerAction['controller'] = $controller;
            }
            if($action != $this->_lastModuleControllerAction['action']){
                $this->setAttrib('action_name', $action);
                $this->_lastModuleControllerAction['action'] = $action;
            }
        }
       

       /**
        * Nastavi atribut a prida jej k zapisu pri ulozeni session.
        *
        * V pripade stejnych nazvu atributu v page_views a sessions pouzivame
        * pro sessions prefix sessions. (pro timestamp v sessions - sessions.timestamp
        * a pro timestamp v page_views - timestamp).
        *
        * @param string Jmeno atributu
        * @param string Hodnota atributu
        * @return bool Povedla se akce?
        */
       public function setAttrib($name, $value)
       {
           $attrName = '_'.$name;

           $this->$attrName = $value;

           // Pripravime k ulozeni do jedne z tabulek pokud to do nejake patri
           if(in_array($name, $this->_sessions_attribs)){
               // Osetrime pripady koliznich jmen atributu
               $parts = explode('.', $name);
               if(isset($parts[1])){
                   $name = $parts[1];
               }
               $this->_toSave_sessions[$name] = $value;
           }
           elseif(in_array($name, $this->_page_views_attribs)){
               $this->_toSave_page_views[$name] = $value;
           }
           else{
               $e = new Ibulletin_Stats_Exception("Pokus o logovani neznameho atributu '$name'.");
               // Dany parametr nezname, logujeme chybu
               Phc_ErrorLog::notice('Ibulletin_stats::setAttrib', $e);
           }

           return true;
       }

       /**
        * Vrati atribut podle jmena.
        *
        * @param string Jmeno atributu
        * @return mixed Hodnota atributu
        */
       public function setBrowserDetectionDone()
       {
           $this->_statsNamespace->browserDetectionDone = $this->_statsNamespace->session_id;
       }
       
       /**
        * Detekuje, jestli uzivatel v teto session pouziva mobilni prohlizec podle
        * HTTP_USER_AGENT
        * 
        * @return bool  Jedna se o mobilni prohlizec?
        */
       public function detectMobileBrowser()
       {
            // Pole regex vzoru pro detekci mobilniho prohlizece
            // Vybrano podle http://www.zytrax.com/tech/web/mobile_ids.html
            $regexps = array(
                'AvantGo', 'DoCoMo', 'KDDI-KC31', 'UP\.Browser', 'SEMC-Browser', 'PalmOS', 'Windows CE',
                'NetFront', 'Android', 'iPhone', 'iPad', 'Opera Mobi', 'RIM Tablet OS', 'BlackBerry', 
                'Opera Mini', 'Cricket', 'Windows Phone', 'IEMobile', 'hp-tablet', 'wOSBrowser',
                'HTC_', 'MSIEMobile', 'Kindle', 'MIDP', 'Browser\/Teleca', 'Obigo', 'POLARIS',
                'Teleca', 'Brew', 'SymbianOS', 'Symbian OS', 'UP\.Link', 'PalmSource', 'PalmOS',
                'UC Browser', 'SAMSUNG', 'TelecaBrowser', 'Sony Tablet', 'PlayStation Portable',
                'ZuneWP7'
            );
            
            // Zkousime detekovat mobilni prohlizec podle znamych regexpu
            foreach($regexps as $re){
                if(preg_match('/'.$re.'/i', $_SERVER['HTTP_USER_AGENT'])){
                    return true;
                }
            }
            
            return false;
       }

       /**
        * Vrati atribut podle jmena.
        *
        * @param string Jmeno atributu
        * @return mixed Hodnota atributu
        */
       public function getAttrib($name)
       {
           $attrName = '_'.$name;
           return $this->$attrName;
       }

       /**
        * Zapise data do sessions_by_reps, ktera reprezentuji sessions, ktere nevytvoril uzivatel sam,
        * ale byly vytvoreny prostrednictvim reprezentanta, napriklad prostrednictvim iPadu.
        *
        * @param int $repreId           ID reprezentanta z users
        * @param int $reprePageViewId   ID z page_views zaznamu, ktery vytvoril session zapsanou
        *                               prostrednictvim reprezentanta.
        * @param string $dataChecksum	Checksum dat (md5), z kterych tento zaznam vyplyva (pokud se jedna o data prijata napriklad z aplikace inRep)
        * @throws   Ibulletin_Stats_No_User_Set_Exception   Pokud neni zadan zadny uzivatel a neni ani nastaven v objektu.
        * @throws   Ibulletin_Stats_Exception               Code 20 - Pokud neni zadan nektery z potrebnych udaju
        *                                                   Code 21 - Pokud se nepodarilo zapsat data do DB
        */
       public function setSessionMadeByRepre($repreId, $reprePageViewId, $dataChecksum = null)
       {
            $db = Zend_Registry::get('db');

            // Pokud jeste neni uvedeno session_id, ulozime do sessions
            if(empty($this->session_id)){
                $this->saveToSessions();
            }

            if(empty($repreId) || empty($reprePageViewId)){
                throw new Ibulletin_Stats_Exception('Nebylo zadano $repreId nebo $reprePageViewId. '.
                    "repreId: '$repreId', reprePageViewId: '$reprePageViewId'.", 20);
            }

            $data = array('repre_id' => $repreId, 'repre_page_view' => $reprePageViewId, 'session_id' => $this->session_id);
            
            // Checksum
            if($dataChecksum){
            	if(strlen($dataChecksum) <= 32){ // velikost pole v DB - pro MD5
            		$data['data_checksum'] = $dataChecksum;
            	}
            	else{
            		Phc_ErrorLog::warning('Ibulletin_Stats::setSessionMadeByRepre()', "Cannot save data checksum into sessions_by_reps. ".
            			"Checksum is longer than 32 characters. Checksum: '".$dataChecksum."'.");
            	}
            }

            try{
                // Ulozime zadana data
                $db->insert('sessions_by_reps', $data);
            }
            catch(Exception $e){
                // Pokud jde o unikatnost klice, nic nedelame, zaznam jiz existuje
                if(!stripos($e, 'unique violation') !== false){
                    throw new Ibulletin_Stats_Exception('Nepodarilo se ulozit data do sessions_by_reps. '.
                        'Zadana data: '.print_r($data, true)."\nPuvodni vyjimka:\n$e", 21);
                }
            }

       }
       
       
       /**
        * Zkontroluje, zda zadany checksum dat nebyl jiz zpracovan.
        * 
        * Pouzivame pro kontrolu duplicitne odeslanych dat ze zarizeni. Spoleha na to, 
        * ze data jsou dostatecne velka tak, aby se nemohly vyskytovat shodne checksumy pro ruzna validni data. 
        * 
        * @param	string	Checksum ke kontrole
        * @return	bool	Byla data s danym checksumem jiz ulozena?
        */
       public function checksumAlredyProcessed($checksum)
       {
       		$db = Zend_Registry::get('db');
       		
       		$sel = new Zend_Db_Select($db);
       		$sel->from('sessions_by_reps', array(new Zend_Db_Expr('1')))
       			->where('data_checksum = ?', $checksum)
       			->limit(1);
       		
       		return (bool)$db->fetchOne($sel);
       }
       
       
       /**
        * Vrati ID z page_views, pokud jeste neni k dispozici, 
        * provede nejprve ulozeni do DB.
        *
        * @return   string  ID z tabulky page_views.
        */
       public function getPageViewsId()
       {
           // Pokud neni jeste ID znamo, musime provest zapis do DB
           if(empty($this->page_view_id)){
               $this->saveToSessions();
               $this->saveToPageViews();
           }
           
           return $this->page_view_id;
       }


       /**
        * Vrati cas ulozeny pro aktualni zaznam v tabulce page_views.
        *
        * @return   string  Timestamp ve formatu ISO primo z DB
        */
       public function getPageViewsTimestamp()
       {
           $db = Zend_Registry::get('db');

           if(empty($this->page_view_id)){
               $this->saveToSessions();
               $this->saveToPageViews();
           }

           $sel = new Zend_Db_Select($db);
           $sel->from('page_views', array('timestamp'))
               ->where('id = ?', $this->page_view_id);
           $timestamp = $db->fetchOne($sel);

           return $timestamp;
       }

       /**
        * Odebere session namespace teto instance.
        * NEMELO by se volat na hlavni tride pouzivane pri zobrazeni webu,
        * v dusledku volani teto metody nebude dale pokracovat uzivatelova session!
        *
        * MELA by byt pouzita po ukonceni prace s objektem pouzitym pro zapis informaci do
        * sessions a page_views.
        */
       public function removeSessionNamespace()
       {
           $this->_statsNamespace->unsetAll();
       }
        
       
       /**
        * Zmeni page_views.dispayed_version v minulem page_view aktualni stranky.
        * Pouzivame pro zmenu displayed version u indetailu v pripade, ze bylo redirectovano z HTML verze na FLASH
        * po detekci flashe v prohlizeci.
        * 
        * @param string     Nova hodnota displayed version.
        * @param string     Minula zobrazena verze, ktera se ma prepsat (napr. html, flash,...)
        * @param int        ID page, ktera byla zobrazovana
        */
       public function changeLastDisplayedVersion($displayedVersion, $lastDisplayedVersion, $pageId)
       {
            $db = Zend_Registry::get('db');
           
            // Select, ktery najde minuly page_view v teto session s touto strankou
            $sel = new Zend_Db_Select($db);
            $sel->from('page_views', array('id'))
                ->where('session_id = ?', $this->session_id)
                ->where('page_id = ?', $pageId)
                ->where('displayed_version = ?', $lastDisplayedVersion)
                ->order(array('timestamp DESC'))
                ->limit(1);
            
            // Updateujeme zaznam podle vytvoreneho selectu.    
            $db->update('page_views', array('displayed_version' => $displayedVersion), 'id = ('.$sel.')');
       }
       
       
       /**
        * Pripravi do tohoto objektu session novou session. Pouziva se po tvrdem odhlaseni, 
        * pokud chceme rovnou ve stejnem volani prihlasit noveho uzivatele.
        */
        public function newSession()
        {
            $this->saveToSessions();
            $this->saveToPageViews();
            self::$_inst = new Ibulletin_Stats();
        }
        
        
        /**
         * Ulozi monitorovaci data resources.
         * 
         * @param  int      Id resource
         * @param  string   Casova znamka zacatku zobrazeni resource v ISO 8601
         * @param  int      Delka zobrazeni resource v ms
         * @throws Ibulletin_Stats_Exception    Code: 21 - Cannot save data into resources_stats table. 
         */
        public function saveResourceStats($resourceId, $timestamp = null, $timeTotal = null){
            $db = Zend_Registry::get('db');
            
            if(empty($timestamp)){
                $timestamp = new Zend_Db_Expr('current_timestamp');
            }
            else{
                $isoTimestValid = new Ibulletin_Validators_TimestampIsoFilter;
                if(!$isoTimestValid->isValid($timestamp)){
                    Phc_ErrorLog::warning('Ibulletin_Stats::saveResourceStats()', 'Pri ukladani resource byla zadana '.
                        'nevalidni casova znamka: "'.$timestamp.'".'." ResourceId: $resourceId, timeTotal: $timeTotal.".
                        'Do DB byla ulozena aktualni casova znamka.');
                    
                    $timestamp = new Zend_Db_Expr('current_timestamp');
                }
            }
            
            if(!$this->session_id){
                $this->saveToSessions();
            }
            
            $data = array(
                'resource_id' => (int)$resourceId, 
                'session_id' => $this->session_id,
                'timestamp' => $timestamp,
                'time' => ($timeTotal !== null ? (int)$timeTotal : null),
                );
            try{
                //echo 'saving'." resourceId: $resourceId\n";
                $db->insert('resources_stats', $data);
            }
            catch(Exception $e){
                throw new Ibulletin_Stats_Exception('Cannot save data into resources_stats table. Data:'.print_r($data, true).
                    "\n Original exception: $e", 21);
            }
            
        }
        
        
       /**
        * Destruktor provede ulozeni vsech novych neulozenych dat
        * do tabulek sessions a page_views.
        */
       public function __destruct()
       {
           // Ulozime vse, pokud neni zakazano
           if(!$this->getNoSaving()){
               $this->saveToSessions();
               $this->saveToPageViews();
           }

           // Objekt skutecne odstranime jen pokud jsme v defaultnim namespace, kde je povoleno
           // pouzivat objekt castecne jako singleton
           if(self::$_inst == $this){
               self::$_inst = null;
           }
       }

       /**
        * Nastavi flag ukladat ci neukladat statistiky.
        * Pouziva se pouze pro nektere controllery, kde je ukladani statistik nezadouci, napriklad
        * generovani souboru s JS funkcemi.
        *
        * @param bool $save   Ukladat statisticka data?
        */
       public function setNoSaving($save)
       {
           $this->_noSaving = $save;
       }

       /**
        * Vrati flag ukladat ci neukladat statistiky.
        */
       public function getNoSaving()
       {
           return $this->_noSaving;
       }
}
