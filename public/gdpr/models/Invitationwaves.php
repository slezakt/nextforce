<?php

/**
 * iBulletin - Invitatiowaves.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}

/**
 * Trida obsahujici metody pouzivane pro praci se zvacimi vlnami
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Invitationwaves
{
    /**
     * Najde a vrati zvaci vlnu
     *
     * @param Int ID vlny.
     * @return array Zaznam vlny
     */
    public static function get($id)
    {
        $db = Zend_Registry::get('db');
        
        $sel = $db->select()
                ->from('invitation_waves')
                ->where('id = ?', (int)$id);
        
        $row = $db->fetchRow($sel);
        
        return $row;
    }
    
    
    /**
     * Najde a vrati seznam zvacich vln
     *
     * @param string Order by klauzule.
     * @return array Pole poli zaznamu jednotlivych vln
     */
    public static function getList($order)
    {
        Zend_Loader::loadClass('Zend_Auth');
        
        $db = Zend_Registry::get('db');
        
        $sel = $db->select()
                ->from('invitation_waves');
        
        if($order === null){
            $sel->order('start DESC');
        }
        else{
            $sel->order($order);
        }
        
        $rows = $db->fetchAll($sel);
        
        return $rows;
    }
    
    /**
     * Vrati seznam vln vhodny pro select pole - klicem je ID vlny
     * a popisek je podle potreby nazev, nebo nazev a start.
     * 
     * @param bool   Pridat do popisu i start?
     * @param string Format datumu z valid_from odpovidajici formatum akceptovanym Zend_Date.
     * @param string Order by klauzule.
     * @return array Klicem je id vlny, hodnotou je popisek.
     */
    public static function getListForSelect($with_validity = false, $date_format = null, $order = null)
    {
        $config = Zend_Registry::get('config');
        
        if($date_format === null){
            $date_format = $config->general->dateformat->medium;
        }
        
        $waves = self::getList($order);
        
        $select = array();
        foreach($waves as $wave){
            $label = $wave['name'];
            if($with_validity){
                $date = new Zend_Date($wave['start'], Zend_Date::ISO_8601);
                $label .= ' ('.$date->toString($date_format).')';
            }
            $select[$wave['id']] = $label; 
        }
        
        return $select;
    }
    
    /**
     *  Overi, jestli je mozny pristup do webu pres zadane url_name vlny nebo/a referer.
     *  Pusti do vydani uzivatele z webu, ktery odpovida url_prefixu v nektere(rych) vlnach
     *  pripadne pusti do vydani uzivatele se spravnym url_name vlny, nebo kontroluje oboji najednou
     *  v pripade pristupu pres url nazev vlny.
     *  
     *  @param  string  URL name pro danou vlnu
     *  @param  string  Referer ziskany z promenne SERVERU
     *  
     *  @return Int/false   Int ID vlny v pripade, ze je povolen pristup, false jinak.
     */ 
    public static function isEnterAllowed($referer, $urlName = null)
    {
        $db = Zend_Registry::get('db');
        
        // Rozhodnuti, jak se ma posuzovat validita vydani
        $session = Zend_Registry::get('session');
        $auth = Zend_Auth::getInstance();
        if(!empty($session->allowed_inactive_content) || $auth->hasIdentity()){
            $whereValidity = '1 = 1';
        }
        else{
            $whereValidity = 'coalesce(iw.start, b.valid_from) < current_timestamp';
        }
        
        // Pokud je zadana vlna
        if(!empty($urlName)){
            $sel = "SELECT iw.*
                FROM invitation_waves iw
                JOIN bulletins_v b ON b.id = iw.bulletin_id
                WHERE
                    iw.url_name = ".$db->quote($urlName)."
                    AND $whereValidity
                ORDER BY
                    coalesce(iw.start, b.valid_from)
                LIMIT 1
                ";
            
            
            $row = $db->fetchRow($sel);
            
            if(!empty($row)){
                // Nahradime znaky kvuli regexpus
                $row['url_prefix'] = str_replace('/', '\/', str_replace('.', '\.', $row['url_prefix']));
                if(!$row['force_referer'] || preg_match("/^(.){2,8}:\/\/".$row['url_prefix']."/i", $referer)){
                    return $row['id'];
                }
            }
        }
        else{
            $sel = "SELECT iw.*
                FROM invitation_waves iw
                JOIN bulletins_v b ON b.id = iw.bulletin_id
                WHERE
                    ".$db->quote($referer)." ~* 
                        ('^(.){2,8}:\\/\\/'::text || regexp_replace(regexp_replace(iw.url_prefix, '\\.', '\\.', 'g'), '\\/', '\\/', 'g')::text)
                    AND $whereValidity
                ORDER BY
                    coalesce(iw.start, b.valid_from) DESC
                LIMIT 1
                ";
            
            $row = $db->fetchRow($sel);
            
            if(!empty($row)){
                return $row['id'];
            }
        }
        

        return false;
    }
    
    /**
     * Vrati ciselnik typu vln
     * @return array pole typu
     */
    public static function getWaveTypes() {
        
         $texts = Ibulletin_Texts::getSet('admin.invitationwaves');
         
         $waveTypes = array(
        	0 => $texts->type_email,
        	1 => $texts->type_postbox,
        	2 => $texts->type_link
        );
        
        return $waveTypes;
         
    }
    
}