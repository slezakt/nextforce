<?php
/**
 *  Controller pro ruzne jednorazove opravy a podobne, ktere nemuseji byt videt v menu
 *
 *  @author Bc. Petr Skoda
 */
class Admin_ServiceController extends Zend_Controller_Action
{

    public function indexAction(){

    }


    /**
     * Vytvori content pro video, content pro prezentaci a content webcastu
     * a content webcastu da do vlastni page, aby mohla byt pridana do bulletinu a zobrazena
     */
    public function addwebcastAction()
    {
        // Vytvorime content videa
        $obj = new Ibulletin_Content_Video;
        $obj->name = 'Video';
        $obj->content_position = 1;

        $video = Contents::edit(null, $obj);


        // Vytvorime content prezentace
        $obj = new Ibulletin_Content_Presentation;
        $obj->name = 'Prezentace';
        $obj->content_position = 1;

        $presentation = Contents::edit(null, $obj);


        // Vytvorime content webcastu
        $obj = new Ibulletin_Content_Webcast;
        $obj->name = 'Webcast_'.$video->id.'_'.$presentation->id;
        $obj->content_position = 1;
        $obj->video_id = $video->id;
        $obj->presentation_id = $presentation->id;

        $webcast = Contents::edit(null, $obj);


        ## Vytvorime page pro content webcastu
        // Vytvorime zaznamy s defaultnimi hodnotami v content_pages,
        // pages a links
        $db = Zend_Registry::get('db');

        $config =  Zend_Registry::get('config');
        $config_def_page = $config->admin_default_page_for_content;

        $name = $webcast->getName();
        // Jmeno ma maximalni povolenou delku v pages a links 100 znaku
        $name = substr($name, 0, 100);

        // Pridame zaznam do pages
        $ins_data = array('tpl_file' => $config_def_page->tpl_file,
                          'name' => $name);
        $db->insert('pages', $ins_data);
        $page_id = $db->lastInsertId('pages', 'id');

        // Pridame zaznam do links
        $ins_data = array('page_id' => $page_id,
                          'name' => $name);
        $db->insert('links', $ins_data);

        // Pridame zaznam do content_pages
        $ins_data = array('page_id' => $page_id,
                          'content_id' => $webcast->id,
                          'position' => $config_def_page->position);
        $db->insert('content_pages', $ins_data);




        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');
    }






    /**
     * Opravi konzistenci dat v page_views po pridani propojeni na users_links_tokens.
     */
    public function updatepageviewAction()
    {
        $db = Zend_Registry::get('db');

        $sel = $db->select();
        $sel->from('page_views')
            ->where('users_links_tokens_id IS NULL')
            ->where("url~*'/token/'")
            ->where("url !~* 'register/'")
            ->where("url !~* 'emailconfirm/track/token/'");
            //->limit(10);

        $rows = $db->fetchAll($sel);

        foreach($rows as $row){
            $matches = array();
            preg_match('/token\/([^\/]+)(\/){0,1}$/', $row['url'], $matches);
            $token = $matches[1];
            //$token = substr($row['url'], 7, 15);
            $sel = $db->select()
                ->from('users_links_tokens')
                ->where("token='".$token."'")
                ->limit(1);
            $ult_rows = $db->fetchAll($sel);
            if(isset($ult_rows[0])){
                $ult_row = $ult_rows[0];
            }
            else{
            echo 'Nenalezen zaznam v ult - page_view_id: '.$row['id'].' | url: '.$row['url'].' | '; // Chyba pri propojovani daneho prvku
                $ult_row = array('id' => null);
            }

            $db->update('page_views', array('users_links_tokens_id' => $ult_row['id']), 'id='.$row['id']);

            echo '"'.$token.'"<br/>';
        }

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');
    }

    /**
     * Jednoducha pomucka pro skladani PAGES
     */
    public function composepageAction(){
        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        $db = Zend_Registry::get('db');
        $req = $this->getRequest();
        $tplDir = 'views/scripts/page_templates/';

        // Seznam hlavnich template do selectboxu        
        $files = array();
        $path = $tplDir;
        if(file_exists($path)){
            $handle = opendir($path);
            while (($file = readdir($handle)) !== false){
                if ($file != '.' && $file != '..' && preg_match('/^main_/i', $file)){
                    $files[$file] = $file;
                }
            }
        }


        // Vytvorime formular
        $form = new Zend_Form(array('class' => 'zend_form_normal'));
        $form->setMethod('post');

        $form->addElement('hidden', 'sheet');

        $form->addElement('text', 'contents', array(
            'label' => 'Id contentuu oddelene mezerou: ',
        ));

        $form->addElement('select', 'tpl_name', array(
            'label' => 'Jmeno hlavniho template: ',
            'multioptions' => $files
        ));

        $form->addElement(new Zend_Form_Element_Submit(
            array(
                'name' => 'savepage',
                'label' => 'Uložit'
            )));

        echo $form;

        // Pokud se ma ulozit, provedeme vytvoreni nove stranky
        if($req->getParam('savepage', null)){
            $contents = explode(' ', $req->getParam('contents', ''));
            $tpl = $req->getParam('tpl_name');

            $contents_data = array();
            foreach($contents as $key => $content){
                if(!empty($content)){
                    $cont_data =  Contents::get((int)$content);
                    if($cont_data){
                        $contents_data[] = $cont_data;
                    }
                }
            }
            // Zkontrolujeme pole contents
            if(empty($contents_data)){
                echo "<br/>Nebyl zadan zadny existujicic content.";
                return;
            }
            // Zkontrolujeme existenci tpl souboru
            if(!file_exists($tplDir.$tpl)){
                echo "<br/>Nebyl zadan nazev existujicicho hlavniho template.";
                return;
            }


            $name = $contents_data[0]['object']->getName();

            // Pridame zaznam do pages
            $ins_data = array('tpl_file' => $tpl,
                              'name' => $name);
            $db->insert('pages', $ins_data);
            $page_id = $db->lastInsertId('pages', 'id');

            // Pridame zaznam do links
            $ins_data = array('page_id' => $page_id,
                              'name' => $name);
            $db->insert('links', $ins_data);

            // Pridame zaznam do content_pages
            $position = 1;
            foreach($contents_data as $content){
                $ins_data = array('page_id' => $page_id,
                                  'content_id' => $content['id'],
                                  'position' => $position);
                $db->insert('content_pages', $ins_data);
                $position ++;
            }

            echo "<br/>Byla vytvorena stranka s id: $page_id a name: $name";
        }

    }

