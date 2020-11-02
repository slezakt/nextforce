<?php
/**
 * Sprava sablon (vstupnich stranek, layoutu, emailu, ...)
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_TemplatesController extends Ibulletin_Admin_BaseControllerAbstract
{

    /**
     * inicializace
     */
    public function init() {
        parent::init();

        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
        );

        // TODO: pridat do adminusers modul a jeho dalsi prava
        if (Ibulletin_AdminAuth::hasPermission('templates_landing')) {
            $this->submenuAll['landing'] = array('title' => $this->texts->submenu_landing, 'params' => array('action' => 'landing'), 'noreset' => false);
        }
        if (Ibulletin_AdminAuth::hasPermission('templates_layouts')) {
            $this->submenuAll['layouts'] = array('title' => $this->texts->submenu_layouts, 'params' => array('action' => 'layouts'), 'noreset' => false);
        }
        if (Ibulletin_AdminAuth::hasPermission('templates_emails')) {
            $this->submenuAll['emails'] = array('title' => $this->texts->submenu_emails, 'params' => array('action' => 'emails'), 'noreset' => false);
        }
        if (Ibulletin_AdminAuth::hasPermission('privilege_edit_scripts')) {
            $this->submenuAll['scripts'] = array('title' => $this->texts->submenu_scripts, 'params' => array('action' => 'scripts'), 'noreset' => false);
        }
        if (Ibulletin_AdminAuth::hasPermission('privilege_edit_template_presentations')) {
            $this->submenuAll['presentations'] = array('title' => $this->texts->submenu_presentations, 'params' => array('action' => 'presentations'), 'noreset' => false);
        }

    }

    /**
     * ulozeni formulare dane entity
     *
     * @param string $name
     * @param array  $values
     * @return bool|int|null
     */

    public function updateRecord($name, $values) {

        switch ($name) {
            case "landing" :
            case "layouts" :
            case "scripts" :
                $path = $values['path'];
                $bck_path = preg_replace('/\.phtml$/', '.bckp', $path);

                //jestlize neexistuje bckp file vytvorime ho, parametr /nobckp/1 vytvoreni bckp filu potlaci
                if(!is_file($bck_path) && !$this->_hasParam('nobckp')) {
                    copy($path, $bck_path);
                }

                // ulozeni do souboru
                $data = $values['content'];

                // odlinkovani, pokud editujeme link TODO: kontrola /products
                if (is_link($path)) {
                    unlink($path);
                }


                // write changes to file
                $saved = file_put_contents($path, $data) !== false;

                return $saved;

            case "emails" :
                $path = $values['path'];

                $tpl_name = Utils::slugify($values['name'],array(),'_');
                $original_tpl_name = pathinfo($path, PATHINFO_FILENAME);

                $tpl_dir = $this->config->paths->mail_template.'/';
                $tpl_file_html = $tpl_dir . $tpl_name . '.html';
                $tpl_file_plain = $tpl_dir . $tpl_name . '.txt';

                // detection if name has changed but template already exists
                if ($original_tpl_name != $tpl_name){

                    if (!file_exists($tpl_file_html)) {
                        // move files and directories from the old template name
                        $moved = rename($path, $tpl_file_html) //rename html file
                            && rename(preg_replace('/\.html$/', '.txt', $path), $tpl_file_plain) //rename plain file
                            && rename($tpl_dir . $original_tpl_name, $tpl_dir. $tpl_name); // rename directory
                        if ($moved) {
                            // update all emails in DB with new template name
                            $updated = $this->db->update('emails', array('template' => $tpl_name),array('template = ?' => $original_tpl_name));
                            if ($updated) {
                                // TODO: think of ID changing if we change orders of files in directory by renaming or creating
                                // update to new path
                                $path = $tpl_file_html;

                                // detect new_id
                                $new_id = null;
                                foreach ($this->getEmailTemplates() as $tpl) {
                                    if (pathinfo($tpl['path'], PATHINFO_FILENAME) == $tpl_name) {$new_id = $tpl['id']; break;}
                                }

                                if ($new_id) $this->setAfterUpdateUrl('emails',array('action'=>'editemail', 'id' =>$new_id));
                            } else {
                                Phc_ErrorLog::warning('AdminTemplates::updateRecord()','Unable to update records for emails table for template '.$original_tpl_name .' to '.$tpl_name);
                                // continue;
                            }
                        } else {
                            Phc_ErrorLog::warning('AdminTemplates::updateRecord()','Unable to rename template '.$original_tpl_name .' to '.$tpl_name);
                            // continue;
                        }
                    } else {
                        $this->infoMessage($this->texts->editemail->tpl_exists, 'warning');
                        // continue;
                    }
                }

                // write changes to file
                $saved = file_put_contents($path, $values['content']) !== false;

                //ulozime plain text
                if ($saved) {
                    //nahradime koncovku
                    $pl_path = preg_replace('/\.html$/', '.txt', $path);
                    $saved = $saved && (file_put_contents($pl_path, $values['plaintext']) !== false);
                }
                return (boolean)$saved;

            case "presentations" :
                $rec = $this->getRecord('presentations', $values['id']);
                $folder = dirname($rec['path']);

                $name = Utils::slugify($values['name']);

                $re_folder = str_replace($rec['name'],$name,$folder);

                if(($folder != $re_folder) && is_dir($re_folder)) {
                     $this->infoMessage($this->texts->presentations->name_exists, 'warning');
                     return false;
                }

                return rename($folder,$re_folder);

            default: return null;
        }
    }

    /**
     * vraci zaznam dane entity
     *
     * @param string $name
     * @param mixed  $id
     * @return array|bool|null
     */

    public function getRecord($name, $id) {

        switch ($name) {
            case "landing":
                $source = Templates::getEditableViewScripts('landingpages');
                break;
            case "layouts" :
                $source = Templates::getEditableViewScripts('layouts');
                break;
            case "scripts" :
                $source = Templates::getAllViewScripts();
                break;
            case "emails" :
                $source = $this->getEmailTemplates();
                break;
            case "presentations" :
                $source = Templates::getAllPresentations();
                break;
            default: return null;
        }

        $page = $this->getTemplateById($source, $id);
        if (!$page) return false;

        // naplneni formulare obsahem a metadatami o souboru
        $data['id'] = $page['id'];
        $data['content'] = file_exists($page['path']) ? file_get_contents($page['path']) : '';
        $data['path'] = $page['path'];
        $data['name'] = $page['name'];
        if ($name=='emails') {
            $data['resources'] = $page['resources'];
            $pl_path = preg_replace('/\.html$/', '.txt', $page['path']);
            $data['plaintext'] = file_exists($pl_path) ? file_get_contents($pl_path) : '';
        }
        return $data;

    }

    /**
     * vraci formular pro editaci entity
     */
    public function getForm($name) {
        switch ($name) {
            case 'newemail':
                $form = parent::getForm($name);
                $form->setFormInline(true);
                $name = new Zend_Form_Element_Text(array(
                    'name' => 'name',
                    'label' => $this->texts->emails->newmail_label,
                    'filters' => array('StringTrim'),
                    'required' => true,
                    'size' => 40
                ));

                $name->addValidator('NotEmpty', true, array(
                    'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->emails->validators->newmail_empty)
                ));

                $form->addElements(array($name));
                return $form;
            case "landing" :
            case "layouts" :
            case "scripts" :

            $form = parent::getForm($name);

            $form->setAction($this->view->url());
                // textarea pro obsah konfiguracniho souboru
                $form->addElement('textarea','content',array(
                    'label' => $this->texts->content,
                    'required' => true,
                    'class' => 'editarea',
                    'data-ace-submit' => 'ajax'
                ));

                $form->getElement('content')->setDecorators(array(array('ViewScript', array(
                        'viewScript' => 'editor_form.phtml'))));

                 // metadata k template
                $form->addElement('hidden','id');
                $form->addElement('hidden','path');

                return $form;

           case "emails" :

               $form = parent::getForm($name);

               // editovatelny nazov templaty
               $form->addElement('text','name', array(
                   'label' => $this->texts->name,
                   'required' => true,
               ));

                // textarea pro obsah konfiguracniho souboru
                $form->addElement('textarea','content',array(
                    'label' => $this->texts->content,
                    'class' => 'span8',
                    'class' => 'editarea'
                ));

                // textarea pro obsah konfiguracniho souboru
                $form->addElement('textarea','plaintext',array(
                    'label' => $this->texts->plaintext,
                    'class' => 'span8',
                    'class' => 'editarea'
                ));

                $links = new Links();
                $storedLinks = $links->getSortedLinks();
                $form->getElement('content')->setDecorators(array(array('ViewScript', array(
                        'viewScript' => 'editor_form.phtml', 'templates'=>$this->loadTemplates(), 'links'=>$storedLinks, 'rank'=>'_1'))));

                $form->getElement('plaintext')->setDecorators(array(array('ViewScript', array(
                        'viewScript' => 'editor_form.phtml', 'templates'=>$this->loadTemplates(), 'links'=>$storedLinks, 'rank'=>'_2'))));


                // metadata k template
                $form->addElement('hidden','id');
                $form->addElement('hidden','path');

                return $form;

            case "presentations" :
                $form = parent::getForm($name);
                // editovatelny nazov templaty
                $form->addElement('text', 'name', array(
                    'label' => $this->texts->name,
                    'required' => true,
                ));
                 $form->addElement('note','pmaker',array(
                    'value' => '<a style="margin-bottom:10px" class="btn" href="'.$this->view->url(array('action'=>'pmaker')).'">'.$this->texts->editpresentation->editor.'</a>'
                ));

                return $form;

            default: return null;
        }
    }


    /**
     * vraci zaznam pro konkretni template, upravuje cestu v pripade vyskytu shadow souboru
     *
     * @param $templates
     * @param $id
     * @return array template data
     */
    public function getTemplateById($templates, $id) {

        $page = Utils::array_kv_search($templates, 'id', $id);
        if (!empty($page)) {

            $page = array_pop($page); // last matching page

            // path consolidation if present
            if (isset($page['path'])) {
                //!!!doplnena cesta, aby neprepisovala cestu fileuploaderu v akcích
                $resources = null;
                if (isset($page['resources'])) {
                    $resources = $page['resources'];
                }

                // change path here if file has shadow copy, so updates and reads are made from/to shadow file
                if (Files::hasShadowFile($page['path'])) {
                    $page['path'] = Files::getShadowFile($page['path']); // overwrite path
                }
            }

        }
        return $page;

    }

    /**
     * get available email templates
     * @return array
     */
    public function getEmailTemplates() {

        $res = array();
        //jestlize neexistuje slozka vytvorime ji
        if (!is_dir($this->config->paths->mail_template)) {
            Utils::mkdir($this->config->paths->mail_template);
        }
        try {
        $dir = new DirectoryIterator($this->config->paths->mail_template);
        } catch(Exception $ex) {
           $this->infoMessage($ex->getMessage(),'error');
           return array();
        }
        $id = 0;
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                //ziska nazev slozky
                $path = $fileinfo->getPathname();
                //jestlize soubor existuje pridame do listu a má koncovku html
                if (is_file($path) && preg_match('/\.html$/', $fileinfo->getFilename())) {
                    // strip extension, remove underscores, uppercase words
                    $name = ucwords(preg_replace(array('/^(.*)\..*$/', '/_/'), array('\1', ' '), $fileinfo->getBasename()));
                    $id++;
                    $resources = preg_replace('/\.html$/','',$path);
                    $res[] = array('id' => $id, 'name' => $name, 'path' => $path,'resources'=>$resources);
                }
            }
        }

        return $res;
    }



    /**
     * general function for listing and editing different template sets
     *
     * @param string $entity_name
     * @param Ibulletin_DataGrid_DataSource_Abstract $source
     * @return void
     */
    public function basic_list_edit_action($entity_name, $source) {

        $this->getHelper('viewRenderer')->setScriptAction('basic');

        $id = $this->_request->getParam('id', null);

        $action = $this->getRequest()->getActionName();

        //mazani emailove sablony
        if ($this->_hasParam('delmailtpl')) {
            $did = $this->_getParam('delmailtpl');
            if($this->delMailTemplate($did)) {
                $this->infoMessage($this->texts->emails->deleted);
            } else {
                $this->infoMessage($this->texts->emails->notdeleted,'error');
            }
            $this->redirect('emails');
        }

        //duplikace emailove sablony
        if ($this->_hasParam('duplicatemailtpl')) {
            $did = $this->_getParam('duplicatemailtpl');
            if($this->duplicateMailTemplate($did)) {
                $this->infoMessage($this->texts->emails->duplicated);
            } else {
               $this->infoMessage($this->texts->emails->notduplicated,'error');
            }
            $this->redirect('emails');
        }

        // edit page
        if ($id) {
            $this->setAfterUpdateUrl($entity_name, array('action' => $entity_name, 'id' => $id));
            $this->view->form = $this->processUpdate($entity_name,$id);
        } else {
        // list page
            try {
                $grid = new Ibulletin_DataGrid($source);

                $grid->setEmptyText($this->texts->index->empty);
                $grid->setDefaultSort("id");
                $grid->setDefaultDir('asc');

                $grid->addColumn("id", array(
                    "header" => $this->texts->id,
                ))
                    ->addColumn("name", array(
                    'header' => $this->texts->name,
                    'filter' => array(
                        'type' => 'expr',
                        'datatype' => 'string',
                        //"autocomplete" => true
                    )
                ));

                //emails template muzeme smazat a duplikovat
                if ($entity_name == "emails") {
                    $grid->addAction("delete", array(
                        'confirm' => $this->texts->emails->confirm_delete,
                        'url' => $this->_helper->url($action) . '/delmailtpl/$id/',
                        'caption' => $this->texts->emails->action_delete,
                        'image' => 'remove'
                    ));
                    $grid->addAction("duplicate", array(
                        'confirm' => $this->texts->emails->confirm_duplicate,
                        'url' => $this->_helper->url($action) . '/duplicatemailtpl/$id/',
                        'caption' => $this->texts->emails->action_duplicate,
                        'image' => 'clone'
                    ));
                    $grid->addAction("edit", array(
                        'url' => $this->_helper->url('editemail') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
                    ));
                } elseif($entity_name == "presentations") {
                     $grid->addAction("delete", array(
                        'confirm' => $this->texts->presentations->confirm_delete,
                        'url' => $this->_helper->url($action) . '/delpresentation/$id/',
                        'caption' => $this->texts->presentations->action_delete,
                        'image' => 'remove'
                    ));
                    $grid->addAction("duplicate", array(
                        'confirm' => $this->texts->presentations->confirm_duplicate,
                        'url' => $this->_helper->url($action) . '/duplicatepresentation/$id/',
                        'caption' => $this->texts->presentations->action_duplicate,
                        'image' => 'clone'
                    ));
                    $grid->addAction("edit", array(
                        'url' => $this->_helper->url('editpresentation').'/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
                    ));
                }
                else {
                    $grid->addAction("revert", array(
                        'empty' => 'revert',
                        'confirm' => $this->texts->confirm_revert,
                        'url' => $this->_helper->url($action) . '/revert/$id/',
                        'caption' => $this->texts->action_revert,
                        'image' => 'refresh'
                    ));
                    $grid->addAction("edit", array(
                        'url' => $this->_helper->url($action) . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
                    ));
                }
                if ($entity_name == "scripts") {
                    // show all records
                    $grid->setLimit(PHP_INT_MAX);
                }
                $this->view->grid = $grid->process();

            } catch (Exception $e) {
                $this->infoMessage($e->getMessage(), 'error');
            }
        }

    }

    /**
     * zobrazi formular pro spravu vstupnich stranek
     * akceptuje parameter id pro editaci sablony
     *
     */
    public function landingAction() {

        $lands = Templates::getEditableViewScripts('landingpages');

        if ($revert = $this->_getParam('revert')) {
            $res = $this->getTemplateById($lands, $revert);
            if (Templates::revertBckpFile($res['path'])) {
                $this->infoMessage($this->texts->reverted);
            }
            $this->redirect('landing');
        }

        $this->basic_list_edit_action('landing', $lands);
        if ($id = $this->_getParam('id')) {
            $l = $this->getTemplateById($lands, $id);
            $this->view->subTitle = $l['name'];
        }
    }

    /**
     * zobrazi formular pro spravu layoutu
     * akceptuje parameter id pro editaci sablony
     */
    public function layoutsAction() {

        $lay = Templates::getEditableViewScripts('layouts');

        if ($revert = $this->_getParam('revert')) {
            $res = $this->getTemplateById($lay, $revert);
            if(Templates::revertBckpFile($res['path'])) {
                $this->infoMessage($this->texts->reverted);
            }
             $this->redirect('layouts');
        }

        $this->basic_list_edit_action('layouts', $lay);
        if ($id = $this->_getParam('id')) {
            $l = $this->getTemplateById($lay, $id);
            $this->view->subTitle = $l['name'];
        }
    }

    /**
     * zobrazi formular pro spravu layoutu
     * akceptuje parameter id pro editaci sablony
     */
    public function scriptsAction() {

        if (!Ibulletin_AdminAuth::hasPermission('privilege_edit_scripts')) {
            $this->redirect('index','index');
        }

        Ibulletin_Js::addJsFile('admin/ajaxsave.js');

        $lay = Templates::getAllViewScripts();

        if ($revert = $this->_getParam('revert')) {
            $res = $this->getTemplateById($lay, $revert);
            if(Templates::revertBckpFile($res['path'])) {
                $this->infoMessage($this->texts->reverted);
            }
            $this->redirect('scripts');
        }

        $this->basic_list_edit_action('scripts', $lay);
        if ($id = $this->_getParam('id')) {
            $l = $this->getTemplateById($lay, $id);
            $this->view->subTitle = $l['name'];
        }
    }


    /**
     * zobrazi datagrid pro spravu emailu
     *
     */
    public function emailsAction() {

        $this->setAfterCreateUrl('newemail', 'emails');
        $this->view->form = $this->processCreate('newemail');

        $this->basic_list_edit_action('emails', $this->getEmailTemplates());

    }


    /**
     * zobrazi formular pro spravu emailu
     * akceptuje parameter id pro editaci sablony
     */
    public function editemailAction() {

        $id = $this->_getParam('id');

        if (!$id) {
            $this->redirect('emails');
        }

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->emails->submenu_edit_email, array('action' => 'editemail', 'id' => $id), null, true, 'editemail');
        $this->moduleMenu->setCurrentLocation('editemail');

        $res = $this->getRecord('emails', $id);

        $this->view->subTitle = $res['name'];

        //cesta k souborům, neexistuje-li vytvorime ji
        if (!file_exists($res['resources'])) {
            Utils::mkdir($res['resources']);
        }
        $this->view->path = $res['resources'];
        
        $this->view->form = $this->processUpdate('emails',$id);

        $this->view->preview_links = Templates::getTemplateEmails(pathinfo($res['path'], PATHINFO_FILENAME),false);

        // zobrazime seznam linku
        $this->renderLinks();
    }

    /**
     * zobrazi nahled sablony emailu
     */
    public function showemailAction() {
        $id = $this->_getParam('id');

        if (!$id) {
            throw new Zend_Controller_Router_Exception('Email with given id '.$id.' does not exists!', 404);
        }

        try {
            $mails = new Ibulletin_Mails($this->config, $this->db);
            $data = $mails->getMailData($id);

            if ($data['template']) {
                // parse HTML body of template
                $html_template = rtrim($this->config->paths->mail_template,'\\/')
                    . DIRECTORY_SEPARATOR . $data['template'] . '.html';
                $content = Templates::parseEmailTemplateFile($html_template, $data['template_data']['data']);
            } else {
                $content = $data['body'];
            }

            // parse tags that are not part of personalization tags
            $content = preg_replace(
                array('/%%subject%%/', '/%%imgmime#(.*)%%/'),
                array($data['subject'], preg_quote($this->config->mailer->imgsaddr) . $id . '/$1'),
                $content
            );

            $this->view->content = $content;

        } catch (Ibulletin_MailsException $e) {
            Phc_ErrorLog::warning('Templates::showemail', 'Unable to get data for email with id: ',$id);
            throw new Zend_Controller_Router_Exception('Email with given id '.$id.' does not exists!', 404);
        }

    }

    /**
     * zobrazi seznam souboru
     */
    public function indexAction() {}


     /**
     * metoda pro pridani zaznamu
     *
     * @param string $name identifikator entity
     * @param array $values
     *
     * @return boolean
     */
    public function createRecord($name, $values) {

        switch ($name) {
            case 'newemail':
                $name = Utils::slugify($values['name'],array(),'_');
                $folder = $this->config->paths->mail_template . '/' . $name;
                //jestlize slozka existuje upravime nazev sablony
                if (is_dir($folder)) {
                    $this->infoMessage($this->texts->emails->name_exist,'warning');
                    return false;
                }
                Utils::mkdir($folder);
                $saved = file_put_contents($this->config->paths->mail_template.'/'. $name . '.html', '') !== false;
                $saved = $saved && (file_put_contents($this->config->paths->mail_template.'/'. $name . '.txt', '') !== false);
                Utils::chmod($this->config->paths->mail_template.'/'. $name . '.html', Utils::FILE_PERMISSION);
                Utils::chmod($this->config->paths->mail_template.'/'. $name . '.txt', Utils::FILE_PERMISSION);
                return (boolean)$saved;
            default: return null;
        }
    }

    /**
     * Duplikuje sablonu email
     * @param int $id id sablony
     * @return bool
     */
    private function duplicateMailTemplate($id) {
        $data = $this->getRecord('emails',$id);
        $path = $data['path'];
        $resources = $data['resources'];

        $newpath = preg_replace('/\.html$/', '', $path);
        //cesta pro plain text
        $pl_path = $newpath;

        //najdeme "volny" nazev
        $i=2;
        while(is_file($newpath.'_'.$i.'.html')) {
            $i++;
        }
        $newpath .= '_'.$i.'.html';

        if (!Utils::rcopy($path,$newpath)) {
            return false;
        }

        //copy plain text
        if (!Utils::rcopy($pl_path.'.txt',$pl_path.'_'.$i.'.txt')) {
            return false;
        }

        return Utils::rcopy($resources,$resources.'_'.$i);

    }

    /**
     * Odstrani sablonu email
     * @param int $id id sablony
     * @return bool
     */
    private function delMailTemplate($id) {
        $data = $this->getRecord('emails',$id);
        //odstrani sablonu
        if(!unlink($data['path'])) {
            return false;
        }

        //odstrani plain text sablonu
        $pl_path = preg_replace('/\.html$/', '.txt',$data['path']);
        if(!unlink($pl_path)) {
            return false;
        }
        //odstrani resources
        return $this->rrmdir($data['resources']);
    }

    /**
     * Rekurzivni odstraneni slozky
     * @param type $dir
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir")
                        $this->rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
            return true;
        }
        return false;
    }

     /**
     * zobrazi datagrid pro spravu emailu
     *
     */
    public function presentationsAction() {

        //kontrola oprávnění
        if (!Ibulletin_AdminAuth::hasPermission('privilege_edit_template_presentations') ) {
            $this->redirect('index','index');
        }

        if ($this->_hasParam('duplicatepresentation')) {
            $pid = $this->_getParam('duplicatepresentation');
            if($this->duplicatePresentaionTemplate($pid)) {
                $this->infoMessage($this->texts->presentations->duplicated);
            } else {
                $this->infoMessage($this->texts->presentations->notduplicated,'error');
            }
            $this->redirect('presentations');
        }

         if ($this->_hasParam('delpresentation')) {
            $pid = $this->_getParam('delpresentation');
            if ($this->delPresentationTemplate($pid)) {
                $this->infoMessage($this->texts->presentations->deleted);
            } else {
                $this->infoMessage($this->texts->presentations->notdted,'error');
            }

            $this->redirect('presentations');
        }

        $this->basic_list_edit_action('presentations', Templates::getAllPresentations());

    }


     /**
     * Duplikuje sablonu prezentace
     * @param int $id id sablony
     * @return bool
     */
    private function duplicatePresentaionTemplate($id) {
        $data = $this->getRecord('presentations',$id);

        $path = dirname($data['path']);

        $newpath = $path;

        //najdeme "volny" nazev
        $i=2;
        while(is_dir($newpath.'_'.$i)) {
            $i++;
        }
        $newpath .= '_'.$i;


        return Utils::rcopy($path,$newpath);
    }


     /**
     * Odstrani sablonu email
     * @param int $id id sablony
     * @return bool
     */
    private function delPresentationTemplate($id) {
        $data = $this->getRecord('presentations',$id);

        //odstrani sablonu
        return $this->rrmdir(dirname($data['path']));
    }

      public function pmakersaveAction() {

        if ($this->getRequest()->isPost()) {
            $values = $this->getRequest()->getPost();
            $data = $values['data'];
            $target_file = $values['file'];

            $save = @file_put_contents($target_file, $data);
            if ($save) {
               $this->_helper->json(json_encode(array('result' => Ibulletin_Texts::get('pmaker.editor.save_presentation_success'))));
            } else {
               $this->_helper->json(json_encode(array('error' => Ibulletin_Texts::get('pmaker.editor.save_presentation_error'))));
            }
        }

    }

    /**
     * zobrazi formular pro editaci sablony prezentace
     * akceptuje parameter id pro editaci sablony
     */
    public function editpresentationAction() {

        //kontrola oprávnění
        if (!Ibulletin_AdminAuth::hasPermission('privilege_edit_template_presentations') ) {
            $this->redirect('index','index');
        }

        $id = $this->_getParam('id');

        if (!$id) {
            $this->redirect('presentations');
        }

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->presentations->submenu_edit_presentation, array('action' => 'editpresentation', 'id' => $id), null, true, 'editpresentation');
        $this->moduleMenu->setCurrentLocation('editpresentation');

        $rec = $this->getRecord('presentations', $id);

        $this->view->subTitle = $rec['name'];

        $path = dirname($rec['path']);
        //cesta k souborům, neexistuje-li vytvorime ji
        if (!file_exists($path)) {
            Utils::mkdir($path);
        }
        $this->view->path = $path;

        $this->view->form = $this->processUpdate('presentations',$id);

    }

    public function pmakerAction() {

       $id = $this->_request->getParam('id', null);

        if (!$id) {
            $this->redirect('presentations');
        }

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->presentations->submenu_edit_presentation, array('action' => 'editpresentation', 'id' => $id), null, true, 'editpresentation');
        $this->moduleMenu->setCurrentLocation('editpresentation');

        Ibulletin_Js::addJsFile('ckeditor/ckeditor.js');
        Ibulletin_Js::addJsFile('ckeditor/adapters/jquery.js');
        Ibulletin_Js::addPlainCode("var CKEDITOR_BASEPATH = '" . $this->view->baseUrl() . "/pub/scripts/ckeditor/';");
        //nastavi direktivu kterou preda ckeditor file manageru (elfinder), ze chce vratit relativni cestu k souborum, ktere se vkladaji pres elfinder
        Ibulletin_Js::addPlainCode("var CKEDITOR_ELFINDER_URL = '" . $this->view->url(array('controller'=>'filemanager','action'=>'elfinder-cke'))."'");
        Ibulletin_Js::addPlainCode("var CKEDITOR_ELF_RELATIVE = 1");
        Ibulletin_Js::addJsFile('admin/pmaker.js');
        Ibulletin_Js::addJsFile('jquery.xcolor.min.js');

        $rec = $this->getRecord('presentations', $id);

        //nastavi aktualni slozky pro elfinder
        $elfinder = new Zend_Session_Namespace('elfinder');
        $elfinder->path = $_SERVER['DOCUMENT_ROOT'].$this->view->baseUrl() . '/' . dirname($rec['path']);
        $elfinder->url = $this->view->baseUrl() . '/' . dirname($rec['path']);

        $links = new Links();
        $this->view->links = $links->getSortedLinks();

        if (is_file($rec['path'])) {
            $this->view->pres_index = $rec['path'];
        } else {
           $this->infoMessage(Ibulletin_Texts::get('pmaker.editor.unable_load'),'error');
           $this->redirect('editpresentation', 'templates', 'admin', array('id'=>$id));
        }


    }



}
