<?php
/**
 * iBulletin - Ibulletin_Marks.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}

/**
 * Trida poskytujici funkce spojene se specialnimi znackami v HTML kodu
 * (%%static%%, formularove prvky...).
 *
 * Obsahuje take funkci pro provedeni TIDY, kterou pouzivaji contenty a jine
 * komponenty, kde admin vklada HTML. Na samotne formulare nelze TIDY pouzit kvuli
 * nekompatibilnimu markupu formu s HTML.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Marks
{
    /**
     * Metoda slouzici k prelozeni veskerych znacek v HTML zadavanem
     * v adminu a podobne.
     * 
     * Nahrazuje take URL v <a href=""> zacinajici na http a https.
     *
     * Trida je napsana jako jeden douhy blok kodu, aby nevznikala zbytecna rezije
     * volanim malych metod pro kazdy prekladany fenomen.
     * 
     * %%static%%
     * %%mainImg%%
     * %%form%%
     * %%input_...%%
     * %%label%%
     * %%label#...%%
     * %%link#...%%
     * %%emailtextbox%% - pro zmenu emailu uzivatele v tabulce users
     * %%labelemail%% - label pro zmenu emailu v tabulce users
     * 
     *
     * @param string  HTML k prelozeni.
     * @param string  Cesta do adresare se statickym obsahem pro prave
     *                prekladany HTML kod.
     * @param Ibulletin_Content_Abstract     Objekt contentu pro vyuziti pri nahrade znacek jako hlavni obrazek contentu
     * @param
     * @param
     * @param bool    Prekladat i URL v <a href="">?
     * 
     * @return string HTML kod se specialnimi znackami prelozenymi na odpovidajici HTML
     */
    public static function translate($string, $path_to_static = '', $content = null, $prefill_answr = array(),
                                     $user_data = array(), $translateUrls = true)
    {
        $out = $string;

        /**
         * Bezne znacky k nahrazeni jako:
         * %%static%%
         * %%mainImg%%
         */

        // Nahradime cestu do statickych dat %%static%%
        $path = $path_to_static;
        $token = '%%static%%';
        $out = str_ireplace($token, $path, $out);

        // Ziskani hlavniho obrazku pro dany content
        if($content){
            $token = '%%mainImgUrl%%';
            if(strpos($string, $token) !== false){
                // Pripravime url pro obrazek (potazmo i obrazek samotny)
                $mainImgUrl = Contents::getImage($content->id, 'mainimg');

                $out = str_ireplace($token, $mainImgUrl, $out);
            }
            // Cely html kod obrazku vcetne alt
            $token = '%%mainImg%%';
            if(strpos($string, $token) !== false){
                // Pripravime url pro obrazek (potazmo i obrazek samotny)
                if(empty($mainImgUrl)){
                    $mainImgUrl = Contents::getImage($content->id, 'mainimg');
                }

                $code = "<img src=\"$mainImgUrl\" alt=\"".str_ireplace('"', '\"', $content->name)."\"/>";
                $out = str_ireplace($token, $code, $out);
            }
        }


        // automaticke generovani linku z http(s)
        // nahrazujeme za existujici external link pokud existuje nalezene url v tele mailu
        // jinak vytvorime novy link a nasledne nalezene linky nahradime v tele mailu za tag %%link#id%%
        if($translateUrls){
            $externalLinks = new ExternalLinks();
            $out = $externalLinks->updateContentLinks($out);
        }

        // LINKY %%link#[id]%%
        $token = '%%link#([0-9]+)%%';
        $m = array();
        if(preg_match_all('/'.$token.'/', $out, $m)){
            // Pro kazdy match prelozime link na URL a nahradime
            // Kazde nahrazeni provadime pouze jednou na vsechny vyskyty
            $viewHelper = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');
            $linkUrlHelper = $viewHelper->view->getHelper('linkurl');
            $replaced = array(); // Pole obsahujici ID linku, ktere jsme uz prelozili
            foreach($m[1] as $key => $linkId){
                if(!in_array($linkId, $replaced)){
                    $linkUrl = $linkUrlHelper->linkurl((int)$linkId);
                    
                    // Nahradime vsechny vyskyty tagu
                    $out = str_ireplace($m[0][$key], $linkUrl, $out);
                    
                    $replaced[] = $linkId;
                }
            }
        }


        /**
         * Prvky generovanych formularu
         */

        if($content){
            // Nahradime %%form%% retezcem form id=""
            $replacement = "form id=\"form_".$content->id."\" method=\"POST\" action=\"\" ";
            $token = '%%form%%';
            $out = str_ireplace($token, $replacement, $out);
 
            //za tag prelozeny tag %%form%% pripojime hidden input s contentId, 
            //abychom mohli identifikovat dotazniky pri zpracovani v pripade vice dotazniku na strance
            $cidInput  = '<input type="hidden" name="content_id" value="'.$content->id.'" />';
            $out = preg_replace("/".preg_quote($replacement)."[ ]*>/i", $replacement.'>'.$cidInput, $out);

            // Nahradime znacky pro vnitrnosti tagu vstupnich poli
            $inputs = array();      // sem odkladame informace pro sledovani zadanych poli
            $tag = '%%input_';
            $taglabel = '%%label';
            $tag_len = strlen($tag);
            $startpos = 0 - $tag_len;
            while(1){
                //$e = new Exception();
                //Phc_ErrorLog::debug('marksdeb', $out.'|'.$tag.'|'.($startpos + $tag_len).' | '.$e);
                $startpos = strpos($out, $tag, $startpos + $tag_len);
                if($startpos === false){
                    break;
                }

                // vysekneme si retezec, z ktereho precteme informace o danem tagu
                $substr = @substr($out, $startpos + $tag_len, 30 + $tag_len);

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
                                    isset($prefill_answr[$answer_id_str]) ?
                                       $prefill_answr[$answer_id_str] : '');
                }
                elseif($type == 'checkbox'){
                    $answer_id_str = 'c'.$answer_id_part;
                    $question_id_str = 'c_'.$question_id;
                    $html = sprintf('input name="%s" id="%s" type="checkbox" value="%s" %s',
                                    $answer_id_str,
                                    $answer_id_str,
                                    $answer_num,
                                    isset($prefill_answr[$answer_id_str]) ?
                                       $prefill_answr[$answer_id_str] : '');
                }
                elseif($type == 'text'){
                    $answer_id_str = 't'.$answer_id_part;
                    $question_id_str = 't_'.$question_id;
                    $html = sprintf('input name="%s" id="%s" type="text" value="%s"',
                                    $question_id_str,
                                    $answer_id_str,
                                    isset($prefill_answr[$question_id_str]) ?
                                       $prefill_answr[$question_id_str] : '');
                }
                elseif($type == 'textarea'){
                    $answer_id_str = 't'.$answer_id_part;
                    $question_id_str = 't_'.$question_id;
                    $html = sprintf('name="%s" id="%s">%s</textarea',
                                    $question_id_str,
                                    $answer_id_str,
                                    isset($prefill_answr[$question_id_str]) ?
                                       $prefill_answr[$question_id_str] : '');
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
                                    (!empty($prefill_answr[$answer_id_str]) ?
                                        'selected="selected"' : ''));
                }
                elseif($type == 'hidden'){
                    $answer_id_str = 't'.$answer_id_part;
                    $question_id_str = 't_'.$question_id;
                    $html = sprintf('input name="%s" id="%s" type="hidden" value="%s"',
                                    $question_id_str,
                                    $answer_id_str,
                                    isset($prefill_answr[$question_id_str]) ?
                                       $prefill_answr[$question_id_str] : '');
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
        }


        // Specialni znacka pro vstupni textbox pro email, ktery zmeni email
        // v tabulce users a odpovidajici label
        $html = $html = sprintf('input name="email" id="email" type="text" value="%s"',
            !empty($user_data['email']) ? $user_data['email'] : '@');
        $out = preg_replace('/%%emailtextbox%%/', $html, $out);

        $html_label = 'label for="email"';
        $out = preg_replace('/%%labelemail%%/', $html_label, $out);
        
        //Znacka %%chart%% graf v ankete
        if ($content) {
            if(!$prefill_answr) {
                $out = preg_replace('/%%chart#([0-9]+)%%/', '', $out);
            } else {
                $ch = array();
                preg_match_all('/%%chart#([0-9]+)%%/',$out,$m);
                //seskupime znacky grafu a priradi cislo otazky
                foreach ($m[0] as $k => $v) {
                    $ch[$v][] = $m[1][$k];
                }
                
                $questions = new Questions($content->id);
                $view = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->view;
               
                //projdeme znacky a nahradime jednotlive znacky vyrenderovaných grafem
                foreach ($ch as $tag => $e) {
                    $a = $questions->getQuestionStats(1, $e[0]);
                    $stats = array();
                    //priprava statistik
                    foreach ($a['answers'] as $o) {
                        $stats[$o['answer_num']] = $o['count'];
                    }
                    //pocet vsech odpovedi
                    $qtotal = 0;
                    if (isset($a['answers'])) {
                        $qtotal = $a['question_sum'];
                    }
                    foreach ($e as $k => $v) {
                        $qcount = 0;
                        if(isset($stats[$k+1])) {
                            $qcount = $stats[$k+1];
                        }
                        $ch_html = $view->partial('questionnaire_chart.phtml', array('qtotal' => $qtotal, 'qcount' => $qcount));
                        $out = preg_replace('/' . $tag . '/', $ch_html, $out, 1);
                    }
                }
            }
        }
        
        return $out;
    }


    /**
     * Provede zacisteni HTML a vrati zpet odpovidajici blok HTML s opravenymi chybami.
     * Nedodrzuje puvodni formatovani HTML kodu, v nekterych pripadech muze pokazit vlozene PHP,
     * protoze PHP je pred provedenim TIDY odstraneno z HTML a nasledne vkladano.
     * HTML na miste, kde nemuze v HTML byt prosty retezec znaku bude ztraceno.
     *
     * @param string    HTML, ktere ma byt opraveno
     * @return string   Zacistene (TIDY) HTML odpovidajici vstupnim datum.
     */
    public function tidyHtml($html)
    {
        $tidyConfig = array(
            'force-output' => 1,
            'wrap' => 0,
            'markup' => 1,
            'drop-empty-paras' => 0,
            'break-before-br' => 0,
            'indent' => 1,
            'vertical-space' => 0,
            'output-xhtml' => true,
            'doctype' => 'strict',
            );

        // Odstranime vsechno vlozene PHP, nahradime ho docasnymi tagy
        $phpPattern = '/\<\?(.+?)\?\>/s';
        $phpCodeMark = 'XSVBphpCodeYTFCC';
        $phpCodes = array();
        $matches = array();
        $counter = 0;
        while(preg_match($phpPattern, $html, $matches)){
            $phpCodes[$counter] = $matches[0];
            $html = preg_replace($phpPattern, $phpCodeMark.$counter, $html, 1);

            $matches = array();
            $counter ++;
        }

        $tidy = new tidy();
        $tidy->ParseString($html, $tidyConfig, 'utf8');
        // Opravime HTML
        $tidy->cleanRepair();

        // Ziskame cely element BODY
        $body = $tidy->body();
        // ROOT pro ziskani head
        $root = $tidy->root();

        // Vypiseme jen obsah elementu body (kazdy children), navic jeste pripadny style z HEAD
        $outString = "";
        // Hledame v HEAD style - nejprve html, pak head a nakonec style
        foreach($root->child as $elem){
            if($elem->name == 'html' && !empty($elem->child)){
                foreach($elem->child as $elem1){
                    if($elem1->name == 'head' && !empty($elem1->child)){
                        foreach($elem1->child as $elem2){
                            if($elem2->name == 'style' && !empty($elem2->child)){
                                $outString .= $elem2->value;
                                break;
                            }
                        }
                        break;
                    }
                }
                break;
            }
        }
        // Obsah BODY vypiseme do retezce
        if(!empty($body->child)){
            foreach($body->child as $child){
                $outString .= $child->value;
            }
        }

        // Provedeme zpetne nahrazeni PHP taguu za puvodni PHP code
        foreach($phpCodes as $counter => $code){
            $outString = preg_replace('/'.$phpCodeMark.$counter.'/', $code, $outString, 1);
        }

        //echo "<textarea cols=80 rows=60>$outString</textarea>";

        return $outString;
        //return (string)$tidy;
    }


    /**
     * !!NEPOUZIVA SE!! Nefunguje spravne - neumi vypsat problemy, ktere by opravil
     * (napriklad chybejici uzaviraci tagy). Jen k pripadnemu nahlednuti.
     *
     * Zjisti chyby v HTML pomoci TIDY a vrati pole s popisem chyb. Snazi se
     * omezit vypis nepodstatnych chyb vyplyvajicich z pouziti vlozeneho PHP
     * a specialnich znacek inBoxu.
     *
     * @param string    HTML, ktere ma byt diagnostikovano
     * @return array    Informace o chybach v HTML
     */
    public function tidyHtmlDiagnose($html)
    {
        $tidyConfig = array(
            'force-output' => 1,
            'wrap' => 0,
            'markup' => 1,
            'drop-empty-paras' => 0,
            'break-before-br' => 0,
            'indent' => 1,
            'vertical-space' => 0,
            'output-xhtml' => true,
            'doctype' => 'strict',
            );


        // Odstranime vsechno vlozene PHP
        $html = preg_replace('/\<\?(.+?)\?\>/s', 'phpCode', $html);

        // Pridame kolem bloku HTML kodu zbytek dokumentu kvuli hlaseni chyb od TIDY
        $html = "
        <!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"
           \"http://www.w3.org/TR/2000/REC-xhtml1-20000126/DTD/xhtml1-strict.dtd\">
        <html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"cs\" lang=\"cs\">
        <head>

        <title>xxx</title>
        </head>
        <body>
        $html
        </body>
        </html>
        ";

        //echo $this->tidyHtml($html);

        $tidy = new tidy();
        $tidy->ParseString($html, $tidyConfig, 'utf8');

        $tidy->cleanRepair();
        $tidy->diagnose();

        echo "<textarea cols=80 rows=60>$tidy</textarea>";

        $outArray = explode("\n", $tidy->errorBuffer);

        foreach($outArray as $item){
            echo htmlspecialchars($item)."<br/>";
        }

        return $outArray;
    }
}
