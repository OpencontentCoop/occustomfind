<?php


interface OCCustomSearchableObjectInterface
{
    public function getGuid();

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
     * @deprecated Use OCCustomSearchableRepositoryObjectCreatorInterface feature instead
     * @return OCCustomSearchableFieldInterface[]
     */
    public static function getFields();

    /**
     * @deprecated Use OCCustomSearchableRepositoryObjectCreatorInterface feature instead
     * @param $array
     *
     * @return OCCustomSearchableObjectInterface
     */
    public static function fromArray($array);

}
