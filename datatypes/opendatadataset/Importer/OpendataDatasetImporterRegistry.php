<?php

class OpendataDatasetImporterRegistry
{
    const PENDING_ACTION_IMPORT_FROM_CSV = 'opendatadataset_import';

    const RUNNING_ACTION_IMPORT_FROM_CSV = 'opendatadataset_importing';

    const FAILED_ACTION_IMPORT_FROM_CSV = 'opendatadataset_fail_import';

    public static function hasPendingImport($attributeId)
    {
        $attributeId = (int)$attributeId;
        if ($attributeId === 0){
            return 0;
        }
        $actionPending = self::PENDING_ACTION_IMPORT_FROM_CSV;
        $actionRunning = self::RUNNING_ACTION_IMPORT_FROM_CSV;
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
        $actionFailed = self::FAILED_ACTION_IMPORT_FROM_CSV;

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
                            try {
                                $importer = self::createImporterFromPendingParams($params);
                                $importer->checkHeaders($datasetDefinition);
                                $importer->import($datasetDefinition, $attribute);
                                $importer->cleanup();
                                $db->query("DELETE FROM ezpending_actions WHERE id = $entryId");
                            }catch (Exception $e){
                                $params['error'] = $e->getMessage();
                                $newParams = json_encode($params);
                                $db->query("UPDATE ezpending_actions SET action = '{$actionFailed}', params = '{$newParams}' WHERE id = $entryId");
                            }
                        }
                    }
                }
            }
        }
    }

    private static function createImporterFromPendingParams($params)
    {
        if (isset($params['file'])) {
            return new OpendataDatasetCsvImporter($params['file']);
        }

        if (isset($params['spreadsheet_id'], $params['spreadsheet_title'])){
            return new OpendataDatasetGoogleSpreadsheetImporter($params['spreadsheet_id'], $params['spreadsheet_title']);
        }

        throw new Exception('Invalid params');
    }
}
