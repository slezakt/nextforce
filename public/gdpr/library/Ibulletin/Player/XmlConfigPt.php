<?php
/**
 * Konfigurace k prehrani prezentace, ktera muze byt nagenerovana do XML souboru,
 * nebo vracena v XML podle potreby.
 *
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Player_XmlConfigPt extends Ibulletin_XmlClass
{
    /**
     * @var string Nazev root tagu/elementu, pokud ma existovat, jinak null.
     */
    var $__rootName = 'presentation';
    
    /**
     * @var string  Identifikace prezentace - napr 'webcast1'
     */
    var $id = null;
    
    
    /**
     * @var string  Zdroj streamu videa - url
     */
    var $source = null;
    
    /**
     * @var Ibulletin_Player_XmlConfigSync  Synchronizacni data prezentace a videa.
     */
    var $syncpoints = null;
    
    
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