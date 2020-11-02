<?php 
/**
 * bootstrap for both Apache and CLI
 */

// detect if we are running CLI
define('ISCLI', PHP_SAPI === 'cli');

// set initial date timezone
if(function_exists("date_default_timezone_set")){
    date_default_timezone_set("Europe/Prague");
}

// Application environment (production/development)
defined('APPLICATION_ENV') ||
    define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?
        trim(strtolower(getenv('APPLICATION_ENV'))) :
        'production'
    );

// Application base path ~ absolute filesystem path to root with no trailing slash
defined('APPLICATION_PATH')	|| 
	define('APPLICATION_PATH',
		rtrim(
		getenv('APPLICATION_PATH') ? getenv('APPLICATION_PATH') : realpath(dirname(__FILE__))
		,'\\/')
	);

// Application base URL ~ full URI with no trailing slash
if (!ISCLI) {
	define('APPLICATION_URL',
			rtrim(
			'http'.(empty($_SERVER['HTTPS']) ? '' : 's') . '://'
			.$_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'])
			,'\\/')
	);
} else {
	define('APPLICATION_URL', APPLICATION_PATH);
}

// Nastaveni cesty k PHC legacy kniznicim
defined('PHC_LEGACY_PATH')
	|| define('PHC_LEGACY_PATH',
			(getenv('PHC_LEGACY_PATH') ? getenv('PHC_LEGACY_PATH') : '/home/lekarnik/php-include2/'));

// Nastaveni cesty k mPDF
defined('_MPDF_PATH')
	|| define('_MPDF_PATH',APPLICATION_PATH . '/library/mpdf/');

set_include_path(implode(PATH_SEPARATOR, array(
		APPLICATION_PATH . '/library/',
		APPLICATION_PATH . '/models/',
		PHC_LEGACY_PATH,
		get_include_path(),
)));

// ** global predefined functions **

/**
 * alias for PHP5 microtime(get_as_float = true) function.
 */
function getmicrotime(){
    return microtime(true);
}

/**
 * alias for PHP5.4 hex2bin() function.
 */
if(!function_exists('hex2bin')){
    function hex2bin($data) {
        $len = strlen($data);
        $newdata = '';
        for($i=0;$i < $len;$i+=2) {
            $newdata .= pack("C",hexdec(substr($data,$i,2)));
        }
        return $newdata;
    }
}

/**
 * Profiling function, functional only in debugging environment
 * second call with same ident stops the profiling of resource
 *
 * @param ident name of profiling resource
 * @return void
 */
function profile($ident = 'default'){

    // TODO: add flag 'profiling' to config
    // in production mode (with profiling enabled) log DEBUG events to logger
    if (Env::isProductionMode()) { return; }

    $zfDebug = Zend_Controller_Front::getInstance()->getPlugin('ZFDebug_Controller_Plugin_Debug');
    $key = null;
    if (isset($GLOBALS['profiling']) && ($key = array_search(true,$GLOBALS['profiling']))) {
        if ($zfDebug) $zfDebug->getPlugin('Time')->mark($key);
        // Stopwatch::stop($key);
        $GLOBALS['profiling'][$key] = false;
    }

    if ($key != $ident) {
        if ($zfDebug) $zfDebug->getPlugin('Time')->mark($key);
        // Stopwatch::start($ident);
        $GLOBALS['profiling'][$ident] = true;
    }
}

// ** Autoloading **
/*
require_once 'Zend/Loader.php';
function __autoload($class_name) {
	// Workaround kvuli spatnemu zpracovani self v PHP < 5.3
	if($class_name !== 'self'){
		Zend_Loader::loadClass($class_name);
	}
	else{
		return false;
	}
}
*/
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();

//$autoloader->registerNamespace('Phc');
//$autoloader->registerNamespace('Ibulletin');
//$autoloader->registerNamespace('Admin');
$autoloader->setFallbackAutoloader(true); // uses Zend_Loader


/**
 * Environment class holding methods for bootstraping (resource initialization)
 */
class Env {

