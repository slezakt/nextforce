<?php
/**
 * Sprava zvacích vln.
 * @author andrej.litvaj@pearshealthcyber.com
 */
class Admin_InvitationwavesController extends Ibulletin_Admin_BaseControllerAbstract
{
	protected $wave_types;

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
	public function init() {
        parent::init();
        $this->submenuAll = array(
        	'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'add' => array('title' => $this->texts->submenu_add, 'params' => array('action' => 'add'), 'noreset' => false),
        );
        
        $this->wave_types = Invitationwaves::getWaveTypes();
        
    }

    public function updateRecord($name, $values) {
        switch ($name) {
        case "iw" :
            $iw = new Ibulletin_InvitationWaves;
            if ($values['start']) {
                $start = new Zend_Date($values['start'], $this->config->general->dateformat->medium);
                $values['start'] = $start->get(Zend_Date::ISO_8601);
            }
            if ($values['end']) {
                $end = new Zend_Date($values['end'], $this->config->general->dateformat->medium);
                $values['end'] = $end->get(Zend_Date::ISO_8601);
            }
            return (boolean)$iw->saveWave(
            	$values['id'],
            	$values['name'],
            	$values['type'],
                $values['start'] ,
                $values['end'],
            	$values['bulletin_id'],
            	$values['url_prefix'],
            	$values['url_name'],
            	$values['force_referer'],
            	$values['invited'],
                $values['link_id']);
        default: return null;
        }
    }

    public function createRecord($name, $values) {
        switch ($name) {
        case "iw":
            $iw = new Ibulletin_InvitationWaves;
            
            if ($values['start']) {
                $start = new Zend_Date($values['start'], $this->config->general->dateformat->medium);
                $values['start'] = $start->get(Zend_Date::ISO_8601);
            }
            if ($values['end']) {
                $end = new Zend_Date($values['end'], $this->config->general->dateformat->medium);
                $values['end'] = $end->get(Zend_Date::ISO_8601);
            }
            
            return (boolean)$iw->newWave(
            	$values['name'],
            	$values['type'],
            	$values['start'],
            	$values['end'],
            	$values['bulletin_id'],
            	$values['url_prefix'],
            	$values['url_name'],
            	$values['force_referer'],
            	$values['invited'],
                $values['link_id']);
        default: return null;
        }
    }

    public function deleteRecord($name, $id) {
    	switch ($name) {
    		case "iw":
    			$iw = new Ibulletin_InvitationWaves;
    			return $iw->deleteWave($id);
    		default: return null;
    	}
    }

    public function getRecord($name, $id) {
        switch ($name) {
        case "iw":
            $iw = new Ibulletin_InvitationWaves;
            $values = (array)$iw->getWaveData($id);
            if ($values['start']) {
                $start = new Zend_Date($values['start'], Zend_Date::ISO_8601);
                $values['start'] = $start->toString($this->config->general->dateformat->medium);
            }

            if ($values['end']) {
                $end = new Zend_Date($values['end'], Zend_Date::ISO_8601);
                $values['end'] = $end->toString($this->config->general->dateformat->medium);
            }
            return $values;
        default: return null;
        }
    }

