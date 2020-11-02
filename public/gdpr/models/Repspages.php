<?php
/**
 * Trida obsluhující vazebni tabulku reps_pages
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Repspages extends Zend_Db_Table_Abstract {
    
    protected $_name = "reps_pages";
    protected $_db;
    
    public function __construct() {
        $this->_db = Zend_Registry::get('db');
    }
    
    public function addReps($reps,$page) {
        foreach($reps as $r) {
            $this->insert(array('page_id' => $page,'repre_id' => $r ));
        }        
    }
    
     public function addPages($pages,$rep) {
        foreach($pages as $p) {
            $this->insert(array('page_id' => $p,'repre_id' => $rep));
        }        
    }
    
    
    public function deletePage($page) {
       $where = $this->getAdapter()->quoteInto('page_id = ?', $page);
       $this->delete($where); 
    }
    
    public function deleteRep($rep) {
       $where = $this->getAdapter()->quoteInto('repre_id = ?', $rep);
       $this->delete($where); 
    }
    
    /**
     * Vrati seznam page ID repa
     * @param int $rep ID repa
     * @return array
     */
    public function getPages($rep) {
        $where = $this->getAdapter()->quoteInto('repre_id = ?', $rep);
        $rows = $this->fetchAll($where);
        $pages = array();
        foreach ($rows as $row) {
            $pages[] = $row['page_id'];
        }
        return $pages;
    }

    /**
     * Vrati seznam repu s pristupem na stranku
     * @param int $page ID stranky
     * @return array
     */
    public function getReps($page) {
         $select = $this->select()->setIntegrityCheck(false)->from(array('rp' => $this->_name))
                        ->joinLeft(array('u' => 'users'), 'rp.repre_id = u.id', null)
                        ->where('u.deleted IS NULL')->where('rp.page_id = ?', $page);

        $rows = $this->getAdapter()->fetchAll($select);
        $reps  = array();
        foreach ($rows as $row) {
           $reps[] = $row['repre_id'];  
        }
        return $reps;
    }
    
        
    /**
     * Vrati seznam reprezentantu, kteri maji prirazenu stranku
     * @param int $page_id ID stranky
     * @return array pole repu
     */
    public function getPageReps($page_id) {
        $select = $this->select()->setIntegrityCheck(false)->from(array('rp' => $this->_name), null)
                        ->joinLeft(array('p' => 'pages'), 'rp.page_id = p.id', null)
                        ->joinLeft(array('u' => 'users'), 'rp.repre_id = u.id', array('name' => 'u.name', 'surname' => 'u.surname', 'id' => 'u.id'))
                        ->where('u.deleted IS NULL')->where('p.deleted IS NULL')->where('u.is_rep IS true')->where('rp.page_id = ?', $page_id)
                        ->order('u.id DESC');

        $rows = $this->getAdapter()->fetchAll($select);

        //neni-li prirazen rep, pristup maji vsichni
        if (!$rows) {
            $f_select = $this->select()->setIntegrityCheck(false)->from('users',array('name','surname','id'))->where('deleted IS NULL')->where('is_rep IS TRUE')->order('id DESC');
            $rows = $this->getAdapter()->fetchAll($f_select);
        }

        return $rows;
    }

}
