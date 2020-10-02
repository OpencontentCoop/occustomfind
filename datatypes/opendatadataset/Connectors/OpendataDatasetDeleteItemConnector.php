<?php

class OpendataDatasetDeleteItemConnector extends OpendataDatasetConnector
{
    protected function getSchema()
    {
        return [
            'title' => 'Sei sicuro di eliminare il record?',
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