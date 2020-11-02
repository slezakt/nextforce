<?php
/**
 * Trida rozsiruje Zend_Controller_Action
 *
 * rozsiruje menu pomoci tridy Ibulletin_Admin_ModuleMenu 
 * pristup k textum na urovni controlleru pres $this->texts (plati i pro view)
 * zjednoceni prenaseni info a flash zpravicek
 * zpravicky obsahuji strukturovane data, napriklad typ spravy (error, success, information, warning, ...)
 * zakladni implementace odvozenych formularu urceneho k pridavani/editaci zaznamu (urceneho nazvem entity)
 * implementace spracovani formulare (urceneho nazvem entity) pro pridani a pro update zaznamu, 
 * vcetne redirectu po uspechu. redirecty jdou nastavit/vypnout pres setter setAfter<Create|Update|Delete>Url($url)
 * TODO: moznost vice ruznych textu v zavislosti od entity name - ted jsou vsechny texty brane z urovne controlleru
 * povinne klice textu jsou {action}.[submit,notsaved,saved,notvalid,notfound,{entity_name}_submit]
 *   

 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 *
 */
abstract class Ibulletin_Admin_BaseControllerAbstract extends Zend_Controller_Action
{
	
	/**
	 * instance konfiguracniho souboru
	 * 
	 * @var Zend_Config 
	 */
	protected $config = null;

	/**
	 * instance databazoveho objektu
	 * 
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $db = null;
	
    /**
     * obsahuje seznam linku v navigaci pro konkretni controller
     * nazev_linku => [title, params[action, controller, module, ...], noreset]
     *
     * @var array  
     */
    protected $submenuAll = array();

    /**
     * obsahuje seznam vedlejsich linku v navigaci pro konkretni link ze $submenuAll
     * nazev_linku => [title, params[action, controller, module, ...], noreset]
     *
     * @var array   
     */   
    protected $submenuSpecific = array();

    /**
     * obsahuje seznam skupin ktere grupuji linky v navigaci
     * nazev_skupiny => [nazev_linku_parent, nazev_linku_child_1, nazev_linku_child_2, ...]
     *
     * @var array     
     */

    protected $submenuGroups = array();
    
    /**
    * menu modulu
    *
    * @var Ibulletin_Admin_ModuleMenu
    */
    public $moduleMenu = null;
    
    /**
     * popisek modulu. implicitne nastavujeme z textuu a klice "heading" pred dispatchem
     *
     * @var string
     */
    public $moduleTitle = null;

    /**
     * popisek akce. implicitne nastavujeme z textuu a klice "heading" po dispatchi
     */
    
    public $actionTitle = null;

    /**
     * objekt textu na urovni controlleru, dostupne pred dispatchem a taky ve view 
     * @see Ibulletin_Texts
     *  
     * @var Zend_Config
     */
    public $texts = null;
        
    /**
     * seznam zpravicek
     * 
     * @var array 
     */
    protected $_info_messages = array();

    /**
     * byly nastavene flash spravy?
     * 
     * @var boolean
     */
    private $_info_messages_sent = false;
     
    /**
     * seznam formularu pro vytvoreni zaznamu
     * 
     * @var array|Zend_Form klic je nazev entity     
     */
    private $_create_form = array();
    
    /**
     * seznam formularu pro editovani zaznamu
     *
     * @var array|Zend_Form klic je nazev entity
     */
    private $_update_form = array();

    /**
     * callback pred spracovanim updateRecord() pri volani processUpdate()
     * @var callback
     */
    private $_before_update_cb = null;

    /**
     * callback pred spracovanim createRecord() pri volani processCreate()
     * @var callback
     */
    private $_before_create_cb = null;

    /**
     * redirect na url po uspesnem (vytvoreni/update/smazani) zaznamu 
     */
    
    private $_after_create_url = array();
    private $_after_update_url = array();
    private $_after_delete_url = array();

    /**
     * @var array
     */
    protected $_message = array();
    
    /**
     * Context Menu
     * @var array
     */
    public $_contextMenu = array();
    
