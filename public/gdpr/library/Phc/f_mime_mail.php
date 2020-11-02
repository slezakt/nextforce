<?php

/**
 * $URL: file:///home/lekarnik/repos/utils/trunk/php-include2/f_mime_mail.php $
 * $Date: 2007-09-26 14:37:00 +0200 (Wed, 26 Sep 2007) $
 * $Author: lekarnik $
 * $Rev: 130 $
 *
 * Třída pro odesílaní mailů a logování odeslaných mailů
 *
 * 2007-09-26
 * - Opraveno nespravne zachazeni s jinym kodovanim nez defaultni - subject nebyl spravne kodovan,
 *   nebo se logovala chyba
 * - Pridana podpora pro posilani na adresy Cc a Bcc
 * - Pridana podpora pro zadavani jmena do adres (To, Cc, Bcc, From) (i v narodnim kodovani!!)
 *
 * @author ukradená kdoví odkud + Jaromír Krotký + Petr Skoda
 * @package php-include
 * @subpackage mime_mail
 *
 * Pro lepąí pochopení ukázka zdrojového kódu:
 * <code>
 * Před odesláním mailu, můľe být definována konstanta TEST s hodnotami 0 nebo 1. Pokud TEST=0 budou emaily
 * normálně odesílány, pokud TEST=1 přejde se do testovacího reľimu a emaily budou odesílány na adresu definovanou
 * konstantou DEVELOPER_EMAIL. Pokud tato není nastavena, nic se neodeąle a jen se uloľí informace o odeslání.
 *
 * Dále je potřeba, aby byly načteny sdílené soubory f_sql.php, f_log.php, f_autoczech.php a f_mime_mail.php.
 *
 * // vytvoření objektu
 * $mail = new mime_mail;
 *
 * // nastavení mailové adresy adresata
 * // Muze obssahovat vice adres oddelenych carkou i adresy se jmeny ve tvaru:
 * // Jmeno Cloveka <jeho.adresa@nekde.cz>
 * // nebo Jmeno Cloveka <jeho.adresa@nekde.cz>, karel.sodny@soda.cz, Radim Petrasek <petrasek@jinde.cz>
 * // a tyto adresy mohou byt take oddeleny carkou, pravidlo plati i pro Cc a Bcc.
 * $mail->to = "doktor@ordinace.cz";
 *
 * // nastavení mailové adresy kopie (nepovinna)
 * $mail->cc = "kdosiDalsi@ordinace.cz";
 *
 * // nastavení mailové adresy skryte kopie (nepovinna)
 * $mail->bcc = "TenKdoNeniVidet@ordinace.cz";
 *
 * // nastavení id adresáta (pokud neznám id, řádek vynechám)
 * $mail->to_id = 12345;
 *
 * // nastavení typu adresáta - k=klient=>id je z crm_klienti (pokud neznám id, řádek vynechám)
 * $mail->to_id_type = 'k';
 *
 * // nastavení mailové adresy odesílatele (pokud neznám nebo nechci, tak vynechám)
 * // Musi byt POUZE JEDNA ADRESA, ale muze byt se jmenem, jako adresy To, Cc, Bcc
 * $mail->from = "repik@farmacie.cz";
 *
 * // nastavení id odesílatele (pokud neznám id, řádek vynechám)
 * $mail->from_id = 10;
 *
 * // nastavení typu odesílatele - u=uľivatel=>id je z m_uzivatele (pokud neznám id, řádek vynechám)
 * $mail->from_id_type = 'u';
 *
 * // předmět emailu
 * $mail->subject = "Vitame Vas v projektu Ordinace";
 *
 * // tělo emailu
 * $mail->body = "Dobrý den, ...........";
 *
 * // nastavení content type těla zprávy (defaultně je nastaveno na  "text/plain; charset=iso-8859-2;")
 * $mail->content_type = "text/plain; charset=windows-1250;"
 *
 * // načtu do proměnné $data obsah souboru, který chci jako přílohu
 * $filename = "/home/lekarnik/ord_uvitani.pdf";
 * $fd = fopen ($filename, "r");
 * $data = fread ($fd, filesize ($filename));
 * fclose ($fd);
 *
 * // připojeni přílohy k mailu, parametry v pořadí obsah souboru, jmeno, typ
 * $mail->add_attachment($data, "uvitani.pdf", "application/pdf" );
 *
 * // stejným způsobem je moľno připojit několik příloh, ovąem jejich velikost nesmí překročit mnoľství
 * // dat, které je php schopno zpracovat (různe podle nastavení php)
 *
 * // nastavení modulu(aplikace) ze které třídu pouľívám
 * $mail->module = "ordinace.cz";
 *
 * // odeslání mailu
 * $mail->send();
 * </code>
 */

