<?php
/**
 * Variable envelope return path (VERP) class for encoding and decoding. 
 * Use getBounceEmails() to detect bounce messages in mailbox.
 *
 */

class Ibulletin_Bounce {

	private static $_inst = null;
	
	private static $_bh = null;
	
	private static $_crypt = null;
	
	private static $_autoflag = true;
	
	private static $_log_directory = null;
	
	private static $_max_message_size = 1000000;
	
	/**
	 * constructor 
	 */
	private function __construct() 
	{
		self::$_inst = $this;
	}
	
	/**
	 * singleton
	 * @return Ibulletin_Bounce
	 */
	static public function getInstance() 
	{
		if (!self::$_inst) {
			self::$_inst = new Ibulletin_Bounce();
		}
		return self::$_inst;
	}
	
	/**
	 * singleton for BounceHandler
	 * 
	 * @return BounceHandler
	 */
	static public function getBounceHandler() {
		if (!self::$_bh) {
			self::$_bh = new Bouncehandler();
		}
		return self::$_bh;
	}
	
	/**
	 * singleton for Ibulletin_Crypt
	 * @return Ibulletin_Crypt
	 */
	static public function getCrypt()
	{
		if (!self::$_crypt) {
			self::$_crypt = new Ibulletin_Crypt();
		}
		return self::$_crypt;
	}
	
	
	static public function setMaxMessageSize($size) {
		self::$_max_message_size = $size;
	}
	
	static public function getMaxMessageSize() {
		return self::$_max_message_size;
	}

	static public function setLogDirectory($size) {
		self::$_log_directory = $size;
	}
	
	static public function getLogDirectory() {
		return self::$_log_directory;
	}
	
	static public function setAutoFlag($bool = TRUE) {
		self::$_autoflag = (boolean) $bool;
	}

	static public function getAutoFlag() {
		return self::$_autoflag;
	}	
	
	/**
	 * Search mailbox for bounce messages
	 * 
	 * @param Zend_Mail_Storage_Abstract $m mail storage object
	 * @param string prefix to process only specific email addresses,
	 * 		  is compared to first value found in metadata
	 * @access public
	 * @static 
	 * @return array delivered_to, sender, recipient, bounce, data
	 */
	static public function getBounceEmails(Zend_Mail_Storage_Abstract $m, $prefix = null, $log = null) {
		
		$log = $log ? $log : self::getLogDirectory();
		
		$result = array();
		
		$m->rewind();		
		while ($m->valid()) {			
			$key = $m->key();
			$msg = $m->current();
				
			$res = array();

			if ((!$msg->hasFlag(Zend_Mail_Storage::FLAG_SEEN) || // unseen OR passed (not processed)
					$msg->hasFlag(Zend_Mail_Storage::FLAG_PASSED)) &&
					!$msg->hasFlag(Zend_Mail_Storage::FLAG_DELETED)) // AND not deleted
			{

				// decode first valid verp email address
				$addresses = array_merge(
                    (array)$msg->getHeader('Delivered-to'),
                    (array)$msg->getHeader('To')
                );
				$parts = false;
				foreach ($addresses as $val) {
					if ($parts = self::verpDecode($val)) {
						break;
					}
				}

				$break = !$parts; // break if email address not found
				if (!$break) {
					//get verp part
					$res['delivered_to'] = $parts[0]; // usually domain mailbox
					$res['sender'] = $parts[1];
					
					// get hash and decrypt metadata [a-zA-Z0-9]
					if (preg_match('/^(.+?)\-(.+)\@(.+)/', $res['sender'], $match)) {
						$res['recipient'] = $match[2] . '@' . $match[3];
                        // metadata contains project and users_emails_id
						$metadata = self::verpDecrypt($match[1]);

						$res['data'] = array_slice($metadata, 1); // decrypted verp identification data

						// prefix matching of first key in metadata
						if (!$res['data'] || ($prefix && !strcmp($prefix, $metadata[0]) == 0)) {
							$break = true;
						}
						
					} else $break = true;
				}

				// if message is too big, store id of mail
				if (!$break && self::$_max_message_size && $msg->getSize() > self::$_max_message_size) {
					$break = true;
				}

				if ($break) {
					// set flag for not processed message
					if (self::getAutoFlag()) {
						$m->setFlags($key,array(Zend_Mail_Storage::FLAG_PASSED));
					}
					$m->next();
					continue;
				}

				// download complete email and run parser on it
				$bounce_result = self::getBounceHandler()->parse_email($m->getRawHeader($key), $m->getRawContent($key));

				if (empty($bounce_result) || !$bounce_result[0]['action']) {
					// set flag for not processed message
					if (self::getAutoFlag()) {
						$m->setFlags($key,array(Zend_Mail_Storage::FLAG_PASSED));
					}
					$m->next();
					continue;
				}

				// we are checking 1 email so only the first row is extracted
				$res['bounce'] = array_shift($bounce_result);
        
				// Save RAW e-mail content
				$raw = $m->getRawContent($key);

				// log the processed message
				if ($log && is_writable($log)) {
					$path = rtrim($log,'/') . '/' . 'bounce_' 
							. implode('_',$res['data']) . '_' . $res['recipient'] . '.eml';
					file_put_contents($path, $raw);
				}

				unset($raw);

				// append to result
				$result[$key] = $res;
			}
						
			$m->next();
		}
		
		return $result;
	}	

	/**
	 * Encode the sender's address with the VERPed recipient.
	 *
	 * @param string $sender    address of the sender
	 * @param string $recipient address of the recipient
	 * @return string           VERP encoded address
	 * @access public
	 * @static
	 */
	static public function verpEncode($sender, $recipient) {
	
		if (preg_match('/(.+)\@([^\@]+)$/', $sender, $match)) {
			$slocal  = self::_arrayValue(1, $match);
			$sdomain = self::_arrayValue(2, $match);
		} else return false;
	
		if (preg_match('/(.+)\@([^\@]+)$/', $recipient, $match)) {
			$rlocal  = self::_arrayValue(1, $match);
			$rdomain = self::_arrayValue(2, $match);
		} else return false;
	
		return "$slocal+$rlocal=$rdomain@$sdomain";
	}
	
