<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Content
 *
 * class to provide a renderer for serialized content (Ibulletin_Content)
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Content
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_Content extends Ibulletin_DataGrid_Column_Renderer_Abstract
{

    public function _getValue($row)
    {
        $res = '';

        $view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;

        $row = Contents::prepareContent($row);

        $res = $row['object']->{$this->getColumn()->getContentField()};

        return $res ? '<span title="'.$view->escape($res).'">'.$view->escape($res).'</span>' :  $this->getDefaultValue();
    }

}