class mime_mail
{
        /**
         * @var array, asociativní pole uchovavájící části mailu s indexy ctype, name, message
         * naplní se přilohami + jako jedna z příloh se doplní i text mailu
         */
        var $parts;
        /**
         * @var string, mailová adresa příjemce (je nutná)
         */
        var $to;
	/**
         * @var string, mailova adresa kopie pro (neni nutna)
         */
        var $cc;
	/**
         * @var string, mailova adresa skryta kopie (neni nutna)
         */
        var $bcc;
        /**
         * @var string, mailová adresa odesilatele (neni nutná)
         */
        var $from;
        /**
         * @var string, hlavička zprávy
         */
        var $headers;
	/**
	 * typ hlavicky pro navratovy mail pri chybe {errors|return}
	 */
	var $headers_errors_return = 'errors';
	/**
	 * mail na ktery se ma zprava vratit pri neuspesnem odeslani
	 */
	var $return_path;
        /**
         * @var string, předmět=subject mailu
         */
        var $subject;
        /**
         * @var string tělo mailu
         */
        var $body;
	/**
	 * @var string nezakodovane tělo mailu
	 */
	var $uncoded_body;

        /**
         * @var integer, id příjemce - buď lékaře z crm_klienti nebo id uľivatele z m_uzivatele
         * POZOR nekontroluje se na správnost ani není provázáno v databázi, není nutné vyplňovat
         */
        var $to_id;
        /**
         * @var char(1), typ id příjemce, pro lékaře z crm_klienti 'k', pro uľivatele z m_uzivatele 'u',
         * pokud zadáte jiný řetězec, také se uloľí, ale s neznámým významem při generovaní statistik
         */
        var $to_id_type;
        /**
         * @var integer, id odesilatele - buď lékaře z crm_klienti nebo id uľivatele z m_uzivatele
         * POZOR nekontroluje se na správnost ani není provázáno v databázi, není nutné vyplňovat
         */
        var $from_id;
        /**
         * @var char(1), typ id odesilatele, pro lékaře z crm_klienti 'k', pro uľivatele z m_uzivatele 'u',
         * pokud zadáte jiný řetězec, také se uloľí, ale s neznámým významem při generovaní statistik
         */
        var $from_id_type;
        /**
         * @var integer typ zpravy, můľe být testovací $message_type=1, nebo normální $message_type=0,
         * implicitně se nastaví podle hodnoty konstanty TEST , ale můľe být přenastavena za běhu
         */
        var $message_type;
        /**
         * @var string, modul (aplikace), ze které byla zpráva odeslána
         */
        var $module;
        /**
         * @var string, content type těla zprávy
         */
        var $content_type;
        /**
         * @var boolean, ukládat zprávu do databáze? ano/ne
         */
        var $save_message;


        /**
         * @var string jméno databáze, do které se má ukládat (! nepřenastavovat)
         */
        var $dbname;
        /**
         * @var string uľivatel databáze, do které se má ukládat (! nepřenastavovat)
         */
        var $dbuser;
        /**
         * @var string heslo do databáze, do které se má ukládat (! nepřenastavovat)
         */
        var $dbpass;
	/**
         * @var string host databáze, do které se má ukládat (! nepřenastavovat)
         */
	var $dbhost;


