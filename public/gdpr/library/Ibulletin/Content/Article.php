<?php

/**
 * iBulletin - Article.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

Zend_Loader::loadClass('Ibulletin_Content_Abstract');

/**
 * Trida zprostredkovavajici zobrazeni hlavniho obsahu na strankach iBulletinu - 
 * Clanek
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Content_Article extends Ibulletin_Content_Abstract
{
    /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "article_%d.phtml";
    
    /**
     * @var int Cislo dotazniku
     */
    var $questionnaire_num = null;
    
    /**
     * @var bool Je povoleno renderovani do PDF u tohoto contentu? Pokud neni, obvykle vracime
     *           URL na PDF null
     */
    var $allowPdf = true;
    
    /**
     * Je spustena pred volanim kterekoli dalsi metody teto tridy pri zobrazovani
     * umoznuje reagovat na vstupy uzivatele a data jim odeslana a podle nich
     * prizpusobit vystup.
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
        
        //kontrola osamocených písmen na koncích řádků, pouze pro Ibulletin_Content_Article, pro jazyk dle configu
        $js = Ibulletin_Js::getInstance();
        $c_lang = Ibulletin_Texts::getLangs();
        $nbsp_langs = explode(',', $config->content_article->addNbsp->languages);
        if (in_array($c_lang[0], $nbsp_langs)) {
            $js->addJsFile('nbsp.js');
        }
    }

    /**
     * Vrati renderovaci data contentu s ohledem na nastavenou pozici v hlavnim contentu
     */
    public function getContent()
    {
        // Html obsahu podle toho, jestli vsechno v jednom, nebo po strankach
        if($this->allSheetsInOne){
            $html = join(" <div class='reset'></div>\n", $this->html);
        }
        else{
            $html = $this->html[$this->sheet_number-1];
        }
        
        // Prelozime znacky
        $config = Zend_Registry::get('config');
        $urlHlpr = new Zend_View_Helper_Url();
		$baseUrl = $urlHlpr->url(array(),'default',true);
		$path = $baseUrl . $config->content_article->basepath.$this->id.'/';
        $html = Ibulletin_Marks::translate($html, $path, $this);
        //$html = $this->translateMarks($this->html[$this->sheet_number-1]);
        
        // Html se bude prohanet eval(), takze na zacatek pridame koncovy tag php
        return array('article_html' => '?> '.$html);
    }
    
    
    /**
     * Varati vstupni retezec s nahrazenymi znackami za spravny kod
     * Aktualne prklada znacky:
     * %%static%% - nahradi za cestu do adresare statickeho obsahu daneho contentu
     *
     * DEPRECATED - nadale se nepouziva, pouzivame Ibulletin_Marks::translate();
     *
     * @param string vstupni retezec
     * @return string retezec s prelozenzmi znackami
     */
    public function translateMarks($string)
    {
        $config = Zend_Registry::get('config');
        
        // Nahradime cestu do statickych dat
        $path = $config->content_article->basepath.'/'.$this->id.'/';
        $token = '%%static%%';
        $out = str_ireplace($token, $path, $string);
        
        return $out;
    }

}
