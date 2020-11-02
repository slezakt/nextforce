<?php
/**
 * Ibulletin_DataGrid_Pager_Simple
 *
 * class to provide a Pager Simple Object Implementation
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_Pager_Abstract
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

class Ibulletin_DataGrid_Pager_Simple extends Ibulletin_DataGrid_Pager_Abstract {

    public function render()
    {
        if (!$this->isVisible())
        {
            return '';
        }

        $next_page = null;
        $previous_page = null;

        if($this->getOnPage() >= $this->getNumberPages())
        {
            $next_page = null;
        } else {
            if ($this->getOnPage() != 1)
            {
                $next_page .= " ";
            }
            $next_page .= "<a href=\"" . $this->getLink($this->getOnPage() * $this->getLimit()) . "\">" . $this->getNext() . ' ' . ($this->getOnPage() + 1) . '/' . $this->getNumberPages() . "</a>";
        }

        if($this->getOnPage() <= 1)
        {
            $previous_page = null;
        } else {
            $previous_page = "<a href=\"" . $this->getLink(($this->getOnPage() - 2) * $this->getLimit()) . "\">" . $this->getPrevious() . ' '. ($this->getOnPage() - 1) . '/' . $this->getNumberPages() . "</a>";
        }

        $output = '<div id="' . $this->getLinksId() . '">' . $previous_page . ($previous_page && $next_page ? ' | ':'') . $next_page .'</div>';

        return $output;
    }
}
