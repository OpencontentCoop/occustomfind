<?php


class OpendataDatasetSearchableRepository extends OCCustomSearchableRepositoryAbstract implements OCCustomSearchableRepositoryObjectCreatorInterface, OCCustomSearchableRepositoryCSVExportCapable
{
    private $attribute;

    public function __construct(eZContentObjectAttribute $attribute)
    {
        $this->attribute = $attribute;
    }

    public function getIdentifier()
    {
        return 'dataset-' . $this->attribute->attribute('contentclass_attribute_identifier') . '-' . $this->attribute->attribute('contentobject_id');
    }

    public function countSearchableObjects()
    {
        // TODO: Implement countSearchableObjects() method.
    }

    public function fetchSearchableObjectList($limit, $offset)
    {
        // TODO: Implement fetchSearchableObjectList() method.
    }

    public function fetchSearchableObject($objectID)
    {
        // TODO: Implement fetchSearchableObject() method.
    }

    public function instanceObject($dataset, $guid)
    {
        if (is_array($dataset)) {
            $datasetArray = $dataset;
            $dataset = new OpendataDataset($datasetArray, $this->attribute, $this->attribute->content());
            $dataset->setGuid($guid);
            if (isset($datasetArray['_createdAt'])){
                $dataset->setCreatedAt($datasetArray['_createdAt']);
            }
            if (isset($datasetArray['_modifiedAt'])){
                $dataset->setModifiedAt($datasetArray['_modifiedAt']);
            }
            if (isset($datasetArray['_creator'])){
                $dataset->setCreator((int)$datasetArray['_creator']);
            }
        }
        return new OpendataDatasetSearchableObject($dataset);
    }

    public function getFields()
    {
        /** @var OpendataDatasetDefinition $definition */
        $definition = $this->attribute->content();

        $fields = [];
        $fields[] = OCCustomSearchableField::create('_createdAt', 'date');
        $fields[] = OCCustomSearchableField::create('_modifiedAt', 'date');
        $fields[] = OCCustomSearchableField::create('_creator', 'int');

        foreach ($definition->getFields() as $field) {
            $types =  $this->mapType($field);
            $fields[] = OCCustomSearchableField::create($field['identifier'], $this->mapType($field), $field['label']);
        }

        return $fields;
    }

    private function mapType(array $field)
    {
        switch ($field['type']) {
            case 'identifier':
            case 'string':
            case 'select':
                return 'string';

            case 'checkbox':
                return 'string[]';

            case 'integer':
                return 'int';

            case 'number':
                return 'float';

            case 'textarea':
                return 'text';

            case 'date':
            case 'datetime':
                return 'date';

            case 'geo':
                return 'geopoint';
        }

        return 'string';
    }

    public function availableForClass()
    {
        //ignore using OCCustomSearchableRepositoryObjectCreatorInterface
        return null;
    }

    public function getCsvHeaders()
    {
        $headers = [];
        foreach ($this->getFields() as $field) {
            if (!in_array($field->getName(), ['_guid', '_createdAt', '_modifiedAt', '_creator'])) {
                $headers[] = $field->getLabel();
            }
        }

        return $headers;
    }


}