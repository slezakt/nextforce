<?php

/**
 *  Kontrolér pro administraci právní poradny. Umožňuje odpovídání na jednotlivé
 *  dotazy.
 *
 *  @author Martin Krčmář.
 */
class Admin_ConsultingController extends Ibulletin_Admin_ContentControllerAbstract
{
    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    var $contentType = 'Ibulletin_Content_Consulting';

    /**
    * overrides Ibulletin_Admin_BaseControllerAbstract::init()
    */
    public function init() {
    	parent::init();
    	$this->submenuAll = array(
			'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => false),
    		'edit' => array('title' => $this->texts->submenu_edit, 'params' => array('action' => 'edit'), 'noreset' => true),
    	    'unreplied' => array('title' => $this->texts->submenu_unreplied, 'params' => array('action' => 'unreplied'), 'noreset' => true),
    	    'replied' => array('title' => $this->texts->submenu_replied, 'params' => array('action' => 'replied'), 'noreset' => true),
    	    'settings' => array('title' => $this->texts->submenu_settings, 'params' => array('action' => 'settings'), 'noreset' => true),                	
    	);
    
    }    
    
    /**
     * Vypise seznam contentuu se strucnym infem o nich a nabidne mozne akce
     */
    public function indexAction($additionalContentTypes = array())
    {
        $this->moduleMenu->removeItem('edit');
        $this->moduleMenu->removeItem('unreplied');
        $this->moduleMenu->removeItem('replied');
        $this->moduleMenu->removeItem('settings');

        parent::indexAction();

    }
    

    public function deletedAction()
    {
        $this->moduleMenu->removeItem('edit');
        $this->moduleMenu->removeItem('unreplied');
        $this->moduleMenu->removeItem('replied');
        $this->moduleMenu->removeItem('settings');

        parent::deletedAction();

    }

    public function editAction()
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        Zend_Loader::loadClass('Ibulletin_Mailer');
        
        Ibulletin_Js::addJsFile('admin/collapse.js');

        $content_id = $this->getRequest()->getParam('id', null);
        // Redirect, pokud neni zadano ID contentu
        if($content_id === null){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('index');
        }
        $id = $content_id;

        // Data contentu
        $data = $this->getContentData($content_id);

        // zkontroluji se nastaveni jednotlivych emailu, pokud nebude treba
        // nastaven zadny email jako auto reply, tak se vypise o tom hlaska
        $select = $db->select()
            ->from('emails',
                array('consulting_room'))
            ->where('consulting_room = ?', Ibulletin_Mailer::CONSULTING_AUTO_REPLY);

        $result = $db->fetchRow($select);
        if (empty($result))
        {
            $this->infoMessage($this->texts->autoreply_notset,'warning');            
        }

        // zkontroluje se, zda je nastaven odpovedni email
        $select = $db->select()
            ->from('emails',
                array('consulting_room'))
            ->where('consulting_room = ?', Ibulletin_Mailer::CONSULTING_REPLY);

        $result = $db->fetchRow($select);
        if (empty($result))
        {
            $this->infoMessage($this->texts->reply_notset,'warning');            
        }

        $urlHlpr = new Zend_View_Helper_Url();

        // --- Ulozeni contentu
        // Pokud byla vyplnena data, provedeme ulozeni a aktualizujeme
        // data z ulozeni.
        $form = $this->getEditContentForm();
        if($form->isValid($data) && $this->getRequest()->getParam('save_content', null)){
            $data = $this->saveContentData($data);
            $form->isValid($data); // Znovu vyplnime form daty zmenenymi pri save
        }

        // Formular na editaci contentu
        $this->view->form = $form;
        $this->view->preview_links = $this->printLinks($content_id);
        
         //doplni do context menu je-li treba page id a bulletin id
        $this->view->contextMenu = $this->prepareContentContext($content_id);  
        
        // Kontrola, jestli je vybran nejaky konzultant
        if(!is_numeric($this->content->def_consultant)){
            $this->infoMessage($this->texts->consultant_notset,'warning');           
        }

