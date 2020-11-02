<?php

/**
 * RepmailerController - modul pro odesilani definovaneho emailu reprezentantem, napr. uvitaci email, odesilany email se definuje v configu, 
 * repovi staci vytvorit ucet adminu propojit jej s emailem repa na frontu a nastavit práva pro repmailer 
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Admin_RepmailerController extends Ibulletin_Admin_BaseControllerAbstract {

    /**
     * ID repa
     * @var $repId  ID Repa
     */
    private $repId;

    /**
     * ID mailu
     * @var $mailId
     */
    private $mailId;

    public function init() {
        parent::init();

        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
        );

        $this->repId = Ibulletin_AdminAuth::getFrontendUser();
        $this->mailId = $this->config->mailer->repmailer->mailID;
    }
    
    

    
    public function indexAction() {


        //neni-li v config nastaveno mail id vyhodime chybu
        if (!$this->mailId) {
            $this->view->repmailer_msg = '<div class="alert">' . $this->texts->mailid_missed . '</div>';
            return;
        }

        //neni-li nastaven frontend user vyhodime chybu
        if (!$this->repId) {
            $this->view->repmailer_msg = '<div class="alert">' . $this->texts->frontuser_missed . '</div>';
            return;
        }
        
        $user= Users::getUser($this->repId);
        
        //neni-li uzivatel repem frontu vyhodime chybu
        if (!$user['is_rep']) {
            $this->view->repmailer_msg = '<div class="alert">' . $this->texts->frontuser_norep . '</div>';
            return;
        }
       
        $email = Ibulletin_Email::getEmail($this->mailId);
        $this->view->subTitle = $email->getName();
        
        
        //pro ipad menime styl datagridu a ikonu pro odesilani
        $iPad = false;
        if (preg_match('/iPad/', $_SERVER['HTTP_USER_AGENT'])) {
            $iPad = true;
        }

        if ($iPad) {
            Ibulletin_HtmlHead::addFile('datagrid_ipad.css');
            $sendIco = 'pub/img/admin/mail-send-ipad.png';
        } else {
            $sendIco = 'pub/img/admin/mail-send.png';
        }
        

        try {

            //ziskani uzivatelskych atributu ktere se maji zobrazit v gridu z konfigurace
            $users_attribs = explode(',', $this->config->mailer->repmailer->usersAttribs);

            
            $sel = Users::getUsersQuery(false, array(
                'pass','authcode','group', 'segment', 'target', 'deleted', 'added', 'bad_addr', 'send_emails', 'target', 'client', 'test','is_rep'), false);

            $sel->joinLeft(array('ur' => 'users_reps'), 'u.id=ur.user_id')
                    ->joinLeft(array('ue' => new Zend_Db_Expr('(SELECT DISTINCT ON (user_id) * from users_emails WHERE email_id = ' . $this->mailId . ' AND sent IS NOT NULL AND status IS NULL ORDER BY user_id, sent DESC)')), 'u.id=ue.user_id', array('sent' => 'ue.sent'))
                    ->joinLeft(array('au'=>'admin_users'),'ue.sent_by_admin = au.id',array('sender'=>'au.name'));

            //doplneni atributu do dotazu
            foreach ($users_attribs as $attr) {
                if ($attr) {
                    $sel->joinLeft(array('ua_' . $attr => 'users_attribs'), "u.id = ua_$attr.user_id AND ua_$attr.name='$attr'", array($attr => 'ua_' . $attr . '.val'));
                }
            }


            $sel->where('ur.repre_id = ?', $this->repId)
                    ->where('u.test IS false')
                    ->where('u.bad_addr IS false')
                    ->where('u.send_emails IS true')
                    ->where('u.deleted IS null');
            
            
            //overeni zda byli nalezeni nejaci uzivatele, jinak vyhodime upozorneni
            $rows = $this->db->fetchAll($sel);
            if (!$rows) {
                 $this->view->repmailer_msg = '<div class="alert">' . $this->texts->no_doctors_found . '</div>';
            }

            $datasource = $sel;

            $grid = new Ibulletin_DataGrid($datasource);

            $grid->setEmptyText($this->texts->empty);

            $grid->setDefaultSort('name');

            $def = array(
                'align' => 'left',
                'default' => '&lt;null&gt;',
            );

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
            )));

            //zobrazeni uziv. attr. v datagridu
            foreach ($users_attribs as $attr) {
                if ($attr) {
                    $grid->addColumn($attr, array_merge($def, array(
                        'header' => ucfirst($attr),
                        'field' => 'ua_' . $attr . '.val',
                        'filter' => array(
                            'autocomplete' => true,
                            'type' => 'expr',
                            'datatype' => 'string'
                        )
                    )));
                }
            }


            $grid->addColumn('email', array_merge($def, array(
                        'header' => $this->texts->email,
                        'field' => 'u.email',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true,
                        )
                    )))
                    ->addColumn('sent', array_merge($def, array(
                        'header' => $this->texts->sent,
                        'type' => 'datetime',
                        'field' => 'ue.sent',
                        'class' => 'sent-time',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'datetime',
                        )
                    )))
                     ->addColumn('sender', array_merge($def, array(
                        'header' => $this->texts->sender,
                        'field' => 'au.name',
                        'filter' => array(
                            'type' => 'expr',
                            'datatype' => 'string',
                            'autocomplete' => true
                        )
                    )))
                    ->addAction('send', array(
                        'data-sent' => '$sent',
                        'class' => 'send-repmail',
                        'url' => $this->_helper->url('send') . '/id/$id',
                        'data-name'=> '$name $surname',
                        'caption' => $this->texts->action_send,
                        'image' => $sendIco
                    ))
                    //hack datagridu, ktery v pripade jedne akce doplnuje prazdny sloupec na predposledni pozici
                    ->addAction('empty', array());
        } catch (Ibulletin_DataGrid_Exception $e) {
            $this->infoMessage($e->getMessage(), 'error');
        }

        $this->view->grid = $grid->process();
    }

    //akce pro odeslani emailu
    public function sendAction() {

        //obsluha jen pro ajax
        if ($this->getRequest()->isXmlHttpRequest()) {

            $userId = $this->_getParam('id');

            if (!$userId || !$this->mailId) {
                $this->getResponse()->setHttpResponseCode(500);
                return $this->view->data = "Mising user ID or mail ID";
            }
            
            //odesleme email a posleme do view zpravu
            $this->view->data = self::sendRepMail($this->mailId, $this->repId, $userId);
        }
    }
     
    /**
     * Metoda odesle Rep Mail
     * @param int $mailId 
     * @param int $repId
     * @param int $userId 
     * @param int $adminId - ID admina, ktery bude odesilatelem
     * @return string Cas odeslani | Chyba
     */
    public static function sendRepMail($mailId,$repId,$userId,$adminId = null) {
        
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        
        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();
        
        $mailer = new Ibulletin_Mailer($config, $db);

        try {

            $email = Ibulletin_Email::getEmail($mailId);
            $rep = Users::getUser($repId);
            $email->setRep($repId);

            $repname = trim($rep['degree_before'] . ' ' . $rep['name'] . ' ' . $rep['surname'] . ' ' . $rep['degree_after']);
            $email->setFrom($rep['email'], $repname);

            $user = Users::getUser($userId);

            // Filtr pro vybrani a pridani pouze jednoho uzivatele
            $filter = "id = $userId";

            // Zaradime do fronty a ziskame email ID
            $enq = $mailer->enqueueEmail($mailId, $filter,null,array(),$adminId);
            $userEmailId = array_shift($enq);

            $sel = $db->select()->from(array('ue' => 'users_emails'))->where('ue.id = ?', $userEmailId);

            //user email pro doplnen token
            $user_email = $db->fetchRow($sel);

            $user['email_id'] = $mailId;
            $user['user_id'] = $userId;
            $user['token'] = $user_email['token'];
            $user['users_emails_id'] = $userEmailId;

            $mailer->prepareSending();
            $mailer->getMailerTags()->parseTags($user, $email);

            //odesleme email
            $mailer->sendMail($user, $email);

            //po odeslani opet ziskame user email a posleme na vystup timestamp odeslani
            $sent_user_email = $db->fetchRow($sel);
            $sent = new Zend_Date($sent_user_email['sent'], Zend_Date::ISO_8601);
            return $sent->toString($config->general->dateformat->long);
        } catch (IBulletinMailerPersonalizationExc $e) {
            
            if ($userEmailId) {
                $mailer->setEmailStatus($userEmailId, Ibulletin_Mailer::S_PER_ERR);
            }
            $response->setHttpResponseCode(500);
            Phc_ErrorLog::error("RepmailerController:sendRepMail(), IBulletinMailerPersonalizationExc, userEmailID:".$userEmailId, $e->getTraceAsString());
            return $e->getMessage();
            
        } catch (Exception $e) {
            
             if ($userEmailId) {
                $mailer->setEmailStatus($userEmailId, Ibulletin_Mailer::S_UNDELIVERED);
             }
            $response->setHttpResponseCode(500);
            Phc_ErrorLog::error("RepmailerController:sendRepMail(), Exception, userEmailID:".$userEmailId, $e->getTraceAsString());
            return $e->getMessage();
            
        }
    }

}
