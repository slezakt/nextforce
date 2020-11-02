<?php
/**
 * iBulletin - View/Getbox.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

/**
 * View helper ktery umoznuje v templatech pridavat dynamicke obsahy podle jmena.
 * Pouziva Boxes::getContent().
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_View_Helper_Getbox{

    /**
     * @var Zend_View Instance
     */
    public $view;
    
    /**
     * Pro prsitup k metodam v objektu view
     * 
     * @param string $name The helper name.
     * @param array $args The parameters for the helper.
     * @return string The result of the helper output.
     */
    public function __call($name, $args)
    {
        // call the helper method
        return call_user_func_array(
            array($this->view, $name),
            $args
        );
    }
    
    /**
     * Pro pristup k promennym objektu view, jako by byly v tomto objetku,
     * diky tomu je mozne provest eval jako bychom kod provadeli uvnitr view scriptu
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->view->$name;
    }

    /**
     * Vrati podle zadaneho jmena boxu jeho aktualni obsah, pripadne s oheldem na
     * casovy posun nastaveny pomoci timeshift controlleru.
     *
     * @access public
     *
     * @param  string Jmeno boxu (atribut name v tabulce boxes).
     * @param boolean potlaceni vyhozeni vyjimky v pripade nenalezeni boxu, default false
     * @return string HTML aktualni verze boxu s prelozenymi znackami.
     */
    public function getbox($name, $suppress_exception = false)
    {
        $currentBulletin = $this->__get('bulletinRow');
        if(empty($name)) {
            return '';
        }
        try {
        	$content = Boxes::getContent($name, $suppress_exception, false, $currentBulletin['id']);
        	/*return*/ eval('?>' . $content);
        } catch (Exception $e) {
            Phc_ErrorLog::warning('Ibulletin_View_Helper_Getbox::getbox()', 
                "Cannot render dynamic box name: \"${name}\". Original exception:\n".$e);
        	
        	return '';
        }
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