<?php
/**
 * Trida pro manipulaci s resources (tabulka resources).
 *
 * @author Petr Skoda
 */
class Resources
{
    /**
     * Seznam resources
     * @param boolean deleted resources $deleted
     * @return Zend_Db_Select
     */
     public static function getResourcesQuery($deleted = false){
        $db = Zend_Registry::get('db');        
        $select = $db->select()->from(array('r'=>'resources'),array('r.*','filename' => new Zend_Db_Expr('regexp_replace(r.path, \'.+[\\\/]\', \'\')')))
                ->joinLeft(array('b'=>'bulletins'),'b.id = r.bulletin_id',array('bulletin' => 'b.name'))
                ->joinLeft('content','r.content_id = content.id',array('content'=>'content.name')); 
        
        if($deleted) {
            $select->where('r.deleted IS NOT NULL');
        } else {
            $select->where('r.deleted IS NULL');
        }
       
        return $select;
    }
    /**
     * Vrati data resource podle id.
     * 
     * @param   int     ID resource z tabulky resources.
     * @return array    Pole s resources
     */
    public static function get($id){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from(array('r' => 'resources'), array('*'))
            ->where('id = ?', (int)$id);
        
        $data = $db->fetchRow($sel);
        return $data;
    }
    

    /**
     * Vraci data resources v poli pro vsechny zadane contenty, bulletiny nebo kombinaci.
     * 
     * @param   array   Pole ID contentu.
     * @param   array   Pole ID bulletinu.
     * @param   bool    Overovat existenci souboru a vracet jen existujici resources? (default true)
     * @param   bool    Ignorovat smazane rexources? (Default true)
     * @param   string  Vrati resource nejakeho specifickeho typu
     * @return  array   Pole poli obsahujici jednotlive zaznamy z tabulky resources
     */
    public static function getBy($contentIds = array(), $bulletinIds = array(), $checkExistence = true, $ignoreDeleted = true, $specialType = null){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from(array('r' => 'resources'), array('*'));
        
        foreach($contentIds as $content){
            $sel->orwhere('content_id = ?', (int)$content);
        }
        foreach($bulletinIds as $bulletin){
            $sel->orwhere('bulletin_id = ?', (int)$bulletin);
        }
        $sel->order('order asc');
        
        $sel1 = $sel;
        $sel = new Zend_Db_Select($db);
        
        $sel->from(array('a' => $sel1), array('*'));
        // Ignore deleted?
        if($ignoreDeleted){
            $sel->where('deleted IS NULL');
        }
        
        if ($specialType) {
            $sel->where('special_type = ?',$specialType);
        } else{
            $sel->where('special_type IS NULL');
        }

        $data = $db->fetchAll($sel);
        
        // Vyhodime z vysledku neexistujici resources pokud je to pozadovano
        if($checkExistence){
            foreach($data as $key => $resource){
                if(!file_exists($resource['path'])){
                    unset($data[$key]);
                }
            }
        }
        
        return $data;
    }

    
    /**
     * Updates or creates new resource record in DB.
     * 
     * For new resource there are no requirements - data can be empty array.
     * If the file defined by path exists during create of resource, the file 
     * is correctly renamed to name ending with resource ID to better 
     * face the collisions of names.
     * 
     * Available parameters to be set:
     * contentId
     * bulletinId
     * name
     * path
     *  
     * @param array     Data of the resource.
     * @param int       ID of the resource. If null, new resource is created.
     * @param bool      append resource id to name
     * @return int      ID of newly created resource.
     */
    public static function update($data, $id = null, $rename = true)
    {
        $db = Zend_Registry::get('db');
        
        // List of attributes that can be saved
        $availableAttribs = array('content_id', 'bulletin_id', 'name', 'path', 'deleted','description', 'order','special_type');
        
        // Use only known attribs
        $dataClean = array();
        foreach($data as $key => $val){
           if(!in_array($key, $availableAttribs)){
                // Log error, because there is used some unknown attrib.
                Phc_ErrorLog::warning('Resources::update()', 
                    'Unknown parameter "'.$key.'" = "'.$val.'" was passed to be saved into resources table.');
            }
            else{
                $dataClean[$key] = $val;
            }
        }

        $order = $dataClean['order'] = ($dataClean['order'] ?: 1);
        // do reordering based on order
        $orderExists = (boolean)$db->select()
            ->from('resources', array('order'))
            ->where('"order" = ?', $order)
            ->query()->fetchColumn();

        if ($id) {
            $originalOrder = (int)$db->select()
                ->from('resources', array('order'))
                ->where('id = ?', $id)
                ->query()->fetchColumn();

            if ($orderExists) {
                if ($order < $originalOrder) {
                    $db->update('resources', array(
                            'order' => new Zend_Db_Expr('"order" + 1')),
                        new Zend_Db_Expr('"order" >= ' . $order . ' AND "order" < '.$originalOrder)
                    );
                } elseif ($order > $originalOrder) {
                    $db->update('resources', array(
                            'order' => new Zend_Db_Expr('"order" - 1')),
                        new Zend_Db_Expr('"order" > ' . $originalOrder. ' AND "order" <= '.$order)
                    );
                } else {
                    // do nothing, no change
                }
            }

        } else {
            if ($orderExists) {
                $db->update('resources', array(
                        'order' => new Zend_Db_Expr('"order" + 1')),
                    new Zend_Db_Expr('"order" >= ' . $order)
                );
            }
        }
        // INSERT new row
        if(!$id){

            $db->insert('resources', $dataClean);
            $id = $db->lastInsertId('resources','id');
            
            // Insert record into table links
            $linkData = array('resource_id' => $id);
            $linkData['name'] = isset($dataClean['name']) ?  $dataClean['name'] : '';
            $db->insert('links', $linkData);
            
            // Rename the file to name ending with resource ID
            if($rename && isset($dataClean['path']) && file_exists($dataClean['path'])){
                $pathI = pathinfo($dataClean['path']);
                // Check if the file is not already renamed
                if(!preg_match('/.*_'.$id.'$/i', $pathI['filename'])){
                    $newPath = $pathI['dirname'].'/'.$pathI['filename'] .'_'.$id. '.' . $pathI['extension'];
                    if(@rename($dataClean['path'], $newPath)){
                        // File renamed, now update the data in db
                        $db->update('resources', array('path' => $newPath), 'id = '.(int)$id);
                    }
                }
            }
            
            return $id;
        }
        // UPDATE
        else{
             // If the name is set in update, we update the name of link too
            if($dataClean['name']){
                $db->update('links', array('name' => $dataClean['name']), 'resource_id = '.(int)$id);
            }
            
            return $db->update('resources', $dataClean, 'id = '.(int)$id);
           
        }
    }
    
    /**
     * Deletes resource. Only marks the record of the resource as deleted.
     * Does not delete the file itself.
     * 
     * @param int   ID of the resource to be deleted.
     */
    public static function delete($id)
    {
        $db = Zend_Registry::get('db');
        $where = $db->quoteInto('id = ?',$id);
        $db->update('resources', array('deleted' => new Zend_Db_Expr('current_timestamp')), $where); 
    }
    
    /**
     * Gets ID resource's link.
     * 
     * @param   int Resource Id
     * @return  int Link ID
     */
    public static function getLink($id)
    {
        $db = Zend_Registry::get('db');
        $sel = new Zend_Db_Select($db);
        $sel->from(array('l' => 'links'), array('id'))
            ->where('resource_id = ?', $id)
            ->order('id')
            ->limit(1);
        $linkId = $db->fetchOne($sel);
        
        return $linkId;
     }
}
        