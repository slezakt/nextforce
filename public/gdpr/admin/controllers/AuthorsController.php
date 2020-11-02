<?php
/**
 * Modul pro spravu autoru.
 *
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_AuthorsController extends Ibulletin_Admin_BaseControllerAbstract {

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
	public function init() {
        parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
        	/*'add' => array('title' => $this->texts->submenu_add, 'params' => array('action' => 'add'), 'noreset' => false),*/
        );
    }

    public function updateRecord($name, $values) {
        switch ($name) {
        case "author" :
            $authors = new Authors;
            return $authors->saveAuthor($values, 'update');
        default: return null;
        }
    }

    public function createRecord($name, $values) {
        switch ($name) {
        case "author":
            $authors = new Authors;
            return $authors->saveAuthor($values, 'insert');
        default: return null;
        }
    }

    public function deleteRecord($name, $id) {
        switch ($name) {
        case "author":
            $authors = new Authors;
            return $authors->deleteAuthor($id);
        default: return null;
        }
    }

    public function getRecord($name, $id) {
        switch ($name) {
        case "author":
            $authors = new Authors;
            return $authors->getAuthor($id);
        default: return null;
        }
    }

    public function getRecords($name, $options = array()){
        switch ($name) {
        case "author":
            $authors = new Authors;
            return $authors->getAuthorsWithArticles();
        default: return null;
        }
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getForm($name) {

        switch ($name) {
        case "author":
            $form = parent::getForm($name);

            // jmeno autora, valid na delku min.3 znaky
            $el_name = new Zend_Form_Element_Text('name', array('size' => '45'));
            $el_name->setLabel($this->texts->name);
            $el_name->setRequired(true);
            //validatory pro nazev
            $val = new Zend_Validate_StringLength(3);
            $val->setMessages(array(
                Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort
                )
            );
            $el_name->addValidator($val);

            $val = new Zend_Validate_NotEmpty();
            $val->setMessages(array(
                Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty
                )
            );
            $el_name->addValidator($val);

            $form->addElements(array($el_name));
            return $form;
        default: return null;
        }
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getCreateForm($name)
     */
    public function getCreateForm($name) {

        switch ($name) {
            case "author":
                $form = parent::getCreateForm($name);
                // custom form setup
                $form->setFormInline(true);

                return $form;
            default: return null;
        }
    }

    /**
    * zobrazi autory a formular pro pridani
    */
    public function indexAction() {

       try {
            $grid = new Ibulletin_DataGrid(Authors::getAuthorsQuery());
            $grid->setEmptyText($this->texts->index->empty);
            $grid->setDefaultSort("id");


            $grid->addColumn("id", array(
                        "header" => $this->texts->id,
                    ))
                    ->addColumn("name", array(
                        "header" => $this->texts->name,
                        "field" => "a.name",
                        "filter" => array(
                            "type" => "expr",
                            "datatype" => "string",
                            "autocomplete" => true
                        )
                    ))
                    ->addColumn("articles", array(
                        "header" => $this->texts->articles
                    ))
                    ->addAction("delete", array(
                        'confirm' => $this->texts->confirm_delete,
                        'url' => $this->_helper->url('delete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                    ->addAction("edit", array(
                        'url' => $this->_helper->url('edit') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
            ));

            $this->view->grid = $grid->process();

            // create form
            $this->setAfterCreateUrl('author', 'index');
            $this->view->form = $this->processCreate('author');

       } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
       }


    }

    /**
    * prida autora
    */
 /*   public function addAction() {

    	$this->setAfterCreateUrl('author','index');
    	$this->view->form =$this->processCreate('author');;
    }
   */
    /**
    * edituje autora
    */
    public function editAction() {

        $id = $this->_request->getParam('id');
        if (!$id) {
        	$this->redirect('index');
        }

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        $this->setAfterUpdateUrl('author','index');

        $this->view->form = $this->processUpdate('author', $id);;
    }

    /**
     * smaze autora
     */
    public function deleteAction() {

        $id = $this->_request->getParam('id');
        if (!$id) {
        	$this->redirect('index');
        }

        $this->setAfterDeleteUrl('author','index');
        $this->processDelete('author', $id);
    }

}
