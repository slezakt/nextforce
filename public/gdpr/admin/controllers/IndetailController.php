<?php
/**
 * Modul pro spravu pridavani contentu typu inDetail.
 *
 * TODO - Reorganizace umisteni templatu pro jednotlive druhy contentu,
 *        doreseni pojemnovavani podle umisteni (position), pridani vyberu template
 *        (mozna nebude nutne pokud budeme i nadale zadavat clanky jako html)
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

class Admin_Content_Not_Found_Exception extends Exception {}

class Admin_IndetailController extends Ibulletin_Admin_ContentControllerAbstract
{
	/**
	 *	Jmeno tridy pro kterou je tato editace urcena
	 */
	var $serialized_class_name = "Ibulletin_Content_Indetail";

	/**
	 * @var string Typ contentu, ktery tento modul spravuje.
	 */
	var $contentType = 'Ibulletin_Content_Indetail';

	/**
	 *	Prozatim staticke jmeno template pro tento druh obsahu
	 */
    var $tpl_name = 'indetail_1.phtml';


    /**
   * Zajistuje nacteni tlacitka pro pridani polozky a seznamu existujicich polozek.
     * V template je moznost vypsani informacnich hlasek.
   */
    /*
    public function indexAction()
  {
        $this->view->newButton = $this->getNewContentButton();
        $this->view->elementListForm = $this->getContentsListForm();
    }
    */


    /**
    * overrides Ibulletin_Admin_CRUDControllerAbstract::init()
    */
    public function init() {
    	parent::init();
        $this->texts = Ibulletin_Texts::getSet('admin.indetail');
    	$this->submenuAll = array(
        		'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
                'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => false)
    	);
        
//        if ($this->getRequest()->getActionName() == 'edit') {
//            $this->submenuAll['edit'] = array('title' => 'Edit', 'params' => array('action' => 'edit','id' => $this->getRequest()->getParam('id')), 'noreset' => false);
//        }
        
        
    }    
    
    /**
   *	Editace contentu.
   */
  public function editAction()
  {

        $texts = Ibulletin_Texts::getSet();
        $id = $this->_request->getParam('id', null);
        
        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit'); 
        $this->moduleMenu->setCurrentLocation('edit');
                
        $this->contentId = $id;
        
		$this->basePath = $this->config->content_article->basepath.'/'.$id.'/flash/'; 
        $this->basePath = Zend_Filter::filterStatic($this->basePath, 'NormPath');

        $c = Contents::get($id);
        $this->view->indetail_name = $c['name'];
        $this->view->contentId = $id;
        $this->view->contentObj = $c['object'];
        $this->view->basePath = $this->basePath;
		
        // Cesty k ruznym souborum
		$this->swfFilePath = $this->basePath . 'runtime.swf';
        $this->configFile = $this->basePath . 'config.xml';
        $this->debugRuntimeFile = $this->basePath . 'rtdebug.xml';
        
		Ibulletin_Js::addJsFile('admin/collapse.js');
        $form = $this->getEditContentForm($id);
        $data = $this->getContentData($id, $form);
        
        //bootstrap_tokenfield pro labely
        Ibulletin_Js::addJsFile('bootstrap_tokenfield/bootstrap-tokenfield.min.js');
        Ibulletin_HtmlHead::addFile('../scripts/bootstrap_tokenfield/bootstrap-tokenfield.css');


        //doplni do context menu je-li treba page id a bulletin id
        $this->view->contextMenu = $this->prepareContentContext($id);        
        
        $this->view->form = $form;

        // Pokud byla vyplnena data, provedeme ulozeni a aktualizujeme
        // data z ulozeni.
        if($form->isValid($data) && $this->getRequest()->getParam('save_content', null)){
            $data = $this->saveContentData($data);
            $this->redirect(array('action' => 'edit', 'id' => $id));
            // Vyplnime data upravena pri ukladani do formu
            $form->isValid($data);
        }
        
        $this->view->preview_links = $this->printLinks($id);
        
        // Seznam linku ke zobrazeni
        $this->renderLinks();

        // Titulek modulu
        $this->setActionTitle('"'.$data['name'].'"');

  }

    /**
     * Vrati objekt formulare pro editaci contentu.
     *
     * @return Zend_Form    Formular pro editaci contentu
     */
    public function getEditContentForm($id)
    {

	    /* @var $db Zend_Db_Adapter_Abstract */
	    $db =  Zend_Registry::get('db');

        // Vytvorime formular
        $form = new Form();
        $form->setMethod('post');

        $form->addElement('hidden', 'sheet');

        $form->addElement('text', 'id', array(
            'label' => $this->texts->form_id,
            'readonly' => 'readonly',
            'class' => 'span1'
        ));

        $form->addElement('text', 'name', array(
            'label' => $this->texts->name,
            'autofocus'=>'autofocus'
        ));

        $form->addElement('text', 'width', array(
            'label' => $this->texts->width,
            'class' => 'span2'
        ));

        $form->addElement('text', 'height', array(
            'label' => $this->texts->height,
            'class' => 'span2'
        ));
        

       $form->addDisplayGroup(array($form->getElement('width'),$form->getElement('height')),
                        'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));
       
       
       $form->addElement('text', 'points', array(
            'label' => $this->texts->edit->points,
            'class' => 'span2',
            'validators' => array(
                 array('Int',true,array('messages'=>array(Zend_Validate_Int::NOT_INT => $this->texts->edit->points_error)))   
            )
        ));

	    $form->addElement('text', 'entrycode', array(
		    'label' => $this->texts->edit->entrycode,
		    'class' => 'span2',
		    'validators' => array(
		    	array('Db_NoRecordExists',true,array('adapter'=>$db,'table'=>'content','field'=>'entrycode',
			                                         'exclude'=>array('field'=>'id','value'=>$id),
				    'messages'=>array(Zend_Validate_Db_NoRecordExists::ERROR_RECORD_FOUND => $this->texts->edit->entrycode_error)))
		    )
	    ));

	    $form->addDisplayGroup(array($form->getElement('points'),$form->getElement('entrycode')),
		    'grp2',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));
               
       //Zobrazime moznost editace prezentace, jestlize neexistuje adresar html5 nebo v html5 obsahuje template.ini
       if(is_file($this->config->content_article->basepath . $id . '/html5/template.ini') || !is_dir($this->config->content_article->basepath . $id . '/html5')) {
        $form->addElement('note','pmaker',array(
           'value' => '<a style="margin-bottom:10px" class="btn" href="'.$this->view->url(array('action'=>'pmaker')).'">'.$this->texts->edit_presentation.'</a>'
        )); 
       }
       
        $ace = new Zend_Form_Element_Textarea('html', array(
                'label' => $this->texts->html,
                'class' => 'editarea'                
         ));

        
        $form->addElement($ace);
        
        $form->addElement('text', 'labels', array(
            'label' => $this->texts->labels,
            'class' => 'span6'
        ));
       
        // Anotace
        $anotace = new Zend_Form_Element_Textarea(array(
          'name' => 'annotation',
          'label' => $this->texts->annotation,
          'class' => 'editarea',
          ));
        
        $form->addElement($anotace);
       
        
        $form->addElement('checkbox', 'tidy_off', array(
            'label' => $this->texts->tidy_off,
        ));
        
        $form->addElement('checkbox', 'adapt_html5_paths', array(
            'label' => $this->texts->adapt_html5_paths,
        ));

        $form->addElement('submit', 'save_content', array(
                'label' => $this->texts->submit,
                'class' => 'btn-primary'
            ));

        $links = new Links();
        $ace->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','rank'=>'_1','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
        $anotace->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','rank'=>'_2','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
     
        return $form;
    }

    
    /**
     * Ziska a vrati data contentu - pokud byl odeslan formular,
     * jsou vracena data z formulare.
     *
     * @param int       ID contentu
     * @param Zend_Form Formular z ktereho jsou odesilana data contentu.
     * @return array    Pole obsahujici data contentu
     */
    public function getContentData($id, $form)
    {

        // Ziskame data contentu
        $data = Contents::get($id);
        if(!$data){
            throw new Admin_Content_Not_Found_Exception('Content se zadaným ID nebyl nalezen.');
            // $this->infoMessage('Content se zadaným ID nebyl nalezen.');
        }

        // Nastavime objekt contentu do atributu content
        $this->content = $data['object'];
        $data['name'] = $this->content->name;
        if (is_string($this->content->html)) $data['html'] = $this->content->html; else
            $data['html'] = $this->content->html[0];

        $data['width'] = (isset($this->content->flashWidth) ? $this->content->flashWidth : '1000');
        $data['height'] = (isset($this->content->flashHeight) ? $this->content->flashHeight : '550');

        $data['tidy_off'] = $this->content->tidyOff;
        $data['adapt_html5_paths'] = $this->content->adaptHtml5Paths;
        $data['labels'] = $this->content->labels;
        $data['annotation'] = $this->content->annotation;
        $data['points'] = $this->content->points;
        $data['entrycode'] = $this->content->entrycode;
        
        // Upravime pripadne zaznamy v pages a links - nastavime nove jmeno
        // TODO Proc to delame tady?? :o/
        $this->updatePagesAndLinks($id, $data);

        if(isset($_POST['save_content']) && isset($_POST['id']) && $_POST['id'] == $id){
            // Naplnime form daty z postu - predevsim kvuli checkboxum
            $form->isValid($_POST);
            return $form->getValues() + $data;
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
     * Cinnosti, ktere se maji provest pri vytvoreni noveho contentu
     *
     * @param Ibulletin_Content_Abstract $obj    Trida contentu k inicializaci.
     */
    public function initializeContent($obj)
    {

        $obj->html[0] = array();
        $obj->flashWidth = "1000";
        $obj->flashHeight = "550";

    }

    /**
     * Ulozi data prijata z editacniho formulare contentu.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveContentData($data)
    {
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

        $obj->tidyOff = $data['tidy_off'];
        $obj->adaptHtml5Paths = $data['adapt_html5_paths'];
        $data['html'] = $obj->setHtml($data['html']); // Kvuli vyplneni upraveneho HTML zpet do formu
        $obj->annotation = $data['annotation'];
        $obj->labels = $data['labels'];
        $obj->flashWidth = $data['width'];
        $obj->flashHeight = $data['height']; 
        $obj->points = $data['points'];
        $obj->entrycode = $data['entrycode'];

        try{
            Contents::edit($id, $obj);
        }
        catch(Exception $e){
            $ok = false;
            $this->infoMessage($this->texts->edit->notsaved, 'error');
            Phc_ErrorLog::warning('Admin_PresentationController', "Udaje contentu se nepodarilo zmenit. content_id='".$id
                ."' Puvodni vyjimka:\n$e");
        }

        if($ok && isset($data['save_content'])){
            $this->infoMessage($this->texts->edit->saved);
            unset($data['save_content']);
        }
        
        // Revert upravenych HTML5 souboru, pokud byla vypnuta uprava statickych cest v HTML5 prezentaci 
        if(!$obj->adaptHtml5Paths){
            $this->htmlFixStaticPathsAndTranslMarks($obj, true);
            $this->infoMessage($this->texts->html_js_files_reverted);
        }
        
        $this->postSaveActions();

        return $data;
    }

    
    /**
     * Najde vsechny .js, .xml a .html soubory v html5/ a nahradi v nich cesty ke statickym souborum spravnymi
     * od korene webu.
     * 
     * Zaroven prelozi znacky pomoci Ibulletin_Marks (aktualne jen %%link a znacky pro zmenu emailu uzivatele).
     * 
     * S parametrem $doRevert umi naopak navratit puvodni soubory a tim zrusit predchozi nahrady cest.
     * K revertu pouziva .shadow soubory.
     * 
     * @param Ibulletin_Content_Indetail    Objekt contentu, pro ktery upravujeme data.
     * @param bool                          Misto convertu vratit puvodni verze souboru ze shadow?
     */
    public function htmlFixStaticPathsAndTranslMarks(Ibulletin_Content_Indetail $obj, $doRevert = false)
    {
        $basePath = $obj->getBasepath();
        // Prochazime jen adresar HTML5, protoze uprava xml ve flash prezentaci pokazi jeji funkci
        $basePath .= '/html5/';
        
        // Pokud neexistuje adresar html5, vyskocime
        if(!file_exists($basePath)){
            return;
        }
        
        if(!$doRevert){
            $transformCallback = 'htmlFixStaticPathsAndTranslMarks_transformPaths';
        }
        else{
            $transformCallback = 'htmlFixStaticPathsAndTranslMarks_revertPaths';
        }

        // Spustime hledani JS a vykonavani funkce transformPaths
        $this->findFiles($basePath, '/((\.js$)|(\.xml$)|(\.html$))/i', array($this, $transformCallback), $obj);
    }
    // Callback, ktery prelozi cesty a znacky v nalezenych JS souborech a ulozi je zpet
    private function htmlFixStaticPathsAndTranslMarks_transformPaths($file, $obj)
    {

        // vytvorime shadow file pred upravou obsahu souboru
        if (!Files::hasShadowFile($file)) {
            Files::createShadowFile($file);
        }

        // Nacteme obsah shadow souboru do pole
        $fileContent = file(Files::getShadowFile($file));

        // Opravime cesty #########################################
        $fileContent = $obj->correctHtmlStaticPaths($fileContent, dirname($file));
        // ########################################################

        // Prelozime znacky
        $fileContentStr = Ibulletin_Marks::translate(join($fileContent, ''), '', null, array(), 
            array(), false);


        // Zapiseme do originalniho souboru
        $fp = fopen($file, 'w');
        fwrite($fp, $fileContentStr);
        fclose($fp);
    }
    // Callback, ktery vrati puvodni neupravene soubory
    private function htmlFixStaticPathsAndTranslMarks_revertPaths($file, $obj)
    {

        // vytvorime shadow file pred upravou obsahu souboru
        if(Files::hasShadowFile($file)) {
            // Nacteme obsah shadow souboru do pole
            $shadowFileName = Files::getShadowFile($file);
            $fileContent = file($shadowFileName);
    
            // Zapiseme do originalniho souboru
            $fp = fopen($file, 'w');
            fwrite($fp, join('',$fileContent));
            fclose($fp);
            
            // Smazeme shadow
            @unlink($shadowFileName);
        }
        
    }

    /**
     * Rekurzivni prohledavani adresaru pro nalezeni souboru
     *
     * @param $path
     * @param $pattern
     * @param $callback
     * @param $obj
     */
    private function findFiles($path, $pattern, $callback, $obj) {
        $path = rtrim(str_replace("\\", "/", $path), '/') . '/';
        $matches = Array();
        $entries = Array();
        $dir = dir($path);
        while (false !== ($entry = $dir->read())) {
            $entries[] = $entry;
        }
        $dir->close();
        foreach ($entries as $entry) {
            $fullname = $path . $entry;
            if ($entry != '.' && $entry != '..' && is_dir($fullname)) {
                $this->findFiles($fullname, $pattern, $callback, $obj);
            } else if (is_file($fullname) && preg_match($pattern, $entry)) {
                call_user_func($callback, $fullname, $obj);
            }
        }
    }


    /**
     * Zjisti, jestli content obsahuje flash prezentaci overenim existence config.xml ve flash/
     * Pouzivame pro nastaveni priznaku v contentu.
     * 
     * @param Ibulletin_Content_Indetail
     * @return bool Obsahuje content flash prezentaci?
     */
    public function hasFlashVersion($obj)
    {
        if(file_exists($obj->getBasepath().$obj->flashConfigFile)){
            return true;
        }
        return false;
    }


    /**
     * Zjisti, jestli content obsahuje HTML prezentaci overenim existence index.html v html5/
     * Pouzivame pro nastaveni priznaku v contentu.
     * 
     * @param Ibulletin_Content_Indetail
     * @return bool Obsahuje content HTML prezentaci?
     */
    public function hasHtmlVersion($obj)
    {
        if(file_exists($obj->getBasepath().$obj->htmlPresIndexFile)){
            return true;
        }
        return false;
    }
    
    
    /**
     * Zjisti, jestli content obsahuje HTML inPad prezentaci overenim existence ipad_package.zip v html5/
     * Pouzivame pro nastaveni priznaku v contentu.
     * 
     * @param Ibulletin_Content_Indetail
     * @return bool Obsahuje content HTML inPad prezentaci?
     */
    public function hasHtmlInPadVersion($obj)
    {
        if(file_exists($obj->getBasepath().$obj->htmlInPadPresIndexFile)){
            return true;
        }
        return false;
    }


    /**
     * Vrati jmeno tohoto controlleru
     */
    public function getControllerName()
    {
        if(empty($this->_controllerName)){
            $name = get_class($this);
            $tokens = split("_", $name);
            $name = str_ireplace('controller', '', $tokens[count($tokens)-1]);
            $name = strtolower($name);
            $this->_controllerName = $name;
        }

        return $this->_controllerName;
    }
    
    /**
     * Operations to be run after saving the content.
     * @see Ibulletin_Admin_ContentControllerAbstract::postSaveActions()
     */
    public function postSaveActions() {        
        
        $id = $this->_request->getParam('id', null);
        $this->contentId = $id;
       
        $this->prepareResources();
        $this->prepareCertificates();
        
        $this->basePath = $this->config->content_article->basepath.'/'.$id.'/flash/'; 
        $this->basePath = Zend_Filter::filterStatic($this->basePath, 'NormPath');
		
        // Cesty k ruznym souborum
		$this->swfFilePath = $this->basePath . 'runtime.swf';
        $this->configFile = $this->basePath . 'config.xml';
        $this->debugRuntimeFile = $this->basePath . 'rtdebug.xml';
        
        $content = Contents::get($id);
        $obj = $content['object'];
        if($obj->adaptHtml5Paths){
            $this->htmlFixStaticPathsAndTranslMarks($obj);
        }
        
        $ok = true; // Flag, jestli se vse ulozilo OK

      # Data contentu a objektu v nem
        if(!$this->content){
            $content = Contents::get($id);
            $obj = $content['object'];
            $this->content = $obj;
        }
        else{
            $obj = $this->content;
        }

        $phpFile = $obj->getBasepath().preg_replace('/\.html$/i', '.php', $obj->htmlPresIndexFile);

        // smazani index.html a shadow souboru pokud existuje index.php i index.html
        if (file_exists($phpFile) && $this->hasHtmlVersion($obj)) {
            $htmlFile = $obj->getBasepath().$obj->htmlPresIndexFile;
            unlink($htmlFile);
            if (file_exists(Files::hasShadowFile($htmlFile))) {
                unlink(Files::getShadowFile($htmlFile));
            }
        }

        // Zkusime, jestli neexistuje index.php pro html5, ktery bychom prohnali PHP interpretem
        if(!$this->hasHtmlVersion($obj)){
            if(file_exists($phpFile)){
                $command = 'cd "'.dirname($phpFile)."\" && php -q \"".basename($phpFile).'" > "'.basename($obj->htmlPresIndexFile).'"';
                //echo $command;
                exec($command);

                Utils::chmod($htmlFile, Utils::FILE_PERMISSION);

                $this->infoMessage($this->texts->html_file_created);
            }
        }
        
        // Nastavime, jake jsou k dispozici typy prezentace
        $obj->hasHtml = $this->hasHtmlVersion($obj);
        $obj->hasInPadHtml = $this->hasHtmlInPadVersion($obj);
        $obj->hasFlash = $this->hasFlashVersion($obj);
        
        // Pokud je k dispozici inPad prezentace, nastavime ready na true
        if($obj->hasInPadHtml){
            $obj->ready = true;
        }
        
        // Pokud mame i HTML prezentaci, upravime cesty ke statickym souborum na spravna URL v JS
        if($obj->hasHtml){
            if($obj->adaptHtml5Paths){
                $this->htmlFixStaticPathsAndTranslMarks($obj);
                $this->infoMessage($this->texts->html_js_files_modified);
            }
            
            // Prezentaci povazujeme za prehratelnou
            $obj->ready = true;
        }
        
        # inRep prezentace - nachystame zipy, pokud je to potreba (pro normalni i testovaci verzi)
        $inrepPres = new Inreppresentation($id);
        $inRepFile = $inrepPres->createZipPackage();
        $inrepPresTest = new Inreppresentation($id, true);
        $inRepFileTest = $inrepPresTest->createZipPackage();
        // Info hlaska o vytvoreni balicku
        if(!empty($inRepFile) || !empty($inRepFileTest)){
            $this->infoMessage($this->texts->inrep_package_created);
        }
        // Info o nevytvoreni balicku, pokud neni zdrojovy adresar dostatecne plny
        if(file_exists($inrepPres->getDirectoryPath()) && !$inrepPres->isReady()){
            $this->infoMessage($this->texts->inrep_package_not_ready);
        }
        else{ // Nastavime ready do objektu Indetailu
            $obj->ready = true;
            $obj->hasInPadHtml = true;
        }
        if(file_exists($inrepPresTest->getDirectoryPath()) && !$inrepPresTest->isReady()){
            $this->infoMessage($this->texts->inrep_package_not_ready);
        }
        else{ // Nastavime ready do objektu Indetailu (objekt je ready i kdyz ma jen testovaci verzi)
            $obj->ready = true;
            $obj->hasInPadHtml = true;
        }

        /* TODO: metoda contentu indetailu -> isReady() - implementovat do ni to co je tady v komentu */
        if (file_exists($this->swfFilePath) && is_readable($this->swfFilePath)) {

            // Prezentaci povazujeme za prehratelnou
            $obj->ready = true;

            $runtimeFile = str_replace('.swf', '.xml', $this->swfFilePath);

            if (!file_exists($runtimeFile)) {
               $this->infoMessage(sprintf($this->texts->missing_file,$runtimeFile),'warning');
                $obj->ready = false;
            }



            // pokud neexistuje. vygenerujeme  debug runtime konfiguraci k runtime.xml
            if (!file_exists($this->debugRuntimeFile)) {

                $xml = new DOMDocument();
                $opened  = $xml->load($runtimeFile);
                if(!$opened){
                    $this->infoMessage(sprintf($this->texts->not_xmlfile,$runtimeFile),'warning');
                    $obj->ready = false;
                } else {
                    $xpath = new DOMXPath($xml);

                    if(!empty($xml) && ($xpath->query('//*')->length == 0 || $xpath->query('//runtime')->length > 0)){

                        $nodes = $xpath->query('//runtime/load[@name="eDetailing Framework"]');

                        if($nodes->length > 0){
                            $node = $nodes->item(0);
                            $elm = $xml->createElement('load');
                            $new_elm = $node->parentNode->insertBefore($elm, $node->nextSibling);
                            $new_elm->setAttribute("name", "Alert");
                            $new_elm->setAttribute("href", "js-alert.swf");
                            // ekvivalent insertAfter()


                            // Zapiseme XML
                            $written = $xml->save($this->debugRuntimeFile) && Utils::chmod($this->debugRuntimeFile,Utils::FILE_PERMISSION);
                            if(!$written){
                                $this->infoMessage(sprintf($this->texts->notwritten_xmlfile,$this->debugRuntimeFile),'warning');
                                $obj->ready = false;
                            }
                        } else {
                            $this->infoMessage(sprintf($this->texts->nodeclaration_xmlfile,$runtimeFile),'warning');
                            $obj->ready = false;
                        }
                    } else {
                        $this->infoMessage(sprintf($this->texts->invalid_xmlfile,$runtimeFile),'warning');
                        $obj->ready = false;
                    }

                }
            }

            if ($obj->ready == true && $obj->hasFlash) {
                //a nakopirujeme js-alert.swf
                if (!file_exists($this->basePath . 'js-alert.swf')) {
                    if (!copy('pub/scripts/js-alert.swf', $this->basePath . 'js-alert.swf') || !Utils::chmod($this->basePath . 'js-alert.swf',Utils::FILE_PERMISSION)) {
                        $this->infoMessage($this->texts->jsalert_error);
                    }
                }
            }

        } else {
            // Prezentaci neni mozne prehravat
            if(!$obj->hasHtml && !$obj->hasInPadHtml){
                $obj->ready = false;
                $this->infoMessage(sprintf($this->texts->missing_file,$this->swfFilePath),'warning');
            }
        }

        /*
      # Nastaveni parametruu v prezentaci a ulozeni konfigurace prezentace
        if(file_exists($this->content->getPathToFile($this->content->file_name, false))){
            $this->content->config->source = $obj->getPathToFile($this->content->file_name);

            // Ulozit XML config
            $this->content->config->getXml($this->content->getPathToFile($this->content->configFileName, false));
            //print_r($this->content->config);

            // Prezentaci je mozne prehravat
            $obj->ready = true;
        }
        else{
            // Prezentaci neni mozne prehravat
            $obj->ready = false;
        }
        */


        try{
            Contents::edit($id, $obj);
        }
        catch(Exception $e){
            $ok = false;
            $this->infoMessage($this->texts->edit->notsaved, 'error');
            Phc_ErrorLog::warning('Admin_PresentationController', "Udaje contentu se nepodarilo zmenit. content_id='".$id
                ."' Puvodni vyjimka:\n$e");
        }


        // Zavolame zpracovani zavislych contentu
        $this->content->afterSave();


        // Zpracovani souboru config.xml (nastaveni odkazu na generovany flashinput.xml)
        if(file_exists($this->configFile) && is_readable($this->configFile)){
            $dataUrl = $obj->getXmlDataUrl();
            $configXmlDoc = new DOMDocument();

            // create a shadow file of config file if not exists
            if (!Files::hasShadowFile($this->configFile)) {
                Files::createShadowFile($this->configFile);
            }

            $cfgFile = Files::getShadowFile($this->configFile);
            $configXmlDoc->load($cfgFile);
            $configXmlXPath = new DOMXPath($configXmlDoc);

            if(!empty($configXmlDoc) && ($configXmlXPath->query('//*')->length == 0 || $configXmlXPath->query('//flash')->length > 0)){


              //#### input/dataXmlUrl
                $cdata = $configXmlDoc->createCDATASection($dataUrl);
                $nodes = $configXmlXPath->query('//flash/input/dataXmlUrl');
                if($nodes->length > 0){
                    $element = $nodes->item(0);
                    while ($element->childNodes->length) {
                        $element->removeChild($element->childNodes->item(0));
                    }
                }
                // Vytvorime elementy pro cestu flash/input/dataXmlUrl
                else
                {
                    // Vytvorime rootelement
                    if($configXmlXPath->query('//flash')->length == 0){
                            $elem = $configXmlDoc->createElement('flash');
                            $configXmlDoc->appendChild($elem);

                    }
                    // Vytvorime element input
                    if($configXmlXPath->query('//flash/input')->length == 0){
                        $parent = $configXmlXPath->query('//flash')->item(0);
                        $elem = $configXmlDoc->createElement('input');
                        $parent->appendChild($elem);
                    }

                    $parent = $configXmlXPath->query('//flash/input')->item(0);
                    $element = $configXmlDoc->createElement('dataXmlUrl');
                    $parent->appendChild($element);
                }

                // Vlozime cdata do spravneho elementu
                $element->appendChild($cdata);
                unset($element);


              //#### links/klient_info_url
                $cdata = $configXmlDoc->createCDATASection($dataUrl);
                $nodes = $configXmlXPath->query('//flash/links/klient_info_url');
                if($nodes->length > 0){
                    $element = $nodes->item(0);
                    while ($element->childNodes->length) {
                        $element->removeChild($element->childNodes->item(0));
                    }
                }
                // Vytvorime elementy pro cestu flash/links/klient_info_url
                else
                {
                    // Vytvorime element input
                    if($configXmlXPath->query('//flash/links')->length == 0){
                        $parent = $configXmlXPath->query('//flash')->item(0);
                        $elem = $configXmlDoc->createElement('links');
                        $parent->appendChild($elem);
                    }

                    $parent = $configXmlXPath->query('//flash/links')->item(0);
                    $element = $configXmlDoc->createElement('klient_info_url');
                    $parent->appendChild($element);
                }

                // Vlozime cdata do spravneho elementu
                $element->appendChild($cdata);

                // Zapiseme XML
                $written = $configXmlDoc->save($this->configFile);
                if(!$written){
                    $this->infoMessage(sprintf($this->texts->notwritten_xmlfile,$this->configFile),'warning');
                }
            }
            else{
              $this->infoMessage(sprintf($this->texts->nodeclaration_xmlfile,$this->configFile),'warning');
            }
        }
        elseif(!$obj->hasHtml){
            $this->infoMessage(sprintf($this->texts->missing_file,$this->configFile),'warning');
        }
        
        // Prepare zip packages for the inRep presentations
        $inRepPresentation = new InRepPresentation($id);
        $inRepPresentation->createZipPackage();
        
        // Load resources for this content
        $this->prepareResources();
        

        if($ok){
            $this->infoMessage($this->texts->edit->saved);          
        }

    }
    
    public function pmakerAction() {
        
       $id = $this->_request->getParam('id', null);

        if (!$id) {
            $this->redirect('index');
        }
        
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit'); 
        $this->moduleMenu->addItem($this->texts->pmaker->submenu_edit, array('action'=>'pmaker','id'=>$id), null, true,'pmaker'); 
        $this->moduleMenu->setCurrentLocation('pmaker');
        
  
        Ibulletin_Js::addJsFile('ckeditor/ckeditor.js');
        Ibulletin_Js::addJsFile('ckeditor/adapters/jquery.js');
        Ibulletin_Js::addPlainCode("var CKEDITOR_BASEPATH = '" . $this->view->baseUrl() . "/pub/scripts/ckeditor/';");
        Ibulletin_Js::addPlainCode("var CKEDITOR_ELFINDER_URL = '" . $this->view->url(array('controller'=>'filemanager','action'=>'elfinder-cke'))."'");
        Ibulletin_Js::addJsFile('admin/pmaker.js');
        Ibulletin_Js::addJsFile('jquery.xcolor.min.js');
       
        //mrkneme do configu na podobu toolbaru (full - kompletní nastroje, default - dle zakladniho nastaveni)
        if (isset($this->config->wysiwyg->toolbar)) {
            $this->view->wysiwyg_toolbar = $this->config->wysiwyg->toolbar;
        } else {
            $this->view->wysiwyg_toolbar = "default";
        }
        
        $target_dir = $this->config->content_article->basepath . $id . '/html5';

        if (!$this->hasHTML5Presentation($id)) {
            $tform = $this->templateForm();
            if ($this->getRequest()->isPost() && $tform->isValid($this->getRequest()->getPost())) {
                Utils::rcopy($tform->getValue('template'), $target_dir);
                $content = Contents::get($id);
                $obj = $content['object'];
                $obj->html5TemplatePath = $tform->getValue('template');
                Contents::edit($id, $obj);
                $this->postSaveActions();
                $this->redirect('pmaker', 'indetail', 'admin', array('id' => $id));
            }

            $this->view->form = $tform;
            return;

        }
        
        //nahled na frontu
         $this->view->preview_links = $this->printLinks($id);

        //posleme template do view
        $content = Contents::get($id);
        $obj = $content['object'];
        $template_dir = $obj->html5TemplatePath;
        $tpl_index = $template_dir.'/index.html';

        if (is_file($tpl_index)) {
            $this->view->tpl_index = $tpl_index;
        }
        
        $links = new Links();
        $this->view->links = $links->getSortedLinks();
       
        //posleme do js texty s nazvy sablon
        $this->view->json_texts_tpl_name = json_encode(Ibulletin_Texts::getSet('admin.indetail.pmaker.template')->toArray());
        
        if (is_file($target_dir.'/index.html.shadow')) {
            $this->view->pres_index = $target_dir.'/index.html.shadow?random='.time();
        } elseif(is_file($target_dir.'/index.html')) {
            $this->view->pres_index = $target_dir.'/index.html?random='.time();
        } else {
           $this->infoMessage(Ibulletin_Texts::get('pmaker.editor.unable_load'),'error'); 
           $this->redirect('edit', 'indetail', 'admin', array('id'=>$id));
        }

    }
    
    public function pmakersaveAction() {

        if ($this->getRequest()->isPost()) {
            $values = $this->getRequest()->getPost();
            $data = $values['data'];
            $target_file = $values['file'];
            $id = $this->_getParam('id');

            //zalohovani index.html
            $tstamp = date('Ymd',time());
            
            if (basename($target_file)=="index.html" && is_file($this->config->content_article->basepath . $id . '/html5/index.html.shadow')) {
                $this->view->data = json_encode(array('error' => $this->texts->pmaker->save_presentation_error,'file_reload'=> $this->config->content_article->basepath . $id . '/html5/index.html.shadow'));
                return;
            }

            $backup_id = 1;
            $backup_folder = $this->config->content_article->basepath . $id . '/html5/.bckp/';
            if (!is_dir($backup_folder)) {
                $backup_folder = $backup_folder.$tstamp.'-1';
            } else {
                $b_dirs = scandir($backup_folder);
                
                foreach ($b_dirs as $f) {
                   if (preg_match('/'.$tstamp.'-[0-9]+/',$f)) {
                       $backup_id++;
                   }
                }
                
                $backup_folder = $backup_folder.$tstamp.'-'.$backup_id;
            }
            
            mkdir($backup_folder, 0777, true);
            copy($target_file,$backup_folder.'/index.html');
            Utils::chmod($backup_folder.'/index.html', Utils::FILE_PERMISSION);
            
            $save = @file_put_contents($target_file, $data); 
            if ($save) {
                $this->_helper->json(json_encode(array('result' => Ibulletin_Texts::get('pmaker.editor.save_presentation_success'))));
                $this->postSaveActions();
            } else {
                $this->_helper->json(json_encode(array('error' => Ibulletin_Texts::get('pmaker.editor.save_presentation_error'))));
            }
        }
    }

    //Formular sablon pro prezentace
    public function templateForm() {

        $template_dir = $this->config->paths->indetail_template;

        $templates = array();
        //vytahneme si sablony
        if (is_dir($template_dir)) {
            $folders = scandir($template_dir);
            foreach ($folders as $f) {
                if ($f == '.' || $f == '..') {
                    continue;
                }
                if (is_dir($template_dir . $f)) {
                    if (is_file($template_dir . $f.'/template.ini')) {
                        $templates[$this->config->paths->indetail_template . $f] = $f;
                    }
                }
            }
        }

        $form = new Form();
        $form->setMethod('post');

        $form->addElement('select','template',array(
            'label' => $this->texts->pmaker->templates,
            'multioptions' => $templates
        ));
        
        $form->addElement('submit','save',array(
            'label' => $this->texts->pmaker->create,
            'class' => 'btn-primary'
        ));

        return $form;
    }
    
    /**
     * Testuje zda ma content sablonovou html5 prezentaci
     * @param int $id ID contentu
     * @return boolean 
     */
    public function hasHTML5Presentation($id) {
        $target_dir = $this->config->content_article->basepath . $id . '/html5';
        if (is_dir($target_dir)) {
            $folders = scandir($target_dir);
            if ($folders) {
                if (count($folders) > 2) {
                    $content = Contents::get($id);
                    $obj = $content['object'];
                    if (!empty($obj->html5TemplatePath)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    
     /**
     * Search for certificates template in folder of the content and adds not existing certificates template to resources
     * into resources table in DB.
     *
     * The creation of new resources or updating of resources is driven by resource file path.
     */
    public function prepareCertificates() {

        $config = Zend_Registry::get('config');

        $certPath = rtrim($config->content_article->basepath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->contentId . DIRECTORY_SEPARATOR . $config->caseStudies->certificate->folderName;

        $resources = Resources::getBy(array($this->contentId), array(), false, false, 'certificate');
        
        //neexistuje-li slozka s certifikaty unkoncime a pripadne vymazeme resource
        if (!file_exists($certPath)) {
            foreach ($resources as $resource) {
                Resources::delete($resource['id']);
            }
            return;
        }

        $id = null;
        
        //je-li certifikat jiz v resources ukoncime
        foreach ($resources as $resource) {
            if ($resource['path'] == $certPath && $resource['deleted'] == null) {
                return;
            }
            
            $id = $resource['id'];
        }
        
        $content = Contents::get($this->contentId);

        //ulozime certifikat do resources
        $data = array();
        $data['name'] = 'Certificate ' . $content['name'];
        $data['path'] = $certPath;
        $data['deleted'] = null;
        $data['content_id'] = $this->contentId;
        $data['special_type'] = 'certificate';
        Resources::update($data, $id, false);
    }

}
