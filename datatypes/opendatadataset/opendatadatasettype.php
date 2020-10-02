<?php

class OpendataDatasetType extends eZDataType
{
    const DATA_TYPE_STRING = "opendatadataset";

    public function __construct()
    {
        parent::__construct(self::DATA_TYPE_STRING, ezpI18n::tr('opendatadataset', "CSV Dataset", 'Datatype name'), [
            'serialize_supported' => true,
            'object_serialize_map' => ['data_text' => 'text']
        ]);
    }

    public static function getTypes()
    {
        return [
            [
                'label' => ezpI18n::tr('opendatadataset', 'Identifier', "CSV Dataset"),
                'identifier' => 'identifier',
                'schema' => [
                    'type' => 'string',
                ],
            ],
            [
                'label' => ezpI18n::tr('opendatadataset', 'String', "CSV Dataset"),
                'identifier' => 'string',
                'schema' => [
                    'type' => 'string',
                ],
            ],
            [
                'label' => ezpI18n::tr('opendatadataset', 'Integer', "CSV Dataset"),
                'identifier' => 'integer',
                'schema' => [
                    'type' => 'integer',
                ],
            ],
            [
                'label' => ezpI18n::tr('opendatadataset', 'Number', "CSV Dataset"),
                'identifier' => 'number',
                'schema' => [
                    'type' => 'number',
                ],
            ],
            [
                'label' => ezpI18n::tr('opendatadataset', 'Textarea', "CSV Dataset"),
                'identifier' => 'textarea',
                'schema' => [
                    'type' => 'string',
                ],
                'options' => [
                    'type' => 'textarea',
                ],
            ],
            [
                'label' => ezpI18n::tr('opendatadataset', 'Select', "CSV Dataset"),
                'identifier' => 'select',
                'schema' => [
                    'type' => 'string',
                ],
                'options' => [
                    'hideInitValidationError' => true,
                ],
            ],
            [
                'label' => ezpI18n::tr('opendatadataset', 'Checkbox', "CSV Dataset"),
                'identifier' => 'checkbox',
                'schema' => [
                    'type' => 'string',
                ],
                'options' => [
                    'type' => 'checkbox',
                ],
            ],
            [
                'label' => ezpI18n::tr('opendatadataset', 'Date', "CSV Dataset"),
                'identifier' => 'date',
                'schema' => [
                    'type' => 'string',
                    'format' => 'date',
                ],
                'options' => [
                    'type' => 'date',
                    'dateFormat' => 'DD/MM/YYYY',
                    'locale' => 'it',
                ],
            ],
            [
                'label' => ezpI18n::tr('opendatadataset', 'Date and time', "CSV Dataset"),
                'identifier' => 'datetime',
                'schema' => [
                    'type' => 'string',
                    'format' => 'datetime',
                ],
                'options' => [
                    'type' => 'datetime',
                    'dateFormat' => 'DD/MM/YYYY HH:mm',
                    'picker' => array(
                        'format' => 'DD/MM/YYYY HH:mm',
                        'useCurrent' => false,
                        'locale' => 'it',
                    ),
                    'locale' => 'it',
                ],
            ],
//            [
//                'label' => ezpI18n::tr('opendatadataset', 'Geo location', "CSV Dataset"),
//                'identifier' => 'geo',
//                'schema' => [
//                    'type' => 'string',
//                ],
//                'options' => [
//                    'type' => 'openstreetmap',
//                    'i18n' => [
//                        'address' => \ezpI18n::tr('opendata_forms', 'Address'),
//                        'latitude' => \ezpI18n::tr('opendata_forms', 'Latitude'),
//                        'longitude' => \ezpI18n::tr('opendata_forms', 'Longitude'),
//                        'noResultsFinding' => \ezpI18n::tr('opendata_forms', 'No results finding'),
//                        'tryToRefineYourSearch' => \ezpI18n::tr('opendata_forms', 'try to refine your search keywords'),
//                    ]
//                ],
//            ],
        ];
    }

