<?php
/**
 * Phc_View je proste rozsireni Zend_view o vypnuti logovani E_NOTICE ve chvili, kdy je 
 * spusten view script - tedy template, timto je predejito zbytecnym hlaskam o 
 * neexistujicich prvcich poli a podobne.
 *
 * @author Bc. Petr Skoda
 */

/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2008 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */


/**
 * Abstract master class for extension.
 */
require_once 'Zend/View/Abstract.php';


/**
 * Concrete class for handling view scripts.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2008 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Phc_View extends Zend_View_Abstract
{

    /**
     * Includes the view script in a scope with only public $this variables.
     * 
     * Navic nastavi error_reporting na E_PARSE.
     *
     * @param string The view script to execute.
     */
    protected function _run()
    {
        // Vypneme pro vystupni skript logovani E_NOTICE
        error_reporting(E_ALL & ~E_NOTICE);

        include func_get_arg(0);
    }
    
    /**
     * Nastavi error_reporting na E_PARSE a spusti render ze Zend_View_Abstract
     *
     */
   
}
