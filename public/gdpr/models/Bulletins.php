<?php

/**
 * iBulletin - Bulletins.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

class Bulletins_No_Valid_Bulletin_Found_Exception extends Exception {}

/**
 * Trida obsahujici metody pouzivane pro praci s bulletiny - tabulka bulletins
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Bulletins
{
    /**
     * Najde a vrati ID bulletinu podle jeho URL jmena
     * pokud je url name prazdne, vrati ID aktualniho bulletinu.
     *
     * @param string URL jmeno bulletinu z tabulky bulletins
     * @param bool Nastavit nalezeny bulletin jako aktualne prohlizeny?
     * @return int ID bulletinu nebo false pro neplatne url
     */
    public static function findBulletinId($url_name, $setAsCurrent = false)
    {
        $db = Zend_Registry::get('db');
        Zend_Loader::loadClass('Zend_Auth');

        // Pokud je $url_name prazdne, vratime ID aktualniho bulletinu
        if(is_numeric($url_name) || !empty($url_name)){
            // Mirne normalizujeme
            $url_name = trim(strtolower($url_name));

            $url_name_quot = $db->quote($url_name);

            // Vytvorime podminku podle toho, jestli je prave povoleno zobrazovani neaktivniho
            // obsahu, nebo ne
            Zend_Loader::loadClass('Zend_Auth');
            $session = Zend_Registry::get('session');
            $auth = Zend_Auth::getInstance();
            if(!empty($session->allowed_inactive_content) || $auth->hasIdentity()){
                $show_inactive_where = '1 = 1';
            }
            else{
                $show_inactive_where = 'valid_from < current_timestamp';
            }

            $q = "SELECT * FROM bulletins WHERE lower(url_name) = lower($url_name_quot)
                    AND $show_inactive_where AND deleted IS NULL";
            $rows = $db->fetchAssoc($q);

            // Pokud se nenasel zaznam, vratime false
            if(empty($rows)){
                return false;
            }

            reset($rows);
            $row = current($rows);
            $id = $row['id'];

            // Pokud je to pozadovano, nastavime tento bulletin jako
            // aktulane prohlizeny do zend registry
            if($setAsCurrent){
                Zend_Registry::set('current_bulletin', $id);
            }

            return $id;
        }
        else{
            // Prazdne url_name - vracime id aktualniho bulletinu
            return self::getActualBulletinId(true);
        }
    }


    /**
     * Najde a vrati url_name bulletinu podle ID.
     *
     * @param int ID bulletinu
     * @return string url_name buletinu podle zadaneho ID
     */
    public static function findBulletinUrlName($id)
    {
        $db = Zend_Registry::get('db');

        $q = sprintf('SELECT url_name FROM bulletins WHERE id=%d', $id);
        $url_name = $db->fetchOne($q);

        return $url_name;
    }
    
    
    /**
     * Zjisti jestli existuje url_name bulletinu.
     *
     * @param string Url name
     * @param int bulletin_id
     * @return bool 
     */
    public static function ExistsPageUrlName($url_name, $bulletin_id)
    {
        $db = Zend_Registry::get('db');
        
        $select = $db->select()->from('bulletins_pages')
                ->where('url_name = ?', $url_name)
                ->where('bulletin_id = ?', $bulletin_id);
        $rows = $db->fetchAll($select);
        
        if ($rows) {
            return true;
        } else {
            return false;
        }
      
    }
    
    /**
     * Zjisti zda je stranka v bulletinu
     *
     * @param int page_id
     * @param int bulletin_id
     * @return bool 
     */
    public static function ExistsPageBulletins($page_id,$bulletin_id)
    {
        $db = Zend_Registry::get('db');
        
        $select = $db->select()->from('bulletins_pages')
                ->where('page_id = ?', $page_id)
                ->where('bulletin_id = ?',$bulletin_id);
        
        $rows = $db->fetchAll($select);
        
        if ($rows) {
            return true;
        } else {
            return false;
        }
      
    }


    /**
     * Najde a vrati ID aktualniho bulletinu.
     *
     * !! DEPRECATED - Wrapper k funkci getActualBulletinRow()
     *
     * @param bool Nastavit nalezeny bulletin jako aktualne prohlizeny?
     * @return int ID aktualniho bulletinu
     */
    public static function getActualBulletinId($setAsCurrent = false)
    {
        $row = self::getActualBulletinRow($setAsCurrent);

        return $row['id'];
    }

    /**
     * Vrati nazev bulletinu podle ID, pokud neni zadano ID
     * je vraceno jmeno aktualniho bulletinu.
     *
     *  @param int ID bulletinu.
     */
    public static function getBulletinName($id = null){
        if(empty($id)){
            $bulletin_row = self::getActualBulletinRow();
            return $bulletin_row['name'];
        }

        $db = Zend_Registry::get('db');
        $select = $db->select()
            ->from('bulletins')
            ->where('id = ?', (int)$id);

        try
        {
            $result = $db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            Phc_ErrorLog::error('Show content', 'Nepodarilo se ziskat nazev
            bulletinu s ID: '.$id.', '.$e);
        }

        return $result->name;
    }

    /**
     * Najde a vrati zaznam aktualniho bulletinu.
     * Bulletin s nejvyssim datem platnosti mensim, nez aktualni datum.
     *
     * @param bool Nastavit nalezeny bulletin jako aktualne prohlizeny?
     * @return array radek z tabulky bulletins aktualniho bulletinu
     */
    public static function getActualBulletinRow($setAsCurrent = false)
    {
        $db = Zend_Registry::get('db');
        $session = Zend_Registry::get('session');

        $sel = $db->select()
                ->from('bulletins')
                ->order('valid_from DESC')
                ->where('deleted IS null')
                ->where('NOT hidden')
                ->limit(1);

        // Pridame podminku podle toho, jestli je prave povoleno zobrazovani neaktivniho
        // obsahu, nebo ne
        if(empty($session->allowed_inactive_content) && !Zend_Auth::getInstance()->hasIdentity()){
            $sel->where('valid_from < current_timestamp');
        }
        /*$q = 'SELECT * FROM bulletins WHERE valid_from < current_timestamp
                ORDER BY valid_from DESC LIMIT 1';
        */

        $row = $db->fetchAll($sel);        
        $row = (array)$row[0];
        
        // Pokud je to pozadovano, nastavime tento bulletin jako
        // aktulane prohlizeny do zend registry
        if($setAsCurrent){
            Zend_Registry::set('current_bulletin', $row['id']);
        }

        return $row;
    }

    /**
     * Vrati id aktualniho bulletinu z registruu - bulletin, ve kterem se uzivatel prave nachazi.
     * @return int  Id current bulletinu - ten ve kterem uzivatel prave je.
     */
    public static function getCurrentBulletinId(){
        try{
            $bulletin = Zend_Registry::get('current_bulletin');
        }
        catch(Zend_Exception $e){
            $bulletin = self::getActualBulletinId(true);
        }
        return $bulletin;
    }


    /**
     * Vrati radek bulletinu podle ID.
     *
     * @param int/string $id   Id pozadovaneho bulletinu nebo url_name
     * @param bool Nastavit nalezeny bulletin jako aktualne prohlizeny?
     * @param bool Zahrne do vysledku i smazane bulletiny
     * @return array    Radek z DB pozadovaneho bulletinu
     */
    public static function get($id, $setAsCurrent = false, $deleted = false)
    {
        $db = Zend_Registry::get('db');
        $session = Zend_Registry::get('session');

        $sel = $db->select()
                ->from('bulletins')
                ->order('valid_from DESC')
                ->limit(1);
        
        if (!$deleted) {
            $sel->where('deleted IS NULL');
        }
        
        
        if(is_int($id)){
            $sel->where("id = $id");
        }
        else{
            $sel->where('url_name = '.$db->quote($id));
        }
        
         try {
            $row = $db->fetchRow($sel);
        } catch (Zend_Db_Statement_Exception $e) {
            throw new Zend_Exception(
            'Nepodařilo se informace o bulletinu. SQL:' . $e);
        }
        

        // Pokud je to pozadovano, nastavime tento bulletin jako
        // aktulane prohlizeny do zend registry
        if($setAsCurrent){
            Zend_Registry::set('current_bulletin', $row['id']);
        }

        return $row;
    }



    /** 
     * Najde a vrati seznam vsech bulletinu a jejich zaznam z DB,
     * v pripade vypisu vsech u kazdeho bulletinu vyznaci, jeslti
     * je jiz validni - atribut "is_valid".
     * funkce je uzpusobena pro pouziti ve frontendu
     *
     * @param bool      Vratit vsechny - i nevalidni
     * @param string    Order by klauzule.
     * @param bool      Vratit jen pole ID? (default false)
     * @param bool      Vypsat i skryta vydani? (hodi se pro pouziti v adminu)
     * @return array Pole poli zaznamu jednotlivych bulletinu
     */
    public static function getBulletinList($all = false, $order = null, $onlyIds = false, $withHidden = false)
    {
        Zend_Loader::loadClass('Zend_Auth');

        $db = Zend_Registry::get('db');
        $session = Zend_Registry::get('session');
        $auth = Zend_Auth::getInstance();

        // Pridame podminku podle toho, jestli je prave povoleno zobrazovani neaktivniho
        // obsahu, nebo ne
        if(empty($session->allowed_inactive_content) && !$auth->hasIdentity()){
            $is_valid_sel = '(valid_from < current_timestamp)';
        }
        else{
            $is_valid_sel = '(true)';
        }

        $sel = $db->select();
        if($onlyIds){ // Vratit jen pole ID bulletinu
            $sel->from('bulletins', array('id'));
        }
        else{ // Vratit cele zaznamy bulletinu
            $sel->from('bulletins', array('*', 'is_valid' => $is_valid_sel));
        }

        $sel->where('deleted IS NULL');
        if(!$withHidden){
            $sel->where('NOT hidden');
        }
        
        if($order === null){
            $sel->order('valid_from DESC', 'created DESC');
        }
        else{
            $sel->order($order);
        }

        if(empty($session->allowed_inactive_content) && !$auth->hasIdentity() && !$all){
            $sel->where('valid_from < current_timestamp');
        }

        $rows = $db->fetchAll($sel);
        
        if($onlyIds){ // Vratit jen pole ID bulletinu
            $ids = array();
            foreach($rows as $row){
                $ids[] = $row['id'];
            }
            return $ids;
        }

        return $rows;
    }

    /**
     * Vrati seznam bulletinuu vhodny pro select pole - klicem je ID bulletinu
     * a popisek je podle potreby nazev, nebo nazev a valid from.
     *
     * @param bool   Vypsat vsechny (i neaktivni) bulletiny?
     * @param bool   Pridat do popisu i valid_from?
     * @param string Format datumu z valid_from odpovidajici formatum akceptovanym Zend_Date.
     * @param string Order by klauzule.
     * @param bool   Pred jmeno vydani pridat ID. (default false)
     * @return array Klicem je id bulletinu, hodnotou je popisek.
     */
    public static function getBulletinListForSelect($all, $with_validity = false, $date_format = null, 
        $order = null, $addIds = false)
    {
        $config = Zend_Registry::get('config');

        if($date_format === null){
            $date_format = $config->general->dateformat->medium;
        }

        $bulletins = self::getBulletinList($all, $order, false, true);

        $select = array();
        
        foreach($bulletins as $bul){
            $label = '';
            $label .= $addIds ? $bul['id'].' - ' : ''; // Pridani ID pred nazev 
            $label .= $bul['name'];
            if($with_validity){
                $date = new Zend_Date($bul['valid_from'], Zend_Date::ISO_8601);
                $label .= ' ('.$date->toString($date_format).')';
            }
            $select[$bul['id']] = $label;
        }

        return $select;
    }
    
    /**
     *  Vrátí pole všech bulletinů připravené pro použití v rozbalovacím
     *  seznamu.
     *
     *  @param Jestli se na první místo dá 'nový'.
     *  @param Vrátí se i s IDčkama.
     *  @param Název prvního políčka.
     */
    public function getBulletinSelectData($new = true, $ids = false, $newLabel = 'new')
    {
        $data = $this->getBulletins();
        $retVal = array();
        if ($new)
            $retVal[0] = $newLabel;

        foreach ($data as $bulletin)
        {
            if ($ids)
                $retVal[$bulletin->id] = "$bulletin->id - $bulletin->name";
            else
                $retVal[$bulletin->id] = $bulletin->name;
        }

        return $retVal;
    }

    /**
     * Presmeruje uzivatele na aktulani bulletin, pokud neni pro daneho uzivatele
     * k dispozici zadny aktivni bulletin, je uzivatel presmerovan na stranku s
     * informaci o nedostupnosti zadneho bulletinu.
     *
     * Prenasi do URL vnitrne i hash (anchor), pokud je zadan, protoze v Safari se toto nepredava pri redirectu.
     * Prenos dalsich parametru v URL neni mozny, protoze vstup presmeruje na bulletin, kde route bulletin
     * neumoznuje pridani dalsich parametru na konec cesty, protoze by dochazelo ke kolizi s jinymi cestami.
     *
     * @param bool          Nastavit ziskany bulletin jako aktualni
     * @param array         Pole parametru, ktere se maji pridat do URL (pro route bulletin vsak nelze pridat
     *                      zadne dalsi parametry, protoze to tato route neumoznuje - nema na konci * a nemuze
     *                      mit kvuli kolizim s jinymi routes)
     * @param array urlHash String za hashem (#) v URL (odkazuje napriklad na anchor, nebo slouzi pro akce v JS)
     *                      - musi byt predan vnitrne, protoze Safari tento string pri redirectu zahazuje
     *                      (nesmi obsahovat samotny #)
     */
    public static function redirectToActualBulletin($setAsCurrent = false, $params = array(), $urlHash = '')
    {
        $urlHlpr = new Zend_View_Helper_Url();
        $row = self::getActualBulletinRow($setAsCurrent);

        if(empty($row)){
            //throw new Bulletins_No_Valid_Bulletin_Found_Exception('Nebyl nalezen zadny validni bulletin, proto neni mozne provest redirect na aktualni bulletin.');
            //Redirectujeme na info stranku o nedostupnosti bulletinu.
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('empty', 'index');
        }

        $url_name = $row['url_name'];

        // Prepare URL with params and URL hash
        $url = $urlHlpr->url(array('name' => $url_name) + $params, 'bulletin');
        $url .= $urlHash ? ('#'.$urlHash) : '';
        //ziskame redirector a presmerujeme
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoUrlAndExit($url, array('prependBase' => false));
    }

    /**
     * Vrati true, jestli pro aktualniho uzivatele nebo neznameho
     * existuje nejaky validni bulletin
     *
     * @param bool  Vrati true, pokud je nejaky bulletin mozno zobrazit
     */
    public static function existsAnyValidBulletin()
    {
        $db = Zend_Registry::get('db');
        $auth = Zend_Auth::getInstance();
        $session = Zend_Registry::get('session');

        // Pridame podminku podle toho, jestli je prave povoleno zobrazovani neaktivniho
        // obsahu, nebo ne
        if(empty($session->allowed_inactive_content) && !$auth->hasIdentity()){
            $is_valid_sel = '(valid_from < current_timestamp)';
        }
        else{
            $is_valid_sel = '(true)';
        }

        $sel = $db->select()
                ->from('bulletins', array('url_name', 'valid_from'))
                ->where('deleted IS NULL')
                ->where('NOT hidden')
                ->where($is_valid_sel);
        $row = $db->fetchAll($sel);
        if($row){
            return true;
        }
        else{
            return false;
        }
    }
    
    /**
     * Vrací seznam bulletinu pro Datagrid
     * @param boolean Deleted issue
     * @return Zend_Db_Select
     */
    public static function getBulletinsQuery($deleted = false) {
        $db = Zend_Registry::get('db');
        $select = $db->select()
            ->from('bulletins', array('*', new Zend_Db_Expr('NOT "hidden" AS visible'))); // Musi byt nacteno visible jiz s tebulkou, aby mohly byt pouzity filtry
        if ($deleted) {
            $select->where('deleted IS NOT NULL');
        } else {
             $select->where('deleted IS NULL');
        }
        return $select;
    }

    /**
     * 	Smaže záznam z tabulky bulletins_pages.
     *
     * 	@param Identifikátor bulletinu.
     * 	@param Identifikátor stránky.
     */
    public function deletePage($bulletinId, $pageId) {
        try {
            $config = Zend_Registry::get('config');
            $db = Zend_Registry::get('db');
            $db->delete(
                    $config->tables->bulletins_pages, "page_id = $pageId AND bulletin_id = $bulletinId"
            );
        } catch (Zend_Db_Statement_Exception $e) {
            throw new Bulletins_Exception(
            "Nepodařilo se smazat page s ID: $pageId z bulletinu:
				$bulletinId" . $e);
        }
    }

    /**
     * 	Vrátí seznam stránek, které jsou přiřazeny ke konkrétnímu bulletinu.
     *
     * 	@param Identifikátor bulletinu.
     */
    public function getBulletinPages($id) {
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');

        $select = $db->select()
                ->from(array('bp' => $config->tables->bulletins_pages))
                ->join(array('p' => $config->tables->pages), 'bp.page_id = p.id')
                ->where('bp.bulletin_id = ?', $id)
                ->order('order');

        try {            
            return $db->fetchAll($select);
        } catch (Zend_Db_Statement_Exception $e) {
            throw new Zend_Exception(
            'Nepodařilo se získat stránky bulletinu. SQL:' . $e);
        }
    }

   
    /**
     * 	Najde a vrátí všechny stránky (pages) z tabulky pages, do výsledného
     * 	seznamu se nezapočítají stránky, které již jsou přiřazeny k bulletinu,
     * 	jehož ID je předáno jako parametr.
     *
     * 	@param Identifikátor bulletinu.
     */
    public function getAllPages($bulletinId) {
        
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');          

        $subselect = $db->select()->from(array('cp' => 'content_pages'), array(new Zend_Db_Expr('MAX(c.changed)')))
                        ->joinLeft(array('c' => 'content'), 'c.id = cp.content_id', null)->where('page_id = p.id');

        $select = $db->select()
                ->from(array('p' => $config->tables->pages), array('p.*', 'last_change' => new Zend_Db_Expr('(' . $subselect . ')')))
                ->joinLeft(array('cat' => new Zend_Db_Expr('(
                            SELECT c.*, pc.page_id 
                            FROM categories c
                            JOIN (
                                SELECT pc1.page_id, pc1.significance, max(pc2.category_id) AS category_id FROM
                                    (SELECT page_id, max(significance) as significance 
                                        FROM pages_categories GROUP BY page_id) pc1
                                JOIN pages_categories pc2 ON (pc1.page_id = pc2.page_id AND pc1.significance = pc2.significance) OR (pc1.page_id = pc2.page_id AND pc1.significance IS NULL)
                                GROUP BY pc1.page_id, pc1.significance     
                                ) pc ON c.id = pc.category_id
                        )')), 'cat.page_id = p.id', array('category_id' => 'cat.id', 'category_name' => 'cat.name'))
                ->where('p.id not in (?)', $db->select()->from($config->tables->bulletins_pages, array('p.id' => 'page_id'))
                ->where('bulletin_id = ?', $bulletinId)
        );

        return $select;
    }
    
    
    /**
	 *	Uloží změněné pořadí stránek. Plus změněné URL.
	 *
	 *	@param Identifikátor bulletinu.
	 *	@param Pole obsahující nové pořadí stránek a nová URL.
	 */
	public function newPagesOrderAndUrl($bulletinId, $order)
	{
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');          

		foreach ($order as $pid => $po)
		{
			$data = Array('order' => $po['order'], 'url_name' => $po['url'], 'cycle' => $po['cycle']);
			$where = Array();
			$where[] = $db->quoteInto('page_id = ?',$pid);
			$where[] = $db->quoteInto('bulletin_id = ?',$bulletinId);

			try
			{
				$db->update(
					$config->tables->bulletins_pages,
					$data,
					$where
				);
			}
			catch (Zend_Db_Statement_Exception $e)
			{
				throw new Zend_Exception(
					"Nepodařilo se uložit nové pořadí pro stránky bulletinu s
					ID: $bulletinId".$e);
			}
		}
	}
    
    /**
	 *	Vytvoří nový bulletin.
	 *
	 *	@param Název bulletinu.
	 *	@param Url bulletinu.
	 *	@param Od kdy je platný.
	 *	@param Datum vytvoření.
	 */
	public function newBulletin($name, $url, $validFrom, $created, $layout_name, $skin = null)
	{
		// zrusit, udelat default hodnoty nekde jinde.
		// TODO, tohle by se melo delat mozna nekde jinde
		if (empty($url)) $url = null;
	//	if (empty($validFrom)) $validFrom = null;
	//	if (empty($created)) $created = null;
        
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');          


		try
		{
		    // HACK
		    // Ziskame nove ID bulletinu hloupou metodou, protoze statistiky spolehaji na to, ze
		    // id bulletinuu jdou po sobe
		    $q = "SELECT max(id) FROM bulletins";
		    $id = $db->fetchOne($q) + 1;

		    $data = Array('id' => $id, 'name' => $name, 'url_name' => $url, 'valid_from' => $validFrom,
		      'created' => $created, 'layout_name' => $layout_name, 'skin' => $skin, 'hidden' => false);

			$db->insert(
				$config->tables->bulletins,
				$data);
			//$id = $this->db->lastInsertId($this->config->tables->bulletins, 'id');
		}
		catch (Zend_Db_Statement_Exception $e)
		{
            //checkuje zda není chyba jen v duplicite url, po zadaní stejneho nazvu vydani
             if($e->getCode() == 23505) {
                throw new Zend_Exception($e->getMessage(),1); 
             } else {
                 throw new Zend_Exception('Nepodařilo se vytvořit nový bulletin'.$e->getMessage());
             }
		}
      
        // Vytvorime zaznam do links, pokud jeste neexistuje
        $ldata = array('bulletin_id' => $id, 'name' => $name);
        $links = new Links();
        $links->createLinks($ldata);       

        return $id;
	}    
    
    /**
	 *	Ulozi zmeneny bulletin.
	 *
	 *	@param pole hodnot pro ulozeni.
	 */
    public function saveBulletin($data) {
        
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db'); 
        
        $id = $data['id'];
        $name = $data['name'];
        unset($data['id']);
        unset($data['update_bulletin']);
        
        $data['url_name'] =  Utils::slugify($data['url_name']);

        try {
            $update_result = $db->update(
                    $config->tables->bulletins, $data, "id = $id");
        } catch (Zend_Db_Statement_Exception $e) {
            throw new Zend_Exception(
            "Nepodařilo se uložit změněný bulletin, ID: $id." . $e);
        }

         // Vytvorime zaznam do links, pokud jeste neexistuje
        $data = array('bulletin_id' => $id, 'name' => $name);
        $links = new Links();
        $links->updateLinks($data);    

        return $update_result;
    }
    
    /**
     * Odstrani bulletin
     * @param id bulletinu $id
     * @return type
     * @throws Zend_Exception
     */
     public function delete($id) {
         
        $db = Zend_Registry::get('db'); 
      
        try {
            $where = $db->quoteInto('id = ?', $id);
            $data = array('deleted' => new Zend_Db_Expr('current_timestamp'));
            return $db->update('bulletins',$data,$where);
        } catch (Zend_Db_Statement_Exception $e) {
            echo $e->getMessage();
            throw new Zend_Exception(
            'Chyba pri mazani bulletinu' . $e);
        }
    }
     
    /**
     * Skryje bulletin nebo odkryje
     * @param id bulletinu $id
     * @param bool $hide    Skryt bulletin?
     * @return type
     * @throws Zend_Exception
     */
     public function setHidden($id, $hide){
         
        $db = Zend_Registry::get('db'); 
      
        try {
            $where = $db->quoteInto('id = ?', $id);
            $data = array('hidden' => (bool)$hide);
            return $db->update('bulletins', $data, $where);
        } catch (Zend_Db_Statement_Exception $e) {
            echo $e->getMessage();
            throw new Zend_Exception(
                'Chyba pri mazani bulletinu' . $e);
        }
    }
    
    /**
	 *	Vrátí informace o strance
	 *
	 *	@param id 
	 */
	public function getPageData($id)
	{ 
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db'); 
       
		$select = $db->select()
                ->from($config->tables->pages)
                ->where('id = ?', $id);
        try {
            return $db->fetchRow($select);
        } catch (Zend_Db_Statement_Exception $e) {
            throw new Zend_Exception(
            'Nepodařilo se vykonat SQL dotaz: ' . $select->__toString() . ' Puvodni vyjimka: ' . $e);
        }
	}
    
    /**
	 *	Přidá stránku k bulletinu. Tzn. nový záznam do tabulky bulletins_pages.
	 *
	 * 	@param Identifikátor bulletinu.
	 * 	@param Identifikátor stránky.
	 * 	@param Název URL.
	 *  @param bool Cyklovat tuto page pri navigaci mezi strankami predchozi/dalsi?
	 *
	 *  @throws Bulletins_Exception code 1 prokud je url_name prilis dlouhe.
	 */
	public function addPageToBulletin($bulletinId, $pageId, $urlName)
	{
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        
        //Test urlName
        $i = 1;
        $t_url = $urlName;        
        while ($this->ExistsPageUrlName($t_url,$bulletinId)) {
            $t_url = $urlName.'_'.$i++;          
        }
        $urlName = $t_url;
        
        
		try
		{
			$db->beginTransaction();
			// nejdriv se musi zjistit jake je nejvyssi order u tabulek
			$select = $db->select()
				->from($config->tables->bulletins_pages,
					array('max' => 'MAX("order") + 1'))
				->where('bulletin_id = ?', $bulletinId);

			$result = $db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
			if (is_int($result->max))
				$newOrder = $result->max;
			else
				$newOrder = 1;
          
			$data = Array(
				'bulletin_id' => $bulletinId,
				'page_id' => $pageId,
				'order' => $newOrder,
				'url_name' => $urlName
			);

			$result = $db->insert(
				$config->tables->bulletins_pages,
				$data
			);

			$db->commit();
			return $result;
		}
		catch (Zend_Db_Statement_Exception $e)
		{
			$db->rollBack();
            if ($e->getCode() == 23505) {
                throw new Zend_Exception($e->getMessage(),1);
            } else {
                throw new Zend_Exception(
                "Nepodařilo se přidat stránku k bulletinu, ID stránky: $pageId,
				bulletin ID: $bulletinId " . $e->getMessage());
            }
		}
	}
    
    /**
	 *	Metoda vrátí pole všech bulletinů seřazených podle poradi vydani z bulletins_v.
	 */
	public function getBulletins()
	{
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db'); 
      
		$select = $db->select()
			->from($config->tables->bulletins)			
			->order('valid_from DESC', 'name');

		try
		{
			return $db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
		}
		catch (Zend_Db_Statement_Exception $e)
		{
			throw new Zend_Exception(
				'Nepodařilo se vykonat SQL dotaz: '.$select->__toString());
		}
	}

    
    /**
	 *	Vrátí pole všech stránek připravené pro použití v rozbalovacím
	 *	seznamu.
	 *
	 *	@param Jestli se na první místo dá 'nový'.
	 *	@param Vrátí se i s IDčkama.
	 *	@param Název prvního políčka.
     *
     *  @return array
	 */
	public function getAllPagesForSelect($new = true, $ids = false, $newLabel = 'nový')
	{

		$res = array();
		if ($new) $res[0] = $newLabel;
		
		foreach ($this->getBulletins() as $bulletin) {
			foreach ($this->getBulletinPages($bulletin->id) as $page) {
                $res[$page['id']] = ($ids ? $page['id'] .' - ' : '') . $bulletin->name . ' - ' . $page['name'];
			}
		}

		return $res;
	}
    
    /**
     * Obnoví bulletin
     * @param id bulletinu $id
     * @return type
     * @throws Ibulletin_BulletinAssemblerException
     */
    public function restore($id) {
         $db = Zend_Registry::get('db'); 
        try {
            $where = $db->quoteInto('id = ?', $id);
            $data = array('deleted' => new Zend_Db_Expr('null'));
            return $db->update('bulletins', $data, $where);
        } catch (Zend_Db_Statement_Exception $e) {
            echo $e->getMessage();
            throw new Zend_Exception(
            'Chyba pri obnoveni bulletinu' . $e);
        }
    }
    
    /**
     * Vratí ID posledního bulletinu
     * @return int ID bulletinu
     */
    public static function getLastBulletinId() {
        $db = Zend_Registry::get('db');
        $sql = "SELECT id FROM bulletins WHERE deleted IS NULL ORDER BY valid_from DESC LIMIT 1";
        $row = $db->fetchRow($sql);
        return $row['id'];
    }


    
    

    
    
}
