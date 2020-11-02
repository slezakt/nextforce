<?php
/**
 * Vypise seznam vsech cisel bulletinu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class ArchiveController extends Zend_Controller_Action
{
    /**
     * Vypise seznam vsech cisel bulletinu.
     * 
     */
    public function indexAction()
    {
        Zend_Loader::loadClass('Ibulletin_ShowContent');
        Zend_Loader::loadClass('Bulletins');
        
        $showCont = Ibulletin_ShowContent::getInstance();
        $bulletins = new Bulletins();
        $req = $this->getRequest();
        
        $bulletin_url_name = $req->getParam('bulletin');
        $bulletin_id = $bulletins->findBulletinId($bulletin_url_name, true);
        if($bulletin_id === false){
            // Stranka nebyla nalezena, vyhodime odpovidajici exception
            require_once('Zend/Controller/Dispatcher/Exception.php');
            throw new Zend_Controller_Dispatcher_Exception($e);
        }
        
        $this->view->is_archive = TRUE;
        // Data bulletinuu
        $this->view->bulletin_list = $bulletins->getBulletinList();
        
        // Pripravime data pro menu
        $this->view->menu = $showCont->renderMenu($bulletin_id);
        
        // Cely radek bulletinu
        $this->view->bulletinRow = $bulletins->get($bulletin_id);
        // Nazev bulletinu, k pouziti na strance
		$this->view->bulletin_name = $this->view->bulletinRow['name'];
        // Zapiseme url_name bulletinu do view
        $this->view->bul_url_name = $this->view->bulletinRow['url_name'];
		
		// Zaznam nejaktualnejsiho bulletinu pro ruzne operace ve view
        $this->view->actual_bul_row = $bulletins->getActualBulletinRow(false);
		
		// Seznam vsech bulletinu pro menu iTrio a inCast
        $this->view->all_bulletins = $bulletins->getBulletinList(true, array('created ASC', 'valid_from ASC'));
        
        // Layout podle nastaveni u vydani
        if(!empty($this->view->bulletinRow['layout_name'])){
            Layouts::setLayout($this->view->bulletinRow['layout_name'], $this);
        }
        
        /**
         * Dodelana moznost si tahnout bulletin v PDF z archivu
         * @author Alexej Viatkine
         */
        foreach ($this->view->bulletin_list as $key => $bulletin) {
            $config = Zend_Registry::get('config');
            
            $pdfFileName = $bulletin['url_name'] . ".pdf";
            $pdfDir = $config->archive->pdfPath;

            if(file_exists($pdfDir . $pdfFileName)) {
                $this->view->bulletin_list[$key]['pdfUrl'] = $pdfDir . $pdfFileName;
            }
        }
    }
}
