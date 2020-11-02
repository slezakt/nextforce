<?php
/**
 * Specielni trida, ktera je instancionovana (je spusten jeji constructor) pred
 * jakymkoli controllerem.
 *
 * Tento soubor se muze lisit pro ruzne projekty iTrio a iBulletin - ale ne nezbytne...... :o))
 *
 * Tato trida vykonava skoro to same co akce nejakeho controlleru pro vse co se vyskytuje
 * bud vzdy v autentizovane casti, nebo vzdy v neautentizovane casti
 */

class Run_Before_Controller
{
    var $front_controller = null;
    var $request = null;
    var $view = null;
    var $user = null;
     
    /**
     * Konstruktor - nemenit...
     */
    public function __construct()
    {
        $this->front_controller = Zend_Controller_Front::getInstance();
        $this->request = $this->front_controller->getRequest();
        $this->view = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->view;
         
        $this->run();
         
        if(Ibulletin_Auth::getActualUserId() === null){
            $this->runForUnauth();
        }
        else{
            $this->runForAuth();
        }
    }
     
    /**
     * Vse co se ma provest pred jakymkoli controllerem.
     * 
     * Pripravuje JS soubory pro dialog potvrzeni cookies. Cookies compliance
     */
    public function run(){
        $config = Zend_Registry::get('config');
        // Cookies compliance load JS souboru, pokud je treba a je povoleno
        if($config->general->cookies_compliance_on == 1 && $this->request->getCookie('cookieAgreed') != '1'){
            // Kontrola, jestli uzivatel nahodou nema ulozeno "cookieAgreed" v users_attribs    
            if($this->user === null){
                $this->user = Ibulletin_Auth::getActualUserData();
            }
            if($this->user === null || ($this->user !== null && empty($this->user['cookieAgreed']))){
                Ibulletin_Js::addJsFile('jquery.js');
            }
        }
    }
     
    /**
     * Vse co se ma provest pred controllerem kdyz neni autentizovan uzivatel.
     */
    public function runForUnauth(){
        $this->view->user_data = null;
        $this->serveSubscribe();
    }
     
    /**
     * Vse co se ma provest pred controllerem kdyz je autentizovan uzivatel.
     * 
     * Uklada do DB pripadne nastaveni uzivatele o potvrzeni cookies - users_attribs.cookieAgreed
     */
    public function runForAuth(){
        $config = Zend_Registry::get('config');
        
        if(empty($this->user)){
            $this->user = Ibulletin_Auth::getActualUserData();
        }
        
        $this->view->user_data = $this->user;
        $this->serveSubscribe();
        
        if($this->view->user_data["email"] == "" || $this->view->user_data["deleted"] != null) {
            $this->view->profileLabel = "Odebírat newsletter" ;
            $this->view->profileAction = "change";
        } else {
            $this->view->profileLabel = "Můj profil";   
            $this->view->profileAction = "index";
        }
        
        // Ulozime cookie agreed, pokud neni ulozeno v DB - ukladame do users_attribs.cookieAgreed
        if($config->general->cookies_compliance_on == 1 && $this->request->getCookie('cookieAgreed') == '1' 
            && !empty($this->user['id']) && empty($this->user['cookieAgreed']))
        {
            Users::updateUser($this->user['id'], array('cookieAgreed' => '1', 'test' => (bool)$this->user['test']));
        }
    }
     
