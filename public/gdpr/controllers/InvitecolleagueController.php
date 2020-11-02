<?php
/**
 * Stranka s preposlanim emailu kolegovy
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class InvitecolleagueController extends Zend_Controller_Action
{
     
    /**
     * Preposila mail kolegovi
     */
    public function indexAction()
    {
        $this->getHelper('viewRenderer')->setNoRender(true);
        
        $db = Zend_Registry::get('db');
        
        $user_id = Ibulletin_Auth::getActualUserId();                
        $user_id = intval($user_id);
        
        $request = $this->getRequest();
        
        $bulletin = $request->getParam("bulletin");
        
        // zjisti token
        $_tk = $db->select()
                  ->from('users_emails')
                  ->where('user_id = ?', $user_id);  
        $token = $db->fetchRow($_tk, array(), Zend_Db::FETCH_OBJ);
        $token = $token->token;
             
        // pokud je prihlaseny uzivatel, ziskej jeho token a presmeruj na pozvani kolegy
        if(!empty($token) || !empty($bulletin)) {
        	
       
        if (!empty($token) || empty($bulletin)) {    
            $params = array(
                'token' => $token,
            );
            
        } 
        if (!empty($bulletin)) {
        	
        	$params = array(
                'bulletin' => $bulletin,
            );
        	
        }    
            $this->getHelper('redirector')->setExit(true)
                    ->goto('index','forwardmail', null, $params);
        
        
        }  else {
            $this->getHelper('redirector')->setExit(true)
                 ->goto('index','bulletin');
        }
    }   
}
