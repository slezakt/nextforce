<?php
/**
 * Modul pro spravu textu.
 * 
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_TextsController extends Ibulletin_Admin_BaseControllerAbstract {
	
	/**
	 * klice ktere maji byt vynechane z textu
	 * @var array
	 */
	private $_blacklisted_key = 'admin';
	
	/**
	 * mapovaci pole pro nahrazeni znaku za HTML ekvivalent
	 * @var array 
	 */
	private $_replace_char = array('"');
	private $_replace_html = array('&quot;');
	
	/**
	 * JS functions array
	 * key is function name, value is array of keys params|code
	 * @var array
	 */
    private $_js_functions = array (
            'revert' => array('code' => "
                $(document).ready(function() {
                    $('.revertBtn').each(function(){
                        $(this).on('click',function(){
                            element = $(this).parent().prev().find('textarea');
                            element.val(element.data('revert-title'));
                       });
                    });
                });"
            ),
            'tooltip' => array('code' => "
                $(document).ready(function() {
                    $('[data-toggle=tooltip]').tooltip();
                });"

        )
	);

    /**
     * struktura defaultnich textu pro aktualne editovany jazyk
     * @var array
     */
    private $_textStructure = array();

    /**
     * struktura defaultnich textu pro defaultni jazyk
     * @var array
     */
    private $_defaultLanguageTextStructure = array();
	
    /**
     * overrides Ibulletin_Admin_BaseControllerAbstract::init()
     */
	public function init() {
        parent::init();        
        $this->submenuAll = array(
            'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),
            'add' => array('title' => $this->texts->submenu_add, 'params' => array('action' => 'add'), 'noreset' => false),
        );
    }

    /**
     * akce pridani jazyka
     *
     */
    public function addAction() {

        // redirect back after form process
        $this->setAfterCreateUrl('language', array('action' => 'index'));
        // save and/or return form
        $this->view->form = $this->processCreate('language');

    }
        /**
	 * index akce
	 * 
	 */
    public function indexAction() {    	    	 

    	// get id for form, defaults to config language
    	$id = $this->_request->getParam('id', $this->config->general->language);
        $langs = Ibulletin_Texts::getAvailableLangs();

        if ($langs && (array_search($id, $langs) === FALSE)) {
            $this->redirect(array('action' => 'index', 'id' => $this->config->general->language));
        }
        // retrieve text for the current language (also used for generating form elements and revert funcionality)
        $default_lang_texts = Ibulletin_Texts::getInstance()->getDefaultLanguageRawTexts($id, $this->_blacklisted_key)->toArray();

        // vyfiltrujeme blacklistovane klice
        $default_lang_texts = array_diff_key($default_lang_texts, array_flip(array($this->_blacklisted_key)));
        // replace HTML code with its character equivalent
        array_walk_recursive($default_lang_texts, array($this, 'htmlToChar'));

        // store current language default texts
        $this->_textStructure = $default_lang_texts;

        // store default language texts
        if ($id != $this->config->general->language) {
            // retrieve text for the default language (used for tooltip title)
            $default_lang_texts = Ibulletin_Texts::getInstance()->getDefaultLanguageRawTexts($this->config->general->language, $this->_blacklisted_key)->toArray();

            // vyfiltrujeme blacklistovane klice
            $default_lang_texts = array_diff_key($default_lang_texts, array_flip(array($this->_blacklisted_key)));
            // replace HTML code with its character equivalent
            array_walk_recursive($default_lang_texts, array($this, 'htmlToChar'));
            $this->_defaultLanguageTextStructure = $default_lang_texts;
        } else {
            $this->_defaultLanguageTextStructure = $default_lang_texts;
        }

        $langs = Ibulletin_Texts::getAvailableLangs();

        // redirect back after form process
    	$this->setAfterUpdateUrl('texts',array('action' => 'index', 'id' => $id));
    	// save and/or return form
    	$this->view->form = $this->processUpdate('texts', $id);
        $this->view->id = $id; // the language
        $this->view->langs = $langs;
    }    
    
    /**
     * ziska texty
     * 
     * @see Ibulletin_Admin_CRUDControllerAbstract::getRecord()
     */
    public function getRecord($name, $id) {
        switch ($name) {
        case 'texts':
        	// vytahneme si texty
        	$res = Ibulletin_Texts::getInstance()->getRawTexts($id, $this->_blacklisted_key)->toArray();

            // vyfiltrujeme blacklistovane klice
            $res = array_diff_key($res, array_flip(array($this->_blacklisted_key)));

        	// replace HTML code with its character equivalent
        	array_walk_recursive($res, array($this, 'htmlToChar'));

        	return array_merge($res, array('id' => $id));

        default: return null;
        }
    }
	
    /**
     * zapise texty do souboru
     * 
     * @see Ibulletin_Admin_CRUDControllerAbstract::updateRecord()
     */
    public function updateRecord($name, $values) {
    	switch ($name) {
    		case 'texts' :
    			try {
    				// soubor do ktereho se zapise novy config object
                    $lang = $values['id'];

                    if (!$lang) throw new InvalidArgumentException('Missing language id for the update of texts');

                    // unset language, all other values are expected to be arrays of texts
                    unset($values['id']);

                    // find modifications between default texts and updated texts
                    $texts = Utils::multi_array_diff($values, $this->_textStructure);

                    // replace unacceptable characters with its HTML equivalent
                    array_walk_recursive($texts, array($this, 'charToHtml'));

                    // search files in texts/ directory
                    $dir = rtrim(Ibulletin_Texts::getTextsDir(),'\\/') . DIRECTORY_SEPARATOR;
                    $files = array();
                    $g = glob(($dir . $lang . "*.ini"));
                    if (!$g) $g=array();
                    foreach ($g as $f) {
                        // blacklist nonrelevant files
                        if (!preg_match('/'.preg_quote($lang).'\.'.preg_quote($this->_blacklisted_key).'.*\.ini$/',$f)) {
                            $files[] = $f;
                        }
                    }

                    //sort files based on segments of the filename
                    usort($files, array('Ibulletin_Texts', 'sortcmpBySegments'));
                    $files = array_reverse($files);

                    //
                    // prepare texts to write for each file found in texts/
                    //
                    $_t = $texts; // texts that are available for writing during iteration
                    $map_files_texts = array(); // result array of (file_prefix => texts_data)
                    foreach ($files as $f) {
                        $key = substr($f,strlen($dir), - 4); // remove dir prefix and ".ini"
                        // search texts array by $key
                        $t = $_t; // referenced texts for given file
                        $sections = explode('.', $key); // split key
                        array_shift($sections); //eliminate language
                        // pickup correct key from array
                        foreach ($sections as $section) {
                            if (isset($t[$section]) && is_array($t[$section])) { // TODO: orezat i o skalare(string), $t by melo byt pole
                                $t = $t[$section];
                            } else {
                                $t = array();
                                break;
                            }
                        }
                        // skip empty structure
                        if (count($t)) {
                            // store result text section
                            $map_files_texts[$key] = $t;
                            // remove text section that was just stored
                            $s = Utils::multi_explode(array(implode('.',$sections) => $t));
                            $_t = Utils::multi_array_diff($_t,$s); // remove stored text section in variable for next iteration
                        }
                    }


                    // pred updatem se smazou vsechny soubory v specific adresari ktere matchujou 'language*.ini'
                    $dir = rtrim(Ibulletin_Texts::getTextsDir(). Ibulletin_Texts::getSpecificDir(),'\\/');
                    $g = glob(($dir . DIRECTORY_SEPARATOR . $lang . "*.ini"));
                    if (!$g) $g=array();
                    foreach ($g as $f) {
                        unlink($f);
                    }

                    // write specific texts to specific files
                    foreach ($map_files_texts as $f => $t) {
                        // determine filename
                        $file = Ibulletin_Texts::getTextsDir()
                            . Ibulletin_Texts::getSpecificDir()
                            . $f . '.ini';
                        // write INI
                        $config = new Zend_Config_Writer_Ini();
                        $config->write($file, new Zend_Config($t));
                        Utils::chmod($file,Utils::FILE_PERMISSION);
                    }

    			} catch (Exception $e) {
    				Phc_ErrorLog::warning('texts', $e->getMessage());
    				return false;
    			}    			
    			return true;
    		default: return null;
    	}
    }

    /**
     * zapise texty do souboru
     *
     * @see Ibulletin_Admin_CRUDControllerAbstract::createRecord()
     */
    public function createRecord($name, $values) {
        switch ($name) {
            case 'language' :
                try {
                    // soubor jazyku ktery vytvorime v texts/specific
                    $lang = $values['language'];
                    $path = Ibulletin_Texts::getTextsDir() . $lang . '.ini';

                    // provadime pokus o zapsani souboru
                    $res = touch($path);
                    $res = $res && Utils::chmod($path, Utils::FILE_PERMISSION);

                    if (!$res) throw new Exception("Unable to create language file '$path'. Check OS permissions!");

                } catch (Exception $e) {
                    Phc_ErrorLog::warning('texts', $e->getMessage());
                    return false;
                }
                return true;
            default: return null;
        }
    }

    public function notInLangs($input, $context = null) {
        return (in_array($input, Ibulletin_Texts::getAvailableLangs()) === FALSE);
    }
    /**
     * pripravi formular
     *
     * @see Ibulletin_Admin_CRUDControllerAbstract::getForm()
     */
    public function getForm($name) {
    	switch ($name) {
            case 'language':
                $form = parent::getForm($name);

                $form->addElement('text', 'language',array(
                    'label' => $this->texts->language,
                    'required' => true,
                    'filters' => array('StringToLower'),
                    'validators' => array(
                        array('StringLength', false, array('max'=>2, 'min'=>2)),
                        array('Callback', false, array('callback' => array($this, 'notInLangs'), 'messages' => array(
                            Zend_Validate_Callback::INVALID_VALUE => $this->texts->validators->notinarray,
                        ))),
                    ),
                ));
                return $form;

    		case 'texts':
    			
    			$form = parent::getForm($name);

    			// define js functions
    			foreach ($this->_js_functions as $func_name => $arr) {
	    			Ibulletin_Js::addPlainCode($arr['code'], true);
    			}

    			// recursive generate subforms based on multi-array of texts for default language
    			$res = $this->generateSubForms($this->_textStructure, $form);

                return $res;
    			
    		default: return null;
    	}
    
    }
    
	/**
	 * generates subforms from multi array
	 * 
	 * @param array $array
	 * @param Zend_Form $form
	 * @param array $parents
	 * @return Zend_Form
	 */
    private function generateSubForms($array, Zend_Form $form, $parents = array()) {
    	
    	$father = end($parents);
		    	    	
    	foreach ($array as $key => $value) {
    		$group = array_merge($parents, array($key));
    		
    		if ($father) {
    			$subform = $form->getSubForm($father);    			
    			if (!$subform instanceof Form_SubForm) {
    				$subform = new Form_SubForm();   				
    				$form->addSubForm($subform, $father);
    			}
    		} else {
    			$subform = $form;
    		}
    		
    		// recurse if necessary
    		if (!is_array($value)) {    			
    			$this->createElements($subform, $key, $group);
    		} else {
    			$this->generateSubForms($value, $subform, $group);
    		}
    		
    	}

    	return $form;
    }    
     
    /**
     * create elements for given key 
     * 
     * @param Zend_Form $form
     * @param string $key
     * @param array $group group of keys
     * @return void
     */

    private function createElements(Zend_Form $form, $key, $group) {


        // assigning revert value for text element of current language
        $_group = $group;
        $revert_title  = $this->_textStructure[array_shift($_group)];
        foreach ((array)$_group as $part) {
            $revert_title = $revert_title[$part];
        }

        // assigning default value for text element of default language
        $_group = $group;
        $title = $this->_defaultLanguageTextStructure[array_shift($_group)];
        foreach ((array)$_group as $part) {
            $title = $title[$part];
        }

        $label = implode(' / ', array_map('ucfirst', $group));

    	// textarea element
    	$el = new Zend_Form_Element_Textarea($key, array(
    			'label' => $label,
    			'required' => false,
    			'rows' => 1,
                'grid' => 11,
                'class' => 'span7',
                'spellcheck' => 'false',
                'data-toggle' => 'tooltip',
                'data-revert-title' => $revert_title,
                'title' => $title,

    			//'filters' => array('StringTrim'),
    	));


        // button to revert changes made to element with $key
        $btn_key = 'btn_revert_'.$key;
        $btn = new Zend_Form_Element_Button($btn_key, array(
            'escape' => false,
            'label' => '<span class="icon icon-refresh"></span>',
            'title' => $this->texts->revert_value,
            'class' => 'revertBtn',
            'ignore' => true,
            'required' => false,
        ));

    	$form->addElements(array($el, $btn));

    	$form->addDisplayGroup(
                array($el, $btn), 'button_group_'.$key, array('displayGroupClass' => 'Form_DisplayGroup_Inline')
        );


        $btn->getDecorator('HtmlTag')->setOption('class','span1');
        //$el_default->removeDecorator('label');
        $el->getDecorator('label')->setOption('class','span4')->setOption('style','line-height:30px;');

    	
    }    
    
    /* TODO: implement with custom Zend_Filter(s) for higher reusability */
    
    /**
     * replace characters with HTML code equivalent 
     * @param string $s
     */
    private function charToHtml(&$s) {
    	$s = str_replace($this->_replace_char, $this->_replace_html, $s);
    }
    
    /**
     * inversion function for replacing HTML code to its character equivalent
     * @param string $s
     */
    private function htmlToChar(&$s) {
    	$s = str_replace($this->_replace_html, $this->_replace_char, $s);
    }    

}
