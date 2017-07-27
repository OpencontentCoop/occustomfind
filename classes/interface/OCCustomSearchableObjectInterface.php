<?php


interface OCCustomSearchableObjectInterface
{
    public function getGuid();

    /**
     * @return OCCustomSearchableFieldInterface[]
     */
    public static function getFields();

    /**
     * @param OCCustomSearchableFieldInterface $field
     *
     * @return mixed
     */
    public function getFieldValue(OCCustomSearchableFieldInterface $field);

    /**
     * @return array
     */
    public function toArray();

    /**
     * @param $array
     *
     * @return OCCustomSearchableObjectInterface
     */
    public static function fromArray($array);

}
