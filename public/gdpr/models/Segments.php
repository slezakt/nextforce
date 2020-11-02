<?php
/**
 * Trida poskytujici funkce spojene se segmenty a manipulaci s nimi.
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class Segments
{
    /**
     * Seznam segmentů
     * 
     * @return Zend_Db_Select
     */
    public static function getSegmentsQuery() {
        
        $db =  Zend_Registry::get('db');
        $select =  $db->select()
                      ->from('segments')
                      ->where('deleted IS NULL');
        return $select;    
    }
    
    /**
     * Vrati segment dle id ci vsechn segmenty, kdyz neni zadano id
     * @param int id segmentu
     * @param bool  Vratit i smazane segmenty? DEFAULT FALSE
     * @return array pole se segmenty
     */
    public function getSegments($id = 0, $withDeleted = false) {
        $db =  Zend_Registry::get('db');
        
        $select =  $db->select()
                      ->from('segments');
        if ($id > 0) {        
            $select->where('id = ?', $id);
        }
        if(!$withDeleted){
            $select->where('deleted IS NULL');
        }    
        $select->order('id');        
        $segments = $db->fetchAll($select);
        return $segments;    
    }

    /**
     * Vrati pocet segmentu     
     * @return int pocet
     */
    public function getSegmentsCount() {
        
        $segmentsCount = 0;    
        $segmentsCount = count($this->getSegments());        
        return $segmentsCount;    
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
        $db =  Zend_Registry::get('db');
        
        if ($id > 0) {               
            $db->update('segments', array('deleted' => new Zend_Db_Expr('current_timestamp')), 'id='.(int)$id);
        }
    } 
}
