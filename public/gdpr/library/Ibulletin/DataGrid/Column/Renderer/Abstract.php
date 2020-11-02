<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Abstract
 *
 * class to provide a grid item abstract renderer
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


abstract class Ibulletin_DataGrid_Column_Renderer_Abstract implements Ibulletin_DataGrid_Column_Renderer_Interface
{

    /**
     * Format variables pattern
     *
     * @var string
     */
    protected $_variablePattern = '/\\$([a-z0-9_]+)/i';

    /**
     * @var Ibulletin_DataGrid_Column
     */
    protected $_column;

    /**
     * @param  Ibulletin_DataGrid_Column $column
     * @return Ibulletin_DataGrid_Column_Renderer_Abstract
     */
    public function setColumn(Ibulletin_DataGrid_Column $column)
	{
		$this->_column = $column;
		return $this;
	}

    /**
     * @return Ibulletin_DataGrid_Column
     */
    public function getColumn()
	{
		return $this->_column;
	}

	/**
	 * Renders grid column
	 *
	 * @param   array $row
	 * @return  string
	 */
	public function render($row)
	{
        return $this->_getValue($row);
	}

    /**
     * @param  array $row
     * @return mixed
     */
    protected function _getValue($row)
	{
		return $row[$this->getColumn()->getIndex()];
	}

    protected function getDefaultValue() {
        return '<span class="null-text">' . $this->getColumn()->getDefault() .'</span>';
    }

    /**
     * @param string $value
     * @return string
     */
    protected function formatImages($value) {
        $baseUrlHlpr = new Zend_View_Helper_BaseUrl();

        $title = is_bool($value) ? $value ? 'true' : 'false' : (string)$value;
        // images to match value
        $res = false;
        if ($imgs = $this->getColumn()->getImgs()) {
            if (isset($imgs[$value]) && file_exists(ltrim($imgs[$value], '\//'))) {
                $res = '<img src="' . $baseUrlHlpr->baseUrl(ltrim($imgs[$value], '\//')) . '" alt="'.$title.'" title="'.$title.'" />';
            } else {
                $res = '<i class="icon icon-'. $imgs[$value] .'"' . $title . '" title="' . $title . '"></i>';
            }
        }
        return $res;
    }

    /**
     * @param string $row
     * @param string $format
     * @return string
     */
    protected function formatVariables($row, $format = '') {
        if (preg_match_all($this->_variablePattern, $format, $matches)) {
            // Parsing of format string
            $formatedString =$format;
            foreach ($matches[0] as $matchIndex=>$match) {
                $value = $row[$matches[1][$matchIndex]];
                $formatedString = str_replace($match, $value, $formatedString);
            }
            return $formatedString;
        } else {
            return $format;
        }
    }

    /**
     * @return string
     */
    public function renderHeader()
	{
		if ( (false !== $this->getColumn()->getGrid()->getSortable()) && (false !== $this->getColumn()->getSortable()) ) {
			$className = 'not-sort';

			$dir = strtolower($this->getColumn()->getDir());

			$icon = '';

			if(!empty($dir)){
				$icon = ($dir == 'asc')? '&nbsp;&#9650;':'&nbsp;&#9660;';
			}

			$dir = ($dir == 'asc') ? 'desc' : 'asc';

			if ($this->getColumn()->getDir()) {
				$className = 'sort-arrow-' . $dir;
			}

			$out = '<a href="'.$this->getColumn()->getLink($dir, $this->getColumn()->getIndex()).'" name="'.$this->getColumn()->getId().'" class="' . $className . '"><span class="sort-title">'.$this->getColumn()->getHeader().$icon.'</span></a>';
		}
		else {
			$out = $this->getColumn()->getHeader();
		}
		return $out;
	}

    /**
     * @return string
     */
    public function renderProperty()
	{
		$out = ' ';

		if ($width = $this->getColumn()->getWidth()) {
			if (!is_numeric($width)) {
				$width = (int)$width;
			}
			$out .='width="'.$width . '" ';
		}
		return $out;
	}

    /**
     * @return mixed
     */
    public function renderCss()
	{
		return $this->getColumn()->getCssClass();
	}

	/**
	 * Escape html entities
	 * @deprecated
	 * @param   mixed $data
	 * @return  mixed
	 */
	public function htmlEscape($data)
	{
		if (is_array($data)) {
			foreach ($data as $item) {
				return $this->htmlEscape($item);
			}
		}
		return htmlspecialchars($data);
	}
}