<?php
/**
 * Ibulletin_DataGrid_Render_Abstract
 *
 * class to provide a Abstract Render Implementation
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Render
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

abstract class Ibulletin_DataGrid_Render_Abstract implements Ibulletin_DataGrid_Render_Interface
{
    /* @var Ibulletin_DataGrid_Abstract $_grid */
	protected $_grid = null;

    /**
     * @param Ibulletin_DataGrid_Abstract $grid
     */
    public function __construct(Ibulletin_DataGrid_Abstract $grid)
	{
		$this->setGrid($grid);
		$this->init();
	}

	public function init()
	{}

    /**
     * @param Ibulletin_DataGrid_Abstract $grid
     * @return Ibulletin_DataGrid_Render_Abstract
     */
    public function setGrid(Ibulletin_DataGrid_Abstract $grid)
	{
		$this->_grid = $grid;
		return $this;
	}

    /**
     * @return Ibulletin_DataGrid_Abstract|null
     */
    public function getGrid()
	{
		return $this->_grid;
	}

    public function render() {
        throw new NotImplementedException();
    }

}