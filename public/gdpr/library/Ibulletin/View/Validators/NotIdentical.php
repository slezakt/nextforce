<?
/**
 * Validator odvozeny od Zend_Validate_Identical (polozka se musi rovnat predem zadane hodnote),
 * ovsem tento akceptuje pokud se polozka nerovna zadane hodnote.
 */
class Ibulletin_Validators_NotIdentical extends Zend_Validate_Identical{

    /**
     * Zpravy pro chybach
     */
    protected $_messageTemplates = array(
        self::NOT_SAME => 'Tokens match',
        self::MISSING_TOKEN => 'No token was provided to match against',
    );



    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if a token has been set and the provided value
     * does not match that token.
     *
     * @param  string $value
     * @return boolean
     */
    public function isValid($value)
    {
        $this->_setValue($value);
        $token = $this->getToken();

        if (empty($token)) {
            $this->_error(self::MISSING_TOKEN);
            return false;
        }

        if ($value === $token)  {
            $this->_error(self::NOT_SAME);
            return false;
        }

        return true;
    }

}