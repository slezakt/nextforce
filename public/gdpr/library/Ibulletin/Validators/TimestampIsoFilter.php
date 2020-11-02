<?php
/*

 *  Třída pro validaci vstupu o filtru pro časovou známku.
 *  Provede validaci podle regexpu odpovidajicimu do velke casti definice ISO 8601.
 */


class Ibulletin_Validators_TimestampIsoFilter extends Zend_Validate_Abstract
{
    const WRONG = 'wrong';

    protected $_messageTemplates = array(
        self::WRONG => 'špatně zadaná časová známka'
    );

    public function isValid($time)
    {
        $isoTimestRegexp = '/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/';

        $this->_setValue($time);

        if(preg_match($isoTimestRegexp, trim($time))){
            return true;
        }
        else{
            return false;
        }
    }
}
?>