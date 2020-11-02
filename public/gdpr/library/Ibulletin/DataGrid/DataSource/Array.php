<?php
/**
 * Ibulletin_DataGrid_DataSource_Array
 *
 * class to provide a DataSource Array Object Implementation
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_DataSource
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

class Ibulletin_DataGrid_DataSource_Array extends Ibulletin_DataGrid_DataSource_Abstract
{
	/**
	 * The array
	 *
	 * @var array
	 * @access private
	 */
	private $_array = array();

	/**
	 * Item count
	 *
	 * @var integer
	 */
	private $_count = null;

    /**
     * @param array $array
     */
    public function __construct(array $array)
	{
		$this->_array = $array;
		$this->_count = count($array);
	}

	/**
	 * return number of records
	 *
	 * @access  public
	 * @return  int The number or records
	 */
	public function count()
	{
		return $this->_count;
	}

    /**
     * @param int  $offset
     * @param null $len
     * @param bool $toArray
     * @return array
     */
    public function fetch($offset = 0, $len = null, $toArray = false)
	{
		return array_slice($this->_array, $offset, $len);
	}

    /**
     * @param string $sortField
     * @param null   $sortDir
     */
    public function sort($sortField, $sortDir = null)
	{
		$sortAr = array();
		$numRows = $this->_count;

		for ($i = 0; $i < $numRows; $i++) {
			$sortAr[$i] = $this->_array[$i][$sortField];
		}

		$sortDir = (is_null($sortDir) or strtoupper($sortDir) == 'ASC') ? SORT_ASC : SORT_DESC;
		// todo: SQL like sort - utf-8 comparision. php5.3 introduces Collator::sort()
        array_multisort($sortAr, $sortDir, $this->_array);
	}

    /**
     * @return array
     */
    public function getColumns()
	{
		if(isset($this->_array[0])){
			return array_keys($this->_array[0]);
		}
		return array();
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
    public function filter($filters) {

        $a = $this->_array;
        $a = array_map(array($this, '_applyFilter'), $a, array_fill(0, $this->count(), $filters));
        $a = array_filter($a);
        $a = array_values($a);
        $this->_count = count($a);
        $this->_array = $a;

    }

    public function _applyFilter($row, $filters) {
        $ok = true;

        foreach ($filters as $key => $filter) {

            if (!$ok) break;

            if ($filter instanceof Ibulletin_DataGrid_Filter_Abstract &&
                isset($row[$key]) && ($subject = $row[$key])) {

                if (!is_array($filter->getValue())) {
                    $v = $filter->sanitize($filter->getValue()); // case-sensitiveness, triming
                } else {
                    $v = $filter->getValue();
                }

                switch ($filter->getType()) {

                    //filtr orquery prida moznost diskjunkce, slouzi k pripojeni mnoziny dat nezavisle na predchozich filtrech, umisti se vzdy jako posledni podminka
                    case 'orquery':
                    case 'query':
                    case 'equals':
                    case 'in':
                    case 'between': // value is of type array
                        break;
                    case 'expr': // value | <value | =value | >value | <=value | >=value | *value | value* | *value* | va?lu?e
                        // <>value | value1,value2,... | NULL | <>NULL
                    default: // parse expression
                        if (strtolower($v) === 'null') { // null or empty string
                            $ok = empty($subject);
                        } elseif (preg_match('/^<>\p{Z}*null$/i',$v)) { // not null or not empty string
                            $ok = !empty($subject);
                        } elseif ($filter->getDatatype() === 'bool') { // boolean
                            $v = $filter->sanitize($v, true);
                            $ok = ($subject == $v);
                        } elseif (strpos($v, '<>') === 0 || strpos($v, '<=') === 0 || strpos($v, '<') === 0 // logical operators
                            || strpos($v, '>=') === 0 || strpos($v, '>') === 0 || strpos($v, '=') === 0 ) {

                            // empty atm

                        } elseif (strpos($v, ',') !== false && $arr = explode(',', $v)) { // multiple values (IN)

                            // empty atm

                        } elseif (strlen($v) !== 0) {             // everything else SQL LIKE compliant
                            switch ($filter->getDatatype()) {
                                // integer
                                case 'int' :
                                    $v = $filter->sanitize($v, true);
                                    $ok = ($v == $subject);
                                    break;
                                // datetime
                                case 'datetime' :

                                     // empty atm

                                    break;
                                // string
                                case 'string' :
                                default:
                                    // workaround to use preg_quote for every PCRE character except * and ?
                                    // other solution is to specifically quote  . \ + [ ^ ] $ ( ) { } = ! < > | : -
                                    $hash_q = 'Xl5oJ8tVbDkSqz7LU5yqwnTR02G52R1h';
                                    $hash_s = 'c9emFz9X3NQ1KIMlWqoq2M2VXUB2NX6O';
                                    $v = str_replace(array('?','*'), array($hash_q,$hash_s),$v);
                                    $v = preg_quote($v);
                                    $v = str_replace(array($hash_q,$hash_s),array('?','.*'),$v);

                                    // pattern expand if no wildcard is present
                                    $v = strpos($v, '*' ) === false ? '.*' . $v . '.*' : $v;
                            }
                        }
                }

                $pattern = '/^'.$v.'$/' . ($filter->getCasesensitive() ? '' : 'i');

                if ($res = preg_match($pattern,$subject)) {
                    $ok = (boolean)$res;
                } else {
                    $ok = false;
                }

            }

        }

        return $ok ? $row : null;
    }



}

