<?php

/**
 * inBulletin - content - Indetail.php
 *
 * Obsahuje flash nebo HTML inDetailu a funkce potrebne k jeho ovladani/interakci
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}

/**
 * Exception
 *
 * Code:
 * 8 - Nebylo zadano ID uzivetele.
 *
 */
class Ibulletin_Content_Indetail_Exception extends Exception {}


/**
 * Trida zprostredkovavajici nacitani inDetailu jako contentu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Content_Indetail extends Ibulletin_Content_Abstract
{

   /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "indetail_%d.phtml";

    /**
     * @var String Jmeno souboru s konfiguraci videa.
     */
    var $configFileName = "config.xml";

    /**
     * @var bool Je objekt pripraveny k pouziti ve frontendu?
     */
    var $ready = false;

    /**
     * @var bool    Obsahuje content Html prezentaci?
     */
    var $hasHtml = false;

    /**
     * @var bool    Obsahuje content Html inPad prezentaci (balicek zip)?
     */
    var $hasInPadHtml = false;

    /**
     * @var bool    Obsahuje content Flash prezentaci?
     */
    var $hasFlash = false;

    /**
     * @var bool    Zakazat nahrazovani cest ke statickemu obsahu v souborech HTML5 prezentace
     */
    var $adaptHtml5Paths = false;

    /**
     * @var string  Cesta k obrazkum nahledu slajdu prezentace.
     */
    var $slidePreviewsPath = 'slide_previews';

    /**
     * @var string   Verze prezentace pro testovani v adminu - NIKDY neukladat do DB nic jineho nez null
     */
    var $testingVersion = null;

    /**
     * @var string  "users.name" uzivatele, ktery je pouzivan na testovani kvuli dohledani jeho ID
     *              tyka se predevsim mobilnich zarizeni.
     */
    public static $testerName = 'Online Tester';

    /**
     * Cesty k vyznamnym souborum contentu. Museji byt doplneny o $this->getBasepath()
     */
    var $htmlPresIndexFile = 'html5/index.html';
    var $flashConfigFile = 'flash/config.xml';
    
    
    /**
     * pozadovany pocet bodu, ktery uzivatel musi splnit, napr. pro vydani certifikatu
     */
    var $points = 0;



    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     *
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepare($req)
    {
        parent::prepare($req);

        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $config = Zend_Registry::get('config');

        // Nastavime do JS cestu pro reportovani dat z videi
        $reportingUrl = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'videostats',
            'content' => $this->id, 'page_id' => $this->pageId), 'service').'/';
        $js = Ibulletin_Js::getInstance();
        $js->vars->videoReportingUrl = $reportingUrl;

        // Kvuli testovani FORCE verze ke zobrazeni
        if($req->getParam('forceVersion')){
            $this->testingVersion = $req->getParam('forceVersion');
        }

        list($versionToUse, $useFlashIfAvailableInBrowser) = $this->determineVersionToUse();
        if($versionToUse == 'flash'){
            Ibulletin_Js::addJsFile('jquery.js');
            // swfobject natahujeme defaultne jen pro flash!
            Ibulletin_Js::addJsFile('swfobject.js');
            Ibulletin_Js::addJsFile('indetail.lib.js'); // V HTML5 prezentacich ma byt jiz obsazen
            // Inicializace indetail.lib
            Ibulletin_Js::addPlainCode('var indetail = null');
            Ibulletin_Js::addFunction('indetail = new Indetail(null, true, true);', null, array(), true);
        }

        // Ulozime do statistik, kterou verzi uzivateli ukazujeme
        $stats = Ibulletin_Stats::getInstance();
        $stats->setAttrib('displayed_version', $versionToUse);

        // Pokud bylo volano s forceVersion, zkusime zmenit displayed_version v minulem page_view na html_redirected
        // V adminu pri testovani je nam to celkem jedno, zmena by se ale nemela provest kvuli chybejicimu page_views_id
        // a pripadne nesouhlasici minule zobrazene verzi
        if($req->getParam('forceVersion') == 'flash' && $this->hasFlash && $this->pageId){
            $stats->changeLastDisplayedVersion('html_redirected', 'html', $this->pageId);
        }

    }


    /**
     * Vytahne z index.html HTML prezentace obsah body a rozparsuje CSS a JS z head tagu
     * pro nacteni z inBoxu. CSS a JS pak nacte do head.
     *
     * Provadi opravu relativnich cest k souborum v BODY (pokud neni zakazano v $this->adaptHtml5Paths),
     * JS soubory maji cesty nahrazeny jiz pri ulozeni contentu v adminu.
     *
     * @return string   HTML uvnitr body
     */
    public function prepareHtmlPresentationData()
    {
        if(!$this->hasHtml){
            return "";
        }

        // Nacteme index.html
        // Pokud existuje shadow, pouzijeme shadow bez upravenych cest, protoze upravene cesty
        // znemoznuji pouziti JS nacitanych z head
        if(file_exists($this->getBasepath().$this->htmlPresIndexFile.'.shadow')){
            $file = file($this->getBasepath().$this->htmlPresIndexFile.'.shadow');
            //$file = file($this->getBasepath().$this->htmlPresIndexFile);
        }
        else{
            $file = file($this->getBasepath().$this->htmlPresIndexFile);
        }

        
        // Najdeme hlavicky obsahujici JS a CSS
        $inHead = false;
        $inScript = false;
        $inBody = false;
        $headFiles = array();
        $meta = array();
        $body = "";
        $script = "";
        foreach($file as $num => $line){
            // Jsme uvnitr head
            if(preg_match('/<head>/i', $line) || $inHead){
                $inHead = true;
                // <link>
                
                //odstranime zakomentovane tagy
                $line = preg_replace('/<!--.*-->/', '', $line);
             
                $m = array();
                if(preg_match_all('/<link[^>]*href[^>=\'\"]*=[^>\'\"]*[\"\']([^\"\'>]+)[\"\'][^>]*>/i', $line, $m, PREG_PATTERN_ORDER) && !$inScript){

                	//detect external css
                	if (preg_match('/^(http|https):\/\/.*$/', $m[1][0], $r)) {
                		Ibulletin_HtmlHead::addExternalCss($r[0]);
	                } else {
		                $headFiles = array_merge($headFiles, $m[1]);
	                }

                }

                // <meta>
                $m = array();
                if(preg_match_all('/<meta[^>]*>/i', $line, $m, PREG_PATTERN_ORDER) && !$inScript){
                    $meta = array_merge($meta, $m[0]);
                }

                // <script>
                $m = array();
                if(preg_match_all('/<script[^>]*src[^>=\'\"]*=[^>\'\"]*[\"\']([^\"\'>]+)[\"\'][^>]*>/i', $line, $m, PREG_PATTERN_ORDER)){
                    $headFiles = array_merge($headFiles, $m[1]);
                }

                // <script> bez src (vlozeny JS zdrojovy kod), u JS code uz prekladame cesty k souboru na spravne URL
                //$line = $this->correctHtmlStaticPaths($line); // OMYL?
                $m = array();
                if((preg_match('/<script([^>]*[\'"]text\/javascript[^>]*)[^\/]>(.*)$/i', $line, $m)
                    && !preg_match('/src[^>=\'\"]*=/i', $m[1]))
                    || $inScript)
                {
                    if(!empty($m) && !empty($m[2])){
                        $script .= $m[2];
                    }

                    // Prelozime i pro JS vsechny relativni cesty ke statickym souborum
                    if($this->adaptHtml5Paths){
                        $line = $this->correctHtmlStaticPaths($line);
                    }

                    if($inScript && !preg_match('/<\/script>/i', $line)){ // Pouze pokud uz jsme uvnitr a neni to posledni radek
                        $script .= $line;
                    }
                    $inScript = true;

                    // Konec script
                    $m1 = array();
                    if(preg_match('/^(.*)<\/script>/i', $line, $m1)){
                        $inScript = false;
                        $script .= $m1[1];
                    }
                }
            }

            // Konec head
            if(preg_match('/<\/head>/i', $line)){
                $inHead = false;
            }

            // Prelozime na kazdem radku body cesty k souborum contentu na spravne URL
            // POZOR, jsou upraveny i cesty ve vsech JS, to se vsak provadi pri uklozeni contentu
            // zde potrebujeme nahradit cesty jen u nekterych veci
            // Pouze pokud je povoleno.
            if($this->adaptHtml5Paths){
                $line = $this->correctHtmlStaticPaths($line);
            }


            // Najdeme <body>
            $m = array();
            if(preg_match('/<body[^>]*>(.*)/i', $line, $m) || $inBody){
                // Posbirani radku body
                if($inBody && !preg_match('/<\/body>/i', $line)){
                    $body .= $line;
                }

                $inBody = true;
                // Z prvniho radku body musime ustrihnout vse pred <body> vcetne
                if(!empty($m) && !empty($m[1])){
                    $body .= $m[1];
                }

                // Posledni radek v body
                $m1 = array();
                if(preg_match('/(.*)<\/body>/i', $line, $m1)){
                    $body .= $m1[1];
                    $inBody = false;
                }
            }
        }


        // Nacteme META tagy
        foreach($meta as $m){
            Ibulletin_HtmlHead::addMetaRaw($m);
        }

        // Nacteme CSS a JS do head
        foreach($headFiles as $file){
            if(preg_match('/\.js$/i', $file)){
                // DD_belatedPNG HACK - belatedPNG nacitame jen pro IE 6 pomoci podminky vlozene v Ibulletin_HtmlHead::render()
                if(preg_match('/belatedPNG/i', $file)){
                    //$file = $this->getBaseUrl().'html5/'.$file;
                    $file = $this->correctHtmlStaticPaths($file);
                    Ibulletin_HtmlHead::addFile($file);
                }
                else{
                    //$file = $this->getBasepath().'html5/'.$file;
                    // Vkladame relativni cestu, protoze je pak pouzita uvnitr FS a ne jako URL
                    $file = $this->correctHtmlStaticPaths($file, null, false);
                    Ibulletin_Js::addJsFile($file);
                }
            }
            elseif(preg_match('/\.css$/i', $file)){
                //$file = $this->getBaseUrl().'html5/'.$file;
                // Do HtmlHead posilame URL s pocatecnim lomitkem
                $file = $this->correctHtmlStaticPaths($file);
                Ibulletin_HtmlHead::addFile($file);
            }
            //echo $file;
        }

        // JS kod do head
        if(!empty($script)){
            Ibulletin_Js::addPlainCode($script, true);
        }

        // Prelozime znacky (hlavne kvuli %%link) - jen pokud se maji prekladat cesty (nastaveni je spojene)
        if($this->adaptHtml5Paths){
            $body = Ibulletin_Marks::translate($body, '', null, array(), array(), false);
        }

        return $body;
    }

    /**
     * V HTML musime nahradit vsechny relativni url na soubory, ktere patri ke contentu
     * cestami od korene webu. Najdeme vse co vypada jako soubor a zjistime, jestli takovy
     * soubor existuje, pokud ano, cestu nahradime spravnou cestou.
     *
     * Nalezeny soubor musi byt ohraniceny nekterym z nasledujicich znaku (nemusi byt stejne na zacatku a na konci):
     * whitespace (\s), ', ", :, ?, =, (, ), |
     *
     * @param string/array      String nebo pole sgtringu (radky)
     * @param string            Cesta k souboru, ze ktereho data pochazeji - kvuli zkouseni relativni cesty
     * @param bool              Nahrazovat cesty za absolutni URL? Pokud je false, jsou cesty nahrazeny
     *                          za relativni cesty od korene webu (hodi se pro nacitani JS). (DEFAULT true)
     * @return string/array     String nebo pole stringu s upravenymi cestami k souborum
     */
    public function correctHtmlStaticPaths($data, $pathToFile = null, $replaceToUrl = true)
    {
        if(!is_array($data)){
            $data = array($data);
            $wasString = true;
        }
        else{
            $wasString = false;
        }

        $urlHlpr = new Zend_View_Helper_Url();
        $basePath = $this->getBasepath().'html5/';
        $baseUrl = $this->getBaseUrl().'html5/';
        $webBase = $urlHlpr->url(array(), 'default', true);

        foreach($data as $key => $line){
            $m = array();
            // Najdeme vsechny vyskyty retezcu vypadajicich jako soubor
            // Hledame cesty zacinajici a koncici nekterym z nasledujicich znaku (zacatek a konec mohou byt ruzne znaky)
            // space, ', ", :, ?, =, (, ), |
            // Pozadujeme nejprve max 260 znaku dlouhy retezec bez lomitka, potom musi nasledovat
            // max 2000 znaku dlouhy retezec i s lomitky - toto je hack na rychlost pri cteni prilis dlouhych retezcu
            // TODO zvzit pridani [\/] za max 260 znaku retezec reprezentujici nazev jednoho adresare
            if(preg_match_all('/([^\s\'"\:\?\=\(|\/]{1,260}[\/\.][^\s\'"\:\?\=\(|]{0,2000}\.[a-z0-9]{2,4})([\'\"\? \:\&\=\)\|]|$)/im', $line, $m, PREG_PATTERN_ORDER)){
                // Testujeme vsechny nalezene retezce, jestli nejsou souborem v tomto contentu
                foreach($m[1] as $path){
                    //echo $baseUrl.$path.'<br/>';
                    //echo $basePath.$path.'<br/>';
                    $newPath = null;
                    // Zkousime najit s cestou od souboru z ktereho cesta pochazi
                    if($pathToFile){
                        // Odstraneni ../
                        $pathToFile1 = preg_replace('/\/[^\/]+\/\.\.\//', '/', $pathToFile.'/'.$path);
                        //echo $pathToFile1.'<br/>';
                        if(file_exists($pathToFile1)){
                            if($replaceToUrl){
                                $pathToFile1 = '/'.$webBase.'/'.$pathToFile1;
                            }
                            $newPath = $pathToFile1;
                        }
                    }

                    // Pokud soubor existuje v tomto contentu, pridame k ceste prefix a nahradime v originale
                    if(!$newPath && file_exists($basePath.$path)){
                        $newPath = $replaceToUrl ? $baseUrl.$path : $basePath.$path;
                    }

                    // Pokud byl soubor nekde nalezen, nahradime cestu
                    if($newPath){
                        $newPath = preg_replace('/\/\.\//', '/', $newPath); // odstranit /./
                        $newPath = preg_replace('/[\/]+/', '/', $newPath); // nahradit vicenasobna "/"
                        //echo 'newpath: '.$newPath.' '.($replaceToUrl?1:0).'</br>';
                        $line = preg_replace('/([\s\'"\:\?\=\(|]|^)'.str_replace('/', '\/', $path).'/im', '$1'.$newPath, $line);
                    }
                }
                $data[$key] = $line;
            }
        }

        // Vracime pole nebo jen string podle toho, co uzivatel zadal
        if($wasString){
            return $data[0];
        }
        else{
            return $data;
        }
    }


    /**
     * Vrati renderovaci data contentu s ohledem na nastavenou pozici v hlavnim contentu.
     *
     * Nachysta do view data jednotlivych prispevku a pripadne predvyplneni formulare.
     */
    public function getContent()
    {
        $config = Zend_Registry::get('config');
        $urlHlpr = new Zend_View_Helper_Url();
        $baseUrl = $urlHlpr->url(array(),'default',true);

        $path = $baseUrl . $config->content_article->basepath.$this->id.'/';
        $view = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer')->view;

        // Prelozime znacky
        if(isset($this->html)){
            $html = Ibulletin_Marks::translate($this->html[$this->sheet_number - 1], $path, $this);
        }
        else{
            $html = '';
        }

        // Pripravime do JS cestu k HTML5 prezentaci
        Ibulletin_Js::getInstance()->vars->incampaign_base_url = $this->getBaseUrl().'html5/';


        // Html se bude prohanet eval(), takze na zacatek pridame koncovy tag php
        return array('html' => '?> '.$html);
    }

    /**
     * Vrati dalsi data, ktera pouziva objekt ve view
     *
     * @return array/stdClass    Dalsi data, ktera pouziva obsah pro sve zobrazeni ve view
     */
    public function getData(){
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        $config = Zend_Registry::get('config');

        $data = new stdClass;

        $data->contentId = $this->id;
        $data->pageViewsId = Ibulletin_Stats::getInstance()->page_view_id;
        // Pokud jsme v adminu a je zadano pageViewsId, pouzijeme jej - jedna se o testovani prezentace
        if($request->getParam('pageViewsId') && Zend_Auth::getInstance()->hasIdentity()){
            $data->pageViewsId = $request->getParam('pageViewsId');
        }

        // Data kolem HTML prezentace
        list($data->presentationVersion, $data->useFlashIfAvailableInBrowser) = $this->determineVersionToUse();
        
        if($data->presentationVersion == 'html'){
            
            //je-li v configu povoleno renderovani indetailu do iframe, odesleme v datech do view skriptu atribut o renderovani, podle neho pak muzeme do
            //view skriptu vlozit iframe s url na rendercontentController s parametrem iframe=1
            // se kterým se teprve proveder skutecny render contentu
            if (!empty($config->indetail->renderToIframe) && !$request->getParam('iframe')) {
                $data->renderToIframe = 1;
            } else {
            //$data->baseUrl = 'http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$this->getBaseUrl().'html5/';
            // Nastavime base pro relativni URL v celem dokumentu
            //Ibulletin_HtmlHead::getInstance()->base = $this->getBaseUrl().'html5/';
                $data->htmlPresentation = $this->prepareHtmlPresentationData();
                $data->jsServerVars = $this->getJsUsersData();
                $data->statsData = $this->getStatsData();
            }
        }

        $data->baseUrl = $this->getBaseUrl();

        return $data;
    }

    /**
     * Sluzba pro ziskani statistickych dat k indetailu.
     * parametry pro strankovani a razeni jsou : page, rows, sidx, sord
     * @return json @see getIndetailStats()
     */
    public function getstatsService() {

        $frontController = Zend_Controller_Front::getInstance();
        $req = $frontController->getRequest();
        $db = Zend_Registry::get('db');

        $pageViewsId = $req->getParam('pageviewsid', null);


        try{
            $data = $this->getIndetailStats($pageViewsId,
                $req->getParam('page', 1),
                $req->getParam('rows', NULL),
                $req->getParam('sidx', 'id'),
                $req->getParam('sord', 'DESC'));

            $res = $data;
        }
        catch(Exception $e){
            Phc_ErrorLog::error('Ibulletin_Content_Indetail::getstatsService()',
                "Nepodarilo se ziskat statisticka data k inDetailu pro page_views_id = ". $pageViewsId . " Puvodni vyjimka : ". $e);
            $res = array('error' => true);
        }

        $frontController->getResponse()->setHeader('Content-type','application/json');
        echo  Zend_Json::encode($res);
    }

    /**
     * sluzba pro ziskani odpovedi z indetailu.
     * povinnym parametrem v requestu je id reprezentujici answers_all_v.indetail_stats_id
     * @return json @see getIndetailAnswers()
     */
    public function getanswersService() {

        $frontController = Zend_Controller_Front::getInstance();
        $req = $frontController->getRequest();
        $db = Zend_Registry::get('db');

        $indetailStatsId = $req->getParam('id', null);

        if(empty($indetailStatsId)){
            Phc_ErrorLog::warning('Ibulletin_Content_Indetail::getanswersService()',
                'Pri ziskavani dat z inDetailu nebylo predano id. Request objekt: '."\n".print_r($req->getParams(), TRUE));
            $res = array('error' => true);

        } else {
            try{
                $data = $this->getIndetailAnswers($indetailStatsId);
                $res = $data;
            }
            catch(Exception $e){
                Phc_ErrorLog::error('Ibulletin_Content_Indetail::getanswersService()',
                    "Nepodarilo se ziskat odpovedi k inDetailu pro answers_all_v.indetail_stats_id = ". $indetailStatsId . " Puvodni vyjimka : ". $e);
                $res = array('error' => true);
            }

        }

        $frontController->getResponse()->setHeader('Content-type','application/json');
        echo  Zend_Json::encode($res);

    }

    /**
     * Sluzba pro ziskani statistickych dat k indetailu.
     * parametre pro strankovani a razeni jsou : page, rows, sidx, sord
     * @return json @see getIndetailStats()
     */
    public function getplayerstatsService() {

        $frontController = Zend_Controller_Front::getInstance();
        $req = $frontController->getRequest();
        $db = Zend_Registry::get('db');

        $contentId = $req->getParam('contentid', null);
        $userId= $req->getParam('userid', null);
        $pageViewsId= $req->getParam('pageviewsid', null);

        try{
            $data = $this->getIndetailPlayerStats($contentId, $userId, $pageViewsId,
                $req->getParam('page', 1),
                $req->getParam('rows', NULL),
                $req->getParam('sidx', 'id'),
                $req->getParam('sord', 'DESC'));

            $res = $data;
        }
        catch(Exception $e){
            Phc_ErrorLog::error('Ibulletin_Content_Indetail::getplayersstatsService()',
                "Nepodarilo se ziskat statisticka data k inDetailu pro content_id = ". $contentId . " Puvodni vyjimka : ". $e);
            $res = array('error' => true);
        }

        $frontController->getResponse()->setHeader('Content-type','application/json');
        echo  Zend_Json::encode($res);

    }

    /**
     * sluzba pro editaci v pohledu odpovedi.
     * povinnym parametrem v requestu je id otazky pro editaci otazky, nebo id odpovedi pro editaci odpovedi.
     * @return json @see editQuestionAnswers()
     */
    public function editanswerService() {

        $frontController = Zend_Controller_Front::getInstance();

        $req = $frontController->getRequest();
        $db = Zend_Registry::get('db');

        $questionId = $req->getParam('question_id');
        $questionTitle = $req->getParam('text');
        //$questionType = $req->getParam('answer_type');
        $answerId = $req->getParam('answer_id');
        $answerTitle = $req->getParam('answer_title');


        if(empty($questionId)){
            Phc_ErrorLog::warning('Ibulletin_Content_Indetail::editanswerService()',
                'Pri ukladani textu k otazkam/odpovedim nebylo predano id otazky. Request objekt: '."\n".print_r($req->getParams(), TRUE));
            $res = FALSE;

        } else {
            try{
                //$data = $this->editQuestionAnswers($indetailStatsId);
                $questions = new Questions($this->id);
                $res = $questions->editQuestion($questionId, array('text' => $questionTitle));
                if (!empty($answerId)) {
                    $answer_saved = $questions->editAnswer($answerId, array('text' => $answerTitle));
                    $res = $res && $answer_saved;
                }

            }
            catch(Exception $e){
                Phc_ErrorLog::error('Ibulletin_Content_Indetail::getanswersService()',
                    "Nepodarilo se ziskat odpovedi k inDetailu pro answers_all_v.indetail_stats_id = ". $indetailStatsId . " Puvodni vyjimka : ". $e);
                $res = FALSE;
            }

        }

        if (!$res) {
            $frontController->getResponse()->setHttpResponseCode(500);

        } else {
            $frontController->getResponse()->setHttpResponseCode(200);
        }

    }

    /**
     * Autentizuje uzivatele podle jakehokoli zadaneho unikatniho atributu.
     * Pokud zjisti, ze atribut neni unikatni, zaloguje problem a autentizuje uzivatele s nejnizsim ID.
     *
     * Pokud je $attrVal prazdny, bude prihlasen rovnou novy uzivatel - nezkousime hledat uzivatele v DB.
     *
     * POZOR: Pokud specialni overovaci atribut v URL obsahuje /, musi byt 3x URL encodovany ve flashi.
     *
     * @param string   Nazev unikatniho atributu z users nebo users_attribs (nutne pridat prefix "ua.")
     * @param string   Hodnota atributu pro uzivatele, ktereho prihlasujeme
     * @return int     user_id, null pokud nebyl nalezen dany uzivatel
     */
    public function authentizeByAttr($attrName, $attrVal)
    {
        $db = Zend_Registry::get('db');

        if(empty($attrName)){
            return null;
        }

        $sel = new Zend_Db_Select($db);
        if(!empty($attrVal)){ // Pro prazdny atribut radeji nehledame
            // Pro atributy z users_attribs
            $m = array();
            if(preg_match('/^ua\.(.*)/i', $attrName, $m)){
                $attrNameShort = $m[1];
                $sel->from(array('u' => 'users'), array('id'))
                    ->join(array('ua' => 'users_attribs'), 'ua.user_id = u.id', array());
                $sel->where('ua.name =\''.$attrNameShort.'\' AND val = ?', $attrVal);
            }
            // Pro atributy z users
            else{
                $sel->from(array('u' => 'users'), array('id'));
                $sel->where($attrName.' = ?', $attrVal);
            }
            $sel->order(array('u.id'));

            // Ziskame ID uzivatele a overime, jestli neprislo vice uzivatelu
            $users = $db->fetchAll($sel);
        }
        else{
             $users = array();
        }

        //Phc_ErrorLog::error('debug', print_r($users, true));
        if(count($users) > 1){
            // Vice nez jeden uzivatel ma tento unikatni atribut, zalogujeme
            Phc_ErrorLog::warning('Ibulletin_Content_Indetail::authentizeByAttr()',
                'Pri overovani uzivatele pomoci specialniho atributu bylo nalezeno vice '.
                'uzivatelu se stejnym identifikatorem v atributu: "'.$attrName.'" '.
                'a hodnotou "'.$attrVal.'".');
        }
        elseif(empty($users)){
            // Uzivatel se zadanym atributem neexistuje
            return null;
        }

        $user_id = $users[0]['id'];

        // Prihlasime uzivatele
        Ibulletin_Auth::setUser($user_id);

        return $user_id;
    }

    /**
     * Uklada data odeslana z inDetailu (flashe/HTML) do DB (answers, indetail_stats)
     *
     * Pokud neni uzivatel prihlasen, pokusi se ho prihlasit pomoci jeho atributu pokud byl predan v URL
     * a je tato metoda povolena v configu.
     */
    public function saveService($req)
    {
        //obsluhuje pouze AJAXové requesty
        if (!$req->isXmlHttpRequest()) {
            echo "BAD REQUEST";
            return;
        }
      
        $config = Zend_Registry::get('config');
        $session = Zend_Registry::get('session');
        $db = Zend_Registry::get('db');

        $pageViewsId = $req->getParam('pageviewsid', null);

        // Pokud neni predano page_views_id, zkusime, jestli nemame overovat podle specialniho atributu uzivatele
        $specialAttrForAuth = $config->indetail->specialAttrForAuth;
        if(empty($pageViewsId) && !empty($specialAttrForAuth)){
            // Pokusime se ziskat udaje o prihlasenem uzivateli ze session
            $uniqueString = $req->getParam('unique', null); // Unique string predany flashem nebo prezentaci, musi byt v get/post datech
            if($uniqueString && $session->indetailAuthUniqueString
                && $session->indetailAuthPageViewsId && $uniqueString == $session->indetailAuthUniqueString)
            {
                $pageViewsId = $session->indetailAuthPageViewsId;
            }
            // Pokud neni v session ulozene page_views_id, musime zkusit uzivatele overit
            else{
                $m = array();
                if(preg_match('/\.(.*)/', $specialAttrForAuth, $m)){
                    $attrShort = $m[1];
                }
                else{
                    $attrShort = $specialAttrForAuth;
                }

                // Ziskame predany atribut, pokud existuje
                // POZOR! Pokud predavame hodnotu flashi a ten pak predava sem, musi byt puvodni hodnota
                // minimalne 3x urlencoded (pri predani do flashe), lepe nejspise 4x a je nutne otestovat.
                // Problemy delaji hodnoty / a +, pri 3x url encoded je v PHP prelozeno + na mezeru!
                $attrVal = $req->getParam($attrShort, null);

                if(!empty($attrVal) || $config->indetail->specialAttrForAuthNewUserForEmptyAttr){ // Pro prihlaseni noveho uzivatele pri prazdnem atributu podle povoleni v configu
                    //Phc_ErrorLog::error('debug', 'authentizeByAttr');
                    $user_id = $this->authentizeByAttr($specialAttrForAuth, $attrVal);

                    // Uzivatel nebyl nalezen, zalozime noveho, pokud je to povoleno
                    if(empty($user_id) && $config->indetail->specialAttrForAuthCreateUsers){
                        // Zalozime uzivatele
                        $user_id = Ibulletin_Auth::registerUser(array($attrShort => $attrVal), false, true, false);
                        // Prihlasime
                        Ibulletin_Auth::setUser($user_id);
                        // Ziskame page_views_id
                        $pageViewsId = Ibulletin_Stats::getInstance()->page_view_id;
                        Ibulletin_Stats::getInstance()->saveToSessions();
                    }

                    // Pokud byl uzivatel prihlasen, pripravime page_views_id a nastavime do
                    // daneho page_view zobrazeni page
                    if($user_id){
                        // Zjistime page_id pro nejnovejsi stranku s timto contentem
                        $sel = new Zend_Db_Select($db);
                        $sel->from(array('c' => 'content'), array())
                            ->join(array('cp' => 'content_pages'), 'cp.content_id = c.id', array('page_id'))
                            ->join(array('bp' => 'bulletins_pages'), 'bp.page_id = cp.page_id', array('bulletin_id'))
                            ->join(array('b' => 'bulletins_v'), 'b.id = bp.bulletin_id')
                            ->joinLeft(array('pc' => 'pages_categories'), 'pc.page_id = bp.page_id', array('category_id'))
                            ->where('content_id = ?', $this->id)
                            ->order(array('b.valid_from DESC', 'bp.order', 'cp.position', 'pc.significance'))
                            ->limit(1);
                        $row = $db->fetchRow($sel);

                        // Ziskame page_views_id
                        $pageViewsId = Ibulletin_Stats::getInstance()->page_view_id;
                        // Nastavime page_id do tohoto page_view kvuli statistikam
                        Ibulletin_Stats::getInstance()->setAttrib('page_id', $row['page_id']);
                        Ibulletin_Stats::getInstance()->setAttrib('bulletin_id', $row['bulletin_id']);
                        Ibulletin_Stats::getInstance()->setAttrib('category_id', $row['category_id']);
                        Ibulletin_Stats::getInstance()->saveToPageViews();

                        // Ulozime si page_views_id a unique string do session pro pristi pozadavky
                        $session->indetailAuthUniqueString = $uniqueString;
                        $session->indetailAuthPageViewsId = $pageViewsId;
                    }

                }
            }
        }

        if(empty($pageViewsId)){
            Phc_ErrorLog::warning('Ibulletin_Content_Indetail::saveService()',
                'Pri ukladani dat z inDetailu nebylo predano page_views_id. Data post:'."\n".print_r($_POST, TRUE));
        }

        //Phc_ErrorLog::error('indetailSave', $req->getParam('question_id').' '.$req->getParam('answer'));



        // Najdeme id uzivatele podle page_views_id prichoziho pozadavku
        $q = "SELECT s.user_id FROM page_views pv JOIN sessions s ON s.id=pv.session_id WHERE pv.id = ".(int)$pageViewsId;
        $user_id_db = $db->fetchOne($q);
        if(!empty($user_id_db)){
            $user_id = $user_id_db;
        }
        // TODO Po vyprseni session pri vypnute cookie auth jsou page_views zaznamy ukladany k session, ktera neni primo prirazena danemu uzivateli. Bylo by vhodne po vyprseni session v tomto pripade uzivatele nejak znovu prihlasit.

        // Ulozime indetail_stats
        try{
            $params = $req->getParams();
            $params['user_id'] = $user_id;
            $data = $this->saveIndetailStats($req, $pageViewsId, $params);
            $indetail_stats_id = $data['indetail_stats_id'];
            //Phc_ErrorLog::error('indetail_stats_id', $indetail_stats_id);
        }
        catch(Exception $e){
            Phc_ErrorLog::error('Ibulletin_Content_Indetail::saveService()',
                "Nepodarilo se ulozit statisticka data inDetailu. $e");
        }

       # Ukladani odpovedi na otazky
        $questionsObj = new Questions($this->id, $user_id);
        $boolValues = array('true' => true, 'false' => false);

        $question_orig = $req->getParam('question_id');
        $answer_orig = $req->getParam('answer');
        $slide_num = $req->getParam('slide_id');

        //$post = file_get_contents('php://input');
        //Phc_ErrorLog::warning('debug POST', $post);

        if(!empty($question_orig) && !empty($answer_orig)){
            // Rozparsujeme
            $questions = explode('|', $question_orig);
            $answers = explode('|', $answer_orig);
            // Kontrola spravneho poctu otazek a odpovedi
            if(count($questions) != count($answers)){
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::save()',
                    'Neshoduje se pocet identifikatoru otazek s poctem odpovedi z inDetail prezentace.'."\n".
                    "questions: '$question_orig'\n".
                    "answers: '$answer_orig'");
            }
            /* Nepotrebujeme, z HTML5 se data posilala jen jednou URL encoded, coz je spatne
            // Kontrola shodneho poctu questions a answers, pokud nesedi, zkusime pouzit raw data kvuli mozne odpovedi obsahujici |, ktere je v raw url encoded
            if(count($questions) != count($answers)){
                $answerRaw = Utils::getRawPost('answer');
                $answersRaw = explode('|', $answerRaw);
                if(count($questions) == count($answersRaw)){
                    // Url decode answers itself
                    $answers = array();
                    foreach($answersRaw as $key => $val){
                        $answers[urldecode($key)] = urldecode($val);
                    }
                    unset($answersRaw);
                }
            }
            */
            foreach($questions as $key => $question_num){
                $text = null;
                if(!empty($answers[$key])){
                    // Zkusime najit zaznam z questions kvuli urceni typu
                    $qType = null;
                    $questionRow = $questionsObj->getQuestion(null, $question_num, null, $slide_num);
                    if(!empty($questionRow)){
                        $qType = $questionRow['type'];
                    }


                    $answer = $answers[$key];
                    $matches = array();
                   ## Pokusime zjistit typ odpovedi
                    // Radio
                    if(!preg_match('/^#(.*)#$/', $answer) && is_numeric($answer) && empty($qType)){
                            $type = 'r';
                    }
                    // Checkbox
                    elseif(preg_match('/^#(.*)#$/', $answer, $matches)){
                        $type = 'c';
                        $answer = explode('#', $matches[1]);
                    }
                    // Text nebo Bool
                    elseif(is_string($answer) && !is_numeric($answer)){
                        // Jeste otestujeme, jestli nejde o true/false hodnoty
                        if(trim($answer) == 'true' || trim($answer) == 'false'){
                            $answer = trim($answer);
                            $bools = array('true' => true, 'false' => false);
                            $type = 'b';
                            $answer = $bools[$answer];
                            //$answerIsBool = true;
                        }
                        else{
                            $type = 't';
                            $text = urldecode($answer);
                            $answer = null;
                        }
                    }
                    // Integer nebo Double
                    elseif(is_numeric($answer) && !empty($qType)){
                        $type = $qType;
                        // Text muze byt take string obsahujici pouze cislo!
                        if($type == 't'){
                            $text = urldecode($answer);
                            $answer = null;
                        }
                    }
                    //Phc_ErrorLog::warning('qqq','text:'.$text.' q_num:'.$question_num);



                    // Zapiseme odpoved
                    if(!empty($type)){
                        try{
                            $users_answers_id = $questionsObj->writeAnswer($question_num, $answer,
                                $type, $text, $indetail_stats_id, $slide_num, null, null, $pageViewsId);
                        }
                        catch(Exception $e){
                            Phc_ErrorLog::error('Ibulletin_Content_Indetail::saveService',
                                "Nepodarilo se ulozit odpovedi odeslane z inDetailu - content_id: '$this->id', ".
                                "question_id: '$question_orig', answer: '$answer_orig'. Puvodni vyjimka: \n $e");
                        }
                    }



                    /*
                    // Pokud se jednalo o bool prvedeny na radio, jeste ulozime texty odpovedi do answers
                    if(!empty($answerIsBool)){

                        // Najdeme question ID
                        $sel = new Zend_Db_Select($db);
                        $sel->from('users_answers', 'question_id')
                            ->where('id = ?', $users_answers_id);
                        $question_id = $db->fetchOne($sel);

                        // Zjistime, jestli nejsou odpovedi jiz zapsane
                        $sel = new Zend_Db_Select($db);
                        $sel->from('answers', 'count(id)')
                            ->where('question_id = ?', $question_id)
                            ->where('text IS NOT NULL');
                        $count = $db->fetchOne($sel);

                        if(!empty($question_id)){
                            if($count < 2){
                                foreach($bools as $answerText => $answerNum){
                                    $questionsObj->editAnswerTitle($question_id, $answerNum, $answerText);
                                }
                            }
                        }
                        else{
                            Phc_ErrorLog::warning('Ibulletin_Content_Indetail::saveService()',
                                "Nepodarilo se najit question_id pro users_answers_id = '$users_answers_id', ".
                                "ktere bylo prave zapsano, proto nebyly doplneny texty pro odpoved bool.");
                        }
                    }
                    */
                }
            }
        }

        $userdata = json_decode($params['userdata'],true);    
    
        //Save user data
        if (!empty($userdata)) {
            
            try {
                
                //uzivatele pouze updatujeme, zamezime vytvareni novych
                $user = Users::getUser($user_id);
                
                //zamezime ulozeni id nebo emailu
                unset($userdata['id']);
                unset($userdata['email']);
               
                $metadata = $db->describeTable('users');
                
                $userCols = array_keys($metadata);
                
                
                //projdeme uzivatelska data pro ulozeni a zaznamy, ktere jsou soucasti tabulky users prefixujeme 
                foreach ($userdata as $k => $v) {
                   
                    if (in_array($k,$userCols)) {
                        $userdata['ua_'.$k] = $v;
                        unset($userdata[$k]);
                    }
                     
                }
                
                if (!Users::updateUser($user_id, $userdata)) {
                    Phc_ErrorLog::warning('Ibulletin_Content_Indetail::saveService()', 'Pri ukladani uzivatelesky dat z inDetailu doslo k chybe: ' . print_r($userdata, TRUE));
                }
                
            } catch (Exception $ex) {
                
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::saveService()', 'Pri ukladani uzivatelskych dat z inDetailu doslo k chybe, uzivatel s ID: '.$user_id.' neexistuje!');
        
            }

        }

        // Do odpovedi pridame PHPSSID pro komunikaci z HTML5 inDetailu provozovanych na cizich domenach
        // Vratime dataSet v JSON na vystup
        $frontController = Zend_Controller_Front::getInstance();
        $frontController->getResponse()->setHeader('Content-type','application/json');

        $data = array();
        $data['PHPSESSID'] = Zend_Session::getId();
        $json = json_encode($data);

        // Pokud se jedna o JSONP obalime data funkci a nastavime jiny mime
        if($req->getParam('callback')){
            $callback = $req->getParam('callback');
            $json = $callback.'('.$json.');';
            $frontController->getResponse()->setHeader('Content-type','text/javascript');
        }

        echo $json;
        return;
    }


    /**
     * Wrapper na metodu Ibulletin_Content_Video::statsService().
     * Uklada pozici videa a pripadne udalosti prehravace.
     *
     */
    public function videostatsService($req)
    {
        Ibulletin_Content_Video::statsService($req);
    }



    /**
     * Postara se o ulozeni statistickych infromaci inDetialu do tabulky indetail_stats.
     *
     * @param   Zend_Controller_Request_Abstract    Obejkt requestu.
     * @param   int Id z tabulky page_views_id - melo by se jednat o page_views_id, ktere vyvolalo nacteni flashe
     * @param   array   Parametry predane metodou GET a POST, mohou obsahovat user_id
     * @param   bool    Neukladat duplicitni zaznamy (neuplatnuje se pro uzivatele test)
     * @return  Array   indetail_stats_id - ID z tabulky indetail_stats do ktereho se data ulozila
     *                  slide_id - ID slidu, na kterem byla otazka; 
     *                  null pokud se jednalo o duplicitni zaznam pri zakazanem ukladani duplicit
     */
    public function saveIndetailStats(Zend_Controller_Request_Abstract $req, 
        $pageViewsId = null, $params = null, $skipDupe = false)
    {
        $db = Zend_Registry::get('db');

        if(!is_array($params)){
            $params = $req->getParams();
        }

        // Page views ID
        /* Nepouzivame, pro iPad nemame pv_id, ktere by melo smysl zde ukladat
        if(empty($pageViewsId)){
            $pageViewsId = Ibulletin_Stats::getInstance()->page_view_id;
        }
        */

        // User ID - pokud neprislo, ziskame podle page_views_id
        // (kvuli konzistenci, aktualne prihlaseny uzivatel nemusi byt ten kdo posila data)
        if(empty($params['user_id']) && $pageViewsId){
            $sel = new Zend_Db_Select($db);
            $sel->from(array('pv' => 'page_views'), array())
                ->join(array('s' => 'sessions'), 's.id = pv.session_id', array('user_id'))
                ->where('pv.id = '.(int)$pageViewsId);
            $userId = $db->fetchOne($sel);
        }
        elseif(!empty($params['user_id'])){
            $userId = $params['user_id'];
        }
        if(empty($userId)){
            $userId = null;
            Phc_ErrorLog::warning('Ibulletin_Content_Indetail::saveIndetailStats()',
                'User_id for indetail_stats was not found. The user_id was neither in $params array nor by searching DB according page_views_id. '.
                "page_views_id = '$pageViewsId', params:\n".print_r($params, true));
        }

        // Pripravime potrebna propojeni do slides
        $slides_refs_ids = array(); // sem ulozime nalezene nebo pripravene reference
        $slides_refs = array(
            'slideon' => @$params['slideon'],
            'slideoff' => @$params['slideoff'],
            'slide_num' => @$params['slide_id'],
        );
        $sel = new Zend_Db_Select($db);
        $sel->from('slides', 'id')
            ->where('content_id = ?', $this->id)
            ->where('slide_num = :slide_num');
        // Pro jednotlive atributy hledame propojeni
        foreach($slides_refs as $ref => $val){
            // Pokud neni slid zadan, nebudeme vytvaret slide 0
            if(!is_numeric($val)){
                $slides_refs_ids[$ref] = new Zend_Db_Expr('null');
                Phc_ErrorLog::error('Ibulletin_Content_Indetail::saveIndetailStats()',
                                "Data do indetail_stats neobsahuji cislo $ref: '$val'.".
                                // Podrobnosti vypiseme jen poprve, ne pro vsechny chybejici slidenums
                                (empty($badSlideNum) ? " Predane parametry: \n".
                                print_r($params, true).", \n jen \$_POST:\n".print_r($_POST, true).", method:".
                                $req->getMethod().", \$_SERVER:\n".print_r($_SERVER, true).". Vyjimka: \n".new Exception() : '')
                                );
                $badSlideNum = true;
                continue;
            }
            else{
                $val = (int)$val;
                $slides_refs[$ref] = $val;
            }


            $slides_refs_ids[$ref] = $db->fetchOne($sel, array('slide_num' => $val));
            if(empty($slides_refs_ids[$ref])){
                try{
                    $db->insert('slides', array('content_id' => (int)$this->id, 'slide_num' => $val));
                    $slides_refs_ids[$ref] = $db->lastInsertId('slides', 'id');
                }
                catch(Exception $e){
                    throw new Ibulletin_Content_Indetail_Exception("Nepodarilo se zapsat novy slide. ".
                        "content_id: '$content_id', slide_num: '$val'. Puvodni vyjimka: \n $e");
                }
            }
        }

        // Nasbirame do pole data, ktera chceme ulozit.
        $data = array(
            'user_id' => $userId,
            'content_id' => $this->id,
            'page_views_id' => $pageViewsId,
            'slide_id' => $slides_refs_ids['slide_num'],
            'slideon_id' => $slides_refs_ids['slideon'],
            'slideoff_id' => $slides_refs_ids['slideoff'],
            'answer_original' => @$params['answer'],
            'question_id_original' => @$params['question_id'],
            'time_total' => (int)@$params['time_total'],
            'time_slide' => (int)@$params['time_slide'],
            'sound_on' => @$params['sound_on'] !== null ? $params['sound_on'] :  'undefined',
            'sound' => @$params['sound'] !== null ? $params['sound'] :  'undefined',
            'points' => (int)@$params['points'],
            'step' => (int)@$params['steps'],
            'slideon' => (int)@$params['slideon'],
            'slideoff' => (int)@$params['slideoff'],
            'slide_group_id' => (int)@$params['slide_group_id'],
            'sequence' => (int)@$params['sequence'],
            'slide_num' => (int)@$params['slide_id'],
            'unique_str' => @$params['unique'],
            );
        // Timestamp jen kdyz je zadana
        if(!empty($params['timestamp'])){
            $data['timestamp'] = $params['timestamp'];
        }

        // Nektere parametry jeste normalizujeme
        $boolValues = array('' => null, 'undefined' => null, '0' => false, '1' => true, 'true' => true, 'false' => false);
        // Sound_on
        if(array_key_exists(trim($data['sound_on']), $boolValues)){
            $data['sound_on'] = $boolValues[trim($data['sound_on'])];
        }
        else{
            $data['sound_on'] = null;
            Phc_ErrorLog::error('Ibulletin_Content_Indirector::saveIndetailStats()',
                "Nepodarilo se rozeznat vyznam hodnoty parametru sound_on = '".(@$params['sound_on'] !== null ? $params['sound_on'] :  'undefined')."'.");
        }
        // Sound
        if(array_key_exists(trim($data['sound']), $boolValues)){
            $data['sound'] = $boolValues[trim($data['sound'])];
        }
        else{
            Phc_ErrorLog::error('Ibulletin_Content_Indirector::saveIndetailStats()',$data['sound']." ->".join('|', array_keys($boolValues)));
            $data['sound'] = null;
            Phc_ErrorLog::error('Ibulletin_Content_Indirector::saveIndetailStats()',
                "Nepodarilo se rozeznat vyznam hodnoty parametru sound = '".(@$params['sound'] !== null ? $params['sound'] :  'undefined')."'.");
        }
        
        //Kontrola spravnosti time_slide a time_total
        if ($data['sequence'] > 1 || $data['slide_id'] != $data['slideon_id']) { // Nejedna se o prvni prechod
            if ($data['time_total'] == 0) {
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::saveIndetailStats()', 
                        "Time total is 0 on nonfirst slide: \n".print_r($data, true));
            }
            if ($data['time_slide'] == 0) {
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::saveIndetailStats()', 
                        "Time slide is 0 on nonfirst slide: \n".print_r($data, true));
            }
        }
        
        
        // Kontrola duplicity ukladaneho zaznamu
        if($skipDupe){
            $dupeSel = new Zend_Db_Select($db);
            $dupeSel->from('indetail_stats', array(new Zend_Db_Expr('1')));
            $dupeSel->where(new Zend_Db_Expr('(SELECT true FROM users WHERE NOT test AND id = '.(int)$userId.')'));
            foreach($data as $key=>$val){
                if($val && $key != 'page_views_id'){
                    $dupeSel->where('"'.$key.'"'.'=?', $val);
                }
            }

            if($db->fetchOne($dupeSel)){
                // Logovani pro ucely odladeni
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::saveIndetailStats()', 
                        'Skipped duplicit line from indetail_stats: '.
                        json_encode($data)."\n SQL: ".$dupeSel);
                return null;
            }
        }

        try{
            $db->insert('indetail_stats', $data);
            $indetail_stats_id = $db->lastInsertId('indetail_stats', 'id');
        }
        catch(Exception $e){
                throw new Ibulletin_Content_Indetail_Exception("Nepodarilo se zapsat zaznam do indetail_stats. Data:".
                    print_r($data, true)."\nPuvodni vyjimka: \n $e");
            }

        return array('indetail_stats_id' => $indetail_stats_id,
                     'slide_id' => $slides_refs_ids['slide_num'],
                );
    }

    /**
     * Ziska statisticke infromace k inDetailu z tabulky indetail_stats podle klice page_views_id
     *
     * Pokud neni predano page_views_id, zkusi se pouzit cas z PHP session:
     * $session->indetail_edit_last_opened_{$contentId}. (pouzivame pro inRep)
     * Pozor, nekontroluje korektnost ISO timestamp ze session.
     *
     * @param int $pageViewsId klic pro omezeni vysledku
     * @param int $page cislo stranky
     * @param int $limit pocet radku vysledku
     * @param int $sortBy nazev sloupce pro razeni
     * @param string $sortOrder typ razeni
     * @return array klice 'page', 'pages_total', 'count', 'data'
     */
    public function getIndetailStats($pageViewsId, $page = 1, $limit = NULL, $sortBy = 'id', $sortOrder = 'DESC') {

        $db = Zend_Registry::get('db');

        // Neni-li zadano page_views_id, zkusime pouzit cas z PHP session
        if(!$pageViewsId){
            $session = Zend_Registry::get('session');
            if(isset($session->indetail_edit_last_opened_{$this->id})){
                $ts = $session->indetail_edit_last_opened_{$this->id};
            }
            else{ // Nemame cas, zapiseme chybu do logu a pouzijeme vymyslenou timestamp
                $args = func_get_args();
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::getIndetailStats()',
                    'Neither page_views_id nor timestamp in PHP session was passed to view the stats.'.
                    "Arguments passed to method: ".print_r($args, true));

                // Cas pred 10ti minutami
                $oldDate = new Zend_Date;
                $oldDate->subMinute(10);
                $ts = $oldDate->getIso();
            }
        }

        $sel = new Zend_Db_Select($db);
        $sel->from('indetail_stats', 'COUNT(*) AS num');
        if($pageViewsId){
            $sel->where('page_views_id = ?', (int)$pageViewsId);
        }
        else{
            $sel->where('timestamp > ?', $ts)
                ->where('content_id = ?', (int)$this->id);
        }

        $count = $db->fetchOne($sel);

        $sortOrder = strtoupper($sortOrder) == 'DESC' ? 'DESC' : 'ASC';

        $sel = new Zend_Db_Select($db);
        $sel->from('indetail_stats', array('id', 'slide_num', 'question_id_original', 'answer_original',
                new Zend_Db_Expr('extract(epoch from timestamp) AS timestamp'), 'time_total', 'time_slide',
                'sound_on', 'sound', 'points', 'slideon', 'slideoff', 'step'))
            ->order(array("$sortBy $sortOrder"));
            if($pageViewsId){
                $sel->where('page_views_id = ?', (int)$pageViewsId);
            }
            else{
                $sel->where('timestamp > ?', $ts)
                    ->where('content_id = ?', (int)$this->id);
            }

        if ($limit) {
            $total_pages =  $count > 0 ? ceil($count/$limit) : 0;
            if ($page > $total_pages) $page=$total_pages;
            $offset = $limit*$page - $limit;
            if ($offset<0) $offset = 0;
            $sel->limit($limit, $offset);
        } else {
            $page = $total_pages = ($count > 0 ? 1 : 0);
        }

        //Phc_ErrorLog::warning('debug', $sel);

        $data = $db->fetchAll($sel);

        return array('page' => $page, 'pages_total' => $total_pages, 'count' => $count, 'data' => $data);

    }

    /**
     * Ziska data z pohledu users_answers_all_v podle klice indetail_stats_id
     *
     * @param int $indetailStatsId klic pro omezeni vysledku
     * @param int $page cislo stranky
     * @param int $limit pocet radku vysledku
     * @param int $sortBy nazev sloupce pro razeni
     * @param string $sortOrder typ razeni
     * @return array klice 'page', 'pages_total', 'count', 'data'
     */
    public function getIndetailAnswers($indetailStatsId, $page = 1, $limit = NULL, $sortBy = 'indetail_stats_id', $sortOrder = 'DESC') {

        $db = Zend_Registry::get('db');

        $sel = new Zend_Db_Select($db);
        $sel->from('answers_all_v', 'COUNT(*) AS num')
            ->where('indetail_stats_id = ?', (int)$indetailStatsId);
        $count = $db->fetchOne($sel);

        $sortOrder = strtoupper($sortOrder) == 'DESC' ? 'DESC' : 'ASC';

        $sel = new Zend_Db_Select($db);
        $sel->from('answers_all_v')
            ->where('indetail_stats_id = ?', (int)$indetailStatsId)
            ->order(array("$sortBy $sortOrder"));

        if ($limit) {
            $total_pages =  $count > 0 ? ceil($count/$limit) : 0;
            if ($page > $total_pages) $page=$total_pages;
            $offset = $limit*$page - $limit;
            if ($offset<0) $offset = 0;
            $sel->limit($limit, $offset);
        } else {
            $page = $total_pages = ($count > 0 ? 1 : 0);
        }
        $data = $db->fetchAll($sel);

        return array('page' => $page, 'pages_total' => $total_pages, 'count' => $count, 'data' => $data);

    }

    /**
     * Ziska statisticke infromace k inDetailu z tabulky indetail_stats podle klice page_views_id
     *
     * Pokud neni predano page_views_id, zkusi se pouzit cas z PHP session:
     * $session->indetail_edit_last_opened_{$contentId}. (pouzivame pro inRep)
     * Pozor, nekontroluje korektnost ISO timestamp
     *
     * @param int $pageViewsId klic pro omezeni vysledku
     * @param int $page cislo stranky
     * @param int $limit pocet radku vysledku
     * @param int $sortBy nazev sloupce pro razeni
     * @param string $sortOrder typ razeni
     * @return array klice 'page', 'pages_total', 'count', 'data'
     */
    public function getIndetailPlayerStats($contentId, $userId, $pageViewsId,  $page = 1, $limit = NULL, $sortBy = 'id', $sortOrder = 'DESC') {

        $db = Zend_Registry::get('db');

        // Ziskame timestamp omezujici zobrazovana data odspodu
        if($pageViewsId){
            $sel = new Zend_Db_Select($db);
            $sel->from('indetail_stats', new Zend_Db_Expr('MIN(timestamp)'))
                ->where('content_id = ?', (int)$contentId)
                ->where('user_id = ?', (int)$userId)
                ->where('page_views_id = ?', (int)$pageViewsId);

            $ts = $db->fetchOne($sel);
            if (!$ts) {
                return array('page' => 0, 'pages_total' => 0, 'count' => 0, 'data' => array());
            }
        }
        else{ // Neni page_views_id, zkusime pouzit cas z PHP session
            $session = Zend_Registry::get('session');
            if(isset($session->indetail_edit_last_opened_{$contentId})){
                $ts = $session->indetail_edit_last_opened_{$contentId};
            }
            else{ // Nemame cas, zapiseme chybu do logu a pouzijeme vymyslenou timestamp
                $args = func_get_args();
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::getIndetailPlayerStats()',
                    'Neither page_views_id nor timestamp in PHP session was passed to view the stats.'.
                    "Arguments passed to method: ".print_r($args, true));

                // Cas pred 10ti minutami
                $oldDate = new Zend_Date;
                $oldDate->subMinute(10);
                $ts = $oldDate->getIso();
            }
        }


        // Pocet zaznamu kvuli strankovani
        $sel = new Zend_Db_Select($db);
        $sel->from('players_stats', 'COUNT(*) AS num')
            ->where('content_id = ?', (int)$contentId)
            ->where('user_id = ?', (int)$userId)
            ->where('timestamp >  ?', $ts);
        $count = $db->fetchOne($sel);

        $sortOrder = strtoupper($sortOrder) == 'DESC' ? 'DESC' : 'ASC';

        // Nacteni samotnych dat videa
        $sel = new Zend_Db_Select($db);
        $sel->from('players_stats', array('id', 'user_id',
                new Zend_Db_Expr('extract(epoch from timestamp) AS timestamp'),
                'action', 'position', 'content_video_num'))
            ->where('content_id = ?', (int)$contentId)
            ->where('user_id = ?', (int)$userId)
            ->where('timestamp >  ?', $ts)
            ->order(array("$sortBy $sortOrder"));
        //Phc_ErrorLog::info('dbg', $sel);

        if ($limit) {
            $total_pages =  $count > 0 ? ceil($count/$limit) : 0;
            if ($page > $total_pages) $page=$total_pages;
            $offset = $limit*$page - $limit;
            if ($offset<0) $offset = 0;
            $sel->limit($limit, $offset);
        } else {
            $page = $total_pages = ($count > 0 ? 1 : 0);
        }

        $data = $db->fetchAll($sel);

        return array('page' => $page, 'pages_total' => $total_pages, 'count' => $count, 'data' => $data);

    }

    /**
     * Ziska pocet bodu uzivatele v tomto indetailu (aktualni rezim je
     * ziskani nejvyssiho poctu bodu za vsechny relace).
     *
     * @param Int $userId   ID uzivatele
     * @return int
     *
     * @throws Ibulletin_Content_Indetail_Exception Code 8 - Pokud nebylo zadano ID uzivatele
     */
    public function getUsersPoints($userId)
    {
        // Pokud neni zadan uzivatel, vyhodime vyjimku
        if(empty($userId)){
            throw new Ibulletin_Content_Indetail_Exception('Nebylo zadano ID uzivatele. Neni mozne ziskat uzivatelovi body.', 8);
        }

        $db = Zend_Registry::get('db');

        $sel = new Zend_Db_Select($db);
        $sel->from('indetail_stats', array('max(points)'))
            ->where('content_id = ?', $this->id)
            ->where('user_id = ?', $userId);

        $points = (int)$db->fetchOne($sel);

        return $points;
    }

    /**
     * Ziska soucet nejvyssich poctu bodu uzivatele za kazdy indetail pres
     * vsechny relace.
     *
     * @param Int $userId   ID uzivatele
     * @return int
     */
    public function getUsersSumPoints($userId)
    {
        $db = Zend_Registry::get('db');

        $subselect = new Zend_Db_Select($db);
        $subselect->from(array('ind' => 'indetail_stats'),
                        array('max' => new Zend_Db_Expr('max(points)')))
                ->where('user_id = ?', $userId)
                ->group('content_id');

        $sel = new Zend_Db_Select($db);
        $sel->from(array('d' => new Zend_Db_Expr('('.$subselect.')')), array('sum(max)'));

        $sum = (int)$db->fetchOne($sel);

        return $sum;
    }
    
   /**
     * Ziska prumerny pocet bodu, kteri ziskali uzivatele v tomto indetailu 
     * @return float
     */
    public function getAvgPoints()
    {
       
        $db = Zend_Registry::get('db');

        $subselect = new Zend_Db_Select($db);
        $subselect->from(array('u'=>'users'),'id')
               ->join(array('ipvf'=>'indetail_page_views_finished_v'),'u.id=ipvf.user_id',array('content_id'))
               ->join(array('isv'=>'indetail_slideon_v'),'u.id=isv.user_id and ipvf.content_id=isv.content_id',array('max_points'=>'max(isv.max_points)'))
               ->where('ipvf.content_id = ?',$this->id)
               ->group(array('u.id','ipvf.content_id'));
        
        
         $select = new Zend_Db_Select($db);
         
         $select->from($subselect,array('prumer'=>'avg(max_points)'))
                 ->group('content_id');
        
        $avg = round($db->fetchOne($select),1);
        
        return $avg;
    }
    
      
   /**
     * Ziska pocet uzivatelu, kteri dokoncili prezentaci
     * @return int
     */
    public function getCountFinishedUsers()
    {
       
        $db = Zend_Registry::get('db');

        $subselect = new Zend_Db_Select($db);
        $subselect->from(array('u'=>'users'),'id')
               ->join(array('ipvf'=>'indetail_page_views_finished_v'),'u.id=ipvf.user_id',null)
               ->join(array('isv'=>'indetail_slideon_v'),'u.id=isv.user_id and ipvf.content_id=isv.content_id',null )
               ->where('ipvf.content_id = ?',$this->id)
               ->group(array('u.id'));
        
        
        $select = new Zend_Db_Select($db);
         
        $select->from($subselect,array('pocet'=>'count(id)'));
        
        $pocet = $db->fetchOne($select);
        
        return $pocet;
    }


    /**
     * Vraci data uzivatele pro HTML5 stejna jako xmldataService pro inDetail.
     * Vystupem je JS kod, ktery se vlozi do HTML tak, aby definovana promenna
     * byla v globalnim scope.
     *
     * POZOR! Nemusi byt zcela bezpecne, pro vytvoreni JS pouziva JSON json_encode().
     *
     * @return  JS kod (bez SCRIPT tagu) pro vlozeni do HTML (ve view) tak,
     *          aby definovane promenne byly v global scope.
     */
    public function getJsUsersData()
    {
        $config = Zend_Registry::get('config');

        // Ziskame pole dat tohoto uzivatele
        $userData = $this->xmldataGetAttribs();

        // Posledni odpovedi na otazky, pokud josou pozadovany
        if($config->indetail->sendLastAnswers){
            $userId = Ibulletin_Auth::getActualUserId();
            $answersStruct = $this->xmldataGetAnswers($userId);

            // Vycistime data odpovedi od nepotrebnych informaci
            // (shromazdime multi odpovedi do poli a spojime podle typu odpovedi)
            foreach($answersStruct as $slide => $questions){
                foreach($questions as $question => $answers){
                    $answerAgg = null;
                    foreach($answers as $answerA){
                        // Vybereme odpoved podle typu a ulozime ji do promenne
                        if($answerA['type'] == 't'){
                            $answer = $answerA['text'];
                        }
                        elseif($answerA['type'] == 'i'){
                            $answer = $answerA['answer_int'];
                        }
                        elseif($answerA['type'] == 'd'){
                            $answer = $answerA['answer_double'];
                        }
                        elseif($answerA['type'] == 'b'){
                            $answer = $answerA['answer_bool'] ? 1 : 0;
                        }
                        elseif($answerA['type'] == 'r'){
                            $answer = $answerA['answer_num'];
                        }
                        elseif($answerA['type'] == 'c'){
                            $answer = $answerA['answer_num'];
                        }

                        // Ulozime vice odpovedi do pole, jesnu odpoved primo
                        if($answerAgg !== null && !is_array($answerAgg)){
                            $answerAgg = array($answerAgg);
                        }
                        if(!is_array($answerAgg)){
                            $answerAgg = $answer;
                        }
                        else{
                            $answerAgg[] = $answer;
                        }
                    }
                    // Zapiseme zpet do puvodniho pole
                    $answersStruct[$slide][$question] = $answerAgg;
                }
            }
        }

        // Vytvorime pole, ktere pozdeji pomoci JSON funkci (ne zcela bezpecne) prevedeme na JS
        $data = array('user' => $userData);
        
        //ziskame datum predavany do prezentace
        $date = Zend_Controller_Front::getInstance()->getRequest()->getParam('date');
       
        //do server vars ulozime datum z request pouze testovaci uzivatelu, jinak nastavime aktualni datum
        if(isset($userData['test']) && $userData['test'] && !empty($date)) {
            $data['date'] = $date;
        } else {
            $data['date'] = date('Y-m-d');
        }

        if(isset($answersStruct)){ // Pridame answers pokud je to pozadovano
            $data['answers'] = $answersStruct;
        }

        $js = json_encode($data);

        return $js;
    }
    
     /**
     * Vraci statisticka data indetail prezentace pro pouziti v prezentaci
     * 
     * @return  JSON s daty
     */
    public function getStatsData()
    {

        $data = array();
        
        $data['avgPoints'] = $this->getAvgPoints();
        $data['countFinishedUsers'] = $this->getCountFinishedUsers();
        return  json_encode($data);

    }


    /**
     * @return string   Vrati cestu k souborum tohoto contentu.
     */
    public function getBasepath()
    {
        if(empty($this->_pubPath)){
            $config = Zend_Registry::get('config');
            $this->_pubPath = $config->content_article->basepath;
        }

        return $this->_pubPath.'/'.$this->id.'/';
    }


    /**
     * @return string   Vrati cestu k souborum tohoto contentu.
     */
    public function getBaseUrl()
    {

        if(empty($this->_baseUrl)){
            $config = Zend_Registry::get('config');
            $urlHlpr = new Zend_View_Helper_Url();
            $this->_baseUrl = $urlHlpr->url(array(),'default',true).$config->content_article->basepath;
        }

        return $this->_baseUrl.$this->id.'/';
    }




    /**
     * Zjisti, ktera verze ma byt zobrazena (html/flash) s ohledem na nastaveni,
     * dostupne verze a pripadne typ zarizeni.
     *
     * @return array    string 'flash'/'html'/null - ktera verze ma byt vyrenderovana
     *                  (null, pokud neni k dispozici zadna prezentace)
     *                  bool    Mel byt radeji pouzit flash misto html, pokud v prohlizeci je
     *                  (vraci true pouze pokud je rozhodnuto pro HTML verzi - pouziva se pro pripady,
     *                  kdy jeste neprobehla detekce z prohlizece, tudiz nevime, jestli je v prohlizeci
     *                  flash, potom se rozhodnuti provede az v prohlizeci)?
     */
    public function determineVersionToUse()
    {
        $config = Zend_Registry::get('config');

        // Pokud je nastaveno testovani, vracime automaticky nastavenou verzi
        if(!empty($this->testingVersion)){
            return array($this->testingVersion, false);
        }

        $preferredVersion = $config->indetail->preferredVersion;
        $forceVersion = $config->indetail->forceVersion;
        $preferredMobileVersion = $config->indetail->preferredMobileVersion;

        // Zjistime dostupnost flashe v prohlizeci
        $flashAvailable = Ibulletin_Stats::getInstance()->getFlashplayerVersion();
        // Zjistime, jestli se jedna o mobilni zarizeni
        $isMobile = Ibulletin_Stats::getInstance()->detectMobileBrowser();
        //$isMobile = true;

        // PC version
        if(!$isMobile){
            // Force
            if(!empty($forceVersion)){
                if(strtolower($forceVersion) == 'flash' && $this->hasFlash){
                    return array('flash', false);
                }
                elseif(strtolower($forceVersion) == 'html' && $this->hasHtml){
                    return array('html', false);
                }
                else{
                    Phc_ErrorLog::warning('Ibulletin_Content_Indetail::determineVersionToUse()',
                        'Neznama $config->indetail->forceVersion = "'.$config->indetail->forceVersion.'".');
                }
            }

            // FLASH preferred
            if(strtolower($preferredVersion) == 'flash'){
                //var_dump($flashAvailable);
                if($this->hasFlash && $flashAvailable){
                    return array('flash', false);
                }
                elseif($this->hasHtml){
                    if($this->hasFlash){
                        return array('html', true);
                    }
                    else{
                        return array('html', false);
                    }
                }
                elseif($this->hasFlash){
                    return array('flash', false);
                }
            }
            // HTML preferred
            elseif(strtolower($preferredVersion) == 'html'){
                if($this->hasHtml){
                    return array('html', false);
                }
                elseif($this->hasFlash){
                    return array('flash', false);
                }
            }
        }
        // Mobile version
        else{
            // Pokud neni zadana preferovana mobilni verze, nastavime stejne jako preferred version
            if(empty($preferredMobileVersion)){
                $preferredMobileVersion = $preferredVersion;
            }

            // FLASH preferred
            if(strtolower($preferredMobileVersion) == 'flash'){
                if($this->hasFlash && $flashAvailable){
                    return array('flash', false);
                }
                elseif($this->hasHtml){
                    if($this->hasFlash){
                        return array('html', true);
                    }
                    else{
                        return array('html', false);
                    }
                }
                elseif($this->hasFlash){
                    return array('flash', false);
                }
            }
            // HTML preferred
            elseif(strtolower($preferredMobileVersion) == 'html'){
                if($this->hasHtml){
                    return array('html', false);
                }
                elseif($this->hasFlash){
                    return array('flash', false);
                }
            }
        }

        return null;
    }

    /**
     * Get question's statistic data.
     *
     * Required params in URL:
     * slide - slide number
     * question - question number (on slide)
     */
     public function questionstatsService($req)
    {
        $slideNum = $req->getParam('slide');
        $questionNum = $req->getParam('question');

        $questions = new Questions($this->id, null);

        try{
             //mame-li oba parametry vracime jednu otazku
            if ($slideNum && $questionNum) {
                $data = $questions->getQuestionStats($slideNum, $questionNum);
                // Vycistime data od ID z DB
                if (!empty($data)) {
                    unset($data['content_id']);
                    unset($data['question_id']);
                    foreach ($data['answers'] as $key => $val) {
                        unset($val['answer_id']);
                        $data['answers'][$key] = $val;
                    }
                }

            } else {
                $data = $questions->getContentStats($slideNum);
            }

            // HACK
            // Data zabalime jeste do jednoho pole, aby pozdejio slo metody rozsirit o moznost posilani dat vice otazek najednou
            $data = array($data);
        }
        catch(Exception $e){
            Phc_ErrorLog::notice('Ibulletin_Content_Indetail::questionstatsService()',
                $e);
            $data = array();
        }

        // Vratime dataSet v JSON na vystup
        $frontController = Zend_Controller_Front::getInstance();
        $frontController->getResponse()->setHeader('Content-type','application/json');
        $json = json_encode($data);
        echo $json;
    }



    /**
     * Vrati XML data potrebna v inDetailu.
     *
     *
     */
    public function xmldataService($req)
    {
        $config = Zend_Registry::get('config');

        // Nastavime content type
        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();
        $response->setHeader('Content-Type', 'text/xml', true);

        // Nastavime pageId a bulletinId
        $this->setPageId($req->getParam('page_id'));
        $this->bulletinId = $req->getParam('bulletin_id');

        // Data uzivatele
        $userData = $this->xmldataGetAttribs();

        // Data uzivatele vlozime do objektu atributu
        $client_attr = new Ibulletin_XmlElementAttributes($userData);


        // Zkonstruujeme objekt XML
        $name = (!empty($userData['name']) ? trim($userData['name']).' ' : '').(!empty($userData['surname']) ? $userData['surname'] : '');
        $data = new Ibulletin_XmlClass();
        $data->__rootName = 'config';
        $data->client = $name;
        $data->__client_attr = $client_attr;

        // Pridame data poslednich odpovedi na otazky, pokud existuji
        if($config->indetail->sendLastAnswers){
            $answers = $this->xmldataGetAnswers(Ibulletin_Auth::getActualUserId());
            $answersTopElem = $this->xmldataGetAnswersDom($answers);
            $data->answers = $answersTopElem;
        }


        // Vypiseme data
        echo $data->getXml();
    }


    /**
     * Vraci pole obsahujici slide, otazku a posledni odpoved uzivatele podle DB.
     *
     * @param Int     ID uzivatele
     * @return array  Posledni odpovedi na otazky
     */
    public function xmldataGetAnswers($userId)
    {
        $db = Zend_Registry::get('db');

        // Pokud neni zadan uzivatel vracime prazdne pole
        if(!$userId){
            return array();
        }

        //$userId = 14;

        // Ziskame data odpovedi a seradime
        $sel = new Zend_Db_Select($db);
        $sel->from(array('ua' => 'users_answers'), array('*'))
            ->joinLeft(array('uac' => 'users_answers_choices'), 'ua.id = uac.users_answers_id', array())
            ->joinLeft(array('a' => 'answers'), 'a.id = ua.answer_id OR a.id = uac.answer_id', array('answer_num'))
            ->join(array('q' => 'questions'), 'q.id = ua.question_id', array('question_num'))
            ->join(array('s' => 'slides'), 's.id = q.slide_id', array('slide_num'))
            ->where('s.content_id = ?', $this->id)
            ->where('ua.user_id = ?', $userId)
            ->order(array('slide_num', 'question_num', 'answer_num'));
        $rows = $db->fetchAll($sel);

        // Rozhazime data do stromu podle slide_num, question_num a answer_num
        $answersA = array();
        $lSlide = null;
        $lQuestion = null;
        $lAnswer = null;
        foreach($rows as $row){
            $slide = $row['slide_num'];
            $question = $row['question_num'];
            if($lSlide != $slide){
                $answersA[$slide] = array();
                $lQuestion = null;
            }
            if($lQuestion != $question){
                $answersA[$slide][$question] = array();
            }

            // Cely radek ulozime do struktury
            $answersA[$slide][$question][$row['answer_num']] = $row;

            $lSlide = $row['slide_num'];
            $lQuestion = $row['question_num'];
            $lAnswer = $row['answer_num'];
        }

        //var_dump($answersA);
        return $answersA;
    }


    /**
     * Vytvori Ibulletin_XmlClass pro strom odpovedi ve slidech.
     *
     * @param array     Pole obsahujici slidy, otazky a odpovedi z $this->xmldataGetAnswers
     * @param Ibulletin_XmlClass    XML vhodne pro predavani s daty uzivatele do flashe
     */
    public function xmldataGetAnswersDom($answers)
    {
        $answersTopElem = new Ibulletin_XmlClass();
        $answersTopElem->__rootName = 'answers';        // Musime zadat root nazev, aby byla data vlozena v elementu ne primo
        foreach($answers as $slide => $questions){
            // Zadame atributy elementu - cislo slidu
            $answersTopElem->{'__slide_'.$slide.'_attr'} = new Ibulletin_XmlElementAttributes(array('num' => $slide));
            // Jednotlive otazky
            $questionsElem = new Ibulletin_XmlClass();
            foreach($questions as $question => $answer){
                // Odpovedi na otazky
                $answersA = array();
                $answerElems = new Ibulletin_XmlClass();
                $count = 0;
                foreach($answer as $a){
                    if($a['type'] == 't'){
                        $answerInText = $a['text'];
                    }
                    elseif($a['type'] == 'i'){
                        $answerInText = $a['answer_int'];
                    }
                    elseif($a['type'] == 'd'){
                        $answerInText = $a['answer_double'];
                    }
                    elseif($a['type'] == 'b'){
                        $answerInText = $a['answer_bool'] ? 1 : 0;
                    }
                    elseif($a['type'] == 'r'){
                        $answerInText = $a['answer_num'];
                    }
                    elseif($a['type'] == 'c'){
                        $answerInText = $a['answer_num'];
                    }

                    $answerElems->{'__answer_'.$count} = $answerInText;
                    $count++;
                }
                $answerElems->__rootName = 'question_'.$question;
                $questionsElem->{'question_'.$question} = $answerElems;
                // Do atributu davame navic cislo otazky na slidu
                $questionsElem->{'__question_'.$question.'_attr'} =
                    new Ibulletin_XmlElementAttributes(array('num' => $question));
            }
            $questionsElem->__rootName = 'slide_'.$slide;
            $answersTopElem->{'slide_'.$slide} = $questionsElem;
        }

        return $answersTopElem;
    }


    /**
     * Vrati atributy uzivatele (i vystupy specialnich funkci) podle
     * $config->indetail->usersAttribsToSend
     *
     * Pro kazdy zadany atribut se nejprve zkousi volat externi metoda z
     * Ibulletin_Content_Indetail_InstallSpecific::xmldataGetAttr_[jmeno_atributu], potom se zkousi volat interni metoda
     * Ibulletin_Content_Indetail::xmldataGetAttr_[jmeno_atributu] a nakonec se zkousi data z users a users_attribs.
     * Pokud se nepodarilo zadnym zpusobem najit pozadovana data, predpoklada se, ze jsou z users_attribs a
     * jen nejsou vyplnena (neni hlasena zadna chyba).
     *
     * Tridu Ibulletin_Content_Indetail_InstallSpecific v pripade jeji eistence instancuje a
     * nastavi ji atribut id na ID aktualne zpracovaneho contentu (dale pak pageId a bulletinId).
     * Nasledne jsou volany metody xmldataGetAttr_[jmeno_atributu]($userData, $dataToSend),
     * kterym je predano pole s atributy uzivatele ziskanymi z Ibulletin_Auth::getActualUserData() a
     * pole jiz vytvorenych dat k predani do XML (vsechny atributy predchazejici aktualnimu atributu).
     *
     * Provadi normalizaci nazvu atributu (lowercase, underscore instead of spaces, remove dots).
     *
     * @return array    Atributy uzivatele podle $config->indetail->usersAttribsToSend
     */
    public function xmldataGetAttribs()
    {
        $config = Zend_Registry::get('config');
        $attribs = (array)$config->indetail->getCommaSeparated('usersAttribsToSend');
        // Pridame na zacatek pole ID
        array_unshift($attribs, 'id');

        $userData = Ibulletin_Auth::getActualUserData();
        if(empty($userData)){
            $userData = array();
        }

        // Pokusime se nacist tridu s installation specific metodami Ibulletin_Content_Indetail_InstallSpecific
        try{
            @Zend_Loader::loadClass('Ibulletin_Content_Indetail_InstallSpecific');
            $installSpecific = new Ibulletin_Content_Indetail_InstallSpecific();
            $installSpecific->id = $this->id;
            $installSpecific->pageId = $this->pageId;
            $installSpecific->bulletinId = $this->bulletinId;
        }
        catch(Zend_Exception $e){
            $installSpecific = null;
        }


        $dataToSend = array(); // Vysledna data k odeslani

        // Postupne zkousime jestli existuji metody pro ziskani tohoto atributu
        foreach($attribs as $attr){
            $methodName = 'xmldataGetAttr_'.$attr;
            if($installSpecific && method_exists($installSpecific, $methodName)){
                try{
                    $dataToSend[$attr] = $installSpecific->{$methodName}($userData, $dataToSend);
                }
                catch(Exception $e){
                    // Chyby zalogujeme
                    Phc_ErrorLog::warning('Ibulletin_Content_Indetail::xmldataGetAttribs()',
                        'Nepodarilo se ziskat nektera data pro XML predavane do inDetail prezentace'.
                        'ziskavana z Ibulletin_Content_Indetail_InstallSpecific. Atribut:"'.$attr.'".'.
                        "Puvodni vyjimka: $e");

                    $dataToSend[$attr] = null;
                }
            }
            elseif(method_exists($this, $methodName)){
                try{
                    $dataToSend[$attr] = $this->{$methodName}($userData, $dataToSend);
                }
                catch(Exception $e){
                    // Chyby zalogujeme
                    Phc_ErrorLog::warning('Ibulletin_Content_Indetail::xmldataGetAttribs()',
                        'Nepodarilo se ziskat nektera data pro XML predavane do inDetail prezentace.'.
                        'Atribut:"'.$attr.'"'.
                        "Puvodni vyjimka: $e");

                    $dataToSend[$attr] = null;
                }
            }
            elseif(array_key_exists($attr, $userData)){
                $dataToSend[$attr] = $userData[$attr];
            }
            else{
                $dataToSend[$attr] = null;
            }
        }


        //normalizujeme klice (lowercase, underscore instead of spaces, remove dots)
        $xmlUserData = array();
        foreach ($dataToSend as $key => $val) {
            $normalized_key = preg_replace(array('/\s/', '/\./','/\-/'),array('_','','_'), strtolower($key));
            $xmlUserData[$normalized_key] = $val;
        }
        $dataToSend = $xmlUserData;

        return $dataToSend;
    }

    /**
     * Ziska pocet bodu uzivatele v tomto inDetailu - wrapper na $this->getUsersPoints()
     *
     * @param array     Pole dat uzivatele z DB.
     * @param array     Pole jiz ziskanych dat pro odeslani do inDetail flashe
     * @return int      Pocet bodu za tuto prezentaci (content) uzivatele podle  $this->getUsersPoints()
     */
    public function xmldataGetAttr_points($userData, $dataToSend)
    {
        return $this->getUsersPoints($userData['id']);
    }

    /**
     * Ziska celkovy pocet bodu uzivatele souctem nejvyssich poctu za inDetaily - wrapper na $this->getUsersSumPoints()
     *
     * @param array     Pole dat uzivatele z DB.
     * @param array     Pole jiz ziskanych dat pro odeslani do inDetail flashe
     * @return int      Pocet bodu za prezentace uzivatele podle $this->getUsersSunPoints()
     */
    public function xmldataGetAttr_sumpoints($userData, $dataToSend)
    {
        return $this->getUsersSumPoints($userData['id']);
    }
    
    
    /**
     * Vrati URL k s xml daty pro flash
     */
    public function getXmlDataUrl()
    {
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $url = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'xmldata'), 'service').'/';

        return $url;
    }


    /**
     * Obsluhuje jednotliva volani akci z iPadu - overuje login a heslo reprezentanta
     * a nasledne spusti metodu odpovidajici akci volane z iPadu.
     *
     * Na vstupu ocekava validni login a md5 otisk hesla reprezentanta - predane nejlepe
     * metodou POST v parametrech "login" a "pass".
     *
     * Nevpusti reprezentanty, kteri jsou smazani (users.deleted)
     *
     * VOLA DALSI METODY! (ipad_*)
     *
     * @param Zend_Controller_Request_Abstract  Request objekt.
     */
    public static function ipadService(Zend_Controller_Request_Abstract $req)
    {
        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();

        // Nabereme parametry
        $login = $req->getParam('login');
        $pass = $req->getParam('pass');
        $operation = $req->getParam('operation');
        $errorLog = $req->getParam('errorlog', null);

        // Zapiseme pripadne error logy predane z iPadu
        if(!empty($errorLog)){
            //$errorLogObj = json_decode($errorLog);
            //if ($errorLogObj && isset($errorLogObj->errors) && is_array($errorLogObj->errors)) {
            //    foreach($errorLogObj->errors as $entry){
            //        // Nepouzivame kvuli chybe error logu, kdy pri zalogovani vice warn za sebou se kazdy dalsi zapise do souboru vickrat
            //        //Phc_ErrorLog::warning('iPad internal log', json_encode($entry/*, JSON_PRETTY_PRINT from PHP 5.4*/));
            //    }
            //}
            /* Odkomentovat po odstraneni chyby v error logu a odstranit logovani dole
            else{
                Phc_ErrorLog::warning('iPad internal log', $errorLog);
            }
            */
            Phc_ErrorLog::warning('iPad internal log', $errorLog);
            //Phc_ErrorLog::warning('iPad internal log', print_r($errorLogObj, true));
        }

        // Pokud neni zadan login nebo heslo, vracime HTTP 401 - unauthorized
        if(empty($login) || empty($pass)){
            $response->setHttpResponseCode(401);
            echo 'Error, login or password was not set.';
            return;
        }

        // Pokusime se overit reprezentanta pomoci hesla a loginu
        try{
            $repre = Users::getUser(null, null, $login);
        }
        catch(Users_User_Not_Found_Exception $e){
            // Nepodarilo se overit reprezentanta
            $response->setHttpResponseCode(401);
            echo 'Error, representant with given login and password was not found.';
            return;
        }
        // Overime, ze uzivatel je repre a ze heslo sedi
        if(!$repre['is_rep'] || $repre['pass'] != $pass || $repre['deleted']){
            // Nepodarilo se overit reprezentanta
            $response->setHttpResponseCode(401);
            echo 'Error, representant with given login and password was not found.';
            return;
        }

        // Nastavime repre jako aktualniho uzivatele
        Ibulletin_Auth::setUser($repre['id']);

        // Overime pozadovanou akci
        if(empty($operation) || !method_exists(get_class(), 'ipad_'.$operation)){ // get_class() bez parametru vraci jmeno aktualni class - self zde nefunguje
            // Nepodarilo se overit reprezentanta
            $response->setHttpResponseCode(500);
            echo 'Error, requested operation "'.$operation.'" was not found.';
            return;
        }

        // Zpracovani pozadovane operace
        try{
            // Spustime akci
            call_user_func_array(array(get_class(), 'ipad_'.$operation), array($repre, $req));
        }
        catch(Exception $e){
        	var_dump($operation);
            // Nastala nejaka necekana chyba pri zpracovani, posleme HTTP 500 a pridame jednoduchou hlasku
            $response->setHttpResponseCode(500);
            echo 'Uknown internal error occured during request.';

            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipadService()', 'Pri zpracovani pozadavku "'.
                'ipad_'.$operation.'" z iPadu nastala neznama chyba. Puvodni vyjimka:'."\n$e");
        }
    }

    /**
     * DataSet01
     *
     * Vrati iPadu data o prave prihlasenem reprezentantovi
     * a casove znamky posledni zmeny dat uzivatelu tohoto reprezentanta
     * s informacemi o jednotlivych prezentacich (casova znamka zmeny, id, jmeno).
     *
     * Data prezentace jsou brana z Pages::getPagesList(), name prezentace je jmeno page v ktere
     * content prezentace je.
     *
     * @param array $repre  Reprezentant jehoz uzivatele chceme ziskat
     * @param Zend_Controller_Request_Abstract  Request
     */
    public static function ipad_login($repre, Zend_Controller_Request_Abstract $req)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        // Vytrvorime tridu, do ktere naplnime potrebna data DataSet01
        $data = new stdClass;

        // Posilame jen omezena data reprezentanta
        $repreData = new stdClass;
        $repreData->id = (int)$repre['id'];
        $repreData->name = (string)$repre['name'];
        $repreData->surname = (string)$repre['surname'];
        $repreData->login = (string)$repre['login'];
        $repreData->pass = (string)$repre['pass'];
        $data->representant = $repreData;

        // Dotaz pro prelozeni timestamp na timestamp with time zone skrz DB - neefektivni
        // TODO vyresit asi efektivneji, ovsem Zend_Date taky moc efektivni neni
        $timestSel = "select :timestamp::timestamp with time zone";


        // PREZENTACE
        // Najdeme contenty a pripravime je do dat - hledame podle pages kvuli platnosti a zarazeni do vydani
        $pages = new Pages;
        $contents = $pages->getPagesList(null, null, true, array("c.class_name = '".get_class()."'"),
                                         array('b.valid_from ASC', 'b.id ASC'), null, null,
                                         false, !$repre['test']
                                         );
        $presentations = array();
        $contentIds = array();  // ID contentu pro ziskani resources
        foreach($contents as $content){
            $contentIds[] = $content['content_id'];
            $presentation = new stdClass();
            $presentation->id = $content['content_id'];
            $presentation->name = $content['page_name'];
            $presentation->entrycode = $content['obj']->entrycode;

            // Vytahneme soubory prezentaci a zjistime jejich velikosti
            // Overime existenci balicku contentu a vybereme spravnou verzi (test/normalni)
            if($repre['test']){
                $inRepPres = new Inreppresentation($content['content_id'], true);
                if(!$inRepPres->isReady()){
                    // Testovaci verze prezentace neni, pouzijeme ostrou pro testovaciho uzivatele
                    $inRepPres = new Inreppresentation($content['content_id']);
                }
            }
            // Chceme jen ostrou verzi prezentace
            else{
                $inRepPres = new Inreppresentation($content['content_id']);
            }
            $presentationFile = $inRepPres->getZipPackagePath();
            if(!$inRepPres->isReady())
            {
                // Prezentace neexistuje, jdeme na dalsi
                continue;
            }

            // Musime overit cas posledni modifikace souboru a pokud je novejsi nez modifikace contentu pouzit ho
            try{
                $lastModifiedFileObj = new Zend_Date(filemtime($presentationFile), Zend_Date::TIMESTAMP);
                $lastModifiedContentbj = new Zend_Date($content['content_changed'], Zend_Date::ISO_8601);
                if($lastModifiedFileObj > $lastModifiedContentbj){
                    $lastModified = $lastModifiedFileObj->getIso();
                }
                else{
                    $lastModified = $content['content_changed'];
                }
            }
            catch(Zend_Date_Exception $e){
                // V pripade nejakeho problemu se Zend_Date pouzijeme datum z DB
                $lastModified = $content['content_changed'];

                Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_login()',
                    'Pri parsovani timestamp do Zend_Date nastal problem - puvodni vyjimka:'."\n$e");

            }
            $presentation->lastModified = $db->fetchOne($timestSel, array('timestamp' => $lastModified));

            // Velikost souboru
            $presentation->length = filesize($presentationFile);

            // Nazvy slidu
            $sel = new Zend_Db_Select($db);
            $sel->from(array('s' => 'slides'), array('slide_num', 'name'))
                ->where('content_id = ?', (int)$content['content_id']);
            $slidesData = $db->fetchAll($sel);
            $slides = array();
            foreach($slidesData as $row){
                $slide = new stdClass();
                $slide->id = $row['slide_num'];
                $slide->name = $row['name'];
                $slides[] = $slide; // Klicem nemuze byt slideId, protoze aplikace na iPadu pracuje s daty jako s polem a ne jako s objektem
            }
            $presentation->slides = $slides;

            // Pridame data souhrnu odpovedi pro nacteni v prezentaci
            if($config->ipad->sendStatisticalData){
                $questions = new Questions($content['content_id']);
                $questionsList = $questions->getList();
                if(!empty($questionsList)){
                    $answerStats = $questions->getContentStats();
                    foreach($slides as $slide){
                        if(!isset($answerStats['slides'][$slide->id])){
                            continue;
                        }
                        $slide->questions = $answerStats['slides'][$slide->id]['questions'];
                    }
                    //$presentation->answerStats = $answerStats;
                }
            }

            // Pridame na seznam
            $presentations[] = $presentation;
        }
        $data->presentations = $presentations;



        //
        // USERS
        //
        $data->users = new stdClass();
        $data->users->lastModified = self::_ipad_getUsersLastModifiedTime($repreData->id)->getIso();
        // serverTimestamp
        $nowDate = new Zend_Date();
        $data->serverTimestamp = $nowDate->getIso();


        //
        // RESOURCES
        //
        $resourcesData = Resources::getBy($contentIds, Bulletins::getBulletinList(false, null, true));
        $resources = array();
        foreach($resourcesData as $resourceRow){
            $resource = new stdClass();
            $resource->id = $resourceRow['id'];
            $resource->presentationId = $resourceRow['content_id'];
            $resource->name = $resourceRow['name'];
            $lastModifiedUnix = filemtime($resourceRow['path']);
            $lastModifiedObj = new Zend_Date((int)$lastModifiedUnix, Zend_Date::TIMESTAMP);
            $resource->lastModified = $lastModifiedObj->getIso();
            $resource->order = $resourceRow['order'];

            $resources[] = $resource;
        }
        $data->resources = $resources;


        //
        // EMAILS - Available e-mails
        //
        $sel = new Zend_Db_Select($db);
        $sel->from(array('e' => 'emails'), array('id', 'name', 'subject', 'type' => 'special_function'))
            ->where("special_function LIKE 'inrep%'");
        $emailsRows = $db->fetchAll($sel, array(), Zend_Db::FETCH_CLASS);
        // Pridame available texts, zatim staticky
        foreach($emailsRows as $key => $email){
            $email->availableTexts = new stdClass();
            //$email->availableTexts->inReptxt_default = "Default text to be pasted into email.";

            // Pokusime se dohledat vsechny dostupne znacky textu inRepa z tela emailu
            $emailObj = Ibulletin_Email::getEmail($email->id);
            $tags = $config->mailer->tags;
            $pattern = '/'.$tags->open.'(inReptxt_([\w]+))'.$tags->close.'/';
            $m = array();
            preg_match_all($pattern, $emailObj->getBody().$emailObj->getPlain(), $m);
            // Vyplnime vsechny nalezene tagy pro texty inrepa do objektu
            foreach($m[1] as $key1 => $textName){
                $email->availableTexts->{$textName} = $m[2][$key1];
            }

            $emailsRows[$key] = $email;
        }
        $data->emails = $emailsRows;


        //
        // CONFIG - Configurations for inRep mobile app from config
        //
        $inRepConfig = new stdClass();
        if(isset($config->ipad) && isset($config->ipad->configs)){
            $inRepConfig = $config->ipad->configs->toArray();
        }
        $data->config = $inRepConfig;



        $data->dataVersion = "0.6";

        // Vratime dataSet v JSON na vystup
        $frontController = Zend_Controller_Front::getInstance();
        $frontController->getResponse()->setHeader('Content-type','application/json');
        $json = json_encode($data);
        echo $json;

        //Phc_ErrorLog::warning('Dbg', $json);
    }


    /**
     * Vrati DataSet02 jako odpoved na pozadavek iPadu getUsers -
     * ziskani uzivatelu daneho reprezentanta. Atributy uzivatele k predani jsou
     * definovany v $config->ipad->getCommaSeparated('usersAttribsToSend').
     *
     * Pro reprezentanty, kteri jsou oznaceni jako test nefiltruje uzivatele podle pohledu users_vf
     *
     * @param array $repre  Reprezentant jehoz uzivatele chceme ziskat
     * @param Zend_Controller_Request_Abstract  Request
     */
    public static function ipad_getUsers($repre, Zend_Controller_Request_Abstract $req)
    {
        // Preklady nazvu nekterych atributu z tabulky users pro iPad
        $attrTranslator = array('degree_before' => 'degreeBefore', 'degree_after' => 'degreeAfter');

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        // Najdeme contenty - hledame podle pages kvuli platnosti a zarazeni do vydani
        // Pripravime pole platnych pages ve vydani pro preklad na content IDs
        $pagesObj = new Pages;
        $pagesRaw = $pagesObj->getPagesList(null, null, true, array("c.class_name = '".get_class()."'"),
                                         array('b.id ASC'), null, null,
                                         false, !$repre['test']
                                         );
        // Priprava Pages a statistickych dat odpovedi
        $pages = array();
        $presentations = array();
        foreach($pagesRaw as $page){
            $pages[$page['page_id']] = $page;
        }
        // Zjistime, ktere prezentace maji nastavene repy v reps_pages
        $sel = new Zend_Db_Select($db);
        $sel->from(array('rp' => 'reps_pages'), array('page_id'))
            ->group(array('page_id'));
        $pagesByReps = $db->fetchAssoc($sel);
        // Najdeme pripadne prezentace tohoto repa v reps_pages
        $sel = new Zend_Db_Select($db);
        $sel->from(array('rp' => 'reps_pages'), array('page_id'))
            ->where('repre_id = ?', $repre['id']);
        $repsPages = $db->fetchCol($sel);
        // Pripravime si vazby mezi segmenty a pages pro spravne vyplneni vazeb pro jednotlive uzivatele
        $sel = new Zend_Db_Select($db);
        $sel->from(array('ps' => 'pages_segments'), array('segment_id', 'page_id'))
            ->join(array('s' => 'segments'), 's.id = ps.segment_id', array())
            ->where('s.deleted IS NULL')
            ->order(array('segment_id', 'page_id'));
        $segmentsPagesRaw = $db->fetchAll($sel);
        $segmentsPages = array();
        foreach($segmentsPagesRaw as $row){
            if(!isset($pages[$row['page_id']])){// preskakujeme zaznamy, ktere patri k neaktivnim pages
               continue;
            }
            if(empty($segmentsPages[$row['segment_id']])){
                $segmentsPages[$row['segment_id']] = array();
            }

            $segmentsPages[$row['segment_id']][] = $row['page_id'];
        }
        // Pripravime si seznam pages, ktere se ridi (mohou ridit - nastaveni repre ma prednost) podle segmentu
        $sel = new Zend_Db_Select($db);
        $sel->from(array('ps' => 'pages_segments'), array('page_id'))
            ->group(array('page_id'));
        $pagesBySegment = $db->fetchAssoc($sel);

        // Vytrvorime tridu, do ktere naplnime potrebna data DataSet02
        $data = new stdClass;

        // Pripravime subselect pro nalezeni odpovedi uzivatelu
        $answersSel = self::_ipad_getUsersAnswersSel();

        // Pripravime dotaz pro ziskani dat a ziskani last changed a odpovedi uzivatele
        $sel = new Zend_Db_Select($db);
        $sel->from(array('u' => 'users'), array('id', 'last_changed'))
            ->join(array('ur' => 'users_reps'), 'ur.user_id = u.id', array())
            ->joinLeft(array('ua' => $answersSel), 'ua.user_id = u.id', array('*'))
            ->where('ur.repre_id = ?', $repre['id']);
        // Uzivatele posilame do iPadu i testovaci, aby meli repre jak testovat/zkouset
        // NEPLATI Pokud neni repre test, vyfiltrujeme uzivatele podle tabulky users_vf
        //if(!$repre['test']){
        //    $sel->join(array('uvf' => 'users_vf'), 'uvf.id = u.id', array());
        //}


        // Ziskame datum posledni zmeny sady uzivatelu
        $data->lastModified = self::_ipad_getUsersLastModifiedTime($repre['id'])->getIso();

        // LastVisit - posledni navstevy
        $lastVisits = self::_ipad_getLastVisitsSel();
        //$sel->joinLeft(array('lv' => $lastVisits), 'lv.user_id');
        //Phc_ErrorLog::error('debug', $lastVisits);
        $lastVisitsRaw = $db->fetchAll($lastVisits, array(), Zend_Db::FETCH_CLASS);
        // Pripravime data do pole podle user_id pro snazsi slucovani
        $lastVisitsA = array();
        foreach($lastVisitsRaw as $row){
            if(!isset($lastVisitsA[$row->user_id])){
                $lastVisitsA[$row->user_id] = array();
            }
            $lastVisitsA[$row->user_id][$row->content_id] = $row;
        }
        unset($lastVisitsRaw);

        // Ziskame data uzivatelu patricich k danemu repre
        $sel->order(array('u.email', 'u.login'))
            ->where('u.deleted IS NULL');
        $usersData = $db->fetchAll($sel);

        // Pole uzivatelu do dataSet02
        $users = array();

        // Prochazime nalezene uzivatele a ziskavame jejich data
        // Spojime atributy pozadovane do aplikace a atributy do vypisu dat uzivatele
        $attribsA = array_unique(array_merge(
                (array)$config->ipad->getCommaSeparated('usersAttribsToSend'),
                (array)$config->ipad->getCommaSeparated('usersAttribsForUserProfile'),
                array('send_emails')
        ));
        $attribsA = array_unique($attribsA);
        foreach($usersData as $row){
            $userA = Users::getUser($row['id']);
            $user = new stdClass();
            $user->id = $row['id'];

            // Vlozime vsechny atributy uzivatele, ktere jsou uvedene v configu
            foreach($attribsA as $attr){
                // Prelozime nazev atributu
                $attrName = isset($attrTranslator[$attr]) ? $attrTranslator[$attr] : $attr;
                if(array_key_exists($attr, $userA)){
                    $user->$attrName = $userA[$attr];
                }
                else{
                    $user->$attrName = null;
                }
            }

            // Pripravime seznam dostupnych prezentaci pro daneho uzivatele
            $presentationsAvailable = array();

            // Vkladame uzivateli prezentace, ktere mu bud patri podle repa, segmentu
            // nebo prezentace, ktere nemaji ani repa ani segment definovany
            foreach($pages as $pageId => $page){
                // Page ma primo nastavene reprezentanty, kteri ji mohou videt
                if(isset($pagesByReps[$pageId])){
                    // Priradime, pokud ma rep tuto page
                    if(in_array($pageId, $repsPages)){
                        $presentationsAvailable[] = $page['content_id'];
                    }
                }
                // Page ma nastavene segmenty
                elseif(isset($pagesBySegment[$pageId])){
                    //Phc_ErrorLog::notice('debug', print_r($segmentsPages[$userA['segment_id']], true));
                    // Priradime, pokud je page v segmentu tohoto uzivatele
                    if(isset($segmentsPages[$userA['segment_id']]) && in_array($pageId, $segmentsPages[$userA['segment_id']])){
                        $presentationsAvailable[] = $page['content_id'];
                    }
                }
                // Pokud prezentace neni v zadnem segmentu a prezentace nema zadneho repa, uzivatel tuto prezentaci muze videt
                else{
                    $presentationsAvailable[] = $page['content_id'];
                }
            }
            $user->presentationsAvailable = $presentationsAvailable;


            //
            // Pridame odpovedi uzivatele
            //
            $user->presentations = new stdClass();
            foreach($row as $key => $val){
                if(empty($val) && $val !== 0){
                    continue;
                }

                // Rozparsujeme nazev atributu na presentation_id, slide_num, question_num
                $m = array();
                if(preg_match('/([0-9]+)_([0-9]+)_([0-9]+)_(.+)/i', $key, $m)){
                    list($whole, $pres, $slide, $quest, $type) = $m;
                    // Vytvarime postupne strukturu pro ukladani dat
                    if(!isset($user->presentations->{$pres})){
                        $user->presentations->{$pres} = new stdClass();
                        $user->presentations->{$pres}->slides = new stdClass();
                    }
                    if(!isset($user->presentations->{$pres}->slides->{$slide})){
                        $user->presentations->{$pres}->slides->{$slide} = new stdClass();
                        $user->presentations->{$pres}->slides->{$slide}->answers = new stdClass();
                    }

                    if($type == 'array'){
                        $user->presentations->{$pres}->slides->{$slide}->answers->{$quest} = explode('|', $val);
                        // Z pripadnych cisel v poli udelame inty
                        if(!function_exists('walkFunction')){
                            function walkFunction(&$item, $key){
                                is_numeric($item) ? $item = (int)$item : null;
                            }
                        }
                        array_walk($user->presentations->{$pres}->slides->{$slide}->answers->{$quest}, 'walkFunction');
                    }
                    else{
                        $user->presentations->{$pres}->slides->{$slide}->answers->{$quest} = $val;
                    }
                }
            }

            // LastVisits
            if(isset($lastVisitsA[$user->id])){
                // Jednotlive navstivene prezentace
                foreach($lastVisitsA[$user->id] as $presentationVisit){
                    $pres = $presentationVisit->content_id;
                    if(!isset($user->presentations->{$pres})){
                        $user->presentations->{$pres} = new stdClass();
                    }

                    $lastVisit = new stdClass();
                    $lastVisit->repreId = $presentationVisit->repre_id;
                    $lastVisit->repreName = $presentationVisit->repre_name.' '.$presentationVisit->repre_surname;
                    $lastVisit->timestamp = $presentationVisit->timestamp;
                    $user->presentations->{$pres}->lastVisit = $lastVisit;
                }
            }


            // Najdeme vsechny odeslane emaily
            // TODO chybi rozpoznani, ze to byly maily na iPad
            $sel = new Zend_Db_Select($db);
            $sel->from(array('ue' => 'users_emails'), array('email_id', 'repre_id' => 'sent_by_user', 'timestamp' => 'sent'))
                ->join(array('u' => 'users'), 'u.id = ue.sent_by_user', array('repre_name' => 'name', 'repre_surname' => 'surname'))
                ->join(array('e' => 'emails'), 'e.id = ue.email_id AND e.special_function LIKE \'inrep%\'')
                ->where('user_id = ?', $user->id)
                ->where('ue.sent IS NOT null')
                ->where('u.is_rep');
            $emailsSentRaw = $db->fetchAll($sel, array(), Zend_Db::FETCH_CLASS);
            $emailsSentA = array();
            foreach($emailsSentRaw as $email){
                $emailSent = new stdClass();
                $emailSent->id = $email->email_id;
                $emailSent->repreId = $email->repre_id;
                $emailSent->repreName = $email->repre_name.' '.$email->repre_surname;
                $emailSent->timestamp = $email->timestamp;
                $emailsSentA[] = $emailSent;
            }
            $user->emailsSent = $emailsSentA;

            // Pridame uzivatele
            $users[] = $user;
        }
        $data->users = $users;


        // Pripravime nazvy atributu pro profil uzivatele na iPadu
        $profileAttribsA = (array)$config->ipad->getCommaSeparated('usersAttribsForUserProfile');
        $profileAttribs = array();
        foreach($profileAttribsA as $attrName){
            $obj = new stdClass();
            $attrText = @Ibulletin_Texts::get('.usrAttr_'.$attrName);
            // Prelozime nazev atributu
            $attrName = isset($attrTranslator[$attrName]) ? $attrTranslator[$attrName] : $attrName;
            @$obj->{$attrName} = $attrText;
            // Pokud nebyl nalezen text, pouzijeme jen nazev atributu
            empty($obj->{$attrName}) ? $obj->{$attrName} = ucfirst($attrName) : null;
            $profileAttribs[] = $obj;
        }
        $data->userProfileAttrNames = $profileAttribs;


        // Vratime dataSet v JSON na vystup
        $frontController = Zend_Controller_Front::getInstance();
        $frontController->getResponse()->setHeader('Content-type','application/json');
        $json = json_encode($data);
        //Phc_ErrorLog::notice('debug', $json);
        echo $json;
    }

    /**
     * Pripravi select pro ziskani odpovedi jednotlivych uzivatelu pro odeslani do iPadu.
     * Pouzivame jako subselect pri ziskavani uzivatelu.
     *
     * Nazvy sloupcu odpovedi jsou ve tvaru [content_id]_[slide_num]_[question_num]
     */
    private static function _ipad_getUsersAnswersSel()
    {
        $db = Zend_Registry::get('db');

        // Ziskame seznam contentu, slajdu, a otazek pro vytvoreni pivot selectu dat z users_answers
        $sel = new Zend_Db_Select($db);
        $sel->from(array('s'=>'slides'), array('s.content_id', 'slide_num', 'q.question_num', 'q.id AS question_id'))
            ->join(array('q' => 'questions'), 's.id = q.slide_id', array())
            ->order(array('s.content_id', 'slide_num', 'q.question_num'));
        $questionsList = $db->fetchAll($sel);

        // Pripravime pole sloupcu do select klauzule
        $cols = array();
        foreach($questionsList as $question){
            $colName = $question['content_id'].'_'.$question['slide_num'].'_'.$question['question_num'];
            $col = "max(CASE WHEN ua.question_id = ${question['question_id']} THEN
                        array_to_string(uac.answers, '|')
                        ELSE null END) AS \"${colName}_array\",
                    max(CASE WHEN ua.question_id = ${question['question_id']} THEN
                        coalesce(a.answer_num, ua.answer_int)
                        ELSE null END) AS \"${colName}_int\",
                    max(CASE WHEN ua.question_id = ${question['question_id']} THEN
                        ua.text
                        ELSE null END) AS \"${colName}_text\",
                    max(CASE WHEN ua.question_id = ${question['question_id']} THEN
                        ua.answer_double
                        ELSE null END) AS \"${colName}_double\",
                    bool_or(CASE WHEN ua.question_id = ${question['question_id']} THEN
                        ua.answer_bool
                        ELSE null END) AS \"${colName}_bool\"
                    ";
            $cols[] = new Zend_Db_Expr($col);
        }

        $sel = new Zend_Db_Select($db);
        $sel->from(array('ua' => 'users_answers'), array_merge(array('ua.user_id', 'max(ua.timestamp) AS timestamp'), $cols))
            ->joinLeft(array('a' => 'answers'), 'a.id = ua.answer_id', array())
            ->joinLeft(array('uac' =>
                    new Zend_Db_Expr('(SELECT users_answers_id, array_agg(a.answer_num) AS answers FROM users_answers_choices uac JOIN answers a ON a.id = uac.answer_id GROUP BY users_answers_id)')
                ), 'uac.users_answers_id = ua.id', array())
            ->group(array('ua.user_id'))
            ;

        //Phc_ErrorLog::notice('debug', $sel);

        return $sel;
    }


    /**
     * Dotaz po ziskani posledni navstevy pro kazdeho uzivatele ke kazdemu contentu.
     * Vraci sloupce: user_id, content_id, timestamp, repre_id
     *
     * @return Zend_Db_Select   Select pro ziskani poslednich navstev lekare a contentu
     */
    public static function _ipad_getLastVisitsSel()
    {
        $db = Zend_Registry::get('db');

        // LastVisit - posledni navstevy
        // TODO Neni nijak omezeno pres jaka data se hledaji posledni navstevy (zkusit dle contents daneho repa?)
        $lastVisits = new Zend_Db_Select($db);
        $lastVisits->from(array('inds' => 'indetail_stats'), array('user_id', 'content_id', 'timestamp'))
            ->join(array('pv' => 'page_views'), 'pv.id = inds.page_views_id', array())
            ->join(array('sbr' => 'sessions_by_reps'), 'sbr.session_id = pv.session_id', array())
            ->join(array('r' => 'users'), 'r.id = sbr.repre_id', array('repre_id' => 'r.id',
                'repre_name' => 'r.name', 'repre_surname' => 'r.surname'))
            // Omezeni podle poslednich pristupu na danou prezentaci
            ->join(array('maxs' => new Zend_Db_Expr(
                "(SELECT max(inds.id) AS id
                    FROM indetail_stats inds
                    JOIN (
                        SELECT user_id, content_id, max(timestamp) as timestamp FROM indetail_stats inds
                            GROUP BY user_id, content_id
                    ) AS maxs1 ON inds.timestamp = maxs1.timestamp AND inds.user_id = maxs1.user_id AND maxs1.content_id = inds.content_id
                    GROUP BY inds.user_id, inds.content_id
                )"
                )), 'maxs.id = inds.id', array())
            ;

        return $lastVisits;
    }


    /**
     * Odesle balik dat pozadovane prezentace do iPadu.
     *
     * Je pouze overena existence pozadovaneho contentu a nasledne souboru, soubor je pak odeslan. Respektuje
     * typ reprezentanta. Testovacimu reprezentantovi posila data testovaci prezentace, pokud je takova k dispozici.
     *
     * POZOR - ukoncuje session - aby mohly probihat pozadavky behem odesilani dat prezentace
     *
     * @param array $repre  Data reprezentanta z tabulky users
     * @param Zend_Controller_Request_Abstract  Request
     */
    public static function ipad_getPresentation($repre, Zend_Controller_Request_Abstract $req)
    {
        $config = Zend_Registry::get('config');

        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();

        $contentId = $req->getParam('presentationId');
        $content = Contents::get($contentId);

        // Pokud nebyl content nalezen, ohlasime chybu a konec.
        if(empty($content)){
            $response->setHttpResponseCode(404);
            echo 'Error - presentation with id = "'.(int)$contentId.'" was not found.';
            return;
        }
        
        // Pripravime objekt pro praci s inRep prezentacemi
        // Pouzijeme bud ostre nebo testovaci prezentace podle aktualniho repa
        $rep = Ibulletin_Auth::getActualUserData();
        if($rep['test']){
            $inrepPres = new Inreppresentation($contentId, true);
            if(!$inrepPres->isReady()){
                $inrepPres = new Inreppresentation($contentId, false);
            }
        }
        else{
            $inrepPres = new Inreppresentation($contentId, false);
        }
        
        $presentationFile = $inrepPres->getZipPackagePath();

        // Overime existenci adresare contentu
        if(!is_readable($presentationFile))
        {
            $response->setHttpResponseCode(404);
            echo 'Error - files of presentation with id = "'.(int)$contentId.'" were not found or package is not readable.';
            return;
        }

        // Vratime soubor
        $filename = 'presentation_'.(int)$contentId.'.zip';
        $length = filesize($presentationFile);
        $response->setHeader('Content-Type', 'application/zip', true);
        $response->setHeader('Content-length', (int)$length, true);
        $response->setHeader('Content-Disposition', 'attachment; filename="'.$filename.'"', true);

        // Konec session kvuli dalsim pozadavkum v teto session
        session_write_close();

        // Posilame data
        $fp = fopen($presentationFile, "r");
        fpassthru($fp);
        fclose($fp);
        return;
    }


    /**
     * Odesle zip obsahujici jeden resource do iPadu.
     *
     * POZOR - ukoncuje session - aby mohly probihat pozadavky behem odesilani dat prezentace
     *
     * @param array $repre  Data reprezentanta z tabulky users
     * @param Zend_Controller_Request_Abstract  Request
     */
    public static function ipad_getResource($repre, Zend_Controller_Request_Abstract $req)
    {
        $config = Zend_Registry::get('config');

        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();

        $resourceId = $req->getParam('resourceId');
        $resource = Resources::get($resourceId);

        // Pokud nebyl content nalezen, ohlasime chybu a konec.
        if(empty($resource)){
            $response->setHttpResponseCode(404);
            echo 'Error - resource with id = "'.(int)$resourceId.'" was not found.';
            return;
        }

        // Overime existenci souboru
        if(!file_exists($resource['path']))
        {
            $response->setHttpResponseCode(404);
            echo 'Error - files of presentation with id = "'.(int)$contentId.'" were not found.';
            return;
        }

        // Zazipujeme
        $file = tempnam("tmp", "zip");
        $zip = new ZipArchive();
        // Zip will open and overwrite the file, rather than try to read it.
        $zip->open($file, ZipArchive::OVERWRITE);
        $zip->addFile($resource['path'], basename($resource['path']));
        $zip->close();

        // Vratime soubor
        $filename = basename($resource['path']).'.zip';
        $length = filesize($file);
        $response->setHeader('Content-Type', 'application/zip', true);
        $response->setHeader('Content-length', (int)$length, true);
        $response->setHeader('Content-Disposition', 'attachment; filename="'.$filename.'"', true);

        // Konec session kvuli dalsim pozadavkum v teto session
        session_write_close();

        // Posilame data
        ob_end_clean();
        readfile($file);
        unlink($file);
        return;
    }


    /**
     * Prijme a ulozi data vyplnovani slidu uzivatele.
     * Ocekava na vstupu DataSet03 v predane promenne "data".
     *
     * @param array $repre  Data reprezentanta z tabulky users
     * @param Zend_Controller_Request_Abstract  Request
     */
    public static function ipad_sendUsersData($repre, Zend_Controller_Request_Abstract $req)
    {
        $frontController = Zend_Controller_Front::getInstance();
        $response = &$frontController->getResponse();
        $response->setHeader('Content-type','application/json');

        // Objekt vystupu
        //$out = new stdClass;

        $dataRaw = $req->getParam('data');
        $checksum = $req->getParam('checksum');

        // Overime, ze byla predana data k ulozeni
        if(empty($dataRaw)){
            $response->setHttpResponseCode(500);
            echo 'Error - no data sent for save.';
            //$out->error = 'Error - no data sent for save.';
            //$json = json_encode($out);
            //echo $json;
            return;
        }
        // Overime, jestli data souhlasi s otiskem
        if(!empty($checksum) && md5($dataRaw) != $checksum){
            $response->setHttpResponseCode(500);
            echo 'Error - checksums don\'t match.';

            // Zalogujeme dodana data
            Phc_ErrorLog::warning('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                "Dodany checksum ($checksum) neodpovida vypoctenemu checksumu (".md5($dataRaw).").".
                "\nPredana data (POST): \n".
                print_r($_POST, true));
            return;
        }

        // Pripravime si page_view_id tohoto zobrazeni stranky pro ulozeni do sessions_by_reps
        $statsRepre = Ibulletin_Stats::getInstance();
        $reprePageViewId = $statsRepre->page_view_id;

        // Overime, ze dana data jeste nebyla zpracovana podle checksumu
		if($statsRepre->checksumAlredyProcessed($checksum)){
			// Data uz byla zpracovana, koncime (na zarizeni dojde potvrzeni HTTP 200)
			return;
		}

        // PATCH NA TIMESTAMPY V KTERYCH CHYBI '+'
        //$dataRaw = preg_replace('/([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}) ([0-9]{2})/', '$1+$2', $dataRaw);

        // Pokusime se dekodovat JSON data
        $data = json_decode($dataRaw);

        if($data === null){
            // Data se nepodarilo rozparsovat nebo jsou prazdna
            $response->setHttpResponseCode(500);
            echo 'Error - cannot parse JSON data.';
            //$out->error = 'Error - cannot parse JSON data.';
            //$json = json_encode($out);
            //echo $json;

            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                "Nepodarilo se rozparsovat JSON data.".
                "\nPredana data (POST): \n".
                print_r($_POST, true));

            return;
        }

        // Pripravime si validator pro timestamp
        $isoTimestValid = new Ibulletin_Validators_TimestampIsoFilter;

        // Pripravime si seznam bulletinu, contentu a pages, abychom mohli vyplnovat
        // spravna data do page_views podle ID contentu.
        // Najdeme prezentace a pripravime je do dat
        $pages = new Pages;
        $contents = $pages->getPagesList(null, null, true, array("c.class_name = '".get_class()."'"),
                                         array('b.valid_from ASC', 'b.id ASC'), null, null, false, false);
        // Vytvorime nove pole, ktere bude mit jako klice id contentu
        $contentsReindexed = array();
        foreach($contents as $key => $content){
            unset($contents[$key]);
            $contentsReindexed[$content['content_id']] = $content;
        }
        $contents = $contentsReindexed;
        unset($contentsReindexed);

        // Muzeme ukladat data
        $rawDataToLog = false; // Vypsat prijaty retezec dat do logu - abychom vypisovali jen jednou cely retezec
        foreach($data->sessions as $session) {
            // Overime, ze jsou k dispozici vsechny potrebne parametry
            if(empty($session->repreId) || empty($session->userId)){
                // Chybi nektery z pozadovanych parametru
                Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                    'Pri ukladani dat session chybi potrebne udaje. '.
                    "repre_id:'$session->repreId', user_id:'$session->userId'.".
                    "data session: \n".print_r($session, true));
                $rawDataToLog = true;
                continue;
            }

            // Overime existenci uzivatele
            try{
                $user = Users::getUser($session->userId);
            }
            catch(Users_User_Not_Found_Exception $e){
                Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                'Nepodarilo se nalezt zadaneho uzivatele. '.
                "content_id:'$session->presentationId', slide_num:'$session->id', user_id:'$session->userId'.".
                "data slidu: \n".print_r($session, true));
                continue;
            }

            // -- START SESSION --
            // Novy objekt stats jako uplne nova session
            $stats = new Ibulletin_Stats('ipadsession', true);
            // DATA SESSION
            $stats->setAttrib('user_id', $session->userId);
            $stats->restoreCookie($session->userId); // Pozor, je nejspise pomerne hodne neefektivni, chceme vsak zajistit, aby se nam nemnozily cookie
            if($session->comment){
                $stats->setAttrib('comment', $session->comment);
            }
            $stats->setAttrib('sessions.timestamp', $session->timestampStart);
            // Casova zona uzivatele, musime ziskat UTC offset
            $utcOffset = Utils::getUtcOffset($session->timestampStart);
            $stats->initializeTimezone($utcOffset);
            $stats->setAttrib('session_length', $session->timeTotal/1000);
            try{
                $stats->setSessionMadeByRepre($session->repreId, $reprePageViewId, $checksum);
            }
            catch(Exception $e){
                // Zalogujeme chybu a nechame skript pokracovat
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                    "Nepodarilo se ulozit vazbu reprezentanta na uzivatelovu session (sessions_by_reps). ".
                    "repreId: '".$session->repreId."', reprePageViewId: '$reprePageViewId', userId: '$session->userId', ".
                    "sessionTimestamp: '$session->timestampStart'".
                    "Puvodni vyjimka:\n".$e);
            }

            // -- JEDNOTLIVE ZOBRAZENI SLIDU --
            // Pridame do slides jeste jeden prvek pro spravne ulozeni veci po projiti vsech prvku
            $lastContent = null;
            $lastUnique = null;
            $smallestPVTimestamp = null; // Nejmensi timestamp pro nastaveni do tabulky page_views
            $biggestLen = null; // Nejdelsi timeTotal ze vsech slidu u contentu
            $lastElem = new stdClass();
            $lastElem->userId = null;
            $lastElem->presentationId = null;
            $lastElem->isEmptyLastSlide = true;
            $slides[] = $lastElem;
            foreach($session->slides as $slide){
                // Overime, ze jsou k dispozici vsechny potrebne parametry
                if(empty($slide->isEmptyLastSlide) && (empty($slide->id) || empty($slide->presentationId))){
                    // Chybi nektery z pozadovanych parametru
                    Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                        'Pri ukladani dat slidu chybi potrebne udaje. '.
                        "slide_num:'$slide->id', content_id:'$slide->presentationId', user_id:'$sessione->userId'.".
                        "data slidu: \n".print_r($slide, true));
                    $rawDataToLog = true;
                    continue;
                }

                // Pokud se zmenilo id contentu, vytahneme novy objekt
                if(empty($slide->isEmptyLastSlide) && ($lastContent === null || $lastContent != $slide->presentationId))
                {
                    $content = Contents::get($slide->presentationId);

                    // Pokud nebyl nalezen content, zalogujeme a pokracujeme ve zpracovani
                    if(empty($content)){
                        Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                        'Nepodarilo se ziskat content zadaneho ID. '.
                        "content_id:'$slide->presentationId', slide_num:'$slide->id', user_id:'$session->userId'.".
                        "data slidu: \n".print_r($slide, true));
                        continue;
                    }

                    // Objekt pro ukladani odpovedi
                    $questionsObj = new Questions($content['id'], $session->userId);
                }

                //print_r($slide);
                // Vytvorime zaznam v page_views pro kazdy novy content ci unique (dodrzujeme kompatibilitu s prezentacemi bez unique)
                if($lastContent === null || $lastContent != $slide->presentationId
                    || (isset($slide->unique) && ($lastUnique != $slide->unique)) || ($lastUnique !== null && !isset($slide->unique)))
                {
                    if($lastContent !== null){
                        // Konec page_view
                        unset($stats);
                        $stats = new Ibulletin_Stats('ipadsession', true); // Novy page view

                        $biggestLen = null;
                        $smallestPVTimestamp = null;
                    }

                    // DATA PV
                    $stats->setAttrib('action', 'page'); // Akce musi byt page kvuli statistikam
                    if(!empty($contents[$slide->presentationId])){
                        $stats->setAttrib('page_id', $contents[$slide->presentationId]['page_id']);
                        $stats->setAttrib('bulletin_id', $contents[$slide->presentationId]['bulletin_id']);
                        $stats->setAttrib('displayed_version', 'ipad');
                    }
                    // Musime zalogovat chybejici data o contentu
                    else{
                        Phc_ErrorLog::warning('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                        "Nebyla nalezena page a bulletin pro content jehoz data byla prijata z iPadu.\n".
                        "content_id:'$slide->presentationId', slide_num:'$slide->id', user_id:'$session->userId'.");
                    }

                    // Provedeme ulozeni stats (ikdyz jsou data nekompletni), protoze pozdeji potrebujeme pouzit page_views_id
                    $stats->saveToSessions();
                    $stats->saveToPageViews();
                }

                // Timestamp pro page_views - hledame nejmensi timestamp ze vsech slidu daneho contentu
                if($isoTimestValid->isValid($slide->timestampStart)){
                    $newTimest = new Zend_Date($slide->timestampStart, Zend_Date::ISO_8601);
                    if($smallestPVTimestamp !== null && $smallestPVTimestamp > $newTimest){
                        $smallestPVTimestamp = $newTimest;
                        $stats->setAttrib('timestamp', $slide->timestampStart);
                    }
                    elseif($smallestPVTimestamp === null){
                        $smallestPVTimestamp = $newTimest;
                        $stats->setAttrib('timestamp', $slide->timestampStart);
                    }
                }
                // Vadnou timestamp musime zalogovat
                else{
                    Phc_ErrorLog::warning('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                        "Byla prijata neplatna casova znamka timestampStart:'$slide->timestampStart'.\n".
                        "content_id:'$slide->presentationId', slide_num:'$slide->id', user_id:'$session->userId'.");
                }
                // Hledame nejdelsi cas na slidu, abychom mohli spocitat view_length a session_length
                if($biggestLen < $slide->timeTotal){
                    $biggestLen = $slide->timeTotal;
                    $stats->setAttrib('view_length', $biggestLen/1000);
                }

                // Parametry pro chod cyklu
                $lastContent = $content['id'];
                $lastUnique = !empty($slide->unique) ? $slide->unique : null;

                //
                // Ulozime slide
                //
                self::ipadSaveSlideData($content, $slide, $session->userId, $stats->page_view_id, $rawDataToLog, $questionsObj, $req);
            }

            // RESOURCES - ulozeni statistickych dat o vyuziti resources
            if(!isset($session->resources)){
                $session->resources = array();
            }
            foreach($session->resources as $resource){
                try{
                    $stats->saveResourceStats($resource->id, $resource->timestampStart, $resource->timeTotal);
                }
                catch(Exception $e){
                    // Vyjimky zalogujeme.
                    $rawDataToLog = true;
                    Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                        'Cannot save data to resources_stats. '.
                        "UserId: $session->userId, repreId: $session->repreId, resourceId: $resource->id, '.
                        'resourceTimestamp: $resource->timestampStart, resourceTimeTotal: $resource->timeTotal ".
                        'Original exception:'."\n$e");
                }
            }

            // Konec SESSION
            //Phc_ErrorLog::warning('dbg', 'UserId:'.$slide->userId);
            // Ulozime (destruct uz neulozi, protoze bude zrusene session namespace
            $stats->saveToSessions();
            $stats->saveToPageViews();
            // Odebereme session namespace - zrusime session tohoto objektu stats
            // Tim vznikne priste nova session
            $stats->removeSessionNamespace();
            $stats->setNoSaving(true);
            // Spustime destruct kvuli ulozeni dat do DB
            //echo "destruct stats session\n";
            unset($stats);
        }


        // Pokjud je treba, zapiseme do LOGU jeste RAW prijatych dat
        if($rawDataToLog){
            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                    "RAW data prijata do aplikace (POST): \n".
                     print_r($_POST, true));
        }

        // Vratime JSON na vystup
        //$json = json_encode($out);
        //echo $json;
    }

    /**
     * Funkce pro online ukladani dat slidu z prezentace na mobilnim zarizeni pro
     * testovani prezentace.
     *
     * @param array $repre  Data reprezentanta z tabulky users
     * @param Zend_Controller_Request_Abstract  Request
     */
    public static function ipad_saveOneSlide($repre, Zend_Controller_Request_Abstract $req)
    {
        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();
        $response->setHeader('Content-type','application/json');

        $dataRaw = $req->getParam('data');

        // Overime, ze byla predana data k ulozeni
        if(empty($dataRaw)){
            $response->setHttpResponseCode(500);
            echo 'Error - no data sent for save.';
            return;
        }

        // Pokusime se dekodovat JSON data
        $slide = json_decode($dataRaw);

        if($slide === null){
            // Data se nepodarilo rozparsovat nebo jsou prazdna
            $response->setHttpResponseCode(500);
            echo 'Error - cannot parse JSON data.';

            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_saveOneSlide()',
                "Nepodarilo se rozparsovat JSON data.".
                "\nPredana data (POST): \n".
                print_r($_POST, true));

            return;
        }

        // Ziskame uzivatele - bud je v datech nebo automaticky pouzijeme uzivatele "Online Tester"
        $userId = null;
        if($slide->userId){
            $userId = $slide->userId;
        }
        else{
            $userArrays = Users::getUsersByAttrib('name', self::$testerName);
            if(count($userArrays)){
                $userArray = array_shift($userArrays);
                $userId = $userArray['id'];
            }
        }

        // Overime existenci uzivatele
        try{
            $user = Users::getUser($userId);

            // Prihlasime uzivatele
            Ibulletin_Auth::setUser($userId);
        }
        catch(Users_User_Not_Found_Exception $e){
            // Data se nepodarilo rozparsovat nebo jsou prazdna
            $response->setHttpResponseCode(500);
            echo 'Error - cannot find user in DB.';

            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_saveOneSlide()',
                'Nepodarilo se nalezt zadaneho uzivatele. '.
                "content_id:'$session->presentationId', slide_num:'$slide->id', user_id:'$userId'.".
                "data slidu: \n".print_r($slide, true));

            return;
        }

        // Pripravime content
        $content = Contents::get($slide->presentationId);

        // Pokud nebyl nalezen content, zalogujeme a koncime
        if(empty($content)){
            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_saveOneSlide()',
            'Nepodarilo se ziskat content zadaneho ID. '.
            "content_id:'$slide->presentationId', slide_num:'$slide->id', user_id:'$userId'.".
            "data slidu: \n".print_r($slide, true));
            return;
        }

        // Objekt pro ukladani odpovedi
        $questionsObj = new Questions($content['id'], $userId);

        $rawDataToLog = null;
        self::ipadSaveSlideData($content, $slide, $userId,
            Ibulletin_Stats::getInstance()->getPageViewsId(), $rawDataToLog, $questionsObj, $req);
    }


    /**
     * Saves data of one slide delivered from mobile inRep device. 
     * Preserves saving of the same data multiple times (except for test users)
     *
     * @param   Ibulletin_Content_Abstract  $content        Object of the content we are saving data to.
     * @param   stdClass                    $slide          Slide data in format defined by inRep protocol.
     * @param   int                         $userId         Id of the user data belongs to.
     * @param   int                         $pageViewsId    Id of the record from page_views.
     * @param   bool                        $rawDataToLog   Print RAW data to error log?
     * @param   Questions                   $questionsObj   Object for saving answers.
     * @param   Zend_Controller_Request_Abstract   $req     Request object.
     */
    public static function ipadSaveSlideData($content, $slide, $userId, $pageViewsId,
                                             &$rawDataToLog, Questions $questionsObj, $req)
    {
        // Pripravime si validator pro timestamp
        $isoTimestValid = new Ibulletin_Validators_TimestampIsoFilter;

        // Overime neprazdnost $slide->questions a pripadne osetrime
        if(!is_array($slide->questions)){
            if(!empty($slide->questions)){
                // questions obsahuje neco podivneho, radeji zalogujeme
                Phc_ErrorLog::warning('Ibulletin_Content_Indetail::ipad_sendUserData()',
                    'Data questions daneho slidu maji necekany obsah: "'.print_r($slide->questions, true).'".');
                $rawDataToLog = true;
            }

            $slide->questions = array();
        }

        // Zkusime mene strukturovane ulozit puvodni data odpovedi, ktera prisla JSONem pro pripad nejasnosti po ulozeni
        $questionsIndetailstatsA = array();
        $answersIndetailstatsA = array();
        foreach($slide->questions as $question){
            if(empty($question->id)){
                continue;
            }
            $questionsIndetailstatsA[] = $question->id;
            $answersIndetailstatsA[] = !empty($question->answer) ? json_encode($question->answer) : '';
        }
        $questionsIndetailstats = join('|', $questionsIndetailstatsA);
        $answersIndetailstats = join('|', $answersIndetailstatsA);


        //
        // UKLADANI
        //

        // Pripravime parametry pro ulozeni indetail_stats
        $params = array();
        $params['user_id'] = $userId;
        $params['slideon'] = !empty($slide->slideOn) ? $slide->slideOn : $slide->id; // HACK - nekdy neposilaji slideon
        $params['slideoff'] = $slide->id; // Slide off iPad neposila, proto vzdy pouzijeme slideId
        $params['slide_id'] = $slide->id;
        $params['answer'] = $answersIndetailstats;
        $params['question_id'] = $questionsIndetailstats;
        $params['time_total'] = $slide->timeTotal;
        $params['time_slide'] = $slide->timeSlide;
        $params['sound_on'] = @$slide->soundOn;
        $params['sound'] = @$slide->sound;
        $params['points'] = @$slide->points;
        $params['steps'] = @$slide->steps;
        $params['slide_group_id'] = @$slide->slideGroupId;
        $params['sequence'] = @$slide->sequence;
        $params['unique'] = @$slide->unique;
        if($isoTimestValid->isValid($slide->timestampSaved)){
            $params['timestamp'] = $slide->timestampSaved;
            $timestampSaved = $slide->timestampSaved;
        }
        else{
            $timestampSaved = null;
        }

        try{
            //
            // Zavolame ulozeni indetail_stats
            //
            $idsData = $content['object']->saveIndetailStats($req, $pageViewsId, $params, true);
            
            // Preskocime duplicitni data
            if($idsData === null){
                return;
            }
            
            $indetail_stats_id = $idsData['indetail_stats_id'];
        }
        catch(Exception $e){
            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                'Nastala chyba behem ukladani dat do indetail_stats. Puvodni vyjimka:'."\n$e");
            $rawDataToLog = true;
            return;
        }

        // Ulozeni odpovedi
        foreach((array)$slide->questions as $question){
            if(empty($question->id)){
                // Neni zadano ID otazky, nemmuzeme pokracovat
                Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                    "Nepodarilo se rozeznat id otazky z iPadu - content_id: '{$content['id']}', ".
                    "Data otazky: '".print_r($question, true)."'.");

                $rawDataToLog = true;
                continue;
            }

            $text = null;
            $answer = $question->answer;
            if($question->type == 'bool'){
                $type = 'b';
                $answer = (bool)$answer;
            }
            elseif($question->type == 'int'){
                $type = 'i';
                $answer = (int)$answer;
            }
            elseif($question->type == 'radio'){
                $type = 'r';
                $answer = (int)$answer;
            }
            elseif($question->type == 'checkbox'){
                $type = 'c';
                $answer = (array)$answer;
            }
            elseif($question->type == 'text'){
                $type = 't';
                $answer = (string)$answer;
                $text = $answer;
            }
            elseif($question->type == 'float'){
                $type = 'd';
                $answer = (double)$answer;
            }

            // Zkontrolujeme, jestli byl rozeznan typ
            if(!empty($type)){
                try{
                    $questionsObj->writeAnswer($question->id, $answer, $type, $text, $indetail_stats_id,
                        $slide->id, null, null, $pageViewsId, false, $timestampSaved);
                }
                catch(Exception $e){
                    Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                        "Nepodarilo se ulozit odpovedi odeslane z iPadu - content_id: '{$content['id']}', ".
                        "question_id: '$question->id', answer: '$question->answer'.\n Puvodni vyjimka: \n $e");
                    $rawDataToLog = true;
                }
            }
            else{
                // Nebyl rozpoznan typ odpovedi
                Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendUsersData()',
                    "Nepodarilo se rozeznat typ odpovedi z iPadu - content_id: '{$content['id']}', ".
                    "question_id: '$question->id', answer: '$question->answer'.");
                $rawDataToLog = true;
            }
        }
    }


    /**
     * Odesle jeden email z iPadu.
     * Vyzaduje na vstupu DataSetSendEmail.
     * 
     * 
     * Ipad zatim posila jen jeden text do emailu: inrepTxt_default
     *
     * @param array $repre  Data reprezentanta z tabulky users
     * @param Zend_Controller_Request_Abstract  Request
     */
    public static function ipad_sendEmail($repre, Zend_Controller_Request_Abstract $req)
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();
        $response->setHeader('Content-type','application/json');

        $dataRaw = $req->getParam('data');
        $checksum = $req->getParam('checksum');

        // Overime, ze byla predana data k ulozeni
        if(empty($dataRaw)){
            $response->setHttpResponseCode(500);
            echo 'Error - no data sent for save.';
            //$out->error = 'Error - no data sent for save.';
            //$json = json_encode($out);
            //echo $json;
            return;
        }
        // Overime, jestli data souhlasi s otiskem
        if(!empty($checksum) && md5($dataRaw) != $checksum){
            $response->setHttpResponseCode(500);
            echo 'Error - checksums don\'t match.';

            // Zalogujeme dodana data
            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendEmail()',
                "Dodany checksum ($checksum) neodpovida vypoctenemu checksumu (".md5($dataRaw).").".
                "\nPredana data (POST): \n".
                print_r($_POST, true));
            return;
        }

        // Pokusime se dekodovat JSON data
        $data = json_decode($dataRaw);

        if($data === null || !isset($data->email)){
            // Data se nepodarilo rozparsovat nebo jsou prazdna
            $response->setHttpResponseCode(500);
            echo 'Error - cannot parse JSON data.';

            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendEmail()',
                "Nepodarilo se rozparsovat JSON data, nebo data neobsahuji objekt 'email'.".
                "\nPredana data (POST): \n".
                print_r($_POST, true));

            return;
        }

        // Data ciloveho uzivatele
        try{
            $user = Users::getUser($data->email->userId);
        }
        catch(Users_User_Not_Found_Exception $e){
            $response->setHttpResponseCode(500);
            echo 'Error - user not found.';

            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendEmail()',
                "Uzivatel nebyl nalezen userId: '$data->email->userId'. Nelze poslat e-mail.".
                "\nPredana data (POST): \n".
                print_r($_POST, true));

            return;
        }

        // Data reprezentanta (hlavne kvuli overeni existence)
        try{
            $repre = Users::getUser($data->email->repreId);
        }
        catch(Users_User_Not_Found_Exception $e){
            $response->setHttpResponseCode(500);
            echo 'Error - representative not found.';

            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendEmail()',
                "Reprezentant nebyl nalezen. Nelze poslat e-mail.".
                "\nPredana data (POST): \n".
                print_r($_POST, true));

            return;
        }

        // EMAIL
        try{
            $email = Ibulletin_Email::getEmail($data->email->id);
        }
        catch(Ibulletin_Email_Exception $e)
        {
            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendEmail()',
                'Nebyl nalezen email pro odeslani z mobilni aplikace - id emailu: "'
                .$data->email->id.'", puvodni vyjimka:'."\n$e");
            return;
        }

        try{
            // Mailer
            $mailer = new Ibulletin_Mailer();

            // Pripravime tagy a jejich texty pro nahrazeni v mailu
            // Texts
            foreach ((array)$data->email->texts as $k => $v) {
                $mailer->getMailerTags()->addTag($k, $v);
            }

            // Resources
            if($data->email->resources){
                $mailer->getMailerTags()->addTag('resources', (array)$data->email->resources);
            }
            // Pripravime id repa do mailu pro pouziti k fotce repa
            $email->setRep($repre['id']);
            // Nastavime from podle nastaveni v configu (bud primo email repa, nebo nechame default inRepa,
            // pripadne nenastavujeme a pouzije se defaultni from pro maily)
            if(!empty($repre['email']) && $config->ipad->emailFromType == 'rep'){
                $name = trim($repre['degree_before'].' '.$repre['name'].' '.$repre['surname'].' '.$repre['degree_after']);
                $name = empty($name) ? $repre['email'] : $name;
                $email->setFrom(
                    $repre['email'],
                    $name
                );
            }
            // Fallback na spolecny inRep from email pokud je nastaveny
            elseif(($config->ipad->emailFromType == 'rep' || $config->ipad->emailFromType == 'common')
                && !empty($config->ipad->emailFrom))
            {
                $name = $config->ipad->emailFromName;
                $name = empty($name) ? $config->ipad->emailFrom : $name;
                $email->setFrom($config->ipad->emailFrom, $name);
            }

            $mailer->prepareSending();

            $db->beginTransaction();

            // zaradi se email do users_emails
            $ue_ids = $mailer->enqueueEmail($email->getId(), "id = ".$user['id'], $repre['id']);
            $ue_id = $ue_ids[0];
            
            $sel = $db->select()->from(array('ue' => 'users_emails'))->where('ue.id = ?', $ue_id);
            //user email pro doplneni token
            $user_email = $db->fetchRow($sel);
            
            // metoda sendMail potrebuje nastaveny tyto vlastnosti.
            $user['user_id'] = $user['id'];
            $user['users_emails_id'] = $ue_id;
            $user['email_id'] = $data->email->id;
            $user['token'] = $user_email['token'];
            // nahrazeni znacek v tele mailu
            $mailer->getMailerTags()->parseTags($user, $email);
            // odeslani mailu
            $mailer->sendMail($user, $email);

            $db->commit();
        }
        // Odchytavame vsechno, nahradime HTTP 500
        catch(Exception $e){
            $db->commit();
            // Data se nepodarilo rozparsovat nebo jsou prazdna
            $response->setHttpResponseCode(500);
            echo 'Error - cannot send email, internal server error.';

            Phc_ErrorLog::error('Ibulletin_Content_Indetail::ipad_sendEmail()',
                'Nastala chyba pri odesilani mailu z mobilniho zarizeni na adresu "'.$user['email'].
                '", email_id: "'.$data->email->id.'". Puvodni vyjimka: '."\n $e");

            return;
        }
    }

    /**
     * Najde last modified pro data uzivatele. Ty se odvijeji jednak od modifikaci v tabulce users
     * a jednak od modifikaci v existenci a prirazeni prezentaci k repum.
     *
     * @return Zend_Date    Last modified pro data uzivatele
     */
    public static function _ipad_getUsersLastModifiedTime($repreId)
    {
        $db = Zend_Registry::get('db');

        // Zjistime posledni zmenu v users tohoto reprezentanta
        // Bereme v potaz i posledni zmeny v odpovedich uzivatele
        // Zamerne nefiltrujeme deleted, protoze pokud uzivatele smazeme, chceme nahrat do iPadu data bez smazanych uzivatelu
        $sel = new Zend_Db_Select($db);
        $sel->from(array('u' => 'users'), array('lastModified' => 'coalesce(greatest(max(last_changed), max(ua.timestamp)), (SELECT max(added) FROM users))',
                'currentTimestamp' => new Zend_Db_Expr('current_timestamp')))
            ->join(array('ur' => 'users_reps'), 'ur.user_id = u.id', array())
            ->joinLeft(array('ua' => 'users_answers'), 'ua.user_id = u.id', array())
            ->where('ur.repre_id = ?', $repreId)
            //->order(array('u.last_changed DESC'))
            ;
        // Pokud neni repre test, vyfiltrujeme uzivatele podle tabulky users_vf
        //if(!$repre['test']){
            //$sel->join(array('uvf' => 'users_vf'), 'uvf.id = u.id', array());
        //}
        $row = $db->fetchRow($sel);
        // Porovname s posledni modifikaci configu - kvuli prenosu nastaveni jako userProfileAttrNames
        // a s posledni modifikaci v contentech kvuli poslani dat prirazeni
        $lastModifiedUserDb = new Zend_Date($row['lastModified'], Zend_Date::ISO_8601);
        $lastModifiedConfig = Utils::getConfigLastModified();
        // Prezentace/contenty a pages (pages kvuli vazbam na segmenty a naslednemu prirazeni prezentaci uzivatelum dle segmentu)
        $presSel = "SELECT greatest('2000-01-01'::timestamp, max(c.created), max(c.deleted), max(c.changed), max(p.deleted), max(p.changed))
            FROM content c
            LEFT JOIN content_pages cp ON cp.content_id = c.id
            LEFT JOIN pages p ON p.id = cp.page_id
            WHERE c.class_name = 'Ibulletin_Content_Indetail'";
        $lastModifiedPresentations = new Zend_Date($db->fetchOne($presSel), Zend_Date::ISO_8601);

        $lastVisitsSel = self::_ipad_getLastVisitsSel();
        $lastVisitsMax = new Zend_Db_Select($db);
        $lastVisitsMax->from(array('foo' => new Zend_Db_Expr('('.$lastVisitsSel.')')), array('timestamp' => 'max(timestamp)'));
        $lastVisitsMaxDate = new Zend_Date($db->fetchOne($lastVisitsMax), Zend_Date::ISO_8601);

        $lastModified = max(array($lastModifiedConfig, $lastModifiedUserDb, $lastModifiedPresentations, $lastVisitsMaxDate));

        return $lastModified;
    }
    
    
    /**
     * Metoda vygeneruje certifikat uzivatele dle splnenych podminek v predanem contentu
     * 
     */
    public function certificateService($req) {

        $contentId = $req->getParam('contentid');

        if (!$contentId) {
            echo "Content ID missing";
            return;
        }

        $pageViewsId = $req->getParam('pageviewsid');


        $db = Zend_Registry::get('db');

        $userId = null;

        if ($pageViewsId) {
            $select = $db->select()->from(array('pv' => 'page_views'), array())
                    ->join(array('s' => 'sessions'), 's.id = pv.session_id', array('user_id'))
                    ->where('pv.id = ?', $pageViewsId);

            $userId = $db->fetchOne($select);
        }


        if (empty($userId)) {

            Phc_ErrorLog::warning('Ibulletin_Content_Indetail::certificateService()', 'User_id for generate certificate was not found. The user_id was neither in $params array nor by searching DB according page_views_id. ' .
                    "page_views_id = '$pageViewsId', params:\n" . print_r($req->getParams(), true));
        }
        
        $users[0] = $userId;

        try {

            $certPath = Certificates::getPath($contentId, $users[0]);
            
            $config = Zend_Registry::get('config');
            
            if (!$certPath && $config->caseStudies->certificate->allowIndetailGenerate) {
               $certPath = Certificates::generate($contentId, $users);
            } 
            
            echo json_encode(array('certPath'=>$certPath,'error'=>'')); 
            
        } catch (Exception $ex) {
            echo json_encode(array('error'=>$ex->getMessage()));
        }
        
        
    }

}
