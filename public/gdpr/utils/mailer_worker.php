#!/usr/bin/php
<?php
/*
 *	Skript pro rozeslani emailu z fronty.
 *
 */

// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));

// define command-line arguments
$getopt = new Zend_Console_Getopt(array(
    'verbose|v' => 'verbose output',
    'help|h' => 'help',
    'fake|f' => 'simulated sending',
));

$options = array();

try {
    // parse command-line
    $getopt->parse();
    // if help requested, report usage message
    if ($getopt->getOption('h')) {
        echo $getopt->getUsageMessage();
        exit(0);
    }

    // get cli arguments
    if ($s = $getopt->getOption('v')) $options['verbose'] = (boolean)$s;

    if (!isset($options['verbose'])) {
        $options['verbose'] = false;
    }

} catch (Zend_Console_Getopt_Exception $e) {
    // Bad options passed: report usage
    echo $getopt->getUsageMessage(), PHP_EOL;
    exit(1);
}

/* *************************************************************** */
/* *************************************************************** */
/* *************************************************************** */

// initialize variables inside lock file

// exit immediately if lock file exists
if (file_exists(Ibulletin_Mailer::WORKER_LOCK_FILE)) {
    echo 'Lock file '.Ibulletin_Mailer::WORKER_LOCK_FILE.' exists so there is propably another process running. Please remove the file manually if desired.', PHP_EOL;
    exit(1);
}

//zalozi worker lock file
file_put_contents(Ibulletin_Mailer::WORKER_LOCK_FILE, Zend_Json::encode(array(
        'pid' => getmypid(),
        'count' => 0,
        'total' => 0
    )));

try {

    // initialize configuration
    $config = Env::initConfig();
    $logger = Phc_ErrorLog::initialize();
    $db = Env::initDatabase();
    $mailer = new Ibulletin_Mailer($config, $db);
    $mailer->sendDeletedToo();
    
    //check and set simulated sending
    if ($getopt->getOption('f')) {
        $mailer->setSendFake(true);
        $mailer->setSendFakeDatetime(date('Y-m-d H:i:s'));
    }

    $mailer->sendEmails(null, false, 'mailer_callback');

} catch (Exception $e) {

    if (file_exists(Ibulletin_Mailer::WORKER_LOCK_FILE)) {
        unlink(Ibulletin_Mailer::WORKER_LOCK_FILE);
    }

    echo $e->getMessage(), PHP_EOL;
    exit(2);
}

// cleanup

if (file_exists(Ibulletin_Mailer::WORKER_LOCK_FILE)) {
    sleep(5);
    unlink(Ibulletin_Mailer::WORKER_LOCK_FILE);
}

// successfully exit
exit(0);

// helper functions for notification of worker

function mailer_callback($row, $cnt, $total,$result=null){
    mailer_state($cnt,$total,$result);
}

function mailer_state($cnt, $total,$result) {
    //vytvori lock file a zapise stav
    file_put_contents(Ibulletin_Mailer::WORKER_LOCK_FILE, Zend_Json::encode(array(
        'pid' => getmypid(),
        'count' => $cnt,
        'total' => $total,
        'result' => $result
    )));

    Utils::chmod($path, 0777);
}

?>