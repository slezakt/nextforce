<?php

/**
 * iBulletin - Virtualpatient.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}
class Ibulletin_Content_Virtualpatient_Exception extends Exception {}

Zend_Loader::loadClass('Ibulletin_Content_Abstract');

/**
 * Trida zprostredkovavajici zobrazeni virtualniho pacienta.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Content_Virtualpatient extends Ibulletin_Content_Abstract
{
    
    /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "virtualpatient_%d.phtml";
    
    /**
     * @var int Cislo dotazniku
     */
    var $questionnaire_num = null;
    
    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     * 
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepare($req)
    {
        // Nachystame do $this->sheet_number cislo listu s ohledem na pozici contentu na strance
        $this->prepareSheetNumber();
        
        // Provedeme zpracovani prijatych
        $this->_evaluate();
        
    }
    

    /**
     * Vrati renderovaci data contentu s ohledem na nastavenou pozici v hlavnim contentu.
     * 
     * Nachysta do view data jednotlivych prispevku a pripadne predvyplneni formulare.
     */
    public function getContent()
    {
        $config = Zend_Registry::get('config');
		$urlHlpr = new Zend_View_Helper_Url();
		$baseUrl = $urlHlpr->url(array(),'default',true);
						
        $path = $baseUrl . $config->content_article->basepath.$this->id.'/';
        $view = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer')->view;
        
        // Prelozime znacky
        if(isset($this->html[$this->sheet_number-1])){
            $html = array();
            $html[0] = Ibulletin_Marks::translate($this->html[$this->sheet_number-1][0], $path, $this);
            $html[1] = Ibulletin_Marks::translate($this->html[$this->sheet_number-1][1], $path, $this);
        }
        else{
            $html = array('', '');
        }
        
        // Html se bude prohanet eval(), takze na zacatek pridame koncovy tag php
        return array('html1' => '?> '.$html[0], 'html2' => '?> '.$html[1]);
    }
    
    /**
     * Vrati dalsi data, ktera pouziva objekt ve view
     * 
     * @return array/stdClass    Dalsi data, ktera pouziva obsah pro sve zobrazeni ve view
     */
    public function getData(){
        $urlHlpr = new Zend_View_Helper_Url();
        $config = Zend_Registry::get('config');
        
        $data = new stdClass;
        
        // Ziskame seznamy uzamcenych listuu pred zodpovezenim otazky a po zodpovezeni otazky
        $locked_sheets = Virtualpatient::getLockedSheetList($this->id, count($this->html), $this->sheet_number);
        
        // Pokud se nachazime na zamcenem liste, presmerujeme na posledni odemceny list
        if(in_array($this->sheet_number, $locked_sheets['before'])){
            reset($locked_sheets['before']);
            $sheet_to_redirect = current($locked_sheets['before']) - 1;
            
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoRouteAndExit(array($config->general->url_name->sheet_number => $sheet_to_redirect));
        }
        
        // Ziskame data otazky
        $question_data = Virtualpatient::getQuestion(null, $this->id, $this->sheet_number);
        // Pokud otazka pro tento list a obsah existuje, najdeme data pro jeji odpovedi
        if($question_data != null){
            $answers = Virtualpatient::getQuestionAnswers($question_data['id']);
            // Ziskame jiz provedene pokusy pro tuto otazku
            $attempts = Virtualpatient::getUsersAttempts($question_data['id']);
            
            // Zjistime, jestli uz byla vybrana spravna odpoved kvuli zamceni nekliknutych odpovedi
            $has_correct = false;
            foreach($attempts as $attempt){
                if($attempt['is_correct']){
                    $has_correct = true;
                    break;
                }
            }
            
            // Zjistime, jestli je nektery z pokusu spravna odpoved, pokud ano, muze byt navigace odemcena
            // Odemceni navigace jeste zavisi na configu.
            $lock_navigation = $config->virtual_patient->lock_navigation;
            if($lock_navigation){
                if($has_correct){
                    $data->lock_navigation = false;
                }
                else{
                    $data->lock_navigation = true;
                }
            }
            else{
                $data->lock_navigation = false;
            }
            
          # Pripravime vsechny JS pro funkci ajaxoveho VP
            // JS pro pripraveni XMLHTTP volani
            // POZOR, obsahuje inicializaci promenne window.vpattempts_answered_correctly
            Ibulletin_Js::addFunction(
                "
                window.xmlhttp=false;
                /*@cc_on @*/
                /*@if (@_jscript_version >= 5)
                try {
                    xmlhttp = new ActiveXObject(\"Msxml2.XmlHttp.4.0\");
                 
                } catch (e) {
                  
                  try {
                   xmlhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");
                  } catch (E) {
                   xmlhttp = false;
                  }
                 }
                @end @*/
                
                if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
                    try {
                        xmlhttp = new XMLHttpRequest();
                    } catch (e) {
                        xmlhttp=false;
                    }
                }
                if (!xmlhttp && window.createRequest) {
                    try {
                        xmlhttp = window.createRequest();
                    } catch (e) {
                        xmlhttp=false;
                    }
                }
                
                window.vpattempts_answered_correctly = false;   
                ", null, array(), true);
            Ibulletin_Js::addFunction(
                "
                     xmlhttp.open('GET', adr, true);
                     //alert(adr);
                     xmlhttp.onreadystatechange=function() {
                      if (xmlhttp.readyState==4) {
                       //alert(xmlhttp.responseText);
                       //eval(func+'(xmlhttp.responseXML,id)');
                      }
                     }
                     xmlhttp.send(null)
                ", 'getXMLHTTPreq', array('adr'), false);
            
            // Odkryti pro kazdou nabizenou odpoved
            $unhide_all_code = '';
            foreach($answers as $answer){
                if(!array_key_exists($answer['id'], $attempts)){
                    
                    // Pripadny kod pro odemceni navigace
                    if($answer['is_correct']){
                        $unlock_after_answer_str = join("','", array_diff($locked_sheets['before'],
                                                                        $locked_sheets['after']));
                        $unlock_nav = "
                            var next = document.getElementById('sheet_navigation_next');
                            var unlock_after_array = new Array('$unlock_after_answer_str');
                            
                            if(next){
                                next.style.display = 'block';
                            }
                            var text_locked = document.getElementById('sheet_navigation_text_locked');
                            if(text_locked){
                                text_locked.style.display = 'none';
                                document.getElementById('sheet_navigation_text').style.display = 'block';
                            };
                            
                            var span = null;
                            var anchor = null;
                            for(i=0; i<unlock_after_array.length; i++){
                                span = document.getElementById('sheet_navigation_locked_' + unlock_after_array[i]);
                                anchor = document.getElementById('sheet_navigation_unlocked_' + unlock_after_array[i]);
                                if(span && anchor){
                                    span.style.display = 'none';
                                    anchor.style.display = 'inline';
                                }
                            }
                            "; 
                    }
                    else{
                        $unlock_nav = '';
                    }
                    
                    // Kod pro pridani odpovedi do seznamu odpovedi nahore
                    if($answer['is_correct']){
                        $add_attempt = "
                            var attempts = document.getElementById('vpattempts');
                            var attempt = document.createElement('div');
                            attempt.setAttribute('class', 'ok');
                            attempt.setAttribute('id', 'vpattempts_div_ok');
                            var text = document.createTextNode('".$answer['name']."');
                            var p = document.getElementById('vpattempts_p');
                            if(!p){
                                p = document.getElementById('vpattempts_p_ok');
                            }
                            p.parentNode.removeChild(p);
                            attempt.appendChild(text);
                            attempts.appendChild(attempt);
                            p = document.createElement('p');
                            p.setAttribute('id', 'vpattempts_p_ok');
                            p.setAttribute('class', 'ok');
                            var p_text = document.createTextNode('".html_entity_decode(sprintf(Ibulletin_Texts::get('content.virtualpatient.correct_answer'), $answer['name']))."');
                            p.appendChild(p_text);
                            attempts.appendChild(p);
                            window.vpattempts_answered_correctly = true;
                        ";
                    }
                    else{
                        $add_attempt = "
                            var attempts = document.getElementById('vpattempts');
                            var attempt = document.createElement('div');
                            //attempt.setAttribute('class', '');
                            var text = document.createTextNode('".$answer['name']."');
                            var p = document.getElementById('vpattempts_p');
                            if(!p){
                                var p_is_ok = true;
                                p = document.getElementById('vpattempts_p_ok');
                            }
                            else{
                                var p_is_ok = false;
                            }
                            p.parentNode.removeChild(p);
                            attempt.appendChild(text);
                            attempts.appendChild(attempt);
                            if(!p_is_ok){
                                p = document.createElement('p');
                                p.setAttribute('id', 'vpattempts_p');
                                //p.setAttribute('class', 'ok');
                                var p_text = document.createTextNode('".html_entity_decode(Ibulletin_Texts::get('content.virtualpatient.choose_another'))."');
                                p.appendChild(p_text);
                            }
                            attempts.appendChild(p);
                        ";
                    }
                    Ibulletin_Js::addOnActionCode(array($answer['name'].'_unclicked'), 'click', "
                        var event = e || window.event;
                        if(vpattempts_answered_correctly){
                            event.returnValue=false;
                            if(event.cancelable){
                                event.preventDefault();
                            }
                            return false;
                        }
                        var clicked = document.getElementById('".$answer['name']."_clicked');
                        var unclicked = document.getElementById('".$answer['name']."_unclicked');
                        unclicked.style.display = 'none';
                        clicked.style.display = 'block';
                        ".($answer['is_correct'] ? 'clickAllquestions();' : '')."
                        getXMLHTTPreq('".$urlHlpr->url(array('answer' => $answer['name']))."');
                        ".$unlock_nav.$add_attempt."
                        event.returnValue=false;
                        if(event.cancelable){
                            event.preventDefault();
                        }
                        return false;
                    ");
                }
                
                // Kod pro odkryti vsech otazek pri kliknuti na spravnou
                $unhide_all_code .= "
                    var clicked = document.getElementById('".$answer['name']."_clicked');
                    var unclicked = document.getElementById('".$answer['name']."_unclicked');
                    if(unclicked && clicked){
                        unclicked.style.display = 'none';
                        clicked.style.display = 'block';
                    }";
            }
            
            // Funkce pro odkryti vsech otazek
            Ibulletin_Js::addFunction($unhide_all_code, 'clickAllquestions', array(), false);
            
            // Pridame do dat
            $data->question = $question_data;
            $data->answers = $answers;
            $data->attempts = $attempts;
        }
        else{
            $data->lock_navigation = false;
        }
        
        $data->locked_before = $locked_sheets['before']; 
        $data->locked_after = $locked_sheets['after'];
        
        $data->heading = !empty($this->heading[$this->sheet_number - 1]) ? $this->heading[$this->sheet_number - 1] : null;
        
        return $data;
    }
    
    
    
    /**
     * Vrati objekt search dokumentu pro zaindexovani tohoto contentu v search.
     *
     * @return Zend_Search_Lucene_Document Search document pro zaindexovani contentu
     */
    public function getSearchDocument()
    {
        Zend_Loader::loadClass('Zend_Search_Lucene_Document_Html');
        Zend_Loader::loadClass('Zend_Search_Lucene_Field');
        
        // Html parser bere jedine cely html dokument, 
        // takze kodovani a strukturu kolem tam dodelame
        $body = "<html>
        <head>
        <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
        </head>
        <body>".Utils::translit($this->html[$this->sheet_number-1][0].$this->html[$this->sheet_number-1][1])."</body></html>";
        
        $doc = Zend_Search_Lucene_Document_Html::loadHTML($body);
        //$doc->addField(Zend_Search_Lucene_Field::Text('annotation', $this->annotation, 'UTF-8'));
        //$doc->addField(Zend_Search_Lucene_Field::Text('author', $this->getAuthor(), 'UTF-8'));
        //$doc->addField(Zend_Search_Lucene_Field::Text('title', $this->name, 'UTF-8'));
        $doc->addField(Zend_Search_Lucene_Field::Keyword('content_id', $this->id, 'UTF-8'));
        
        
        return $doc;
    }
    
    /**
     * Funkce ulozi prijate informace o stisknute volbe.
     */
    private function _evaluate(){
        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        //$session = Zend_Registry::get('session');
        
        if(!$request->has('answer')){
            // Nebyla odeslana volba odpovedi, neni co delat
            return;
        }
        
        // Zjistime odpoved - ovsem v pojmenovani podle answer naming
        $answer = $request->getParam('answer');
        
        // Zjistime id otazky
        $question_data = Virtualpatient::getQuestion(null, $this->id, $this->sheet_number);
        $question_id = $question_data['id'];
        
        // Ulozime
        try{
            Virtualpatient::writeAttempt($question_id, null, $answer);
        }
        catch(Virtualpatient_Exception $e){
            Phc_ErrorLog::warning('Ibulletin_Content_Virtualpatient', "Nepodarilo se zapsat pokus o odpoved na otazku. Puvodni vyjimka:\n$e");
        }
        
        // Aby se dotaz neopakoval, redirectujeme nebo v pripade AJAX dotazu rovnou ukoncime
        if($request->isXmlHttpRequest()){
            Ibulletin_Stats::getInstance()->setAttrib('action', 'ajax');
            Ibulletin_Stats::__destruct();
            exit();
        }
        else{
            $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
            $redirector->gotoRouteAndExit(array('answer' => null));
        }
    }
}
