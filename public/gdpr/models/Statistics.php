<?php
/**
 *  obecna vyjimka
 */
class StatisticsException extends Exception {}

/**
 *  Třída pro získávání statistik.
 *
 *  @author Tomas Ovesny
 */
class Statistics {

    /**
     * instance statistik
     * @var Statistics
     */
    private static $instance = null;

    /**
     * Jestli se do statistik budou započítávat i testovací uživatelé nebo ne.
     * @var boolean
     */
    protected static $_testUsers = 'false';

    /**
     * Do statistik se zapocitavaji uzivatele, co jsou v cilove skupine (DB-users.target = true)
     * @var boolean
     */
    protected static $_targetUsers = 'true';

    /**
     * Do statistik se zapocitavaji uzivatele, co jsou ve skupine client (DB-users.client = false)
     * @var boolean
     */
    protected static $_clientUsers = 'false';

    /**
     * Na kolik se zaokrouhli vysledne cislo ci procento
     * @var int
     */
    public static $round = 2;

    /**
     * Pocet target kontaktu
     *
     * @var int
     */
    private static $_targetCount = 100;

    /**
     * Zapocitavat bulletiny, co jsou platne
     * @var boolean
     */
    protected static $_onlyValidBulletins = 'true';



    /**
     * nacte spolecna data
     */
    private function __construct() {

        $config = Zend_Registry::get('config');

        // zjisti target kontaktu
        if (!empty($config->stats->target)) {
            self::$_targetCount = $config->stats->target;
        }

        // nastavit zaokrouhlovani
        if (!empty($config->stats->round)) {
            self::$round = $config->stats->round;
        }

        // pouze platne bulletiny
        if (!empty($config->stats->only_valid)) {
            self::$_onlyValidBulletins = $config->stats->only_valid;
        }

        // nastaveni globalniho zaokrouhlovani pres funkce bc_xxxxx
        bcscale(self::$round);

        // target
        self::$_targetCount = $config->stats->target;

    }

    /**
     * vrati instanci statistik
     * @return Statistics instance tridy
     */
    public static function getInstance(){
      if (self::$instance === null){
        self::$instance = new Statistics;
      }
      return self::$instance;
    }

    /**
     * vrati velikost cilove skupiny (target)
     * @return int pocet kontaktu z cilove skupiny
     */
    public static function getTargetCount() {
        return self::$_targetCount;
    }

    /**
     * vrati celkovy pocet bulletinu v DB
     * @return int pocet bulletinu
     */
    public static function getBulletinsCount() {

        $bulletins = 0;
        $db = Zend_Registry::get('db');

        $_select = $db->select()
                     ->from('bulletins_v', array(new Zend_Db_Expr('count(id) AS count')));

        //jsou-li nastaveny parametry, pridame do selectu
        if ( !empty($params) ) {
            foreach ( $params as $param ) {
                $_select->where(new Zend_Db_Expr($param));
            }
        }

        // ziskame kontakty
        $bulletins = $db->fetchOne($_select);

        return $bulletins;

    }


     /**
     * vrati tabulku bulletins_v
     * @param int $limit pocet bulletinu, 0 = vsechny
     * @return arr
     */
    public static function getBulletins_v($limit = 0) {
        $bulletins = array();
//        $bulletins = new Zend_Db_Table();
        $db = Zend_Registry::get('db');

        if ($limit == 0) {
            $_select = 'select * from bulletins_v order by valid_from DESC';
        } else {
            $_select = 'select * from bulletins_v order by valid_from DESC LIMIT '.$limit;
        }

        // ziskame bulletiny
        $bulletins = $db->fetchAll($_select);
        return $bulletins;
    }

    /** 14.12.09 RV
     * vrati celkovy pocet kontaktu v DB
     * @param array $params - modifikatory vyberu pro upresneni typu kontaktu (odhlaseny, neobesilany)
     * @return int pocet kontaktu
     */
    public static function getContactsCount($params = array()) {

        $contacts = 0;
        $db = Zend_Registry::get('db');

//        $_select = $db->select()
//                     ->from('contacts_v', array(new Zend_Db_Expr('count(*) AS count')));

        $_select = $db->select()
                     ->from('users_vf', array(new Zend_Db_Expr('count(*) AS count')))
                     ->where('send_emails = true');

        //jsou-li nastaveny parametry, pridame do selectu
        //26.10.09 RV nefunguje, where se prepisuje.....
        if ( !empty($params) ) {
            foreach ( $params as $param ) {
                $_select->where(new Zend_Db_Expr($param));
            }
        }

        // ziskame kontakty
        $contacts = $db->fetchOne($_select);

        return $contacts;

    }


    /** 18.12.09 RV
     * vrati kontakty v DB
     * @param array $params - modifikatory vyberu pro upresneni typu kontaktu (odhlaseny, neobesilany)
     * @return int pocet kontaktu, nebo pole u_id
     */
    public static function getContacts($params = array(),$type='count') {

        $contacts = 0;
        $db = Zend_Registry::get('db');

        $_select = $db->select()
                     ->from('users_vf', 'id');

        //jsou-li nastaveny parametry, pridame do selectu

        if ( !empty($params) ) {
            foreach ( $params as $param ) {
                $_select->where(new Zend_Db_Expr($param));
            }
        }

        // ziskame kontakty
//        echo $_select;

        $contacts = $db->fetchCol($_select);
        if($type == 'count'){
            return count($contacts);}
        else                         {
            return $contacts;         }

    }

    /**
     * vrati pocet aktivnich kontaktu
     * @return int $contacts pocet aktivnich kontaktu
     */
    public static function getActiveContactsCount() {

        $params = array("deleted IS NULL AND unsubscribed IS NULL AND bad_addr IS FALSE");
        $contacts = self::getContacts($params, 'count');
        return $contacts;
    }

    /**
     * vrati pocet neaktivnich kontaktu
     * @return int $contacts pocet neaktivnich kontaktu
     */
    public static function getNoActiveContactsCount() {

        $params = array("deleted IS NOT NULL OR unsubscribed IS NOT NULL OR bad_addr IS TRUE");
        $contacts = self::getContacts($params, 'count');
        return $contacts;
    }

    /**
     * vrati pocet platný kontaktu pro emailing
     * @return int $contacts pocet aktivnich kontactu
     */
    public static function getActiveContactsForEmailingCount() {

        $params = array("send_emails IS true AND deleted IS NULL AND email IS NOT NULL AND trim(both from email)!='' and bad_addr IS FALSE and unsubscribed IS NULL");
        $contacts = self::getContacts($params, 'count');
        return $contacts;
    }

	/**
	 * vrati pocet platných kontaktu se souhlasem GDPR
	 * @return int $contacts pocet kontaktu
	 */
	public static function getActiveGDPRContactsCount() {
		$params = array("deleted IS NULL AND unsubscribed IS NULL AND bad_addr IS FALSE AND users_vf.id IN (SELECT ua.user_id FROM users_attribs AS ua WHERE (ua.name='gdpr_project' AND val='1'))");
		$contacts = self::getContacts($params, 'count');
		return $contacts;
	}

	/**
	 * vrati pocet platných kontaktu s globalním souhlasem GDPR
	 * @return int $contacts pocet kontaktu
	 */
	public static function getActiveGDPRGlobalContactsCount() {
		$params = array("deleted IS NULL AND unsubscribed IS NULL AND bad_addr IS FALSE AND users_vf.id IN (SELECT ua.user_id FROM users_attribs AS ua WHERE (ua.name='gdpr_global' AND val='1'))");
		$contacts = self::getContacts($params, 'count');
		return $contacts;
	}

    public static function getActiveContacts($type='u_id') {

        $contacts = 0;
        $db = Zend_Registry::get('db');

        $_select = $db->select()
                     ->from('contacts_active_v', 'id');
        $contacts = $db->fetchCol($_select);

        return $contacts;
    }

   /**
    * pocet uzivatelu (kontakty, ctenari) pro vsechny bulletiny
    * @param string tabulka default: contacts_v (kontakty)
    * @param valid_only omezit na platne bulletiny? default: FALSE
    * @return int pocet uzivatelu
    */
    private static function _getCountForAllBulletins($table = 'contacts_v', $valid_only = FALSE) {
        $db = Zend_Registry::get('db');
        $q = 'select count(*) from ('.
        'SELECT distinct p.user_id, p.bulletin_id FROM
        page_views_v AS p WHERE p.user_id in (select id from '.$table.') and '.
        ($valid_only ?
                "p.bulletin_id IN (SELECT b.id FROM bulletins_v AS b)" :
                "p.bulletin_id IN (SELECT b.id FROM bulletins AS b WHERE deleted IS NULL)").
        ') as foo';

        $contactsCount = $db->fetchOne($q);
        return $contactsCount;
    }

    /**
     * Pocet aktivnich kontaktu pro konkretni bulletin.
     * (Uzivatele nesmazani, kteri existovali v danem vydani (added pred koncem vydani),
     * s nastavenym unsubsribed na NULL)
     *
     * @param int $bulletin cislo bulletinu
     * @return int pocet kontaktu pro dany bulletin
     */
    public static function getContactsCountForBulletin($bulletin = 0) {
        $db = Zend_Registry::get('db');
        // Udelame select podobny contacts_active_v, ten nelze pouzit, protoze
        // nezohlednuje datum smazani uzivatele.
        $select = $db->select()
                     ->from(array('u' => 'users_vf'), array(new Zend_Db_Expr('count(DISTINCT u.id) AS count')))
                     ->join(array('b' => 'bulletins_v'), 'b.id = '.(int)$bulletin, array())
                     ->where("u.unsubscribed IS NULL") // Podminky z contacts_active_v
                     ->where("u.added <= b.valid_to")
                     ->where("u.deleted > b.valid_to OR u.deleted IS NULL")
                     ->where("bad_addr IS false");

        $contactsCount = $db->fetchOne($select);

        return $contactsCount;
    }

     /**
     * Pocet aktivnich kontaktu pro emailing pro konkretni bulletin.
     *
     * @param int $bulletin cislo bulletinu
     * @return int pocet kontaktu pro dany bulletin
     */
    public static function getContactsCountForEmailingForBulletin($bulletin = 0) {
        $db = Zend_Registry::get('db');
        // Udelame select podobny contacts_active_v, ten nelze pouzit, protoze
        // nezohlednuje datum smazani uzivatele.
        $select = $db->select()
                     ->from(array('u' => 'users_vf'), array(new Zend_Db_Expr('count(DISTINCT u.id) AS count')))
                     ->join(array('b' => 'bulletins_v'), 'b.id = '.(int)$bulletin, array())
                     ->where("u.send_emails IS true AND u.deleted IS NULL AND u.email IS NOT NULL AND trim(both from u.email)!='' and u.bad_addr IS false and u.unsubscribed IS NULL") // Podminky z contacts_active_v
                     ->where("u.added <= b.valid_to")
                     ->where("u.deleted > b.valid_to OR u.deleted IS NULL");

        $contactsCount = $db->fetchOne($select);

        return $contactsCount;
    }

    /**
    * pocet kontaktu pro vsechny bulletiny
    * @param bool pouze platne bulletiny?
    * @return int pocet kontaktu
    */
    public static function getContactsCountForAllBulletins($valid_only = FALSE) {
        return self::_getCountForAllBulletins('users_vf', $valid_only);
    }

    /**
     * vrati pocet odhlasenych kontaktu
     * @return int pocet odhlasenych kontaktu
     */
    public static function getDeregistredContactsCount($type='count') {

        $params = array("unsubscribed IS NOT NULL");
        $contacts = self::getContacts($params, $type);
        return $contacts;
    }

    /**
     * vrati pocet odstranenych kontaktu
     * @return int pocet odstranenych kontaktu
     */
    public static function getRemovedContactsCount($type='count') {

        $params = array("deleted IS NOT NULL AND unsubscribed IS NULL");
        $contacts = self::getContacts($params, $type);
        return $contacts;
    }

    /**
     * vrati pocet nefuncknich kontaktu
     * @return int pocet nefunkcnich kontaktu
     */
    public static function getInvalidContactsCount($type='count') {

        $params = array("deleted IS NULL AND unsubscribed IS NULL AND bad_addr IS true");
        $contacts = self::getContacts($params, $type);
        return $contacts;
    }


    /**
     * vrati pocet kontaktu bez emailu
     * @return int pocet kontaktu
     */
    public static function getNoEmailContactsCount() {
        $params = array("bad_addr IS false and deleted IS NULL and (send_emails IS false OR email IS NULL OR trim(both from email)='')");
        $contacts = self::getContacts($params);
        return $contacts;
    }

    /**
     * vrati pocet spatnych kontaktu
     * @return int pocet spatnych kontaktu
     */
    public static function getbadContactsCount($type='count') {

        $contacts = 0;
        $db = Zend_Registry::get('db');


        $_select = $db->select()
//                     ->from('users_vf', array(new Zend_Db_Expr('count(*) AS count')))
                     ->from('users_vf', 'id')
                     ->where('bad_addr = true')
                     ->where("email is not null and trim(both from email)!=''")
                     ->where('deleted IS NULL');
//                         ->where('c.send_emails = true and c.deleted IS NULL and c.email IS NOT NULL and c.reset_bad_addr IS NULL and exists(select ue.user_id from users_emails ue where ue.user_id = c.id and ue.status IS NOT NULL)');

        $contacts = $db->fetchCol($_select);

        if($type=='count')
            return count($contacts);
        else
            return $contacts;
    }


    /**
     * provadi select, ktery vrati uzivatele a procenta pro nejakou oblast (segment, region, kanaly, reps)
     * @param int pocet uzivatelu kvuli vypoctu procentualni slozky
     * @param array parametry (tabulka, primary key)
     * @return array kontakty
     */
    private static function _getContactsPerArea($contactsCount = 0, $params = array()) {
        if ( $contactsCount > 0 && !empty($params) ) {
            $db = Zend_Registry::get('db');
            $_select = $db->select()
                    ->from( array('s' => $params['table']), array('s.name', 'count(DISTINCT u.id) AS count', 'round((count(DISTINCT u.id)::float / '.$contactsCount.') * 100) AS percents'))
                    ->joinLeft( array('u'=>'contacts_v'), 'u.'.$params['primary_key'].'= s.id', array() )
                    ->group(array('s.id', 's.name'))
            ;
//            echo $_select;
            $result = $db->fetchAssoc($_select);
        }

        return $result;
    }



    /**
     * provadi select, ktery vrati ctenare a procenta pro nejakou oblast (segment, region, kanaly, reps)
     * @param int pocet ctenaru kvuli vypoctu procentualni slozky
     * @param array parametry (table, primary key)
     * @return array tabulka(oblast, pocet ctenaru, procento)
     */
    public static function getReadersPerArea($contactsCount = 0, $params = array()) {
        //$contactsCount = self::getRedersCount;
        if ( $contactsCount > 0 && !empty($params) ) {
            $db = Zend_Registry::get('db');
            $_select = $db->select()
                    ->from( array('s' => $params['table']),
                        array('s.name', 'count(DISTINCT u.id) AS count',
                        'round((count(DISTINCT u.id)::float / '.$contactsCount.') * 100) AS percents')
                    )
                    ->group(array('s.id', 's.name'))
            ;
            // Filtrovani podle parametru
            if($params['primary_key'] != 'reps'){
                $_select->joinLeft( array('u'=>'readers_starttime_v'), 'u.'.$params['primary_key'].'= s.id', array());
            }
            else{
                $_select
                    ->joinLeft(array('ur' => 'users_reps'), 'ur.repre_id = s.id', array())
                    ->joinLeft(array('u'=>'readers_starttime_v'), 'u.'.$params['primary_key'].'= ur.user_id', array());
            }

            $result = $db->fetchAssoc($_select);
        }

        return $result;
    }



    /**
     * provadi select, ktery vrati ctenare a procenta pro nejakou oblast (segment, region, kanaly, reps)
     * @param int pocet ctenaru kvuli vypoctu procentualni slozky
     * @param array parametry (table, primary key)
     * @return array kontakty
     */
    private static function _getReadersPerArea($contactsCount = 0, $params = array()) {

        if ( $contactsCount > 0 && !empty($params) ) {
            $db = Zend_Registry::get('db');
            $_select = $db->select()
                    ->from( array('s' => $params['table']), array('s.name', 'count(DISTINCT u.id) AS count', 'round((count(DISTINCT u.id)::float / '.$contactsCount.') * 100) AS percents'))
                    ->joinLeft( array('u'=>'readers_starttime_v'), 'u.'.$params['primary_key'].'= s.id', array() )
                    ->group(array('s.id', 's.name'))
            ;
            $result = $db->fetchAssoc($_select);
        }

        return $result;
    }

    /**
     * vrati kontakty a procento podle segmentu
     * @param int pocet uzivatelu
     * @return array kontakty
     */
    public static function getContactsPerSegment($contactsCount = 0) {

        $params = array(
            'table'=>'segments',
            'primary_key' => 'segment_id'
        );
        $result = self::_getContactsPerArea($contactsCount, $params);
        return $result;
    }


    /**
     * vrati ctenare a procento podle segmentu
     * @param int pocet uzivatelu
     * @return array kontakty
     */
    public static function getReadersPerSegment($contactsCount = 0) {

        $params = array(
            'table'=>'segments',
            'primary_key' => 'segment_id'
        );
        $result = self::_getReadersPerArea($contactsCount, $params);
        return $result;
    }


    /**
     * vrati kontakty a procento podle kanalu
     * @param int pocet uzivatelu
     * @return array kontakty
     */
    public static function getContactsPerChannel($contactsCount = 0) {

        $params = array(
            'table'=>'channels',
            'primary_key' => 'channel_id'
        );
        $result = self::_getContactsPerArea($contactsCount, $params);
        return $result;
    }

    /**
     * vrati kontakty a procento podle regionu
     * @param int pocet uzivatelu
     * @return array kontakty
     */
    public static function getContactsPerRegion($contactsCount = 0) {

        $params = array(
            'table'=>'regions',
            'primary_key' => 'region_id'
        );
        $result = self::_getContactsPerArea($contactsCount, $params);
        return $result;
    }

    /**
     * vrati kontakty a procento podle repu
     * @param int pocet uzivatelu
     * @return array kontakty
     */
    public static function getContactsPerRep($contactsCount = 0) {

        if ($contactsCount > 0 ) {
            $db = Zend_Registry::get('db');
            $_select = $db->select()
                    ->from( array('u' => 'users'), array(new Zend_Db_Expr('u.name || \' \' || u.surname AS name'), 'count(DISTINCT c.id) AS count', 'round((count(DISTINCT c.id)::float / '.$contactsCount.') * 100) AS percents') )
                    ->joinLeft( array('c'=>'contacts_v'), 'c.id = u.id', array() )
                    ->where('u.is_rep = true')
                    ->group(array('u.id', 'u.name', 'u.surname'))
            ;
            $result = $db->fetchAssoc($_select);
        }

        return $result;
    }


    /** 15.3.10 RV
     * vrati tabulku navstevnosti externich odkazu
     * @return array
     */

    public static function getLinksVisits($bul_id = 0) {

            $db = Zend_Registry::get('db');
            // Hledame unikatni prokliky tak, aby kazdy anomnymni proklik byl unikatni
                        $select = $db->select()
                                 ->from( array('pv' => 'page_views'), array('link_id'=>'l.id','l.name','count(distinct s.user_id) as count_users','count(distinct pv.id) as used_times'))
                                 ->join( array('s' => 'sessions'), 's.id = pv.session_id',array())
                                 ->joinLeft( array('bv' => 'bulletins_v'), 'pv.timestamp > bv.valid_from and pv.timestamp < bv.valid_to',array())
                                 ->joinLeft( array('u' => 'users_vf'), 'u.id=s.user_id',array())
                                 ->join( array('l' => 'links'),  'pv.link_id = l.id and l.foreign_url IS NOT NULL', array())
                                 ->where('(target) or s.user_id is null')
                                 ->group(array('l.id','l.name'));


                        if(!empty($bul_id)) {
                            $select->where('bv.id = ?', $bul_id);
                        }

        return $db->fetchAll($select);
    }


    /**
     * Vrati tabulku navstevnosti resources
     * @return array
     */
    public static function getResourcesVisits() {

         $db = Zend_Registry::get('db');
         $select = $db->select()->from(array('s'=>'sessions'),array('count(distinct s.user_id) as unique_clicks','count(distinct s.id) as total_clicks'))
                 ->join(array('rs'=>'resources_stats'),'s.id = rs.session_id',array())
                 ->join(array('r'=>'resources'),'rs.resource_id = r.id',array('id','name'))
                 ->joinLeft(array('u'=>'users_vf'),'s.user_id = u.id',array())
                 ->where('s.user_id IS null OR u.target IS true')
                 ->group(array('r.id','r.name'))
                 ->order('r.id');

         return $db->fetchAll($select);
    }


    /** 21.8.11 AL
    * vrati tabulku navstevnosti externich odkazu rozdelenych do oblasti
    * @param array pole oblasti, obsahuje nazev a pole cest xpath, klice [title][xpath]
    * @return array pole obsahuje klice [title][users][clicks]
    */

