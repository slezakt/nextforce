<?php
/**
 * Editace souboru
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_EditController extends Ibulletin_Admin_BaseControllerAbstract
{
	const MAX_FILE_SIZE = 2097152; // 2 MBytes

    private $_backupPath = array('views/scripts');
    
    /**
     * @var array mime-type restriction
     */
    static private $_allowedMimeTypes = array('text/plain');

    /**
     * @var array file extension restriction
     */
    static private $_allowedFileExtensions = array('ini','xml','css','html', 'phtml', 'php','js','txt','srt');

    /**
     * @var array base path restrictions
     */
    static private $_allowedPaths = array('pub/content','pub/skins','pub/mails', 'pub/resources', 'views/scripts');

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
	public function init() {
        parent::init();
        // TODO:add ajax content switch

    }

    public function updateRecord($name, $values) {
        switch ($name) {
        case "edit" :

            $path = $values['path'];

            if ($this->isBackedUpPath($path)) {
                $bck_path = preg_replace('/\.phtml$/', '.bckp', $path);

                //jestlize neexistuje bckp file vytvorime ho, parametr /nobckp/1 vytvoreni bckp filu potlaci
                if(!is_file($bck_path) && !$this->_hasParam('nobckp')) {
                    copy($path, $bck_path);
                }
            }

            // ulozeni do souboru
            $data = $values['content'];

            // odlinkovani, pokud editujeme link TODO: kontrola /products
            if (is_link($path)) {
                unlink($path);
            }

            // write changes to file
            $saved = file_put_contents($path, $data);


            // call postSaveActions() on parent controller
            $control = $values['control'];
            $id = $values['id'];

            if ($control && $id) {
                $class = 'Admin_'.ucwords($control) . 'Controller';
                Zend_Loader::loadFile(ucwords($control) . 'Controller.php',array('admin/controllers'));
                try {
                    $controllerObject = new $class(new Zend_Controller_Request_Http(), new Zend_Controller_Response_Http());
                    if (method_exists($controllerObject,'postSaveActions')) {
                        $controllerObject->postSaveActions();
                    }
                } catch (Exception $e) {}
            }

            return $saved;
            
        default: return null;
        }   
    }

    public function getRecord($name, $id) {
        switch ($name) {
        case "edit":        	      
        	
        	$path = $id['path'];
            $data['control'] = $id['control'];
            $data['id'] = $id['id'];

        	// naplneni formulare obsahem a metadatami o souboru
        	$data['content'] = file_get_contents($path);
        	$data['path'] = $path;

            return $data;
        default: return null;
        }
    }
 
    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getForm($name)
     */
    public function getForm($name) {
        switch ($name) {
        case "edit":        	
            $form = parent::getForm($name);

            // textarea pro obsah konfiguracniho souboru
            $el = new Zend_Form_Element_Textarea(array(
            		'name' => 'content',
            		'label' => $this->texts->content,
            		'required' => true,
            		'class' => 'editarea',
                    'data-ace-submit' => 'ajax'
            ));

            $hidden = new Zend_Form_Element_Hidden(array(
            	'name' => 'path',
            ));

            $hidden3 = new Zend_Form_Element_Hidden(array(
                'name' => 'control',
            ));
            $hidden4 = new Zend_Form_Element_Hidden(array(
                'name' => 'id',
            ));

            $form->addElements(array($hidden, $hidden3,$hidden4, $el));

            // ace editor
            $form->getElement('content')->setDecorators(array(array('ViewScript', array('viewScript' => 'editor_form.phtml'))));

            return $form;
        default: return null;
        }
    }

    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::getUpdateForm($name)
     */
    public function getUpdateForm($name) {
        switch ($name) {
            case "edit":
                $form = parent::getUpdateForm($name);

                $el = new Zend_Form_Element_Button('close', array(
                    'label' =>  $this->texts->index->close,
                    'onClick' => 'window.close();'
                ));
                $form->addElements(array($el));
                $form->addDisplayGroup(array($el,$form->getElement('edit_submit_update')),
                    'grp1',array('displayGroupClass' => 'Form_DisplayGroup_ModalFoot'));

                return $form;
            default: return null;
        }
    }

    /**
    * zobrazi formular pro editaci souboru
    */
    public function indexAction() {

        //editace z elfinderu,cesta k souboru jako hash
        if ($this->_hasParam('fhash')) {
            //decode hash cesta, prevzato z elfinderu
            $hash = $this->_getParam('fhash');
            $id = $this->_request->getParam('id', '');
            $basepath = $this->_getParam('basepath',$this->config->content_article->basepath.$id);
            
            // cut volume id after it was prepended in encode
			$h = substr($hash, strpos($hash,'_')+1);
			// replace HTML safe base64 to normal
			$path = base64_decode(strtr($h, '-_.', '+/='));
            
            $path = $basepath.'/'.$path;
            $control = $this->_request->getParam('control', null);
        } else {
            $path = $this->_request->getParam('path', null);
            $control = $this->_request->getParam('control', null);
            $id = $this->_request->getParam('id', null);
        }

        if ($path) {
            Ibulletin_Js::addJsFile('admin/ajaxsave.js');
            $this->view->path = $path;
            $pathOrig = $path; // Save for redirect
            $this->view->title = preg_replace('/^(.*)\.shadow(.*)$/','$1$2', sprintf($this->actionTitle, $path));
            $this->actionTitle = pathinfo($path, PATHINFO_BASENAME);

            if (!file_exists($path) || !is_readable($path) || !is_writable($path)) {
                $this->infoMessage($this->texts->notfound, 'error', array($path));
                $this->view->form = null;
                return;
            }

            if (filesize($path) > self::MAX_FILE_SIZE) {
                $this->infoMessage($this->texts->toobig, 'error', array($path));
                $this->view->form = null;
                return;
            }

            if (!self::isValidFile($path)) {
                $this->infoMessage($this->texts->invalidmime, 'error', array($path));
                $this->view->form = null;
                return;
            }

            // change path here if file has shadow copy, so updates and reads are made from/to shadow file
            if (Files::hasShadowFile($path)) {
                $path = Files::getShadowFile($path);
            }

            // set redirect URI after successfull update
            $urlHlpr = new Zend_View_Helper_Url();
            $uri = $urlHlpr->url(array('action'=>'index'),null,false) . '?path='.$pathOrig;

            $this->setAfterUpdateUrl('edit', null, $uri);

            $this->view->form = $this->processUpdate('edit', array(
                'path' => $path,
                'control' => $control,
                'id' => $id
            ));
            
            $this->view->control = $control;

            # Vlozime do view jeste seznam linku
            $links = new Links();
            if ($control == "indetail") {
                $this->view->links_list = $links->getLinks();
            }
            
        } else {
    		Phc_ErrorLog::error('Admin_EditController', 'missing argument path');

    	}
    }

    /**
     * cesta se nachazi v zalohovanem adresari
     * @param $path
     * @return bool vraci true pokud je soubor na chranene zalohovane ceste
     */
    public function isBackedUpPath($path) {
        $path = ltrim($path, '\\/');
        $res = false;
        foreach ($this->_backupPath as $bp) {
            $res = strpos($path, ltrim($bp,'\\/'), 0) !== FALSE;
            if ($res) return true;
        }
        return $res;
    }

    
     /**
     * checks for mime type, file extension and base path of file
     *
     * @param string $path
     * @return string TRUE if file is valid for editing
     */
    public static function isValidFile($path) {

        $found = false;

        // base extension detection
        $fpath = pathinfo($path, PATHINFO_DIRNAME);
        if (!empty($fpath)) {
            foreach (self::$_allowedPaths as $v) {
                if (preg_match('#^' . $v . '#', $fpath)) $found = true;
            }
        }

        // if not allowed path skip other checks
        if (!$found) return false;

        $found = false;
        // mime-type detection
        $fmime = Utils::getMimeType($path);
        if (!empty($fmime)) {
            foreach (self::$_allowedMimeTypes as $v) {
                if (preg_match('#' . $v . '#', $fmime)) $found = true;
            }
        }

        // skip file extension check if is valid mime
        if ($found) return true;

        // file extension detection
        $fext = pathinfo($path, PATHINFO_EXTENSION);
        if (!empty($fext)) {
            foreach (self::$_allowedFileExtensions as $v) {
                if (preg_match('#' . $v . '#', $fext)) $found = true;
            }
        }

        return $found;

    }
    
    

}