        /**
         * Konstruktor třídy
         */
        function mime_mail() {
                $this->parts = array();
                $this->to =  "";
		$this->cc =  "";
		$this->bcc =  "";
                $this->from =  "";
                $this->subject =  "";
                $this->body =  "";
		$this->uncoded_body = "";
                $this->headers =  "";

                $this->to_id = "";
                $this->to_id_type = "";
                $this->from_id = "";
                $this->from_id_type = "";
                if (!Env::isProductionMode()) $this->message_type = 1;
                else $this->message_type = 0;
                $this->module = "";
                $this->content_type =  "text/plain; charset=iso-8859-2;";
                $this->save_message = 1;

                $this->dbname = "dbmain2";
                $this->dbuser = "lekarnik";
                $this->dbpass = "deiM5ejohsoo";
		if(phpversion() == "4.1.2") {
		    //lekarna1
		    $this->dbhost = "10.99.0.254";
		}
		else {
		    //valentine
		    $this->dbhost = "10.0.0.100";
		}
		
		if (substr(ini_get('memory_limit'),0,-1) < 60) ini_set('memory_limit','60M');
        }

        /**
         * Funkce vkládá přílohu do objektu zprávy
         *
         * @param string řetězec obsahující data ze souboru přílohy
         * @param string řetězec obsahující jméno souboru přílohy
         * @param string řetězec určující content type souboru přílohy
         */
        function add_attachment(&$message, $name =  "", $ctype = "application/octet-stream") {
                $this->parts[] = array ("ctype" => $ctype,
                                        "message" => $message,
                                        "name" => $name
                                        );
        }

        /**
         * Vytvoření částí vícedílné zprávy
         *
         * @param array pole definující část mailu, s indexy 'message', 'ctype' a 'name'
         * @return string řetězec, který popisuje část mailu, je adekvátní poli, ale je moľné ho odeslat
         */
        function build_message(&$part) {
                $message = &$part[ "message"];
                $message = chunk_split(base64_encode($message));
                $encoding =  "base64";
                return  "Content-Type: ".$part[ "ctype"].
                                ($part[ "name"]? "; name = \"".$part[ "name"].
                                "\"" :  "").
                                "\nContent-Transfer-Encoding: $encoding\n\n$message\n";
        }

        /**
         * Vytvaří vícedílnou zprávu
         *
         * @return string řetězec, který je sloľen z jednotlivých částí mailu, které jsou správně odděleny pomocí
         *                boundary a odřádkování
         */
        function build_multipart() {
                $boundary =  "b".md5(uniqid(time()));
                $multipart = "Content-Type: multipart/mixed; boundary = $boundary\n\nThis is a MIME encoded message.\n\n--$boundary";

                for($i = sizeof($this->parts)-1; $i >= 0; $i--) {
                        $multipart .=  "\n".$this->build_message($this->parts[$i])."--$boundary";
                }
                return $multipart.=  "--\n";
        }

        /**
         * Vrací vytvořenou zprávu
         *
         * @param boolean má/nemá se subject přidávat k hlavičkám
         * @return string řetězec popisující celý vytvořený mail a který se jiľ můľe odeslat
         */
        function get_mail($complete = true) {
                $mime =  "";
                if (!empty($this->from)) {
		  	$mime .=  "From: ".$this->headerAddrEncode($this->from)."\n";
			
			if (empty($this->return_path))
			    $this->return_path = $this->getAddrFromAddrHeader($this->from);
                }
		if(!empty($this->return_path))
		{
		    if ($this->message_type==1 && defined("DEVELOPER_EMAIL") && DEVELOPER_EMAIL)
			$this->return_path = DEVELOPER_EMAIL;
				    
		    $mime .= ($this->headers_errors_return=='errors' ? 
			"Errors-To: " : "Return-Path: ").$this->return_path."\n";
		}
		
                if (!empty($this->headers)) {
                        $mime .= $this->headers. "\n";
                }

                if ($complete) {
                        if (!empty($this->to)) {
                                $mime .= "To: ".$this->headerAddrEncode($this->to)."\n";
                        }
                        if (!empty($this->subject)) {
                                $mime .= "Subject: ".$this->mimeEncode($this->subject)."\n";
                        }
                }
		
		//kopie a skryta kopie
		if (!empty($this->cc)) {
			$mime .= "Cc: ".$this->headerAddrEncode($this->cc)."\n";
		}
		if (!empty($this->bcc)) {
			$mime .= "Bcc: ".$this->headerAddrEncode($this->bcc)."\n";
		}

		// tenhle "if" jsem pridal
		if (empty($this->return_path))
		{
			if (!empty($this->headers)) {
				$header_parts = explode("\n", $this->headers);
				foreach ($header_parts AS $param) {
					if (preg_match('/(.*):(.*)/', $param, $part))
					   $header[trim($part[1])] = trim($part[2]);
				}
			}

			if (IsSet($header['Errors-To']))
				$this->return_path = $header['Errors-To'];
			elseif (IsSet($header['Return-Path']))
				$this->return_path = $header['Return-Path'];
			elseif (IsSet($header['From'])) {
				$this->return_path = $header['From'];
			}
		}

                if (!empty($this->body)) {
			$this->uncoded_body = $this->body;
                        $this->add_attachment($this->body,  "",  $this->content_type);
                }

                $mime .=  "MIME-Version: 1.0\n".$this->build_multipart();
                return $mime;
        }

