<?php
/**
 *
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 * @author Andrej Litvaj, <andrej.litvaj@pearshealthcyber.com>
 * 
 */
class RedirectController extends Zend_Controller_Action
{
	
	/**
	 * Zameni tagy v externim odkazu
	 * tagy z uzivatelovych dat typu %%user_attribut%%
	 * specialni tag %%euni#id%% nahrazuje za sifrovany odkaz na euni.cz
	 * 
	 * @param string $url
	 */
	private function parseTags($url) {

		$config = Zend_Registry::get('config');
		
		$user_data = Ibulletin_Auth::getActualUserData();

        //pro neprihlasene uzivatele vracime url, jestli ze url obsahuje tagy vyhodime 500
        if($user_data == null) {
            $reg = $config->mailer->tags->open
			. '.+' . $config->mailer->tags->close;
            if (preg_match('/' . $reg . '/i', $url)) {
                throw new Zend_Controller_Action_Exception('Cannot parse tags in url: '.$url.' for anonymous users', 500);
            } else {
                return $url;
            }   
        }
		
		// user tags
		foreach ($user_data as $k => $v) {
			$reg = $config->mailer->tags->open
			. $k . $config->mailer->tags->close;
			if (preg_match('/' . $reg . '/i', $url, $result)) {
				$url = preg_replace('/'.$reg.'/i', urlencode($v), $url);
			}	
		}
		
		// euni link tag
		$reg = $config->mailer->tags->open
		. 'euni' . $config->mailer->tags->separator
		. '(.*)' . $config->mailer->tags->close;
		if (preg_match('/' . $reg . '/i', $url, $result)) {
			$euni_url = self::getEuniLink(
					$result[1],
					$config->general->project_name,
					$user_data['email'],
					$user_data['name'],
					$user_data['surname']
			);
			$url = preg_replace('/'.$reg.'/i', $euni_url, $url);
		}		
		
		return $url;
	}
	
	/**
	 * Generuje URL pro přihlášení na portál EUNI
	 *
	 * Příklad
	 * <code>
	 * $url = self::getEuniLink("tema.php?id=4","project_name", "developers@euni.cz","Franta","Omacka");
	 * </code>
	 *
	 * @param string $url_suffix část cesty za cílovou adresou na euni
	 * @param string $bulletinName název bulletin logující se do statistik
	 * @param string $email email přihlašovaného uživatele
	 * @param string $fname jméno přihlašovaného uživatele
	 * @param string $sname přijmení přihlašovaného uživatele
	 */
	static private function getEuniLink($url_suffix, $bulletinName, $email, $fname=null, $sname=null ) {
		 
		$target = sprintf('www.euni.cz/%s', $url_suffix);
		 
		// zakoduji parametry do retezce
		$arg =  '"'. addcslashes($bulletinName,'"').'",'
		.'"'. addcslashes($email,'"').'",'
		.'"'. addcslashes($fname,'"').'",'
		.'"'. addcslashes($sname,'"').'"';
	
		// zasifruji		
		$cr = new Ibulletin_Crypt();
		$arg = $cr->encrypt($arg);
	
		// pripravim pro vlozeni do URL
		$arg = urlencode(base64_encode($arg));
	
		// vygenerovany cil
		$genTarget = '';
	
		// pridam k url
		if ( strpos($target,'?') )
			$genTarget = $target.'&'.'inbulletin='.$arg;
		else
			$genTarget = $target.'?'.'inbulletin='.$arg;
	
		return $genTarget;
	}
    
