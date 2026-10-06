<?php

class OpendataDatasetSolrStorage implements OpendataDatasetStorageInterface
{
    public function createDataset(OpendataDataset $dataset)
    {
        $repository = new OpendataDatasetSearchableRepository($dataset->getContext());
        if (!$repository->index($repository->instanceObject($dataset, $dataset->getGuid()))){
            throw new Exception("Fail indexing dataset " . json_encode($dataset->getData()));
        }

        return $dataset;
    }

    public function updateDataset(OpendataDataset $dataset)
    {
        $repository = new OpendataDatasetSearchableRepository($dataset->getContext());
        if (!$repository->index($repository->instanceObject($dataset, $dataset->getGuid()))){
            throw new Exception("Fail indexing dataset " . json_encode($dataset->getData()));
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
        // Prima si leggono tutte le righe e poi si cancella: cancellare mentre si
        // pagina sposterebbe gli offset. La find() restituisce di default solo 10 righe.
        foreach ($this->findAllByCreator($creatorId, $context) as $hit) {
            $this->deleteDataset($hit->getDataset());
        }

        return true;
    }

    /**
     * @param int $creatorId
     * @param eZContentObjectAttribute $context
     * @return OpendataDatasetSearchableObject[]
     */
    public function findAllByCreator($creatorId, eZContentObjectAttribute $context)
    {
        $repository = new OpendataDatasetSearchableRepository($context);
        $pageSize = 500;
        $offset = 0;
        $found = [];
        do {
            $parameters = OCCustomSearchParameters::instance();
            $parameters->addFilter('_creator', (int)$creatorId);
            $parameters->setLimit($pageSize);
            $parameters->setOffset($offset);
            $rows = $repository->find($parameters);
            $hits = $rows['searchHits'];
            foreach ($hits as $hit) {
                if ($hit instanceof OpendataDatasetSearchableObject) {
                    $found[] = $hit;
                }
            }
            $offset += $pageSize;
        } while (count($hits) === $pageSize);

        return $found;
    }
}
