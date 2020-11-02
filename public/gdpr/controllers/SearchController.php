<?php
/**
 * Stara se o vyhledavani a zobrazovani vysledku vyhledavani
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class SearchController extends Zend_Controller_Action
{
    /**
     * Provede vyhledavani a pripravi pro template data vysledkuu
     */
    public function indexAction()
    {
        Zend_Loader::loadClass('Ibulletin_Search');
        Zend_Loader::loadClass('Ibulletin_ShowContent');
        Zend_Loader::loadClass('Zend_Search_Lucene_Search_QueryParser');
        Zend_Loader::loadClass('Bulletins');

        $req = $this->getRequest();
        /**
         * @var Zend_Db_Adapter
         */
        $db = Zend_Registry::get('db');
        /**
         * @var Zend_Locale
         */
        $locale = Zend_Registry::get('locale');
        $config = Zend_Registry::get('config');
        $session = Zend_Registry::get('session');
        $bulletins = new Bulletins();

        // Nastavime poradi ziskavani parametru z requestu
        $req->setParamSources(array('_GET', '_POST', 'userland'));

        $pageNum = $req->getParam('page', 1);

        $url_params = array();

        // Nastavime do stats akci
        Ibulletin_Stats::getInstance()->setAttrib('action', 'search');

        // prepare query
        $q = trim(urldecode($req->getParam('q')));
        $urlGet = '?q='.$q;

        // splnuje query string kriteria pro hledani?
        // strip excess whitespaces and remove accents
        $query_string = strtolower(Utils::translit(trim($q)));
        //$query_string = trim($q);
        
        $arr = explode (' ', $query_string);
        $terms = array();
        foreach ($arr as $k => $term) {
        	//$term = preg_replace('/\W/u','',$term); // all symbols other than a-zA-Z0-9_ are removed
        	if (mb_strlen($term) > 2 && !preg_match('/[0-9\.,]+/', $term)) { // Cannot use * for terms containing only numbersbecause of error in Lucene implementation
        		// adding wildcard to each term, which does not contain special characters
                if (!preg_match('/[\-\&\|\!\(\)\{\}\[\]\^\"\~\*\?\:\\\]+/',$term)) {
                    $terms[] = $term.'*'; 
                } else {
                    $terms[] = $term;
                }
        	}
        }
        // build query for lucene query parser
        $query_string = implode (' ',$terms);        

        $hits=array();
        
        // run search
        if (!empty($query_string)) {
            $index = Ibulletin_Search::getSearchIndex();            
            Zend_Search_Lucene_Search_QueryParser::setDefaultOperator(Zend_Search_Lucene_Search_QueryParser::B_AND);
            Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('UTF-8');
            Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8());
            // measure time elapsed
			$time_1 = getmicrotime();
			// do actual query
			try {
				$query = Zend_Search_Lucene_Search_QueryParser::parse($query_string, 'utf-8');
				//dump($query);exit;
				$hits = $index->find($query);
			} catch (Zend_Search_Lucene_Exception $e) {
				Phc_ErrorLog::notice('search', 'invalid query string : "'.$query_string. '" exception message: '.$e);
			}
			
			$time_2 = getmicrotime();
			$this->view->time = ($time_2 - $time_1);
            // Jmena poli v indexu
            $field_names = $index->getFieldNames();
        }
        
        //nacteme si URL helper, abychom mohli snadno delat URL
        Zend_Loader::loadClass('Zend_View_Helper_Url');
        $urlHlpr = new Zend_View_Helper_Url();

        // Najdeme url pro kazdy nalezeny content
        // Neaktivni, nebo nezarazene contenty odstranime - pokud neni
        // povoleno zobrazovani neaktivnich

        // Vytvorime podminku podle toho, jestli je prave povoleno zobrazovani neaktivniho
        // obsahu, nebo ne
        Zend_Loader::loadClass('Zend_Auth');
        $session = Zend_Registry::get('session');
        $auth = Zend_Auth::getInstance();
        if(!empty($session->allowed_inactive_content) || $auth->hasIdentity()){
            $show_inactive_where = 'b.deleted IS NULL and NOT b.hidden';
        }
        else{
            $show_inactive_where = 'b.valid_from < current_timestamp AND b.deleted IS NULL AND not b.hidden';
        }

        // Poradi a filtrovani
        $order = (int)$req->getParam('order', 1);
        $filter_author = $req->getParam('fauthor', 'default');
        $filter_category = $req->getParam('fcateg', 'default');
        // Pokud je stare filtrovani jine nez nove, musime nastavit page na 1
        if($filter_author != $session->searchLastFilterAuthor || $filter_category != $session->searchLastFilterCategory){
            $pageNum = 1;
        }
        $session->searchLastFilterAuthor = $filter_author;
        $session->searchLastFilterCategory = $filter_category;
        $filter = '';
        if($filter_author != 'default'){
            $filter .= 'AND a.id = '.(int)$filter_author;
        }
        if($filter_category != 'default'){
            $filter .= 'AND cat.id = '.(int)$filter_category;
        }
        // Order query part
        $order_qp = 'id';
        if($order == 2){
            $order_qp = 'created DESC';
        }
        elseif($order == 3){
            $order_qp = 'name';
        }
        $order != 1 ? $url_params['order'] = $order : $url_params['order'] = null;
        $filter_author != 'default' ? $url_params['fauthor'] = $filter_author : $url_params['fauthor'] = null;
        $filter_category != 'default' ? $url_params['fcateg'] = $filter_category : $url_params['fcateg'] = null;

        // Pripravime si IN klauzuli s id vsech nalezenych
        $in = array();
        foreach($hits as $hit){
            $in[] = $hit->content_id;
        }
        $in = '('.join(', ', $in).')';

        // Zjistime celkovy pocet zaznamu ziskanych vyhledavanim
        $sel = "
          SELECT count(distinct(c.id))
              FROM content_pages cp
                   JOIN bulletins_pages bp ON cp.page_id = bp.page_id
                   JOIN pages p ON p.id = cp.page_id
                   JOIN bulletins b ON b.id = bp.bulletin_id
                   JOIN content c ON c.id = cp.content_id
                   LEFT OUTER JOIN authors a ON a.id = c.author_id
                   LEFT OUTER JOIN pages_categories pc ON p.id = pc.page_id
                   LEFT OUTER JOIN categories cat ON pc.category_id = cat.id
              WHERE
                   cp.content_id IN $in AND $show_inactive_where
                   $filter
                   ";
        if(!empty($hits)){
            $hits_count = $db->fetchOne($sel);
        }
        else{
            $hits_count = 0;
        }

        // Strankovani paginatorem
        $urlParams = new stdClass();
        $urlParams->urlParams = $url_params;
        $urlParams->urlGet = $urlGet;
        $this->view->paginatorUrlParams = $urlParams; // Je neco jineho nez obvykle pouzivame jako urlParams napriklad v opinions
        $paginator = new Zend_Paginator(new Zend_Paginator_Adapter_Null($hits_count));
        $paginator->setItemCountPerPage($config->general->paging->itemsperpage);
        $paginator->setCurrentPageNumber($pageNum);
        $limit = (int)$paginator->getItemCountPerPage();
        $offset = (int)$limit * $paginator->getCurrentPageNumber() - (int)$limit;


        // Hlavni select
        // TODO je dulezite uvedomit si, ze kriteriem pro jedinecnost je
        // content, tedy kazdy content odpovidajici vyhledani je nabidnut
        // v nejnovejsim bulletinu a nejvice odpovidajici kategorii
        $dbq = "
            SELECT * FROM (
              SELECT DISTINCT ON (c.id) c.id,
                    b.url_name AS bulletin_url_name, bp.url_name AS page_url_name,
                    cat.name AS category_name, c.created, a.name AS author, a.id AS author_id,
                    cat.id AS category_id, p.name AS name
              FROM content_pages cp,
                   bulletins_pages bp,
                   bulletins b,
                   pages p
                   LEFT OUTER JOIN pages_categories pc ON p.id = pc.page_id
                   LEFT OUTER JOIN categories cat ON pc.category_id = cat.id,
                   content c
                   LEFT OUTER JOIN authors a ON a.id = c.author_id
              WHERE
                  cp.page_id = bp.page_id AND b.id = bp.bulletin_id
                  AND c.id = cp.content_id AND p.id = cp.page_id
                  AND cp.content_id IN $in AND $show_inactive_where
                  $filter
              ORDER BY c.id, b.valid_from DESC, bp.order, cp.position, pc.significance DESC
            ) AS data
            ORDER BY $order_qp
            LIMIT $limit
            OFFSET $offset
            ";
        if(!empty($hits)){
            $rows = $db->fetchAssoc($dbq);
        }
        else{
            $rows = array();
        }
        // Pridame do kazdeho prvku jeho poradi v razeni podle db pokud radime jinak
        // nez podle relevance
        if($order == 2 || $order == 3){
            $i = 0;
            foreach($rows as $key => $row){
                $rows[$key]['order_num'] = $i;
                $i++;
            }
        }

        // Procistime pole hituu od neodpovidajicich pozadavkum
        // podle vysledku z DB a priradime dalsi udaje ziskane z DB
        $hits_sorted = array(); // Pokud menime razeni, ukladame sem
        foreach($hits as $key => $hit){
            if(!isset($rows[$hit->content_id])){
                // Neexistuje zaznam, content neni aktivni, nebo nema page ci bulletin
                // smazeme ho z vysledku
                unset($hits[$key]);
                continue;
            }

            $r = $rows[$hit->content_id];
            $params = array('name' => $r['bulletin_url_name'], 'article' => $r['page_url_name']);
            $url = $urlHlpr->url($params, 'bulletinarticle');

            if(isset($field_names['anchor']) && !empty($hit->anchor)){
                // Pridame odkaz na kotvu v dokumentu na konec URL
                $url = $url.'#'.$hit->anchor;
            }
            $hits[$key]->url = $url;
            $hits[$key]->author = $r['author'];
            $created = new Zend_Date($r['created'], Zend_Date::ISO_8601);
            $created->setLocale($locale);
            $hits[$key]->created = $created->toString($config->general->dateformat->short);
            $hits[$key]->category_name = $r['category_name'];
            $hits[$key]->category_id = $r['category_id'];

            // Pokud ma byt razeni jine nez podle relevance,
            // zmenime kazdemu prvku klic podle poradi a pozdeji seradime pole
            if($order == 2 || $order == 3){
                $hits_sorted[$r['order_num']] = $hits[$key];
                unset($hits[$key]);
                /*
                if(isset($hits[$r['order_num']])){
                    $hits[$r['order_num']] = $hits[$key];
                }
                else{
                    $hits[$r['order_num']] = $hits[$key];
                    unset($hits[$key]);
                }
                */

            }

            if(!empty($r['category_name']) && !empty($r['category_id'])){
                $categoriesA[$r['category_id']] = $r['category_name'];
            }
            if(!empty($r['author']) && !empty($r['author_id'])){
                $authorsA[$r['author_id']] = $r['author'];
            }
        }
        // Pokud ma byt razeni jine nez podle relevance,
        // seradime pole podle poradi nastaveneho z vysledku z DB
        if($order == 2 || $order == 3){
            $hits = $hits_sorted;
            unset($hits_sorted);
            ksort($hits);
        }

        $this->view->hits = $hits;
        $this->view->query = $q;

        // Najdeme si seznam kategorii a autoruu
        $categoriesA = array('default' => Ibulletin_Texts::get('.all'));
        $sel = "
            SELECT DISTINCT c.id, c.name
            FROM
                pages p
                JOIN pages_categories pc ON p.id = pc.page_id
                JOIN categories c ON pc.category_id = c.id
                JOIN bulletins_pages bp ON p.id = bp.page_id
                JOIN bulletins b ON b.id = bp.bulletin_id
            WHERE
                $show_inactive_where
            ORDER BY c.name
            ";
        $cat_rows = $db->fetchAll($sel);
        foreach ($cat_rows as $cat){
            $categoriesA[$cat['id']] = $cat['name'];
        }

        // Pole selectu autoruu
        $authors = new Authors();
        $authorsA = $authors->getAuthorsSelectList(true);

        $this->view->categories_sel = $categoriesA;
        $this->view->authors_sel = $authorsA;


        // Pokud je zadano jmeno bulletinu, nechame jej najit a ulozit do registru
        if($req->has('bulletin')){
            $bul_url_name = $req->getParam('bulletin');
            $bulletin_id = $bulletins->findBulletinId($bul_url_name, true);
        }
        else{
            $bul_url_name = null;
            $bulletin_id = null;
        }

        // Breadcrumbs (nekompatibilni s breadcrumbs z BulletinController)
        $this->view->breadcrumbs = array(
            'Vyhledávání' => array(
                'params' => array('controller' => 'search',
                                  'q' => $q),
                'route_name' => 'default'),
            );

        // JS pro automaticke odesilani formulare na onchange
        Ibulletin_Js::onchangeFormSubmit('filterform', 'fauthor');
        Ibulletin_Js::onchangeFormSubmit('filterform', 'fcategory');
        Ibulletin_Js::onchangeFormSubmit('filterform', 'order1');
        Ibulletin_Js::onchangeFormSubmit('filterform', 'order2');
        Ibulletin_Js::onchangeFormSubmit('filterform', 'order3');
        Ibulletin_Js::hideElem('filtersubmit');

        // Paginator do view
        $this->view->paginator = $paginator;

        $this->view->is_search = true;

        // Pridame do view nastaveni razeni a filtrovani
        $this->view->order_num = $order;
        $this->view->filter_category = $filter_category;
        $this->view->filter_author = $filter_author;

        $showCont = Ibulletin_ShowContent::getInstance();
        // Pripravime data pro menu
        $this->view->menu = $showCont->renderMenu($bulletin_id);

        // Cely radek bulletinu
        $this->view->bulletinRow = $bulletins->get($bulletin_id);
        // Nazev bulletinu, k pouziti na strance
        $this->view->bulletin_name = $this->view->bulletinRow['name'];
        // Zapiseme url_name current bulletinu do view
        $this->view->bul_url_name = $this->view->bulletinRow['url_name'];
        
        
        // Layout podle nastaveni u vydani
        if(!empty($this->view->bulletinRow['layout_name'])){
            Layouts::setLayout($this->view->bulletinRow['layout_name'], $this);
        }
    }
}
