<?php
/**
 * Zobrazuje clanek v bulletinu, nebo cely bulletin podle potreby
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class BulletinController extends Zend_Controller_Action
{
    /**
     * Zobrazi clanek v bulletinu
     *
     * TODO - je treba tuto metodu prebrat a odstranit funkcionality,
     *        ktere jiz neresi jako je zobrazovani prvniho clanku v bulletinu, kdyz
     *        neni zadan clanek a podobne
     *
     * @param $bulletin_id  Int     ID bulletinu (pouze pokud volame pro jiz nactena data stranky)
     * @param $page_data    array   Data stranky ve tvaru jaky predava $showCont->fetchPageData
     *                              (pouze pokud volame pro jiz nactena data stranky)
     */
    public function showAction($bulletin_id = null, $page_data = null)
    {
        Zend_Loader::loadClass('Ibulletin_ShowContent');
        Zend_Loader::loadClass('Bulletins');

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $bulletins = new Bulletins();
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

        $fc = Zend_Controller_Front::getInstance();
        $req = $this->getRequest();
        $router = $fc->getRouter();
        $showCont = Ibulletin_ShowContent::getInstance();

        $route_name = $router->getCurrentRouteName();


        // Pokud nebylo zavolano odjinud musime dohledat dalsi informace
        if(empty($page_data)){
            // Ziskame url parametry ktere byly zadany
            $bulletin_url_name = $req->getParam('name');
            $article_url_name = $req->getParam('article');

            $bulletin_id = $bulletins->findBulletinId($bulletin_url_name, true);
            $this->bulletin_id = $bulletin_id;
            if($bulletin_id === false){
                // Stranka nebyla nalezena, vyhodime odpovidajici exception
                require_once('Zend/Controller/Dispatcher/Exception.php');
                throw new Zend_Controller_Dispatcher_Exception('Nebyl nalezeny pozadovany bulletin. Url_name: "'.
                $bulletin_url_name.'"');
            }

            try
            {
                $page_data = $showCont->fetchPageData($article_url_name, $bulletin_id);
            }
            catch(Ibulletin_ShowContent_Not_Found_Exception $e)
            {
                // Stranka nebyla nalezena, vyhodime odpovidajici exception
                require_once('Zend/Controller/Dispatcher/Exception.php');
                throw new Zend_Controller_Dispatcher_Exception($e);
            }

            // HACK?? Ma smysl toto kontrolovat, pokud pred tim odchytavame exception???
            // Presmerovani na koren bulletinu, pokud je adresa nesmyslna
            if($bulletin_id === false || $page_data === false){
                $params = array();
                // Pokud je zadan bulletin, prejdeme na bulltin
                if(is_numeric($bulletin_id)){
                    $params = array('name' => $bulletin_url_name);
                }
                $redirector->gotoRouteAndExit($params, 'bulletin');
            }
        }

        $bul_url_name = $bulletins->findBulletinUrlName($bulletin_id);

        // Zaznamename do statistik navstevu page
        Ibulletin_Stats::getInstance()->setAttrib('action', 'page');
        Ibulletin_Stats::getInstance()->setAttrib('page_id', $page_data['page_id']);
        Ibulletin_Stats::getInstance()->setAttrib('bulletin_id', $page_data['bulletin_id']);


        // Breadcrumbs
        $breadcrumbs = array();
        if(!empty($page_data['category_url_name'])){
            $breadcrumbs['category'][$page_data['category_url_name']] = array(
                'params' => array('name' => $page_data['category_url_name'],
                                  'bulletin' => $bul_url_name),
                'route_name' => 'categorybulletin');
        }
        $breadcrumbs['page'][$page_data['url_name']] = array(
                'params' => array('name' => $bul_url_name,
                                  'article' => $page_data['url_name']),
                'route_name' => 'bulletinarticle');
        $this->view->breadcrumbs = $breadcrumbs;

        // Layout celeho webu
        if(!empty($page_data['layout_name']) || !empty($page_data['category_layout_name'])
            || !empty($page_data['bulletin_layout_name']))
        {
            // Vybreme nejspecifictejsi zadany layout
            if(!empty($page_data['bulletin_layout_name'])){
                $layoutName = $page_data['bulletin_layout_name'];
            }
            if(!empty($page_data['category_layout_name'])){
                $layoutName = $page_data['category_layout_name'];
            }
            if(!empty($page_data['layout_name'])){
                $layoutName = $page_data['layout_name'];
            }

            /*
            $layoutFileTop = $page_data['layout_name'].'_top.phtml';
            $layoutFileBottom = $page_data['layout_name'].'_bottom.phtml';

            $this->view->layoutFileTop = $layoutFileTop;
            $this->view->layoutFileBottom = $layoutFileBottom;

            // Pokusime se nahradit css.css, pokud existuje pro dany layout
            if(file_exists('pub/css/'.$page_data['layout_name'].'.css')){
                Ibulletin_HtmlHead::removeFile('css.css', 'css');
                Ibulletin_HtmlHead::addFile($page_data['layout_name'].'.css', 'css');
            }

            // Pokusime se nahradit view skript pro tuto akci, pokud existuje odpovidajici skript
            //$action = $req->getActionName();
            $controller = $req->getControllerName();
            $viewScriptName = $controller.'/'.$page_data['layout_name'].'-show.phtml';
            if($this->view->getScriptPath($viewScriptName)){
                $this->getHelper('viewRenderer')->setScriptAction($page_data['layout_name'].'-show');
            }
            */
            Layouts::setLayout($layoutName, $this);

            // Zkusime nahradit hlavni template pro page, pokud existuje
            if($this->view->getScriptPath($layoutName.'/'.$page_data['tpl_file'])){
                $page_data['tpl_file'] = $layoutName.'/'.$page_data['tpl_file'];
            }
        }


        // Vyrenderujeme do view stranku ------------------------------------------------
        $this->renderPage($page_data);
        // ------------------------------------------------------------------------------

        $categories = new Categories();
        $this->view->category = $categories->getCategoryInfo($page_data['category_url_name']);

        // Jmeno kategorie
        $this->view->category_name = $page_data['category'];
        $this->view->category_url_name = $page_data['category_url_name'];

        // Pripravime data pro menu
        $this->view->menu = $showCont->renderMenu($bulletin_id);

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

    }


    /**
     * Zobrazi cely bulletin podle nastaveni v configu - globals - main_page.type...
     *
     * Jak zobrazit bulletin po vstupu na hlavni stranku:
     *   - zobrazit prvni clanek v bulletinu             article
     *   - zobrazit bulletin po kategoriich              categories
     *   - zobrazit bulletin bez ohledu na kategorie     bulletin
     */
    public function showbulletinAction()
    {
        Zend_Loader::loadClass('Ibulletin_ShowContent');
        Zend_Loader::loadClass('Bulletins');

        $config = Zend_Registry::get('config');
        $req = $this->getRequest();
        $showCont = Ibulletin_ShowContent::getInstance();
        $bulletins = new Bulletins();

        $bulletin_url_name = $req->getParam('name');

        // Pokud nebylo zadano url_name, presmerujeme na aktualni bulletin
        if(empty($bulletin_url_name)){
            $bulletins->redirectToActualBulletin(true);
        }

        // Najdeme id bulletinu a nastavime jej jako current
        $bulletin_id = $bulletins->findBulletinId($bulletin_url_name, true);
        if($bulletin_id === false){
            // Stranka nebyla nalezena, vyhodime odpovidajici exception
            require_once('Zend/Controller/Dispatcher/Exception.php');
            throw new Zend_Controller_Dispatcher_Exception('Nebyl nalezeny pozadovany bulletin. Url_name: "'.
                $bulletin_url_name.'"');
        }

        # Chceme zobrazit prvni clanek v bulletinu
        if($config->general->main_page->type == 'article'){
            $page_row = $showCont->getFirstBulletinsPageRow($bulletin_id);
            $page_data = $showCont->fetchPageData($page_row, $bulletin_id);

            // Nastavime render script (musime predem, protoze showAction obcas meni
            $this->getHelper('viewRenderer')->setScriptAction('show');

            $this->showAction($bulletin_id, $page_data);


            return;

            /*
            // Vyrenderujeme do view stranku
            $this->renderPage($page_data);

            // Zapiseme do statistik page_id
            Ibulletin_Stats::getInstance()->setAttrib('page_id', $page_data['page_id']);
            */
        }


        # Zobrazujeme seznam clanku blletinu dle kategorii
        elseif($config->general->main_page->type == 'categories'){
            Zend_Loader::loadClass('Categories');
            Zend_Loader::loadClass('Pages');
            $pages = new Pages();
            $categories = new Categories();

            // Vyhledame anotace
            $annotations = $pages->getPagesAnnotations(null, $bulletin_url_name);

            // Prestrukturujeme anotace podle kategorii
            // Vyhazujeme skryte kategorie, ty se v tomto vypisu neukazuji
            $annot_by_cat = array();
            $last_cat_url_name = '';
            $annot_in_cat = array();
            foreach($annotations as $annotation){
                // Vyhazujeme kategorie, ktere jsou skryte
                if($annotation['cat_type'] == 'h'){
                    continue;
                }
                if($last_cat_url_name != $annotation['category_url_name']){
                    if($last_cat_url_name != ''){
                        $annot_by_cat[] = $annot_in_cat;
                    }
                    $last_cat_url_name = $annotation['category_url_name'];
                    $annot_in_cat = array();
                }
                $annot_in_cat[] = $annotation;
            }

            if (!empty($annotations)) {
                $annot_by_cat[] = $annot_in_cat;
            } else {
                $annot_by_cat = array();
            }

            // Ulozime pole dat anotaci jednotlivych clanku
            $this->view->annotations_by_cat = $annot_by_cat;

            // Nastavime render script
            $this->getHelper('viewRenderer')->setScriptAction('show-bulletin-by-cat');
        }


        # Zobrazujeme seznam clanku blletinu samostatne
        elseif($config->general->main_page->type == 'bulletin'){
            Zend_Loader::loadClass('Categories');
            Zend_Loader::loadClass('Pages');
            $pages = new Pages();
            $categories = new Categories();

            // Ulozime pole dat anotaci jednotlivych clanku
            $this->view->annotations = $pages->getPagesAnnotations(null, $bulletin_url_name, false);

            // Nastavime render script
            $this->getHelper('viewRenderer')->setScriptAction('show-bulletin');
        }

        // Zaznamename do statistik navstevu page
        Ibulletin_Stats::getInstance()->setAttrib('action', 'bulletin');
        Ibulletin_Stats::getInstance()->setAttrib('bulletin_id', $bulletin_id);


        $this->view->is_bulletin = TRUE;

        // Pripravime data pro menu
        $this->view->menu = $showCont->renderMenu($bulletin_id);

        // Zapiseme url_name current bulletinu do view
        Zend_Loader::loadClass('Bulletins');
        $bulletins = new Bulletins();

        // Cely radek bulletinu
        $currBulletinRow = $bulletins->get((int)$bulletin_id);
        $this->view->bulletinRow = $currBulletinRow;
        // Nazev bulletinu, k pouziti na strance
        $this->view->bulletin_name = $currBulletinRow['name'];
        // Zapiseme url_name current bulletinu do view
        $this->view->bul_url_name = $currBulletinRow['url_name'];

        // Zaznam nejaktualnejsiho bulletinu pro ruzne operace ve view
        $this->view->actual_bul_row = $bulletins->getActualBulletinRow(false);

        // Seznam vsech bulletinu pro menu iTrio a inCast
        $this->view->all_bulletins = $bulletins->getBulletinList(true, array('created ASC', 'valid_from ASC'));

        // Layout podle nastaveni u vydani
        if(!empty($currBulletinRow['layout_name'])){
            Layouts::setLayout($currBulletinRow['layout_name'], $this);
        }
    }

    /**
     * Vvyrenderuje do view clanek zadany v parametrech
     *
     * @param array Kompletni data stranky napriklad z metody
                    Ibulletin_ShowContent::fetchPageData()
     */
    public function renderPage($page_data)
    {
        //Zend_Loader::loadClass('Pages');

        $config = Zend_Registry::get('config');
        $pages = new Pages();

        /**
         * RATING
         */
        // Pokud byl rating odeslan, ulozime ho - toto pouzivame pro to, aby bylo mozne provozovat
        // rating bez JS, pomoci JS je rating ulozen pres $js->vars->rating_savePageRatingUrl
        $sentRating = $this->getRequest()->getParam('rating', null);
        if($sentRating !== null){
            Ibulletin_Stats::savePageRating($page_data['page_id'], $sentRating);
        }

        // Ulozime do JS adresu pro PAGE RATING
        $urlHlpr = new Zend_View_Helper_Url();
        $js = Ibulletin_Js::getInstance();
        $js->vars->rating_savePageRatingUrl = $urlHlpr->url(array('controller' => 'service',
                                                                'action' => 'savepagerating',
                                                                'page_id' => $page_data['page_id']),
                                                                'default').'/';
        
        try{
            // Zkusime ziskat rating i s ratingem konkretniho uzivatele
            $ratingA = Ibulletin_Stats::getPageRating($page_data['page_id'], false);
            $this->view->usersPageRating = $ratingA['rating'];
        }
        catch(Ibulletin_Stats_No_User_Set_Exception $e){
            // Uzivatel zrejme neni k dispozici, vezmeme alespon celkovy rating
            $ratingA = Ibulletin_Stats::getPageRating($page_data['page_id'], true);
            $this->view->usersPageRating = null;
        }
        $this->view->pageRating = $ratingA['avg'];
        $this->view->pageRatingUsersCount = $ratingA['count'];

        // Pokud pouzivame interni like, pripojime JS pro obsluhu
        if($config->general->show_like_butt == 'local' && $ratingA['rating'] === null){
            // JS pro like nacitame ze souboru like.js
            $js->addOnActionCode(array('like_a'), 'click', file_get_contents(Ibulletin_Js::$jsRoot.'/like.js'));
        }



        // Nastavime do view hlavni template
        $this->view->main_template = $page_data['tpl_file'];

        // Nastavime do view id a url name teto page
        $this->view->curr_page_id = $page_data['page_id'];
        $this->view->curr_page_url_name = $page_data['url_name'];

        // Nastavime do view nazev do url pro promennou listu ve strance
        $this->view->sheet_number_url_name = $config->general->url_name->sheet_number;

        $req = $this->getRequest();
        $content_tpl = array();
        $content = array();
        // Nastavime templaty a data pro jednotlive contenty
        foreach ($page_data['contents'] as $position => $object) {

            // Vyrenderujeme (pripravime do view) postupne vsechny contenty
            $this->renderContent($position, $object, $page_data['page_id'], $req, $content, $content_tpl);
        }

        // Nahradime templaty contentu za layout specific, pokud takove existuji
        if(!empty($page_data['layout_name']) || !empty($page_data['category_layout_name'])
            || !empty($page_data['bulletin_layout_name']))
        {
            // Vybreme nejspecifictejsi zadany layout
            if(!empty($page_data['bulletin_layout_name'])){
                $layoutName = $page_data['bulletin_layout_name'];
            }
            if(!empty($page_data['category_layout_name'])){
                $layoutName = $page_data['category_layout_name'];
            }
            if(!empty($page_data['layout_name'])){
                $layoutName = $page_data['layout_name'];
            }
            //$layoutName = $page_data['layout_name'];

            foreach($content_tpl as $key => $val){
                //$matches = array();
                //preg_match('/^(.*)\.phtml$/', $val, $matches);
                //$name = $matches[1];
                if($this->view->getScriptPath($layoutName.'_'.$val)){
                    $content_tpl[$key] = $layoutName.'_'.$val;
                }
            }
        }

        $this->view->content_tpl = $content_tpl;
        $this->view->content = $content;


        // Ziskame odkaz na predchozi a nasledujici clanek v bulletinu
        $this->view->prevNextPage = $pages->getPrevNextPageInBulletin(
                                        $page_data['url_name'],
                                        $page_data['bulletin_id']);

    }

    /**
     * Renderuje jeden content. View je potreba jen v pripade pouziti renderu pro content na pozici 1,
     * jinak lze renderovat celekm bez problemu cokoliv.
     */
    public function renderContent($position, $object, $page_id, $req, &$content, &$content_tpl){
        $config = Zend_Registry::get('config');

        // Sdelime objektu position
        $object->setPosition($position);
        $object->setPageId($page_id);
        // V nekterych contentech potrebujeme i bulletin_id. :o/
        if(!empty($this->bulletin_id)){
            $object->bulletinId = $this->bulletin_id;
        }

        $object->prepare($req,$position);

        // Pokud je to pozice 1 vlozime jeho title
        if($position == 1){
            $this->view->page_title = $object->getTitle();

            // Nastavime URL pro ziskani PDF teto stranky (hlavniho contentu),
            // pokud toto content neumi, vraci null
            $this->view->pagePdfUrl = $object->getPdfUrl();

            // Pole jednotlivych listu
            // Jen hlavni obsah stranky muze mit vic listu
            $contentSheetKeys = $object->getSheetKeys();
            $this->view->contentSheetKeys = $contentSheetKeys;
            $this->view->contentActualSheet = $object->getSheetNumber();
            if(count($contentSheetKeys) > 1){
                // Nasledujici a predchozi list
                $currArrayKey = array_search($object->sheet_number, $contentSheetKeys);
                $nextArrayKey = $currArrayKey + 1;
                $prevArrayKey = $currArrayKey - 1;
                $this->view->contentNextSheet = isset($contentSheetKeys[$nextArrayKey]) ? $contentSheetKeys[$nextArrayKey] : null;
                $this->view->contentPrevSheet = isset($contentSheetKeys[$prevArrayKey]) ? $contentSheetKeys[$prevArrayKey] : null;
            }
            else{
                // Nastavime na null, aby se listy nezobrazovaly
                $this->view->contentSheetKeys = null;
            }
        }

        // Template pro dany content
        $content_tpl[$position] = $object->getTplFileName();

        // Data contentu
        $content[$position] = $object->getContent(true);

        // Anotace
        $content[$position]['annotation'] = $object->getAnnotation();

        // Jmeno clanku
        $content[$position]['title'] = $object->getName();

        // Dalsi data objektu
        $content[$position]['data'] = $object->getData();

        // Samotny objekt
        $content[$position]['obj'] = $object;

        // Datum pridani a jmeno autora
        $created = new Zend_Date($object->created, Zend_Date::ISO_8601);
        $created = $created->toString($config->general->dateformat->short);
        $date_authorA = array('created' => $created, 'author'=> $object->getAuthor());
        //$date_authorA = array_filter($date_authorA, create_function('$elem','return empty($elem) ? false : true;'));
        //$date_author = join(' | ', $date_authorA);
        $content[$position]['info'] = $date_authorA;

        return $content;
    }

    /**
     * Vyrenderuje do response jeden samotny content.
     *
     * Nepodporuje odkaz na predchozi a nasledujici bulletin.
     * 
     * @param $position int         Cislo pozice contentu na strance
     * @param $object Ibulletin_Content_Abstract
     * @param $page_id int          ID page daneho contentu - nektere contenty potrebuji pro svoji
     *                              cinnost mit nastavene page ID
     * @param $page_template string Template k pouziti nad template contentu z adresare. DEFAULT null
     * @param $toString boolean     Renderovat misto do view jen do stringu s HTML? DEFAULT false
     * 
     * @return string|null          HTML contentu nebo null, pokud neni nastaveno $toString
     */
    public function renderContentHtml($position, Ibulletin_Content_Abstract $object, $page_id, $page_template = null, $toString = false){
        $config = Zend_Registry::get('config');
        $req = $this->getRequest();
        $content_tpl = array();
        $content = array();

        // Pripravime dummy view nebo pouzijeme aktualni podle toho, zda renderujeme do stringu ci ne
        if($toString){
            // Vytvorime kopii view abyhcom meli k dispozici nastaveni cest ke skriptum a dalsi nastaveni
            // tento view pouzijeme jen k renderu daneho contentu
            $view = $view = clone $this->view;
        }
        else{
            $view = $this->view;
        }
        
        // Vyrenderujeme (pripravime do view)
        $this->renderContent($position, $object, $page_id, $req, $content, $content_tpl);

        // Pokud je zadano page_id, pripravime do view jeste informace o kategorii
        if(!empty($page_id)){
            $categories = Categories::getPageCategories($page_id);
            if(!empty($categories)){
                $category = $categories[0];
                $view->category_name = $category['name'];
            }
        }

        $view->current_content = $position;
        $view->content_tpl = $content_tpl;
        $view->content = $content;

        // Pokud je zadan $page_template, pouzime jej k renderovani, jinak renderujeme jen content template
        if(!empty($page_template)){
            $template = $page_template;
        }
        else{
            $template = $content_tpl[$position];
        }
        
        // Renderujeme do view nebo do stringu
        if($toString){ // renderujeme jinak pro string a pro aktualni view
            return $view->render($template);
        }
        else{
            $this->renderScript($template);
        }
    }
}
