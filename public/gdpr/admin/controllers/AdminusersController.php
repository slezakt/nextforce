<?php
/**
 * Modul pro spravu uzivatelu administrace.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 * @author Andrej Litvaj, <andrej.litvaj@pearshealthcyber.com>
 */

class Admin_AdminusersController extends Ibulletin_Admin_BaseControllerAbstract {

	/**
	 * overrides Ibulletin_Admin_BaseControllerAbstract::init()
	 */
	public function init() {
		parent::init();
		$this->submenuAll = array(
			'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
			'add' => array('title' => $this->texts->submenu_add, 'params' => array('action' => 'add'), 'noreset' => false),
		);
	}

    /**
     * aktualizuje uzivatele adminu
     *
     * @param string $name
     * @param array  $values
     * @return bool|null
     */
    public function updateRecord($name, $values) {
		switch ($name) {
			case "adminuser" :
				try {

					$auth = Ibulletin_AdminAuth::getInstance();
                    
                    $privileges = array_merge(
                                (array)$values['privileges_module'],
                                (array)$values['privileges_monitoring'],
                                (array)$values['privileges_config'],
                                (array)$values['privileges_additional']
                            );
                    
                    //kontrola zda lze priradit uzivateli prava - dle emailu
                    if (!Ibulletin_AdminAuth::canSetUserPrivilegesByEmail($privileges, $values['email'])) {
                       $this->infoMessage($this->texts->exc->notallowprivilege, 'error');
                        return false; 
                    }

					$res = $auth->editUser(
						$values['id'],
						$values['login'],
						$values['name'],
						$values['password'],
						$values['email'],
						$values['language'],
						$values['timezone']);

					if ($auth->hasPermission('module_adminusers')){
							$auth->manipulatePrivileges($privileges,'change',$values['id']);
					}

					return true;
				}
				catch (Ibulletin_AdminAuth_User_Not_Found_Exception $e) {
					$this->infoMessage(sprintf($this->texts->exc->notfound, $values['id']), 'error');
					return false;
				}

			default: return null;
		}
	}

    /**
     * vytvari uzivatele adminu
     *
     * @param string $name
     * @param array  $values
     * @return bool|int|null
     */
    public function createRecord($name, $values) {
		switch ($name) {
			case "adminuser":
				try {
                
                    $privileges = array_merge(
                                (array)$values['privileges_module'],
                                (array)$values['privileges_monitoring'],
                                (array)$values['privileges_config'],
                                (array)$values['privileges_additional']
                            );
                    
                    //kontrola zda lze priradit uzivateli prava - dle emailu
                    if (!Ibulletin_AdminAuth::canSetUserPrivilegesByEmail($privileges, $values['email'])) {
                       $this->infoMessage($this->texts->exc->notallowprivilege, 'error');
                        return false; 
                    }

					$auth = Ibulletin_AdminAuth::getInstance();
					if ($auth->hasPermission('module_adminusers')) {
						$id = Ibulletin_AdminAuth::addUser(
							$values['login'],
							$values['name'],
							$values['password']
                        );

                        if ($id) {
                            Ibulletin_AdminAuth::editUser($id,
                                $values['login'],
                                $values['name'],
                                $values['password'],
                                $values['email'],
                                $values['language'],
                                $values['timezone']);

                            Ibulletin_AdminAuth::manipulatePrivileges($privileges,'add',$id);

                        }

                        return $id;
					} else {
						$this->infoMessage($this->texts->exc->notpermitted, 'warning');
                        return false;
					}
				}
				catch (Ibulletin_AdminAuth_User_Duplicate_Login_Exception $e) {
					$this->infoMessage(sprintf($this->texts->exc->duplicate, $values['login']), 'error');
					return false;
				}

			default: return null;
		}
	}

    /**
     * smaze uzivatele adminu
     *
     * @param string $name
     * @param mixed  $id
     * @return bool|null
     */
    public function deleteRecord($name, $id) {
		switch ($name) {
			case "adminuser":
				try {
					$auth = Ibulletin_AdminAuth::getInstance();
					if ($auth->hasPermission('module_adminusers')) {
                        return Ibulletin_AdminAuth::removeUser($id);
					} else {
						$this->infoMessage($this->texts->exc->notpermitted, 'warning');
					}
				}
				catch (Ibulletin_AdminAuth_User_Self_Deleting_Exception $e) {
					$this->infoMessage(sprintf($this->texts->exc->selfdelete,$id), 'error');
					return false;
				}
                catch (Exception $e) {
                    $this->infoMessage($this->texts->exc->notpermitted, 'error');
                    return false;
                }
			default: return null;
		}
	}

