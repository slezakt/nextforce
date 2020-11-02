<?php

/**
 * iBulletin - Categories.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}
class Categories_Exception extends Exception {}

/**
 * Trida obsahujici metody pouzivane pro praci s kategoriemi
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Categories
{

	/**
	* Nacte kategorii podle zadaneho id
	*
	*/
	public static function getCategory($id) {
		 
		$db =  Zend_Registry::get('db');
		 
		$id = intval($id);
		if ($id > 0 ) {			
			$q = 'SELECT * FROM categories WHERE id = '.$id.' LIMIT 1'; 
			$rows = $db->fetchAll($q);
			if(!empty($rows)){
	            return $rows[0];
	        }
	        else{
	            return null;
	        }
		}
		return null;
	}
    
    /**
     * Najde zaznam katagorie zadane pomoci url jmena.
     * 
     * @param string URL jmeno kategorie
     * @return array Radek pozadovane kategorie z tabulky categories, Pokud url name neexistuje, vraci NULL
     */
    public function getCategoryInfo($url_name){
        $db =  Zend_Registry::get('db');
        
        $url_name_quot = $db->quote($url_name);
        
        $q = "SELECT * FROM categories WHERE url_name = $url_name_quot";
        $rows = $db->fetchAll($q);
        
        if(!empty($rows)){
            return $rows[0];
        }
        else{
            return null;
        }
    }
    
    /**
     * Najde zaznam katagorii pro zadane page_id. Seradi je podle signifcance a naledne category_id
     * 
     * @param $page_id int  page_id
     * @return array        Pole poli obsahujici zaznamy jednotlivych kategorii pro zadanou page.
     *                      Pokud neni zadna kategorie nalezena vraci prazdne pole.
     */
    public static function getPageCategories($pageId){
        $db =  Zend_Registry::get('db');
        
        $q = "SELECT c.* 
            FROM pages_categories pc
            JOIN categories c ON pc.category_id = c.id
            WHERE pc.page_id = ".(int)$pageId.' 
            ORDER BY pc.significance, pc.category_id';
        $rows = $db->fetchAll($q);
        
        if(!empty($rows)){
            return $rows;
        }
        else{
            return array();
        }
    }
    
     /**
     * Vrati seznam kategorii pro grid
     * 
      *@param boolean Deleted category
     * @return Zend_Db_Select
     */
    public static function getCategoriesQuery($deleted = false)
    {
        $db = Zend_Registry::get('db');
        $select = $db->select()->from("categories");  
        if ($deleted) {
            $select->where('deleted IS NOT NULL');
        } else {
            $select->where('deleted IS NULL');
        }
        return $select;
    }

    /**
     * get highest order number in categories
     * @return int
     */
    public static function getHighestOrder() {
        $db = Zend_Registry::get('db');
        $select = $db->select()->from("categories", array(new Zend_Db_Expr('MAX("order")')));
        return (int) $db->fetchOne($select);

    }

    /**
     * Vrati seznam kategorii
     * 
     * @param bool       Vcetne smazanych?
     * @param array/null Ktere sloupce, pokud je null vraci vsechny
     * @param array/null Podle ceho radit - kazdy prvek pole je nazev sloupce i se smerem razeni
     *                   napriklad array('order ASC')
     * @param bool       Vracet jen smazane kategorie?
     * @param int        Vracet jen kategorie pro zadany bulletin - ID z tabulce bulletins
     * 
     * @return  Pole jednotlivych radku z DB
     */
    public static function getList($with_deleted = false, $columns = null, $order = null,
        $only_deleted = false, $forBulletin = null)
    {
        $db =  Zend_Registry::get('db');
        
        if($order === null){
            $order = array('order ASC');
        }
        if($columns === null){
            $columns = array('*');
        }
        
        $sel = new Zend_Db_Select($db);
        $sel->from(array('c' => 'categories'), $columns)
            ->order($order);
        if(!$with_deleted && !$only_deleted){
            $sel->where('c.deleted IS null');
        }
        elseif($only_deleted){
            $sel->where('c.deleted IS NOT null');
        }
        
        // Vyhazeni polozek neplatnych pro dany bulletin
        // Pokud je u platnosti od nebo do nastaveno NULL, bereme ji z te strany neomezenou
        if($forBulletin){
            $forBulletin = (int)$forBulletin;
            $sel->join(array('currb' => 'bulletins'), 'currb.id = '.$forBulletin, array())
                ->joinLeft(array('fromb' => 'bulletins'), 'fromb.id = c.valid_from', array())
                ->joinLeft(array('tob' => 'bulletins'), 'tob.id = c.valid_to', array())
                ->where('(currb.id = tob.id OR currb.id = fromb.id '.
                        'OR ((fromb.valid_from < currb.valid_from OR fromb.id IS NULL) '.
                        'AND (tob.valid_from > currb.valid_from OR tob.id IS NULL)))');
        }
        
        
        $r = $db->fetchAll($sel);
        
        return $r;
    }

    /**
     * prida kategorii, vrati id nebo false
     *
     * @param   int     ID kategorie z tabulky categories
     * @return  int|bool    Povedl se update
     */
    public static function insert($data) {
        $db =  Zend_Registry::get('db');

        $affected = $db->insert('categories', $data);

        // Vytvorime novy link pro tuto kategorii
        $id = $db->lastInsertId('categories', 'id');
        $db->insert('links', array('category_id' => $id, 'name' => $data['name']));

        if($affected){
            return $id;
        }
        else{
            return false;
        }
    }

    /**
     * edituje kategorii podle ID
     *
     * @param   int     ID kategorie z tabulky categories
     * @param   array   data
     * @param   bool    pri zmene poradi posune ostatni polozky
     * @return  bool    Povedl se update
     * 
     */
    public static function update($id, $data, $reorder = false){
      
        $db =  Zend_Registry::get('db');
        
      
        $affected = $db->update('categories', $data, sprintf('id=%d', $id));

        // dohledame link pro tuto kategorii
        $link_id = $db->fetchOne(sprintf('SELECT id FROM links WHERE category_id = %d', $id));
        if (!is_numeric($link_id)) {
            // Vytvorime novy link pro tuto kategorii
            $db->insert('links', array('category_id' => $id, 'name' => $data['name']));
        } else {
            // Upravime nazev jiz existujiciho linku
            $db->update('links', array('name' => $data['name']), sprintf('id=%d', $link_id));
        }
        
        if($affected){
            
            if ($reorder) {
                $poradi = $data['order'];
                
                $sel = $db->select()->from('categories')->where('"order" = ?',$poradi);
                $rows = $db->fetchAll($sel);
                
                //paklize existuji polozky se stejnym poradim provedem precislovani 
                if(count($rows)>1) {
                    //ziskame vsechny polozky s vyssim poradim a posuneme jejich poradi, nezachováme původní číslování, přiřadíme novou posloupnost
                    $sel = $db->select()->from('categories',array('id',new Zend_Db_Expr('"order"')))
                            ->where('deleted IS NULL')->where('id != ?',$id)->where('"order" >= ?',$poradi)->order(new Zend_Db_Expr('"order"'));
                    $rows = $db->fetchAll($sel);
                    foreach ($rows as $row) {
                       //prvni polozku posuneme vzdy
                        $poradi++;
                        self::update($row['id'], array('order'=> $poradi));
                    }
                }
                
            }
            
            return true;
        }
        else{
            return false;
        }
    }
    
    /**
     * Smaze kategorii podle ID
     * 
     * @param   int     ID kategorie z tabulky categories
     * @return  bool    Povedlo se nastaveni za smazanou?
     */
    public static function delete($id){
        $db =  Zend_Registry::get('db');
        
        $data = array('deleted' => new Zend_Db_Expr('current_timestamp'));
        
        $affected = $db->update('categories', $data, sprintf('id=%d', $id));
        
        if($affected){
            return true;
        }
        else{
            return false;
        }
    }
    
    /**
     * Obnovi smazanou kategorii podle ID
     * 
     * @param   int     ID kategorie z tabulky categories
     * @return  bool    Povedlo se obnoveni?
     */
    public static function renew($id){
        $db =  Zend_Registry::get('db');
        
        $data = array('deleted' => new Zend_Db_Expr('null'));
        
        $affected = $db->update('categories', $data, sprintf('id=%d', $id));
        
        if($affected){
            return true;
        }
        else{
            return false;
        }
    }
}
