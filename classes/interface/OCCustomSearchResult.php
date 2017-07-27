<?php


class OCCustomSearchResult
{
    /**
     * @var OCCustomSearchableRepositoryInterface
     */
    private $repository;

    public function __construct(OCCustomSearchableRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function fromArrayResult(array $resultArray)
    {
        eZDebug::writeDebug($resultArray['responseHeader'], __METHOD__);

        /** @var OCCustomSearchableObjectInterface $searchableClass */
        $searchableClass = $this->repository->availableForClass();
        $fields = $searchableClass::getFields();

        $result = $this->getEmptyResult();
        if (isset( $resultArray['response'] )) {

            $result['totalCount'] = $resultArray['response']['numFound'];
            foreach ($resultArray['response']['docs'] as $index => $doc) {
                if ($index == 0){
                    eZDebug::writeDebug($doc, __METHOD__);
                }
                if (isset( $doc[$this->repository->getStorageObjectFieldName()] )) {
                    $data = ezfSolrStorage::unserializeData($doc[$this->repository->getStorageObjectFieldName()]);
                    $result['searchHits'][] = $searchableClass::fromArray($data);
                }
            }
            if (isset($resultArray['facet_counts']['facet_fields'])){
                foreach($resultArray['facet_counts']['facet_fields'] as $solrName => $facetData){
                    foreach($fields as $field){
                        if ($field->getSolrName('facet') == $solrName){
                            $result['facets'][$field->getName()] = $facetData;
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function getEmptyResult()
    {
        return array(
            'totalCount' => 0,
            'searchHits' => array(),
            'facets' => array()
        );
    }

}
