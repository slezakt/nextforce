<?php
/**
 * ClientAbstract.php
 * 
 * @author  Bc. Petr Skoda
 */

/**
 * 
 * POZOR, pri garantovanem doruceni a odeslani pozdeji nevraci zadnou navratovou hodnotu volane funkce.
 * 
 * @author Bc. Petr Skoda
 */
abstract class Communicator_ClientAbstract
{
    /**
     * Adresa ktera bude volana bez verze protokolu na konci
     */
    protected $_address = null;
    
    /**
     * Klic aplikace k urceni opravnenosti pozadavku
     */
    protected $_key = null;
    
    /**
     * ID teto aplikace v evidenci inDirectoru
     * @var int
     */
    protected $_applicationId = null;
    
    /**
     * Verze protokolu pro komunikaci - pridava se do url volani sluzby
     */
    protected $_protocolVer = null;
    
    /**
     * Odeslat data ihned (true) nebo odeslat az pri destrukci objektu(false)?
     * @var bool
     */
    protected $_sendImmediate = false;
    
    /**
     * Maji byt data pred odeslanim persistentne ulozena - zarucene odeslani?
     * Pro pozadavky napriklad do aplikaci tykajici se bezprostredniho zobrazeni stranky
     * je nutne nastavit $_guaranteedDelivery na FALSE.
     * @var bool
     */
    protected $_guaranteedDelivery = true;
    
    /**
     * Volani odlozena na pozdeji
     * @var array - (functionName, args, timestamp_iso8601)
     */
    protected $_laterCalls = array();
    
    /**
     * Maji se predvyplnit informace potrebne pro komunikaci s inDirectorem?
     * $key, $app_id, $timestamp
     * Nemelo by se menit po tom, co byla provedena zarucena nebo odlozena volani.
     * @var bool
     */
    protected $_prefillIndirIdent = true;
    
    /**
     * @var bool  Metoda __call neprovadi akce podle priznakuu, jen provede primo 
     *            vzdalene volani, nedoplnuje zadne promenne. Nemela by se nastavovat z venku.
     */
    protected $_callPlain = false;
    
    
    /**
     * Nastavi adresu aplikace a verzi protokolu.
     * 
     *  Verze protokolu je zatim pevne 1.0
     * 
     * @param string $addr    Adresa ciloveho adapteru
     * @param string $key     Klic aplikace pro overeni opravnenosti volani
     * @param int    $appId  ID teto aplikace v evidenci inDirectoru
     */
    public function __construct($addr = null, $key = null, $appId = null)
    {
        $config = Zend_Registry::get('config');
        // Verze protokolu
        $this->_protocolVer = '1.0';

        if($addr === null){
             $this->setAdderess($config->indirector->indirectorUrl);
        }
        else{
            $this->setAdderess($addr);
        }
        
        if($key === null){
            $this->_key = $config->indirector->key;
        }
        else{
            $this->_key = $key;
        }
        
        if($appId === null){
            $this->_applicationId = (int)$config->indirector->appId;
        }
        else{
            $this->_applicationId = $appId;
        }
        
        
    }
    
