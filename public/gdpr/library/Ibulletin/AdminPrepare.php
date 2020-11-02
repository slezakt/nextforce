<?php

/**
 * iBulletin - AdminPrepare.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


/**
 * Trida se postara o pripraveni vseho potrebneho pred
 * spustenim samotnych controlleru jednotlivych modulu.
 * Provede autentizaci a pripravi menu do view.
 * Pouzije se pouze vytvorenim nove instance.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_AdminPrepare
{
    /**
     * Jmena modulu, u kterych nemaji byt overovana pristupova prava uzivatele
     */
    var $not_auth_modules = array('login', 'logoff', 'index', 'adminusers', 'loopback','filemanager');

    /**
     * Provede vse potrebne pred spustenim adminu.
     */
    public function __construct(){
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        // Autentizace
        Zend_Loader::loadClass('Ibulletin_AdminAuth');
        Ibulletin_AdminAuth::authenticate();

        // Front controller a viewRenderer
        $frontController = Zend_Controller_Front::getInstance();
        $renderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');

        // Nastavime casovou zonu
        $user_data = Ibulletin_AdminAuth::getInstance()->user_data;
        if(!empty($user_data)){
            $timezone = empty($user_data->timezone) ? $config->general->default_timezone : $user_data->timezone;            
            date_default_timezone_set($timezone);
            $q = "SET TIME ZONE '$timezone'";
            $db->getConnection()->exec($q);
        }
        
        $res = Ibulletin_Texts::getLangs();        
        Ibulletin_Js::getInstance()->vars->language = $res[0];
        
        // Pridame menu k vyrenderovani
        $renderer->view->menu = $this->_getMenu();
        
         $renderer->view->version = self::getInboxVersion();

        // Overime pravo pristupu do daneho modulu administrace
        $fc = Zend_Controller_Front::getInstance();
        $module_name = $fc->getRequest()->getControllerName();
        if(!in_array($module_name, $this->not_auth_modules)){
            if(!Ibulletin_AdminAuth::hasPermission('module_'.$module_name)){
                $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                $redirector->gotoAndExit('index', 'index');
                //echo 'UNauth '.$module_name.' ';
            }
        }
    }


    /**
     * Prida do pole submenu novy prvek a
     * nahraje nove submenu do view. Je mozne zadat vsechny parametry pro
     * routovani primo v params a routovat kamkoli, ne jen uvnitr controlleru.
     *
     * @param string Jmeno zobrazovane v submenu
     * @param string Jmeno akce ktere se submenu tyka
     * @param array Pole obsahujici dalsi parametry predavane adresou (nazev_param => hodnota)
     */
    public static function addSubmenuItem($name, $action, $params = array()){
        //nacteme si URL helper, abychom mohli snadno delat URL
        Zend_Loader::loadClass('Zend_View_Helper_Url');
        $urlHlpr = new Zend_View_Helper_Url();

        // Front controller a viewRenderer
        $frontController = Zend_Controller_Front::getInstance();
        $renderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');

        // Ziskame aktualni controller
        $controllerName = $frontController->getRequest()->getControllerName();

        // Ziskame aktualni menu
        $menu = $renderer->view->submenu;

        // Vytvorime URL a pridame polozku do submenu
        // Params se merguje, takye je mozne zadat uplne vsechny parametry znovu
        $url = $urlHlpr->url(array_merge(array('controller' => $controllerName, 'module' => 'admin', 'action' => $action), $params), 'default', true);
        $menu[] = array('url' => $url, 'name' => $name);
        // Pridame menu k vyrenderovani
        $renderer->view->submenu = $menu;

    }


    /**
     * Pripravi a vrati pole menu pro renderovani.
     *
     * @return array Pole poli [url][name]
     */
    private static function _getMenu(){
        $config = Zend_Registry::get('config');
        $texts = Ibulletin_Texts::getSet('admin.module_names');

        //nacteme si URL helper, abychom mohli snadno delat URL
        Zend_Loader::loadClass('Zend_View_Helper_Url');
        $urlHlpr = new Zend_View_Helper_Url();

        $fc = Zend_Controller_Front::getInstance();
        $controller = $fc->getRequest()->getControllerName();
        
        $menu_cfg_new = array();
        
        if ($config->admin_modules_menu) $menu_cfg_new = $config->admin_modules_menu->toArray();
     
        $menuN = array();
         
        foreach ($menu_cfg_new as $key => $m) {
            $cat_active = false;            
            foreach ($m as $skey => $sm) {               
                //preskocime zaznamy s nulou
                if($sm == 0) continue;
                //preskocime settings"
                if ($skey == 'settings') continue;
                // Polozky ke kterym nema uzivatel pravo preskakujeme
                if (!Ibulletin_AdminAuth::hasPermission('module_' . $skey)) {
                    // Pro spravu uzivatelu adminu plati jina pravidla
                    if ($skey == 'adminusers') {
                        if (!Ibulletin_AdminAuth::hasPermission('module_' . $skey)) {
                            //$val = 'Nastavení uživatele';
                            $name = $texts->adminusersOnlySelf;
                        }
                    } else {
                        continue;
                    }
                } else {
                    $name = $texts->$skey;
                }
                //paklize nema name tzn. nema prava nebo neni v textech tzn preskakujem
                if (!$name) continue;
                $url = $urlHlpr->url(array('controller' => $skey, 'module' => 'admin'), 'default', true);
                $menuN[$key]['submenu'][] = array('url' => $url, 'name' => $name, 'active' => ($controller == $skey));
                if ($controller == $skey) $cat_active = true;
            }
            //pokud existuje nejaky submenu, pridame menu
            if (isset($menuN[$key]['submenu'])) {
                $menuN[$key]['name'] = $texts->category->$key;
                $menuN[$key]['active'] = $cat_active;
            }
        }
        
        return $menuN;
    }
    /**
     * Vraci verzi a revizi instalace dle umisteni 
     * @return string
     */
    public static function getInboxVersion() {
        $inbox_info = ".inbox_instance.info";
        if(is_file($inbox_info)) {
           $fcont = file($inbox_info, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                      
           if (isset($_SERVER['SERVER_NAME'])) {
           $host = $_SERVER['SERVER_NAME'];
           } else {
               //neni-li SERVER_NAME neni mozne zjistit verze, vratime prazdny retezec
               return '';
           }
           
           $revision = "";
           $version = "";
           //pro vyvoj vracime jine hodnoty nez pro ostry
           if (!preg_match('/^vyvoj.*/', $host)) {
               $val = current(preg_grep('/^ostryrevision/', $fcont));
               if ($val) {
                   $revision = 'r'.trim(str_replace("ostryrevision=", "", $val));
               } 
               $val = current(preg_grep('/^ostryversion/', $fcont));
               if ($val) {
                   $version = 'v'.trim(str_replace("ostryversion=", "", $val));
               } 
           } else {               
               $val = current(preg_grep('/^vyvojrevision/', $fcont));
               if ($val) {
                   $revision = 'r'.trim(str_replace("vyvojrevision=", "", $val));
               } 
               $val = current(preg_grep('/^version/', $fcont));
               if ($val) {
                   $version = 'v'.trim(str_replace("version=", "", $val));
               } 
           }
           
           return $version.' '.$revision;
          
        } else {
            return "";
        }
    }
}
