<?php

class OpendataDatasetChainStorage implements OpendataDatasetStorageInterface
{
    private $storageList = [];

    /**
     * @param OpendataDatasetStorageInterface[] $storageList
     */
    public function __construct(array $storageList)
    {
        $this->storageList = $storageList;
    }

    public function createDataset(OpendataDataset $dataset)
    {
        foreach ($this->storageList as $storage) {
            $storage->createDataset($dataset);
        }

        return $dataset;
    }

    public function updateDataset(OpendataDataset $dataset)
    {
        foreach ($this->storageList as $storage) {
            $storage->updateDataset($dataset);
        }

        return $dataset;
    }

    public function deleteDataset(OpendataDataset $dataset)
    {
        foreach ($this->storageList as $storage) {
            $storage->deleteDataset($dataset);
        }

        return true;
    }

    public function truncate(eZContentObjectAttribute $context)
    {
        foreach ($this->storageList as $storage) {
            $storage->truncate($context);
        }

        return true;
    }

    public function getDataset($guid, eZContentObjectAttribute $context)
    {
        try {
            foreach ($this->storageList as $storage) {
                $dataset = $storage->getDataset($guid, $context);
                if ($dataset instanceof OpendataDataset) {
                    return $dataset;
                }
            }
        }catch (Exception $e){

        }

        throw new Exception("Dataset $guid not found");
    }

    public function deleteByCreator($creatorId, eZContentObjectAttribute $context)
    {
        foreach ($this->storageList as $storage) {
            $storage->deleteByCreator($creatorId, $context);
        }

        return true;
    }


}