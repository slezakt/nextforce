<?php
/**
 * Logoff akce.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class LogoffController extends Zend_Controller_Action
{
    function indexAction()
    {
        Ibulletin_Auth::logoffUser();
        
		// Nebudeme nic renderovat ;o)
        $this->getHelper('viewRenderer')->setNoRender(true);
    }
	
	function infoAction() 
	{
		// odhlasime, neprovedeme redirect po odhlaseni
		Ibulletin_Auth::logoffUser(false);			
	}

}
