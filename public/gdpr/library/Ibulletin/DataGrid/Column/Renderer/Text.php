<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Text
 *
 * class to provide a grid item renderer text/string
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_Text extends Ibulletin_DataGrid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @param array $row
     * @return mixed
     */
    public function _getValue($row)
    {
        $format = $this->getColumn()->getFormat();

        $view = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer')->view;

        $out = '';

        // If no format specified return filtered value as is
        if (is_null($format)) {

            $value = parent::_getValue($row);

            // replace NULL with default value
            if (is_null($value)) {
                $out =  $this->getDefaultValue();

            // replace with image if matched
            } elseif ($out = $this->formatImages($value)) {
                ;
            } else {
                $title = $value;
                // support for stringLimit
                if ($limit = (int)$this->getColumn()->getStringLimit()) {
                    $suffix = ( $this->getColumn()->getSuffix() ) ? $this->getColumn()->getSuffix() : '...';
                    if( strlen($value) > $limit ) {
                        $value = substr($value, 0, $limit) . $suffix;
                    }
                }

                $out = '<span title="'.$view->escape($title).'">'.$view->escape($value).'</span>';
            }
        } else {
            // find variable references and replace for values in 'format' string
            $out = $view->escape($this->formatVariables($row, $format));
        }
        
        // Url for whole content of column
        if($url = $this->getColumn()->getUrl()){
            $url = $view->escape($this->formatVariables($row, $url));
            $out = '<a href="'.$url.'">'.$out.'</a>';
        }
        
        return $out;
    }

}