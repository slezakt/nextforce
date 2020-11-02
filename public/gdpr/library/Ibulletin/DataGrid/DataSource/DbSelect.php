<?php
/**
 * Ibulletin_DataGrid_DataSource_DbSelect
 *
 * class to provide a DataSource Zend_Db_Select Object Implementation
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_DataSource
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

class Ibulletin_DataGrid_DataSource_DbSelect extends Ibulletin_DataGrid_DataSource_Abstract
{

	/* @var Zend_Db_Select $_select */
	private $_select;

    /* @var Zend_Db_Adapter_Abstract $_conn */
    private $_conn = null;

    /* @var int $_count */
	private static $_count = null;

	public function __construct(Zend_Db_Select $select = null)
	{
		$this->setSelect($select);
	}

	/**
	 * Set Zend_Db_Select instance
	 *
	 * @return Zend_Db_Select
	 */
	public function getSelect()
	{
		if(null === $this->_select){
			$this->setSelect();
		}

		return $this->_select;
	}

    /**
     * Set Zend_Db_Select instance
     *
     * @param Zend_Db_Select $select
     * @return $this
     */
	public function setSelect(Zend_Db_Select $select = null)
	{
		if(null === $select)
		{
			$conn = Zend_Db_Table::getDefaultAdapter();

			if (!$conn instanceof Zend_Db_Adapter_Abstract){
				$conn = Zend_Registry::get('db');
			}

			$select = $conn->select();

		} else {
            $conn = $select->getAdapter();
        }
        $this->setConnection($conn);

		$this->_select = $select;
		return $this;
	}


    /**
     * @return Zend_Db_Adapter_Abstract
     */
    public function getConnection()
	{
		return $this->_conn;
	}

	public function setConnection(Zend_Db_Adapter_Abstract $conn)
	{
		$this->_conn = $conn;
		return $this;
	}

    /**
     * Fetching method prototype
     *
     * When overloaded this method must return a 2D array of records
     * on success or exception is raised.
     *
     * @abstract
     * @param   integer $offset     Limit offset (starting from 0)
     * @param null      $size
     * @param bool      $toArray
     * @internal param int $len Limit length
     * @return  object
     * @access  public
     */
	public function fetch($offset = 0, $size = null, $toArray = false)
	{
		$count = is_null($size) ? $this->count() : $size;

        $sel = $this->getSelect();

		$sel->limit($count, $offset);

        return $sel->query()->fetchAll();

	}

	/**
	 * Counting method prototype
	 *
	 * Note: must be called before fetch()
	 *
	 * When overloaded, this method must return the total number or records
	 * or exception is raised
	 *
	 * @abstract
	 * @return  object
	 * @access  public
	 */
	public function count(){

		if( null === self::$_count){

            $count = $this->getConnection()->fetchOne($this->_getSelectCountSql());

			self::$_count = (int) $count;
			return self::$_count;
		} else {
			return self::$_count;
		}
	}

	/**
	 *
	 */
	public function sort($sortSpec, $sortDir = 'ASC') {

        /* add qoutes around table and column identifiers */
        if (preg_match('/[^"]\.[^"]/', $sortSpec) !== FALSE) {
            $arr =  explode('.', $sortSpec);
            foreach ($arr as $key => &$val) {
                $val = '"' . $val . '"';
            }
            $sortSpec = implode('.',$arr);
        }

        // add ordering
        $this->getSelect()->order(new Zend_Db_Expr("$sortSpec $sortDir"));

	}

	/**
	 * Get sql for get record count
	 *
	 * @return  string
	 */
	private function _getSelectCountSql()
	{
		$countSelect = clone $this->getSelect();

		$countSelect->reset(Zend_Db_Select::ORDER);
		$countSelect->reset(Zend_Db_Select::LIMIT_COUNT);
		$countSelect->reset(Zend_Db_Select::LIMIT_OFFSET);
        $countSelect->reset(Zend_Db_Select::COLUMNS);

        $countSelect->columns(new Zend_Db_Expr('COUNT(1)'));

		return $countSelect;
	}

	public function getColumnsMeta()
	{
		// Fetch Select Columns
		$rawColumns = $this->getSelect()->getPart(Zend_Db_Select::COLUMNS);

        $columns = array();
		// Get columns and Force casting as strings

		foreach($rawColumns as $col)
		{

            $alias = $col[0];
            if($col[1] === "*"){
				//$columns = array_merge($columns, $this->_getColumnsAlternative($alias));
                foreach ($this->_getColumnsAlternative($alias) as $col) {
                    $columns[] = $col;/*array(
                        'name' => (string) $col['name'],
                        'datatype' => /*'string'$col['datatype'],
                        'alias' => $col['alias'],
                    );                  */
                }
                continue;
			}
			
			if($col[1] instanceof Zend_Db_Expr){
				$name = $col[2];
			} else {
				$name = $col[2] == null ? $col[0].'_'.$col[1] : $col[2];
			}

            $columns[] = array('name' => (string) $name, 'datatype' => 'string', 'alias' => null, 'table' => null);

		}
        //dump($columns);exit;
		return $columns;
	}

    public function getColumns() {
        $cols = $this->getColumnsMeta();
        $res = array();
        foreach ($cols as $column) {
            $res[] = $column['name'];
        }
        return $res;
    }


	private function _getColumnsAlternative($alias)
	{
        echo $alias;
        $sel = clone $this->getSelect();

        $frompart = $sel->getPart(Zend_Db_Select::FROM);
        $table = $frompart[$alias]['tableName'];

        $sel->reset(Zend_Db_Select::COLUMNS);
        $sel->reset(Zend_Db_Select::FROM);
        $sel->reset(Zend_Db_Select::WHERE);

        $sel->from(array($alias => $table));
        $query = $sel->__toString();

        $query = $this->getConnection()->limit($query, 1);

        $rst = $this->getConnection()->query( $query );

        $cols = array();

        for( $i = 0; $i < $rst->columnCount(); $i++ ) {
            $data = $rst->getColumnMeta($i);

            // expand with a 'generic' key for various db-specific native types of column
            switch ($data['native_type']) {
                case 'int4' : $data['datatype'] = 'int'; break;
                case 'text': case 'varchar':
                case 'bpchar' : $data['datatype'] = 'string'; break;
                case 'bool': $data['datatype'] = 'bool'; break;
                case 'timestamptz': $data['datatype'] = 'datetime'; break;
                default: $data['datatype'] = $data['native_type'];
            }
             $data['alias'] = $alias;
            $data['table'] = $table;
            $cols[$i] = $data;

        }
        $rst = null;
        return $cols;

	}

    /**
     * Filtering method prototype
     *
     * When overloaded this method must return true on success or exception is raised.
     *
     * Note: must be called before fetch()
     *
     * @param   array   $filterSpec array of filters consisting of 'value' and 'type'
     * @see Ibulletin_DataGrid_DataSource_Interface
     * @return  void
     * @access  public
     */
    public function filter($filters)
    {
        $db = $this->getConnection();
        $sel = $this->getSelect();
        
        $orwhere = array();
        
        foreach ($filters as $key => $filter) {
            if ($filter instanceof Ibulletin_DataGrid_Filter_Abstract) {

                if (!is_array($filter->getValue())) {
                    $v = $filter->sanitize($filter->getValue()); // case-sensitiveness, triming
                } else {
                    $v = $filter->getValue();
                }

                switch ($filter->getType()) {
                    
                    //filtr orquery prida moznost diskjunkce, slouzi k pripojeni mnoziny dat nezavisle na predchozich filtrech, umisti se vzdy jako posledni podminka
                    case 'orquery':
                        if ($q = $filter->getQuery()) {                          
                            $orwhere[] = $db->quoteInto($q,$v);                           
                        }
                        break;

                    case 'query':
                        if ($q = $filter->getQuery()) {
                            $sel->where($q, $v);
                        }
                        break;

                    case 'equals':
                        $sel->where($key . ' = ?', $v);
                        break;

                    case 'in':
                        if (is_array($v) && !empty($v)) {
                            foreach ($v as &$item) {
                                $item = $filter->sanitize($item, true);
                            }
                            $sel->where($key . ' IN (?)', $v);
                        }
                        break;

                    case 'between': // value is of type array
                        if (is_array($v) && !empty($v)) {
                            foreach ($v as $k => &$item) {
                                $item = $filter->sanitize($item, true);
                            }
                            // http://framework.zend.com/issues/browse/ZF-2211
                            // $sel->where($key . ' BETWEEN ? AND ?', $v);
                            $v1 = reset($v); next($v); $v2 = current($v);
                            $sel->where($key . ' >= ?', $v1);
                            $sel->where($key . ' <= ?', $v2);
                        } else {
                            throw new Ibulletin_DataGrid_Exception('Filter value for filter type "between" must be of type "array"');
                        }
                        break;

                    case 'expr': // value | <value | =value | >value | <=value | >=value | *value | value* | *value* | va?lu?e
                                 // <>value | value1,value2,... | NULL | <>NULL

                    default: // parse expression
                        if (strtolower($v) === 'null') { // null or empty string
                            $extra = !in_array($filter->getDatatype(), array('int', 'datetime')) ? ' OR '. $key . " = ''" : '';
                            $sel->where($key . ' IS NULL' . $extra);
                        } elseif (preg_match('/^<>\p{Z}*null$/i',$v)) { // not null or not empty string
                            $extra = !in_array($filter->getDatatype(), array('int', 'datetime')) ? ' AND '. $key . " <> ''" : '';
                            $sel->where($key . ' IS NOT NULL' . $extra);
                        } elseif ($filter->getDatatype() === 'bool') { // boolean
                            $v = $filter->sanitize($v, true);
                            $v === true ? $sel->where($key . ' IS TRUE') : $sel->where($key . ' IS FALSE');
                        } elseif (strpos($v, '<>') === 0 || strpos($v, '<=') === 0 || strpos($v, '<') === 0 // logical operators
                                || strpos($v, '>=') === 0 || strpos($v, '>') === 0 || strpos($v, '=') === 0 ) {
                            if (in_array($filter->getDatatype(), array('int', 'datetime')) || $filter->getCasesensitive()) {
                                $_key = $key;
                                $_value = '?';
                            } else {
                                $_key = 'LOWER('.$key.')';
                                $_value = 'LOWER(?)';
                            }
                            if (strpos($v, '<>') === 0 && ($v = substr($v,2)) && strlen($v) !== 0) { // other than
                                $v = $filter->sanitize($v, true);
                                if ($filter->getDatatype() == 'datetime') {
                                    $sel->where($db->quoteInto($_key . ' < ' . $_value, $v[0]) .
                                        $db->quoteInto(' OR '. $_key . ' > ' . $_value, $v[1]));
                                } else {
                                    if (strpos($v,'*') !== false) {
                                        $v = str_replace('*', '%', $v);
                                        $sel->where($_key . ' NOT LIKE ' . str_replace('*', '%', $_value) .' OR '.$_key.' IS NULL', $v);
                                    } else {
                                        $sel->where($_key . ' <> ' . $_value .' OR '.$_key.' IS NULL', $v);
                                    }
                                }
                            } elseif (strpos($v, '<=') === 0 && ($v = substr($v,2)) && strlen($v) !== 0) { // less than or equal
                                $v = $filter->sanitize($v, true);
                                if ($filter->getDatatype() == 'datetime') {
                                    $sel->where($_key . ' <= ' . $_value, $v[1]);
                                } else {
                                    $sel->where($_key . ' <= ' . $_value, $v);
                                }
                            } elseif (strpos($v, '<') === 0 && ($v = substr($v,1)) && strlen($v) !== 0) { // less than
                                $v = $filter->sanitize($v, true);
                                if ($filter->getDatatype() == 'datetime') {
                                    $sel->where($_key . ' < ' . $_value, $v[0]);
                                } else {
                                    $sel->where($_key . ' < ' . $_value, $v);
                                }
                            }  elseif (strpos($v, '>=') === 0 && ($v = substr($v,2)) && strlen($v) !== 0) { // greater than or equal
                                $v = $filter->sanitize($v, true);
                                if ($filter->getDatatype() == 'datetime') {
                                    $sel->where($_key . ' >= ' . $_value, $v[0]);
                                } else {
                                    $sel->where($_key . ' >= ' . $_value, $v);
                                }
                            } elseif (strpos($v, '>') === 0 && ($v = substr($v,1)) && strlen($v) !== 0) { // greater than
                                $v = $filter->sanitize($v, true);
                                if ($filter->getDatatype() == 'datetime') {
                                    $sel->where($_key . ' > ' . $_value, $v[1]);
                                } else {
                                    $sel->where($_key . ' > ' . $_value, $v);
                                }
                            } elseif (strpos($v, '=') === 0 && ($v = substr($v,1)) && strlen($v) !== 0) { // equals
                                $v = $filter->sanitize($v, true);
                                if ($filter->getDatatype() == 'datetime') {
                                    $sel->where($key . ' >= ?', $v[0]);
                                    $sel->where($key . ' <= ?', $v[1]);
                                } else {
                                    $sel->where($_key . ' = ' . $_value, $v);
                                }
                            }

                        } elseif (strpos($v, ',') !== false && $arr = explode(',', $v)) { // multiple values (IN)
                            foreach ($arr as $k => &$item) {
                                $item = $filter->sanitize($item, true);
                            }
                            if ( in_array($filter->getDatatype(), array('int', 'datetime')) || $filter->getCasesensitive()) {
                                $sel->where($key . ' IN (?)', $arr);
                            } else {
                                $sel->where('LOWER('.$key.') IN (?)', $arr);
                            }
                        } elseif (strlen($v) !== 0) {             // everything else SQL LIKE compliant
                            switch ($filter->getDatatype()) {
                                // integer
                                case 'int' :
                                    $v = $filter->sanitize($v, true);
                                    $sel->where($key . ' = ?', $v);
                                    break;
                                // datetime
                                case 'datetime' :
                                    $v = $filter->sanitize($v, true);
                                    $sel->where($key . ' >= ?', $v[0]);
                                    $sel->where($key . ' <= ?', $v[1]);
                                    break;
                                // string
                                case 'string' :
                                default:
                                    // do SQL escaping for wildcard identifiers if present
                                    // and replace [?, *] with [_, %] for SQL LIKE syntax
                                    $v = str_replace(array('_','%', '?','*'), array('\\_', '\\%','_','%'), $v);

                                    // pattern expand if no wildcard is present
                                    $v = strpos($v, '%' ) === false ? '%' . $v . '%' : $v;

                                    if ($filter->getCasesensitive()) {
                                        $sel->where($key . ' LIKE ?', $v);
                                    } else {
                                        $sel->where($key . ' ILIKE ?', $v);
                                    }
                                    break;
                            }
                        }
                }              
            }
        }
        
        //doplneni orwhere - aby byla posledni podminka
        foreach($orwhere as $cond) {           
            $sel->orWhere($cond);
        }

    }

    /**
     * Hitlisting method prototype
     *
     * When overloaded this method must return true on success or exception is raised.
     *
     *
     * @param   string $field column field name
     * @param          string term term criteria
     * @return  object
     * @access  public
     */
    public function hitlist($field, $term)
    {
        $select = clone $this->getSelect();
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->reset(Zend_Db_Select::ORDER);
        $select->columns(array($field, 'count(1) as cnt'))->order('cnt desc')->group($field)->limit(15);
        $term = strpos($term, '%') === false ?  '%' . $term . '%' : $term;
        $select->where($field . ' LIKE ?', '%' . $term . '%');

        $res = array();
        foreach ($this->getConnection()->fetchCol($select) as $_val) {
            $v = strval($_val);
            if ( !is_null($v) && strval($v) !== '' ) { // erase NULL or '' // !empty(strval($_v))
                $res[$_val] = $_val;
            }
        }
        return $res;
    }

}