    /**
     * @see Zend_Controller_Action::init()
     */
    public function init(){

    	// handler na databazi
    	$this->db = Zend_Registry::get('db');
    	
    	// pristup do configu
    	$this->config = Zend_Registry::get('config');

        // texty od urovne controlleru
        if(empty($this->texts)){ // Pouze pokud jiz neni nastaveno odjinud (napr pri ziskavani privilegii v adminusersController)

            $ident = '';
            if ($this->_request->getModuleName()) {
                $ident .=$this->_request->getModuleName();
            }
            if ($this->_request->getControllerName()) {
                $ident .= $ident? '.'.$this->_request->getControllerName(): $this->_request->getControllerName();
            }

            $this->texts = Ibulletin_Texts::getSet($ident);
        }


        //popis do view s prelozenim tagu
        if (isset($this->texts->{$this->_request->getActionName()}->description))
        $this->view->description = $this->parseTags($this->texts->{$this->_request->getActionName()}->description);
        
        //preda do view polozky pro context menu
        $this->_contextMenu = $this->prepareContext();
        $this->view->contextMenu = $this->_contextMenu;
        
        //datum pro datetimepicker
        Ibulletin_Js::getInstance()->vars->datepicker = $this->config->general->dateformatpicker->medium->date;
        

    }
    
    /**
     * pred dispatchem
     * 
     * @see Zend_Controller_Action::preDispatch()
     */    
    public function preDispatch() {

    	// admin module 
    	$this->moduleTitle = isset($this->texts->heading) ? $this->texts->heading : '';

    	//action title
    	$texts_action = isset($this->texts->{$this->_request->getActionName()}) ? $this->texts->{$this->_request->getActionName()} : null;
    	if (!empty($texts_action)) {
    		$this->actionTitle = isset($texts_action->heading) ? $texts_action->heading : '';
    	}
    	
    	// admin module menu
        $this->moduleMenu = new Ibulletin_Admin_ModuleMenu(
            $this->submenuAll, 
            $this->submenuSpecific,
            $this->submenuGroups);
    }
    
    /**
     * po dispatchi
     * 
     * @see Zend_Controller_Action::postDispatch()
     */
    public function postDispatch() {

	    // module menu
	   	$this->view->moduleMenu = $this->moduleMenu;
	    
	    // title, h1, h3
	   	$this->view->page_title = $this->moduleTitle . ' - '. $this->actionTitle;
	    $this->view->moduleTitle = $this->moduleTitle;
	    $this->view->actionTitle = $this->actionTitle;
	    
	    
	    // flash a info zpravicky
	    $this->view->info_messages = $this->getMessages();
	    
	    // module texty
	    $this->view->texts = $this->texts;
    }

    // TODO: CRUD funkce oddelit do interfacu
    
    /**
    * metoda pro pridani zaznamu
    * $values obsahuje validni a "sanitized" hodnoty z formulare getCreateForm()
    * metoda by mela vracet TRUE v pripade uspechu, jinak FALSE
    *
    * @param string $name identifikator entity
    * @param array $values
    *
    * @return boolean
    */
    protected function createRecord($name, $values) {
    	throw new Exception('createRecord() not implemented!');
    }
    
    /**
     * neimplementovana metoda pro update zaznamu
     * $values obsahuje validni a "sanitized" hodnoty z formulare getUpdateForm()
     * metoda by mela vracet TRUE v pripade uspechu, jinak FALSE
     *
     * @param string $name identifikator entity
     * @param array $values
     *
     * @return boolean
     */
    protected function updateRecord($name, $values) {
    	throw new Exception('updateRecord() not implemented!');
    }
    
    /**
     * neimplementovana metoda pro smazani zaznamu
     * metoda by mela vracet TRUE v pripade uspechu, jinak FALSE
     *
     * @param string $name identifikator entity
     * @param mixed $id identifikator zaznamu
     *
     * @return boolean
     */
    
    protected function deleteRecord($name, $id) {
    	throw new Exception('deleteRecord() not implemented!');
    }
    
