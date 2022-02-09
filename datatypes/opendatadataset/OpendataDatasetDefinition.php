<?php

use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OpendataDatasetDefinition implements JsonSerializable
{
    private static $role;

    private $properties = [];
    /**
     * @var string
     */
    private $itemName = 'Item';
    /**
     * @var array
     */
    private $fields = [];
    /**
     * @var string[]
     */
    private $views = [];
    /**
     * @var bool
     */
    private $apiEnabled = false;
    /**
     * @var array
     */
    private $facetsSettings = [];
    /**
     * @var array
     */
    private $calendarSettings = [];
    /**
     * @var array
     */
    private $tableSettings = [];
    /**
     * @var array
     */
    private $chartSettings;
    /**
     * @var OpendataDatasetStorageInterface
     */
    private $storage;
    /**
     * @var bool
     */
    private $canEdit;
    /**
     * @var bool
     */
    private $canRead;
    /**
     * @var bool
     */
    private $canTruncate;
    private $extraUsers;

    public function __construct(array $properties = null)
    {
        if (!empty($properties)) {
            $this->properties = $properties;
            foreach ($properties as $property => $value) {
                if (property_exists($this, $property)) {
                    $this->$property = $value;
                }
            }
            $this->normalizeFields();
        }
    }

    public static function getReadRole()
    {
        return self::initRole('Dataset read', [[
            'ModuleName' => 'customcalendar',
            'FunctionName' => 'calendar'
        ], [
            'ModuleName' => 'customdatatable',
            'FunctionName' => 'datatable'
        ], [
            'ModuleName' => 'customexport',
            'FunctionName' => 'export'
        ], [
            'ModuleName' => 'customfind',
            'FunctionName' => 'find'
        ], [
            'ModuleName' => 'forms',
            'FunctionName' => 'use'
        ]]);
    }

    public function grantPermissions($userLists, $datasetNodeId)
    {
        $datasetNodeId = (int)$datasetNodeId;
        if ($datasetNodeId > 0) {
            $db = eZDB::instance();
            $db->begin();
            $this->revokePermissions($datasetNodeId);
            $userLists = array_column($userLists, 'id');
            foreach ($userLists as $id) {
                self::getRole()->assignToUser($id, 'subtree', $datasetNodeId);
            }
            $db->commit();
        }
    }

    private function revokePermissions($datasetNodeId)
    {
        $role = self::getRole();
        $db = eZDB::instance();
        $node = eZContentObjectTreeNode::fetch($datasetNodeId, false, false);
        $limitIdent = 'Subtree';
        $limitValue = $node['path_string'];
        $query = "DELETE FROM ezuser_role WHERE role_id='$role->ID' AND limit_identifier='$limitIdent' AND limit_value='$limitValue'";
        $db->query($query);
    }

    /**
     * @return eZRole
     */
    public static function getRole()
    {
        if (self::$role === null) {
            self::$role = self::initRole('Dataset edit', [[
                'ModuleName' => 'opendatadataset',
                'FunctionName' => 'edit'
            ], [
                'ModuleName' => 'forms',
                'FunctionName' => 'use'
            ]]);
        }

        return self::$role;
    }

    private static function initRole($name, $policies, $reset = false)
    {
        $role = eZRole::fetchByName($name);
        if ($role instanceof eZRole && $reset) {
            $role->removeThis();
            $role = false;
        }
        if (!$role instanceof eZRole) {
            $role = eZRole::create($name);
            $role->store();

            foreach ($policies as $policy) {
                $role->appendPolicy($policy['ModuleName'], $policy['FunctionName'], isset($policy['Limitation']) ? $policy['Limitation'] : array());
            }
        }
        eZCache::clearByID(array('user_info_cache'));
        return $role;
    }

    public function createDataset(OpendataDataset $dataset)
    {
        if (!$this->canEdit()) {
            throw new ForbiddenException($this->getItemName(), 'edit');
        }

        $fieldName = null;
        foreach ($dataset->getDefinition()->getFields() as $field) {
            if ($field['type'] == 'identifier') {
                $fieldName = $field['identifier'];
            }
        }
        $key = $fieldName ? md5($dataset->getData($fieldName)) : md5(json_encode($dataset->getData()));
        $dataset->setGuid($dataset->getContext()->attribute('contentclassattribute_id')
            . '_' . $dataset->getContext()->attribute('contentobject_id')
            . '_' . $key);
        $now = time();
        $dataset->setCreatedAt($now);
        $dataset->setModifiedAt($now);
        $dataset->setCreator(eZUser::currentUserID());

        return $this->getStorage()->createDataset($dataset);
    }

    /**
     * @return bool
     */
    public function canEdit()
    {
        return $this->canEdit;
    }

    /**
     * @return string
     */
    public function getItemName()
    {
        return $this->itemName;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return OpendataDatasetStorageInterface
     */
    private function getStorage()
    {
        if ($this->storage === null || !$this->storage instanceof OpendataDatasetStorageInterface) {
            $this->storage = new OpendataDatasetChainStorage([
                new OpendataDatasetDBStorage(),
                new OpendataDatasetSolrStorage()
            ]);
        }

        return $this->storage;
    }

    public function updateDataset(OpendataDataset $dataset)
    {
        if (!$this->canEdit()) {
            throw new ForbiddenException($this->getItemName(), 'edit');
        }

        if (!$this->canTruncate() && $dataset->getCreator() != eZUser::currentUserID()) {
            throw new ForbiddenException($this->getItemName(), 'edit');
        }

        $now = time();
        $dataset->setModifiedAt($now);

        return $this->getStorage()->updateDataset($dataset);
    }

    /**
     * @return bool
     */
    public function canTruncate()
    {
        return $this->canTruncate;
    }

    public function deleteDataset(OpendataDataset $dataset)
    {
        if (!$this->canDeleteDataset($dataset)) {
            throw new ForbiddenException($this->getItemName(), 'edit');
        }

        return $this->getStorage()->deleteDataset($dataset);
    }

    public function canDeleteDataset(OpendataDataset $dataset)
    {
        if (!$this->canEdit()) {
            return false;
        }

        if (!$this->canTruncate() && $dataset->getCreator() != eZUser::currentUserID()) {
            return false;
        }

        return true;
    }

    public function truncate($context)
    {
        if (!$this->canTruncate()) {
            throw new ForbiddenException($this->getItemName(), 'edit');
        }

        return $this->getStorage()->truncate($context);
    }

    public function truncateByCreator($creatorId, $context)
    {
        if (!$this->canEdit()) {
            throw new ForbiddenException($this->getItemName(), 'edit');
        }

        return $this->getStorage()->deleteByCreator(eZUser::currentUserID(), $context);
    }

    public function getDataset($guid, $context)
    {
        if (!$this->canRead()) {
            throw new ForbiddenException($this->getItemName(), 'read');
        }

        return $this->getStorage()->getDataset($guid, $context);
    }

    /**
     * @return bool
     */
    public function canRead()
    {
        return $this->canRead;
    }

    public function hasAttribute($key)
    {
        return in_array($key, $this->attributes());
    }

    public function attributes()
    {
        return [
            'is_api_enabled',
            'item_name',
            'views',
            'fields',
            'settings',
            'can_edit',
            'can_truncate',
        ];
    }

    public function attribute($property)
    {
        switch ($property) {
            case 'is_api_enabled':
                return $this->isApiEnabled();

            case 'item_name':
                return $this->getItemName();

            case 'views':
                return $this->getViews();

            case 'fields':
                return $this->getFields();

            case 'settings':
                return [
                    'facets' => (array)$this->getFacetsSettings(),
                    'calendar' => (array)$this->getCalendarSettings(),
                    'table' => (array)$this->getTableSettings(),
                    'chart' => $this->getChartSettings(),
                ];

            case 'can_edit':
                return $this->canEdit();

            case 'can_truncate':
                return $this->canTruncate();
        }

        eZDebug::writeNotice("Attribute $property does not exist", get_called_class());

        return false;
    }

    /**
     * @return bool
     */
    public function isApiEnabled()
    {
        return $this->apiEnabled === 'true' || $this->apiEnabled === true;
    }

    /**
     * @return string[]
     */
    public function getViews()
    {
        return $this->views;
    }

    /**
     * @return array
     */
    public function getFacetsSettings()
    {
        return $this->facetsSettings;
    }

    /**
     * @return array
     */
    public function getCalendarSettings()
    {
        return $this->calendarSettings;
    }

    /**
     * @return array
     */
    public function getTableSettings()
    {
        return $this->tableSettings;
    }

    /**
     * @return array
     */
    public function getChartSettings()
    {
        return $this->chartSettings;
    }

    /**
     * @param bool $canEdit
     */
    public function setCanEdit($canEdit)
    {
        $this->canEdit = $canEdit;
    }

    public function canEditDataset(OpendataDataset $dataset)
    {
        if (!$this->canEdit()) {
            return false;
        }

        if (!$this->canTruncate() && $dataset->getCreator() != eZUser::currentUserID()) {
            return false;
        }

        return true;
    }

    /**
     * @param bool $canRead
     */
    public function setCanRead($canRead)
    {
        $this->canRead = $canRead;
    }

    /**
     * @param bool $canTruncate
     */
    public function setCanTruncate($canTruncate)
    {
        $this->canTruncate = $canTruncate;
    }

    public function jsonSerialize()
    {
        return [
            'apiEnabled' => $this->isApiEnabled(),
            'extraUsers' => $this->getExtraUsers(),
            'itemName' => $this->getItemName(),
            'views' => $this->getViews(),
            'fields' => $this->getFields(),
            'facetsSettings' => $this->getFacetsSettings(),
            'calendarSettings' => $this->getCalendarSettings(),
            'tableSettings' => $this->getTableSettings(),
            'chartSettings' => $this->getChartSettings(),
        ];
    }

    /**
     * @return mixed
     */
    public function getExtraUsers()
    {
        if (is_array($this->extraUsers)) {
            foreach ($this->extraUsers as $index => $extraUser) {
                $user = eZUser::fetch((int)$extraUser['id']);
                if ($user instanceof eZUser) {
                    if (!isset($extraUser['name'])) {
                        $this->extraUsers[$index]['name'] = $user->attribute('login');
                    }
                } else {
                    unset($this->extraUsers[$index]);
                }
            }
        }
        return $this->extraUsers;
    }

    /**
     * @param $properties
     * @return OpendataDatasetDefinition
     */
    public function merge($properties)
    {
        $properties = array_merge($this->properties, $properties);

        return new self($properties);
    }

    /**
     * @param array $data
     * @param eZContentObjectAttribute $context
     * @return OpendataDataset
     */
    public function create(array $data, $context)
    {
        foreach ($this->getFields() as $field) {
            if ($field['required'] === 'true' && !isset($data[$field['identifier']])) {
                throw new InvalidArgumentException("Field {$field['identifier']} is required");
            }
            if ($field['type'] == 'checkbox' && !empty($data[$field['identifier']])) {
                $data[$field['identifier']] = explode(",", $data[$field['identifier']]);
            }
            if (!empty($field['enum'])) {
                $enum = OpendataDatasetDefinition::parseEnumConfiguration($field['enum']);
                if (!empty(array_diff($data[$field['identifier']], $enum))) {
                    throw new InvalidArgumentException("Invalid data in field {$field['identifier']}");
                }
            }
        }
        return new OpendataDataset($data, $context, $this);
    }

    public static function parseEnumConfiguration($enum)
    {
        return explode("\n", $enum);
    }

    /**
     * @param $definition array Geo field definition
     * @param $data string Source string value
     * @return array
     */
    public static function explodeGeoValue(array $definition, $data)
    {
        $separator = isset($definition['geo_separator']) ? $definition['geo_separator'] : '|';
        [$longitude, $latitude] = explode($separator, $data, 2);
        return [
            'longitude' => $longitude,
            'latitude' => $latitude,
        ];
    }

    /**
     * @param $definition array Geo field definition
     * @param $data array Exploded geo value
     * @return string
     */
    public static function implodeGeoValue(array $definition, array $data)
    {
        $separator = isset($definition['geo_separator']) ? $definition['geo_separator'] : '|';
        return implode($separator, [
            $data['longitude'],
            $data['latitude'],
        ]);
    }

    /**
     * @param $definition array Geo field definition
     * @param $data string Source string value
     * @return string
     */
    public static function formatGeoValueToSolr(array $definition, $data)
    {
        $separator = isset($definition['geo_separator']) ? $definition['geo_separator'] : '|';
        [$longitude, $latitude] = explode($separator, $data);
        return $longitude . ',' . $latitude;
    }

    private function normalizeFields()
    {
        if (is_array($this->fields)){
            foreach ($this->fields as $index => $field){
                if (is_numeric($field['identifier'])) {
                    $this->fields[$index]['identifier'] = 'col_' . $field['identifier'];
                }
                $this->fields[$index]['js_label'] = str_replace(
                    ['"', "'"],
                    ["&quot;", "&apos;"],
                    htmlspecialchars($this->fields[$index]['label'])
                );
            }
        }
    }
}
