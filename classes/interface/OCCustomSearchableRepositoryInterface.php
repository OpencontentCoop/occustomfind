<?php


interface OCCustomSearchableRepositoryInterface
{
    const STORAGE_ATTR_FIELD_PREFIX = 'as_';
    const STORAGE_ATTR_FIELD_SUFFIX = '_bst';

    /**
     * @return string
     */
    public function getIdentifier();

    /**
     * @deprecated Use OCCustomSearchableRepositoryObjectCreatorInterface feature instead
     * @return string FQCN of OCCustomSearchableObjectInterface class
     */
    public function availableForClass();

    /**
     * @return int
     */
    public function countSearchableObjects();

    /**
     * @param int $limit
     * @param int $offset
     *
     * @return OCCustomSearchableObjectInterface[]
     */
    public function fetchSearchableObjectList($limit, $offset);

    /**
     * @param $objectID
     *
     * @return OCCustomSearchableObjectInterface
     */
    public function fetchSearchableObject($objectID);

    /**
     * @param OCCustomSearchParameters $parameters
     *
     * @return array
     */
    public function find(OCCustomSearchParameters $parameters);

    /**
     * @param OCCustomSearchableObjectInterface $object
     * @param bool $commit
     *
     * @return boolean
     */
    public function index(OCCustomSearchableObjectInterface $object, $commit = true);

    /**
     * @param OCCustomSearchableObjectInterface $object
     *
     * @return boolean
     */
    public function remove(OCCustomSearchableObjectInterface $object);

    /**
     * @return void
     */
    public function truncate();

    /**
     * @see OCCustomSearchableRepositoryAbstract::getStorageObjectFieldName
     * @see OCCustomSearchResult::fromArray
     * @return string
     */
    public function getStorageObjectFieldName();

    /**
     * @return OCCustomSearchableFieldInterface[]
     */
    public function getFields();

    /**
     * @param string $fieldName
     *
     * @return OCCustomSearchableFieldInterface|null
     */
    public function getFieldByName($fieldName);
}
