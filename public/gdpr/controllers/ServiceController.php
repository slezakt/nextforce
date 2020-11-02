<?php
/**
 * Zprostredkovava metody contentuu, ktere slouzi jako sluzby napriklad frontendu
 * jako je Ajax a podobne. Tyto metody nevyzaduji zadne view skripty, jejich vysledkem
 * muze byt napriklad XML s daty pro predani.
 *
 * Metoda volana jako sluzba musi mit na konci Service - napr. logStatusService,
 * v adrese pak prenasime jen cast bez koncoveho Service - "logStatus"
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class ServiceController extends Zend_Controller_Action
{
    /**
     * Spusti sluzbu nejakeho contentu
     */
    public function doAction()
    {
        $db = Zend_Registry::get('db');
        //$config = Zend_Registry::get('config');

        $req = $this->getRequest();

        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);

        Ibulletin_Stats::getInstance()->setAttrib('action', 'service');

        // Ziskame url parametry ktere byly zadany
        $content_id = $req->getParam('contentid');
        $srv_name = $req->getParam('srvname');

        $content = Contents::get($content_id);
        // Pokud je content null, znamena to, ze nebyl nalezen, tedy zalogujeme chybu a skoncime
        if($content === null){
            Phc_ErrorLog::error('ServiceController::do', 'Nepodarilo se najit content s ID="'.
                $content_id.'".');
            return;
        }

        $obj = $content['object'];

        $method =  $srv_name.'Service';
        // Pokud neexistuje pozadovana sluzba, zalogujeme chybu a koncime
        if(!method_exists($obj, $method)){
            Phc_ErrorLog::error('ServiceController::do', 'Nepodarilo se najit sluzbu contentu s ID="'.
                $content_id.'", se jmenem "'.$srv_name.'".');
            return;
        }

        // Spustime pozadovanou sluzbu
        $obj->{$method}($req);


    }

    /**
     * Spusti statickou sluzbu nejakeho typu contentu.
     * Jedna se o contenty ve smyslu typu contentu - tyto sluzby nezavisi na id contentu.
     */
    public function dostaticAction()
    {
        $db = Zend_Registry::get('db');
        //$config = Zend_Registry::get('config');

        $req = $this->getRequest();

        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);

        Ibulletin_Stats::getInstance()->setAttrib('action', 'service');

        // Ziskame url parametry ktere byly zadany
        $contentName = $req->getParam('contentname');
        $srv_name = $req->getParam('srvname');


        // Pokud je content null, znamena to, ze nebyl nalezen, tedy zalogujeme chybu a skoncime
        if(!class_exists('Ibulletin_Content_'.ucfirst($contentName), true)){
            Phc_ErrorLog::error('ServiceController::dostaticAction', 'Nepodarilo se najit objekt contentu:"'.
                'Ibulletin_Content_'.ucfirst($contentName).'".');
            return;
        }

        $method =  $srv_name.'Service';
        // Pokud neexistuje pozadovana sluzba, zalogujeme chybu a koncime
        if(!method_exists('Ibulletin_Content_'.ucfirst($contentName), $method)){
            Phc_ErrorLog::error('ServiceController::dostaticAction', 'Nepodarilo se najit sluzbu objektu contentu:"'.
                'Ibulletin_Content_'.ucfirst($contentName).'", se jmenem "'.$srv_name.'".');
            return;
        }

        // Spustime pozadovanou sluzbu
        call_user_func(array('Ibulletin_Content_'.ucfirst($contentName), $method), $req);


    }

    /**
     * Ulozi rating page predany pomoci AJAXU
     */
    public function savepageratingAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);

        Ibulletin_Stats::getInstance()->setAttrib('action', 'savepagerating');

        $req = $this->getRequest();

        $rating = $req->getParam('rating', null);
        $page_id = $req->getParam('page_id', null);

        if($rating === null || $page_id === null){
            return;
        }

        //Phc_ErrorLog::debug('debug', var_export($_POST, true));

        Ibulletin_Stats::savePageRating($page_id, $rating);
        // Vypiseme vysledek
        $ratingA = Ibulletin_Stats::getPageRating($page_id);
        // Pro like vypisujeme neco jineho nez pro bezny rating
        if($req->getParam('is_like')){
            $this->getResponse()->setHeader('Content-Type', 'text/xml');

            $this->view->usersPageRating = $ratingA['rating'];
            $this->view->pageRating = $ratingA['avg'];
            $this->view->pageRatingUsersCount = $ratingA['count'];

            /*
            $xml = new Ibulletin_XmlClass();
            $xml->__rootName = 'likeResponse';
            $xml->likeTextCDATA = 'xxxxxxxxxxxxxxx'; // CDATA na konci nazvu provede obaleni vystupu do cdata, tedy XML pak muze obsahovat HTML
            header("Content-Type: text/xml");
            echo $xml->getXml();
            */
            // Vyrenderujeme view script s XML vystupem
            $this->render('likexmlresponse');
        }
        else{
            echo round($ratingA['avg']);
        }

    }

    /**
     * Sluzba pro vydani grafu z Chartdirectoru. Wrapper pro getchart.php
     * Spusti getchart.php
     */
    public function getchartAction()
    {
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);

        require_once 'Phc/getchart.php';

    }

}
