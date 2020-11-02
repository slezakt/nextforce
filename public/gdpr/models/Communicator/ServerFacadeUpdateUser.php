<?php
/**
 * ServerFacadeUpdateUser.php
 * 
 * @author  Bc. Petr Skoda
 */

/**
 * 
 * 
 * @author Bc. Petr Skoda
 */
class Communicator_ServerFacadeUpdateUser extends Communicator_ServerFacadeAbstract
{
    /**
     * Provede update uzivatele podle dat dorucenych z inDirectoru.
     * 
     * Pokud je id v poli data null, znamena to, ze ma byt vytvoren novy uzivatel.
     * 
     * Pokud novy uzivatel koliduje s jednim uzivatelem, je novy uzivatel timto uzivatelem,
     * pokud koliduje s vice uzivateli, je vybran ten s nejdulezitejsi kolizi - email, login, authcode
     * a atributy kolidujici s ostatnimi uzivateli jsou vyprazdneny (nezmeni se). Je zalogovana chyba pri teto operaci,
     * protoze by se takovato situace nemela objevovat. 
     * 
     * @param string $key Klic aplikace
     * @param int|null $userId LID uzivatele
     * @param struct $data Pole dat uzivatele k ulozeni v teto aplikaci. Povinne je data['id'], 
     *                     ktere obsahuje lokalni ID uzivatele v teto aplikaci nebo null, 
     *                     pokud se ma zalozit uzivatel novy.
     * @param Zend_Db_Adapter_Abstract $db DB adapter
     * 
     * @return int ID uzivatele v teto aplikaci.
     * 
     * @throws Communicator_ServerFacadeException Code 0 v pripade 
     */
    public function updateUser($key, $userId, $data, $db = null)
    {
        try{
            if($db instanceof Zend_Db_Adapter_Abstract){
                $this->db = $db;
            }
            
            
            if(!$this->checkKey($key)){
                throw new Communicator_ServerFacadeException("Byl predan nespravny klic aplikace klic: '$key'.", 0);
            }
            
            // Budeme updatovat, nebo vytvaret noveho uzivatele?
            if(!empty($data['id'])){
                // UPDATUJEME
                $id = $data['id'];
                unset($data['id']);
                $result = Users::updateUser($id, $data, false, array(), true);
            }
            else{
                // VYTVARIME NOVEHO
                try{
                    $result = Users::updateUser(null, $data, true, array('email', 'login', 'authcode'), true);
                }
                catch(Users_Exception $e){
                    // Chytame jen kolizi pri merge
                    // Pokud nastane ulozime uzivatele jako uzivatele s kolizi na mailu  nebo loginu
                    // ostatni kolidujici parametry nebudeme nastavovat.
                    if($e->getCode() == 128){
                        if(isset($e->data['email'])){
                            $choosenUserId = $e->data['email'];
                        }
                        elseif(isset($e->data['login'])){
                            $choosenUserId = $e->data['login'];
                        }
                        else{
                            if(isset($e->data['authcode'])){
                                $choosenUserId = $e->data['authcode'];
                            }
                        }
                        // Odtranime kolidujici atributy ostatnich kolidujicich uzivatelu nez je nas vybrany
                        foreach($e->data as $attr => $userId){
                            if($userId !== $choosenUserId){
                                unset($data[$attr]);
                            }
                        }
                        
                        // Zalogujeme chybu - tohle by se nemelo stavat a je nutne to nejak resit
                        Phc_ErrorLog::error('Communicator_ServerFacadeUpdateUser::updateUser()',
                            "Data uzivatele od indirectoru koliduji s vice uzivateli v DB. Byly odebrany ".
                            "atributy kolidujici s ostatnimi uzivateli mimo userId: $$choosenUserId. Detaily:\n".
                            $e);
                        
                        // Provedeme UPDATE uzivatele
                        $result = Users::updateUser($choosenUserId, $data, false, array(), true);
                    }
                    else{
                        throw $e;
                    }
                }
            }
        }
        catch(Exception $e){
            // Chytame vse, abychom to mohli zalogovat
            Phc_ErrorLog::warning('Communicator_ServerFacadeUpdateUser::updateUser()', $e);
            throw $e;
        }
        
        return $result;
    }
}
