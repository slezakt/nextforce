<?php
/**
 * Ibulletin_DataGrid_Abstract
 * 
 * It abstract class to provide Grid and Pager Interface
 * from a data source: Data Base Table, SQL Query, Array
 * 
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


abstract class Ibulletin_DataGrid_Abstract implements Ibulletin_DataGrid_Abstract_Interface, Countable, IteratorAggregate
{

    /**
     * Total number of records on page
     * @var int
     */
    protected $_total = null;

    /**
	 * Loading state flag
	 * @var bool
	 */
	protected $_isCollectionLoaded;

	/**
     * Datasource object
	 * @var Ibulletin_DataGrid_DataSource_Interface
	 */
	protected $_datasource = null;

    /**
     * @var Ibulletin_DataGrid_DataSource_Interface
     */
    protected $_export_datasource = null;

	/**
	 * Number of records per page
	 * @var int
	 */
	protected $_limit;

	/** Total number of records
	 * @var int
	 */
	protected $_numberRecords = null;

	/** Current Page Number
	 * @var int
	 */
	protected $_page;

    /**
     * Default number of records per page
     * @var int
     */
    protected $_defaultLimit = 20;

	/**
	 * Array of records.
	 * @var array
	 */
	protected $_recordSet = array();

	public function init()
	{}

    /**
     * @return Ibulletin_DataGrid_DataSource_Interface
     */
    public function getSelect()
	{
		return $this->getDataSource()->getSelect();
	}

	public function setSelect($select)
	{
		$this->getDataSource()->setSelect($select);
		return $this;
	}

	public function setPage($page)
	{
		$this->_page = $page;
		return $this;
	}

	public function getPage()
	{
		return $this->_page;
	}

	public function setLimit($limit = null)
	{
		$limit = ($limit !== null)? $limit: $this->_defaultLimit;

		if( !is_int($limit) && ($limit < 0) ) {
			throw new Ibulletin_DataGrid_Abstract_Exception('Invalid number of records ' . $limit);
		}

		$this->_limit = $limit;
		return $this;
	}

	public function getLimit()
	{
		return $this->_limit;
	}

	public function setTotal($total)
	{
		$this->_total = $total;
		return $this;
	}

	public function getTotal()
	{
		return $this->_total;
	}

	/**
	 * Retrieve collection loading status
	 *
	 * @return bool
	 */
	public function isLoaded()
	{
		return $this->_isCollectionLoaded;
	}

	/**
	 * Set collection loading status flag
	 *
	 * @param bool $flag
	 * @return Ibulletin_DataGrid_Abstract
	 */
	protected function _setIsLoaded($flag = true)
	{
		$this->_isCollectionLoaded = $flag;
		return $this;
	}

    /**
     * @param mixed|Ibulletin_DataGrid_DataSource_Interface|string|array|Zend_Db_Table|Zend_Db_Select $dataSource
     * @return Ibulletin_DataGrid_Abstract
     */
    public function setDataSource($dataSource)
	{
        if ($dataSource instanceof Zend_Db_Table_Abstract) {                               // Zend_Db_Table_Abstract
            $dataSource = new Ibulletin_DataGrid_DataSource_Table($dataSource);
        } elseif ($dataSource instanceof Zend_Db_Select) {                                 // Zend_Db_Select
            $dataSource = new Ibulletin_DataGrid_DataSource_DbSelect($dataSource);
        } elseif (is_array($dataSource)) {                                                 // Array
            $dataSource = new Ibulletin_DataGrid_DataSource_Array($dataSource);
        }
        if ($dataSource instanceof Ibulletin_DataGrid_DataSource_Interface) {              // Ibulletin_DataGrid_DataSource_Interface
            $this->_datasource = $dataSource;
        } else {
            throw new InvalidArgumentException('Datasource must be either Zend_Db_Table, Zend_Db_Select,
            array or Ibulletin_DataGrid_DataSource_Interface. Invalid type given: '.gettype($dataSource));
        }
		return $this;
	}

    /**
     * @return Ibulletin_DataGrid_DataSource_Interface|null
     */
    public function getDataSource()
    {
        return $this->_datasource;
    }

    /**
     * @param mixed|Ibulletin_DataGrid_DataSource_Interface|string|array|Zend_Db_Table|Zend_Db_Select $dataSource
     * @return Ibulletin_DataGrid_Abstract
     */
    public function setExportDataSource($dataSource)
    {
        if ($dataSource instanceof Zend_Db_Table_Abstract) {                               // Zend_Db_Table_Abstract
            $dataSource = new Ibulletin_DataGrid_DataSource_Table($dataSource);
        } elseif ($dataSource instanceof Zend_Db_Select) {                                 // Zend_Db_Select
            $dataSource = new Ibulletin_DataGrid_DataSource_DbSelect($dataSource);
        } elseif (is_array($dataSource)) {                                                 // Array
            $dataSource = new Ibulletin_DataGrid_DataSource_Array($dataSource);
        }
        if ($dataSource instanceof Ibulletin_DataGrid_DataSource_Interface) {              // Ibulletin_DataGrid_DataSource_Interface
            $this->_export_datasource = $dataSource;
        } else {
            throw new InvalidArgumentException('Datasource must be either Zend_Db_Table, Zend_Db_Select,
            array or Ibulletin_DataGrid_DataSource_Interface. Invalid type given: '.gettype($dataSource));
        }
        return $this;
    }

    /**
     * @return Ibulletin_DataGrid_DataSource_Interface|null
     */
    public function getExportDataSource()
    {
        return $this->_export_datasource;
    }

	/**
	 * method getNumberRecords
	 * @access public
	 * @return int
	 * @description return total number of records
	 */
	public function getNumberRecords() {
		if(null === $this->_numberRecords){
			$this->setNumberRecords();
		}

		return $this->_numberRecords;
	}

	/**
	 * method setNumberRecords
	 * @access private
	 * @return int
     * @throws Ibulletin_DataGrid_Abstract_Exception
	 * @description calculate total number of records
	 */
	protected function setNumberRecords($source = null) {
		if (!$source) $source = $this->getDataSource();
        if(null === $this->_numberRecords){
			if (null !== $source) {
				$this->_numberRecords = $source->count();
			} else {
				throw new Ibulletin_DataGrid_Abstract_Exception("Cannot fetch data: no datasource driver loaded.");
			}
		}
	}

    /**
     * @return Ibulletin_DataGrid_Abstract
     * @throws Ibulletin_DataGrid_Abstract_Exception
     */
    public function fetch()
	{
		try{
			$this->_fetch();
		} catch (Exception $e) {
			throw new Ibulletin_DataGrid_Abstract_Exception("Message Exception: " . $e->getMessage() . "\n");
		}
		return $this;
	}

    /**
     * @param Ibulletin_DataGrid_DataSource_Interface $source
     * @return Ibulletin_DataGrid_Abstract
     * @throws Ibulletin_DataGrid_Abstract_Exception
     */
    public function bindDataSource(Ibulletin_DataGrid_DataSource_Interface $source)
	{
		$this->setDataSource($source);

		try{
			$this->_fetch();
		} catch (Exception $e) {
			throw new Ibulletin_DataGrid_Abstract_Exception("Message Exception: " . $e->getMessage() . "\n");
		}
		return $this;
	}

    /**
     * @return int|null
     */
    public function count()
	{
		return (null === $this->getTotal())? 0: $this->getTotal();
	}

    /**
     * @return ArrayIterator
     */
    public function getIterator()
	{
		if($this->getTotal() === null){
			$this->_fetch();
		}

		return new ArrayIterator($this->_recordSet);
	}

    /**
     * @param null $adapterName
     * @return Ibulletin_DataGrid_Pager_Abstract
     */
    public function getPager($adapterName = null)
	{
		return Ibulletin_DataGrid_Pager::factory($adapterName, $this->getPage(), $this->getLimit(), $this->getNumberRecords());
	}

    /**
     * @param null $adapterName
     * @return mixed
     */
    public function renderPager($adapterName = null)
	{
		return $this->getPager($adapterName)->render();
	}

    /**
     * Render block
     *
     * @param null $adapterName
     * @return string
     */
	public function render($adapterName = null)
	{
		if($this->getTotal() === null){
			$this->_fetch();
		}

		return $this->_render($adapterName = null);
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

	abstract protected function _fetch();

	abstract protected function _render($adapterName = null);
}
