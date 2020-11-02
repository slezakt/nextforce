<?php
/**
 *	Třída pro validaci vstupu pro zadávání skupiny do filtru.
 *
 *	@auhtor Martin Krčmář.
 */
class Ibulletin_Validators_GroupFilter extends Zend_Validate_Abstract
{
	private $group;
	const WRONG = 'wrong';


	protected $_messageTemplates = array(
		self::WRONG => "špatně zadaná skupina"
	);

	public function isValid($value)
	{
		$this->_setValue($value);

		//$reg = "/^\w+(,( *)(\w+))*$/Us";
        $reg = "/^[[:alnum:]%_]+(,( *)([[:alnum:]%_]+))*$/Us";
        
	
		if (!preg_match($reg, $value))
		{
			$this->_error();
			return FALSE;
		}

		return TRUE;
	}
}
?>