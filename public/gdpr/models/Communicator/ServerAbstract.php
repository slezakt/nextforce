<?php
/**
 * ServerAbstract.php
 * 
 * @author  Bc. Petr Skoda
 */

/**
 * 
 * @author Bc. Petr Skoda
 */
abstract class Communicator_ServerAbstract
{
    /**
     * DB adapter
     * 
     * @var Zend_Db_Adapter_Abstract
     */
    public $db = null;
    
    /**
     * Adresar kde se maji vyhledavat facades pro vystaveni
     * @var string
     */
    protected $_facadesDir = 'models/Communicator';
    
    /**
     * Pattern pro preg_match ktery rozpoznava soubory facades a vybira z nazvu souboru
     * cast ze ktere slozime jmeno tridy
     * @var string
     */
    protected $_facadeFileRegex = '/^(ServerFacade.*)\.php$/';
    
    /**
     * Prefix pro nazev tridy Facade patrici pred nazev souboru
     * @var string
     */
    protected $_facadeClassPrefix = 'Communicator_';
    
    /**
     * Konstruktor - nastavi DB adapter nebo jej ziska ze Zend_Db_Table_Abstract
     * 
     * @param Zend_Db_Adapter_Abstract
     */
    public function __construct(Zend_Db_Adapter_Abstract $db = null)
    {
        
        if(empty($db)){
            // Pripravime DB
            //$this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $this->db = Zend_Registry::get('db');
        }
        else{
            $this->db = $db;
        }
    }
    
    /**
     * Factory pro ziskavani instanci communicatoru.
     * 
     * Zatim pouzivame vyhradne XML-RPC, takze vzdy vraci Communicator_ServerXmlRpc
     * 
     * @param Zend_Db_Adapter_Abstract
     * @return Communicator_ServerAbstract
     */
    public static function factory(Zend_Db_Adapter_Abstract $db = null)
    {
        $com = new Communicator_ServerXmlRpc($db);
        return $com;
    }
    
    
    /**
     * Ziska seznam trid, ktere obsahuji funkce k vystaveni.
     * 
     * Prohledava natvrdo models/ na soubory koncici Facade.php
     * 
     * @return array    Nazvy trid facade pro vystaveni. 
     */
    public function getFacades()
    {
        $prefix = $this->_facadeClassPrefix;
        $classes = array();

        if(file_exists($this->_facadesDir) && ($dirh = opendir($this->_facadesDir))){
            while($dir_element = readdir($dirh)){
                $m = array();
                if(preg_match($this->_facadeFileRegex, $dir_element, $m)){
                    $classes[] = $prefix.$m[1];
                }
            }
            unset($dir_element);
            closedir($dirh);
        }
        
        return $classes;
    }
    
    
    /**
     * Pripravi vse potrebne
     */
    public abstract function prepare();
    
    
    /**
     * Zpracuje pozadavek, predem musi byt spustena metoda prepare
     */
    public abstract function dispatch();
    
}