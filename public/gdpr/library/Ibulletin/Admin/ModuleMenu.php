<?php

/**
 * Trida reprezentujici menu v modulu, ktere umoznuje navigaci mezi soucastmi modulu.
 * 
 * Menu lze pripravit pro cely modul predem bez nutnosti sloziteho sestavovani polozek v kazde 
 * Action (akci). Pro konstrukci menu mame idealne ve tride controlleru preddefinovane promenne
 * odpovidajici $forAll a $specific z teto tridy, ktere zadame do konstruktoru. Konstrukci tridy
 * je sikovne spustit v preDispatch() daneho controlleru a to timto zpusobem, kvuli dostupnosti
 * menu ve view i v controlleru:
 *      $this->moduleMenu = new Ibulletin_Admin_ModuleMenu($this->submenu_forAll, $this->submenu_specific);
 *      $this->view->moduleMenu = $this->moduleMenu;
 * 
 * Modul adminu je rozdelen na akce a nektere akce mohou mit jeste dalsi ruzne podsekce, ktere zde v menu
 * nazyvame location. V poli $forAll jsou polozky menu, ktere se maji zobrazovat ve vsech akcich 
 * ci lokacich controlleru. Na kazde strance se samozrejme vzdy automaticky vynecha odkaz 
 * odpovidajici nazvu akce, ktera byla vykonana nebo nastaveni aktualni location.
 * 
 * Kdykoli pri vykonavani controlleru je mozne pomoci metod addItem() a removeItem() pridat ci 
 * odebrat polozky ke zobrazeni v menu. Lze take nastavit, jestli maji byt tyto modifikace menu za
 * behu pouzity v pripade prechodu na jinou Action behem dispatch procesu (metodou _forward('akce') 
 * controlleru).
 * 
 * Pokud se akce (Action) deli na vice casti s ruznymi menu, je treba zajistit spravne nastaveni 
 * location behem akce v ruznych castech metodou setCurrentLocation(). Kdyz je nastaveno location,
 * action neni vubec pouzita.
 * 
 * Pro render moduleMenu se provede ve view skriptu:
 * <?=$this->render('modulemenu.phtml')?>
 * 
 * Format $forAll:
 * array(
 *      'index' => array('title' => 'Seznam obsahů', 'params' => array('action' => null), 'noreset' => true),
 *      'edit' => array('title' => 'Editace', 'params' => array('action' => 'edit', 'promenna_v_url' => 'hodnota')),
 *      'odkaz' => array('title' => 'Nějaký soubor', 'url' => 'pub/content/6/seznam.txt'),
 * );
 * 
 * Format $specific:
 * array(
 *      'location_nebo_akce' => array(
 *          'index' => array('title' => 'Seznam obsahů', 'params' => array('action' => null)),
 *          'edit' => array('title' => 'Editace', 'params' => array('action' => 'edit', 'promenna_v_url' => 'hodnota'))
 *      ),
 *      'jina_location_nebo_akce' => array(
 *          'index' => array('title' => 'Seznam obsahů', 'params' => array('action' => null)),
 *          'edit' => array('title' => 'Editace', 'params' => array('action' => 'edit', 'promenna_v_url' => 'hodnota'))
 *      ),
 * );
 * 
 * 
 * title   - Text polozky menu
 * params  - Pole parametru URL tak jak jej prijma Zend_View_Helper_Url - obvykle pouzijeme jen action 
 *           a pak dvojice nazev promene hodnota, ktere chceme prenest v url.
 * url     - URL na kterou ma odkaz odkazovat, pokud je zadana, params se nepouzije, muze byt relativni 
 *           i absolutni. Pouzivame jen na soubory, pripadne odkazy uplne ven ze systemu.
 * noreset - normalne je kazdy odkaz ocisten od ostatnich parametru predanych v URL pri nacteni 
 *           aktualni stranky, pokud chceme ponechat i parametry, ktere zustaly po nacteni aktualni 
 *           stranky a nejsou nastaveny v 'params', nastavime 'noreset' na true.
 * 
 * 
 * @author Bc. Petr Skoda
 */
class Ibulletin_Admin_ModuleMenu
{
    
    /**
     * Zadavame ve formatu:
     * array(
     *      'index' => array('title' => 'Seznam obsahů', 'params' => array('action' => null)),
     *      'edit' => array('title' => 'Editace', 'params' => array('action' => 'edit', 'promenna_v_url' => 'hodnota')),
     * );
     * 
     * @var array   Stale menu zobrazene vsude.
     */
    var $forAll = array();
    
