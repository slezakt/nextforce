<?php
/**
 * Ibulletin_DataGrid_Render_ZendView
 *
 * class to provide a Zend View Render Implementation
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Render
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

class Ibulletin_DataGrid_Render_ZendView extends Ibulletin_DataGrid_Render_Abstract
{
	protected $_template = null;

	public function init()
	{
        $this->setTemplate('datagrid.phtml');
	}

	public function setTemplate($templateName)
	{
		$this->_template = $templateName;
		return $this;
	}

	public function getTemplate()
	{
		return $this->_template;
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

        $view->grid = $this->getGrid();
		$view->baseUrl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();

		return $view->render($templateName);
	}
}