<?php
/**
 * Trida poskytujici funkce spojene se segmenty, kanaly, regiony atd. (souhrnne nazyvane oblasti)pro statistiky.
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 * @author R. Vajsar
 */
class Areas
{
    /**
     * Vrati oblast dle id ci vsechny oblasti, kdyz neni zadano id
     * @param string typ oblasti 
     * @param int id oblasti
     * @return array pole s oblastmi
     */
    public function getAreas($area='', $id = 0) {
        $db =  Zend_Registry::get('db');
        
        $select =  $db->select()
                      ->from($area);
        if ($id > 0) {        
            $select->where('id = ?', $id);
            }      
        $select->order('id');        

        try {
                $areas = $db->fetchAll($select);
                }
        catch (Zend_Db_Exception $e) {
                    $areas = null;
                }            
        return $areas;    
    }

    /**
     * Vrati pocet kanalu     
     * @return int pocet
     */
    public function getAreasCount($area='') {
        
        $channelsCount = 0;    
        $channelsCount = count($this->getAreas($area));        
        return $channelsCount;    
    }  
        
 }
