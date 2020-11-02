<?php
/**
 * Stranka s loginem.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class IndexController extends Zend_Controller_Action
{
    /**
     * Stara se o to, aby nemohl byt pouzit nepovoleny zpusob registrace.
     * Pokud se uzivatel pokusi pristoupit na nepovolenou vstupni stranku, je
     * presmerovan na defaultni z configu.
     */
    public function preDispatch()
    {
        $action = strtolower($this->_getParam('action'));
        $config = Zend_Registry::get('config');

        // Pro empty a wave musime provest akci i kdyz je uzivatel jiz autentizovan
        if($action != 'empty' && $action != 'wave'){
            // Pokud je uzivatel jiz autentizovan, presmerujeme do autentizovane casti
            $this->_checkLoggedIn();
        }

        // Zkontrolujeme, jestli vubec existuje nejaky bulletin, do ktereho
        // lze uzivatele prihlasit, pokud ne, zobrazime pouze info
        $bulletins = new Bulletins();
        if(!$bulletins->existsAnyValidBulletin() && $action != 'empty'){
            // Presmerujeme na novou akci
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('empty', 'index');

            return;
        }

        $forbidden_actions = explode(',',$config->general->forbidden_came_in_pages);
        foreach($forbidden_actions as $key => $val){
            $forbidden_actions[$key] = strtolower(trim($val));
        }

        if($action != $config->general->come_in_page && in_array($action, $forbidden_actions)){
            // Tato akce je nepovolena, presmerujeme na defaultni controller
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit(null, null);
        }
    }


    public function testerAction() {
        $this->authcodeAction();
    }

    public function emptyAction()
    {
        $texts = Ibulletin_Texts::getSet('index.preDispatch');

        $this->view->message = $texts->noValidBulletinFound;

        $this->getHelper('viewRenderer')->renderScript('info.phtml');
        return;
    }

    /**
     * Prazdna stranka, pres kterou se nelze prihlasit do webu.
     */
    public function emptypageAction()
    {
        return;
    }


    /**
     * Zde se vybira co se stane, kdyz novy uzivatel prijde na web bez registrace.
     * Je nutne zde uvest i presmerovani na akce v RegisterController.php
     */
    public function indexAction()
    {
         $req = $this->getRequest();
         $config = Zend_Registry::get('config');

         $action = $config->general->come_in_page;

         // Podle nastaveni v configu spustime odpovidajici akci, nebo presmerujeme na registraci
         if($action == 'confirmation'){
             $this->_forward($action);
             return;
         }
         elseif($action == 'login'){
             $this->_forward($action);
             return;
         }
         elseif($action == 'authcode'){
             $this->_forward($action);
             return;
         }
         elseif($action == 'emaillogin'){
             // emaillogin je stejny jako registrace emailem, jen s jinym nastavenim
             $this->_forward('emaillogin', 'register');
             return;
         }
         elseif($action == 'emailregister'){
             //$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
             //$redirector->gotoAndExit(null , 'register');
             $this->_forward('emailregister', 'register');
             return;
         }
        elseif($action == 'register'){
             $this->_forward('register', 'register');
             return;
         }
        elseif($action == 'wave'){
             $this->_forward('wave');
             return;
         }
         elseif($action == 'free'){
             $this->_forward('free');
             return;
         }
         elseif($action == 'lightboxconfirm'){
             $this->_forward('lightboxconfirm');
             return;
         }
         elseif($action == 'emptypage'){
             $this->_forward('emptypage');
             return;
         }
		 elseif($action == 'gdpr'){
			 $this->_forward('gdpr','register');
			 return;
		 }


         // Pokud nebyla vybrana zadna akce, vyhodime vyjimku
         throw new Exception('Nepodarilo se vybrat login akci podle config.ini - zadana akce je "'.$action.'".');
    }


    /**
     * Prihlasi uzivatele na zaklade potvrzeni odbornosti
     *
     * Z formulare prijma bud promenne yes=yes, no=no, nebo prmennou answer=[yes|no]
     */
    public function confirmationAction()
    {
        $texts = Ibulletin_Texts::getSet();
        $req = $this->getRequest();

        $this->view->texts = $texts;
        // pokud jsme na infu, nezobrazuje se odkaz SPC v menu
        $this->view->hideSPC = true;

        //pokusime se prihlasit uzivatele
        $confirmation = null;
        $req->getParam('yes') ? $confirmation = $req->getParam('yes') : null;
        $req->getParam('no') ? $confirmation = $req->getParam('no') : null;
        $req->getParam('answer') ? $confirmation = $req->getParam('answer') : null;

        if(strtolower($confirmation) == 'yes'){
            Ibulletin_Auth::registerUser(array(), true);
        }
        elseif(strtolower($confirmation) == 'no'){
            $this->view->message = $texts->answeredNo;

            $this->getHelper('viewRenderer')->renderScript('info.phtml');
            return;
        }
    }
	/**
	 * presmerovani pomoci globalniho autentizacniho mechanizmu definovanem v configu pod klicem global_auth
	 */
	public function globalAuth() {

		$req = $this->getRequest();
		$config = Zend_Registry::get('config');

		$route_name = '';
		$route_params = array();
		$is_url_suffix = FALSE;
		$url = '';
		$matched = FALSE;

		// vytahneme s configu global_auth mapu
		$routes = !empty($config->general->global_auth) ? $config->general->global_auth : array();
		foreach ($routes as $route) {

			$match = explode(',',$route->match);
			$matched = TRUE;
			// cyklus jestli splnujeme podminku, ze parametre v klici 'match' matchuji request
			foreach ($match as $m) {
				$arr = explode(':', $m);
				if ($req->getParam($arr[0],NULL)  != $arr[1]) {
					$matched = FALSE;
					break;
				}
			}

			// byl nalezen match, vyzobeme url a breakneme spracovavani dalsich rout
			if ($matched) {
				// url ve tvaru url suffixu
				if (preg_match('/^\/?([%a-zA-Z0-9_-]*\/?)+$/',$route->url)) {
					$is_url_suffix = TRUE;
					$url = $route->url;
					break;
				}
				$params = '';
				list($route_name, $params) = explode('#',$route->url);
				$url = explode(',',$params);
				// cyklus poskladani url
				foreach ($url as $u) {
					$arr = explode(':', $u);
					$url_key = $arr[0];
					if (!isset($arr[1])) {
						continue;
					}
					$url_val = $arr[1];
					$route_params[$url_key] = $url_val;
				}
				break;
			}
		}

		// vykoname presmerovani na route nebo url a ukoncime beh
		if($matched){
			$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
			if ($is_url_suffix) {
				$redirector->gotoUrl($url);
			} else {
				// presmerujeme na routu
				$redirector->gotoRouteAndExit($route_params, $route_name);
			}
		}
	}

    /**
     * Prihlasi uzivatele na zaklade authcode.
     *
     */
    public function authcodeAction()
    {
        $texts = Ibulletin_Texts::getSet();
        $req = $this->getRequest();

        $this->view->texts = $texts;

        $this->globalAuth();

        //pokusime se prihlasit uzivatele
        if($req->getParam('authcode', false)){
            $authcode = $req->getParam('authcode');
            $prihlaseni = Ibulletin_Auth::authByAuthcode($authcode, false, true);
            if($prihlaseni === 'not_exists'){
                $this->view->loginError = $texts->wrongAuthcode;
            }
            elseif($prihlaseni === 'deleted'){
                $this->view->loginError = $texts->disabledAuthcode;
            } else {
                $inst = Ibulletin_Auth::getInstance();
                if ($userData = $inst->getActualUserData()) {
                    // ulozime extra data k uzivateli
                    $params = $this->getRequest()->getParams();
                    unset($params['module'],$params['controller'],$params['action'], $params['authcode']);
                    if (isset($params['submit'])) unset($params['submit']);
                    Users::updateUser($prihlaseni,array_merge($userData, $params), false);
                }
                // redirectujeme autentizovane uzivatele
                $inst->redirectToAuth();
            }
        }
    }


    /**
     * Prihlasi uzivatele zadanim loginu a hesla
     *
     */
    public function loginAction()
    {
        $texts = Ibulletin_Texts::getSet();
        $req = $this->getRequest();

        $this->view->texts = $texts;

        //pokusime se prihlasit uzivatele
        if($req->getMethod() == 'POST'){
            $login = $req->getParam('login');
            $password = $req->getParam('password');
            $prihlaseni = Ibulletin_Auth::authByLoginAndPassword($login, $password);
            if($prihlaseni === 0){
                $this->view->loginError = $texts->wrongCredentials;
            }
        }
    }


    /**
     * Prihlasi uzivatele a prime presmerovani do bulletinu
     */
    public function freeAction()
    {
        Ibulletin_Auth::registerUser(array(), true);
    }

    /**
     * Prihlasi uzivatele a primo presmeruje do bulletinu pokud prisel z nektere
     * domeny (prefixu URL) nastavene u nektere vlny pro tento zpusob pristupu. Nebo nastavi vlnu
     * pro odkaz z jakekoli domeny bez ohledu na referer, nebo pozaduje zaroven spravnou URL i referer.
     *
     * Jako vlna je vzdy vybrana nejnovejsi platna (pro daneho uzivatele - admini muzou vsude) vlna
     * se spravnym url prefixem nebo url_name vlny. Muze existovat vice vln pro shodne URL name a prefix,
     * mely by se vsak lisit vydanim, nebo zadanou platnosti.
     *
     * Prenasi do URL jako urlHash (anchor) i obsah parametru 'urlhash', protoze v Safari se hash (anchor) nepodrzi pri redirectu.
     * Prenos dalsich parametru v URL obvykle neni mozny, protoze vstup presmeruje na bulletin, kde route bulletin
     * neumoznuje pridani dalsich parametru na konec cesty, protoze by dochazelo ke kolizi s jinymi cestami.
     * Parametry se prenaseji jen pro specialni routovani pro uzivatele nastavene v configu waveParamsToSave a waveUserAuthParams.
     *
     * Pokud se povede prihlaseni je nastavena k teto session vlna pres kterou uzivatel prisel.
     */
    public function waveAction()
    {
        $config = Zend_Registry::get('config');
        $req = $this->getRequest();

        // Domeny s volnym pristupem
        $domains = $config->general->getCommaSeparated('come_in_referer_domains', array());

        // Ziskame referer
        isset($_SERVER['HTTP_REFERER']) ? $referer = $_SERVER['HTTP_REFERER'] : $referer = "";

        // Params to be passed to URL after redirect
        $params = $req->getParams();

        if($invitationId = Invitationwaves::isEnterAllowed($referer, $req->getParam('name', null))){
            $attribs = array();

            // Remove all parameters that was used for wave decisionning
            unset($params['name']);

            // ulozeni groupy z parametru linku
            if ($req->getParam('group')) {
                $attribs['group'] = $req->getParam('group', null);
                unset($params['group']);
            }

            // Najdeme zbyle parametry, ktere by se mely ukladat k uzivateli
            $keysToSave = $config->general->getCommaSeparated('waveParamsToSave');
            $authParams = $config->general->getCommaSeparated('waveUserAuthParams');
            $paramsRename = $config->general->waveParamsRename ? $config->general->waveParamsRename->toArray() : array();
            foreach($keysToSave as $key){
                $param = $req->getParam($key, null);
                if($param !== null){
                    unset($params[$key]);
                    // Najdeme prejmenovani atributu, pokud je potreba
                    if(array_key_exists($key, $paramsRename)){
                        $keyRename = $paramsRename[$key];
                    }
                    else{
                        $keyRename = $key;
                    }

                    $attribs[$keyRename] = $param;

                    // Hledani klice pro autentizaci uzivatele, pokud je povolena
                    if(!empty($authParams) && in_array($key, $authParams)){
                        $authParam = array('key' => $keyRename, 'value' => $param);
                    }
                }
            }

            // Pokud ma byt vzdy pred vstupem pres vlnu provedeno odhlaseni, odhlasime
            if($config->general->waveStartsByLogoff){
                Ibulletin_Auth::logoffUser(false);
            }

            // Nastavime invitation_id, musi byt az po logoff!!
            Ibulletin_Stats::getInstance()->setAttrib('invitation_id', $invitationId);

            // Pokud byl zadan autentizacni parametr, odhlasime pripadneho prihlaseneho uzivatele
            // a pokusime se najit existujiciho uzivatele k overeni
            if(!empty($authParam)){
                $auth = Ibulletin_Auth::getInstance();
                Ibulletin_Auth::logoffUser(false);
                $userData = $auth->authByAttrib($authParam['key'], $authParam['value'], false);
            }
            //dump($userData);
            //exit;

            // UrlHash jako url param (protoze retezec za hashem se nepredava na Server)
            $urlHash = $req->getParam('urlhash', '');
            unset($params['urlhash']);

            $userData = Ibulletin_Auth::getActualUserData();

            if(empty($userData)){
                $uid = null;
                if(empty($authParam)){
                    // Neovereneho uzivatele nejprve registrujeme
                    $uid = Ibulletin_Auth::registerUser($attribs, false,true, true, null, true);
                }
                else{
                    // Registrujeme s ohledem na parametr pro autentizaci
                    $uid = Ibulletin_Auth::registerUser($attribs, false, true, true, array($authParam['key']),true);
                }

                // Log-in user
                if($uid){
                    Ibulletin_Auth::setUser($uid);
                }
            }
            else{
                @$attribs = array_merge($userData, $attribs);
                Users::updateUser($attribs['id'], $attribs, false);
            }

            // Redirect
            unset($params['controller']);
            unset($params['action']);


            // v pripade nastaveneho linku u vlny presmerujeme na ten
            try {
                $wave = Invitationwaves::get($invitationId);
                if (!is_null($wave['link_id'])) {
                    $menu = new Ibulletin_Menu();
                    $redirect_params = $menu-> getLinkUrl($wave['link_id']);

                    $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

                    $redirector->gotoRouteAndExit(array_merge($redirect_params['params'],$params), $redirect_params['route']);
                }
            } catch (Exception $e) {
                Phc_ErrorLog::error('Index:Wave action', $e);
            }

            $auth = Ibulletin_Auth::getInstance();
            $auth->redirectToAuth($params, $urlHash);
        }

        // Registrace se nezdarila, pokud neni wave defaultni prihlasovaci stranka, presmerujeme
        // na defaultni rihlasovaci stranku, jinak se zobrazi view skript teto akce
        if($config->general->come_in_page != 'wave'){
            $this->_forward('index');
            return;
        }

        /*
        foreach($domains as $domain){
            if(preg_match("/^(.){2,8}:\/\/$domain/i", $referer)){
                // Nastaveni zvaci vlny, pokud je zadana v URL
                $invitationId = $req->getParam('wave', null);
                if($invitationId){
                    // Kontrola existence vlny
                    $wave = Invitationwaves::get($invitationId);
                    if(!empty($wave)){
                            Ibulletin_Stats::getInstance()->setAttrib('invitation_id', $invitationId);
                    }
                }
                Ibulletin_Auth::registerUser(array(), true);
                break;
            }
        }
        */

    }


    /**
     * Vpusti uzivatele na stranku, ale spusti pres ni lightbox s potvrzenim o odbornosti
     *
     *  Pozor, spousta veci se musi delat v Ibulletin_Auth::prepare()!!
     */
    public function lightboxconfirmAction()
    {
        $req = $this->getRequest();
        $confirm = $req->getParam('confirm', null);

        if(!$confirm){
            $auth = Ibulletin_Auth::getInstance();
            $auth->redirectToAuth(array('islightboxconfirm' => 1));
            //Ibulletin_Auth::redirectToActualBulletin(true, array('islightboxconfirm' => 1));
        }

        // Vypneme vsechno renderovani view skriptuu
        $this->getHelper('viewRenderer')->setNeverRender(true);

        if($confirm == 'yes'){
            // Registrujeme uzivatele
            Ibulletin_Auth::registerUser(array(), true);
        }
    }

    /**
     * Zkontroluje, jestli neni uzivatel prihlasen, pokud je a je zapnute
     * tvrde odhlasovani, presmeruje uzivatele na aktualni bulletin.
     */
    private function _checkLoggedIn()
    {
         $config = Zend_Registry::get('config');

        // Zjistime, jestli je nekdo prihlasen, a jestli je zapnute tvrde odhlasovani
        // pokud ano, presmerujeme na aktualni bulletin
        $user_data = Ibulletin_Auth::getActualUserData();
        if($user_data !== null && $config->general->hard_logoff){
            //$bulletins = new Bulletins();
            //$bulletins->redirectToActualBulletin(true);
            $auth = Ibulletin_Auth::getInstance();
            $auth->redirectToAuth();

        }
    }
}
