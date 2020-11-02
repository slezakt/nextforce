<?php

class Ibulletin_Email_Exception extends Exception {}

/**
 *  Třída pro emaily. Do databáze se ukládají emaily jako serializované objekty.
 *
 *  @author Martin Krčmář
 *  @author Andrej Litvaj
 */
class Ibulletin_Email
{
  /**
   *    Identifikator emailu v databazi.
   */
  protected $id = 0;

  /**
   *    Nazev emailu.
   */
  protected $name = "";

  /**
   *    From emailu
   *    Nemusi byt nastaveno predem, pred odeslanim se v takovem pripade nastavi jako odesilatel
   *    config->mailer->from.
   */
  protected $from = null;

  /**
   *    Zobrazovane jmno pro from emailu
   *    Nemusi byt nastaveno predem, pred odeslanim se v takovem pripade nastavi jako odesilatel
   *    config->mailer->fromName.
   */
  protected $fromName = null;

  /**
   *    Rep odesilajici nebo zodpovedny za tento email.
   */
  protected $rep = null;

  /**
   *    Predmet emailu.
   */
  protected $subject = "";

  /**
   *    Telo emailu.
   */
  protected $body = "";

  /**
   *    Plain text cast emailu.
   */
  protected $plain = "";

  /**
   *    Result of SpamAssassin
   */
  protected $spam = null;

  /**
   *    Objekt Zend_Mail, ten se postupne vytvari ve funkci createMimeParts. Pak
   *    se to do DB serializuje i s timto objektem, aby se nepusely komplikovane
   *    ukladat jednotlive MIME casti a zase davat dohromady. Pri odesilani
   *    emailu se potom jednoduse ziska tento objekt a email odesle.
   *    @var Zend_Mail
   */
  protected $zendMail;

  /**
   *    Pole Content identifikatoru, ktere se vytvareji postupne pri kodovani
   *    obrazku do tela emailu v metode createMimeParts.
   *    Potom ve funkci replaceCid se nahrazuju jednotlive src atributy obrazku
   *    prave temito identifikatory.
   */
  public $imgsEncoded = array();

  /**
   *    Pole Content identifikatoru, ktere se vytvareji postupne pri prikladani
   *    priloh do tela emailu v metode createMimeParts.
   *    Potom ve funkci replaceCid se mazou vyskyty znacky pro vlozeni souboru
   */
  protected $attachmentsEncoded = array();

  /**
   *    True pokud již v těle emailu byly nahrazeny značky %%imgmime%% za
   *    příslušné cid. Pokud bude stále false při volání metody getZendMail,
   *    nahradí se ty značky až právě při volání getZendMail.
   */
  protected $cidReplaced = false;

  /**
   *    True pokud již v těle emailu byly vymazany značky %%attach%%.
   *    Pokud bude stále false při volání metody getZendMail,
   *    nahradí se ty značky až právě při volání getZendMail.
   */
  protected $attachmentsRemoved = false;

  /**********************************************************
   *  PUBLIC METODY
   **********************************************************/

  /**
   * Nacte mail z DB podle id a vrati objekt Ibulletin_Email.
   *
   * @param int $emailId     ID emailu z tabulky emails
   * @return Ibulletin_Email     Email se zadanym ID.
   *
   * @throws Ibulletin_Email_Exception   Pokud email se zadanym ID nebyl nalezen.
   */
  public static function getEmail($emailId)
  {
        $db = Zend_Registry::get('db');

        $select = new Zend_Db_Select($db);
        $select->from('emails', '*')
               ->where('id = ?', $emailId);
        $row = $db->fetchRow($select);

        if(empty($row)){
            throw new Ibulletin_Email_Exception('Email s ID='.$emailId.' nebyl v DB nalezen.');
        }

        $email = self::createInstance($row['body']);

        return $email;
  }

  /**
   *    Vrati instanci Ibulletin_Email objektu.
   *
   *    @param Kod serializovaneho objektu, zakodovany pomoci base64_encode.
   */
  public static function createInstance($code)
  {
    Zend_Loader::loadClass('Zend_Mail');
    return unserialize(base64_decode($code));
  }

  /**
   *    Pripravi objekt pro ulozeni do DB.
   *
   *    @param objekt pro serializaci a zakodovani.
   */
  public static function encodeInstance($object)
  {
    return base64_encode(serialize($object));
  }

