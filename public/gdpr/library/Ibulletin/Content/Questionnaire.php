<?php

/**
 * iBulletin - Questionnaire.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

Zend_Loader::loadClass('Ibulletin_Content_Abstract');

/**
 * Trida zprostredkovavajici zobrazeni hlavniho obsahu na strankach iBulletinu -
 * Dotaznik
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Content_Questionnaire extends Ibulletin_Content_Abstract
{
    /**
     *  @var bool   Je zakazano na HTML tohoto contentu provadet TIDY? (true = TIDY zakazano)
     *              Pro Questionnaire je tidy zakazane kvuli specialnimu markupu pro tvorbu formu.
     */
    var $tidyOff = true;

    /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "questionnaire_%d.phtml";

    /**
     * @var array Sem odkladame predvyplneni dotazniku
     */
    var $prefill_answr = array();

    /**
     * @var bool Info, zda-li byl dotaznik ulozen
     */
    var $saved = false;

    /**
     * @var bool Prepinac, jestli zobrazit dotaznik po ulozeni, nebo jen hlasku
     */
    var $show_form_after_save = true;

    /**
     * Ukladame cisla otazek, ktere jsme jiz zapisovali, abychom v dusledku vadneho
     * formulare neztraceli data.
     * POZOR - nesmi byt nikdy serializovan s neprazdnym $savedQuestions
     */
    var $savedQuestions = array();
    
     
    /**
     * @var bool Je povoleno renderovani do PDF u tohoto contentu? Pokud neni, obvykle vracime
     *           URL na PDF null
     */
    public $allowPdf = false;

    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     *
     *
     *
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepare($req)
    {
        $config = Zend_Registry::get('config');
       
        // Ziskame z URL cislo aktualniho listu na strance
        $sheet_number_get = $req->getParam($config->general->url_name->sheet_number);
        if(is_numeric($sheet_number_get)){
            $this->sheet_number = $sheet_number_get;
        }

        Zend_Loader::loadClass('Zend_Validate_EmailAddress');
        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet('questionnaire');
        $renderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');

        //HACK kontroluje typ requestu, z duvodu generovani PDF, kde se pri renderovani pouziva Zend_Controller_Request_Simple,
        //ktera nema potrebne metody (pouzito na nekolika mistech teto metody)
        //TODO - vymyslet lepsi reseni
         if ($req instanceof Zend_Controller_Request_Http) {
            if ($req->getMethod() == 'POST' && $req->getParam('submit_', null)) {
                $renderer->view->formInfoMessage = $texts->formThanksForFillup;
                // Navesti o ulozeni dat
                $this->saved = true;
            }
        }
        // Pripravime data o aktualnim uzivateli
        $this->user_data = Ibulletin_Auth::getActualUserData();
        $this->user_id = $this->user_data['id'];

        // Zpracovani zadaneho e-mailu
        $validator = new Zend_Validate_EmailAddress();
        
        if ($req instanceof  Zend_Controller_Request_Http) {
        $email = $req->getPost('email');
        }
        
        // Otestujeme spravnost mailove adresy, pokud neni spravna, neukladame
        // a informujeme uzivatele
        if($validator->isValid($email)){
            $email = strtolower($email);

            // Provedeme akce spojene s nastavenim emailu uzivatelem
            //Ibulletin_Auth::changeUsersData(array('email' => $email), null, true);
            try{
                Users::emailSet($email);
            }
            catch(Users_Exception $e){
                // Pokud se nepodarilo, neresime to, jen zalogujeme
                Phc_ErrorLog::warning('Ibulletin_Content_Questionaire', $e);
            }
        }
        elseif(!empty($email) && $email != '@'){
            // Info zprava o neulozeni mailu
            $renderer->view->formInfoMessage = $texts->formWrongEmailAddr;
            $this->bad_email = true;
        }

        // Pripravime data o aktualnim uzivateli jeste jednou, kdyby se odehrala zmena mailu
        $this->user_data = Ibulletin_Auth::getActualUserData();
        $this->user_id = $this->user_data['id'];

        // Zaridime nacteni dotazniku z DB a ulozeni odeslanych dat do DB
        if ($req instanceof  Zend_Controller_Request_Http) {
        $this->resolveFormData($req, $renderer);
        }
        
        // Pokud je ve formulari vice listu, po ulozeni dat presmerujeme na dalsi list,
        // nebo na URL poskytnutou v post - redirect_to_url
        $url = $req->getParam('redirect_to_url');
        //$data_sent = $req->getParam('submit_');
        
        if ($req instanceof  Zend_Controller_Request_Http) {
            $data_sent = ($req->getMethod() == 'POST' && $req->getParam('submit_', null));
        }
        if(!empty($url) || !empty($data_sent)){
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            if(!empty($url)){
                $redirector->gotoUrlAndExit($url);
            }
            else{
                // najdeme cislo dalsiho listu a presmerujeme na nej
                $next_sheet = $this->sheet_number+1;
                if(isset($this->html[$this->sheet_number])){
                    $redirector->gotoRouteAndExit(array($config->general->url_name->sheet_number => $next_sheet));
                }
            }
        }

        Ibulletin_Js::addJsFile('jquery.js');

        // Predvyplneni e-mailu
        /*
        if(!empty($this->user_data['email'])){
            // HACK? Podivne slozity zpusob doplneni pole ulozeneho ve view
            $this->prefill_answr = $renderer->view->prefill_answr;
            $this->prefill_answr['email'] = $this->user_data['email'];
            $renderer->view->prefill_answr = $this->prefill_answr;
        }
        */

    }

    /**
     * Vrati renderovaci data contentu s ohledem na nastavenou pozici v hlavnim contentu
     */
    public function getContent()
    {
        // Prelozime znacky
        $config = Zend_Registry::get('config');
        
		$urlHlpr = new Zend_View_Helper_Url();
		$baseUrl = $urlHlpr->url(array(),'default',true);				
		$path = $baseUrl . $config->content_article->basepath . $this->id.'/';
        // Pouzivame preklad znacek z teto tridy i z Marks
        $html_content = isset($this->html[$this->sheet_number-1]) ? $this->html[$this->sheet_number-1] : $this->html[0];

        $html = Ibulletin_Marks::translate($html_content, $path, $this,
                                           $this->prefill_answr, $this->user_data);

        // ziskame view pres viewrenderer
        $viewRenderer = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');
        $view = $viewRenderer->view;

        // Ulozime do view informaci o tom, jestli byl dotaznik ulozen a jestli
        // zobrazit i dotaznik po ulozeni, nebo jen hlasku
        $view->form_saved = $this->saved;
        $view->show_form_after_save = $this->show_form_after_save;

        // Pridame JS pro odeslani formulare html odkazem pokud je vice listuu
        // Pridame JS pro odeslani formulare html odkazem pokud je vice listuu
        if(count($this->html) > 1 && preg_match('/(\<form|\%\%form\%\%)/i', $this->html[$this->sheet_number-1])){
            $form_id = 'form_'.$this->id;
            // Pridame vickrat podle poctu listu a pridame dve pro prvni a posledni list
            Ibulletin_Js::hrefFormSubmit($form_id);
            Ibulletin_Js::hrefFormSubmit($form_id);
            foreach($this->html as $key => $x){
                Ibulletin_Js::hrefFormSubmit($form_id);
            }
            $view->questionnaire_form_id = $form_id;
        }



        // Html se bude prohanet eval(), takze na zacatek pridame koncovy tag php
        return array('form_html' => '?> '.$html);
    }




    /**
     * Naplni do view data pro predvyplneni formulare a ulozi hodnoty
     * do DB, pokud jsou data v poradku.
     *
     * Rozlisuje nazvy atributu z formulare ve tvaru ^[rctm]{1}_[0-9]+_([0-9]+|[a-z]+){0,1},
     * kde prvni pismeno znaci typ pole:
     * r - radio - ocekava jednu hodnotu na otazku
     * c - checkbox - je ve tvaru c_1_5, kde prvni cislo je cislo otazky
     *                a druhe cislo je cislo odpovedi na otazku
     * t - text - jakakoli textova hodnota
     *
     * @param Zend_Controller_Request Request objekt
     * @param Zend_Controller_Action_Helper_ViewRenderer renderer
     * @TODO  BUG - pokud je otazka z checkboxuu, nejde prepsat existujici odpoved
     *        prazdnym (nezaskrtnuto nic), protoze v cyklu formulare se vubec neobjevi zadna data ze
     *        zadavaciho pole checkboxu.
     */
    public function resolveFormData($req, $renderer)
    {
        //$saved_data = $this->loadQuestionnaireData(); // Presunuto az za ulozeni
        //$data = array_merge($saved_data, $req->getPost());
        $data = $req->getPost();
        
        //kontrola zda zpracovavame dotaznik contentu, podle hidden input content id, ktery doplnujeme pri prekladu znacky %%form%%
        //neni-li v postu content_id radeji formular zpracujemem
        if (isset($data['content_id']) && $data['content_id'] != $this->id) {
            $this->loadQuestionnaireData();
            $this->saved = false;
            return;
        }
        
        $checkbox_val = '';
        $counter = 0;
        $checkboxes = array();
        $pageViewsId = Ibulletin_Stats::getInstance()->page_view_id;

        foreach($data as $key => $val){
            $d = array();
            // Pokusime se rozparsovat jmeno promenne, pokud se nepovede, preskocime
            if(!preg_match('/^([rctm]{1})_([0-9]+)(_(([0-9]+)|([a-z]+))){0,1}/i', $key, $d)){
                continue;
            }
            $counter++;

            $type = $d[1];
            $question_id = $d[2];
            
            if(array_key_exists(5, $d)){
                $answer_num = $d[5];
            }
            else{
                $checker = $d[6];
            }


            // Umozni pozdeji rozpoznat, jestli byly vsechny otazky vyplneny
            $last_question_id = $question_id;

            // --------------------------------------------------------------
            // Provedeme akce podle typu odpovedi
            // --------------------------------------------------------------
            if($type == 'r'){
                // Ukladani radio nebo selectboxu
                $answer = (int)$val;
                $text = null;
                $this->writeAnswer($question_id, $answer, $type, $text, $pageViewsId);

                // Nastavime predvyplneni dotazniku
                $this->prefill_answr['r_'.$question_id.'_'.$answer] = 'checked="checked"';
            }
            elseif($type == 'c'){
                // Jen ukladame do pole jednotliva zaskrtnuti, ulozime pozdeji
                $curbin = 1 << (((int)$answer_num-1)<1 ? 0 : ((int)$answer_num-1));

                if(isset($checkboxes[$question_id])){
                    $checkboxes[$question_id] |= $curbin;
                }
                else{
                    $checkboxes[$question_id] = $curbin;
                }
                // Nastavime predvyplneni dotazniku
                $this->prefill_answr['c_'.$question_id.'_'.$answer_num] = 'checked="checked"';
            }
            elseif($type == 't'){
                // Ukladani text
                $text = $val;
                $this->prefill_answr['t_'.$question_id] = $text;
                $this->writeAnswer($question_id, null, $type, $text, $pageViewsId);
            }
            elseif($type == 'm'){
                foreach($val as $answer_num){
                    // Jen ukladame do pole jednotliva zaskrtnuti, ulozime pozdeji
                    $curbin = 1 << (((int)$answer_num-1)<1 ? 0 : ((int)$answer_num-1));

                    if(isset($checkboxes[$question_id])){
                        $checkboxes[$question_id] |= $curbin;
                    }
                    else{
                        $checkboxes[$question_id] = $curbin;
                    }
                    // Nastavime predvyplneni dotazniku - jedna se o multiple select
                    $this->prefill_answr['c_'.$question_id.'_'.$answer_num] = 'selected="selected"';
                }
            }

        }

        // Ulozime data z checkboxuu
        foreach($checkboxes as $question_id => $answer){
            $this->writeAnswer($question_id, (int)$answer, 'c', null, $pageViewsId);
        }

        // Nacteme data ulozena pro tohoto uzivatele v db kvuli nastaveni predvyplneni
        $this->loadQuestionnaireData();

        // Zapiseme do view
        $renderer->view->prefill_answr = $this->prefill_answr;
    }

    /**
     * Pokusi se nacist do pole data tohoto uzivatele a tohoto dotazniku.
     *
     */
     public function loadQuestionnaireData()
     {
         $db = Zend_Registry::get('db');

         /*
         $q = sprintf('SELECT * FROM answers WHERE user_id = %d AND content_id = %d',
                       $this->user_id, $this->id);
         $answers = $db->fetchAll($q);
         */

         // Objekt pro praci s odpovedmi
         $questionsObj = new Questions($this->id);

         $answers = $questionsObj->getUsersAnswers();

         // Vytvorime pole ve formatu jako, ktery dostaneme z formulare POSTem,
         // abychom data jednoduse mergnuli pred zpracovanim
         $output = array();
         foreach($answers as $answer){
             if($answer->type == 'r'){
                 $attr_name = sprintf('r_%d', $answer->question_num);
                 $output[$attr_name] = $answer->answer_num;
                 $this->prefill_answr[$attr_name.'_'.$answer->answer_num] = 'checked="checked"';
             }
             elseif($answer->type == 'c' && isset($answer->answers) && is_array($answer->answers)){
                 // Nacte a predvyplni checkboxy a multiple select
                 foreach($answer->answers as $a){
                     $attr_name_part = sprintf('_%d_%d', $answer->question_num, $a);
                     $output['c'.$attr_name_part] = $a;
                     $output['m'.$attr_name_part] = $a;
                     $this->prefill_answr['c'.$attr_name_part] = 'checked="checked"';
                     $this->prefill_answr['m'.$attr_name_part] = 'selected="selected"';
                 }
             }
             elseif($answer->type == 't'){
                 $attr_name = sprintf('t_%d', $answer->question_num);
                 $output[$attr_name] = $answer->text;
                 $this->prefill_answr[$attr_name] = $answer->text;
             }
         }

         return $output;
     }

    /**
     * Zapise odpoved na otazku do DB, tabulka answers
     *
     * @param   Int  Cislo dotazniku (content_id)
     * @param   Enum  r, c nebo t pro radio, checkbox a jen text
     *          NEPLATÍ: textova odpoved muze byt pridana primo k checkboxu i radio
     * @param   Int/String/Array  Cislo odpovedi, pro radio decimalni cislo,
     *          pro checkbox binarni cislo nebo string (string - 011100110) - 1 v
     *          dane pozici znamena zaskrtnuty chcekbox v dane pozici, poradi je brane
     *          z prava, tedy prvni moznost ve vyberu je jednicka uplne vpravo.
     *          Pole obsahujici cisla vybranych odpovedi. [4,5,8]
     * @param   String  Pripadna textova odpoved k otazce
     * @param   int     Id z tabulky page_views odpovidajici nacteni dotazniku, z ktereho jsou data ukladana
     * @return  Bool  podarilo/nepodarilo se zapsat
     */
    public function writeAnswer($question_num, $answer, $type, $text = null, $pageViewsId = null)
    {
        $db = Zend_Registry::get('db');

        // Objekt pro praci s odpovedmi
        $questionsObj = new Questions($this->id);

        /*
        // Nastavime dotaznik
        $content_id = $this->id;

        // User id
        $user_id = $this->user_id;
        */


        // Zkontrolujeme, jestli neni dana odpoved zapisovana po druhe, to naznacuje chybu ve formulari
        // pokud jiz byla ulozena, zalogujeme chybu a data druhe odpovedi
        if(in_array($question_num, $this->savedQuestions)){
            // Zapiseme data do logu a poskocime na dalsi kolo, nechceme prepsat jiz existujici data v DB
            Phc_ErrorLog::error('Ibulletin_Content_Questionnaire::resolveFormData()',
                "Pokus o znovuulozeni otazky dotazniku id: '$this->id', question_id: '$question_num', ".
                "user_id: '$this->user_id'.Nova data - type: '$type', answer: '$answer', text: '$text'.");
        }
        else{
            $this->savedQuestions[] = $question_num;
        }


        //pokud je odpoved checkbox, prevedeme pripadny string tvaru 0001010 na decimalni cislo
        if($type == 'c' && !is_int($answer) && !is_array($answer)){
            $answer = bindec("$answer");
        }
        // Pokud se jedna o radio, jen upravime na skutecne cislo
        elseif($type == 'r'){
            $answer = (int)$answer;
        }

        if(empty($text)){
            $text = null;
        }

        // Samotny zapis odpovedi.
        try{
            $questionsObj->writeAnswer($question_num, $answer, $type, $text,
                null, null, null, null, $pageViewsId);
        }
        catch(Exception $e){
            Phc_ErrorLog::error('Ibulletin_Content_Questionnaire::writeAnswer()',
                "Nepodarilo se ulozit odpovedi odeslane z dotazniku - content_id: '$this->id', ".
                "question_num: '$question_num', answer: '$answer', text: '$text', ".
                "page_views_id: '$pageViewsId'. Puvodni vyjimka: \n $e");
        }

        /*
        //zjistime, jestli uz neni dana otazka vyplnena, pokud ne pouzijem INSERT, jinak UPDATE
        $q = sprintf("SELECT * FROM answers
                      WHERE content_id=%d AND question_id=%d
                          AND user_id=%d",
                      $content_id, $question_id, $user_id);
        $row = $db->fetchRow($q);

        // Data pro ulozeni do DB
        $data = array('answer' => $answer,
                      'text' => $text,
                      'type' => $type
                      );
        $data1 = array('user_id' => (int)$user_id,
                      'content_id' => (int)$content_id,
                      'question_id' => (int)$question_id,
                      );
        $where = array('user_id = '.(int)$user_id,
                       'content_id = '.(int)$content_id,
                       'question_id = '.(int)$question_id,
                       );

        // Zaznam jiz existuje - UPDATE
        // Ulozime z puvodni data do answers_log
        if(!empty($row)){
            // Ulozime stara data
            $db->insert('answers_log', $row);

            //$q = sprintf("UPDATE answers SET answer=%d, text=%s, stat_id=%d WHERE content_id=%d AND question_id=%d AND type='%s' AND stat_id=%d", $answer, $text, $stat_id, $content_id, $question_id, $type, $old_stat_id);
            // Upravime existujici zaznam novymi daty
            $affected = $db->update('answers', $data, $where);
        }
        else{ //zaznam neexistuje - INSERT
            //$q = sprintf("INSERT INTO answers(content_id, question_id, type, answer, text, stat_id) VALUES(%d, %d, '%s', %d, %s, %d)", $content_id, $question_id, $type, $answer, $text, $stat_id);
            $affected = $db->insert('answers', array_merge($data, $data1));
        }

        if($affected < 1){
            Phc_ErrorLog::warning('Questionnaire', "Nepodarilo se zapsat odpoved - answer=\"$answer\", text=\"$text\", cislo odpovedi=\"$question_id\", content_id=\"$content_id\".");
            return false;
        }
        */

        return true;
    }


    /**
     * Varati vstupni retezec s nahrazenymi znackami za spravny kod
     * Aktualne prklada znacky:
     * %%static%% - nahradi za cestu do adresare statickeho obsahu daneho contentu
     *
     * DEPRECATED - nadale se nepouziva, pouzivame Ibulletin_Marks::translate();
     *
     * @param string vstupni retezec
     * @param array $errors  Chyby zjistene pri zpracovani formulare pro vypis v adminu
     * @return string retezec s prelozenzmi znackami
     */
    public function translateMarks($string, &$errors = array())
    {
        $config = Zend_Registry::get('config');

        // Nahradime cestu do statickych dat
        $path = $config->content_article->basepath.'/'.$this->id.'/';
        $token = '%%static%%';
        $out = str_ireplace($token, $path, $string);

        // Nahradime %%form%% retezcem form id=""
        $replacement = "form id=\"form_".$this->id."\" method=\"POST\" action=\"\" ";
        $token = '%%form%%';
        $out = str_ireplace($token, $replacement, $out);

        // Nahradime znacky pro vnitrnosti tagu vstupnich poli
        $inputs = array();      // sem odkladame informace pro sledovani zadanych poli
        $startpos = 0;
        $tag = '%%input_';
        $taglabel = '%%label';
        $tag_len = strlen($tag);
        while(1){
            if($startpos + $tag_len >= strlen($out)){
                break;
            }
            $startpos = strpos($out, $tag, $startpos + $tag_len);
            if($startpos === false){
                break;
            }

            // vysekneme si retezec, z ktereho precteme informace o danem tagu
            $substr = substr($out, $startpos + $tag_len, 30 + $tag_len);

            // rozparsujeme tag na hodnoty
            $vars = array();
            if(!preg_match('/([a-zA-Z]+)#([0-9]+)%%.*/i', $substr, $vars)){
                continue;
            }

            $type = $vars[1];
            $question_id = $vars[2];


            // Zjistime cislo moznosti v otazce
            if(isset($inputs[$question_id]['input'])){
                $answer_num = ++$inputs[$question_id]['input'];
            }
            else{
                $answer_num = 1;
                $inputs[$question_id]['input'] = 1;
            }


            // Overime, jestli neni type jiny ve stejne otazce, pokud ano, znamena to chybu ve formulari
            if(empty($inputs[$question_id]['type'])){
                $inputs[$question_id]['type'] = $type;
            }
            // Zkusime, jestli neni vadny formular, pokud ano, pridame neco do $errors
            // a vynulujeme pocitadlo cisla odpovedi
            elseif($inputs[$question_id]['type'] != $type &&
                !($type == 'option' && ($inputs[$question_id]['type'] == 'select' ||
                $inputs[$question_id]['type'] == 'selectmulti')))
            {
                $answer_num = 1;
                $inputs[$question_id]['input'] = 1;

                $errors[] = array('question_id' => $question_id, 'problem' => 'differentTypes', 'text' =>
                    "Otázka s číslem '$question_id' má v zadávacích polích více typů (".
                    $inputs[$question_id]['type']." a $type), to značí chybu v číslování otázek.");
            }

            // Id odpovedi
            $answer_id_part = '_'.$question_id.'_'.$answer_num;

            // Vytvorime retezec k nahrazeni tagu podle typu vstupu
            if($type == 'radio'){
                $answer_id_str = 'r'.$answer_id_part;
                $question_id_str = 'r_'.$question_id;
                $html = sprintf('input name="%s" id="%s" type="radio" value="%s" %s',
                                $question_id_str,
                                $answer_id_str,
                                $answer_num,
                                isset($this->prefill_answr[$answer_id_str]) ?
                                   $this->prefill_answr[$answer_id_str] : '');
            }
            elseif($type == 'checkbox'){
                $answer_id_str = 'c'.$answer_id_part;
                $question_id_str = 'c_'.$question_id;
                $html = sprintf('input name="%s" id="%s" type="checkbox" value="%s" %s',
                                $answer_id_str,
                                $answer_id_str,
                                $answer_num,
                                isset($this->prefill_answr[$answer_id_str]) ?
                                   $this->prefill_answr[$answer_id_str] : '');
            }
            elseif($type == 'text'){
                $answer_id_str = 't'.$answer_id_part;
                $question_id_str = 't_'.$question_id;
                $html = sprintf('input name="%s" id="%s" type="text" value="%s"',
                                $question_id_str,
                                $answer_id_str,
                                isset($this->prefill_answr[$question_id_str]) ?
                                   $this->prefill_answr[$question_id_str] : '');
            }
            elseif($type == 'textarea'){
                $answer_id_str = 't'.$answer_id_part;
                $question_id_str = 't_'.$question_id;
                $html = sprintf('name="%s" id="%s">%s</textarea',
                                $question_id_str,
                                $answer_id_str,
                                isset($this->prefill_answr[$question_id_str]) ?
                                   $this->prefill_answr[$question_id_str] : '');
            }
            elseif($type == 'select'){
                //zrusime zaznam v inputs pro tuto otazku - inputy jsou az option
                unset($inputs[$question_id]['input']);
                //nastavime, ze se jedna o prosty select v teto otazce
                $inputs[$question_id]['is_multi'] = false;

                $answer_id_str = 'r'.$answer_id_part;
                $question_id_str = 'r_'.$question_id;
                $html = sprintf('select name="%s" id="%s"',
                                $question_id_str,
                                $answer_id_str);
            }
            elseif($type == 'selectmulti'){
                //zrusime zaznam v inputs pro tuto otazku - inputy jsou az option
                unset($inputs[$question_id]['input']);
                //nastavime, ze se jedna o selectmulti v teto otazce
                $inputs[$question_id]['is_multi'] = true;

                $answer_id_str = 'm'.$answer_id_part;
                $question_id_str = 'm_'.$question_id;
                $html = sprintf('select name="%s[]" id="%s" multiple="multiple"',
                                $question_id_str,
                                $answer_id_str);
            }
            elseif($type == 'option'){
                // Pozor, ID kazdeho tagu option neni ve std formatu r_3_2, ale r_3_2_o
                // std. format pouzivame pro

                // Zvolime typ otazky podle toho, jestli je select multi nebo ne
                if(isset($inputs[$question_id]['is_multi']) && $inputs[$question_id]['is_multi']){
                    $type_char = 'm';
                }
                else{
                    $type_char = 'r';
                }

                $answer_id_str = $type_char.$answer_id_part;
                $question_id_str = $type_char.'_'.$question_id;
                $html = sprintf('option id="%s" value="%s" %s',
                                $answer_id_str.'_o',
                                $answer_num,
                                (!empty($this->prefill_answr[$answer_id_str]) ?
                                    'selected="selected"' : ''));
            }
            elseif($type == 'hidden'){
                $answer_id_str = 't'.$answer_id_part;
                $question_id_str = 't_'.$question_id;
                $html = sprintf('input name="%s" id="%s" type="hidden" value="%s"',
                                $question_id_str,
                                $answer_id_str,
                                isset($this->prefill_answr[$question_id_str]) ?
                                   $this->prefill_answr[$question_id_str] : '');
            }

            // Nahradime tag pozadovanym html kodem
            $out = preg_replace('/'.$tag.$type.'#'.$question_id.'%%/', $html, $out, 1);

            // Pro nektere tagy se label nezapisuje, takze musi byt preskocen
            if($type != 'option'){
                // Nahradime tag odpovidajiciho labelu html kodem labelu
                // nejprve se zkousi nahradit odpovidajici label v otazce, potom
                // se pripadne zkusi nahradit label bez cisla otazky
                $html_label = 'label for="'.$answer_id_str.'"';
                $count = 0;
                $out = preg_replace('/'.$taglabel.'#'.$question_id.'%%/', $html_label, $out, 1, $count);
                if(!$count){
                    $out = preg_replace('/'.$taglabel.'%%/', $html_label, $out, 1);
                }
            }
        }


        // Specialni znacka pro vstupni textbox pro email, ktery zmeni email
        // v tabulce users a odpovidajici label
        $html = $html = sprintf('input name="email" id="email" type="text" value="%s"',
            !empty($this->user_data['email']) ? $this->user_data['email'] : '@');
        $out = preg_replace('/%%emailtextbox%%/', $html, $out);

        $html_label = 'label for="email"';
        $out = preg_replace('/%%labelemail%%/', $html_label, $out);

        return $out;
    }

    /**
     * Sluzba, ktera ulozi odpoved na otazku z ajax dotazu.
     *
     * Rozlisuje nazvy atributu ve tvaru /^answer_([rct]{1})_([0-9])+/,
     * kde prvni pismeno znaci typ pole:
     * r - radio - ocekava jednu hodnotu na otazku
     * c - checkbox -
     * t - text - jakakoli textova hodnota
     * return - vraci content
     */
    public function saveService($req)
    {
        // Pripravime data o aktualnim uzivateli
        $this->user_data = Ibulletin_Auth::getActualUserData();
        $this->user_id = $this->user_data['id'];

        $pageViewsId = $req->getParam('pageviewsid', null);

        $params = $req->getParams();
        $matches = array();
        $answers = array(); // Zagregovane odpovedi
        foreach($params as $name => $param){

            if(preg_match('/^answer_([rct]{1})_([0-9]+)/i', $name, $matches)){
                $question = (int)$matches[2];
                $type = $matches[1];
                $answer = $param;
                $text = null;

                if($type == 't'){
                    if ($req->isGet()) {
                        $text = rawurldecode($param);
                    } else {
                        $text = $param;
                    }
                    
                }
                elseif($type == 'c'){
                    $m = array();
                    $answer = array($param);
                }
                elseif($type == 'r'){
                    $m = array();
                    if(preg_match('/([0-9]*)/', $param, $m)){
                        $answer = $m[1];
                    }
                    else{
                        Phc_ErrorLog::warning('Ibulletin_Content_Questionnaire::saveService()',
                            'Nespravny format dat pro odpoved radioboxem. name: '.$name.' data: '.$param.
                            "user_id: '$this->user_id', content_id: '$this->id'");
                        continue;
                    }
                }
                
                if(empty($answers[$question])){
                    $answers[$question] = array('answer' => $answer, 'type' => $type, 'text' =>$text);
                }
                // Pokud ukladame checkbox, musime rozsirit pole answer
                else{
                    // Pokud jsou data spravna (pole v minule i v nove odpovedi), spojime pole do jednoho
                    if(is_array($answers[$question]['answer']) && is_array($answer)){
                        $answers[$question]['answer'] = array_merge($answers[$question]['answer'], $answer);
                    }
                    else{
                        // Problem s datovymi typy - vice odpovedi muze mit jen checkbox
                        Phc_ErrorLog::warning('Ibulletin_Content_Questionnaire::saveService()',
                            'Nespravny format dat odpovedi. Vice odpovedi na otazku, ktera neni ve vsech vyskytech checkbox. '.
                            'Nebylo mozne spojit answer s last-answer'.
                            ' name: '.$name.' data: '.$param.' answer:'.print_r($answer, true).' last-answer:'.print_r($answers[$question]['answer'], true).
                            "user_id: '$this->user_id', content_id: '$this->id'");
                    }
                }
                $matches = array();
            }
            // Pokud se jedna o data v POST, zalogujeme je, protoze to muze byt vadne udelana anketa
            // co neni v POST je jiz logovano v page_views
            elseif(!empty($_POST[$name])){
                Phc_ErrorLog::warning('Ibulletin_Content_Questionnaire::saveService()',
                    'Nespravny format dat odpovedi. name: '.$name.' data: '.$param.
                    "user_id: '$this->user_id', content_id: '$this->id'");
            }
            //Phc_ErrorLog::debug('save Quest', 'name: '.$name.' value: '.$param);
        }
        
        // Ulozime postupne zagregovane odpovedi
        foreach($answers as $question => $answer){
            $this->writeAnswer($question, $answer['answer'], $answer['type'], $answer['text'], $pageViewsId);
        }
        
        //vrati html contentu
        if (isset($params['return'])) {
            $this->loadQuestionnaireData();
            $c = $this->getContent();
            echo eval($c['form_html']);
        }
    }

}
