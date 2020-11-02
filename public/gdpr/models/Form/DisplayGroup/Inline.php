<?php
/**
 * Trida definuje Form Display Group pro umisteni elementu v radku
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Form_DisplayGroup_Inline extends Zend_Form_DisplayGroup {
    
   
    public function loadDefaultDecorators() {
     
       if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

       $decorators = $this->getDecorators();       
      
        if (empty($decorators)) {
            $this->addDecorator('FormElements')
                 ->addDecorator('HtmlTag', array('tag' => 'div','class'=>'controls controls-row'))
                 ->addDecorator('Fieldset',array('class' => 'inline'));             
        }
        return $this;
    }
    
    public function addElement(Zend_Form_Element $element) {
       //grid rozsiruje element o atribut grid 1-12 tzn. jakou sirku zabere dle http://twitter.github.io/bootstrap/scaffolding.html#gridSystem 
       $grid = $element->getAttrib('grid');       
       $element->setAttrib('grid', null);       
                     
       $dec = $element->getDecorator('HtmlTag');           
       if ($dec) $dec->setOption('class','span'.$grid);
          
       parent::addElement($element);
    }
    
    
    
 
}

?>
