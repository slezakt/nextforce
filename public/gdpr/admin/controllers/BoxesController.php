<?php
/**
 * Modul pro spravu Boxuu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>, Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Admin_BoxesController extends Ibulletin_Admin_BaseControllerAbstract
{
    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
    public function init() {
        parent::init();
        $this->submenuAll = array(
			'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
        );
    }

    /**
    * overrides Ibulletin_Admin_BaseControllerAbstract::getCreateForm($name)
    */
    public function getForm($name) {

    	$texts = Ibulletin_Texts::getSet();
    	$form = parent::getForm($name);

    	switch ($name) {
    		case "box":
    			$name = new Zend_Form_Element_Text(array(
   					'name' => 'name',
					'label' => $texts->name,
					'required' => true

   				));
    			$emptyValidator = new Zend_Validate_NotEmpty();
    			$emptyValidator->setMessages(array(
    				Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty
    			));
    			$name->addValidator($emptyValidator);
    			$form->setFormInline(true);
    			$form->addElements(array($name));

    			return $form;

    		default: return null;
   		}
   	}

   	/**
   	* overrides Ibulletin_Admin_BaseControllerAbstract::getUpdateForm($name)
   	*/
   	public function getUpdateForm($name) {

   		$texts = Ibulletin_Texts::getSet();
   		$form = parent::getUpdateForm($name);

   		switch ($name) {
   			case "box":
   				$name = new Zend_Form_Element_Text(array(
   					'name' => 'name',
					'label' => $texts->name,
					'required' => true
   				));

   				$emptyValidator = new Zend_Validate_NotEmpty();
   				$emptyValidator->setMessages(array(
   					Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty
   				));
   				$name->addValidator($emptyValidator);

   				$description = new Zend_Form_Element_Textarea(array(
					'name' => 'description',
					'label' => $texts->description,
					'rows' => '3'
   				));
				$form->setFormInline(false);
   				$form->addElements(array($name,$description));
   				return $form;

   			default: return null;
   		}
   	}

   	/**
   	* overrides Ibulletin_Admin_BaseControllerAbstract::getRecord($name, $id)
   	*/
   	public function getRecord($name, $id) {

   		switch ($name) {
   			case "box":
   				return Boxes::getBox($id);

   			case "version":
   				return Boxes::getVersion($id);

   			default: return null;
   		}
   	}

   	/**
   	* overrides Ibulletin_Admin_BaseControllerAbstract::getRecords($name, $id)
   	*/
   	public function getRecords($name, $options = array()) {

   		switch ($name) {
   			case "box":
   				// Ziskame seznam boxuu
   				$boxes = Boxes::getBoxes();

   				// Dohledame datum platnosti nejnovejsiho boxu
   				foreach($boxes as $key => $box){
   					$versions = Boxes::getVersions($box['id'], 'final_valid_from DESC');
   					$boxes[$key]['newest_version'] = isset($versions[0]['final_valid_from']) ? $versions[0]['final_valid_from'] : '';
   					$boxes[$key]['newest_version_id'] = isset($versions[0]['id']) ? $versions[0]['id'] : '';
   				}
   				return (array)$boxes;

   			case "version":
   				return (array)Boxes::getVersions($options['id']);

   			default: return null;
   		}
   	}
   	/**
   	 * overrides Ibulletin_Admin_BaseControllerAbstract::createRecord($name, $values)
   	 */
   	public function createRecord($name, $values) {

   		$texts = Ibulletin_Texts::getSet();

   		switch ($name) {
   			case "box":
		   		try {
		            $box_id = Boxes::editBox(null, $values['name']);
		            Boxes::editVersion(null, $box_id);
		            return true;
		        } catch(Boxes_Edit_Box_Unique_Name_Violation_Exception $e) {
		        	$this->infoMessage($texts->duplicate,'warning', array($values['name']));
		        }
		        return false;

   			default: return null;
   		}
   	}

   	/**
   	 * overrides Ibulletin_Admin_BaseControllerAbstract::updateRecord($name, $values)
   	 */
   	public function updateRecord($name, $values) {

   		$texts = Ibulletin_Texts::getSet();

   		switch ($name) {
   			case "box":
   				try {
   					if (!$values['id']) {
   						return false;
   					}
   					$res = Boxes::editBox($values['id'], $values['name'], $values['description']);
   					return $res;
   				} catch(Boxes_Edit_Box_Unique_Name_Violation_Exception $e) {
   					$this->infoMessage($texts->duplicate, 'warning', array($values['name']));
   				}
   				return false;

   			default: return null;
   		}
   	}

   	/**
   	 * overrides Ibulletin_Admin_BaseControllerAbstract::deleteRecord($name, $id)
   	 */
   	public function deleteRecord($name, $id) {
   		$texts = Ibulletin_Texts::getSet();
   		switch ($name) {
   			case "box":
   				if (!$id) {
   					return false;
   				}
   				$res = Boxes::deleteBox($id);
   				return $res;

   			case "version":
   				if (!$id) {
   					return false;
   				}
   				$res = Boxes::deleteVersion($id);
   				return $res;

   			default: return null;
   		}
   	}

    /**
   	 * Vypise seznam Boxuu se strucnym infem o nich a nabidne mozne akce
   	 */
    public function indexAction()
    {

    	$this->setAfterCreateUrl('box', 'index');
        $form = $this->processCreate('box');
        $this->view->create_form = $form;
         try {
            $grid = new Ibulletin_DataGrid(Boxes::getBoxesQuery());
            $grid->setEmptyText($this->texts->empty);
            $grid->setDefaultSort('name');
            $grid->setDefaultDir('ASC');

            $grid->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'bx.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    ))
                    ->addColumn('description', array(
                        'header' => $this->texts->title,
                        'field' => 'bx.description'
                    ))
                    ->addColumn('datum', array(
                        'header' => $this->texts->valid_from,
                        'field' => 'bx.datum',
                        'type' => 'datetime'
                    ))
                    ->addAction('delete', array(
                        'confirm' => $this->texts->confirm_delete,
                        'url' => $this->_helper->url('deletebox') . '/id/$box_id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                        ->addAction('version', array(
                        'url' => $this->_helper->url('editversion') . '/id/$id/',
                        'caption' => $this->texts->action_editversion,
                        'image' => 'time'
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('editbox') . '/id/$box_id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
            ));

             $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

    }


    /**
     * Smaze Box
     */
    public function deleteboxAction()
    {
        $id = $this->_request->getParam('id');
        if (!$id) {
        	$this->redirect('index');
        }
        $this->setAfterDeleteUrl('box', 'index');
        $this->processDelete('box', $id);

    }

    /**
     * Smaze verzi
     */
    public function deleteversionAction()
    {
        $box_id = $this->_request->getParam('box_id');
    	$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}
        $this->setAfterDeleteUrl('version', array('action' => 'editbox', 'id' => $box_id));
        $this->processDelete('version', $id);
    }

    /**
    * vytvori novou verzi
    */
    public function newversionAction()
    {
    	$texts = Ibulletin_Texts::getSet();

    	$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}

    	// spracovava se pouze odkaz
    	// nepouziva se spracovani pres processCreate() tudiz se nemusi implementovat createRecord()

    	$last_insert_id = Boxes::editVersion(null, $id);

    	if($last_insert_id === false){
    		$this->infoMessage($texts->error,'error');
    		$this->redirect(array('action' => 'editbox', 'id' => $id));
    	} else {
    		$this->infoMessage($texts->ok);
    		$this->redirect(array('action' => 'editversion', 'id' => $last_insert_id));
    	}

    }

    /**
    * Klonuje verzi
    */
    public function cloneversionAction()
    {
    	$texts = Ibulletin_Texts::getSet();

    	$box_id = $this->_request->getParam('box_id');
    	$id = $this->_request->getParam('id');
    	if (!$id || !$box_id) {
    		$this->redirect('index');
    	}

    	$clone_id = Boxes::cloneVersion($id);

    	if($clone_id === false){
    		$this->infoMessage($texts->error,'error');
    		//$this->redirect(array('action' => 'editbox', 'id' => $box_id));
    	} else {
    		$this->infoMessage($texts->ok);
    		//$this->redirect(array('action' => 'editversion', 'id' => $clone_id));
    	}

    	$this->redirect(array('action' => 'editbox', 'id' => $box_id));

    }

    /**
     * Edituje box
     */
    public function editboxAction()
    {
    	$texts = Ibulletin_Texts::getSet();

    	$id = $this->_request->getParam('id');
        if(!$id){
            $this->redirect('index');
        }

        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'editbox','id'=>$id), null, true,'editbox');
        $this->moduleMenu->setCurrentLocation('editbox');

        //$this->setAfterUpdateUrl('box', 'index');
        $this->setAfterUpdateUrl('box', array('action' => 'editbox', 'id' => $id));
        $this->view->form = $this->processUpdate('box', $id);

        $this->view->box_id = $id;

        try {
            $grid = new Ibulletin_DataGrid(Boxes::getBoxesVersionQuery($id));
            $grid->setDefaultSort('valid_from');


            $grid->addColumn('valid_from', array(
                        'header' => $this->texts->valid_from,
                        'field' => 'valid_from',
                        'type' => 'datetime'
                    ))
                    ->addColumn('description', array(
                        'header' => $this->texts->description,
                        'field' => 'description'
                    ))
                     ->addColumn('template', array(
                        'header' => $this->texts->template,
                        'field' => 'template'
                    ))

                    ->addAction('delete', array(
                        'confirm' => $texts->confirm_delete,
                        'url' => $this->_helper->url('deleteversion') . '/id/$id/box_id/$box_id',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                    ->addAction('clone', array(
                        'url' => $this->_helper->url('cloneversion') . '/id/$id/box_id/$box_id',
                        'caption' => $this->texts->action_clone,
                        'image' => 'clone'
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('editversion') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
            ));

             $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }


    }


    /**
     * Zobrazi editacni formular pro editaci verze
     * a pripadne zavola ulozeni prijateho formulare.
     *
     */
    public function editversionAction()
    {
    	$texts = Ibulletin_Texts::getSet();
        Ibulletin_Js::addJsFile('admin/collapse.js');

    	$id = $this->_request->getParam('id');
        if(!$id){
            $this->redirect('index');
        }

        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'editversion','id'=>$id), null, true,'editversion');
        $this->moduleMenu->setCurrentLocation('editversion');

        $data = $this->getVersionData($id);

        // TODO: zapouzdreni Ibulletin_Admin_ModuleMenu, addItem() nastavuje pouze $submenuSpecific ?
        $this->moduleMenu->forAll['editbox'] = array(
            		'title' => $this->texts->submenu_versions,
            		'params' => array('action' => 'editbox', 'id' => $data['box_id']),
            		'noreset' => false
        );

        // Predvyplnime
        $form = $this->getEditVersionForm($data);

        if($form->isValid($data)){
            $data = $this->saveVersionData($data);
        }

        $form = $this->getEditVersionForm($data);

        // Ziskame i zaznam boxu pro vypsani jmena
        $box_data = $this->getRecord('box', $data['box_id']);

        // nastavime titulek akce
        $this->setActionTitle('"'.$box_data['name'].'"');

        //cesta k souborům, neexistuje-li vytvorime ji
        $path = $this->config->boxes->static_basepath.DIRECTORY_SEPARATOR.$data['box_id'].DIRECTORY_SEPARATOR.$id;
        if (!file_exists($path)) {
            Utils::mkdir($path);
        }
        $this->view->path = $path;

        $this->view->form = $form;
        $this->view->box_id = $box_data['id'];

        // Jeste do view pridame seznam linku ke zobrazeni
        $this->renderLinks();
    }


    /**
     * Vrati objekt formulare pro editaci verze
     *
     * @return Zend_Form    Formular pro editaci verze
     */
    public function getEditVersionForm($data = null)
    {
       	$texts = Ibulletin_Texts::getSet();

        $urlHlpr = new Zend_View_Helper_Url();

        $form = new Form();
        $form->setMethod('post');

        $form->addElement('text', 'id', array(
            'label' => $texts->id,
            'class' => 'span1',
            'readonly' => 'readonly'
        ));

        $form->addElement('text', 'valid_from', array(
            'class' => 'datetimepicker',
            'autocomplete' => 'off',
            'label' => sprintf($texts->valid_from,$this->config->general->dateformat->medium)
        ));

        $bulletins = new Bulletins();
        $bulletins_sel = $bulletins->getBulletinListForSelect(true, true);
        $bulletins_sel = array('' => $this->texts->editversion->notspecified) + $bulletins_sel; // Spoji pole a nemeni klice jako array_merge
        // Pokud v datech neni pozadovany bulletin, je smazany, proto ho docasne pridame, aby se ulozenim neodpojil
        if(!isset($bulletins_sel[$data['bulletin_id']])){
            $bulletins_sel[$data['bulletin_id']] = $data['bulletin_id'].' - '.$texts->deleted;
        }
        $form->addElement(new Zend_Form_Element_Select(
            array(
                'name' => 'bulletin_id',
                'label' => $texts->bulletin,
                'multioptions' => $bulletins_sel
            )));

        $waves_sel = Invitationwaves::getListForSelect(true);
        $waves_sel = array('' => $this->texts->editversion->notspecified) + $waves_sel;
        $form->addElement(new Zend_Form_Element_Select(
            array(
                'name' => 'invitation_wave_id',
                'label' => $texts->invitation_wave,
                'multioptions' => $waves_sel
            )));

          $form->addDisplayGroup(array($form->getElement('valid_from'),$form->getElement('bulletin_id'),$form->getElement('invitation_wave_id')),
                        'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));


        // Template
        $tempates_sel = array();
        $dir = $this->config->paths->boxes_templates;

        if(file_exists($dir) && ($dirh = opendir($dir))){
          while($dir_element = readdir($dirh)){
            if($dir_element != '.' && $dir_element != '..' && !is_dir($dir.'/'.$dir_element)){
                 $tempates_sel[$dir_element] = $dir_element;
            }
          }
          unset($dir_element);
          closedir($dirh);
        }

        if (empty($data['content_id'])) {
            $tempates_sel = array('' => $this->texts->editversion->notspecified) + $tempates_sel;
            $form->addElement(new Zend_Form_Element_Select(
                    array(
                'name' => 'template',
                'label' => $texts->template,
                'multioptions' => $tempates_sel
            )));

            // Prevedeni z template na editovatelny
            //if(!empty($data['template'])){
            $form->addElement('submit', 'convert_from_template', array(
                'name' => 'convert_from_template',
                'class' => 'inline-no-label',
                'label' => $texts->convert_from_template,
            ));
            //}
            $form->addDisplayGroup(array($form->getElement('template'), $form->getElement('convert_from_template')), 'grp2', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));
        }

        if (empty($data['template'])) {
            $form->addElement(new Zend_Form_Element_Select(
                    array(
                'name' => 'content_id',
                'label' => $texts->content_id,
                'multioptions' => array('' => $this->texts->editversion->notspecified) + $this->boxableContent()
            )));
        }

        // Description
        $form->addElement('textarea', 'description', array(
                'label' => $texts->description,
                'rows' => '3'
            ));

        // HTML je mozne editovat pouze v pripade, ze neni nastaven template
        if(empty($data['template']) && empty($data['content_id'])){
            $form->addElement('textarea', 'content', array(
                    'id' => 'content_editarea',
                    'label' => $texts->content,
                    'class' => 'editarea'
                ));
        }

        // Neprovadet TIDY
        $form->addElement('checkbox', 'tidy_off', array(
            'label' => $texts->tidy_off,
        ));

        $form->addElement('submit','save_version_data', array(
                'name' => 'save_version_data',
                'label' => $texts->submit,
                'class' => 'btn-primary'
            ));

        $links = new Links();
        if(empty($data['template']) && empty($data['content_id']))
             $form->getElement('content')->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));

        // Validacii vyplnime formular z pole
        $form->isValid($data);

        return $form;
    }

    /**
     * Ziska a vrati data verze - pokud byl odeslan formular,
     * jsou vracena data z formulare.
     *
     * @param int       ID boxu
     * @return array    Pole obsahujici data verze
     */
    public function getVersionData($id)
    {
        $config = Zend_Registry::get('config');

        // Ziskame data verze z DB
        $version_data = Boxes::getVersion($id);

        // Valid from se nesmi do formulare zapsat, pokud je vyplnena zvaci vlna, nebo bulletin
        if(!empty($version_data['valid_from'])
                || (empty($version_data['bulletin_id']) && empty($version_data['invitation_wave_id']))){
            $valid_from = new Zend_Date($version_data['valid_from'], Zend_Date::ISO_8601);
            $version_data['valid_from'] = $valid_from->toString($config->general->dateformat->medium);
        }
        else{
            $version_data['valid_from'] = null;
        }


        if((isset($_POST['save_version_data']) || isset($_POST['convert_from_template'])) && isset($_POST['id']) && $_POST['id'] == $id){
            // Bool z tidy_off - primo nacteme pres request kvuli checkboxu
            $version_data['tidy_off'] = $this->getRequest()->getParam('tidy_off');

            return $_POST + $version_data;
        }
        elseif(!empty($this->version_data) && isset($this->version_data['id']) && $this->version_data['id'] == $id){
            return $this->version_data;
        }
        else{
            $this->version_data = $version_data;
            return $version_data;
        }
    }

    /**
     * Ulozi data prijata z editacniho formulare.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveVersionData($data)
    {

    	$texts = Ibulletin_Texts::getSet();

    	$config = Zend_Registry::get('config');

        if(!isset($data['save_version_data']) && !isset($data['convert_from_template'])){
            // Neni co ukladat, data nebyla odeslana
            return $data;
        }

        $tidyOnceOff = false;

        // Zapiseme data verze
        $id = $data['id'];

        if((string)$data['valid_from'] != ''){
            $valid_from = new Zend_Date($data['valid_from'], $config->general->dateformat->medium);
            $data['valid_from'] = $valid_from->toString($config->general->dateformat->medium);
        }
        else{
            $valid_from = new Zend_Db_Expr('null');
            $data['valid_from'] = null;
        }

        if(!empty($data['bulletin_id']) && $data['valid_from'] === null){
            $bulletin_id = $data['bulletin_id'];
        }
        else{
            $bulletin_id = new Zend_Db_Expr('null');
            $data['bulletin_id'] = null;
        }

        if(!empty($data['invitation_wave_id']) && $data['valid_from'] === null && $data['bulletin_id'] === null){
            $invitation_wave_id = $data['invitation_wave_id'];
        }
        else{
            $invitation_wave_id = new Zend_Db_Expr('null');
            $data['invitation_wave_id'] = null;
        }

        $description = $data['description'];

        $content = array_key_exists('content', $data) ? $data['content'] : null;
        $data['content'] = $content; // Aby se prenesly upravy zpet do formulare

        if($data['content_id'] == "") {
            $data['content_id'] = null;
        }

        // Pokud ma byt box preveden z template na editovatelny, musime do content nacist obsah template
        if($data['content_id']) {
           $template = '';
           $content = '';
        }
        elseif(!isset($data['convert_from_template']) || empty($data['template'])){
            $template = empty($data['template']) ? new Zend_Db_Expr('null') : $data['template'];
            $data['content_id'] = null;
        }
        else{
            if(is_readable($config->paths->boxes_templates.$data['template'])){
                $a = file($config->paths->boxes_templates.$data['template']);
                $content = join("\r\n", $a);
                $tidyOnceOff = true;
            }
            else{
                $this->infoMessage($texts->notreadable_template,'warning', array($config->paths->boxes_templates.$data['template']));
                $content = '';
                Phc_ErrorLog::warning('BoxesController::saveVersionData()', 'Nepodarilo se nacist zadany template dynamickeho boxu pri prevadeni boxu z template na editovatelny. '.
                    "version_id: $id, template: ".
                    $config->paths->boxes_templates.$data['template']."."
                    );
            }
            $data['content'] = $content;
            $data['template'] = '';
            $template = '';
            $data['content_id'] = null;
        }


        // ZAPISEME
        $newData = Boxes::editVersion($id, null, $valid_from, $bulletin_id, $invitation_wave_id, $content,
                           $description, null, $template, $data['tidy_off'], $tidyOnceOff,$data['content_id']);

        $data = array_merge($data, $newData);

        $this->infoMessage($texts->saved);

        return $data;
    }


    /**
     * Vráti seznam boxíkovatelných contentů, tzn. které mají views *_100.phtml
     * @return Array
     */
    public function boxableContent() {
        $dir = $this->config->paths->content_templates;
        $types = Contents::getTypes();

        $filters = array();
        foreach($types as $t) {
            //existuje-li views s _100, lze content zobrazovat v boxiku, priradi class_name do filtru.
            if (file_exists($dir.strtolower($t['types']).'_100.phtml')) {
                $filters[] = "Ibulletin_Content_".$t['types'];
            }
        }

        $db = Zend_Registry::get('db');
        $contents = $db->fetchAll(Contents::getQuery($filters));
        $list = array();
        $list[""] = "";
        foreach($contents as $c) {
            $list[$c['id']] = $c['name'];
        }
        return $list;
    }



}
