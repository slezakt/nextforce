<?php

/**
 * Modul pro schvalovani certifikatu
 *
 * @author Ondra Bláha <ondrej.blaha@pearshealthcyber.com>
 */
class Admin_CertificateController extends Ibulletin_Admin_BaseControllerAbstract {

    /**
     * Seznam atributu uzivatele, ktere se pouzivaji ve vypisu
     * @var array
     */
    var $userAttribs;

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
    public function init() {
        parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
        );

        $this->userAttribs = array_merge(array('id'), explode(',', str_replace(' ', '', $this->config->caseStudies->certificate->usersAttribsApproval)));
    }

    public function indexAction() {

        $contentId = $this->_getParam('id');

        //seznam indetailu
        $indetail = Contents::getList('Ibulletin_Content_Indetail');
        $indetail_list = array();
        foreach ($indetail as $c) {
            $obj = $c['object'];
            if ($obj->ready) {
                $indetail_list[$c['id']] = $c['name'];
            }
        }

        $this->view->indetail_list = $indetail_list;

        if ($contentId) {

            Ibulletin_JS::addJsFile('datatables/datatables.min.js');
            Ibulletin_JS::addJsFile('jszip.js');
            Ibulletin_JS::addJsFile('datatables/Buttons-1.2.2/js/buttons.html5.min.js');
            Ibulletin_HtmlHead::addFile('../scripts/datatables/datatables.min.css');

            //data pro datatables
            $dtColumns = array();

            foreach ($this->userAttribs as $attr) {
                $dtColumns[] = array('data' => $attr, 'class' => $attr . ' text-search', 'title' => ($this->texts->$attr) ? $this->texts->$attr : ucfirst($attr));
            }

            $dtColumns[] = array('data' => 'score', 'class' => 'score', 'title' => ($this->texts->score) ? $this->texts->score : ucfirst('score'));
            $dtColumns[] = array('data' => 'filled', 'class' => 'filled', 'title' => ($this->texts->filled) ? $this->texts->filled : ucfirst('filled'),
                'render' => array('display' => 'display', 'filter' => 'filter'));
            $dtColumns[] = array('data' => 'certificate', 'class' => 'certificate', 'title' => ($this->texts->certificate) ? $this->texts->certificate : ucfirst('certificate'),
                'render' => array('display' => 'display', 'filter' => 'filter'));

            $this->view->dtColumns = $dtColumns;
            $this->view->contentId = $contentId;

            //pozadovany pocet bodu pro filtr
            $contents = Contents::get($contentId);
            $content = $contents['object'];
            $this->view->requiredPoints = $content->points;

            //url pro nacitani uzivatelu
            $this->view->usersScoresUrl = $this->_helper->url('load-users', null, null, array('id' => $contentId));
            //url pro generovani certifikatu
            $this->view->generateUrl = $this->_helper->url('generate-certificate', null, null, array('id' => $contentId)) . '/';
            $this->view->previewUrl = $this->_helper->url('generate-preview', null, null, array('id' => $contentId)) . '/';
        }
    }

    /**
     * Akce zobrazi seznam uzivatelu s dosazenymi body v prezentaci, vypise JSON
     */
    public function loadUsersAction() {

        $id = $this->_getParam('id');

        $this->_helper->viewRenderer->setNoRender(true);

        if (!$id || !is_numeric($id)) {
            return null;
        }

        $users = Statistics::getUsersScores($id);

        $data = array();
        $data['data'] = array();

        $certPath = rtrim($this->config->content_article->basepath, '/') . "/$id/" . $this->config->caseStudies->certificate->folderName;

        foreach ($users as $user) {

            if (file_exists($certPath . '/' . $user['id'] . '.pdf')) {
                $user['certificate'] = array('display' => '<span class="glyphicon glyphicon-ok"></span>', 'filter' => "1");
            } else {
                $user['certificate'] = array('display' => '', 'filter' => '0');
            }

            //kontrola ze jsou vyplneny atributy
            $filled = true;
            foreach ($this->userAttribs as $attrib) {
                if (empty($user[$attrib])) {
                    $filled = false;
                }
            }

            //pripojime do tabulky pro filtry
            $user['filled'] = $filled;
            if ($filled) {
                $user['filled'] = array('display' => '<span class="glyphicon glyphicon-ok"></span>', 'filter' => "1");
            } else {
                $user['filled'] = array('display' => '', 'filter' => '0');
            }


            $data['data'][] = $user;
        }

        $this->_helper->json->sendJson($data);
    }

    /**
     * Akce vygeneruje certifikáty uživatelům
     */
    public function generateCertificateAction() {

        //contentId
        $id = $this->_getParam('id');

        $this->_helper->viewRenderer->setNoRender(true);

        if (!$id || !is_numeric($id)) {
            return null;
        }

        if ($this->getRequest()->isPost()) {

            $data = $this->getRequest()->getPost();

            if (empty($data['users'])) {
                return $this->_helper->json->sendJson(array('status' => 'warning', 'msg' => $this->texts->no_users_selected));
            }

            try {
                Certificates::generate($id, $data['users']);
            } catch (Exception $ex) {
                $this->_helper->json->sendJson(array('status' => 'error', 'msg' => $ex->getMessage()));
            }
            
             $this->_helper->json->sendJson(array('status' => 'success', 'msg' => $this->texts->certificates_created));
        }
        
    }

    /**
     * Akce vygeneruje obrázek s náhledem certifikátu
     */
    public function generatePreviewAction() {

        //contentId
        $id = $this->_getParam('id');
        $userId = $this->_getParam('user');

        $this->_helper->viewRenderer->setNoRender(true);

        if (!$id || !is_numeric($id)) {
            return null;
        }

        if (empty($userId)) {
            return $this->_helper->json->sendJson(array('status' => 'warning', 'msg' => $this->texts->no_users_selected));
        }

        $contents = Contents::get($id);
        $content = $contents['object'];

        $user = Users::getUser($userId);

        require_once('library/mpdf/mpdf.php');
        $pdf = new mPDF();
        $pdf->showImageErrors = true;
        $pdf->WriteHTML(Certificates::parseTemplate($content, $user));

        $im = new Imagick();
        $im->setresolution(150, 150);
        $im->readimageblob($pdf->Output('', 'S'));
        $im->setBackgroundColor('#ffffff');
        
        if (method_exists($im, 'flattenimages')) {
            $im->flattenimages();
        } else {
            $im->setImageAlphaChannel(imagick::ALPHACHANNEL_REMOVE);
            $im->mergeImageLayers(imagick::LAYERMETHOD_FLATTEN);
        }

        $im->setimageformat('png');
        header('Content-Type: image/png');
        echo $im;
        $im->clear();
        $im->destroy();
    }

}
