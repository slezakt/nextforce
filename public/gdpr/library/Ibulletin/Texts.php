<?php

/**
 * iBulletin - Texts.php
 *
 *
 * Konfigurace textu pouzitych v inBoxu.
 *
 * Identifikator textu by mel byt ve tvaru:
 * moduleName.controllerName.actionName.nejakeIdProDanyTextVystihujiciJehoVyznam
 * (moduleName se zcela vynecha vcetne tecky pro modul default, coz je front end webu)
 * Identifikatrory skladajici se z vice nez 4 casti nejsou jednoduse podporovane metodou Ibulletin_Texts::get(),
 * proto by nemely byt bezne pouzivane. Notaci identifikatoru je mozne porusit napriklad pokud se nejedna
 * o text patrici controlleru - pouzivame treba pro jednotlive contenty, potom je treba bud nastavovat
 * kontext pomoci Ibulletin_Texts::setActualContext();, nebo pouzivat mene preferovane cesty ziskavani textu
 * pres Ibulletin_Texts::getSet();
 *
 * Jmeno souboru zacina nazvem jazyka a dale v teckove notaci nasleduje jakykoli prefix umisteni textu.
 * Vsechny texty ulozene v danem souboru jsou umisteny v ceste zaciniajici nazvem daneho souboru:
 * pro soubor cz.admin.stats.questionnaire.ini s radkem obsahujicim text
 * nejakyText = "Nejaky text"
 * bude k tomuto textu mozno pristupovat napriklad pomoci nasledujicich konstrukci v controlleru a view:
 * 1) pokud jsme ve spravnem controlleru a akci - tedy v modulu 'admin', controlleru 'statsController'
 * a akci 'questionnaire':
 * $t = Ibulletin_Texts::getInstance();
 * echo $t->get('nejakyText');
 * 2) pokud jsme v jine akci nez questionnaire (vice v dokumentaci metody Ibulletin_Texts::get()):
 * $t = Ibulletin_Texts::getInstance();
 * echo $t->get('questionnaire.nejakyText');
 * nebo:
 * $t = Ibulletin_Texts::getInstance('questionnaire'); // Pozor, zmeni aktualni kontext textu i pro nasledujici casti programu
 * echo $t->get('nejakyText');
 * 3) ve view skriptu (kdekoli avsak ve view skriptu pro spravny modul, controller a akci => 'admin', 'stats', 'questionnaire'):
 * <?=$this->text('nejakyText')?> (jedna se o alias ve forme view helperu pro Ibulletin_Texts::get() - je dostupny vzdy ve view)
 * 4) mene preferovany zpusob, kdy si vytahneme objet s celou casti sady textu podle prefixu
 * $texts = Ibulletin_Texts::getSet('admin.stats.questionnaire');
 * echo $texts->nejakyText;
 * 5) nebo kratsi prefix:
 * $texts = Ibulletin_Texts::getSet('admin.stats');
 * echo $texts->questionnaire->nejakyText;
 *
 * Kdekoliv v textech je mozne pouzit tag %%project_email%%, ktery bude prelozen na
 * defaultni email z configu a %%project_name%%, ktery bude prelozen na jmeno projektu,
 * %%project_name_p2%% pro druhý pád.
 *
 * INSTALACNE SPECIFICKE TEXTY:
 * Texty umistene v /texts by mely zustavat pro instalace nezmenene (verze v instalaci
 * je totozna s verzi v SVN). Pro zmenu textu v konkretni instalaci slouzi /texts/specific,
 * kde je mozne vytvaret soubory ve stejne jmenne konvenci jako v /texts a se stejnym obsahem.
 * Kazdy samostatny zaznam ze souboru v /texts/specific prepise puvodni hodnotu z /texts pro
 * stejnou cestu v teckove notaci (neni nutne napr. text deregister.renew.wrongemail z cz.ini
 * prepisovat v specific/cz.ini, ale muze byt prepsan treba ve specific/cz.deregister.renew.ini).
 *
 *
 * @author Mgr. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


//class Ibulletin_Texts_Exception extends Exception {}

/**
 * Trida poskytujici metody potrebne pro vkladani textu do view skriptu
 * pripadne i uvnitr aplikace.
 *
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Texts
{
    /**
     * Aktualne nactene texty.
     *
     * @var Zend_Config
     */
    private $_texts = null;

    /**
     * Aktualne nactene neprelozene texty.
     *
     * @var Zend_Config
     */
    private $_rawTexts = null;

    private $_cacheDir = 'generated_cfg/';
    private $_textsDir = 'texts/';
    private $_specificDir = 'specific/';

    private static $_inst = null;
    private $_langs = array();
    private $_preparedFiles = array();

    /**
     * @var stdClass
     */
    private $_availableFiles = null;

    private $_action = null;
    private $_controller = null;
    private $_module = null;

    // Seznam modulu - pouzivame k rozliseni default ('') od ostatnich modulu (aktualne jen admin)
    private static $_modules = array('admin');
    private static $_availableLangs = array();

    const NO_CHANGE = 'NO_CHANGE';

    private $_switchedTo = null;


    /**
     * Nastavi do objektu modul, controller a action pro dalsi pouziti.
     */
    private function __construct($action = null, $controller = null, $module = null) {
        self::$_inst = $this;
    	Ibulletin_Texts::setActualContext($action, $controller, $module);
    	$this->_texts = new Zend_Config(array(), true);
    	$this->_rawTexts = new Zend_Config(array(), true);
    }


    /**
     * Vrati existujici instanci Ibulletin_Texts nebo vytvori novou a tu vrati.
     * Je mozne zmenit aktualni kontext pro ziskavani textu pri volani teto metody.
     *
     * POZOR! Pokud nekde zmenime kontext, je tato zmena trvala i pro nasledujici casti
     * programu, proto je vhodne po skonceni potreby zmeny kontextu zavolat
     * Ibulletin_Texts::setActualContext();
     *
     * @param string    Akce
     * @param string    Controller
     * @param string    Modul
     *
     * @return Ibulletin_Texts Jedina instance Ibulletin_Texts v aplikaci
     */
    public static function getInstance($action = null, $controller = null, $module = null)
    {
        if(self::$_inst === null){
            self::$_inst = new Ibulletin_Texts();
        }

        if($action !== null || $controller !== null || $module !== null){
            $action = $action !== null ? $action : self::NO_CHANGE;
            $controller = $controller !== null ? $controller : self::NO_CHANGE;
            $module = $module !== null ? $module : self::NO_CHANGE;
            Ibulletin_Texts::setActualContext($action, $controller, $module);
        }

        return self::$_inst;
    }


    /**
     * Urci primarni a sekundarni jazyk pro aktualni relaci.
     * Melo by byt spusteno az v controlleru kvuli tomu, aby byly k dispozici
     * informace o prihlasenem uzivateli.
     *
     * @return array    0=>primarni_jazyk, 1=>sekundarni_jazyk (jazyk s kompletnimi texty)
     */
    public static function getLangs()
    {
        $inst = Ibulletin_Texts::getInstance();
        // Pokud jiz bylo nacteno vratime nactene
        if(!empty( $inst->_langs)){
             return $inst->_langs;
        }

        $config = Zend_Registry::get('config');
        $langs = array(null, null);
        // Jazyk v adminu
        if((!empty(Ibulletin_AdminAuth::getInstance()->user_data) && $inst->_switchedTo != 'front') || $inst->_switchedTo == 'admin'){
            if(!empty(Ibulletin_AdminAuth::getInstance()->user_data->language)){
                $langs[0] = Ibulletin_AdminAuth::getInstance()->user_data->language;
            }
            else{
                $langs[0] = $config->admin->language;
            }
            $langs[1] = $config->admin->default_language;
        }
        // Jazyk ve frontendu
        else{
            $langs[0] = $config->general->language;
            $langs[1] = $config->general->default_language;
        }

        $inst->_langs = $langs;

        return $langs;
    }

    /**
     * vraci vsechny jazyky z nazvu souboru v adresari s textama
     *
     * @return array
     */
    public static function getAvailableLangs() {

        if (self::$_availableLangs) return self::$_availableLangs;

        $res = array();
        $dir1 = rtrim(self::getTextsDir(), '\\/') . DIRECTORY_SEPARATOR;
        $dir2 = rtrim(self::getTextsDir() . self::getSpecificDir(), '\\/') . DIRECTORY_SEPARATOR;

        // get all files in given directories
        $g = glob('{' . $dir1 . "*.ini" . ',' .
                        $dir2 . '*.ini' . '}', GLOB_BRACE);

        // replace first 2 letters of file extrapolating directory prefixes and filename suffixes
        $langs = preg_replace(array(
            '/^'.preg_quote($dir2,'/').'([a-z][a-z]).*$/',
            '/^'.preg_quote($dir1,'/').'([a-z][a-z]).*$/'),
            '$1', $g);
        $langs = array_unique($langs);
        $langs = array_combine($langs, $langs);

        if (!$langs) return false;

        self::$_availableLangs = $langs;
        return $langs;

    }

    /**
     * Nastavi podle zadanych parametru do objektu aktualni
     * kontext (modul, controller, akci), pokud nejsou zadany, pouziji
     * se parametry nalezene routerem ulozene v requestu.
     *
     * POZOR! Pokud nekde zmenime kontext, je tato zmena trvala i pro nasledujici casti
     * programu, proto je vhodne po skonceni potreby zmeny kontextu zavolat
     * Ibulletin_Texts::setActualContext();
     *
     * @param string    Akce
     * @param string    Controller
     * @param string    Modul
     */
    public static function setActualContext($action = null, $controller = null,
                                            $module = null) {
    	//$inst = $inst !== null ? $inst : Ibulletin_Texts::getInstance();
    	$inst = Ibulletin_Texts::getInstance();

        $request = Zend_Controller_Front::getInstance()->getRequest();

        if (!$request) {
            $d_action = 'index';
            $d_controller = 'default';
            $d_module = 'admin';
        } else {
            $d_action = $request->getActionName();
            $d_controller = $request->getControllerName();
            $d_module = $request->getModuleName();
        }

        $action = $action !== null ? $action : $d_action;
        $controller = $controller !== null ? $controller : $d_controller;
        $module = $module !== null ? $module : $d_module;

        $action !== self::NO_CHANGE ? $inst->_action = $action : null;
        $controller !== self::NO_CHANGE ? $inst->_controller = $controller : null;
        $module !== self::NO_CHANGE ? $inst->_module = $module : null;
    }
	/**
	 * vraci config object aktualnych prelozenych textu
	 *
	 * @return Zend_Config
	 */
    public static function getAllTexts() {

    	$inst = Ibulletin_Texts::getInstance();
    	return $inst->_texts;
    }

    /**
     * vraci config object surovych-neprelozenych textu spolu se specific textami.
     *
     * @param string $lang jazyk pro ktery se maji nacitat texty
     * @param string $excludemodule ktere moduly se maji vynechat pri nacitani
     * @return Zend_Config
     */
    public static function getRawTexts($lang, $excludemodule = '') {

        $inst = Ibulletin_Texts::getInstance();

        $store[0] = $inst->_availableFiles;
        $store[1] = $inst->_preparedFiles;
        $store[2] = $inst->_texts;
        $store[3] = $inst->_rawTexts;

        // clear internal texts representation
        $inst->reloadTexts();

        $files = array();

        // load all language default files in text root folder
        $dir = rtrim($inst->getTextsDir(), '\\/');

        $arr = array();
        $g = glob($dir . DIRECTORY_SEPARATOR . $lang . "*.ini");
        if (!$g) $g=array();
        foreach ($g as $fname) {
            $f = pathinfo($fname, PATHINFO_BASENAME);
            if (!$excludemodule ||
                !preg_match('/'.preg_quote($lang).'\.'.preg_quote($excludemodule).'.*\.ini/', $f)) {
                $arr[] = $f;
            }
        }
        //sort files based on segments of the filename
        usort($arr, array(__CLASS__, 'sortcmpBySegments'));

        // add DEFAULT texts
        $files = array_merge($files, $arr);

        // load all language specific files in specific folder
        $dir = rtrim($inst->getTextsDir() . $inst->getSpecificDir(), '\\/');

        $arr = array();
        $g = glob($dir . DIRECTORY_SEPARATOR . $lang . "*.ini");
        if (!$g) $g=array();
        foreach ($g as $fname) {
            $f = pathinfo($fname, PATHINFO_BASENAME);
            if (!$excludemodule ||
                !preg_match('/'.preg_quote($lang).'\.'.preg_quote($excludemodule).'.*\.ini/', $f)) {
                $arr[] = $inst->getSpecificDir() . pathinfo($fname, PATHINFO_BASENAME);
            }
        }
        //sort files based on segments of the filename
        usort($arr, array(__CLASS__, 'sortcmpBySegments'));

        // add SPECIFIC texts
        $files = array_merge($files, $arr);

        // time consuming I/O of loading files into zend_config object stored in singleton
        foreach ($files as $file) {
            $fpath = pathinfo($file, PATHINFO_BASENAME);
            $fdir = pathinfo($file, PATHINFO_DIRNAME) == '.' ? '' : pathinfo($file, PATHINFO_DIRNAME) . '/';
            $inst->loadFile($fpath, $fdir);
        }

        // retrieve structure
        $res = /*clone */$inst->_rawTexts;

        // load original texts
        $inst->_availableFiles = $store[0];
        $inst->_preparedFiles = $store[1];
        $inst->_texts = $store[2];
        $inst->_rawTexts = $store[3];

        return $res;
    }

    /**
     * vraci config object surovych-neprelozenych textu pro defaultni
     * jazyk bez nacitani specifickych souboru.
     *
     * @param string $lang jazyk pro ktery maji byt nacitany texty. pokud se lisi od general.default_language, prepise se defaultnima textama pro tenhle jazyk
     * @param string $excludemodule ktere moduly se maji vynechat pri nacitani
     * @return Zend_Config
     */
    public static function getDefaultLanguageRawTexts($lang, $excludemodule = '') {

        $inst = Ibulletin_Texts::getInstance();

        $store[0] = $inst->_availableFiles;
        $store[1] = $inst->_preparedFiles;
        $store[2] = $inst->_texts;
        $store[3] = $inst->_rawTexts;

        // clear internal texts representation
        $inst->reloadTexts();

        $files = array();

        $config = Zend_Registry::get('config');

        // load all language default files in text root folder
        $dir = rtrim($inst->getTextsDir(), '\\/');

        $g = glob($dir . DIRECTORY_SEPARATOR . $lang . "*.ini");
        if (!$g) $g=array(); // TODO: funkce glob vraci false nebo empty array v zavislosti od open_basedir (UGLY MESS)
        foreach ($g as $fname) {
            $f = pathinfo($fname, PATHINFO_BASENAME);
            if (!$excludemodule || !preg_match('/'.preg_quote($lang).'\.'.preg_quote($excludemodule).'.*\.ini/', $f)) {
                $files[] = $f;
            }
        }

        //sort files based on segments of the filename
        usort($files, array(__CLASS__, 'sortcmpBySegments'));

        // time consuming I/O of loading files into zend_config object stored in singleton
        foreach ($files as $file) {
            $fpath = pathinfo($file, PATHINFO_BASENAME);
            $fdir = pathinfo($file, PATHINFO_DIRNAME) == '.' ? '' : pathinfo($file, PATHINFO_DIRNAME) .'/';
            $inst->loadFile($fpath, $fdir);
        }

        // retrieve structure
        $res = $inst->_rawTexts;

        // load default language text and overwrite them with previously loaded texts
        if ($config->general->default_language != $lang) {

            $_lang = $config->general->default_language;
            // clear internal texts representation
            $inst->reloadTexts();

            $files = array();

            // load all language default files in text root folder
            $dir = rtrim($inst->getTextsDir(), '\\/');

            $g = glob($dir . DIRECTORY_SEPARATOR . $_lang . "*.ini");
            if (!$g) $g=array(); // TODO: funkce glob vraci false nebo empty array v zavislosti od open_basedir (UGLY MESS)
            foreach ($g as $fname) {
                $f = pathinfo($fname, PATHINFO_BASENAME);
                if (!$excludemodule || !preg_match('/'.preg_quote($_lang).'\.'.preg_quote($excludemodule).'.*\.ini/', $f)) {
                    $files[] = $f;
                }
            }

            //sort files based on segments of the filename
            usort($files, array(__CLASS__, 'sortcmpBySegments'));

            // time consuming I/O of loading files into zend_config object stored in singleton
            foreach ($files as $file) {
                $fpath = pathinfo($file, PATHINFO_BASENAME);
                $fdir = pathinfo($file, PATHINFO_DIRNAME) == '.' ? '' : pathinfo($file, PATHINFO_DIRNAME) .'/';
                $inst->loadFile($fpath, $fdir);
            }

            // clear all values for default language text, use multi_implode/explode functions to flatten and build array back up
            $default_lang_texts = array();
            foreach (Utils::multi_implode($inst->_rawTexts->toArray()) as $k => $v) {
                $default_lang_texts[$k] = '';
            }
            $default_lang_texts = new Zend_Config(Utils::multi_explode($default_lang_texts), true);

            // merge texts together
            $default_lang_texts->merge($res);
            // return
            $res = $default_lang_texts;

        }

        // load original texts
        $inst->_availableFiles = $store[0];
        $inst->_preparedFiles = $store[1];
        $inst->_texts = $store[2];
        $inst->_rawTexts = $store[3];

        return $res;

    }

    /**
     * Nacte soubor s jazykem do self::$_texts + prelozi znacky a ulozi jej do cache pokud je to potreba.
     *
     * @param String $file  Nazev souboru z /texts k pripraveni
     * @param String $path  Cesta k souboru textu od korene textuu
     */
    public static function loadFile($file, $path)
    {
        $inst = Ibulletin_Texts::getInstance();
        $cacheDir = $inst->_cacheDir;
        $textsDir = $inst->_textsDir;

        // sanitizace path
        $path = $path == '.' ? '' : $path;

        // Pripravime si prefix pro soubory, ktere nejsou v rootu config_text
        $prefix = rtrim(preg_replace('/\//', '_', $path),'_');
        $prefix = !empty($prefix) ? $prefix.'_' : '';

        $options = array('allowModifications' => true);

        $cached_file_path = rtrim($cacheDir,'\\/').'/'.$prefix.$file;
        $file_path = rtrim(rtrim($textsDir,'\\/').'/'.$path,'\\/').'/'.$file;

        // Pokud neni soubor pripraven, zkusime jestli je v cache a nacteme
        if(!in_array($prefix.$file, $inst->_preparedFiles)){
            // Pokud neexistuje soubor configu s pregenerovanymi texty nebo byl config_texts upraven,
            // provedeme nahrady tagu %%project_name%% a %%project_email%% za spravne hodnoty.
            if(!file_exists($cached_file_path)
                || filemtime($cached_file_path) < filemtime($file_path)
                || filemtime($cached_file_path) < filemtime('config.ini')
                || filemtime($cached_file_path) < filemtime('config_admin.ini'))
            {
                $fileStr = join("\n", file($file_path)); // TODO: proc se zde zdvojuje odradkovani v generovanem souboru textu?

                $fileStr = self::translator($fileStr);

                $fp = fopen($cached_file_path, 'w');
                fputs($fp, $fileStr);
                fclose($fp);
            }

            // Zapiseme do seznamu nactenych
            $inst->_preparedFiles[] = $prefix.$file;

            $newTexts = new Zend_Config_Ini($cached_file_path, null, $options);

            // Vytvorime podle jmena souboru cestu v objektech textu
            $parts = explode('.', $file);
            array_pop($parts);  // Odstraneni pripony souboru
            $actual = $inst->_texts;
            foreach($parts as $key => $part){
            	if($key == 0){
            		continue;
            	}

            	if(!isset($actual->$part)){
            		$actual->$part = new Zend_Config(array(), true);
            	}
            	$actual = $actual->$part;
            }
            // mergneme text do odpovidajici hloubky, tim je vse ulozeno i v $inst->_texts, protoze
            // objekty jsou predavany odkazem
            $actual->merge($newTexts);

            // ulozime i raw texty pred prekladem
            $rawTexts = new Zend_Config_Ini($file_path, null, $options);
            $actual = $inst->_rawTexts;

            // Vytvorime podle jmena souboru cestu v objektech textu
            $parts = explode('.', $file);
            array_pop($parts);  // Odstraneni pripony souboru

            foreach($parts as $key => $part){
            	if($key == 0){
            		continue;
            	}

            	if(!isset($actual->$part)){
            		$actual->$part = new Zend_Config(array(), true);
            	}
            	$actual = $actual->$part;
            }

            // mergneme text do odpovidajici hloubky, tim je vse ulozeno i v $inst->_rawTexts, protoze
            // objekty jsou predavany odkazem
            $actual->merge($rawTexts);

        }

        //echo $prefix.$file.'<br/>';
    }


    /**
     * Nacte potrebne soubory textuu pro zadany identifikator textu.
     *
     * @param $ident String Retezec oddeleny teckami identifikujici cestu k textu
     */
    public static function prepareTexts($ident)
    {
        $inst = Ibulletin_Texts::getInstance();
        $langs = self::getLangs();

        if(empty($inst->_availableFiles)){
            $textsDir = $inst->_textsDir;
            $inst->_availableFiles = new stdClass();
            // Nacteme strukturu souboru, ktere jsou v adresari s texty. Genericke napred, texty specificke
            // pro instalaci pozdeji, aby prepsaly genericke.
            foreach(array('', $inst->_specificDir) as $dirName){
                $dir = dir($textsDir.'/'.$dirName);
                while($dir && $file = $dir->read()) {
                    $m = array();
                    if ($file == '.' || $file == '..') continue;
                    if(preg_match('/^(.*)\.ini$/i', $file, $m)){
                        $parts = explode('.', $m[1]);
                        $actual = $inst->_availableFiles;
                        foreach($parts as $part){
                            if(!isset($actual->$part)){
                                $actual->$part = new stdClass();
                                $actual->$part->__files__ = array();
                            }

                            $actual = $actual->$part;
                        }
                        // Data o souboru dame do stdClass
                        $fileCls = new stdClass();
                        $fileCls->__filenameStr__ = $file;
                        $fileCls->__isFileLoaded__ = false;
                        $fileCls->__filePath__ = $dirName; // Jen cesta k souboru uvnitr adresare s texty
                        // Pridame soubor do spravneho umisteni ve stromu
                        $actual->__files__[] = $fileCls;
                    }
                }
                if ($dir) $dir->close();
            }
        }

        $availableFiles = $inst->_availableFiles;


        // Nacteme vsechny existujici soubory na zadane ceste - nejprve pro default lang a nasledne
        // pro preferovanou lang
        $langs = array_unique(array_reverse($langs));
        $parts = explode('.', $ident);
        /*
        // Najdeme nechtene moduly
        $identModule = in_array($prats[0], self::$_modules) ? $prats[0] : null;
        // Odebereme aktualni modul z nechtenych
        $unwantedModules = self::$_modules;
        if($identModule !== null){
            unset($unwantedModules[array_search($identModule, $unwantedModules)]);
        }
        */
        if(!empty($parts)){
            foreach($langs as $lang){
                //$depth = 0; // Hloubka zanoreni ve stromu podle identifikatoru
                $newParts = $parts;
                array_unshift($newParts, $lang);
                $actual = $inst->_availableFiles;
                //echo join('|', $newParts);
                foreach($newParts as $part){
                    //$depth ++;
                    if(isset($actual->$part)){
                        //echo "$part ";
                        if(!empty($actual->$part->__files__)){
                            // Zpracovavame kazdy soubor odpovidajici danemu prefixu
                            foreach($actual->$part->__files__ as $file){
                                // Preskocime jiz nactene soubory
                                if($file->__isFileLoaded__){
                                   continue;
                                }
                                //echo '<br/>Load0:'.$file->__filenameStr__.' "'.$file->__filePath__.'" ident:'.$ident.'<br/>';
                                self::loadFile($file->__filenameStr__, $file->__filePath__);
                                $file->__isFileLoaded__ = true;

                                /* Zda se, ze neni potreba nacitat nic vic nez to co je na ceste identifikatoru
                                // Postupne pomoci fronty nacteme zbytek podvetve, snazime se preskocit obsahy jinych modulu
                                // Musime nacitat
                                $todo = get_object_vars($actual->$part);
                                while(!empty($todo)){
                                    $curr = array_pop($todo);
                                    // Preskocime vse, co patri do jineho modulu - jen pokud je hloubka v ident 1
                                    echo "\n<br/>curr:".print_r($curr, true)."<br/>";
                                    if($depth == 1 && in_array($curr, $unwantedModules)){
                                        echo "\n<br/>Skipped:".$ident;
                                        continue;
                                    }
                                    if($curr instanceof stdClass){
                                        // Pridame nove prvky k provedeni
                                        $todoOld = $todo;
                                        $todo = get_object_vars($curr);
                                        foreach($todoOld as $val){
                                            $todo[] = $val;
                                        }
                                        if(!empty($curr->__files__)){
                                            foreach($curr->__files__ as $file1){
                                                echo 'Load1:'.$file1->__filenameStr__.' '.$file1->__filePath__.' ident:'.$ident.'<br/>';
                                                self::loadFile($file1->__filenameStr__, $file1->__filePath__);
                                                $file1->__isFileLoaded__ = true;
                                            }
                                        }
                                    }
                                }
                                //*/
                            }
                        }
                        $actual = $actual->$part;
                    }
                    else{
                        //echo "x$part ";
                        break;
                    }
                }
            }
        }
    }


    /**
     * Prelozi v retezci configu znacky tak jak je potreba a vrati vysledek.
     * %%project_email%%, %%project_name%%, %%project_name_{1-8}%%
     *
     * @param  String   $fileStr    Obsah souboru se znackami
     * @return String   Obsah souboru s nahrazenymi znackami
     */
    public static function translator($fileStr)
    {
        $config = Zend_Registry::get('config');

        $tran = array('%%project_email%%' => $config->general->project_email,
                      '%%project_name%%' => $config->general->project_name,
                      );
        // Pridame dalsi pady nazvuu projektuu podle nastaveni v configu
        for($i=2; $i<8; $i++){
            $case = 'p'.$i;
            if(!empty($config->general->$case->project_name)){
                $tran['%%project_name_'.$case.'%%'] = $config->general->$case->project_name;
            }
        }

        $fileStr = strtr($fileStr, $tran);

        return $fileStr;
    }


    /**
     * @return string   Vrati adresar s texty.
     */
    public static function getTextsDir()
    {
        $inst = self::getInstance();
        return $inst->_textsDir;
    }

    /**
     * @return string   Vrati adresar s texty.
     */
    public static function getSpecificDir()
    {
    	$inst = self::getInstance();
    	return $inst->_specificDir;
    }

    /**
     * @return string   Vrati adresar s texty.
     */
    public static function getCacheDir()
    {
    	$inst = self::getInstance();
    	return $inst->_cacheDir;
    }

    /**
     * Vrati podle zadaneho identifikatoru odpovidajici text. Pokud je zadan identifikator
     * s teckou na zacatku, je vyhledavan v prostoru global. Bezne staci zadat identifikator
     * textu, nekdy je treba pridat i nazev akce, controlleru a modulu.
     *
     * @param string    Identifikator textu - jakykoli suffix tvaru
     *                  module.controller.action.identifikator_textu
     * @return string   Text z configu textu podle zadaneho indentifikatoru.
     */
    public static function get($ident) {
        $inst = Ibulletin_Texts::getInstance();
    	//$texts = Zend_Registry::get('config_texts');
    	$texts = $inst->_texts;

    	$textident = '';
    	$action = $inst->_action;
    	$controller = $inst->_controller;
    	$module = $inst->_module;

        if(empty($ident)) {
            return '';
        }

        $paramA = explode('.', $ident);

        $count = count($paramA);
        if($count == 1 && !empty($paramA[0])) {
            $textident = $paramA[0];
        }
        elseif($count == 2 && !empty($paramA[0])) {
            $textident = $paramA[1];
            $action = $paramA[0];
        }
        elseif($count == 2 && empty($paramA[0])) {
            $textident = $paramA[1];
            $action = '';
            $controller = '';
            $module = '';
        }
        elseif($count == 3 && !empty($paramA[0])) {
            $textident = $paramA[2];
            $action = $paramA[1];
            $controller = $paramA[0];
        }
        elseif($count == 4 && !empty($paramA[0])) {
            $textident = $paramA[3];
            $action = $paramA[2];
            $controller = $paramA[1];
            $module = $paramA[0];
        }
        else{
            Phc_ErrorLog::warning('Ibulletin_Texts', 'Nebyl nalezen text pro identifikator "'.
                $textident.'", identifikovany modul, controller, akce: "'.$module.'", "'.$controller
                .'", "'.$action.'". Originalni vstupni identifikator: "'.$ident.'"');
            return '';
        }

        if(!empty($module) && $module != 'default'){
            self::prepareTexts($module.'.'.$controller.'.'.$action.'.'.$textident);
            if(isset($texts->$module->$controller->$action->$textident)){
                return $texts->$module->$controller->$action->$textident;
            }
        }
        elseif((empty($module) || $module == 'default') && empty($action) && empty($controller)){
            self::prepareTexts($textident);
            if(isset($texts->$textident)){
                return $texts->$textident;
            }
        }
        else{
            self::prepareTexts($controller.'.'.$action.'.'.$textident);
            if(isset($texts->$controller->$action->$textident)){
                return $texts->$controller->$action->$textident;
            }
            else{
                Phc_ErrorLog::warning('Ibulletin_Texts', 'Nebyl nalezen text pro identifikator "'.
                    $textident.'", identifikovany modul, controller, akce: "'.$module.'", "'.$controller
                    .'", "'.$action.'". Originalni vstupni identifikator: "'.$ident.'"');
                return '';
            }
        }
    }

    /**
     * Vrati podle zadaneho identifikatoru odpovidajici sadu textuu - obvykle pro celou akci.
     * Pokud neni zadan parametr, vybere se sada podle aktualniho umisteni (module, controller, action),
     * pokud je zadan parametr je vracena sada s danym prefixem
     *
     * @param string    Identifikator sady (presne jako je pouzito v ini souboru textu -
     *                  jakykoli prefix), musi byt kompletni od zacatku, tato metoda neprovadi
     *                  zadne doplnovani na rozdil od metody self::get()
     * @return Zend_Config   Sada textu z configu textu podle zadaneho prefixu nebo aktualniho umisteni.
     *                       Pokud neni sada nalezena, vraci null
     */
    public static function getSet($ident = null)
    {
        $inst = Ibulletin_Texts::getInstance();
        //$texts = Zend_Registry::get('config_texts');
        $texts = $inst->_texts;

        $textident = '';
        $action = $inst->_action;
        $controller = $inst->_controller;
        $module = $inst->_module;


        if(empty($ident)) {
            if(empty($module) || $module == 'default'){
                self::prepareTexts($controller.'.'.$action);
                if(!empty($texts->$controller->$action)){
                    return $texts->$controller->$action;
                }
                else{
                    Phc_ErrorLog::warning('Ibulletin_Texts', 'Nebyla nalezena sada textu pro prefix "'.
                        $ident.'", identifikovany modul, controller, akce: "'.$module.'", "'.$controller
                        .'", "'.$action.'".'."\n".new Exception());
                }
            }
            else{
                self::prepareTexts($module.'.'.$controller.'.'.$action);
                if(!empty($texts->$module->$controller->$action)){
                    return $texts->$module->$controller->$action;
                }
                else{
                    Phc_ErrorLog::warning('Ibulletin_Texts', 'Nebyla nalezena sada textu pro prefix "'.
                        $ident.'", identifikovany modul, controller, akce: "'.$module.'", "'.$controller
                        .'", "'.$action.'".'."\n".new Exception());
                }
            }
        }
        else{
            self::prepareTexts($ident);
            $paramA = explode('.', $ident);
            $set = $texts;
            foreach($paramA as $item){
                if(!empty($set->$item)){
                    $set = $set->$item;
                }
                else{
                    Phc_ErrorLog::warning('Ibulletin_Texts', 'Nebyla nalezena sada textu pro prefix "'.
                        $ident.'", identifikovany modul, controller, akce: "'.$module.'", "'.$controller
                        .'", "'.$action.'". Neexistujici polozka: "'.$item.'".'."\n".new Exception());

                    return new Zend_Config(array(), true);
                }
            }

            return $set;
        }

        return new Zend_Config(array(), true);
    }

    /**
     * Switches languages between front and admin. This is useful if
     * it is needed to generate texts for frontend when in admin - e.g. when
     * sending emails. It is nescessary to switch back after required actions are done.
     *
     * @param   string $target  admin|front Where to switch languages of texts generated by this object.
     */
    public static function switchFrontAdmin($target)
    {
        $inst = Ibulletin_Texts::getInstance();

        if($inst->_switchedTo != $target){
            // Set _switchedTo
            if($target == 'admin'){
                $inst->_switchedTo = 'admin';
            }
            if($target == 'front'){
                $inst->_switchedTo = 'front';
            }

            $inst->reloadTexts();

        }
    }

    /**
     * clears all cached variables. next object call will load texts again
     */
    public static function reloadTexts() {

        $inst = Ibulletin_Texts::getInstance();

        // Force redetection of languages
        $oldLangs = $inst->getLangs();
        $inst->_langs = array();
        $newLangs = $inst->getLangs();

        // If languages have changed, it is nescessary to clean whole object
        //if($oldLangs[0] != $newLangs[0] || $oldLangs[1] != $newLangs[1]){
            // HACK Force reload of all ini files, can be slow if many switches are done
            $inst->_availableFiles = null;
            $inst->_preparedFiles = array();
            $inst->_texts = new Zend_Config(array(), true);
            $inst->_rawTexts = new Zend_Config(array(), true);
       // }
    }

    /**
     * sortovaci funkce ktera porovnava retezce podle delky segmentu (delimiter .)
     * @param $v1
     * @param $v2
     * @return int
     */
    public static function sortcmpBySegments($v1,$v2) {
        $c1 =  count(explode('.', $v1));
        $c2 =  count(explode('.', $v2));
        if ($c1 > $c2) return 1;
        if ($c1 < $c2) return -1;
        if ($c1 == $c2) return 0;
    }
    
    
    /**
     * Vrati aktualni sadu textu front | admin
     * @return string
     */
    public function getSwitchedTo() {
        
        return $this->_switchedTo;
        
    }
    
}