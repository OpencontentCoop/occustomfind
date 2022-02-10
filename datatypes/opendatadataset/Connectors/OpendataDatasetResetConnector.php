<?php

use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OpendataDatasetResetConnector extends OpendataDatasetConnector
{
    protected function getData()
    {
        return null;
    }

    protected function getSchema()
    {
        return [
            'title' => ezpI18n::tr('opendatadataset', 'Are you sure you are removing all data and settings?'),
            'type' => 'object',
            'properties' => [
                'confirm' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Please type %name to confirm.', null,
                        ['%name' => '<em>'.ezpI18n::tr('design/standard/content/view', 'Remove').'</em>']),
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
        if ($_POST['confirm'] === ezpI18n::tr('design/standard/content/view', 'Remove')) {
            $this->datasetDefinition->truncate($this->attribute);
            $this->attribute->setAttribute('data_text', '');
            $this->attribute->store();
            OpendataDatasetImporterRegistry::removeScheduledImport($this->attribute->attribute('id'));
        } else {
            throw new Exception(ezpI18n::tr('opendatadataset', 'Please type %name to confirm.', null,
                ['%name' => '<em>'.ezpI18n::tr('design/standard/content/view', 'Remove').'</em>']));
        }

        return true;
    }

}
