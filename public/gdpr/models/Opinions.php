<?php
/**
 * iBulletin - Opinions.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}
class Opinions_Exception extends Exception {}

/**
 * Trida poskytujici funkce pro obsluhu diskuzi a predevsim diskuznich 
 * prispevku k jednotlivym clankum nebo jinym obsahum.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Opinions
{
    /**
     * Prida nebo edituje prispevek v databazi. Pro editaci je nutne zadat ID prispevku,
     * ktery ma byt editovan.
     * 
     * Pro nahrazeni hodnot v DB NULLama pri editaci je nutne pouzit Zend_Db_Expr('null').
     * Pro ponechani atributu bezezmeny pouzijeme null. 
     * 
     * @param int     ID contentu, ke kteremu tento prispevek nalezi. (neni treba zadavat pri
     *                editaci, kdyz je zadano id prispevku)
     * @param int     ID z tabulky opinions k editaci, pokud pridavame novy zaznam je null.
     * @param int     ID z tabulky users, pokud je null, je pouzit prave prihlaseny uzivatel,
     *                pokud je nejaky prihlasen.
     * @param string  jmeno
     * @param string  prijmeni
     * @param string  titul
     * @param string  zamestnani
     * @param string  email
     * @param string  text
     * @param Zend_Date timestamp
     *
     * @return int    ID editovaneho, nebo nove vlozeneho zaznamu
     * 
     * @throws Opinions_Exception 
     *         1 Nelze vybrat operaci, neni zadane ani opinion ID ani content ID
     *         2 Zadany uzivatel nebyl nalezen.
     *         4 Nepodarilo se updatovat zaznam.
     *         6 Nepodarilo se vlozit novy zaznam.
     */
    public static function edit($content_id = null, $id = null, $user_id = null, $name, $surname, $title, $employment, 
                                $email, $text, $timestamp = null){
        $db = Zend_Registry::get('db');
                                    
        if(!is_numeric($id) && !is_numeric($content_id)){
            // Neni ani id contentu ani id zaznamu z opinions, 
            // nelze provest ani editaci ani pridani zaznamu
            throw new Opinions_Exception('Nelze vybrat operaci, neni zadane ani opinion ID ani content ID', 1); 
        }
        
        if(!is_numeric($user_id)){
            // Pokusime se jako user_id pouzit ID aktualniho uzivatele.
            $user_id = Ibulletin_Auth::getActualUserId();
        }
        
        if($user_id !== null && !is_numeric($id)){
            try{
                // Zjistime, jestli uzivatel skutecne existuje v DB
                Users::getUser($user_id);
            }
            catch(Users_User_Not_Found_Exception $e){
                throw new Opinions_Exception('Zadany uzivatel user_id="'.$user_id.'" nebyl nalezen.', 2);
            }
        }
        
        // Zjistime, jestli editujeme, nebo vkladame novy zaznam.
        if(!is_numeric($id)){
            $is_update = false;
        }
        else{
            $is_update = true;
        }
        
        // Sestavime pole dat pro ulozeni
        $data = array();
        if(is_numeric($user_id)){
            $data['user_id'] = $user_id;
        }
        if(is_numeric($content_id)){
            $data['content_id'] = $content_id;
        }
        if($name !== null){
            $data['name'] = (string)substr($name, 0, 40);
        }
        if($surname !== null){
            $data['surname'] = (string)substr($surname, 0, 40);
        }
        if($title !== null){
            $data['title'] = (string)substr($title, 0, 20);
        }
        if($employment !== null){
            $data['employment'] = (string)substr($employment, 0, 300);
        }
        if($email !== null){
            $data['email'] = (string)substr($email, 0, 300);
        }
        if($text !== null){
            $data['text'] = $text;
        }
        if($timestamp instanceof Zend_Date){
            $data['timestamp'] = $timestamp->get(Zend_Date::ISO_8601);
        }
        
        // Pokud neni nic k upadtu
        if(empty($data)){
            return $id;
        }
        
        // Updatujeme?
        if($is_update){
            try{
                $db->update('opinions', $data, "id = $id");
            }
            catch(Exception $e){
                throw new Opinions_Exception('Nepodarilo se updatovat zaznam opinion_id = "'.$id
                    .'", puvodni vyjimka: '.$e, 4);
            }
        }
        // Insertujeme
        else{
            try{
                $db->insert('opinions', $data);
            }
            catch(Exception $e){
                throw new Opinions_Exception('Nepodarilo se vlozit novy zaznam do opinions, puvodni vyjimka: '.$e, 6);
            }
            $id = $db->lastInsertId('opinions', 'id');
        }
        
        return $id;
    }
    
    /**
     * Smaze prispevek z diskuze.
     * 
     * @param int   ID z tabulky opinions.
     * @return bool True pokud se smazani podarilo.
     */
    public static function delete($id){
        $db = Zend_Registry::get('db');
        $affected = $db->update('opinions', array('deleted' => new Zend_Db_Expr('current_timestamp')), 
            sprintf('id = %d', $id));
        if($affected < 1){
            return false;
        }
        return true;
    }
    
    /**
     * Vrati pocet dotazu pro dany content.
     * 
     * @param int   ID contentu
     * @param bool  Pocitat i smazane?
     * @return int  Pocet zaznamu v opinions pro dany content
     */
    public static function getCount($content_id, $deletedToo = false){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('opinions', 'count(*)')
            ->where(sprintf('content_id = %d', $content_id));
        if(!$deletedToo){
           $sel->where('deleted IS NULL'); 
        }
        $count = $db->fetchOne($sel);
        return $count;
    }
    
    /**
     * Vrati pole jednotlivych zaznamu opinions pro zadany content_id.
     * 
     * @param int       ID contentu
     * @param int       Cislo pozadovane stranky vysledkuu
     * @param int       Pocet zaznamu na jednu stranku
     * @param mixed     String nebo array razeni vysledku (cast klauzule ORDER - kazda polozka pole
     *                  je retezec "nazev_atributu ASC/DESC"
     * @param bool      Vypisovat i smazane zaznamy?
     * @return array    Pole radku jednotlivych nazoru
     */
    public static function getList($content_id, $page = null, $rowCount = null, $order = 'timestamp DESC', 
        $with_deleted = false)
    {
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('opinions')
            ->where(sprintf('content_id = %d', $content_id))
            ->order($order);
        if($page !== null && $rowCount !== null){
            $sel->limitPage($page, $rowCount);
        }
        if(!$with_deleted){
            $sel->where('deleted IS NULL');
        }
        
        $rows = $db->fetchAll($sel);
        
        // Z created udelame date object
        foreach($rows as $key => $row){
            $rows[$key]['timestamp'] = new Zend_Date($row['timestamp'], Zend_Date::ISO_8601);
        }
        
        return $rows;
    }
    
    
     /**
     * Vrati Db Select zaznamu opinions pro zadany content_id.
     * 
     * @param int ID contentu
     * @return Zend_Db_Select
     */
    public static function getOpinionsQuery($content_id)
    {
        $db = Zend_Registry::get('db');
        $sel = new Zend_Db_Select($db);
        $sel->from('opinions')
            ->where(sprintf('content_id = %d', $content_id))
            ->where('deleted IS NULL')
            ->order('timestamp DESC');
        return $sel;
    }
    
   
    
    
    /**
     * Vrati zaznam podle ID.
     * 
     * @param int    ID z opinions
     * @return array Radek z tabulky opinions
     */
    public static function get($id){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('opinions')
            ->where(sprintf('id = %d', $id))
            ->limit(1);
            
        $rows = $db->fetchAll($sel);
        if(!empty($rows)){
            $row = $rows[0];
            $row['timestamp'] = new Zend_Date($row['timestamp'], Zend_Date::ISO_8601);
        }
        else{
            $row = null; 
        }
        
        return $row;
    }
    
    
    /**
     * Vrati poslendi zaznam pro daneho uzivatele - pouzivame pro predvyplneni informaci
     * o uzivateli ve formulari. Nezahrnuji se smazane zaznamy.
     * 
     * @param int    ID uzivatele z tabulky users
     * @return array Radek z tabulky opinions
     */
    public static function getLastUsersRecord($user_id){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('opinions')
            ->where(sprintf('user_id = %d', $user_id))
            ->where('deleted IS NULL')
            ->limit(1)
            ->order('timestamp DESC');
            
        $rows = $db->fetchAll($sel);
        if(!empty($rows)){
            $row = $rows[0];
            $row['timestamp'] = new Zend_Date($row['timestamp'], Zend_Date::ISO_8601);
        }
        else{
            $row = null; 
        }
        
        return $row;
    }
}
