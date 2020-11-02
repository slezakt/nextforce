<?php
/**
 * iBulletin - Boxes.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}
class Boxes_Edit_Box_Unique_Name_Violation_Exception extends Exception {}
class Boxes_Edit_Version_Exception extends Exception {}
class Boxes_Edit_Version_Not_Zend_Date_Exception extends Exception {}
class Boxes_Version_Exception extends Exception {}

/**
 * Trida poskytujici funkce spojene s dynamickymi boxy.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Boxes
{
    /**
     * Provede upravu, nebo pridani noveho zaznamu do tabulky boxes.
     *
     * Pokud je zadan u nejakeho parametru null, nebude tento zmenen,
     * pro nastaveni daneho parametru na NULL v DB, pouzijte new Zend_Db_Expr('null'),
     * pro nektere atributy ale neni null povolen.
     *
     * @param  int      Id z boxes pro editaci, nebo null pro pridani noveho zaznamu.
     * @param  string   Nazev iboxu, pozor normalizuje se na mala pismena!!
     * @param  string   Popis iboxu.
     * @param  bool     Je tento box smazany?
     * @return bool/int Pokud editujeme vraci bool povedlo/nepovedlo, pro vkladani int
     *                  id noveho zaznamu/false nepodarilo se.
     */
    public static function editBox($id = null, $name = null, $description = null, $deleted = null)
    {
        $db =  Zend_Registry::get('db');

        $data = array();
        if($id instanceof Zend_Db_Expr && $id == 'null'){
            $data['id'] = null;
        }
        elseif($id !== null){
            $data['id'] = $id;
        }
        if($name instanceof Zend_Db_Expr && $name == 'null'){
            $data['name'] = null;
        }
        elseif($name !== null){
            $data['name'] = strtolower($name);
        }
        if($description instanceof Zend_Db_Expr && $description == 'null'){
            $data['description'] = null;
        }
        elseif($description !== null){
            $data['description'] = $description;
        }
        if($deleted !== null){
            $data['deleted'] = $deleted;
        }

        // Vkladame novy
        if($id === null){
            try{
                $db->insert('boxes', $data);
            }
            catch(Zend_Db_Statement_Exception $e){
                    // Pokud je to problem existujiciho jmena, vyhodime spravnou vyjimku
                    if(stripos($e->getMessage(), 'Unique violation') !== false){
                        throw new Boxes_Edit_Box_Unique_Name_Violation_Exception("Box se zadanym name='$name' jiz existuje.");
                    }

                    Phc_ErrorLog::warning('Boxes::editBox()', $e);

                    return false;
            }

            $id = $db->lastInsertId('boxes', 'id');

            return $id;
        }

        // Editujeme
        else{
            try{
                $affected = $db->update('boxes', $data, 'id='.(int)$id);
            }
            catch(Zend_Db_Statement_Exception $e){
                Phc_ErrorLog::warning('Boxes::editBox()', $e);
                return false;
            }

            if($affected){
                return true;
            }
            else{
                return false;
            }
        }
    }

    /**
     * Smaze zaznam z boxes oznacenim zaznamu za deleted.
     *
     * @param  int      Id z boxes ke smazani.
     * @return bool     Povedlo se smazat?
     */
    public static function deleteBox($id)
    {
        return self::editBox($id, new Zend_Db_Expr('null'), null, true);
    }

    /**
     * Provede upravu, nebo pridani noveho zaznamu do tabulky boxes_versions.
     *
     * Pokud je zadan u nejakeho parametru null, nebude tento zmenen,
     * pro nastaveni daneho parametru na NULL v DB, pouzijte new Zend_Db_Expr('null'),
     * pro nektere atributy ale neni null povolen.
     *
     * @param  int        Id z boxes_values pro editaci, nebo null pro pridani noveho zaznamu.
     * @param  int        Id z boxes - v pripade vkladani nove verze je povinne.
     * @param  Zend_Date  Datum pocatku platnosti dane verze iBoxu
     * @param  int        Id z bulletins - verze plati prave od zacatku platnosti daneno cisla
     * @param  int        Id z invitation_waves - verze plati prave po dobu platnosti dane vlny.
     * @param string      Obsah (HTML) boxu v teto verzi.
     * @param string      Popis verze.
     * @param bool        Je content samazany? null = beze zmeny
     * @param bool        Je zakazano provadet TIDY na tomto boxu?
     * @param bool        Je zakazano provadet TIDY jen pri tomto konkretnim ulozeni? (nemeni ulozenou hodnotu tidy_off)
     * @param string      Jmeno souboru template
     * @param int         ID contentu
     * @return array      Data tak jak byla ulozena (jen data, ktera byla zmenena).
     *                    (pri vytvareni noveho zaznamu vraci jen ID)
     */
    public static function editVersion($id = null, $box_id = null, $valid_from = null,
                                        $bulletin_id = null,  $invitation_wave_id = null,
                                        $content = null, $description = null, $deleted = null, $template = null,
                                        $tidyOff = null, $tidyOnceOff = false, $contentId = null)
    {
        $db =  Zend_Registry::get('db');

        if($id === null && $box_id === null){
            throw new Boxes_Edit_Version_Exception('Neni mozne vytvorit novou verzi bez specifikace boxu (box_id) do ktereho parti.');
        }

        $data = array();

        if($id instanceof Zend_Db_Expr && $id == 'null'){
            $data['id'] = null;
        }
        elseif($id !== null){
            $data['id'] = (int)$id;
        }
        if($id === null){
            // Prednastavime content na prazdny retezec
            $data['content'] = '';
        }

        if($box_id !== null){
            $data['box_id'] = (int)$box_id;
        }

        if($valid_from instanceof Zend_Db_Expr && $valid_from == 'null'){
            $data['valid_from'] = null;
        }
        elseif($valid_from !== null){
            if(!($valid_from instanceof Zend_Date)){
                throw new Boxes_Edit_Version_Not_Zend_Date_Exception('Datum valid from musi byt instance Zend_Date.');
            }
            $data['valid_from'] = $valid_from->get(Zend_Date::ISO_8601);
        }
        else{
            $data['valid_from'] = null;
        }

        if($bulletin_id instanceof Zend_Db_Expr && $bulletin_id == 'null'){
            $data['bulletin_id'] = null;
        }
        elseif($bulletin_id !== null){
            $data['bulletin_id'] = (int)$bulletin_id;
        }

        if($invitation_wave_id instanceof Zend_Db_Expr && $invitation_wave_id == 'null'){
            $data['invitation_wave_id'] = null;
        }
        elseif($invitation_wave_id !== null){
            $data['invitation_wave_id'] = (int)$invitation_wave_id;
        }

        if($content instanceof Zend_Db_Expr && $content == 'null'){
            $data['content'] = null;
        }
        elseif($content !== null){
            if(!$tidyOff && !$tidyOnceOff){
                // Provedeme TIDY na HTML
                $marks = new Ibulletin_Marks();
                $content = $marks->tidyHtml($content);
            }
            $data['content'] = $content;
        }

        if($description instanceof Zend_Db_Expr && $description == 'null'){
            $data['description'] = null;
        }
        elseif($description !== null){
            $data['description'] = $description;
        }
        if($deleted !== null){
            $data['deleted'] = $deleted;
        }
        if($template !== null){
            $data['template'] = $template;
        }
        if ($tidyOff !== null) {
            $data['tidy_off'] = $tidyOff;
        }
        
        if ($contentId != null) {
            $data['content_id'] = $contentId;
        } else  {
            $data['content_id'] = null;
        }

        // Vkladame novy
        if($id === null){
            try{
                $db->insert('boxes_versions', $data);
            }
            catch(Zend_Db_Statement_Exception $e){
                Phc_ErrorLog::warning('Boxes::editVersion()', $e);
                return false;
            }

            $id = $db->lastInsertId('boxes_versions', 'id');

            return $id;
        }

        // Editujeme
        else{
            try{
                $affected = $db->update('boxes_versions', $data, 'id='.(int)$id);
            }
            catch(Zend_Db_Statement_Exception $e){
                Phc_ErrorLog::warning('Boxes::editVersion()', $e);
                return false;
            }

            if($affected){
                return array('content' => $content);
            }
            else{
                return false;
            }
        }
    }

    /**
     * Smaze zaznam z boxes_versions oznacenim zaznamu za deleted.
     *
     * @param  int      Id z boxes_versions ke smazani.
     * @return bool     Povedlo se smazat?
     */
    public static function deleteVersion($id)
    {
        return self::editVersion($id, null, null, null, null, null, null, true);
    }

    /**
     * Vrati radek boxu podle ID.
     *
     * @param  int    ID boxu.
     * @return array  Radek z DB pozadovaneho boxu.
     */
    public static function getBox($id)
    {
        $db =  Zend_Registry::get('db');

        $select = new Zend_Db_Select($db);
        $select->from('boxes')
            ->where('id=:id');
        $boxA = $db->fetchAll($select, array('id' => $id));

        if(empty($boxA)){
            return false;
        }

        $box = current($boxA);
        return $box;
    }

