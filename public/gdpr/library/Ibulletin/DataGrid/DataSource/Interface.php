<?php
/**
 * Ibulletin_DataGrid_DataSource_Interface
 * 
 * class to provide a DataSource Interface Implementation
 * 
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_DataSource
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


interface Ibulletin_DataGrid_DataSource_Interface
{
    /**
     * Fetching method prototype
     *
     * When overloaded this method must return a 2D array of records
     * on success or exception is raised.
     *
     * @abstract
     * @param   integer $offset     Limit offset (starting from 0)
     * @param   integer $len        Limit length
     * @param bool      $toArray
     * @return  object
     * @access  public
     */
	public function fetch($offset = 0, $len = null, $toArray = false);

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
	public function count();

	/**
	 * Sorting method prototype
	 *
	 * When overloaded this method must return true on success or exception is raised.
	 *
	 * Note: must be called before fetch()
	 *
	 * @abstract
	 * @param   string  $sortSpec   If the driver supports the "multiSort"
	 *                              feature this can be either a single field
	 *                              (string), or a sort specification array of
	 *                              the form: array(field => direction, ...)
	 *                              If "multiSort" is not supported, then this
	 *                              can only be a string.
	 * @param   string  $sortDir    Sort direction: 'ASC' or 'DESC'
	 * @return  object
	 * @access  public
	 */
	public function sort($sortSpec, $sortDir = null);

    /**
     * Filtering method prototype
     *
     * When overloaded this method must return true on success or exception is raised.
     *
     * Note: must be called before fetch()
     *
     * @abstract
     * @param   array   $filterSpec array of filters consisting of 'value' and 'datatype'
     * @return  object
     * @access  public
     */
    public function filter($filterSpec);

    /**
     * Hitlisting method prototype
     *
     * When overloaded this method must return true on success or exception is raised.
     *
     *
     * @abstract
     * @param   string $field column field name
     * @param   string term term criteria
     * @return  object
     * @access  public
     */
    public function hitlist($field, $term);

    /**
     * Export method prototype
     *
     * When overloaded, this method must provide export (headers and data) to given format
     *
     * @param      $format
     * @param null $filename
     * @return void
     */

    public function export($format, $filename = null);

    /**
     * Returns columns (keys) defined in datasource
     *
     * @return mixed
     */
    public function getColumns();
}