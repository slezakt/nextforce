<?php
/**
 * Ibulletin_DataGrid_DataSource_Table
 *
 * class to provide a DataSource Zend_Db_Table_Abstract Object Implementation
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_DataSource
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

class Ibulletin_DataGrid_DataSource_Table extends Ibulletin_DataGrid_DataSource_DbSelect
{

	public function __construct(Zend_Db_Table_Abstract $table)
	{
		$this->setConnection($table->getAdapter());
        $this->setSelect($table->select(/*Zend_Db_Table::SELECT_WITH_FROM_PART)*/));

	}

}
