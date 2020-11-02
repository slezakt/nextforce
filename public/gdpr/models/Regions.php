<?php
/**
 * Trida poskytujici funkce spojene s regiony a manipulaci s nimi.
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class Regions
{
    /**
     * Seznam regionů
     * 
     * @return Zend_Db_Select
     */
      public static function getRegionsQuery() {
        $db =  Zend_Registry::get('db');        
        $select =  $db->select()
                      ->from('regions'); 
        return $select;    
    }
    
    /**
     * Vrati region dle id ci vsechny regiony, kdyz neni zadano id
     * @param int id regionu
     * @return array pole s regiony
     */
    public function getRegions($id = 0) {
        $db =  Zend_Registry::get('db');
        
        $select =  $db->select()
                      ->from('regions');
        if ($id > 0) {        
            $select->where('id = ?', $id);
        }      
        $select->order('id');        
        $regions = $db->fetchAll($select);
        return $regions;    
    }

    /**
     * Vrati pocet regionu     
     * @return int pocet
     */
    public function getRegionsCount() {
        
        $regionsCount = 0;    
        $regionsCount = count($this->getRegions());        
        return $regionsCount;    
    }      
    
    /**
     * Ulozi region
     * @param array data k ulozeni
     * @param string mod ulozeni, zda insert ci update; default update
     * @return boolean zda podarilo ulozit
     */
    public function saveRegion($data = array(), $mode = 'update') {
        $saved = false;
        
        if ($mode == 'update') {
            $query = "UPDATE regions
                      SET name = '".$data['name']."'
                      WHERE id = ".$data['id']."   
            ";
        }
        elseif ($mode == 'insert') {
            $query = "INSERT INTO regions (name)
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
     * smaze region
     * @param int id region
     */
    public function deleteRegion($id = 0) {
        
        $deleted = false;
        if ($id > 0) {               
            $query = "DELETE FROM regions
                      WHERE id = ".$id."  
            ";
        
	        if (!empty($query)) {
	            $db =  Zend_Registry::get('db');                
	            try {
	                $db->query($query);
	                $deleted = true;
	            }
	            catch (Zend_Db_Exception $e) {
	                $deleted = false;
	            }            
	        }        
	        return $deleted; 
	     }    
    } 
}
