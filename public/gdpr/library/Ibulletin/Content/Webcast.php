<?php

/**
 * inBulletin - Webcast.php
 * 
 * Spojuje video s prezentaci tak, aby mohly byt spolu synchronizovany
 * 
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}


/**
 * Trida spojujici video s prezentaci s tim, ze tyto dve jsou synchronizovane.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Content_Webcast extends Ibulletin_Content_Abstract
{
    
    /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "webcast_%d.phtml";
    
    /**
     * @var Ibulletin_Player_XmlConfigSync Objekt konfigurace syncpointuu videa.
     */
    var $syncpoints_vid = null;
    
    /**
     * @var Ibulletin_Player_XmlConfigSync Objekt konfigurace syncpointuu prezentace.
     */
    var $syncpoints_pres = null;
    
    /**
     * @var Ibulletin_Content_Video Objekt contentu videa.
     */
    var $video = null;
    
    /**
     * @var Ibulletin_Content_Presentation  Objekt contentu prezentace. 
     */
    var $presentation = null;
       
    /**
     * @var int ID contentu s videem 
     */
    var $video_id = null;
    
    /**
     * @var int ID contentu s prezentaci 
     */
    var $presentation_id = null;
    
    /**
     * @var string  Jmeno souboru konfigurace videa.
     */
    var $configFileVideo = 'videoconfig.xml';
    
    /**
     * @var string  Jmeno souboru konfigurace prezentace.
     */
    var $configFilePres = 'presentationconfig.xml';
    
    /**
     * @var bool Je objekt pripraveny k pouziti? (je vlozene video i prezentace)
     */
    var $ready = false;
    
    
    
    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     * 
     * POZOR! Vlastni prepare ma take zvlast video a prezentace, ten se pouziva kdyz nejsou ve webcastu,
     * upravy je tedy obvykle nutne provest i v jejich prepare.
     * 
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepare($req)
    {
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $config = Zend_Registry::get('config');
        
        $this->prepareSheetNumber();  
        
        $this->loadSubcontents();
        
        // Pridani videa a prezentace pomoci JS
        Ibulletin_Js::addJsFile('jquery.js');
        Ibulletin_Js::addJsFile('Player/webcast.js');
        //Adresa pro reporting informaci z JS i prehravace
        $reportingUrl = $urlHlpr->url(array('contentid' => $this->video->id, 'srvname' => 'stats',
            'content' => $this->video->id, 'webcast' => $this->id, 'page_id' => $this->pageId, 
            'pageviewsid' => Ibulletin_Stats::getInstance()->page_view_id), 'service').'/';
        $js = Ibulletin_Js::getInstance();
        //$js->vars->playersReportingUrl = $reportingUrl;
        // Pokud je zadan v URL parametr play, spustime video
        $autoPlay = (bool)$req->getParam('play', false);
        
        // Prehravac videa
        if($this->video && $this->video->ready){
            // Pripravime do stranky JS prehravace a akce s tim spojene
            //$confUrl = $this->getPathToFile($this->configFileVideo);
            $confUrl = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'xmlconfig',
                'page_id' => $this->pageId, 'bulletin_id' => $this->bulletinId), 'service').'/';
            Ibulletin_Player_Js::prepareAvPlayer($this->video->playerHtmlElemId, 
                                                 $this->video->getPlayerPath(),
                                                 $confUrl,
                                                 $reportingUrl,
                                                 $autoPlay
                                                 );
        }
        
        // Prehravac prezentace
        if($this->presentation && $this->presentation->ready){
            // Pripravime do stranky JS prehravace a akce s tim spojene
            $confUrl = $this->getPathToFile($this->configFilePres);
            Ibulletin_Player_Js::preparePtPlayer($this->presentation->playerHtmlElemId, 
                                                 $this->presentation->getPlayerPath(),
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
            $html = Ibulletin_Marks::translate($this->html[$this->sheet_number-1], $path, $this);
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
        
        
        // Ziskame contenty s videem i s prezentaci
        $this->loadSubcontents();
        
        // Pridame data do view pro video a prezentaci a navic jeste adresy metadat
        $data->video_url = $this->video->getData()->videoUrl;
        $data->presentation = $this->presentation->getData();
        $data->metadataUrl = $this->getMetadataUrl();
        
        return $data;
    }
    
    /**
     * Sluzba, ktera vrati metadata pro video a prezentaci
     */
    public function metadataService(){
        // Pro testovani jsou metadata v souborech v adresari contentu
        $config = Zend_Registry::get('config');
        $file = $config->content_article->basepath.'/'.$this->id.'/'.'metadata.xml';
        
        $fh = fopen($file, "r");
        
        while(!feof($fh)) 
        {
            print(fread($fh, filesize($file)));
        }
        
    }
    
    
    /**
     * Vrati URL metadat potrebnych pro video a prezentaci
     */
    public function getMetadataUrl()
    {
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $url = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'metadata'), 'service');
        
        return $url;
    }
    
    /**
     * Nacte objekt contentu videa a prezentace ktere patri do tohoto webcastu.
     * Video se nacte do $this->video a prezentace do $this->presentation
     */
    public function loadSubcontents()
    {
        $video_cont = Contents::get($this->video_id);
        $this->video = $video_cont['object'];
        $presentation_cont = Contents::get($this->presentation_id);
        $this->presentation = $presentation_cont['object'];
    }

    
    /**
     * Metoda, ktera je volana contentem, na kterem tento content zavisi ve chvili, kdy je content
     * zmenen. 
     * 
     * U webcastu provede znovuulozeni XML configu s novymi daty od videa ci prezentace.
     * 
     * @param Ibulletin_Content_Abstract $dep   Trida, ktera se zmenila.
     */
    public function dependencyChanged($dep)
    {
        if($dep instanceof Ibulletin_Content_Video && $dep->id == $this->video_id && isset($dep->video)){
            $dep->video->config->syncpoints = $this->syncpoints_vid;
            $dep->video->config->getXml($this->getPathToFile($this->configFileVideo, false));
        }
        elseif($dep instanceof Ibulletin_Content_Presentation && $dep->id == $this->presentation_id && isset($dep->config)){
            $dep->config->syncpoints = $this->syncpoints_pres;
            $dep->config->getXml($this->getPathToFile($this->configFilePres, false));
        }
    }
    
    /**
     * Vrati XML konfiguraci pro video tohoto webcastu, volame metodu v objektu videa a predavame
     * navic syncpointy.
     */
    public function xmlconfigService($req)
    {
        $this->loadSubcontents();
        $this->video->xmlconfigService($req, $this->syncpoints_vid);
    }
}
