<?php
/**
 * Sprava resources, predevsim globalnich.
 *
 * @author petr.skoda@pearshealthcyber.com
 */
class Admin_ResourcesController extends Ibulletin_Admin_BaseControllerAbstract
{

	public function init() {
        parent::init();
        $this->submenuAll = array(
        	'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'add' => array('title' => $this->texts->submenu_add, 'params' => array('action' => 'add'), 'noreset' => false),
            'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => false),
        );
    }

    public function deleteRecord($name, $id) {
    	switch ($name) {
    		case "res":
                $config = Zend_Registry::get('config');
                $res = (array)Resources::get($id);
                //presune soubor do trashe -> slozka deleted dle configu
                rename($res['path'],$config->paths->resources_deleted.basename($res['path']));
                Resources::delete($id);
                return true;
    		default: return null;
    	}
    }

    public function getRecord($name, $id) {
        switch ($name) {
        case "res":
            return (array)Resources::get($id);
        default: return null;
        }
    }

    /**
     * Force download
     * @param id resources $id
     */
    public function download($id) {

	   $this->_helper->viewRenderer->setNoRender(true);
       $res = Resources::get($id);

       //jestlize je soubor vymazan -> download z kose
       if ($res['deleted']) {
           $config = Zend_Registry::get('config');
           $file = $config->paths->resources_deleted.basename($res['path']);
       } else {
           $file = $res['path'];
       }
       
       if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_end_clean();
            readfile($file);
       }
    }

   /**
    * Vrati formular pro editaci resource
    * @param Zend_Form_Element_Text $name
    * @param boolean $update true edit form
    * @param boolean $content true resources content
    * @param int $id_resource ID resources
    * @return Form
    */
    public function getForm($name,$update = false,$content = false,$id_resource = null) {
        switch ($name) {
        case "res":

            $form = parent::getForm($name);
            $config = Zend_Registry::get('config');

			// nazev zvaci vlny
            $name = new Zend_Form_Element_Text(array(
	            'name' => 'name',
	            'maxlength' => 50,
	            'label' => $this->texts->name
            ));
            $name->setRequired(true)
                   ->setAttrib('size',40);

            $emptyValidator = new Zend_Validate_NotEmpty();
            $emptyValidator->setMessages(array(
            	Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty
            ));
            $name->addValidator($emptyValidator);

            $order = new Zend_Form_Element_Text(array(
                'name' => 'order',
                'label' => $this->texts->order,
                'filters'  => array('Int'),
                //'validators' => array(
                //    array('Between', true, array('min' => 1, 'max' => $this->getMaxOrder()))
                //)
            ));

            $bulletin_id = null;

            if (!$content) {
                    // bulletin
                    $bu = new Bulletins();
                    $bulletin_list = $bu->getBulletinListForSelect(true, false, null, null, true);

                    $bulletin_id = new Zend_Form_Element_Select(array(
                        'name' => 'bulletin_id',
                        'label' => $this->texts->bulletin,
                        'multioptions' => $bulletin_list
                    ));
                    $bulletin_id->addFilter(new Zend_Filter_Int());
            }

            $content_id = new Zend_Form_Element_Hidden('content_id');
            $content_id->addFilter(new Zend_Filter_Null());

            $description = new Zend_Form_Element_Textarea('description');
            $description->setLabel($this->texts->description)
                        ->setAttrib('cols',40)
                        ->setAttrib('rows',7);

            $file = new Zend_Form_Element_File('file');
            $file->setLabel($this->texts->file);

            //paklize editujeme content, nastavime jeho slozku (jestli existuje), jinak nastavime globalni
            if ($content) {
                $res = Resources::get($id_resource);
                $file->setDestination(dirname($res['path']));
            } else {
                 $file->setDestination($config->paths->resources_global);
            }

            if (!$update) {
                $file->addValidator('Count', false, array('min' => 1, 'max' => 1,
                    "messages" => array(Zend_Validate_File_Count::TOO_FEW => $this->texts->validators->isempty)));
            }

            $id = new Zend_Form_Element_Hidden('id');

            $submit = new Zend_Form_Element_Submit($this->texts->edit->submit);
            $submit->setAttrib('class','btn-primary');

            $form->addElements(array(
	            $name,
                $order,
	            $bulletin_id,
                $content_id,
                $description,
                $file,
                $id,
                $submit
            ));

            return $form;
        default: return null;
        }
    }


    public function grid($select) {
        $grid = new Ibulletin_DataGrid($select);
        $grid->setDefaultSort('order');
        $grid->setDefaultDir('asc');
        $grid->setEmptyText($this->texts->index->empty);

        $grid->addColumn('id', array(
                    'header' => $this->texts->id,
                ))
                ->addColumn('order', array(
                    'header' => $this->texts->order,
                ))
                ->addColumn('name', array(
                    'header' => $this->texts->name,
                    'field' => 'r.name',
                    'filter' => array(
                        'autocomplete' => true,
                        'type' => 'expr',
                        'datatype' => 'string'
                    )
                ))
                ->addColumn('filename', array(
                    'header' => 'filename',//$this->texts->filename,
                    'field' => 'filename'
                ))
                ->addColumn('description', array(
                    'header' => $this->texts->description,
                    'field' => 'description'
                ))
                ->addColumn('bulletin', array(
                    'header' => $this->texts->bulletin,
                    'field' => 'b.name'
                ))
                ->addColumn('content_id', array(
                    'header' => $this->texts->content_id,
                    'field' => 'content_id'
                ))
                ->addColumn('content', array(
                    'header' => $this->texts->content_name,
                    'field' => 'content.name',
                    'filter' => array(
                        'autocomplete' => true,
                        'type' => 'expr',
                        'datatype' => 'string'
                    )
                ))
                ->addAction('download', array(
                        'url' => $this->_helper->url('index') . '/download/$id/',
                        'caption' => 'Download $filename',
                        'image' => 'download-alt'
                ));

        return $grid;
    }

   /**
    * Odstranene resources
    */
    public function deletedAction() {

        try {
            $grid = $this->grid(Resources::getResourcesQuery(true));

            $grid->addAction('undelete', array(
                    'confirm' => $this->texts->confirm_undelete,
                    'url' => $this->_helper->url('undelete') . '/id/$id/',
                    'caption' => $this->texts->action_undelete,
                    'image' => 'refresh'
             ));
            $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

    }

    /**
    * Zobrazi resources
    */
    public function indexAction() {

        $download = $this->_request->getParam('download');
        if ($download) {
            //force download
            $this->download($download);
        }

        try {
            $grid = $this->grid(Resources::getResourcesQuery());

            $grid->addAction('delete', array(
                        'confirm' => $this->texts->confirm_delete,
                        'url' => $this->_helper->url('delete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('edit') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
                    ));

            $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

    }

    /**
     * Prida resource
     */
    public function addAction() {

        try {
            $form = $this->getForm('res');
            $form->getElement('order')->setValue(1);
        } catch (Zend_File_Transfer_Exception $ex) {
            $this->infoMessage($ex->getMessage(), 'warning');
            Phc_ErrorLog::warning('warning', $ex->message());
            $this->redirect('index');
        }

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            try {
                if ($form->file->isUploaded()) {
                    $form->file->receive();
                    Utils::chmod($form->file->getFileName(),Utils::FILE_PERMISSION);
                    Resources::update(
                                    array('content_id' => $form->getValue('content_id'), 'bulletin_id' => $form->getValue('bulletin_id'),
                                        'name' => $form->getValue('name'),'path'=>$form->file->getFileName(),'description' => $form->getValue('description'), 'order' => $form->getValue('order')));
                    $this->infoMessage($this->texts->add->saved);
                } else {
                    $this->infoMessage($this->texts->add->notsaved, 'warning');
                }
            } catch (Zend_File_Transfer_Exception $ex) {
                $this->infoMessage($ex->getMessage());
                Phc_ErrorLog::error('warning', $ex->getMessage());
            }
            $this->redirect('index');

        }

        $this->view->form = $form;
    }

    /**
    * Edituje resource
    */
    public function editAction() {

        $id = $this->_request->getParam('id');
        if (!$id) {
            $this->redirect('index');
        }

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        $rescs = $this->getRecord('res', $id);

        $this->view->resource = $rescs;

        //test content resources
        if ($rescs["content_id"]) {
            $content = true;
        } else {
            $content = false;
        }

        try {
            $form = $this->getForm('res', true, $content,$id);
        } catch (Zend_File_Transfer_Exception $ex) {
            $this->infoMessage($ex->getMessage(), 'warning');
            Phc_ErrorLog::warning('warning', $ex->message());
            $this->redirect('index');
        }

        $form->populate($rescs);


        // Pokud byl vyplnen deleted bulletin, musime jej pridat do seznamu kvuli konzistenci
        if($form->getElement('bulletin_id')){
            $multioptions = $form->getElement('bulletin_id')->getMultioptions();
            if(!isset($multioptions[$rescs['bulletin_id']])){
                $form->getElement('bulletin_id')->addMultiOption(
                    $rescs['bulletin_id'], $rescs['bulletin_id'].' - '.$this->texts->edit->deleted
                );
                $form->getElement('bulletin_id')->setValue($rescs['bulletin_id']);
            }
        }


        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            $data = array('content_id' => $form->getValue('content_id'), 'bulletin_id' => $form->getValue('bulletin_id'),
                'name' => $form->getValue('name'),'description' => $form->getValue('description'), 'order' => $form->getValue('order'));

            try {
                if ($form->file->isUploaded()) {

                    //odstrani puvodni soubor
                    unlink($rescs["path"]);

                    $form->file->receive();
                    Utils::chmod($form->file->getFileName(),Utils::FILE_PERMISSION);
                    //pridat cestu pro databazi
                    $data['path'] = $form->file->getFileName();
                    $this->infoMessage($this->texts->edit->file_saved);
                }
            } catch (Zend_File_Transfer_Exception $ex) {
                $this->infoMessage($ex->getMessage());
                Phc_ErrorLog::error('warning', $ex->getMessage());
            }

            if (Resources::update($data, $id))
                    $this->infoMessage ($this->texts->edit->saved);
            else {
                 $this->infoMessage ($this->texts->edit->notsaved,'warning');
            }

            $this->redirect('index');
        }



        $this->view->form = $form;
    }

    /**
     * Undelete resource
     */
    public function undeleteAction() {

        $id = $this->_request->getParam('id');
        if (!$id) {
            $this->redirect('index');
        }

        $config = Zend_Registry::get('config');

        try {
          $res = Resources::get($id);
          //presune soubor zpet z trashe
          rename($config->paths->resources_deleted.basename($res['path']),$res['path']);
         //obnovi soubor -> clear timestamp
         Resources::update(array('deleted' => null),$id);
            $this->infoMessage($this->texts->undelete->undelete);
        } catch (Exception $ex) {
           Phc_ErrorLog::warning('warning', $ex->message());
           $this->infoMessage($this->texts->undelete->notundelete,'error');
        }

        $this->redirect('index');
    }

    /**
    * Smaze resource
    */
    public function deleteAction() {

    	$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}

    	$this->setAfterDeleteUrl('res','index');
    	$this->processDelete('res', $id);
    }

    /**
     * vraci nejvyssi order v tabulce resources
     * @return int
     */
    private function getMaxOrder() {
        return (int)$this->db->select()
            ->from('resources', array(new Zend_Db_Expr('MAX("order")')))
            ->query()->fetchColumn();
    }
}
?>