    public static function getLinksAreaVisits($areas, $bul_id = 0) {

        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet('admin.stats');

        $res = array();
        $xpath_all = array();

        // specificke dotazy, spracovane pres switch v cyklu
        $areas['mail']['title'] = $texts->links->area_email;
        $areas['other']['title'] = $texts->links->area_other;

        // dotaz na pocet unikatnich uzivatelu a pocty kliku v kazde oblasti
        $select = new Zend_DB_Select($db);
        $select->from(array('pv' => 'page_views'), array(
                'users' => 'count(distinct s.user_id)',
                'clicks' => 'count(distinct pv.id)'))
            ->join(array('s' => 'sessions'), 's.id = pv.session_id', array())
            ->join(array('u' => 'users_vf'), 'u.id = s.user_id', array());


        // pro kazdou oblast se udela 1 dotaz, cesty v xpath a xpath_all se zORuji v regularu
        foreach ($areas as $key => $area) {
            // omezeni na bulletin
            $select->where(empty($bul_id) ? 'pv.bulletin_id IN (SELECT id FROM bulletins_v)' : 'pv.bulletin_id = ?', (int)$bul_id);

            switch ($key) {
            case 'mail' :
                $select->where("pv.link_xpath ~* ?", "^mail_link:");
                break;
            case 'other' :
                // doplnek ke vsem ostatnim xpath, emailu a NULL
                $select->where("pv.link_xpath IS NOT NULL")
                    ->where("pv.link_xpath !~* ?", "^mail_link:");
                if (!empty($xpath_all)) {
                    $select->where("pv.link_xpath !~* ?", '^('.implode('|',$xpath_all).')(\/|$)');
                }
                break;
            default:
                // pattern pro postgre regular potrebuje mit escapovane hranate zavorky, tecku, hash
                $xpath = preg_replace(array('/\[/','/\]/', '/\#/', '/\./'), array('\[', '\]', '\#', '\.'), $area['xpath']);
                // nastavime regular tak, aby na konci patternu nasledovalo lomitko nebo nic, jinak mame neuplny match
                $select->where("pv.link_xpath ~* ?", '^('.implode('|',$xpath).')(\/|$)');
                $xpath_all = array_merge($xpath_all, $xpath);
                break;
            }
            $res[$key] = $db->fetchRow($select);
            $res[$key]['title'] = $area['title'];
            // vycisti where pro dalsi dotazy
            $select->reset(Zend_Db_Select::WHERE);
        }

        return $res;
    }

   /** 25.8.11 AL
    * vrati tabulku navstevnosti odkazu z emailu rozdelenych podle pojmenovaneho linku
    * @return array pole obsahuje klice [title][users][clicks]
    */

    public static function getLinksMailVisits($bul_id = 0) {

        $db = Zend_Registry::get('db');

        // dotaz na pocet unikatnich uzivatelu a pocty kliku z emailu grupnutych podle casti retezce z xpath
        $select = new Zend_DB_Select($db);
        $select->from(array('pv' => 'page_views'), array(
                'title' => "substring(pv.link_xpath from '^mail_link:(.*)')",
                'users' => 'count(distinct ult.user_id)',
                'clicks' => 'count(distinct pv.id)'))
            ->join(array('ult' => 'users_links_tokens'), 'ult.id = pv.users_links_tokens_id', array())
            ->join(array('u' => 'users_vf'), 'u.id = ult.user_id', array())
            ->join(array('l' => 'links'), 'l.id = ult.link_id', array())
            ->where("pv.link_xpath ~* ?", "^mail_link:")
            // omezeni na bulletin
            ->where(empty($bul_id) ? 'l.bulletin_id IN (SELECT id FROM bulletins_v)' : 'l.bulletin_id = ?', (int)$bul_id)
            ->group('title');

        return $db->fetchAll($select);
    }

    /** 8.9.09 RV
     * vrati tabulku ctenosti clanku
     * @return array kontakty
     */

    public static function getArticlesReadTable($bul_id = 0) {

            $db = Zend_Registry::get('db');
/*            $_select = $db->select()
                    ->from( array('av' => 'articles_v'), array('*'))
                    ->where('av.bulletin_id = $bul_id')
            ;
            */
            $_select = 'select max(id) as maxid from bulletins_v';
            $result0 = $db->fetchOne($_select);
//            if(empty($bul_id)) $bul_id =  $result0;
            if(empty($bul_id))
                        $_select = 'select * from articlesall_v av';
            else
                        $_select = 'select * from articles_v av where av.bulletin_id = '.$bul_id;

//            $result = $db->fetchAssoc($_select);
            $result = $db->fetchAll($_select);


        return $result;
    }

    /** 8.9.09 RV
    * vrati tabulku ctenosti rubrik
     * @return array kontakty
     */

    public static function getCategoriesReadTable($bul_id = 0) {

            $db = Zend_Registry::get('db');
/*            $_select = $db->select()
                    ->from( array('av' => 'articles_v'), array('*'))
                    ->where('av.bulletin_id = $bul_id')
            ;
            */
            $_select = 'select max(id) as maxid from bulletins_v';
            $result0 = $db->fetchOne($_select);

            if(empty($bul_id))
                        $_select = 'select * from categoriesall_v av';
            else
                        $_select = 'select * from categories_v av where av.bulletin_id = '.$bul_id;

            $result = $db->fetchAll($_select);

        return $result;
    }

    /**
     * vrati bulletiny v urcite oblasti s pocty uzivatelu
     * @param array parametry selectu
     * @return array kontakty
     */
    private static function _getContactsPerAreaInBulletins($params = array()) {

        if ( !empty($params) ) {
           $db = Zend_Registry::get('db');
           $_select = '
                SELECT o.name AS bulletin, s.name AS '.$params['name'].', count(DISTINCT pv.user_id) as count
                FROM '.$params['table'].' AS s
                CROSS JOIN obsah_v AS o
                LEFT JOIN contacts_v AS cv ON cv.'.$params['primary_key'].' = s.id
                LEFT JOIN page_views_v AS pv ON (pv.user_id = cv.id AND pv.bulletin_id = o.id)

                GROUP BY s.name,o.name, o.id
                ORDER BY o.id'
           ;
           $result = $db->fetchAll($_select);
           return $result;
        }
    }


    /**
     * vrati bulletiny v urcite oblasti s pocty ctenaru
     * @param array parametry selectu
     * @return array tabulka(jmeno bulletinu, jmeno oblasti, pocet ctenaru)
     */
    public static function getReadersPerAreaInBulletins($params = array()) {

        if ( !empty($params) ) {
           $db = Zend_Registry::get('db');
           $_select = '
                SELECT o.name AS bulletin, s.name AS '.$params['name'].', count(DISTINCT pv.user_id) as count
                FROM '.$params['table'].' AS s
                CROSS JOIN obsah_v AS o
                LEFT JOIN readers_starttime_v AS cv ON cv.'.$params['primary_key'].' = s.id
                LEFT JOIN page_views_v AS pv ON (pv.user_id = cv.id AND pv.bulletin_id = o.id)

                GROUP BY s.name,o.name, o.id
                ORDER BY o.id'
           ;
           $result = $db->fetchAll($_select);
           return $result;
        }
    }

    /**
     * vrati bulletiny v segmentech s pocty uzivatelu
     * @return array kontakty
     */
    public static function getContactsPerSegmentInBulletins() {

        $params = array(
            'table' => 'segments',
            'name' => 'segment',
            'primary_key' => 'segment_id'
        );
        $result = self::_getContactsPerAreaInBulletins($params);
        return $result;
    }

    /**
     * vrati pole odpovidajicich user_id (pro export)
     * @param array parametry selectu
     * @return array uzivatele
     */
    public static function getUsersTable($aParams) {

//        echo "getUsersTable";

        if ( !empty($aParams) ) {
                $db = Zend_Registry::get('db');

                //           $table = $params['table'];
           $action = $aParams['what'];
           @$bulletinId = $aParams['bulletinId'];
           @$emailId = $aParams['emailId'];
           $aId = array();
           switch ($action) {
//               case 'contacts':
//                    $params = array('true');
//                    $type = 'u_id';
//                    $result = self::getContacts($params, $type);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;
//               case 'activecontacts':
//                    $_select = 'select id from users_vf WHERE deleted IS NULL and unsubscribed IS NULL AND bad_addr IS FALSE';
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                     $joinUsersDataId = "id";
//               break;
//               case 'activecontactsforemailing':
//                    $_select = "select id from users_vf WHERE send_emails IS true AND deleted IS NULL AND email IS NOT NULL AND trim(both from email)!='' and bad_addr IS false and unsubscribed IS NULL";
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;
//               case 'deregistredcontacts':
//                    $_select = 'select id from users_vf where unsubscribed IS NOT NULL';
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;
//               case 'removedcontacts':
//                    $_select = 'select id from users_vf where deleted IS NOT NULL AND unsubscribed IS NULL';
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;
//                case 'invalidcontacts':
//                    $_select = 'select id from users_vf where deleted IS NULL AND unsubscribed IS NULL AND bad_addr IS TRUE';
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;
//               case 'noemailcontacts':
//                    $_select = "select id from users_vf where bad_addr IS false AND deleted IS NULL and (send_emails IS false OR email IS NULL OR trim(both from email)='')";
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;
//               case 'inactivecontacts':
//                    $_select = "select id from users_vf WHERE deleted IS NOT NULL OR unsubscribed IS NOT NULL OR bad_addr IS TRUE";
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;
               case 'nonaddressedcontacts':
                    $_select = "select id from contacts_v where deleted IS NULL and email IS NOT NULL and trim(both from email)!='' and send_emails = false";
                    $result = $db->fetchCol($_select);
                    $aId = $result;
               break;
               case 'badcontacts':
                    $result = self::getbadContactsCount('u_id');
                    $aId = $result;
               break;

//               case 'readers':
//                    if($bulletinId > 0)
//                        $_select = "select id from readers_starttime_v where min_timestamp <= valid_to and email IS NOT NULL and trim(both from email)!='' and bulletin_id = ".$bulletinId;
//                    else
//                        $_select = "select id from readers_starttime_v where min_timestamp <= valid_to and email IS NOT NULL and trim(both from email)!=''";
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//               break;
//               case 'readers_late':
//                    if($bulletinId > 0)
//                        $_select = "select id from readers_starttime_v where min_timestamp > valid_to and email IS NOT NULL and trim(both from email)!='' and bulletin_id = ".$bulletinId;
//                    else
//                        $_select = "select id from readers_starttime_v where min_timestamp > valid_to and email IS NOT NULL and trim(both from email)!=''";
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//               break;
               case 'returnrate':
                    $_select = "select distinct b3.user_id from readers_bulletin_v b3 where b3.id = " .$bulletinId.
                    " and b3.user_id in(select distinct b2.user_id from readers_bulletin_v b2 where b2.poradi = b3.poradi - 1)";
                    $_select = "select distinct b3.id from readers_starttime_v b3 where b3.bulletin_id = " .$bulletinId. "and b3.min_timestamp <= b3.valid_to
                    and b3.id in(select distinct b2.id from readers_starttime_v b2 where b2.poradi = b3.poradi - 1 and b2.min_timestamp <= b2.valid_to and b2.email is not null and trim(both from b2.email)!='')";

                    $result = $db->fetchCol($_select);
                    $aId = $result;
               break;

//               case 'email_send':
//                    if($emailId > 0)
//                        $_select = 'select distinct esv.user_id from emails_send_v esv where esv.email_id = '.$emailId;
//                    else
//                        $_select = 'select distinct esv.user_id from emails_send_v esv where esv.bulletin_id = '.$bulletinId;
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//               break;

//               case 'email_undelivered':
//                $_where = array();
//                if (isset($aParams['from_mailer'])) {
//                    $_select = 'select distinct user_id from users_emails';
//                     $_where[] ="status = 'undelivered' or status = 'bad_addr' or status = 'personalization_err'";
//                } else {
//                    $_select = 'select distinct user_id from emails_undelivered_v';
//                }
//
//                if($bulletinId > 0)
//                    $_where[] = 'bulletin_id = '.$bulletinId;
//                if($emailId > 0)
//                    $_where[] = 'email_id = '.$emailId;
//                if (!empty($_where))
//                    $_select .= ' where '.implode($_where, ' and ');
//
//                $result = $db->fetchCol($_select);
//                $aId = $result;
//
//                if (empty($aId)) {
//                    return array();
//                }
//
//                $_select = $db->select()
//                ->from( 'users_vv', array(
//                        'id', 'name', 'surname', 'email', 'deleted', 'gender',
//                        'group'=>'skupina', 'selfregistered', 'added', 'client'=>'client', 'target',
//                        'reps', 'is rep'=>'is_rep', 'issues read'=>'pocet_vydani_cetl',
//                        'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1',
//                        'last but two issue' => 'prisel_2','last but three issue' => 'prisel_3',
//                        'last but four issue' => 'prisel_4'));
//
//                if ($aParams['from_mailer']) {
//                    $_select
//                    ->join(array('ue' => 'users_emails'),
//                                "ue.user_id = users_vv.id and (ue.status = 'undelivered' or ue.status = 'bad_addr' or ue.status = 'personalization_err')"
//                                .($emailId ? ' and ue.email_id='.$emailId : ''),
//                                array(
//                                        'sent' => 'sent',
//                                        'status' => 'status',
//                                        'bounce' => 'bounce_status'
//                                ));
//                } else {
//                    $_select
//                    ->join(array('ue' => 'emails_undelivered_v'),
//                            "ue.user_id = users_vv.id and (ue.status = 'undelivered' or ue.status = 'bad_addr' or ue.status = 'personalization_err')"
//                            .($emailId ? ' and ue.email_id='.$emailId : '')
//                            .($bulletinId ? ' and ue.bulletin_id='.$bulletinId : ''),
//                            array(
//                                    'sent' => 'sent',
//                                    'status' => 'status',
//                                    'bounce' => 'bounce_status'
//                            ));
//                }
//
//                $_select->joinLeft(array('e' => 'emails'),
//                    'e.id = ue.email_id',
//                    array(
//                        'email_name' => 'name'
//                    ))
//                ->where('users_vv.id in (?)',array($aId))
//                ->where('e.deleted IS NULL');
//
////                  echo $_select; exit;
//                $result = $db->fetchAll($_select);
//
//                $bh = Ibulletin_Bounce::getBounceHandler();
//                foreach ($result as $key=>$val) {
//                    if ($val['bounce']) {
//                        $arr = $bh->fetch_status_messages($val['bounce']);
//                        $result[$key]['bounce'] = $val['bounce'] . ' - ' . $arr[1]['title'];
//                    }
//                }
//
//                return $result;
//
//                break;

//               case 'email_read':
//                    if($emailId > 0)
//                        $_select = 'select distinct esv.user_id from emails_send_v esv where esv.read_date is not null and esv.email_id = '.$emailId;
//                    else
//                        $_select = 'select distinct esv.user_id from emails_send_v esv where esv.read_date is not null and esv.bulletin_id = '.$bulletinId;
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//               break;
//
//               case 'email_response':
//                    if($aParams['linkId'] > 0){
//                        $_select = 'select distinct erv.user_id from emails_response_v erv where timestamp <= valid_to and erv.email_id = '.$emailId.' and erv.link_id = '.$aParams['linkId'];
//                    }
//                    elseif($emailId > 0)
//                        $_select = 'select distinct erv.user_id from emails_response_v erv where timestamp <= valid_to and erv.email_id = '.$emailId;
//                    else
//                        $_select = 'select distinct erv.user_id from emails_response_v erv where timestamp <= valid_to and erv.bulletin_id = '.$bulletinId;
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;

//               case 'email_response_late':
//                    if($emailId > 0)
//                        $_select = 'select distinct erv.user_id from emails_response_v erv where
//                                        erv.user_id not in(select distinct erv.user_id from emails_response_v erv where timestamp <= valid_to and erv.email_id = '.$emailId.') and erv.email_id = '.$emailId;
//                    else
//                        $_select = 'select distinct erv.user_id from emails_response_v erv where
//                                        erv.user_id not in(select distinct erv.user_id from emails_response_v erv where timestamp <= valid_to and erv.bulletin_id = '.$bulletinId.') and erv.bulletin_id = '.$bulletinId;
//
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//                    $joinUsersDataId = "id";
//               break;

//               case 'email_read_prob':
//                    if($emailId > 0)
//                        $_select = 'select distinct user_id from (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.email_id = '.$emailId
//                                            .' union select erv.user_id from emails_response_v erv where erv.email_id = '.$emailId.') as foo';
//                    else
//                        $_select = 'select distinct user_id from (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.bulletin_id = '.$bulletinId
//                                            .' union select erv.user_id from emails_response_v erv where erv.bulletin_id = '.$bulletinId.') as foo';
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//               break;


//               case 'articlesView':
//                    $someId = $aParams['someId'];
//                    $pageId = $aParams['pageId'];
//                    if(empty($bulletinId))
//                        $_select = 'select user_id from articles_readers_v where page_id = '.$pageId;
//                    else
//                        $_select = 'select user_id from articles_readers_v where bulletin_id = '.$bulletinId.' and page_id = '.$pageId;
//                    //category_id = '.$someId;
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//               break;
//               case 'categoriesView':
//                    $someId = $aParams['someId'];
//                    if(empty($bulletinId))
//                        $_select = 'select user_id from categories_readers_v where category_id = '.$someId;
//                    else
//                        $_select = 'select user_id from categories_readers_v where bulletin_id = '.$bulletinId.' and category_id = '.$someId;
//                    $result = $db->fetchCol($_select);
//                    $aId = $result;
//               break;
           }
                if(empty($aId)) {
                    if(!is_array($aId)){
                        $aId = array();
                    }
                    $aNull = array(0);
                    $aId = $aId + $aNull;
                }
                $db = Zend_Registry::get('db');

                if ($action=='email_send' && $emailId>0) {
                    $_select = $db->select()
                                  ->from( array('u' => 'users_vv'), array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                                        'group'=>'skupina', 'selfregistered', 'added', 'client'=>'client',
                                        'target',
                                        'reps', 'is rep'=>'is_rep', 'issues read'=>'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2','last but three issue' => 'prisel_3','last but four issue' => 'prisel_4'))
                                  ->joinLeft(array('esv' => 'emails_send_v'), 'esv.user_id = u.id and esv.email_id='.$emailId, array('sent'=>'date_trunc(\'seconds\', sent)'))
                                  ->where('u.id in (?)',array($aId));

                } else {
                    $_select = $db->select()
                                  ->from( 'users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                                        'group'=>'skupina', 'selfregistered', 'added', 'client'=>'client', 'target',
                                        'reps', 'is rep'=>'is_rep', 'issues read'=>'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2','last but three issue' => 'prisel_3','last but four issue' => 'prisel_4'))
                                  ->where('users_vv.id in (?)',array($aId));
                }

//                if (in_array($action,array("activecontacts","activecontactsforemailing","noemailcontacts","inactivecontacts","deregistredcontacts","removedcontacts","contacts"))) {
//                    $_select->joinLeft(array('uvf'=>'users_vf'),'users_vv.id = uvf.id',array('uvf.send_emails','uvf.bad_addr','uvf.unsubscribed'));
//                }