	/**
	* Decode the address and return the sender and recipient as an array
	*
	* @param string $address   address to be decoded
	* @return array            tuple ($sender, $recipient)
	* @access public
	* @static
	*/
	static public function verpDecode($address) {
	
	$address = preg_replace(array('/.*</','/>.*/'),'',$address);
	
			if (preg_match('/^(.+)\+([^=]+)\=([^@]+)\@(.+)$/', $address, $match)) {
	
			$slocal  = self::_arrayValue(1, $match);
			$rlocal  = self::_arrayValue(2, $match);
			$rdomain = self::_arrayValue(3, $match);
			$sdomain = self::_arrayValue(4, $match);
	
			return array( "$slocal@$sdomain", "$rlocal@$rdomain");
	}
	return false;
	}
	
	
	static private function _arrayValue($key, $list, $default = null) 
	{
		if (is_array($list)) {
			return $rdomain = array_key_exists($key, $list) ? $list[$key] : $default;
		}
		return $default;
	}	

	/**
	 * Encrypts array into crypted part of the verp email address (after + before -)
	 * WARNING: data should NOT contain any NUL (\x00) characters, it is reserved for crypt function padding
	 *
	 * @return string
	 * @param array $data
	 */	
	static public function verpEncrypt(array $data)
	{
		$string = implode(',', $data);
		return bin2hex(self::getCrypt()->encrypt($string));		
	}	

	/**
	 * Decrypts crypted part of the verp email address into array
     * parameter must be hexadecimal string data
	 *
	 * @return string
	 * @param string $data
	 */	
	
	static public function verpDecrypt($string) 
	{
        $decryptedText = self::getCrypt()->decrypt(hex2bin($string));
		// filter from first NUL \x00 character found to end of string
		$decryptedText = preg_replace('/\x00.*$/','',$decryptedText);
		$data = explode(',', $decryptedText);
		
		return $data;
	}


}/** END class Ibulletin_Bounce **/

class BounceHandler{

	/**** VARS ****************************************************************/
	public $head_hash = array();
	public $fbl_hash = array();
	public $body_hash = array(); // not necessary

	public $looks_like_a_bounce = false;
	public $looks_like_an_FBL = false;
	public $is_hotmail_fbl = false;

	// these are for feedback reports, so you can extract uids from the emails
	// eg X-my-custom-header: userId12345
	// eg <img src="http://mysite.com/track.php?u=userId12345">
	public $web_beacon_preg_1 = "";
	public $web_beacon_preg_2 = "";
	public $x_header_search_1 = "";
	public $x_header_search_2 = "";

	// accessors
	public $type = "";
	public $web_beacon_1 = "";
	public $web_beacon_2 = "";
	public $feedback_type = "";
	public $x_header_beacon_1 = "";
	public $x_header_beacon_2 = "";

	// these accessors are useful only for FBL's
	// or if the output array has only one index
	public $action = "";
	public $status = "";
	public $subject = "";
	public $recipient = "";

	// the raw data set, a multiArray
	public $output = array();


	/**** INSTANTIATION *******************************************************/
	public function __construct(){
		$this->output[0]['action']  = "";
		$this->output[0]['status']  = "";
		$this->output[0]['recipient'] = "";
	}


