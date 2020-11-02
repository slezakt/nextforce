<?php


/**
 *	Kontrolér pro Mailer. Je zde rozhraní pro výběr osob do mailingu a rozhraní
 *	pro ruční zadávání nedoručených emailů.
 *
 *	@author Martin Krčmář
 */
class Admin_MailerController extends Ibulletin_Admin_BaseControllerAbstract {

    /** @var private Ibulletin_DataGrid */
    private $_grid;

    /** @var Ibulletin_Mailer */
    private $_mailer;

	public function init(){
	    parent::init();
        $this->submenuAll = array(
            'undelivered' => array('title' => $this->texts->submenu_undelivered, 'params' => array('action' => 'undelivered'), 'noreset' => false),
            'mailing' => array('title' => $this->texts->submenu_mailing, 'params' => array('action' => 'mailing'), 'noreset' => false),
            'emaillinks' => array('title' => $this->texts->submenu_emaillinks, 'params' => array('action' => 'emaillinks'), 'noreset' => false),
        );

        $this->submenuSpecific = array(
            /*'groupUndelivered' => array(
            'insert' => array('title' => $this->texts->submenu_insert, 'params' => array('action' => 'insert')),
            'importform' => array('title' => $this->texts->submenu_importform, 'params' => array('action' => 'importform')
          )), */
            'groupMailing' => array(
               /* 'export' => array('title' => $this->texts->submenu_export, 'params' => array('action' => 'export'), 'noreset' => true),
                'downloadcsv' => array('title' => $this->texts->submenu_downloadcsv, 'url' => 'pub/others/mailing_csv.txt', 'noreset' => true, 'external' => true),*/
                'showlog' => array('title' => $this->texts->submenu_showlog, 'params' => array('action' => 'showlog'), 'noreset' => false),
                'queue' => array('title' => $this->texts->submenu_queue, 'params' => array('action' => 'queue'), 'noreset' => false

          )));

        $this->submenuGroups = array(
            'groupMailing' => array('mailing', 'mailing'/*,'export'*/,'queue','showlog'),
            'groupUndelivered' => array('undelivered', 'undelivered'/*, 'insert', 'importform'*/),
        );

        $this->_mailer = new Ibulletin_Mailer($this->config, $this->db);
	}


	/**
	 * INDEX
     * Presmerujeme rovnou na vytvareni mailing listu.
	 */
	public function indexAction(){

	    $this->_helper->redirector('mailing');
	}

	public function importformAction(){
		$errors = Array ();
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');
		Zend_Loader::loadClass('Ibulletin_Mailer'); // objekt pro praci s emaily


		$sort = "wave";

		$mailer = new Ibulletin_Mailer($config, $db);
		$dataEmails = $mailer->getEmailsAndWavesRenderData("wave");
		$form = $this->getEmailCSVForm($dataEmails, 0);

		if ($_FILES == null){
			// do nothing
		} else {

			if ($_FILES["file"]["error"] > 0){
				$error_codes =array(
                    0=>"There is no error, the file uploaded with success",
                    1=>"The uploaded file exceeds the upload_max_filesize directive in php.ini",
                    2=>"The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
                    3=>"The uploaded file was only partially uploaded",
                    4=>"No file was uploaded",
                    6=>"Missing a temporary folder"
                );
                $this->infoMessage($error_codes[$_FILES["file"]["error"]],'error');
			}
			else{

				foreach ($_FILES as $key => $error){
					switch ($error) {
						case UPLOAD_ERR_OK :
							$tmpName = $_FILES['file']['tmp_name'][$key];
							$name = basename($_FILES['file']['name'][$key]);

							if (! is_uploaded_file($tmpName)){
								$this->infoMessage($this->texts->notuploaded,'error',array($name));
							}

							if (! move_uploaded_file($tmpName, "$this->uploadDir/$name")){
								$this->infoMessage($this->texts->notuploaded,'error',array($name));
								break;
							}

						case UPLOAD_ERR_INI_SIZE :
						case UPLOAD_ERR_FORM_SIZE :
                        	$name = basename($_FILES['file']['name'][$key]);
							$this->infoMessage($this->texts->filesizelimit,'error',array($name));

						case UPLOAD_ERR_NO_FILE :
							break;

						default :

					}
				}
			}
		}

		Zend_Loader::loadClass('Zend_Validate_EmailAddress');
		$validator = new Zend_Validate_EmailAddress();

		if ($this->getRequest()->isPost()){

			$emails = array ();
			$stats = array ();
			$delimeter = ";";

			if ($form->isValid($_POST)){

				try{

					if (($handle = fopen($_FILES['file']['tmp_name'], "r")) !== FALSE){
					    $row = 0;
						while (($data = fgetcsv($handle, 4096, $delimeter)) !== FALSE){
							$row++;
							if ($validator->isValid($data[0])){
								$emails[] = $data[0];
							}
							else{
								$this->infoMessage($this->texts->invalidemail, 'warning', array($data[0], $row));
							}

						}
					}

					foreach ($emails as $m){
						$stats[$m] = $mailer->setUndelivered($m, $form->getValue('emailid'));
					}



				}catch (IBulletinMailerException $e){
					Phc_ErrorLog::error('Mailer', $e);

				}
				$this->view->updatedRows = $stats;

			}
		}

		$this->view->form = $form;

	}

