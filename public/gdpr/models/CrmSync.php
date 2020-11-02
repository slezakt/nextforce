<?php
/**
 * Trida obsluhujici synchronizaci kontaktů se CRM
 * @author Ondra Bláha <ondra@kvarta.cz>
 *
 */


class CrmSync
{

	/**
	 * @var int source ID dle CRM
	 */
	private $sourceId;


	public function __construct()
	{
		$this->sourceId = $this->getSourceId();
	}


	/**
	 * Vrati odpoved z CRM
	 * @param array $query
	 * @return mixed
	 * @throws Zend_Http_Client_Exception
	 */
	public function getResponse($query) {

		$config = Zend_Registry::get('config');

		$url = $config->gdpr->crm->url;
		$responseTimeout = $config->gdpr->crm->response_timeout;

		if (!$url || !$responseTimeout) {
			throw new Exception("CRMSync: Chybne nastaveni CRM, chybi url nebo response_timeout");
		}

		$jsonQuery = json_encode($query);

		$client = new Zend_Http_Client($url, array('timeout'=>$responseTimeout));
		$client->setHeaders('Content-type', 'application/json');
		$client->setRawData($jsonQuery, 'application/json')->request('POST');
		$response = $client->request(Zend_Http_Client::POST)->getBody();

		$response = json_decode($response);

		if (isset($response->errors)) {
			$errors = array();
			foreach ($response->errors as $e) {
				$errors[] = $e->message;
			}

			$errors = implode(', ',$errors);
			throw new Exception("CrmSync error response: ".$errors.', query: '.$jsonQuery);
		}

		return $response;

	}


	/**
	 * Získá z configu sourceId pro komunikaci s CRM
	 * @return int
	 * @throws Exception
	 * @throws Zend_Exception
	 */
	public function getSourceId() {

		$config = Zend_Registry::get('config');
		$sourceId = $config->gdpr->crm->source_id;

		if (!$sourceId) {
			throw new Exception("CrmSync: Chybne nastaveni GDPR, crm.source_id neni nastaveno");
		}

		return $sourceId;

	}


	/**
	 * Vrati seznam atributu uzivatel, v pripade ze uzivatel je ma vyplnene je synchronizova do CRM
	 * @return array
	 * @throws Exception
	 * @throws Zend_Exception
	 */
	public function getSyncUsersAttribs() {

		$config = Zend_Registry::get('config');
		$attribs = $config->gdpr->crm->sync_users_with_attribs;

		if (!$attribs) {
			throw new Exception("CrmSync: Chybne nastaveni GDPR, crm.sync_users_with_attribs neni nastaveno");
		}

		return explode(',',str_replace(' ','',$attribs));

	}


	/**
	 * Seznam uživatelů pro synchronizaci
	 * @param  $force bool pri true jsou zarazeni uzivatele bez ohledu na posledni replikace
	 * @return array
	 * @throws Zend_Exception
	 */
	public function getUsersForSync($force = false) {

		/** @var Zend_Db_Adapter_Abstract $db */
		$db = Zend_Registry::get('db');

		$attribs = $this->getSyncUsersAttribs();

		$attribsWhere = array();

		foreach ($attribs as $a) {
			$attribsWhere[] = "COALESCE(".$a.",'') <> ''";
		}

		$where = '('.implode(' OR ',$attribsWhere).')';

		if (!$force) {
			$where .= " AND (last_changed > last_replicated OR last_replicated IS NULL)";
		}

		$select = $db->select()->from(array('u'=>'users'))->where($where);

		$select = Users::expandAttribs($select);

		return $select->query()->fetchAll();

	}


	/**
	 * Získá seznam souhlasu z CRM
	 * @return array
	 * @throws Exception
	 * @throws Zend_Exception
	 * @throws Zend_Http_Client_Exception
	 */
	public function getAgreements() {

		$query = array('query'=>'query{agreementSearch(sourceId: '.$this->sourceId.'){id, name, description, attachmentPath, anonymizeInterval, validTill, source{id, name, url, administratorName, name}}}');

		$agreementsResponse = $this->getResponse($query);

		return $agreementsResponse->data->agreementSearch;
	}


	/**
	 * Ulozi souhlasy ziskane z CRM do DB
	 * @param $agreements pole souhlasu ziskane z CRM
	 */
	public function saveAgreements($agreements) {

		$items = array_map(function($a) {
			return array('id' => $a->id, 'name'=>$a->name, 'description' => $a->description);
		}, $agreements);

		$gdprAgreements = new GDPRAgrements();

		foreach ($items as $item) {

			$gdprAgreements->save($item);

		}
	}


	/**
	 * Odstrani neplatne souhlasy z DB
	 *
	 * @param $agreements pole souhlasu ziskane z CRM
	 */
	public function deleteInvalidAgreements($agreements) {

		$gdprAgreements = new GDPRAgrements();

		$agreementIds = array_map(function($a){
			return $a->id;
		},$agreements);

		$where = $gdprAgreements->getAdapter()->quoteInto('id NOT IN (?)',$agreementIds);

		$gdprAgreements->update(array('deleted_at' => 'now'), $where);

	}


