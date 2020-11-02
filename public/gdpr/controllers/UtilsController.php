<?php
/**
 * Controller pro ruzne akce pristupne z adminu i frontu jako napr. konverze obrazku
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class UtilsController extends Zend_Controller_Action {
   
        /**
      * Převod svg na png
      */
     public function convertsvgAction() {
        $im = new Imagick();
      
        if ($this->getRequest()->isPost()) {
            $svg = $this->getRequest()->getPost('data');
            preg_match_all('/(xlink:href=)"(.*)"/Ui',$svg,$matches);
            foreach ($matches[2] as $m) {
               $data = file_get_contents($m);
               //zmenseni obrazku v grafu (fixne na sirku 200px)
               $im->readimageblob($data); 
               $im->setformat('png32');
               $size = $im->getimagegeometry();
               $ratio = 200/$size['width'];
               $im->resizeimage($size['width']*$ratio, $size['height']*$ratio, imagick::FILTER_LANCZOS, 1);
               $base64 = 'data:image/png;base64,' . base64_encode($im); 
               $svg = str_replace($m, $base64, $svg);
               $im->clear();
            }
            $im->destroy();
            $filename = $this->getRequest()->getPost('filename'); 
            try {
                $png = Utils::svgtopng($svg);
                header("Content-Type: application/x-download");
                header("Content-Disposition: attachment; filename=$filename.png");
                echo $png;
                exit();
            } catch (ImagickException $ex) {
                Phc_ErrorLog::error('Admin_Service_Convertsvg', $ex->getMessage());
            }
            
        }
    }
}
