<?php
/**
 * Ibulletin_DataGrid_Render
 *
 * class to provide a DataGrid Render Object
 * default renderer is Zend View
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

final class Ibulletin_DataGrid_Render
{
	const DEFAULT_ADAPTER = 'ZendView';

	public static function factory(Ibulletin_DataGrid_Interface $grid, $adapterName = null)
	{
		if (null === $adapterName){
			$adapterName = self::DEFAULT_ADAPTER;
		}

		if (!is_string($adapterName) or !strlen($adapterName)) {
			throw new Exception('Render Datagrid: Adapter name must be specified in a string.');
		}

		$adapterName = 'Ibulletin_DataGrid_Render_' . $adapterName;

		Zend_Loader::loadClass($adapterName);

		return new $adapterName($grid);
	}
}
