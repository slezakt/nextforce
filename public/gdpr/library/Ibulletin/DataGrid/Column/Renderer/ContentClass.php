<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_ContentClass
 *
 * class to provide a renderer for content class_name (Ibulletin_Content_*)
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_ContentClass
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_ContentClass extends Ibulletin_DataGrid_Column_Renderer_Abstract
{

    public function _getValue($row)
    {
        $res = str_replace('Ibulletin_Content_', '', parent::_getValue($row));

        return $res ? $res : $this->getDefaultValue();
    }

}