    /**
     * neimplementovana metoda pro ziskani jednoho zaznamu
     * interne se pouzije pri populate formu pred updatem zaznamu
     * metoda by mela vracet pole jinak NULL
     *
     * @param string $name identifikator entity
     * @param mixed $id
     *
     * @return array
     */
    protected function getRecord($name, $id) {
    	throw new Exception('getRecord() not implemented!');
    }
    /**
     * implementovana metoda pro overeni existenci zaznamu podle id
     * $id obsahuje identifikator zaznamu
     * metoda by mela vracet true/false pokud existuje/neexistuje zaznam     
     *
     * @param string $name identifikator entity
     * @param mixed $id
     *
     * @return boolean
     */
    protected function existsRecord($name, $id) {
    	$res = $this->getRecord($name, $id);
    	if (is_null($res)) {
    		throw new Exception(sprintf('getRecord() is not implemented for name = %s',$name));
    	} 
    	return (boolean) $res;
    }
    /**
     * neimplementovana metoda pro ziskani vsech zaznamu
     * filtrovani vysledku lze parametrizovat pres volitelny treti
     * parametr $options. implementace zavisi od schopnosti modelu
     * ze ktereho se data ziskavaji spracovat predane "options"
     * typicky tam muzou byt klice pro strankovani, razeni, filtre ...
     * metoda by mela vracet TRUE v pripade uspechu, jinak FALSE
     *
     * @param string $name identifikator entity
     * @param array options defaults to array()
     *  array $id
     *
     * @return boolean
     */
    protected function getRecords($name, $options = array()) {
    	throw new Exception('getRecords() not implemented!');
    }
    
    /**
     * 
     * nastavi kam se ma redirectovat v pripade volani process{Update,Delete,Create}
     * prijima bud nazev akce stavajiciho controlleru nebo
     * pole URL vcetne doplnkovych parametru nebo
     * null a pak se redirectuje na URI parameter
     *
     * @param string $name nazev entity
     * @param string|array $url action nebo pole action, controller, module, params
     * @param string $uri FQDN URI plna cesta vcetne domeny
     */
    
    public function setAfterCreateUrl($name, $url = null, $uri = null) {
        $this->_after_create_url[$name] = array('url' => $url, 'uri' => $uri);
    }
    
    public function setAfterUpdateUrl($name, $url = null, $uri = null) {
        $this->_after_update_url[$name] = array('url' => $url, 'uri' => $uri);
    }
    
    public function setAfterDeleteUrl($name, $url = null, $uri = null) {
        $this->_after_delete_url[$name] = array('url' => $url, 'uri' => $uri);
    }

    /**
     * nastavi callback pro upravu formulare pred jeho spracovanim
     * predaji se parametry $name, $form
     *
     * @param $callback
     */
    public function setBeforeCreate($callback) {
        $this->_before_create_cb = $callback;
    }

    /**
     * nastavi callback pro upravu formulare pred jeho spracovanim
     * predaji se parametry $name, $form, $record
     *
     * @param $callback
     */
    public function setBeforeUpdate($callback) {
        $this->_before_update_cb = $callback;
    }

    /**
     * internal redirect helper for url/uri redirects
     *
     * @param array $params
     */
    private function _processRedirect($params) {

        if (isset($params['url'])) {
            $this->redirect($params['url']);
        } elseif (isset($params['uri'])) {
            $this->redirectUri($params['uri']);
        }

    }
    
    
    /**
     * Prida novy custom update form pro danou akci. Tento form je pak pouzit
     * misto formulare z self::getForm() pro danou akci.
     * 
     * !! Pouzivame pouze pokud nechceme pouzivat primo form z self::getForm(),
     * napriklad, kdyz chceme form nejprve upravit a potom pouzit.
     * 
     * @param string $name      Nazev fromu/entity.
     * @param Zend_Form $form   Formular.
     */
   public function setUpdateForm($name, $form){
       $this->_update_form['name'] = $form;
   }
   

