<?php

/**
 * Normalizace cesty ve filesystemu.
 * @see Zend_Filter_Interface
 */
require_once 'Zend/Filter/Interface.php';


/**
 * @category   Ibulletin
 * @package    Ibulletin_Filter
 * @copyright  Petr Skoda
 */
class Ibulletin_Filter_NormPath implements Zend_Filter_Interface
{
    /**
     * Varci normalizovanou cestu bez zdvojenych lomitek '//'
     *
     * @param  string $path
     * @return string
     */
    public function filter($path)
    {
        $separators = array('/','\\');
        foreach($separators as $s){
            $path = preg_replace('/\\'.$s.'\\'.$s.'/', $s, $path);
            //$path = preg_replace('/'.$s.'$/', '', $path) . $s;
        }
     
        return $path;
    }
}
