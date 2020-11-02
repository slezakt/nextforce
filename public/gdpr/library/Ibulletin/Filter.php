<?php

/**
 *	Třída filtr pro filtrování uživatelů v mailer. Používá se při zobrazování 
 *	uživatelů v mailingu. 
 *
 *	@author Martin Krčmář.
 */
class Ibulletin_Filter
{
	/**
	 *	Filtr pro filtrování jména.
	 */
	protected $nameFilter = '';

	/**
	 *	Filtr pro filtrování příjmení.
	 */
	protected $surnameFilter = '';

	/**
	 *	Filtr pro filtrování emailů.
	 */
	protected $emailFilter = '';

	/**
	 *	Jestli se budou zobrazovat uživatelé s emailem nebo bez.
	 */
	protected $withEmail = true;
	
	/**
     *  Maji byt defaultne zobrazeni i uzivatele bez registrace a bez nastaveneho send_emails.
     */
    protected $ignoreRegAndSend = false;
	
	/**
     * Nezohlednovat jestli je email vyplneny nebo ne - ignorovat $withEmail.
     */
    protected $ignoreEmail = false;

	/**
	 *	Jestli se budou zobrazovat pouze uzivatele, kterym jeste nebyl poslan
	 *	zadny mail.
	 */
	protected $newUsers = false;

	/**
	 *	Filtrování uživatelů podle času přidání do DB.
	 */
	protected $addedFilter = '';

	/**
	 *	Pro filtrování uživatelů podle skupiny.
	 */
	protected $groupFilter = array();

	/**
	 *	Budou se filtrovat uživatelé, kteří nedostali konkrétní email.
	 */
	protected $undeliveredEmailUsers = '';

	 /**
     *  Budou se filtrovat uživatelé, kteří dostali konkrétní email.
     */
    protected $deliveredEmailUsers = '';
	
	/**
	 *	Výsledná část SQL dotazu.
	 */
	protected $filterSQL = '';

	/**
	 *	Budou se filtrovat pouze uživatelé, kteří na web ještě nepřišli.
	 */
	protected $didntCome = false;

	/**
	 *	Uživatelé, kteří nepřišli na žádný link z emailu.
	 */
	protected $didntComeToEmail = '';

	/**
	 *	Uživatelé, kteří nepřisli do bulletinu.
	 */
	protected $didntComeToBulletin = '';
	
	/**
	 *  Smazaní uživatelé 
	 */
	protected $deletedFilter = false ;

	/**
	 * Nečetli článek X
	 */
	protected $didntReadArticle = '';
	
	/**
	 * Zahrnout uzivatele typu client
	 */
	protected $includeClients = false;	
	
	/**
	 *	Přidá další podmínku typu AND do SQL dotazu.
	 *
	 *	@param Podmínka pro přidání.
	 */
	protected function addAND($rule)
	{
		if (empty($this->filterSQL))
			$this->filterSQL = $rule;
		else
			$this->filterSQL .= " and $rule";
	}
	
	/**
	 *	Přidá další podmínku typu OR do SQL dotazu.
	 *
	 *	@param Podmínka pro přidání.
	 */
	protected function addOR($rule)
	{
		if (empty($this->filterSQL))
			$this->filterSQL = $rule;
		else
			$this->filterSQL = '(' . $this->filterSQL . ") or ($rule)";
	}	

	public function setNameFilter($name)
	{
		$this->nameFilter = $name;
	}	

	public function getNameFilter()
	{
		return $this->nameFilter;
	}

	public function setSurnameFilter($name)
	{
		$this->surnameFilter = $name;
	}

	public function getSurnameFilter()
	{
		return $this->surnameFilter;
	}

	public function setEmailFilter($filter)
	{
		$this->emailFilter = $filter;
	}

	public function getEmailFilter()
	{
		return $this->emailFilter;
	}

	public function setWithEmail($flag)
	{
		$this->withEmail = $flag;
	}

	public function getWithEmail()
	{
		return $this->withEmail;
	}
	
    public function getIgnoreRegAndSend()
    {
        return $this->ignoreRegAndSend;
    }
    
    public function setIgnoreRegAndSend($val)
    {
        $this->ignoreRegAndSend = (bool)$val;
    }
	
    public function setIgnoreEmail($flag)
    {
        $this->ignoreEmail = $flag;
    }

    public function getIgnoreEmail()
    {
        return $this->ignoreEmail;
    }

	public function setNewUsers($flag)
	{
		$this->newUsers = $flag;
	}

	public function getNewUsers()
	{
		return $this->newUsers;
	}

	public function setAddedFilter($added)
	{
		$this->addedFilter = $added;
	}

	public function getAddedFilter()
	{
		return $this->addedFilter;
	}

		public function setDeletedFilter($added)
	{
		$this->deletedFilter = $added;
	}

	public function getADeletedFilter()
	{
		return $this->deletedFilter;
	}
	
	public function getGroupFilter()
	{
        return (empty($this->groupFilter) ? '' : implode(', ',$this->groupFilter));
	}

	public function setGroupFilter($group)
	{
		if ($group !== '') {
			$this->groupFilter = explode(',',$group);             
            array_walk($this->groupFilter, create_function('&$val', '$val = trim($val);'));
		}
	}

