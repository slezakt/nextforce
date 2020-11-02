<?php
/**
 * inBulletin - Video.php
 * 
 * Objekt reprezentujici soubor videa. Zprostredkovava sluzby videa, jako je stream
 * nebo snimek z videa. Obsahuje konfiguraci pro prehravac videa.
 * 
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}
/**
 * Code:
 * 1 - Soubor videa neexistuje.
 * 2 - nepodarilo se vykonat program ffmpeg
 * 3 - nepodarilo se nacist metadata videa 
 *
 */
class Ibulletin_Video_Exception extends Exception {}

/**
 * 
 */
class Ibulletin_Video_Regenrate_Metadata_Exception extends Exception {}



/**
 * Trida tvorici objekty videa, z kterych lze video streamovat nebo ziskavat jednotlive snimky.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Video 
{
    
    /**
     * @var string  Lokalni cesta k souboru videa
     */
    var $filePath = null;
    
    /**
     * @var int  Delka videa v ms
     */
    var $duration = null;
    
    /**
     * @var int     Pocet bytu, po kterych se vyprazdni buffer pri streamovani. 
     */
    var $streamPiece = 7000;
    
    /**
     * @var array(w, h)  Rozliseni videa
     */
    var $resolution = null;
    
    /**
     * @var stdClass Vsechna metadata videa
     */
    var $metadata = null;
    
    /**
     * @var Ibulletin_Player_XmlConfigAv Konfigurace pro prehravac predavana v XML
     */
    var $config = null;
    
    
    /**
     * Nastavi pozici souboru a pokud existuje, natahne do objektu informace o videu,
     * jako je jeho delka, pocet snimku a podobne. 
     * 
     * @param string $filePath  Lokalni cesta k souboru videa.
     * 
     * @throws Ibulletin_Video_Exception
     */
    public function __construct($filePath)
    {
        if(!file_exists($filePath)){
            throw new Ibulletin_Video_Exception('Soubor videa "'.$filePath.'" neexistuje.', 1);
        }
        
        $this->filePath = $filePath;
        
        // Pripravime konfiguraci pro prehravac
        $this->config = new Ibulletin_Player_XmlConfigAv();
        $this->config->id = 'video';
        $this->config->type = 'video';
        
        
        // Ziskame a ulozime dalsi informace o videu
        /*
        $shellOut = array();
        $returnVar = null;
        
        $command = 'ffmpeg -i '.$this->filePath.' 2>&1'; 
        exec($command, $shellOut, $returnVar);
        
        if($retrunVar > 0){
            throw new Ibulletin_Video_Exception('Nepodarilo se ziskat obrazek pomoci ffmpeg,'.
                ' cely prikaz: "'.$command.'".', 2);
        }
        
        // najdeme informace o videu
        $match = array();
        $match1 = array();
        $duration = null;
        $resolution = null;
        foreach($shellOut as $line){
            // Delka videa
            if(preg_match('/Duration:.*([0-9]{2}):([0-9]{2}):([0-9]{2}).([0-9]{1,2})/i', $line, $match)){
                // Spocitame cas v sekundach
                $duration = $match[1]*3600000 + $match[2]*60000 + $match[3]*1000 + $match[4]*10;
            }
                
            // Rozmer videa
            if(!$resolution && preg_match('/Stream[ ]+\#.*:[ ]+Video:.*, ([0-9]+)x([0-9]+),/i', $line, $match1)){
                $resolution = array('w' => $match1[1], 'h' => $match1[2]);
            }
        }
        //*/
        
        
        $this->loadFlvInfo();
    }
    
    /**
     * Nacte metadata do pormennych objektu
     */
    public function loadFlvInfo()
    {
        // Nacteme metadata
        try
        {
            $flvinfo = new Phc_Flvinfo();
        }
        catch(Exception $e)
        {
            throw new Ibulletin_Video_Exception('Nepodarilo se nacist metadata videa. Soubor: '.
                $filePath.' Puvodni vyjimka: '."\n".$e, 3);            
            
            Phc_ErrorLog::error('Ibulletin_Video::__construct()', 
                'Nepodarilo se nacist metadata videa. Soubor: '.$filePath.' Puvodni vyjimka: '."\n".$e);
            
        }

        if(file_exists($this->filePath)){
            $this->metadata = $flvinfo->getInfo($this->filePath);
            $resolution = array('w'=>$this->metadata->video->width, 'h'=>$this->metadata->video->height);

            $this->duration = $this->metadata->duration * 1000;
            $this->resolution = $resolution;
        }
        else{
            $this->duration = 0;
            $this->resolution = array('w'=>0, 'h'=>0);
        }
        
        
        
    }
    
    /**
     * Nastavi do konfigurace prehravace zdroj streamovani.
     * 
     * @param string $url Adresa streamu videa pro prehravac.
     */
    public function setStreamUrl($url)
    {
        $this->config->source = $url;
    }
    
    
    /**
     * Nastavi do konfigurace prehravace zdroj ukazkoveho obrazku,
     * ktery se ukaze pred spustenim videa.
     * 
     * @param string $url Adresa preview obrazk.
     */
    public function setPreviewPic($url)
    {
        $this->config->preview = $url;
    }
    
    /**
     * Vrati nebo ulozi data XML konfigurace pro prehravac.
     *
     * @param bool $plainVideo  Ma byt konfigurace poskytnuta bez embed a synchronizacnich
     *                          dat pro adminstraci a podobne?
     * @param string $file  Soubor kam se ma cesta ulozit.
     * @return string/null  XML data konfigurace, nebo null pokud byl zadat soubor k ulozeni.
     */
    public function getPlayerConfigXml($plainVideo = false, $file = null)
    {
        // Pokud se ma vratit konfigurace pro plain video, musime odebrat casti konfigurace.
        if($plainVideo){
            $this->config->disableAttr('syncpoints');
            $this->config->disableAttr('embeds');
        }
        
        $xml = $this->config->getXml($file);
        
        // Nastavime zase vse zpet
        if($plainVideo){
            $this->config->enableAttr('syncpoints');
            $this->config->enableAttr('embeds');
        }
        
        return $xml;
    }    
    
    /**
     * Zjisti, jestli ma video vyplnene bytove pozice klicovych snimkuu,
     * tedy jestli je potreba provest regeneraci metadat.
     * 
     * @return bool Obsahuji metadata informace o klicovych snimcich?
     */
    public function hasKeyframesMetadata()
    {
        //return true;
        if(!isset($this->metadata->rawMeta) || !isset($this->metadata->rawMeta[1]['keyframes'])
                || empty($this->metadata->rawMeta[1]['keyframes']->filepositions) 
                || !isset($this->metadata->rawMeta[1]['metadatacreator']) 
                || !preg_match('/Yet Another Metadata Injector/', $this->metadata->rawMeta[1]['metadatacreator']))
        {
            return false;
        }
        else{
            return true;
        }
    }
    
    
    /**
     * Provede nove oindexovani videa pomoci yamdi. Prida bytove pozice klicovych snimku. 
     * Nekontroluje, jestli jiz metadata nejsou obsazena.
     * 
     * @throws  Ibulletin_Video_Regenrate_Metadata_Exception    Pokud se nepodari regenerovat metadata.
     */
    public function regenerateMetadata()
    {
        $flvinfo = new Phc_Flvinfo();
        $this->metadata = $flvinfo->getInfo($this->filePath);
        //return;
        
        // Vygenerujeme soubor videa i s metadaty
        $ok = true;
        
        $tmpfile = tempnam('4378dsjkdj832jsmjeewqsdxckdj', 'tmp_'); // Neexistujici adresar, aby se to hodilo to tempu
        
        $returnVar = null;
        $exeoutput = array();
        $command = "yamdi -i $this->filePath -o $tmpfile";
        // Na serverech mame yamdi podivne umisten
        if(file_exists('/opt/yamdi/yamdi')){
            $command = '/opt/yamdi/'.$command;
        }
        exec($command, $exeoutput, $returnVar);
        
        
        // Presuneme soubor misto puvodniho
        if($returnVar === 0){
            if(unlink($this->filePath)){
                if(!copy($tmpfile, $this->filePath)){
                    Phc_ErrorLog::error('VideoController::editAction', 'Nepodarilo se presunout '.
                        'soubor s oindexovanym videem -  '.$tmpfile.'.');
                    Utils::chmod($tmpfile, Utils::FILE_PERMISSION);
                    unlink($tmpfile);
                    $ok = false;
                }
                else{
                    unlink($tmpfile);
                    Utils::chmod($this->filePath, Utils::FILE_PERMISSION);
                }
            }
            else{
                $ok = false;
                Phc_ErrorLog::error('VideoController::editAction', 'Nepodarilo se smazat '.
                    'puvodni soubor videa -  '.$this->filePath.'. ');
            }
        }
        else{
            Phc_ErrorLog::error('VideoController::editAction', 'Nepodarilo se vykonat '.
                    'yamdi pro oindexovani videa -  '.$this->filePath.'. '.
                    'vystup yamdi: "'.join("\n",$exeoutput).'".');
            $ok = false;
        }
        if(!$ok){
            throw new Ibulletin_Video_Regenrate_Metadata_Exception('Nepodarilo se regenrovat '.
                'metadata souboru '.$this->filePath.'.');
        }
        
        // Znovu metadata
        $flvinfo = new Phc_Flvinfo();
        $this->metadata = $flvinfo->getInfo($this->filePath);
    }
    
    
    /**
     * Ziska obrazek z videa a zacne jej primo odesilat, pripadne ulozi obrazek na zadane umisteni.
     * 
     *  Lze zadat pouze jeden rozmer obrazku a druhy se pak dopocita, pokud se podarilo
     *  ziskat rozmer videa.
     * 
     * @param int $time      Cas videa v ms.
     * @param int $width     Sirka vysledneho obrazku. Pri nezadani rozmeruu bude obrazek
     *                       v puvodni velikosti.
     *                       POZOR!! Rozmer musi byt sudy, takze pri zadani licheho cisla se rozmer 
     *                       zvetsi o jedna.
     * @param int $height    Vyska vysledneho obrazku.
     *                       POZOR!! Rozmer musi byt sudy, takze pri zadani licheho cisla se rozmer 
     *                       zvetsi o jedna.
     * @param string $format Format vysledneho souboru - png/jpg - DEFAULT jpg
     */
    public function getPicture($time = 0, $width = null, $height = null, $format = 'jpeg', 
                               $outfile = null)
    {
        // Pokud je cas vetsi, nez delka videa, zalogujeme to, zbytek nechame bezet
        // vylezou aspon hlavicky
        /*
        if($time > $this->duration){
            Phc_ErrorLog::error('Ibulletin_Video::getPicture()', 'Byla pozadovana pozice videa'.
                ' vetsi, nez je delka videa. Pozice: '.$time.' Delka videa: '.$this->duration);
        }
        */
        
        
        // Cas v sekundach
        $time_s = round($time / 1000, 3);
        
        // Velikost
        if(!empty($this->resolution) && ($width || $height) || ($width && $height)){
            // pokud je z ceho, dopocitam druhy rozmer
            
            if(!($width && $height)){
                if($width && $this->resolution['w'] && $this->resolution['h']){
                    $height = round($width/$this->resolution['w'] * $this->resolution['h']);
                }
                if($height && $this->resolution['w'] && $this->resolution['h']){
                    $width = round($height/$this->resolution['h'] * $this->resolution['w']);
                }
            }
            
            
            // Pozor, rozmer musi byt sudy...
            if($width&1){
                $width++;
            }
            if($height&1){
                $height++;
            }
            
            $size = ' -s '.$width.'x'.$height.' ';
        }
        else{
            $size = '';
        }
        
        // Format
        if($format == 'jpeg'){
            $codec = 'mjpeg';
            $type = 'jpeg';
        }
        elseif($format == 'png'){
            $codec = 'png';
            $type = 'png';
        }
        else{
            $codec = 'mjpeg';
            $type = 'jpeg';
        }
        
        // Vystupni soubor
        if(!$outfile){
            $outfile = '-';
            
            // Neukladame, musime poslat hlavicky
            $frontController = Zend_Controller_Front::getInstance();
            $response = $frontController->getResponse();
            $response->setHeader('Content-Type', 'image/'.$type, true);
            $response->setHeader('Content-Disposition', 'inline; filename="frame'.$time
                .'.'.$type.'"', true);
            $response->sendHeaders();
        }
        
        $retrunVar = null;
        $command = 'ffmpeg -i '.$this->filePath.' -ss '.$time_s.$size.' -vframes 1 -vcodec '.$codec.
                   ' -f image2 '.$outfile;
        
        //Phc_ErrorLog::debug('', $time_s);
        passthru($command, $retrunVar);
        
        if($retrunVar > 0){
            throw new Ibulletin_Video_Exception('Nepodarilo se ziskat obrazek pomoci ffmpeg,'.
                ' cely prikaz: "'.$command.'".', 2);
        }
    }
    
    
    
    /**
     * Zacne odesilat data video streamu i s hlavickami na zacatku.
     * Pozor! Ukoncuje SESSION!!
     * 
     * @param int       Bytova pozice v souboru videa
     * @param string    Jmeno vystupniho souboru predavane v hlavickach html
     */
    public function stream($pos = 0, $outfilename = null)
    {
        // Ukoncime session, aby k nim mohly pristupovat jine behy skriptu
        session_write_close();
        
        if(!$outfilename){
            $outfilename = basename($this->filePath);
        }
        //  SCRIPT CONFIGURATION
    
        //------------------------------------------------------------------------------------------
        //  MEDIA PATH
        //
        //  you can configure these settings to point to video files outside the public html folder.
        //------------------------------------------------------------------------------------------
        
        // points to server root
        define('XMOOV_PATH_ROOT', '');
        
        // points to the folder containing the video files.
        define('XMOOV_PATH_FILES', 'video/');
        
        
        
        //------------------------------------------------------------------------------------------
        //  SCRIPT BEHAVIOR
        //------------------------------------------------------------------------------------------
        
        //set to TRUE to use bandwidth limiting.
        define('XMOOV_CONF_LIMIT_BANDWIDTH', FALSE);
        
        //set to FALSE to prohibit caching of video files.
        define('XMOOV_CONF_ALLOW_FILE_CACHE', FALSE);
        
        
        
        //------------------------------------------------------------------------------------------
        //  BANDWIDTH SETTINGS
        //
        //  these settings are only needed when using bandwidth limiting.
        //  
        //  bandwidth is limited my sending a limited amount of video data(XMOOV_BW_PACKET_SIZE),
        //  in specified time intervals(XMOOV_BW_PACKET_INTERVAL). 
        //  avoid time intervals over 1.5 seconds for best results.
        //  
        //  you can also control bandwidth limiting via http command using your video player.
        //  the function getBandwidthLimit($part) holds three preconfigured presets(low, mid, high),
        //  which can be changed to meet your needs
        //------------------------------------------------------------------------------------------    
        
        //set how many kilobytes will be sent per time interval
        define('XMOOV_BW_PACKET_SIZE', 90);
        
        //set the time interval in which data packets will be sent in seconds.
        define('XMOOV_BW_PACKET_INTERVAL', 0.3);
        
        //set to TRUE to control bandwidth externally via http.
        define('XMOOV_CONF_ALLOW_DYNAMIC_BANDWIDTH', FALSE);
        
        
        //------------------------------------------------------------------------------------------
        //  INCOMING GET VARIABLES CONFIGURATION
        //  
        //  use these settings to configure how video files, seek position and bandwidth settings are accessed by your player
        //------------------------------------------------------------------------------------------
        
        //define('XMOOV_GET_FILE', 'file'); // Mame v $this->filePath
        //define('XMOOV_GET_POSITION', 'position'); // Mame v $pos
        //define('XMOOV_GET_BANDWIDTH', 'bw'); // Nepouzivame
        
        
        
        //  END SCRIPT CONFIGURATION - do not change anything beyond this point if you do not know what you are doing
        
        
        
        //------------------------------------------------------------------------------------------
        //  PROCESS FILE REQUEST
        //------------------------------------------------------------------------------------------
        
        // Streamujeme jen v pripade, ze se jedna o soubor 
        if(file_exists($this->filePath))
        {
            //  PROCESS VARIABLES
            
            # get seek position
            $seekPos = intval($pos);
            # Complete filepaht           
            $file = $this->filePath;
            
            # assemble packet interval
            $packet_interval = (XMOOV_CONF_ALLOW_DYNAMIC_BANDWIDTH && isset($_GET[XMOOV_GET_BANDWIDTH])) ? getBandwidthLimit('interval') : XMOOV_BW_PACKET_INTERVAL;
            # assemble packet size
            $packet_size = ((XMOOV_CONF_ALLOW_DYNAMIC_BANDWIDTH && isset($_GET[XMOOV_GET_BANDWIDTH])) ? getBandwidthLimit('size') : XMOOV_BW_PACKET_SIZE) * 1042;
            
            
            if(!is_dir($file))
            {
                $fh = fopen($file, 'rb'); 
                // Zkontrolujeme uspesnost otevreni souboru
                if(!$fh){
                    Phc_ErrorLog::error('FlvStreamer::stream()', 'Nepodarilo se otevrit soubor videa: "'.
                        $file.'".');
                }
                    
                $fileSize = filesize($file) - (($seekPos > 0) ? $seekPos  + 1 : 0);
                
                //  SEND HEADERS
                if(!XMOOV_CONF_ALLOW_FILE_CACHE)
                {
                    # prohibit caching (different methods for different clients)
                    session_cache_limiter("nocache");
                    header("Expires: Thu, 19 Nov 1981 08:52:00 GMT");
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                    header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
                    header("Pragma: no-cache");
                }
                
                # content headers
                header("Content-Type: video/x-flv");
                header("Cache-Control: no-transform");
                header("Content-Disposition: attachment; filename=\"" . str_replace(str_split("\t\n\r\0\x0B"), "", $outfilename) . "\"");
                header("Content-Length: " . $fileSize);
                
                // Hlavicku posilame jen pokud streamujeme od nejake pozice
                if($pos > 0){
                    # FLV file format header
                    if($seekPos != 0) 
                    {
                        print('FLV');
                        print(pack('C', 1));
                        print(pack('C', 1));
                        print(pack('N', 9));
                        print(pack('N', 9));
                    }
                    
                    # seek to requested file position
                    fseek($fh, $seekPos);
                }
                
                # output file
                while(!feof($fh)) 
                {
                    # use bandwidth limiting - by Terry
                    if(XMOOV_CONF_LIMIT_BANDWIDTH)
                    {
                        # get start time
                        list($usec, $sec) = explode(' ', microtime());
                        $time_start = ((float)$usec + (float)$sec);
                        # output packet
                        print(fread($fh, $packet_size));
                        # get end time
                        list($usec, $sec) = explode(' ', microtime());
                        $time_stop = ((float)$usec + (float)$sec);
                        # wait if output is slower than $packet_interval
                        $time_difference = $time_stop - $time_start;
                        if($time_difference < (float)$packet_interval)
                        {
                            usleep((float)$packet_interval * 1000000 - (float)$time_difference * 1000000);
                        }
                    }
                    else
                    {
                        # output file without bandwidth limiting
                        print(fread($fh, $this->streamPiece));
                        flush();
                    }
                }
            }
        }
        else
        {
            // Neexistuje soubor, ktery se ma streamovat
            Phc_ErrorLog::error('FlvStreamer::stream()', 'Soubor videa neexistuje: "'.$file.'".');
        }
    } 
    
    
    /**
     * Pomocna funkce prekladajici parametry o rychlosti predavane XOOM playerem.
     * 
     * @param $part
     * @return double
     */
    public function getBandwidthLimit($part)
    {
        switch($part)
        {
            case 'interval' :
                switch($_GET[XMOOV_GET_BANDWIDTH])
                {
                    case 'low' :
                        return 1;
                    break;
                    case 'mid' :
                        return 0.5;
                    break;
                    case 'high' :
                        return 0.3;
                    break;
                    default :
                        return XMOOV_BW_PACKET_INTERVAL;
                    break;
                }
            break;
            case 'size' :
                switch($_GET[XMOOV_GET_BANDWIDTH])
                {
                    case 'low' :
                        return 10;
                    break;
                    case 'mid' :
                        return 40;
                    break;
                    case 'high' :
                        return 90;
                    break;
                    default :
                        return XMOOV_BW_PACKET_SIZE;
                    break;
                }
            break;
        }
    }
    
}