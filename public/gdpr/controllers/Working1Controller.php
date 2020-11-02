<?php

/**
 * 
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Working1Controller extends Zend_Controller_Action
{
    /**
     * Opravi konzistenci dat v page_views po pridani propojeni na users_links_tokens.
     * 
     * !!! PRESUNUTO DO admin/ServiceController.php
     */
    public function updatepageviewAction()
    {
        $db = Zend_Registry::get('db');
        
        $sel = $db->select();
        $sel->from('page_views')
            //->where('users_links_tokens_id IS NULL')
            ->where("url~*'/token/'")
            ->where("url !~* 'register/'")
            ->where("url !~* 'emailconfirm/track/token/'");
            //->limit(10);
            
        $rows = $db->fetchAll($sel);
        
        foreach($rows as $row){
            $token = substr($row['url'], 7, 15);
            $sel = $db->select()
                ->from('users_links_tokens')
                ->where("token='".$token."'")
                ->limit(1);
            $ult_rows = $db->fetchAll($sel);
            if(isset($ult_rows[0])){
                $ult_row = $ult_rows[0];
            }
            else{
            echo '!!!!! '.$row['id'].' | '; // Chyba pri propojivani daneho prvku
                $ult_row = array('id' => null);
            }
            
            $db->update('page_views', array('users_links_tokens_id' => $ult_row['id']), 'id='.$row['id']);
            
            echo $token.'<br/>';
        }

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');
    }
}
