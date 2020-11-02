<?php
/**
 * Trida reprezentujici XML attributy pro jeden element.
 * 
 * @author Bc. Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_XmlElementAttributes
{
    /**
     * Nacte prvky pole do atributuu objektu.  
     * 
     * @param  array   Pole, ktere ma byt nacteno jako atributy
     */
    public function __construct($array){
        $this->loadArray($array);
    }
    
    /**
     * Nacte prvky pole do atributuu objektu. 
     * 
     * @param  array   Pole, ktere ma byt nacteno jako atributy
     */
    public function loadArray($array)
    {
        foreach($array as $key => $val){
            $this->{$key} = $val;
        }
    }
}
