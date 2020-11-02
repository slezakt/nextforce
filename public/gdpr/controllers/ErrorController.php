<?

class ErrorController extends Zend_Controller_Action {

    private static $exception;

    /**
     * displays error page (no exception is needed)
     * route is /error/<code>
     */
    public function indexAction() {
        $this->_forward('e' . $this->_getParam('id', '404'));
    }

    /**
     * called if an exception is catched during dispatch
     */
    public function errorAction() {

        Ibulletin_HtmlHead::addFile('error.css');
		$config = Zend_Registry::get('config');

		$errors = $this->_getParam('error_handler');
        self::$exception = $errors->exception;

		// Clear previous content
		$this->getResponse()->clearBody();

		// prepare view
		$this->view->project_name = $config->general->project_name;
		$this->view->project_mail = $config->general->project_email;

		switch ($errors->type) {
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ROUTE:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                // 404
                $this->_forward('e404');
				break;
			default:
				// Log the exception
				Phc_ErrorLog::warning('Exception', self::$exception);
                // 500
                $this->_forward('e500');
				break;
		}
	}

    public function e404Action() {
        $this->_helper->viewRenderer->setRender('error');
        $this->getResponse()->setRawHeader('HTTP/1.1 404 Not Found');

        $this->view->page_title = Ibulletin_Texts::get('error.404.title');
        Ibulletin_Texts::setActualContext('404');
    }

    public function e500Action() {
        $this->_helper->viewRenderer->setRender('error');
        $this->getResponse()->setRawHeader('HTTP/1.1 500  Internal Server Error');

        $this->view->page_title = Ibulletin_Texts::get('error.500.title');
        Ibulletin_Texts::setActualContext('500');
    }

}

?>