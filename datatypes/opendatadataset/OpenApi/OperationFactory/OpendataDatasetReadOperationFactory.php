<?php

use Opencontent\OpenApi\OperationFactory;
use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\OpenApi\EndpointFactory;
use Opencontent\OpenApi\Exceptions\InvalidParameterException;
use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OpendataDatasetReadOperationFactory extends OperationFactory\ReadOperationFactory
{
    use OpendataDatasetCheckAccessTrait;
    use OpendataDatasetFilterTrait;

    /**
     * @param OpendataDatasetEndpointFactory $endpointFactory
     * @return ezpRestMvcResult
     * @throws InvalidParameterException
     * @throws ForbiddenException
     */
    public function handleCurrentRequest(EndpointFactory $endpointFactory)
    {
        $this->checkAccess($endpointFactory->getDatasetDefinition(), 'read');
        $requestId = $this->getCurrentRequestParameter($this->getItemIdLabel());
        if (empty($requestId)){
            throw new InvalidParameterException($this->getItemIdLabel(), $requestId);
        }

        $result = new \ezpRestMvcResult();
        $result->variables = $this->filterDataset(
            $endpointFactory->getDatasetDefinition()->getDataset($requestId, $endpointFactory->getAttribute())
        );

        return $result;
    }

    protected function generateOperationAdditionalProperties()
    {
        $properties = parent::generateOperationAdditionalProperties();
        $properties['parameters'] = [
            new OA\Parameter($this->getItemIdLabel(), OA\Parameter::IN_PATH, $this->getItemIdDescription(), [
                'schema' => $this->generateSchemaProperty(['type' => 'string']),
                'required' => true,
            ])
        ];

        return $properties;
    }
}