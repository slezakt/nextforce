<?php
/**
 * iBulletin - Abstract.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

/**
 * Obecna vyjimka pro contenty.
 */
class Ibulletin_Content_Exception extends Exception {}

/**
 * Abstraktní trida pro objekty poskytujici jedotlive druhy obsahu v iBulletinu
 * Implementace zprostredkovavaji napriklad zobrazovani clanku, dotaznikuu, kalendaru akci
 * a podobne na strankach iBulletinu.
 * 
 * TODO: nastavit promenne aspon na protected a udelat gettery/settery a refactor. aspon pro id!
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
abstract class Ibulletin_Content_Abstract
{
    /**
     * @var int ID contentu
     */
    public $id = null;

    /**
     * @var int Cislo aktualniho listu
     */
    public $sheet_number = 1;

    /**
     * @var int Pozice contentu ve strance
     */
    public $content_position = 1;

    /**
     * @var string Nazev contentu
     */
    public $name = "";

    /**
     * @var Datum vytvoreni contentu?
     */
    public $created = null;
    
    /**
     *
     * @var Labely
     */
    public $labels = "";

    /**
     * @var string Anotace contentu
     */
    public $annotation = "";

    /**
     * @var int     ID page, ve ktere je prave renderovano, je nastaveno jeste pred vykonanim
     *              jakekoli jine metody pri renderu. Nikdy by nemelo ve tride zustat ulozene v DB.
     */
    public $pageId = null;

    /**
     * @var int     ID bulletinu, ve kterem je prave renderovano, je nastaveno jeste pred vykonanim
     *              jakekoli jine metody pri renderu. Nikdy by nemelo ve tride zustat ulozene v DB.
     *              !! Je mozne, ze nekdy neni k dispozici.
     */
    public $bulletinId = null;

    /**
     * @var string Pole obsahujici jednotlive stranky tela contentu
     */
    public $html = array('');

    /**
     *  @var bool   Je zakazano na HTML tohoto contentu provadet TIDY? (true = TIDY zakazano)
     */
    public $tidyOff = false;

    /**
     * @var bool Maji se vsechny stranky vyrenderovat do jedne?
     *           Pouzivame treba pri renderu do PDF
     */
    public $allSheetsInOne = false;

    /**
     * @var bool Je povoleno renderovani do PDF u tohoto contentu? Pokud neni, obvykle vracime
     *           URL na PDF null
     */
    public $allowPdf = false;

    /**
     * Pouzivame pouze kvuli indexovani - obsahuje jmeno autora
     *
     * @var string
     */
    private $_author = null;


    /**
     * @var string Jmeno template pro dany content - obsahuje %d pro doplneni cisla template,
     *             nekdy je vyplnovat programem pokud je mozne vybirat z vice template
     *             pro dany content.
     */
    public $tpl_name_schema = "";

    /**
     * @var string  Zaklad jmena templatu pro dany content, jedna se o cast nayvu od zacatku jmena
     *              souboru, pouzivame pro nalezeni dostupnych template do vyberu.
     */
    public $tpl_name_base = "";

    /**
     * @var array(int) Seznam id contentu, ktere se maji nacist a ma byt spustena jejich metoda
     *                 dependencyChanged($this) pri ulozeni tohoto contentu.
     */
    public $dependants = array();

    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
     *
     * @param Zend_Controller_Request_Abstract aktualni request objekt
     */
    public function prepare($req){
        $this->prepareSheetNumber();
    }

    /**
     * Metoda ulozi do objektu informaci o tom, pro jake umisteni v hlavnim template
     * ma byt pozadovany obsah generovan.
     *
     * @param int Cislo reprezentujici pozici v hlavnim templatu stranky
     *            (content_pages - position)
     */
    public function setPosition($position = 1)
    {
        $this->content_position = $position;
    }


    /**
     * Vrati jmeno souboru s template pro render daneho contentu k pouziti
     * ve funkci Zend_Controller_Action_Helper_ViewRenderer::renderScript('')
     * s ohledem na nastavenou pozici v hlavnim contentu
     */
    public function getTplFileName()
    {
        return sprintf($this->tpl_name_schema, $this->content_position);
    }


    /**
     * Vrati titulek pro stranku do popisu okna prohlizece
     */
    public function getTitle()
    {
        $texts = Ibulletin_Texts::getSet('content.article');
        $title = $this->name;

        // Pokud je clanek na vice stran, vypiseme cislo aktualni stranky do titulku
        if(count($this->html)>1){
            $title = $title." ".$texts->sheettitletext." ".$this->sheet_number;
        }

        return $title;
    }


    /**
     * Vrati nazev tohoto clanku
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Vrati labely clanku
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * Vrati autora.
     *
     * @return string   Jmeno autora clanku.
     */
    public function getAuthor(){
        return $this->_author;
    }

    /**
     * Vrati cislo aktualniho listu.
     *
     * @return int Cislo aktualniho listu.
     */
    public function getSheetNumber(){
        return $this->sheet_number;
    }

    /**
     * Pouzivame pro indexovani, vkladame jmeno autora.
     *
     * @param string    Jmeno autora
     */
    public function setAuthor($author){
        $this->_author = $author;
    }

    /**
     * Nastavi Page ID aktualniho renderovani objektu.
     *
     * @param string    Page ID pri aktualnim renderu
     */
    public function setPageId($pageId){
        $this->pageId = $pageId;
    }

    /**
     * Vrati dalsi data, ktera pouziva objekt ve view
     *
     * @return array/stdClass    Dalsi data, ktera pouziva obsah pro sve zobrazeni ve view
     */
    public function getData(){
        $data = new stdClass();

        // Adresa pro ziskani PDF z tohoto contentu.
        $data->pdfUrl = $this->getPdfUrl();

        return $data;
    }


    /**
     * Nastavi $this->html a provede potrebne akce jako TIDY.
     * Pred spustenim setHtml by mel byt pozadovanym zpusobem nastaven atribut $this->tidyOff.
     *
     * Pokud neni zadano ID listu a je zadan string, je HTML nahrazeno pouze jednim (prave vlozenym) listem.
     *
     * @param array/string  $html   HTML contentu, muze byt string pro jednolistovy content,
     *                              nebo array pro vicelistovy
     * @param int   $sheetId        ID listu (listy pocitame od 0)
     * @return string/array         Upravena vstupni promenna tak jak byla ulozena (napr. kvuli TIDY)
     *                              Pokud je vstupem string, vraci string, pro vstup array vraci array
     */
    public function setHtml($html, $sheetId = null)
    {
        if(!is_array($html)){
            // Provedeme TIDY pokud je povoleno
            if(!$this->tidyOff){
                $html = $this->tidyHtml($html);
            }

            if($sheetId === null){
                $this->html = array($html);
            }
            else{
                $this->html[$sheetId] = $html;
            }
        }
        else{
            // Provedeme TIDY pokud je povoleno
            if(!$this->tidyOff){
                foreach($html as $key => $sheet){
                    $html[$key] = $this->tidyHtml($sheet);
                }
            }
            $this->html = $html;
        }

        return $html;
    }

    /**
     * Vrati $this->html.
     *
     * @return array $html
     */
    public function getHtml()
    {
        return $this->html;
    }

    /**
     * Nastavi do $this->sheet_number cislo listu ke zobrazeni.
     *
     * @return string    Cislo listu pro tento content
     */
    public function prepareSheetNumber(){
        // Pokud vypisujeme vse do jednoho listu, nastavime cislo listu na 1
        if($this->allSheetsInOne){
            $this->sheet_number = 1;
            return;
        }

        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();
        $config = Zend_Registry::get('config');

        $param_name = $this->getSheetNumberUrlName();

        // Ziskame z URL cislo aktualniho listu na strance
        $sheet_number_get = $request->getParam($param_name);
        if(is_numeric($sheet_number_get)){
            $this->sheet_number = $sheet_number_get;
        }

        return;
    }

    /**
     * Vrati jmeno url parametru pro strankovani tohoto contentu.
     *
     * Content na pozici 1 ma parametr pro sheet bez cisla a dalsi contenty
     * maji jiz nazev parametru pro sheet number z configu rozsiren o cislo
     * pozice daneho contentu.
     *
     * Nesmi byt volano drive, nez je nastaveno setPosition()!
     *
     * @return string   Jmeno URL parametru s cislem listu pro tento content
     */
    public function getSheetNumberUrlName(){
        $config = Zend_Registry::get('config');

        $name = $config->general->url_name->sheet_number;
        if($this->content_position > 1){
            $name = $name.$this->content_position;
        }

        return $name;
    }

    /**
     * Vrati anotaci teto stranky - pouziva se v nekterych vypisech stranek
     */
    public function getAnnotation()
    {
        $path = $this->getPathToFile(''); // Cestu ke statickym souborum ziskame jako cestu k souboru s prazdnym jmenem
        $annotation = Ibulletin_Marks::translate($this->annotation, $path, $this);
        return $annotation;
    }


    /**
     * Vrati renderovaci data contentu s ohledem na nastavenou pozici v hlavnim contentu
     */
    abstract public function getContent();


    /**
     * Vrati objekt search dokumentu pro zaindexovani tohoto contentu v search.
     *
     * @return Zend_Search_Lucene_Document Search document pro zaindexovani contentu
     */
    public function getSearchDocument()
    {
    	Zend_Loader::loadClass('Zend_Search_Lucene_Document_Html');
    	Zend_Loader::loadClass('Zend_Search_Lucene_Field');
		
    	if (!empty($this->html)) {
    		$content = is_array($this->html) ? implode(' ',$this->html) : $this->html;
    		$content = Utils::translit($content);
    		$body = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>'.$content.'</body></html>';
    		$doc = Zend_Search_Lucene_Document_Html::loadHTML($body);
    	} else {
    		$doc = new Zend_Search_Lucene_Document();
    	}

    	$enc = 'UTF-8';
    	$doc->addField(Zend_Search_Lucene_Field::UnStored('tr_annotation', Utils::translit($this->getAnnotation()), $enc));
    	$doc->addField(Zend_Search_Lucene_Field::UnStored('tr_author', Utils::translit($this->getAuthor()), $enc));
    	$doc->addField(Zend_Search_Lucene_Field::UnStored('tr_title', Utils::translit($this->getName()), $enc));
        $doc->addField(Zend_Search_Lucene_Field::UnStored('tr_labels', Utils::translit($this->getLabels()), $enc));
    	$doc->addField(Zend_Search_Lucene_Field::UnIndexed('annotation', $this->getAnnotation(), $enc));
    	$doc->addField(Zend_Search_Lucene_Field::UnIndexed('author', $this->getAuthor(), $enc));
    	$doc->addField(Zend_Search_Lucene_Field::UnIndexed('title', $this->getName(), $enc));
        $doc->addField(Zend_Search_Lucene_Field::UnIndexed('labels', $this->getLabels(), $enc));
    	$doc->addField(Zend_Search_Lucene_Field::Keyword('content_id', $this->id, $enc));
    
    	return $doc;
    }

    /**
     * Vrati HTML vyrenderovaneho obsahu. Pouziva stejny proces jako pri renderovani
     * primo na stranku.
     *
     * @param $contentPosition int
     * @param $pageId int
     * @param $pageTemplate string  Page template pro renderovani nad content template.
     */
    public function getRenderedHtml($contentPosition, $pageId = null, $pageTemplate = null)
    {
        // Pozici contentu musime prenaset mimo, protoze v tomto requestu ji netusime
        //$contentPosition = $req->getParam('contentPosition', null);

        /*
        if(!$contentPosition){
            throw new Ibulletin_Content_Exception('Nebyl zadan parametr contentPosition.');
        }
        */

        // Pripravime renderovaci data pro vypsani tohoto contentu
        Zend_Loader::loadFile('BulletinController.php', 'controllers', true);
        $newReqest = new Zend_Controller_Request_Simple();
        $newResponse = new Zend_Controller_Response_Http();
        $bulletinController = new BulletinController($newReqest, $newResponse);
        $bulletinController->renderContentHtml($contentPosition, $this, $pageId, $pageTemplate);
        $body = $newResponse->getBody();

        // HACK Protoze nenavidime FIREFOX, musime odstranit SCRIPT tagy, aby se nemohl vyloupnout
        // pri vkladani HTML pomoci JS problem ve FireFoxu
        function strip_script($string) {
            // Prevent inline scripting
            $string = preg_replace("/(<script[^>]*>)(.*)?(<\/script[^>]*>)/isU", "", $string);
            // Prevent linking to source files
            $string = preg_replace("/<script[^>]*>/i", "", $string);
            return $string;
        }
        $body = strip_script($body);

        return $body;
    }

    /**
     * Vypise na vystup PDF prave otevreneho contentu.
     * Jako parametr musi byt predano v pozadavku contentPosition s pozici contentu
     * a volitelne pageId, pokud jej z nejakeho duvodu objekt contentu potrebuje k renderovani.
     *
     * Pouziva BOX footer jako paticku stranky, pokud tento existuje.
     *
     * Pokud je v URL parametr 'debug' nastaven na pozitivni hodnotu, vypise jen HTML pro ladeni css
     */
    public function renderpdfService()
    {
        require_once('library/mpdf/mpdf.php');

        $config = Zend_Registry::get('config');
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $frontController = Zend_Controller_Front::getInstance();
        $req = $frontController->getRequest();

        $contentPosition = $req->getParam('contentPosition', 1);
        $pageId = $req->getParam('pageId', null);

        // Nachystame jmeno souboru do http hlavicky ve spravnem kodovani a delce
        $filename = substr($this->name, 0, 200);
        $filename = preg_replace('~[^\\pL0-9_]+~u', '-', $filename);
        /*
        $filename = trim($filename, "-");
        $filename = iconv("utf-8", "us-ascii//TRANSLIT", $filename);
        */
        if(preg_match('/MSIE/i', $req->getServer('HTTP_USER_AGENT'))){
            $filename = urlencode($filename);

            // Zkusime, jestli to neni IE6
            if(preg_match('/MSIE 6/i', $req->getServer('HTTP_USER_AGENT'))){
                $isIE6 = true;
            }
        }
        $filename .= '.pdf';


        // Nacteme stylesheet
        $css = '';
        if(empty($config->useOnlyPdfCss)){
            $files = $config->htmlhead->file;
            foreach($files as $file){
                if(preg_match('/\.css$/i', $file)){
                    $css .= file_get_contents(Skins::getActualSkinPath().'/css/'.$file)."\n";
                }
            }
        }

        // Nakonec jeste specialni stylesheet pro PDF
        $css .= join("\n", file('pub/css/'.$config->htmlhead->pdfCss))."\n";

        // Nastavime, ze renderujeme vsechny sheety do jednoho
        $this->allSheetsInOne = true;
        // Ziskame html contentu
        $html = $this->getRenderedHtml($contentPosition, $pageId, 'pdf_simple_content.php');

 		//convert absolute path to relative before rendering PDF
		$urlHlpr = new Zend_View_Helper_Url();
	    $baseUrl = $urlHlpr->url(array(),'default',true);
		$html = preg_replace('/src="'.preg_quote($baseUrl,'/').'(.*)"/','src="${1}"', $html);
 
        // POUZIVAME K LADENI CSS
        // Pridame kolem tele HTML zbytek kodu
        $htmla = "
           <html>
           <head>
               <style type='text/css'>
               $css
               </style>
           </head>

           $html

           </html>
           ";

        // Provedeme zacisteni HTML
        // Servery PHC neumeji
        /*
        $tidy = new tidy();
        $tidyconfig = array(
           'indent'         => true,
           'output-xhtml'   => true,
           'wrap'           => 200);
        $html = $tidy->repairString($html, $tidyconfig, 'utf8');
        */


        // LADENI, pokud je nastaven v URL debug, jen vypiseme html
        if($req->getParam('debug')){
            echo $htmla;
            exit;
        }



        // mPDF
        $mpdf = new mPDF('utf-8', 'A4');
        $mpdf->SetBasePath('./');
        $mpdf->WriteHTML($css, 1);   // The parameter 1 tells that this is css/style only and no body/html/text
        $mpdf->WriteHTML($html, 2);
        // Problem je, ze se footer neda formatovat pomoci css dokumentu, pokud je na kazde strane,
        // proto je tu jen na posledni strance
        // DAVAME PRIMO DO HTML
        // $mpdf->SetHTMLFooter($footer);

        // Pokud je IE6 nechame vystup inline
        if(!empty($isIE6)){
            $mpdf->Output($filename, 'I');
        }
        else{
            $mpdf->Output($filename, 'D');
        }




        /*
        $dompdf = new DOMPDF();
        $dompdf->set_base_path($urlHlpr->url(array(), 'default', true));

        $dompdf->set_paper('A4', 'portrait');
        //$dompdf->;
        $dompdf->load_html($html);
        $dompdf->render();
        $dompdf->stream('file.pdf');
         */
        /*
        // Protoze je cosi shnileho v kralovstvi Danskem, musime to spustit skrz shell
        $tmpfile = tempnam(null, 'dompdf');
        $fp = fopen($tmpfile, "w");
        // Ulozime html do souboru
        fputs($fp, $html);
        fclose($fp);

        // Soubor pro vystupni pdf
        $tmpfilepdf = tempnam(null, 'dompdfout');

        $retval = null;
        $output = array();
        exec("php library/dompdf/dompdf.php -p 'A4' -b './' -f $tmpfilepdf $tmpfile", $output, $retval);

        unlink($tmpfile);

        if($retval !== 0 || !file_exists($tmpfilepdf)){
           Phc_ErrorLog::error('Ibulletin_Content_Abstract::renderpdfService()',
               "Nepodarilo se vygenerovat PDF. Content: $this->id, pageId: $pageId ");
        }
        else{
            // Hlavicky
            header("Cache-Control: private");
            header("Content-type: application/pdf");
            header("Content-Disposition: Attachment; filename=\"$this->name.pdf\"");

            $pdf = fopen($tmpfilepdf, 'r');
            while(!feof($pdf)){
                # output file without bandwidth limiting
                print(fread($pdf, 7000));
                flush();
            }
            fclose($pdf);
            unlink($tmpfilepdf);
        }
        */

        exit;
    }

    /**
     * Vrati pole url nazvu na jednotlive listy
     *
     * @return array    Pole priznaku listu vhodne pro pouziti v URL
     */
    public function getSheetKeys()
    {
        // Pokud renderujeme vse na jeden list, vracime jen jeden
        if($this->allSheetsInOne){
            $sheets = array(1);
            return $sheets;
        }

        if(is_array($this->html)){
            $sheets = array_keys($this->html);
            foreach($sheets as $key => $sheet_key){
                $sheets[$key] = $sheet_key + 1;
            }
        }
        else{
            return array();
        }

        return $sheets;
    }

    /**
     * Vrati URL nebo cestu na serveru k souboru v contentu.
     *
     * @param string $file  Jmeno souboru v adresari contentu
     * @param bool $getUrl  Vratit URL? Pokud je false, vrati cestu na serveru. DEFAULT: true
     * @return string       URL nebo cesta na serveru k souboru v contentu.
     */
    public function getPathToFile($file, $getUrl = true)
    {
        $config = Zend_Registry::get('config');
        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        if($getUrl){
            $base = $urlHlpr->url(array(), 'default', true).'/';
        }
        else{
            $base = '';
        }
        $path = $base.$config->content_article->basepath.'/'.$this->id.'/'.$file;
        // Normalizujeme od zdvojenych lomitek //
        $path = Zend_Filter::filterStatic($path, 'NormPath');

        return $path;
    }

    /**
     * Vrati URL nebo cestu na serveru k souboru v contentu.
     * Pokud neni povoleno renderovani PDF $this->allowPdf, vraci null.
     *
     * @return string       URL pro ziskani PDF s timto contentem
     */
    public function getPdfUrl()
    {
        if(!$this->allowPdf){
            return null;
        }

        $urlHlpr = Zend_Controller_Action_HelperBroker::getStaticHelper('url');
        $path = $urlHlpr->url(array('contentid' => $this->id,
                                    'srvname' => 'renderpdf',
                                    'contentPosition' => $this->content_position,
                                    'pageId' => $this->pageId
                                    ), 'service', true).'/';
        //echo $path;
        return $path;
    }

    /**
     * Pridat content na seznam volanych contentuu pri zmene tohoto contentu.
     *
     * @param int $id   ID contentu, ktery bude odebirat zmeny v tomto contentu.
     */
    public function subscribeForChanges($id)
    {
        if(!in_array($id, $this->dependants)){
            $this->dependants[] = (int)$id;
        }
    }

    /**
     * Odebrat content ze seznamu volanych contentuu pri zmene tohoto contentu.
     *
     * @param int $id   ID contentu, ktery bude odebran z odberu zmen v tomto contentu.
     */
    public function unsubscribeForChanges($id)
    {
        foreach($this->dependants as $key => $dep){
            if($dep == $id){
                unset($this->dependants[$key]);
                break;
            }
        }
    }

    /**
     * Metoda, ktera je volana contentem, na kterem tento content zavisi ve chvili, kdy je content
     * zmenen.
     *
     * @param Ibulletin_Content_Abstract $dep   Trida, ktera se zmenila.
     */
    public function dependencyChanged($dep)
    {
        return;
    }

    /**
     *  Metoda, ktera by mela byt zavolana po provedeni ulozeni. Zavola zavisle contenty
     *  a necha je provest zmeny podle zmen v teto tride.
     */
    public function afterSave(){
        foreach($this->dependants as $depId){
            $dep = Contents::get($depId);

            if($dep){
                $dep['object']->dependencyChanged($this);
                Contents::edit($depId, $dep['object']);
            }
        }
    }

    /**
     * Najde seznam souboruu templatuu, ktere jsou dostupne pro tento content
     */
    public function getAvailableTemplates()
    {
        $config = Zend_Registry::get('config');

        $path = $config->paths->content_templates;

        $files = array();
        if(file_exists($path)){
            $handle = opendir($path);
            while(($file = readdir($handle)) !== false){
                // Najdeme soubory odpovidajici $this->tpl_name_base
                if($file != '.' && $file != '..' && preg_match('/^'.$this->tpl_name_base.'/i', $file)){
                    array_push($files, array('name' => $file,
                        'path' => Zend_Filter::filterStatic($path.'/'.$file, 'NormPath')
                        ));
                }
            }
        }


        // Ziskame schema pro pozadovany template a pripravime do pole tak, aby se hodilo pro vyber selectem
        $schemas = array();
        foreach($files as $file){
            $matches = array();
            if(preg_match('/(.*)_[0-9]+\.phtml/i', $file['name'], $matches)){
                $schemas[$matches[1].'_%d.phtml'] = $matches[1];
            }
        }

        return $schemas;
    }


    /**
     * Provede zacisteni HTML a vrati zpet odpovidajici blok HTML s opravenymi chybami.
     * Nedodrzuje puvodni formatovani HTML kodu, v nekterych pripadech muze pokazit vlozene PHP,
     * protoze PHP je pred provedenim TIDY odstraneno z HTML a nasledne vkladano.
     * HTML na miste, kde nemuze v HTML byt prosty retezec znaku bude ztraceno.
     *
     * Jedna se o wrapper na Ibulletin_Marks::tidyHtml().
     * Pozor, tuto metodu mohou pouzivat primo z objektu contentu nektere adminy contentu.
     *
     * @param string    HTML, ktere ma byt opraveno
     * @return string   Zacistene (TIDY) HTML odpovidajici vstupnim datum.
     */
    public function tidyHtml($html)
    {
        $marks = new Ibulletin_Marks();

        return $marks->tidyHtml($html);
    }


}
