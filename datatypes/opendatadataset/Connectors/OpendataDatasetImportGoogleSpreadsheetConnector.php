<?php

use Opencontent\Google\GoogleSheet;

class OpendataDatasetImportGoogleSpreadsheetConnector extends OpendataDatasetConnector
{
    const DELAY_IMPORT_MIN_ITEMS = 200;

    protected $googleSpreadsheetId;

    /**
     * @var GoogleSheet
     */
    protected $googleSpreadsheet;

    protected function load()
    {
        parent::load();
        $this->googleSpreadsheetId = $this->getHelper()->getParameter('sheet');
        if (empty($this->googleSpreadsheetId)){
            throw new Exception('Invalid spreadsheet url');
        }

        try {
            $this->googleSpreadsheet = new GoogleSheet($this->googleSpreadsheetId);
        }catch (\Google\Exception $e){
            $message = $e->getMessage();
            $decodeMessage = json_decode($message, true);
            if (is_array($decodeMessage) && isset($decodeMessage['error']['message'])){
                $message = $decodeMessage['error']['message'];
            }
            throw new Exception($message);
        }
    }

    protected function getData()
    {
        return null;
    }

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
                'delete' => [
                    'type' => 'boolean',
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
            'delete' => [
                'type' => 'checkbox',
                'rightLabel' => ezpI18n::tr('opendatadataset', 'Removing your existing data from the dataset', null,
                    ['%name' => $this->attribute->object()->attribute('name')]),
            ],
        ];

        return $options;
    }

    protected function submit()
    {
        $data = $_POST;
        $sheetTitle = $data['sheet'];
        $importer = new OpendataDatasetGoogleSpreadsheetImporter($this->googleSpreadsheetId, $sheetTitle);
        $importer->checkHeaders($this->datasetDefinition);
        if ($data['delete'] === 'true') {
            try {
                $this->datasetDefinition->truncateByCreator(eZUser::currentUserID(), $this->attribute);
            } catch (Exception $e) {

            }
        }
        if ($importer->countValues() > self::DELAY_IMPORT_MIN_ITEMS) {
            $importer->delayImport($this->attribute);
        } else {
            $importer->import($this->datasetDefinition, $this->attribute);
            $importer->cleanup();
        }

        return true;
    }

}
