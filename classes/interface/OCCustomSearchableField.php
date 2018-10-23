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

    public static function create($name, $type)
    {
        $instance = new self;
        $instance->setName($name)->setType($type);
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


    public function getSolrName($context = null)
    {
        $separator = '_';
        if ($this->isMultiValued()){
            $separator .= ezfSolrDocumentFieldBase::SUBATTR_FIELD_SEPARATOR;
        }
        return $this->getName() . $separator . self::$FieldTypeMap[$this->getType()];
    }

}
