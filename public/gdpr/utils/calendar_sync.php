#!/usr/bin/php5
<?php
/*
 * 	Skript pro synchronizaci kalendaru s EUNI
 *
 */

// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));

// define command-line arguments
$getopt = new Zend_Console_Getopt(array(
    'file|f=s' => 'soubor se stazenymi daty z euni',
    'help|h' => 'help'
        ));

try {
    // parse command-line
    $getopt->parse();
    // if help requested, report usage message
    if ($getopt->getOption('h')) {
        echo $getopt->getUsageMessage();
        exit(0);
    }

    $data = null;

    //v pripade ze skriptu predame cestu k souboru s daty, data stahneme a nemu je stahovat
    if ($getopt->getOption('f')) {
        $file = $getopt->getOption('f');
        $data = json_decode(file_get_contents($file), true);
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

$contents = Contents::getList('Ibulletin_Content_Calendar');

$calendar = new Calendar();

//ziskame eventy z euni
$EUNIEvents = $calendar->getEUNIEvents(null, $data);

$deleted_events = array();
$updated_events = array();

//postupne synchronizuje eventy v kalendarich
foreach ($contents as $content) {

    //ulozene eventy
    $events = Calendar::getEvents($content['id']);

    foreach ($events as $event) {

        //neni-li event soucasti stazenych euni akci, vymazemeho z db
        if (isset($EUNIEvents['raw'][$event['event_id']])) {
            $euniEvent = $EUNIEvents['raw'][$event['event_id']];
            $euniUpdated = new Zend_Date($euniEvent['update_at']['date'], Zend_Date::ISO_8601);
            $euniUpdated->setTimezone($euniEvent['update_at']['timezone']);

            $eventUpdated = new Zend_Date(strtotime($event['updated']));

            $euniEvent['content_id'] = $content['id'];

            //byl-li event v euni aktualizovan udelame update
            if ($euniUpdated->isLater($eventUpdated)) {
                 if  ($calendar->save($euniEvent)) {
                    $updated_events[$content['id']][] = $euniEvent['title'];
                 }
            }
        } else {
            if ($calendar->delete($calendar->getAdapter()->quoteInto('id = ?', $event['id']))) {
                $deleted_events[$content['id']][] = $event['title'];
            }
        }
    }
}

echo "Synchronizace byla dokončena" . PHP_EOL;

//jsou-li nejake eventy zmeneny a je nastaven email pro upozorneni, odesleme mail
if (($updated_events || $deleted_events) && $config->calendar->noticeEmail) {

    $msg = "";

    if ($updated_events) {
        $msg .= "<strong>Aktualizované události:</strong><br>";
        foreach ($updated_events as $k => $uev) {
            $c = Contents::get($k);
            $msg .= "<u>Kalendář: " . $c['name'] . "</u><br>";
            $msg .= implode('<br>', $uev) . '<br><br>';
        }
        $msg .= "<br>";
    }

    if ($deleted_events) {
        $msg .= "<strong>Odstraněné události:</strong><br>";
        foreach ($deleted_events as $k => $dev) {
            $c = Contents::get($k);
            $msg .= "<u>Kalendář: " . $c['name'] . "</u><br>";
            $msg .= implode('<br>', $dev) . '<br><br>';
        }
    }

    $mail = new Zend_Mail('utf-8');
    $mail->setSubject($config->general->project_name . ': synchronizace kalendáře');
    $mail->setFrom($config->general->project_email);
    $mail->addTo($config->calendar->noticeEmail);
    $mail->setBodyHtml($msg);

    if ($mail->send()) {
        echo "Výsledek synchronizace byl odeslán" . PHP_EOL;
    }
}
    
