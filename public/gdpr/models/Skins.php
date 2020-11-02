<?php

/**
 * Trida obsahujici metody pouzivane pro praci se skinama
 *
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */
class Skins {
	
	/**
	 * vraci cestu k adresari skinu
	 * 
	 * @return string
	 */
	public static function getBasePath() {
		$config = Zend_Registry::get('config');
		$bp = trim($config->htmlhead->skinDir,'\\/');
		return $bp ? $bp : 'pub/skins';
	}

	/**
	 * vraci TRUE v pripade, ze existuje adresar se skinem
	 * 
	 * @param string $id
	 * @return boolean
	 */
	public static function isValid($id) {
		return $id && file_exists(self::getBasePath() .'/' .$id) 
				   && is_dir(self::getBasePath() .'/' .$id);
	}

    /**
     * vraci URL k aktualnimu skinu
     */
    public static function getActualSkinUrl() {
        $baseUrlHlpr = new Zend_View_Helper_BaseUrl();
        return $baseUrlHlpr->baseUrl(self::getActualSkinPath());
    }

    /**
     * vraci cestu ve filesystemu k aktualnimu skinu
     */
    public static function getActualSkinPath() {
        $config = Zend_Registry::get('config');
        $skin = $config->general->skin ? $config->general->skin : 'default';
        return self::getBasePath() . '/' . $skin;
    }
	
	/**
	 * vraci seznam skinu (name, active)
	 * 
	 * @return array
	 */
	public static function findAll() {
		$config = Zend_Registry::get('config');
		$res = array();
		foreach (self::findPairs() as $v) {
			$item = array('name' => $v, 'active' => $config->general->skin == $v);
			// make default skin as first item
			$v == 'default' ? array_unshift($res, $item) : $res[] = $item;
		}
		return $res;
	}
	
	public static function findPairs() {
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Skins::getBasePath()),
				RecursiveIteratorIterator::SELF_FIRST);
		// first level directories
		$it->setMaxDepth(0);
		$res = array();
		if ($it) {
			while ($it->valid()) {
				if (!$it->isDot() && $it->isDir()) {
					$path = trim($it->getSubPathName(), '\\/');
					$res[$path] = $path;
				}
				$it->next();
			}
			// eliminate duplicates
			$res = array_unique($res);
		}
		return $res;
	}
	
	/**
	 * smaze skin
	 * 
	 * @param string id skinu
	 * @return boolean
	 */
	public static function delete($id) {
		$db =  Zend_Registry::get('db');
	
		// set all bulletins skin field to NULL		
		try {
			$db->update('bulletins',
					array('skin' => null),
					$db->quoteInto('skin = ?', $id)
			);			
		} catch (Exception $e) {
			Phc_ErrorLog::warning('skins', $e->getMessage());			
			return false;
		}
		
		return true;
	}	
}