    public function getRecords($name, $options = array()){
        switch ($name) {
        case "iw":
            $iw = new Ibulletin_InvitationWaves;
            $data = array();
            foreach ($iw->getInvitationWaves() as $row) {
            	$data[] = (array)$row;
            }
            return $data;
        default: return null;
        }
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getForm($name) {
        switch ($name) {
        case "iw":
            $form = parent::getForm($name);

			// nazev zvaci vlny
            $name = new Zend_Form_Element_Text(array(
	            'name' => 'name',
	            'maxlength' => 50,
	            'label' => $this->texts->name
            ));
            $name->setRequired(true);
            $emptyValidator = new Zend_Validate_NotEmpty();
            $emptyValidator->setMessages(array(
            	Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty
            ));
            $name->addValidator($emptyValidator);

            // typ zvaci vlny
            $type = new Zend_Form_Element_Select(array(
            	'name' => 'type',
            	'label' => $this->texts->type,
            	'multioptions' => $this->wave_types
            ));
            $type->addFilter(new Zend_Filter_Int());

            // start
            $start = new Zend_Form_Element_Text(array(
                'class' => 'datetimepicker',
                'autocomplete' => 'off',
	            'name' => 'start',
	            'maxlength' => 25,
	            'label' => sprintf($this->texts->start, $this->config->general->dateformat->medium)
            ));
            $dateValidator = new Zend_Validate_Date($this->config->general->dateformat->medium);
            $dateValidator->setMessages(array(
            	Zend_Validate_Date::FALSEFORMAT => $this->texts->validators->date
            ));
            $start->addValidator($dateValidator);
            $start->addFilter(new Zend_Filter_Null());

            // end
            $end = new Zend_Form_Element_Text(array(
                'class' => 'datetimepicker',
                'autocomplete' => 'off',
	            'name' => 'end',
	            'maxlength' => 25,
	            'label' => sprintf($this->texts->end, $this->config->general->dateformat->medium)
            ));
            $dateValidator = new Zend_Validate_Date($this->config->general->dateformat->medium);
            $dateValidator->setMessages(array(
            	Zend_Validate_Date::FALSEFORMAT => $this->texts->validators->date
            ));
            $end->addValidator($dateValidator);
            $greaterValidator = new Ibulletin_Validators_DateGreaterThan(array('start'));
            $greaterValidator->setMessages(array(
            	Ibulletin_Validators_DateGreaterThan::LESS => $this->texts->validators->date_less
            ));
            $end->addValidator($greaterValidator);
            $end->addFilter(new Zend_Filter_Null());

            // url_prefix
            $url_prefix = new Zend_Form_Element_Text(array(
	            'name' => 'url_prefix',
	            'label' => $this->texts->url_prefix
            ));
            $url_prefix->addFilter(new Zend_Filter_Null());

            // url_name
            $url_name = new Zend_Form_Element_Text(array(
	            'name' => 'url_name',
	            'maxlength' => 40,
                'order'=>3,
	            'label' => $this->texts->url_name
            ));
            $url_name->addFilter(new Zend_Filter_Null());

            // force_referer
            $force_referer = new Zend_Form_Element_Checkbox(array(
	            'name' => 'force_referer',
	            'label' => $this->texts->force_referer
            ));
            $force_referer->addFilter(new Zend_Filter_Boolean());

            // link_id (linkpicker)
            $links = new Links();
            $link_id = new Zend_Form_Element_Select('link_id', array(
                'label' => $this->texts->link_id,
                'filters' => array('Null'),
                'disable-picker' => true,
                'multioptions' => array(0 => $this->texts->empty) + $links->getLinks(),
                'class' => 'form-linkpicker'
            ));
            // invited
            $invited = new Zend_Form_Element_Text(array(
	            'name' => 'invited',
	            'maxlength' => 40,
	            'label' => $this->texts->invited,
                'order' => 5
            ));
            $invited->addFilter(new Zend_Filter_Int());

            // bulletin
            $bu = new Bulletins();
            $bulletin_list = $bu->getBulletinListForSelect(true, false, null, null, true);

            $bulletin_id = new Zend_Form_Element_Select(array(
            	'name' =>'bulletin_id',
            	'label' => $this->texts->bulletin,
            	'multioptions' => $bulletin_list
            ));

            $bulletin_id->addFilter(new Zend_Filter_Int());

            $form->addElements(array(
	            $name,
	            $type,
	            $start,
	            $end,
                $link_id,
	            $bulletin_id,
	            $url_prefix,
	            $url_name,
	            $force_referer,
	            $invited
            ));

           $form->addDisplayGroup(array($name,$type,$bulletin_id),
             'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>1));

            $form->addDisplayGroup(array($start,$end, $link_id),
             'grp2',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>2));

             $form->addDisplayGroup(array($url_prefix,$force_referer),
             'grp3',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>4));

            return $form;
        default: return null;
        }
    }

