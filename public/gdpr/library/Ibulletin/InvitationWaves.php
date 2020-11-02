<?php

class Ibulletin_InvitationWavesException extends Exception {}

/**
 *  Třída pro administraci zvacích vln.
 *
 *  @author Martin Krčmář
 */
class Ibulletin_InvitationWaves
{
    /**
     *  Konfigurační nastavení. Musejí tam být hlavně názvy tabulek.
     */
    private $config;

    /**
     *  Db handler.
     */
    private $db;

    /**********************************************************
     *  KONSTRUKTORY
     **********************************************************/

    /**
     *  Konstruktor.
     *
     *  @param Konfigurační nastavení.
     *  @param DB handler.
     */
    public function __construct($config = null, $db = null)
    {
    	$db = $db? $db : Zend_Registry::get('db');
    	$config = $config? $config : Zend_Registry::get('config');

        $this->config = $config;
        $this->db = $db;
    }

    /**********************************************************
     *  PUBLIC METODY
     **********************************************************/
    /**
     * Vraci seznam zvacich vln
     *
     * @return Zend_Db_Select
     */
    public static function getInvitationWavesQuery() {
        $db = Zend_Registry::get('db');
        $subselect = $db->select()->from("emails",array("COUNT(id)"))
                        ->where("invitation_id = iw.id");

         $select = $db->select()
            ->from(array('iw' => "invitation_waves"),
                    array("iw.*","c_mail"=>new Zend_Db_Expr("(".$subselect.")")))
            ->joinLeft('bulletins','bulletins.id = iw.bulletin_id',array('bulletin'=>'bulletins.name'));

        return $select;
    }

    /**
     *  Vrátí seznam invitačních vln. Spojený s tabulkou sessions.
     *  Ke každé vlně se také najde seznam emailů.
     */
    public function getInvitationWaves()
    {
        $select = $this->db->select()
            ->from(array('iw' => $this->config->tables->invitation_waves))
            ->joinLeft(array('pv' => 'page_views'),
                'iw.id = pv.invitation_id',
                array('page_views' => 'COUNT(pv.id)'))
            ->order('name')
            ->group('iw.id')
            ->group('iw.type')
            ->group('iw.name')
            ->group('iw.start')
            ->group('iw.end')
            ->group('iw.bulletin_id')
            ->group('iw.url_prefix')
            ->group('iw.url_name')
            ->group('iw.invited')
            ->group('iw.force_referer')
            ;

        try
        {
            $result = $this->db->fetchAll($select);
            // projde se cely seznam zvacich vln a ke kazde se najde seznam
            // emailu, ktere jsou spojeny s touto vlnou. Ten se potom prida do
            // pole, ktere se vraci.
            foreach ($result as $row)
            {
                $select = $this->db->select()
                    ->from($this->config->tables->emails,
                        array('email_id' => 'id', 'email_name' => 'name'))
                    ->where('invitation_id = ?', $row->id);

                $emails = $this->db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);
                $row->emails = $emails;
            }

            return $result;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new Ibulletin_InvitationWavesException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString());
        }
    }

    /**
     *  Vrátí seznam vln, pro select.
     *
     *  @param pokud true, spojí se název s id.
     *  @param Pokud TRUE dá se na první místo seznamu obsah parametru $first.
     *  @param To co se dá na první místo.
     */
