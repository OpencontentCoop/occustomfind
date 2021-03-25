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
        return (int)OcOpendataDataset::count(OcOpendataDataset::definition(), ['repository' => $this->getIdentifier()]);
    }

    public function fetchSearchableObjectList($limit, $offset)
    {
        $list = OcOpendataDataset::fetchObjectList(
            OcOpendataDataset::definition(),
            null,
            ['repository' => $this->getIdentifier()],
            null,
            ['offset' => $offset, 'length' => $limit]
        );
        $objects = [];
        foreach ($list as $row) {
            $data = json_decode($row->attribute('data'), true);
            $dataset = new OpendataDataset($data, $this->attribute, $this->attribute->content());
            $dataset->setGuid($row->attribute('guid'));
            $dataset->setCreatedAt($row->attribute('created_at'));
            $dataset->setModifiedAt($row->attribute('modified_at'));
            $dataset->setCreator($row->attribute('creator'));
            $objects[] = new OpendataDatasetSearchableObject($dataset);
        }

        return $objects;
    }

    public function fetchSearchableObject($objectID)
    {
        $row = OcOpendataDataset::fetchObject(OcOpendataDataset::definition(), null, [
            'repository' => $this->getIdentifier(),
            'guid' => $objectID
        ]);
        if ($row instanceof OcOpendataDataset) {
            $data = json_decode($row->attribute('data'), true);
            $dataset = new OpendataDataset($data, $context, $context->content());
            $dataset->setGuid($row->attribute('guid'));
            $dataset->setCreatedAt($row->attribute('created_at'));
            $dataset->setModifiedAt($row->attribute('modified_at'));
            $dataset->setCreator($row->attribute('creator'));

            return new OpendataDatasetSearchableObject($dataset);
        }
    }

    public function instanceObject($dataset, $guid)
    {
        if (is_array($dataset)) {
            $datasetArray = $dataset;
            $dataset = new OpendataDataset($datasetArray, $this->attribute, $this->attribute->content());
            $dataset->setGuid($guid);
            if (isset($datasetArray['_createdAt'])) {
                $dataset->setCreatedAt($datasetArray['_createdAt']);
            }
            if (isset($datasetArray['_modifiedAt'])) {
                $dataset->setModifiedAt($datasetArray['_modifiedAt']);
            }
            if (isset($datasetArray['_creator'])) {
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
            $types = $this->mapType($field);
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