    /**
     * Nastavi adresu aplikace
     * 
     * @param string    Adresa aplikace
     */
    public function setAdderess($addr)
    {
        $this->_address = $addr;
    }
    
    
    /**
     * Nastavi priznak sendImmediate
     * @param bool
     */
    public function setSendImmediate($val)
    {
        $this->_sendImmediate = (bool)$val;
    }
    
    
    /**
     * Nastavi priznak guaranteedDelivery
     * @param bool
     */
    public function setGuaranteedDelivery($val)
    {
        $this->_guaranteedDelivery = (bool)$val;
    }
    
    
    /**
     * Nastavi priznak prefillIndirIdent
     * @param bool
     */
    public function setPrefillIndirIdent($val)
    {
        $this->_prefillIndirIdent = (bool)$val;
    }
    
    
    /**
     * Factory pro ziskavani instanci communicatoru.
     * 
     * Zatim pouzivame vyhradne XML-RPC, takze vzdy vraci Communicator_ClientXmlRpc  
     * 
     * @param string $addr    Adresa ciloveho adapteru
     * @param string $key     Klic aplikace pro overeni opravnenosti volani
     * @param int    $appId  ID teto aplikace v evidenci inDirectoru
     * 
     * @return Communicator_ClientAbstract
     */
    public static function factory($addr = null, $key = null, $appId = null)
    {
        $com = new Communicator_ClientXmlRpc($addr, $key, $appId);
        return $com;
    }
    
    
    /**
     * Volani vzdalenych fulkci jako metod teto tridy.
     * Volame jako $this->jmenoVzdaleneFunkceCall() - tedy na konec jmena vzdalene funkce je 
     * pridano Call.
     * 
     * @param string
     * @param array
     * @throws Communicator_ClientException
     */
    public function __call($name, $args)
    {
        $m = array();
        if(!preg_match('/^(.*)Call$/', $name, $m)){
            //trigger_error('Method '.$name.' is not avaliable.', E_USER_ERROR);
            //return;
            throw new Communicator_ClientException('Volana neexistujici metoda '.$name.'.', 0);
        }
        $function = $m[1];
        
        // Pokud se mame ridit podle priznaku, vykoname akce dle priznaku
        if(!$this->_callPlain){
            $config = Zend_Registry::get('config');
            // Pokud maji byt doplneny parametry pro komunikaci s inDirectorem, doplnime je
            if($this->_prefillIndirIdent){
                // Timestamp s mikrosekundami
                list($usec, $sec) = explode(" ",microtime());
                $date = new Zend_Date($sec);
                $date->setFractionalPrecision(6);
                $date->setMilliSecond((int)((float)$usec * pow(10, $date->getFractionalPrecision())));
                $timestamp = $date->toString($config->general->dateformat->preciseIso);
                
                $inDirParams = array(
                    $this->_key,
                    (int)$this->_applicationId,
                    $timestamp
                    );
                    
                $args = array_merge($inDirParams, $args);
            }
            
            // Pokud ma byt ulozeno persistentne, zavolame persistentni ulozeni a skonicme
            if($this->_guaranteedDelivery){
                $this->_saveGuaranteedCall($function, $args);
                return;
            }
            
            // Pokud ma byt odeslano pozdeji, ulozime parametry volani a budeme vyrizovat pozdeji
            if(!$this->_sendImmediate){
                
                if(!Zend_Date::isDate($args[2], Zend_Date::ISO_8601)){
                    list($usec, $sec) = explode(" ",microtime());
                    $date = new Zend_Date($sec);
                    $date->setFractionalPrecision(6);
                    $usec = (int)((float)($usec) * pow(10,6));
                    $date->setMilliSecond($usec, 6);
                    $dateStr = $date->toString($config->general->dateformat->preciseIso);
                }
                else{
                    $dateStr = $args[2];
                }
                
                $this->_laterCalls[] = array('name' => $function, 'args' => $args, 'timestamp' => $dateStr);
                return;
            }
        }
        
        // Zavolame metodu se zadanymi parametry
        $result = $this->call($function, $args);
        
        return $result;
    }
    
    
    /**
     * Volani vzdalene funkce, muzeme volat i pres magickou metodu, tedy volat vzdalenou
     * funkci jako by byla metodou tohoto objektu (za nazev metody pridame 'Call'):
     * $this->jmenoVzdalFceCall();
     * 
     * @param string    Jmeno vzdalene funkce.
     * @param array     Pole obsahujici jednotlive parametry pro zadani ve volani
     * 
     * @return mixed    Navratova hodnota vzdalene funkce.
     */
    protected abstract function call($function, $args);
    
    
    /**
     * Vytvori soubor v adresari pro garantovana volani a serializuje do nej data volani -
     * pole s klici 'name' (obsahuje nazev volane vzdalene funkce) a 'args' (pole obsahujici 
     * argumenty k predani teto funkci) 
     * 
     * @param string $name  Nazev volane vzdalene funkce vcetne Call na konci
     * @param array $args
     */
    protected function _saveGuaranteedCall($name, $args)
    {
        $config = Zend_Registry::get('config');
        $dir = $config->indirector->guaranteedCacheDir;
        
        /*
        // Vytvorime soubor kam ulozime data
        while(1){
            list($usec, $sec) = explode(" ",microtime());
            $time = ((float)$usec + (float)$sec);
            $timeStr = str_replace('.', '_', (string)$time);
            if(!file_exists($dir.'/'.$timeStr)){
               $fp = fopen($dir.'/'.$timeStr, 'w');
               break; 
            }
        }
        */
        
        // Pripravime timestamp
        if(!Zend_Date::isDate($args[2], Zend_Date::ISO_8601)){
            list($usec, $sec) = explode(" ",microtime());
            $date = new Zend_Date($sec);
            $date->setFractionalPrecision(6);
            $usec = (int)((float)($usec) * pow(10,6));
            $date->setMilliSecond($usec, 6);
        }
        else{
            $date = self::parseTimestampPreciseIso($args[2], 6);
        }
        
        // Vytvorime soubor
        // POZOR $date->toString(Zend_Date::TIMESTAMP.'_S') nefunguje
        $timeStr = (string)$date->getTimestamp().$date->toString('_S');
        
        $fp = fopen($dir.'/'.$timeStr, 'w+');
        if(flock($fp, LOCK_EX | LOCK_NB)){
            
            // Zapiseme serializovane pole
            $data = array(
                'name' => $name, 
                'args' => $args, 
                'timestamp' => $date->toString($config->general->dateformat->preciseIso)
                );
            $serialized = serialize($data);
            if(fputs($fp, $serialized) === false){
                // nepodarilo se zapsat, zalogujeme
                Phc_ErrorLog::error('Communicator_ClientAbstract::_saveGuaranteedCall()', 
                    "Nepodarilo se zapsat serializovana data do souboru: '".$dir.'/'.$timeStr."', data:\n$serialized");
            }

            // Odemknout
            flock($fp, LOCK_UN);
        }
        else{
            // nepodarilo se zapsat, zalogujeme
            Phc_ErrorLog::error('Communicator_ClientAbstract::_saveGuaranteedCall()', 
                "Nepodarilo se zapsat serializovana data v dusledku zamku do souboru: '".$dir.'/'.$timeStr."', data:\n$serialized");
        }
        
        
        fclose($fp);
    }
    
    
    /**
     * Spusti vsechna odlozena a vsechna zarucena (persistentni) volani.
     * 
     * Persistentni volani nevykonava pokud je nektery soubor zamcen, protoze
     * to znamena, ze volani nejspise provadi jiny proces.
     * 
     * @param array $excludeFiles Jmena souboru, ktere se nemaji provadet - obvykle protoze byly
     *                            jiz v tomto procesu neuspesne volany.
     * @return array isLocked => bool - jsou zarucena volani zamcena?
     *               unsuccessful => array - jmena souboru, ktere se nepodarilo vykonat
     *               allDone => bool - v adresari jiz nejsou zadne operace k provedeni? 
     *                          (kontroluje na konci sveho chodu)
     */
    public function runDefferedCalls($excludeFiles = array())
    {
        $config = Zend_Registry::get('config');
        
        //
        // Nacteme vsechna volani jak ze souboru tak z pole do noveho pole
        //
        $calls = array();
        foreach($this->_laterCalls as $key => $call){
            $calls[] = $call;
            unset($this->_laterCalls[$key]);
        }
        $sorter = array();
        
        // Nacteme zarucena volani
        $myLocks = array(); // Pole ukazatelu na soubory, abychom je mohli zase odemknout
        $isLocked = false;
        $dir = $config->indirector->guaranteedCacheDir;

        if(file_exists($dir) && ($dirh = opendir($dir))){
        	while($dir_element = readdir($dirh)){
        	    if(!is_file($dir.'/'.$dir_element) || !is_readable($dir.'/'.$dir_element)){
        	        continue;
        	    }
        	    // Preskakovane soubory
        	    if(in_array($dir_element, $excludeFiles)){
        	        continue;
        	    }
        	    
        	    $fp = fopen($dir.'/'.$dir_element, 'r');
        	    
        	    // Kontrolujeme zamky a zamikame, pokud je kterykoli soubor zamcen, nebudeme volani
        	    // ze souboru provadet, protoze je nejspis prave provadi jiny proces.
        	    if(flock($fp, LOCK_EX | LOCK_NB)){
            	    //$serialized = file_get_contents($dir.'/'.$dir_element);
            	    if(filesize($dir.'/'.$dir_element) == 0){
            	        continue;
            	    }
            	    $serialized = fread($fp, filesize($dir.'/'.$dir_element));
            	    $call = unserialize($serialized);
            	    
            	    // Ulozime cestu k souboru, aby mohl byt po uspesnem provedeni volani smazan
                    $call['file'] = $dir.'/'.$dir_element;
            	    $call['filename'] = $dir_element;
            	    $call['filepointer'] = $fp;
            	    
            	    $calls[] = $call;
        	    }
        	    else{
        	        $isLocked;
        	        break;
        	    }
        	    
        	}
        	closedir($dirh);
        }
        
        // Pokud byl nejaky soubor zamcen, vse odemkneme a odstranime volani souboru z $calls
        if($isLocked){
            foreach($myLocks as $fp){
                flock($fp, LOCK_UN);
            }
            foreach($calls as $key => $call){
                if(isset($call['file'])){
                    unset($calls[$key]);
                }
            }
        }
        
        
        //
        // Seradime podle casu
        //
        $callsSort = array();
        foreach($calls as $key => $call){
            $date = self::parseTimestampPreciseIso($call['timestamp'], 6);
            $callsSort[$key] = $date;
        }
        asort($callsSort);
        
        //
        // Spustime
        //
        $unsuccessful = array();
        $this->_callPlain = true;
        foreach($callsSort as $key => $date){
            $call = $calls[$key];
            try{
                $this->call($call['name'], $call['args'], true);
                // (volame primo call, protoze pri volani pres __call zpusobi vyjimka ukonceni foreach)
                
                // Pokud se jedna o soubor - zarucene provedeni, smazeme jej
                if(isset($call['file']) && file_exists($call['file']) && is_readable($call['file'])
                    && flock($call['filepointer'], LOCK_EX | LOCK_NB)){
                    fclose($call['filepointer']);
                    unlink($call['file']);
                    @flock($call['filepointer'], LOCK_UN);
                }
            }
            catch(Exception $e){
                $this->_callPlain = false;
                if(isset($call['filename'])){
                    $unsuccessful[] = $call['filename'];
                }
                Phc_ErrorLog::error('Communicator_ClientAbstract::runDefferedCalls()', 
                    "Nepodarilo se vykonat vzdalenou funkci pri zpozdenem zpracovani. Puvodni vyjimka:\n$e");
            }
        }
        $this->_callPlain = false;
        
        foreach($myLocks as $fp){
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        
        
        // Kontrolujeme, jestli nepribyly dalsi soubory pro zpracovani
        $allDone = true;
        $dir = $config->indirector->guaranteedCacheDir;

        if(file_exists($dir) && ($dirh = opendir($dir))){
            while($dir_element = readdir($dirh)){
                if(!is_file($dir.'/'.$dir_element) || !is_readable($dir.'/'.$dir_element)){
                    continue;
                }
                // Preskakovane soubory
                if(in_array($dir_element, $excludeFiles)){
                    continue;
                }
                
                $allDone = false;
                break;
            }
        }
        
        
        return array('isLocked' => $isLocked, 'unsuccessful' => $unsuccessful, 'allDone' => $allDone);
    }
    
    
    /**
     * Provede odlozena volani a persistentni volani.
     * Opakuje dokud se mu nepodari vykonat vsechny zarucene dotazy mimo tech, ktere se nepovedly.
     * Maximalne 10x. 
     * 
     * PROVADI flush()!
     * 
     * !!! Ukoncuje session pred zacatkem sve prace.
     */
    public function __destruct()
    {
        //@ob_end_flush();
        //flush();
        
        ob_start();
        // Ukoncime session, aby k nim mohly pristupovat jine behy skriptu
        session_write_close();
        
        $unsuccessful = array();
        for($i = 0; $i < 10; $i++){
            $result = $this->runDefferedCalls($unsuccessful);
            $isLocked = $result['isLocked'];
            $unsuccessful = $result['unsuccessful'];
            $allDone = $result['allDone'];
            if($isLocked || $allDone){
                break;
            }
        }
        
        ob_end_clean();
    }
    
    
    /**
     * Rozparsuje timestamp v ISO 8601 i s desetinami u sekund do Zend_Date.
     * Soucasne nastavi presnost v Zend_Date na pozadovanou hodnotu.
     * Presne datum lze potom ziskat pomoci $date->toString($config->general->dateformat->preciseIso),
     * format preciseIso v configu je 'YYYY-MM-ddTHH:mm:ss.SZZZZ'
     * 
     * @param string $timestamp ISO 8601 s desetinami u sekund
     * @param int $precision Pocet desetinnych mist se kterymi ma byt Zend_Date nastaven
     * @return Zend_Date    Rozparsovane datum vcetne mikrosekund
     */
    public static function parseTimestampPreciseIso($timestamp, $precision = 6)
    {
        $date = new Zend_Date($timestamp, Zend_Date::ISO_8601);
        $date->setFractionalPrecision($precision);
        // Protoze ZEND a PHP jsou uplne k nicemu, musime si rucne vyparsovat desetiny sekund
        $m = array();
        preg_match('/[0-9]{1,2}[\.:][0-9]{1,2}[\.:][0-9]{1,2}\.([0-9]*)/', $timestamp, $m);
        // Spocitame bezpecne int o spravnem rozmeru ($precision), protoze nevime kolik mist bylo zadano v timestamp
        $usec = isset($m[1]) ? (int)((float)('0.'.$m[1]) * pow(10, $precision)) : 0;
        $date->setMilliSecond($usec, $precision);
        
        return $date;
    }
}