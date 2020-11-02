<?php
/**
 * Trida reprezentujici XML data, ktera je mozne kdykoli prevest na XML dokumnet.
 * 
 * Kazdy atribut tridy, ktery nezacina '__' je pridan do XML.
 * Atributy elementu mohou byt ulozeny jako Ibulletin_XmlElementAttributes v atributu stejneho jmena s '__' pred 
 * a '_attr' za - '__jmenoElementu_attr'.
 *
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_XmlClass
{
    /**
     * @var string Nazev root tagu/elementu, pokud ma existovat, jinak null.
     */
    var $__rootName = null;
    
    /**
     * Vrati DOMDocument objekt.
     * 
     * @return DOMDocument Dokument DOM reprezentujici data v objektu.
     */
    public function getDom()
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        
        if($this->__rootName){
            $root = $doc->createElement($this->__rootName);
            $doc->appendChild($root);
        }
        else{
            // Nema byt zadny root tag, pouzijeme dokument jako root.
            $root = $doc;
        }
        
        // Prochazime atributy tridy a vytvarime DOM
        foreach(get_object_vars($this) as $key => $val){
            // Pokud je klic ve formatu '__nazev_cislo', budou mit vsechny takove prvky
            // tag jen s nazvem 'nazev'
            $matches = array();
            if(preg_match('/^__(.*)_[0-9]+$/i', $key, $matches)){
                $key = $matches[1];
            }
            // Preskocime atributy zacinajici '__'
            elseif(substr($key,0,2) == '__'){
                continue;
            }
            // Preskocime atributy nastavene na null
            if($val === null && (empty($this->{'__'.$key.'_attr'}) 
                || !($this->{'__'.$key.'_attr'} instanceof Ibulletin_XmlElementAttributes)))
            {
                continue;
            }
            
            
            
            if($val instanceof Ibulletin_XmlClass){
                // Ziskame XML elem dat a adoptujeme jej
                $elemForeign = $val->getDom();
                $nodes = $elemForeign->childNodes;
                
                for($i=0; $i<$nodes->length; $i++){
                    $elem = $doc->importNode($nodes->item($i), true);
                    $root->appendChild($elem);
                }
            }
            elseif(is_array($val) || $val instanceof StdClass){
                // provedeme zpracovani pole ci StdClass
                $elem = $doc->createElement($key);
                $this->_getDomFromArrayOrStdClass($val, $doc, $elem);
                $root->appendChild($elem);
            }
            elseif(preg_match('/CDATA$/',$key)){
                // CDATA
                $cdata = $doc->createCDATASection($val);
                $elem = $doc->createElement($key);
                $elem->appendChild($cdata);
                $root->appendChild($elem);
                
            }
            else{
                // Jedna se o beznou hodnotu, nechame jak je
                $elem = $doc->createElement($key, $val);
                $root->appendChild($elem);
            }
            
            // Pridame atributy elementu, pokud nejake existuji
            // POZOR - Pro element typu Ibulletin_XmlClass muze pridat atributy jen 
            // k poslednimu importovanemu elementu.
            if(!empty($this->{'__'.$key.'_attr'}) && 
                $this->{'__'.$key.'_attr'} instanceof Ibulletin_XmlElementAttributes)
            {
                $attribs = $this->{'__'.$key.'_attr'};
                // Postupne pridavame atributy
                foreach(get_object_vars($attribs) as $key1 => $val1){
                    $elem->setAttribute($key1, $val1);
                }
            }
            
        }
        
        
        return $doc;
    }
    
    /**
     * Vrati XML data bez hlavicky XML.
     * 
     * @return string XML data bez hlavicky - pouze telo.
     */
    public function getXmlData()
    {
        $doc= $this->getDom();
        $out = '';
        
        if($doc->hasChildNodes()){
            $nodes = $doc->childNodes;
            for($i=0; $i<$nodes->length; $i++){
                $node = $nodes->item($i);
                $out .= $doc->saveXML($node);
            }
        }
        return $out;
    }
    
    /**
     * Vrati XML data se vsim vsudy
     * 
     * @param string $file  Cesta k souboru, kam se ma konfigurace ulozit. Volitelne.
     * @return string XML soubor se vsim vsudy.
     */
    public function getXml($file = null)
    {
        $doc= $this->getDom();
        $xml = $doc->saveXML();
        
        if(!empty($file)){
            $fp = fopen($file, "w");
            fputs($fp, $xml);
            fclose($fp);
            
            Utils::chmod($file, Utils::FILE_PERMISSION);
        }
        
        return $xml;
    }
    
    /**
     * Vytvori DOMElement z pole ci StdClass tak, ze klice pole jsou tagy a data jsou data.
     * Pokud je klic ve formatu '__nazev_cislo', budou mit vsechny takove prvky tag 
     * jen s nazvem 'nazev'.
     * 
     * @param array/StdClass $var Pole nebo StcClass pro zpracovani
     * @param DOMDocument $doc    Dokument, do ktereho maji elementy patrit.
     * @param DOMDocument $doc    Dokument, do ktereho maji elementy patrit.  
     */
    private function _getDomFromArrayOrStdClass($var, DOMDocument $doc, DOMElement $parent)
    {
        if($var instanceof StdClass){
            // Ziskame pole z atributuu
            $var = get_object_vars($var);
        }
        
        foreach($var as $key => $val){
            // Preskocime atributy nastavene na null
            if($val === null){
                continue;
            }
            
            // Pokud je klic ve formatu '__nazev_cislo', budou mit vsechny takove prvky
            // tag jen s nazvem 'nazev'
            $matches = array();
            if(preg_match('/^__(.*)_[0-9]+$/i', $key, $matches)){
                $key = $matches[1];
            }
            
            if($val instanceof Ibulletin_XmlClass){
                // Ziskame XML elem dat a adoptujeme jej
                $elemForeign = $val->getDom();
                $nodes = $elemForeign->childNodes;
                
                for($i=0; $i<$nodes->length; $i++){
                    $elem = $doc->importNode($nodes->item($i), true);
                    $parent->appendChild($elem);
                }
            }
            elseif(is_array($val) || $val instanceof StdClass){
                $elem = $doc->createElement($key);
                $this->_getDomFromArrayOrStdClass($val, $doc, $elem);
                $parent->appendChild($elem);
            }
            else{
                $elem = $doc->createElement($key, $val);
                $parent->appendChild($elem);
            }
            
        }
    }
    
    /**
     * Skryje atribut tak, ze pri generovani XML nebude pouzito.
     * Skryti se provede prejmenovanim na jmeno zacinajici '__'
     */
    public function disableAttr($attrName, $emptyVal = null)
    {
        $attrNameHid = '__'.$attrName;
        if(!empty($this->$attrName)){
            $this->$attrNameHid = $this->$attrName;
            $this->$attrName = $emptyVal;
        }
    }
    
    /**
     * Skryje atribut tak, ze pri generovani XML nebude pouzito.
     * Skryti se provede prejmenovanim na jmeno zacinajici '__'.
     * 
     * @param string $attrName  Jmeno atributu.
     * @param mixed $emptyVal   null nebo '', s null nebude XML element vubec generovan.
     */
    public function enableAttr($attrName, $emptyVal = null)
    {
        $attrNameHid = '__'.$attrName;
        if(!empty($this->$attrNameHid)){
            $this->$attrName = $this->$attrNameHid;
            unset($this->$attrNameHid);
        }
    }
    
    /**
     * Zjisti, jestli je atribut aktualne disabled.
     * 
     * @param string $attrName  Atribut, ktery se ma overit.
     * @return bool Je zadany atribut vypnuty? 
     */
    public function isAttrDisabled($attrName)
    {
        $attrNameHid = '__'.$attrName;
        if(!empty($this->$attrNameHid)){
            return true;
        }
        else{
            return false;
        }
    }
}
