<?php

/**
 *	Kontroler pro psana mailu uzivateli.
 *
 *	@author Martin Krcmar
 *  @author Petr Skoda
 */
class WriteusController extends Zend_Controller_Action
{
	public function indexAction()
	{
        Zend_Loader::loadClass('Zend_Validate_EmailAddress');
		Zend_Loader::loadClass('Ibulletin_Auth');
        Zend_Loader::loadClass('Ibulletin_ShowContent');

        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $request = $this->getRequest();

		if ($request->isPost())
		{
			// Kontrolujeme validitu dat zadanych ve formulari
		    $this->error = FALSE;

		    // Name
			if ($request->__isSet('name') &&
				$request->getParam('name') !== '')
			{
				$name = $request->getParam('name');
			}
			else
			{
				$this->error = TRUE;
				$nameError = TRUE;
				$this->view->nameErrorMessage = Ibulletin_Texts::get('necessary_to_fill_name');
			}

			// Email
			if ($request->__isSet('email') &&
		    	$request->getParam('email') !== '')
			{
				$emailValidator = new Zend_Validate_EmailAddress();
				$email = $request->getParam('email');
				if (!$emailValidator->isValid($email))
				{
					$this->view->emailErrorMessage = Ibulletin_Texts::get('got_bad_email');
					$this->error = TRUE;
					$emailError = TRUE;
				}
			}
			else
			{
				$this->error = TRUE;
				$emailError = TRUE;
				$this->view->emailErrorMessage = Ibulletin_Texts::get('necessary_to_fill_email');
			}

			// Message
			if ($request->__isSet('message') &&
				$request->getParam('message') !== '')
			{
				$message = $request->getParam('message');
			}
			else
			{
				$this->error = TRUE;
				$messageError = TRUE;
				$this->view->messageErrorMessage = Ibulletin_Texts::get('question_must_not_be_empty');
			}


			// pokud nenastala chyba, odesleme dotaz
			if (!$this->error){
				if (!$this->sendMessage($name, $email, $message)){
					// Nepodarilo se odeslat, napiseme info uzivateli.
				    if (!isset($emailError))
                        $this->view->email = $email;
                    if (!isset($nameError))
                        $this->view->name = $name;
                    if (!isset($messageError))
                        $this->view->message = $message;

                    $this->view->messageErrorMessage = Ibulletin_Texts::get('sending_failed');
				}
				else{
					$redirector->gotoRouteAndExit(
						array('action' => 'done'),
						'writeus'
					);
				}
			}
			// zobrazime ve formulari ta pole, ktera byla zadana spravne
			else
			{
				if (!isset($emailError))
					$this->view->email = $email;
				if (!isset($nameError))
					$this->view->name = $name;
				if (!isset($messageError))
					$this->view->message = $message;
			}
		}
		else
		{
			// predvyplneni dat ve formulari daty uzivatele
			$user = Ibulletin_Auth::getActualUserData();
			$this->view->email = $user['email'];
			$this->view->name = "$user[name] $user[surname]";
		}


        // Pripravime data pro menu
        $showCont = Ibulletin_ShowContent::getInstance();
        $this->view->menu = $showCont->renderMenu($bulletin_id);

        // Zapiseme url_name bulletinu do view
        $bulletins = new Bulletins();
        // Cely radek bulletinu
        $this->view->bulletinRow = $bulletins->get($bulletin_id);
        // Nazev bulletinu, k pouziti na strance
        $this->view->bulletin_name = $this->view->bulletinRow['name'];
        // Zapiseme url_name current bulletinu do view
        $this->view->bul_url_name = $this->view->bulletinRow['url_name'];

        // Breadcrumbs (nekompatibilni s breadcrumbs z BulletinController)
        $this->view->breadcrumbs = array(
            Ibulletin_Texts::get('writeus_heading') => array(
                'params' => array(),
                'route_name' => 'writeus'),
            );
            
        
        // Layout podle nastaveni u vydani
        if(!empty($this->view->bulletinRow['layout_name'])){
            Layouts::setLayout($this->view->bulletinRow['layout_name'], $this);
        }
	}

	/**
	 *	Při úspěšeném odeslání zprávy.
	 */
	public function doneAction()
	{
	    // Pripravime data pro menu
        $showCont = Ibulletin_ShowContent::getInstance();
        $this->view->menu = $showCont->renderMenu($bulletin_id);

        // Zapiseme url_name bulletinu do view
        $bulletins = new Bulletins();
        // Cely radek bulletinu
        $this->view->bulletinRow = $bulletins->get($bulletin_id);
        // Nazev bulletinu, k pouziti na strance
        $this->view->bulletin_name = $this->view->bulletinRow['name'];
        // Zapiseme url_name current bulletinu do view
        $this->view->bul_url_name = $this->view->bulletinRow['url_name'];

        // Breadcrumbs (nekompatibilni s breadcrumbs z BulletinController)
        $this->view->breadcrumbs = array(
            Ibulletin_Texts::get('writeus.index.writeus_heading') => array(
                'params' => array(),
                'route_name' => 'writeus'),
            );

        // Zprava o odeslani
		$this->view->message = Ibulletin_Texts::get('message_sent_thanks');
        
        // Layout podle nastaveni u vydani
        if(!empty($this->view->bulletinRow['layout_name'])){
            Layouts::setLayout($this->view->bulletinRow['layout_name'], $this);
        }

        return;
	}

	/**
	 *	Metoda odešle email s dotazem.
	 *
	 *	@param Jméno uživatele.
	 *	@param Email uživatele.
	 *	@param Text zprávy.
	 */
	public function sendMessage($name, $email, $message)
	{
		Zend_Loader::loadClass('Zend_Mail');
		Zend_Loader::loadClass('Zend_Mail_Transport_Sendmail');
		Zend_Loader::loadClass('Zend_Validate_EmailAddress');

        $config = Zend_Registry::get('config');
		$mail = new Zend_Mail('UTF-8');
		$validator = new Zend_Validate_EmailAddress();
		if (!$validator->isValid($config->mailer->return_path))
		{
		    $e = new Exception;
			Phc_ErrorLog::error('Writeus controller',
				'Neplatna return_path adresa v configu. '.$e);

			return FALSE;
		}

		$transport = new Zend_Mail_Transport_Sendmail('-f'.$config->mailer->return_path);
		Zend_Mail::setDefaultTransport($transport);

        // Overime existenci techto promennych v configu
		if(isset($config->writeus) && isset($config->writeus->to)){
		    $addTos = $config->writeus->to->toArray();
        }
        else{
            $addTos = array();
        }

		if (empty($addTos))
		{
		    $e = new Exception;
			Phc_ErrorLog::error('Writeus controller',
				'V configu nebyla nalezena zadna adresa, na kterou by se mel
				odeslat dotaz. '.$e);

			return FALSE;
		}

		foreach ($addTos as $key => $addr)
		{
			if ($validator->isValid($addr))
				$mail->addTo($addr);
			else{
			    $e = new Exception;
				Phc_ErrorLog::error('Writeus controller',
					'Neplatna adresa prijemnce dotazu: '.$addr.'. '.$e);
			}
		}

		$mail->setFrom($email, $name)
			->setBodyText($message)
			->setSubject($config->writeus->subject);

		try
		{
			return $mail->send();
		}
		catch (Exception $e)
		{
			Phc_ErrorLog::error('Writeus controller',
				'Nepodarilo se odeslat dotaz.'.$e);

			return FALSE;
		}
	}
}

?>
