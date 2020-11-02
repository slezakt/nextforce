<?php
/**
 * Zobrazování online emailů
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class MailController extends Zend_Controller_Action {


    public function showAction() {

        $token = $this->_getParam('token');
        if (!$token)
            return;

        $noreplace = (boolean) $this->_getParam('noreplace', false);
        $user_id = Ibulletin_Auth::getUserIdFromEmailToken($token);

        // Pokud nebyl identifikovan uzivatel, ukoncime
        if($user_id === null){
            return;
        }

        // Prihlasime uzivatele
        Ibulletin_Auth::setUser($user_id);

        $users_emails_id = $this->getEmailID($token);
        if (!$users_emails_id)
            return;

        $mailer = new Ibulletin_Mailer();

        if ($noreplace) {
            $mailer->getMailerTags()->setForceOnlineMailLinkPreserve(true);
        }
        $zmails = $mailer->sendEmails($users_emails_id, true);
        $zmail = $zmails[$users_emails_id];

        if ($zmail) {
            $attach = array();
            if($zmail->getHtmlRelatedAttachments()) $attach = $zmail->getHtmlRelatedAttachments()->getParts();
            $content = quoted_printable_decode($zmail->getBodyHtml(true));
            //projdeme obsaha nahradime CID inline obrazky
            foreach ($attach as $a) {
                $content = str_replace('cid:' . $a->id, 'data:' . $a->type . ';base64,' . $a->getContent(), $content);
            }
            $this->view->email = $content;
        }

        // Zapiseme zobrazeni emailu do page_views
        Ibulletin_Stats::getInstance()->setAttrib('users_emails_id', $users_emails_id);
    }

    /**
     * Vrati user mail id dle tokenu
     * @param type $token
     * @return id
     */
    private function getEmailID($token) {

         $db = Zend_Registry::get('db');
         $config = Zend_Registry::get('config');
         $select = $db->select()
            ->from(array('ue' => $config->tables->users_emails))
            ->where('token = ?',$token);
          $row = $db->fetchRow($select);

          if ($row) {
              return $row['id'];
          } else {
              return null;
          }


    }
}

?>
