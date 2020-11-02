 <?php

/**
 *  Obecna vyjimka.
 */
class MonitoringexportsException extends Exception {

}

/**
 *  Trida obsahujici metody pro vytvareni exportu ze statistik.
 *
 * Metody by mely byt vsechny staticke. Admin_StatsController::exportnewAction() sama vybere odpovidajici
 * metodu z teto tridy a zavola ji. Zaroven je pri volani predan jako prvni parametr Request.
 *
 * Kazda metoda si sama resi, jak export provede, obvzkle si zavola
 * Ibulletin_Excel::ExportXLSX().
 * SQL dotazy mohou byt primo v teto tride, neni nutne je mit v Statistics.php.
 *
 * Pomocne metody by mely byt prefixovany nazvem hlavni metody oddelene "_".
 *
 *  @author Petr Skoda
 */
class Monitoringexports {

    /**
     * Pripoji k vystupu exportu pozadovana policka uzivatele jak z users tak z users_attribs
     * podle nastaveni v config->stats->usersAttribsToExport
     *
     * SQL dotaz predany do teto metody musi mit ve vystupu user_id.
     *
     * @param   string  SQL dotaz, ke kteremu chceme pripojit sloupce z users a users_attribs podle configu.
     *                  MUSI obsahovat sloupec user_id
     * @param   array   Atributy dodaneho dotazu, podle kterych ma byt vysledek serazen.
     *                  Jednotlive atributy museji byt v poli samostatne, aby mohl byt pridan prefix.
     *                  POZOR! pokud neni zadano, neni zarucene, ze bude vysledek razen spravne!
     * @param   string $onJoinId    nazev sloupe podle ktereho se bude joinovat, defaultně user_id
     * @return  array  Data vytazena z DB obsahujici puvodni dotaz propojeny s daty podle configu.
     *                  Prvni radek obsahuje hlavicku s popisy policek prelozenych podle configu textu.
     */
    public static function joinUsersData($sql, $order = null, $onJoinId = "user_id") {
        // Zname specialni atributy, ktere jsou vytvoreny nejakym zvlastnim zpusobem, nepatri primo do users ani users_attribs
        // SPECIALNI atributy museji mit unikatni jmeno, aby se nekrizily s zadnnym jinym atributem z users nebo
        // users_attribs nebo z nektereho dotazu!
        // Klic je jmeno unikatniho atributu a hodnota je jmeno subselectu, z ktereho se sloupec ma vzit...
        $specialAttribsKnown = array('reps' => 'reps', 'rep_names' => 'reps');

        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        $texts = Ibulletin_Texts::getSet('admin.stats.exportattribs');

        $attribs = $config->stats->getCommaSeparated('usersAttribsToExport');
        $attribsOrig = $attribs;


        // Pokusime se odstranit strednik na konci SQL
        $sql = rtrim(rtrim($sql), ';');

        // Seznam atributu z users
        $q = "SELECT column_name, data_type FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'users' ORDER BY ordinal_position";
        $usersColumns = $db->fetchAssoc($q);


        // Rozdelime, ktere atributy kam patri
        $attrUsers = array();
        $attrSpecial = array();
        $attrUsersAttribs = array();
        $attrAllSel = array(); // Pole k pouziti do  from v Zend_Db_Select se jmeny sloupcu
        foreach ($attribs as $key => $attr) {
            $prefix = null;
            // Atributy z users
            if (isset($usersColumns[$attr])) {
                $attrUsers[] = $attr;
                $prefix = 'u';
                unset($attribs[$key]);
            }

            // Atributy, ktere patri specialnim funkcim
            if (isset($specialAttribsKnown[$attr])) {
                $attrSpecial[] = $attr;
                //$prefix = $attr; // Jako prefix pro specialni pouzijeme cele jmeno attr - kvuli kolizim
                $prefix = $specialAttribsKnown[$attr];
                unset($attribs[$key]);
            }

            // Atriuty z users_attribs oznacene prefixem "ua."
            if (substr($attr, 0, 3) == 'ua.') {
                $attr = substr($attr, 3);
                $attrUsersAttribs[] = $attr;
                $prefix = 'ua';
                //$attribsOrig[$key] = $prefix.'."'.$attr.'"'; // musime upravit v originale pro spravny vypis
                unset($attribs[$key]);
            }

            if ($prefix === null) {
                $prefix = 'ua'; // users_answers
            }

            // Pridani do hlavniho pole pro select
            $attrAllSel[] = $prefix . '.' . $attr;
        }
        // Atributy z users_attribs
        $attrUsersAttribs = (array) $attrUsersAttribs + (array) $attribs;


        // Hlavni select psojujici vse dohromady
        $sel = new Zend_Db_Select($db);
        $sel->from(array('u' => 'users'), $attrAllSel)
                ->join(array('d' => new Zend_Db_Expr("($sql)")), 'd.' . $onJoinId . ' = u.id', array('*'));


        // Atributy z users_attribs
        if (!empty($attrUsersAttribs)) {
            $uaSel = new Zend_Db_Select($db);
            $uaSel->from(array('u' => 'users'), array('*', 'u.id AS user_id_main'));
            // Jednotlive sloupce vyhledame a naJOINujeme
            foreach ($attrUsersAttribs as $attr) {
                $uaSel->joinLeft(array($attr => new Zend_Db_Expr("(SELECT user_id, max(val) AS \"$attr\" FROM users_attribs WHERE name = '$attr' GROUP BY user_id)")), "\"$attr\"" . '.user_id = u.id');
            }
            $sel->joinLeft(array('ua' => $uaSel), 'ua.user_id_main = u.id', array());
        }

        // users_attribs
        ###### Specialni sloupce ######
        // reps a rep_names
        if (in_array('reps', $attrSpecial) || in_array('rep_names', $attrSpecial)) {
            $repsSel = "SELECT ur.user_id, 
                            array_to_string(array_agg(u.name||' '||u.surname), ', ') as rep_names,
                            array_to_string(array_agg(u.id), ', ') as reps
                        FROM users u JOIN users_reps ur ON u.id = ur.repre_id GROUP BY ur.user_id
            ";
            $sel->joinLeft(array('reps' => new Zend_Db_Expr("($repsSel)")), 'reps.user_id = u.id', array());
        }

        // Pridame razeni, pokud bylo predano
        if (is_array($order)) {
            foreach ($order as $key => $val) {
                // Pridame prefixy d. kazdemu atributu a pridame k razeni
                $sel->order('d.' . $val);
            }
        }

        //echo nl2br($sel); exit;
        // Ziskame data a vytvorime radek hlavicky obsahujici prelozene nazvy sloupcu
        $data = $db->fetchAll($sel);
        if (!empty($data)) {
            $head = array();
            foreach ($data[0] as $colName => $val) {
                $text = $texts->$colName;
                if ($text) {
                    $head[$colName] = $text;
                } else {
                    $head[$colName] = $colName;
                }
            }
            // Pridame na zacate pole dat.
            array_unshift($data, $head);
        }

        //dump($data);
        //exit;


        return $data;
    }

    /**
     * Vypise seznam uzivatelu, kteri byli podle mailu pozvani na dany content.
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content int      Content ID
     * emailId int      ID emailu
     * from    string   ISO timestamp zacatku sledovaneho intervalu
     * to      string   ISO timestamp konce sledovaneho intervalu
     * repre   int      ID reprezentanta (nepovinne)
     *
     * @param  Zend_Controller_Request_Abstractt
     */
    public static function indetail_invited($req) {
        $emailId = $req->getParam('emailId', null);

        $filenamePart = (!empty($emailId) ? 'email_' . $emailId : '') .
                (!empty($waveId) ? 'wave_' . $waveId : '') .
                (empty($emailId) && empty($waveId) ? 'all' : '');
        $filename = 'indetail-' . $req->getParam('contentId') . '-invited-' . $filenamePart;

        $users = Statistics::getIndetailInvited($req->getParam('content'), $emailId, $req->getParam('from'), $req->getParam('to'), $req->getParam('repre')
        );


        Ibulletin_Excel::ExportXLSX($users, $filename, false, null, 1);
    }

    /**
     * Vypise seznam uzivatelu, kteri prisli na dany content.
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content int      Content ID
     * emailId int      ID emailu
     * from    string   ISO timestamp zacatku sledovaneho intervalu
     * to      string   ISO timestamp konce sledovaneho intervalu
     * repre   int      ID reprezentanta (nepovinne)
     *
     * @param  Zend_Controller_Request_Abstractt
     */
    public static function indetail_come($req) {
        $emailId = $req->getParam('emailId', null);
        $waveId = $req->getParam('wave', null);

        $filenamePart = (!empty($emailId) ? 'email_' . $emailId : '') .
                (!empty($waveId) ? 'wave_' . $waveId : '') .
                (empty($emailId) && empty($waveId) ? 'all' : '');
        $filename = 'indetail-' . $req->getParam('contentId') . '-come-' . $filenamePart;

        $users = Statistics::getIndetailCome($req->getParam('content'), $emailId, $waveId, $req->getParam('from'), $req->getParam('to'), $req->getParam('repre')
        );


        Ibulletin_Excel::ExportXLSX($users, $filename, false, null, 1);
    }

    /**
     * Vypise seznam uzivatelu, kteri spustili prezentaci na danem contentu.
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content int      Content ID
     * emailId int      ID emailu
     * from    string   ISO timestamp zacatku sledovaneho intervalu
     * to      string   ISO timestamp konce sledovaneho intervalu
     * repre   int      ID reprezentanta (nepovinne)
     *
     * @param  Zend_Controller_Request_Abstractt
     */
    public static function indetail_started($req) {
        $emailId = $req->getParam('emailId', null);
        $waveId = $req->getParam('wave', null);

        $filenamePart = (!empty($emailId) ? 'email_' . $emailId : '') .
                (!empty($waveId) ? 'wave_' . $waveId : '') .
                (empty($emailId) && empty($waveId) ? 'all' : '');
        $filename = 'indetail-' . $req->getParam('contentId') . '-started-' . $filenamePart;

        $users = Statistics::getIndetailStarted($req->getParam('content'), $emailId, $waveId, $req->getParam('from'), $req->getParam('to'), $req->getParam('repre')
        );


        Ibulletin_Excel::ExportXLSX($users, $filename, false, null, 1);
    }

