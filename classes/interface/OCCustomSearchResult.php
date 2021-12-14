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
        eZDebugSetting::writeDebug('occustomfind', $resultArray['responseHeader'], __METHOD__);

        $fields = $this->repository->getFields();

        $result = $this->getEmptyResult();
        if (isset( $resultArray['response'] )) {

            $result['totalCount'] = $resultArray['response']['numFound'];
            foreach ($resultArray['response']['docs'] as $index => $doc) {
                if ($index == 0){
                    eZDebugSetting::writeDebug('occustomfind', $doc, __METHOD__);
                }
                if (isset( $doc[$this->repository->getStorageObjectFieldName()] )) {
                    $data = ezfSolrStorage::unserializeData($doc[$this->repository->getStorageObjectFieldName()]);
                    $result['searchHits'][] = $this->instanceObject($data, $doc[ezfSolrDocumentFieldBase::generateMetaFieldName('guid')]);
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

    /**
     * @param $data
     * @param $guid
     * @return OCCustomSearchableObjectInterface
     */
    private function instanceObject($data, $guid)
    {
        if ($this->repository instanceof OCCustomSearchableRepositoryObjectCreatorInterface){
            return $this->repository->instanceObject($data, $guid);
        }

        /** @var OCCustomSearchableObjectInterface $searchableClass */
        $searchableClass = $this->repository->availableForClass();

        return $searchableClass::fromArray($data);
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
