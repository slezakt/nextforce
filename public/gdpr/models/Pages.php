<?php

/**
 * iBulletin - Pages.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}

/**
 * Codes:
 * 1 - Pokud neexistuje zadana page nebo content.
 * 3 - Pokud neexistuje soubor v adresari layoutuu pro dany layout.
 * 4 - Pokud neexistuje pozadovany soubor v adresari templatuu.
 * 5 - Nepodarilo se ulozit upravu zaznamu v pages.
 * 6 - Nepodarilo se pridat content z jineho duvodu.
 */
class Pages_Exception extends Exception {}

/**
 * Trida obsahujici metody pouzivane pro praci se strankami z tabulky pages
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Pages
{
    /**
     * Vrati renderovaci data seznamu pages v kategorii, nebo bulletinu, nebo
     * oboji ve spravnem poradi.
     *
     * @param string URL jmeno pozadovane kategorie (muze byt null)
     * @param string URL jmeno pozadovaneho bulletinu (muze byt null)
     * @param bool Radit podle kategorii?
     * @param array Jednotlive doplnujici podminky do WHERE - budou spojeny pomoci AND
     * @param array Jednotlive atributy k razeni, vystup je vyzdy  primarne razen podle
     *              bulletinuu a ty podle platnosti.
     * @param int   Limit poctu vypsanych zaznamu.
     * @param int   Offset zobrazeni - musi byt zadan limit, jinak se ignoruje.
     * @param bool  Vratit pouze pocet vysledku?
     * @param bool  Ignorovat polozky, ktere jeste nejsou platne? (def true) Pozor, stale jeste
     *              zavisi na tom, jestli jsme v adminu nebo ne.
     * @return array Pole anotaci s odkazy od jednotlivych pages ve spravnem poradi
     *               (novost bulletinu, poradi kategorii, poradi v kategorii)
     *         int Pocet vysledku pokud je pozadovano jen to
     */
    public static function getPagesAnnotations($cat_url_name, $bul_url_name = null, $sort_by_cat = true,
                    $filter = null, $order = null, $limit = null, $offset = null,
                    $get_only_count = false, $ignoreNotValid = true)
    {
        //profile('getAnnotsStart');
        $db =  Zend_Registry::get('db');
        $menu = new Ibulletin_Menu();
        $locale = Zend_Registry::get('locale');
        $config = Zend_Registry::get('config');

        $data = self::getPagesList($cat_url_name, $bul_url_name, $sort_by_cat,
                                    $filter, $order, $limit, $offset, $get_only_count, $ignoreNotValid);

        // Pokud jen pocitame pocet, vratim ted vysledek
        if($get_only_count){
            //profile('getAnnotsCntEnd');
            return $data;
        }
        //profile('getAnnotsAfterPgList');

        $annotations = array();
        $created = new Zend_Date();
        $created->setLocale($locale);
        foreach($data as $article){
            $page_id = (int)$article['page_id'];
            // Pokud zname vse potrebne k vytvoreni URL, slozime URL params rovnou, jinak to zkusime pres link
            if(empty($article['page_url_name']) || empty($article['bulletin_url_name'])){
                // Najdeme ID linku pro odpovidajici clanek
                $q = "SELECT l.id FROM links l
                      WHERE l.page_id = $page_id ORDER BY id DESC LIMIT 1";
                $link =  $db->fetchOne($q);
                $url_params = $menu->getLinkUrl($link, true, false, null/*$bul_url_name*/, $article['bulletin_id']);
            }
            else{
                $url_params = array('params' => array('name' => $article['bulletin_url_name'],
                                                      'article' => $article['page_url_name']
                                                      ),
                                    'route' => 'bulletinarticle'
                );
            }

            $created->setIso($article['created']);
            $annotations[] = array(
                    'page_id' => $page_id,
                    'content_id' => $article['content_id'],
                    'category' => $article['cat_name'],
                    'category_url_name' => $article['cat_url_name'],
                    'cat_type' => $article['cat_type'],
                    'name' => $article['obj']->getName(),
                    'labels' => $article['obj']->getLabels(),
                    'annotation' => $article['obj']->getAnnotation(),
                    'class_name' => get_class($article['obj']),
                    'url' => $url_params,
                    'bulletin_url_name' => $article['bulletin_url_name'],
                    'bulletin_name' => $article['bulletin_name'],
                    'author' => $article['author'],
                    'created' => $created->toString($config->general->dateformat->short),
                    );
        }
        //profile('getAnnotsEnd');

        return $annotations;
    }


    /**
     * Najde zaznamy stranek v kategorii, v bulletinu nebo v obojim
     * ve spravnem poradi i s objekty contentuu. V pripade, ze je zadan bulletin a neni zadana kategorie,
     * vraci jen clanky kategorii, ktere jsou v danem vydani aktivni.
     *
     * TODO - bude problem s vicenasobnymi vazbami vice contentu v jedne page...
     *
     * @param string/int URL jmeno pozadovane kategorie (muze byt null) / ID kategorie
     * @param string/int URL jmeno pozadovaneho bulletinu (muze byt null) / ID bulletinu
     * @param bool Radit podle kategorii?
     * @param array Jednotlive doplnujici podminky do WHERE - budou spojeny pomoci AND
     * @param array Jednotlive atributy k razeni, vystup je vyzdy  primarne razen podle
     *              bulletinuu a ty podle platnosti. POZOR, pokud menime razeni podle b.valid_from,
     *              je treba zaroven pridat i razeni podle b.id, protoze jinak b.id zustane pred b.valid_from
     * @param int   Limit poctu vypsanych zaznamu.
     * @param int   Offset zobrazeni - musi byt zadan limit, jinak se ignoruje.
     * @param bool  Vratit pouze pocet vysledku?
     * @param bool  Ignorovat polozky, ktere jeste nejsou platne? (def true) Pozor, stale jeste
     *              zavisi na tom, jestli jsme v adminu nebo ne.
     * @param bool  Vratit jen pages pres ktere se ma cyklovat v nasledujici/predchozi stranka
     * @param bool  Vypsat i pages bez bulletinu? DEFAULT false
     * @return array Pole informaci o dane page v kategorii
     *         int   Pocet vysledku pokud je pozadovano jen to
     */
    public static function getPagesList($cat = null, $bul = null, $sort_by_cat = true,
                    $filter = null, $order = null, $limit = null, $offset = null,
                    $get_only_count = false, $ignoreNotValid = true, $onlyCycled = false,
                    $bulletinIndependent = false)
    {
        $db =  Zend_Registry::get('db');

        // Nachystame hlavni where jako pole ktere pozdeji spojime pomoci AND
        $where = array();
        if(is_array($filter)){
            $where = $filter;
        }

        // Filtr kategorii podle bulletinu
        if($bul !== null && $cat === null){
            /*
            $catFilter = new Zend_Db_Select($db);
            $catFilter->from(array('c' => 'categories'), 'id')
                      ->where('c.deleted IS null');

            if(is_numeric($bul)){
                $catFilter->join(array('currb' => 'bulletins'), 'currb.id = '.$bul, array());
            }
            else{
                $catFilter->join(array('currb' => 'bulletins'), "currb.url_name = ".$db->quote($bul), array());
            }

            $catFilter->joinLeft(array('fromb' => 'bulletins'), 'fromb.id = c.valid_from', array())
                      ->joinLeft(array('tob' => 'bulletins'), 'tob.id = c.valid_to', array())
                      ->where('(currb.id = tob.id OR currb.id = fromb.id '.
                              'OR ((fromb.valid_from < currb.valid_from OR fromb.id IS NULL) '.
                              'AND (tob.valid_from > currb.valid_from OR tob.id IS NULL)))');
                      ;
            */

            $where[] = '(currb.id = tob.id OR currb.id = fromb.id '.
                              'OR ((fromb.valid_from < currb.valid_from OR fromb.id IS NULL) '.
                              'AND (tob.valid_from > currb.valid_from OR tob.id IS NULL)))';
            if(is_numeric($bul)){
                $catFilterQ = "INNER JOIN bulletins AS currb ON currb.id = ".(int)$bul." ";
            }
            else{
                $catFilterQ = "INNER JOIN bulletins AS currb ON currb.url_name = ".$db->quote($bul)." ";
            }

            $catFilterQ .= "
            LEFT JOIN bulletins AS fromb ON fromb.id = cat.valid_from
            LEFT JOIN bulletins AS tob ON tob.id = cat.valid_to
            ";
        }

        // Urcime co bude obsazeno ve where
        $filter_dupl_pages = '';
        // Vytvorime podminku podle toho, jestli je prave povoleno zobrazovani neaktivniho
        // obsahu, nebo ne
        Zend_Loader::loadClass('Zend_Auth');
        $session = Zend_Registry::get('session');
        $auth = Zend_Auth::getInstance();
        if(!empty($session->allowed_inactive_content) || $auth->hasIdentity() || !$ignoreNotValid){
            $where1 = '1 = 1';
            $where[] = '1 = 1';
        }
        else{
            $where1 = 'b.valid_from < current_timestamp';
            $where[] = 'b.valid_from < current_timestamp';
        }
        if($cat !== null){
            if(is_numeric($cat)){
                $where[] = "cat.id=".$db->quote($cat);
            }
            else{
                $where[] = "cat.id=(SELECT id FROM categories WHERE lower(url_name) = lower(".$db->quote($cat).") LIMIT 1)";
            }
        }
        if($onlyCycled){
            // Jen pages zahrnute v cyklovani pres predchozi/nasledujici stranka
            $where[] = 'bp.cycle';
        }
        if($bul !== null){
            if(is_numeric($bul)){
                $where[] = "b.id=".$db->quote($bul);
            }
            else{
                //$where[] = "lower(b.url_name)=lower(".$db->quote($bul).")";
                $where[] = "b.id = (SELECT id FROM bulletins WHERE lower(url_name) = lower(".$db->quote($bul).") LIMIT 1)";
            }
        }
        else{
            // Pridame filtrovani shodnych pages
            /*
             $filter_dupl_pages =
                  "JOIN (SELECT max(b.id) AS bulletin_id, page_id
                         FROM bulletins b,
                            (SELECT max(b.valid_from) AS valid_from, page_id
                            FROM bulletins b
                                JOIN bulletins_pages bp ON b.id = bp.bulletin_id
                            WHERE $where1
                            GROUP BY page_id) hlpr
                         WHERE b.valid_from = hlpr.valid_from AND $where1
                         GROUP BY b.valid_from, hlpr.page_id
                        ) hlpr_1 ON  hlpr_1.bulletin_id = b.id AND hlpr_1.page_id = p.id";
            //*/
            /*
            $filter_dupl_pages =
                  "JOIN (
                  SELECT max(b.id) AS bulletin_id, bp.page_id AS page_id
                  FROM bulletins b
                    JOIN bulletins_pages bp ON bp.bulletin_id = b.id
                    JOIN (
                        SELECT max(b.valid_from) AS valid_from, page_id
                        FROM bulletins b
                            JOIN bulletins_pages bp ON bp.bulletin_id = b.id
                            WHERE $where1
                            GROUP BY page_id
                        ) hlp
                        ON hlp.page_id = bp.page_id AND hlp.valid_from = b.valid_from
                  WHERE $where1
                  GROUP BY bp.page_id
                  ) hlpr_1 ON  (hlpr_1.bulletin_id = b.id AND hlpr_1.page_id = p.id)".
                  ($bulletinIndependent ? ' OR b.id is null' : '')."
                  ";
            */

            $filter_dupl_pages = "
                JOIN (
                    SELECT max(b.valid_from) AS valid_from, page_id
                    FROM bulletins b
                        JOIN bulletins_pages bp ON bp.bulletin_id = b.id AND b.deleted IS NULL AND NOT b.hidden
                        WHERE $where1
                        GROUP BY page_id
                ) hlp
                ON hlp.page_id = p.id AND hlp.valid_from = b.valid_from".
                  ($bulletinIndependent ? ' OR b.id is null' : '')."
            ";
        }

        /*
        SELECT max(b.id) AS bulletin_id, page_id
                         FROM bulletins b,
                            (SELECT max(b.valid_from) AS valid_from, page_id
                            FROM bulletins b
                                JOIN bulletins_pages bp ON b.id = bp.bulletin_id
                            WHERE 1=1
                            GROUP BY page_id) hlpr
                         WHERE b.valid_from = hlpr.valid_from AND 1=1
                         GROUP BY b.valid_from, hlpr.page_id;
        */

        // Slozime where klauzuli
        if(!empty($where)){
            $where_str = join(' AND ', $where);
        }
        else{
            $where_str = '';
        }

        // Radit dle kategorii?
        $order_str = "";
        if($sort_by_cat){
            $order_str = 'cat.order IS NOT NULL ASC, cat.order ASC,';
        }
        $order_str1 = "";
        if(!empty($order)){
            $order_str1 = join(', ', $order).', ';
        }

        $order_str2 = '';
        if(!preg_match('/b\.valid_from/i', $order_str1)){
            $order_str2 .= 'b.valid_from DESC,';
        }
        if(!preg_match('/b\.id/i', $order_str1)){
            $order_str2 .= 'b.id DESC,';
        }

        // Limit a offset
        $limit_qp = '';
        if(is_numeric($limit)){
            $limit_qp = 'LIMIT '.$limit;
            if(is_numeric($offset)){
                $limit_qp .= ' OFFSET '.$offset;
            }
        }

        // Pro zavislost na bulletinu pridame pred udelame z JOINu LEFT JOIN
            $bulIndepStr = $bulletinIndependent ? 'LEFT' : '';

        // TODO POZOR na vicenasobne spojeni content_pages ci pages_categories!!
        $q = "SELECT ".($get_only_count ? "p.id"
                     : "
                     c.serialized_object, c.class_name, c.id AS content_id, cp.page_id, p.name AS page_name,
                     l.id AS link_id, cat.name AS cat_name, cat.url_name AS cat_url_name, cat.type AS cat_type,
                     bp.url_name AS page_url_name, b.id AS bulletin_id,  b.url_name AS bulletin_url_name,
                     b.name AS bulletin_name, a.name AS author, a.id AS author_id,
                     c.created AS created, c.changed AS content_changed")."
              FROM ".($get_only_count ? "pages p" :
                     "content c
                   JOIN content_pages cp ON c.id = cp.content_id
                   JOIN pages p ON cp.page_id = p.id")."
                   LEFT OUTER JOIN pages_categories pc ON p.id = pc.page_id
                   LEFT OUTER JOIN categories cat ON  pc.category_id = cat.id
                   ".($get_only_count ? "" : "JOIN links l ON l.page_id = p.id")."
                   $bulIndepStr JOIN bulletins_pages bp ON bp.page_id = p.id
                   $bulIndepStr JOIN bulletins b ON b.id = bp.bulletin_id AND b.deleted IS NULL AND NOT b.hidden
                   ".($get_only_count ? "" : "
                   JOIN (
                            SELECT page_id, min(position) AS position
                            FROM content_pages
                            GROUP BY page_id
                        ) mins ON cp.page_id = mins.page_id AND cp.position = mins.position
                   LEFT OUTER JOIN authors a ON a.id = c.author_id
                   ")."
                   $filter_dupl_pages
                   ".(!empty($catFilterQ) ? $catFilterQ : '')."
                   "./*(!empty($catFilter) ? ' JOIN ('.$catFilter.') valid ON valid.id = cat.id OR cat.id IS NULL' : '').*/"
              WHERE $where_str
              ";

        // Pokud je pozadovano pouze vratit pocet vysledku, provedeme to
        $count = null;
        if($get_only_count){
            $q_count = "SELECT count(*) FROM ($q) AS foo";
            $count = $db->fetchOne($q_count);
            //echo new Exception()."\n\n".$q_count."\n\n\n";
            // Vratime pocet vysledkuu
            return $count;
        }

        // Pridame razeni a limit
        $q_order_limit = "
            ORDER BY $order_str $order_str2 $order_str1 bp.order ASC
            $limit_qp";
            $q = $q.$q_order_limit;

        // Provedeme select
        //profile('before');
        $rows = $db->fetchAll($q, array(), Zend_Db::FETCH_ASSOC);
        //echo new Exception()."\n\n".$q."\n\n\n";
        //profile('after');

        // Deserializujeme objekty
        foreach($rows as $key => $row){
            Zend_Loader::loadClass($row['class_name']);
            // Aby nedoslo k chybam, nevkladame do pole nerozbalitelne objekty,
            // ale presto nechame funkci unserialize zalogovat problem
            // Pred deserializaci zkusime uhodnout, jestli se jedna o escapovany
            // serializovany objekt, nebo ne
            if(preg_match('/^O:\d+:"/', $row['serialized_object'])){
                $object = unserialize($row['serialized_object']);
            }
            else{
                $object = unserialize(stripslashes($row['serialized_object']));
            }
            if($object !== false){
                $rows[$key]['obj'] = $object;
            }
        }

        return $rows;
    }



    /**
     * Vyhleda a vrati v poli parametry pro vytvoreni URL predchoziho a nasledujiciho
     * clanku v aktualnim bulletinu. Pokud je clanek posledni, nasledujicim clankem
     * je prvni clanek v bulletinu - czkluje se dokola po clancich.
     *
     * @param string $page_url_name URL jmeno pozadovane page z tabulky bulletins_pages
     * @param int $bulletin_id      ID bulletinu v kterem se stranka nachazi
     * @param int $page_id  ID      page, pouzivame misto page_url_name, default null
     * @return array Pole se dvema prvky 'prev' a 'next' obsahujici url parametry
     *               predchoziho a nasledujiciho clanku v bulletinu
     *               - Vraci null, pokud je v configu nastaveno nezobrazovat
     *                 navigaci napric bulletinem
     *
     * @throws Pages_Exception    Pokud nebyly zadany potrebne parametry.
     */
    public static function getPrevNextPageInBulletin($page_url_name, $bulletin_id, $page_id = null,
        $only_this_bulletin = null, $only_cycled = true)
    {
        $config = Zend_Registry::get('config');
        // Pokud se nemaji odkazy na nasledujici stranky zobrazovat, vratime pouze null
        if(!$config->general->show_bulletin_navigation){
            return null;
        }

        // Pokud se ma navigovat na dalsi clanky bez ohledu na bulletin, nastavime $bulletin_id na null
        if(!empty($config->general->bulletin_navigation_across_whole_web) && !$only_this_bulletin){
            $bulletin_id = null;
            $order = array('b.valid_from', 'b.id');
        }
        else{
            $order = null;
        }

        // Pokud chceme vypis pouze pro tento bulletin a bulletin_id neni zadano, musime jej najit
        if(empty($bulletin_id) && $only_this_bulletin){
            $bulletin_id = Bulletins::getCurrentBulletinId();
        }

        // Vyhledame vsechny clanky v bulletinu ve spravnem poradi
        $pages = self::getPagesList(null, $bulletin_id, true, null, $order, null, null, false, true, $only_cycled);

        // Pokud je v bulletinu jen jedna stranka, vratime null
        // - nebude zobrazena navigace
        if(count($pages) < 2){
            return null;
        }

        // Najdeme aktualni clanek a podle neho odvodime predchozi a nasledujici
        // Podle page_url_name
        if(!empty($page_url_name)){
            foreach($pages as $key => $page){
                if(strtolower($page['page_url_name']) == strtolower($page_url_name)){
                    break;
                }
            }
        }
        // Podle page_id
        elseif(!empty($page_id)){
            foreach($pages as $key => $page){
                if($page['page_id'] == $page_id){
                    break;
                }
            }
        }
        else{
            // Nepodarilo se najit aktualni stranku, zrejme nebyla zadana
            throw new Pages_Exception('Pages::getPrevNextPageInBulletin()', 'Nebyl zadan parametr '.
                'page_url_name ani page_id.');
        }

        /*
        echo $pages[0]['page_id'];
        echo $pages[1]['page_id'];
        exit;
        */

        // Zjistime, ktera je predchozi stranka
        if(isset($pages[$key - 1])){
            $prev = $pages[$key - 1];
        }
        elseif(!empty($config->general->bulletin_navigation_cycle)){
            $prev = $pages[count($pages)-1];
        }
        else{
            $prev = null;
        }

        // Zjistime, ktera je nasledujici stranka
        if(isset($pages[$key + 1])){
            $next = $pages[$key + 1];
        }
        elseif(!empty($config->general->bulletin_navigation_cycle)){
            $next = $pages[0];
        }
        else{
            $next = null;
        }

        // Pripravime data pro vytvoreni URL
        if($prev){
            $prev_url = array('params' => array('name' => $prev['bulletin_url_name'],
                                                'article' => $prev['page_url_name']),
                              'route' => 'bulletinarticle');
        }
        else{
            $prev_url = null;
        }
        if($next){
            $next_url = array('params' => array('name' => $next['bulletin_url_name'],
                                            'article' => $next['page_url_name']),
                              'route' => 'bulletinarticle');
        }
        else{
            $next_url = null;
        }

        return array('prev' => $prev_url, 'next' => $next_url);
    }


    /**
     * Najde clanky zajimave pro konkretniho uzivatele podle kategorii v kterych cetl nejvice clanku.
     * Pokud neni zadan uzivatel, pokusi se pouzit aktualniho uzivatele frontendu.
     *
     * Uklada data neulozena do page_viwes - Ibulletin_Stats::saveToPageViews()
     *
     * @param int $userId   ID uzivatele z tabulky users, pokud je null, pokusi se pouzit aktualniho uzivatele
     * @param int $count    Pocet zajimavych stranek, ktere maji byt vraceny,
     *                      pokud je null, pouzije se hodnota z $config->general->paging->interesting_pages->perpage
     * @return  array       Pole poli obsahujici informace pro jednotlive pages k nabidnuti
     *                      [page_name, category_name, bulletin_id, bulletin_url_name, page_url_name,
     *                      poradi, page_id, category_id, priorita, order, oblibene, cetl]
     */
    public static function getUsersInterestingPages($userId = null, $count = null)
    {
        $config = Zend_Registry::get('config');
        $db =  Zend_Registry::get('db');

        // Pokud neni zadan uzivatel, pokusime se pouzit aktualniho
        if($userId === null){
            $userId = Ibulletin_Auth::getActualUserId();
        }
        if($userId === null){
            Phc_ErrorLog::warning('Pages::getUsersInterestingPages()', 'Nebyl zadan uzivatel ani neni zadny prihlasen. '.
                'Nelze najit doporucene clanky.');
            return array();
        }

        // Count
        if($count === null){
            $count = $config->general->paging->interesting_pages->perpage;
        }

        // Pred provedenim dotazu musime ulozit data do page_views, abychom idealne zabranili
        // vypsani prave otevrene stranky na seznamu mohlo by vas zajimat
        $stats = Ibulletin_Stats::getInstance();
        $stats->saveToPageViews();


        // Select, ktery ziska potrebna data
        $q = '
            -- POZOR pohled bulletins_v nevraci neaktivni vydani, takze tento dotaz nebude vracet clanky neaktivniho
            -- vydani i kdyz bude pouzit timeshift a uzivatel bude prihlasen v adminu na rozdil od jinych casti systemu
            SELECT p.name AS page_name,
                   c.id AS content_id,
                   --c.class_name,
                   --c.serialized_object,
                   cat.name AS category_name,
                   b2.id AS bulletin_id,
                   b2.url_name AS bulletin_url_name,
                   bp.url_name AS page_url_name,
                   a.*
            FROM

            (SELECT DISTINCT
                max(b.poradi) AS poradi,
                --max(b.id) AS bulletin_id,
                --(SELECT id FROM bulletins_v WHERE poradi = max(b.poradi)) AS bulletin_id,
                pc.page_id,
                max(pc.category_id) AS category_id,
                max(cat.pocet) AS priorita,
                min(bp."order") AS "order",
                max(cat.pocet) IS NOT NULL AS oblibene
                -- bez cetl to bude podstatnerychlejsi (odebrat i left join page_views a session a order by cetl)
                --(case when s.id is not null then true else false end) as cetl
                /*
                ,(SELECT pv.id FROM sessions s JOIN page_views pv ON pv.session_id=s.id WHERE s.user_id = '.(int)$userId.' AND page_id = pc.page_id LIMIT 1) IS NOT NULL AS cetl
                --*/
                ,max(pvs.page_views_id) IS NOT NULL AS cetl
                --,max(pvs.page_views_id)
            FROM
                bulletins_v as b
                JOIN bulletins_pages as bp ON (b.id=bp.bulletin_id)
                JOIN pages_categories as pc ON (pc.page_id=bp.page_id)
                JOIN categories c ON pc.category_id = c.id

                LEFT JOIN (
                    SELECT pv.id as page_views_id, page_id FROM sessions s JOIN page_views pv ON pv.session_id=s.id WHERE s.user_id = '.(int)$userId.'
                    ) AS pvs ON pvs.page_id = pc.page_id

            --    LEFT JOIN page_views AS pv ON (pv.page_id=pc.page_id)
            --    LEFT JOIN sessions as s ON (s.id=pv.session_id and user_id = '.(int)$userId.')
                -- tento subselect vytahne pro konkretniho cloveka jeho oblibene kategorie podle poctu clanku, ktere v nich cetl
                -- clanek cteny vicekrat se pocita do skore dane kategorie jen jednou
                LEFT JOIN category_user_interest_v AS cat ON (pc.category_id=cat.category_id AND cat.user_id = '.(int)$userId.')
            -- tohle by slo udelat jako pohled
                /*
                (select
                    pc.category_id,
                    count(distinct pv.page_id) as pocet
                from
                    pages_categories as pc,
                    page_views as pv,
                    sessions as s
                where
                    pc.page_id=pv.page_id and
                    pv.session_id=s.id and
                    s.user_id = '.(int)$userId.'
                group by
                    pc.category_id
                )
                */
            WHERE (c.type != \'h\' OR c.type IS NULL)-- Vyhazujeme skryte hidden kategorie
            GROUP BY
                pc.page_id
            ORDER BY
                --page_id,
                -- Nejdrive hledam v tech co jeste necetl
                cetl,
                -- Pokud to jde, tak hledam co mu nabidnout nejdrive v oblibenych
                oblibene desc,
                -- Pak podle poradi vydani od nejnovejsiho
                poradi desc,
                -- A vybiram clanky z kategorii s nejvyssi prioritou
                priorita desc,
                -- Stranky se stejnou kategorii (a tedy i prioritou) a vydanim seradime podle poradi v danem cisle
                "order"
            -- Razeni je mozne upravit napriklad pokud by mi na priorite zalezelo vic nez na aktualnim vydani
            LIMIT '.(int)$count.'

            ) AS a
            JOIN bulletins_v b2 ON b2.poradi = a.poradi
            JOIN bulletins b ON b2.id = b.id
            JOIN pages p ON p.id = a.page_id AND p.deleted IS NULL AND NOT b.hidden
            JOIN bulletins_pages AS bp ON (bp.bulletin_id=b2.id AND p.id=bp.page_id)
            JOIN categories cat ON cat.id = category_id
            -- Pripojeni ID prvniho contentu v page pro veci jako je piktogram stranky
            JOIN (
                -- Seznam pages s jejich prvnim content_id
                    SELECT a.page_id, a."position", max(content_id) AS content_id
                    FROM (SELECT page_id, min("position") AS "position" FROM content_pages GROUP BY page_id) a
                    JOIN content_pages b ON a.page_id = b.page_id AND a."position" = b."position"
                    GROUP BY a.page_id, a."position"
                )cp ON cp.page_id = a.page_id
            JOIN content c ON cp.content_id = c.id AND c.deleted IS NULL
        ';

        // Ziskame seznam
        $pages = $db->fetchAll($q);

        // Pokusime se nachystat miniatury obrazku pro jednotlive page
        foreach($pages as $key => $page){
            $pictogram = Contents::getImage($page['content_id'], 'pictogram', $page['category_id']);
            $pages[$key]['pictogram'] = $pictogram;
        }


        return $pages;
    }


    /**
     * Najde nejctenejsi clanky pro zadany bulletin bud podle url_name nebo podle ID. Pokud neni zadano, vrati
     * vypis pro nejaktualnejsi bulletin. V pripade shodnych poctu zobrazeni pages jsouy razeny podle
     * poradi ve vydani.
     *
     * Jsou pocitana jen precteni clanku v danem vydani (clanek ve vice vydanich ma zapocitane jen pocty
     * ze zadaneho vydani).
     *
     * @param int $bulletinId   ID bulletinu pro ktery hledame nejctenejsi pages, pokud null a $bulletinUrlName
     *                          je take null, pokusi se pouzit aktualni
     * @param string $bulletinUrlName   URL name bulletinu ktery ma byt vypsan pokud null,
     *                                  funguje stejne jako $bulletinId
     * @param int $count        Pocet vracenych zaznamu, pokud je null, pouzije se hodnota z configu
         *                          $config->general->paging->most_readed->perpage
     * @return array    Pole poli obsahujici informace potrebne k vytvoreni odakzu na dane pages
     */
    public static function getMostReaded($bulletinId = null, $bulletinUrlName = null, $count = null)
    {
        $config = Zend_Registry::get('config');
        $db =  Zend_Registry::get('db');

        // Count
        if($count === null){
            $count = $config->general->paging->most_readed->perpage;
        }

        $whereA = array('1=1');
        // Bulletin ID
        if($bulletinId !== null){
            $whereA[] = 'bp.bulletin_id = '.(int)$bulletinId;
        }
        elseif(!empty($bulletinUrlName)){
            $whereA[] = 'bp.bulletin_id = (SELECT id FROM bulletins
                WHERE url_name = '.$db->quote($bulletinUrlName).' LIMIT 1)';
        }
        else{
            $whereA[] = 'bp.bulletin_id = (SELECT id FROM bulletins_v ORDER BY poradi DESC LIMIT 1)';
        }

        // Ziskame z pohledu nejctenejsi pages s informacemi pro vytvoreni URL
        $q = '
            SELECT coalesce(a.ctenaru, 0) AS ctenaru, p.*, c.name AS category_name, b.url_name AS bulletin_url_name
            FROM (SELECT bp.bulletin_id, bp.page_id, p.name AS page_name, bp.url_name AS page_url_name, bp."order"
                    FROM pages p
                    JOIN bulletins_pages bp ON p.id = bp.page_id
                    WHERE '.join(' AND ', $whereA).') p
            LEFT JOIN (
                 SELECT pv.page_id, pv.bulletin_id, count(distinct(user_id)) AS ctenaru
                 FROM page_views pv
                 JOIN sessions s ON s.id = pv.session_id AND pv.page_id > 0 AND pv.bulletin_id > 0
                 JOIN users_vf u ON s.user_id = u.id
                 GROUP BY pv.page_id, pv.bulletin_id
                 ORDER BY ctenaru desc) a ON a.page_id = p.page_id AND a.bulletin_id = p.bulletin_id
            JOIN bulletins b ON p.bulletin_id = b.id AND b.deleted IS NULL AND NOT b.hidden
            LEFT JOIN pages_categories pc ON pc.page_id = p.page_id
            LEFT JOIN categories c ON c.id = pc.category_id
            WHERE (c.type!=\'h\' OR c.type IS NULL) -- Vyhodime z vypisu skryte hidden kategorie
            ORDER BY ctenaru DESC, p."order" ASC
            LIMIT '.$count.'
        ';

        /* Stary dotaz - pomaly, nevypisuje nezobrazene stranky
        SELECT a.*,  a.name_c AS category_name, a.name_p AS page_name,
                b.url_name AS bulletin_url_name, bp.url_name AS page_url_name
            FROM articles_v a
            JOIN bulletins b ON a.bulletin_id = b.id
            JOIN bulletins_pages bp  ON a.page_id = bp.page_id AND a.bulletin_id = bp.bulletin_id
            WHERE '.join(' AND ', $whereA).'
            ORDER BY a.ctenaru DESC, bp."order" ASC
            LIMIT '.$count.'
            */

        // Ziskame seznam
        $pages = $db->fetchAll($q);


        return $pages;
    }


    /**
     * Vrati zaznam page se zadanym ID nebo null pokud page neexistuje. V prvku
     * 'content' jsou zaznamy vsech contentu, ktere tato stranka obsahuje.
     *
     * @param int $id   ID page, ktera se ma ziskat
     * @return array    Zaznam dane page v DB, v prvku 'content' jsou zaznamy contentu v teto page.
     */
    public static function get($id)
    {
        $db =  Zend_Registry::get('db');
        $select = new Zend_Db_Select($db);
        $select->from('pages', '*')
               ->where('id = ?', $id)
               ->limit(1);
        $row = $db->fetchRow($select);

        if(!empty($row)){
            $select = new Zend_Db_Select($db);
            $select->from(array('cp' => 'content_pages'), array())
                   ->join(array('c' => 'content'),'c.id = cp.content_id','*')
                   ->where('page_id = ?', $id)
                   ->order('content_id');
            $row['content'] = $db->fetchAll($select);

            // Deserializujeme objekty
            foreach($row['content'] as $key => $content){
                $row['content'][$key]['object'] = unserialize(stripslashes($content['serialized_object']));
            }
        }
        else{
            $row = null;
        }

        return $row;
    }

    /**
     * Vrati zaznamy pages ve kterych je zadany content nebo null pokud takove pages neexistuji.
     *
     * @param int $content_id   ID contentu, jehoz pages hledame
     */
    public static function getContentPages($content_id)
    {
        $content_id = (int)$content_id;
        $db =  Zend_Registry::get('db');
        $select = new Zend_Db_Select($db);
        $select->from('pages', '*')
               ->join(array('cp' => 'content_pages'),'cp.page_id = pages.id', array())
               ->where("content_id = ?", $content_id)
               ->order('page_id');

        $rows = $db->fetchAll($select);

        if(empty($rows)){
            $rows = null;
        }

        return $rows;
    }

    /**
     * Prida do stranky (page) content s danym ID pridanim zaznamu v content_pages.
     *
     * @param int $page_id      ID page, do ktere pridavame content
     * @param int $content_id   ID contentu, ktery ma byt pridan do teto page
     * @param int $position     Pozice contentu ve strance
     * @throws Pages_Exception  1 - Pokud neexistuje zadana page nebo content.
     *                          // NENI 2 - Pokud pridavany zaznam jiz existuje.
     *                          6 - Nepodarilo se pridat content z jineho duvodu.
     */
    public static function addContent($page_id, $content_id, $position)
    {
        $db =  Zend_Registry::get('db');
        $data = array('page_id' => $page_id, 'content_id' => $content_id, 'position' => $position);

        try{
            $db->insert('content_pages', $data);
        }
        catch(Zend_Db_Statement_Exception $e){
            if(stripos($e->getMessage(), 'Unique violation') !== false){
                /*
                throw new Pages_Exception('Tento content byl jiz do stranky pridan. '.
                    'page_id: '.$page_id.', content_id: '.$content_id.', position: '.$position.
                    'Puvodni vyjimka: '.$e, 2);
                //*/
            }
            elseif(stripos($e->getMessage(), 'Foreign key violation') !== false){
                throw new Pages_Exception('Neexistuje zadana page nebo content. '.
                    'page_id: '.$page_id.', content_id: '.$content_id.', position: '.$position.
                    ' Puvodni vyjimka: '.$e, 1);
            }
            else{
                throw new Pages_Exception("Nepodarilo se pridat content. Puvodni vyjimka:\n".$e, 6);
            }
        }
    }

    /**
     * Odebere ze stranky (page) content s danym ID smazanim zaznamu v content_pages.
     *
     * @param int $page_id      ID page, ze ktere odebirame content
     * @param int $content_id   ID contentu, ktery ma byt odebran z teto page,
     *                          pokud je null, odeberou se vsechny contenty z dane page
     */
    public static function removeContent($page_id, $content_id)
    {
        $db =  Zend_Registry::get('db');

        if($content_id){
            $db->delete('content_pages', sprintf("page_id = %d and content_id = %d", $page_id, $content_id));
        }
        else{
            $db->delete('content_pages', sprintf("page_id = %d", $page_id));
        }
    }


    /**
     * Prida novou page do tabulky pages.
     *
     * @param string $tpl_file  Nazev souboru s hlavnim templatem pro tuto page.
     * @param string $name      Nazev teto page.
     * @return int              Id nove vytvorene page.
     * @throws Pages_Exception  Pokud se nepodarilo vlozit novou page.
     */
    public static function add($tpl_file, $name) {
        $db =  Zend_Registry::get('db');

        $ins_data = array('tpl_file' => $tpl_file, 'name' => $name);

        try{
            $db->insert('pages', $ins_data);
        }
        catch(Exception $e){
            throw new Pages_Exception('Nepodarilo se vlozit novou page do tabulky pages. tpl_file: "'.
            $tpl_file.'", name: "'.$name.'" Puvodni vyjimka:'."\n".$e);
        }

        $page_id = $db->lastInsertId('pages', 'id');


        // Pridame zaznam do LINKS
        $ins_data = array('page_id' => $page_id,
                  'name' => $name);
        $db->insert('links', $ins_data);

        return $page_id;
    }

    /**
     * Upravi zaznam v tabulce pages.
     *
     * @param $pageId       Int     ID page z tabulky pages
     * @param $name         String  Nazev page.
     * @param $category     Int     ID kategorie. Pokud je NULL, je page odebrana ze vsech kategorii.
     * @param $tpl_file     String  Nazev templatu rozlozeni stranky. Pokud je null, neni nastaveni zmeneno.
     * @param $layoutName   String  Nazev layoutu celeho webu, ktery bude pouzit pri
     *                              zobrazeni teto page. Null lze nastavit zadanim new Zend_Db_Expr('null').
     * @param $segments     Array   Array of segment IDs. If null, nothing is changed.
     *
     * @throws  Page_Exception      1 - Pokud neexistuje zadana page nebo content.
     *                              3 - Pokud neexistuje soubor v adresari layoutuu pro dany layout.
     *                              4 - Pokud neexistuje pozadovany soubor v adresari templatuu.
     *                              5 - Nepodarilo se ulozit upravu zaznamu v pages.
     */
    public static function editPage($pageId, $name, $categories = null, $tpl_file = null, $layoutName = null, $segments = null)
    {
        $db =  Zend_Registry::get('db');

        $ins_data = array('name' => $name, 'changed' => new Zend_Db_Expr('current_timestamp'));
        
        try{
            $db->update('pages', $ins_data, 'id = '.(int)$pageId);
        }
        catch(Exception $e){
            throw new Pages_Exception('Nepodarilo se upravit page v tabulce pages. id="'.$page_id.'", tpl_file: "'.
            $tpl_file.'", name: "'.$name.', layout_name = "'.$layoutName.', segments = "'.print_r($segments, true).'"'.
            '. Puvodni vyjimka:'."\n".$e, 5);
        }

        // Kategorie
        $db->delete('pages_categories', 'page_id = '.(int)$pageId);
        if(!empty($categories)){
            if(!is_array($categories)){
                $categories = array($categories);
            }

            foreach($categories as $category_id){
                $db->insert('pages_categories', array('page_id' => $pageId, 'category_id' => (int)$category_id));
            }
        }

        // Segmenty
        if($segments !== null){
            $db->delete('pages_segments', 'page_id='.(int)$pageId);
            foreach($segments as $segment){
                $db->insert('pages_segments', array('page_id' => (int)$pageId, 'segment_id' => $segment));
            }
        } else {
            $db->delete('pages_segments', 'page_id='.(int)$pageId);
        }

        if($layoutName!==null){
            if($layoutName instanceof Zend_Db_Expr && $layoutName == new Zend_Db_Expr('null')){
                $layoutName = null;
            }
            self::setLayout($pageId, $layoutName);
        }
        if($tpl_file){
            self::setTemplate($pageId, $tpl_file);
        }

        // Upravime zaznam v LINKS
        $db->update('links', array('name' => $name), 'page_id = '.$pageId);
    }


    /**
     * Nastavi pro zadanou stranku layout v tabulce pages.layout_name.
     * Pokud je $layoutName NULL, zrusi se nastaveni layoutu pro page.
     *
     * @param $pageId       Int     ID page z pages.
     * @param $layoutName   String  Jmeno layoutu - pouziva se take jako prefix souboruu
     *                              ruznych template v tomto layoutu.
     * @throws  Page_Exception      1 - Pokud neexistuje zadana page nebo content.
     *                              3 - Pokud neexistuje soubor v adresari layoutuu pro dany layout.
     */
    public static function setLayout($pageId, $layoutName = null)
    {
        $db =  Zend_Registry::get('db');
        
        // Overeni existence Page s danym ID
        $page = self::get((int)$pageId);
        if(empty($page)){
            throw new Pages_Exception("Stranka s ID = '$pageId' neexistuje.", 1);
        }

        // Overime existenci pozadovaneho layoutu
        if($layoutName === null){
            $layoutName = new Zend_Db_Expr('null');
        }

        // Zapiseme novy layout
        $data = array('layout_name' => $layoutName);
        $db->update('pages', $data, 'id='.(int)$pageId);

    }


    /**
     * Nastavi pro zadanou stranku (page) template v pages.tpl_file.
     *
     * @param $pageId       Int     ID page z pages.
     * @param $layoutName   String  Jmeno souboru templatu v adresari page_templates podle configu.
     * @throws  Page_Exception      1 - Pokud neexistuje zadana page nebo content.
     *                              4 - Pokud neexistuje pozadovany soubor v adresari templatuu.
     */
    public static function setTemplate($pageId, $tplFile)
    {
        $db =  Zend_Registry::get('db');
        $config =  Zend_Registry::get('config');

        // Overeni existence Page s danym ID
        $page = self::get((int)$pageId);
        if(empty($page)){
            throw new Pages_Exception("Stranka s ID = '$pageId' neexistuje.", 1);
        }

        // Overime existenci pozadovaneho template
        $path = $config->paths->page_templates;
        if(!is_readable($path.'/'.$tplFile) ||
            !is_readable($path.'/'.$tplFile))
        {
            throw new Pages_Exception("Page template '$tplFile' v adresari '$path' neexistuje.", 4);
        }

        // Zapiseme novy template
        $data = array('tpl_file' => $tplFile);
        $db->update('pages', $data, 'id='.(int)$pageId);

    }
   
    /**
     * Vrati seznam pages
     * @return Zend_Db_Select
     */
    public static function getPagesQuery() {
         $db = Zend_Registry::get('db');       
         $select = $db->select()->from(array('p' => 'pages'), array('*'))
                 ->joinLeft(array('cat' => new Zend_Db_Expr('(
                            SELECT c.*, pc.page_id 
                            FROM categories c
                            JOIN (
                                SELECT pc1.page_id, pc1.significance, max(pc2.category_id) AS category_id FROM
                                    (SELECT page_id, max(significance) as significance 
                                        FROM pages_categories GROUP BY page_id) pc1
                                JOIN pages_categories pc2 ON (pc1.page_id = pc2.page_id AND pc1.significance = pc2.significance) OR (pc1.page_id = pc2.page_id AND pc1.significance IS NULL)
                                GROUP BY pc1.page_id, pc1.significance     
                                ) pc ON c.id = pc.category_id
                        )')), 'cat.page_id = p.id', array('category' => 'name'))
                 ->joinLeft(array('l'=>'links'),'l.page_id = p.id',array('link' => 'l.id'));        
         return $select;
    }
    
    
      /**
     * Vrati seznam contentu pridanych do pages
     * @return Array
     */
    public static function getPagesContent($page_id) {
         $db = Zend_Registry::get('db');       
         $select = $db->select()->from(array('cp' => 'content_pages'))
                 ->joinLeft(array('c'=>'content'),'c.id = cp.content_id',
                         array('content_object'=>'c.serialized_object','class_name'=>'c.class_name','changed'=>'c.changed'))
                 ->where('cp.page_id = ?',$page_id)
                 ->order('cp.position');
         $pages = $db->fetchAll($select);
          
         if(empty($pages)) return array();
         
         foreach ($pages as $key => $page) {
             $pages[$key]['content_object'] = unserialize(stripslashes($page['content_object']));             
         }
        
         return $pages;
    }
    
    
     /**
     * Vrati pocet contentu pridanych do pages
     * @return Array
     */
    public static function getCountContent($page_id) {
         $db = Zend_Registry::get('db');       
         $select = $db->select()->from(array('cp' => 'content_pages'))
                 ->where('cp.page_id = ?',$page_id);
         $pages = $db->fetchAll($select);         
         return count($pages);
    }
    
        
    /**
     * Vrati seznam pages reprezentanta
     * @param int $rep_id ID repa
     * @param bool $db_select
     * @return array pole stranek | Zend_Db_Select
     */
    public static function getRepPages($rep_id, $db_select = false) {
        $db = Zend_Registry::get('db');
        $select = $db->select()->from(array('bp'=>'bulletins_pages'),array('page_id'=>'p.id','name'=>'p.name'))
                ->joinLeft(array('b'=>'bulletins'),'bp.bulletin_id = b.id',null)
                ->joinLeft(array('p'=>'pages'),'bp.page_id = p.id',null)
                ->joinLeft(array('rp'=>'reps_pages'),'bp.page_id = rp.page_id',null)
                ->where('p.deleted IS NULL')->where('b.deleted IS NULL')->where('rp.repre_id = ? OR rp.repre_id IS NULL',$rep_id);
        
        if ($db_select) {
            return $select;
        }
        
        $rows = $db->fetchAll($select);
        
        return $rows;
    }
    
   /**
    * Vrati seznam segmentu s pristupem na stranku
    * @param int $page_id ID stránky
    * @return array
    */
    public static function getPageSegments($page_id) {
        $db = Zend_Registry::get('db');
        
        $rp = new Repspages();
        
        $reps = $rp->getReps($page_id);
        
        //segment zobrazime jen v pripade ze stranka nema repa, v priprade ze ma repa i segment je dostupna pouze pro segment repa
        if ($reps) {
            return null;
        }
        
        $select = $db->select()->from(array('ps' => 'pages_segments'), null)
                        ->joinLeft(array('p' => 'pages'), 'ps.page_id = p.id', null)
                        ->joinLeft(array('s' => 'segments'), 'ps.segment_id = s.id', array('id'=>'s.id','name' => 's.name'))
                        ->where('p.deleted IS NULL')->where('s.deleted IS NULL')->where('ps.page_id = ?', $page_id)
                        ->order('s.id DESC');
        
        $rows = $db->fetchAll($select);
        
        if (!$rows) {
           $f_select = $db->select()->from('segments',array('id','name'))->where('deleted IS NULL')->order('id DESC');
           $rows = $db->fetchAll($f_select);
        }
        
        return $rows;
    }

    /**
     * Seznam stranek dostupnych pro dany segment
     * @param int $segment_id
     * @return array
     */
    public static function getSegmentPages($segment_id) {

        $db = Zend_Registry::get('db');

        $select = $db->select()->from(array('bp'=>'bulletins_pages'),array('page_id'=>new Zend_Db_Expr('DISTINCT ON (p.id) p.id'),'name'=>'p.name'))
                ->joinLeft(array('b'=>'bulletins'),'bp.bulletin_id = b.id',null)
                ->joinLeft(array('p'=>'pages'),'bp.page_id = p.id',null)
                ->joinLeft(array('rp'=>'reps_pages'),'bp.page_id = rp.page_id',null)
                ->joinLeft(array('u'=>'users'),'rp.repre_id = u.id AND u.deleted IS NULL',null)
                ->joinLeft(array('ps' => 'pages_segments'), 'bp.page_id=ps.page_id', null)
                ->joinLeft(array('s'=>'segments'),'ps.segment_id = s.id',null)
                ->where('p.deleted IS NULL')->where('b.deleted IS NULL')->where('s.deleted IS NULL')->where('(ps.segment_id = ? AND u.id IS NULL) OR (rp.repre_id IS NULL AND ps.segment_id IS NULL)',$segment_id)
                ->order('p.id DESC');
        $rows = $db->fetchAll($select);
          
        return $rows;
    }
    
    /**
     * Vrati seznam uzivatelu s pristupem na stranku
     * @param int $page_id ID stranky
     * @return array
     */
    public static function getPageUsers($page_id) {
        $db = Zend_Registry::get('db');

        $selreps = $db->select()->from(array('rp'=>'reps_pages'),array('repre_id'))
                ->joinLeft(array('u'=>'users'),'rp.repre_id = u.id',null)
                ->where('page_id = ?',$page_id)->where('u.deleted IS NULL')
                ->order('u.id DESC');
        
        $reps = $db->fetchCol($selreps);
        
        $selsegs = $db->select()->from('pages_segments',array('segment_id'))->where('page_id = ?',$page_id);
        
        $segs = $db->fetchCol($selsegs);

        //stranka nema  ani repa ani segment pristup maji vsichni
        if(!$segs && !$reps) {
           $selusers = $db->select()->from('users')->where('deleted IS NULL')->order('id DESC');
           $users = $db->fetchAll($selusers);
           return $users;
        }

        //stranka ma pouze segment 
        if($segs && !$reps) {
             $selusers = $db->select()->from('users')->where('deleted IS NULL')->where('segment_id IN (?)',$segs)->order('id DESC');
             $users = $db->fetchAll($selusers);
             return $users;
        }

         //stranka ma pouze repa 
        if(!$segs && $reps) {
             $selusers = $db->select()->from(array('ur'=>'users_reps'),array('u.id','u.surname','u.name'))
                     ->joinLeft(array('u'=>'users'),'ur.user_id = u.id',null)
                     ->where('u.deleted IS NULL')->where('ur.repre_id IN (?)',$reps)->order('u.id DESC');
             $users = $db->fetchAll($selusers);
             return $users;
        }
        
         //stranka ma repa i segment 
        if($segs && $reps) {
            $selusers = $db->select()->from(array('ur'=>'users_reps'),array('u.id','u.surname','u.name'))
                     ->joinLeft(array('u'=>'users'),'ur.user_id = u.id',null)
                     ->where('u.deleted IS NULL')->where('ur.repre_id IN (?)',$reps)->where('u.segment_id IN (?)',$segs)->order('u.id DESC');
            $users = $db->fetchAll($selusers);
            return $users;
        }

        
    }
    
    /**
     * Seznam stranek dostupne pro uzivatele
     * @param array $segments_id
     * @param int $reps_id  ID repa
     * @param bool $db_select 
     * @return array | zend_db_select
     */
    public static function getUserPages($segments_id,$reps_id, $db_select = false) {
        $db = Zend_Registry::get('db');
        
        $filter = array();
        
        $filter[] = '(rp.repre_id IS NULL AND ps.segment_id IS NULL)';
        
        if ($segments_id) {
             $filter[] = $db->quoteInto('(rp.repre_id IS NULL AND ps.segment_id IN (?))',$segments_id);
        }
        
        if ($reps_id) {
            $filter[] = $db->quoteInto('(rp.repre_id IN (?) AND ps.segment_id IS NULL)',$reps_id);
        }
        
        if ($segments_id & $reps_id) {
            $filter[] = '('.$db->quoteInto('rp.repre_id IN (?)',$reps_id).' AND '.$db->quoteInto('ps.segment_id IN (?)',$segments_id).')';
        }
        
        $select = $db->select()->from(array('bp' => 'bulletins_pages'), array('page_id' => new Zend_Db_Expr('DISTINCT ON (p.id) p.id'), 'name' => 'p.name'))
                ->joinLeft(array('b' => 'bulletins'), 'bp.bulletin_id = b.id', null)
                ->joinLeft(array('p' => 'pages'), 'bp.page_id = p.id', null)
                ->joinLeft(array('rp' => 'reps_pages'), 'bp.page_id = rp.page_id', null)
                ->joinLeft(array('u' => 'users'), 'rp.repre_id = u.id AND u.deleted IS NULL', null)
                ->joinLeft(array('ps' => 'pages_segments'), 'bp.page_id=ps.page_id', null)
                ->joinLeft(array('s' => 'segments'), 'ps.segment_id = s.id', null)
                ->where('p.deleted IS NULL')->where('b.deleted IS NULL')->where('s.deleted IS NULL')
                ->where(implode(' OR ',$filter))
                ->order('p.id DESC');
        
        if ($db_select) {
            return $select;
        }
        
        return $db->fetchAll($select);
    }
       
    /**
     * Vrati pole bulletinu do kterych je page zarazena
     * @param int $page_id
     * @return array 
     */
    public static function getPageBulletins($page_id) {
        
        $db = Zend_Registry::get('db');
        
        $select = $db->select()->from(array('b'=>'bulletins'),array('b.id','b.name'))->joinLeft(array('bp'=>'bulletins_pages'),'b.id=bp.bulletin_id',null)
                ->where('b.deleted IS NULL')->where('bp.page_id = ?',$page_id);

        return $db->fetchPairs($select);

    }
    
    
     /**
     * Smaze page podle ID
     * 
     * @param   int     ID page 
     * @return  bool    vysledek smazani
     */
    public static function delete($id){

        /* @var $db Zend_Db_Adapter_Abstract */
        $db =  Zend_Registry::get('db');
        
        $data = array('deleted' => new Zend_Db_Expr('current_timestamp'));

        $where = $db->quoteInto('id = ?', $id);
        
        $affected = $db->update('pages', $data, $where);
        
        if($affected){
            return true;
        }
        else{
            return false;
        }
    }
    
    /**
     * Obnovi page podle ID
     * 
     * @param   int     ID page 
     * @return  bool    vysledek smazani
     */
    public static function restore($id){
        
        /* @var $db Zend_Db_Adapter_Abstract */
        $db =  Zend_Registry::get('db');
        
        $data = array('deleted' => null);

        $where = $db->quoteInto('id = ?', $id);
        
        $affected = $db->update('pages', $data, $where);
        
        if($affected){
            return true;
        }
        else{
            return false;
        }
    }


}
