<?php

use Opencontent\OpenApi\OperationFactory;
use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\OpenApi\EndpointFactory;
use Opencontent\OpenApi\Exceptions\InvalidParameterException;
use Opencontent\Opendata\Api\Exception\ForbiddenException;
use Opencontent\OpenApi\Exceptions\UpdateContentException as OpenApiUpdateContentException;

class OpendataDatasetUpdateOperationFactory extends OperationFactory\UpdateOperationFactory
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
        $requestId = $this->getCurrentRequestParameter($this->getItemIdLabel());
        if (empty($requestId)){
            throw new InvalidParameterException($this->getItemIdLabel(), $requestId);
        }

        $currentDataset = $endpointFactory->getDatasetDefinition()->getDataset($requestId, $endpointFactory->getAttribute());
        $this->checkAccess($endpointFactory->getDatasetDefinition(), 'edit', $currentDataset);
        $result = new \ezpRestMvcResult();
        $payload = $this->filterPayload($this->getCurrentPayload(), $endpointFactory->getDatasetDefinition());
        try {
            $dataset = $endpointFactory->getDatasetDefinition()->create($payload, $endpointFactory->getAttribute());
            $dataset->setGuid($currentDataset->getGuid());
            $dataset->setCreatedAt($currentDataset->getCreatedAt());
            $dataset->setCreator($currentDataset->getCreator());
            $result->variables = $this->filterDataset(
                $endpointFactory->getDatasetDefinition()->updateDataset($dataset)
            );
        } catch (Exception $e) {
            throw new OpenApiUpdateContentException($e->getMessage(), $e->getCode(), $e);
        }

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