    /**
     * vraci zaznam uzivatele adminu
     *
     * @param string $name
     * @param mixed  $id
     * @return array|bool|null
     */
    public function getRecord($name, $id) {
		switch ($name) {
			case "adminuser":
				try {
					$res = (array)Ibulletin_AdminAuth::getUser($id);
					// split privileges from comma-list into 2 arrays
					foreach (explode(',', $res['privileges']) as $v) {
						if (strpos($v, 'module_') === 0) $res['privileges_module'][] = $v;
						if (strpos($v, 'privilege_') === 0) $res['privileges_additional'][] = $v;
                        if (strpos($v, 'monitoring_') === 0) $res['privileges_monitoring'][] = $v;
                        if (strpos($v, 'config_') === 0) $res['privileges_config'][] = $v;
					}
					unset($res['privileges']);
					// select default timezone if none
					if (!$res['timezone']) $res['timezone'] = $this->config->general->default_timezone;
				}
				catch (Ibulletin_AdminAuth_User_Not_Found_Exception $e) {
					return false;
				}
				return $res;
			default: return null;
		}
	}

    /**
     * vraci seznam uzivatelu adminu
     *
     * @param string $name
     * @param array  $options
     * @return array|null
     */
    public function getRecords($name, $options = array()){
		switch ($name) {
			case "adminuser":
				return (array)Ibulletin_AdminAuth::getUsers();
			default: return null;
		}
	}

