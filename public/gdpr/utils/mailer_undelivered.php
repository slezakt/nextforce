#!/usr/bin/php5
<?php
/*
 *	Skript pro detekci nedoručených správ pomoci parsovani bounce sprav z daneho mailboxu.
 *	spustitelne z CLI (cron, shell)
 *  navratova hodnota 0 znaci uspech, ostatni hodnoty chybu
 *
 *	@author Andrej Litvaj
 */

//define('PHC_LEGACY_PATH','/home/mentor/projects/inbox_lib/');

// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));

// can be defined earlier (f.e. in bootstrap)
defined('BOUNCE_LOG_PATH')
|| define ('BOUNCE_LOG_PATH', APPLICATION_PATH . '/log/bounces/');


// define command-line arguments
$getopt = new Zend_Console_Getopt(array(
    'host|H=s' => 'hostname of mail server (pop3, imap)',
    'port|p=i' => 'port number of mail server (pop3, imap)',
    'ssl|s-w' => 'type of encryption (ssl,tls) for connection (pop3,imap)',
    'username|u=s' => 'username credentials for mail server (pop3, imap)',
    'password|P=s' => 'password credentials for mail server (pop3, imap)',
    'filename|f=s' => 'path to mailbox file (mbox)',
    'dirname|d=s' => 'path to maildir directory (maildir)',
    'type|T=s' => 'type of connection (pop3, imap, mbox, maildir)',
    'size|m=i' => 'maximum size of message in bytes',
    'test|t' => 'no modifications are done, prints result to stdout',
    'verbose|v' => 'verbose output',
    'help|h' => 'help',
));

$allowed_mailbox_types = array('pop3', 'imap', 'mbox', 'maildir');
$allowed_encryption_types = array('SSL', 'TLS');


try {
    // parse command-line
    $getopt->parse();
    // if help requested, report usage message
    if ($getopt->getOption('h')) {
        echo $getopt->getUsageMessage();
        exit(0);
    }

    // initialize configuration
    $config = Env::initConfig();

    // get project identifier
    $project = $config->database->params->dbname;

    // read configuration section
    $options = ($config->get('mailer') && $config->mailer->get('undelivered'))
        ? $config->mailer->undelivered->toArray() : array();

    // merge with cli arguments
    if ($s = $getopt->getOption('H')) $options['host'] = $s;
    if ($s = $getopt->getOption('p')) $options['port'] = $s;
    if ($s = $getopt->getOption('s')) $options['ssl'] = $s;
    if ($s = $getopt->getOption('u')) $options['username'] = $s;
    if ($s = $getopt->getOption('P')) $options['password'] = $s;
    if ($s = $getopt->getOption('f')) $options['filename'] = $s;
    if ($s = $getopt->getOption('d')) $options['dirname'] = $s;
    if ($s = $getopt->getOption('T')) $options['type'] = $s;
    if ($s = $getopt->getOption('m')) $options['max_message_size'] = $s;
    if ($s = $getopt->getOption('t')) $options['test'] = (boolean)$s;
    if ($s = $getopt->getOption('v')) $options['verbose'] = (boolean)$s;

    //sanitize options
    foreach ($options as $k => $v) {
        // (trim, lowercase strings in array)
        if (in_array($k, array('host', 'type'))) {
            $options[$k] = strtolower(trim($v));
        }
    }

    if (!isset($options['verbose'])) {
        $options['verbose'] = false;
    }

    if (!isset($options['test'])) {
        $options['test'] = false;
    }


    // max message size
    if (empty($options['max_message_size'])) {
        $options['max_message_size'] = Ibulletin_Bounce::getMaxMessageSize();
    }

    // port is optional, defaults to NULL
    if (empty($options['port'])) {
        $options['port'] = null;
    }

    // password is optional, defaults to NULL
    if (empty($options['password'])) {
        $options['password'] = null;
    }

    // default type is POP3
    if (empty($options['type']) || !in_array($options['type'], $allowed_mailbox_types)) {
        $options['type'] = $allowed_mailbox_types[0];
    }

    // default ssl encryption type is FALSE or SSL if set
    if (!empty($options['ssl'])) {
        $options['ssl'] = strtoupper($options['ssl']);
        if ($options['ssl'] == '1' || !in_array($options['ssl'], $allowed_encryption_types)) {
            $options['ssl'] = $allowed_encryption_types[0];
        }
    } else {
        $options['ssl'] = false;
    }

} catch (Zend_Console_Getopt_Exception $e) {
    // Bad options passed: report usage
    echo $getopt->getUsageMessage(), PHP_EOL;
    //Phc_ErrorLog::warning('undelivered', $e->getUsageMessage());
    exit(1);
}

