<?php


class OpendataDatasetCsvImporter
{
    const PENDING_ACTION_IMPORT_FROM_CSV = 'opendatadataset_import';

    const RUNNING_ACTION_IMPORT_FROM_CSV = 'opendatadataset_importing';

    private $file;

    private $fileHandler;

    private $isParsed = false;

    private $headers = [];

    private $values = [];

    public function __construct($file)
    {
        $this->fileHandler = eZClusterFileHandler::instance($file);
        $this->fileHandler->fetch();

        $this->file = $file;
    }

    public static function hasPendingImport($attributeId)
    {
        $attributeId = (int)$attributeId;
        if ($attributeId === 0){
            return 0;
        }
        $actionPending = OpendataDatasetCsvImporter::PENDING_ACTION_IMPORT_FROM_CSV;
        $actionRunning = OpendataDatasetCsvImporter::RUNNING_ACTION_IMPORT_FROM_CSV;
        $data = eZDB::instance()->arrayQuery("SELECT COUNT(*) AS count FROM ezpending_actions WHERE action IN ('{$actionPending}', '{$actionRunning}') AND param LIKE '%\"attribute_id\":\"{$attributeId}\"%'");

        return (int)$data[0]['count'];
    }

    public static function executePendingImports($attributeId = null)
    {
        $db = eZDB::instance();
        $offset = 0;
        $limit = 50;
        $actionPending = self::PENDING_ACTION_IMPORT_FROM_CSV;
        $andWhere = '';
        if ($attributeId) {
            $andWhere = "AND param LIKE '%\"attribute_id\":\"{$attributeId}\"%'";
        }
        $entries = $db->arrayQuery(
            "SELECT * FROM ezpending_actions WHERE action = '{$actionPending}' $andWhere ORDER BY created",
            ['limit' => $limit, 'offset' => $offset]
        );
        if (is_array($entries) && count($entries) > 0) {
            foreach ($entries as $entry) {
                self::executePendingAction($entry);
            }
        }
    }

    private static function executePendingAction(array $entry)
    {
        $db = eZDB::instance();
        $actionRunning = self::RUNNING_ACTION_IMPORT_FROM_CSV;

        $entryId = (int)$entry['id'];
        $db->query("UPDATE ezpending_actions SET action = '{$actionRunning}' WHERE id = $entryId");

        $params = json_decode($entry['param'], true);
        $user = eZUser::fetch((int)$params['user']);
        if ($user instanceof eZUser) {
            eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));
            $object = eZContentObject::fetch((int)$params['object_id']);
            if ($object instanceof eZContentObject) {
                foreach ($object->dataMap() as $attribute) {
                    if ($attribute->attribute('id') == (int)$params['attribute_id']) {
                        $datasetDefinition = $attribute->content();
                        if ($datasetDefinition instanceof OpendataDatasetDefinition) {
                            $file = $params['file'];
                            try {
                                $importer = new self($file);
                                $importer->checkHeaders($datasetDefinition);
                                $importer->import($datasetDefinition, $attribute);
                                $importer->cleanup();
                                $db->query("DELETE FROM ezpending_actions WHERE id = $entryId");
                            }catch (Exception $e){
                                $params['error'] = $e->getMessage();
                                $newParams = json_encode($params);
                                $db->query("UPDATE ezpending_actions SET params = '{$newParams}' WHERE id = $entryId");
                            }
                        }
                    }
                }
            }
        }
    }

    public function checkHeaders(OpendataDatasetDefinition $definition)
    {
        $this->parse();
        $atLeastOne = false;
        foreach ($definition->getFields() as $field) {
            if ($field['required'] == "true" && !in_array($field['label'], $this->getHeaders()) && !in_array($field['identifier'], $this->getHeaders())) {
                throw new Exception($field['label'] . ' is required');
            }
            if (in_array($field['label'], $this->getHeaders()) || in_array($field['identifier'], $this->getHeaders())) {
                $atLeastOne = true;
            }
        }
        if (!$atLeastOne) {
            throw new Exception('Invalid csv headers');
        }
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        $this->parse();
        return $this->headers;
    }

    public function import(OpendataDatasetDefinition $definition, $context)
    {
        $this->parse();
        $identifierAndLabels = [];
        foreach ($definition->getFields() as $field) {
            $identifierAndLabels[$field['identifier']] = $field['label'];
        }

        foreach ($this->values as $row) {
            $item = [];
            foreach ($row as $key => $value) {
                if (in_array($key, $identifierAndLabels)) {
                    foreach ($identifierAndLabels as $identifier => $label) {
                        if ($label == $key) {
                            $item[$identifier] = $value;
                        }
                    }
                }
            }
            $dataset = $definition->create($item, $context);
            $definition->createDataset($dataset);
        }
    }

    public function cleanup()
    {
        $this->fileHandler->deleteLocal();
        $this->fileHandler->delete();
        $this->fileHandler->purge();
    }

    public function createDefinition()
    {
        $firstRow = $this->getValues(0);
        $fields = [];
        $index = 0;
        foreach ($firstRow as $header => $value) {
            $identifier = eZCharTransform::instance()->transformByGroup($header, 'identifier');
            if (empty($identifier)) {
                $identifier = 'header-' . $index;
            }
            $field = [
                'identifier' => $identifier,
                'label' => $header,
                'type' => 'string',
                'required' => 'false',
            ];

//            if (is_integer($value)) {
//                $field['type'] = 'integer';
//            } elseif (is_numeric($value)) {
//                $field['type'] = 'number';
//            }

            if (substr_count($value, '/') == 2) {
                $field['type'] = 'date';
                $field['date_format'] = 'DD/MM/YYYY';
            }

            if (mb_strlen($value) > 50) {
                $field['type'] = 'textarea';
            }
            $fields[] = $field;
            $index++;
        }

        return new OpendataDatasetDefinition(['fields' => $fields]);
    }

    /**
     * @param null $index
     * @return array
     * @throws Exception
     */
    public function getValues($index = null)
    {
        $this->parse();
        if ($index !== null) {
            return $this->values[$index];
        }

        return $this->values;
    }

    private function parse()
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

    public function countValues()
    {
        $this->parse();
        return count($this->values);
    }

    public function delayImport(eZContentObjectAttribute $attribute)
    {
        $pendingAction = new eZPendingActions([
            'action' => self::PENDING_ACTION_IMPORT_FROM_CSV,
            'created' => time(),
            'param' => json_encode([
                'attribute_id' => $attribute->attribute('id'),
                'object_id' => $attribute->attribute('contentobject_id'),
                'file' => $this->file,
                'user' => eZUser::currentUserID(),
            ])
        ]);
        $pendingAction->store();
        exec('sh extension/occustomfind/bin/bash/opendatadataset_import_pending.sh ' . eZSiteAccess::current()['name'] . ' ' . $attribute->attribute('id'));
    }
}