    /**
     * Obstara zpracovani prihlaseni k odberu emailuu. To znamena upravu
     * uzivatelova e-mailu podle zadani v policku nahore. Stara se o flag
     * $view->showEmailSubscribe ktery ridi zobrazeni zadavaciho pole pro e-mail
     * ve view.
     * 
     * Bere tyto parametry:
     * subscribeemail           Email uzivatele
     * subscribename            Jmeno uzivatele v kuse
     * subscribegroup           Skupina uzivatele
     * dontsendregistration     Zcela zakazat odesilani registracniho mailu - pouziva se, pokud
     *                          chceme jen doplnit uzivatelovi informace.
     * subscribeemailnotchange  Ma se email doplnit, kdyz je prazdny a nemenit, kdyz uz je vyplneny?
     * dontregister             Pokud je timto doplnen email, nastavit uzivatele jako smazaneho?
     *                          Slouzi k vyplneni emailu, pri tom ovsem nedojde k registraci uzivatele.
     */
    public function serveSubscribe(){
        $config = Zend_Registry::get('config');
        if(empty($config->general->page_has_subscribe_in_auth)){
            // Subscribe neni pouzivan podle configu
            return;
        }
        
         $user_data =  $this->view->user_data;
         $texts = Ibulletin_Texts::getSet('runfirst.serveSubscribe');         
         
         $email = $this->request->getParam('subscribeemail', null);
         $subscribename = $this->request->getParam('subscribename', null);
         $subscribegroup = $this->request->getParam('subscribegroup', null);
         // Neposilat registracni email?
         $dontsendregistration = (bool)$this->request->getParam('dontsendregistration', null);
         // Ma se email doplnit, kdyz je prazdny a nemenit, kdyz uz je vyplneny?
         $emailnotchange = $this->request->getParam('subscribeemailnotchange', null);
         // Pokud je timto doplnen email, nastavit uzivatele jako smazaneho?
         $dontRegister = (bool)$this->request->getParam('dontregister', null);
         if(
             ($this->request->getParam('subscribeemailsubmit') || $this->request->getParam('onlyservesubscribe')) 
             /*&& !empty($email)*/)
         {
            $data = array();
            if(!empty($email)){
                // Zkontrolujeme, jestli se nema jen dovyplnit pri prazdnem
                if(!$emailnotchange || empty($user_data['email'])){
                    $data['email'] = $email;
                }
            }
            
            // Subscribename
            if(!empty($subscribename)){
                $nameA = explode(' ', $subscribename);
                // Zkusime najit zaznam z
                if(count($nameA) == 2){
                    $data['name'] = trim($nameA[0]);
                    $data['surname'] = trim($nameA[1]);
                }
                elseif(count($nameA) == 3){
                    $data['name'] = trim($nameA[1]);
                    $data['surname'] = trim($nameA[2]);
                }
                else{
                    $data['name'] = '';
                    $data['surname'] = trim($nameA[0]);
                }
            }
            else{
                $data['name'] = '';
                $data['surname'] = '';
            }
            
            // Subscribegroup
            if(!empty($subscribegroup)){
                $data['group'] = $subscribegroup;
            }
            
            
            try{
                if(!empty($data)){
                    // Pokud neni zadny uzivatel prihlasen, musime nejprve nejakeho vytvorit
                    // Je potreba na strankach bez autentizace, kde nabizime subscribe
                    try{
                        if(Ibulletin_Auth::getActualUserId() == null){
                            Ibulletin_Auth::registerUser($data);
                        }
                        else{
                            // Registracni email forcujeme jen pokud neni nastaveno dontsendregistration
                            Ibulletin_Auth::changeUsersData($data, null, true, true, !$dontsendregistration, 
                                $dontRegister);
                        }
                    }
                    catch(Ibulletin_Auth_Send_Special_Email_Exception $e){
                        Phc_ErrorLog::error('_run_before_any_controller::serveSubscribe()', 
                            'Nepodarilo se odeslat registracni email. Adresa:'.$email.' Puvodni vyjimka: '.$e);
                        $this->view->showEmailSubscribeErrorMessage = $texts->subscribeErrorMessageMailNotSent;
                    }
                    
                    $user_data['email'] = $email;
                    if(!empty($data['surname'])){
                        $user_data['surname'] = $data['surname'];
                    }
                    if(!empty($data['name'])){
                        $user_data['name'] = $data['name'];
                    }
                    $this->view->user_data = $user_data;
                    // Pokud se to povedlo a je vyplnen uzivateluv mail, predame do view jeste info
                    // o tom, ze uzivatel byl prave registrovan
                    if(!empty($data['email'])){
                        $this->view->userWasRegistered = true;
                    }
                }
            }
            catch(Ibulletin_Auth_Invalid_Email_Exception $e){
                $this->view->showEmailSubscribeErrorMessage = $texts->subscribeErrorMessage;
            }
        }
         
        if(empty($user_data['email'])){
            $this->view->showEmailSubscribe = true;
        }
        // Predvyplneni polii v subscribe
        $this->view->subscribename = (!empty($user_data['name']) ? $user_data['name'].' ' : '').
            (!empty($user_data['surname']) ? $user_data['surname'] : '');
        if(!empty($subscribename)){
            $this->view->subscribename = $subscribename;
        }
        $this->view->subscribeemail = $user_data['email'];
        if(!empty($email)){
            $this->view->subscribeemail = $email;
        }
        
        // Pokud je stranka volana jen kvuli serveSubscribe, zde skoncime.
        if($this->request->getParam('onlyservesubscribe', null)){
            if(!empty($this->view->showEmailSubscribeErrorMessage)){
                echo $this->view->showEmailSubscribeErrorMessage;
            }
            elseif(trim($email) == "" || trim($email) == "@"){
                echo $texts->subscribeErrorMessage;
            }
            exit();
        }
        
        // Znovu nacteme user data
        $this->view->user_data = Ibulletin_Auth::getActualUserData();
    }
}
