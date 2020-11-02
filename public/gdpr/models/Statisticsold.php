<?php

class StatisticsoldException extends Exception {}

/**
 *	Třída pro získávání statistik.
 *
 *	@author Martin Krčmář.
 */
class Statisticsold
{
    /**
     * Jedina instance tridy.
     *
     * @var Ibulletin_Auth
     */
    private static $_inst = null;

	/**
	 *	Jestli se do statistik budou započítávat i testovací uživatelé nebo ne.
	 */
//	protected static $test = '(true, false)';
	protected static $test = '(false)';
	
	/*
	 * Jaci users.target se maji zapocitavat do statistik? vsichni: '(true, false)' 
	 */
	protected static $target = '(true)';
	
	/*
     * Jaci users.klient se maji zapocitavat do statistik? vsichni: '(true, false)' 
     */
    protected static $klient = '(false)';
	
    
    
    /**
     * Vrati existujici instanci Statistics nebo vytvori novou a tu vrati.
     *
     * @return Statistics Jedina instance Statistics
     */
    public static function getInstance()
    {
        if(self::$_inst === null){
            self::$_inst = new Statisticsold();
        }
        
        return self::$_inst;
    }
    

	const TRUNC = 3;

	/**
	 *	Získá počet 'target' uživatelů. Tzn. těch, kteří mají ve sloupci target
	 *	hodnotu TRUE.
	 */
	public static function getTargetCount()
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = $db->select()
				->from('users',
					array('target' => 'count(*)'))
				->where('target = ?', TRUE)
				->where('test in '.self::$test);

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->target;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat počet TARGET uživatelů. '.$e);
		}
	}

	/**
	 * DEPRECATED
	 * Je nahrazena metodou getSubscribers(true)
	 * 
	 * 
	 *	Vrátí počet subscribers. Tzn. těch, kteří jsou target a nejsou smazaní.
	 */
	public static function xxgetSubscribersCount()
	{
	    $e = new Exception;
        Phc_ErrorLog::notice('Statisticsold', 'Pouzita DEPRECATED funkce:'."\n".$e);
	    
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = $db->select()
				->from('users',
					array('subscribed' => 'count(*)'))
				->where('target = ?', TRUE)
				->where('deleted IS NULL')
				->where('test in '.self::$test);

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->subscribed;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat počet SUBSCRIBRES uživatelů. '.$e);
		}
	}


	/**
	 * DEPRECATED
	 * Misto teto funkce pouzijeme getSelfregistered()
	 * 
	 *	Vrátí počet selfregistered.
	 */

	public static function xxgetSelfregisteredCount()
	{
	    $e = new Exception;
        Phc_ErrorLog::notice('Statisticsold', 'Pouzita DEPRECATED funkce:'."\n".$e);
	    
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = $db->select()
				->from('users',
					array('subscribed' => 'count(*)'))
				->where('selfregistred = ?', TRUE)
				->where('deleted IS NULL')
				->where('test in '.self::$test);

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->subscribed;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat počet SELFREGISTERED uživatelů. '.$e);
		}
	}
	
	/**
	 *	Vrátí seznam subscriberů.
	 * 
	 * @param bool Vratit pouze pocet?
	 * @param bool Pouze uzivatele s vyplnenym emailem/bez emailu (false)/ignorovat (null)
     * @param bool Pouze neanonymni uzivatele - vyplneno jmeno/prijmeni/login (true) | jen anonymni (false) |
     *             ignorovat (null)
	 */
	public static function getSubscribers($get_count = false, $withEmail = null, $nonAnonymousOnly = null)
	{
	    $inst = self::getInstance();
	    
	    // Pokud byla data jiz ziskana vratime ulozeny vysledek
	    /* 
	    if(isset($inst->subscribers)){
	        if(!$get_count){
	            return $inst->subscribers;
	        }
	        else{
	            return count($inst->subscribers);
	        }
	    }
	    elseif($get_count && isset($inst->subscribersCount)){
	        return $inst->subscribersCount;
	    }
	    */
	    
	    
	    
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');
        
		$select = $db->select()
            //->from('old_users_vv')
            ->where('uvv.deleted IS NULL')
            ->where('uvv.test in '.self::$test)
            ->where('uvv.klient in '.self::$klient)
            ->where('uvv.target in '.self::$target);
        
    	// Vracime vsechna data, nebo jen pocet?
        if(!$get_count){
            $select->from(array('uvv' => 'old_users_vv'))
                   ->order('email');
        }
        else{
            $select->from(array('uvv' => 'old_users_vv'), 'COUNT(distinct(uvv.id))');
        }
            
        
        // Uzivatele s emailem?
        if($withEmail){
            $select->where("uvv.email IS NOT NULL AND trim(both from uvv.email) != ''");
        }elseif($withEmail !== null){
            $select->where("uvv.email IS NULL OR trim(both from uvv.email) = ''");
        }
        
        // Anonymni uzivatel
    	if($nonAnonymousOnly){
            $select
                   ->where("(u.name IS NULL OR trim(both from u.name) = '') AND
                            (u.surname IS NULL OR trim(both from u.surname) = '') AND
                            (u.login IS NULL OR trim(both from u.login) = '')")
                   ->join(array('u' => 'users'), 'u.id = uvv.id', array())
                   ;
            
        }elseif($nonAnonymousOnly !== null){
            $select->where("(u.name IS NOT NULL AND trim(both from u.name) != '') OR
                            (u.surname IS NOT NULL AND trim(both from u.surname) != '') OR
                            (u.login IS NOT NULL AND trim(both from u.login) != '')")
                   ->join(array('u' => 'users'), 'u.id = uvv.id', array());
        }
		
		try
		{
    		// Vracime vsechna data, nebo jen pocet?
            if(!$get_count){
                $result = $db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
                $inst->subscribers = $result;
            }
            else{
                $result = $db->fetchOne($select);
                $inst->subscribersCount = $result;
            }
			
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			throw new StatisticsoldException(
				'Nepodařilo se získat seznam SUBSCRIBRES uživatelů. '.$e.$select);
		}
	}

	/**
	 *	Vrátí procentuální podíl subscribers uživatelů z celkového počtu
	 * 	target uživatelů.
	 *
	 * @param Na kolik desetinných míst se bude výsledek ořezávat.
	 * @param bool Pouze uzivatele s vyplnenym emailem/bez emailu (false)/ignorovat (null)
     * @param bool Pouze neanonymni uzivatele - vyplneno jmeno/prijmeni/login (true) | jen anonymni (false) |
     *             ignorovat (null)
	 */
	public static function getSubscribersPercent($trunc = self::TRUNC, $withEmail = null, $nonAnonymousOnly = null)
	{
	    $config = Zend_Registry::get('config');
	    
		try
		{
			$subscribers = Statisticsold::getSubscribers(true, $withEmail, $nonAnonymousOnly);
		}
		catch (StatisticsoldException $e)
		{
			throw new StatisticsoldException(
				'Nepodařilo se získat procentuální podíl subscribers uživatelů.
				'.$e);
		}
		
		$target = $config->stats->target;
        
        $percent =  $target ? (round($subscribers * 100 / $target, $trunc)) : ' - ';
        
        return $percent;
	}

	/**
	 *  DEPRECATED!
	 *  Misto teto funkce pouzijeme getUnsubscribed(true).
	 *  
	 *	Vrátí počet unsubscribed uživatelů, ti kteří jsou smazaní a zároveň
	 *	target.
	 */
	public static function xxgetUnsubscribedCount()
	{
	    $e = new Exception;
	    Phc_ErrorLog::notice('Statisticsold::getUnsubscribedCount', 'Pouzita DEPRECATED funkce:'."\n".$e);
	    
	    
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = $db->select()
				->from('users',
					array('unsubscribed' => 'count(*)'))
				->where('target = ?', TRUE)
				->where('deleted IS NOT NULL')
				->where('test in '.self::$test);

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->unsubscribed;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat počet UNSUBSCRIBED uživatelů. '.$e);
		}
	}

	/**	15.10.08 RV
	 *	Vrátí seznam odhlasenych, nebo pocet odhlasenych.
	 *
	 * @param  Vracet jen pocet? Default: false
	 */
	public static function getUnsubscribed($get_count = false)
	{
	   $inst = self::getInstance();
        
        // Pokud byla data jiz ziskana vratime ulozeny vysledek
        if(isset($inst->unsubscribed)){
            if(!$get_count){
                return $inst->unsubscribed;
            }
            else{
                return count($inst->unsubscribed);
            }
        }
        elseif($get_count && isset($inst->unsubscribedCount)){
            return $inst->unsubscribedCount;
        }
	    
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = $db->select()
				->where('target = ?', TRUE)
				->where('deleted IS NOT NULL')
				->where('test in '.self::$test)
				->where('klient in '.self::$klient)
				->where('target in '.self::$target);
			$fetchMode = $db->getFetchMode();
			
			// Vracime vsechna data, nebo jen pocet?
			if(!$get_count){
			    $select->from('old_users_vv');
    			$db->setFetchMode(Zend_Db::FETCH_OBJ);
    			$result = $db->fetchAll($select);
    			$db->setFetchMode($fetchMode);
    			$inst->unsubscribed = $result;
			}
			else{
			    $select->from('old_users_vv', 'COUNT(*)');
			    $result = $db->fetchOne($select);
			    $inst->unsubscribedCount = $result;
			}
			
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{   
		    if(!$get_count){
			     $db->setFetchMode($fetchMode);
		    }
			throw new StatisticsoldException(
				'Nepodařilo se získat seznam UNSUBSCRIBED uživatelů. '.$e);
		}
	}
	
    /**
     *  Vrátí seznam uzivatelu, kteri maji v nektereho mailu status bad_address.
     *
     * @param  Vracet jen pocet? Default: false
     */
    public static function getBadAddress($get_count = false)
    {
       $inst = self::getInstance();
        
        // Pokud byla data jiz ziskana vratime ulozeny vysledek
        if(isset($inst->BadAddress)){
            if(!$get_count){
                return $inst->BadAddress;
            }
            else{
                return count($inst->BadAddress);
            }
        }
        elseif($get_count && isset($inst->BadAddressCount)){
            return $inst->BadAddressCount;
        }
        
        
        
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        
        $select = new Zend_Db_Select($db);
        // Vracime vsechna data, nebo jen pocet?
        if(!$get_count){
            $select->from(array('uvv' => 'old_users_vv'));
        }
        else{
            $select->from(array('uvv' => 'old_users_vv'), 'COUNT(*)');
        }
        
        $select 
                ->where('deleted IS NULL')
                ->where('test in '.self::$test)
                ->where('klient in '.self::$klient)
                ->where('target in '.self::$target)
                ->where("uvv.id in (SELECT distinct(user_id) FROM users_emails WHERE status = 'bad_addr')")
                ;
                
        try
        {
            // Vracime vsechna data, nebo jen pocet?
            if(!$get_count){
                $result = $db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
                $inst->BadAddress = $result;
            }
            else{
                $result = $db->fetchOne($select);
                $inst->BadAddressCount = $result;
            }
            
            return $result;
        }
        catch (Zend_Db_Exception $e)
        {   
            throw new StatisticsoldException(
                'Nepodařilo se získat seznam bad_address uživatelů. '.$e);
        }
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
            $select->from(array('u' => 'users'));
        }
        else{
            $select->from(array('u' => 'users'), 'COUNT(*)');
        }
        
        $select 
                ->joinLeft(array('uvv' => 'old_users_vv'), 'u.id = uvv.id', array())
                ->where('u.send_emails')
                ->where('u.test in '.self::$test)
                ->where('u.klient in '.self::$klient)
                ->where('u.target in '.self::$target)
                ->where("u.id in ($usersSeenVideo)")
                ;
        
        try
        {
            // Vracime vsechna data, nebo jen pocet?
            if(!$get_count){
                $result = $db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
            }
            else{
                $result = $db->fetchOne($select);
            }
            
            return $result;
        }
        catch (Zend_Db_Exception $e)
        {   
            throw new StatisticsoldException(
                'Nepodařilo se získat seznam videoWatchers uživatelů. '.$e);
        }
    }

	/**	15.10.08 RV
	 *	Vrátí seznam selfregistered.
	 */
	public static function getSelfregistered($get_count)
	{
	   $inst = self::getInstance();
        
        // Pokud byla data jiz ziskana vratime ulozeny vysledek
        if(isset($inst->selfregistered)){
            if(!$get_count){
                return $inst->selfregistered;
            }
            else{
                return count($inst->selfregistered);
            }
        }
        elseif($get_count && isset($inst->selfregisteredCount)){
            return $inst->selfregisteredCount;
        }
        
        
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = $db->select()
				//->from('old_users_vv')
				->where('deleted IS NULL')
				->where('selfregistred = ?', TRUE)
				->where('test in '.self::$test)
                ->where('klient in '.self::$klient)
                ->where('target in '.self::$target);
            
		    $fetchMode = $db->getFetchMode();
            
            // Vracime vsechna data, nebo jen pocet?
            if(!$get_count){
                $select->from('old_users_vv');
                $db->setFetchMode(Zend_Db::FETCH_OBJ);
                $result = $db->fetchAll($select);
                $db->setFetchMode($fetchMode);
                $inst->selfregistered = $result;
            }
            else{
                $select->from('old_users_vv', 'COUNT(distinct(id))');
                $result = $db->fetchOne($select);
                $inst->selfregisteredCount = $result;
            }
			
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat seznam SUBSCRIBRES uživatelů. '.$e);
		}
	}


	
	/**
	 *	 Vrátí skupiny uživatelů, a ke každé skupině počet
	 *	 jejich uživatelů.
	 */
	public static function getGroupsCounts()
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = $db->select()
				->from('users',
					array('users' => 'count(*)', 'group'))
				->where('deleted IS NULL')
				->where('test IN '.self::$test)
                ->where('klient IN '.self::$klient)
                ->where('target IN '.self::$target)
				->group('group')
				->order('group ASC');

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$db->setFetchMode($fetchMode);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat seznam skupin a jejich uživatelů. '.$e);
		}
	}

	/**
	 *	Vrátí reprezentanty a počty jejich uživatelů.
	 */
	public static function getRepsCounts()
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = $db->select()
				->from(array('u' => 'users'),
					array('users' => 'count(u.*)', 'u.rep'))
				->joinLeft(array('r' => 'users'),
						'u.rep = r.id',
						array('r.id', 'r.name', 'r.surname'))
				->where('u.deleted IS NULL')
				->where('u.test IN '.self::$test)
                ->where('u.klient IN '.self::$klient)
                ->where('u.target IN '.self::$target)
				->group('u.rep')
				->group('r.id')
				->group('r.name')
				->group('r.surname')
				->order('u.rep ASC');

