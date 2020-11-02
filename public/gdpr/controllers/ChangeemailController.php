<?php
/**
 * Stranka pro potvrzeni zmeny adresy
 *  TODO: nefunkcni a DEPRECATED? neexistuje tabulka 'change_list'
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class ChangeemailController extends Zend_Controller_Action
{  
      
    /**
     * Provede zmenu adresy
     */
    public function indexAction()
    {
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');          
        $config_texts = Ibulletin_Texts::getSet('changeemail');  
        
        $request = $this->getRequest();
        $token = $request->getParam('token');  

        if (!empty($token)) {
            
            try {
                // vybrat email, co se zapise
	            $select = $db->select()
	                         ->from('change_list',array('email_next', 'user_id'))
	                         ->where('token = ?', $token)
	            ;
	            $tochange = $db->fetchRow($select);
	            
	            // uloz novou adresu
	            $params = array(
	               'email' => $tochange['email_next']
	            );	            
	            //$db->update('users', $params, sprintf('id = %d', $tochange['user_id']));
	            Users::updateUser($tochange['user_id'], $params, true);	            	           

	            // zapis zmenu, kdy k tomu doslo do changelistu
	            $params = array(
                   'change_date' => new Zend_Db_Expr('current_timestamp')
                );                         
	            $db->update('change_list', $params, "token = '".$token."'");
	            
	            // zobraz uspech
	            $this->view->status = $config_texts->index->done;
            }
            catch (Exception $e) {
                echo $e->getMessage();
                Phc_ErrorLog::error('ChangeEmail::indexAction()', $config_texts->index->error);
                $this->view->status = $config_texts->index->error;
            }
            
        }
        else {
            Phc_ErrorLog::error('ChangeEmail::indexAction()', $config_texts->index->notoken);
            $this->view->status = $config_texts->index->notoken;
        }
    }  
    
    /**
     * neni zadana hodnota tokenu, tak presmeruj na index 
     * 
     */
    public function tokenAction() {
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');               
        $redirector->gotoRouteAndExit(array('controller' =>'changeemail', 'action' => 'index'));    
    }
}
