<?php
/**
 * iBulletin - Virtualpatient.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}
class Virtualpatient_Exception extends Exception {}

/**
 * Trida poskytujici funkce pro obsluhu diskuzi a predevsim diskuznich 
 * prispevku k jednotlivym clankum nebo jinym obsahum.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Virtualpatient
{
    /**
     * Vrati zaznam podle ID nebo podle contentu a cisla listu.
     * Pokud na danem listu neni otazka, nebo otazka nebyla nalezena vraci null.
     * 
     * @param int    ID z vp_questions | nebo null pokud zadavame ID contentu a sheet
     * @param int    ID contentu y tabulky content
     * @param int    Cislo listu, na kterem ma byt otazka - atribut sheet
     * @param bool   Vratit i smazane odpovedi?, default je false
     * @return array Radek z tabulky vp_questions
     */
    public static function getQuestion($id, $content_id = null, $sheet = null, $deleted = false){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('vp_questions')
            ->limit(1);
        if($id === null && $content_id !== null && $sheet !== null){
            $sel->where(sprintf('content_id = %d', $content_id));
            $sel->where(sprintf('sheet = %d', $sheet));
        }
        elseif($id !== null && $content_id === null && $sheet === null){
            $sel->where(sprintf('id = %d', $id));
        }
        else{
            // Zadane parametry jsou zmatene
            return null;
        }
        
        if(!$deleted){
            $sel->where('deleted IS NULL');
        }
           
        $rows = $db->fetchAll($sel);
        if(!empty($rows)){
            $row = $rows[0];
            $row['deleted'] = new Zend_Date($row['deleted'], Zend_Date::ISO_8601);
        }
        else{
            $row = null; 
        }
        
        return $row;
    }
    
    /**
     * Edituje nebo vlozi novou otazku do tabulky vp_questions
     * 
     * Pro nahrazeni hodnot v DB NULLama pri editaci je nutne pouzit Zend_Db_Expr('null').
     * Pro ponechani atributu bezezmeny pouzijeme null. 
     * 
     * @param int       ID - pokud je zadano, provadime editaci, pokud je null, vkladame novy
     * @param int       ID contentu - musi byt zadano, pokud vkladame novy
     * @param int       Cislo listu ve VP na ktery tato otazka patri
     * @param string    text 
     * @param string    Text odpovedi - je zvyraznen pod pruvodnim textem
     * @return int      ID nove vlozeneho, nebo editovaneho zaznamu
     * 
     * @throws Opinions_Exception 
     *         1 Nelze vybrat operaci, neni zadane ani vp_question ID ani content ID
     *         4 Nepodarilo se updatovat zaznam.
     *         6 Nepodarilo se vlozit novy zaznam.
     */
    public static function editQuestion($id, $content_id, $sheet, $text, $question){
        if(!is_numeric($id) && !is_numeric($content_id)){
            // Neni ani id contentu ani id zaznamu z vp_questions, 
            // nelze provest ani editaci ani pridani zaznamu
            throw new Virtualpatient_Exception('Nelze vybrat operaci, neni zadane ani vp_question ID ani content ID', 1); 
        }
        
        $db = Zend_Registry::get('db');
        
        // Zjistime, jestli editujeme, nebo vkladame novy zaznam.
        if(!is_numeric($id)){
            $is_update = false;
        }
        else{
            $is_update = true;
        }
        
        // Sestavime pole dat pro ulozeni
        $data = array();
        if(is_numeric($content_id)){
            $data['content_id'] = $content_id;
        }
        if($sheet !== null){
            $data['sheet'] = $sheet;
        }
        if($text !== null){
            $data['text'] = $text;
        }
        if($question !== null){
            $data['question'] = $question;
        }
        
        // Pokud neni nic k upadtu
        if(empty($data)){
            return $id;
        }
        
        // Updatujeme?
        if($is_update){
            try{
                $db->update('vp_questions', $data, "id = $id");
            }
            catch(Exception $e){
                throw new Virtualpatient_Exception('Nepodarilo se updatovat zaznam vp_questionsn_id = "'.$id
                    .'", puvodni vyjimka: '.$e, 4);
            }
        }
        // Insertujeme
        else{
            try{
                $db->insert('vp_questions', $data);
            }
            catch(Exception $e){
                throw new Virtualpatient_Exception('Nepodarilo se vlozit novy zaznam do vp_questions, puvodni vyjimka: '.$e, 6);
            }
            $id = $db->lastInsertId('vp_questions', 'id');
        }
        
        return $id;
    }
    
    /**
     * Smaze otazku z vp_questions nastavenim deleted. Smaze i vsechny jeji odpovedi...
     * 
     * Funkce nepracuje atomicky, pokud neco nejde smazat, nebude to smazano,
     * ale vse co smazat lze se smaze.
     * 
     * @param int   ID z tabulky vp_questions.
     * @return bool True pokud se smazani podarilo.
     */
    public static function deleteQuestion($id){
        $db = Zend_Registry::get('db');
        
        $result = true;
        
        // Smazeme otazky
        $answers = self::getQuestionAnswers($id);
        foreach($answers as $answer){
            $ok = self::deleteAnswer($answer['id']);
            if(!$ok){
                $result = false;
            }
        }
        
        $affected = $db->update('vp_questions', array('deleted' => new Zend_Db_Expr('current_timestamp')), 
            sprintf('id = %d', $id));
        if($affected < 1){
            $result = false;
        }
        
        return $result;
    }
    
    /**
     * Vrati zaznam podle ID.
     * 
     * @param int    ID z vp_answers
     * @return array Radek z tabulky vp_answers
     */
    public static function getAnswer($id){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('vp_answers')
            ->where(sprintf('id = %d', $id))
            ->limit(1);
            
        $rows = $db->fetchAll($sel);
        if(!empty($rows)){
            $row = $rows[0];
            $row['deleted'] = new Zend_Date($row['deleted'], Zend_Date::ISO_8601);
        }
        else{
            $row = null; 
        }
        
        return $row;
    }
    
    /**
     * Edituje nebo vlozi novou odpoved do tabulky vp_answers
     * 
     * Pro nahrazeni hodnot v DB NULLama pri editaci je nutne pouzit Zend_Db_Expr('null').
     * Pro ponechani atributu bezezmeny pouzijeme null. 
     * 
     * @param int       ID - pokud je zadano, provadime editaci, pokud je null, vkladame novy
     * @param int       ID vp_questions - musi byt zadano, pokud vkladame novy
     * @param string    Text odpovedi na otazku
     * @param string    Text vysvetlujici odpoved
     * @param bool      Je tato odpoved spravna?
     * @return int      ID nove vlozeneho, nebo editovaneho zaznamu
     * 
     * @throws Opinions_Exception 
     *         1 Nelze vybrat operaci, neni zadane ani vp_question ID ani content ID
     *         4 Nepodarilo se updatovat zaznam.
     *         6 Nepodarilo se vlozit novy zaznam.
     */
    public static function editAnswer($id, $vp_question_id, $answer, $explanation, $is_correct, $order){
        if(!is_numeric($id) && !is_numeric($vp_question_id)){
            // Neni ani id contentu ani id zaznamu z questions, 
            // nelze provest ani editaci ani pridani zaznamu
            throw new Virtualpatient_Exception('Nelze vybrat operaci, neni zadane ani vp_question ID ani content ID', 1); 
        }
        
        $db = Zend_Registry::get('db');
        
        // Zjistime, jestli editujeme, nebo vkladame novy zaznam.
        if(!is_numeric($id)){
            $is_update = false;
        }
        else{
            $is_update = true;
        }
        
        // Sestavime pole dat pro ulozeni
        $data = array();
        if(is_numeric($vp_question_id)){
            $data['vp_question_id'] = $vp_question_id;
        }
        if($answer !== null){
            $data['answer'] = $answer;
        }
        
        if($explanation !== null){
            $data['explanation'] = $explanation;
        }
        
        if($is_correct !== null){
            $data['is_correct'] = $is_correct == true ? new Zend_Db_Expr('true') : new Zend_Db_Expr('false');
        }
        if($order !== null){
            $data['order'] = $order;
        }
        
        // Pokud neni nic k upadtu
        if(empty($data)){
            return $id;
        }
        
        // Updatujeme?
        if($is_update){
            try{
                $db->update('vp_answers', $data, "id = $id");
            }
            catch(Exception $e){
                throw new Virtualpatient_Exception('Nepodarilo se updatovat zaznam vp_answers_id = "'.$id
                    .'", puvodni vyjimka: '.$e, 4);
            }
        }
        // Insertujeme
        else{
            try{
                $db->insert('vp_answers', $data);
            }
            catch(Exception $e){
                throw new Virtualpatient_Exception('Nepodarilo se vlozit novy zaznam do vp_answers, puvodni vyjimka: '.$e, 6);
            }
            $id = $db->lastInsertId('vp_answers', 'id');
        }
        
        return $id;
    }
    
    /**
     * Smaze odpoved z vp_answers nastavenim deleted.
     * 
     * @param int   ID z tabulky vp_answers.
     * @return bool True pokud se smazani podarilo.
     */
    public static function deleteAnswer($id){
        $db = Zend_Registry::get('db');
        $affected = $db->update('vp_answers', array('deleted' => new Zend_Db_Expr('current_timestamp')), 
            sprintf('id = %d', $id));
        if($affected < 1){
            return false;
        }
        return true;
    }
    
    /**
     * Vrati odpovedi na jednu otazku. Ke kazde otazce je pridan atribut name, ktery
     * je oznacenim jmena otazky podle konfigurace (A B C D)...
     * 
     * @param int       ID otazky z vp_questions
     * @param bool      Vratit i smazane odpovedi?, default je false
     * @return array    Pole radku z vp_answers patrici zadane otazce, pripadne prazdne pole, pokud
     *                  k danemu ID otazky neexistuji zadne odpovedi. !!Klicem pole je ID odpovedi.!!
     */
    public static function getQuestionAnswers($question_id, $deleted = false){
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('vp_answers')
            ->where(sprintf('vp_question_id = %d', $question_id))
            ->order(array('order ASC', 'id ASC'));
        if(!$deleted){
            $sel->where('deleted IS NULL');
        }
            
        $rows = $db->fetchAssoc($sel);
        
        // Pokud se nic nenaslo, vracime prazdne pole
        if(empty($rows)){
            return array();
        }
        
        // Pridame spravne pojmenovani odpovedi
        $answer_naming = $config->virtual_patient->getCommaSeparated('answer_naming');
        $i = 0;
        foreach($rows as $key => $row){
            $rows[$key]['name'] = $answer_naming[$i];
            $i++;
        }
        
        return $rows;
    }
    
    /**
     * Vrati pole obsahujici pokusy uzivatele pri odpovidani na otazku - klicem je ID odpovedi 
     * a hodnotou je cely zaznam odpovedi z vp_answers. 
     *
     * @param int    ID otazky
     * @param int    ID uzivatele, pokud je null, pokusime se pouzit aktualniho
     * @return array Pole obshujici ID z vp_answers jako klic a pole radku odpovedi z vp_answers
     * 
     * @throws Virtualpatient_Exception kod:
     *      2 - Zadany uzivatel nebyl nalezen
     */
    public static function getUsersAttempts($question_id, $user_id = null){
        if($user_id == null){
            // Pokusime se jako user_id pouzit ID aktualniho uzivatele.
            $user_id = Ibulletin_Auth::getActualUserId();
        }
        
        if($user_id !== null){
            try{
                // Zjistime, jestli uzivatel skutecne existuje v DB
                Users::getUser($user_id);
            }
            catch(Users_User_Not_Found_Exception $e){
                throw new Virtualpatient_Exception('Zadany uzivatel user_id="'.$user_id.'" nebyl nalezen.', 2);
            }
        }
        
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('vp_users_answers')
            ->where(sprintf('user_id = %d', $user_id))
            ->where(sprintf('vp_question_id = %d', $question_id))
            ->order('timestamp ASC');
        $rows = $db->fetchAll($sel);
        
        // Ziskame vsechny odpovedi na otazku kvuli poradi a pojmenovani podle nastaveni 
        // v config->virtual_patient->answer_naming
        $answers = self::getQuestionAnswers($question_id);
        
        $attempts = array();
        foreach($rows as $row){
            $attempts[$row['vp_answer_id']] = $answers[$row['vp_answer_id']];
        }
        
        return $attempts;
    }
    
    /**
     * Zapise pokus odpovedi uzivatele do DB. Vraci true, pokud byla odpoved spravna a false, pokud
     * byla odpoved nespravna.
     * 
     * Pokud pokus v DB jiz je zapsan, neprovadi se zadny zaznam do DB.
     * 
     * @param int       ID otazky z tabulky vp_questions
     * @param int       ID odpovedi z tabulky vp_answers nebo null, pokud zadavame odpoved jmenem
     * @param int       Nazev odpovedi podle answer_naming
     * @param int       ID uzivatele z tabulky users, pokud je null, pokusime se pouzit
     *                  aktualne prihlaseneho uzivatele.
     * @return bool     Byla tato odpoved spravna?
     * 
     * @throws Virtualpatient_Exception kod:
     *      2 - Zadany uzivatel nebyl nalezen
     *      8 - Otazka neexistuje, nebo nema zadne odpovedi, proto neni mozne zapsat pokus
     *      10 - Odpoved v zadane otazce neexistuje.
     *      16 - Nepodarilo se zapsat pokus odpovedet na otazku. Muze se jednat o duplicitni zaznam.
     */
    public static function writeAttempt($question_id, $answer_id, $answer_name = null, $user_id = null){
        if($user_id == null){
            // Pokusime se jako user_id pouzit ID aktualniho uzivatele.
            $user_id = Ibulletin_Auth::getActualUserId();
        }
        
        if($user_id !== null){
            try{
                // Zjistime, jestli uzivatel skutecne existuje v DB
                Users::getUser($user_id);
            }
            catch(Users_User_Not_Found_Exception $e){
                throw new Virtualpatient_Exception('Zadany uzivatel user_id="'.$user_id.'" nebyl nalezen.', 2);
            }
        }
        
        // Ziskame odpovedi na otazku, tim overime existenci otazky i odpovedi
        $answers = self::getQuestionAnswers($question_id);
        
        if(empty($answers)){
            // Otazka neexistuje, nebo nema zadne odpovedi, neni proto mozne zapsat pokus
            throw new Virtualpatient_Exception("Otazka neexistuje, nebo nema zadne odpovedi, proto neni mozne zapsat pokus. question_id='$question_id', answer_id='$answer_id'", 8);
        }
        
        if($answer_id === null && $answer_name !== null){
            // Pokusime se najit answer_id podle pojmenovani otazky
            foreach($answers as $answer){
                if($answer['name'] == $answer_name){
                    $answer_id = $answer['id'];
                    break;
                }
            }
        }
        if($answer_id === null || empty($answers[$answer_id])){
           // Neexistuje zadana odpoved. 
           throw new Virtualpatient_Exception("Odpoved v zadane otazce neexistuje. Neni mozne zapsat pokus. question_id='$question_id', answer_id='$answer_id', answer_name='$answer_name'", 8);
        }
        
        // Zjistime, jestli nebyl tento pokus jiz zapsan, pokud byl, skoncime
        $attempts = self::getUsersAttempts($question_id, $user_id);
        if(array_key_exists($answer_id, $attempts)){
             return $answers[$answer_id]['is_correct'];
        }
        
        
        // Vsechno bylo overeno, muzeme zapsat pokus
        $db = Zend_Registry::get('db');
        $data = array(
            'user_id' => $user_id,
            'vp_question_id' => $question_id,
            'vp_answer_id' => $answer_id);
        try{
            $db->insert('vp_users_answers', $data);
        }
        catch(Exception $e){
            throw new Virtualpatient_Exception('Nepodarilo se zapsat pokus odpovedet na otazku. Muze se jednat o duplicitni zaznam. Puvodni vyjimka: '."\n$e");
        }
        
        return $answers[$answer_id]['is_correct'];
    }
    
    
    /**
     * Vrati pole obsahujici cisla listu zadaneho contentu, ktere maji byt zamcene pred zodpovezenim otazky 
     * a pole s cisly listu, ktere maji byt zamcene po spravnem zodpovezeni otazky.
     * 
     * @param int    ID contentu z tabulky content
     * @param int    Pocet listuu v danem contentu
     * @param int    ID uzivatele z tabulky users, pokud je null, pokusime se pouzit
     *               aktualne prihlaseneho uzivatele.
     * 
     * @return  Pole obsahujici dve pole 'before', 'after' s cisly zamcenych listu pred 
     *          a po spravnem zoodpovezeni otazky 
     * 
     * @throws Virtualpatient_Exception kod:
     *      2 - Zadany uzivatel nebyl nalezen
     *      32 - Nebyl zadan uzivatel a zadny uzivatel neni prihlasen.
     */
    public static function getLockedSheetList($content_id, $sheet_count, $current_sheet, $user_id = null){
        if($user_id == null){
            // Pokusime se jako user_id pouzit ID aktualniho uzivatele.
            $user_id = Ibulletin_Auth::getActualUserId();
        }
        
        if($user_id !== null){
            try{
                // Zjistime, jestli uzivatel skutecne existuje v DB
                Users::getUser($user_id);
            }
            catch(Users_User_Not_Found_Exception $e){
                throw new Virtualpatient_Exception('Zadany uzivatel user_id="'.$user_id.'" nebyl nalezen.', 2);
            }
        }
        else{
            throw new Virtualpatient_Exception('Nebyl zadan uzivatel a zadny uzivatel neni prihlasen.', 32);
        }
        
        $db = Zend_Registry::get('db');
        
        // Najdeme vsechny listy v tomto contentu, pro ktere existuje otazka
        $sel = new Zend_Db_Select($db);
        $sel->from(array('q' => 'vp_questions'), array('sheet'))
            ->where(sprintf('q.content_id = %d', $content_id))
            ->where('q.deleted IS NULL')
            ->order('q.sheet ASC');
        $rows = $db->fetchAll($sel);
        $questions = array();
        foreach($rows as $row){
            $questions[] = $row['sheet'];
        }
        
        // Najdeme listy, na ktere bylo jiz spravne zodpovezeno
        $sel = new Zend_Db_Select($db);
        $sel->from(array('q' => 'vp_questions'), array('sheet'))
            ->joinInner(array('a' => 'vp_answers'), 'a.vp_question_id = q.id', array())
            ->joinInner(array('ua' => 'vp_users_answers'), 'ua.vp_answer_id = a.id', array())
            ->where(sprintf('q.content_id = %d', $content_id))
            ->where(sprintf('ua.user_id = %d', $user_id))
            ->where('a.is_correct = true')
            ->where('q.deleted IS NULL')
            ->where('a.deleted IS NULL')
            ->order('q.sheet ASC');
        $rows = $db->fetchAll($sel);
        $correctlyAnsw = array();
        foreach($rows as $row){
            $correctlyAnsw[] = $row['sheet'];
        }
        
        // Vybereme listy, ktere maji byt zamcene
        $curr_locked = array(); // Listy zamcene pred spravnou odpovedi na aktualni otazku
        $next_locked = array(); // Listy zamcene po spravne odpovedi na otazku
        $curr_first_locked = 1000000000; // Snad dostacujici cislo vetsi nez jakykoli pocet listuu
        $next_first_locked = 1000000000;
        for($i=1; $i<=$sheet_count; $i++){
            if(!in_array($i,$correctlyAnsw)){
                // Pokud nema list spravnou odpoved a je na nem otazka, jsou vsechy
                // nasledujici aktualne zamceny
                if(in_array($i,$questions) && $i < $curr_first_locked){
                    $curr_first_locked = $i;
                }
            }
            
            // Hledame list, ktery bude prvni zamceny po spravne odpovedi na aktualni list
            if($i >= $curr_first_locked && $current_sheet == $curr_first_locked && $i < $next_first_locked){
                if(!in_array($i,$correctlyAnsw) && $i != $current_sheet){
                    if(in_array($i,$questions) && $i < $next_first_locked){
                        $next_first_locked = $i;
                    }
                }
            }
            
            // Pridame listy do pole uzamcenych
            if($i > $curr_first_locked){
                $curr_locked[] = $i;
            }
            if($i > $next_first_locked){
                $next_locked[] = $i;
            }
        }
        
        // Pokud je aktualni list jiz spravne zodpovezen, jsou next locked stejne jako current
        if(in_array($current_sheet,$correctlyAnsw) || !in_array($current_sheet,$questions)){
            $next_locked = $curr_locked;
        }
        
        //print_r($curr_locked);
        //print_r($next_locked);
        
        return array('before' => $curr_locked, 'after' => $next_locked);
    }
}