// Build this query:
//   SELECT p."product_id", p."product_name", l.*
//   FROM "products" AS p JOIN "line_items" AS l
//     ON p.product_id = l.product_id

//$select = $db->select()
//             ->from(array('p' => 'products'),
//                    array('product_id', 'product_name'))
//             ->join(array('l' => 'line_items'),
//                    'p.product_id = l.product_id');

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$db->setFetchMode($fetchMode);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
		    $db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat seznam reprezentantů a jejich uživatelů. '.$e);
		}
	}

	/**
	 * DEPRECATED
	 * Misto teto funkce pouzijeme funkci getReaders(true)
	 * 
	 *	Vrátí počet reades, ti ze subscribers, kteří alespoň jednou přišli na
	 *	web.
	 *
	 *	// TODO jestli staci jenom zaznam ze session. Protoze to muze taky
	 *	znamenat, ze se pouze zaregistrovali, ale uz neprisli. Coz ted pri
	 *	soucasnem stavu informace v page_views nepujde zrovna lehce vyfiltrovat.
	 *	Ta stejna uprava by se potom tykala dalsi funkce !!
	 */
	public static function xxgetReadersCount()
	{
	    $e = new Exception;
        Phc_ErrorLog::notice('Statisticsold', 'Pouzita DEPRECATED funkce:'."\n".$e);
        
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = "SELECT count(*) AS readers
			           FROM users u
					   WHERE id IN (
						   SELECT distinct(u.id)
						   FROM sessions s LEFT JOIN users u
						   ON s.user_id = u.id
						   INNER JOIN page_views pv ON pv.session_id = s.id WHERE pv.action = 'bulletin' OR pv.action = 'page' OR pv.action = 'category'
						)
					   AND u.deleted IS NULL 
					   AND u.test IN ".self::$test.'
					   AND u.target = TRUE';

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->readers;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);

			throw new StatisticsoldException(
				'Nepodařilo se získat počet READERS uživatelů. '.$e);
		}
	}

	/**
	 *	Vrátí seznam readers, viz. getReadersCount().
	 */
	public static function getReaders($get_count)
	{
	    $inst = self::getInstance();
        
        // Pokud byla data jiz ziskana vratime ulozeny vysledek
        if(isset($inst->readers)){
            if(!$get_count){
                return $inst->readers;
            }
            else{
                return count($inst->readers);
            }
        }
        elseif($get_count && isset($inst->readersCount)){
            return $inst->readersCount;
        }
        
	    
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
		    if(!$get_count){
		        $columns = '*';
		        $order = 'ORDER BY email ASC';
		    }
		    else{
		        $columns = 'count(*)';
                $order = '';
		    }
		    
		    /*
		    // Pokud je v konfigu nastavene, ze se jedna jen o platna cisla, 
		    // musime udelat nejakou divocinu...
		    if($config->stats->only_valid){
		        $only_valid = '
		        SELECT 
		        '
		    }
		    */
		    
			$select = "SELECT $columns
			           FROM old_users_vv u
					   WHERE id IN (
						   SELECT distinct(u.id)
						   FROM sessions s LEFT JOIN users u
						   ON s.user_id = u.id
						   INNER JOIN page_views pv ON pv.session_id = s.id 
						   WHERE pv.bulletin_id IS NOT NULL OR pv.page_id IS NOT NULL OR pv.category_id IS NOT NULL
						)
					   AND u.deleted IS NULL 
					   AND u.test IN ".self::$test."
					   AND u.target IN ".self::$target."
					   AND u.klient IN ".self::$klient."
					   $order";
            
		    $fetchMode = $db->getFetchMode();
            
            // Vracime vsechna data, nebo jen pocet?
            if(!$get_count){
                $db->setFetchMode(Zend_Db::FETCH_OBJ);
                $result = $db->fetchAll($select);
                $db->setFetchMode($fetchMode);
                $inst->readers = $result;
            }
            else{
                $result = $db->fetchOne($select);
                $inst->readersCount = $result;
            }
					   
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);

			throw new StatisticsoldException(
				'Nepodařilo se získat seznam READERS uživatelů. '.$e);
		}
	}

	/**
	 *	Vrátí procentuální podíl readers z subscribers.
	 *
	 *	@param Na kolik desetinných míst se bude výsledek ořezávat.
	 */
	public static function getReadersPercent($trunc = self::TRUNC)
	{
		

		try
		{
		    $subscribers = Statisticsold::getSubscribers(true);
		    $readers = Statisticsold::getReaders(true);
		}
		catch (StatisticsoldException $e)
		{
			throw new StatisticsoldException(
				'Nepodařilo se získat procentuální podíl readers uživatelů.
				'.$e);
		}
		
		$percent =  $subscribers ? (round($readers * 100 / $subscribers, $trunc)) : ' - ';
		
		return $percent;
	}

	/**
	 *	Vrací response rate konkrétního čísla bulletinu. Tzn. procentuální podíl
	 *	prokliků přes email. Kolik procent uživatelů přislo na číslo bulletinu
	 *	přes nějaký email, který byl k bulletinu poslán.
	 *
	 *	@param Číslo bulletinu.
	 *	@param Zaokrouhlení.
	 */
	public static function getResponseRateForBulletin($bulletin, $trunc = self::TRUNC)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');
		
		$sel_part = "SELECT count(distinct(u.id))
                            FROM users u
                            WHERE id IN (
                                SELECT s.user_id
                                FROM page_views pv 
                                JOIN sessions s ON pv.session_id = s.id
                                JOIN users_links_tokens ult ON ult.id = pv.users_links_tokens_id
                                WHERE ult.email_id IN (
                                    SELECT e.id 
                                    FROM emails e
                                    JOIN invitation_waves iv ON e.invitation_id = iv.id
                                    WHERE iv.bulletin_id = $bulletin 
                                )
                            )
                           AND u.test IN ".self::$test."
                           AND u.target IN ".self::$target."
                           AND u.klient IN ".self::$klient;
		
		$sel_whole = "SELECT count(distinct(u.id))
                    FROM users u
                    WHERE id IN (
                        SELECT distinct(u.id)
                        FROM users u INNER JOIN users_emails ue ON u.id = ue.user_id
                        INNER JOIN emails e ON ue.email_id = e.id
                        INNER JOIN invitation_waves iw ON e.invitation_id = iw.id
                        INNER JOIN bulletins b ON b.id = iw.bulletin_id
                        WHERE b.id = $bulletin
                    )
                       AND u.test IN ".self::$test."
                       AND u.target IN ".self::$target."
                       AND u.klient IN ".self::$klient;

		try
		{
		    $part = $db->fetchOne($sel_part);
		    $whole = $db->fetchOne($sel_whole);
		}
		catch (Zend_Db_Exception $e)
		{
			throw new StatisticsoldException(
				'Nepodařilo se získat response rate. '.$e);
		}
		
		$rate = $whole ? (round($part * 100 / $whole, $trunc)) : false;
		return $rate;
	}

	/**
	 *	Vrátí response rate pro poslední bulletin, plus průměr za poslední čísla.
	 *
	 *	@param Za kolik posledních čísel se průměr bude počítat.
	 *	@param Na kolik míst se bude osekávat výsledek.
	 *	@return Array('response_rate' => response rate posledního čísla,
	 *                'avg_response_rate' => průměr za poslední tři čísla).
	 *	@param Pokud TRUE, bude vracet statistiky pouze pro bulletiny, které už jsou platné.
	 */
	public static function getResponseRatePlusAvg($num = 3, $trunc = self::TRUNC, $onlyValid = FALSE)
	{
		$lastBulletins = self::getBulletins('DESC', $num, $onlyValid);
		$responseRates = 0;
		$first = null;
		foreach ($lastBulletins as $bulletin)
		{
			$responseRate = self::getResponseRateForBulletin($bulletin->id, $trunc);
			if ($responseRate)
				$responseRates += $responseRate;

			if ($first === null)
				$first = $responseRates;
		}

		if ($first !== null)
		{
			return Array(
				'response_rate' => $first,
				'avg_response_rate' => round($responseRates / count($lastBulletins), $trunc)
			);
		}

		return Array();
	}

	/**
	 *  DEPRECATED 
	 *  
	 *	Vrátí počet čtenářů daného čísla.
	 *	zmena 30.10.08 R.V.
	 *	@param Číslo bulletinu.
	 */
	public static function xxgetReadersForBulletin($bulletin)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$select = "SELECT count(*) AS return_rate
				   FROM users u
					WHERE id IN (SELECT distinct(user_id) FROM old_page_views_v pv
									where (pv.action = 'bulletin' OR pv.action = 'page' OR pv.action = 'category') 
										and bulletin_id = $bulletin
								)
						AND u.deleted IS NULL
						AND u.target = TRUE
						AND u.test IN ".self::$test;
        
		$select_xxxtest = "SELECT count(*) AS return_rate
                   FROM users u
                    WHERE id IN (SELECT distinct(user_id) FROM old_page_views_v pv
                                    where (pv.page_id IS NOT NULL OR pv.bulletin_id IS NOT NULL OR pv.category_id IS NOT NULL) 
                                        and bulletin_id = $bulletin
                                )
                        
                        AND u.target = TRUE
                        AND u.test IN ".self::$test;
		
		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->return_rate;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat readersPerBulletin.'.$e);
		}
	}


	/**	
	 *	Vrátí počet navrativsich se čtenářů daného čísla (z minuleho cisla).
	 *	R.V.
	 *	@param Číslo bulletinu.
	 */
	public static function getRetReadersForBulletin($bulletin)
	{
		$db = Zend_Registry::get('db');

		$select = "SELECT count(distinct(user_id))
		           FROM old_page_views_v 
				   WHERE 
				    bulletin_id = $bulletin
				    AND user_id IN (SELECT user_id FROM old_page_views_v WHERE bulletin_id = $bulletin - 1)
				    AND user_id IN (
				        SELECT id 
				        FROM users u
				        WHERE  
				            u.test IN ".self::$test."
                            AND u.target IN ".self::$target."
                            AND u.klient IN ".self::$klient."
			            )";
		try
		{
			$result = $db->fetchOne($select);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			throw new StatisticsoldException(
				'Nepodařilo se získat readersPerBulletin.'.$e);
		}
	}

	
	/**
	 *	Vrátí seznam čtenářů daného čísla.
	 *
	 *	@param Číslo bulletinu.
	 * @param bool Vratiti pouze pocet?
	 */
	public static function getReadersForBulletin($bulletin, $get_count = false)
	{
	$inst = self::getInstance();
        
        // Pokud byla data jiz ziskana vratime ulozeny vysledek
        if(isset($inst->bullReaders[$bulletin])){
            if(!$get_count){
                return $inst->bullReaders[$bulletin];
            }
            else{
                return count($inst->bullReaders[$bulletin]);
            }
        }
        elseif($get_count && isset($inst->bullReadersCount[$bulletin])){
            return $inst->bullReadersCount[$bulletin];
        }
	    
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');
        
    	if(!$get_count){
            $columns = '*';
            $order = 'ORDER BY u.email ASC';
        }
        else{
            $columns = 'count(*)';
            $order = '';
        }
		
		$select = "SELECT $columns
				   FROM old_users_vv u
					WHERE id IN (SELECT distinct(user_id) FROM old_page_views_v pv
									WHERE bulletin_id = $bulletin
								)
					AND u.test IN ".self::$test."
                    AND u.target IN ".self::$target."
                    AND u.klient IN ".self::$klient."
					$order";
        
		$fetchMode = $db->getFetchMode();
        
		try
		{
            // Vracime vsechna data, nebo jen pocet?
            if(!$get_count){
                $db->setFetchMode(Zend_Db::FETCH_OBJ);
                $result = $db->fetchAll($select);
                $db->setFetchMode($fetchMode);
                $inst->bullReaders[$bulletin] = $result;
            }
            else{
                $result = $db->fetchOne($select);
                $inst->bullReadersCount[$bulletin] = $result;
            }
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat readersListForBulletin.'.$e);
		}
		
		return $result;
	}

	/**
	 * DEPRECATED
	 * 
	 *	Počet uživatelů, kterým byl odeslán alespoň jeden email z konkrétního
	 *	čísla.
	 *
	 *	@param Id bulletinu.
	 */
	public static function xxgetSubscribersCountForBulletin($bulletinId)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$select = "SELECT count(*) AS subscribers_mails
		           FROM users u
				   WHERE u.deleted IS NULL
				   	AND u.test IN ".self::$test."
					AND u.target = TRUE
					AND u.id IN (
						SELECT distinct(u.id)
						FROM users u INNER JOIN users_emails ue ON u.id = ue.user_id
						INNER JOIN emails e ON ue.email_id = e.id
						INNER JOIN invitation_waves iw ON e.invitation_id = iw.id
						INNER JOIN bulletins b ON b.id = iw.bulletin_id
						WHERE b.id = $bulletinId
					)";

		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			
			return $result->subscribers_mails;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat subscribersCountForBulletin.'.$e);
		}
	}

	/**
	 *	Seznam uživatelů, kterým byl odeslán alespoň jeden email z konkrétního
	 *	čísla.
	 *
	 *	@param Id bulletinu.
	 * @param Vracime jen pocet?
	 */
	public static function getSubscribersForBulletin($bulletinId, $get_count = false)
	{
	    $inst = self::getInstance();
        
        // Pokud byla data jiz ziskana vratime ulozeny vysledek
        if(isset($inst->bullSubscribers[$bulletinId])){
            if(!$get_count){
                return $inst->bullSubscribers[$bulletinId];
            }
            else{
                return count($inst->bullSubscribers[$bulletinId]);
            }
        }
        elseif($get_count && isset($inst->bullSubscribersCount[$bulletinId])){
            return $inst->bullSubscribersCount[$bulletinId];
        }
	    
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');
        
	   if(!$get_count){
            $columns = '*';
            $order = 'ORDER BY u.email ASC';
        }
        else{
            $columns = 'count(*)';
            $order = '';
        }
		
		$select = "SELECT $columns
		           FROM old_users_vv u
				   WHERE 
				    u.test IN ".self::$test."
                    AND u.target IN ".self::$target."
                    AND u.klient IN ".self::$klient."
					AND u.id IN (
						SELECT distinct(u.id)
						FROM users u INNER JOIN users_emails ue ON u.id = ue.user_id
						INNER JOIN emails e ON ue.email_id = e.id
						INNER JOIN invitation_waves iw ON e.invitation_id = iw.id
						INNER JOIN bulletins b ON b.id = iw.bulletin_id
						WHERE b.id = $bulletinId
						  AND (ue.status != 'undelivered' OR ue.status IS NULL)
						  AND ue.sent IS NOT NULL
					)
					$order";
        
		$fetchMode = $db->getFetchMode();
        
		try
		{
            // Vracime vsechna data, nebo jen pocet?
            if(!$get_count){
                $db->setFetchMode(Zend_Db::FETCH_OBJ);
                $result = $db->fetchAll($select);
                $db->setFetchMode($fetchMode);
                $inst->bullSubscribers[$bulletinId] = $result;
            }
            else{
                $result = $db->fetchOne($select);
                $inst->bullSubscribersCount[$bulletinId] = $result;
            }
                       
            return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat subscribersForBulletin.'.$e);
		}
	}

	/**
	 * DEPRECATED
	 * 
	 *	Vrátí vždy bulletin a počet uživatelů, kterým byl odeslán alespoň jeden
	 *	email z konkrétního bulletinu.
	 *	Výsledek stejný, jako by se pro každý bulletin volala funkce
	 *	getSubscribersCountForBulletin(Id).
	 */
	public static function xxgetSubscribersForAllBulletins()
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$select = 'SELECT bs.bulletin_id, bs.name, count(*) AS bulletins_subscribers
				   FROM (
					  SELECT b.name, b.id AS bulletin_id, u.id AS user_id
					  FROM users u 
					  INNER JOIN users_emails ue ON u.id = ue.user_id
					  INNER JOIN emails e ON ue.email_id = e.id
					  INNER JOIN invitation_waves iw ON e.invitation_id = iw.id
					  INNER JOIN bulletins b ON b.id = iw.bulletin_id
					  WHERE u.test IN '.self::$test.'
						  AND u.target = TRUE
						  AND u.deleted IS NULL
					  GROUP BY b.name, b.id, u.id
				  ) AS bs
				  GROUP BY bs.bulletin_id, bs.name';
		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$db->setFetchMode($fetchMode);

			$retVal = Array();
			foreach ($result as $bs)
			{
				$retVal[$bs->bulletin_id] = Array(
					'name' => $bs->name,
					'bulletins_subscribers' => $bs->bulletins_subscribers
				);
			}
			
			return $retVal;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat subscribers for all bulletins.'.$e);
		}
	}


	/**	9.10.08 R.V.
	 *	Vrátí seznam uživatelů ve skupine
	 */
	public static function getGroupUsers($groupa)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');
		try
		{
		if ($groupa <> 'NULL'){
//			echo 'zadano'.$groupa;
			$select = $db->select()
				->from('old_users_vv')
				->where('deleted IS NULL')
//				->where("'group' = ?",$groupa)
				->where("skupina = ?",$groupa)
				->where('test IN '.self::$test)
                ->where('klient IN '.self::$klient)
                ->where('target IN '.self::$target)
				->order('id');
		}
		else{
//			echo 'nezadano'.$groupa;
			$select = $db->select()
				->from('old_users_vv')
				->where('deleted IS NULL')
				->where("skupina = '' or skupina is NULL")
				->where('test IN '.self::$test)
                ->where('klient IN '.self::$klient)
                ->where('target IN '.self::$target)
				->order('id');
		}
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$db->setFetchMode($fetchMode);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat seznam GROUPUS uživatelů. '.$e);
		}
	}

	/**	10.08 R.V.
	 *	Vrátí seznam uživatelů za reprezentanta
	 */
	public static function getRepUsers($rep)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');
		try
		{
		if ($rep <> 0){
			$select = $db->select()
				->from('old_users_vv')
				->where('deleted IS NULL')
				->where("rep = ?",$rep)
				->where('test IN '.self::$test)
                ->where('klient IN '.self::$klient)
                ->where('target IN '.self::$target)
				->order('id');
		}
		else{
			$select = $db->select()
				->from('old_users_vv')
				->where('deleted IS NULL')
				->where("rep = 0 or rep is NULL")
				->where('test IN '.self::$test)
                ->where('klient IN '.self::$klient)
                ->where('target IN '.self::$target)
				->order('id');
		}
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$db->setFetchMode($fetchMode);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat seznam REPS uživatelů. '.$e);
		}
	}


	/**
	 *	Vrátí počet návštěv (sessions) ke konkrétnímu bulletinu.
	 *
	 *	@param Id bulletinu.
	 */
	public static function getVisitsForBulletin($bulletinId)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');
        
		/*
		$select = "SELECT count(distinct(s.id)) AS visits
                    FROM sessions s INNER JOIN page_views pv ON s.id = pv.session_id
                    INNER JOIN users u ON s.user_id = u.id
                    WHERE (s.user_id IS NOT NULL)
                        AND (
                            link_id IN (
                                SELECT l.id FROM links l
                                INNER JOIN bulletins_pages bp ON l.page_id = bp.page_id
                                WHERE bp.bulletin_id = $bulletinId
                            )
                            OR link_id IN (
                                SELECT l.id FROM links l
                                WHERE bulletin_id = $bulletinId
                            )
                            OR link_id IN (
                                SELECT l.id FROM links l
                                INNER JOIN categories c ON l.category_id = c.id
                                INNER JOIN pages_categories pc ON c.id = pc.category_id
                                INNER JOIN bulletins_pages bp ON bp.page_id = pc.page_id
                                WHERE bp.bulletin_id = $bulletinId
                            )
                            OR page_id IN (
                                SELECT page_id FROM bulletins_pages
                                WHERE bulletin_id = $bulletinId
                            )
                            OR bulletin_id = $bulletinId
                        )
                        AND u.deleted IS NULL
                        AND u.test IN ".self::$test."
                        AND u.target IN ".self::$target."
                        AND u.klient IN ".self::$klient;
		*/
		
		$select = "SELECT count(distinct(s.id)) AS visits
					FROM sessions s INNER JOIN page_views pv ON s.id = pv.session_id
					INNER JOIN users u ON s.user_id = u.id
					WHERE s.user_id IS NOT NULL
						AND bulletin_id = $bulletinId
						AND u.test IN ".self::$test."
                        AND u.target IN ".self::$target."
                        AND u.klient IN ".self::$klient."";

		try
		{
			$result = $db->fetchOne($select);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			throw new StatisticsoldException(
				'Nepodařilo se získat subscribers for all bulletins.'.$e);
		}
	}

	/**
	 *	Vrátí seznam bulletinů, řazený od nejstaršího po nejnovější.
	 *
	 *	@param Řazení výsledku.
	 *	@param Počet vrácených bulletinů, defaultně všechny.
	 *	@param Pokud TRUE, bude vracet pouze bulletiny, které už jsou platné.
	 */
	public static function getBulletins($order, $num = null, $onlyValid = FALSE)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		// TODO jestli brat i bulletiny, ktere maji cas platnosti nastaven na
		// budoucnost, budou asi blbe ovlivnovat statistiky
		try
		{
			$select = $db->select()
				->from('bulletins')
				->order("valid_from $order");

			if ($onlyValid)
				$select->where('valid_from < current_timestamp');

			if ($num !== null)
				$select->limit($num);

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$db->setFetchMode($fetchMode);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat seznam bulletinů.'.$e);
		}
	}

	/**
	 *	Vrátí return rate pro poslední bulletin, plus průměr za poslední čísla.
	 *
	 *	@param Za kolik posledních čísel se průměr bude počítat.
	 *	@param Na kolik míst se bude osekávat výsledek.
	 *	@return Array('return_rate' => return rate posledního čísla,
	 *                'avg_return_rate' => průměr za poslední tři čísla).
	 *	@param Pokud TRUE, bude vracet statistiky pouze pro bulletiny, které už jsou platné.
	 */
	public static function getReturnRatePlusAvg($num = 3, $trunc = self::TRUNC, $onlyValid = FALSE)
	{
		// vezmou se 4 posledni bulletiny, pro treti se return rate pocita jako
		// readers(3) / readers(4), proto 4 posledni bulletiny
		$lastBulletins = self::getBulletins('DESC', $num + 1, $onlyValid);
		// otocime pole, bude se prochazet od 4.teho po nejnovejsi
		krsort($lastBulletins);

		$previous = null;
		$previousReaders = 0;
		$lastReturnRate = null;
		$returnRates = 0;
		$numBulletins = 0; 			// celkovy pocet bulletinu, ze kterych se bude pocitat prumer.
		foreach ($lastBulletins as $bulletin)
		{
			$readers = self::getReadersForBulletin($bulletin->id, true);
			$retreaders = self::getRetReadersForBulletin($bulletin->id);

			// Celkovy return rates, nepocita se pro ten 4. bulletin, ale az od
			// 3.tiho nejnovejsiho
			if ($previous !== null)
			{
				$lastReturnRate = $previousReaders ? round($retreaders/ $previousReaders, 3) : 0;
				$returnRates += $lastReturnRate;
				$numBulletins++;
			}
			
			// ulozime ID bulletin pro dalsi iteraci
			$previous = $bulletin->id;
			// ulozime pocet readers pro dalsi iteraci
			$previousReaders = $readers;
		}

		if ($lastReturnRate !== null)
		{
			return Array(
				'return_rate' => $lastReturnRate,
				'avg_return_rate' => round(($returnRates / $numBulletins), $trunc));
		}

		return Array();
	}

	/**
	 *	Vrátí celkový průměr přečtených článků na čtenáře.
	 *
	 *	@param Na kolik míst se bude osekávat výsledek.
	 */
	public static function getArticlesAvg($trunc = self::TRUNC)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			$select = "SELECT trunc(avg(t.pocet_cl), $trunc) AS avg_articles
						FROM users u,
							(SELECT s.user_id, count(distinct(pv.page_id)) AS pocet_cl 
							 FROM sessions s, page_views pv 
							 WHERE s.id = pv.session_id AND page_id IS NOT NULL 
							 GROUP BY s.user_id
							) t
						WHERE u.id = t.user_id
    						AND u.test IN ".self::$test."
                            AND u.target IN ".self::$target."
                            AND u.klient IN ".self::$klient;

			$result = $db->fetchOne($select);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			throw new StatisticsoldException(
				'Nepodařilo se získat průměr přečtených článků.'.$e);
		}
	}

	/**
	 *	Průměr přečtených článků za posedních n vydání.
	 *
	 *	@param Za kolik posledních vydání se bude počítat průměr.
	 *	@param Na kolik míst se bude osekávat výsledek.
	 *	@param Pokud TRUE, bude vracet statistiky pouze pro bulletiny, které už jsou platné.
	 * 11.08 R.V. zmena, pocet ctenaru je vyssi, zahrnuti i ti, co nectou clanky
	 */
	public static function getLastArticlesAvg($num = 3, $trunc = self::TRUNC, $onlyValid = FALSE)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		try
		{
			if (!$onlyValid)
			{
				$select = "SELECT id FROM bulletins
										ORDER BY valid_from DESC
										LIMIT $num";
			}
			else
			{
				$select = "SELECT id FROM bulletins
										WHERE valid_from < current_timestamp
										ORDER BY valid_from DESC
										LIMIT $num";
			}

			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$i = 0;
			$avg_articles = 0;
			foreach ($result as $bulletin)
			{
				$i += 1;
				// prumerny pocet prectenych clanku v kategorii		-	(za bulletin?)
				$articles = self::getReadArticlesForBulletin($bulletin->id);
				$readers = self::getReadersForBulletin($bulletin->id, true);
				if (!empty($articles))
				{
					$avg_articles += $readers ? round($articles/$readers, $trunc) : 0;
				}
			}

			$db->setFetchMode($fetchMode);

			return $i ? round($avg_articles/$i, $trunc) : 0;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat průměr přečtených článků za poslední vydání.'.$e);
		}
	}

	/**
	 * DEPRECATED
	 * Funkce zrejme jiz neni pouzivana, pred pouzitim je nutne ji opravit (omezeni test, target,
	 * klient v users) a zkontrolovat. A samozrejme odstranit z nazvu xx na zacatku.
	 * 
	 *	Vrátí průměrný počet přečtených článků ve skupině pro konkrétní
	 *	bulletin.
	 *
	 *	@param Identifikátor bulletinu.
	 *	@param Na kolik míst se bude osekávat výsledek.
	 */
	public static function xxgetArticlesCategoriesForBulletin($bulletinId, $trunc = self::TRUNC)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$select = "SELECT trunc(avg(t.num_art), $trunc) AS avg_articles
				   FROM categories c, 
					   (SELECT pc.category_id, count(distinct(pc.page_id)) AS num_art 
					   FROM page_views pv 
					   INNER JOIN pages_categories pc ON pv.page_id = pc.page_id 
					   INNER JOIN bulletins_pages bp ON pc.page_id = bp.page_id 
					   INNER JOIN sessions s ON pv.session_id = s.id 
					   INNER JOIN users u ON s.user_id = u.id 
					   WHERE bp.bulletin_id = $bulletinId 
					   AND u.test IN ".self::$test."
					   AND u.target = TRUE 
					   GROUP BY pc.category_id
				   ) t 
				   WHERE c.id = t.category_id";
			
	 	try
		{
		    $fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->avg_articles;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				"Nepodařilo se získat průměr přečtených článků ve skupine pro
				bulletin s ID: $bulletinId. $e");
		}
	}

	/**
	 * DEPRECATED
	 * Funkce zrejme jiz neni pouzivana, pred pouzitim je nutne ji opravit (omezeni test, target,
     * klient v users) a zkontrolovat. A samozrejme odstranit z nazvu xx na zacatku.
	 * 
	 *	Vrátí počet všech přečtených článků v bulletinu.
	 *
	 *	@param Identifikátor bulletinu.
	 */
	public static function xxgetAllReadArticlesForBulletin($bulletinId)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$select = "SELECT sum(art) AS all
			       FROM (SELECT pc.category_id, count(distinct(pc.page_id)) AS art 
					   FROM page_views pv 
					   INNER JOIN pages_categories pc ON pv.page_id = pc.page_id 
					   INNER JOIN bulletins_pages bp ON pc.page_id = bp.page_id
					   WHERE bp.bulletin_id = $bulletinId 
					   GROUP BY pc.category_id
				   ) AS foo"; 

	 	try
		{
		    $fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->all;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				"Nepodařilo se získat počet přečtených článků pro bulletin s ID: $bulletinId. $e");
		}
	}


	/**
	 *	Vrátí počet všech přečtených článků v bulletinu.
	 *	10.08	R.V.
	 *	@param Identifikátor bulletinu.
	 */
	public static function getAllArticlesForBulletin($bulletinId)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$select = "SELECT count(distinct(page_id)) 
		           FROM bulletins_pages
		           WHERE bulletin_id = $bulletinId";
		
