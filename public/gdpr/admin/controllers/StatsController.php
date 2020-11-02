<?php

/**
 *  Zobrazuje statistiky
 *
 *  @author Tomas Ovesny, R. Vajsar
 */
class Admin_StatsController extends Ibulletin_Admin_BaseControllerAbstract {

    /**
     * instance statistik
     * @var Statistics
     */
    protected $_statistics = null;

    /**
     * Ignorovat prava uzivatele adminu (zpristupnit vse)?
     * Pouzivame pri ziskavani seznamu modulu monitoringu v adminusersControlleru.
     * @var bool
     */
     var $ignorePrivileges = false;

    /**
     * v ktere se nachazime polozce submenu
     * @var string
     */
    public $mode = null;

    const VIDEOWATCHERS = 'videowatchers';

    /**
     *
     * @see Ibulletin_Admin_CRUDControllerAbstract::init()
     */
    public function init() {
        parent::init();

        $this->_statistics = Statistics::getInstance();

        // nastaveni menu
        $this->submenuAll =  array(
            'index' => array('title' => $this->texts->menu_dashboard, 'params' => array('action' => 'index')),
            'contacts' => array('title' => $this->texts->menu_contacts, 'params' => array('action' => 'contacts')),
            'readers' => array('title' => $this->texts->menu_readers, 'params' => array('action' => 'readers')),
            'returnrate' => array('title' => $this->texts->menu_return_rate, 'params' => array('action' => 'returnrate')),
            'campaign' => array('title' => $this->texts->menu_mail_campaign, 'params' => array('action' => 'campaign')),
            'entrymethods' => array('title' => $this->texts->menu_entrymethods, 'params' => array('action' => 'entrymethods')),
            'articles' => array('title' => $this->texts->menu_articles, 'params' => array('action' => 'articles')),
            'categories' => array('title' => $this->texts->menu_categories, 'params' => array('action' => 'categories')),
            'behavior' => array('title' => $this->texts->menu_behavior, 'params' => array('action' => 'behavior')),
            'indetail' => array('title' => $this->texts->menu_indetail, 'params' => array('action' => 'indetailpresentations')),
            'links' => array('title' => $this->texts->menu_links, 'params' => array('action' => 'links')),
            'questionnaires' => array('title' => $this->texts->menu_questionaires, 'params' => array('action' => 'questionnaires')),
        );
        if(isset($this->config->stats->stat_video) && $this->config->stats->stat_video) {
            $this->submenuAll['video']=array('title' => $this->texts->menu_video, 'params' => array('action' => 'video'));
        }

        // Remove all menuitems that are not allowed by privileges
        $redirect = false; // Redirect to another allowed action?
        if(!$this->ignorePrivileges){
            $actionName = $this->_request->getActionName();
            foreach($this->submenuAll as $key => $item){
                if(isset($item['params']) && isset($item['params']['action'])
                        && !Ibulletin_AdminAuth::hasPermission('monitoring_'.$item['params']['action']))
                {
                    unset($this->submenuAll[$key]);
                    if($actionName == $item['params']['action']){
                        $redirect = true;
                    }
                }
            }
        }


        // nastaveni submenu
        $this->submenuSpecific = array(
            'contacts' => array(
            //    'contactsindex' => array('title' => $this->texts->submenu_in_general, 'params' => array('action' => 'contacts')),
                /*'contactssegments' => array('title' => 'segmenty', 'params' => array('action' => 'contacts', 'mode'=>'segments')),
                 'contactschannels' => array('title' => 'kanály', 'params' => array('action' => 'contacts', 'mode'=>'channels')),
                'contactsreps' => array('title' => 'repové', 'params' => array('action' => 'contacts', 'mode'=>'reps')),
                'contactsregions' => array('title' => 'regiony', 'params' => array('action' => 'contacts', 'mode'=>'regions'))*/
            ),
            'readers' => array(
           //     'readersindex' => array('title' => $this->texts->submenu_in_general, 'params' => array('action' => 'readers')),
                /*'readerssegments' => array('title' => 'segmenty', 'params' => array('action' => 'readers', 'mode'=>'segment')),
                 'readerschannels' => array('title' => 'kanály', 'params' => array('action' => 'readers', 'mode'=>'channel')),
                'readersreps' => array('title' => 'repové', 'params' => array('action' => 'readers', 'mode'=>'rep')),
                'readersregions' => array('title' => 'regiony', 'params' => array('action' => 'readers', 'mode'=>'region'))
                */
            ),
            'returnrate' => array(
         //       'returnrateindex' => array('title' => $this->texts->submenu_in_general, 'params' => array('action' => 'returnrate')),
            /*'returnrateindex1' => array('title' => 'segmenty', 'params' => array('action' => 'returnrate', 'mode'=>'segment', 'parametrik'=>'segments')),
             'returnrateindex2' => array('title' => 'kanály', 'params' => array('action' => 'returnrate', 'mode'=>'channel', 'parametrik'=>'channels')),
            'returnratereps' => array('title' => 'repové', 'params' => array('action' => 'returnrate', 'mode'=>'rep', 'parametrik'=>'reps')),
            'returnrateregions' => array('title' => 'regiony', 'params' => array('action' => 'returnrate', 'mode'=>'region', 'parametrik'=>'regions'))
            */
            ),
            'campaign' => array(
                'campaignissue' => array('title' => $this->texts->submenu_campaign_issue, 'params' => array('action' => 'campaign')),
                'campaignother' => array('title' => $this->texts->submenu_campaign_other, 'params' => array('action' => 'campaign', 'mode'=>'other')),
         //       'campaignindex' => array('title' => $this->texts->submenu_in_general, 'params' => array('action' => 'campaign')),
                /*'campaignsegments' => array('title' => 'segmenty', 'params' => array('action' => 'campaign', 'mode'=>'segment')),
                 'campaignchannels' => array('title' => 'kanály', 'params' => array('action' => 'campaign', 'mode'=>'channel')),
                'campaignreps' => array('title' => 'repové', 'params' => array('action' => 'campaign', 'mode'=>'rep')),
                'campaignregions' => array('title' => 'regiony', 'params' => array('action' => 'campaign', 'mode'=>'region'))
                */
            ),
            'links' => array(
                'linksindex' => array('title' => $this->texts->submenu_links_external, 'params' => array('action' => 'links')),
                'linksarea' => array('title' => $this->texts->submenu_links_location, 'params' => array('action' => 'links', 'mode' => 'area')),
                'linksmail' => array('title' => $this->texts->submenu_links_emails, 'params' => array('action' => 'links', 'mode' => 'mail')),
                'resources' => array('title' => $this->texts->submenu_links_resources, 'params' => array('action' => 'resources'))
            ),
            'groupQuestShowData' => array(
                'questionnaires' => array('title' => $this->texts->submenu_back_to_the_list, 'params' => array('action' => 'questionnaires')),
                'questionnaires_showData' => array('title' => $this->texts->submenu_answers,'params' => array(), 'noreset' => true),
            ),
            'indetail' => array(
                'indetailpresentations' => array('title' => $this->texts->submenu_indetail_presentations, 'params' => array('action' => 'indetailpresentations')),
                'indetailvisits' => array('title' => $this->texts->submenu_indetail_visits, 'params' => array('action' => 'indetailvisits')),
                'indetailresources' => array('title' => $this->texts->submenu_indetail_resources, 'params' => array('action' => 'indetailresources')),
                'indetailconnections' => array('title' => $this->texts->submenu_indetail_connections, 'params' => array('action' => 'indetailconnections'))
            ),
            'entrymethodsGrp' => array( // Pouzivame submenu group, protoze submenu nepracuje jen s jednou action, ale s vice
                'entrymethods' => array('title' => $this->texts->submenu_entrymethods_entrymethods, 'params' => array('action' => 'entrymethods')),
                'entrymethodswaves' => array('title' => $this->texts->submenu_entrymethods_waves, 'params' => array('action' => 'entrymethodswaves')),
                'entrymethodschannels' => array('title' => $this->texts->submenu_entrymethods_channels, 'params' => array('action' => 'entrymethodschannels'))
            ),

        );

        $this->submenuGroups = array(
            'groupQuestShowData' => array('questionnaires', 'questionnaires_showData'),
            'entrymethodsGrp' => array('entrymethods', 'entrymethodswaves','entrymethodschannels','entrymethods'), // Toto poradi je spravne - rodic musi byt na prvnim miste a pokud chceme, aby byl v submenu i rodic, musime pridat rodice jeste jednou (viz dokumentace ModuleMenu->groups
            //'indetailGrp' => array('indetail', 'indetailpresentations', 'indetailresources', 'indetailvisits'),
        );


        //nacteme js export do xlsx
        Ibulletin_Js::addJsFile('jszip.js');
        Ibulletin_Js::addJsFile('xlsx.js');
        //js pro rozsirene moznosti tabulek v monitoringu export a volitelné sloupce
        Ibulletin_Js::addJsFile('admin/stattable.js');
        //text export xlsx do js
        Ibulletin_Js::addPlainCode('var TEXT_EXPORT_XLSX = "'.$this->texts->export_xlsx.'";');
        Ibulletin_Js::addPlainCode('var TEXT_TABLE_COLOPT = "'.$this->texts->table_colopt.'";');
        Ibulletin_Js::addPlainCode('var PHP_EXPORT_XLSX_LINK = "'.$this->view->url(array('action'=>'xexport')).'";');
        $loop_url = $this->view->url(array('controller'=>'loopback', 'action'=>'get'));
        //loopback url pro xlsx export
        Ibulletin_Js::addPlainCode('var LOOPBACK_URL = "'.$loop_url.'";');
        //tabulky - volitelne sloupce
        Ibulletin_Js::addJsFile('admin/tcol_optional.js');

        // Redirect to another action that is allowed by privilegues
        if($redirect){
            reset($this->submenuAll);
            $target = current($this->submenuAll);
            $this->redirect($target['params']['action']);
        }
    }
    /**
     * Nastaveni spolecnych veci
     */
    public function preDispatch() {
        parent::preDispatch();

        // nazev aktualni akce
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        $location = $request->getActionName();
        $this->view->location = $location;

        // zjisteni, v kterem jsme submenu
        $_mode = $request->getParam('mode');
        $mode = ( !empty($_mode) ) ? $_mode  : '';
        $this->mode = $mode;
        $this->view->mode = $mode;

    }

    /**
     * nastavi jiny skript zobrazeni pro kazde submenu, aby nebyly vsechny podmenu
     * v jednom skriptu (postDispatch kvuli tomu, aby se prvni zavolala akce)
     * Napr: contacts           => contacts.phtml
     *       contacts/segmenty => contacts_segments.phtml
     *       contacts/regiony => contacts_regions.phtml
     */
    public function postDispatch() {
        parent::postDispatch();

        // nacti request object a ziskej jmeno akce
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        $action = $request->getActionName();

        // nastaveni scriptu pro zobrazeni
        $mode = ( !empty($this->mode) ) ? '_' . $this->mode  : '';
        $viewScript = '/stats/'.$action . $mode . '.phtml';
        $this->getHelper('viewRenderer')->renderScript($viewScript);
    }

