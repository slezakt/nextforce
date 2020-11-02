<?php
/**
 * Trida pro sub formy
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Form_SubForm extends Form {
    
    protected $_isArray = true;
   
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }
        $this->addPrefixPath('Form_Decorator','Form/Decorator/','decorator');
  
        $decorators = $this->getDecorators();
        if (empty($decorators)) {
            $this->addDecorator('FormElements')                 
                 ->addDecorator('Fieldset');              
        }
        return $this;
    }

   
    
}

?>
