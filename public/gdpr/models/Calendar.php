<?php

/**
 * Obsluha kalendare
 *
 * @author Ondra Bláha <ondrej.blaha@pearshealthcyber.com>
 */
class Calendar extends Zend_Db_Table_Abstract {

    protected $_name = "calendar_events";
    protected $_primary = "id";
    protected $_db;
    
    public function __construct() {
        $this->_db = Zend_Registry::get('db');
    }

    /**
     * Získa udalosti z kalendare EUNI s prijatych dat si jeste vytvori ciselniky pro filtry
     * @param $contentId ID contentu
     * @param array data ziskana euni jinou cestou
     * @return array|string  vrati seznam udalosti nebo chybu
     */
    public function getEUNIEvents($contentId = null, $data = null) {

        $config = Zend_Registry::get('config');
        
        if (!$data) {
            $client = new Zend_Http_Client($config->calendar->euni_url, array('timeout'=>30));
            $client->setHeaders('Content-type', 'application/json');
            $json = json_encode(array('hash' => $config->calendar->euni_hash, 'without'=> array('image_base','bg_image_base'),'date' => '2000-01-01'));
            $client->setRawData($json, 'application/json')->request('POST');
            $response = $client->request(Zend_Http_Client::POST)->getBody();

            $data = json_decode($response,true);
        }
        
        if (isset($data['error'])) {
            Phc_ErrorLog::error('Calendar::getEUNIEvents', $data['error']);
        }

        $events = array();
        $specs = array();
        $tags = array();

        $events['data'] = array();
        $events['raw'] = array();
        
        if (isset($data['actions'])) {
            foreach ($data['actions'] as $ev) {

                //jiz ulozeny zaznam preskocime
                if ($contentId && $this->getEvents($contentId, $ev['id'])) {
                    continue;
                }
                
                $specializations = implode(', ', array_map(function($v) {
                            return $v['name'];
                        }, $ev['specializations']));
                $tags_str = implode(', ', array_map(function($v) {
                            return $v['name'];
                        }, $ev['tags']));
                                
                $dateBegin = new Zend_Date($ev['dateBegin']['date']);
                $dateEnd = new Zend_Date($ev['dateEnd']['date']);
        
                $dateBegin->setTimezone($ev['dateBegin']['timezone']);
                $dateEnd->setTimezone($ev['dateEnd']['timezone']);

                $ev['helper']['formattedDateBegin'] = $dateBegin->toString($config->general->dateformat->medium);
                $ev['helper']['formattedDateEnd'] = $dateEnd->toString($config->general->dateformat->medium);

                $events['data'][] = array($ev['id'], $ev['title'], $ev['dateBegin']['date'], $ev['dateEnd']['date'], $specializations, $tags_str);
                $ev['helper']['specs_str'] = $specializations;
                $ev['helper']['tags_str'] = $tags_str;


                $events['raw'][$ev['id']] = $ev;

                //vyzobeme si specializace pro ciselnik
                foreach ($ev['specializations'] as $s) {
                    $specs[$s['id']] = $s['name'];
                }

                //vyzobeme si tagy pro ciselnik
                foreach ($ev['tags'] as $t) {
                    $tags[$t['id']] = $t['name'];
                }
            }
        }
        sort($specs);
        sort($tags);
        //ciselnik tagu a specializaci
        $events['enum']['specializations'] = $specs;
        $events['enum']['tags'] = $tags;

        return $events;
    }
    
    /**
     * Pocet eventu v kalendari
     * @param int $contentId ID contentu
     * @param array pouze udalosti s tagem
     * @return int Pocet zaznamu
     */
    public static function getEventsCount($contentId, $tags = array()) {

        $db = Zend_Registry::get('db');
        $select = $db->select()->from(array('ce'=>'calendar_events'), new Zend_Db_Expr('count(distinct ce.id)'))->where('content_id = ?', $contentId);
        
        //filtrování podle tagů
        if ($tags) {
            $select->join(array('cet'=>'calendar_events_tags'),'ce.id = cet.calendar_events_id',null)
            ->where('cet.calendar_tags_id IN (?)',$tags); 
        }

        $result = $db->fetchOne($select);

        return $result;
    }

