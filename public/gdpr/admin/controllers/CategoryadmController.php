<?php

class Admin_CategoryController_Exception extends Exception {}

/**
 *	Modul pro spravu kategorii.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>; Andrej Litvaj, <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_CategoryadmController extends Ibulletin_Admin_BaseControllerAbstract
{

	/**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
	public function init() {
		parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
        	/*'add' => array('title' => $this->texts->submenu_add, 'params' => array('action' => 'add'), 'noreset' => false),*/
            'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => false),
        );
    }

    public function updateRecord($name, $values) {
    	switch ($name) {
    		case "category" :
    			$id = $values['id'];
    			unset($values['id']);
                try {
                    $res = Categories::update($id, $values,true);
                } catch (Exception $e) {
                    $this->infoMessage('Duplicate URL name', 'warning');
                    return null;
                }

    			return $res;
    		default: return null;
    	}
    }

    public function createRecord($name, $values) {
    	switch ($name) {
    		case "category":
                $values['url_name'] = Utils::slugify($values['name'],'`');
                $values['order'] = Categories::getHighestOrder() + 1;
    			try {
                    $res = Categories::insert($values);
                } catch (Exception $e) {
                    $this->infoMessage('Duplicate URL name', 'warning');
                    return null;
                }


                return (boolean)$res;
    		default: return null;
    	}
    }

    public function deleteRecord($name, $id) {
    	switch ($name) {
    		case "category":
    			return Categories::delete($id);
    		default: return null;
    	}
    }

    public function getRecord($name, $id) {
    	switch ($name) {
    		case "category":
    			return Categories::getCategory($id);
    		default: return null;
    	}
    }

    public function getRecords($name, $options = array()) {
    	switch ($name) {
    		case "category":
		        if (!empty($options['only_deleted'])) {
		        	$categories = Categories::getList(true, null, null, true);
		        } else {
		        	$categories = Categories::getList(false);
		        }
				return $categories;
    		default: return null;
    	}
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getForm($name) {
        switch ($name) {
            case "category":
                $form = parent::getForm($name);

                // Nazev
                $name = new Zend_Form_Element_Text(array(
                    'name' => 'name',
                    'maxlength' => 100,
                    'label' => $this->texts->name
                ));
                $name->setRequired(true);
                $emptyValidator = new Zend_Validate_NotEmpty();
                $emptyValidator->setMessages(array(
                    Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->name_empty
                ));
                $name->addValidator($emptyValidator);

                $form->addElements(array($name));

                return $form;

            default: return null;
        }
    }


    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getCreateForm($name)
     */
    public function getCreateForm($name) {
        switch ($name) {
            case "category":
                $form = parent::getCreateForm($name);
                $form->setFormInline(true);
                return $form;

            default: return null;
        }
    }

    /**
    * overrides Ibulletin_Admin_BaseControllerAbstract::getUpdateForm($name)
    */
    public function getUpdateForm($name) {
    	switch ($name) {
    		case "category":
    			$form = parent::getUpdateForm($name);

    			// Bulletins select
    			$bulletins[0] = '-----';
    			$bulletins+= Bulletins::getBulletinListForSelect(true,true,null,array('valid_from', 'id'));

    			// Layout select
    			$layouts = Layouts::getListForSel();


	            // Order
	            $order = new Zend_Form_Element_Text(array(
		            'name' => 'order',
		            'label' => $this->texts->order
	        	));
	            $order->setRequired(true);
	            $emptyValidator = new Zend_Validate_NotEmpty();
	            $emptyValidator->setMessages(array(
	            	Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->order_empty
	            ));
	            $order->addValidator($emptyValidator, true);

	            $digitsValidator = new Zend_Validate_Digits();
	            $digitsValidator->setMessages(array(
	            	Zend_Validate_Digits::NOT_DIGITS => $this->texts->validators->order_digit
	            ));
	            $order->addValidator($digitsValidator, true);

	            $greaterValidator = new Zend_Validate_GreaterThan(0);
	            $greaterValidator->setMessages(array(
	            	Zend_Validate_GreaterThan::NOT_GREATER => $this->texts->validators->order_greater
	            ));

	            $order->addValidator($greaterValidator);


	            // URL name
	            $url_name = new Zend_Form_Element_Text(array(
		            'name' => 'url_name',
		            'maxlength' => 100,
		            'label' => $this->texts->url_name
	            ));
	            $url_name->setRequired(true);
	            $emptyValidator = new Zend_Validate_NotEmpty();
	            $emptyValidator->setMessages(array(
	            	Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->url_name_empty
	            ));
	            $url_name->addValidator($emptyValidator);

            	// GotoArticle
	            // pokud ma jen jeden clanek, zda jit do rubriky, ci primo na clanek
	            $gotoArticle = new Zend_Form_Element_Checkbox(array(
					'name' => 'goto_article',
                    'order' => 3,
		            'label' => $this->texts->goto_article
	            ));
	            $gotoArticle->addFilter(new Zend_Filter_Boolean());

	            // Type
	            // Druh kategorie (nic, rubrika, aplikace, skryta)
	            $type = new Zend_Form_Element_Select(array(
	                'name' => 'type',
	                'label' => $this->texts->type,
	                'multioptions' => array(
	                	'' => '-----',
	                	'a' => $this->texts->type_a,
	                	'r' => $this->texts->type_r,
	                	'h' => $this->texts->type_h
	                )
	            ));
	            $type->addFilter(new Zend_Filter_Null());

	            // Anotace
	            $annotation = new Zend_Form_Element_Textarea(array(
		            'name' => 'annotation',
		            'label' => $this->texts->annotation,
                    'order' => 4
	            ));

	            $annotation->setAttrib('class', 'span6');
	            $annotation->setAttrib('rows', '10');

	            // Valid from
	            $valid_from = new Zend_Form_Element_Select(array(
					'name' => 'valid_from',
                    'label' => $this->texts->valid_from,
                    'multioptions' => $bulletins
	            ));
	            $valid_from->addFilter(new Zend_Filter_Null());

	            // Valid to
	            $valid_to = new Zend_Form_Element_Select(array(
					'name' => 'valid_to',
	                'label' => $this->texts->valid_to,
	                'multioptions' => $bulletins
	            ));
	            $valid_to->addFilter(new Zend_Filter_Null());

	            // Layout
	            $layout = new Zend_Form_Element_Select(array(
					'name' => 'layout_name',
	                'label' => $this->texts->layout,
	                'multioptions' => $layouts,
                    'order' => 6
	            ));
	            $layout->addFilter(new Zend_Filter_Null());

	    		$form->addElements(array(
	    		    //$name,
		            $order,
		            $url_name,
		            $gotoArticle,
		            $type,
		            $annotation,
		            $valid_from,
		            $valid_to,
		            $layout
	    		));

               $form->addDisplayGroup(array($name,$url_name),
                        'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>1));

               $form->addDisplayGroup(array($order,$type),
                        'grp2',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>2));

               $form->addDisplayGroup(array($valid_from,$valid_to),
                        'grp3',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>5));

                return $form;

	    	default: return null;
    	}
    }

    /**
     *
     * @param Zend_Db_Select $select
     * @return Ibulletin_Datagrid
     */
    public function grid($select) {
        $grid = new Ibulletin_DataGrid($select);
        $grid->setDefaultSort('order');
        $grid->setDefaultDir('ASC');

        $grid->addColumn('id', array(
                    'header' => $this->texts->id,
                ))
                ->addColumn('name', array(
                    'header' => $this->texts->name,
                    'field' => 'name',
                    'filter' => array(
                        'autocomplete' => true,
                        'type' => 'expr',
                        'datatype' => 'string'
                    )
                ))
                ->addColumn('order', array(
                    'header' => $this->texts->order,
                    'type' => 'number',
                    'align' => 'center'
                ))
                ->addColumn('url_name', array(
                    'header' => $this->texts->url_name,
                    'field' => 'url_name',
                    'filter' => array(
                        'autocomplete' => true,
                        'type' => 'expr',
                        'datatype' => 'string'
                    )
                ));

        return $grid;
    }

	/**
     * zobrazi kategorie
     */
    public function indexAction() {

        $this->setAfterCreateUrl('category','index');
        $this->view->form = $this->processCreate('category');

        try {
            $grid = $this->grid(Categories::getCategoriesQuery());
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
     * zobrazi smazané kategorie
     */
    public function deletedAction() {

        try {
            $grid = $this->grid(Categories::getCategoriesQuery(true));
            $grid->addAction('renew', array(
                        'confirm' => $this->texts->confirm_renew,
                        'url' => $this->_helper->url('renew') . '/id/$id/',
                        'caption' => $this->texts->action_renew,
                        'image' => 'refresh'
            ));
            $this->view->grid = $grid->process();
        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

    /**
     * prida kategorii
     */
/*    public function addAction() {

    	$this->setAfterCreateUrl('category','index');

    	$this->view->form = $this->processCreate('category');
    }
*/
    /**
     * edituje kategorii
     */
    public function editAction() {

	    $id = $this->_request->getParam('id');
	    if (!$id) {
	    	$this->redirect('index');
	    }

        //Dropzone
        Ibulletin_Js::addJsFile('dropzone.min.js');
        Ibulletin_HtmlHead::addFile('dropzone.css');

        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

	    $this->setAfterUpdateUrl('category','index');

	    // Formular pro upload souboruu
	    $urlHlpr = new Zend_View_Helper_Url();
	    $fileUploader = new Ibulletin_FileUploader(null, $this->config->categories_admin->img_basepath, $this->getRequest(), true);
	    $fileUploader->setNumFiles(1);
	    $fileUploader->fileNames[] = $id;
	    $fileUploader->doFormActions();

	    // obrazek kategorie
	    $extensions = array('jpg', 'png', 'gif');
	    $cat_img = null;
	    foreach ($extensions as $ext) {
	    	$file = $this->config->categories_admin->img_basepath.'/'.$id.'.'.$ext;
	    	if (file_exists($file)) {
	    		$cat_img = $file;
	    		break;
	    	}
	    }

        // Pokud neni vybrany bulletin (valid_from valid_to) v selectu, pridame ho,
        // je smazany a nechceme, aby se data sama menila
        $form = $this->getUpdateForm('category');
        $record = $this->getRecord('category', $id);
        $multioptions = $form->getElement('valid_from')->getMultioptions();
        if(!isset($multioptions[$record['valid_from']])){
            $form->getElement('valid_from')->addMultiOption(
                $record['valid_from'], $record['valid_from'].' - '.$this->texts->edit->deleted
            );
            $form->getElement('valid_from')->setValue($record['valid_from']);
        }
        if(!isset($multioptions[$record['valid_to']])){
            $form->getElement('valid_to')->addMultiOption(
                $record['valid_to'], $record['valid_to'].' - '.$this->texts->edit->deleted
            );
            $form->getElement('valid_to')->setValue($record['valid_to']);
        }
        $this->setUpdateForm('category', $form);

        $form = $this->processUpdate('category', $id);

        $this->view->category_img = $cat_img;
        $this->view->category_id = $id;
        $this->view->fileUploader = $fileUploader;
        $this->view->form = $form;

    }

    /**
     * smaze obrazek
     */
    public function deleteimgAction() {

    	$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}

    	// Pokusime se smazat existujici soubor s obrazkem pro tuto kategorii
    	$d = dir($this->config->categories_admin->img_basepath);
    	while($entry = $d->read()) {
    		if(preg_match('/^'.preg_quote($id).'/i', $entry)){
    			unlink($this->config->categories_admin->img_basepath .'/'. $entry);
    		}
    	}

    	// presmeruje na editovani (a smaze neulozene upravy)
    	$this->redirect('edit', 'categoryadm', 'admin',array('id' => $id));
    }

    /**
     * smaze kategorii
     */
    public function deleteAction() {

		$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}

    	$this->setAfterDeleteUrl('category','index');
	    $this->processDelete('category', $id);

    }

    /**
     * obnovi kategorii
     */
    public function renewAction() {

    	$id = $this->_request->getParam('id');
    	if (!$id) { $this->redirect('index'); }

    	$texts = Ibulletin_Texts::getSet();
    	$done = Categories::renew($id);

		if($done){
			$this->infoMessage($texts->renewed);
		} else {
			$this->infoMessage($texts->error, array($id));
		}
		$this->redirect('index');

    }



}

?>