    /**
     * Vypise seznam uzivatelu, kteri dokoncili prezentaci na danem contentu.
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content int      Content ID
     * emailId int      ID emailu
     * from    string   ISO timestamp zacatku sledovaneho intervalu
     * to      string   ISO timestamp konce sledovaneho intervalu
     * repre   int      ID reprezentanta (nepovinne)
     *
     * @param  Zend_Controller_Request_Abstractt
     */
    public static function indetail_finished($req) {
        $emailId = $req->getParam('emailId', null);
        $waveId = $req->getParam('wave', null);

        $filenamePart = (!empty($emailId) ? 'email_' . $emailId : '') .
                (!empty($waveId) ? 'wave_' . $waveId : '') .
                (empty($emailId) && empty($waveId) ? 'all' : '');
        $filename = 'indetail-' . $req->getParam('contentId') . '-finished-' . $filenamePart;

        $users = Statistics::getIndetailFinished($req->getParam('content'), $emailId, $waveId, $req->getParam('from'), $req->getParam('to'), $req->getParam('repre'), $req->getParam('total')
        );


        Ibulletin_Excel::ExportXLSX($users, $filename, false, null, 1);
    }

    /**
     * Vypise seznam uzivatelu a jejich navstev a reprezentantu
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content int      Content ID
     * from    string   ISO timestamp zacatku sledovaneho intervalu
     * to      string   ISO timestamp konce sledovaneho intervalu
     * repre   int      ID reprezentanta (nepovinne)
     *
     * @param  Zend_Controller_Request_Abstractt
     */
    public static function indetail_visits_physicians($req) {
        $fromIso = $req->getParam('from');
        $toIso = $req->getParam('to');
        $repreId = (int) $req->getParam('repre');
        $contentId = (int) $req->getParam('content');

        $filenamePart = $repreId ? '-repre-' . $repreId : '';
        $filename = $req->getParam('filename', 'indetail-visits') . '-physicians' . $filenamePart;

        if (!Reps::checkSessionsByReps()) {
            $repreWhere1 = "AND isp.user_id IN (SELECT user_id FROM users_reps WHERE repre_id = '" . (int) $repreId . "')";
            $repreWhere2 = "AND isr.user_id IN (SELECT user_id FROM users_reps WHERE repre_id = '" . (int) $repreId . "')";
        } else {
            $repreWhere1 = "AND isp.repre_id='" . (int) $repreId . "'";
            $repreWhere2 = "AND isr.repre_id='" . (int) $repreId . "'";
        }

        $sel = "
            select distinct
                u.id as user_id,
                count(distinct s.id) as session_count,
                array_length(presentations,1) as presentations_count,
                presentations,
                array_length(resources,1) as resources_count,
                resources,
                ((coalesce(sum(sess_pres.sum_time),0) + coalesce(sum(sess_res.sum_time),0))/1000)::varchar::interval as session_length,
                (((coalesce(sum(sess_pres.sum_time),0) + coalesce(sum(sess_res.sum_time),0))/1000)/count(distinct s.id))::varchar::interval as session_avg,
                slides_count
            --    slides_count/count(distinct s.id) as slides_avg  -- tohle je asi blbost pocitat
            from
                users_vf as u
                join sessions as s on (u.id=s.user_id)
                left join (select res.user_id, array_agg(distinct res.content_id order by res.content_id) as presentations, sum(res.max_time) as sum_time
                    from
                        (select distinct isp.user_id, isp.content_id, isp.max_time, isp.displayed_versions
                        from indetail_sessions_pres_v as isp
                        where isp.timestamp >= '$fromIso' AND isp.timestamp <= '$toIso'
                            " . ($repreId ? $repreWhere1 : '') . "
                            " . ($contentId ? "AND isp.content_id=$contentId" : '') . "
                        ) as res
                    group by res.user_id
                    ) as sess_pres on (u.id=sess_pres.user_id)
                left join (select res.user_id, array_agg(distinct res.resource_id order by res.resource_id) as resources, sum(res.max_time) as sum_time
                    from 
                        (select distinct isr.user_id, isr.resource_id, r.name, isr.max_time
                        from resources as r
                        join indetail_sessions_res_v as isr on (isr.resource_id=r.id)
                        where 
                            isr.timestamp >= '$fromIso' AND isr.timestamp <= '$toIso'
                            " . ($repreId ? $repreWhere2 : '') . "
                            " . ($contentId ? "AND r.content_id=$contentId" : '') . "
                        ) as res
                    group by res.user_id
                    ) as sess_res on (u.id=sess_res.user_id)
                left join (select user_id, count(distinct slideon_id) as slides_count
                    from indetail_slides_v as isv
                    --where isv.content_id and isv.timestamp<> and isv.repre_id
                    group by user_id
                    ) as slides_res on (u.id=slides_res.user_id)
            where
                (s.id in (select session_id from resources_stats) or s.id in (select session_id from page_views where id in (select page_views_id from indetail_stats)))
                and (presentations is not null or resources is not null)
            group by
                u.id, presentations, resources, slides_count
            order by 
                u.id
        ";

        //echo nl2br($sel); exit;

        $users = Monitoringexports::joinUsersData($sel, array('user_id'));

        Ibulletin_Excel::ExportXLSX($users, $filename, false, null, 1);
    }

