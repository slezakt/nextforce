<?php
/**
 * Konfiguracni soubor pro nastaveni logovani jednotlivych projektu/modulu
 *
 * V tomto souboru jsou ulozeny implicitni hodnoty a pokud si nevytvorite
 * vlastni takovy soubor budou pouzity tyto hodnoty
 *
 * @package php-include
 * @subpackage error-handler
 * @author Jaroslav Prodelal
 * @since 2004-05-03
 * @datechange 2004-05-18
 */
 
/**
 * Adresar pro ukladani logu
 *
 * Tato konstanta by se nemela nikdy menit
 * default: /home/lekarnik/php-logs
 */
define('ERR_LOG_ROOT','/home/lekarnik/php-logs');

/**
 * Jmeno projektu
 *
 * Kratke, vystizne, bez diakritiky, mezer, vyhovujici sablone /^[a-z][a-z0-9_-\.]*[a-z0-9]$/i
 */
define('ERR_PROJECT_NAME','nextforce_cz_gdpr');

/**
 * Nazev log souboru podle aktualniho data
 * default: phplog-date('Ymd')
 */
define('ERR_LOG_FILE', ERR_PROJECT_NAME . '-' . date('Ymd'));

/**
 * Cislo projektu, ktere bylo prideleno PM
 */
define('ERR_PROJECT_ID', 5575);

/**
 * Email vyvojare
 */
define('ERR_EMAIL_DEVELOPER','inboxerror@pearshealthcyber.com');
define('DEVELOPER_EMAIL', 'inboxerror@pearshealthcyber.com');

/**
 * SMS email vyvojare
 */
define('ERR_EMAIL_SMS_DEVELOPER','pecast_cz@seznam.cz');

/**
 * Pri jake chybe se odesila SMSka vyvojari
 *
 * Standardne E_USER_ERROR
 */
define('ERR_SMS_SEND_LEVEL',E_USER_ERROR);

/**
 * Ukladat kontext promennych pri E_USER_ERROR
 *
 * Jedna se o ulozeni prommene $errcontext -> base64_encode(serialize($errcontext))
 */
define('ERR_DUMP_VARS_USER_ERROR',1);

/**
 * Pokud nastaveme E_USER_ERROR presmerovat uzivatele
 */
define('ERR_REDIRECT_ON',0);

/**
 * Nastaveni kam bude pri chybe a splneni ERR_REDIRECT_ON presmerovano
 */
define('ERR_REDIRECT_DOC','/');

/**
 * Definuje, zda se ma chyba zobrazit na obrazovku
 */
define('ERR_DISPLAY_ERROR',0);

/**
 * Pouziti vlastniho error handleru
 */
define('USE_ERROR_HANDLER',1);

/**
 * Stale testujeme?
 */
define("TEST", 0);

/**
 * Nastavime display_errors
 */
//ini_set('display_errors', 0);

?>