  /**
   *    Metoda pro klonování objektu. Je zde kvůli klonování $this->zendMail
   *    objektu, ale obecně naklonuje všechny vlastnosti, které jsou typu
   *    objekt.
   */
  public function __clone()
  {
    foreach ($this as $name => $value)
    {
      if (gettype($value) == 'object')
      {
        $this->$name = clone($this->$name);
      }
    }
  }

  /**
   *    Nastavi identifikator emailu.
   *
   *    @param Id emailu.
   */
  public function setId($id)
  {
    $this->id = (int)$id;
  }

  /**
   *    Vrati ID emailu.
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   *    Nastavi jmeno emailu.
   *
   *    @param Jmeno emailu.
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   *    Vrati jmeno emailu.
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   *    Nastavi from adresu pro email.
   *
   * @param string  Emailova adresa pro pole FROM
   * @param string  Zobrazovane jmeno pro pole FROM (null znamena, ze nebude prenastaveno)
   */
  public function setFrom($from, $fromName = null)
  {
    $this->from = $from;

    if($fromName !== null){
        $this->fromName = $fromName;
    }
  }

  /**
   *    Vrati from adresu pro email.
   * @return From adresa emailu.
   */
  public function getFrom()
  {
    return $this->from;
  }

  /**
   *    Nastavi zobrazovane jmeno pro FROM emailu.
   *
   * @param Zobrazovane jmeno pro pole FROM
   */
  public function setFromName($fromName)
  {
    $this->fromName = $fromName;
  }

  /**
   *    Vrati zobrazovane jmeno pro email.
   *
   * @return Zobrazovane jmeno pro pole FROM
   */
  public function getFromName()
  {
    return $this->fromName;
  }

  /**
   * Nastavi id repa, ktery tento mail odesila nebo zastituje.
   *
   * POZOR!!
   * Tento uzivatel neni pouzit jako FROM emailu!
   *
   * @param int    Id reprezentanta z users, ktery tento email odesila nebo zastituje.
   */
  public function setRep($rep)
  {
    $this->rep = $rep;
  }

  /**
   * Vrati id repa, ktery tento mail odesila nebo zastituje.
   *
   * @return int    Id reprezentanta z users, ktery tento email odesila nebo zastituje.
   */
  public function getRep()
  {
    return $this->rep;
  }

  /**
   *    Nastavi predmet emailu.
   *
   *    @param Predmet emailu.
   */
  public function setSubject($subject)
  {
    $this->subject = $subject;
  }

  /**
   *    Vrati predmet emailu.
   */
  public function getSubject()
  {
    return $this->subject;
  }

  /**
   *    Vrati telo emailu.
   */
  public function getBody()
  {
    return $this->body;
  }

  /**
   *    Nastavi telo emailu.
   *
   *    @param Telo emailu.
   */
  public function setBody($body)
  {
    $this->body = $body;
  }

  /**
   *    Vrati plain text cast emailu.
   */
  public function getPlain()
  {
    return $this->plain;
  }

  /**
   *    Nastavi plain text cast emailu.
   *
   *    @param Plain text.
   */
  public function setPlain($plain)
  {
    $this->plain = $plain;
  }
  /**
   *    Vraci vysledek ohodnoceni mailu SpamAssassinem
   * @return SpamAssassin_Client_Result
   */
  public function getSpamReport() {
    return $this->spam;
  }

  /**
   * detects spam score for this email and returns detailed information
   *
   * @return SpamAssassin_Client_Result
   */
  public function detectSpamScore()
  {
    $config = Zend_Registry::get('config');

    // use spamassasin for mail spam score detection
    $sa = new SpamAssassin_Client($config->mailer->spamassassin->toArray());
    // use dummy transport for catching generated message
    $tr = new Ibulletin_Mail_Transport_Memory();

    // clone current object state
    $tmp = clone $this;
    // add extra headers for the sake of score precision
    $mail = $tmp->getZendMail();

    $mail->addTo($config->general->project_email);
    $mail->setReturnPath($config->general->project_email);
    $mail->setFrom($config->general->project_email, $config->general->project_name);
    $mail->setMessageId(true);

    // no message is sent
    $mail->send($tr);

    // store score to original unmodified object
    $this->spam = $sa->getSpamReport($tr->getRawMessage());

    // clean up
    unset($mail);
    unset($tmp);
  }

