<?php

use Opencontent\Google\GoogleSheet;

class OpendataDatasetSelectGoogleSpreadsheetConnector extends OpendataDatasetConnector
{
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
                'sheet_url' => [
                    'title' => 'Google Spreadsheet Uri',
                ]
            ],
        ];
    }

    protected function getOptions()
    {
        $options = parent::getOptions();
        $options['fields'] = [
            'sheet_url' => [
                "type" => "url",
            ]
        ];

        return $options;
    }

    protected function submit()
    {
        //https://docs.google.com/spreadsheets/d/12Abc4eY7cgyUpgRoYu9AIqLQ5AbJpqJLC42w0rkldLk/edit#gid=0 -> 12Abc4eY7cgyUpgRoYu9AIqLQ5AbJpqJLC42w0rkldLk
        $googleSpreadsheetParts = explode('/',
            str_replace('https://docs.google.com/spreadsheets/d/', '', $_POST['sheet_url'])
        );
        $googleSpreadsheetId = array_shift($googleSpreadsheetParts);
        if (empty($googleSpreadsheetId)){
            throw new Exception('Invalid spreadsheet url');
        }

        try {
            $sheet = new GoogleSheet($googleSpreadsheetId);
        }catch (\Google\Exception $e){
            $message = $e->getMessage();
            $decodeMessage = json_decode($message, true);
            if (is_array($decodeMessage) && isset($decodeMessage['error']['message'])){
                $message = $decodeMessage['error']['message'];
            }
            throw new Exception($message);
        }

        return $googleSpreadsheetId;
    }
}
