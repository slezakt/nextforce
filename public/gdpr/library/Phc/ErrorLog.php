<?php
/**
 * PHC error handler - objektovy interface pro Zend
 * 
 * Pouziti:
 * Na zacatku aplikace se pouze spusti metoda initialize a tim se zajisti
 * nacteni vseho potrebneho.
 * <code>
 * Phc_ErrorLog::initialize();
 * </code>
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Phc_ErrorLog
{
    /**
     * Instance tridy.
     *
     * @var Phc_ErrorLog
     */
    public static $_origErrorHandler = null;
    public static $_last_error = null;
    public static $_errors = array();
    
    private static $_logger = null;
    private static $_priorities = null;
    private static $_formatter = null;
    
    
    /**
     * Nacte konfiguraci error logu (zatim ulozena po staru v php souboru) 
     * a naincluduje potrebne knihovny.
     */
    public static function initialize()
    {
        // inicialni nastaveni zachycovani vsech chyb
        error_reporting(-1);
        self::initLogger();
    }

    
    /**
     * Provede zalogovani chyby vyznamnosti LOG_DEBUG pomoci error handleru.
     * 
     * pro ladeni - start funkce, konec funkce,... Zprava je
     * zalogovana pres syslog do /var/log/php.debug. Narozdil od LOG_NOTICE se ladici
     * zpravy nikdy nezobrazuji na obrazovce a jsou ignorovany, pokud je
     * definováno TEST=true
     *
     * @param string identifikace casti IS, ke ktere se log vaze, napr. php-include,
     *               nazev submodulu,...
     * @param string Logovany retezec - musi byt co nejpopisnejsi
     */
    public static function debug($ident, $message)
    {   
        self::getLogger()->log('['. $ident . '] ' . $message, Zend_Log::DEBUG);
    }
    
    /**
     * Provede zalogovani chyby vyznamnosti LOG_INFO pomoci error handleru
     *
     * Pro mene dulezite informace,... 
     * Trigger_error() bude volana s E_USER_NOTICE.
     *
     * @param string identifikace casti IS, ke ktere se log vaze, napr. php-include,
     *               nazev submodulu,...
     * @param string Logovany retezec - musi byt co nejpopisnejsi
     */
    public static function info($ident, $message)
    {
       trigger_error('['. $ident . '] ' . $message, E_USER_NOTICE);
    }
    
    /**
     * Provede zalogovani chyby vyznamnosti LOG_NOTICE pomoci error handleru
     *
     * Pro mene dulezite informace,... 
     * Trigger_error() bude volana s E_USER_NOTICE.
     *
     * @param string identifikace casti IS, ke ktere se log vaze, napr. php-include,
     *               nazev submodulu,...
     * @param string Logovany retezec - musi byt co nejpopisnejsi
     */
    public static function notice($ident, $message)
    {
        trigger_error('['. $ident . '] ' . $message, E_USER_NOTICE);
    }
    
    /**
     * Provede zalogovani chyby vyznamnosti LOG_WARNING pomoci error handleru
     *
     * Pro chyby od uzivatele
     * Trigger_error() bude volana s E_USER_WARNING
     *
     * @param string identifikace casti IS, ke ktere se log vaze, napr. php-include,
     *               nazev submodulu,...
     * @param string Logovany retezec - musi byt co nejpopisnejsi
     */
    public static function warning($ident, $message)
    {
        trigger_error('['. $ident . '] ' . $message, E_USER_WARNING);
    }
    
    /**
     * Provede zalogovani chyby vyznamnosti LOG_ERR pomoci error handleru
     *
     * Pro kriticke chyby, ktere znemoznuji dalsi beh aplikace - nelze
     * se spojit s DB,... 
     * Trigger_error() bude volana s E_USER_ERROR.
     *
     * @param string identifikace casti IS, ke ktere se log vaze, napr. php-include,
     *               nazev submodulu,...
     * @param string Logovany retezec - musi byt co nejpopisnejsi
     */
    public static function error($ident, $message)
    {
        trigger_error('['. $ident . '] ' . $message, E_USER_ERROR);
    }
    
        /**
     * Error handler just to catch last error warnings, notices and recoverable errors.     *
     * @return void
     * @internal
     */
    static public function _errorHandler($errno, $errstr, $errfile, $errline, $errcontext) {

        // store error
        self::$_last_error = array(
            'type' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            //'context' => $errcontext
        );

        // suppress error messages based on error_reporting
        $suppress_error = (boolean) !($errno & error_reporting());
        if ($suppress_error) return true;

        // add error for use in debugger
        self::$_errors[] = self::$_last_error;

        $logger = self::getLogger();
        // get backtrace
        $trace = self::getDebugBacktrace();

        // log error
        $logger->log($errstr.$trace, self::_errorCodeToZendLogCode($errno),
            array('errno'=>$errno, 'file'=>$errfile, 'line'=>$errline, 'context'=>$errcontext)
        );

        // call original handler
        if (self::$_origErrorHandler !== null) {
            return call_user_func(self::$_origErrorHandler, $errno, $errstr.$trace, $errfile, $errline, $errcontext);
        }

        return true;
    }
    
    /**
     * returns formatted function stack trace
     * @return string
     */
    static public function getDebugBacktrace() {

        $i = 0;
        $trace = debug_backtrace();
        unset($trace[0]); //Remove call to this function from stack trace
        $stack = PHP_EOL . 'PHP Stack trace:'.PHP_EOL;
        foreach($trace as $node) {
            $stack .= "#$i ".(isset($node['file'])?$node['file']:'--') ."(" .(isset($node['line'])? $node['line']:'--')."): ";
            if(isset($node['class'])) {
                $stack .= $node['class'] . "->";
            }
            $stack .= $node['function'] . "()" . PHP_EOL;
            $i++;
        }
        return $stack;
    }
    
    /**
     * helper function to convert E_* error codes to Zend_Log::* codes
     * @param $code int
     * @param $toString boolean
     * @return int
     */
    static public function _errorCodeToZendLogCode($code, $toString = false) {
        switch ($code) {
            case E_NOTICE:
            case E_USER_NOTICE:
                return $toString ? 'NOTICE' : Zend_Log::NOTICE;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_STRICT:
                return $toString ? 'INFO' : Zend_Log::INFO;
            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
                return $toString ? 'WARN' : Zend_Log::WARN;
            case E_ERROR:
            case E_USER_ERROR:
            case E_CORE_ERROR:
            case E_RECOVERABLE_ERROR:
                return $toString ? 'ERR' : Zend_Log::ERR;
            default:
                return $toString ? 'CRIT' : Zend_Log::CRIT;
        }
    }
    
    //přidá file writer pro Zend_log
    private static function getFileWriter() {
        $config = Zend_Registry::get('config') ;
        
        $logFileName = rtrim(APPLICATION_PATH, '\\/') . '/' .
                trim($config->log->directory, '\\/') . '/' .
                date('Ymd').'.log';
        
        // file writer
        $fileWriter = new Zend_Log_Writer_Stream($logFileName);

        $fileWriter->setFormatter(self::$_formatter);
        $fileWriter->addFilter(new Zend_Log_Filter_Priority(array_search(
                        $config->log->file_priority, self::$_priorities), '<=')
        );
        
        if(file_exists($logFileName)) {
            chmod($logFileName,0666);
        }

        return $fileWriter;
    }

    /**
     * Initializes logging and exception handling including parse/fatal errors
     *
     * @return Zend_Log
     */
    static public function initLogger() {

        $config = Zend_Registry::get('config') ;
        
        // initialize logger
        $logger = new Zend_Log();

        // TODO: define as constants
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['SCRIPT_NAME'];
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : php_uname('n');

        // prepare log format
        $logFormat = "%timestamp%\t[%priorityName%]\t[%message% in %file% on line %line%]\t[$request_uri]\t[$user_agent]\t[Inbox ".Ibulletin_AdminPrepare::getInboxVersion()."]\r\n" . PHP_EOL;
        self::$_formatter = new Zend_Log_Formatter_Simple($logFormat);

        // log level matching
        $class = new ReflectionClass('Zend_Log');
        self::$_priorities = array_flip($class->getConstants());
        
         $logger->addWriter(self::getFileWriter());

        // email writer (only in production mode)
        if (Env::isProductionMode()) {
            $mail = new Zend_Mail('UTF-8');
            $mail->setFrom($config->general->project_email);
            $mail->addTo($config->log->email);

            $emailWriter = new Zend_Log_Writer_Mail($mail);
            $emailWriter->setSubjectPrependText('PHP error in project "'.$config->general->project_name.'" ('.APPLICATION_URL.')');
            $emailWriter->setFormatter(self::$_formatter);
            $emailWriter->addFilter(new Zend_Log_Filter_Priority(array_search(
                    $config->log->email_priority, self::$_priorities), '<=')
            );
            $logger->addWriter($emailWriter);
        }
        
        self::$_logger = $logger;

        // register error handler for normal errors (warnings,notices,...)
        self::$_origErrorHandler = set_error_handler(array(__CLASS__,'_errorHandler'));

        //// register an exception handler (uses Zend_Log error handler for logging)
        //set_exception_handler(array(__CLASS__, '_exceptionHandler'));

        // register error handler for fatal type of errors (uses Zend_Log error handler for logging)
        register_shutdown_function(array(__CLASS__, '_shutdownHandler'));

        // register an Zend_Log error handler (automatically log errors)
        //$logger->registerErrorHandler(); // _errorHandler is automatically called

    }
    
    
    public static function getLogger() {
        
        if (self::$_logger) {
            return self::$_logger;
        } else {
            self::initLogger();
            return self::$_logger;
        }
    }
    
     /**
     * Shutdown handler to catch fatal, parse and unrecoverable errors.
     * logs message to logger and redirects to error page.
     * @return void
     * @internal
     */
    static public function _shutdownHandler() {

        // get error (if any)
        $error = error_get_last();

        if (is_null($error)) { return; }
        // add check for already processed errors
        if (!is_null(self::$_last_error) && ((array)$error == (array)self::$_last_error)) { return; }

        $logger = self::getLogger();

        // get backtrace
        $trace = self::getDebugBacktrace();

        $logger->log($error['message'] . $trace, self::_errorCodeToZendLogCode($error['type']), array('errno'=>$error['type'], 'file'=>$error['file'], 'line'=>$error['line'], 'context'=>null));

        // fatal error are passed further (to give 500 page)
        switch ((int)$error['type']) {
            case E_CORE_ERROR:
            case E_CORE_WARNING:
            case E_COMPILE_ERROR:
            case E_COMPILE_WARNING:
            case E_USER_ERROR:
            case E_ERROR;
                break;
            default:
        }

        // output error message for development/CLI
        if (!env::isProductionMode() || ISCLI) {
            // print to stdout so on development environment developer can see the error
            ob_clean();
            printf('%s: %s in file %s on line %s',$error['type'], $error['message'] . $trace ,$error['file'], $error['line']);
        } else {

        // redirect to 500 error page
            if (!headers_sent()) { // non-empty headers workaround for last error that was handled by zend_log error_handler
                ob_clean();
                header("Location: ".APPLICATION_URL."/error/e500/");
            }
        }

    }
    
     /**
     * Error handler to catch warnings, notices and recoverable errors.
     * only stores last error processed
     * @return void
     * @internal
     */
    static public function _exceptionHandler(Exception $ex) {

        self::$_last_error = array(
            'type' => E_WARNING,
            'message' => $ex->__toString(),
            'file' => $ex->getFile(),
            'line' => $ex->getLine(),
            //'context' => $errcontext
        );

        // prepare logger
        if (Zend_Registry::isRegistered('logger')) {
            $logger = Zend_Registry::get('logger');
        } else {
            $logger = self::initLogger();
        }

        // log exception
        $logger->log($ex->__toString(), Zend_Log::WARN,
            array('errno'=>$ex->getCode(), 'file'=>$ex->getFile(), 'line'=>$ex->getLine(), 'context'=>null));

        return false;
    }

}