    /**
     *  Rychly prehled
     *
     */
    public function indexAction()
    {
        //nacte bulletiny
        $bulletin_rows = $this->_statistics->getBulletins_v(); // platne, serazene vzestupne podle valid_from

        Ibulletin_Js::addJsFile('admin/jquery.knob.js');
        Ibulletin_Js::addJsFile('admin/excanvas.js');

        // aktualni a predchazejici bulletin
        $last = reset($bulletin_rows);

        $actual_bulletin_id = (isset($last) ? $last['id'] : null);

        // pocet target
        $this->view->target = $this->_statistics->getTargetCount();

        // pocet platných kontaktu
        $activeContactsForEmailingCount = $this->_statistics-> getActiveContactsForEmailingCount();
        $this->view->activeContactsForEmailingCount = $activeContactsForEmailingCount;

        // procento platných kontaktu vuci target
        $this->view->activeContactsForEmailingPercentage = 100*bcdiv($activeContactsForEmailingCount ,$this->_statistics->getTargetCount());

        // pocet ctenatu
        $readersCount = count($this->_statistics->getReadersForBulletin(array("r.min_timestamp <= r.valid_to", "email IS NOT NULL")));
        $this->view->readersCount = $readersCount;

        // procento ctenaru vuci kontaktu
        $this->view->readersPercentage = ($activeContactsForEmailingCount>0 ? round(100*($readersCount/$activeContactsForEmailingCount)) : 0);

        // počet kontaktů se čtenáři = součet čtenářů v jednotlivých vydáních bez ohledu na platnost vydání
        $this->view->readersContactsCount = $this->_statistics->getContactsCountForAllBulletins();

        // průměrna délka návštěvy všech čtenářů za všechna vydání bez ohledu na platnost vydání
        $this->view->readersTimeAverage = $this->_statistics->getAverageUserLengthForBulletins();
        // sformatuje cas na `minuta:sekunda`
        $this->view->readersTimeAverage = preg_replace('/^[0-9]{2}:(.*)\.[0-9]*$/', '$1',  $this->view->readersTimeAverage);


        // response rate
        $this->view->responseRate = $this->_statistics->getResponseRateTable('', $actual_bulletin_id);

        // return rate
        $this->view->returnRate = $this->_statistics->getReturnRateTable("", $actual_bulletin_id);

		// pocet aktivnich kontaktu
		$activeContactsCount = $this->_statistics->getActiveContactsCount();
		$this->view->activeContactsCount = $activeContactsCount;

		// pocet aktivnich kontaktu se souhlasem GDPR
		$activeGDPRContactsCount = $this->_statistics->getActiveGDPRContactsCount();
		$this->view->activeContactsGDPRCount = $activeGDPRContactsCount;

		// procento aktivnich kontaktu se souhlasem GDPR
		$this->view->activeContactsGDPRPercentage = ($activeGDPRContactsCount>0 ? round(100*($activeGDPRContactsCount/$activeContactsCount)) : 0);

		// pocet aktivnich kontaktu s globalnim souhlasem GDPR
		$activeGDPRGlobalContactsCount = $this->_statistics->getActiveGDPRGlobalContactsCount();
		$this->view->activeContactsGDPRGlobalCount = $activeGDPRGlobalContactsCount;

		// procento aktivnich kontaktu s globalnim souhlasem GDPR
		$this->view->activeContactsGDPRGlobalPercentage = ($activeGDPRGlobalContactsCount>0 ? round(100*($activeGDPRGlobalContactsCount/$activeContactsCount)) : 0);


	}

    /**
     * Vypis statistik pro clanky
     *
     */
    public function articlesAction()
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $request = $this->getRequest();

        // Nacteni JS knihoven potrebnych pro graf
        $this->loadJqplot();

        $formData  = $this->_request->getParams();

       // nacist bulletiny
       $bulletins_v = $this->_statistics->getBulletins_v();
       reset($bulletins_v);

       if(isset($formData['bulletin_id']))  {
           $bul_id = $formData['bulletin_id'];
       } elseif(!empty($bulletins_v)){
           $bullRow = current($bulletins_v);
           $bul_id = $bullRow['id'];
           $formData['bulletin_id'] = $bul_id;
       } else {  // Vybereme ALL, protoze neni zadne platne vydani
           $bul_id = 0;
       }

        if($bul_id==0) {
            $usersCount = count($this->_statistics->getReadersForBulletin(array('email is not null')));
        } else {
             $usersCount = count($this->_statistics->getReadersForBulletin(array('bulletin_id = '.$bul_id, 'email is not null')));
        }

       // Pridame moznost zobrazeni napric vsemi vydanimi
       array_unshift($bulletins_v, array('poradi'=>0, 'id'=>0, 'name'=>Ibulletin_Texts::get('all_issues')));

       // vrat tabulku clanku
       $articles = $this->_statistics->getArticlesReadTable($bul_id);

