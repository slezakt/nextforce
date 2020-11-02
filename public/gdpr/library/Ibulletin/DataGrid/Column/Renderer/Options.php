<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Options
 *
 * class to provide a Grid column widget for rendering grid cells that contains mapped values
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_Options extends Ibulletin_DataGrid_Column_Renderer_Text
{
    /**
     * @param array $row
     * @return string
     */
    public function render($row)
    {
        $options = $this->getColumn()->getOptions();
        if (!empty($options) && is_array($options)) {
            $value = $row[$this->getColumn()->getIndex()];
            if (is_array($value)) {
                $res = array();
                foreach ($value as $item) {
                    $res[] = isset($options[$item]) ? $options[$item] : $item;
                }
                return implode(', ', $res);
            }
            elseif (isset($options[$value])) {
                return $options[$value];
            }
            return '';
        }
        return '';
    }

}
