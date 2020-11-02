<?php
/**
 * @author Ondra Bláha <ondra@kvarta.cz>
 */

class GDPRAgrements extends Zend_Db_Table_Abstract
{
	protected $_name = "gdpr_agreements";
	protected $_primary = "id";

	/**	@var Zend_Db_Adapter_Abstract */
	protected  $_db;


	public function __construct()
	{
		$this->_db = Zend_Registry::get('db');
	}

	public function save($item) {

		$row = $this->find($item['id']);

		if ($row->count() > 0) {
			$where = $this->getAdapter()->quoteInto('id = ?', $item['id']);
			$this->update($item, $where);

		} else {
			$this->insert($item);

		}

	}

	public function getList() {
		$select = $this->select()->from(array('a'=>$this->_name),array('id','name'))->where('deleted_at IS NULL')->order('name');
		$list = $this->_db->fetchPairs($select);
		return $list;
	}

}
