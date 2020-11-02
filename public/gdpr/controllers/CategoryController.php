<?php
/**
 * Stara se o vypsani seznamu clakuu v kategorii. Podle nastaveni v configu
 * presmeruje na jediny clanek v kategorii, pripadne zobrazuje clanky v kategorii
 * ze vsech cisel bulletinu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class CategoryController extends Zend_Controller_Action
{
    public function indexAction()
    {
        Zend_Loader::loadClass('Bulletins');
        Zend_Loader::loadClass('Categories');
        Zend_Loader::loadClass('Pages');
        Zend_Loader::loadClass('Ibulletin_ShowContent');
        $config = Zend_Registry::get('config');
        $req = $this->getRequest();
        $bulletins = new Bulletins();
        $pages = new Pages();
        $categories = new Categories();
        $session = Zend_Registry::get('session');
        $show_only_current_bulletin = $config->general->show_only_current_bulletin_in_category;

        // Seznamy content typu a url jmen kategorii, pro ktera se ma primo zobrazovat
        // jediny clanek v kategorii
        // !!Dale se vstup primo na clanek ridi podle tabulky categories.goto_article
        $content_directly_types = $config->general->category
            ->getCommaSeparated('content_types_to_open_directly_when_only_one_article_in_category');
        $content_directly_url_names = $config->general->category
            ->getCommaSeparated('category_url_names_to_open_directly_when_only_one_article_in_category');

        $cat_url_name = $req->getParam('name');

        // kontrola existence kategorie
        if ($categories->getCategoryInfo($cat_url_name) === NULL) {
            // Pozadovana kategorie nebyla nalezena, vyhodime Dispatcher exception
            require_once('Zend/Controller/Dispatcher/Exception.php');
            throw new Zend_Controller_Dispatcher_Exception('Nebyl nalezena pozadovana kategorie. Url_name: "'.
                $cat_url_name.'"');
        };

        // Pokud je zadano jmeno bulletinu, nechame jej najit a oznacit za aktivni
        if($req->has('bulletin')){
            $bul_url_name = $req->getParam('bulletin');
            $bulletin_id = $bulletins->findBulletinId($bul_url_name, true);
            // kontrola existence bulletinu
            if($bulletin_id === false){
                // Pozadovany bulletin nebyl nalezen, vyhodime Dispatcher exception
                require_once('Zend/Controller/Dispatcher/Exception.php');
                throw new Zend_Controller_Dispatcher_Exception('Nebyl nalezeny pozadovany bulletin. Url_name: "'.
                    $bul_url_name.'"');
            }
            // ziskani dat pro bulletin
            $bulletinRow = $bulletins->get($bul_url_name, true);
        }
        else{
            $bulletin_row = $bulletins->getActualBulletinRow(true);
            $bul_url_name = $bulletin_row['url_name'];
            $bulletin_id = $bulletin_row['id'];
        }

        // Nastavime poradi ziskavani parametru z requestu
        //$req->setParamSources(array('_GET', '_POST', 'userland'));

        $url_params = array();
        // Poradi a filtrovani
        $order = (int)$req->getParam('order', 0);
        $filter_author = $req->getParam('fauthor', 'default');
        $filter = '';
        if($filter_author != 'default'){
            $filter = array('a.id = '.(int)$filter_author);
        }
        // Order query part
        $order_qp = array();
        if($order == 1){
            $order_qp[] = 'c.created DESC';
        }
        elseif($order == 2){
            $order_qp[] = 'p.name';
        }
        elseif($order == 3){
            $order_qp[] = 'a.name';
        }
        $order != 0 ? $url_params['order'] = $order : null;
        $filter_author != 'default' ? $url_params['fauthor'] = $filter_author : null;
        // Ulozime all do URL params
        $url_params['all'] = $req->getParam('all');

        // Zjistime celkovy pocet vysledkuu
        if($req->has('all') || !$req->has('bulletin') || empty($show_only_current_bulletin)){
            $annot_count = $pages->getPagesAnnotations($cat_url_name, null, true,
                                    $filter, $order_qp, null, null, true);
            // Pouzivame pro zjisteni jestli se nema presmerovat primo na clanek
            $annot_count_bull = $pages->getPagesAnnotations($cat_url_name, $bul_url_name, true,
                                    $filter, $order_qp, null, null, true);
        }
        else{
            $annot_count = $pages->getPagesAnnotations($cat_url_name, $bul_url_name, true,
                                    $filter, $order_qp, null, null, true);
            $annot_count_all = $pages->getPagesAnnotations($cat_url_name, null, true,
                                    $filter, $order_qp, null, null, true);
            $annot_count_bull = $annot_count;
        }

        // Strankovani paginatorem
        $params = new stdClass();
        $params->urlParams = $url_params;
        $this->view->paginatorUrlParams = $params;
        $paginator = new Zend_Paginator(new Zend_Paginator_Adapter_Null($annot_count));
        $paginator->setItemCountPerPage($config->general->paging->itemsperpage);
        //$paginator->setViewParameters($params);
        $paginator->setCurrentPageNumber($req->getParam('page', 1));
        $limit = (int)$paginator->getItemCountPerPage();
        $offset = (int)$limit * $paginator->getCurrentPageNumber() - (int)$limit;


        if($req->has('all') || !$req->has('bulletin') || empty($show_only_current_bulletin)){
            // Pokud je pozadovano vypsat vsechny i stare clanky,
            // pouzijeme pole se vsema
            $annotations = $pages->getPagesAnnotations($cat_url_name, null, true,
                           $filter, $order_qp, $limit, $offset);

            // Nastavime info do view
            $this->view->showing_whole_category = true;
        }
        else{
            // Ziskame pole anotaci pouze pro konretni bulletin
            // Clanky v kategorii a vybranem bulletinu
            $annotations = $pages->getPagesAnnotations($cat_url_name, $bul_url_name, true,
                                    $filter, $order_qp, $limit, $offset);
        }

        // Zjistime, jestli existuji clanky mimo tento bulletin
        if(!empty($annot_count_all) && ($annot_count_all != $annot_count)){
            $this->view->articles_in_other_bulletins = true;
        }
        else{
            $this->view->articles_in_other_bulletins = false;
        }
        // Ulozime, jak je nastaveno zobrazovani anotaci v kategorii
        $this->view->show_only_current_bulletin = $show_only_current_bulletin;

        $this->view->annotations = $annotations;

        $category_row = $categories->getCategoryInfo($cat_url_name);

        // Zaznamename do statistik navstevu page
        Ibulletin_Stats::getInstance()->setAttrib('action', 'category');
        Ibulletin_Stats::getInstance()->setAttrib('category_id', $category_row['id']);
        Ibulletin_Stats::getInstance()->setAttrib('bulletin_id', $bulletin_id);

        // Zjistime, jestli je pozadovano zobrazeni primo clanku
        // pokud je v kategorii jen jeden clanek
        if(1){
            // Pokud je jen jeden, redirectujeme
            // Zavisi take na konfiguraci chovani pri jednom clanku v kategorii
            // v tomto cisle a dalsich clancich v teto cat. a jinych cislech
            // Pripadne se rozhodujeme podle nastaveni zobrazit primo jen jeden clanek v
            // kategorii pro urcity content type, nebo url_name kategorie, nebo v tabulce categories.goto_article
            //
            // Nastaveni $config->general->show_directly_first_page_in_category znamena, ze
            // se ma za kazdych okolnosti zobrazit prvni clanek v kategorii
            if($annot_count_bull == 1 || $config->general->show_directly_first_page_in_category){
                // Najdeme prvni clanek pro tento bulletin (na ten budeme pripadne presmerovavat)
                if($annot_count_bull !== $annot_count){
                    // Pokud nebyly vybrany jen anotace pro tento bulletin je treba provest dotaz
                    // znovu, protoze v celkovem vypisu jiz muze byt dany content zarazen v novejsim vydani
                    $annotation = $pages->getPagesAnnotations($cat_url_name, $bul_url_name, true,
                        $filter, $order_qp);
                    $annotation = $annotation[0];
                }
                else{
                    $annotation = $annotations[0];
                }

                // Rozhodneme, jestli se ma skutecne zobrazit prvni clanek
                if(
                    (
                        $config->general->show_directly_only_one_page_in_category
                        &&(
                            !$this->view->articles_in_other_bulletins
                            || !$config->general->show_article_list_when_articles_in_other_bulletins
                        )
                        && !$req->has('all')
                    )
                    || in_array($category_row['url_name'], $content_directly_url_names)
                    || $category_row['goto_article']
                    || in_array($annotation['class_name'], $content_directly_types)
                    || $config->general->show_directly_first_page_in_category
                  )
                {
                    $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                    $redirector->gotoRouteAndExit(
                           $annotation['url']['params'],
                           $annotation['url']['route']);
               }
            }
        }

        // JS pro automaticke odesilani formulare na onchange
        Ibulletin_Js::onchangeFormSubmit('filterform', 'fauthor');
        Ibulletin_Js::onchangeFormSubmit('filterform', 'order1');
        Ibulletin_Js::onchangeFormSubmit('filterform', 'order2');
        Ibulletin_Js::onchangeFormSubmit('filterform', 'order3');
        Ibulletin_Js::hideElem('filtersubmit');

        // Pole selectu autoruu
        $authors = new Authors();
        $authorsA = $authors->getAuthorsSelectList(true);
        $this->view->authors_sel = $authorsA;

        // Breadcrumbs
        $this->view->breadcrumbs = array('category' =>
            array(
            $category_row['url_name'] => array(
                'params' => array('name' => $category_row['url_name'],
                                  'bulletin' => $bul_url_name),
                'route_name' => 'categorybulletin'),
            ));


        // Ulozime do view data kategorie
        $this->view->category = $category_row;

        $this->view->is_category = true;

        // Paginator do view
        $this->view->paginator = $paginator;

        // Pridame do view nastaveni razeni a filtrovani
        $this->view->order_num = $order;
        $this->view->filter_author = $filter_author;

        $showCont = Ibulletin_ShowContent::getInstance();
        // Pripravime data pro menu
        $this->view->menu = $showCont->renderMenu($bulletin_id);

        // Zapiseme url_name current bulletinu do view
        Zend_Loader::loadClass('Bulletins');
        $bulletins = new Bulletins();
        // Cely radek bulletinu
        $this->view->bulletinRow = $bulletins->get($bulletin_id);
        // Nazev bulletinu, k pouziti na strance
        $this->view->bulletin_name = $this->view->bulletinRow['name'];
        // Zapiseme url_name current bulletinu do view
        $this->view->bul_url_name = $this->view->bulletinRow['url_name'];

        // Zaznam nejaktualnejsiho bulletinu pro ruzne operace ve view
        $this->view->actual_bul_row = $bulletins->getActualBulletinRow(false);

        // Seznam vsech bulletinu pro menu iTrio a inCast
        $this->view->all_bulletins = $bulletins->getBulletinList(true, array('created ASC', 'valid_from ASC'));

        // Layout podle nastaveni u kategorie
        if(!empty($category_row['layout_name']) || !empty($bulletinRow['layout_name'])){
            // Vybreme nejspecifictejsi zadany layout
            if(!empty($bulletinRow['layout_name'])){
                $layoutName = $bulletinRow['layout_name'];
            }
            if(!empty($category_row['layout_name'])){
                $layoutName = $category_row['layout_name'];
            }
            Layouts::setLayout($layoutName, $this);
        }
    }
}
