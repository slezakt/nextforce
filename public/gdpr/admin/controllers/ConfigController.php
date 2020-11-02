<?php
/**
 * Sprava konfigurace projektu
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_ConfigController extends Ibulletin_Admin_BaseControllerAbstract
{

    public $configFile = 'config_admin.ini';
    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
	public function init() {
        parent::init();

        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
        );

        if (Ibulletin_AdminAuth::hasPermission('config_advanced')) {
            $this->submenuAll['config'] = array('title' => $this->texts->submenu_advanced, 'params' => array('action' => 'advanced'), 'noreset' => false);
        }

    }

    public function updateRecord($name, $values) {
        // ignore id
        unset($values['id']);

        switch ($name) {
        case "config" :
            // ulozeni obsahu konfiguracniho souboru
            $data = $values['config_content'];

            $tmp = tempnam(sys_get_temp_dir(), 'cfg');
            try {
            	// TODO: atomicity, wait for free file handle before opening for write

            	// test zend_config_ini instance
            	$fp = fopen($tmp, 'w');
            	fputs($fp, $data);
            	fclose($fp);
            	new Zend_Config_Ini($tmp);
            	unlink($tmp);

            	// write config file
            	$fp = fopen($this->configFile, 'w');
            	fputs($fp, $data);
            	fclose($fp);
            } catch (Exception $e) {
            	unlink($tmp);
            	Phc_ErrorLog::warning('configAdmin', $e->getMessage());
            	return false;
            }
            return true;
        case "basic" :

            
            $result = Config::setBasicConfig($values, $this->configFile);
                
            if ($result['errorsMsg']) {
                foreach ($result['errorsMsg'] as $errorMsg) {
                    $this->infoMessage($this->texts->$errorMsg,'error');
                }
            }
            
            return $result['state'];
            
        default: return null;
        }
    }

    /**
     * read configuration values
     * @param string $name
     * @param mixed  $id
     * @return array|null
     */
    public function getRecord($name, $id) {
        // $id je ignorovano
        switch ($name) {
        case "config":
        	// nacitani konfiguracniho souboru
        	$data['config_content'] = file_get_contents($this->configFile);
            return $data;
        case "basic":
            // nacitani konfigurace
            $cfg = $this->config;

            return array(
                'come_in_page' => $cfg->general->come_in_page,
                'project_name' => $cfg->general->project_name,
                'project_email' => $cfg->general->project_email,
                'skin' => $cfg->general->skin,
                'language' => $cfg->general->language,
                'domains_register_as_client' => $cfg->general->domains_register_as_client,
                'domains_register_as_test' => $cfg->general->domains_register_as_test,
                // csv to array
                'forbidden_came_in_pages' => array_map('trim',explode(',',$cfg->general->forbidden_came_in_pages)),
                'return_path' => $this->checkReturnPathDomain($cfg->mailer->return_path)
            );
            
            
            default: return null;
        }
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getUpdateForm($name) {
        switch ($name) {
        case "config":
            $form = parent::getUpdateForm($name);

            // textarea pro obsah konfiguracniho souboru
            $form->addElement('textarea', 'config_content', array(
            		'label' => $this->texts->content,
            		'required' => true,
                    'class' => 'editarea',

            ));
            $form->getElement('config_content')->setDecorators(array(
                array('ViewScript', array('viewScript' => 'editor_form.phtml','templates'=>$this->loadTemplates()))
            ));

            return $form;

        case "basic":
            $form = parent::getUpdateForm($name);
            
            $subform_general =  new Form_SubForm();

            // general.project_name
             $subform_general->addElement('text','project_name',array(
                'label' => $this->texts->project_name,
                'required' => true,
            ));

            // general.project_email
             $subform_general->addElement('text', 'project_email',array(
                'label' => $this->texts->project_email,
                'required' => false,
                'validators' => array(
                    'EmailAddress'
                )
            ));

            // general.come_in_page
             $subform_general->addElement('select', 'come_in_page',array(
                'label' => $this->texts->come_in_page,
                'required' => false,
                'multioptions' => Templates::getComeInPagesTexts(Templates::getLandingPages())
            ));

            // general.forbidden_come_in_pages
             $subform_general->addElement('multiselect', 'forbidden_came_in_pages',array(
                'label' => $this->texts->forbidden_came_in_pages,
                'required' => false,
                'multioptions' => Templates::getComeInPagesTexts(Templates::getAllEntryMetohods())
            ));

            // general.skin
             $subform_general->addElement('select', 'skin',array(
                'label' => $this->texts->skin,
                'required' => false,
                'multioptions' => Skins::findPairs()
            ));

            // general.language
             $subform_general->addElement('select', 'language',array(
                'label' => $this->texts->language,
                'required' => false,
                'multioptions' => Ibulletin_Texts::getAvailableLangs()
            ));

            // general.domains_register_as_client
             $subform_general->addElement('text', 'domains_register_as_client',array(
                'label' => $this->texts->domains_register_as_client,
                'required' => false,
            ));

            // general.domains_register_as_test
             $subform_general->addElement('text', 'domains_register_as_test',array(
                'label' => $this->texts->domains_register_as_test,
                'required' => false,
            ));
            
            $form->addSubForm($subform_general, 'general');
            
            $subform_mailer = new Form_SubForm();
            
            // mailer.return_path
            $subform_mailer->addElement('checkbox', 'return_path',array(
                'label' => $this->texts->mailer_return_path
            ));

            $form->addSubForm($subform_mailer, 'mailer');
            return $form;
        default: return null;
        }
    }


    /**
    * zobrazi formular pro editaci konfiguraku
    */
    public function advancedAction() {

        if (!Ibulletin_AdminAuth::hasPermission('config_advanced')) {
            $this->redirect(array('action'=> 'index'));
        }

        Ibulletin_Js::addJsFile('admin/collapse.js');
    	$this->setAfterUpdateUrl('config','advanced');
    	$this->view->form = $this->processUpdate('config', null);
    }

    /**
     * zobrazi formular pro basic konfiguraci
     */
    public function indexAction() {
        Ibulletin_Js::addJsFile('admin/collapse.js');
        $this->setAfterUpdateUrl('basic','index');
        $this->view->form = $this->processUpdate('basic', null);
    }
    
    
    /**
     * Kontroluje zda je v return path nastaven email s doménou projektu
     * @param string $return_path return path email
     * @return boolean
     */
    public function checkReturnPathDomain($return_path) {
        
        $tmp = explode('@',$return_path);
        $rr_domain = array_pop($tmp);
        $domain = $_SERVER['SERVER_NAME'];
        
        // Odstranit www. nebo vyvoj. z domeny
        $prefixes = array('www.', 'vyvoj.');
        foreach ($prefixes as $prefix) {
            if (substr($domain, 0, strlen($prefix)) == $prefix) {
                $domain = substr($domain, strlen($prefix));
            } 
        }
        
        if ($rr_domain == $domain) {
            return true;
        } else {
            return false;
        }

    }
    
    
}
