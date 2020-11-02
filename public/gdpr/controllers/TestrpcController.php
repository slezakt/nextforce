<?php
/**
 * Controller, ktery posila pozadavky na zjisteni informaci o uzivateli neustale
 * za sebou, idealne spustit ve vice oknech prohlizece pro provedeni 
 * skutecneho zatezoveho testu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class TestrpcController extends Zend_Controller_Action
{
    /**
     * 
     */
    public function getlastvisitsAction()
    {
        $testLenght = 30;   // Delka testu v sec
        $server_url = APPLICATION_URL . '/xmlrpc/';
        //echo $server_url;
        //exit;
        
        // POZOR!!!! Adresa musi byt perfektne zformovana, protoze rpc klient zrejme
        // sam od sebe nezvladne zadny redirect!! Tedy nikde dvojite lomitko (mimo http://)
        // a na konci adresy adresare musi byt lomitko napr.:
        // http://vyvoj.ibulletin.farmacie.cz/xmlrpc/
        if(preg_match('/http:\/\/localhost/', $server_url)){
            $server_url = 'http://127.0.0.1:8080/PHC/ibulletin/www/xmlrpc/';
        }
        
        $i = 0;
        $timeCount = 0;
        $absoluteStart = $this->getmicrotime();
        
        while($i < 15){
            $i++;
            
            // Vygenerujeme nahodne ID uzivatele
            $id = rand(1, 20);
            
            // START mereni
            $timeStart = $this->getmicrotime();
            
            // Kvuli mereni casu vytvarime clienta a tedy i spojeni znovu a znovu
            $client = new Zend_XmlRpc_Client($server_url);
            $server = $client->getProxy();
            
            // Provedeme volani
            $visit = $server->lastVisit($id);
            //$visit = $client->call('lastVisit', array($id));
            
            
            // Konecne mereni casu
            $timeEnd = $this->getmicrotime();
            $duration = $timeEnd - $timeStart;
            $timeCount += $duration;

            // Zjisteni vysledku
            if($visit === false){
                $result = "uzivatel neexistuje";
            }
            elseif($visit === true){
                $result = "uzivatel jeste web nenavstivil";
            }
            else{
                $result = $visit;
            }
            
            // Vypiseme vysledek volani
            echo "Uzivatel s ID '$id' cas: $duration vysledek: ".$result."</br>\n";
            
            unset($client);
            unset($server);
            // Pokud ubehl urceny cas od zacatku testovani, ukoncime
            if(($timeEnd - $absoluteStart) > $testLenght ){
                break;
            }
        }
        
        // Spocitame prumerny cas jednoho RPC volani
        $avgTime = $timeCount / $i;
        echo '<b>Prumerny cas jednoho RPC dotazu: '.$avgTime."</b></br>\n";
        
        // Nerenderovat zadny view skript
        $this->_helper->viewRenderer->setNoRender();
    }
    
    function getmicrotime(){ 
        list($usec, $sec) = explode(" ",microtime()); 
        return ((float)$usec + (float)$sec); 
    } 
}
