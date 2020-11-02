#!/usr/bin/php5
<?php
/*
 * Skript rozesesle remindery uzivatelum, kterym byl odeslan mail (repmailer.mailID) v ramci repmaileru a neotevreli content (dle nastaveni repmailer.pageID
 * Odesilaji se vzdy dva remindery dle repmailer.reminder1 a repmailer.reminder2,interval odeslani reminderů se řídi nastaveni repmailer.reminderInterval 
 * Skript se odešle pouze jeli nastaveno v configu repmailer.reminderAllow=1
 */

// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));
//repmailercontroller obsahuje metodu pro odesilani
require(realpath(dirname(__FILE__)).'/../admin/controllers/RepmailerController.php');

chdir(realpath(dirname(__FILE__).'/../'));

try {
   

    // initialize configuration
    $config = Env::initConfig();
    $db = Env::initDatabase();


    $frontController = Zend_Controller_Front::getInstance();
    $request = new Zend_Controller_Request_Simple('index', 'repmailer', 'admin');
    $frontController->setRequest($request);
    $frontController->getRouter()->addDefaultRoutes();
    $frontController->setResponse(new Zend_Controller_Response_Cli());
    
    
    $reminderAllow = $config->mailer->repmailer->reminderAllow;

    if (!$reminderAllow) {
        Phc_ErrorLog::warning('Reminder repmailer', 'Reminder není povolen');
        exit(0);
    }


    $mailID = $config->mailer->repmailer->mailID;

    $reminder1 = $config->mailer->repmailer->reminder1;
    $reminder2 = $config->mailer->repmailer->reminder2;
    
    $pageID = $config->mailer->repmailer->pageID;
    $reminderInterval = $config->mailer->repmailer->reminderInterval;


    if (!$mailID) {
        Phc_ErrorLog::warning('Reminder repmailer', 'V config není nastaven mailID');
        exit(0);
    }
    
    if (!$reminderInterval) {
        Phc_ErrorLog::warning('Reminder repmailer', 'V config není nastaven reminder interval');
        exit(0);
    }

    //dotaz do db, vybereme odeslane email z users_emails dle mailID (reminder ID), pouze emaily, ktere odeslal admin, jehož front user rep, ktery je repem uzivatele emailu
    //a pouze emaily uzivatele, ktery nenastivil page z configu 
    $select = $db->select()->from(array('ue' => 'users_emails'), array(new Zend_Db_Expr('DISTINCT ON ("ue"."user_id") "ue"."user_id"')));
    
    $select->join(array('au'=>'admin_users'),'ue.sent_by_admin = au.id',array('admin_id'=>'au.id'))
            ->join(array('ur' => 'users_reps'), 'ue.user_id = ur.user_id AND ur.repre_id = au.user_id', array('ur.repre_id'))
            ->join(array('u' => 'users'), 'ue.user_id = u.id',null)
            ->joinLeft(array('pvv' => 'page_views_v'), 'ue.user_id = pvv.user_id AND '. $db->quoteInto('pvv.page_id = ?', $pageID) .'', null);
    
    $select ->where('ue.status IS NULL')
            ->where('u.deleted IS NULL')
            ->where("ue.sent < current_timestamp - interval '".$reminderInterval."' day")
            ->where('pvv.id IS NULL')
            ->where('ue2.id IS NULL')
            ->order(array('ue.user_id','ue.sent'));
    
    
    $select1 = $select;
    //klonuje zakladni select, který je spolecny pro obe rozesilky
    $select2 = clone $select;
    
    if (!$reminder1) {
        Phc_ErrorLog::warning('Reminder repmailer', 'V config není nastaveno ID prvniho reminderu');
        exit(0);
    }
    
    //kontrola zda jiz nebyl odeslan prvni reminder
    $select1->where('ue.email_id = ?', $mailID)
           ->joinLeft(array('ue2' => 'users_emails'), 'ue.user_id = ue2.user_id AND ' . $db->quoteInto('ue2.email_id = ?', $reminder1) . ' AND ue2.sent IS NOT NULL AND ue2.status IS NULL', null);

    $rows1 = $db->fetchAll($select1);
    
    //odeslani prvniho reminderu
    foreach ($rows1 as $row) {
        echo "Send email " . $row['user_id'] . "\n";
        Admin_RepmailerController::sendRepMail($reminder1, $row['repre_id'], $row['user_id'],$row['admin_id']);
    }
    
    //neni-li nastaven druhy reminder skript ukoncime
    if (!$reminder2) {
        Phc_ErrorLog::warning('Reminder repmailer', 'V config není nastaveno ID druheho reminderu');
        exit(0);
    }
    
    //kontrola zda jiz nebyl odeslan druhy reminder
    $select2->where('ue.email_id = ?', $reminder1)
          ->joinLeft(array('ue2' => 'users_emails'), 'ue.user_id = ue2.user_id AND ' . $db->quoteInto('ue2.email_id = ?', $reminder2) . ' AND ue2.sent IS NOT NULL AND ue2.status IS NULL', null);
    

    $rows2 = $db->fetchAll($select2);
    
    //odeslaniho druheho reminder
    foreach ($rows2 as $row) {
        echo "Send email " . $row['user_id'] . "\n";
        Admin_RepmailerController::sendRepMail($reminder2, $row['repre_id'], $row['user_id'],$row['admin_id']);
    }
   
    
} catch (Zend_Console_Getopt_Exception $e) {
    // Bad options passed: report usage
    echo $getopt->getUsageMessage(), PHP_EOL;
    exit(1);
}