    /**
     * vraci formular ktery definuje editovatelne atributy zaznamu
     * typicky se tato funkce pretezi, zavola se parent::getForm($name) a
     * doplni formularove prvky, validatory, messages...   
     * 
     * @param string $name identifikator entity
     * 
     * @return Zend_Form 
     */
    protected function getForm($name) {
        
       // $form = new Zend_Form();
        $form = new Form();
        $form->setMethod(Zend_Form::METHOD_POST);
        $form->setAttrib('id', $name);        
        return $form;
    }
    
    
    /**
     * vraci formular pro pridani zaznamu
     * implicitni implementace ziska formular pomoci getForm()
     * a prida submit button
     *    
     * @param string $name identifikator entity
     * @return Zend_Form
     */
    protected function getCreateForm($name) {
        
        if (!isset($this->_create_form[$name])) {
            
            $form = $this->getForm($name);
            $submit = new Zend_Form_Element_Submit($name.'_submit_create',  array('ignore' => true,'class'=>'btn-primary'));
            $submit->setLabel($this->getMessage(array('submit_create', 'submit')));
            $submit->setDecorators(array(
            		array('ViewHelper'),
            ));
            $submit->setOrder(PHP_INT_MAX);
            $form->addElement($submit);

            // group button(s) in decorator
//            $form->addDisplayGroup(array($submit), 'button_group_submit', array(
//            		'order' => PHP_INT_MAX,
//            		'decorators' => array(
//            				array('FormElements'),
//            				array('HtmlTag', array('tag' => 'div',  'class' => 'button_group_submit')),
//            		),
//            ));     
            
            $this->_create_form[$name] = $form;        
        }
        
        return $this->_create_form[$name];
        
    }
    
    
    /**
     * vraci formular pro pridani zaznamu
     * implicitni implementace ziska formular pomoci getForm()
     * a prida submit button a hidden id
     * 
     * @return Zend_Form
     */
    protected function getUpdateForm($name) {
        
        if (!isset($this->_update_form[$name])) {
            
            $form = $this->getForm($name);
            
            $id = new Zend_Form_Element_Hidden('id');
            $id->setDecorators(array(
            	array('ViewHelper')
            ));            
            $form->addElement($id);
            
            $submit = new Zend_Form_Element_Submit($name .'_submit_update', array('ignore' => true,'class'=>'btn-primary'));
            
            $submit->setLabel($this->getMessage(array('submit_update', 'submit')));
            $submit->setDecorators(array(
            	array('ViewHelper'),
            ));            
            $submit->setOrder(PHP_INT_MAX);
            $form->addElement($submit);           
            
//            // group button(s) in decorator
//            $form->addDisplayGroup(array($submit), 'button_group_submit', array(
//            		'order' => PHP_INT_MAX,
//            		'decorators' => array(
//            				array('FormElements'),
//            				array('HtmlTag', array('tag' => 'div',  'class' => 'button_group_submit')),
//            		),
//            ));
            
   		
            $this->_update_form[$name] = $form;
                    
        }
        
        return $this->_update_form[$name];
        
    }


    /**
     * spracuje formular, v pripade nevalidniho vraci NULL,
     * jinak vraci vysledek callback funkce s parametrami $params
     * doplnene o hodnoty z formulare
     * 
     * @param Zend_Form $form
     * @param callback $callback
     * @param array $params [optional]
     * @return 
     */    
    public function processForm($form, $callback, $params = array()) {
    	
    	if ($form->isValid($this->_request->getPost())) {                    
            $form->populate($form->getValues());
            
            // ziskame filtrovane hodnoty z formulare (lisi se od POSTu)
            $form_values = $form->getValues();
            
            // odstranime tlacitko ktere odeslalo formuar 
            // TODO : dependency break, vazba na tlacitka submit_create a submit_update
            unset($form_values[$params[0].'_submit_create']);
            unset($form_values[$params[0].'_submit_update']);
            
            // DEPENDENCY : posledni parameter callbacku musi prijimat pole formularovych hodnot
            $params[] = $form_values;
            
            return call_user_func_array($callback,$params);                
        } else {       
            return null;
        }           
    }
    
    
    /**
     * metoda vytvori zaznam pomoci createRecord()
     * v pripade nastavene redirect url presmeruje
     * vraci formular
     * 
     * @param string name 
     * @return Zend_Form
     */
    public function processCreate($name) {
        
        $form = $this->getCreateForm($name);

        //pridani zaznamu pokud mame post
        if ($this->_request->isPost() && $this->_request->getPost($name.'_submit_create')) {

            if (is_callable($this->_before_create_cb)) {
                call_user_func_array($this->_before_create_cb, array($name, $form));
            }
            
            $status = $this->processForm($form, array($this,'createRecord'), array($name));
            
            if ($status) {
                $this->infoMessage($this->getMessage(array('saved_create','saved')), 'success', $status);
                if (!empty($this->_after_create_url[$name]))
                    $this->_processRedirect($this->_after_create_url[$name]);

            } elseif ($status === false) {
                $this->infoMessage($this->getMessage(array('notsaved_create','notsaved')) , 'error');
            
            } elseif (is_null($status)) {
                $this->infoMessage($this->getMessage(array('notvalid_create','notvalid')), 'warning');
            }
        }

        return $form;
    }
    
    
    /**
     * metoda updatuje zaznam pomoci updateRecord()
     * v pripade nastavene redirect url presmeruje
     * vraci formular nebo null pokud jiz editovany zaznam neexistuje
     * 
     * @param string name 
     * @return Zend_Form
     */
    public function processUpdate($name, $id) {

        // retrieve form
    	$form = $this->getUpdateForm($name);

        // populate form from record
        $record = $this->getRecord($name, $id);
        
        if ($record) {
            $form->populate($record);
        } else {
            
        	$this->infoMessage($this->getMessage('notfound'), 'error', $id);
            if (!empty($this->_after_update_url[$name]))
                $this->_processRedirect($this->_after_update_url[$name]);
            
        }
           //update zaznamu pokud mame post
        if ($this->_request->isPost() && $this->_request->getPost($name.'_submit_update')) {                

            if (is_callable($this->_before_update_cb)) {
                call_user_func_array($this->_before_update_cb, array($name, $form, $record));
            }

            $status = $this->processForm($form, array($this,'updateRecord'), array($name));
            
            if ($status) {
                
                //pro ajax informační hlášku o uložení nezobrazujeme
                if(!$this->_request->isXmlHttpRequest()) {
                    $this->infoMessage($this->getMessage(array('saved_update','saved')), 'success', $id);
                }
                if (!empty($this->_after_update_url[$name]))
                    $this->_processRedirect($this->_after_update_url[$name]);

            } elseif ($status === false) {
                $this->infoMessage($this->getMessage(array('notsaved_update','notsaved')), 'error', $id);
            } elseif (is_null($status)) {
                $this->infoMessage($this->getMessage(array('notvalid_update','notvalid')), 'warning', $id);
            }
        }    

        return $form;
        
    }
    