    /**
     * Optimalizace vyhledavaciho indexu
     */
    public function optimizesearchAction(){
        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        set_time_limit(240);

        $index = Ibulletin_Search::getSearchIndex();
        $index->optimize();
    }

    /**
     * Reindexace vsech contentuu
     */
    public function reindexcontentsAction(){
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        ini_set('memory_limit', '64M');
        set_time_limit(240);

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        // Smazeme existujici indexy
        $indexDir = $config->search->index_file;
        if(file_exists($indexDir)){
            $dh = opendir($indexDir);
            while (($resource = readdir($dh)) !== false) {
                if($resource == '.' || $resource == '..'){
                    continue;
                }
                unlink($config->search->index_file.'/'.$resource);
            }
        }



        # Ulozime data objektu do vyhledavaciho indexu
        Zend_Loader::loadClass('Ibulletin_Search');
        $index = Ibulletin_Search::getSearchIndex();

        // Najdeme seznam ID contentuu
        $contents = Contents::getList();

        $counter = 0;
        foreach($contents as $content){
            echo $content['id'].' | ';
            Phc_ErrorLog::debug('contentreindex', 'content_id:'.$content['id']);

            $search_doc = $content['object']->getSearchDocument();

            /*
            // Nejprve odstranit stare indexy pro toto content ID
            Zend_Loader::loadClass('Zend_Search_Lucene_Index_Term');
            Zend_Loader::loadClass('Zend_Search_Lucene_Search_Query_Term');
            $term  = new Zend_Search_Lucene_Index_Term($content['id'], 'content_id');
            $query = new Zend_Search_Lucene_Search_Query_Term($term);
            $hits = $index->find($query);
            foreach ($hits as $hit) {
                $index->delete($hit->id);
            }
            */

            // Zaindexujeme dokument
            $index->addDocument($search_doc);

            if($counter>10){
                //$index->commit();
                //$index->optimize();
                $counter = 0;
            }

            $counter++;
        }

    }

    /**
     * Test funkcnosti Zend search
     */
    public function testsearchsaveAction(){
        set_time_limit(20);

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        # Ulozime data objektu do vyhledavaciho indexu
        Zend_Loader::loadClass('Ibulletin_Search');
        $index = Ibulletin_Search::getSearchIndex();

        // Najdeme seznam ID contentuu
        $contents = Contents::getList();

        reset($contents);
        $content = current($contents);

        $search_doc = $content['object']->getSearchDocument();


        // Nejprve odstranit stare indexy pro toto content ID
        Zend_Loader::loadClass('Zend_Search_Lucene_Index_Term');
        Zend_Loader::loadClass('Zend_Search_Lucene_Search_Query_Term');
        $term  = new Zend_Search_Lucene_Index_Term($content['id'], 'content_id');
        $query = new Zend_Search_Lucene_Search_Query_Term($term);
        $hits = $index->find($query);
        foreach ($hits as $hit) {
            $index->delete($hit->id);
        }


        // Zaindexujeme dokument
        $index->addDocument($search_doc);

        //$index->optimize();

        echo 'Dokument '.$content['object']->name.' byl zaindexovan. ';
        echo ' automatictestok';
    }


    /**
     * Spravne vyplneni nove pridaneho atributu sessions.timestamp
     */
    public function setsessiontimestampAction(){

        set_time_limit(240);

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        $db = Zend_Registry::get('db');

        $select = new Zend_Db_Select($db);
        $select->from(array('pv' => 'page_views'), array('session_id', 'timestamp'))
            ->join(array('s' => 'sessions'), 's.id=pv.session_id', array())
            ->order(array('pv.session_id ASC', 'pv.timestamp ASC'));

        $lastId = null;
        $limit = 10000;
        for($i=0; true; $i++){
            $select->limit($limit, $i * $limit);
            $sessions = $db->fetchAll($select);

            echo ($i*$limit)."<br/>";

            if(empty($sessions)){
                echo 'done<br/>';
                break;
            }

            foreach($sessions as $session){
                if($lastId != $session['session_id']){
                    $db->update('sessions', array('timestamp' => $session['timestamp']), 'id='.$session['session_id']);
                }
                $lastId = $session['session_id'];
            }
        }
    }

