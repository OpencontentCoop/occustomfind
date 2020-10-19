<?php

use Opencontent\OpenApi\Exceptions\CreateContentException as OpenApiCreateContentException;
use Opencontent\OpenApi\OperationFactory;
use Opencontent\OpenApi\EndpointFactory;
use Opencontent\OpenApi\Exceptions\InvalidParameterException;
use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OpendataDatasetCreateOperationFactory extends OperationFactory\CreateOperationFactory
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
        $this->checkAccess($endpointFactory->getDatasetDefinition(), 'edit');

        $result = new \ezpRestMvcResult();
        $payload = $this->filterPayload($this->getCurrentPayload(), $endpointFactory->getDatasetDefinition());

        try {
            $dataset = $endpointFactory->getDatasetDefinition()->createDataset(
                $endpointFactory->getDatasetDefinition()->create($payload, $endpointFactory->getAttribute())
            );
            $result->variables = $this->filterDataset($dataset);
        } catch (Exception $e) {
            throw new OpenApiCreateContentException($e->getMessage(), $e->getCode(), $e);
        }

        return $result;
    }

    protected function generateOperationAdditionalProperties()
    {
        $properties = parent::generateOperationAdditionalProperties();
        unset($properties['parameters']);

        return $properties;
    }
}