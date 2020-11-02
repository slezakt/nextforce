<?php
/**
 * Zajistuje nastaveni posunu casu pro zobrazovani dynamickych obsahu
 * pomoci parametru v url. 
 * 
 * Pouziti: 
 * timeshift/add/days/5 - posune cas o pet dnu vpred
 * timeshift/sub/days/3 - posune cas o tri dny zpet
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class TimeshiftController extends Zend_Controller_Action
{
    /**
     * Posune cas o zadany pocet dnu vpred.
     * 
     * Pouziti:
     * timeshift/add/days/5 - posune cas o pet dnu vpred
     */
    public function addAction()
    {
        $config = Zend_Registry::get('config');
        
        $shift = $this->getRequest()->getParam('days');
        $now = new Zend_Date();
        
        if($shift !== null){
            $session = Zend_Registry::get('session');
            $session->timeshift = $shift;
            $now = $now->addDayOfYear((int)$shift);
        }
        
        $this->view->message = sprintf(Ibulletin_Texts::get('newDateForThisSessionIs'), 
                                       $now->toString($config->general->dateformat->short));
    
        $this->getHelper('viewRenderer')->renderScript('info.phtml');
        return;
    }
    
    /**
     * Posune cas o zadany pocet dnu zpet.
     * 
     * Pouziti:
     * timeshift/sub/days/5 - posune cas o pet dnu vzad
     */
    public function subAction()
    {
        $config = Zend_Registry::get('config');
        
        $shift = $this->getRequest()->getParam('days');
        $now = new Zend_Date();
        
        if($shift !== null){
            $session = Zend_Registry::get('session');
            $session->timeshift = (int)$shift * (-1);
            $now = $now->addDayOfYear((int)$shift * (-1));
        }
        
        $this->view->message = sprintf(Ibulletin_Texts::get('add.newDateForThisSessionIs'), 
                                       $now->toString($config->general->dateformat->short));
    
        $this->getHelper('viewRenderer')->renderScript('info.phtml');
        return;
    }
}