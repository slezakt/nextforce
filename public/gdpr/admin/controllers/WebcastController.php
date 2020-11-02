<?php

/**
 * Modul pro spravu webcastuu.
 *
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


class Admin_WebcastController extends Ibulletin_Admin_ContentControllerAbstract
{
    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    var $contentType = 'Ibulletin_Content_Webcast';

    /**
     * @var Ibulletin_Content_Abstract Objekt contentu, ktery prave editujeme, pokud jsme v akci edit.
     */
    var $content = null;

    /**
     * @var array    Data pro submenu modulu - zobrazi se vzdy.
     */
    var $submenuAll = array(
        'index' => array('title' => 'Seznam obsahů', 'params' => array('action' => null)),
    );

    /**
     * @var array    Data pro submenu modulu - zobrazeni podle modulu.
     */
    var $submenuSpecific = array(
    //'index' =>
    //    array('selectmainpage' => array('title' => 'Vybrat obsah pro hlavní stránku webcastu', 'params' => array('action' => 'selectmainpage')))
    );

    /**
     * @var string  Nazev modulu.
     */
    var $moduleTitle = 'Správa webcastů';

    /**
     * @var string  Nazev souboru template pro PAGE s contentem webcastu.
     */
    var $main_tpl_file_name = 'main_webcast_basic.phtml';

    /**
     * @var Ibulletin_Content_Video Objekt contentu videa prevzaty z objektu contentu
     */
    var $video = null;

    /**
     * @var Ibulletin_Content_Presentation Objekt contentu prezentace prevzaty z objektu contentu
     */
    var $presentation = null;

    /**
     * @var Ibulletin_Player_XmlConfigSync Objkt konfigurace syncpointuu videa
     */
    var $syncpoints_vid = null;

    /**
     * @var Ibulletin_Player_XmlConfigSync Objkt konfigurace syncpointuu prezentace
     */
    var $syncpoints_pres = null;


    /**
     * Editace contentu
     */
    public function editAction(){
        $config = Zend_Registry::get('config');
        $id = $this->getRequest()->getParam('id', null);
        $this->id = $id;
        $this->view->id = $id;
        $js = Ibulletin_Js::getInstance(); // Kvuli predavani promennych do JS
        $js->addJsFile('Player/webcastadmin.js'); // Nacist JS pro webcast admin
        $urlHlpr = new Zend_View_Helper_Url();
        $this->contentId = $id;
        
        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit'); 
        $this->moduleMenu->setCurrentLocation('edit');

        $data = $this->getContentData($id);
        $form = $this->getEditContentForm($data);


        // Pokud byla vyplnena data, provedeme ulozeni a aktualizujeme
        // data z ulozeni.
        if($form->isValid($data)){
            $data = $this->saveContentData($data);
            $form = $this->getEditContentForm($data);
        }
        $this->view->form = $form;


        // Prehravac videa
        if($this->video){
            $this->view->video_id = $this->video->id;
            if($this->video->ready){
                // Pripravime do stranky JS prehravace a akce s tim spojene
                //$confUrl = $this->content->getPathToFile($this->content->configFileVideo).'?'.time();
                $confUrl = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'xmlconfig',
                    'page_id' => $this->pageId, 'bulletin_id' => $this->bulletinId), 'service').'/';
                Ibulletin_Player_Js::prepareAvPlayer($this->video->playerHtmlElemId, 
                                                     $this->video->getPlayerPath(),
                                                     $confUrl
                                                     );
                
                $this->view->videoFileName = $this->video->videoFileName;
                $this->view->videoReady = true;
            }
        }
        elseif(!$this->video){
            $this->view->videoReady = false;
            // Neni video - ziskame seznam contentu videa
            $this->view->videoContents = $this->getContentList('video');
        }

        // Prehravac prezentace
        if($this->presentation){
            $this->view->presentation_id = $this->presentation->id;
            $this->view->presentationReady = false;
            if($this->presentation->ready){
                // Pripravime do stranky JS prehravace a akce s tim spojene
                $confUrl = $this->content->getPathToFile($this->content->configFilePres).'?'.time();
                Ibulletin_Player_Js::preparePtPlayer($this->presentation->playerHtmlElemId, 
                                                     $this->presentation->getPlayerPath(),
                                                     $confUrl
                                                     );
                
                $this->view->presentationFileName = $this->presentation->file_name;
                $this->view->presentationReady = true;
            }
        }
        else{
            $this->view->presentationReady = false;
            // Neni prezentace - ziskame seznam contentu prezentace
            $this->view->presentationContents = $this->getContentList('presentation');
        }

        $js->vars->videoXmlConfigUrl = $this->content->getPathToFile($this->content->configFileVideo);
        $js->vars->presXmlConfigUrl = $this->content->getPathToFile($this->content->configFilePres);

         
        # Pridani dalsich contentu do page v ktere je tento webcast
        $pages = Pages::getContentPages($id);
        if(!empty($pages)){
            $this->view->contentHasPage = true;
            $page = Pages::get($pages[0]['id']);

            $this->view->articleIncluded = null;
            $this->view->opinionsIncluded = null;
            foreach($page['content'] as $content){
                if($content['class_name'] == 'Ibulletin_Content_Article' || 
                    $content['class_name'] == 'Ibulletin_Content_Questionnaire')
                {
                    if($content['class_name'] == 'Ibulletin_Content_Atricle'){
                        $editcontroller = 'contentarticle';
                    }
                    else{
                        $editcontroller = 'questionnaire';
                    }
                    $this->view->articleIncluded = array('id' => $content['id'],
                        'name' => $content['object']->name, 'editcontroller' => $editcontroller);
                }
                elseif($content['class_name'] == 'Ibulletin_Content_Opinions')
                {
                    $editcontroller = 'opinions';
                    $this->view->opinionsIncluded = array('id' => $content['id'],
                        'name' => $content['object']->name, 'editcontroller' => $editcontroller);
                }
            }

            // Najdeme seznamy dostupnych contentu
            if(!$this->view->articleIncluded){
                $this->view->articleList = $this->getContentList('article');
            }
            if(!$this->view->opinionsIncluded){
                $this->view->opinionsList = $this->getContentList('opinions');
            }
        }
        else{
            $this->view->contentHasPage = false;
        }
         
        // Manipulace se syncpointy
        if($this->video && $this->video->ready && $this->presentation && $this->presentation->ready){
            // URL pro manipulaci se syncpointy
            $js->vars->manipulateSyncpUrl = $urlHlpr->url(array('action' => 'manipulatesync'));

            $this->prepareSyncpointsData();
        }
            
        $this->view->preview_links = $this->printLinks($id);
        
        //doplni do context menu je-li treba page id a bulletin id
        $this->view->contextMenu = $this->prepareContentContext($id);  
        
        // Titulek modulu
        $this->setActionTitle('Editace webcastu "'.$data['name'].'"');
    }


    /**
     * Ziska a vrati data contentu - pokud byl odeslan formular,
     * jsou vracena data z formulare. Nacte do atributu tridy take objekt contentu videa
     * a prezentace pro snadnejsi pristup, pokud jsou objekty k dispozici.
     *
     * @param int       ID contentu
     * @return array    Pole obsahujici data contentu
     */
    public function getContentData($id)
    {
        $db = Zend_Registry::get('db');


        // Ziskame data contentu
        $data = Contents::get($id);
        if(!$data){
            $this->infoMessage('Content se zadaným ID nebyl nalezen.');
        }

        if(!empty($data['object'])){
            $this->content = $data['object'];

            // Pokud je potreba vygenerujeme prazdne objekty syncpointuu
            $this->content->loadSubcontents();
            if(!($this->content->syncpoints_vid instanceof Ibulletin_Player_XmlConfigSync)){
                $this->content->syncpoints_vid = new Ibulletin_Player_XmlConfigSync();
                $this->content->syncpoints_vid->setMedia('vid');
                // Nastavime cil syncpointuu
                //$this->content->syncpoints_vid->setTarget($this->presentation->playerHtmlElemId);
            }
            if(!($this->content->syncpoints_pres instanceof Ibulletin_Player_XmlConfigSync)){
                $this->content->syncpoints_pres = new Ibulletin_Player_XmlConfigSync();
                $this->content->syncpoints_pres->setMedia('pres');
                // Nastavime cil syncpointuu
                //$this->content->syncpoints_pres->setTarget($this->video->playerHtmlElemId);
            }
            $this->syncpoints_vid = $this->content->syncpoints_vid;
            $this->syncpoints_pres = $this->content->syncpoints_pres;
            // Nacteme do contentu subcontenty
            if(!empty($data['object']->video)){
                $this->video = $data['object']->video;
            }
            if(!empty($data['object']->presentation)){
                $this->presentation = $data['object']->presentation;
            }
        }

        // Nastavime objekt contentu do atributu content
        $this->content = $data['object'];

        $data['name'] = $this->content->name;
        $data['data_annotation'] = $this->content->annotation;
        $data['data_author'] = $data['author_id'];

        // Upravime pripadne zaznamy v pages a links - nastavime nove jmeno
        // TODO Proc to delame tady?? :o/
        $this->updatePagesAndLinks($id, $data);



        if(isset($_POST['save_content']) && isset($_POST['id']) && $_POST['id'] == $id){
            return $_POST + $data;
        }
        elseif(!empty($this->content_data) && isset($this->content_data['id']) && $this->content_data['id'] == $id){
            return $this->content_data;
        }
        else{
            $this->content_data = $data;
            return $data;
        }
    }


    /**
     * Vrati objekt formulare pro editaci contentu.
     *
     * @return Zend_Form    Formular pro editaci contentu
     */
    public function getEditContentForm($data = null)
    {
        $db = Zend_Registry::get('db');
        
        // Pripravime selectbox existujicich autoru
        $sel = $db->select()
            ->from('authors')
            ->order('name');
        $authorsA = $db->fetchAll($sel);
        $authors_sel = array('default' => '- nový -', 'none' => '- žádný -');
        foreach ($authorsA as $var) {
            $authors_sel[$var['id']] = $var['name'];
        }
        
        // Vytvorime formular
        $form = new Form();
        $form->setMethod('post');

        $form->addElement('hidden', 'sheet');

        $form->addElement('text', 'id', array(
            'label' => 'Id: ',
            'readonly' => 'readonly',
            'class' => 'span1'
        ));

        $form->addElement('text', 'name', array(
            'label' => 'Název: ',
            ));
        
        // Autor select
        $author_sel = new Zend_Form_Element_Select(array(
            'name' => 'data_author',
            'label' => 'Autor',
            'multioptions' => $authors_sel
            ));
        $form->addElement($author_sel);
        if(is_numeric($data['author_id'])){
            $author_sel->setValue($data['author_id']);
            $data['data_author'] = $data['author_id'];
        }
        else{
            $author_sel->setValue('default');
        }
        
        // Novy autor
        $author = new Zend_Form_Element_Text(array(
            'name' => 'data_author_new',
            'label' => 'Nový autor'
            ));
        $form->addElement($author); 
        
        // Anotace
        $annotation = new Zend_Form_Element_Textarea(array(
            'name' => 'data_annotation',
            'label' => 'Anotace'
            ));
        $annotation->setAttrib('class', 'span6');
        $annotation->setAttrib('rows', '10');
        $form->addElement($annotation);
        
        // Ulozit
        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'save_content',
                'label' => 'Uložit',
                'class' => 'btn-primary'
                )));
        
        // Validacii vyplnime formular z pole
        $form->isValid($data);
        
        return $form;
    }

    /**
     * Ulozi data prijata z editacniho formulare contentu.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveContentData($data)
    {
        $db = Zend_Registry::get('db');
        $ok = true; // Flag, jestli se vse ulozilo OK
        
        $id = $data['id'];        
        
      # Data contentu a objektu v nem
        if(!$this->content){
            $content = Contents::get($id);
            $obj = $content['object'];
            $this->content = $obj;
        }
        else{
            $obj = $this->content;
        }
        $obj->name = $data['name'];
        $obj->syncpoints_vid = $this->syncpoints_vid;
        $obj->syncpoints_pres = $this->syncpoints_pres;
        $obj->annotation = $data['data_annotation'];
        
      # Pripravime a ulozime autora
        $author_sel = !empty($data['data_author']) ? $data['data_author'] : null;
        $author_new = !empty($data['data_author_new']) ? $data['data_author_new'] : null;
        $author_row = null;
        if (!$author_row && !empty($author_new) && $author_sel == 'default') {
            // Vytvorime novy zaznam do authors
            $db->insert('authors', array('name' => $author_new));
            $author_sel = $db->lastInsertId('authors', 'id');
            $obj->setAuthor($author_new);
        }
        if(is_numeric($author_sel)){
            $sel = $db->select()
                ->from('authors')
                ->order('name')
                ->where('id = :id')
                ->limit(1);
            $author_row = $db->fetchAll($sel, array('id' => $author_sel));
        }
        if(!$author_row){
            $author_sel = null;       
        }
        else{
            $data['author_id'] = $author_sel;
            $data['data_author'] = $author_sel;
            $obj->setAuthor($author_row[0]['name']);
        }
        $data['data_author_new'] = '';

        // Overeni, pripadne vytvoreni adresare contentu
        $config = Zend_Registry::get('config');
        if(!file_exists($config->content_article->basepath."/$id/")){
            mkdir($config->content_article->basepath."/$id/", 0777);
        }

        // Pro ulozeni na chvili odebereme subcontenty z contentu
        unset($obj->video);
        unset($obj->presentation);

        // Zjistime, jestli je webcast pripraven
        if($this->video && $this->video->ready && $this->presentation->ready){
            // Prenastavime targety synchronizace
            $this->content->syncpoints_vid->setTarget($this->presentation->playerHtmlElemId);
            $this->content->syncpoints_pres->setTarget($this->video->playerHtmlElemId);
            // Pripravime konfigurace
            $this->video->video->config->syncpoints = $this->syncpoints_vid;
            $this->video->video->config->getXml($this->content->getPathToFile($this->content->configFileVideo, false));
            $this->presentation->config->syncpoints = $this->syncpoints_pres;
            $this->presentation->config->getXml($this->content->getPathToFile($this->content->configFilePres, false));

            $obj->ready = true;
        }
        else{
            $obj->ready = false;
        }

        try{
            Contents::edit($id, $obj, $author_sel);
        }
        catch(Exception $e){
            $ok = false;
            $this->infoMessage('Při změně údajů obsahu nastala chyba. Údaje obsahu pravděpodobně nebyly změněny.');
            Phc_ErrorLog::warning('Admin_WebcastController', "Udaje contentu se nepodarilo zmenit. content_id='".$id
                ."' Puvodni vyjimka:\n$e");
        }

        // Zavolame zpracovani zavislych contentu
        $this->content->afterSave();

        if($ok && isset($data['save_content'])){
            $this->infoMessage('Údaje byly změněny.');
            unset($data['save_content']);
        }

        // Pro poradek vratime zase subcontenty do objektu contentu
        $obj->video = $this->video;
        $obj->presentation = $this->presentation;

        return $data;
    }

    /**
     * Ziska seznam existujicich contentu videa, prezentace, clanku nebo diskuze pro
     * vytvoreni tabulky pro prirazeni daneho contentu do aktualniho webcastu.
     *
     * @param string $type    'video' / 'presentation' / 'article' / 'opinions' / 'articleonly'
     * @throws Ibulletin_Admin_ContentController_Exception  Pri nespravne zadanem $type
     */
    public function getContentList($type)
    {
        switch($type){
            case 'presentation':
                $classes = array('Ibulletin_Content_Presentation');
                break;
            case 'video':
                $classes = array('Ibulletin_Content_Video');
                break;
            case 'article':
                $classes = array('Ibulletin_Content_Article', 'Ibulletin_Content_Questionnaire');
                break;
            case 'articleonly':
                $classes = array('Ibulletin_Content_Article');
                break;
            case 'opinions':
                $classes = array('Ibulletin_Content_Opinions');
                break;
            default:
                throw new Ibulletin_Admin_ContentController_Exception(
                    'Nespravne zadaby parametr $type. Zadano: '.$type);
                break;
        }

        // Ziskame seznam contentuu
        $list = array();
        foreach($classes as $class){
            $listPom = Contents::getList($class);
            $list = array_merge($list, $listPom);
        }
        return $list;
    }

    /**
     * Cinnosti, ktere se maji provest pri vytvoreni noveho contentu
     *
     * @param Ibulletin_Content_Abstract $obj    Trida contentu k inicializaci.
     */
    public function initializeContent($obj)
    {
        $config = Zend_Registry::get('config');

        $obj->html[0] = array();

    }

    /**
     * Pripravi do view data pro renderovani tabulek se syncpointy
     */
    public function prepareSyncpointsData()
    {
        $js = Ibulletin_Js::getInstance(); // Kvuli predavani promennych do JS

        $video = $this->syncpoints_vid->getSyncpoints();
        $presentation = $this->syncpoints_pres->getSyncpoints();

        $js->vars->syncVideoTimes = array_keys($video);
        $js->vars->syncVideoSlides = $video;

        $js->vars->syncPresTimes = array_keys($presentation);
        $js->vars->syncPresSlides = $presentation;

        // Texty
        $js->vars->butRemoveText = 'Odstranit';

        // Seradime
        ksort($video);
        ksort($presentation);

        // Ulozime do pole mimo cisla slidu jeste cas v textu
        $videoNew = array();
        foreach($video as $time => $slide){
            $videoNew[] = array('time' => $this->formatMs($time), 'timems' => $time, 'slide' => $slide);
        }
        $presentationNew = array();
        foreach($presentation as $slide => $time){
            $presentationNew[] = array('time' => $this->formatMs($time), 'timems' => $time, 'slide' => $slide);
        }

        $this->view->videoSyncTable = $videoNew;
        $this->view->presSyncTable = $presentationNew;
    }

    /**
     * Vrati cas v textu jako minuty, vteriny a ms jako tisiciny vterin.
     * @param int $time Poct ms.
     * @return string Naformatovany cas m:ss.sss
     */
    public function formatMs($time)
    {
        $mins = floor($time / 1000 / 60);
        $secs = round($time / 1000, 3) - $mins * 60;
        $str = sprintf('%d:%06.3f', $mins, $secs);

        return $str;
    }

    /**
     * Akce odebere video nebo prezentaci z webcastu
     * Musi byt v URL predan parametr 'contentId' s ID contentu k odebrani
     * dale musi byt predano ID aktualniho contentu
     */
    public function removesubcontentAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);

        $id = $this->getRequest()->getParam('id', null);
        $forRemove = $this->getRequest()->getParam('contentId', null);

        // Nacte objekt Contentu do $this->content.
        $data = $this->getContentData($id);

        if($forRemove === null){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoRouteAndExit(array('action' => 'edit', 'contentId' => null));
        }

        // Odebereme content
        if($forRemove == $this->content->video_id){
            $this->video = null;
            $this->content->video_id = null;

            // Odebereme se ze seznamu zavislych contentu v odebiranem contentu
            $forRemoveCont = Contents::get($forRemove);
            $forRemoveCont['object']->unsubscribeForChanges($id);
            Contents::edit($forRemove, $forRemoveCont['object']);
        }
        elseif($forRemove == $this->content->presentation_id){
            $this->presentation = null;
            $this->content->presentation_id = null;

            // Odebereme se ze seznamu zavislych contentu v odebiranem contentu
            $forRemoveCont = Contents::get($forRemove);
            $forRemoveCont['object']->unsubscribeForChanges($id);
            Contents::edit($forRemove, $forRemoveCont['object']);
        }
        // Jedna se o content obsazeny v page tohoto contentu
        else{
            $pages = Pages::getContentPages($id);
            if(!empty($pages)){
                $page = $pages[0];
                Pages::removeContent($page['id'], $forRemove);
            }
        }

        // Ulozit
        $this->saveContentData($data);

        // Redirect na edit
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit', 'contentId' => null));
    }

    /**
     * Akce prida video nebo prezentaci do webcastu
     * Musi byt v URL predan parametr contentId s ID contentu k pridani
     * dale musi byt predano ID aktualniho contentu
     */
    public function addsubcontentAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);

        $id = $this->getRequest()->getParam('id', null);
        $forAdd = $this->getRequest()->getParam('contentId', null);

        // Nacte objekt naseho Contentu do $this->content.
        $data = $this->getContentData($id);

        // Nacteme content
        $newCont = Contents::get($forAdd);

        if(!$newCont){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoRouteAndExit(array('action' => 'edit', 'contentId' => null));
        }

        if($newCont['class_name'] == 'Ibulletin_Content_Video'){
            $this->content->video_id = $forAdd;

            // Pridame tento se na seznam zavislych contentu v novem contentu
            $newCont['object']->subscribeForChanges($id);
            Contents::edit($forAdd, $newCont['object']);
        }
        elseif($newCont['class_name'] == 'Ibulletin_Content_Presentation'){
            $this->content->presentation_id = $forAdd;

            // Pridame tento se na seznam zavislych contentu v novem contentu
            $newCont['object']->subscribeForChanges($id);
            Contents::edit($forAdd, $newCont['object']);
        }
        // Chceme pridat content do teto page
        else{
            $pages = Pages::getContentPages($id);
            $position = 4;
            if(!empty($pages)){
                // Vymyslime si pozici
                if($newCont['object'] instanceof Ibulletin_Content_Opinions){
                    $position = 2;
                }
                elseif($newCont['object'] instanceof Ibulletin_Content_Article || 
                    $newCont['object'] instanceof Ibulletin_Content_Questionnaire)
                {
                    $position = 3;
                }

                $page = $pages[0];
                try{
                    Pages::addContent($page['id'], $forAdd, $position);
                }
                catch(Pages_Exception $e){
                    if($e->getCode() == 1){
                        Phc_ErrorLog::error('Ibulletin_Admin_WebcastController::addsubcontentAction',
                            "Pokus o pridani contentu nebo page, ktera neexistuje. Puvodni vyjimka:\n".
                            $e);
                    }
                }
            }
        }


        // Ulozit
        $this->saveContentData($data);

        // Redirect na edit
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit', 'contentId' => null));
    }

    /**
     * Zmeni konfiguraci syncpointu a ulozi.
     * Je nutne predat parametr 'id' s id tohoto contentu a pramater 'media' = 'pres'/'vid'
     * dale pak parametry:
     * - pro pridani novy cas 'time' a 'slide'
     * - pro presunuti 'time', 'slide', 'timeold', 'slideold'
     * - pro odebrani 'timeold', 'slideold'
     */
    public function manipulatesyncAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);

        $req = $this->getRequest();

        $id = $req->getParam('id', null);
        $media = $req->getParam('media', null);
        $time = $req->getParam('time', null);
        $slide = $req->getParam('slide', null);
        $timeold = $req->getParam('timeold', null);
        $slideold = $req->getParam('slideold', null);

        if(!$id || !$media){
            return;
        }
        
        // Nacte objekt Contentu do $this->content.
        $data = $this->getContentData($id);
        
        // Pro pripad nejakeho problemu nastavime implicitne typy media - mohlo by se stat, 
        // ze se v contentu nejak ulozi content bez nastaveneho media
        $this->syncpoints_vid->setMedia('vid');
        $this->syncpoints_pres->setMedia('pres');
        
        if($media == 'vid'){
            $addToObj = $this->syncpoints_vid;
        }
        elseif($media == 'pres'){
            $addToObj = $this->syncpoints_pres;
        }
        elseif($media == 'both' && $time !== null && $slide !== null && $timeold === null && $slideold === null){
            $addToObj = $this->syncpoints_vid;
        }
        else{
            return;
        }
        
        // Vkladame novy
        if($time !== null && $slide !== null && $timeold === null && $slideold === null){
            $addToObj->addSyncpoint($time, $slide);
            if($media == 'both'){ // Pridavame k obema smerum
                $this->syncpoints_pres->addSyncpoint($time, $slide);
            }
        }
        // Presouvame
        elseif($time !== null && $slide !== null && $timeold === null && $slideold === null){
            $addToObj->removeSyncpoint($timeold, $slideold);
            $addToObj->addSyncpoint($time, $slide);
        }
        // Odstranime
        elseif($time === null && $slide === null && $timeold !== null && $slideold !== null){
            //echo "$timeold $slideold";
            $addToObj->removeSyncpoint($timeold, $slideold);
        }

        
        // Ulozit
        $this->saveContentData($data);
    }

    /**
     * Vytvori prostou page pro webcast
     */
    public function createsimplepageAction(){
        $db = Zend_Registry::get('db');

        $id = $this->getRequest()->getParam('id', null);

        // Ziskame jmeno pro page ze zaznamu contentu
        $cont_data = Contents::get($id);
        $name = $cont_data['object']->getName();

        // Pridame zaznam do pages
        $page_id = Pages::add($this->main_tpl_file_name, $name);

        // Pridame zaznam do links
        $ins_data = array('page_id' => $page_id,
                          'name' => $name);
        $db->insert('links', $ins_data);

        // Pridame zaznam do content_pages
        Pages::addContent($page_id, $id, 1);

        // Presmerujeme na index
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => null));
    }

    /**
     * Slouzi k vybrani contentu, ktery bude slouzit jako hlavni stranka.
     */
    public function selectmainpageAction()
    {
        $req = $this->getRequest();
        $this->setActionTitle('Výběr contentu pro hlavní stránku');

        // Pokud byla odeslana data, provedeme ulozeni
        $id = $req->getParam('id', null);
        if($id !== null){
            Config::set('mainPageContent', $id);
            $this->infoMessage('Obsah byl nastaven pro hlavní stránku.');
        }

        $contentList = $this->getContentList('articleonly');
        $currentMainContent = Config::get('mainPageContent');

        $this->view->contentList = $contentList;
        $this->view->currentMainContent = $currentMainContent;
    }
}