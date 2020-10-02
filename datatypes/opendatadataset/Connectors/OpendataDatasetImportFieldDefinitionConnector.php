<?php


class OpendataDatasetImportFieldDefinitionConnector extends OpendataDatasetImportCsvConnector
{
    protected function getSchema()
    {
        return [
            'title' => ezpI18n::tr('opendatadataset', 'Import definitions from CSV'),
            'type' => 'object',
            'properties' => [
                'file' => [
                ],
            ],
        ];
    }

    protected function getOptions()
    {
        $options = parent::getOptions();
        $options['fields'] = [
            'file' => [
                "type" => "upload",
                "upload" => array(
                    "url" => $this->getHelper()->getServiceUrl('upload', $this->getHelper()->getParameters()),
                    "autoUpload" => true,
                    "showSubmitButton" => false,
                    "disableImagePreview" => true,
                    "maxFileSize" => 25000000, //@todo,
                    "maxNumberOfFiles" => 1,
                ),
                "showUploadPreview" => false,
                "maxNumberOfFiles" => 1,
                "multiple" => false
            ],
        ];

        return $options;
    }

    protected function submit()
    {
        $data = $_POST;

        if (isset($data['file'][0])) {
            $file = $this->getUploadDir() . $data['file'][0]['name'];
            $importer = new OpendataDatasetCsvImporter($file);
            $definition = $importer->createDefinition();
            $this->attribute->setAttribute('data_text', json_encode($definition));
            $this->attribute->store();
        }

        return true;
    }
}