    /**
     * Test strlen
     */
    public function teststrlenAction(){
        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        $str = "ěščřžýáíé";
        $strlen = strlen($str);
        $mb_strlen = mb_strlen($str, 'ISO-8859-1');

        echo 'retezec v UTF-8 "'.$str.'" delka strlen | delka mb_strlen </br>';
        echo $strlen.' | ';
        echo $mb_strlen;

        if($strlen == $mb_strlen){
            echo ' automatictestok';
        }

    }

    /**
     * Test generovani XLS
     *
     * Vygeneruje xls soubor, ktery porovna s ulozenym souborem library/Phc/test.xls.
     *
     * Pokud se do url prida /getfile/1/, vrati XML soubor, ktery se generuje pro porovnavani.
     * Pri volani s /regenerate/1/ v URL se znovu naganereuje porovnavaci soubor a prepise se stavajici.
     *
     * @param bool  Ma se pouze znovu vygenerovat a ulozit soubor s kterym se porovnava?
     */
    public function testxlsexportAction($regenerateFile = false){
        // POROVNAVACI DATA - nesmi se zmenit, pokud se nezmeni i library/Phc/test.xls
        $table = array(
            array('mesto' => 0, 'more' => 1, 'kure' => 2),
            array('mesto' => '234', 'more' => 'čšřčšě', 'kure' => '345243'),
            array('mesto' => 'tds', 'more' => '32s3sst', 'kure' => 234),
            array('mesto' => 'tds', 'more' => '32s3sst', 'kure' => 234),
            array('mesto' => 'dsasdga', 'more' => 323, 'kure' => 234),
        );




        $req = $this->getRequest();

        if($req->getParam('getfile')){
            Ibulletin_Excel::ExportXLSX($table, 'test');
            exit;
        }
        if($req->getParam('regenerate') || $regenerateFile){
            // Nastavime render script
            $this->getHelper('viewRenderer')->setScriptAction('index');
            if(Ibulletin_Excel::ExportXLSX($table, 'test', false, null, null, 'library/Phc/')){
                $this->view->out = 'XML regenerated.';
            }
            else{
                $this->view->out = 'File could not be copied.';
            }
            return;
        }

        // Vygenerujeme xls do promenne
        ob_start();
        Ibulletin_Excel::ExportXLSX($table, 'test', true);
        $xlsGenerated = ob_get_contents();
        ob_end_clean();

        //Nacteme porovnavaci soubor do promenne
        $xlsOriginal = '';
        $fp = fopen('library/Phc/test.xls', 'r');
        while($piece = fgets($fp, '1000')){
            $xlsOriginal .= $piece;
        }


        // Porovname XLS
        if(strcmp($xlsOriginal, $xlsGenerated) == 0){
            $this->view->out = ' automatictestok';
        }
        else{
            $this->view->out = 'Files are not same.';
        }


        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');
    }


