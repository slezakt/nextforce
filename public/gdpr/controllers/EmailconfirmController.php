<?php

/**
 *	Kontrolér pro generování obrázků při potvrzování přečtění emailů.
 *
 *	@author Martin Krčmář
 */
class EmailconfirmController extends Zend_Controller_Action
{
	/**
	 *	Metoda, která zpravuje požadavek na obrázek. Uloží do DB čas přečtení
	 *	k příslušnému tokenu a přesměruje na obrázek, který se poté vrátí 
	 *	kllientovi.
	 */
	public function trackAction()
	{
		$db = Zend_Registry::get('db');
		$config = Zend_Registry::get('config');

		if (!($config->get('mailer') && $config->mailer->get('pic')))
		{
			Phc_ErrorLog::error(
				'Email confirm controller',
				'Není nastavena adresa tracking obrázku v configu'
			);
			return;
		}

		if (!$config->get('tables'))
		{
			Phc_ErrorLog::error(
				'Email confirm controller',
				'Chybí nastavení tabulek v configu'
			);
			$this->redirectToImage($config->mailer->pic);
		}

		$request = $this->getRequest();			// objekt request

		if ($request->__isset('token'))			// pokud je nastaven parametr token
			$token = $request->getParam('token');
		else
		{
			Phc_ErrorLog::error(
				'Email confirm controller',
				'Chybí parametr token'
			);
			$this->redirectToImage($config->mailer->pic);
		}
		
		// do DB se ulozi, ze uzivatel email precetl, vyplni se sloupec 
		// read_date v tabulce users_emails.
		// zapise se asi jenom prvni precteni, pokud uzivatel na to prijde potom 
		// jeste jednou, tak uz se nic neudela

		// zjisti se, zda mail uz byl precten
		$select = $db->select()
			->from($config->tables->users_emails)
			->where('token = ?', $token);

		try
		{			
			$result = $db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
		}
		catch (Zend_Db_Statement_Exception $e)
		{
			Phc_ErrorLog::error(
				'Email confirm controller',
				'Nepodařilo se vykonat SQL dotaz: '.$select->__toString()
			);
			$this->redirectToImage($config->mailer->pic);
		}

		// email jeste nebyl precten, nastavime read_date
		if (empty($result->read_date))
		{
			$data = array('read_date' => new Zend_Db_Expr('current_timestamp'));
			try
			{
				$affected = $db->update(
					$config->tables->users_emails,
					$data,
					"token = '$token'");
			}
			catch (Zend_Db_Statement_Exception $e)
			{
				Phc_ErrorLog::error(
					'Email confirm controller',
					"Nepodařilo se uložit read_date emailu s tokenem: $token"
				);
				$this->redirectToImage($config->mailer->pic);
			}

			// token neni v DB
			if ($affected == 0)
			{
				Phc_ErrorLog::warning(
					'Email confirm controller',
					"V DB nebyl nalezen token: $token"
				);
				$this->redirectToImage($config->mailer->pic);
			}
		}

		// presmerujeme se na obrazek, ten se vrati klientovi
		$this->redirectToImage($config->mailer->pic);
	}

	/**
	 *	Metoda provede pouze přesměrování na obrázek.
	 *
	 *	@param URL obrázku.
	 */
	function redirectToImage($url)
	{
		$this->_helper->getHelper('Redirector')->gotoUrl($url);
	}
}

?>
