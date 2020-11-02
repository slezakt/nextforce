<?php
/**
 * 
 * @author dominik vesely
 * 
 * Třída je rozšířením tříy mailer o 
 * prvky které využijeme při práci s uživateli
 * a výpisem jejich informaci
 *
 */
class IBulletin_MailerUsers extends Ibulletin_Mailer
{
	
	/**
	 * Funkce vraci id,jmeno,prijmeni reprezentanta daneho uzivatele 
	 * 
	 * @param $rep - id Uzivatele ke kteremu hledame reprezentanta
	 * 
	 */
	public function getRep($rep)
	{

		if($rep != null)
        {
        	$db =  Zend_Registry::get('db');
           $select=$db->select('name,surname,id')
           ->from ('users')
           ->where('id=?',$rep);
           $res=$db->fetchAll($select);
           foreach ($res as $userres)
           {
           	$result = array('id' =>	$userres[id],
           					'name' =>$userres[name],
           					'surname' => $userres[surname]);
           }
                
       return $result ;
        } else {
        	return false;
        }
	}
	
}
?>