<?php
/**
 * iBulletin - ExternalLinks.php
 * Exception class 
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */

class ExternalLinksException extends Exception {}


/**
 * Trida pracuje s externimy linky z DB
 *
 * @author Tomas Ovesny, <tomas.ovesny@pearshealthcyber.com>
 */
class ExternalLinks
{

    /**
     * blind construct
     */
    public function __construct() { }
    
    /**
     * Seznam linků
     * 
     * @return Zend_Db_Select
     */
    public static function getLinksQuery(){     
       
        $db = Zend_Registry::get('db');
        
        $select = $db->select()->from('links',array('id','name','foreign_url','used_times','deleted'))
                ->where('foreign_url IS NOT NULL');
      		
        return $select;        
    }
    
    /**
     * Nacte linky nebo jeden konkretni dle zadaneho ID    
     * 
     * @param int ID konkretniho linku
     * @return array pole linku
     */
    public function getLinks($id = 0)
    {     
        $id = intval($id); 
        $db = Zend_Registry::get('db');
        
        // pokud je definovano konkretni ID, tak vloz do SQL
        $_andQuery = '';
        if ($id > 0) {
            $_andQuery = 'AND id = '.$id;
        }
        
        // vrat asociativni pole s vysledkama
        $query = 'SELECT id, name, foreign_url, used_times
                      FROM links
                      WHERE foreign_url IS NOT NULL
                     '.$_andQuery.'
                     ORDER BY id DESC';
        $result = $db->fetchAssoc($query);
		
        return $result;        
    }
    /**
     * prida link do tbl links
     * osetri chybejici http://
     *
     * @param array data z $_POST  
     * @return boolean zda se podarilo ulozit data   
     */ 
    public function saveAddLink($data) {
        
		$db = Zend_Registry::get('db');
	    // neobsahuje-li http na zacatku, tak pridej
	    if (!empty($data['foreign_url']) && !preg_match('/^https?:\/\//', $data['foreign_url'])) {
	    	$data['foreign_url'] = 'http://'.$data['foreign_url'];
	    }
		$aff = $db->insert('links', $data);
		return $aff;
	
    }    
    
    /**
     * Ulozi editovany link
     * osetri chybejici http://
     *
     * @param array data z $_POST  
     * @return boolean zda se podarilo ulozit data   
     */ 
     public function saveLink($data) {
        
     	$db = Zend_Registry::get('db');
     	// neobsahuje-li http na zacatku, tak pridej
     	if (!empty($data['foreign_url']) && !preg_match('/^https?:\/\//', $data['foreign_url'])) {
     		$data['foreign_url'] = 'http://'.$data['foreign_url'];
     	}
     	$id = $data['id'];
     	unset($data['id']);
     	$aff = $db->update('links', $data, sprintf('id=%d', $id));
     	return $aff;
        
     }

    /**
     * nahradi v obsahu nalezene http(s):// linky za tag %%link#id%%
     * v pripade neexistujiciho linku takovy link vytvori v DB
     *
     * @param string content
     * @return string zmeneny content
     */
    public function updateContentLinks($content) {

        // prepare variables
        $matches = $replacement = $links = $link_exact_patterns = array();

        // search pattern for links in content, usually href="http://..."
        $pattern = '/(href[\s]*=[\s]*["\']?)(https?:\/\/[^\s\>"\']*)["\']?/i'; // [^\s<>"]+ part matches any non-whitespace, non quote, non anglebracket character
        if (preg_match_all($pattern, $content, $matches) !== FALSE) {

            $match_url = array();
            $search = array();

            foreach ($matches[2] as $k => $i) {
                // remove everything after %%, including %%
                if (false !== ($pos = strpos($i, '%%'))) $i = substr($i, 0, $pos);
                // search pattern is 'href="...url...'
                $search[$k] = $matches[1][$k].$i;
                // stripped url, decode html entities because of TIDY
                $match_url[$k] = html_entity_decode($i, null , "UTF-8");
            }

            $link_exact_patterns = array_merge($link_exact_patterns, $search);
            $links = array_merge($links, $match_url);
        }

        // no links found, exit
        if (!$links) {
            return $content;
        }

        // find existing links in DB
        $db = Zend_Registry::get('db');
        $q = $db->select()->from('links', array('id','foreign_url'))->where('foreign_url IN (?)', $links);
        $found_links = $q->query()->fetchAll();

        // Pripravime si tagy
        $cfg = Zend_Registry::get('config');
        $tags = $cfg->mailer->tags;
        $tagOpen = $tags->open;
        $tagClose = $tags->close;
        $tagSeparator = $tags->separator;

        // find corresponding link id or insert a new link
        foreach ($links as $k => $l) {
            $l_id = null;
            foreach ($found_links as $fl) {
                if ($l == $fl['foreign_url']) {
                    $l_id = $fl['id'];
                    break;
                }
            }
            // link id not found
            if (!$l_id) {
                // set name and foreign_url for new link
                $data = array('name' => $l, 'foreign_url' => $l, 'used_times' => 0);
                try {
                    // insert
                    $aff = $this->saveAddLink($data);
                    if($aff == 1) {
                        $l_id= $db->lastInsertId('links','id');
                    } else {
                        Phc_ErrorLog::warning('ExternalLinks::updateContentLinks()',
                            "Nepodarilo se vytvorit link v tabulce links pro link: '$l'. Link byl preskocen.");
                        // equivalent replacement = no change on this link
                        $replacement[] = $l;
                        continue;
                    }
                } catch (Exception $e) {
                    Phc_ErrorLog::warning('ExternalLinks::updateContentLinks()',
                        "Nepodarilo se vytvorit link pro nalezenou url: '$l'. Link byl preskocen.");
                    // equivalent replacement = no change on this link
                    $replacement[] = $l;
                    continue;
                }
            }
            // replace with 'href="%%link#id%%'
            $replacement[$k] = $matches[1][$k].$tagOpen.$tags->link.$tagSeparator.$l_id.$tagClose;
        }
        // Nahradime v textu mailu nalezene urls s jejich %%link#id%% tagami
        return str_replace($link_exact_patterns, $replacement, $content);

    }


}