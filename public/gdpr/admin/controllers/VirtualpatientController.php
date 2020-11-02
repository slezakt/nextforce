<?php
/**
 * Modul pro spravu virtualnich pacientuu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


class Admin_VirtualpatientController extends Zend_Controller_Action
{
    /**
     * Vypise seznam contentuu se strucnym infem o nich a nabidne mozne akce
     */
    public function indexAction()
    {
        $db = Zend_Registry::get('db');
        
        // Ziskame seznam contentuu
        $list = Contents::getList('Ibulletin_Content_Virtualpatient');
        
        # Zadavaci formular pro pridani contentu
        $form = $this->getAddContentForm();
        
        # Zjistime, ktere stranky maji nejakou page a ktere ne
        foreach($list as $key => $content){
            $id = $content['id'];
            $sel = $db->select()
                ->from('content_pages', 'page_id')
                ->where('content_id = :id')
                ->where('position = 1')
                ->order('page_id');
            $page_id = $db->fetchOne($sel, array('id' => $id));
            if($page_id){
                $list[$key]['has_page'] = true;
            }
            else{
                $list[$key]['has_page'] = false;
            }
        }
        
        # Ulozime do view potrebne veci
        $this->view->content_list = $list;
        $this->view->form = $form;
        
    }
    
    /**
     * Prida novy content se zadanym jmenem. Jmeno se zapise do vlastnosti name objektu.
     *
     */
    public function addcontentAction()
    {
        $form = $this->getAddContentForm();
        $form->isValid($_POST);
        $values = $form->getValues();
                
        $name = $form->getValue('name');
        
        $obj = new Ibulletin_Content_Virtualpatient;
        $obj->name = $name;
        $obj->html[0] = array('', '');
        $obj->heading[0] = '';
        
        try{
            $box_id = Contents::edit(null, $obj);
        }
        catch(Exception $e){
            // Vyjimku zalogujeme a ohlasime chybu
            Phc_ErrorLog::warning('VirtualpatientController', $e);
        }
        
        if(empty($obj->id)){
            $this->infoMessage('Obsah "'.$name.'" se bohužel nepodařilo přidat.');
        }
        else{
            $this->infoMessage('Obsah "'.$name.'" byl přidán, prosím pokračujte jeho editací.');
        }
        
        $this->_forward('index');
    }
    
    /**
     * Editace contentu
     */
    public function editAction(){
        $config = Zend_Registry::get('config');
        $id = $this->getRequest()->getParam('id', null);
        $sheet = $this->getRequest()->getParam('sheet', 1);
        
        $data = $this->getContentData($id, $sheet);
        $form = $this->getEditContentForm($data);
        
        if($form->isValid($data)){
            $data = $this->saveContentData($data);
        }
        
        $form = $this->getEditContentForm($data);
        
        $this->view->sheets = isset($data['object']) && is_array($data['object']->html) ? array_keys($data['object']->html) : array();
        $this->view->current_sheet = $sheet-1;
        $this->view->sheet = $sheet;
        $this->view->form = $form;
        
        
        // Formular pro upload souboruu
        $urlHlpr = new Zend_View_Helper_Url();
        $fileUploader = new Ibulletin_FileUploader($id, $config->content_article->basepath);
        $fileUploader->setFormAction($urlHlpr->url(array('action' => 'savefiles', 'sheet' => $sheet)).'/');
        $this->view->fileForm = $fileUploader->getUploadForm();
        
        // Seznam souboru prilozenych ke contentu
        $files = array();
        if (!empty($id)){
            $path = $config->content_article->basepath."/$id/";
            if(file_exists($path)){
                $handle = opendir($path);   
                $files = Array();
                while (($file = readdir($handle)) !== false){
                    if ($file != '.' && $file != '..'){
                        array_push($files, $file);
                    }
                }
            }
        }
        $this->view->files = $files;
    }
    
    /**
     * Pridani listu
     */
    public function addsheetAction(){
        $id = $this->getRequest()->getParam('id', null);
        
        // Nacteme content
        $content = Contents::get($id);
        
        $obj = $content['object'];
        $obj->html[] = array('', '');
        
        $html_keys = array_keys($obj->html);
        $sheet_key = end($html_keys);
        $sheet = $sheet_key + 1;
        
        $obj->heading[$sheet_key] = '';
        
        Contents::edit($id, $obj);
        
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit', 'sheet' => $sheet));
    }
    
    /**
     * Odebrani listu
     */
    public function removesheetAction(){
        $id = $this->getRequest()->getParam('id', null);
        
        // Nacteme content
        $content = Contents::get($id);
        
        $obj = $content['object'];
        
        $html_keys = array_keys($obj->html);
        $sheet_key = end($html_keys);
        $sheet = $sheet_key + 1;
        
        if(count($obj->html) > 1){      // Nemazeme jediny list
            unset($obj->html[$sheet_key]);
            unset($obj->heading[$sheet_key]);
            
            Contents::edit($id, $obj);
            
            $question = Virtualpatient::getQuestion(null, $id, $sheet);
            if(!empty($question)){
                // Smazeme otazku
                Virtualpatient::deleteQuestion($question['id']);
            }
        }
        
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit', 'sheet' => $sheet_key));
    }
    
    /**
     * Pridani otazky do listu
     */
    public function addquestionAction(){
        $id = $this->getRequest()->getParam('id', null);
        $sheet = $this->getRequest()->getParam('sheet', null);
        
        Virtualpatient::editQuestion(null, $id, $sheet, '', '');
        
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit'));
    }
    
    /**
     * Odebrani otazky z listu
     */
    public function removequestionAction(){
        $id = $this->getRequest()->getParam('id', null);
        $sheet = $this->getRequest()->getParam('sheet', null);
        
        $question = Virtualpatient::getQuestion(null, $id, $sheet);
        Virtualpatient::deleteQuestion($question['id']);
        
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit'));
    }
    
    /**
     * Pridani odpovedi do listu
     */
    public function addanswerAction(){
        $id = $this->getRequest()->getParam('id', null);
        $sheet = $this->getRequest()->getParam('sheet', null);
        
        $question = Virtualpatient::getQuestion(null, $id, $sheet);
        
        if(!empty($question)){
            $answers = Virtualpatient::getQuestionAnswers($question['id']);
            if(!empty($answers)){
                // Najdeme nejvyssi cislo order, abychom mohli pridat odpoved na posledni pozici
                $last_answer = end($answers);
                $max_order = $last_answer['order'];
                $order = $max_order + 1;
            }
            else{
                $order = 1;
            }
            
            Virtualpatient::editAnswer(null, $question['id'], '', '', false, $order);
        }
        
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit'));
    }
    
    /**
     * Odebrani posledni odpovedi z listu
     */
    public function removeanswerAction(){
        $id = $this->getRequest()->getParam('id', null);
        $sheet = $this->getRequest()->getParam('sheet', null);
        
        $question = Virtualpatient::getQuestion(null, $id, $sheet);
        
        if(!empty($question)){
            $answers = Virtualpatient::getQuestionAnswers($question['id']);
    
            if(!empty($answers)){
                // Najdeme nejvyssi cislo order, abychom mohli pridat odpoved na posledni pozici
                $last_answer = end($answers);
                
                Virtualpatient::deleteAnswer($last_answer['id']);
            }
        }
            
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit'));
    }
    
    /**
     * Vytvori prostou stranku s timto contentem
     */
    public function createsimplepageAction(){
        $db = Zend_Registry::get('db');
        
        $id = $this->getRequest()->getParam('id', null);
        
        // Vytvorime zaznamy s defaultnimi hodnotami v content_pages,
        // pages a links
        $config =  Zend_Registry::get('config');
        $config_def_page = $config->admin_default_page_for_content;
        
        $cont_data = Contents::get($id);
        $name = $cont_data['object']->getName();
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
        
        // Presmerujeme na index
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => null));
    }
    
    /**
     * Ulozi soubory contentu
     */
    public function savefilesAction(){
        $config = Zend_Registry::get('config');
        $id = $this->getRequest()->getParam('id', null);
        
        // zkontrolovat platnost formulare, tj. zda vyplnen nazev souboru
        $fileUploader = new Ibulletin_FileUploader($id, $config->content_article->basepath);
        
        $fileForm = $fileUploader->getUploadForm();
        $allEmpty = TRUE;
        foreach ($_FILES['files']['name'] as $name)
            if (!empty($name)) $allEmpty = FALSE;

        if (!$allEmpty)
        {
            try
            {
                $errors = $fileUploader->uploadFiles($_FILES);
                if (!empty($errors) && is_array($errors))
                {
                    $this->infoMessage('Některé soubory se nepodařilo uploadovat.');
                    foreach($errors as $error){
                        $this->infoMessage($error);
                    }
                }
                else
                {
                    $this->infoMessage('Soubory byly úspěšně uploadovány.');
                }
            }
            catch (Ibulletin_FileUploader_Exception $e)
            {
                $this->infoMessage('Během uploadování souborů nastala chyba.');
                Phc_ErrorLog::error('VirtualpatientController, savefilesAction, Nepodarilo se ulozit soubory. Puvodni vyjimka:'."\n",
                    $e);
            }
        }
        
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->gotoRouteAndExit(array('action' => 'edit'));
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
        $form = new Zend_Form(array('class' => 'zend_form_normal'));
        $form->setMethod('post');
        
        $form->addElement('hidden', 'sheet');
        
        $form->addElement('text', 'id', array(
            'label' => 'Id: ',
            'readonly' => 'readonly'
        ));
        
        $form->addElement('text', 'name', array(
            'label' => 'Název: ',
        ));
        
        $form->addElement('text', 'heading', array(
            'label' => 'Nadpis listu VP: ',
        ));
        
        $form->addElement(new Zend_Form_Element_Textarea(
            array(
                'name' => 'html1',
                'label' => 'HTML před otázkou: ',
                'rows' => '6'
            )));

        $form->addElement(new Zend_Form_Element_Textarea(
            array(
                'name' => 'html2',
                'label' => 'HTML za otázkou: ',
                'rows' => '6'
            )));

        // Otazka
        if(isset($data['quest_id'])){
            $form->addElement('hidden', 'quest_id');
            $form->addElement(new Zend_Form_Element_Textarea(
                array(
                    'name' => 'quest_text',
                    'label' => 'Text k otázce: ',
                    'rows' => '4'
                )));
            $form->addElement(new Zend_Form_Element_Textarea(
                array(
                    'name' => 'quest_question',
                    'label' => 'Text vlastní otázky (obvykle zvýrazněn): ',
                    'rows' => '2'
                )));
            $form->addDisplayGroup(array('quest_id', 'quest_text', 'quest_question'), 'question');
        }

            
        // Odpovedi
        $form->addElement('hidden', 'number_of_answers');
        for($i=0; $i < $data['number_of_answers']; $i++){
            $form->addElement('hidden', 'answer_'.$i.'_id');
            $form->addElement('text', 'answer_'.$i.'_name', array(
            'label' => 'Název odpovědi podle configu: ',
            'readonly' => 'readonly'
        ));
            $form->addElement(new Zend_Form_Element_Textarea(
            array(
                'name' => 'answer_'.$i.'_answer',
                'label' => "Odpověď ".($i+1).": ",
                'rows' => '4'
            )));
            $form->addElement(new Zend_Form_Element_Textarea(
            array(
                'name' => 'answer_'.$i.'_explanation',
                'label' => "Vysvětlení odpověďi ".($i+1).": ",
                'rows' => '4'
            )));
            $form->addElement(new Zend_Form_Element_Checkbox(
                array(
                    'name' => 'answer_'.$i.'_is_correct',
                    'label' => "Je tato odpověď pravdivá? "
                )));
            $form->addElement('text', 'answer_'.$i.'_order', array(
                'label' => "Pořadí odpovědi ".($i+1)." v otázce: ",
            ));
            $form->addDisplayGroup(array(
                    'answer_'.$i.'_id',
                    'answer_'.$i.'_name',
                    'answer_'.$i.'_answer', 
                    'answer_'.$i.'_explanation', 
                    'answer_'.$i.'_is_correct',
                    'answer_'.$i.'_order'
                ), 'answer_'.$i);
        }
        
        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'save_content',
                'label' => 'Uložit'
            )));
            
        // Validacii vyplnime formular z pole
        $form->isValid($data);
        
        return $form;
    }
    
    /**
     * Vrati objekt formulare pro pridani noveho contentu - zadava se jen jmeno
     *
     * @return Zend_Form    Formular pro pridani contentu
     */
    public function getAddContentForm()
    {
        $urlHlpr = new Zend_View_Helper_Url();
        
        $form = new Zend_Form();
        $form->setAction($urlHlpr->url(array('action' => 'addcontent', 'name' => null)).'/');
        $form->setMethod('post');
        
        $form->addElement('text', 'name', array(
            'label' => 'Nový - název: ',
        ));
        // Pridame validaci regexem
        $form->getElement('name')->addValidator(new Zend_Validate_Regex('/^[\d\s\w]*$/i'));
        
        $form->addElement('submit', 'new_submit', array(
            'label' => 'Vytvořit'
            ));
        
        return $form;
    }
    
    /**
     * Ziska a vrati data contentu - pokud byl odeslan formular, 
     * jsou vracena data z formulare.
     *
     * @param int       ID contentu
     * @return array    Pole obsahujici data contentu
     */
    public function getContentData($id, $sheet)
    {
        $db = Zend_Registry::get('db');
        
        $sheet_key = $sheet-1; // Klic v poliich pro dany sheet
        
        // Ziskame data contentu
        $data = Contents::get($id);
        
        $data['sheet'] = $sheet;
        $data['name'] = $data['object']->name;
        
        // Data pro dany list
        $data['heading'] = $data['object']->heading[$sheet_key];
        $data['html1'] = $data['object']->html[$sheet_key][0];
        $data['html2'] = $data['object']->html[$sheet_key][1];
        
        // Najdeme data otazky na tomto liste
        $question = Virtualpatient::getQuestion(null, $id, $sheet);
        
        if(!empty($question)){
            $this->view->question_exists = true;
            
            // Data otazky
            $data['quest_id'] = $question['id'];
            $data['quest_text'] = $question['text'];
            $data['quest_question'] = $question['question'];
            
            // Natahame data jednotlivych existujicich odpovedi
            $answers = Virtualpatient::getQuestionAnswers($question['id']);
            $i = 0;
            foreach($answers as $answer){
                $data['answer_'.$i.'_id'] = $answer['id'];
                $data['answer_'.$i.'_name'] = $answer['name'];
                $data['answer_'.$i.'_answer'] = $answer['answer'];
                $data['answer_'.$i.'_explanation'] = $answer['explanation'];
                if($answer['is_correct'] && !isset($_POST['save_content'])){
                     $data['answer_'.$i.'_is_correct'] = $answer['is_correct'];
                }
                $data['answer_'.$i.'_order'] = $answer['order'];
                $i++;
            }
            $data['number_of_answers'] = $i;
        }
        else{
            $this->view->question_exists = false;
            $data['number_of_answers'] = 0;
        }
        
        // Upravime pripadne zaznamy v pages a links - nastavime nove jmeno
        $sel = $db->select()
            ->from('content_pages', 'page_id')
            ->where('content_id = :id')
            ->where('position = 1')
            ->order('page_id');
        $page_id = $db->fetchOne($sel, array('id' => $id));
        if(!empty($page_id)){
            $db->update('pages', array('name' => $data['object']->name), "id = $page_id");
            
            $sel = $db->select()
                ->from('links', 'id')
                ->where('page_id = :id')
                ->order('id');
            $link_id = $db->fetchOne($sel, array('id' => $page_id));
            if(!empty($link_id)){
                $db->update('links', array('name' => $data['object']->name), "id = $link_id");
            }
        }
        
        
        
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
     * Ulozi data prijata z editacniho formulare contentu.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveContentData($data)
    {
        if(!isset($data['save_content'])){
            // Neni co ukladat, data nebyla odeslana
            return $data;
        }
        
        $db = Zend_Registry::get('db');
        
        $ok = true;
        
        $id = $data['id'];        
        $sheet = $data['sheet'];
        $sheet_key = $sheet - 1;
        
      # Data contentu a objektu v nem
        $content = Contents::get($id);
        $content['object']->name = $data['name'];
        $content['object']->heading[$sheet_key] = $data['heading'];
        $content['object']->html[$sheet_key] = array($data['html1'], $data['html2']);
        
        
        try{
            Contents::edit($id, $content['object']);
        }
        catch(Exception $e){
            $ok = false;
            $this->infoMessage('Při změně údajů obsahu nastala chyba. Údaje obsahu pravděpodobně nebyly změněny.');
            Phc_ErrorLog::warning('VirtualpatientController', "Udaje contentu se nepodarilo zmenit. content_id='".$id
                ."' Puvodni vyjimka:\n$e");
        }
        
      # Data otazky
        if(isset($data['quest_text'])){
            try{
                Virtualpatient::editQuestion($data['quest_id'], $id, $sheet, $data['quest_text'], 
                    $data['quest_question']);
            }
            catch(Exception $e){
                $ok = false;
                $this->infoMessage('Při změně otázky nastala chyba. Otázka pravděpodobně nebyla změněna.');
                Phc_ErrorLog::warning('VirtualpatientController', "Otazku VP se nepodarilo zmenit. vp_question_id='".$data['quest_id']
                    ."' Puvodni vyjimka:\n$e");
            }
        }
        
      # Data odpovedii
        for($i=0; $i<$data['number_of_answers']; $i++){
            try{
                Virtualpatient::editAnswer(
                    $data['answer_'.$i.'_id'], 
                    $data['quest_id'], 
                    $data['answer_'.$i.'_answer'], 
                    $data['answer_'.$i.'_explanation'],
                    isset($data['answer_'.$i.'_is_correct']) ? true : false,
                    $data['answer_'.$i.'_order']
                    );
            }
            catch(Exception $e){
                $ok = false;
                $this->infoMessage('Při změně odpovědi s pořadím '.$data['answer_'.$i.'_order']
                    .' nastala chyba. Odpověď pravděpodobně nebyla změněna.');
                Phc_ErrorLog::warning(
                    'VirtualpatientController', "Odpoved se nepodarilo zmenit. vp_answer_id='".$data['answer_'.$i.'_id'].
                    "' Puvodni vyjimka:\n$e");
            }
        }
        
        
        if($ok){
            $this->infoMessage('Údaje byly změněny.');
        }
        
        return $data;
    }
    
    /**
     * Prida zpravu do pole zprav ve view.
     *
     * @param string    Zprava.
     */
    public function infoMessage($string)
    {
        $this->_info_messages[] = $string;
        $this->view->info_messages = $this->_info_messages;
    }
}
