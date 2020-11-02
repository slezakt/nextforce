<?php

/**
 *	Kontrolér pro zobrazování statistik.
 *
 *	@author Martin Krčmář.
 */
class Admin_StatsoldController extends Zend_Controller_Action
{
	const TEST_STATS = false;		// vypisovat odkazy na detaily

	/**
	 *	Konstanty pro jednotlivé exporty. Každé tlačítko pro export má jiný
	 *	název, právě podle těchto konstant. Potom se v exportAction podle toho
	 *	určuje co se bude exportovat.
	 */

	const SUBSCRIBERS = 'subscribers';
	const SUBSCRIBERSEMAIL = 'subscribersEmail';
	const SUBSCRIBERSNOEMAIL = 'subscribersNoEmail';
	const SUBSCRIBERSANONYMOUS = 'subscribersAnonymous';
	const GROUPS = 'groups';
	const REPS = 'reps';
	const READERS = 'readers';
	const BULLETIN_SUBSCRIBERS = 'bulletinsub';
	const BULLETIN_READERS = 'bulletinread';

	//9.10.08 R.V., 15.10.08
	const GROUPS_USERS = 'groupu';
	const UNSUBSCRIBED = 'unsubscribed';		
	const SELFREGISTERED = 'selfregistered';
	const BADADDRESS = 'badaddress';
	const VIDEOWATCHERS = 'videowatchers';

	/**
	 *	Některé údaje se budou exportovat do XLS jako odkazy na stránky
	 *	administrace, pokud FALSE, tak budou jako normální čísla, ne jako
	 *	odkazy.
	 */
	protected $hrefs = TRUE;

	/**
	 *	Zobrazí statistiky, které se netýkají konkrétního
	 *	vydání bulletinu, ale webu jako celku.
	 */
	public function indexAction()
	{
		Zend_Loader::loadClass('Statisticsold');

		$config = Zend_Registry::get('config');

		try
		{
			//$this->view->targetValue = Statisticsold::getTargetCount();	RV 26.8.08
			$this->view->targetValue = $config->stats->target;
			
			$this->view->subscribersEmail = Statisticsold::getSubscribers(true, true);
			$this->view->subscribersEmailExport = $this->getExportButton(self::SUBSCRIBERS);
			$this->view->subscribersEmailPercent =
				Statisticsold::getSubscribersPercent($config->stats->round, true);
				
			$this->view->subscribersNoEmail = Statisticsold::getSubscribers(true, false, true);
            $this->view->subscribersNoEmailExport = $this->getExportButton(self::SUBSCRIBERS);
            $this->view->subscribersNoEmailPercent =
                Statisticsold::getSubscribersPercent($config->stats->round, false, true);

            $this->view->subscribersAnonymous = Statisticsold::getSubscribers(true, false, false);
            $this->view->subscribersAnonymousExport = $this->getExportButton(self::SUBSCRIBERS);
            $this->view->subscribersAnonymousPercent =
                Statisticsold::getSubscribersPercent($config->stats->round, false, false);
			
			$this->view->unsubscribed = Statisticsold::getUnsubscribed(true);
			$this->view->unsubscribedExport = $this->getExportButton(self::UNSUBSCRIBED);
			$this->view->selfregistered = Statisticsold::getSelfRegistered(true);
			$this->view->selfregisteredExport = $this->getExportButton(self::SELFREGISTERED);
            $this->view->badaddress = Statisticsold::getBadAddress(true);
            $this->view->badaddressExport = $this->getExportButton(self::BADADDRESS);
			
			$this->view->groups = Statisticsold::getGroupsCounts();
			$this->view->groupusExport = $this->getExportButton(self::GROUPS);

			$this->view->reps = Statisticsold::getRepsCounts();
			$this->view->repsExport = $this->getExportButton(self::REPS);

			$this->view->readers = Statisticsold::getReaders(true);
			$this->view->readersPercent =
				Statisticsold::getReadersPercent($config->stats->round);
			$this->view->readersExport = $this->getExportButton(self::READERS);

			// pro grafy 5.12.08 RV
			$this->view->bulletinsStats = Statisticsold::getBulletinsStats($config->stats->round);
			$this->view->DayReaders = Statisticsold::getDayReaders();

			// response rate aktualniho bulletinu, plus prumer za posledni 3
			// vydani
			$resRates = Statisticsold::getResponseRatePlusAvg(
				$config->stats->last, 
				$config->stats->round,
				$config->stats->only_valid
			);
			if (!empty($resRates))
			{
				$this->view->responseRate = $resRates['response_rate'];
				$this->view->responseRateAvg = $resRates['avg_response_rate'];
			}
			
			// return rate
			$retRates = Statisticsold::getReturnRatePlusAvg(
				$config->stats->last,
				$config->stats->round,
				$config->stats->only_valid
			);
//			print_r($retRates); 

			if (!empty($retRates))
			{
				$this->view->returnRate = $retRates['return_rate'];
				$this->view->returnRateAvg = $retRates['avg_return_rate'];
			}

			// prumer prectenych clanku, plus prumer za posledni 3 vydani
			$this->view->articles =
				Statisticsold::getArticlesAvg($config->stats->round);
			$this->view->articlesAvg = Statisticsold::getLastArticlesAvg(
				$config->stats->last,
				$config->stats->round,
				$config->stats->only_valid
			);

			// rubriky
			$this->view->categories = 
				Statisticsold::getCategoriesStats($config->stats->round);
		}
		catch (StatisticsoldException $e)
		{
			$this->view->message = 'Během zpracování nastala chyba.';
			Phc_ErrorLog::error('StatsController, emailsAction', $e);
		}
	}