	public function setUndeliveredEmailUsers($email)
	{
		$this->undeliveredEmailUsers = $email;
	}

   public function setDeliveredEmailUsers($email)
    {
        $this->deliveredEmailUsers = $email;
    }
	

	public function getUndeliveredEmailUsers()
	{
		return $this->undeliveredEmailUsers;
	}
	
   public function getDeliveredEmailUsers()
    {
        return $this->deliveredEmailUsers;
    }

	public function setWhoDidntCome($flag)
	{
		$this->didntCome = $flag;
	}

	public function getWhoDidntCome()
	{
		return $this->didntCome;
	}

	public function setDidntComeToEmail($email)
	{
		$this->didntComeToEmail = $email;
	}

	public function getDidntComeToEmail()
	{
		return $this->didntComeToEmail;
	}

	public function setDidntComeToBulletin($bulletin)
	{
		$this->didntComeToBulletin = $bulletin;
	}

	public function getDidntComeToBulletin()
	{
		return $this->didntComeToBulletin;
	}
	
    public function setDidntReadArticle($article)
	{
		$this->didntReadArticle = $article;
	}

	public function getDidntReadArticle()
	{
		return $this->didntReadArticle;
	}
	
	public function setIncludeClients($var)
	{
		$this->includeClients = (bool)$var;
	}
	
	public function getIncludeClients()
	{
		return $this->includeClients;
	}	

	/**
	 *	Metoda vrací řetězec, který se poté použije v SQL dotazu a podle toho se 
	 *	vyfiltrují někteří uživatelé.
	 */
	public function getFilterSQL()
	{
		$this->filterSQL = '';

		if (!empty($this->nameFilter))
			$this->addAND("name ILIKE '%$this->nameFilter%'");

		if (!empty($this->surnameFilter))
			$this->addAND("surname ILIKE '%$this->surnameFilter%'");

		if (!empty($this->addedFilter))
			$this->addAND("added $this->addedFilter");

		if (!empty($this->groupFilter) && is_array($this->groupFilter)) {
			$rule = array();
            foreach ($this->groupFilter as $group) {
                if ($group=='NULL') {                                			    
                    $rule[] = "\"group\" IS NULL";
                    $rule[] = "trim(both ' ' from \"group\") = ''";
                } else {
                    $group = str_replace('_','\_',$group);                    
                    $rule[] = "\"group\" ILIKE '$group'";                    
                }
            }            
            $this->addAND('('.implode(' OR ', $rule).')');
        }

		if(!$this->ignoreEmail){
    		if ($this->withEmail)
    			if (!empty($this->emailFilter))
    				$this->addAND("email ILIKE '%$this->emailFilter%'");
    			else
    				$this->addAND("email <> ''");
    		else
    			$this->addAND("email IS null OR email = ''");
		}
		else{
		    if (!empty($this->emailFilter))
                    $this->addAND("email ILIKE '%$this->emailFilter%'");
		}
		

		if ($this->newUsers)
			$this->addAND('id NOT IN (SELECT user_id FROM users_emails GROUP BY user_id)');
			
		if ($this->deletedFilter)
			$this->addAND('deleted IS NOT NULL');

		if (!empty($this->undeliveredEmailUsers))
			$this->addAND("id NOT IN (
				SELECT user_id FROM users_emails 
				WHERE email_id = $this->undeliveredEmailUsers 
				GROUP BY user_id)"
			);
			
        if (!empty($this->deliveredEmailUsers))
            $this->addAND("id IN (
                SELECT user_id FROM users_emails 
                WHERE email_id = $this->deliveredEmailUsers 
                GROUP BY user_id)"
            );
            
		if ($this->didntCome)
			$this->addAND("id NOT IN (
				SELECT u.id FROM sessions s, users u
			 	WHERE s.user_id = u.id
			 	GROUP BY u.id)"
			);

		if (!empty($this->didntComeToEmail)){		
		    $this->addAND("id in (
                SELECT user_id FROM users_emails
                WHERE email_id = $this->didntComeToEmail AND sent IS NOT null
                )
                AND id NOT IN (
                    SELECT ult.user_id
                        FROM page_views pv
                        JOIN users_links_tokens ult ON pv.users_links_tokens_id = ult.id
                        WHERE ult.email_id = $this->didntComeToEmail
                )"
            );
		}

		if (!empty($this->didntComeToBulletin)){
		    $this->addAND("id NOT IN (
                    SELECT s.user_id
                    FROM page_views pv INNER JOIN sessions s ON pv.session_id = s.id
                    WHERE (s.user_id IS NOT null)
                    AND bulletin_id = $this->didntComeToBulletin
                )"
            );
		}
	   
		if (!empty($this->didntReadArticle)){
			$this->addAND("id NOT IN (
					SELECT s.user_id
                    FROM page_views pv INNER JOIN sessions s ON pv.session_id = s.id
                    WHERE (s.user_id IS NOT null)
                    AND page_id = $this->didntReadArticle
				)"
			);
		}
	   
		if ($this->includeClients) {
			$this->addOR("client = TRUE");	   	
		}
		
		if (empty($this->filterSQL)) {
			return '1 = 1';        
		}
		
		return $this->filterSQL;
	}
	
}
?>
