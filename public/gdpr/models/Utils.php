<?php
/**
 * Trida obsahujici obvykle staticke metody, ktere slouzi jako pomucky
 * a nikam jinam se zrovna nehodi je umistit.
 *
 * @author Petr Skoda
 */
class Utils
{
    /**
     * Primitive POST data parser (not url decoded POST data)
     * 
     * Gets a member of post data in the same way as the Zend_Controller_Request_Http::getPost(),
     * but the data is not urlDecoded.
     * 
     * If no $key is passed, returns the entire $_POST-like array.
     *
     * @param string $key
     * @param mixed $default Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    public static function getRawPost($key = null, $default = null)
    {
        // Get all POST data
        $postStr = trim(file_get_contents('php://input'));
        
        // Get key/val pairs
        $postChunks = explode('&', $postStr);
        $post = array();
        foreach($postChunks as $chunk){
            $chunkA = explode('=', $chunk);
            @$post[$chunkA[0]] = $chunkA[1];
        }
        
        if (null === $key) {
            return $post;
        }

        return (isset($post[$key])) ? $post[$key] : $default;
    }
    
    
    /**
     * Vrati UTC offset v minutach z ISO 8601 timestamp.
     * Vraci null pro nevalidni ISO timestampy podle Ibulletin_Validators_TimestampIsoFilter.
     *
     * @param string    ISO 8601 timestamp
     * @return int      UTC offset v minutach, pro vadnou timestamp vraci null, pro timestamp bez
     *                  offsetu vraci 0
     */
    public static function getUtcOffset($timestamp)
    {
        $isoTimestValid = new Ibulletin_Validators_TimestampIsoFilter;
        if(!$isoTimestValid->isValid($timestamp)){
            return null;
        }

        $isoTimestRegexp = '/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/';

        $m = array();
        preg_match($isoTimestRegexp, $timestamp, $m);
        //$offset = $m[21]; // 21 je skupina obsahujici celou cast s offsetem

        //Phc_ErrorLog::notice('timestamp parsed', print_r($m, true));
        //return 0;

        // Zjistime, ktery format je pouzit na offset a spocitame offset v minutach
        if(strtolower(trim($m[21])) == 'z'){ // ZULU
            $offsetMinutes = 0;
        }
        elseif(!empty($m[21])){
            $offsetMinutes = 0;
            if(!empty($m[23])){ // hodiny
                $offsetMinutes += $m[23] * 60;
            }
            if(!empty($m[22])){ // znamenko
                $offsetMinutes = ($m[22]."1") * $offsetMinutes;
            }
            if(!empty($m[24])){ // minuty
                $offsetMinutes += $m[24];
            }
        }
        else{
            $offsetMinutes = 0;
        }

        return $offsetMinutes;
    }

    /**
     * Transforms ISO timestamp to the one of preconfigured timestamp formats.
     * 
     * Formats are defined in $config->general->dateformat->[format],
     * default formats are:
     *      short = "d. M. YYYY"
     *      medium = "d. M. YYYY H:mm"
     *      long = "d. M. YYYY H:mm:ss"
     *      preciseIso = "YYYY-MM-ddTHH:mm:ss.SZZZZ"
     * 
     * If wrong timestamp is given, error is logged and original string is returned.
     * If empty timestamp given, empty string is returned.
     * 
     * DANGER! Can be slow if called for too many records (thousands) because 
     * of slow Zend_Date implementation. 
     * 
     * TODO: Prepare similar method and configuration for time ranges formatting.
     * 
     * @param $timestamp    string  ISO 8601 timestamp
     * @param $formatName   string  Name of the format defined in config (short, medium, long...)
     * @return  string      Timestamp formatted to required format.  
     */
    public static function formatIsoTs($timestamp, $formatName)
    {
        if(empty($timestamp)){
            return '';
        }
        
        $config = Zend_Registry::get('config');
        $locale = Zend_Registry::get('locale');
        
        if(!isset($config->general->dateformat->$formatName)){
            // Problem with formatName, log and return original timestamp
            Phc_ErrorLog::warning('Utils::formatIsoTs()', 
                "Not existing format name '$formatName' from config->general->dateformat used.\n".new Exception);
            
            return $timestamp;
        }
        
        $date = new Zend_Date();
        $date->setLocale($locale);
        try{
            $date->setIso($timestamp);
            return $date->toString($config->general->dateformat->$formatName);
        }
        catch(Exception $e){
            // Problem with format, log and return original timestamp
            Phc_ErrorLog::notice('Utils::formatIsoTs()', 
                "Cannot format ISO timestamp '$timestamp'. Original exception:\n".$e);
            
            return $timestamp;
        }
    }


    /**
     * detects content mime-type
     * wrapper around php mime_content_type() function. uses OS 'file -bi' command alternatively
     *
     * @param $file
     * @return string
     */
    public static function getMimeType($file) {

        $info = @getimagesize($file); // @ - files smaller than 12 bytes causes read error
        if (isset($info['mime'])) {
            return $info['mime'];

        } elseif (extension_loaded('fileinfo')) {
            $type = preg_replace('#[\s;].*$#', '', finfo_file(finfo_open(FILEINFO_MIME), $file));

        } elseif (function_exists('mime_content_type')) {
            $type = mime_content_type($file);
        }

        return isset($type) && preg_match('#^\S+/\S+$#', $type) ? $type : 'application/octet-stream';

    }


