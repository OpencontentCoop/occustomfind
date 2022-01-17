<?php

class OpendataDatasetImporterRegistry
{
    const PENDING_ACTION_IMPORT_FROM_CSV = 'opendatadataset_import';

    const RUNNING_ACTION_IMPORT_FROM_CSV = 'opendatadataset_importing';

    const FAILED_ACTION_IMPORT_FROM_CSV = 'opendatadataset_fail_import';

    const SCHEDULED_HANDLER = 'opendatadataset_google_import';

    public static function addPendingImport($attributeId, array $params)
    {
        $pendingAction = new eZPendingActions([
            'action' => OpendataDatasetImporterRegistry::PENDING_ACTION_IMPORT_FROM_CSV,
            'created' => time(),
            'param' => json_encode($params),
        ]);
        $attributeId = (int)$attributeId;
        $actionPending = self::PENDING_ACTION_IMPORT_FROM_CSV;
        $actionRunning = self::RUNNING_ACTION_IMPORT_FROM_CSV;
        $actionFailed = self::FAILED_ACTION_IMPORT_FROM_CSV;
        eZDB::instance()->arrayQuery("DELETE FROM ezpending_actions WHERE action IN ('{$actionPending}', '{$actionRunning}', '{$actionFailed}') AND param LIKE '%\"attribute_id\":\"{$attributeId}\"%'");
        $pendingAction->store();
        exec('sh extension/occustomfind/bin/bash/opendatadataset_import_pending.sh ' . eZSiteAccess::current()['name'] . ' ' . $attributeId);
    }

    public static function fetchScheduledImport($attributeId)
    {
        $handler = self::SCHEDULED_HANDLER;
        $optionsLike = 's:12:"attribute_id";s:' . strlen($attributeId) . ':"' . $attributeId . '";';
        $data = eZDB::instance()->arrayQuery("SELECT * FROM sqliimport_scheduled WHERE handler = '$handler' AND options_serialized LIKE '%$optionsLike%'");

        if (!empty($data)) {
            $results = eZPersistentObject::handleRows($data, 'SQLIScheduledImport', true);
            return $results[0];
        }

        return false;
    }

    public static function hasScheduledImport($attributeId)
    {
        $data = self::fetchScheduledImport($attributeId);
        if (!$data){
            return ['scheduled' => 0];
        }
        return ['scheduled' => 1];
    }

    public static function removeScheduledImport($attributeId)
    {
        $data = self::fetchScheduledImport($attributeId);
        if ($data instanceof SQLIScheduledImport){
            $options = $data->getOptions();
            $data->remove();
            return $options->object_id;
        }

        return false;
    }

    public static function addScheduledImport($attributeId, $options, $frequency, $nextDateTime)
    {
        self::removeScheduledImport($attributeId);
        $scheduledImport = new SQLIScheduledImport([
            'handler' => self::SCHEDULED_HANDLER,
            'user_id' => eZUser::currentUserID(),
            'label' => ezpI18n::tr('opendatadataset', 'Import from Google Sheet'),
            'is_active' => 1,
        ]);
        $scheduledImport->setAttribute('options', new SQLIImportHandlerOptions($options));
        $scheduledImport->setAttribute('label', ezpI18n::tr('opendatadataset', 'Import from Google Sheet'));
        if ($frequency){
            $scheduledImport->setAttribute('frequency', $frequency);
            $nextDate = ($nextDateTime instanceof DateTime) ? $nextDateTime->format('U') : time();
            $scheduledImport->setAttribute('next', $nextDate);
        }
        $scheduledImport->store();
    }

    public static function hasPendingImport($attributeId)
    {
        $attributeId = (int)$attributeId;
        if ($attributeId === 0) {
            return 0;
        }
        $actionPending = self::PENDING_ACTION_IMPORT_FROM_CSV;
        $actionRunning = self::RUNNING_ACTION_IMPORT_FROM_CSV;
        $actionFailed = self::FAILED_ACTION_IMPORT_FROM_CSV;
        $data = eZDB::instance()->arrayQuery("SELECT COUNT(*) AS count FROM ezpending_actions WHERE action IN ('{$actionPending}', '{$actionRunning}') AND param LIKE '%\"attribute_id\":\"{$attributeId}\"%'");
        $pendingCount = (int)$data[0]['count'];
        $data = eZDB::instance()->arrayQuery("SELECT * FROM ezpending_actions WHERE action = '$actionFailed' AND param LIKE '%\"attribute_id\":\"{$attributeId}\"%' ORDER BY created desc LIMIT 1");
        $message = false;
        $failedCount = count($data);
        if ($failedCount > 0) {
            $failParam = json_decode($data[0]['param'], true);
            if (isset($failParam['error'])) {
                $message = $failParam['error'];
            }
        }
        return [
            'pending' => $pendingCount,
            'failed' => $failedCount,
            'message' => $message,
        ];
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
                            } catch (Exception $e) {
                                $params['error'] = $e->getMessage();
                                $newParams = json_encode($params);
                                $db->query("UPDATE ezpending_actions SET action = '{$actionFailed}', param = '{$newParams}' WHERE id = $entryId");
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

        if (isset($params['spreadsheet_id'], $params['spreadsheet_title'])) {
            return new OpendataDatasetGoogleSpreadsheetImporter($params['spreadsheet_id'], $params['spreadsheet_title']);
        }

        throw new Exception('Invalid params');
    }
}
