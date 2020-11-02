<?php
/**
 * iBulletin - View/Reprezentant.php
 *
 * @author Dominik Vesel , <dominik.vesely@pearshealthcyber.com>
 */

/**
 * View helper který umožní v templatech přidat ikonu člověka s popisem jménem reprezentanta
 * daného uživatele
 *
 * @author Dominik Vesel , <dominik.vesely@pearshealthcyber.com>
 */
class Ibulletin_View_Helper_Reprezentant {

    /**
     * @var Zend_View Instance
     */
    public $view;

    public function reprezentant($ids)
    {
        if($ids != null)
        {
            $repNames = array();
            foreach($ids as $id){
                $rep = IBulletin_MailerUsers::getRep($id);
                if(!empty($rep)){
                    $repNames[] = $rep['name']." ".$rep['surname'];
                }
            }

            if(!empty($repNames)) {
                echo '<div align="center"><a href="#" title="'.join(", \n", $repNames).'" style="cursor:default;" onclick="return false;"><img src="pub/img/admin/th_human.gif" border="no"> </a></div>';
            } else {
                echo "&nbsp;" ;
            }
        } else {
                echo "&nbsp;";
            }



    }

    /**
     * Set the view object
     *
     * @param Zend_View_Interface $view
     * @return void
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
}