    /**
    * zobrazi vlny
    */
    public function indexAction() {

         try {
            $grid = new Ibulletin_DataGrid(Ibulletin_InvitationWaves::getInvitationWavesQuery());
            $grid->setEmptyText($this->texts->index->empty);
            $grid->setDefaultSort('id');

            $imgs_yes_no = array(0 => 'remove-color', 1 => 'ok-color');

            $grid->addColumn('id', array(
                        'header' => $this->texts->id,
                    ))
                    ->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'iw.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    ))
                    ->addColumn('type', array(
                        'header' => $this->texts->type,
                        'type' => 'options',
                        'options' => $this->wave_types
                    ))
                    ->addColumn('start', array(
                        'header' => $this->texts->index->start,
                        'field' => 'iw.start',
                        'type' => 'datetime',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'datetime'
                        )
                    ))
                    ->addColumn('end', array(
                        'header' => $this->texts->index->end,
                        'field' => 'iw.end',
                        'type' => 'datetime',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'datetime'
                       )
                    ))
                     ->addColumn('bulletin', array(
                        'header' => $this->texts->bulletin,
                        'field' => 'bulletins.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    ))
                    ->addColumn('url_prefix', array(
                        'header' => $this->texts->index->url_prefix,
                    ))
                    ->addColumn('url_name', array(
                        'header' => $this->texts->index->url_name,
                    ))
                    ->addColumn('force_referer', array(
                        'header' => $this->texts->index->force_referer,
                        'align' => 'center',
                        'imgs' => $imgs_yes_no
                    ))
                    ->addColumn('invited', array(
                        'header' => $this->texts->invited,
                    ))
                    ->addAction("delete", array(
                        'notempty' => 'c_mail',
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

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

    }

    /**
    * prida vlnu
    */
    public function addAction() {

    	$this->setAfterCreateUrl('iw','index');
        
        $links = new Links();
        $this->view->links = $links->getSortedLinks();

    	$this->view->form = $this->processCreate('iw');
    }

    /**
    * edituje vlnu
    */
    public function editAction() {

        $id = $this->_request->getParam('id');
        if (!$id) {
        	$this->redirect('index');
        }

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        $this->setAfterUpdateUrl('iw','index');
        $this->setBeforeUpdate(array($this, 'beforeUpdate'));

        // Pokud neni vybrany bulletin v selectu, pridame ho
        $form = $this->getUpdateForm('iw');
        $record = $this->getRecord('iw', $id);
        $multioptions = $form->getElement('bulletin_id')->getMultioptions();
        if(!isset($multioptions[$record['bulletin_id']])){
            $form->getElement('bulletin_id')->addMultiOption(
                $record['bulletin_id'], $record['bulletin_id'].' - '.$this->texts->edit->deleted
            );
            $form->getElement('bulletin_id')->setValue($record['bulletin_id']);
        }
        $this->setUpdateForm('iw', $form);

        $form = $this->processUpdate('iw', $id);

        $links = new Links();
        $this->view->form = $form;
        $this->view->links = $links->getSortedLinks();

    }

    public function beforeUpdate($name, $form, $record) {
        switch ($name) {
            case "iw":
                // conditionally turn of validation for SELECT if we are not changing original value
                if ($record['bulletin_id'] == $this->_request->getParam('bulletin_id'))
                    $form->getElement('bulletin_id')->setRegisterInArrayValidator(false);
                break;
            default: return null;
        }
    }

    /**
    * smaze vlnu
    */
    public function deleteAction() {

    	$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}

    	$this->setAfterDeleteUrl('iw','index');
    	$this->processDelete('iw', $id);
    }



}
?>