    /**
     * Vypise seznam navstev a jejich parametru.
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content          int      Content ID
     * from             string   ISO timestamp zacatku sledovaneho intervalu
     * to               string   ISO timestamp konce sledovaneho intervalu
     * repre            int      ID reprezentanta (nepovinne)
     * special_type     string   specialni typ exportu - aktualne muze byt nastaveno "short" pro zkraceny export
     *
     * @param  Zend_Controller_Request_Abstractt
     */
    public static function indetail_visits($req) {
        $config = Zend_Registry::get('config');
        $db = Zend_Registry::get('db');
        $fromIso = $req->getParam('from');
        $toIso = $req->getParam('to');
        $repreId = (int) $req->getParam('repre');
        $contentId = (int) $req->getParam('content');

        // Nastavime si flag, jestli delame strucny vypis
        $short = $req->getParam('special_type') == 'short' ? true : false;

        $filenamePart = $repreId ? '-repre-' . $repreId : '';

        if ($short) {
            $filenamePart = '-short' . $filenamePart;
        }

        $filename = $req->getParam('filename', 'indetail-visits-' . $contentId) . $filenamePart;

        $filterByUsersReps = false;
        if (!Reps::checkSessionsByReps()) {
            $repreJoin = "JOIN users_reps as ur ON ur.user_id = inds.user_id AND ur.repre_id =  $repreId";
            $filterByUsersReps = true;
            $repreWhere1 = "AND isp.user_id IN (SELECT user_id FROM users_reps WHERE repre_id = '" . (int) $repreId . "')";
            $repreWhere2 = "AND isr.user_id IN (SELECT user_id FROM users_reps WHERE repre_id = '" . (int) $repreId . "')";
        } else {
            $repreJoin = "JOIN (SELECT distinct(session_id) FROM sessions_by_reps WHERE repre_id = $repreId) sbr ON sbr.session_id = s.id";
            $repreWhere1 = "AND isp.repre_id='$repreId'";
            $repreWhere2 = "AND isr.repre_id='$repreId'";
        }


        // Pro nektere exporty chceme i seznam casu stravenych na jedntlivych slidech
        if ($contentId) {
            // Ziskame seznam vsech slides
            $sel = "SELECT id, slide_num, name FROM slides WHERE content_id = ${contentId}";
            $slides = $db->fetchAll($sel);
            // Nachystame do selectu casti, ktere vybiraji sloupce jednotlivych resources
            $slidesSelA = array();
            $slidesColumnsA = array();

            if ($config->indetail->useSlideNameInstedOfSlideNum == 1) {
                foreach ($slides as $slide) {
                    $slidesSelA[] = "round(sum(CASE WHEN inds.slide_id = ${slide['id']} THEN inds.time_slide ELSE null END)/1000.0, '1') as \"slide_${slide['slide_num']}\"";
                    $slidesColumnsA[] = "sl.\"slide_${slide['slide_num']}\" as \"" . (($slide['name'] != '') ? $slide['name'] : "Slide ${slide['slide_num']}") . " (sum sec)\"";
                }
            } else {
                foreach ($slides as $slide) {
                    $slidesSelA[] = "round(sum(CASE WHEN inds.slide_id = ${slide['id']} THEN inds.time_slide ELSE null END)/1000.0, '1') as \"slide_${slide['slide_num']}\"";
                    $slidesColumnsA[] = "sl.\"slide_${slide['slide_num']}\" as \"Slide ${slide['slide_num']} (sum sec)\"";
                }
            }

            // Select pro nalezeni casu na jednotlivych slidech
            $slidesSel = "
                SELECT s.id AS session_id " .
                    (!empty($slidesSelA) ? ', ' . join(',', $slidesSelA) : '') . "
                FROM page_views pv
                JOIN sessions s ON pv.session_id = s.id
                JOIN indetail_stats inds ON inds.page_views_id = pv.id AND inds.content_id = ${contentId}
                " . ($repreId ? $repreJoin : '') . "
                WHERE
                    s.timestamp >= '$fromIso' AND s.timestamp <= '$toIso'
                GROUP BY s.id
            ";
        }


        // Hlavni select
        $sel = "
            select distinct
                s.id as session_id,
                u.id as user_id,
                " . ($short ? "ARRAY_TO_STRING(ARRAY[u.name, ' ', u.surname], ' ') AS user_name," : "") . "
                sr.repre_id,
                (COALESCE(reps.surname ||' '|| reps.name,reps.surname,reps.name)) as repre_name,
                to_char(s.timestamp, '" . $config->general->dateformatsql->long . "') as timestamp,
                " . (!$short ? "(case when sr.repre_id is null then 'remote' else 'inRep' end) as type," : "") . "
                array_to_string(presentations, ',') as presentations,
                ((coalesce(sess_pres.sum_time,0) + coalesce(sess_res.sum_time,0))/1000)::varchar::interval as session_length_hhmmss,
            -- vraci poradi slidu pro danou session (pouzivat jen pri konkretnim contentu) {{{
                array_to_string(array(
                    SELECT /*inds.content_id || ' - ' ||*/ sl.slide_num FROM slides AS sl
                    JOIN indetail_stats AS inds ON (sl.id = inds.slideon_id)
                    JOIN page_views AS pv ON (inds.page_views_id = pv.id)
                    WHERE pv.session_id=s.id " . ($contentId ? "AND inds.content_id=$contentId" : '') . " --nastaveni content_id
                    ORDER BY inds.timestamp
                ), ',') as slides,
            -- }}}
               " . (!$short ? "
                -- vraci ruzne verze, ktere byly zobrazeny } pouzivat jen pri konkretnim contentu) {{{
                    array_to_string(array(select distinct pv.displayed_version from indetail_stats AS inds
                    join page_views as pv on (pv.id=inds.page_views_id)
                    where pv.session_id=s.id " . ($contentId ? "AND inds.content_id=$contentId" : '') . "  --nastaveni content_id
                    ), ', ') as displayed_versions,
                -- }}}
                " : "") . "
                array_to_string(resources, ', ') AS resources
                -- Jednotlive slidy a jejich casy
                " . (!empty($slidesColumnsA) ? ', ' . join(', ', $slidesColumnsA) : '') . "
            from
                users_vf as u
                --left join users_attribs_v as attribs on (u.id=attribs.user_id)
                join sessions as s on (u.id=s.user_id)
                " . ($filterByUsersReps ? "left join users_reps as sr ON sr.user_id = u.id AND sr.repre_id = '$repreId'" : "left join sessions_by_reps as sr on (s.id=sr.session_id)") . "
                left join users reps ON sr.repre_id = reps.id
                left join (select res.id, array_agg(res.content_id order by res.content_id) as presentations, sum(res.max_time) as sum_time
                    from
                        (select distinct isp.id, isp.content_id, isp.max_time, isp.displayed_versions
                        from indetail_sessions_pres_v as isp
                        where isp.timestamp >= '$fromIso' AND isp.timestamp <= '$toIso'
                            " . ($repreId ? $repreWhere1 : '') . "
                            " . ($contentId ? "AND isp.content_id=$contentId" : '') . "
                        ) as res
                    group by res.id
                    ) as sess_pres on (s.id=sess_pres.id)
                left join (select res.id, array_agg(res.resource_id order by res.resource_id) as resources, sum(res.max_time) as sum_time
                    from 
                        (select distinct isr.id, isr.resource_id, r.name, isr.max_time
                        from resources as r
                        join indetail_sessions_res_v as isr on (isr.resource_id=r.id)
                        where 
                            isr.timestamp >= '$fromIso' AND isr.timestamp <= '$toIso'
                            " . ($repreId ? $repreWhere2 : '') . "
                            " . ($contentId ? "AND r.content_id=$contentId" : '') . "
                        ) as res
                    group by res.id
                    ) as sess_res on (s.id=sess_res.id)
                -- Celkove casy jednotlivych slidu
                " . (!empty($slidesColumnsA) ?
                        "left join (" . $slidesSel . ") sl ON sl.session_id = s.id" : ""
                ) . "
            where
                (presentations is not null or resources is not null)
                
            order by timestamp, " . ($short ? "sr.repre_id" : "s.id" ) . "
        ";

        //echo nl2br($sel); exit;

        $names = Questions::getSlidesName($contentId);

        if (!$short) {
            // Plny export se vsemi sloupci uzivatele podle configu
            $users = Monitoringexports::joinUsersData($sel, array('timestamp', 'session_id'));
            $widths = null; // Default widths
        } else {
            $widths = array(7, 7, 25, 7, 25, 21, 13, 10, 10, 10); // Widths according to special data
            // Strucny export jen se sloupci ktere zde mame, nahrazujeme pripadne texty sloupcu v hlavicce
            $texts = Ibulletin_Texts::getSet('admin.stats.exportattribs');
            $data = $db->fetchAll($sel);
            if (!empty($data)) {
                // Priprava hlavicky
                $head = array();
                foreach ($data[0] as $colName => $val) {
                    $text = $texts->$colName;
                    if ($text) {
                        $head[$colName] = $text;
                    } else {
                        $head[$colName] = $colName;
                    }
                }
                // Pridame na zacatek pole dat.
                array_unshift($data, $head);
                $users = $data;
            }
        }

        //nazvy slidu dle configu
        if ($config->indetail->useSlideNameInstedOfSlideNum == 1) {
            $x = 0;
            foreach ($users as $u) {
                $slides = explode(',', $u['slides']);
                $i = 0;
                foreach ($slides as $s) {
                    $slides[$i] = (isset($names[$s]) ? $names[$s] : $s);
                    $i++;
                }
                $users[$x]['slides'] = implode(',', $slides);
                $x++;
            }
        }

        Ibulletin_Excel::ExportXLSX($users, $filename, false, $widths, 1);
    }

    /**
     * Vypise seznam uzivatelu a globalnich resources, ktere videli vcetne casu, ktery na nich stravili celkem za vsechny navstevy.
     *
     * V requestu by mely byt predany nasledujici parametry:
     * from    string   ISO timestamp zacatku sledovaneho intervalu
     * to      string   ISO timestamp konce sledovaneho intervalu
     * repre   int      ID reprezentanta (nepovinne)
     *
     * @param  Zend_Controller_Request_Abstractt
     */
    public static function indetail_globalresources($req) {
        $db = Zend_Registry::get('db');

        $fromIso = $req->getParam('from');
        $toIso = $req->getParam('to');
        $repreId = $req->getParam('repre');

        $filenamePart = $repreId ? '-repre-' . $repreId : '';
        $filename = $req->getParam('filename', 'indetail-global-resources') . $filenamePart;

        // Ziskame seznam vsech globalnich resources
        $sel = "SELECT id, name FROM resources WHERE content_id IS NULL";
        $resources = $db->fetchAll($sel);

        // Nachystame do selectu casti, ktere vybiraji sloupce jednotlivych resources
        $resSelA = array();
        foreach ($resources as $resource) {
            $resSelA[] = "round(sum(CASE WHEN rs.resource_id = ${resource['id']} THEN rs.time/1000.1 ELSE null END), '1') as \"${resource['name']} (sum sec)\"";
        }

        if (!Reps::checkSessionsByReps()) {
            $repreJoin = "JOIN users_reps as ur ON ur.user_id = u.id AND ur.repre_id = '$repreId'";
        } else {
            $repreJoin = "JOIN (SELECT distinct(session_id) FROM sessions_by_reps WHERE repre_id = $repreId) sbr ON sbr.session_id = s.id";
        }


        $sel = "
            SELECT s.user_id " .
                (!empty($resSelA) ? ', ' . join(',', $resSelA) : '') . "
            FROM sessions s
            JOIN users_vf u ON u.id = s.user_id
            JOIN resources_stats rs ON rs.session_id = s.id
            JOIN resources r ON rs.resource_id = r.id AND r.content_id IS NULL  -- Jen globalni resources
            " . ($repreId ? $repreJoin : '') . "
            WHERE
                s.timestamp >= '$fromIso' AND s.timestamp <= '$toIso'
            GROUP BY s.user_id
        ";

        //echo nl2br($sel); exit;

        $users = Monitoringexports::joinUsersData($sel, array('user_id'));

        Ibulletin_Excel::ExportXLSX($users, $filename, false, null, 1);
    }

    /**
     * Vypise seznam uzivatelu (nebo page_views) a slajdu dane prezentace. Ke kazdemu slajdu je
     * spocitana suma casu straveneho na danem slajdu danym uzivatelem.
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content int      Content ID
     * from    string   ISO timestamp zacatku sledovaneho intervalu
     * to      string   ISO timestamp konce sledovaneho intervalu
     * repre   int      ID reprezentanta (nepovinne)
     * byViews   int      1/0 - vypisovat s presnosti na page_views misto na uzivatele?
     *
     * @param  Zend_Controller_Request_Abstractt
     */
    public static function indetail_presentation_slides($req) {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');
        $fromIso = $req->getParam('from');
        $toIso = $req->getParam('to');
        $repreId = $req->getParam('repre');
        $contentId = $req->getParam('content');
        $byViews = (bool) $req->getParam('byViews');

        $filenamePart = $repreId ? '-repre-' . $repreId : '';
        $filename = $req->getParam('filename', 'indetail-presentation-' . $contentId . '-slides') . $filenamePart;

        // Ziskame seznam vsech slides
        $sel = "SELECT id, slide_num, name FROM slides WHERE content_id = ${contentId}";
        $slides = $db->fetchAll($sel);

        // Nachystame do selectu casti, ktere vybiraji sloupce jednotlivych resources
        $slidesSelA = array();

        if (!Reps::checkSessionsByReps()) {
            $repreJoin = "JOIN users_reps as ur ON ur.user_id = u.id AND ur.repre_id = '$repreId'";
        } else {
            $repreJoin = "JOIN (SELECT distinct(session_id) FROM sessions_by_reps WHERE repre_id = '$repreId') sbr ON sbr.session_id = s.id";
        }

        //nazvy sloupcu dle configu
        if ($config->indetail->useSlideNameInstedOfSlideNum == 1) {
            foreach ($slides as $slide) {
                $slidesSelA[] = "round(sum(CASE WHEN i2.slide_id = ${slide['id']} THEN i2.time_slide ELSE null END)/1000.0, '1') as \"".(($slide['name']!='')?$slide['name']:"Slide ${slide['slide_num']}")." (sum sec)\"";
                if (!$byViews) {
                    $slidesSelA[] = "count(distinct(CASE WHEN (inds.slideon_id = ${slide['id']} AND (inds.slideon <> inds.slideoff OR (inds.time_total = inds.time_slide AND (inds.sequence = 1 OR inds.sequence IS NULL )))) THEN inds.id else null END)) AS \"".(($slide['name']!='')?$slide['name']:"Slide ${slide['slide_num']}")." (visits)\"";
                }
            }
        } else {
            foreach ($slides as $slide) {
                $slidesSelA[] = "round(sum(CASE WHEN i2.slide_id = ${slide['id']} THEN i2.time_slide ELSE null END)/1000.0, '1') as \"Slide ${slide['slide_num']} (sum sec)\"";
                if (!$byViews) {
                    $slidesSelA[] = "count(distinct(CASE WHEN (inds.slideon_id = ${slide['id']} AND (inds.slideon <> inds.slideoff OR (inds.time_total = inds.time_slide AND (inds.sequence = 1 OR inds.sequence IS NULL )))) THEN inds.id else null END)) as \"Slide ${slide['slide_num']} (visits)\"";
                }
            }
        }

        $sel =
            "SELECT inds.user_id ".($byViews ? ", inds.page_views_id " : '').
            (!empty($slidesSelA) ? ', '.join(',', $slidesSelA) : '')."
            FROM page_views pv
            JOIN sessions s ON pv.session_id = s.id
            JOIN indetail_stats inds ON inds.page_views_id = pv.id AND inds.content_id = ${contentId}
            LEFT JOIN indetail_stats i2 ON inds.slideon_id = i2.slide_id AND inds.page_views_id = i2.page_views_id
            JOIN users_vf u ON u.id = inds.user_id -- Musime omezovat podle indetail_stats.user_id a ne podle sessions.user_id (nekdy tam jsou nespravna data)
            ".($repreId ?
                $repreJoin : '')."
            WHERE
                s.timestamp >= '$fromIso' AND s.timestamp <= '$toIso'
            GROUP BY ".($byViews ? 'inds.page_views_id, inds.user_id' : 'inds.user_id')."
        ";

        //echo nl2br($sel); exit;

        $users = Monitoringexports::joinUsersData($sel, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, $filename, false, null, 1);
    }

    /**
     * Najde nejcastejsi prechody mezi slajdy. Hleda dvojice, trojice a ctverice prechodu.
     * Prechody vrati v souhrnne tabulce razene podle cetnosti bez ohledu na to, zda se
     * jedna o dvojice, trocjice nebo ctverice.
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content int      Content ID
     * from    string   ISO timestamp zacatku sledovaneho intervalu
     * to      string   ISO timestamp konce sledovaneho intervalu
     * repre   int      ID reprezentanta (nepovinne)
     */
    public static function indetail_presentation_slides_usual_transitions($req) {
        $db = Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        $fromIso = $req->getParam('from');
        $toIso = $req->getParam('to');
        $repreId = $req->getParam('repre', null);
        $contentId = $req->getParam('content');

        $filenamePart = $repreId ? '-repre-' . $repreId : '';
        $filename = $req->getParam('filename', 'indetail-presentation-' . $contentId . '-slides-usual-transitions') . $filenamePart;

        if (!Reps::checkSessionsByReps()) {
            $repreJoin = "JOIN users_reps as ur ON ur.user_id = u.id AND ur.repre_id = '$repreId'";
        } else {
            $repreJoin = "JOIN sessions_by_reps sbr ON sbr.session_id = s.id AND sbr.repre_id = '$repreId'";
        }

        // Data castych prechodu
        $limit = 10; // Pocet hledanych n-tic na kazdy typ (dvojice, trojice, ctverice)
        $sel = "
            -- Pro kontrolu tohoto selectu lze pouzit pouze dvojice, kde soucet dvojice s jednim slideon
            -- musi souhlasit s poctem zobrazeni daneho slide.
            -- POZOR, pro kontrolu nesmeji byt vzhayovany prechody mezi stejnymi slajdy
            -- Trojice a ctverice prechodu uz nelze kontrolovat, lze jen overovat, ze soucet pro dany slideon je mensi nez pocet zobrazeni

            -- Zakladni vstupni data omezena podle repre, content, timestamps a pripadne s vyhazenymi zaznamy
            -- patrici navsteve prezentace, ktera netravala minimalni pozadovanou dobu
            WITH indetail_stats_filtered AS (
                SELECT DISTINCT i.* FROM indetail_stats i
                JOIN page_views pv ON pv.id = i.page_views_id
                JOIN sessions s ON s.id = pv.session_id
                JOIN users_vf u ON u.id = i.user_id

                -- Servier min/maxSlideTimesMs - minimalni/maximalni cas na danem slajdu
                " . ($config->indetail->minSlideTimesMs || $config->indetail->maxSlideTimesMs ? "
                JOIN
                (
                    SELECT id AS session_id, slideon_id -- provadime spojeni podle session_id i slideon_id tak, aby omezeni bylo shodne s omezenim v monitoringu slides
                    FROM indetail_slides_v i

                    -- Servier minVisitTime - minimalni cas v dane prezentaci za celou session
                    " . ($config->indetail->minVisitTimeMs ? "
                    JOIN
                    (
                        SELECT id AS session_id FROM indetail_slides_v
                        WHERE content_id = " . (int) $contentId . "
                            --AND timestamp>='$fromIso' AND timestamp<='$toIso' -- Zpomaluje
                        GROUP BY id
                        HAVING sum(max_slide_time) > " . $config->indetail->minVisitTimeMs . "
                    ) AS filter ON filter.session_id = i.id" : '') . "

                    --WHERE content_id = " . (int) $contentId . " -- Zpomaluje
                    --    AND timestamp>='$fromIso' AND timestamp<='$toIso' -- Zpomaluje
                    WHERE 1=1
                        -- Servier, kazdy slide musi byt zobrazen po minimalni a maximalni cas, jinak se nepocita (nastavitelne)
                        " . ($config->indetail->minSlideTimesMs ? "AND sum_slide_time >= " . $config->indetail->minSlideTimesMs : '') . "
                        " . ($config->indetail->maxSlideTimesMs ? "AND sum_slide_time <= " . $config->indetail->maxSlideTimesMs : '') . "
                    GROUP BY id, slideon_id
                ) AS filter2 ON filter2.session_id = pv.session_id  AND filter2.slideon_id = i.slideon_id" : '') . "
                "
                . ($repreId ? $repreJoin : '') . "
                WHERE i.content_id=$contentId and s.timestamp>='$fromIso' and s.timestamp<='$toIso'
                	AND i.slideon != i.slideoff -- Odstranujeme, protoze v indetail_slides_v se take nezapocitava do view_count (koliduje s prechodem sama na sebe v inRep preyentaci)
            ),


            -- Dvojice indetail_stats_id, ktere po sobe nasleduji
            -- odfiltrovane duplicity, tedy nasledujici id navstevy je vzdy jen pro jedno ID predchazejci
            following AS (
                SELECT max(id) AS id, next_id
                FROM (
                    SELECT i1.id, min(i2.id) AS next_id
                    FROM indetail_stats i1
                    JOIN indetail_stats i2 on i2.page_views_id = i1.page_views_id AND i1.slideon_id!=i1.slideoff_id /*AND i2.slideon_id!=i2.slideoff_id*/ AND i1.slideon_id=i2.slideoff_id AND i1.timestamp<i2.timestamp
                    JOIN users_vf u ON u.id = i1.user_id
                    WHERE i1.content_id = " . (int) $contentId . " AND i2.content_id = " . (int) $contentId . "
                GROUP BY i1.id
                ) AS foo
                -- musime groupovat podle obou sloupcu, abychom dostali unikatni ID na obou stranach
                GROUP BY next_id
            )

            -- Vlastni select castych prechodu
            SELECT * FROM (
            
            --dvojice
            (
            SELECT count(*), i1.slideoff AS s1, i1.slideon AS s2, cast(null AS integer) AS s3, cast(null AS integer) AS s4
            FROM indetail_stats_filtered i1
            --WHERE  i1.slideon_id!=i1.slideoff_id -- Pro kontrolu celkovych cisel s navstevnosti slajdu toto omezeni nepouzivat
            WHERE /*i1.slideoff != 1 AND i1.slideon != 1 AND */time_total!=0 -- Vyhozeni inicialniho prechodu z 1 na 1, konkretni slideon slideoff nefunguji
            GROUP BY i1.slideoff, i1.slideon
            --ORDER BY count(*) desc
            --LIMIT $limit
            )

            UNION

            --(select 0 AS count, null AS s1, null as s2, null as s3, cast(null AS integer) as s4) -- pro testovani pouze dvojic pak staci dalsi uniony zakomentovat

            (
            --trojice
            SELECT count(*) AS count, i1.slideoff AS s1, i1.slideon AS s2,  i2.slideon AS s3, null AS s4
            FROM following f1
            JOIN indetail_stats_filtered i1 ON f1.id = i1.id
            JOIN indetail_stats_filtered i2 ON f1.next_id = i2.id
            --WHERE i1.slideon_id!=i1.slideoff_id -- Pro kontrolu celkovych cisel s navstevnosti slajdu toto omezeni nepouzivat
            GROUP BY i1.slideoff, i1.slideon, i2.slideon--, i3.slideon
            --ORDER BY count(*) desc
            --LIMIT $limit
            )

            UNION
            (
            --ctverice
            SELECT count(*), i1.slideoff AS s1, i1.slideon AS s2,  i2.slideon AS s3, i3.slideon AS s4
            FROM following f1
            JOIN following f2 ON f1.next_id = f2.id
            JOIN indetail_stats_filtered i1 ON f1.id = i1.id
            JOIN indetail_stats_filtered i2 ON f1.next_id = i2.id
            JOIN indetail_stats_filtered i3 ON f2.next_id = i3.id
            --WHERE i1.slideon_id!=i1.slideoff_id -- Pro kontrolu celkovych cisel s navstevnosti slajdu toto omezeni nepouzivat
            GROUP BY i1.slideoff, i1.slideon, i2.slideon, i3.slideon
            --ORDER BY count(*) desc
            --LIMIT $limit
            )

            ) AS foo

            ORDER BY count desc, s1, s2, s3 NULLS FIRST, s4 NULLS FIRST
        ";
        //echo nl2br($sel); exit;
        //echo $sel; exit;

        $table = $db->fetchAll($sel);

        $names = Questions::getSlidesName($contentId);

        $config = Zend_Registry::get('config');
        //nazvy slidu dle configu
        if ($config->indetail->useSlideNameInstedOfSlideNum == 1) {
            $i = 0;
            foreach ($table as $row) {
                //var_dump((isset($names[$row['s1']])?$names[$row['s1']]:$row['s1']));
                $table[$i]['s1'] = (isset($names[$row['s1']]) ? $names[$row['s1']] : $row['s1']);
                $table[$i]['s2'] = (isset($names[$row['s2']]) ? $names[$row['s2']] : $row['s2']);
                $table[$i]['s3'] = (isset($names[$row['s3']]) ? $names[$row['s3']] : $row['s3']);
                $table[$i]['s4'] = (isset($names[$row['s4']]) ? $names[$row['s4']] : $row['s4']);
                $i++;
            }
        }

        // Hlavicka s texty
        $texts = Ibulletin_Texts::getSet('admin.stats.indetailpresentationsslides');
        array_unshift($table, array(
            $texts->transitionsExportCount,
            $texts->transitionsExportS1,
            $texts->transitionsExportS2,
            $texts->transitionsExportS3,
            $texts->transitionsExportS4
        ));


        Ibulletin_Excel::ExportXLSX($table, $filename, false, array(10, 7, 7, 7, 7), 1);
    }

    /**
     * Vypise seznam unikatnich uzivatelu, kteri otevreli externi link
     *
     * V requestu by mely byt predany nasledujici parametry:
     * bulletin_id  int Bulletin ID
     * linkId   int     ID linku
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function links_unique($req) {

        $bulId = $req->getParam('bulletin_id');
        $linkId = $req->getParam('link_id');

        $db = Zend_Registry::get('db');

        // Hledame unikatni prokliky tak, aby kazdy anomnymni proklik byl unikatni
        $select = $db->select()->distinct()
                ->from(array('pv' => 'page_views'), array('user_id' => 's.user_id'))
                ->join(array('s' => 'sessions'), 's.id = pv.session_id', array())
                ->joinLeft(array('bv' => 'bulletins_v'), 'pv.timestamp > bv.valid_from and pv.timestamp < bv.valid_to', array())
                ->joinLeft(array('u' => 'users_vf'), 'u.id=s.user_id', array())
                ->join(array('l' => 'links'), 'pv.link_id = l.id and l.foreign_url IS NOT NULL', array())
                ->where('u.target is true')
                ->where('l.id = ?', $linkId);


        if (!empty($bulId)) {
            $select->where('bv.id = ?', $bulId);
        }

        $users = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, 'links_unique_id_' . $linkId, false, null, 1);
    }

    /**
     * Vypise seznam unikatnich uzivatelu, kteri otevreli link podle umisterni
     *
     * V requestu by mely byt predany nasledujici parametry:
     * bulletin_id  int Bulletin ID
     * area   string     Area
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function links_unique_area($req) {

        $bul_id = $req->getParam('bulletin_id');
        $area = $req->getParam('area');

        $db = Zend_Registry::get('db');

        $xpath_all = array();

        // dotaz na pocet unikatnich uzivatelu a pocty kliku v kazde oblasti
        $select = new Zend_DB_Select($db);
        $select->distinct()->from(array('pv' => 'page_views'), array('user_id' => 's.user_id'))
                ->join(array('s' => 'sessions'), 's.id = pv.session_id', array())
                ->join(array('u' => 'users_vf'), 'u.id = s.user_id', array());

        // omezeni na bulletin
        $select->where(empty($bul_id) ? 'pv.bulletin_id IN (SELECT id FROM bulletins_v)' : 'pv.bulletin_id = ?', (int) $bul_id);

        switch ($area) {
            case 'mail' :
                $select->where("pv.link_xpath ~* ?", "^mail_link:");
                break;
            case 'other' :
                // doplnek ke vsem ostatnim xpath, emailu a NULL
                $select->where("pv.link_xpath IS NOT NULL")
                        ->where("pv.link_xpath !~* ?", "^mail_link:");
                if (!empty($xpath_all)) {
                    $select->where("pv.link_xpath !~* ?", '^(' . implode('|', $xpath_all) . ')(\/|$)');
                }
                break;
            default:
                // pattern pro postgre regular potrebuje mit escapovane hranate zavorky, tecku, hash
                $xpath = preg_replace(array('/\[/', '/\]/', '/\#/', '/\./'), array('\[', '\]', '\#', '\.'), $area['xpath']);
                // nastavime regular tak, aby na konci patternu nasledovalo lomitko nebo nic, jinak mame neuplny match
                $select->where("pv.link_xpath ~* ?", '^(' . implode('|', $xpath) . ')(\/|$)');
                $xpath_all = array_merge($xpath_all, $xpath);
                break;
        }

        $users = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, 'links_unique_area_' . $area, false, null, 1);
    }

    /**
     * Vypise seznam unikatnich uzivatelu, kteri otevreli link z mailu
     *
     * V requestu by mely byt predany nasledujici parametry:
     * bulletin_id  int Bulletin ID
     * link_title   titulek linku
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function links_unique_mail($req) {

        $bul_id = $req->getParam('bulletin_id');
        $linkTitle = $req->getParam('link_title');

        $db = Zend_Registry::get('db');

        // dotaz na pocet unikatnich uzivatelu a pocty kliku z emailu grupnutych podle casti retezce z xpath
        $select = new Zend_DB_Select($db);
        $select->distinct()->from(array('pv' => 'page_views'), array('user_id' => 'ult.user_id'))
                ->join(array('ult' => 'users_links_tokens'), 'ult.id = pv.users_links_tokens_id', array())
                ->join(array('u' => 'users_vf'), 'u.id = ult.user_id', array())
                ->join(array('l' => 'links'), 'l.id = ult.link_id', array())
                ->where("pv.link_xpath ~* ?", "^mail_link:")
                // omezeni na bulletin
                ->where(empty($bul_id) ? 'l.bulletin_id IN (SELECT id FROM bulletins_v)' : 'l.bulletin_id = ?', (int) $bul_id)
                ->where("substring(pv.link_xpath from '^mail_link:(.*)') = ?", $linkTitle);

        $users = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, 'links_unique_title_' . $linkTitle, false, null, 1);
    }

    /**
     * Vypise seznam unikatnich uzivatelu, kteri otevreli externi link
     *
     * V requestu by mely byt predany nasledujici parametry:
     * resource_id  int Resource ID
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function resources_unique($req) {

        $resourceId = $req->getParam('resource_id');

        // Hledame unikatni prokliky tak, aby kazdy anomnymni proklik byl unikatni
        $db = Zend_Registry::get('db');
        $select = $db->select()->distinct()->from(array('s' => 'sessions'), array('user_id' => 's.user_id'))
                ->join(array('rs' => 'resources_stats'), 's.id = rs.session_id', array())
                ->joinLeft(array('u' => 'users_vf'), 's.user_id = u.id', array())
                ->where('s.user_id IS null OR u.target IS true')
                ->where('rs.resource_id = ?', $resourceId);

        $users = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, 'resources_unique_id_' . $resourceId, false, null, 1);
    }

    /**
     * Vypise seznam unikatnich uzivatelu dle poctu shlednutych prezentaci
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content_count  int pocet shlednuti
     * from_iso string timestamp od
     * to_iso string timestamp do
     * repre_id int ID reprezentanta
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function indetail_connections_views($req) {

        $contentCount = $req->getParam('content_count');
        $fromIso = $req->getParam('from_iso');
        $toIso = $req->getParam('to_iso');
        $repreId = $req->getParam('repre_id');

        $db = Zend_Registry::get('db');

        $subselect = $db->select()->from(array('u' => 'users_vf'), array('user_id' => 'u.id', 'content_count' => 'count(distinct isp.content_id)'))
                ->join(array('isp' => 'indetail_sessions_pres_v'), 'u.id=isp.user_id', array())
                ->where('isp.timestamp > ?', $fromIso)
                ->where('isp.timestamp < ?', $toIso)
                ->group('u.id');

        if ($repreId) {
            if (!Reps::checkSessionsByReps()) {
                $subselect->join(array('ur' => 'users_reps'), "ur.user_id = u.id AND ur.repre_id = '" . (int) $repreId . "'", array());
            } else {
                $subselect->join(array('sbr' => 'sessions_by_reps'), 'sbr.session_id = isp.id AND sbr.repre_id =\'' . (int) $repreId . '\'', array());
            }
        }

        $select = $db->select()->from(array('cc' => new Zend_Db_Expr('(' . $subselect . ')')), array('user_id'))
                ->where('content_count = ?', $contentCount);

        $users = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, 'indetail_connections_views_count_' . $contentCount, false, null, 1);
    }

    /**
     * Vypise seznam unikatnich uzivatelu kteri shledli prezentace v danem poradi
     *
     * V requestu by mely byt predany nasledujici parametry:
     * content_id  int ID prezentace
     * order int poradi
     * from_iso string timestamp od
     * to_iso string timestamp do
     * repre_id int ID reprezentanta
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function indetail_connections_order($req) {

        $contentId = $req->getParam('content_id');
        $order = $req->getParam('order');
        $fromIso = $req->getParam('from_iso');
        $toIso = $req->getParam('to_iso');
        $repreId = $req->getParam('repre_id');

        $db = Zend_Registry::get('db');

        $subsubselect = $db->select()->from(array('u' => 'users_vf'), array('min_timestamp' => 'MIN(s.timestamp)'))
                ->join(array('s' => 'sessions'), 'u.id=s.user_id', array())
                ->join(array('pv' => 'page_views'), 's.id=pv.session_id', array())
                ->join(array('i' => 'indetail_stats'), 'i.page_views_id=pv.id', array('i.user_id', 'i.content_id'))
                ->joinLeft(array('sr' => 'sessions_by_reps'), 's.id = sr.session_id', array())
                ->where('s.timestamp > ?', $fromIso)
                ->where('s.timestamp < ?', $toIso)
                ->group(array('i.user_id', 'i.content_id'));

        if ($repreId) {

            if (!Reps::checkSessionsByReps()) {
                $subsubselect->where('u.id IN (SELECT user_id FROM users_reps WHERE repre_id = ?)', $repreId);
            } else {
                $subsubselect->where('sr.repre_id = ?', $repreId);
            }
        }

        $subselect = $db->select()->from(array('co1' => new Zend_Db_Expr('(' . $subsubselect . ')')), array('user_id', 'content_id', 'poradi' => 'row_number() OVER (PARTITION BY user_id ORDER BY min_timestamp)'));

        $select = $db->select()->from(array('co2' => new Zend_Db_Expr('(' . $subselect . ')')), array('user_id'))
                ->where('content_id = ?', $contentId)
                ->where('poradi = ?', $order);

        $users = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, 'indetail_connections_order_' . $order . '_id_' . $contentId, false, null, 1);
    }

     /**
     * Vypise seznam unikatnich uzivatelu kteri alespon jednu z kombinace
     *
     * V requestu by mely byt predany nasledujici parametry:
     * contents_id  array ID prezentaci z kombinace
     * from_iso string timestamp od
     * to_iso string timestamp do
     * repre_id int ID reprezentanta
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function indetail_connections_combination_at_least($req) {

        $contentsId = explode('|',$req->getParam('contents_id'));
        $fromIso = $req->getParam('from_iso');
        $toIso = $req->getParam('to_iso');
        $repreId = $req->getParam('repre_id');

        $db = Zend_Registry::get('db');

        $subselect = $db->select()->from(array('u' => 'users_vf'), array('min_timestamp' => 'MIN(s.timestamp)'))
                ->join(array('s' => 'sessions'), 'u.id=s.user_id', array())
                ->join(array('pv' => 'page_views'), 's.id=pv.session_id', array())
                ->join(array('i' => 'indetail_stats'), 'i.page_views_id=pv.id', array('i.user_id', 'i.content_id'))
                ->joinLeft(array('sr' => 'sessions_by_reps'), 's.id = sr.session_id', array())
                ->where('s.timestamp > ?', $fromIso)
                ->where('s.timestamp < ?', $toIso)
                ->where('i.content_id IN (?)',$contentsId)
                ->group(array('i.user_id', 'i.content_id'));

        if ($repreId) {

            if (!Reps::checkSessionsByReps()) {
                $subselect->where('u.id IN (SELECT user_id FROM users_reps WHERE repre_id = ?)', $repreId);
            } else {
                $subselect->where('sr.repre_id = ?', $repreId);
            }
        }

        $select = $db->select()->from(array('co1' => new Zend_Db_Expr('(' . $subselect . ')')), array('user_id'));
        $select->group('user_id');

        $users = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, 'indetail_connections_combination_at_least' . implode('-',$contentsId), false, null, 1);
    }

     /**
     * Vypise seznam unikatnich uzivatelu kteri shledli kombinaci prezentaci
     *
     * V requestu by mely byt predany nasledujici parametry:
     * contents_id  array seznam ID pro porovnani
     * from_iso string timestamp od
     * to_iso string timestamp do
     * repre_id int ID reprezentanta
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function indetail_connections_combination_exactly($req) {

        $contentsId = explode('|',$req->getParam('contents_id'));
        $fromIso = $req->getParam('from_iso');
        $toIso = $req->getParam('to_iso');
        $repreId = $req->getParam('repre_id');

        $db = Zend_Registry::get('db');

        $subsubselect = $db->select()->from(array('u' => 'users_vf'), array('min_timestamp' => 'MIN(s.timestamp)'))
                ->join(array('s' => 'sessions'), 'u.id=s.user_id', array())
                ->join(array('pv' => 'page_views'), 's.id=pv.session_id', array())
                ->join(array('i' => 'indetail_stats'), 'i.page_views_id=pv.id', array('i.user_id', 'i.content_id'))
                ->joinLeft(array('sr' => 'sessions_by_reps'), 's.id = sr.session_id', array())
                ->where('s.timestamp > ?', $fromIso)
                ->where('s.timestamp < ?', $toIso)
                ->where('i.content_id IN (?)',$contentsId)
                ->group(array('i.user_id', 'i.content_id'));

        if ($repreId) {

            if (!Reps::checkSessionsByReps()) {
                $subsubselect->where('u.id IN (SELECT user_id FROM users_reps WHERE repre_id = ?)', $repreId);
            } else {
                $subsubselect->where('sr.repre_id = ?', $repreId);
            }
        }

        $subselect = $db->select()->from(array('co1' => new Zend_Db_Expr('(' . $subsubselect . ')')), array('user_id', 'pp'=>new Zend_Db_Expr('count(user_id)')))
                ->group('user_id');

        $select = $db->select()->from(array('co2' => new Zend_Db_Expr('(' . $subselect . ')')), array('user_id','pp'))
                ->where('pp = ?', count($contentsId));

        $users = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users, 'indetail_connections_combination_exactly' . implode('-',$contentsId), false, null, 1);
    }

    /**
     * Vypise platné kontakty dle vydání
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts_active_issue($req) {

        $texts = Ibulletin_Texts::getSet('admin.stats.contacts');

        $bulletins = Statistics::getBulletins_v();

        $gcontacts = array();

        //data pro graf kontaktu dle bulletinu
        foreach ($bulletins as $b) {
            $gcontacts[] = array($b['name'], Statistics::getContactsCountForBulletin($b['id']));
        }

        $gcontacts[] = array($texts->issue, ucfirst($texts->active_contacts));


        Ibulletin_Excel::ExportXLSX(array_reverse($gcontacts), 'contacts_active', false, array(15, 13), 1);
    }

    /**
     * Vypise platné kontakty pro emailing dle vydání
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts_active_emailing_by_issue($req) {

        $texts = Ibulletin_Texts::getSet('admin.stats.contacts');

        $bulletins = Statistics::getBulletins_v();

        $gcontacts_emailing = array();

        //data pro graf kontaktu dle bulletinu
        foreach ($bulletins as $b) {
            $gcontacts_emailing[] = array($b['name'], Statistics::getContactsCountForEmailingForBulletin($b['id']));
        }

        $gcontacts_emailing[] = array($texts->issue, ucfirst($texts->active_contacts_emailing));


        Ibulletin_Excel::ExportXLSX(array_reverse($gcontacts_emailing), 'contacts_active_emailing', false, array(15, 13), 1);
    }

    /**
     * Exportuje platné kontakty
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts_active($req) {

        $db = Zend_Registry::get('db');

        $subselect = 'select id from users_vf WHERE deleted IS NULL and unsubscribed IS NULL AND bad_addr IS FALSE';

        $_select = $db->select()
                ->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                    'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
                    'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

        $result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
        Ibulletin_Excel::ExportXLSX($result, __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje platné kontakty pro emailing
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts_active_emailing($req) {

        $db = Zend_Registry::get('db');

        $subselect = "select id from users_vf WHERE send_emails IS true AND deleted IS NULL AND email IS NOT NULL AND trim(both from email)!='' and bad_addr IS false and unsubscribed IS NULL";

        $_select = $db->select()
                ->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                    'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
                    'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

        $result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

	/**
	 * Exportuje platné kontakty se souhlasem GDPR
	 *
	 * @param req  Zend_Controller_Request_Abstract
	 */
	public static function contacts_active_gdpr($req) {

		$db = Zend_Registry::get('db');

		$subselect = "select id from users_vf WHERE deleted IS NULL AND unsubscribed IS NULL AND bad_addr IS FALSE AND users_vf.id IN (SELECT ua.user_id FROM users_attribs AS ua WHERE (ua.name='gdpr_project' AND val='1'))";

		$_select = $db->select()
			->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
				'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
				'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
			->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

		$_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

		$result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
		Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
	}


	/**
	 * Exportuje platné kontakty s globalnim souhlasem GDPR
	 *
	 * @param req  Zend_Controller_Request_Abstract
	 */
	public static function contacts_active_gdpr_global($req) {

		$db = Zend_Registry::get('db');

		$subselect = "select id from users_vf WHERE deleted IS NULL AND unsubscribed IS NULL AND bad_addr IS FALSE AND users_vf.id IN (SELECT ua.user_id FROM users_attribs AS ua WHERE (ua.name='gdpr_global' AND val='1'))";

		$_select = $db->select()
			->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
				'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
				'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
			->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

		$_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

		$result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
		Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
	}

    /**
     * Exportuje platné kontakty bez email
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts_active_noemail($req) {

        $db = Zend_Registry::get('db');

        $subselect = "select id from users_vf where bad_addr IS false AND deleted IS NULL and (send_emails IS false OR email IS NULL OR trim(both from email)='')";

        $_select = $db->select()
                ->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                    'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
                    'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

        $result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje neplatné kontakty
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts_inactive($req) {

        $db = Zend_Registry::get('db');

        $subselect = "select id from users_vf WHERE deleted IS NOT NULL OR unsubscribed IS NOT NULL OR bad_addr IS TRUE";

        $_select = $db->select()
                ->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                    'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
                    'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

        $result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje odhlasene kontakty
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts_deregistred($req) {

        $db = Zend_Registry::get('db');

        $subselect = "select id from users_vf where unsubscribed IS NOT NULL";

        $_select = $db->select()
                ->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                    'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
                    'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

        $result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje odstranene kontakty
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts_removed($req) {

        $db = Zend_Registry::get('db');

        $subselect = "select id from users_vf where deleted IS NOT NULL AND unsubscribed IS NULL";

        $_select = $db->select()
                ->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                    'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
                    'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

        $result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

	/**
	 * Exportuje nefunkcni kontakty
	 *
	 * @param req  Zend_Controller_Request_Abstract
	 */
	public static function contacts_invalid($req) {

		$db = Zend_Registry::get('db');

		$subselect = "select id from users_vf where deleted IS NULL AND unsubscribed IS NULL AND bad_addr IS true";

		$_select = $db->select()
			->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
				'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
				'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
			->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

		$_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

		$result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
		Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
	}

    /**
     * Exportuje kontakty
     *
     * @param req  Zend_Controller_Request_Abstract
     */
    public static function contacts($req) {

        $db = Zend_Registry::get('db');

        $subselect = "select id from users_vf";

        $_select = $db->select()
                ->from('users_vv', array('id', 'name', 'surname', 'email', 'deleted', 'gender',
                    'group' => 'skupina', 'selfregistered', 'added', 'client' => 'client', 'target',
                    'reps', 'is rep' => 'is_rep', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $_select->joinLeft(array('uvf' => 'users_vf'), 'users_vv.id = uvf.id', array('uvf.send_emails', 'uvf.bad_addr', 'uvf.unsubscribed'));

        $result = Monitoringexports::joinUsersData($_select, array('id'), 'id');
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje ctenari
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     *
     */
    public static function readers($req) {

        $db = Zend_Registry::get('db');

        $bulletinId = $req->getParam('bulletinId');

        if ($bulletinId) {
            $subselect = "select id from readers_starttime_v where min_timestamp <= valid_to and email IS NOT NULL and trim(both from email)!='' and bulletin_id = '" . $bulletinId . "'";
        } else {
            $subselect = "select id from readers_starttime_v where min_timestamp <= valid_to and email IS NOT NULL and trim(both from email)!=''";
        }

        $_select = $db->select()
                        ->from('users_vv', array('user_id' => 'id', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                        ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))->order('id');

        $users = Monitoringexports::joinUsersData($_select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje ctenari po platnosti
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     *
     */
    public static function readers_late($req) {

        $db = Zend_Registry::get('db');

        $bulletinId = $req->getParam('bulletinId');

        if ($bulletinId) {
            $subselect = "select id from readers_starttime_v where min_timestamp > valid_to and email IS NOT NULL and trim(both from email)!='' and bulletin_id = '" . $bulletinId . "'";
        } else {
            $subselect = "select id from readers_starttime_v where min_timestamp > valid_to and email IS NOT NULL and trim(both from email)!=''";
        }

        $_select = $db->select()
                        ->from('users_vv', array('user_id'=>'id','issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                        ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))->order('id');

        $users = Monitoringexports::joinUsersData($_select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje seznam uzivatelu kterym byl dorucen email
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     * emailId int Email ID
     */
    public static function email_send($req) {

        $db = Zend_Registry::get('db');

        $emailId = $req->getParam('emailId');
        $bulletinId = $req->getParam('bulletinId');

        if ($emailId) {
            $subselect = "select distinct esv.user_id from emails_send_v esv where esv.email_id = '$emailId'";
        } else {
            $subselect = "select distinct esv.user_id from emails_send_v esv where esv.bulletin_id = '$bulletinId'";
        }

        if ($emailId) {
            $_select = $db->select()
                            ->from(array('u' => 'users_vv'), array('user_id'=>'id','issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                            ->joinLeft(array('esv' => 'emails_send_v'), 'esv.user_id = u.id and esv.email_id=' . $emailId, array('sent' => 'date_trunc(\'seconds\', sent)'))
                            ->where('u.id in (?)', new Zend_Db_Expr($subselect))->order('id');
        } else {
            $_select = $db->select()
                            ->from('users_vv', array('user_id'=>'id','issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                            ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))->order('id');
        }

        $users = Monitoringexports::joinUsersData($_select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje seznam uzivatelu kterym nebyl dorucen email
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     * emailId int Email ID
     *
     */
    public static function email_undelivered($req) {

        $db = Zend_Registry::get('db');

        $emailId = $req->getParam('emailId');
        $bulletinId = $req->getParam('bulletinId');

        $subselect = $db->select()->from('emails_undelivered_v', array(new Zend_Db_Expr('distinct user_id')));

        if ($bulletinId > 0) {
            $subselect->where('bulletin_id = ?', $bulletinId);
        }

        if ($emailId > 0) {
            $subselect->where('email_id = ?', $emailId);
        }

        $_select = $db->select()
                ->from('users_vv', array(
            'user_id'=>'id', 'issues read' => 'pocet_vydani_cetl',
            'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1',
            'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3',
            'last but four issue' => 'prisel_4'));

        $_select
                ->join(array('ue' => 'emails_undelivered_v'), "ue.user_id = users_vv.id and (ue.status = 'undelivered' or ue.status = 'bad_addr' or ue.status = 'personalization_err')"
                        . ($emailId ? ' and ue.email_id=' . $emailId : '')
                        . ($bulletinId ? ' and ue.bulletin_id=' . $bulletinId : ''), array(
                    'sent' => 'sent',
                    'status' => 'status',
                    'bounce' => 'bounce_status'
        ));

        $_select->joinLeft(array('e' => 'emails'), 'e.id = ue.email_id', array('email_name' => 'name'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))
                ->where('e.deleted IS NULL')->order('e.id');

        $result = Monitoringexports::joinUsersData($_select, array('user_id'));

        $bh = Ibulletin_Bounce::getBounceHandler();
        foreach ($result as $key => $val) {
            if ($val['bounce']) {
                $arr = $bh->fetch_status_messages($val['bounce']);
                $result[$key]['bounce'] = $val['bounce'] . ' - ' . $arr[1]['title'];
            }
        }

        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__);
    }

    /**
     * Exportuje seznam uzivatelu kterym si otevreli email
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     * emailId int Email ID
     */
    public static function email_read($req) {

        $db = Zend_Registry::get('db');

        $emailId = $req->getParam('emailId');
        $bulletinId = $req->getParam('bulletinId');

        $subselect = $db->select()->from(array('esv' => 'emails_send_v'), array(new Zend_Db_Expr('distinct esv.user_id')))
                ->where('esv.read_date is not null');

        if ($emailId) {
            $subselect->where('esv.email_id = ?', $emailId);
        } else {
            $subselect->where('esv.bulletin_id = ?', $bulletinId);
        }

        $select = $db->select()
                        ->from('users_vv', array('user_id'=>'id','issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                        ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))->order('id');

        $result = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje seznam uzivatelu s pristupy (RR) z emailu
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     * emailId int Email ID
     * linkId int Link ID
     */
    public static function email_response($req) {

        $db = Zend_Registry::get('db');

        $emailId = $req->getParam('emailId');
        $bulletinId = $req->getParam('bulletinId');
        $linkId = $req->getParam('linkId');

        $subselect = $db->select()->from(array('erv' => 'emails_response_v'), array(new Zend_Db_Expr('distinct erv.user_id')))
                ->where('erv.timestamp <= erv.valid_to');

        if ($linkId) {
            $subselect->where('erv.link_id = ?', $linkId);
        }

        if ($emailId) {
            $subselect->where('erv.email_id = ?', $emailId);
        } else {
            $subselect->where('erv.bulletin_id = ?', $bulletinId);
        }

        $select = $db->select()
                ->from('users_vv', array('user_id'=>'id', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $result = Monitoringexports::joinUsersData($select, array('user_id'), 'user_id');
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje seznam uzivatelu s pozdnimi pristupy (RR) z emailu
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     * emailId int Email ID
     */
    public static function email_response_late($req) {

        $db = Zend_Registry::get('db');

        $emailId = $req->getParam('emailId');
        $bulletinId = $req->getParam('bulletinId');

        $subselect = $db->select()->from(array('erv' => 'emails_response_v'), array(new Zend_Db_Expr('distinct erv.user_id')));

        if ($emailId) {
            $subselect->where('erv.user_id not in(select distinct erv.user_id from emails_response_v erv where timestamp <= valid_to and erv.email_id = ?)', $emailId);
            $subselect->where('erv.email_id = ?', $emailId);
        } else {
            $subselect->where('erv.user_id not in(select distinct erv.user_id from emails_response_v erv where timestamp <= valid_to and erv.bulletin_id = ?)', $bulletinId);
            $subselect->where('erv.bulletin_id = ?', $bulletinId);
        }

        $select = $db->select()
                ->from('users_vv', array('user_id'=>'id', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect));

        $result = Monitoringexports::joinUsersData($select, array('user_id'), 'user_id');
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje minimalni seznam uzivatelu kteri otevreli email
     *
     * @param $req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     * emailId int Email ID
     */
    public static function email_read_prob($req) {

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('db');

        $emailId = $req->getParam('emailId');
        $bulletinId = $req->getParam('bulletinId');

        if ($emailId) {
            $subselect = "select distinct user_id from (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.email_id = '$emailId'"
                    . "union select erv.user_id from emails_response_v erv where erv.email_id = '$emailId') as foo";
        } else {
            $subselect = "select distinct user_id from (select esv.user_id from emails_send_v esv where esv.read_date is not null and esv.bulletin_id = '$bulletinId'"
                    . "union select erv.user_id from emails_response_v erv where erv.bulletin_id = '$bulletinId') as foo";
        }

        $select = $db->select()
                        ->from('users_vv', array('user_id'=>'id', 'issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                        ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))->order('id');

        $result = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje seznam uzivatelu s emailem kteri otevreli clanek
     *
     * @param $req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     * pageId int Page ID
     */
    public static function article_view($req) {

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('db');

        $pageId = $req->getParam('pageId');
        $bulletinId = $req->getParam('bulletinId');

        $subselect = $db->select()->from('articles_readers_v', array('user_id'))->where('page_id = ?', $pageId);

        if ($bulletinId) {
            $subselect->where('bulletin_id = ?', $bulletinId);
        }

        $select = $db->select()
                        ->from('users_vv', array('user_id'=>'id','issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                        ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))
                        ->where('email IS NOT NULL')
                        ->order('id');

        $result = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }


	/**
	 * Exportuje seznam uzivatelu bez emailem kteri otevreli clanek
	 *
	 * @param $req  Zend_Controller_Request_Abstract
	 * V requestu by mely byt predany nasledujici parametry:
	 * bulletinId  int Bulletin ID
	 * pageId int Page ID
	 */
	public static function article_view_noemail($req) {

		/* @var $db Zend_Db_Adapter_Abstract */
		$db = Zend_Registry::get('db');

		$pageId = $req->getParam('pageId');
		$bulletinId = $req->getParam('bulletinId');

		$subselect = $db->select()->from('articles_readers_v', array('user_id'))->where('page_id = ?', $pageId);

		if ($bulletinId) {
			$subselect->where('bulletin_id = ?', $bulletinId);
		}

		$select = $db->select()
			->from('users_vv', array('user_id'=>'id','issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
			->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))
			->where("email IS NULL OR email = ''::text")
			->order('id');

		$result = Monitoringexports::joinUsersData($select, array('user_id'));
		Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
	}

    /**
     * Exportuje seznam uzivatelu kteri otevreli clanek v kategorii
     *
     * @param $req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     * pageId int Page ID
     */
    public static function category_view($req) {

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('db');

        $categoryId = $req->getParam('categoryId');
        $bulletinId = $req->getParam('bulletinId');

        $subselect = $db->select()->from('categories_readers_v', array('user_id'))->where('category_id = ?', $categoryId);

        if ($bulletinId) {
            $subselect->where('bulletin_id = ?', $bulletinId);
        }

        $select = $db->select()
                        ->from('users_vv', array('user_id'=>'id','issues read' => 'pocet_vydani_cetl', 'last issue' => 'prisel_0', 'last but one issue' => 'prisel_1', 'last but two issue' => 'prisel_2', 'last but three issue' => 'prisel_3', 'last but four issue' => 'prisel_4'))
                        ->where('users_vv.id in (?)', new Zend_Db_Expr($subselect))->order('id');

        $result = Monitoringexports::joinUsersData($select, array('user_id'));
        Ibulletin_Excel::ExportXLSX($result,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje seznam uzivatelu kteri shledli video
     *
     * @param $req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * videoId  int Video ID
     */
    public static function video_watchers($req) {

        $videoWatchers = Statistics::getVideoWatchers($req->getParam('videoId'));
        Ibulletin_Excel::ExportXLSX($videoWatchers,  __FUNCTION__, false, null, 1);
    }

    /**
     * Exportuje seznam uzivatelu s jejich odpovedmi
     *
     * @param $req Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * contentId  int Content ID
     * repreId  int ID Reprezentanta
     * fromIso string interval od
     * toIso sting interval do
     */
    public static function answers_latest($req) {

        $fromIso = $req->getParam('fromIso');
        $toIso = $req->getParam('toIso');
        $repreId = $req->getParam('repreId');
        $contentId = $req->getParam('contentId');

        $table = Statistics::getAnswersUsersTable($contentId, true, $fromIso, $toIso, $repreId);
        Ibulletin_Excel::ExportXLSX($table,  __FUNCTION__, false, array(6, 20, 20, 40, 15, 20, 10, 21, 26), 2);
    }

    /**
     * Exportuje seznam uzivatelu s jejich odpovedmi
     *
     * @param $req Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * contentId  int Content ID
     * repreId  int ID Reprezentanta
     * fromIso string interval od
     * toIso sting interval do
     */
    public static function answers_all($req) {

        $fromIso = $req->getParam('fromIso');
        $toIso = $req->getParam('toIso');
        $repreId = $req->getParam('repreId');
        $contentId = $req->getParam('contentId');

        $table = Statistics::getAnswersUsersTable($contentId, false, $fromIso, $toIso, $repreId);
        Ibulletin_Excel::ExportXLSX($table,  __FUNCTION__, false, array(6, 20, 20, 40, 15, 20, 10, 21, 26), 2);
    }

    /**
     * Exportuje seznam uzivatelu s jejich odpovedmi
     *
     * @param $req Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * contentId  int Content ID
     * repreId  int ID Reprezentanta
     * fromIso string interval od
     * toIso sting interval do
     */
    public static function video($data, $repres) {

        $config = Zend_Registry::get('config');
        $texts = Ibulletin_Texts::getSet();

        Zend_Loader::loadClass('PHPExcel');
        $excel = new PHPExcel();
        $excel->removeSheetByIndex(0);

        // Nejprve najdeme nejdesi odpovedi na otazky checkbox
        $answerLengths = array();
        foreach ($data as $rec) {
            if (!is_numeric($rec['question_id'])) {
                continue;
            }
            if ($rec['type'] == 'c') {
                $answerLengths[$rec['question_id']] = max(strlen(decbin($rec['answer'])), $answerLengths[$rec['question_id']]);
            } else {
                $answerLengths[$rec['question_id']] = 1;
            }
        }
        $answersStartPos = 8;
        $answersStarts = array(); // Pro kazdou odpoved evidujeme posledni sloupec
        $pos = $answersStartPos;
        $lastLen = 0;
        foreach ($answerLengths as $key => $len) {
            $pos += $lastLen;
            $answersStarts[$key] = $pos;
            $lastLen = $len;
        }
        //print_r($answersStarts);
        // Zapiseme radky tak, ze sledujeme a seskupujeme spravne radky, ktere souviseji
        $questionCount = 0;
        $questionCountLocal = 0;
        $xlsRow = 0;
        $lastUserId = null;
        $lastRep = null;
        foreach ($data as $rec) {
            // Pokud jde o dalsiho repa, pridame list repa
            if ($lastRep !== (int) $rec['rep']) {
                $lastRep = (int) $rec['rep'];
                if (isset($repres[$rec['rep']])) {
                    $rep = $repres[$rec['rep']];
                    $name = $rep['name'] . ' ' . $rep['surname'];
                } else {
                    $name = 'bez repa';
                }

                $excel->createSheet()->setTitle($name);
                $excel->setActiveSheetIndexByName($name);
                $xlsRow = 1;
            }


            // Pokud je to dalsi uzivatel, posuneme se o radek v XLS
            if ($lastUserId != $rec['id']) {
                $xlsRow++;
                $questionCountLocal = 0;
                $lastUserId = $rec['id'];
                $i = 0;

                $firstVisit = new Zend_Date($rec['firstvisit'], Zend_Date::ISO_8601);
                $firstVisit = $rec['firstvisit'] ? $firstVisit->toString($config->general->dateformat->long) : '';

                // Zapis dat
                $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, $rec['id']);
                $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, $rec['name']);
                $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, $rec['surname']);
                $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, $rec['group']);
                $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, empty($rec['firstvisit']) ? 'ne' : 'ano');
                $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, $firstVisit);
                $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, sprintf('%d:%02d', floor($rec['position'] / 1000 / 60), round($rec['position'] / 1000 % 60, 0))); // Kdyby to nebylo jasne, tak tohle dela cas
                $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, $rec['rating'] ? round($rec['rating'], 2) : '');
            }

            // Odpoved na otazku
            if ($rec['question_id']) {
                $questionCountLocal++;
                $questionCount = max($questionCountLocal, $questionCount);

                // Odpoved checkbox zapisujeme do vice sloupcu
                if ($rec['type'] == 'c') {
                    $answers = str_split(strrev(decbin($rec['answer'])));
                    foreach ($answers as $answer) {
                        !$answer ? $answer = '' : null;
                        $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, $answer);
                    }
                }
                // Ostatni typy odpovedi
                else {
                    $excel->getActiveSheet()->setCellValueByColumnAndRow($i++, $xlsRow, $rec['answer']);
                }
            }
        }

        // Pro kazdy list zapiseme hlavicku
        $sheets = $excel->getAllSheets();

        foreach ($sheets as $name => $sheet) {

            $i = 0;
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
            $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_id);
            $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_firstname);
            $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_lastname);
            $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_group);
            $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_come);
            $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_time);
            $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_watch_time);
            $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_rating);

            foreach ($answersStarts as $answer) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
                $sheet->getStyleByColumnAndRow($i, 1)->getFont()->setBold(true);
                $sheet->setCellValueByColumnAndRow($i++, 1, $texts->export_question . ' ' . ($answer));
            }
        }

		$writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');

		Ibulletin_Excel::createXLSXHeader(__FUNCTION__.'.xlsx');
		ob_end_clean();
		$writer->save('php://output');
		exit;

    }

     /**
     * Exportuje souhrnny seznam adresatu emailu, doplneny o nejvyznamnejsi akci kterou provedli dle poradi (sending, undelivered, reading, click)
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * emailId int Email ID
     */
     public static function users_summary_by_email($req) {

        $emailId = $req->getParam('emailId');

        $sql = "SELECT DISTINCT ON (user_id) email_id, user_id, e.name as email_name, log.date, log.action, log.url FROM ( 
                    (SELECT user_id, email_id, sent as date,'sending' as action, null as url, 1 as rank FROM emails_send_v)
                    UNION
                    (SELECT user_id, email_id, sent as date, 'undelivered', null as url, 2 as rank FROM emails_undelivered_v)
                    UNION
                    (SELECT user_id, email_id, read_date as date,'open', null as url, 3 as rank FROM emails_send_v WHERE read_date IS NOT NULL)
                    UNION
                    (SELECT user_id, email_id, timestamp as date, 'click', url, 4 as rank FROM emails_response_v)
                ) as log
               JOIN emails AS e ON e.id = log.email_id
               WHERE log.email_id = '".$emailId."'
               ORDER BY user_id, rank DESC";

        $users = Monitoringexports::joinUsersData($sql, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users,  __FUNCTION__,false, null, 1);

    }


     /**
     * Exportuje souhrnny seznam uzivatelu, kterym byl v ramci vydani odeslan email,
     * doplneny o nejvyznamnejsi akci kterou provedli dle poradi (sending, undelivered, reading, click)
     *
     * @param req  Zend_Controller_Request_Abstract
     * V requestu by mely byt predany nasledujici parametry:
     * bulletinId  int Bulletin ID
     */
     public static function users_summary_by_issue($req) {

        $bulletinId = $req->getParam('bulletinId');

        $sql = "SELECT DISTINCT ON (user_id) email_id, bulletin_id, user_id, e.name as email_name, log.date, log.action, log.url FROM ( 
                    (SELECT user_id, email_id, bulletin_id, sent as date,'sending' as action, null as url, 1 as rank FROM emails_send_v)
                    UNION
                    (SELECT user_id, email_id, bulletin_id, sent as date, 'undelivered', null as url, 2 as rank FROM emails_undelivered_v)
                    UNION
                    (SELECT user_id, email_id, bulletin_id, read_date as date,'open', null as url, 3 as rank FROM emails_send_v WHERE read_date IS NOT NULL)
                    UNION
                    (SELECT user_id, email_id, bulletin_id, timestamp as date, 'click', url, 4 as rank FROM emails_response_v)
                ) as log
               JOIN emails AS e ON e.id = log.email_id
               WHERE log.bulletin_id = '".$bulletinId."'
               ORDER BY user_id, rank DESC";

        $users = Monitoringexports::joinUsersData($sql, array('user_id'));
        Ibulletin_Excel::ExportXLSX($users,  __FUNCTION__,false, null, 1);

    }

}
