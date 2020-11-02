<?php

/**
 *	Adminstrace emailů.
 *
 * 	@author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_MailsController extends Ibulletin_Admin_BaseControllerAbstract {

	/**
	 * Pole a prekladova tabulka typu specialni funkce mailu.
	 * @var Array
	 */
	public $special_types;


    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
    public function init() {
        parent::init();
        
        if ($this->config->mails->show_standard_emails) {
            $this->submenuAll['index'] = array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false);
        }
        $this->submenuAll += array(
            //'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'templates' => array('title' => $this->texts->submenu_templates, 'params' => array('action' => 'templates'), 'noreset' => false),
            'deleted' => array('title' => $this->texts->submenu_deleted, 'params' => array('action' => 'deleted'), 'noreset' => false)
        );

        $this->special_types = array(
        0 => $this->texts->type_none,
            'registration' => $this->texts->type_registration,
            'registrationlogin' => $this->texts->type_registrationlogin,
            'deregistration' => $this->texts->type_deregistration,
            'change' => $this->texts->type_change,
            'forward' => $this->texts->type_forward,
            'forgotpass' => $this->texts->type_forgotpass,
            'inrep' =>  $this->texts->type_inrep,
            'inrep_resources' =>  $this->texts->type_inrepresources,
            'certificate' => $this->texts->type_certificate
        );
    }

    /**
     * TODO: duplicitni s TemplatesController::getEmailTemplates(), patri do modelu
     * TODO: zrusit vytvareni prazdneho adresare (patri do SVN nebo setmods.sh)
     *
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
                //jestlize existuje soubor s koncovkou html
                if (is_file($path) && preg_match('/\.html$/', $fileinfo->getFilename())) {
                    // strip extension, remove underscores, uppercase words
                    $name = ucwords(preg_replace(array('/^(.*)\..*$/', '/_/'), array('\1', ' '), $fileinfo->getBasename()));
                    $id++;
                    $resources = preg_replace('/\.html$/','',$path);
                    $res[] = array(
                        'id' => $id,
                        'name' => $name,
                        'html_template' => $path,
                        'plain_template' => preg_replace('/\.html$/', '.txt', $path),
                        'resources'=>$resources
                    );
                }
            }
        }

        return $res;
    }

    /**
     * vraci seznam souboru pro selectbox
     * volitelne lze nastavit filter
     * @param $id int id emailu
     * @param $filter null|string regularni vyraz pro matchovani s nazvu souboru
     * @return array|boolean FALSE on error
     */
    public function getTemplateEmailFiles($id, $filter = null) {

        $res = array();

        $config = Zend_Registry::get('config');
        $path = rtrim($config->mailer->imgsaddr,'\\/') . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'email-files';

        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        } catch(Exception $ex) {
            $this->infoMessage($ex->getMessage(),'error');
            return false;
        }
        $id = 0;
        while ($it->valid()) {

            // pouze soubory, pripadne splnujici filter
            if ($it->isDot()
                || !$it->isFile()
                || ($filter && preg_match('/'.preg_quote($filter).'/', $it->getSubPathName()))) {
                $it->next();
                continue;
            }

            //ziska nazev souboru
            $path = $it->key();
            $res['email-files' . DIRECTORY_SEPARATOR . $it->getSubPathName()] = $it->getSubPathName();
            $it->next();
        }

        return $res;

    }

    /**
     * vraci seznam vsech dostupnych linku
     * @return array
     */
    public function getLinks() {
        $res = array();

        $q = 'SELECT l.id, l.name FROM links l WHERE page_id IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $page_links = $this->db->fetchPairs($q);

        $q = 'SELECT l.id, l.name FROM links l WHERE category_id IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $category_links = $this->db->fetchPairs($q);

        $q = 'SELECT l.id, l.name FROM links l WHERE bulletin_id IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $bulletin_links = $this->db->fetchPairs($q);

        $q = 'SELECT l.id, l.name FROM links l WHERE foreign_url IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $external_links = $this->db->fetchPairs($q);

        $q = 'SELECT l.id, l.name FROM links l WHERE resource_id IS NOT NULL AND deleted IS NULL ORDER BY l.id desc';
        $resources = $this->db->fetchPairs($q);

        $res = array(
            'pages' => $page_links,
            'categories' => $category_links,
            'issues' => $bulletin_links,
            'external links' => $external_links,
            'resources' => $resources
        );

        return $res;
    }

  	/**
	 * @see Ibulletin_Admin_CRUDControllerAbstract::createRecord()
	 */
	public function createRecord($name, $values) {
		switch ($name) {
            case "mail":
				try {
					$mails = new Ibulletin_Mails($this->config, $this->db);
					$last_insert_id = $mails->newMail($values['name']);
					return (boolean)$last_insert_id;

				} catch (Ibulletin_MailsException $e) {
					Phc_ErrorLog::error('Mails admin', $e);
					return false;
				}

            case "template":
                try {

                    $mails = new Ibulletin_Mails($this->config, $this->db);
                    $templates = $this->getEmailTemplates();

                    $page = Utils::array_kv_search($templates, 'id', $values['template']);
                    if (!empty($page)) {
                        $page = array_pop($page); // last matching page
                        // detect filename without extension
                        $tpl_filename = pathinfo($page['html_template'], PATHINFO_FILENAME);

                        // insert in DB, set template to basename file
                        $last_insert_id = $mails->newMail($values['name'], $tpl_filename);
                        return (boolean)$last_insert_id;
                    } else {
                        return false;
                    }


                } catch (Ibulletin_MailsException $e) {
                    Phc_ErrorLog::error('Mails admin', $e);
                    return false;
                }

            default: return null;
		}
	}

    /**
     *
     * uklada email
     *
     * @param string $name
     * @param array  $values
     * @return bool|null
     */
    public function updateRecord($name, $values) {
		$texts = IBulletin_Texts::getSet();
            switch ($name) {
			case "mail" :

				try {
					$mails = new Ibulletin_Mails($this->config, $this->db);
                    $from = $values['from'] == $this->config->mailer->from ? null : $values['from'];
                    $fromName = $values['from_name'] == $this->config->mailer->fromName ? null : $values['from_name'];
					$errors = $mails->saveMail($values['id'], $values['name'], $values['wave'],
                            $values['subject'], $values['body'], $values['plain'], $values['special_type'],
                            null, null, $from, $fromName);

                    //odebrání příznaku inrep_resources
                    if ($values['special_type'] == 'inrep_resources') {
                        $inrep = $mails->clearInRepResources($values['id']);
                        if ($inrep)
                            $this->infoMessage($texts->remove_inrepresources, "info", array($inrep));
                    }

					if ($errors) {
						foreach ($errors as $error) {
							$this->infoMessage($error,'error');
						}
						//return false;
					}

                     // odesleme testovaci email ?
                    if ($errors) {
                        $this->infoMessage($texts->test_mail_notsent,"error");
                        return true; // v pripade chyb neodesilame testovaci email
                    } else if (isset($values["testmail_send"]) && $values["testmail_send"] != 0) {
                        if ($values['testmail_target'] == 0 || empty($values['testmail_target'])) {
                            // neni komu posilat
                            return true; // nepovazujeme neposlani testovaciho emailu za neuspech ulozeni emailu
                        }
                        $values['testmail_target'] = is_array($values['testmail_target']) ? $values['testmail_target'] : array($values['testmail_target']);
                        $res = true;
                        foreach ($values['testmail_target'] as $m) {
                            $res = $res && $this->sendTestMail($values['id'], $m);
                        }
                        if ($res) {
                            $this->infoMessage($texts->test_mail_sent);
                            return true;
                        } else {
                            $this->infoMessage($texts->test_mail_notsent,"error");
                            return true; // nepovazujeme neposlani testovaciho emailu za neuspech ulozeni emailu
                        }

                    }

				} catch (Exception $e) {
					$this->infoMessage($e->getMessage(),'error');
					Phc_ErrorLog::error('MailsController', $e);
					return false;
				}

				return true;
            case "template":
                // ulozi se zmeneny mail
                try {
                    $mails = new Ibulletin_Mails($this->config, $this->db);

                    // parse HTML body of template
                    $html_template = rtrim($this->config->paths->mail_template,'\\/')
                        . DIRECTORY_SEPARATOR . $values['template'] . '.html';

                    $values['template_data'] = !empty($values['template_data']) ?  $values['template_data']: array();

                    $values['body'] = Templates::parseEmailTemplateFile($html_template, $values['template_data']);

                    // parse PLAIN body of template
                    $plain_template = rtrim($this->config->paths->mail_template,'\\/')
                        . DIRECTORY_SEPARATOR . $values['template'] . '.txt';

                    $values['plain'] = Templates::parseEmailTemplateFile($plain_template , $values['template_data']);

                    $from = $values['from'] == $this->config->mailer->from ? null : $values['from'];
                    $fromName = $values['from_name'] == $this->config->mailer->fromName ? null : $values['from_name'];

                    // save to DB, add template and template_data
                    $errors = $mails->saveMail($values['id'], $values['name'], $values['wave'],
                        $values['subject'], $values['body'], $values['plain'], $values['special_type'],
                        $values['template'],$values['template_data'], $from, $fromName);

                    //odebrání příznaku inrep_resources
                    if ($values['special_type'] == 'inrep_resources') {
                        $inrep = $mails->clearInRepResources($values['id']);
                        if ($inrep)
                            $this->infoMessage($texts->remove_inrepresources, "info", array($inrep));
                    }

                    if ($errors) {
                        foreach ($errors as $error) {
                            $this->infoMessage($error,'error');
                        }
                        //return false;
                    }

                    // odesleme testovaci email ?
                    if ($errors) {
                        $this->infoMessage($texts->test_mail_notsent,"error");
                        return true; // v pripade chyb neodesilame testovaci email
                    } else if (isset($values["testmail_send"]) && $values["testmail_send"] != 0) {
                        if ($values['testmail_target'] == 0 || empty($values['testmail_target'])) {
                            // neni komu posilat
                            return true; // nepovazujeme neposlani testovaciho emailu za neuspech ulozeni emailu
                        }
                        $values['testmail_target'] = is_array($values['testmail_target']) ? $values['testmail_target'] : array($values['testmail_target']);
                        $res = true;
                        foreach ($values['testmail_target'] as $m) {
                            $res = $res && $this->sendTestMail($values['id'], $m);
                        }
                        if ($res) {
                            $this->infoMessage($texts->test_mail_sent);
                            return true;
                        } else {
                            $this->infoMessage($texts->test_mail_notsent,"error");
                            return true; // nepovazujeme neposlani testovaciho emailu za neuspech ulozeni emailu
                        }

                    }

                } catch (Exception $e) {
                    $this->infoMessage($e->getMessage(),'error');
                    Phc_ErrorLog::error('MailsController', $e);
                    return false;
                }

                return true;
			default: return null;
		}
	}

	public function getRecord($name, $id) {
		switch ($name) {
			case "mail":
				try {
					$mails = new Ibulletin_Mails($this->config, $this->db);
					$mail =   $mails->getMailData($id); // Zend_Db_Table_Row

					if (empty($mail)) {
						return false;
					}
					$res = array();
					$res['id'] = (int)$mail->id;
					$res['name'] = (string)$mail->name;
                    $res['from'] = is_null($mail->body->getFrom()) ? $this->config->mailer->from : $mail->body->getFrom();
                    $res['from_name'] = is_null($mail->body->getFromName()) ? $this->config->mailer->fromName : $mail->body->getFromName();
					$res['wave'] = (int)$mail->invitation_id;
					$res['subject'] = (string)$mail->subject;
                    $cnt_html = $mail->body->getBody();
                    $cnt_plain = $mail->body->getPlain();
                    $res['body'] = !empty($cnt_html) ? $cnt_html : '';
                    $res['plain'] = !empty($cnt_plain) ? $cnt_plain : '';
					$res['special_type'] = $this->getMailSpecialType($mail);
					$res['send_test_mail'] = false;
					$res['spam'] = $mail->body->getSpamReport();
					return $res;

				} catch (Exception $e) {
					$this->infoMessage($e->getMessage(),'error');
					Phc_ErrorLog::error('MailsController', $e);
					return false;
				}
            case "template":
                try {
                    $mails = new Ibulletin_Mails($this->config, $this->db);
                    $mail = $mails->getMailData($id); // Zend_Db_Table_Row

                    if (empty($mail)) {
                        return false;
                    }

                    $res = array();
                    $res['id'] = (int)$mail->id;
                    $res['name'] = (string)$mail->name;
                    $res['from'] = is_null($mail->body->getFrom()) ? $this->config->mailer->from : $mail->body->getFrom();
                    $res['from_name'] = is_null($mail->body->getFromName()) ? $this->config->mailer->fromName : $mail->body->getFromName();
                    $res['wave'] = (int)$mail->invitation_id;
                    $res['subject'] = (string)$mail->subject;

                    $cnt_html = $mail->body->getBody();
                    $cnt_plain = $mail->body->getPlain();
                    $res['body'] = !empty($cnt_html) ? $cnt_html : '';
                    $res['plain'] = !empty($cnt_plain) ? $cnt_plain : '';

                    $res['special_type'] = $this->getMailSpecialType($mail);
                    $res['template'] = $mail->template;
                    $res['template_data'] = isset($mail->template_data) ? $mail->template_data['data'] : array();
                    $res['template_hash'] =isset($mail->template_data) ? $mail->template_data['hash'] : '';
                    $res['send_test_mail'] = false;
                    $res['spam'] = $mail->body->getSpamReport();

                    return $res;

                } catch (Exception $e) {
                    $this->infoMessage($e->getMessage(),'error');
                    Phc_ErrorLog::error('MailsController', $e);
                    return false;
                }
			default: return null;
		}
	}

	/**
	 *	vrací zakladni spolecny formulář
	 */
	public function getForm($name) {

		switch ($name) {
			case "mail":
            case "template":
                $form = parent::getForm($name);

				$name = new Zend_Form_Element_Text(array(
					'name' => 'name',
					'label' => $this->texts->name,
					'filters' => array('StringTrim'),
					'required' =>true,
					'size' => 40,
				));
				$name->addValidator('NotEmpty', true, array(
					'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
				));
                
                $name->addValidator('StringLength',true, array('max'=>100,'messages'=>array(Zend_Validate_StringLength::TOO_LONG => $this->texts->validators->toolong)));

				$form->addElements(array(
				    $name
				));

            return $form;

			default: return null;
		}
	}

    /**
     * vrací formulář pro vytvoření nového emailu. (obsahuje elementy z getForm()).
     * @see Ibulletin_Admin_CRUDControllerAbstract::getCreateForm()
     */
    public function getCreateForm($name) {

        $form = parent::getCreateForm($name);
        // custom form setup
        $form->setFormInline(true);

        switch ($name) {
            case "template":

                // template picker, filter 'name' field as value for selectbox
                $options = array();
                foreach ($this->getEmailTemplates() as $k => $v) {
                    $options[$v['id']] = $v['name'];
                }

                $form->addElement('select', 'template', array(
                    'name' => 'template',
                    'label' => $this->texts->template_name,
                    'multioptions' => $options,
                    'required' =>true,
                ));

                return $form;
            case "mail":

                return $form;
            default: return null;
        }
    }

	/**
	 * vrací formulář pro editaci emailu (obsahuje elementy z getForm()).
	 * @see Ibulletin_Admin_CRUDControllerAbstract::getUpdateForm()
	 */

	public function getUpdateForm($name) {

		$texts = Ibulletin_Texts::getSet();
		$form = parent::getUpdateForm($name);

		switch ($name) {
			case "mail":
				// zvaci vlna
				$iw = new Ibulletin_InvitationWaves($this->config, $this->db);
				$waves = $iw->getInvitationWavesSelect(true, true, $texts->empty_wave);

				$wave = new Zend_Form_Element_Select(array(
					'name' => 'wave',
					'label' => $texts->wave,
					'filters' => array('Null'),
					'multioptions' => $waves
				));

				// Specialni funkce
				$special_type = new Zend_Form_Element_Select(array(
					'name' => 'special_type',
					'label' => $texts->special_type,
					'filters' => array('Null'),
					'multioptions' => $this->special_types
				));

				// predmet
				$subject = new Zend_Form_Element_Text(array(
					'name' => 'subject',
					'label' => $texts->subject,
					'class' => 'span5',
					'filters' => array('StringTrim'),
					'required' => true
				));
				$subject->addValidator('NotEmpty', true, array(
					'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
				));

				// telo
				$body = new Zend_Form_Element_Textarea(array(
					'name' => 'body',
					'label' => $texts->body,
					'class' => 'editarea'
				));

				// textova cast emailu
				$plain = new Zend_Form_Element_Textarea(array(
					'name' => 'plain',
					'label' => $texts->plain,
					'attribs' => array('class' => 'span7', 'rows' => 40)
				));

                $testmail_send = new Zend_Form_Element_Radio('testmail_send');
                $testmail_send->addMultiOptions(array(0=>$texts->testmail_nosend,1=>$texts->testmail_send,2=>$texts->testmail_group))
                                ->setSeparator('')
                                ->setValue(0);
                $testmail_send->setAttrib('label_class','inline radio');

                // seznam emailu a defaultne zvoleny email pro posilani testovacich emailu
                $users = new Users();
                $selusers = $users->getListTestMail();
                $default = ($uid = Ibulletin_AdminAuth::hasFrontendUser()) && isset($selusers[$uid]) ? $uid : 0;

                $testmail_target = new Zend_Form_Element_Multiselect('testmail_target[]',array(
                    'multiOptions' => $selusers,
                    'value' => $default,
                    'decorators' => array(
                        'ViewHelper',
                        array('HtmlTag',array('tag'=>'div'))
                    )
                ));

                $from = new Zend_Form_Element_Text(array(
                    'name' => 'from',
                    'label' => $this->texts->from,
                    'filters' => array('StringTrim' ,'Null'),
                    'validators' => array('EmailAddress'),
                    'required' =>false,
                    'size' => 40,
                ));

                $from_name = new Zend_Form_Element_Text(array(
                    'name' => 'from_name',
                    'label' => $this->texts->from_name,
                    'filters' => array('StringTrim' ,'Null'),
                    'required' =>false,
                    'size' => 40,
                ));

                $form->addElements(array($from, $from_name, $wave,$special_type,$subject,$body,$plain));

                $form->addDisplayGroup(
                        array($form->getElement('name'),$wave),
                        'grp1',
                        array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>1));

                $form->addDisplayGroup(
                    array($from,$from_name),
                    'grp2',
                    array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>2));


                $form->addDisplayGroup(
                        array($testmail_send,$testmail_target),
                        'testmail',
                        array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                $testmail = $form->getDisplayGroup('testmail');
                $testmail->setLegend($texts->testmail);

                $testmail_send->removeDecorator('label');
                $links = new Links();
                $form->getElement('body')->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml','templates'=>$this->loadTemplates(),'links'=>$links->getSortedLinks()))));


				return $form;

/* SABLONOVY EMAIL */

            case "template":

                $iw = new Ibulletin_InvitationWaves($this->config, $this->db);
                $waves = $iw->getInvitationWavesSelect(true, true, $texts->empty_wave);

                $wave = new Zend_Form_Element_Select(array(
                    'name' => 'wave',
                    'label' => $texts->wave,
                    'filters' => array('Null'),
                    'multioptions' => $waves
                ));

                // Specialni funkce
                $special_type = new Zend_Form_Element_Select(array(
                    'name' => 'special_type',
                    'label' => $texts->special_type,
                    'filters' => array('Null'),
                    'multioptions' => $this->special_types
                ));

                // predmet
                $subject = new Zend_Form_Element_Text(array(
                    'name' => 'subject',
                    'label' => $texts->subject,
                    'class' => 'span5',
                    'filters' => array('StringTrim'),
                    'required' => true
                ));
                $subject->addValidator('NotEmpty', true, array(
                    'messages' => array(Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty)
                ));

                // telo
                $body = new Zend_Form_Element_Hidden(array(
                    'name' => 'body',
                ));

                // textova cast emailu
                $plain = new Zend_Form_Element_Hidden(array(
                    'name' => 'plain',
                ));

                // sablona ktera generuje email
                $template = new Zend_Form_Element_Hidden(array(
                    'name' => 'template',
                ));

                $from = new Zend_Form_Element_Text(array(
                    'name' => 'from',
                    'label' => $this->texts->from,
                    'filters' => array('StringTrim' ,'Null'),
                    'validators' => array('EmailAddress'),
                    'required' =>false,
                    'size' => 40,
                ));

                $from_name = new Zend_Form_Element_Text(array(
                    'name' => 'from_name',
                    'label' => $this->texts->from_name,
                    'filters' => array('StringTrim' ,'Null'),
                    'required' =>false,
                    'size' => 40,
                ));

                $form->addElements(array($from, $from_name, $wave,$special_type,$subject,$body,$plain,$template));

                $form->addDisplayGroup(
                    array($from,$from_name),
                    'grp2',
                    array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>2));

                // vytahneme sablonu podle ktere formular generujeme
                $tpl = $this->getRecord('template', $this->_getParam('id'));

                // parsujeme HTML sablonu
                $html_template = rtrim($this->config->paths->mail_template,'\\/')
                    . DIRECTORY_SEPARATOR. $tpl['template'].'.html';
                // parsujeme plain text sablonu
                $plain_template = rtrim($this->config->paths->mail_template,'\\/')
                    . DIRECTORY_SEPARATOR. $tpl['template'].'.txt';

                $html_struct = Templates::parseEmailTemplateFile($html_template);
                $plain_struct = Templates::parseEmailTemplateFile($plain_template);

                $template_struct = ($html_struct || $plain_struct) ? array_merge((array)$html_struct, (array)$plain_struct) : array();

                // nacteme soubory prilozene k emailu
                $options_pictures = array(0=>$this->texts->notselected) + (array)$this->getTemplateEmailFiles(
                    $this->_getParam('id'), '\.[jJ][pP][eE]?[gG]$|\.[pP][nN][gG]$|[gG][iI][fF]$');

                // nacteme linky
                $options_links = array(0=>$this->texts->notselected) + (array)$this->getLinks();

                // dynamicke vytvareni fieldu podle typu
                $i = 0;
                $elms = array();

                foreach ($template_struct as $v) {

                    // klic daneho elementu
                    $key = $v['key'];

                    switch ($v['type']) {
                        case 'text':
                            $el = new Zend_Form_Element_Text($key, array(
                                'label' =>$v['label'],
                            ));
                            break;
                        case 'textarea':
                            $el = new Zend_Form_Element_Textarea($key, array(
                                'label' =>$v['label'],
                                'rows' => 6,
                                'grid' => 8,
                                'class' => 'wysiwig'
                            ));
                            break;
                        case 'img':
                            $el = new Zend_Form_Element_Select($key, array(
                                'label' =>$v['label'],
                                'multioptions' => $options_pictures,
                            ));
                            break;
                        case 'link':
                            $el = new Zend_Form_Element_Select($key, array(
                                'label' =>$v['label'],
                                'disable-picker' => true,
                                'multioptions' => $options_links,
                                'class' => 'form-linkpicker'
                            ));
                            break;
                    }

                    // nastaveni array klice pro template elementy
                    $el->setBelongsTo('template_data');

                    // kategorizace podle groupy
                    $group = $v['group'] ? $v['group'] : 'group_' . $i++;
                    $elms[$group][] = $el;
                }

                foreach ($elms as $group_name => $g) {
                    // pridani elementu
                    foreach ($g as $elm) {$form->addElement($elm);}
                    // nastaveni display groupy
                    $form->addDisplayGroup($g, Utils::slugify($group_name).'-grp', // Je nutne suffixovat interni nazev skupiny, protoze treba skupina 'body' zrejme kolidovala s nejakym internim nazvem Zendu
                        array(
                            'legend' => preg_match('/^group_/',$group_name) ? '' : $group_name,
                            'displayGroupClass' => 'Form_DisplayGroup_Inline'
                        )
                    );

                }


                // testovaci email
                $testmail_send = new Zend_Form_Element_Radio('testmail_send');
                $testmail_send->addMultiOptions(array(0=>$texts->testmail_nosend,1=>$texts->testmail_send, 2=>$texts->testmail_group))
                    ->setSeparator('')
                    ->setValue(0);
                $testmail_send->setAttrib('label_class','inline radio');
                $testmail_send->setAttrib('label_style','margin-bottom: 10px');


                // seznam emailu a defaultne zvoleny email pro posilani testovacich emailu
                $users = new Users();
                $selusers = $users->getListTestMail();
                $default = ($uid = Ibulletin_AdminAuth::hasFrontendUser()) && isset($selusers[$uid]) ? $uid : 0;

                $testmail_target = new Zend_Form_Element_Multiselect('testmail_target[]',array(
                    'multiOptions' => $selusers,
                    'value' => $default,
                    'decorators' => array(
                        'ViewHelper',
                        array('HtmlTag',array('tag'=>'div'))
                    )
                ));

                $form->addDisplayGroup(
                    array($form->getElement('name'),$wave),
                    'grp1',
                    array('displayGroupClass' => 'Form_DisplayGroup_Inline','order'=>1));

                $form->addDisplayGroup(
                    array($testmail_send,$testmail_target),
                    'testmail',
                    array('displayGroupClass' => 'Form_DisplayGroup_Inline'));

                $testmail = $form->getDisplayGroup('testmail');
                $testmail->setLegend($texts->testmail);

                $testmail_send->removeDecorator('label');

                // ziskani hashe souboru sablony a poslani hlasky o zmene
                $html_template = rtrim($this->config->paths->mail_template,'\\/') . DIRECTORY_SEPARATOR . $tpl['template'] . '.html';
                $plain_template = rtrim($this->config->paths->mail_template,'\\/') . DIRECTORY_SEPARATOR . $tpl['template'] . '.txt';

                if (file_exists($html_template) && file_exists($plain_template)) {
                    $hash = Utils::getFileChecksum(array($html_template, $plain_template));
                    if ($hash != $tpl['template_hash']) {
                        $this->infoMessage($this->texts->invalid_hash,'warning');
                    }
                }

                return $form;

			default: return null;
		}
	}

	/**
	 * seznam emailu a formular pro vytvoreni noveho emailu
	 */
	 public function indexAction() {
        $config = Zend_Registry::get('config');
        $texts = Ibulletin_Texts::getSet();

        if (!$this->config->mails->show_standard_emails) {
            $this->redirect('templates');
        }

        $this->setLastVisited();

        // vytahneme seznam emailu
        $mails = new Ibulletin_Mails($this->config, $this->db);
        try {
            $grid = new Ibulletin_DataGrid($mails->getMailsQuery());
            $grid->setEmptyText($texts->empty);
            $grid->setDefaultSort('id');
            $grid->addColumn('id', array(
                        'header' => $this->texts->id
                    ))
                    ->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'e.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    ))
                    ->addColumn('subject', array(
                        'header' => $this->texts->subject,
                        'field' => 'e.subject',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    ))
                    ->addColumn('special_function', array(
                        'header' => $this->texts->type,
                        'width' => "100px",
                        'field' => 'special_function',
                        'type' => 'options',
                        'options' => $this->special_types
                    ))
                    ->addColumn('invitation_name', array(
                        'header' => $this->texts->invitation_name,
                        'width' => "100px",
                        'field' => 'iw.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true)
                    ))
                    ->addAction('delete', array(
                        'confirm' => $this->texts->confirm_delete,
                        'url' => $this->_helper->url('delete') . '/id/$id/',
                        'caption' => $this->texts->action_delete,
                        'image' => 'remove'
                    ))
                    ->addAction('duplicate', array(
                        'confirm' => $this->texts->confirm_duplicate,
                        'url' => $this->_helper->url('duplicate') . '/id/$id/',
                        'caption' => $this->texts->action_duplicate,
                        'image' => 'clone'
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

        // spracujeme formular
        $this->setAfterCreateUrl('mail', 'index');
        $form = $this->processCreate('mail');

        // jeste zkontrolujeme, zda je nastaven nejaky email jako
        // registracni, jinak zobrazime hlasku
        $q = 'SELECT id FROM emails WHERE send_after_registration AND deleted IS NULL  ORDER BY id DESC LIMIT 1';
        $result = $this->db->fetchRow($q);
        if (empty($result)
            && ($config->general->register_action->send_email
            || $config->general->register_action->send_email_to_users_filled_email_later))
        {
            $this->infoMessage(Ibulletin_Texts::get('index.missing_register_mail'), 'warning');
        }

         if ((boolean)!Ibulletin_AdminAuth::hasFrontendUser()){
             $this->infoMessage($this->texts->notpaired_front_user,'warning');
         }

        $this->view->form = $form;
    }

    /**
	 * seznam odstranenycg emailu
	 */
	 public function deletedAction() {
        $texts = Ibulletin_Texts::getSet();

        $this->setLastVisited();

        // vytahneme seznam emailu
        $mails = new Ibulletin_Mails($this->config, $this->db);
        try {
            $grid = new Ibulletin_DataGrid($mails->getMailsQuery(true,true));
            $grid->setEmptyText($texts->empty);
            $grid->setDefaultSort('id');
            $grid->addColumn('id', array(
                        'header' => $this->texts->id
                    ))
                    ->addColumn('name', array(
                        'header' => $this->texts->name,
                        'field' => 'e.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    ))
                    ->addColumn('subject', array(
                        'header' => $this->texts->subject,
                        'field' => 'e.subject',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    ))
                    ->addColumn('special_function', array(
                        'header' => $this->texts->type,
                        'width' => "100px",
                        'field' => 'special_function',
                        'type' => 'options',
                        'options' => $this->special_types
                    ))
                    ->addColumn('invitation_name', array(
                        'header' => $this->texts->invitation_name,
                        'width' => "100px",
                        'field' => 'iw.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true)
                    ))
                    ->addAction('restore', array(
                        'url' => $this->_helper->url('restore') . '/id/$id/',
                        'caption' => $this->texts->deleted->action_restore,
                        'confirm' => $this->texts->deleted->confirm_restore,
                        'image' => 'refresh'
                    ));

                    $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

        // spracujeme formular
        $this->setAfterCreateUrl('mail', 'index');
        $form = $this->processCreate('mail');


        $this->view->form = $form;
    }

    /**
     * seznam sablonovych emailu a formular pro vytvoreni noveho emailu ze sablony
     */
    public function templatesAction() {
        $config = Zend_Registry::get('config');

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        $this->setLastVisited();

        $texts = $this->texts;

        // vytahneme seznam emailu
        $mails = new Ibulletin_Mails($this->config, $this->db);
        try {
            $grid = new Ibulletin_DataGrid($mails->getTemplateMailsQuery());
            $grid->setEmptyText($texts->empty);
            $grid->setDefaultSort('id');
            $grid->addColumn('id', array(
                'header' => $this->texts->id
            ))
                ->addColumn('name', array(
                'header' => $this->texts->name,
                'field' => 'e.name',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'string',
                    'autocomplete' => true
                )
            ))
                ->addColumn('subject', array(
                'header' => $this->texts->subject,
                'field' => 'e.subject',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'string',
                    'autocomplete' => true
                )
            ))
                ->addColumn('special_function', array(
                'header' => $this->texts->type,
                'width' => "100px",
                'field' => 'special_function',
                'type' => 'options',
                'options' => $this->special_types
            ))
                ->addColumn('invitation_name', array(
                'header' => $this->texts->invitation_name,
                'width' => "100px",
                'field' => 'iw.name',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'string',
                    'autocomplete' => true)
            ))
                ->addColumn('template', array(
                'header' => $this->texts->template_name,
                'field' => 'e.template',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'string',
                    'autocomplete' => true)
            ))
                ->addAction('delete', array(
                'confirm' => $this->texts->confirm_delete,
                'url' => $this->_helper->url('delete') . '/id/$id/',
                'caption' => $this->texts->action_delete,
                'image' => 'remove'
            ))
                ->addAction('duplicate', array(
                'confirm' => $this->texts->confirm_duplicate,
                'url' => $this->_helper->url('duplicate') . '/id/$id/',
                'caption' => $this->texts->action_duplicate,
                'image' => 'clone'
            ))
                ->addAction('edit', array(
                'url' => $this->_helper->url('tpledit') . '/id/$id/',
                'caption' => $this->texts->action_edit,
                'image' => 'pencil'
            ));

            $this->view->grid = $grid->process();

        } catch (Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

        // spracujeme formular
        $this->setAfterCreateUrl('template', 'templates');
        $form = $this->processCreate('template');

        // jeste zkontrolujeme, zda je nastaven nejaky email jako
        // registracni, jinak zobrazime hlasku
        $q = 'SELECT id FROM emails WHERE send_after_registration AND deleted IS NULL ORDER BY id DESC LIMIT 1';
        $result = $this->db->fetchRow($q);
        if (empty($result)
            && ($config->general->register_action->send_email
            || $config->general->register_action->send_email_to_users_filled_email_later))
        {
            $this->infoMessage(Ibulletin_Texts::get('index.missing_register_mail'), 'warning');
        }

        if ((boolean)!Ibulletin_AdminAuth::hasFrontendUser()){
            $this->infoMessage($this->texts->notpaired_front_user,'warning');
        }

        $this->view->form = $form;
    }

    /**
	 * editace emailu
	 */
	public function editAction() {

		$texts = Ibulletin_Texts::getSet();

        $id = $this->_request->getParam('id');
		if (!$id) {
            $this->redirectUri($this->getLastVisited());
		}
        
        //prida do menu odkaz editace
        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'edit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        Ibulletin_Js::addJsFile('admin/mails.js');
        Ibulletin_Js::addJsFile('admin/collapse.js');

		// spracujeme formular a ulozeni
		$this->setAfterUpdateUrl('mail',(array('id' => $id)));
		$form = $this->processUpdate('mail', $id);

        //link na nahled emailu
        // get preview token for test user and this email
        $token = $this->getTestTokenForEmail($id);
        $this->view->preview_links_tooltip = $this->texts->edit->preview_tooltip;
        if ($token) {
            // store token for email preview
            $serverUrl = new Zend_View_Helper_ServerUrl();
            $url = new Zend_View_Helper_Url();
            $this->view->preview_links = $serverUrl->serverUrl() .
                $url->url(array('controller' => 'mail', 'module'=> null, 'action'=> 'show','noreplace' => '1','token'=> $token), null, true);
        }

        //cesta k souborům, neexistuje-li vytvorime ji
        $filepath = $this->config->mailer->imgsaddr.DIRECTORY_SEPARATOR.$id;
        if (!file_exists($filepath)) {
            Utils::mkdir($filepath);
        }
        $this->view->path = $filepath;
        
		// vysledek posledniho spam score
		$record = $this->getRecord('mail', $id);
        $this->view->subTitle = $record['name'];
        $this->view->spam = $record['spam'];

        // v helpe se pouziji tagy
        $texts->help->description->body = Utils::vnsprintf($texts->help->description->body,$this->config->mailer->tags->toArray());

		// zobrazime seznam linku
		$this->renderLinks();

		$this->view->form = $form;

	}

    /**
     * editace sablonoveho emailu
     */
    public function tpleditAction() {

        $id = $this->_request->getParam('id');
        if (!$id) {
            $this->redirectUri($this->getLastVisited());
        }

        $texts = Ibulletin_Texts::getSet();

        Ibulletin_Js::addJsFile('admin/mails.js');
        //wysiwig
        //Ibulletin_Js::addJsFile('admin/wysiwig.js');
        Ibulletin_Js::addJsFile('ckeditor/ckeditor.js');
        Ibulletin_Js::addJsFile('ckeditor/adapters/jquery.js');
        //nastavi basepath pro ckeditor
        Ibulletin_Js::addPlainCode("var CKEDITOR_BASEPATH = '" . $this->view->baseUrl() . "/pub/scripts/ckeditor/';");
        

        // spracujeme formular a ulozeni
        $this->setAfterUpdateUrl('template',(array('id' => $id)));
        $form = $this->processUpdate('template', $id);
        //prida do menu odkaz editace
        $this->moduleMenu->addItem($texts->submenu_edit, array('action'=>'tpledit','id'=>$id), null, true,'edit');
        $this->moduleMenu->setCurrentLocation('edit');

        //link na nahled emailu
        // get preview token for test user and this email
        $token = $this->getTestTokenForEmail($id);
        if ($token) {
            // store token for email preview
            $serverUrl = new Zend_View_Helper_ServerUrl();
            $url = new Zend_View_Helper_Url();
            $this->view->preview_links = $serverUrl->serverUrl() .
                $url->url(array('controller' => 'mail', 'module'=> null, 'action'=> 'show','noreplace' => '1', 'token'=> $token), null, true);
        }

        // formular pro upload souboru
        $this->view->path = rtrim($this->config->mailer->imgsaddr, '\\/') . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'email-files';

        // vysledek posledniho spam score
        $record = $this->getRecord('template', $id);
        $this->view->subTitle = $record['template'];
        $this->view->spam = $record['spam'];

        $links = new Links();
        $this->view->links = $links->getSortedLinks();

        // v helpe se pouziji tagy
        //$texts->help->description->body = Utils::vnsprintf($texts->help->description->body,$this->config->mailer->tags->toArray());

        // zobrazime seznam linku
        $this->renderLinks();

        $this->view->form = $form;
    }
	/**
	 * Vrati identifikator specialniho typu mailu odpovidajici prevodni tabulce z
	 * $this->special_types
	 *
	 * @param StdClass $mail   Mail obsahujici atributy z tabulky emails
	 *                         (send_after_registration, send_after_deregistration, special_function)
	 * @return String  identifikator specialniho typu mailu
	 */
	public function getMailSpecialType($mail)
	{
		// Specialni funkce mailu - registracni/deregistracni
		$special_type_val = "";//'none';
		if(!empty($mail->send_after_registration)){
			$special_type_val = 'registration';
		}
		if(!empty($mail->send_after_deregistration)){
			$special_type_val = 'deregistration';
		}
		if(!empty($mail->special_function)){
			$special_type_val = $mail->special_function;
		}

		return $special_type_val;
	}

	/**
	 * akce smazani emailu
	 */
	public function deleteAction() {

		$texts = Ibulletin_Texts::getSet();

		$id = $this->_request->getParam('id');
		if (!$id) {
			$this->redirectUri($this->getLastVisited());
		}
		// smazeme email
		try {
			$mails = new Ibulletin_Mails($this->config, $this->db);
			$res = $mails->deleteMail($id);
			if ($res) {
				$this->infoMessage($texts->deleted);
			} else {
				$this->infoMessage($texts->notdeleted, 'error');
			}

		} catch (Ibulletin_MailsException $e) {
			$this->infoMessage($texts->deleted);
			$this->infoMessage($e->getMessage(),'warning');
		}

        $this->redirectUri($this->getLastVisited());

	}

	/**
	 * akce duplikovani emailu
	 */
	public function duplicateAction() {

		$texts = Ibulletin_Texts::getSet();

		try {
			$mailId = $this->_request->getParam('id');

			$mails = new Ibulletin_Mails($this->config, $this->db);
			$mail = $mails->getMailData($mailId);

			// duplikujeme email
			$errors = $mails->duplicateMail($mailId);
			if (!empty($errors) && is_array($errors)) {
				$this->infoMessage($texts->error,'error');
				foreach ($errors as $error) {
					$this->infoMessage($error,'error');
				}
			} else {
				$this->infoMessage($texts->ok,'success',array($mail->name));
			}

		}
		catch (Ibulletin_MailsException $e) {
			Phc_ErrorLog::error('Mails admin', $e->getMessage());
			$this->infoMessage($e->getMessage(),'error');//'Během zpracování nastala chyba.';
		}

        $this->redirectUri($this->getLastVisited());

	}

	/**
	 * Posle testovaci email sprazenemu uzivateli frontendu prave prihlaseneho
	 * uzivatele adminu.
	 *
	 * @param int       ID emailu, ktery ma byt odeslan
	 * @param int       ID uzivatele, kteremu ma byt email odeslan
	 * @return bool     Podarilo se email odeslat?
	 */
	public function sendTestMail($emailId, $userId) {

		if(empty($emailId)){
			return false;
		}

		// zaradi email do fronty pro odeslani
		try {
			$mailer = new Ibulletin_Mailer($this->config, $this->db);
			// Nastaveni pokracovat v odesilani i pri chybach
			$mailer->setKeepSendingEH();
			// Posilat email i smazanym uzivatelum
			$mailer->sendDeletedToo();
			// Ignorujeme, jestli je uzivatel registrovan a ma nastaveno send_emails
			$mailer->setIgnoreRegisteredAndSendEmails(true);
            // povolime posilat i uzivatelum kteri maji nastaveny bad address
            $mailer->setIgnoreBadAddr(true);

			// Filtr pro vybrani a pridani pouze jednoho uzivatele
			$filter = "id = $userId";

			// Zaradime do fronty
			$mailer->enqueueEmail($emailId, $filter);
			// Ziskame id mailu k odeslani z users_emails
			$usersEmailsId = $this->db->lastInsertId('users_emails', 'id');

			// Posleme jeden mail
			$mailer->sendEmails($usersEmailsId);
		}
		catch (IBulletinMailerException $e) {
			return false;
        }

        return true;
    }

    /**
     * Posle testovaci email testovacimu uzivateli frontendu
     * uzivatele adminu.
     *
     * @param int       ID emailu, ktery ma byt odeslan
     * @param int       ID uzivatele, kteremu ma byt email odeslan, default se guessne podle emailu (testovaci uzivatel)
     * @return bool     Podarilo se email odeslat?
     */
    public function getTestTokenForEmail($emailId, $userId = NULL) {

        if (empty($emailId)) {
            return false;
        }

        if (empty($userId)) {
            $select = $this->db->select()
                 ->from(array('u' => $this->config->tables->users), array('id' => new Zend_Db_Expr('MIN(id)')))
                ->where('authcode = ?','DYko15VagHYTnS3Hfnre');
            $row = $this->db->fetchRow($select);

            if ($row) {
                $userId = $row['id'];
            } else {
                return false;
            }
        }

        // search for existing users_emails record (and corresponding token)
        $select = $this->db->select()
            ->from(array('ue' => $this->config->tables->users_emails),array('id'))
            ->where('user_id = ?', $userId)
            ->where('email_id = ?', (int)$emailId);

        $res = $this->db->fetchRow($select);
        if ($res) {
            $ue_id = $res['id'];
            $select = $this->db->select()
                ->from(array('ue' => $this->config->tables->users_emails), array('token'))
                -> where("id = ?", $ue_id);
            $res = $this->db->fetchRow($select);
            if ($res) {
                return $res['token'];
            } else {
                return false;
            }
        }

        // zaradi email do fronty pro odeslani
        try {
            $mailer = new Ibulletin_Mailer($this->config, $this->db);
            $mailer->setIgnoreBadAddr(true);
            $mailer->setIgnoreRegisteredAndSendEmails(true);
            // Filtr pro vybrani a pridani pouze jednoho uzivatele
            $filter = "id = $userId";
            // Zaradime do fronty
            $ue_ids = $mailer->enqueueEmail($emailId, $filter, null, array('sent' => new Zend_Db_Expr('NOW()')));

            if ($ue_id = array_pop($ue_ids)) {
                $select = $this->db->select()
                    ->from(array('ue' => $this->config->tables->users_emails), array('token'))
                    -> where("id = ?", (int)$ue_id);
                $res = $this->db->fetchRow($select);
                if ($res) {
                    return $res['token'];
                } else {
                    return false;
                }
            } else {
                // nepodarilo se zaradit email do fronty
                return false;
            }

        }
        catch (IBulletinMailerException $e) {
            return false;
        }
    }


    /**
     * Metoda spoustena po uploadu souboru
     */
    public function postSaveActions() {
        $id = $this->_getParam('id');
        $record = $this->getRecord('mail', $id);

        if ($record) {
            $this->updateRecord('mail', $record);
        }
    }

    /**
     * Obnovi smazany email, presmeruje na seznam emailu dle typu obnoveneho emailu
     *
     */
    public function restoreAction() {

        $id = $this->_request->getParam('id');
        //jestlize chybi parametr id, presmeruje zpet na deleted
    	if (!$id) {
    		$this->redirect('deleted');
    	}

        $mails = new Ibulletin_Mails($this->config, $this->db);

	    if ($mails->restore($id)) {
             $this->infoMessage($this->texts->deleted->restored);
        } else {
           $this->infoMessage($this->texts->deleted->notrestored, 'error');
        }

        //podle typy emailu presmerujeme na seznam emailu
        $email = $mails->getMailData($id);
        if ($email->template) {
            $this->redirect('templates');
        } else {
          $this->redirect('index');
        }


    }


}

