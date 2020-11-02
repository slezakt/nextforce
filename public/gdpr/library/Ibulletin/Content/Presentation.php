<?php

/**
 * inBulletin - Presentation.php
 * 
 * Obsahuje prezentaci, ktera muze byt soucasti stranky ci webcastu
 * 
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}


/**
 * Trida zprostredkovavajici prsitup k SWF prezentaci na strance s prehravacem SWF prezentacii.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Content_Presentation extends Ibulletin_Content_Abstract
{
    
    /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "presentation_%d.phtml";
    
    /**
     * @var bool Je objekt pripraveny k pouziti? (je nahrana prezentace)
     */
    var $ready = false;
    
    
       
    /**
     * @var String Nazev souboru s prezentaci
     */
    var $file_name = "presentation.swf";
    
    /**
     * @var String Jmeno souboru s konfiguraci prezentace - prehravace.
     */
    var $configFileName = "config.xml";
    
    /**
     * @var Ibulletin_Player_XmlConfigPt    Objekt konfigurace prezentace.
     */
    var $config = null;
    
    /**
     * @var string DEPRECATED Cesta k prehravaci prezentace.
     *             Cesta se nyni bere z config.ini - presentation.player
     */
    var $playerPath = 'pub/scripts/PTPlayer.swf';
    
    /**
     * @var string ID HTML elementu prehravace prezentace.
     */
    var $playerHtmlElemId = 'swfPTPlayer';
    
    
    /**
     * Pripravi objekt XML konfigurace prehravace.
     */
    public function __construct()
    {
        // Pripravime konfiguraci pro prehravac
        $this->config = new Ibulletin_Player_XmlConfigPt();
        $this->config->id = 'presentation';
    }
    
    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     * 
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepare($req)
    {
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $config = Zend_Registry::get('config');
        
        $this->prepareSheetNumber();  
        
        // Pridani prezentace pomoci JS
        Ibulletin_Js::addJsFile('jquery.js');
        Ibulletin_Js::addJsFile('Player/webcast.js');
        // Prehravac
        if($this->ready){
            // Pripravime do stranky JS prehravac a akce s tim spojene
            $confUrl = $this->getPathToFile($this->configFileName);
            $reportingUrl = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'stats',
                'content' => $this->id, 'page_id' => $this->pageId), 'service').'/';
            $js = Ibulletin_Js::getInstance();
            //$js->vars->playersReportingUrl = $reportingUrl;
            Ibulletin_Player_Js::preparePtPlayer($this->playerHtmlElemId, 
                                                 $this->getPlayerPath(),
                                                 $confUrl
                                                 );
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
        if(isset($this->html[$this->sheet_number-1])){
            //$html = Ibulletin_Marks::translate($this->html[$this->sheet_number-1], $path, $this);
            $html = '';
        }
        else{
            $html = '';
        }
        
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
        
        // Ziskame adresu souboru s prezentacii, ktera se preda do prehravace
        $data->presentationUrl = $this->getPresentationUrl();
        
        return $data;
    }
    
    /**
     * Uklada nejvetsi dosazenou pozici v prezentaci do statistik.
     * Wrapper na shodnou metodu v Ibulletin_Content_Video
     */
    public function statsService($req)
    {
        Ibulletin_Content_Video::statsService($req);
    }
    
    /**
     * Vrati cestu k souboru prehravace na serveru od korene, aby mohl byt prehravac cachovan
     * prohlizecem.
     */
    public static function getPlayerPath()
    {
        $config = Zend_Registry::get('config');
        $inst = new Ibulletin_Content_Presentation();
        $urlHlpr = new Zend_View_Helper_BaseUrl();
        $path = $urlHlpr->baseUrl('pub/scripts/'.$config->presentation->player);
        $path = Zend_Filter::filterStatic($path, 'NormPath');        
        return $path;
    }
    
    /**
     * Vrati cestu k souboru prezentace na serveru
     */
    public function getPresentationPath()
    {
        $config = Zend_Registry::get('config');
        $path = '/'.$config->content_article->basepath.'/'.$this->id.'/'.$this->file_name;
        $path = Zend_Filter::filterStatic($path, 'NormPath');
        return $path;
    }
    
    /**
     * Vrati URL k volani prezentace z prehravace
     */
    public function getPresentationUrl()
    {
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $urlHlpr = new Zend_View_Helper_BaseUrl();
        
        $url = $urlHlpr->baseUrl($this->getPresentationPath());
        
        return $url;
    }
}