    /**
     * Initializes cache for frontend pages.
     *
     * @return Zend_Cache_Core
     */
    static public function initFrontendCache()
    {
        // Get config
        try {
            $config = Zend_Registry::get('config') ;
        } catch (Zend_Exception $e) {
            $config = self::initConfig();
        }


        $frontendOptions = array(
            'lifetime' => 7200,
            'http_conditional' => true,
            //'debug_header' => true, // for debugging
            'memorize_headers' => array('content-type', 'Content-Length', 'Last-Modified', 'Expires', 'Cache-Control', 'Pragma'),
            'ignore_user_abort' => true,
            'regexps' => array(
                'javascriptcode' => array(   // javascriptcode
                    'cache' => (bool)$config->cache->cacheJavascript,
                    'cache_with_session_variables' => true,
                    'cache_with_cookie_variables' => true,
                    'make_id_with_session_variables'=>false,
                    'make_id_with_cookie_variables'=>false,
                ),
                '/service/getchart/' => array(   // Get chart
                    'cache' => true,
                    'cache_with_session_variables' => true,
                    'cache_with_cookie_variables' => true,
                    'cache_with_get_variables' => true,
                    'cache_with_post_variables' => true,
                    'make_id_with_session_variables'=>false,
                    'make_id_with_cookie_variables'=>false,
                    'make_id_with_get_variables' => true,
                    'make_id_with_post_variables' => true,
                ),
            ),
            'default_options' => array(
                'cache'=>false,
                //'cache_with_session_variables'=>true,
                //'cache_with_cookie_variables'=>true,
                //'make_id_with_session_variables'=>true,
                //'make_id_with_cookie_variables'=>true
            )
        );

        $backendOptions = array(
            'cache_dir' => 'cache/' // Directory where to put the cache files
        );

        $cache = Zend_Cache::factory('Page', 'File', $frontendOptions, $backendOptions);
        $cache->start();

        return $cache;

    }

    /**
     * Initializes admin cache
     *
     * @return Zend_Cache_Core
     */
    static public function initAdminCache()
    {
        $frontendOptions = array(
            'lifetime' => 7200,
            'http_conditional' => true,
            //'debug_header' => true, // for debugging
            'memorize_headers' => array('content-type', 'Content-Length', 'Last-Modified', 'Expires', 'Cache-Control', 'Pragma'),
            'ignore_user_abort' => true,
            'regexps' => array(
                '(/admin/stats/)' => array(   // Statistiky
                    'cache' => true,
                    'cache_with_session_variables' => true,
                    'cache_with_cookie_variables' => true,
                    'cache_with_get_variables' => true,
                    'cache_with_post_variables' => true,
                    'make_id_with_session_variables'=>false,
                    'make_id_with_cookie_variables'=>false,
                    'make_id_with_get_variables' => true,
                    'make_id_with_post_variables' => true,
                )
            ),
            //*
            'default_options' => array(
                'cache'=>false,
                //'cache_with_session_variables'=>true,
                //'cache_with_cookie_variables'=>true,
                //'make_id_with_session_variables'=>true,
                //'make_id_with_cookie_variables'=>true
            )
            //*/
        );

        $backendOptions = array(
            'cache_dir' => 'cache/admin/' // Directory where to put the cache files
        );

        $cache = Zend_Cache::factory('Page', 'File', $frontendOptions, $backendOptions);
        $cache->start();

        return $cache;

    }

    /**
     * Initializes session
     *
     * @return Zend_Session_Namespace
     */
    static public function initSession() {
		$config = Zend_Registry::get('config') ;
    	$options = array();
    	// Umoznit PHP session ID predavat pres HTTP GET?
    	if($config->general->allow_session_id_in_get){
    		$options['use_only_cookies'] = false;
    	}
    	// Zivotnost session cookie
    	if($config->general->cookies_timeout){
    		$options['cookie_lifetime'] = $config->general->cookies_timeout;
    	}
        Zend_Session::start($options);
        $globalSessionNamespace = new Zend_Session_Namespace($config->sessions->namespace->global);
        Zend_Registry::set('session', $globalSessionNamespace);
        return $globalSessionNamespace;

    }

    /**
     * Initializes locale
     *
     * @return Zend_Locale
     */
    static public function initLocale() {

        // Zend uses mb_internal_encoding on many places to determine the encoding
        mb_internal_encoding('UTF-8');

        try {
            $config = Zend_Registry::get('config') ;
        } catch (Zend_Exception $e) {
            $config = self::initConfig();
        }

        $locale = new Zend_Locale($config->general->locale);
        Zend_Registry::set('locale', $locale);

        return $locale;

    }


    /**
     * Initializes database
     *
     * @return Zend_Db_Adapter_Abstract
     */
    static public function initDatabase() {

        try {
            $config = Zend_Registry::get('config');
        } catch (Zend_Exception $e) {
            $config = self::initConfig();
        }

        $db = Zend_Db::factory($config->database);
        Zend_Registry::set('db', $db);

        return $db;

    }

    /**
     * Initializes debugging
     *
     * @return object of debugger
     */
    static public function initDebug() {

        try {
            $db = Zend_Registry::get('db');
        } catch (Zend_Exception $e) {
            $db = self::initDatabase();
        }

        try {
            $cache = Zend_Registry::get('cache');
        } catch (Zend_Exception $e) {
            $cache = self::initFrontendCache();
        }
        // Leave 'Database' options empty to rely on Zend_Db_Table default adapter
        $options = array(
            // requires jQuery !!!
            'jquery_path' => APPLICATION_URL . '/pub/scripts/jquery.js', //http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js',

            'plugins' => array('Variables',
                'Html',
                'Database' => array('adapter' => array('standard' => $db)),
                'File' => array('base_path' => APPLICATION_PATH),
                'Memory',
                'Time',
                'Registry',
                'Cache' => array('backend' => $cache->getBackend()),
                'Exception')
        );

        $debug = new ZFDebug_Controller_Plugin_Debug($options);
        Zend_Registry::set('debug', $debug);

        return $debug;
    }

