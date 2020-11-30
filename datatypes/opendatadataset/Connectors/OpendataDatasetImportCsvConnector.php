<?php


class OpendataDatasetImportCsvConnector extends OpendataDatasetConnector
{
    const DELAY_IMPORT_MIN_ITEMS = 200;

    protected function getData()
    {
        return null;
    }

    protected function getSchema()
    {
        return [
            'title' => ezpI18n::tr('opendatadataset', 'Import data from CSV'),
            'type' => 'object',
            'properties' => [
                'file' => [
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

        if (isset($data['file'][0])) {
            $file = $this->getUploadDir() . $data['file'][0]['name'];
            $importer = new OpendataDatasetCsvImporter($file);
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
        }

        return true;
    }

    protected function getUploadDir()
    {
        $directory = md5(\eZUser::currentUserID() . $this->attribute->attribute('id'));

        $uploadDir = eZSys::storageDirectory() . '/fileupload/' . $directory . '/';
        \eZDir::mkdir($uploadDir, false, true);

        return $uploadDir;
    }

    protected function upload()
    {
        $paramName = 'file_files';
        $options = [];
        $options['upload_dir'] = $this->getUploadDir();
        $options['download_via_php'] = true;
        $options['param_name'] = $paramName;

        $uploadHandler = new UploadHandler($options, false);
        $data = $uploadHandler->post(false);

        $files = array();
        foreach ($data[$options['param_name']] as $file) {

            $tempFileCheck = file_exists($this->getUploadDir() . $file->name);
            \eZClusterFileHandler::instance()->fileStore($this->getUploadDir() . $file->name, 'binaryfile', true, 'application/csv');

            $files[] = [
                'id' => uniqid($file->name),
                'name' => $file->name,
                'size' => $file->size,
                'url' => '#',
                'thumbnailUrl' => false,
                'deleteUrl' => false,
                'deleteType' => "GET",
                'tempFileCheck' => $tempFileCheck
            ];
        }

        return ['files' => $files];
    }

}