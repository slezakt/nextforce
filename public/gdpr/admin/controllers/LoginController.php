<?php
/**
 * Zprostredkovava formular loginu a vyvolava akce spojene s prihlasenim.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Admin_LoginController extends Zend_Controller_Action
{
    private $config = null;
    
    public function init() {
       $this->config = Zend_Registry::get('config');
    }
    
    public function indexAction() {
        
        $this->view->urlHash = $this->getUrlHash();

        $form = $this->getForm();
        $this->view->form = $form;

        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        
        $this->view->loginMessages = $this->_helper->FlashMessenger->getMessages();
        
        if ($this->_hasParam('goa_error')) {
            $this->_helper->FlashMessenger('Google Auth: Login failed');
            $redirector->gotoAndExit('index','login','admin');
        }
        
        //prihlaseni Google pomoci sifrovaneho tokenu, ktery obsahuje pole s prihlasovaci udaji od google auth service z inbox portalu
        if ($this->_hasParam('gtoken')) {
            $goa = base64_decode(rawurldecode($this->_getParam('gtoken')));
            $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
            $iv = substr($goa, 0, $iv_size);
            $goa = substr($goa, $iv_size);
            $key = $this->config->login->google->authservice->share_key;
            
            //dekodujeme token
            $gauth = json_decode(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $goa, MCRYPT_MODE_CBC, $iv)), true);

            //neni-li pole, token nebyl spravne dekodovan
            if (!is_array($gauth)) {
                $this->_helper->FlashMessenger('Google Auth: Error during authentication');
                Phc_ErrorLog::error('Login', 'Chybny prihlasovaci token, Remote address: '.$_SERVER['REMOTE_ADDR']);
                $redirector->gotoAndExit('index','login','admin');
            }

            //overeni platnosti tokenu
            if (time() > $gauth['expire']) {
                $this->_helper->FlashMessenger('Google Auth: The validity of the authentication token expired');
                $redirector->gotoAndExit('index','login','admin');
            }
            
            //ziskame login
            $glogin = $this->getGauthLogin($gauth);
            
            //mame-li login uzivatele prihlasime
            if ($glogin) {
                
                if (!$this->checkUserAllowLogin($glogin)) {
                    $this->_helper->FlashMessenger('User "'.$gauth['email'].'" is not allowed to log on');
                    $redirector->gotoAndExit('index','login','admin');
                }
                $f_priv = null;
                
                //vrati-li google auth ze user je superadmin, nastavime mu privilege_all nehlede na db  
                if ($gauth['superuser']=='1') {
                    $f_priv = array('privilege_all');
                }
                
                //keep sign
                if ($this->_hasParam('keepsign')) {
                   $authResult = Ibulletin_AdminAuth::login($glogin, null, true, $f_priv,true);
                } else {
                    $authResult = Ibulletin_AdminAuth::login($glogin, null, true, $f_priv);
                }
                
            } else {
                
                $this->_helper->FlashMessenger('Google Auth: Unknown user');
                $redirector->gotoAndExit('index','login','admin');
                
            }
            
        } /*login pomocí cookie*/ elseif(isset($_COOKIE['inbox_auth'])) {

            $cookie_auth = $_COOKIE['inbox_auth'];
            
            $clogin = Ibulletin_AdminAuth::getAuthCookieLogin($cookie_auth);

            if ($clogin) {
                
                $authResult = Ibulletin_AdminAuth::login($clogin, null, true);
                
                if ($authResult->isValid()) {

                    //kontrola platnosti auth cookie
                    if (!Ibulletin_AdminAuth::isValidCookie($clogin)) {
                        Ibulletin_AdminAuth::removeAuthCookie();
                        $redirector->gotoAndExit('index', 'login', 'admin');
                    }
                    
                    //nastavime novou cookie bez serie
                    Ibulletin_AdminAuth::setAuthCookie($clogin, false);
                    
                    //ma-li uzivatel ulozeno v db pravo cookie superuser (pouziva se pro google uzivatele které nemohou mit nastaveno privilege_all v instalaci), doplnime prava 
                    if (Ibulletin_AdminAuth::hasPermission(Ibulletin_AdminAuth::$cookie_auth_superuser_privilege)) {
                        //user data rozbijeme pole prav a ulozime do session
                        $auth_inst = Ibulletin_AdminAuth::getInstance();
                        $user_data = $auth_inst->authAdapter->getResultRowObject();
                        $user_data->privileges = explode(',', $user_data->privileges);
                        $user_data->privileges = array_merge($user_data->privileges, array('privilege_all'));
                        $auth_inst->auth->getStorage()->write($user_data);
                    }
                }
            } else {
                Ibulletin_AdminAuth::removeAuthCookie();
                $redirector->gotoAndExit('index', 'login', 'admin');
            }
        } else {

            if (!$this->getRequest()->isPost()) {
                $request = $this->getRequest();
                return;
            }

            if (!$form->isValid($_POST)) {
                // Nespravne vyplneno, znovu zobrazim
                return;
            }

            $values = $form->getValues();
            
            if (!$this->checkUserAllowLogin($values['login'])) {
                $this->_helper->FlashMessenger('User "'.$values['login'].'" is not allowed to log-in.');
                $redirector->gotoAndExit('index','login','admin');
            }
            
            if (!Ibulletin_AdminAuth::checkAdminUserEmail($values['login'])) {
                $this->_helper->FlashMessenger('User\'s email for athentication is not set, please contact &project manager.');
//                $redirector->gotoAndExit('index','login','admin');
            }
            
            $authResult = Ibulletin_AdminAuth::login($values['login'], $values['password'],null,null,$values['keepsign']);
        }

        //pokud se nepodarilo, zobrazime znovu formular
        if (!$authResult->isValid()) {
            $this->view->loginMessages = array('Wrong login or password. Please try again.');
            return;
        }

        $request = $this->getRequest();

        //pokud existuje predchozi url tak nas presmeruj
        if ($request->__isSet('prevurl')) {

            $url = $request->getParam('prevurl');
            $url = str_replace("-", "/", $url);
            $this->_redirect($url, array('prependBase' => false));
        }

        //jsme prihlaseni, presmerujeme na hlavni stranku adminu
        $redirector->gotoAndExit('index', 'index', 'admin');
        
    }

    public function getForm()
    {
        $form = new Form();
        $form->setMethod('post');
        $form->setAttrib('id', 'login');

        // Pridat username
        $login = new Zend_Form_Element_Text('login');
        $login->addValidator('alnum')
                 ->setRequired(true)
                 ->setLabel('Login: ')
                 ->addFilter('StringToLower')
                 ->setAttrib('class','input');
      
        $form->addElement($login);

        // Pridat password
        $password = new Zend_Form_Element_Password('password');
        $password->setRequired(true)
                 ->setLabel('Password:')
                 ->setAttrib('class','input');
        $form->addElement($password);
        
        $form->setAttrib('style', 'width:250px;margin:auto;');
        
        // Submit
        $form->addElement('submit', 'submit', array('label' => 'Login','style'=>'margin-top:5px;','value' => 'Login', 'class' => 'btn btn-warning'));
        
        return $form;
    }
    
    /**
     * Vrati prirazeny login dle Google Auth 
     * @param array $gauth google auth token
     * @return string
     */
    public function getGauthLogin($gauth) {
        
        $admin_email = Ibulletin_AdminAuth::getAdminUserLoginByEmail($gauth['email']);
        
        if ($admin_email) {
            return $admin_email;
        }
        
        if ($gauth['superuser']) {
            return $this->config->login->account->default_superuser->name;
        }
        
        $admin_domain = explode(',',$this->config->login->account->allow_domains);
        
        if (in_array($gauth['domain'],$admin_domain)) {
             return $this->config->login->account->default_admin->name;
        }
        
        return null;
    }
    
    /**
     * Testuje zda ma uzivatel povolene prihlaseni, pro defaultni ucty
     * @param string $login
     * @return boolean
     */
    public function checkUserAllowLogin($login) {
        //default superuser
        if (isset($this->config->login->account->default_superuser->name)) {
            if ($this->config->login->account->default_superuser->name == $login) {
               if (isset($this->config->login->account->default_superuser->allow) && $this->config->login->account->default_superuser->allow == "1") {
                   return true;
               } else {
                   return false;
               }
            }
        }
        
        //default admin
        if (isset($this->config->login->account->default_admin->name)) {
            if ($this->config->login->account->default_admin->name == $login) {
               if (isset($this->config->login->account->default_admin->allow) && $this->config->login->account->default_admin->allow == "1") {
                   return true;
               } else {
                   return false;
               }
            }
        }

        return true;
    }
    
    /**
     * Vytvori url hash pro inBox portal, která se skládá ze dvou částí, adresy instalace a url adminu
     * @return string
     */
    public function getUrlHash() {
        
        $installUrl = $this->view->serverUrl().$this->view->baseUrl();
        
        $installUrl = preg_replace('#^https?://(vyvoj\.|www\.|preview\.)?#', '', $installUrl);

        $referer = $this->view->serverUrl(true);

        $urlHash = rawurlencode(openssl_encrypt($installUrl.'%%'.$referer,"AES-256-CBC",
                $this->config->login->google->authservice->share_key, 0,
                substr(hash('sha256', $this->config->login->google->authservice->share_key), 0, 16)));
        
        return $urlHash;
    }

}
