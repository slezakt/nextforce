<?php
/**
 * Trida prida decorator pro modal TwB
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Form_Decorator_ModalBody extends Zend_Form_Decorator_Abstract
{
    
    public function render($content)
    {       
        return '<div class="modal-body">'.$content.'</div>';
    }
}

?>
