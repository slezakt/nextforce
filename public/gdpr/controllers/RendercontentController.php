<?php
/**
 * Vyrenderuje jeden konkretni content podle ID, uzivatel musi byt prihlasen v adminu.
 * Pouziva se pro testovani contentuu jako jsou inCampaign HTML prezentace
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class RendercontentController extends Zend_Controller_Action
{
    /**
     * Vyrenderuje content zadany pomoci ID v parametru content.
     *
     * V teto verzi neni vhodny pro pouziti ve frontendu!
     */
    public function renderAction()
    {
        $req = $this->getRequest();

        $contentId = $req->getParam('content');

        if(empty($contentId)){
            $this->view->error = 'No content ID was set.';
            return;
        }

        $content = Contents::get($contentId);
        if(empty($content)){
            $this->view->error = "Content id '$contentId' was not found.";
            return;
        }

        if(!Zend_Auth::getInstance()->hasIdentity() && !$this->renderForUser($content)){
            $this->view->error = 'ACCESS DENIED';
            return;
        }

        // pripravime renderovaci data pro vypsani tohoto contentu
        Zend_Loader::loadFile('BulletinController.php', 'controllers', true);
        // vyrenderujeme content do stringu identicky jako na frontu
        $bulletinController = new BulletinController($this->getRequest(), $this->getResponse(), array('noViewRenderer' => true));


        $bul = Contents::getBulletin($contentId);

        if ($bul['page_layout_name']) {
            Layouts::setLayout($bul['page_layout_name'], $bulletinController);
        } else if ($bul['layout_name']) {
            Layouts::setLayout($bul['layout_name'], $bulletinController);
        }

        $content_tpl = array();
        $current_content = 1;
        $contentArray = array();
        $bulletinController->renderContent(
            $current_content, $content['object'], NULL, $this->getRequest(), $contentArray, $content_tpl
            );

        $this->view->content_tpl = $content_tpl;
        $this->view->content = $contentArray;
    }

    /**
     * Metoda overuje, zda content lze vyrenderovat uzivateli na frontu, coz se vyuziva pri zobrazovani indetailu v iframe
     * @param array $content
     * @return bool
     */
    private function renderForUser($content) {

        //neni-li content indetail ukoncime s false
        if ($content['class_name'] != 'Ibulletin_Content_Indetail') {
            return false;
        }

        //neni-li uzivatel prihlasen ukoncime
        if(!Ibulletin_Auth::getActualUserId()) {
            return false;
        }

        $config = Zend_Registry::get('config');
        //neni-li zobrazovani indetalu v iframu zamitneme
        //if (empty($config->indetail->renderToIframe)) {
        //     return false;
        //}


        $content_pages = Pages::getContentPages($content['id']);
        //neni-li content v page ukoncime
        if (!$content_pages) {
            return false;
        }


        foreach($content_pages as $cp) {
           //je-li stranka zarazena do bulletinu vracime true
           $bulletins = Pages::getPageBulletins($cp['id']);
           if ($bulletins) {
               return true;
           }

        }

        return false;
    }
}