//		$select = "select count(*) as all from (select distinct page_id, user_id from old_page_views_v where action='page' and bulletin_id=$bulletinId) as foo"; 

	 	try
		{
			$result = $db->fetchOne($select);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			throw new StatisticsoldException(
				"Nepodařilo se získat počet přečtených článků pro bulletin s ID: $bulletinId. $e");
		}
	}



	/**
	 *	Vrátí počet všech přečtených článků v bulletinu.
	 *	10.08	R.V.
	 *	@param Identifikátor bulletinu.
	 */
	public static function getReadArticlesForBulletin($bulletinId)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		/*
		$select = "SELECT count(distinct(pvv.page_id)) AS all from old_page_views_v pvv 
		          where bulletin_id = $bulletinId 
		              and page_id > 0 
		              and user_id in(select id from users where not test)";
		*/
		$select = "SELECT count(*) as all 
		           FROM (SELECT distinct page_id, user_id 
		                      FROM old_page_views_v pvv 
		                      WHERE page_id IS NOT NULL 
		                          AND bulletin_id = $bulletinId 
		                          AND user_id IN
		                          (SELECT id FROM users u
		                              WHERE 
		                                    u.test IN ".self::$test."
                                            AND u.target IN ".self::$target."
                                            AND u.klient IN ".self::$klient."
		                  )) as foo";

		try
		{
			$result = $db->fetchOne($select);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			throw new StatisticsoldException(
				"Nepodařilo se získat počet přečtených článků pro bulletin s ID: $bulletinId. $e");
		}
	}


	/** 
	 *	Vrátí statistiky pro jednotlivá vydání.
	 *
	 *	@param Zaokrouhlování.
	 *	@param Pokud TRUE, bude vracet statistiky pouze pro bulletiny, které už jsou platné.
	 */
	public static function getBulletinsStats($trunc = self::TRUNC, $onlyValid = FALSE)
	{
		try
		{
			// ziskame statistiky pro vsechny bulletiny, pro ty, pro ktere ty
			// funkce mame.

		    
			// najdou se vsechny bulletiny a potom se pro ne budou ziskavat
			// jednotlive statistiky
			$bulletins = self::getBulletins('ASC', null, $onlyValid);

			// Pripravime pole s pocty subscriberu pro kazde cislo
			$bulletinsSubscribers = array();
			foreach ($bulletins as $bulletin){
			    $bulletinsSubscribers[$bulletin->id] = self::getSubscribersForBulletin($bulletin->id, true);
			}
			
			
			$retVal = Array();
			$previous = null;
			foreach ($bulletins as $bulletin)
			{
				$retVal[$bulletin->id] = Array();
				$retVal[$bulletin->id]['name'] = $bulletin->name;
				if (array_key_exists($bulletin->id, $bulletinsSubscribers)){
					$retVal[$bulletin->id]['subscribers'] =
						$bulletinsSubscribers[$bulletin->id];
				}
				else{ 
					$retVal[$bulletin->id]['subscribers'] = 0;
				}
				
				// pocet ctenaru, bude se jeste hodit
				$readers = self::getReadersForBulletin($bulletin->id, true);
				$retreaders = self::getRetReadersForBulletin($bulletin->id);	//R.V.
				
				$retVal[$bulletin->id]['readers'] = $readers;
				$retVal[$bulletin->id]['visits'] = self::getVisitsForBulletin($bulletin->id);
				
				if($responseRate = self::getResponseRateForBulletin($bulletin->id, $trunc)){
					$retVal[$bulletin->id]['response_rate'] = $responseRate;
				}

				// return rate je pomer reader(bulletin) / readers(bulletin - 1)
				// takze nema smysl ho pocitat pro prvni bulletin
				if ($previous !== null){
					$retVal[$bulletin->id]['return_rate'] = 
						$previousReaders ? round(100*$retreaders / $previousReaders, $trunc) : 0;
				}

				// prumerny pocet prectenych clanku za bulletin?
				$articles = self::getReadArticlesForBulletin($bulletin->id);
				if(!empty($articles)){
					$retVal[$bulletin->id]['articles'] = $readers ? round($articles/$readers, $trunc) : 0;
					
					// ziskame celkovy pocet prectenych clanku v bulletinu
					$totalArticles = self::getAllArticlesForBulletin($bulletin->id);
					$retVal[$bulletin->id]['articles_percent'] =
						$readers && $totalArticles ? (round(round($articles/$readers, $trunc) * 100 / $totalArticles, $trunc)) : 0;
				}
				else{
					$retVal[$bulletin->id]['articles'] = 0;
					$retVal[$bulletin->id]['articles_percent'] = 0;
				}
				
				// ulozime ID bulletin pro dalsi iteraci
				$previous = $bulletin->id;
				// ulozi se pocet ctenaru, aby se to v dalsi iteraci nemuselo
				// znovu ziskavat jako self::getReadersForBulletin($previous)
				$previousReaders = $readers;
			}

			krsort($retVal);
			return $retVal;
		}
		catch (StatisticsoldException $e)
		{
			throw new StatisticsoldException(
				"Nepodařilo se získat statistiky pro jednotlivé bulletiny.".$e);
		}
	}



	/** 
	 *	Vrátí pocty navstevniku za poslednich asi 31 dnu.
	 *
	 *	5.12.08 RV
	 */
	public static function getDayReaders()
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$select = "SELECT * FROM old_dates_readers_v";

		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$db->setFetchMode($fetchMode);

			$retVal = Array();
			$lastReaders = 0;
			$i = 0;
			foreach ($result as $res)
			{
				if ($i <> 0) {
//					$retVal[$res->id] = Array();
//					$retVal[$res->id]['dates'] = $res->dates;
//					$retVal[$res->id]['readers'] = $res->readers;
					$retVal[$res->dates] = Array();
					$retVal[$res->dates]['dates'] = $res->dates;
					$retVal[$res->dates]['readers'] = $res->readers;
					$retVal[$res->dates]['diff'] = $res->readers - $lastReaders;
				}
				$lastReaders = $res->readers;
			$i++;
			}
			
			return $retVal;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				'Nepodařilo se získat DayReaders.'.$e);
		}
	}

	/**
	 *	Vrátí počet uživatelů, kteří klikli na email.
	 *
	 *	@param Identifikátor emailu.
	 */
	public static function getEmailClicks($emailId)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		/*$select = "SELECT count(distinct(user_id)) as users 
					FROM page_views pv 
					INNER JOIN sessions s ON pv.session_id = s.id 
					INNER JOIN users u ON s.user_id = u.id 
					WHERE link_id IS NOT NULL 
					AND link_id IN (
						SELECT distinct(l.id)
						FROM links l 
						INNER JOIN users_links_tokens ult ON ult.link_id = l.id
						INNER JOIN emails e ON ult.email_id = e.id 
						WHERE e.id = $emailId
					)
					AND u.id IN (
						SELECT user_id
						FROM users_emails 
						WHERE email_id = $emailId
						AND sent IS NOT NULL
					)
					AND u.target = TRUE 
					AND u.test IN ".self::$test;
					*/
        
		$select = "SELECT count(distinct(ult.user_id)) AS users
                   FROM page_views pv
                        JOIN users_links_tokens ult ON pv.users_links_tokens_id = ult.id
                        JOIN users u ON ult.user_id = u.id
                   WHERE 
                        ult.email_id = $emailId 
                        AND u.test IN ".self::$test."
                        AND u.target IN ".self::$target."
                        AND u.klient IN ".self::$klient;
		
		try
		{
			$result = $db->fetchOne($select);
			return $result;
		}
		catch (Zend_Db_Exception $e)
		{
			throw new StatisticsoldException(
				"Nepodařilo se získat počet kliků na email s ID: $emailId.".$e);
		}
	}

	/**
	 *	Získá statistiky emailů pro konkrétní bulletin.
	 *	U statistiky 'odesláno', pokud se jeden email odešle uživateli vícekrát
	 *	(což by v reálu němelo nastat), bude se to počítat jako jedno odeslání.
	 *
	 *	@param Identifikátor bulletinu.
	 *	@param Zaokrouhlování.
	 */
	public static function getEmailsStats($bulletinId, $trunc = self::TRUNC)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		// ziskaji se vsechny emaily k bulletinu a pocet jejich odeslani
		// TODO pocitat, kdyz se email odesle 2x stejnemu uzivateli, jako 2x
		// odeslan? Ted je to udelany tak, ze ne.
		$select = "SELECT name, email_id, count(*) AS sent 
					FROM (
						SELECT u.id, e.name, ue.email_id, count(ue.id) AS sent 
						FROM users_emails ue 
						INNER JOIN users u ON ue.user_id = u.id 
						INNER JOIN emails e ON ue.email_id = e.id
						WHERE email_id IN ( 
							SELECT e.id 
							FROM emails e 
							INNER JOIN invitation_waves iw ON e.invitation_id = iw.id 
							INNER JOIN bulletins b ON b.id = iw.bulletin_id 
							WHERE bulletin_id = $bulletinId 
						) 
						AND ue.sent IS NOT NULL 
                        AND u.test IN ".self::$test."
                        AND u.target IN ".self::$target."
                        AND u.klient IN ".self::$klient." 
						GROUP BY ue.email_id, u.id, e.name
					) AS foo 
					GROUP BY email_id, name
					ORDER BY name ASC";

		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			
			$retVal = Array();
			foreach ($result as $email)
			{
				// nazev emailu
				$retVal[$email->email_id]['name'] = $email->name;
				// pocet uzivatelu, kterym byl odeslan
				$retVal[$email->email_id]['sents'] = $email->sent;
				// ted pocet uzivatelu, kteri klikli na email
				$retVal[$email->email_id]['clicks'] =
					self::getEmailClicks($email->email_id);
				// procento prokliku
				$retVal[$email->email_id]['percent'] =
					$email->sent ? round(($retVal[$email->email_id]['clicks'] * 100 / $email->sent), $trunc) : false;
			}

			$db->setFetchMode($fetchMode);
			return $retVal;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				"Nepodařilo se získat statistiky pro emaily k bulletinu:
				$bulletinId.".$e);
		}
	}

	/**
	 *	Vrátí celkový počet přečtených článků za celý projekt.
	 */
	public static function getArticlesRead()
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

