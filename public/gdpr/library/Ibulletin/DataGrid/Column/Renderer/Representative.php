<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Representative
 *
 * class to provide a renderer for comma-separated user IDs
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_Representative extends Ibulletin_DataGrid_Column_Renderer_Abstract
{

    public function _getValue($row)
    {
        $res = '';
        $data = parent::_getValue($row);

        if (!is_null($data) && !$data == '') {

            $ids = explode(',', (string)$data);

            $sel = Zend_Registry::get('db')->select()
                ->from(array('u' => 'users'), array('id', 'name', 'surname'))
                ->where('id IN (?)', $ids);

            $arr = $this->getColumn()->getImgs();

            if (count($arr) == 1 && $singleImage = array_shift($arr)) {
                foreach ((array)$sel->query()->fetchAll() as $row) {
                    $title = $row['id'] . ' | ' . $row['name'] . ' ' . $row['surname'];
                    if (file_exists($singleImage)) {
                        $res .= '<img src="'. $singleImage .'" title="' . $title . '" alt="' . $title . '"/>';
                    } else {
                        $res .= '<i class="icon icon-' . $singleImage . '" title="' . $title . '" alt="' . $title . '"></i>';
                    }

                }
            } else {
                foreach ((array)$sel->query()->fetchAll() as $row) {
                    $res .= '<span title="'.$row['id'].'">'.$row['name'] .' '.$row['surname'].'</span>';
                }
            }
        }
        return $res ? $res : $this->getDefaultValue();
    }

}