	/**** METHODS *************************************************************/
	// this is the most commonly used public method
	// quick and dirty
	// useage: $multiArray = $this->get_the_facts($strEmail);
	public function parse_email($eml_header, $eml_body = null){
		if (is_null($eml_body)) {
            return $this->get_the_facts($eml_header);
        } else {
            return $this->get_the_facts($eml_header . "\r\n\r\n" . $eml_body);
        }
	}
	public function get_the_facts($eml){
		// fluff up the email
		$bounce = $this->init_bouncehandler($eml);
		list($head, $body) = preg_split("/\r\n\r\n/", $bounce, 2);
		$this->head_hash = $this->parse_head($head);

		// parse the email into data structures
		$boundary = isset($this->head_hash['Content-type']['boundary']) ? $this->head_hash['Content-type']['boundary']: null;
		$mime_sections = $this->parse_body_into_mime_sections($body, $boundary);
		$this->body_hash = explode("\r\n", $body);
		$this->first_body_hash = isset($mime_sections['first_body_part']) ? $this->parse_head($mime_sections['first_body_part']) : array();

		$this->looks_like_a_bounce = $this->is_a_bounce();
		$this->looks_like_an_FBL = $this->is_an_ARF();

		//if(!$this->looks_like_a_bounce && !$this->looks_like_an_FBL)
		//    return "unknown"; // not good if email is badly formatted, which is TOO OFTEN!


		/*** now we try all our weird text parsing methods ****************************************/

		// is it a Feedback Loop, in Abuse Feedback Reporting Format (ARF)?
		// http://en.wikipedia.org/wiki/Abuse_Reporting_Format#Abuse_Feedback_Reporting_Format_.28ARF.29
		if($this->looks_like_an_FBL){
			$this->output[0]['action'] = 'failed';
			$this->output[0]['status'] = "5.7.1";
			$this->subject = trim(str_ireplace("Fw:", "", $this->head_hash['Subject']));

			if($this->is_hotmail_fbl === true){
				$this->output[0]['recipient'] = isset($this->head_hash['X-hmxmroriginalrecipient']) ? $this->head_hash['X-hmxmroriginalrecipient'] : '';
				if (! $this->output[0]['recipient']) {
					$this->output[0]['recipient'] = isset($this->first_body_hash['X-hmxmroriginalrecipient']) ? $this->first_body_hash['X-hmxmroriginalrecipient'] : '';
				}
			}
			else{
				$fbl = $this->standard_parser($mime_sections['machine_parsable_body_part']);
				$this->output[0]['recipient'] = $this->find_fbl_recipients($fbl);
				$this->fbl_hash = $fbl;
			}
		}
		else if (preg_match("/auto.{0,20}reply|vacation|(out|away|on holiday).*office/i", $this->head_hash['Subject'])
            || $this->find_x_header('X-Autoreply')
            || ($this->find_x_header('Auto-submitted') && ($this->find_x_header('Auto-submitted') == 'auto-replied')) ){ // gmail vacation bounces
			// looks like a vacation autoreply, ignoring
			$this->output[0]['action'] = 'autoreply';
		}
		else if ($this->is_RFC1892_multipart_report() === TRUE){
			$rpt_hash = $this->parse_machine_parsable_body_part($mime_sections['machine_parsable_body_part']);			
			if (isset($rpt_hash['per_recipient'])){
				for($i=0; $i<count($rpt_hash['per_recipient']); $i++){
					$this->output[$i]['recipient'] = $this->find_recipient($rpt_hash['per_recipient'][$i]);
					if (isset($rpt_hash['per_recipient'][$i]['Status'])) {
						$mycode = $this->format_status_code($rpt_hash['per_recipient'][$i]['Status']);
						$this->output[$i]['status'] = $mycode['code'];
					} else {
						$this->output[$i]['status'] = $this->get_status_code_from_text($this->output[$i]['recipient'],0);
					}
					$this->output[$i]['action'] = $rpt_hash['per_recipient'][$i]['Action'];
				}
			}
		}
		else if(isset($this->head_hash['X-failed-recipients'])) {
			//  Busted Exim MTA
			//  Up to 50 email addresses can be listed on each header.
			//  There can be multiple X-Failed-Recipients: headers. - (not supported)
			$arrFailed = explode(',', $this->head_hash['X-failed-recipients']);
			for($j=0; $j<count($arrFailed); $j++){
				$this->output[$j]['recipient'] = trim($arrFailed[$j]);
				$this->output[$j]['status'] = $this->get_status_code_from_text($this->output[$j]['recipient'],0);
				$this->output[$j]['action'] = $this->get_action_from_status_code($this->output[$j]['status']);
			}
		}
		else if(!empty($boundary) && $this->looks_like_a_bounce){
			// oh god it could be anything, but at least it has mime parts, so let's try anyway
			$arrFailed = $this->find_email_addresses($mime_sections['first_body_part']);
			for($j=0; $j<count($arrFailed); $j++){
				$this->output[$j]['recipient'] = trim($arrFailed[$j]);
				$this->output[$j]['status'] = $this->get_status_code_from_text($this->output[$j]['recipient'],0);
				$this->output[$j]['action'] = $this->get_action_from_status_code($this->output[$j]['status']);
			}
		}
		else if($this->looks_like_a_bounce){
			// last ditch attempt
			// could possibly produce erroneous output, or be very resource consuming,
			// so be careful.  You should comment out this section if you are very concerned
			// about 100% accuracy or if you want very fast performance.
			// Leave it turned on if you know that all messages to be analyzed are bounces.
			$arrFailed = $this->find_email_addresses($body);
			for($j=0; $j<count($arrFailed); $j++){
				$this->output[$j]['recipient'] = trim($arrFailed[$j]);
				$this->output[$j]['status'] = $this->get_status_code_from_text($this->output[$j]['recipient'],0);
				$this->output[$j]['action'] = $this->get_action_from_status_code($this->output[$j]['status']);
			}
		}
		// else if()..... add a parser for your busted-ass MTA here


		// accessors
		/*if it is an FBL, you could use the class variables to access the
		 data (Unlike Multipart-reports, FBL's report only one bounce)
		*/
		$this->type = $this->find_type();
		$this->action = $this->output[0]['action'];
		$this->status = $this->output[0]['status'];
		$this->subject = ($this->subject) ? $this->subject : $this->head_hash['Subject'];
		$this->recipient = $this->output[0]['recipient'];
		$this->feedback_type = isset($this->fbl_hash['Feedback-type']) ? $this->fbl_hash['Feedback-type'] : "";

		// sniff out any web beacons
		if($this->web_beacon_preg_1)
			$this->web_beacon_1 = $this->find_web_beacon($body, $this->web_beacon_preg_1);
		if($this->web_beacon_preg_2)
			$this->web_beacon_2 = $this->find_web_beacon($body, $this->web_beacon_preg_2);
		if($this->x_header_search_1)
			$this->x_header_beacon_1 = $this->find_x_header  ($this->x_header_search_1);
		if($this->x_header_search_2)
			$this->x_header_beacon_2 = $this->find_x_header  ($this->x_header_search_2);

		return $this->output;
	}



	function init_bouncehandler($blob, $format='string'){
		//if($format=='xml_array'){
		//    $strEmail = "";
		//    $out = "";
		//    for($i=0; $i<$blob; $i++){
		//        $out = preg_replace("/<HEADER>/i", "", $blob[$i]);
		//        $out = preg_replace("/</HEADER>/i", "", $out);
		//        $out = preg_replace("/<MESSAGE>/i", "", $out);
		//        $out = preg_replace("/</MESSAGE>/i", "", $out);
		//        $out = rtrim($out) . "\r\n";
		//        $strEmail .= $out;
		//    }
		//}
		//else if($format=='string'){

		$strEmail = str_replace("\r\n", "\n", $blob);    // line returns 1
		$strEmail = str_replace("\n", "\r\n", $strEmail);// line returns 2
		$strEmail = str_replace("=\r\n", "", $strEmail); // remove MIME line breaks
		$strEmail = str_replace("=3D", "=", $strEmail);  // equals sign =
		$strEmail = str_replace("=09", "  ", $strEmail); // tabs

		//}
		//else if($format=='array'){
		//    $strEmail = "";
		//    for($i=0; $i<$blob; $i++){
		//        $strEmail .= rtrim($blob[$i]) . "\r\n";
		//    }
		//}

		return $strEmail;
	}

