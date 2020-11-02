#!/usr/bin/php5
<?
ini_set('memory_limit','512M');

/**************************************************************************/
$help = '
Provede export dat o zobrazovani prezentaci uzivateli a jejich odpovedich
na otazky do XML. Nyni konstruovano predevsim na inDetail prezentace

Jako prvni parametr predame nazev ciloveho souboru. DEFAULT: xml_export.xml

-h --help   Vypise napovedu.
--from="yyyy-mm-dd hh:mm:ss"  ISO 8601 timestamp od kdy vypisovat data, omezuje se podle session

Uziti:
./xml_export.php cilovy/adresar/export.xml
';
/**************************************************************************/

//
// Promenne
//
$args = arguments($_SERVER['argv']);
$args['commands'] += $args['arguments']; // arguments pripojime ke commands, protoze bez prepinacu jsou commands jen v arguments
//print_r($args);

$installDir = realpath(dirname(__FILE__).'/..');

$targetFile = !empty($args['commands'][0]) ? $args['commands'][0] : 'xml_export.xml';

/**
 * Nacteme vse potrebne k behu
 */
error_reporting(E_ALL|E_STRICT);
date_default_timezone_set('Europe/Paris');

set_include_path(
	get_include_path() . PATH_SEPARATOR . 
	$installDir.'/library' . PATH_SEPARATOR .  
	$installDir.'/models'
);
require_once("Phc/Legacy.php");
require_once 'Zend/Loader.php';


// Autoloader - nacte tridu, kdyz ji nemuze najit pomoci Zend_Loaderu
function __autoload($class_name) {
    Zend_Loader::loadClass($class_name);
}

// Databaze
$cfg_db = new Zend_Config_Ini($installDir.'/config_db.ini', 'database');
$db = Zend_Db::factory($cfg_db);
Zend_Registry::set('db', $db);

// TIMEZONE
$timezone = 'UTC';
date_default_timezone_set($timezone);
$q = "SET TIME ZONE '$timezone'";
$db->getConnection()->exec($q);


// DALSI ZADANE PARAMTERY, KTERE POTREBUJI ZEND
$fromTime = !empty($args['options']['from']) ? $args['options']['from'] : null;
$isoTimestValid = new Ibulletin_Validators_TimestampIsoFilter;
if($fromTime !== null && !$isoTimestValid->isValid($fromTime)){
    echo "\nCHYBA! Casova znamka '{$fromTime} neni ve formatu ISO 8601. Koncim.'\n\n";
    $args['flags'][] = 'h';
}

$errors = array();

/**
 * Kontrola existence config_db.ini
 */
if(!in_array('h', $args['flags']) && !file_exists($installDir.'/config_db.ini')){
    echo "\nCHYBA! Nebyl nalezen soubor '{$installDir}/config_db.ini', zrejme neni spravne nastaven adresar instalace inBoxu.\n\n";
    $args['flags'][] = 'h';
}

/**
 * Napoveda
 */
if(in_array('h', $args['flags']) || !empty($args['options']['help'])){
    echo $help;
    exit(0);
}


/******************************************************************************
 * Hlavni program
 *****************************************************************************/
main();
function main()
{
    global $targetFile;
    $xml = getViewDataXml();

    $indetail = $xml->getElementsByTagName('indetail')->item(0);
    $results = $xml->getElementsByTagName('results')->item(0);

    // Ziskame xml pages
    $pagesDoc = getPagesXml();
    $pages = $pagesDoc->getElementsByTagName('pages');
    $pages = $xml->importNode($pages->item(0), true);
    $indetail->insertBefore($pages, $results);

    // Ziskame xml reprezentantu
    $represDoc = getRepresXml();
    $repres = $represDoc->getElementsByTagName('representatives');
    $repres = $xml->importNode($repres->item(0), true);
    $indetail->insertBefore($repres, $results);

    // Ziskame xml otazek
    $questsDoc = getQuestionsXml();
    $quests = $questsDoc->getElementsByTagName('questions');
    $quests = $xml->importNode($quests->item(0), true);
    $indetail->insertBefore($quests, $results);

    $xml->formatOutput = true; // Hezke formatovani vystupu
    $xml->save($targetFile);

    echo "HOTOVO: $targetFile\n";
}

/**
 * Vytvori XML ulozenych dat - page_views, odpovedi a spol
 *
 * @return  DOMDocument
 */
