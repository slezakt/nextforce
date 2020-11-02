<?php
/**
 * @author Ondra Bláha <ondra@kvarta.cz>
 */

class GDPRUsersAgrements extends Zend_Db_Table_Abstract
{
	protected $_name = "gdpr_users_agreements";
	protected $_primary = "id";

	/**	@var Zend_Db_Adapter_Abstract */
	protected  $_db;


	public function __construct()
	{
		$this->_db = Zend_Registry::get('db');
	}


	public function save($item) {

		$where[] = $this->getAdapter()->quoteInto('user_id = ?',$item['user_id']);
		$where[] = $this->getAdapter()->quoteInto('agreement_id = ?',$item['agreement_id']);

		$row = $this->fetchRow($where);

		if ($row) {
			$this->update($item, $where);

		} else {
			$this->insert($item);

		}

	}


	public function delete($userId, $agreementId) {

		$where[] = $this->getAdapter()->quoteInto('user_id = ?',$userId);
		$where[] = $this->getAdapter()->quoteInto('agreement_id = ?',$agreementId);

		$this->update(array('deleted_at' => 'now'),$where);

	}


}
