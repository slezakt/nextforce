<?php
/**
 * Ibulletin_DataGrid
 *
 * class to provide a Grid that renders data in html table
 * from data source: Zend_Db_Select, Zend_Db_Table, SQL query string, Array
 * 
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

class Ibulletin_DataGrid extends Ibulletin_DataGrid_Abstract implements Ibulletin_DataGrid_Interface
{
    /**
     * @var Ibulletin_DataGrid_Column[]
     */
    protected $_columns = array();

	protected $_lastColumnId;

    protected $_firstColumnId;

	protected $_defaultSort = null;

	protected $_defaultDir = 'desc';

	/** Current order
	 * @access protected
	 * data type String
	 */
	protected $order = null;

	/** Current direction
	 * @access protected
	 * data type String
	 */
	protected $direction = null;

	protected $_sortable = true;

    protected $_exportable = true;

    protected $_storeFilters = false;

    protected $_inputHitlist = array();

    protected $_inputTerm = '';

    protected $_inputFilters = array();

    protected $_defaultFilters = array();

    /**
     * @var Ibulletin_DataGrid_Filter_Abstract[]
     */
    protected $filters = array();

	/**
	 * Empty grid text
	 *
	 * @var sting|null
	 */
	protected $_emptyText;

	/**
	 * Empty grid text CSS class
	 *
	 * @var sting|null
	 */
	protected $_emptyTextCss = 'empty-text';

	protected $_generateColumns = true;

	protected $_idFieldName = null;

    /**
     * Data Grid constructor
     *
     * @access public
     * @param array|Ibulletin_DataGrid_DataSource_Interface|null|string|Zend_Db_Select|Zend_Db_Table_Abstract $dataSource
     * @param                                                                                                 int limit
     * @param array                                                                                           $_params
     * @throws Ibulletin_DataGrid_Exception
     * @internal param array|\Ibulletin_DataGrid_DataSource_Interface|string|\Zend_Db_Select|\Zend_Db_Table_Abstract $dataSource
     * @internal param \params $array
     */

	public function __construct($dataSource = null, $limit = null, array $_params = array())
	{
        $this->setLimit($limit);

		$this->_emptyText = 'No records found.';

        $fc = Zend_Controller_Front::getInstance();
        
        if(empty($_params)){            
            $_params = $fc->getRequest()->getParams();          
		}

        if (isset($_params['action'])) unset($_params['action']);
        if (isset($_params['controller'])) unset($_params['controller']);
        if (isset($_params['module'])) unset($_params['module']);

        // POST REDIRECT GET
        if ($fc->getRequest()->isPost() && isset($_params['filter'])) {
            //destroy filters
            if ($_params['filter'] === 'destroy') {
                // resets filters and sort
                foreach ($_params as $k => $v) {
                    if ((strpos($k, 'f_') === 0) ||
                        (strpos($k, 'direction') === 0) ||
                        (strpos($k, 'orderBy') === 0)) {
                        unset($_params[$k]);
                    }
                }

            // clean session filter and sort data
            $this->unsetSession('filters');
            $this->unsetSession('sort');

            } else {
                // set filter
                foreach ($_params as $k => $v) {
                    // owerwrite with POST value (if present)
                    $new_value = $fc->getRequest()->getPost($k, null);
                    if ($new_value !== null) {
                        // convert arrays to delimited string
                        if (is_array($new_value) && count($new_value) > 0) {
                            // eliminate empty string values
                            foreach ($new_value as $_k =>$i) {
                                if (empty($i)) unset($new_value[$_k]);
                            }
                            // if the array remained empty, unset this filter
                            if (empty($new_value)) { unset($_params[$k]); continue;}

                            // glue array with delimiter
                            $new_value = implode('|', $new_value);
                        }

                        $new_value = urlencode($new_value); // encode value in URL
                        $_params[$k] = $new_value;
                    }
                    // clean empty filters
                    if (strval($_params[$k]) === '' || $k === 'filter') {
                        unset($_params[$k]);
                    }
                }
            }

            // reset paging
            if (isset($_params['page'])) unset($_params['page']);
            // reset submit
            if (isset($_params['filter'])) unset($_params['filter']);


            // redirect
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoSimpleAndExit($fc->getRequest()->getActionName(),
                $fc->getRequest()->getControllerName(),
                $fc->getRequest()->getModuleName(),
                $_params
            );
        }

        // sanitize input (excluding filter keys - processed after)
        $filters = array(
            'orderBy'     => 'StripTags',
            'direction'   => 'alpha',
            'hitlist'     => 'StripTags',
            'term'        => 'StripTags',
            'page'   	  => 'digits',
        );

        $valids = array(
            'orderBy'     => array('allowEmpty' => true),
            'direction'   => array('Alpha', 'allowEmpty' => true),
            'hitlist'     => array('allowEmpty' => false),
            'term'        => array('allowEmpty' => false),
            'page'        => array('int', 'default' => 0),
        );

		$input = new Zend_Filter_Input($filters, $valids, $_params);

		if (!$input->isValid()) {
			$errors = '';

			foreach ($input->getMessages() as $messageId => $messages) {
				$message = current($messages);
				$errors .= "'$messageId': $message\n";
			}

			throw new Ibulletin_DataGrid_Exception('Invalid Params for DataGrid: '.$errors);
		}

		$this->setPage((int)$input->page);
		$this->setOrder( !empty($input->orderBy)? $input->orderBy: null);
		$this->setDirection( !empty($input->direction)? $input->direction: null);
        $this->setInputHitlist(!empty($input->hitlist)? $input->hitlist: null);
        $this->setInputTerm(!empty($input->term)? $input->term: null);

        // search for filter keys in url params
        $filters = array();
        $found = false;
        foreach ($_params as $k => $v) {
            if (substr($k, 0, 2) === 'f_') { // filters have prefix 'f_' in request parameters
                $fkey = substr($k, 2);
                if (strval($v) !== '') {
                    $filters[$fkey] = urldecode($v); // decode encoded value from URL
                    $found = true;
                }
            }
        }

        if ($found) {
            $this->setInputFilters($filters);
        }

		if( null !== $dataSource){
            $this->setDataSource($dataSource);
            $this->setExportDataSource($dataSource);
		}

		$this->init();
	}

    protected function _fetch()
    {

        if ($this->isLoaded()) {
            return $this;
        }

        if (null === $this->getDataSource()) {
            throw new Ibulletin_DataGrid_Exception("Cannot fetch data: no datasource driver loaded.");
        }

        // fetch records
        $this->_recordSet = $this->getDataSource()->fetch($this->getPage(), $this->getLimit(), true);

        $this->setTotal(count($this->_recordSet));
        $this->_setIsLoaded();

    }

    public function addDefaultColumn($source = null)
    {
        // default source
        if (!$source) $source = $this->getDataSource();

        foreach($source->getColumnsMeta() as $column){

            // add column, set header, field, datatype

            $field = (isset($column['alias']) ? $column['alias'] .'.': '') .$column['name'];
            // column
            $this->addColumn($column['name'], array(
                'header' => ucfirst(str_replace('_', '&nbsp;', $column['name'])),
                'field' => $field,
            ));
            // filter
            if ($column['alias']) {
                $this->addFilter($column['name'],array(
                    'datatype' => $column['datatype'],
                    'field' => $field
                ));
            }

        }
    }

    /**
     * Add column to grid
     *
     * @param   string $columnId
     * @param   array|Ibulletin_DataGrid_Column $column
     * @return  Ibulletin_DataGrid
     */
    public function addColumn($columnId, $column)
    {
        if($column instanceof Ibulletin_DataGrid_Column) {
            $col = $column;
        } elseif (is_array($column)) {
            $col = new Ibulletin_DataGrid_Column();
        } else {
            throw new Ibulletin_DataGrid_Exception('Wrong column format');
        }

        $col->setId($columnId);
        $col->setIndex($columnId);
        $col->setGrid($this);

        if (is_array($column)) {
            $col->setData($column);
        }

        if ($col->getHeader() === null) {
            $col->setHeader(ucfirst(str_replace('_', '&nbsp;', $columnId)));
        }

        $this->_columns[$columnId] = $col;

        $this->_lastColumnId = $columnId;
        if (!$this->_firstColumnId) {
            $this->_firstColumnId = $columnId;
        }

        return $this;
    }

    /**
     * Add action to grid
     *
     * @param   string $columnId
     * @param   array $options
     * @return  Ibulletin_DataGrid
     */
    public function addAction($columnId, $options) {
        $column = array(
            'align' => 'center',
            'sortable' => 'false',
            'action' => 'true',
            'type' => 'action',
            'actions' => $options
        );
        return $this->addColumn($columnId, $column);
    }


	public function getGenerateColumns()
	{
		return $this->_generateColumns;
	}

	public function setGenerateColumns($generateColumns)
	{
		$this->_generateColumns = $generateColumns;
		return $this;
	}

	public function setDefaultSort($sort)
	{
		if(is_array($sort)){
			list($sort, $dir) = each($sort);
			$this->setDefaultDir($dir);
		}

		$this->_defaultSort = $sort;
		return $this;
	}

	public function getDefaultSort()
	{
        if (!$this->_defaultSort) {
            $this->_defaultSort =  $this->getIdFieldName();
        }
        return $this->_defaultSort;
	}

	public function setDefaultDir($dir)
	{
		$this->_defaultDir = $dir;
		return $this;
	}

	public function getDefaultDir()
	{
		return $this->_defaultDir;
	}

	public function setOrder($order)
	{
		$this->order = $order;
		return $this;
	}

	public function getOrder()
	{
		return $this->order;
	}

	public function setDirection($direction)
	{
		$this->direction = $direction;
		return $this;
	}

	public function getDirection()
	{
		return $this->direction;
	}

	public function setSortable($sortable)
	{
		$this->_sortable = $sortable;
		return $this;
	}

	public function getSortable()
	{
		return $this->_sortable;
	}

    public function setExportable($exportable)
    {
        $this->_exportable = $exportable;
    }

    public function getExportable()
    {
        return $this->_exportable;
    }

    public function setStoreFilters($storeFilters)
    {
        $this->_storeFilters = $storeFilters;
    }

    public function getStoreFilters()
    {
        return $this->_storeFilters;
    }

    public function getSessionKey() {
        $fc = Zend_Controller_Front::getInstance();
        $request = $fc->getRequest();
        return 'dg_' . $request->getActionName();
    }

    public function storeSession(array $data) {
        $session = Zend_Registry::get('session');
        $session->{$this->getSessionKey()} = array_merge(
            (array)$session->{$this->getSessionKey()},
            (array)$data
        );
    }

    public function restoreSession() {
        $session = Zend_Registry::get('session');
        return $session->{$this->getSessionKey()};
    }

    public function unsetSession($key = null) {
        $session = Zend_Registry::get('session');
        $value = $session->{$this->getSessionKey()};

        if (!empty($value)) {
            if ($key !== null) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    unset($session->{$this->getSessionKey()}[$key]);
                }
            } else {
                unset($session->{$this->getSessionKey()});
            }
        }

        unset($value);

    }

    /**
	 * Set empty text for grid
	 *
	 * @param string $text
	 * @return Ibulletin_DataGrid
	 */
	public function setEmptyText($text)
	{
		$this->_emptyText = $text;
		return $this;
	}

	/**
	 * Return empty text for grid
	 *
	 * @return string
	 */
	public function getEmptyText()
	{
		return $this->_emptyText;
	}

	/**
	 * Set empty text CSS class
	 *
	 * @param string $cssClass
	 * @return Ibulletin_DataGrid
	 */
	public function setEmptyTextClass($cssClass)
	{
		$this->_emptyTextCss = $cssClass;
		return $this;
	}

	/**
	 * Return empty text CSS class
	 *
	 * @return string
	 */
	public function getEmptyTextClass()
	{
		return $this->_emptyTextCss;
	}

	public function getSortIconLink()
	{
		return ($this->getDirection() == 'asc')? '&dArr;':'&uArr;';
	}

	public function getDirLink()
	{
		return ($this->getDirection() == 'asc')? 'desc' : 'asc';
	}

	public function setIdFieldName($fieldName)
	{
		$this->_idFieldName = $fieldName;
		return $this;
	}

	public function getIdFieldName()
	{
		if (!$this->_idFieldName) {                      // first column key guessed
            reset($this->_columns);
            $this->_idFieldName =  key($this->_columns);
        }
        return $this->_idFieldName;
	}

	public function getLastColumnId()
	{
		return $this->_lastColumnId;
	}

    public function getFirstColumnId()
    {
        return $this->_firstColumnId;
    }

	public function getColumnCount()
	{
		return count($this->getColumns());
	}

	/**
	 * Retrieve grid column by column id
	 *
	 * @param   string $columnId
	 * @return  Ibulletin_DataGrid_Column|false
	 */
	public function getColumn($columnId)
	{
		if (!empty($this->_columns[$columnId])) {
			return $this->_columns[$columnId];
		}
		return false;
	}

	/**
	 * Retrieve all grid columns
	 *
	 * @return array
	 */
	public function getColumns()
	{
		return $this->_columns;
	}

	/**
	 * Render block
	 *
	 * @return string
	 */
	protected function _render($adapterName = null)
	{
		if (empty($this->_columns)){
			throw new Ibulletin_DataGrid_Exception("Cannot render columns: the columns are empty.");
		}

		return Ibulletin_DataGrid_Render::factory($this, $adapterName)->render();
	}


    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    protected function setFilters($filters) {
        if (!is_array($filters)) {
            $filters = (array) $filters;
        }
        $this->filters = $filters;
    }

    public function getCustomFilters() {
        $res = array();
        foreach ($this->getFilters() as $k => $filter) {
            if ($filter->getCustom()) {
                $res[$k] = $filter;
            }
        }
        return $res;
    }

    /**
     * @return Ibulletin_DataGrid_Filter_Abstract
     */
    public function getFilter($key)
    {
        $filters = $this->getFilters();
        if (!empty($filters[$key])) {
            return $filters[$key];
        }
        return false;
    }

    /**
     * @param string $key
     * @param array|Ibulletin_DataGrid_Filter_Abstract $filter
     * @throws Ibulletin_DataGrid_Exception
     * @return Ibulletin_DataGrid
     */
    public function addFilter($key, $filter)
    {
        if (empty($this->filters[$key])) {
            if (is_array($filter)) {
                $filter = new Ibulletin_DataGrid_Filter($filter);
            }
            $this->filters[$key] = $filter;
        } else {
            throw new Ibulletin_DataGrid_Exception('Filter with key "'.$key.'" is already defined!');
        }
        return $this;
    }

    /**
     * set array of default values for specific filter keys
     * @param array $defaultFilters
     */
    public function setDefaultFilters($defaultFilters)
    {
        $this->_defaultFilters = $defaultFilters;
    }

    /**
     * @return array
     */
    public function getDefaultFilters()
    {
        return $this->_defaultFilters;
    }

    /**
     * @return array
     */
    public function getInputFilters()
    {
        // logic to sort out non-matching input filters
        $result = array();
        foreach ($this->_inputFilters as $k => $v) {
            if (strlen($v) && ($filter = $this->getFilter($k))) { // filter only found keys and non-empty values
                $result[$k] = $v;
            }
        }

        return $result;//$this->_inputFilters;
    }

    /**
     * @param array $filters
     */
    protected function setInputFilters($filters)
    {
        $this->_inputFilters = $filters;
    }

    public function hasColumnFilters() {
        foreach (array_keys($this->getColumns()) as $k) {
            if ($filter = $this->getFilter($k)) return true;
        }
        return false;
    }

    /**
     * @param string $filters
     */
    public function setInputHitlist($inputHitlist)
    {
        $this->_inputHitlist = $inputHitlist;
    }

    public function getInputHitlist()
    {
        return $this->_inputHitlist;
    }

    public function setInputTerm($inputTerm)
    {
        $this->_inputTerm = $inputTerm;
    }

    public function getInputTerm()
    {
        return $this->_inputTerm;
    }

    public function process()
    {
        $fc = Zend_Controller_Front::getInstance();
        $request = $fc->getRequest();
        $response = $fc->getResponse();

        $format = $request->getParam('export', null);
        $hitlist = $fc->getRequest()->isXmlHttpRequest() && $request->getParam('hitlist') && $request->getParam('term');

        // select source based on presence of export parameter
        $source = $format !== null ? $this->getExportDataSource() : $this->getDataSource();

        //
        // prepare filters for datasources
        //
        if (empty($this->_columns) && true === $this->getGenerateColumns()) {
            $this->addDefaultColumn($source);
        }

        // prepare array of active filters (combine input filters and defined filters)
        $input_filters = $this->getInputFilters();

        //store|restore filters in session
        if ($this->getStoreFilters()) {
            if (empty($input_filters)) { // if data from POST are empty
                $session = $this->restoreSession();
                // apply with last filter from session if no input POST filter data is given
                if (!empty($session['filters'])) { //
                    $input_filters = $session['filters'];
                } else { // otherwise set default filters (and update to session)
                    if (($input_filters = $this->getDefaultFilters())) {;
                        $this->storeSession(array('filters' => $input_filters));
                    }
                }
            } else {
                // update session with newer filter data
                $this->storeSession(array('filters' => $input_filters));
            }
        } else {
            // set default filters on empty input POST filter data
            if (empty($input_filters) && ($df = $this->getDefaultFilters())) {
                $input_filters = $df;
            }
        }


        // extracts filters to be used with fieldname suffix excluding those not used
        $filters = array();
        // create array of filters with their value and fieldname applied
        foreach ($input_filters as $k => $v) {
            $filter = $this->getFilter($k);
          //  dump($input_filters, $k, $filter);exit;

            // for multiple values, explode delimited string to array
            if ($filter->getMultiple()) {
                $v = (array)explode('|', $v);
            }
            // set input value for filter
            $filter->setValue($v);

            // alternative field name (usually table.column)
            ($fieldName = $filter->getField()) ||
                (($column = $this->getColumn($k)) && ($fieldName = $column->getField())) ||
                ($fieldName = $k);

            $filters[$fieldName] = $filter;
        }

        foreach ($this->getFilters() as $k => $f) {
            if (!in_array($k, array_keys((array)$input_filters)) && $f->getDefault() !== NULL) {

                // set default value if not present in input filters and default value is supplied
                $f->setValue($f->getDefault());

                // alternative field name (usually table.column)
                ($fieldName = $f->getField()) ||
                    (($column = $this->getColumn($k)) && ($fieldName = $column->getField())) ||
                    ($fieldName = $k);

                // add to filter chain
                $filters[$fieldName] = $f;
            }

        }
        //var_dump($input_filters,$this->getFilter('deleted'), $filters, in_array('deleted', (array)array_keys($input_filters)));exit;
        // filter records
        $source->filter($filters);

        // AJAX hitlist (find pairs - id, key) from datasource and return JSON array
        if ($hitlist) {

            Env::disableOutput();

            // prepare array of active filters (combine input filters and defined filters)
            if (($filter = $this->getFilter($k = $this->getInputHitlist())) && ($term = $this->getInputTerm())) {
                // alternative field name (usually table.column)
                ($fieldName = $filter->getField()) ||
                    (($column = $this->getColumn($k)) && ($fieldName = $column->getField())) ||
                    ($fieldName = $k);

                $hitlist = (array) $source->hitlist($fieldName, $term);

                $json_helper = new Zend_Json();
                $response->clearAllHeaders();
                $response->setHeader('Content-Type','application/json');
                $response->setBody($json_helper->encode($hitlist));
                $response->sendResponse();
                exit;
            }

            return $this;

        }

        // count records for datasource
        $this->setNumberRecords($source);

        $columnId = null;
        $dir = null;

        //store|restore sorting in session
        if ($this->getStoreFilters()) {
            if (!$this->getOrder()) {
                $session = $this->restoreSession();
                if (!empty($session['sort'])) {
                    $columnId = $session['sort']['field'];
                    $dir = $session['sort']['dir'];
                }
            } else {
                $this->storeSession(array('sort' => array(
                    'field' => ($this->getOrder() !== null)? $this->getOrder(): $this->getDefaultSort(),
                    'dir' => ($this->getDirection() !== null)? $this->getDirection(): $this->getDefaultDir()
                )));
            }
        }


        // prepare for sorting
        $columnId = $columnId ? $columnId : (
            $this->getOrder() !== null ? $this->getOrder():
            $this->getDefaultSort());

        $dir = $dir ? $dir : (
            $this->getDirection() !== null ? $this->getDirection():
            $this->getDefaultDir());

        $dir = strtolower($dir);

        if(empty($columnId)){
            $columnId = $this->getIdFieldName();
        }

        if (isset($this->_columns[$columnId])) {
            $this->_columns[$columnId]->setDir($dir);
        }

        if(!empty($columnId) && !empty($dir)){

            if (!$this->getColumn($columnId)) { // no column found to sort
                // recover, set default values
                $fieldName = $this->getDefaultSort();
                $dir = $this->getDefaultDir();
                //throw new Ibulletin_DataGrid_Exception("Invalid sort column '$columnId'.");
            } else {
                // alternative field name (usually table.column)
                $fieldName = $this->_columns[$columnId]->getField();
            }
            // sort records
            $sortByDirection = $dir;
            if ($source instanceof Ibulletin_DataGrid_DataSource_DbSelect) {
                $sortByField = $fieldName ? $fieldName : $columnId;
            } else {
                $sortByField = $columnId;
            }

            // sort records
            $source->sort($sortByField, $sortByDirection);

        } else {
            throw new Ibulletin_DataGrid_Exception("Cannot sort data: OrderBy or Direction are empty.");
        }

        // set parameters for fetching via call from fetch() in render phase
        $this->setOrder($columnId);
        $this->setDirection($dir);

        // export, we have sorting and filtering already setup
        if ($format) {

            // turn off debugging output
            Env::disableOutput();

            // sanitize parameter export - default csv
            $format = strtolower($format);
            // todo: $source->getAvailableExportTypes()
            $format = in_array($format, array('txt', 'csv', 'xlsx', 'pdf')) ? $format : 'csv';

            // prepare file parameters
            $fparams = array(
                $request->getParam('controller'),
                time(),
            );

            // output to stdout
            $filename = null;

            // generate filename
            $fname = strtolower('export_' . implode('_',array_filter($fparams))) . '.' . $format;

            switch ($format) {
                case 'plain':
                    $response->setHeader('Content-Disposition','inline');
                    $response->setHeader('Content-Type','text/plain');
                case 'xlsx':
                    $response->setHeader('Content-Disposition','attachment; filename="'.$fname.'"');
                    $response->setHeader('Content-Type','application/vnd.ms-excel');
                    break;
                case 'pdf':
                    $response->setHeader('Content-Disposition','inline');
                    $response->setHeader('Content-Type','application/pdf');
                    break;
                case 'csv':
                    $response->setHeader('Content-Disposition','attachment; filename="'.$fname.'"');
                    $response->setHeader('Content-Type','text/csv');
                    //$response->setHeader('Content-Type','application/download');

            }

            $response->sendHeaders();

            // export datasource to filename (or std out)
            $source->export($format, $filename);
            // exit immediatelly
            exit;
        }

        // let __toString() -> render() be called afterwise
        return $this;
    }

}
