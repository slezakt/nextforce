<?php

/**
 * iBulletin - Menu.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

class Ibulletin_Menu_Link_Not_Found_Exception extends Exception {}
 
 
/**
 * Trida poskytujici funkcionality tykajici se menu 
 * ulozeneho v tabulce menu_items.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Menu
{

    /**
     * Pole obsahujici polozky staleho menu.
     *
     * @var array
     */
    public $menu_items = array();
    
    /**
     * Pole obsahujici polozky menu konkretniho bulletinu.
     *
     * @var array
     */
    public $bulletin_menu_items = array();
    
    /**
     * Pole obsahujici odkazy na jednotlive kategorie.
     *
     * @var array
     */
    public $categories = array();
    
    /**
     * Cislo bulletinu z tabulky bulletins
     *
     * @var int
     */
    public $bulletin_id = null;
    
    /**
     * Pripojeni k DB.
     *
     * @var Zend_Db
     */
    public $db = null;
    
    /**
     * Url Helper slouzici k tvorbe url pomoci prednastavenych cest.
     *
     * @var Zend_View_Helper_Url
     */
    private $_urlHlpr = null;
    
    
    
    /**
     * Kontruktor
     *
     * @param pripojeni k DB
     * @param int id aktualniho bulletinu.
     */
    public function __construct($bulletin_id = null)
    {
        //naplnime atributy
        $this->db = Zend_Registry::get('db');
        $this->bulletin_id = $bulletin_id;
        
        //nacteme si URL helper, abychom mohli snadno delat URL
        Zend_Loader::loadClass('Zend_View_Helper_Url');
        $this->_urlHlpr = new Zend_View_Helper_Url();
    }
    
    /**
     * Nacte do pole $this->menu_items vsechny polozky staleho menu a do
     * pole $this->bulletin_menu_items polozky menu konkretniho cisla bulletinu,
     * pokud bylo v konstruktoru zadano.
     *
     * @return array Pole obsahujici dve pole - pole s radky staleho menu (index menu)
     * a menu bulletinu (index bulletin_menu).
     */
    public function loadItems()
    {
        $db = $this->db;
        // Ziskame stale menu
        $q = 'SELECT l.name, b.url_name AS bulletin_url_name, l.foreign_url, special, m.disabled, m.link_id, l.page_id, c.url_name as category_url_name, l.resource_id
              FROM menu_items m
              LEFT JOIN links l ON m.link_id = l.id
              LEFT JOIN bulletins b ON l.bulletin_id = b.id
              LEFT JOIN categories c ON l.category_id = c.id
              WHERE m.bulletin_id IS NULL AND NOT (m.disabled = true AND m.visible = false)
              ORDER BY m.order ASC, l.name';
              
        $r = $db->fetchAll($q);
        
        // Vytvorime podminku podle toho, jestli je prave povoleno zobrazovani neaktivniho
        // obsahu, nebo ne
        Zend_Loader::loadClass('Zend_Auth');
        $session = Zend_Registry::get('session');
        $auth = Zend_Auth::getInstance();
        if(!empty($session->allowed_inactive_content) || $auth->hasIdentity()){
            $show_inactive_where = '1 = 1';
        }
        else{
            $show_inactive_where = 'valid_from < current_timestamp';
        }
        
        // Dohledame url_name a bulletin_url_name pro zaznamy, kde jeste neni
        foreach($r as $key => $row){
            $q = sprintf('SELECT bp.url_name AS url_name, b.url_name AS bulletin_url_name
                          FROM bulletins_pages bp, bulletins b
                          WHERE b.id = bp.bulletin_id AND page_id = %d 
                                AND '.$show_inactive_where.' 
                          ORDER BY valid_from DESC LIMIT 1
                          ', $row['page_id']);
            $url_names = $db->fetchAll($q);
            if(!empty($url_names)){
                $r[$key]['url_name'] = $url_names[0]['url_name'];
                if(empty($r[$key]['bulletin_url_name'])){
                    $r[$key]['bulletin_url_name'] = $url_names[0]['bulletin_url_name'];
                }
            }
        }
        
        if(!empty($r))
        {
            $this->menu_items = $r;
        }
        
        # Pokud je treba, ziskame menu konkretniho bulletinu
        $q = "SELECT 1 FROM menu_items WHERE special = 'bulletin'";
        $result = $db->fetchOne($q);
        if($result && $this->bulletin_id !== null){
            $q = sprintf(
                 'SELECT l.name, bp.url_name, b.url_name AS bulletin_url_name, l.foreign_url, special, m.disabled, m.link_id
                  FROM 
                      ((menu_items m LEFT JOIN links l ON m.link_id = l.id)
                      LEFT JOIN bulletins b ON m.bulletin_id = b.id) 
                      LEFT JOIN bulletins_pages bp ON bp.page_id = l.page_id
                  WHERE m.bulletin_id = %d AND bp.bulletin_id = %d
                        AND NOT (l.page_id IS NULL AND l.foreign_url IS NULL AND m.disabled = false)
                        AND NOT (m.disabled = true AND m.visible = false)
                  ORDER BY m.order ASC, l.name', $this->bulletin_id, $this->bulletin_id);
            $r = $db->fetchAll($q);
            
            if(!empty($r))
            {
                $this->bulletin_menu_items = $r;
            }
        }
        
        # Pokud je treba, ziskame seznam kategorii
        $q = "SELECT 1 FROM menu_items WHERE special IN ('category_application', 'category') AND visible IS TRUE";
        $result = $db->fetchOne($q);
        if($result){
            //$q = 'SELECT name, url_name AS category_url_name FROM categories ORDER BY "order"';
            //$r = $db->fetchAll($q);
            
            $categories = Categories::getList(false, array('name', 'category_url_name' => 'url_name', 'type', 'order', 'annotation', 'goto_article'), 
                null, false, $this->bulletin_id);
            
            if(!empty($categories))
            {
                $this->categories = $categories;
            }
        }
    }
    
    /**
     * Vytvori co nejpresnejsi URL pro zadany link.
     *
     * TODO - Asi nejak zrevidovat kdy se urci prislusnost page ke kteremu bulletinu.
     * TODO - Metoda by mela patrit do models/Links.php
     *
     * @param int Id linku
     * @param bool Vratit parametry pro vytvoreni url (true), nebo vratit primo url (false)?
     * @param bool Nastavit v pripade nevalidniho bulletinu do session flag 
     *             povolujici zobrazeni neaktivnich clanku a bulletinu?
     * @param string url name bulletinu
     * @param int Id bulletinu
     * @return array parametry pro route a jmeno route v poli s klici [params, route],
     *               nebo primo url podle nastaveni $getParams
     * @throws Ibulletin_Menu_Link_Not_Found_Exception
     */
    public function getLinkUrl($link_id, $getParams = true, $setAllowForbidden = false, $bul_url_name = null, $bulletin_id = null)
    {
        $db = $this->db;

        // overeni existence nesmazaneho linku
        $q = "SELECT id AS link_id, * FROM links WHERE id = $link_id AND deleted IS NULL";
        $link_row = $db->fetchAll($q);

        // v pripade neexistence vraci 404
        if (empty($link_row)) {
            if ($getParams) {
                return array('params' => array('id' => '404'), 'route' => 'error');
            } else {
                return $this->_urlHlpr->url(array('id'=>'404'), 'error', true);
            }
        }

        // Je treba vedet, jestli se jedna o link na page, nebo jiny
        // - podle toho se pouziva bulletin_id
        $link_row = $link_row[0];
        $is_page_link = $link_row['page_id'] !== null ? true : false;
        
        // Pokud se jedna o externi odkaz, jen nechame vytvorit primitivni url
        if(!empty($link_row['foreign_url'])){
            return $this->_createUrlForItem($link_row, $getParams);
        }
        
        // Pro resource jen sestavime URL na RedirectController
        if(!empty($link_row['resource_id'])){
            return $this->_createUrlForItem(array('foreign_url' => true, 'link_id' => $link_id), $getParams);
            //return $this->_urlHlpr->url(array('target' => $link_id), 'redirect', true);
        }
        

        $where = array();
        if(is_numeric($bulletin_id) && $is_page_link){
            $where[] = "b.id = $bulletin_id";
        }
        elseif(!empty($bul_url_name) && $is_page_link){
            $where[] = "b.url_name = '$bul_url_name'";
        }
        
        if(!empty($where)){
            $where_sql = join(' AND ', $where).' AND';
        }
        else{
            $where_sql = '';
        }
        
        $q = sprintf(
             'SELECT l.name, bp.url_name, b.url_name AS bulletin_url_name, l.foreign_url, 
                  l.id, l.page_id, b0.url_name AS bulletin_only_url_name,
                  cat.url_name AS category_url_name, 
                  (b.valid_from < current_timestamp) AS valid_article,
                  (b0.valid_from < current_timestamp) AS valid_bulletin
              FROM
                  links l 
                  LEFT JOIN bulletins_pages bp ON l.page_id=bp.page_id
                  LEFT JOIN bulletins b ON bp.bulletin_id = b.id
                  LEFT JOIN categories cat ON l.category_id = cat.id
                  LEFT JOIN bulletins b0 ON l.bulletin_id = b0.id
              WHERE %s NOT (l.bulletin_id IS NULL AND l.category_id IS NULL 
                         AND l.page_id IS NULL AND l.foreign_url IS NULL)
                    AND l.id = %d AND l.deleted IS NULL
              ORDER BY b.id DESC LIMIT 1;', $where_sql, $link_id);
        $rows = $db->fetchAll($q);
        
        if(empty($rows)){
            throw new Ibulletin_Menu_Link_Not_Found_Exception("Odkaz pro link_id=$link_id nebyl nalezen.");
        }
        
        $row = $rows[0];
        
        // Nedohledavame url jmeno bulletinu a page pokud jsou znama, nebo je
        // zname url jmeno kategorie ci bulletinu v linku na ne
        if((empty($row['url_name']) || empty($row['bulletin_url_name'])) 
            && empty($row['bulletin_only_url_name']) && empty($row['category_url_name'])){
            // Dohledame url_name a bulletin_url_name pokud neni zname
            $q = sprintf('SELECT bp.url_name AS url_name, b.url_name AS bulletin_url_name, (valid_from < current_timestamp) AS valid
                          FROM bulletins_pages bp, bulletins b
                          WHERE b.id = bp.bulletin_id AND page_id = %d 
                          ORDER BY valid_from DESC
                          ', $row['page_id']);
            $url_names = $db->fetchAll($q);
            
            // Nastavime bulletin url name co nejnovejsi validni, 
            // v pripade neexistence validniho bulletinu pouzijeme nejnovejsi nevalidni
            $url_name_set = false;
            $url_name_valid = true;
            foreach($url_names as $url_name){
                if($url_name_set && !$url_name['valid']){
                    continue;
                }
                $row['url_name'] = $url_name['url_name'];
                $row['bulletin_url_name'] = $url_name['bulletin_url_name'];
                $url_name_set = true;
                if($url_name['valid']){
                    $url_name_valid = true;
                    break;
                }
                else{
                    $url_name_valid = false;
                }
            }
        }
        
        //echo 'bid:'.$bulletin_id.', '.($row['valid_article'] === null? 'null':'notnull').' '.($row['valid_article']?'T':'F')." ".($setAllowForbidden?'T':'F')."<br/>";
        //print_r($row);
        
        // Pokud se jedna o kategorii a potrebujeme vedet, jestli je odkaz validni, musime
        // najit, validitu zadaneho bulletin_id
        $cat_valid = null;
        if($setAllowForbidden && $row['valid_bulletin'] === null 
           && $row['valid_article'] === null && is_numeric($bulletin_id)){
        
            $q = "SELECT (b.valid_from < current_timestamp) AS valid 
                  FROM bulletins b
                  WHERE id = $bulletin_id";
            $cat_valid = $db->fetchOne($q);
        }
        
        if(($row['valid_bulletin'] === false || $row['valid_article'] === false 
            || $cat_valid === false || (isset($url_name_valid) && !$url_name_valid)) 
            && $setAllowForbidden)
        {
            $session = Zend_Registry::get('session');
            $session->allowed_inactive_content = true;
        }

        // Pro category dohledame bulletin_url_name, pokud
        // je k dispozici bulletin_id
        if(is_numeric($bulletin_id) && !empty($row['category_url_name']) && !$is_page_link){
            Zend_Loader::loadClass('Bulletins');
            $bulletins = new Bulletins();
            $row['bulletin_url_name'] = $bulletins->findBulletinUrlName($bulletin_id);
        }
        elseif(!empty($bul_url_name) && !empty($row['category_url_name']) && !$is_page_link){
            // Jen priradime bulletin url name
            $row['bulletin_url_name'] = $bul_url_name;
        }
        
        return $this->_createUrlForItem($row, $getParams);
    }
    
    
    /**
     * Vrati pro dany zaznam menu jeho URL podle parametruu,
     * vybere, je-li odkaz na bulletinu, do clanku v bulletinu, do clanku bez bulletinu,
     * odkaz mimo web.
     *
     * @param array polozka menu
     * @param bool ma se misto stringu vratit pole s paramtery a routem pro danou URL
     * @return string URL pro danou polozku
     */
    private function _createUrlForItem($item, $get_array = false)
    {
        $params = null;
        $route = null;
        
        // Externi odkaz
        if(!empty($item['foreign_url']))
        {
            $params = array('target' => (int)$item['link_id']);
            $route = 'redirect';
        }
        elseif(!empty($item['resource_id']))
        {
            $params = array('target' => (int)$item['link_id']);
            $route = 'redirect';
        }
        // Kategorie s bulletinem
        elseif(!empty($item['category_url_name']) && !empty($item['bulletin_url_name']))
        {
            $params = array('name' => $item['category_url_name'], 'bulletin' => $item['bulletin_url_name']);
            $route = 'categorybulletin';
        }
        // Kategorie
        elseif(!empty($item['category_url_name']))
        {
            $params = array('name' => $item['category_url_name']);
            $route = 'category';
        }
        // Cely bulletin
        elseif(!empty($item['bulletin_url_name']) && empty($item['url_name']))
        {
            $params = array('name' => $item['bulletin_url_name']);
            $route = 'bulletin';
        }
        // Cely bulletin linkem na bulletin
        elseif(!empty($item['bulletin_only_url_name']))
        {
            $params = array('name' => $item['bulletin_only_url_name']);
            $route = 'bulletin';
        }
        //Clanek v bulletinu
        elseif(!empty($item['bulletin_url_name']) && !empty($item['url_name']))
        {
            $params = array('name' => $item['bulletin_url_name'], 
                            'article' => $item['url_name']);
            $route = 'bulletinarticle';
        }
        
        
        // Rozhodneme, jestli vracime string, nebo pole
        if(!$get_array){
            // Url vytvarime jen pokud je zadano pole params i string route
            if($params && $route){
                return $this->_urlHlpr->url($params, $route, true);
            }
            else{
                return null;
            }
        }
        else{
            // Vratime parametry k presmerovani
            return array('params' => $params, 'route' => $route);
        }
    }
    
    
    /**
     * Vrati pole pro ulozeni do view a pozdejsi render. Pole se sklada
     * z prvku menu a bulletin_menu, tyto pak obsahuji pole jednotlivych radku menu.
     */
    public function getRenderData()
    {
        //vytvorime pole poli s klici name a url ke kazde polozce menu
        $menu = array();
        foreach($this->menu_items as $item)
        {
            $menu[] = array('name' => $item['name'], 'url' => $this->_createUrlForItem($item), 'special' => $item['special'], 'disabled' => $item['disabled']);
        }
        $bulletin_menu = array();
        foreach($this->bulletin_menu_items as $item)
        {
            $bulletin_menu[] = array('name' => $item['name'], 'url' => $this->_createUrlForItem($item), 'disabled' => $item['disabled']);
        }
        
        $categories = array();
        // Ziskame id bulletinu, pokud je k dispozici
        if(is_numeric($this->bulletin_id)){
            $db = $this->db;
            $q = "SELECT url_name FROM bulletins WHERE id=".$this->bulletin_id;
            $bul_url_name = $db->fetchOne($q);
            if(empty($bul_url_name)){
                unset($bul_url_name);
            }
        }
        foreach($this->categories as $item)
        {
            $categories[] = array(
                'name' => $item['name'],
                'url_name' => $item['category_url_name'], 
                'url' => isset($bul_url_name) ?
                    $this->_urlHlpr->url(array('name' => $item['category_url_name'], 'bulletin' => $bul_url_name), 'categorybulletin') : 
                    $this->_urlHlpr->url(array('name' => $item['category_url_name']), 'category'), 
                'disabled' => false,
                'type' => $item['type'], 
                'order' => $item['order'], 
                'annotation' => $item['annotation'], 
                'goto_article' => $item['goto_article']
                );
        }
        
        return array('menu' => $menu, 'bulletin_menu' => $bulletin_menu, 'categories_menu' => $categories);
    }
}
