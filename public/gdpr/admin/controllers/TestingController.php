<?php
/**
 * Modul testování
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Admin_TestingController extends Ibulletin_Admin_BaseControllerAbstract {
    
    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
	public function init() {
        parent::init();
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'indetail' => array('title' => $this->texts->submenu_indetail, 'params' => array('action' => 'indetail'), 'noreset' => false),
            'availablepages' => array('title' => $this->texts->submenu_availablepages, 'params' => array('action' => 'availablepages'), 'noreset' => false),
            'slides' => array('title' => $this->texts->submenu_slides, 'params' => array('action' => 'slides'), 'noreset' => false)
        );

    }

    public function indexAction() {
        
    }
    
    /**
     * Testovani indetailu
     */
    public function indetailAction() {

        $id = $this->_request->getParam('id', null);
        
        //seznam testovatelnych indetailu
        $indetail = Contents::getList('Ibulletin_Content_Indetail');
        $indetail_list = array();
        foreach ($indetail as $c) {
            $obj = $c['object'];
            if ($obj->ready) {
                $indetail_list[$c['id']] = $c['name'];
            }
        }
        $this->view->indetail_list = $indetail_list;

        if ($id) {
            
            $c = Contents::get($id);
            $this->view->indetail_name = $c['name'];
            $this->view->contentId = $id;
            $this->view->contentObj = $c['object'];

            $this->basePath = $this->config->content_article->basepath . '/' . $id . '/flash/';
            $this->basePath = Zend_Filter::filterStatic($this->basePath, 'NormPath');

            $this->view->contentId = $id;
            $this->view->basePath = $this->basePath;
        }
    }
    
    
    /**
     * Testovani slidů
     */
    public function slidesAction() {

        $id = $this->_request->getParam('id', null);
        
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

        if ($id) {
            $c = Contents::get($id);
            $this->view->indetail_name = $c['name'];
            $this->view->contentID = $id;
            $slides = Slides::getSlidesData($id);
            
            unset($slides['content_id']);
            
            $this->view->slides = $slides;
            
            $this->view->slide_previews = Slides::getSlidesPreviews($c);
            
            $this->view->question_types = Questions::getTypes();

        }
    }

    /*
     * Zobrazeni dostupnych page, setridenych dle entity
     */    
    public function availablepagesAction() {

        $db = Zend_Registry::get('db');
        Ibulletin_Js::addJsFile('typeahead.js');

        //users
        $seluser = $db->select()->from('users')->where('deleted IS NULL')->order('id DESC');
        $users = $db->fetchAll($seluser);
        $this->view->users_all = $users;

        //bulletins
        $bulletins = Bulletins::getBulletinList(true, null, false, true);

        $this->view->bulletins_all = $bulletins;

        //pages
        $selpage = $db->select()->from(array('bp' => 'bulletins_pages'), array(new Zend_Db_Expr('DISTINCT ON(bp.page_id) bp.page_id')))
                ->joinLeft(array('b' => 'bulletins'), 'bp.bulletin_id = b.id', null)
                ->joinLeft(array('p' => 'pages'), 'bp.page_id = p.id', array('name' => 'p.name'))
                ->where('b.deleted IS NULL')->where('p.deleted IS NULL')
                ->order('bp.page_id DESC');
        $pages = $db->fetchAll($selpage);
        $this->view->pages_all = $pages;

        //reps
        $selreps = $db->select()->from('users')->where('is_rep IS true')->where('deleted IS NULL')->order('id DESC');
        $reps = $db->fetchAll($selreps);
        $this->view->reps_all = $reps;

        //segments
        $segsel = $db->select()->from('segments', array('id', 'name'))->where('deleted IS NULL')->order('id DESC');
        $segments = $db->fetchAll($segsel);
        $this->view->segments_all = $segments;

        //detekce filtru
        if ($this->_hasParam('rep') || $this->_hasParam('bulletin') || $this->_hasParam('page') ||
                $this->_hasParam('user') || $this->_hasParam('segment')) {
            
            //entita bulletin
            if ($this->_hasParam('bulletin')) {
                $bul = Bulletins::get((int) $this->_getParam('bulletin'));
                if ($bul) {
                    $this->view->detail_title = $this->texts->availablepages->bulletin.': ' . $bul['name'];
                    $this->view->detail_title_url = $this->view->url(array('module' => 'admin', 'controller' => 'bulletinassembler', 'action' => 'edit', 'id' => $bul['id']),null,true);
                }
                $f_selpage = $db->select()->from(array('bp' => 'bulletins_pages'), array(new Zend_Db_Expr('DISTINCT ON(bp.page_id) bp.page_id')))
                        ->joinLeft(array('b' => 'bulletins'), 'bp.bulletin_id = b.id', null)
                        ->joinLeft(array('p' => 'pages'), 'bp.page_id = p.id', array('name' => 'p.name'))
                        ->where('b.deleted IS NULL')->where('p.deleted IS NULL')
                        ->where('b.id = ?', (int)$this->_getParam('bulletin'))
                        ->order('bp.page_id DESC');
                $this->view->pages = $db->fetchAll($f_selpage);
            }

            //entita page
            if ($this->_hasParam('page')) {
                $page = Pages::get((int)$this->_getParam('page'));
                if ($page) {
                    $this->view->detail_title = $this->texts->availablepages->page.': ' . $page['name'];
                    $this->view->detail_title_url = $this->view->url(array('module' => 'admin', 'controller' => 'pages', 'action' => 'edit', 'id' => $page['id']),null,true);
                }
                //bulletins
                $f_selbul = $db->select()->from(array('bp' => 'bulletins_pages'), array('b.name', 'b.id'))
                        ->joinLeft(array('b' => 'bulletins'), 'bp.bulletin_id = b.id', null)
                        ->joinLeft(array('p' => 'pages'), 'bp.page_id = p.id', null)
                        ->where('b.deleted IS NULL')->where('p.deleted IS NULL')
                        ->where('p.id = ?', (int)$this->_getParam('page'))
                        ->order('bp.page_id DESC');
                $this->view->bulletins = $db->fetchAll($f_selbul);

                $rp = new Repspages();

                //reps
                $f_reps = $rp->getPageReps((int)$this->_getParam('page'));
                $this->view->reps = $f_reps;

                //segments
                $this->view->segments = Pages::getPageSegments((int)$this->_getParam('page'));

                //users
                $rows = Pages::getPageUsers((int)$this->_getParam('page'));
                $f_users = array();
                foreach ($rows as $row) {
                    $f_users[$row['id']] = $row['surname'] . ' ' . $row['name'];
                }
                $this->view->users = $f_users;
            }

            //zobrazi seznam uzivatelu repa a pro nej dostupne stranky
            if ($this->_hasParam('rep')) {
                
                $repuser = Users::getUser((int)$this->_getParam('rep'));
                if ($repuser) {
                    $this->view->detail_title = $this->texts->availablepages->representative.': ['.$repuser['id'].'] '. $repuser['surname'] . ' ' . $repuser['name'];
                    $this->view->detail_title_url = $this->view->url(array('module' => 'admin', 'controller' => 'users', 'action' => 'edit', 'id' => $repuser['id']),null,true);
                }
                
                //rep users
                $f_users = Users::getRepUsers((int)$this->_getParam('rep'));
                $this->view->users = $f_users;
                
                //rep vidi stranky pres uzivatele, nema-li uzivatele zadne dostupne nema
                if ($f_users) {
                    //ziskame seznam segmentu uzivatelu repa
                    $usel = $db->select()->from('users', array('segment_id'))->where('segment_id IS NOT NULL')->where('id IN (?)', array_keys($f_users))->group('segment_id');
                    $srows = $db->fetchAll($usel);
                    $segs = array();
                    foreach ($srows as $s) {
                        $segs[] = $s['segment_id'];
                    }

                    //rep pages
                    $sel_reppages = Pages::getRepPages((int) $this->_getParam('rep'), true);

                    //users pages
                    $sel_repuserspages = Pages::getUserPages($segs, (int) $this->_getParam('rep'), true);

                    //stranky repa jaku prunik rep pages a users pages
                    $pages_sql = '(' . $sel_reppages->__toString() . ') INTERSECT (' . $sel_repuserspages->__toString() . ')';

                    $f_reppages = $db->fetchAll($pages_sql);
                    $this->view->pages = $f_reppages;
                }
            }

            //entita segment
            if ($this->_hasParam('segment')) {
                $segments = new Segments();
                $seg = $segments->getSegments((int)$this->_getParam('segment'));
                if ($seg[0]) {
                    $this->view->detail_title = $this->texts->availablepages->segment.': ' . $seg[0]['name'];
                    $this->view->detail_title_url = $this->view->url(array('module' => 'admin', 'controller' => 'users', 'action' => 'segmentsedit', 'id' => $seg[0]['id']),null,true);
                }
                
                //pages
                $f_segpages = Pages::getSegmentPages((int)$this->_getParam('segment'));
                $this->view->pages = $f_segpages;

                $f_selusers = $db->select()->from(array('u' => 'users'), array('u.id', 'u.surname', 'u.name'))->where('u.deleted IS NULL')->where('u.segment_id = ?', (int)$this->_getParam('segment'))->order('u.id DESC');
                $rows = $db->fetchAll($f_selusers);
                $f_users = array();
                foreach ($rows as $row) {
                    $f_users[$row['id']] = $row['surname'] . ' ' . $row['name'];
                }
                $this->view->users = $f_users;
            }

            //entita user
            if ($this->_hasParam('user')) {

                $user = Users::getUser((int)$this->_getParam('user'));
                if($user) {
                    $this->view->detail_title = $this->texts->availablepages->user.': ' . $user['surname'] . ' ' . $user['name'];
                    $this->view->detail_title_url = $this->view->url(array('module' => 'admin', 'controller' => 'users', 'action' => 'edit', 'id' => $user['id']),null,true);
                } 
                
                $f_usegs = array();
                //segment
                if ($user['segment_id']) {
                    $segments = new Segments();
                    $f_usegs = $segments->getSegments($user['segment_id']);
                    $this->view->segments = $f_usegs;
                }

                //rep
                $f_selreps = $db->select()->from(array('ur' => 'users_reps'), null)
                                ->joinLeft(array('u' => 'users'), 'ur.repre_id = u.id', array('name' => 'u.name', 'surname' => 'u.surname', 'id' => 'u.id'))
                                ->where('u.deleted IS NULL')->where('ur.user_id = ?', (int)$this->_getParam('user'));
                $f_ureps = $db->fetchAll($f_selreps);
                $this->view->reps = $f_ureps;

                //page
                $usegs_id = array();
                $ureps_id = array();

                foreach ($f_usegs as $s) {
                    $usegs_id[] = $s['id'];
                }

                foreach ($f_ureps as $r) {
                    $ureps_id[] = $r['id'];
                }

                $this->view->pages = Pages::getUserPages($usegs_id, $ureps_id);
            }
        } else {
            
            $this->view->segments = $segments;
            $this->view->reps = $reps;
            $this->view->pages = $pages;
            $this->view->bulletins = $bulletins;
            
        }
    }
}
