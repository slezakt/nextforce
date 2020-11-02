<?php
/**
 * iBulletin - View/Linkurl.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

/**
 * View helper ktery umoznuje v templatech snadno vytvaret odkazy
 * po iBulletinu pomoci ID linku.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_View_Helper_Linkurl {

    /**
     * @var Zend_View Instance
     */
    public $view;

    /**
     * Vytvori url pro zadane ID linku z tabulky links, pokud link neexistuje
     * vraci # jako zaslepeni cesty
     *
     * @access public
     *
     * @param  int $id ID linku z tabulky links/ string jmeno linku z tabulky links
     * @return string Url pro ID zadaneho linku nebo # pro nenalezeny link.
     */
    public function linkurl($id)
    {
        Zend_Loader::loadClass('Ibulletin_Menu');
        
        $menu = new Ibulletin_Menu();
        $db = Zend_Registry::get('db');
        
        // Pokud je zadan retezec, jedna se o nazev linku, musime najit id
        if(is_string($id)){
            $sel = new Zend_Db_Select($db);
            $sel->from('links', 'id')
                ->where('name = ? AND deleted IS NULL', $id);
            $id = $db->fetchOne($sel);
        }
        
        if (empty($id)) {
            return "#";
        }
        
        try{
            $url = $menu->getLinkUrl($id, false);
        }
        catch(Exception $e){
            return "#";
        }
        
        return $url;

    }

    /**
     * Set the view object
     *
     * @param Zend_View_Interface $view
     * @return void
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
}