    /**
     * Test generovani PDF
     *
     * Vygeneruje pdf soubor, ktery porovna s ulozenym souborem library/Phc/test.pdf.
     *
     * Pokud se do url prida /getfile/test.pdf, vrati PDF soubor, ktery se generuje pro porovnavani.
     * Pri volani s /regenerate/1/ v URL se znovu naganereuje porovnavaci soubor a prepise se stavajici.
     *
     * @param bool  Ma se pouze znovu vygenerovat a ulozit soubor s kterym se porovnava?
     */
    public function testgeneratepdfAction($regenerateFile = false){
        // POROVNAVACI DATA - nesmi se zmenit, pokud se nezmeni i library/Phc/test.pdf
        $html = "
        <html>
          <head>
          </head>
          <body>
          <div class='mpdf'>
            <h2>Lehké astma u žen s nadváhou – nový fenotyp?</h2>

                <div id=\"casopis\">
                    <p>
                    <b>Engbers M, Vachier I, Sterk P et al. Respir Med. 2010 Apr 1.</b>
                    </p>
                    <p>
                    <i>MUDr. Milan Kasl</i>
                    </p>

          </div>
          <div class='foot'>&copy; 2010 <a href='http://www.pearshealthcyber.com'>Powered by Pears Health Cyber, s. r. o.</a>
            <br />Všechna práva vyhrazena. Tyto stránky jsou určené výhradně pro odbornou lékařskou veřejnost.
            <br /><br />
           </body>
           </html>
        ";
        //----------------------------------------------------------------------------------


        $req = $this->getRequest();


        // mPDF
        require_once('library/mpdf/mpdf.php');
        $mpdf = new mPDF('utf-8', 'A4');
        $mpdf->SetBasePath('./');
        $mpdf->WriteHTML($css,1);   // The parameter 1 tells that this is css/style only and no body/html/text
        $mpdf->WriteHTML($html, 2);
        // Problem je, ze se footer neda formatovat pomoci css dokumentu, pokud je na kazde strane,
        // proto je tu jen na posledni strance
        // DAVAME PRIMO DO HTML
        // $mpdf->SetHTMLFooter($footer);


        // Pokud se jedna jen o vygenerovani testovaciho souboru
        if($req->getParam('getfile')){
            $mpdf->Output('test.pdf', 'D');
            exit;
        }
        // Pokud se ma soubor znovu nagenerovat
        if($req->getParam('regenerate') || $regenerateFile){
            // Nastavime render script
            $this->getHelper('viewRenderer')->setScriptAction('index');

            $mpdf->Output('library/Phc/test.pdf', 'F');
            $this->view->out = 'PDF regenerated';
            return;
        }

        // Vygenerujeme PDF do stringu
        $pdfGenerated = $mpdf->Output('test.pdf', 'S');


        //Nacteme porovnavaci soubor do promenne
        $pdfOriginal = '';
        $fp = fopen('library/Phc/test.pdf', 'r');
        while($piece = fgets($fp, '1000')){
            $pdfOriginal .= $piece;
        }
        fclose($fp);

        // Musime v obou souborech opravit datumy, aby mohly byt shodne, provadime trochu nesikovne
        $files = array('original' => $pdfOriginal, 'generated' => $pdfGenerated);
        foreach($files as $key => $file){
            $file = preg_replace('/ \(D\:[0-9]{14}\)/', ' (D:20100726102344)', $file);
            $file = preg_replace('/\([0-9]{14}(\+.+){0,1}\)/', ' (D:20100726102344)', $file);
            // /Info 28 0 R /ID [<68661f3becf7c3047102ab658f8e46fc> <68661f3becf7c3047102ab658f8e46fc>]
            $file = preg_replace('/\/ID \[(\<[a-z0-9]+\>){0,1} (\<[a-z0-9]+\>\){0,1}]/',
                '/ID [<68661f3becf7c3047102ab658f8e46fc> <68661f3becf7c3047102ab658f8e46fc>]', $file);
            $files[$key] = $file;
        }

        /*
        $fp = fopen('library/Phc/test1.pdf', 'w');
        fputs($fp, $files['original']);
        //fputs($fp, $pdfOriginal);
        fclose($fp);
        */

        // Porovname PDF
        if(strcmp($files['original'], $files['generated']) == 0){
            $this->view->out = ' automatictestok';
        }
        else{
            $this->view->out = ' generated file is not same';
            $lastNotMatch = null;
            for($i=0; $i<strlen($files['original']); $i++){
                if(substr($files['original'], $i, 1) != substr($files['generated'], $i, 1)){
                    if($lastNotMatch === null || ($i - $lastNotMatch > 50)){
                        echo "diffOrig:".substr($files['original'], ($i - 30 > 0 ? $i-30 : 0), 60)."<br/>\n";
                        echo "diffGene:".substr($files['generated'], ($i - 30 > 0 ? $i-30 : 0), 60)."<br/>\n";
                    }
                    $lastNotMatch = $i;
                }
            }
            echo '<h4>Generated file</h4>'."\n";
            echo $files['generated'];
            echo "<br/><br/>\n\n";
            echo '<h4>Original file</h4>'."\n";
            echo $files['original'];
        }


        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');
    }

    /**
     * Znovu nageneruje a prepise porovnavaci soubory pro test XLS a PDF exporteru.
     */
    public function regeneratetestfilesAction(){
        $this->testxlsexportAction(true);
        $this->testgeneratepdfAction(true);

        $this->view->out = 'Regenerate done.';

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');
    }


