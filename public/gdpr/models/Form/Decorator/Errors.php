<?php
/**
 * Trida upravuje Errors Decorator dle formatu Twitter Bootstrap
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Form_Decorator_Errors extends Zend_Form_Decorator_Errors
{
    
    public function render($content)
    {       
        $errors = $this->getElement()->getMessages();      
        $placement = $this->getPlacement();
        $separator = $this->getSeparator();
        $output = "";
        
        if ($errors) {
            $output .= '<span class="help-inline">';
            $output .= implode('<br />',$errors);
            $output .= '</span>';
        }
        
        switch ($placement) {
            case 'PREPEND':
                return $output . $separator . $content;
            case 'APPEND':
            default:
                return $content . $separator . $output;
        }
    }
}

?>