        // Kontrola, jestli existuje nejaky poradce
        $sel = $db->select()
            ->from(array('c' => 'consulting_consultants'), new Zend_Db_Expr('count(*)'))
            ->join(array('n' => 'consulting_notifications'), 'c.id = n.consultant_id', array())
            ->where('n.content_id = ?', $this->content->id);
        $consultatntsCount = $db->fetchOne($sel);
       if($consultatntsCount < 1){
       		$this->infoMessage($this->texts->consultant_notfound,'warning');       	
        }

    }

    public function unrepliedAction()
    {
        $this->view->unrepliedQuestions = $this->getUnrepliedQuestionsForm();
    }

    public function repliedAction()
    {
        $this->view->repliedQuestions = $this->getRepliedQuestionsForm();
    }

    public function settingsAction()
    {
        if (!isset($this->view->settingsForm))
        {
            $this->view->settingsForm = $this->getSettingsForm();
        }

        // Ulozeni poradcu
        if($this->getRequest()->getParam('save_consultant')){
            $this->saveConsultant();
        }

        $this->view->consultantsEdit = $this->getEditConsultantForm();
        $this->view->autoRepliesNotifications = $this->getAutoRepliesNotifications();
        $this->view->repliesNotifications = $this->getRepliesNotifications();
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
        $config = Zend_Registry::get('config');


        // Ziskame data contentu
        $data = Contents::get($id);
        if(!$data){
            $this->infoMessage('Content se zadaným ID nebyl nalezen.');
        }

        $this->content = $data['object'];

        $this->content->getAvailableTemplates();

        $data['name'] = $this->content->name;
        $data['date_created'] = $data['created']->toString($config->general->dateformat->medium);
        $data['hide_date_created'] = (isset($this->content->hide_date_created) ? $this->content->hide_date_created : FALSE);
        $data['hide_pdf_link'] = (isset($this->content->hide_pdf_link) ? $this->content->hide_pdf_link : FALSE);
        $data['show_annotation'] = (isset($this->content->show_annotation) ? $this->content->show_annotation : FALSE);
        $data['extendedfields'] = join(",", $this->content->extended_fields);
        $data['requiredfields'] = join(",", $this->content->required_fields);
        $data['template'] = $this->content->tpl_name_schema;
        $data['data_annotation'] = $this->content->annotation;
        $data['data_html'] = isset($this->content->html[0]) ? $this->content->html[0] : '';
        $data['data_form'] = $this->content->form;
        $data['consultant'] = $this->content->def_consultant;
        $data['tidy_off'] =  $this->content->tidyOff;


        if(isset($_POST['save_content']) && isset($_POST['id']) && $_POST['id'] == $id){
            /*
            if (!isset($_POST['hide_date_created'])) {
                $data['hide_date_created'] = FALSE;
            }
            if (!isset($_POST['hide_pdf_link'])) {
                $data['hide_date_created'] = FALSE;
            }
            if (!isset($_POST['show_annotation'])) {
                $data['show_annotation'] = FALSE;
            }
            */
            // Naplnime form daty z postu - predevsim kvuli checkboxum
            $form = $this->getEditContentForm($data);
            $form->isValid($_POST);

            //$out = $form->getValues() + $data;
            //var_dump($out);

            return $form->getValues() + $data;
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
     */
    public function getEditContentForm()
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        // Vytvorime formular
        $form = new Form();
        $form->setMethod('post');

        $form->addElement('hidden', 'sheet');

        $form->addElement('text', 'id', array(
            'label' => $this->texts->id,
            'readonly' => 'readonly',
            'class' => 'span1'
        ));

        $form->addElement('text', 'name', array(
            'label' => $this->texts->name,
            'autofocus' => 'autofocus'
        ));

         // Datum vytvoreni
        $created = new Zend_Form_Element_Text(array(
            'name' => 'date_created',
            'class' => 'datetimepicker',
            'autocomplete' => 'off',
            'label' => sprintf($this->texts->created,$config->general->dateformat->medium)
            ));
        $created->setRequired(true);

        $created->addValidator('NotEmpty', true, array(
        	'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));
        
        $form->addElement($created);
        
        $form->addDisplayGroup(array($form->getElement('name'),$created),
                        'grp1',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

        $hide_date_created = new Zend_Form_Element_Checkbox(array(
            'name' => 'hide_date_created',
            'label' => $this->texts->hide_date_created
            ));
        $form->addElement($hide_date_created);

        $hide_pdf_link = new Zend_Form_Element_Checkbox(array(
            'name' => 'hide_pdf_link',
            'label' => $this->texts->hide_pdf_link
            ));
        $form->addElement($hide_pdf_link);

        $show_annotation = new Zend_Form_Element_Checkbox(array(
            'name' => 'show_annotation',
            'label' => $this->texts->show_annotation
            ));
        $form->addElement($show_annotation);

        $form->addElement('text', 'extendedfields', array(
            'label' => $this->texts->extendedfields,
        ));

        $form->addElement('text', 'requiredfields', array(
            'label' => $this->texts->requiredfields,
        ));

        // Select schematu template
        $templatesA = $this->content->getAvailableTemplates();
        $templateSel = new Zend_Form_Element_Select(array(
            'name' => 'template',
            'label' => $this->texts->template,
            'multioptions' => $templatesA
            ));
        $form->addElement($templateSel);


        // Select poradcu
        $sel = $db->select()
            ->from(array('c' => 'consulting_consultants'), 'name')
            ->join(array('n' => 'consulting_notifications'), 'c.id = n.consultant_id')
            ->where('n.content_id = ?', $this->content->id)
            ->order('name');
        $consultatntsA = $db->fetchAll($sel, array(), Zend_Db::FETCH_ASSOC);
        if(!empty($consultatntsA)){
            $consultatnts_sel = array();
        }
        else{
            // Zadny pouzijeme jen pokud nejsou vubec poradci, jinak dame jako default toho prvniho
            // obvzkle to bude ten co by mel vzdy byt v DB a jmenuje se 'default'
            $consultatnts_sel = array('none' => $this->texts->consultant_none);
        }
        foreach ($consultatntsA as $var) {
            $consultatnts_sel[$var['consultant_id']] = $var['name'];
        }
        $consultants = new Zend_Form_Element_Select(array(
            'name' => 'consultant',
            'label' =>  $this->texts->consultant_default,
            'multioptions' => $consultatnts_sel
            ));
        $form->addElement($consultants);

        // Anotace
        $annotation = new Zend_Form_Element_Textarea(array(
            'name' => 'data_annotation',
            'label' => $this->texts->annotation
            ));
        $annotation->setAttrib('class', 'editarea');
        $annotation->setAttrib('rows', '10');
        $form->addElement($annotation);

        // Html
        $html = new Zend_Form_Element_Textarea(array(
            'name' => 'data_html',
            'label' => $this->texts->html,
            'class' => 'editarea'            
            ));
        $form->addElement($html);

        // Html form
        $htmlForm = new Zend_Form_Element_Textarea(array(
            'name' => 'data_form',
            'label' => $this->texts->form,
            'class' => 'editarea',
        ));
        
       $form->addElement($htmlForm);

        // Tidy off
        $form->addElement('checkbox', 'tidy_off', array(
            'label' => $this->texts->tidy_off,
        ));

        // Ulozit
        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'save_content',
                'label' => $this->texts->submit,
                'class' => 'btn-primary'
            )));
               
        //parametr seq -> pro pripad vice ace editoru na strance ve formatu _#
        $links = new Links();
        $annotation->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','rank'=>'_1','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
        $html->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','rank'=>'_2','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
        $htmlForm->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','rank'=>'_3','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
        
        return $form;
    }

    /**
     * Vrati objekt formulare pro pridani poradce.
     *
     * @return Zend_Form    Formular pro pridani/upravu poradce
     */
    public function getEditConsultantForm()
    {
        // Vytvorime formular
        $form = new Form();
        $form->setMethod('post');

        // Pole do selectu konzultantu
        $consultatnts_sel = $this->getConsultatntsSelData(array('new' => $this->texts->consultant_new));
        $consultants = new Zend_Form_Element_Select(array(
            'name' => 'consultant',
            'label' => $this->texts->consultant,
            'multioptions' => $consultatnts_sel
            ));
        $form->addElement($consultants);
        $consultants->setValue('new');

        $form->addElement('text', 'consultant_name', array(
            'label' => $this->texts->consultant_name,
        ));

        // Ulozit
        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'save_consultant',
                'label' => $this->texts->submit,
                'class' => 'btn-primary'
            )));

        return $form;
    }

    /**
     *  Vrátí formulář se seznam nezodpovězených dotazů.
     */
    function getUnrepliedQuestionsForm()
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $this->loadLibs();

        $content_id = $this->getRequest()->getParam('id', null);
        // Redirect, pokud neni zadano ID contentu
        if($content_id === null){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('index');
        }

        $form = new Zend_Form();
        $form->setMethod('post');

        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('controller' => 'consulting',
            'action' => 'reply')));

        // ziska se seznam vsech nezodpovezenych dotazu
        $select = $db->select()
            ->from('consulting_questions',
                array(
                    'date' => 'to_char(created, \''.$config->general->format->totimestamp.'\')',
                    '*')
                )
            ->where('replied is null')
            ->where('content_id = ?', $content_id)
            ->order('created DESC');

        $result = $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);

        // vytvori se jednotlive subformulare s dotazy
        foreach ($result as $question)
        {
            $subForm = new Zend_Form_SubForm();
            $subForm->getDecorator('HtmlTag')
                ->setOption('class', 'line');

            // hidden ID
            $id = new Zend_Form_Element_Hidden('question_id');
            $id->setValue($question['id']);

            // text dotazu
            $text = new Zend_Form_Element_Textarea('question_text');
            $text->setOptions(array(
                'cols' => 50,
                'rows' => 2,
                'readonly' => 'readonly'
            ));
            $text->setValue($question['question']);

            // email uzivatele, ktery dotaz polozil
            $email = new Zend_Form_Element_Text('question_email');
            $email->setValue($question['email']);
            $email->setOptions(array(
                'readonly' => 'readonly',
                'size' => 35
            ));

            // poradce
            $consultants = $this->getConsultatntsSelData();
            $consultant = new Zend_Form_Element_Text('question_consultant');
            $consultant->setValue(isset($consultants[$question['consultant_id']]) ? $consultants[$question['consultant_id']] : '');
            $consultant->setOptions(array(
                'readonly' => 'readonly',
                'size' => 20,
                'label' => $this->texts->consultant
            ));

            // datum polozeni
            $date = new Zend_Form_Element_Text('question_date');
            $date->setOptions(array(
                 'size' => 15,
            ));
            $date->setValue($question['date']);

            // tlacitko pro odpovezeni na dotaz
            $reply = new Zend_Form_Element_Submit('question_reply');
            $reply->setLabel($this->texts->question_reply);

            // tlacitko pro smazani dotazu
            $delete = new Zend_Form_Element_Submit('question_delete');
            $delete->setLabel('Smazat');
            $confirmText = $this->texts->confirm_delete;
            $options = array('onclick' => 'return confirm(\''.$confirmText.'\')');
            $delete->setOptions($options);

            $subForm->addElements(array(
                $id,
                $text,
                $email,
                $consultant,
                $date,
                $reply,
                $delete
            ));

            $form->addSubForm($subForm, "sub_".$question['id']);
        }

        return $form;
    }

    function getRepliedQuestionsForm()
    {
        Zend_Loader::loadClass('Paging');

        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        $request = $this->getRequest();
        $this->loadLibs();

        $content_id = $this->getRequest()->getParam('id', null);
        // Redirect, pokud neni zadano ID contentu
        if($content_id === null){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('index');
        }

        $form = new Zend_Form();
        $form->setMethod('post');

        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('controller' => 'consulting',
            'action' => 'editquestion')));

        // strankovani
        $paging = new Paging($request, new Zend_View_Helper_Url());
        $paging->setUrlHelperOptions(
            array('controller' => 'consulting', 'action' => 'replied'));
        if (isset($config->general->paging->numpages))
            $paging->setLimit($config->general->paging->numpages);

        // strankovani
        // seznam vsech zodpovezenych dotazu, kvuli strankovani
        $select = $db->select()
            ->from('consulting_questions',
                array('num' => 'COUNT(*)'))
            ->where('replied is not null')
            ->where('content_id = ?', $content_id);
        $rows = $db->fetchRow($select);

        $paging->setNumRows($rows['num']);

        // ziska se seznam vsech zodpovezenych dotazu
        $select = $db->select()
            ->from('consulting_questions',
                array(
                    'date' => 'to_char(replied, \''.$config->general->format->totimestamp.'\')',
                    '*')
                )
            ->where('replied is not null')
            ->where('content_id = ?', $content_id)
            ->limit($paging->getLimit(), $paging->getOffset())
            ->order('created DESC');

        $result = $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);

        // zobrazeni strankovani
        $this->view->paging = $paging->generatePageLinks();

        // vytvori se jednotlive subformulare s dotazy
        foreach ($result as $question)
        {
            $subForm = new Zend_Form_SubForm();
            $subForm->getDecorator('HtmlTag')
                ->setOption('class', 'line');

            // hidden ID
            $id = new Zend_Form_Element_Hidden('question_id');
            $id->setValue($question['id']);

            // text dotazu
            $text = new Zend_Form_Element_Textarea('question_text');
            $text->setOptions(array(
                'cols' => 50,
                'rows' => 2,
                'readonly' => 'readonly'
            ));
            $text->setValue($question['question']);

            // email uzivatele, ktery dotaz polozil
            $email = new Zend_Form_Element_Text('question_email');
            $email->setValue($question['email']);
            $email->setOptions(array(
                'readonly' => 'readonly',
                'size' => 35
            ));

            // poradce
            $consultants = $this->getConsultatntsSelData();
            $consultant = new Zend_Form_Element_Text('question_consultant');
            $consultant->setValue(isset($consultants[$question['consultant_id']]) ? $consultants[$question['consultant_id']] : '');
            $consultant->setOptions(array(
                'readonly' => 'readonly',
                'size' => 20,
                'label' =>  $this->texts->consultant
            ));

            // datum polozeni
            $date = new Zend_Form_Element_Text('question_date');
            $date->setOptions(array(
                 'size' => 15,
            ));
            $date->setValue($question['date']);

            // tlacitko pro odpovezeni na dotaz
            $edit = new Zend_Form_Element_Submit('question_edit');
            $edit->setLabel( $this->texts->question_edit);

            // tlacitko pro smazani dotazu
            $delete = new Zend_Form_Element_Submit('question_delete');
            $delete->setLabel('Smazat');
            $confirmText = $this->texts->confirm_delete;
            $options = array('onclick' => 'return confirm(\''.$confirmText.'\')');
            $delete->setOptions($options);


            $subForm->addElements(array(
                $id,
                $text,
                $email,
                $consultant,
                $date,
                $edit,
                $delete
            ));

            $form->addSubForm($subForm, "sub_".$question['id']);
        }

        return $form;
    }

    /**
     *  Vytvoří formulář pro odeslání odpovědi.
     */
    function getQuestionReplyForm($id = '')
    {
        $this->loadLibs();
        $db = Zend_Registry::get('db');

        $form = new Zend_Form();
        $form->setMethod('post');

        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('controller' => 'consulting',
            'action' => 'reply')));

        if (is_numeric($id))
        {
            // ziska se seznam vsech nezodpovezenych dotazu
            $select = $db->select()
                ->from('consulting_questions')
                ->where('id = ?', $id);

            $row = $db->fetchRow($select, array(), Zend_Db::FETCH_ASSOC);
        }

        // id dotazu
        $idHidden = new Zend_Form_Element_Hidden('question_id');
        if (is_numeric($id))
            $idHidden->setValue($id);

        $userIdHidden = new Zend_Form_Element_Hidden('question_user_id');
        if (is_numeric($id))
            $userIdHidden->setValue($row['user_id']);

        // Poradce
        $consultatnts_sel = $this->getConsultatntsSelData(array('none' => '- žádný -'));
        $consultants = new Zend_Form_Element_Select(array(
            'name' => 'question_consultant',
            'label' => $this->texts->consultant,
            'multioptions' => $consultatnts_sel
            ));
        if (is_numeric($id) && $row['consultant_id'])
            $consultants->setValue($row['consultant_id']);

        // email uzivatele
        $email = new Zend_Form_Element_Text('question_email');
        $email->setOptions(array(
            'readonly' => 'readonly',
            'size' => 50
        ));
        if (is_numeric($id))
            $email->setValue($row['email']);
        $email->setLabel($this->texts->question_email);

        // checkbox pro zobrazeni (zobrazit dotaz na webu?)
        $show = new Zend_Form_Element_Checkbox('question_show');
        $show->setLabel($this->texts->question_show);
        if (is_numeric($id))
            $show->setValue($row['show']);

        // text dotazu
        $question = new Zend_Form_Element_Textarea('question_text');
        $question->setOptions(array(
            'cols' => 60,
            'rows' => 10
        ));
        if (is_numeric($id))
            $question->setValue($row['question']);
        $question->setLabel($this->texts->question_text);
        $question->addValidator('NotEmpty', true, array(
        	'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));
        $question->setRequired(true);

        // misto pro odpoved
        $reply = new Zend_Form_Element_Textarea('question_reply');
        $reply->setOptions(array(
            'cols' => 60,
            'rows' => 10
        ));
        $reply->setLabel($this->texts->question_reply);
        $reply->addValidator('NotEmpty', true, array(
        	'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));
        $reply->setRequired(true);

        // tlacitko pro odeslani
        $send = new Zend_Form_Element_Submit('question_send');
        $send->setLabel($this->texts->submit_send);

        $form->addElements(array(
            $idHidden,
            $userIdHidden,
            $consultants,
            $email,
            $show,
            $question,
            $reply,
            $send
        ));

        return $form;
    }

    /**
     *  Vytvoří formulář pro editaci dotazu.
     */
    function getQuestionEditForm($id = '')
    {
        $this->loadLibs();
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        $form = new Zend_Form();
        $form->setMethod('post');

        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('controller' => 'consulting',
            'action' => 'editquestion')));

        if (is_numeric($id))
        {
            // ziskaji se informace o dotazu
            $select = $db->select()
                ->from('consulting_questions',
                    array(
                        'date' => 'to_char(replied, \''.$config->general->format->totimestamp.'\')',
                        'send' => 'to_char(created, \''.$config->general->format->totimestamp.'\')',
                        '*')
                    )
                ->where('id = ?', $id);

            $row = $db->fetchRow($select, array(), Zend_Db::FETCH_ASSOC);
        }

        // id dotazu
        $idHidden = new Zend_Form_Element_Hidden('question_id');
        if (is_numeric($id))
            $idHidden->setValue($id);

        $userIdHidden = new Zend_Form_Element_Hidden('question_user_id');
        if (is_numeric($id))
            $userIdHidden->setValue($row['user_id']);

        // Poradce
        $consultatnts_sel = $this->getConsultatntsSelData(array('none' => $this->texts->consultant_none));
        $consultants = new Zend_Form_Element_Select(array(
            'name' => 'question_consultant',
            'label' => $this->texts->consultant,
            'multioptions' => $consultatnts_sel
            ));
        if (is_numeric($id) && $row['consultant_id'])
            $consultants->setValue($row['consultant_id']);

        // email uzivatele
        $email = new Zend_Form_Element_Text('question_email');
        $email->setOptions(array(
            'readonly' => 'readonly',
            'size' => 50
        ));
        if (is_numeric($id))
            $email->setValue($row['email']);
        $email->setLabel($this->texts->question_email);

        // datum polozeni
        $created = new Zend_Form_Element_Text('question_created');
        $created->setLabel($this->texts->question_created);
        if (is_numeric($id))
            $created->setValue($row['send']);
        $created->setOptions(array('readonly' => 'readonly'));

        // datum odeslani
        $replied = new Zend_Form_Element_Text('question_replied');
        $replied->setLabel($this->texts->question_replied);
        if (is_numeric($id))
            $replied->setValue($row['date']);
        $replied->setOptions(array('readonly' => 'readonly'));

        // text dotazu
        $question = new Zend_Form_Element_Textarea('question_text');
        $question->setOptions(array(
            'cols' => 60,
            'rows' => 10
        ));
        if (is_numeric($id))
            $question->setValue($row['question']);
        $question->setLabel($this->texts->question_text);
        $question->addValidator('NotEmpty', true, array(
        	'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));
        $question->setRequired(true);

        // misto pro odpoved
        $reply = new Zend_Form_Element_Textarea('question_reply');
        $reply->setOptions(array(
            'cols' => 60,
            'rows' => 10
        ));
        $reply->setLabel($this->texts->question_reply);
        $reply->addValidator('NotEmpty', true, array(
        	'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
        ));
        $reply->setRequired(true);
        if (is_numeric($id))
            $reply->setValue($row['reply']);

        // tlacitko pro odeslani
        $save = new Zend_Form_Element_Submit('question_save');
        $save->setLabel($this->texts->submit);

        // checkbox pro zobrazeni
        $show = new Zend_Form_Element_Checkbox('question_show');
        $show->setLabel($this->texts->question_show);
        if (is_numeric($id))
            $show->setValue($row['show']);

        $form->addElements(array(
            $idHidden,
            $userIdHidden,
            $consultants,
            $email,
            $created,
            $replied,
            $show,
            $question,
            $reply,
            $save
        ));
        

        return $form;
    }

    /**
     *  Zpracovává odpovídání na dotaz. Případně jeho mazání.
     */
    public function replyAction()
    {
        $db = Zend_Registry::get('db');                 // db handler
        $config = Zend_Registry::get('config');         // nacte se config
        $request = $this->getRequest();
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
		$texts = Ibulletin_Texts::getSet();
		
        Zend_Loader::loadClass('Zend_View_Helper_Url');

        // prichod na tuto stranku musi byt s POSTem, jinak presmerujeme zpet na
        // seznam dotazu.
        if (!$request->isPost())
        {
            $redirector->gotoAndExit('unreplied', 'consulting', 'admin');
        }

        // zjistime ID dotazu, na ktery chce uzivatel odpovedet nebo ho smazat
        foreach ($_POST as $pom)
        {
            if (is_array($pom))
            {
                if (isset($pom['question_reply']))
                {
                    $id = $pom['question_id'];
                    $action = 'reply';
                }

                // chceme smazat dotaz
                if (isset($pom['question_delete']))
                {
                    $id = $pom['question_id'];
                    $action = 'delete';
                }
            }
        }

        try
        {
            if (isset($id) && is_numeric($id))
            {
                switch ($action)
                {
                    case 'reply' :
                        $this->view->replyForm = $this->getQuestionReplyForm($id);
                        break;

                    case 'delete' :
                        $db->delete('consulting_questions', "id = $id");
                        $this->infoMessage($texts->deleted);
                        $this->unrepliedAction();
                        $this->getHelper('viewRenderer')->renderScript('consulting/unreplied.phtml');
                        return;

                    default :
                        $this->infoMessage('Chyba, neplatná akce', 'error');
                        $this->unrepliedAction();
                        $this->getHelper('viewRenderer')->renderScript('consulting/unreplied.phtml');
                        return;
                }
            }
            else if($request->__isSet('question_send'))
            {
                $id = $request->getParam('question_id');
                $form = $this->getQuestionReplyForm();
                if ($form->isValid($request->getPost()))
                {
                    if ($this->sendReply(
                        $form->getElement('question_id')->getValue(),
                        $form->getElement('question_reply')->getValue(),
                        $form->getElement('question_email')->getValue(),
                        $form->getElement('question_text')->getValue(),
                        $form->getElement('question_consultant')->getValue(),
                        $form->getElement('question_show')->getValue()
                    ))
                        $this->infoMessage($texts->sent);
                    else
                    	$this->infoMessage($texts->notsent,'error');                    

                    $this->unrepliedAction();
                    $this->getHelper('viewRenderer')->renderScript('consulting/unreplied.phtml');
                }
                $this->view->replyForm = $form;
            }
            else
            {
                $this->infoMessage('Chyba, neplatná akce', 'error');
                $this->unrepliedAction();
                $this->getHelper('viewRenderer')->renderScript('consulting/unreplied.phtml');
                return;
            }
        }
        catch (Exception $e)
        {
            Phc_ErrorLog::error('Consulting Controller, replyAction',
                $e);
            $this->infoMessage($texts->error,'error');           
            $this->unrepliedAction();
            $this->getHelper('viewRenderer')->renderScript('consulting/unreplied.phtml');
        }
    }

    /**
     *  Editace zodpovězeného dotazu.
     */
    public function editquestionAction()
    {
        $db = Zend_Registry::get('db');                 // db handler
        $config = Zend_Registry::get('config');         // nacte se config
        $request = $this->getRequest();
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $texts = Ibulletin_Texts::getSet();
        Zend_Loader::loadClass('Zend_View_Helper_Url');

        // prichod na tuto stranku musi byt s POSTem, jinak presmerujeme zpet na
        // seznam dotazu.
        if (!$request->isPost())
        {
            $redirector->gotoAndExit('replied', 'consulting', 'admin');
        }

        // zjistime ID dotazu, na ktery chce uzivatel odpovedet nebo ho smazat
        foreach ($_POST as $pom)
        {
            if (is_array($pom))
            {
                if (isset($pom['question_edit']))
                {
                    $id = $pom['question_id'];
                    $action = 'edit';
                }

                // chceme smazat dotaz
                if (isset($pom['question_delete']))
                {
                    $id = $pom['question_id'];
                    $action = 'delete';
                }
            }
        }

        try
        {
            if (isset($id) && is_numeric($id))
            {
                switch ($action)
                {
                    case 'edit' :
                        $this->view->editForm = $this->getQuestionEditForm($id);
                        break;

                    case 'delete' :
                        $db->delete('consulting_questions', "id = $id");
                        $this->infoMessage($texts->deleted);
                        $this->repliedAction();
                        $this->getHelper('viewRenderer')->renderScript('consulting/replied.phtml');
                        return;

                    default :
                		$this->infoMessage('Chyba, neplatná akce', 'error');
                        $this->repliedAction();
                        $this->getHelper('viewRenderer')->renderScript('consulting/replied.phtml');
                        return;
                }
            }
            else if($request->__isSet('question_save'))
            {
                $id = $request->getParam('question_id');
                $form = $this->getQuestionEditForm();
                if ($form->isValid($request->getPost()))
                {
                    $flag = null;
                    if ($this->saveReply(
                        $form->getElement('question_id')->getValue(),
                        $form->getElement('question_reply')->getValue(),
                        $form->getElement('question_text')->getValue(),
                        $form->getElement('question_show')->isChecked($flag),
                        $form->getElement('question_consultant')->getValue()
                    ))
                        $this->infoMessage($texts->saved);
                    else
                        $this->infoMessage($texts->notsaved,'error');

                    $this->repliedAction();
                    $this->getHelper('viewRenderer')->renderScript('consulting/replied.phtml');
                }
                $this->view->editForm = $form;
            }
            else
            {
                $this->infoMessage('Chyba, neplatná akce', 'error');
                $this->repliedAction();
                $this->getHelper('viewRenderer')->renderScript('consulting/replied.phtml');
                return;
            }
        }
        catch (Exception $e)
        {
            Phc_ErrorLog::error('Consulting Controller, replyAction',
                $e);
            $this->infoMessage($texts->error,'error');     
            $this->repliedAction();
            $this->getHelper('viewRenderer')->renderScript('consulting/replied.phtml');
        }

    }


    /**
     *  Metoad pro odeslání dotazu. Využívá třídy Mailer.
     *
     *  @param ID dotazu.
     *  @param Text odpovědi.
     *  @param Email, kam se má odpověď odeslat.
     *  @param Text dotazu.
     *  @param ID konzultanta z tabulky consultants
     *  @param Zobrazit dotaz na webu?
     */
    function sendReply ($id, $reply, $emailAddr, $question, $consultant_id, $show)
    {
        Zend_Loader::loadClass('Ibulletin_Mailer');
        Zend_Loader::loadClass('Ibulletin_Email');

        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');

        try
        {
            // vytvori se instance maileru
            $mailer = new Ibulletin_Mailer($config, $db);
            $mailer->setIgnoreRegisteredAndSendEmails(true);

            // najit email, ktery se pouziva jako odpoved v pravni
            // poradne - odpoved tazajicimu?
            $email = $mailer->getConsultingReplyEmail();
            if (empty($email))
            {
                Phc_ErrorLog::error('Consulting Consulting, sendReply',
                    'Nebyl nalezen email pro odpoved - id dotazu: "'.$id.'"');
                //return FALSE;
            }
            else{
                $replyEmail = Ibulletin_Email::createInstance($email->body);

                // nastaveni return path
                $mailer->prepareSending();

                $mail = $replyEmail->getZendMail();
                $mail->setFrom(
                    $config->mailer->from,
                    $config->mailer->fromName
                    );
            }

            // nastavit stav dotazu na zodpovezeny
            $data = array(
                'replied' => new Zend_Db_Expr('current_timestamp'),
                'reply' => $reply,
                'question' => $question,
                'show' => (bool)$show,
            );
            if(is_numeric($consultant_id)){
                $data['consultant_id'] = (int)$consultant_id;
            }
            else{
                $data['consultant_id'] = new Zend_Db_Expr('null');
            }

            $db->update('consulting_questions', $data, "id = $id");

            // ziskaji se informace o dotazu
            $select = $db->select()
                ->from('consulting_questions',
                array(
                    'date' => 'to_char(replied, \''.$config->general->format->totimestamp.'\')',
                    'asked' => 'to_char(created, \''.$config->general->format->totimestamp.'\')',
                    '*')
                )
                ->where('id = ?', $id);

            $row = $db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);

            $row->question = nl2br($row->question);
            $row->reply = nl2br($reply);
            $reply = $this->createHTMLReply($row);

            if(!empty($mail)){
                $mail->createAttachment(
                    $reply,
                    'text/html',
                    Zend_Mime::DISPOSITION_INLINE,
                    Zend_Mime::ENCODING_BASE64,
                    'odpověď.html'
                );
                if(!empty($emailAddr)){
                    $mail->addTo($emailAddr);
                    // Odeslani mailu uzivateli ktery podal dotaz
                    if (!$mail->send())
                    {
                        Phc_ErrorLog::error('Consulting Controller, sendReply',
                            'Nepodarilo se odeslat email s odpovedi na dotaz uzivateli "'.$emailAddr.'", selhala funkce Zend_Mail::send()');
                        //return FALSE;
                    }
                }
            }

            // jeste se odesle informace na zaregistrovane emaily v tabulce
            // consulting_notifications
            $email = $mailer->getConsultingEmail(Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION);
            if (!empty($email))
            {
                $email = Ibulletin_Email::createInstance($email->body);

                // Zpracovani podle consultant ID
                $consultant_id = $row->consultant_id;
                if(is_numeric($consultant_id)){
                     $consultantQ = sprintf('(consultant_id = %d OR consultant_id is null)', $consultant_id);
                }
                else{
                    $consultantQ = 'consultant_id is null';
                }

                // ziskaji se uzivatele, kterych se to tyka
                $filter = 'id IN (SELECT user_id FROM consulting_notifications WHERE role = \''.Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION.'\' '.
                ' AND '.$consultantQ.')';
                $users = $mailer->getUsers($filter);

                if(!empty($users)){
                    $zendMail = $email->getZendMail();
                    $zendMail->setFrom(
                        $config->mailer->from,
                        $config->mailer->fromName
                    );
                    // tady se k tomu muze jeste pripojit pripona
                    foreach ($users as $user)
                    {
                        $cloneMail = clone $zendMail;
                        $cloneMail->addTo($user['email']);

                        if (!$cloneMail->send())
                        {
                            Phc_ErrorLog::error('Consulting Controller, sendReply',
                                'Nepodarilo se odeslat upozorneni o odpovedi v poradne na mail "'.$user.'".');
                        }
                    }

                }
            }

            return TRUE;
        }
        catch (Exception $e)
        {
            echo $e;
            exit();
            Phc_ErrorLog::error('Consulting Controller, sendReply',
                $e);
            return FALSE;
        }
    }

    /**
     *  Formulář s nastavením jednotlivých emailů.
     *
     *  @param Zda se mají předvyplnit hodnoty. FALSE - hodnoty se předvyplní.
     */
    function getSettingsForm($empty = false)
    {
        Zend_Loader::loadClass('Ibulletin_Mailer');

        $this->loadLibs();
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        $form = new Form();
        $form->setMethod('post');

        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('controller' => 'consulting',
            'action' => 'savesettings')));

        try
        {
            $mailer = new Ibulletin_Mailer($config, $db);
            $emails = $mailer->getEmailsRenderData('');

            // nastaveni auto reply emailu
            $autoReply = new Zend_Form_Element_Select('consulting_auto_reply');
            $autoReply->setMultiOptions($emails);
            $autoReply->setLabel($this->texts->consulting_auto_reply);
            if (!$empty) {
                $row = $mailer->getConsultingEmail(Ibulletin_Mailer::CONSULTING_AUTO_REPLY);
                if ($row) {
                    $autoReply->setValue($row->id);
                    if (!isset($emails[$row->id])) {
                        $autoReply->addMultiOption($row->id, $row->name);
                    }
                }
            }

            // normalni odpoved
            $reply = new Zend_Form_Element_Select('consulting_reply');
            $reply->setMultiOptions($emails);
            $reply->setLabel($this->texts->consulting_reply);
            if (!$empty)
            {
                $row = $mailer->getConsultingEmail(Ibulletin_Mailer::CONSULTING_REPLY);
                if($row){
                    $reply->setValue($row->id);
                    if (!isset($emails[$row->id])) {
                        $reply->addMultiOption($row->id, $row->name);
                    }
                }
            }

            // upozorneni na novy dotaz
            $newNotification = new Zend_Form_Element_Select('consulting_new_notification');
            $newNotification->setMultiOptions($emails);
            $newNotification->setLabel($this->texts->consulting_new_notification);
            if (!$empty)
            {
                $row = $mailer->getConsultingEmail(Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION);
                if($row){
                     $newNotification->setValue($row->id);
                      if (!isset($emails[$row->id])) {
                        $newNotification->addMultiOption($row->id, $row->name);
                    }
                }
            }

            // upozorneni na odpoved
            $repliedNotification = new Zend_Form_Element_Select('consulting_replied_notification');
            $repliedNotification->setMultiOptions($emails);
            $repliedNotification->setLabel($this->texts->consulting_replied_notification);
            if (!$empty)
            {
                $row = $mailer->getConsultingEmail(Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION);
                if($row){
                    $repliedNotification->setValue($row->id);
                    if (!isset($emails[$row->id])) {
                        $repliedNotification->addMultiOption($row->id, $row->name);
                    }
                }
            }

        }
        catch (IBulletinMailerException $e)
        {
            Phc_ErrorLog::error('Consulting Controller, getSettingsForm',
                $e);
        }

        // tlacitko pro ulozeni
        $save = new Zend_Form_Element_Submit('consulting_save');
        $save->setLabel($this->texts->submit)
             ->setAttrib('class', 'btn-primary');

        $form->addElements(array(
            $autoReply,
            $reply,
            $newNotification,
            $repliedNotification,
            $save
        ));

        return $form;
    }

    /**
     *  Zobrazuje seznam uživatelů, kteří dostávají emaily,
     *  položení dotazu.
     */
    function getAutoRepliesNotifications()
    {
        Zend_Loader::loadClass('Ibulletin_Mailer');

        $this->loadLibs();
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $req = $this->getRequest();

        $content_id = $req->getParam('id');

        // Redirect, pokud neni zadano ID contentu
        if($content_id === null){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('index');
        }

        $form = new Form();
        $form->setMethod('post');

        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('controller' => 'consulting',
            'action' => 'savesettings')));

        try
        {
            $mailer = new Ibulletin_Mailer($config, $db);
            // vynechame uzivatele, kteri jiz jsou v tabulce consulting_notifications
            /*
            $filter = 'id NOT IN (SELECT user_id FROM
                consulting_notifications WHERE role = \''.Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION.'\')';
            */
            $filter = 'test OR client OR is_rep';
            $users = $mailer->getUsers($filter, 'email ASC');
            $usersRender = array();
            foreach ($users as $user)
            {
                $usersRender[$user['id']] = $user['email'];
            }

            $select = $db->select()
                ->from(array('cn' =>'consulting_notifications'))
                ->join(array('u' => 'users'),
                    'u.id = cn.user_id',
                    array('id', 'email'))
                ->where('role = ?', Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION)
                ->where('content_id = ?', $content_id)
                ->order('u.email ASC');

            $result = $db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            Phc_ErrorLog::error('Consulting Controller',
                $e);
        }
        catch (IBulletinMailerException $e)
        {
            Phc_ErrorLog::error('Consulting Controller',
                $e);
        }

        // Seznam consultantu
        $consultatnts_sel = $this->getConsultatntsSelData();

        foreach ($result as $row)
        {
            $subForm = new Zend_Form_SubForm();
            $subForm->setDisableLoadDefaultDecorators(true);
            $subForm->getDecorator('HtmlTag')->setOption('class','line');

            // id uzivatele
            $userId = new Zend_Form_Element_Hidden('new_notification_user_id');
            $userId->setValue($row->id);
            $userId->setOptions(array('readonly' => 'readonly'));

            // id konzultanta
            $consultantId = new Zend_Form_Element_Hidden('new_notification_consultant_id');
            $consultantId->setValue($row->consultant_id);
            $consultantId->setOptions(array('readonly' => 'readonly'));

            // Konzultant
            $consultant = new Zend_Form_Element_Text('reply_email');
            if(isset($consultatnts_sel[$row->consultant_id])){
                $consultant_name = $consultatnts_sel[$row->consultant_id];
            }
            else{
                $consultant_name = 'všichni';
            }
            $consultant->setValue($consultant_name);
            $consultant->setOptions(array(
                'readonly' => 'readonly',
                'size' => 40
            ));
            $consultant->setLabel($this->texts->consultant);

            // email uzivatele
            $email = new Zend_Form_Element_Text('new_notification_email');
            $email->setValue($row->email);
            $email->setOptions(array(
                'readonly' => 'readonly',
                'size' => 40
            ));
            $email->setLabel($this->texts->email);

            // tlacitko smazat
            $confirmText = $this->texts->confirm_email;
            $delete = new Zend_Form_Element_Submit('new_notification_delete');
            $delete->setLabel($this->texts->submit_delete);
            $delete->setAttrib('class','btn btn-primary');
            $delete->setOptions(array('onclick' => 'return confirm(\''.$confirmText.'\')'));

            $subForm->addElements(array(
                $userId,
                $consultantId,
                $consultant,
                $email,
                $delete
            ));

            $form->addSubForm($subForm, "sub_".$row->id."_".$row->consultant_id);
        }

        // Select poradcu
        $consultants = new Zend_Form_Element_Select(array(
            'name' => 'consultant',
            'label' => $this->texts->consultant,
            'multioptions' => $consultatnts_sel
            ));
        $form->addElement($consultants);
        $consultants->setValue('all');

        // select pro vlozeni noveho emailu
        $new = new Zend_Form_Element_Select('new_notification_new');
        $new->setMultiOptions($usersRender);
        $new->setLabel($this->texts->add_email);

        // tlacitko pro pridani
        $add = new Zend_Form_Element_Submit('new_notification_add');
        $add->setLabel($this->texts->submit_add);
        $add->setAttrib('class', 'btn-primary');

        $form->addElements(array(
            $new,
            $add
        ));

        return $form;
    }

    /**
     *  Zobrazuje seznam uživatelů, kteří dostávají emaily,
     *  odpovězení na dotaz.
     */
    function getRepliesNotifications()
    {
        Zend_Loader::loadClass('Ibulletin_Mailer');

        $this->loadLibs();
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $req = $this->getRequest();

        $content_id = $req->getParam('id');

        // Redirect, pokud neni zadano ID contentu
        if($content_id === null){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('index');
        }

        $form = new Form();
        $form->setMethod('post');

        $url = new Zend_View_Helper_Url();
        $form->setAction($url->url(array('controller' => 'consulting',
            'action' => 'savesettings')));

        try
        {
            $mailer = new Ibulletin_Mailer($config, $db);
            // vynechame uzivatele, kteri jiz jsou v tabulce consulting_notifications
            /*
            $filter = 'id NOT IN (SELECT user_id FROM
                consulting_notifications WHERE role = \''.Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION.'\')';
            */
            $filter = 'test OR client OR is_rep';
            $users = $mailer->getUsers($filter, 'email ASC');
            $usersRender = array();
            foreach ($users as $user)
            {
                $usersRender[$user['id']] = $user['email'];
            }

            $select = $db->select()
                ->from(array('cn' =>'consulting_notifications'))
                ->join(array('u' => 'users'),
                    'u.id = cn.user_id',
                    array('id', 'email'))
                ->where('role = ?', Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION)
                ->where('content_id = ?', $content_id)
                ->order('u.email ASC');

            $result = $db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            Phc_ErrorLog::error('Consulting Controller',
                $e);
        }
        catch (IBulletinMailerException $e)
        {
            Phc_ErrorLog::error('Consulting Controller',
                $e);
        }

        // Seznam consultantu
        $consultatnts_sel = $this->getConsultatntsSelData();

        foreach ($result as $row)
        {
            $subForm = new Zend_Form_SubForm();
            $subForm->setDisableLoadDefaultDecorators(true);
            $subForm->getDecorator('HtmlTag')->setOption('class','line');

            // id uzivatele
            $userId = new Zend_Form_Element_Hidden('reply_user_id');
            $userId->setValue($row->id);
            $userId->setOptions(array('readonly' => 'readonly'));

            // id konzultanta
            $consultantId = new Zend_Form_Element_Hidden('reply_consultant_id');
            $consultantId->setValue($row->consultant_id);
            $consultantId->setOptions(array('readonly' => 'readonly'));

            // Konzultant
            $consultant = new Zend_Form_Element_Text('reply_consultant');
            if(isset($consultatnts_sel[$row->consultant_id])){
                $consultant_name = $consultatnts_sel[$row->consultant_id];
            }
            else{
                $consultant_name = $this->texts->consultants_all;
            }
            $consultant->setValue($consultant_name);
            $consultant->setOptions(array(
                'readonly' => 'readonly',
                'size' => 40
            ));
            $consultant->setLabel($this->texts->consultant);

            // email uzivatele
            $email = new Zend_Form_Element_Text('reply_email');
            $email->setValue($row->email);
            $email->setOptions(array(
                'readonly' => 'readonly',
                'size' => 40
            ));
            $email->setLabel($this->texts->email);

            // tlacitko smazat
            $confirmText = $this->texts->confirm_email;
            $delete = new Zend_Form_Element_Submit('reply_delete');
            $delete->setLabel($this->texts->submit_delete);
            $delete->setAttrib('class','btn btn-primary');
            $delete->setOptions(array('onclick' => 'return confirm(\''.$confirmText.'\')'));

            $subForm->addElements(array(
                $userId,
                $consultantId,
                $consultant,
                $email,
                $delete
            ));

            $form->addSubForm($subForm, "sub_".$row->id."_".$row->consultant_id);
        }

        // Select poradcu
        $consultants = new Zend_Form_Element_Select(array(
            'name' => 'consultant',
            'label' => $this->texts->consultant,
            'multioptions' => $consultatnts_sel
            ));
        $form->addElement($consultants);
        $consultants->setValue('all');

        // select pro vlozeni noveho emailu
        $new = new Zend_Form_Element_Select('reply_new');
        $new->setMultiOptions($usersRender);
        $new->setLabel($this->texts->add_email);

        // tlacitko pro pridani
        $add = new Zend_Form_Element_Submit('reply_add');
        $add->setLabel($this->texts->submit_add);
        $add->setAttrib('class','btn-primary');

        $form->addElements(array(
            $new,
            $add
        ));

        return $form;
    }

    /**
     * Vrati pole dat pro select poradcu
     */
    public function getConsultatntsSelData($defaults = array())
    {
        $db = Zend_Registry::get('db');

        // Pripravime selectbox existujicich konzultantu
        $sel = $db->select()
            ->from('consulting_consultants')
            ->order('name');
        $consultatntsA = $db->fetchAll($sel, array(), Zend_Db::FETCH_ASSOC);
        $consultatnts_sel = $defaults;
        foreach ($consultatntsA as $var) {
            $consultatnts_sel[$var['id']] = $var['name'];
        }

        return $consultatnts_sel;
    }


    /**
     * Ulozi data prijata z editacniho formulare contentu.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveContentData($data)
    {
        /*
        if(!isset($data['save_content'])){
            // Neni co ukladat, data nebyla odeslana
            return $data;
        }
        //*/

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

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

        // seznamy dalsich policek k ukladani
        // Musime je nejprve rozbit z retezcu a otrimovat
        $extendedfields = explode(',', $data['extendedfields']);
        foreach($extendedfields as $key=>$field){
            $extendedfields[$key] = trim($field);
        }
        $requiredfields = explode(',', $data['requiredfields']);
        foreach($requiredfields as $key=>$field){
            $requiredfields[$key] = trim($field);
        }

        $obj->extended_fields = $extendedfields;
        $obj->required_fields = $requiredfields;

        $obj->hide_date_created = (boolean)$data['hide_date_created'];
        $obj->hide_pdf_link = (boolean)$data['hide_pdf_link'];
        $obj->show_annotation = (boolean)$data['show_annotation'];

        $obj->tidyOff = (boolean)$data['tidy_off'];

        $obj->id = $id;
        $obj->name = $data['name'];
        $obj->tpl_name_schema = $data['template'];
        $obj->annotation = $data['data_annotation'];
        $data['data_html'] = $obj->setHtml($data['data_html'], 0);
        if($obj->tidyOff){ // Rozhodujeme, jestli se ma provadet tidy
            $data['data_form'] = $data['data_form']; // I HTML formu prohanime skrz TIDY
        }
        else{
            $data['data_form'] = $obj->tidyHtml($data['data_form']); // I HTML formu prohanime skrz TIDY
        }
        $obj->form = $data['data_form'];
        $obj->def_consultant = is_numeric($data['consultant']) ? $data['consultant'] : null;


        try{
            Contents::edit($id, $obj);
            $date_created = new Zend_Date($data['date_created'], $config->general->dateformat->medium);
            $db->update('content', array('created' => $date_created->get(Zend_Date::ISO_8601)), sprintf('id=%d', $id));
        }
        catch(Exception $e){
            $ok = false;
            $this->infoMessage($this->texts->content_notsaved,'warning');
            Phc_ErrorLog::warning('Admin_ConsultingController', "Udaje contentu se nepodarilo zmenit. content_id='".$id
                ."' Puvodni vyjimka:\n$e");
        }

        // Upravime pripadne zaznamy v pages a links - nastavime nove jmeno
        $this->updatePagesAndLinks($id, $data);

        // Zavolame zpracovani zavislych contentu
        $this->content->afterSave();

        if($ok && isset($data['save_content'])){
            $this->infoMessage($this->texts->content_saved);
            unset($data['save_content']);
        }

        return $data;
    }

    /**
     * Ulozi noveho nebo upravi poradce
     */
    public function saveConsultant(){
        $db = Zend_Registry::get('db');
        $req = $this->getRequest();

        $id = $req->getParam('consultant');
        $name = $req->getParam('consultant_name');

        $data = array('name' => $name);
        if($id == 'new' && !empty($name)){
            $db->insert('consulting_consultants', $data);
        }
        elseif(!empty($name)){
            $db->update('consulting_consultants', $data, sprintf("id = %d", $id));
        }
    }


    /**
     *  Zpracovává akce při nastavování právní poradny.
     */
    public function savesettingsAction()
    {
        $db = Zend_Registry::get('db');                 // db handler
        $config = Zend_Registry::get('config');         // nacte se config
        $request = $this->getRequest();
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $content_id = $request->getParam('id');
		$texts = Ibulletin_Texts::getSet();
		
        // Redirect, pokud neni zadano ID contentu
        if($content_id === null){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('index');
        }

        Zend_Loader::loadClass('Zend_View_Helper_Url');
        Zend_Loader::loadClass('Ibulletin_Mailer');

        // prichod na tuto stranku musi byt s POSTem, jinak presmerujeme zpet na
        // seznam dotazu.
        if (!$request->isPost())
        {
            $redirector->gotoAndExit('settings', 'consulting', 'admin');
        }

        // zjistime, co chce uzivatel udelat
        foreach ($_POST as $pom)
        {
            if (is_array($pom))
            {
                if (isset($pom['new_notification_delete']))
                {
                    $id = $pom['new_notification_user_id'];
                    $consultant = $pom['new_notification_consultant_id'];
                    $action = 'delete_new_notification';
                }

                if (isset($pom['reply_delete']))
                {
                    $id = $pom['reply_user_id'];
                    $consultant = $pom['reply_consultant_id'];
                    $action = 'delete_reply';
                }
            }
        }

        try
        {
            if (isset($id) && is_numeric($id))
            {
                switch ($action)
                {
                    case 'delete_new_notification' :
                        if(is_numeric($consultant)){
                            $consultantQ = 'consultant_id = '.(int)$consultant;
                        }
                        else{
                            $consultantQ = 'consultant_id is null';
                        }
                        $db->delete(
                            'consulting_notifications',
                            "user_id = $id AND role = '".Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION.'\''.
                            " AND ".$consultantQ
                        );
                        $this->infoMessage($texts->deleted);
                        $this->settingsAction();
                        $this->getHelper('viewRenderer')->renderScript('consulting/settings.phtml');
                        return;

                    case 'delete_reply' :
                        if(is_numeric($consultant)){
                            $consultantQ = 'consultant_id = '.(int)$consultant;
                        }
                        else{
                            $consultantQ = 'consultant_id is null';
                        }
                        $db->delete(
                            'consulting_notifications',
                            "user_id = $id AND role = '".Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION.'\''.
                            " AND ".$consultantQ
                        );
                        $this->infoMessage($texts->deleted);
                        $this->settingsAction();
                        $this->getHelper('viewRenderer')->renderScript('consulting/settings.phtml');
                        return;

                    default :
                        $this->infoMessage('Chyba, neplatná akce','error');
                        $this->settingsAction();
                        $this->getHelper('viewRenderer')->renderScript('consulting/settings.phtml');
                        return;
                }
            }
            else if($request->__isSet('consulting_save'))
            {
                $form = $this->getSettingsForm(true);

                if ($form->isValid($request->getPost()))
                {
                    if ($this->saveSettings(
                        $form->getElement('consulting_reply')->getValue(),
                        $form->getElement('consulting_auto_reply')->getValue(),
                        $form->getElement('consulting_new_notification')->getValue(),
                        $form->getElement('consulting_replied_notification')->getValue()
                    ))
                        $this->infoMessage($texts->saved);
                    else
                        $this->infoMessage($texts->notsaved,'error');
                }
                $this->view->settingsForm = $form;
                $this->settingsAction();
                $this->getHelper('viewRenderer')->renderScript('consulting/settings.phtml');
            }
            else if ($request->__isSet('new_notification_add'))
            {
                $select = new Zend_Db_Select($db);
                $select->from('consulting_notifications')
                       ->where('content_id=?', $content_id)
                       ->where('user_id=?', $request->getParam('new_notification_new'))
                       ->where('role=?',Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION);

                $data = array(
                    'content_id' => $content_id,
                    'user_id' => $request->getParam('new_notification_new'),
                    'role' => Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION
                );
                $consultant = $request->getParam('consultant', 'all');
                if($consultant != 'all'){
                    $data['consultant_id'] = $consultant;
                    $select->where('consultant_id=?', $consultant);
                }
                else{
                    $select->where('consultant_id is null');
                }

                // Pokud takovy zaznam neexistuje, ulozime
                $row = $db->fetchAll($select, array(),  Zend_Db::FETCH_ASSOC);
                if(empty($row)){
                    $db->insert('consulting_notifications', $data);
                    $this->infoMessage($texts->added);
                }

                $this->settingsAction();
                $this->getHelper('viewRenderer')->renderScript('consulting/settings.phtml');
                return;
            }
            else if ($request->__isSet('reply_add'))
            {
                $select = new Zend_Db_Select($db);
                $select->from('consulting_notifications')
                       ->where('content_id=?', $content_id)
                       ->where('user_id=?', $request->getParam('reply_new'))
                       ->where('role=?',Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION);

                $data = array(
                    'content_id' => $content_id,
                    'user_id' => $request->getParam('reply_new'),
                    'role' => Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION
                );

                $consultant = $request->getParam('consultant', 'all');
                if($consultant != 'all'){
                    $data['consultant_id'] = $consultant;
                    $select->where('consultant_id=?', $consultant);
                }
                else{
                    $select->where('consultant_id is null');
                }

                // Pokud takovy zaznam neexistuje, ulozime
                $row = $db->fetchAll($select, array(),  Zend_Db::FETCH_ASSOC);
                if(empty($row)){
                    $db->insert('consulting_notifications', $data);
                    $this->infoMessage($texts->saved);
                }

                $this->settingsAction();
                $this->getHelper('viewRenderer')->renderScript('consulting/settings.phtml');
                return;
            }
            else
            {
                $this->infoMessage('Chyba, neplatná akce','error');
                $this->settingsAction();
                $this->getHelper('viewRenderer')->renderScript('consulting/settings.phtml');
                return;
            }
        }
        catch (Exception $e)
        {
            Phc_ErrorLog::error('Consulting Controller, savesettingsAction',
                $e);
           	$this->infoMessage($texts->error,'error');
            $this->settingsAction();
            $this->getHelper('viewRenderer')->renderScript('consulting/settings.phtml');
        }
    }

    /**
     *  Uloží nastavení mailů do právní poradny.
     *
     *  @param ID mailu pro odpoved z pravni poradny.
     *  @param ID mailu, ktery se posila jako automaticka odpoved.
     *  @param ID mailu, ktery se posila jako upozornění na nový dotaz.
     *  @param ID mailu, ktery se posila jako upozornění zodpovězený dotaz.
     */
    function saveSettings($replyId, $autoReplyId, $newNotification, $replyNotification)
    {
        Zend_Loader::loadClass('Ibulletin_Mailer');
        Zend_Loader::loadClass('Zend_Db_Expr');

        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');

        try
        {
            // nejdrive se zrusi puvodni nastaveni emailu pro poradnu
            $default = array(
                'consulting_room' => Ibulletin_Mailer::CONSULTING_DEFAULT
            );
            $db->update('emails', $default);

            // nastaveni noveho auto replay mailu
            $data = array(
                'consulting_room' => Ibulletin_Mailer::CONSULTING_AUTO_REPLY
            );
            $db->update('emails', $data, "id = $autoReplyId");

            // nastaveni noveho odpovedniho emailu
            $data = array(
                'consulting_room' => Ibulletin_Mailer::CONSULTING_REPLY
            );
            $db->update('emails', $data, "id = $replyId");

            // nastaveni noveho mailu, jako upozorneni na novy dotaz
            $data = array(
                'consulting_room' => Ibulletin_Mailer::CONSULTING_NEW_NOTIFICATION
            );
            $db->update('emails', $data, "id = $newNotification");

            // nastaveni noveho mailu, jako upozorneni na zodpovezeny dotaz
            $data = array(
                'consulting_room' => Ibulletin_Mailer::CONSULTING_REPLY_NOTIFICATION
            );
            $db->update('emails', $data, "id = $replyNotification");

            return TRUE;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            Phc_ErrorLog::error('Consulting Controller, saveSettings',
                $e);
            return FALSE;
        }
    }

    /**
     *  Uloží změněný dotaz do DB.
     *
     *  @param Identifikátor dotazu.
     *  @param Text odpovědi na dotaz.
     *  @param Text dotazu.
     *  @param Zda se má dotaz zobrazit.
     */
    function saveReply($id, $reply, $question, $show, $consultant_id)
    {
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');

        try
        {
            // nastavit stav dotazu na zodpovezeny
            $data = array(
                'reply' => $reply,
                'question' => $question,
                'show' => $show ? 'true' : 'false'
            );
            if(is_numeric($consultant_id)){
                $data['consultant_id'] = (int)$consultant_id;
            }
            else{
                $data['consultant_id'] = new Zend_Db_Expr('null');
            }

            $db->update('consulting_questions', $data, "id = $id");
            return TRUE;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            Phc_ErrorLog::error('Consulting Controller, saveReply',
                'Nepodařilo se uložit změněný dotaz s ID: '.$id.'
                ,'.$e);
            return FALSE;
        }
    }

    /**
     *  Vytvoří HTML odpověď na dotaz.
     *
     *  @param Pole s ospovědí.
     */
    function createHTMLReply($reply)
    {
        $retVal = ' <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
                    <html xmlns="http://www.w3.org/1999/xhtml" lang="cs" xml:lang="cs">
                    <head>
                      <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                      <meta content="cs" http-equiv="language" />
                      <title>Odpověď na dotaz z právní poradny</title>
                      <link rel="stylesheet" href="pub/css/css.css" media="screen" type="text/css" />
                    </head>
                    <body>
                        <p>Datum položení dotazu: '.$reply->asked.'</p>
                        <p>Datum zodpovězení dotazu: '.$reply->date.'</p>
                        <h3>Váš dotaz:</h3>
                        <p>'.$reply->question.'</p>
                        <h3>Odpověď:</h3>
                        <p>'.$reply->reply.'</p>
                    </body>
                    </html>';

        return $retVal;
    }

    /**
     * Nacte knihovny potrebne k praci s formulari
     */
    function loadLibs()
    {
        Zend_Loader::loadClass('Zend_Form');
        Zend_Loader::loadClass('Zend_Form_Element_Submit');
        Zend_Loader::loadClass('Zend_Form_Element_Text');
        Zend_Loader::loadClass('Zend_Form_Element_Textarea');
        Zend_Loader::loadClass('Zend_Form_Element_Checkbox');
        Zend_Loader::loadClass('Zend_Form_Element_Hidden');
        Zend_Loader::loadClass('Zend_Form_SubForm');
        Zend_Loader::loadClass('Zend_Form_Element_Select');

        Zend_Loader::loadClass('Zend_Validate_NotEmpty');
        Zend_Loader::loadClass('Zend_Validate_Date');
        Zend_Loader::loadClass('Zend_Validate_Digits');
        Zend_Loader::loadClass('Zend_Validate_GreaterThan');

        Zend_Loader::loadClass('Zend_View_Helper_Url');
    }
}