<?php
/**
 * iBulletin - RpcTest.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}


/**
 * Trida pro testovani RPC.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class RpcTest
{
    /**
     * Zjisti datum posledni navstevy uzivatele se zadanym id.
     * Zjistovani je trochu rozvlacnejsi na vice dotazuu.
     * 
     * @param int   ID uzivatele z tabulky users
     * @return string | bool Cas posledni navstevy uzivatele, nebo false pokud uzivatel neexistuje,
     *                       true pokud jeste web nenavstivil.
     */
    public static function lastVisit($id)
    {
        $db = Zend_Registry::get('db');

        $select = new Zend_Db_Select($db);
        $select->from('users')
               ->where('id = :id');
        $userA = $db->fetchAll($select, array('id' => (int)$id));
        if(empty($userA)){
            // uzivatel neexistuje
            return false;
        }
        
        for($i = 0; $i < 100; $i++){
            // Najdeme cas posledni navstevy
            $select = new Zend_Db_Select($db);
            $select->from(array('s' => 'sessions'))
                    ->joinInner(array('pw' => 'page_views'), 's.id = pw.session_id')
                    ->where('s.user_id = :id')
                    ->order('pw.timestamp DESC')
                    ->limit(1);
            $page_viewA = $db->fetchAll($select, array('id' => (int)$id));
        }
        if(empty($page_viewA)){
            // uzivatel existuje, ale jeste web nenavstivil
            return true;
        }
        $page_view = current($page_viewA);
        
        return $page_view['timestamp'];
    }
}