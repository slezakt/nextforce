<?php

/**
 * Modul pro spravu prezentacii.
 *
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


class Admin_PresentationController extends Ibulletin_Admin_ContentControllerAbstract
{
    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    var $contentType = 'Ibulletin_Content_Presentation';
    
    /**
     * @var Ibulletin_Content_Abstract Objekt contentu, ktery prave editujeme, pokud jsme v akci edit.
     */
    var $content = null;
    
    /**
     * @var array    Data pro submenu modulu - zobrazi se vzdy.
     */
    var $submenuAll = array(
        'index' => array('title' => 'Seznam obsahů', 'params' => array('action' => null)),
    );
    
    /**
     * @var array    Data pro submenu modulu - zobrazeni podle modulu.
     */
    var $submenuSpecific = array();
    
    /**
     * @var string  Nazev modulu.
     */
    var $moduleTitle = 'Správa prezentací';
    

    
    /**
     * Editace contentu
     */
    public function editAction(){
        $config = Zend_Registry::get('config');
        $id = $this->getRequest()->getParam('id', null);

        $urlHlpr = new Zend_View_Helper_Url();
        $this->contentId = $id;
        
        $data = $this->getContentData($id);
        $form = $this->getEditContentForm($data);
        $this->view->form = $form;
        
        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit'); 
        $this->moduleMenu->setCurrentLocation('edit');
        
        
        // Pokud byla vyplnena data, provedeme ulozeni a aktualizujeme
        // data z ulozeni.
        if($form->isValid($data)){
            $data = $this->saveContentData($data);
        }

        
        // Prehravac prezentace
        if(file_exists($this->content->getPathToFile($this->content->file_name, false))){
            // Pripravime do stranky JS prehravace a akce s tim spojene
            $confUrl = $this->content->getPathToFile($this->content->configFileName);
            Ibulletin_Player_Js::preparePtPlayer($this->content->playerHtmlElemId, 
                                                 $this->content->getPlayerPath(),
                                                 $confUrl
                                                 );
            //$js->addJsFile('Player/videoadmin.js');
            
            $this->view->playerHtmlElemId = $this->content->playerHtmlElemId;
            $this->view->presentationFileName = $this->content->file_name;
            $this->view->presentationReady = true;
        }
        else{
            $this->view->presentationReady = false;
        }
        
        $this->view->preview_links = $this->printLinks($id);
        
        //doplni do context menu je-li treba page id a bulletin id
        $this->view->contextMenu = $this->prepareContentContext($id);  
        
        // Titulek modulu
        $this->setActionTitle('Editace prezentace "'.$data['name'].'"');
    }
    
    
    /**
     * Ziska a vrati data contentu - pokud byl odeslan formular, 
     * jsou vracena data z formulare.
     *
     * @param int       ID contentu
     * @return array    Pole obsahujici data contentu
     */
    public function getContentData($id)
    {
        $db = Zend_Registry::get('db');
        
        
        // Ziskame data contentu
        $data = Contents::get($id);
        if(!$data){
            $this->infoMessage('Content se zadaným ID nebyl nalezen.');
        }
        
        // Nastavime objekt contentu do atributu content
        $this->content = $data['object'];
        
        $data['name'] = $this->content->name;
        $data['data_annotation'] = $this->content->annotation;        
        
        // Upravime pripadne zaznamy v pages a links - nastavime nove jmeno
        // TODO Proc to delame tady?? :o/
        $this->updatePagesAndLinks($id, $data);
        
        
        
        if(isset($_POST['save_content']) && isset($_POST['id']) && $_POST['id'] == $id){
            return $_POST + $data;
        }
        elseif(!empty($this->content_data) && isset($this->content_data['id']) && $this->content_data['id'] == $id){
            return $this->content_data;
        }
        else{
            $this->content_data = $data;
            return $data;
        }
    }
    
    
    /**
     * Vrati objekt formulare pro editaci contentu.
     *
     * @return Zend_Form    Formular pro editaci contentu
     */
    public function getEditContentForm($data = null)
    {
        $db = Zend_Registry::get('db');
        
        // Vytvorime formular
        $form = new Form();
        $form->setMethod('post');
        
        $form->addElement('hidden', 'sheet');
        
        $form->addElement('text', 'id', array(
            'label' => 'Id: ',
            'readonly' => 'readonly'
        ));
        
        $form->addElement('text', 'name', array(
            'label' => 'Název: ',
             'autofocus' => 'autofocus'
        ));
        
        // Anotace
        $annotation = new Zend_Form_Element_Textarea(array(
            'name' => 'data_annotation',
            'label' => 'Anotace'
            ));
        $annotation->setAttrib('class', 'editarea');
        $form->addElement($annotation);
        
        
        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'save_content',
                'label' => 'Uložit',
                'class' => 'btn-primary'
            )));
            
        // Validacii vyplnime formular z pole
        $form->isValid($data);
        
        $links = new Links();
        $annotation->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
     
        
        return $form;
    }
    
    /**
     * Ulozi data prijata z editacniho formulare contentu.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveContentData($data)
    {
        
        $ok = true; // Flag, jestli se vse ulozilo OK
        
        $id = $data['id'];        
        
      # Data contentu a objektu v nem
        if(!$this->content){
            $content = Contents::get($id);
            $obj = $content['object'];
            $this->content = $obj;
        }
        else{
            $obj = $this->content;
        }
        $obj->name = $data['name'];
        $obj->annotation = $data['data_annotation'];
        
        
      # Nastaveni parametruu v prezentaci a ulozeni konfigurace prezentace
        if(file_exists($this->content->getPathToFile($this->content->file_name, false))){
            $this->content->config->source = $obj->getPathToFile($this->content->file_name);
            
            // Ulozit XML config
            $this->content->config->getXml($this->content->getPathToFile($this->content->configFileName, false));
            //print_r($this->content->config);
            
            // Prezentaci je mozne prehravat
            $obj->ready = true;
        }
        else{
            // Prezentaci neni mozne prehravat
            $obj->ready = false;
        }
        
        try{
            Contents::edit($id, $obj);
        }
        catch(Exception $e){
            $ok = false;
            $this->infoMessage('Při změně údajů obsahu nastala chyba. Údaje obsahu pravděpodobně nebyly změněny.');
            Phc_ErrorLog::warning('Admin_PresentationController', "Udaje contentu se nepodarilo zmenit. content_id='".$id
                ."' Puvodni vyjimka:\n$e");
        }
        
        
        // Zavolame zpracovani zavislych contentu
        $this->content->afterSave();
        
        
        if($ok && isset($data['save_content'])){
            $this->infoMessage('Údaje byly změněny.');
            unset($data['save_content']);
        }
        
        return $data;
    }
    
    /**
     * Cinnosti, ktere se maji provest pri vytvoreni noveho contentu
     * 
     * @param Ibulletin_Content_Abstract $obj    Trida contentu k inicializaci.
     */
    public function initializeContent($obj)
    {
        $config = Zend_Registry::get('config');
        
        $obj->html[0] = array();
        
    }
}