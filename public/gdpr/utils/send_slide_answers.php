#!/usr/bin/php5
<?php
/*
 * 	Skript pro odeslani odpovedi na otazku na slidu
 *
 */

// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));

// define command-line arguments
$getopt = new Zend_Console_Getopt(array(
        'contentid|c=i' => 'ID contentu',
        'slidenum|s=i' => 'cislo slidu',
        'questionnum|q=i' => 'cislo otazky na slidu',
        'emails|e=s' => 'seznam cilovych emailovych adres oddelennych carkou',
        'interval|i=s' => 'interval (ve formatu postgres, např. \'3 days\',\'12 hours\') od spusteni skriptu z ktereho se poslou data',
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

    $contentId = $getopt->c;
    $slideNum = $getopt->s;
    $questionNum = $getopt->q;
    $emails = $getopt->e;
    $interval = $getopt->i;
    
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


if ($contentId) {
    $content = Contents::get($contentId);
    if (!$content) {
        echo "Content s ID: $contentId neexistuje" . PHP_EOL;
        exit(1);
    }
} else {
    echo "Prepinac -c je povinny" . PHP_EOL;
    exit(1);
}

if (!$slideNum || !$questionNum) {
    echo "Prepinace -s a -q jsou povinne" . PHP_EOL;
    exit(1);
}

if (!$emails) {
    echo "Prepinac -e je povinny" . PHP_EOL;
    exit(1);
}

$questions = new Questions($contentId);
$q = $questions->getQuestion(null, $questionNum, null, $slideNum);

$select = $db->select()->from(array('aav' => 'answers_all_v'), array('question' => 'aav.text', 'answer' => 'aav.answer_text'))
        ->join(array('u' => 'users'), 'aav.user_id = u.id', array('uid' => 'u.id', 'user_name' => 'u.name', 'user_surname' => 'u.surname', 'user_email' => 'u.email'))
        ->join(array('pv' => 'page_views'), 'aav.page_views_id = pv.id', array('timestamp' => 'pv.timestamp'))
        ->where('content_id = ?', $contentId)
        ->where('slide_num = ?', $slideNum)
        ->where('question_num = ?', $questionNum)
        ->order('pv.timestamp DESC');

if ($interval) {
    $select->where("pv.timestamp >= CURRENT_TIMESTAMP - interval '$interval'");
}

$rows = $db->fetchAll($select);

if ($rows) {
    
    $rowHead = array_keys($rows[0]);

    $mail = new Zend_Mail('utf-8');
    
    $emails = explode(',',str_replace(' ', '', $emails));
    
    $validator = new Zend_Validate_EmailAddress();
    
    foreach ($emails as $key => $email) {
        
        if (!$validator->isValid($email)) {
             unset($emails[$key]);      
        } else {
            
            if ($key == 0) {
                $mail->addTo($email);
            } else {
                $mail->addCc($email);
            }
            
        }
        
    }
    
    if (count($emails) == 0) {
        echo "Nejsou nasteveny zadne platne adresy".PHP_EOL;
        exit(1);
    }

    
    $mail->setFrom($config->general->project_email);
    $mail->setSubject($config->general->project_name . ': Answers report');
    $mail->setBodyHtml(
            '<p>Content: ' . $content['name'] . '<br>'
            . 'Slide: ' . $slideNum . '<br>'
            . 'Question: ' . $q['text']
            . '</p>'
    );

    $fp = fopen('php://temp', 'w');

    fputs($fp, $bom = ( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    fputcsv($fp, $rowHead, ';');
    
    foreach ($rows as $row) {
        fputcsv($fp, $row, ';');
    }

    rewind($fp);

    $mail->createAttachment(stream_get_contents($fp), 'text/csv', Zend_Mime::DISPOSITION_ATTACHMENT, Zend_Mime::ENCODING_BASE64, 'answers_report.csv');

    if ($mail->send()) {
        echo "Report byl odeslan".PHP_EOL;
    }

    fclose($fp);
    
    
} else {
     echo "Zadna data k odeslani".PHP_EOL;
}