	/**
	 * vraci formular pro editaci/vytvoreni uzivatele adminu
     *
     * @overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
	 */
	public function getForm($name) {
		switch ($name) {
			case "adminuser":
				$form = parent::getForm($name);
				$form->setAttrib('autocomplete', 'off'); // stops browsers to auto prefill form

				// name
				$form->addElement('text', 'name', array(
					'label' => $this->texts->name,
					'order' => 1,
					'required' => true,
					'validators' => array(
						array('NotEmpty', true, array('messages' => array(
							Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
						))),
						array('Alnum', true, array('allowWhiteSpace' => true, 'messages' => array(
							Zend_Validate_Alnum::NOT_ALNUM => $this->texts->validators->notalpha,
						))),
						array('StringLength', true, array('min' => 3, 'max' => 60, 'messages' => array(
							Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
							Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
						))),
					),
					'filters' => array('StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
				));

				// login
				$form->addElement('text', 'login', array(
					'label' => $this->texts->login,
					'order' => 2,
					'required' => true,
					'validators' => array(
						array('NotEmpty', true, array('messages' => array(
							Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
						))),
						array('Alnum', true, array('messages' => array(
							Zend_Validate_Alnum::NOT_ALNUM => $this->texts->validators->notalnum,
						))),
						array('StringLength', true, array('min' => 3, 'max' => 60, 'messages' => array(
							Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
							Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong,
						))),
						array('Callback', true, array('callback' => array($this, 'isLoginUnique'), 'messages' => array(
							Zend_Validate_Callback::INVALID_VALUE => $this->texts->validators->loginnotunique,
						))),
					),
					'filters' => array('StringToLower','StringTrim',array('Null', array('type' => Zend_Filter_Null::STRING))),
				));

				// password
				$form->addElement('password', 'password', array(
					'label' => $this->texts->password,
					'order' => 4,
					'required' => true,
					'renderPassword' => true,
					'validators' => array(
						array('NotEmpty', true, array('messages' => array(
							Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
						))),
						array('StringLength', true, array('min' => 5, 'messages' => array(
							Zend_Validate_StringLength::TOO_SHORT => $this->texts->validators->tooshort,
						))),
						array('Regex', true, array('pattern' => '/^[-.*_\w\d]+$/', 'messages' => array(
							Zend_Validate_Regex::NOT_MATCH => $this->texts->validators->password,
						))),
					),
					'filters' => array(array('Null', array('type' => Zend_Filter_Null::STRING))),
				));

				// password_repeat
				$form->addElement('password', 'password_repeat', array(
					'label' => $this->texts->password_repeat,
					'order' => 5,
					'required' => true,
					'validators' => array(
						array('NotEmpty', true, array('messages' => array(
							Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
						))),
						array('Identical', true, array('token' => 'password', 'messages' => array(
							Zend_Validate_Identical::NOT_SAME => $this->texts->validators->passwordmismatch,
						))),
					),
					'filters' => array(array('Null', array('type' => Zend_Filter_Null::STRING))),
				));

                // email
                $form->addElement('text', 'email', array(
                    'label' => $this->texts->email,
                    'order' => 6,
                    'required' => true,
                    'validators' => array(
                         array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                        array('EmailAddress', true,array('messages' => array(
                        Zend_Validate_EmailAddress::INVALID_FORMAT => $this->texts->validators->invalid_email_format,
                        ))),
                    ),
                    'filters' => array('StringTrim','StringToLower',array('Null', array('type' => Zend_Filter_Null::STRING))),
                ));

                $form->addDisplayGroup(
                    array($form->getElement('name'), $form->getElement('email')), 'grp1', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                $form->addDisplayGroup(
                    array($form->getElement('login'), $form->getElement('password'), $form->getElement('password_repeat')), 'grp2', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                // language
                $form->addElement('select', 'language', array(
                    'label' => $this->texts->language,
                    'order' => 7,
                    'required' => false,
                    'multioptions' => $this->getLanguages(true),
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                    ),
                    'filters' => array('StringToLower','Null'
                    ),
                ));

                // timezone
                $form->addElement('select', 'timezone', array(
                    'label' => $this->texts->timezone,
                    'order' => 8,
                    'required' => true,
                    'multioptions' => $this->getTimezones(),
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                    ),
                ));

                $form->addDisplayGroup(
                    array($form->getElement('language'), $form->getElement('timezone')), 'grp3', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));


                $auth = Ibulletin_AdminAuth::getInstance();
                // potvrzeni hesla pouze pokud nemam opravneni editovat ostatnich nebo editujem sebe
                if (!$auth->hasPermission('module_adminusers')
                    || ($this->_request->getParam('id') == $auth->user_data->id)) {
                    // old-password
                    $form->addElement('password', 'password_old', array(
                        'label' => $this->texts->password_old,
                        'order' => 3,
                        'required' => true,
                        'renderPassword' => false,
                        'validators' => array(
                            array('NotEmpty', true, array('messages' => array(
                                Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                            ))),
                            array('Callback', true, array('callback' => array('Ibulletin_AdminAuth', 'checkPassword'), 'messages' => array(
                                Zend_Validate_Callback::INVALID_VALUE => $this->texts->validators->invalidpassword,
                            ))),
                        ),
                    ));
                }

                if ($auth->hasPermission('module_adminusers')) {

                    // module privileges
                    $form->addElement('multiCheckbox', 'privileges_module', array(
                        'label' => $this->texts->privileges_modules,
                        'order' => 9,
                        'label_class' => 'checkbox inline',
                        'separator' => '',
                        'multioptions' => $this->getPrivilegesModule(),
                    ));

                    $form->addDisplayGroup(
                        array($form->getElement('privileges_module')), 'grp4', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                    // Monitoring privileges
                    $form->addElement('multiCheckbox', 'privileges_monitoring', array(
                        'label' => $this->texts->privileges_monitoring,
                        'order' => 10,
                        'label_class' => 'checkbox inline',
                        'separator' => '',
                        'multioptions' => $this->getPrivilegesMonitoring(),
                    ));

                    $form->addDisplayGroup(
                        array($form->getElement('privileges_monitoring')), 'grp5', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                    // Config privileges
                    $form->addElement('multiCheckbox', 'privileges_config', array(
                        'label' => $this->texts->privileges_config,
                        'order' => 10,
                        'label_class' => 'checkbox inline',
                        'separator' => '',
                        'multioptions' => $this->getPrivilegesConfig(),
                    ));

                    $form->addDisplayGroup(
                        array($form->getElement('privileges_config')), 'grp6', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                    // additional privileges
                    $form->addElement('multiCheckbox', 'privileges_additional', array(
                        'label' => $this->texts->privileges_additional,
                        'order' => 11,
                        'separator' => '',
                        'label_class' => 'checkbox inline',
                        'multioptions' =>$this->getPrivilegesAdditional(),
                    ));

                    $form->addDisplayGroup(
                        array($form->getElement('privileges_additional')), 'grp7', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                    // Pri editaci sameho sebe si nesmi uzivatel odebrat privilege_all a module_adminusers
                    if ($this->_request->getParam('id') == $auth->user_data->id) {
                        $form->getElement('privileges_module')->setAttrib('disable', array('module_adminusers'));
                        $form->getElement('privileges_additional')->setAttrib('disable', array('privilege_all'));
                    }
                }

				return $form;
			default: return null;
		}
	}

    /**
     * formular pro vytvoreni uzivatele adminu
     *  nastavuji se defaultni hodnoty
     *
     * @param string $name
     * @return null|Zend_Form
     */
    public function getCreateForm($name) {
        switch ($name) {
            case "adminuser":
                $form = parent::getCreateForm($name);

                // default values, leave all modules checked, except adminusers and superuser
                /*
                $f = $form->getElement('privileges_module');
                $a = $f->getMultiOptions();
                unset($a['module_adminusers']);
                $form->setDefault($f->getName(), array_keys($a));
                */

                $f = $form->getElement('privileges_monitoring');
                $a = $f->getMultiOptions();
                $form->setDefault($f->getName(), array_keys($a));

                $f = $form->getElement('privileges_config');
                $a = $f->getMultiOptions();
                unset($a['config_advanced']);
                $form->setDefault($f->getName(), array_keys($a));

                /*
                $f = $form->getElement('privileges_additional');
                $a = $f->getMultiOptions();
                unset($a['privilege_all']);
                $form->setDefault($f->getName(), array_keys($a));
                */

                return $form;

            default: return null;
        }
    }

	/**
     * formular pro editaci uzivatale adminu
     *
	 * @overrides Ibulletin_Admin_BaseControllerAbstract::getUpdateForm($name)
	 */
	public function getUpdateForm($name) {
		switch ($name) {
			case "adminuser":
				$form = parent::getUpdateForm($name);
             	return $form;

			default: return null;
		}
	}

    /**
     * vraci seznam modulu
     *
     * @return mixed
     */
    public function getPrivilegesModule(){
        $t = Ibulletin_Texts::getSet('admin.module_names');
        foreach ($this->config->admin_modules_menu->toArray() as $section) {
            // add prefix module_ to keys
            foreach ($section as $k => $module) {
                // skip turned off modules and module settings
                if (!is_array($module) && $module == 1) {
                    $res['module_'.$k] = $t->{$k};
                }
            }
        }

		return $res;
	}

    /**
     * vraci seznam opravneni
     * @return mixed
     */
    public function getPrivilegesAdditional(){
        $t = Ibulletin_Texts::getSet('admin.module_privileges');
		// add prefix privilege_ to keys
		foreach ($this->config->admin_privileges->toArray() as $k => $v) {
			$res['privilege_'.$k] = $t->{$k};
		}
		return $res;
	}

    /**
     * Gets set of privileges for disabling and enabling monitoring modules.
     */
    public function getPrivilegesMonitoring()
    {
        // Prefix for monitoring privileges
        $prefix = 'monitoring_';

        // Find modules using a module menu of stats Controller
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        $response = $frontController->getResponse();
        require('admin/controllers/StatsController.php');
        $statsController = new Admin_StatsController($request, $response);
        // Set some nescessary atributes
        $statsController->texts = Ibulletin_Texts::getSet('admin.stats');
        $statsController->ignorePrivileges = true;
        $statsController->init();

        $privileges = array();
        foreach($statsController->submenuAll as $item){
            if(isset($item['params']['action'])){ // Skip menu items without action - we cannot handle such item as privilegue
                $privileges[$prefix.$item['params']['action']] = $item['title'];
            }
        }

        return $privileges;
    }

    /**
     * Gets set of privileges for disabling and enabling config modules.
     */
    public function getPrivilegesConfig()
    {
        // Prefix for config privileges
        $prefix = 'config_';

        // Find modules using a module menu of stats Controller
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        $response = $frontController->getResponse();
        require('admin/controllers/ConfigController.php');
        $ct = new Admin_ConfigController($request, $response);
        // Set some nescessary atributes
        $ct->texts = Ibulletin_Texts::getSet('admin.config');
        $ct->init();

        $privileges = array();
        foreach($ct->submenuAll as $item){
            if(isset($item['params']['action'])){ // Skip menu items without action - we cannot handle such item as privilegue
                $privileges[$prefix.$item['params']['action']] = $item['title'];
            }
        }

        return $privileges;
    }


    /**
     * callback pro overeni existence loginu
     *
     * @param      $login
     * @param null $context
     * @return bool
     */
    public function isLoginUnique($login, $context = null) {

		$found_id = Ibulletin_AdminAuth::userExists($login);
		return (!$found_id) || (isset($context['id']) && $context['id'] == $found_id);
	}

    /**
     * vraci seznam dostupnych jazyku
     *
     * @param bool $emptyAllowed
     * @return array
     */
    public function getLanguages($emptyAllowed = false) {

		$res = array();
		if ($emptyAllowed) $res=array('' => '- -');
		// Najdeme seznam dostupnych jazyku tak ze prescanujeme adresar textov
		$textsDir = Ibulletin_Texts::getTextsDir();
		$dir = dir($textsDir);
		while($file = $dir->read()){
			$m = array();
			if(preg_match('/^(.*)\.ini$/i', $file, $m)){
				$parts = explode('.', $m[1]);
				if(!isset($res[$parts[0]])){
					$res[$parts[0]] = $parts[0];
				}
			}
		}
		return $res;
	}

    /**
     * vraci seznam casovych zon
     *
     * @param bool $emptyAllowed
     * @return array
     */
    public function getTimezones($emptyAllowed = false) {

		$res = array();
		if ($emptyAllowed) $res=array('' => '- -');
		// pripojeni awaited_timezones
		foreach (explode(',',$this->config->general->awaited_timezones) as $tz) {
			$s = trim($tz);
			$res[$s] = $s;
		}
		if (method_exists('DateTimeZone','listIdentifiers')) {
			$list = DateTimeZone::listIdentifiers();
			foreach ($list as $v) {
				$res[$v] = $v;
			}
		} else {
			// failsafe query with avg time 12 sec
			$q = 'SELECT DISTINCT name FROM pg_timezone_names ORDER BY name';
			$listRows = $this->db->fetchAll($q);
			foreach($listRows as $row) {
				$res[$row['name']] = $row['name'];
			}
		}
		return $res;
	}

	/**
	 * zobrazi seznam uzivatelu adminu
	 */
	public function indexAction() {

		// Pokud uzivatel nema pravo pristupu do adminu uzivatelu, posleme ho na stranku editace sameho sebe
		$auth = Ibulletin_AdminAuth::getInstance();
		if (!$auth->hasPermission('module_adminusers')) {
            $this->redirect(array('action'=> 'edit', 'id' => $auth->user_data->id));
		}

        try {
            $grid = new Ibulletin_DataGrid(Ibulletin_AdminAuth::getUsersQuery());
            $grid->setDefaultSort('login');
            $grid->setDefaultDir('ASC');

            $imgs_yes_no = array(0 => 'remove-color', 1 => 'ok-color');
            
            $opts_bool = array(
                0 => $this->texts->option_no,
                1 => $this->texts->option_yes,
            );

            $def = array(
                'align'=>'left',
                'default' => '&lt;null&gt;',
            );

            $grid->addColumn('login', array(
                        'header' => $this->texts->login,
                        'field' => 'admin_users.login',
                        'filter' => array(
                            'autocomplete' => true,
                            'type' => 'expr',
                            'datatype' => 'string'
                        )
                    ))
                    ->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'admin_users.name',
                        'filter' => array(
                            'autocomplete' => true,
                            'type' => 'expr',
                            'datatype' => 'string'
                        )
                    ))
                    ->addColumn('language', array(
                        'header' => $this->texts->language,
                        'field' => 'admin_users.language',
                        'filter' => array(
                            'autocomplete' => true,
                            'type' => 'expr',
                            'datatype' => 'string'
                        )
                    ))
                     ->addColumn('email', array(
                        'header' => $this->texts->email,
                        'field' => 'users.email',
                        'filter' => array(
                            'autocomplete' => true,
                            'type' => 'expr',
                            'datatype' => 'string'
                        )
                    ))
                      ->addColumn('superuser', array(
                        'header' => $this->texts->superuser,
                        'field' => 'superuser',
                        'imgs' => $imgs_yes_no,
                        'align' => 'center'
                    ))
                    ->addColumn('deleted', array_merge($def, array(
                        'header' => $this->texts->deleted,
                        'type' => 'datetime',
                        'field' => 'deleted',
                        'filter' => array(
                            'type' => 'query',
                            'query' => 'CASE WHEN ? = 0 THEN admin_users.deleted IS NULL ELSE admin_users.deleted IS NOT NULL END',
                            'datatype' => 'datetime',
                            'options' => $opts_bool,
                        )
                    )))
                    ->addAction('delete', array(
                        'empty' => 'del',
                        'confirm' => $this->texts->confirm_delete,
                        'url' => $this->_helper->url('delete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                    ->addAction('renew', array(
                        'empty' => 'deleted',
                        'confirm' => $this->texts->confirm_renew,
                        'url' => $this->_helper->url('renew') . '/id/$id/',
                        'caption' => $this->texts->action_renew,
                        'image' => 'refresh'
                    ))
                    ->addAction('edit', array(
                        'url' => $this->_helper->url('edit') . '/id/$id/',
                        'caption' => $this->texts->action_edit,
                        'image' => 'pencil'
            ));

            $this->view->grid = $grid->process();
        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }
    }

	/**
	 * zobrazi formular pro pridani
	 */
	public function addAction() {

		// Pokud uzivatel nema pravo pristupu do adminu uzivatelu, posleme ho na stranku editace sameho sebe
		$auth = Ibulletin_AdminAuth::getInstance();
		if (!$auth->hasPermission('module_adminusers')) {
			$this->redirect(array('action'=> 'edit', 'id' => $auth->user_data->id));
		}
		$this->setAfterCreateUrl('adminuser','index');
		$this->view->form = $this->processCreate('adminuser');;
	}

    /**
     * callback po upravu formulare pred zobrazenim v zavislosti od postovanych dat
     *
     * @param $name
     * @param $form
     * @param $record
     * @return null
     */
    public function cbUpdate($name, $form, $record) {
        switch ($name) {
            case "adminuser" :
                // pokud se nezadalo nove heslo, vypneme required u vsech poli pro hesla
                if (!strval($this->_request->getParam('password'))) {
                    $form->getElement('password')->setRequired(false);
                    $form->getElement('password_repeat')->setRequired(false);
                    if ($form->getElement('password_old'))
                        $form->getElement('password_old')->setRequired(false);
                } else { //zapneme rendrovani stareho hesla, aby pri nevalidnim formu mizlo pouze opakovani noveho hesla
                    if ($form->getElement('password_old'))
                        $form->getElement('password_old')->setAttrib('renderPassword', true);
                }
            default: return null;
        }
    }

    /**
	 * zobrazi formular pro editaci
	 */
	public function editAction() {

		$id = $this->_request->getParam('id');

        // missing id
        if (!$id) {
			$this->redirect('index');
		}

        // Pokud uzivatel ma pravo pristupu do adminu uzivatelu, po ulozeni redirectujeme na list
        $auth = Ibulletin_AdminAuth::getInstance();
        if ($auth->hasPermission('module_adminusers')) {
            $this->setAfterUpdateUrl('adminuser', 'index');
        } else {
            $this->moduleMenu->removeItem('index');
            $this->moduleMenu->removeItem('add');
            $this->setAfterUpdateUrl('adminuser', array('action' => 'edit','id' => $id));
        }

        //prida do menu odkaz editace
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        $this->setBeforeUpdate(array($this, 'cbUpdate'));

       // $this->setAfterUpdateUrl('adminuser', 'index');
		$this->view->form = $this->processUpdate('adminuser', $id);
	}

	/**
	 * smaze uzivatele adminu
	 */
	public function deleteAction() {

		$id = $this->_request->getParam('id');
        $auth = Ibulletin_AdminAuth::getInstance();

		// missing id, nema dostatecna prava, selfdelete
        if (!$id || !$auth->hasPermission('module_adminusers') || $id == $auth->user_data->id) {
			$this->redirect('index');
		}

		$this->setAfterDeleteUrl('adminuser','index');
		$this->processDelete('adminuser', $id);
	}
    
    /**
     * akce pro obnoveni uzivatele adminu
     */
    public function renewAction() {

        $id = $this->_getParam('id');
        $user =  Ibulletin_AdminAuth::getUser($id);
        $result = true;
        try {
            
            Ibulletin_AdminAuth::restoreUser($id);
            $this->infoMessage($this->texts->renew->success, 'success', array($user['name']));
        } catch (Exception $e) {
            $result = false;
            Phc_ErrorLog::warning('Admin::AdminusersController', $e);
            $this->infoMessage($this->texts->renew->error, 'error', array($user['name']));
        }

        $this->redirect('index');
    }

}
