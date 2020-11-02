<?php

/**
 * Modul pro spravu a pridavani contentu typu calendar 
 *
 * @author Ondra Blaha, <ondrej.blaha@pearshealthcyber.com>
 */
class Admin_Content_Not_Found_Exception extends Exception {
    
}

class Admin_CalendarController extends Ibulletin_Admin_ContentControllerAbstract {

    /**
     *  Jmeno tridy pro kterou je tato editace urcena
     */
    var $serialized_class_name = "Ibulletin_Content_Calendar";

    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    var $contentType = 'Ibulletin_Content_Calendar';

    /**
     *  Prozatim staticke jmeno template pro tento druh obsahu
     */
    var $tpl_name = 'calendar_1.phtml';

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
    public function init() {
        parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => false)
        );
        
    }

    /**
     *  Editace contentu
     */
    public function editAction() {
        Ibulletin_Js::addJsFile('admin/collapse.js');

        //bootstrap_tokenfield pro labely
        Ibulletin_Js::addJsFile('bootstrap_tokenfield/bootstrap-tokenfield.min.js');
        Ibulletin_HtmlHead::addFile('../scripts/bootstrap_tokenfield/bootstrap-tokenfield.css');

        $id = $this->_getParam('id');

        if (!$id || !is_numeric($id)) {
            $this->redirect('index');
        }

        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action' => 'edit', 'id' => $id), null, true, 'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        $data = $this->getContentData($this->serialized_class_name, $id);

        $form = $this->getContentEditForm($data);

        if ($this->_request->isPost() && $form->isValid($this->_request->getPost())) {

            if ($this->saveForm($form)) {
                $this->infoMessage($this->texts->edit->saved);
                $this->redirect(array('id' => $id));
            }
        }

        // Nastavime id do view
        $this->view->content_id = $id;

        //doplni do context menu je-li treba page id a bulletin id
        $this->view->contextMenu = $this->prepareContentContext($id);

        //odkaz pro nahled
        $this->view->preview_links = $this->printLinks($id);

        // Vypiseme formular
        $this->view->form = $form;

        // Seznam linku ke zobrazeni
        $this->renderLinks();
    }

    /**
     *  Vytvori a vrati formular pro editaci zvaci vlny.
     *
     *  @param $data populate data 
     */
    function getContentEditForm($data) {
        
        $urlHlpr = new Zend_View_Helper_Url();

        // Pripravime selectbox existujicich autoru
        $sel = $this->db->select()->from('authors')->order('name');

        $authorsA = $this->db->fetchAll($sel);
        $authors_sel = array('default' => $this->texts->author_new, 'none' => $this->texts->author_none);
        foreach ($authorsA as $var) {
            $authors_sel[$var['id']] = $var['name'];
        }

        $form = new Form();
        $form->setAction($urlHlpr->url(array('action' => 'edit')));
        $form->setMethod('post');


        // Id - muze byt readonly
        $id = new Zend_Form_Element_Text(array(
            'name' => 'data_id',
            'label' => $this->texts->id,
            'class' => 'span1'
        ));
        $id->setOptions(array('readonly' => 'readonly'));
        $id->setValue($data['id']);

        // Nazev
        $name = new Zend_Form_Element_Text(array(
            'name' => 'data_name',
            'label' => $this->texts->name,
            'autofocus' => 'autofocus'
        ));
        $name->setRequired(true);
        $name->setValue($data['obj']->name);

        $name->addValidator('NotEmpty', true, array(
            'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));

        // Datum vytvoreni
        $created = new Zend_Form_Element_Text(array(
            'name' => 'data_created',
            'class' => 'datetimepicker',
            'autocomplete' => 'off',
            'label' => sprintf($this->texts->created, $this->config->general->dateformat->medium)
        ));
        $created->setRequired(true);

        $date = new Zend_Date($data['created'], Zend_Date::ISO_8601);
        $created->setValue($date->toString($this->config->general->dateformat->medium));

        $created->addValidator('NotEmpty', true, array(
            'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));


        // Autor select
        $author_sel = new Zend_Form_Element_Select(array(
            'name' => 'data_author',
            'label' => $this->texts->author,
            'multioptions' => $authors_sel
        ));
        if (is_numeric($data['author_id'])) {
            $author_sel->setValue($data['author_id']);
        } else {
            $author_sel->setValue('default');
        }

        // Novy autor
        $author = new Zend_Form_Element_Text(array(
            'name' => 'data_author_new',
            'label' => $this->texts->new_author
        ));


        $hide_date_author = new Zend_Form_Element_Checkbox(array(
            'name' => 'hide_date_author',
            'label' => $this->texts->hide_date_author
        ));

        if (isset($data['obj']->hide_date_author)) {
            $hide_date_author->setValue($data['obj']->hide_date_author);
        }

        $show_annotation = new Zend_Form_Element_Checkbox(array(
            'name' => 'show_annotation',
            'label' => $this->texts->show_annotation
        ));

        if (isset($data['obj']->show_annotation)) {
            $show_annotation->setValue($data['obj']->show_annotation);
        }

        // Labels
        $labels = new Zend_Form_Element_Text(array(
            'name' => 'labels',
            'label' => $this->texts->labels,
            'class' => 'span6'
        ));

        if (isset($data['obj']->labels)) {
            $labels->setValue($data['obj']->labels);
        }

        // Anotace
        $annotation = new Zend_Form_Element_Textarea(array(
            'name' => 'data_annotation',
            'label' => $this->texts->annotation,
            'class' => 'editarea'
        ));

        $annotation->setValue($data['obj']->annotation);

        $annotation->setAttrib('rows', '10');

        // Html
        $html = new Zend_Form_Element_Textarea(array(
            'name' => 'data_html',
            'label' => $this->texts->html,
            'class' => 'editarea'
        ));

        $html->setValue($data['obj']->html);


        // tlacitko ulozit
        $save = new Zend_Form_Element_Submit('data_save');
        $save->setLabel($this->texts->submit);
        $save->setAttrib('class', 'btn-primary');


        $form->addElements(array(
            $id, $name, $created, $author_sel, $author,
            $hide_date_author, $show_annotation, $labels,
            $annotation, $html, $save
        ));

        $form->addDisplayGroup(array($name, $created), 'grp1', array('displayGroupClass' => 'Form_DisplayGroup_Inline', 'order' => 1));

        $form->addDisplayGroup(array($author_sel, $author), 'grp2', array('displayGroupClass' => 'Form_DisplayGroup_Inline', 'order' => 2));

        $links = new Links();
        $html->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml', 'rank' => '_1', 'templates' => $this->loadTemplates(), 'links' => $links->getSortedLinks()))));
        $annotation->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml', 'rank' => '_2', 'templates' => $this->loadTemplates(), 'links' => $links->getSortedLinks()))));

        return $form;
    }

    /**
     * Ziska z DB pole vsech contentu zadaneho typu a deserializuje objekty
     * vlastniho obsahu.
     *
     * @param string Jmeno tridy jejiz contenty chceme ziskat
     * @param int ID contentu ktery chceme ziskat jako jediny
     * @return array Data daneho contentu
     */
    public function getContentData($class_name, $id = null) {
        $db = $this->db;

        if (is_numeric($id)) {
            $where = 'id = ' . (int) $id;
        } else {
            $class_name_quot = $db->quote($class_name);
            $where = 'class_name = ' . $class_name_quot;
        }

        $q = "SELECT * FROM content WHERE $where ORDER BY created DESC, changed DESC";
        $rows = $db->fetchAll($q);

        if (empty($rows)) {
            if (is_numeric($id)) {
                throw new Admin_Content_Not_Found_Exception("Content with id='.$id.' not found.");
            }
        }

        Zend_Loader::loadClass($class_name);

        // Projdeme zaznamy a deserializujeme objekty
        foreach ($rows as $key => $row) {
            $object = unserialize(stripslashes($row['serialized_object']));
            $rows[$key]['obj'] = $object;
        }

        // Pokud chceme jen jeden zaznam vratime jen prvni radek
        if (is_numeric($id)) {
            return $rows[0];
        } else {
            return $rows;
        }
    }

    /**
     * Ulozi formular do DB
     * @param Zend_Form Formular editace obsahu
     * @return int ID noveho nebo editovaneho zaznamu
     */
    public function saveForm($form) {

        $id = $form->getValue('data_id');

        if (!$id || !is_numeric($id)) {
            return null;
        }

        // Pro porovnani puvodniho jmena contentu se jmenem page vytahneme puvodni content z DB
        $orig_content = Contents::get($id);
        $orig_obj = $orig_content['object'];

        $class_name = $this->serialized_class_name;
        $obj = new $class_name();
        $obj->id = $id;
        $obj->annotation = $form->getValue('data_annotation');
        $obj->name = $form->getValue('data_name');
        $obj->hide_date_author = $form->getValue('hide_date_author');
        $obj->hide_pdf_link = $form->getValue('hide_pdf_link');
        $obj->show_annotation = $form->getValue('show_annotation');
        $obj->show_form_after_save = $form->getValue('show_after_save');
        $obj->labels = $form->getValue('labels');
        $obj->setAuthor($form->getValue('data_author'));
        $obj->tpl_name = $this->tpl_name;
        $obj->html = $form->getValue('data_html');

        // Pripravime autora, pripadne udelame novy zaznam do authors
        $author_sel = $form->getValue('data_author');
        $author_new = $form->getValue('data_author_new');
        $author_row = null;
        if (is_numeric($author_sel)) {
            $sel = $this->db->select()->from('authors')->order('name')->where('id = :id')->limit(1);
            $author_row = $this->db->fetchAll($sel, array('id' => $author_sel));
        }
        if (!$author_row && !empty($author_new) && $author_sel == 'default') {
            // Vytvorime novy zaznam do authors
            $this->db->insert('authors', array('name' => $author_new));
            $author_sel = $this->db->lastInsertId('authors', 'id');
            // Pridame pro toto nacteni formu noveho autora do listu
            $form->getElement('data_author')->addMultiOption($author_sel, $author_new);
            $obj->setAuthor($author_new);
        } elseif (!$author_row) {
            $author_sel = null;
        } elseif ($author_row) {
            $obj->setAuthor($author_row[0]['name']);
        }
        $form->getElement('data_author_new')->setValue('');
        $form->getElement('data_author')->setValue($author_sel);

        // Datum vytvoreni clanku
        $created = new Zend_Date($form->getValue('data_created'), $this->config->general->dateformat->medium);
        $form->getElement('data_created')->setValue($created->toString($this->config->general->dateformat->medium));

        $insert_data = array(
            'name' => $obj->name,
            'changed' => new Zend_Db_Expr('current_timestamp'),
            'serialized_object' => addslashes(serialize($obj)),
            'class_name' => $this->serialized_class_name,
            'author_id' => $author_sel,
            'created' => $created->get(Zend_Date::ISO_8601)
        );

        if (!is_numeric($id)) {
            $this->db->insert('content', $insert_data);
            $id = $this->db->lastInsertId('content', 'id');
        }

        // Nastavime jeste ID contentu do objektu a znovu ulozime
        $obj->id = $id;
        $insert_data['serialized_object'] = addslashes(serialize($obj));

        $this->db->update('content', $insert_data, sprintf('id=%d', $id));

        # Ulozime data objektu do vyhledavaciho indexu
        $index = Ibulletin_Search::getSearchIndex();
        $search_doc = $obj->getSearchDocument();

        // Nejprve odstranit stare indexy pro toto content ID
        $term = new Zend_Search_Lucene_Index_Term($id, 'content_id');
        $query = new Zend_Search_Lucene_Search_Query_Term($term);
        $hits = $index->find($query);
        foreach ($hits as $hit) {
            $index->delete($hit->id);
        }

        // Zaindexujeme dokument
        $index->addDocument($search_doc);

        // Upravime pripadne zaznamy v pages a links - nastavime nove jmeno
        $sel = $this->db->select()->from('content_pages', 'page_id')->where('content_id = :id')->where('position = 1')->order('page_id');

        $page_id = $this->db->fetchOne($sel, array('id' => $id));

        if (!empty($page_id)) {
            // Ziskame zaznam page pro porovnani name s name contentu
            $sel = $this->db->select()->from('pages')->where('id = :id');

            $page = $this->db->fetchAll($sel, array('id' => $page_id));
            $page = $page[0];

            // Menime jen pokud bylo pred zmenou jmeno shodne se jmenem v contentu
            if (trim($orig_obj->name) == trim($page['name'])) {

                $this->db->update('pages', array('name' => $obj->name), "id = $page_id");

                // Upravime i link pouze pokud je jmeno stranky stejne s contentem, 
                // protoze link odkazuje na stranku a ne na content
                $sel = $this->db->select()->from('links', 'id')->where('page_id = :id')->order('id');

                $link_id = $this->db->fetchOne($sel, array('id' => $page_id));

                if (!empty($link_id)) {
                    $this->db->update('links', array('name' => $obj->name), "id = $link_id");
                }
            }
        }


        return $id;
    }
    
    /**
     * Správa událostí kalendáře
     */
    public function eventsAction() {
        
        $id = $this->_getParam('id');
        
        if (!is_numeric($id)) {
            $this->redirect('index');
        }
        
        //ulozeni udalosti
        if ($this->getRequest()->isPost()) {
            
            $this->_helper->viewRenderer->setNoRender(true);
            
            $data = $this->getRequest()->getPost();
            $calendar = new Calendar();
            if ($calendar->save($data)) {
              $this->_helper->json->sendJson(array('status'=>'success','msg'=>$this->texts->events->save_success));
            } else {
              $this->_helper->json->sendJson(array('status'=>'error','msg'=>$this->texts->events->save_error));
            }
            
            return $this->render('save-event');    
                    
        } 

        $this->view->contentId = $id;

        Ibulletin_JS::addJsFile('datatables/datatables.min.js');
        Ibulletin_HtmlHead::addFile('../scripts/datatables/datatables.min.css');
        Ibulletin_Js::addJsFile('admin/calendar.js');
        
        $this->view->getEventsURL = $this->_helper->url('get-events',null,null,array('id'=>$id));
        $this->view->checkEventsURL = $this->_helper->url('check-events',null,null,array('id'=>$id));
        
        $this->moduleMenu->addItem($this->texts->events->submenu_events, array('action' => 'events', 'id' => $id), null, true, 'events');
        $this->moduleMenu->setCurrentLocation('events');

    }
    
    
    /**
     * Vrati json ze zjistenych udalosti
     */
    public function checkEventsAction() {
        
        $id = $this->_getParam('id');

        if (!$id || !is_numeric($id)) {
            return null;
        }
        
        $calendar = new Calendar();
        $data = $calendar->getEUNIEvents($id);

        foreach ($data['data'] as $k => $v) {

            $buttons = array();
            $buttons[] = '<a href="#" data-original-title="detail akce" class="tip btn btn-info btn-mini availableEventDetail" data-id="'.$v[0].'"><span class="glyphicon glyphicon-new-window"></span></a>';
            $buttons[] = '<a href="#" class="btn btn-info btn-mini eventSave" data-id="'.$v[0].'"><span class="glyphicon glyphicon-plus"></span></a>';
            
            $data['data'][$k][6] = implode(' ',$buttons);
        }

        $this->_helper->json->sendJson($data);

    }
    
    
    /**
     * Akce pro ulozeni udalosti do kalendare 
     */
    public function saveEventAction() {
        
        $this->_helper->viewRenderer->setNoRender(true);
        
        if ($this->getRequest()->isPost()) {
            
            $data = $this->getRequest()->getPost();
            $calendar = new Calendar();
            if ($calendar->save($data)) {
              $this->_helper->json->sendJson(array('status'=>'success','msg'=>$this->texts->events->save_success));
            } else {
              $this->_helper->json->sendJson(array('status'=>'error','msg'=>$this->texts->events->save_error));
            }
                    
        } 

    }
    
    /**
     * Akce pro odstraneni udalosti z kalendare
     */
    public function deleteEventAction() {

        $this->_helper->viewRenderer->setNoRender(true);

        $id = $this->_getParam('id');

        if (!$id || !is_numeric($id)) {
            return null;
        }
        $calendar = new Calendar();

        if ($calendar->delete($calendar->getAdapter()->quoteInto('id = ?',$id))) {
            $this->_helper->json->sendJson(array('status' => 'success', 'msg' => $this->texts->events->delete_success));
        } else {
            $this->_helper->json->sendJson(array('status' => 'error', 'msg' => $this->texts->events->delete_error));
            
        }
    }

    /**
     * Akce pro ziskani udalosti kalendare
     */
    public function getEventsAction() {
        
        $id = $this->_getParam('id');

        if (!$id || !is_numeric($id)) {
            return null;
        }

        $this->_helper->viewRenderer->setNoRender(true);

        $items = Calendar::getEvents($id);
        
        $events = array();
        $events['data'] = array();
        
        foreach ($items as $item) {
            $buttons = array();
            $buttons[] = '<a href="#" data-original-title="detail akce" class="tip btn btn-info btn-mini eventDetail" data-id="'.$item['id'].'"><span class="glyphicon glyphicon-new-window"></span></a>';
            $buttons[] = '<a href="'.$this->_helper->url('delete-event',null,null,array('id'=>$item['id'])).'" class="btn btn-danger btn-mini eventDelete" data-confirmtext="'.$this->texts->events->confirm_delete.'"><span class="glyphicon glyphicon-trash"></span></a>';
        
            $events['data'][] = array($item['event_id'],$item['title'],$item['date_begin'],$item['date_end'],$item['specializations'],$item['tags'],implode(' ',$buttons));
        
            $dateBegin = new Zend_Date($item['date_begin']);
            $dateEnd = new Zend_Date($item['date_end']);
            $items[$item['id']]['formattedDateBegin'] = $dateBegin->toString($this->config->general->dateformat->medium);
            $items[$item['id']]['formattedDateEnd'] = $dateEnd->toString($this->config->general->dateformat->medium);
        }

        $events['enum']['tags'] = Calendar::getTags();
        $events['enum']['specializations'] = Calendar::getSpecializations();
        $events['raw'] = $items;
        
        $this->_helper->json->sendJson($events);
        
    }

}