	public function getConfigAgreements() {

		/** @var Zend_Config_Ini $config */
		$config = Zend_Registry::get('config');

		if (!isset($config->gdpr->agreements) || empty($config->gdpr->agreements)) {
			throw new Exception("CrmSync: Chybne nastaveni GDPR, agreements neni nastaveno");
		}

		$configAgreements = $config->gdpr->agreements->toArray();

		if (!$configAgreements) {
			throw new Exception("CrmSync: Chybne nastaveni GDPR, agreements neni nastaveno");
		}

		$gdprAgreements = new GDPRAgrements();

		foreach ($configAgreements as $ca => $id) {

			if(empty($id)) {
				throw new Exception("CrmSync: Chybne nastaveni GDPR, agreements neni nastaveno");
			}

			$where = $gdprAgreements->getAdapter()->quoteInto('id = ? AND deleted_at IS NULL',$id);
			$items = $gdprAgreements->fetchAll($where);

			if ($items->count() == 0) {
				throw new Exception("CrmSync: Souhlas $ca, id = $id v CRM neexistuje");
			}
		}

		return $configAgreements;

	}


	/**
	 * Synchronizace souhlasu a attributu uzivatelu z CRM
	 * @param $users
	 * @throws Exception
	 * @throws Zend_Date_Exception
	 * @throws Zend_Exception
	 * @throws Zend_Http_Client_Exception
	 */
	public function syncUsersAgreements($users) {

		$configAgreements = $this->getConfigAgreements();

		$gdprUsersAgreements = new GDPRUsersAgrements();

		foreach ($users as $u) {

			$validAgreements = array();

			foreach ($configAgreements as $a => $agreementId) {

				if (isset($u[$a])) {

					if ($u[$a] == "-") {
						$gdprUsersAgreements->delete($u['id'], $agreementId);
						$this->removeCRMAgreement($u['id'],$agreementId);
					} else if (!empty($u[$a])) {
						$date = new Zend_Date($u[$a . '_timestamp']);
						$gdprUsersAgreements->save(array('user_id' => $u['id'], 'deleted_at'=> null, 'agreement_id' => $agreementId, 'created_at' => $date->get(Zend_Date::ISO_8601)));
						$validAgreements[] = $agreementId;
					}

				}

			}

			if ($this->syncAnonymizedContact($u) != 0) {
				Phc_ErrorLog::error('CRM sync anonymize','Chyba pri odesilani uzivatele s ID'.$u['id']);
			}

			$this->syncCRMContact($u, $validAgreements);

		}

	}


	public function syncAnonymizedContact($u) {

		if (empty($u['anonymized'])) {
			return;
		}

		$lastReplicatedDate = new Zend_Date($u['last_replicated'],Zend_Date::ISO_8601);
		$anomymizedDate = new Zend_Date($u['anonymized'],Zend_Date::ISO_8601);

		if ($anomymizedDate > $lastReplicatedDate) {

			$request = array(
				"query" => 'mutation {anonymizeContact(appUserId:"'.$u['id'].'", sourceId:'.$this->sourceId.'){code,message}}'
			);

			$response = $this->getResponse($request);

			return $response->data->anonymizeContact->code;

		}

	}

	/**
	 * Provede deleteAgreement v CRM, odstrani souhlas v CRM
	 *
	 * @param $userId
	 * @param $agreementId
	 * @throws Exception
	 * @throws Zend_Exception
	 * @throws Zend_Http_Client_Exception
	 */
	public function removeCRMAgreement($userId, $agreementId) {

		$request = array(
			"query" => 'mutation {deleteAgreement(appUserId:"'.$userId.'", sourceId:'.$this->sourceId.', agreementId:'.$agreementId.'){id}}'
		);

		$this->getResponse($request);
	}


	/**
	 * Provede addOrUpdateContact do CRM, vlozi nebo aktualizuje uzivatele se souhlasi v CRM
	 * @param $user data uzivatele
	 * @param $agreements array pole s id souhlasu
	 * @throws Zend_Http_Client_Exception
	 */
	public function syncCRMContact($user, $agreements) {

		if(empty($user['anonymized'])) {

			$input = array();

			$input[] = 'appUserId: "'.$user['id'].'"';
			$input[] = 'firstName: "'.$user['name'].'"';
			$input[] = 'lastName: "'.$user['surname'].'"';
			$input[] = 'email: "'.$user['email'].'"';

			$input[] = 'sourceId: '.$this->sourceId;

			$input[] = 'agreementIds: ['.implode(', ',$agreements).']';

			if (isset($user['phone'])) {
				$input[] = 'phone: "'.$user['phone'].'"';
			}

			if (isset($user['specialization'])) {
				$input[] = 'specialization: "'.$user['specialization'].'"';
			}

			if (isset($user['profession'])) {
				$input[] = 'profession: "'.$user['profession'].'"';
			}

			$input = implode(', ',$input);

			$request = array(
				"query" => "mutation {addOrUpdateContact($input){id}}"
			);

			$response = $this->getResponse($request);

			if (isset($response->data->addOrUpdateContact->id)) {
				Users::setLastReplicated($user['id']);
			}

		} else {

			Users::setLastReplicated($user['id']);

		}


	}

}
