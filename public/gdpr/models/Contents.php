<?php
/**
 * iBulletin - Contents.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

class Contents_Exception extends Exception {}
//class Exception extends Exception {}

/**
 * Trida poskytujici funkce spojene s contenty.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Contents
{
    /**
     * Vrati zaznamy jednotlivych contentu podle typu contentu.
     * 
     * @param string/array Jmeno tridy contentu
     * @param array        Pole s jednotlivymi polozkami ORDER i se smerem razeni
     * @return array       Pole poli radkuu z content, datumy jsou v Zend_Date a objekt
     *                     je deserializovany v prvku 'object'
     */
    public static function getList($class_name = null, $order = array('created DESC')){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('content')
            ->order($order);

        if(is_array($class_name)){
            foreach($class_name as $val){
                $sel->orwhere('class_name = ?', $val);
            }
        }
        elseif($class_name){
            $sel->orwhere('class_name = ?', $class_name);
        }
        
        
        $rows = $db->fetchAll($sel);
        $assoc = array();
        if(is_array($rows)){
            // pripravime (deserializace) kazdy radek contentu
            foreach($rows as $key => $row){
                $assoc[$row['id']] = self::prepareContent($row);
            }
        }
        
        return $assoc;
    }

    public static function getQuery($classes = array()) {

        $db = Zend_Registry::get('db');

        // Pripojime pages, bulletiny, autory ke contentu
        $pagesSel = '(SELECT cp.content_id, max(cp.page_id) AS page_id FROM pages p
                    JOIN content_pages cp ON p.id = cp.page_id GROUP BY cp.content_id)';
        
        //  $bulletinsSel = '(SELECT bp.page_id, max(bp.bulletin_id)  AS bulletin_id FROM bulletins b
        //              JOIN bulletins_pages bp ON b.id = bp.bulletin_id GROUP BY bp.page_id)';

        $bulletinsSel = '(SELECT DISTINCT ON (bp.page_id) bp.page_id, max(b.valid_from), b.id AS bulletin_id FROM bulletins_pages AS bp '
                . 'LEFT JOIN bulletins AS b ON bp.bulletin_id = b.id WHERE b.deleted IS NULL GROUP BY page_id,b.id,b.valid_from '
                . 'ORDER BY bp.page_id, b.valid_from DESC)';

        $sel = $db->select()->from(array('c' => 'content'), array('*',new Zend_Db_Expr('(CASE WHEN (SELECT id FROM bulletins WHERE deleted IS NULL ORDER BY valid_from DESC LIMIT 1) = bulletin_id THEN true END) AS in_latest_bulletin')))
            ->joinLeft(array('cp' => new Zend_Db_Expr($pagesSel)), 'c.id = cp.content_id', array())
            ->joinLeft(array('p' => 'pages'), 'p.id = cp.page_id', array(
                'page_id' => 'p.id', 'page_name' => 'p.name'))
            ->joinLeft(array('bp' => new Zend_Db_Expr($bulletinsSel)), 'p.id = bp.page_id', array())
            ->joinLeft(array('b' => 'bulletins'), 'b.id = bp.bulletin_id', array(
                'bulletin_id' => 'b.id', 'bulletin_name' => 'b.name'))
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
                        )')), 'cat.page_id = p.id', array('category_id' => 'cat.id', 'category_name' => 'cat.name'))
            ->joinLeft(array('a' => 'authors'), 'a.id = c.author_id', array(
            'author_name' => 'a.name'));
            //->order('c.created DESC');

        /*foreach($classes as $val){
            $sel->orwhere('class_name = ?', $val);
        } */
        if ($classes) {
            $sel->where('class_name IN (?)' , (array)$classes);
        }

        return $sel;

    }
	
	/**
	 * rozsiri pole contentu o deserializovany objekt a datumove typy 
	 * @param object $row
	 * @return 
	 */
	public static function prepareContent($row) {
			
			// Z datumuu udelame Zend_Date objekt
            $row['created'] = new Zend_Date($row['created'], Zend_Date::ISO_8601);
            $row['changed'] = new Zend_Date($row['changed'], Zend_Date::ISO_8601);
            
			// Deserializujeme objekt            	
			if(!empty($row['serialized_object'])){
				$row['object'] = unserialize(stripslashes($row['serialized_object']));
				
				// Ibulletin_Content_Abstract
				if ($row['object'] instanceof Ibulletin_Content_Abstract) {                
								// Nastavime authora a created podle DB do objektu
            		$row['object']->created = $row['created'];
            		$authors_model = new Authors();
					$author = $authors_model->getAuthor($row['author_id']);
					$row['object']->setAuthor($author['name']);	
									
				} else {
					Phc_ErrorLog::warning('Contents::prepareContent()', 
                	'Byl deserializovan objekt typu `'.$row['class_name'].'`');
				}
            } 
			
			return $row;
	}
    
    /**
     * Vrati zaznam jednoho contentu. V pripade nenalezeni contentu vraci null.
     * 
     * @param int       ID contentu
     * @return array    Pole radku z content, datumy jsou v Zend_Date a objekt
     *                  je deserializovany v prvku 'object'
     */
    public static function get($id){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('content')
            ->where(sprintf('id = %d', $id))
            ->limit(1);
            
        $rows = $db->fetchAll($sel);
        
        if(!empty($rows)){
            $row = $rows[0];
			$row = self::prepareContent($rows[0]);				        
        }
        else{
            $row = null; 
        }
        
        return $row;
    }
    
    /**
     * Edituje, nebo prida zaznam do contents. Serializuje objekt i priulozi ID contentu do
     * objektu noveho zaznamu.
     * 
     * @param int       ID contentu
     * @return obj      Nekdy upraveny objekt contentu 
     */
    public static function edit($id = null, $obj, $author_id = null){
        $db = Zend_Registry::get('db');
        
        $class_name = get_class($obj);
        
        if (!$obj instanceof Ibulletin_Content_Abstract) {
		 throw new Contents_Exception('Objekt pro serializaci do contentu s id `'.$id.'`neni potomek tridy Ibulletin_Content_Abstract');
		}
        $data = array(
            'changed' => new Zend_Db_Expr('current_timestamp'),
            'serialized_object' => addslashes(serialize($obj)),
            'class_name' => $class_name,
            );
            
        if($author_id !== null){
            $data['author_id'] = $author_id;
        }
        
        // Ulozime name contentu
        if(isset($obj->name)){
            $data['name'] = $obj->name;
        }

	    // Ulozime entry code
	    if(isset($obj->entrycode)){
		    $data['entrycode'] = $obj->entrycode;
	    }

        if($id === null){
            $db->insert('content', $data);
            $id = $db->lastInsertId('content', 'id');
            
            $obj->id = $id;
            $data['serialized_object'] = addslashes(serialize($obj));
        }
        
        $db->update('content', $data, sprintf('id = %d', $id));
        
        return $obj;
    }
    
    
    /**
     * Vrati URL k obrazku pro dany content. Pokud neexistuje, pripravi jej.
     * Rozmery a nazev obrazku jsou v $config->general->images->$imgType->...
     * 
     * Hledani vhodneho obrazku je nasledujici:
     * 1. Pokud existuje $config->general->images->$imgType->name v adresari contentu, je pouzit
     * 2. Pokud existuje hlavni obrazek contentu v adresari contentu
     *   $config->general->images->main->name je z neho vytvoren soubor podle bodu 1 a ten je puzit
     * 3. Pokud existuje v adresari contentu jpg obrazek, je pouzit prvni podle abecedy - vytvori
     *    se piktogram podle bodu 1. a ten se vrati
     * 4. Pokud existuje obrazek id_ketegorie.jpg v adresari $config->categories_admin->img_basepath, 
     *    je pouzit pro vytvoreni piktogramu dle bodu 1. a je vracen
     * 
     * @param int $contentId    ID contentu pro ktery ma byt obrazek ziskan
     * @param string $imgType   Jaky obrazek se ma nagenerovat - urcuji parametry v configu
     *                          $config->general->images->$imgType
     * @param int $categoryId   ID kategorie ve ktere dany content je umisten, pokud neni zadano, 
     *                          obrazek kategorie se nepouzije ke generovani
     * @return string           URL k obrazku vhodne pro pouziti ve view skriptu
     */
    public static function getImage($contentId, $imgType, $categoryId = null)
    {
        $config = Zend_Registry::get('config');
        
        $pictName = $config->general->images->$imgType->name;
		
		$urlHlpr = new Zend_View_Helper_BaseUrl();	
						
        
        $path = $config->content_article->basepath.'/'.$contentId.'/';
        
        // Pokud soubor jiz existuje, jen vratime jeho url
        if(file_exists($path.'/'.$pictName)){
            $file = $pictName;
        }
        
        // Hledame obrazek, z ktereho udelame miniaturu
        // Nejprve zkusime, jestli existuje hlavni obrazek pro content $config->general->images->main->name
        elseif(file_exists($path.$config->general->images->main->name)){
            $fileToMinify = $path.$config->general->images->main->name;
        }
        else{
            $picturesA = array();
            if(file_exists($path) && ($dirh = opendir($path))){
                while($dir_element = readdir($dirh)){
                    if(preg_match('/\.jpg$/i', $dir_element)){
                        $picturesA[] = $dir_element;
                    }
                }
                unset($dir_element);
                closedir($dirh);
            }

            if(!empty($picturesA)){
                // Seradime pole, abychom brali abecedne prvni obrazek, ne nahodny
                sort($picturesA);
                reset($picturesA);
                $fileToMinify = $path.current($picturesA);
                
                unset($picturesA);
            }
            // Zkusime pro vytvoreni piktogramu pouzit obrazek pouzity jako obrazek kategorie
            elseif($categoryId){
                $catImage = $config->categories_admin->img_basepath.'/'.$categoryId;
                if(file_exists($catImage.'.jpg')){
                    $fileToMinify = $catImage.'.jpg';
                }
                elseif(file_exists($catImage.'.JPG')){
                    $fileToMinify = $catImage.'.JPG';
                }
            }
        }
        
        // Pokud byl nalezen soubor ze ktereho se ma udelat miniatura, vytvorime miniaturu
        if(!empty($fileToMinify)){
            // Pokud je treba vytvorime adresar pro soubory contentu
            if(!file_exists($path)){
                Utils::mkdir($path);
            }
            
            // Vytvorime miniaturu
            $sizeX = $config->general->images->$imgType->size->x;
            $sizeY = $config->general->images->$imgType->size->y;
            require_once 'library/phpthumb/ThumbLib.inc.php';
            try{
                $thumb = PhpThumbFactory::create($fileToMinify);
                $thumb->adaptiveResize($sizeX, $sizeY);
                $thumb->save($path.'/'.$pictName);
            }
            catch(Exception $e){
                // Neco se nepovedlo pri zmensovani, zalogujeme
                Phc_ErrorLog::error('Contents::getImage()','Nepodarilo se vytvorit miniaturu. '.
                    "Puvodni vyjimka:\n $e");
            }
            
            if(file_exists($path.'/'.$pictName)){
                $file = $pictName;
            }
            else{
                // Zrejme se nepodarilo vytvorit miniaturu, zalogujeme chybu
                Phc_ErrorLog::error('Contents::getImage()', 'Nepodarilo se vytvorit miniaturu pro soubor "'.
                    $fileToMinify.'" k ulozeni do "'.$path.'/'.$pictName.'".');
            }
        }

        
        // Pokud se podarilo najit nebo vytvorit miniaturu, vratime jeji URL
        if(!empty($file)){
            //$url = $urlHlpr->url(array(), 'default', true).'/'.$path.'/'.$file; // Zlobi s PDF exportem - potrebuje cestu ve filesystemu
            $url = $urlHlpr->baseUrl($path.'/'.$file);
            $url = Zend_Filter::filterStatic($url, 'NormPath');    
            return $url;
        }
        else{
            return null;
        }
    }
    
    
    /**
     * Wrapper na getImage(), ktery najde vhodny obrazek podle link_id z tabulky links.
     * Pokusi se najit odpovidajici page a k in prvni content.
     * 
     * @param $linkId int       ID linku z tabulky links
     * @param $imgType string   Jaky obrazek se ma nagenerovat - urcuji parametry v configu
     *                          $config->general->images->$imgType
     * @return string           URL k obrazku vhodne pro pouziti ve view skriptu, nebo null, 
     *                          pokud neni content nebo obrazek k dispozici 
     */
    public static function getImageByLink($linkId, $imgType)
    {
        $db = Zend_Registry::get('db');
        
        $q = "
        SELECT cp.content_id
        FROM content_pages cp
        JOIN (
            SELECT cp.page_id, min(\"position\") AS \"position\"
            FROM links l
            JOIN content_pages cp ON cp.page_id = l.page_id
            WHERE l.id = ".(int)$linkId."
            GROUP BY cp.page_id
            ) cp1 ON cp1.page_id = cp.page_id AND cp1.\"position\" = cp.\"position\"
        LIMIT 1
        ";
        
        $contentId = $db->fetchOne($q);
        
        if(empty($contentId)){
            // Neexistuje vhodny content, vracime null
            return null;
        }
        else{
            return self::getImage($contentId, $imgType);
        }
        
    }
    
    /**
     * Vrati zaznamy dle nazvu contentu. V pripade nenalezeni contentu vraci null.
     * 
     * @param string      name contentu
     * @return array    Pole radku z content
     */
    public static function getItemByName($name){
        $db = Zend_Registry::get('db');
        
        $sel = new Zend_Db_Select($db);
        $sel->from('content')
            ->where('name = ?', $name);
        $rows = $db->fetchAll($sel);
        
        if(!empty($rows)){
            return $rows;		        
        }
        else{
            return null; 
        }
        
    }
    
    /**
     * Vrátí pole s typy contentů
     * @return array
     */
    public static function getTypes() {
        $db = Zend_Registry::get('db');
        $sql = "SELECT substring(class_name from 'Ibulletin_Content_(.*)') AS types FROM content GROUP BY class_name ORDER BY types";
        
        $types = $db->fetchAll($sql);
        
        return $types;
    }
    
    /**
     * Smaze content podle ID
     * 
     * @param   int     ID contentu 
     * @return  bool    vysledek smazani
     */
    public static function delete($id){

        /* @var $db Zend_Db_Adapter_Abstract */
        $db =  Zend_Registry::get('db');
        
        $data = array('deleted' => new Zend_Db_Expr('current_timestamp'));

        $where = $db->quoteInto('id = ?', $id);
        
        $affected = $db->update('content', $data, $where);
        
        if($affected){
            return true;
        }
        else{
            return false;
        }
    }
    
    /**
     * Obnovi content podle ID
     * 
     * @param   int     ID contentu 
     * @return  bool    vysledek smazani
     */
    public static function restore($id){
        
        /* @var $db Zend_Db_Adapter_Abstract */
        $db =  Zend_Registry::get('db');
        
        $data = array('deleted' => null);

        $where = $db->quoteInto('id = ?', $id);
        
        $affected = $db->update('content', $data, $where);
        
        if($affected){
            return true;
        }
        else{
            return false;
        }
    }

    
    /**
     * Ziska posledni Bulletin do ktereho je content zarazen
     * @param int $contentId
     * @return array
     */
    public static function getBulletin($contentId) {
        
        $db = Zend_Registry::get('db');

        
        $pagesSel = '(SELECT cp.content_id, max(cp.page_id) AS page_id FROM pages p
                    JOIN content_pages cp ON p.id = cp.page_id GROUP BY cp.content_id)';
        $bulletinsSel = '(SELECT bp.page_id, max(bp.bulletin_id) AS bulletin_id FROM bulletins b
                    JOIN bulletins_pages bp ON b.id = bp.bulletin_id GROUP BY bp.page_id)';

       
        $sel =   $db->select()->from(array('c' => 'content'), array('b.*','page_layout_name'=>'p.layout_name'))
                ->joinLeft(array('cp' => new Zend_Db_Expr($pagesSel)), 'c.id = cp.content_id', array())
                ->joinLeft(array('p' => 'pages'), 'p.id = cp.page_id',
                    array('page_id' => 'p.id', 'page_name' => 'p.name'))
                ->joinLeft(array('bp' => new Zend_Db_Expr($bulletinsSel)), 'p.id = bp.page_id', array())
                ->joinLeft(array('b' => 'bulletins'), 'b.id = bp.bulletin_id',
                    array('bulletin_id' => 'b.id', 'bulletin_name' => 'b.name'))
                ->where('c.id = ?',$contentId)
                ->where('b.id IS NOT NULL')
                ->order('b.valid_from DESC');

        $row = $db->fetchRow($sel);

       return $row;
    }
       
}