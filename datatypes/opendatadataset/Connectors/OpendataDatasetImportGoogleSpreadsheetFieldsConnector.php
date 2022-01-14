<?php

class OpendataDatasetImportGoogleSpreadsheetFieldsConnector extends OpendataDatasetImportGoogleSpreadsheetConnector
{
    protected function getSchema()
    {
        return [
            'title' => ezpI18n::tr('opendatadataset', 'Import from Google Sheet'),
            'type' => 'object',
            'properties' => [
                'sheet' => [
                    'title' => 'Select sheet',
                    'enum' => $this->googleSpreadsheet->getSheetTitleList(),
                    'required' => true,
                ],
            ],
        ];
    }

    protected function getOptions()
    {
        $options = parent::getOptions();
        $options['fields'] = [
            'sheet_url' => [
                "type" => "select",
            ],
        ];

        return $options;
    }

    protected function submit()
    {
        $data = $_POST;
        $sheetTitle = $data['sheet'];
        $importer = new OpendataDatasetGoogleSpreadsheetImporter($this->googleSpreadsheetId, $sheetTitle);
        $definition = $importer->createDefinition();
        $this->attribute->setAttribute('data_text', json_encode($definition));
        $this->attribute->store();

        return true;
    }

}
