<?php

class Ibulletin_MailsException extends Exception {}

class Ibulletin_Mails
{
	/**
	 *	Konfigurační nastavení. Musejí tam být hlavně názvy tabulek.
	 */
	private $config;

	/**
	 *	Db handler.
	 */
	private $db;

	/**
	 *	Zend_db_table tabulka emailů.
	 */
	private $mailsTable;

    /**********************************************************
     *  KONSTRUKTORY
     **********************************************************/
	
	/**
	 *	Konstruktor.
	 *
	 *	@param Konfigurační nastavení.
	 *	@param DB handler.
	 */
	public function __construct($config, $db)
	{
		$this->config = $config;
		$this->db = $db;
		$this->mailsTable = new Mails(array('db', $db));
	}

    /**********************************************************
     *  PUBLIC METODY
     **********************************************************/
         
   

/**
 * Vrati seznam emailu
 * @param boolean $deleted  seznam smazanych emailu
 * @param boolean $templates vcetne sablonovych emailu
 * @return Zend_Db_Select
 */

	public function getMailsQuery($deleted = false,$templates = false)
	{            
            $select = $this->db->select()
			    ->from(array('e' => $this->config->tables->emails), array(
                    'id', 'name', 'subject',
                    'special_function' => new Zend_Db_Expr('
                     	(case when special_function is not null then special_function
                        when send_after_registration then \'registration\'
                        when send_after_deregistration then \'deregistration\'
                        else null end)')
                ))
                ->joinLeft(array('iw' => 'invitation_waves'), 'iw.id = e.invitation_id', array('invitation_name' => 'name'));
            
            if (!$templates) {
                $select->where('template is null');
            }
                
            if ($deleted) {   
                $select->where('deleted IS NOT NULL');
            } else {
                $select->where('deleted IS NULL');
            }

            return $select;
	}

    /**
     * Vrátí seznam sablonovych emailů
     *
     * @return Zend_Db_Select
     */



    public function getTemplateMailsQuery($deleted = false)
    {
        $select = $this->db->select()
            ->from(array('e' => $this->config->tables->emails), array(
            'id', 'name', 'subject', 'template',
            'special_function' => new Zend_Db_Expr('
                     	(case when special_function is not null then special_function
                        when send_after_registration then \'registration\'
                        when send_after_deregistration then \'deregistration\'
                        else null end)')
        ))
            ->joinLeft(array('iw' => 'invitation_waves'), 'iw.id = e.invitation_id', array('invitation_name' => 'name'))
            ->where('template is not null'); // email je sablonovy
        
        if ($deleted) {
            $select->where('deleted IS NOT NULL');
        }else {
            $select->where('deleted IS NULL');
        }

        return $select;
    }

	/**
	 *	Vrátí seznam mailů, ale u každého bude ještě informace, zda se daný mail 
	 *	vyskytuje někde v tabulce users_emails nebo users_links_token.
     *  
     *  @param boolean $show_deleted Zobrazit smazané emaily
	 */
	public function getMailsForForm($show_deleted = false)
	{
		$select = $this->db->select()
			->from(array('e' => $this->config->tables->emails), 
			     array('id', 'name', 'subject', 'special_function', 
                       'send_after_deregistration', 'send_after_registration'))
			->joinLeft(array('ue' => $this->config->tables->users_emails),
				'ue.email_id = e.id AND ue.sent IS null AND status IS null',
				array('users_emails' => 'COUNT(ue.id)'))
		    ->joinLeft(array('iw' => 'invitation_waves'), 'iw.id = e.invitation_id', array('invitation_name' => 'name'))
			->group(array('e.id', 'e.name', 'e.subject', 'e.special_function', 
			              'e.send_after_deregistration', 'e.send_after_registration', 'iw.name'))
            ->order('id DESC', 'e.name');
        
        if (!$show_deleted) {
            $select->where("e.deleted IS NULL");
        }

		try
		{
			return $this->db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
		}
		catch (Zend_Db_Statement_Exception $e)
		{
			throw new Ibulletin_MailsException(
				"Nepovedlo se provést SQL dotaz: ".$select->__toString());
		}
	}

	/**
	 *	Najde email a vrátí jeho data.
	 *
	 * 	@param Identifikátor emailu.
	 */
	public function getMailData($id)
	{
		Zend_Loader::loadClass('Ibulletin_Email');

		try
		{
			$result = $this->mailsTable->find($id)->current();
			// deserializace objektu 
			$result->body = Ibulletin_Email::createInstance($result->body);

            // deserializace JSON objektu, pokud neni prazdny (typicky NULL)
            if (isset($result->template_data) && !is_null($result->template_data)) {
                $result->template_data = Zend_Json::decode($result->template_data);
            }

			return $result;
		}
		catch (Exception $e)
		{
			throw new Ibulletin_MailsException(
				"Nepodařilo se získata data emailu s ID: $id, ".$e);
		}
	}

	/**
	 *	Uloží změněný email.
	 *
	 * 	@param Identifikátor emailu.
	 * 	@param Název emailu.
	 * 	@param Identifikátor zvací vlny.
	 * 	@param Předmět emailu.
	 * 	@param Tělo emailu.
	 *	@param Plain text emailu.
	 *	@param Typ specialniho mailu - none/registration/deregistration.
     *  @param Sablona emailu
     *  @param Data k sablone emailu
     *  @param From emailova adresa
     *  @param FromName Jmeno odesilatele
	 *  @return array|FALSE returns errors or FALSE on success
	 */
	public function saveMail($id, $name, $wave, $subject, $body, $plain, 
	                         $special_type, $template = NULL, $template_data = NULL, $from = NULL, $from_name = NULL)
	{
		try
		{
            $config = Zend_Registry::get('config');

			Zend_Loader::loadClass('Ibulletin_Email');
            Zend_Loader::loadClass('Zend_Db_Expr');
			$mail = new Ibulletin_Email();
            $mail->setFrom($from, $from_name);
			$mail->setId($id);
			$mail->setName($name);
			$mail->setSubject($subject);
			$mail->setPlain($plain);
			$mail->setBody($body);
			$errors = $mail->createMimeParts();
            
            if ($body) {
            	try {
                    $mail->detectSpamScore();
                } catch (Exception $e) {
                    //Phc_ErrorLog::error('MailsController', $e);
                    //$errors[] = $e->getMessage();
                }
            }
            
            // Pokud je vlna 0, znamena to, ze se ma vyplnit null
            if($wave == 0 || is_null($wave)){
                $wave = new Zend_Db_Expr('NULL');
            }
            
			$data = array(
				'name' => $name,
				'invitation_id' => $wave,
				'subject' => $subject,
				'body' => Ibulletin_Email::encodeInstance($mail),
			);

            // pokud ukladame s nastavenym parametrem $template,
            // ulozime nazev sablony, sablonove data a hash souboru sablony
            if ($template) {

                // ulozeni nazvu sablony ktera generuje email
                $data['template'] = $template;

                $html_template = rtrim($this->config->paths->mail_template,'\\/') . DIRECTORY_SEPARATOR . $template . '.html';
                $plain_template = rtrim($this->config->paths->mail_template,'\\/') . DIRECTORY_SEPARATOR . $template . '.txt';

                // zahashovani obsahu souboru sablony a ulozeni sablonovych dat (key:value)
                if (file_exists($html_template) && file_exists($plain_template)) {
                    $hash = Utils::getFileChecksum(array($html_template, $plain_template));

                    // serializace template dat - pole do JSON
                    $data['template_data'] = Zend_Json::encode(array(
                        'hash' => $hash,
                        'data' => $template_data ? $template_data : array()
                    ));

                }
                // create destination directory, copy content of template directory over

                // create directory for email content files
                $src = rtrim($config->paths->mail_template,'\//') . DIRECTORY_SEPARATOR . $template;
                $dest = rtrim($config->mailer->imgsaddr, '\//') . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'template-files';

                Utils::mkdir($dest);
                Utils::rcopy($src, $dest);

                $status = is_dir($dest);
                // TODO: exception?
                if (!$status) $errors[] = "Error updating from template '$template'";


            }

    		// Specialni mail
            switch($special_type){
                case 'registration':
                    $data['send_after_registration'] = 'true';
                    $data['send_after_deregistration'] = 'false';
                    $data['special_function'] = new Zend_Db_Expr('null');
                    break;
                case 'deregistration':
                    $data['send_after_registration'] = 'false';
                    $data['send_after_deregistration'] = 'true';
                    $data['special_function'] = new Zend_Db_Expr('null');
                    break;
                case 'forward':
                    $data['special_function'] = 'forward';
                    
                    $data['send_after_registration'] = 'false';
                    $data['send_after_deregistration'] = 'false';
                    break;
                case 'forgotpass':
                    $data['special_function'] = 'forgotpass';
                    
                    $data['send_after_registration'] = 'false';
                    $data['send_after_deregistration'] = 'false';
                    break;
                case 'registrationlogin':
                    $data['special_function'] = 'registrationlogin';
                    
                    $data['send_after_registration'] = 'false';
                    $data['send_after_deregistration'] = 'false';
                    break;
                case 'inrep':
                case 'inrep_resources':
                    $data['special_function'] = $special_type;
                    
                    $data['send_after_registration'] = 'false';
                    $data['send_after_deregistration'] = 'false';
                    break;
                default:
                    $data['send_after_registration'] = 'false';
                    $data['send_after_deregistration'] = 'false';
                    $data['special_function'] = $special_type;
                    break;
            }
			
			$where = $this->mailsTable->getAdapter()->quoteInto('id = ?', $id);
			$this->mailsTable->update($data, $where);
            return $errors;
		}
		catch (Exception $e)
		{
			throw new Ibulletin_MailsException(
				"Nepodařilo se uložit email s ID: $id, ".$e);
		}
	}
	
        /**
         * Odstraní příznaky Inrep resources od emailů
         * 
         * @param type $id - ID mailu s nově přidaným příznaky inRep resources
         * @return null | název emailu
         */
         public function clearInRepResources($id) {

            try {
                $where[] = $this->mailsTable->getAdapter()->quoteInto('special_function = ?', "inrep_resources");
                $where[] = $this->mailsTable->getAdapter()->quoteInto('id != ?', $id);
                $row = $this->mailsTable->fetchRow($where);

                if ($row) {
                    $this->mailsTable->update(array('special_function' => null), $where);
                    return $row["name"];
                }

                return null;
            } catch (Exception $e) {
                throw new Ibulletin_MailsException(
                'Chyba při odebírání příznaku inRep resources. ' . $e->getMessage());
            }
        }

    /**
	 *	Vloží do DB nový email.
	 *
	 * 	@param Název emailu.
     *  @param Volitelny nazev sablony ze ktere se vytvori email.
     *
	 *  @return id vlozeneho emailu
	 */
	public function newMail($name, $template = null)
	{
		try
		{

            $config = Zend_Registry::get('config');

            Zend_Loader::loadClass('Ibulletin_Email');
			$mail = new Ibulletin_Email();
			$mail->setName($name);
            
            //zarazeni mailu do zvaci vlnu
            $bu = new Bulletins();
            $bulid = $bu->getActualBulletinId();
            $iw = new Ibulletin_InvitationWaves();            
            $iwid = $iw->getLastBulletinIw($bulid);

            $data = array();

            $data['name'] = $name;

            if($iwid) {
                $data['invitation_id'] = $iwid;
            }

            // pokud ukladame s nastavenym parametrem $template,
            // ulozime nazev sablony, sablonove data a hash souboru sablony
            if ($template) {

                // ulozeni nazvu sablony ktera generuje email
                $data['template'] = $template;

                $file_html = rtrim($this->config->paths->mail_template,'\\/') . DIRECTORY_SEPARATOR . $template . '.html';
                $file_plain = rtrim($this->config->paths->mail_template,'\\/') . DIRECTORY_SEPARATOR . $template . '.txt';

                // zahashovani obsahu souboru sablony a ulozeni sablonovych dat (key:value)
                if (file_exists($file_html) && file_exists($file_plain)) {
                    $hash = Utils::getFileChecksum(array($file_html, $file_plain));

                    // serializace template dat - pole do JSON
                    $data['template_data'] = Zend_Json::encode(array(
                        'hash' => $hash,
                        'data' => array()
                    ));

                    $mail->setBody(file_get_contents($file_html));
                    $mail->setPlain(file_get_contents($file_plain));

                } else {
                    // TODO: exception?
                    return false;
                }
            }

            $data['body'] = Ibulletin_Email::encodeInstance($mail);

            // store to DB
			$last_insert_id = $this->mailsTable->insert($data);


            if ($template) {
                if (!$last_insert_id) return false;

                // create directory for email content files
                $src = rtrim($config->paths->mail_template,'\//') . DIRECTORY_SEPARATOR . $template;
                $dest = rtrim($config->mailer->imgsaddr, '\//') . DIRECTORY_SEPARATOR . $last_insert_id;

                // create destination mail directory and extra template directory, copy content of template over
                Utils::mkdir($dest . DIRECTORY_SEPARATOR . 'email-files');
                Utils::mkdir($dest . DIRECTORY_SEPARATOR . 'template-files');
                Utils::rcopy($src, $dest . DIRECTORY_SEPARATOR . 'template-files');

                $status = is_dir($dest . DIRECTORY_SEPARATOR . 'email-files') && is_dir($dest . DIRECTORY_SEPARATOR . 'template-files');
                // TODO: exception?
                if (!$status) return false;
            }

            return $last_insert_id;

		}
		catch (Exception $e)
		{
			throw new Ibulletin_MailsException(
				'Nepodařilo se vložit nový email. '.$e->getMessage());
		}
	}
    
    /**
     *  Zduplikuje email v DB. Zaroven prida k nazvu slovo 'kopie' a vynuluje special_function.
     *
     *  @param Identifikátor emailu.
     *  @return array pole chyb
     */
    public function duplicateMail($id)
    {
        try
        {
            Zend_Loader::loadClass('Ibulletin_Email');
            Zend_Loader::loadClass('Ibulletin_FileUploader');
            
            $result = array();
            $mail = $this->getMailData($id);
            
            $name = 'copy of '.$mail->name;
            
            //delsi text nez 100 znaku orizneme
            if (strlen($name) > 100) {
                $name = substr($name, 0, 100);
            }
            
            
            $new_id = $this->newMail($name, $mail->template);

            // pred ulozenim je potreba skopirovat soubory
            $fu = new Ibulletin_FileUploader();            
            $res = $fu->rcopy($id, $new_id);
            if (!$res) $result[] = 'Nepodařilo se skopírovat soubory!';
            
            $errors = $this->saveMail(
                $new_id, 
                $name, 
                $mail->invitation_id, 
                $mail->subject, 
                $mail->body->getBody(), 
                $mail->body->getPlain(), 
                NULL,//$mail->special_function
                $mail->template,
                $mail->template_data['data']
                );
            
            $result = $result + (array)$errors;
                            
            return $result;
                
        }
        catch (Exception $e)
        {
            throw new Ibulletin_MailsException(
                'Nepodařilo se duplikovat nový email. '.$e->getMessage());
        }
    }    

	/**
	 *	Smaže email respektive oznaci email jako smazany
	 *
	 *	@param Identifikátor emailu.
	 *  @return boolean
	 */
	public function deleteMail($id)	{
		try {	       
            
            //$this->db->delete('users_links_tokens', "email_id = $id");
			//$this->db->delete('users_emails', "email_id = $id");
			$where = $this->mailsTable->getAdapter()->quoteInto('id = ?', $id);
			//$aff = $this->mailsTable->delete($where);
            
            $aff = $this->mailsTable->update(array('deleted' => new Zend_Db_Expr('current_timestamp')), $where);
			
			if (!$aff) {
				Phc_ErrorLog::warning('Ibulletin_Mails::deleteMail', sprintf('Nebyl smazán email s id: %s',$id));
				return false;
			}
            
			// smazeme soubory a adresare pro dany email
           // $dir = $this->config->mailer->imgsaddr;
			//$fu = new Ibulletin_FileUploader($id, $dir);
          //  $fu->rrmdir();
			
		} catch (Ibulletin_FileUploader_Exception $e) {
			throw new Ibulletin_MailsException($e->getMessage()); // re-throw
		}

		return true;
	}

	/**
	 *	Vrátí data pro rozbalovací seznam s možnostmi nastavení vztahu mailu k
	 *	právní poradně.
	 */
	public function getConsultingMailsInfo()
	{
		Zend_Loader::loadClass('Ibulletin_Mailer');
		return array(
			Ibulletin_Mailer::CONSULTING_DEFAULT => 'nic',
			Ibulletin_Mailer::CONSULTING_AUTO_REPLY => 'automatická odpověď',
			Ibulletin_Mailer::CONSULTING_REPLY => 'odpověď z poradny'
		);
	}
    
    /**
     * Obnoví emais
     * @param int $id ID emailu
     * @return int The number of affected rows.     
     */
    public function restore($id) {
         $db = Zend_Registry::get('db'); 
        try {
            $where = $db->quoteInto('id = ?', $id);
            $data = array('deleted' => new Zend_Db_Expr('null'));
            return $db->update('emails', $data, $where);
        } catch (Zend_Db_Statement_Exception $e) {
            echo $e->getMessage();
            throw new Zend_Exception('Chyba pri obnoveni emailu' . $e);
        }
    }

}


Zend_Loader::loadClass('Zend_Db_Table_Abstract');

class Mails extends Zend_Db_Table_Abstract
{
	/**
	 *	Název tabulky v databázi.
	 */
	protected $_name = 'emails';

	/**
	 *	Primární klíč.
	 */
	protected $_primary = 'id';
}
?>
