<?php

/**
 * iBulletin - Config.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}

/**
 * Trida slouzici ke snadnemu pristupu ke konfiguraci ulozene v DB
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Config
{
    /**
     * Instance tridy.
     *
     * @var Config
     */
    private static $_inst = null;
    
    /**
     * Nactena konfigurace z DB.
     *
     * @var StdClass
     */
    private $_config = null;

    /**
     * Vrati existujici instanci Config nebo vytvori novou a tu vrati.
     *
     * @return Config Jedina instance Config
     */
    public static function getInstance()
    {
        if(self::$_inst === null){
            self::$_inst = new Config();
        }
        
        return self::$_inst;
    }
    
    /**
     * Konstruktor nacte konfiguraci do atributu tridy.
     */
    public function __construct()
    {
        $db = Zend_Registry::get('db');
        $select = new Zend_Db_Select($db);
        $select->from('configs', '*');
        
        $data = $db->fetchAll($select);
        
        $this->_config = new StdClass;
        
        foreach($data as $pair){
            $this->_config->$pair['name'] = $pair['value'];
        }
        
    }
    
    
    /**
     * Prida nebo upravi promennou v konfiguraci
     * Zmena je ulozena ihned do DB
     *
     * @param string $name  Jmeno konfiguracni promenne.
     * @param string $value Hodnota konfiguracvni promenne.
     */
    public static function set($name, $value)
    {
        $inst = self::getInstance();
        $db = Zend_Registry::get('db');
        
        // editace
        if(isset($inst->_config->$name)){
            $db->update('configs', array('value' => $value), 'name = '.$db->quote($name));
        }
        // pridani
        else{
            $db->insert('configs', array('value' => $value, 'name' => $name));
        }
        
        // Pridame/zmenime do aktualni nactene konfigurace
        $inst->_config->$name = $value;
    
    }
    
    /**
     * Vrati pozadovanou konfiguracni promennou nebo null v pripade neexistence.
     * 
     * @param string $name  Jmeno pozadovane promenne.
     * @return string       Hodnota pozadovane promenne nebo null v pripade neexistence.
     */
    public static function get($name)
    {
        $inst = self::getInstance();
        
        if(isset($inst->_config->$name)){
            return $inst->_config->$name;
        }
        else{
            return null;
        }
    }
    
    /**
     * Odebere pozadovanou konfiguracni promennou.
     * 
     * @param string $name  Jmeno promenne k odebrani.
     */
    public static function remove($name, $value)
    {
        $inst = self::getInstance();
        $db = Zend_Registry::get('db');
        
        if(isset($inst->_config->$name)){
            $db->delete('configs', 'name = '.$db->quote($name));
            
            // Odebereme promennou
            unset($inst->_config->$name);
        }
    }
    
    /**
     * Ulozi basic config do config_admin.ini
     * @param array $values
     * @param string $configFile path to config file
     * @return array (state, errors)
     */
    public static function setBasicConfig($values, $configFile) {
        
        $errors = array();
        $state = false;

        // array to csv
        if(isset($values['general']['forbidden_came_in_pages'])) {
            $values['general']['forbidden_came_in_pages'] = implode(',', (array)$values['general']['forbidden_came_in_pages']);
        }

        //return path, 0->null, will removed to set default
        if ($values['mailer']['return_path'] == 0) {
            $values['mailer']['return_path'] = null;
        } else {
            //pokud posilame project domain pouzijeme ji, jinak gloovalni SERVER_NAME
            if (isset($values['mailer']['return_path_domain'])) {
               $domain = $values['mailer']['return_path_domain']; 
               unset($values['mailer']['return_path_domain']);
            } else {
               $domain = $_SERVER['SERVER_NAME']; 
            }
            // Odstranit www. nebo vyvoj. z domeny
            $prefixes = array('www.', 'vyvoj.');
            foreach ($prefixes as $prefix) {
                if (substr($domain, 0, strlen($prefix)) == $prefix) {
                    $domain = substr($domain, strlen($prefix));
                } 
            }

            //overeni spravnosti nastaveni MX zaznamu
            if (Utils::checkMXRecordTarget($domain)) {
                $values['mailer']['return_path'] = 'undelivered@' . $domain;
            } else {
                $errors[] = 'error_mailer_return_path';
                unset($values['mailer']['return_path']);
            }
        }

        // split config file by CRLF
        $lines = preg_split('/\n|\r\n?/', file_get_contents($configFile));

        //projdeme sekce a vyplnime polozky
        foreach ($values as $section => $s_values) {

            // replace all found configuration keys with new values
            $not_replaced = $s_values;
            //cislo radku cfg
            $r = 0;
            foreach ($lines as &$line) {
                foreach ($s_values as $k => $v) {
                    // replace values 
                    if (preg_match('/^\\s*' . preg_quote($k) . '\\s*=/', $line)) {
                        //je-li hodnota null, odebereme řádku
                        if (is_null($v)) {
                            unset($lines[$r]);
                        } else {
                            $line = $k . ' = "' . $v . '"';
                        }
                        unset($not_replaced[$k]);
                    }
                }
                $r++;
            }

            // add new not replaced yet
            if (!empty($not_replaced)) {
                $t_section = '[' . $section . ']';
                // find [section] line number
                if (($line_no = array_search($t_section, $lines)) === FALSE) {
                    $lines[] = $t_section;
                    $line_no = count($lines) - 1;
                }

                // split at section line
                $head_lines = array_slice($lines, 0, $line_no + 1);
                $tail_lines = array_slice($lines, $line_no + 1);

                // add new values after section line, if is not null
                foreach ($not_replaced as $k => $v) {
                    if (!is_null($v)) {
                        $head_lines[] = $k . ' = "' . $v . '"';
                    }
                }
                // and concatenate result
                $lines = array_merge($head_lines, $tail_lines);
            }
        }

        // write to file
        if (file_put_contents($configFile, implode("\n", $lines))) {
            $state = true;
        }

        return array(
            'state' => $state,
            'errorsMsg' => $errors
        );
    }
 
}
