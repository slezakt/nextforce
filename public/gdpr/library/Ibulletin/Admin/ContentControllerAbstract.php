<?php
/**
 *
 * @author Bc. Petr Skoda
 */

class Ibulletin_Admin_ContentController_Exception extends Exception {}

/**
 *
 *
 */
abstract class Ibulletin_Admin_ContentControllerAbstract extends Ibulletin_Admin_BaseControllerAbstract
{

    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    public $contentType = null;

    /**
     * @var Ibulletin_Content_Abstract Objekt contentu, ktery prave editujeme, pokud jsme v akci edit.
     */
    public $content = null;

    /**
     * FileUploader
     *
     * @var Ibulletin_FileUploader
     */
    public $fileUploader = null;

    /**
     * Inicializuje potrebne veci. je inicializovan fileUploader a případne i spracován request na výpis souborů.
     */
    public function init() {
    	parent::init();

    	// Pokud je zadano ID, inicializujeme file uploader
        if($this->_request->getParam('id')){
            $this->fileUploader = new Ibulletin_FileUploader($this->_request->getParam('id'), $this->config->content_article->basepath);
//            $this->fileUploader->setPostUploadCallback(array($this,'postSaveActions'));
//            $this->fileUploader->doFormActions();
//            $this->view->fileUploader = $this->fileUploader;
        }

        //Dropzone
//        Ibulletin_Js::addJsFile('dropzone.min.js');
//        Ibulletin_HtmlHead::addFile('dropzone.css');
    }

