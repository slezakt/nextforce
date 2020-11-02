<?php
/**
 * iBulletin - View/Jsid.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

/**
 * View helper ktery umoznuje v templatech snadno pridavat id elementum
 * kterych te tykaji ruzne JS akce.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_View_Helper_Jsid {

    /**
     * @var Zend_View Instance
     */
    public $view;

    /**
     * Vrati podle zadaneho klice odpovidajici ID pro element.
     *
     * @access public
     *
     * @param  string Klic pro ID
     * @return string ID pro element
     */
    public function jsid($key)
    {
        if(empty($key)) {
            return "";
        }
        
        return Ibulletin_Js::getId($key);
    }

    /**
     * Set the view object
     *
     * @param Zend_View_Interface $view
     * @return void
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
}
