<?php

/**
 * iBulletin - RandomStringGenerator.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */


/**
 * Generator retezcu nahodnych znaku a cislic.
 *
 * Pouziti:
 * Vytvorime instanci generatoru a tim jej nakonfigurujeme.
 * Metodou get muzeme opakovane generovat retezce ruznych delek
 * nad stejnou (prednastavenou) abecedou.
 *
 * Priklad:
 * <code>
 * $randGen = new Ibulletin_RandomStringGenerator('sn', '.[#@');
 * $string = $randGen->get(10);
 * </code>
 * Potom ve $string bude nahodny retezec o delce 10 slozeny
 * nahodne z abecedy [a-z0-9\.\[#@]
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_RandomStringGenerator
{
    /**
     * Retezec obsahujici vsechna mala pismena.
     *
     * @var string Retezec s celou abecedou malych pismen
     */
    public static $alpha_s = "abcdefghijklmnopqrstuvwxyz";
    
    /**
     * Retezec obsahujici vsechna velka pismena.
     *
     * @var string Retezec s celou abecedou velkych pismen
     */
    public static $alpha_b = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    
    /**
     * Retezec se vsemi cislicemi
     *
     * @var string Retezec se vsemi cislicemi
     */
    public static $num = "0123456789";
    
    /**
     * Pole znaku ze kterych nahodne vybirame
     *
     * @var array Pole znakuu
     */
    public $poolA = array();
    
    /**
     * Delka pole znaku ze kterych nahodne vybirame
     *
     * @var int Delka pole znakuu
     */
    public $poolSize = 0;
    
    
    /**
     * Pripravi generator nahodnych cisel a pole podle ze ktereho
     * se budou vybirat znaky.
     * 
     * @param string Retezec oznacujici ktere sady pouzit: 
     *               s - mala pismena
     *               b - velka pismena
     *               n - cisla
     *               Tedy retezec 'sn' znamena pouziti malych pismen a cislic,
     *               pismena se mohou i opakovat... Defaultne je 'sbn'.
     * @param string Retezec vlastnich znaku - prida se ke znakum z predchoziho
     */
    public function __construct($sets = 'sbn', $str = null)
    {
        $sets = str_split($sets);
        $pool = "";
        foreach($sets as $set){
            if($set == 's'){
                $pool .= self::$alpha_s;
            }
            elseif($set == 'b'){
                $pool .= self::$alpha_b;
            }
            elseif($set == 'n'){
                $pool .= self::$num;
            }
        }
        
        if(!empty($str)){
            $pool .= $str;
        }
        
        $this->poolSize = strlen($pool);
        $this->poolA = str_split($pool);
        
        // Inicializovat (hloupe) random num generator
        list($usec, $sec) = explode(' ', microtime());
        $seed = ((float) $sec + ((float) $usec * 100000));
        srand($seed);
    }
    
    
    /**
     * Vrati nahodny retezec pozadovane delky.
     *
     * @param int Delka vysledneho retezce
     * @return string Nahodny retezec z pozadovaneho oboru
     */
    public function get($len)
    {
        //Generujeme znaky
        $string = "";
        for($i=0; $i<$len; $i++){
            $string .= $this->poolA[rand(0, $this->poolSize-1)];
        }
        
        return $string;
    }
}
