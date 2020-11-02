<?php
/**
 * Trida obsahujici metody pouzivane pro praci s linky - tabulka links
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Links extends Zend_Db_Table_Abstract {

    protected $_name = "links";
    protected $_primary = "id";
    protected $_db;

    public function __construct() {
        $this->_db = Zend_Registry::get('db');
    }

    public function getId($bulletin_id) {
        $where = $this->getAdapter()->quoteInto('bulletin_id =?', $bulletin_id);
        $row = $this->fetchRow($where);
        return $row["id"];
    }

    public function createLinks($data) {
        try {
            if (!$this->getId($data['bulletin_id'])) {
               return $this->insert($data);
            }
        } catch (Zend_Db_Statement_Exception $e) {
            throw new Zend_Exception(
            "Nepodařilo se uložit link" . $e);
        }
    }

    public function updateLinks($data) {

        $id = $this->getId($data['bulletin_id']);
        try {
            //jestlize link neexistuje vytvorime ho
            if (!$id) {
               return $this->insert($data);
            } else {
                $where = $this->getAdapter()->quoteInto('id = ?', $id);
               return $this->update($data, $where);
            }
        } catch (Zend_Db_Statement_Exception $e) {
            throw new Zend_Exception(
            "Nepodařilo se uložit link" . $e);
        }
    }
    /**
     * Vrati pole setridenych linku dle druhu
     * @return Array | Links
     */
    public function getSortedLinks() {

        $links = array();

        //bulletin pages links
        $select = $this->select()->setIntegrityCheck(false)->from($this->_name)
                ->joinLeft(array('bp'=>'bulletins_pages'),'links.page_id = bp.page_id',array('bulletin'=>'bp.bulletin_id'))
                ->joinLeft(array('b' => 'bulletins'),'links.bulletin_id = b.id',null)
                ->where('b.deleted IS NULL')
                ->where('links.deleted IS NULL')
                ->order('links.name');

        $rows = $this->getAdapter()->fetchAll($select);
        $pages = array();

        //projdeme data z databaze a vytvorime se pomocne pole pages pro bulletinu
        foreach($rows as $row) {
            $pages['page_bul'][$row['bulletin']][] = array('link_id'=>$row['id'],'name'=>$row['name']);
        }

        //category pages links
        $csql = 'SELECT * FROM (SELECT DISTINCT ON("links"."id","links"."page_id","links"."category_id")
          "links"."id","links"."page_id","links"."name","pc"."category_id" AS "category","b"."valid_from" FROM "links"
          JOIN "pages_categories" AS "pc" ON "links"."page_id" = "pc"."page_id"
          LEFT JOIN "bulletins_pages" AS "bp" ON "links"."page_id" = "bp"."page_id"
          LEFT JOIN "bulletins" AS "b" ON "bp"."bulletin_id" = "b"."id"
          LEFT JOIN "categories" AS "c" ON "pc"."category_id" = "c"."id"
          WHERE "links"."page_id" IS NOT NULL AND "b"."deleted" IS NULL AND "c"."deleted" IS NULL AND "links"."deleted" IS NULL) "pages_cat"
          ORDER BY "pages_cat"."valid_from" DESC NULLS LAST, "pages_cat"."name"';

        $rows = $this->getAdapter()->fetchAll($csql);

        //projdeme data z databaze a vytvorime se pomocne pole pages pro kategorie
        foreach($rows as $row) {
            $pages['page_cat'][$row['category']][] = array('link_id'=>$row['id'],'name'=>$row['name']);
         }

        $select = $this->select()->setIntegrityCheck(false)->from($this->_name,'links.*')
        ->joinLeft(array('b' => 'bulletins'), 'links.bulletin_id = b.id', null)
        ->joinLeft(array('c' => 'categories'), 'links.category_id = c.id', array('category' => 'c.name'))
        ->joinLeft(array('r' => 'resources'), 'links.resource_id = r.id', null)
        ->joinLeft(array('cnt'=>'content'),"r.content_id = cnt.id",array('content'=>'cnt.name'))
        ->where('links.deleted IS NULL')
        ->where('b.deleted IS NULL')
        ->where('c.deleted IS NULL')
        ->where('r.deleted IS NULL')
        ->order(array('b.valid_from DESC','c.order','r.id DESC','links.name'));

        $rows = $this->getAdapter()->fetchAll($select);

        //projdeme data a roztridime linky dle druhu
        foreach ($rows as $row) {
            if ($row['resource_id'] !== null) {
                if ($row['content']) {
                    $tooltip = $row['content'];
                } else {
                    $tooltip = "";
                }
                $links['resources'][] = array('link_id'=>$row['id'],'name'=>$row['name'],'tooltip' => $tooltip);
            } elseif ($row['foreign_url'] !== null) {
                $links['external'][] = array('link_id'=>$row['id'],'name'=>$row['name'],'tooltip'=> $row['foreign_url']);
            } elseif($row['bulletin_id'] !== null) {
                //jestlize ma aktualni bulletin nejake pages vlozime je do pole z pomocneho pole
                if (isset($pages['page_bul'][$row['bulletin_id']])) {
                    $links['issues'][] = array('link_id'=>$row['id'],'name'=>$row['name'],'pages' => $pages['page_bul'][$row['bulletin_id']]);
                } else {
                    $links['issues'][] = array('link_id'=>$row['id'],'name'=>$row['name']);
                }
           } elseif($row['category_id'] !== null) {
               //jestlize ma aktualni kategorie nejake pages vlozime je do pole z pomocneho pole
               if (isset($pages['page_cat'][$row['category_id']])) {
                    $links['categories'][] = array('link_id'=>$row['id'],'name'=>$row['category'],'pages' => $pages['page_cat'][$row['category_id']]);
                } else {
                    $links['categories'][] = array('link_id'=>$row['id'],'name'=>$row['category']);
                }
            }
        }

        return $links;

    }

    /**
     * vraci seznam linku rozdelenych do kategorii
     * @return array
     */
    public function getLinks()
    {
        $q = 'SELECT l.id, l.name FROM links l WHERE page_id IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $page_links = $this->getAdapter()->fetchPairs($q);

        $q = 'SELECT l.id, l.name FROM links l WHERE category_id IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $category_links = $this->getAdapter()->fetchPairs($q);

        $q = 'SELECT l.id, l.name FROM links l WHERE bulletin_id IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $bulletin_links = $this->getAdapter()->fetchPairs($q);

        $q = 'SELECT l.id, l.name FROM links l WHERE foreign_url IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $external_links = $this->getAdapter()->fetchPairs($q);

        $q = 'SELECT l.id, l.name FROM links l WHERE resource_id IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $resources = $this->getAdapter()->fetchPairs($q);

        return array(
            'pages' => $page_links,
            'categories' => $category_links,
            'issues' => $bulletin_links,
            'external links' => $external_links,
            'resources' => $resources
        );
    }

}

?>
