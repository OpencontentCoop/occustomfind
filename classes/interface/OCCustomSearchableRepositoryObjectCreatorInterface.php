<?php


interface OCCustomSearchableRepositoryObjectCreatorInterface
{
    /**
     * @param $data
     * @param $guid
     * @return OCCustomSearchableObjectInterface
     */
    public function instanceObject($data, $guid);
}