       // posli do view clanky, pocet uzivatelu, zobraz graf
       $this->view->formData = $formData;
       $this->view->articles = $articles;
       $this->view->bulletins_v = $bulletins_v;
       $this->view->usersCount = $usersCount;
       $this->view->hasLikes = $config->general->show_like_butt == 'local';
  //      $this->view->form = $form;

    }


    /**
     * Vypis statistik vstupnich stranek.
     */
    public function entrymethodsAction() {

        $config = Zend_Registry::get('config');

        //pocet zobrazenych bulletinu, 0 = vsechny
        if ($this->_hasParam('showall')) {
            $bul_limit = 0;
        } else {
            $bul_limit = (int) $config->stats->number_display_issues;
        }
        $this->view->visitsData = $this->_statistics->getEntryMethodsVisits($bul_limit);

        $total_count_bul = Statistics::getBulletinsCount();

        if ($bul_limit >= $total_count_bul || $bul_limit == 0) {
            $this->view->bul_more = false;
        } else {
            $this->view->bul_more = true;
        }
    }


     /**
     * Vypis statistik vstupnich kanalu.
     */
    public function entrymethodschannelsAction() {

        $texts = Ibulletin_Texts::getSet();

        $buls = $this->_statistics->getBulletins_v();

        // seznam bulletinu
        $bulletin_list = array();
        foreach ($buls as $row) {
            $bulletin_list[$row['id']] = $row['name'];
        }

        // vytvoreni formulare pro vyber bulletinu
        $form = new Form(array('id' => 'bulletin_list'));
        $form->setFormInline(true);
        $form->setMethod('get');
        // select box
        $select = new Zend_Form_Element_Select('bulletin_id');
        $select->setMultiOptions($bulletin_list)
            ->setLabel($texts->bulletin)
            ->setRequired(FALSE);

        // submit
        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setValue('submit')
            ->setLabel($texts->submit)
            ->setAttrib('class','btn-primary');

        if ($this->_hasParam('bulletin_id')) {
            $bulId = $this->_getParam('bulletin_id');
        } else {
            $rowBulletin = Bulletins::getActualBulletinRow();
            $bulId = $rowBulletin['id'];
        }

        $select->setValue($bulId);
        $form->addElements(array($select, $submit));
        $this->view->form = $form;

        $data = $this->_statistics->getEntryMethodsChannels($bulId);

        $this->view->entryMethods = Templates::getAllowedEntryMetohods();
        $this->view->invitationWaveTypes = Invitationwaves::getWaveTypes();
        $this->view->entries = $data;

    }

    /**
     * Podrobne statistiky pristupu pres vlny
     */
    public function entrymethodswavesAction() {
        //$this->moduleMenu->setCurrentLocation('entrymethodswaves');
        $config = Zend_Registry::get('config');

        // Pocet zobrazenych bulletinu, 0 = vsechny
        $bul_limit = $this->_hasParam('showall') ? 0 : (int)$config->stats->number_display_issues;

        // Ziskame data
        $this->view->wavesData = $this->_statistics->getEntryMethodsWavesVisits($bul_limit * 2);

        // Pouzijeme fake pro urceni kdy ma byt zobrazeno tlacitko show all
        // Ukazujeme jej vzdy, kdyz pocet radku vystupu == $bul_limit * 2
        if (($bul_limit * 2) == count($this->view->wavesData) && $bul_limit != 0) {
            $this->view->bul_more = true;
        } else {
            $this->view->bul_more = false;
        }
    }

    /**
     * Vypis statistik pro externi odkazy, 11.3.2010 RV
     *
     */
    public function linksAction()
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $req = $this->getRequest();
        $texts = Ibulletin_Texts::getSet();
        $buls = $this->_statistics->getBulletins_v();
        // seznam bulletinu
        $bulletin_list = array('0' => Ibulletin_Texts::get('all_issues'));
        foreach ($buls as $row) {
            $bulletin_list[$row['id']] = $row['name'];
        }

        // vytvoreni formulare pro vyber bulletinu
        $form = new Form(array('id' => 'bulletin_list'));
        $form->setFormInline(true);
        $form->setMethod('get');
        // select box
        $select = new Zend_Form_Element_Select('bulletin_id');
        $select->setMultiOptions($bulletin_list)
            ->setLabel($texts->bulletin)
            ->setRequired(FALSE);

        // submit
        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setValue('submit')
            ->setLabel($texts->submit)
            ->setAttrib('class','btn-primary');

        $form->addElements(array($select, $submit));

        // spracovani formulare, ziskani bulletin_id po odeslani
        if ($req->getParam('submit')) {
            if ($form->isValid($req->getParams())) {
                $formData = $form->getValues();
                $bul_id = $formData['bulletin_id'];
            }
        } else {
            if (isset($buls[0]['id'])) {
                $bul_id = $buls[0]['id'];
                $form->getElement('bulletin_id')->setValue($bul_id);
            }
        }

        // vyber podle submenu
        switch ($this->mode) {
            case NULL: // views/scripts/stats/links.phtml
                $this->moduleMenu->setCurrentLocation('linksindex');
                $links = $this->_statistics->getLinksVisits($bul_id);
                break;
            case 'area': // views/scripts/stats/links_area.phtml
                $this->moduleMenu->setCurrentLocation('linksarea');
                $areas = (isset($config->stats->area) ? $config->stats->area->toArray() : array());
                $links = $this->_statistics->getLinksAreaVisits($areas, $bul_id);
                break;
            case 'mail': // views/scripts/stats/links_mail.phtml
                $this->moduleMenu->setCurrentLocation('linksmail');
                $links = $this->_statistics->getLinksMailVisits($bul_id);
                break;
        }
        $this->view->bulletinId = $bul_id;
        $this->view->links = $links;
        $this->view->form = $form;

    }

    /**
     * Vypis statistik resources
     */
    public function resourcesAction()
    {

        $this->moduleMenu->setCurrentLocation('links');

        $resources = $this->_statistics->getResourcesVisits();
        $this->view->resources = $resources;

    }

    /**
     * Vypis statistik pro rubriky
     *
     */
    public function categoriesAction()
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
//        $request = $this->getRequest();

        // Nacteni JS knihoven potrebnych pro graf
        $this->loadJqplot();

        $formData  = $this->_request->getParams();

       // nacist bulletiny
       $bulletins_v = $this->_statistics->getBulletins_v();
       reset($bulletins_v);

       if(isset($formData['bulletin_id']))  {
           $bul_id = $formData['bulletin_id'];
       } elseif(!empty($bulletins_v)){
           $bullRow = current($bulletins_v);
           $bul_id = $bullRow['id'];
           $formData['bulletin_id'] = $bul_id;
       } else {  // Vybereme ALL, protoze neni zadne platne vydani
           $bul_id = 0;
       }

        if($bul_id==0) {
            $usersCount = count($this->_statistics->getReadersForBulletin(array('email is not null')));
        } else {
             $usersCount = count($this->_statistics->getReadersForBulletin(array('bulletin_id = '.$bul_id, 'email is not null')));
        }



       // Pridame moznost zobrazeni napric vsemi vydanimi
       array_unshift($bulletins_v, array('poradi'=>0, 'id'=>0, 'name'=>Ibulletin_Texts::get('all_issues')));

       // vrat tabulku rubrik
       $categories = $this->_statistics->getCategoriesReadTable($bul_id);

       // posli do view vybrany bulletin, rubriky, pocet uzivatelu, zobraz graf
       $this->view->formData = $formData;
       $this->view->articles = $categories;
       $this->view->bulletins_v = $bulletins_v;
       $this->view->usersCount = $usersCount;
    }



    /**
     * Vypis statistik pro kontakty
     *
     */
    public function contactsAction()
    {
        $db = Zend_Registry::get('db');

        // nacti pocet segmentu, kvuli vypisu prehledove tabulky
        $segments = new Segments();
        $segmentsCount = $segments->getSegmentsCount();

        // nacti pocet kanalu, kvuli vypisu prehledove tabulky
        $channels = new Channels();
        $channelsCount = $channels->getChannelsCount();

        // nacti pocet regionu, kvuli vypisu prehledove tabulky
        $regions = new Regions();
        $regionsCount = $regions->getRegionsCount();

        // nacti pocet reprezentantu, kvuli vypisu prehledove tabulky
        $reps = new Reps();
        $repsCount = $reps->getRepsCount();


        switch ($this->mode) {

            /**
             * contacts action - segments (mode=>'segments')
             * kontakty rozdelene dle segmentu
             */
            case 'segments' :

               // vrat pocet kontaktu
               $usersCount = $this->_statistics->getContactsCount();

               // vrat kontakty pro kazdy segment a jejich procentualni vyjadreni
               $contactsPerSegment = $this->_statistics->getContactsPerSegment($usersCount);

               // posli do view nactene segmenty, pocet uzivatelu, zobraz tabulku
               $this->view->segments = $contactsPerSegment;
               $this->view->usersCount = $usersCount;

               // kolac -segmenty
               $this->view->segmentChart = $this->_statistics->getContactsPerSegmentChart();

               // vyvoj v case -graf
               $this->view->timeChart = $this->_statistics->getContactsPerSegmentInTimeChart();

               // tabulka - celkovy prehled
               $this->view->segmentsCount = $segmentsCount;
               $this->view->bulletinsCount = $this->_statistics->getBulletinsCount();
               $this->view->overview = $this->_statistics->getContactsPerSegmentInBulletins();

            break;

            /**
             * contacts action - channels (mode=>'channels')
             * kontakty rozdelene dle kanalu
             */
            case 'channels' :

               // vrat pocet kontaktu
               $usersCount = $this->_statistics->getContactsCount();

               // vrat kontakty pro kazdy kanal a jejich procentualni vyjadreni
               $contactsPerChannel = $this->_statistics->getContactsPerChannel($usersCount);

               // posli do view nactene kanaly, pocet uzivatelu, zobraz tabulku
               $this->view->channels = $contactsPerChannel;
               $this->view->usersCount = $usersCount;

               // kolac - kanaly
               $this->view->channelChart = $this->_statistics->getContactsPerChannelChart();

               // vyvoj v case -graf
               $this->view->timeChart = $this->_statistics->getContactsPerChannelInTimeChart();


               // tabulka - celkovy prehled
               $this->view->channelsCount = $channelsCount;
               $this->view->bulletinsCount = $this->_statistics->getBulletinsCount();
               $this->view->overview = $this->_statistics->getContactsPerChannelInBulletins();

            break;

            /**
             * contacts action - regions (mode=>'regions')
             * kontakty rozdelene dle regionu
             */
            case 'regions' :

               // vrat pocet kontaktu
               $usersCount = $this->_statistics->getContactsCount();

               // vrat kontakty pro kazdy region a jejich procentualni vyjadreni
               $contactsPerRegion = $this->_statistics->getContactsPerRegion($usersCount);

               // posli do view nactene regiony, pocet uzivatelu, zobraz tabulku
               $this->view->regions = $contactsPerRegion;
               $this->view->usersCount = $usersCount;

               // kolac - regiony
               $this->view->regionChart = $this->_statistics->getContactsPerRegionChart();

               // vyvoj v case - graf
               $this->view->timeChart = $this->_statistics->getContactsPerRegionInTimeChart();


               // tabulka - celkovy prehled
               $this->view->regionsCount = $regionsCount;
               $this->view->bulletinsCount = $this->_statistics->getBulletinsCount();
               $this->view->overview = $this->_statistics->getContactsPerRegionInBulletins();

            break;

            /**
             * contacts action - reps (mode=>'reps')
             * kontakty rozdelene dle reprezentatnu
             */
            case 'reps' :

               // vrat pocet kontaktu
               $usersCount = $this->_statistics->getContactsCount();

               // vrat kontakty dle reprezentatnu a jejich procentualni vyjadreni
               $contactsPerRep = $this->_statistics->getContactsPerRep($usersCount);

               // posli do view nactene repy, pocet uzivatelu, zobraz tabulku
               $this->view->reps = $contactsPerRep;
               $this->view->usersCount = $usersCount;

               // kolac - reps
               $this->view->repsChart = $this->_statistics->getContactsPerRepsChart();


               // vyvoj v case - graf
               $this->view->timeChart = $this->_statistics->getContactsPerRepInTimeChart();


               // tabulka - celkovy prehled
               $this->view->repsCount = $repsCount;
               $this->view->bulletinsCount = $this->_statistics->getBulletinsCount();
               $this->view->overview = $this->_statistics->getContactsPerRepInBulletins();

            break;


            /**
             * index action (mode=>'')
             */
            default:

                // Nacteni JS knihoven potrebnych pro graf
                $this->loadJqplot();


                // pocet target
                $this->view->target = $this->_statistics->getTargetCount();

                // pocet aktivnich kontaktu
                $this->view->activeContacts = $this->_statistics->getActiveContactsCount();

                // pocet neaktivnich kontaktu
                $this->view->noActiveContacts = $this->_statistics->getNoActiveContactsCount();

                // pocet aktivnich pro emailing kontaktu
                $this->view->activeContactsForEmailing = $this->_statistics->getActiveContactsForEmailingCount();

				// pocet aktivnich kontaktu se souhlasem GDPR
				$this->view->activeContactsGDPR = $this->_statistics->getActiveGDPRContactsCount();

				// pocet aktivnich kontaktu s globalnim souhlasem GDPR
				$this->view->activeContactsGDPRGlobal = $this->_statistics->getActiveGDPRGlobalContactsCount();

                // celkovy pocet kontaktu
                $this->view->contactsCount = $this->_statistics->getContacts();

                 // odstranene kontakty
                $this->view->removedContactsCount = $this->_statistics->getRemovedContactsCount();

                // odhlasene kontakty
                $this->view->deregistredContactsCount = $this->_statistics->getDeregistredContactsCount();

                // nefunkcni kontakty
                $this->view->invalidContactsCount = $this->_statistics->getInvalidContactsCount();

                //aktivni kontakty bez emailu
                $this->view->noEmailContactsCount =  $this->_statistics->getNoEmailContactsCount();

                $bulletins = $this->_statistics->getBulletins_v();

                $gcontacts = array();
                //data pro graf kontaktu dle bulletinu
                foreach ($bulletins as $b) {
                    $gcontacts[$b['name']] = $this->_statistics->getContactsCountForBulletin($b['id']);
                }

               $this->view->graph_contact_active_data = json_encode(array('data'=> array_reverse($gcontacts)));

               $gcontacts_emailing = array();
                //data pro graf kontaktu pro emailing dle bulletinu
                foreach ($bulletins as $b) {
                    $gcontacts_emailing[$b['name']] = $this->_statistics->getContactsCountForEmailingForBulletin($b['id']);
                }

               $this->view->graph_contact_emailing_active_data = json_encode(array('data'=> array_reverse($gcontacts_emailing)));

             break;
        }

    }


    /**
     * Vypis statistik pro ctenare
     *
     */
    public function readersAction()
    {
        $db = Zend_Registry::get('db');

        //inicializace knihovny Zend_Layout - ma byt sice v bootstrapu, ale zde funguje tez
        Zend_Layout::startMvc();
        $this->_helper->layout->setLayout('stats/layout_readers');

       // vrat pocet kontaktu celkem
       $usersCount = count($this->_statistics->getReadersForBulletin());
       $this->view->usersCount = $usersCount;

       $this->view->area = $this->mode;

       if(!empty($this->mode)){
                // Nazev atributu v users
                $attrName = $this->mode.'_id';
                if($this->mode == 'rep'){
                    $attrName = $this->mode;
                }

                // nacti pocet oblasti, kvuli vypisu prehledove tabulky
                $areas = new Areas();
                $areasCount = $areas->getAreasCount($this->mode.'s');

               // vrat kontakty pro kazdou oblast a jejich procentualni vyjadreni
               $contactsPerArea = $this->_statistics->getReadersPerArea($usersCount, array('table'=>$this->mode.'s','primary_key'=>$attrName));

               // posli do view nactene oblasti, zobraz tabulku
               $this->view->areas = $contactsPerArea;

               // kolac - oblasti
               $this->view->areaChart = $this->_statistics->getReadersPerAreaChart($contactsPerArea);

               // tabulka - celkovy prehled
               $this->view->areasCount = $areasCount;
               $this->view->bulletinsCount = $this->_statistics->getBulletinsCount();
               $contactsPerBulletinsAreas = $this->_statistics->getReadersPerAreaInBulletins(array('table'=>$this->mode.'s','primary_key'=>$attrName,'name'=>$this->mode));
               $this->view->overview = $contactsPerBulletinsAreas;

               // vyvoj v case -graf
               $this->view->timeChart = $this->_statistics->getReadersPerAreaInTimeChart('',$contactsPerBulletinsAreas, $areasCount, '');
              return;
       }

        switch ($this->mode) {


            /**
             * index action (mode=>'')
             */
            default:
                // vypneme sablonu
               $this->_helper->layout->disableLayout();

                // Nacteni JS knihoven potrebnych pro graf
                $this->loadJqplot();

                $config = Zend_Registry::get('config');

               //pocet zobrazenych bulletinu, 0 = vsechny
               if ($this->_hasParam('showall')) {
                   $bul_limit = 0;
               } else {
                   $bul_limit = (int)$config->stats->number_display_issues;
               }

               // kontaktu
               $contactsCount = $this->_statistics->getActiveContactsCount();
               $this->view->contactsCount = $contactsCount;

               // pocet ctenaru
               $readersCount = count($this->_statistics->getReadersForBulletin(array("r.min_timestamp <= r.valid_to and email IS NOT NULL and trim(both from email)!=''")));
               $this->view->readersCount = $readersCount;
               $noemailReadersCount = count($this->_statistics->getReadersForBulletin(array("r.min_timestamp <= r.valid_to", "email is NULL or trim(both from email)=''")));
               $this->view->noemailReadersCount = $noemailReadersCount;
			   // $this->view->readersExport = self::getExportButton(array('action' => self::READERS, 'bulletin' => $this->view->bulletin[bulletin]));

               // procento aktivnich
               $this->view->activePercents = $contactsCount ? round(100*($readersCount/$contactsCount)) : 0;

               // tabulka ctenaru v case
               $bulletin_rows = $this->_statistics->getBulletins_v($bul_limit); // platne, serazene sestupne podle valid_from
               $inTimeOverview = array();

               //pole s prumernym casem session
               $reader_ses_avg = $this->_statistics->getAverageSessionLengthForBulletins(null,true);
               //pole s prumernym casem uzivatele
               $reader_user_avg = $this->_statistics->getAverageUserLengthForBulletins(null,true);
               //pole čtenáři v době platnosti vydání
               $reader_valid_email = $this->_statistics->getReadersForBulletin(array("r.min_timestamp <= r.valid_to", "r.email is not null"),'group');
               //pole čtenáři bez emailu
               $reader_valid_noemail = $this->_statistics->getReadersForBulletin(array("r.min_timestamp <= r.valid_to", "r.email is null"),'group');
               //pole čtenáři po platnosti
               $reader_novalid_email = $this->_statistics->getReadersForBulletin(array("r.min_timestamp > r.valid_to", "r.email is not null"),'group');
               //pole čtenáři bez emailu po platnosti
               $reader_novalid_noemail = $this->_statistics->getReadersForBulletin(array("r.min_timestamp > r.valid_to", "r.email is null"),'group');

               foreach ($bulletin_rows as $bulletin) {
                    if (!empty($bulletin['name'])) {
                        $inTimeOverview[$bulletin['poradi']]['id']= $bulletin['id'];
                        $inTimeOverview[$bulletin['poradi']]['bulletin']= $bulletin['name'];
                        if (isset($reader_valid_email[$bulletin['id']])) {
                           $inTimeOverview[$bulletin['poradi']]['readers'] = $reader_valid_email[$bulletin['id']]['pocet'];
                        } else {
                            $inTimeOverview[$bulletin['poradi']]['readers'] = 0;
                        }
                        if (isset($reader_valid_noemail[$bulletin['id']])) {
                           $inTimeOverview[$bulletin['poradi']]['readers_anon'] = $reader_valid_noemail[$bulletin['id']]['pocet'];
                        } else {
                            $inTimeOverview[$bulletin['poradi']]['readers_anon'] = 0;
                        }
                        if (isset($reader_novalid_email[$bulletin['id']])) {
                           $inTimeOverview[$bulletin['poradi']]['readers_late'] = $reader_novalid_email[$bulletin['id']]['pocet'];
                        } else {
                            $inTimeOverview[$bulletin['poradi']]['readers_late'] = 0;
                        }
                        if (isset($reader_novalid_noemail[$bulletin['id']])) {
                           if (isset($reader_valid_noemail[$bulletin['id']]['pocet'])) {
                               $inTimeOverview[$bulletin['poradi']]['readers_late_anon'] = $reader_valid_noemail[$bulletin['id']]['pocet'];
                           } else {
                               $inTimeOverview[$bulletin['poradi']]['readers_late_anon'] = 0;
                           }
                        } else {
                            $inTimeOverview[$bulletin['poradi']]['readers_late_anon'] = 0;
                        }
                        if (isset($reader_ses_avg[$bulletin['id']])) {
                            $inTimeOverview[$bulletin['poradi']]['readers_avg']= $reader_ses_avg[$bulletin['id']]['avg'];
                        } else {
                            $inTimeOverview[$bulletin['poradi']]['readers_avg'] = 0;
                        }
                        if (isset($reader_user_avg[$bulletin['id']])) {
                            $inTimeOverview[$bulletin['poradi']]['readers_avg2']= $reader_user_avg[$bulletin['id']]['avg'];
                        } else {
                            $inTimeOverview[$bulletin['poradi']]['readers_avg2'] = 0;
                        }
                        $inTimeOverview[$bulletin['poradi']]['contacts_count'] = Statistics::getContactsCountForBulletin($bulletin['id']);
                        $inTimeOverview[$bulletin['poradi']]['contacts_count_foremailing'] = Statistics::getContactsCountForEmailingForBulletin($bulletin['id']);
                    }
               }
               $this->view->inTimeOverview = $inTimeOverview;

               $total_count_bul = Statistics::getBulletinsCount();

               if ($bul_limit >= $total_count_bul || $bul_limit == 0) {
                    $this->view->bul_more = false;
               } else {
                   $this->view->bul_more = true;
               }


               // ucast ctenaru na vydanich
             //$this->view->readersPercentageChart = $this->_statistics->getReadersPercentagePerBulletinsChart($bul_limit);
             break;
        }

    }



    /** 10.9.09 RV
    * @desc
    *
    */

    public function returnrateAction()
    {
        $db = Zend_Registry::get('db');
        //inicializace knihovny Zend_Layout - ma byt sice v bootstrapu, ale zde funguje tez
        Zend_Layout::startMvc();
        $this->_helper->layout->setLayout('layout_returnrate');

        $request = $this->getRequest();
//            echo '*****';
//            print_r($request);
//            echo '******';
//        echo $request->getParam('action');

       if(!empty($this->mode)){
                // Nazev atributu v users
                $attrName = $this->mode.'_id';
                if($this->mode == 'rep'){
                    $attrName = $this->mode;
                }

                // nacti pocet oblasti, kvuli vypisu prehledove tabulky
                $areas = new Areas();
                $areasCount = $areas->getAreasCount($this->mode.'s');

               // vrat pocet kontaktu
               $usersCount = count($this->_statistics->getReadersForBulletin());

               // vrat kontakty pro kazdou oblast a jejich procentualni vyjadreni
               //$contactsPerArea = $this->_statistics->getReadersPerArea($usersCount, array('table'=>$this->mode.'s','primary_key'=>$attrName));

               // posli do view nactene oblasti, zobraz tabulku
               $this->view->areas = $contactsPerArea;
               $this->view->usersCount = $usersCount;

               // tabulka - celkovy prehled
               $this->view->areasCount = $areasCount;
               $this->view->bulletinsCount = $this->_statistics->getBulletinsCount();
               $contactsPerBulletinsAreas = $this->_statistics->getReadersPerAreaInBulletins(array('table'=>$this->mode.'s','primary_key'=>$attrName,'name'=>$this->mode));
               $this->view->overview = $this->_statistics->getReturnRateTable($this->mode);

              return;

       }
       else{

             // Nacteni JS knihoven potrebnych pro graf
             $this->loadJqplot();

            /**
             * index action (mode=>'')
             */
               $this->_helper->layout->disableLayout();

               // kontaktu
               $contactsCount = $this->_statistics->getContactsCount();
               $this->view->contactsCount = $contactsCount;

               // vrat tabulku s RR dle bulletinu
               $this->view->rrPerBulletin = $this->_statistics->getReturnRateTable(0,0);


               // pocet ctenaru
               $readersCount = count($this->_statistics->getReadersForBulletin());
               $this->view->readersCount = $readersCount;

               // procento aktivnich
               $this->view->activePercents = (bcdiv($readersCount, $contactsCount) * 100);

        }

    }






     /** 10.9.09 RV
    * @desc
    *
    */

    public function campaignAction()
    {
        $db = Zend_Registry::get('db');

        Zend_Layout::startMvc();
        $this->_helper->layout->setLayout('layout_campaign');

        $request = $this->getRequest();
//        echo $request->getParam('action');

       if(!empty($this->mode)){
               if ($this->mode = 'other') {
                   $this->_helper->layout->disableLayout();
                    $this->redirect('campaign_mails');

               } else {

                   // nacti pocet oblasti, kvuli vypisu prehledove tabulky
                   $areas = new Areas();
                   $areasCount = $areas->getAreasCount($this->mode.'s');

                   // tabulka - celkovy prehled
                   $this->view->areasCount = $areasCount;
                   $this->view->bulletinsCount = $this->_statistics->getBulletinsCount();
                   $this->view->overview = $this->_statistics->getResponseRateTable($this->mode);
               }

       }
       else{

               $this->moduleMenu->setCurrentLocation('campaignissue');
               $this->_helper->layout->disableLayout();

               $config = Zend_Registry::get('config');

               //pocet zobrazenych bulletinu, 0 = vsechny
               if ($this->_hasParam('showall')) {
                   $bul_limit = 0;
               } else {
                   $bul_limit = (int)$config->stats->number_display_issues;
               }


                // Nacteni JS knihoven potrebnych pro graf
               $this->loadJqplot();

               // vrat tabulku s response dle bulletinu
               $this->view->responsePerBulletin = $this->_statistics->getResponseRateTable(0,0,null,$bul_limit);

               $total_count_bul = Statistics::getBulletinsCount();

               if ($bul_limit >= $total_count_bul || $bul_limit == 0) {
                    $this->view->bul_more = false;
               } else {
                   $this->view->bul_more = true;
               }

       }
    }





     /** RV
    * @desc
    *
    */

    public function campaignmailsAction()
    {
        $db = Zend_Registry::get('db');
        //inicializace knihovny Zend_Layout - ma byt sice v bootstrapu, ale zde funguje tez

        $request = $this->getRequest();
        //echo $request->getParam('action');
        $bulletinId = $request->getParam('bulletinId', NULL);

        if ($bulletinId) {
            $this->moduleMenu->setCurrentLocation('campaignissue');
            $this->view->bulletinName = Bulletins::getBulletinName($bulletinId);
        } else {
            $this->moduleMenu->setCurrentLocation('campaignother');
            $this->view->bulletinName = $this->texts->other;
        }

        // vrat tabulku s RR dle mailu za bulletinu
        $this->view->rrPerBulletin = $this->_statistics->getResponseRateTable('bulletin', $bulletinId,0,0);

    }


     /** RV
    * @desc
    *
    */

    public function campaignlinksAction()
    {
        $db = Zend_Registry::get('db');
        //inicializace knihovny Zend_Layout - ma byt sice v bootstrapu, ale zde funguje tez

        $request = $this->getRequest();
        //echo $request->getParam('action');
        $bulletinId = $request->getParam('bulletinId', NULL);
        $emailId = $request->getParam('emailId');

        if ($bulletinId) {
            $bulletin = $this->_statistics->getResponseRateTable('', $bulletinId);
            $this->view->bulletinName = $bulletin['name'];
        } else {
            $this->view->bulletinName = $this->texts->other;
        }
        $res = $db->fetchRow('select name from emails where id ='.$emailId);
        $this->view->emailName = $res['name'];

        // vrat tabulku s RR dle mailu za bulletinu
        $this->view->rrPerBulletin = $this->_statistics->getResponseRateTable('email', $emailId);

    }

    public function campaignlinksdetailAction()
    {
        $db = Zend_Registry::get('db');
        //inicializace knihovny Zend_Layout - ma byt sice v bootstrapu, ale zde funguje tez

        $request = $this->getRequest();
        //echo $request->getParam('action');
        $bulletinId = $request->getParam('bulletinId', NULL);
        $emailId = $request->getParam('emailId');
        $linkId = $request->getParam('linkId');

        if ($bulletinId) {
            $bulletin = $this->_statistics->getResponseRateTable('', $bulletinId);
            $this->view->bulletinName = $bulletin['name'];
        } else {
            $this->view->bulletinName = $this->texts->other;
        }

        $res = $db->fetchRow('select name from emails where id ='.$emailId);
        $this->view->emailName = $res['name'];
        $res = $db->fetchRow('select name from links where id ='.$linkId);
        $this->view->linkName = $res['name'];

        // vrat tabulku s RR dle mailu za bulletinu
        $this->view->rrPerLink = $this->_statistics->getResponseRateTable('link', $emailId, $linkId);


    }

   /** 10.9.09 RV
    * @desc
    *
    */

    public function _areaAction()
    {
        $db = Zend_Registry::get('db');

        $request = $this->getRequest();
            echo '*****';
            print_r($request);
            echo '******';


        // nacti pocet kanalu, kvuli vypisu prehledove tabulky
        $channels = new Channels();
        $channelsCount = $channels->getChannelsCount();

        // nacti pocet regionu, kvuli vypisu prehledove tabulky
        $regions = new Regions();
        $regionsCount = $regions->getRegionsCount();

        // nacti pocet reprezentantu, kvuli vypisu prehledove tabulky
        $reps = new Reps();
        $repsCount = $reps->getRepsCount();


        switch ($this->mode) {

            /**
             * returnrate action - segments (mode=>'segments')
             * rozdelene dle segmentu
             */
            case 'segments' :

                // nacti pocet segmentu, kvuli vypisu prehledove tabulky
                $segments = new Segments();
                $segmentsCount = $segments->getSegmentsCount();

               // vrat pocet kontaktu
               $usersCount = count($this->_statistics->getReadersForBulletin());

               // vrat kontakty pro kazdy segment a jejich procentualni vyjadreni
               $readersPerSegment = $this->_statistics->getReadersPerSegment($usersCount);

               // posli do view nactene segmenty, pocet uzivatelu, zobraz tabulku
               $this->view->segments = $readersPerSegment;
               $this->view->usersCount = $usersCount;

               // kolac -segmenty
               $this->view->segmentChart = $this->_statistics->getReadersPerSegmentChart();

               // vyvoj v case -graf
               $this->view->timeChart = $this->_statistics->getReadersPerSegmentInTimeChart();

               // tabulka - celkovy prehled
               $this->view->segmentsCount = $segmentsCount;
               $this->view->bulletinsCount = $this->_statistics->getBulletinsCount();
               $this->view->overview = $this->_statistics->getReadersPerSegmentInBulletins();

            break;

            /**
             * rr action - channels (mode=>'channels')
             * kontakty rozdelene dle kanalu
             */
            case 'channels' :

            break;
            /**
             * index action (mode=>'')
             */
            default:

               // kontaktu
               $contactsCount = $this->_statistics->getContactsCount();
               $this->view->contactsCount = $contactsCount;

               // vrat tabulku s RR dle bulletinu
               $this->view->rrPerBulletin = $this->_statistics->getReturnRateTable(0,0);


               // pocet ctenaru
               $readersCount = count($this->_statistics->getReadersForBulletin());
               $this->view->readersCount = $readersCount;

               // procento aktivnich
               $this->view->activePercents = (bcdiv($readersCount, $contactsCount) * 100);

               // graf ctenaru v case
               $this->view->readersChart = $this->_statistics->getRRPerBulletinsChart(5);

               // tabulka ctenaru v case
             break;
        }

    }


    /**
     * Wrapper ke questionnaire controller s omezenou funkcnosti pouze k prohlizeni
     * vysledkuu doaznikuu.
     *
     * Pouziva se i z monitoringu indetailu - presentations, pro tento je pridan filtr a navigace
     * mezi ostatnimi castmi monitoringu indetailu.
     *
     * @author Petr Skoda
     */
    public function questionnairesAction()
    {
        $req = $this->getRequest();

        if(!Ibulletin_AdminAuth::hasPermission('privilege_stats_can_export_xls')){
            // Ulozime do view info, ze nemaji byt poskytovany exporty
            $this->view->noExports = true;
        }
        else{
            $this->view->noExports = false;
        }

        // INDETAIL PRESENTATIONS
        if($req->getParam('indetailpresentaions')){
            $this->view->isIndetailPresentations = true;

            $this->moduleMenu->setCurrentLocation('indetail');

            // Nastavime si kvuli filtru do requestu 'content' - ID contentu nebo opacne podle toho, jestli byl zadan 'content'
            if($req->getParam('content')){
                $req->setParam('id', $req->getParam('content'));
            }
            else{
                $req->setParam('content', $req->getParam('id'));
            }

            // Pripravime data filtru
            list($from, $to, $fromIso, $toIso, $repreId, $contentId) = $this->indetail_filters(true);

            // Pokud neni zadan content, vratime se na presentations
            if(!$contentId){
                $this->redirect('indetailpresentations');
            }

            $this->view->stattablename = $this->assembleStatTableName('indetailquestionnaires', $fromIso, $toIso, $contentId);
        }
        else{
            // Pripravime prazdne promenne
            $fromIso = $toIso = $repreId = null;
        }

        // Ziskame objekt questionnaire controlleru
        Zend_Controller_Front::getInstance()
            ->getDispatcher()
            ->loadClass('QuestionnaireController');
        $questionnaireController = new Admin_QuestionnaireController($this->getRequest(), $this->getResponse());

        $id = $req->getParam('id');

        // Rozhodneme co se ma provest
        ### Vypsat prehled odpovedi
        if($id && $req->getParam('operation') == 'showdata'){
            //$questionnaireController->showdataAction();
            $this->view->renderWhat = 'showdata';
            if(empty($this->view->isIndetailPresentations)){
                $this->moduleMenu->setCurrentLocation('questionnaires_showData');
            }

            // Najdeme informace o contentu - predevsim nazev
            $content = Contents::get($id);
            $this->view->content = $content;
            $this->view->fromIso = $fromIso;
            $this->view->toIso = $toIso;
            $this->view->repreId = $repreId;

            // Vygenerujeme tabulku prehledu
            $this->view->answersTable = Statistics::getAnswersOverviewTable($req->getParam('id'), $fromIso, $toIso, $repreId);

            // Datove typy odpovedi a jejich prepis na text
            $this->view->answerTypes = Questions::getTypes();

        }
        ### Vypsat seznam dotazniku
        else{
            $questionnaireController->indexAction(array('Ibulletin_Content_Indetail'));
        }
    }


    /**
     * Výpis statistik chování lékařů - graf příhodů během dne, týdne a ve dnech po spuštění
     *
     * @author Jaromir Krotky
     */
    public function behaviorAction()
    {
        $texts = Ibulletin_Texts::getSet();

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $request = $this->getRequest();

        // Nacteni JS knihoven potrebnych pro graf
        $this->loadJqplot();

        $formData  = $this->_request->getParams();
       // nacist bulletiny a stranky
       $pages = $this->_statistics->getPages();
       $menu_array = array("0-0"=>$texts->all_bulletins." - ".$texts->all_pages);
       $bull_id = 0;
       $last_bulletin = 0;
       foreach($pages as $page) {
            if ($bull_id!=$page['bulletin_id']) {
                $menu_array[$page['bulletin_id'].'-0'] = $page['bulletin_name'].' - '.$texts->all_pages;
                $bull_id = $page['bulletin_id'];
            }
            if ($last_bulletin==0) {
                $last_bulletin = $page['bulletin_id'];
            }
            $menu_array[$page['bulletin_id'].'-'.$page['id']] = $page['bulletin_name'].' - '.$page['name'];
       }

       $bulletin_id = $page_id = 0;
       if(isset($formData['bulletin-page']))  {
           $params =  explode('-', $formData['bulletin-page']);
           $bulletin_id = $params[0];
           $page_id = $params[1];
       }
       else {
            $bulletin_id = $last_bulletin;
            $page_id = 0;
            $formData['bulletin-page'] = $bulletin_id.'-'.$page_id;
       }

       // Nacteme pripadny odeslany form s range pro pocet dni v grafu visitsProgress
       $visitsRange = $request->getParam('visits_range', 30);

       // posli do view menu, zobraz grafy
       $this->view->formData = $formData;
       $this->view->menu_array = $menu_array;
       $this->view->daysInWeekChartData = array('data'=>$this->_statistics->getBehaviorChart('daysInWeek', $bulletin_id, $page_id));
       $this->view->hoursInDayChartData = array('data'=>$this->_statistics->getBehaviorChart('hoursInDay', $bulletin_id, $page_id));
       $progressChartData = $this->_statistics->getBehaviorChart('visitsProgress', $bulletin_id, $page_id, $visitsRange);
       $progress_chart_limit = $progressChartData['limit'];
       unset($progressChartData['limit']);
       $this->view->visitsProgressChartData = $progressChartData;
       $this->view->visitsProgressChartDataLimit = $progress_chart_limit;
       $this->view->visitsRange = $visitsRange;

    }


    /**
     * Výpis statistik indetail prezentace
     *
     * @author Jaromir Krotky
     */
    public function indetailAction()
    {
        Ibulletin_Js::addJsFile('admin/monitoring.js');

        // Headers kvuli cachovani
        /* Nepouzivame, protoze v adminu nam nevadi, ze se nevraci 304 not modified
        $response = $this->getResponse();
        $response->setHeader('Expires', gmdate('D, d M Y H:i:s', time()+3800).' GMT', true);
        $response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', time()-3800).' GMT', true);
        $response->setHeader('Cache-Control', 'public', true);
        $response->setHeader('Cache-Control', 'max-age=3800');
        $response->setHeader('Pragma', '', true);
        */

        profile('indStart');
        $texts = Ibulletin_Texts::getSet();

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $request = $this->getRequest();

        $formData  = $this->_request->getParams();
        //$contents = $this->_statistics->getContents();
        $contents = Contents::getList('Ibulletin_Content_Indetail');
        if(isset($formData['content'])) {
            $content_id = $formData['content'];
        }
        elseif (count($contents)>0) {
            $firstContent = current($contents);
            $content_id = $firstContent['id'];
            $formData['content'] = $content_id;
        }
        else {
            $content_id = 0;
        }

        if (is_numeric($content_id) && $content_id>0 && !$request->getParam('export')) {
            // Nastaveni rozsahu datumu
            $shortISO = 'yyyy-MM-dd';
            $today = new Zend_Date();
            $firstUsed = $this->_statistics->getContentsFirstUsed('Ibulletin_Content_Indetail');
            foreach($firstUsed as $key => $val){
                $date = new Zend_Date($val, Zend_Date::ISO_8601);
                $firstUsed[$key] = $date->get(Zend_Date::RFC_2822);
                //echo $date->get(Zend_Date::RFC_2822);
            }
            $firstUsedThisContent = new Zend_Date($firstUsed[$content_id], Zend_Date::RFC_2822);
            $from = new Zend_Date($request->getParam('from'), Zend_Date::ISO_8601);
            $to = new Zend_Date($request->getParam('to'), Zend_Date::ISO_8601);
            $from = $from ? $from : $firstUsedThisContent;
            $to = $to ? $to : $today;
            // Pokud byl zmenen content, nastavime from a to na defaults
            if($request->getParam('last_content') != $content_id){
                $from = $firstUsedThisContent;
                $to = $today;
            }
            // Pokud neni stanoven cas, musime nastavit hodiny "to" na 23:59:59 aby se zapocitaval i posledni den
            $noTimeDate = new Zend_Date('2010-11-11');  // Pouzivame ke zjisteni jestli je v Date objektu cas
            if($to->getTime() == $noTimeDate->getTime()){
                $to = $to->addDay(1)->addSecond(-1);
                //echo $to." | ".$request->getParam('to');
                //exit;
            }
            // Stringy v plnem ISO-8061
            $fromIso = $from->get(Zend_Date::ISO_8601);
            $toIso = $to->get(Zend_Date::ISO_8601);
            //echo $fromIso.' '.$toIso;

            profile('afterFirstUsed');

            $report_emails_data = $this->_statistics->getInDetailContentEmailReport($content_id,
                $fromIso, $toIso);
            $report_emails = array();
            $report_content_data = array(); // Soucty sloupcu
            $all_invited = 0;
            foreach ($report_emails_data as $data) {
                $report_emails[] = array('email_id'=>$data['email_id'],
                                        'wave_id'=>$data['wave_id'],
                                        'name'=>$data['name'],
                                        'invited'=>$data['invited'],
                                        'come'=>$data['come'],
                                        'come%'=>$data['invited'] ? round($data['come']/$data['invited'] * 100,2) : 0,
                                        'started'=>$data['started'],
                                        'started%'=>$data['come'] ? round($data['started']/$data['come'] * 100,2) : 0,
                                        'finished'=>(int) $data['finished'],
                                        'finished%'=>$data['started'] ? round($data['finished']/$data['started'] * 100,2) : 0,
                                        );
                $all_invited += $data['invited'];

                // Pripravime soucty sloupcu
                foreach($data as $key => $val){
                    if(!array_key_exists($key, $report_content_data)){
                        $report_content_data[$key] = $val;
                    }
                    else{
                        $report_content_data[$key] += $val;
                    }
                }
            }

            profile('afterEmailReport');

            $report_content = array();
            $data = $report_content_data;
            // hack, aby se k pozvaným počítali nejen emaily, ale i to co je nastaveno u zvacích vln jiných typů
            //$data['invited'] = $all_invited;
            if(!empty($data)){
                $report_content = array('isOverall' => 'yes',
                                        'name'=>$texts->all_together,
                                        'invited'=>$data['invited'],
                                        'come'=>$data['come'],
                                        'come%'=>$data['invited'] ? round($data['come']/$data['invited'] * 100,2) : 0,
                                        'started'=>$data['started'],
                                        'started%'=>$data['come'] ? round($data['started']/$data['come'] * 100,2) : 0,
                                        'finished'=>$data['finished'],
                                        'finished%'=>$data['started'] ? round($data['finished']/$data['started'] * 100,2) : 0,
                                        );
            }

            $slideVisitorsData = $this->_statistics->getSlideVisitors($content_id, $fromIso, $toIso);
            $slideVisitors = array();
            foreach ($slideVisitorsData as $slide) {
                if(!isset($pocet)) $pocet = $slide['pocet'];
                $slideVisitors[] = array('slide' => $slide['slide'],
                                        'pocet' => $slide['pocet'],
                                        'procent' => $pocet ? round(($slide['pocet']/$pocet*100), 2) : 0,
                                        'avg' => round($slide['avg_time']/1000),
                                        'median' => round($slide['med_time']/1000),
                                        'mandatory' => $slide['mandatory']);
                $pocet = $slide['pocet'];
            }

            profile('afterSlideVisitors');

            $inDetailVisitorsInOrderData = $this->_statistics->getInDetailVisitorsInOrder($fromIso, $toIso);
            $inDetailVisitorsInOrderData2 = array(); // Kvuli prazdnym datum
            $max_poradi = 1;
            foreach ($inDetailVisitorsInOrderData as $row) {
                $inDetailVisitorsInOrderData2[$row['content_id']][$row['poradi']] = $row['pocet'];
                if ($max_poradi<$row['poradi']) $max_poradi = $row['poradi'];
            }
            $inDetailVisitorsInOrder = array();
            foreach ($inDetailVisitorsInOrderData2 as $c_id=>$row) {
                for($i=1;$i<=$max_poradi;$i++) {
                    if (isset($inDetailVisitorsInOrderData2[$c_id][$i])) $inDetailVisitorsInOrder[$c_id][$i] = $inDetailVisitorsInOrderData2[$c_id][$i];
                    else $inDetailVisitorsInOrder[$c_id][$i] = 0;
                }
            }

            profile('afterVisitorsInOrder');
            // posli do view menu, zobraz grafy
            $avgMedA = $this->_statistics->getInDetailAverage($content_id, $fromIso, $toIso);
            $formData['content'] = $content_id;
            $this->view->formData = $formData;
            $this->view->contents = $contents;
            $this->view->report_emails = $report_emails;
            $this->view->report_content = $report_content;
            $this->view->slideVisitors = $slideVisitors;
            $this->view->average = round($avgMedA['avg']/1000);
            $this->view->median = round($avgMedA['median']/1000);
            $this->view->slideVisitorsChart = $this->_statistics->getSlideVisitorsChart($content_id, $fromIso, $toIso, $slideVisitorsData);
            $this->view->inDetailVisitorsInOrder = $inDetailVisitorsInOrder;
            $this->view->inDetailVisitorsMaxPoradi = $max_poradi;
            $this->view->dateFrom = $from->toString($shortISO);
            $this->view->dateTo = $to->toString($shortISO);
            $this->view->contentFirstUsed = $firstUsed;
        }
        # EXPORT navstev zadaneho contentu
        elseif(is_numeric($content_id) && $content_id>0 && $request->getParam('export') == 'visits') {
           if(!empty($this->view->noExports)){ // Pokud je zakazano exportovat, vyskocime
                $this->_forward('index');
                return;
            }

            $table = Statistics::getInDetailExportVisits($content_id);

            Ibulletin_Excel::ExportXLSX($table, 'visitors', false, array(6, 13, 14, 5, 8, 10, 24, 5, 5, 5, 10, 24, 5, 20, 40, 10), null);
            $this->view->renderWhat = $request->getParam('operation');

            // Vypneme vsechno renderovani view skriptuu
            $this->getHelper('viewRenderer')->setNeverRender(true);
            exit();
        }
        # Prazdna data, neni co zobrazovat
        else {
            $this->view->formData = $formData;
            $this->view->contents = array();
            $this->view->report_emails = array();
            $this->view->report_content = array();
            $this->view->slideVisitors = array();
            $this->view->average = 0;
            $this->view->slideVisitorsChart = null;
            $this->view->inDetailVisitorsInOrder = array();
            $this->view->inDetailVisitorsMaxPoradi = 1;
        }

        profile('indEnd');

    }

    /**
     * Pripravi data pro nastavovani rozsahu od kdy do kdy maji byt data zobrazena
     * a vyber reprezentanta.
     * Mel by byt spousten jen jednou pro kazde zobrazeni stranky.
     *
     * @param   bool    Pridat vybiraci pole contentu?
     * @return  array   Pole obsahujici from, to, fromIso, toIso, $repreId, $contentId
     */
    public function indetail_filters($contentFilter = false)
    {
        $session = Zend_Registry::get('session');
        $db = Zend_Registry::get('db');
        $request = $this->getRequest();
        $texts = Ibulletin_Texts::getInstance();
        $texts->setActualContext('indetail', 'stats', 'admin');

        // Pripravime data formulare pripadne ulozena v session
        if(isset($session->indetailmonitoring_formData)){
            $formData = $session->indetailmonitoring_formData;
        }
        else{
            $formData = new stdClass;
        }
        $request->getParam('from') ? $formData->from = $request->getParam('from') : null;
        $request->getParam('to') ? $formData->to = $request->getParam('to') : null;
        $request->getParam('repre') !== null ? $formData->repre = $request->getParam('repre') : null;
        $request->getParam('content') !== null ? $formData->content = $request->getParam('content') : null;

        // Doplnime nenastavene prvky formData
        !isset($formData->from) ? $formData->from = null : null;
        !isset($formData->to) ? $formData->to = null : null;
        !isset($formData->repre) ? $formData->repre = null : null;
        !isset($formData->content) ? $formData->content = null : null;

        // custom JS
        Ibulletin_Js::addJsFile('admin/monitoring.js');

        // Nastaveni rozsahu datumu
        $today = new Zend_Date();
        //$today = $today\->setTime('23:59:59.9999'); // Pro dotazy z DB musi obsahovat cely dnesni den
        $firstUsed = $this->_statistics->getContentsFirstUsed('Ibulletin_Content_Indetail');
        $firstUsedTotal = new Zend_Date(); // First used from all contents
        foreach($firstUsed as $key => $val){
            $date = new Zend_Date($val, Zend_Date::ISO_8601);
            // Hodiny firstUsed posuneme na 0:00:00 kvuli tomu, aby nam nevypadl nekdo na okraji (to se stavalo)
            $date->setTime('0:00:00');//->addSecond(-1); // Npouzivame, zmeni den prvni navstevy o 1 zpet
            $firstUsed[$key] = $date->get(Zend_Date::RFC_2822);
            if($firstUsedTotal > $date){
                $firstUsedTotal = $date;
            }
        }
        // Vybereme nejake datum pro
        if($contentFilter && isset($firstUsed[$formData->content])){
            $firstUsedThisContent = new Zend_Date($firstUsed[$formData->content], Zend_Date::RFC_2822);
        }
        else{
            $firstUsedThisContent = $firstUsedTotal;
        }

        try{
            $from = $formData->from ? new Zend_Date($formData->from, Zend_Date::ISO_8601) : null;
            $to = $formData->to ? new Zend_Date($formData->to, Zend_Date::ISO_8601) : null;
        }
        // Hlidame kvuli nacteni ruznych retezcu do Zend_Date
        catch(Exception $e){
            $from = null;
            $to = null;
        }
        $from = $from ? $from : $firstUsedThisContent;
        $to = $to ? $to : $today;
        // Pokud byl zmenen content, nastavime from a to na defaults
        /* NEPOUZIVAME, misto toho RESET
        if(!isset($session->indetailmonitoring_lastContent) || $session->indetailmonitoring_lastContent != $formData->content){
            $from = $firstUsedThisContent;
            $to = $today;
        }
         */

        // RESET - Pokud je pozadovan reset formulare, nastavime defaultni hodnoty
        if($request->getParam('reset')){
            $from = $firstUsedThisContent;
            $to = $today;
            $formData->repre = null;
            //smazeme i session
            unset($session->indetailmonitoring_formData);
        }

        // Pokud neni stanoven cas, musime nastavit hodiny "to" na 23:59:59 aby se zapocitaval i posledni den
        $noTimeDate = new Zend_Date('2010-11-11');  // Pouzivame ke zjisteni jestli je v Date objektu cas
        if($to->getTime() == $noTimeDate->getTime()){
            $to = $to->addDay(1)->addSecond(-1);
        }


        // Stringy v plnem ISO-8061
        $formData->from = $fromIso = $from->get(Zend_Date::ISO_8601);
        $formData->to = $toIso = $to->get(Zend_Date::ISO_8601);

        // Pro vypis uzivateli pouzijeme kratky format
        $shortISO = 'yyyy-MM-dd';
        $fromShort = $from->get($shortISO);
        $toShort = $to->get($shortISO);

        //echo $fromIso.' | '.$toIso;

        // Pro nastaveni slideru spocitame pocet dnu od zacatku po dnesek
        $minDateSlider = $firstUsedThisContent < $from ? $firstUsedThisContent : $from;
        $timerangeSliderMin = round(($today->subTime($today)->get(Zend_Date::TIMESTAMP) - $minDateSlider->subTime($minDateSlider)->get(Zend_Date::TIMESTAMP)) / (3600*24));
        //echo ($today->subTime($today)->get(Zend_Date::TIMESTAMP) - $minDateSlider->subTime($minDateSlider)->get(Zend_Date::TIMESTAMP)) / (3600*24);
        //echo " | ".$minDateSlider->subTime($minDateSlider)." | ".$today."<br/>";
        $this->view->timerangeSliderMin = $timerangeSliderMin;



        // Najdeme seznam reprezentantu do selectu
        $sel = new Zend_Db_Select($db);
        $sel->from(array('u' => 'users'), array('id', 'name', 'surname'))
            ->where('u.is_rep')
            ->order(array('u.surname', 'u.name', 'u.id'));
        $repres = $db->fetchAll($sel);
        array_unshift($repres, array('id' => '', 'name' => $texts->get('none_selected'), 'surname' => ''));

        // Najdeme seznam contentu do selectu
        if($contentFilter){
            $contents = Contents::getList('Ibulletin_Content_Indetail', array('id desc'));
            $contentsSel = array('0' => $texts->get('none_selected'));
            foreach($contents as $content){
                $contentsSel[$content['id']] = $content['id'].' - '.$content['object']->name;
            }
        }


        // Data do zadavacich policek
        $this->view->dateFrom = $fromShort;
        $this->view->dateTo = $toShort;
        $this->view->repres = $repres;
        $this->view->filtersData = $formData;
        $contentFilter ? $this->view->contentsList = $contentsSel : null;

        // Ulozime potrebna data do session
        $session->indetailmonitoring_lastContent = $formData->content;

        //filtr do session ulozime jen pri odeslani formulare
        if ($request->getParam('submit')) {
            $session->indetailmonitoring_formData = $formData;
        }

        // Resetujeme context
        $texts->setActualContext();

        return array($from, $to, $fromIso, $toIso, $formData->repre, $formData->content);
    }

    /**
     * Vypis seznamu indetail prezentaci a zakladnich statistik.
     */
    public function indetailpresentationsAction()
    {
        $config = Zend_Registry::get('config');

        $this->moduleMenu->setCurrentLocation('indetail');
        $this->setActionTitle(Ibulletin_Texts::getSet('admin.stats')->menu_indetail.' &rarr; '.Ibulletin_Texts::get('pageTitle'));

        // Pripravime data filtru
        list($from, $to, $fromIso, $toIso, $repreId) = $this->indetail_filters();

        //pocet zobrazenych itemu, 0 = vsechny
        if ($this->_hasParam('showall')) {
            $limit = 0;
        } else {
            $limit = (int) $config->stats->number_display_issues * 2;
        }
        $this->view->stattablename = $this->assembleStatTableName('indetailpresentations', $fromIso, $toIso);

        $presentations = $this->_statistics->indetail_getPresentations($fromIso, $toIso, $repreId, null, $limit);

        $total_count = $this->_statistics->indetail_getPresentationsCount($fromIso, $toIso, $repreId);

        if ($limit >= $total_count || $limit == 0) {
            $this->view->bul_more = false;
        } else {
            $this->view->bul_more = true;
        }
        $this->view->presentations = $presentations;


    }

    /**
     * Vypis statistik slidu v prezentaci pro indetail monitoring.
     */
    public function indetailpresentationsslidesAction()
    {
        $this->moduleMenu->setCurrentLocation('indetail');
        $this->setActionTitle(Ibulletin_Texts::getSet('admin.stats')->menu_indetail.' &rarr; '.Ibulletin_Texts::get('pageTitle'));

        // Pripravime data filtru
        list($from, $to, $fromIso, $toIso, $repreId, $contentId) = $this->indetail_filters(true);

        // Pokud neni zadan content, vratime se na presentations
        if(!$contentId){
            $this->redirect('indetailpresentations');
        }

         $this->view->stattablename = $this->assembleStatTableName('indetailslides', $fromIso, $toIso, $contentId);

        // Nazev prezentace
        $content = Contents::get($contentId);
        $this->view->presentationName = $content['object']->name;


        // Data pro slajdy
        $slides = $this->_statistics->indetail_getPresentationsSlides($fromIso, $toIso, $repreId, $contentId);

        //Data pro souhrn
        $this->view->summary = $this->_statistics->indetail_getPresentations($fromIso, $toIso, $repreId,$contentId);

        // Maxima grafu pres reprezentanty
        if($repreId){
            $maxs = $this->_statistics->indetail_getPresentationsSlides($fromIso, $toIso, $repreId, $contentId, true);
            $this->view->maxs = $maxs;
        }

        //zobrazení nazvu nebo cisla slidu v grafu dle konfigurace
        $this->view->useSlideNameInstedOfSlideNum = $this->config->indetail->useSlideNameInstedOfSlideNum;

        $this->view->slides = $slides;

        // Pripravime seznam dostupnych nahledu slajdu
        $this->view->slide_previews_path = Slides::getSlidesPreviewsPath($content);
        $this->view->slidePreviews = Slides::getSlidesPreviews($content);

        ## Graf navstevnosti slidu
        // Nacteni potrebnych JS knihoven
        Ibulletin_Js::addJsFile('d3/d3.min.js');
        Ibulletin_Js::addJsFile('d3/d3-tip.js');
        Ibulletin_Js::addJsFile('d3/rgbcolor.js');
        Ibulletin_Js::addJsFile('d3/StackBlur.js');
        Ibulletin_Js::addJsFile('d3/canvg.js');

    }

    /**
     * Vypis statistik globalnich resources pro indetail monitoring.
     */
    public function indetailpresentationsresourcesAction()
    {
        $this->moduleMenu->setCurrentLocation('indetail');
        $this->setActionTitle(Ibulletin_Texts::getSet('admin.stats')->menu_indetail.' &rarr; '.Ibulletin_Texts::get('pageTitle'));

        // Pripravime data filtru
        list($from, $to, $fromIso, $toIso, $repreId, $contentId) = $this->indetail_filters(true);

        $this->view->stattablename = $this->assembleStatTableName('indetailresources', $fromIso, $toIso, $contentId);

        // Pokud neni zadan content, vratime se na presentations
        if(!$contentId){
            $this->redirect('indetailpresentations');
        }

        $resources = $this->_statistics->indetail_getPresentationsResources($fromIso, $toIso, $repreId, $contentId);

        $this->view->resources = $resources;

    }


    /**
     * Vypis vstupnich kanalu prezentaci.
     */
    public function indetailpresentationschannelsAction()
    {
        $config = Zend_Registry::get('config');
        $this->moduleMenu->setCurrentLocation('indetail');
        $this->setActionTitle(Ibulletin_Texts::getSet('admin.stats')->menu_indetail.' &rarr; '.Ibulletin_Texts::get('pageTitle'));

        // Pripravime data filtru
        list($from, $to, $fromIso, $toIso, $repreId, $contentId) = $this->indetail_filters(true);

        // Pokud neni zadan content, vratime se na presentations
        if(!$contentId){
            $this->redirect('indetailpresentations');
        }

        $this->view->stattablename = $this->assembleStatTableName('indetailchannels', $fromIso, $toIso, $contentId);

        $channels = $this->_statistics->indetail_getPresentationEntryChannels($fromIso, $toIso, $repreId, $contentId);
        $channelsTotals = $this->_statistics->indetail_getPresentationEntryChannelsTotals($fromIso, $toIso, $repreId, $contentId);

        // Upravime total a other podle configu, pokud jsou definovane
        if($config->indetail->othersCount){
            foreach($channels as $key => $channel){
                if($channel['name'] == 'other'){
                    $channels[$key]['invited'] = $config->indetail->othersCount;
                }
            }
        }
        if($config->indetail->totalCount){
            $channelsTotals[0]['invited'] = $config->indetail->totalCount;
        }
        else{
//            $channelsTotals[0]['invited'] = $channelsTotals[0]['invited_email'];
//            $this->view->totalOnlyEmail = true;
                $channelsTotals[0]['invited'] = 0;
        }


        $this->view->channels = $channels;
        $this->view->channelsTotals = $channelsTotals[0];

    }

    /**
     * Presentations - Questions and answers - wrapper na questionnaireAction
     *
     * Je nutne pouzit wrapper kvuli kontrole prav pro monitoring (primo na questionnaires
     * by uzivatel nebyl pusten, pokud jsou prava povolena jen pro indetail)
     *
     * View script vyrenderuje puvodni view questionnaires.phtml.
     */
    public function indetailpresentationsqaAction()
    {
        // Nastavime akci na questionnaires (kvuli automatickemu nastaveni textu)
        $this->getRequest()->setActionName('questionnaires');
        // Provedeme jinou akci
        $this->questionnairesAction();
    }


    /**
     * Vypis seznamu resources a jejich zakladni statistika.
     */
    public function indetailresourcesAction()
    {

        $config = Zend_Registry::get('config');
        $this->moduleMenu->setCurrentLocation('indetail');
        $this->setActionTitle(Ibulletin_Texts::getSet('admin.stats')->menu_indetail.' &rarr; '.Ibulletin_Texts::get('pageTitle'));

        // Pripravime data filtru
        list($from, $to, $fromIso, $toIso, $repreId) = $this->indetail_filters();

        //pocet zobrazenych itemu, 0 = vsechny
        if ($this->_hasParam('showall')) {
            $limit = 0;
        } else {
            $limit = (int) $config->stats->number_display_issues;
        }

        $resources = $this->_statistics->indetail_getResources($fromIso, $toIso, $repreId, $limit);

        $total_count = $this->_statistics->indetail_getResourcesCount($fromIso, $toIso, $repreId);

        if ($limit >= $total_count || $limit == 0) {
            $this->view->bul_more = false;
        } else {
            $this->view->bul_more = true;
        }

        $this->view->stattablename = $this->assembleStatTableName('indetailresources', $fromIso, $toIso);

        $this->view->resources = $resources;

    }

    /**
     * Vypis seznamu resources a jejich zakladni statistika.
     */
    public function indetailvisitsAction()
    {
        $this->moduleMenu->setCurrentLocation('indetail');
        $this->setActionTitle(Ibulletin_Texts::getSet('admin.stats')->menu_indetail.' &rarr; '.Ibulletin_Texts::get('pageTitle'));

        // Pripravime data filtru
        list($from, $to, $fromIso, $toIso, $repreId, $contentId) = $this->indetail_filters(true);

        $this->view->stattablename = $this->assembleStatTableName('indetailvisits', $fromIso, $toIso, $contentId);

        $visits = $this->_statistics->indetail_getVisits($fromIso, $toIso, $repreId, $contentId);

        foreach($visits as $key => $visit){
            $visits[$key]['sum_time'] = $visit['sum_time'];
            $visits[$key]['avg_usr_time'] = $visit['avg_usr_time'];
            $visits[$key]['avg_ses_time'] = $visit['avg_ses_time'];
        }


        $this->view->visits = $visits;

    }


    /**
     * Vypis seznamu resources a jejich zakladni statistika.
     */
    public function indetailconnectionsAction()
    {
        $this->moduleMenu->setCurrentLocation('indetail');
        $this->setActionTitle(Ibulletin_Texts::getSet('admin.stats')->menu_indetail.' &rarr; '.Ibulletin_Texts::get('pageTitle'));

        // Pripravime data filtru
        list($from, $to, $fromIso, $toIso, $repreId) = $this->indetail_filters();


        // --- Poradi prezentaci ---
        $connections = $this->_statistics->indetail_getConnectionsOrder($fromIso, $toIso, $repreId);

        $orderedConnections = array();

        foreach ($connections as $c) {
            $orderedConnections[$c['content_id']]['name'] = $c['content_name'];
            $orderedConnections[$c['content_id']][$c['poradi']] = $c['pocet'];
        }

        // Zjistime, jake mame nejvetsi poradi
        $maxOrder = 0;
        foreach($connections as $row){
            if($row['poradi'] > $maxOrder){
                $maxOrder = $row['poradi'];
            }
        }

        $select = $this->db->select()->from(array('ids'=>'indetail_stats'),array('content_id'))
                ->join(array('u'=>'users_vf'),'u.id = ids.user_id',null)
                ->group('ids.content_id')->order('ids.content_id');
        $contents =$this->db->fetchCol($select);

        $content_combinations = $this->combinations($contents);

        $combinatedConnections = array();

        foreach ($content_combinations as $c) {
            $combinatedConnections[] = $this->_statistics->indetail_getConnectionsPresentationsCombinations($fromIso, $toIso, $repreId,$c);
        }

        $this->view->combinations = $combinatedConnections;
        $this->view->connections = $orderedConnections;
        $this->view->connectionsMaxOrder = $maxOrder;

        $this->view->stattablename1 = $this->assembleStatTableName('indetailconnections1', $fromIso, $toIso);
        $this->view->stattablename2 = $this->assembleStatTableName('indetailconnections2', $fromIso, $toIso);
        $this->view->stattablename3 = $this->assembleStatTableName('indetailconnections3', $fromIso, $toIso);

        // --- Kolik prezentaci videli ---
        $seen = $this->_statistics->indetail_getConnectionsPresentationsSeen($fromIso, $toIso, $repreId);
        $this->view->seen = $seen;

        $this->view->fromIso = $fromIso;
        $this->view->toIso = $toIso;
        $this->view->repreId = $repreId;

    }


    /**
     *  Vytvoří formulář s tlačítkem pro export do XLSX.
     *
     * @param array(what, bulletinid, someId,...)
     *                  Pro nove exporty mohou obsahovat jakakoli data jsou potreba, zadne povinne prvky nejsou.
     * @param string    Pro nove exporty zadavame navic jmeno exportu odpovidajici jmenu metody z Monitoringexports.
     *                  Toto jmeno je predano v URL jako exportaction.
     * @param string    Text pro odkaz na export. Nepovinne.
     *
     * @param string    Styly odkazu link | btn
     */
    public static function getExportButton($aParams, $exportName, $anchorText = '',$link_style = 'link')
    {
        // Pokud uzivatel nema opravneni na export, vratime nic
        if(!Ibulletin_AdminAuth::hasPermission('privilege_stats_can_export_xls')){
            return '';
        }

        if($anchorText) {
            $anchorText .= '&nbsp;';
        }

        Zend_Loader::loadClass('Zend_View_Helper_Url');
        $url = new Zend_View_Helper_Url();

        // sestavi url  http://inbulletin/admin/stats/export/what/readers
        if ($exportName) {
            $aExport = array('action' => 'export', 'exportaction' => $exportName);

            //$aParams = $aExport + $aParams;
            // Musime vycistit polozky, ktere jsou prazdne (null nebo '')
            // Vytvorime GET string kvuli trnsformacim na z "+" na " ", kterou dela apache mod_rewrite
            $getArray = array();

            if ($aParams) {
                foreach ($aParams as $key => $val) {
                    if ($val === null || $val === '') {
                        unset($aParams[$key]);
                    }
                    $getArray[] = $key . '=' . urlencode($val);
                }
                $getString = '?' . join('&', $getArray);

                $texts = Ibulletin_Texts::getSet("admin.stats");

                if ($link_style == 'btn') {
                    $link_style = 'btn btn-mini btn-info btn';
                }
            }

            return '<a class="' . $link_style . '-export tip" data-original-title="' . $texts->tooltip->export_xls . '" href="' . $url->url($aExport) . $getString . '">' . $anchorText . '<span class="glyphicon glyphicon-save"></span></a>';
        } else {
            return '<a class="' . $link_style . '-export tip" name="kotva">' . $anchorText . '<span class="glyphicon glyphicon-download-alt"></span></a>';
        }
    }

    /**
     * Nova akce pro provadeni exportu. Vola se nastavenim parametru v getExportButton
     */
    public function exportAction()
    {
        $req = $this->getRequest();

        $exportAction = $req->getParam('exportaction');
        if(method_exists('Monitoringexports', $exportAction)){
            call_user_func(array('Monitoringexports', $exportAction), $req);
        }
        else{
            Phc_ErrorLog::warning('Admin_StatsController::exportAction()',
                'Nepodarilo se najit metodu pro pozadovany export. ExportAction: "'.$exportAction.'"');
        }


        exit(); // Kvuli tomu, aby se nehledal renderscript
    }


    /**
     *  Vypis statistik videii pro jednotlive uzivatele.
     *
     *  !! REPREZENTANTI!! V detailnim exportu pro jedno video jsou uzivatele zarazeni
     *  k vice reprezentantum uvedeni v zalozkach vsech reprezentantu.
     */
    public function videoAction()
    {
        $db = Zend_Registry::get('db');
        $req = $this->getRequest();

        // Nejdrive vytahneme vsechna videa, abychom mohli najit, ktere ankety k nim patri
        $videos = (array)Contents::getList('Ibulletin_Content_Video') + (array)Contents::getList('Ibulletin_Content_Indetail');
        //$videos = Contents::getList('Ibulletin_Content_Video');

        // Najdeme na jakych pages jsou videa umistenaa ve webcastech a slozime ON clausuli
        $webcasts = Contents::getList('Ibulletin_Content_Webcast');
        $videoPagesOnClausules = array();
        $webcastContents = array();
        foreach($webcasts as $webcast){
            if(!empty($webcast['object']->video_id)){
                $videoId1 = $webcast['object']->video_id;
                $webcastContents[] = '(c.id = '.(int)$videoId1.' AND cp.content_id = '.(int)$webcast['id'].')';
            }
        }
        $webcastsOn = join(' OR ', $webcastContents);
        if(empty($webcastsOn)){
            // Pokud neni zadne video ve webcastu dame false, aby to nezlobilo v SQL dotazu
            $webcastsOn = 'false';
        }

        // Maximalni dosazene pozice videa
        // HACK pocitame za nejpozdejsi dosazeny cas vsechno kam playhed sla i to kde nebylo prehravano
        $latestTime = new Zend_Db_Expr("(SELECT user_id, content_video_num, content_id, max(position) AS position FROM players_stats
            --WHERE action='latesttime'
            GROUP BY content_id, content_video_num, user_id)");

        // Nalezneme casy prvnich navstev
        $firstVisits = new Zend_Db_Select($db);
        $firstVisits->from(array('pv' => 'page_views'), array('firstvisit' => new Zend_Db_Expr('min(timestamp)'), 'session_id'))
            ->group('session_id');
        $usersFirstVisits = new Zend_Db_Select($db);
        $usersFirstVisits->from(array('s' => 'sessions'), array('user_id'))
            ->join(array('fv' => $firstVisits), 'fv.session_id = s.id',
                array('firstvisit'=> new Zend_Db_Expr('min(firstvisit)'), 'session_id' => new Zend_Db_Expr('min(session_id)')))
            ->group('user_id');

        // Ratingy - vsechny ratingy uzivatele pro dane video
        $ratings = new Zend_Db_Expr("(SELECT page_id, user_id, avg(rating) AS rating
            FROM page_ratings GROUP BY page_id, user_id)");

        // Vytvorime hlavni dotaz
        $sel = new Zend_Db_Select($db);
        $sel->from(array('u' => 'users'), array('id', 'name', 'surname', 'group'))
            ->join(array('c' => 'content'), '1=1', array('content_id' => 'id'))
            ->joinLeft(array('fv' => $usersFirstVisits), 'fv.user_id = u.id', array('session_id', 'firstvisit'))
            ->joinLeft(array('cp' => 'content_pages'), 'cp.content_id = c.id OR '.$webcastsOn, array())
            //->joinLeft(array('wcp' => 'content_pages'), '1=1', array())
            ->joinLeft(array('pr' => $ratings),
                //'(wcp.page_id = pr.page_id OR cp.page_id = pr.page_id) AND u.id = pr.user_id',
                '(cp.page_id = pr.page_id) AND u.id = pr.user_id',
                new Zend_Db_Expr('avg(pr.rating) AS rating'))
            ->joinLeft(array('lt' => $latestTime), 'lt.user_id = u.id AND lt.content_id = c.id', array('content_video_num', 'position'))

            ->where('NOT test AND target AND NOT client')
            ->where("c.class_name = 'Ibulletin_Content_Video' OR c.class_name = 'Ibulletin_Content_Indetail'")
            ;


        $videoId = $req->getParam('videoId');
        $videoNum = $req->getParam('videoNum');

        if(!$videoId && !$videoNum){

            // Klonujeme select a doplnime potrebna propojeni
            $newSel = clone $sel;
            $newSel
                ->where('lt.position is not null OR pr.rating is not null')
                ->group(array('c.id', 'lt.content_video_num','u.id', 'u.name', 'u.surname', 'u.group', 'fv.session_id', 'fv.firstvisit',
                    'lt.position'))
                ;

            // Provedeme agregovani nad predchozim selectem
            $videoSelAgreg = new Zend_Db_Select($db);
            //echo nl2br((string)$newSel);
            $videoSelAgreg->from(new Zend_Db_Expr(('('.$newSel.')')),
                    new Zend_Db_Expr('content_id, content_video_num, count(distinct(id)) as watchers, avg(rating) AS rating,
                        median(rating) AS rating_median, avg(position) as position, median(position) as position_median'))
                ->group(array('content_id', 'content_video_num'))
            ;
            //echo nl2br((string)$videoSelAgreg);
            //exit;

            $videoData = $db->fetchAll($videoSelAgreg);

            $videosTable = array();
            foreach($videoData as $data){
                $key = $data['content_id'];
                //print_r($videos[$key]['object']->video);
                if(!empty($videos[$key]['object']->video)){
                    $videos[$key]['object']->video->loadFlvInfo();
                    $videoLen = $videos[$key]['object']->video->duration;
                }
                else{
                    $videoLen = 0;
                }
                //echo '<br/>durat:'.$videoLen;
                $videosTable[] = array('content_id' => $key,
                                           'video_num' => $data['content_video_num'],
                                           'name' => $videos[$key]['object']->name,
                                           //'watchers' => $data['watchers'],
                                           'watchers' => Statistics::getVideoWatchers($key, true),
                                           'position' => $videoLen ? $data['position'] / $videoLen * 100: 0,
                                           'positiontime' => $data['position'],
                                           'position_median_percent' => $videoLen ? $data['position_median'] / $videoLen * 100: 0,
                                           'position_median' => $data['position_median'],
                                           'rating' => $data['rating'],
                                           'rating_median' => $data['rating_median']
                                          );
            }


            $this->view->videosTable = $videosTable;
        }


        // ZPRACOVANI VYPISU PRO JEDNO VIDEO
        else {
            // Najdeme dvojice video / anketa
            // Vytvarime pole poli o strukture: [videoid][dotaznikid] = jmeno_embedu
            // vyhledavame vzdy jen jedno video
            $pairs = array();
            foreach($videos as $video){
                if(empty($video['object']->video) || empty($video['object']->video->config)){
                    continue;
                }
                $embeds = $video['object']->video->config->getEmbeds();
                foreach($embeds as $embed){
                    if(!empty($embed['questionnaireid'])){
                        if(empty($pairs[$video['id']])){
                            $pairs[$video['id']] = array();
                        }
                        elseif(empty($pairs[$video['id']][$embed['questionnaireid']])){
                            $pairs[$video['id']][$embed['questionnaireid']] = array();
                        }

                        $pairs[$video['id']][$embed['questionnaireid']][] = basename($embed['source']);
                    }
                }
            }


            // Doplnime hlavni dotaz
            $sel->joinLeft(array('ur' => 'users_reps'), 'u.id = ur.user_id', array('rep' => 'repre_id'))
                ->where('c.id = ?', $videoId)
                ->where('lt.content_video_num = ?', $videoNum)
                ->order(array('ur.repre_id', 'u.surname', 'u.name','u.id'/*, 'a.content_id', 'a.question_id'*/))
                     ->group(array('content_id' => 'c.id', 'lt.content_video_num', 'u.id', 'u.name', 'u.surname', 'ur.repre_id', 'u.group', 'fv.session_id', 'fv.firstvisit',
                    'lt.position')) ;

            try{
                $rows = $db->fetchAll($sel);
            }
            catch(Exception $e){
                //echo '<br/>'.$e;
                throw $e;
            }

            // Najdeme seznam reprezentantuu pro snazsi praci
            $sel = new Zend_Db_Select($db);
            $sel->from(array('u' => 'users'), array('id', 'name', 'surname'))
                ->where('is_rep');
            $repres = $db->fetchAssoc($sel);

            Monitoringexports::video($rows, $repres);
        }
    }

    private function loadJqplot() {
        Ibulletin_Js::addJsFile('admin/graphing.js');
        Ibulletin_HtmlHead::addFile('../scripts/jqplot/jquery.jqplot.min.css');
        Ibulletin_Js::addJsFile('jqplot/jquery.jqplot.min.js');
        Ibulletin_Js::addJsFile('jqplot/excanvas.min.js');
        Ibulletin_Js::addJsFile('jqplot/jqplot.barRenderer.min.js');
        Ibulletin_Js::addJsFile('jqplot/jqplot.highlighter.min.js');
        Ibulletin_Js::addJsFile('jqplot/jqplot.categoryAxisRenderer.min.js');
        Ibulletin_Js::addJsFile('jqplot/jqplot.dateAxisRenderer.min.js');
        Ibulletin_Js::addJsFile('jqplot/jqplot.pointLabels.min.js');
        Ibulletin_Js::addJsFile('jqplot/jqplot.canvasTextRenderer.min.js');
        Ibulletin_Js::addJsFile('jqplot/jqplot.canvasAxisTickRenderer.min.js');
    }

    /**
     * Exportuje tabulku html monitoringu do xlsx, jestlize jsou k dispozici náhledu slidů v contentu ve složce slide_previews, umístí se do tabulky, tabulka využívá knihovnu PHPExcel
     * a její html reader proto se tabulka ukládá do tmp souboru.
     */
    public function xexportAction() {
        if ($this->getRequest()->isPost()) {
            $table = $this->getRequest()->getPost('xtable');
            require_once 'PHPExcel.php';

            //tabulku obalime a ulozime do filu
            $table = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $table . '</body></html>';
            $temp = tempnam(sys_get_temp_dir(), 'pex');
            file_put_contents($temp, $table);
            $reader = new PHPExcel_Reader_HTML();
            $excel = $reader->load($temp);
            unlink($temp);

            //hack, pri exportu ze souboru zustava prvni radek prazdny...? Odebereme ho...
            $excel->getActiveSheet()->removeRow(1);

            //prvni radek tucne
            $lastcol = $excel->getActiveSheet()->getColumnDimension($excel->getActiveSheet()->getHighestColumn())->getColumnIndex();
            $excel->getActiveSheet()->getStyle('A1:'.$lastcol.'1')->getFont()->setBold(true);
            $lastcol++;

            for ($i = 'A'; $i !== $lastcol; $i++) {
                $excel->getActiveSheet()->getColumnDimension($i)->setAutoSize(true);
            }

            //projdeme slozku slide_previews a vyzobeme obrazky
            if ($this->getRequest()->getPost('slide_previews')) {
                $path = $this->getRequest()->getPost('slide_previews');
                $m = array();
                $slidePreviews = array();
                if (is_readable($path)) {
                    $dir = dir($path);
                    while (false !== ($entry = $dir->read())) {
                        // Staci, aby slide zacinal cislem v jakekoli podobe
                        if (preg_match('/^([0-9]+).*\.(jpg|png)$/', $entry, $m)) {
                           $slidePreviews[intval($m[1])] = $path . '/' . $entry;
                        }
                    }
                    $dir->close();
                }

                if ($slidePreviews) {
                    $excel->getActiveSheet()->insertNewColumnBefore($lastcol);
                    $excel->getActiveSheet()->getColumnDimension($lastcol)->setWidth(15);

                    foreach ($excel->getActiveSheet()->getRowIterator() as $row) {
                        $slide = (int) $excel->getActiveSheet()->getCellByColumnAndRow(0, $row->getRowIndex())->getValue();
                        if (isset($slidePreviews[$slide])) {
                            $objDrawing = new PHPExcel_Worksheet_Drawing();
                            $objDrawing->setName('Slide preview');
                            $objDrawing->setDescription('Slide preview');
                            $objDrawing->setPath($slidePreviews[$slide]);
                            $objDrawing->setHeight(80);
                            $objDrawing->setCoordinates($lastcol . ($row->getRowIndex()));
                            $excel->getActiveSheet()->getRowDimension($row->getRowIndex())->setRowHeight(65);
                            $objDrawing->setWorksheet($excel->getActiveSheet());
                        }
                    }

                    $excel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
                }
            }

            //odstranime sloupce dle parametru data-after-remove
            $r = 0;
            $ar = $this->getRequest()->getPost('afterremove');
            if ($ar) {
                foreach ($ar as $a) {
                    $excel->getActiveSheet()->removeColumnByIndex(intval($a) - $r, 1);
                    $r++;
                }
            }
            $xwriter = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$this->getRequest()->getPost('xname').'.xlsx"');
            header('Cache-Control: max-age=0');

            $xwriter->save('php://output', sys_get_temp_dir());
        }
   }

   /**
    * Sestavi data-table-name pro tabulky monitoring (atribut se vyuziva napr. pro naze souboru exportu)
    * @param sting $name custom name
    * @param iso time $from
    * @param iso time $to
    * @param int $contentId
    * @return string
    */
   private function assembleStatTableName($name, $from, $to, $contentId=null) {

       $content_name = '';
        if ($contentId) {

            $c = Contents::get($contentId);

            if ($c) {
                $content_name = Utils::slugify($c['name']) . '_';
            }
        }
        return $name . '_' . $content_name . date('Ymd', strtotime($from)) . '-' . date('Ymd', strtotime($to));
    }

    /**
     * Vytvori kombinace predanych polozek
     * @param array $items
     * @param array $cmb predgenerovane polozky pri rekurzivnim volani
     * @return array
     */
    function combinations($items, $cmb = array()) {

        $c = count($items);

        $values = array();

        if (count($items) > 1) {

            for ($i = 0; $i < $c; $i++) {

                $first = array_shift($items);

                foreach ($items as $item) {
                    $values[] = array_merge($cmb, array($first, $item));
                }

                $values = array_merge($values, $this->combinations($items, array_merge($cmb, array($first))));
            }
        }

        //seradime podle poctu polozek v poli
        array_multisort(array_map('count', $values), SORT_ASC, $values);

        return $values;
    }

}
