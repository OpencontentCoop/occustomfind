<?php

use Opencontent\Google\GoogleSheet;

class OpendataDatasetGoogleSpreadsheetImporter extends OpendataDatasetAbstractImporter
{
    private $spreadsheetId;

    private $sheetTitle;

    private $spreadsheet;

    public function __construct($spreadsheetId, $sheetTitle)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->sheetTitle = $sheetTitle;

        $this->spreadsheet = new GoogleSheet($this->spreadsheetId);
    }

    public function cleanup()
    {
    }

    protected function parse()
    {
        if (!$this->isParsed) {
            $this->values = $this->spreadsheet->getSheetDataHash($this->sheetTitle);
            $this->headers = array_keys($this->values[0]);
            $this->isParsed = true;
        }
    }


    public function delayImport(eZContentObjectAttribute $attribute)
    {
        OpendataDatasetImporterRegistry::addPendingImport($attribute->attribute('id'), [
            'attribute_id' => $attribute->attribute('id'),
            'object_id' => $attribute->attribute('contentobject_id'),
            'spreadsheet_id' => $this->spreadsheetId,
            'spreadsheet_title' => $this->sheetTitle,
            'user' => eZUser::currentUserID(),
        ]);
    }

}