    /**
     *	Rozhraní pro výběr osob postihnutých mailingem a jejich přidání do
     *	fronty.
     */
    public function mailingAction(){

        $dataEmails = $this->_mailer->getEmailsAndWavesRenderData('email'); // seznam mailu vcetne zvacich vln
        unset($dataEmails[0]);

        $bu = new Bulletins;
        $dataBulletins = /*array('0' => '') + */(array)$bu->getBulletinListForSelect(true, false, null, null, true);

        $dataArticles = $bu->getAllPagesForSelect(false, true, '');

        try {

            $grid = new Ibulletin_DataGrid(Users::getUsersQuery(true)
                    ->joinLeft(array('s' => 'segments'), 's.id = segment_id', array('segment' => 'name'))
            );

            $grid->setEmptyText($this->texts->index->empty);

            $grid->setDefaultSort('added');

            $grid->setStoreFilters(true);

            $grid->setDefaultFilters(array(
                'bad_addr' => '0',
                'send_emails' => '1',
                'deleted' => '0',
                'unsubscribed' => '0',
                'users_with_email' => '1')
            );

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

            $opts_bool_nps = array(
                0 => $this->texts->option_nps_no,
                1 => $this->texts->option_nps_yes,
            );

            $def = array(
                'align'=>'left',
                'default' => '&lt;null&gt;',
            );

            $gdprAgreements = new GDPRAgrements();
            $dataAgreements = $gdprAgreements->getList();

            // Column definition
            $grid->addColumn('id', array_merge($def, array(
                'header' => $this->texts->id,
                'field' => 'u.id',
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
                'imgs' =>  array('user'),
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
                ->addColumn('selfregistered', array_merge($def, array(
                'header' => $this->texts->selfregistered,
                'align' => 'center',
                'imgs' => $imgs_yes_no,
                'field' => 'u.selfregistered',
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
                'field' => 'u.test',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'bool',
                    'options' => $opts_bool,
                )
            ))
                ->addColumn('added', array_merge($def, array(
                'header' => $this->texts->added,
                'type' => 'datetime',
                'field' => 'u.added',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'datetime',
                )
            )))
                ->addColumn('send_emails', array_merge($def, array(
                'header' => $this->texts->send_emails,
                'align' => 'center',
                'imgs' => $imgs_yes_no,
                'field' => 'u.send_emails',
                'filter' => array(
                    'type' => 'expr',
                    'datatype' => 'bool',
                    'options' => $opts_bool,
                )
            )));

            $grid->addFilter('users_with_email', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'CASE WHEN ? = 0 THEN u.email IS NULL OR u.email = \'\' ELSE u.email <> \'\' END',
                'label' => $this->texts->with_email,
                'emptyText'  => $this->texts->all_users,
                'options' => array(
                    0 => $this->texts->with_email_false,
                    1 => $this->texts->with_email_true
                ),
            ))
                ->addFilter('deleted', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'CASE WHEN ? = 0 THEN u.deleted IS NULL ELSE u.deleted IS NOT NULL END',
                'label' => $this->texts->mailing->deleted,
                'emptyText' => $this->texts->all_users,
                'options' => $opts_bool
                ))

                 ->addFilter('unsubscribed', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'CASE WHEN ? = 0 THEN u.unsubscribed IS NULL ELSE u.unsubscribed IS NOT NULL END',
                'label' => $this->texts->mailing->unsubscribed,
                'emptyText' => $this->texts->all_users,
                'options' => $opts_bool
                ))


                ->addFilter('delivered', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id IN (SELECT user_id FROM users_emails WHERE email_id = ? GROUP BY user_id)',
                'label' => $this->texts->delivered,
                'options' => $dataEmails,
                'emptyText'  => $this->texts->all_users,
            ))
                ->addFilter('undelivered', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id NOT IN (SELECT user_id FROM users_emails WHERE email_id = ? GROUP BY user_id)',
                'label' => $this->texts->notdelivered,
                'options' => $dataEmails,
                'emptyText'  => $this->texts->all_users,
            ))
                ->addFilter('didnt_come_bulletin', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id NOT IN (
                            SELECT s.user_id
                            FROM page_views pv INNER JOIN sessions s ON pv.session_id = s.id
                            WHERE (s.user_id IS NOT null)
                            AND bulletin_id = ?
                        )',
                'label' => $this->texts->didnt_come_bulletin,
                'options' => $dataBulletins,
                'emptyText'  => $this->texts->all_users,
            ))
                ->addFilter('did_come_bulletin', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id IN (
                            SELECT s.user_id
                            FROM page_views pv INNER JOIN sessions s ON pv.session_id = s.id
                            WHERE (s.user_id IS NOT null)
                            AND bulletin_id IN (?)
                        )',
                'label' => $this->texts->did_come_bulletin,
                'options' => $dataBulletins,
                'multiple' => true,
                'emptyText'  => $this->texts->all_users,
            ))

                ->addFilter('didnt_read_article', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id NOT IN (
                            SELECT s.user_id
                            FROM page_views pv INNER JOIN sessions s ON pv.session_id = s.id
                            WHERE (s.user_id IS NOT null)
                            AND page_id = ?
                        )',
                'label' => $this->texts->didnt_read,
                'options' => $dataArticles,
                'emptyText'  => $this->texts->all_users,
            ))

                ->addFilter('didnt_come_to_email', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id IN (
                        SELECT user_id FROM users_emails
                        WHERE email_id = ? AND sent IS NOT null
                        )
                        AND u.id NOT IN (
                            SELECT ult.user_id
                                FROM page_views pv
                                JOIN users_links_tokens ult ON pv.users_links_tokens_id = ult.id
                                WHERE ult.email_id = ?
                        )',
                'label' => $this->texts->didnt_come,
                'options' => $dataEmails,
                'emptyText'  => $this->texts->all_users,
            ))

                ->addFilter('did_read_article', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id IN (
                            SELECT s.user_id
                            FROM page_views pv INNER JOIN sessions s ON pv.session_id = s.id
                            WHERE (s.user_id IS NOT null)
                            AND page_id = ?
                        )',
                'label' => $this->texts->did_read,
                'options' => $dataArticles,
                'emptyText'  => $this->texts->all_users,
            ))
                ->addFilter('send_nps', array(
                'custom' => true,
                'field' => 'u.send_nps',
                'type' => 'expr',
                'datatype' => 'bool',
                'options' => $opts_bool_nps,
                'label' => $this->texts->send_nps,
                'emptyText'  => $this->texts->all_users,
            ))
              ->addFilter('didnt_read_email', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id IN (SELECT esv.user_id FROM emails_send_v as esv WHERE esv.sent IS NOT NULL AND esv.read_date IS NULL AND esv.email_id = ? AND user_id NOT IN (SELECT DISTINCT user_id FROM emails_response_v as erv WHERE email_id = ?))',
                'label' => $this->texts->didnt_read_email,
                'options' => $dataEmails,
                'emptyText'  => $this->texts->all_users,
            ))
				->addFilter('users_did_agreed', array(
				'custom' => true,
				'type' => 'query',
				'query' => 'u.id IN (SELECT ua.user_id FROM gdpr_users_agreements AS ua JOIN gdpr_agreements AS a ON ua.agreement_id = a.id AND a.deleted_at IS NULL WHERE ua.deleted_at IS NULL AND ua.agreement_id IN (?))',
				'label' => $this->texts->users_did_agreed,
				'options' => $dataAgreements,
				'multiple' => true,
				'emptyText'  => $this->texts->all_users,
			))
				->addFilter('users_did_not_agreed', array(
					'custom' => true,
					'type' => 'query',
					'query' => 'u.id NOT IN (SELECT ua.user_id FROM gdpr_users_agreements AS ua JOIN gdpr_agreements AS a ON ua.agreement_id = a.id AND a.deleted_at IS NULL WHERE ua.deleted_at IS NULL AND ua.agreement_id IN (?))',
					'label' => $this->texts->users_did_not_agreed,
					'options' => $dataAgreements,
					'multiple' => true,
					'emptyText'  => $this->texts->all_users,
			))
				->addFilter('new_users', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'CASE WHEN ? = 1 THEN u.id NOT IN (SELECT user_id FROM users_emails GROUP BY user_id) ELSE TRUE END',
                //'query' => 'CASE WHEN ? = 0 THEN u.email IS NULL OR u.email = \'\' ELSE u.email <> \'\' END',
                'label' => $this->texts->new_users,
                'newLineForm' => true,
                'formtype' => 'checkbox'
            ))
              ->addFilter('didnt_come', array(
                'custom' => true,
                'type' => 'query',
                'query' => 'u.id NOT IN (
                        SELECT u.id FROM sessions s, users u
                         WHERE s.user_id = u.id
                         GROUP BY u.id)',
                'label' => $this->texts->didnt_come_session,
                'formtype' => 'checkbox'
            ))
              ->addFilter('client_user', array(
                'custom' => true,
                'type' => 'orquery',
                'query' => 'u.client = ? AND u.deleted IS NULL AND u.send_emails IS true AND u.unsubscribed IS NULL',
                'formtype' => 'checkbox',
                'label' => $this->texts->client_users
            ));

        } catch (Ibulletin_DataGrid_Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

        $this->_grid = $grid->process();

        $this->view->form = $this->processCreate('enqueue');

        $this->view->grid = $grid;

    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getForm($name) {
        switch ($name) {
            case "enqueue":
                $form = parent::getForm($name);
                $form->setFormInline(true);
                $dataEmails = $this->_mailer->getEmailsAndWavesRenderData('email'); // seznam mailu vcetne zvacich vln
                //unset($dataEmails[0]);
                $dataEmails[0] = $this->texts->mailing->empty_select;

                $form->addElement('select', 'email_id', array(
                    'label' => $this->texts->email,
                    'required' => true,
                    'validators' => array(
                        array('NotEmpty', true, array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => $this->texts->validators->isempty,
                        ))),
                    ),
                    'multioptions' => $dataEmails,
                    'filters' => array('Int', array('Null', array('type' => Zend_Filter_Null::INTEGER))),
                ));

                return $form;
            default: return null;
        }
    }

    public function createRecord($name, $values) {
        switch ($name) {
            case "enqueue":
                try {

                    $sel = clone $this->_grid->getDataSource()->getSelect();

                    $ids = array();
                    $addDeleted = false;

                    foreach ($sel->query()->fetchAll() as $row) {
                        $ids[] = $row['id'];

                        if (($row['test'] == false && $row['client'] == false) && ($row['deleted'] || $row['unsubscribed'])) {
                              $addDeleted = true;
                        }

                    }

                    if ($addDeleted) {
                        $this->infoMessage($this->texts->mailing->queue_check_warning,'danger');
                    }


                    $res = $this->_mailer->enqueueEmail($values['email_id'], $ids);
                    return $res === NULL ? false : $res;
                }
                catch (Exception $e) {
                    $this->infoMessage($e->getMessage(), 'error');
                    return false;
                }
                return $res;
            default: return null;
        }
    }

	/**
	 *	Vypis vsech nedorucenych emailu. Jeste parametrem muze byt nejaky email.
	 *	Pak se ziskaji pouze ty konkretni nedorucene emaily.
	 */
	public function undeliveredAction(){

        $grid = new Ibulletin_DataGrid($this->_mailer->getUndeliveredQuery());

        $grid->setDefaultSort('sent');

        $imgs_yes_no = array(0 => 'remove-color', 1 => 'ok-color');

        $opts_bool = array(
            0 => $this->texts->option_no,
            1 => $this->texts->option_yes,
        );


        $grid->addColumn('id', array(
            'header' => $this->texts->id,
            'field' => 'ue.id',
            'align' => 'right',
            'width' => '40px',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'int',
            )
        ))
            ->addColumn('name', array(
            'header' => $this->texts->first_name,
            'field' => 'u.name',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('surname', array(
            'header' => $this->texts->last_name,
            'field' => 'u.surname',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('email', array(
            'header' => $this->texts->email,
            'field' => 'u.email',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('group', array(
            'header' => $this->texts->group,
            'field' => 'u.group',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('email_name', array(
            'header' => $this->texts->email_name,
            'field' => 'e.name',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
        /*    ->addColumn('email_subject', array(
            'header' => $this->texts->email_subject,
            'field' => 'e.subject',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))*/
            ->addColumn('status', array(
            'header' => $this->texts->status,
            'field' => 'ue.status',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('bounce_status', array(
            'header' => $this->texts->bounce_status,
            'field' => 'ue.bounce_status',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))

            /*
            ->addColumn('sent_by_user', array(
            'header' => $this->texts->sent_by_user,
            'field' => 'ue.sent_by_user',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'int',
            )
        ))
            ->addColumn('sent_by_admin', array(
            'header' => $this->texts->sent_by_admin,
            'field' => 'ue.sent_by_admin',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'int',
            )
        ))
            */
            ->addColumn('sent', array(
            'header' => $this->texts->sent,
            'field' => 'ue.sent',
            'type' => 'datetime',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'datetime',
            )
        ))
            ->addColumn('created', array(
            'header' => $this->texts->created,
            'field' => 'ue.created',
            'type' => 'datetime',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'datetime',
            )
        ));


        $this->view->grid = $grid->process();

	}

    /**
     * AJAX service for gathering mailer worker script data in JSON
     * @return JSON
     */
    public function mailerstateAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);

        if ($this->_mailer->isWorkerRunning()) {
            echo file_get_contents(Ibulletin_Mailer::WORKER_LOCK_FILE);
        } else echo '{}';
    }

    /**
     * AJAX service for killing mailer worker script
     * @return JSON
     */
    public function killworkerAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_mailer->killWorker();
        $this->redirect(array('action' => 'queue'));
    }

    /**
     *	Zobrazí mailovou frontu. S možnostmi mazat záznamy. A odeslat všechny
     *	emaily.
     */
    public function queueAction(){

        $this->setLastVisited();
        $this->_mailer->sendDeletedToo();
        $datasource = $this->_mailer->getUsersEmailsQuery()
            ->where('sent is NULL')
            ->where('status is NULL');               // nebudou se brat napr. emaily, pri kterych doslo k chybe v personalizaci

        $grid = new Ibulletin_DataGrid($datasource);

        $grid->setDefaultSort('created');

        $imgs_yes_no = array(0 => 'remove-color', 1 => 'ok-color');

        $opts_bool = array(
            0 => $this->texts->option_no,
            1 => $this->texts->option_yes,
        );


        $grid->addColumn('id', array(
            'header' => $this->texts->id,
            'field' => 'ue.id',
            'align' => 'right',
            'width' => '40px',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'int',
            )
        ))
            ->addColumn('name', array(
            'header' => $this->texts->first_name,
            'field' => 'u.name',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('surname', array(
            'header' => $this->texts->last_name,
            'field' => 'u.surname',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('email', array(
            'header' => $this->texts->email,
            'field' => 'u.email',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('group', array(
            'header' => $this->texts->group,
            'field' => 'u.group',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('email_name', array(
            'header' => $this->texts->email_name,
            'field' => 'e.name',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))
            ->addColumn('email_subject', array(
            'header' => $this->texts->email_subject,
            'field' => 'e.subject',
            'filter' => array(
                'autocomplete' => true,
                'type' => 'expr',
                'datatype' => 'string',
            )
        ))

            ->addColumn('created', array(
            'header' => $this->texts->created,
            'field' => 'ue.created',
            'type' => 'datetime',
            'filter' => array(
                'type' => 'expr',
                'datatype' => 'datetime',
            )
        ));

        $grid->addAction('remove', array(
            'url' =>  $this->_helper->url('remove').'/id/$id/',
            'confirm' => $this->texts->confirm_delete,
            'caption' => $this->texts->queue->delete,
            'image' => 'remove'
        ));


        $this->view->grid = $grid->process();

    }

    public function removeAction() {


        $id = $this->_getParam('id');

        if (!$id) {
            $this->redirectUri($this->getLastVisited());
        }

        try {
            $this->_mailer->deleteUsersEmails($id);
            $this->infoMessage($this->texts->deleted, 'success');
        } catch (Exception $e) {
            $this->infoMessage($this->texts->notdeleted, 'error');
            // TODO: log
        }
        $this->redirectUri($this->getLastVisited());

        //$this->view->payload = array('data'=> array('id' => $id), 'result' => $result);

    }

    public function clearAction() {

        try {
            $this->_mailer->deleteQueue();
            $this->infoMessage($this->texts->queuedeleted, 'success');
        } catch (Exception $e) {
            $this->infoMessage($this->texts->notdeleted, 'error');
            // TODO: log
        }
        $this->redirectUri($this->getLastVisited());

    }


	/**
	 *	Má na starosti formulář pro ruční zadávání adres, na které se nepodařilo
	 *	doručit email.
	 */
	public function insertAction(){
		$db = Zend_Registry::get('db'); // db handler
		$config = Zend_Registry::get('config'); // nacte se config
		$sort = "wave"; // razeni emailu


		Zend_Loader::loadClass('Ibulletin_Mailer'); // objekt pro praci s emaily
		Zend_Loader::loadClass('Zend_View_Helper_Url');
		Zend_Loader::loadClass('Zend_Session_Namespace');

		$request = $this->getRequest();
		$mailerNameSpace = new Zend_Session_Namespace('mailer_namespace');

		if ($request->__isset('emailid'))// pokud je nastaven parametr emailid
{
			$emailId = $request->getParam('emailid');
			$mailerNameSpace->emailIdInsert = $emailId;

		}
		else{
			if (isset($mailerNameSpace->emailIdInsert))
				$emailId = $mailerNameSpace->emailIdInsert;
			else
				$emailId = 0;
		}

		try{
			$mailer = new Ibulletin_Mailer($config, $db);
			$dataEmails = $mailer->getEmailsAndWavesRenderData($sort);

			$form = $this->getEmailInsertForm($dataEmails, $emailId);

			if ($this->getRequest()->isPost()){
				if ($form->isValid($_POST)){
					$this->view->updatedRows = $mailer->setUndelivered($form->getValue('address'), $form->getValue('emailid'));
				}
			}
		}catch (IBulletinMailerException $e){
			Phc_ErrorLog::error('Mailer', $e);
		}

		$this->view->form = $form;
	}

	/**
	 *	Formulář pro ruční zadávání nedoručených emailů.
	 *
	 * 	@param Seznam emailů.
	 *	@param Právě vybraný email.
	 */
	function getEmailInsertForm($emails, $chosen = ''){
		$this->loadLibs();

		$form = new Zend_Form();
		$form->setMethod('post');

		$input = new Zend_Form_Element_Text('address');
		$input->setLabel($this->texts->email_addr);
		//$input->addValidator(new Zend_Validate_EmailAddress());
		$input->setRequired(true);
		$input->addFilter('StringTrim');
		$ev = new Zend_Validate_EmailAddress();
		$ev->setMessage($this->texts->validators->invalidemail, Zend_Validate_EmailAddress::INVALID);
		$emptyValidator = new Zend_Validate_NotEmpty();
		$emptyValidator->setMessage($this->texts->validators->isempty, Zend_Validate_NotEmpty::IS_EMPTY);
		$input->addValidators(array ($ev, $emptyValidator ));

		$select = new Zend_Form_Element_Select('emailid');
		$select->setLabel($this->texts->email);
		$select->setMultiOptions($emails);
		$select->setRequired(true);
		$emptyValidator = new Zend_Validate_NotEmpty();
		$emptyValidator->setMessage($this->texts->validators->isempty, Zend_Validate_NotEmpty::IS_EMPTY);
		$select->addValidator($emptyValidator);
		if (! empty($chosen))
			$select->setValue($chosen);

		$submit = new Zend_Form_Element_Submit('submit');
		$submit->setLabel($this->texts->submit_set);

		$form->addElements(array ($select, $input, $submit ));

		return $form;
	}

	/**
	 *
	 * @param Seznam Emailů
	 * @param Předvolená volba
	 */

	function getEmailCSVForm($emails, $chosen = ''){
		$this->loadLibs();

		$form = new Zend_Form();
		$form->setMethod('post');

		$file = new Zend_Form_Element_File("file");

		$encoding = new Zend_Form_Element_Select("encoding");
		$encoding->setMultiOptions(array ("windows-1250" => "windows-1250", "utf-8" => "utf-8", "iso-8859-2" => "iso-8859-2" ));
		$encoding->setRequired(true);
		$empty = new Zend_Validate_NotEmpty();
		$empty->setMessage($this->texts->validators->isempty, Zend_Validate_NotEmpty::IS_EMPTY);
		$encoding->addValidator($empty);
		$encoding->setValue("windows-1250");

		$select = new Zend_Form_Element_Select('emailid');
		$select->setMultiOptions($emails);
		$select->setRequired(true);
		$emptyValidator = new Zend_Validate_NotEmpty();
		$emptyValidator->setMessage($this->texts->validators->isempty, Zend_Validate_NotEmpty::IS_EMPTY);
		$select->addValidator($emptyValidator);
		if (! empty($chosen))
			$select->setValue($chosen);

		$submit = new Zend_Form_Element_Submit('submit');
		$submit->setLabel($this->texts->submit_set);

		$form->addElements(array ($select, $file, $encoding, $submit ));

		return $form;
	}

	/**
	 *	Vrací formulář pro změnu emailu.
	 *
	 *	@param Data pro rozbalovací seznam, seznam emilů.
	 *	@param Vybraný email.
	 */
	function getEmailSwitchForm($data, $chosen = ''){
		$this->loadLibs();

		$url = new Zend_View_Helper_Url();

		$form = new Zend_Form();
		$form->setMethod('post');

		$form->setAction($url->url(array ('controller' => 'mailer', 'action' => 'undelivered', 'recstart' => 1 )) . '/');

		$options = array ('onchange' => 'submit();' );
		$select = new Zend_Form_Element_Select('emailid', $options);
		$select->setLabel($this->texts->email);
		$select->setMultiOptions($data);
		if (! empty($chosen))
			$select->setValue($chosen);

		$form->addElements(array ($select ));

		return $form;
	}

	/**
	 *	Formulář pro výběr osob do mailingu. Zobrazuje emaily včetně zvacích
	 *	vln.
	 *
	 *	@param Seznam emailu.
	 *	@param Právě vybraný email.
	 */
	function getEmailAndWaveForm($dataEmails, $chosenEmail = ''){
		$this->loadLibs();

		$form = new Zend_Form();
		$form->setMethod('post');

		$selectEmail = new Zend_Form_Element_Select('emailid');
		$selectEmail->setLabel($this->texts->email);
		$selectEmail->setMultiOptions($dataEmails);
		$ev = new Zend_Validate_NotEmpty();
		$ev->setMessage($this->texts->validators->isempty, Zend_Validate_NotEmpty::IS_EMPTY);
		$selectEmail->addValidator($ev);
		$selectEmail->setRequired('true');

		if (! empty($chosenEmail))
			$selectEmail->setValue($chosenEmail);

		$send = new Zend_Form_Element_Submit('to_send');
		$send->setLabel($this->texts->submit_queue);
		$form->addElements(array ($selectEmail, $send ));

		return $form;
	}

	/**
	 *	Formulář pro výběr osob do mailingu. Zobrazuje inputy pro zadávání
	 *	filtrů.
	 *
	 *	@param Pole emailů pro rozbalovací select.
	 * 	@param Pole bulletinů pro rozbalovací select.
	 */
	function getMailingForm($dataEmails = array(), $dataBulletins = array(), $dataArticles = array()){
		$this->loadLibs();

		$form = new Form();
		$form->setMethod('post');
		$url = new Zend_View_Helper_Url();

		// tohle je tady kvuli tomu recstartu, kdyz se nastavi novy filtr a
		// uzivatel je zrovna treba na 3 trance vypisu uzivatelu, s novym
		// filtrem tech vyslednych stranek muze byt mit, treba 2, takze po
		// zadani noveho filtru se vzdy zacne vypis uzivatelu zobrazovat od
		// prvni stranky.
		$form->setAction($url->url(array ('controller' => 'mailer', 'action' => 'mailing', 'recstart' => 1 )) . '/');

		// input texty pro nastaveni filtru uzivatelu
		$nameFilter = new Zend_Form_Element_Text('name_filter');
		$surnameFilter = new Zend_Form_Element_Text('surname_filter');
		$emailFilter = new Zend_Form_Element_Text('email_filter');
		$addedFilter = new Zend_Form_Element_Text('added_filter');
		// napoveda pro input pole
        $addedHelp = preg_replace('/\n/','',addslashes(($this->texts->added_help))); // atribut xhtml elementu nesmi obsahovat tvrde mezery

		$options = array ('onmouseover' => 'Tip(\'' . $addedHelp . '\');' );
		$addedFilter->setOptions($options);
		$addedValidator = new MyValid_TimestampFilter();
		$addedFilter->addValidator($addedValidator);

		// napoveda pro input pole
		$groupFilter = new Zend_Form_Element_Text('group_filter');
		$groupHelp = preg_replace('/\n/','',addslashes(($this->texts->group_help)));
		$options = array ('onmouseover' => 'Tip(\'' . $groupHelp . '\');' );
		$groupFilter->setOptions($options);
		$groupValidator = new Ibulletin_Validators_GroupFilter();
		$groupFilter->addValidator($groupValidator);

		// radiobox pro vybrani s emailem / bez emailu
		$withEmail = new Zend_Form_Element_Radio('with_email');
		$withEmail->addMultiOptions(array (true => $this->texts->with_email_true, false => $this->texts->with_email_false ));
		$withEmail->setLabel($this->texts->with_email);
		$withEmail->setValue(1);

		// Ignorovat priznaky registered a send_emails
		$ignoreRegAndSend = new Zend_Form_Element_Checkbox('ignoreRegAndSend');
		$ignoreRegAndSend->setLabel($this->texts->ignore_reg_send);

		// jeste submit
		$setFilter = new Zend_Form_Element_Submit('set_filter');
		$setFilter->setLabel($this->texts->set_filter);
		$setFilter->removeDecorator('DtDdWrapper');

		$unsetFilter = new Zend_Form_Element_Submit('destroy_filter');
		$unsetFilter->setLabel($this->texts->destroy_filter);
		$unsetFilter->removeDecorator('DtDdWrapper');

		// radio pro vyber pouze osob, kterym jeste nebyl poslan mail
		$newUsers = new Zend_Form_Element_Radio('new_users');
		$newUsers->setLabel($this->texts->new_users);
		$newUsers->addMultiOptions(array (false => $this->texts->new_users_all, true => $this->texts->new_only ));
		$newUsers->setValue(0);

		// filtr pro vyber uzivatelu, kteri nedostali konkretni mail
		$selectEmail = new Zend_Form_Element_Select('undelivered_filter');
		$selectEmail->setLabel($this->texts->notdelivered);
		$selectEmail->setMultiOptions($dataEmails);

		// filtr tech, kteri neprisli na tento email
		$didntComeToEmail = new Zend_Form_Element_Select('didnt_come_email_filter');
		$didntComeToEmail->setLabel($this->texts->didnt_come);
		$didntComeToEmail->setMultiOptions($dataEmails);

		// checkbox pro vyber tech, kteri na mail jeste napresli
		$didntCome = new Zend_Form_Element_Checkbox('didnt_come_filter');
		$didntCome->setLabel($this->texts->didnt_come_session);

		$bulletin = new Zend_Form_Element_Select('didnt_come_bulletin');
		$bulletin->setLabel($this->texts->bulletin);
		$bulletin->setMultiOptions($dataBulletins);

		// uzivatele, kterym byl tento mail poslan
		$delivered = new Zend_Form_Element_Select('delivered_filter');
		$delivered->setLabel($this->texts->delivered);
		$delivered->setMultiOptions($dataEmails);

		// uzivatele, kteri necetli dany clanek
		$articles = new Zend_Form_Element_Select('didnt_read_article');
		$articles->setLabel($this->texts->didnt_read);
		$articles->setMultiOptions($dataArticles);

		// zahrnout uzivatele typu "client"
		$includeClients = new Zend_Form_Element_Checkbox('include_clients');
		$includeClients->setLabel($this->texts->include_clients);


		$form->addElements(array ($nameFilter, $surnameFilter, $unsetFilter, $emailFilter, $addedFilter, $groupFilter, $setFilter, $withEmail, $ignoreRegAndSend, $selectEmail, $didntCome, $didntComeToEmail, $bulletin, $newUsers, $delivered, $articles, $includeClients ));

		return $form;
	}



	/**
	 *	Nastaví filtr podle dat z formuláře.
	 *
	 * 	@param Objekt filtru.
	 *	@param Objekt formuláře.
	 */
	public function setFilter($filter, $form){
		$filter->setNameFilter($form->getValue('name_filter'));
		$filter->setSurnameFilter($form->getValue('surname_filter'));
		$filter->setEmailFilter($form->getValue('email_filter'));
		$filter->setAddedFilter($form->getValue('added_filter'));
		$filter->setGroupFilter($form->getValue('group_filter'));
		$flag = true;
		$filter->setWhoDidntCome($form->getElement('didnt_come_filter')->isChecked($flag));

		// budou se vybirat uzivatele s emailem nebo bez
		$filter->setWithEmail($form->getValue('with_email'));

		// ignorovat registered a send_emails
		$filter->setIgnoreRegAndSend($form->getValue('ignoreRegAndSend'));

		// jestli se budou zobrazovat vsichni uzivatele, nebo pouze
		// ti, kterym jeste nebyl poslan zadny email
		$filter->setNewUsers($form->getValue('new_users'));

		$filter->setUndeliveredEmailUsers($form->getValue('undelivered_filter'));
		//$form->getElement('undelivered_filter')->setValue($form->getValue('undelivered_filter'));


		$filter->setDidntComeToEmail($form->getValue('didnt_come_email_filter'));
		//$form->getElement('didnt_come_email_filter')->setValue($form->getValue('didnt_come_email_filter'));


		$filter->setDidntComeToBulletin($form->getValue('didnt_come_bulletin'));
		//$form->getElement('didnt_come_bulletin')
		//->setValue($form->getValue('didnt_come_bulletin'));
		$filter->setDeliveredEmailUsers($form->getValue('delivered_filter'));

		$filter->setDidntReadArticle($form->getValue('didnt_read_article'));

		$filter->setIncludeClients($form->getValue('include_clients'));

		return $filter;
	}

	/**
	 *	Vyplní pole ve formuláři daty z filtru.
	 *
	 *	@param Objekt formuláře.
	 *	@param Objekt filtru.
	 */
	public function fillFormWithFilter($form, $filter){
		$form->getElement('name_filter')->setValue($filter->getNameFilter());
		$form->getElement('surname_filter')->setValue($filter->getSurnameFilter());
		$form->getElement('email_filter')->setValue($filter->getEmailFilter());
		$form->getElement('with_email')->setValue($filter->getWithEmail());
		$form->getElement('ignoreRegAndSend')->setValue($filter->getIgnoreRegAndSend());
		$form->getElement('new_users')->setValue($filter->getNewUsers());
		$form->getElement('added_filter')->setValue($filter->getAddedFilter());
		$form->getElement('group_filter')->setValue($filter->getGroupFilter());
		$form->getElement('undelivered_filter')->setValue($filter->getUndeliveredEmailUsers());
		$form->getElement('didnt_come_email_filter')->setValue($filter->getDidntComeToEmail());
		$form->getElement('didnt_come_filter')->setValue($filter->getWhoDidntCome());
		$form->getElement('didnt_come_bulletin')->setValue($filter->getDidntComeToBulletin());
		$form->getElement('delivered_filter')->setValue($filter->getDeliveredEmailUsers());
		$form->getElement('didnt_read_article')->setValue($filter->getDidntReadArticle());
		$form->getElement('include_clients')->setValue($filter->getIncludeClients());
		return $filter;
	}

	/**
	 * Nacte knihovny potrebne k praci s formulari
	 */
	public function loadLibs(){
		Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Zend_Form_Element_Submit');
		Zend_Loader::loadClass('Zend_Form_Element_Text');
		Zend_Loader::loadClass('Zend_Form_Element_Textarea');
		Zend_Loader::loadClass('Zend_Form_Element_Checkbox');
		Zend_Loader::loadClass('Zend_Form_Element_Radio');
		Zend_Loader::loadClass('Zend_Form_Element_Hidden');
		Zend_Loader::loadClass('Zend_Form_SubForm');
		Zend_Loader::loadClass('Zend_Form_Element_Select');

		Zend_Loader::loadClass('Zend_Validate_NotEmpty');
		Zend_Loader::loadClass('Zend_Validate_Date');
		Zend_Loader::loadClass('Zend_Validate_Digits');
		Zend_Loader::loadClass('Zend_Validate_GreaterThan');
		Zend_Loader::loadClass('Zend_Validate_EmailAddress');

		Zend_Loader::loadClass('Zend_View_Helper_Url');
	}

	/**
	 * Export linkuu z jednotlivych mailuu.
	 */
	public function emaillinksAction()
	{
	    $db = Zend_Registry::get('db'); // db handler
        $config = Zend_Registry::get('config'); // nacte se config
	    $mails = new Ibulletin_Mails($config, $db);
	    $req = $this->getRequest();

	    // Zkontrolujeme jestli nebyl odeslan formular, pokud ano, spustime export
	    if($req->getParam('emailId')){
	        $this->emaillinksExport($req->getParam('emailId'), $req->getParam('onlyLatest', false));
	        return;
	    }


	    $maillist = $mails->getMailsForForm();
	    // Pripravime seznam pro select
	    $maillistSel = array();
	    foreach($maillist as $item){
	        $maillistSel[$item->id] = join(' - ', array($item->name, $item->subject));
	    }

	    // Formular se seznamem
	    $form = new Form(array(
	       'method' => 'get')
	    );
	    // Seznam
        $form->addElement('select', 'emailId', array(
            'label' => $this->texts->email,
            'multioptions' => $maillistSel,
        ));
        // Seznam
        $form->addElement('checkbox', 'onlyLatest', array(
            'label' => $this->texts->onlylatest,
            'multioptions' => $maillistSel,
        ));
        // Submit
        $form->addElement('submit', 'exportLinks', array('class'=>'btn-primary','label' => $this->texts->submit_export));

        $this->view->form = $form;
	}

    /**
     * Provede export seznamu odkazu pro uzivatele danym mailem do xml
     */
    public function emaillinksExport($emailId, $onlyLatest = false)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        $mails = new Ibulletin_Mails($config, $db);
        $email = $mails->getMailData($emailId);

        $sel = new Zend_Db_Select($db);
        $sel->from(array('u' => 'users_vv'), array('user_id' => 'id', 'name', 'surname', 'email'))
            ->join(array('ue' => 'users_emails'), 'ue.user_id = u.id', array('email_token' => 'token', 'sent', 'created'))
            ->joinLeft(array('ult' => 'users_links_tokens'), 'ult.users_emails_id = ue.id', array('link_token' => 'token'))
            ->joinLeft(array('l' => 'links'), 'ult.link_id = l.id', array('link_id' => 'id', 'link_name' => 'name'))
            ->where('ue.email_id = ?', $emailId)
            ->order(array('u.surname', 'u.name', 'u.email', 'u.id', 'ue.created', 'l.id'));

        // Vyfiltrujeme jen posledni odeslani tohoto mailu pro kazdeho uzivatele
        if($onlyLatest){
            $sel->join(array('uelast' => new Zend_Db_Expr("(SELECT user_id, max(created) AS created FROM users_emails
                WHERE email_id = ".(int)$emailId." GROUP BY user_id)")),
                'uelast.user_id = ue.user_id AND uelast.created = ue.created', array());
        }

	    //echo $sel;
	    //exit;

        $data = $db->fetchAll($sel, array(), Zend_Db::FETCH_OBJ);


	    // Zagregujeme vse od jednoho uzivatele do radku a vytvorime linky
	    // stejne jako v Ibulletin_MailerTags::parseTags()
	    $table = array();
	    $table[0] = array('id' => 'id', 'name' => 'name', 'surname' => 'surname', 'email' => 'email', 'created',
	        'trackingpicture' => 'trackingpicture', 'unsubscribe' => 'unsubscribe', 'changeemailaddr' => 'changeemailaddr');
	    $linkPositions = array();
	    $lastUser = null;
	    $lastCreated = null;
	    $lastRow = new stdClass();
	    $lastRow->user_id = null;
	    $data[] = $lastRow; // radek navic umoznujici zapsat posledni sadu
	    foreach($data as $item){
    	        //echo join('|', (array)$item)."<br/>";
	        if($lastUser != $item->user_id || $lastCreated != $item->created){
	            // Zapis minuleho radku
	            if(isset($row)){
    	            $table[] = $row;
	            }
	            // Ukonceni poslednim prazdnym radkem
	            if($item->user_id === null){
	                break;
	            }

	            // Zacatek radku uzivatele (kde jeste neni treba agregovat)
	            $row = array(
	                'id' => $item->user_id,
	                'name' => $item->name,
    	            'surname' => $item->surname,
	                'email' => $item->email,
                    'created' => $item->created,
	                'trackingpicture' => $config->mailer->tags->email_confirm_address.'/'.$item->email_token.'/',
	                'unsubscribe' => $config->mailer->logoff_address.'/'.$item->email_token,
    	            'changeemailaddr' => $config->mailer->changeemailaddr_address.'/'.$item->email_token.'/',
                );
                $row = $row + $linkPositions;
            }

	        // Pokud v tomto mailu je nejaky link, pridame ho
	        if(!empty($item->link_id)){
    	        // Pokud jeste tento link neni v linksPositions, pridame jej na konec a doplnime ho take do hlavicky
    	        if(!isset($linkPositions[$item->link_id])){
    	            $linkPositions[$item->link_id] = '';
    	           $table[0][$item->link_id] = $item->link_name;
    	        }

    	        // Vytvorime url a ulozime na odpovidajici misto v radku
    	        $row[$item->link_id] = $config->mailer->link->url.'/'.$item->link_token.'/';
            }
	        $lastUser = $item->user_id;
	        $lastCreated = $item->created;
        }

        /*
	    echo "<table class='tablesorter'>";
	    foreach($table as $row){
	        echo "<tr><td>".join('</td><td>', $row)."</td></tr>";
        }
	    echo "</table>";
        //*/

	    require('admin/controllers/StatsController.php');
	    Ibulletin_Excel::ExportXLSX($table, $email->name.'_links', false, null, 1);

	    // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);
        exit();
	}


	/**
	 *	Odešle všechny emaily, které jsou ve frontě.
	 */
	public function sendallAction(){

        $texts = Ibulletin_Texts::getSet();

		try{

		    $this->_mailer->setSendFake($this->_request->getParam('fake', false));

		    if ($this->_mailer->getSendFake() && ($fake_datetime = $this->_request->getParam('datetime'))) {
		    	if ($fake_datetime = strtotime($fake_datetime)) {
                    $this->_mailer->setSendFakeDatetime(date('Y-m-d H:i:s',$fake_datetime));
		    	} else {
		    		$this->infoMessage($texts->invaliddate, 'error');
		    		$this->redirect('queue');return;
		    	}
		    } else {
                $this->_mailer->setSendFakeDatetime(date('Y-m-d H:i:s'));
		    }


            if (!$this->_mailer->isWorkerRunning()) {
                $this->_mailer->startWorker();
            }

		}catch (IBulletinMailerException $e){

			Phc_ErrorLog::error('MailerController::sendAllAction()', $e);
		}

        $this->redirectUri($this->getLastVisited());

	}

	/**
	 *	Přidá ke každému záznamu tlačítko pro smazání.
	 */
	function createUsersEmailsForm($usersEmails){
		for($i = 0; $i < count($usersEmails); $i ++){
			$usersEmails[$i]['email_delete'] = $this->createDeleteButton($usersEmails[$i]['users_emails_id']);
		}

		return $usersEmails;
	}

	/**
	 *	Vytvoří tlačítko pro smazání záznamu.
	 */
	function createDeleteButton($id){
		$this->loadLibs();
		$url = new Zend_View_Helper_Url();
		$form = new Zend_Form();
		$form->setMethod('post');
		$form->setAction($url->url(array ('controller' => 'mailer', 'action' => 'queue' )) . '/');

		$delete = new Zend_Form_Element_Submit("delete_email_$id");
		$delete->setLabel($this->texts->queue->delete);
		$options = array ('onClick' => 'return confirm(\''.$this->texts->confirm_delete,'\')' );
		$delete->setOptions($options);
		$delete->removeDecorator('DtDdWrapper');

		$form->addElements(array ($delete ));

		$form->removeDecorator('HtmlTag');

		return $form;
	}

	/**
	 *	Vytvoří tlačítko pro smazání všech záznamů.
	 */
	function getDeleteAllButton(){
		$this->loadLibs();
		$url = new Zend_View_Helper_Url();
		$form = new Zend_Form();
		$form->setMethod('post');
		$form->setAction($url->url(array ('controller' => 'mailer', 'action' => 'queue', 'recstart' => 1 )) . '/');

		$delete = new Zend_Form_Element_Submit('delete_all');
		$delete->setLabel($this->texts->queue->delete_all);
		$options = array ('onClick' => 'return confirm(\''.$this->texts->confirm_delete_all.'\')' );
		$delete->setOptions($options);
		$delete->removeDecorator('DtDdWrapper');

		$form->addElements(array ($delete ));

		$form->removeDecorator('HtmlTag');
		//$form->getDecorator('HtmlTag')->setOption('class','line');
		$form->getDecorator('Form')->setOption('class', 'line');

		return $form;
	}

	/**
	 *	Vytvoří tlačítko pro odeslání všech emailů.
	 */
	function getSendAllButton(){
		$this->loadLibs();
		$url = new Zend_View_Helper_Url();
		$form = new Zend_Form();
		$form->setMethod('post');
		$form->setAction($url->url(array ('controller' => 'mailer', 'action' => 'sendall' )) . '/');

		$send = new Zend_Form_Element_Submit('send_all');
		$send->setLabel($this->texts->queue->send_all);
		$send->removeDecorator('DtDdWrapper');

        $send->setOptions(array('onClick' => 'return confirm(\''.$this->texts->confirm_send_all.'\')' ));

		$form->addElements(array ($send ));

		$form->removeDecorator('HtmlTag');
		//$form->getDecorator('HtmlTag')->setOption('class','line');
		$form->getDecorator('Form')->setOption('class', 'line');

		return $form;
	}

    /**
     *  Vytvoří tlačítko pro vytvoreni vsech emailu a zaznamu k nim v DB bez samotneho odesilani
     *  mailu - slouzi pri generovani odkazu pro externi rozesilky.
     */
    function getSendFakeAllButton(){
        $this->loadLibs();
        $url = new Zend_View_Helper_Url();
        $form = new Zend_Form();
        $form->setMethod('post');
        $form->setAction($url->url(array ('controller' => 'mailer', 'action' => 'sendall', 'fake' => '1' )) . '/');

        $send = new Zend_Form_Element_Submit('send_all');
        $send->setLabel($this->texts->queue->send_fake);
        $send->removeDecorator('DtDdWrapper');
        $send->setOptions(array('onClick' => 'return confirm(\''.$this->texts->confirm_sendfake_all.'\')' ));

        $datetime = new Zend_Form_Element_Text('datetime');
        $datetime->setLabel($this->texts->queue->send_fake_datetime);
        $datetime->removeDecorator('DtDdWrapper');
        $datetime->setValue(date('d.m.Y H:i:s'));

        $form->addElements(array ($send, $datetime ));

        $form->removeDecorator('HtmlTag');
        //$form->getDecorator('HtmlTag')->setOption('class','line');
        $form->getDecorator('Form')->setOption('class', 'line');

        return $form;
    }

	/**
	 * Vypise na obrazovku LOG maileru
	 */
	public function showlogAction(){
		$config = Zend_Registry::get('config');
		$logfile = $config->mailer->logfile;

		if (! file_exists($logfile)){
			$this->view->logArray = array (Ibulletin_Texts::get('notexists'));
			return;
		}

		$array = file($logfile);
		$this->view->logArray = $array;

	}
}

Zend_Loader::loadClass('Zend_Validate_Abstract');


/**
 *	Třída pro validaci vstupu o filtru pro časovou známku.
 *	Při validaci zkusí provést SQL dotaz se zadaným parametrem. Pokud dotaz
 *	projde vrátí TRUE, jinak FALSE.
 */
class MyValid_TimestampFilter extends Zend_Validate_Abstract {

	const WRONG = 'wrong';

	protected $_messageTemplates = array (self::WRONG => 'invalid date' );

	public function isValid($time){
		$this->_setValue($time);

		$config = Zend_Registry::get('config');
		$db = Zend_Registry::get('db');

		$select = $db->select()->from('users')->where("added $time")->limit(1);

		try{
			$res = $db->fetchRow($select);
			return TRUE;
		}catch (Zend_Db_Exception $e){
			$this->_error();
			return FALSE;
		}
	}
}

?>
