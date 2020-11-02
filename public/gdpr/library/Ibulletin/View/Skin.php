<?
/**
 * iBulletin - View/Skin.php
 *
 * View helper vraci URL cestku k aktualne zvolenemu skinu (vcetne base URL)
 * Ridi se $this->view->actual_bul_row
 *
 */
class Ibulletin_View_Helper_Skin extends Zend_View_Helper_Abstract {

   public function skin($path) {
       $baseUrlHlpr = new Zend_View_Helper_BaseUrl();
       if (($bulletinRow = $this->view->bulletinRow)) {
           if (($skin = $bulletinRow['skin'])) {
               return $baseUrlHlpr->baseUrl(Skins::getBasePath() . DIRECTORY_SEPARATOR . $skin
                   . DIRECTORY_SEPARATOR. ltrim($path,'\\/'));
           }
       }
       // try config skin
       if (file_exists(Skins::getActualSkinPath().'/'.ltrim($path,'\\/'))) {
           return Skins::getActualSkinUrl().'/'.ltrim($path,'\\/');
       // fallback to pub
       } else {
           return $baseUrlHlpr->baseUrl('pub/' . ltrim($path,'\\/'));
       }

   }

}