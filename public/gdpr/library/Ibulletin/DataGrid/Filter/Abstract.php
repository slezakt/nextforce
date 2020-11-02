<?php
/**
 * Ibulletin_DataGrid_Filter_Abstract
 *
 * class to provide a filter for datasource
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */


abstract class Ibulletin_DataGrid_Filter_Abstract implements Ibulletin_DataGrid_Filter_Interface
{

    /**
     * @var
     */
    protected $type;

    /**
     * @var
     */
    protected $datatype;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var bool
     */
    protected $multiple = false;

    /**
     * @var
     */
    protected $field;
    
    /**
     * @var typ formu
     */
    protected $formtype;
    
     /**
     * @var predvyplneny text nenastaveneho filtru
     */
    protected $emptyText = '';
    
     /**
     * @var umisti ve formu filter na novy radek
     */
    protected $newLineForm = false;

    /**
     * @var
     */
    protected $query;

    /**
     * @var
     */
    protected $value;

    /**
     * @var
     */
    protected $default = NULL;

    /**
     * @var
     */
    protected $title;

    /**
     * @var
     */
    protected $label;

    /**
     * @var bool
     */
    protected $casesensitive = false;

    /**
     * @var bool
     */
    protected $autocomplete = false;

    /**
     * @var bool
     */
    protected $custom = false;
    
    /**
     * Odesilani formulare filtru js pri zmene, v pripade povoleni u jednoho filtru zmeni chovani vsech
     * @var bool
     */
    protected $submitOnChange = false;

    /**
     * filter type
     *
     * @return mixed|string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param $type
     */
    public function setType($type) {
        $this->type = $type;
    }

    /**
     * data type of key used for filtering
     * @return mixed|string
     */
    public function getDatatype() {
        return $this->datatype;
    }

    /**
     * @param $type
     */
    public function setDatatype($type) {
        $this->datatype = $type;
    }

    /**
     * @param array $options
     */
    public function setOptions($options)
    {
        if (is_array($options))
            $this->options = $options;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param boolean $multiple
     */
    public function setMultiple($multiple)
    {
        $this->multiple = $multiple;
    }

    /**
     * @return boolean
     */
    public function getMultiple()
    {
        return $this->multiple;
    }

    /**
     * @param $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * @return mixed
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * get value for filtering
     *
     * @return mixed|string
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * set default key
     * @param mixed
     */
    public function setDefault($default)
    {
        $this->default = $default;
    }

    /**
     * get default selected key
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }



    /**
     * sanitize (case-sensitivity, trim) any given value
     * optional 2nd parameter defines casting
     *
     * @param bool $typeCasting
     * @param mixed $value
     * @return mixed
     */
    public function sanitize($value, $typeCasting = false)
    {
        $value = $this->getCasesensitive() ? $value : strtolower($value);
        $value = trim($value);

        if ($typeCasting) {
            $value = $this->_castValueByType($value, $this->getDatatype());
        }

        return $value;

    }

    private function _castValueByType($v, $type)
    {
        switch ($type) {
            case 'bool' :
                $v = (bool) $v;
                break;
            case 'int' :
                $v = (int) $v;
                break;
            case 'datetime' :

                $low_d = 1; $low_m = 1; $low_y = 1970;
                $high_d = 31; $high_m = 12; $high_y = date('Y');

                $d = $m = $y = null;
                // DD.MM.YYYY
                if (preg_match('/^([0-9]{1,2})[ -.]([0-9]{1,2})[ -.]([0-9]{2,4})$/', $v, $matches)) {
                    list($all,$d,$m,$y) = $matches;
                }
                // YYYY.MM.DD
                elseif (preg_match('/^([0-9]{2,4})[ -.]([0-9]{1,2})[ -.]([0-9]{1,2})$/', $v, $matches)) {
                    list($all, $y, $m, $d) = $matches;
                }
                // MM.YYYY
                elseif (preg_match('/^([0-9]{1,2})[ -.]([0-9]{2,4})$/', $v, $matches)) {
                    list($all, $m, $y) = $matches;
                }
                // YYYY.MM
                elseif (preg_match('/^([0-9]{2,4})[ -.]([0-9]{1,2})$/', $v, $matches)) {
                    list($all, $y, $m) = $matches;
                }
                // YYYY
                elseif (preg_match('/^([0-9]{2,4})$/', $v, $matches)) {
                    list($all, $y) = $matches;
                }

                $low_d = is_null($d) ? $low_d : $d; $low_m = is_null($m) ? $low_m : $m; $low_y = is_null($y) ? $low_y : $y;
                $high_d = is_null($d) ? $high_d : $d; $high_m = is_null($m) ? $high_m : $m; $high_y = is_null($y) ? $high_y : $y;

                // fix higher day -> f.e. february has only 28,29 days
                $delta = date('t', mktime(0,0,0,$high_m, 1, $high_y));
                $high_d = $high_d > $delta ? $delta : $high_d;

                $low = date('Y-m-d H:i:s',mktime(0,0,0,$low_m,$low_d,$low_y));
                $high = date('Y-m-d H:i:s',mktime(23,59,59,$high_m,$high_d,$high_y));

                $v = array($low, $high);

                // $v = date('Y-m-d', strtotime($v));
                break;
            case 'string' :
            default :
                //$v = pg_escape_literal($v);
        }
        return  $v;
    }


    /**
     * @param $value
     */
    public function setValue($value) {
        $this->value = $value;
    }

    /**
     * @param $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param bool $autocomplete
     */
    public function setAutocomplete($autocomplete)
    {
        $this->autocomplete = $autocomplete;
    }

    /**
     * @return bool
     */
    public function getAutocomplete()
    {
        return $this->autocomplete;
    }

    /**
     * @param bool $casesensitive
     *
     */
    public function setCasesensitive($casesensitive = true)
    {
        $this->casesensitive = $casesensitive;
    }

    /**
     * @return bool
     */
    public function getCasesensitive()
    {
        return $this->casesensitive;
    }

    /**
     * @param boolean $custom
     */
    public function setCustom($custom = true)
    {
        $this->custom = $custom;
    }

    /**
     * @return boolean
     */
    public function getCustom()
    {
        return $this->custom;
    }
    
    /**
     * @param string $formtype
     */
    public function setFormtype($formtype)
    {
        $this->formtype = $formtype;
    }

    /**
     * @return string
     */
    public function getFormtype()
    {
        return $this->formtype;
    }
    
     /**
     * @param string $label
     */
    public function setEmptyText($label)
    {
        $this->emptyText=  $label;
    }

    /**
     * @return string
     */
    public function getEmptyText()
    {
        return $this->emptyText;
    }
    
     /**
     * @param string $newline
     */
    public function setNewLineForm($newline)
    {
        $this->newLineForm = $newline;
    }

    /**
     * @return string
     */
    public function getNewLineForm()
    {
        return $this->newLineForm;
    }
    
    /**
     * @param string $submitOnChange
     */
    public function setSubmitOnChange($submitOnChange)
    {
        $this->submitOnChange = $submitOnChange;
    }

    /**
     * @return string
     */
    public function getSubmitOnChange()
    {
        return $this->submitOnChange;
    }
    

}