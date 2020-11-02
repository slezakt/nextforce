<?php
/**
 * JavascriptcodeController.php
 * 
 * @author Bc. Petr Skoda
 */



/**
 * Vrati poskladany vsechny JS soubory pro dane nacteni stranky. Je zavisla na SESSION.
 * Pokud by vyvstala potreba umet to i bez session, je treba ukladat informace o souborech
 * k include JS v DB.
 * 
 */
class JavascriptcodeController extends Zend_Controller_Action
{
    /**
     * Vypise JS
     */
    public function getAction()
    {
        $req = $this->getRequest();
        $id = $req->getParam('id', null);

        Ibulletin_Stats::getInstance()->setAttrib('action', 'javascriptcode');
        
        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);
        Env::disableOutput();

        // Pokud neni zadano ID koncime.
        if($id === null){
            return;
        }
        
        Ibulletin_Js::renderAllJsFilesCode($id);
    }
}
