<?php

/**
 *	Kontrolér pro ukládání výsledků z virtuálního pacienta.
 *
 *	@author Martin Krčmář
 */
 class VirtualpatientController extends Zend_Controller_Action
 {
	public function saveAction()
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		$request = $this->getRequest();			// objekt request
        
        // Nastavime do stats akci
        Ibulletin_Stats::getInstance()->setAttrib('action', 'virtualpatient');

		if ($request->__isset('session') && is_numeric($request->getParam('session')))
		{
			$session = $request->getParam('session');
		}
		else
		{
            $session = Ibulletin_Stats::getInstance()->session_id;
			/*
            Phc_ErrorLog::error(
				'Virtual patient controller',
				'Chybí parametr session'
			);
			exit();
            */
		}

		if ($request->__isset('vzkaz'))
		{
			$message = $request->getParam('vzkaz');
		}
		else
		{
			Phc_ErrorLog::warinig(
				'Virtual patient controller',
				'Chybí parametr vzkaz'
			);
			exit();
		}
        
        
        Phc_ErrorLog::notice('Virtual patient controller', 'Message:"'.$message).'".';
        
		$data = array(
			'session_id' => $session,
			'message' => $message,
			'created' => new Zend_Db_Expr('current_timestamp')
		);

		try
		{
			$db->insert('virtual_patient', $data);
		}
		catch (Zend_Db_Statement_Exception $e)
		{
			Phc_ErrorLog::error(
				'Virtual patient controller',
				'Nepodařilo se uložit vzkaz do DB.'.$e->getMessage()
			);
			exit();
		}
	}
 }