	// general purpose recursive heuristic function
	// to try to extract useful info from the bounces produced by busted MTAs
	function get_status_code_from_text($recipient, $index){
		for($i=$index; $i<count($this->body_hash); $i++){
			$line = trim($this->body_hash[$i]);
			
			/******** recurse into the email if you find the recipient ********/
			if($recipient && (stristr($line, $recipient)!==FALSE)) {
				// the status code MIGHT be in the next few lines after the recipient line,
				// depending on the message from the foreign host... What a laugh riot!

				$status_code = $this->get_status_code_from_text($recipient, $i+1);
				if($status_code){
					return $status_code;
				}

			}

			/******** exit conditions ********/
			// if it's the end of the human readable part in this stupid bounce
			if(stristr($line, '------ This is a copy of the message')!==FALSE){
				return '';
			}
			
			//if we see an email address other than our current recipient's,
			/*if(count($this->find_email_addresses($line))>=1
					&& stristr($line, $recipient)===FALSE
					&& strstr($line, 'FROM:<')===FALSE){ // Kanon added this line because Hotmail puts the e-mail address too soon and there actually is error message stuff after it.
				//return '';
			}*/
			/******** pattern matching ********/
			if(    stristr($line, 'no such address')!==FALSE
					||  stristr($line, 'Recipient address rejected')!==FALSE
					||  stristr($line, 'User unknown in virtual alias table')!==FALSE){
				return  '5.1.1';
			}
			else if(stristr($line, 'unrouteable mail domain')!==FALSE
					|| stristr($line, 'Esta casilla ha expirado por falta de uso')!==FALSE){
				return  '5.1.2';
			}
			else if(stristr($line, 'mailbox is full')!==FALSE
					||  stristr($line, 'Mailbox quota usage exceeded')!==FALSE
					||  stristr($line, 'User mailbox exceeds allowed size')!==FALSE){
				return  '4.2.2';
			}
			else if(stristr($line, 'not yet been delivered')!==FALSE){
				return  '4.2.0';
			}
			else if(stristr($line, 'mailbox unavailable')!==FALSE){
				return  '5.2.0';
			}
			else if(stristr($line, 'Unrouteable address')!==FALSE){
				return  '5.4.4';
			}
			else if(stristr($line, 'retry timeout exceeded')!==FALSE){
				return  '4.4.7';
			}
			else if(stristr($line, 'The account or domain may not exist, they may be blacklisted, or missing the proper dns entries.')!==FALSE){ // Kanon added
				return  '5.2.0'; // I guess.... seems like 5.1.1, 5.1.2, or 5.4.4 would fit too, but 5.2.0 seemed most generic
			}
			else if(stristr($line, '554 TRANSACTION FAILED')!==FALSE){ // Kanon added
				return  '5.5.4'; // I think this should be 5.7.1. "SMTP error from remote mail server after end of data: ... (HVU:B1) http://postmaster.info.aol.com/errors/554hvub1.html" -- AOL rejects messages that have links to certain sites in them.
			}
			else if(stristr($line, 'Status: 4.4.1')!==FALSE
					|| stristr($line, 'delivery temporarily suspended')!==FALSE){ // Kanon added
				return  '4.4.1';
			}
			else if(stristr($line, '550 OU-002')!==FALSE
					|| stristr($line, 'Mail rejected by Windows Live Hotmail for policy reasons')!==FALSE){ // Kanon added
				return  '5.5.0'; // Again, why isn't this 5.7.1 instead?
			}
			else if(stristr($line, 'PERM_FAILURE: DNS Error: Domain name not found')!==FALSE){ // Kanon added
				return  '5.1.2'; // Not sure if this is right code. Just copied from above.
			}
			else if(stristr($line, 'Delivery attempts will continue to be made for')!==FALSE){ // Kanon added. From Symantec_AntiVirus_for_SMTP_Gateways@uqam.ca
				return  '4.2.0'; // I'm not sure why Symantec delayed this message, but x.2.x means something to do with the mailbox, which seemed appropriate. x.5.x (protocol) or x.7.x (security) also seem possibly appropriate. It seems a lot of times it's x.5.x when it seems to me it should be x.7.x, so maybe x.5.x is standard when mail is rejected due to spam-like characteristics instead of x.7.x like I think it should be.
			}
			else if(stristr($line, '554 delivery error:')!==FALSE){
				return  '5.5.4'; // rogers.com
			}
			else if(strstr ($line, '550-5.1.1')!==FALSE
					|| stristr($line, 'This Gmail user does not exist.')!==FALSE){ // Kanon added
				return  '5.1.1'; // Or should it be 5.5.0?
			}
			else{
				// end strstr tests
			}
			
			
			// rfc1893 return code
			if(preg_match('/([245]\.[01234567]\.[012345678])/', $line, $matches)){
				if(stripos($line, 'Message-ID')!==FALSE){
					break;
				}
				$mycode = str_replace('.', '', $matches[1]);
				$mycode = $this->format_status_code($mycode);
				return implode('.', $mycode['code']);
			}

			// search for RFC821 return code
			// thanks to mark.tolman@gmail.com
			// Maybe at some point it should have it's own place within the main parsing scheme (at line 88)
			if(preg_match('/\]?: ([45][01257][012345]) /', $line, $matches)
					|| preg_match('/^([45][01257][012345]) (?:.*?)(?:denied|inactive|deactivated|rejected|disabled|unknown|no such|not (?:our|activated|a valid))+/i', $line, $matches))
			{
				$mycode = $matches[1];
				// map common codes to new rfc values
				if($mycode == '450' || $mycode == '550' || $mycode == '551' || $mycode == '554'){
					$mycode = '511';
				} else if($mycode == '452' || $mycode == '552'){
					$mycode = '422';
				} else if ($mycode == '421'){
					$mycode = '432';
				}
				$mycode = $this->format_status_code($mycode);
				return implode('.', $mycode['code']);
			}

		}
		return '';
	}

	function is_RFC1892_multipart_report(){
		return isset($this->head_hash['Content-type']['type']) && $this->head_hash['Content-type']['type'] == 'multipart/report' &&  
			isset($this->head_hash['Content-type']['report-type']) && $this->head_hash['Content-type']['report-type'] == 'delivery-status' && 
			isset($this->head_hash['Content-type']['boundary']) && $this->head_hash['Content-type']['boundary'] !== '';
	}

	function parse_head($headers){
		if(!is_array($headers)) $headers = explode("\r\n", $headers);
		$hash = $this->standard_parser($headers);
		// get a little more complex
		if (isset($hash['Received'])) {
			$arrRec = explode('|', $hash['Received']);
			$hash['Received']= $arrRec;
		}
		
		if(isset($hash['Content-type'])){//preg_match('/Multipart\/Report/i', $hash['Content-type'])){
			$multipart_report = explode (';', $hash['Content-type']);
			$hash['Content-type']='';
			$hash['Content-type']['type'] = strtolower($multipart_report[0]);			
			foreach($multipart_report as $mr){
				if(preg_match('/([^=.]*?)=(.*)/i', $mr, $matches)){					
					// didn't work when the content-type boundary ID contained an equal sign,
					// that exists in bounces from many Exchange servers
					//if(preg_match('/([a-z]*)=(.*)?/i', $mr, $matches)){
					$hash['Content-type'][strtolower(trim($matches[1]))]= str_replace('"','',$matches[2]);
				}
			}
		}
		return $hash;
	}

	function parse_body_into_mime_sections($body, $boundary){
		if(!$boundary) return array();
		if(is_array($body)) $body = implode("\r\n", $body);
		$body = explode($boundary, $body);
		$mime_sections['first_body_part'] = isset($body[1]) ? $body[1] : $body;
		$mime_sections['machine_parsable_body_part'] = isset($body[2]) ? $body[2] : '';
		$mime_sections['returned_message_body_part'] = isset($body[3]) ? $body[3] : '';
		return $mime_sections;
	}


