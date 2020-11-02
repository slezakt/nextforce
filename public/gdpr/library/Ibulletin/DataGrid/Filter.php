<?php
/**
 * Ibulletin_DataGrid_Filter
 *
 * class to provide a filter definition
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Filter extends Ibulletin_DataGrid_Filter_Abstract
{

    public function __construct($data = null) {
        if (is_array($data)) {
            $this->setData($data);
        }
    }

    /**
     * @param array $data
     */
    protected function setData($data) {
        foreach ($data as $k => $v) {           
            $method = 'set' . ucwords($k);
            if (method_exists(__CLASS__, $method)) {
                $this->{$method}($v);
            }
        }
    }

}