<?php

/**
 * iBulletin - ShowContent.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

class Ibulletin_ShowContent_Not_Found_Exception extends Exception {}

/**
 * Trida zprostredkovavajici zobrazeni hlavniho obsahu na strankach iBulletinu -
 * clanky, dotazniky, kalendare, atd.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_ShowContent
{
    /**
     * Instance tridy.
     *
     * @var Ibulletin_ShowContent
     */
    private static $_inst = null;

    /**
     * Vrati existujici instanci Ibulletin_ShowContent nebo vytvori novou a tu vrati.
     *
     * @return Ibulletin_ShowContent Jedina instance Ibulletin_ShowContent
     */
    public static function getInstance()
    {
        if(self::$_inst === null){
            self::$_inst = new Ibulletin_ShowContent();
        }

        return self::$_inst;
    }


    /**
     * Pripravi do view data pro vypsani menu
     *
     * @param int ID bulletinu z tabulky bulletins
     * @return mixed Data pro vypsani menu
     */
    public function renderMenu($bulletin_id = null)
    {
        Zend_Loader::loadClass('Ibulletin_Menu');

        $config = Zend_Registry::get('config');

        $menu = new Ibulletin_Menu($bulletin_id);
        $menu->loadItems();

        return $menu->getRenderData();
    }


    /**
     * Vyhleda a vrati data pro vypsani pozadovane page. K nacteni serializovanych
     * objektu v tabulce content
     * je pouzit Zend_Loader, tedy k nalezeni souboru se tridou staci nazev tridy podle konvenci
     * Zendu.
     *
     * @param mixed URL jmeno stranky z tabulky pages (string)
     *              cely radek z pages renderovane stranky (array)
     * @param int ID bulletinu
     * @return array [page_id, tpl_file, name, url_name, layout_name
     *               contents[klic position, data Ibulletin_ContentAbstract
                                array of content objects]]
     *               pripadne vraci false, pokud pro zadane url_name neexistuje
     *               zaznam v tabulce pages
     * @throws Ibulletin_ShowContent_Not_Found_Exception
     */
    public static function fetchPageData($page_data, $bulletin_id)
    {
        $db = Zend_Registry::get('db');

        // Pokud bylo zadano pole, nemusime stranku hledat dle url
        if(is_array($page_data)){
            $page_row = $page_data;
        }
        else{
            if(is_int($page_data)){
                $where = ' bp.page_id = '.$page_data.' ';
            }
            else{
                $url_name = $page_data;
                // Mirne normalizujeme
                $url_name = trim(strtolower($url_name));
                $url_name_quot = $db->quote($url_name);
                $where = " lower(bp.url_name) = lower($url_name_quot) ";
            }
            // Najdeme data dane page podle url_name
            $q = "SELECT p.id, p.tpl_file, p.layout_name, p.name, bp.url_name, bp.bulletin_id AS bulletin_id,
                         b.layout_name AS bulletin_layout_name,
                         c.name AS category, c.url_name AS category_url_name, c.layout_name AS category_layout_name
                  FROM pages p
                    JOIN bulletins_pages bp ON bp.page_id = p.id
                    JOIN bulletins b ON b.id = bp.bulletin_id
                    LEFT JOIN (SELECT c.*, pc.page_id FROM pages_categories AS pc LEFT JOIN categories AS c ON pc.category_id = c.id WHERE c.type != 'h' ORDER BY c.order) as c ON c.page_id = p.id
                  WHERE $where
                        AND bp.bulletin_id = $bulletin_id ORDER BY bp.id DESC LIMIT 1";
            $rows = $db->fetchAssoc($q);
            
            // Pokud se nenasel zaznam, vratime false, pozadovany article neexistuje
            if(empty($rows)){
                return false;
            }

            reset($rows);
            $row = current($rows);

            $page_row = $row;
        }

        // Vyhledame jednotlive contenty
        // TODO - neslo by to udelat najednou s vyhledanim stranek?
        $q = sprintf('SELECT c.id, serialized_object, class_name, position, a.name AS author,
                      c.created
                      FROM
                        content c
                        JOIN content_pages cp ON cp.content_id = c.id
                        LEFT OUTER JOIN authors a ON a.id = c.author_id
                      WHERE page_id = %d
                      ORDER BY position ASC', $page_row['id']);
        $contents = $db->fetchAssoc($q);

        // Pro kazdy content natahneme class do ktere spada a deserializujeme jeho objekt
        Zend_Loader::loadClass('Ibulletin_Content_Abstract');
        $objects = array();
        foreach($contents as $content){
            // Preskocime nepouzitelne zaznamy
            if(empty($content['class_name']) || empty($content['serialized_object'])){
                continue;
            }
            // Nacteme tridu pro dany objekt
            Zend_Loader::loadClass($content['class_name']);

            $object = unserialize(stripslashes($content['serialized_object']));

            // Nastavime authora a created podle DB do objektu
            $object->created = $content['created'];
            $object->setAuthor($content['author']);

            $objects[$content['position']] = $object;
        }

        // Vratime vysledek
        $output = array();
        $output['page_id'] = $page_row['id'];
        $output['bulletin_id'] = $page_row['bulletin_id'];
        $output['tpl_file'] = $page_row['tpl_file'];
        $output['layout_name'] = $page_row['layout_name'];
        $output['category_layout_name'] = $page_row['category_layout_name'];
        $output['bulletin_layout_name'] = $page_row['bulletin_layout_name'];
        $output['name'] = $page_row['name'];
        $output['url_name'] = $page_row['url_name'];
        $output['contents'] = $objects;
		$output['category'] = isset($page_row['category']) ? $page_row['category'] : null;
        $output['category_url_name'] = isset($page_row['category_url_name']) ? $page_row['category_url_name'] : null;

        return $output;
    }

    /**
     * Najde a vrati zaznam prvniho clanku v bulletinu.
     *
     * @param int ID bulletinu pro ktery hledame prvni clanek
     * @return array Radek z rabulky pages pro prvni clanek zadaneho bulletinu
     * @throws Ibulletin_ShowContent_Not_Found_Exception
     */
    public function getFirstBulletinsPageRow($bulletin_id)
    {
        $db = Zend_Registry::get('db');
        $q = sprintf('SELECT p.id, p.tpl_file, p.name, p.layout_name,  
                        bp.url_name, bp.bulletin_id, 
                        b.layout_name AS bulletin_layout_name,
                        c.name AS category, c.url_name AS category_url_name, c.layout_name AS category_layout_name
                      FROM pages p
                      JOIN bulletins_pages bp ON bp.page_id = p.id
                      JOIN bulletins b ON b.id = bp.bulletin_id
                      LEFT OUTER JOIN pages_categories pc ON pc.page_id = p.id
                      LEFT OUTER JOIN categories c ON pc.category_id = c.id
                      WHERE
                        bp.bulletin_id = %1$d
                        -- AND p.id = (
                        -- SELECT page_id FROM bulletins_pages
                        -- WHERE bulletin_id = %1$d  ORDER BY "order" ASC LIMIT 1)
                        -- ORDER BY bp.id DESC LIMIT 1
                      ORDER BY bp.order ASC LIMIT 1', $bulletin_id);
        $rows = $db->fetchAll($q);

        // Pokud se nenasel zaznam, vyhodime vyjimku
        if(empty($rows)){
            throw new Ibulletin_ShowContent_Not_Found_Exception("No article found for bulletin id = '$bulletin_id'");
        }

        reset($rows);
        return current($rows);
    }

    /**
     * Najde a vrati ID aktualniho bulletinu.
     *
     * !! DEPRECATED !! - nyni umisteno v model/Bulletins.php
     * @param bool Nastavit nalezeny bulletin jako aktualne prohlizeny?
     * @return int ID aktualniho bulletinu
     */
    public function getActualBulletinId($setAsCurrent = false)
    {
        $bulletins = new Bulletins();
        $row = $bulletins->getActualBulletinRow();
        return $row['id'];
    }

	/**
	 * Vrati nazev bulletinu.
	 *
	 * DEPRECATED - presunuto do Bulletins
	 *
	 *	@param int ID bulletinu.
	 */
	public function getBulletinName($id = null){
		$bulletins = new Bulletins();
        return $bulletins->getBulletinName();
	}
}
