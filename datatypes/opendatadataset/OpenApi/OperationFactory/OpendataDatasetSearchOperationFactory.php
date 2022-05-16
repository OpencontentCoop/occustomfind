<?php

use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\OpenApi\EndpointFactory;
use Opencontent\OpenApi\Exceptions\InvalidParameterException;
use Opencontent\OpenApi\OperationFactory;
use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OpendataDatasetSearchOperationFactory extends OperationFactory\SearchOperationFactory
{
    use OpendataDatasetCheckAccessTrait;
    use OpendataDatasetFilterTrait;

    const MAX_LIMIT = 400;

    /**
     * @param OpendataDatasetEndpointFactory $endpointFactory
     * @return ezpRestMvcResult
     * @throws InvalidParameterException
     * @throws ForbiddenException
     */
    public function handleCurrentRequest(EndpointFactory $endpointFactory)
    {
        $this->checkAccess($endpointFactory->getDatasetDefinition(), 'read');

        $searchTerm = $this->getCurrentRequestParameter('searchTerm');
        $limit = (int)$this->getCurrentRequestParameter('limit');
        $offset = (int)$this->getCurrentRequestParameter('offset');

        if ($limit <= 0 || $limit > self::MAX_LIMIT) {
            throw new InvalidParameterException('limit', $limit);
        }
        if ($offset < 0) {
            throw new InvalidParameterException('offset', $offset);
        }

        $searchRepository = new OpendataDatasetSearchableRepository($endpointFactory->getAttribute());
        $parameters = OCCustomSearchParameters::instance();
        foreach ($endpointFactory->getDatasetDefinition()->getFields() as $field) {
            if (in_array($field['identifier'], $endpointFactory->getDatasetDefinition()->getFacetsSettings()) || $field['type'] == 'textarea') {
                $searchField = $field['identifier'];
                if ($searchValue = $this->getCurrentRequestParameter($searchField)){
                    $parameters->addFilter($searchField, $searchValue);
                }
            }
        }
        if ($sortBy = $this->getCurrentRequestParameter('sortBy')){
            $sortDir = $this->getCurrentRequestParameter('sortDir') ? $this->getCurrentRequestParameter('sortDir') : 'asc';
            $parameters->setSort([$sortBy => $sortDir]);
        }
        $parameters->setLimit($limit);
        $parameters->setOffset($offset);
        if (!empty($searchTerm)) {
            $parameters->setQuery($searchTerm);
        }

        $result = new \ezpRestMvcResult();
        $path = $endpointFactory->getBaseUri() . $endpointFactory->getPath();
        $result->variables = $this->buildResult($searchRepository->find($parameters), $path);

        return $result;
    }

    protected function buildResult($searchResults, $path)
    {
        $count = $searchResults['totalCount'];
        $hits = $searchResults['searchHits'];
        $result = [
            'self' => null,
            'prev' => null,
            'next' => null,
            'count' => $count,
            'items' => $this->filterDatasetList($hits),
        ];

        $parameters = [];
        foreach ($this->generateSearchParameters() as $parameter) {
            if ($this->getCurrentRequestParameter($parameter->name)) {
                $parameters[$parameter->name] = $this->getCurrentRequestParameter($parameter->name);
            }
        }

        $result['self'] = $path . '?' . http_build_query($parameters);

        if (count($hits) < $count) {
            $nextParameters = $parameters;
            $nextParameters['offset'] += $nextParameters['limit'];
            $result['next'] = $path . '?' . http_build_query($nextParameters);
        }

        if (isset($parameters['offset']) && $parameters['offset'] > 0) {
            $prevParameters = $parameters;
            $prevParameters['offset'] -= $prevParameters['limit'];
            if ($prevParameters['offset'] < 0) {
                $prevParameters['offset'] = 0;
            }
            $result['prev'] = $path . '?' . http_build_query($prevParameters);
        }

        return $result;
    }

    protected function generateSearchParameters()
    {
        $parameters = [
            new OA\Parameter('limit', OA\Parameter::IN_QUERY, 'Limit to restrict the number of entries on a page', [
                'schema' => $this->generateSchemaProperty(['type' => 'integer', 'minimum' => 1, 'maximum' => static::MAX_LIMIT, 'default' => static::DEFAULT_LIMIT, 'nullable' => true]),
            ]),
            new OA\Parameter('offset', OA\Parameter::IN_QUERY, 'Numeric offset of the first element provided on a page representing a collection request', [
                'schema' => $this->generateSchemaProperty(['type' => 'integer']),
            ]),
        ];
        /** @var OpendataDatasetSchemaFactory $schemaFactory */
        $schemaFactory = $this->getSchemaFactories()[0];
        if ($schemaFactory instanceof OpendataDatasetSchemaFactory) {
            foreach ($schemaFactory->getDatasetDefinition()->getFields() as $field) {
                if (in_array($field['identifier'], $schemaFactory->getDatasetDefinition()->getFacetsSettings()) || $field['type'] == 'textarea') {
                    $properties = ['type' => 'string', 'nullable' => true];
                    if (!empty($field['enum'])) {
                        $properties['enum'] = OpendataDatasetDefinition::parseEnumConfiguration($field['enum']);
                    }
                    $parameters[] = new OA\Parameter($field['identifier'], OA\Parameter::IN_QUERY, 'Search by ' . $field['label'], [
                        'schema' => $this->generateSchemaProperty($properties),
                    ]);
                }
            }
            $fields = array_column($schemaFactory->getDatasetDefinition()->getFields(), 'identifier');
            $parameters[] = new OA\Parameter('sortBy', OA\Parameter::IN_QUERY, 'Sort field', [
                'schema' => $this->generateSchemaProperty(['type' => 'string', 'nullable' => true, 'enum' => $fields]),
            ]);
            $parameters[] = new OA\Parameter('sortDir', OA\Parameter::IN_QUERY, 'Sort direction', [
                'schema' => $this->generateSchemaProperty(['type' => 'string', 'nullable' => true, 'enum' => ['asc', 'desc'], 'default' => 'asc']),
            ]);
        }

        return $parameters;
    }
}