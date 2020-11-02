<?php
/**
 * Trida poskytujici funkce spojene s reprezentanty a manipulaci s nimi.
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class Reps
{
    /**
     * Vrati reprezentanta dle id ci vsechny reprezentanty, kdyz neni zadano id
     * @param int id reprezentanta
     * @return array pole s reprezentanty
     */
    public function getReps($id = 0) {
        $db =  Zend_Registry::get('db');
        
        $select =  $db->select()
                      ->from('users')
                      ->where('is_rep = true')
                      ;
        if ($id > 0) {        
            $select->where('id = ?', $id);
        }      
        $select->order('id');        
        $reps = $db->fetchAll($select);
        return $reps;    
    }

    /**
     * Vrati pocet repu     
     * @return int pocet
     */
    public function getRepsCount() {
        
        $repsCount = 0;    
        $repsCount = count($this->getReps());        
        return $repsCount;    
    }    
    
    /**
     * Ulozi segment
     * @param array data k ulozeni
     * @param string mod ulozeni, zda insert ci update; default update
     * @return boolean zda podarilo ulozit
     */
    public function saveSegment($data = array(), $mode = 'update') {
        $saved = false;
        
        if ($mode == 'update') {
            $query = "UPDATE segments
                      SET name = '".$data['name']."'
                      WHERE id = ".$data['id']."   
            ";
        }
        elseif ($mode == 'insert') {
            $query = "INSERT INTO segments (name)
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
     * smaze segment
     * @param int id segmentu
     */
    public function deleteSegment($id = 0) {
        
        $deleted = false;
        if ($id > 0) {               
            $query = "DELETE FROM segments
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
    
    /**
     * Kontroluje zda tabulka sessions by reps obsahuje nejake zaznamy
     * @return bool 
     */
    public static function checkSessionsByReps() {
        $db = Zend_Registry::get('db');

        $select = $db->select()->from('sessions_by_reps');
        $s = $db->fetchAll($select);

        if (count($s) > 0) {
            return true;
        } else {
            return false;
        }
    }

}
