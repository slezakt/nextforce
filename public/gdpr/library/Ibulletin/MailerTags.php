<?php

/**
 * Class Ibulletin_MailerTags
 *
 */
class Ibulletin_MailerTags {

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
     * @var array seznam tagu a jejich hodnot pro nahrazovani v obsahu
     */
    private $tags = array();

    /**
     *  Objekt slouží k určování pohlaví. Musí být zděděn od abstraktní třídy
     *  GenderAssigner.
     */
    private $genderAssigner;


    /**
     * pokud je mail generovany pro online link, nepreklada a maze se souvisejici znacka
     * @var bool
     */
    private $onlineMailLink = false;

    /**
     * zachovat link na online verzi emailu
     * @var bool
     */
    private $forceOnlineMailLinkPreserve = false;

    public function __construct($config, $db)
    {
        $this->config = $config;
        $this->db = $db;

        // vytvori se defaultni objekt pro urcovani pohlavi uzivatele, lze pote
        // nastavit jiny pomoci metody setGenderAssigner
        $this->genderAssigner = new DefaultAssigner($config, $db);

        // initial tags
        $this->addTag('forwardMailUserName','');
        $this->addTag('forwardMailText', '');
        $this->addTag('forgotPassLogin', '');
        $this->addTag('forgotPassPass', '');
    }

    /**
     * pridani/prepsani znacky a jeji nahrazovane hodnoty
     *
     * @param $tagName
     * @param $tagValue
     */
    public function addTag($tagName, $tagValue)
    {
        $this->tags[$tagName] = $tagValue;
    }

    /**
     * odebere tag
     * @param $tagName
     */
    public function removeTag($tagName) {
        unset($this->tags[$tagName]);
    }

    /**
     * @param mixed $genderAssigner
     */
    public function setGenderAssigner($genderAssigner)
    {
        $this->genderAssigner = $genderAssigner;
    }

    /**
     * @return mixed
     */
    public function getGenderAssigner()
    {
        return $this->genderAssigner;
    }

    /**
     * @param boolean $forceOnlineMailLinkPreserve
     */
    public function setForceOnlineMailLinkPreserve($forceOnlineMailLinkPreserve = true)
    {
        $this->forceOnlineMailLinkPreserve = $forceOnlineMailLinkPreserve;
    }

    /**
     * @return boolean
     */
    public function getForceOnlineMailLinkPreserve()
    {
        return $this->forceOnlineMailLinkPreserve;
    }

    /**
     * @param boolean $onlineMailLink
     */
    public function setOnlineMailLink($onlineMailLink = true)
    {
        $this->onlineMailLink = $onlineMailLink;
    }

    /**
     * @return boolean
     */
    public function getOnlineMailLink()
    {
        return $this->onlineMailLink;
    }

    /**
     *   Funkce pro generovani tokenu. Vygeneruje token a rovnou ho ulozi do DB.
     *
     * @param Tabulka , do ktere se token bude ukladat.
     * @param Sloupec , do ktereho se token bude vkladat.
     * @param Podminka where SQL dotazu.
     * @param Data pro vložení, pokud je tento parametr předán, bude se do DB
     *          vkládat celý nový řádek, ne jenom hodnota do sloupce.
     * @param Délka generovaného tokenu.
     *
     * @return Vygenerovaný token.
     * @throws IBulletinMailerException.
     */
    public function generateToken($table, $column, $where, $insertData = '', $tokenLen = 10)
    {
        Zend_Loader::loadClass('Ibulletin_RandomStringGenerator');
        if (!(isset($this->rGen)) || !($this->rGen instanceof Ibulletin_RandomStringGenerator))
            $this->rGen = new Ibulletin_RandomStringGenerator();

        // je na to 1000 pokusu
        for ($i = 0; $i < 1000; $i++) {
            try {
                $token = $this->rGen->get($tokenLen);
                // kdyz se tam vkladaji data, tak se to nemuze vkladat tim,
                // ze se to bude zkouset, dokud se to nevlozi, takze se ten
                // token nejdriv otestuje, jestli tam neni.
                if ($this->testToken($table, $column, $token)) {
                    if (!empty($insertData) && !empty($insertData['user_id']) && empty($where)) {
                        $insertData[$column] = $token;
                        $affected = $this->db->insert($table, $insertData);
                        break;
                    } elseif (!empty($where)) {
                        $data = Array($column => $token);
                        $affected = $this->db->update($table, $data, $where);
                        break;
                    } // Chybi potrebna data
                    else {
                        throw new IBulletinMailerException(
                            'Nepodario se vygenerovat token, chybi potrebna data. Musi byt zadano UserId nebo Where. ' .
                            "InsertData: " . print_r($insertData, true) . ", where: '$where'."
                        );
                    }
                } else {
                    // spatny token
                    continue;
                }
            } catch (Zend_Db_Statement_Exception $e) {
                throw new IBulletinMailerException(
                    'Nepodario se vygenerovat token. Puvodni vyjimka: ' . "\n" . $e);
            }
        }

        if (!isset($affected)) {
            throw new IBulletinMailerException(
                'Nepodario se vygenerovat token. Muze se jednat o chybu generovani nahodnych retezcu. ' .
                'Posledni retezec: "' . $token . '".');
        }

        return $token;
    }

