<?php
/**
 * Trida prizbusobuje decorator label check a radioboxum
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Form_Decorator_Label extends Zend_Form_Decorator_Label {
    
    public function render($content) {
        
        $element = $this->getElement();
        $view = $element->getView();
        if (null === $view) {
            return $content;
        }

        $label     = $this->getLabel();
        $separator = $this->getSeparator();
        $placement = $this->getPlacement();
        $tag       = $this->getTag();
        $tagClass  = $this->getTagClass();
        $id        = $this->getId();
        $class     = $this->getClass();
        $options   = $this->getOptions();
                
        //úprava label checkboxu a radia pro twitter bootstrap
        if($element instanceof Zend_Form_Element_Checkbox) {            
            if ($class) {
                $class = $class.' checkbox';
            } else {
                $class = 'checkbox';
            }            
            return '<label class="'.$class.'" for="'.$element->getFullyQualifiedName().'">'.$content.$label.'</label>';
        }
        
        if($element instanceof Zend_Form_Element_Radio) {            
            if ($class) {
                $class = $class.' radio';
            } else {
                $class = 'radio';
            }            
            return '<label class="'.$class.'" for="'.$element->getFullyQualifiedName().'">'.$content.$label.'</label>';
        }


        if (empty($label) && empty($tag)) {
            return $content;
        }

        if (!empty($label)) {
            $options['class'] = $class;
            $label = $view->formLabel($element->getFullyQualifiedName(), trim($label), $options);
        } else {
            // PHC HACK
        	//$label = '&#160;';
        	$label='';
        }

        if (null !== $tag) {
            require_once 'Zend/Form/Decorator/HtmlTag.php';
            $decorator = new Zend_Form_Decorator_HtmlTag();
            if (null !== $this->_tagClass) {
                $decorator->setOptions(array('tag'   => $tag,
                                             'id'    => $id . '-label',
                                             'class' => $tagClass));
            } else {
                $decorator->setOptions(array('tag'   => $tag,
                                             'id'    => $id . '-label'));
            }

            $label = $decorator->render($label);
        }

        switch ($placement) {
            case self::APPEND:
                return $content . $separator . $label;
            case self::PREPEND:
                return $label . $separator . $content;
        }
        
    }
}

?>
