<?php
/**
 * Stranka s preposlanim emailu kolegovi
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class ForwardmailController extends Zend_Controller_Action
{
    /**
     * Regularni vyraz pouzity ke kontrole mailove adresy.
     */
    var $mailRegex = '^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.(([0-9]{1,3})|([a-zA-Z]{2,3})|(aero|coop|info|museum|name))$';


    /**
     * Preposila mail kolegovi
     */
    public function indexAction()
    {

        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet();

        $req = $this->getRequest();
        $session = Zend_Registry::get('session');

        $token = $req->getParam('token', NULL);

        // ziskame data uzivatele
        $auth = Ibulletin_Auth::getInstance();
        $user = $auth->getActualUserData();
        $bulletins = new Bulletins();

        $bulletin_id = $req->getParam('bulletin_id',$bulletins->getCurrentBulletinId());

        // overime uzivatele anebo token
        if (!$user) {
            if ($token) {
                // autentizujeme podle token
                if ($auth->authByToken($token, FALSE)) {
                    $user = $auth->getActualUserData();
                } else {
                    $auth->redirectToUnauth();
                }
            } else {
                $auth->redirectToUnauth();
            }
        }


        $isAjax = $req->isXmlHttpRequest() || $req->has('isAjaxForwardMail') ;

        if($isAjax){
            // Vypneme vsechno renderovani view skriptuu
            $this->getHelper('viewRenderer')->setNeverRender(true);
            // Pripravime tridu s XML pro odpoved.
            $xml = new Ibulletin_XmlClass();
            $xml->__rootName = 'forwardmail';
            // content-type na text/xml
            $frontController = Zend_Controller_Front::getInstance();
            $response = $frontController->getResponse();
            $response->setHeader('Content-Type', 'text/xml', true);
        } else {
            $showCont = Ibulletin_ShowContent::getInstance();
            $this->view->is_forwardmail = true;
            $this->view->menu = $showCont->renderMenu($bulletin_id);
            // Cely radek bulletinu
            $this->view->bulletinRow = $bulletins->get($bulletin_id);
            // Nazev bulletinu, k pouziti na strance
            $this->view->bulletin_name = $this->view->bulletinRow['name'];
            // Zapiseme url_name current bulletinu do view
            $this->view->bul_url_name = $this->view->bulletinRow['url_name'];


            // Layout podle nastaveni u vydani
            if(!empty($this->view->bulletinRow['layout_name'])){
                Layouts::setLayout($this->view->bulletinRow['layout_name'], $this);
            }
        }


        // pripravime si formular
        $form = $this->_getForwardForm();


        // byl odeslan formular?
        if (!$req->isPost()) {
            $form->setDefaults(array(
                'subscribename' => $user['name'].' '.$user['surname'],
                'email' => '@',
                'message' => '',
                ));

            $this->view->form = $form;

        } elseif ($form->isValidPartial($_POST)) {

            // zaregistruje a nebude posilat registracni mail
            $auth = Ibulletin_Auth::getInstance();
            $attribs = array(
                'email' => $form->getValue('email'),
                'name' => $form->getValue('subscribename')     
            );
            // Zkusime, jestli uzivatel s danym ma ilem jiz neexistuje
            try{
                $new_user = Users::getUser(null, $form->getValue('email'));
                $new_user_id = $new_user['id'];
            }
            catch(Users_User_Not_Found_Exception $e){
                // Pro neexistujiciho uzivatele zalozime rovnou noveho
                $attribs['target'] = false;
                $attribs['group'] = "FORWARD E-MAIL";        
                $attribs['contact_origin'] = "FORWARD E-MAIL";
                $new_user_id = $auth->registerUser($attribs, false, false, false);
            }

            // ziska se email
            $select = new Zend_Db_Select($db);
            $select->from(array('e' => 'emails', 'email_id'))
                   ->where("e.special_function = 'forward'");
            // Podle nastaveni v configu, jestli ma byt forward mail ke kazdemu
            // vydani nebo jen jeden doplnime select.
            if(!$config->general->forwardmail_only_one){
                $select->join(array('iw' => 'invitation_waves'), 'e.invitation_id = iw.id')
                       ->where('iw.bulletin_id = ?', $bulletin_id);
            }

            $email_id = $db->fetchOne($select);
            if(empty($email_id)){
                // Neexistuje email k pouziti jako registracni, musime zaznamenat error
                Phc_ErrorLog::error('ForwardmailController::indexAction()', 'Nepodarilo se najit '.
                    'preposilaci (forward) email pro bulletin_id: "'.$bulletin_id.'".');

                if(!$isAjax){
                    $this->view->ok = 0;
                    $this->view->send = $texts->email_notsent;
                    $this->view->form = $form;
                }
                else{
                    $xml->message = $texts->email_notsent;
                    echo $xml->getXml();
                }
                return;
            }

            // zaradi email do fronty pro odeslani
            try {
                $mailer = new Ibulletin_Mailer($config, $db);
                // Nastaveni pokracovat v odesilani i pri chybach
                $mailer->setKeepSendingEH();
                // Posilat email i smazanym uzivatelum
                $mailer->sendDeletedToo();
                // Ignorujeme, jestli je uzivatel registrovan a ma nastaveno send_emails
                $mailer->setIgnoreRegisteredAndSendEmails(true);
                // Nastavime FORWARD NAME a TEXT
                $mailer->getMailerTags()->addTag('forwardMailUserName', $form->getValue('subscribename'));
                $mailer->getMailerTags()->addTag('forwardMailText', $form->getValue('message'));

                // Filtr pro vybrani a pridani pouze jednoho uzivatele
                $filter = "id = $new_user_id";
                // Zaradime do fronty
                $mailer->enqueueEmail($email_id, $filter);
                // Ziskame id mailu k odeslani z users_emails
                $users_emails_id = $db->lastInsertId('users_emails', 'id');

                // Posleme jeden mail
                $mailer->sendEmails($users_emails_id);

                if(!$isAjax){
                    $this->view->ok = 1;
                    $this->view->send = $texts->email_sent;
                } else{
                    $xml->message = $texts->email_sent;
                    $xml->ok = 1;
                }
            }
            catch (IBulletinMailerException $e) {
                if(!$isAjax){
                    $this->view->ok = 0;
                    $this->view->send = $texts->email_notsent;
                    $this->view->form = $form;
                } else{
                    $xml->message = $texts->email_notsent;
                }
            }

        // not valid form
        } else {
            if(!$isAjax){
                    $this->view->ok = 0;
                    $this->view->send = $texts->validators->notmatch;
                    $this->view->form = $form;
            } else{
                $xml->wrongMail = 1;
            }
        }

        if($isAjax){
        // Vypiseme XML data Ajaxu
        echo $xml->getXml();
        }

    }

    /**
     * formular pro preposlani mailu
     */
    protected function _getForwardForm() {

        $form = new Zend_Form();
        $form->setMethod('post');

        // subscribename
        $subscribeName = new Zend_Form_Element('subscribename');
        $form->addElement($subscribeName);

        // email
        $formEmail = new Zend_Form_Element('email', array('size' => '25', 'validators'));
        $formEmail->setLabel('E-mail');
        $formEmail->setRequired(true)->addValidator('NotEmpty')->addValidator(new Zend_Validate_EmailAddress());
        $form->addElement($formEmail);


        // vzkaz
        $formText = new Zend_Form_Element_Textarea('message', array(
            'cols' => '65',
            'rows' => '5'
        ));
        $formText->setLabel('Zpráva');
        $form->addElement($formText);

        // odeslat
        $formSave = new Zend_Form_Element_Submit('Odeslat', array('class'=>'button w140'));
        $formSave->setRequired(TRUE);
        $form->addElement($formSave);

        return $form;
    }



}
