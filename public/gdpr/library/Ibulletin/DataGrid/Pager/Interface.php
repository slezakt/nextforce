<?php
/**
 * Ibulletin_DataGrid_Pager_Abstract_Interface
 *
 * class to provide a Interface Pager Implementation
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Pager_Abstract
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

interface Ibulletin_DataGrid_Pager_Interface
{
	public function setPage($_page);
	
	public function getPage();
	
	public function setLimit($_pageLimit);
	
	public function getLimit();
	
	public function setNumberPages();
	
	public function getNumberPages();
	
	public function setNumberRecords($_recordsNum);
	
	public function getNumberRecords();

    public function render();
}