        /**
         * Překóduje string do prislusneho charsetu ve tvaru MIME encoded-word
         *
         * @param string text v narodnim kodovani
         * @return string text převedený na prislusne kodovani
         */
        function mimeEncode($string) {
                $ch = substr(strstr($this->content_type,"charset"),7);
                //echo "$ch 1<br/>";
		//$ch = substr($ch,0,strpos($ch,";"));
                //echo "$ch 2<br/>";
		$ch = substr($ch,strpos($ch,"=")+1);
		//echo "$ch 3<br/>";
                $charset = trim($ch);
		
                if ($charset=='') $charset = 'iso-8859-2';

                $string = bin2hex($string);
                $encoded = chunk_split($string, 2, '=');
                $encoded=preg_replace ('/=$/',"",$encoded);
                $string="=?$charset?Q?=".$encoded."?="; 
		
		return $string;
        }
	
	
	/**
         * Pomoci mimeEncode prekoduje spravnym zpusobem adresu do pole hlavicky tak, ze
	 * pokud je v retezci pouzito "< cokoliv >" bere se obsah zavorek <> jako adresa a 
	 * zbytek retezce pred se zakoduje narodnim kodovanim
	 *
	 * PRIKLAD 1: Petr Skoda <petr.skoda@pearshealthcyber.com>, Radek Rohac
	 * <radek.rohac@pearshealthcyber.com>
	 * PRIKLAD 2: petr.skoda@pearshealthcyber.com, radek.rohac@pearshealthcyber.com
	 * PRIKLAD 3: Petr Skoda <petr.skoda@pearshealthcyber.com>, radek.rohac@pearshealthcyber.com
         *
         * @param string mailove adresy se jmeny nebo bez jmen
         * @return string text subjectu převedený na přísluąné kódování
         */
        function headerAddrEncode($string) {
	  $adrA = explode(",", $string);
	  $addrs = array();
	  foreach($adrA as $adr){
	    $a = array();
	    if(preg_match("/(.*)<(.*)>/i", $adr, $a)){
	      $name = trim($a[1]);
	      $adr = $a[2];
	      
	      $addrs[] = $this->mimeEncode($name)." <".$adr.">";
	    }
	    else{
	      $addrs[] = $adr;
	    }
	  }
	  return join(", ", $addrs);
	}
	
	
	/**
         * Vrati pouze adresu z adresy zadavane v hlavickach, kde muze byt adresa 
	 * i se jmenem.
         *
         * @param string Mailova adresa do hlavicky s nebo bez jmena
         * @return string pouze mailova adresa
         */
        function getAddrFromAddrHeader($string) {
	  $a = array();
	  if(preg_match("/<(.*)>/i", $string, $a)){
	    return $a[1];
	  }
	  else{
	    return $string;
	  }
	}
	

