<?php
/*

 *	Třída pro validaci vstupu o filtru pro časovou známku.
 *	Při validaci zkusí provést SQL dotaz se zadaným parametrem. Pokud dotaz
 *	projde vrátí TRUE, jinak FALSE.
 */
 
 
class Ibulletin_Validators_TimestampFilter extends Zend_Validate_Abstract
{
	const WRONG = 'wrong';

	protected $_messageTemplates = array(
		self::WRONG => 'špatně zadaný filtr'
	);

	public function isValid($time)
	{
		$this->_setValue($time);

		$config = Zend_Registry::get('config');
		$db = Zend_Registry::get('db');

		$select = $db->select()
			->from('users')
			->where("added $time")
			->limit(1);

		try
		{
			$res = $db->fetchRow($select);
			return TRUE;
		}
		catch (Zend_Db_Exception $e)
		{
			$this->_error();
			return FALSE;
		}
	}
}
?>