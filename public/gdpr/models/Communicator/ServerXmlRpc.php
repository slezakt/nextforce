<?php
/**
 * ServerXmlRpc.php
 * 
 * @author  Bc. Petr Skoda
 */

/**
 * 
 * @author Bc. Petr Skoda
 */
class Communicator_ServerXmlRpc extends Communicator_ServerAbstract
{
    /**
     * @var Zend_XmlRpc_Server
     */
    private $_server = null;
    
    /**
     * Pripravi Zend_XmlRpc_Server, pouziva cache.
     * 
     * TODO Cachefile asi presunout do configu...
     */
    public function prepare()
    {
        $cacheFile = 'cache/xmlrpc/xmlrpc.cache';
        
        $server = new Zend_XmlRpc_Server();
        //$server->sendArgumentsToAllMethods(false); // Zend 0.6 neumi
         
        if(//true || 
            !Zend_XmlRpc_Server_Cache::get($cacheFile, $server)) {
            $classes = $this->getFacades();
            foreach($classes as $val){
                $server->setClass($val, null, $this->db);
            }
         
            Zend_XmlRpc_Server_Cache::save($cacheFile, $server);
        }
        
        $this->_server = $server;
    }
    
    
    /**
     * Provede vyrizeni XML-RPC pozadavku
     */
    public function dispatch()
    {
        echo $this->_server->handle();
    }
    
}