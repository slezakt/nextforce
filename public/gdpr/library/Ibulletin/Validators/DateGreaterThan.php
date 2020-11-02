<?php 
/**
 *  Třída pro validaci datumů, zkontroluje zda je jedno datum větší než druhé (nebo víc) 
 *  datumy jsou brány z contextu a definované sou v constructoru jako pole názvu form elementů
 *
 *  @author Andrej Litvaj
 */
class Ibulletin_Validators_DateGreaterThan extends Zend_Validate_Abstract
{
    const LESS = 'less';

    /**
     *  Chybové zprávy.
     */
    protected $_messageTemplates = array(
    self::LESS => "'%value%' je menší než počáteční datum."
    );

    /**
    * The fields that the current element needs to match
    *
    * @var array
    */
    protected $_fieldsToMatch = array();
    
    /**
    * Constructor of this validator
    *
    * The argument to this constructor is the third argument to the elements' addValidator
    * method.
    *
    * @param array|string $fieldsToMatch
    */
    public function __construct($fieldsToMatch = array()) {

    	if (is_array($fieldsToMatch)) {
    		foreach ($fieldsToMatch as $field) {
    			$this->_fieldsToMatch[] = (string) $field;
    		}
    	} else {
    		$this->_fieldsToMatch[] = (string) $fieldsToMatch;
    	}    
    }
    
    /**
     *  implements Zend_Validate_Interface::isValid($value)
     */
    public function isValid($value,$context = null)
    {
    	$value = (string) $value;
        $this->_setValue($value);
        
        foreach ($this->_fieldsToMatch as $fieldName) {        	

	        preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):{0,1}([0-9]{0,2})/", $value, $e);
	        preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):{0,1}([0-9]{0,2})/", $context[$fieldName], $s);

	        $s = mktime($s[4], $s[5], $s[6], $s[3], $s[2], $s[1]);
	        $e = mktime($e[4], $e[5], $e[6], $e[3], $e[2], $e[1]);

	        if ($e < $s) {
	            $this->_error(self::LESS);
	            return FALSE;
	        }
        }
        
        return TRUE;
    }
}
?>