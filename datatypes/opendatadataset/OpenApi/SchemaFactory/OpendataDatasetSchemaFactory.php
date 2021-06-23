<?php

use Opencontent\OpenApi\SchemaFactory;
use erasys\OpenApi\Spec\v3 as OA;

class OpendataDatasetSchemaFactory extends SchemaFactory
{
    /**
     * @var OpendataDatasetDefinition
     */
    private $definition;

    public function __construct(OpendataDatasetDefinition $definition)
    {
        $this->definition = $definition;
        $itemName = $definition->getItemName();
        $itemSlug = eZCharTransform::instance()->transformByGroup($itemName, 'identifier');
        $this->name = $this->toCamelCase($itemSlug . '_dataset_item');
    }

    public function getItemIdLabel()
    {
        return lcfirst($this->name) . 'Guid';
    }

    public function generateSchema()
    {
        $schema = new OA\Schema();
        $schema->title = $this->name;
        $schema->type = 'object';
        $required = [];
        $schema->properties = [];
        $schema->properties['guid'] = $this->generateSchemaProperty(['type' => 'string', 'description' => 'Item unique identifier', "readOnly" => true]);
        $schema->properties['createdAt'] = $this->generateSchemaProperty(['type' => 'string', "format" => "date-time", 'description' => 'Item creation date', "readOnly" => true]);
        $schema->properties['modifiedAt'] = $this->generateSchemaProperty(['type' => 'string', "format" => "date-time", 'description' => 'Item last modification date', "readOnly" => true]);
        $schema->properties['creator'] = $this->generateSchemaProperty(['type' => 'integer', 'title' => 'Item creator id', "readOnly" => true]);

        foreach ($this->definition->getFields() as $field){
            $schema->properties[$field['identifier']] = $this->generateSchemaProperty($this->getOpenApiSchemaForField($field));
            if ($field['required'] == "true"){
                $required[] = $field['identifier'];
            }
        }
        $schema->required = $required;

        return $schema;
    }

    private function getOpenApiSchemaForField($field)
    {
        $data = [
            'description' => $field['label']
        ];
        if (!empty($field['enum'])) {
            $data['enum'] = OpendataDatasetDefinition::parseEnumConfiguration($field['enum']);
        }
        foreach (OpendataDatasetType::getTypes() as $item) {
            if ($item['identifier'] == $field['type'] && isset($item['openapi_schema'])) {
                $data = array_merge($data, $item['openapi_schema']);
            }
        }

        return $data;
    }

    public function generateRequestBody()
    {
        $schema = $this->generateSchema();
        unset($schema->properties['guid']);
        unset($schema->properties['createdAt']);
        unset($schema->properties['modifiedAt']);
        unset($schema->properties['creator']);

        return new OA\RequestBody(['application/json' => new OA\MediaType([
            'schema' => $schema
        ])]);
    }

    public function serialize()
    {
        return serialize([
            'definition' => $this->definition,
            'name' => $this->name,
        ]);
    }

    /**
     * @return OpendataDatasetDefinition
     */
    public function getDatasetDefinition()
    {
        return $this->definition;
    }
}