	/**
	 *	Exportování dat do XLS formátu. 
	 */
	public function exportAction()
	{
		Zend_Loader::loadClass('Statisticsold');
		Zend_Loader::loadClass('Ibulletin_XLSExport');
		Zend_Loader::loadClass('Ibulletin_XLSExporter');
        
		// Pokud uzivatel nema opravneni na export, presmerujeme pryc.
		if(!Ibulletin_AdminAuth::hasPermission('privilege_stats_can_export_xls')){
		    $this->_forward('index');
		    return;
		}
		
		$db = Zend_Registry::get('db');					// db handler
		$config = Zend_Registry::get('config');			// nacte se config
		$request = $this->getRequest();
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

		// co se bude exportovat, bude se muze prijit postem, ta verze, kdy
		// exportovaci tlacitka jsou buttony, nebo odkazem
		if ($request->__isSet(self::SUBSCRIBERS))
			$action = self::SUBSCRIBERS;
		if ($request->__isSet(self::SUBSCRIBERSEMAIL))
            $action = self::SUBSCRIBERSEMAIL;
        if ($request->__isSet(self::SUBSCRIBERSNOEMAIL))
            $action = self::SUBSCRIBERSNOEMAIL;
        if ($request->__isSet(self::SUBSCRIBERSANONYMOUS))
            $action = self::SUBSCRIBERSANONYMOUS;
		if ($request->__isSet(self::UNSUBSCRIBED))		//15.10.08 RV
			$action = self::UNSUBSCRIBED;
		if ($request->__isSet(self::BADADDRESS))
            $action = self::BADADDRESS;
        if ($request->__isSet(self::VIDEOWATCHERS))
            $action = self::VIDEOWATCHERS;
		if ($request->__isSet(self::SELFREGISTERED))		//15.10.08 RV
			$action = self::SELFREGISTERED;
		else if ($request->__isSet(self::REPS))
			$action = self::REPS;
		else if ($request->__isSet(self::GROUPS))
			$action = self::GROUPS;
		else if ($request->__isSet(self::GROUPS_USERS))
			$action = self::GROUPS_USERS;
		else if ($request->__isSet(self::READERS))
			$action = self::READERS;	
		else if ($request->__isSet('what'))
			$action = $request->getParam('what');
		else
			$action = 'unknown';

		// pokud se budou nektere udaje v exportovanych souborech reprezentovat
		// jako odkazy na hlavni stranku se statistikama
		if ($this->hrefs)
		{
			$url = new Zend_View_Helper_Url();
			$href = 'http://'.$_SERVER['SERVER_NAME'];
			$href .= $url->url(
				array(
					'controller' => 'statsold',
					'action' => 'index',
					'module' => 'admin'
				),
				null,
				TRUE
			);
		}
		
		try
		{
			switch ($action)
			{
				case self::SUBSCRIBERS :
					$subscribers = Statisticsold::getSubscribers();

					$this->ExportXLS($subscribers, 'subscribers');
/**
					
					$oUsers = $subscribers[1];

					$xls = Ibulletin_XLSExport::createXLSExporter(self::SUBSCRIBERS.'.xls');

					$xls->begin();
					$xls->setColumn(0, 0, 10);
					$xls->setColumn(1, 2, 20);
					$xls->setColumn(0, 3, 40);
					$xls->setColumn(0, 4, 15);
					$xls->setColumn(0, 5, 30);
					$i = 0;
					foreach ($oUsers as $oUser){
						$xls->writeHeader(0, $i, key($oUsers));
						$i++;
					}

					$xlsRow = 1;
					foreach ($subscribers as $subscriber) {
						$i = 0;
						foreach ($oUsers as $oUser){
							$xls->writeText($xlsRow, $i, $subscriber->id);
							$i++;
						}
						$xlsRow++;
					}

					$xls->writeHeader(0, 0, 'Id');
					$xls->writeHeader(0, 1, 'Příjmení');
					$xls->writeHeader(0, 2, 'Jméno');
					$xls->writeHeader(0, 3, 'Email');
					$xls->writeHeader(0, 4, 'Skupina');
					$xls->writeHeader(0, 5, 'Přidáno');
					$xls->writeHeader(0, 6, 'Samoregistrace');

					$xlsRow = 1;
					foreach ($subscribers as $subscriber)
					{
						$xls->writeText($xlsRow, 0, $subscriber->id);
						$xls->writeText($xlsRow, 1, $subscriber->surname);
						$xls->writeText($xlsRow, 2, $subscriber->name);
						$xls->writeText($xlsRow, 3, $subscriber->email);
						$xls->writeText($xlsRow, 4, $subscriber->group);
						$xls->writeText($xlsRow, 5, $subscriber->added);
						$xls->writeText($xlsRow, 6, $subscriber->selfregistered);
						$xlsRow++;
					}


					$xls->stop();
					ob_clean();
					Ibulletin_XLSExport::createXLSHeader(self::SUBSCRIBERS.'.xls');
					$xls->printResult();
	*/			
					exit();
				case self::SUBSCRIBERSEMAIL :
                    $subscribers = Statisticsold::getSubscribers(false, true);
                    exit;
                case self::SUBSCRIBERSNOEMAIL :
                    $subscribers = Statisticsold::getSubscribers(false, false, true);
                    $this->ExportXLS($subscribers, 'subscribers');
                    exit;
                case self::SUBSCRIBERSANONYMOUS :
                    $subscribers = Statisticsold::getSubscribers(false, false, false);
                    $this->ExportXLS($subscribers, 'subscribers');
                    exit;
				case self::BULLETIN_SUBSCRIBERS :
					// ziska se ID bulletinu
					if (!$request->__isSet('bulletinid'))
						break;
					
					$bulletinId = $request->getParam('bulletinid');
					$subscribers = Statisticsold::getSubscribersForBulletin($bulletinId);
					$this->ExportXLS($subscribers, 'bulletin_subscribers');
					exit();

				//15.10.08	RV
				case self::UNSUBSCRIBED :
					$unsubscribed = Statisticsold::getUnsubscribed();
					$this->ExportXLS($unsubscribed, 'unsubscribed');
					exit();
					
				case self::BADADDRESS :
                    $badaddress = Statisticsold::getBadAddress();
                    $this->ExportXLS($badaddress, 'badaddress');
                    exit();
                    
                case self::VIDEOWATCHERS :
                    $videoWatchers = Statisticsold::getVideoWatchers($request->getParam('videoId'));
                    $this->ExportXLS($videoWatchers, 'videoWatchers');
                    exit();
				
				case self::SELFREGISTERED :
					$selfregistered = Statisticsold::getSelfregistered();
					$this->ExportXLS($selfregistered, 'selfregistered');
					exit();

				case self::GROUPS :
					if (!$request->__isSet('group'))
//						break;
						$Group = 'NULL';
					else
						$Group = $request->getParam('group');
	
//					echo $Group;
					$groups = Statisticsold::getGroupUsers($Group);
//					$groups = Statisticsold::getGroupsCounts();
					$this->ExportXLS($groups, 'groups');
					exit();

				case self::GROUPS_USERS :
					// ziska se group
					if (!$request->__isSet('group'))
						break;
					else
						$Group = $request->getParam('group');

					echo $Group;
//					echo $group;
//					echox();
					$users = Statisticsold::getGroupUsers($Group);
					$this->ExportXLS($users, 'users');
					exit();

				case self::REPS :
					if (!$request->__isSet('rep'))
						$Rep = 0;
//						break;
					else
						$Rep = $request->getParam('rep');
					$reps = Statisticsold::getRepUsers($Rep);
					$this->ExportXLS($reps, 'reps');
					exit();

				case self::READERS :
					$readers = Statisticsold::getReaders();
					$this->ExportXLS($readers, 'readers');
					exit();

				case self::BULLETIN_READERS :
					// ziska se ID bulletinu
					if (!$request->__isSet('bulletinid'))
						break;
					
					$bulletinId = $request->getParam('bulletinid');

					$readers = Statisticsold::getReadersForBulletin($bulletinId);
					$this->ExportXLS($readers, 'bulletin_readers');
					exit();

				default :
					break;
			}
		}
		catch (StatisticsException $e)
		{
			$this->view->message = 'Při exportu nastala chyba.';
			Phc_ErrorLog::error('StatsController', $e);
			break;
		}

		$this->generalAction();
		$this->getHelper('viewRenderer')->renderScript('statsold/index.phtml');
	}

