<?php

/**
 * inBulletin - content - Video.php
 * 
 * Obsahuje video, ktere muze byt soucasti stranky
 * 
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}


/**
 * Trida zprostredkovavajici prsitup k prehravani Flash videa v prehravaci na strance.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Content_Video extends Ibulletin_Content_Abstract
{
    
   /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "video_%d.phtml";
    
    /**
     * @var String Jmeno parametru position v POST/GET
     */
    var $pos_param_name = 'position';
    
    
    /**
     * @var String Typ obsahu - video/audio
     */
    var $video_type = "video";


    /**
     * @var String identifikator Vimeo videa
     */
    var $vimeo_id = null;

    /**
     * @var array objekt Vimeo videa ziskany cez Vimeo API
     */
    var $vimeo_video = null;

    /**
     * @var string vimeo original source filename
     */
    var $vimeo_file = null;

    /**
     * @var int last modified time of vimeo original source file
     */
    var $vimeo_file_lastmodified = null;

    /**
     * @var Process object handle holding commandline and process id of running background script
     */
    var $vimeo_worker = null;

    /**
     * @var bool video je nahrano ve full hd - 1080p rozliseni
     */
    var $quality1080p = false;

    /**
     * @var String Jmeno souboru s videem.
     */
    var $videoFileName = "video.flv";
    
    /**
     * @var String Jmeno souboru s preview obrazkem.
     */
    var $previewPicFileName = "preview.jpg";
    
    /**
     * @var String Jmeno souboru s konfiguraci videa.
     */
    var $configFileName = "config.xml";
    
    /**
     * @var Ibulletin_Video     Objekt videa.
     */
    var $video = null;
    
    
    /**
     * @var string DEPRECATED Cesta k prehravaci videa.
     *             Cesta se nyni bere z config.ini - video.player
     */
    var $playerPath = 'pub/scripts/AVPlayer.swf';
    
    /**
     * @var string ID HTML elementu prehravace videa.
     */
    var $playerHtmlElemId = 'swfAVPlayer';
    
    /**
     * @var bool Je objekt pripraveny k pouziti? (je nahrane video)
     */
    var $ready = false;
    
    /**
     * @var bool    Ma byt na konci videa zobrazena navigace predchozi/dalsi video?
     *              Null je inicialni hodnota, ktera je v adminu ihned nahrazena podle configu
     */
    var $addVideoNavigation = null;
    
    /**
     * @var bool    Ma byt na konci videa zobrazen odkaz pro preposalni kolegovi?
     *              Null je inicialni hodnota, ktera je v adminu ihned nahrazena podle configu
     */
    var $addVideoForward = null;
    
    /**
     * @var bool    Ma byt na konci videa zobrazen odkaz na prehart znovu?
     *              Null je inicialni hodnota, ktera je v adminu ihned nahrazena podle configu
     */
    var $addPlayAgain = null;
    
    
    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     * 
     * POZOR! Prepare ma pro video je take ve webcastu a tedy je potreba upravit oba.
     * 
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepare($req)
    {
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $config = Zend_Registry::get('config');
        
        $this->prepareSheetNumber();

        //POZOR! Prepare ma pro video je take ve webcastu a tedy je potreba upravit oba.
        // Pridani videa pomoci JS
        Ibulletin_Js::addJsFile('jquery.js');
        Ibulletin_Js::addJsFile('Player/webcast.js');

        // Lokalny prehravac videa
        if($this->ready){

            // Pripravime do stranky JS prehravac a akce s tim spojene
            //$confUrl = $this->getPathToFile($this->configFileName);
            $confUrl = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'xmlconfig',
                'page_id' => $this->pageId, 'bulletin_id' => $this->bulletinId, 
                'pageviewsid' => Ibulletin_Stats::getInstance()->page_view_id), 'service').'/';
            $reportingUrl = $this->getReportingUrl();
            // Pokud je zadan v URL parametr play, spustime video
            $autoPlay = (bool)$req->getParam('play', false);
            $js = Ibulletin_Js::getInstance();
            //$js->vars->playersReportingUrl = $reportingUrl;
            $this->playerHtmlElemId = $this->playerHtmlElemId . '_'.$this->id; 
            
            Ibulletin_Player_Js::prepareAvPlayer($this->playerHtmlElemId, 
                                                 $this->getPlayerPath(),
                                                 $confUrl,
                                                 $reportingUrl,
                                                 $autoPlay
                                                 );
        }

        // VIMEO Video
        elseif ($this->vimeo_id) {
            // Pridani videa pomoci JS
            Ibulletin_Js::addJsFile('Player/player.min.js');
            Ibulletin_Js::addJsFile('Player/vimeo.js');
        }
        
        /* NESPOUSTI SE v pripade webcastu
        // Ulozime si do session ID posledni otevrene page kvuli ratingu v embedu a predani URL kam poslat rating
        $seesion = Zend_Registry::get('session');
        $seesion->lastOpenedPageId = $this->pageId;
        */
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
        
        // Ziskame adresu souboru s videem, ktera se preda do prehravace videii
        $data->videoUrl = $this->getVideoUrl();
        // Pridame typ videa: audio/video
        $data->contentType = $this->video_type;
        // nazev id elementu
        $data->elemId = $this->playerHtmlElemId; 
        // Reporting URL
        $data->reportingUrl = $this->getReportingUrl();
        
        return $data;
    }
    
    /**
     * Vrati podle parametru nastavenych ve videu odkazy na konci videa jako XmlClass tak,
     * aby se tato trida dala vlozit do konfigurace videa. 
     * 
     * @return Ibulletin_XmlClass  XML s odkazy na konci videa / null pokud odkazy nemaji byt pouzity
     */
    public function getLinksXmlClass()
    {
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $config = Zend_Registry::get('config');
        
        $links = new Ibulletin_XmlClass();
        $links->__rootName = 'links';
        
        $cnt = 0;   // jen pocitadlo pro oznacovani elementuu ve tride
        
        // Odkazy na predchozi a nasledujici clanek
        if($this->addVideoNavigation && !empty($this->bulletinId)){
            // Ziskame odkazy
            $navigation = Pages::getPrevNextPageInBulletin(null, $this->bulletinId, $this->pageId);
            
            // Nezobrazujeme navigaci, pokud neni kam
            if($navigation !== null){
                if(!empty($navigation['next'])){
                    $links->next = new Ibulletin_XmlClass();
                    $links->next->__rootName = 'link';
                    $links->next->type = 'next';
                    $links->next->url = $urlHlpr->url($navigation['next']['params'], $navigation['next']['route'], true).'/';
                    $links->next->graphic = $urlHlpr->url(array(), 'default', true).$config->video->linksNavigationNextImg;
                }
                
                if(!empty($navigation['prev'])){
                    $links->prev = new Ibulletin_XmlClass();
                    $links->prev->__rootName = 'link';
                    $links->prev->type = 'previous';
                    $links->prev->url = $urlHlpr->url($navigation['prev']['params'], $navigation['prev']['route'], true).'/';
                    $links->prev->graphic = $urlHlpr->url(array(), 'default', true).$config->video->linksNavigationPrevImg;
                }
            }
        }
        
        // Odkaz na preposlani kolegovi
        if($this->addVideoForward){
            $links->forward = new Ibulletin_XmlClass();
            $links->forward->__rootName = 'link';
            $links->forward->type = 'forward';
            //$links->forward->url = $urlHlpr->url($navigation['prev']['params'], $navigation['prev']['route'], true).'/';
            $links->forward->url = '#forwardFromPlayer';
            $links->forward->graphic = $urlHlpr->url(array(), 'default', true).$config->video->linksForwardImg;
        }
        
        // Odkaz na spusteni znovu
        if($this->addPlayAgain){
            $links->play = new Ibulletin_XmlClass();
            $links->play->__rootName = 'link';
            $links->play->type = 'play';
            $links->play->graphic = $urlHlpr->url(array(), 'default', true).$config->video->linksPlayAgainImg;
        }
        
        return $links;
    }
    
    /**
     * Streamuje video pomoci objektu ke streamovani
     * 
     * @param Zend_Controller_Request_Abstract  Request
     */
    public function streamService($req)
    {
        // Vytvorime objekt videa s cestou k souboru videa na serveru
        $video = new Ibulletin_Video($this->getVideoPath());
        
        // Najdeme si pozici, od ktere budeme posilat video
        $pos = $req->getParam($this->pos_param_name, 0);
        
        // Zacneme streamovat
        $video->stream($pos, $this->name.'.flv');
    }

    
    /**
     * Vraci obrazek z videa podle casu v ms. Parametry zadane v adrese metodou get:
     * time - cas v ms pozice obrazku
     * w - pozadovana sirka obrazku
     * h - pozadovana vyska obrazku
     *  Pokud neni zadano, obrazek je v puvodni velikosti, pokud jen jeden rozmer, pokusime se dopocitat.
     *  POZOR!! Rozmer musi byt sudy, takze pri zadani licheho cisla se rozmer zvetsi o jedna.
     * format - jpeg/png
     * 
     * ffmpeg -i docpohunek.flv -ss 00:00:16.04 -vframes 1 -f image2 picsa.png
     * ffmpeg -i docpohunek.flv -ss 00:00:16.04 -vframes 1 -f image2 -
     * http://stream0.org/2008/02/howto-extract-images-from-a-vi.html
     * @param Zend_Controller_Request_Abstract  Request objekt
     */
    public function pictureService($req)
    {
        // Vytvorime objekt videa s cestou k souboru videa na serveru
        $video = new Ibulletin_Video($this->getVideoPath());
        
        
        //return;
        // Ziskame obrazek a nechame jej vypsat.
        $video->getPicture((int)$req->getParam('time', 0), 
                           (int)$req->getParam('w', null),
                           (int)$req->getParam('h', null),
                           $req->getParam('format', 'jpeg'));
    }
    
    
    /**
     * Uklada nejvetsi dosazenou pozici ve videu do statistik
     * 
     * POZOR! je take wrappovana z Ibulletin_Content_Presentation - nemel by se pouzivat kontext tridy
     * (proto oznacena jako static)
     */
    public static function statsService($req)
    {
        $frontController = Zend_Controller_Front::getInstance();
        $req = $frontController->getRequest();
        $content = $req->getParam('content',null);
        $videoNum = $req->getParam('videoNum',1);
        $webcast = $req->getParam('webcast',null);
        // Nazev action uz je zabrany, takze musime skrz $_REQUEST
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
        $position = $req->getParam('position',null);
        $embedFile = basename($req->getParam('embedfile',null));
        
        // Pokud neni zadana pozice a akce, nejedna se o ukladani statistik videa,
        // provedeme pripadne jinou akci
        if($position === null && $action === null){
            // Rating
            $rating = $req->getParam('rating',null);
            if($rating !== null){
                // Nechame dispatchnout jiny controller
                $req->setControllerName('service');
                $req->setActionName('savepagerating')
                    ->setDispatched(false);
                return;
            }
            
            return;
        }
        
        //Phc_ErrorLog::error('debug', $_REQUEST['action'].' '.$req->getParam('position','null'));
        
        try{
            Ibulletin_Stats::savePlayerEvent($content, $videoNum, $action, $position, $embedFile, $webcast);
        }
        catch(Exception $e){
            Phc_ErrorLog::error('Ibulletin_Content_Video::statsService()', $e);
        }
    }
    
    /**
     * Vrati XML konfiguraci pro toto video.
     */
    public function xmlconfigService($req, $syncpoints = null)
    {
        // Nastavime content type
        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();
        $response->setHeader('Content-Type', 'text/xml', true);
        
        $config = clone $this->video->config;
        
        // Nastavime pageId a bulletinId
        $this->setPageId($req->getParam('page_id'));
        $this->bulletinId = $req->getParam('bulletin_id');
        
        // Pokud byly predany sincpointy, dame je do konfigurace
        if($syncpoints !== null){
            $config->syncpoints = $syncpoints;
        }
        
        /* NEFUNGOVALO - v adminu videa se nenastavuje ktery embed je rating
        // Pokud je nejaky embed ratingem, musime mu nastavit spravnou URL podle stranky na ktere prave jsme.
        // Klicove slovo v reportUrl je '/ratingurl'
        $embeds = $config->getEmbeds();
        $ratingKey = null;
        foreach($embeds as $key => $embed){
            if($embed['reporturl'] == '/ratingurl'){
                $ratingKey = $key;
                break;
            }
        }
        if($ratingKey !== null){
            $seesion = Zend_Registry::get('session');
            $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
            $reportUrl = $urlHlpr->url(array('controller' => 'service', 'action' => 'savepagerating',
                'page_id' => $seesion->lastOpenedPageId), 'default', true).'/';
            $config->addEmbed(null, null, $ratingKey, null, null, $reportUrl);
        }
        */
        
        // Do reporting URL kazdeho embedu pridame page_views_id, pouzivame v tabulce users_answers
        $pvid = $req->getParam('pageviewsid');
        if(!empty($pvid)){
            $embeds = $config->getEmbeds();
            foreach($embeds as $key => $embed){
                $config->addEmbed(null, null, $key, null, null, $embed['reporturl'].'pageviewsid/'.$pvid.'/');
            }
        }
        
        // Odkazy na konci videa
        $links = $this->getLinksXmlClass();
        $config->links = $links;
        
        // Vypiseme config
        echo $config->getXml();
    }
    
    /**
     * Vrati cestu k souboru prehravace na serveru od korene, aby mohl byt prehravac cachovan
     * prohlizecem.
     */
    public static function getPlayerPath()
    {
        $config = Zend_Registry::get('config');
        $inst = new Ibulletin_Content_Video();
        $urlHlpr = new Zend_View_Helper_BaseUrl();
        $path = $urlHlpr->baseUrl('pub/scripts/'.$config->video->player);
        $path = Zend_Filter::filterStatic($path, 'NormPath');    
        return $path;
    }
    
    /**
     * Vrati reporting url pro tento content
     * @return string Reporting URL
     */
    public function getReportingUrl(){
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $reportingUrl = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'stats',
            'content' => $this->id, 'page_id' => $this->pageId), 'service').'/';
        
        return $reportingUrl;
    }
    
    
    /**
     * Vrati cestu k souboru videa na serveru
     */
    public function getVideoPath()
    {
        $config = Zend_Registry::get('config');        
        $path = $config->content_article->basepath.'/'.$this->id.'/'.$this->videoFileName;  
        $path = Zend_Filter::filterStatic($path, 'NormPath'); 
        return $path;
    }
    
    /**
     * Vrati URL k volani videa z prehravace
     */
    public function getVideoUrl()
    {
        /*
        $bashout = array();

        passthru('ffmpeg -i '.$this->getVideoPath().' -ss 00:00:02.24 -vframes 1 -f image2 pub/content/'
            .$this->id.'/picss.png', $bashout);

        echo "<img src='pub/content/".$this->id."/picss.png' />";    
        
        print_r($bashout);
        */
        
        
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $url = $urlHlpr->url(array('contentid' => $this->id, 'srvname' => 'stream'), 'service').'/';
        
        return $url;
    }
}