    /**
     * Initializes configuration files.
     *
     * Initialization takes place only once.
     *
     * @return Zend_Config
     */
    static public function initConfig() {
        if($config = Zend_Registry::isRegistered('config')){
            // Config is already prepared
            return $config;
        }

        // configuration object
        $config = null;

        // absolute paths to configuration files
        $configFile 		 = APPLICATION_PATH . '/config.ini';
        $configFileDefault	 = APPLICATION_PATH . '/config_default.ini';
        $configFileDatabase	 = APPLICATION_PATH . '/config_db.ini';
        $configFileTables	 = APPLICATION_PATH . '/config_tables.ini';
        $configFileAdmin	 = APPLICATION_PATH . '/config_admin.ini';
        $configFileGenerated = APPLICATION_PATH . '/generated_cfg/config.ini';
        $configAplicationUrl = APPLICATION_PATH . '/generated_cfg/.application_url';
        
        //for use in CLI, generate application url to file
        if (!file_exists($configAplicationUrl)) {

            if (!ISCLI) {
                file_put_contents($configAplicationUrl, APPLICATION_URL);
            } else {
                Phc_ErrorLog::error('CLI', $configAplicationUrl . ' was not generated, unable to get aplication url!
				Resolve by making HTTP request to project domain to generate configuration.');
                exit(1);
            }
        }

        // check if we have generated config for CLI environment
        if(ISCLI && !file_exists($configFileGenerated)) {
            Phc_ErrorLog::error('CLI', $configFileGenerated.' was not generated, unable to expand tags from CLI!
				Resolve by making HTTP request to project domain to generate configuration.');
            exit(1);
        }

        if ( !ISCLI // watch for modification of configuration files
            && ( ! file_exists($configFileGenerated)
                || filemtime($configFileGenerated) < filemtime($configFileDefault)
                || filemtime($configFileGenerated) < filemtime($configFile)
                || filemtime($configFileGenerated) < filemtime($configFileDatabase)
                || filemtime($configFileGenerated) < filemtime($configFileTables)
                || filemtime($configFileGenerated) < filemtime($configFileAdmin)
            ))
        {
            // prepare configuration object, merge all config files
            try {
                $config = new Zend_Config_Ini($configFileDefault, null, array('allowModifications' => true));
                $config->merge(new Zend_Config_Ini($configFile));
                $config->merge(new Zend_Config_Ini($configFileDatabase));
                $config->merge(new Zend_Config_Ini($configFileTables, 'tables'));  // reduce the section level
                $config->merge(new Zend_Config_Ini($configFileAdmin));

                // TODO: eliminate unnecessary IO operations by
                // iterating config object hierarchy

                // store configuration into file
                $writer = new Zend_Config_Writer_Ini();
                $writer->write($configFileGenerated, $config);

                // read from file and expand tags
                $content = file_get_contents($configFileGenerated);

                $content = str_replace(array(
                        '%%cfg_mail_folder%%',
                        '%%cfg_mail_url%%',
                        '%%cfg_mail_def_email%%',
                        '%%cfg_mail_def_project_name%%'
                    ), array(
                        APPLICATION_PATH . '/',
                        APPLICATION_URL . '/',
                        $config->general->project_email,
                        $config->general->project_name
                    ), $content
                );

                // write back
                file_put_contents($configFileGenerated, $content);

            } catch (Zend_Config_Exception $e) {
                Phc_ErrorLog::warning('config', $e->getMessage());
            }
        }
        // read configuration from generated file
        if (!$config) {
            try {
                $config = new Zend_Config_Ini($configFileGenerated);
            } catch (Zend_Config_Exception $e) {
                Phc_ErrorLog::warning('config', $e->getMessage());
            }
        }

        // save into registry
        Zend_Registry::set('config', $config);

        return $config;

    }

    /**
     * checks whether current environment is in production mode,
     * that means no debugging output is sent
     *
     * @return bool
     */
    static public function isProductionMode() {

        return APPLICATION_ENV == 'production';
        /*if (!defined('TEST') || (defined('TEST') && TEST != 1)) {
            return true;
        }
        return false;*/
    }

    /**
     * disables debugging output (in development mode)
     *
     * @return void
     */
    static public function disableOutput() {

        if (!self::isProductionMode()) {
            $front = Zend_Controller_Front::getInstance();
            if ($front && $front->getRequest()) {
                $front->getRequest()->setParam('ZFDEBUG_DISABLE', true);
            }
            //NDebugger::$bar = new NDummyBar();
        }
    }

}