    /**
     * Regenruje users_links_tokens.users_emails_id - odhaduje zaznam v users_emails
     */
    public function regenerateultueidAction()
    {
        ini_set('memory_limit', '512M');

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        $db = Zend_Registry::get('db');


        // Pripravime pole obsahujici pocty odkazu v jednotlivych mailech
        $sel = "select ue.email_id,
                    round((select count(*) from users_links_tokens where email_id = ue.email_id)
                        / count(id)::float) as links
                from users_emails ue
                group by email_id";
        $linksInMailsRaw =  $db->fetchAll($sel);
        $linksInMails = array();
        foreach($linksInMailsRaw as $email){
            $linksInMails[$email['email_id']] = $email['links'];
        }
        unset($linksInMailsRaw);



        // Ziskame a nachystame do pole seznam zaznamu z users_emails
        // $usersEmails[userId][emailId]
        $sel = new Zend_Db_Select($db);
        $sel->from('users_emails', array('id', 'user_id', 'email_id'))
            ->order(array('email_id', 'user_id', 'id'))
            ->where('id not in (select distinct(users_emails_id) from users_links_tokens where users_emails_id is not null)')
            ->where('(sent is not null OR status = \'bad_addr\')');
        $usersEmailsRaw =  $db->fetchAll($sel);
        $usersEmails = array();
        foreach($usersEmailsRaw as $row){
            if(!isset($usersEmails[$row['user_id']])){
                $usersEmails[$row['user_id']] = array();
            }
            if(!isset($usersEmails[$row['user_id']][$row['email_id']])){
                $usersEmails[$row['user_id']][$row['email_id']] = array();
            }

            $usersEmails[$row['user_id']][$row['email_id']][] = $row['id'];
        }
        unset($usersEmailsRaw);


        // Budeme prochazet zaznamy z ult a pokousime se k nim priradit postupne zaznam z users_emails
        // v poli $usersEmails vzdy odstranime zaznam, ktery jsme jiz priradili.
        $sel = new Zend_Db_Select($db);
        $sel->from('users_links_tokens', array('id', 'user_id', 'email_id'))
            ->where('users_emails_id is null')
            ->order(array('id'))
            ;

        $ult = $db->fetchAll($sel);
        // Pridame prazdny zaznam na konec kvuli cyklu
        array_push($ult, array('id' => null, 'email_id' => null, 'user_id' => null));


        // Pole obsahujici id zaznamuu z ult pro kazdy zaznam z users_emails
        $forWrite = array();
        // Pomocne pole pro id zaznamuu z users_links_tokens patrici k jednomu users_emails
        $ultIds = array();
        $lastRec = array('id' => null, 'email_id' => null, 'user_id' => null);
        foreach($ult as $rec){
            set_time_limit(20);

            // Zkousime rozlisit, kdy se bude jednat o dalsi zaznam z users_emails
            if($last['user_id'] == $rec['user_id'] && $last['email_id'] == $rec['email_id']
                    && ($last['id']+1 == $rec['id']
                        ||(!empty($linksInMails[$rec['email_id']])
                            && count($ultIds) < ceil(count($ultIds) / $linksInMails[$rec['email_id']]) * $linksInMails[$rec['email_id']]
               )))
            {
                $ultIds[] = $rec['id'];
                $last = $rec;
                continue;
            }


            // Najdeme odpovidajici zaznam z users_emails a provedeme vse potrebne
            if(!empty($usersEmails[$last['user_id']][$last['email_id']]))
            {
                reset($usersEmails[$last['user_id']][$last['email_id']]);
                $usersEmailsId = current($usersEmails[$last['user_id']][$last['email_id']]);


                /*
                if(in_array(5081, $ultIds)){
                    echo "bagr '$usersEmailsId' - ".join(' ', $ultIds)."</br>";
                }
                if($usersEmailsId == 5145){
                    echo "xxx '$usersEmailsId' - ".join(' ', $ultIds).", email ".$last['email_id'].", user ".$last['user_id']."</br>";
                }
                */


                // Overime, pokud neni pro dany zaznam z users_emails prilis mnoho zaznamu z ult
                // pokud ano, rozdelime na dva v pulce
                $cnt = count($ultIds);

                if(!empty($linksInMails[$last['email_id']]) && ((float)$cnt / $linksInMails[$last['email_id']]) > 1.5){
                    $multi = (float)$cnt / $linksInMails[$last['email_id']];
                    $ultIds = array_reverse($ultIds);
                    for($j = 0; $j < round($multi); $j++){
                        $tmpUltIds = array();
                        for($i = 0; $i < $cnt/$multi; $i++){
                            if(!empty($ultIds)){
                                $tmpUltIds[] = array_pop($ultIds);
                            }
                        }
                        // Pokud neco zbylo v poslednim kole, tak to pridame
                        if($j+1 == round($multi) && !empty($ultIds)){
                            foreach($ultIds as $x){
                                $tmpUltIds[] = $x;
                            }
                        }

                        if(!empty($tmpUltIds)){
                            echo "ults: ".join(', ', $tmpUltIds)."<br/>";
                            $forWrite[$usersEmailsId] = $tmpUltIds;
                            unset($usersEmails[$last['user_id']][$last['email_id']][key($usersEmails[$last['user_id']][$last['email_id']])]);

                            reset($usersEmails[$last['user_id']][$last['email_id']]);
                            $usersEmailsId = current($usersEmails[$last['user_id']][$last['email_id']]);
                        }

                    }
                    echo "too much - $cnt / ".$linksInMails[$last['email_id']]." <br/>";
                }
                else{
                    $forWrite[$usersEmailsId] = $ultIds;
                    unset($usersEmails[$last['user_id']][$last['email_id']][key($usersEmails[$last['user_id']][$last['email_id']])]);
                }

                /*
                if($usersEmailsId == 2521){
                    echo "eee '$usersEmailsId' - ".join(' ', $usersEmails[$last['user_id']][$last['email_id']])."</br>";
                }
                */
            }
            else{
                echo "Nelze najit ue_id pro ult_ids: ".join(', ', $ultIds)."<br/>";
            }

            // Vyprazdnime ultIds a zacneme tam davat zaznamy patrici k dalsimu zaznamu z users_emails
            $ultIds = array($rec['id']);

            $last = $rec;
        }

        echo "Starting write...<br/>";

        unset($ult);
        unset($usersEmailsRaw);

        // Soubor pro vystup SQL updatuu
        $fp = fopen('pub/others/users_links_tokens_update.sql', 'w');


        $db->beginTransaction();
        $count = 0;
        $count1 = 1;
        foreach($forWrite as $key => $a)
        {
            set_time_limit(20);

            $db->update('users_links_tokens', array('users_emails_id' => $key), "id IN (".join(',', $a).")");

            $sql = "update users_links_tokens set users_emails_id = $key where id IN (".join(',', $a).");\n";
            //fputs($fp, $sql);

            $count ++;
            /*
            if($count > 500){
                $db->commit();

                // Zavreme a znovu vyrobime pripojeni
                $db->closeConnection();

                $cfg_db = new Zend_Config_Ini('./config_db.ini', 'database');
                $db = Zend_Db::factory($cfg_db);
                Zend_Registry::set('db', $db);

                $db->beginTransaction();
                //$db->rollback();
                //break;
            }
            */
            if($count1 > 1000){
                echo "Count: $count<br/>";
                $count1 = 0;
            }
            $count1 ++;
        }

        fclose($fp);

        echo count($forWrite);

        try{
            $db->commit();
        }
        catch(Exception $e){
            // Nic...
        }
    }


