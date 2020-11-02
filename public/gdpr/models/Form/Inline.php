<?php
/**
 * Trida rozsiruje zakladni form o "inline" verzi formu dle Twitter Bootstrap
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Form_Inline extends Form {
    
    public static $_default_form_inline_decorator = array(
                array('FormErrors',array(
                    'markupListStart' => '<div class="alert alert-error">',
                    'markupListEnd' => '</div>',
                    'markupElementLabelStart'=> '<strong>',                    
                    'markupElementLabelEnd' => '</strong>',
                    'markupListItemStart' => '<div>',
                    'markupListItemEnd' => '</div>'
                )), 
                'Description',
                'FormElements',
                'Form'
    );
       
       
    public function __construct($options = null) {
        parent::__construct($options); 
    }
    
    public function init() {
        parent::init();
        $this->setAttrib('class','form-inline');
        $this->_form_inline = true;
    }       
    
    public function loadDefaultDecorators() {

        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

        $decorators = $this->getDecorators();
        
        if (empty($decorators)) {
            $this->setDecorators(self::$_default_form_inline_decorator);
        }

        return $this;
    }

          
}

?>
