<?php

use Opencontent\Google\GoogleSheet;

class OpendataDatasetGoogleSpreadsheetImporter extends OpendataDatasetAbstractImporter
{
    private $spreadsheetId;

    private $sheetTitle;

    private $spreadsheet;

    /**
     * @var false
     */
    private $deleteExistingData;

    public function __construct($spreadsheetId, $sheetTitle, $deleteExistingData = false)
    {
        $this->spreadsheetId = $spreadsheetId;
        $this->sheetTitle = $sheetTitle;

        $this->spreadsheet = new GoogleSheet($this->spreadsheetId);
        $this->deleteExistingData = $deleteExistingData;
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
            'delete_before' => $this->deleteExistingData,
        ]);
    }

    public function import(OpendataDatasetDefinition $definition, $context)
    {
        if ($this->deleteExistingData) {
            try {
                $definition->truncate($context);
            } catch (Exception $e) {
                eZDebug::writeError($e->getMessage(), __METHOD__);
            }
        }
        parent::import($definition, $context);
    }


}
