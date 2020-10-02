<?php

interface OpendataDatasetStorageInterface
{
    /**
     * @param OpendataDataset $dataset
     * @return OpendataDataset
     */
    public function createDataset(OpendataDataset $dataset);

    /**
     * @param OpendataDataset $dataset
     * @return OpendataDataset
     */
    public function updateDataset(OpendataDataset $dataset);

    /**
     * @param OpendataDataset $dataset
     * @return bool
     */
    public function deleteDataset(OpendataDataset $dataset);

    /**
     * @param eZContentObjectAttribute $context
     * @return bool
     */
    public function truncate(eZContentObjectAttribute $context);

    /**
     * @param mixed $guid
     * @param eZContentObjectAttribute $context
     * @return OpendataDataset
     */
    public function getDataset($guid, eZContentObjectAttribute $context);

    /**
     * @param $creatorId
     * @param eZContentObjectAttribute $context
     * @return bool
     */
    public function deleteByCreator($creatorId, eZContentObjectAttribute $context);
}