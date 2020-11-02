<?php

class IBulletinMailerException extends Exception {}
class IBulletinMailerPersonalizationExc extends Exception {}
class IBulletinMailerSendMailExc extends Exception {}



/**
 * Třída Mailer slouží k hromadnému rozesílání mailů z tabulky users_emails
 * a o správu těchto údajů.
 * Použití:
 *  v kódu se tato třída používá následovně.
 *
 *  try
 *  {
 *      // config musi obsahovat nastaveni maileru
 *      // ($config->mailer) a tabulky ($config->tables).
 *      $mailer = new IBulletinMailer($config, $db);
 *      // lze pred samotnym odeslanim nastavit par veci, napr. chovani pri
 *      // chybe, prirazeni objektu pro urcovani pohlavi atd. Viz public metody
 *      // a jejich komentare.
 *      // Pokud chceme napriklad urcovat pohlavi uzivatelu podle nejakeho
 *      // sloupce 'pohlavi' z tabulky users, ktere v konkretni verzi webu muze
 *      // byt, ale standardne v tabulce neni, musi se vytvorit trida, ktera dedi
 *      // od abstraktni tridy genderAssigner a jeji instance priradit maileru
 *      // pomoci metody setGenderAssigner. Podrobnejsi informace jsou u
 *      // komentaru k temto tridam a metodam nize.
 *      // $ga = new NasObjektProUrcovaniPohlavi();
 *      // $mailer->setGenderAssigner($ga);
 *
 *      // Dalsim dulezitym nastavenim je chovani pri chybe, bud pri prvni chybe
 *      // (chybou muze byt personalizace emailu, chyba pri odeslani atd.) se
 *      // odesilani zastavi (nasteveni CANCEL_ALL) a vyvola se vyjimka, nebo se
 *      // bude v odesilani pokracovat (KEEP_SENDING). Podrobnejsi info. viz
 *      // prislusne public metody a promenna $errorHandling.
 *      // $mailer->setCancelAllEH();
 *
 *      $mailer->sendEmails();      // hlavni metoda pro odeslani mailu
 *      // po odeslani si lze prohlidnou chyb, ktere mohli nastat pri odesilani
 *      // viz metody anyErrors() a getErrors(). Musi byt ale nastaveno
 *      // KEEP_SENDING.
 *      // if ($mailer->anyErrors())
 *      // {
 *      //     $errors = $mailer->getErros();
 *      // }
 *  }
 *  catch (IBulletinMailerException $e)
 *  {
 *      // vyjimka se musi osetrit. Trida IBulletinMailer nepouziva
 *      // f_error_handler.php, aby byla co nejmene zavisla na prostredi,
 *      // takze vsechny chyby, ktere chceme logovat, je nutne zalogovat tady.
 *      f_make_log($e->getMessage());
 *  }
 *
 * @author Martin Krčmář
 */

class Ibulletin_Mailer
{
    /**
     * @var Zend_Db_Adapter_Abstract
     */
    private $db;

    /**
     *  Konfigurační soubor musí obsahovat nastavení připojení k databázi
     *  a nastavení maileru. Tzn. speciální značky, které se potom budou
     *  v mailu nahrazovat za oslovení atd.
     */
    private $config;

    /**
     *  Jestli se budou odesílat emily i smazaných uživatelů. Pokud je is NULL,
     *  odesílají se pouze emaily uživatelů, kteří nejsou smazaní.
     *  Pro nastavování se používají metody sendDeletedToo() a dontSendDeleted().
    *  Změna také ovlivní zobrazování smazaných uživatelů v administraci.
     */
    private $deleted = 'is NULL';

    /**
     *  Urcuje, jestli se ma mail poslat i uzivatelum, kteri nemaji nastaveno registered,
     *  ci send emails. Pouzivame pri odesilani mailuu informujicich o nejakych akcich, jako je
     *  polozeni dotazu v poradne nebo preposlani mailu priteli.
     *
     *  @var bool
     */
    private $ignoreRegAndSend = false;

    /**
     *  Urcuje, jestli se maji posilat maily i uzivatelum, kteri maji nastaveno users.bad_addr.
     *  Pokud je true, posilaji se maily i uzivatelum s nastavenym users.bad_addr.
     *
     *  @var bool
     */
    private $ignoreBadAddr = false;

    /**
     * Urcuje, jestli budou e-maily skutecne odeslany, nebo jestli budou jen nagenerovana
     * vsechna data pro tyto maily. Pokud je nastaven na TRUE, zadne maily nebudou odeslany ven.
     *
     * @var bool
     */
    private $_sendFake = false;

    /**
     * Urcuje datum, ke kteremu byly odeslany emaily.
     *
     * @var DateTime
     */
    private $_sendFakeDatetime;

    /**
     * Uchovava informaci o tom, ktere emaily z users_emails byly pri sendEmails() odeslany.
     */
    public $sentEmails = array();

    /**
     * Uchovava informaci o tom, ktere emaily z users_emails pri odesilani pomoci sendEmails() selhaly.
     */
    public $unsentEmails = array();

    /**
     * @var Ibulletin_MailerTags
     */
    private $mailerTags = null;


    const WORKER_LOCK_FILE = 'pub/others/mailer.lock';
    const WORKER_CMD = 'utils/mailer_worker.php';
    /**
     *  Jak se má mailer chovat v případě, že se během odesílání emailů vyskytne
     *  chyba u nějakého emailu. Buď se může odesílání úplně zrušit -
     *  CANCEL_ALL, nebo se může pokračovat v odesílání dalších emailů v řadě -
     *  KEEP_SENDING.
     */
    private $errorHandling = self::KEEP_SENDING;
    const CANCEL_ALL = 'cancel_all';
    const KEEP_SENDING = 'keep_sending';

    /**
     *  Tyto hodnoty se nastavují do sloupce status tabulky users_emails.
     */
    const S_PER_ERR = 'personalisation_err';            // chyba pri personalizaci emailu
    const S_READED = 'readed';                          // nastavuje pouze skript generujici obrazek
    const S_BAD_ADDR = 'bad_addr';
    const S_UNDELIVERED = 'undelivered';                // nedorucene emaily
    const S_AUTOREPLIED = 'autoreplied';                // prijate nedorucenka o neprecteni emailu z duvodu napr. dovolene
    const S_DELIVERED = 'delivered';                    // prijate bounce sprava o uspesnom doruceni
    const S_SENDING = 'sending';                        // mail se odesila

    /**
     *  Sem se ukládá seznam emailů, které se nepodařilo odeslat, spolu s
     *  důvodem selhání. [users_email_id] => [důvod selhání].
     */
    private $errors = Array();
    const E_SEND_ERR = 1;               // chyba pri odesilani
    const E_SEND_BUT_STATE = 3;         // email se podarilo odeslat, ale selhalo ulozeni SENT do DB
    const E_SEND_BAD_ADDR = 5;          // spatna emailova adresa
    const E_PER_ERR = 2;                // chyba pri personalizaci
    const E_PER_BUT_STATE = 4;          // chyba pri personalizaci, ale nepodarilo se ulozit tu chybu do DB
    const E_SEND_BAD_RET = 6;           // chybna navratova adresa

    /**
    *   Konstanty pro sloupec consulting_room tabulky emails;
    */
    const CONSULTING_AUTO_REPLY = 'a';  // email, který se bude automaticky odesílat
    const CONSULTING_REPLY = 'r';       // email pro odpověď
    const CONSULTING_DEFAULT = '0';     // defaultní hodnota sloupce
    const CONSULTING_REPLY_NOTIFICATION = 'n';  // upozorneni na zodpovezeny dotaz
    const CONSULTING_NEW_NOTIFICATION = 'q';    // upozorneni na novy dotaz

    /**
     *  Regulární výraz pro kontrolu emailu.
     */
    private $emailReg = '^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.(([0-9]{1,3})|([a-zA-Z]{2,3})|(aero|coop|info|museum|name))$';

    /**
     *  Co se použije pro validaci emailů, buď Zend validátor nebo regulární
     *  výraz, co je výše.
     */
    private $useZendValidator = true;

    /**********************************************************
     *  KONSTRUKTORY
     **********************************************************/

