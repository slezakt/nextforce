<?php
/**
 * Zpracuje a ulozi data o uzivatelove prohlizeci ziskana z ajax pozadavku.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class UserbrwsrinfoController extends Zend_Controller_Action
{
    /**
     * Vlastni akce.
     */
    public function aAction()
    {
        $req = $this->getRequest();
        $stats = Ibulletin_Stats::getInstance();

        $stats->setAttrib('javascript', (bool)$req->getParam('js'));
        $stats->setAttrib('cookies', (bool)$req->getParam('co'));
        $stats->setFlashplayer($req->getParam('fl'));
        $stats->setResolution($req->getParam('res'));

        // Uvedeme do statistik akci "browser detection"
        $stats->setAttrib('action', 'browser detection');

        // Nastavime casovou zonu
        $utcOffset = $req->getParam('utcoffset');
        if($utcOffset !== null){
            $stats->initializeTimezone($utcOffset);
        }

        // Ulozime info o tom, ze detekce pro teuto session jiz byla provedena
        $stats->setBrowserDetectionDone();
        
        // Nerenderovat zadny view skript
        $this->_helper->viewRenderer->setNoRender();
    }
}
