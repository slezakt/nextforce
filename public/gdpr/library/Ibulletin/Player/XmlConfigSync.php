<?php
/**
 * Konfiguracni data k synchronizaci videa ci audia s prezentaci.
 *
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Player_XmlConfigSync extends Ibulletin_XmlClass
{
    /**
     * @var array  Pole synchronizacnich bodu. Klicem je cas v ms a hodnotou je cislo slidu
     */
    var $syncpoints = array();
    
    /**
     * @var string  Jsou to syncpointy pro video nebo prezentaci. Video ma jako klice casy
     *              a prezentace ma jako klice slidy. 'vid'/'pres'
     */
    var $__media = null;
    
    /**
     * @var string  Atribut target pro syncpoints.
     */
    var $__syncpoints_target_attr = null;
    
    /**
     * Prida syncpoint
     * 
     * @param int $time     Cas videa v ms
     * @param int $slide    Cislo slidu
     */
    public function addSyncpoint($time, $slide)
    {
        if($this->__media == 'vid'){
            $this->syncpoints[$time] = $slide;
        }
        elseif($this->__media == 'pres'){
            $this->syncpoints[$slide] = $time;
        }
        ksort($this->syncpoints);
        reset($this->syncpoints);
    }
    
    /**
     * Odebere syncpoint
     * 
     * @param int $time     Cas videa v ms
     * @param int $slide    Cislo slidu
     */
    public function removeSyncpoint($time, $slide)
    {
    if($this->__media == 'vid'){
            unset($this->syncpoints[$time]);
        }
        elseif($this->__media == 'pres'){
            unset($this->syncpoints[$slide]);
        }
    }
    
    /**
     * Vrati pole syncpointu, klic je podle zdroje cas u videa a slid u prezentace.
     * POZOR - co je klicem a co hodnotou zavisi na nastaveni $this->__media!!
     * 
     * @return array    Pole syncpointuu.
     */
    public function getSyncpoints()
    {
        return $this->syncpoints;
    }
    
    /**
     * Nastavi atribut target v tagu syncpoints
     * 
     * @param string $target    Target do tagu syncpoints 
     */
    public function setTarget($target)
    {
        $this->__syncpoints_target_attr = $target;
    }
    
    /**
     * Nastavi co je zdrojem dat - jestli video nebo prezentace.
     * 
     * @param string $media    Media syncpointu - 'vid' video / 'pres' prezentace
     */
    public function setMedia($media)
    {
        $this->__media = $media;
    }
    
    /**
     * Vrati DOMDocument objekt.
     * 
     * @return DOMDocument Dokument DOM reprezentujici data v objektu.
     */
    public function getDom()
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        
        $syncpoints = $doc->createElement('syncpoints');
        $syncpoints->setAttribute('target', $this->__syncpoints_target_attr);
        $doc->appendChild($syncpoints);
        
        foreach($this->syncpoints as $time => $slide){
            // Pro prezentaci mame klicem slide a hodnotou cas
            if($this->__media == 'pres'){
                $time_pom = $time;
                $time = $slide;
                $slide = $time_pom;
            }
            
            $syncpoint = $doc->createElement('syncpoint');
            $syncpoints->appendChild($syncpoint);
            
            
            $timeE = $doc->createElement('time', $time);
            $syncpoint->appendChild($timeE);
            
            $slideE = $doc->createElement('slide', $slide);
            $syncpoint->appendChild($slideE);
        }
        
        return $doc;
    }
}
