<?php
/**
 * Deregistrace z mailingu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class DeregisterController extends Zend_Controller_Action
{
    /**
     * Provede odhlaseni z mailingu podle tokenu s potvrzovacim dialogem (odhlasit?) 
     * nebo odhlasi s potvrzovacim dialogem aktualne prihlaseneho uzivatele.
     */
    function indexAction()
    {
        $req = $this->getRequest();
        
        //ziskame redirector
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        
        $token = $req->getParam('token', null);
        $nps = $req->getParam('nps', null);
        $user_id = Ibulletin_Auth::getUserIdFromEmailToken($token);
        
        if($user_id != null){
            // Prihlasime uzivatele
            Ibulletin_Auth::setUser($user_id);
        }
        else{
            $user_id = Ibulletin_Auth::getActualUserId();
        }
        
        //pokusime se odhlasit uzivatele
        if($req->getMethod() == 'POST'){
            if($req->getParam('deregister', null) !== null && $user_id){
                //echo $token;
                // Muzeme odhlasit uzivatele z odberu mailu
                if(!empty($token)){
                    // Pro pripad tokenu v URL preferujeme odhlasovani primo timto tokenem
                    $deregistered = $nps ? Ibulletin_Auth::deregisternpsUser($token) : Ibulletin_Auth::deregisterUser($token);
                }
                else{
                    // Odhlaseni z mailingu prihlaseneho uzivatele z webu
                    $deregistered = $nps ? Ibulletin_Auth::deregisternpsUserById($user_id) : Ibulletin_Auth::deregisterUserById($user_id);
                }
                
                // Presmerujeme na odpovidajici stranku v pripade uspechu/neuspechu
                if($deregistered){
                    $redirector->gotoAndExit(
                        'done', 
                        'deregister',
                        null,
                        array('token' => $token)
                        );
                }
                else{
                    $redirector->gotoAndExit(
                        'wrongtoken', 
                        'deregister'
                        );
                }
            }
        }
        elseif($user_id == null){
            // Nepodarilo se najit uzivatele s danym tokenem, presmerujeme na
            // wrong token
            $redirector->gotoAndExit(
                        'wrongtoken', 
                        'deregister'
                        );
        }

        $this->view->nps = $nps;
    }

    
	/**
	 *	Pro deregistraci uživatele bez tokenu, tzn. odkazem ze stránky
     *  bez dotazu - uzivatel je ihned odhlasen (nastaveno deleted a unsubscribed).
	 *
	 * 	@author Martin Krčmář.
	 */
	function deregAction()
	{
        //ziskame redirector
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

		if (Ibulletin_Auth::deregisterActualUser())
		{
			$redirector->gotoAndExit(
				'done',
				'deregister'
			);
		}
		else
		{
			$redirector->gotoAndExit(
				'wrongdereg',
				'deregister'
			);
		}
	}

	function wrongderegAction()
	{
	    $texts = Ibulletin_Texts::getSet();
	    
        $this->view->message = $texts->message;
		//$this->view->title = 'odhlášení';

		Phc_ErrorLog::error(
			'Deregister',
			'Nepodarilo se deregistrovat uzivatele s ID: '.
			Ibulletin_Auth::getInstance()->actual_user_id
		);
        
		$this->getHelper('viewRenderer')->renderScript('info.phtml');
	}
    
    function doneAction()
    {
        $texts = Ibulletin_Texts::getSet();
        
        $this->view->message = $texts->message;
		//$this->view->title = 'odhlášení';
        
        $tip['text'] = $texts->accidentallyderegistered;
        
        $tip['controller'] = 'deregister';
        $tip['action'] = 'renew';
        $tip['params'] = array('token' => $this->getRequest()->getParam('token'));
        $this->view->tip = $tip;
        
        $this->getHelper('viewRenderer')->renderScript('info.phtml');
    }
    
    
    function wrongtokenAction()
    {
        $texts = Ibulletin_Texts::getSet();
        
        //$this->view->message = $texts->deregister->wrongtoken->message;
        $this->view->message = Ibulletin_Texts::get('message').' '.Ibulletin_Texts::get('next');
        
        $tip['text'] = $texts->mistakenentrance;
        
        $tip['controller'] = 'bulletin';
        $tip['action'] = '';
        $tip['params'] = array();
        $this->view->tip = $tip;
        
        $this->getHelper('viewRenderer')->renderScript('info.phtml');
    }
    
    /**
     * Obnovi prave odhlaseneho uzivatele v seznamu pro mailing.
     * (nastavi deleted na NULL)
     */
    function renewAction()
    {
        $texts = Ibulletin_Texts::getSet();
        
        $token = $this->getRequest()->getParam('token', null);
        
        $wrongToken = $texts->wrongemail;
        
        $successful = $texts->reactivated;
        
        if($token !== null){
            // Muzeme obnovit uzivatele v odberu mailu
            $deregistered = Ibulletin_Auth::renewUser($token);
            if($deregistered){
                $this->view->message = $successful;
                $tip['text'] = $texts->wannaregisteragain;
                $tip['controller'] = 'bulletin';
                $tip['action'] = '';
                $tip['params'] = array();
                $this->view->tip = $tip;
            }
            else{
                $this->view->message = $wrongToken;
            }
        }
        else{
            $this->view->message = $wrongToken;
        }
        
        $this->getHelper('viewRenderer')->renderScript('info.phtml');
    }
}
