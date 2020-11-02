<?php

/**
 * Obsluha generovani certifikatu
 *
 * @author Ondra Bláha <ondrej.blaha@pearshealthcyber.com>
 */
class Certificates {

    /**
     * Vrati cestu k certifikátu
     * @param int $contentID
     * @param int $userId
     * @return boolean|string
     */
    public static function getPath($contentID,$userId) {

        $config = Zend_Registry::get('config');
        $certPath = rtrim($config->content_article->basepath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $contentID . DIRECTORY_SEPARATOR . $config->caseStudies->certificate->folderName;
        $certFile = $certPath . '/' . $userId . '.pdf';

        if (file_exists($certFile)) {
            return $certFile;
        } else {
            return false;
        }
    }
    
    /**
     * Nacte sablonu a vygeruje html
     * @param array $content
     * @param array $user
     * @return string
     * @throws Exception
     */
    public static function parseTemplate($content, $user) {

        $config = Zend_Registry::get('config');
        $texts = Ibulletin_Texts::getSet('admin.certificate');

        $certPath = rtrim($config->content_article->basepath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $content->id . DIRECTORY_SEPARATOR . $config->caseStudies->certificate->folderName;

        $template = $certPath . DIRECTORY_SEPARATOR . $config->caseStudies->certificate->templateFolderName . DIRECTORY_SEPARATOR . 'index.html';

        if (!file_exists($template)) {
            throw new Exception(sprintf($texts->template_missing, $template));
        }

        $parseTemplate = file_get_contents($template);

        $tagOpen = $config->mailer->tags->open;
        $tagClose = $config->mailer->tags->close;

        preg_match_all('/' . $tagOpen . '(.+)' . $tagClose . '/Ui', $parseTemplate, $m);
        $search = array();
        $replace = array();

        foreach ($m[0] as $k => $t) {
            $search[] = $t;
            if ($m[1][$k] == "date") {
                $date = new Zend_Date();
                $replace[] = $date->toString($config->general->dateformat->short);
            } elseif ($m[1][$k] == "basepath") {
                $replace[] = $certPath . DIRECTORY_SEPARATOR . $config->caseStudies->certificate->templateFolderName;
            } else {
                $replace[] = $user[$m[1][$k]];
            }
        }
        
        return str_replace($search, $replace, $parseTemplate);
    }
    
   
    /**
      * Generuje certifikáty uživatele
      * @param int $contentID
      * @param array pole s ID uzivatelu
      * @return bool | array | string exportovane soubory
      * @throws Exception
      */
     public static function generate($contentID, $users) {

        $config = Zend_Registry::get('config');

        $texts = Ibulletin_Texts::getSet('admin.certificate');

        $contents = Contents::get($contentID);
        $content = $contents['object'];
        
        $users = Statistics::getUsersScores($contentID, $users);

        //kontrola bodu a attibutu
        $userAttribs = array_merge(array('id'), explode(',', str_replace(' ', '', $config->caseStudies->certificate->usersAttribsApproval)));

        foreach ($users as $user) {

            if ($user['score'] < $content->points) {
                throw new Exception(sprintf($texts->low_score, $user['id']));
            }

            foreach ($userAttribs as $attrib) {
                if (empty($user[$attrib])) {
                    throw new Exception(sprintf($texts->attributes_missed, $user['id']));
                }
            }
        }
        
        require_once('library/mpdf/mpdf.php');
        
        $certFiles = array();
        
        foreach ($users as $user) {

            $pdf = new mPDF();
            $pdf->showImageErrors = true;
            $pdf->WriteHTML(self::parseTemplate($content, $user));

            $certPath = rtrim($config->content_article->basepath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $contentID . DIRECTORY_SEPARATOR . $config->caseStudies->certificate->folderName;

            $certFile = $certPath . '/' . $user['id'] . '.pdf';

            $pdf->Output($certFile, 'F');

            if (!file_exists($certFile)) {
                throw new Exception(sprintf($texts->error_create_certificate, $user['id']));
            }
            
            self::send($user['id'], $contentID);
            
            $certFiles[] = $certFile;
            
        }
        
        if (!$certFiles) {
            return false;
        } else if (count($certFiles == 1)) {
            return array_shift($certFiles);
        } else {
            return $certFiles;
        }
       
        return $certFiles;
    }
    
    

    /**
     * Odesle email s certifikatem uzivatele
     *
     * @param int     ID uzivatele
     * @param int   Content ID
     * @return bool Podarilo se odeslani mailu?
     */
    public static function send($userID, $contentID) {

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        // Ziskame id mailu
        $q = "SELECT id FROM emails WHERE special_function = 'certificate' AND deleted IS NULL ORDER BY id DESC LIMIT 1";
        $email_id = $db->fetchOne($q);

        if (!is_numeric($email_id)) {
            throw new Exception('Nepodařilo se najít email pro odeslání certifikátu');
        }

        // Zaradime email do fronty
        try {
            Zend_Loader::loadClass('Ibulletin_Mailer');
            $mailer = new Ibulletin_Mailer($config, $db);
            // Nastaveni pokracovat v odesilani i pri chybach
            $mailer->setKeepSendingEH();

            // Ignorovat nastaveni send_emails
            $mailer->setIgnoreRegisteredAndSendEmails(true);

            // Filtr pro vybrani a pridani pouze jednoho uzivatele
            $filter = "id = $userID";

            // Zaradime do fronty
            $usersEmailsIds = $mailer->enqueueEmail($email_id, $filter);
            
            $mailer->setContentId($usersEmailsIds,$contentID);
            
        } catch (IBulletinMailerException $e) {

            throw new Exception("Certifikat uživatele ID: $userID  odeslan, email se nepodarilo zaradit do fronty");
            
        }

        // Odeslani jednoho emailu z fronty
        try {
            if (!empty($usersEmailsIds)) {
                $usersEmailsId = current($usersEmailsIds);
                $mailer->sendEmails($usersEmailsId);
            } else {
                
                throw new Exception("Certifikat uživatele ID: $userID nebyl odeslan, email nebyl zarazen do fronty");
                
            }
        } catch (IBulletinMailerException $e) {
            
            throw new Exception("Certifikat uživatele ID: $userID nebyl odeslan, selhalo odesilani fronty.");
            
        } catch (Exception $e) {
            
            throw new Exception($e->getMessage());
            
        }
    }
    
    
}
