<?php


abstract class OCCustomSearchableRepositoryAbstract implements OCCustomSearchableRepositoryInterface
{
    const META_CUSTOM_PREFIX = 'meta_custom_search_ms';
    const META_REPOSITORY_PREFIX = 'meta_custom_search_repository_ms';

    public function index(OCCustomSearchableObjectInterface $object, $commit = true)
    {
        $docList = array();
        $docBoost = 1.0;

        $doc = new eZSolrDoc($docBoost);

        if (is_numeric($object->getGuid())) {
            throw new Exception("OCCustomSearchableObjectInterface ID can must be an alphanumeric string");
        }

        $doc->addField(ezfSolrDocumentFieldBase::generateMetaFieldName('guid'), $object->getGuid());
        $doc->addField(ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id'), eZSolr::installationID());
        $doc->addField(ezfSolrDocumentFieldBase::generateMetaFieldName('installation_url'), $this->getInstallationUrl());
        $doc->addField(self::META_REPOSITORY_PREFIX, $this->getIdentifier());
        $doc->addField(self::META_CUSTOM_PREFIX, eZSolr::installationID());

        foreach ($this->getFields() as $field) {
            $value = $object->getFieldValue($field);
            if ($field->isMultiValued()) {
                foreach ((array)$value as $item) {
                    $doc->addField($field->getSolrName(), (string)$item);
                }
            } else {
                $doc->addField($field->getSolrName(), $value);
            }
        }

        $doc->addField($this->getStorageObjectFieldName(), base64_encode(json_encode($object->toArray())));

        $debugDom = new DOMDocument('1.0', 'utf-8');
        $debugDom->formatOutput = true;
        $debugDom->loadXML($doc->docToXML());
        eZDebug::writeDebug($debugDom->saveXML(), __METHOD__);

        $languageCode = eZLocale::currentLocaleCode();
        $docList[$languageCode] = $doc;

        $softCommit = false;
        if (ezfeZPSolrQueryBuilder::$FindINI->variable('IndexOptions', 'EnableSoftCommits') === 'true') {
            $softCommit = true;
        }
        if (ezfeZPSolrQueryBuilder::$FindINI->variable('IndexOptions', 'DisableDirectCommits') === 'true') {
            $commit = false;
        }
        $commitWithin = 0;
        if (ezfeZPSolrQueryBuilder::$FindINI->variable('IndexOptions', 'CommitWithin') > 0) {
            $commitWithin = ezfeZPSolrQueryBuilder::$FindINI->variable('IndexOptions', 'CommitWithin');
        }
        $optimize = false;
        if ($commit
            && ( ezfeZPSolrQueryBuilder::$FindINI->variable('IndexOptions', 'OptimizeOnCommit') === 'enabled' )
        ) {
            $optimize = true;
        }

        $solr = new eZSolrBase();

        return $solr->addDocs($docList, $commit, $optimize, $commitWithin, $softCommit);
    }

    public function remove(OCCustomSearchableObjectInterface $object)
    {
        $docList = array();

        $optimize = false;
        if (ezfeZPSolrQueryBuilder::$FindINI->variable('IndexOptions', 'OptimizeOnCommit') === 'enabled') {
            $optimize = true;
        }
        $commitWithin = 0;
        if (ezfeZPSolrQueryBuilder::$FindINI->variable('IndexOptions', 'CommitWithin') > 0) {
            $commitWithin = ezfeZPSolrQueryBuilder::$FindINI->variable('IndexOptions', 'CommitWithin');
        }

        $languageCode = eZLocale::currentLocaleCode();
        $docList[$languageCode] = $object->getGuid();

        $solr = new eZSolrBase();

        return $solr->deleteDocs($docList, false, true, $optimize, $commitWithin);
    }

    public function truncate()
    {
        $solr = new eZSolrBase();
        $query = self::META_REPOSITORY_PREFIX . ':' . $this->getIdentifier();

        return $solr->deleteDocs(array(), $query, true);
    }

    public function find(OCCustomSearchParameters $parameters)
    {
        eZDebug::createAccumulator('Query build', 'eZ Find');
        eZDebug::accumulatorStart('Query build');
        $queryParams = $this->buildQuery($parameters);
        eZDebug::accumulatorStop('Query build');

        eZDebug::createAccumulator('Engine time', 'eZ Find');
        eZDebug::accumulatorStart('Engine time');
        $solr = new eZSolrBase();
        $resultArray = $solr->rawSearch($queryParams);
        eZDebug::accumulatorStop('Engine time');

        $result = new OCCustomSearchResult($this);

        return $result->fromArrayResult($resultArray);
    }

    private function getInstallationUrl()
    {
        return ezfeZPSolrQueryBuilder::$FindINI->variable('SiteSettings',
            'URLProtocol') . ezfeZPSolrQueryBuilder::$SiteINI->variable('SiteSettings', 'SiteURL') . '/';
    }

    protected function buildQuery(OCCustomSearchParameters $parameters)
    {
        $filterQuery = array();

        $filterQuery[] = ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id') . ':' . eZSolr::installationID();
        $filterQuery[] = ezfSolrDocumentFieldBase::generateMetaFieldName('installation_id') . ':' . eZSolr::installationID();
        $filterQuery[] = self::META_REPOSITORY_PREFIX . ':' . $this->getIdentifier();
        $filterQuery[] = self::META_CUSTOM_PREFIX . ':' . eZSolr::installationID();

        $filter = $this->buildFilters($parameters->getFilters());
        if ($filter !== null) {
            $filterQuery[] = $filter;
        }

        $fieldsToReturnString = 'score, *';

        $queryFields = $this->buildQueryFields();

        $queryParams = array_merge(
            array(
                'q' => $parameters->getQuery(),
                'qf' => implode(' ', $queryFields),
                'qt' => 'ezpublish',
                'start' => $parameters->getOffset(),
                'rows' => $parameters->getLimit(),
                'sort' => $this->buildSort($parameters->getSort()),
                'indent' => 'on',
                'version' => '2.2',
                'fl' => $fieldsToReturnString,
                'fq' => $filterQuery,
                'wt' => 'php'
            ),
            $this->buildFacet($parameters->getFacets())
        );

        return $queryParams;
    }

    protected function buildFilters(array $filterArray)
    {
        if (!empty( $filterArray )) {
            $booleanOperator = $this->getBooleanOperatorFromFilter($filterArray);

            $filterQueryList = array();
            foreach ($filterArray as $name => $value) {

                if (is_array($value)) {
                    $filterQueryList[] = '( ' . $this->buildFilters($value) . ' )';
                } else {
                    if ($value !== null) {
                        $filterQueryList[] = $this->getFieldSolrName($name, 'filter') . ':' . $value;
                    }
                }
            }

            if (!empty( $filterQueryList )) {
                return implode(" $booleanOperator ", $filterQueryList);
            }
        }

        return null;
    }

    private function getBooleanOperatorFromFilter(&$filter)
    {
        if (isset( $filter[0] ) and is_string($filter[0]) and in_array($filter[0],
                ezfeZPSolrQueryBuilder::$allowedBooleanOperators)
        ) {
            $retVal = strtoupper($filter[0]);
            unset( $filter[0] );

            return $retVal;
        } else {
            return ezfeZPSolrQueryBuilder::DEFAULT_BOOLEAN_OPERATOR;
        }
    }

    protected function buildSort(array $sortArray)
    {
        $sortString = array('score desc');

        if (!empty( $sortArray )) {
            $sortString = '';
            foreach ($sortArray as $field => $order) {
                // If array, set key and order from array values
                if (is_array($order)) {
                    $field = $order[0];
                    $order = $order[1];
                }

                // Fixup field name
                switch ($field) {
                    case 'score':
                    case 'relevance': {
                        $field = 'score';
                    }
                        break;


                    default: {
                        $field = $this->getFieldSolrName($field, 'sort');
                        if (!$field) {
                            eZDebug::writeNotice("Sort field $field not found", __METHOD__);
                            continue;
                        }
                    }
                        break;
                }

                // Fixup order name.
                switch (strtolower($order)) {
                    case 'desc':
                    case 'asc': {
                        $order = strtolower($order);
                    }
                        break;

                    default: {
                        eZDebug::writeDebug('Unrecognized sort order. Setting for order for default: "desc"',
                            __METHOD__);
                        $order = 'desc';
                    }
                        break;
                }

                $sortString[] = $field . ' ' . $order;
            }
        }

        return implode(',', $sortString);
    }

    protected function buildFacet(array $facetsArray)
    {
        $queryParamList = array();
        foreach ($facetsArray as $facetDefinition) {

            $queryPart = array();

            $queryPart['field'] = $this->getFieldSolrName($facetDefinition['field'], 'facet');
            if (!empty( $facetDefinition['sort'] )) {
                switch (strtolower($facetDefinition['sort'])) {
                    case 'count': {
                        $queryPart['sort'] = 'true';
                    }
                        break;

                    case 'alpha': {
                        $queryPart['sort'] = 'false';
                    }
                        break;

                    default: {
                        eZDebug::writeWarning('Invalid sort option provided: ' . $facetDefinition['sort'], __METHOD__);
                    }
                        break;
                }
            }
            // Get limit option
            if (!empty( $facetDefinition['limit'] )) {
                $queryPart['limit'] = $facetDefinition['limit'];
            } else {
                $queryPart['limit'] = ezfeZPSolrQueryBuilder::FACET_LIMIT;
            }

            // Get offset
            if (!empty( $facetDefinition['offset'] )) {
                $queryPart['offset'] = $facetDefinition['offset'];
            } else {
                $queryPart['offset'] = ezfeZPSolrQueryBuilder::FACET_OFFSET;
            }

            // Get mincount
            if (!empty( $facetDefinition['mincount'] )) {
                $queryPart['mincount'] = $facetDefinition['mincount'];
            } else {
                $queryPart['mincount'] = ezfeZPSolrQueryBuilder::FACET_MINCOUNT;
            }

            // Get missing option.
            if (!empty( $facetDefinition['missing'] )) {
                $queryPart['missing'] = 'true';
            }

            if (!empty( $queryPart )) {
                foreach ($queryPart as $key => $value) {
                    // check for fully prepared parameter names, like the per field options
                    if ($key !== 'field' && !empty( $queryParamList['facet.' . $key] ) && isset( $queryPart['field'] )) {
                        // local override for one given facet
                        $queryParamList['f.' . $queryPart['field'] . '.facet.' . $key][] = $value;
                    } else {
                        // global value
                        $queryParamList['facet.' . $key][] = $value;
                    }
                }
            }

        }
        if (!empty( $queryParamList )) {
            $queryParamList['facet'] = 'true';
        }

        return $queryParamList;
    }

    protected function getFields()
    {
        /** @var OCCustomSearchableObjectInterface $class */
        $class = $this->availableForClass();

        return $class::getFields();
    }

    protected function buildMultiFieldQuery($searchText)
    {
        $multiFieldQuery = '';

        foreach ($this->getFields() as $field) {
            $multiFieldQuery .= $field->getSolrName() . ':(' . $searchText . ')';
            $multiFieldQuery .= ' ';
        }

        return $multiFieldQuery;
    }

    protected function buildQueryFields()
    {
        $queryFields = array();
        foreach ($this->getFields() as $field) {
            $queryFields[] = $field->getSolrName();
        }

        return $queryFields;
    }

    protected function getFieldSolrName($fieldName, $context)
    {
        foreach ($this->getFields() as $field) {
            if ($field->getName() == $fieldName) {
                return $field->getSolrName($context);
            }
        }

        return null;
    }

    public function getStorageObjectFieldName()
    {
        return self::STORAGE_ATTR_FIELD_PREFIX . $this->getIdentifier() . self::STORAGE_ATTR_FIELD_SUFFIX;
    }

}
