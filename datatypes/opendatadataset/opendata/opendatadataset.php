<?php

use Opencontent\Opendata\Api\AttributeConverter\Base;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;

class OpendataDatasetAttributeConverter extends Base
{
    protected static $availableTypes = [];

    protected static $availableViews = [];

    public function __construct($classIdentifier, $identifier)
    {
        parent::__construct($classIdentifier, $identifier);

        self::$availableTypes = OpendataDatasetType::getTypes();
        self::$availableViews = array_keys(OpendataDatasetType::getViews());
    }

    public function get(eZContentObjectAttribute $attribute)
    {
        $data = parent::get($attribute);
        $data['content'] = json_decode($data['content'], true);
        if (isset($data['content']['chartSettings'])){
            $data['content']['chartSettings'] = json_decode($data['content']['chartSettings'], true);
        }
        return $data;
    }

    public function set($data, PublicationProcess $process)
    {
        $definition = new OpendataDatasetDefinition($data);

        return json_encode($definition);
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if (!is_array($data) && !empty($data)){
            throw new InvalidInputException('Invalid type', $identifier, $data);
        }
        if (isset($data['chartSettings'])){
            $data['chartSettings'] = json_encode($data['chartSettings']);
        }

        if (!isset($data['itemName']) || empty($data['itemName'])){
            throw new InvalidInputException('itemName is required', $identifier, $data);
        }

        if (!isset($data['fields']) || empty($data['fields']) || !is_array($data['fields'])){
            throw new InvalidInputException('Can not set empty fields', $identifier, $data);
        }
        $fieldErrors = [];
        $fields = [];
        foreach ($data['fields'] as $field){
            try {
                self::validateField($field);
                $fields[] = $field['identifier'];
            }catch (Exception $e){
                $fieldErrors[] = $e->getMessage();
            }
        }
        if (!empty($fieldErrors)) {
            throw new InvalidInputException(implode(', ', $fieldErrors), $identifier, $data);
        }

        if (isset($data['apiEnabled']) && !is_bool($data['apiEnabled'])){
            throw new InvalidInputException('apiEnabled value must be a boolean', $identifier, $data);
        }
        if (isset($data['extraUsers']) && !is_array($data['extraUsers'])){
            throw new InvalidInputException('extraUsers value must be an array', $identifier, $data);
        }
        if (isset($data['extraUsers'])){
            foreach ($data['extraUsers'] as $extraUser){
                if (!isset($extraUser['id'])){
                    throw new InvalidInputException('Missing extraUsers.id', $identifier, $data);
                }
            }
        }

        if (isset($data['facetsSettings']) && !is_array($data['facetsSettings'])){
            throw new InvalidInputException('facetsSettings value must be an array', $identifier, $data);
        }
        if (isset($data['facetsSettings'])) {
            $wrongFacets = array_diff($data['facetsSettings'], $fields);
            if (!empty($wrongFacets)) {
                throw new InvalidInputException('Invalid facets ' . implode(',', $wrongFacets), $identifier, $data);
            }
        }

        if (isset($data['views']) && !is_array($data['views'])){
            throw new InvalidInputException('views value must be an array', $identifier, $data);
        }
        if (isset($data['views'])) {
            $wrongViews = array_diff($data['views'], self::$availableViews);
            if (!empty($wrongViews)) {
                throw new InvalidInputException('Invalid views ' . implode(',', $wrongViews), $identifier, $data);
            }
        }
        if (in_array('calendar', $data['views'])){
            if (!isset($data['calendarSettings']['default_view']) || empty($data['calendarSettings']['default_view'])){
                throw new InvalidInputException('Missing calendarSettings.default_view', $identifier, $data);
            }
            if (isset($data['calendarSettings']['default_view'])
                && !in_array($data['calendarSettings']['default_view'], ['dayGridDay', 'dayGridWeek', 'dayGridMonth'])){
                throw new InvalidInputException('Invalid default_view ' . $data['calendarSettings']['default_view'], $identifier, $data);
            }
            if (!isset($data['calendarSettings']['start_date_field'])){
                throw new InvalidInputException('Missing calendarSettings.start_date_field', $identifier, $data);
            }
            if (isset($data['calendarSettings']['start_date_field']) && !in_array($data['calendarSettings']['start_date_field'], $fields)){
                throw new InvalidInputException('Invalid calendarSettings.start_date_field', $identifier, $data);
            }
            if (isset($data['calendarSettings']['end_date_field']) && !in_array($data['calendarSettings']['end_date_field'], $fields)){
                throw new InvalidInputException('Invalid calendarSettings.end_date_field', $identifier, $data);
            }
            if (isset($data['calendarSettings']['text_fields'])){
                $wrongFields = array_diff($data['calendarSettings']['text_fields'], $fields);
                if (!empty($wrongFields)) {
                    throw new InvalidInputException('Invalid calendarSettings.text_fields ' . implode(',', $wrongFields), $identifier, $data);
                }
            }
            if (isset($data['calendarSettings']['text_labels'])){
                $wrongFields = array_diff($data['calendarSettings']['text_labels'], $fields);
                if (!empty($wrongFields)) {
                    throw new InvalidInputException('Invalid calendarSettings.text_labels ' . implode(',', $wrongFields), $identifier, $data);
                }
            }
        }
        if (in_array('table', $data['views'])) {
            if (isset($data['tableSettings']['show_fields'])) {
                $wrongFields = array_diff($data['tableSettings']['show_fields'], $fields);
                if (!empty($wrongFields)) {
                    throw new InvalidInputException('Invalid tableSettings.show_fields ' . implode(',', $wrongFields), $identifier, $data);
                }
            }
        }
        if (in_array('chart', $data['views']) && !isset($data['chartSettings'])) {
            throw new InvalidInputException('Missing chartSettings', $identifier, $data);
        }
    }

    public static function validateField($field)
    {
        if (!isset($field['identifier']) || empty($field['identifier'])){
            throw new Exception('Field must have a identifier');
        }
        if (!isset($field['type']) || empty($field['type'])){
            throw new Exception('Missing type in field ' . $field['identifier']);
        }
        if (!in_array($field['type'], array_column(self::$availableTypes, 'identifier'))){
            throw new Exception('Invalid type ' . $field['type'] . ' for field ' . $field['identifier']);
        }
        if (!isset($field['required']) || empty($field['required'])){
            throw new Exception('Missing required in field ' . $field['identifier']);
        }

        if ($field['type'] == 'date' && (!isset($field['date_format']) || empty($field['date_format']))){
            throw new Exception('Missing date_format in date field ' . $field['identifier']);
        }
        if ($field['type'] == 'datetime' && (!isset($field['datetime_format']) || empty($field['datetime_format']))){
            throw new Exception('Missing datetime_format in datetime field ' . $field['identifier']);
        }

        if (isset($field['enum']) && !in_array($field['type'], ['select', 'checkbox'])){
            throw new Exception('Enum value is allowed only in select and checkbox type');
        }
        if (isset($field['enum']) && !is_string($field['enum'])){
            throw new Exception('Enum value must be a line break separated string (e.g.: red\nblue\ngreen\nyellow\nblack)');
        }

    }

}