        /**
         * Odesílá a loguje zprávu
         *
         * @return int/boolean pokud se  podaří odeslat i logovat, vrací id uloľené zprávy, pokud jen odeslat vrací
         *                     true, pokud se nepodaří odeslat vrací false
         */
        function send() {
		# overeni testovaciho rezimu
	  	$send = ($this->message_type==1 ? false : true);
                if ($this->message_type==1 && defined("DEVELOPER_EMAIL") && DEVELOPER_EMAIL)
                {
                        $send = true;
			$this->to = DEVELOPER_EMAIL;
			//zmenime i mailove adresy na kopii a skrytou kopii
			$this->cc = empty($this->cc) ? "" : DEVELOPER_EMAIL;
			$this->bcc = empty($this->bcc) ? "" : DEVELOPER_EMAIL;
                }
		
		# vygenerujeme hlavni cast mailu
		$mime = $this->get_mail(false);
		
                # odeslání emailu
                if ($send){
		  	$vysl = mail($this->headerAddrEncode($this->to), $this->mimeEncode($this->subject),  "", $mime, "-f ".$this->return_path);
		}
                else
                        $vysl = true;
		        
			
                if (!$vysl)
                {
		  trigger_error("Nepodarilo se odeslat email: TO:".$this->headerAddrEncode($this->to).", SUBJECT: ".$this->mimeEncode($this->subject).", EMAIL: $mime");
                    return false;
                }
                elseif ($this->save_message)
                {
                    # ukladání informací o odesílanem mailu a jeho přílohách {{{
                    $mail_conn = sql_connect($this->dbname,$this->dbuser,$this->dbpass,$this->dbhost);
                    $dotaz = "SELECT nextval('email_outgoing_message_id_seq');";
                    $result = sql_query($dotaz, false, $mail_conn);
                    $row = sql_fetch_array($result,0);
                    $message_id = $row[0];
                    sql_query("BEGIN;", true, $mail_conn);  
                    $encoding = "utf8";
                    $str_charset = "charset=";
                    $pos = strpos(strtolower($this->content_type),$str_charset);
                    if($pos!=false){
                        $encoding = substr(strtolower($this->content_type),$pos+strlen($str_charset));
                    }
                    if(strtolower($encoding)!="utf-8"){
		      $this->subject = @iconv($encoding,"utf-8",$this->subject);
                      $this->uncoded_body = @iconv($encoding,"utf-8",$this->uncoded_body);
		    }
                    if(($this->uncoded_body===false)||($this->subject===false)){
                        trigger_error("Neporadrilo se rozpoznat kodovani emailu");
                        return false;  
                    }                  
                    $dotaz = sprintf("INSERT INTO email_outgoing (message_id, message_to, message_to_id, message_to_id_type,
                                        message_from, message_from_id, message_from_id_type, message_subject,
                                        message_text, message_type, message_send_time, message_module, message_from_url, message_from_host) VALUES
                                        (%d, '%s', %s, '%s',
                                         %s, %s, '%s', '%s',
                                         '%s', '%s', now(), '%s', '%s', '%s');",
                                        $message_id,
                                        addslashes($this->to),
                                        ($this->to_id ? (int) $this->to_id : 'NULL'),
                                        addslashes(substr($this->to_id_type,0,1)),

                                        ($this->from ? "'".addslashes($this->from)."'" : 'NULL'),
                                        ($this->from_id ? (int) $this->from_id : 'NULL'),
                                        addslashes(substr($this->from_id_type,0,1)),
                                        str_replace("'",'"',$this->subject),
                                        
                                        str_replace("'",'"',$this->uncoded_body),
                                        (int) $this->message_type,
                                        addslashes($this->module),
					$_SERVER["REQUEST_URI"],
					$_SERVER["HTTP_HOST"]);
                    $vysl = sql_query($dotaz, true, $mail_conn) && $vysl;
                    for ($i=0;$i<(sizeof($this->parts)-1);$i++)
                    {
                            $part = $this->parts[$i];
                            $dotaz = sprintf("INSERT INTO email_outgoing_attachments (message_id, attachment_type,
                                                attachment_name, attachment) VALUES
                                                (currval('email_outgoing_message_id_seq'),
                                                '%s','%s','%s');\n",
                                                addslashes($part["ctype"]),
                                                addslashes($part["name"]),
                                                sql_escape_bytea($part["message"]));
                            $vysl = sql_query($dotaz, true, $mail_conn) && $vysl;
                    }
                    if ($vysl)
                    {
                        $ret = $message_id;
                        sql_query("COMMIT;", true, $mail_conn);
                    }
                    else
                    {
                        sql_query("ROLLBACK;", true, $mail_conn);
                        $ret = true;
                    }
                    if ($GLOBALS["_PG_HANDLER"]!=$mail_conn) sql_close($mail_conn);
                    return $ret;
                    # }}}
                }
                else
                {
                        return true;
                }
        }

};
?>