            //pripojime users a users attribs v pripade ze jsme nastavili id podle ktereho se spojujeme
            if ($joinUsersDataId == "") {
           $result = $db->fetchAll($_select);
            } else {
                $result = Monitoringexports::joinUsersData($_select, null, $joinUsersDataId);
            }
        }
        return $result;
    }



     /**
     * vrati bulletiny v segmentech s pocty ctenaru
     * @return array kontakty
     */
    public static function getReadersPerSegmentInBulletins() {

        $params = array(
            'table' => 'segments',
            'name' => 'segment',
            'primary_key' => 'segment_id'
        );
        $result = self::_getReadersPerAreaInBulletins($params);
        return $result;
    }

    /**
     * vrati bulletiny dle regionu s pocty uzivatelu
     * @return array kontakty
     */
    public static function getContactsPerRegionInBulletins() {

        $params = array(
            'table' => 'regions',
            'name' => 'region',
            'primary_key' => 'region_id'
        );
        $result = self::_getContactsPerAreaInBulletins($params);
        return $result;
    }

    /**
     * vrati bulletiny dle reprezentanta s pocty uzivatelu
     * @return array kontakty
     */
    public static function getContactsPerRepInBulletins() {

       $db = Zend_Registry::get('db');
       $_select = '
            SELECT o.name AS bulletin, u.name || \' \' || u.surname AS name, count(DISTINCT pv.user_id) as count
            FROM users AS u
            CROSS JOIN obsah_v AS o
            LEFT JOIN users_reps ur ON u.id = ur.repre_id
            LEFT JOIN contacts_v AS cv ON cv.id = ur.user_id
            LEFT JOIN page_views_v AS pv ON (pv.user_id = cv.id AND pv.bulletin_id = o.id)
            WHERE u.is_rep = true
            GROUP BY u.name, u.surname, o.name, o.id
            ORDER BY o.id'
       ;
       $result = $db->fetchAll($_select);
       return $result;
    }

    /**
     * vrati pocet ctenaru
     * @return int $readers pocet ctenaru
     */
    public static function getReadersCount() {

        $readers = 0;
        $db = Zend_Registry::get('db');

        $_select = $db->select()
                     ->from('readers_starttime_v', array(new Zend_Db_Expr('count(*) AS count')));

        //jsou-li nastaveny parametry, pridame do selectu
        if ( !empty($params) ) {
            foreach ( $params as $param ) {
                $_select->where(new Zend_Db_Expr($param));
            }
        }

        // ziskame kontakty
        $readers = $db->fetchOne($_select);

        return $readers;
    }

    /**
     * vrati procento response rate (tj. kolik procent obeslaných kontaktu
     * kliklo na nejaky link v mailu (newsletter ci remindery)
     * @param int cislo bulletinu
     */
    public static function getResponseRate($bulletin = 0) {

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $bulletin = intval($bulletin);

        // vyber kontakty, ktere klikly na link z mailu
        $sel_part = "select count(distinct erv.user_id) as response
                    from emails_response_v erv where erv.bulletin_id = $bulletin" ;

        // vyber vsechny kontakty, ktere klikly na link z mailu
        $sel_whole = "select count(distinct esv.user_id) as send
                    from emails_send_v esv where esv.bulletin_id = $bulletin" ;

        try
        {
            $part = $db->fetchOne($sel_part);
            $whole = $db->fetchOne($sel_whole);
        }
        catch (Zend_Db_Exception $e)
        {
            PHC_ErrorLog::warning(
                'Nepodařilo se získat response rate. '.$e->getMessage());
        }
        if($whole > 0){
        $rate = 0;
        //$_rate = bcdiv($part, $whole);     //zaokrouhleni da 6, ne 7 jako lomitko?
        $_rate = $part/$whole;
        $rate = round(100*$_rate);
        }
        else
            $rate=false;
        return $rate;
    }

    /**
     * Vrati pole obsahujici ke kazdemu vydani z bulletins_v pocet pristupu
     * na jakoukoli povolenou landing page a entry method v platnosti daneho vydani.
     *
     * Struktura dat je vicedimenzionalni pole obsahujici jednotlive bulletiny,
     * v nich vstupni metody a u kazde vstupni metody jeji pocty navstev znamych
     * a neznamych uzivatelu (testi jsou vylouceni).
     *
     * Ridi se nastavenim v $config->general->forbidden_came_in_pages
     * @param int  $limit pocet zobrazených bulletinu
     * @return  array   Pole obsahujici statisticka data povolenych vstupnich metod.
     *                  [{bulletin}[bul_id, bul_order, bul_name, [entry_methods[{method}[unknown_users_views,
     *                  known_users_views, unknown_users_sessions, known_users_sessions, known_users]]]]]
     */
    public static function getEntryMethodsVisits($limit = 0)
    {
        $db = Zend_Registry::get('db');

        $entryMethods = Templates::getAllowedEntryMetohods();
        $sel_limit = "";
        if ($limit > 0) {
            $sel_limit = "ORDER BY valid_from DESC LIMIT ".$limit;
        }

        $sel = new Zend_Db_Select($db);
        $sel->from(
                array('b' => new Zend_Db_Expr("(SELECT id, poradi, name, valid_from, valid_to FROM bulletins_v
                    /*UNION SELECT 0, 0, '', '01-01-1900 00:00:00', (SELECT min(valid_from) FROM bulletins_v)*/ $sel_limit)")),
                array('bul_id' => 'b.id', 'bul_order' => 'b.poradi', 'bul_name' => 'name')
            )
            // Tabulka vstupnich metod pripojujici ke kazdemu vydani vsechny povolene vstupni metody
            ->join(array('em' => new Zend_Db_Expr("(SELECT entry_method FROM unnest(ARRAY['".join($entryMethods, "', '")."']) AS entry_method)")),
                '1=1', array('entry_method'))
            ->joinLeft(array('pv' => 'page_views'), 'pv.timestamp > b.valid_from AND pv.timestamp < b.valid_to AND pv.action_name = em.entry_method',
                array(
                    //'unknown_users_views' => 'count(distinct(CASE WHEN s.user_id IS null AND u.id IS null THEN pv.id ELSE null END))', // Pocet otevreni dane vstupni metody neznamymi uzivateli
                    //'known_users_views' => 'count(distinct(CASE WHEN u.id IS NOT null THEN pv.id ELSE null END))' // Pocet otevreni vstupni metody znamymi uzivateli (kteri se typicky pozdeji prihlasili)
                )
            )
            ->joinLeft(array('s' => 'sessions'), 'pv.session_id = s.id',
                array(
                    'all_sessions' => 'count(distinct(CASE WHEN u.id IS null AND s.user_id IS NOT null THEN null ELSE s.id END))', // Vsechny session bez testovacich uzivatelu
                    //'unknown_users_sessions' => 'count(distinct(CASE WHEN s.user_id IS null AND u.id IS null '.
                    //    /*kontrolujeme u.id a s.user_id kvuli filtrovani testovacich uzivatelu*/' THEN s.id ELSE null END))', // Pocet session neznamych uzivatelu, ve kterych byla metoda pouzita
                    'known_users_sessions' => 'count(distinct(CASE WHEN u.id IS NOT null THEN s.id ELSE null END))', // Pocet sessions znamych uzivatelu, ve kterych byla metoda pouzita
                    'users_by_cookies' => 'count(distinct(CASE WHEN u.id IS null AND s.user_id IS null THEN s.cookie_id ELSE null END))+count(distinct(u.id))', // Pocet vsech moznych (i neznamych) uzivatelu dle cookie, zname uzivatele spocita podle user_id (bez testovacich), takze se nemnozi, pokud ma vice cookie
                )
            )
            ->joinLeft(array('u' => 'users_vf'), 's.user_id = u.id', array('known_users' => 'count(distinct(u.id))')) // Pocet znamych uzivatelu, kteri pouzili metodu
            ->where("pv.module IS null OR pv.module = 'default'")
            ->where("pv.controller = 'index' OR pv.controller = 'register' OR s.id IS NULL")
            ->group(array('b.id', 'b.poradi', 'bul_name', 'pv.controller', 'em.entry_method'))
            ->order(array('b.poradi DESC', 'em.entry_method'));

        //echo $sel;

        $data = $db->fetchAll($sel);



        // Transformace dat tak, aby data jednotlivych metod byla v zanorenych polich
        $aggregated = array();
        $lastBulId = null;
        $dataExtended = $data;
        $dataExtended[] = array_merge(current($data), array('bul_id' => null)); // Pridame prvek na konec dat, aby probehl zapis i posledniho radku
        foreach($dataExtended as $row){
            // Novy bulletin
            if($lastBulId !== $row['bul_id']){
                if(isset($bulletinRow)){
                    // Zapiseme vysledek do zagregovaneho pole
                    $aggregated[$row['bul_id']] = $bulletinRow;
                }

                // Novy radek bulletinu
                $bulletinRow = array(
                    'id' => $row['bul_id'],
                    'order' => $row['bul_order'],
                    'name' => $row['bul_name'],
                    'entry_methods' => array()
                );
            }

            // Data jedne vstupni metody
            $method = array(
                'all_sessions' => $row['all_sessions'],
                'users_by_cookies' => $row['users_by_cookies'],
                //'unknown_users_views' => $row['unknown_users_views'],
                //'known_users_views' => $row['known_users_views'],
                //'unknown_users_sessions' => $row['unknown_users_sessions'],
                'known_users_sessions' => $row['known_users_sessions'],
                'known_users' => $row['known_users']
            );

            // Zapiseme do jednoho bulletinu
            $bulletinRow['entry_methods'][$row['entry_method']] = $method;

            $lastBulId = $row['bul_id'];
        }


        return $aggregated;
    }

    /**
     * Vrati statistiku vstupu do vydani
     *
     * @param int $bulId ID vydání
     * @return array pole obsahující statistiku
     */
    public static function getEntryMethodsChannels($bulId) {

        $db = Zend_Registry::get('db');

        $entryMethods = Templates::getAllowedEntryMetohods();

        $bulSelect = $db->select()->from('bulletins_v')->where('id = ?', $bulId);
        $bulletin = $db->fetchRow($bulSelect);

        $data = array();
        $data['wave'] = array();

        if (!$bulletin) {
            return $data;
        }

        //vlny zpracujeme zvlast
        $key = array_search('wave', $entryMethods);

        if ($key) {
            unset($entryMethods[$key]);
        }

        $subselect = $db->select()->from(array('pv' => 'page_views'),
                array('entry_method'=>'pv.action_name','controller'=>'pv.controller', 'poradi'=>new Zend_Db_Expr('row_number() OVER (partition by u.id ORDER BY pv.timestamp)')))
                ->joinLeft(array('s'=>'sessions'),'pv.session_id = s.id',null)
                ->joinLeft(array('u'=>'users_vf'),'s.user_id = u.id',array('uid'=>'u.id'))
                ->joinLeft(array('iw'=>'invitation_waves'),'pv.invitation_id = iw.id',array('invitation_id'=>'iw.id','invitation_wave'=>'iw.name','invitation_type'=>'iw.type'))
                ->where("pv.action_name in (?) OR ((pv.action_name = 'wave' OR pv.controller = 'tokenaccess') AND invitation_id IS NOT NULL)",array($entryMethods))
                ->where('pv.timestamp >= ?',$bulletin['valid_from'])
                ->where('pv.timestamp < ?',$bulletin['valid_to']);


        $select = $db->select()->from(array('entries'=>$subselect),
                array('entry_method','controller','invitation_wave','invitation_type','invitation_id','first_entry'=>new Zend_Db_Expr('sum(case when poradi = 1 and uid is not null then 1 else 0 end)'),
                    'unique_users'=>new Zend_Db_Expr('count(distinct uid)'),'all_usages'=>'count(uid)'))
                ->group(array('entry_method','invitation_id','invitation_wave','invitation_type','controller'))
                ->order('invitation_id DESC');


        $entries = $db->fetchAll($select);

        foreach ($entries as $entry) {
            if ($entry['controller'] == 'tokenaccess' || $entry['entry_method'] == 'wave') {
                 $data['wave'][$entry['invitation_id']] = $entry;
            } else {
                $data[$entry['entry_method']] = $entry;
            }

        }

        return $data;

    }

    /**
     * Vrati pole obsahujici ke kazde zvaci vlne data o poctu pristupu touto vlnou.
     * Jedna se o analogii entry channels v monitoringu indetailu s tim rozdilem, ze
     * data nejsou vazana na konkretni contenty.
     *
     * Jako pocita pocet unikatnich session, ve kterych byla vlna pouzita - jedna
     * session tedy muze byt ve vice vlnach, avsak v kazde vlne maximalne jednou.
     *
     * Pozvani jsou souctem nastaveneho cisla invitation_waves.invited s
     * poctem uzivatelu pozvanych emailem.
     *
     * @param   int     Limit pro pocet vypsanych nejnovejsich radku
     * @return  array   Pole poli obsahujici [[id, name, invited, sessions, users]]
     */
    public static function getEntryMethodsWavesVisits($bul_limit = null)
    {
        $db = Zend_Registry::get('db');

        // Number of users invited using email
        $selInvited = new Zend_Db_Select($db);
        $selInvited->from(array('ue' => 'users_emails'), array(
                        new Zend_Db_Expr('count(distinct(ue.user_id)) AS invited')
                    ))
                    ->join(array('e' => 'emails'), 'e.id = ue.email_id', array('invitation_id'))
                    ->join(array('u' => 'users_vf'), 'u.id = ue.user_id', array())
                    ->group(array('invitation_id'));

        // Find accumulated data for entries according the wave.
        // Each session is counted to every wave from which the emtry into that session
        // was done (e.g., when user enters from 2 different emails in one session)
        $sel = new Zend_Db_Select($db);
        $sel->from(array('iw' => 'invitation_waves'), array(
                'iw.id', 'iw.name', /*'iw.start', 'iw.end',*/
                new Zend_Db_Expr('(iw.invited + coalesce(inv.invited, 0)) AS invited'),
                new Zend_Db_Expr('count(distinct(u.id)) AS users'),
                new Zend_Db_Expr('count(distinct(s.id)) AS sessions')
            ))
            // bulletins_v is used for sorting along with invitation_wave start
            ->joinLeft(array('b' => 'bulletins_v'), 'b.id = iw.bulletin_id', array())
            // Numbers of users invited by emails
            ->joinLeft(array('inv' => new Zend_Db_Expr('('.$selInvited.')')), 'inv.invitation_id = iw.id', array())
            ->join(array('pv' => 'page_views'), 'pv.invitation_id = iw.id', array())
            ->join(array('s' => 'sessions'), 'pv.session_id = s.id', array())
            ->join(array('u' => 'users_vf'), 'u.id = s.user_id', array())
            ->group(array('iw.id', 'iw.name', 'iw.start', 'iw.end', 'iw.invited', 'inv.invited', 'b.poradi'))
            ->order(array('b.poradi DESC', 'iw.start DESC'));

        // Limit the number of displayed lines
        if ($bul_limit){
            $sel->limit($bul_limit);
        }

        //echo $sel; exit;
        $data = $db->fetchAll($sel);

        return $data;
    }

    /**
     * ctenari pro konkretni bulletin
     * @param array parametry do where
     * @param string $type typ dotazu (group -> pole s bulletiny a pocty ctenaru
     * @return array id ctenaru pro dany bulletin
     */
    public static function getReadersForBulletin($params = array(), $type = '') {
        $db = Zend_Registry::get('db');

        $select = $db->select();

        if (!empty($params)) {
            foreach ($params as $param) {
                $select->where(new Zend_Db_Expr($param));
            }
        }

        if ($type == 'group') {
            $select->from(array('r' => 'readers_starttime_v'), array('bulletin_id','pocet'=>new Zend_Db_Expr('count(r.id)')))
                    ->group('bulletin_id');
            return $db->fetchAssoc($select);
        } else {
            $select->distinct()
                    ->from(array('r' => 'readers_starttime_v'), 'id');
        }

        $readers = $db->fetchCol($select);
        return $readers;
    }

    /**
     * prumerna delka ctenosti - trvani session
     *
     * Tato metoda je velice nepresna.
     * Pocita kazdou session k prvnimu vydani, ktere v ni bylo navstiveno.
     * Presnejsi pocitani pres page_views neni pouzito, protoze ma take mnoho nevyhod.
     *
     * @param int $bulletin bulletin id
     * @param bool $table vrati pole z bulletiny a prumernymi casy
     * @return int | array prumerna delka session
     */
    public static function getAverageSessionLengthForBulletins($bulletin = 0,$table = false) {
        $db = Zend_Registry::get('db');

        if ($table) {
           $select = '
                SELECT
                        -- Hledame prvni bulletin navstiveny v dane session a pro ten budeme danou session zapocitavat
                        (SELECT bulletin_id from page_views WHERE session_id=s.id
                            and (bulletin_id is not null or category_id is not null or page_id is not null)
                            order by timestamp limit 1) as bul_id,
                        (EXTRACT(EPOCH FROM avg(s.session_length))*1000) as avg
                FROM users_vf as u
                JOIN sessions as s ON u.id = s.user_id AND timestamp IS NOT NULL
                GROUP BY bul_id
                ';
            return $db->fetchAssoc($select);
        }

        if ($bulletin ==0) {
            $select = $db->select()
                   ->from('sessions', array(new Zend_Db_Expr('AVG(session_length)')))
                                     ->where('user_id in (select id from users_vf)');
        }
        else {
            $select = 'select AVG(session_length) from
                            (select s.id,
                                    s.user_id,
                                    s.session_length,
                                    (select bulletin_id from page_views where session_id=s.id and (bulletin_id is not null or category_id is not null or page_id is not null) order by timestamp limit 1) as bul_id
                            from users_vf as u, sessions as s
                            where u.id=s.user_id and timestamp is not null
                            ) as foo
                        where bul_id='.$bulletin;
        }

        return $db->fetchOne($select);

    }

    /**
     * prumerna delka ctenosti - vsech navstev uzivatele
     *
     * @param int $bulletin bulletin id
     * @param bool $table vrati pole z bulletiny a prumernymi casy
     * @return int | array prumerna delka ctenosti
     */
    public static function getAverageUserLengthForBulletins($bulletin = 0, $table = false) {

        $db = Zend_Registry::get('db');

        if ($table) {
           $select = '
                SELECT
                    bul,
                   (EXTRACT(EPOCH FROM avg(session_length))*1000)as avg
                FROM (
                    SELECT s.user_id,s.bul_id as bul,sum(s.session_length) AS session_length
                    FROM users_vf as u, sessions_with_bul_id_v AS s
                    WHERE u.id=s.user_id AND timestamp is not null
                    GROUP BY s.user_id, s.bul_id
                    ) AS foo
                GROUP BY bul
                ';
           return $db->fetchAssoc($select);
        }


        if ($bulletin ==0) {
            $select = 'select AVG(session_length) from
                            (select s.user_id,
                                    s.bul_id,
                                    sum(s.session_length) as session_length
                       from users_vf as u, sessions_with_bul_id_v as s
                       where u.id=s.user_id and timestamp is not null
                            group by s.user_id, s.bul_id
                            ) as foo;';
        }
        else {
            $select = 'select AVG(session_length) from
                            (select s.user_id,
                                    s.bul_id,
                                    sum(s.session_length) as session_length
                            from users_vf as u, sessions_with_bul_id_v as s
                            where u.id=s.user_id and timestamp is not null
                            group by s.user_id, s.bul_id
                            ) as foo
                        where bul_id='.$bulletin;
        }

        return $db->fetchOne($select);

    }

   /**  8.9.2008, 23.9.09 RV
     * Vrati tabulku RR po bulletinech
     * Parametry filtruji ctenare v predchozim vydani, cimz ziskame po repech, po krajich, po....
     */
    public static function getReturnRateTable($area = "", $bulletinId=0, $type = "") {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        if($bulletinId > 0){
           $_select = "select
                   bv.poradi,
                   bv.id as bulletin_id,
                   bv.name,
                   (select count(distinct b3.id) from readers_starttime_v b3 where b3.poradi = bv.poradi and b3.min_timestamp <= b3.valid_to and b3.id
                      in(select distinct b2.id from readers_starttime_v b2 where b2.poradi = bv.poradi - 1 and b2.min_timestamp <= b2.valid_to and b2.email is not null and trim(both from b2.email)!='')) as rr,
                    (select count(distinct b2.id) from readers_starttime_v b2 where b2.poradi = bv.poradi - 1 and b2.min_timestamp <= b2.valid_to and b2.email is not null and trim(both from b2.email)!='') as readers_last,
                    (select count(distinct b3.id) from readers_starttime_v b3 where b3.poradi = bv.poradi and b3.id
                      in(select distinct b2.id from readers_starttime_v b2 where b2.poradi = bv.poradi - 1)) as rr_all,
                    (select count(distinct b2.id) from readers_starttime_v b2 where b2.poradi = bv.poradi - 1) as readers_last_all
                  from bulletins_v bv where bv.id = ".$bulletinId." order by poradi";
        }
        elseif(empty($area)) {

           $_select = "select
                   bv.poradi,
                   bv.id as bulletin_id,
                   bv.name,
                   (select count(distinct b3.id) from readers_starttime_v b3 where b3.poradi = bv.poradi and b3.min_timestamp <= b3.valid_to and b3.id
                      in(select distinct b2.id from readers_starttime_v b2 where b2.poradi = bv.poradi - 1 and b2.min_timestamp <= b2.valid_to and b2.email is not null and trim(both from b2.email)!='')) as rr,
                    (select count(distinct b2.id) from readers_starttime_v b2 where b2.poradi = bv.poradi - 1 and b2.min_timestamp <= b2.valid_to and b2.email is not null and trim(both from b2.email)!='') as readers_last,
                    (select count(distinct b3.id) from readers_starttime_v b3 where b3.poradi = bv.poradi and b3.id
                      in(select distinct b2.id from readers_starttime_v b2 where b2.poradi = bv.poradi - 1)) as rr_all,
                    (select count(distinct b2.id) from readers_starttime_v b2 where b2.poradi = bv.poradi - 1) as readers_last_all
                  from bulletins_v bv order by poradi DESC";
        }
        elseif($area=='reps')

           $_select = "select bv.name as bulletin, bv.id as bulletin_id, a.name as name, a.id,   (
                select count(distinct b3.user_id) from readers_bulletin_v b3 where b3.poradi = bv.poradi and b3.user_id
                in(select distinct b2.user_id from readers_bulletin_v b2 JOIN users_reps ur ON ur.user_id = b2.user_id where (coalesce(ur.repre_id, 0)=coalesce(a.id, 0)) and b2.poradi = bv.poradi - 1)
                ) as count,  (
                select count(distinct b2.user_id) from readers_bulletin_v b2 JOIN users_reps ur ON ur.user_id = b2.user_id where (coalesce(ur.repre_id, 0)=coalesce(a.id, 0)) and b2.poradi = bv.poradi - 1
                ) as readers_last
                from bulletins_v bv
                cross join (select id, name from reps union select null as id,'-neurceno-' as name) as a
                GROUP BY bv.id, bv.poradi, bv.name, a.name, a.id order by poradi, a.id"
           ;

        else

           $_select = "select bv.name as bulletin, bv.id as bulletin_id, a.name as name, a.id,   (
                select count(distinct b3.user_id) from readers_bulletin_v b3 where b3.poradi = bv.poradi and b3.user_id
                in(select distinct b2.user_id from readers_bulletin_v b2 where (coalesce(b2.".$area."_id, b2.".$area."_id, 0)=coalesce(a.id, a.id, 0)) and b2.poradi = bv.poradi - 1)
                ) as count,  (
                select count(distinct b2.user_id) from readers_bulletin_v b2 where (coalesce(b2.".$area."_id, b2.".$area."_id, 0)=coalesce(a.id, a.id, 0)) and b2.poradi = bv.poradi - 1
                ) as readers_last,
                ".$area."_id
                from bulletins_v bv
                cross join (select id, name from ".$area."s union select null as id,'-neurceno-' as name) as a
                left join users_vf uvf on bv.id = uvf.id group by bv.id, bv.poradi, bv.name, ".$area."_id, a.name, a.id order by poradi, a.id"
           ;

        if($bulletinId > 0)
           $result = $db->fetchRow($_select);
        else
           $result = $db->fetchAll($_select);
