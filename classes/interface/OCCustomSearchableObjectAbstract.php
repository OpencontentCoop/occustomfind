<?php


abstract class OCCustomSearchableObjectAbstract implements OCCustomSearchableObjectInterface, JsonSerializable
{
    protected $attributes = array();

    public function __construct(array $array = array())
    {
        foreach($array as $key => $value){
            if ($this->hasFieldByName($key)){
                $this->attributes[$key] = $value;
            }
        }
    }

    public function getFieldValue(OCCustomSearchableFieldInterface $field)
    {
        if (isset($this->attributes[$field->getName()])){
            return $this->attributes[$field->getName()];
        }

        return null;
    }

    protected function hasFieldByName($name)
    {
        foreach(static::getFields() as $field){
            if ($field->getName() == $name){
                return true;
            }
        }

        return false;
    }

    protected function getFieldByName($name)
    {
        foreach(static::getFields() as $field){
            if ($field->getName() == $name){
                return $field;
            }
        }

        return false;
    }

    public function toArray()
    {
        return $this->attributes;
    }

    public static function fromArray($array)
    {
        return new static($array);
    }


    function jsonSerialize()
    {
        return $this->toArray();
    }


}
