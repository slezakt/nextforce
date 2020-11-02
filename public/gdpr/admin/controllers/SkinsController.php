<?php
/**
 * Modul pro spravu skinu.
 *
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_SkinsController extends Ibulletin_Admin_BaseControllerAbstract {

	protected $fu = null;
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
	 * index akce
	 *
	 */
    public function indexAction() {

        $grid = new Ibulletin_DataGrid($this->getRecords('skin'));

        $imgs_yes_no = array(0 => 'remove-color', 1 => 'ok-color');

        $grid->addColumn('name',array(
        ));
        $grid->addColumn('active',array(
            'header' => $this->texts->active,
            'align'=>'center',
            'imgs' => $imgs_yes_no,
        ));

        $grid->addAction('delete',array(
            'equals' => array('name', 'default'),
            'confirm' => $this->texts->index->confirm_delete,
            'url' =>  $this->_helper->url('delete') . '/id/$name/',
            'caption' => $this->texts->action_delete,
            'image' => 'remove'
        ));
        $grid->addAction('duplicate',array(
            'url' =>  $this->_helper->url('duplicate') . '/id/$name/',
            'caption' => $this->texts->action_duplicate,
            'image' => 'clone'
        ));
        $grid->addAction('edit',array(
            'equals' => array('name', 'default'),
            'url' =>  $this->_helper->url('edit') . '/id/$name/',
            'caption' => $this->texts->action_edit,
            'image' => 'pencil'
        ));

        $this->view->grid = $grid->process();

    }

    /**
     * duplicate akce
     *
     */
    public function duplicateAction() {

    	$id = $this->_request->getParam('id');
    	$texts = Ibulletin_Texts::getSet();

    	// input check
    	if (!Skins::isValid($id)) {
    		$this->infoMessage($texts->notfound, 'error', array($id));
    		$this->redirect('index');
    	}

    	// rewrite or add config key
    	try {
	    	$this->fu = new Ibulletin_FileUploader();
	    	$this->fu->setBasePath(Skins::getBasePath());

	    	$new_id = 'copy_'.$id;

	    	$this->fu->mkdir($new_id);
	    	$res = $this->fu->rcopy($id, $new_id);
	    	$this->fu->rchmod($new_id);

	    	if (!$res) {
	    		$this->infoMessage($texts->notduplicated, 'error', array($id));
	    	}

	    	$this->infoMessage($texts->duplicated, 'info', array($id));

    	} catch (Exception $e) {
    		$this->infoMessage($texts->notduplicated, 'error', array($id));
    	}
    	$this->redirect('index');

    }

    /**
     * edit akce
     *
     */
    public function editAction()
    {

        $id = $this->_request->getParam('id');
    	$texts = Ibulletin_Texts::getSet();

       //prida do menu odkaz editace
        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

    	// input check
    	if (!Skins::isValid($id)) {
    		$this->infoMessage($texts->notfound, 'error', array($id));
    		$this->redirect('index');
    	}

        $this->fu = new Ibulletin_FileUploader($id, Skins::getBasePath());
    	$this->view->path = Skins::getBasePath().DIRECTORY_SEPARATOR.$id;

    	$this->view->form = $this->processUpdate('skin', $id);
    	$this->view->fileUploader = $this->fu;

    }

    /**
     * delete akce
     */
    public function deleteAction() {

    	$id = $this->_request->getParam('id');
    	$texts = Ibulletin_Texts::getSet();

    	// input check
    	if (!Skins::isValid($id)) {
    		$this->infoMessage($texts->notfound, 'error', array($id));
    		$this->redirect('index');
    	}

    	$this->fu = new Ibulletin_FileUploader($id, Skins::getBasePath());

    	$this->view->form = $this->processDelete('skin', $id);
    }

    /**
     * ziska seznam skinu
     *
     * @see Ibulletin_Admin_CRUDControllerAbstract::getRecords()
     */
    public function getRecords($name, $options = array()) {
    	switch ($name) {
    	case "skin":
    		return Skins::findAll();
    	default: return null;
    	}
    }

    /**
     * ziska parametre skinu
     *
     * @see Ibulletin_Admin_CRUDControllerAbstract::getRecord()
     */
    public function getRecord($name, $id) {
        switch ($name) {
        case "skin":
        	$res = array('name' => $id ,'id' => $id);
			return $res;
        default: return null;
        }
    }

    /**
     * zapise texty do souboru
     *
     * @see Ibulletin_Admin_CRUDControllerAbstract::updateRecord()
     */
    public function updateRecord($name, $values) {
    	switch ($name) {
    		case "skin" :
    			try {
    				 $id = $values['id'];
    				 $new_id = $values['name'];

    				 if ($id == $name) { return true; } // do nothing

    				 // store current path
    				 $old_path = $this->fu->getBasePath();
    				 // change base path one level up
    				 $this->fu->setBasePath(Skins::getBasePath());


    				 // try to rename directory
    				 if (!$this->fu->rename($id, $new_id)) {
    				 	// rename failed, set old path
    				 	$this->fu->setBasePath($old_path);
    				 	return false;
    				 }
    				 // chmod
    				 $this->fu->rchmod($new_id);
    				 // follow with redirect to new id
    				 $this->setAfterUpdateUrl('skin', array('action' => 'edit', 'id' => $new_id));
    			} catch (Exception $e) {
    				Phc_ErrorLog::warning('skins', $e->getMessage());
    				return false;
    			}
    			return true;
    		default: return null;
    	}
    }

    public function deleteRecord($name, $id) {
    	switch ($name) {
    		case "skin":
    			try {
    				// redirect to index
    				$this->setAfterDeleteUrl('skin', array('action' => 'index'));

    				// try delete dir and update bulletin.skin
    				$this->fu->setBasePath(Skins::getBasePath());
    				$res = $this->fu->rrmdir($id) && Skins::delete($id);
    				if (!$res) {
    					return false;
    				}
    			} catch (Exception $e) {
    				Phc_ErrorLog::warning('skins', $e->getMessage());
    				return false;
    			}

    			return true;
    		default: return null;
    	}
    }

    /**
     * pripravi formular
     *
     * @see Ibulletin_Admin_CRUDControllerAbstract::getForm()
     */
    public function getForm($name) {
    	switch ($name) {
    		case "skin":

    			$form = parent::getForm($name);
    			$form->addElement(new Zend_Form_Element_Text('name', array(
    				'label' => $this->texts->name,
    				'required' => true,
    				'validators' => array(
    					array('regex', false, array(
    						'pattern'   => '/^[a-zA-Z0-9_]+$/i',
    						'messages'  =>  $this->texts->validators->regex))
    				),
    				'filters' => array(
    					array('StringTrim'),
    					array('Callback', array('callback' => 'strtolower'))
    				),
    			)));

    			return $form;
    		default: return null;
    	}

    }

}