    /**
     * Akce pro volani postsave ajaxem
     */
    public function postsaveAction() {
        
        if(method_exists($this,'postSaveActions')) {
            $this->postSaveActions();
            $this->view->info_messages = $this->_info_messages;
        }
        $this->renderScript('infoMessages.phtml');
    }

    
    /**
     * metoda smaze zaznam pomoci deleteRecord()
     * v pripade nastavene redirect url presmeruje
     * vraci null v pripade ze zaznam jiz neexistuje
     * jinak vraci true/false pokud se operace zdarila/nezdarila
     * 
     * return boolean
     */
    public function processDelete($name, $id) {
        
        // pred mazanim overime existenci zaznamu
        if (!$this->existsRecord($name, $id)) {
            $this->infoMessage($this->getMessage('notfound'), 'error', $id);
            if (!empty($this->_after_delete_url[$name]))
                $this->_processRedirect($this->_after_delete_url[$name]);
        }
        
        // provedeme delete zaznamu
        $res = $this->deleteRecord($name, $id);
        if ($res) {
            $this->infoMessage($this->getMessage('deleted'), 'info', $id);
            if (!empty($this->_after_delete_url[$name]))
                $this->_processRedirect($this->_after_delete_url[$name]);

        } else {            
            $this->infoMessage($this->getMessage('notdeleted'), 'error', $id);
            if (!empty($this->_after_delete_url[$name]))
                $this->_processRedirect($this->_after_delete_url[$name]);

        } 

        return $res;
    }
    
    
    /**
     * helper pro redirectovani v controlleru
     * 
     * pokud je prvni parameter string, tak se predaji vsechny parametry     
     * pokud pole, vytahnou se klice action, controller, module a zvysne klice jdou do params a vola se
     * Zend_Action_Helper_Redirector::goto($action, $controller, $module, $params)
     
     * @param array|string $action nazev akce, nebo pole urlOptions
     * @param string $controller [optional] 
     * @param string $module [optional]
     * @param array $params [optional]     
     */
    
    public function redirect($action, $controller = null, $module = null, array $params = array()) {

        // setup info messages
        if (!$this->_info_messages_sent) {
            $this->sendInfoMessages();
        }

        // no redirect if AJAX, json contextswitch is on and view variables with view scripts can be utilized
        if (!$this->getRequest()->isXmlHttpRequest()) {

            // redirect
            if (is_array($action)) {
                $url = $action;
                $action = !empty($url['action']) ? $url['action'] : $this->_request->getActionName();
                $controller = !empty($url['controller']) ? $url['controller'] : $this->_request->getControllerName();
                $module = !empty($url['module']) ? $url['module'] : $this->_request->getModuleName();

                // params are what has left
                unset($url['action'],$url['controller'],$url['module']);
                $params = $url;
            }

            // perform redirect
            $this->_helper->redirector->goto($action, $controller, $module, $params);

        }

    }

