<?php
/**
 * ServerFacadeAbstract.php
 * 
 * @author  Bc. Petr Skoda
 */

/**
 * 
 * 
 * @author Bc. Petr Skoda
 */
abstract class Communicator_ServerFacadeAbstract
{
    /**
     * DB adapter
     * 
     * @var Zend_Db_Adapter_Abstract
     */
    public $db = null;
    
       
    /**
     * Nastavi DB adapter.
     * 
     * @param Zend_Db_Adapter_Abstract
     */
    public function __construct($db = null)
    {
        $this->db = $db;
    }
    
    
    /**
     * Overi prijaty klic proti klici teto aplikace.
     * @param string    Klic k overeni.
     * @return bool     Je klic platny?
     */
    protected function checkKey($key)
    {
        $config = Zend_Registry::get('config');
        if($key == $config->indirector->key){
            return true;
        }
        
        return false;
    }
}