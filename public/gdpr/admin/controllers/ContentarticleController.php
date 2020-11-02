<?php
/**
 * Modul pro spravu pridavani contentu typu article (clanek).
 *
 * TODO - Reorganizace umisteni templatu pro jednotlive druhy contentu,
 *        doreseni pojemnovavani podle umisteni (position), pridani vyberu template
 *        (mozna nebude nutne pokud budeme i nadale zadavat clanky jako html)
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

class Admin_Content_Not_Found_Exception extends Exception {}

class Admin_ContentarticleController extends Ibulletin_Admin_ContentControllerAbstract
{
  /**
   *	Jmeno tridy pro kterou je tato editace urcena
   */
    var $serialized_class_name = "Ibulletin_Content_Article";

    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    var $contentType = 'Ibulletin_Content_Article';

    /**
   *	Prozatim staticke jmeno template pro tento druh obsahu
   */
    var $tpl_name = 'article_1.phtml';

    /**
   *	Cislo aktualne editovaneho listu
   */
    var $current_sheet = null;
    
    var $pageID;


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
   * Zajistuje nacteni tlacitka pro pridani polozky a seznamu existujicich polozek.
     * V template je moznost vypsani informacnich hlasek.
   */
    /*
    public function indexAction()
  {
        $this->view->newButton = $this->getNewContentButton();
        $this->view->elementListForm = $this->getContentsListForm();
    }
    */

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
   *	Editace contentu. Zpracuje i smazani a
   *	presmerovani zpatky na seznam.
   */
  public function editAction()
  {
    $texts = Ibulletin_Texts::getSet();
    Ibulletin_Js::addJsFile('admin/collapse.js');
    
  	$db = Zend_Registry::get('db');					// db handler
    $config = Zend_Registry::get('config');			// nacte se config
    $request = $this->getRequest();
    $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

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

        // Novy zpusob ovladani
        if(empty($action) && $request->getParam('id')){
            $id = $request->getParam('id');
            $action = 'edit';
        }

        // Pro editaci bereme i id z metody get
        if(!isset($id) && $request->__isset('id')){
            $id = $request->getParam('id');
            $action = 'edit';
        }

        if(!isset($id) && empty($_POST)){
            // Pokud nebylo nic odeslano presmerujeme zpet na spravu Contentuu
            $this->redirect('index');
        }
                
        //wysiwig
        Ibulletin_Js::addJsFile('admin/wysiwig.js');
        Ibulletin_Js::addJsFile('ckeditor/ckeditor.js');
        Ibulletin_Js::addJsFile('ckeditor/adapters/jquery.js');
        //nastavi basepath pro ckeditor
        Ibulletin_Js::addPlainCode("var CKEDITOR_BASEPATH = '" . $this->view->baseUrl() . "/pub/scripts/ckeditor/';");
        Ibulletin_Js::addPlainCode("var CKEDITOR_ELFINDER_URL = '" . $this->view->url(array('controller'=>'filemanager','action'=>'elfinder-cke'))."'");
        
        //nastavi aktualni slozku contentu pro javascript
        Ibulletin_Js::addPlainCode("var EDITOR_CONTENT_BASEPATH = '" . $this->view->baseUrl() . '/' . $this->config->content_article->basepath.$id ."';");
        
        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit'); 
        $this->moduleMenu->setCurrentLocation('edit');
        
        //bootstrap_tokenfield pro labely
        Ibulletin_Js::addJsFile('bootstrap_tokenfield/bootstrap-tokenfield.min.js');
        Ibulletin_HtmlHead::addFile('../scripts/bootstrap_tokenfield/bootstrap-tokenfield.css');

        $showForm = false;

        # Vybereme akci a provedeme
        if(isset($id) && is_numeric($id)){
            if($action == 'edit'){
                $data = $this->getContentData($this->serialized_class_name, $id);
                $this->pageID = $data['page_id'];
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
            elseif($action == 'delete'){
                $db->delete('content_pages', sprintf('content_id=%d', $id));
                $db->delete('content', sprintf('id=%d', $id));

                // Spustime index action
                $this->infoMessage('Obsah byl smazán.');
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
                $this->infoMessage('Defaultní stránka pro tento obsah byla vytvořena.');
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
                $this->pageID = $data['page_id'];
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

            // Vypiseme seznam souboru
            $this->view->preview_links = $this->printLinks($id);
            
            //doplni do context menu je-li treba page id a bulletin id
            $this->view->contextMenu = $this->prepareContentContext($id);  

            // Vypiseme formular
            $this->view->form = $form;
            
            // Seznam linku ke zobrazeni
            $this->renderLinks();
        }
  }


    /**
   *	Vytvori a vrati formular pro editaci zvaci vlny.
   *
   *	@param int id
   *	@param bool Pokud TRUE, nebudou se hodnoty ve formulari vyplnovat hodnotama z
   *		   $data
   *	@param bool Kdyz TRUE, tak se zobrazi ID.
   *    @param int ID page, pro editaci atributu souvisejici s prostou strankou napr. kategorie
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

            $req = $this->getRequest();

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

       
        // Nazev
        $name = new Zend_Form_Element_Text(array(
          'name' => 'data_name',
          'label' => $this->texts->name,               
          'class' => 'span4',
          'autofocus' => 'autofocus',
          'required' => true
                ));
       
       if (!$empty){
          $name->setValue($data['obj']->name);
            }
		$name->addValidator('NotEmpty', true, array(
			'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
		));

        // Datum vytvoreni
        $created = new Zend_Form_Element_Text(array(
            'name' => 'data_created',
            'autocomplete' => 'off',
            'label' => sprintf($this->texts->created, $config->general->dateformat->medium),
            'class' => 'span2 datetimepicker'
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
            'multioptions' => $authors_sel,
            'class' => 'span4'
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

        //ma-li content prostou stranku zobrazime moznost vyberu kategorie
        if ($this->pageID) {

            //priprava seznam categorii pro select box
            $l_categories = Categories::getList(false, null, array('order ASC', 'name', 'id'));
            $catlist = array();
            foreach ($l_categories as $cat) {
                $catlist[$cat['id']] = $cat['name'];
            }

            $categories = new Zend_Form_Element_Multiselect(array(
                'name' => 'categories',
                'label' => 'Kategorie',
                'multioptions' => $catlist,
                'class' => 'span4'
            ));

            if (!$empty) {
                //ziskani seznamu categorii v kterych je zarazen content
                $s_categories = Categories::getPageCategories($this->pageID);
                $selcat = array();
                foreach ($s_categories as $cat) {
                    $selcat[] = $cat['id'];
                }

                $categories->setValue($selcat);
            }
        } else {
            $categories = null;
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


        // Anotace
        $annotation = new Zend_Form_Element_Textarea(array(
          'name' => 'data_annotation',
          'label' => $this->texts->annotation,
          'class' => 'editarea',
          'style' => 'height:200px'
                ));
        if(!$empty){
          $annotation->setValue($data['obj']->annotation);
        }  
        
        $annotation->setAttrib('rows', '10');
        $annotation->setAttrib('ace_class', 'slim');


        // Html
        $html = new Zend_Form_Element_Textarea(array(
            'name' => 'data_html',
            'label' => $this->texts->html,
            'class' => 'editarea'            
        ));
      
        if (!$empty) {
            $html->setValue($data['obj']->html[$this->current_sheet]);
        }

        // Vypinani TIDY
        $tidyOff = new Zend_Form_Element_Checkbox(array(
            'name' => 'tidy_off',
            'label' => $this->texts->tidy_off
            ));
        if (!$empty && isset($data['obj']->tidyOff)){
            $tidyOff->setValue($data['obj']->tidyOff);
        }

        // tlacitko ulozit
        $save = new Zend_Form_Element_Submit('data_save');
        $save->setLabel($this->texts->submit)
             ->setAttrib('class', 'btn-primary');

        
        if($showID){
          $form->addElement($id);
            }

        $form->addElements(array(
            $name,
            $created,
            $author_sel,
            $author,
            $categories,
            $hide_date_author,
            $hide_pdf_link,
            $show_annotation,
            $labels,
            $annotation,
            $html,
            $tidyOff,
            $save
    ));
        
      $form->addDisplayGroup(array($name,$created),
                        'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>1));

      $form->addDisplayGroup(array($author_sel,$author),
                        'grp2',array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>2));
      $links = new Links();
      $form->getElement('data_html')->setDecorators(array(array('ViewScript', array(
         'viewScript' => 'editor_form.phtml','templates'=>$this->loadTemplates(),'rank'=>'_2','links'=>$links->getSortedLinks(),'wysiwig'=>true))));

      $form->getElement('data_annotation')->setDecorators(array(array('ViewScript', array(
         'viewScript' => 'editor_form.phtml','templates'=>$this->loadTemplates(),'rank'=>'_1','links'=>$links->getSortedLinks(),'wysiwig'=>true))));

    return $form;
  }


  /**
   *	Vytvori formular se seznamem kategorii, s moznosti kazdou smazat nebo
   *	editovat jejich detaily.
   *
   *    @deprecated
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
      $name->setLabel('Název');

            // datum posledni zmeny
      $changed = new Zend_Form_Element_Text('data_changed');
      $changed->setOptions(array('readonly' => 'readonly'));
      $changed->setValue($record['changed']);
      $changed->setLabel('Změněno');


      // tlacitko odkaz na editaci
      $edit = new Zend_Form_Element_Submit('data_edit');
      $edit->setLabel('Editovat');

      $subForm->addElements(array(
        $id,
        $name,
        $changed,
                $edit
      ));

            // Smazani
            $confirmText = 'Opravdu chcete smazat Článek?';
            $options = array('onClick' => 'return confirm(\''.$confirmText.'\')');
            $delete = new Zend_Form_Element_Submit('data_delete', $options);
            $delete->setLabel('Smazat');
            $subForm->addElement($delete);

            // Vytvoreni defaultni stranky pro dany content
            if(!isset($used_content_list[$record['id']])){
                // Vumoznime vytvorit pokud tento content neni jiz v nejake strance
                $create_page = new Zend_Form_Element_Submit('data_create_page');
                $create_page->setLabel('Vytvořit prostou stránku');
                $subForm->addElement($create_page);
            }


      $form->addSubForm($subForm, "sub_".$record['id']);
    }

    return $form;
  }


    /**
   *	Vytvori tlacitko pro zadani noveho Contentu
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
    $new->setLabel('Nový obsah');

    $form->addElement($new);
    return $form;
  }

    /**
     * Ziska z DB pole vsech contentu zadaneho typu a deserializuje objekty
     * vlastniho obsahu. Nebo ziska data jedineho contentu.
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

        $q = $db->select()->from(array('c'=>'content'))
                ->joinLeft(array('cp'=>'content_pages'),'c.id = cp.content_id',array('cp.page_id'))
                ->where($where)->order(array('created DESC','changed DESC'));
        
        $rows = $db->fetchAll($q);

        if(empty($rows)){
            if(is_numeric($id)){
                throw new Admin_Content_Not_Found_Exception("Content id='.$id.' nebyl nalezen.");
            }
        }

        Zend_Loader::loadClass('Ibulletin_Content_Abstract');
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
            $obj = $orig_obj;
            //$obj = $data['obj'];
            //$class_name = $this->serialized_class_name;
            //$obj =  new $class_name();
            //$obj->id = $id;
            //$obj->setHtml($data['obj']->getHtml());
        }
        elseif($data!==null){
            $obj = $data['obj'];
            /*
            $class_name = $this->serialized_class_name;
            $obj =  new $class_name();
            $obj->id = $id;
            $obj->setHtml($data['obj']->getHtml());
            */
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

        $obj->tidyOff = $form->getValue('tidy_off');
        $obj->labels = $form->getValue('labels');

        // TODO - Zadavani template dle vyberu
        $obj->tpl_name = "article_1.phtml";
        if(!isset($dont_save_html)){
            // Ulozime HTML a vlozime jej zpet do formu kvuli zmenam, ktere provadi objekt pri ukladani
            $newHtml = $obj->setHtml($form->getValue('data_html'), $this->current_sheet);
            $form->getElement('data_html')->setValue($newHtml);
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
            ->from('content_pages')
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
        
        //ulozeni kategorii
        $categories = $form->getValue('categories');
        if ($categories) {
            //odebereme puvodni a uloziem stavajici stav
            $db->delete('pages_categories', 'page_id = ' . (int) $this->pageID);
            if (!empty($categories)) {
                if (!is_array($categories)) {
                    $categories = array($categories);
                }

                foreach ($categories as $category_id) {
                    $db->insert('pages_categories', array('page_id' => $this->pageID, 'category_id' => (int) $category_id));
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
