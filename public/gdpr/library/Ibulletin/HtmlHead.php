<?php

/**
 * iBulletin - HtmlHead.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


 /**
 * Trida slouzici k pridavani potrebnych casti do hlavicky HTML,
 * predevsim nacteni pozadovanych java scriptu a css stylu.
 * 
 * POZOR! DD_belatedPNG je vkladan podminene jen pro IE6, je tedy nutne osetrit 
 * kod volani tridy belatedPNG podle toho, jestli existuje!
 *
 * Nove skripty k nacteni se vkladaji pomoci funkce:
 * Ibulletin_HtmlHead::addFile((string) $file, ["js"/"css"]);
 *
 * Uvnitr tagu <head></head> musi byt provedeno:
 * <?=Ibulletin_HtmlHead::render()?>
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_HtmlHead
{
    /**
     * Instance tridy.
     *
     * @var Ibulletin_HtmlHead
     */
    private static $_instance = null;


    /**
     * Flag jestli jiz problehla detekce uzivatelova prohlizece
     *
     * @var bool
     */
    private $_browserDetectionDone = true;


    /**
     * Pole JavaScriptuu
     *
     * @var array
     */
    private $_javascriptA = array();


    /**
     * Pole CSS stylu
     *
     * @var array
     */
    private $_cssA = array();


	/**
	 * Pole externich CSS stylu
	 *
	 * @var array
	 */
	private $_externalCss = array();

    
    /**
     * Pole RAW META tagu
     *
     * @var array
     */
    private $_metaRawA = array();

    
    /**
     * @var string  Base do tagu <base> v head, ktery nastavuje base url pro vsechny relativni 
     *              pozadavky ve strance. Pokud je prazdny, nenastavuje se base. Pouzivame v HTML inCampaign
     */
    public $base = null;


    /**
     * Vrati existujici instanci Ibulletin_HtmlHead nebo vytvori novou a tu vrati.
     *
     * @return Ibulletin_HtmlHead Jedina instance Ibulletin_HtmlHead
     */
    public static function getInstance()
    {
        if(self::$_instance === null){
            self::$_instance = new Ibulletin_HtmlHead();
        }

        return self::$_instance;
    }


    /**
     * Konstruktor nacte z configu prednastavene css a js
     *
     */
    private function __construct()
    {
        $config = Zend_Registry::get('config');

        // Nacteme a zavedeme z configu soubory do hlavicek
        //provadime pouze pokud nejsme v adminu
        $req = Zend_Controller_Front::getInstance()->getRequest();
        $files = array();
        if($req->getModuleName() != 'admin'){
            $files = $config->htmlhead->file;
        }
        elseif(!empty($config->htmlhead->admin->file)){
            $files = $config->htmlhead->admin->file->toArray();
            //print_r($files);
        }
        if(!empty($files)){
            foreach($files as $file){
                if(preg_match('/\.js$/', $file)){
                    // Pridame JS soubor k souborum k vraceni jako jednoho celku
                    Ibulletin_Js::addJsFile($file, true);
                }
                else{
                    self::addFile($file, null, $this);
                }
            }
        }
    }


    /**
     * Nastavi flag, ze jiz probehla detekce uzivatelova prohlizece pres JS
     * a tedy neni treba pridavat js pro detekci prohlizece do htmlhead.
     */
    public static function setBrowserDetectionUndone()
    {
        $inst = self::getInstance();

        $inst->_browserDetectionDone = false;
    }


    /**
     * Prida RAW meta tag k vypsani v HEAD. Jedna se o kompletni tag <META xxxx >. 
     * 
     * @param   string  Cely meta tag ke vlozeni do HEAD
     * @param   Ibulletin_HtmlHead  Instance Ibulletin_HtmlHead pro pouziti v konstruktoru
     */
    public static function addMetaRaw($string, $inst = null)
    {
        if($inst === null){
            $inst = self::getInstance();
        }
        
        $string = trim($string);
        
        if(preg_match('/^<meta[^>]*>$/i', $string)){
            $inst->_metaRawA[] = $string;
        }
        else{
            // Nespravne zapsany meta, zalogujeme chybu.
            Phc_ErrorLog::info('Ibulletin_HtmlHead::addMetaRaw()', 
                "Nespravny format zadaneho meta tagu. Zadany tag:\n".$string);
        }
    }


    /**
     *
     *
     * @param string    Nazev souboru z adresare ./pub/scripts nebo ./pub/css
     * @param string    - js pro javascript
     *                  - css pro kaskadovy styl
     *                  V pripade nezadani type se detekuje podle pripony a defaultni je JS.
     * @param Ibulletin_HtmlHead Instance Ibulletin_HtmlHead pro pouziti v konstruktoru
     */
    public static function addFile($file, $type = null, $inst = null)
    {
        if($inst === null){
            $inst = self::getInstance();
        }

        if($type == 'js' || ($type === null && preg_match('/\.js$/', $file))){
            $inst->_javascriptA[] = $file;
        }
        elseif($type == 'css' || ($type === null && preg_match('/\.css$/', $file))){
            $inst->_cssA[] = $file;
        }
        else{
            $inst->_javascriptA[] = $file;
        }
    }

	/**
	 * @param string    Nazev souboru z adresare ./pub/scripts nebo ./pub/css
	 * @param Ibulletin_HtmlHead Instance Ibulletin_HtmlHead pro pouziti v konstruktoru
	 */
	public static function addExternalCss($link, $inst = null)
	{
		if($inst === null){
			$inst = self::getInstance();
		}

		$inst->_externalCss[] = $link;

	}

    /**
     * Odebere soubor ze seznamu souboru pro pridani do HEAD
     *
     * @param   String  Soubor, ktery ma byt odebran z nacitani
     * @param   String  - js pro javascript
     *                  - css pro kaskadovy styl
     *                  V pripade nezadani type se prohleda vse.
     * @return  Int     Pocet odebranych souboru.
     */
    public static function removeFile($file, $type = null){
        $inst = self::getInstance();

        $removed = 0;

        if($type == 'css' || empty($type)){
            foreach($inst->_cssA as $key => $val){
                if(preg_match('/'.$file.'/', $val)){
                    unset($inst->_cssA[$key]);
                    $removed++;
                }
            }
        }

        if($type == 'js' || empty($type)){
            foreach($inst->_javascriptA as $key => $val){
                if(preg_match('/'.$file.'/', $val)){
                    unset($inst->_javascriptA[$key]);
                    $removed++;
                }
            }
        }
    }


    /**
     * Vyrenderuje cely kod HTML HEAD.
     * 
     * POZOR! JS DD_belatedPNG vklada do podminky IF IE6!!
     *
     */
    public static function render()
    {
        $inst = self::getInstance();        
        $config = Zend_Registry::get('config');
        
        $urlHlpr = new Zend_View_Helper_Url();
        $baseUrlHlpr = new Zend_View_Helper_BaseUrl();        

        // Provedeme prednacteni SWF souboruu
        $js = Ibulletin_Js::getInstance();
        $js->preloadSwfs();

        // Pokud je nejaky JS soubor k nacteni v Ibulletin_Js, pridame adresu pro nacteni uceleneho JS
        $jsUrl = Ibulletin_Js::renderAllJsFilesNameForHead();
        if($jsUrl){            
            self::addFile($jsUrl);
        }

        $out = $inst->_getBrowserDetectionCode();

        // Html META tagy
        foreach($inst->_metaRawA as $meta){
            $out .= $meta."\n";
        }

        // Base URL pro pripad, ze je potreba, pouzivame pro HTML inCampaign, 
        // kde se importuje cele HTML ulozene nekde v pub/content
        if($inst->base){
            $out .= '<base href="'.'http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$inst->base.'" />';
        }

        // Pridame Java Script z objektu Ibulletin_Js
        $out .= '<script type="text/javascript">';
        $out .= Ibulletin_Js::render();
        $out .= '</script>'."\n";

        foreach($inst->_javascriptA as $file){
            if(!preg_match('@^/@', $file)){
                $pathPrefix = $baseUrlHlpr->baseUrl('pub/scripts/');
            }
            else{
                $pathPrefix = '';
            }
            $script = '<script type="text/javascript" src="'.$pathPrefix.$file.'"></script>';
            
            // DD_belatedPNG HACK
            if(preg_match('/belatedPNG/i', $file)){
                $script = "<!--[if IE 6]>".$script."<![endif]-->";
            }
            
            $out .= $script."\n";
        }

	    // External CSS
	    foreach($inst->_externalCss as $css){
		    $out .= '<link rel="stylesheet" href="'.$css.'" media="screen" type="text/css" />'."\n";
	    }


        foreach($inst->_cssA as $file){
            if(!preg_match('@^/@', $file)){                
                // add skin path prefix
                
                //pokusi se získat aktualni bulletin s vyjimkou v pripade ze renderujem např. E500 (např. chyba v pripojeni k DB)
                try {
                    $act_bul = (array)Bulletins::get(Bulletins::getCurrentBulletinId());
                } catch (Zend_Exception $ex) {
                    // pro error controller nastavime na null, abychom neprerusili zobrazeni error pages
                    $fc = Zend_Controller_Front::getInstance();
                    if($fc->getRequest()->getControllerName() == "error") {   
                        $act_bul = null;
                    } else {
                         Phc_ErrorLog::error('Ibulletin_HtmlHead',$ex->getMessage());
                    }
                }

                // pick actual bulletin`s skin 
                $bulletin_skin = empty($act_bul['skin']) ? $config->general->skin : $act_bul['skin'];
                //dump(empty($act_bul['skin']), $act_bul['skin'], $bulletin_skin);
                $path = Skins::getBasePath() . '/' . $bulletin_skin . '/css';
            	if (file_exists($path . '/' .  $file)) { 
                	$pathPrefix = $baseUrlHlpr->baseUrl($path);
            	// fallback to config skin
            	} else { 
	            	$path = Skins::getBasePath() . '/' . $config->general->skin . '/css';                
	                if (file_exists($path . '/' .  $file)) { // fallback to config skin
	                	$pathPrefix = $baseUrlHlpr->baseUrl($path);
	               	// fallback to pub/css
	                } else { 
	                	$pathPrefix = $baseUrlHlpr->baseUrl('pub/css');
	                }
            	}
            	$pathPrefix = rtrim($pathPrefix, '\\/') . '/';
                $pathPrefixFile = 'pub/css/';
            }
            else{
                $pathPrefix = '';
                $pathPrefixFile = null;
            }

            
            $out .= '<link rel="stylesheet" href="'.$pathPrefix.$file.'" media="screen" type="text/css" />'."\n";
            
/* Andrejova kolize?
=======
            if(($pathPrefixFile && file_exists($pathPrefixFile.$file)) || $pathPrefixFile === null){
                $out .= '<link rel="stylesheet" href="'.$pathPrefix.$file.'" media="screen" type="text/css" />'."\n";
            }
>>>>>>> .r1734
*/
        }
        
        $jsToEnd = Ibulletin_Js::renderEnd();
        if(!empty($jsToEnd)){
            $out .= '<script type="text/javascript">'."\n".'//<![CDATA[';
            $out .= $jsToEnd;
            $out .= '//]]>'."\n".'</script>';
        }
        
        // Ulozime do session data o JS a uklidime Tridu JS k dalsimu pouziti
        Ibulletin_Js::saveToPhpSession();
        
        return $out;
    }

    /**
     * Vrati podle potreby html kod do hlavicky pro urceni uzivatelova prohlizece.
     * Pokud byl prohlizec jiz urcen vraci prazdny retezec.
     *
     * @return string HTML kod do <head></head> pro urceni uzivatelova prohlizece
     */
    private static function _getBrowserDetectionCode()
    {
        $inst = self::getInstance();
        $baseUrlHlpr = new Zend_View_Helper_BaseUrl();

        if(!$inst->_browserDetectionDone){            
			return '<script type="text/javascript">var base_url="'.$baseUrlHlpr->baseUrl().'";</script>
			<script type="text/javascript" src="'.$baseUrlHlpr->baseUrl().'/pub/scripts/flash_detect_min.js"></script>
			<script type="text/javascript" src="'.$baseUrlHlpr->baseUrl().'/pub/scripts/browser_info.js"></script>';
        }
        else{
            return '';
        }
    }
}
