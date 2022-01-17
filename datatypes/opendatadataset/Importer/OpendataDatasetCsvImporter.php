<?php


class OpendataDatasetCsvImporter extends OpendataDatasetAbstractImporter
{
    private $file;

    private $fileHandler;

    public function __construct($file)
    {
        $this->fileHandler = eZClusterFileHandler::instance($file);
        $this->fileHandler->fetch();

        $this->file = $file;
    }

    public function cleanup()
    {
        $this->fileHandler->deleteLocal();
        $this->fileHandler->delete();
        $this->fileHandler->purge();
    }

    protected function parse()
    {
        if (!$this->isParsed) {
            $row = 1;
            if (($handle = fopen($this->file, "r")) !== false) {
                while (($data = fgetcsv($handle, 100000, ",")) !== false) {
                    if ($row === 1) {
                        $this->headers = $data;
                    } else {
                        $value = [];
                        for ($j = 0, $jMax = count($this->headers); $j < $jMax; ++$j) {
                            $value[$this->headers[$j]] = $data[$j];
                        }
                        $this->values[] = $value;
                    }
                    $row++;
                }
                fclose($handle);
                $this->isParsed = true;
            } else {
                throw new Exception("File not found");
            }
        }
    }

    public function delayImport(eZContentObjectAttribute $attribute)
    {
        OpendataDatasetImporterRegistry::addPendingImport($attribute->attribute('id'), [
            'attribute_id' => $attribute->attribute('id'),
            'object_id' => $attribute->attribute('contentobject_id'),
            'file' => $this->file,
            'user' => eZUser::currentUserID(),
        ]);
    }
}