    /**
     *  Konstruktor, vytvoří se db spojení a uloží se konfigurační soubor.
     *
     *  @param Objekt s nastavením.
     *  @param Db Handler.
     *  @throws IBulletinMailerException
     */
    public function __construct($config = null, $db = null)
    {
        !$db ?  $db = Zend_Registry::get('db') : null;
        !$config ?  $config = Zend_Registry::get('config') : null;

        // zkontroluje konfig, pokud je v nem chyba, metoda vyvola vyjimku, ta
        // se propaguje vys, pokud je konfig v poradku, ulozi ho.
        $this->checkConfig($config);

        try
        {
            // pokud nebyl predan db, tak se pokusi vytvorit ho pomoci configu
            if (empty($db))
            {
                Zend_Loader::loadClass('Zend_Db');
                $this->db = Zend_Db::factory($config);
                $this->db->getConnection();
            }
            else
                $this->db = $db;

        }
        catch (Zend_Db_Adapter_Exception $e)
        {
            throw new IBulletinMailerException(
                'Nepodařilo se připojit k databázi: '.$e);
        }
        catch (Zend_Exception $e)
        {
            throw new IBulletinMailerException(
                'Nepodařilo se připojit k databázi: '.$e);
        }
        catch (GenderAssignerException $e)
        {
            throw new IBulletinMailerException(
                'Nepodařilo se připojit k databázi: '.$e);
        }
    }


    /**********************************************************
     *  PRIVATNI METODY
     **********************************************************/

    /**
     *  Nastaví sloupec status tabulky users_emails.
     *
     *  @param Identifikátor emailu.
     *  @param Status.
     *  @throws Zend_Db_Statement_Exception.
     */
    public function setEmailStatus($id, $status)
    {
        $data = array('status' => $status);
        $this->db->update(
            $this->config->tables->users_emails,
            $data,
            "id = $id");
    }

