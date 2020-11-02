<?php
/**
 * iBulletin - FlvStreamer.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

/**
 * Exception
 */
//class Ibulletin_FlvStreamer_Exception extends Exception {}

/**
 * Trida zajistujici streamovani FLV souboru. Soubor musi obsahovat metadata
 * s bytovimi pozicemi jednotlivych klicovych snimku.
 * 
 * V pripade, ze neni pozadovan soubor od bytove pozice nejakeho klicoveho snimku
 * podle metadat, je vracen cely soubor od zacatku.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_FlvStreamer
{
    /**
     * @var string  Lokalni cesta k souboru videa
     */
    var $filePath = null;
    
    
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }
    
    /**
     * Zacne odesilat data video streamu i s hlavickami na zacatku.
     * 
     * @param int   Bytova pozice v souboru videa
     */
    public function stream($pos = 0)
    {
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
                header("Content-Disposition: attachment; filename=\"" . basename($file) . "\"");
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
                        print(fread($fh, filesize($file))); 
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