    public static function getViews()
    {
        return [
            'calendar' => ezpI18n::tr('opendatadataset', 'Calendar', "CSV Dataset"),
            //'map' => ezpI18n::tr('opendatadataset', 'Map', "CSV Dataset"),
            'chart' => ezpI18n::tr('opendatadataset', 'Chart', "CSV Dataset"),
            'table' => ezpI18n::tr('opendatadataset', 'Data table', "CSV Dataset"),
        ];
    }

    function classAttributeContent( $classAttribute )
    {
        return [
            'types' => self::getTypes(),
            'views' => self::getViews(),
        ];
    }

    function initializeObjectAttribute($contentObjectAttribute, $currentVersion, $originalContentObjectAttribute)
    {
        if ($currentVersion != false) {
            $dataText = $originalContentObjectAttribute->attribute("data_text");
            $contentObjectAttribute->setAttribute("data_text", $dataText);
        }
    }

    function validateObjectAttributeHTTPInput($http, $base, $contentObjectAttribute)
    {
        if (!$http->hasPostVariable($base . '_data_text_' . $contentObjectAttribute->attribute('id')) && $contentObjectAttribute->validateIsRequired()) {
            $contentObjectAttribute->setValidationError(ezpI18n::tr('kernel/classes/datatypes', 'Input required.'));
            return eZInputValidator::STATE_INVALID;
        }

        return eZInputValidator::STATE_ACCEPTED;
    }

    function isSimpleStringInsertionSupported()
    {
        return true;
    }

    function insertSimpleString($object, $objectVersion, $objectLanguage, $objectAttribute, $string, &$result)
    {
        $result = [
            'errors' => [],
            'require_storage' => true
        ];
        $objectAttribute->setAttribute('data_text', $string);

        return true;
    }

    function objectAttributeContent($contentObjectAttribute)
    {
        $dataset = new OpendataDatasetDefinition(json_decode($contentObjectAttribute->attribute("data_text"), true));
        $dataset->setCanEdit($this->canEdit($contentObjectAttribute));
        $dataset->setCanRead($contentObjectAttribute->attribute('object')->canRead());
        $dataset->setCanTruncate($contentObjectAttribute->attribute('object')->canEdit());

        return $dataset;
    }

    private function canEdit($contentObjectAttribute)
    {
        if ($contentObjectAttribute->attribute('object')->canEdit()) {
            return true;
        }

        $datasetNodeId = (int)$contentObjectAttribute->object()->mainNodeID();
        if ($datasetNodeId) {
            $role = OpendataDatasetDefinition::getRole();
            $userID = eZUser::currentUserID();
            $node = eZContentObjectTreeNode::fetch($datasetNodeId, false, false);
            $limitIdent = 'Subtree';
            $limitValue = $node['path_string'];
            $query = "SELECT * FROM ezuser_role WHERE role_id='$role->ID' AND contentobject_id='$userID' AND limit_identifier='$limitIdent' AND limit_value='$limitValue'";
            $rows = eZDB::instance()->arrayQuery($query);
            if (count($rows) > 0) {
                return true;
            }
        }

        return false;
    }

    function toString($contentObjectAttribute)
    {
        return $contentObjectAttribute->attribute('data_text');
    }

    function fromString($contentObjectAttribute, $string)
    {
        return $contentObjectAttribute->setAttribute('data_text', $string);
    }

    function hasObjectAttributeContent($contentObjectAttribute)
    {
        return trim($contentObjectAttribute->attribute('data_text')) != '';
    }

    function title($objectAttribute, $name = null)
    {
        return $objectAttribute->attribute("data_text");
    }

    function isIndexable()
    {
        return false;
    }

    function isInformationCollector()
    {
        return false;
    }

    function supportsBatchInitializeObjectAttribute()
    {
        return true;
    }

    /**
     * @param eZContentObjectAttribute $contentObjectAttribute
     * @param null $version
     */
    function deleteStoredObjectAttribute($contentObjectAttribute, $version = null)
    {
        if ($version == null) {
            $definition = $this->objectAttributeContent($contentObjectAttribute);
            try {
                $definition->truncate($contentObjectAttribute);
            }catch (Exception $e){
                eZDebug::writeError($e->getMessage(), __METHOD__);
            }
        }
    }
}

eZDataType::register(OpendataDatasetType::DATA_TYPE_STRING, "OpendataDatasetType");