  /**
   *    Vrati instanci Zend_Mail. Pokud jeste neexistuje, tak vytvori novy
   *    Zend_Mail objekt.
   *    @return Zend_Mail
   */
  public function getZendMail()
  {
    $config = Zend_Registry::get('config');

    if (!($this->zendMail instanceof Zend_Mail))
    {
        $this->createZendMail();
    }

    $mail = $this->zendMail;

    // vymazeme vsechny existujici mime casti a nagenerujeme je znovu
    $mail->setParts(array());
    if ($att = $mail->getHtmlRelatedAttachments()) {
        $att->setParts(array());
    }

    // Nastavime from a fromName i s ohledem na nastaveni v configu
    if(!empty($this->from) || !empty($this->fromName)){
        $from = $this->from ? $this->from : $config->mailer->get('from');
        $fromName = $this->fromName ? $this->fromName : $config->mailer->get('fromName');

        $this->zendMail->setFrom($from, $fromName);
    }

    // nastavime subject
    if (!empty($this->subject)) {
        $mail->clearSubject();
        $mail->setSubject($this->subject);
    }

    // nahradi se odkazy na inline obrazky
    if (!$this->cidReplaced) {
        $this->replaceCid(); // pozor, dochazi ke zmene $this->body
    }

    // vymazou se tagy attach
    if (!$this->attachmentsRemoved) {
        $this->removeAttachments(); // pozor, dochazi ke zmene $this->body
    }

    // nastavime telo
    if (!empty($this->body))
        $mail->setBodyHtml($this->body);
    if (!empty($this->plain))
        $mail->setBodyText($this->plain);

    // pridame inline prilohy
    foreach ($this->imgsEncoded as $v) {
        $mail->createHtmlRelatedAttachment($v['content'], $v['cid'], $v['type'], $v['disposition'], $v['encoding'], $v['filename']);
    }
    // pridame attachmenty
    foreach ($this->attachmentsEncoded as $v) {
        $mail->createAttachment($v['content'], $v['type'], $v['disposition'], $v['encoding'], $v['filename']);
    }

    return $mail;
  }

  /**
   *    Vytvoří email s MIME castmi jako jsou inline obrazky a prilohy.
   *    @return array|FALSE return errors or FALSE
   */
  public function createMimeParts()
  {

    $config = Zend_Registry::get('config');         // nacte se config

    $errors = array();          // behem zpracovavani muzou nastat chyby

    if (empty($this->body))
      return;

    // vzdy se vytvori novy objekt $this->zendMail. Puvodni muze z
    // predchoziho volani obsahovat nejake MIME casti, ale behem toho mohl
    // uzivatel z tela odstranit nejake obrazky, takze se vse vytvori znovu
    // podle aktualniho body.
    $mail = $this->createZendMail();

    // pripravime pole zakodovanych obrazku s jejich Content-IDs
    $this->imgsEncoded = array();

    // base path w/o trailing slash for email content files
    $basePath = APPLICATION_PATH . '/' . trim($config->mailer->imgsaddr,'\\/');

    // regularni vyraz pro ziskani nazvu vsech obrazku, ktere chceme zakodovat
    $reg = preg_quote($config->mailer->tags->open . $config->mailer->tags->cid . $config->mailer->tags->separator, '/')
         . '(.*)'
         . preg_quote($config->mailer->tags->close, '/');
    $matches = array();
    preg_match_all("/$reg/U", $this->body, $matches);

    // pro kazdy soubor vytvorime mime cast v multipart/related obalce
    foreach ($matches[1] as $key => $val) {
        // nacte se obsah souboru
        $path = $basePath . "/$this->id/$val";
        if (!file_exists($path)
            || !is_readable($path)
            || !$content = file_get_contents($path)) {
            $errors[] = 'Nepodařilo se otevřít soubor s obrázkem pro vytvoření MIME. Soubor: ' . $val;
            continue;
        }

        $item = array(
            'cid' => '_inline_no_' . $key . '_md5_' . md5($path),
            'content' => $content,
            'type' => Utils::getMimeType($path),
            'disposition' => Zend_Mime::DISPOSITION_INLINE,
            'encoding' => Zend_Mime::ENCODING_BASE64,
            'filename' => basename($val)
        );

        // ulozi se parametre souboru pro email do pole, aby se priste mohl v
        // odkazu akorat nahradit novy cid odkaz, ale soubor se uz
        // znova nenacital
        $this->imgsEncoded[$val] = $item;
        unset($content);

    }

    // regularni vyraz pro ziskani nazvu vsech priloh
    $reg = preg_quote($config->mailer->tags->open . $config->mailer->tags->attachment . $config->mailer->tags->separator, '/')
        . '(.*)'
        . preg_quote($config->mailer->tags->close, '/');
    $matches = array();
    preg_match_all("/$reg/U", $this->body . $this->plain, $matches);

    // pole nalezenych priloh
    $this->attachmentsEncoded = array();
    foreach ($matches[1] as $val) {
        // nacte se obsah souboru
        $path = $basePath . "/$this->id/$val";
        if (!file_exists($path)
            || !is_readable($path)
            || !$content = file_get_contents($path)) {
            $errors[] = 'Nepodařilo se otevřít soubor s obrázkem pro vytvoření MIME. Soubor: ' . $val;
            continue;
        }

        $item = array(
                'cid' => null, // auto-generate content-id
                'content' => $content,
                'type' => Utils::getMimeType($path),
                'disposition' => Zend_Mime::DISPOSITION_ATTACHMENT,
                'encoding' => Zend_Mime::ENCODING_BASE64,
                'filename' => basename($val)
        );

        $this->attachmentsEncoded[$val] = $item;
        unset($content);

    }

    return $errors;     // vrati se pole chyb
  }

