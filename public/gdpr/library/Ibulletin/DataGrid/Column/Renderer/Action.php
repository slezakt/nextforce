<?php
/**
 * Ibulletin_DataGrid_Column_Renderer_Action
 *
 * class to provide a Grid column widget for rendering action grid cells
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Column_Renderer
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


class Ibulletin_DataGrid_Column_Renderer_Action extends Ibulletin_DataGrid_Column_Renderer_Text
{

	/**
	 * Renders column
	 *
	 * @param array $row
	 * @return string
	 */
	public function render($row)
	{
		$actions = $this->getColumn()->getActions();
		if ( empty($actions) || !is_array($actions) ) {
			return '&nbsp;';
		}
        $isVisible = $this->_isLinkVisible($actions, $row);

        if (isset($actions['empty'])) unset($actions['empty']);
        if (isset($actions['notempty'])) unset($actions['notempty']);
        if (isset($actions['isnull'])) unset($actions['isnull']);
        if (isset($actions['equals'])) unset($actions['equals']);
        if (isset($actions['notequals'])) unset($actions['notequals']);

        if ($isVisible) {
            $res = $this->_toLinkHtml($actions, $row);
        } else {
            $actions['class'] = 'disabled';
            unset($actions['url']);
            $res = $this->_toLinkHtml($actions, $row);
        }

        return $res;
	}

	/**
	 * Render single action as link html
	 *
	 * @param array $action
	 * @param array $row
	 * @return string
	 */
	protected function _toLinkHtml($action, $row)
	{
		$actionCaption = '';
		$res = $this->_transformActionData($action, $actionCaption, $row);
        if (!$res) return '';

		if(isset($action['confirm'])) {
            if (isset($action['href'])) {
                $action['data-confirm'] = $this->formatVariables($row,$action['confirm']);
            }
			unset($action['confirm']);
		}

		if(isset($action['image'])) {
			$actionCaption = "<i class=\"icon icon-".$action['image']."\" title=\"".$actionCaption."\"></i>";
            $action['class'] .= ' btn btn-small';
		}
		unset($action['image']);

        if ($actionCaption == '') {
            $this->getColumn()->setFormat(null);
            $actionCaption = parent::render($row);

            // nullovou hodnotu pouze vypiseme formatovanou bez linku
            if (array_key_exists($this->getColumn()->getIndex(),$row) &&
                is_null($row[$this->getColumn()->getIndex()])) {
                return $actionCaption;
            }
        }

		return '<a ' . $this->buildLink($action) . '>' . $actionCaption . '</a>';
	}

	/**
	 * Prepares action data for html render
	 *
	 * @param array $action
	 * @param string $actionCaption
	 * @param array $row
	 * @return Ibulletin_DataGrid_Column_Renderer_Action
	 */
	protected function _transformActionData(&$action, &$actionCaption, $row)
	{

        foreach ( $action as $key => $value ) {
			if(isset($action[$key]) && !is_array($action[$key])) {
				$this->getColumn()->setFormat($action[$key]);
				$action[$key] = parent::render($row);
			} else {
				$this->getColumn()->setFormat(null);
			}

			switch ($key) {
				case 'caption':
					$actionCaption = $action['caption'];
					unset($action['caption']);
					break;
				case 'url':

                    if (is_callable($action['url'])) {
                        $res = call_user_func_array($action['url'], array($row));
                        $action['url'] = $res ? $res: '';
                    }
                    if (!$action['url']) {
                        unset ($action['href']);
                    } else {
                        $action['href'] = $action['url'];
                    }
					unset($action['url']);
					break;
				case 'popup':
					$action['onclick'] = 'popWin(this.href, \'width=800,height=700,resizable=1,scrollbars=1\');return false;';
					break;
			}
		}
		return true;
	}

    public function buildLink($action, $attributes = array(), $valueSeparator='=', $fieldSeparator=' ', $quote='"')
    {
        $data = array();
        if (empty($attributes)) {
            $attributes = array_keys($action);
        }

        foreach ($action as $key => $value) {
            if (in_array($key, $attributes)) {
                $data[] = $key.$valueSeparator.$quote.$value.$quote;
            }
        }
        $res = implode($fieldSeparator, $data);
        return $res;
    }

    protected function _isLinkVisible($action, $row)
    {
        if (empty($action['url'])) return false;

        if (isset($action['empty'])) {
            if (empty($row[$action['empty']])) {
                return false;
            }
        }

        if (isset($action['notempty'])) {
            if (!empty($row[$action['notempty']])) {
                return false;
            }
        }

        if (isset($action['isnull'])) {
            if (is_null($row[$action['isnull']])) {
                return false;
            }
        }

        if (isset($action['equals']) && ($a = $action['equals'])) {
            if  ($row[$a[0]] == $a[1]) {
                return false;
            }
        }
        if (isset($action['notequals']) && ($a = $action['equals'])) {
            if  ($row[$a[0]] != $a[1]) {
                return false;
            }
        }

        return true;
    }
}
