<?php

/**
 * Modul pro spravu videi.
 *
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


class Admin_VideoController extends Ibulletin_Admin_ContentControllerAbstract
{
    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    var $contentType = 'Ibulletin_Content_Video';
    
    /**
     * @var Ibulletin_Content_Abstract Objekt contentu, ktery prave editujeme, pokud jsme v akci edit.
     */
    var $content = null;
    
    
    /**
     * @var Ibulletin_Video     Objekt videa
     */
    var $video = null;

    /**
     * @var Vimeo   Objekt vimeo API
     */
    var $vimeo_api = null;
    
    /**
     * Fileuploaderu - vyuzivaji se funkce z puvodniho fileuploaderu
     */
    var $fileUploader;

    /**
     * 
     * @return 
     */
    public function init() {
        parent::init();

        $this->vimeo_api = new Vimeo($this->config->video->vimeo_client_id,
            $this->config->video->vimeo_client_secret,
            $this->config->video->vimeo_access_token);

        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => false)
        );

    }
    
    /**
     * Editace contentu
     */
    public function editAction(){
        $config = Zend_Registry::get('config');
        $id = $this->getRequest()->getParam('id', null);
        Ibulletin_Js::addJsFile('admin/collapse.js');
        
        //bootstrap_tokenfield pro labely
        Ibulletin_Js::addJsFile('bootstrap_tokenfield/bootstrap-tokenfield.min.js');
        Ibulletin_HtmlHead::addFile('../scripts/bootstrap_tokenfield/bootstrap-tokenfield.css');
                
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit'); 
        $this->moduleMenu->setCurrentLocation('edit');
        
        $urlHlpr = new Zend_View_Helper_Url();
        $this->contentId = $id;
        $sheet = $this->getRequest()->getParam('sheet', 1);
        
        $data = $this->getContentData($id);
        $form = $this->getEditContentForm($data);
        
        // Overeni a pripadne predzpracovani videa, ulozi do $this->video objekt videa
        $videoReady = $this->checkVideo(true); // silent pri vimeo videich
        
        // Pokud byla vyplnena data, provedeme ulozeni a aktualizujeme
        // data z ulozeni.
        if(isset($data['save_content']) && $form->isValid($data)){
            $data = $this->saveContentData($data);
            $form = $this->getEditContentForm($data);
        }
        
        // Formular pro editaci dat
        $this->view->form = $form;

        // Videoprehravace
        $this->view->vimeo_id = $data['vimeo_id'];
        $this->view->videoReady = $videoReady;

        if($videoReady){
            // Pripravime do stranky JS prehravacu a akce s tim spojene
            $confUrl = $this->content->getPathToFile($this->content->configFileName);
            //*
            Ibulletin_Player_Js::prepareAvPlayer('plainAvPlayer', 
                                                 $this->content->getPlayerPath(),
                                                 $urlHlpr->url(array('action' => 'plainxmlconfig'))
                                                 );
            //*/
            Ibulletin_Player_Js::prepareAvPlayer('fullAvPlayer', 
                                                 $this->content->getPlayerPath(),
                                                 $confUrl.'?time='.time()
                                                 );
            $js = Ibulletin_Js::getInstance();
            $js->addJsFile('Player/videoadmin.js');
            
            // Url pro nastaveni snimku preview a znovunacteni konfigurace 
            $js->vars->setPreviewPictureUrl = $urlHlpr->url(array('action' => 'setpreviewpicture'));
            $js->vars->xmlConfigUrl = $confUrl;
            
            // Data pro pridavani a odebirani embeded swf
            $this->view->embedsTableData = $this->getEmbedTableData($id, $data);
            $js->vars->manipulateEmbedUrl = $urlHlpr->url(array('action' => 'manipulateembed'));
            $js->vars->swfMoveButText = $this->texts->action_swfmove;
            $js->vars->swfRemoveButText = $this->texts->action_swfdelete;
            $js->vars->setPreviewPictureConfirmMessage = $this->texts->confirm_preview;
        }
        
         //doplni do context menu je-li treba page id a bulletin id
         $this->view->contextMenu = $this->prepareContentContext($id);  
                
        // Titulek modulu
        $this->setActionTitle('"'.$data['name'].'"');
    }
    
    
    /**
     * Ziska a vrati data contentu - pokud byl odeslan formular, 
     * jsou vracena data z formulare.
     *
     * @param int       ID contentu
     * @return array    Pole obsahujici data contentu
     */
    public function getContentData($id)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        
        
        // Ziskame data contentu
        $data = Contents::get($id);
        if(!$data){
            $this->infoMessage($this->texts->edit->notfound, 'error', array($id));
            $this->redirect('');
        }
        // Nastavime objekt contentu do atributu content a videa do video
        $this->content = $data['object'];
        $this->video = $data['object']->video;
        $data['name'] = $this->content->name;
        $data['labels'] = $this->content->labels;
        $data['data_annotation'] = $this->content->annotation;
        $data['data_author'] = $this->content->getAuthor();
        $data['vimeo_id'] = $this->content->vimeo_id ?: null;
        $data['vimeo_file'] = $this->content->vimeo_file ?: null;
        $data['quality1080p'] = $this->content->quality1080p ?: false;

        // Navigace na konci videa - v pripade ze neni nastaveno, nastavujeme podle configu
        if(empty($_POST['save_content'])){
            if(($this->content->addVideoNavigation === null && $config->video->addVideoNavigationDefault) 
                || $this->content->addVideoNavigation)
            {
                $data['data_addVideoNavigation'] = true;
            }
            if(($this->content->addVideoForward === null && $config->video->addVideoForwardDefault) 
                || $this->content->addVideoForward)
            {
                $data['data_addVideoForward'] = true;
            }
            if(($this->content->addPlayAgain === null && $config->video->addPlayAgainDefault) 
                || $this->content->addPlayAgain)
            {
                $data['data_addPlayAgain'] = true;
            }
        }
        
        
        // Pokud neexistuje objekt videa Ibulletin_Video, vytvorime jej.
        if(empty($this->content->video)){
            $this->initializeContent($this->content);
        }
        // Pokud chybi info o delce videa, znovu nacteme
        if(empty($this->video->duration) && !empty($this->video)){
            $this->infoMessage($this->texts->reloaded,'info',array($this->video->duration));
            $this->video->loadFlvInfo();
        }
        
        
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
        $authors_sel = array('default' => $this->texts->author_new, 'none' => $this->texts->author_none);
        foreach ($authorsA as $var) {
            $authors_sel[$var['id']] = $var['name'];
        }
        
        // Vytvorime formular
        $form = new Form();
        $form->setMethod('post');
        
        $form->addElement('hidden', 'sheet');
        
        $form->addElement('text', 'id', array(
            'label' => $this->texts->id,
            'readonly' => 'readonly',
            'class' => 'span1'
        ));

        $vimeo_file =  new Zend_Form_Element_Select(array(
            'name' => 'vimeo_file',
            'multioptions' => array('' => $this->texts->none_selected)+ $this->getVideoFileList(),
            'label' => $this->texts->vimeo_file,
            'filters' => array('Null')
        ));
        $form->addElement($vimeo_file);
        
        $form->addElement('text', 'name', array(
            'label' => $this->texts->name,
            'autofocus' => 'autofocus'
        ));

        
        // Autor select
        $author_sel = new Zend_Form_Element_Select(array(
            'name' => 'data_author',
            'label' => $this->texts->author,
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
            'label' => $this->texts->new_author
            ));
        $form->addElement($author);
        
        $form->addDisplayGroup(array($author_sel,$author),
                        'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));
        
        // Labels
        $labels = new Zend_Form_Element_Text(array(
            'name' => 'labels',
            'label' => $this->texts->labels,
            'class' => 'span6'
        ));
        $form->addElement($labels);
        
        // Anotace
        $annotation = new Zend_Form_Element_Textarea(array(
            'name' => 'data_annotation',
            'label' => $this->texts->annotation
            ));
        $annotation->setAttrib('class', 'editarea');
        $form->addElement($annotation);

        $vimeo_id =  new Zend_Form_Element_Text(array(
            'name' => 'vimeo_id',
            'label' => $this->texts->vimeo_id,
            'filters' => array('Null')
        ));
        $form->addElement($vimeo_id);

        $quality1080p =  new Zend_Form_Element_Checkbox(array(
            'name' => 'quality1080p',
            'label' => $this->texts->quality1080p,
            'filters' => array('Boolean')
        ));
        $form->addElement($quality1080p);
        
        // Navigace na konci videa
        $form->addElement('checkbox', 'data_addVideoNavigation', array(
            'label' => $this->texts->addnavigation,
        ));
        $form->addElement('checkbox', 'data_addVideoForward', array(
            'label' => $this->texts->addforward,
        ));
        $form->addElement('checkbox', 'data_addPlayAgain', array(
            'label' => $this->texts->addreplay,
        ));

        // Ulozit
        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'save_content',
                'label' => $this->texts->submit,
                'class' => 'btn-primary'
            )));
        
        // Validacii vyplnime formular z pole
        $form->isValid($data);
        
        $links = new Links();
        $annotation->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
     

        return $form;
    }

    /**
     * get list of valid video files for uploading to vimeo
     *
     * @return array
     */
    public function getVideoFileList(){
        $res = array();
        foreach ($this->fileUploader->getDirectoryIterator() as $file) {

            if(!$file->isFile()) continue;
            // BC: stop if video.flv is found
            if (stristr('video.flv', $file->getFilename()) !== false) break;
            // mime type 'video'
            $type = Utils::getMimeType($this->fileUploader->getBasePath() . $file->getFilename());
            if (strpos($type, 'video') === false && strpos($type, 'application/octet-stream') === false) continue;
            // file for upload
            $res[$file->getFilename()] = $file->getFilename();
        }
        return $res;
    }

    /**
     * Ulozi data prijata z editacniho formulare contentu.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveContentData($data)
    {
        /*
        if(!isset($data['save_content'])){
            // Neni co ukladat, data nebyla odeslana
            return $data;
        }
        //*/
        
        $db = Zend_Registry::get('db');

        $ok = true; // Flag, jestli se vse ulozilo OK
        
        $id = $data['id'];        
        
        $form = $this->getEditContentForm($data);

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
        $obj->annotation = $data['data_annotation'];
        $obj->quality1080p = (boolean)$data['quality1080p'];
        $obj->video = $this->video;
        
        $obj->labels = $data['labels'];

      # Pripravime a ulozime autora
        $author_sel = $data['data_author'];
        if(!empty($data['data_author_new'])){
            $author_new = $data['data_author_new'];
        }
        else{
            $author_new = null;
        }
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
        elseif($author_row){
            $data['author_id'] = $author_sel;
            $data['data_author'] = $author_sel;
            $obj->setAuthor($author_row[0]['name']);
        }
        $data['data_author_new'] = '';
        
        
        // Navigace ve videu
        $obj->addVideoNavigation = (bool)$form->getValue('data_addVideoNavigation');
        $obj->addVideoForward = (bool)$form->getValue('data_addVideoForward');
        $obj->addPlayAgain = (bool)$form->getValue('data_addPlayAgain');
        
      # Nastaveni parametruu ve videu a ulozeni konfigurace videa
        if(file_exists($this->content->getPathToFile($this->content->videoFileName, false))){
            if(!empty($this->video)){
                $this->video->config->id = $this->content->id;
                $this->video->config->source = $obj->getVideoUrl();
                // Ulozit preview pict
                $previewFileUrl = $this->content->getPathToFile($this->content->previewPicFileName, true);
                if(file_exists($this->content->getPathToFile($this->content->previewPicFileName, false))){
                    $this->video->config->preview = $previewFileUrl;
                }
                else{
                    $this->video->config->preview = null;
                }
                // Ulozit XML config
                $this->video->getPlayerConfigXml(false, 
                    $this->content->getPathToFile($this->content->configFileName, false));
                
                // Video je mozne prehravat
                $obj->ready = true;
            }
            else{
                // Video neni mozne prehravat
                $obj->ready = false;
            }
        }
        elseif(!empty($this->video)){
            $this->video = null;
            unset($obj->video);
        }

        /*
        // Vimeo integration code for uploading video
        */

        // no inbox local video
        if (empty($this->video)) {

            // GET VIMEO_ID
            // update metadata from vimeo video by given form field vimeo_id
            if (empty($obj->vimeo_worker) && // no vimeo worker is running
                !empty($data['vimeo_id']) && empty($obj->vimeo_id) && empty($obj->vimeo_file)) {

                // check existence of provided vimeo_id
                $response = $this->vimeo_api->request('/videos/'. $data['vimeo_id']);
                if ($response['status'] == 404) {
                    $ok = false;
                    Phc_ErrorLog::warning('Admin_VideoController', "Nepodaril se update metadat pro vimeo_id: ".$data['vimeo_id']);
                    $this->infoMessage($this->texts->invalid_vimeo_id, 'warning',array($data['vimeo_id']));
                } else { // vimeo video exists
                    // update object with metadata
                    $obj->name = $data['name'] = $response['body']['name'];
                    $obj->annotation = $data['data_annotation'] = $response['body']['description'];
                    // store new vimeo_id for content
                    $obj->vimeo_id = $data['vimeo_id'];
                    $obj->vimeo_video = $response;
                    $obj->tpl_name_schema = 'vimeo_%d.phtml';
                }
            }
            // OR UPDATE METADATA
            // update vimeo video with object data
            elseif (!empty($obj->vimeo_id)) {
                // check existence of content vimeo_id (video was deleted on Vimeo side)
                $response = $this->vimeo_api->request('/videos/'.$obj->vimeo_id);
                if ($response['status'] == 404) {
                    $ok = false;
                    if (empty($obj->vimeo_worker)) {
                        Phc_ErrorLog::warning('Admin_VideoController', "Vimeo video deleted, vimeo_id: ".$obj->vimeo_id);
                        $this->infoMessage($this->texts->invalid_vimeo_id, 'warning', array($obj->vimeo_id));

                        $obj->vimeo_id = $data['vimeo_id'] = null;
                        $obj->vimeo_file = $data['vimeo_file'] = null;
                    }

                } else {

                    $response = $this->vimeo_api->setVideoMetadata('/videos/'.$obj->vimeo_id, $obj->name, $obj->annotation);
                    if ($response['status'] == 404) {
                        $ok = false;
                        Phc_ErrorLog::warning('Admin_VideoController', "Nepodaril se update metadat pro vimeo_id: ".$obj->vimeo_id);
                        $this->infoMessage($this->texts->metadata_notsaved, 'warning');
                    } else {
                        // update vimeo object
                        $obj->vimeo_video = $response;
                        $this->infoMessage($this->texts->metadata_saved, 'success');
                    }

                    // update form with object vimeo_id
                    $data['vimeo_id'] = $obj->vimeo_id;
                }

            }

            // REPLACE FILE
            // check if we are initiating replace of vimeo source file
            $do_replace = false;
            $do_upload = false;

            if (empty($obj->vimeo_worker) &&
                !empty($obj->vimeo_id) && !empty($obj->vimeo_file)  && !empty($data['vimeo_file']) &&
                ($data['vimeo_file'] != $obj->vimeo_file || // file has changed or last modified time of file has changed
                    $obj->vimeo_file_lastmodified != (filemtime(($this->fileUploader->getBasePath() . $data['vimeo_file']))))) {

                // update obbject with new filename (can be the same)
                $obj->vimeo_file = $data['vimeo_file'];
                $obj->vimeo_file_lastmodified = filemtime($this->fileUploader->getBasePath() . $data['vimeo_file']);

                $do_replace = true;
            }
            // OR UPLOAD FILE
            // check if we are initiating upload for the first time and
            // no vimeo worker scripts are running
            elseif (empty($obj->vimeo_worker) && empty($obj->vimeo_id) &&
                    empty($obj->vimeo_file)   && !empty($data['vimeo_file']) ) {

                $fullpath = $this->fileUploader->getBasePath().$data['vimeo_file'];
                // update object vimeo_file and last modified time
                $obj->vimeo_file = $data['vimeo_file'];
                $obj->vimeo_file_lastmodified = filemtime($fullpath);

                $do_upload = true;
            }

            // update form video file
            if (!empty($obj->vimeo_file)) {
                $data['vimeo_file'] = $obj->vimeo_file;
            }

        }

        // update content object
        try{
            Contents::edit($id, $obj, $author_sel);
        }
        catch(Exception $e){
            $ok = false;
            $this->infoMessage(IBulletin_Texts::get('notsaved'),'error');
            Phc_ErrorLog::warning('Admin_VideoController', "Udaje contentu se nepodarilo zmenit. content_id='".$id
                ."' Puvodni vyjimka:\n$e");
        }

        if ($do_replace) {
            sleep(1);
            // initiate background replace process with new filename and content id
            $cmd = 'utils/vimeo_worker.php -t replace -i '. $id;

            $obj->vimeo_worker = new Process($cmd);
            Contents::edit($id, $obj);

        } elseif ($do_upload) {
            sleep(1);
            // initiate background upload process with filename and content id
            // worker MUST clean object vimeo_worker or client can kill worker using UI
            $cmd = 'utils/vimeo_worker.php -t upload  -i '. $id;

            $obj->vimeo_worker = new Process($cmd);
            Contents::edit($id, $obj);
        }

        // Zavolame zpracovani zavislych contentu
        $this->content->afterSave();

        if($ok && isset($data['save_content'])){
            $this->infoMessage(IBulletin_Texts::get('saved'));
            // POST REDIRECT GET
            $this->redirect('edit', null, null, array('id' => $id));
            unset($data['save_content']);
        }
        
        return $data;
    }

    /**
     * AJAX service for gathering vimeo upload script data in JSON
     * @return JSON
     */
    public function killworkerAction() {

        $this->_helper->viewRenderer->setNoRender(true);

        $content_id = $this->_request->getParam('id');
        $content = Contents::get($content_id);
        /** @var Ibulletin_Content_Video $obj */
        $obj = $content['object'];
        if ($obj->vimeo_worker) {
            if ($obj->vimeo_worker->status()) {
                $obj->vimeo_worker->stop();
            }
        }
        $f = $this->fileUploader->getBasePath() .$obj->vimeo_file.'.vimeo';
        if (file_exists($f)) {
            unlink($f);
        }

        // finally video is ready and we can clear worker and update object
        $obj->vimeo_worker = null;
        $obj->vimeo_id = null;
        $obj->vimeo_file = null;
        Contents::edit($obj->id, $obj);

        $this->redirect(array('action' => 'edit', 'id'=> $content_id));

    }

    /**
     * AJAX service for gathering vimeo upload script data in JSON
     * @return JSON
     */
    public function vimeostateAction() {

        $this->_helper->viewRenderer->setNoRender(true);

        $content_id = $this->_request->getParam('id');
        $content = Contents::get($content_id);
        $obj = $content['object'];
        $fname = $this->fileUploader->getBasePath().$obj->vimeo_file.'.vimeo';

        if ($obj->vimeo_worker && file_exists($fname)) {
            echo file_get_contents($fname);
        } else echo '{}';

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
        if($this->checkVideo(true)){
            $obj->video = new Ibulletin_Video($config->content_article->basepath.
                "/$obj->id/".$this->content->videoFileName);
        }
    }
    
    /**
     * Najde vsechny soubory swf, ktere jsou vlozeny ve videu nebo mohou byt vlozeny do videa
     * a pripravi data pro vyrenderovani ovladacich prvku.
     * 
     * @param int $id       Id contentu.
     * @param array $data   Data tohoto contentu
     * @return array        Data pro vyrenderovani seznamu vlozenych souboru. array('embeds', 'files')
     */
    public function getEmbedTableData($id, $data)
    {
		//$files = $this->getContentFiles($id);
		$files = $this->fileUploader->getDirectoryIterator();
		        
        $embeds = $this->video->config->getEmbeds();
        
        // Sem si odlozime embedy, ktere maji existujici soubory
        $embedsFiles = array();
        $filesSend = array();
        $sort = array();
        
        // Vyfiltrujeme jen soubory s priponou swf
        // a najdeme ke kazdemu souboru jeho pozici, pokud nejakou ma.
        
		foreach($files as $key => $item){
            
			if ($item->isFile()) {
                $file = substr($item->getPathname(),strlen($this->fileUploader->getBasePath()));            	
				if (!preg_match('/\.swf$/i', $file)) continue;				 				
			} else { continue; }
			            
            // Pridame do filesSend i s url k souboru
            $source = $this->content->getPathToFile($file);
            $filesSend[] = array('source' => $source, 'file' => $file);
            
            // Najdeme, jestli ma pozici
            foreach($embeds as $embKey => $embed){
                if(preg_match('/'.str_replace('.', '\.', $file).'$/', $embed['source'])){
                    $embedNew = $embed;
                    $embedNew['file'] = $file;
                    $embedsFiles[$embKey] = $embedNew;
                    $sort[$embKey] = $embed['time'];
                }
            }
        }
        
        // Smazeme z konfigurace embedy, ktere nemaji soubor
        foreach($embeds as $key => $val){
            if(!isset($embedsFiles[$key])){
                $this->video->config->removeEmbed($key);
            }
        }
        
        // Dohledame jmena pripojenych dotazniku
        foreach($embedsFiles as $key => $embed){
            if($embed['questionnaireid']){
                $embedsFiles[$key]['questionnaire'] = Contents::get($embed['questionnaireid']);
            }
            else{
                $embedsFiles[$key]['questionnaire'] = null;
            }
        }
        
        // Naformatujeme cas a seradime prvky podle casu
        asort($sort);
        $embedsFilesSort = array();
        foreach($sort as $key => $time){
            $mins = floor($time / 1000 / 60);
            $secs = round($time / 1000, 3) - $mins * 60;
            
            $embedsFilesSort[$key] = $embedsFiles[$key];
            $embedsFilesSort[$key]['time'] = sprintf('%d:%06.3f', $mins, $secs);
        }
        
        // Seznam questionnaires
        $questionnairesList = Contents::getList('Ibulletin_Content_Questionnaire');
        
        // Projistotu ulozime i tady - kvuli odebranym embedum
        $this->saveContentData($data);
        
        return array('embeds' => $embedsFilesSort, 'files' => $filesSend, 'questionnaires' => $questionnairesList);
    }
    
    
    /**
     * Overi existenci videa, zkontroluje pritomnost metadat a pripadne prida metadata.
     * Informace o akcich se pridavaji pomoci infoMessage(). Prida video do atributu $this->video
     * 
     * @param bool $silent  Nevypisovat hlasky?
     * @return bool     Je video pripravene k pouziti?
     */
    public function checkVideo($silent = false)
    {
        $config = Zend_Registry::get('config');
        $id = $this->contentId;
        

        $videoFile = $config->content_article->basepath."/$id/".$this->content->videoFileName;
        if(file_exists($videoFile)){
            if(empty($this->content->video)){
                $video = new Ibulletin_Video($videoFile);
            }
            else{
                $video = $this->content->video;
            }
            $this->video = $video;
        }
        else{
            // Video neexituje, ohlasime a konec
            if(!$silent){
                $this->infoMessage($this->texts->missingvideo,'warning',array($this->content->videoFileName));
            }
            return false;
        }
        
        // Zkontrolujeme a pripadne nechame regenerovat metadata
        if(!$video->hasKeyframesMetadata()){
            try{
                $video->regenerateMetadata();
                if(!$silent){
                    $this->infoMessage($this->texts->metadata_saved);
                }
            }
            catch(Ibulletin_Video_Regenrate_Metadata_Exception $e){
                return false;
                if(!$silent){
                    $this->infoMessage($this->texts->metadata_notsaved,'error');
                }
            }
        }
        
        return true;
        
        
        /*
        $videoFile = $config->content_article->basepath."/$id/video.flv";
        if(file_exists($videoFile)){
            
            // Ziskame metadata videa
            $flvinfo = new Phc_Flvinfo();
            $videoMetadata = $flvinfo->getInfo($videoFile);
            
            // Kontrola pritomnosti informaci o pozicich keyframes, pripadne oindexovani
            if(!isset($videoMetadata->rawMeta) || !isset($videoMetadata->rawMeta[1]['keyframes'])
                || empty($videoMetadata->rawMeta[1]['keyframes']->filepositions))
            {
                // Vygenerujeme soubor videa i s metadaty
                $ok = true;
                $tmpfile = tempnam('4378dsjkdj832jskdj', 'tmp_'); // Neexistujici adresar, aby se to hodilo to tempu
                $returnVar = null;
                $exeoutput = array();
                $command = "yamdi -i $videoFile -o $tmpfile";
                // Na serverech mame yamdi podivne umisten
                if(file_exists('/opt/yamdi/yamdi')){
                    $command = '/opt/yamdi/'.$command;
                }
                exec($command, $exeoutput, $returnVar);
                
                // Presuneme soubor misto puvodniho
                if($returnVar === 0){
                    if(unlink($videoFile)){
                        if(!rename($tmpfile, $videoFile)){
                            Phc_ErrorLog::error('VideoController::editAction', 'Nepodarilo se presunout '.
                                'soubor s oindexovanym videem -  '.$tmpfile.'. Content_id: "'.$id.'"');
                            $ok = false;
                        }
                        else{
                            chmod($videoFile, '0777');
                        }
                    }
                    else{
                        $ok = false;
                        Phc_ErrorLog::error('VideoController::editAction', 'Nepodarilo se smazat '.
                            'puvodni soubor videa -  '.$videoFile.
                            '. Content_id: "'.$id.'"');
                    }
                }
                else{
                    Phc_ErrorLog::error('VideoController::editAction', 'Nepodarilo se vykonat '.
                            'yamdi pro oindexovani videa -  '.$videoFile.'. Content_id: "'.$id.'", '.
                            'vystup yamdi: "'.join("\n",$exeoutput).'"');
                    $ok = false;
                }
                if($ok){
                    $this->infoMessage('Videu byla úspěšně přidána metadata.');
                }
                else{
                    $this->infoMessage('Videu se nepodařilo přidat metadata.');
                }
            }
            
        }
        // Kontrola, jestli existuje soubor videa
        else{
            $this->infoMessage('Není nahrán soubor videa - "video.flv".');
        }
        //*/
    }
    
    /**
     * Vrati primo cistou XML konfiguraci prehravace bez vlozenych veci a synchronizace - plain video.
     * Musi byt zadano ID v URL.
     */
    public function plainxmlconfigAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);
        
        $id = $this->getRequest()->getParam('id', null);
        // Nacte objekt Contentu do $this->content.
        $data = $this->getContentData($id);
        
        echo $this->video->getPlayerConfigXml(true);
    }
    
    /**
     * Vytvori z prijate pozice videa obrazek a ulozi ho k pouziti jako preview picture videa
     * Vyzaduje zadany paramter URL id s ID contentu.
     */
    public function setpreviewpictureAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);
        
        $id = $this->getRequest()->getParam('id', null);
        $pos = $this->getRequest()->getParam('pos', 1000);
        // Nacte objekt Contentu do $this->content.
        $data = $this->getContentData($id);
        
        if(!$data){
            return;
        }
        
        $previewFile = $this->content->getPathToFile($this->content->previewPicFileName, false);
        $this->video->getPicture($pos, null, null, 'jpeg', $previewFile);
        
        $this->saveContentData($data);
        
        // Primo na vystup vypiseme hlasku
        $this->infoMessage($this->texts->preview_saved);
    }
    
    /**
     * Vlozi, presune nebo odebere embed na/z pozadovane misto ve videu a ulozi konfiguraci.
     * Predavame:
     * - ID vzdy!
     * - jmeno souboru a cas pokud se jedna o vlozeni,
     * - cas a klic pro presunuti,
     * - klic pro smazani
     * - ID questionnaire pro pripojeni na dotaznik
     * 
     * Promenne: id, file, time, key
     */
    public function manipulateembedAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);
        
        $urlHlpr = new Zend_View_Helper_Url();
        
        $id = $this->getRequest()->getParam('id', null);
        $file = $this->getRequest()->getParam('file', null);
        $key = $this->getRequest()->getParam('key', null);
        $time = $this->getRequest()->getParam('time', null);
        $questionnaire = $this->getRequest()->getParam('questionnaire', null);
        //$isRating = $this->getRequest()->getParam('isAddRating', null);
        
        $newKey = null;
        
        // Nacte objekt Contentu do $this->content.
        if(!$id){
            return;
        }
        $data = $this->getContentData($id);
        if(!$data){
            return;
        }
        
        // Pokud soubor neexistuje, neni co delat
        if(!empty($file) && (!preg_match('/\.swf$/i', $file) || !file_exists($this->content->getPathToFile($file, false)))){
            return;
        }
        $source = $this->content->getPathToFile($file, true);
        
        /*
        // Pokud se jedna o RATING
        // Nastavime do reporting URL neco, co se pred predanim konfigurace nastavi na spravnou URL
        if($isRating){
            $reportUrl = '/ratingurl';
        }
        else{
            $reportUrl = null;
        }
        */
        
        
        // Vlozit embed
        if($time !== null && empty($key)){
            $newKey = $this->video->config->addEmbed($time, $source, null, null, true, null);
        }
        // Presunout embed
        elseif($time !== null && !empty($key)){
            $this->video->config->addEmbed($time, null, $key);
        }
        // Odstranit embed
        elseif(!empty($key) && empty($questionnaire)){
            $this->video->config->removeEmbed($key);
        }
        // Ulozit pripojeni k dotazniku
        if(!empty($questionnaire)){
            if($questionnaire != 'none'){
                $questionnaire = (int)$questionnaire;
                $reportUrl = $urlHlpr->url(array('contentid' => $questionnaire, 'srvname' => 'save'), 'service', true).'/';
                $this->video->config->addEmbed(null, null, $key, null, null, $reportUrl, $questionnaire);
            }
            else{
                $this->video->config->addEmbed(null, null, $key, null, null, '', '');
            }
        }
        
        //print_r($this->video->config->embeds);
        
        // Ulozime
        $this->saveContentData($data);
        
        // Primo na vystup vratime jmeno noveho klice, pokud bylo vlozeno
        echo $newKey;
    }
}