  /**
   *    Nahradi vsechny %%imgmime#%% za prislusny cid. Musi se volat po funkci
   *    createMimeParts, jinak je pole imgsEncoded prazdne.
   */
  public function replaceCid()
  {
    $config = Zend_Registry::get('config');         // nacte se config

    // pokud je pole znacek prazdne vratime, muze to znamenat bud, ze v
    // mailu nejsou zadne obrazky, nebo, ze je metoda volana jeste pred tim,
    // nez byly vytvoreny jednotlive MIME contenty
    if (empty($this->imgsEncoded))
      return FALSE;

    // regularni vyraz pro ziskani nazvu vsech obrazku, ktere chceme
    // zakodovat
    $reg = preg_quote($config->mailer->tags->open . $config->mailer->tags->cid . $config->mailer->tags->separator, '/')
            . '(.*)' . preg_quote($config->mailer->tags->close, '/');
    $matches = array();
    preg_match_all("/$reg/U", $this->body, $matches);

    foreach ($matches[1] as $val) {
        if(isset($this->imgsEncoded[$val])){
            // ziskame identifikator pro prislusny MIME content
            $cid = $this->imgsEncoded[$val]['cid'];
            $this->body = preg_replace(
                    '/'
                    . preg_quote($config->mailer->tags->open . $config->mailer->tags->cid . $config->mailer->tags->separator
                            . $val . $config->mailer->tags->close, '/')
                    . '/',
                    "cid:$cid",
                    $this->body
            );
        }
    }

    $this->cidReplaced = TRUE;

  }

  /**
   *    Odstrani vsechny %%attach#%%. Musi se volat po funkci
   *    createMimeParts, jinak je pole attachmentsEncoded prazdne.
   */
  public function removeAttachments()
  {
    $config = Zend_Registry::get('config');         // nacte se config

    // pokud je pole znacek prazdne vratime, muze to znamenat bud, ze v
    // mailu nejsou zadne obrazky, nebo, ze je metoda volana jeste pred tim,
    // nez byly vytvoreny jednotlive MIME contenty
    if (empty($this->attachmentsEncoded)) {
        return FALSE;
    }

    $contents = array(&$this->body, &$this->plain); //references
    foreach ($contents as &$content) { // modify referenced value
        // regularni vyraz pro ziskani nazvu vsech souboru, ktere jsou prilozeny k emailu
        $reg = preg_quote($config->mailer->tags->open . $config->mailer->tags->attachment . $config->mailer->tags->separator, '/')
                . '(.*)'
                . preg_quote($config->mailer->tags->close, '/');
        $matches = array();
        preg_match_all("/$reg/U", $content, $matches);

        foreach ($matches[1] as $val) {
            // pokud existuje encodovana priloha
            if(isset($this->attachmentsEncoded[$val])){
                // smazeme s tela znacku pro attachment
                $content = preg_replace(
                    '/' . preg_quote($config->mailer->tags->open . $config->mailer->tags->attachment
                        . $config->mailer->tags->separator . $val . $config->mailer->tags->close, '/')
                    . '/',
                    '', // empty string
                    $content
                );
            }
        }
    }

    $this->attachmentsRemoved = TRUE;

  }

  /**
   *    Vytvori novou instanci tridy Zend_Mail.
   *    @return Zend_Mail
   */
  private function createZendMail()
  {
    return $this->zendMail = new Zend_Mail('UTF-8');
  }
}
