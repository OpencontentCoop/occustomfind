<?php

class OpendataDatasetSolrStorage implements OpendataDatasetStorageInterface
{
    public function createDataset(OpendataDataset $dataset)
    {
        $repository = new OpendataDatasetSearchableRepository($dataset->getContext());
        if (!$repository->index($repository->instanceObject($dataset, $dataset->getGuid()))){
            throw new Exception("Fail indexing dataset " . implode(', ', $dataset->getData()));
        }

        return $dataset;
    }

    public function updateDataset(OpendataDataset $dataset)
    {
        $repository = new OpendataDatasetSearchableRepository($dataset->getContext());
        if (!$repository->index($repository->instanceObject($dataset, $dataset->getGuid()))){
            throw new Exception("Fail indexing dataset " . implode(', ', $dataset->getData()));
        }

        return $dataset;
    }

    public function deleteDataset(OpendataDataset $dataset)
    {
        $repository = new OpendataDatasetSearchableRepository($dataset->getContext());
        $repository->remove($repository->instanceObject($dataset, $dataset->getGuid()));
    }

    public function truncate(eZContentObjectAttribute $context)
    {
        $repository = new OpendataDatasetSearchableRepository($context);
        $repository->truncate();
    }

    public function getDataset($guid, eZContentObjectAttribute $context)
    {
        $repository = new OpendataDatasetSearchableRepository($context);
        /** @var OpendataDatasetSearchableObject $searchableObject */
        $searchableObject = $repository->findOneByGuid($guid);
        if ($searchableObject instanceof OpendataDatasetSearchableObject){
            return $searchableObject->getDataset();
        }

        throw new Exception("Dataset $guid not found");
    }

    public function deleteByCreator($creatorId, eZContentObjectAttribute $context)
    {
        $repository = new OpendataDatasetSearchableRepository($context);
        $parameters = OCCustomSearchParameters::instance();
        $parameters->addFilter('_creator', (int)$creatorId);
        $rows = $repository->find($parameters);

        foreach ($rows['searchHits'] as $hit){
            if ($hit instanceof OpendataDatasetSearchableObject) {
                $this->deleteDataset($hit->getDataset());
            }
        }

        return true;
    }
}