    /**
     * Vypise seznam contentu se strucnym infem o nich a nabidne mozne akce
     */
    public function indexAction()
    {
        $texts = Ibulletin_Texts::getSet('admin.content_list');

        $this->setLastVisited();

        $this->setMessage('notvalid', $texts->add->notvalid);
        $this->setMessage('notsaved', $texts->add->notsaved);
        $this->setMessage('saved', $texts->add->saved);
        $this->setMessage('submit', $texts->add->submit);

        $this->setAfterCreateUrl('content', 'index');
        $this->view->form = $this->processCreate('content');
        
        $additionalContentTypes[] = $this->contentType;
        
        $ds = Contents::getQuery($additionalContentTypes);
        
        $ds->where('c.deleted IS NULL');
        
        $grid = $this->grid($ds);
        
         
        $grid->addAction('delete', array(
            'notempty' => 'page_id',
            'url' =>  $this->_helper->url('delete').'/id/$id/',
            'caption' => $texts->table->action_delete,
            'confirm' => $texts->table->confirm_delete,
            'image' => 'remove'
        ))
             ->addAction('preview', array(
            'empty' => 'bulletin_name',
            'url' =>  array($this, 'printLinks'),
            'caption' => $texts->table->action_preview,
            'image' => 'search'
        ))
            ->addAction('createsimplepage', array(
            'notempty' => 'page_id',
            'confirm' => $texts->table->confirm_createsimplepage,
            'url' =>  $this->_helper->url('createsimplepage').'/id/$id/',
            'caption' => $texts->table->action_createsimplepage,
            'image' => 'file'
        ))
            ->addAction('addtobul', array(
            'notempty' => 'bulletin_id',
            'url' =>  $this->_helper->url('addtobulletin').'/id/$id/pid/$page_id/',
            'caption' => $texts->table->action_addtolatestbulletin,
            'image' => 'plus'
        ))
          ->addAction('removefrombul', array(
            'empty' => 'bulletin_id',
            'confirm' => $texts->table->confirm_remfromlatestbulletin,
            'url' =>  $this->_helper->url('removefrombulletin').'/bid/$bulletin_id/pid/$page_id',
            'caption' => $texts->table->action_remfromlatestbulletin,
            'image' => 'minus'
        ))
           ->addAction('preview', array(
            'empty' => 'bulletin_name',
            'url' =>  array($this, 'printLinks'),
            'caption' => $texts->table->action_preview,
            'image' => 'search'
        ))
           ->addAction('duplicate', array(
            'confirm' =>  $texts->table->confirm_duplicate,
            'url' =>  $this->_helper->url('duplicate').'/id/$id/',
            'caption' => 'duplikovat',
            'image' => 'clone'
        ))
            ->addAction('edit', array(
            'url' =>  $this->_helper->url('edit').'/id/$id/',
            'caption' => $texts->table->action_edit,
            'image' => 'pencil'
        ));

        //pro opinions pridame spravu nazoru
        if ($this->contentType == 'Ibulletin_Content_Opinions') {
            $grid->addAction('manage', array(
                'url' => $this->_helper->url('manage') . '/id/$id/',
                'caption' => $this->texts->action_manage,
                'image' => 'comment'
            ));
        }
        
        //pro opinions pridame spravu nazoru
        if ($this->contentType == 'Ibulletin_Content_Calendar') {
            $grid->addAction('manage', array(
                'url' => $this->_helper->url('events') . '/id/$id/',
                'caption' => $this->texts->action_events,
                'image' => 'calendar'
            ));
        }
        
        // Ziskame seznam contentuu
        $list1 = Contents::getList($additionalContentTypes);

        // Ziskame vsechna dalsi zajimava data o contentu
        $pagesSel = '(SELECT cp.content_id, max(cp.page_id) AS page_id FROM pages p
                    JOIN content_pages cp ON p.id = cp.page_id GROUP BY cp.content_id)';
        $bulletinsSel = '(SELECT bp.page_id, max(bp.bulletin_id) AS bulletin_id FROM bulletins b
                    JOIN bulletins_pages bp ON b.id = bp.bulletin_id GROUP BY bp.page_id)';

        $sel = new Zend_Db_Select($this->db);
        $sel    ->from(array('c' => 'content'), '*')
                ->joinLeft(array('cp' => new Zend_Db_Expr($pagesSel)), 'c.id = cp.content_id', array())
                ->joinLeft(array('p' => 'pages'), 'p.id = cp.page_id',
                    array('page_id' => 'p.id', 'page_name' => 'p.name'))
                ->joinLeft(array('bp' => new Zend_Db_Expr($bulletinsSel)), 'p.id = bp.page_id', array())
                ->joinLeft(array('b' => 'bulletins'), 'b.id = bp.bulletin_id',
                    array('bulletin_id' => 'b.id', 'bulletin_name' => 'b.name'))
                ->joinLeft(array('pc' => 'pages_categories'), 'p.id = pc.page_id', (array()))
                ->joinLeft(array('cat' => 'categories'), 'cat.id = pc.category_id',
                    array('category_id' => 'cat.id', 'category_name' => 'cat.name'))
                ->order('c.created DESC');

        foreach($additionalContentTypes as $contentType){
            $sel->orwhere('class_name = ?', $contentType);
        }

        $list = $this->db->fetchAll($sel);

        // Ke kazdemu zaznamu jeste pridame deserializovany objekt contentu
        foreach($list as $key => $rec){
            foreach($list1 as $key1 => $rec1){
                if($rec1['id'] == $rec['id']){
                    $list[$key]['object'] = $rec1['object'];
                    unset($list1[$key1]);
                    break;
                }
            }
        }

        # Ulozime do view potrebne veci
        $this->view->content_list = $list;

        $this->view->grid = $grid->process();

    }
    
    
     /**
     * Vypise seznam odstranenych contentu
     */
    public function deletedAction()
    {
        $texts = Ibulletin_Texts::getSet('admin.content_list');

        $additionalContentTypes[] = $this->contentType;
        
        $ds = Contents::getQuery($additionalContentTypes);
        $ds->where('c.deleted IS NOT NULL');
        
        $grid = $this->grid($ds);
        
        $grid->addColumn('deleted', array(
            'header' => $texts->table->deleted,
            'type' => 'datetime',
            'field' => 'deleted'
        ));
        
        $grid->addAction('restore', array(
            'url' =>  $this->_helper->url('restore').'/id/$id/',
            'caption' => $texts->table->action_restore,
            'confirm' => $texts->table->confirm_restore,
            'image' => 'refresh'
        ));

        $this->view->grid = $grid->process();

    }
    

    /**
     * Cinnosti, ktere se maji provest pri vytvoreni noveho contentu
     *
     * @param Ibulletin_Content_Abstract $obj    Trida contentu k inicializaci.
     */
    public function initializeContent($obj)
    {
        $obj->html[0] = array();
    }

    public function createSimplePage($id) {
         // Vytvorime zaznamy s defaultnimi hodnotami v content_pages,
        // pages a links
        $config_def_page = $this->config->admin_default_page_for_content;

        $cont_data = Contents::get($id);
        $name = $cont_data['object']->getName();
        // Jmeno ma maximalni povolenou delku v pages a links 100 znaku
        //$name = substr($name, 0, 100); // Neni nadale potreba

        // Pridame zaznam do pages
        $ins_data = array('tpl_file' => $config_def_page->tpl_file,
                          'name' => $name);
        $this->db->insert('pages', $ins_data);
        $page_id = $this->db->lastInsertId('pages', 'id');

        // Pridame zaznam do links
        $ins_data = array('page_id' => $page_id,
                          'name' => $name);
        $this->db->insert('links', $ins_data);

        // Pridame zaznam do content_pages
        $ins_data = array('page_id' => $page_id,
                          'content_id' => $id,
                          'position' => $config_def_page->position);
        $this->db->insert('content_pages', $ins_data);

        return $page_id;

    }

    /**
     * Vytvori prostou stranku s timto contentem
     */
    public function createsimplepageAction(){

        $id = $this->_request->getParam('id', null);

        $this->createSimplePage($id);

        // Presmerujeme na index
       $this->redirectUri($this->getLastVisited());
    }

    /**
     * Prida content do posledniho bulletinu
     */
    public function addtobulletinAction() {
        $id = $this->_request->getParam('id', null);
        $pid = $this->_request->getParam('pid', null);
        $content = Contents::get($id);
        $this->assignToPageAndBulletin($content['object'], Bulletins::getLastBulletinId(),$pid);
        // Presmerujeme zpet;
        $this->redirectUri($this->getLastVisited());
    }

    /**
     * Odebere content z posledniho bulletinu, zachová prostou stránkou
     */
    public function removefrombulletinAction() {
        $bid = $this->_request->getParam('bid', null);
        $pid = $this->_request->getParam('pid', null);

        if ($bid && $pid) {
            $bu = new Bulletins();
            $bu->deletePage($bid, $pid);
        }

        // Presmerujeme na index
        $this->redirectUri($this->getLastVisited());
    }

    /**
     * Funkce aktualizuje data v tabulkach links a pages.
     *
     *  @param int $id  ID contentu.
     *  @param array $data  Data contentu.
     */
    public function updatePagesAndLinks($id, $data)
    {

        $sel = $this->db->select()
            ->from('content_pages', 'page_id')
            ->where('content_id = :id')
            ->where('position = 1')
            ->order('page_id');
        $page_id = $this->db->fetchOne($sel, array('id' => $id));
        if(!empty($page_id)){
            $this->db->update('pages', array('name' => $data['object']->name), "id = $page_id");

            $sel = $this->db->select()
                ->from('links', 'id')
                ->where('page_id = :id')
                ->order('id');
            $link_id = $this->db->fetchOne($sel, array('id' => $page_id));
            if(!empty($link_id)){
                $this->db->update('links', array('name' => $data['object']->name), "id = $link_id");
            }
        }
    }
    /**
     * metoda pro pridani zaznamu
     * $values obsahuje validni a "sanitized" hodnoty z formulare getCreateForm()
     * metoda by mela vracet TRUE v pripade uspechu, jinak FALSE
     *
     * @param string $name identifikator entity
     * @param array $values
     *
     * @return boolean
     */
    protected function createRecord($name, $values) {
        switch ($name) {
            case 'content':
                $obj = new $this->contentType;
                
                //generovani nazvu
                $name = str_replace('Ibulletin_Content_', '', $this->contentType);
                $n = count(Contents::getList($this->contentType)) + 1;
                $obj->name = $name.' '.$n;

                try{
                    $obj = Contents::edit(null, $obj);
                    //dle konfigurace přidá content do page a bulletinu
                    if ($this->config->content_article->autoAssignToPageAndBulletin) {
                       $this->assignToPageAndBulletin($obj,Bulletins::getActualBulletinId());
                    }

                    if ($obj) {
                        $this->redirect('edit', null, null, array('id'=>$obj->id));
                    }
                }
                catch(Exception $e){
                    // Vyjimku zalogujeme a ohlasime chybu
                    Phc_ErrorLog::warning('AdminContentControllerAbstract', $e);
                }

                return !empty($obj->id);
            default: return null;
        }
    }
    
    public function deleteRecord($name, $id) {
    	switch ($name) {
    		case "content":
                return Contents::delete($id);
    		default: return null;
    	}
    }
    
    public function getRecord($name, $id) {
    	switch ($name) {
    		case "content":
                return Contents::get($id);
    		default: return null;
    	}
    }
    

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getForm($name) {
        switch ($name) {
            case "content":
                $form = parent::getForm($name);
                $form->setFormInline(true);

                return $form;
            default: return null;
        }
    }

    /**
     * Vrati objekt formulare pro pridani noveho contentu - zadava se jen jmeno
     *
     * @return Zend_Form    Formular pro pridani contentu
     */
    /*public function getAddContentForm()
    {

        $form = new Zend_Form();
        $form->setMethod('post');

        $form->addElement('text', 'name', array(
            'label' => Ibulletin_Texts::get('content_list.new.name'),
        ));
        // Pridame validaci
        $name = $form->getElement('name');
        $name->setRequired(true);
        $name->addValidator('NotEmpty', true, array(
        	'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => Ibulletin_Texts::get('content_list.validators.isempty'))
        ));

        $form->addElement('submit', 'new_submit', array(
            'label' => Ibulletin_Texts::get('content_list.new.submit')
        ));

        return $form;
    }
    */

    /**
     * Najde dalsi informace, ktere vypisujeme pod editaci contentu, jako je
     * seznam statickych souboru prilozenych ke contentu.
     *
     *  @param Identifikator contentu
     */
    public function printLinks($id = null)
    {
    	$db = Zend_Registry::get('db');                 // db handler

        //zobrazovani links v datagridu -> callback datagridu posila array ...
        if(is_array($id)) {
            $id = $id['id'];
        }


        //zjistujeme zda je object "ready", kdyz ma atribut a neni ready vracime null
        $c = Contents::get($id);
        $obj = $c['object'];

        if (isset($obj->ready)) {
            if ($obj->ready == false) {
                return null;
            }
        }

    	if(is_numeric($id)){
    		// Pokusime se vytvorit odkaz(y) pro nahled contentu
    		// pomoci existujici page s timto contentem a priprazeni k bulletinu
    		$q = "SELECT bp.url_name AS article, b.url_name AS name
    			FROM content c
				JOIN content_pages cp ON c.id = cp.content_id
                JOIN pages p ON cp.page_id = p.id
                JOIN bulletins_pages bp ON p.id = bp.page_id
                JOIN bulletins b ON bp.bulletin_id = b.id
                WHERE c.id = ".$id." AND b.deleted IS NULL
                ORDER BY b.valid_from DESC, cp.position";
    		$row = $db->fetchRow($q);
    		if($row) {
        		return $this->view->url($row,'bulletinarticle');
    		}
    	}

        return null;
    }

    /**
     * Upravi a vrati upravene context menu, do ktereho paklize najde, odkaz pro editaci bulletinu nebo pages
     * @param type $id
     * @return array
     */
    public function prepareContentContext($id = null) {
        $db = Zend_Registry::get('db');                 // db handler

        if (is_numeric($id)) {
            // najde id page a id bulletin contentu
            $q = "SELECT p.id AS page_id, b.id AS bulletin_id
    			FROM content c
				JOIN content_pages cp ON c.id = cp.content_id
                JOIN pages p ON cp.page_id = p.id
                JOIN bulletins_pages bp ON p.id = bp.page_id
                JOIN bulletins b ON bp.bulletin_id = b.id
                WHERE c.id = " . $id . " AND b.deleted IS NULL
                ORDER BY b.valid_from DESC, cp.position";
            $row = $db->fetchRow($q);

            $page_id = null;
            $bulletin_id = null;

            if ($row) {
                $page_id = $row['page_id'];
                $bulletin_id = $row['bulletin_id'];
            } else {
                //nebylo nalezeno bulletin id, pokusime se najit alespon page id
                $q = "SELECT p.id AS page_id
                        FROM content c
                        JOIN content_pages cp ON c.id = cp.content_id
                        JOIN pages p ON cp.page_id = p.id
                        WHERE c.id = " . $id . "
                        ORDER BY cp.position";
                $page_row = $db->fetchRow($q);
                $page_id = $page_row['page_id'];
            }


            foreach ($this->_contextMenu as $key => $cxmenu) {
                if (($cxmenu['route']['controller'] == 'bulletinassembler')) {

                    //pokud mame id bulletinu upravime routu
                    if ($bulletin_id) {
                        $this->_contextMenu[$key]['route']['action'] = 'edit';
                        $this->_contextMenu[$key]['route']['id'] = $bulletin_id;
                    }
                }

                if (($cxmenu['route']['controller'] == 'pages')) {
                    //pokud mame id page upravime routu jestli ne zahodime ji
                    if ($page_id) {
                        $this->_contextMenu[$key]['route']['action'] = 'edit';
                        $this->_contextMenu[$key]['route']['id'] = $page_id;
                    }
                }
            }
        }

        return $this->_contextMenu;
    }


    /**
     * Search for resources in "resources" folder of the content and adds not existing resources
     * into resources table in DB.
     *
     * The creation of new resources or updating of resources is driven by resource file path.
     */
    public function prepareResources()
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        $basepath = $config->content_article->basepath;

        $resources = Resources::getBy(array($this->contentId), array(), false, false);

        $dirname = $basepath.'/'.$this->contentId.'/resources/';

        // If the directory of resources does not exist, return
        if(file_exists($dirname)){
            $dir = dir($dirname);
            while(false !== ($entry = $dir->read())){
                $found = false;
                if(is_file($dirname.'/'.$entry)){
                    // Prohledame resources daneho contentu a pripadne pridame nove
                    foreach($resources as $key => $resource){
                        if(basename($resource['path']) == basename($entry)){
                            $found = true;
                            unset($resources[$key]);
                            break;
                        }
                    }

                    // Add new resource
                    if(!$found){
                        $data = array();
                        $data['name'] = preg_replace('/\.*$/i', '', $entry);
                        $data['path'] = rtrim($dirname,'\\/').'/'.$entry;
                        $data['deleted'] = null;
                        $data['content_id'] = $this->contentId;
                        Resources::update($data);
                    }
                    // Undelete resource
                    /*
                    elseif($found && !empty($resource['deleted'])){
                        $data['deleted'] = null;
                        Resources::update($data, $resource['id']);
                    }
                    */
                }
            }
        }

        // Remove all resources of content without existing file
        foreach($resources as $resource){
            if(empty($resource['deleted'])){
                Resources::delete($resource['id']);
            }
        }
    }

    /**
     * Metoda spoustena po uploadu souboru
     */
    public function postSaveActions() {

    }

    /**
     * Duplikuje contenty
     */
    public function duplicateAction() {

        $id = $this->_getParam('id');

        if (!$id) {
            $this->redirect('index');
        }

        $texts = Ibulletin_Texts::getSet('admin.content_list');

        $cont = Contents::get($id);

        //doplni do nazvu duplikovaneho cislo, kontroluje duplicity nazvu
        $name = $cont['object']->name;
        $i = 1;
        while(Contents::getItemByName($name.' '.$i)) {
            $i++;
        }
        $name .= ' '.$i;
        $cont['object']->name = $name;

        $obj = Contents::edit(null, $cont['object'], $cont['author_id']);

        $newid = $obj->id;

        //paklize newid je odlisne -> content se zkopiroval viz contents:edit
        if ($id != $newid) {
            $this->infoMessage($texts->duplicate->success,'success');
            $config = Zend_Registry::get('config');
            //duplikace souboru
            $path = $config->content_article->basepath . $id;
            $newpath = $config->content_article->basepath . $newid;
            if (!Utils::rcopy($path, $newpath,array('resources'))) {
              $this->infoMessage($texts->duplicate->file_error,'error');
            }
            //automaticke vytvoreni page a pridani do bulletinu
            if ($this->config->content_article->autoAssignToPageAndBulletin) {
                $this->assignToPageAndBulletin($obj,Bulletins::getActualBulletinId());
            }
        } else {
           $this->infoMessage($texts->duplicate->error,'error');
        }

       $this->redirect('index');
    }


    /**
     * Priradi automaticky to bulletinu a vytvori page
     * @param type $obj content object
     * @param int ID bulletinu
     * @param int ID page
     */
    private function assignToPageAndBulletin($obj,$bulletin_id,$pageId = null) {
        $bu = new Bulletins();

        if (empty($pageId)) {
            $pageId = $this->createSimplePage($obj->id);
        }

        $urlName = Utils::slugify($obj->name, '`');
        $bu->addPageToBulletin($bulletin_id, $pageId, $urlName);
    }
    
    
    /**
    * Datagrid
    * @param Zend_Db_Select $select
    * @return Ibulletin_Datagrid
    */
    public function grid($select) {
      
        $grid = new Ibulletin_DataGrid($select);

        $grid->setDefaultSort('created');
        $grid->setDefaultDir('desc');
        
        $texts = Ibulletin_Texts::getSet('admin.content_list');
        
        $grid->setEmptyText($this->texts->index->empty ? $this->texts->index->empty : $texts->table->empty);
        
        $def = array(
            'align'=>'left',
            'default' => '&lt;null&gt;',
        );

        $grid->addColumn('id', array_merge($def, array(
            'header' => $texts->table->id,
            'field' => 'c.id',
            'type' => 'action',
            'actions' => array(
                'url' =>  $this->_helper->url('edit').'/id/$id/',
                'caption' => '$id',
            ),
            'align' => 'right',
            'width' => '40px',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'int',
            )
        )))
            ->addColumn('name', array_merge($def, array(
            'header' => $texts->table->name,
            'field' => 'c.name',
            'filter' => array(
                'autocomplete' => true,
                'field' => 'c.name',
                'type' => 'expr',
                'datatype' => 'string',
            ),
        )))
            ->addColumn('bulletin_name', array_merge($def, array(
            'header' => $texts->table->bulletin_name,
            'type' => 'action',
            'actions' => array(
                    'style' => "text-decoration:underline;",
                    'url' =>  $this->_helper->url('edit','bulletinassembler').'/id/$bulletin_id/'
            ),
            'filter' => array(
                'autocomplete' => true,
                'field' => 'b.name',
                'type' => 'expr',
                'datatype' => 'string',
            ),
        )))
            ->addColumn('category_name', array_merge($def, array(
            'header' => $texts->table->category_name,
            'filter' => array(
                'autocomplete' => true,
                'field' => 'cat.name',
                'type' => 'expr',
                'datatype' => 'string',
            )
        )))

            ->addColumn('page_name', array_merge($def, array(
            'header' => $texts->table->page_name,
            'type' => 'action',
            'actions' => array(
                    'style' => "text-decoration:underline;",
                    'url' =>  $this->_helper->url('edit','pages').'/id/$page_id/'
            ),
            'filter' => array(
                'autocomplete' => true,
                'field' => 'p.name',
                'type' => 'expr',
                'datatype' => 'string',
            )
        )))
            ->addColumn('changed', array_merge($def, array(
            'header' => $texts->table->changed,
            'type' => 'datetime',
            'field' => 'changed',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'datetime',
            )
        )));
        
        return $grid;
        
    }
    
     /**
     * Smaze content
     */
    public function deleteAction() {

		$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}
        
        $texts = Ibulletin_Texts::getSet('admin.content_list');
        $this->setMessage('deleted', $texts->delete->success);
        $this->setMessage('notdeleted', $texts->delete->error);

    	$this->setAfterDeleteUrl('content','index');
	    $this->processDelete('content', $id);

    }
    
    
     /**
     * Obnovi odstraneny content
     */
    public function restoreAction() {

        $id = $this->_getParam('id');

        if (!$id) {
            $this->redirect('index');
        }

        $texts = Ibulletin_Texts::getSet('admin.content_list');

        if (Contents::restore($id)) {
            $this->infoMessage($texts->restore->success, 'success');
        } else {
            $this->infoMessage($texts->restore->error, 'error');
        }


        $this->redirect('deleted');
    }

}
