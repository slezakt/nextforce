<?php
/**
 * Symmetric-key encryption/decryption class implemented using PHP mcrypt module
 * 
 * @author Andrej Litvaj
 * 
 */

class Ibulletin_Crypt 
{	
	/**
	 * Tajny klic pro sifrovani - delka 56 znaku
	 */
	const KEY = '8vxU^pV3"4scGt2%:vCqI`QQ!h!w#_>[bg^@%:>k=:CwO.0][f?/grUM';
	
	/**
	 * key used for encryption/decryption
	 *  
	 * @var string
	 */
	private $key;
	
	/**
	 * mcrypt resource
	 *
	 * @var resource
	 */
	private $td;	
	
	/**
	 * constructor
	 * 
	 * @param string $key
	 */	
	public function __construct($key = null) 
	{		
		$this->key = is_null($key) ?  self::KEY : $key; 
		
		// opens specific cryptographic module
		$this->td = mcrypt_module_open( MCRYPT_BLOWFISH, '', MCRYPT_MODE_ECB, '' );
		if ( !$this->td ) {
			trigger_error("Unable to open mcrypt module", E_USER_ERROR);
			return null;
		}
		
		// inicializace modulu
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($this->td));
		if ( mcrypt_generic_init( $this->td, $this->key, $iv ) != 0 ) {
			trigger_error("Unable to initializace mcrypt module", E_USER_ERROR);
			return null;
		}		
		
	}	
	
	/**
	 * destructor
	 */
	public function __destruct() 
	{				
		// deinicializace modulu
		mcrypt_generic_deinit($this->td);		
		// zavru modul
		mcrypt_module_close($this->td);				
	}
	
	/**
	 * Encrypts data using mcrypt
	 *
	 * @param string $data
	 * @return string
	 */
	public function encrypt($data) 
	{
		return mcrypt_generic($this->td, $data);		
	}
	
	/**
	 * Decrypts data using mcrypt
	 *
	 * @param string $cryptedData
	 * @return string
	 */
	public function decrypt($cryptedData) 
	{
		return mdecrypt_generic($this->td, $cryptedData);
		// trim trailing whitespace
		//return rtrim($data,"\0");
	}
		
	
}