    /**
     *  Metoda zkontroluje, zda jsou v konfiguračním nastavení potřebné
     *  informace. Hlavně tagy, které se potom přepisují při personalizaci
     *  emailu. V případě chyby vyvolá vyjímku.
     *
     *  @throws IBulletinMailerException
     */
    private function checkConfig($config)
    {
        // zda konfig vubec obsahuje pole mailer a podpole tags
        if ($config->get('mailer') && $config->mailer->get('tags'))
        {
            if (!$config->mailer->tags->get('firstname'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag firstname');

            if (!$config->mailer->tags->get('surname'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag surname');

            if (!$config->mailer->tags->get('trackingpicture'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag trackingpicture');

            if (!$config->mailer->tags->get('email_confirm_address'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag email_confirm_address');

            if (!$config->mailer->tags->get('salutation'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag salutation');

            if (!$config->mailer->tags->get('separator'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag separator');

            if (!$config->mailer->tags->get('open'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag open');

            if (!$config->mailer->tags->get('close'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag close');

            if (!$config->mailer->tags->get('logoff'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag logoff');

            if (!$config->mailer->tags->get('link'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag link');

            if (!$config->mailer->get('logoff_address'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí logoff_address');

            if (!$config->mailer->get('pic'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí pic');

            if (!$config->mailer->get('link'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí link url');

            if (!$config->mailer->get('test'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí test');

            if (!$config->mailer->get('return_path'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybá return_path');

            if (!$config->mailer->get('imgsaddr'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí imgsaddr');

            if (!$config->mailer->get('from'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí from');

            if (!$config->mailer->get('fromName'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí fromName');

            if (!$config->mailer->tags->get('imgs'))
                throw new IBulletinMailerException(
                    'Neplatný formát konfiguračního souboru, chybí tag imgs');

            // pokud je v configu nastaveno mailer.error, tak se to pouzije
            if ($config->mailer->get('error'))
            {
                if ($config->mailer->get('error') == self::KEEP_SENDING ||
                    $config->mailer->get('error') == self::CANCEL_ALL)
                    $this->errorHandling = $config->mailer->get('error');
                else
                    throw new IBulletinMailerException(
                        'Neplatný formát konfiguračního souboru, špatná hodnota v \'error\'.');
            }

            $this->config = $config;        // konfig je v poradku, tak ho ulozime
        }
        else
        {
            throw new IBulletinMailerException(
                'Neplatný formát konfiguračního souboru, chybí nastavení pro mailer');
        }
    }

    /**
     *  Metoda pro ukládání chyb při odesílání emailů.
     *
     *  @param Identifikátor emailu z tabulky users_emails.
     *  @param Příčina chyby, např. chyba při personalizaci, odesílání ...
     */
    private function error($id, $reason)
    {
        //array_push($this->errors, Array($id => $reason));
        $this->errors[$id] = $reason;
    }

    /**
    *   Vytvoří pole deserializovanych emailů do promenne emails tohoto objektu.
    *   Parameter ids obsahuje pole idecek tabulky emails
    *   Při personalizaci se potom budou vytvářet kopie těchto objektů,
    *  ty dále personalizovat a potom odesílat.
    *   @param ids array of email ids
    *   @throws IBulletinMailerException.
    */
    private function createEmails(array $ids)
    {

        // ziskaji se konretni emaily z tabulky emails, ktere maji byt odeslany
        $select = $this->db->select()
            ->from(array('e' => $this->config->tables->emails))
            -> where('id in (?)',$ids);

        try
        {
            $emails = $this->db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new IBulletinMailerException(
                'Nepodarilo se vykonat SQL dotaz: '.$select->__toString());
        }

        foreach ($emails as $email)
        {
            // vytvori se pole deserializovanych emailu
            Zend_Loader::loadClass('Ibulletin_Email');
            $this->emails[$email->id] = Ibulletin_Email::createInstance($email->body);

            // u kazdeho emailu se uz tady nahradi znacky. Je to kvuli
            // zrychleni, jinak by se u kazde vytvareni instance Zend_Mail
            // objektu v metode sendEmails znovu a znovu zbytecne volala tato
            // funkce pro kazdy novy klonovany mail.
            $this->emails[$email->id]->replaceCid();
        }
    }

    /**
     * shuffle rows of array based on domain part of the email key
     * algorithm is command & conquer: first iteration, distribute rows based on unique domain part
     * second iteration, round-robin each domain and distribute rows accordingly
     * @param array $rows
     * @return array of evenly shuffled rows based on domain part of the email key
     */
    private function _shuffleEmails(array $rows) {

        $res = array();
        $parts = array();
        $lookup = array();

        // first iteration, command
        foreach ($rows as $row) {
            // split email and extract domain
            $arr = explode('@',$row->email);
            $domain = $arr[1];
            // push into stack
            $parts[$domain][] = $row;
        }

        $lookup = array_keys($parts);

        // second iteration, conquer
        for ($i=0; $i<count($rows);$i++) {
            // do-while (repeat-until like) loop
            $row = null;
            while ($row === null) {
                // current pointer in lookup array
                $v = current($lookup);
                $k = key($lookup);
                // pop from stack
                $row = array_shift($parts[$v]);
                // reduce lookup array
                if (empty($parts[$v])) {
                    unset($lookup[$k]);
                }

                // cycle through lookup array
                if (next($lookup) === false) {
                    reset($lookup);
                }
            }
            $res[] = $row;
        }
        return $res;
    }

    /**********************************************************
     *  PUBLIC METODY
     **********************************************************/

    /**
     * vraci tridu pro preklad znacek v emailu
     * @return Ibulletin_MailerTags
     */
    public function getMailerTags() {
        if (!$this->mailerTags) {
            $this->mailerTags = new Ibulletin_MailerTags($this->config, $this->db);
        }
        return $this->mailerTags;
    }
    /**
    *   Metoda nastaví vlastnosti odesílání, například Zand_Mail_Transport.
    *
    *   @throws IbulletinMailerException.
    */
    public function prepareSending()
    {
        Zend_Loader::loadClass('Zend_Mail_Transport_Sendmail');
        Zend_Loader::loadClass('Zend_Validate_EmailAddress');
        Zend_Loader::loadClass('Zend_Mail');

        // zkontrolujeme platnost te adresy
        $validator = new Zend_Validate_EmailAddress();
        if ($validator->isValid($this->config->mailer->return_path))
        {
            $transport = new Zend_Mail_Transport_Sendmail('-f'.$this->config->mailer->return_path);
            Zend_Mail::setDefaultTransport($transport);
        }
        else
            throw new IbulletinMailerException(
                'Nepodařilo se odeslat email, neplatná return_path adresa.');
    }

    /**
     *  Metoda pro odeslání jednoho emailu.
     *
     * V zaznamu k odeslani mailu, ktery neobsahuje emailovou adresu, je tento zaznam smazan
     * z tabulky users_emails, aby nedoslo k obtezovani uzivatele v pripade pozdejsiho
     * doplneni mailove adresy.
    *
    * @param array/StdClass           Radek uzivatele z tabulky users doplneny o users_emails_id.
    * @param Zend_Mail/Ibulleti_Email Objekt Emailu, který se bude odesílat. Už objekt třídy Zend_Mail.
     * @throws IBulletinMailerSendMailExc.
     */
    public function sendMail($row, $mail)
    {
        Zend_Loader::loadClass('Zend_Mail');
        Zend_Loader::loadClass('Ibulletin_Email');
        Zend_Loader::loadClass('Zend_Validate_EmailAddress');

        $sendFake = $this->getSendFake();

        // Pokud je radek z users pole, predelame ho na StdClass
        if(is_array($row) && !($row instanceof StdClass)){
            $row = (object)$row;
        }

        if (!$row->users_emails_id)
            throw new IBulletinMailerSendMailExc(
                'Nepodařilo se odeslat email, chybí ID z tabulky users_emails',
                self::E_SEND_ERR);

        // Pokud je mail typu Ibulletin_Email, nejprve ziskame Zend_Mail
        if($mail instanceof Ibulletin_Email){
            $mail = $mail->getZendMail();
        }

        if ($this->config->mailer->debug)
        {
            $mail->addTo($this->config->mailer->debugEmail);
        }
        else
        {
            if (!empty($row->email) || $sendFake)
            {
                if(!$sendFake){
                    // Provedeme oseknuti netisknutelnych znaku kolem adresy - casto staci k zvalidneni,
                    // nemelo by zpusobovat zadne problemy u validnich mailuu
                    $row->email = trim($row->email);

                    if ($this->useZendValidator)
                    {
                        $validator = new Zend_Validate_EmailAddress();
                        if ($validator->isValid($row->email))           // zkontroluje se platnost adresy
                            $mail->addTo($row->email);
                        else
                            throw new IBulletinMailerSendMailExc(
                                "Nepodařilo se odeslat email, neplatná emailová adresa. ID: $row->users_emails_id",
                                self::E_SEND_BAD_ADDR);
                    }
                    else
                    {
                        if (preg_match('/'.preg_quote($this->emailReg).'/i', $row->email))
                            $mail->addTo($row->email);
                        else
                            throw new IBulletinMailerSendMailExc(
                                "Nepodařilo se odeslat email, neplatná emailová adresa. ID: $row->users_emails_id",
                                self::E_SEND_BAD_ADDR);
                    }
                }
            }
            else{
                // Uzivatel nema e-mailovou adresu, je nutne smazat zaznam z tabulky
                // users-emails, aby se v pripade doplneni emailu nestal uzivatel cilem mnoha
                // emailuu.
                $db = Zend_Registry::get('db');
                $db->delete('users_emails', 'id = '.$row->users_emails_id);
                return;

                /*
                throw new IBulletinMailerSendMailExc(
                    "Nepodařilo se odeslat email, chybí emailová adresa. ID: $row->users_emails_id",
                    self::E_SEND_ERR);
                //*/
            }
        }

        $repSender = array();


        //podle nastaveni ziskame data repa
        if ($this->config->mailer->useRepAsSender && $row->reps) {

            $reps = explode(',',$row->reps);
            $repId = reset($reps);
            $rep = Users::getUser($repId);

            if($rep['email']) {
                $repSender = $rep;
            }

        }

        //nastavime repa jako odesilatele, je-li tak nastaveno a je k dispozici
        if ($repSender) {
        	$mail->clearFrom();
            $mail->setFrom($repSender['email'],$repSender['name'].' '.$repSender['surname']);
        }
        // odesilatel, pokud jiz neni nastaven v objektu emailu.
        // $mail->getFrom() by nyni melo byt vzdy prednastavene (podle configu nebo specialen vyplnenych udaju v konkretnim emailu)
        elseif ($this->config->mailer->get('from') && $this->config->mailer->get('fromName') && !$mail->getFrom())
            $mail->setFrom(
                $this->config->mailer->from,
                $this->config->mailer->fromName
            );

        // nastaveni return path
        $hash = Ibulletin_Bounce::verpEncrypt(array(
            $this->config->database->params->dbname,
            $row->users_emails_id
        ));
        $return_path = Ibulletin_Bounce::verpEncode(
            $this->config->mailer->return_path,
             implode('-', array($hash, $row->email))
        );

        Zend_Mail::getDefaultTransport()->parameters = '-f'.$return_path;
        $mail->setReturnPath($return_path);

        if (!($mail instanceof Zend_Mail) ||
            (($mail->getBodyHtml() === false || $mail->getBodyHtml()->getContent() === '') &&
            ($mail->getBodyText() === false || $mail->getBodyText()->getContent() === '')))
        {
            throw new IBulletinMailerSendMailExc(
                "Nepodařilo se odeslat email, chybí text emailu. ID: $row->users_emails_id",
                self::E_SEND_ERR);
        }


        // ============== ODESLANI MAILU ===================
        if(!$sendFake){
            $success = $mail->send();
        }
        else{
            $success = true;
        }
        // ============== ============== ===================


        // Kontrola uspechu odeslani a veci po fyzickem odeslani, uspech je vzdy, kdyz odesilame fake
        if ($success)
        {
            // nastavit v tabulce users_emails sloupec sent
            $data = array('sent' => new Zend_Db_Expr('current_timestamp'));
            if ($sendFake) {
                $data['sent'] = $this->getSendFakeDatetime();
            }

            try
            {
                $this->db->update(
                    $this->config->tables->users_emails,
                    $data,
                    "id = $row->users_emails_id");
            }
            catch (Zend_Db_Statement_Exception $e)
            {
                // nepodarilo se ulozit do DB cas odeslani emailu, ale email se
                // uz odeslal.
                throw new IBulletinMailerSendMailExc(
                    "Nepodařilo se nastavit pole sent, ale email byl odeslán. ID: $row->users_emails_id",
                    self::E_SEND_BUT_STATE);
            }
        }
        else
        {
            throw new IBulletinMailerSendMailExc(
                'Nepodařilo se odeslat email, selhala funkce $mail->send().',
                self::E_SEND_ERR);
        }
    }

    /**
     *  Pomocí metody lze nastavit posílání emailů i smazaným uživatelům, kteří
     *  ale ještě mají nějaké emily v users_emails. Defaultně se těmto
     *  uživatelům nic neposílá.
     */
    public function sendDeletedToo()
    {
        $this->deleted = '';
        return $this;
    }

    /**
     *  Viz metoda sendDeletedToo.
     */
    public function dontSendDeleted()
    {
        $this->deleted = 'is NULL';
        return $this;
    }

    /**
     * Vraci hodnotu nastaveni jestli se ma ignorovat nastaveni users.bad_addr. Tedy posilat i na
     * maily s pravdepodobne spatnou adresou.
     *
     * @return bool     Aktuani nastaveni ignoreBadAddr.
     */
    public function getIgnoreBadAddr()
    {
        return $this->ignoreBadAddr;
    }

    /**
     * Nastavi hodnotu nastaveni jestli se ma ignorovat nastaveni users.bad_addr. Tedy posilat i na
     * maily s pravdepodobne spatnou adresou.
     *
     * @param bool     Nove nastaveni ignoreBadAddr.
     */
    public function setIgnoreBadAddr($ignoreBadAddr)
    {
        $this->ignoreBadAddr = (bool)$ignoreBadAddr;
    }

    /**
     * Urcuje, jestli budou e-maily skutecne odeslany, nebo jestli budou jen nagenerovana
     * vsechna data pro tyto maily. Pokud je nastaven na TRUE, zadne maily nebudou odeslany ven.
     *
     * @return bool     Aktuani nastaveni ignoreBadAddr.
     */
    public function getSendFake()
    {
        return $this->_sendFake;
    }

    /**
     * Urcuje, jestli budou e-maily skutecne odeslany, nebo jestli budou jen nagenerovana
     * vsechna data pro tyto maily. Pokud je nastaven na TRUE, zadne maily nebudou odeslany ven.
     *
     * @param bool     Nove nastaveni sendFake.
     */
    public function setSendFake($val)
    {
        $this->_sendFake = (bool)$val;
    }

    /**
     * Urcuje datum, ke kteremu byly odeslany emaily.
     *
     * @return DateTime     Aktualni nastaveni sendFakeDatetime.
     */
    public function getSendFakeDatetime()
    {
        return $this->_sendFakeDatetime;
    }

    /**
     * Urcuje datum, ke kteremu byly odeslany emaily.
     *
     * @param DateTime     Nove nastaveni sendFakeDatetime.
     */
    public function setSendFakeDatetime($val)
    {
        $this->_sendFakeDatetime = $val;
    }

    /**
     * Nastavi jestli se ma ignorovat nastaveni SendEmail a Registred daneho uzivatele
     * kvuli odesilani nekterych mailu, jako je informace o polozeni dotazu v poradne,
     * nebo preposlani znamemu.
     *
     * @return Ibulletin_Mailer $this instance tohoto objektu
     */
    public function setIgnoreRegisteredAndSendEmails($val)
    {
        $this->ignoreRegAndSend = $val;
        return $this;
    }

    /**
     * Vraci hodnotu nastaveni jestli se ma ignorovat nastaveni SendEmail a Registred daneho uzivatele
     * kvuli odesilani nekterych mailu, jako je informace o polozeni dotazu v poradne,
     * nebo preposlani znamemu.
     *
     * @return bool     Aktuani nastaveni ignoreRegAndSend.
     */
    public function getIgnoreRegisteredAndSendEmails()
    {
        return $this->ignoreRegAndSend;
    }

    /**
     * Vraci cast SQL dotazu do WHERE clausule pro vybrani uzivatelu v zavislosti na
     * nastaveni ignoreRegAndSend.
     * @param string $usersTabPrefix     Prefix tabulky uzivatelu v danem dotazu, default bez.
     */
    public function getIgnoreRegisteredAndSendEmailsWhere($usersTabPrefix = ''){
        if(!empty($usersTabPrefix)){
            $usersTabPrefix .= '.';
        }

        if(!$this->getIgnoreRegisteredAndSendEmails()){
            return '('.$usersTabPrefix.'registered IS NOT NULL AND '.$usersTabPrefix.'send_emails)';
        }
        else{
            return '1 = 1';
        }
    }

    /**
     *  Nastavení chování mailer při chybě během odesílání. Zruší se celé
     *  odesílání.
     *
     * @return Ibulletin_Mailer $this instance tohoto objektu
     */
    public function setCancelAllEH()
    {
        $this->errorHandling = self::CANCEL_ALL;
        return $this;
    }

    /**
     *  Nastavení chování mailer při chybě během odesílání. Bude se pokračovat v
     *  odesílání.
     */
    public function setKeepSendingEH()
    {
        $this->errorHandling = self::KEEP_SENDING;
        return $this;
    }

    /**
     *  Metoda pro přiřazení objektu, který se bude starat o určování pohlaví
     *  uživatelů.
     *
     *  @param GenderAssigner
     */
    public function setGenderAssigner(GenderAssigner $assigner)
    {
        $this->genderAssigner = $assigner;
        return $this;
    }

    /**
     *  Metoda vrátí seznam chyb, které se mohly objevit během odesílání emailů.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     *  Metoda vrací TRUE, pokud se vyskytla během odesílání nějaká chyba. Jinak
     *  FALSE.
     */
    public function anyErrors()
    {
        return !empty($this->errors);
    }

    /**
    *   Vrátí pole chyb, stejně jako getErrors(), ale místo čísel chyb, nějaké
    *   vysvětlení chyby.
    */
    public function getErrorsDescribed()
    {
        $retErrors = Array();
        if ($this->anyErrors())
        {
            $select = new Zend_Db_Select($this->db);
            $select->from(array('u' => 'users'))
                ->join(array('ue' => 'users_emails'), 'u.id = ue.user_id')
                ->where('ue.id=:ueid');

            foreach ($this->getErrors() as $ueId => $reason)
            {
                switch ($reason)
                {
                    case Ibulletin_Mailer::E_SEND_ERR :
                        $description = 'chyba při odesílání';
                        break;
                    case Ibulletin_Mailer::E_SEND_BUT_STATE :
                        $description = 'email odeslán, selhalo uložení SENT do DB';
                        break;
                    case Ibulletin_Mailer::E_SEND_BAD_ADDR :
                        $description = 'chybná emailová adresa';
                        break;
                    case Ibulletin_Mailer::E_PER_ERR :
                        $description = 'chyba při personalizaci emailu';
                        break;
                    case Ibulletin_Mailer::E_PER_BUT_STATE :
                        $description = 'chyba při personalizaci, nepodařilo se uložit chybu do BD';
                        break;
                    case Ibulletin_Mailer::E_SEND_BAD_RET :
                        $description = 'chyba při odesílání, neplatná návratová adresa, return_path';
                        break;
                    default :
                        $description = 'neznámá chyba';
                        break;
                }

                // Ziskame info o uzivateli
                //$fetchMode = $this->db->getFetchMode();
                //$this->db->setFetchMode(Zend_Db::FETCH_OBJ);
                $rows = $this->db->fetchAll($select, array('ueid' => $ueId), Zend_Db::FETCH_OBJ);
                //$this->db->setFetchMode($fetchMode);
                $userRow = $rows[0];

                $error =  "Nepodařilo se odeslat email uživatele ID: '".$userRow->user_id."',
                            email: '".$userRow->email."', důvod: $description\n";
                $retErrors[$ueId] = $error;
            }
        }

        return $retErrors;
    }

    /**
    *   Metoda najde a vrátí ID emailu, který se používá jako automatická
    *   odpověd po položení dotazu v právní poradně.
    *
    *   @throws IBulletinMailerException.
    */
    public function getConsultingAutoReplayId()
    {
        $select = $this->db->select()
            ->from($this->config->tables->emails,
                array('id'))
            ->where('consulting_room = ?', self::CONSULTING_AUTO_REPLY);

        try
        {
            // nactou se vsechny emaily k odeslani
            //$fetchMode = $this->db->getFetchMode();
            //$this->db->setFetchMode(Zend_Db::FETCH_OBJ);
            $result = $this->db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
            //$this->db->setFetchMode($fetchMode);
            if (!empty($result))
                return $result->id;
            else
                return 0;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            // Nastavime zpet fetch mode
            //$this->db->setFetchMode($fetchMode);

            throw new IBulletinMailerException(
                'Nepodarilo se vykonat SQL dotaz: '.$select->__toString());
        }
    }

    /**
    *   Vrátí email, který se používá při odpovědi na dotaz z právní poradny.
    */
    public function getConsultingReplyEmail()
    {
        $select = $this->db->select()
            ->from($this->config->tables->emails)
            ->where('consulting_room = ?', self::CONSULTING_REPLY);

        try
        {
            // nactou se vsechny emaily k odeslani
            //$fetchMode = $this->db->getFetchMode();
            //$this->db->setFetchMode(Zend_Db::FETCH_OBJ);
            $result = $this->db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
            //$this->db->setFetchMode($fetchMode);
            return $result;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            // Nastavime zpet fetch mode
            //$this->db->setFetchMode($fetchMode);

            throw new IBulletinMailerException(
                'Nepodarilo se vykonat SQL dotaz: '.$select->__toString());
        }
    }

    /**
    * Funkce vraci email podle jeho id
    * @param $id id emailu
    * @return retezec emailu
    */
    public function getEmailById($id)
    {
        $select = $this->db->select()
            ->from(array('ue' => $this->config->tables->users_emails),
                array('users_emails_id' => 'id', '*'))
            ->join(array('u' => $this->config->tables->users),
                'u.id = ue.user_id')
            ->where('users_emails_id = ?',$id);


        try
        {
            // nactou se vsechny emaily k odeslani
            //$fetchMode = $this->db->getFetchMode();
            //$this->db->setFetchMode(Zend_Db::FETCH_OBJ);
            $result = $this->db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
            //$this->db->setFetchMode($fetchMode);
            $res = $result->email;
            return $res;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            // Nastavime zpet fetch mode
            //$this->db->setFetchMode($fetchMode);

            throw new IBulletinMailerException(
                'Nepodarilo se vykonat SQL dotaz: '.$select->__toString().' Puvodni vyjimka: '."\n$e");
        }
    }

    /**
    *   Vrátí email z právní poradny, podle zadaného typu.
    *
    *   @param Typ emailu.
    */
    public function getConsultingEmail($type)
    {
        $select = $this->db->select()
            ->from($this->config->tables->emails)
            ->where('consulting_room = ?', $type);

        try
        {
            // nactou se vsechny emaily k odeslani
            //$fetchMode = $this->db->getFetchMode();
            //$this->db->setFetchMode(Zend_Db::FETCH_OBJ);
            $result = $this->db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
            //$this->db->setFetchMode($fetchMode);
            return $result;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            // Nastavime zpet fetch mode
            //$this->db->setFetchMode($fetchMode);

            throw new IBulletinMailerException(
                'Nepodarilo se vykonat SQL dotaz: '.$select->__toString());
        }
    }

    /**
     * vraci query pro ziskani emailu urcene k rozesilce z fronty
     *
     * @param boolean $withSent zahrnout podminku na sent IS NULL, default true
     * @return Zend_Db_Select
     */
    public function getEmailsForSendoutQuery($withSent = true) {
        // vsechny emaily, ktere maji byt odeslany, sent = NULL
        $sel = $this->db->select()
            ->from(array('ue' => $this->config->tables->users_emails),
                array('users_emails_id' => 'id', '*', new Zend_Db_Expr("array_to_string(ARRAY(SELECT ur.repre_id FROM users_reps ur WHERE user_id = u.id), ',') AS reps")))
            ->join(array('u' => $this->config->tables->users),
                'u.id = ue.user_id')
            ->where('test in ?', new Zend_Db_Expr($this->config->mailer->test))
            ->where('status is NULL'); // nebudou se brat napr. emaily, pri kterych doslo k chybe v personalizaci

        if ($this->deleted) {
            $sel->where('deleted ?', new Zend_Db_Expr($this->deleted));
        }

        //paklize chceme zend_mail, nechceme filtrovat odeslane maily
        if ($withSent) {
            $sel->where('sent is NULL');
        }
        return $sel;

    }
    /**
     *  Metoda řídí odesílání emailů. Nejdříve vybere z tabulky users_emails
    *  všechny záznamy, které se budou odesílat. Potom získá z DB seznam
    *  emailů, kterých se to bude týkat. Dále nastaví odesílání a nakonec začne
    *  pro každý záznam volat metody pro nahrazení tagů a odesílání.
    *
    *  @param int $id   Odeslat pouze mail s danym id.
     * @param bool      Pouze vratit zend_mail pro zobrazeni online mailu?
     * @param callable  $callback
     */
    public function sendEmails($user_email_id = null, $onlyGetZendMail = false, $callback = null)
    {

        // pripravi parsovani emailoveho linku
        $this->getMailerTags()->setOnlineMailLink($onlyGetZendMail);
        $select = $this->getEmailsForSendoutQuery(!$onlyGetZendMail);
        // nactou se vsechny emaily, ktere maji byt odeslany, sent = NULL

        // pokud je nadefinovano id, tak odesli pouze tento jeden email
        if (!empty($user_email_id) && is_numeric($user_email_id)) {
            $select->where('ue.id = ?', $user_email_id);
        }

        try
        {
            // nactou se vsechny emaily k odeslani
            $result = $this->db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new IBulletinMailerException(
                'Nepodarilo se vykonat SQL dotaz: '.$select->__toString());
        }
        // vytvori pole emailu pro vysledny seznam emailu, ktere se budou klonovat, presonalizovat a
        // odesilat
        if (!$result) {
        throw new IBulletinMailerException(
            'Empty email queue!');
        }
        $ue_ids = $email_ids = array();
        foreach ($result as $row) {
            $email_ids[] = $row->email_id;
            $ue_ids[] = $row->users_emails_id;
        }
        $this->createEmails($email_ids);
        // nastavi se nejake vlastnosti odesilani
        $this->prepareSending();
        // nastavit stav, ze se maily budou odesilat
        $this->db->update(
            $this->config->tables->users_emails,
            array('status' => self::S_SENDING),
            $this->db->quoteInto('id IN (?)', $ue_ids)
        );

        // no of emails for sendout
        $total = count($result);
        // reset counter
        $cnt = 0;

        // zavola se callback pred rozesilkou
        if ($callback) {
            call_user_func_array($callback, array(array(), $cnt, $total));
        }
        // sprehazime poradi emailu tak, aby stejne domeny byly co nejdal od sebe
        if($total > 1){
            $result = $this->_shuffleEmails($result);
        }

        //v pripade ze chceme jen object zalozime pole pro objekty
        if ($onlyGetZendMail) $zmail = array();


        // nastaveni stavu odesilani
        foreach ($result as $row)           // zpracovani jednotlivych emailu
        {
            // Aby to pri dlouhych frontach nevyskakovalo na timeout, nastavime pri
            // kazdem mailu novy timelimit, coz znamena, ze kazdy mail ma na zpracovani prave
            // tento limit - pocitadlo casu se nuluje pri nastaveni noveho limitu
            set_time_limit(20);
            // increase counter
            $cnt++;

            $mail = clone $this->emails[$row->email_id];

            // zacatek transakce
            if (!$onlyGetZendMail) {
                $this->db->beginTransaction();
            }

            try
            {
                // u kazdyho emailu se nahradi ty specialni znacky
                $this->getMailerTags()->parseTags($row, $mail);

                //odesilame paklize nechceme zend_mail object
                if (!$onlyGetZendMail) {

                    // ted uz muzeme ziskat objekt Zend_Mail, ktery se bude odesilat
                    $zendMail = $mail->getZendMail();
                    // odesle se email
                    $this->sendMail($row, $zendMail);
                    // konec transakce
                    $this->db->commit();


                    // Zapiseme uspesny email
                    $this->sentEmails[] = $row->users_emails_id;
                } else {
                   $zmail[$row->users_emails_id] = $mail->getZendMail();
                }

                // nastavit status jako odeslany email
                $this->setEmailStatus($row->users_emails_id, null);

                // zavola se callback ihned po odeslani emailu
                if ($callback) {
                    call_user_func_array($callback, array($row, $cnt, $total));
                }

            }
            catch (IBulletinMailerPersonalizationExc $e)
            {
                //neresime jestli jestli ziskavame jen zend_mail object
                if ($onlyGetZendMail) continue;

                // nepodarilo se personalizovat email
                $this->db->rollBack();

                // Zapiseme neuspesny email
                $this->unsentEmails[] = $row->users_emails_id;

                $this->error($row->users_emails_id, self::E_PER_ERR);    // ulozi se chyba
                try
                {
                    // ulozit chybu o personalizaci emailu do DB
                    $this->setEmailStatus($row->users_emails_id, self::S_PER_ERR);
                }
                catch (Zend_Db_Statement_Exception $ze)
                {
                    // prepise se chyba
                    $this->error($row->users_emails_id, self::E_PER_BUT_STATE);
                }

                // zavola se callback i pri neuspechu personalizovat
                if ($callback) {
                    call_user_func_array($callback, array($row, $cnt, $total));
                }

                // ted se bude bud zkouset posilat dalsi maily, nebo se vse
                // ukonci
                switch ($this->errorHandling)
                {
                    case self::KEEP_SENDING :
                        break;

                    case self::CANCEL_ALL :
                        throw new IBulletinMailerException('Puvodni vyjimka:'."\n".$e);
                        break;

                    default :
                        throw new IBulletinMailerException(
                            'Chyba při odesílání emailů, ale není nastaveno chování při chybě během odesílání.');
                        break;
                }
            }
            catch (IBulletinMailerSendMailExc $e)
            {
                //neresime jestli jestli ziskavame jen zend_mail object
                if ($onlyGetZendMail) continue;

                // nepodarilo se odeslat email, mohla selhat funkce send, spatny
                // email, atd.
                $this->db->rollback();

                // Zapiseme neuspesny email
                $this->unsentEmails[] = $row->users_emails_id;

                $this->error($row->users_emails_id, $e->getCode());  // ulozi se chyba

                // pokud chybou byla neplatna emailova adresa, ulozi se to do DB
                if ($e->getCode() == self::E_SEND_BAD_ADDR)
                {
                    try
                    {
                        // ulozit chybu o personalizaci emailu do DB
                        $this->setEmailStatus($row->users_emails_id, self::S_BAD_ADDR);
                    }
                    catch (Zend_Db_Statement_Exception $ze)
                    {
                        // nepodarilo se ulozit udaj o spatne adrese, nic se
                        // nebude delat, tahle informace je aspon v poli errors.
                        Phc_ErrorLog::error('Mailer::sendEmails()', 'Nepodarilo se ulozit informaci o'.
                            ' nespravne emailove adrese. email_id="'.$row->users_emails_id.'" Puvodni vyjimka: '.$ze);
                    }
                }

                // zavola se callback i pri neuspesnem odeslani emailu
                if ($callback) {
                    call_user_func_array($callback, array($row, $cnt, $total));
                }
                switch ($this->errorHandling)
                {
                    case self::KEEP_SENDING :
                        break;

                    case self::CANCEL_ALL :
                        throw new IBulletinMailerException('Puvodni vyjimka:'."\n".$e);
                        break;

                    default :
                        throw new IBulletinMailerException(
                            'Chyba při odesílání emailů, ale není nastaveno chování při chybě během odesílání.');
                        break;
                }
            }
            catch (Zend_Db_Exception $e)
            {
                //neresime jestli jestli ziskavame jen zend_mail object
                if ($onlyGetZendMail) continue;

                // nepodarilo se odeslat email, mohla selhat funkce send, spatny
                // email, atd.
                $this->db->rollback();

                // Zapiseme neuspesny email
                $this->unsentEmails[] = $row->users_emails_id;

                Phc_ErrorLog::error('Mailer::sendEmails()', 'Nepodarilo se odeslat email - '.
                    'chyba pri provadeni SQL dotazu. email_id="'.$row->users_emails_id
                    .'"'."\n".' Puvodni vyjimka:'.$e);


                // zavola se callback i pri neuspesnem odeslani emailu
                if ($callback) {
                    call_user_func_array($callback, array($row, $cnt, $total));
                }
                switch ($this->errorHandling)
                {
                    case self::KEEP_SENDING :
                        break;

                    case self::CANCEL_ALL :
                        throw new IBulletinMailerException('Puvodni vyjimka:'."\n".$e);
                        break;

                    default :
                        throw new IBulletinMailerException(
                            'Chyba při odesílání emailů, ale není nastaveno chování při chybě během odesílání.');
                        break;
                }

                //throw new IBulletinMailerException($e->getMessage()); //Nepouzijeme, protoze to je opravdu na nic
            }
            catch (Exception $e){
                //neresime jestli jestli ziskavame jen zend_mail object
                if ($onlyGetZendMail) continue;

                // nepodarilo se odeslat email, mohla selhat funkce send, spatny
                // email, atd.
                $this->db->rollback();

                // Zapiseme neuspesny email
                $this->unsentEmails[] = $row->users_emails_id;

                Phc_ErrorLog::error('Mailer::sendEmails()', 'Nepodarilo se odeslat email - '.
                    'ostatni chyby. email_id="'.$row->users_emails_id
                    .'"'."\n".' Puvodni vyjimka:'.$e);

                // zavola se callback i pri neuspesnem odeslani emailu
                if ($callback) {
                    call_user_func_array($callback, array($row, $cnt, $total));
                }
                switch ($this->errorHandling)
                {
                    case self::KEEP_SENDING :
                        break;

                    case self::CANCEL_ALL :
                        throw new IBulletinMailerException('Puvodni vyjimka:'."\n".$e);
                        break;

                    default :
                        throw new IBulletinMailerException(
                            'Chyba při odesílání emailů, ale není nastaveno chování při chybě během odesílání.');
                        break;
                }
                // throw $e;
            }
        }

        if ($onlyGetZendMail) return $zmail;

        // Provedeme zapis logu
        $logMsg = $this->writeLog();

        // zavola se callback pro zobrazeni logu
        if ($callback) {
            call_user_func_array($callback, array($row, $cnt, $total,$logMsg));
        }

    }

    /**
     * checks whether worker is running
     *
     * @return bool true if worker is running
     */
    public function isWorkerRunning()
    {
        return file_exists(self::WORKER_LOCK_FILE);
    }

    /**
     * vraci data z beziciho workera
     * @return JSON
     */
    public function getWorkerData()
    {
        if (!$this->isWorkerRunning()) {
            return false;
        }

        return Zend_Json::decode(file_get_contents(self::WORKER_LOCK_FILE));
    }
    /**
     * starts the worker, can be only one running at a time
     *
     * @return Process instance of the running process, false if the worker is already running
     */
    public function startWorker()
    {
        if ($this->isWorkerRunning()) {
            return false;
        }

        $command = self::WORKER_CMD;

        //append parameter 'f' for simulated sending
        if ($this->getSendFake()) {
            $command .= " -f";
        }

        return new Process($command);
        }

    /**
     * kill the worker and cleanup the mailing queue
     *
     * @return bool true if the worker has been killed, false otherwise
     */
    public function killWorker()
    {
        if (!$this->isWorkerRunning()) {
            return false;
        }

        $data = $this->getWorkerData();

        // kill it
        exec('kill '.$data['pid']);

        // remove lock file
        if ($this->isWorkerRunning()) {
            unlink(self::WORKER_LOCK_FILE);
        }

        // update mailing queue
        $this->db->update(
            $this->config->tables->users_emails,
            array('status' => null),
            $this->db->quoteInto('status = ?', self::S_SENDING)
        );

        return true;

    }
    public function getUndeliveredQuery() {

        $select = $this->db->select()
            ->from(array('ue' => $this->config->tables->users_emails), array(
                /*'users_emails_id' => 'id', '*'*/
                '*',
        ))
            ->join(array('e' => $this->config->tables->emails),
                'e.id = ue.email_id',
                array('email_name' => 'name',
                    'email_subject' => 'subject'))
            ->join(array('u' => $this->config->tables->users),
                'u.id = ue.user_id', array(
                'name' => 'name',
                'surname' => 'surname',
                'email' => 'email',
                'group' => 'group',
                'rep_name' => new Zend_Db_Expr(
                    "array_to_string(ARRAY(SELECT COALESCE(u1.name || ' ' || u1.surname, u1.surname, u1.name) FROM users_reps ur
                            JOIN users u1 ON u1.id = ur.repre_id WHERE ur.user_id = u.id), ', ')"
                )
            )
        )
            ->where('status = ? OR status = '.$this->db->quote(self::S_BAD_ADDR), self::S_UNDELIVERED)
            ->where('u.test in ?', new Zend_Db_Expr($this->config->mailer->test));

        /*
        <th class="{sorter: false}"><?=$this->text('last_name')?></th>
        <th class="{sorter: false}"><?=$this->text('first_name')?></th>
        <th class="{sorter: false}"><?=$this->text('email')?></th>
        <th class="{sorter: false}"><?=$this->text('email_name')?></th>
        <th class="{sorter: false}"><?=$this->text('group')?></th>
        <th class="{sorter: false}"><?=$this->text('representative')?></th>
        <th class="{sorter: false}"><?=$this->text('status')?></th>
        <th class="{sorter: false}"><?=$this->text('bounce_status')?></th>
        */




        return $select;
    }
    /**
     *  Metoda najde všechny nedoručené emaily.
     *
     *  @param ID emailu, pokud je zadáno, najdou se pouze nedoručené emaily
     *  tohoto jednoho emailu.
    *  @param Kolik záznamů se má vrátit, pokus nezadáno, vrátí se všechny
    *  záznamy.
    *  @param Od kterého záznamu.
     */
    public function getUndelivered($emailId = 0, $limit = '', $offset = 1)
    {
        $select = $this->getUndeliveredQuery();

        $select->order(array('ue.created'));
        $select->limit($limit, $offset);

        // Pokud je zadan email, o ktery se jedna, pridame jej do podminky
        if ($emailId != 0) {
            $select->where("e.id = ?", $emailId);
        }

        try {
            $result = $this->db->fetchAll($select);

        } catch (Zend_Db_Statement_Exception $e) {
            throw new IBulletinMailerException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString());
        }

        return $result;
    }

    /**
    *   Vrátí počet nedoručených emailů.
    *
    *   @param Identifikátor emailu.
    */
    public function getUndeliveredCount($emailId = 0)
    {
        $select = $this->db->select()
                ->from(array('ue' => $this->config->tables->users_emails),
                    array('count' => 'count(*)'))
                ->join(array('e' => $this->config->tables->emails),
                    'e.id = ue.email_id', array())
                ->join(array('u' => $this->config->tables->users),
                    'u.id = ue.user_id', array())
                ->where('status = ? OR status = '.$this->db->quote(self::S_BAD_ADDR), self::S_UNDELIVERED)
                ->where('u.test in ?', new Zend_Db_Expr($this->config->mailer->test));

        // Pokud je zadan email, o ktery se jedna, pridame jej do podminky
        if ($emailId != 0) {
            $select->where("e.id = ?", $emailId);
        }

        try {
            $result = $this->db->fetchOne($select);
            return $result;
        } catch (Zend_Db_Statement_Exception $e) {
            throw new IBulletinMailerException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString());
        }
    }

    /**
    *   Metoda vrací pole emailů z tabulky emails. Pokud je email přiřazen k
    *   nějaké zvací vlně, tak se vybere taky.
    *
    *   @param Sort podle ceho se budou emaily tridit email/wave
    *   @param boolean $show_deleted    Maji se zobrazit smazanane emaily
    *   @throws IBulletinMailerException.
    */
    public function getEmails($sort = "email",$show_deleted = false)
    {
        if($sort == "email") {
        $select = $this->db->select()
            ->from(array('e' => $this->config->tables->emails))
            ->joinLeft(array('iw' => $this->config->tables->invitation_waves),
                'e.invitation_id = iw.id',
                array('wave_name' => 'name', 'wave_id' => 'id'))
            ->order('e.id desc');
        } elseif($sort == "wave") {
            $select = $this->db->select()
            ->from(array('e' => $this->config->tables->emails))
            ->joinLeft(array('iw' => $this->config->tables->invitation_waves),
                'e.invitation_id = iw.id',
                array('wave_name' => 'name', 'wave_id' => 'id'))
            ->order(array('iw.id desc' ,'e.id desc'));
        }

        if (!$show_deleted) {
           $select->where("deleted IS NULL");
        }

        try
        {
            //$fetchMode = $this->db->getFetchMode();
            //$this->db->setFetchMode(Zend_Db::FETCH_OBJ);
            $result = $this->db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
            //$this->db->setFetchMode($fetchMode);
            return $result;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            // Nastavime zpet fetch mode
            //$this->db->setFetchMode($fetchMode);

            throw new IBulletinMailerException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString());
        }
    }

    /**
    *   Metoda vrací pole emailů pro rozbalovací seznam.
    *   Ve tvaru [id] => [name].
    *
    *   @param Co bude na prvním místě.
    *   @throws IBulletinMailerException.
    */
    public function getEmailsRenderData($first = 'all')
    {
        // pokud vyvola vyjimku, bude se propagovat vys.
        $data = $this->getEmails();
        $retVal = Array();

        $retVal[0] = $first;
        foreach ($data as $row)
        {
            $retVal[$row->id] = $row->name;
        }

        return $retVal;
    }

    /**
    *   Metoda vrací pole emailů a zvacích vln, ve tvaru
    *   název emailu - zvací vlna.
    *
    *
    *   @param Sort podle ceho se budou emaily tridit email/wave
    *   @throws IBulletinMailerException.
    *
    */
    public function getEmailsAndWavesRenderData($sort="email")
    {

        $data = $this->getEmails($sort);
        $retVal = Array();


        $retVal[0] = '';
        foreach ($data as $row)
        {
            if (!empty($row->wave_name))
                $retVal[$row->id] = $row->id . ' - '. $row->name . ' ('. $row->wave_name.')';
            else
                $retVal[$row->id] = $row->id . ' - '. $row->name;
        }

        return $retVal;
    }

    /**
    *   Vrací seznam uživatelů z tabulky users. Používá se při přidávání osob do
    *   mailingu.
    *
    *   @param Filtr, podle kterého se filtrují uživatelé. Defaultně je tam 1 = 1,
    *          aby se vybrali všichni uživatelé.
    *   @param Podle kterého sloupce se bude výsledek řadit.
    *   @param Limit
    *   @param Offset, kvůli stránkování.
    */
    public function getUsers($filter = '1 = 1', $order = '', $limit = '', $offset = 1)
    {
        $select = $this->db->select()
                ->from(array('u' => $this->config->tables->users),
                        array('*', 'reps' => new Zend_Db_Expr("array_to_string(ARRAY(SELECT ur.repre_id FROM users_reps ur WHERE user_id = u.id ORDER BY repre_id), ',')"))
                       )
                ->where($filter)
                ->where('test in ?', new Zend_Db_Expr($this->config->mailer->test))
                ->where($this->getIgnoreRegisteredAndSendEmailsWhere())
                ->order($order);

        if ($this->deleted) {
             $select->where('deleted ?', new Zend_Db_Expr($this->deleted));
        }

        // podminka podle users.bad_addr
        if(!$this->getIgnoreBadAddr()){
            $select->where('NOT bad_addr');
        }

        if (!empty($limit))
        {
            $select->limit($limit, $offset);
        }

        try
        {
            return $this->db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new IBulletinMailerException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString()."\nPuvodni vyjimka:\n$e");
        }
    }

    /**
    *   Vrátí počet uživatelů, kteří projdou filtrem.
    *
    *   @param Filtr.
    */
    public function getUsersFiltered($filter)
    {
        $select = $this->db->select()
            ->from($this->config->tables->users,
                array('num' => 'COUNT(*)'))
            ->where('test in ?', new Zend_Db_Expr($this->config->mailer->test))
            ->where($this->getIgnoreRegisteredAndSendEmailsWhere())
            ->where($filter);

        if ($this->deleted) {
           $select->where('deleted ?', new Zend_Db_Expr($this->deleted));
        }

        // podminka podle users.bad_addr
        if(!$this->getIgnoreBadAddr()){
            $select->where('NOT bad_addr');
        }

        try
        {
            $result = $this->db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
            return $result->num;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new IBulletinMailerException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString().' Puvodni vyjimka: '.$e);
        }
    }

    /**
    *   Vrátí záznamy z tabulky users_emails. Tzn. frontu k odeslání. Vybírají
    *   se pouze ty záznamy, které ještě nabyly odeslány.
    *
    *   @param Podle čeho se budou záznamy řadit.
    *   @param Limit, počet záznamů na stránku.
    *   @param Offset, od kterého záznamu se budou vybírat.
    */
    public function getUsersEmails($order = 'surname', $limit = '', $offset = 1) {

        $select = $this->getUsersEmailsQuery()
            ->where('sent is NULL')
            ->where('test in ?', new Zend_Db_Expr($this->config->mailer->test))
            ->where('status is NULL')               // nebudou se brat napr. emaily, pri kterych doslo k chybe v personalizaci
            ->order($order);

        if ($this->deleted) {
            $select->where('deleted ?', new Zend_Db_Expr($this->deleted));
        }


        if (!empty($limit)) {
            $select->limit($limit, $offset);
        }

        try {
            return $this->db->fetchAll($select);

        } catch (Zend_Db_Statement_Exception $e) {
            throw new IBulletinMailerException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString());
        }

    }

    public function getUsersEmailsQuery() {

        $select = $this->db->select()
            ->from(array('ue' => $this->config->tables->users_emails),
                array(/*'users_emails_id' => 'id',*/ '*'))
            ->join(array('u' => $this->config->tables->users), 'u.id = ue.user_id',
                array('name' => 'name',
                    'surname' => 'surname',
                    'email' => 'email',
                    'group' => 'group',
                )
        )
            ->join(array('e' => $this->config->tables->emails), 'ue.email_id = e.id',
                array('email_name' => 'name',
                    'email_subject' => 'subject',
                )
        );

        if ($this->deleted) {
            $select->where('u.deleted ?', new Zend_Db_Expr($this->deleted));
        }

        /*echo $select->__toString();
        exit;*/
        return $select;
    }



    /**
    *   Smaže celou frontu. Všechny záznamy z users_emails, kde sent je NULL a
    *   status není nastaven. Také se jako všude zohledňují smazaní uživatelé a
    *   testovací uživatelé.
    */
    public function deleteQueue()
    {
        try
        {
            $this->db->delete($this->config->tables->users_emails,
                'sent is NULL '.
                'AND status IS NULL');
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new IBulletinMailerException(
                'Nepodařilo se smazat frontu emailů. '.$e);
        }
    }

    /**
    *   Smaže záznam z tabulky users_emails.
    *
    *   @param ID záznamu ke smazání.
    */
    public function deleteUsersEmails($id)
    {
        try
        {
            $this->db->delete(
                $this->config->tables->users_emails,
                "id = $id"
            );
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new IBulletinMailerException(
                'Nepodařilo se smazat záznam z tabulku users_emails s ID:
                '.$id.', původní vyjímka: '.$e);
        }
    }

    /**
    *   Vloží do DB nové záznamy do tabulky users_emails. Tzn. zařadí nějaké
    *   uživatele k emailu.
    *
    *   @param int Identifikátor emailu.
    *   @param string|array Filtr, podle kterého se vyberou uživatele anebo pole users.id
    *   @param int  ID uzivatele, ktery email odeslal (pokud neni zadano,
    *               pouzije se bud prihlaseny admin nebo prihlaseny uzivatel)
    *   @param array Pole extra atributu ktere se maji nastavit pri ukladani do users_emails
     *  @param int $sentByAdmin ID admina, který bude označen jako odesilatel, v pripade ze je admin prihlasen pouzije se ucet jeho
    *   @return array Pole users_emails_id, ktere byly vlozeny.
    *   @throws IBulletinMailerException.
    */
    public function enqueueEmail($emailId, $filter, $sentByUser = null, $extraAttribs = array(),$sentByAdmin = null)
    {
        $fc = Zend_Controller_Front::getInstance();
        $req = $fc->getRequest();

        // Najdeme kdo je odesilatelem mailu - uzivatele adminu nebo uzivatele webu
        $currAdminUser = null;
        $currUser = null;

        if($sentByUser){
            try{
                Users::getUser($sentByUser);
                $currUser = $sentByUser;
            }
            catch(Exception $e){
                Phc_ErrorLog::warning("Ibulletin_Mailer::enqueueEmail()",
                    "Uzivatel nastaveny jako odesilatel tohoto mailu nebyl nalezen nebo byl smazan userId: '$sentByUser'. ".
                    "Jako odesilatel bude oznacen aktualne prihlaseny uzivatel.");
            }
        }
        if(!$currUser){
            if($sentByAdmin) {
                $currAdminUser = $sentByAdmin;
            }
            elseif($req->getModuleName() == 'admin'){
                $adminAuth = Ibulletin_AdminAuth::getInstance();
                $currAdminUser = $adminAuth->user_data->id;
            }
            else{
                $currUser = Ibulletin_Auth::getActualUserId();
            }
        }

        // nejdriv se vyberou vsichni uzivatele podle filtru.
        if (is_array($filter)) {
            foreach ($filter as $id) {
                $users[]['id'] = $id;
            }
        } else {
            $users = $this->getUsers($filter);
        }

        $ids = array();

        //Phc_ErrorLog::notice('debug users', join(' | ', $users).' filter: '. $filter);

        foreach ($users as $user)
        {
            // pro kazdeho uzivatele se vlozi zaznam do tabulky users_emails.
            $data = array(
                'user_id' => $user['id'],
                'email_id' => $emailId,
                'created' => new Zend_Db_Expr('current_timestamp'));

            if($currAdminUser !== null){
                $data['sent_by_admin'] = $currAdminUser;
            }
            elseif($currUser !== null){
                $data['sent_by_user'] = $currUser;
            }

            if ($extraAttribs) {
                $data = array_merge($data, $extraAttribs);
            }

            try
            {
                $this->db->insert(
                    $this->config->tables->users_emails,
                    $data);

                // ziskame ID noveho zaznamu
                $id = $this->db->lastInsertId(
                    $this->config->tables->users_emails, 'id');
                $ids[] = $id;

                // ted se jeste k zaznamu vygeneruje token
                $token = $this->getMailerTags()->generateToken(
                    $this->config->tables->users_emails,
                    'token',
                    "id = $id");
            }
            catch (Zend_Db_Statement_Exception $e)
            {
                throw new IBulletinMailerException(
                    'Nepodařilo se zařadit uživatele do fronty pro email. ID: '.$emailId);
            }
        }

        // vrati posledni id z tabulky users_emails
        return $ids;
    }

    /**
    *   Metoda vyhledá záznam z tabulky users_emails podle zadaného emailu a
    *   adresy a nastaví u něho status na UNDELIVERED.
    *
    *   @param Emailová adresa, podle které se bude hledat.
    *   @param Identifikátor emailu.
    *   @throws IBulletinMailerException.
    *   @return Počet dotazem ovlivněných řádků.
    */
    public function setUndelivered($address, $emailId)
    {
        $data = array('status' => self::S_UNDELIVERED);
        $where[] = "email_id = $emailId";
        $where[] = "user_id in ((SELECT id FROM users WHERE email = '$address'))";

        try
        {
            return $this->db->update(
                $this->config->tables->users_emails,
                $data,
                $where);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new IBulletinMailerException(
                "Nepodařilo se nastavit stav UNDELIVERED pro email s ID: $emailId, adresa: $address");
        }
    }

    /**
    * Zapise informace o rozesilani do log souboru maileru.
    * Log soubor je definovan v configu maileru "mailer.logfile".
    * Melo by byt volano az po provedeni sendEmails().
     * @return string Vrati zapsanou message bez oddelovace
    */
    public function writeLog(){
        $logfile = $this->config->mailer->logfile;

        if(!file_exists($logfile)){
            $f = fopen($logfile, "w+");

            // Vytvoreni soboru selhalo
            if($f === false){
                Phc_ErrorLog::error('Mailer::writeLog()', 'Nepodarilo se vytvorit novy soubor logu '.
                'na zadane ceste. Soubor: "'.$logfile.'"');

                return;
            }
        }
        else{
            $f = fopen($logfile, "a+");
        }

        // Zapiseme datum
        $logMsg = "";
        $logMsg  .= new Zend_Date()."\n";


        if ($this->getSendFake()) {
            $logMsg .= "SIMULOVANÁ ROZESÍLKA\n";
        }


        // Pokud nastaly zname chyby, vypiseme je
        if($this->anyErrors()){
            $errorsDesc = $this->getErrorsDescribed();
            foreach($errorsDesc as $err){
               $logMsg .= $err."\n";
            }
        }

        // Zapiseme pocet uspesnych a neuspesnych emailu
        $logMsg .= "Emailu uspesne odeslano: ".count($this->sentEmails)."\n";
        $logMsg .= "Emailu neodeslano kvuli chybam: ".count($this->unsentEmails)."\n";

        // Vypiseme seznam ID neuspesnych emailuu
        if(!empty($this->unsentEmails)){
            $logMsg .= "ID neuspesnych emailu z users_emails:"."\n";
            foreach($this->unsentEmails as $eid){
                $logMsg .= $eid."\n";
            }
        }

        fputs($f,$logMsg);

        // Oddelovaci cara
        fputs($f, "-------------------------------------------------------------------------------"."\n"."\n");

        return $logMsg;
    }


    /**
    *   Metoda vyhledá záznam z tabulky users_emails podle ID a nastavi mu content_id
    *   @param type $name Description
    *   @param int ID Emailu
    *   @param int ID Contentu
    *   @throws IBulletinMailerException.
    *   @return int Počet dotazem ovlivněných řádků.
    */
    public function setContentId($emailId, $contentId)
    {
        $data = array('content_id' => $contentId);
        $where = $this->db->quoteInto('id = ?', $emailId);

        try
        {
            return $this->db->update(
                $this->config->tables->users_emails,
                $data,
                $where);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new IBulletinMailerException(
                "Nepodařilo se nastavit content UD pro email s ID: $emailId");
        }
    }

}
