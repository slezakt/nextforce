<?php
class Admin_Newuser_Action_Exception extends Exception {}

/**
 * Modul pro správu uživatelů
 *
 * @author Zuzana Kleprlíková
 *
 */
class Admin_UsersController extends Ibulletin_Admin_BaseControllerAbstract {

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
    public function init() {
        parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'newuser' => array('title' => $this->texts->submenu_add, 'params' => array('action' => 'newuser'), 'noreset' => false),
     /*       'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'index', 'importform'=>null, 0=>'sort', 'sortdeleted'=> 'yes'), 'noreset' => true),*/
            'importdata' => array('title' => $this->texts->submenu_importdata, 'params' => array('action' => 'importdata'), 'noreset' => false),
            //'importform' => array('title' => $this->texts->submenu_importform, 'params' => array('action' => 'importform'), 'noreset' => true),//
            'segments' => array('title' => $this->texts->submenu_segments, 'params' => array('action' => 'segments'), 'noreset' => false),
            'regions' => array('title' => $this->texts->submenu_regions, 'params' => array('action' => 'regions'), 'noreset' => false),
            'channels' => array('title' => $this->texts->submenu_channels, 'params' => array('action' => 'channels'), 'noreset' => false)
        );

        $this->submenuSpecific = array(
            'groupChannels' => array(
                  'channelsadd' => array('title' => $this->texts->submenu_channels_new, 'params' => array('action' => 'channelsadd'), 'noreset' => true),
            ),
            'groupSegments' => array(
                  'segmentsadd' => array('title' => $this->texts->submenu_segments_new, 'params' => array('action' => 'segmentsadd'), 'noreset' => true),
            ),
            'groupRegions' => array(
                  'regionsadd' => array('title' => $this->texts->submenu_regions_new, 'params' => array('action' => 'regionsadd'), 'noreset' => true),
            ),
         );

        $this->submenuGroups = array(
            'groupChannels' => array('channels', 'channels', 'channelsadd'),
            'groupSegments' => array('segments', 'segments', 'segmentsadd'),
            'groupRegions' => array('regions', 'regions', 'regionsadd')
        );