try {
    // make connection to mailbox, specified by type
    switch ($options['type']) {
        case 'pop3' :
            // required parameters
            if (empty($options['host']) || empty($options['username'])) {
                echo 'Parameters host and username are required!', PHP_EOL;
                exit(1);
            }
            $mailbox = new Zend_Mail_Storage_Pop3(array(
                'host' => $options['host'],
                'port' => $options['port'],
                'user' => $options['username'],
                'password' => $options['password'],
                'ssl' => $options['ssl']
            ));
            break;
        case 'imap' :
            // required parameters
            if (empty($options['host']) || empty($options['username'])) {
                echo 'Parameters host and username are required!', PHP_EOL;
                exit(1);
            }
            $mailbox = new Zend_Mail_Storage_Imap(array(
                'host' => $options['host'],
                'port' => $options['port'],
                'user' => $options['username'],
                'password' => $options['password'],
                'ssl' => $options['ssl']
            ));
            break;
        case 'mbox' :
            // required parameters
            if (empty($options['filename'])) {
                echo 'Parameter filename is required!', PHP_EOL;
                exit(1);
            }
            $mailbox = new Zend_Mail_Storage_Mbox(array(
                'filename' => $options['filename']
            ));
            break;
        case 'maildir' :
            // required parameters
            if (empty($options['dirname'])) {
                echo 'Parameter dirname is required!', PHP_EOL;
                exit(1);
            }
            $mailbox = new Zend_Mail_Storage_Maildir(array(
                'dirname' => $options['dirname']
            ));
            break;
    }
} catch (Zend_Mail_Protocol_Exception $e) {
    //Phc_ErrorLog::warning('undelivered', $e->getMessage());
    echo $e->getMessage(), PHP_EOL;
    exit(2);

}

/* *************************************************************** */
/* *************************************************************** */
/* *************************************************************** */

Zend_Registry::set('mailbox', $mailbox);
try {

    // initialize database
    $db = !$options['test'] ? Env::initDatabase() : null;

    // bounce messages class
    $bounce = Ibulletin_Bounce::getInstance();

    // set logging directory (mandatory)
    if (!file_exists(BOUNCE_LOG_PATH)) {
        if (!@mkdir(BOUNCE_LOG_PATH) || !@chmod(BOUNCE_LOG_PATH, 0777)) {
            throw new Exception('Nelze vytvorit adresar ' . BOUNCE_LOG_PATH);
        }
    }

    // turn on logging
    $bounce->setLogDirectory(!$options['test'] ? BOUNCE_LOG_PATH : null);
    // turn on setting flags for passed and deleted messages
    $bounce->setAutoFlag(!$options['test']);
    // set max acceptable message size (in Bytes)
    $bounce->setMaxMessageSize(!$options['test'] ? $options['max_message_size'] : null);

    // get filtered bounce messages
    $bounces = $bounce->getBounceEmails($mailbox, $project);
    $count_updates = 0;
    foreach ($bounces as $key => $val) {

        // print to stdout if testing or being verbose
        if ($options['verbose'] || $options['test']) {
            echo $key . ' ' . $val['sender'] . ' ' . $val['bounce']['status']
                . ' ' . implode(' ', $val['data']) . ' ' . $val['recipient'] . "\n";
        }

        // get identifier from data
        if (is_numeric($val['data'][0])) {
            $ue_id = (int)$val['data'][0];
        } else {
            if ($options['verbose'] || $options['test']) {
                echo '* Invalid bounce metadata. logging ...' . "\n";
            }
            // bogus not numeric identifier in data
            //Phc_ErrorLog::warning('undelivered', "Invalid bounce with email key $key : " . print_r($val, TRUE));
            echo "Invalid bounce with email key $key : " . print_r($val, true);
            continue;
        }

        // skip db updates
        if ($options['test']) {
            continue;
        }

        $count_updates++;

        // transaction
        $db->beginTransaction();

        // detect action, status of bounce message
        switch ($val['bounce']['action']) {

            case 'failed' :

                // find user.id
                $q = $db->select()
                    ->from($config->tables->users_emails, array('user_id'))
                    ->where('id = ?', array($ue_id));
                $user_id = $db->fetchOne($q);

                // update users_emails.status = undelivered
                $db->update($config->tables->users_emails, array(
                    'status' => Ibulletin_Mailer::S_UNDELIVERED
                ), sprintf('id = %d', $ue_id));


                // update users.bad_addr
                $db->update($config->tables->users, array(
                    'bad_addr' => true
                ), sprintf('id = %d', $user_id));
                break;

            case 'transient' :

                // update users_emails.status = undelivered
                $db->update($config->tables->users_emails, array(
                    'status' => Ibulletin_Mailer::S_UNDELIVERED
                ), sprintf('id = %d', $ue_id));
                break;

            case 'autoreply' :

                // update users_emails.status = autoreplied
                $db->update($config->tables->users_emails, array(
                    'status' => Ibulletin_Mailer::S_AUTOREPLIED
                ), sprintf('id = %d', $ue_id));
                break;

            case 'success' :

                // update users_emails.status = success
                $db->update($config->tables->users_emails, array(
                    'status' => Ibulletin_Mailer::S_DELIVERED
                ), sprintf('id = %d', $ue_id));
                break;

        }
        // update users_emails.bounce_status
        if ($val['bounce']['status']) {
            $db->update($config->tables->users_emails, array(
                    'bounce_status' => $val['bounce']['status']),
                sprintf('id = %d', $ue_id)
            );
        }

        // commit changes
        $db->commit();

        // set flag for processed message
        if (!$options['test']) {
            $mailbox->setFlags($key, array(Zend_Mail_Storage::FLAG_DELETED /*FLAG_SEEN*/));
        }

        // deletes the message from mailbox
        // TODO : at the moment unable to do because of IMAP asynchronous delete vs fetch
        //$mailbox->removeMessage($key);

    }

} catch (Exception $e) {

    // rollback db changes
    $db->rollBack();

    //Phc_ErrorLog::error('undelivered', $e->getMessage());
    echo $e->getMessage(), PHP_EOL;
    exit(2);
}

echo $count_updates . '/' . count($bounces) . " bounces processed.", PHP_EOL;
// successfully exit
exit(0);

?>