function getViewDataXml()
{
    global $db, $fromTime;

    // Cas od kdy se maji data ziskavat
    if($fromTime !== null){
        $fromTimeQ = "WHERE s.timestamp > '$fromTime'";
    }
    else{
        $fromTimeQ = '';
    }

    $q = "
        SELECT DISTINCT
            uattr.val AS cmt_id,
            s.user_id, s.id AS session_id, s.timestamp AS session_timestamp, s.timezone, EXTRACT(epoch FROM tz.utc_offset)/3600 AS utc_offset,
            pv.id AS page_view_id, pv.timestamp AS page_view_timestamp, pv.page_id,
            sl.slide_num,
            coalesce(inds.time_slide, 0) AS time_slide, inds.timestamp - (coalesce(inds.time_slide, 0)::varchar || 'ms')::interval AS slide_start,
            q.question_num,
            a.answer_num,
            ual.type AS answer_type, ual.answer_id, ual.answer_int, ual.answer_double, ual.answer_bool, ual.text, ual.timestamp AS answer_timestamp,
            sbr.repre_id
        FROM sessions s
        LEFT JOIN pg_timezone_names tz ON s.timezone = tz.name
        LEFT JOIN users_attribs uattr ON uattr.user_id = s.user_id AND uattr.name = 'cmtId'
        JOIN users_vf u ON s.user_id = u.id
        LEFT JOIN page_views pv ON pv.session_id = s.id AND pv.page_id IS NOT NULL
        JOIN bulletins_pages bp ON bp.page_id = pv.page_id
        JOIN bulletins_v b ON b.id = bp.bulletin_id
        LEFT JOIN sessions_by_reps sbr ON sbr.session_id = s.id
        JOIN ( -- Slidy, na ktere uzivatel presel (videl je nebo na ne aspon presel z jineho slide)
                SELECT * FROM(
                    SELECT page_views_id, slide_id FROM indetail_stats
                    UNION
                    SELECT page_views_id, slideon_id AS slide_id FROM indetail_stats
                ) inds GROUP BY page_views_id, slide_id
            ) slw ON slw.page_views_id = pv.id
        LEFT JOIN indetail_stats inds ON inds.page_views_id = slw.page_views_id AND inds.slide_id = slw.slide_id
        JOIN slides sl ON sl.id = slw.slide_id
        LEFT JOIN ( -- ANSWERS
                (SELECT id, question_id, page_views_id, indetail_stats_id, timestamp, coalesce(ua.answer_id, uac.answer_id) AS answer_id, answer_int,
                        answer_double, answer_bool, text, type FROM users_answers ua LEFT JOIN users_answers_choices uac ON ua.id = uac.users_answers_id
                )
                UNION
                (SELECT id, question_id, page_views_id, indetail_stats_id, timestamp, coalesce(ua.answer_id, uac.answer_id) AS answer_id, answer_int,
                        answer_double, answer_bool, text, type FROM users_answers_log ua LEFT JOIN users_answers_choices_log uac ON ua.id = uac.users_answers_log_id
                )
            ) AS ual ON inds.id = ual.indetail_stats_id
        LEFT JOIN questions q ON q.id = ual.question_id
        LEFT JOIN answers a ON a.id = ual.answer_id
        $fromTimeQ
        ORDER BY s.user_id, s.id, pv.id, slide_start, slide_num, answer_timestamp, question_num, answer_num
    ";

    $data = $db->fetchAll($q, array(), Zend_Db::FETCH_OBJ);

    $xml = new DOMDocument();
    $root = $xml->createElement('indetail');
    $root = $xml->appendChild($root);

    $results = $xml->createElement('results');
    $results = $root->appendChild($results);

    $lastR = new stdClass;
    $lastR->user_id = null;
    $lastR->session_id = null;
    $lastR->page_view_id = null;
    $lastR->slide_num = null;
    $lastR->question_num = null;
    $lastR->answer_timestamp = null;
    $lastR->answer_num = null;
    $reset = false; // Indikuje, jestli se zmenil zaznam na vyssi urovni
    foreach($data as $r){
        // Novy uzivatel
        if($r->user_id !== $lastR->user_id){
            $user = $xml->createElement('user');
            $user->setAttribute("cmtId", $r->cmt_id);
            $user->setAttribute("id", $r->user_id);
            $user = $results->appendChild($user);
            $reset = true;
        }
        // Nova session
        if($r->session_id !== $lastR->session_id){
            $session = $xml->createElement('session');
            $session->setAttribute("id", $r->session_id);
            $session->setAttribute("started", $r->session_timestamp);
            $session->setAttribute("originalTimeZone", $r->timezone);
            $session->setAttribute("originalTimeZoneOffset", $r->utc_offset);
            if(!empty($r->repre_id)){
                $session->setAttribute("representativeId", $r->repre_id);
            }
            $session = $user->appendChild($session);
            $reset = true;
        }
        // Nove page_view
        if($r->page_view_id !== $lastR->page_view_id && $r->page_view_id){
            $pageView = $xml->createElement('pageView');
            $pageView->setAttribute("id", $r->page_view_id);
            $pageView->setAttribute("started", $r->page_view_timestamp);
            $pageView->setAttribute("pageId", $r->page_id);
            $pageView = $session->appendChild($pageView);
            $reset = true;
        }
        // Novy slide
        if(($r->slide_num !== $lastR->slide_num || $r->slide_start !== $lastR->slide_start || $reset) && $r->slide_num){
            $slide = $xml->createElement('slide');
            $slide->setAttribute("num", $r->slide_num);
            $slide->setAttribute("time", round((float)$r->time_slide/(float)1000.00, 2));
            $slide->setAttribute("started", $r->slide_start);
            $slide = $pageView->appendChild($slide);
            $reset = true;
        }
        // Nova odpoved
        if(($r->question_num !== $lastR->question_num || $r->answer_timestamp != $lastR->answer_timestamp
            || $reset) && $r->question_num)
        {
            switch($r->answer_type){
                case 'c':
                    $answer = $xml->createElement('answer');
                    $answer->setAttribute("type", 'checkbox');
                    break;
                case 'r':
                    $answer = $xml->createElement('answer');
                    $answer->setAttribute("type", 'radio');
                    break;
                case 'b':
                    $answer = $xml->createElement('answer', $r->answer_bool ? 'true' : 'false');
                    $answer->setAttribute("type", 'bool');
                    break;
                case 't':
                    $answer = $xml->createElement('answer', $r->text);
                    $answer->setAttribute("type", 'text');
                    break;
                case 'i':
                    $answer = $xml->createElement('answer', $r->answer_int);
                    $answer->setAttribute("type", 'integer');
                    break;
                case 'd':
                    $answer = $xml->createElement('answer', $r->answer_double);
                    $answer->setAttribute("type", 'double');
                    break;
                default:
                    $answer = $xml->createElement('answer', $r->text);
                    $answer->setAttribute("type", 'text');
                    break;
            }
            $answer->setAttribute("questionNum", $r->question_num);
            $answer->setAttribute("timestamp", $r->answer_timestamp);
            $answer = $slide->appendChild($answer);
            $reset = true;
        }
        // Nova moznost odpovedi
        if($r->answer_num && ($r->answer_type == 'r' || $r->answer_type == 'c')){
            $choice = $xml->createElement('choice', $r->answer_num);
            $choice = $answer->appendChild($choice);
            $reset = true;
        }

        $lastR = $r;
        $reset = false;
    }

    return $xml;
}



