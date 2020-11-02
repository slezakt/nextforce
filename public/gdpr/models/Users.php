<?php
/**
 * iBulletin - Users.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}
class Users_User_Not_Found_Exception extends Exception {}

/**
 * Codes:
 * 16  - Byl zadan nevalidni email.
 * 32  - Uzivatel s danym unikatnim klicem jiz v DB existuje.
 *       Pokud je nastaven atribut $e->data, obsahuje pole s kolidujicimi atributy.
 * 64  - Uzivatel nebyl nalezen.
 * 128 - Pri merge uzivatel koliduje s vice uzivateli v DB.
 *       V $e->data je pole $e->data[nazev atributu] = user_id
 * 256 - Pri vytvareni noveho uzivatele nebyl zadan zadny atribut z
         tabulky users, data nemohou byt vlozena (omezeni Zend_Db).
 */
class Users_Exception extends Exception {
    /**
     * Data pro predani do vyssich vrstev.
     * @var mixed
     */
    public $data = null;
}

/**
 * Trida poskytujici funkce spojene s uzivateli a manipulaci s nimi.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Users
{
    /**
     * @var string  Symbol oznacujici v importu (i exportu) pole, ktere se nema menit (ma v DB zustat tak jak je)
     */
    public static $noChangeSymbol = '(n/c)';

    /**
     * Atributy nalezene v importu s ohledem na tabulky
     * (klic je nazev atributu, hodnota je tabulka kam patri).
     * Je naplnen po provedeni $this->importData() nebo $this->importProcessRow().
     */
    public $importedAttribs = array();

    /**
     * Nazev puvodu DB importovanych dat, pouzivace pro import novych uzivatelu
     * @var string
     */
    public $importContactOrigin = "";


    private static $_anonymizeAttribs = array('name','surname','email');

    /**
     * Podle nastaveni v configu "general->domains_register_as_test" urci,
     * zda ma byt uzivatel oznacen jako target. Kontrola se provadi porovnanim
     * poslednich znaku z emailu s domenami z configu.
     *
     * @param  string   Emailova adresa uzivatele.
     * @return bool     Ma byt uzivatel oznacen jako Target podle zadaneho e-mailu?
     */
    public static function shouldBeMarkedAsTestByEmail($email)
    {
        $config = Zend_Registry::get('config');
        $domains = $config->general->getCommaSeparated('domains_register_as_test');
        if(empty($domains)){
            return false;
        }

        /*
        // Ziskame pole ze seznamu oddeleneho carkou
        $domainsA = explode(',', $domains);
        // Otrimujeme kazdy prvek
        array_walk($domainsA, create_function('&$val, $key','$val = trim($val);'));
        // Smazeme prazdne
        array_walk($domainsA, create_function('&$val, $key, &$array','if(empty($val)){unset($array[$key]);}'), $domainsA);
        */

        foreach($domains as $domain){
            // Kontrolujeme, jestli email konci retezcem z konfigu
            if(preg_match('/'.preg_quote($domain).'$/i', $email)){
                return true;
            }
        }

        return false;
    }

    /**
     * Podle nastaveni v configu "general->domains_register_as_client" urci,
     * zda ma byt uzivatel oznacen jako client (users -> client). Kontrola se provadi porovnanim
     * poslednich znaku z emailu s domenami z configu.
     *
     * @param  string   Emailova adresa uzivatele.
     * @return bool     Ma byt uzivatel oznacen jako "client" podle zadaneho e-mailu?
     */
    public static function shouldBeMarkedAsClientByEmail($email)
    {
        $config = Zend_Registry::get('config');
        $domains = $config->general->getCommaSeparated('domains_register_as_client');
        if(empty($domains)){
            return false;
        }

        /*
        // Ziskame pole ze seznamu oddeleneho carkou
        $domainsA = explode(',', $domains);
        // Otrimujeme kazdy prvek
        array_walk($domainsA, create_function('&$val, $key','$val = trim($val);'));
        // Smazeme prazdne
        array_walk($domainsA, create_function('&$val, $key, &$array','if(empty($val)){unset($array[$key]);}'), $domainsA);
        */

        foreach($domains as $domain){
            // Kontrolujeme, jestli email konci retezcem z konfigu
            if(preg_match('/'.preg_quote($domain).'$/i', $email)){
                return true;
            }
        }

        return false;
    }

    /**
     * Vrati radek z DB uzivatele podle id, emailu nebo loginu. Jedna se o zaznam uzivatele
     * vcetne speciaelnich atributu z tabulky users_attribs. Reprezentanti jsou vraceni jako pole.
     *
     * @param int       ID uzivatele z tabulky users
     * @param string    Email uzivatele.
     * @param string    Login uzivatele.
     * @param string    Authcode uzivatele
     * @param string    Hash emailu uzivatele
     * @return array    Radek uzivatele z DB
     * @throws Users_User_Not_Found_Exception pokud uzivatel neexistuje
     */
    public static function getUser($id, $email = null, $login = null, $authcode = null, $emailHash = null){
        if(empty($id) && empty($email) && empty($login) && empty($authcode) && empty($emailHash)){
            throw new Users_User_Not_Found_Exception("Uzivatel se zadanym ID ($id) nebo emailem ($email) ".
                "nebo loginem ($login) nebo authcode ($authcode) nebyl nalezen.");
        }

        $db = Zend_Registry::get('db');

        $sel = self::getUsersQuery()
            ->limit(1);
        if(!empty($id)){
            $sel->where("id = ?", $id);
        }
        elseif(!empty($email)){
            $sel->where("email = ?", $email);
        }
        elseif(!empty($login)){
            $sel->where("login = ?", $login);
        }
        elseif(!empty($authcode)){
            $sel->where("authcode = ?", $authcode);
        }
        elseif(!empty($emailHash)){
            $sel->where("md5(email) = ?", $emailHash);
        }

        $rows = $db->fetchAll($sel);
        if(empty($rows)){
            throw new Users_User_Not_Found_Exception("Uzivatel se zadanym ID ($id) nebo emailem ($email) nebo loginem ($login) nebyl nalezen.");
        }

        $user = $rows[0];

        // Najdeme a doplnime rozsirujici atributy uzivatele z users_attribs
        $sel = new Zend_Db_Select($db);
        $sel->from('users_attribs')
            ->where('user_id = ?', (int)$user['id']);
        $attribs = $db->fetchAll($sel);

        foreach($attribs as $attrib){
            $user[$attrib['name']] = $attrib['val'];
        }

        // Reprezentanty prevedeme na pole
        if(!empty($user['reps'])){
            $user['reps'] = explode(',', $user['reps']);
        }
        else{
            $user['reps'] = array();
        }

        return $user;
    }

    /**
     * get users query as Zend_Db_Select
     * @param array $exclude_cols seznam sloupcu vyloucenych z dotazu
     * @return Zend_Db_Select
     */
    public static function getUsersQuery($includeDeleted = false, $exclude_cols = array()) {

        /* @var $db Zend_Db_Adapter_Abstract */
        $db =  Zend_Registry::get('db');


        $cols = array_keys($db->describeTable('users'));

        if($exclude_cols) {
            $i = 0;
            foreach($cols as $c) {
                if (in_array($c, $exclude_cols)) {
                    unset($cols[$i]);
                }
                $i++;
            }
        }


        $cols['reps'] = new Zend_Db_Expr("array_to_string(ARRAY(SELECT ur.repre_id FROM users_reps ur WHERE user_id = u.id ORDER BY repre_id), ',')");

        // default is to exclude "deleted" users
        if (!$includeDeleted) {
            $cols['del'] = new Zend_Db_Expr('(u.deleted IS NOT NULL)');
        }

        $select = $db->select()->from(array('u' => 'users'), $cols);

        return $select;
    }

    /**
     * retrieves all distinct users_attribs names
     * @return array
     */
    public static function getUserAttribNames() {

        $db =  Zend_Registry::get('db');
        $sel = $db->select()->from('users_attribs', 'name')->distinct()->order('name');

        return $sel->query()->fetchAll();
    }


    /**
     * expands given db select (typically Users::getUsersQuery()) with user attributes.
     * optional user_id_key defines key for available users.id in select
     *
     * @param Zend_Db_Select $sel
     * @param $prefix
     * @param $user_id_key
     * @return Zend_Db_Select
     */
    public static function expandAttribs(Zend_Db_Select $sel, $prefix = '', $user_id_key = 'u.id') {

        $db =  Zend_Registry::get('db');
        foreach(self::getUserAttribNames() as $v){
            $name = $v['name'];
            $tbl = 'ua_'.$name; //
            // kombinovany index user_id,name zaruci nanejvys 1 zaznam
            $subsel = $db->select()->from(array('ua' => 'users_attribs'), array('user_id', 'val'))->where('name = ?', $name);
            $sel->joinLeft(array($tbl => $subsel), '"'.$tbl.'".user_id = '.$user_id_key, array($prefix . $name => 'val'));
        }

        return $sel;
    }

    /**
     * Vrati data jednoho ci vice uzivatelu v poli podle kterehokoli atributu.
     * Data obsahuji i zaznamy z users_attribs slozene do jednoho radku.
     *
     * @param string name   Jmeno atributu.
     * @param mixed val     Hodnota atributu, podle ktereho se maji hledat uzivatele.
     * @return array        Pole poli s daty jednotlivych nalezenych uzivatelu. Prazdne pole v pripade nenalezeni zadneho uzivatele.
     */
     public static function getUsersByAttrib($key, $val)
     {
         $db = Zend_Registry::get('db');
         if(empty($key)){
             throw new Users_Exception('Nebyl zadan nazev atributu pro vyhledavani.');
         }

         // Ziskame seznam atributu v users (pro rozliseni kde se maji data hledat)
         $q = "select column_name/*, data_type, ordinal_position*/ from INFORMATION_SCHEMA.COLUMNS where table_name = 'users' order by ordinal_position";
         $usersAttribsRows = $db->fetchAll($q);
         $usersAttribsList = array();
         foreach($usersAttribsRows as $row){
             $usersAttribsList[] = $row['column_name'];
         }

         $sel = new Zend_Db_Select($db);
         $sel->from(array('u' => 'users'), array('*'))
            ->join(array('users_attribs'), 'u.id = users_attribs.user_id', array('ua_name' => 'name', 'ua_val' => 'val'))
            ->order('u.id', 'users_attribs.name');

         // Provedeme omezeni bud podle users_attribs nebo podle primo users
         if(!in_array($key, $usersAttribsList)){
            $sel->join(array('ua1' => 'users_attribs'), 'ua1.user_id = u.id', array())
                ->where('ua1.name = ?',  $key)
                ->where('ua1.val = ?', $val);
         }
         else{
             $sel->where('u.'.$key.' = ?', $val);
         }

         $rows = $db->fetchAll($sel);


         // Slozime data pro jednotlive uzivatele
         $users = array();

         if ($rows) {
             $user = null;
             $lastUser = null;
             foreach($rows as $row){
                 // Inicializace pro kazdeho noveho uzivatele
                 if($lastUser != $row['id']){
                     if($lastUser !== null){
                         // Pridani hotoveho zagragovaneho uzivatele na vystup
                         $users[$lastUser] = $user;
                     }
                     $user = $row;
                     // Odstranime atributy z users_attribs na tomto radku z DB, pozdeji je pridame spravne
                     unset($user['ua_name']);
                     unset($user['ua_val']);
                 }

                 // Pridavame atributy z users_attribs
                 $user[$row['ua_name']] = $row['ua_val'];

                 $lastUser = $row['id'];
             }
             // Pridani hotoveho zagragovaneho uzivatele na vystup (posledni uzivatel)
             $users[$lastUser] = $user;
         }

         return $users;
     }


    /**
     * Zaridi akce podle konfigurace spojene s nastavenim nebo zmenenim emailu uzivatelem nekde
     * v aplikaci.
     *
     * Zatim neprovadi zadne akce podle konfigurace, jen ulozi zadany email do users, pokud je validni.
     *
     * @param string    Nove zadany email.
     * @param int       ID uzivatele, pokud je null, pokusi se fce pouzit aktualniho uzivatele
     */
    public static function emailSet($email, $user_id = null){
        if($user_id === null){
            $user_id = Ibulletin_Auth::getActualUserId();
        }
        if(!is_numeric($user_id)){
            throw new Users_User_Not_Found_Exception('Zadany uzivatel nebyl nalezen, nebo neni prihlasen zadny uzivatel. User ID:"'.$user_id.'"');
        }

        $validator = new Zend_Validate_EmailAddress();
        if(!$validator->isValid($email)){
            throw new Users_Exception('Zadany email neni validni.', 2);
        }

        Ibulletin_Auth::changeUsersData(array('email' => $email), $user_id, true);
    }


    /**
     * Nastavi priznak send_emails v tabulce users.
     *
     * @param int   $id Id uzivatele.
     * @param bool  $send_emails Nastaveni priznaku. Defaultne TRUE
     */
    public static function setSendEmails($id, $send_emails = true)
    {
        $db = Zend_Registry::get('db');

        $data = array('send_emails' => $send_emails);

        //$db->update('users', $data, 'id='.$id);
        Users::updateUser($id, $data, false);
    }

    /**
     * Nastavi priznak unsubscribed v tabulce users.
     *
     * DEFAULTNE nastavuje na NULL, tedy rusi unsubscribed, pokud je
     * $unsubscribed zadan TRUE, je v users.unsubscribed vyplnena casova znamka.
     *
     * @param int   $id Id uzivatele.
     * @param bool  $unsubscribed Nastaveni priznaku, pri TRUE nastavi casovou znamku. Defaultne FALSE
     */
    public static function setUnsubscribed($id, $unsubscribed = false)
    {
        $db = Zend_Registry::get('db');

        if($unsubscribed){
            $unsubscribedSql = new Zend_Db_Expr('current_timestamp');
        }
        else{
            $unsubscribedSql = new Zend_Db_Expr('NULL');
        }

        $data = array('unsubscribed' => $unsubscribedSql);

        //$db->update('users', $data, 'id='.$id);
        Users::updateUser($id, $data, false);
    }


    /**
     * Priradi k danemu uzivateli reprezentanty.
     *
     * @param $userId   int     Id uzivatele pro ktereho maji byt reprezentanti zapsani.
     * @param $reps     array   Pole ID reprezentantu.
     * @param $replace  bool    Preprsat vsechny zaznamy repu aktualne dodanymi [true]?
     *                          (jinak se jen pridaji novi repove k jiz existujicim v db)
     * @return  int             Pole ID repu, ktere se podarilo zapsat.
     */
    public static function writeReps($userId, $reps, $replace = true)
    {
        $db = Zend_Registry::get('db');

        // Smazeme existujici vazby na repre, pokud replacujeme
        if($replace){
            $db->delete('users_reps', 'user_id = '.(int)$userId);
        }

        $writtenReps = array();
        // Zapiseme postupne jednotlive zaznamy do users_reps
        foreach($reps as $rep){
            try{
                $db->insert('users_reps', array('user_id' => (int)$userId, 'repre_id' => (int)$rep));
                $writtenReps[] = $rep;
            }
            catch(Zend_Db_exception $e){
                // Zachycena vyjimka znamena, ze zaznam jiz existuje nebo byl zadan neexistujici rep
            }
        }

        return $writtenReps;
    }


    /**
     * Vrati pole reprezentantu zadaneho uzivatele.
     *
     * @param int   $userId Id uzivatele
     */
    public static function getReps($userId)
    {
        $db = Zend_Registry::get('db');

        $sel = new Zend_Db_Select();
        $sel->from(array('ur' => 'users_reps'), array('repre_id'))
            ->where('ur.user_id = '.(int)$userId);

        $rows = $db->fetchAll($sel);

        $reps = array();
        foreach($rows as $row){
            $reps[] = $row['repre_id'];
        }

        return $reps;
    }


    /**
     * Updatuje nebo vytvori novy zaznam uzivatele v DB.
     *
     * V pripade zakladani noveho uzivatele neresi nijak kolize, takze pokud zaznam koliduje
     * s nejakym unikatnim klicem bude vyhozena vyjimka.
     *
     * Pracuje jen s daty, neprovadi zadne ukony souvisejici s bussines logikou jako
     * odesilani mailu, nastavovani priznaku a podobne.
     *
     * Mela by se pouzivat vyhradne po dukladnem zvazeni, jestli nejsou vhodnejsi metody
     * Ibulletin_Auth::changeUsersData() a Ibulletin_Auth::registerUser(), ktere implementuji
     * bussines logiku a nasledne tepre volaji tuto metodu.
     *
     * Heslo zadavame v plain podobe, funkce si sama heslo zakoduje a pripadne ulozi i plain kopii
     * podle nastaveni v configu. Pokud je zadano v datech jen pass_plain, je pouzito jako pass a zakodovano.
     *
     * Reprezentanty predavame jako pole nebo seznam ID oddeleny carkou.
     * POZOR: Chyby ukladani reprezentantu nehlasi - ulozi repy, kteri existuji jako repove.
     *
     * !!Data neprizpusobuje datovym typum v DB (co dostane to ulozi).
     *
     * @param   int     ID uzivatele, pokud je null, vlozi se novy uzivatel. Pro neexistujici ID je vytvoren novy uzivatel.
     * @param   array   Data uzivatele k ulozeni (klic pole je atribut v DB).
     * @param   bool    Ma se provadet merge uzivatelu v pripade, ze jiz v DB existuje shodny e-mail?
     * @param   array   Pole nazvu atributu, podle kterych se maji spojovat
     *                  (zatim jen) novi uzivatele. V pripade kolize na techto atributech s vice uzivateli
     *                  je vyhozena vyjimka s koliznimi atributy v $e->data[nazev atributu] = user_id.
     * @param   bool    Jedna se o pozadavek od inDirecotru? (pokud ano, nesmeji se zmeny odesilat zpet!)
     * @param   string  Oznaceni metody kterou bylo s uzivatelem nakonec nalozeno - update, insert, merge
     * @param   bool    Spoustime na scuho (bez skutecneho zapisu do DB)?
     * @param   string  Puvod DB
     *
     * @return  int|null    ID uzivatle nebo ID uzivatele na ktereho byl upravovany uzivatel mergovan
     *
     * @throws  Users_Exception
     * @throws  Zend_Db_...
     */
    public static function updateUser($id, $attribs, $mergeUsers = true, $mergeAttribs = array('email'),
        $isIndirectorRequest = false, &$usedMethod = null, $dryRun = false, $contact_origin = '')
    {
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet('admin.users');

        if($id !== null){
            $id = (int)$id;
            // Ziskame data uzivatele tak jak jsou puvodni v DB
            try{
                $usersDataOld = self::getUser($id);
            }
            // Pokud uzivatel neexistuje, odstranime ID
            catch(Users_User_Not_Found_Exception $e){
                $id = null;
            }
        }

        if(array_key_exists('id', $attribs)){
            unset($attribs['id']);
        }

        //odstranime puvod db, paklize je predavana v atributech
        if(array_key_exists('contact_origin', $attribs)){
            unset($attribs['contact_origin']);
        }

        if(array_key_exists('selfregistered', $attribs)){
            if ($attribs['selfregistered']) {
                $contact_origin = 'selfregistered';
            }
        }

        // Ziskame seznam atributuu users
        $q = "select column_name from INFORMATION_SCHEMA.COLUMNS where table_name = 'users'";
        $columns = $db->fetchAll($q);
        $cols = array();
        foreach($columns as $col){
            $cols[] = $col['column_name'];
        };

        // Pokud menime heslo, musime jej zMD5kovat
        // Heslo neresime v pripade dat z inDirecotru - tam prijde heslo zaMD5kovany a passplain udelat uz nemuzeme
        if((isset($attribs['pass']) || isset($attribs['pass_plain'])) && !$isIndirectorRequest){
            !isset($attribs['pass']) ? $attribs['pass'] = $attribs['pass_plain'] : null; // Pokud je zadano jen pass_plain, pouzijeme jej jako heslo
            // Pokud se ma heslo ukladat i jako plain, musime to ucinit
            if(!empty($config->general->password_save_as_plain)){
                $attribs['pass_plain'] = $attribs['pass'];
            }

            $attribs['pass'] = md5($attribs['pass']);
        }

        // Validace emailu
        if(!empty($attribs['email'])){
            $attribs['email'] = strtolower(trim($attribs['email']));
            $emailValidator = new Zend_Validate_EmailAddress();
            if(!$emailValidator->isValid($attribs['email']) || empty($attribs['email'])){
                throw new Users_Exception(sprintf($texts->invalid_mail,$attribs['email']), 16);
            }
        }
        elseif(array_key_exists('email', $attribs)){
            $attribs['email'] = null;
        }

        // Dame do zvlastniho pole atributy, ktere nejsou primo v tabulce users
        $attribsExt = array();
        foreach($attribs as $key => $val){
            if(!in_array($key, $cols)){
                unset($attribs[$key]);
                $attribsExt[$key] = $val;
            }
        }

        // Pokud v attribs do tabulky users nic nezustalo a neni nastaveno ID, tak koncime,
        // Zend neumi vkladat novy zaznam s prazdnym polem.
        if(empty($attribs) && !$id){
            throw new Users_Exception($texts->no_atributes_ins, 256);
        }

        // Pokud se maji uzivatele spojit a je ke zmene zadan email,
        // provedeme spojeni uzivatelu dohromady
        $mergedTo = null;
        if(!empty($id) && $mergeUsers && isset($attribs['email'])){
            $sel = $db->select()
                ->from('users')
                ->where('email = :email')
                ->order(array('id'));
            $row = $db->fetchRow($sel, array('email' => $attribs['email']));
            if($row && $row['id'] != $id){
                $target_id = $row['id'];
                // Provedeme spojeni uzivatelu
                Ibulletin_Stats::mergeUsers($target_id, $id);
                $id = $target_id;
                $mergedTo = $id;
                $usedMethod = 'merge';
            }
        }


        // Pokud se jedna o noveho uzivatele a merge, musime hledat podle zadanych $mergeAttribs
        if($mergeUsers && empty($id) && count($mergeAttribs)){
            // Najdeme pripadne kolidujici uzivatele na $mergeAttribs
            $q = $db->select()
                    ->from(array('u' => 'users'), array('*'));
            // Hledame podle vsech unikatnich atributuu identifikujicich uzivatele,
            // abychom mohli resit vsechny kolize
            $hasUniqueAttr = false;
            foreach($mergeAttribs as $attrKey => $attr){
                if(isset($attribs[$attr])){
                    $q->orwhere($attr." = ".$db->quote($attribs[$attr]));
                    $hasUniqueAttr = true;
                }
                else{
                    // Odstranime nekolidujici atributy
                    unset($mergeAttribs[$attrKey]);
                }
            }
            // Pokud ma uzivatel nejaky z unikatnich atributu, pokracujeme v hledani kolidujicich
            if($hasUniqueAttr){
                $collidingUsers = $db->fetchAll($q);

                // Pokud koliduje s vice uzivateli, vyhledame data do vyjimky a vyhodime vyjimku
                if(count($collidingUsers) > 1){
                    $errorData = array();
                    foreach($collidingUsers as $colUser){
                        foreach($mergeAttribs as $key => $attr){
                            if(isset($colUser[$attr])){
                                $errorData[$attr]= $colUser['id'];
                            }
                        }
                    }
                    $e = new Users_Exception(vsprintf($texts->merge_error,array(print_r($mergeAttribs, true),var_export($errorData, true))), 128);
                    $e->data = $errorData;
                    throw $e;
                }
                elseif(isset($collidingUsers[0])){
                    // Updatujeme uzivatele, ktereho jsme nasli, takze jen nastavime $id a provede se update
                    $id = $collidingUsers[0]['id'];
                }
            }
        }

        /**
         * UKLADAME
         */

        if (empty($attribs) && empty($attribsExt)) {
            throw new Users_Exception(sprintf($texts->empty_attr_row, $id), 64);
        }

        // UPDATE
        if(!empty($id)){
            try{
                if(!$dryRun && $attribs){
                    $affected = $db->update('users', $attribs, sprintf('id = %d', $id));
                }
                else{
                    $affected = 1;
                }
                if($affected < 1){
                    // Uzivatel neexistuje
                    throw new Users_Exception(sprintf($texts->user_not_found,$id), 64);
                }
                $usedMethod = 'update';
            }
            catch(Zend_Db_Statement_Exception $e){
                if(stripos($e->getMessage(), 'Unique violation') !== false){
                    $e1 = new Users_Exception(sprintf($texts->user_exist,$e), 32);
                    throw $e1;
                }
                throw $e;
            }
        }
        // INSERT
        else{
            // Pokusime se vlozit udaje noveho uzivatele
            try
            {
                // Pokud se toto vlozeni povede, znamena to, ze uzivatel v db neexistoval
                if(!$dryRun){

                    //pred insertem doplnime contact_origin
                    if ($contact_origin) {
                        $attribs['contact_origin'] = $contact_origin;
                    }

                    $db->insert('users', $attribs);

                    // Zjistime ID noveho uzivatele
                    $id = $db->lastInsertId('users', 'id');
                }
                elseif(!empty($attribs['email'])) {
                    //pro dry run zkontrolujeme zda v databázi neexistuje uživatel se stejným emailem
                    $mq = $db->select()->from('users')->where('email = ?', $attribs['email']);
                    $mrow = $db->fetchRow($mq);

                    if ($mrow) {
                        throw new Users_Exception(sprintf($texts->mail_exist,$attribs['email']),32);
                    } else {
                        $id = '99999';
                    }
                }
                else{
                    $id = '99999';
                }
                $usedMethod = 'insert';
            }
            catch (Zend_Db_Statement_Exception $e)
            {
                if(stripos($e, 'unique violation') !== false){
                    // Jedna se o problem unikatnosti klice
                    $e1 = new Users_Exception(sprintf($texts->user_exist,$e), 32);

                    // Najdeme pripadne kolidujici uzivatele
                    $q = $db->select()
                            ->from(array('u' => 'users'), array('*'));
                    // Hledame podle vsech unikatnich atributuu identifikujicich uzivatele,
                    // abychom mohli resit vsechny kolize
                    $hasUniqueAttr = false;
                    foreach($mergeAttribs as $attrKey => $attr){
                        if(isset($attribs[$attr])){
                            $q->orwhere($attr." = ".$db->quote($attribs[$attr]));
                            $hasUniqueAttr = true;
                        }
                        else{
                            // Odstranime nekolidujici atributy
                            unset($mergeAttribs[$attrKey]);
                        }
                    }
                    // Pokud ma uzivatel nejaky z unikatnich atributu, pokracujeme v hledani kolidujicich
                    if($hasUniqueAttr){
                        $collidingUsers = $db->fetchAll($q);
                        // Pokud koliduje s vice uzivateli, vyhledame data do vyjimky a vyhodime vyjimku
                        if(count($collidingUsers) >= 1){
                            $errorData = array();
                            foreach($collidingUsers as $colUser){
                                foreach($mergeAttribs as $key => $attr){
                                    if(isset($colUser[$attr])){
                                        $errorData[$attr]= $colUser['id'];
                                    }
                                }
                            }
                            $e1->data = $errorData;
                        }
                    }

                    throw $e1;
                }
                else{
                    // Jedna se o jiny problem
                    throw $e;

                    //Phc_ErrorLog::error('Exception', $e);
                }
            }
        }

        //vyhodime atribut patrici k vazebni tabulce reps_pages
        unset($attribsExt['presentations']);

        //pomocna promenna pro zjisteni,zda doslo ke zmene dat
        $updated = false;

        // Ulozime atributy, ktere nejsou v tabulce users
        // Reprezentanti
        if(array_key_exists('rep', $attribsExt)){ // Stara metoda zadani jednoho repa - kvuli kompatibilite
            $attribsExt['reps'] = array($attribsExt['rep']);
            unset($attribsExt['rep']);
        }
        if(array_key_exists('reps', $attribsExt)){
            // Pro cokoli prazdneho nastavime repre na prazdne pole
            if(empty($attribsExt['reps'])){
                $attribsExt['reps'] = array();
            }
            // Reprezentanti oddeleni carkou
            if(!is_array($attribsExt['reps'])){
                $foo = preg_replace('/\s+/', '', $attribsExt['reps']);
                $attribsExt['reps'] = explode(',', $foo);
            }

            foreach($attribsExt['reps'] as $key => $rep){
                if(empty($rep)){
                    unset($attribsExt['reps'][$key]);
                    continue;
                }
            }

            // ZAPISEME REPY pokud neni dry run
            if(!$dryRun){
                // Zapiseme
                $insertedReps = Users::writeReps($id, $attribsExt['reps']);
                // Zkontrolujeme, zda se vlozili vsichni
                $subtract = array_diff($attribsExt['reps'], $insertedReps);
                if(!empty($subtract)){ // Nejaci repre se neulozili
                      throw new Users_Exception(sprintf($texts->reps_save_error,join(', ', $subtract).'.'));
                }
                $updated = true;
            }

            unset($attribsExt['reps']);
        }

        // Users_attribs
        foreach($attribsExt as $key => $attr){
            // Atributy, ktere jsou prazdne smazeme
            if($attr !== null && !strtolower((string)$attr) !== 'null'){
                try
                {
                    if(!$dryRun){
                        $db->insert('users_attribs', array('user_id' => $id, 'name' => $key, 'val' => $attr));
                        $updated = true;
                    }
                }
                catch(Zend_Db_Statement_Exception $e){
                    if(stripos($e, 'unique violation') !== false){
                        // Updatujeme udaj uzivatele
                        $db->update('users_attribs', array('val' => $attr), "user_id = $id AND name = '$key'");
                        $updated = true;
                    }else{
                        // Nechame to bezet a chybu zalogujeme
                        Phc_ErrorLog::error('Users::updateUser()', $e);
                    }
                }
            }
            // Mazeme atributy, ktere jiz nejsou potreba (jsou nastaveny na null)
            elseif(!empty($usersDataOld) && isset($usersDataOld[$key]) && !$dryRun){
                $db->delete('users_attribs', sprintf("user_id = %d AND name = '%s' ", $id, $key));
            }
        }

        //doslo-li ke zmene user attribs a zaroven se neukladalo nic do tabulky users, zmenime datum posledni upravy v tabulce users
        if ($updated && !$attribs) {
           self::setLastChanged($id);
        }

        // Obnovime data uzivatele v Auth
        //Ibulletin_Auth::getActualUserData(true); // Snad si provede vrstva nad kdyz to potrebuje

        //
        // Provedeme oznameni zmen inDirectoru
        //
        if(!$isIndirectorRequest && !$dryRun){
            $indir = null;
            if(isset($config->indirector) && isset($config->indirector->indirectorUrl)){
                $indir = $config->indirector->indirectorUrl;
            }
            if(!empty($indir)){

                //
                // Pripravime jen data, ktera byla zmenena
                //
                $data = array('id' => $id);
                // Ziskame data uzivatele tak jak jsou v DB
                $usersDataNew = self::getUser($id);
                /* ZATIM POUZIVAME ODESILANI VZDY CELEHO ZAZNAMU KVULI KONZISTENCI
                // Kdyz se nejedna o noveho uzivatele, prochazime data a hledame zmeny
                if(!empty($usersDataOld)){
                    foreach($usersDataNew as $key => $val){
                        if(!array_key_exists($key, $usersDataOld) || $usersDataOld[$key] != $val){
                            $data[$key] = $val;
                        }
                    }
                }
                */
                $data = $usersDataNew;

                // Odesleme
                if(!Zend_Registry::isRegistered('defferedCommunicator')){
                    $comm = Communicator_ClientAbstract::factory();
                    // Ulozime communicator do registru, aby byl spusten destructor az pri ukonceni procesu
                    // a aby bylo mozne communicator znovu pouzit.
                    //Zend_Registry::set('defferedCommunicator', $comm);
                }
                else{
                    $comm = Zend_Registry::get('defferedCommunicator');
                }

                // UPDATE
                $comm->updateUserCall($data);
            }
        }

        return $id;
    }
    /**
     * Vrati seznam mailu pro select list
     *
     * @return array
     */
    public function getListTestMail() {
        $list = array();
        $db = Zend_Registry::get('db');

        $select = $db->select()
                ->from("users")
                ->where("deleted IS NULL")
                ->where("test = ?", true)
                ->where("email IS  NOT NULL")
                ->where('send_emails IS true')
                ->order(array("surname", "name", "email"));

        $rows = $db->fetchAll($select);

        if ($rows) {
        foreach ($rows as $row) {
            $list[$row['id']] = $row['surname'] . " " . $row['name'] . "  " . $row['email'];
        }
        }

        return $list;
    }


    /**
     * Importuje uzivatele z csv souboru.
     *
     * @param $file string      Cesta k souboru, ze ktereho nacitame data
     * @param $type string      Typ souboru k importu - csv | xml
     * @param $encoding string  Kodovani souboru (UTF-8, cp1250, ...)
     * @param $messages array   Pole pro predani vyslednych hlasek, kazdy prvek je pole obsahujici
     *                          0 - telo zpravy, 1 - typ zpravy, 2 - promenne pro doplneni do tela zpravy
     *                          Zpravy obvykle pouzivame pro vypis pomoci
     *                          Ibulletin_Admin_BaseControllerAbstract::infoMessage().
     * @param $dryRun bool      Spustit na bez skutecneho ukladani do DB?
     *
     * @return      string      CSV data vadnych radku
     */
    public function importData($file, $type, $encoding, &$messages = array(), $dryRun = false)
    {
        $db =  Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet('admin.users'); // HACK texty z Admin_UsersController

        // Pripravime do objektu promenne pro kontrolu importovanych dat
        $this->importPrepare();

        // Spustime nacitani souboru podle typu
        // -- CSV --
        if($type == 'csv'){
            $result = $this->importProcessCsv($file, $encoding, $messages, $dryRun);
            $usersInserted = $result['inserted'];
            $usersUpdated = $result['updated'];
            $dataForRepair = $result['forRepair'];
        }
        // -- XML --
        elseif($type == 'xml'){
            $result = $this->importProcessXml($file, $messages, $dryRun);
            $usersInserted = $result['inserted'];
            $usersUpdated = $result['updated'];
            $dataForRepair = $result['forRepair'];
        }
        // -- XLSX --
        elseif($type == 'xlsx'){
            $result = $this->importProcessXlsx($file, $encoding, $messages, $dryRun);
            $usersInserted = $result['inserted'];
            $usersUpdated = $result['updated'];
            $dataForRepair = $result['forRepair'];
        }
        else{
            throw new Users_Exception(sprintf($texts->wrongtype, $type));
        }

        // Vypiseme dokonceni importu
        $messages[] = array($texts->csv_success,'success',array($usersInserted, $usersUpdated));

        // Pripravime CSV pro opravu vadnych radku
        if(!empty($dataForRepair)){
            require_once('parsecsv/parsecsv.lib.php');
            $repairHead = array_keys(current($dataForRepair));
            if(!in_array('id', $repairHead)){
                $repairHead[] = 'id';
            }
            $csvRepair = new parseCSV();
            $dataRepair = $csvRepair->unparse($dataForRepair, $repairHead, null, null, ";");

            return nl2br($dataRepair);
        }

        return '';
    }


    /**
     * Provede zpracovani CSV souboru. Ma byt volano z importData().
     *
     * @param $file string      Cesta k souboru, ze ktereho nacitame data
     * @param $encoding string  Kodovani souboru (UTF-8, cp1250, ...)
     * @param $messages array   Pole pro predani vyslednych hlasek, kazdy prvek je pole obsahujici
     *                          0 - telo zpravy, 1 - typ zpravy, 2 - promenne pro doplneni do tela zpravy
     *                          Zpravy obvykle pouzivame pro vypis pomoci
     *                          Ibulletin_Admin_BaseControllerAbstract::infoMessage().
     * @param $dryRun bool      Spustit na bez skutecneho ukladani do DB?
     *
     * @return array            Obsahuje klice:
     *                          inserted - pocet nove vlozenych zaznamu
     *                          updated - pocet updatovanych zaznamu
     *                          forRepair - Pole obsahujici vadne radky
     */
    public function importProcessCsv($file, $encoding, &$messages = array(), $dryRun = false)
    {
        require_once('parsecsv/parsecsv.lib.php');

        // Provedeme nacteni CSV
        $csv = new parseCSV();
        $csv->delimiter = ';';
        $csv->enclosure = '"';
        $csv->encoding($encoding, 'UTF-8');
        @$csv->parse($file);

        // Vypiseme errors z parsovani CSV
        foreach($csv->error_info as $error){
            $messages[] = array("Line: {$error['row']}, column: {$error['field']} ({$error['field_name']}) - {$error['info']}");
        }

        $usersInserted = 0; // Pocitadlo vlozenych uzivatelu
        $usersUpdated = 0; // Pocitadlo updatovanych uzivatelu

        $dataForRepair = array();

        //detekci prazdnych sloupcu nebo chybejiciho zahlavi
        $first_row = reset($csv->data);
        foreach (array_keys($first_row) as $k => $v) {
            //csv parse pri chybejicim zahlavi sloupce doplni cislo sloupce, tzn. hleda pripady kdy se klic rovna popisku
            if (strval($k) == $v) {
               $texts = Ibulletin_Texts::getSet('admin.users');
               $messages[] = array($texts->emptycol, 'error', array());
               return array('inserted' => 0, 'updated' => 0, 'forRepair' => array());
            }
        }

        #############################################
        ### Zpracujeme data
        #############################################
        foreach($csv->data as $row){
            // Zpracujeme jednotlive radky
            $result = $this->importProcessRow($row, $messages, $dryRun);

            $result['status'] == 'updated' ? $usersUpdated++ : null;
            $result['status'] == 'inserted' ? $usersInserted++ : null;
            !empty($result['badRow']) ? $dataForRepair[] = $result['badRow'] : null;
        }

        return array(
            'inserted' => $usersInserted,
            'updated' => $usersUpdated,
            'forRepair' => $dataForRepair
        );
    }

    /**
     * Provede zpracovani XLSX souboru. Ma byt volano z importData().
     * pouzije se skript utils/xlsx2csv a vola se import csv
     *
     * @param $file string      Cesta k souboru, ze ktereho nacitame data
     * @param $encoding string  Kodovani souboru (UTF-8, cp1250, ...)
     * @param $messages array   Pole pro predani vyslednych hlasek, kazdy prvek je pole obsahujici
     *                          0 - telo zpravy, 1 - typ zpravy, 2 - promenne pro doplneni do tela zpravy
     *                          Zpravy obvykle pouzivame pro vypis pomoci
     *                          Ibulletin_Admin_BaseControllerAbstract::infoMessage().
     * @param $dryRun bool      Spustit na bez skutecneho ukladani do DB?
     *
     * @return array            Obsahuje klice:
     *                          inserted - pocet nove vlozenych zaznamu
     *                          updated - pocet updatovanych zaznamu
     *                          forRepair - Pole obsahujici vadne radky
     */
    public function importProcessXlsx($file, $encoding, &$messages = array(), $dryRun = false)
    {
        $emptyOut = array('inserted' => 0, 'updated' => 0, 'forRepair' => array());

        // convert XLSX to CSV
        $tmpfname = 'cache/import_xlsx'.uniqid().'.csv';
        $cmd = 'utils/xlsx2csv.py -d ";" '.$file.' '.$tmpfname.' 2>&1';
        $res = exec($cmd, $out, $exitcode);

        if ($exitcode) {
            // remove temporary file
            unlink($tmpfname);

            // set error message
            $texts = Ibulletin_Texts::getSet('admin.users'); // HACK texty z Admin_UsersController
            $messages[] = array($texts->wrongtype, 'error', array('XLSX'));

            // return empty result
            return $emptyOut;
        }

        // run CSV parser
        $res = $this->importProcessCsv($tmpfname,$encoding, $messages, $dryRun);
        // remove temporary file
        unlink($tmpfname);

        return $res;
    }

    /**
     * Provede zpracovani XML souboru. Ma byt volano z importData().
     *
     * Cteni XML souboru se provadi pomoci XMLreader, ktery prochazi nody postupne
     * bez ukladani celeho stromu do pameti.
     *
     * @param $file string      Cesta k souboru, ze ktereho nacitame data
     * @param $messages array   Pole pro predani vyslednych hlasek, kazdy prvek je pole obsahujici
     *                          0 - telo zpravy, 1 - typ zpravy, 2 - promenne pro doplneni do tela zpravy
     *                          Zpravy obvykle pouzivame pro vypis pomoci
     *                          Ibulletin_Admin_BaseControllerAbstract::infoMessage().
     * @param $dryRun bool      Spustit na bez skutecneho ukladani do DB?
     *
     * @return array            Obsahuje klice:
     *                          inserted - pocet nove vlozenych zaznamu
     *                          updated - pocet updatovanych zaznamu
     *                          forRepair - Pole obsahujici vadne radky
     */
    public function importProcessXml($file, &$messages = array(), $dryRun = false)
    {
        $texts = Ibulletin_Texts::getSet('admin.users'); // HACK texty z Admin_UsersController

        // Prazdny vystup pro return
        $emptyOut = array('inserted' => 0, 'updated' => 0, 'forRepair' => null);

        // Priprava XML readeru
        $xml = new XMLReader();
        if(!$xml->open($file)){
            $messages[] = array($texts->xls_error_reading_file, 'error');
            return $emptyOut;
        }

        // Sledovani pozice ve stromu
        $xmlPos = array();

        $usersInserted = 0; // Pocitadlo vlozenych uzivatelu
        $usersUpdated = 0; // Pocitadlo updatovanych uzivatelu

        $dataForRepair = array();

        // Hlavicka tabulky
        $heading = null;
        // Zpracovavany radek
        $newRow = array();
        $offset = 0;

        // Postupne prochazime dokumentem
        while(@$xml->read()){
            // Hledame workbook
            if($xml->nodeType == XMLReader::ELEMENT){
                /* Toto nedelame, protoze nekdy je empty element i Cell (musime tedz zpracovat kazdy element, aby se napocitaly indexy)
                if($xml->isEmptyElement){ // Empty element nevraci konec elementu
                    continue;
                }
                 */
                array_push($xmlPos, $xml->name);

                //echo join('.', $xmlPos).'<br/>';

                // Radek dat podle cesty v XML
                if(join('.', $xmlPos) == 'Workbook.Worksheet.Table.Row'){
                    $newRow = array();
                }

                elseif(join('.', $xmlPos) == 'Workbook.Worksheet.Table.Row.Cell'){
                    // Sledujeme ss:Index u Cell, protoze muze posunout data o bunky vpravo
                    if($xml->moveToAttribute('ss:Index')){
                        $offset = $xml->value;
                    }
                    else{
                        $offset++;
                    }
                }

                elseif(join('.', $xmlPos) == 'Workbook.Worksheet.Table.Row.Cell.Data'){
                    $newRow[$offset] = $xml->readString();
                }
            }



            // Konec elementu, prazdny element hned zase konci
            if($xml->nodeType == XMLReader::END_ELEMENT || $xml->isEmptyElement){
                // Vyskocili jsme z elementu
                $popped = array_pop($xmlPos);
                //echo 'removed '.$popped.'<br/>';

                // Pokud koncime row, zpracujeme radek
                if($popped == 'Row' && join('.', $xmlPos) == 'Workbook.Worksheet.Table'){
                    if($heading){
                        // Prelozime na pole se spravnymi klici
                        $row = array();
                        foreach($newRow as $key => $val){
                            $row[$heading[$key]] = $val;
                        }

                        #############################################
                        ### Zpracujeme data
                        #############################################

                        // Nepouzivame, protoze to zabranuje importu novych uzivatelu
                        //if (!array_key_exists('email', $row) && !array_key_exists('email', $row)) {
                        //  $messages[] = array($texts->id_email_miss,'error');
                        //  return;
                        //}

                        //detekce prazdych sloupcu nebo nevyplnenych zahlavich sloupcu
                        if (array_key_exists('', $row)) {
                            $texts = Ibulletin_Texts::getSet('admin.users');
                            $messages[] = array($texts->emptycol, 'error', array());
                            // Vysledek
                            return array(
                                'inserted' => $usersInserted,
                                'updated' => $usersUpdated,
                                'forRepair' => $dataForRepair
                            );

                        }

                        $result = $this->importProcessRow($row, $messages, $dryRun);

                        $result['status'] == 'updated' ? $usersUpdated++ : null;
                        $result['status'] == 'inserted' ? $usersInserted++ : null;
                        !empty($result['badRow']) ? $dataForRepair[] = $result['badRow'] : null;


                        //print_r($row);
                        //echo '<br/>';
                    }
                    // Hlavicka, pokud jeste nemame
                    else{
                        $heading = $newRow;
                    }
                    $newRow = array();
                    $offset = 0;
                }
            }
        }

        //otestujeme zda pricteni nanastali nějaké chyby
        if(libxml_get_last_error()) {
            $messages[] = array(libxml_get_last_error()->message,'error');
        }

        if(!$heading){
            // Nenalezen element Workbook.Worksheet.Table.Row
            $messages[] = array($texts->xls_wrong_format_element, 'error', array('Workbook.Worksheet.Table.Row'));
            return $emptyOut;
        }

        // Uzavreme soubor
        $xml->close();

        // Vysledek
        return array(
            'inserted' => $usersInserted,
            'updated' => $usersUpdated,
            'forRepair' => $dataForRepair
        );
    }


    /**
     * Pripravi do objektu data pro overovani spravnosti dat jednotlivych radku importu.
     * (repre, segmenty, regiony, seznam znamych atributu)
     */
    public function importPrepare()
    {
        $db =  Zend_Registry::get('db');

        // Pripravime pro kontroly seznam ID repre, channels, segments, regions
        // Reps
        $q = "SELECT id FROM users WHERE is_rep";
        $result = $db->fetchAll($q);
        $this->reps = array();
        foreach($result as $row){$this->reps[] = $row['id'];}
        // channels
        $q = "SELECT id FROM channels";
        $result = $db->fetchAll($q);
        $this->channels = array();
        foreach($result as $row){$this->channels[] = $row['id'];}
        // segments
        $q = "SELECT id FROM segments";
        $result = $db->fetchAll($q);
        $this->segments = array();
        foreach($result as $row){$this->segments[] = $row['id'];}
        // regions
        $q = "SELECT id FROM regions";
        $result = $db->fetchAll($q);
        $this->regions = array();
        foreach($result as $row){$this->regions[] = $row['id'];}


        // Pro spravnou pripravu dat pouzijeme describe tabulky users
        $this->usersDescribe = $db->describeTable('users');

        // Regexp pro kontrolu timestamp
        $this->isoTimestRegexp = '/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/';

        $this->rowCounter = 1; // Pocitadlo radku
        $this->rowRepairCounter = 2; // Pocitadlo radku k oprave (pricitame az na konci a zapocitame i header)
    }


    /**
     * Zpracuje jeden radek importu.
     * Pouziva atributy objektu pripravene v $this->importData().
     *
     * @param $row array        Pole obsahujici jeden importovany zaznam,
     *                          kazdy element musi mit spravny klic podle jmena atributu v DB.
     * @param $messages array   Pole pro predani vyslednych hlasek, kazdy prvek je pole obsahujici
     *                          0 - telo zpravy, 1 - typ zpravy, 2 - promenne pro doplneni do tela zpravy
     *                          Zpravy obvykle pouzivame pro vypis pomoci
     *                          Ibulletin_Admin_BaseControllerAbstract::infoMessage().
     * @param $dryRun bool      Spustit na bez skutecneho ukladani do DB?
     *
     * @return array            Pole obsahujici klice:
     *                              status - updated/inserted/null
     *                              badRow - Vadny radek (forRepair), jinak null.
     */
    public function importProcessRow($row, &$messages = array(), $dryRun = false)
    {
        $db =  Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet('admin.users'); // HACK texty z Admin_UsersController

        $status = null; // inserted/updated

        //echo join(' | ', $row).'<br/>';
        $rowNeedRepair = false; // Potrebuje radek opravit?

        $this->rowCounter++;

        // Preskocime prazdne radky
        if(trim(join('', $row)) == ''){
            return null;
        }


        $skipRow = false;

        // Pripravime bud ID pokud existuje, nebo null
        if(!empty($row['id'])){
            $id = (int)$row['id'];
        }
        else{
            $id = null;
        }

        //
        // Zpracujeme jednotlive atributy v radku
        //
        $data = array();
        foreach($row as $attrName => $attrVal){
            $attrVal = trim($attrVal);
            $attrName = trim(strtolower($attrName));


            // ATRIBUTY Z USERS (zname jejich datove typy a dalsi informace
            if(!empty($this->usersDescribe[$attrName])){

                // Prehled atributu z importu
                if(!isset($this->importedAttribs[$attrName])){
                    $this->importedAttribs[$attrName] = 'users';
                }

                // BOOL
                if(preg_match('/bool/', $this->usersDescribe[$attrName]['DATA_TYPE'])){
                    if($attrVal == 't' || $attrVal == 'f'){
                        $attrVal == 't' ? $attrVal = true : $attrVal = false;
                    }
                }

                // timestamp without time zone
                if(preg_match('/timestamp/', $this->usersDescribe[$attrName]['DATA_TYPE']) && !empty($attrVal)){
                    if(!preg_match($this->isoTimestRegexp, trim($attrVal))){
                        $skipRow = true;
                        $messages[] = array($texts->csv_timestamp,'info',array($this->rowCounter,$this->rowRepairCounter,$attrVal,$attrName));
                        $rowNeedRepair = true;
                    }
                }

                // Kontrola pro mandatory
                if(!$this->usersDescribe[$attrName]['NULLABLE']){
                    // BOOL - mandatory atributy maji defaults, takze prazdne zahodime (nastavime no change)
                    if(preg_match('/bool/', $this->usersDescribe[$attrName]['DATA_TYPE'])
                        && is_string($attrVal) && trim($attrVal) == '')
                    {
                        $attrVal = Users::$noChangeSymbol;
                    }
                    elseif (preg_match('/timestamp/', $this->usersDescribe[$attrName]['DATA_TYPE'])
                        && is_string($attrVal) && trim($attrVal) == '') {
                        // Pokud je datumovy atribut prazdny nastavime mu NULL
                        $attrVal = null;
                    }
                    // Mandatory pole, ktere neni bool otrimujeme
                    elseif(!is_bool($attrVal)){
                        $attrVal = trim($attrVal);
                    }
                }
                // Muzeme pouzit NULL
                else{
                    // Pokud je atribut prazdny nastavime mu NULL
                    if(is_string($attrVal) && trim($attrVal) == ''){
                        $attrVal = null;
                    }
                    // Bool - pokud ted neni NULL zboolujeme booly na true/false
                    elseif(preg_match('/bool/', $this->usersDescribe[$attrName]['DATA_TYPE'])){
                        $attrVal = (bool)$attrVal;
                    }
                }

                // Kontrola delky zadanych dat pro vse co je s retezcem
                if($this->usersDescribe[$attrName]['LENGTH'] > 0 && preg_match('/char/', $this->usersDescribe[$attrName]['DATA_TYPE'])){
                    // Zkratime a vypiseme info
                    if(mb_strlen($attrVal, 'UTF-8') > $this->usersDescribe[$attrName]['LENGTH']){
                        $attrVal = mb_substr($attrVal, 0, $this->usersDescribe[$attrName]['LENGTH'], 'UTF-8');
                        $messages[] = array($texts->csv_shortened,'info',array($this->rowCounter,$attrName,$this->usersDescribe[$attrName]['LENGTH']));
                    }
                }
            }
            // ATRIBUTY Z USERS_ANSWERS - datovy typ je vzdy varchar
            else{
                // Prehled atributu z importu
                if(!isset($this->importedAttribs[$attrName])){
                    $this->importedAttribs[$attrName] = 'usersAttribs';
                }

                // Pokud je atribut prazdny nastavime mu NULL
                if(trim($attrVal) == ''){
                    $attrVal = null;
                }
            }


            // Pridame k zapisu do DB, pokud pole nema byt ponechano bezezmeny
            if(trim($attrVal) !== Users::$noChangeSymbol){ // No change podminka
                $data[$attrName] = $attrVal;
            }
        }

        // Data patrici do externich tabulek
        $externalAttribs = array();

        // Specialni kontroly pro konkretni policka
        // GENDER
        if(!empty($data['gender'])){
            $data['gender'] = mb_substr(trim($data['gender']), 0, 1, 'UTF-8');
            if(in_array($data['gender'], array('f', 'z', 'ž'))){
                $data['gender'] = 'f';
            }
            elseif($data['gender'] != 'm'){
                // Neni mozne urcit o co se jedna, vyplinme null a vypiseme info
                $messages[] = array($texts->csv_gender,'info',array($this->rowCounter,$this->rowRepairCounter,$data['gender']));
                $data['gender'] = null;
                $rowNeedRepair = true;
            }
        }
        // CLIENT a TEST - Overime, jestli nepotrebujeme doplnit test a client
        if(!empty($data['email'])){
            // Nastavime automaticky pokud je zadano prazdne pole, nebo se jedna o noveho usera
            if((array_key_exists('client', $data) && trim($data['client']) == '') || (!array_key_exists('client', $data) && empty($id))){
                $data['client'] = Users::shouldBeMarkedAsClientByEmail($data['email']);
            }
            if((array_key_exists('test', $data) && trim($data['test']) == '') || (!array_key_exists('test', $data) && empty($id))){
                $data['test'] = Users::shouldBeMarkedAsTestByEmail($data['email']);
            }
        }
        // TARGET - pokud je nastaven na null, nenastavujeme jej (spravne by mel v DB byt mandatory)
        if(array_key_exists('target', $data) && $data['target'] === null){
            unset($data['target']);
        }
        // SELFREGISTERED - pokud je nastaven na null, nenastavujeme jej (spravne by mel v DB byt mandatory)
        if(array_key_exists('selfregistered', $data) && $data['selfregistered'] === null){
            unset($data['selfregistered']);
        }
        // ADDED - pokud je nastaven na null, nenastavujeme jej (spravne by mel v DB byt mandatory)
        if(array_key_exists('added', $data) && $data['added'] === null){
            unset($data['added']);
        }
        // REGISTERED - pokud je nastaven na null, nenastavujeme jej (spravne by mel v DB byt mandatory)
        if(array_key_exists('registered', $data) && $data['registered'] === null){
            unset($data['registered']);
        }
        // REPREZENTANT - nove repy musime pridat, nastavene musime zkontrolovat
        if(isset($data['is_rep']) && $data['is_rep'] && $id){
            $this->reps[] = $id;
        }
        // CHANNEL
        if(!empty($data['channel_id']) && !in_array($data['channel_id'], $this->channels)){
            $messages[] = array($texts->csv_channel,'info',array($this->rowCounter,$this->rowRepairCounter,$data['channel_id']));
            $rowNeedRepair = true;
            $data['channel_id'] = null;
        }
        // SEGMENT
        if(!empty($data['segment_id']) && !in_array($data['segment_id'], $this->segments)){
            $messages[] = array($texts->csv_segment,'info',array($this->rowCounter,$this->rowRepairCounter,$data['segment_id']));
            $rowNeedRepair = true;
            $data['segment_id'] = null;
        }
        // REGION
        if(!empty($data['region_id']) && !in_array($data['region_id'], $this->regions)){
            $messages[] = array($texts->csv_region,'info',array($this->rowCounter,$this->rowRepairCounter,$data['region_id']));
            $rowNeedRepair = true;
            $data['region_id'] = null;
        }
        // Jeden reprezentant
        if(!empty($data['rep']) && !in_array($data['rep'], $this->reps)){
            $messages[] = array($texts->csv_rep,'info',array($this->rowCounter,$this->rowRepairCounter,$data['rep']));
            $rowNeedRepair = true;
            $data['rep'] = null;
        }
        elseif(!empty($data['rep'])){
            // Zapiseme po zapisu radku jako externi data
            $externalAttribs['reps'] = array($data['rep']);
            $data['rep'] = null;
        }
        // Vice repre najednou
        if(array_key_exists('reps', $data)){
            $foo = preg_replace('/\s+/', '', $data['reps']);
            if(is_array($data['reps'])){
                $data['reps'] = $data['reps'] + explode(',', $foo);
            }
            else{
                $data['reps'] = explode(',', $foo);
            }

            foreach($data['reps'] as $key => $rep){
                //print_r($externalAttribs['reps']);
                if(empty($rep)){
                    unset($data['reps'][$key]);
                    continue;
                }
                if(!in_array($rep, $this->reps)){
                    $messages[] = array($texts->csv_rep_missing,'info',array($this->rowCounter,$this->rowRepairCounter,$rep));
                    unset($data['reps'][$key]);
                    $rowNeedRepair = true;
                }
            }
        }


        // Zapiseme data
        if(!$skipRow){
            try{
                //echo 'ID:'.$id.' - '.join(' | ', $data).'<br/>';
                //var_dump($data);

                // ZAPISUJEME (neprovadime merge existujicich uzivatelu)
                $method = null;

                if ($this->importContactOrigin) {
                    $contact_origin = $this->importContactOrigin;
                } else {
                    $contact_origin = "";
                }

				if (!$id && array_key_exists('email', $data)) {
					$mergeUsers = true;
				} else {
                	$mergeUsers = false;
				}

				$newId = Users::updateUser($id, $data, $mergeUsers, array('email'), false, $method, $dryRun, $contact_origin);

                // Pocitani vlozenych a updatovanych uzivatelu (neni presne, nepocita za vlozene ty, kteri meli neexistujici ID)
                if($method == 'insert'){ // Insert
                    $status = 'inserted';
                }
                else{ // Update
                    $status = 'updated';
                }

                //
                // Zapisy do dalsich externich tabulek
                //
                // REPREZENTANTI
                /*
                if(!empty($externalAttribs['reps'])){
                    // Zkontrolujeme existenci repu
                    foreach($externalAttribs['reps'] as $key => $rep){
                        //print_r($externalAttribs['reps']);
                        if(empty($rep)){
                            unset($externalAttribs['reps'][$key]);
                            continue;
                        }
                        if(!in_array($rep, $this->reps)){
                            $messages[] = array("<b>Řádek: $this->rowCounter($this->rowRepairCounter)</b> - reprezentant ('{$rep}') nebyl mezi existujícími reprezentanty nalezen, reprezentant byl vynechan.");
                            unset($externalAttribs['reps'][$key]);
                            $rowNeedRepair = true;
                        }
                    }
                    // Zapiseme
                    $insertedReps = Users::writeReps($newId, $externalAttribs['reps']);
                    // Zkontrolujeme, zda se vlozili vsichni
                    $subtract = array_diff($externalAttribs['reps'], $insertedReps);
                    if(!empty($subtract)){
                        $messages[] = array("<b>Řádek: $this->rowCounter($this->rowRepairCounter)</b> - reprezentanty ('".join(', ', $subtract)."') se z neznameho duvodu nepodarilo zapsat.");
                        $rowNeedRepair = true;
                    }
                }
                */
            }
            catch(Users_Exception $e){
                $message = $e->getMessage();
                // V pripade unique violation vytahneme jen kus zpravy
                $m = array();
                if(preg_match('/(.*)Puvodni vyjimka:.*?Unique violation:.*?: (.*?".*?")/s', $message, $m)){
                    $message = $m[1].' ('.$m[2].')';

                    // Pridame k ID kolidujiciho uzivatele, pokud bylo predano ve vyjimce
                    if(!empty($e->data) && is_array($e->data)){
                        $row['id'] = array_shift($e->data);
                    }
                }

                $messages[] = array($texts->csv_exception, 'warning', array($this->rowCounter, $this->rowRepairCounter, $message));
                $rowNeedRepair = true;
            }
        }



        // Dame do pole radky, ktere je treba opravit
        if($rowNeedRepair){
            $this->rowRepairCounter ++;
            // Pokud zname ID pridame ho
            if(!empty($newId)){
                $row['id'] = $newId;
            }
            return array('badRow' => $row, 'status' => $status);
        }

        return array('badRow' => null, 'status' => $status);
    }



    /**
     * Vrati pole uzivatelu zadaneho reprezentanta.
     *
     * @param int   $repId Id repa
     */
    public static function getRepUsers($repId)
    {
        $db = Zend_Registry::get('db');

        $sel= $db->select()->from(array('ur' => 'users_reps'), array('user_id'))
           ->joinLeft(array('u'=>'users'),'u.id=ur.user_id',array('name'=>'u.name','surname'=>'u.surname'))
           ->where('ur.repre_id = '.(int)$repId);

        $rows = $db->fetchAll($sel);

        $users = array();
        foreach($rows as $row){
            $users[$row['user_id']] = $row['surname'].' '.$row['name'];

        }
        return $users;
    }


    /**
     * Metoda nastavi aktulani datum do posledni zmeny dat uzivatele
     * @param ing $uid ID uzivatele
     */
    public static function setLastChanged($uid) {

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('db');

        $where = $db->quoteInto('id = ?', $uid);

        return $db->update('users', array('last_changed'=> Zend_Date::now()->getIso()), $where);
    }


	/**
	 * Metoda nastavi aktulani datum do sloupce last_replicated
	 * @param ing $uid ID uzivatele
	 */
	public static function setLastReplicated($uid) {

		/* @var $db Zend_Db_Adapter_Abstract */
		$db = Zend_Registry::get('db');

		$where = $db->quoteInto('id = ?', $uid);

		return $db->update('users', array('last_replicated'=> 'now'), $where);
	}


	public static function AnonymizeUser($user) {

		/** @var Zend_Config_Ini $config */
		$config = Zend_Registry::get('config');

		/** @var Zend_Db_Adapter_Abstract $db */
		$db = Zend_Registry::get('db');

		$usersAttribs = array();

		if (isset($config->gdpr->anonymize->users_attribs) && !empty($config->gdpr->anonymize->users_attribs)) {
			$usersAttribs = explode(',',$config->gdpr->anonymize->users_attribs);
		}

		$anonymizeAttribs = array_merge(Users::$_anonymizeAttribs, $usersAttribs);


		foreach ($anonymizeAttribs as $a) {

			$validEmail = new Zend_Validate_EmailAddress();
			$validDigits = new Zend_Validate_Digits();

			if ($validEmail->isValid($user[$a])) {

				$user[$a] = $user['id'].'@'. $user['id'] .'.cz';

			} else if ($validDigits->isValid($user[$a])) {

				$user[$a] = 0;

			} else {

				$user[$a] = 'anonym'.$user['id'];

			}

		}

		$user['anonymized'] = 'now';
		$user['deleted'] = 'now';
		$user['send_emails'] = false;

		Users::updateUser($user['id'],$user,false);
	}

}
