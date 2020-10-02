<?php


use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OpendataDatasetDeleteDataConnector extends OpendataDatasetConnector
{
    protected function getData()
    {
        return null;
    }

    protected function getSchema()
    {
        return [
            'title' => ezpI18n::tr('opendatadataset', 'Are you sure you are removing all data from the dataset?'),
            'type' => 'object',
            'properties' => [
                'confirm' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Please type %name to confirm.', null,
                        ['%name' => '<em>'.$this->attribute->object()->attribute('name').'</em>']),
                    'type' => 'string',
                    'required' => true,
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
                ],
                'buttons' => [
                    'submit' => [],
                    'reset' => [],
                ],
            ]
        ];
    }

    protected function submit()
    {
        if ($_POST['confirm'] === $this->attribute->object()->attribute('name')) {
            $this->datasetDefinition->truncate($this->attribute);
        } else {
            throw new Exception(ezpI18n::tr('opendatadataset', 'Please type %name to confirm.', null,
                ['%name' => $this->attribute->object()->attribute('name')]));
        }

        return true;
    }

}