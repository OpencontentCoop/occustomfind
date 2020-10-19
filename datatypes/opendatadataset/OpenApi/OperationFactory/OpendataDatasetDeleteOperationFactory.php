<?php

use Opencontent\OpenApi\OperationFactory;
use erasys\OpenApi\Spec\v3 as OA;
use Opencontent\OpenApi\EndpointFactory;
use Opencontent\OpenApi\Exceptions\InvalidParameterException;
use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OpendataDatasetDeleteOperationFactory extends OperationFactory\DeleteOperationFactory
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

        $currentDataset = $endpointFactory->getDatasetDefinition()->getDataset($requestId, $endpointFactory->getAttribute());
        $this->checkAccess($endpointFactory->getDatasetDefinition(), 'delete', $currentDataset);
        $endpointFactory->getDatasetDefinition()->deleteDataset($currentDataset);

        return new \ezpRestMvcResult();
    }
}