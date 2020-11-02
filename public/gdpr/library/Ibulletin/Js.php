<?php
/**
 * iBulletin - Js.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


/**
 * Singleton
 * Trida, ktera napomaha k jednodussi praci s JavaScriptem v Inbulletinu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Js
{
    /**
     * Instance tridy.
     *
     * @var Ibulletin_Js
     */
    private static $_instance = null;

    /**
     * ID ruznych elementu pro pridelovani pomoci klice JsId helperem
     *
     * @var array
     */
    private $_ids = array();

    /**
     * Javascript code ktery se nasledne pouzije pri renderu html head
     *
     * @var string
     */
    private $_jscode = '';

    /**
     * Javascript code ktery se nasledne pouzije pri renderu html head
     * Toto je cast JS, ktera ma byt vlozena na konec HEAD za vsechny ostatni JS
     *
     * @var string
     */
    private $_jscodeEnd = '';

    /**
     * Tela a pripadne jmena funkci
     *
     * @var array - array(jmeno_funkce => array('name' => string jmeno_funkce,
     *                                          'code' => string telo_funkce,
     *                                          'onload' => bool,
     *                                          'params' => array))
     */
    private $_functions = array();

    /**
     * @var array Pole s nazvy formularu, pro ktere jiz existuje onclick funkce
     *            array('id_formulare' => 'jmeno_js_fce')
     */
    private $_hrefFormSubmitForms = array();

    /**
     * @var array Pole s nazvy formularu, pro ktere jiz existuje onchange funkce
     *            array('id_formulare' => 'jmeno_js_fce')
     */
    private $_onchangeSubmitForms = array();

    /**
     * @var string
     */
    public static $jsRoot = 'pub/scripts/';

    /**
     * @var string  Jmeno cookie, ve ktere mame ulozene jiz prednactene SWF
     */
    public $preloadedCookieName = 'preloadswf_doneSwfs';

    /**
     * @var array   Pole obsahujici jiz nactene swf soubory - napriklad ty,
     *              ktere se na aktualni strance pouzivaji, aby nebyly znovu natahovany preloadem.
     */
    public $loadedSwfs = array();

    /**
     * @var array Pole obsahujici cesty k souborum s pocatkem v pub/scripts/,
     *            ktere obsahuji JS k prilozeni do teto stranky. Nacitano jako prvni -
     *            vkladaji se sem soubory z configu.
     */
    public $jsFilesFirst = array();

    /**
     * @var array Pole obsahujici cesty k souborum s pocatkem v pub/scripts/,
     *            ktere obsahuji JS k prilozeni do teto stranky. Nacitano pozdeji.
     */
    public $jsFilesLast = array();
    
    /**
     * @var array Pole obsahujici cesty k souborum s pocatkem v pub/scripts/,
     *            ktere jiz byly v renderu pridane k nacteni a tedy neni treba je nacitat znovu.
     *            Soubory k nacteni renderujeme na dvakrat v HTML head a na konci HTML.
     */
    public $jsFilesLoaded = array();

    /**
     * @var StdClass    Atributy teto tridy budou vlozeny jako globalni promenne v JS.
     *                  Umi i pole, ale jsou ulozena pouze s ciselnymi klici. Zahodi klice a zpracuje
     *                  jen jeden rozmer.
     */
    public $vars = null;

    /**
     * @var bool    Jsme v developer modu, kdy jsou JS soubory predany tak jak jsou a
     *              ne zapakovany do jednoho??
     */
    //public $developerMode = false;


    /**
     * Vrati existujici instanci Ibulletin_Js nebo vytvori novou a tu vrati.
     *
     * @return Ibulletin_Js Jedina instance Ibulletin_Js
     */
    public static function getInstance()
    {
        if(self::$_instance === null){
            self::$_instance = new Ibulletin_Js();
        }

        return self::$_instance;
    }

    /**
     * Nastavi unikatni ID pro toto nacteni stranky k pouziti v session a v session jej zaregistruje.
     * Nepouzivame....
     */
    public function __construct()
    {
        $this->vars = new StdClass();
        /*
        $rsg = new Ibulletin_RandomStringGenerator();
        $session = Zend_Registry::get('session');

        while(1){
            $id = $rsg->get(10);
            $sesName = 'loadId_'.$id;
            if(!isset($session->$sesName)){
                break;
            }
        }

        $this->loadId = $id;
        $session->$sesName = array();
        $session->setExpirationSeconds(100, $sesName);
        //*/
    }


    /**
     * Ulozi si informaci o id a jeho klici, aby dane ID mohlo byt prideleno
     * ve view JsId helperem. Pod danym klicem muze byt vice ID - patrici k jednomu
     * objektu.
     *
     * @param string    Klic podle ktereho bude id zase vraceno (treba id formulare)
     * @param string    Pripadne id elementu, ktere je jiz pridelene
     * @return string   Vygenerovane nebo zadane id
     */
    private static function _addId($key, $id = null)
    {
        $inst = self::getInstance();

        $used = true;
        if($id === null){
            // Vytvorime nove id z klice
            if(isset($inst->_ids[$key])){
                $num = count($inst->_ids[$key]);
            }
            else{
                $num = 0;
            }

            $id = $key.'_'.$num;
            $used = false;
        }

        $inst->_ids[$key][] = array('id' => $id, 'used' => $used);

        return $id;
    }


    /**
     * Pripravi prednacteni SWF z konfigu. Pozor, je treba aby existoval element
     * s nazvem sandbox, do ktereho se oteviraji swf pri prednacitani. Neprednacita swf, ktera jiz byla
     */
    public static function preloadSwfs()
    {
        $inst = self::getInstance();
        $session = Zend_Registry::get('session');
        $urlHlpr = new Zend_View_Helper_BaseUrl();

        $config = Zend_Registry::get('config');

        // Nacteme cookie s pripadnymi jiz preloadnutymi swf
        if(empty($session->{$inst->preloadedCookieName})){
            $session->{$inst->preloadedCookieName} = array();
        }
        $frontController = Zend_Controller_Front::getInstance();
        $req = $frontController->getRequest();
        $cookies = $req->getCookie($inst->preloadedCookieName);
        $cookieA = explode('|',$cookies);
        foreach($cookieA as $cookie){
            // Ukladame jen soubory, ktere jeste v session nemame
            if(!empty($cookie) && $cookie != 'null' &&
                !in_array($cookie, $session->{$inst->preloadedCookieName}))
            {
                array_push($session->{$inst->preloadedCookieName}, $cookie);
            }
        }
        // Smazeme cookie (cookie slouzi jen pro predani infa o preloadnutych swf na server
        if($cookies){ // Mazeme jen pokud nejaka cookie dorazila, jinak nefunguje predani
            setcookie($inst->preloadedCookieName, null, null, '/');
        }

        $files = $config->swfpreload->file;
        if(!empty($files)){
            foreach($files as $file){
                $path = $urlHlpr->baseUrl($file);                
                if(!in_array($path, $inst->loadedSwfs) &&
                    !in_array($path, $session->{$inst->preloadedCookieName}))
                {
                    $inst->preloadSwfFile($path);
                }
                // Pridame do jiz preloadnutych i ty, ktere byli nyni regulerne loadnuty
                elseif(in_array($path, $inst->loadedSwfs)){
                    array_push($session->{$inst->preloadedCookieName}, $path);
                }
            }
        }

        //var_dump($cookies);
        //var_dump($session->{$inst->preloadedCookieName});
    }


    /**
     * Vrati id elementu podle klice
     *
     * @param string    Klic
     */
    public static function getId($key)
    {
        $inst = self::getInstance();

        if(!isset($inst->_ids[$key]) || !is_array($inst->_ids[$key])){
            return null;
        }

        $id = null;
        foreach($inst->_ids[$key] as $elem_key => $id_a){
            if(!$id_a['used']){
                $id = $id_a['id'];
                $inst->_ids[$key][$elem_key]['used'] = true;
                break;
            }
        }

        return $id;
    }


    /**
     * Prida prosty JS kod k ostatnimu kodu - kod musi byt uceleny,
     * jinak muze narusit ostatni JS kod
     *
     * @param string    JS kod
     * @param bool      Pridat tento JS az na konec HEAD za vsechny ostatni JS?
     */
    public static function addPlainCode($code, $toEnd = false)
    {
        $inst = self::getInstance();
        $code = "\n" . $code; 
        if(!$toEnd){
            $inst->_jscode .= $code;
        }
        else{
            $inst->_jscodeEnd .= $code;
        }
    }


    /**
     * Prida JS kod k nejakemu elementu, nebo elementum vykonany pri dane akci.
     *
     * @param array     ID HTML elementuu - pole
     * @param string    JS akce (click, mouseOver, mouseOut, change...)
     *                  akce musi byt bez pocatecniho "on"!!!
     * @param string    JS kod, ktery se ma na dany element a akci navesit
     * @param string    Misto kodu a vytvareni nove funkce pouzit existujici funkci se jmenem...
     * @return string   Jmeno JS funkce, ktera bude vykonana pri udalosti
     */
    public static function addOnActionCode($elements, $action, $code, $func_name = null)
    {
        $inst = self::getInstance();
        $addOnAction_code = '';

        // Pokud jeste nebyla pridana js funkce addOnAction, pridame ji
        if(!isset($inst->_functions['addOnAction'])){
            $addOnAction_code .= "
obj = document.getElementById(element);
if(typeof func_name == 'string'){
    var t = (function(){return this;})();
    var fnc = t[func_name];
}
else{
    var fnc = func_name;
}
if(!obj || !fnc){
    return;
}
if(action == 'change' && obj.nodeName == 'INPUT' &&
   (obj.type == 'radio' || obj.type == 'checkbox')){
       action = 'click';
}
". // pripravime funkci do eventu tak, aby respektovala return false pro zruseni default akce
"var evFnc = function(e){
    if(!fnc()){
        if( e.preventDefault ) { e.preventDefault(); }
        e.returnValue = false;
    }
}
if(obj != null){
    if(obj.addEventListener){
        obj.addEventListener(action, evFnc, false);
    }
    else if(obj.attachEvent){
        obj.attachEvent('on'+action, evFnc);
    }
}
                ";
            $inst->addFunction($addOnAction_code, 'addOnAction', array('element', 'action', 'func_name'));
        }

        if($func_name === null){
            $func_name = $inst->addFunction($code, null, array('e'));
        }

        $onLoad_code = "var obj;";
        foreach($elements as $element){
            $onLoad_code .= "addOnAction('$element', '$action', $func_name);";
        }

        $inst->addFunction($onLoad_code, 'loadEvent', array(), true);

        return $func_name;
    }


    /**
     * Prida novou JS funkci, pokud jiz fce s danym jmenem funguje,
     * je tento kod pridan k existujicimu a parametry jsou rozsirene o zadane nove parametry
     *
     * @param string    JS kod - pouze telo funkce
     * @param string    Jmeno funkce
     * @param array     Pole parametru funkce
     * @param bool      Je toto funkce pro spusteni po nacteni stranky?
     * @param bool      Je toto funkce pro spusteni po ukonceni stranky?
     * @return string   Jmeno funkce, onload funkce bez zadaneho jmena vraceji pouze cislo,
     *                  ktere neni nazvem funkce
     */
    public static function addFunction($code, $name = null, $params = array(), $isOnLoad = false, $isOnUnload = false)
    {
        $inst = self::getInstance();

        if($name === null && !$isOnLoad){
            $name = 'func_'.count($inst->_functions);
            $key = $name;
        }
        elseif($name === null){
            $key = count($inst->_functions);
        }
        else{
            $key = $name;
        }

        if(isset($inst->_functions[$key])){
            $code = $inst->_functions[$key]['code'].$code;
            $params = array_merge($inst->_functions[$key]['params'], $params);
        }

        $inst->_functions[$key] = array('name' => $key,
                                        'code' => $code,
                                        'params' => $params,
                                        'onload' => $isOnLoad,
                                        'onunload' => $isOnUnload,);

        return $key;
    }

    /**
     * Zjisti, jestli bylo jiz vsechno vyrenderovano, nebo zda je treba znovu volat render.
     * @return  bool    Je treba znovu renderovat JS? 
     */
    public static function needsRender()
    {
        $inst = self::getInstance();
        
        if(!empty($inst->_functions))
            return true;
        if(!empty($inst->jsFilesFirst) || !empty($inst->jsFilesLast))
            return true;
        if(!empty($inst->vars))
            return true;
        if(!empty($inst->_jscode))
            return true;
        
        return false;
    }

    /**
     * Vrati javascript kod nagenerovany vsemi metodami z teto tridy,
     * prida kod pro spusteni vseho potrebneho na onload.
     *
     * @param bool      Jedna se o prvni nacteni v tomto HTML?
     *                  Rozhoduje po pridani pomocnych JS funkci.
     * 
     * @return string   JS kod nagenerovany touto tridou nebo null, pokud neni co renderovat.
     */
    public static function render($firstLoad = true)
    {
        $inst = self::getInstance();
    
        # Kod pro pridani funkci s onload a on unload
        if($firstLoad){
            $addLoadEvent =
           "function addLoadEvent(func) {
              var oldonload = window.onload;
              if(typeof func == 'string'){
                var funcStr = func;
                func = function () {
                    eval(funcStr + '();');
                };
              }
              if (typeof window.onload != 'function') {
                window.onload = func;
              } else {
                window.onload = function() {
                  oldonload();
                  func();
                }
              }
            }
            function addUnloadEvent(func) {
              var oldonunload = window.onunload;
              if(typeof func != 'function'){
                var funcStr = func;
                func = function () {
                    eval(funcStr + '();');
                };
              }
              if (typeof window.onunload != 'function') {
                window.onunload = func;
              } else {
                window.onunload = function() {
                  oldonunload();
                  func();
                }
              }
            }
            ";
        }
        else{
            $addLoadEvent = '';
        }

        # Pridani vsech promennych z $this->vars do JS globals
        $varsStr = self::getRenderedVars();

        $functions = '';
        # Nacteni kodu funkci a pridani funkci s onload do onload
        foreach($inst->_functions as $key => $f_array){
            $params_str = join(',', $f_array['params']);
            if(!is_numeric($f_array['name'])){
                // Kdyz ma fce jmeno, vytvorime ji s danym jmenem

                $functions .= "\n var ".$f_array['name']." = function($params_str){
                    ".$f_array['code']."
                    }";

                if($f_array['onload']){
                    $functions .= "\n addLoadEvent(".$f_array['name'].");";
                }
                if($f_array['onunload']){
                    $functions .= "\n addUnloadEvent(".$f_array['name'].");";
                }
            }
            elseif($f_array['onload'] || $f_array['onunload']){
                // Kdyz fce nema jmeno a ma onload, ulozime ji bezejmenne do onload nebo onunload
                if($f_array['onload']){
                    $functions .= "\n addLoadEvent(function($params_str){
                        ".$f_array['code']."
                    });";
                }
                if($f_array['onunload']){
                    $functions .= "\n addUnloadEvent(function($params_str){
                        ".$f_array['code']."
                    });";
                }
            }

        }
        
        // Vratime kod obaleny CDATA nebo null
        $jsCode = $varsStr.$addLoadEvent.$functions.$inst->_jscode;
        if(!empty($jsCode)){
            return "\n".'//<![CDATA['."\n"
                .$jsCode."\n"
                ."\n".'//]]>';
        }
        else{
        	return null;
        }
    }

    
    /**
     * Vrati javascript kod, ktery ma byt umisten na konec HEAD tagu za vsechny ostatni JS.
     *
     * @return string   JS kod patrici na konec HEAD tagu
     */
    public static function renderEnd()
    {
        $inst = self::getInstance();
        return $inst->_jscodeEnd;
    }
    
    /**
     * Renderuje nazev souhrnneho JS souboru do pro HEAD.
     * 
     * @return String/null  Retezec s cestou k souhrnnemu JS nebo null, pokud nejsou zadne soubory. 
     */
    public static function renderAllJsFilesNameForHead()
    {
        $inst = self::getInstance();
        $jsUrl = null;
        $urlHlpr = new Zend_View_Helper_Url();
        
        if(!empty($inst->jsFilesFirst) || !empty($inst->jsFilesLast)){            
            $jsUrl = $urlHlpr->url(array('id' => $inst->getJsFilesId()), 'javascriptcode');
        }
        
        return $jsUrl;
    }
    
    /**
     * Vyrenderuje do JS pozadovane globalni promenne z $this->vars.
     * 
     * @return  String JS obsahujici definice pozadovanych globalnich promennych.
     */
    public static function getRenderedVars()
    {
        $inst = self::getInstance();
        
        $varsA = get_object_vars($inst->vars);
        $varsStr = '';
        foreach($varsA as $key => $val){
            if(is_array($val)){
                $val = 'new Array("'.str_replace("'", "\\'", join('","', $val)).'")';
            } elseif (is_bool($val)) {
                $val = $val ? 'true' : 'false';                             
            } elseif (is_string($val)) {
                $val = "'".str_replace("'", "\\'", $val)."'";                               
            } elseif (is_null($val)) {
                $val = 'null';
            }
            // int, float is treated as-is
            // append to string
            $varsStr .= 'var '.$key.' = '.$val.";\n";
        }
        
        return $varsStr;
    }


    /**
     * Pripravi Java Script pro odeslani formulare pomoci odkazu HTML anchor.
     * V pripade nezadani ID HTML anchoru se vygeneruje vlastni ID,
     * ktere se do HTML vklada pomoci JsId Helperu
     *
     * @param string    ID html tagu <form> formulare, ktery chceme submitovat.
     * @param string    ID pouzite v html u elementu anchor, na ktery se ma
     *                  submit formu navazat.
     */
    public static function hrefFormSubmit($form_id, $anchor_id = null)
    {
        $inst = self::getInstance();

        // Zjistime, jestli jiz neexistuje onclick fce pro tento form k znovupouziti
        $func_name = null;
        if(isset($inst->_hrefFormSubmitForms[$form_id])){
            $func_name = $inst->_hrefFormSubmitForms[$form_id];
        }

        // Ziskame id a udelame zaznam pro pripadne pridavani id do HTML
        $anchor_id = self::_addId($form_id, $anchor_id);

        $code = "
        var obj_form = document.getElementById('$form_id');
        if(obj_form){
            var event = e || window.event;
            var element = event.target || event.srcElement;
            if(element.href){
                var input = document.createElement('input');
                input.setAttribute('type','hidden');
                input.setAttribute('name','redirect_to_url');
                input.setAttribute('value',element.href);
                obj_form.appendChild(input);
            }
            obj_form.submit();
            if(event.cancelable){
                event.preventDefault();
            }
            return false;
        }
        ";

        $func_name = $inst->addOnActionCode(array($anchor_id), 'click', $code, $func_name);

        // Odlozime si id formu a jeho onclick funkci do pole
        $inst->_hrefFormSubmitForms[$form_id] = $func_name;

    }

    /**
     * Pripravi Java Script pro odeslani formulare pomoci onchange action handleru.
     *
     * @param string    ID html tagu <form> formulare, ktery chceme submitovat.
     * @param string    ID pouzite v html u elementu formulare, na ktery se ma
     *                  submit formu navazat.
     */
    public static function onchangeFormSubmit($form_id, $elem_id)
    {
        $inst = self::getInstance();

        // Zjistime, jestli jiz neexistuje onchange fce pro tento form k znovupouziti
        $func_name = null;
        if(isset($inst->_onchangeSubmitForms[$form_id])){
            $func_name = $inst->_onchangeSubmitForms[$form_id];
        }

        // Ziskame id a udelame zaznam pro pripadne pridavani id do HTML
        $elem_id = self::_addId($form_id, $elem_id);

        $code = "
        var obj_form = document.getElementById('$form_id');
        if(obj_form){
            obj_form.submit();
        }
        ";

        $func_name = $inst->addOnActionCode(array($elem_id), 'change', $code, $func_name);

        // Odlozime si id formu a jeho onchange funkci do pole
        $inst->_onchangeSubmitForms[$form_id] = $func_name;
    }

    /**
     * Skryje HTML element pri nacteni nastavenim display:none. Hodi se
     * pro odesilaci tlacitka formularu, ktere maji onchange odesilani -
     * funkce samozrejme skryva pouze pri funkcnim JS.
     *
     * @param string    ID html tagu elementu, ktery se ma skryt.
     */
    public static function hideElem($elem_id)
    {
        $inst = self::getInstance();

        $code = "var obj_elem = document.getElementById('$elem_id');
        if(obj_elem){obj_elem.style.display = 'none';}";

        $inst->addFunction($code, null, array(), true);
    }

    /**
     * Zobrazi HTML element pri nacteni dokumentu nastavenim display:block.
     *
     * @param string    ID html tagu elementu, ktery se ma zobrazit.
     */
    public static function showElem($elem_id)
    {
        $inst = self::getInstance();

        $code = "var obj_elem = document.getElementById('$elem_id');
        obj_elem.style.display = 'block';";

        $inst->addFunction($code, null, array(), true);
    }

    /**
     * Prida k prednacteni SWF soubor. Soubory se nacitaji do divu s ID sandbox,
     * ktery musi byt nekde na strance, jinak se prednacteni neprovede. Prednacteni je vhodne
     * pouzivat pouze na jine strance, nez je vlastni flash - treba registracni stranka a podobne.
     *
     * @param string $swfFile   Soubor SWF k prednacteni.
     */
    public static function preloadSwfFile($swfFile)
    {
        $inst = self::getInstance();

        // Nacteme JS preloaderu SWF
        $inst->addJsFile('preloadswf.js');
        $inst->addJsFile('swfobject.js');

        $code = "if(preloadswf != undefined){preloadswf.load('$swfFile');}";

        // Pridame kod jako funkci pro spusteni po nacteni stranky
        $inst->addFunction($code, null, array(), true);

    }

    /**
     * Prida JS soubor k nacteni se strankou.
     *
     * @param string $file Muze byt bud soubor z self::jsRoot nebo z jakehokoli adresare,
     *              nejprve je zkousena existnece souboru tak jak je zadan v self::jsRoot
     * @param bool $first Ma byt soubor pridan na zacatek? Pouzivame pro ridavani souboruu z config.ini
     */
    public static function addJsFile($file, $first = false)
    {
        $inst = self::getInstance();
        
        // Jiz nactene soubory uplne preskocime
        if(in_array($file, $inst->jsFilesLoaded)){
            return;
        }
        
        if($first && in_array($file, $inst->jsFilesLast)){
            $key = array_search($file, $inst->jsFilesLast);
            unset($inst->jsFilesLast[$key]);
        }
        if(!in_array($file, $inst->jsFilesFirst) && !in_array($file, $inst->jsFilesLast)){
            if($first){
                $inst->jsFilesFirst[] = $file;
            }
            else{
                $inst->jsFilesLast[] = $file;
            }
        }
    }


    /**
     * Vypise na vystup JS vsech souboru JS k nacteni s touto strankou.
     * Pozor! Ukoncuje SESSION!!
     *
     * @param string $id ID nacteni stranky pro ktere se ma vypsat spojeny JS soubor.
     */
    public static function renderAllJsFilesCode($id)
    {
        $inst = self::getInstance();
        $fc = Zend_Controller_Front::getInstance();
        $response = $fc->getResponse();

        // Koren JS souboruu
        $jsRoot = self::$jsRoot;
        require_once('Zend/Db/Statement/Exception.php');
        $session = Zend_Registry::get('session');
        $sesVarName = $id;
        if(isset($session->$sesVarName)){
            $files = $session->$sesVarName;
        }
        else{
            return;
        }
        
        // Najdeme cas posledni modifikace nejnovejsiho souboru
        $lastModTime = 0;
        foreach($files as $key => $file){
            if(!file_exists($jsRoot.$file)){
                if(!file_exists($jsRoot.$file)){
                    continue;
                }
            }
            else{
                // Pridame k souboru root cesty, kdyz je to potreba
                $file = $jsRoot.$file;
                $files[$key] = $file;
            }
            
            $time = filemtime($file);
            if($time > $lastModTime){
                $lastModTime = $time;
            }
        }

        // Porovname s casem zadanym v pozadavku a pripadne vratime HTTP 304 Not modified
        /* Nepouzivame, misto toho mame cache
        $ifModifiedSince = $fc->getRequest()->getHeader('If-Modified-Since');
        if(strtotime($ifModifiedSince) >= $lastModTime && TEST != 0 && false){ // TEST - 304 se nesmi posilat pokud je zapnuta cache
            $response->setHttpResponseCode(304);
            $response->sendHeaders();
            exit;
        }
        //*/

        // Ukoncime session, aby k nim mohly pristupovat jine behy skriptu
        session_write_close();

        $response->setHeader('Expires', gmdate('D, d M Y H:i:s', time()+3800).' GMT', true);
        $response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $lastModTime).' GMT', true);
        $response->setHeader('Cache-Control', 'public', true);
        $response->setHeader('Cache-Control', 'max-age=3800');
        $response->setHeader('Pragma', '', true);
        $response->setHeader('Content-Type', 'application/x-javascript', true);
        $response->sendHeaders();


        foreach($files as $file){
            if(!file_exists($file)){
                continue;
            }
            $fp = fopen($file, 'r');
            fpassthru($fp);
            fclose($fp);
            echo ";\n";
        }
        // Smazeme ID v teto instanci objektu, aby nedochazelo k chybam v destruktoru
        // protoze do session se uz neda zapisovat.
        $inst->_jsFilesId = null;

        //unset($session->$sesVarName);
    }

    /**
     * Vrati jsfilesID podle aktualne navkladanych JS souboruu.
     *
     * @return string   jsFilesId aktualni stranky podle
     */
    public function getJsFilesId()
    {
        $inst = self::getInstance();

        $files = array_merge($inst->jsFilesFirst, $inst->jsFilesLast);
        sort($files);
        $md5 = md5(serialize($files));

        $inst->_jsFilesId = 'jsfiles_'.$md5;

        return $inst->_jsFilesId;
    }

    /**
     * Ulozi data do session a vycisti objekt pro pripadne JS soubory dodane az po odeslani
     * HTML header skriptu.
     * 
     * Pozor, nesmi byt volana drive, nez je vse vyrenderovano, vcetne url souboru souhrnneho JS!
     */
    public static function saveToPhpSession()
    {
        $inst = self::getInstance();

        $session = Zend_Registry::get('session');
        if(!empty($inst->_jsFilesId)){
            $sesVarName = $inst->_jsFilesId;
            $session->$sesVarName = array_merge($inst->jsFilesFirst, $inst->jsFilesLast);
            //$pom = array_merge($inst->jsFilesFirst, $inst->jsFilesLast);
            //print_r($pom);
        }
        
        
        // Ulozime soubory, ktere uz jsou nactene
        $inst->jsFilesLoaded = array_merge($inst->jsFilesFirst, $inst->jsFilesLast);
        
        // Vycistime objekt
        $inst->jsFilesFirst = array();
        $inst->jsFilesLast = array();
        $inst->_jscode = null;
        $inst->_jscodeEnd = '';
        $inst->_functions = array();
        $inst->_jsFilesId = null;
        $inst->vars = new stdClass;
    }
    
    
    /**
     * Pri zniceni tridy ulozi pole s informacemi o JS souborech k nacteni u teto stranky
     */
    public function __destruct(){
        self::saveToPhpSession();
    }
}

