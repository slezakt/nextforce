#!/usr/bin/php5
<?php
/*
 *	Skript pro resumable PUT upload videa na vimeo.
 *
 *  parametry skriptu jsou typ operace (upload/replace) a id contentu (typu Ibulletin_Video)
 *
 *	@author Ondrej Blaha
 */


// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));

if (!isset($argv[1])) {
    echo "Domain name missing!\n";
    exit;
}

$domain = $argv[1];
$configFile = realpath(dirname(__FILE__) . "../config_admin.ini");

// Odstranit www. nebo vyvoj. z domeny
$prefixes = array('www.', 'vyvoj.');
foreach ($prefixes as $prefix) {
    if (substr($domain, 0, strlen($prefix)) == $prefix) {
        $domain = substr($domain, strlen($prefix));
    } 
}

$cfg_values = array(
    'mailer' => array(
        'return_path' => 1,
        'return_path_domain' => $domain)
);

$state = Config::setBasicConfig($cfg_values, $configFile);

if ($state['errorsMsg']) {
    foreach ($state['errorsMsg'] as $e) {
        switch ($e) {
            case 'error_mailer_return_path':
                echo "It was not possible to set a domain to project return path email, MX records do not have the required settings.\n";
                break;
        }
    }
} else {
    echo "Return path email has been set\n";
}

?>