/**		$select = 'SELECT count(distinct(pv.page_id)) AS num
					FROM page_views pv
					INNER JOIN pages p ON pv.page_id = p.id
					INNER JOIN sessions s ON s.id = pv.session_id
					INNER JOIN users u ON u.id = s.user_id
					WHERE u.target = TRUE
					AND u.test IN '.self::$test;
*/
		$select = "SELECT count(pv.id) as num
						FROM old_page_views_v pv 
						INNER JOIN pages_categories pc ON pv.page_id = pc.page_id
						INNER JOIN users u ON pv.user_id = u.id
						WHERE u.test IN ".self::$test."
                        AND u.target IN ".self::$target."
                        AND u.klient IN ".self::$klient;


		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->num;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				"Nepodařilo se získat počet všech přečtených článků.".$e);
		}
	}

	/**
	 *	Statistiky pro jednotlivé kategorie. Vždy vrátí kategorii a procentuální
	 *	podíl prečtených článků z kategorie vůči všem článkům za celý projekt.
	 *	@param Zaokrouhlování.
	 */
	public static function getCategoriesStats($trunc = self::TRUNC)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		// TODO vyfiltrovat kategorie jako pravni poradna???
		// da se filtrovat podle class_name v tabulce content,
		// vzit jenom kategorie, kde je class_name 
		// Ibulletin_Content_Article
