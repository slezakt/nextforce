
<?php
/**
 * Trida pro obsluhu slidů
 *
 * @author Ondra Bláha
 */
class Slides
{

	/**
	 *	Vrati seznam slidu rozsireny o otazky a odpovedi.
	 *	@param int id contentu
	 *	@return array slidy daneho contentu
	 */
	public static function getSlidesData($content_id)
	{

		$db = Zend_Registry::get('db');   

		if(!is_numeric($content_id)){
			return FALSE;
		}		

		$q = new Zend_Db_Select($db);
        $q->from(array('s' => 'slides'), 
				 array('slide_id' => 'id', 'slide_num', 'name', 'mandatory'))
          ->joinLeft(array('q' => 'questions'), 'q.slide_id = s.id',
		  		 array('question_id' => 'id', 'question_num', 'question_type' => 'type', 'question_text' => 'text'))
		  ->joinLeft(array('a' => 'answers'), 'a.question_id = q.id',
		  		 array('answer_id' => 'id', 'answer_num', 'answer_text' => 'text'))                   
          ->where('s.content_id = ?', $content_id)		  
		  ->order(array('s.slide_num', 'q.question_num', 'a.answer_num'));
		
		$rows = $db->fetchAll($q);

		$data = array('content_id' => $content_id);
		$prev = array('slide_id' => NULL, 'question_id' => NULL, 'answer_id' => NULL);
        
		$sk=$qk=$ak=0;
        
		foreach($rows as $row){

			if ($row['slide_id'] != $prev['slide_id']) {
				$item = array();
				$item['id'] = $row['slide_id'];
				$item['num'] = $row['slide_num'];
				$item['name'] = $row['name'];
				$item['mandatory'] = $row['mandatory'];								
				$sk++; $qk=$ak=0;
				$data[$sk] = $item; 
				
			}
			
			if ($row['question_id'] != $prev['question_id'] && $row['question_id'] !== NULL) {
				$item = array();				
				$item['id'] = $row['question_id'];
				$item['num'] = $row['question_num'];
				$item['type'] = $row['question_type'];
				$item['text'] = $row['question_text'];							 
				$qk++; $ak=0;				
				$data[$sk]['questions'][$qk] = $item; 
				
			}
			
			if ($row['answer_id'] != $prev['answer_id'] && $row['answer_id']  !== NULL) {
				$item = array();
				$item['id'] = $row['answer_id'];
				$item['num'] = $row['answer_num'];
				$item['text'] = $row['answer_text'];
				$ak++; 
				$data[$sk]['questions'][$qk]['answers'][$ak] = $item;
				
			}			
			//echo 'ak: '.$ak;echo '<br/>';
			$prev['slide_id']= $row['slide_id'];
			$prev['question_id']= $row['question_id'];
			$prev['answer_id']= $row['answer_id'];
		}

		return $data;
		
	}
    
    
    /**
     * Vrati seznam dostupnych nahledu slidu
     * @param array $c radek z tabulky content
     * @return  array seznam nahledu
     */
    public static function getSlidesPreviews($c) {

        if (!$c) {
            return array();
        }
        
        // Pripravime seznam dostupnych nahledu slajdu
        $path = $c['object']->getBasepath().$c['object']->slidePreviewsPath;

        $slidePreviews = Array();
        $m = array();
        if(is_readable($path)){
            $dir = dir($path);
            while(false !== ($entry = $dir->read())){
                // Staci, aby slide zacinal cislem v jakekoli podobe
                if(preg_match('/^([0-9]+).*\.jpg$/', $entry, $m)){
                    $slidePreviews[(int)$m[1]] = $path.'/'.$entry;

                }
            }
            $dir->close();
        }
        
        return $slidePreviews;
        
    }
    
    
     /**
     * Vrati cestu k nahledum slidu
     * @param array $c radek z tabulky content 
     * @return string cesta
     */
    public static function getSlidesPreviewsPath($c) {

        if (!$c) {
            return null;
        }

        return $c['object']->getBasepath().$c['object']->slidePreviewsPath;
        
    }
    
    
    
}
        