/**
     * Vrati seznam boxuu.
     *
     * @param string Order by klauzule.
     * @return array  Pole poli vsech boxuu.
     */
    public static function getBoxes($order = null)
    {
        $db =  Zend_Registry::get('db');

        $select = new Zend_Db_Select($db);
        $select->from('boxes')
               ->where('deleted = false');

        if($order === null){
            $select->order('name');
        }
        else{
            $select->order($order);
        }

        $boxA = $db->fetchAll($select);

        if(empty($boxA)){
            return array();
        }

        return $boxA;
    }

    /**
     * Vytvori kopii aktualni verze.
     *
     * @param  int        Id z boxes_versions k naklonovani.
     * @return int/false  Id nove verze, nebo false pri neuspechu.
     */
    public static function cloneVersion($id)
    {
        $db =  Zend_Registry::get('db');
        $config = Zend_Registry::get('config');

        $version = self::getVersion($id);
        unset($version['id']);

        // Vlozime jako novy zaznam.
        try{
            $db->insert('boxes_versions', $version);
        }
        catch(Zend_Db_Statement_Exception $e){
            Phc_ErrorLog::warning('Boxes::cloneVersion()', $e);
            return false;
        }

        $old_id = $id;
        $id = $db->lastInsertId('boxes_versions', 'id');

        // Okopirujeme vsechny soubory statickeho obsahu dane verze
        $old_path = $config->boxes->static_basepath.'/'.$version['box_id'].'/'.$old_id.'/';
        $new_path = $config->boxes->static_basepath.'/'.$version['box_id'].'/'.$id.'/';
        if(file_exists($old_path)){
            // Vytvorime novy adresar
            if(!file_exists($new_path)){
                Utils::mkdir($new_path);
                if(!is_writable($new_path)){
                    Phc_ErrorLog::warning('Boxes::cloneVersion()',
                        'Nepodarilo se vytvorit ci zapisovat adresar pro staticka data "'.$new_path.'".');
                    return false;
                }
            }
            // Kopirujeme
            $d = dir($old_path);
            while($entry = $d->read()) {
                if(is_dir($old_path.$entry)){
                    continue;
                }
                if(!copy($old_path.$entry, $new_path.$entry)){
                    Phc_ErrorLog::warning('Boxes::cloneVersion()',
                        'Nepodarilo se okopirovat soubor "'.$entry.'" z "'.$old_path.'" do "'.$new_path.'".');
                }
            }
            $d->close();
        }

        return $id;
    }
    
    /**
     * Seznam verzí dynamických okének pro Datagrid
     * 
     * @param type $id ID boxu
     * @return type Zend_Db_Select
     */
    public static function getBoxesVersionQuery($id) {
        
        $db = Zend_Registry::get('db');
        
        $where = $db->quoteInto("bv.box_id = ?",$id);
                
        $select = $db->select()->from(array("bv" => "boxes_versions"))
                ->where($where)
                ->where("bv.deleted = false");

        return $select;
    }
    
     /**
    * Seznam boxů pro grid 
    *  
    * @return Zend_Db_Select
    */
     public static function getBoxesQuery() {

        $db = Zend_Registry::get('db');
       
        $subselect = $db->select()                
                ->from(array("bv" => "boxes_versions"),
                array(new Zend_Db_Expr("DISTINCT ON (bv.box_id) b.name"),"b.description","bv.id","bv.box_id",
                new Zend_Db_Expr("COALESCE(bv.valid_from,bul.valid_from,iw.start) AS datum")))
                ->joinLeft(array("bul" => "bulletins"),"bul.id = bv.bulletin_id",null)
                ->joinLeft(array("iw" => "invitation_waves"),"iw.id = bv.invitation_wave_id",null)
                ->joinLeft(array("b" => "boxes"),"b.id = bv.box_id",null)
                ->where("bv.deleted = false")
                ->where("b.deleted = false")
                ->order(array("bv.box_id","datum DESC"));
        $select = $db->select()->from(array("bx" => $subselect));      
        return $select;
    }

    /**
     * Vrati radek verze podle ID.
     *
     * @param  int    ID verze.
     * @return array  Radek z DB pozadovane verze.
     */
    public static function getVersion($id)
    {
        $db =  Zend_Registry::get('db');

        $select = new Zend_Db_Select($db);
        $select->from('boxes_versions')
            ->where('id=:id');
        $versionA = $db->fetchAll($select, array('id' => $id));

        if(empty($versionA)){
            return false;
        }

        $version = current($versionA);
        return $version;
    }

