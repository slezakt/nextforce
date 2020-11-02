<?php
/**
 * Ibulletin_DataGrid_Pager_Abstract
 *
 * class to provide a Abstract Pager Implementation
 *
  * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Pager
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

abstract class Ibulletin_DataGrid_Pager_Abstract implements Ibulletin_DataGrid_Pager_Interface
{

    /** Current Page Number
     * @access protected
     * data type Int
     */
	protected $_page;

    /** Total number of records
     * @access protected
     * data type Int
     */
    protected $_numberRecords;

    /**
     * number of records per page
     * @access protected
     * data type integer
     */
    protected $_limit;

    /** Total total number of pages
     * @access protected
     * data type Int
     */
    protected $_numberPages;

	/** Interval or Rank of the Pager (Floor Page)
	 * @access protected
	 * data type Int
	 */
	protected $_onPage;


	/**
	 * Constructor
	 *
	 * @access  public
	 */
	public function __construct($_page, $_pageLimit, $_recordsNum)
	{
		$this->setPage($_page)
		->setlimit($_pageLimit)
		->setNumberRecords($_recordsNum)
		->setNumberPages();

        $this->setOnPage();

        $this->init();
	}


    public function init()
    {}

    /**
	 * method setLinksId
	 * @access public
	 * @return Ibulletin_DataGrid_Pager_Abstract
	 * @set String Links Id
	 */
	public function setLinksId($linksId)
	{
		$this->_linksId = $linksId;
		return $this;
	}

	/**
	 * method getLinksId
	 * @access public
	 * @return String
	 * @return String Links Id
	 */
	public function getLinksId()
	{
		return $this->_linksId;
	}

	/**
	 * method setNumberPages
	 * @access private
	 * @return int
	 * @description set total number of pages
	 */
	public function setNumberPages()
	{
		// calculate number of pages
		$this->_numberPages = ceil($this->getNumberRecords()/$this->getLimit());
		return $this;
	}

	/**
	 * method getNumberPages
	 * @access public
	 * @return int
	 * @description return Number Pages
	 */
	public function getNumberPages()
	{
		return $this->_numberPages;
	}

    /**
     * method setPage
     *
     * @access public
     * @param $_page
     * @return int
     * @description set current page
     */
	public function setPage($_page)
	{
		$this->_page = $_page;
		return $this;
	}

	/**
	 * method getPage
	 * @access public
	 * @return int
	 * @description return current page
	 */
	public function getPage()
	{
		return $this->_page;
	}

    /**
     * method getNextPage
     * @access public
     * @return int
     * @description return number of ending page
     */
    public function getNextPage() {
        return min($this->_page + $this->_limit, $this->_numberRecords);
    }

    /**
     * method setlimit
     *
     * @access public
     * @param $_pageLimit
     * @return int
     * @description set Number Per Page
     */
	public function setLimit($_pageLimit)
	{
		$this->_limit = $_pageLimit;
		return $this;
	}

	/**
	 * method getlimit
	 * @access public
	 * @return int
	 * @description return Number Per Page
	 */
	public function getLimit()
	{
		return $this->_limit;
	}

    /**
     * method setNumberRecords
     *
     * @access public
     * @param $_recordsNum
     * @return int
     * @description set total number of records
     */
	public function setNumberRecords($_recordsNum)
	{
		$this->_numberRecords = $_recordsNum;
		return $this;
	}

	/**
	 * method getNumberRecords
	 * @access public
	 * @return int
	 * @description return total number of records
	 */
	public function getNumberRecords()
	{
		return $this->_numberRecords;
	}

	/**
	 * method setOnPage
	 * @access private
	 * @return int
	 * @description calculate the floor page
	 */
	public function setOnPage()
	{
		$this->_onPage = floor($this->getPage() / $this->getLimit()) + 1;
		return $this;
	}

	/**
	 * method getOnPage
	 * @access public
	 * @return int
	 * @description calculate the floor page
	 */
	public function getOnPage()
	{
		return $this->_onPage;
	}

    /**
     * method setPrevious
     *
     * @access public
     * @param $previous
     * @return Ibulletin_DataGrid_Pager_Abstract
     * @set String Previous Page
     */
	public function setPrevious($previous)
	{
		$this->_previous = $previous;
		return $this;
	}

	/**
	 * method getPrevious
	 * @access public
	 * @return String
	 * @return String Previous Page
	 */
	public function getPrevious()
	{
		return $this->_previous;
	}

    /**
     * is pager visible
     * @return bool
     */
    public function isVisible() {
        return !($this->getNumberPages() == 1 || !$this->getNumberRecords());
    }
    /**
     * method getLink
     *
     * @access public
     * @param null $page
     * @param bool $emptyQuery
     * @return string
     * @description filter and return the URL and query string
     */
	public function getLink($page = null, $emptyQuery = false)
	{
		return Zend_Controller_Action_HelperBroker::getStaticHelper('url')->url(array( 'page' => $page === 0? null : $page ), null, $emptyQuery);
	}


    /**
     * @return string
     */
    public function render() {
        throw new NotImplementedException();
    }

    /**
     * Serialize as string
     *
     * Proxies to {@link render()}.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            $return = $this->render();
            return $return;
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        return '';
    }

}