/**
 * Vytvori XML obsahujici seznam pages
 *
 * @return  DOMDocument
 */
function getPagesXml()
{
    global $db;

    $q = "
        SELECT p.id, p.name, max(b.valid_from) AS valid_from, min(bp.\"order\") AS \"order\" FROM pages p
        JOIN bulletins_pages bp ON bp.page_id = p.id
        JOIN bulletins_v b ON b.id = bp.bulletin_id
        GROUP BY p.id, p.name
        ORDER BY valid_from, \"order\", id
    ";

    $data = $db->fetchAll($q, array(), Zend_Db::FETCH_OBJ);

    $xml = new DOMDocument();
    $root = $xml->createElement('pages');
    $root = $xml->appendChild($root);

    foreach($data as $r){
        $page = $xml->createElement('page');
        $page->setAttribute("id", $r->id);
        $page->setAttribute("name", $r->name);
        $page = $root->appendChild($page);
    }

    return $xml;
}


/**
 * Vytvori XML obsahujici seznam reprezentantu
 *
 * @return  DOMDocument
 */
function getRepresXml()
{
    global $db;

    $q = "
        SELECT u.* FROM users u WHERE is_rep
    ";

    $data = $db->fetchAll($q, array(), Zend_Db::FETCH_OBJ);

    $xml = new DOMDocument();
    $root = $xml->createElement('representatives');
    $root = $xml->appendChild($root);

    foreach($data as $r){
        $representative = $xml->createElement('representative');
        $representative->setAttribute("id", $r->id);
        $representative->setAttribute("name", $r->name.' '.$r->surname);
        $representative = $root->appendChild($representative);
    }

    return $xml;
}

/**
 * Vytvori XML obsahujici seznam otazek a odpovedi
 *
 * @return  DOMDocument
 */
function getQuestionsXml()
{
    global $db;

    $q = "
        SELECT p.page_id, s.slide_num, q.question_num, q.text AS question_text, a.answer_num, a.text AS answer_text
        FROM slides s
        JOIN questions q ON q.slide_id = s.id
        LEFT JOIN answers a ON a.question_id = q.id
        JOIN (
            SELECT cp.page_id, cp.content_id
            FROM content_pages cp
            JOIN (SELECT content_id, min(position) AS pos FROM content_pages GROUP BY content_id) f ON f.pos = cp.position AND f.content_id = cp.content_id
            JOIN pages p ON cp.page_id = p.id
            JOIN bulletins_pages bp ON bp.page_id = p.id
            JOIN bulletins_v b ON b.id = bp.bulletin_id
            ) AS p ON p.content_id = s.content_id
        ORDER BY p.page_id, s.slide_num, q.question_num, a.answer_num
    ";

    $data = $db->fetchAll($q, array(), Zend_Db::FETCH_OBJ);

    $xml = new DOMDocument();
    $root = $xml->createElement('questions');
    $root = $xml->appendChild($root);

    $lastR = new stdClass;
    $lastR->page_id = null;
    $lastR->slide_num = null;
    $lastR->question_num = null;
    $lastR->answer_num = null;
    $reset = false; // Indikuje, jestli se zmenil zaznam na vyssi urovni
    foreach($data as $r){
        if($lastR->page_id != $r->page_id){
            $page = $xml->createElement('page');
            $page->setAttribute("id", $r->page_id);
            $page = $root->appendChild($page);
            $reset = true;
        }
        if($lastR->slide_num != $r->slide_num || $reset){
            $slide = $xml->createElement('slide');
            $slide->setAttribute("num", $r->slide_num);
            $slide = $page->appendChild($slide);
            $reset = true;
        }
        if($lastR->question_num != $r->question_num || $reset){
            $question = $xml->createElement('question');
            $question->setAttribute("num", $r->question_num);
            $question->setAttribute("text", $r->question_text);
            $question = $slide->appendChild($question);
            $reset = true;
        }
        if($r->answer_num/*($lastR->answer_num != $r->answer_num || $reset) && $lastR->answer_num*/){
            $answer = $xml->createElement('answer');
            $answer->setAttribute("num", $r->answer_num);
            $answer->setAttribute("text", $r->answer_text);
            $answer = $question->appendChild($answer);
            $reset = true;
        }

        $lastR = $r;
        $reset = false;
    }

    return $xml;
}


/**
 * Funkce pro rozparsovani argumentu do pole.
 *
 * test.php asdf asdf --help --dest=/var/ -asd -h --option mew arf moo -z
 *
 *   Array
 *   (
 *       [commands] => Array
 *           (
 *               [0] => asdf
 *               [1] => asdf
 *           )
 *       [options] => Array
 *           (
 *               [help] => 1
 *               [dest] => /var/
 *               [option] => mew arf moo
 *           )
 *       [flags] => Array
 *           (
 *               [0] => a
 *               [1] => s
 *               [2] => d
 *               [3] => h
 *               [4] => z
 *           )
 *       [arguments] => Array
 *           (
 *           )
 *   )
 *
 *
 * @param $args
 */
function arguments ( $args )
 {
   array_shift( $args );
   $endofoptions = false;

  $ret = array
     (
     'commands' => array(),
     'options' => array(),
     'flags'    => array(),
     'arguments' => array(),
     );

  while ( $arg = array_shift($args) )
   {

    // if we have reached end of options,
     //we cast all remaining argvs as arguments
     if ($endofoptions)
     {
       $ret['arguments'][] = $arg;
       continue;
     }

    // Is it a command? (prefixed with --)
     if ( substr( $arg, 0, 2 ) === '--' )
     {

      // is it the end of options flag?
       if (!isset ($arg[3]))
       {
         $endofoptions = true;; // end of options;
         continue;
       }

      $value = "";
       $com   = substr( $arg, 2 );

      // is it the syntax '--option=argument'?
       if (strpos($com,'='))
         list($com,$value) = split("=",$com,2);

      // is the option not followed by another option but by arguments
       elseif (strpos($args[0],'-') !== 0)
       {
         while (strpos($args[0],'-') !== 0)
           $value .= array_shift($args).' ';
         $value = rtrim($value,' ');
       }

      $ret['options'][$com] = !empty($value) ? $value : true;
       continue;

    }

    // Is it a flag or a serial of flags? (prefixed with -)
     if ( substr( $arg, 0, 1 ) === '-' )
     {
       for ($i = 1; isset($arg[$i]) ; $i++)
         $ret['flags'][] = $arg[$i];
       continue;
     }

    // finally, it is not option, nor flag, nor argument
     $ret['commands'][] = $arg;
     continue;
   }

  if (!count($ret['options']) && !count($ret['flags']))
   {
     $ret['arguments'] = array_merge($ret['commands'], $ret['arguments']);
     $ret['commands'] = array();
   }
 return $ret;
}
