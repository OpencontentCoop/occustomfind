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
        if (empty($this->googleSpreadsheetId)) {
            throw new Exception(ezpI18n::tr('opendatadataset', 'Invalid spreadsheet url'));
        }

        try {
            $this->googleSpreadsheet = new GoogleSheet($this->googleSpreadsheetId);
        } catch (\Google\Exception $e) {
            $message = $e->getMessage();
            $decodeMessage = json_decode($message, true);
            if (is_array($decodeMessage) && isset($decodeMessage['error']['message'])) {
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
                'activate_importer' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Enable automatic update'),
                ],
                'schedule_importer' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Schedule'),
                    'type' => 'object',
                    'properties' => [
                        'frequency' => [
                            'title' => ezpI18n::tr('opendatadataset', 'Frequency'),
                            'enum' => ['daily', 'weekly', 'monthly'],
                            'default' => 'daily',
                            'required' => true,
                        ],
                        'date' => [
                            'title' => ezpI18n::tr('opendatadataset', 'Start date and time'),
                            'format' => 'datetime',
                            "default" => date('d/m/Y H:i'),
                            'required' => true,
                        ],
                    ],
                ],
                'delete' => [
                    'type' => 'boolean',
                ],
            ],
            'dependencies' => ['schedule_importer' => 'activate_importer'],
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
        $options['fields']['activate_importer'] = [
            'type' => 'checkbox',
            'rightLabel' => 'Attiva',
        ];
        $options['fields']['schedule_importer'] = [
            'fields' => [
                'frequency' => [
                    'type' => 'select',
                    'optionLabels' => [
                        ezpI18n::tr('opendatadataset', 'Daily'),
                        ezpI18n::tr('opendatadataset', 'Weekly'),
                        ezpI18n::tr('opendatadataset', 'Monthly'),
                    ],
                ],
                'date' => [
                    'type' => 'datetime',
                    "dateFormat" => "DD/MM/YYYY HH:mm",
                    "picker" => [
                        "format" => "DD/MM/YYYY HH:mm",
                        "useCurrent" => false,
                        "locale" => "it",
                    ],
                    "locale" => "it",
                ],
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

        if (isset($data['activate_importer'])) {
            $activateImporter = $data['activate_importer'] == 'true';
            OpendataDatasetImporterRegistry::addScheduledImport(
                $this->attribute->attribute('id'), [
                    'attribute_id' => $this->attribute->attribute('id'),
                    'object_id' => $this->attribute->attribute('contentobject_id'),
                    'spreadsheet_id' => $this->googleSpreadsheetId,
                    'spreadsheet_title' => $sheetTitle,
                    'user' => eZUser::currentUserID(),
                ],
                isset($data['schedule_importer']) ? $data['schedule_importer']['frequency'] : false,
                (int)$activateImporter,
                isset($data['schedule_importer']['date']) ? \DateTime::createFromFormat('d/m/Y H:i', $data['schedule_importer']['date']) : null
            );
        }

        return true;
    }

}
