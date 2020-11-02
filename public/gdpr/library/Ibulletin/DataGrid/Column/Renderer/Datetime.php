<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Datetime
 *
 * class to provide a grid item renderer number
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_Datetime extends Ibulletin_DataGrid_Column_Renderer_Abstract
{

    protected function _getValue($row)
    {
        $data = parent::_getValue($row);

        if (!is_null($data)) {
            $ts = strtotime($data);
            return '<span title="'.$data.'" class="date">'.date('d.m.Y', $ts) .'</span>';
        }
        return $this->getDefaultValue();
    }

}