    /**
     * vsprintf() function with named parameters
     *
     * @url http://www.php.net/manual/de/function.vsprintf.php#83883
     * @param $format
     * @param array $data
     * @return string
     */

    public static function vnsprintf( $format, array $data)
    {
        preg_match_all( '/ (?<!%) % ( (?: [[:alpha:]_-][[:alnum:]_-]* | ([-+])? [0-9]+ (?(2) (?:\.[0-9]+)? | \.[0-9]+ ) ) ) \$ [-+]? \'? .? -? [0-9]* (\.[0-9]+)? \w/x', $format, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $offset = 0;
        $keys = array_keys($data);
        foreach ( $match as &$value )
        {
            if ( ( $key = array_search( $value[1][0], $keys) ) !== false || ( is_numeric( $value[1][0]) && ( $key = array_search( (int)$value[1][0], $keys) ) !== false ) ) {
                $len = strlen( $value[1][0]);
                $format = substr_replace( $format, 1 + $key, $offset + $value[1][1], $len);
                $offset -= $len - strlen( $key);
            }
        }
        return vsprintf( $format, $data);
    }

  	/**
  	 * slugifies text, usable for generating clean urls
  	 * 
  	 * @param string $text text to be slugified
  	 * @param string|array $replace array of characters to be replaced with space before slugifying
  	 * @param string $delimiter default delimiter is minus
  	 * @param number $maxLength maximum length of result string, defaults to 100
  	 * @return string
  	 */
    public static function slugify($text, $replace=array(), $delimiter='-', $maxLength=100) {
    	
    	$text = trim($text);
    	
    	// replace valid characters for spaces
    	if( !empty($replace) ) {
    		$text = str_replace((array)$replace, ' ', $text);
    	}
    	// transliterate
    	/*if (function_exists('iconv')) {
    		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    	}*/
    	$clean = self::translit($text);
		// clean and replace with delimiter
    	$clean = preg_replace("%[^-/+|\w ]%", '', $clean);
    	$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
    	// cut length and trim
    	$clean = strtolower(trim(substr($clean, 0, $maxLength), $delimiter));
    
    	return $clean;
    }

    /**
     * flattens array and implodes its keys with delimiter
     * 
     * @param array $array 
     * @param string  $delim delimiter for imploded key
     * @param array $prefix internal traversal keys holder     
     * @return array flattened array
     */
    public static function multi_implode($array, $delim = '.', $prefix = array()) {
    	
    	$res = array();
    	foreach ($array as $key => $value) {
    		if (is_array($value)) {	 
    			$res += self::multi_implode($value, $delim, array_merge($prefix, (array)$key));
    		} else {
    			$res[implode($delim, array_merge($prefix, (array)$key))] = $value;
    		}
    	}
    	return $res;
    }

    /**
     * expand 1-dimensional array into multidimensional based on its key with delimiter used as a separator
     * for nested array structure
     *
     * @param array $array
     * @param string  $delim delimiter for imploded key
     * @return array expanded array
     */
    public static function multi_explode($array, $delim = '.') {
        $result = array();
        foreach ($array as $key=>$val) {
            $r = & $result;
            foreach (explode($delim, $key) as $key) {
                if (!isset($r[$key])) {
                    $r[$key] = array();
                }
                $r = & $r[$key];
            }
            $r = $val;
        }
        return $result;
    }

    /**
     * array key-value search
     *
     * @param $array
     * @param $key
     * @param $value
     * @return array
     */
    public static function array_kv_search($array, $key, $value) {
        $results = array();

        if (is_array($array))
        {
            if (isset($array[$key]) && $array[$key] == $value)
                $results[] = $array;

            foreach ($array as $subarray)
                $results = array_merge($results, self::array_kv_search($subarray, $key, $value));
        }

        return $results;
    }
    
    /**
     * Vrati aplication z konstranty pro CLI z pomocneho souboru
     * @return string application url
     */
    public static function applicationUrl() {

        if (Zend_Uri::check(APPLICATION_URL)) {

            return APPLICATION_URL;
            
        } else {

            $configAplicationUrl = APPLICATION_PATH . '/generated_cfg/.application_url';

            if (file_exists($configAplicationUrl)) {
                return file_get_contents($configAplicationUrl);
            } else {
                return "";
            }
        }
    }

    /**
     * Maps UTF-8 ASCII-based latin characters, puntuation, symbols and number forms to ASCII
     *
     * Any characters or symbols that can not be translated will be removed.
     *
     * This function is most useful for situation that only allows ASCII, such
     * as in URLs.
     *
     * Translates elements form the following unicode blocks:
     *
     *  - Latin-1 Supplement
     *  - Latin Extended-A
     *  - Latin Extended-B
     *  - IPA Extensions
     *  - Latin Extended Additional
     *  - General Punctuation
     *  - Letterlike symbols
     *  - Number Forms
     *
     * @internal
     *
     * @param  string $string  The string to convert
     * @return string  The input string in pure ASCII
     */
    static public function translit($string)
    {    	
    	if (!self::detect($string)) {
    		return $string;
    	}    	
    	$string = strtr($string, self::$utf8_to_ascii);    	
    	//$string = preg_replace('#[^\x00-\x7F]#', '', $string);
    	return $string;
    }
        
    /**
     * Detects if a UTF-8 string contains any non-ASCII characters
     *
     * @param  string $string  The string to check
     * @return boolean  If the string contains any non-ASCII characters
     */
    static private function detect($string)
    {
    	return (boolean) preg_match('#[^\x00-\x7F]#', $string);
    }
    
    /**
     * A mapping of all ASCII-based latin characters, puntuation, symbols and number forms to ASCII.
     *
     * Includes elements form the following unicode blocks:
     *
     *  - Latin-1 Supplement
     *  - Latin Extended-A
     *  - Latin Extended-B
     *  - IPA Extensions
     *  - Latin Extended Additional
     *  - General Punctuation
     *  - Letterlike symbols
     *  - Number Forms
     *  - Cyrilic
     *
     * @var array
     */
    private static  $utf8_to_ascii = array(
		// Latin-1 Supplement
		'©' => '(c)', '«' => '<<',  '®' => '(R)', '»' => '>>',  '¼' => '1/4',
		'½' => '1/2', '¾' => '3/4', 'À' => 'A',   'Á' => 'A',   'Â' => 'A',
		'Ã' => 'A',   'Ä' => 'A',   'Å' => 'A',   'Æ' => 'AE',  'Ç' => 'C',
		'È' => 'E',   'É' => 'E',   'Ê' => 'E',   'Ë' => 'E',   'Ì' => 'I',
		'Í' => 'I',   'Î' => 'I',   'Ï' => 'I',   'Ñ' => 'N',   'Ò' => 'O',
		'Ó' => 'O',   'Ô' => 'O',   'Õ' => 'O',   'Ö' => 'O',   'Ø' => 'O',
		'Ù' => 'U',   'Ú' => 'U',   'Û' => 'U',   'Ü' => 'U',   'Ý' => 'Y',
		'à' => 'a',   'á' => 'a',   'â' => 'a',   'ã' => 'a',   'ä' => 'a',
		'å' => 'a',   'æ' => 'ae',  'ç' => 'c',   'è' => 'e',   'é' => 'e',
		'ê' => 'e',   'ë' => 'e',   'ì' => 'i',   'í' => 'i',   'î' => 'i',
		'ï' => 'i',   'ñ' => 'n',   'ò' => 'o',   'ó' => 'o',   'ô' => 'o',
		'õ' => 'o',   'ö' => 'o',   'ø' => 'o',   'ù' => 'u',   'ú' => 'u',
		'û' => 'u',   'ü' => 'u',   'ý' => 'y',   'ÿ' => 'y',
		// Latin Extended-A
	    'Ā' => 'A',   'ā' => 'a',   'Ă' => 'A',   'ă' => 'a',   'Ą' => 'A',
	    'ą' => 'a',   'Ć' => 'C',   'ć' => 'c',   'Ĉ' => 'C',   'ĉ' => 'c',
	    'Ċ' => 'C',   'ċ' => 'c',   'Č' => 'C',   'č' => 'c',   'Ď' => 'D',
	    'ď' => 'd',   'Đ' => 'D',   'đ' => 'd',   'Ē' => 'E',   'ē' => 'e',
	    'Ĕ' => 'E',   'ĕ' => 'e',   'Ė' => 'E',   'ė' => 'e',   'Ę' => 'E',
	    'ę' => 'e',   'Ě' => 'E',   'ě' => 'e',   'Ĝ' => 'G',   'ĝ' => 'g',
	    'Ğ' => 'G',   'ğ' => 'g',   'Ġ' => 'G',   'ġ' => 'g',   'Ģ' => 'G',
	    'ģ' => 'g',   'Ĥ' => 'H',   'ĥ' => 'h',   'Ħ' => 'H',   'ħ' => 'h',
	    'Ĩ' => 'I',   'ĩ' => 'i',   'Ī' => 'I',   'ī' => 'i',   'Ĭ' => 'I',
	    'ĭ' => 'i',   'Į' => 'I',   'į' => 'i',   'İ' => 'I',   'ı' => 'i',
	    'Ĳ' => 'IJ',  'ĳ' => 'ij',  'Ĵ' => 'J',   'ĵ' => 'j',   'Ķ' => 'K',
	    'ķ' => 'k',   'Ĺ' => 'L',   'ĺ' => 'l',   'Ļ' => 'L',   'ļ' => 'l',
	    'Ľ' => 'L',   'ľ' => 'l',   'Ŀ' => 'L',   'ŀ' => 'l',   'Ł' => 'L',
	    'ł' => 'l',   'Ń' => 'N',   'ń' => 'n',   'Ņ' => 'N',   'ņ' => 'n',
	    'Ň' => 'N',   'ň' => 'n',   'ŉ' => "'n", 'Ŋ' => 'N',   'ŋ' => 'n',
	    'Ō' => 'O',   'ō' => 'o',   'Ŏ' => 'O',   'ŏ' => 'o',   'Ő' => 'O',
	    'ő' => 'o',   'Œ' => 'OE',  'œ' => 'oe',  'Ŕ' => 'R',   'ŕ' => 'r',
	    'Ŗ' => 'R',   'ŗ' => 'r',   'Ř' => 'R',   'ř' => 'r',   'Ś' => 'S',
	    'ś' => 's',   'Ŝ' => 'S',   'ŝ' => 's',   'Ş' => 'S',   'ş' => 's',
	    'Š' => 'S',   'š' => 's',   'Ţ' => 'T',   'ţ' => 't',   'Ť' => 'T',
	    'ť' => 't',   'Ŧ' => 'T',   'ŧ' => 't',   'Ũ' => 'U',   'ũ' => 'u',
	    'Ū' => 'U',   'ū' => 'u',   'Ŭ' => 'U',   'ŭ' => 'u',   'Ů' => 'U',
	    'ů' => 'u',   'Ű' => 'U',   'ű' => 'u',   'Ų' => 'U',   'ų' => 'u',
	    'Ŵ' => 'W',   'ŵ' => 'w',   'Ŷ' => 'Y',   'ŷ' => 'y',   'Ÿ' => 'Y',
	    'Ź' => 'Z',   'ź' => 'z',   'Ż' => 'Z',   'ż' => 'z',   'Ž' => 'Z',
	    'ž' => 'z',
	    // Latin Extended-B
	    'ƀ' => 'b',   'Ɓ' => 'B',   'Ƃ' => 'B',   'ƃ' => 'b',   'Ɔ' => 'O',
	    'Ƈ' => 'C',   'ƈ' => 'c',   'Ɖ' => 'D',   'Ɗ' => 'D',   'Ƌ' => 'D',
	    'ƌ' => 'd',   'Ǝ' => 'E',   'Ɛ' => 'E',   'Ƒ' => 'F',   'ƒ' => 'f',
	    'Ɠ' => 'G',   'Ɨ' => 'I',   'Ƙ' => 'K',   'ƙ' => 'k',   'ƚ' => 'l',
	    'Ɯ' => 'M',   'Ɲ' => 'N',   'ƞ' => 'n',   'Ɵ' => 'O',   'Ơ' => 'O',
	    'ơ' => 'o',   'Ƣ' => 'OI',  'ƣ' => 'oi',  'Ƥ' => 'P',   'ƥ' => 'p',
	    'ƫ' => 't',   'Ƭ' => 'T',   'ƭ' => 't',   'Ʈ' => 'T',   'Ư' => 'U',
	    'ư' => 'u',   'Ʋ' => 'V',   'Ƴ' => 'Y',   'ƴ' => 'y',   'Ƶ' => 'Z',
	    'ƶ' => 'z',   'ƻ' => '2',   'Ǆ' => 'DZ',  'ǅ' => 'Dz',  'ǆ' => 'dz',
	    'Ǉ' => 'LJ',  'ǈ' => 'Lj',  'ǉ' => 'lj',  'Ǌ' => 'Nj',  'ǋ' => 'Nj',
	    'ǌ' => 'nj',  'Ǎ' => 'A',   'ǎ' => 'a',   'Ǐ' => 'I',   'ǐ' => 'i',
	    'Ǒ' => 'O',   'ǒ' => 'o',   'Ǔ' => 'U',   'ǔ' => 'u',   'Ǖ' => 'U',
	    'ǖ' => 'u',   'Ǘ' => 'U',   'ǘ' => 'u',   'Ǚ' => 'U',   'ǚ' => 'u',
	    'Ǜ' => 'U',   'ǜ' => 'u',   'ǝ' => 'e',   'Ǟ' => 'A',   'ǟ' => 'a',
	    'Ǡ' => 'A',   'ǡ' => 'a',   'Ǣ' => 'AE',  'ǣ' => 'ae',  'Ǥ' => 'G',
	    'ǥ' => 'g',   'Ǧ' => 'G',   'ǧ' => 'g',   'Ǩ' => 'K',   'ǩ' => 'k',
	    'Ǫ' => 'O',   'ǫ' => 'o',   'Ǭ' => 'O',   'ǭ' => 'o',   'ǰ' => 'j',
	    'Ǳ' => 'DZ',  'ǲ' => 'Dz',  'ǳ' => 'dz',  'Ǵ' => 'G',   'ǵ' => 'g',
	    'Ǹ' => 'N',   'ǹ' => 'n',   'Ǻ' => 'A',   'ǻ' => 'a',   'Ǽ' => 'AE',
	    'ǽ' => 'ae',  'Ǿ' => 'O',   'ǿ' => 'o',   'Ȁ' => 'A',   'ȁ' => 'a',
	    'Ȃ' => 'A',   'ȃ' => 'a',   'Ȅ' => 'E',   'ȅ' => 'e',   'Ȇ' => 'E',
	    'ȇ' => 'e',   'Ȉ' => 'I',   'ȉ' => 'i',   'Ȋ' => 'I',   'ȋ' => 'i',
	    'Ȍ' => 'O',   'ȍ' => 'o',   'Ȏ' => 'O',   'ȏ' => 'o',   'Ȑ' => 'R',
	    'ȑ' => 'r',   'Ȓ' => 'R',   'ȓ' => 'r',   'Ȕ' => 'U',   'ȕ' => 'u',
	    'Ȗ' => 'U',   'ȗ' => 'u',   'Ș' => 'S',   'ș' => 's',   'Ț' => 'T',
	    'ț' => 't',   'Ȟ' => 'H',   'ȟ' => 'h',   'Ƞ' => 'N',   'ȡ' => 'd',
	    'Ȥ' => 'Z',   'ȥ' => 'z',   'Ȧ' => 'A',   'ȧ' => 'a',   'Ȩ' => 'E',
	    'ȩ' => 'e',   'Ȫ' => 'O',   'ȫ' => 'o',   'Ȭ' => 'O',   'ȭ' => 'o',
	    'Ȯ' => 'O',   'ȯ' => 'o',   'Ȱ' => 'O',   'ȱ' => 'o',   'Ȳ' => 'Y',
	    'ȳ' => 'y',   'ȴ' => 'l',   'ȵ' => 'n',   'ȶ' => 't',   'ȷ' => 'j',
	    'ȸ' => 'db',  'ȹ' => 'qp',  'Ⱥ' => 'A',   'Ȼ' => 'C',   'ȼ' => 'c',
	    'Ƚ' => 'L',   'Ⱦ' => 'T',   'ȿ' => 's',   'ɀ' => 'z',   'Ƀ' => 'B',
	    'Ʉ' => 'U',   'Ʌ' => 'V',   'Ɇ' => 'E',   'ɇ' => 'e',   'Ɉ' => 'J',
	    'ɉ' => 'j',   'Ɋ' => 'Q',   'ɋ' => 'q',   'Ɍ' => 'R',   'ɍ' => 'r',
	    'Ɏ' => 'Y',   'ɏ' => 'y',
	    // IPA Extensions
	    'ɐ' => 'a',   'ɓ' => 'b',   'ɔ' => 'o',   'ɕ' => 'c',   'ɖ' => 'd',
	    'ɗ' => 'd',   'ɘ' => 'e',   'ɛ' => 'e',   'ɜ' => 'e',   'ɝ' => 'e',
	    'ɞ' => 'e',   'ɟ' => 'j',   'ɠ' => 'g',   'ɡ' => 'g',   'ɢ' => 'G',
	    'ɥ' => 'h',   'ɦ' => 'h',   'ɨ' => 'i',   'ɪ' => 'I',   'ɫ' => 'l',
	    'ɬ' => 'l',   'ɭ' => 'l',   'ɯ' => 'm',   'ɰ' => 'm',   'ɱ' => 'm',
	    'ɲ' => 'n',   'ɳ' => 'n',   'ɴ' => 'N',   'ɵ' => 'o',   'ɶ' => 'OE',
	    'ɹ' => 'r',   'ɺ' => 'r',   'ɻ' => 'r',   'ɼ' => 'r',   'ɽ' => 'r',
	    'ɾ' => 'r',   'ɿ' => 'r',   'ʀ' => 'R',   'ʁ' => 'R',   'ʂ' => 's',
	    'ʇ' => 't',   'ʈ' => 't',   'ʉ' => 'u',   'ʋ' => 'v',   'ʌ' => 'v',
	    'ʍ' => 'w',   'ʎ' => 'y',   'ʏ' => 'Y',   'ʐ' => 'z',   'ʑ' => 'z',
	    'ʗ' => 'C',   'ʙ' => 'B',   'ʚ' => 'e',   'ʛ' => 'G',   'ʜ' => 'H',
	    'ʝ' => 'j',   'ʞ' => 'k',   'ʟ' => 'L',   'ʠ' => 'q',   'ʣ' => 'dz',
	    'ʥ' => 'dz',  'ʦ' => 'ts',  'ʨ' => 'tc',  'ʪ' => 'ls',  'ʫ' => 'lz',
	    'ʮ' => 'h',   'ʯ' => 'h',
	    // Latin Extended Additional
	    'Ḁ' => 'A',   'ḁ' => 'a',   'Ḃ' => 'B',   'ḃ' => 'b',   'Ḅ' => 'B',
	    'ḅ' => 'b',   'Ḇ' => 'B',   'ḇ' => 'b',   'Ḉ' => 'C',   'ḉ' => 'c',
	    'Ḋ' => 'D',   'ḋ' => 'd',   'Ḍ' => 'D',   'ḍ' => 'd',   'Ḏ' => 'D',
	    'ḏ' => 'd',   'Ḑ' => 'D',   'ḑ' => 'd',   'Ḓ' => 'D',   'ḓ' => 'd',
	    'Ḕ' => 'E',   'ḕ' => 'e',   'Ḗ' => 'E',   'ḗ' => 'e',   'Ḙ' => 'E',
	    'ḙ' => 'e',   'Ḛ' => 'E',   'ḛ' => 'e',   'Ḝ' => 'E',   'ḝ' => 'e',
	    'Ḟ' => 'F',   'ḟ' => 'f',   'Ḡ' => 'G',   'ḡ' => 'g',   'Ḣ' => 'H',
	    'ḣ' => 'h',   'Ḥ' => 'H',   'ḥ' => 'h',   'Ḧ' => 'H',   'ḧ' => 'h',
	    'Ḩ' => 'H',   'ḩ' => 'h',   'Ḫ' => 'H',   'ḫ' => 'h',   'Ḭ' => 'I',
	    'ḭ' => 'i',   'Ḯ' => 'I',   'ḯ' => 'i',   'Ḱ' => 'K',   'ḱ' => 'k',
	    'Ḳ' => 'K',   'ḳ' => 'k',   'Ḵ' => 'K',   'ḵ' => 'k',   'Ḷ' => 'L',
	    'ḷ' => 'l',   'Ḹ' => 'L',   'ḹ' => 'l',   'Ḻ' => 'L',   'ḻ' => 'l',
	    'Ḽ' => 'L',   'ḽ' => 'l',   'Ḿ' => 'M',   'ḿ' => 'm',   'Ṁ' => 'M',
	    'ṁ' => 'm',   'Ṃ' => 'M',   'ṃ' => 'm',   'Ṅ' => 'N',   'ṅ' => 'n',
	    'Ṇ' => 'N',   'ṇ' => 'n',   'Ṉ' => 'N',   'ṉ' => 'n',   'Ṋ' => 'N',
	    'ṋ' => 'n',   'Ṍ' => 'O',   'ṍ' => 'o',   'Ṏ' => 'O',   'ṏ' => 'o',
	    'Ṑ' => 'O',   'ṑ' => 'o',   'Ṓ' => 'O',   'ṓ' => 'o',   'Ṕ' => 'P',
	    'ṕ' => 'p',   'Ṗ' => 'P',   'ṗ' => 'p',   'Ṙ' => 'R',   'ṙ' => 'r',
	    'Ṛ' => 'R',   'ṛ' => 'r',   'Ṝ' => 'R',   'ṝ' => 'r',   'Ṟ' => 'R',
	    'ṟ' => 'r',   'Ṡ' => 'S',   'ṡ' => 's',   'Ṣ' => 'S',   'ṣ' => 's',
	    'Ṥ' => 'S',   'ṥ' => 's',   'Ṧ' => 'S',   'ṧ' => 's',   'Ṩ' => 'S',
	    'ṩ' => 's',   'Ṫ' => 'T',   'ṫ' => 't',   'Ṭ' => 'T',   'ṭ' => 't',
	    'Ṯ' => 'T',   'ṯ' => 't',   'Ṱ' => 'T',   'ṱ' => 't',   'Ṳ' => 'U',
	    'ṳ' => 'u',   'Ṵ' => 'U',   'ṵ' => 'u',   'Ṷ' => 'U',   'ṷ' => 'u',
	    'Ṹ' => 'U',   'ṹ' => 'u',   'Ṻ' => 'U',   'ṻ' => 'u',   'Ṽ' => 'V',
	    'ṽ' => 'v',   'Ṿ' => 'V',   'ṿ' => 'v',   'Ẁ' => 'W',   'ẁ' => 'w',
	    'Ẃ' => 'W',   'ẃ' => 'w',   'Ẅ' => 'W',   'ẅ' => 'w',   'Ẇ' => 'W',
	    'ẇ' => 'w',   'Ẉ' => 'W',   'ẉ' => 'w',   'Ẋ' => 'X',   'ẋ' => 'x',
	    'Ẍ' => 'X',   'ẍ' => 'x',   'Ẏ' => 'Y',   'ẏ' => 'y',   'Ẑ' => 'Z',
	    'ẑ' => 'z',   'Ẓ' => 'Z',   'ẓ' => 'z',   'Ẕ' => 'Z',   'ẕ' => 'z',
	    'ẖ' => 'h',   'ẗ' => 't',   'ẘ' => 'w',   'ẙ' => 'y',   'ẚ' => 'a',
	    'Ạ' => 'A',   'ạ' => 'a',   'Ả' => 'A',   'ả' => 'a',   'Ấ' => 'A',
	    'ấ' => 'a',   'Ầ' => 'A',   'ầ' => 'a',   'Ẩ' => 'A',   'ẩ' => 'a',
	    'Ẫ' => 'A',   'ẫ' => 'a',   'Ậ' => 'A',   'ậ' => 'a',   'Ắ' => 'A',
	    'ắ' => 'a',   'Ằ' => 'A',   'ằ' => 'a',   'Ẳ' => 'A',   'ẳ' => 'a',
	    'Ẵ' => 'A',   'ẵ' => 'a',   'Ặ' => 'A',   'ặ' => 'a',   'Ẹ' => 'E',
	    'ẹ' => 'e',   'Ẻ' => 'E',   'ẻ' => 'e',   'Ẽ' => 'E',   'ẽ' => 'e',
	    'Ế' => 'E',   'ế' => 'e',   'Ề' => 'E',   'ề' => 'e',   'Ể' => 'E',
	    'ể' => 'e',   'Ễ' => 'E',   'ễ' => 'e',   'Ệ' => 'E',   'ệ' => 'e',
	    'Ỉ' => 'I',   'ỉ' => 'i',   'Ị' => 'I',   'ị' => 'i',   'Ọ' => 'O',
	    'ọ' => 'o',   'Ỏ' => 'O',   'ỏ' => 'o',   'Ố' => 'O',   'ố' => 'o',
	    'Ồ' => 'O',   'ồ' => 'o',   'Ổ' => 'O',   'ổ' => 'o',   'Ỗ' => 'O',
	    'ỗ' => 'o',   'Ộ' => 'O',   'ộ' => 'o',   'Ớ' => 'O',   'ớ' => 'o',
	    'Ờ' => 'O',   'ờ' => 'o',   'Ở' => 'O',   'ở' => 'o',   'Ỡ' => 'O',
	    'ỡ' => 'o',   'Ợ' => 'O',   'ợ' => 'o',   'Ụ' => 'U',   'ụ' => 'u',
	    'Ủ' => 'U',   'ủ' => 'u',   'Ứ' => 'U',   'ứ' => 'u',   'Ừ' => 'U',
	    'ừ' => 'u',   'Ử' => 'U',   'ử' => 'u',   'Ữ' => 'U',   'ữ' => 'u',
	    'Ự' => 'U',   'ự' => 'u',   'Ỳ' => 'Y',   'ỳ' => 'y',   'Ỵ' => 'Y',
	    'ỵ' => 'y',   'Ỷ' => 'Y',   'ỷ' => 'y',   'Ỹ' => 'Y',   'ỹ' => 'y',
	    // General Punctuation
	    ' ' => ' ',   ' ' => ' ',   ' ' => ' ',   ' ' => ' ',   ' ' => ' ',
	    ' ' => ' ',   ' ' => ' ',   ' ' => ' ',   ' ' => ' ',   ' ' => ' ',
	    ' ' => ' ',   '​' => '',    '‌' => '',    '‍' => '',    '‐' => '-',
	    '‑' => '-',   '‒' => '-',   '–' => '-',   '—' => '-',   '―' => '-',
	    '‖' => '||',  '‘' => "'",   '’' => "'",   '‚' => ',',   '‛' => "'",
	    '“' => '"',   '”' => '"',   '‟' => '"',   '․' => '.',   '‥' => '..',
	    '…' => '...', ' ' => ' ',   '′' => "'",   '″' => '"',   '‴' => '\'"',
	    '‵' => "'",   '‶' => '"',   '‷' => '"\'', '‹' => '<',   '›' => '>',
	    '‼' => '!!',  '‽' => '?!',  '⁄' => '/',   '⁇' => '?/',  '⁈' => '?!',
	    '⁉' => '!?',
	    // Letterlike Symbols
	    '℠' => 'SM',  '™' => 'TM',
	    // Number Forms
	    '⅓' => '1/3', '⅔' => '2/3', '⅕' => '1/5', '⅖' => '2/5', '⅗' => '3/5',
	    '⅘' => '4/5', '⅙' => '1/6', '⅚' => '5/6', '⅛' => '1/8', '⅜' => '3/8',
	    '⅝' => '5/8', '⅞' => '7/8', 'Ⅰ' => 'I',   'Ⅱ' => 'II',  'Ⅲ' => 'III',
	    'Ⅳ' => 'IV',  'Ⅴ' => 'V',   'Ⅵ' => 'Vi',  'Ⅶ' => 'VII', 'Ⅷ' => 'VIII',
	    'Ⅸ' => 'IX',  'Ⅹ' => 'X',   'Ⅺ' => 'XI',  'Ⅻ' => 'XII', 'Ⅼ' => 'L',
	    'Ⅽ' => 'C',   'Ⅾ' => 'D',   'Ⅿ' => 'M',   'ⅰ' => 'i',   'ⅱ' => 'ii',
	    'ⅲ' => 'iii', 'ⅳ' => 'iv',  'ⅴ' => 'v',   'ⅵ' => 'vi',  'ⅶ' => 'vii',
	    'ⅷ' => 'viii','ⅸ' => 'ix',  'ⅹ' => 'x',   'ⅺ' => 'xi',  'ⅻ' => 'xii',
	    'ⅼ' => 'l',   'ⅽ' => 'c',   'ⅾ' => 'd',   'ⅿ' => 'm',
	    // Cyrilic
		'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd',
		'Е' => 'e', 'Ё' => 'yo', 'Ж' => 'zh', 'З'=> 'z', 'И' => 'i',
		'Й' => 'j', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n',
		'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't',
		'У' => 'u', 'Ф' => 'f', 'Х' => 'kh', 'Ц' => 'ts', 'Ч' => 'ch',
		'Ш' => 'sh', 'Щ' => 'sch', 'Ъ' => '', 'Ы' => 'y', 'Ь' => '',
		'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya', 'а' => 'a', 'б' => 'b',
		'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
		'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k',
		'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
		'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
		'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
		'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' =>'e', 'ю' => 'yu',
		'я' => 'ya',
    );

    /**
     *    Jaké defaultní práva budou mít vytvořené adresáře.
     */
    const DIR_PERMISSION = 0777; // octal

    /**
     *    Jaké defaultní práva budou mít vytvořené soubory.
     */
    const FILE_PERMISSION = 0666; // octal

    
     /**
     * Rekurzivni kopirovani slozek
     *
     * @param string zdrojova slozka
     * @param string cilova slozka
     * @param array seznam nazvu slozek ktere nebudou kopirovany
     * @return boolean
     */
    public static function rcopy($src, $dst, $skip_folder = array()) {

        $status = true;
        if (is_dir($src)) {

            //vynechame slozky dle seznamu            
            if (in_array(basename($src), $skip_folder)) {
                return true;
            }
            self::mkdir($dst);
            $files = scandir($src);
            foreach ($files as $file) {
                if ($file !== "." && $file !== "..") {
                    if (!self::rcopy("$src/$file", "$dst/$file",$skip_folder)) {
                        $status = false;
                    }
                }
            }
        } else if (file_exists($src)) {
            if (!copy($src, $dst) || !Utils::chmod($dst, Utils::FILE_PERMISSION)) {
                $status = false;
            }
        }

        return $status;
    }

    /**
     * vytvoreni adresare (prip. podadresaru) s nastavenim prav. wrapper nad mkdir() s pouzitim umask()
     *
     * @param string $path cesta (absolutni/relativni) pro nove vytvareny adresar
     * @param int $permissions octal reprezentace prav
     * @param bool $recursive vytvorit rekurzivne i podadresare? default true
     * @return bool
     */
    public static function mkdir($path, $mode = null, $recursive = true) {

        $mode = $mode ? $mode : self::DIR_PERMISSION;

        $old_umask = umask(0);
        $res = @mkdir($path, $mode, $recursive);
        umask($old_umask);

        return $res;

    }
    
    /**
     * Checks if the directory is empty.
     * 
     * @param string    $dir
     * @return bool     Is directory empty? (null if the directory is not found)
     */
    public static function isDirEmpty($dir) {
        if (!is_readable($dir)) return NULL;
        return (count(scandir($dir)) == 2);
    }

    /**
     * nastaveni prav pro dany soubor/adresar. wrapper nad chmod() s pouzitim umask()
     *
     * @param string $path
     * @param int $mode
     * @return bool
     */
    public static function chmod($path, $mode) {

        $old_mask = umask(0);
        $res = @chmod($path, $mode);
        umask($old_mask);

        return $res;

    }

    /**
     * wrapper for php symlink()
     * additionally creates link directory if not exists before linking
     *
     * @param string $target
     * @param string $link
     * @return bool
     */
    public static function symlink($target, $link) {

        $dir = dirname($link);

        if (!file_exists($dir)) {
            self::mkdir($dir);
        }

        return symlink($target, $link);
    }

    /**
     * generates checksum for content of given file(s)
     *
     * @param string|array $file string path to file or array of paths to files
     * @return string|boolean return FALSE if file(s) not exists or not readable,
     *                        otherwise returns checksum of content of given file(s) as hexadecimal string
     */
    public static function getFileChecksum($file) {

        $file = is_array($file) ? $file : array($file);
        $content = '';

        foreach ($file as $f) {
            if (!file_exists($f) || !is_readable($f)) {
                return false;
            } else {
                $content .= file_get_contents($f);
            }
        }

        return sprintf("%u", crc32($content));
    }

    /**
     * wrapper for multi-array array_diff() funcionality
     *
     * @param array $a1
     * @param array $a2
     * @return array
     */
    public static function multi_array_diff($a1, $a2) {
        // flatten array and generate new keys glueing with dot
        //dump(self::multi_implode($a1), self::multi_implode($a2),array_diff(self::multi_implode($a1), self::multi_implode($a2)));exit;
        $diff = array_diff(self::multi_implode($a1), self::multi_implode($a2));
        // explode $diff keys back into multidimensional array
        return (array)self::multi_explode($diff);
    }
    
    
    public static function ms_to_timestring($ms) {

        $time = round($ms/1000); 
        $hours = floor($time/3600);
        $mins = floor(($time % 3600)/ 60);
        $seconds = ($time % 3600)% 60;
        return $hours.':'.str_pad($mins,2,'0',STR_PAD_LEFT).":".str_pad($seconds,2,'0',STR_PAD_LEFT);
    }
    
    /**
     * Najde posledni modifikaci configu mezi config.ini, config_default.ini a config_admin.ini  
     *
     * @return Zend_Date    Datum posledni modifikace configu v souborech.
     */
    public static function getConfigLastModified()
    {
        $times = array(filemtime('config.ini'), filemtime('config_default.ini'), filemtime('config_admin.ini'));
        $time = max($times);
        return new Zend_Date($time, Zend_Date::TIMESTAMP);
    }
    
    /**
     * Převod svg do png (Imagick)
     * @param type $svg
     * 
     */
    public static function svgtopng($svg) {
        $im = new Imagick();
        $im->readimageblob('<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . $svg);
        $im->setImageFormat("png32");
        return $im;
    }
    
    
    /**
     * Testuje zda jsou MX zaznamy nastaveny na PHC servery mx.pearshealthcyber.com, mx.farmacie.cz
     * @param string $domain Domain name
     * @return boolean
     */
    public static function checkMXRecordTarget($domain) {
        $ms_phc = array('mx.pearshealthcyber.com', 'mx.farmacie.cz');
        
        $s_mx = array();
        $w_mx = array();

        getmxrr($domain, $s_mx, $w_mx);
       
         if (!$s_mx) {
            return false;
        }

        //spojíme vahu a názvy serveru do pole a seradime dle vahy
        $mx = (array_combine($w_mx, $s_mx));
        ksort($mx);

        //overime smerovani
        if(in_array(reset($mx),$ms_phc)) {
            return true;
        }
        
        return false;
    }
    
    
    /**
     * Add files and sub-directories in a folder to zip file.
     * @param string $folder
     * @param ZipArchive $zipFile
     * @param int $exclusiveLength Number of text to be exclusived from the file path.
     */
    public static function folderToZip($folder, &$zipFile, $exclusiveLength) {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    $zipFile->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    $zipFile->addEmptyDir($localPath);
                    self::folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }
    
    
    /**
     * Zip a folder (include itself).
     * Usage:
     *   zipDir('/path/to/sourceDir', '/path/to/out.zip');
     *
     * @param string $sourcePath Path of directory to be zip.
     * @param string $outZipPath Path of output zip file.
     */
    public static function zipDir($sourcePath, $outZipPath)
    {
        $pathInfo = pathInfo($sourcePath);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];
    
        $z = new ZipArchive();
        $z->open($outZipPath, ZIPARCHIVE::CREATE);
        $z->addEmptyDir($dirName);
        self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
        $z->close();
    }
    
    /**
	 *    Metoda pro převední velikosti souboru, které je použito v php-ini na
	 *    čitelnější výstup.
	 *
	 *    @param Velikost souboru.
	 */
	public static function letToNum($v)
	{
		$l = substr($v, -1);
		$ret = substr($v, 0, -1);
		switch(strtoupper($l))
		{
			case 'P':
				$ret *= 1024;
			case 'T':
				$ret *= 1024;
			case 'G':
				$ret *= 1024;
			case 'M':
				$ret *= 1024;
			case 'K':
				$ret *= 1024;
				break;
		}

		return $ret;
	}
    
}