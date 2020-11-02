<?php
/**
 * Konfigurace k prehrani videa, ktera muze byt nagenerovana do XML souboru,
 * nebo v XML primo vracena podle potreby.
 *
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Player_XmlConfigAv extends Ibulletin_XmlClass
{
    /**
     * @var string Nazev root tagu/elementu, pokud ma existovat, jinak null.
     */
    var $__rootName = 'media';
    
    /**
     * @var string  Identifikace videa - napr 'webcast1'
     */
    var $id = null;
    
    /**
     * @var string  Typ obsahu: video | audio
     */
    var $type = null;
    
    /**
     * @var string  Cesta k obrazku s nahledem pred spustenim videa - url, 
     *              povinne jen pokud jde o video.
     */
    var $preview = null;
    
    /**
     * @var string  Zdroj streamu videa - url
     */
    var $source = null;
    
    /**
     * @var StdClass  Odkazy na konci videa
     */
    var $links = null;
    
    /**
     * @var array  Vlozena SWF
     *             Klice druhe dimenze pole: 
     *                  id      - string id embedu
     *                  time    - cas videa na zacatku embed v ms
     *                  source  - cesta k embed - url
     *                  scale   - prizpusobit velikost embedu na velikost okna prehravace? true/false
     *                  reorturl
     *                  questionnaireid
     */
    var $embeds = array();
    
    
    /**
     * @var Ibulletin_Player_XmlConfigSync  Synchronizacni data prezentace a videa.
     */
    var $syncpoints = null;
    
    
    /**
     * Konstruktor - zinicializuje nektere promenne. 
     */
    public function __construct()
    {
        $this->links = new StdClass();
    }
    
    /**
     * Prida embed. Pokud je zadan existujici klic $key, je dany embed editovan.
     * 
     * @param int $time         Cas videa v ms.
     * @param string $source    URL k SWF souboru.
     * @param string $key       Existujici klic embedu pro editaci daneho embedu.
     * @param string $id        ID pro dany embed. Pokud neni vyplneno, vyplni se samo.
     * @param bool $scale       Ma se prizpusobit velikost SWF oknu prehravace?
     * @param string $reportUrl URL pro odesilani vysledku predanych embed flashem.
     * @param int $questionnaireId ID dotazniku, na ktery je tento embed napojen
     * @return string         Klic $key v poli embedu
     */
    public function addEmbed($time, $source, $key = null, $id = null, $scale = true, $reportUrl = null, 
            $questionnaireId = null)
        {
        // Protoze embeds mohou byt skryte, musime rozhodnout kam je mame pridat. 
        if(!empty($this->__embeds)){
            $embeds_name = '__embeds';
        }
        else{
            $embeds_name = 'embeds';
        }
        
        // Pokud neni zadan klic kvuli editaci, musime ho vyrobit
        if(!isset($key)){
            // Klic pole je takhle slozity kvuli exportu do XML
            $count = count($this->embeds);
            // Musime najit klic takovy, aby jeste neexistoval
            while(1){
                $key = '__embed_'.$count;
                if(!isset($this->{$embeds_name}[$key])){
                    break;
                }
                $count ++;
            }
        }
        // Pokud je zadan klic, editujeme => nastavime nezadane parametry na ty jiz existujici
        else{
            if($time === null){
                $time = $this->{$embeds_name}[$key]['time'];
            }
            if($source === null){
                $source = $this->{$embeds_name}[$key]['source'];
            }
            if($id === null){
                $id = $this->{$embeds_name}[$key]['id'];
            }
            if($scale === null){
                $scale = $this->{$embeds_name}[$key]['scale'];
            }
            if($reportUrl === null){
                $reportUrl = $this->{$embeds_name}[$key]['reporturl'];
            }
            if($questionnaireId === null){
                $questionnaireId = $this->{$embeds_name}[$key]['questionnaireid'];
            }
        }
        
        
        // Pokud neni zadano ID, vyplnime take klicem
        if(!$id){
            $id = $key;
        }
        
        $this->{$embeds_name}[$key] = array('id' => $id, 'time' => $time, 
            'source' => $source, 'scale' => $scale, 'reporturl' => $reportUrl, 
            'questionnaireid' => $questionnaireId);
        
        return $key;
    }
    
    
    /**
     * Odebere embed z pole embeduu
     * 
     * @param string $key   Klic v poli embedu - nejedna se o ID!
     */
    public function removeEmbed($key)
    {
        // Protoze embeds mohou byt skryte, musime rozhodnout kam je mame pridat. 
        if(!empty($this->__embeds)){
            $embeds_name = '__embeds';
        }
        else{
            $embeds_name = 'embeds';
        }
        
        unset($this->{$embeds_name}[$key]);
    }
    
    /**
     * Vrati pole embedu.
     * 
     * @return array    Pole embedu.
     */
    public function getEmbeds()
    {
        // Protoze embeds mohou byt skryte, musime rozhodnout, kde jsou. 
        if(!empty($this->__embeds)){
            $embeds_name = '__embeds';
        }
        else{
            $embeds_name = 'embeds';
        }
        return $this->$embeds_name;
    }
        
    /**
     * Vlozi konfiguraci syncpoints. Pri tom nastavi target syncpontu na spravnou hodnotu z
     * $this->__syncpointsTarget.
     * 
     * @param Ibulletin_Player_XmlConfigSync $syncpoints    Objekt se syncpointy.
     * @param string $htmlElemId    Id HTML elementu s prehravacem videa.
     */
    public function setSyncpoints(Ibulletin_Player_XmlConfigSync $syncpoints, $htmlElemId)
    {
        // Protoze syncpoints mohou byt skryte, musime rozhodnout kam je mame pridat. 
        if(!empty($this->__syncpoints)){
            $attrName = '__syncpoints';
        }
        else{
            $attrName = 'syncpoints';
        }
        
        $syncpoints->target = $htmlElemId;
        $this->$attrName = $syncpoints;
    }
    
    /**
     * Oebere konfiguraci syncpoints.
     */
    public function removeSyncpoints()
    {
        $this->syncpoints = null;
        if(isset($this->__syncpoints)){
            unset($this->__syncpoints);
        }
    }
}