<?php

class OpendataDatasetDeleteItemConnector extends OpendataDatasetConnector
{
    protected function getSchema()
    {
        return [
            'title' => ezpI18n::tr('opendatadataset', 'Are you sure you want to delete the record?'),
            'type' => 'object',
            'properties' => [
                'confirm' => [
                    'type' => 'boolean',
                ],
            ],
        ];
    }

    protected function getOptions()
    {
        return [
            'form' => [
                'attributes' => [
                    'action' => $this->getHelper()->getServiceUrl('action', $this->getHelper()->getParameters()),
                    'method' => 'post'
                ]
            ],
            'fields' => [
                'confirm' => [
                    'type' => 'hidden',
                ],
            ],
        ];
    }

    protected function submit()
    {
        return $this->datasetDefinition->deleteDataset($this->currentDataset);
    }
}
