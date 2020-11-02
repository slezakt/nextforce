<?php
/**
 * Stara se o prihlaseni a presmerovani uzivatele pomoci adresy s tokenem
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class TokenaccessController extends Zend_Controller_Action
{
    /**
     * Provede overeni tokenu a presmeruje na stranku skrytou pod tokenem.
     * 
     * Pokud je v URL pridan parametr /register/[registerAction], bude pred vpustenim
     * na samotny obsah uzivatel presmerovan na akci [registerAction] z RegisterController.
     * Uklada do $session->registerActionRedirectParams pole s parametry pro redirect po registraci. 
     */
    function doAction()
    {
        $req = $this->getRequest();
        $token = $req->getParam('token');
        
        // Pro pripadne pozadovani vyplneni registracni akce uzivatelem pred vstupem
        $register = $req->getParam('register');
        
        // Pokud se ma vynuceni registracniho formulare ridit nejakym parametrem, nacteme
        $registerParam = $req->getParam('regparam');
        
        Zend_Loader::loadClass('Ibulletin_Auth');
        Ibulletin_Auth::getInstance()->authByToken($token, !(bool)$register); // Redirectujeme pokud neni zadan register
        
        // Presmerujeme nejdrive na registraci
        if($register){
            // Ulozime do session, kam se ma po registraci redirectovat
            $session = Zend_Registry::get('session');
            $session->registerActionRedirectParams = array('action' => 'do', 'controller' => 'tokenaccess', 'token' => $token, 'module' => null);
            // Spustime registraci
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoRouteAndExit(array('controller' => 'register', 'action' => $register, 'ignoreloggedin' => 1), 'default');
            //$this->_forward($register, 'register', null, array('ignoreloggedin' => 1));
        }
        
        // Nerenderovat zadny view skript
        $this->_helper->viewRenderer->setNoRender();
    }
}
