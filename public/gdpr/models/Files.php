<?php

/**
 * Files - service pro spravu souboru (file uploader, file manager)
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */

class Files {

    /**
     * Koncovka shadow souboru
     * @var string
     */
    public static $shadowFileIdentifier = '.shadow';

    /**
     * Vrati podobu nazvu shadow souboru
     * @param $file
     * @return string
     */
    public static function getShadowFile($file) {
        return $file . self::$shadowFileIdentifier;
    }

    /**
     * Kontroluje zda k souboru existuje shadow file
     * @param $file
     * @return bool
     */
    public static function hasShadowFile($file) {
        return file_exists(self::getShadowFile($file));
    }
    
    /**
     * Vytvori shadow file
     * @param $file
     * @return bool
     */
    public static function createShadowFile($file) {

        $f = self::getShadowFile($file);
        if (!$f) return false;

        if (copy($file, $f)) {
            Utils::chmod($f, Utils::FILE_PERMISSION);
            return true;
        } else {
            return false;
        }
    }

}
