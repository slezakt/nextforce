<?php
/**
 * ClientXmlRpc.php
 * 
 * @author  Bc. Petr Skoda
 */

/**
 * XML-RPC
 * Vola vzdalene funkce poskytovane aplikaci na jeji URL se zadanou verzi protokolu na konci url
 * '/1.0/'. Kazde funkci je jako prvni parametr pred klic aplikace k overeni opravneni.
 * vzdalenaFunkce($klic)
 * 
 * @author Bc. Petr Skoda
 */
class Communicator_ClientXmlRpc extends Communicator_ClientAbstract
{
    /**
     * Klient pro komunikaci pomoci XML-RPC
     * @var Zend_XmlRpc_Client
     */
    private $_client = null;
    
    
    /**
     * Nastavi adresu aplikace, verzi protokolu a inicializuje XmlRpc klienta.
     * 
     * @param string $addr    Adresa ciloveho adapteru
     * @param string $key     Klic aplikace pro overeni opravnenosti volani
     * @param int    $appId  ID teto aplikace v evidenci inDirectoru
     */
    public function __construct($addr = null, $key = null, $appId = null)
    {
        parent::__construct($addr, $key, $appId);
        
        // Zalozeni XML-RPC klienta (na konec URL zadame verzi protokolu)
        $this->_client = new Zend_XmlRpc_Client($this->_address.'/'.$this->_protocolVer.'/');
        //Phc_ErrorLog::error('ADDRESSS', $this->_address.'/'.$this->_protocolVer.'/');
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
    protected function call($function, $args)
    {
        try{
            $result = $this->_client->call($function, $args);
        }
        catch(Zend_XmlRpc_Client_HttpException $e){
            throw new Communicator_ClientException(
                "Nepodarilo se provest HTTP request. Puvodni vyjimka:\n".$e, 1);
        }
        catch(Zend_XmlRpc_Client_FaultException $e){
            throw new Communicator_ClientException(
                "Chyba pri vykonavani vzdalene metody. Puvodni vyjimka:\n".$e, 2);
        }
        
        
        return $result;
    }
}