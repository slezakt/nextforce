<?php
/**
 * Modul pro sestaveni bulletinu z ruznych pages.
 *
 * @author Martin Krčmář
 */
class Admin_BulletinassemblerController extends Ibulletin_Admin_BaseControllerAbstract
{
    private $id;

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
	public function init() {
        parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => true)
        );
    }

    public function getRecord($name, $id) {
		switch ($name) {
			case 'bulletin':
				try {
                    $bul = Bulletins::get((int)$id);

                    if ($bul['valid_from']) {
                       $valid_from = new Zend_Date($bul['valid_from'], Zend_Date::ISO_8601);
                       $bul['valid_from'] = $valid_from->toString($this->config->general->dateformat->medium);
                    }

                    $bul['created'] = $bul['created'] ? date('Y-m-d H:i:s', strtotime($bul['created'])) : NULL;
                    $bul['visible'] = !$bul['hidden'];
                    unset($bul['hidden']);
                    return $bul;
				} catch (Exception $e) {
					$this->infoMessage($e->getMessage(),'error');
					Phc_ErrorLog::error('BulletinassemblerController', $e);
					return false;
				}

			default: return null;
		}
	}

    /**
	 * @see Ibulletin_Admin_CRUDControllerAbstract::createRecord()
	 */
	public function createRecord($name, $values) {
		switch ($name) {
			case "bulletin":
				try {
					$bu = new Bulletins();
                    $last_insert_id = $bu->newBulletin(
                        $values['name'],
                        Utils::slugify($values['name'], '`'),
                        date('Y-m-d',strtotime('+1 year')), //validFrom
                        date('Y-m-d H:m:s',time()), //created
                        $this->config->general->layout->default //layout
                    );
					return (boolean)$last_insert_id;
				} catch (Ibulletin_MailsException $e) {
                 	Phc_ErrorLog::error('BulletinassemblerController:', $e);
                	return false;
				}

			default: return null;
		}
	}

    public function updateRecord($name, $values) {

        switch ($name) {
            case "bulletin" :
                try {
                    $values['hidden'] = !$values['visible'];
                    unset($values['visible']);

                    $valid_from = new Zend_Date($values['valid_from'], $this->config->general->dateformat->medium);
                    $values['valid_from'] = $valid_from->get(Zend_Date::ISO_8601);

                    $bu = new Bulletins();
                    $bu->saveBulletin($values);

                } catch (Exception $e) {

                    $this->infoMessage($e->getMessage(), 'error');
                    Phc_ErrorLog::error('BulletinassamblerController', $e);
                    return false;
                }

                return true;

            default: return null;
        }
    }

     public function deleteRecord($name, $id) {
    	switch ($name) {
    		case "bulletin":
                $bu = new Bulletins();
    			return $bu->delete($id);
    		default: return null;
    	}
    }

    /**
     * Datagrid pro vyber vydani.
     *
     * @param Zend_Db_Select pro grid $select
     * @return Ibulletin_Datagrid
     */
    public function grid($select) {

        $grid = new Ibulletin_DataGrid($select);
        $grid->setEmptyText($this->texts->index->empty);
        $grid->setDefaultSort('valid_from');

        $imgs_yes_no = array(0 => 'remove-color', 1 => 'ok-color');


        $opts_bool = array(
            0 => $this->texts->option_no,
            1 => $this->texts->option_yes,
        );

        $grid->addColumn('id', array(
                    'header' => $this->texts->bulletin_id
                ))
                ->addColumn('name', array(
                    'header' => $this->texts->bulletin_name,
                    'field' => 'name',
                    'filter' => array(
                        'type' => 'expr',
                        'datatype' => 'string',
                        'autocomplete' => true
                    )
                ))
                ->addColumn('valid_from', array(
                    'header' => $this->texts->bulletin_valid_from,
                    'type' => 'datetime',
                    'field' => 'valid_from',
                    'filter' => array(
                        'type' => 'expr',
                        'datatype' => 'datetime',
                    )
                ))
                ->addColumn('created', array(
                    'header' => $this->texts->bulletin_created,
                    'type' => 'datetime',
                    'field' => 'created',
                    'filter' => array(
                        'type' => 'expr',
                        'datatype' => 'datetime',
                    )
                ))
                ->addColumn('layout_name', array(
                    'header' => $this->texts->bulletin_layout,
                    'field' => 'created'
                ))
                ->addColumn('skin', array(
                    'header' => $this->texts->bulletin_skin,
                    'field' => 'created'
                ))
                ->addColumn('visible', array(
                    'header' => $this->texts->bulletin_visible,
                    'align' => 'center',
                    'imgs' => $imgs_yes_no,
                    'field' => 'visible',
                    'url' => $this->getHelper('url')->url(array('action' => 'alterhide')) . '/id/$id/currently/$visible',
            /*        'filter' => array(
                        'type' => 'expr',
                        'datatype' => 'bool',
                        'options' => $opts_bool,
                    ),*/
                ))
                ;

        return $grid;
    }

    /**
     * Vraci formular pro vytvoreni noveho bulletinu
     * @param Zend_Form_Element_Text $name
     * @return null
     */
	public function getForm($name) {

		$form = parent::getForm($name);

		switch ($name) {
			case "bulletin":
            $form->setFormInline(true);
            $name = new Zend_Form_Element_Text(array(
					'name' => 'name',
					'label' => $this->texts->bulletin_name,
					'filters' => array('StringTrim'),
					'required' =>true,
					'size' => 40,
				));

				$name->addValidator('NotEmpty', true, array(
					'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->bulletin_name_empty)
				));

				$form->addElements(array($name));
				return $form;

			default: return null;
		}
	}

    /**
     * Spusti se po zavolani bez specifikace akce - hlavni stranka
     */
    public function indexAction() {

        // spracujeme formular
        $this->setAfterCreateUrl('bulletin', 'index');


        $this->setLastVisited();

        try {
            $form = $this->processCreate('bulletin');
            $this->view->create_form = $form;
        } catch (Zend_Exception $ex) {
            //osetreni duplicitnich nazvu
            if ($ex->getCode() == 1) {
                $this->infoMessage($this->texts->duplicate_issue, 'error');
                $this->redirect('index');
            } else {
                Phc_ErrorLog::warning('warning', $ex->getMessage());
            }
        }

        try {
            $grid = $this->grid(Bulletins::getBulletinsQuery());
            $grid->addAction('preview', array(
                        'url' => $this->view->url(array('module' => null, 'controller' => 'bulletin'), null, true) . '$url_name',
                        'caption' => $this->texts->action_preview,
                        'image' => 'search'
                    ))
                    ->addAction('delete', array(
                        'notempty' => 'deleted',
                        'confirm' => $this->texts->confirm_delete,
                        'url' => $this->_helper->url('delete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                    ->addAction('duplicate', array(
                        'confirm' => $this->texts->confirm_duplicate,
                        'url' => $this->_helper->url('duplicate') . '/id/$id/',
                        'caption' => $this->texts->action_duplicate,
                        'image' => 'clone'
                    ))
                    ->addAction('edit', array(
                        'notempty' => 'deleted',
                        'url' => $this->_helper->url('edit') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
            ));
            $grid->process();
            $this->view->grid = $grid;
        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

     /**
     * Deleted issue
     */
    public function deletedAction() {

         try {
            $grid = $this->grid(Bulletins::getBulletinsQuery(true));
            $grid->addAction('renew', array(
                        'empty' => 'deleted',
                        'confirm' => $this->texts->confirm_restore,
                        'url' => $this->_helper->url('restore') . '/id/$id/',
                        'caption' => $this->texts->action_restore,
                        'image' => 'refresh'
             ));
            $grid->process();
            $this->view->grid = $grid;
        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

    /*
    * Vlozi akci do vydani
    */
    public function addpageAction() {

        $bul_id = $this->_getParam('id');
        $page_id = $this->_getParam('page_id');

        if (!$bul_id || !$page_id) {
            $this->redirect('index');
        }

        $texts = Ibulletin_Texts::getSet();
        $bu = new Bulletins();

        $page = $bu->getPageData($page_id);

        $url_name = Utils::slugify($page['name'], '`');

        //kdyz chybi url name - doplnit se page_ + cas pridani
        if(empty($url_name)) $url_name = "page_".  mktime();

        try {
            $bu->addPageToBulletin($bul_id, $page_id, $url_name);
        } catch (Exception $e) {
           if ($e->getCode() == 1) {
                $this->infoMessage($this->texts->duplicate_page,'error',$page_id);
            } else {
                $this->infoMessage($e->getMessage(), 'warning');
                Phc_ErrorLog::warning('BulletinAssembler', $e->getMessage());
            }
        }

        $this->redirect('edit',null,null,array('id' => $bul_id));
    }

    /**
     * Editace vydani
     */
    public function editAction()
    {
        $id = $this->_request->getParam('id');

        if (!$id) {
            $this->redirect('index');
        }

        $this->id = $id;

        Ibulletin_Js::addJsFile('admin/bulletins.js');

        $texts = Ibulletin_Texts::getSet();
        $bu = new Bulletins();

        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        //Ulozi upraveny bulletin
        $this->setAfterUpdateUrl('bulletin',null,$this->getLastVisited());
        $bulletinForm = $this->processUpdate('bulletin', $id);

        //Odstrani stranku z bulletinu
        if ($this->getRequest()->isPost()) {
            foreach ($this->getRequest()->getPost() as $p) {
                if (is_array($p)) {
                    if (isset($p['delete_page'])) {
                        $bu->deletePage($id, $p['id']);
                    }
                }
            }
        }

        $pages = $bu->getBulletinPages($id);
        $record = $this->getRecord('bulletin', $id);

        //nahled, jen tehdy pakliže url_name
        if ($record['url_name']) {
            $this->view->preview_links = $this->view->url(array('module' => null, 'controller' => 'bulletin'), null, true).$record['url_name'];
            $bulletinpageForm = $this->getBulletinPagesForm($pages);
        }

        //Ulozi razeni a nastaveni
        if ($this->getRequest()->getPost('save_order') && $this->getRequest()->isPost()) {
            $p_values = $this->getRequest()->getPost();
            foreach ($p_values as $k => $v) {
                if (isset($v['url_name'])) {
                   $p_values[$k]['url_name'] =  Utils::slugify($v['url_name']);
                }
            }

            if ($bulletinpageForm->isValid($p_values)) {
            $newOrder = Array();
            $order = 1;
            foreach ($this->getRequest()->getPost() as $p) {
                if (is_array($p)) {
                    $newOrder[$p['id']] = array(
                        'order' => $order,
                        'url' =>  Utils::slugify($p['url_name']),
                        'cycle' => !empty($p['cycle']) ? 't' : 'f'
                    );
                }
                $order++;
            }
            $bu->newPagesOrderAndUrl($id, $newOrder);
            //nacte formular s novym razenim
            $pages = $bu->getBulletinPages($id);
            $bulletinpageForm = $this->getBulletinPagesForm($pages);
            }
        }

        $this->view->bulletinPageForm = $bulletinpageForm;
        $this->view->bulletinForm = $bulletinForm;

        try {
            $sel = $bu->getAllPages($id);
            
            $sel->where('p.deleted IS NULL');
            
            $grid = new Ibulletin_DataGrid($sel);
            $grid->getDefaultSort('id');
            $grid->setEmptyText($texts->page_empty);

            $grid->addColumn('id', array(
                        'header' => $texts->page_id,
                    ))
                    ->addColumn('name', array(
                        'header' => $texts->page_name,
                        'field' => 'p.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    ))
                    ->addColumn('tpl_file', array(
                        'header' => $texts->page_tpl_file,
                        'field' => 'p.tpl_file'
                    ))
                    ->addColumn('last_change', array(
                        'header' => $texts->page_last_change,
                        'type' => 'datetime',
                        'field' => 'last_change'
                    ))
                    ->addColumn('category_name', array(
                        'header' => $texts->page_category,
                        'field' => 'category_name'
                    ))
                    ->addAction('add_page', array(
                        'url' => $this->view->url(array('action' => 'addpage')) . 'page_id/$id',
                        'class' => 'add-page',
                        'caption' => $texts->page_action_add,
                        'image' => 'plus'
            ));

            $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

     /**
     * Smaze bulletin
     */
    public function restoreAction() {

		$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}
        $texts = Ibulletin_Texts::getSet();

        $bu = new Bulletins();
	    if ($bu->restore($id)) {
             $this->infoMessage($texts->restored, 'info');
        } else {
            $this->infoMessage($texts->notrestored, 'error');
        }

        $this->redirect('index');

    }

    /**
     * Smaze bulletin
     */
    public function deleteAction() {

		$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}

    	$this->setAfterDeleteUrl('bulletin','index');
	    $this->processDelete('bulletin', $id);
    }
    
    
     /**
     * Duplikuje bulletin
     */
    public function duplicateAction() {

        $id = $this->_request->getParam('id');
        if (!$id) {
            $this->redirect('index');
        }

        $bu = new Bulletins();
        $source_bulletin = $bu->get(intval($id));

        try {
            
            //url name je unikatni, proto najdeme volne
            $new_url_name = $source_bulletin['url_name'].'-copy';
            $i = 1;
            while($bu->get($new_url_name,false,true)) {
               $new_url_name = $source_bulletin['url_name'].'-copy-'.$i++;
            }

            //vytvorime novy bulletin s hodnotami zdrojoveho vydani
            $new_bulletin_id = $bu->newBulletin($source_bulletin['name'] . '_copy', $new_url_name, $source_bulletin['valid_from'],
                    $source_bulletin['created'], $source_bulletin['layout_name'], $source_bulletin['skin']);
            
            //nastavime viditelnost dle zdroje
            $bu->setHidden($new_bulletin_id, $source_bulletin['hidden']);

            //ziskame stranky zdrojoveho vydani
            $source_bulletin_pages = $bu->getBulletinPages($id);

            //postupne ulozime stranky do noveho bulletinu
            foreach ($source_bulletin_pages as $page) {
                $bu->addPageToBulletin($new_bulletin_id, $page['id'], $page['url_name']);
            }
            
            $this->infoMessage($this->texts->duplicate->ok,'success',array($source_bulletin['name']));
            
        } catch (Exception $e) {
            $this->infoMessage($this->texts->duplicate->error.' '.$e->getMessage(),'error');
        }

        $this->redirectUri($this->getLastVisited());
    }

    /**
     * Smaze bulletin
     */
    public function alterhideAction() {

        $id = $this->_request->getParam('id');
        $currently = $this->_request->getParam('currently');
        if(!$id){
            $this->redirect(array('index'));
        }

        // Podle aktualniho stavu visible nastavime akci, ktera se ma provest
        $hide = $currently == '1' ? true : false;

        $bulletins = new Bulletins();
        $bulletins->setHidden($id, $hide);

        // Vezmeme puvodni parametry requestu a odstranime nase
        $params = $this->_getAllParams();
        unset($params['id']);
        unset($params['currently']);

        // Redirect s dodrzenim kontextu
        $this->redirect('index', null, null, $params);
    }

	/**
	 *	Vytvoří formulář pro editaci vlastností bulletinu.
	 */
	function getUpdateForm($name)
	{
         switch ($name) {
            case 'bulletin':
                $form = parent::getUpdateForm($name);

                $form->setFormInline(false);

                $id = new Zend_Form_Element_Hidden(array(
                    'name' => 'id'
                ));
                $id->setValue(0);

                // input pro URL name
                $url = new Zend_Form_Element_Text(array(
                    'name' => 'url_name',
                    'maxlength' => 100,
                    'label' => $this->texts->bulletin_url_name
                ));

                $url->setRequired(true)
                        ->addValidator('NotEmpty', null, array('messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->url_name_empty)))
                        ->addValidator('Db_NoRecordExists',true,array('adapter'=>Zend_Registry::get('db'),'table'=>'bulletins','field' => 'url_name', 'exclude' => array(
                        'field' => 'id',
                        'value' => $this->id
                        ), 'messages' => array(Zend_Validate_Db_NoRecordExists::ERROR_RECORD_FOUND =>  $this->texts->validators->url_exists)))
                        ->addValidator('Regex',false,array('pattern'=>'/^\w*[^\.]+\w*$/','messages' => array(Zend_Validate_Regex::NOT_MATCH => $this->texts->validators->url_name_dot)));


                $validFrom = new Zend_Form_Element_Text(array(
                    'name' => 'valid_from',
                    'class' => 'datetimepicker',
                    'autocomplete' => 'off',
                    'label' => sprintf($this->texts->edit->valid_from,$this->config->general->dateformat->medium)
                ));
                $validFrom->setRequired(true);
                $emptyValidator = new Zend_Validate_NotEmpty();
                $emptyValidator->setMessage($this->texts->validators->valid_from_empty, Zend_Validate_NotEmpty::IS_EMPTY);
                $dateValidator = new Zend_Validate_Date($this->config->general->dateformat->medium);
                $dateValidator->setMessage(sprintf($this->texts->validators->valid_from_invalid, $this->config->general->dateformat->medium), Zend_Validate_Date::FALSEFORMAT);
                $validFrom->addValidators(array($dateValidator, $emptyValidator));

                // input pro created
                // TODO validatory na datum, timestamp
               /* $created = new Zend_Form_Element_Text(array(
                    'name' => 'created',
                    'label' => $this->texts->bulletin_created
                ));
                $emptyValidator->setMessage($this->texts->validators->created_empty, Zend_Validate_NotEmpty::IS_EMPTY);
                $created->setRequired(true);
                $created->addValidators(array($emptyValidator,$dateValidator));
                $created->setValue(strftime('%Y-%m-%d %H:%M:%S'));
                 */
                $visible = new Zend_Form_Element_Checkbox(array(
                    'name' =>'visible',
                    'label' => $this->texts->bulletin_visible,
                    'filters' => array(array('Boolean'))
                ));

                // Layout select
                $layouts = Layouts::getListForSel();
                // Remove the same as issue (empty) choice
                unset($layouts['']);
                $layout = new Zend_Form_Element_Select(array(
                    'name' => 'layout_name',
                    'label' => $this->texts->bulletin_layout,
                    'multioptions' => $layouts,
                    'filters' => array(
                        array('Null')
                    )
                ));

                $skins = array_merge(array('' => '-----'), Skins::findPairs());
                $skin = new Zend_Form_Element_Select(array(
                    'name' => 'skin',
                    'label' => $this->texts->bulletin_skin,
                    'multioptions' => $skins,
                    'filters' => array(
                        array('Null')
                    )
                ));


                $form->addElements(array($id,$url, $validFrom, $visible, $layout, $skin));


                $form->addDisplayGroup(array($form->getElement('name'),$url, $validFrom, $visible),
                        'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>1));

                $form->addDisplayGroup(array($layout,$skin),
                        'grp2',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>2));

                return $form;

            default: return null;
        }
    }

    /**
	 *	Přidá k formuláři elementy reprezentující stránky bulletinu. U každého
	 *	je možnost odstranit stránku z bulletinu nebo změnit pořadí. Nebo
	 *	změnit její URL name.
	 *
	 *	@param Pole stránek, plus někajé jejich parametry.
	 */
	function getBulletinPagesForm($pages)
	{
        $form = new Form();

        if ($pages) {
            //Hlavicka tabulky formulare
            $thead = new Zend_Form_Element_Hidden('thead');
            $form->addElement($thead);
            $thead->setDecorators(array(
                array('ViewScript', array('viewScript' => 'bulletinassembler/bp_thead.phtml'))
            ));
        }

        foreach ($pages as $page)
		{
            $pageForm = new Zend_Form_SubForm();

            if (!$page['name']) $page['name'] = "No name";
	        $pageForm->setDescription($page['name']);

			$id = new Zend_Form_Element_Text("id");
			$id->setValue($page['id'])
               ->setAttrib('size',5)
               ->setAttrib('readonly','readonly')
               ->setAttrib('class','span1')
               ->setAttrib('style','width:30px;');

			// nazev url stranky
		   $urlName = new Zend_Form_Element_Text('url_name');

           $urlName->addValidator('NotEmpty',null,array('messages' =>
                array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->page_url_name_empty)))
                    ->addValidator('Db_NoRecordExists',true,array('adapter'=>Zend_Registry::get('db'),'table'=>'bulletins_pages','field' => 'url_name',
                       'exclude' =>"page_id != '".$page["page_id"]."' AND bulletin_id = '".$page['bulletin_id']."'"
                        , 'messages' => array(Zend_Validate_Db_NoRecordExists::ERROR_RECORD_FOUND =>  $this->texts->validators->url_exists)))
                    ->setAttrib('size', 50)
                    ->setRequired(true)
                    ->setAttrib('class','span4')
                    ->setValue($page['url_name']);

			$cycle = new Zend_Form_Element_Checkbox('cycle');
            $cycle->setValue($page['cycle']);

			$delete = new Zend_Form_Element_Submit('delete_page');
			$delete->setLabel($this->texts->page_delete)
                    ->setAttrib('class', 'btn');

			$pageForm->addElements(array($id,$urlName, $cycle, $delete));

            $pageForm->setElementDecorators(array(
                'ViewHelper',
                array('HtmlTag', array('tag' => 'td')),
            ));

			$form->addSubForm($pageForm, 'sub_'.$page["id"]);
		}

		// na konci tlacitko ulozit razeni, ale jenom jestli pages neni prazdne
		if ($pages)
		{
			$saveOrder = new Zend_Form_Element_Submit('save_order');
			$saveOrder->setLabel($this->texts->page_save_order)
               ->setDescription($this->texts->edit->rank_note)
               ->setAttrib('class','btn btn-primary');
			$form->addElement($saveOrder);
            $saveOrder->setDecorators(array(
                'ViewHelper',
                array('Description',array('placement' => 'prepend')),
                array(array('data'=>'HtmlTag'),array('tag'=>'td','colspan' => '5')),
                ));
		}

         $form->setSubFormDecorators(array(
          'FormElements',
          array('Description',array('tag'=>'td','class'=>'desc','placement'=>'prepend')),
          new Zend_Form_Decorator_FormErrors(array(
                'ignoreSubForms' => false,
               //nezobrazovat nadpis chyby, nic lepsiho me nenapadlo
                'markupElementLabelStart' => '<div style="display:none">',
                'markupElementLabelEnd' => '</div>',
                'markupListStart' => '<td>',
                'markupListEnd' => '</td>',
                'markupListItemStart' => '',
                'markupListItemEnd' => ''
            )),
            array(array('row'=>'HtmlTag'),array('tag'=>'tr','class'=>'item')),
        ));

        $form->setDecorators(array(
            'FormElements',
             array('HtmlTag',array('tag'=>'table','id'=>'bp_sortable')),
            'Form'

        ));

		return $form;
	}


}
