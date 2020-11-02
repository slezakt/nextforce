<?php
/**
 * Ibulletin_DataGrid_Pager_Standard
 *
 * class to provide a Pager Standard Object Implementation
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Pager_Abstract
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

class Ibulletin_DataGrid_Pager_Standard extends Ibulletin_DataGrid_Pager_Abstract
{
    protected $_template = null;

    protected $_htmlId = '';

    protected $_title = '%d - %d';

    protected $_range = 7;

    public function init()
    {
        if (!$this->isVisible()) return;

        $this->setTemplate('datagrid_paginator.phtml');
    }

    /**
     * @param $templateName
     * @return Ibulletin_DataGrid_Pager_Standard
     */
    public function setTemplate($templateName)
    {
        $this->_template = $templateName;
        return $this;
    }

    public function getTemplate()
    {
        return $this->_template;
    }

    /**
     * @param $htmlId
     * @return Ibulletin_DataGrid_Pager_Standard
     */
    public function setHtmlId($htmlId) {
        $this->_htmlId = $htmlId;
        return $this;
    }

    /**
     * @return string
     */
    public function getHtmlId() {
        return $this->_htmlId;
    }


    public function getTitle($i)
    {
        return sprintf($this->_title, ((int)$i - 1) * $this->getLimit() + 1, min((int)$i * $this->_limit, $this->_numberRecords));
    }

    public function setTitle($title)
    {
        $this->_title = $title;
    }

    /**
     * method setRange
     *
     * @access public
     * @param $next
     * @return Ibulletin_DataGrid_Pager_Standard
     * @set String Range Length
     */
    public function setRange($length)
    {
        $this->_range = $length;
        return $this;
    }

    /**
     * method getRange
     * @access public
     * @return int
     * @description get range length
     */
    public function getRange()
    {
        return $this->_range;
    }

    /**
     * @return int
     */
    public function getRangeStart()
    {
        return min(max(1, $this->_onPage - $this->_range + 1), $this->_numberPages - $this->_range);
    }

    /**
     * @return int
     */
    public function getRangeEnd()
    {
        return max(min($this->_numberPages, $this->_onPage + $this->_range - 1), $this->_range + 1);
    }


    public function render()
    {

        if (!$templateName = $this->getTemplate()) {
            return '';
        }

        /* @var $viewRenderer Zend_Controller_Action_Helper_ViewRenderer */
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');

        /* @var Zend_View $view */
        $view = clone $viewRenderer->view;

        $view->addScriptPath(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'admin/views/scripts');

        $view->clearVars();

        $view->pager = $this;

        return $view->render($templateName);
    }


}