    /**
     *   Metoda ověří, zda token je v DB nebo ne.
     *
     * @param Tabulka
     * @param Sloupec
     * @param Token
     *
     * @return TRUE pokud je token unikátní, jinak FALSE.
     */
    public function testToken($table, $column, $token)
    {
        $select = $this->db->select()
            ->from($table, array(new Zend_Db_Expr('1')))
            ->where("$column = ?", $token);

        $result = $this->db->fetchOne($select);
        if (empty($result)) {
            return true;
        }

        return false;
    }

    /**
     *  Metoda nahradí v těle emailu speciální značky za odpovídající data.
     *
     * @param array /StdClass      Radek uzivatele z tabulky users doplneny o users_emails_id a email_id.
     *                             !!user_id musi obsahovat ID uzivatele!!
     *                             Muze obsahovat polozku array tagVals obsahujici tagy primo pro daneho uzivatele.
     * @param Ibulletin_Email     Objekt Emailu, jehož tělo se personalizuje. Objekt třídy
     *                             Ibulletin_Email.
     * @param array $tagVals DEPRECATED
     *                             Misto tohoto pouzivat Ibulletin_Email->addPersonalizationItem()
     *                             Hodnoty nekterych tagu, dalsi hodnoty mohou byt ulozeny
     *                             v $this->tagVals, pripadne primo v datech uzivatele $row. Klicem
     *                             pole je nazev tagu.
     *
     * @throws IBulletinMailerPersonalizationExc.
     */
    public function parseTags($row, Ibulletin_Email $mail)
    {

        $url = new Zend_View_Helper_Url();
        $serverUrl = new Zend_View_Helper_ServerUrl();

        $texts = Ibulletin_Texts::getInstance();
        
        //pred prepnutim textu na front si podrzime aktualni
        $currentSwitchedTexts = $texts->getSwitchedTo();
        // Textum nastavime docasne jazyk z frontu
        $texts->switchFrontAdmin('front');

        // Pripravime si tagy
        $tags = $this->config->mailer->tags;
        $tagOpen = $tags->open;
        $tagClose = $tags->close;
        $tagSeparator = $tags->separator;

        // Pokud je radek z users pole, predelame ho na StdClass
        if (is_array($row) && !($row instanceof StdClass)) {
            $row = (object)$row;
        }

        // simple no-parameter tags
        $this->addTag('firstname', array_key_exists('name', $row) ? $row->name : '');
        $this->addTag('%%jmeno%%', array_key_exists('name', $row) ? $row->name : '');
        $this->addTag('surname', array_key_exists('surname', $row) ? $row->surname : '');
        $this->addTag('%%prijmeni%%', array_key_exists('surname', $row) ? $row->surname : '');
        $this->addTag('addressing', array_key_exists('degree_before', $row) ? $row->degree_before : '');
        $this->addTag('email', array_key_exists('email', $row) ? $row->email : '');
        $this->addTag('emailhash', array_key_exists('email', $row) ? md5($row->email) : '');
        $this->addTag('subject', $mail->getSubject());

        // LINK na online verzi emailu
        $onlinemailurl = Utils::applicationUrl() . '/mail/show/token/'.$row->token.'/'; 
        
        
        $mreg = ''.preg_quote($tags->open . str_replace('%','',$tags->onlinemailurl))
                .'(#(.*))?' . preg_quote($tags->close);
        
        $matches = array();

        preg_match_all("/$mreg/U", $mail->getBody(), $matches);
        foreach ($matches[0] as $k => $m) {
            
            //odstranime tagy
            $_omuTag = preg_replace("/".preg_quote($tags->open)."|".preg_quote($tags->open)."/U", '', $m);

        // na webovem zobrazeni emailu negenrujeme link na sebe
        if ($this->getOnlineMailLink() && !$this->getForceOnlineMailLinkPreserve()) {
                $this->addTag($_omuTag, '');
        } else {
            // link v emailu na na online verzi emailu
                $om_style = '';
                if ($matches[2][$k]) {
                    $om_style = ' style="' .$matches[2][$k] . '"';
            }
                $this->addTag($_omuTag, array(
                    'html' => '<a' . $om_style . ' href="' . $onlinemailurl . '">' . $texts->get('default.mailer.tags.onlinemailurl') . '</a>',
                'plain' => $texts->get('default.mailer.tags.onlinemailurl') . "\n" . $onlinemailurl
            ));
        }
        }
        
        unset($matches);

        // Repa ziskame z property mail objektu nebo se vezme posledni z DB
        if ($repId = $mail->getRep()) {
            $rep = Users::getUser($repId);
        } elseif ($row->reps) {
            $reps = explode(',', $row->reps);
            $repId = end($reps);
            $rep = Users::getUser($repId);
        } else {
            $rep = array('name' => '', 'surname' => '', 'degree_before' => '', 'degree_after' => '');
        }

        $this->addTag('repFirstName', $rep['name']);
        $this->addTag('repSurname', $rep['surname']);
        $this->addTag('repName',
            trim($rep['degree_before'] . ' ' . $rep['name'] . ' ' . $rep['surname'] . ' ' . $rep['degree_after'])
        );


        // nahrazeni notranslate
        $reg = $tags->open .
            $tags->notranslate .
            $tags->separator .
            '(.*)' .
            $tags->close;
        $notrans = Array();

        // dostaneme pole vsech polozek, ktere nechceme prekladat
        preg_match_all("/$reg/U", $mail->getBody() . $mail->getPlain(), $notrans);

        foreach($notrans[1] as $key => $notran){
            $notran_tag = $tags->open . $tags->notranslate . $tags->separator . $key . $tags->close;
            // ted se jeste musi v tele emailu nahradit za klic
            $mail->setBody(
                preg_replace(
                    quotemeta('~' .
                    $tagOpen .
                    $tags->notranslate .
                    $tagSeparator .
                    $notran .
                    $tagClose.
                    '~'), // => %%notranslate#[ARRAY_KEY]%%
                    $notran_tag,
                    $mail->getBody()
                )
            );

            $mail->setPlain(
                preg_replace(
                    quotemeta('~' .
                    $tagOpen .
                    $tags->notranslate .
                    $tagSeparator .
                    $notran .
                    $tagClose.
                    '~'), // => %%notranslate#[ARRAY_KEY]%%
                    $notran_tag,
                    $mail->getPlain()
                )
            );
        }

        // Resources predane z iPadu
        // POZOR, musi se provest pred nahrazovanim linku, protoze do tela mailu pridava linky pro resources
        // Pro spravnou funkci ocekava v Ibulletin_Email::presonalizationData['resources'] pole s id resources.
        // Nejprve ziskame radek pro kazdy zaznam resource, ktery budeme mnozit
        if (isset($this->tags['resources']) && is_array($this->tags['resources'])) {

            $pattern = '/' . $tags->resourceStart . '(.*)' . $tags->resourceEnd . '/s'; // /s zpusobi, ze . matchuje i \n
            // Provedeme 2x, nejprve pro HTML, pak pro plaintext
            $mailTexts = array('body' => $mail->getBody(), 'plain' => $mail->getPlain());
            foreach ($mailTexts as $textType => $text) {
                $m = array();
                $output = '';
                // Pokud mail obsahuje znacky pro resources a byly vyplneny nejake resources
                $match = preg_match($pattern, $text, $m);
                if (preg_match($pattern, $text, $m)) {
                    $resourceLine = $m[1]; // Radek jednoho resource, ktery budeme opakovat
                    // Nakopirujeme radek resource a nahradime name a url
                    foreach ($this->tags['resources'] as $resourceId) {
                        $resource = Resources::get($resourceId);
                        // Nahradime nazev resource
                        $line = str_replace($tags->resourceName, $resource['name'], $resourceLine);
                        // Nahradime popis resource
                        $line = str_replace($tags->resourceDescription, $resource['description'], $line);
                        // Pripravime znacku linku pro mail, bude pozdeji prelozena s ostatnimi linky
                        $linkId = Resources::getLink($resourceId);
                        if (!$linkId) {
                            // Link neexistuje, vynechame resource a zalogujeme chybu
                            Phc_ErrorLog::warning('Ibulletin_Mailer::parseTags()',
                                "Nepodarilo se ziskat link pro resource id: '$resourceId'. Resource byl v mailu preskocen.");
                            continue;
                        }
                        $link = $tagOpen . $tags->link . $tagSeparator . $linkId . $tagClose;
                        $line = str_replace($tags->resourceUrl, $link, $line);
                        $output .= $line;

                    }

                    // Nahradime v textu mailu vzorovy radek resource za kompletni resources
                    $text = preg_replace($pattern, $output, $text);

                    // Zapiseme upraveny text zpet do mailu (body nebo plain)
                    $textType == 'body' ? $mail->setBody($text) : $mail->setPlain($text);

                }
            }
            $this->removeTag('resources');
        }

        // Provedeme nahradu pro vsechny polozky z pole tags
        foreach ($this->tags as $tagName => $tagVal) {
            $mail = $this->_parseTag($tagName, $mail, $this->tags, $row);
        }

        // advanced parametric tags

        // fotka reprezentanta
        //
        // regular pro nalezeni imgrep
        $reg = preg_quote($tags->open . $tags->imgrep
                . $tags->separator) . '(.*)' . preg_quote($tags->close)
            . '|' . preg_quote($tags->open . $tags->imgrep
                . $tags->close);
        $matches = array();
        preg_match("/$reg/U", $mail->getBody(), $matches);

        if ($matches) {
            // First try to get rep_id from data injected into mail object
            if ($rep_user_id = $mail->getRep()) {
                $imgrep = rtrim($this->config->mailer->imgrepaddr, '/\\') . '/' . $rep_user_id . '.png';
            } // Get last of user's reps
            elseif ($row->reps) {
                // get user picture of rep
                $reps = explode(',', $row->reps);
                $rep_user_id = end($reps);
                $imgrep = rtrim($this->config->mailer->imgrepaddr, '/\\') . '/' . $rep_user_id . '.png';
            }
            // alternate picture
            if (!$imgrep || !file_exists($imgrep)) {
                if ($path = $matches[1]) {
                    $imgrep = rtrim($this->config->mailer->imgsaddr, '/\\')
                        . '/' . $mail->getId() . '/' . ltrim($path, '\\/');
                }
            }
            // fallback picture
            if (!file_exists($imgrep)) {
                $imgrep = $this->config->mailer->emptypic;
            }

            // replace za cid pro vsechny vyskyty tagu imgrep
            $item = array(
                'cid' => '_inline_' . 'imgrep' . '_md5_' . md5($imgrep),
                'content' => file_get_contents($imgrep),
                'type' => Utils::getMimeType($imgrep),
                'disposition' => Zend_Mime::DISPOSITION_INLINE,
                'encoding' => Zend_Mime::ENCODING_BASE64,
                'filename' => basename($imgrep)
            );

            $mail->setBody(
                preg_replace("/$reg/U", 'cid:' . $item['cid'], $mail->getBody())
            );

            $mail->imgsEncoded[$imgrep] = $item;

            unset($item);
            unset($imgrep);

        }

        unset($matches);

        // nahrazeni trackingpicture
        // ten se nahradi kodem <img src="nejaka_adresa/token" /> nejaka_adresa
        // se nacte z konfigu, token je v tabulce users_emails

        // zjistit zda v DB je kod tracking picture, jestli ne, tak nahradit
        // prazndym retezcem.
        if (isset($row->token))
            $imgCode = '<img alt="" width="1" height="1" src="' . $tags->email_confirm_address . '/' . $row->token . '" />';
        else
            $imgCode = '';

        // tohle  se nahrazuje jenom v HTML casti mailu
        $mail->setBody(
            str_replace(
                $tags->trackingpicture,
                $imgCode,
                $mail->getBody()
            )
        );

        // dalsi zpusob odhlaseni z mailingu, tag %%logoffaddr%%
        if (isset($row->token))
            $logoffaddr = $this->config->mailer->logoff_address . '/' . $row->token;
        else
            $logoffaddr = '#';

        $mail->setBody(
            str_replace(
                $tags->logoffaddr,
                $logoffaddr,
                $mail->getBody()
            )
        );

        $mail->setPlain(
            str_replace(
                $tags->logoffaddr,
                $logoffaddr,
                $mail->getPlain()
            )
        );

        if (isset($row->token))
            $logoffnpsaddr = $this->config->mailer->logoffnps_address . '/' . $row->token;
        else
            $logoffnpsaddr = '#';

        $mail->setBody(
            str_replace(
                $tags->logoffnpsaddr,
                $logoffnpsaddr,
                $mail->getBody()
            )
        );

        $mail->setPlain(
            str_replace(
                $tags->logoffnpsaddr,
                $logoffnpsaddr,
                $mail->getPlain()
            )
        );

        // zmena adresy
        if (isset($row->token))
            $changeemailaddr = $this->config->mailer->changeemailaddr_address . '/' . $row->token;
        else
            $changeemailaddr = '#';

        $mail->setBody(
            str_replace(
                $tags->changeemailaddr,
                $changeemailaddr,
                $mail->getBody()
            )
        );

        $mail->setPlain(
            str_replace(
                $tags->changeemailaddr,
                $changeemailaddr,
                $mail->getPlain()
            )
        );


        // nahrazeni adresy obrazku v mailu, obrazky nacitane z webu
        $mail->setBody(
            str_replace(
                $tags->imgs,
                APPLICATION_URL . '/' . trim($this->config->mailer->imgsaddr, '\\/') . '/' . $row->email_id,
                $mail->getBody()
            )
        );

        //%%resource#[resourceId]%%, k získání ID linku pro resource stačí Resources::getLink($resourceId);
        $reg = $tags->open .
            $tags->resource .
            $tags->separator .
            '(.*)' .
            $tags->close;
        $ids = Array();

        // dostaneme pole vsech ID ze vsech linku, ktere jsou v tele emailu
        preg_match_all("/$reg/U", $mail->getBody() . $mail->getPlain(), $ids);

        foreach ($ids[1] as $id) {
            $link_id = Resources::getLink($id);
            $link_tag = $tags->open . $tags->link . $tags->separator . $link_id . $tags->close;

            // ted se jeste musi v tele emailu nahradit tokeny link za odkazy
            $mail->setBody(
                preg_replace(
                    '/' .
                    $tagOpen .
                    $tags->resource .
                    $tagSeparator .
                    $id .
                    $tagClose .
                    '/',
                    $link_tag,
                    $mail->getBody()
                )
            );

            $mail->setPlain(
                preg_replace(
                    '/' .
                    $tagOpen .
                    $tags->resource .
                    $tagSeparator .
                    $id .
                    $tagClose .
                    '/',
                    $link_tag,
                    $mail->getPlain()
                )
            );

        }
        
        
        //%%certificate%%, k získání ID linku na certifikát;
        $reg = $tags->open .
                $tags->certificate .
                $tags->close;

        // dostaneme pole vsech ID ze vsech linku, ktere jsou v tele emailu
        if (preg_match("/$reg/U", $mail->getBody() . $mail->getPlain())) {
            
            $resources = Resources::getBy(array($row->content_id), array(), true, true, 'certificate');
            
            if ($resources) {

                $resource = reset($resources);

                $userCertPath = rtrim($resource['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $row->user_id . '.pdf';

                if ($userCertPath) {

                    $link_id = Resources::getLink($resource['id']);
                    $link_tag = $tags->open . $tags->link . $tags->separator . $link_id . $tags->close;

                    // ted se jeste musi v tele emailu nahradit tokeny link za odkazy
                    $mail->setBody(
                            preg_replace(
                                    '/' .
                                    $tagOpen .
                                    $tags->certificate .
                                    $tagClose .
                                    '/', $link_tag, $mail->getBody()
                            )
                    );

                    $mail->setPlain(
                            preg_replace(
                                    '/' .
                                    $tagOpen .
                                    $tags->certificate .
                                    $tagClose .
                                    '/', $link_tag, $mail->getPlain()
                            )
                    );
                    
                } else {

                    Phc_ErrorLog::warning('Ibulletin_Mailer::parseTags()', "Certifikat uzivatele " . $row->user_id . "se nepodarilo nalezt.");
                }
            } else {
                Phc_ErrorLog::warning('Ibulletin_Mailer::parseTags()', "Certifikat pro content ID: '$id' neexistuje.");
            }
        }

        // nahrazeni a vytvoreni linku na menu
        $reg = $tags->open .
            $tags->link .
            $tags->separator .
            '(.*)' .
            $tags->close;
        $ids = Array();

        // dostaneme pole vsech ID ze vsech linku, ktere jsou v tele emailu
        preg_match_all("/$reg/U", $mail->getBody() . $mail->getPlain(), $ids);

        $linksGenerated = Array();
        foreach ($ids[1] as $id) {
            // prvni vyskyt linku v mailu, vygeneruje se pro nej token a ulozi
            // se novy zaznam do tabulky users_links_tokens.
            if (!array_key_exists($id, $linksGenerated)) {
                // pred generovani tokenu a ukladanim do DB, se otestuje zda zadany
                // link existuje v tabulce links.
                $select = $this->db->select()
                    ->from($this->config->tables->links)
                    ->where('id = ?', $id);
                try {
                    $result = $this->db->fetchAll($select);
                } catch (Zend_Db_Statement_Exception $e) {
                    // Zalogujeme informaci o nefunkcnim linku, snad si toho developer vsimne
                    Phc_ErrorLog::notice('Ibulletin_Mailer::parseTags()',
                        "Nepodarilo se prelozit ID linku '$id' na url. ID emailu '" . $mail->getId() .
                        "', jmeno emailu '" . $mail->getName() . "'");

                    $result = '';
                }

                // kdyz dany link v databazi je, vygeneruje se pro neho token atd.
                if (!empty($result)) {
                    try {
                        $token = $this->generateToken(
                            $this->config->tables->users_links_tokens,
                            'token',
                            null,
                            Array(
                                'user_id' => $row->user_id,
                                'link_id' => $id,
                                'email_id' => $row->email_id,
                                'users_emails_id' => $row->users_emails_id,
                                'is_online' => (bool)$this->getOnlineMailLink()
                            ),
                            15
                        );
                    } catch (IBulletinMailerException $e) {
                        throw new IBulletinMailerPersonalizationExc(
                            'Nepodařilo se personalizovat email, chyba při generování tokenu pro odkazy. users_emails_id: ' .
                            $row->users_emails_id . " linkId: $id, userId: $row->user_id, emailId: $row->email_id." .
                            "\nPuvodni vyjimka: \n$e");
                    }

                    // ulozi se token do pole linku
                    $linksGenerated[$id] = $this->config->mailer->link->url . '/' . $token . '/';
                } else // link neexistuje
                {
                    $linksGenerated[$id] = '#';
                }
            }

            // ted se jeste musi v tele emailu nahradit tokeny link za odkazy
            $mail->setBody(
                preg_replace(
                    '/' .
                    $tagOpen .
                    $tags->link .
                    $tagSeparator .
                    $id .
                    $tagClose .
                    '[\/]{0,1}/', // Odstranime pripadne 1 lomitko za %%link#xx%%
                    $linksGenerated[$id],
                    $mail->getBody()
                )
            );

            $mail->setPlain(
                preg_replace(
                    '/' .
                    $tagOpen .
                    $tags->link .
                    $tagSeparator .
                    $id .
                    $tagClose .
                    '[\/]{0,1}/', // Odstranime pripadne 1 lomitko za %%link#xx%%
                    $linksGenerated[$id],
                    $mail->getPlain()
                )
            );
        }

        // Nahrazeni tagu pro osloveni
        $reg = '/' . $tagOpen . '(' . $tags->salutation . '|' . 'osloveni' . ')' .
            $tagSeparator . '(.*?)' . $tagSeparator . '(.*?)' . $tagSeparator . '(.*?)' . $tagClose . '/';

        // obsahy
        $contents = array(
            'html' => $mail->getBody(),
            'plain' => $mail->getPlain(),
            'subject' => $mail->getSubject()
        );

        foreach ($contents as $cnt_type => $cnt) {

            // get matched tags
            if (preg_match_all($reg, $cnt, $matches) === false || empty($matches)) continue;

            // get gender
            if (!($this->genderAssigner instanceof GenderAssigner)) {
                throw new IBulletinMailerException(
                    'Není nastaven objekt, pro určení pohlaví uživatelů.');
            }
            $this->genderAssigner->assign($row->user_id);
            $gender = $this->genderAssigner->getGender();

            // replace each tag by gender text
            foreach ($matches[0] as $k => $ptn) {
                $m_male = $matches[2][$k];
                $m_female = $matches[3][$k];
                $m_unknown = $matches[4][$k];

                $cnt = ($gender == GenderAssigner::MALE ?
                    str_replace($ptn, $m_male, $cnt) : (
                    $gender == GenderAssigner::FEMALE ?
                        str_replace($ptn, $m_female, $cnt) : (
                    $gender == GenderAssigner::UNKNOWN ?
                        str_replace($ptn, $m_unknown, $cnt) : // fallback to MALE
                        str_replace($ptn, $m_male, $cnt)
                    )
                    )
                );

            }

            if ($cnt_type == 'html') $mail->setBody($cnt);
            elseif ($cnt_type == 'plain') $mail->setPlain($cnt);
            elseif ($cnt_type == 'subject') $mail->setSubject($cnt);

        }

        // subselect for user_attribs based on user_id
        $sel = $this->db->select()
            ->from('users_attribs', array('name', 'val'))
            ->where('user_id = ?', $row->user_id);
        $users_attribs = array();
        foreach ($this->db->fetchAll($sel, array(), Zend_Db::FETCH_OBJ) as $v) {
            $users_attribs[$v->name] = $v->val;
        };
        //Phc_Errorlog::warning('debug', 'users attribs: '.print_r($users_attribs, true));

        // replace tags for users_attribs (ua_*)
        foreach ($users_attribs as $k => $v) {
            $tag = '%%ua_' . $k . '%%';
            if (strpos($mail->getPlain(), $tag)) {
                $mail->setPlain(str_replace($tag, $v, $mail->getPlain()));
            }
            if (strpos($mail->getBody(), $tag)) {
                $mail->setBody(str_replace($tag, $v, $mail->getBody()));
            }
        }

        //odstranime vsechny neprelezone tagy users_attribs
        $ua_patt = "/%%ua_.*%%/";
        $mail->setBody(preg_replace($ua_patt, "", $mail->getBody()));
        $mail->setPlain(preg_replace($ua_patt, "", $mail->getPlain()));


        // Preklad vsech ostatnich tagu definovanych v mailer.tags.*
        // Musi byt na konci, aby se to co prekladame nejak specificky prelozilo driv
        foreach ($this->config->mailer->tags as $tagName => $tag) {
            // Preskocime tagy, ktere zacinaji pismeny (pouziva se na konfiguraci neceho jineho)
            if (preg_match('/^[a-zA-Z]+/', $tag) || !preg_match('/[a-zA-Z]+/', $tag)) {
                continue;
            }
            $mail = $this->_parseTag($tagName, $mail, array(), $row);
        }

        $mail->setPlain(strip_tags($mail->getPlain()));
        
        // Textum nastavime zpet puvodni sadu
        $texts->switchFrontAdmin($currentSwitchedTexts);

        // vraceni obsahu z notarans
        foreach($notrans[1] as $key => $notran){
            $notran_tag = $tags->open . $tags->notranslate . $tags->separator . $key . $tags->close;
            $mail->setBody(
                preg_replace(
                    '~' .
                    $notran_tag .
                    '~',
                    $notran,
                    $mail->getBody()
                )
            );

            $mail->setPlain(
                preg_replace(
                    '~' .
                    $notran_tag .
                    '~',
                    $notran,
                    $mail->getPlain()
                )
            );
        }

    }

    /**
     * Prelozi v tele mailu a plaintextu zadany tag. Tag muze byt ulozen v $tagVals nebo se muze
     * jednat primo o nazev atributu v $usersData, coz jsou data.
     *
     * Preklada jak tagy definovane v config.mailer.tags, tak nezname tagy tak,
     * ze doplni pocatecni a zakoncovaci symbol pred a ya jmeno tagu.
     *
     * @param string $tagName Jmeno tagu k prelozeni, obvykle nazev z config_mailer.ini mailer.tags.*,
     *                              pripadne se muze jednat o nazev atriutu z $userData
     * @param Ibulletin_Email $mail Email v ketrem ma byt tag nahrazen.
     * @param array $tagVals Pole obsahujici hodnoty taguu, klicem je $tagName,
     *                              hodnota muze byt pole s klici 'html' a 'plain'.
     *                              Na hodnotu typu string je pro HTML aplikovano nl2br.
     * @param StdClass $userData Radek uzivatele z DB tabulka users. Pouzivame pouze pokud chceme
     *                              prekladat tagy jmen atributuu z tabulky users. default null
     *
     * @return Ibulletin_Email      Email s prelozenymi znackami pro tag $tagName v HTML body i plain text body.
     */
    private function _parseTag($tagName, Ibulletin_Email $mail, array $tagVals, StdClass $userData = null)
    {
        //var_dump($tagName);
        // Zkusime, jestli mame hodnotu v
        if (isset($tagVals[$tagName])) {
            if (is_array($tagVals[$tagName])) {
                $tagValHtml = $tagVals[$tagName]['html'];
                $tagValPlain = $tagVals[$tagName]['plain'];
            } else {
                $tagValPlain = $tagVals[$tagName];
                $tagValHtml = nl2br($tagValPlain);
            }
        } elseif (isset($userData->$tagName)) {
            $tagValHtml = $userData->$tagName;
            $tagValPlain = $tagValHtml;
        } else {
            $tagValHtml = '';
            $tagValPlain = $tagValHtml;
        }

        //vycistime preventivne tag  
         $tagName = str_replace($this->config->mailer->tags->open, '', $tagName);
         $tagName = str_replace($this->config->mailer->tags->close, '', $tagName);

        // Pokud tag neni definovan v config->mailer->tags, vytvorime ho implicitne pridanim zacatecni a koncove znacky tagu
        if (isset($this->config->mailer->tags->{$tagName})) {
            $tag = $this->config->mailer->tags->{$tagName};
        } else {
            $tag = $this->config->mailer->tags->open . $tagName . $this->config->mailer->tags->close;
        }

        if (strpos($mail->getBody(), $tag) >= 0) {

            $mail->setBody(
                str_replace(
                    $tag,
                    $tagValHtml,
                    $mail->getBody()
                )
            );
        }
        if (strpos($mail->getPlain(), $tag) >= 0) {
            $mail->setPlain(
                str_replace(
                    $tag,
                    $tagValPlain,
                    $mail->getPlain()
                )
            );
        }
        if (strpos($mail->getSubject(), $tag) >= 0) {
            $mail->setSubject(
                str_replace(
                    $tag,
                    $tagValPlain,
                    $mail->getSubject()
                )
            );
        }

        return $mail;
    }

}


/**
 *  Vyjímka pro GenderAssigner.
 *
 * @author Martin Krčmář
 */
class GenderAssignerException extends Exception
{
}


/**
 *  Abstraktní třída pro objekty, které určují pohlaví uživatele.
 *  Pokud chceme v maileru určovat pohlaví nějak jinak, než standardním
 *  způsobem, například tak, že budeme mít v tabulce users přímo sloupec
 *  pohlaví, musí se vytvořit nová třída, která bude dědit od této. Potom stačí
 *  vytvořit objekt této nové třídy a nastavit ho v maileru pomocí funkce
 *  setGenderAssigner().
 *
 * @author Martin Krčmář
 */
abstract class GenderAssigner
{
    // Konstanty pro promennou state
    const FAILED = 'failed';
    const SUCCESS = 'success';

    // Konstanty pro promennou gender
    const MALE = 'male';
    const FEMALE = 'female';
    const UNKNOWN = 'unknown';

    // Hodnoty sloupce gender v DB
    const DB_MALE = 'm';
    const DB_FEMALE = 'f';

    /**
     *  Metoda, která určí pohlaví uživatele.
     *
     * @param Identifikátor uživatele z tabulky users.
     */
    abstract public function assign($userId);

    /**
     *  Vrací pohlaví, buď MALE, FEMALE nebo UNKNOWN.
     */
    abstract public function getGender();

    /**
     *  Vrací stav operace zjišťování pohlaví. Buď FAILED nebo SUCCESS.
     */
    abstract public function getState();
}


/**
 *  Třída, která slouží pro určování pohlaví osoby. Tahle konkrétně
 *  to bude určovat na základě sloupce gender nebo koncovky -á.
 */
class DefaultAssigner extends GenderAssigner
{
    private $db; // db handler
    private $state; // stav posledni akce
    private $gender; // urcene pohlavi
    private $config; // konfiguracni nastaveni

    /**
     *  Konstrukor, vytváří nové DB spojení.
     *
     * @param Konfigurační nastavení, hlavně tam musí být přípojení k DB.
     * @param Db handler.
     *
     * @throws GenderAssignerException.
     */
    public function __construct($config, $db)
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
     *  Metoda, která určí pohlaví uživatele. Výsledek uloží do proměnné gender,
     *  také nastaví proměnnou state.
     *
     * @param Identifikátor uživatele.
     */
    public function assign($userId)
    {
        // najde se prijmeni osoby, podle toho se zkusi urcit pohlavi
        $select = $this->db->select()
            ->from(array('u' => $this->config->tables->users),
                array('gender', 'surname', 'name'))
            ->where('id = ?', $userId);

        try {

            //$fetchMode = $this->db->getFetchMode();
            //$this->db->setFetchMode(Zend_Db::FETCH_OBJ);
            $result = $this->db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
            //$this->db->setFetchMode($fetchMode);

            // je nastaven sloupec s pohlavim, urci se to podle neho
            if (isset($result->gender) && !empty($result->gender)) {
                switch ($result->gender) {
                    case self::DB_MALE :
                        $this->gender = self::MALE;
                        $this->state = self::SUCCESS;

                        return;

                    case self::DB_FEMALE :
                        $this->gender = self::FEMALE;
                        $this->state = self::SUCCESS;

                        return;

                    default :
                        $this->state = self::FAILED;
                        $this->gender = self::UNKNOWN;
                        break;
                }
            }

            if (isset($result->name)) {
                // druha moznost je zjistit pohlavi podle tabulky first_names
                $select = $this->db->select()
                    ->from($this->config->tables->names)
                    ->where('name = ?', strtolower($result->name));

                //$fetchMode = $this->db->getFetchMode();
                //$this->db->setFetchMode(Zend_Db::FETCH_OBJ);
                $result2 = $this->db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
                //$this->db->setFetchMode($fetchMode);
                if (isset($result2->gender)) {
                    switch ($result2->gender) {
                        case self::DB_MALE :
                            $this->gender = self::MALE;
                            $this->state = self::SUCCESS;

                            return;

                        case self::DB_FEMALE :
                            $this->gender = self::FEMALE;
                            $this->state = self::SUCCESS;

                            return;

                        default :
                            $this->state = self::FAILED;
                            $this->gender = self::UNKNOWN;
                            break;
                    }
                }
            }

            // jinak se pohlavi bude urcovate podle prijmeni
            if (!isset($result->surname)) {
                $this->state = self::FAILED;
                $this->gender = self::UNKNOWN;

                return;
            }

            // bude se testovat konec retezce na a.
            // prekodovani prijmeni z utf8 na latin2, php funkce jako strlen,
            // substr pracuji spatne s utf8
            if ($surname = iconv('UTF-8', 'ISO-8859-2', $result->surname)) {
                $end = substr($surname, strlen($surname) - 1, 1);
                if (!strcmp($end, iconv('UTF-8', 'ISO-8859-2', 'á'))) {
                    $this->gender = self::FEMALE;
                    $this->state = self::SUCCESS;
                } else {
                    $this->gender = self::UNKNOWN;
                    $this->state = self::SUCCESS;
                }
            } else {
                $this->gender = self::UNKNOWN;
                $this->state = self::FAILED;
            }
        } catch (Zend_Db_Statement_Exception $e) {
            // Vratime zpet fetch mode
            //$this->db->setFetchMode($fetchMode);

            // nastavi se status na failed
            $this->state = self::FAILED;
            $this->gender = self::UNKNOWN;
        }
    }

    /**
     *  Metoda vrátí pohlaví.
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     *  Metoda vrátí status operace zjišťování pohlaví.
     */
    public function getState()
    {
        return $this->state;
    }

}