	function standard_parser($content){ // associative array orstr
		// receives email head as array of lines
		// simple parse (Entity: value\n)
		$entity = '';
		$hash = array();
		if(!is_array($content)) $content = explode("\r\n", $content);
		foreach($content as $line){
			if(preg_match('/([^\s.]*):\s(.*)/', $line, $array)){
				$entity = ucfirst(strtolower($array[1]));
				if(empty($hash[$entity])){
					$hash[$entity] = trim($array[2]);
				}
				else if(isset($hash['Received'])){
					// grab extra Received headers :(
					// pile it on with pipe delimiters,
					// oh well, SMTP is broken in this way
					if ($entity and $array[2] and $array[2] != $hash[$entity]){
						$hash[$entity] .= "|" . trim($array[2]);
					}
				}
			}
			else{
				
				if ($entity){
					$hash[$entity] .= " $line";
				}
			}
		}
		
		return $hash;
	}

	function parse_machine_parsable_body_part($str){
		//Per-Message DSN fields
		$hash = $this->parse_dsn_fields($str);
		$hash['mime_header'] = $this->standard_parser($hash['mime_header']);
		$hash['per_message'] = isset($hash['per_message']) ? $this->standard_parser($hash['per_message']) : array();
		if(isset($hash['per_message']['X-postfix-sender'])){
			$arr = explode (';', $hash['per_message']['X-postfix-sender']);
			$hash['per_message']['X-postfix-sender']='';
			$hash['per_message']['X-postfix-sender']['type'] = trim($arr[0]);
			$hash['per_message']['X-postfix-sender']['addr'] = trim($arr[1]);
		}
		if(isset($hash['per_message']['Reporting-mta'])){
			$arr = explode (';', $hash['per_message']['Reporting-mta']);
			$hash['per_message']['Reporting-mta']='';
			$hash['per_message']['Reporting-mta']['type'] = trim($arr[0]);
			$hash['per_message']['Reporting-mta']['addr'] = trim($arr[1]);
		}
		//Per-Recipient DSN fields
		if (isset($hash['per_recipient'])) {
			for($i=0; $i<count($hash['per_recipient']); $i++){
				$temp = $this->standard_parser(explode("\r\n", $hash['per_recipient'][$i]));
				if (isset($temp['Final-recipient'])) {
					$arr = explode (';', $temp['Final-recipient']);
					$temp['Final-recipient'] = $this->format_final_recipient_array($arr);
					//$temp['Final-recipient']['type'] = trim($arr[0]);
					//$temp['Final-recipient']['addr'] = trim($arr[1]);
				}
				if (isset($temp['Original-recipient'])) {
					$arr = explode (';', $temp['Original-recipient']);
					$temp['Original-recipient']='';
					$temp['Original-recipient']['type'] = trim($arr[0]);
					$temp['Original-recipient']['addr'] = trim($arr[1]);
				}
				if (isset($temp['Diagnostic-code'])) {
					$arr = explode (';', $temp['Diagnostic-code']);
					$temp['Diagnostic-code']='';
					$temp['Diagnostic-code']['type'] = trim($arr[0]);
					$temp['Diagnostic-code']['text'] = trim($arr[1]);
						
					// now this is wierd: plenty of times you see the status code is a permanent failure,
					// but the diagnostic code is a temporary failure.  So we will assert the most general
					// temporary failure in this case.
					$ddc=''; $judgement='';
					$ddc = $this->decode_diagnostic_code($temp['Diagnostic-code']['text']);
					$judgement = $this->get_action_from_status_code($ddc);
					if($judgement == 'transient'){
						if(stristr($temp['Action'],'failed')!==FALSE){
							$temp['Action']='transient';
							$temp['Status']='4.3.0';
						}
					}
				}
					
				$hash['per_recipient'][$i]='';
				$hash['per_recipient'][$i]=$temp;
			}
		}
		return $hash;
	}

	function get_head_from_returned_message_body_part($mime_sections){
		$temp = explode("\r\n\r\n", $mime_sections[returned_message_body_part]);
		$head = $this->standard_parser($temp[1]);
		$head['From'] = $this->extract_address($head['From']);
		$head['To'] = $this->extract_address($head['To']);
		return $head;
	}

	function extract_address($str){
		$from_stuff = preg_split('/[ \"\'\<\>:\(\)\[\]]/', $str);
		foreach ($from_stuff as $things){
			if (strpos($things, '@')!==FALSE){
				$from = $things;
			}
		}
		return $from;
	}

	function find_recipient($per_rcpt){
		if(isset($per_rcpt['Original-recipient']['addr']) && $per_rcpt['Original-recipient']['addr'] !== ''){
			$recipient = $per_rcpt['Original-recipient']['addr'];
		}
		else if(isset($per_rcpt['Final-recipient']['addr']) && $per_rcpt['Final-recipient']['addr'] !== ''){
			$recipient = $per_rcpt['Final-recipient']['addr'];
		}
		$recipient = $this->strip_angle_brackets($recipient);
		return $recipient;
	}

	function find_type(){
		if($this->looks_like_a_bounce)
			return "bounce";
		else if($this->looks_like_an_FBL)
			return "fbl";
		else
			return false;
	}

	function parse_dsn_fields($dsn_fields){
		if(!is_array($dsn_fields)) $dsn_fields = explode("\r\n\r\n", $dsn_fields);
		$j = 0;
		reset($dsn_fields);
		for($i=0; $i<count($dsn_fields); $i++){
			$dsn_fields[$i] = trim($dsn_fields[$i]);
			if($i==0)
				$hash['mime_header'] = $dsn_fields[0];
			elseif($i==1 && !preg_match('/(Final|Original)-Recipient/',$dsn_fields[1])) {
				// some mta's don't output the per_message part, which means
				// the second element in the array should really be
				// per_recipient - test with Final-Recipient - which should always
				// indicate that the part is a per_recipient part
				$hash['per_message'] = $dsn_fields[1];
			}
			else {
				if($dsn_fields[$i] == '--') continue;
				$hash['per_recipient'][$j] = $dsn_fields[$i];
				$j++;
			}
		}
		return $hash;
	}

	function format_status_code($code){
		$ret = array('code' => NULL, 'text' => NULL);
		if(preg_match('/([245]\.[01234567]\.[012345678])(.*)/', $code, $matches)){
			$ret['code'] = $matches[1];
			$ret['text'] = $matches[2];
		}
		else if(preg_match('/([245][01234567][012345678])(.*)/', $code, $matches)){
			preg_match_all("/./", $matches[1], $out);
			$ret['code'] = $out[0];
			$ret['text'] = $matches[2];
		}
		return $ret;
	}

