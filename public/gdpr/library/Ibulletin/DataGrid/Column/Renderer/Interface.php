<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Interface
 *
 * class to provide a grid item renderer interface
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


interface Ibulletin_DataGrid_Column_Renderer_Interface
{

    /**
     * @param Ibulletin_DataGrid_Column $column
     * @return Ibulletin_DataGrid_Column_Renderer_Interface
     */
    public function setColumn(Ibulletin_DataGrid_Column $column);


    /**
     * @return Ibulletin_DataGrid_Column
     */
    public function getColumn();

    /**
     * Renders grid column
     *
     * @param mixed $row
     * @return string
     */
    public function render($row);
}