/**		$select = 'SELECT id, name AS category, count(*) AS num
					FROM (
						SELECT c.id, c.name, pv.page_id 
						FROM page_views pv 
						INNER JOIN pages p ON pv.page_id = p.id
						INNER JOIN pages_categories pc ON p.id = pc.page_id
						INNER JOIN categories c ON pc.category_id = c.id
						INNER JOIN sessions s ON pv.session_id = s.id
						INNER JOIN users u ON s.user_id = u.id
						WHERE u.target = TRUE
							AND u.test IN '.self::$test.'
						GROUP BY pv.page_id, c.name, c.id
					) AS foo
					GROUP BY id, name';
*/
		$select = "SELECT id, name AS category, count(*) AS num
					FROM (
						SELECT c.id, c.name, pv.page_id 
						FROM old_page_views_v pv 
						INNER JOIN pages_categories pc ON pv.page_id = pc.page_id
						INNER JOIN categories c ON pc.category_id = c.id
						INNER JOIN users u ON pv.user_id = u.id
						WHERE
						  u.test IN ".self::$test."
                          AND u.target IN ".self::$target."
                          AND u.klient IN ".self::$klient."
					) AS foo
					GROUP BY id, name";




		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchAll($select);
			$categoriesPercents = Array();
			foreach ($result as $category)
				$categoriesPercents[$category->id] = $category;	

			// ziska se seznam vsech kategorii v projektu
			$select = 'SELECT * FROM categories';
			$allCategories = $db->fetchAll($select);

			// celkovy pocet prectenych clanku
			$total = self::getArticlesRead();

			$retVal = Array();
			foreach ($allCategories as $category)
			{
				if (array_key_exists($category->id, $categoriesPercents))
				{
					$retVal[$category->id]['name'] =
						$categoriesPercents[$category->id]->category;
					$retVal[$category->id]['percent'] = round(
						(($categoriesPercents[$category->id]->num * 100) / $total), $trunc);
				}
				else
				{
					$retVal[$category->id]['name'] = $category->name;
					$retVal[$category->id]['percent'] = 0;
				}
			}

			$db->setFetchMode($fetchMode);
			return $retVal;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				"Nepodařilo se získat statistiky pro kategorie.".$e);
		}
	}

	/**
	 *	Vrátí počet návštěv uživatele na bulletinu.
	 *
	 *	@param Identifikátor uživatele.
	 *	@param Identifikátor bulletinu.
	 */
	public static function getUsersVisitsForBulletin($user, $bulletin)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$select = "SELECT count(s.id) AS visits
					FROM page_views pv 
					INNER JOIN sessions s ON pv.session_id = s.id 
					WHERE bulletin_id = $bulletin 
					AND s.user_id = $user";

		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_OBJ);
			$result = $db->fetchRow($select);
			$db->setFetchMode($fetchMode);
			return $result->visits;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				"Nepodařilo se získat počet návštěv pro uživatele.".$e);
		}
	}

	/**
	 * !!!!!!!!!!
	 * TODO
	 * Je treba zrevidovat a zkontrolovat. Vsude musi byt spravne filtrovany zaznamy z users
	 * tedy klient, test, target.
	 * Ja to nekontroloval - Petr Skoda 2009.2.19
	 * !!!!!!!!!!!!!!!!!
	 * 
	 *	Vrací statistiky pro jednotlivé uživatele.
	 *
	 *	@param Zaokrouhlování.
	 */
	public static function getUsersStats($trunc = self::TRUNC)
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		// varati uzivatele a ID prvniho bulletinu, ktery mu byl zaslan
		$select = 'select u.id, u.name, u.email, (select b.id from users_emails
		ue inner join emails e on ue.email_id = e.id inner join invitation_waves
		iw on e.invitation_id = iw.id inner join bulletins b on iw.bulletin_id =
		b.id where ue.user_id = u.id order by b.valid_from asc limit 1) as
		first_sent from users u';  

		/*
		select u.id, u.name, u.email, u.added, u.deleted, (select b.name from
		users_emails ue inner join emails e on ue.email_id = e.id inner join
		invitation_waves iw on e.invitation_id = iw.id inner join bulletins b on
		iw.bulletin_id = b.id where ue.user_id = u.id order by b.valid_from asc
		limit 1) as first_sent, (select b.name from users_emails ue inner join
		emails e on ue.email_id = e.id inner join invitation_waves iw on
		e.invitation_id = iw.id inner join bulletins b on iw.bulletin_id = b.id
		where ue.user_id = u.id order by b.valid_from desc limit 1) as last_sent
		from users 
		*/

		/*
		// zjisti pocet prectenych bulletin podle page_id sloupce v page_views 
		select count(distinct(bp.bulletin_id)) from page_views pv inner join
		sessions s on pv.session_id = s.id inner join pages p on pv.page_id =
		p.id inner join bulletins_pages bp on p.id = bp.page_id where s.user_id
		= 14;
		*/

		/*
		//	ziska informace o uzivateli a prvni bulletin, ktery cetl, zatim
		//	jenom, podle sloupce page_id v page_views
		SELECT u.*, 
			(SELECT name 
			FROM bulletins 
			WHERE id IN (
				SELECT b.id 
				FROM page_views pv
				INNER JOIN sesssions s ON pv.session_id = s.id
				INNER JOIN links l ON pv.link_id = l.id
				INNER JOIN bulletins b ON l.bulletin_id = b.id
				WHERE s.user_id = u.id
				ORDER BY valid_from ASC
				LIMIT 1
			)
			OR id IN (
				SELECT b.id
				FROM


			)
		FROM USERS u;
		*/

		// jako predchozi, ale i s poslednim vydanim, ktere cetl
		$select = "SELECT u.id, u.name, u.surname, u.email, u.group, u.rep,
						  to_char(u.added, 'YYYY-MM-DD HH24:MI:SS') AS added,
					      to_char(u.deleted, 'YYYY-MM-DD HH24:MI:SS'), 
						  (SELECT b.name 
						   FROM users_emails ue 
						   INNER JOIN emails e ON ue.email_id = e.id 
						   INNER JOIN invitation_waves iw ON e.invitation_id = iw.id 
						   INNER JOIN bulletins b ON iw.bulletin_id = b.id 
						   WHERE ue.user_id = u.id 
						   ORDER BY b.valid_from ASC
						   LIMIT 1) AS first_sent,
						  (SELECT b.name 
						   FROM users_emails ue 
						   INNER JOIN emails e ON ue.email_id = e.id 
						   INNER JOIN invitation_waves iw ON e.invitation_id = iw.id 
						   INNER JOIN bulletins b ON iw.bulletin_id = b.id
						   WHERE ue.user_id = u.id 
						   ORDER BY b.valid_from DESC 
						   LIMIT 1) AS last_sent
					FROM users u
					WHERE u.test IN ".self::$test."
					AND u.target = TRUE
					ORDER BY email ASC";

		try
		{
			$fetchMode = $db->getFetchMode();
			$db->setFetchMode(Zend_Db::FETCH_ASSOC);
			$result = $db->fetchAll($select);

			$bulletins = self::getBulletins('DESC');
			
			$retVal = Array();
			foreach ($result as $user)
			{
				$retVal[$user['id']] = $user;
				$retVal[$user['id']]['bulletins'] = Array();
				foreach ($bulletins as $bulletin)
				{
					$retVal[$user['id']]['bulletins'][$bulletin->id] = Array();
					$retVal[$user['id']]['bulletins'][$bulletin->id]['name'] = $bulletin->name;
					$retVal[$user['id']]['bulletins'][$bulletin->id]['visits'] = 
						self::getUsersVisitsForBulletin($user['id'], $bulletin->id);
				}
			}

			$db->setFetchMode($fetchMode);
			return $retVal;
		}
		catch (Zend_Db_Exception $e)
		{
			$db->setFetchMode($fetchMode);
			throw new StatisticsoldException(
				"Nepodařilo se získat statistiky pro uživatele.".$e);
		}
	}
}
