<?php

/**
 * Description of Templates
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Templates {
    
    /**
     * Vrati pole obsahujici seznam landing pages.
     * 
     * @return array Seznam landing pages z configu.
     */
    public static function getLandingPages(){
        $config = Zend_Registry::get('config');
        return (array)$config->general->getCommaSeparated('landing_pages_list');
    }
    
    /**
     * Vrati pole obsahujici seznam landing pages a doplnujicich zpusobu vstupu.
     * 
     * @return array Seznam doplnujicich zpusobu vstupu.
     */
    public static function getAllEntryMetohods(){
        $config = Zend_Registry::get('config');
        $landingPages = $config->general->getCommaSeparated('landing_pages_list');
        $entryMethods = $config->general->getCommaSeparated('additional_entry_methods_list');
        return array_merge((array)$landingPages,(array)$entryMethods);
    }
    
    /**
     * Vrati pole obsahujici seznam vsech aktualne povolenych vstupnich metod.
     * 
     * @return array Seznam aktualne povolenych zpusobu vstupu.
     */
    public static function getAllowedEntryMetohods(){
        $config = Zend_Registry::get('config');
        $landingPages = $config->general->getCommaSeparated('landing_pages_list');
        $entryMethods = $config->general->getCommaSeparated('additional_entry_methods_list');
        $forbidden = $config->general->getCommaSeparated('forbidden_came_in_pages');
        
        
        return array_diff(array_merge((array)$landingPages,(array)$entryMethods), (array)$forbidden);
    }

    /**
     * Vrati pole obsahujici predane vstupni metody jako klice 
     * a jejich texty jako hodnoty.
     * 
     * @param  array    Pole vstupnich metod (napr z getAdditionalEntryMetohods nebo getLandingPages).
     * @return array    Asociativni pole obsahujici texty k jednotlivym predanym vstupnim metodam.
     */
    public static function getComeInPagesTexts($entryMethods) {
        $texts = Ibulletin_Texts::getSet('admin.templates');
        // Pridame ke kazde vstupni metode text
        $entryMethodsTexts = array();
        foreach($entryMethods as $method){
            $entryMethodsTexts[$method] = $texts->{$method};
        }
        
        return $entryMethodsTexts;
    }
    

    /**
     * Vrati pole sablon z .editable_views.cfg
     * @param string $typ Typ šablony (landingpages, layouts,...)
     * @return array seznam sablon
     */
    public static function getEditableViewScripts($typ) {

        $view_path = "views/scripts/_editable_views.cfg";
        
        if(!is_file($view_path)) {
            throw new Exception('Can not open file: '.$view_path);
        }
        
        $fcont = file($view_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $tpls = array();
        $add = false;
        $i = 1;
        foreach ($fcont as $line) {
            $line = trim($line);
            if (preg_match('/^\(--.+--\)$/', $line)) {
                $mkey = strtolower(preg_replace(array('/^\(--/', '/--\)$/'), "", $line));
                $add = true;
            } else {
                if ($add & !preg_match('/^#.*/', $line)) {
                    if (is_file($line)) {
                        //jestlize existuje backup file lze revertovat
                        if (is_file(preg_replace('/\.phtml$/', '.bckp', $line))) {
                            $revert = "1";
                        } else {
                            $revert = "0";
                        }
                        $finfo = pathinfo($line);
                        //jestlize je nazev souboru index, prevezme nazev z adresare
                        if ($finfo['filename'] == 'index') {
                            $name = ucwords(basename($finfo['dirname']));
                        } else {
                            $name = ucwords($finfo['filename']);
                        }
                        $tpls[$mkey][] = array('id' => $i++, 'name' => $name, 'path' => $line,'revert'=>$revert);
                    } elseif (is_dir($line)) {
                        $dir = new DirectoryIterator($line);
                        foreach ($dir as $fileinfo) {
                            if (!$fileinfo->isDot() && !preg_match('/\.bckp/', $fileinfo->getFilename())) {
                                $path = $fileinfo->getPathname();
                                // strip extension, remove underscores, uppercase words
                                $name = ucwords(preg_replace(array('/^(.*)\..*$/', '/_/'), array('\1', ' '), $fileinfo->getBasename()));
                                //jestlize existuje backup file lze revertovat
                                if (is_file(preg_replace('/\.phtml$/', '.bckp', $path))) {
                                    $revert = "1";
                                } else {
                                    $revert = "0";
                                }
                                $tpls[$mkey][] = array('id' => $i++, 'name' => $name, 'path' => $path, 'revert' => $revert);
                            }
                        }
                    }
                }
            }
        }
        if (array_key_exists($typ, $tpls)) {
            return $tpls[$typ];
        } else {
            return array();
        }
       
    }

    public static function getAllViewScripts() {

        $parent_dir = "views/scripts/";
        $it = new RecursiveDirectoryIterator($parent_dir);
        $result = array();
        $i = 1;
        foreach(new RecursiveIteratorIterator($it) as $file) {
            $path = $file->__toString();
            if (!preg_match('/\.phtml$/', $path)) continue;

            //jestlize existuje backup file lze revertovat
            if (is_file(preg_replace('/\.phtml$/', '.bckp', $path))) {
                $revert = "1";
            } else {
                $revert = "0";
            }

            $result[] = array('id' => $i++, 'name' => substr($path,strlen($parent_dir)), 'path' => $path, 'revert' => $revert);

        }

        return $result;
    }

    /**
     * Obnovi bckp soubor na vychozi hodnotu
     * pokud backup soubor existuje, smaze puvodni backup
     * @param string $path cesta k souboru
     */
    public static function revertBckpFile($path) {

        $bckp_path = preg_replace('/\.'.preg_quote(pathinfo($path, PATHINFO_EXTENSION)).'$/', '.bckp', $path);

        if (!is_file($bckp_path)) {
            return false;
        }

        if (copy($bckp_path,$path)) {
            //odstranime zalohu
            return unlink($bckp_path);
        } else {
            return false;
        }

    }
    
    /**
     * Vrátí seznam šablon prezentace
     * @return array
     */
    public static function getAllPresentations() {

        $config = Zend_Registry::get('config');
        $template_dir = $config->paths->indetail_template;
        $i = 1;
        $result = array();
        foreach (new DirectoryIterator($template_dir) as $file) {
            if (!$file->isDot() && $file->isDir()) {
                if (is_file($file->getPathname() . '/index.html')) {
                    $result[] = array('id' => $i, 'name' => $file->__toString(), 'path' => $file->getPathname().'/index.html','resources'=> $file->getPathname());
                    $i++;
                }
                
            }
        }

        return $result;

    }


    /**
     * vraci seznam emailu i s url pro odkaz na nahled emailu
     *
     * @param $templateName string nazev (soubor) sablony
     * @param $deleted bool vracet i smazane emaily
     * @return array|boolean pole id => ['url', 'name']
     *
     */
    public static function getTemplateEmails($templateName,$deleted = true) {

        $res = array();
        $db = Zend_Registry::get('db');
        
        $sel = $db->select()->from('emails', array('id','name'))->where('template = ?', $templateName);
        
        if (!$deleted) {
            $sel->where('deleted IS NULL');
        }
        
        if ($rows = $sel->query()->fetchAll()) {
            foreach ($rows as $row) {
                $res[$row['id']] = $row['name'];
            }
        }

        return $res;
    }

    /**
     * parsuje soubor sablony a vraci nactenou strukturu podle nalezenych tagu
     * pokud sou template_data, vraci nahrazeny obsah
     *
     * @param $templateFile string soubor sablony (relativne umisten)
     * @param $templateData array|NULL pole klicu hodnot pro nahrazeni v sablone
     *
     * @return array|string|boolean pokud je templateData NULL vraci pole tagu ze sablony, jinak vraci naparsovanou sablonu
     *                            vraci FALSE v pripade chyby
     */
    public static function parseEmailTemplateFile($templateFile, $templateData = null) {

        $struct = array();

        if (!file_exists($templateFile)) {
            return false;
        }

        $content = file_get_contents($templateFile);

        // bere se nejmin greedy spusobem 2 az 3 parametrovy tag typu %%a#b#c%%
        // druhy parameter nesmi zacinat cislem (duvod je kolize s tagem link, ostrejsi podminka na label)
        $pattern = '/%%(text|textarea|img|link)#([^0-9#%][^#%]*)#?([^#%]*?)%%/';


        // TODO: slo by pouzit PREG_SET_ORDER misto subcyklu; nevyhoda indexovane vs pojmenovane klice
        if (preg_match_all($pattern, $content, $match) !== false) {
            for ($k = 0; $k < count($match[0]); $k++) {

                //zjistime zda je element v content groupe (prvek pro moznost volitelnych elementu), paklize ano pridame nazev do pole struct
                $content_group = null;
                $gpatt = '/%%grpstart#([^#%]*)%%(.*?)' . preg_quote($match[0][$k]) . '(.*?)%%grpend#\1%%/si';
                if (preg_match($gpatt, $content, $gmatch)) {
                    $content_group = $gmatch[1];
                    if (!$match[3][$k]) {
                        //doplnime doplnenou groupu do contentu
                        $content = str_replace($match[0][$k],preg_replace('/%%$/','#'.preg_quote($gmatch[1]).'%%',$match[0][$k]),$content);
                        $match[3][$k] = $gmatch[1];

                    }
                }


                //pred pridani elementu do pole kontrolujeme jestli neobsahuje stejny,
                //existuje preskocime ho, ve formulari se objevi pouze jeden, ale hodnota se nahradi u vsech
                $is_in_struct = false;

                foreach ($struct as $s) {
                    if ($s['pattern'] == $match[0][$k]) {
                        $is_in_struct = true;
                        break;
                    }
                }

                if (!$is_in_struct) {
                    $struct[] = array(
                        'pattern' => $match[0][$k],
                        'type' => $match[1][$k],
                        'label' => $match[2][$k],
                        'group' => $match[3][$k],
                        'content_group' => $content_group,
                        'key' => str_replace('-', '_', Utils::slugify(implode('-', array($match[1][$k], $match[2][$k], $match[3][$k]))))
                    );
                }
            }
        }

        // vracime soubor z nahrazenyma hodnotama
        if (!is_null($templateData)) {

            $replacements = $patterns = array();

            // nahradime znacky za data
            foreach ($struct as $v) {

                $replacement = $pattern = '';

                $val = $templateData[$v['key']];

                //paklize je element v groupe a ma data, groupa v obsahu zustane, odstranime jen tagy
                if($v['content_group'] && !empty($val)) {
                    $content = str_replace(array('%%grpstart#'.$v['content_group'].'%%','%%grpend#'.$v['content_group'].'%%'), '', $content);
                }

                // note: if group is specified, set different pattern including this group
                $pattern = '/%%'.preg_quote($v['type']).'#'.preg_quote($v['label']).
                    ($v['group'] ? '#'.preg_quote($v['group']) : '') .'%%/';

                switch ($v['type']) {
                    case 'text':
                    case 'textarea':
                        $replacement = $val;
                        break;
                    case 'link':
                        if ($val == '0') break;
                        $replacement = (preg_match('/^r_([0-9]+)$/', $val, $m)) ?
                            '%%resource#'.$m[1].'%%' : '%%link#' . $val . '%%';
                        break;
                    case 'img':
                        if ($val == '0') break;
                        $replacement = '%%imgmime#' . $val . '%%';
                        break;
                }

                $patterns[] = $pattern;
                $replacements[] = $replacement;

            }
            // a este v mailu nahradime vyskyt %%static%% za %%imgmime%% aby sa zahrnuli obrazky zo sablony
            $patterns[] = '/%%static%%(\/?)(.*?)\"/';
            $replacements[] = '%%imgmime#template-files/$2%%"';

            //odstranime zbyle groupy, ktere neobsahuji zadne data
            $patterns[] = '/%%grpstart#([^#%]*)%%(.*?)%%grpend#\1%%/si';
            $replacements[] = '';

            $content = preg_replace($patterns, $replacements, $content);

            return $content;

        }

        return $struct;
    }

}
