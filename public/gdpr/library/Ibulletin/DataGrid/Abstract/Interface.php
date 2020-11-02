<?php
/**
 * Ibulletin_DataGrid_Abstract_Interface
 *
 * class to provide a Abstract DataGrid Interface
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Abstract
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

interface Ibulletin_DataGrid_Abstract_Interface
{
	public function setSelect($select);

	public function getSelect();

	public function getPage();

	public function setPage($page);

	public function setLimit($limit = null);

	public function getLimit();

	public function getNumberRecords();

	public function setDataSource($dataSource);

	public function getDataSource();

	public function fetch();

	public function setTotal($total);

	public function getTotal();

	public function isLoaded();

	public function getPager($adapterName = null);

	public function renderPager($adapterName = null);
	
	public function render($adapterName = null);
}