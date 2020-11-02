<?php
/**
 * Trida definuje Form Display Group pro foot modalniho okna Twitter Bootstrap
 * http://twitter.github.io/bootstrap/javascript.html#modals
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Form_DisplayGroup_ModalFoot extends Zend_Form_DisplayGroup {
    
       
    public function loadDefaultDecorators() {
       
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

       $decorators = $this->getDecorators();       
      
       foreach ($this->getElements() as $element) {
            $element->removeDecorator('Label');
            $element->removeDecorator('HtmlTag');
       }
        
        if (empty($decorators)) {
            $this->addDecorator('FormElements')
                 ->addDecorator('HtmlTag', array('tag' => 'div','class'=>'modal-footer'));                        
        }
        return $this;
    }
}

?>
