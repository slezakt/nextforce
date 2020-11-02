<?php
/**
 * Zajistuje inicializaci a predani parametru Zend_Xmlrpc_Serveru.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class XmlrpcController extends Zend_Controller_Action
{
    /**
     * Provadi vsechny akce teto tridy
     */
    public function indexAction()
    {
        ini_set('display_errors', 0);
        error_reporting(E_ALL);
        
        // Vytvorit instanci RPC serveru
        $server = new Zend_XmlRpc_Server();
        
        // Pridani trid dostupnych pres RPC
        $server->setClass('RpcTest');
        
        $response = $server->handle();
        
        // Logovani celeho requestu
        //Phc_ErrorLog::warning('XmlrpcController::indexAction()', $server->getRequest()->getFullRequest());
        
        //Phc_ErrorLog::debug('XmlrpcController::indexAction()', $response);
        // Zjistime, jestli nenastala chyba, pokud ano, zalogujeme vyjimku
        if($response instanceof Zend_XmlRpc_Server_Fault){
            $e = $response->getException();
            Phc_ErrorLog::warning('XmlrpcController::indexAction()', $e);
        }
        
        echo $response;
        
        // Nerenderovat zadny view skript
        $this->_helper->viewRenderer->setNoRender();
    }
    
}