<?php

/**
 * Nepodarilo se odeslat mail pro potvrzeni zmeny adresy
 */
class Myprofile_Not_Sent_Exception extends Exception {}

/**
 * Muj profil
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class MyprofileController extends Zend_Controller_Action
{
    /*
     * Prevzato z showbulletin, aby se natahla data o bulletinech, jinak hazi expception
     */
    function predispatch() {
    
        $action = strtolower($this->_getParam('action'));    
        if($action != 'empty'){
            $this->_checkLoggedIn();
        }
            
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $bulletins = new Bulletins();
        
        
        $fc = Zend_Controller_Front::getInstance();
        $req = $this->getRequest();
        $router = $fc->getRouter();
        $showCont = Ibulletin_ShowContent::getInstance();
        
        $route_name = $router->getCurrentRouteName();
        
        // Ziskame url parametry ktere byly zadany
        $bulletin_url_name = $req->getParam('name');
        $article_url_name = $req->getParam('article');
        
        $bulletin_id = $bulletins->findBulletinId($bulletin_url_name, true);
        
        $bul_url_name = $bulletins->findBulletinUrlName($bulletin_id);
        
        
        // Jmeno kategorie
        $this->view->category_name = $page_data['category'];
        
        // Pripravime data pro menu
        $this->view->menu = $showCont->renderMenu($bulletin_id);
        
        // Cely radek bulletinu
        $this->view->bulletinRow = $bulletins->get($bulletin_id);
        // Nazev bulletinu, k pouziti na strance
        $this->view->bulletin_name = $this->view->bulletinRow['name'];
        // Zapiseme url_name current bulletinu do view
        $this->view->bul_url_name = $this->view->bulletinRow['url_name'];
        
        // Zaznam nejaktualnejsiho bulletinu pro ruzne operace ve view
        $this->view->actual_bul_row = $bulletins->getActualBulletinRow(false);
        
        // Seznam vsech bulletinu pro menu iTrio a inCast
        $this->view->all_bulletins = $bulletins->getBulletinList(true, array('created ASC', 'valid_from ASC'));
        
        
        // Layout podle nastaveni u vydani
        if(!empty($this->view->bulletinRow['layout_name'])){
            Layouts::setLayout($this->view->bulletinRow['layout_name'], $this);
        }
        
        /**
         * nastavi menu pro muj profil
         * 
         */

        // label pro prihlaseni /odhlaseni z projektu defaultne odhlasujeme pokud je del tak prihlasujeme 
        $deregisterLabel = "Odhlášení z projektu";   
        
        if($this->view->user_data["deleted"] != null) {
        $deregisterLabel = "Přihlásit k projektu"; 
        }      
        
        $submenu_forAll =  array(
                '_change' => array('title' => 'Změna osobních údajů', 'params' => array('action' => 'change'), 'noreset' => true),
                '_new' => array('title' => 'Registrace nové emailové adresy', 'params' => array('action' => 'new')),
     
                '_deregister' => array('title' => $deregisterLabel, 'params' => array('action' => 'deregister'))
        );
  
        
        $moduleMenu = new Ibulletin_Admin_ModuleMenu($submenu_forAll, $specific);        
        $this->view->moduleMenu = $moduleMenu;        
    }

    /*
     * Sprava meho profilu
     */
    function indexAction() {
        $user_data = Ibulletin_Auth::getActualUserData();
        $this->view->name = $user_data['name'];
        $this->view->surname = $user_data['surname'];
        $this->view->email = $user_data['email'];        
    }
    
    /*
     * zmena adresy
     */
    function changeAction()
    {
        $db = Zend_Registry::get('db');       
        $config = Zend_Registry::get('config'); 
        $config_texts = Ibulletin_Texts::getSet('myprofile');     

        $user_data = Ibulletin_Auth::getActualUserData();
        $user_id = $user_data['id']; 
        $email = $user_data['email']; 
        $user_name = $user_data["name"];
        $user_surname = $user_data["surname"];       
        
        $_confirmation = $config_texts->change->confirmation;
        $_confirmation = str_replace('%%email_address%%', '<strong>'.$email.'</strong>', $_confirmation);
        $this->view->confirmation = $_confirmation;
        
        // vytvorit formular pro zadani nove adresy
        $form = new Zend_Form();
        $form->setMethod('post');

        // mail
        $formMail = new Zend_Form_Element_Text('email', array('size' => '35'));
        $formMail->setLabel('E-mail');                               
        $formMail->setRequired(true);
        $formMail->setValue($email);
        
       
        
        
        
                        
        // email prazdny?
        $formMailEmpty = new Zend_Validate_NotEmpty();   
        $formMailEmpty->setMessages(array(
            Zend_Validate_NotEmpty::IS_EMPTY => $config_texts->new->validators->isempty
            )
        );               
        $formMail->addValidator($formMailEmpty);

        // email validni?
        $formMailValidator = new Zend_Validate_EmailAddress();
        $formMailValidator->setMessages(array(
            Zend_Validate_EmailAddress::INVALID => $config_texts->new->validators->invalidmail 
        ));
        $formMail->addValidator($formMailValidator);  

        $formName = new Zend_Form_Element_Text('name');
        $formName->setLabel("Jméno");
        $formName->setValue($user_name);
        $formName->removeDecorator('Label');
        $formName->removeDecorator('DtDdWrapper');
        
        $formSurname = new Zend_Form_Element_Text('surname');
        $formSurname->setLabel("Příjmení");
        $formSurname->setValue($user_surname);
        
        $form->addElement($formMail);
        $form->addElement($formName);
        $form->addElement($formSurname);       
        
      
        $formSave = new Zend_Form_Element_Submit('save');
        $formSave->setLabel($config_texts->change->form->submit);                
        $form->addElement($formSave);  

        foreach($form->getElements() as $el) {
        
        $el->removeDecorator("DtDdWrapper");
        $el->removeDecorator("Label");
        }

        // formular validni, ulozime        
        if (empty($_POST) || !$form->isValid($_POST)) {        
            $this->view->form = $form;
            $this->view->email = $config_texts->change->form->email ;
            $this->view->name = $config_texts->change->form->name ;
            $this->view->surname = $config_texts->change->form->surname ;
        }
        else {                   
            $email = $this->getRequest()->getParam('email');
            $name = $this->getRequest()->getParam("name");
            $surname = $this->getRequest()->getParam("surname");
            $params['email'] = $email;
            $params['name'] = $name ;
            $params['surname'] = $surname ;
            
            // musíme nastavit odmazání 
            
           
          // zmenme email v users
            $_old_mail = $db->select()
                         ->from('users', array('email'))
                         ->where('id = ?', $user_id) ;
                       
          $old_mail = $db->fetchOne($_old_mail);
            
           
            try {  

            	
            	/*
                // zmenit docasne email, pokud existuje v DB vyhodi se vyjimka                               
              	$_update_mail = $db->update('users', $params, sprintf('id = %d', $user_id));
                
                
                // nacist id mailu, ktery se posila po zmene mailu (musi byt nadefinovan v Adminu!)
                $_special_email_id = $db->select()
                                ->from('emails', array('id'))
                               ->where('send_after_change = ?', 'true');
                
                $special_email_id = $db->fetchOne($_special_email_id);                
                
                // odesleme mail
                if (!empty($special_email_id)) {
	                $mailer = new Ibulletin_Mailer($config, $db);
	                // Nastaveni pokracovat v odesilani i pri chybach
	                $mailer->setKeepSendingEH();
	                // Posilat email i smazanym uzivatelum
	                $mailer->sendDeletedToo();              
	                // Filtr pro vybrani a pridani pouze jednoho uzivatele
	                $filter = "id = $user_id";               
	                // Zaradime do fronty a nacti id posledniho zaznamu v users_mails
	                $user_emails_mail_id = $mailer->enqueueEmail($special_email_id, $filter);	                	                
	                
	                // odesli tento jeden email
	                $mailer->sendEmails($user_emails_mail_id);
	                
	                // zjisti token
	                $_token = $db->select()
                           ->from('users_emails', array('token'))
                           ->where('id = ?', $user_emails_mail_id); 
                                   
                    $token = $db->fetchOne($_token);
                }
                else {
                    throw new Myprofile_Not_Sent_Exception($config_texts->myprofile->change->notsent);
                }
             
                */
           
                // pokud se odesle mail, zmenime emailovou adresu na starou
            
			
					
				            	
                $params['name'] = $name ;
                $params['surname'] = $surname ;
                
             if($this->view->user_data["deleted"] != null) {
            $params['deleted'] = new Zend_Db_Expr("null");
            
            }
                //$db->update('users', $params, sprintf('id = %d', $user_id));
                Users::updateUser($user_id, $params, true);
                
               
                // dej zaznam do changelistu, ze zmenil adresu a prijde id zaznamu do users_emails
                $data = array(
                    'user_id' =>  $user_id,
                    'email_prev' => $old_mail,
                    'email_next' => $email,
                    'request_date' => new Zend_Db_Expr('current_timestamp')
                );
                $db->insert('change_list', $data);
                $changelist_id = $db->lastInsertId('change_list', 'id');
                

                // pridej id zmeny do users_emails
                $data = array(
                    'sent_to' => $changelist_id
                );                
              
                
                //  $db->update('users_emails', $data, sprintf('id = %d', $user_emails_mail_id));
                
                
                
                // zobraz hlasku
                $this->view->status = $config_texts->change->success;
                
            
                

		//Catch bloky ktere jsou pouzite pri odesilani emailu
		/*
            }
            catch (Myprofile_Not_Sent_Exception $e) {                                
                $this->view->status = $config_texts->myprofile->change->notsent;
                Phc_ErrorLog::warning('Myprofile::changeAction()', $e);
            }
            
            
                
            catch (IBulletinMailerException $e) {                             
                $this->view->status = $config_texts->myprofile->change->queueerr;
                Phc_ErrorLog::warning('Myprofile::changeAction()', $e);
            }
            */

        }catch (Exception $e) {     
                $this->view->status = $config_texts->change->emailused;
                $this->view->form = $form ;
                $this->view->email = $config_texts->change->form->email ;
           		$this->view->name = $config_texts->change->form->name ;
            	$this->view->surname = $config_texts->change->form->surname ;
            }

              //nastavime hodnotu pro menu aby ukazovala na muj profil 
                // kontrola totiz probehla predtim a je potreba ji zmenit protoze
                //vime ze jsme undeleted a email mame nastaveny
                
                $this->view->profileLabel = "Můj profil";
                $this->view->profileAction = "index";   
           
          
        }               
    }
    /*
     * nova adresa
     */
    function newAction()
    {
        $db = Zend_Registry::get('db');       
        $config_texts = Ibulletin_Texts::getSet();
        
        $user_data = Ibulletin_Auth::getActualUserData();
        $email = $user_data['email'];
        
        $_confirmation = $config_texts->confirmation;
        $_confirmation = str_replace('%%email_address%%', '<strong>'.$email.'</strong>', $_confirmation);
        $this->view->confirmation = $_confirmation;
        
        // vytvorit formular pro zadani nove adresy
        $form = new Zend_Form();
        $form->setMethod('post');

        // mail
        $formMail = new Zend_Form_Element_Text('email', array('size' => '35'));
        $formMail->setLabel('E-mail');                               
        $formMail->setRequired(true);
                        
        // email prazdny?
        $formMailEmpty = new Zend_Validate_NotEmpty();   
        $formMailEmpty->setMessages(array(
            Zend_Validate_NotEmpty::IS_EMPTY => $config_texts->validators->isempty
            )
        );               
        $formMail->addValidator($formMailEmpty);

        // email validni?
        $formMailValidator = new Zend_Validate_EmailAddress();
        $formMailValidator->setMessages(array(
            Zend_Validate_EmailAddress::INVALID => $config_texts->validators->invalidmail 
        ));
        $formMail->addValidator($formMailValidator);        
        
        $form->addElement($formMail);
        
        // save        
        $formSave = new Zend_Form_Element_Submit('Registrovat');                
        $form->addElement($formSave);   

        // formular validni, ulozime
        
        if (empty($_POST) || !$form->isValid($_POST)) {        
            $this->view->form = $form;
        }
        else {                   
            $email = $this->getRequest()->getParam('email');
            $params['email'] = $email;
            
            try {
	            if (Ibulletin_Auth::registerUser($params)) {
	                $this->view->status = $config_texts->saved;
	            }
	            else {
	                $this->view->status = $config_texts->notsaved;
	            }
            }
            catch(Exception $e) {
                $this->view->status = $config_texts->savednotsent;
            }
            
        }        
        
      
    } 

    /*
     * odhlasit
     */
    function deregisterAction()
    {
        $user_data = Ibulletin_Auth::getActualUserData();
        $deleted = $user_data['deleted'];          
        $id = $user_data['id']; 
        $db = Zend_Registry::get('db');
        
        $config_texts = Ibulletin_Texts::getSet();

        //pokud je potvrzeno, deregistruj, jinak zobraz formular
        $confirm = $this->getRequest()->getParam('confirmation');
        if (!empty($confirm) && $confirm == 'yes') {
            
            // pokud je prihlasen, tak ho odhlas, jinak prihlas
            if (empty($deleted)) {
                $data['deleted'] = new Zend_Db_Expr('current_timestamp');                
                $this->view->status = $config_texts->messagederegistred;                                                      
            }
            else {
                $data['deleted'] = null;                
                $this->view->status = $config_texts->messageregistred;
            }
            
            //$db->update('users', $data, sprintf('id = %d', $id));
            Users::updateUser($id, $data, true);
        }
        else {
            $form = new Zend_Form();
            $form->setMethod('post');

            // confirmation
            $formConfirmation = new Zend_Form_Element_Hidden('confirmation');
            $formConfirmation->setValue('yes');                
            $form->addElement($formConfirmation);
            
	        // odhlasit
	        $_label = (empty($deleted)) ? 'Odhlásit' : 'Přihlásit';
	        $formSave = new Zend_Form_Element_Submit($_label);                
	        $form->addElement($formSave);   
            $this->view->status  = (empty($deleted)) ? $config_texts->deregconfirmation : $config_texts->regconfirmation;
            $this->view->status  .= $form;	
                    
        }          
      
    }    
    
    /**
     * Zkontroluje, jestli neni uzivatel prihlasen, pokud je a je zapnute
     * tvrde odhlasovani, presmeruje uzivatele na aktualni bulletin.
     */
    private function _checkLoggedIn()
    {
         $config = Zend_Registry::get('config');
         
        // Zjistime, jestli je nekdo prihlasen, a jestli je zapnute tvrde odhlasovani
        // pokud ano, presmerujeme na aktualni bulletin
        $user_data = Ibulletin_Auth::getActualUserData();

        if($user_data === null){
            $this->getHelper('redirector')->setExit(true)
                 ->goto('index','index');
        }
        
    }    
}
