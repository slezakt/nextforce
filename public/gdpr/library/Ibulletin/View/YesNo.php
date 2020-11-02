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
class Ibulletin_View_Helper_YesNo {

    /**
     * @var Zend_View Instance
     */
    public $view;
    
 
    

    public function yesNo($value,$key,$id)
    {
    	
    	$urlHlpr = new Zend_View_Helper_Url(); 
    	
    	  if($value == 1)
        {
        
        $result = "<div align=\"center\"><a href=\"".$urlHlpr->url(array('ide'=>$id,'value'=>$key,'hodnota'=>0,'action'=>'change'))."\"><img src=\"pub/img/admin/yes.png\" border=\"no\"></a></div>";
            
        }
        else 
        {
       
       $result =  "<div align=\"center\"> <a href=\"".$urlHlpr->url(array('ide'=>$id,'value'=>$key,'hodnota'=>1,'action'=>'change'))."\"><img src=\"pub/img/admin/no.png\" border=\"no\"></a></div>";
       	
        }
        
        echo $result ;
    	
    	
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
