<?php

class OpendataDatasetGoogleSqlImportHandler extends SQLIImportAbstractHandler implements ISQLIImportHandler
{
    private $done;

    public function initialize()
    {
        // TODO: Implement initialize() method.
    }

    public function getProcessLength()
    {
        return 1;
    }

    public function getNextRow()
    {
        if (!$this->done){
            $this->done = true;
            return true;
        }

        return false;
    }

    public function process($row)
    {
        $options = [];
        foreach ($this->options->attributes() as $attribute){
            $options[$attribute] = $this->options->attribute($attribute);
        }
        OpendataDatasetImporterRegistry::addPendingImport($options['attribute_id'], $options);
        return true;
    }

    public function cleanup()
    {
        // TODO: Implement cleanup() method.
    }

    public function getHandlerName()
    {
        return 'OpendataDataset Google Import Handler';
    }

    public function getHandlerIdentifier()
    {
        return 'opendatadataset_google_import';
    }

    public function getProgressionNotes()
    {
        return '';
    }

}
