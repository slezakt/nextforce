<?php
/*
// CONFIG
require_once 'Zend/Config/Ini.php';
$config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

// AUTOLOADER
require_once 'Zend/Loader/Autoloader.php';
$loader = Zend_Loader_Autoloader::getInstance();
//$loader->registerNamespace($config->appnamespace.'_');
//$loader->registerNamespace('Model_');
$autoloader = new Zend_Loader_Autoloader_Resource(array(
    //'namespace' => $config->appnamespace.'_',
    'namespace' => 'Indir_',
    'basePath' => APPLICATION_PATH
    ));
$autoloader->addResourceType('models', 'models', 'Model_');




// Databaze
$db = Zend_Db::factory($config->resources->db);
Zend_Db_Table::setDefaultAdapter($db);
*/

// Vsechny chyby posilame do error handleru
try{
     @ob_end_clean();
     header("Connection: close");
     ob_start();
    
    
    /**
     * Nachystame communicator a vyridime pozadavek
     */
    $com = Communicator_ServerAbstract::factory($db);
    $com->prepare();
    echo $com->dispatch();
    //flush();
    
    
    $size = ob_get_length();
    header("Content-Length: $size");
    ob_end_flush(); // Strange behaviour, will not work
    flush();            // Unless both are called !
        
    
} 
catch(Exception $e){
    // Chyby posilame jen do logu
    Phc_ErrorLog::warning('bootrstrap-xmlrpc', $e);
}
