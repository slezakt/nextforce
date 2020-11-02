<?php
/**
 * Modul pro spravu nazoruu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


class Admin_OpinionsController extends Ibulletin_Admin_ContentControllerAbstract
{
    /**
     *  Jmeno tridy pro kterou je tato editace urcena
     */
    var $serialized_class_name = "Ibulletin_Content_Opinions";

    /**
     * @var string Typ contentu, ktery tento modul spravuje.
     */
    var $contentType = 'Ibulletin_Content_Opinions';

    /**
     *  Prozatim staticke jmeno template pro tento druh obsahu
     */
    var $tpl_name = 'opinions_1.phtml';

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
     * Vypise seznam prispevkuu
     */
    public function manageAction() {
        $id = $this->_request->getParam('id');
        if (!$id) {
            $this->redirect('index');
        }
        $this->moduleMenu->addItem($this->texts->manage->heading, array('action' => 'manage', 'id' => $id), null, true, 'manage');
        $this->moduleMenu->setCurrentLocation('manage');

        try {
            $grid = new Ibulletin_DataGrid(Opinions::getOpinionsQuery($id));

            $grid->setEmptyText($this->texts->manage->empty);
            $grid->setDefaultSort('timestamp');

            $grid->addColumn('name', array(
                        'header' => $this->texts->firstname,
                        'field' => 'name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            "autocomplete" => true
                        )
                    ))
                    ->addColumn('employment', array(
                        'header' => $this->texts->employment,
                        'field' => 'employment',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            "autocomplete" => true
                        )
                    ))
                    ->addColumn('timestamp', array(
                        'header' => $this->texts->created,
                        'field' => 'timestamp',
                        'type' => 'datetime',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'datetime'
                        )
                    ))
                    ->addColumn('text', array(
                        'header' => $this->texts->textarea,
                        'field' => 'text',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            "autocomplete" => true
                        )
                    ))
                    ->addAction('delete', array(
                        'confirm' => $this->texts->manage->confirm_delete,
                        'url' => $this->_helper->url('deleteopinion') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('editopinion') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
                    ));

            $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }


        // Ziskame seznam boxuu
        $list = Opinions::getList($id);

        # Ulozime do view potrebne veci
        $this->view->list = $list;

    }

    /**
     * Editace zaznamu z opinions
     */
    public function editopinionAction(){
        $id = $this->getRequest()->getParam('id');
        if (!$id) {
            $this->redirect('index');
        }
        $data = $this->getOpinionData($id);

        $this->moduleMenu->addItem($this->texts->manage->heading, array('action'=>'manage','id'=>$data['content_id']), null, true,'manage');
        $this->moduleMenu->addItem($this->texts->editopinion->heading, array('action'=>'editopinion','id'=>$id), null, true,'editopinion');
        $this->moduleMenu->setCurrentLocation('editopinion');


        $form = $this->getEditOpinionForm($data);

        if($form->isValid($data)){
            $data = $this->saveOpinionData($data);
        }

        $form = $this->getEditOpinionForm($data);

        $this->view->form = $form;

        $this->view->user_id = $data['user_id'];
        $this->view->content_id = $data['content_id'];
    }

    /**
     * Editace contentu
     */
    public function editAction(){

        $id = $this->getRequest()->getParam('id', null);

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        $data = $this->getContentData($id);
        $form = $this->getEditContentForm($data);

        if($form->isValid($data)){
            $data = $this->saveContentData($data, $form);
        }

        $form = $this->getEditContentForm($data);

        // Nachystame seznam uzivatelu, kterym se posila mail o pridani prispevku
        $notifiedUsers = array();
        foreach($data['object']->notificateUsers as $uid){
            try{
                $user = Users::getUser($uid);
                $notifiedUsers[$user['id']] = trim($user['name'].' '.$user['surname']).' - '.$user['email'];
            }
            catch(Users_User_Not_Found_Exception $e){
                // Uzivatel nenalezen, proste ho nepridame
            }
        }

        $this->view->notifiedUsers = $notifiedUsers;
        $this->view->form = $form;

         //odkaz pro nahled
        $this->view->preview_links = $this->printLinks($id);

        //doplni do context menu je-li treba page id a bulletin id
        $this->view->contextMenu = $this->prepareContentContext($id);

        //$this->view->content_id = $data['content_id'];
    }

    /**
     * Smazani zaznamu z opinions
     */
    public function deleteopinionAction(){
        $texts = Ibulletin_Texts::getSet();
        $id = $this->_request->getParam('id');
        if (!$id) {
            $this->redirect('index');
        }
        // Najdeme data prispevku pro pouziti pri navigaci
        try{
            $opinion = Opinions::get($id);
        }
        catch(Exception $e){
            $this->infoMessage($texts->notfound,'error',array($id));
            Phc_ErrorLog::warning('OpinionsController', new Exception("Nepodarilo se smazat nazor z tabulky opinions s id='$id'. Puvodni vyjimka:\n$e"));
        }

        // Smazani
        $ok = Opinions::delete($id);


        if(!$ok){
            $this->infoMessage($texts->notdeleted,'error');
            Phc_ErrorLog::warning('OpinionsController', new Exception("Nepodarilo se smazat nazor z tabulky opinions s id='$id'."));
        }
        else{
            $this->infoMessage($texts->deleted, 'success',array($opinion['title'],$opinion['name'], $opinion['surname']));
        }

        // Nastavime ID na ID contentu a presmerujeme na seznam prispevku v contentu
        $this->redirect(array('action' => 'manage', 'id' => $opinion['content_id']));
    }

    /**
     * Vrati objekt formulare pro editaci nazoru
     *
     * @return Zend_Form    Formular pro editaci nazoru
     */
    public function getEditOpinionForm($data = null)
    {
        $form = new Form();
        $form->setMethod('post');

        $form->addElement('text', 'id', array(
            'label' => $this->texts->id,
            'readonly' => 'readonly',
            'class' => 'span1'
        ));

        $form->addElement('text', 'user_id', array(
            'label' => $this->texts->user_id,
            'readonly' => 'readonly',
            'class' => 'span1'
        ));

        $form->addElement('text', 'timestamp', array(
            'label' => $this->texts->created,
            'class' => 'span2'
        ));

        $form->addElement('text', 'name', array(
            'label' => $this->texts->firstname,
            'class' => 'span3'
        ));

        $form->addElement('text', 'surname', array(
            'label' => $this->texts->surname,
            'class' => 'span3'
        ));

        $form->addElement('text', 'title', array(
            'label' => $this->texts->title,
            'class' => 'span3'
        ));

        $form->addElement(new Zend_Form_Element_Textarea(
            array(
                'name' => 'employment',
                'label' => $this->texts->workplace,
                'rows' => '3',
                'class' => 'span3'
            )));

        $form->addElement('text', 'email', array(
            'label' => $this->texts->email,
            'class' => 'span3'
        ));

        $form->addElement(new Zend_Form_Element_Textarea(
            array(
                'name' => 'text',
                'label' => $this->texts->textarea,
                'class' => 'span3',
                'rows' => '5'
            )));




        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'save_opinion_data',
                'label' => $this->texts->submit,
                'class' => 'btn-primary'
            )));

        // Validacii vyplnime formular z pole
        $form->isValid($data);

        return $form;
    }

    /**
     * Vrati objekt formulare pro editaci contentu.
     * Umoznuje pridat tento nazorovnik k nejake existujici proste strance
     * vyberem v selectboxu.
     *
     * @return Zend_Form    Formular pro editaci contentu
     */
    public function getEditContentForm($data = null)
    {

        // Najdeme seznam pozadovanych pages do selectboxu
        $sel = new Zend_Db_Select($this->db);
        $sel->from(array('p' => 'pages'))
            ->joinInner(array('cp' => 'content_pages'), 'p.id = cp.page_id')
            ->joinInner(array('c' => 'content'), 'c.id = cp.content_id')
            ->where("c.class_name != 'Ibulletin_Content_Opinions'")
            ->where("p.tpl_file = 'main_simple_content.phtml'")
            ->orwhere(sprintf("c.id = %d", $data['id']))
            ->order('p.name ASC');

        $pages = $this->db->fetchAll($sel);


        // Pripravime do selectu
        $pages_select = array('' => $this->texts->select_none);
        foreach($pages as $key => $page){
            $pages_select[$page['page_id']] = $page['name'];
        }

        // Emaily do vyberu
        $mailer = new Ibulletin_Mailer($this->config, $this->db);
        $emails = $mailer->getEmailsRenderData('');

        if(!isset($emails[$data['email_id']])) {
            $fmails = new Ibulletin_Mails($this->config,$this->db);
            $a_mail = (array)$fmails->getMailData($data['email_id']);
            if ($a_mail) {
                $emails[$data['email_id']] = $a_mail['name'];
            }
        }

        // Uzivatele k obmailovani do selectu - bereme jen test a client uzivatele, aby jich bylo mene
        $filter = '(test OR client) AND email IS NOT NULL';
        $users = $mailer->getUsers($filter, 'email ASC');
        $usersSelect = array(0 => $this->texts->select_none);
        foreach ($users as $user)
        {
            // Nechavame vybrat jen z uzivatelu, kteri nejsou jiz vybrani
            if(!in_array($user['id'], $data['object']->notificateUsers)){
                $usersSelect[$user['id']] = $user['email'];
            }
        }
        // Pokud jiz v selectu neni prdany uzivatel, odnastavime ho aby form po pridani nehlasil chybu
        if(!array_key_exists($data['notificate_user_id'], $usersSelect)){
            unset($data['notificate_user_id']);
        }

        // Vytvorime formular
        $form = new Form();
        $form->setMethod('post');

        $form->addElement('text', 'id', array(
            'label' => $this->texts->id,
            'readonly' => 'readonly',
            'class' => 'span1'
        ));

        $form->addElement('text', 'name', array(
            'label' => $this->texts->name,
            'autofocus' => 'autofocus'
        ));

        // Nastaveni co se bude v clanku jak zobrazovat - autor, pdf, anotace
        $form->addElement('checkbox', 'hide_date_author', array(
            'label' => $this->texts->hide_date_author
            ));
        $form->addElement('checkbox', 'hide_pdf_link', array(
            'label' => $this->texts->hide_pdf_link
            ));
        $form->addElement('checkbox', 'show_annotation', array(
            'label' => $this->texts->show_annotation
            ));

        // Anotace
        $annotation = new Zend_Form_Element_Textarea(array(
            'name' => 'annotation',
            'label' => $this->texts->annotation
            ));
        $annotation->setAttrib('class', 'editarea');
        $form->addElement($annotation);


        $form->addElement(new Zend_Form_Element_Select(
            array(
                'name' => 'page_id',
                'label' => $this->texts->page_id,
                'multioptions' => $pages_select
            )));

        $form->addElement('text', 'requiredfields', array(
            'label' => $this->texts->requiredfields,
        ));

        $form->addElement(new Zend_Form_Element_Select(
            array(
                'name' => 'email_id',
                'label' => $this->texts->email_id,
                'multioptions' => $emails,
            )));

        $form->addElement(new Zend_Form_Element_Select(
            array(
                'name' => 'notificate_user_id',
                'label' => $this->texts->notificate_user_id,
                'multioptions' => $usersSelect,
            )));

        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'save_content',
                'label' => $this->texts->submit,
                'class' => 'btn-primary'
            )));

        // Validacii vyplnime formular z pole
        $form->isValid($data);
        
        $links = new Links();
        $annotation->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));
     

        return $form;
    }

    /**
     * Ziska a vrati data prispevku - pokud byl odeslan formular,
     * jsou vracena data z formulare.
     *
     * @param int       ID prispevku
     * @return array    Pole obsahujici data prispevku
     */
    public function getOpinionData($id)
    {
        // Ziskame data nazoru z DB
        $data = Opinions::get($id);

        if(isset($_POST['save_opinion_data']) && isset($_POST['id']) && $_POST['id'] == $id){
            return $_POST + $data;
        }
        elseif(!empty($this->opinion_data) && isset($this->opinion_data['id']) && $this->opinion_data['id'] == $id){
            return $this->opinion_data;
        }
        else{
            $this->opinion_data = $data;
            return $data;
        }
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

        // Ziskame data contentu
        $data = Contents::get($id);

        $data['name'] = $data['object']->name;
        $data['annotation'] = $data['object']->annotation;

        // Checkboxy - v pripade ze neni odeslano, nastavujeme podle ulozeneho
        if(!isset($_POST['save_content'])){
            $data['hide_date_author'] = (!isset($data['object']->hide_date_author)?false:$data['object']->hide_date_author);
            $data['hide_pdf_link'] = (!isset($data['object']->hide_pdf_link)?false:$data['object']->hide_pdf_link);
            $data['show_annotation'] = (!isset($data['object']->show_annotation)?false:$data['object']->show_annotation);
        }


        // Najdeme page ke ktere je tento content pripojent, v pripade vice pripojeni bereme jen posledni
        $sel = new Zend_Db_Select($this->db);
        $sel->from(array('cp' => 'content_pages'))
            ->joinInner(array('c' => 'content'), 'c.id = cp.content_id')
            ->where(sprintf("c.id = %d", $id))
            ->order('c.id DESC');

        $data['page_id'] = $this->db->fetchOne($sel);
        if($data['page_id'] === null){
            $data['page_id'] = '';
        }

        $data['requiredfields'] = join(',', $data['object']->requiredFields);

        $data['email_id'] = $data['object']->emailNotification;

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
     * Ulozi data prijata z editacniho formulare.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveOpinionData($data)
    {
        if(!isset($data['save_opinion_data'])){
            // Neni co ukladat, data nebyla odeslana
            return $data;
        }

        # Zapiseme data prispevku
        $id = $data['id'];

        if((string)$data['timestamp'] != ''){
            //$valid_from = new Zend_Date($data['valid_from'], $config->general->dateformat->medium);
            $timestamp = new Zend_Date($data['timestamp']);
        }
        else{
            $timestamp = null;
        }

        $validator = new Zend_Validate_EmailAddress();

        // Provedeme akce spojene s nastavenim emailu uzivatelem
        if($validator->isValid($data['email'])){
            try{
                Users::emailSet($data['email'], $data['user_id']);
            }
            catch(Users_Exception $e){
                // Pokud se nepodarilo, neresime to, jen zalogujeme
                Phc_ErrorLog::warning('OpinionsController', $e);
            }
        }
        else{
            if(!empty($data['email']) && trim($data['email']) != '@'){
                $this->infoMessage($this->texts->validators->invalidemail, 'warning', array($data['email']));
                $data['email'] = null;
            }
            else{
                $data['email'] = '';
            }
        }

        Opinions::edit(null, $id, null, $data['name'], $data['surname'], $data['title'], $data['employment'],
                           $data['email'], $data['text'], $timestamp);

        $this->infoMessage(IBulletin_Texts::get('saved'));

        return $data;
    }

    /**
     * Ulozi data prijata z editacniho formulare contentu.
     *
     * @param array Data ziskane z formulare.
     * @return array Nekdy pozmenena data z formulare
     */
    public function saveContentData($data, $form)
    {
        if(!isset($data['save_content'])){
            // Neni co ukladat, data nebyla odeslana
            return $data;
        }

        // Zapiseme data prispevku
        $id = $data['id'];

        $content = Contents::get($id);
        $content['object']->name = $data['name'];
        $content['object']->annotation = $data['annotation'];

        $content['object']->emailNotification = $data['email_id'];
        // Notifikovani uzivatele
        if(!in_array($data['notificate_user_id'], $content['object']->notificateUsers)){
            $content['object']->notificateUsers[] = $data['notificate_user_id'];
        }

        // Povinna pole
        $requiredFields = explode(',',$data['requiredfields']);
        foreach($requiredFields as $key => $val){
            $requiredFields[$key] = trim($val);
        }
        $content['object']->requiredFields = $requiredFields;

        $content['object']->hide_date_author = (bool)$form->getValue('hide_date_author');
        $content['object']->hide_pdf_link = (bool)$form->getValue('hide_pdf_link');
        $content['object']->show_annotation = (bool)$form->getValue('show_annotation');


        $data['object'] = $content['object'];

        $ok = true;
        try{
            Contents::edit($id, $content['object']);
        }
        catch(Exception $e){
            $ok = false;
            $this->infoMessage(IBulletin_Texts::get('notsaved'),'error');
            Phc_ErrorLog::warning('OpinionsController', "Udaje contentu se nepodarilo zmenit. Puvodni vyjimka:\n$e");
        }

        // TODO - Vsechno to propojovani contentu udelat na multi - tohle zpusobi v pripade rucniho pripojeni vice odpojeni vsech!!
        if($ok){
            # Provedeme pripojeni ci odpojeni contentu od nejake proste stranky
            $default_template = 'main_simple_content.phtml';
            $opinions_template = 'main_article+opinion.phtml';

            try{
                $this->db->beginTransaction();
                // Nejprve odpojime od posledniho propojeni i se zmenou rozvrzeni na main_simple_content.phtml
                $sel = new Zend_Db_Select($this->db);
                $sel->from(array('cp' => 'content_pages'), array('page_id'))
                    ->joinInner(array('c' => 'content'), 'c.id = cp.content_id')
                    ->where(sprintf("c.id = %d", $id))
                    ->order('c.id DESC');
                $pages_to_change = $this->db->fetchAll($sel);

                // Pokud je to k necemu pripojene
                if(!empty($pages_to_change[0]['page_id']) && (empty($data['page_id'])
                    || $pages_to_change[0]['page_id'] != $data['page_id']))
                {
                    foreach($pages_to_change as $page){ // Foreach ted neni nutny
                       $this->db->update('pages', array('tpl_file' => $default_template), "id = ".$page['page_id']);
                    }
                    foreach($pages_to_change as $page){ // Foreach ted neni nutny
                       $this->db->delete('content_pages', "content_id = $id AND page_id = ".$page['page_id']);
                    }
                }

                // Pokud byl zvolen content, provedeme pripojeni
                if(!empty($data['page_id']) && (empty($pages_to_change[0]['page_id'])
                     || $pages_to_change[0]['page_id'] != $data['page_id']))
                 {
                   $this->db->update('pages', array('tpl_file' => $opinions_template), "id = ".$data['page_id']);
                   $this->db->insert('content_pages', array('content_id' => $id, 'page_id' => $data['page_id'], 'position' => 2));
                }

                $this->db->commit();
            }
            catch(Exception $e){
                $this->db->rollBack();
                // Pokud nastala chyba, zalogujeme ji a informujeme uzivatele o neuspechu
                Phc_ErrorLog::warning('OpinionsController', "Nepodarilo se priradit content do existujici page. Puvodni vyjimka:\n$e");
                $this->infoMessage(IBulletin_Texts::get('content_notsaved'), 'warning');
                $ok = false;
                if(!empty($pages_to_change[0]['page_id'])){
                    $data['page_id'] = $pages_to_change[0]['page_id'];
                }
            }

        }

        if($ok){
            $this->infoMessage(IBulletin_Texts::get('saved'));
        }

        return $data;
    }

    /**
     * Smaze uzivatele ze seznamu notificate users.
     *
     * Musi byt predany parametry:
     *  id      ID contentu nazorovniku
     *  userid  ID uzivatele k odebrani ze seznamu notificated
     */
    public function removenotifiedAction()
    {
        $contentId = $this->_request->getParam('id');
        $userId = $this->_request->getParam('userid');
        if($contentId && $userId){
            $content = $this->getContentData($contentId);
            if(in_array($userId, $content['object']->notificateUsers)){
                unset($content['object']->notificateUsers[array_search($userId, $content['object']->notificateUsers)]);
            }

            Contents::edit($contentId, $content['object']);
        }

        $this->redirect(array('action' => 'edit', 'id' => $contentId));
    }

}