    /**
     * Provede migraci dat ze stare jedine tabulky odpovedi do nove struktury.
     *
     * !!! NEFUNGUJE PRO INCASTY - problem s webcastem (id contentu videa, ktere uklada data je skryto ve webcastu)
     */
    public function migrateanswersAction()
    {
        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');


        // Overime, jestli v tabulce users_answers jiz neco neni, pokud je neprazdna, neprovadime
        $cnt = $db->fetchOne('SELECT count(*) FROM users_answers');
        if($cnt && !$this->_request->getParam('dowithnotempty')){
            echo "Tabulka users_answers jiz obsahuje nejaka data. Pokud skutecne chcete spustit ".
                "transformaci dat ze starych tabulek, pridejte na konec url /dowithnotempty/1/";
            return;
        }

        echo "Zapsane zaznamy:<br/>";



        $q = "SELECT a.content_id, a.question_id, a.user_id, a.type, a.answer, a.text, a.timestamp
              FROM (
                SELECT content_id, question_id, user_id, type, answer, text, current_timestamp AS timestamp
                    FROM answers_old_schema
                UNION ALL
                SELECT content_id, question_id, user_id, type, answer, text, timestamp
                    FROM answers_log_old_schema
                ) AS a
              ORDER BY a.content_id, a.user_id, a.question_id, a.timestamp asc, a.answer";
        $data = $db->fetchAll($q);

        // Pridame na konec jeste jeden zaznam, ktery uz se do tabulky nevypise, ale
        // umozni zpracovani skutecneho posledniho zaznamu
        $data = $data + array('xxx' => array('user_id' => 'xxx', 'content_id' => 'xxx'));

        $last_cid = null;
        $last_uid = null;
        $used_pv_ids = array();
        foreach($data as $key => $val){
            // Udrzovani objektu Questions podle aktualniho contentu a uzivatele
            if($last_uid != $val['user_id'] || $last_cid != $val['content_id']){
                $quest = new Questions($val['content_id'], $val['user_id']);

                $last_uid = $val['user_id'];
                $last_cid = $val['content_id'];
            }


            # Jednotlive druhy odpovedi - t, r, c
            // Text
            if($val['type'] == 't'){
                $quest->writeAnswer($val['question_id'], null, $val['type'], $val['text']);
            }
            // Radio
            elseif($val['type'] == 'r'){
                $quest->writeAnswer($val['question_id'], (int)$val['answer'], $val['type']);
            }
            // Checkbox
            elseif($val['type'] == 'c'){
                // Vice odpovedi ulozime do pole podle pozadavku metody pro zapis odpovedi v Questions
                $answerA = array_reverse(str_split(decbin((int)$val['answer'])));
                $answerAWrite = array();
                $i = 1;
                foreach($answerA as $a){
                    if((int)$a){
                        $answerAWrite[] = $i;
                    }
                    $i ++;
                }

                $quest->writeAnswer($val['question_id'], $answerAWrite, $val['type']);
            }

            //$ua_id = $db->lastInsertId('users_answers', 'id');
            $ua_id = $db->fetchOne('SELECT max(id) from users_answers WHERE user_id = '.(int)$val['user_id'].'');
            $updData = array();

            // Musime nastavit spravnejsi cas ke kazde preulozene otazce
            $updData['timestamp'] = $val['timestamp'];

            // Najdeme vhodne page_views_id pro pravdepodobne zobrazeni neceho co vyvolalo ulozeni teto otazky
            // funguje jen pro inBulletiny
            $timestampWhere = !empty($val['timestamp']) ? "AND pv.timestamp < '".$val['timestamp']."'" : '';
            $q  = "SELECT pv.*
                   FROM page_views pv
                   JOIN sessions s ON s.id = pv.session_id
                   JOIN content_pages cp ON pv.page_id = cp.page_id
                   WHERE cp.content_id = ".(int)$val['content_id']." AND s.user_id = ".(int)$val['user_id']."
                   $timestampWhere
                   ORDER BY pv.timestamp desc
                   ";

            $pvs = $db->fetchAll($q);

            // Prochazime vytipovane page views a podle refereru se pokousime identifikovat odeslani formulare
            foreach($pvs as $pv){
                $m = array();
                // Ziskame referer bez cisla stranky na konci
                preg_match('/^(.*)(\/){0,1}('.$config->general->url_name->sheet_number.'\/[0-9]+(\/){0,1}){0,1}$/', $pv['referer'], $m);
                $referer = $m[1];

                // Ziskame url bez cisla stranky na konci
                preg_match('/^(.*)(\/){0,1}('.$config->general->url_name->sheet_number.'\/[0-9]+(\/){0,1}){0,1}$/', $pv['url'], $m);
                $url = $m[1];

                if(preg_match('/.*'.str_replace('/','\/', $url).'(\/){0,1}$/', $pv['referer'])
                    && !in_array($pv['id'], $used_pv_ids[$val['question_id']]))
                {
                    /*
                    if($val['user_id']==45 && $val['content_id']==37){
                        echo $val['timestamp'].' | '.$pv['id'].' | '.$val['user_id'].' | '.$val['content_id'].' | '.$val['question_id'].' | '.$pv['timestamp'].' | '.$url.' | '.$referer.'<br/>';
                    }
                    //*/

                    $updData['timestamp'] = $pv['timestamp'];
                    $updData['page_views_id'] = $pv['id'];

                    $used_pv_ids[$val['question_id']][] = $pv['id'];
                    break;
                }
            }

            // Upravime data v users_answers
            $db->update('users_answers', $updData, 'id = '.(int)$ua_id);

            echo 'newTime:'.$pv['timestamp'].' | pvId:'.$pv['id'].' | user:'.$val['user_id'].' | content:'.$val['content_id'].
                ' | questionNum:'.$val['question_id'].' | nextTime:'.$val['timestamp'].'<br/>';

            // Resetujeme casovac, aby probehlo vse v jednom behu
            set_time_limit(30);
        }
    }