/**
     * Vrati seznam verzi navic s polem, ktere rika, jaka je platnost
     * po zohledneni vsech zpusobuu urceni platnosti.
     *
     * @param  int    Id boxu pro ktery chceme verze vypsat.
     * @param string Order by klauzule.
     * @return array  Vsechny verze.
     */
    public static function getVersions($box_id = null, $order = null)
    {
        $db =  Zend_Registry::get('db');


        $subselect = new Zend_Db_Select($db);
        $subselect->from(array('b' => 'boxes'),
                        new Zend_Db_Expr('coalesce(bv.valid_from, bul.valid_from, iw."start")
                                        AS final_valid_from'))
            ->join(array('bv' => 'boxes_versions'), 'bv.box_id=b.id')
            ->joinleft(array('bul' => 'bulletins'), 'bul.id=bv.bulletin_id', array('bul_valid_from' => 'valid_from'))
            ->joinleft(array('iw' => 'invitation_waves'), 'iw.id=bv.invitation_wave_id', array('iw_valid_from' => 'start'))
            ->where('b.deleted = false')
            ->where('bv.deleted = false');

        if($box_id !== null){
            $subselect->where('box_id='.(int)$box_id);
        }

        $select = new Zend_Db_Select($db);
        $select->from(new Zend_Db_Expr('('.$subselect->__toString().')'),
                      new Zend_Db_Expr('*'));
        
        if($order === null){
            $select->order('final_valid_from DESC', 'id');
        }
        else{
            $select->order($order);
        }

        $versionA = $db->fetchAll($select);

        if(empty($versionA)){
            return array();
        }

        return $versionA;
    }

    /**
     * Vrati content boxu pro aktualni datum (s ohledem na timeshift),
     * podle nazvu boxu.
     *
     * Pokud ma box nastaveny template neni vypsano HTML boxu, ale obsah template, 
     * pokud je nastaveno content_id, je renderovan content s position=100. Content_id ma
     * vyssi prioritu nez template.
     *
     * @param  string  Nazev boxu (atribut name z tabuky boxes), je normalizovan na mala pismena
     * @param  bool    Maji byt vyhazovany vyjimky vpripade potizi? Default false - jen logujeme.
     * @param bool Vratí pole s html, id, verzi boxiku a platnost
     * @param int ID bulletin pro ktery se ma boxik renderovat
     * @return string|array  HTML obsahu boxu s jiz prelozenymi znackami.| pole s contentem a informacemi o boxiku 
     *
     * @throws  Boxes_Version_Exception, code 1 pokud nebyla nalezena verze ke zobrazeni.
     */
    public static function getContent($name, $throwExceptions = false, $additionInfo = false, $bulletinId = null)
    {
        $db =  Zend_Registry::get('db');
        $session = Zend_Registry::get('session');
        $config = Zend_Registry::get('config');
        
        if (!$bulletinId) {
            $bulletinId = Bulletins::getActualBulletinId();
        }

        // Aktualni timestamp
        $time = new Zend_Date();

        // Zjistime, jestli neprobehl timeshift, pokud ano, upravime podle nej
        // timestamp pro porovnani platnosti
        if(!empty($session->timeshift)){
            $time = $time->addDayOfYear((int)$session->timeshift);
        }
        
        $fdate = $db->quote($time->get(Zend_Date::ISO_8601));

        $select = new Zend_Db_Select($db);
        $select->from(array('b'=>'boxes'))
            ->join(array('bv' => 'boxes_versions'), 'bv.box_id=b.id')
            ->joinleft(array('bul' => 'bulletins'), 'bul.id=bv.bulletin_id',null)
            ->joinleft(array('iw' => 'invitation_waves'), 'iw.id=bv.invitation_wave_id',null)
            ->where('b.name=?',strtolower($name))
            ->where('b.deleted = false')
            ->where('bv.deleted = false')
            ->where('((bv.bulletin_id = ?)'
                    . ' OR (iw.bulletin_id = ? AND "start" <= '.$fdate.' AND "end" >= '.$fdate.')'
                    . ' OR (iw.bulletin_id = ? AND "start" <= '.$fdate.' AND "end" IS NULL)'
                    . ' OR (iw.bulletin_id = ? AND "end" >= '.$fdate.' AND start IS NULL)'
                    . ' OR (bv.valid_from <= '.$fdate.'))',$bulletinId)
            ->order(array('iw.id', 'bv.bulletin_id', 'bv.valid_from DESC'))
            ->limit(1);

        $version = $db->fetchRow($select);
        
        if(empty($version)){
            if($throwExceptions){
                throw new Boxes_Version_Exception("Nebyla nalezena potrebna verze boxu. box: '$name', Select: '$select'", 1);
            }
            else{
                Phc_ErrorLog::notice('Boxes::getContent()', 'Pouzit neexistujici box, nebo box bez platne verze. Jmeno boxu:"'.$name.'". Select: '.$select);
                return '';
            }
        }

        $box_id = $version['box_id'];
        $version_id = $version['id'];

        // Rozhodneme, jestli zobrazujeme HTML boxu, content_id nebo renderujeme template, ktery je pro tento box nastaven
        // CONTENT ID
        if(!empty($version['content_id'])){
            $frontController = Zend_Controller_Front::getInstance();
            Zend_Loader::loadFile('BulletinController.php', 'controllers', true);
            $bulletinController = new BulletinController(
                $frontController->getRequest(), 
                $frontController->getResponse(), 
                array('noViewRenderer' => true)
            );
            
            $contentA = Contents::get($version['content_id']);
            
            if($contentA === null){
                // Nenalezen content, zalogujeme
                $content = '';
                Phc_ErrorLog::warning('Boxes::getContent()', 'Nepodarilo se nacist zadany content dle content_id dynamickeho boxu. '.
                    "box_name: $name, box_id: $box_id, version_id: $version_id, content_id: ".
                    $version['content_id']."."
                    );
            }
            else{
                $content = $bulletinController->renderContentHtml(100, $contentA['object'], null, null, true);
            }
        }
        // Template
        elseif(!empty($version['template']) && is_readable($config->paths->boxes_templates.$version['template'])){
            $a = file($config->paths->boxes_templates.$version['template']);
            $content = join("\r\n", $a);
        }
        elseif(!empty($version['template'])){
            $content = '';
            Phc_ErrorLog::warning('Boxes::getContent()', 'Nepodarilo se nacist zadany template dynamickeho boxu. '.
                "box_name: $name, box_id: $box_id, version_id: $version_id, template: ".
                $config->paths->boxes_templates.$version['template']."."
                );
        }
        // HTML boxu
        else{
            $content = $version['content'];
        }

        // Prelozime znacky boxu
        $urlHlpr = new Zend_View_Helper_Url();
        $baseUrl = $urlHlpr->url(array(),'default',true);
		$path = $baseUrl . $config->boxes->static_basepath.'/'.$box_id.'/'.$version_id.'/';			
		$content = Ibulletin_Marks::translate($content, $path);
        
        if ($additionInfo) {
            return array('content'=>$content,'boxId'=>$box_id,'versionId'=>$version_id,'validFrom'=>$version['valid_from']);
        }

        return $content;
    }
    
    
    /**
     * Testuje jestli existuje boxik
     * @param string $name nazev boxu
     * @return bool 
     */
    public static function isBox($name) {
        if (!$name) {
            return false;
        }
        
        try{
            $box = self::getContent($name, true);
            return true;
        }
        catch(Boxes_Version_Exception $e){
            return false;
        }      
    }
}
