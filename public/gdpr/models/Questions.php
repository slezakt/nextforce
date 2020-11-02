<?php
/**
 * iBulletin - Questions.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}

/**
 * Codes:
 * 1 - pokud nelze identifikovat typ odpovedi
 * 2 - Nespravny format predaneho parametru.
 */
class Questions_Exception extends Exception {}

/**
 * Trida poskytujici funkce pro obsluhu datovych struktur otazek a odpovedi -
 * tabulky: questions, answers, users_answers. Umoznuje ruznym contentum pristupovat
 * k temto strukturam a vyuzivat je.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Questions
{
    /**
     * ID uzivatele
     */
    var $user_id = null;

    /**
     * ID contentu
     */
    var $content_id = null;

    /**
     * Inforomace o problemech pri ukladani odpovedi na otazku nebo dalsich akcich,
     * tyto problemy nejsou kriticke, proto pro ne neni vyhozena vyjimka, jen jsou logovany
     * do error logu.
     */
    var $info = array();



    /**
     * Vytvori instanci Questions
     *
     * @param   Int  ID contentu content_id
     * @param   Int  ID uzivatele, pokud neni zadano, pouzije se aktualni
     */
    public function __construct($content_id, $user_id = null)
    {
        // User id
        if(empty($user_id)){
            $this->user_id = Ibulletin_Auth::getActualUserId();
        }
        else{
            $this->user_id = $user_id;
        }

        // content_id
        $this->content_id = $content_id;

    }

    /**
     * Vrati radky tabulky questions podle id contentu a id slidu (optional)
     *
     * @param int       Id do tabulky slides
     * @return array    Pole poli radkuu z questions
     */
    public function getList($slide_id = null){
        $db = Zend_Registry::get('db');

        $sel = new Zend_Db_Select($db);
        $sel->from('questions')
            ->where('content_id = ?', (int)$this->content_id);

        if ($slide_id !== null) {
            if (is_numeric($slide_id)) {
                $sel->where('slide_id = ?', (int)$slide_id);
            }  else {
                throw new Questions_Exception('parameter slide_id = `'.$slide_id.'` neni cislo.');
            }
        }

        $rows = $db->fetchAll($sel);
        $assoc = array();
        if(is_array($rows)){
            foreach($rows as $key => $row){
                $assoc[$row['id']] = $row;
            }
        }

        return $assoc;
    }

    /**
     * Vrati radky tabulky answers podle id z questions
     *
     * @param int       Id do tabulky questions
     * @return array    Pole poli radkuu z questions
     */
    public function getAnswers($question_id) {
        $db = Zend_Registry::get('db');

        $sel = new Zend_Db_Select($db);
        $sel->from('answers');

        if ($question_id !== null) {
            if (is_numeric($question_id)) {
                $sel->where('question_id = ?', (int)$question_id);
            } else {
                throw new Questions_Exception('parameter question_id = `'.$question_id.'` neni cislo.');
            }
        }

        $rows = $db->fetchAll($sel);
        $assoc = array();
        if(is_array($rows)){
            foreach($rows as $key => $row){
                $assoc[$row['id']] = $row;
            }
        }

        return $assoc;
    }


    /**
     * Vrati statisticka data celeho jednoho contentu. Pole obsahuje souhrny odpovedi jednotlivych otazek.
     * 
     * @param int   $slideNum       Cislo slide z tabulky slides (nejedna se o ID)
     * 
     * @return array    slides[_slide_number_].questions[_question_number_].answerStats[answer_num, answer_title, count, numeric_sum] 
     *                  Pole obsahujici statisticka data nebo null, pokud neni co vypsat.
     */
    public function getContentStats($slideNum = null)
    {
        // Data ziskame z metody slouzici pro vypis prehledu odpovedi v monitoringu a v adminu questions
        $data = Statistics::getAnswersOverviewTable($this->content_id,null,null,null,$slideNum,null);
        
        if(empty($data)){
            return null;
        }
        
        // Dame data answers do zvlastniho pole
        $slides = array();
        foreach($data as $row){
            if(!isset($slides['slides'])){
                $slides['slides'] = array();
            }
            if(!isset($slides['slides'][$row['slide_num']])){
                $slides['slides'][$row['slide_num']] = array();
            }
            if(!isset($slides['slides'][$row['slide_num']]['questions'])){
                $slides['slides'][$row['slide_num']]['questions'] = array();
            }
            if(!isset($slides['slides'][$row['slide_num']]['questions'][$row['question_num']])){
                $slides['slides'][$row['slide_num']]['questions'][$row['question_num']] = array();
            }
            if(!isset($slides['slides'][$row['slide_num']]['questions'][$row['question_num']]['answerStats'])){
                $slides['slides'][$row['slide_num']]['questions'][$row['question_num']]['answerStats'] = array();
            }
            
            $slides['slides'][$row['slide_num']]['questions'][$row['question_num']]['answerStats'] = array(
                //'answer_id'=>$row['answer_id'], 
                'answer_num'=>$row['answer_num'], 
                'answer_title'=>$row['answer_title'], 
                'count'=>$row['count'],
                'numeric_sum'=>$row['numeric_sum'],
                'numeric_avg'=>$row['numeric_avg']
            );
            
            if($row['answer_type'] == 'b') {
                $slides['slides'][$row['slide_num']]['questions'][$row['question_num']]['answerStats'] = array_merge(
                        $slides['slides'][$row['slide_num']]['questions'][$row['question_num']]['answerStats'],array(
                   'b_answer_true' => $row['b_answer_true'],
                   'b_answer_false' => $row['b_answer_false']
                ));
            }
        }
        
        return $slides;
    }

    /**
     * Vrati statisticka data jedne Question. Pole obsahuje souhrn vsech odpovedi na otazku.
     * 
     * @param int   $slideNum       Cislo slide z tabulky slides (nejedna se o ID)
     * @param int   $questionNum    Cislo otazky z tabulky questions (nejedna se o ID)
     */
    public function getQuestionStats($slideNum, $questionNum)
    {
        if(!$questionNum || !$slideNum){
            throw new Questions_Exception("slideNum: $slideNum and questionNum: $questionNum must be positive numbers.", 2);
        }
        
        // Data ziskame z metody slouzici pro vypis prehledu odpovedi v monitoringu a v adminu questions
        $data = Statistics::getAnswersOverviewTable($this->content_id, null, null, null, (int)$slideNum, (int)$questionNum);

        if(empty($data)){
            return;
        }
        
        // Dame data answers do zvlastniho pole
        $answers = array();
        foreach($data as $row){
           
            //v pripade ze se nejedna o otazku typu boolean, odebereme pocty odpovedi
            if ($row['answer_type'] != 'b') {
                unset($row['b_answer_true']);
                unset($row['b_answer_false']);
            }
            
            $answers[] = array(
                'answer_id'=>$row['answer_id'], 
                'answer_num'=>$row['answer_num'], 
                'answer_title'=>$row['answer_title'], 
                'count'=>$row['count'],
                'numeric_sum'=>$row['numeric_sum'],
                'numeric_avg'=>$row['numeric_avg']
            );
        }
        $structured = $row;
        unset($structured['answer_id']);
        unset($structured['answer_num']);
        unset($structured['answer_title']);
        unset($structured['count']);
        $structured['answers'] = $answers;
          
        return $structured;
    }


    /**
    * Vrati posledni odpovedi daneho uzivatele v danem contentu. Pokud je zadano question_id, vrati
    * jen odpovedi pro danou question.
    *
    * Jsou pouzity jen parametry, ktere byly zadany, nemelo by se uzivat zaroven ID i num
    *
    * @param int $slide_num Cislo slidu otazky z tabulky slides pro dany content
    * @param int $question_num Cislo otazky z tabulky questions pro dany content a slide
    * @param int $slide_id ID slidu z tabulky slides
    * @param int $question_id ID otazky z tabulky questions
    * @return array   Pole stdClass objektu s daty odpovedi, checkbox odpovedi jsou v poli odpovedi
    */
    public function getUsersAnswers($slide_num = null, $question_num = null, $slide_id = null, $question_id = null )
    {
        $db = Zend_Registry::get('db');

        $sel = new Zend_Db_Select($db);
        $sel->from(array('q' => 'questions'), array('question_num', 'slide_id'))
            ->join(array('ua' => 'users_answers'), 'ua.question_id = q.id', array('*'))
            ->join(array('s' => 'slides'), 's.id = q.slide_id', array('slide_num'))
            ->joinLeft(array('uac' => 'users_answers_choices'), 'uac.users_answers_id = ua.id',
                        array('checkb_answer_id' => 'answer_id'))
            ->joinLeft(array('a' => 'answers'), 'uac.answer_id = a.id OR ua.answer_id = a.id', array('answer_num'))
            ->where('q.content_id = ?', $this->content_id)
            ->where('ua.user_id = ?', $this->user_id)
            ->order(array('s.slide_num', 'q.question_num', 'a.answer_num', 'ua.id'));

        if(!empty($question_id)){
            $sel->where('q.id = ?', $question_id);
        }
        if(!empty($question_num)){
            $sel->where('q.question_num = ?', $question_num);
        }
        if(!empty($slide_id)){
            $sel->where('s.id = ?', $slide_id);
        }
        if(!empty($slide_num)){
            $sel->where('s.slide_num = ?', $slide_num);
        }

        $data = $db->fetchAll($sel, array(), Zend_Db::FETCH_OBJ);

        // Pridame jeste jeden prazdny radek kvuli zapisu dat v poslednim radku
        $newRow = new stdClass();
        $newRow->id = null;
        $data[] = $newRow;

        // Zagregujeme odpovedi checkboxu
        $dataAggregated = array();
        $lastUAId = null;
        $lastRow = null;
        $checkbAnswers = array();
        foreach($data as $row){
            if($lastUAId != $row->id && $lastUAId !== null){
                if(!empty($checkbAnswers)){
                    $lastRow->answers = $checkbAnswers;
                    $checkbAnswers = array();
                }

                $dataAggregated[] = $lastRow;

                // Ukonceni na poslednim zaznamu
                if($row->id === null){
                    break;
                }
            }

            if(!empty($row->checkb_answer_id)){
                $checkbAnswers[] = $row->answer_num;
            }

            $lastUAId = $row->id;
            $lastRow = $row;
        }

        return $dataAggregated;
    }


    public function getNearestSlideNum() {
        return $this->getNearestNum('slides', 'slide_num','content_id = '.$this->content_id);
    }

    public function getNearestQuestionNum($slide_id) {
        return $this->getNearestNum('questions', 'question_num','slide_id = '.$slide_id);
    }

    public function getNearestAnswerNum($question_id) {
        return $this->getNearestNum('answers', 'answer_num','question_id = '.$question_id);
    }

    /**
     * Vrati nejnizsi volnou intovou hodnotu z tabule pocinaje 1
     * @param string table name
     * @param string integer column name
     * @return int    nejnizsi num
     */
    public function getNearestNum($tbl, $col, $where)
    {
        $db = Zend_Registry::get('db');
        $last = 0;
        $stmt = $db->query('SELECT '.$col.' FROM '.$tbl.' WHERE '.$where.' ORDER BY '.$col);
        while ($row = $stmt->fetch()) {
            if ($row[$col] != $last + 1) {
                break;
            } else {$last = $row[$col];}
        }
        return $last + 1;
    }


    /**
     * Zapise odpoved na otazku do DB, tabulka answers
     *
     * @param   Int  Cislo cislo otazky (poradove pro dvojici content, slide)
     * @param   Int/String/array  Cislo odpovedi, pro radio decimalni cislo,
     *          pro checkbox binarni cislo nebo string (string - 011100110) - 1 v
     *          dane pozici znamena zaskrtnuty chcekbox v dane pozici, poradi je brane
     *          z prava, tedy prvni moznost ve vyberu je jednicka uplne vpravo. Pro checkbox pole
     *          obsahuje poradova cisla vybranych/zaskrtnutych odpovedi (nemusi byt serazene).
     * @param   Enum  r, c, t, b, i, d pro radio, checkbox, jen text, bool, integer, double
     *          NEPLATÍ: textova odpoved muze byt pridana primo k checkboxu i radio
     * @param   String  Pripadna textova odpoved k otazce
     * @param   Int  ID zaznamu z indetail_stats
     * @param   Int  Cislo slidu (v ramci contentu), DEFAULT null
     * @param   Int  ID slidu, DEFAULT null
     * @param   Int  ID otazky - lze pouzit misto cisla otazky, $question_num musi byt potom null.
     * @param   Int  ID z tabulky page_views
     * @param   Bool Zaznamenavat info o nesrovnalostech do Error logu? Jedna se predevsim o
     *               nesrovnalosti v typu odpovedi dorucene a nastavene v questions. Informace
     *               o nesrovnalostech lze vzdy ziskat z $this->info. DEFAULT true
     * @param   string  Timestamp ulozeni dat, ISO 8601
     * @return  Int  ID zapsaneho zaznamu v users_answers
     *
     * @throws  Questions_Exception Code:
     *                                  1 - pokud nelze identifikovat typ odpovedi
     */
    public function writeAnswer($question_num, $answer, $type = null, $text = null,
        $indetail_stats_id = null, $slide_num = null, $slide_id = null, $question_id = null, $page_views_id = null,
        $dontLogInfo = false, $timestamp = null)
    {
        $db = Zend_Registry::get('db');
        $this->info = array();

        //Phc_ErrorLog::warning('xxx', 'q_num:'.$question_num.' | answer:'.$answer.' | type:'.$type.' | text:'.$text.' | indetail_stats_id'.
        //$indetail_stats_id.' | slide_num:'.$slide_num.' | slide_id:'.$slide_id.' | '.$question_id);

        // Slide num
        if(empty($slide_num) && empty($slide_id)){
            $slide_num = 1;
        }

        // page_views_id
        if(empty($page_views_id)){
            $page_views_id = Ibulletin_Stats::getInstance()->page_view_id;
        }

        if(empty($page_views_id)){
            Phc_ErrorLog::error('Questions::writeAnswer()', 'Nepodarilo se ziskat page_views_id. ');
        }


       # Zpracovani odpovedi
        // Prevedeme vstup typu checkbox na pole cisel odpovedi
        if($type == 'c'){
            if(!is_array($answer)){
                if(is_int($answer)){
                    $answer = decbin($answer);
                }

                // retezec nul a jednicek prevedeme na pole
                $answersA = preg_split('//', $answer, -1, PREG_SPLIT_NO_EMPTY);
                $answer = array();
                $answersA = array_reverse($answersA);
                foreach($answersA as $key => $val){
                    if($val != 0){
                        $answer[] = $key+1;
                    }
                }
            }

            //Phc_ErrorLog::debug('ANSWERS', print_r($answer, 1));

            $answerCheckbox = $answer;
            $answer = null;
        }
        // Pokud se jedna o radio, jen upravime na skutecne cislo
        elseif($type == 'r'){
            $answer = (int)$answer;
        }
        if($text === ''){
            $text = null;
        }



        // Zkusime najit id odpovidajiciho slidu pokud je potreba
        if(empty($slide_id)){
            $q = new Zend_Db_Select($db);
            $q->from('slides', 'id')
                ->where('content_id = ?', $this->content_id)
                ->where('slide_num = ?', $slide_num);
            $slide_id = $db->fetchOne($q);

            if(empty($slide_id)){
                // Pridame zaznam do slides
                $dataSlides = array(
                    'content_id' => $this->content_id,
                    'slide_num' => $slide_num
                    );
                $db->insert('slides', $dataSlides);
                $slide_id = $db->lastInsertId('slides', 'id');
            }
        }

        //zjistime, jestli uz neni dana otazka vyplnena, podle toho provedeme doplneni tabulek
        // a rozhodneme, jestli na users_answrs pouzijeme update nebo insert
        $q = new Zend_Db_Select($db);
        $q->from(array('q' => 'questions'), array('question_type' => 'type'))
            ->join(array('ua' => 'users_answers'), 'q.id = ua.question_id', '*')
            ->where('q.content_id = ?', $this->content_id)
            ->where('ua.user_id = ?', $this->user_id)
             ->where('q.slide_id = ?', $slide_id);
        if(!empty($question_num)){
            $q->where('q.question_num = ?', $question_num);
        }
        else{
            $q->where('q.id = ?', $question_id);
        }
        /*
        $q = sprintf("SELECT ua.* FROM users_answers ua JOIN questions q ON q.id = ua.question_id
                      WHERE q.content_id=%d AND question_id=%d
                          AND user_id=%d",
                      $this->content_id, $question_id, $this->user_id);
        */
        $row = $db->fetchRow($q);

        //Phc_ErrorLog::error('TEST', print_r($row, true));

        // Overime existenci zaznamu ve vsech potrebnych tabulkach a pripadne doplnime
        if(empty($row)){
            // Zkusime najit zaznam pro danou question v questions
            $q = new Zend_Db_Select($db);
            $q->from('questions', array('id', 'type'))
                ->where('content_id = ?', $this->content_id)
                ->where('slide_id = ?', $slide_id)
                ->where('question_num = ?', $question_num);
            $questionRow = $db->fetchRow($q);

            if(!empty($questionRow)){
                $question_id = $questionRow['id'];
                $questionType = $questionRow['type'];
            }
            else{
                // Novy zaznam do questions
                $dataQuestions = array(
                    'content_id' => $this->content_id,
                    'slide_id' => $slide_id,
                    'question_num' => $question_num,
                    'type' => $type,
                    );
                $db->insert('questions', $dataQuestions);
                $question_id = $db->lastInsertId('questions', 'id');
                $questionType = $type;
            }

        }
        else{
            $question_id = $row['question_id'];
            $questionType = $row['question_type'];
        }

        // Doresime typ odpovedi - pokud je nezadan, bereme z questions, pokud je zadan a je
        // i v questions overujeme shodu a pokud je jen zadan pouzijeme zadany
        if(!empty($type) && !empty($questionType) && $type != $questionType){
            $this->info[] = 'Otázka má uvedený jiný typ, než byl doručen v odpovědi na ni. '.
                "Typ otázky: $questionType, typ odpovědi: $type, číslo otázky: $question_num, ID otázky: $question_id.";
            if(!$dontLogInfo){
                Phc_ErrorLog::warning('Questions::writeAnswer()',
                    'Otazka ma uvedeny jiny typ, nez byl dorucen v odpovedi na ni. '.
                    "Typ otazky: $questionType, typ odpovedi: $type, cislo otazky: ".
                    "$question_num, ID otazky: $question_id.");
            }
        }
        elseif(empty($type) && !empty($questionType)){
            $type = $questionType;
        }
        elseif(empty($type) && empty($questionType)){
            // Nejde nic delat, vyhodime vyjimku
            throw new Questions_Exception('Nebylo mozne identifikovat typ odpovedi. Typ je '.
                'u odpovedi i u otazky prazdny. '."Cislo otazky: ".
                    "$question_num, ID otazky: $question_id.", 1);
        }


        // Najdeme id jednotlivych odpovedi nebo jedine odpovedi
        $answer_ids = array();
        $inA = !empty($answerCheckbox) ? $answerCheckbox : (!empty($answer) ? array($answer) : null);
        if(!empty($inA) && trim(join(',', $inA)) != '' && ($type == 'r' || $type == 'c')){
            $in = join(',', $inA); // Musime kontrolovat neprazdnost po join, protoze v IN clausuli nesmi byt prazdno
            $sel = new Zend_Db_Select($db);
            $sel->from('answers', array('answer_num', 'id'))
                ->where("answer_num IN ($in)")
                ->where('question_id = ?', $question_id);
            $answer_ids = $db->fetchAssoc($sel);

            // Zkontrolujeme uspech a doplnime neexistujici zaznamy do tabulky answers
            foreach($inA as $answer_num){
                if(!isset($answer_ids[$answer_num])){
                    try{
                        $db->insert('answers', array('question_id' => $question_id,
                                                      'answer_num' => $answer_num));
                        $answer_ids[$answer_num] = array('id' => $db->lastInsertId('answers', 'id'));
                    }
                    catch(Exception $e){
                        Phc_ErrorLog::error('Questions::writeAnswer()', "Nepodarilo se vytvorit novou ".
                            "odpoved v tabulce answers. answer_num = '$answer_num', question_id = '$question_id'."
                            ." Puvodni vyjimka:\n".$e);
                    }
                }
            }
        }

        // Timestamp
        $timestValidator = new Ibulletin_Validators_TimestampIsoFilter();
        if(!$timestValidator->isValid($timestamp)){
            $timestamp = new Zend_Db_Expr('current_timestamp');
        }


       # Data pro ulozeni do DB
        //Data spolecna pro insert i update
        $data = array('page_views_id' => $page_views_id,
                      'answer_id' => $answer && !empty($answer_ids) ? $answer_ids[$answer]['id'] : null,
                      'text' => ($text !== null ? (string)$text : null),
                      'type' => $type,
                      'timestamp' => $timestamp,
                      );
        !empty($indetail_stats_id) ? $data['indetail_stats_id'] = $indetail_stats_id : null;
        $data['answer_bool'] = ($type == 'b' ? (bool)$answer : null);
        $data['answer_double'] = ($type == 'd' ? (double)$answer : null);
        $data['answer_int'] = ($type == 'i' ? (int)$answer : null);

        // Data jen pro insert
        $data1 = array('user_id' => (int)$this->user_id,
                      'question_id' => (int)$question_id,
                      );

        // Zaznam jiz existuje - UPDATE
        // Ulozime z puvodni data do answers_log
        if(!empty($row)){
            // Ulozime stara data
            $row['users_answers_id'] = $row['id'];
            $users_answers_id = $row['id'];
            unset($row['id']);
            unset($row['question_type']);
            $where = array('id = '.(int)$users_answers_id);
            $db->insert('users_answers_log', $row);
            $users_answers_log_id = $db->lastInsertId('users_answers_log', 'id');
            // Tabulka users_answers_choices
            $sel = new Zend_Db_Select($db);
            $sel->from('users_answers_choices', '*')
                ->where('users_answers_id = ?', $users_answers_id);
            $users_answers_choices = $db->fetchAll($sel);
            // Zapiseme do users_answers_choices_log
            foreach($users_answers_choices as $choice){
                $db->insert('users_answers_choices_log', array('users_answers_log_id' => $users_answers_log_id,
                    'answer_id' => $choice['answer_id']));
            }

            // Uklidime users_answers_choices
            $db->delete('users_answers_choices', 'users_answers_id = '.(int)$users_answers_id);

            //$q = sprintf("UPDATE answers SET answer=%d, text=%s, stat_id=%d WHERE content_id=%d AND question_id=%d AND type='%s' AND stat_id=%d", $answer, $text, $stat_id, $this->content_id, $question_id, $type, $old_stat_id);
            // Upravime existujici zaznam novymi daty
            $affected = $db->update('users_answers', $data, $where);
        }
        else{ //zaznam neexistuje - INSERT
            //$q = sprintf("INSERT INTO answers(content_id, question_id, type, answer, text, stat_id) VALUES(%d, %d, '%s', %d, %s, %d)", $this->content_id, $question_id, $type, $answer, $text, $stat_id);
            $affected = $db->insert('users_answers', array_merge($data, $data1));
            $users_answers_id = $db->lastInsertId('users_answers', 'id');
        }

        // Zapiseme pripadne odpovedi do users_answers_choices
        if(empty($answer)){
            foreach($answer_ids as $ua_id){
                $db->insert('users_answers_choices', array('users_answers_id' => $users_answers_id,
                                                           'answer_id' => $ua_id['id']));
            }
        }

        if($affected < 1){
            Phc_ErrorLog::warning('Questions', new Exception(
                "Nepodarilo se zapsat odpoved - answer=\"$answer\", text=\"$text\", ".
                "ID odpovedi=\"$question_id\", content_id=\"$this->content_id\", user_id='$this->user_id'."));
            return null;
        }

        return $users_answers_id;
    }

    /**
     * Vrati zaznam otazky z questions podle ID, nebo question_num, content_id a slide_id/slide_num
     *
     * @return  Array   Radek z tabulky questions.
     */
    public function getQuestion($id = null, $num = null, $slide_id = null, $slide_num = null){
        $db = Zend_Registry::get('db');

        $q = new Zend_Db_Select($db);
        $q->from(array('q' => 'questions'), '*')
            ->join(array('s' => 'slides'), 'q.slide_id = s.id', array());

        if(!empty($id)){
            $q->where('q.id = ?', $id);
        }
        else{
            $q->where('q.content_id = ?', $this->content_id)
                ->where('q.question_num = ?', $num);

            if($slide_num !== null){
                $q->where('s.slide_num = ?', $slide_num);
            }
            else{
                $q->where('q.slide_id = ?', $slide_id);
            }
        }

        $questionRow = $db->fetchRow($q);

        return  $questionRow;
    }

    /**
     * Zapise data k odpovedi.
     *
     * @param id int id otazky
     * @param $data   array key:value, klice sou sloupce tabulky answers
     * @return boolean vraci TRUE pokud se ulozeni povedlo
     */
    public function editAnswer($id, $data) {

        $db = Zend_Registry::get('db');

        try {
                $db->update('answers', $data, 'id = '.(int)$id);
                return true;
            } catch (Exception $e) {
                // zalogovani chyby
                Phc_ErrorLog::error('Questions::editAnswer',
                "nezdaril se update answer_id: $id, data : \n".print_r($data, true).
                "\n Puvodni vyjimka: ".$e);
                return false;
            }
    }

    // TODO: logovani chyb, vyhozeni vyjimky
    /**
     * Zapise text k odpovedi, nebo zalozi odpoved bez textu. Pokud neni zadano $answerNum,
     * je zalozena nova odpoved v answers pro danou question.
     *
     * @param $questionId   Int     ID otazky z questions.
     * @param $answerNum    Int     Cislo odpovedi v otazce, pokud je null, prida se jako
     *                              dalsi odpoved v poradi
     * @param $text         String  Text odpovedi, pokud je null, bude existujici text odstranen.
     * @return Int  vraci id_odpovedi nebo FALSE
     */
    public function editAnswerTitle($questionId, $answerNum, $text = null)
    {
        $db = Zend_Registry::get('db');

        $answer_id = null;
        if($answerNum !== null){
            // Zjistime, jestli editujeme nebo vytvarime
            $sel = new Zend_Db_Select($db);
            $sel->from('answers', 'id')
                ->where('question_id = ?', $questionId)
                ->where('answer_num = ?', $answerNum );
            $answer_id = $db->fetchOne($sel);
        }


        $data = array('text' => $text);

        if($answer_id){
            try {
                $db->update('answers', $data, 'id = '.(int)$answer_id);
            } catch (Exception $e) {
                // zalogovani chyby
                Phc_ErrorLog::error('Questions::editAnswerTitle',
                "nezdaril se update answer_id: $answer_id question_id: $questionId answer_num: $answerNum text: $text
                \n Puvodni vyjimka: ".$e);
                return false;
            }
        }
        else{
            // Pokud neni zadano cislo otazky, najdeme nejvyssi answer_num a pridame dalsi
            if($answerNum === null){
                $answerNum = $this->getNearestAnswerNum($questionId);
            }

            $data['question_id'] = $questionId;
            $data['answer_num'] = $answerNum;
            try {
                $db->insert('answers', $data);
                $answer_id = $db->lastInsertId('answers','id');
            } catch (Exception $e) {
                // zalogovani chyby
                Phc_ErrorLog::error('Questions::editAnswerTitle',
                "nezdaril se insert question_id: $questionId answer_num: $answerNum text: $text
                \n Puvodni vyjimka: ".$e);
                return false;
            }
        }

        return $answer_id;
    }

    /**
     * Zapise data k otazce.
     *
     * @param id int id odpovedi
     * @param $data   array key:value, klice sou sloupce tabulky questions
     * @return boolean vraci TRUE pokud se ulozeni povedlo
     */
    public function editQuestion($id, $data) {

        $db = Zend_Registry::get('db');

        try {
                $db->update('questions', $data, 'id = '.(int)$id);
                return true;
            } catch (Exception $e) {
                // zalogovani chyby
                Phc_ErrorLog::error('Questions::editQuestion',
                "nezdaril se update question_id: $id, data : \n".print_r($data, true).
                "\n Puvodni vyjimka: ".$e);
                return false;
            }
    }

    /**
     * Zapise text k otazce, nebo zalozi otazku bez textu. Pokud neni zadano $questionNum,
     * je zalozena nova otazka v questions.
     *
     * @param $slideId   Int     ID otazky z slides.
     * @param $questionNum    Int     Cislo otazky, pokud je null, prida se jako
     *                              dalsi otazka v poradi
     * @param $questionType String  typ otazky ( r c t b i d )
     * @param $text         String  Text otazky, pokud je null, bude existujici text odstranen.
     */
    public function editQuestionTitle($contentId, $slideId, $questionNum,$questionType, $text = null)
    {
        $db = Zend_Registry::get('db');

        $question_id = null;
        if($questionNum !== null){
            // Zjistime, jestli editujeme nebo vytvarime
            $sel = new Zend_Db_Select($db);
            $sel->from('questions', 'id')
                ->where('slide_id = ?', $slideId)
                ->where('question_num = ?', $questionNum );
            $question_id = $db->fetchOne($sel);
        }

        $data = array('content_id' => $contentId, 'text' => $text, 'type' => $questionType);


        if($question_id){


            try {
                $db->update('questions', $data, 'id = '.(int)$question_id);
            } catch (Exception $e) {
                // zalogovani chyby
                Phc_ErrorLog::error('Questions::editQuestionTitle',
                "nezdaril se update question_id: $question_id slide_id: $slideId question_num: $questionNum question_type: $questionType text: $text
                \n Puvodni vyjimka: ".$e);
                return false;
            }
        }
        else{

            // Pokud neni zadano cislo otazky, najdeme nejvyssi question_num a pridame dalsi
            if($questionNum === null){
                $questionNum = $this->getNearestQuestionNum($slideId);
            }

            $data['slide_id'] = $slideId;
            $data['question_num'] = $questionNum;

            try {
                $db->insert('questions', $data);
                $question_id = $db->lastInsertId('questions','id');
            } catch (Exception $e) {
                // zalogovani chyby
                Phc_ErrorLog::error('Questions::editQuestionTitle',
                "nezdaril se insert content_id: $contentId slide_id: $slideId question_num: $questionNum question_type: $questionType text: $text
                \n Puvodni vyjimka: ".$e);
                return false;
            }
        }

        return $question_id;
    }

    /**
     * Nastavi parametry slidu, nebo zalozi slide. Pokud neni zadano $slideNum,
     * je zalozena nova otazka v questions.
     *
     * @param $slideNum    Int     Cislo slidu, pokud je null, prida se jako
     *                              dalsi slide v poradi
     * @param $slideMandatory Bool  nastavi mandatornost slidu
     */
    public function editSlide($slideNum, $slideName, $slideMandatory)
    {
        $db = Zend_Registry::get('db');

        $slide_id = null;
        if($slideNum !== null){
            // Zjistime, jestli editujeme nebo vytvarime
            $sel = new Zend_Db_Select($db);
            $sel->from('slides', 'id')
                ->where('content_id = ?', $this->content_id)
                ->where('slide_num = ?', $slideNum );
            $slide_id = $db->fetchOne($sel);
        }

        $data = array('mandatory' => $slideMandatory, 'name' => $slideName);


        if($slide_id){
            try {
                $db->update('slides', $data, 'id = '.(int)$slide_id);
            } catch (Exception $e) {
                // zalogovani chyby
                Phc_ErrorLog::error('Questions::editSlide',
                "nezdaril se update slide_id: $slide_id slide_num: $slideNum slide_mandatory: $slideMandatory
                \n Puvodni vyjimka: ".$e);
                return false;
            }
        }
        else{
            // Pokud neni zadano cislo slidu, najdeme nejvyssi slide_num a pridame dalsi
            if($slideNum === null){
                $slideNum = $this->getNearestSlideNum();
            }

            $data['content_id'] = $this->content_id;
            $data['slide_num'] = $slideNum;
            $data['name'] = $slideName;
            try {
                $db->insert('slides', $data);
                $slide_id = $db->lastInsertId('slides','id');

            } catch (Exception $e) {
                // zalogovani chyby
                Phc_ErrorLog::error('Questions::editSlide',
                "nezdaril se insert slide_num: $slideNum slide_mandatory: $slideMandatory
                \n Puvodni vyjimka: ".$e);
                return false;
            }
        }

        return $slide_id;
    }

    /**
     * Vraci dotaz pro seznam uzivatelskych odpovedi daneho slidu z tabulek users_answers(_log) a indetail_stats
     * pouzivame pro zjisteni, zda je mozne dany slide smazat (zabranuje smazani uzivatelskych dat se slide).
     *
     * @return Zend_Db_Select dotaz vraci seznam id z users_answers(_log) a indetail_stats
     */
    private function getSlideHasUserDataQuery($slide_id) {

        $db = Zend_Registry::get('db');

        // orezani pres users_vf
        // omezeni na question_id v tabulkach users_answers a users_answers_log
        $s1 = $db->select()
            ->from(array('q' => 'questions'),array('ua.id'))
            ->join(array('ua' => 'users_answers'),'ua.question_id = q.id',array())
            ->join(array('u' => 'users_vf'),'u.id = ua.user_id',array())
            ->where('q.slide_id= ?', (int)$slide_id);

        $s2 = $db->select()
            ->from(array('q' => 'questions'),array('ual.id'))
            ->join(array('ual' => 'users_answers_log'),'ual.question_id = q.id',array())
            ->join(array('u' => 'users_vf'),'u.id = ual.user_id',array())
            ->where('q.slide_id= ?', (int)$slide_id);

        // orezani pres users_vf
        // omezeni na slideon_id alebo slide_id v indetail_stats
        $s3 = $db->select()
            ->from(array('u' => 'users_vf'),array('ids.id'))
            ->join(array('ids' => 'indetail_stats'),'ids.user_id = u.id',array())
            ->where('ids.slideon_id = ? OR ids.slide_id = ?', (int)$slide_id);

        // union pres vsechny tabulky
        return $db->select()->union(array($s1, $s2, $s3),Zend_Db_Select::SQL_UNION_ALL);

    }

    /**
     * Vraci dotaz pro seznam uzivatelskych odpovedi dane otazky z tabulek users_answers(_log)
     * pouzivame pro zjisteni, zda je mozne danou otazku smazat (zabranuje smazani uzivatelskych dat dane otazky).
     *
     * @return Zend_Db_Select dotaz vraci seznam id z users_answers a users_answers_log
     */
    private function getQuestionHasUserDataQuery($question_id) {

        $db = Zend_Registry::get('db');

        // orezani pres users_vf
        // omezeni na question_id v tabulkach users_answers a users_answers_log
        // union pres log tabulky
        $s1 = $db->select()
            ->from(array('u' => 'users_vf'),array('ua.id'))
            ->join(array('ua' => 'users_answers'),'ua.user_id = u.id',array())
            ->where('ua.question_id = ?', (int)$question_id);
        $s2 = $db->select()
            ->from(array('u' => 'users_vf'),array('ual.id'))
            ->join(array('ual' => 'users_answers_log'),'ual.user_id = u.id',array())
            ->where('ual.question_id = ?', (int)$question_id);;

        return $db->select()->union(array($s1, $s2),Zend_Db_Select::SQL_UNION_ALL);

}
    /**
     * Vraci dotaz pro seznam uzivatelskych odpovedi pro danou odpoved z tabulek users_answers(_log) a users_answers_choices(_log)
     * pouzivame pro zjisteni, zda je mozne danou odpoved smazat (zabranuje smazani uzivatelskych dat dane odpovedi).
     *
     * @return Zend_Db_Select dotaz vraci seznam id z users_answers a users_answers_log a answer_id z users_answers(_choices)
     */
    private function getAnswerHasUserDataQuery($answer_id) {

        $db = Zend_Registry::get('db');

        // orezani pres users_vf s left joinem user_answers_choices a union all pres souvisejici log tabulky
        // omezeni na answer_id v tabulkach users_answers,users_answers_choices
        // union pres log tabulky
        $s1 = $db->select()
            ->from(array('u' => 'users_vf'),array('ua.id'))
            ->join(array('ua' => 'users_answers'),'ua.user_id = u.id',array())
            ->joinLeft(array('uac' => 'users_answers_choices'),'uac.users_answers_id = ua.id',array('uac.answer_id'))
            ->where('ua.answer_id = ? OR uac.answer_id = ?', (int)$answer_id);
        $s2 = $db->select()
            ->from(array('u' => 'users_vf'),array('ual.id'))
            ->join(array('ual' => 'users_answers_log'),'ual.user_id = u.id',array())
            ->joinLeft(array('uacl' => 'users_answers_choices_log'),'uacl.users_answers_log_id = ual.id',array('uacl.answer_id'))
            ->where('ual.answer_id = ? OR uacl.answer_id = ?', (int)$answer_id);;

       return $db->select()->union(array($s1, $s2),Zend_Db_Select::SQL_UNION_ALL);
    }


    /**
     * is slide deletable?
     * ??? if there is an undeletable question/answer or slide exists in indetail_stats function returns FALSE, TRUE otherwise
     *
     * @return boolean
     */
    public function isDeletableSlide($slide_id) {
        return (boolean)$this->getSlideHasUserDataQuery($slide_id)->query()->fetch() === false ? true : false;
    }
    /**
     * is question deletable?
     * ??? if there is an undeletable answer function returns FALSE, TRUE otherwise
     *
     * @return boolean
     */
    public function isDeletableQuestion($question_id) {
        return (boolean)$this->getQuestionHasUserDataQuery($question_id)->query()->fetch() === false ? true : false;
    }

    /**
     * is answer deletable?
     *
     * @return boolean
     */
    public function isDeletableAnswer($answer_id) {
        return (boolean) $this->getAnswerHasUserDataQuery($answer_id)->query()->fetch() === false ? true : false;
    }

    /**
     * Zmaze slide.
     *
     * @param $slide_id    Int     ID slidu
    * @return  boolean
     */
    public function deleteSlide($slide_id) {

        if (!$this->isDeletableSlide($slide_id)) return false;

        $db = Zend_Registry::get('db');

        // begin
        $db->beginTransaction();
        try {
            $affected = $db->delete('slides', 'id = '.(int)$slide_id);
            if($affected != 1)  throw new Exception('affected = '.$affected);
            // commit
            $db->commit();
            return $affected;

        } catch (Exception $e) {
            // rollback
            $db->rollback();
            Phc_ErrorLog::error('Questions::deleteSlide',
            "nezdaril se delete slidu ze slide_id: $slide_id
            \n Puvodni vyjimka: ".$e);
            return false;
        }

    }

    /**
     * Zmaze otazku.
     *
     * @param $question_id    Int     ID otazky
    * @return  boolean
     */
    public function deleteQuestion($question_id) {

        if (!$this->isDeletableQuestion($question_id)) return false;

        $db = Zend_Registry::get('db');

        // begin
        $db->beginTransaction();
        try {
            $affected = $db->delete('questions', 'id = '.(int)$question_id);
            if($affected != 1)  throw new Exception('affected = '.$affected);
            // commit
            $db->commit();
            return $affected;

        } catch (Exception $e) {
            // rollback
            $db->rollback();
            Phc_ErrorLog::error('Questions::deleteQuestion',
            "nezdaril se delete otazky s question_id: $question_id
            \n Puvodni vyjimka: ".$e);
            return false;
        }

    }

    /**
     * Zmaze odpoved.
     *
     * @param $answer_id    Int     ID odpovede
    * @return boolean
     */
    public function deleteAnswer($answer_id) {

        if (!$this->isDeletableAnswer($answer_id)) return false;

        $db = Zend_Registry::get('db');

        // begin
        $db->beginTransaction();
        try {
            $affected = $db->delete('answers', 'id = '.(int)$answer_id);
            if($affected != 1)  throw new Exception('affected = '.$affected);
            // commit
            $db->commit();
            return $affected;

        } catch (Exception $e) {
            // rollback
            $db->rollback();
            Phc_ErrorLog::error('Questions::deleteAnswer',
            "nezdaril se delete odpovedi s answer_id: $answer_id
            \n Puvodni vyjimka: ".$e);
            return false;
        }

    }
    
    
    public static function getSlidesName($content_id) {
        $db = Zend_Registry::get('db');
        $sel = $db->select()->from('slides',array('slide_num','name'))->where('content_id = ?',$content_id);
        
        $rows = $db->fetchPairs($sel);
        return $rows;
    }
    
    /**
     * Vrati typy otazek
     * @return array pole s prelozenymi typy 
     */
    public static function getTypes() {
        
        $texts = Ibulletin_Texts::getSet('admin.slides');
        
        return array(
			'r' => $texts->type_r,
			'c' => $texts->type_c,
			't' => $texts->type_t, 
			'b' => $texts->type_b, 
			'i' => $texts->type_i, 
			'd' => $texts->type_d
		);
        
    }
    
    

}
