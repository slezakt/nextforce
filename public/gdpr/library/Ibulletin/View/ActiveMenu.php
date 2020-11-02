<?php

/**
 * Helper zkontroluje jestli je polozka menu aktivni, vraci boolean
 *
 * @author Ondra Bláha <ondrej.blaha@pearshealthcyber.com>
 */
class Ibulletin_View_Helper_ActiveMenu extends Zend_View_Helper_Abstract {
    
    public $view;
 
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
 
    public function activeMenu($menuItem)
    {
        
        if (isset($menuItem['url']) && $this->view->url() == $menuItem['url']) {
            return true;
        }

        //je-li menu polozka se stejnym url neoznacime jinou        
        if (isset($this->view->menu['menu'])) {
            foreach ($this->view->menu['menu'] as $menu) {
                if ($this->view->url() == $menu['url']) {
                    return false;
                }
            }
        }

        $is_special_category = $this->view->category && $this->view->category['type'] == 'a';
        $is_actual_bulletin = $this->view->bul_url_name == $this->view->actual_bul_row['url_name'];
        $active_category = $this->view->is_category || $is_special_category;
        $active_bulletin = $is_actual_bulletin && !($this->view->is_archive || $active_category || $this->view->is_search || $this->view->is_forwardmail);
         
        if ($menuItem['special'] == 'category') {
            if ($active_category && $this->view->category['type'] == 'r') {
                return true;
            }
        } elseif ($menuItem['special'] == 'category_application') {

            if ($active_category && $this->view->category['url_name'] == $menuItem['url_name']) {
                return true;
            }
            
        } elseif ($menuItem['special'] == 'archive') {
            $active_archive = $this->view->is_archive || !($active_bulletin || $active_category || $this->view->is_search || $this->view->is_forwardmail);
            return $active_archive;
        } elseif ($menuItem['special'] == 'actual_bulletin') {
            return $active_bulletin;
        }

        return false;
    }
    
}
