<?php
/**
 * iBulletin - Player/Js.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

/**
 * JavaScript funkcionality potrebne pro provoz Flash prehravacu a webcastu.
 * 
 * Interni funkce prehravacu
 * 
 * AVPlayer (audio/video přehrávač):
 * 
 * reloadSource(url) - nahraje nový zdroj dat; parametr je URL odkaz na nové konifgurační XML
 * releasePlayer() - dealokuje interně používané zdroje; mělo byse volat při ukončení stránky zejména kvůli správnému ukončení videa 
 * receiveEvent(event) - doručí událost do dané komponenty; události mohou být vytvořené kdekoliv, v současné době jsou to jen ty zasílané při synchronizaci
 * getPlayhead() - vrátí aktuální pozici hlavy přehrávače v sekundách; v případě nevalidního stavu vrátí -1 
 * setPlayhead() - nastaví aktuální pozici hlavy přehrávače; parametr je v sekundách
 * commandPlay() - spustí přehrávání média od aktuální pozice hlavy přehrávače
 * commandPause() - nastaví přehrávač do stavu "pause"
 *  
 * PTPlayer (přehrávač prezentací)
 * 
 * reloadSource(url) - nahraje nový zdroj dat; parametr je URL odkaz na nové konifgurační XML
 * release() - dealokuje interně používané zdroje; mělo byse volat při ukončení stránky 
 * receiveEvent(event) - doručí událost do dané komponenty; detail viz. stejná funkce výše
 * getSlide() - vrátí aktuálně zobrazený (a/nebo vybraný) snímek prezentace; vrátí -1 pokud není prezentace nahraná
 * setSlide(slide) - nastaví nový aktální snímek; toto nastavení automaticky nepřepne zobrazný mód
 * getCount() - vrátí celkový počet snímků v dané prezentaci; vrátí 0 pokud není prezentace nahraná
 * setViewMode(viewMode) - přepíná mezi mód zobrazení; validní hodnoty jsou "modePageView" a "modeTileView"
 * 
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Player_Js
{
    /**
     * Instance tridy.
     *
     * @var Ibulletin_Player_Js
     */
    private static $_instance = null;
    
    /**
     * @var Ibulletin_Js    Objekt JS, ktery zpracovava vsechny JS funkce a renderuje je. 
     */
    var $js = null;
    
    /**
     * @var bool    Jsou nacteny soubory s kodem pro JS prehravacu?
     */
    var $_isJsLoaded = false;
    
    
    /**
     * Vrati existujici instanci Ibulletin_Player_Js nebo vytvori novou a tu vrati.
     *
     * @return Ibulletin_Player_Js Jedina instance Ibulletin_Player_Js
     */
    public static function getInstance()
    {
        if(self::$_instance === null){
            self::$_instance = new Ibulletin_Player_Js();
        }
        
        return self::$_instance;
    }
    
    /**
     * Pripravi k pouziti instanci Ibulletin_Js
     * 
     */
    public function __construct()
    {
        $this->js = Ibulletin_Js::getInstance();
    }
    
    
    /**
     * Prida k nacteni soubory s JS kodem k pouzivani s prehravaci pokud jiz neni nacten.
     */
    public static function loadJsFiles()
    {
        $inst = self::getInstance();
        if(!$inst->_isJsLoaded){
            // Pridame JS prehravace k nacteni
            $inst->js->addJsFile('swfobject.js');
            $inst->js->addJsFile('Player/incast.js');
        }
    }
    
    
    /**
     * Pripravi pro spousteni prehravac videa.
     * 
     * @param string $playerHtmlElemId  ID HTML elementu prehravace.
     * @param string $source            URL k SWF prehravace.
     * @param string $xmlConf           URL XML konfigurace pro prehravac.
     * @param string $reporting         URL pro posilani reportingovych dat o dosazene pozici videa.
     * @param bool $autoPlay            Ma se prehravani automaticky spustit?
     * @param bool $buffer              Bufferovat?
     * @param string $skin              Skin prehravace, pokud neni zadan, pouzije se nastaveni z config.ini
     *                                  Je treba zadat kompletni URL
     */
    public static function prepareAvPlayer($playerHtmlElemId, $source, 
        $xmlConf, $reporting = '', $autoPlay = false, $buffer = true, $skin = null)
    {
        $inst = self::getInstance();
        $inst->loadJsFiles();
        
        $config = Zend_Registry::get('config');
        
        // Ulozime informaci o tom, ze tento SWF soubor je jiz nacitan
        $inst->js->loadedSwfs[] = $source;
        
        $userId = Ibulletin_Auth::getActualUserId();
        
        // Skin
        if(empty($skin)){
            $urlHlpr = new Zend_View_Helper_BaseUrl();
            $skin = $urlHlpr->baseUrl('pub/scripts/'.$config->video->defaultPlayerSkin);
            $skin = Zend_Filter::filterStatic($skin, 'NormPath');            
        }
        
        //$autoPlayCode = $autoPlay ? 'true' : 'false';
        $autoPlayCode = $autoPlay ? 'true' : 'false';
        $bufferCode = $buffer ? 'true' : 'false';
        
        $code = 
"
inCast.obj_$playerHtmlElemId = new AVPlayerClass();
inCast.obj_$playerHtmlElemId.elemId = '$playerHtmlElemId';
inCast.obj_$playerHtmlElemId.source = '$source';
inCast.obj_$playerHtmlElemId.xmlConf = '$xmlConf';
inCast.obj_$playerHtmlElemId.report = '$reporting';
inCast.obj_$playerHtmlElemId.autoPlay = $autoPlayCode;
inCast.obj_$playerHtmlElemId.buffer = $bufferCode;
inCast.obj_$playerHtmlElemId.userId = '$userId';
inCast.obj_$playerHtmlElemId.skin = '$skin';
inCast.obj_$playerHtmlElemId.self = 'inCast.obj_$playerHtmlElemId';
inCast.obj_$playerHtmlElemId.initialize();
";
//addUnloadEvent('inCast.obj_$playerHtmlElemId.release');

        // Pridame kod jako funkci pro spusteni po nacteni stranky
        $inst->js->addFunction($code, null, array(), true);
        
    }
    
    /**
     * Pripravi pro spousteni na strance pomoci JS prehravac prezentace.
     * 
     * @param string $playerHtmlElemId  ID HTML elementu prehravace.
     * @param string $source            URL k SWF prehravace.
     * @param string $xmlConf           URL XML konfigurace pro prehravac.
     * @param string $skin              Skin prehravace, pokud neni zadan, pouzije se nastaveni z config.ini
     *                                  Je treba zadat kompletni URL
     */
    public static function preparePtPlayer($playerHtmlElemId, $source, $xmlConf, $skin = null)
    {
        $inst = self::getInstance();
        $inst->loadJsFiles();
        
        $config = Zend_Registry::get('config');
        
        $userId = Ibulletin_Auth::getActualUserId();
        
        // Skin
        if(empty($skin)){
            $urlHlpr = new Zend_View_Helper_BaseUrl();
            $skin = $urlHlpr->baseUrl('pub/scripts/'.$config->presentation->defaultPlayerSkin);            
        }
        $skin = Zend_Filter::filterStatic($skin, 'NormPath');            
        
        // Ulozime informaci o tom, ze tento soubor je jiz nacitan
        $inst->js->loadedSwfs[] = $source;
        
        $code = 
"
inCast.obj_$playerHtmlElemId = new PTPlayerClass();
inCast.obj_$playerHtmlElemId.elemId = '$playerHtmlElemId';
inCast.obj_$playerHtmlElemId.source = '$source';
inCast.obj_$playerHtmlElemId.xmlConf = '$xmlConf';
inCast.obj_$playerHtmlElemId.userId = '$userId';
inCast.obj_$playerHtmlElemId.skin = '$skin';
inCast.obj_$playerHtmlElemId.self = 'inCast.obj_$playerHtmlElemId';
inCast.obj_$playerHtmlElemId.initialize();
addUnloadEvent('inCast.obj_$playerHtmlElemId.release');
";
        // Pridame kod jako funkci pro spusteni po nacteni stranky
        $inst->js->addFunction($code, null, array(), true);
        
    }
    
}