    /**
     * Zadavame ve formatu:
     * array(
     *      'location_nebo_akce_nebo_skupina' => array(
     *          'index' => array('title' => 'Seznam obsahů', 'params' => array('action' => null)),
     *          'edit' => array('title' => 'Editace', 'params' => array('action' => 'edit', 'promenna_v_url' => 'hodnota'))
     *      ),
     * );
     * 
     * 'location_nebo_akce_nebo_skupina' je nazev location nebo akce nebo skupina, pro kterou dane polozky menu plati,
     * nikde jinde zobrazeny nebudou.   
	 * Pokud chceme vyuzivat plnohodnotne 2 urovnove menu musime pouzit skupiny!!
     *  
     * @var array   Zvlastni polozky pro ruzne casti modulu.
     */
    var $specific = array();
    
    /**
     * Muzeme nadefinovat skupiny pro ktere plati urcita specific menu.
     * 
     * Formát:
     * array(
     *      'nazev_skupiny' => array('rodic','akce1','akce3'),
     * );
     * 
     * !!! Nazev skupiny se musi jmenovat odlisne nez jine akce !!!
     * 
     * Aby skupiny spravne fungovaly musime nastavit rodice na prvni misto!!
     * 
     * @var array S vypisem skupin
     */
    var $groups = array();
    
    
    /**
     * @var string  Aktualni location pro kterou se ma menu zobrazit.
     */
    var $location = null;
    
    /**
     * @var array  Klice odkazu, ktere jsou odebrane ze zobrazovani pro konkretni akce.
     * Klicem prvni dimenze je $location nebo akce, pro kterou toto odebrani plati, v druhe
     * dimenzi jsou jako prvky klice polozek menu ke smazani.
     */
    var $removed = array();
    
    /**
     * @var array  Klice odkazu, ktere jsou odebrane ze zobrazovani pro vsechny akce.
     */
    var $removedAllActions = array();
    
    
    /**
     *  
     * @param array     Stale menu zobrazene vsude.
     * @param array     Zvlastni polozky pro ruzne casti modulu.
     */
    public function __construct($forAll, $specific, $grps = array())
    {
        $this->specific = $specific;
        $this->forAll = $forAll;
        $this->groups = $grps;
    }
    
    /**
     * Nastavi na ktere strance se nachazime, pokud nestaci identifikace podle Akce.
     * 
     * @param string $location Aktualni stranka nebo podstranka, pro 
     *               kterou se bude vykreslovat menu.
     */
    public function setCurrentLocation($location)
    {
        $this->location = $location;
    }
    
    /**
     * Prida do aktulane zobrazovaneho menu polozku.
     * 
     * Pokud se pouziva setCurrentLocation(), musi byt nastaveno pred pouzitim teto metody.
     * 
     * @param string $title      Nazev, ktery bude vypsan uzivateli jako odkaz.
     * @param array $urlParams   Parametry pro url helper.
     * @param string $url        URL, ktere bude pouzito misto skladani URL z $urlParams
     * @param bool $inherit      Ma se tato polozka zdedit i do dalsich akci vyvolanych touto? DEFAULT false
     * @param bool $forLocation  Pro jakou akci ci location tato volba plati. Hodi se v priapde,
     *                           ze jedna akce presmerovava na jinou a zpracovani konci jinde.
     */
    public function addItem($title, $urlParams, $url = null, $inherit = false, $forLocation = null)
    {
        // Vybereme, kam se ma odebrani zaradit
        if(!empty($forLocation)){
            $location = $forLocation;
        }
        elseif(!empty($this->location)){    
            $location = $this->location;
        }
        else{
             // Ziskame jmeno aktualni akce
            $frontController = Zend_Controller_Front::getInstance();
            $request = $frontController->getRequest(); 
            $location = $request->getActionName();
        }
        
        $data = array('title' => $title, 'params' => $urlParams, 'url' => $url);
        
        if($inherit){
            $this->forAll[$location] = $data;
        }
        else{
            $this->specific[$location][$location] = $data;
        }
    }
    
