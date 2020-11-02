<?php

/**
 * Controller pro spravu menu bulletinu
 *
 * @author Ondra Bláha <ondrej.blaha@pearshealthcyber.com>
 */
class Admin_MenuController extends Ibulletin_Admin_BaseControllerAbstract {
    
    
    public function init() {
        parent::init();
        
         $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
        );
    }
    
    
    public function indexAction() {

        $links = new Links();
        $this->view->links = $links->getSortedLinks();

        $this->setAfterCreateUrl('menu','index');
        $form = $this->processCreate('menu');
        
        //Odstrani menu item
        if ($this->getRequest()->isPost()) {
            foreach ($this->getRequest()->getPost() as $p) {
                if (is_array($p)) {
                    if (isset($p['delete_menu_item'])) {
                        if ($this->db->delete('menu_items',$this->db->quoteInto('id = ?', $p['id']))) {
                            $this->infoMessage($this->texts->menuitem_delete_success);
                        } else {
                             $this->infoMessage($this->texts->menuitem_delete_error,'error');
                        }
                        $this->redirect('index');
                    }
                }
            }
        }

        //ulozeni nastaveni
        if ($this->getRequest()->isPost() && $this->getRequest()->getPost('save_order')) {
            $i = 1;
            $update_state = true;
            foreach ($this->getRequest()->getPost() as $val) {
                if (is_array($val)) {
                    if (!$this->db->update('menu_items', array('order' => $i, 'visible' => $val['visible'], 'disabled' => !$val['visible']), $this->db->quoteInto('id = ?', $val['id']))) {
                        $update_state = false;
                    }
                    $i++;
                }
            }
            if ($update_state) {
                $this->infoMessage($this->texts->menuitem_save_success);
            } else {
                $this->infoMessage($this->texts->menuitem_save_error,'error');
            }
            $this->redirect('index');
        }

        $this->view->form = $form;
        $this->view->orderform = $this->getMenuItemsForm();
        
    }

    public function getCreateForm($name) {

        switch ($name) {

            case 'menu':
                
                $form = parent::getCreateForm($name);

                // link_id (linkpicker)
                $links = new Links();
                $link_id = new Zend_Form_Element_Select('link_id', array(
                    'label' => $this->texts->link_id.': ',
                    'filters' => array('Null'),
                    'disable-picker' => true,
                    'multioptions' => array(0 => $this->texts->empty) + $links->getLinks(),
                    'class' => 'form-linkpicker'
                ));
                
                $form->addElements(array($link_id));

                $form->setElementDecorators(array(
                'ViewHelper',
                array('HtmlTag', array('tag' => 'div','style'=>'display:inline-block')),
                ));
                
                $link_id->setDecorators(array(
                   'ViewHelper',
                    array('Label',array('style'=>'display:inline-block;margin-right:5px;')),
                    array('HtmlTag', array('tag' => 'div','style'=>'display:inline-block'))
                ));
                
                $form->getElement('menu_submit_create')->setAttrib('style', 'margin-top:0px');
                
                return $form;

            default:
                return null;
        }
        
    }
      


    /**
	 *	Formulář pro změnu nastavení menu - řazení, skrývání položek, odbírání položek
	 *
	 *	@return Zend_Fom Formulář pro řázení a nastavení menu
	 */
	function getMenuItemsForm()
	{
        $form = new Form();
        
        $select = $this->db->select()->from(array('mi'=>'menu_items'))
                ->joinLeft(array('l'=>'links'),'mi.link_id = l.id',array('linkname'=>'l.name'))
                ->order('order');
        
        $menuItems = $this->db->fetchAll($select);

        foreach ($menuItems as $item)
		{
            $menuForm = new Zend_Form_SubForm();
	        $menuForm->setDescription("&nbsp;");

			$id = new Zend_Form_Element_Hidden("id");
			$id->setValue($item['id']);

			// titulek menu
		   $title = new Zend_Form_Element_Text('title');
           $title->setAttrib('class','span4');
           
           if ($item['special']) {
                $title->setValue(str_replace('_', ' ', ucfirst($item['special'])));
           } elseif ($item['link_id']) {
               $title->setValue($item['linkname']);
           }
           
            $title->setAttrib('readonly', 'readonly');
           
			$visible = new Zend_Form_Element_Checkbox('visible');
            $visible->setValue($item['visible']);

			$delete = new Zend_Form_Element_Submit('delete_menu_item');
			$delete->setLabel($this->texts->menuitem_delete)
                    ->setAttrib('class', 'btn btn-danger delete-menu');
           
			$menuForm->addElements(array($id,$title,$visible));
            
            if (!$item['special']) {
                $menuForm->addElement($delete);
            } else {
                //doplneni radku
                $note = new Zend_Form_Element_Note('note',array('value'=>''));
                $menuForm->addElement($note);
            }
            
            $menuForm->setElementDecorators(array(
                'ViewHelper',
                array('HtmlTag', array('tag' => 'td')),
            ));

			$form->addSubForm($menuForm, 'sub_'.$item["id"]);
		}

		// na konci tlacitko ulozit razeni, ale jenom jestli pages neni prazdne
		if ($menuItems)
		{
			$saveOrder = new Zend_Form_Element_Submit('save_order');
			$saveOrder->setLabel($this->texts->menuitem_save)
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
          array('Description',array('tag'=>'td','class'=>'desc','placement'=>'prepend','escape'=>false)),
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
    
    
     public function createRecord($name, $values) {

        switch ($name) {
            case 'menu':
                if(!$values['link_id']) {
                    return false;
                }
                
                $select = $this->db->select()->from('menu_items',array('max'=>new Zend_Db_Expr('max("order")+1')));
                $row = $this->db->fetchRow($select);   
                return $this->db->insert('menu_items', array('order'=>$row['max'],'link_id'=>$values['link_id'],'visible'=>true,'disabled'=>false));

            default:
                return null;
        }
    }

}