    /**
     * Zaridi predani resource do prohlizece uzivateli.
     * 
     * @param   int Resource ID
     */
    public function getResource($id){
        $config = Zend_Registry::get('config');
        $frontController = Zend_Controller_Front::getInstance();
        $response = $frontController->getResponse();
        
        // Nerenderovat zadny view skript
        $this->_helper->viewRenderer->setNoRender();
        
        $resource = Resources::get($id);
        
        // Pokud resource neexistuje, vratime link not found.
        if(empty($resource) || empty($resource['path'])){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoAndExit('', $config->general->link_not_found->controller);
        }

        //stazeni certifikatu uzivatele
        if ($resource['special_type'] == 'certificate') {
           $userData = Ibulletin_Auth::getActualUserData();
           $userCertPath = $resource['path'].DIRECTORY_SEPARATOR.$userData['id'].'.pdf';
            if (file_exists($userCertPath)) {
                $resource['path'] = $userCertPath;
            } else {
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                $redirector->gotoAndExit('', $config->general->link_not_found->controller);
            }
        }

        // Zapiseme do stats
        Ibulletin_Stats::getInstance()->saveResourceStats($id);
        
        // Vratime soubor
        $file = $resource['path'];
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $filename = Utils::slugify($resource['name']).'.'.$extension;
        
        // TODO Upravit inteligentneji vyber content-type?
        $response->setHeader('Content-Type', 'application/'.$extension, true);
        $response->setHeader('Content-Length', filesize($file), true);
        $response->setHeader('Content-Disposition', 'attachment; filename="'.$filename.'"', true);
        $response->sendResponse();

        // Konec session kvuli dalsim pozadavkum v teto session
       session_write_close();

        // Posilame data
        ob_end_clean();
        readfile($file);
        exit();

    }
	
	/**
	 * Vykona redirect pro dane id linku z parametru target.
     * Redirectuje jen linky s URL a resources.
	 */
    public function doAction()
    {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');

        $req = $this->getRequest();
        $link_id = $req->getParam('target');

        if (!$link_id) {
            throw new Zend_Controller_Action_Exception('Missing request parameter "target"', 404);
        }

        // fetch link from DB
        $q = sprintf('SELECT * FROM links WHERE id = %d', $link_id);
        $link = $db->fetchRow($q);
        
        if (empty($link)) {
            throw new Zend_Controller_Action_Exception('Link with id "'.$link_id.'" not found', 404);
        }

        // store page hit and give 404
        if ($link['deleted']) {
            // Uvedeme do statistik akci "redirected" a kam bylo presmerovano
            Ibulletin_Stats::getInstance()->setAttrib('action', 'redirected');
            Ibulletin_Stats::getInstance()->setAttrib('link_id', $link_id);
            // Zvysime pocitadlo pro tento link v tabulce links
            Ibulletin_Stats::increaseLinkUsed($link_id);

            throw new Zend_Controller_Action_Exception('Link with id "'.$link_id.'" is deleted', 404);
        }

        // Pro resource zpracujeme primo resource
        if(!empty($link['resource_id'])){
            $this->getResource($link['resource_id']);
            return;
        }
        
        // parse tags
        $url = $link['foreign_url'];
        $url = $this->parseTags($url);

        //prazdnou URL presmerujeme na link_not_found z configu
        if(empty($url)){
            throw new Zend_Controller_Action_Exception('Link with empty url for id "'.$link_id.'"', 404);
            //$redirector->gotoAndExit('', $config->general->link_not_found->controller);
        }

        // Ziskame vsechny parametry predane v url za cislem linku mimo 'target' a pridame je za URL
        $params = $req->getParams();
        unset($params['target']);
        unset($params['controller']); // Musime odstranit vse co je definovane v route v config.ini
        unset($params['action']);
        // Pokud budeme pridavat parametry za URL, odstranime pripadne koncove lomitko
        if(!empty($params)){
            $url = preg_replace('/\/$/', '', $url);
        }
        foreach($params as $key => $val){
            $url .= "/$key/$val";
        }

        // Uvedeme do statistik akci "redirected" a kam bylo presmerovano
        Ibulletin_Stats::getInstance()->setAttrib('action', 'redirected');
        Ibulletin_Stats::getInstance()->setAttrib('link_id', $link_id);
        // Zvysime pocitadlo pro tento link v tabulce links
        Ibulletin_Stats::increaseLinkUsed($link_id);

        // Nerenderovat zadny view skript
        $this->_helper->viewRenderer->setNoRender();
        
        //presmerujeme
        $redirector->gotoUrlAndExit($url);
    }
}
