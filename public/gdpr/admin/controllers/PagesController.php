<?php

/**
 * Modul pro spravu a pridavani stranek do tabulky pages.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Admin_PagesController extends Ibulletin_Admin_BaseControllerAbstract {

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
    public function init() {
        parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => false),
            'showpagepictures' => array('title' => $this->texts->submenu_showpagepictures, 'params' => array('action' => 'showpagepictures'), 'noreset' => false),
        );

        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('edit', 'html')
                ->initContext();
    }

    /**
     * metoda pro pridani zaznamu
     * $values obsahuje validni a "sanitized" hodnoty z formulare getCreateForm()
     * metoda by mela vracet TRUE v pripade uspechu, jinak FALSE
     *
     * @param string $name identifikator entity
     * @param array $values
     *
     * @return boolean
     */
    protected function createRecord($name, $values) {
        switch ($name) {
            case 'pages':
                $new_id = false;
                try{
                    $new_id = Pages::add(null,$values['name']);
                }
                catch(Exception $e){
                    // Vyjimku zalogujeme a ohlasime chybu
                    Phc_ErrorLog::warning('AdminPagesController', $e);
                }
                return (boolean)$new_id;
            default: return null;
        }
    }
    
    /**
     * Odstraneni zaznamu
     * @param string $name
     * @param int $id
     * @return boolean
     */
    public function deleteRecord($name, $id) {
    	switch ($name) {
    		case "pages":
                return Pages::delete($id);
    		default: return null;
    	}
    }
    
        
    public function getRecord($name, $id) {
    	switch ($name) {
    		case "pages":
                return Pages::get($id);
    		default: return null;
    	}
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getCreateForm($name) {
        switch ($name) {
            case "pages":
                $form = parent::getCreateForm($name);
                $form->setFormInline(true);

                $emptyValidator = new Zend_Validate_NotEmpty();
                $emptyValidator->setMessage(
                    $this->texts->validators->isempty, Zend_Validate_NotEmpty::IS_EMPTY
                );

                // Nazev
                $form->addElement('text', 'name', array(
                    'label' => $this->texts->name,
                    'size' => '90',
                    'required' => true,
                    'validators' => array(
                        $emptyValidator
                    ),
                ));

                return $form;
            default: return null;
        }
    }


    /**
     * Zajistuje nacteni tlacitka pro pridani polozky a seznamu existujicich polozek.
     * V template je moznost vypsani informacnich hlasek.
     */
    public function indexAction() {

        $this->setAfterCreateUrl('pages', 'index');
        $this->view->form = $this->processCreate('pages');

        $this->setLastVisited();

        Ibulletin_Js::addJsFile('admin/npages.js');
        
        $sel = Pages::getPagesQuery();
            $sel->joinLeft(array('bp'=>new Zend_Db_Expr('(SELECT DISTINCT ON(page_id) * FROM bulletins_pages)')),'bp.page_id = p.id',array('bp.bulletin_id'));
        
        $sel->where('p.deleted IS NULL');

        $grid = $this->grid($sel);

        $grid->addAction('deleted', array(
                    'notempty' => 'bulletin_id',
                    'url' => $this->_helper->url('delete') . '/id/$id',
                    'caption' => $this->texts->action_delete,
                    'confirm' => $this->texts->confirm_delete,
                    'image' => 'remove'
                ))
                ->addAction('preview', array(
                    'url' => array($this, 'printLinks'),
                    'caption' => $this->texts->action_preview,
                    'image' => 'search'
                ))->addAction('qedit', array(
                    'class' => 'editpage',
                    'id' => '$id',
                    'url' => $this->_helper->url('edit') . '/id/$id/format/html',
                    'caption' => $this->texts->action_quickedit,
                    'image' => 'edit'
                ))
                ->addAction('edit', array(
                    'url' => $this->_helper->url('edit') . '/id/$id/',
                    'caption' => $this->texts->action_edit,
                    'image' => 'pencil'
        ));

        $this->view->grid = $grid->process();
    }
    
    
    public function deletedAction() {
          
        $this->setAfterCreateUrl('pages', 'index');
        $this->view->form = $this->processCreate('pages');

        $this->setLastVisited();

        Ibulletin_Js::addJsFile('admin/npages.js');

        $sel = Pages::getPagesQuery();
        $sel->where('p.deleted IS NOT NULL');

        $grid = $this->grid($sel);

        $grid->addAction('restore', array(
                    'url' => $this->_helper->url('restore') . '/id/$id/',
                    'caption' => $this->texts->action_restore,
                    'confirm' => $this->texts->confirm_restore,
                    'image' => 'refresh'
                ));
        
        $this->view->grid = $grid->process();
    }

    /**
     * Pridata content do page
     */
    public function addContentAction() {

        $cid = $this->_getParam('cid');
        $pid = $this->_getParam('pid');

        if (!$cid || !$pid) {
            $this->redirect('index');
        }

        //pocet contentu v pages pro urceni poradi
        $pos = Pages::getCountContent($pid);

        try {

            Pages::addContent($pid, $cid, $pos + 1);
        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
            Phc_ErrorLog::warning('Pages', $e);
        }

        $this->redirect('edit', null, null, array('id' => $pid));
    }

    /**
     * 	Editace contentu. Zpracuje i smazani a
     * 	presmerovani zpatky na seznam.
     *
     * @deprecated
     */
    public function editAction() {

        Ibulletin_Js::getInstance()->vars->confirm_save_pages_text = $this->texts->edit->confirm_save_pages_text;
        Ibulletin_Js::getInstance()->vars->error_save_pages_text = $this->texts->edit->error_save_pages_text;
        Ibulletin_Js::addJsFile('admin/npages.js');

        $id = $this->_request->getParam('id');
        if (!$id) {
            $this->redirect('index');
        }

        $pageform = $this->getEditPageForm($id);

        if ($this->getRequest()->isPost() && $pageform->isValid($_POST)) {
            $fvalue = $pageform->getValue('bodyform');

            //upravi stranku
            try {
                Pages::editPage($fvalue['id'], $fvalue['name'], $fvalue['categories'], $fvalue['tpl_file'], $fvalue['layout_name'], $fvalue['segments']);
                //add page to select bulletins
                if ($buls = $fvalue['bulletins']) {
                    foreach($buls as $bul) {
                        if (!Bulletins::ExistsPageBulletins($fvalue['id'], $bul)) {
                           $bu = new Bulletins();
                           $urlName = Utils::slugify($fvalue['name'], '`');
                           $bu->addPageToBulletin($bul, $fvalue['id'], $urlName);
                        }
                    }
                    //odstrani neoznacene bulletinu
                    if ($fvalue['id']) {
                        $where[] = $this->db->quoteInto('page_id = ?', $fvalue['id']);
                        $where[] = $this->db->quoteInto('bulletin_id NOT IN (?)', $buls);
                        $this->db->delete('bulletins_pages', $where);
                    }
                } else {
                    //jestlize neni vybran bulletin odstranime je z pages
                    if ($fvalue['id']) {
                        $where = $this->db->quoteInto('page_id = ?',$fvalue['id']);
                        $this->db->delete('bulletins_pages',$where);
                    }
                }
            } catch (Exception $e) {
                $this->infoMessage($e->getMessage(), 'error');
                Phc_ErrorLog::warning('Pages', $e);
            }

            //odebere stavajici contenty a ulozi zmeny dle poradi
            Pages::removeContent($fvalue['id'], null);
            if (isset($_POST['cont'])) {
                $cont = $_POST['cont'];
                $i = 1;
                foreach ($cont as $c) {
                    try {
                        Pages::addContent($fvalue['id'], $c, $i++);
                    } catch (Exception $e) {
                        $this->infoMessage($e->getMessage(), 'error');
                        Phc_ErrorLog::warning('Pages', $e);
                    }
                }
            }

            //ulozi vazbu mezi strankou a reprezentantem
            $freps = new Repspages();
            $freps->deletePage($fvalue['id']);
            if ($fvalue['reps']) {
                $freps->addReps($fvalue['reps'], $fvalue['id']);
            }

            //pro AJAX se odesle vysledek JSON a ukonci se akce
            if ($this->_request->isXmlHttpRequest()) {
                $this->view->form = json_encode($this->getSavePagesData($id));
                return;
            } else {
               $this->redirectUri($this->getLastVisited());
            }
        }

        $pageform->populate($this->getPagesData($id));

        $this->view->form = $pageform;

        //pro AJAX akci ukoncime
        if ($this->_request->isXmlHttpRequest())
            return;


        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet();

        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        $ds = Contents::getQuery();
        //filtr pro zobrazeni pouze neprirazenych contentu
        $ds->where('c.id not in (?)', $db->select()->from(array('cp' => 'content_pages'), array('cp.content_id'))->where('cp.page_id = ?', $id));
        $ds->where('c.deleted IS NULL');
        
        $grid = new Ibulletin_DataGrid($ds);

        $grid->setEmptyText($this->texts->index->empty);
        $grid->setDefaultSort('changed');

        $def = array(
            'align' => 'left',
            'default' => '&lt;null&gt;',
        );

        $grid->addColumn('id', array_merge($def, array(
                    'header' => $this->texts->content->id,
                    'field' => 'c.id',
                    'align' => 'right',
                    'width' => '40px',
                    'filter' => array(
                        'type' => 'expr',
                        'datatype' => 'int',
                    )
                )))
                ->addColumn('name', array_merge($def, array(
                    'header' => $this->texts->content->name,
                     'field' => 'c.name',
                     'filter' => array(
                        'autocomplete' => true,
                        'type' => 'expr',
                        'datatype' => 'string'
                    )
                )))
                ->addColumn('bulletin_name', array_merge($def, array(
                    'header' => $this->texts->content->bulletin_name,
                    'filter' => array(
                        'field' => 'b.name',
                        'type' => 'expr',
                        'datatype' => 'string',
                    ),
                )))
                ->addColumn('category_name', array_merge($def, array(
                    'header' => $this->texts->content->category_name,
                    'filter' => array(
                        'field' => 'cat.name',
                        'type' => 'expr',
                        'datatype' => 'string',
                    )
                )))
                ->addColumn('page_name', array_merge($def, array(
                    'header' => $this->texts->content->page_name,
                    'filter' => array(
                        'field' => 'p.name',
                        'type' => 'expr',
                        'datatype' => 'string',
                    )
                )))
                ->addColumn('changed', array_merge($def, array(
                    'header' => $this->texts->content->changed,
                    'type' => 'datetime',
                    'field' => 'changed',
                    'filter' => array(
                        'type' => 'expr',
                        'datatype' => 'datetime',
                    )
                )))
                 ->addColumn('class_name', array_merge($def, array(
                    'header' => $this->texts->content->type,
                    'field' => 'class_name',
                    'type' => 'contentClass'
                )))
                ->addAction('add', array(
                    'url' => $this->_helper->url('add-content') . '/cid/$id/pid/' . $id,
                    'class' => 'addpage',
                    'caption' => $this->texts->edit->action_addcontent,
                    'image' => 'plus'
        ));


        //odkaz na nahled
        $this->view->preview_links = $this->printLinks($id);

        //doplni do context menu je-li treba page id a bulletin id
        $this->view->contextMenu = $this->prepareContentContext($id);

        //tooltip odkazu, zmena defaultniho
        $this->view->preview_links_tooltip = $this->texts->edit->preview_links->tooltip;

        $this->view->content_grid = $grid->process();
    }

    public function showpagepicturesAction() {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $texts = Ibulletin_Texts::getSet();
        $req = $this->getRequest();

        // Regenerovat pictogram - smazeme proste jen soubor $config->general->images->pictogram->name
        if ($req->getParam('regeneratePictogr') && $req->getParam('contentId')) {
            unlink($config->content_article->basepath . '/' . $req->getParam('contentId') .
                    '/' . $config->general->images->pictogram->name);

            // Redirectujeme na URL bez regenrate...
            $this->_helper->redirector('showpagepictures', null, null);
        }

        // Pripravime seznam vydani
        $q = 'SELECT * FROM bulletins_v ORDER BY poradi DESC';
        $bulletins = $db->fetchAll($q);

        // Vezmeme bud bulletin predany v requestu nebo pokud nebyl predan vezmeme nejnovejsi bulletin
        $bulletinId = $req->getParam('bulletinId') ? $req->getParam('bulletinId') : $bulletins[0]['id'];

        // Pripravime seznam vydani
        $bulletinsSel = array();
        foreach ($bulletins as $bulletin) {
            $bulletinsSel[$bulletin['id']] = $bulletin['poradi'] . ' - ' . $bulletin['name'];
        }

        // FORM pro vyber bulletinu
        $form = new Form();
        $form->setFormInline(true);
        $form->setMethod('get');

        // Bulletin
        $form->addElement('select', 'bulletinId', array(
            'label' => $texts->switch_bulletin,
            'multioptions' => $bulletinsSel,
            'value' => $bulletinId,
        ));

        $form->addElement('submit', 'showBulletin', array('label' => $texts->submit,'class'=>'btn-primary'));

        // Najdeme vsechny "hlavni" (ty ktere jsou na prvni v poradi) contenty ve vydani a pripravime
        // vypis jejich informaci a obrazkuu
        $q = '
            SELECT bp.page_id, c.id AS content_id, p.name AS page_name, pc.category_id
            FROM (
                -- Seznam pages s jejich prvnim content_id
                    SELECT a.page_id, a."position", max(content_id) AS content_id
                    FROM (SELECT page_id, min("position") AS "position" FROM content_pages GROUP BY page_id) a
                    JOIN content_pages b ON a.page_id = b.page_id AND a."position" = b."position"
                    GROUP BY a.page_id, a."position"
                )cp
            JOIN content c ON cp.content_id = c.id AND c.deleted IS NULL
            JOIN bulletins_pages bp ON bp.page_id = cp.page_id
            JOIN pages p ON bp.page_id = p.id
            JOIN pages_categories pc ON bp.page_id = pc.page_id
            WHERE bp.bulletin_id = ' . $bulletinId . '
            ORDER BY bp."order"
        ';

        $pages = $db->fetchAll($q);

        foreach ($pages as $key => $page) {
            $pages[$key]['pictogram'] = Contents::getImage($page['content_id'], 'pictogram', $page['category_id']);
        }



        $this->view->chooseBulletinForm = $form;
        $this->view->pages = $pages;
    }

    /**
     * Formular pro editaci page a pridavani contentu
     * @param type $page_id
     * @return \Zend_Form
     */
    public function getEditPageForm($page_id) {

        $config = Zend_Registry::get('config');         // nacte se config
        // Najdeme seznam kategorii
        $categories = Categories::getList(false, null, array('order ASC', 'name', 'id'));
        //$caterogiesSel = array(0 => $this->texts->category_empty); // Pro multiselect neni potreba
        $caterogiesSel = array();
        foreach ($categories as $cat) {
            $caterogiesSel[$cat['id']] = $cat['name'];
        }

        // Najdeme seznam templatu pro pages
        $files = array();
        $path = $config->paths->page_templates;
        if (file_exists($path)) {
            $handle = opendir($path);
            while (($file = readdir($handle)) !== false) {
                if ($file != '.' && $file != '..' && !is_dir($path . '/' . $file) && preg_match('/\.phtml$/i', $file)) {
                    $files[$file] = $file;
                }
            }
        }
        $templates = $files;

        // Find available segments
        $segmentsObj = new Segments();
        $segmentsRaw = $segmentsObj->getSegments();
        $segments = array();
        foreach ($segmentsRaw as $segment) {
            $segments[$segment['id']] = $segment['name'];
        }

        // Najdeme seznam layoutuu
        $layouts = Layouts::getListForSel();

        $emptyValidator = new Zend_Validate_NotEmpty();
        $emptyValidator->setMessage(
                $this->texts->validators->isempty, Zend_Validate_NotEmpty::IS_EMPTY
        );

        $form = new Form();
        $form->setMethod('post');
        $form->setAttrib('id', 'editPageForm');
        $form->setAction($this->view->url());

        $body_form = new Form_SubForm();

        $body_form->addElement('hidden', 'id');

        // Nazev
        $body_form->addElement('text', 'name', array(
            'label' => $this->texts->name,
            'size' => '90',
            'required' => true,
            'validators' => array(
                $emptyValidator,
                array('StringLength', false, array(0, 1000))
            ),
        ))
        ;

        // Kategorie
        $body_form->addElement('multiselect', 'categories', array(
            'label' => $this->texts->category_name,
            'multioptions' => $caterogiesSel,
        ));

        $body_form->addDisplayGroup(array($body_form->getElement('name'),$body_form->getElement('categories')),
                'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));


        // Layout
        $body_form->addElement('select', 'layout_name', array(
            'label' => $this->texts->layout_name,
            'multioptions' => $layouts,
                //'required' => true,
                //'validators' => array($emptyValidator,)
        ));

        // Template
        $body_form->addElement('select', 'tpl_file', array(
            'label' => $this->texts->tpl_file,
            'multioptions' => $templates,
            'required' => true,
            'validators' => array(
                $emptyValidator,
            )
        ));

        $body_form->addDisplayGroup(array($body_form->getElement('layout_name'),$body_form->getElement('tpl_file')),
                'grp2',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

        // Segments
        $body_form->addElement('multiselect', 'segments', array(
            'label' => $this->texts->segments,
            'multioptions' => $segments,
            'required' => false,
        ));


        // Segments
        $body_form->addElement('multiselect', 'bulletins', array(
            'label' => $this->texts->bulletins,
            'multioptions' => $this->getBulletinsForOpt($page_id),
            'required' => false,
        ));


        $freps = new Reps();
        $reps = $freps->getReps();
        $reps_sel = array();
        foreach ($reps as $r) {
            $reps_sel[$r['id']] = $r['name'].' '.$r['surname'];
        }

        // Reps
        $body_form->addElement('multiselect', 'reps', array(
            'label' => $this->texts->reps,
            'multioptions' => $reps_sel,
            'required' => false,
        ));

        $body_form->addDisplayGroup(array($body_form->getElement('segments'),$body_form->getElement('bulletins')),
                'grp3',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

        //seznam pridanych kontentu
        $pages = Pages::getPagesContent($page_id);


        $contents = new Zend_Form_Element_Hidden('contents');
        $body_form->addElement($contents);


        $body_form->removeDecorator('fieldset');

         if($this->_request->isXmlHttpRequest()) {
             $body_form->addDecorator('ModalBody');
           }

           $form->addSubForm($body_form, 'body-form');


        $form->addElement('submit', 'save', array(
            'label' => $this->texts->submit_save,
            'id' => 'save_page',
            'class'=>'btn-primary'
        ));

       if($this->_request->isXmlHttpRequest()) {

            $form->addElement('button', 'close', array(
            'label' => $this->texts->submit_close,
            'class' => 'btn',
            'data-dismiss' => 'modal',
            'aria-hidden' => 'true'
            ));


        $form->addDisplayGroup(
                array($form->getElement('close'),$form->getElement('save')), 'action', array('displayGroupClass' => 'Form_DisplayGroup_ModalFoot')
        );
       }

       $contents->setDecorators(array(array('ViewScript', array('viewScript' => 'pages/content_form.phtml', 'contents' => $pages))));

       return $form;
    }

    /**
     * Ziska z DB pole vsech existujicih pages, nebo jednu konkretni page
     *
     * @param int ID page kterou chceme ziskat jako jedinou
     * @return array Data daneho contentu
     */
    public function getPagesData($id = null) {
        $db = $this->db;

        // Rozsireni WHERE podle toho, jestli hledame jeden zaznam, nebo vice
        if (is_numeric($id)) {
            $where = 'p.id = ' . (int) $id;
        } else {
            $where = 'true = true';
        }

        $q = "SELECT p.id, p.name, l.id AS link_id, c.id AS content_id, p.layout_name, p.tpl_file,
                     cp.position, c.class_name, c.serialized_object
              FROM pages p LEFT JOIN content_pages cp ON  p.id = cp.page_id
                   LEFT JOIN content c ON cp.content_id = c.id
                   LEFT JOIN links l ON p.id = l.page_id
              WHERE $where
              ORDER BY id DESC, cp.position";
        $rows = $db->fetchAll($q);

        // Projdeme zaznamy, predelame do stromu a deserializujeme objekty
        $data = array();
        foreach ($rows as $key => $row) {
            if (!empty($row['class_name']) && !empty($row['serialized_object'])) {
                Zend_Loader::loadClass($row['class_name']);
                $object = unserialize(stripslashes($row['serialized_object']));
            } else {
                $object = null;
            }

            if (!isset($data[$row['id']])) {
                // Preorganizujeme data do stromovejsi struktury
                $row1 = $row;
                unset($row1['content_id']);
                unset($row1['position']);
                unset($row1['class_name']);
                unset($row1['serialized_object']);
                $data[$row['id']] = $row1;
                $data[$row['id']]['contents'][] = array(
                    'content_id' => $row['content_id'],
                    'position' => $row['position'],
                    'obj' => $object);
            } else {
                // Jen pridame objekt a pozici jako dalsi prvek do pole
                $data[$row['id']]['contents'][] = array(
                    'content_id' => $row['content_id'],
                    'position' => $row['position'],
                    'obj' => $object);
            }
        }

        // Projdeme znovu data a dohledame vzdy seznam kategorii do kterych spada
        // TODO - zatim ma kazda page jen jednu kategorii - to se ale zmeni
        foreach ($data as $key => $row) {
            $q = sprintf('
			     SELECT category_id, significance, c.name
			     FROM pages_categories pc
			     JOIN categories c ON pc.category_id = c.id
			     WHERE page_id = %d
			     ORDER BY significance DESC', $key);
            //uprava nacteni jedne kategorie do pole - stale ma page pouze jednu kategorii
            $categories = $db->fetchAll($q);
            if ($categories){
                foreach($categories as $category){
                    $data[$key]['categories'][] = $category['category_id'];
                }
            }
        }

        // Najdeme vazby k segmentum a pridame je do dat
        $sel = new Zend_Db_Select($db);
        $sel->from(array('ps' => 'pages_segments'), array('*'))
                ->join(array('s' => 'segments'), 'ps.segment_id = s.id', array())
                ->order(array('ps.page_id'));
        $pagesSegments = $db->fetchAll($sel);
        foreach ($pagesSegments as $row) {
            if (isset($data[$row['page_id']])) {
                if (isset($data[$row['page_id']]['segments']) && is_array($data[$row['page_id']]['segments'])) {
                    $data[$row['page_id']]['segments'][] = $row['segment_id'];
                } else {
                    $data[$row['page_id']]['segments'] = array($row['segment_id']);
                }
            }
        }

        //populate bulletins
        $bsel = $this->db->select()
                ->from(array('bp'=>'bulletins_pages'),array('bp.bulletin_id'))
                ->where('bp.page_id = ?',$id);

        $b_rows = $this->db->fetchAll($bsel);

        foreach($b_rows as $row) {
            $data[$id]['bulletins'][] = $row['bulletin_id'];
        }

        //populate reps
        $freps = new Repspages();
        $data[$id]['reps'] = $freps->getReps($id);

        if (is_numeric($id)) {
            // Pokud chceme jen jeden zaznam vratime jen prvni radek
            reset($data);
            return current($data);
        } else {
            return $data;
        }
    }

    /**
     * Ziska z DB pole vsech existujicih pages, nebo jednu konkretni page
     *
     * @param int ID page kterou chceme ziskat jako jedinou
     * @return array Data daneho contentu
     */
    public function getSavePagesData($id) {
        $db = $this->db;

        $select = Pages::getPagesQuery();
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->columns(array('p.id', 'name' => 'p.name', 'link' => 'l.id', 'category' => 'cat.name', 'tpl_file', 'layout_name'))
                ->where('p.id = ?', $id);

        $pages = $db->fetchRow($select);

        return $pages;
    }

    //vraci odkaz pro preview
    public function printLinks($id = null)
    {
        $db = Zend_Registry::get('db');                 // db handler

        //zobrazovani links v datagridu -> callback datagridu posila array ...
        if(is_array($id)) {
            $id = $id['id'];
        }

    	if(is_numeric($id)){
    		// Pokusime se vytvorit odkaz(y) pro nahled contentu
    		// pomoci existujici page s timto contentem a priprazeni k bulletinu
    		$q = "SELECT bp.url_name AS article, b.url_name AS name
    			FROM pages p
                JOIN bulletins_pages bp ON p.id = bp.page_id
                JOIN bulletins b ON bp.bulletin_id = b.id
                WHERE p.id = ".$id." AND b.deleted IS NULL
                ORDER BY b.valid_from DESC";
    		$row = $db->fetchRow($q);
    		if($row) {
        		return $this->view->url($row,'bulletinarticle');
    		}
    	}

        return null;
    }

     /**
     * Upravi a vrati upravene context menu, do ktereho paklize najde, odkaz pro editaci pages
     * @param type $id
     * @return array
     */
    public function prepareContentContext($id = null) {
        $db = Zend_Registry::get('db');                 // db handler

        if (is_numeric($id)) {
            // najde id page a id bulletin contentu
            $q = "SELECT p.id AS page_id, b.id AS bulletin_id
    			FROM pages p
                JOIN bulletins_pages bp ON p.id = bp.page_id
                JOIN bulletins b ON bp.bulletin_id = b.id
                WHERE p.id = " . $id . " AND b.deleted IS NULL
                ORDER BY b.valid_from DESC";
            $row = $db->fetchRow($q);

            if ($row) {

                $bulletin_id = $row['bulletin_id'];
                foreach ($this->_contextMenu as $key => $cxmenu) {
                    if (($cxmenu['route']['controller'] == 'bulletinassembler')) {
                        //pokud mame id bulletinu upravime routu
                        if ($bulletin_id) {
                            $this->_contextMenu[$key]['route']['action'] = 'edit';
                            $this->_contextMenu[$key]['route']['id'] = $bulletin_id;
                        }
                    }
                }
            }
        }

        return $this->_contextMenu;
    }


    /**
     * Vrati bulletiny pro options select formu
     * @param type $page_id Page ID
     * @return array
     */
    public function getBulletinsForOpt($page_id) {
        $sel = array();

        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');

		$select1 = $db->select()
			->from(array("b" => $config->tables->bulletins))
			->order('valid_from DESC')
            ->where('deleted IS NULL')
            ->where('hidden = ?','false')
            ->limit(5);

        $select2 = $db->select()
			->from(array("b" => $config->tables->bulletins))
            ->joinLeft(array("bp" =>"bulletins_pages"),"b.id = bp.bulletin_id",null)
            ->where('bp.page_id = ?',$page_id);

        $select = $db->select()->union(array('('.$select1.')','('.$select2.')'))->order(array('valid_from DESC','name'));
        $rows = $db->fetchAll($select);


        foreach($rows as $row) {
            $sel[$row['id']] = $row['name'];
        }

        return $sel;
   }
   
   
     /**
     * Datagrid
     * @param Zend_Db_Select $select
     * @return Ibulletin_Datagrid
     */
    public function grid($select) {

        $grid = new Ibulletin_DataGrid($select);
        $grid->setDefaultSort('id');
        $grid->setEmptyText($this->texts->index->empty);

        $grid->addColumn('id', array(
                    'header' => $this->texts->id,
                ))
                ->addColumn('name', array(
                    'header' => $this->texts->name,
                    'field' => 'p.name',
                    'filter' => array(
                        'autocomplete' => true,
                        'type' => 'expr',
                        'datatype' => 'string'
                    )
                ))
                ->addColumn('link', array(
                    'header' => $this->texts->link_id,
                    'field' => 'link'
                ))
                ->addColumn('category', array(
                    'header' => $this->texts->category_name,
                    'field' => 'cat.name',
                    'filter' => array(
                        'autocomplete' => true,
                        'type' => 'expr',
                        'datatype' => 'string'
                    )
                ))
                ->addColumn('tpl_file', array(
                    'header' => $this->texts->tpl_file,
                    'field' => 'p.tpl_file'
                ))
                ->addColumn('layout_name', array(
                    'header' => $this->texts->layout_name,
                    'field' => 'p.layout_name'
        ));

        return $grid;
    }
    
    
     /**
     * Smaze page
     */
    public function deleteAction() {

		$id = $this->_request->getParam('id');
    	if (!$id) {
    		$this->redirect('index');
    	}
        
        $this->setMessage('deleted', $this->texts->delete->success);
        $this->setMessage('notdeleted', $this->texts->delete->error);

    	$this->setAfterDeleteUrl('pages','index');
	    $this->processDelete('pages', $id);

    }
    
    
     /**
     * Obnovi odstranenou page
     */
    public function restoreAction() {

        $id = $this->_getParam('id');

        if (!$id) {
            $this->redirect('index');
        }


        if (Pages::restore($id)) {
            $this->infoMessage($this->texts->restore->success, 'success');
        } else {
            $this->infoMessage($this->texts->restore->error, 'error');
        }


        $this->redirect('deleted');
    }


}
