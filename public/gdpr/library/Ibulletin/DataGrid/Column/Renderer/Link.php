<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Link
 *
 * class to provide a grid item renderer link
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_Link extends Ibulletin_DataGrid_Column_Renderer_Text
{
	/**
	 * Format variables pattern
	 *
	 * @var string
     * @return string
     */

	public function render($row)
	{
		$links = $this->getColumn()->getLinks();

		if (empty($links)) {
			return parent::render($row);
		}

		$text = parent::render($row);

		$this->getColumn()->setFormat($links);
		$action = parent::render($row);
		$this->getColumn()->setFormat(null);

		return '<a href="' . $action . '" title="' . $text . '">' . $text . '</a>';
	}
}