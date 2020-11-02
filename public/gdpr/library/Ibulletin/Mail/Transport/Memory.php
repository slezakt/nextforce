<?php
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
 * @package    Zend_Mail
 * @subpackage Transport
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @see Zend_Mail_Transport_Abstract
 */
require_once 'Zend/Mail/Transport/Abstract.php';


/**
 * Memory transport
 *
 * Class for saving outgoing emails in variable
 *
 * @category   Zend
 * @package    Zend_Mail
 * @subpackage Transport
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Ibulletin_Mail_Transport_Memory extends Zend_Mail_Transport_Abstract
{
    /**
     * Target variable for saving sent email message
     *
     * @var string
     */
    protected $_message;

    /**
     * Saves e-mail message to a variable
     *
     * @return void
     */
    protected function _sendMail()
    {
        $this->_message = $this->header . $this->EOL . $this->body;
    }
    
    /**
     * Returns raw mail message as string with full headers and body content
     * 
     *  @return string
     */
    public function getRawMessage() 
    {
    	return $this->_message;	
    }

}
