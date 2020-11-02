<?php
/**
 * Formulare pro inBox prizpusobene pro Twitter Bootstrap
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Form extends Zend_Form {
    
    protected $_form_inline = false;
    
    //defaultni dekoratory
    private $_default_form_decorator = array(
                'Description',
                'FormElements',
                'Form'
    );
           
    private $_default_element_decorator = array(
            'ViewHelper',
            'Label',
            'Errors',
            array('Description',array('tag'=>'span','class'=>'help-block')),
            array('HtmlTag', array('tag' => 'div'))
    );
    
    private $_file_element_decorator = array(
            'File',
            'Label',
            'Errors',
            array('HtmlTag', array('tag' => 'div'))
    );
    
    public function __construct($options = null) {
        parent::__construct($options); 
    }
    
    public function init() {
        parent::init();
         $this->addElementPrefixPath('Form_Decorator','Form/Decorator/','decorator');          
    }
    
     public function loadDefaultDecorators() {

        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

        $decorators = $this->getDecorators();

        if (empty($decorators)) {
            $this->setDecorators($this->_default_form_decorator);           
        }
        return $this;
    }
    
    /**
     * Metoda prirazuje elementu decorator dle typu elementu
     * @param type $element Zend_Form_Element
     */
    private function assignDecorator($element) {        
                
        switch (get_class($element)) {
            case 'Zend_Form_Element_File':
                $element->setDecorators($this->_file_element_decorator);
                break;
            case 'Zend_Form_Element_Button':
            case 'Zend_Form_Element_Submit':
                $element->setDecorators($this->_default_element_decorator);
                $element->removeDecorator('Label')
                        ->setIgnore(true);
                $css_class = $element->getAttrib('class');

                if ($css_class) {
                    $element->setAttrib('class', 'btn ' . $css_class);
                } else {
                    $element->setAttrib('class', 'btn');
                }               
                break;
            case 'Zend_Form_Element_Multiselect':
            case 'Zend_Form_Element_Select':
                $element->setDecorators($this->_default_element_decorator);
                $css_class = $element->getAttrib('class');
                //bez select pickeru
                if ($element->getAttrib('disable-picker')) {
                   break; 
                }
                
                if ($css_class) {
                    $element->setAttrib('class', 'selectpicker ' . $css_class);
                } else {
                    $element->setAttrib('class', 'selectpicker');
                }
                break;
            default:
                if ($element) $element->setDecorators($this->_default_element_decorator);
                break;
        }
        
        //V pripade radkoveho formulare odebere decorator Errors (je nahrazen FormErrors) a wrap HtmlTag
        if ($this->_form_inline) {
            $element->removeDecorator('errors');
            $element->removeDecorator('htmltag');
        }
    }
    
    public function addElements(array $elements) {
        foreach ($elements as $el) {           
           $this->assignDecorator($el);            
        }
       parent::addElements($elements);
    }
    
    public function addElement($element, $name = null, $options = null) {          
        
        parent::addElement($element, $name, $options);
        
        if (!is_string($element)) {
            $this->assignDecorator($element);
        } else {
            $el = $this->getElement($name);
            $this->assignDecorator($el);
        }
    }
    
    /**
     * Metoda nastavuje druh formulare radkovy nebo verikalni a upravi decoratory
     * @param type $bool Boolean
     */
    public function setFormInline($bool) {
                      
        $this->_form_inline = $bool;
        
        foreach($this->getElements() as $element) {
            $this->assignDecorator($element);
        }
        
        if ($this->_form_inline) {           
            $this->setDecorators(Form_Inline::$_default_form_inline_decorator);
            $this->setAttrib('class', 'form-inline');
        } else { 
            //vertikalni formular nepouziva decorator FormErrors a odebere css class formu
            $this->removeDecorator('FormErrors');
            $this->removeAttrib('class');
        }
    }     
  
       
}

?>
