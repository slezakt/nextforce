<?php
/**
 * Ibulletin_DataGrid_Pager
 *
 * class to provide a DataGrid Pager Object
 * for default the Pager is Standard class
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

final class Ibulletin_DataGrid_Pager
{
	const DEFAULT_ADAPTER = 'Standard';

	public static function factory($adapterName = null, $_page, $_pageLimit, $_recordsNum)
	{
		if (null === $adapterName){
			$adapterName = self::DEFAULT_ADAPTER;
		}

		if (!is_string($adapterName) or !strlen($adapterName)) {
			throw new Exception('Adapter name must be specified in a string.');
		}

		$adapterName = 'Ibulletin_DataGrid_Pager_' . $adapterName;

		Zend_Loader::loadClass($adapterName);

		return new $adapterName($_page, $_pageLimit, $_recordsNum);
	}
}