    /**
     * Zabrani zobrazeni polozky menu s danym klicem (bud $location nebo jmeno akce) v
     * menu na aktualne zpracovane strance.
     * 
     * Pokud se pouziva setCurrentLocation(), musi byt nastaveno pred pouzitim teto metody.
     * 
     * @param string $key        Klic (bud $location nebo jmeno akce) odkazu, ktery v aktualni
     *                           strance nema byt zobrazen.
     * @param bool $inherit      Ma se tento zakaz zdedit i do dalsich akci vyvolanych touto? DEFAULT false
     * @param bool $forLocation  Pro jakou akci ci location tato volba plati. Hodi se v priapde,
     *                           ze jedna akce presmerovava na jinou a zpracovani konci jinde.
     */
    public function removeItem($key, $inherit = false, $forLocation = null){
        // Vybereme, kam se ma odebrani zaradit
        if(!empty($forLocation)){
            $location = $forLocation;
        }
        elseif(!empty($this->location)){    
            $location = $this->location;
        }
        else{
             // Ziskame jmeno aktualni akce
            $frontController = Zend_Controller_Front::getInstance();
            $request = $frontController->getRequest(); 
            $location = $request->getActionName();
        }
        
        if($inherit){
            $this->removed[$location][] = $key;
        }
        else{
            $this->removedAllActions[] = $key;
        }
    }
    
    
    /**
     * Vrati pole s daty pro renderovani v aktualni strance (dle akce a podobne)
     * @param $mode - mod renderovani dat defaultne vsechno jinak forAll (pouze forall), specific(specificke polozky pouze)
     */
    public function getRenderData($mode = "all")
    {
        // Ziskame jmeno posledni zpracovane a zobrazovane akce, modulu a cesty.
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest(); 
        $action = $request->getActionName();
        $controller = $request->getControllerName();
        $module = $request->getModuleName();
        
        $relevant = array();
        
        // Pripojime relevantni data pro konkretni cast k
        // datum pouzivanym pro vsechny casti
        if($mode == "all" || $mode == "forAll") {
            $relevant = $this->forAll;
        }
        
       // print_r($this->groups);
        if($mode == "all" || $mode == "specific" ) {    	
        	
            if(isset($this->specific[$this->location])){
                $relevant = array_merge($relevant, $this->specific[$this->location]);
            }
            elseif(isset($this->specific[$action])){
                $relevant = array_merge($relevant, $this->specific[$action]);
            } 
            
    		foreach($this->groups as $key => $group) {
    		    // odstranime prvni prvek z group, protoze je to parent
                //$groupParent = $group[0];
                unset($group[0]);
    			if(isset($this->specific[$key])){
    				if(in_array($action,$group) || in_array($this->location, $group)) {
    					$relevant = array_merge($relevant, $this->specific[$key]);
    				}
    			}			
            }        
        }
        
        // //Odstranime zaznamy, ktere vedou na aktualni location nebo akci
        // NASTAVIME jim paramter aktivni
        if(!empty($this->location)){
            if(isset($relevant[$this->location])){
                //unset($relevant[$this->location]);
                $relevant[$this->location]['active'] = true;
            }
            elseif(isset($relevant[$action])) {
                $relevant[$action]['active'] = true;
            }
        }elseif(isset($relevant[$action])){
            //unset($relevant[$action]);
            $relevant[$action]['active'] = true;
        } else { // Hledame jestli nejaka skupina neobsahuje location nebo akci, ktera je aktivni
    		foreach($this->groups as $key => $group) {
				if(isset($relevant[$group[0]]) && (in_array($action,$group) || in_array($this->location, $group))) {
					$relevant[$group[0]]['active'] = true;
					break;
				}
    		}
        }	
        
        
        // Odstranime odkazy, ktere byly odebrany pomoci $this->removeItem()
        $toRemove = array();
        if(!empty($this->location)){
            if(isset($this->removed[$this->location])){
                $toRemove = array_merge($toRemove, $this->removed[$this->location]);
            }
        }
        else{
            if(isset($this->removed[$action])){
                $toRemove = array_merge($toRemove, $this->removed[$action]);
            }
        }
        // Pridame jeste ty, ktere maji byt odebrany ve vsech akcich a lokacich
        $toRemove = array_merge($toRemove, $this->removedAllActions);
        // Odstranime z $relevant polozky se zakazanymi klici
        foreach($toRemove as $item){
            if(array_key_exists($item, $relevant)){
                unset($relevant[$item]);
            }
        }
        
        // Vyplnime controller, akci a modul do params, pokud nejsou jiz vyplneny,
        // tim je umozneno provest reset v helper_url, tedy odstranit vsechny ostatni parametry.
        foreach($relevant as $key => $item){
            if(is_array($item['params'])){
                if(!array_key_exists('action', $item['params'])){
                    $relevant[$key]['params']['action'] = $action;
                }
                if(!array_key_exists('controller', $item['params'])){
                    $relevant[$key]['params']['controller'] = $controller;
                }
                if(!array_key_exists('module', $item['params'])){
                    $relevant[$key]['params']['module'] = $module;
                }
            }
        }
        
        //echo $mode."<br/>";
        //var_dump($relevant);
        // Vratime data pro vyrenderovani
        return $relevant; 
    }
    
}