	/**
	 *	Zobrazuje statistiky pro jednotlivá vydání bulletinu.
	 */
	public function bulletinsAction()
	{
		Zend_Loader::loadClass('Statisticsold');

		$config = Zend_Registry::get('config');

		try
		{
			// TODO strankovani??
			$this->view->bulletinsStats =
				Statisticsold::getBulletinsStats($config->stats->round);

			$request = $this->getRequest();
			if ($request->__isSet('what')) $action = $request->getParam('what');
			else $action = "";
			switch($action){
				case self::BULLETIN_SUBSCRIBERS:
					$this->view->bulletinsStatsDetailSubs = "gaga";
					break;
				default;
			}
		}
		catch (StatisticsException $e)
		{
			$this->view->message = 'Během zpracování nastala chyba.';
			Phc_ErrorLog::error('StatsController, emailsAction', $e);
		}
	}
    
	
	/**
     *  Vypis statistik videii pro jednotlive uzivatele 
     */
    public function videoAction()
    {
        $db = Zend_Registry::get('db');
        $req = $this->getRequest();
        
        // Nejdrive vytahneme vsechna videa, abychom mohli najit, ktere ankety k nim patri
        $videos = Contents::getList('Ibulletin_Content_Video');
        
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
        $latestTime = new Zend_Db_Expr("(SELECT user_id, content_id, max(position) AS position FROM players_stats 
            --WHERE action='latesttime' 
            GROUP BY content_id, user_id)");
        
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
        $sel->from(array('u' => 'users'), array('id', 'name', 'surname', 'rep', 'group'))
            ->join(array('c' => 'content'), '1=1', array('content_id' => 'id'))
            ->joinLeft(array('fv' => $usersFirstVisits), 'fv.user_id = u.id', array('session_id', 'firstvisit'))
            ->joinLeft(array('cp' => 'content_pages'), 'cp.content_id = c.id OR '.$webcastsOn, array())
            //->joinLeft(array('wcp' => 'content_pages'), '1=1', array())
            ->joinLeft(array('pr' => $ratings), 
                //'(wcp.page_id = pr.page_id OR cp.page_id = pr.page_id) AND u.id = pr.user_id', 
                '(cp.page_id = pr.page_id) AND u.id = pr.user_id',
                new Zend_Db_Expr('avg(pr.rating) AS rating'))
            ->joinLeft(array('lt' => $latestTime), 'lt.user_id = u.id AND lt.content_id = c.id', 'position')
            
            ->where('NOT test AND target AND NOT client')
            ->where("c.class_name = 'Ibulletin_Content_Video'")
            ;
        
        
        
        
        
        
        
        
        
        
        
        $videoId = $req->getParam('videoId');
        
        if(!$videoId){
            
            // Klonujeme select a doplnime potrebna propojeni
            $newSel = clone $sel;
            $newSel
                ->where('lt.position is not null OR pr.rating is not null')
                ->group(array('c.id','u.id', 'name', 'surname', 'rep', 'group', 'fv.session_id', 'fv.firstvisit', 
                    'lt.position'))
                ;
            
            // Provedeme agregovani nad predchozim selectem
            $videoSelAgreg = new Zend_Db_Select($db);
            $videoSelAgreg->from(new Zend_Db_Expr(('('.$newSel.')')), 
                    new Zend_Db_Expr('content_id, count(distinct(id)) as watchers, avg(rating) AS rating, 
                        avg(position) as position'))
                ->group('content_id')
            ;
            //echo $videoSelAgreg;
            //exit;
            
            $videoData = $db->fetchAll($videoSelAgreg);
            
            $videosTable = array();
            foreach($videoData as $data){
                $key = $data['content_id'];
                //print_r($videos[$key]['object']->video);
                $videos[$key]['object']->video->loadFlvInfo();
                $videoLen = $videos[$key]['object']->video->duration;
                //echo '<br/>durat:'.$videoLen;
                $videosTable[$key] = array('content_id' => $key,
                                           'name' => $videos[$key]['object']->name,
                                           //'watchers' => $data['watchers'],
                                           'watchers' => Statisticsold::getVideoWatchers($key, true),
                                           'position' => $videoLen ? $data['position'] / $videoLen * 100: 0,
                                           'positiontime' => $data['position'],
                                           'rating' => $data['rating']
                                          );
            }
            
            
            $this->view->videosTable = $videosTable;
        }
        
        
        // ZPRACOVANI VYPISU PRO JEDNO VIDEO
        else{
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
            $sel
                ->joinLeft(array('a' => 'answers'), 'u.id = a.user_id', '*')
                ->where('c.id = ?', $videoId)
                ->order(array('rep','surname', 'name','u.id', 'a.content_id', 'a.question_id'))
                
                ->group(array('content_id' => 'c.id', 'u.id', 'name', 'surname', 'rep', 'group', 'fv.session_id', 'fv.firstvisit', 
                    'lt.position', 'a.content_id', 'a.question_id', 'a.user_id', 'a.type', 'a.answer',
                    'a.text'))
                ;
            
            // Pridame podminku pro napojeni kazdeho dotazniku
            if(isset($pairs[$videoId])){
                $answersWhere = array('a.content_id IS NULL');
                foreach($pairs[$videoId] as $questionnaire => $name){
                    $answersWhere[] = 'a.content_id='.$questionnaire;
                }
                $sel->where(join(' OR ', $answersWhere));
            }
            if(isset($videoPages[$videoId])){
                foreach($videoPages[$videoId] as $page_id){
                    $sel->orWhere('wcp.page_id = ?', $page_id);
                }
            }
                
            //echo $sel;
            //exit;
            
            try{
                $rows = $db->fetchAll($sel);
            }
            catch(Exception $e){
                //echo '<br/>'.$e;
                throw $e;
            }
            
            /*
            echo '<table class="std">';
            echo '<tr><th>'.join('</th><th>', array_keys($rows[0])).'</th></tr>';
            foreach($rows as $row){
                echo '<tr><td>'.join('</td><td>', $row).'</td></tr>';
            }
            echo '</table>';
            return;
            //*/
            
            
            // Najdeme seznam reprezentantuu pro snazsi praci
            $sel = new Zend_Db_Select($db);
            $sel->from(array('u' => 'users'), array('id', 'name', 'surname'))
                ->where('is_rep')
                ;
            $repres = $db->fetchAssoc($sel);
            
            $this->createVideoStatXls($rows, $repres);
        }
    }
    
    /** 
     * Vytvori XLS soubor se statistikami videa.
     * 
     * @param array     Pole dat z DB
     */
    function createVideoStatXls($data, $repres){
        $fileName = 'video_stats.xls';
        
        $config = Zend_Registry::get('config');
        
        $oRow = $data[0];
    
        $xls = Ibulletin_XLSExport::createXLSExporter($fileName.'.xls');
    
        $xls->begin(null);
        
        // Nejprve najdeme nejdesi odpovedi na otazky checkbox
        $answerLengths = array();
        foreach ($data as $rec) {
            if(!is_numeric($rec['question_id'])){
                continue;
            }
            if($rec['type'] == 'c'){
                $answerLengths[$rec['question_id']] = max(strlen(decbin($rec['answer'])), $answerLengths[$rec['question_id']]);
            }
            else{
                $answerLengths[$rec['question_id']] = 1;
            }
        }
        $answersStartPos = 8;
        $answersStarts = array(); // Pro kazdou odpoved evidujeme posledni sloupec
        $pos = $answersStartPos;
        $lastLen = 0;
        foreach($answerLengths as $key => $len){
            $pos += $lastLen; 
            $answersStarts[$key] = $pos;
            $lastLen = $len;
        }
        //print_r($answersStarts);
    
        // Zapiseme radky tak, ze sledujeme a seskupujeme spravne radky, ktere souviseji
        $questionCount = 0;
        $questionCountLocal = 0;
        $xlsRow = 0;
        $lastUserId = null;
        $lastRep = null;
        foreach ($data as $rec) {
            // Pokud jde o dalsiho repa, pridame list repa
            if($lastRep !== (int)$rec['rep']){
                $lastRep = (int)$rec['rep'];
                if(isset($repres[$rec['rep']])){
                    $rep = $repres[$rec['rep']];
                    $name = $rep['name'].' '.$rep['surname'];
                }
                else{
                    $name = 'bez repa';
                }
                // Jmeno sheetu neumi byt utf-8
                $name = iconv('UTF-8', 'windows-1250', $name);
                
                $xls->addSheet($name);
                $xlsRow = 0;
            }
            
            
            // Pokud je to dalsi uzivatel, posuneme se o radek v XLS
            if($lastUserId != $rec['id']){
                $xlsRow++;
                $questionCountLocal = 0;
                $lastUserId = $rec['id'];
                $i = 0;
                
                $firstVisit = new Zend_Date($rec['firstvisit'], Zend_Date::ISO_8601);
                $firstVisit = $rec['firstvisit'] ? $firstVisit->toString($config->general->dateformat->long) : '';
                
                // Zapis dat
                $xls->writeText($xlsRow, $i++, $rec['id']);
                $xls->writeText($xlsRow, $i++, $rec['name']);
                $xls->writeText($xlsRow, $i++, $rec['surname']);
                $xls->writeText($xlsRow, $i++, $rec['group']);
                $xls->writeText($xlsRow, $i++, empty($rec['firstvisit']) ? 'ne' : 'ano');
                $xls->writeText($xlsRow, $i++, $firstVisit);
                $xls->writeText($xlsRow, $i++, sprintf('%d:%02d', round($rec['position']/1000/60, 0), round($rec['position']/1000 % 60, 0))); // Kdzbz to nebylo jasne, tak tohle dela cas
                //$xls->writeText($xlsRow, $i++, $rec['position']);
                $xls->writeText($xlsRow, $i++, $rec['rating'] ? round($rec['rating'], 2) : '');
            }

            // Odpoved na otazku
            if($rec['question_id']){
                $questionCountLocal++;
                $questionCount = max($questionCountLocal, $questionCount);
                
                $i = $answersStarts[$rec['question_id']];
                
                // Odpoved checkbox zapisujeme do vice sloupcu
                if($rec['type'] == 'c'){
                    $answers = str_split(strrev(decbin($rec['answer'])));
                    foreach($answers as $k => $answer){
                        !$answer ? $answer = '' : null;
                        $xls->writeText($xlsRow, $i++, $answer);
                    }
                }
                // Ostatni typy odpovedi
                else{
                    $xls->writeText($xlsRow, $i++, $rec['answer']);
                }
            }
        }
        
        
        // Pro kazdy list zapiseme hlavicku
        $sheets = $xls->getSheets();
        foreach($sheets as $name => $sheet){
            $xls->setActualSheet($name);
            
            $xls->setColumn(0, 0, 4);
            $xls->setColumn(1, 1, 12);
            $xls->setColumn(2, 2, 12);
            $xls->setColumn(3, 3, 17);
            $xls->setColumn(4, 4, 14);
            $xls->setColumn(5, 5, 20);
            $xls->setColumn(6, 6, 18);
            $xls->setColumn(7, 7, 10);
            // Nastavime sirky sloupcu pro odpovedi checkbox
            foreach($answerLengths as $answer => $len){
                if($len>1){
                    $xls->setColumn($answersStarts[$answer], $answersStarts[$answer]+$len-1, 2);
                }
            }
            
            
            $i = 0;
            $xls->writeHeader(0, $i++, 'Id');
            $xls->writeHeader(0, $i++, 'jméno');
            $xls->writeHeader(0, $i++, 'příjmení');
            $xls->writeHeader(0, $i++, 'skupina');
            $xls->writeHeader(0, $i++, 'přišel na web');
            $xls->writeHeader(0, $i++, 'kdy');
            $xls->writeHeader(0, $i++, 'kolik zhlédl z videa');
            $xls->writeHeader(0, $i++, 'hodnocení');
            
            foreach($answersStarts as $answer => $start){
                $xls->writeHeader(0, $start, 'otázka '.($answer));
            }
        }
        
        
        $xls->stop();
        ob_clean();
        Ibulletin_XLSExport::createXLSHeader($fileName.'.xls');
        $xls->printResult();
        exit();
    }
	

	/**
	 *	Testovani grafu
	 */
	public function grafAction()
	{
		Zend_Loader::loadClass('Statisticsold');
		$config = Zend_Registry::get('config');

		try
		{
//			$this->view->graf1 = $this->Graf1();
			$this->view->bulletinsStats = Statisticsold::getBulletinsStats($config->stats->round);
			$this->view->reps = Statisticsold::getRepsCounts();
			$this->view->users = Statisticsold::getUsersStats();
			$this->view->DayReaders = Statisticsold::getDayReaders();
		}
		catch (StatisticsException $e)
		{
			$this->view->message = 'Během zpracování nastala chyba.';
			Phc_ErrorLog::error('StatsController, grafAction', $e);
		}
	}

	
	/**
	 *	Zobrazuje seznam uživatelů s jejich statistikama.
	 */
	public function usersAction()
	{
		Zend_Loader::loadClass('Statisticsold');

		try
		{
			$this->view->users = 
				Statisticsold::getUsersStats();
		}
		catch (StatisticsException $e)
		{
			$this->view->message = 'Během zpracování nastala chyba.';
			Phc_ErrorLog::error('StatsController, usersAction', $e);
		}
	}

	/**
	 *	Zobrazuje statistiky emailů pro konkrétní bulletin.
	 */
	public function emailsAction()
	{
		Zend_Loader::loadClass('Statisticsold');
		Zend_Loader::loadClass('Ibulletin_ShowContent');
		
		$config = Zend_Registry::get('config');

		$db = Zend_Registry::get('db');					// db handler
		$config = Zend_Registry::get('config');			// nacte se config
		$request = $this->getRequest();
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

		if (!$request->__isSet('bulletinid'))
			$redirector->gotoAndExit('index', 'statsold', 'admin');

		// id bulletinu, jehoz emaily a jejich statistiky budeme zobrazovat
		$bulletinId = $request->getParam('bulletinid');

		// do view se ulozi nazev bulletinu
		$bulletins = new Bulletins;
		$this->view->bulletinName = $bulletins->getBulletinName($bulletinId);

		try
		{
			$this->view->emails = Statisticsold::getEmailsStats(
				$bulletinId, 
				$config->stats->round
			);
		}
		catch (StatisticsException $e)
		{
			$this->view->message = 'Během zpracování nastala chyba.';
			Phc_ErrorLog::error('StatsController, emailsAction', $e);
		}
	}

	/**
	 *	Vytvoří formulář s tlačítkem pro export do XLS.
	 *
	 *	@param Akce. Bude to pak název tlačítka.
	 */
	public function getExportButton($action)
	{
		/*
		$this->loadLibs();
		$url = new Zend_View_Helper_Url();
		$form = new Zend_Form();
		$form->setMethod('POST');
		$form->setAction($url->url(array(
			'controller' => 'statsold',
			'action' => 'export'
		)).'/');
		
		$export = new Zend_Form_Element_Submit($action);
		$export->removeDecorator('DtDdWrapper');
		$export->setLabel('export do XLS');

		$form->addElements(array(
			$export,
			$pokus
		));

		$form->removeDecorator('HtmlTag');
		*/

		// nebo jenom jako odkaz
		Zend_Loader::loadClass('Zend_View_Helper_Url');
		$url = new Zend_View_Helper_Url();
		return '<a href="'.$url->url(array('controller' => 'statsold',
			'action' => 'export', 'what' => $action)).'"><img
			src="pub/img/admin/icon-xls.png" style="border: none"/></a>';

		//return $form;
	}

	function loadLibs()
	{
        Zend_Loader::loadClass('Zend_Form');
		Zend_Loader::loadClass('Zend_Form_Element_Submit');
        Zend_Loader::loadClass('Zend_Form_Element_Text');
        Zend_Loader::loadClass('Zend_Form_Element_Textarea');
        Zend_Loader::loadClass('Zend_Form_Element_Checkbox');
		Zend_Loader::loadClass('Zend_Form_Element_Radio');
		Zend_Loader::loadClass('Zend_Form_Element_Hidden');
		Zend_Loader::loadClass('Zend_Form_SubForm');
		Zend_Loader::loadClass('Zend_Form_Element_Select');
        
		Zend_Loader::loadClass('Zend_Validate_NotEmpty');
		Zend_Loader::loadClass('Zend_Validate_Date');
		Zend_Loader::loadClass('Zend_Validate_Digits');
		Zend_Loader::loadClass('Zend_Validate_GreaterThan');
        
		Zend_Loader::loadClass('Zend_View_Helper_Url');
	}



	/**
	 *	Udela obrazek
	 *
	 *	@param oTable.
	 */
	function Graf1() {

			require_once("Phc/Legacy.php");
			require_once("phpchartdir.php");

			$tcontroller = 'statsold';
			$action = 'graf';
//			echo "aaaaaaa";
//			exhox();
			//$this->reps = Statisticsold::getRepsCounts();

			#The data for the bar chart
			$data = array(85, 156, 179.5, 211, 123);

			#The labels for the bar chart
			$labels = array("Mon", "Tue", "Wed", "Thu", "Fri");

			#Create a XYChart object of size 250 x 250 pixels
			$c = new XYChart(250, 250);

			#Set the plotarea at (30, 20) and of size 200 x 200 pixels
			$c->setPlotArea(30, 20, 200, 200);

			#Add a bar chart layer using the given data
			$c->addBarLayer($data);

			#Set the x axis labels using the given labels
			$c->xAxis->setLabels($labels);

			#output the chart
//			header("Content-type: image/png");
			//header("Content-type: text");
//			print($c->makeChart2(PNG));
			return $c->makeChart2(PNG);
	}


	/**
	 *	Vytvoří XLS soubor podle vysledku dotazu.
	 *
	 *	@param oTable.
	 */
	function ExportXLS($oTable, $fileName = 'subscribers')	{
					$oRow = $oTable[0];

//					foreach ($oRow as $oRo){		dela neplechu, urizne zacatek a posledni je prazdny
//					while ($oRo = key($oRow)){
//						$cString = "\$record->".key($oRow);
//						print $cString;
//						print eval("\$cString;");
//						print key($oRow);
//						echo "<br>";
//						next($oRow);
//					}
//					print_r(aray_keys($subscribers));

					$xls = Ibulletin_XLSExport::createXLSExporter($fileName.'.xls');

					$xls->begin();
					$xls->setColumn(0, 0, 10);
					$xls->setColumn(1, 2, 20);
					$xls->setColumn(0, 3, 40);
					$xls->setColumn(0, 4, 15);
					$xls->setColumn(0, 5, 30);
					$i = 0;
					while ($oRo = key($oRow)){
						$xls->writeHeader(0, $i, key($oRow));
						next($oRow);
						$i++;
					}

					$xlsRow = 1;
					foreach ($oTable as $record) {
						$i = 0;
//						foreach ($oRow as $oRo) {
						reset($oRow);
						while ($oRo = key($oRow)){
							$cString = "\$record->".key($oRow);
							eval("\$cString = \"$cString\";");
							$xls->writeText($xlsRow, $i, $cString);
							next($oRow);
							$i++;
						}
						$xlsRow++;
					}

					$xls->stop();
					ob_clean();
					Ibulletin_XLSExport::createXLSHeader($fileName.'.xls');
					$xls->printResult();
	}
}
?>
