<?php


class OCCustomSearchableField implements OCCustomSearchableFieldInterface
{
    private static $FieldTypeMap = array(
        'int' => 'i',
        'float' => 'f',
        'double' => 'd',
        'sint' => 'si',
        'sfloat' => 'sf',
        'sdouble' => 'sd',
        'string' => 's',
        'long' => 'l',
        'slong' => 'sl',
        'text' => 't',
        'boolean' => 'b',
        'date' => 'dt',
        'random' => 'random',
        'keyword' => 'k',
        'lckeyword' => 'lk',
        'textgen' => 'tg',
        'alphaOnlySort' => 'as',
        'tint' => 'ti',
        'tfloat' => 'tf',
        'tdouble' => 'td',
        'tlong' => 'tl',
        'tdate' => 'tdt',
        'geopoint' => 'gpt',
        'geohash' => 'gh',
        'mstring' => 'ms',
        'mtext' => 'mt',
        'texticu' => 'tu',
        'binary' => 'bst',
    );

    public static function create($name, $type, $label = null)
    {
        $instance = new self;
        $instance->setName($name)->setType($type);
        if ($label){
            $instance->setLabel($label);
        }
        return $instance;
    }

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    private $multiValued = false;

    /**
     * @var string
     */
    private $label;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return OCCustomSearchableField
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return OCCustomSearchableField
     */
    public function setType($type)
    {
        if (substr($type, -2) === '[]'){
            $type = rtrim($type, '[]');
            $this->setMultiValued(true);
        }
        $this->type = $type;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isMultiValued()
    {
        return $this->multiValued;
    }

    /**
     * @param boolean $multiValued
     *
     * @return OCCustomSearchableField
     */
    public function setMultiValued($multiValued)
    {
        $this->multiValued = $multiValued;

        return $this;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

    public function getSolrName($context = null)
    {
        $separator = '_';
        if ($this->isMultiValued()){
            $separator .= ezfSolrDocumentFieldBase::SUBATTR_FIELD_SEPARATOR;
        }
        return $this->getName() . $separator . self::$FieldTypeMap[$this->getType()];
    }

    public static function convertMomentFormatToPhp($format)
    {
        $replacements = [
            'DD' => 'd',
            'ddd' => 'D',
            'D' => 'j',
            'dddd' => 'l',
            'E' => 'N',
            'o' => 'S',
            'e' => 'w',
            'DDD' => 'z',
            'W' => 'W',
            'MMMM' => 'F',
            'MM' => 'm',
            'MMM' => 'M',
            'M' => 'n',
            'YYYY' => 'Y',
            'YY' => 'y',
            'a' => 'a',
            'A' => 'A',
            'h' => 'g',
            'H' => 'G',
            'hh' => 'h',
            'HH' => 'H',
            'mm' => 'i',
            'ss' => 's',
            'SSS' => 'u',
            'zz' => 'e',
            'X' => 'U',
        ];

        return strtr($format, $replacements);
    }

    public static function convertPhpToMomentFormat($phpFormat)
    {
        $replacements = [
            'A' => 'A',      // for the sake of escaping below
            'a' => 'a',      // for the sake of escaping below
            'B' => '',       // Swatch internet time (.beats), no equivalent
            'c' => 'YYYY-MM-DD[T]HH:mm:ssZ', // ISO 8601
            'D' => 'ddd',
            'd' => 'DD',
            'e' => 'zz',     // deprecated since version 1.6.0 of moment.js
            'F' => 'MMMM',
            'G' => 'H',
            'g' => 'h',
            'H' => 'HH',
            'h' => 'hh',
            'I' => '',       // Daylight Saving Time? => moment().isDST();
            'i' => 'mm',
            'j' => 'D',
            'L' => '',       // Leap year? => moment().isLeapYear();
            'l' => 'dddd',
            'M' => 'MMM',
            'm' => 'MM',
            'N' => 'E',
            'n' => 'M',
            'O' => 'ZZ',
            'o' => 'YYYY',
            'P' => 'Z',
            'r' => 'ddd, DD MMM YYYY HH:mm:ss ZZ', // RFC 2822
            'S' => 'o',
            's' => 'ss',
            'T' => 'z',      // deprecated since version 1.6.0 of moment.js
            't' => '',       // days in the month => moment().daysInMonth();
            'U' => 'X',
            'u' => 'SSSSSS', // microseconds
            'v' => 'SSS',    // milliseconds (from PHP 7.0.0)
            'W' => 'W',      // for the sake of escaping below
            'w' => 'e',
            'Y' => 'YYYY',
            'y' => 'YY',
            'Z' => '',       // time zone offset in minutes => moment().zone();
            'z' => 'DDD',
        ];

        // Converts escaped characters.
        foreach ($replacements as $from => $to) {
            $replacements['\\' . $from] = '[' . $from . ']';
        }

        return strtr($phpFormat, $replacements);
    }
}
