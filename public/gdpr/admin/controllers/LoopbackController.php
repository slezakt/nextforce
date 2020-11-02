<?php
/**
 * Vraci data predana v JSON datech v objektu s dodanymi HTTP hlavickami.
 * 
 * Slouzi napriklad pro ziskani stazitelneho obrazku grafu generovaneho v JS.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Admin_LoopbackController extends Zend_Controller_Action
{
    /**
     * Vezme prijata POST data, rozbali JSON objekt z POST promenne "json" a nastavi HTTP 
     * hlavicky obsazene v JSON datech. Vse pak vrati zase zpet.
     * 
     * Ukazka:
     * var json = {data:imgData, base64: true, headers: {
     *          "Content-Disposition": "attachment; filename=chart_"+'<?=urlencode($this->presentationName)?>',
     *          "Content-Type": "application/x-download"
     * }
     * 
     */
    public function getAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);
        // Response
        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();
        
        $json = $this->_request->getParam('json');
        
        if(empty($json)){
            Phc_ErrorLog::warning('Admin_LoopbackController::get()', 'No JSON data received.');
            $response->setHttpResponseCode(401);
            echo 'Error, no JSON data received.';
            return;
        }
        
        // Dekodovani a kontrola dat
        $data = json_decode($json);
        if($data === null || empty($data->data)){
            Phc_ErrorLog::warning('Admin_LoopbackController::get()', "Wrong JSON data received. Data:\n".$json);
            $response->setHttpResponseCode(401);
            echo 'Error, wrong JSON data received.';
            return;
        }
        
        // Nastavime headers pokud nejake jsou
        if(!empty($data->headers) && get_class($data->headers) == 'stdClass'){
            foreach($data->headers as $name => $val){
                $response->setHeader($name, $val, true);
            }
        }
        
        // Dekodovat base64
        if(!empty($data->base64)){
            $data->data = base64_decode($data->data);
        }
        
        echo $data->data;
    }
}