	function fetch_status_messages($code){
		$ret = $this->format_status_code($code);
		$arr = explode('.', $ret['code']);
		/*
		 $str = "<P><B>". $status_code_classes[$arr[0]]['title'] . "</B> - " .$status_code_classes[$arr[0]]['descr']. "  <B>". $status_code_subclasses[$arr[1].".".$arr[2]]['title'] . "</B> - " .$status_code_subclasses[$arr[1].".".$arr[2]]['descr']. "</P>";
		return $str;
		*/
		$status_code_classes = BounceHandlerStatusCodes::getClasses();
		$status_code_subclasses = BounceHandlerStatusCodes::getSubClasses();
		return array($status_code_classes[$arr[0]], $status_code_subclasses[$arr[1].'.'.$arr[2]]);
	}

	function get_action_from_status_code($code){
		if($code=='') return '';
		$ret = $this->format_status_code($code);
		$stat = $ret['code'][0];
		switch($stat){
			case(2):
				return 'success';
				break;
			case(4):
				return 'transient';
				break;
			case(5):
				return 'failed';
				break;
			default:
				return '';
				break;
		}
	}

	function decode_diagnostic_code($dcode){
		if(preg_match("/(\d\.\d\.\d)\s/", $dcode, $array)){
			return $array[1];
		}
		else if(preg_match("/(\d\d\d)\s/", $dcode, $array)){
			return $array[1];
		}
	}

	function is_a_bounce(){		
		if(isset($this->head_hash['Subject']) && preg_match("/(nedoručitelná: |zprávu nelze doručit: |mail delivery failed|failure notice|warning: message|delivery status notif|delivery failure|delivery problem|spam eater|returned mail|undeliverable|returned mail|delivery errors|mail status report|mail system error|failure delivery|delivery notification|delivery has failed|undelivered mail|returned email|returning message to sender|returned to sender|message delayed|mdaemon notification|mailserver notification|mail delivery system|nondeliverable mail|mail transaction failed)|auto.{0,20}reply|vacation|(out|away|on holiday).*office/i", $this->head_hash['Subject'])) return true;

		if(isset($this->head_hash['Precedence']) && preg_match('/auto_reply/',$this->head_hash['Precedence'])) return true;

		if(isset($this->head_hash['From']) && preg_match("/^(postmaster|mailer-daemon)\@?/i", $this->head_hash['From'])) return true;
		return false;
	}

	function find_email_addresses($first_body_part){
		// not finished yet.  This finds only one address.
		if(preg_match("/\b([A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4})\b/i", $first_body_part, $matches)){
			return array($matches[1]);
		}
		else
			return array();
	}


	// these functions are for feedback loops
	function is_an_ARF(){
		if(isset($this->head_hash['Content-type']['report-type']) && preg_match('/feedback-report/',$this->head_hash['Content-type']['report-type'])) return true;
		if(isset($this->head_hash['X-loop']) && preg_match('/scomp/',$this->head_hash['X-loop'])) return true;
		if(isset($this->head_hash['X-hmxmroriginalrecipient']) || isset($this->first_body_hash['X-hmxmroriginalrecipient']) )  {
			$this->is_hotmail_fbl = true;
			return true;
		}
		return false;
	}

	// use a perl regular expression to find the web beacon
	public function find_web_beacon($body, $preg){
		if(!isset($preg) || !$preg)
			return "";
		if(preg_match($preg, $body, $matches))
			return $matches[1];
	}

	public function find_x_header($xheader){
		$xheader = ucfirst(strtolower($xheader));
		// check the header
		if(isset($this->head_hash[$xheader])){
			return $this->head_hash[$xheader];
		}
		// check the body too
		$tmp_body_hash = $this->standard_parser($this->body_hash);
		if(isset($tmp_body_hash[$xheader])){
			return $tmp_body_hash[$xheader];
		}
		return "";
	}

	private function find_fbl_recipients($fbl){
		if(isset($fbl['Original-rcpt-to'])){
			return $fbl['Original-rcpt-to'];
		}
		else if(isset($fbl['Removal-recipient'])){
			return trim(str_replace('--', '', $fbl['Removal-recipient']));
		}
		//else if(){
		//}
		//else {
		//}
	}

	private function strip_angle_brackets($recipient){
		$recipient = str_replace('<', '', $recipient);
		$recipient = str_replace('>', '', $recipient);
		return $recipient;
	}


	/*The syntax of the final-recipient field is as follows:
	 "Final-Recipient" ":" address-type ";" generic-address
	*/
	private function format_final_recipient_array($arr){
		$output = array('addr'=>'',
				'type'=>'');
		if(strpos($arr[0], '@')!==FALSE){
			$output['addr'] = $this->strip_angle_brackets($arr[0]);
			$output['type'] = (!empty($arr[1])) ? trim($arr[1]) : 'unknown';
		}
		else{
			$output['type'] = trim($arr[0]);
			$output['addr'] = $this->strip_angle_brackets($arr[1]);
		}
		return $output;
	}
}/** END class BounceHandler **/


class BounceHandlerStatusCodes {

	static public function getClasses() {
		$status_code_classes['2']['title'] =  "Success";
		$status_code_classes['2']['descr'] =  "Success specifies that the DSN is reporting a positive delivery action.  Detail sub-codes may provide notification of transformations required for delivery.";

		$status_code_classes['4']['title'] =  "Persistent Transient Failure";
		$status_code_classes['4']['descr'] =  "A persistent transient failure is one in which the message as sent is valid, but some temporary event prevents the successful sending of the message.  Sending in the future may be successful.";

		$status_code_classes['5']['title'] =  "Permanent Failure";
		$status_code_classes['5']['descr'] =  "A permanent failure is one which is not likely to be resolved by resending the message in the current form.  Some change to the message or the destination must be made for successful delivery.";
		return $status_code_classes;
	}