    /**
     * Vrati seznam udalosti kalendare
     * @param int $contentId id contentu
     * @param int $eventId id udalosti, nepovinny parametr, ktery vrati konkretni udalost kalendare
     * @param int $page cislo stranky (strankovani)
     * @param int pocet radek (strankovani)
     * @param array vraci pouze udalosti s tagy
     * @param string razeni kalendarni dat (like sql)
     * @return array
     */
    public static function getEvents($contentId, $eventId = null,$page = null, $rowCount = null, $tags = array(), $order = null) {

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('db');
        
  
        $select = $db->select()->from(array('ce'=>'calendar_events'),array(
            'ce.*',
            'tags'=>new Zend_Db_Expr("array_to_string(ARRAY(SELECT ct.name FROM calendar_events_tags AS cet 
                LEFT JOIN calendar_tags as ct ON cet.calendar_tags_id = ct.id
                WHERE cet.calendar_events_id = ce.id
                ORDER BY ct.name),', ')"),
            'specializations' => new Zend_Db_Expr("array_to_string(ARRAY(SELECT cs.name FROM calendar_events_specializations AS ces
                LEFT JOIN calendar_specializations AS cs ON ces.calendar_specializations_id = cs.id
                WHERE ces.calendar_events_id = ce.id
                ORDER BY cs.name),', ')")
            
        ));
        
        $select->where('content_id = ?',$contentId);
        
        if ($eventId) {
            $select->where('event_id = ?',$eventId);
        }
        
        if ($page && $rowCount) {
            $select->limitPage($page, $rowCount);
        }
        
        //filtrování podle tagů
        if ($tags) {
            $select->distinct()->join(array('cet'=>'calendar_events_tags'),'ce.id = cet.calendar_events_id',null)
            ->where('cet.calendar_tags_id IN (?)',$tags); 
        }
       
       if(!$order) {
          $order = 'id DESC';
       }
        
       $select->order($order);
       
        if ($eventId) {
            return $db->fetchRow($select);
        } else {
            $rows = $db->fetchAll($select);
            $items = array();
            
            foreach ($rows as $row) {
                $items[$row['id']] = $row;
            }
            
            return $items;
        }
    }

    /**
     * Ulozi udalosti do kalendare
     * @param array $data data k ulozeni
     * @return bool info o ulozeni
     */
    public function save($data) {

        $saveData = array();

        if (!isset($data['id']) || !isset($data['content_id'])) {
            return false;
        }
        
        $saveData['event_id'] = $data['id'];
        $saveData['content_id'] = $data['content_id'];
        $saveData['title'] = $data['title'];
        $saveData['description'] = $data['description'];
        $saveData['place'] = $data['place'];
        $saveData['address'] = $data['address'];
        $saveData['url_action'] = $data['url_action'];
        $saveData['image'] = $data['image_abspath'];
        
        $dateBegin = new Zend_Date($data['dateBegin']['date']);
        $dateEnd = new Zend_Date($data['dateEnd']['date']);
        $updated = new Zend_Date($data['update_at']['date']);
        
        $dateBegin->setTimezone($data['dateBegin']['timezone']);
        $dateEnd->setTimezone($data['dateEnd']['timezone']);
        $updated->setTimezone($data['update_at']['timezone']);
        
        $saveData['date_begin'] = $dateBegin->getIso();
        $saveData['date_end'] = $dateEnd->getIso();
        $saveData['updated'] = $updated->getIso();
        
        //seznam atributu ktere nebude ukladat do additional
        $excludeKey = array('id','content_id','title','description','place',
            'address','url_action','image_abspath','dateBegin','dateEnd','update_at','helper','tags','specializations');
        $additional = array();
        foreach ($data as $key => $value) {
           if (!in_array($key, $excludeKey)) {
               $additional[$key] = $value;
           }
        }
        
        if ($additional) {
            $saveData['additional'] = json_encode($additional);
        }

        try {

            $item = $this->getEvents($data['content_id'], $data['id']);
            
            if (isset($item['id'])) {
                $saveData['id'] = $item['id'];
            } else {
                $saveData['id'] = null;
            }
            
            
            if ($saveData['id']) {
                $this->update($saveData, $this->_db->quoteInto('id = ?', $saveData['id']));
            } else {
                $saveData['id'] = $this->insert($saveData);
            }

            //pred ulozenim odstranime tagy a specializace udalosti
            $this->deleteEventTags($saveData['id']);
            $this->deleteEventSpecializations($saveData['id']);

            //ulozime tagy a priradime k eventu
            if (isset($data['tags'])) {
                foreach ($data['tags'] as $tag) {
                    $this->saveTag($tag);
                    $this->saveEventTag(array('calendar_events_id' => $saveData['id'], 'calendar_tags_id' => $tag['id']));
                }
            }

            //ulozime specializace a priradime k eventu
            if (isset($data['specializations'])) {
                foreach ($data['specializations'] as $spec) {
                    $this->saveSpecialization($spec);
                    $this->saveEventSpecialization(array('calendar_events_id' => $saveData['id'], 'calendar_specializations_id' => $spec['id']));
                }
            }
            
        } catch (Zend_Db_Exception $ex) {
            Phc_ErrorLog::error('Calendar:save', $ex->getMessage());
            return false;
        }

        return true;
    }

    /**
     * Ulozi tagy
     * @param array $data data k ulozeni
     * @return bool info o ulozeni
     */
    public function saveTag($data) {

        if (!isset($data['id'])) {
            return false;
        }
        
        unset($data['order']);
        
        $items = $this->_db->fetchAll($this->_db->select()->from('calendar_tags')->where('id = ?',$data['id']));

        try {
            if (count($items) > 0) {
                if ($this->_db->update('calendar_tags', $data, $this->_db->quoteInto('id = ?', $data['id']))) {
                    return true;
                }
            } else {
                if($this->_db->insert('calendar_tags',$data)) {
                    return true;
                }
            }
        } catch (Zend_Db_Exception $ex) {
            Phc_ErrorLog::error('Calendar:saveTag', $ex->getMessage());
            return false;
        }

        return false;
    }
    
    
    /**
     * Ulozi specializace
     * @param array $data data k ulozeni
     * @return bool ulozeno
     */
    public function saveSpecialization($data) {

        if (!isset($data['id'])) {
            return false;
        }
        
        unset($data['type']);
        
        $items = $this->_db->fetchAll($this->_db->select()->from('calendar_specializations')->where('id = ?',$data['id']));

        try {
            if (count($items) > 0) {
                if ($this->_db->update('calendar_specializations', $data, $this->_db->quoteInto('id = ?', $data['id']))) {
                    return true;
                }
            } else {
                if($this->_db->insert('calendar_specializations',$data)) {
                    return true;
                }
            }
        } catch (Zend_Db_Exception $ex) {
            Phc_ErrorLog::error('Calendar:saveSpecialization', $ex->getMessage());
            return false;
        }

        return false;
    }
    
    
     /**
     * Ulozi tag udalosti
     * @param array $data data k ulozeni
     * @return bool ulozeno
     */
    public function saveEventTag($data) {

        try {
            if ($this->_db->insert('calendar_events_tags', $data)) {
                return true;
            }
        } catch (Zend_Db_Exception $ex) {
            Phc_ErrorLog::error('Calendar:saveEventTag', $ex->getMessage());
            return false;
        }

        return false;
    }
    
     /**
     * Ulozi specilizaci udalosti
     * @param array $data data k ulozeni
     * @return bool ulozeno
     */
    public function saveEventSpecialization($data) {

        try {
            if ($this->_db->insert('calendar_events_specializations', $data)) {
                return true;
            }
        } catch (Zend_Db_Exception $ex) {
            Phc_ErrorLog::error('Calendar:saveEventSpecialization', $ex->getMessage());
            return false;
        }

        return false;
    }

    /**
     * Odstrani tagy udalosti
     * @param int $eventId ID udalosti
     * @return bool provedeno?
     */
    public function deleteEventTags($eventId) {

        if (!isset($eventId)) {
            return false;
        }
        
        if ($this->_db->delete('calendar_events_tags', $this->_db->quoteInto('calendar_events_id = ?', $eventId))) {
            return true;
        } else {
            return false;
        }

    }
    
    
     /**
     * Odstrani specializace udalosti
     * @param int $eventId ID udalosti
     * @return bool provedeno?
     */
    public function deleteEventSpecializations($eventId) {

        if (!isset($eventId)) {
            return false;
        }
        
        if ($this->_db->delete('calendar_events_specializations', $this->_db->quoteInto('calendar_events_id = ?', $eventId))) {
            return true;
        } else {
            return false;
        }

    }
    
    /**
     * Vrati seznam tagu udalosti
     * 
     * @return array
     */
    public static function getTags($assoc = false) {
        
        $db = Zend_Registry::get('db');
        
        if($assoc) {
            $select = $db->select()->from('calendar_tags',array('id','name'))->order('name');
            $result = $db->fetchPairs($select);
        } else {
            $select = $db->select()->from('calendar_tags',array('name'))->order('name');
            $result = $db->fetchCol($select);
        }
        
        return $result;
    }
    
    
    /**
     * Vrati seznam specializaci udalosti
     * @return array
     */
    public static function getSpecializations() {
        
        $db = Zend_Registry::get('db');
        
        $select = $db->select()->from('calendar_specializations',array('name'))->order('name');
        
        $result = $db->fetchCol($select);
        
        return $result;
    }
    
    
     /**
     * Vrati poradove cislo stranky nejblizsi udalosti 
     * @param int $contentId id contentu
     * @param array vraci pouze udalosti s tagy
     * @return int
     */
    public static function getCurrentEventPage($contentId, $tags = array()) {

       /* @var $db Zend_Db_Adapter_Abstract */
       $db = Zend_Registry::get('db');
       $config = Zend_Registry::get('config');

       $subselect = $db->select()->from(array('ce'=>'calendar_events'),
               array('ce.id','ce.date_begin','row_number'=>new Zend_Db_Expr('row_number() OVER (ORDER BY date_begin)')))
               ->where('content_id = ?',$contentId)
               ->group(array('ce.id','ce.date_begin'));

       //filtrování podle tagů
       if ($tags) {
            $subselect->join(array('cet'=>'calendar_events_tags'),'ce.id = cet.calendar_events_id',null)
            ->where('cet.calendar_tags_id IN (?)',$tags); 
       }
        $select = $db->select()->from(array('ce2'=>$subselect),array('row_number'))->where('date_begin>=?',Zend_Date::now()->setTime(0)->getIso())->order('date_begin')->limit(1);
        
        $pos = $db->fetchOne($select);
        
        $itemCount = $config->calendar->paging->perpage;
        
        return ceil($pos/$itemCount);

    }
    
    
}
