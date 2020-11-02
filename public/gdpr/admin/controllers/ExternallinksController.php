<?php
/**
 * Sprava externich linku
 * @author andrej.litvaj@pearshealthcyber.com
 */
class Admin_ExternalLinksController extends Ibulletin_Admin_BaseControllerAbstract
{

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
    public function init() {
        parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
           /* 'add' => array('title' => $this->texts->submenu_add, 'params' => array('action' => 'add'), 'noreset' => false),*/
        );

        }

    public function updateRecord($name, $values) {
        switch ($name) {
            case "link" :
                $links = new ExternalLinks;
            return (boolean)$links->saveLink($values);
            default: return null;
        }
    }

    public function createRecord($name, $values) {
        switch ($name) {
            case "link":
                $links = new ExternalLinks;
            return (boolean)$links->saveAddLink($values);
            default: return null;
        }
    }

    public function deleteRecord($name, $id) {
        switch ($name) {
            case "link":
                $links = new ExternalLinks;
                return (boolean)$links->saveLink(array('id' => $id, 'deleted' => new Zend_Db_Expr('NOW()')));
            default: return null;
        }
    }

    public function getRecord($name, $id) {
        switch ($name) {
            case "link":
                $links = new ExternalLinks;
                // TODO: udelat funkci v modelu pro ziskani jednoho zaznamu
                $data = $links->getLinks($id);
                return array_pop($data);
            default: return null;
        }
    }

    public function getRecords($name, $options = array()){
        switch ($name) {
            case "link":
                $links = new ExternalLinks;
                return $links->getLinks();
            default: return null;
        }
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getForm($name) {
        switch ($name) {
            case "link":
                $form = parent::getForm($name);

                // nazev clanku,valid na delku min.3 znaky
                $name = new Zend_Form_Element_Text('name');
                $name->setLabel($this->texts->name);
                $name->setRequired(true);

                $validatorEmpty = new Zend_Validate_NotEmpty();
                $validatorEmpty->setMessages(array(
                    Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty
                ));
			    $name->addValidator($validatorEmpty,true);

                //validatory pro nazev
                $nameValidatorLength = new Zend_Validate_StringLength(3);
                $nameValidatorLength->setMessages(array(
                    Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort
                ));
                $name->addValidator($nameValidatorLength);

                // odkaz, musi byt jen pismena
                $foreign_url = new Zend_Form_Element_Text('foreign_url');
                $foreign_url->setLabel($this->texts->foreign_url);
                $foreign_url->setRequired(true);
                $foreign_url->addValidator($validatorEmpty);

                $form->addElements(array($name, $foreign_url));
                return $form;
            default: return null;
        }
    }


    public function getCreateForm($name) {
        switch ($name) {
            case "link":
                $form = parent::getCreateForm($name);
                $form->setFormInline(true);

                return $form;

            default: return null;
        }
    }

    public function getUpdateForm($name) {
        switch ($name) {
            case "link":
                $form = parent::getUpdateForm($name);


                // "resetovani" poctu pouziti linku, musi byt cislo
                $used_times = new Zend_Form_Element_Text('used_times');
                $used_times->setLabel($this->texts->used_times);

                $usedTimesValidatorInt = new Zend_Validate_Int();
                $usedTimesValidatorInt->setMessages(array(
                    Zend_Validate_Int::NOT_INT => $this->texts->validators->int
                ));
                $used_times->addValidator($usedTimesValidatorInt);
                $used_times->addFilter(new Zend_Filter_Int());

                $form->addElements(array($used_times));

                return $form;
            default: return null;
        }
    }

    /**
     * zobrazi linky
     */
    public function indexAction() {

        $this->setLastVisited();

         try {
            $grid = new Ibulletin_DataGrid(ExternalLinks::getLinksQuery());
            $grid->setEmptyText($this->texts->index->empty);
            $grid->setDefaultSort('id');

            $grid->addColumn('id', array(
                        'header' => $this->texts->id,
                    ))
                    ->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            "autocomplete" => true
                        )
                    ))
                    ->addColumn('foreign_url', array(
                        'header' => $this->texts->foreign_url,
                        'field' => 'foreign_url'
                    ))
                    ->addColumn('used_times', array(
                        'header' => $this->texts->used_times,
                        'align' => 'center',
                        'field' => 'used_times',
                    ))
                    ->addAction('delete', array(
                        'notempty' => 'deleted',
                        'confirm' => $this->texts->confirm_delete,
                        'url' =>  $this->_helper->url('delete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                    ->addAction('renew', array(
                        'empty' => 'deleted',
                        'confirm' => $this->texts->confirm_renew,
                        'url' =>  $this->_helper->url('renew').'/id/$id/',
                        'caption' => $this->texts->action_renew,
                        'image' => 'refresh'
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('edit') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
            ));

            $this->view->grid = $grid->process();

            // create form
            $this->setAfterCreateUrl('link', 'index');
            $this->view->form = $this->processCreate('link');

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

    /**
     * prida link
     */
/*    public function addAction() {

    	$this->setAfterCreateUrl('link','index');

        $this->view->form = $this->processCreate('link');
    }
*/
    /**
     * edituje link
     */
    public function editAction() {

        $id = $this->_request->getParam('id');
        Ibulletin_Js::addJsFile('admin/collapse.js');

        if (!$id) {
            $this->redirect('index');
        }

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        $this->setAfterUpdateUrl('link','index');

        $this->view->form = $this->processUpdate('link', $id);
    }

    /**
     * smaze link
     */
    public function deleteAction() {

        $id = $this->_getParam('id');

        if (!$id) {
            $this->redirectUri($this->getLastVisited());
        }

        $this->setAfterDeleteUrl('link', null, $this->getLastVisited());
        $result = $this->processDelete('link', $id);

        //$this->view->payload = array('data'=> array('id' => $id), 'result' => $result);

    }

    /**
     * obnovilink
     */
    public function renewAction() {

        $id = $this->_getParam('id');

        $result = true;
        try {
            $links = new ExternalLinks;
            $result = (boolean)$links->saveLink(array('id' => $id, 'deleted' => NULL));
        } catch (Exception $e) {
            $result = false;
            Phc_ErrorLog::warning('Admin::ExternallinksController', $e);
        }

        if ($result) {
            $this->infoMessage($this->texts->renew->success, 'success', array($id));
        } else {
            $this->infoMessage($this->texts->renew->error, 'error', array($id));
        }

        $this->redirectUri($this->getLastVisited());

        //$this->view->payload = array('data'=> array('id' => $id), 'result' => $result);

    }

}
?>