//       print_r($result);
        return $result;
    }



   /**  23.9.09, 22.10.09 RV
    *  Pokud dano id bulletinu, pak vrati odpovidajici zaznam, jinak
     * Vrati tabulku emailovych kampani(ResponseRate,....)   po bulletinech
     * Parametry filtruji ctenare v predchozim vydani, cimz ziskame po repech, po krajich, po....
     * @param string oblast
     * @param int cislo bulletinu (nebo id emailu pokud area = email)
     * @param int id linku (pokud area = link)
     * @param int limit (pocet bulletinu ve vypisu, 0 = vsechny, implementovano pro area=empty)
     */
    public static function getResponseRateTable($area = "", $bulletinId = 0, $linkId = 0, $limit = 0) {

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        if($bulletinId > 0 and empty($area)){
           $_select = "select bv.id as bulletin_id, bv.poradi, bv.name, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.bulletin_id = bv.id
                ) as send, (
                select count(distinct esv.user_id) from emails_undelivered_v esv
                    where esv.bulletin_id = bv.id
                ) as undelivered, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.read_date is not null and esv.bulletin_id = bv.id
                ) as read, (
                select count(distinct erv.user_id) from emails_response_v erv where timestamp <= valid_to and erv.bulletin_id = bv.id
                ) as response, (
                select count(distinct erv.user_id) from emails_response_v erv where erv.user_id not in(select distinct erv.user_id from emails_response_v erv where timestamp <= valid_to and erv.bulletin_id = bv.id) and erv.bulletin_id = bv.id
                ) as response_late, (
                select count(distinct user_id) from
                (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.bulletin_id = bv.id union
                select erv.user_id from emails_response_v erv where erv.bulletin_id = bv.id) as foo
                ) as read_prob, (
                select 0) as links, (
                select 0
                ) as proklik
                from bulletins_v bv where bv.id = ".$bulletinId
           ;
        }
        else if(empty($area)) {

           if ($limit > 0) {
                $_select = "select bv.id as bulletin_id, bv.poradi, bv.name,
                    (select count(distinct esv.user_id) from emails_send_v esv where esv.bulletin_id = bv.id) as send,
                    (select count(distinct esv.user_id) from emails_send_v esv where esv.read_date is not null and esv.bulletin_id = bv.id) as read,
                    (select count(distinct esv.user_id) from emails_undelivered_v esv where esv.bulletin_id = bv.id) as undelivered,
                    (select count(distinct erv.user_id) from emails_response_v erv
                        join (select * from bulletins_v order by valid_from DESC LIMIT $limit) as b on b.id=erv.bulletin_id where timestamp <= erv.valid_to and erv.bulletin_id = bv.id ) as response,
                   (select count(distinct erv.user_id) from emails_response_v erv
                        join (select * from bulletins_v order by valid_from DESC LIMIT $limit) as b on b.id=erv.bulletin_id where erv.bulletin_id = bv.id ) as response_all,
                    (select count(distinct erv.user_id) from emails_response_v erv
                        join (select * from bulletins_v order by valid_from DESC LIMIT $limit) as b on b.id=erv.bulletin_id where erv.user_id not in
                    (select distinct erv.user_id from emails_response_v erv
                        join (select * from bulletins_v order by valid_from DESC LIMIT $limit) as b on b.id=erv.bulletin_id where timestamp <= erv.valid_to and erv.bulletin_id = bv.id) and erv.bulletin_id = bv.id) as response_late,
                    (select count(distinct user_id) from
                    (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.bulletin_id = bv.id union select erv.user_id from emails_response_v erv join (select * from bulletins_v order by valid_from DESC LIMIT $limit) as b on b.id=erv.bulletin_id  where erv.bulletin_id = bv.id) as foo) as read_prob
                    from bulletins_v bv
                    order by bv.valid_from DESC
                    LIMIT $limit";
            } else {
                  $_select = "select bv.id as bulletin_id, bv.poradi, bv.name, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.bulletin_id = bv.id
                ) as send, (
                select count(distinct esv.user_id) from emails_send_v esv
                    where esv.read_date is not null and esv.bulletin_id = bv.id
                ) as read, (
                select count(distinct esv.user_id) from emails_undelivered_v esv
                    where esv.bulletin_id = bv.id
                ) as undelivered, (
                select count(distinct erv.user_id) from emails_response_v erv
                    where timestamp <= valid_to and erv.bulletin_id = bv.id
                ) as response,
                (select count(distinct erv.user_id) from emails_response_v erv
                    where erv.bulletin_id = bv.id
                ) as response_all,
                (select count(distinct erv.user_id) from emails_response_v erv
                    where erv.user_id not in
                        (select distinct erv.user_id from emails_response_v erv
                            where timestamp <= valid_to and erv.bulletin_id = bv.id
                        ) and erv.bulletin_id = bv.id
                ) as response_late, (
                select count(distinct user_id) from
                (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.bulletin_id = bv.id union
                select erv.user_id from emails_response_v erv where erv.bulletin_id = bv.id) as foo
                ) as read_prob, (
                select 0) as links, (
                select 0
                ) as proklik
                from bulletins_v bv
                    --join invitation_waves iw on bv.id = iw.bulletin_id
                    --join emails e on iw.id = e.invitation_id
                    order by bv.valid_from DESC ";

            }

        } elseif($area=='bulletin') {
            // emaily podle vydani (vlny)
            if ($bulletinId) {
            $_select = "select e.id as email_id, bv.id as bulletin_id, bv.poradi, e.name, (
               select date_trunc('hour',sent) from emails_send_v esv where esv.email_id=e.id group by date_trunc('hour',sent) order by count(*) desc limit 1
               ) as send_date, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.email_id = e.id
                ) as send, (
                select count(distinct esv.user_id) from emails_undelivered_v esv
                    where esv.bulletin_id = bv.id AND esv.email_id = e.id
                ) as undelivered, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.read_date is not null and esv.email_id = e.id
                ) as read, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.read_date is not null
                    and esv.email_id = e.id AND esv.is_online IS NOT null AND esv.is_online
                ) as read_online, (
                select count(distinct erv.user_id) from emails_response_v erv where timestamp <= valid_to and erv.email_id = e.id
                ) as response, (
                    select count(distinct erv.user_id) from emails_response_v erv
                    where
                        erv.user_id not in (
                        select distinct erv.user_id from emails_response_v erv
                        where timestamp <= valid_to and erv.email_id = e.id
                        ) and erv.email_id = e.id
                ) as response_late, (
                select count(distinct user_id) from
                (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.email_id = e.id
                 union
                select erv.user_id from emails_response_v erv where erv.email_id = e.id) as foo
                ) as read_prob, (
                select 0) as links, (
                select 0
                ) as proklik
                from bulletins_v bv join invitation_waves iw on bv.id = iw.bulletin_id and bv.id = ".$bulletinId." join emails e on iw.id = e.invitation_id AND e.deleted IS NULL
                AND (
                select count(distinct esv.user_id) from emails_send_v esv where esv.email_id = e.id
                ) > 0
                order by e.id";
            } else {
                // emails without wave/bulletin id
               $_select = "SELECT e.id as email_id, e.name, (
               select to_char(date_trunc('hour',sent), 'DD.MM.YYYY HH24:00') from emails_send_v esv where esv.email_id=e.id group by date_trunc('hour',sent) order by count(*) desc limit 1
               ) as send_date, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.email_id = e.id
                ) as send, (
                select count(distinct esv.user_id) from emails_undelivered_v esv
                    where esv.email_id = e.id
                ) as undelivered, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.read_date is not null and esv.email_id = e.id
                ) as read, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.read_date is not null
                    and esv.email_id = e.id AND esv.is_online IS NOT null AND esv.is_online
                ) as read_online, (
                select count(distinct erv.user_id) from emails_response_v erv where erv.email_id = e.id
                ) as response, (
                    select count(distinct erv.user_id) from emails_response_v erv
                    where erv.user_id not in (
                        select distinct erv.user_id from emails_response_v erv
                        where erv.email_id = e.id
                        ) and erv.email_id = e.id
                ) as response_late, (
                select count(distinct user_id) from
                (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.email_id = e.id union
                select erv.user_id from emails_response_v erv where erv.email_id = e.id) as foo
                ) as read_prob, (
                select 0) as links, (
                select 0
                ) as proklik
                FROM emails e WHERE e.deleted IS NULL AND e.invitation_id IS NULL AND
                (select count(distinct esv.user_id) from emails_send_v esv where esv.email_id = e.id
                ) > 0
                order by e.id DESC -- new first";
            }
            $_select .= $limit == 0 ? '' : " LIMIT $limit";
     // Predpoklad, ze odkaz se v mailu vyskytuje jen jednou
        } elseif($area=='email')
           $_select = "select ult.link_id, l.name, l.foreign_url, ult.link_id, (
                select count(distinct esv.user_id) from emails_send_v esv where esv.email_id = ".$bulletinId.") as send, (
                select count(distinct user_id) from emails_response_v erv where erv.link_id = ult.link_id
                    and timestamp <= valid_to and erv.email_id = ".$bulletinId."
                ) as proklik_un, (
                select count(*) from emails_response_v erv where erv.link_id = ult.link_id and timestamp <= valid_to and erv.email_id = ".$bulletinId ."
                ) as proklik,
                (select count(distinct user_id) from emails_response_v erv where erv.link_id = ult.link_id
                    and timestamp <= valid_to and erv.email_id = ".$bulletinId." and is_online
                ) as proklik_online_un,
                (select count(*) from emails_response_v erv where erv.link_id = ult.link_id
                    and timestamp <= valid_to and erv.email_id = ".$bulletinId."
                    and is_online
                ) as proklik_online
                from users_links_tokens ult
                join links l on ult.link_id = l.id and ult.email_id = ".$bulletinId.
                "join users on ult.user_id = users.id
                where users.test = false
                group by ult.link_id, l.name, ult.link_id, l.foreign_url, ult.email_id order by l.name";

        elseif($area=='link')

        $_select = "select substring(pv.link_xpath from '^mail_link:(.*)') as name, count(pv.id) as proklik, count(distinct(ult.user_id)) as proklik_un
            from page_views pv
            join users_links_tokens ult on ult.id = pv.users_links_tokens_id
            join users_vf uvf on uvf.id = ult.user_id
            where ult.email_id = $bulletinId AND ult.link_id = $linkId AND pv.link_xpath ~* '^mail_link:'
            group by pv.link_xpath";

        else

           $_select = "select bv.name as bulletin, a.name as name, a.id,   (
                select count(distinct esv.user_id) from emails_send_v esv where esv.bulletin_id = bv.id and esv.user_id
                in(select distinct uvf.id from users_vf uvf where (coalesce(uvf.".$area."_id, uvf.".$area."_id, 0)=coalesce(a.id, a.id, 0)) )
                ) as send, (
                select count(distinct erv.user_id) from emails_response_v erv where timestamp <= valid_to and erv.bulletin_id = bv.id  and erv.user_id
                in(select distinct uvf.id from users_vf uvf where (coalesce(uvf.".$area."_id, uvf.".$area."_id, 0)=coalesce(a.id, a.id, 0)) )
                ) as response,
                ".$area."_id
                from bulletins_v bv
                cross join (select id, name from ".$area."s union select null as id,'-neurceno-' as name) as a
                left join users_vf uvf on bv.id = uvf.id group by bv.poradi, bv.id, bv.name, ".$area."_id, a.name, a.id order by poradi, a.id"
           ;

        if($bulletinId > 0 and empty($area))
           $result = $db->fetchRow($_select);
        else
           $result = $db->fetchAll($_select);
        return $result;
    }




    /**
     *  Vrátí seznam uzivatelu, kteri videli dane video.
     *
     * @param  Vracet jen pocet? Default: false
     */
    public static function getVideoWatchers($videoId, $get_count = false)
    {
       $inst = self::getInstance();


        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        // Subselect pro ziskani seznamu users, kteri videli dane video
        $usersSeenVideo = new Zend_Db_Select($db);
        $usersSeenVideo
            ->from('players_stats', 'user_id')
            ->where('content_id = ?', $videoId)
            ;


        $select = new Zend_Db_Select($db);
        // Vracime vsechna data, nebo jen pocet?
        if(!$get_count){
            $select->from(array('u' => 'users_vf'));
        }
        else{
            $select->from(array('u' => 'users_vf'), 'COUNT(*)');
        }

        $select
                ->joinLeft(array('uvv' => 'users_vv'), 'u.id = uvv.id', array())
                ->where('u.send_emails')
                ->where("u.id in ($usersSeenVideo)")
                ;

        try
        {
            // Vracime vsechna data, nebo jen pocet?
            if(!$get_count){
                $result = $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);
            }
            else{
                $result = $db->fetchOne($select);
            }

            return $result;
        }
        catch (Zend_Db_Exception $e)
        {
            throw new StatisticsException(
                'Nepodařilo se získat seznam videoWatchers uživatelů. '.$e);
        }
    }

    /**
     * vrati Pages, které jsou přiřazeny nějakému bulletinu
     *
     * @return arr pole stránek
     * @author Jaromir Krotky
     */
    public static function getPages() {
        $pages = array();
        $db = Zend_Registry::get('db');

        $_select = 'select b.id as bulletin_id, b.name as bulletin_name, p.*
            from bulletins_pages as bp, pages as p, bulletins_v as b
            where b.id=bp.bulletin_id and bp.page_id=p.id order by b.poradi desc, p.name;';

        $pages = $db->fetchAll($_select);
        return $pages;
    }

    /**
     * Vrati seznam rozesilek ve formatu array(...)
     *
     * @param integer ID bulletinu nebo 0 pro vsechny
     * @return arr seznam emailu
     */
    private static function _getBulletinMailsSendOuts($bulletin_id=0) {
        $mails = array();
        $db = Zend_Registry::get('db');

        $_select = 'select * from emails_sent_dates_v '.
                    ($bulletin_id ? 'where bulletin_id='.$bulletin_id : '');

        $mails = $db->fetchAll($_select);
        return $mails;
    }


    /**
     *
     * Vrati data pro grafy chovani uzivatelu
     *
     * @param string typ grafu z moznosi 'daysInWeek', 'hoursInDay', 'visitsProgress'
     * @param integer pro který bulletin počítat, defaul=0=pro všechny
     * @param integer pro kterou stránku počítat, default=0=pro všechny
     * @param int       Rozsah dni pro statistiku navstevnosti za posledni dny.
     *
     * @return arr data navstevnosti
     * @author Jaromir Krotky
     */
    private static function _getBehaviorData($type, $bulletin_id=0, $page_id=0, $range = 30) {
        switch ($type) {
            case 'daysInWeek':
                $select = 'dny.den';
                $select2 = 'EXTRACT(DOW FROM datum) as den';
                $from = 'dny_v as dny';
                $group = 'EXTRACT(DOW FROM datum)';
                $join_on = 'data.den=dny.den';
                $order_by = 'dny.den';
                break;
            case 'hoursInDay':
                $select = 'hodiny.hodina';
                $select2 = 'EXTRACT(HOUR FROM datum) as hodina';
                $from = 'hodiny_v as hodiny';
                $group = 'EXTRACT(HOUR FROM datum)';
                $join_on = 'data.hodina=hodiny.hodina';
                $order_by = 'hodiny.hodina';
                break;
            case 'visitsProgress':
                $select = 'dny.datum';
                $select2 = 'date(datum) as datum';
                $b_id = ((is_numeric($bulletin_id) && $bulletin_id>0) ? $bulletin_id : false);
                $from = '(select datum from
                    (SELECT id as bulletin_id, poradi, date(valid_from)+s.a AS datum
                        FROM bulletins_v, generate_series (0,'.($range-1).',1) AS s(a)
                    ) days
                    where '.($b_id ? 'bulletin_id='.$b_id : 'poradi=1').') as dny';
                $group = 'date(datum)';
                $join_on = 'data.datum = dny.datum';
                $order_by = 'dny.datum';
                break;
        }

        $where = '';
        if (is_numeric($bulletin_id) && $bulletin_id>0) {
            $where .= ' and bulletin_id='.$bulletin_id.' ';
            if (is_numeric($page_id) && $page_id>0) {
                $where .= ' and page_id='.$page_id.' ';
            }
        }

        $data = array();
        $db = Zend_Registry::get('db');
        $_select = 'select '.$select.' as doba, coalesce(data.pocet,0) as pocet '.
                    'from '.$from.' left join ( '.
                        'select '.$select2.', count(*) as pocet '.
                        'from
                            (select min(timestamp) as datum
                            from page_views_v as pv, users_vf as u
                        where pv.user_id=u.id '.$where.'
                        group by session_id
                        ) as foo
                    group by '.$group.
                    ') as data on ('.$join_on.')
                    order by '.$order_by.';';
        $data = $db->fetchAll($_select);
        return $data;
    }

    /**
     * vrati graf chování uživatelů ve dnech v týdnu, hodinách ve dne, nebo ve dnech po spuštění
     *
     * @param string typ grafu z moznosi 'daysInWeek', 'hoursInDay', 'visitsProgress'
     * @param integer pro který bulletin počítat, defaul=0=pro všechny
     * @param integer pro kterou stránku počítat, default=0=pro všechny
     * @param int       Rozsah dnu po spusteni, pro ktere maji byt data pripravena. (jen pro $type = 'visitsProgress')
     * @author Jaromir Krotky
     */
    public function getBehaviorChart($type, $bulletin_id=0, $page_id=0, $range = 30) {

        $texts = Ibulletin_Texts::getSet();

        $data_results = $this->_getBehaviorData($type, $bulletin_id, $page_id, $range);
        $chart_data = array();
        foreach ($data_results as $result) {
            $chart_data[$result['doba']] = $result['pocet'];
        }
        $labels = array_keys($chart_data);
        $data = array_values($chart_data);
        switch ($type) {
            case 'daysInWeek':
                $labels = array($texts->monday,$texts->tuesday, $texts->wednesday, $texts->thursday, $texts->friday, $texts->saturday, $texts->sunday);
                $ne = array_shift($data);
                array_push($data, $ne);

                //pro jistotu odebereme prebytecne labely
                foreach ($data as $k => $v) {
                    if (!$labels[$k]) {
                        unset($labels[$k]);
                    }
                }
                return array_combine($labels, $data);
            case 'hoursInDay':
                return($data);
            case 'visitsProgress':
                $marks = array();
                $mails = $this->_getBulletinMailsSendOuts($bulletin_id);
                $sent_dates = array();
                foreach ($mails as $mail) {
                    if(array_key_exists($mail['datum'], $sent_dates)){
                        $sent_dates[$mail['datum']] += (int) $mail['pocet'];
                    }
                    else{
                        $sent_dates[$mail['datum']] = (int) $mail['pocet'];
                    }
                }
                $limit = (count($sent_dates)>0 ? floor(max($sent_dates)/50) : 0);

                foreach ($labels as $key => $val) {
                    if (isset($sent_dates[$val]) && $sent_dates[$val]>$limit) {
                        $marks[] = $sent_dates[$val].' ('.$val.')';
                    } else {
                        $marks[] = null;
                    }
                }

                return array('data'=>array_combine($labels,$data),'labels'=>$marks,'limit'=>$limit);
        }

        return null;

    }


    /**
     * Vrati seznam contentu typu inDetail
     *
     * @return array seznam contentu
     */
    public function getContents() {
        $contents = array();
        $db = Zend_Registry::get('db');

        $_select = "SELECT * FROM content AS c WHERE class_name='Ibulletin_Content_Indetail' ORDER by id;";

        $contents = $db->fetchAll($_select);
        return $contents;
    }


    /**
     * Najde pro kazdy content timestamp, kdy byl poprve zobrazen platnym uzivatelem
     * (nepocitame testy, clienty a netargety).
     * Pro contenty, ktere jeste nebyly pouzity
     *
     * @param string $className Nazev tridy contentu, pkud neni zadano, najde pro vsechny
     * @return array    Klicem pole je ID contentu a hodnotou je ISO8061 timestamp prvniho pouziti
     */
    public function getContentsFirstUsed($className = null){
        $db = Zend_Registry::get('db');

        $where = '1=1';
        if($className){
            $where = "c.class_name = ".$db->quote($className);
        }

        $q = "
            SELECT c.id AS content_id, min(foo.timestamp) AS timestamp
            FROM content c
            LEFT JOIN (SELECT cp.content_id, pv.timestamp
                FROM content_pages cp
                JOIN page_views pv ON pv.page_id = cp.page_id
                JOIN sessions s ON pv.session_id = s.id
                JOIN users_vf uvf ON s.user_id = uvf.id) foo ON c.id = foo.content_id
            WHERE $where
            GROUP BY c.id
        ";

        $firstComes = $db->fetchAssoc($q);

        $firstComesAssoc = array();
        foreach($firstComes as $content){
            $firstComesAssoc[$content['content_id']] = $content['timestamp'];
        }

        return $firstComesAssoc;
    }


    /**
     *
     * Vrati data propustnosti slidu pro danou prezentaci
     * Kolik uzivatelu videlo ktery slide
     *
     * Aktualne se z prumeru vyrazuje hornich a dolnich 10 % hodnot,
     * jedna se o situace, kdy nekdo necha prohlizec s flashem bezet i nekolik dni a pak pokracuje
     * v prezentaci - skutecne se stava - overeno na palexii.
     *
     * @param integer pro který content(=prezentaci) počítat
     * @param string    Timestamp ISO 8061 od kdy
     * @param string    Timestamp ISO 8061 do kdy
     *
     * @return arr data propustnosti slidu
     * @author Jaromir Krotky
     */
    public function getSlideVisitors($content_id, $from, $to) {
        $data = array();
        $db = Zend_Registry::get('db');

        $_select = "
                    SELECT s.slide_num AS slide, count(distinct isv.user_id) AS pocet,
                        coalesce(avg((CASE WHEN (isv.percentile<10 AND isv.percentile>1) THEN isv.max_slide_time else NULL END)),0) AS avg_time, -- pouzijeme jen delku zobrazeni slidu mezi 10 a 90 % percentilu kvuli lekarum, kteri nechaji flash bezet a vrati se k nemu o nekolik dni pozdeji
                        coalesce(median(isv.max_slide_time),0) AS med_time,
                        s.mandatory
                    FROM slides AS s
                    LEFT JOIN (
                        SELECT
                            is1.slideon_id,
                            is1.user_id,
                            is1.content_id,
                            --(CASE WHEN (max(is2.time_slide) < 300000) THEN max(is2.time_slide) else NULL END) AS max_slide_time, -- pouzijeme jen delku zobrazeni slidu mensi nez 5 minut kvuli lekarum, kteri nechaji flash beyet a vrati se k nemu o nekolik dni pozdeji
                            max(is2.time_slide) AS max_slide_time,
                            ntile(10) OVER (PARTITION BY is1.slideon_id ORDER BY max(is2.time_slide) NULLS FIRST) AS percentile
                        FROM (
                            -- To je nutne kvuli tomu, abychom zapocitali i slidy na ktere presel, ale uz z nich nebyla odeslana zadna data
                            SELECT inds.slideon_id, inds.user_id, inds.content_id
                            FROM indetail_stats inds
                            WHERE timestamp >= '$from' AND timestamp <= '$to' AND
                            content_id = $content_id --je nutny kvuli vypoctu celkoveho avg a median
                            GROUP BY inds.slideon_id, inds.user_id, inds.content_id
                            ) is1
                        JOIN users_vf u ON u.id = is1.user_id
                        LEFT JOIN indetail_stats is2 ON is1.slideon_id = is2.slide_id
                            AND is1.user_id = is2.user_id AND is1.content_id = is2.content_id
                            AND is2.timestamp >= '$from' AND is2.timestamp <= '$to'
                        GROUP BY is1.slideon_id, is1.user_id, is1.content_id
                        --ORDER BY is1.slideon_id, max_slide_time

                     ) isv ON s.id=isv.slideon_id AND isv.content_id = $content_id
                    --JOIN users_vf u ON u.id = isv.user_id
                    WHERE s.content_id = $content_id
                    GROUP BY s.slide_num, s.mandatory ORDER BY s.slide_num
                    ";

        //echo '<br/>'.nl2br($_select).'<br/>-----------------------------------'.'<br/>';
        //echo $_select;
        //exit;

        $data = $db->fetchAll($_select);
        return $data;
    }

    /**
     *
     * Vrati pole popisujici kolikatou v poradi kterou prezentaci videlo lidi
     * Napriklad prezentaci 1 videlo jako prvni 100 lidi, jako druhou 10,
     * prezentaci 2 videlo jako prvni 5 lidi jako druhou 70 atd.
     *
     * @param string    Timestamp ISO 8061 od kdy
     * @param string    Timestamp ISO 8061 do kdy
     *
     * @return arr data poradi prezentaci
     * @author Jaromir Krotky
     */
    public function getInDetailVisitorsInOrder($from, $to){
        $data = array();
        $db = Zend_Registry::get('db');

        $_select = "SELECT content_id, poradi, COUNT(*) AS pocet
          FROM (
            SELECT user_id, content_id, row_number() OVER (PARTITION BY user_id ORDER BY min_timestamp) AS poradi
            FROM (
              SELECT user_id, content_id, MIN(timestamp) AS min_timestamp
              FROM indetail_stats AS i, users_vf AS u
              WHERE i.user_id=u.id --AND i.timestamp >= '$from' AND i.timestamp <= '$to' --Podle casu neomezujeme
              GROUP BY user_id, content_id
            ) foo
          ) as foo2
          GROUP BY content_id, poradi;";

        //echo $_select;
        //exit;
        $data = $db->fetchAll($_select);
        return $data;
    }


    /**
     *
     * Vrati prumerny cas prezentace
     *
     * Aktualne se z prumeru vyrazuji casy delsi nez 5 minut (300000ms),
     * jedna se o situace, kdy nekdo necha prohlizec s flashem bezet i nekolik dni a pak pokracuje
     * v prezentaci - skutecne se stava - overeno na palexii.
     *
     * @param integer pro který content(=prezentaci) počítat
     * @param string    Timestamp ISO 8061 od kdy
     * @param string    Timestamp ISO 8061 do kdy
     *
     * @return array    array(avg, median)
     * @author Jaromir Krotky
     */
    public function getInDetailAverage($content_id, $from, $to) {
        $data = array();
        $db = Zend_Registry::get('db');

        $_select = "
            SELECT
                coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN time else NULL END)),0) AS avg,
                median(time) AS median
            FROM (
                SELECT
                    max(max_time_total) AS time,
                    ntile(10) OVER (ORDER BY max(max_time_total) NULLS FIRST) AS percentile
                FROM (
                    SELECT
                        --is1.slideon_id,
                        is1.user_id,
                        is1.content_id,
                        --(CASE WHEN (max(is2.time_total) < 3000000) THEN max(is2.time_total) else NULL END) AS max_time_total -- pouzijeme jen delku zobrazeni slidu mensi nez 5 minut kvuli lekarum, kteri nechaji flash beyet a vrati se k nemu o nekolik dni pozdeji
                        max(is2.time_total) AS max_time_total
                    FROM (
                        -- To je nutne kvuli tomu, abychom zapocitali i slidy na ktere presel, ale uz z nich nebyla odeslana zadna data
                        SELECT indetail_stats.slideon_id, indetail_stats.user_id, indetail_stats.content_id
                        FROM indetail_stats
                        WHERE timestamp >= '$from' AND timestamp <= '$to' AND content_id = $content_id
                        GROUP BY indetail_stats.slideon_id, indetail_stats.user_id, indetail_stats.content_id
                        ) is1
                    LEFT JOIN indetail_stats is2 ON is1.slideon_id = is2.slide_id
                        AND is1.user_id = is2.user_id AND is1.content_id = is2.content_id
                        AND is2.timestamp >= '$from' AND is2.timestamp <= '$to'
                    GROUP BY is1.slideon_id, is1.user_id, is1.content_id
                ) isv, users_vf
                WHERE users_vf.id=user_id
                GROUP BY user_id) AS foo;
            ";

        //echo "\n\n".$_select."\n\n";
        //exit;

        $data = $db->fetchRow($_select);
        return $data;
    }

    /**
     * Vrati report pozvanych, navstevnosti a dokonceni pro danou prezentaci deleny podle emailu
     *
     * @param integer pro který content(=prezentaci) počítat
     * @param string    Timestamp ISO 8061 od kdy
     * @param string    Timestamp ISO 8061 do kdy
     * @return arr report
     */
    public function getInDetailContentEmailReport($content_id, $from, $to) {
        $data = array();
        $db = Zend_Registry::get('db');

        $indetail_content_come_method_report_sel = "
            SELECT
                foo.content_id,
                email_id,
                wave_id,
                name,
                invited,
                coalesce(come.pocet, 0) AS come,
                coalesce(started.pocet, 0) AS started,
                coalesce(finished.pocet, 0) AS finished
            FROM (
                (SELECT -- Spojeni pozvanych z jednotlivych mailu i tech, kteri prisli tranzitivne
                        -- pres mail a k inDetailu se nasledne proklikali na webu
                        -- Zabranuje duplicitnim radkum ve vypisu
                    email_id, null as wave_id, name, content_id, sum(invited) AS invited
                FROM
                    (SELECT -- Nejprve vezmeme vsechny maily kterymi se zvalo
                        e.id AS email_id,
                        null AS wave_id,
                        e.name,
                        cp.content_id,
                        (CASE WHEN (l.page_id=cp.page_id) THEN count(distinct u.id) else 0 END) AS invited
                        --,l.page_id AS l_page_id,
                        --cp.page_id AS cp_page_id
                    FROM
                        users_vf as u,
                        emails AS e,
                        users_links_tokens AS ult,
                        emails_send_v AS ue,
                        links AS l,
                        content_pages AS cp
                        --,content AS c
                    WHERE
                        u.id=ult.user_id
                        AND ue.user_id=u.id
                        AND e.id=ult.email_id
                        AND e.id=ue.email_id
                        AND ult.link_id=l.id
                        AND e.deleted IS NULL
                        --AND l.page_id=cp.page_id -- zabranuje zapocitavani pristupu z mailu na pages, na ktere nebylo v mailu kliknuto - vypina tranzitivitu
                        --AND cp.content_id=c.id
                        --AND c.class_name='Ibulletin_Content_Indetail'
                        --AND cp.content_id = 9
                    GROUP BY
                        e.id, e.name, cp.content_id, l.page_id,cp.page_id
                    ) AS unfiltered
                GROUP BY email_id, name, content_id
                )

                UNION -- Pripojime radky pro Vlny - pripojujeme proste vsechny vlny na ktere bylo pristoupeno pomoci wave pristupu
                (SELECT
                    null,
                    iw.id AS wave_id,
                    iw.name,
                    c.id AS content_id,
                    iw.invited
                FROM
                    invitation_waves iw
                JOIN page_views pv ON pv.invitation_id = iw.id AND pv.users_links_tokens_id IS NULL
                JOIN content c ON c.class_name='Ibulletin_Content_Indetail'
                GROUP BY
                    iw.id, iw.name, c.id, iw.invited
                )

                UNION -- Pripojime radky pro others
                (SELECT 0, 0, 'other', id, 0 FROM content WHERE class_name='Ibulletin_Content_Indetail')
                ) as foo

            LEFT JOIN ( -- come - prisli
                SELECT
                    cufs.content_id, count(distinct u.id) AS pocet, cufs.enter_by_email, cufs.enter_by_wave
                FROM
                    users_vf u
                JOIN contents_users_first_session_started_from_v cufs ON u.id=cufs.user_id
                    --AND cufs.timestamp >= '$from' AND cufs.timestamp <= '$to'
                GROUP BY
                    cufs.content_id,
                    cufs.enter_by_email,
                    cufs.enter_by_wave
                ) AS come ON come.content_id = foo.content_id AND
                    ((come.enter_by_email = foo.email_id AND come.enter_by_wave IS NULL)
                    OR (come.enter_by_wave = foo.wave_id AND come.enter_by_email IS NULL)
                    OR (come.enter_by_email IS NULL AND come.enter_by_wave IS NULL AND foo.email_id = 0))

            LEFT JOIN (-- started - zacali
                SELECT
                    cufs.content_id, count(distinct u.id) AS pocet, cufs.enter_by_email, cufs.enter_by_wave
                FROM
                    users_vf AS u
                JOIN contents_users_first_session_started_from_v as cufs ON u.id=cufs.user_id
                    --AND cufs.timestamp >= '$from' AND cufs.timestamp <= '$to'
                JOIN indetail_stats AS i ON cufs.content_id=i.content_id AND cufs.user_id=i.user_id
                    AND i.timestamp >= '$from' AND i.timestamp <= '$to'
                GROUP BY
                    cufs.content_id,
                    cufs.enter_by_email,
                    cufs.enter_by_wave
                ) AS started ON started.content_id = foo.content_id AND ((started.enter_by_email = foo.email_id
                    AND started.enter_by_wave IS NULL) OR (started.enter_by_wave = foo.wave_id
                    AND started.enter_by_email IS NULL) OR  (started.enter_by_email IS NULL
                    AND started.enter_by_wave IS NULL AND foo.email_id = 0))

            LEFT JOIN (-- finished
                SELECT
                    cufs.content_id, count(distinct u.id) AS pocet, cufs.enter_by_email, cufs.enter_by_wave
                FROM
                    users_vf AS u
                JOIN contents_users_first_session_started_from_v as cufs ON u.id=cufs.user_id
                    --AND cufs.timestamp >= '$from' AND cufs.timestamp <= '$to'
                JOIN indetail_stats AS i ON cufs.user_id=i.user_id AND cufs.content_id=i.content_id
                    AND i.timestamp >= '$from' AND i.timestamp <= '$to'
                JOIN (
                        SELECT DISTINCT user_id, content_id, array_agg(slideon_id)
                        FROM indetail_stats ins
                        WHERE content_id = $content_id
                        GROUP BY user_id, content_id
                        HAVING array_agg(slideon_id) @> array(SELECT DISTINCT id FROM slides WHERE content_id = $content_id AND mandatory)
                      ) finishers ON u.id = finishers.user_id AND i.content_id = finishers.content_id
                --WHERE
                --    coalesce(array(SELECT DISTINCT slideon_id FROM indetail_stats/*indetail_slideon_v*/ WHERE user_id=u.id AND content_id=i.content_id ORDER BY slideon_id) @>
                --        array(SELECT DISTINCT id FROM slides WHERE content_id=i.content_id AND mandatory), true)
                GROUP BY
                    cufs.content_id,
                    cufs.enter_by_email,
                    cufs.enter_by_wave
                ) AS finished ON finished.content_id = foo.content_id AND (finished.enter_by_email = foo.email_id OR finished.enter_by_wave = foo.wave_id OR (finished.enter_by_email IS NULL AND finished.enter_by_wave IS NULL AND foo.email_id = 0))

            --WHERE foo.content_id = 202

            --ORDER BY foo.content_id, foo.email_id
        ";

        //echo '<br/>'."\n".$indetail_content_come_method_report_sel."\n".'<br/>-----------------------------------'.'<br/>';
        //exit;

        $_select = "SELECT * FROM ($indetail_content_come_method_report_sel) AS bar
                    WHERE content_id=$content_id AND (come > 0 OR invited > 0)
                    ORDER BY email_id, wave_id";

        //echo $_select;
        //exit;
        $data = $db->fetchAll($_select);
        return $data;
    }


    /**
     * Vraci seznam uzivatelu, kteri byli pozvani na prezentace.
     *
     * - REPRE filtrujeme podle prislusnosti uzivatele k repre v users_reps.
     * - CAS filtrujeme podle casu odeslani pozvanky v users_emails jen podle horni hranice intervalu $toIso.
     *
     * @param $content_id   int     ID contentu
     * @param $email_id     int     ID emailu
     * @param $fromIso      string  Timestamp zacatku intervalu v ISO
     * @param $toIso        string  Timestamp konce intervalu v ISO
     * @param $repreId      int     Reprezentant, default NULL
     */
    public static function getIndetailInvited($content_id, $email_id=null, $fromIso = null, $toIso = null, $repreId = null) {
        $data = array();
        $db = Zend_Registry::get('db');

        $_select = "
                    SELECT u.id as user_id, min(ue.sent) as invited
                    FROM users_vf u
                    JOIN users_links_tokens ult ON u.id=ult.user_id ".($email_id ? " AND ult.email_id=$email_id" : '')."
                    JOIN links l ON ult.link_id=l.id
                    JOIN content_pages cp ON cp.page_id=l.page_id AND cp.content_id=$content_id
                    JOIN users_emails ue ON ult.email_id=ue.email_id ".($toIso ? "AND ue.sent <= '$toIso'": '')."
                    JOIN users_vv uvv ON uvv.id=u.id
                    ".($repreId ?
                    "JOIN users_reps ur ON ur.user_id = u.id AND ur.repre_id = $repreId"
                    : '')."
                    GROUP BY u.id
                    ORDER BY u.id";

        //echo nl2br($_select);

        //$data = $db->fetchAll($_select);
        $data = Monitoringexports::joinUsersData($_select, array('user_id'));
        return $data;
    }

    /**
     * Vraci seznam uzivatelu, kteri prisli na prezentace
     *
     * @param $content_id
     * @param $email_id
     * @param $wave_id
     * @param $fromIso      string  Timestamp zacatku intervalu v ISO
     * @param $toIso        string  Timestamp konce intervalu v ISO
     * @param $repreId      int     Reprezentant, default NULL
     */
    public static function getIndetailCome($content_id, $email_id=null, $wave_id = null, $fromIso = null, $toIso = null, $repreId = null, $total = false) {
        $data = array();
        $db = Zend_Registry::get('db');

        $where = '';
        if(is_numeric($email_id) && $email_id == 0 && is_numeric($wave_id) && $wave_id == 0 && !$total){
            $where = " AND cufs.enter_by_email IS NULL AND cufs.enter_by_wave IS NULL";
        }
        elseif(!$total){
            $where = (is_numeric($email_id) ? " AND cufs.enter_by_email=$email_id" : '').
                    (is_numeric($wave_id) ? " AND cufs.enter_by_wave=$wave_id" : '');
        }

        $_select = "SELECT u.id AS user_id, min(cufs.timestamp) as first_come
                        --uvv.id, uvv.name, uvv.surname, uvv.email, uvv.deleted, uvv.gender,
                        --uvv.skupina, uvv.selfregistered, uvv.added, uvv.client, uvv.target, uvv.reps,
                        --array_to_string(array(SELECT u1.name||' '||u1.surname FROM users u1 JOIN users_reps ur ON u1.id = ur.repre_id WHERE ur.user_id = u.id), ', ') AS rep_names,
                        --uvv.is_rep
                    FROM users_vf u
                    JOIN contents_users_first_session_started_from_v cufs ON u.id=cufs.user_id AND cufs.content_id=$content_id
                        ".($fromIso && $toIso ? "
                            AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                        " : '')."
                    --JOIN users_vv uvv ON uvv.id=u.id
                    ".($repreId ? '
                        -- REPRE
                        JOIN sessions_by_reps sbr ON sbr.session_id = cufs.id AND sbr.repre_id='.(int)$repreId.'
                    ' : '')."

                    WHERE 1=1
                        ".$where."
                    GROUP BY u.id
                    ORDER BY u.id";

        $data = Monitoringexports::joinUsersData($_select, array('user_id'));

        //$data = $db->fetchAll($_select);
        return $data;
    }


    /**
     * Vraci seznam uzivatelu, kteri spustili prezentaci
     *
     * @param $content_id
     * @param $email_id
     * @param $wave_id
     * @param $fromIso      string  Timestamp zacatku intervalu v ISO
     * @param $toIso        string  Timestamp konce intervalu v ISO
     * @param $repreId      int     Reprezentant, default NULL
     */
    public static function getIndetailStarted($content_id, $email_id=null, $wave_id = null, $fromIso = null, $toIso = null, $repreId = null, $total = false){
        $data = array();
        $db = Zend_Registry::get('db');

        $where = '';
        if(is_numeric($email_id) && $email_id == 0 && is_numeric($wave_id) && $wave_id == 0 && !$total){
            $where = " AND cufs.enter_by_email IS NULL AND cufs.enter_by_wave IS NULL";
        }
        elseif(!$total){
            $where = (is_numeric($email_id) ? " AND cufs.enter_by_email=$email_id" : '').
                    (is_numeric($wave_id) ? " AND cufs.enter_by_wave=$wave_id" : '');
        }

        $_select = "SELECT u.id as user_id,
                        max(vs.visited_slides),
                        sum(st.slides_time) AS \"sum_slides_time (sec)\"
                    FROM indetail_stats i
                    JOIN contents_users_first_session_started_from_v cufs ON cufs.user_id=i.user_id AND cufs.content_id=i.content_id
                        AND cufs.content_id=$content_id
                    JOIN users_vf u ON u.id=cufs.user_id
                    LEFT JOIN ( -- slide times
                        SELECT isv.user_id, round(sum(max_slide_time)::numeric/1000,2) AS slides_time
                        FROM indetail_slideon_v AS isv
                        WHERE isv.content_id=$content_id GROUP BY isv.user_id
                        ) as st ON st.user_id=u.id
                    LEFT JOIN ( -- visited slides
                        SELECT s.user_id, array_agg(s.slide_num) AS visited_slides
                        FROM (
                            SELECT isv.user_id, s.slide_num FROM indetail_stats/*indetail_slideon_v*/ isv
                            JOIN slides s ON s.id = isv.slideon_id
                            WHERE isv.content_id=$content_id GROUP BY isv.user_id, s.slide_num ORDER BY isv.user_id, s.slide_num
                            ) s
                        GROUP BY s.user_id
                    ) as vs ON vs.user_id=u.id
                    ".($repreId ? '
                        -- REPRE
                        JOIN sessions_by_reps sbr ON sbr.session_id = cufs.id AND sbr.repre_id='.(int)$repreId.'
                    ' : '')."
                    WHERE 1=1
                        ".($fromIso && $toIso ? "
                            AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                        " : '')."
                        ".$where."
                    GROUP BY u.id
                    ORDER BY u.id";

        //$data = $db->fetchAll($_select);
        $data = Monitoringexports::joinUsersData($_select, array('user_id'));
        return $data;
    }


    /**
     * Vraci seznam uzivatelu, kteri dokoncili prezentaci
     *
     * @param $content_id
     * @param $email_id
     * @param $wave_id
     * @param $fromIso      string  Timestamp zacatku intervalu v ISO
     * @param $toIso        string  Timestamp konce intervalu v ISO
     * @param $repreId      int     Reprezentant, default NULL
     * @param $total      bool    Ignorovat rozdeleni na vlny, maily a ostatni - total.
     */
    public static function getIndetailFinished($content_id, $email_id=null, $wave_id = null, $fromIso = null, $toIso = null, $repreId = null, $total = false){
        $data = array();
        $db = Zend_Registry::get('db');

        $where = '';
        if(is_numeric($email_id) && $email_id == 0 && is_numeric($wave_id) && $wave_id == 0 && !$total){
            $where = " AND cufs.enter_by_email IS NULL AND cufs.enter_by_wave IS NULL";
        }
        elseif(!$total){
            $where = (is_numeric($email_id) ? " AND cufs.enter_by_email=$email_id" : '').
                    (is_numeric($wave_id) ? " AND cufs.enter_by_wave=$wave_id" : '');
        }

        $_select = "
                    SELECT u.id as user_id, vs.visited_slides, st.slides_time AS \"sum_slides_time (sec)\" FROM
                    (
                        SELECT distinct u.id
                        FROM users_vf u
                        JOIN contents_users_first_session_started_from_v cufs ON u.id=cufs.user_id AND cufs.content_id=$content_id
                            ".($fromIso && $toIso ? "
                                AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                            " : '')."
                        JOIN indetail_stats i ON cufs.user_id=i.user_id AND cufs.content_id=i.content_id
                        ".($repreId ? '
                            -- REPRE
                            JOIN sessions_by_reps sbr ON sbr.session_id = cufs.id AND sbr.repre_id='.(int)$repreId.'
                        ' : '')."
                        WHERE
                            1=1
                            AND
                            u.id IN (
                                SELECT f.user_id from indetail_page_views_finished_v f
                                WHERE f.content_id = $content_id
                                GROUP BY f.user_id
                                )
                            ".$where.
                        "
                    ) u
                    LEFT JOIN ( -- slide times
                        SELECT isv.user_id, round(sum(max_slide_time)::numeric/1000,2) AS slides_time
                        FROM indetail_slideon_v AS isv
                        WHERE isv.content_id=$content_id GROUP BY isv.user_id
                        ) as st ON st.user_id=u.id
                    LEFT JOIN ( -- visited slides
                        SELECT s.user_id, array_agg(s.slide_num) AS visited_slides
                        FROM (
                            SELECT isv.user_id, s.slide_num FROM indetail_stats/*indetail_slideon_v*/ isv
                            JOIN slides s ON s.id = isv.slideon_id
                            WHERE isv.content_id=$content_id GROUP BY isv.user_id, s.slide_num ORDER BY isv.user_id, s.slide_num
                            ) s
                        GROUP BY s.user_id
                    ) as vs ON vs.user_id=u.id
                    ORDER BY u.id";

        //echo nl2br($_select);
        //exit();

        //$data = $db->fetchAll($_select);
        $data = Monitoringexports::joinUsersData($_select, array('user_id'));

        return $data;
    }


    /**
     * Vrati tabulku jednotivych navstev uzivatelu daneho contentu indetailu obsahujici navstivene slidy
     * a dalsi informace o uzivateli.
     *
     * @param $contentId Int   ID contentu inDetailu.
     * @return Array           Tabulka uzivatelu a jejich navstev v indetailu.
     */
    public static function getInDetailExportVisits($contentId)
    {
        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet();

        // Data
        $sel = "
            SELECT u.id, u.name, u.surname, u.gender, u.group, u.selfregistered, u.added, u.client, u.target, u.is_rep,
                array(SELECT u1.name||' '||u1.surname FROM users u1 JOIN users_reps ur ON u1.id = ur.repre_id WHERE ur.user_id = u.id) AS reps,
                --pv.id AS page_view_id,
                pv.timestamp AS visit_time, rep.id AS repre_surveyed_id, rep.name||' '||rep.surname AS repre_surveyed,
                array(SELECT s.slide_num FROM indetail_stats inds
                JOIN slides s ON s.id = inds.slideon_id
                WHERE inds.user_id=u.id AND inds.content_id=cp.content_id AND inds.page_views_id = pv.id ORDER BY inds.timestamp)
                AS slides_visited,
                (SELECT round(max(time_total)::numeric/1000,2)
                FROM indetail_stats AS inds
                WHERE inds.user_id=u.id AND inds.content_id=cp.content_id AND inds.page_views_id = pv.id)
                AS \"visit_time (sec)\"
            FROM users_vf u
            JOIN sessions s ON s.user_id = u.id
            JOIN page_views pv ON pv.session_id = s.id AND page_id IS NOT NULL
            JOIN content_pages cp ON cp.page_id = pv.page_id
            LEFT JOIN sessions_by_reps sbr ON sbr.session_id = s.id
            LEFT JOIN users rep ON rep.id = sbr.repre_id
            WHERE content_id = ".(int)$contentId."
            ORDER BY u.surname, u.name, u.id, pv.timestamp, pv.id
            ;
        ";

        $tableRaw = $db->fetchAll($sel);

        return $tableRaw;
    }


    /**
     * Vrati data indetail prezentaci ke zobrazeni v monitoringu indetail prezentaci.
     * Data vytazena z DB jsou doplnena o jmena contentu ziskana deserializaci contentu.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @param int  $contentId predame v pripade ze pozadujeme jediny content
     * @param int  $limit omezit vysledek na pocet zaznamu
     */
    public static function indetail_getPresentations($fromIso, $toIso, $repreId, $contentId = null, $limit = null)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

         if (!Reps::checkSessionsByReps()) {
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
         } else {
            $repreWhere = "AND s.repre_id='".(int)$repreId."'";
         }

        $sel =
                "WITH filtered AS (
                    -- Filtrovana data z indetail_sessions_pres_v
                    SELECT s.*
                    FROM users_vf as u
                    JOIN indetail_sessions_pres_v as s on (u.id=s.user_id)
                    -- Servier, cela navsteva musi byt delsi nez 10 s (nastavitelne)
                	".($config->indetail->minVisitTimeMs ?
                    "JOIN (
                        SELECT id, content_id FROM indetail_slides_v
                        GROUP BY id, content_id
                        HAVING sum(max_slide_time) > ".$config->indetail->minVisitTimeMs."
                    ) AS sessionFilter ON s.id = sessionFilter.id AND s.content_id = sessionFilter.content_id "
                    : '')."
                    WHERE
                        s.timestamp > '".$fromIso."' and
                        s.timestamp < '".$toIso."'
                        ".($repreId ?  $repreWhere : '')."
                        ".($contentId ? 'AND s.content_id='.(int)$contentId : '')."
                        --".($config->indetail->minVisitTimeMs ? "AND s.max_time > ".$config->indetail->minVisitTimeMs : '')." -- nepouzivame, protoze vznika nekonzistence v pripade opravy dat podle timestamp
                    --ORDER BY s.content_id
                )
                -- Hlavni SELECT
                SELECT
                    ses.*,
                    usr.avg_time AS avg_usr_time,
                    usr.med_time AS med_usr_time,
                    slides_mandatory.pocet AS slides_mandatory
                FROM (
                    -- Granularita prumeru a medianu na session (primo prevzate z indetail_sessions_pres_v)
                    SELECT
                        content_id,
                        sum(view_count) as view_count,
                        count(distinct id) as session_count,
                        count(distinct user_id) as user_count,
                        count(distinct (case when finished then user_id else null end)) as finished,
                        coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_time,
                        coalesce(median(max_time),0) AS med_time
                    FROM filtered
                    GROUP BY
                        content_id
                ) AS ses
                JOIN (
                    -- Granularita prumeru a medianu na uzivatele (dvakrat group)
                    SELECT
                        content_id,
                        coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_time,
                        coalesce(median(max_time),0) AS med_time
                    FROM (
                        -- Ekvivalent indetail_sessions_pres_v, ale s granularitou na uzivatele
                        SELECT content_id, user_id,
                            max(max_time) AS max_time,
                            ntile(10) OVER (PARTITION BY content_id ORDER BY max(max_time) NULLS FIRST) AS percentile
                        FROM filtered GROUP BY content_id, user_id
                        ) AS filtered
                    GROUP BY
                        content_id
                ) AS usr ON usr.content_id = ses.content_id
                JOIN (
                    SELECT content_id,COALESCE(sum(CASE WHEN mandatory THEN 1 ELSE 0 END),0) AS pocet from slides GROUP BY content_id
                ) AS
                    slides_mandatory ON slides_mandatory.content_id = ses.content_id
                JOIN content ON content.id = ses.content_id
                ORDER BY content.created DESC".($limit?" LIMIT $limit":'');

        //echo nl2br($sel); exit;

        $data = $db->fetchAll($sel);

        // Pripravime objekty jednotlivych contentu a jeich jmena
        foreach($data as $key => $presentation){
            $content = Contents::get($presentation['content_id']);
            $data[$key]['name'] = $content['object']->name;
        }

        return $data;
    }

    /**
     * Vrati pocet indetail prezentaci ke zobrazeni v monitoringu indetail prezentaci.
     * Data vytazena z DB jsou doplnena o jmena contentu ziskana deserializaci contentu.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @param int       $contentId predame v pripade ze pozadujeme jediny content
	 * @return  int     pocet zaznamu
     */
    public function indetail_getPresentationsCount($fromIso, $toIso, $repreId, $contentId = null)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        if (!Reps::checkSessionsByReps()) {
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
         } else {
            $repreWhere = "AND s.repre_id='".(int)$repreId."'";
         }

        $sel =
                "WITH filtered AS (
                    -- Filtrovana data z indetail_sessions_pres_v
                    SELECT s.*
                    FROM users_vf as u
                    JOIN indetail_sessions_pres_v as s on (u.id=s.user_id)
                    -- Servier, cela navsteva musi byt delsi nez 10 s (nastavitelne)
                	".($config->indetail->minVisitTimeMs ?
                    "JOIN (
                        SELECT id, content_id FROM indetail_slides_v
                        GROUP BY id, content_id
                        HAVING sum(max_slide_time) > ".$config->indetail->minVisitTimeMs."
                    ) AS sessionFilter ON s.id = sessionFilter.id AND s.content_id = sessionFilter.content_id "
                    : '')."
                    WHERE
                        s.timestamp > '".$fromIso."' and
                        s.timestamp < '".$toIso."'
                        ".($repreId ? $repreWhere : '')."
                        ".($contentId ? 'AND s.content_id='.(int)$contentId : '')."
                        --".($config->indetail->minVisitTimeMs ? "AND s.max_time > ".$config->indetail->minVisitTimeMs : '')." -- nepouzivame, protoze vznika nekonzistence v pripade opravy dat podle timestamp
                    --ORDER BY s.content_id
                )
                -- Hlavni SELECT
                SELECT
                    COUNT(ses.content_id)

                FROM (
                    -- Granularita prumeru a medianu na session (primo prevzate z indetail_sessions_pres_v)
                    SELECT
                        content_id,
                        sum(view_count) as view_count,
                        count(distinct id) as session_count,
                        count(distinct user_id) as user_count,
                        count(distinct (case when finished then user_id else null end)) as finished,
                        coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_time,
                        coalesce(median(max_time),0) AS med_time
                    FROM filtered
                    GROUP BY
                        content_id
                ) AS ses
                JOIN (
                    -- Granularita prumeru a medianu na uzivatele (dvakrat group)
                    SELECT
                        content_id,
                        coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_time,
                        coalesce(median(max_time),0) AS med_time
                    FROM (
                        -- Ekvivalent indetail_sessions_pres_v, ale s granularitou na uzivatele
                        SELECT content_id, user_id,
                            max(max_time) AS max_time,
                            ntile(10) OVER (PARTITION BY content_id ORDER BY max(max_time) NULLS FIRST) AS percentile
                        FROM filtered GROUP BY content_id, user_id
                        ) AS filtered
                    GROUP BY
                        content_id
                ) AS usr ON usr.content_id = ses.content_id
                JOIN (
                    SELECT content_id,COALESCE(sum(CASE WHEN mandatory THEN 1 ELSE 0 END),0) AS pocet from slides GROUP BY content_id
                ) AS
                    slides_mandatory ON slides_mandatory.content_id = ses.content_id";


        $count = $db->fetchOne($sel);

        return $count;
    }
    /**
     * Vrati informace o navstevnosti slajdu prezentace.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @param   int     Id contentu
     * @param   bool    Vratit maxima pro vykresleni grafu - nejvyssi cisla nejlepsiho repa
     * @return  array
     */
    public static function indetail_getPresentationsSlides($fromIso, $toIso, $repreId, $contentId, $getMaxs = false)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');


        $filterByUsersReps = false;
        if (!Reps::checkSessionsByReps()) {
            $filterByUsersReps = true;
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
            $repreCol = 'ur.repre_id,';
         } else {
            $repreWhere = "AND isv.repre_id='".(int)$repreId."'";
            $repreCol = 'isv.repre_id,';
        }


        $sel ="
            WITH filtered AS (
                -- Filtrovana data z indetail_slides_v a slides
                SELECT ".
                    ($getMaxs ? $repreCol : '')." -- pro maxima potrebujeme rozcleneni napric reprezentanty
                    s.slide_num,
                    s.name,
                    s.mandatory,
                    isv.view_count,
                    isv.id AS session_id,
                    u.id as user_id,
                    ntile(10) OVER (PARTITION BY s.slide_num ORDER BY isv.sum_slide_time NULLS FIRST) AS percentile_max,
                    isv.sum_slide_time
                FROM users_vf as u
                JOIN indetail_slides_v AS isv ON
                        (u.id=isv.user_id)
                        AND isv.content_id=".(int)$contentId."
                        AND isv.timestamp > '".$fromIso."'
                        AND isv.timestamp < '".$toIso."'
                        ".($repreId && !$getMaxs ? $repreWhere : '')."
                JOIN slides AS s ON (s.id=isv.slideon_id)
                ".(($filterByUsersReps) ? "LEFT JOIN users_reps as ur ON ur.user_id = u.id AND ur.repre_id = '".(int)$repreId."'" : "") ."
                 -- Servier, cela navsteva musi byt delsi nez 10 s (nastavitelne)
                ".($config->indetail->minVisitTimeMs ?
                    "JOIN (
                        SELECT id FROM indetail_slides_v
                        WHERE content_id = ".(int)$contentId."
                        GROUP BY id
                        HAVING sum(max_slide_time) > ".$config->indetail->minVisitTimeMs."
                        --HAVING max(max_time_total) > ".$config->indetail->minVisitTimeMs." -- toto vyzaduje pridani max_time_total do indetail_slides_v
                    )AS sessionFilter ON isv.id = sessionFilter.id "
                    : '')."
                WHERE isv.view_count > 0
                    -- Servier, kazdy slide musi byt zobrazen po minimalni a maximalni cas, jinak se nepocita (nastavitelne)
                    ".($config->indetail->minSlideTimesMs ? "AND isv.sum_slide_time >= ".$config->indetail->minSlideTimesMs : '')."
                    ".($config->indetail->maxSlideTimesMs ? "AND isv.sum_slide_time <= ".$config->indetail->maxSlideTimesMs : '')."

            )
            SELECT
                ses.*,
                usr.avg_usr_time,
                usr.med_usr_time
            FROM (
                -- Prumery a mediany pres sessions
                SELECT
                    ".($getMaxs ? 'repre_id,' : '')." -- pro maxima potrebujeme rozcleneni napric reprezentanty
                    slide_num,
                    name,
                    mandatory,
                    sum(view_count) as view_count,
                    count(distinct session_id) as session_count,
                    count(distinct user_id) as user_count,
                    coalesce(avg((CASE WHEN (percentile_max<10 AND percentile_max>1) THEN sum_slide_time else NULL END)),0) AS avg_ses_time,
                    coalesce(median(sum_slide_time),0) AS med_ses_time
                FROM filtered

                GROUP BY ".
                    ($getMaxs ? 'repre_id,' : '')." -- pro maxima potrebujeme rozcleneni napric reprezentanty
                    slide_num,
                    name,
                    mandatory
            ) AS ses
            JOIN (
                -- Prumery a mediany pres users (dvakrat group)
                SELECT
                    ".($getMaxs ? 'repre_id,' : '')." -- pro maxima potrebujeme rozcleneni napric reprezentanty
                    slide_num,
                    coalesce(avg((CASE WHEN (percentile_max<10 AND percentile_max>1) THEN sum_slide_time else NULL END)),0) AS avg_usr_time,
                    coalesce(median(sum_slide_time),0) AS med_usr_time
                FROM (
                    -- Nalezni maxima souctu casu pro jednoho usera napric jeho sessions
                    SELECT
                        ".($getMaxs ? 'repre_id,' : '')."
                        slide_num, max(sum_slide_time) AS sum_slide_time,
                        ntile(10) OVER (PARTITION BY slide_num ORDER BY max(sum_slide_time) NULLS FIRST) AS percentile_max
                    FROM filtered
                    GROUP BY slide_num, user_id
                        ".($getMaxs ? ',repre_id' : '')." -- pro maxima potrebujeme rozcleneni napric reprezentanty
                ) AS filtered
                GROUP BY ".
                    ($getMaxs ? 'repre_id,' : '')." -- pro maxima potrebujeme rozcleneni napric reprezentanty
                    slide_num
            ) AS usr ON usr.slide_num = ses.slide_num

            ORDER BY
                slide_num

        ";

        // Dotaz pro zjisteni maxim pro graf (nejvyssi cisla napric slidy a repy)
        $selMaxs = '
            SELECT
                max(view_count) as view_count,
                max(session_count) as session_count,
                max(user_count) as user_count,
                max(avg_ses_time) as avg_ses_time,
                max(med_ses_time) AS med_ses_time,
                max(avg_usr_time) as avg_usr_time,
                max(med_usr_time) AS med_usr_time
            FROM ('.$sel.') foo
            WHERE repre_id IS NOT NULL
        ';

        //echo nl2br($selMaxs); exit;
        //echo nl2br($sel); exit;

        if(!$getMaxs){
            $data = $db->fetchAll($sel);
        }
        else{
            $data = $db->fetchAll($selMaxs);
            $data = array_shift($data);
        }

        return $data;
    }

    /**
     * Vrati data monitoringu resorces pro danou prezentaci v monitoringu indetailu.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @param   int     Id contentu
     */
    public static function indetail_getPresentationsResources($fromIso, $toIso, $repreId, $contentId)
    {
        $db = Zend_Registry::get('db');


        if (!Reps::checkSessionsByReps()) {
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
         } else {
            $repreWhere = "AND s.repre_id='".(int)$repreId."'";
         }

        $sel = "
            WITH filtered AS (
                -- Predfiltrovana tabulka indetail_sessions_res_v pro pouziti v dalsich dotazech
                SELECT
                    s.resource_id, r.name, view_count, s.id AS session_id, u.id AS user_id, s.percentile, s.max_time, s.sum_time
                FROM users_vf AS u
                JOIN indetail_sessions_res_v AS s ON (u.id=s.user_id)
                JOIN resources AS r ON (s.resource_id=r.id)
                WHERE
                    r.content_id=".(int)$contentId." AND
                    s.timestamp > '".$fromIso."' AND
                    s.timestamp < '".$toIso."'
                    ".($repreId ? $repreWhere : '')."
            )
            SELECT ses.*, usr.avg_usr_time, usr.med_usr_time
            FROM (
                -- Prumer a median maximalnich casu na session (pouziva predfiltrovanou tabulku indetail_sessions_res_v)
                SELECT
                    resource_id,
                    name,
                    sum(view_count) as view_count,
                    count(distinct session_id) as session_count,
                    count(distinct user_id) as user_count,
                    coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_ses_time,
                    coalesce(median(max_time),0) AS med_ses_time,
                    sum(sum_time) as sum_time
                FROM filtered
                GROUP BY
                    resource_id,
                    name
            ) AS ses
            JOIN (
                -- Prumer a median maximalnich casu na uzivatele (pouziva predfiltrovanou tabulku indetail_sessions_res_v)
                SELECT
                    resource_id,
                    coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_usr_time,
                    coalesce(median(max_time),0) AS med_usr_time
                FROM (
                    -- Maximalni casy na uzivatele
                    SELECT resource_id, max(max_time) AS max_time,
                        ntile(10) OVER (PARTITION BY resource_id ORDER BY max(max_time) NULLS FIRST) AS percentile
                    FROM filtered
                    GROUP BY resource_id, user_id
                ) AS filtered
                GROUP BY
                    resource_id
            ) AS usr ON ses.resource_id = usr.resource_id
            ORDER BY
                resource_id


        ";

        //echo nl2br($sel); exit;

        $data = $db->fetchAll($sel);

        return $data;
    }

    /**
     * Vrati data monitoringu vstupnich kanalu pro danou prezentaci v monitoringu indetailu.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @param   int     Id contentu
     */
    public static function indetail_getPresentationEntryChannels($fromIso, $toIso, $repreId, $contentId)
    {
        $db = Zend_Registry::get('db');

         if (!Reps::checkSessionsByReps()) {
            $repreJoin = "JOIN users_reps as ur ON cufs.user_id = ur.user_id AND ur.repre_id = '".(int)$repreId."'";
         } else {
            $repreJoin = "JOIN sessions_by_reps sbr ON sbr.session_id = cufs.id AND sbr.repre_id='".(int)$repreId."'";
         }

        $sel = "
            SELECT
                foo.content_id,
                email_id,
                wave_id,
                name,
                invited,
                coalesce(come.pocet, 0) AS come,
                coalesce(started.pocet, 0) AS started,
                coalesce(finished.pocet, 0) AS finished
            FROM (
                (SELECT -- Spojeni pozvanych z jednotlivych mailu i tech, kteri prisli tranzitivne
                        -- pres mail a k inDetailu se nasledne proklikali na webu
                        -- Zabranuje duplicitnim radkum ve vypisu
                    email_id, null as wave_id, name, content_id, sum(invited) AS invited
                FROM
                    (
                    -- Nejprve vezmeme vsechny maily kterymi se zvalo
                    -- Filtrujeme REPRE podle prislusnosti uzivatele k repre v users_reps
                    -- Filtrujeme podle casu jen dle horni hranice
                    SELECT
                        e.id AS email_id,
                        null AS wave_id,
                        e.name,
                        cp.content_id,
                        (CASE WHEN (l.page_id=cp.page_id) THEN count(distinct u.id) else 0 END) AS invited
                    FROM
                        users_vf as u
                    JOIN emails_send_v ue ON ue.user_id=u.id AND ue.sent <= '$toIso'
                    JOIN emails e ON e.id=ue.email_id AND e.deleted IS NULL
                    JOIN users_links_tokens ult ON e.id=ult.email_id AND u.id=ult.user_id
                    JOIN links l ON ult.link_id=l.id
                    JOIN content_pages cp ON 1=1
                    ".($repreId ? 'JOIN users_reps ur ON ur.user_id = u.id AND ur.repre_id='.(int)$repreId : '')."
                    GROUP BY
                        e.id, e.name, cp.content_id, l.page_id,cp.page_id
                    ) AS unfiltered
                GROUP BY email_id, name, content_id
                )

                UNION
                (
                -- Pripojime radky pro Vlny - pripojujeme proste vsechny vlny na ktere bylo pristoupeno pomoci wave pristupu
                -- Filtrujeme REPRE podle prislusnosti uzivatele k repre v users_reps
                -- Podle casu omezujeme dle platnosti vlny a to jen horni hranici
                SELECT
                    null,
                    iw.id AS wave_id,
                    iw.name,
                    c.id AS content_id,
                    iw.invited
                FROM
                    invitation_waves iw
                JOIN page_views pv ON pv.invitation_id = iw.id AND pv.users_links_tokens_id IS NULL
                JOIN content c ON c.class_name='Ibulletin_Content_Indetail'
                ".($repreId ? '
                    -- prisli pomoci reprezentanta, nebo patri reprezentantovi
                    -- Pouziva se zde jen pro vyfiltrovani relevantnich vln ke zobrazeni, ne k pocitani uzivatelu
                    JOIN sessions s ON s.id = pv.session_id
                    LEFT JOIN sessions_by_reps sbr ON sbr.session_id = s.id
                    JOIN users u ON u.id = s.user_id
                    LEFT JOIN users_reps ur ON ur.user_id = u.id
                ' : '')."
                WHERE
                    (iw.\"start\" <= '$toIso' OR iw.\"start\" IS null)
                    ".($repreId ? '
                    AND (ur.repre_id='.(int)$repreId.' OR  sbr.repre_id = '.(int)$repreId.')
                    ' : '')."
                GROUP BY
                    iw.id, iw.name, c.id, iw.invited
                )

                UNION -- Pripojime radky pro others
                (SELECT 0, 0, 'other', id, 0 FROM content WHERE class_name='Ibulletin_Content_Indetail')
                ) as foo

            LEFT JOIN (
                -- come - prisli
                -- Cas se filtruje podle sessions (prvni session uzivatele na danem contentu - timestamp v cufs)
                -- REPRE se filtruje podle sessions_by_reps
                SELECT
                    cufs.content_id, count(distinct u.id) AS pocet, cufs.enter_by_email, cufs.enter_by_wave
                FROM
                    users_vf u
                JOIN contents_users_first_session_started_from_v cufs ON u.id=cufs.user_id AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                ".($repreId ? $repreJoin : '')."
                GROUP BY
                    cufs.content_id,
                    cufs.enter_by_email,
                    cufs.enter_by_wave
                ) AS come ON come.content_id = foo.content_id AND
                    ((come.enter_by_email = foo.email_id AND come.enter_by_wave IS NULL)
                    OR (come.enter_by_wave = foo.wave_id AND come.enter_by_email IS NULL)
                    OR (come.enter_by_email IS NULL AND come.enter_by_wave IS NULL AND foo.email_id = 0))

            LEFT JOIN (
                -- started - zacali
                -- Cas se filtruje podle sessions (prvni session uzivatele na danem contentu - timestamp v cufs)
                -- REPRE se filtruje podle sessions_by_reps
                SELECT
                    cufs.content_id, count(distinct u.id) AS pocet, cufs.enter_by_email, cufs.enter_by_wave
                FROM
                    users_vf AS u
                JOIN contents_users_first_session_started_from_v as cufs ON u.id=cufs.user_id
                    AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                ".($repreId ? $repreJoin : '')."
                JOIN indetail_stats AS i ON cufs.content_id=i.content_id AND cufs.user_id=i.user_id
                GROUP BY
                    cufs.content_id,
                    cufs.enter_by_email,
                    cufs.enter_by_wave
                ) AS started ON started.content_id = foo.content_id AND ((started.enter_by_email = foo.email_id
                    AND started.enter_by_wave IS NULL) OR (started.enter_by_wave = foo.wave_id
                    AND started.enter_by_email IS NULL) OR  (started.enter_by_email IS NULL
                    AND started.enter_by_wave IS NULL AND foo.email_id = 0))

            LEFT JOIN (
                -- finished
                -- Cas se filtruje podle sessions (prvni session uzivatele na danem contentu - timestamp v cufs)
                -- REPRE se filtruje podle sessions_by_reps
                SELECT
                    cufs.content_id, count(distinct u.id) AS pocet, cufs.enter_by_email, cufs.enter_by_wave
                FROM
                    users_vf AS u
                JOIN contents_users_first_session_started_from_v as cufs ON u.id=cufs.user_id
                    AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                ".($repreId ? $repreJoin : '')."
                JOIN indetail_stats AS i ON cufs.user_id=i.user_id AND cufs.content_id=i.content_id
                JOIN indetail_page_views_finished_v finishers ON u.id = finishers.user_id AND i.content_id = finishers.content_id
                --WHERE
                --    coalesce(array(SELECT DISTINCT slideon_id FROM indetail_stats/*indetail_slideon_v*/ WHERE user_id=u.id AND content_id=i.content_id ORDER BY slideon_id) @>
                --        array(SELECT DISTINCT id FROM slides WHERE content_id=i.content_id AND mandatory), true)
                GROUP BY
                    cufs.content_id,
                    cufs.enter_by_email,
                    cufs.enter_by_wave
                ) AS finished ON finished.content_id = foo.content_id AND (finished.enter_by_email = foo.email_id OR finished.enter_by_wave = foo.wave_id OR (finished.enter_by_email IS NULL AND finished.enter_by_wave IS NULL AND foo.email_id = 0))

            --WHERE foo.content_id = 202
            --ORDER BY foo.content_id, foo.email_id
        ";

        //echo nl2br($sel);

        $_select = "SELECT bar.*, e.name AS email_name, iw.name AS wave_name
                    FROM ($sel) AS bar
                    LEFT JOIN emails e ON e.id = bar.email_id AND e.deleted IS NULL
                    LEFT JOIN invitation_waves iw ON iw.id = bar.wave_id
                    WHERE bar.content_id=$contentId AND (bar.come > 0 OR bar.invited > 0)
                    ORDER BY email_id, wave_id";

        //echo nl2br($_select);
        //exit;


        $data = $db->fetchAll($_select);

        return $data;
    }

    /**
     * Vrati data souhrnu (totals) monitoringu vstupnich kanalu pro danou prezentaci v monitoringu indetailu.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @param   int     Id contentu
     */
    public static function indetail_getPresentationEntryChannelsTotals($fromIso, $toIso, $repreId, $contentId)
    {
        $db = Zend_Registry::get('db');

         if (!Reps::checkSessionsByReps()) {
            $repreJoin = "JOIN users_reps as ur ON cufs.user_id = ur.user_id AND ur.repre_id = '".(int)$repreId."'";
         } else {
            $repreJoin = "JOIN sessions_by_reps sbr ON sbr.session_id = cufs.id AND sbr.repre_id='".(int)$repreId."'";
         }

        $sel = "
            SELECT
            (
                -- Vsichni pozvani mailem
                -- reprezentanty filtrujeme podle users_reps
                -- cas omezujeme podle odeslani mailu, ovsem pouze z vrchu kvuli konzistenci
                SELECT count(distinct u.id) AS invited
                FROM
                    users_vf as u
                JOIN emails_send_v ue ON ue.user_id=u.id
                JOIN emails e ON e.id=ue.email_id AND ue.sent <= '$toIso' AND e.deleted IS NULL
                JOIN users_links_tokens ult ON e.id=ult.email_id AND u.id=ult.user_id
                JOIN links l ON ult.link_id=l.id
                JOIN content_pages cp ON l.page_id=cp.page_id AND cp.content_id = $contentId
                ".($repreId ? '
                JOIN users_reps ur ON ur.user_id = u.id AND ur.repre_id = '.(int)$repreId.'
                ' : '')."
            )AS invited_email,

            (
                -- Uzivatele, kteri otevreli stranku s prezentaci
                -- Reprezentanti podle sessions_by_reps
                -- Cas podle cufs - cas session prvniho pristupu kvuli souladu se zbytkem
                SELECT count(distinct u.id) AS come
                FROM users_vf u
                JOIN contents_users_first_session_started_from_v cufs ON u.id=cufs.user_id AND cufs.content_id=$contentId
                    AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                ".($repreId ? $repreJoin : '')."
            ) AS come,

            (
                -- Uzivatele, kterym se spustila prezentace (prezentace odeslala nejaka data na server)
                -- Reprezentanti podle sessions_by_reps
                -- Cas podle cufs - cas session prvniho pristupu kvuli souladu se zbytkem
                SELECT count(distinct u.id) AS started
                FROM indetail_stats ins
                JOIN contents_users_first_session_started_from_v cufs ON ins.user_id=cufs.user_id
                    AND ins.content_id = $contentId AND cufs.content_id=$contentId
                    AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                JOIN users_vf u ON u.id = ins.user_id
                ".($repreId ? $repreJoin : '')."

            ) AS started,

            (
                -- Uzivatele, kteri dokoncili prezentaci - navstivili vsechny povinne slidy
                -- Reprezentanti podle sessions_by_reps
                -- Cas podle cufs - cas session prvniho pristupu kvuli souladu se zbytkem
                SELECT count(*) AS finished FROM (
                    SELECT u.id
                    FROM contents_users_first_session_started_from_v cufs
                    JOIN users_vf u ON u.id = cufs.user_id
                    ".($repreId ? $repreJoin : '')."
                    WHERE cufs.content_id=$contentId
                         AND cufs.timestamp >= '$fromIso' AND cufs.timestamp <= '$toIso'
                         AND u.id IN (
                            SELECT f.user_id from indetail_page_views_finished_v f
                            WHERE f.content_id = $contentId
                            GROUP BY f.user_id
                            )
                    GROUP BY u.id
                ) as foo
            ) AS finished
        ";

        //echo nl2br($sel);

        $data = $db->fetchAll($sel);

        return $data;
    }

    /**
     * Vrati data globalnich resources ke zobrazeni v monitoringu indetail resources.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
	 * @param   int     omezeni na pocet vysledku
     */
    public static function indetail_getResources($fromIso, $toIso, $repreId, $limit = null)
    {
        $db = Zend_Registry::get('db');

        if (!Reps::checkSessionsByReps()) {
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
         } else {
            $repreWhere = "AND s.repre_id='".(int)$repreId."'";
         }

        $sel = "
                WITH filtered AS (
                    -- Ziskame vyfiltrovana data z indetail_sessions_res_v a resources
                    SELECT
                        s.resource_id,
                        r.name,
                        r.content_id,
                        view_count,
                        s.id AS session_id,
                        u.id AS user_id,
                        s.percentile AS percentile,
                        s.max_time,
                        s.sum_time
                    FROM
                        users_vf as u
                        join indetail_sessions_res_v as s on (u.id=s.user_id)
                        join resources as r on (s.resource_id=r.id)
                    where
                        r.content_id is null and
                        s.timestamp > '".$fromIso."' and
                        s.timestamp < '".$toIso."'
                        ".($repreId ? $repreWhere: '')."
                )
                SELECT
                    ses.*,
                    usr.avg_usr_time,
                    usr.med_usr_time
                FROM (
                    -- Data s granularitou na session
                    SELECT
                        resource_id,
                        name,
                        content_id,
                        sum(view_count) as view_count,
                        count(distinct session_id) as session_count,
                        count(distinct user_id) as user_count,
                        coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_ses_time,
                        coalesce(median(max_time),0) AS med_ses_time,
                        sum(sum_time) as sum_time
                    from
                        filtered
                    group by
                        resource_id,
                        name,
                        content_id
                    ) AS ses
                JOIN (
                    -- Data s granularitou na user (dvakrat group by)
                    SELECT
                        resource_id,
                        content_id,
                        coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_usr_time,
                        coalesce(median(max_time),0) AS med_usr_time
                    FROM (
                        -- Data seskupena podle uzivatele - bereme maximum na vsechny session uzivatele
                        SELECT
                            resource_id,
                            content_id,
                            ntile(10) OVER (PARTITION BY resource_id ORDER BY max(max_time) NULLS FIRST) AS percentile,
                            max(max_time) AS max_time
                        FROM
                            filtered
                        group by
                            resource_id,
                            content_id,
                            user_id
                    ) AS filteres
                    GROUP BY
                        resource_id,
                        content_id

                ) AS usr ON ses.resource_id = usr.resource_id
                JOIN content ON content.id = ses.content_id
                ORDER BY
                    content.created DESC,
                    resource_id DESC
        ".($limit?" LIMIT $limit":'');

        //echo nl2br($sel); exit;

        $data = $db->fetchAll($sel);

        // Pripravime objekty jednotlivych contentu a jeich jmena
        foreach($data as $key => $resource){
            $content = Contents::get($resource['content_id']);
            if($content){
                $data[$key]['content_name'] = $content['object']->name;
            }
        }

        return $data;
    }

    /**
     * Vrati data globalnich resources ke zobrazeni v monitoringu indetail resources.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
	 * @return  int     pocet zaznamu
    */
	public function indetail_getResourcesCount($fromIso, $toIso, $repreId) {
		$db = Zend_Registry::get('db');

        if (!Reps::checkSessionsByReps()) {
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
         } else {
            $repreWhere = "AND s.repre_id='".(int)$repreId."'";
         }

        $sel = "
                WITH filtered AS (
                    -- Ziskame vyfiltrovana data z indetail_sessions_res_v a resources
                    SELECT
                        s.resource_id,
                        r.name,
                        r.content_id,
                        view_count,
                        s.id AS session_id,
                        u.id AS user_id,
                        s.percentile AS percentile,
                        s.max_time,
                        s.sum_time
                    FROM
                        users_vf as u
                        join indetail_sessions_res_v as s on (u.id=s.user_id)
                        join resources as r on (s.resource_id=r.id)
                    where
                        r.content_id is null and
                        s.timestamp > '".$fromIso."' and
                        s.timestamp < '".$toIso."'
                        ".($repreId ? $repreWhere : '')."
                )
                SELECT
                    COUNT(ses.content_id)
                FROM (
                    -- Data s granularitou na session
                    SELECT
                        resource_id,
                        name,
                        content_id,
                        sum(view_count) as view_count,
                        count(distinct session_id) as session_count,
                        count(distinct user_id) as user_count,
                        coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_ses_time,
                        coalesce(median(max_time),0) AS med_ses_time,
                        sum(sum_time) as sum_time
                    from
                        filtered
                    group by
                        resource_id,
                        name,
                        content_id
                    ) AS ses
                JOIN (
                    -- Data s granularitou na user (dvakrat group by)
                    SELECT
                        resource_id,
                        content_id,
                        coalesce(avg((CASE WHEN (percentile<10 AND percentile>1) THEN max_time else NULL END)),0) AS avg_usr_time,
                        coalesce(median(max_time),0) AS med_usr_time
                    FROM (
                        -- Data seskupena podle uzivatele - bereme maximum na vsechny session uzivatele
                        SELECT
                            resource_id,
                            content_id,
                            ntile(10) OVER (PARTITION BY resource_id ORDER BY max(max_time) NULLS FIRST) AS percentile,
                            max(max_time) AS max_time
                        FROM
                            filtered
                        group by
                            resource_id,
                            content_id,
                            user_id
                    ) AS filteres
                    GROUP BY
                        resource_id,
                        content_id

                ) AS usr ON ses.resource_id = usr.resource_id";


		$count = $db->fetchOne($sel);
		return $count;
	}

    /**
     * Vrati data navstev (sessions) indetailu ke zobrazeni v monitoringu indetail visits.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @param   int     Id contentu - vzberou se jen sessions, kde byl zobrazen tento content
     */
    public static function indetail_getVisits($fromIso, $toIso, $repreId, $contentId)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        $filterByUsersReps = false;

        if (!Reps::checkSessionsByReps()) {
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
            $filterByUsersReps = true;
         } else {
            $repreWhere = "AND sr.repre_id='".(int)$repreId."'";
         }

        $sel = "
              select
                    CASE WHEN coalesce(sr.repre_id, 0) != 0 THEN coalesce(sr.repre_id, 0) ELSE null END as repre_id,
                    (COALESCE(reps.surname || ' ' || reps.name,reps.surname,reps.name)) as repre_name,
                    count(distinct s.id) as session_count,
                    count(distinct isp.id || '-' || isp.content_id) as pres_count,
                    count(distinct isr.id || '-' || isr.resource_id) as res_count,
                    count(distinct u.id) as user_count,
                    (coalesce(sum(isp.max_time),0) + coalesce(sum(isr.sum_time),0)) as sum_time,
                    (coalesce(sum(isp.max_time),0) + coalesce(sum(isr.sum_time),0))/count(distinct s.id) as avg_ses_time,
                    (coalesce(sum(isp.max_time),0) + coalesce(sum(isr.sum_time),0))/count(distinct u.id) as avg_usr_time
                from
                    users_vf as u
                join sessions as s on (u.id=s.user_id)
                ".($filterByUsersReps ? "left join users_reps as sr ON u.id = sr.user_id AND sr.repre_id = '".(int)$repreId."'":"left join sessions_by_reps as sr on (s.id=sr.session_id)")."
                left join (
        			SELECT isp.* FROM indetail_sessions_pres_v isp
        			-- Servier, cela navsteva musi byt delsi nez 10 s (nastavitelne)
                	".($config->indetail->minVisitTimeMs ?
                    "JOIN (
                        SELECT id, content_id FROM indetail_slides_v
                        GROUP BY id, content_id
                        HAVING sum(max_slide_time) > ".$config->indetail->minVisitTimeMs."
                    ) AS sessionFilter ON isp.id = sessionFilter.id AND isp.content_id = sessionFilter.content_id "
                    : '')."
        		) as isp on (s.id=isp.id)
                left join indetail_sessions_res_v as isr on (s.id=isr.id)
                left join resources res ON res.id = isr.resource_id
                left join users as reps on reps.id = sr.repre_id
                where
                    (isp.id is not null or isr.id is not null) AND --chci pocitat jen ty session, kde se neco ukazalo
                s.timestamp > '".$fromIso."' and
                s.timestamp < '".$toIso."'
                ".($repreId ? $repreWhere : '')."
                ".($contentId ? ' AND (isp.content_id = '.(int)$contentId.' OR res.content_id = '.(int)$contentId.')' : "" )."
                --".($config->indetail->minVisitTimeMs ? "AND isp.max_time > ".$config->indetail->minVisitTimeMs : '')." -- Omezeni pro Servier - podle casu session nemusi byt uplne shodne s omezenimi na jinych mistech
                group by
                    coalesce(sr.repre_id, 0),
                    reps.name,
                    reps.surname".
                /*($contentId ?
                    " HAVING
                    array_agg(isp.content_id) @> ARRAY[".(int)$contentId."]" : "").*/"
                order by
                    reps.surname
        ";

        //echo nl2br($sel); exit;

        $data = $db->fetchAll($sel);

        return $data;
    }


    /**
     * Vrati data monitoringu vazeb mezi prezentacemi v monitoringu indetailu.
     * Poradi shlednuti prezentaci.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     */
    public static function indetail_getConnectionsOrder($fromIso, $toIso, $repreId)
    {
        $db = Zend_Registry::get('db');

        if (!Reps::checkSessionsByReps()) {
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
         } else {
            $repreWhere = "AND sr.repre_id='".(int)$repreId."'";
         }

        $sel = "
            SELECT
                content_id,
                poradi,
                COUNT(*) AS pocet
            FROM (
                SELECT user_id, content_id, row_number() OVER (PARTITION BY user_id ORDER BY min_timestamp) AS poradi
                FROM (
                SELECT i.user_id, i.content_id, MIN(s.timestamp) AS min_timestamp
                FROM
                    users_vf as u
                    join sessions as s on (u.id=s.user_id)
                    join page_views as pv on (s.id=pv.session_id)
                    join indetail_stats AS i on (i.page_views_id=pv.id)
                    left join sessions_by_reps as sr on (s.id=sr.session_id)
                WHERE
                    s.timestamp > '".$fromIso."' and
                    s.timestamp < '".$toIso."'
                    ".($repreId ? $repreWhere : '')."
                GROUP BY i.user_id, i.content_id
                ) foo
            ) as foo2
            GROUP BY
                content_id,
                poradi
            ORDER BY content_id, poradi
        ";

        //echo nl2br($sel);

        $data = $db->fetchAll($sel);

        // Pripravime objekty jednotlivych contentu a jeich jmena
        foreach($data as $key => $row){
            $content = Contents::get($row['content_id']);
            if($content){
                $data[$key]['content_name'] = $content['object']->name;
            }
        }

        return $data;
    }

    /**
     * Vrati data monitoringu vazeb mezi prezentacemi v monitoringu indetailu.
     * Poradi shlednuti prezentaci.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     */
    public static function indetail_getConnectionsPresentationsSeen($fromIso, $toIso, $repreId)
    {
        $db = Zend_Registry::get('db');

         if (!Reps::checkSessionsByReps()) {
            $repreJoin = "JOIN users_reps as ur ON u.id = ur.user_id AND ur.repre_id = '".(int)$repreId."'";
         } else {
            $repreJoin = "JOIN sessions_by_reps sbr ON sbr.session_id = isp.id AND sbr.repre_id = '".(int)$repreId."'";
         }

        $sel = "
            -- kolik videli
            SELECT
                content_count,
                count(*) as user_count
            FROM
                (SELECT
                u.id,
                    count(distinct isp.content_id) as content_count
                FROM
                    users_vf as u
                    JOIN indetail_sessions_pres_v as isp ON (u.id=isp.user_id)
                    ".($repreId ? $repreJoin : '')."
                WHERE
                    isp.timestamp > '".$fromIso."' and
                    isp.timestamp < '".$toIso."'
                GROUP BY u.id
                ) as foo
            GROUP BY content_count
            ORDER BY content_count
        ";

        //echo nl2br($sel);

        $data = $db->fetchAll($sel);

        return $data;
    }

     /**
     * Vrati data monitoringu vazeb mezi prezentacemi v monitoringu indetailu.
     * Kolik uzivatelu shledlo kombinace prezentaci.
     *
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     */
    public static function indetail_getConnectionsPresentationsCombinations($fromIso, $toIso, $repreId, $contents = array())
    {

       $db = Zend_Registry::get('db');

        if (!Reps::checkSessionsByReps()) {
            $repreWhere = "AND u.id IN (SELECT user_id FROM users_reps WHERE repre_id = '".(int)$repreId."')";
         } else {
            $repreWhere = "AND sr.repre_id='".(int)$repreId."'";
         }

        $sel = "
            SELECT
                COUNT(*) AS count_least,
                SUM(CASE WHEN pp = ".count($contents)." THEN 1 ELSE 0 END) AS count_exactly
            FROM (
                SELECT user_id, count(user_id) as pp
                FROM (
                SELECT i.user_id, i.content_id, MIN(s.timestamp) AS min_timestamp
                FROM
                    users_vf as u
                    join sessions as s on (u.id=s.user_id)
                    join page_views as pv on (s.id=pv.session_id)
                    join indetail_stats AS i on (i.page_views_id=pv.id)
                    left join sessions_by_reps as sr on (s.id=sr.session_id)
                WHERE
                    s.timestamp > '".$fromIso."' and
                    s.timestamp < '".$toIso."' and
                    i.content_id IN (".implode(',',$contents).")
                    ".($repreId ? $repreWhere : '')."
                GROUP BY i.user_id, i.content_id
                ) foo
                GROUP BY user_id
            ) as foo2
        ";

        //echo nl2br($sel);
        //pole pro sestani nazvu
        $name = array();

        foreach ($contents as $c) {
            $cnt = Contents::get($c);
            $name[] = $cnt['name'];
        }

        $data = $db->fetchRow($sel);
        $data['name'] = implode(' | ',$name);
        $data['contentsId'] = $contents;

        return $data;

    }

    /**
     * Vrati tabulku prehledu odpovedi v dotaznicich pro dany content.
     *
     * @param $contentId Int        ID contentu.
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @param   int     Cislo slide, pokud je zadano soucasne s cislem otazky, je vracen jen radek dane otazky
     * @param   int     Cislo otazky, pokud je zadano soucasne s cislem slide, je vracen jen radek dane otazky
     * @return Array                Tabulka obsahujici prehled poctu odpovidajicich
     *                              pro jednotlive odpovedi.
     */
    public static function getAnswersOverviewTable($contentId, $fromIso = null, $toIso = null, $repreId = null, $slideNum = null, $questionNum = null)
    {
        $db = Zend_Registry::get('db');

        if (!Reps::checkSessionsByReps()) {
            $repreJoin = "JOIN users_reps as ur ON uvf.id = ur.user_id AND ur.repre_id = '".(int)$repreId."'";
            $repreJoin2 = "JOIN users_reps as ur ON s.user_id = ur.user_id AND ur.repre_id = '".(int)$repreId."'";
         } else {
            $repreJoin = "JOIN sessions_by_reps sbr ON sbr.session_id = s.id AND sbr.repre_id='".(int)$repreId."'";
            $repreJoin2 = "LEFT JOIN sessions_by_reps sbr ON sbr.session_id = s.id AND sbr.repre_id = '".(int)$repreId."'";
         }

        $sel = "
           WITH answers_overview AS (
                SELECT ao2.*
                FROM answers_overview_v ao2
                JOIN users_vf uvf ON uvf.id = ao2.user_id
                ".(($fromIso && $toIso) || $repreId ? "
                JOIN page_views pv ON pv.id = ao2.page_views_id
                JOIN sessions s ON pv.session_id = s.id AND s.timestamp > '$fromIso' AND s.timestamp < '$toIso' " : '')
                .($repreId ? $repreJoin : '')."
           )
           SELECT
             q.content_id,
             sl.slide_num,
             q.question_num,
             q.id AS question_id,
             q.text AS question_title,
             COALESCE(ao.answer_type, q.type) AS answer_type,
             a.id AS answer_id,
             a.answer_num AS answer_num,
             a.text AS answer_title,
             count(distinct(ao.user_id)) AS count, -- pocet unikatnich uzivatelu, kteri odpovedeli na moznost v odpovedi (answer)
             ao1.count AS question_sum, -- pocet unikatnich uzivatelu, kteri odpovedeli na otazku (question)
             ao1.numeric_sum,
             numeric_avg,
             b_answer_true,
             b_answer_false
           FROM questions q
           LEFT JOIN answers a ON q.id = a.question_id
           LEFT JOIN slides sl ON sl.id = q.slide_id
           -- Pridame counts na jednotlive answers
           LEFT JOIN answers_overview ao ON ao.question_id = q.id AND (ao.answer_id = a.id OR ao.answer_id IS NULL)
           LEFT JOIN (
                -- Ziskame celkovy pocet odpovedi pro kazdou question (count je zde na question)
                SELECT content_id, slide_num, question_id, count(distinct(user_id)) AS count,
                        sum(numeric_answer) AS numeric_sum, avg(numeric_answer) AS numeric_avg,
                        COALESCE(SUM(CASE WHEN  answer_title = 'true' THEN 1 END),0) AS b_answer_true,
                        COALESCE(SUM(CASE WHEN answer_title = 'false' THEN 1 END),0) AS b_answer_false
                FROM answers_overview
                GROUP BY content_id, slide_num, question_id
                ) ao1 ON sl.content_id = ao1.content_id AND sl.slide_num = ao1.slide_num
                        AND q.id = ao1.question_id
           ".(($fromIso && $toIso) || $repreId ? "
            LEFT JOIN page_views pv ON pv.id = ao.page_views_id
            LEFT JOIN sessions s ON pv.session_id = s.id AND s.timestamp > '$fromIso' AND s.timestamp < '$toIso' " : '')
            .($repreId ? $repreJoin2 : '')."
           WHERE
                q.content_id = $contentId
                ".($slideNum ? "
                    AND sl.slide_num = ".(int)$slideNum
                : '').
                ($slideNum && $questionNum ? "
                    AND q.question_num = ".(int)$questionNum
                : '').
           "
           GROUP BY
             q.content_id,
             sl.slide_num,
             q.question_num,
             q.id,
             q.text,
             coalesce(ao.answer_type, q.type),
             a.id,
             a.answer_num,
             a.text,
             ao1.count,
             ao1.numeric_sum,
             ao1.b_answer_true,
             ao1.b_answer_false,
             numeric_avg
           ORDER BY
             q.content_id,
             sl.slide_num,
             q.question_num,
             a.answer_num
           ";


        //echo nl2br($sel);exit;

        $table = $db->fetchAll($sel);

        return $table;
    }

    /**
     * Vrati tabulku odpovedi pro jednotlive uzivatele v dotazniku pro dany content.
     *
     * Pro vypis vsech odpovedi vynechavame v radcich, kde je stejny uzivatel
     * pod sebou (dany uzivatel odpovidal vicekrat), volne misto ve spolecnych datech uzivatele.
     * Prvni (nejnovejsi radek uzivatele) by mel vzdy obsahovat kompletni odpovedi poskladane
     * z nejnovejsich odpovedi uzivatele bez ohledu na page_views dane odpovedi.
     *
     * @param $contentId Int   ID contentu.
     * @param   string  Datum od kdy vypisujeme data v ISO8601
     * @param   string  Datum do kdy vypisujeme data v ISO8601
     * @param   int     Id reprezentanta
     * @return Array           Tabulka obsahujici pro kazdeho uzivatele jeho odpovedi pro
     *                         dany content.
     */
    public static function getAnswersUsersTable($contentId, $onlyLatest = true, $fromIso = null, $toIso = null, $repreId = null)
    {
        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet();

        if($onlyLatest){
            $sourceView = 'answers_latest_v';
        }else{
            $sourceView = 'answers_all_v';
        }

        // Data
        // Razeni s ohledem na hloubku exportu...
        if($onlyLatest){
            $order = array('user_id', 'slide_num', 'question_num', 'answer_num');
        }
        else{
            $order = array('user_id', 'pv_sorter DESC', 'slide_num', 'question_num', 'timestamp DESC', 'answer_num');
        }

        if (Reps::checkSessionsByReps()) {
            $repreJoin = "LEFT JOIN users_reps sbr ON uvf.id = sbr.user_id AND sbr.repre_id = '".$repreId."'";
         } else {
            $repreJoin = "LEFT JOIN sessions_by_reps sbr ON sbr.session_id = s.id ".($repreId ? " AND sbr.repre_id = '".(int)$repreId."'" : '');
         }

        // podle al.content_id pozname, ze konci doplnena pole pridana Monitoringexports::joinUsersData()
        $sel = "
            SELECT
                coalesce(reps.name,'')||' '||coalesce(reps.surname,'') AS session_rep, -- data skutecneho repa
                al.* -- al obsahuje user_id nutne pro spojeni s Monitoringexports::joinUsersData()
                --, uvf.name, uvf.surname, uvf.group, uvf.email,
               --array_to_string(array(SELECT u1.name||' '||u1.surname FROM users u1 JOIN users_reps ur ON u1.id = ur.repre_id WHERE ur.user_id = uvf.id), ', ') AS rep_names
            FROM $sourceView al
            JOIN users_vf uvf ON uvf.id = al.user_id
            -- Skutecny reprezentant a datum
            LEFT JOIN page_views pv ON pv.id = al.page_views_id
            LEFT JOIN sessions s ON pv.session_id = s.id ".(($fromIso && $toIso) ? " AND s.timestamp > '$fromIso' AND s.timestamp < '$toIso' " : '')."
            ".$repreJoin."
            LEFT JOIN users reps ON reps.id = sbr.repre_id
            WHERE content_id = ".(int)$contentId."
            ORDER BY
                ".join(', ', $order)."
        ";
        //echo nl2br($sel); exit;
        //$tableRaw = $db->fetchAll($sel);

        $tableRaw = Monitoringexports::joinUsersData($sel, $order);

        // Prnvni radek je "hlavicka" podle configu textu
        $headTexts = array_shift($tableRaw);



        // Ziskame neco, z ceho udelame hlavicku tabulky
        $sel = new Zend_Db_Select($db);
        $sel->from(array('q' => 'questions'), array('question_id' => 'id', 'question_num', 'question_title' => 'text', 'slide_id'))
            ->joinLeft(array('a' => 'answers'), 'q.id = a.question_id', array('answer_id' => 'id', 'answer_num', 'answer_title' => 'text'))
            ->joinLeft(array('s' => 'slides'), 's.id = q.slide_id', array('slide_num'))
            ->joinLeft(array('qtypes' => new Zend_Db_Expr("(SELECT \"type\", a.question_id FROM users_answers a
                JOIN (SELECT question_id, max(timestamp) AS timestamp FROM users_answers GROUP BY question_id) b
                ON a.timestamp = b.timestamp AND a.question_id = b.question_id)")), 'qtypes.question_id = q.id', array('question_type' => 'coalesce(q.type, qtypes.type)'))
            ->where('q.content_id = ?', $contentId)
            ->order(array('slide_num', 'question_num', 'answer_num'))
            ;
        //echo $sel;
        $tableHeadRaw = $db->fetchAll($sel);

        // Slozime z dat pro hlavicku dvouradkovou hlavicku
        $lastQId = null;
        $questions = array();
        // Pripravime prvni sloupce podle toho co se doplnilo
        $headEmptyExternalCols = array(); // POUZIJEME jako nahradu prazdnych bunek na zacatku tabulky
        $answersTxt = array();
        $headTextsExternalCols = array();
        foreach($headTexts as $key => $val){
            if($key == 'content_id' || $key == 'user_id'){
                break;
            }
            $headTextsExternalCols[$key] = $val;
            $headEmptyExternalCols[] = '';
            $answersTxt[] = $val;
        }
        // Pridame sloupce, ktere patri jen tomuto exportu
        $questionsTxt = array_merge($headEmptyExternalCols, array('', '', ''));
        $answersTxt = array_merge($answersTxt, array($texts->export_page_view_id, $texts->export_legth, $texts->export_time));

        $counter = -1;
        foreach($tableHeadRaw as $row){
            if($lastQId != $row['question_id']){
                $counter ++;
                $questions[$counter] = array('slide_id' => $row['slide_id'],
                                            'question_id' => $row['question_id'],
                                            'type' =>  $row['question_type'],
                                            'answers' => array());
                $questionsTxt[] = $row['slide_num'].'-'.$row['question_num'].': '.$row['question_title'];
            }
            else{
                $questionsTxt[] = null;
            }

            if(!empty($row['answer_num'])){
                $questions[$counter]['answers'][] = $row['answer_id'];
                $answersTxt[] = $row['answer_num'].': '.$row['answer_title'];
            }
            else{
                $answersTxt[] = null;
            }

            $lastAId = $row['question_id'];
            $lastQId = $row['question_id'];
        }

        //print_r($questions);


        /*
        echo "<style>.testtable td {border-collapse:collapse; border:#666 1px solid;}</style>
        <table class='testtable' style='border-collapse:collapse; border:#666 1px solid;'>";
        //*/





        // Vytvorime jednotlive radky tabulky
        // Pro vypis vsech odpovedi vynechavame v radcich, kde je stejny uzivatel
        // pod sebou (dany uzivatel odpovidal vicekrat), volne misto ve spolecnych datech uzivatele.
        // Prvni (nejnovejsi radek uzivatele) by mel vzdy obsahovat kompletni odpovedi poskladane
        // z nejnovejsich odpovedi uzivatele bez ohledu na page_views dane odpovedi.
        !empty($tableRaw) ? $tableRaw[] = array('user_id' => null) : null;
        $tableOut = array($questionsTxt, $answersTxt);
        $lastUser = null;
        $lastPVId = null;
        $lastSource = null;
        $lastTime = null;
        $rowSets = array();
        $filledQuestions = array();
        foreach($tableRaw as $row){
            if(empty($row['source'])){
                $row['source'] = '';
            }

            // Dalsi uzivatel
            if($lastUser != $row['user_id'] ||
                ($row['source'] == 'ual' && $lastPVId != $row['page_views_id']) ||
                ($lastSource != $row['source'])
               )
            {
                if($lastUser !== null){
                    foreach($rowSets as $key => $rowSet){
                        // Najdeme si jeste timestamp a time z $filledQuestions
                        $lastUserData['timestamp'] = $filledQuestions[$key]['biggestTimestamp'];
                        if(isset($lastUserData['time'])){
                            $time = $filledQuestions[$key]['biggestTime']/1000;
                            $dateParts = array('h' => 3600, 'm' => 60, 's' => 1);
                            $timeArray = array();
                            foreach($dateParts as $devider){
                                $pom = floor($time / $devider);
                                $time -= $pom * $devider;
                                $timeArray[] = sprintf('%02d', $pom);
                            }
                            $lastUserData['time'] = join(':', $timeArray);
                        }

                        // Vygenerujeme radek a zapiseme data do vystupni tabulky
                        $newRow = self::getAnswersUsersTableWriteRow($rowSet, $questions);
                        $tableOut[] = array_merge($lastUserData, $newRow);

                        // NOVY ZAZNAM
                        // Pro pristi radek staci v $lastUserData jen timestamp
                        $lastUserData = array_merge($headEmptyExternalCols, array('', '', 'timestamp' => null));
                    }
                }

                // Ukonceni
                if($row['user_id'] === null){
                    break;
                }

                // Data uzivatele
                if($lastUser != $row['user_id']){
                    // Naplnime data podle headTexts
                    $lastUserData = array();
                    foreach($headTextsExternalCols as $key => $val){
                        $lastUserData[] = $row[$key];
                    }
                    $lastUserData = array_merge($lastUserData, array($row['page_views_id'], 'time' => '', 'timestamp' => null));
                }
                elseif($row['source'] == 'ual' && $lastPVId != $row['page_views_id']){
                    $lastUserData = array_merge($headEmptyExternalCols, array($row['page_views_id'], 'time' => '', 'timestamp' => null));
                }
                elseif($lastSource != $row['source']){
                    $lastUserData = array_merge($headEmptyExternalCols, array($row['page_views_id'], 'time' => '', 'timestamp' => null));
                }

                $rowSets = array();
                $filledQuestions = array();
                $lastPVId = null;
                $lastSource = null;
                $lastTime = null;
            }

            // Najdeme kam mame tuto odpoved zapsat
            $emptyFound = false;
            $key = -1;
            $str = '';
            foreach($filledQuestions as $key => $filledQuestionsRow){
                // Hledame radek s jeste nevyplnenou touto otazkou, nebo s vyplnenou, ale
                // pri shodnem casu
                if(empty($filledQuestionsRow[$row['question_id']]) ||
                        (($filledQuestionsRow[$row['question_id']] == $row['timestamp'] || $row['source'] == 'ua') &&
                         $row['answer_type'] == 'c'))
                {
                    // Pridame radek z db
                    $rowSets[$key][] = $row;
                    $filledQuestions[$key][$row['question_id']] = $row['timestamp'];


                    // Nejvyssi casova znamka
                    if($filledQuestions[$key]['biggestTimestamp'] < $row['timestamp']){
                        $filledQuestions[$key]['biggestTimestamp'] = $row['timestamp'];
                        $filledQuestions[$key]['biggestTime'] = $row['max_time'];
                    }

                    $emptyFound = true;
                    break;
                }
            }
            if(!$emptyFound){
                $rowSets[$key+1][] = $row;
                $filledQuestions[$key+1][$row['question_id']] = $row['timestamp'];
                $filledQuestions[$key+1]['biggestTimestamp'] = $row['timestamp'];
                $filledQuestions[$key+1]['biggestTime'] = $row['max_time'];
            }



            $lastUser = $row['user_id'];
            $lastPVId === null ? $lastPVId = $row['page_views_id'] : null;
            $lastSource = $row['source'];
            $lastTime = $row['timestamp'];
        }

        /*
        foreach($tableOut as $row){
            echo '<tr><td>'.join('</td><td>', $row).'</td></tr>';
        }

        echo "</table>";
        exit;
        //*/

        return $tableOut;
    }


    /**
     * Zapise jeden radek uzivatele tak, aby odpovedi na otazky odpovidaly
     * nadpisum v hlavicce ($questions).
     *
     *
     * @param array $rows    Pole zaznamu z pohledu v DB
     * @param array $questions
     *
     * @return array    Doplnena tabulka.
     */
    private static function getAnswersUsersTableWriteRow($rows, $questions) {
        // OZNACENI VYBRANE ODPOVEDI radia nebo checkboxu
        $marker = ' X ';

        $newRow = array();

        // Prochazime pole hlavicky a vkladame na spravne misto v radku bud odpoved nebo prazdne misto
        foreach ($questions as $question) {

            // RADIO a CHECKBOX
            if ($question['answers']) {

                // Projdeme radek dane question a pridame je do pole dle answers_id
                $answers = array();
                foreach ($rows as $r) {
                    if ($r['question_id'] == $question['question_id']) {
                        $answers[$r['answer_id']] = $r;
                    }
                }

                // Prochazime answers v hlavicce tabulky
                foreach ($question['answers'] as $answerId) {

                    if ($question['type'] == 'r' || $question['type'] == 'c') {
                        // Zaskrtneme policko, pokud odpoved patri sem
                        if (isset($answers[$answerId])) {
                            $newRow[] = $marker;
                        }
                        // Pokud se nenalezla answer nebo nepatri do tohoto pole, proste nechame policko prazdne
                        else {
                            $newRow[] = '';
                        }
                    }
                    // Odpoved je cosi jineho nez radio nebo checkbox, tak zkusime vlozit text odpovedi
                    else {
                        $newRow[] = $answers[$answerId]['answer_num'] . ': ' .  $answers[$answerId]['answer_text'];
                    }
                }

            } else {

                // Najdeme radek dane question (pokud neexistuje, znamena to, ze vynechavame)
                $row = null;
                foreach ($rows as $r) {
                    if ($r['question_id'] == $question['question_id']) {
                        $row = $r;
                        break;
                    }
                }

                // Pokud pro tento sloupec podle hlavicky nic nemame, preskocime
                if (empty($row)) {
                    $newRow[] = '';
                }
                // OSTATNI TYPY krome RADIO a CHECKBOX
                // BOOL
                elseif ($row['answer_type'] == 'b') {
                    $newRow[] = $row['answer_bool'] ? $marker : '';
                }
                // Ostatni
                else {
                    // Zbyle odpovedi vypiseme jednoduse - vyplneno v DB ma byt jen jedno
                    $newRow[] = $row['answer_int'] . $row['answer_double'] . $row['answer_text'];
                }
            }
        }

        return $newRow;
    }


    /**
     * Vrati seznam uzivatelu s body, ktere ziskali v prezentaci
     * @param int ID contentu
     * @param array ID uzivatel, omezeni na konkretniho uzivatele
     * @return array seznam uzivatelu | data uživatele
     */
    public static function getUsersScores($contentID, $users = array()) {

        $db = Zend_Registry::get('db');

        $selectPoints = $db->select()->from('indetail_stats', array('user_id', 'max(points) as score'))
                        ->where('content_id = ?', $contentID)->group('user_id');

        $select = $db->select()->from(array('u' => 'users'))->join(array('ins' => $selectPoints), 'u.id = ins.user_id', array('score'))
                        ->where('deleted IS NULL')->where('test IS false')->order('surname');

        $select = Users::expandAttribs($select);

        if ($users) {
            $select->where('u.id IN (?)', $users);
            return $db->fetchAll($select);
        }

        return $db->fetchAll($select);
    }

}
