<?php

/**
 * iBulletin - Authors.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}

/**
 * Trida obsahujici metody pouzivane pro praci s autory
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Authors
{
    /**
     * Vrati pole jmen autoru vhodne treba pro selectbox. Klicem je ID.
     * 
     * @param  bool Pridat polozku default s popiskem "Vse" na zacatek?
     * @param  bool Vratit vsechny autory (true), vratit jen autory aktivnich clanku (false)
     * @return array Autori z tabulky authors, klicem je id, hodnotou je name
     */
    public function getAuthorsSelectList($add_default = false, $all = false){
        $db =  Zend_Registry::get('db');
        
        // Vytvorime podminku podle toho, jestli je prave povoleno zobrazovani neaktivniho
        // obsahu, nebo ne
        Zend_Loader::loadClass('Zend_Auth');
        $session = Zend_Registry::get('session');
        $auth = Zend_Auth::getInstance();
        if(!empty($session->allowed_inactive_content) || $auth->hasIdentity() || $all){
            $show_inactive_where = '1 = 1';
        }
        else{
            $show_inactive_where = 'b.valid_from < current_timestamp';
        }
        
        if($add_default){
            $authorsA = array('default' => Ibulletin_Texts::get('.all'));
        }
        else{
            $authorsA = array();
        }
        
        $sel = "
                SELECT DISTINCT ON (a.name) a.id, a.name 
                FROM
                    pages p 
                    JOIN content_pages cp ON p.id = cp.page_id
                    JOIN content c ON cp.content_id = c.id
                    JOIN authors a ON a.id = c.author_id
                    JOIN bulletins_pages bp ON p.id = bp.page_id
                    JOIN bulletins b ON b.id = bp.bulletin_id
                WHERE
                    $show_inactive_where                                    
                ORDER BY a.name                
                ";
        $author_rows = $db->fetchAll($sel);
        
        foreach ($author_rows as $author){
            $authorsA[$author['id']] = $author['name'];
        }
        
        return $authorsA;
    }
    
    /**
     * Nacte konkretniho autora
     * 
     */
    public function getAuthor($id = 0) {
       
       $_author = array();
       $db =  Zend_Registry::get('db');
       
       $id = intval($id);
       if ($id > 0 ) {
	       // najdi autora
	       $_query = 'SELECT id, name 
	                  FROM authors      
	                  WHERE id = '.$id.'       
                      LIMIT 1       
	       '; 
	       $_author = $db->fetchAll($_query);
		   return $_author[0];
       }
       return NULL;
    }
    
    /**
    * Seznam autorů pro grid 
    *  
    * @return Zend_Db_Select
    */
     public static function getAuthorsQuery() {

        $db = Zend_Registry::get('db');
        
        $select = $db->select()->from(array("p" => "pages"),array(
            "a.*","count(a.name)","articles"=>new Zend_Db_Expr("string_agg(p.name,'<br />')")))
                ->join(array("cp" => "content_pages"),"p.id=cp.page_id",null)
                ->join(array("c" => "content"),"cp.content_id=c.id",null)
                ->joinRight(array("a" => "authors"),"a.id=c.author_id")
                ->group(array("a.id","a.name"));       
        return $select;
    }

    /**
     * nacte vsechny autory
     * @param boolean nacte i s jejich clanky
     * @return array pole autoru      
     */
    public function getAuthors($withArticles = false) {
       
       $_authors = array();
       $db =  Zend_Registry::get('db');

       // najdi autory
       $_query = 'SELECT DISTINCT ON (name) id, name 
                  FROM authors             
                  GROUP BY id, name      
       '; 
       $_authors = $db->fetchAll($_query);  

       // prirad clanky
       if ($withArticles === true){
            $_tmpAuthors = array();
            foreach ($_authors as $_author) {
                $_query = 'SELECT p.name 
                FROM
                    pages p 
                    JOIN content_pages cp ON p.id = cp.page_id
                    JOIN content c ON cp.content_id = c.id
                    JOIN authors a ON a.id = c.author_id
                    JOIN bulletins_pages bp ON p.id = bp.page_id
                    JOIN bulletins b ON b.id = bp.bulletin_id                                   
                WHERE a.id = '.$_author['id'].'
                ORDER BY a.name 
                ';
                $_articles = $db->fetchAll($_query);                
                                                                                                
                $_tmpAuthors[] = array(
                    'id' => $_author['id'],
                    'author' => $_author['name'],
                    'articles' => $_articles
                );   
            }
            $_authors = $_tmpAuthors;
       }
       
       return $_authors;
    } 
    
    /**
     * nacte vsechny autory s jejich clanku
     * @return array pole autoru s clanky      
     */
    public function getAuthorsWithArticles() {
        return self::getAuthors(true);
    } 
    
    /**
     * ulozi autora
     * @param array data o autorovi
     * @param string pridat novy zaznam nebo provest update
     * @return boolean zda se podarilo ulozit
     */
    public function saveAuthor($data = array(), $mode = 'update') {
        $saved = false;
        
        if ($mode == 'update') {
            $query = "UPDATE authors
                      SET name = '".$data['name']."'
                      WHERE id = ".$data['id']."   
            ";
        }
        elseif ($mode == 'insert') {
            $query = "INSERT INTO authors (name)
                      VALUES('".$data['name']."')  
            ";
        }
        else {
            $query = '';
        }
        
        if (!empty($data) && !empty($query)) {
            $db =  Zend_Registry::get('db');                
            try {
                $db->query($query);
                $saved = true;
            }
            catch (Zend_Db_Exception $e) {
                $saved = false;
            }            
                        
        }
        
        return $saved;
    }
    
    /**
     * smaze autora
     * @param int id autora
     */
    public function deleteAuthor($id = 0) {
        $deleted = false;
        
        $id = intval($id);
        if ($id > 0) {
            $query = "DELETE FROM authors                      
                      WHERE id = ".$id."   
            ";
            $db =  Zend_Registry::get('db');                
            try {
                $db->query($query);
                $deleted = true;
            }
            catch (Exception $e) {
                $deleted = false;
            }
        }
        return $deleted;
    }
}
