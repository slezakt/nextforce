<?php
/**
 * @author Ondra Bláha <ondrej.blaha@pearshealhcyber.com>
 */

// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));

// define command-line arguments
$getopt = new Zend_Console_Getopt(array(
	'help|h' => 'help',
	'force|f' => 'synchronizace bez ohledu na casu posledni replikace'
));


try {
	// parse command-line
	$getopt->parse();
	// if help requested, report usage message
	if ($getopt->getOption('h')) {
		echo $getopt->getUsageMessage();
		exit(0);
	}

	if ($getopt->getOption('f')) {
		$force = true;
	} else {
		$force = false;
	}


} catch (Zend_Console_Getopt_Exception $e) {
	// Bad options passed: report usage
	echo $e->getMessage() . PHP_EOL;
	echo $getopt->getUsageMessage() . PHP_EOL;
	exit(1);
}

/* * ************************************************************** */
/* * ************************************************************** */
/* * ************************************************************** */

// initialize configuration
$config = Env::initConfig();
$db = Env::initDatabase();

$crmSync = new CrmSync();

try {
	$agreements = $crmSync->getAgreements();
} catch (Exception $ex) {
	Phc_ErrorLog::error("CRM sync","GET agreements: " . $ex->getMessage());
	exit;
}


try {
	if ($agreements) {
		//odebrani neplatnych souhlasu z DB
		$crmSync->deleteInvalidAgreements($agreements);

		//ulozeni souhlasu do DB
		$crmSync->saveAgreements($agreements);
	}

} catch (Exception $ex) {
	Phc_ErrorLog::error("CRM sync", "Sync agreements:" . $ex->getMessage());
}


try {

	//seznam uzivatelu pro synchronizaci
	$users = $crmSync->getUsersForSync($force);

	//synchronizace tabulky souhlasu v inBoxu
	$crmSync->syncUsersAgreements($users);

} catch (Exception $ex) {
	Phc_ErrorLog::error("CRM sync","Sync users:" . $ex->getMessage());
}