        $action = $this->_getParam('action');
        $this->_helper->getHelper('contextSwitch')
            ->addActionContext($action, 'json')
            ->initContext();


    }

    /**
     * zobrazi seznam uzivatelu
     */
    public function indexAction() {

        $this->setLastVisited();

        try {

            $sel = Users::getUsersQuery()
                ->joinLeft(array('s' => 'segments'), 's.id = segment_id', array('segment' => 'name'));


            //db select pro export
            $expsel = Users::getUsersQuery(false,array('last_changed','last_replicated','changed_by_indirector'))
                ->joinLeft(array('s' => 'segments'), 's.id = segment_id', array('segment' => 'name'));

            if (isset($this->config->general->ghostUser->attribs) && !empty($this->config->general->ghostUser->attribs)) {
                $ghostAttribs = explode(',',str_replace(' ','',$this->config->general->ghostUser->attribs));
                       $sel->joinLeft(array('g'=>new Zend_Db_Expr('(SELECT id, (COALESCE('.implode($ghostAttribs, '::text, ').'::text'.') IS NULL) as ghost FROM users)')),'g.id=u.id',array('ghost'));

                $expsel->joinLeft(array('g'=>new Zend_Db_Expr('(SELECT id, (COALESCE('.implode($ghostAttribs, '::text, ').'::text'.') IS NULL) as ghost FROM users)')),'g.id=u.id',array('ghost'));
            }


              $grid = new Ibulletin_DataGrid($sel);

            $grid->setExportDataSource(Users::expandAttribs($expsel));

            $grid->setEmptyText($this->texts->index->empty);

            $grid->setDefaultSort('added');

            $opts_representants = array();
            $reps = new Reps();
            foreach ($reps->getReps() as $v) {
                $opts_representants[$v['id']] = $v['name'] . ' ' . $v['surname'];
            }

            $imgs_yes_no = array(0 => 'remove-color', 1 => 'ok-color');

            $opts_bool = array(
                0 => $this->texts->option_no,
                1 => $this->texts->option_yes,
            );

            $def = array(
                'align'=>'left',
                'default' => '&lt;null&gt;',
            );

            // Column definition
            $grid->addColumn('id', array_merge($def, array(
                'header' => $this->texts->id,
                'field' => 'u.id',
                'type' => 'action',
                'actions' => array(
                    'url' =>  $this->_helper->url('edit').'/id/$id/',
                    'caption' => '$id',
                ),
                'align' => 'right',
                'width' => '40px',
                'filter' => array(
                    'field' => 'u.id',
                    'type' => 'expr',
                    'datatype' => 'int',
                )
            )))
               ->addColumn('name', array_merge($def, array(
                'header' => $this->texts->first_name,
                'field' => 'u.name',
                'filter' => array(
                    'autocomplete' => true,
                    'type' => 'expr',
                    'datatype' => 'string',
                )
            )))
                ->addColumn('surname', array_merge($def, array(
                'header' => $this->texts->last_name,
                'field' => 'u.surname',
                'filter' => array(
                    'autocomplete' => true,
                    'type' => 'expr',
                    'datatype' => 'string',
                )
            )))
                ->addColumn('reps', array_merge($def, array(
                'header' => $this->texts->representative,
                'type' => 'representative',
                'class' => 'representative',
                'imgs' => array('user'),
                'filter' => array(
                    'type' => 'query',
                    'query' => 'u.id IN (SELECT user_id FROM users_reps WHERE repre_id = ?)',
                    'options' => $opts_representants,
                )

            )))
                ->addColumn('group', array_merge($def, array(
                'header' => $this->texts->group,
                'field' => 'u.group',
                'filter' => array(
                    'autocomplete' => true,
                    'type' => 'expr',
                    'datatype' => 'string',
                )
            )))
                ->addColumn('segment',array_merge($def, array(
                'header' => $this->texts->segment,
                'filter' => array(
                    'field' => 's.name',
                    'type' => 'expr',
                    'datatype' => 'string',
                    'autocomplete' => true,
                )
            )))
                ->addColumn('email', array_merge($def, array(
                'header' => $this->texts->email,
                'field' => 'u.email',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'string',
                    'autocomplete' => true,
                )
            )))
                ->addColumn('bad_addr', array_merge($def, array(
                'header' => $this->texts->bad_addr,
                'align' => 'center',
                'imgs' => $imgs_yes_no,
                'field' => 'u.bad_addr',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'bool',
                    'options' => $opts_bool,
                )
            )))
                ->addColumn('send_emails', array_merge($def, array(
                 'header' => $this->texts->send_emails,
                'align' => 'center',
                'imgs' => $imgs_yes_no,
                'type' => 'action',
                'actions' => array(
                    'url' =>  $this->_helper->url('update').'/id/$id/field/send_emails/',
                    'confirm' => $this->texts->index->confirmsend_emails
                ),
                'field' => 'u.send_emails',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'bool',
                    'options' => $opts_bool,
                )
            )))
                ->addColumn('target', array(
                'header' => $this->texts->target,
                'align'=>'center',
                'imgs' => $imgs_yes_no,
                'type' => 'action',
                'actions' => array(
                    'url' =>  $this->_helper->url('update').'/id/$id/field/target/',
                    'confirm' => $this->texts->index->confirmtarget,
                    //      'caption' => 'Modify target',
                    //      'image' => 'pub/img/admin/edit.png'
                ),
                'field' => 'u.target',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'bool',
                    'options' => $opts_bool,
                )
            ))
                ->addColumn('client', array(
                'header' => $this->texts->client,
                'align'=>'center',
                'imgs' => $imgs_yes_no,
                'type' => 'action',
                'actions' => array(
                    'url' =>  $this->_helper->url('update').'/id/$id/field/client/',
                    'confirm' => $this->texts->index->confirmclient
                ),
                'field' => 'u.client',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'bool',
                    'options' => $opts_bool,
                )
            ))
                ->addColumn('test', array(
                'header' => $this->texts->test,
                'align'=>'center',
                'imgs' => $imgs_yes_no,
                'type' => 'action',
                'actions' => array(
                    'url' =>  $this->_helper->url('update').'/id/$id/field/test/',
                    'confirm' => $this->texts->index->confirmtest
                ),
                'field' => 'u.test',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'bool',
                    'options' => $opts_bool,
                )
            ))
                ->addColumn('deleted', array_merge($def, array(
                'header' => $this->texts->deleted,
                'type' => 'datetime',
                'field' => 'u.deleted',
                'filter' => array(
                    'type' => 'query',
                    'query' => 'CASE WHEN ? = 0 THEN u.deleted IS NULL ELSE u.deleted IS NOT NULL END',
                    'datatype' => 'datetime',
                    'options' => $opts_bool,
                    )
            )))
                ->addColumn('added', array_merge($def, array(
                'header' => $this->texts->added,
                'type' => 'datetime',
                'field' => 'u.added',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'datetime',
                )
            )))
                ->addAction('delete', array(
                'notempty' => 'deleted',
                'confirm' => $this->texts->index->confirmdelete,
                'url' =>  $this->_helper->url('delete') . '/id/$id/',
                'caption' => $this->texts->action_delete,
                'image' => 'remove'
            ))
			    ->addAction('renew', array(
                'empty' => 'deleted',
                'confirm' => $this->texts->index->confirmrenew,
                'url' =>  $this->_helper->url('renew').'/id/$id/',
                'caption' => $this->texts->action_renew,
                'image' => 'refresh'
            ))
                ->addAction('subscribe', array(
                'empty' => 'unsubscribed',
                'confirm' => $this->texts->index->confirmsubscribe,
                'url' =>  $this->_helper->url('subscribe').'/id/$id/',
                'caption' => $this->texts->action_subscribe,
                'image' => 'inbox'
            ))
                ->addAction('edit', array(
                'url' =>  $this->_helper->url('edit').'/id/$id/',
                'caption' => $this->texts->action_edit,
                'image' => 'pencil'
            ));

            if (isset($this->config->general->ghostUser->attribs) && !empty($this->config->general->ghostUser->attribs)) {

                $grid->setDefaultFilters(array(
                    'ghost_users' => 0
                ));

                $grid->addFilter('ghost_users', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'CASE WHEN ? = 0 THEN ghost IS false ELSE 1=1 END',
                'formtype' => 'checkbox',
                'label' => $this->texts->ghost_users,
                'submitOnChange' => true
            ));
            }


        } catch (Ibulletin_DataGrid_Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

        $this->view->grid = $grid->process();

    }

    public function updateRecord($name, $values) {
        switch ($name) {
            case "user" :
                try {

                    if ($values['unsubscribed']) {
                    $unsub = new Zend_Date($values['unsubscribed'], $this->config->general->dateformat->medium);
                    $values['unsubscribed'] = $unsub->get(Zend_Date::ISO_8601);
                    }

                    $id = $values['id'];
                    unset($values['id']);
                    if ($values['pass'] == null) unset($values['pass']);
                    unset($values['pass_repeat']);
                    unset($values['contact_origin']);

                    if (!$id) return false;
                    $res = Users::updateUser($id, $values, false);

                    $freps = new Repspages();
                    $freps->deleteRep($id);
                    if ($values['presentations']) {
                        $freps->addPages($values['presentations'], $id);
                    }

                }
                catch (Exception $e) {
                    $this->infoMessage($e->getMessage(), 'error');
                    return false;
                }
                return $res;
            default: return null;
        }
    }

    public function createRecord($name, $values) {
        switch ($name) {
            case "user":
                try {
                    $res = Users::updateUser(null, $values, FALSE, array(), false, $_method, false, $values['contact_origin']);
                }
                catch (Exception $e) {
                    $this->infoMessage($e->getMessage(), 'error');
                    return false;
                }
                return $res;
            default: return null;
        }
    }

    public function deleteRecord($name, $id) {
        switch ($name) {
            case "user":
                try {
                    $user = Users::getUser($id);
                    $res = Users::updateUser($id, array('deleted' => new Zend_Db_Expr('current_timestamp')), false);
                }
                catch (Exception $e) {
                    $this->infoMessage($e->getMessage(), 'error');
                    return false;
                }
                return $res;
            default: return null;
        }
    }

    public function getRecord($name, $id) {
        $config =  Zend_Registry::get('config');
        switch ($name) {
            case "user":
                try {
                    $res = Users::getUser($id);

                    // Odstranime zakodovane heslo
                    if(isset($res['pass'])) unset($res['pass']);

                    if($res['unsubscribed']) {
                       $unsub = new Zend_Date($res['unsubscribed'], Zend_Date::ISO_8601);
                       $res['unsubscribed'] = $unsub->toString($config->general->dateformat->medium);
                    }

                    // Pokud je povolene ukladani plain hesla, dame plain heslo do pass a passAgain
                    if($config->general->password_save_as_plain && isset($res['pass_plain'])){
                        $res['pass'] = $res['pass_plain'];
                        $res['pass_repeat'] = $res['pass_plain'];
                    }

                    $freps = new Repspages();
                    $res['presentations'] = $freps->getPages($id);
                }
                catch (Exception $e) {
                    return false;
                }
                return $res;
            default: return null;
        }
    }

    /**
     * zobrazi formular pro pridani
     */
    public function newuserAction() {

        $this->setAfterCreateUrl('user', null, $this->getLastVisited());
        $this->setBeforeCreate(array($this, 'beforeCreate'));
        $this->view->form = $this->processCreate('user');

    }

    /**
     * zobrazi formular pro editaci
     */
    public function editAction() {

        $id = $this->_getParam('id');
        if (!$id) {
            $this->redirectUri($this->getLastVisited());
        }


        //Dropzone
        Ibulletin_Js::addJsFile('dropzone.min.js');
        Ibulletin_HtmlHead::addFile('dropzone.css');

	    // Formular pro upload fotky repa
	    $fileUploader = new Ibulletin_FileUploader(null, $this->config->mailer->imgrepaddr, $this->getRequest(), true);
	    $fileUploader->setNumFiles(1);
	    $fileUploader->fileNames[] = $id;
	    $fileUploader->doFormActions();
        $this->view->fileUploader = $fileUploader;

        $imgrep = rtrim($this->config->mailer->imgrepaddr, '/\\') . '/' . $id . '.png';

        if (file_exists($imgrep)) {
            $this->view->repImg = $imgrep;
        }

        //prida do menu odkaz editace a anonymizace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
		$this->moduleMenu->addItem($this->texts->anonymization->submenu_anonymization, array('action'=>'anonymization','id'=>$id), null, true,'anonymization');
		$this->moduleMenu->setCurrentLocation('edit');

        $this->setAfterUpdateUrl('user', null, $this->getLastVisited());
        $this->setBeforeUpdate(array($this, 'beforeUpdate'));

        $this->view->form = $this->processUpdate('user', $id);

    }

    public function beforeUpdate($name, $form, $record) {
        switch ($name) {
            case "user":
                // pokud se nezadalo nove heslo, vypneme required u vsech poli pro hesla
                if (!strval($this->_request->getParam('pass'))) {
                    $form->getElement('pass')->setRequired(false);
                    $form->getElement('pass_repeat')->setRequired(false);
                }
                break;
            default: return null;
        }
    }

    public function beforeCreate($name, $form) {
        switch ($name) {
            case "user":
                // pokud se nezadalo nove heslo, vypneme required u vsech poli pro hesla
                if (!strval($this->_request->getParam('pass'))) {
                    $form->getElement('pass')->setRequired(false);
                    $form->getElement('pass_repeat')->setRequired(false);
                }
                break;
            default: return null;
        }
    }



    /**
     * Action, která updatuje změny u bool hodnot target,test,client v tabulce
     *
     */
    public function updateAction()
    {

        $id = $this->_getParam('id');
        $field = $this->_getParam('field');

        $result = true;
        try {
            $user = Users::getUser($id);

            if (!array_key_exists($field, $user)) {
                throw new Exception('field \''.$field.'\' does not exists');
            } else {
                $value = $user[$field];
            }

            if (!is_bool($value)) {
                throw new Exception('field \''.$field.'\' is not of a boolean type');
            }

            Users::updateUser($id, array($field => (bool)!$value), false);
            $this->infoMessage($this->texts->update->success, 'success', array($field, $id));

        } catch (Exception $e) {
            $result = false;
            Phc_ErrorLog::warning('Admin::UsersController', $e);
            $this->infoMessage($this->texts->update->error, 'error', array($field, $id));
        }

        $this->redirectUri($this->getLastVisited());

        //$this->view->payload = array('data'=> array('id' => $id, 'field' => $field), 'result' => $result);

    }

    public function deleteAction() {

        $id = $this->_getParam('id');

        if (!$id) {
            $this->redirectUri($this->getLastVisited());
        }

        $this->setAfterDeleteUrl('user', null, $this->getLastVisited());
        $result = $this->processDelete('user', $id);

        //$this->view->payload = array('data'=> array('id' => $id), 'result' => $result);

    }

    public function renewAction() {

        $id = $this->_getParam('id');

        $result = true;
        try {
            $user = Users::getUser($id);
            Users::updateUser($id, array('deleted' => NULL), false);

            $this->infoMessage($this->texts->renew->success, 'success', array($id));
        } catch (Exception $e) {
            $result = false;
            Phc_ErrorLog::warning('Admin::UsersController', $e);
            $this->infoMessage($this->texts->renew->error, 'error', array($id));
        }

        $this->redirectUri($this->getLastVisited());

        //$this->view->payload = array('data'=> array('id' => $id), 'result' => $result);

    }

    /**
     * Akce pro prihlaseni uzivatele k emailingu
     */
    public function subscribeAction() {

        $id = $this->_getParam('id');

        $result = true;
        try {
            Users::updateUser($id, array('unsubscribed' => NULL), false);
            $this->infoMessage($this->texts->subscribe->success, 'success', array($id));
        } catch (Exception $e) {
            $result = false;
            Phc_ErrorLog::warning('Admin::UsersController', $e);
            $this->infoMessage($this->texts->subscribe->error, 'error', array($id));
        }

        $this->redirectUri($this->getLastVisited());

    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getForm($name) {
        $config =  Zend_Registry::get('config');

        switch ($name) {
            case "user":
                $form = parent::getForm($name);

                // email
                $form->addElement('text', 'email', array(
                    'label' => $this->texts->email,

                    'required' => false,
                    'validators' => array(
                        array('EmailAddress', true),
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                        array('StringLength', true, array('max' => 1000, 'messages' => array(
                            Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
                        ))),
                        array('Callback', true, array('callback' => array($this, 'isEmailUnique'), 'messages' => array(
                            Zend_Validate_Callback::INVALID_VALUE => $this->texts->validators->emailnotunique,
                        ))),
                    ),
                    'filters' => array('StringTrim','StringToLower',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));
               /* $form->addDisplayGroup(array($form->getElement('email')),'mail',
                    array('displayGroupClass' => 'Form_DisplayGroup_Inline'));*/

                // name
                $form->addElement('text', 'name', array(
                    'label' => $this->texts->first_name,

                    'required' => false,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                        array('StringLength', true, array('max' => 50, 'messages' => array(
                            Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
                            Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
                        ))),
                    ),
                    'filters' => array('StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));

                // surname
                $form->addElement('text', 'surname', array(
                    'label' => $this->texts->last_name,

                    'required' => false,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                        array('StringLength', true, array('max' => 50, 'messages' => array(
                            Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
                            Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
                        ))),
                    ),
                    'filters' => array('StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));

                $form->addDisplayGroup(array($form->getElement('name'),$form->getElement('surname'),$form->getElement('email')),'person',
                    array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                // gender
                $form->addElement('select', 'gender', array(
                    'label' => $this->texts->gender,
                    'required' => false,
                    'multioptions' => array(0 => $this->texts->notspecified, 'm' => $this->texts->male, 'f' => $this->texts->female),
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                    ),
                    'filters' => array('StringToLower','Null'
                    ),
                ));

                // degree_before
                $form->addElement('text', 'degree_before', array(
                    'label' => $this->texts->degree_before,
                    'required' => false,
                    'validators' => array(
                        array('StringLength', true, array('max' => 50, 'messages' => array(
                            Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
                            Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
                        ))),
                    ),
                    'filters' => array('StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));

                // degree_after
                $form->addElement('text', 'degree_after', array(
                    'label' => $this->texts->degree_after,
                    'required' => false,
                    'validators' => array(
                        array('StringLength', true, array('max' => 50, 'messages' => array(
                            Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
                            Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
                        ))),
                    ),
                    'filters' => array('StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));

                $form->addDisplayGroup(array($form->getElement('gender'), $form->getElement('degree_before'),
                    $form->getElement('degree_after')),'info',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                // login
                $form->addElement('text', 'login', array(
                    'label' => $this->texts->login,
                    'required' => false,
                    'validators' => array(
                        array('StringLength', true, array('max' => 60, 'messages' => array(
                            Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
                        ))),
                        array('Callback', true, array('callback' => array($this, 'isLoginUnique'), 'messages' => array(
                            Zend_Validate_Callback::INVALID_VALUE => $this->texts->validators->loginnotunique,
                        ))),
                    ),
                    'filters' => array('StringToLower','StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));

                // password
                // Type of the element depends on password_save_as_plain config value
                if($config->general->password_save_as_plain){
                    $passType = 'text';
                }
                else{
                    $passType = 'password';
                }
                $form->addElement($passType, 'pass', array(
                    'label' => $this->texts->password,
                    'required' => true,
                    'renderPassword' => true,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                        /*
                        array('StringLength', true, array('min' => 5, 'messages' => array(
                            Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
                        ))),
                        array('Regex', true, array('pattern' => '/^[-.*_\w\d]+$/', 'messages' => array(
                            Zend_Validate_Regex::NOT_MATCH => $this->texts->validators->password,
                        ))),
                        */
                    ),
                    'filters' => array(array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));

                // password_repeat
                $form->addElement($passType, 'pass_repeat', array(
                    'label' => $this->texts->password_repeat,
                    'required' => true,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                        array('Identical', true, array('token' => 'pass', 'messages' => array(
                            Zend_Validate_Identical::NOT_SAME => $this->texts->validators->passwordmismatch,
                        ))),
                    ),
                    'filters' => array(array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));

                $form->addDisplayGroup(array($form->getElement('login'),$form->getElement('pass'),$form->getElement('pass_repeat')),
                    'auth',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                // authcode
                $form->addElement('text', 'authcode', array(
                    'label' => $this->texts->authcode,

                    'required' => false,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                        array('StringLength', true, array('max' => 20, 'messages' => array(
                            Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
                            Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
                        ))),
                        array('Callback', true, array('callback' => array($this, 'isAuthcodeUnique'), 'messages' => array(
                            Zend_Validate_Callback::INVALID_VALUE => $this->texts->validators->authcodenotunique,
                        ))),
                    ),
                    'filters' => array(/*'StringToLower','StringTrim',*/array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));


                // group
                $form->addElement('text', 'group', array(
                    'label' => $this->texts->group,
                    'required' => false,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                        array('StringLength', true, array('max' => 100, 'messages' => array(
                            Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
                            Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
                        ))),
                    ),
                    'filters' => array('StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));


                $r = new Reps();
                $reps_select = array();
                foreach($r->getReps() as $val){
                    $reps_select[$val['id']]= $val['name'] == null && $val['surname']==null ?
                        $val['email'] : $val['name'].' '.$val['surname'];
                }


                $form->addElement('multiselect', 'reps', array(
                    'label' => $this->texts->representative,
                    'multioptions' => (array)$reps_select,
                ));

                $form->addDisplayGroup(array($form->getElement('authcode'),$form->getElement('group'), $form->getElement('reps')),
                    'grp',array('displayGroupClass' => 'Form_DisplayGroup_Inline'));


                // contact origin
                $contact_origin_params = array(
                    'label' => $this->texts->contact_origin,
                    'required' => true,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                                    Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                                )))
                    )
                );

                if ($this->getRequest()->getActionName() != "newuser") {
                    $contact_origin_params['readonly'] = 'readonly';
                    $contact_origin_params['required'] = false;
                }

                $form->addElement('text', 'contact_origin', $contact_origin_params);

                $form->addDisplayGroup(array($form->getElement('contact_origin')), 'grp2', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                // target, client, test
                $form->addElement('checkbox', 'target', array(
                    'label' => $this->texts->target,
                    'filters' => array('Boolean'),
                    'grid' => 1
                ));
                $form->addElement('checkbox', 'client', array(
                    'label' => $this->texts->client,
                    'filters' => array('Boolean'),
                    'grid' => 1
                ));
                $form->addElement('checkbox', 'test', array(
                    'label' => $this->texts->test,
                    'filters' => array('Boolean'),
                    'grid' => 1
                ));

                $form->addElement('checkbox', 'send_emails', array(
                    'label' => $this->texts->send_emails,
                    'filters' => array('Boolean'),
                    'grid' => 1
                ));

                $form->addElement('checkbox', 'send_nps', array(
                    'label' => $this->texts->send_nps,
                    'filters' => array('Boolean'),
                    'grid' => 1
                ));

                $form->addElement('checkbox', 'is_rep', array(
                    'label' => $this->texts->is_rep,
                    'filters' => array('Boolean'),
                    'grid' => 1
                ));

                $form->addElement('checkbox', 'selfregistered', array(
                    'label' => $this->texts->selfregistered,
                    'filters' => array('Boolean'),
                    'grid' => 2
                ));

                $form->addElement('checkbox', 'bad_addr', array(
                    'label' => $this->texts->bad_addr,
                    'filters' => array('Boolean'),
                    'grid' => 1
                ));

                $form->addDisplayGroup(array($form->getElement('target'),
                    $form->getElement('client'), $form->getElement('test'), $form->getElement('is_rep')), 'chck',
                        array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                $form->addDisplayGroup(array($form->getElement('send_emails'),$form->getElement('send_nps'),
                    $form->getElement('selfregistered'), $form->getElement('bad_addr')), 'chck2',
                        array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                // unsubscribed
                $form->addElement('text', 'unsubscribed', array(
                    'label' => $this->texts->unsubscribed,
                    'class' => 'datetimepicker',
                    'autocomplete' => 'off',
                    'required' => false,
                    'validators' => array(
                        array('Date', true, array(
                            'format' => $this->config->general->dateformat->medium,
                            'messages' => array(
                                Zend_Validate_Date::FALSEFORMAT=> sprintf($this->texts->validators->wrongdateformat, $this->config->general->dateformat->medium),
                                Zend_Validate_Date::INVALID=> sprintf($this->texts->validators->wrongdateformat, $this->config->general->dateformat->medium),
                                Zend_Validate_Date::INVALID_DATE=> sprintf($this->texts->validators->wrongdateformat, $this->config->general->dateformat->medium),
                            )
                        ))
                    ),
                    'filters' => array('StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));
                $form->addDisplayGroup(array($form->getElement('unsubscribed')), 'additional_attrs',
                        array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                // select pro segmenty
                $segments = new Segments();
                $segments_options = array('' => $this->texts->notspecified);
                foreach ($segments->getSegments() as $segment) {
                    $segments_options[$segment['id']] = $segment['name'];
                }

                // segment
                $form->addElement('select', 'segment_id', array(
                    'label' => $this->texts->segment,
                    'required' => false,
                    'multioptions' => $segments_options,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                    ),
                    'filters' => array( 'Int', 'Null',
                    ),
                ));

                // select pro regiony
                $regions = new Regions();
                $regions_options = array('' => $this->texts->notspecified);
                foreach ($regions->getRegions() as $region) {
                    $regions_options[$region['id']] = $region['name'];
                }

                // region
                $form->addElement('select', 'region_id', array(
                    'label' => $this->texts->region,
                    'required' => false,
                    'multioptions' => $regions_options,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                    ),
                    'filters' => array('Int', 'Null'
                    ),
                ));

                // select pro kanaly
                $channels = new Channels();
                $channels_options = array('' => $this->texts->notspecified);
                foreach ($channels->getChannels() as $channel) {
                    $channels_options[$channel['id']] = $channel['name'];
                }

                // segment
                $form->addElement('select', 'channel_id', array(
                    'label' => $this->texts->channel,
                    'required' => false,
                    'multioptions' => $channels_options,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                    ),
                    'filters' => array('Int', 'Null'
                    ),
                ));

                  $form->addDisplayGroup(array($form->getElement('segment_id'),
                    $form->getElement('region_id'), $form->getElement('channel_id')), 'seg',
                        array('displayGroupClass' => 'Form_DisplayGroup_Inline', 'grid' => 3));

                  // Reps
                 $form->addElement('multiselect', 'presentations', array(
                    'label' => $this->texts->presentantions,
                    'required' => false,
                    'description' => $this->texts->presentations_desc,
                    'multioptions' => $this->getPresentations())
                    );

                // all user attribute names
                $res = Users::getUserAttribNames();

                 foreach ($res as $v) {
                    $form->addElement('text', $v['name'], array(
                        'label' => $v['name'],
                        'filters' => array('Null'
                        ),
                    ));

                    if (!$form->getDisplayGroup('vname')) {
                        $form->addDisplayGroup(array($form->getElement($v['name'])), 'vname',
                                array('displayGroupClass' => 'Form_DisplayGroup_Inline', 'legend' => $this->texts->user_attribs));
                    } else {
                        $form->getDisplayGroup('vname')->addElement($form->getElement($v['name']));

                    }
                }

                return $form;
            default: return null;
        }
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getUpdateForm($name)
     */
/*    public function getCreateForm($name) {
        switch ($name) {
            case "user":
                $form = parent::getCreateForm($name);


                return $form;
            default: return null;
        }
    }
  */
    public function isEmailUnique($email, $context = NULL) {
        try {
            $user = Users::getUser(null, $email);
        } catch (Users_User_Not_Found_Exception $e) {
            return true;
        }
        $found_id = $user['id'];
        return (!$found_id) || (isset($context['id']) && $context['id'] == $found_id);
    }

    public function isLoginUnique($login, $context = NULL) {
        try {
            $user = Users::getUser(null, null, $login);
        } catch (Users_User_Not_Found_Exception $e) {
            return true;
        }
        $found_id = $user['id'];
        return (!$found_id) || (isset($context['id']) && $context['id'] == $found_id);
    }

    public function isAuthcodeUnique($authcode, $context = NULL) {
        try {
            $user = Users::getUser(null, null, null, $authcode);
        } catch (Users_User_Not_Found_Exception $e) {
            return true;
        }
        $found_id = $user['id'];
        return (!$found_id) || (isset($context['id']) && $context['id'] == $found_id);
    }

    /**
     * Umoznuje upload CSV s daty uzivatelu do DB.
     *
     * Ze vstupniho CSV uklada na misto prazdnych bunek (otrimovanych) do DB NULL honoty tam,
     * kde je to povoleno. V pripade NOT NULL a boolean atributu je za prazdny retezec ulozeno FALSE.
     * Pro neprovadeni zmeny v bunce je treba bunku vyplnit retezcem Users::$noChangeSymbol - (n/c).
     *
     * Vstupni CSV rozparsuje a nazvy sloupcu v prvnim radku prevede na mala pismena,
     * v DB se tedy ani v tabulce users_answers nesmeji vyskytovat nazvy atributu s velkymi pismeny.
     */
    public function importdataAction()
    {
        $req = $this->getRequest();
        $db =  Zend_Registry::get('db');
        $config =  Zend_Registry::get('config');
        $texts = Ibulletin_Texts::getSet();

        // Najdeme seznam columns pro vypsani v infu
        $q = "SELECT column_name, data_type FROM (
                  (select column_name, data_type, ordinal_position from INFORMATION_SCHEMA.COLUMNS where table_name = 'users'
                  AND column_name NOT IN ('last_changed', 'last_replicated', 'changed_by_indirector')

                  order by ordinal_position)
                  UNION
                  (SELECT DISTINCT name AS column_name, 'character_varying' AS data_type, 999999 AS ordinal_position FROM users_attribs)
               ) as foo
               ORDER BY ordinal_position, column_name";
        $columns = $db->fetchAll($q);
        $this->view->usersDescribe = $columns;

        // nastavime JS
        $js = Ibulletin_Js::getInstance();
        Ibulletin_Js::addJsFile('admin/collapse.js');

        //nacteme js export do xlsx
        Ibulletin_Js::addJsFile('jszip.js');
        Ibulletin_Js::addJsFile('xlsx.js');
        //js pro rozsirene moznosti tabulek v monitoringu export a volitelné sloupce
        Ibulletin_Js::addJsFile('admin/users.js');
        $loop_url = $this->view->url(array('controller'=>'loopback', 'action'=>'get'));
        //loopback url pro xlsx export
        Ibulletin_Js::addPlainCode('var LOOPBACK_URL = "'.$loop_url.'";');

        // sloupce pro xlsx import templatu a do vypisu sloupcu v sidebaru
        $exclude_cols = array('id', 'last_changed', 'changed_by_indirector', 'last_replicated', 'reps', 'added', 'registered');
        $out = '';
        foreach($columns as $attr){
            $out .="<li>${attr['column_name']} (${attr['data_type']})</li>";

            if (in_array($attr['column_name'], $exclude_cols)) continue;
            $js->vars->xlsx_columns[] = $attr['column_name'];
        }
        // ukazkovy uzivatel jako jeden zaznam xlsx import templaty
        $example_user = Users::expandAttribs(Users::getUsersQuery(true, $exclude_cols)->where('email = ?', 'kdosi@pearshealthcyber.com'))
            ->query()->fetchAll();
        if ($example_user) {
            //sloupce do kterych bude doplnen ukazkovy datum
            $writedate_cols = array('unsubscribed','deleted');
            $example_user = array_pop($example_user);
            foreach ($example_user as $k => $v) {

                // sloupce ktere v sablone zamerne vynechavame
                if (in_array($k, $exclude_cols)) {
                    continue;
                }

                //nema-li sloupec ve kterem má být datum hodnotu doplnime ji
                 if (!$v && in_array($k, $writedate_cols)) {
                     $v = date('Y-m-d H:i:s');
                 }

                foreach ($columns as $c) {
                    if ($v && $c['column_name'] == $k && strpos($c['data_type'],'timestamp') !== FALSE) {
                        $v = date('Y-m-d H:i:s', strtotime($v));
                    }
                }
                // remove authcode value (security reason)
                if ($k == 'authcode') $v = '';

                $js->vars->xlsx_example_user[] = $v;
            }
        }

        $texts->help->description->body = sprintf($texts->help->description->body,$out);

        $this->view->noChangeSymbol = Users::$noChangeSymbol;

        // Zjistime, jestli byl odeslan formular nebo ukladame data z dry runu
        if(!$req->getParam('submit', null) && !$this->_getParam('dr_save')){
            $this->view->showInfo = true;
            return;
        }

        $ses_imp = new Zend_Session_Namespace('usersimport');

        //jestlize neukladame data z dry runu, uploadujeme atd ...
        if (!$this->_getParam('dr_save')) {

            $contact_origin = $this->_getParam('contact_origin');

            if(!$contact_origin) {
                $this->infoMessage($this->texts->importdata->contactOriginError, 'error');
                $this->redirect('importdata');
                return;
            }

            //vycistime session
            if (isset($ses_imp->tmpfile)) {
                unlink($ses_imp->tmpfile);
                $ses_imp->unsetAll();
            }
            // Nahrajeme soubory, pouzijeme nektere funkce z file uploaderu
            $tmpFile = tempnam(sys_get_temp_dir(), "usersimport");
            unlink($tmpFile); // Soubor se pri vytvareni nazvu zaroven vytvori
            $fileUpl = new Ibulletin_FileUploader();
            $fileUpl->setBasePath(dirname($tmpFile));
            $fileUpl->setTargetPath('');
            //$fileUpl->fileNames = array(basename($tmpFile));
            try {
                //$errors = $fileUpl->uploadFiles($_FILES);
                // Musime nazev souboru predat jako pole jmeno a pripona... (na windows a linux vraci tempnam s priponou a bez)
                $errors = $fileUpl->uploadFile(array(basename($tmpFile), ''));
            } catch (Ibulletin_FileUploader_Exception $e) {
                $this->infoMessage($e->getMessage(), 'warning');
                Phc_ErrorLog::warning('Admin_UsersController::importdataAction()', $e);
                return;
            }
            if ($errors !== true) {
                $errors = is_array($errors) ? $errors : array($errors);
                foreach ($errors as $error) {
                    $this->infoMessage($error, 'error');
                }
            }

            // Zkontrolujeme, jestli existuje nahrany soubor
            if (!is_readable($tmpFile)) {
                $this->infoMessage($this->texts->file_not_readable, 'error', array($tmpFile));
                return;
            }

            // Nacteme si typ a encoding
            $fileType = $req->getParam('fileType', 'xml');
            // Zjednoduseny vyber typu souboru a kodovani - XML je vzdy UTF-8, ostatni kodovani jsou vzdy typ CSV
            if ($fileType == 'xml') {
                $type = 'xml';
                $encoding = 'utf-8';
            } elseif ($fileType == 'xlsx') {
                $type = 'xlsx';
                $encoding = 'utf-8';
            } else {
                $type = 'csv';
                $encoding = $fileType;
            }
        } else {
            if (isset($ses_imp->tmpfile)) {
                $tmpFile = $ses_imp->tmpfile;
                $type = $ses_imp->filetype;
                $encoding = $ses_imp->encoding;
                $contact_origin = $ses_imp->contact_origin;
                $ses_imp->unsetAll();
            } else {
                return;
            }
        }

        // Zavolame zpracovani souboru
        $messages = array();
        $users = new Users;

        //nastavime zdroj importovanych dat
        $users->importContactOrigin = $contact_origin;

        try {
        $dataForRepair = $users->importData($tmpFile, $type, $encoding, $messages, $req->getParam('dryRun'));
        } catch (Exception $e) {
            $messages[] = array($texts->import_error, 'error');
            $dataForRepair = array();
            Phc_ErrorLog::warning('Users::importData', $e);
        }

        $detailedList = array();

        // Pridame zpravy z importu k vystupu uzivateli
        foreach($messages as $message){

            // Doplnime pripadny chybejici prvky
            !isset($message[1]) ?  $message[1] = null : null;
            !isset($message[2]) ?  $message[2] = array() : null;

            //odchytavame varovne zpravy, tech se muze vyskytnou mnohou, proto je skryjeme pod "více"
            if ($message[1] == 'warning') {
               $detailedList['warning'][] = $this->infoMessage($message[0], $message[1], $message[2],false);
            } else {
               $this->infoMessage($message[0], $message[1], $message[2]);
            }

        }

        if (isset($detailedList['warning'])) {
             $this->infoMessage(sprintf($texts->warningMessages,count($detailedList['warning'])),'warning',array(),true,$detailedList['warning']);
        }

        // Preskupime importovane atributy
        $importedAttribs = array('users' => array(), 'usersAttribs' => array());
        foreach($users->importedAttribs as $attr => $table){
            $importedAttribs[$table][] = $attr;
        }

        // Info o dry run
        if($req->getParam('dryRun')){
            $this->infoMessage('Dry run!' , 'warning');
        }

        $this->view->dataRepair = $dataForRepair;
        $this->view->importedAttribs = $importedAttribs;

        //jestlize dry run prosel v poradku nastavime info k filu do session a zobrazime dialog pro ulozeni dat do db
        if ($req->getParam('dryRun')) {
            if (isset($ses_imp->tmpfile)) {
              unlink($ses_imp->tmpfile);
            }
            $ses_imp->unsetAll();
            $ses_imp->tmpfile = $tmpFile;
            $ses_imp->filetype = $type;
            $ses_imp->encoding = $encoding;
            $ses_imp->contact_origin = $contact_origin;
            //pred zobrazením dialogu, mrknem jestli jsou nejaka data k vlozeni
            if ($users->importedAttribs) {
             $this->view->dry_save = true;
            }
        } else {
            // Uklidime docasny soubor
            unlink($tmpFile);
            //jestlize ukladame data z dry runu přesměrujeme
            if ($this->_getParam('dr_save')) {
                $this->redirect('importdata');
            }
        }
    }

    /**
     * nacte ciselnik segmentu
     */
    public function segmentsAction() {

        $config_texts = Ibulletin_Texts::getSet();

        try {
            $grid = new Ibulletin_DataGrid(Segments::getSegmentsQuery());
            $grid->setEmptyText($config_texts->empty);
            $grid->setDefaultSort('id');

            $grid->addColumn('id', array(
                        'header' => $this->texts->id,
                    ))
                    ->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            "autocomplete" => true
                        )
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('segmentsedit') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
                    ))
                    ->addAction("delete", array(
                        'confirm' => $config_texts->confirmdelete,
                        'url' => $this->_helper->url('segmentsdelete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
            ));

            $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

    /**
     * edituje ciselnik segmentu
     */
    public function segmentseditAction() {
         $config_texts = Ibulletin_Texts::getSet('admin.users.segments');
         $request = $this->getRequest();

         $_id = $request->id;
         $id = (!empty($_id)) ? intval($_id) : 0;

         $segments = new Segments();
         $segment = $segments->getSegments($id);

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($config_texts->submenu_segmentsedit, array('action'=>'segmentsedit','id'=>$id), null, false,'segmentsedit');
        $this->moduleMenu->setCurrentLocation('segments');

        /**
         * zobrazit formular
         */
        if (!empty($segment)) {
            $form = $this->_getSegmentsForm($segment[0]);
            $this->view->form = $form;
        }
        else {
            $this->view->notFound = $config_texts->notfound;
        }

        // pokud je formular validni, ulozime
        if (empty($_POST) || !$form->isValid($_POST)) {
            $this->view->form = $form;
        }
        else {
           if ($segments->saveSegment($_POST)) {
               $this->view->form = $config_texts->saved;
           }
           else {
               $this->view->form = $config_texts->notsaved;
           }
        }
    }

    /**
     * prida segment
     */
    public function segmentsaddAction() {
        $config_texts = Ibulletin_Texts::getSet('admin.users.segments');
        $request = $this->getRequest();

        $_id = $request->id;
        $id = (!empty($_id)) ? intval($_id) : 0;

        $segments = new Segments();
        $form = $this->_getSegmentsForm();

        // pokud je formular validni, ulozime
        if (empty($_POST) || !$form->isValid($_POST)) {
            $this->view->form = $form;
        }
        else {
           if ($segments->saveSegment($_POST, 'insert')) {
               $this->view->form = $config_texts->saved;
           }
           else {
               $this->view->form = $config_texts->notsaved;
           }
        }
    }

    /**
     * smaze segment
     */
    public function segmentsdeleteAction() {
        $config_texts = Ibulletin_Texts::getSet('admin.users.segments');
        $request = $this->getRequest();

        $_id = $request->id;
        $id = (!empty($_id)) ? intval($_id) : 0;

        $segments = new Segments();

        if ($segments->deleteSegment($id)) {
            $this->view->status = $config_texts->deleted;
        }
        else {
            $this->view->status = $config_texts->notdeleted;
        }
    }

    /**
     * Formular pro pridani nebo editaci segmentu
     * @param array data
     * @return Zend_Form formular
     */
    protected function _getSegmentsForm($data = array()) {

        $config_texts = Ibulletin_Texts::getSet('admin.users.segments');

        $form = new Form();
        $form->setMethod('post');

        // hidden id
        $formIdHidden = new Zend_Form_Element_Hidden('id');
        if (!empty($data['id'])) {
            $formIdHidden->setValue($data['id']);
        }
        $form->addElement($formIdHidden);

        // nazev
        $formName = new Zend_Form_Element_Text('name', array('size' => '35'));
        $formName->setLabel($config_texts->name);
        if (!empty($data['name'])) {
            $formName->setValue($data['name']);
        }

        //validatory pro nazev
        $nameValidatorLength = new Zend_Validate_StringLength(3);
        $nameValidatorLength->setMessages(array(
            Zend_Validate_StringLength::TOO_SHORT => $config_texts->validators->tooshort
            )
        );
        $formName->addValidator($nameValidatorLength);

        $nameValidatorEmpty = new Zend_Validate_NotEmpty();
        $nameValidatorEmpty->setMessages(array(
            Zend_Validate_NotEmpty::IS_EMPTY => $config_texts->validators->isempty
            )
        );
        $formName->addValidator($nameValidatorEmpty);

        $formName->setRequired(true);
        $form->addElement($formName);

        //  csrf ochrana
        $form->addElement('hash', 'no_csrf', array('salt' => 'unique'));

        // odeslat
        $formSave = new Zend_Form_Element_Submit($config_texts->submit);
        $formSave->setAttrib('class','btn-primary');

        $form->addElement($formSave);

        return $form;
    }


    /**
     * nacte ciselnik regionu
     */
    public function regionsAction() {
         $config_texts = Ibulletin_Texts::getSet('admin.users.regions');

          try {
            $grid = new Ibulletin_DataGrid(Regions::getRegionsQuery());
            $grid->setEmptyText($config_texts->empty);
            $grid->setDefaultSort('id');

            $grid->addColumn('id', array(
                        'header' => $this->texts->id,
                    ))
                    ->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            "autocomplete" => true
                        )
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('regionsedit') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
                    ))
                    ->addAction("delete", array(
                        'confirm' => $config_texts->confirmdelete,
                        'url' => $this->_helper->url('regionsdelete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
            ));

            $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

    /**
     * prida region
     */
    public function regionsaddAction() {
        $config_texts = Ibulletin_Texts::getSet('admin.users.regions');
        $request = $this->getRequest();

        $_id = $request->id;
        $id = (!empty($_id)) ? intval($_id) : 0;

        $regions = new Regions();
        $form = $this->_getRegionsForm();

        // pokud je formular validni, ulozime
        if (empty($_POST) || !$form->isValid($_POST)) {
            $this->view->form = $form;
        }
        else {
           if ($regions->saveRegion($_POST, 'insert')) {
               $this->view->form = $config_texts->saved;
           }
           else {
               $this->view->form = $config_texts->notsaved;
           }
        }
    }

    /**
     * edituje ciselnik regionu
     */
    public function regionseditAction() {
         $config_texts = Ibulletin_Texts::getSet('admin.users.regions');
         $request = $this->getRequest();

         $_id = $request->id;
         $id = (!empty($_id)) ? intval($_id) : 0;

         $regions = new Regions();
         $region = $regions->getRegions($id);

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($config_texts->submenu_regionsedit, array('action'=>'reqionsedit','id'=>$id), null, false,'regionsedit');
        $this->moduleMenu->setCurrentLocation('regions');

        /**
         * zobrazit formular
         */
        if (!empty($region)) {
            $form = $this->_getRegionsForm($region[0]);
            $this->view->form = $form;
        }
        else {
            $this->view->notFound = $config_texts->notfound;
        }

        // pokud je formular validni, ulozime
        if (empty($_POST) || !$form->isValid($_POST)) {
            $this->view->form = $form;
        }
        else {
           if ($regions->saveRegion($_POST)) {
               $this->view->form = $config_texts->saved;
           }
           else {
               $this->view->form = $config_texts->notsaved;
           }
        }
    }

    /**
     * smaze region
     */
    public function regionsdeleteAction() {
        $config_texts = Ibulletin_Texts::getSet('admin.users.regions');
        $request = $this->getRequest();

        $_id = $request->id;
        $id = (!empty($_id)) ? intval($_id) : 0;

        $regions = new Regions();

        if ($regions->deleteRegion($id)) {
            $this->view->status = $config_texts->deleted;
        }
        else {
            $this->view->status = $config_texts->notdeleted;
        }
    }


    /**
     * Formular pro pridani nebo editaci regionu
     * @param array data
     * @return Zend_Form formular
     */
    protected function _getRegionsForm($data = array()) {

        $config_texts = Ibulletin_Texts::getSet('admin.users.segments');

        $form = new Form();
        $form->setMethod('post');

        // hidden id
        $formIdHidden = new Zend_Form_Element_Hidden('id');
        if (!empty($data['id'])) {
            $formIdHidden->setValue($data['id']);
        }
        $form->addElement($formIdHidden);

        // nazev
        $formName = new Zend_Form_Element_Text('name', array('size' => '35'));
        $formName->setLabel($config_texts->name);
        if (!empty($data['name'])) {
            $formName->setValue($data['name']);
        }

        //validatory pro nazev
        $nameValidatorLength = new Zend_Validate_StringLength(3);
        $nameValidatorLength->setMessages(array(
            Zend_Validate_StringLength::TOO_SHORT => $config_texts->validators->tooshort
            )
        );
        $formName->addValidator($nameValidatorLength);

        $nameValidatorEmpty = new Zend_Validate_NotEmpty();
        $nameValidatorEmpty->setMessages(array(
            Zend_Validate_NotEmpty::IS_EMPTY => $config_texts->validators->isempty
            )
        );
        $formName->addValidator($nameValidatorEmpty);

        $formName->setRequired(true);
        $form->addElement($formName);

        //  csrf ochrana
        $form->addElement('hash', 'no_csrf', array('salt' => 'unique'));

        // odeslat
        $formSave = new Zend_Form_Element_Submit($config_texts->submit);
        $formSave->setAttrib('class','btn-primary');
        $form->addElement($formSave);

        return $form;
    }

    /**
     * nacte ciselnik kanalu
     */
    public function channelsAction() {
         $config_texts = Ibulletin_Texts::getSet('admin.users.channels');

          try {
            $grid = new Ibulletin_DataGrid(Channels::getChannelsQuery());
            $grid->setEmptyText($config_texts->empty);
            $grid->setDefaultSort('id');

            $grid->addColumn('id', array(
                        'header' => $this->texts->id,
                    ))
                    ->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            "autocomplete" => true
                        )
                    ))
                    ->addColumn('code', array(
                        'header' => $this->texts->code,
                        'field' => 'code',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            "autocomplete" => true
                        )
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('channelsedit') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
                    ))
                    ->addAction("delete", array(
                        'confirm' => $config_texts->confirmdelete,
                        'url' => $this->_helper->url('channelsdelete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
            ));

            $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

    /**
     * prida kanal
     */
    public function channelsaddAction() {
        $config_texts = Ibulletin_Texts::getSet('admin.users.channels');
        $request = $this->getRequest();

        $_id = $request->id;
        $id = (!empty($_id)) ? intval($_id) : 0;

        $channels = new Channels();
        $form = $this->_getChannelsForm();

        // pokud je formular validni, ulozime
        if (empty($_POST) || !$form->isValid($_POST)) {
            $this->view->form = $form;
        }
        else {
           if ($channels->saveChannel($_POST, 'insert')) {
               $this->view->form = $config_texts->saved;
           }
           else {
               $this->view->form = $config_texts->notsaved;
           }
        }
    }

    /**
     * edituje ciselnik kanalu
     */
    public function channelseditAction() {
         $config_texts = Ibulletin_Texts::getSet('admin.users.channels');
         $request = $this->getRequest();

         $_id = $request->id;
         $id = (!empty($_id)) ? intval($_id) : 0;

         $channels = new Channels();
         $channel = $channels->getChannels($id);

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($config_texts->submenu_channelsedit, array('action'=>'channelsedit','id'=>$id), null, false,'channelsedit');
        $this->moduleMenu->setCurrentLocation('channels');

        /**
         * zobrazit formular
         */
        if (!empty($channel)) {
            $form = $this->_getChannelsForm($channel[0]);
            $this->view->form = $form;
        }
        else {
            $this->view->notFound = $config_texts->notfound;
        }

        // pokud je formular validni, ulozime
        if (empty($_POST) || !$form->isValid($_POST)) {
            $this->view->form = $form;
        }
        else {
           if ($channels->saveChannel($_POST)) {
               $this->view->form = $config_texts->saved;
           }
           else {
               $this->view->form = $config_texts->notsaved;
           }
        }
    }

    /**
     * smaze kanal
     */
    public function channelsdeleteAction() {
        $config_texts = Ibulletin_Texts::getSet('admin.users.channels');
        $request = $this->getRequest();

        $_id = $request->id;
        $id = (!empty($_id)) ? intval($_id) : 0;

        $channels = new Channels();

        if ($channels->deleteChannel($id)) {
            $this->view->status = $config_texts->deleted;
        }
        else {
            $this->view->status = $config_texts->notdeleted;
        }
    }


    /**
     * Formular pro pridani nebo editaci kanalu
     * @param array data
     * @return Zend_Form formular
     */
    protected function _getChannelsForm($data = array())
    {

        $config_texts = Ibulletin_Texts::getSet('admin.users.segments');

        $form = new Form();
        $form->setMethod('post');

        // hidden id
        $formIdHidden = new Zend_Form_Element_Hidden('id');
        if (!empty($data['id'])) {
            $formIdHidden->setValue($data['id']);
        }
        $form->addElement($formIdHidden);

        // nazev
        $formName = new Zend_Form_Element_Text('name', array('size' => '35'));
        $formName->setLabel($config_texts->name);
        if (!empty($data['name'])) {
            $formName->setValue($data['name']);
        }

        //validatory pro nazev
        $nameValidatorLength = new Zend_Validate_StringLength(3);
        $nameValidatorLength->setMessages(array(
            Zend_Validate_StringLength::TOO_SHORT => $config_texts->validators->tooshort
            )
        );
        $formName->addValidator($nameValidatorLength);

        $nameValidatorEmpty = new Zend_Validate_NotEmpty();
        $nameValidatorEmpty->setMessages(array(
            Zend_Validate_NotEmpty::IS_EMPTY => $config_texts->validators->isempty
            )
        );
        $formName->addValidator($nameValidatorEmpty);

        $formName->setRequired(true);
        $form->addElement($formName);

        // kod, zda jde o web, ci kolegu
        $formCode = new Zend_Form_Element_Select('code');
        $formCode->setLabel('Kód kanálu');
        $formCode->setMultiOptions(array(
                '' => '- - -',
                'web' => $this->texts->code_web,
                'kolega' => $this->texts->code_colleague
        ));
        $form->addElement($formCode);
        if (!empty($data['code'])) {
            $formCode->setValue($data['code']);
        }

        //  csrf ochrana
        $form->addElement('hash', 'no_csrf', array('salt' => 'unique'));

        // odeslat
        $formSave = new Zend_Form_Element_Submit($config_texts->submit);
        $formSave->setAttrib('class','btn-primary');
        $form->addElement($formSave);

        return $form;
    }

    private function getPresentations() {
        $db = Zend_Registry::get('db');
        $select = "SELECT DISTINCT p.id, p.name,c.created FROM content AS c
                LEFT JOIN content_pages AS cp ON c.id = cp.content_id
                LEFT JOIN pages AS p ON cp.page_id = p.id
                WHERE c.class_name = 'Ibulletin_Content_Indetail' AND p.id IS NOT NULL
                ORDER BY c.created DESC";
        $rows = $db->fetchAll($select);

        $pres = array();

        foreach ($rows as $row) {
            $pres[$row['id']] = $row['name'];
        }

        return $pres;
    }

    public function getuserAction() {
         $db = Zend_Registry::get('db');

         if ($this->_hasParam('q')) {
             $q = $this->_getParam('q');
             $select = $db->select()->from('users',array('id','surname','name'))
                     ->where("(coalesce(surname, '') || ' ' || coalesce(name, '') ILIKE ?) OR (coalesce(name, '') || ' ' || coalesce(surname, '') ILIKE ?) OR (CAST(id AS TEXT) LIKE ?)",$q.'%')
                     ->order('id DESC');
             $rows = $db->fetchAll($select);
             echo json_encode($rows);
             exit();
         }



    }

    public function removeRepimgAction() {

        $id = $this->_getParam('id');

        if ($id) {
            try {
                $fileUploader = new Ibulletin_FileUploader(null, $this->config->mailer->imgrepaddr);
                $fileUploader->rmfile($id.'.png');
                $this->infoMessage($this->texts->edit->removeRepImgRemoved);
            } catch (Ibulletin_FileUploader_Exception $ex) {
                $this->infoMessage($ex->getMessage(), 'error');
            }
        }

        $this->redirect('edit', 'users','admin',array('id'=>$id));
    }


	/**
	 * Anonymizace uživatele
	 */
	public function anonymizationAction() {

		$id = $this->_getParam('id');

		if (!$id) {
			$this->redirectUri($this->getLastVisited());
		}

		//prida do menu odkaz editace a anonymizace
		$this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
		$this->moduleMenu->addItem($this->texts->anonymization->submenu_anonymization, array('action'=>'anonymization','id'=>$id), null, true,'anonymization');
		$this->moduleMenu->setCurrentLocation('anonymization');

		$form = $this->getAnonymizationForm();

		$user = Users::getUser($id);

		if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

			try {
				Users::AnonymizeUser($user);
				$this->infoMessage($this->texts->anonymization->success, 'success', array($id));
			} catch (Exception $e) {
				Phc_ErrorLog::warning('Admin::UsersController', $e);
				$this->infoMessage($this->texts->anonymization->error . $e->getMessage(), 'error', array($id));
			}

			$this->redirectUri($this->getLastVisited());

		}
		$this->view->user = $user;
		$this->view->form = $form;

	}

	/**
	 * Formular pro pridani nebo editaci segmentu
	 * @param array data
	 * @return Zend_Form formular
	 */
	protected function getAnonymizationForm() {

		$form = new Form();
		$form->setMethod('post');
		$form->setAttrib('class','anonymization_form');

		$form->addElement('hidden','id');


		$form->addElement('submit',$this->texts->anonymization->submit_form,array(
			'class' => 'btn btn-danger'
		));



		return $form;
	}

}