    /**
     * helper pro redirectovani podle URI
     * @param      $uri
     * @param bool $prependBase
     */
    public function redirectUri($uri, $prependBase = false) {

        // setup info messages
        if (!$this->_info_messages_sent) {
            $this->sendInfoMessages();
        }

        $this->_redirect($uri, $options = array('prependBase' => $prependBase));

    }

    /**
     * Returns first found message for (array of) keyword(s)
     *
     * @param $keyword
     */
    protected function getMessage($keyword)
    {
        if (!is_array($keyword)) $keyword = array($keyword);
        $action = $this->_request->getActionName();

        if (isset($this->texts->$action)) {
            $local_texts = $this->texts->$action;
        } else {
            $local_texts = Ibulletin_Texts::getSet();
        }

        foreach ($keyword as $k) {
            if (isset($this->_message[$k])) return $this->_message[$k];
            if (isset($local_texts->{$k})) {
                return $local_texts->{$k};
            }
        }

        return 'Missing text for keyword: '. implode(', ',$keyword);
    }

    /**
     * Set message for given keyword
     *
     * @param $keyword
     * @param $value
     */
    public function setMessage($keyword, $message) {
        $this->_message[$keyword] = $message;
    }

    /**
     * ulozi info zpravu
     * 
     * @param string $message telo zpravy
     * @param string $type typ zpravy [success,info,warning,error]
     * @param array $params promenne ve zprave
     * @param bool $appendToFM prida zpravu do flashMessages
     * @param array $detailedList polozky detailniho vypisu 
     * @return string
     */

    public function infoMessage($message, $type = 'success', $params = array(),$appendToFM = true, $detailedList = array()) {
    	
    	if(!is_array($params)){
    		$params = array($params);
    	}
    	
        $msg = empty($params) ? $message : vsprintf($message, $params);
        
        $data = array('type' => $type, 'message' => $msg, 'detailedList' => $detailedList);
        
        if ($appendToFM) {
            $this->_info_messages[] = $data;
        }
        
        return $msg;
        
    }
	
    
    /**
     * lze pouzit rucne pokud se nevola redirect ale napriklad _forward 
     */
    public function sendInfoMessages() {
    	$fm = $this->getHelper('FlashMessenger');
    	foreach ($this->_info_messages as $msg) {
    		$fm->addMessage($msg);
    	}
    	$this->_info_messages_sent = true;
    }
    /**
     * vraci lokalni info zpravy a flash spravy
     * 
     * @return array
     */
    public function getMessages() {
        
        $fm = $this->getHelper('FlashMessenger');
        
        return array_merge(
            (array)$this->_info_messages, 
            (array)$fm->getMessages(), 
            (array)$fm->getCurrentMessages()
        );        
    }

    /**
     * nastavi aktualnu URI do session
     */
    public function setLastVisited() {
        $session = new Zend_Session_Namespace('admin');
        $session->requestUri = $this->getRequest()->getRequestUri();
    }

    /**
     * vraci posledne ulozenou URI ze session, slouzi k redirectu na URI napr. po vykonani akce nad zaznamem
     * @return mixed|string
     */
    public function getLastVisited() {
        $session = new Zend_Session_Namespace('admin');
        return $session->requestUri ? $session->requestUri : '';
    }
    
    /**
    * nastavi titulek aktualni akce
    *
    * @param string $title retezec k titulku aktualni akce
    * @param boolean smazat puvodni titulek?
    */
    public function setActionTitle($title, $reset = false) {
    
    	if (empty($title)) {
    		return;
    	}
    	
    	$this->actionTitle = $reset ? $title : $this->actionTitle.' '.$title;
    }
    
    /**
     * nastavi do view seznam linku pro stranky, kategorie, bulletiny
     */
    public function renderLinks() {
        
	    # Vlozime do view jeste seznam linku
        $links = new Links();
        $this->view->links_list = $links->getLinks();
        
    }
    
    /**
     * Nahrazuje tagy v textu
     * @param type $str
     */
    private function parseTags($str) {
        //tags baseUrl
        $tag = '%%baseUrl%%';
        if (strpos($str,$tag)) $str = str_replace($tag, $this->view->baseUrl(), $str);
        return $str;
    }
    
