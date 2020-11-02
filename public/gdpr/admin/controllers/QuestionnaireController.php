<?php
/**
 * Modul pro spravu a pridavani contentu typu questionnaire (formular).
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
 
class Admin_Content_Not_Found_Exception extends Exception {}

 
class Admin_QuestionnaireController extends Ibulletin_Admin_ContentControllerAbstract
{
    /**
     *  Jmeno tridy pro kterou je tato editace urcena
     */
    var $serialized_class_name = "Ibulletin_Content_Questionnaire";
    
    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    var $contentType = 'Ibulletin_Content_Questionnaire';
    
    /**
     *  Prozatim staticke jmeno template pro tento druh obsahu
     */
    var $tpl_name = 'questionnaire_1.phtml';
    
	/**
     *  Cislo aktualne editovaneho listu
     */
    var $current_sheet = null;
    
    
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
     * Zprostredkuje formular pro zalozeni noveho contentu
     */
    public function newAction()
    {
        $this->view->form = $this->getContentEditForm(null, true, false);
        
        // Pomocne promenne, bez kterych se ve view zobrazuji chyby
        $this->view->sheets = array();
        $this->view->current_sheet = 0;
        
        $this->renderScript($this->getControllerName().'/edit.phtml');
        return;
    }
    
    
    /**
     *  Editace contentu. Zpracuje i smazani a 
     *  presmerovani zpatky na seznam.
     */
    public function editAction()
    {
    	$texts = Ibulletin_Texts::getSet();
        $db = Zend_Registry::get('db');                 // db handler
        $config = Zend_Registry::get('config');         // nacte se config
        $request = $this->getRequest();
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        Ibulletin_Js::addJsFile('admin/collapse.js');
        
         //bootstrap_tokenfield pro labely
        Ibulletin_Js::addJsFile('bootstrap_tokenfield/bootstrap-tokenfield.min.js');
        Ibulletin_HtmlHead::addFile('../scripts/bootstrap_tokenfield/bootstrap-tokenfield.css');
        
        Zend_Loader::loadClass('Zend_View_Helper_Url');
        
        // Najdeme a nastavime aktualni list, ktery je editovan
        $this->findCurrentSheet();
        
        // Nejdriv se musi zjistit ID, ktere chce uzivatel
        // editovat, nebo smazat
        foreach ($_POST as $pom)
        {
            if (is_array($pom))
            {
                // editace
                if (isset($pom['data_edit']))
                {
                    $id = $pom['data_id'];
                    $action = 'edit';
                }
                
                // ukazat data
                if (isset($pom['data_show']))
                {
                    $id = $pom['data_id'];
                    $action = 'show';
                }

                // mazani
                elseif (isset($pom['data_delete']))
                {
                    $id = $pom['data_id'];
                    $action = 'delete';
                }
                
                // vytvoreni defaultni stranky pro dany content
                elseif (isset($pom['data_create_page']))
                {
                    $id = $pom['data_id'];
                    $action = 'create_page';
                }
            }
        }
        // Pro editaci bereme i id z metody get
        if(!isset($id) && $request->__isset('id')){
            $id = $request->getParam('id');
            $action = 'edit';
        }
        
        if(!isset($id) && empty($_POST)){
            // Pokud nebylo nic odeslano presmerujeme zpet na spravu Contentuu
            $redirector->gotoRouteAndExit(array('action' => null));
        }
        
        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit'); 
        $this->moduleMenu->setCurrentLocation('edit');
        
        $showForm = false;
        
        # Vybereme akci a provedeme
        if(isset($id) && is_numeric($id)){
            if($action == 'edit'){
                $data = $this->getContentData($this->serialized_class_name, $id);
                
                # Pridat list:
                if($request->__isSet('add_sheet')){                	
                	// Ziskame klic posledniho prvku
                    end($data['obj']->html);
                    $key = key($data['obj']->html);
                    $data['obj']->html[$key + 1] = '';
                    $this->current_sheet = $key + 1;
					
                    $this->infoMessage(Ibulletin_Texts::get('sheet_list.main.added'));
                    $save_it = true;
                }
                # Ubrat list:
                if($request->__isSet('remove_sheet')){
                    // Ziskame klic posledniho prvku
                	end($data['obj']->html);
                	$key = key($data['obj']->html);

                    // Odebereme, pokud to není jediný list...
                    if(count($data['obj']->html) > 1){
                        unset($data['obj']->html[$key]);
                        $this->infoMessage(Ibulletin_Texts::get('sheet_list.main.removed'));
                    }
                    else{
                        $this->infoMessage(Ibulletin_Texts::get('sheet_list.main.notremoved'));
                    }

                    // Ziskame klic posledniho prvku a pokud je stejny jako aktualni list,
                    // zmenime aktualni list
                    end($data['obj']->html);
                    $key = key($data['obj']->html);
                    if($this->current_sheet > $key){
                        $this->current_sheet = $key;
                    }
                    $save_it = true;
                }

                // Hodime si do promenne formular...
                $form = $this->getContentEditForm($data);
                $showForm = true;
                if(isset($save_it)){
                    $this->saveForm($form, $data);
                    $this->redirect(array('id' => $id,'sheet' => $this->current_sheet+1));
               }
            }
            elseif($action == 'show'){
                // presmerujeme na akci showdata s parametrem ID
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                $redirector->gotoRouteAndExit(array('action' => 'showdata', 'id' => $id), 'default');
            }
            elseif($action == 'delete'){
                $db->delete('content_pages', sprintf('content_id=%d', $id));
                $db->delete('content', sprintf('id=%d', $id));

                // Spustime index action
                $this->infoMessage('Content was deleted.');
                $this->redirect('index');
                return;
            }
            elseif($action == 'create_page'){
                // Vytvorime zaznamy s defaultnimi hodnotami v content_pages,
                // pages a links
                
                $config =  Zend_Registry::get('config');
                $config_def_page = $config->admin_default_page_for_content;
                
                $cont_data = $this->getContentData($this->serialized_class_name, $id);
                $name = $cont_data['obj']->getName();
                // Jmeno ma maximalni povolenou delku v pages a links 100 znaku
                $name = substr($name, 0, 100);
                
                // Pridame zaznam do pages
                $ins_data = array('tpl_file' => $config_def_page->tpl_file,
                                  'name' => $name);
                $db->insert('pages', $ins_data);
                $page_id = $db->lastInsertId('pages', 'id');
                
                // Pridame zaznam do links
                $ins_data = array('page_id' => $page_id,
                                  'name' => $name);
                $db->insert('links', $ins_data);
                
                // Pridame zaznam do content_pages
                $ins_data = array('page_id' => $page_id,
                                  'content_id' => $id,
                                  'position' => $config_def_page->position);
                $db->insert('content_pages', $ins_data);
                
                
                // Spustime index action
   				$this->infoMessage('Default page was created.');
                $this->redirect('index');
                return;
            }
        }
        // Nebyla rozpoznana akce vyvolana ze seznamu - zkusime ulozeni
        if($request->__isSet('data_save')){
            $form = $this->getContentEditForm(null, true);
            
            // Kontrola validity
            if($form->isValid($request->getPost())){
                $newid = $this->saveForm($form);
                $this->infoMessage($texts->saved);
                $form->getElement('data_id')->setValue($newid);
            }
            $id = $newid;
            $showForm = true;
        }
        
        // Zobrazit formular?
        if($showForm){
            if(!isset($data) && is_numeric($id)){
                $data = $this->getContentData($this->serialized_class_name, $id);
            }
            elseif(!isset($data)){
                $data = null;
            }
            if(empty($form)){
                $form = $this->getContentEditForm($data);
            }
            
            // Nastavime do view pole s cisly listu
            $this->view->sheets = isset($data['obj']) && is_array($data['obj']->html) ? array_keys($data['obj']->html) : array();
            $this->view->current_sheet = $this->current_sheet;
            
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
    }
    
    
    /**
     * Vypise vsechna vyplneni daneho formulare ve formatu tabulky prenesitelnem
     * do excelu.
     */
    public function showdataAction()
    {
        $db = Zend_Registry::get('db');
        $req = $this->getRequest();
        $id = $req->getParam('id');
        
        // Zjistime existenci contentu a informace o nem, pripadne ukoncime vyhledavani,
        // pokud content neexistuje.
        try{
            $content = $this->getContentData($this->serialized_class_name, $id);
            $this->view->content_name = $content['obj']->name;
        }
        catch(Admin_Content_Not_Found_Exception $e){
            $this->view->content_not_found = $e;
            $this->view->content_name = '';
        }
        
        $q = "SELECT a.question_id, a.user_id, a.type, a.answer, a.text, u.name, u.surname, u.email
              FROM answers a JOIN users u ON a.user_id = u.id 
              WHERE a.content_id = $id ORDER BY u.surname, u.name, u.id, a.question_id";
        $data = $db->fetchAll($q);
        
        // Pridame na konec jeste jeden zaznam, ktery uz se do tabulky nevypise, ale
        // umozni zpracovani skutecneho posledniho zaznamu
        $data = $data + array('xxx' => array('user_id' => 'xxx'));
        
        $table = array();
        $table_head = array();
        $last_uid = null;
        foreach($data as $key => $val){
            if($last_uid != $val['user_id']){
                // Do ostatnich radku zaznamu na pozice dat uzivatele dame ''
                empty($record) ? $record = array() : null;
                foreach($record as $key1 => $row){
                    if($key1 == 0){
                        // Prvni radek daneho uzivatele bude obsahovat i data uzivatele
                        $record[0] = array_merge($user_data, $row);
                    }
                    else{
                        $record[$key1] = array_merge($user_data_empty, $row);
                    }
                }
                
                // Zapiseme do vystupni tabulky
                if($last_uid !== null){
                    $table = array_merge($table, $record);
                }
                
                /* Nepouzivame!!!
                // Pokud je to posledni zaznam, vyskocime
                if($key == $end_data_key){
                    break;
                }
                */
                
                $last_uid = $val['user_id'];
                $record = array();
                $user_data = array( 'uid' => $val['user_id'], 
                                    'surname'  => $val['surname'], 
                                    'name' => $val['name'], 
                                    'email' => $val['email']);
                $user_data_empty = array(   'uid' => '', 
                                            'surname'  => '', 
                                            'name' => '', 
                                            'email' => '');
            }
            
            $column = $val['question_id'];
            if(is_numeric($column)){
                $table_head['head_'.$column] = $column;
            }
            
            # Jednotlive druhy odpovedi - t, r, c
            // Text
            if($val['type'] == 't'){
                $record[0][$val['question_id']] = $val['text'];
            }
            // Radio
            elseif($val['type'] == 'r'){
                $record[0][$val['question_id']] = $val['answer'];
            }
            // Checkbox
            elseif($val['type'] == 'c'){
                // Vice odpovedi ukladame do vice radku
                $answerA = array_reverse(str_split(decbin($val['answer'])));
                $i = 0;
                $j = 1;
                foreach($answerA as $a){
                    if((int)$a){
                        // Radek musime predvyplnit vsemi predchazejicimi sloupci
                        empty($record[0]) ? $record[0] = array() : null;
                        foreach($record[0] as $key1 => $val1){
                            if(!isset($record[$i][$key1])){
                                $record[$i][$key1] = null;
                            }
                        }
                        if(empty($record[$i])){
                            $record[$i] = array();
                        }
                        $record[$i][$val['question_id']] = $j;
                        $i++;
                    }
                    $j++;
                }
            }
        }
        
        ksort($table_head);
        $this->view->answersTable = $table;
        $this->view->questionsHead = $table_head;
        
        /*
        // Pridame zahlavi k tabulce
        $table_head = array_merge($user_data_empty, $table_head);
        $table = array_merge(array('head' => $table_head), $table);
        
        
        echo '<table border="1">';
        foreach($table as $d){
            echo '<tr><td>'.join('</td><td>', $d).'</td></tr>';
        }
        echo '</table>';
        */
              
    }
    
    
    /**
     *  Vytvori a vrati formular pro editaci zvaci vlny.
     *
     *  @param int id 
     *  @param bool Pokud TRUE, nebudou se hodnoty ve formulari vyplnovat hodnotama z 
     *         $data
     *  @param bool Kdyz TRUE, tak se zobrazi ID.
     */
    function getContentEditForm($data, $empty = FALSE, $showID = TRUE)
    {
        $db = $this->db;
        $config = Zend_Registry::get('config');
        
        // Nacteme knihovny pro tvorbu formularu
        $this->loadLibs();
        $urlHlpr = new Zend_View_Helper_Url();
        
        // Pripravime selectbox existujicich autoru
        $sel = $db->select()
            ->from('authors')
            ->order('name');
        $authorsA = $db->fetchAll($sel);
        $authors_sel = array('default' => $this->texts->author_new, 'none' => $this->texts->author_none);
        foreach ($authorsA as $var) {
            $authors_sel[$var['id']] = $var['name'];
        }
        
        $form = new Form();
        $form->setAction($urlHlpr->url(array('action' => 'edit', 'sheet' => $this->current_sheet+1, 'remove_sheet' => null, 'add_sheet' => null)));
        $form->setMethod('post');
        
        
        // Id - muze byt readonly
        $id = new Zend_Form_Element_Text(array(
            'name' => 'data_id',
            'label' => $this->texts->id,
            'class' => 'span1'             
            ));
        $id->setOptions(array('readonly' => 'readonly'));
        if (!$empty){
            $id->setValue($data['id']);
        }
        
        // Zobrazit formular po ulozeni
        $show_after_save = new Zend_Form_Element_Checkbox('show_after_save');
        $show_after_save->setLabel($this->texts->show_after_save);
        if(!$empty){
            $show_after_save->setValue($data['obj']->show_form_after_save);
        }
        
        
        // Nazev
        $name = new Zend_Form_Element_Text(array(
            'name' => 'data_name',
            'label' => $this->texts->name,
            'autofocus'=>'autofocus'
            ));
        $name->setRequired(true);
        if (!$empty){
            $name->setValue($data['obj']->name);
        }
        $name->addValidator('NotEmpty', true, array(
        	'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));
        
        
        // Datum vytvoreni
        $created = new Zend_Form_Element_Text(array(
            'name' => 'data_created',
            'class' => 'datetimepicker',
            'autocomplete' => 'off',
            'label' => sprintf($this->texts->created,$this->config->general->dateformat->medium)
            ));
        $created->setRequired(true);
        if (!$empty){
            $date = new Zend_Date($data['created'], Zend_Date::ISO_8601);
            $created->setValue($date->toString($config->general->dateformat->medium));
        }
        else{
            $date = new Zend_Date();
            $created->setValue($date->toString($config->general->dateformat->medium));
        }
        $created->addValidator('NotEmpty', true, array(
        	'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));
        
        
        // Autor select
        $author_sel = new Zend_Form_Element_Select(array(
            'name' => 'data_author',
            'label' => $this->texts->author,
            'multioptions' => $authors_sel
            ));
        if (!$empty && is_numeric($data['author_id'])){
            $author_sel->setValue($data['author_id']);
        }
        else{
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
        if (!$empty && isset($data['obj']->hide_date_author)){
            $hide_date_author->setValue($data['obj']->hide_date_author);
        }
        
        $hide_pdf_link = new Zend_Form_Element_Checkbox(array(
            'name' => 'hide_pdf_link',
            'label' => $this->texts->hide_pdf_link
            ));
        if (!$empty && isset($data['obj']->hide_pdf_link)){
            $hide_pdf_link->setValue($data['obj']->hide_pdf_link);
        }

        $show_annotation = new Zend_Form_Element_Checkbox(array(
            'name' => 'show_annotation',
            'label' => $this->texts->show_annotation            
            ));
        if (!$empty && isset($data['obj']->show_annotation)){
            $show_annotation->setValue($data['obj']->show_annotation);
        }
        
        // Labels
        $labels = new Zend_Form_Element_Text(array(
            'name' => 'labels',
            'label' => $this->texts->labels,
            'class' => 'span6'
        ));

        if (!$empty && isset($data['obj']->labels)){
            $labels->setValue($data['obj']->labels);
        }
        
        // Anotace
        $annotation = new Zend_Form_Element_Textarea(array(
            'name' => 'data_annotation',
            'label' => $this->texts->annotation,
            'class' => 'editarea'
            ));
        if(!$empty){
            $annotation->setValue($data['obj']->annotation);
        }
        
        $annotation->setAttrib('rows', '10');
                
        // Html
        $html = new Zend_Form_Element_Textarea(array(
            'name' => 'data_html',
            'label' => $this->texts->html,
            'class' => 'editarea'           
            ));
        
        if(!$empty){
            $html->setValue($data['obj']->html[$this->current_sheet]);
        }
        
        
        // tlacitko ulozit
        $save = new Zend_Form_Element_Submit('data_save');
        $save->setLabel($this->texts->submit);
        $save->setAttrib('class', 'btn-primary');
        
        if($showID){
            $form->addElement($id);
        }
        
        $form->addElements(array(
            $show_after_save,
            $name,
            $created,
            $author_sel,
            $author,
            $hide_date_author,
            $hide_pdf_link,
            $show_annotation,
            $labels,
            $annotation,
            $html,
            $save
        ));  
        
        $form->addDisplayGroup(array($name,$created),
                        'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>1));
        
        $form->addDisplayGroup(array($author_sel,$author),
                        'grp2',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>2));
        
        $links = new Links();
        $html->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','rank'=>'_1','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
        $annotation->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','rank'=>'_2','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
     
        return $form;
    }
    
    
    /**
     *  Vytvori formular se seznamem kategorii, s moznosti kazdou smazat nebo 
     *  editovat jejich detaily.
     */
    public function getContentsListForm()
    {
        $db = $this->db;
        // Nacteme knihovny pro tvorbu formularu
        $this->loadLibs();
        
        $form = new Zend_Form();
        $form->setMethod('post');
        
        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('action' => 'edit')).'/');
        
        // Ziskame seznam id contentu, ktere jsou jiz v nejake strance
        // pro overovani, jestli ma byt nabidnuto generovani defaultni page pro ne
        $q = 'SELECT distinct(content_id) FROM content_pages';
        // IDcka budou i jako klice
        // HACK - mozna bude problem s vice polozkama
        $used_content_list = $db->fetchAssoc($q);
        
        
        // Ziskame data
        $data = $this->getContentData($this->serialized_class_name);
        
        // vytvori se vice sub formularu, kazdy pro jeden zaznam
        foreach ($data as $record)
        {
            $subForm = new Zend_Form_SubForm();
            $subForm->getDecorator('HtmlTag')->setOption('class','line');

            // hidden ID
            $id = new Zend_Form_Element_Hidden('data_id');
            $id->setValue($record['id']);

            // nazev
            $name = new Zend_Form_Element_Text('data_name');
            $name->setOptions(array('readonly' => 'readonly'));
            $name->setValue($record['obj']->name);  
            $name->setLabel('Title');
            
            // datum posledni zmeny
            $changed = new Zend_Form_Element_Text('data_changed');
            $changed->setOptions(array('readonly' => 'readonly'));
            $changed->setValue($record['changed']); 
            $changed->setLabel('Changed');
            
            
            // tlacitko odkaz na editaci
            $edit = new Zend_Form_Element_Submit('data_edit');
            $edit->setLabel('Edit');
            
            // tlacitko odkaz na vypis odpovedi v tomto dotazniku
            $show = new Zend_Form_Element_Submit('data_show');
            $show->setLabel('Show answers');

            $subForm->addElements(array(
                $id,
                $name,
                $changed,
                $edit,
                $show
            ));

            // Smazani
            $confirmText = 'Are you sure you want to delete this article?';
            $options = array('onClick' => 'return confirm(\''.$confirmText.'\')');
            $delete = new Zend_Form_Element_Submit('data_delete', $options);
            $delete->setLabel('Delete');
            $subForm->addElement($delete);
            
            // Vytvoreni defaultni stranky pro dany content
            if(!isset($used_content_list[$record['id']])){
                // Vumoznime vytvorit pokud tento content neni jiz v nejake strance
                $create_page = new Zend_Form_Element_Submit('data_create_page');
                $create_page->setLabel('Create simple page');
                $subForm->addElement($create_page);
            }
            

            $form->addSubForm($subForm, "sub_".$record['id']);
        }

        return $form;
    }
    
    
    /**
     *  Vytvori tlacitko pro zadani noveho Contentu
     */
    function getNewContentButton()
    {
        // Nacteme knihovny pro tvorbu formularu
        $this->loadLibs();
        
        $form = new Zend_Form();
        $form->setMethod('post');
        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('action' => 'new')).'/');

        $new = new Zend_Form_Element_Submit('new_category');
        $new->setLabel('New content');

        $form->addElement($new);
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
    public function getContentData($class_name, $id = null)
    {
        $db = $this->db;
        
        if(is_numeric($id)){
            $where = 'id = '.(int)$id;
        }
        else{
            $class_name_quot = $db->quote($class_name);
            $where = 'class_name = '.$class_name_quot;
        }
        
        $q = "SELECT * FROM content WHERE $where ORDER BY created DESC, changed DESC";
        $rows = $db->fetchAll($q);
        
        if(empty($rows)){
            if(is_numeric($id)){
                throw new Admin_Content_Not_Found_Exception("Content with id='.$id.' not found.");
            }
        }
        
        Zend_Loader::loadClass($class_name);
        
        // Projdeme zaznamy a deserializujeme objekty
        foreach($rows as $key => $row){
            $object = unserialize(stripslashes($row['serialized_object']));
            $rows[$key]['obj'] = $object;
        }
        
        // Pokud chceme jen jeden zaznam vratime jen prvni radek
        if(is_numeric($id)){
            return $rows[0];
        }
        else{
            return $rows;
        }
    }
    
    /**
     * Ulozi formular do DB, pokud je zadana promenna data, neni ulozeno pole
     * html kodu.
     *
     * @param Zend_Form Formular editace obsahu
     * @param Pole dat ziskane metodou $this->getContentData, pokud je zadano, neni meneno pole html
     * @return int ID noveho nebo editovaneho zaznamu
     */
    public function saveForm($form, $data = null)
    {
        $db = $this->db;
        $config = Zend_Registry::get('config');
        Zend_Loader::loadClass('Zend_Db_Expr');
        Zend_Loader::loadClass($this->serialized_class_name);
        
        $id = $form->getValue('data_id');
        
        // Pro porovnani puvodniho jmena contentu se jmenem page vytahneme puvodni content z DB
        $orig_content = Contents::get($id);
        $orig_obj = $orig_content['object'];
        
        
        // Pripravime a serializujeme objekt
        if(is_numeric($id) && $data === null){
            $data = $this->getContentData($this->serialized_class_name, $id);
            //$obj = $data['obj'];
            $class_name = $this->serialized_class_name;
            $obj =  new $class_name();
            $obj->id = $id;
            $obj->html = $data['obj']->html;
        }
        elseif($data!==null){
            $class_name = $this->serialized_class_name;
            $obj =  new $class_name();
            $obj->id = $id;
            $obj->html = $data['obj']->html;
            $dont_save_html = true;
        }
        else{
            $class_name = $this->serialized_class_name;
            $obj =  new $class_name();
        }
        
        $obj->annotation = $form->getValue('data_annotation');
        $obj->name = $form->getValue('data_name');
        $obj->hide_date_author = $form->getValue('hide_date_author');
        $obj->hide_pdf_link = $form->getValue('hide_pdf_link');
        $obj->show_annotation = $form->getValue('show_annotation');
        $obj->show_form_after_save = $form->getValue('show_after_save');
        $obj->labels = $form->getValue('labels');
        
        $obj->setAuthor($form->getValue('data_author'));
        // TODO - Zadavani template dle vyberu
        $obj->tpl_name = $this->tpl_name;
        if(!isset($dont_save_html)){
            $obj->html[$this->current_sheet] = $form->getValue('data_html');
        }
        
        // TODO: Ibulletin_Content_Questionnaire::translateMarks() je DEPRECATED
        // Kontrola dotazniku pokusem o preklad znacek
        $errors = array();
        foreach($obj->html as $html){
            $obj->translateMarks($html, $errors);
        }
        foreach($errors as $error){
            $this->infoMessage($error['text'], 'error');
        }
        
        
        // Pripravime autora, pripadne udelame novy zaznam do authors
        $author_sel = $form->getValue('data_author');
        $author_new = $form->getValue('data_author_new');
        $author_row = null;
        if(is_numeric($author_sel)){
            $sel = $db->select()
                ->from('authors')
                ->order('name')
                ->where('id = :id')
                ->limit(1);
            $author_row = $db->fetchAll($sel, array('id' => $author_sel));
        }
        if (!$author_row && !empty($author_new) && $author_sel == 'default') {
            // Vytvorime novy zaznam do authors
            $db->insert('authors', array('name' => $author_new));
            $author_sel = $db->lastInsertId('authors', 'id');
            // Pridame pro toto nacteni formu noveho autora do listu
            $form->getElement('data_author')->addMultiOption($author_sel, $author_new);
            $obj->setAuthor($author_new);
        }
        elseif(!$author_row){
            $author_sel = null;       
        }
        elseif($author_row){
            $obj->setAuthor($author_row[0]['name']);
        }
        $form->getElement('data_author_new')->setValue('');
        $form->getElement('data_author')->setValue($author_sel);
        
        // Datum vytvoreni clanku
        $created = new Zend_Date($form->getValue('data_created'), $config->general->dateformat->medium);
        $form->getElement('data_created')->setValue($created->toString($config->general->dateformat->medium));
        
        $insert_data = array(
            'name' => $obj->name,
            'changed' => new Zend_Db_Expr('current_timestamp'),
            'serialized_object' => addslashes(serialize($obj)),
            'class_name' => $this->serialized_class_name,
            'author_id' => $author_sel,
            'created' => $created->get(Zend_Date::ISO_8601)
            );
        
        if(!is_numeric($id)){
            $db->insert('content', $insert_data);
            $id = $db->lastInsertId('content', 'id');
        }
        
        // Nastavime jeste ID contentu do objektu a znovu ulozime
        $obj->id = $id;
        $insert_data['serialized_object'] = addslashes(serialize($obj));
        
        $db->update('content', $insert_data, sprintf('id=%d', $id));
        //Contents::edit($id, $obj);

        # Ulozime data objektu do vyhledavaciho indexu
        Zend_Loader::loadClass('Ibulletin_Search');
        $index = Ibulletin_Search::getSearchIndex();
        $search_doc = $obj->getSearchDocument();
        
        // Nejprve odstranit stare indexy pro toto content ID
        Zend_Loader::loadClass('Zend_Search_Lucene_Index_Term');
        Zend_Loader::loadClass('Zend_Search_Lucene_Search_Query_Term');
        $term  = new Zend_Search_Lucene_Index_Term($id, 'content_id');
        $query = new Zend_Search_Lucene_Search_Query_Term($term);
        $hits = $index->find($query);
        foreach ($hits as $hit) {
            $index->delete($hit->id);
        }
        
        // Zaindexujeme dokument
        $index->addDocument($search_doc);
        
        
        // Upravime pripadne zaznamy v pages a links - nastavime nove jmeno
        $sel = $db->select()
            ->from('content_pages', 'page_id')
            ->where('content_id = :id')
            ->where('position = 1')
            ->order('page_id');
        $page_id = $db->fetchOne($sel, array('id' => $id));
        if(!empty($page_id)){
            // Ziskame zaznam page pro porovnani name s name contentu
            $sel = $db->select()
                ->from('pages')
                ->where('id = :id');
            $page = $db->fetchAll($sel, array('id' => $page_id));
            $page = $page[0];
            // Menime jen pokud bylo pred zmenou jmeno shodne se jmenem v contentu
            if(trim($orig_obj->name) == trim($page['name'])){
                $db->update('pages', array('name' => $obj->name), "id = $page_id");
                
            
                // Upravime i link pouze pokud je jmeno stranky stejne s contentem, 
                // protoze link odkazuje na stranku a ne na content
                $sel = $db->select()
                    ->from('links', 'id')
                    ->where('page_id = :id')
                    ->order('id');
                $link_id = $db->fetchOne($sel, array('id' => $page_id));
                if(!empty($link_id)){
                    $db->update('links', array('name' => $obj->name), "id = $link_id");
                }
            }
        }
        
        
        return $id;
    }

    
    /**
    * Zjisti podle parametru z GET jaky je aktualni list a nastavi jej do $this->current_sheet
    */
    public function findCurrentSheet(){
        $req = $this->getRequest();
        // Pokud neni nastaven aktualni list, zkusime predane promenne, pripadne vybereme nulu
        if($this->current_sheet === null){
            if($req->__isSet('sheet') && is_numeric($req->getParam('sheet'))){
                $this->current_sheet = $req->getParam('sheet') - 1;
            }
            else{
                $this->current_sheet = 0;
            }
        }
    }
    
    
    /**
     * Nacte knihovny potrebne k praci s formulari
     */
    public function loadLibs()
    {
        Zend_Loader::loadClass('Zend_Form');
        Zend_Loader::loadClass('Zend_Form_Element_Submit');
        Zend_Loader::loadClass('Zend_Form_Element_Text');
        Zend_Loader::loadClass('Zend_Form_Element_Textarea');
        Zend_Loader::loadClass('Zend_Form_Element_Checkbox');
        Zend_Loader::loadClass('Zend_Form_Element_Hidden');
        Zend_Loader::loadClass('Zend_Form_SubForm');
        Zend_Loader::loadClass('Zend_Form_Element_Select');
        Zend_Loader::loadClass('Zend_Form_Element_File');
        
        Zend_Loader::loadClass('Zend_Validate_NotEmpty');
        Zend_Loader::loadClass('Zend_Validate_Date');
        Zend_Loader::loadClass('Zend_Validate_Digits');
        Zend_Loader::loadClass('Zend_Validate_GreaterThan');
        
        Zend_Loader::loadClass('Zend_View_Helper_Url');
    }
    
    /**
     * Vrati jmeno tohoto controlleru
     */
    public function getControllerName()
    {
        if(empty($this->_controllerName)){
            $name = get_class($this);
            $tokens = split("_", $name);
            $name = str_ireplace('controller', '', $tokens[count($tokens)-1]);
            $name = strtolower($name);
            $this->_controllerName = $name;
        }
        
        return $this->_controllerName;
    }
}