    /**
     * Provede opravu tabulky users_answers pro question_id 68, kde se v dusledku chyby v ukladani otazek
     * neukladalo cislo zadane do pole otypovaneho jako text - PSC. Question_num = 10
     */
    public function repairpalexiapscAction()
    {
        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        $db = Zend_Registry::get('db');

        $q = 'SELECT ua.id, ids.answer_original, ids.question_id_original from users_answers ua
            JOIN indetail_stats ids ON ids.id = ua.indetail_stats_id where question_id = 68';

        $rows = $db->fetchAll($q);

        foreach($rows as $row){
            $qNums = explode('|', $row['question_id_original']);
            $answers = explode('|', $row['answer_original']);
            // Najdeme ktera odpoved je 10
            $found = false;
            foreach($qNums as $key => $num){
                if(trim($num) == 10){
                    $found = true;
                    break;
                }
            }
            if(!$found){
                continue;
            }

            $answer = $answers[$key];

            echo 'id:'.$row['id'].' answer:'.$answer.' key:'.$key.' qnum:'.$row['question_id_original'].' answers:'.$row['answer_original']."<br/>\n";

            $db->update('users_answers', array('text' => $answer), 'id='.(int)$row['id']);
        }
    }


    /**
     * Akce pro otestování komunikace z iPadu do inBoxu
     */
    public function ipadtesterAction()
    {
        Ibulletin_Js::addJsFile('jquery.json-2.2.js');

        // Hledani testovacich odpovedi:
        /*
         select s.slide_num, q.question_num, ua.* from users_answers ua join questions q on q.id = ua.question_id JOIN slides s ON s.id = q.slide_id where ua.timestamp > '2011-06-04 10:00:16.849';
        */
    }


    /**
     * Nastaveni tidyOff pro vsechny existujici contenty. Potreba provest pri zavedeni TIDY
     * contentu, aby nedochazelo k problemum s existujicimi contenty.
     */
    public function allcontenttidyoffAction(){
        $db = Zend_Registry::get('db');

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        // Najdeme seznam ID contentuu
        $contents = Contents::getList();

        foreach($contents as $content){
            echo $content['id'].' | ';

            // Nastavime tidyOff
            $content['object']->tidyOff = true;

            // Ulozime
            Contents::edit($content['id'], $content['object']);
        }

    }
    
    /**
     * Defaultni nastaveni
     * var $htmlPresIndexFile = 'html5/index.html';
     * var $htmlInPadPresIndexFile = 'html5/ipad_package.zip';
     * var $flashConfigFile = 'flash/config.xml'; 
     * 
     * Pro contenty indetailu po update na novou verzi s HTML5.
     */
    public function allindetailsetdefaultattrsAction(){
        $db = Zend_Registry::get('db');

        // Nastavime render script
        $this->getHelper('viewRenderer')->setScriptAction('index');

        // Najdeme seznam ID contentuu
        $contents = Contents::getList('Ibulletin_Content_Indetail');

        foreach($contents as $content){
            echo $content['id'].' | ';

            // Nastavime noveatributy
            $content['object']->htmlPresIndexFile = 'html5/index.html';
            $content['object']->htmlInPadPresIndexFile = 'html5/ipad_package.zip';
            $content['object']->flashConfigFile = 'flash/config.xml';

            // Ulozime
            Contents::edit($content['id'], $content['object']);
        }

    }
    
    /**
     * Precte jmeno contentu ze serializovaneho objektu a ulozi jej do content.name
     */
     public function updatecontentnamesAction()
     {
         $db = Zend_Registry::get('db');
         
         // Nastavime render script
         $this->getHelper('viewRenderer')->setScriptAction('index');
         
         // Vezmeme seznam ID contentu
         $contents = Contents::getList();
         
         foreach($contents as $content){
            if(isset($content['object']->name)){
                $db->update('content', array('name' => $content['object']->name), 'id = '.(int)$content['id']);
            }
         }
     }
     