public function getInvitationWavesSelect($ids = false, $ifFirst = FALSE, $first = '')
    {
        $select = $this->db->select()
            ->from($this->config->tables->invitation_waves)
            ->order('start DESC', 'type', 'name');

        try
        {
            $result = $this->db->fetchAll($select, array(), Zend_Db::FETCH_OBJ);

            $retVal = Array();
            if ($ifFirst)
                $retVal[0] = $first;

            foreach ($result as $row)
            {
                if ($ids)
                    $retVal[$row->id] = "$row->id - $row->name";
                else
                    $retVal[$row->id] = $row->id;
            }

            return $retVal;
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new Ibulletin_InvitationWavesException(
                'Nepodařilo se získat seznam zvacích vln. '.$e->getMessage());
        }
    }

    /**
     *  Vrátí informace o konkrétní zvací vlně.
     *
     *  @param Identifikátor zvací vlny.
     */
    public function getWaveData($id)
    {
        $select = $this->db->select()
            ->from($this->config->tables->invitation_waves,
                array('id', 'name', 'type', 'bulletin_id', 'url_prefix', 'url_name', 'force_referer',
                    'start' => 'to_char("start", \''.$this->config->general->format->totimestamp.'\')',
                    'end' => 'to_char("end", \''.$this->config->general->format->totimestamp.'\')',
                    'invited',
                    'link_id'
                ))
            ->where('id = ?', $id);

        try
        {
            return $this->db->fetchRow($select, array(), Zend_Db::FETCH_OBJ);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new Ibulletin_InvitationWavesException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString().', funkce getWaveData');
        }
    }

    /**
     *  Uloží změněnou zvací vlnu.
     *
     *  @param Identifikátor zvací vlny.
     *  @param Jméno zvací vlny.
     *  @param Typ zvací vlny.
     *  @param Start zvací vlny.
     *  @param Konec zvací vlny.
     */
    public function saveWave($id, $name, $type, $start, $end, $bulletin, $urlPrefix, $urlName, $forceReferer, $invited=0, $link_id = null)
    {
        if (empty($start)) $start = NULL;
        if (empty($end)) $end = NULL;
        if (empty($invited)) $invited = 0;

        $data = array(
            'name' => $name,
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'bulletin_id' => $bulletin,
            'url_prefix' => !empty($urlPrefix) ? $urlPrefix : null,
            'url_name' => !empty($urlName) ? $urlName : null,
            'force_referer' => !empty($forceReferer) ? $forceReferer : false,
            'invited' => $invited,
            'link_id' => $link_id,
        );

        try
        {
            return $this->db->update(
                $this->config->tables->invitation_waves,
                $data,
                "id = $id");
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new Ibulletin_InvitationWavesException(
                "Nepodařilo se uložit změněnou zvací vlnu, ID: $id. ".$e->getMessage());
        }

    }

    /**
     *  Vloží do DB novou vlnu.
     *
     *  @param Jméno zvací vlny.
     *  @param Typ zvací vlny.
     *  @param Začátek zvací vlny
     *  @param Konec zvací vlny.
     *  @param Bulletin.
     */
    public function newWave($name, $type, $start, $end, $bulletin, $urlPrefix, $urlName, $forceReferer, $invited=0, $link_id=null)
    {
        if (empty($start)) $start = NULL;
        if (empty($end)) $end = NULL;
        if (empty($invited)) $invited = 0;

        $data = array(
            'name' => $name,
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'bulletin_id' => $bulletin,
            'url_prefix' => !empty($urlPrefix) ? $urlPrefix : null,
            'url_name' => !empty($urlName) ? $urlName : null,
            'force_referer' => !empty($forceReferer) ? $forceReferer : false,
            'invited' => $invited,
            'link_id' => $link_id
        );

        try
        {
            return $this->db->insert(
                $this->config->tables->invitation_waves,
                $data);
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new Ibulletin_InvitationWavesException(
                'Nepodařilo se vložit novou zvací vlnu. '.$e->getMessage());
        }
    }

    /**
     *  Smaže zvací vlnu.
     *
     *  @param Identifikátor vlny.
     */
    public function deleteWave($id)
    {
        try
        {
            return $this->db->delete(
                $this->config->tables->invitation_waves,
                "id = $id");
        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new Ibulletin_InvitationWavesException(
                "Nepodařilo se smazat zvací vlnu, ID: $id, ".$e->getMessage());
        }

    }


    /**
     * Najde nejaktualnejsi vlnu bulletinu
     *
     * @return int id
     * @throws Ibulletin_InvitationWavesException
     */
    public static function getLastBulletinIw() {

        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $select = $db->select()
                ->from(array('iw'=>$config->tables->invitation_waves))
                ->joinLeft(array('b'=>'bulletins'),'iw.bulletin_id = b.id',null)
                ->where('b.deleted IS NULL')
                ->where('b.hidden = false')
                ->where('iw.type = 0')
                ->order(array('b.valid_from DESC', 'iw.start DESC', 'iw.id DESC'))
                ->limit(1);

        try
        {
            $row = $db->fetchRow($select);
            if (isset($row['id'])) {
                return $row['id'];
            } else {
                return null;
            }

        }
        catch (Zend_Db_Statement_Exception $e)
        {
            throw new Ibulletin_InvitationWavesException(
                'Nepodařilo se vykonat SQL dotaz: '.$select->__toString().', funkce getLastBulletinIw');
        }

    }

}