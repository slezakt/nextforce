<?php
/**
 * Trida poskytujici funkce spojene s kanaly a manipulaci s nimi.
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class Channels
{
    /**
     * Seznam kanálů
     * 
     * @return Zend_Db_Select
     */
     public static function getChannelsQuery() {
        $db =  Zend_Registry::get('db');
        
        $select =  $db->select()
                      ->from('channels');        
        return $select;    
    }
    /**
     * Vrati kanal dle id ci vsechny kanaly, kdyz neni zadano id
     * @param int id kanalu
     * @return array pole s kanaly
     */
    public function getChannels($id = 0) {
        $db =  Zend_Registry::get('db');
        
        $select =  $db->select()
                      ->from('channels');
        if ($id > 0) {        
            $select->where('id = ?', $id);
        }      
        $select->order('id');        
        $regions = $db->fetchAll($select);
        return $regions;    
    }

    /**
     * Vrati pocet kanalu     
     * @return int pocet
     */
    public function getChannelsCount() {
        
        $channelsCount = 0;    
        $channelsCount = count($this->getChannels());        
        return $channelsCount;    
    }  
        
    /**
     * Ulozi kanal
     * @param array data k ulozeni
     * @param string mod ulozeni, zda insert ci update; default update
     * @return boolean zda podarilo ulozit
     */
    public function saveChannel($data = array(), $mode = 'update') {
        $saved = false;
        
        if ($mode == 'update') {
            $query = "UPDATE channels
                      SET name = '".$data['name']."',
                          code = '".$data['code']."'
                      WHERE id = ".$data['id']."   
            ";
        }
        elseif ($mode == 'insert') {
            $query = "INSERT INTO channels (name, code)
                      VALUES('".$data['name']."', '".$data['code']."')  
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
     * smaze kanal
     * @param int id kanalu
     */
    public function deleteChannel($id = 0) {
        
        $deleted = false;
        if ($id > 0) {               
            $query = "DELETE FROM channels
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