     /**
     * Preulozi vsechny maily
     */
     public function resaveemailsAction()
     {
         // Kopie metody z MailsController 
         function getMailSpecialType($mail)
        {
            // Specialni funkce mailu - registracni/deregistracni
            $special_type_val = 0;//'none';
            if(!empty($mail->send_after_registration)){
                $special_type_val = 'registration';
            }
            if(!empty($mail->send_after_deregistration)){
                $special_type_val = 'deregistration';
            }
            if(!empty($mail->special_function)){
                $special_type_val = $mail->special_function;
            }
    
            return $special_type_val;
        }
         
         $db = Zend_Registry::get('db');
         $config =  Zend_Registry::get('config');
         
         // Nastavime render script
         $this->getHelper('viewRenderer')->setScriptAction('index');
         
         // Objekt pro praci s maily
         $mails = new Ibulletin_Mails($config, $db);
         
         // Ziskame seznam mailu
         $sel = new Zend_Db_Select($db);
         $sel->from('emails');
         $emails = $db->fetchAll($sel);
         
         // Kazdy mail znovu ulozime
         foreach($emails as $email){
            $mail = $mails->getMailData($email['id']); // Zend_Db_Table_Row
            
            if (empty($mail)) continue;
                        
             
            $errors = $mails->saveMail(
                (int)$mail->id, 
                (string)$mail->name, 
                (int)$mail->invitation_id, 
                (string)$mail->subject, 
                (string)$mail->body->getBody(),
                (string)$mail->body->getPlain(), 
                getMailSpecialType($mail)
            );
            
            echo 'Mail: '.$mail->id.'<br/>';
            print_r($errors);
            echo '<br/>';
         }
     }
     
     /**
      * Presune odpovedi na otazky z jednoho slide na jiny s dodrzenim question_num.
      * 
      */
     public function movequestionstoslideAction()
     {
         $db = Zend_Registry::get('db');
         
         // Nastavime render script
         $this->getHelper('viewRenderer')->setScriptAction('index');
          
         $contentId = 14;
         $slideNumTo = 1;
         
         
         // Zaznamy, ktere se maji presunout na jiny slide (zmenit question_id)
         $sel = "
            SELECT ua.*, a.answer_num, q.question_num FROM users_answers ua
            JOIN questions q ON q.id = ua.question_id
            JOIN slides s ON s.id = q.slide_id
            LEFT JOIN answers a ON a.id = answer_id 
            WHERE slide_num > 1 -- chceme presunout odpovedi ze vsech slajdu do slajdu 1
                AND q.content_id = 14
            ORDER BY ua.timestamp
            ;
            ";
         $uaToMove = $db->fetchAll($sel);
         
         $sel = "
                 SELECT distinct q1.id as from , q2.id as to  /*, q2.question_num, q1.question_num*/
                 FROM questions q1 
                 JOIN questions q2 ON q1.question_num = q2.question_num
                 JOIN slides s1 ON s1.id = q1.slide_id JOIN slides s2 ON s2.id = q2.slide_id
                 WHERE q1.content_id =14 and q2.content_id = 14 
                    and s1.slide_num> 1 and s2.slide_num=1
                 ";
         $translate = $db->fetchAssoc($sel);
         
         foreach($uaToMove as $uaRow){
             $questionTo = $translate[$uaRow['question_id']]['to'];
             
             echo "New quest ID: ".$questionTo." -- ";
             echo join($uaRow, ' | ');
             echo '<br/>';
             
             //$answer = $uaRow['answer_num'] ?: $uaRow['answer_int'] ?: $uaRow['answer_double'] ?: $uaRow['answer_bool'] ?: $uaRow['text'];
             if($uaRow['answer_num'] !== null){
                 $answer = $uaRow['answer_num'];
             }
             elseif($uaRow['answer_int'] !== null){
                 $answer = $uaRow['answer_int'];
             }
             elseif($uaRow['answer_double'] !== null){
                 $answer = $uaRow['answer_double'];
             }
             elseif($uaRow['answer_bool'] !== null){
                 $answer = $uaRow['answer_bool'];
             }
             
             echo 'Answer: ';var_dump($answer);
             echo '<br/>';
             
             // Zapsat odpovedi jako na jinem slide
             $questions = new Questions($contentId, $uaRow['user_id']);
             echo "writeAnswer PARAMS: ".join(array($uaRow['question_num'], $answer, $uaRow['type'], $uaRow['text'], 
                     $uaRow['indetail_stats_id'], $slideNumTo, null, null, $uaRow['page_views_id'], false, $uaRow['timestamp']), ' # ').'<br/>';
             $newUaId = $questions->writeAnswer($uaRow['question_num'], $answer, $uaRow['type'], $uaRow['text'], 
                     $uaRow['indetail_stats_id'], $slideNumTo, null, null, $uaRow['page_views_id'], false, $uaRow['timestamp']);
             
             echo 'Written uaId: '.$newUaId.'<br/>';
             
             // Smazat radek, ktery byl zapsan
             if($newUaId){
                $db->delete('users_answers', 'id='.$uaRow['id']);
                echo 'Deleted uaId: '.$uaRow['id'].'<br/>';
             }
         }   
     }
}
