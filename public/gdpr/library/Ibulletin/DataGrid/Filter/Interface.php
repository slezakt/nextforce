<?php
/**
 * Ibulletin_DataGrid_Filter_Interface
 *
 * class to provide a grid filter interface
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


interface Ibulletin_DataGrid_Filter_Interface
{

    /**
     * filter type
     *
     * @return mixed|string
     */
    public function getType();

    /**
     * data type of key used for filtering
     * @return mixed|string
     */
    public function getDataType();

    /**
     * get value for filtering
     *
     * @return mixed|string
     */
    public function getValue();

    /*
     * get field name
     *
     * @return mixed|string
     */
    public function getField();



}