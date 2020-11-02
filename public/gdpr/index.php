<?php
/**
 * Bootstrap soubor projektu iBulletin, zajistuje nastaveni a inicializaci
 * ruznych komponent Zendu, ktere jsou pouzivany dale v aplikaci.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 * @author Andrej Litvaj, <andrej.litvaj@pearshealthcyber.com>
 */

// Bootstraping
require(realpath(dirname(__FILE__) . '/bootstrap.php'));

// Config
profile('config');
$config = Env::initConfig();

// Logging
profile('logging');
Phc_ErrorLog::initialize();

// Cache
profile('cache');
$cache = Env::initFrontendCache();

// Database
profile('db');
$db = Env::initDatabase();


// Timezone, pozdeji je v Ibulletin_Stats nastavovana podle posledni
// session uzivatele nebo podle informaci ziskanych z uzivatelova browseru
date_default_timezone_set($config->general->default_timezone);

/**
 *
 * Rozhodneme, jestli spoustime bezny frontend s controllerem
 * nebo jestli se jedna o XML-RPC pozadavek.
 *
 */
if(preg_match('/\/xmlrpc[\/]+1\.0/', $_SERVER['REQUEST_URI'])){
    require_once('bootstrap-xmlrpc.php');
    // KONEC
    exit;
}

// Session
profile('session');
Env::initSession();

// Locale
profile('locale');
Env::initLocale();

// Front controller
$frontController = Zend_Controller_Front::getInstance();

//$frontController->registerPlugin(new Zend_Controller_Plugin_ErrorHandler());
//$frontController->throwExceptions(true);

// Debugger
if (!Env::isProductionMode()) {
    profile('debugger');
    $debug = Env::initDebug();
    $frontController->registerPlugin($debug);
}

profile('front_controller');
$frontController->setControllerDirectory(array('default' => 'controllers'));

// Nastavime adresar controlleruu pro modul admin
$frontController->addControllerDirectory('admin/controllers/', 'admin');
$frontController->addModuleDirectory('admin/controllers/');

// Stahnout nastavit predkonfigurovane cesty z config.ini, sekce routes
$router =  $frontController->getRouter();
$router->addConfig($config, 'routes');
$routes = $router->getRoutes();

// Nastavit namespace pro nase filtry
Zend_Filter::setDefaultNamespaces('Ibulletin_Filter');

// Zakladni prednastaveni a inicializace view
$viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer();
// Vlozime vlastni view script - oproti Zend_View vypina logovani E_NOTICE
$viewRenderer->setView(new Phc_View());
// View renderer (from the helper broker)
$viewRenderer->init();
// Konfigurace view
$viewRenderer->view->setEncoding('UTF-8');
// Pridani cesty k templatum pro polozky z tabulky
$viewRenderer->view->addScriptPath($config->paths->page_templates);
$viewRenderer->view->addScriptPath($config->paths->content_templates);
$viewRenderer->view->addScriptPath($config->paths->layout_templates);
// Pridani cesty k dalsim view helperum
$viewRenderer->view->setHelperPath('Ibulletin/View/', 'Ibulletin_View_Helper');
//Inicializujeme pole pro stylesheety
$viewRenderer->view->stylesheets = array();
// Nastavime do view config, aby byl dostupny v templatech
$viewRenderer->view->config = $config;
// Nastavime do view project_name a project_email
$viewRenderer->view->project_name = $config->general->project_name;
// Natahneme dalsi pady pro project name, pripadne nejakych dalsich nazvuu
for($i=2; $i<8; $i++){
    $case = 'p'.$i;
    if(!empty($config->general->$case->project_name)){
        $viewRenderer->view->$case = $config->general->$case;
    }
}
$viewRenderer->view->project_email = $config->general->project_email;
//Priradime view renderer
Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);

profile('response');
// Vytvorime a pridame response do front controlleru, aby bylo
// jeste pred dispatchnutim mozne provadet operace jako redirect.
$response = new Zend_Controller_Response_Http();
$frontController->setResponse($response);

// Provest routovani a operace specificke pro ruzne moduly
// (predevsim overeni autentizace)
$request = new Zend_Controller_Request_Http();
$frontController->setRequest($request);
$router->route($request);

$moduleName = $request->getModuleName();

//pro ErrorController rovnou dispatchnem, kvuli nebezpeci redirect loop
if ($frontController->getRequest()->getControllerName() == "error") {
   $frontController->dispatch(); 
   exit();
}

if($moduleName == 'admin'){
    // Pridame cestu ke sdilenym sablonam v adminu
    $viewRenderer->view->addScriptPath('admin/views/scripts/');
    //Pripravime admin
    new Ibulletin_AdminPrepare();
    // Spustime cache pro admin
    //Env::initAdminCache();
}
else{ //Pouzije se pro hlavni cast webu - ta neni modulem
	profile('stats');
	Ibulletin_Stats::getInstance();
    
        profile('auth');        
    Ibulletin_Auth::getInstance()->prepare();
    
        // Spustime akce, ktere se maji vykonat pred jakymkoli controllerem:
    require(APPLICATION_PATH . '/controllers/_run_before_any_controller.php');
    new Run_Before_Controller(); 
}
    
    // Run - dispatchneme to
profile('dispatch');
$frontController->dispatch();
profile('dispatch');