    /**
     * Nahraje a pripravuje templaty pro ace.
     * 
     * Konfigurace je defaultne volena podle controlleru, pokud vsak existuje vetev konfigurace
     * odpovidajici konkretni akci, je zvolena ta (nemerguje se s konfiguraci controlleru).
     * Vetev akce je pojmenovana [jmeno_akce]Action.
     */
    public function loadTemplates() {
        
        if(!is_file(APPLICATION_PATH . '/config_templates.ini')) return;
        
        $config = new Zend_Config_Ini(APPLICATION_PATH . '/config_templates.ini');
        
        $c = $this->getRequest()->getControllerName();
        $action = $this->getRequest()->getActionName().'Action';
        $tags = array();
        $ico_tpls = array();
        $list_tpls = array();
        
        // Najdeme pozadovanou cast configu - pokud existuje specificka konfigurace pro danou akci, pouzijeme ji
        if(isset($config->$c->$action)){
            $configBranch = $config->$c->$action;
        }
        else{
            $configBranch = $config->$c;
        }
        
        //nastavi ace mode pro zvyrazneni syntaxe
        $ace_mode = 'html';        
        if (isset($configBranch->ace->mode))
            $ace_mode = $configBranch->ace->mode;

        //pripravi pole s daty pro ace toolbar - template list a template ikon - jestlize je definovana ikona
        if (isset($configBranch->tpls)) {            
            foreach ($configBranch->tpls as $key => $tpl) {                
                if ($tpl->icon) {
                    $ico_tpls[$key]['icon'] = $tpl->icon;
                    $ico_tpls[$key]['template'] = $tpl->template; 
                    $ico_tpls[$key]['tooltip'] = (isset($this->texts->tpls->$key->tooltip)) ? $this->texts->tpls->$key->tooltip : '';
                } else {
                    $list_tpls[$key]['template'] = $tpl->template;  
                    $list_tpls[$key]['title'] = (isset($this->texts->tpls->$key->title)) ? $this->texts->tpls->$key->title : $this->view->escape($tpl->template);
                    $list_tpls[$key]['tooltip'] = (isset($this->texts->tpls->$key->tooltip)) ? $this->texts->tpls->$key->tooltip : '';
                }
            }
        }

        //pripravi pole s daty pro ace toolbar - tags
        if (isset($configBranch->tags)) {
            foreach ($configBranch->tags as $key => $tag) {                
                $tags[$key]['title'] = (isset($this->texts->tags->$key->title)) ? $this->texts->tags->$key->title : $tag->tag;
                $tags[$key]['tooltip'] =  (isset($this->texts->tags->$key->tooltip)) ? $this->texts->tags->$key->tooltip : '';
                $tags[$key]['tag'] = $tag->tag;
            }
        }

        $templates = new stdClass();
        $templates->tags = $tags;
        $templates->ico_tpls = $ico_tpls;
        $templates->list_tpls = $list_tpls;
        $templates->mode = $ace_mode;
        return $templates;
    }
    
    
    /**
     * * pripravuje data pro zobrazeni context menu
     *
     *  @return array
     */
    public function prepareContext() {
        $act = $this->getRequest()->getActionName();
        $cont = $this->getRequest()->getControllerName();
        
        $context_menu = array();
       
        //context menu pro vsechny akce controlleru
        if (isset($this->config->admin_modules_contexts->$cont->__all__)) {
            $context_menu = $this->config->admin_modules_contexts->$cont->__all__->toArray();            
        }
        
        //context menu aktualni akce controlleru
        if (isset($this->config->admin_modules_contexts->$cont->$act)) {
            $context_menu = array_merge($context_menu,$this->config->admin_modules_contexts->$cont->$act->toArray());
        }

        //projde polozky menu a odstranit ty na ktere nema user prava
        foreach ($context_menu as $key => $cm) {
            if ((!Ibulletin_AdminAuth::hasPermission('module_' . $cm['route']['controller'])) || (!$this->isModuleEnable($cm['route']['controller']))) {
               unset($context_menu[$key]);
            } else {
            //pridame modul kvuli resetu url
            $context_menu[$key]['route']['module'] = 'admin';
            }
        }
        return $context_menu;
    }
    
    /**
     * Kontroluje zda je module povoleny v configu
     * @param string $module
     * @return boolean
     */
    private function isModuleEnable($module) {
        foreach ($this->config->admin_modules_menu->toArray() as $section) {           
            foreach ($section as $k => $v) {               
                if ($k == $module && $v == '1') return true;
            }
       }
        return false;
    }

}