	static public function getSubClasses() {
		$status_code_subclasses['0.0']['title'] =  "Other undefined Status";
		$status_code_subclasses['0.0']['descr'] =  "Other undefined status is the only undefined error code. It should be used for all errors for which only the class of the error is known.";

		$status_code_subclasses['1.0']['title'] =  "Other address status";
		$status_code_subclasses['1.0']['descr'] =  "Something about the address specified in the message caused this DSN.";

		$status_code_subclasses['1.1']['title'] =  "Bad destination mailbox address";
		$status_code_subclasses['1.1']['descr'] =  "The mailbox specified in the address does not exist.  For Internet mail names, this means the address portion to the left of the @ sign is invalid.  This code is only useful for permanent failures.";
		 
		$status_code_subclasses['1.2']['title'] =  "Bad destination system address";
		$status_code_subclasses['1.2']['descr'] =  "The destination system specified in the address does not exist or is incapable of accepting mail.  For Internet mail names, this means the address portion to the right of the @ is invalid for mail.  This codes is only useful for permanent failures.";

		$status_code_subclasses['1.3']['title'] = "Bad destination mailbox address syntax";
		$status_code_subclasses['1.3']['descr'] =  "The destination address was syntactically invalid.  This can apply to any field in the address.  This code is only useful for permanent failures.";

		$status_code_subclasses['1.4']['title'] = "Destination mailbox address ambiguous";
		$status_code_subclasses['1.4']['descr'] =  "The mailbox address as specified matches one or more recipients on the destination system.  This may result if a heuristic address mapping algorithm is used to map the specified address to a local mailbox name.";

		$status_code_subclasses['1.5']['title'] = "Destination address valid";
		$status_code_subclasses['1.5']['descr'] =  "This mailbox address as specified was valid.  This status code should be used for positive delivery reports.";

		$status_code_subclasses['1.6']['title'] = "Destination mailbox has moved, No forwarding address";
		$status_code_subclasses['1.6']['descr'] =  "The mailbox address provided was at one time valid, but mail is no longer being accepted for that address.  This code is only useful for permanent failures.";

		$status_code_subclasses['1.7']['title'] = "Bad sender's mailbox address syntax";
		$status_code_subclasses['1.7']['descr'] =  "The sender's address was syntactically invalid.  This can apply to any field in the address.";

		$status_code_subclasses['1.8']['title'] = "Bad sender's system address";
		$status_code_subclasses['1.8']['descr'] =  "The sender's system specified in the address does not exist or is incapable of accepting return mail.  For domain names, this means the address portion to the right of the @ is invalid for mail. ";

		$status_code_subclasses['2.0']['title'] = "Other or undefined mailbox status";
		$status_code_subclasses['2.0']['descr'] =  "The mailbox exists, but something about the destination mailbox has caused the sending of this DSN.";

		$status_code_subclasses['2.1']['title'] = "Mailbox disabled, not accepting messages";
		$status_code_subclasses['2.1']['descr'] =  "The mailbox exists, but is not accepting messages.  This may be a permanent error if the mailbox will never be re-enabled or a transient error if the mailbox is only temporarily disabled.";

		$status_code_subclasses['2.2']['title'] = "Mailbox full";
		$status_code_subclasses['2.2']['descr'] =  "The mailbox is full because the user has exceeded a per-mailbox administrative quota or physical capacity.  The general semantics implies that the recipient can delete messages to make more space available.  This code should be used as a persistent transient failure.";

		$status_code_subclasses['2.3']['title'] = "Message length exceeds administrative limit";
		$status_code_subclasses['2.2']['descr'] =  "A per-mailbox administrative message length limit has been exceeded.  This status code should be used when the per-mailbox message length limit is less than the general system limit.  This code should be used as a permanent failure.";

		$status_code_subclasses['2.4']['title'] = "Mailing list expansion problem";
		$status_code_subclasses['2.3']['descr'] =  "The mailbox is a mailing list address and the mailing list was unable to be expanded.  This code may represent a permanent failure or a persistent transient failure. ";

		$status_code_subclasses['3.0']['title'] = "Other or undefined mail system status";
		$status_code_subclasses['3.0']['descr'] =  "The destination system exists and normally accepts mail, but something about the system has caused the generation of this DSN.";

		$status_code_subclasses['3.1']['title'] = "Mail system full";
		$status_code_subclasses['3.1']['descr'] =  "Mail system storage has been exceeded.  The general semantics imply that the individual recipient may not be able to delete material to make room for additional messages.  This is useful only as a persistent transient error.";

		$status_code_subclasses['3.2']['title'] = "System not accepting network messages";
		$status_code_subclasses['3.2']['descr'] =  "The host on which the mailbox is resident is not accepting messages.  Examples of such conditions include an immanent shutdown, excessive load, or system maintenance.  This is useful for both permanent and permanent transient errors. ";

		$status_code_subclasses['3.3']['title'] = "System not capable of selected features";
		$status_code_subclasses['3.3']['descr'] =  "Selected features specified for the message are not supported by the destination system.  This can occur in gateways when features from one domain cannot be mapped onto the supported feature in another.";

		$status_code_subclasses['3.4']['title'] = "Message too big for system";
		$status_code_subclasses['3.4']['descr'] =  "The message is larger than per-message size limit.  This limit may either be for physical or administrative reasons. This is useful only as a permanent error.";

		$status_code_subclasses['3.5']['title'] = "System incorrectly configured";
		$status_code_subclasses['3.5']['descr'] =  "The system is not configured in a manner which will permit it to accept this message.";

		$status_code_subclasses['4.0']['title'] = "Other or undefined network or routing status";
		$status_code_subclasses['4.0']['descr'] =  "Something went wrong with the networking, but it is not clear what the problem is, or the problem cannot be well expressed with any of the other provided detail codes.";

		$status_code_subclasses['4.1']['title'] = "No answer from host";
		$status_code_subclasses['4.1']['descr'] =  "The outbound connection attempt was not answered, either because the remote system was busy, or otherwise unable to take a call.  This is useful only as a persistent transient error.";

		$status_code_subclasses['4.2']['title'] = "Bad connection";
		$status_code_subclasses['4.2']['descr'] =  "The outbound connection was established, but was otherwise unable to complete the message transaction, either because of time-out, or inadequate connection quality. This is useful only as a persistent transient error.";

		$status_code_subclasses['4.3']['title'] = "Directory server failure";
		$status_code_subclasses['4.3']['descr'] =  "The network system was unable to forward the message, because a directory server was unavailable.  This is useful only as a persistent transient error. The inability to connect to an Internet DNS server is one example of the directory server failure error. ";

		$status_code_subclasses['4.4']['title'] = "Unable to route";
		$status_code_subclasses['4.4']['descr'] =  "The mail system was unable to determine the next hop for the message because the necessary routing information was unavailable from the directory server. This is useful for both permanent and persistent transient errors.  A DNS lookup returning only an SOA (Start of Administration) record for a domain name is one example of the unable to route error.";

		$status_code_subclasses['4.5']['title'] = "Mail system congestion";
		$status_code_subclasses['4.5']['descr'] =  "The mail system was unable to deliver the message because the mail system was congested. This is useful only as a persistent transient error.";

		$status_code_subclasses['4.6']['title'] = "Routing loop detected";
		$status_code_subclasses['4.6']['descr'] =  "A routing loop caused the message to be forwarded too many times, either because of incorrect routing tables or a user forwarding loop. This is useful only as a persistent transient error.";

		$status_code_subclasses['4.7']['title'] = "Delivery time expired";
		$status_code_subclasses['4.7']['descr'] =  "The message was considered too old by the rejecting system, either because it remained on that host too long or because the time-to-live value specified by the sender of the message was exceeded. If possible, the code for the actual problem found when delivery was attempted should be returned rather than this code.  This is useful only as a persistent transient error.";

		$status_code_subclasses['5.0']['title'] = "Other or undefined protocol status";
		$status_code_subclasses['5.0']['descr'] =  "Something was wrong with the protocol necessary to deliver the message to the next hop and the problem cannot be well expressed with any of the other provided detail codes.";

		$status_code_subclasses['5.1']['title'] = "Invalid command";
		$status_code_subclasses['5.1']['descr'] =  "A mail transaction protocol command was issued which was either out of sequence or unsupported.  This is useful only as a permanent error.";

		$status_code_subclasses['5.2']['title'] = "Syntax error";
		$status_code_subclasses['5.2']['descr'] =  "A mail transaction protocol command was issued which could not be interpreted, either because the syntax was wrong or the command is unrecognized. This is useful only as a permanent error.";

		$status_code_subclasses['5.3']['title'] = "Too many recipients";
		$status_code_subclasses['5.3']['descr'] =  "More recipients were specified for the message than could have been delivered by the protocol.  This error should normally result in the segmentation of the message into two, the remainder of the recipients to be delivered on a subsequent delivery attempt.  It is included in this list in the event that such segmentation is not possible.";

		$status_code_subclasses['5.4']['title'] = "Invalid command arguments";
		$status_code_subclasses['5.4']['descr'] =  "A valid mail transaction protocol command was issued with invalid arguments, either because the arguments were out of range or represented unrecognized features. This is useful only as a permanent error. ";

		$status_code_subclasses['5.5']['title'] = "Wrong protocol version";
		$status_code_subclasses['5.5']['descr'] =  "A protocol version mis-match existed which could not be automatically resolved by the communicating parties.";

		$status_code_subclasses['6.0']['title'] = "Other or undefined media error";
		$status_code_subclasses['6.0']['descr'] =  "Something about the content of a message caused it to be considered undeliverable and the problem cannot be well expressed with any of the other provided detail codes. ";

		$status_code_subclasses['6.1']['title'] = "Media not supported";
		$status_code_subclasses['6.1']['descr'] =  "The media of the message is not supported by either the delivery protocol or the next system in the forwarding path. This is useful only as a permanent error.";

		$status_code_subclasses['6.2']['title'] = "Conversion required and prohibited";
		$status_code_subclasses['6.2']['descr'] =  "The content of the message must be converted before it can be delivered and such conversion is not permitted.  Such prohibitions may be the expression of the sender in the message itself or the policy of the sending host.";

		$status_code_subclasses['6.3']['title'] = "Conversion required but not supported";
		$status_code_subclasses['6.3']['descr'] =  "The message content must be converted to be forwarded but such conversion is not possible or is not practical by a host in the forwarding path.  This condition may result when an ESMTP gateway supports 8bit transport but is not able to downgrade the message to 7 bit as required for the next hop.";

		$status_code_subclasses['6.4']['title'] = "Conversion with loss performed";
		$status_code_subclasses['6.4']['descr'] =  "This is a warning sent to the sender when message delivery was successfully but when the delivery required a conversion in which some data was lost.  This may also be a permanant error if the sender has indicated that conversion with loss is prohibited for the message.";

		$status_code_subclasses['6.5']['title'] = "Conversion Failed";
		$status_code_subclasses['6.5']['descr'] =  "A conversion was required but was unsuccessful.  This may be useful as a permanent or persistent temporary notification.";

		$status_code_subclasses['7.0']['title'] = "Other or undefined security status";
		$status_code_subclasses['7.0']['descr'] =  "Something related to security caused the message to be returned, and the problem cannot be well expressed with any of the other provided detail codes.  This status code may also be used when the condition cannot be further described because of security policies in force.";

		$status_code_subclasses['7.1']['title'] = "Delivery not authorized, message refused";
		$status_code_subclasses['7.1']['descr'] =  "The sender is not authorized to send to the destination. This can be the result of per-host or per-recipient filtering.  This memo does not discuss the merits of any such filtering, but provides a mechanism to report such. This is useful only as a permanent error.";

		$status_code_subclasses['7.2']['title'] = "Mailing list expansion prohibited";
		$status_code_subclasses['7.2']['descr'] =  "The sender is not authorized to send a message to the intended mailing list. This is useful only as a permanent error.";

		$status_code_subclasses['7.3']['title'] = "Security conversion required but not possible";
		$status_code_subclasses['7.3']['descr'] =  "A conversion from one secure messaging protocol to another was required for delivery and such conversion was not possible. This is useful only as a permanent error. ";

		$status_code_subclasses['7.4']['title'] = "Security features not supported";
		$status_code_subclasses['7.4']['descr'] =  "A message contained security features such as secure authentication which could not be supported on the delivery protocol. This is useful only as a permanent error.";

		$status_code_subclasses['7.5']['title'] = "Cryptographic failure";
		$status_code_subclasses['7.5']['descr'] =  "A transport system otherwise authorized to validate or decrypt a message in transport was unable to do so because necessary information such as key was not available or such information was invalid.";

		$status_code_subclasses['7.6']['title'] = "Cryptographic algorithm not supported";
		$status_code_subclasses['7.6']['descr'] =  "A transport system otherwise authorized to validate or decrypt a message was unable to do so because the necessary algorithm was not supported. ";

		$status_code_subclasses['7.7']['title'] = "Message integrity failure";
		$status_code_subclasses['7.7']['descr'] =  "A transport system otherwise authorized to validate a message was unable to do so because the message was corrupted or altered.  This may be useful as a permanent, transient persistent, or successful delivery code.";
		return $status_code_subclasses;
	}

} /** END class BounceHandlerStatusCodes **/




?>