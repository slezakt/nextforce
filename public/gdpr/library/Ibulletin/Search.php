<?php

/**
 * iBulletin - Search.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Ibulletin_Auth_Register_Action_Exception extends Exception {}

/**
 * Trida poskytujici pomocne funkce k vyhledavani a indexovani v iBulletinu.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_Search
{
    /**
     * Nacte a vrati instanci Zend_Search_Lucene_Interface. V pripade neexistence
     * indexu vytvori novy. Cesta k souboru indexu je nastavena v 
     * config->search->index_file.
     *
     * @return Zend_Search_Lucene_Interface Search index ibulletinu
     */
    public static function getSearchIndex()
    {
        Zend_Loader::loadClass('Zend_Search_Lucene');
        $config = Zend_Registry::get('config');
        
        try{
            $index = Zend_Search_Lucene::open($config->search->index_file);
        }
        catch(Zend_Search_Lucene_Exception $e){
            // Vytvorime novy index
            $index = Zend_Search_Lucene::create($config->search->index_file);
        }
        
        return $index;
    }
}
