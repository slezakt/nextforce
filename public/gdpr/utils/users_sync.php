#!/usr/bin/php5
<?php
/*
 * 	Skript pro synchronizaci uzivatelu mezi dvema inBoxy
 *
 */

// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));

// define command-line arguments
$getopt = new Zend_Console_Getopt(array(
    'database|d=s' => 'název databáze určené k synchronizaci',
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


    //z parametru skriptu ziskame nazev db se kterou budeme synchronizovat misni db
    if ($getopt->getOption('d')) {
        $dbname2 = $getopt->getOption('d');
    }

    if (!isset($dbname2)) {
        echo "Databáze určená k synchronizaci nebyla nastavena (nastavte parametr -d)" . PHP_EOL;
        exit(1);
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
/* @var $db Zend_Db_Adapter_Abstract */
$db = Env::initDatabase();

//pripojime druhou db k synchronizaci
$db2params = $config->database->params->toArray();
$db2params['dbname'] = $dbname2;
/* @var $db2 Zend_Db_Adapter_Abstract */
$db2 = Zend_Db::factory($config->database->adapter, $db2params);

//synchronizace
$c1 = UsersSync::sync($db2, $db);
$c2 = UsersSync::sync($db, $db2);

if ($c1 > 0 || $c2 > 0) {

echo "Výsledek synchronizace:".PHP_EOL;
echo "Počet záznamů uložených do lokální databáze: $c1".PHP_EOL;
echo "Počet záznamů uložených do databáze propojeného inBoxu: $c2".PHP_EOL;

}

class UsersSync {

    public static function sync($db1, $db2) {

        //synchronizace uzivatelu mezi db
        $usql = "SELECT email, * FROM users WHERE deleted IS NULL AND email IS NOT NULL AND unsubscribed IS NULL";

        $rows2 = $db2->fetchAssoc($usql);
        $rows = $db1->fetchAssoc($usql);

        $diff_users = (array_diff_key($rows, $rows2));

        $counter = 0;

        foreach ($diff_users as $user) {

            $id2 = $user['id'];
            
            //odebereme ID
            unset($user['id']);
            
            //otestujeme zda v cilove databazi neni user jiz ulozen  
            $csql = $db2->select()->from('users')->where('email = ?',$user['email']);
            $crow = $db2->fetchRow($csql);
            if ($crow) {
                continue;
            }

            if ($db2->insert('users', $user)) {
                $counter++;
                $id = $db2->lastInsertId('users','id');

                //synchronizujeme uzivatelske atributy
                $uasql = $db1->select()->from('users_attribs')->where('user_id = ?', $id2);
                $userAttribs = $db1->fetchAll($uasql);
                
                //pridame do atributu informace ze uzivatel vznkl synchronizaci
                $userAttribs[] = array('name'=>'synchronized','val'=>Zend_Date::now()->getIso());

                foreach ($userAttribs as $ua) {
                    
                    unset($ua['id']);
                    $ua['user_id'] = $id;
                    
                    $db2->insert('users_attribs',$ua);
                    
                }
            }
        }
        
        return $counter;
        
    }
    

}
