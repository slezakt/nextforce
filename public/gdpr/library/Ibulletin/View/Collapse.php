<?php
/**
 * iBulletin - View/Collapse.php
 *
 * @author Ondrej Blaha, <ondra@blahait.cz>
 * 
 * View helper sestavi collapse dle http://twitter.github.io/bootstrap/2.3.2/javascript.html#collapse
 *  
 * 
 */

class Ibulletin_View_Helper_Collapse extends Zend_View_Helper_Abstract {

    /**
     * Sestavuje collapse
     * 
     * @param object $help - collapse z helpu v textech, format v textech %action%.help.%nazev%.title="",%action%.help.%nazev%.body=""
     * @param array $collapse  - collapse se připojuje k predchozimu helpu, format pole array(nazev=>array('body'=>'','title'=>''),nazev2=>...)
     * @param array $show  - pole s poradovaci cisly collapse ktere budou defaultně otevreny (1,2)
     * @param array $color - pole s RGB(A) barvami collapse boxu (%poradove cislo%=>%barva%,....)
     * @return string
     */
    public function Collapse($help,$collapse=null,$show=null,$color=null) {    
        
        $accordion = "";
        $i = 1;
        if (is_object($help)) {
            foreach ($help as $h) {
                $b = '';
                $s = false;                
                if (is_array($show) && in_array($i,$show)) $s=true;
                if (is_array($color)) $b=$color[$i];
                $accordion .= $this->getAccordion($h->title,$this->parseTags($h->body), $s, $b, $i++);               
            }
        }
        
        $s = false;
       
        if (is_array($collapse)) {
            foreach ($collapse as $c) {
                if (is_array($c))
                    $b = '';
                    $s = false;
                    if (is_array($show) && in_array($i,$show)) $s=true;
                    if (is_array($color)) $b=$color[$i];
                    $accordion .= $this->getAccordion($c['title'], $c['body'], $s, $b, $i++);
            }
        }
        
        return '<div class="accordion">'.$accordion.'</div>';        
    }
    
    
    private function getAccordion($title,$body,$show,$color,$position) {
        
        $front = Zend_Controller_Front::getInstance();
        $controller = $front->getRequest()->getControllerName();
        $action = str_replace('-','',$front->getRequest()->getActionName());
        
        $aid = 'cop-'.$controller.'-'.$action.'-'.$position;
        
        if (isset($_COOKIE['collapse'])) {
            $colset = json_decode($_COOKIE['collapse']);
            if (isset($colset->$aid)) $show = true;
        }

        $direct = 'down';
        $dbg = " accordion-box";

        $s = '';

        if($show) {
            $s = ' in';
            $direct = 'up';
        }
        if ($color) {
            $dbg = '';
            $color = ' style = "background-color:'.$color.';"';
        }
        
        return '<div class="accordion-group"><div' . $color . ' class="accordion-heading' . $dbg . '">
                <a class="accordion-toggle" data-toggle="collapse" href="#'.$aid.'"><i class=" icon-chevron-'.$direct.'"></i>
                ' . $title . '</a></div><div id="'.$aid.'" class="accordion-body collapse' . $s . '">
                <div class="accordion-inner">' . $body . '</div></div></div>';
    }
    
    /**
     * Nahradi tagy v textu napovedy
     * @param type $str vstupni text
     * @return type
     */
    private function parseTags($str) {

        //tags baseUrl
        $tag = '%%baseUrl%%';
        if (strpos($str,$tag)) $str = str_replace($tag, Zend_Controller_Front::getInstance()->getBaseUrl(), $str);
        return $str;
    }

}

?>
