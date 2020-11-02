<?php

/**
 * Pouze zaridi logoff, ten automaticky presmeruje na login page.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Admin_LogoffController extends Zend_Controller_Action
{
    public function indexAction()
    {
        Ibulletin_AdminAuth::logoff();
    }
}
