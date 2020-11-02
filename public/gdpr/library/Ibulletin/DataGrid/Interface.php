<?php
/**
 * Ibulletin_DataGrid_Interface
 *
 * class to provide a DataGrid Interface
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

interface Ibulletin_DataGrid_Interface
{
	public function setDefaultSort($sortSpec);

	public function getDefaultSort();

	public function setDefaultDir($dir);

	public function getDefaultDir();

	public function getOrder();

	public function getDirection();

	public function addColumn($columnId, $column);

	public function getLastColumnId();

    public function getFirstColumnId();

	public function getColumnCount();

	public function getColumn($columnId);

}
