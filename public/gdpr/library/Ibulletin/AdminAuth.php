<?php

/**
 * iBulletin - AdminAuth.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

/**
 * Pri nenalezeni uzivatele.
 */
class Ibulletin_AdminAuth_User_Not_Found_Exception extends Exception {}

/**
 * Pri nenalezeni uzivatele z tabulky users.
 */
class Ibulletin_AdminAuth_Web_User_Not_Found_Exception extends Exception {}

/**
 * Pri mazani sama sebe.
 */
class Ibulletin_AdminAuth_User_Self_Deleting_Exception extends Exception {}

/**
 * Duplikatni login.
 */
class Ibulletin_AdminAuth_User_Duplicate_Login_Exception extends Exception {}


/**
 * Trida poskytujici funkcionality tykajici se autentizace.
 * Tato trida obvykle existuje pouze v jedne instanci, k jejimu ziskani
 * se pouziva saticka metoda getInstance().
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_AdminAuth
{
    /**
     * Instance tridy.
     *
     * @var Ibulletin_AdminAuth
     */
    private static $_adminAuth = null;


    /**
     * Instance Zend_Auth pro snazsi praci.
     *
     * @var Zend_Auth
     */
    public $auth = null;


    /**
     * Auth adapter pro overeni proti tabulce s loginem a heslem.
     *
     * @var Zend_Auth_Adapter_DbTable
     */
    public $authAdapter = null;


    /**
     * Data prihlaseneho uzivatele z DB
     *
     * @var stdClass
     */
    public $user_data = null;


    /**
     * Pole prav prihlaseneho uzivatele
     *
     * @var array
     */
    public $user_privileges = null;
    
    
    /**
     * Délka auth cookie
     * @var int 
     */
    private static $cookie_auth_length = 13;
    
    /**
     * Pozice auth cookie serie v cookie auth hash
     * @var int 
     */
    private static $cookie_auth_serie_start = 3;
    
    /**
     * Nazev specialniho prava, ktere vkladame do db pro superuser, ktery se prihlasuje googlem, nemuze byt superuser v instalaci, ale ma toto povereni z portalu
     * @var string
     */
    public static $cookie_auth_superuser_privilege = 'privilege_all_cookie_auth';


    /**
     * Vrati existujici instanci Ibulletin_AdminAuth nebo vytvori novou a tu vrati.
     *
     * @return Ibulletin_AdminAuth Jedina instance Ibulletin_AdminAuth
     */
    public static function getInstance()
    {
        if(self::$_adminAuth === null){
            self::$_adminAuth = new Ibulletin_AdminAuth();
        }

        return self::$_adminAuth;
    }


    /**
     * Provede kontrolu pred pustenim uzivatele kamkoli do adminu.
     * Pokud je zivatel prihlasen, nic neprovede, pokud zivatel zadal
     * prihlasovaci informace, zkontroluje je a pripadne pusti uzivatele dal
     * a pokud uzivatel neni prihlasen, presmeruje jej na login page.
     *
     * Pokud je uzivatel autentizovan, nacte jeho data do promennych objektu.
     */
    public static function authenticate()
    {
        $inst = self::getInstance();

        Zend_Loader::loadClass('Zend_Auth_Adapter_DbTable');
        Zend_Loader::loadClass('Zend_Auth');

        if($inst->auth === null){
            $inst->auth = Zend_Auth::getInstance();
        }
        
        // Pokusime se nacist data z perzistentniho odkladiste autentizace
        $inst->_loadFromStorage();

        // Kontrola prihlaseni spravneho uzivatele frontendu
        $inst->checkFrontUser();

        if(!$inst->auth->hasIdentity()){
            //musime overit, jestli jiz nejsme na login page
            $frontController = Zend_Controller_Front::getInstance();
            $request =  $frontController->getRequest();
            $prevUrl = $request->getRequestUri();
            // musime nastavit - místo /
            $prevUrl = str_replace("/","-",$prevUrl);
            
            // Kontrola prihlaseni spravneho uzivatele frontendu
            $inst->checkFrontUser();

            if($request->getControllerName() != 'login'){
                //ziskame redirector
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                //presmerujeme na login page
                $redirector->gotoAndExit('index', 'login', 'admin',array("prevurl" => $prevUrl,));

            }
        }
        
        //zkontrolujeme cookie time, jestlize vyprsel short limit vygenerujeme novy authcookie krom serie
        if (isset($inst->user_data->auth_cookie_created)) {
            $ac_expire = strtotime($inst->user_data->auth_cookie_created) + self::shortLimitAuthCokie();
            if (time()>$ac_expire) {
                self::setAuthCookie($inst->user_data->login, false);
            }
           
        }
       
        return;
    }


    /**
     * Provede/neprovede zalogovani uzivatele a vrati Auth_Result objekt, logovani pomoci prihlasovacich udaju nebo pomocí google auth,
     * @param string Login
     * @param string Heslo
     * @param bool fillpass doplni hash hesla
     * @param array force_privilege vnuti prava nez ohledu na db 
     * @param bool keepsign ulozi auth cookie pro keepsign login 
     * @return Auth_Result obsahujici vysledek pokusu o autentizaci
     */
    public static function login($login, $password, $fillpass = false, $force_privilege = null,$keepsign = false)
    {
        $inst = self::getInstance();
        $db = Zend_Registry::get('db');

    //pri autentizaci mimo inbox (např. google auth) k uzivateli doplnime hash hesla a prihlasime
    if ($fillpass) {
            $selpass = $db->select()->from('admin_users', array('password'))->where('login = ?', $login)->where('deleted IS NULL');
            $password = $db->fetchOne($selpass);
            
            // Inicializace auth adapteru 
            $inst->authAdapter = new Zend_Auth_Adapter_DbTable(
                    $db, 'admin_users', 'login', 'password', 'deleted IS NULL'
            );
            
        } else {
            
            // Inicializace auth adapteru
            $inst->authAdapter = new Zend_Auth_Adapter_DbTable(
                    $db, 'admin_users', 'login', 'password', 'MD5(?) AND deleted IS NULL'
            );
           
        }
        
        //nastavime jmeno a heslo
        $inst->authAdapter->setIdentity($login)
                ->setCredential($password);

        $result = $inst->auth->authenticate($inst->authAdapter);

        // Pokud se povedla autentizace, nacteme data uzivatele do objektu
        if ($result->isValid()) {
            
            //Nacteme uzivatelova data
            $user_data = $inst->authAdapter->getResultRowObject();

            //ulozime cookie login 
            if ($keepsign) {
                self::setAuthCookie($login);

                //funkcionalita pro auth cookie, ve chvili kdy doplnuje superuser prava, ulozime do db special pravo, ktere kontrolujeme pri login s cookie
                if ($force_privilege) {
                    if (in_array('privilege_all', $force_privilege)) {
                        self::manipulatePrivileges(self::$cookie_auth_superuser_privilege, 'add', $user_data->id);
                    }
                }
            }

            //user data rozbijeme pole prav
            $user_data->privileges = explode(',', $user_data->privileges);

            if ($force_privilege) {
                $user_data->privileges = array_merge($user_data->privileges, $force_privilege);
            }
            
            // Ulozime data autentizovaneho uzivatele do persistent storage a do objektu
            $inst->auth->getStorage()->write($user_data);

            // Nacteme data do objektu
            $inst->_loadFromStorage();
        }

        return $result;

        /*
          Zend_Loader::loadClass('Zend_Auth');
          $auth = Zend_Auth::getInstance();
          echo 'xxx'.$auth->getIdentity().'xxx<br/>';
         */
    }

    /**
     * Zkontroluje a pripadne prehlasi uzivatele frontendu tak, aby odpovidal
     * prihlasenemu uzivateli v adminu. 
     * 
     * Hodi se pro pripady, kdy uzivatel klikne treba v mailu a session mu na frontu prihlasi nekoho jineho.
     * 
     * Mela by byt spustena az po $inst->_loadFromStorage(), protoze zavisi na datech uzivatele adminu.
     */
    public static function checkFrontUser()
    {
        $inst = self::getInstance();
        $session = Zend_Registry::get('session');
        
        if(!empty($inst->user_data->user_id) && !empty($session->actual_user_id) && $inst->user_data->user_id != $session->actual_user_id){
            Ibulletin_Auth::doHardLogoff(true); // Odhlasime uzivatele a spustime novou session
            Ibulletin_Auth::setUser($inst->user_data->user_id); // Prihlasime uzivatele
        }
    }


    /**
     * Overi dane pravo u prihlaseneho uzivatele.
     *
     * @param string    Pravo k overeni
     * @return bool     Ma uzivatel pridelene dotazovane pravo?
     */
    public static function hasPermission($perm)
    {
        $inst = self::getInstance();

        // pokud neni uzivatel prihlasen, vracime vzdy false
        if(empty($inst->user_privileges)){
            return false;
        }

        if(in_array($perm, $inst->user_privileges) || in_array('privilege_all', $inst->user_privileges)){
            return true;
        }

        return false;
    }


    /**
     * Prida/ubere uzivateli pravo, pokud neni zadano jmeno uzivatele,
     * pracuje s aktualnim uzivatelem. Stara se o to, aby si uzivatel nemohl
     * odebrat sam pravo na pridelovani prav - "module_adminusers".
     *
     * @param string/array     Pravo k manipulaci.
     * @param string     Akce - add/remove/change (change nahradi vsechna existujici prava pravy/
                         pravem ktere je zadano)
     * @param string     Nepovinne - ID uzivatele, v pripade nezadani se pracuje s aktualnim.
     * @return boolean  povedla se akce?
     *
     * @throws Ibulletin_AdminAuth_User_Not_Found_Exception - pokud zadany uzivatel neexistuje.
     */
    public static function manipulatePrivileges($privilege, $action = 'add', $id = null)
    {
        $inst = self::getInstance();
        $db = Zend_Registry::get('db');

        // prava ktere chceme nastavit uzivateli
        $privilege = is_array($privilege) ? $privilege : array($privilege);

        $is_actual_user = false;

        if(($id === null && $inst->user_data->id !== null) || $id == $inst->user_data->id){
            $is_actual_user = true;
            $id = $inst->user_data->id;
        }
        elseif($id === null){
            throw new Ibulletin_AdminAuth_User_Not_Found_Exception('Uzivatel neni prihlasen, proto neni mozne pracovat s pravy bez zadani jmena uzivatele.');
        }
        
        // Nacteme aktualni prava uzivatele (pokud se jedna o aktualniho uzivatele, vytahneme z auth storage)
        if(!$is_actual_user){
            $sel = $db->select()
                ->from('admin_users', 'privileges')
                ->where("id = ?", $id);
            $res = $db->fetchAll($sel);
            if(!is_array($res)){
                throw new Ibulletin_AdminAuth_User_Not_Found_Exception('Uzivatel s id "'.$id.'" nebyl nalezen.');
            }
            $privileges = $res[0]['privileges'];

            // Rozdelime do pole
            $privileges = $privileges ? explode(',', $privileges) : array();

        }
        else{
            $privileges = $inst->user_privileges;
        }


        // Kontrola, jestli si uzivatel neodstranuje pravo na spravu uzivatelu, nebo na modul pro upravu prav
        if($is_actual_user) {
            switch ($action) {
                case 'remove':
                    if(in_array('module_adminusers', $privilege)){
                        $key = array_search('module_adminusers', $privilege);
                        unset($privilege[$key]);
                    }
                    if(in_array('privilege_all', $privilege)){
                        $key = array_search('privilege_all', $privilege);
                        unset($privilege[$key]);
                    }
                    break;
                case 'change':
                    // pokud je nastaveno pravo a nema byt
                    if(in_array('module_adminusers', $privilege) && !in_array('module_adminusers', $privileges)){
                        $key = array_search('module_adminusers', $privilege);
                        unset($privilege[$key]);
                    }
                    // pokud chybi pravo a ma byt nastaveno
                    if(!in_array('module_adminusers', $privilege) && in_array('module_adminusers', $privileges)){
                        $privilege[] = 'module_adminusers';
                    }

                    if(in_array('privilege_all', $privilege) && !in_array('privilege_all', $privileges)){
                        $key = array_search('privilege_all', $privilege);
                        unset($privilege[$key]);
                    }

                    if(!in_array('privilege_all', $privilege) && in_array('privilege_all', $privileges)){
                        $privilege[] = 'privilege_all';
                    }
                    break;
                case 'add':
                    break;
                default: return false;
            }
        }


        // Provedeme operaci s pravem/pravy
        if ($action == 'add') {
            $privileges = array_unique(array_merge($privileges, $privilege));

        } elseif ($action == 'remove') {
            $privileges = array_diff($privileges, $privilege);

        } elseif($action == 'change'){
            $privileges = $privilege;
        }

        // Ulozime data autentizovaneho uzivatele do persistent storage a do objektu
        if($id == $inst->user_data->id){
            $inst->user_data->privileges = $privileges;
            $inst->user_privileges = $privileges;
            $inst->auth->getStorage()->write($inst->user_data);
        }

        // nastavime sloupec s pravami pro zapis do DB
        $privileges = join(',', $privileges);
        
        $aff = $db->update('admin_users', array('privileges' => $privileges), sprintf("id = %d", $id));

        return (boolean)$aff;
    }

	/**
	 * Overi existenci uzivatele.
	 * 
	 * @param string	login uzivatele 
	 * @return int|FALSE	vraci id uzivatele nebo FALSE v pripade ze nebyl uzivatel nalezen
	 */
    public static function userExists($login) {
    	
    	$db = Zend_Registry::get('db');
    	$inst = self::getInstance();
    	
    	$q = $db->select()
    		->from('admin_users', 'id')
    	   	->where("login = ?", $login)
    		->limit(1);
    	$id = $db->fetchOne($q); 
		return $id;   	   	
    	
    }
    
    /**
     * Prida noveho uzivatele.
     *
     * @param string    Login uzivatele
     * @param string    Jmeno uzivatele
     * @param string    Heslo uzivatele
     * @return int|bool		id vlozeneho zaznamu nebo false
     * @throws Ibulletin_AdminAuth_User_Duplicate_Login_Exception
     */
    public static function addUser($login, $name = null, $pass = null)
    {
        $db = Zend_Registry::get('db');
        $inst = self::getInstance();

        // Kontrola neexistence daneho loginu        
        if($inst->userExists($login)){
            throw new Ibulletin_AdminAuth_User_Duplicate_Login_Exception(
                'Uzivatel s login "'.$login.'" jiz existuje.');            
        }

        /*
        // Uzivatele v users implicitne nevytvarime

        // Vytvorime noveho uzivatele v USERS
        $db->insert('users', array('test' => true));
        $user_id = $db->lastInsertId('users', 'id');

        // Pokud je zadano jmeno zapiseme do users jmeno
        if($name !== null){
            // Nachystame jmeno a prijmeni do poli v tabulce users
            $nameA = explode(' ', trim($name));
            $count = count($name);
            if($count == 3){
                $name = $nameA[1];
                $surname = $nameA[2];
            }
            elseif($count == 2){
                $name = $nameA[0];
                $surname = $nameA[1];
            }
            elseif($count == 1){
                $name = '';
                $surname = $nameA[0];
            }
            else{
                $name = '';
                $surname = '';
            }
            // Zapiseme jmeno a prijmeni do users
            $db->update('users', array('name' => $name, 'surname' => $surname), "id=$user_id");
        }
        if($email !== null){
            // Zapiseme email do users
            $email = strtolower($email);
            if(empty($email)){
                $email = null;
            }
            $db->update('users', array('email' => $email), "id=$user_id");
        }
        */
        $user_id = null;

        $data = array('login' => $login,
                      'name' => $name,
                      'password' => new Zend_Db_Expr("MD5('$pass')"),
                      'user_id' => $user_id
                      );

        $res = $db->insert('admin_users', $data);

        if ($res) {
            return $db->lastInsertId('admin_users','id');
        }
        return false;

        // Ulozime data autentizovaneho uzivatele do persistent storage a do objektu
        /* 
        if(!empty($id) && !empty($inst->user_data->id) && $id == $inst->user_data->id){
            $inst->auth->getStorage()->write($inst->user_data);
        }*/
    }


    /**
     * Edituje uzivatele.
     *
     * @param string    Id uzivatele
     * @param string    Novy login uzivatele - pokud je null, nebude zmenen
     * @param string    Jmeno uzivatele - pokud je null, nebude zmenen
     * @param string    Heslo uzivatele - pokud je null, nebude zmenen
     * @param string    Email uzivatele pro pridani do sprazeneho zaznamu tabulky users
     * @param string    Preferovany jazyk uzivatele z adresare texts.
     *                  Pokud je null - ignorujeme, pokud je '' zapiseme do DB null.
     * @param string    Casova zona k pouziti s danym uzivatelem adminu. Pro nezadanou
     *                  casovou zonu nastavi $config->general->default_timezone
     */
    public static function editUser($id, $login, $name, $pass, $email = null, $language = null, $timezone = null)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $inst = self::getInstance();

        $data = array();
        $login_exists_id = $inst->userExists($login);
        // Kontrola neexistence daneho loginu        
        if($login_exists_id && $login_exists_id != $id){
            throw new Ibulletin_AdminAuth_User_Duplicate_Login_Exception('Uzivatel se zadanym loginem (id "'.$id.'") jiz existuje.');
        }

        // Pokusime se najit uzivatele z users a napojit na zaznam z admin_users,
        // pokud neexistuje, vyhodime vyjimku
        $sel = $db->select()
            ->from('admin_users')
            ->where("id = :id", $id);
        $admin_users_row = $db->fetchAll($sel, array('id' => $id));
        // Pokud je zadan email, zkontrolujeme, jestli neni jiny uzivatel webu s timto mailem
        if(!empty($email)){
            $sel = $db->select()
                ->from('users')
                ->where("email = :email");
            $users_by_email_row = $db->fetchAll($sel, array('email' => strtolower($email)));
            if(!empty($users_by_email_row)){
                // Nastavime ID na to uzivatele nalezeneho podle mailu
                $user_id = $users_by_email_row[0]['id'];
            }
        }

        if(empty($user_id) && !empty($email)){
            //throw new Ibulletin_AdminAuth_Web_User_Not_Found_Exception('Nebyl nalezen existujici uzivatel webu podle mailu.');
           
           // Vytvorime noveho uzivatele
           $db->insert('users', array('test' => true,'email'=>$email));
           $user_id = $db->lastInsertId('users', 'id');
           
        }
        elseif(empty($user_id)){
            $user_id = $admin_users_row[0]['user_id'];
        }
        
        $data['user_id'] = $user_id;

        if($login !== null){
            $data['login'] = $login;
            if($id == $inst->user_data->id){
                $inst->user_data->login = $login;
            }
        }
        if($name !== null){
            $data['name'] = $name;

            // Nachystame jmeno a prijmeni do poli v tabulce users
            $nameA = explode(' ', trim($name));
            $count = count($nameA);
            if($count == 3){
                $name = $nameA[1];
                $surname = $nameA[2];
            }
            elseif($count == 2){
                $name = $nameA[0];
                $surname = $nameA[1];
            }
            elseif($count == 1){
                $name = '';
                $surname = $nameA[0];
            }
            else{
                $name = '';
                $surname = '';
            }
            // Zapiseme jmeno a prijmeni do users
            if(!empty($user_id)){
                //$db->update('users', array('name' => $name, 'surname' => $surname), "id=$user_id");
                Users::updateUser($user_id, array('name' => $name, 'surname' => $surname), false);
            }

            if($id == $inst->user_data->id){
                $inst->user_data->name = $name;
            }
        }
        if($pass !== null){
            $data['password'] = new Zend_Db_Expr("MD5('$pass')");
            if($id == $inst->user_data->id){
                $inst->user_data->password = md5($pass);
            }
        }
        /*
        if($email !== null){
            // Zapiseme email do users
            $email = strtolower($email);
            if(empty($email)){
                $email = null;
            }
            $db->update('users', array('email' => $email), "id=$user_id");
        }
        */

        if($language !== null){
            if($language == ''){
                $data['language'] = null;
            }
            else{
                $data['language'] = $language;
            }
        }

        if($timezone == '' || $timezone === null){
            $data['timezone'] = $config->general->default_timezone;
        }
        else{
            $data['timezone'] = $timezone;
        }

        $db->update('admin_users', $data, sprintf("id = %d", $id));

        // Ulozime data autentizovaneho uzivatele do persistent storage a do objektu
        if($id == $inst->user_data->id){
            $inst->auth->getStorage()->write($inst->user_data);
        }

        return $data;
    }


    /**
     * Odebere uzivatele.  Pohlida aby uzivatel nesmazal sam sebe.
     *
     * @param string    Id uzivatele
     */
    public static function removeUser($id)
    {
        $inst = self::getInstance();
        $db = Zend_Registry::get('db');
        
        // Kontrola, jestli nemaze sam sebe.
        if($inst->user_data !== null && $inst->user_data->id == $id){
            throw new Ibulletin_AdminAuth_User_Self_Deleting_Exception('Uzivatel id="'.$id.'" nemuze smazat sam sebe.' );
        }
        
        $where = $db->quoteInto('id = ?', $id);
        
        return $db->update('admin_users', array('deleted'=>new Zend_Db_Expr('current_timestamp')),$where);
    }
    
    /**
     * Obnovi smazaneho uzivatele
     *
     * @param string    Id uzivatele
     */
    public static function restoreUser($id)
    {
        $db = Zend_Registry::get('db');
        
        $where = $db->quoteInto('id = ?', $id);
        
        return $db->update('admin_users', array('deleted'=>null),$where);
    }
    
    /**
     * Seznam adminu
     * 
     * @return type Zend_Db_Select
     */
    public static function getUsersQuery() {
   		
    	$inst = self::getInstance();
    	$db = Zend_Registry::get('db');
    	
    	$select = $db->select()
    		->from('admin_users',array('*','del' => new Zend_Db_Expr('(admin_users.id != '.$inst->user_data->id.' AND admin_users.deleted IS NULL)'),
                'superuser' => new Zend_Db_Expr('privileges like \'%privilege_all%\'')))
            ->joinLeft('users','admin_users.user_id=users.id',array('email'=>'users.email'));  
        return $select;
    }

    /**
     * Vraci seznam uzivatelu administrace.
     * 
     * @return array seznam uzivatelu administrace
     */
    public static function getUsers() {
   		
    	$inst = self::getInstance();
    	$db = Zend_Registry::get('db');
    	
    	$q = $db->select()
    		->from('admin_users')
    		->order('id');
    		
    	$res = $db->fetchAll($q);    	
    	
    	// Expandujeme opravneni z comma-separated retezce na pole 
    	// a nastavime editovatelnost a smazatelnost zaznamu (podle toho kto je prihlasen)
    	$can_edit = $inst->hasPermission('module_adminusers');
    	
    	foreach ($res as $key => $user) {
    		$res[$key]['privileges'] = explode(',', $res[$key]['privileges']);
    		// bez opravneni lze editovat pouze sebe sameho
    		$res[$key]['editable']= ($can_edit || $res[$key]['id'] == $inst->user_data->id);
    		// sebe sameho nejde smazat
    		$res[$key]['deletable'] = ($inst->user_data->id != $res[$key]['id']);
    	}

    	return $res;
    }
    
    /**
     * Vraci zaznam uzivatele administrace podle jeho id.
     * 
     *  @param int id uzivatele
     *  @return array
     */
    public static function getUser($id) {
    	
    	$db = Zend_Registry::get('db');
    	    	
    	$q = $db->select()
    		->from('admin_users')
    	   	->where('id = ?', (int)$id)
    		->limit(1);
    	
    	$res = $db->fetchRow($q);    	
    	
    	if (!$res) {
    		throw new Ibulletin_AdminAuth_User_Not_Found_Exception('Nebyl nalezen uzivatel s id '.$id.'.');
    	}
    	
    	// Ziskame email ze sprazeneho zaznamu v users
    	// TODO udelat klasicky join
    	$q2 = $db->select()
    		->from('users', 'email')
    		->where('id = ?', (int)$res['user_id'])
    	   	->limit(1);
    	
    	$res['email'] = $db->fetchOne($q2);
    	
    	// remove hashed password from result
    	unset($res['password']);
    	
    	return $res;
    	
    }
    
    /**
     * Vraci id uzivatele z tabulky users podle emailu
     * 
     * @param string	Email uzivatele na frontu
     * @return int|false	ID uzivatele nebo FALSE v pripade nenalezeni
     */
    public static function getUserIdByEmail($email) {

    	$db = Zend_Registry::get('db');
    	
    	$q = $db->select()
    		->from('users','id')
    		->where("email = ?", strtolower(trim($email)))
    		->limit(1);
    	
    	$res = $db->fetchOne($q);
    	
    	return $res;
    }
    
    
    /**
     * Vraci login uzivatele z tabulky admin_users podle emailu
     * 
     * @param string	Email uzivatele adminu
     * @return string|false	Login uživatele nebo FALSE v pripade nenalezeni
     */
    public static function getAdminUserLoginByEmail($email) {

    	$db = Zend_Registry::get('db');
    	
    	$q = $db->select()
    		->from('admin_users','login')
            ->joinLeft(array('u'=>'users'),'admin_users.user_id = u.id',array('email'))
    		->where("email = ?", strtolower(trim($email)))
            ->where("admin_users.deleted IS NULL")
    		->limit(1);
    	
    	$res = $db->fetchOne($q);
    	
    	return $res;
    }
    
     /**
     * Vraci id uzivatele z tabulky admin_users podle login
     * 
     * @param string	Login uzivatele adminu
     * @return string|false	ID uživatele nebo FALSE v pripade nenalezeni
     */
    public static function getAdminUserIdByLogin($login) {

    	$db = Zend_Registry::get('db');
    	
    	$q = $db->select()
    		->from('admin_users','id')
            ->where("login = ?", strtolower(trim($login)))
    		->limit(1);
    	
    	$res = $db->fetchOne($q);
    	
    	return $res;
    }
    

    /**
     * Overi heslo aktualniho uzivatele proti zadanemu heslo
     * a vrati true/false
     *
     * @return bool Je zadane heslo spravne heslo pro tohoto uzivatele?
     */
    public static function checkPassword($pass)
    {
        $inst = self::getInstance();
        return md5($pass) == $inst->user_data->password;
    }


    /**
     * Odhlasi uzivatele - smaze jeho identity
     */
    public static function logoff()
    {
        $inst = self::getInstance();
        
        $login = $inst->user_data->login;

        $inst->auth->clearIdentity();
        
        $inst->user_data = null;
        $inst->user_privileges = null;
        
        //odebereme cookie auth
        if (isset($_COOKIE['inbox_auth'])) {
            self::removeAuthCookie($login);
        }
        
        //ziskame redirector
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        //presmerujeme na login page
        $redirector->gotoAndExit('index', 'login', 'admin');
    }

    /**
     * Zjisti, jestli ma tento uzivatel adminu sprazeneho uzivatele frontendu
     * a pokud ano, vrati jeho ID.
     * @return false|int    ID uzivatele frontendu nebo false pokud neexistuje sprazeny uzivatel
     */
    public static function hasFrontendUser()
    {
        $inst = self::getInstance();
        if(!empty($inst->user_data->user_id)){
            return $inst->user_data->user_id;
        }

        return false;
    }
    
    /**
     * vraci ID uzivatele na frontendu
     * 
     * @return false|int    ID uzivatele frontendu nebo false pokud neexistuje sprazeny uzivatel
     */
    public static function getFrontendUser() {
    	$inst = self::getInstance();
    	if(!empty($inst->user_data->user_id)){
    		return $inst->user_data->user_id;
    	}
    	
    	return false;
    }
    /*
     * pokud existuje sprazeny uzivatel na frontendu vrati true, jinak false
     * 
     * @return boolean
     * /
    public static function hasFrontendUser() {
    	return (boolean)self::getFrontendUser();
    }
	*/
    /**
     * Nacte z instance Zend_Auth data uzivatele do promennych objektu,
     * pokud je uzivatel autentizovan.
     */
    private static function _loadFromStorage()
    {
        $inst = self::getInstance();

        if(!$inst->auth || !$inst->auth->hasIdentity()){
            // neni co nacitat
            return;
        }

        $inst->user_data = $inst->auth->getStorage()->read();
        $inst->user_privileges = $inst->user_data->privileges;
    }
    
    /**
     * Kontroluje zda ma uzivatel vyplnenou emailovou adresu
     * @param string $login
     * @return boolean
     */
    public static function checkAdminUserEmail($login) {
        $db = Zend_Registry::get('db');
        $sel = $db->select()->from(array('au'=>'admin_users'),array())
                ->joinLeft(array('u'=>'users'),'au.user_id = u.id',array('email'))
                ->where('au.login = ?', $login);
        $email = $db->fetchOne($sel);
        
        if ($email != '') {
            return true;
        } else {
            return false;
        }
        
    }
    /**
     * Kontoluje zda je mozne uzivateli priradit prava, prava superadmin a admin users je mozne nastavit jen se spravnou domenou
     * @param array $privileges
     * @param string $email
     * @return boolean
     */
    public static function canSetUserPrivilegesByEmail($privileges,$email) {
        
        if (in_array('privilege_all',$privileges) || in_array('module_adminusers',$privileges)) {
            
            $config = Zend_Registry::get('config');
            
            $email = explode('@', $email);
            $allow_domains = explode(',',$config->login->account->allow_domains);
            
            if (!in_array($email[1],$allow_domains)) {
                return false;
            }
            
        }
        
        return true;
    }
    
    public static function shortLimitAuthCokie() {
        $config = Zend_Registry::get('config');
        return isset($config->login->auth_cookie->short_limit) ? (intval($config->login->auth_cookie->short_limit) * 3600) : 0;
    }

    public static function longLimitAuthCokie() {
        $config = Zend_Registry::get('config');
        return isset($config->login->auth_cookie->long_limit) ? (intval($config->login->auth_cookie->long_limit) * 3600) : 0;
    }
    
    
    /**
     * Nastavi prihlaseni pomoci cookie
     * @param type $login login uzivatele
     * @param type $serie vygeneruje novou serii 
     */
    public static function setAuthCookie($login, $serie = true) {
        
        $auth_cookie = self::generateAuthCookie();
        $auth_cookie_serie = self::generateAuthCookie();
        
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('db');
        
        $where = $db->quoteInto('login = ?', $login);

        if ($serie) {
            if ($db->update('admin_users', array('auth_cookie' => $auth_cookie, 'auth_cookie_serie' => $auth_cookie_serie, 'auth_cookie_created' => Zend_Date::now()->getIso()), $where)) {
                setcookie('inbox_auth', self::encodeAuthCookie($auth_cookie, $auth_cookie_serie), time() + self::longLimitAuthCokie(), '/');
            }
        } else {
            $db->update('admin_users', array('auth_cookie' => $auth_cookie, 'auth_cookie_created' => Zend_Date::now()->getIso()), $where);
            $tac = self::getAuthCookie($login);
            setcookie('inbox_auth', self::encodeAuthCookie($auth_cookie, $tac['auth_cookie_serie']), time() + self::longLimitAuthCokie(), '/');
        }
        
    }
    
    /**
     * Odebrani cookie
     * @param string $login username
     */
    public static function removeAuthCookie($login = null) {
        if ($login) {
            /* @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('db');
            $where = $db->quoteInto('login = ?', $login);
            $db->update('admin_users', array('auth_cookie' => null, 'auth_cookie_serie' => null, 'auth_cookie_created' => null), $where);
            
            //odebereme cookie prava
            self::manipulatePrivileges(self::$cookie_auth_superuser_privilege,'remove',self::getAdminUserIdByLogin($login));

        }

        setcookie('inbox_auth', null, time() - 3600, '/');
    }

    /**
     * Generuje nahodny retezec pro cookie auth
     * @return string
     */
    private static function generateAuthCookie() {
        $gen = new Ibulletin_RandomStringGenerator();
        return $gen->get(self::$cookie_auth_length);
    }
    
    /**
     * Vytvori z auth cookie a z auth cookie serie hash 
     * @param string $auth_cookie
     * @param string $auth_cookie_serie
     * @return string
     */
    private static function encodeAuthCookie($auth_cookie,$auth_cookie_serie) {
        
        return substr($auth_cookie,0,self::$cookie_auth_serie_start).$auth_cookie_serie.substr($auth_cookie,self::$cookie_auth_serie_start,strlen($auth_cookie) - self::$cookie_auth_serie_start);

    }
    
    /**
     * Dekoduje auth hash a vrati pole z auth cookies
     * @param string $auth_cookie_code
     * @return array
     */
    private static function  decodeAuthCookie($auth_cookie_code) {
        
        $decode_auth_cookie = array(
            'auth_cookie' => substr($auth_cookie_code,0,self::$cookie_auth_serie_start).substr($auth_cookie_code,(self::$cookie_auth_length + self::$cookie_auth_serie_start),strlen($auth_cookie_code)-self::$cookie_auth_length),
            'auth_cookie_serie' => substr($auth_cookie_code,self::$cookie_auth_serie_start,self::$cookie_auth_length)
        );
        return $decode_auth_cookie;
    }
    
    /**
     * Vrati auth cookie dle loginu
     * @param string $login
     * @return Array
     */
    public static function getAuthCookie($login) {

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('db');
        $where = $db->quoteInto('login = ?',$login);

        $row = $db->fetchRow($db->select()->from('admin_users',array('auth_cookie','auth_cookie_serie','auth_cookie_created'))->where($where));

        return $row;
    }

    /**
     * Získá login dle auth cookie
     * @param string $auth_cookie_code
     * @return string
     */
    public static function getAuthCookieLogin($auth_cookie_code) {
        
        $auth = self::decodeAuthCookie($auth_cookie_code);
        
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('db');
        $where = $db->quoteInto('auth_cookie = ?',$auth['auth_cookie']);
        $where_serie = $db->quoteInto('auth_cookie_serie = ?',$auth['auth_cookie_serie']);

        $login = $db->fetchOne($db->select()->from('admin_users',array('login'))->where($where)->where('deleted IS NULL'));
        
        //neziskame-li login, ukoncime a odebereme cookie 
        if(!$login) {
            Phc_ErrorLog::error('Login', 'Auth cookie - Pokus o HACK chybny retezec auth cookie, Remote address: '.$_SERVER['REMOTE_ADDR']);
            self::removeAuthCookie($login);
            return null;
        }
        
        $login_serie = $db->fetchOne($db->select()->from('admin_users',array('login'))->where($where_serie));
        //neziskame-li login, ukoncime a odebereme cookie
        if(!$login_serie) {
            Phc_ErrorLog::error('Login', 'Auth cookie - Pokus o HACK chybny retezec auth cookie serie, Remote address: '.$_SERVER['REMOTE_ADDR']);
            self::removeAuthCookie($login_serie);
            return null;
        }

        if ($login == $login_serie) {
            return $login;
        } else {
            Phc_ErrorLog::error('Login', 'Auth cookie - Pokus o HACK auth cookie a auth cookie serie se neshoduji, Remote address: '.$_SERVER['REMOTE_ADDR']);
            self::removeAuthCookie();
            return null;
        }
        
    }
    
    /**
     * Kontroluje zda je auth cookie platne
     * @param string $login
     * @param boolean $long_limit true - kontroluje long limit | false - kontroluje short limit
     * @return boolean
     */
    public static function isValidCookie($login,$long_limit = true) {
        
        if ($long_limit) {
            $limit = self::longLimitAuthCokie();
        } else {
            $limit = self::shortLimitAuthCokie();
        }
        
        $ac = self::getAuthCookie($login);
        
        $ck_time = $ac['auth_cookie_created'];
        
        if ((strtotime($ck_time)+$limit) > time()) {
           return true;
        } else {
            return false;
        }
        
    }
    
     /**
     * Overuje zda je povoleno "nebezpecne prihlasovani", tzn. jsou povoleny vychozi ucty inbulletin nebo phc
     * @return boolean
     */
    public static function isAllowDangerLogin() {
        
        $config = Zend_Registry::get('config');

        if (isset($config->login->account->default_superuser->allow) && $config->login->account->default_superuser->allow == "1") {
            return true;
        }

        if (isset($config->login->account->default_admin->allow) && $config->login->account->default_admin->allow == "1") {
            return true;
        } 

        return false;
    }
    
    
    
    
}