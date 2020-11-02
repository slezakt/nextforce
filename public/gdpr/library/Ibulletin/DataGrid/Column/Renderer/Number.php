<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Number
 *
 * class to provide a grid item renderer number
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_Number extends Ibulletin_DataGrid_Column_Renderer_Abstract
{

    protected function _getValue($row)
    {

        $this->getColumn()->setClass('a-right');

        $data = parent::_getValue($row);
        if (!is_null($data)) {
            $value = $data * 1;
        	return $value ? $value: '0'; // fixed for showing zero in grid
        }
        return $this->getDefaultValue();
    }

}
