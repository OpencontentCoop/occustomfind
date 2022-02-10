<?php


class OpendataDatasetSearchableObject implements OCCustomSearchableObjectInterface, JsonSerializable, OCCustomSearchableObjectCSVExportCapable
{
    private $dataset;

    public function __construct(OpendataDataset $dataset)
    {
        $this->dataset = $dataset;
    }

    public function getGuid()
    {
        return $this->dataset->getGuid();
    }

    /**
     * @return OpendataDataset
     */
    public function getDataset()
    {
        return $this->dataset;
    }

    public function getFieldValue(OCCustomSearchableFieldInterface $field)
    {
        if ($field->getName() === '_createdAt' && $this->dataset->getCreatedAt()){
            return ezfSolrDocumentFieldBase::convertTimestampToDate($this->dataset->getCreatedAt());
        }
        if ($field->getName() === '_modifiedAt' && $this->dataset->getModifiedAt()){
            return ezfSolrDocumentFieldBase::convertTimestampToDate($this->dataset->getModifiedAt());
        }
        if ($field->getName() === '_creator' && $this->dataset->getCreator()){
            return (int)$this->dataset->getCreator();
        }

        /** @var OpendataDatasetDefinition $definition */
        $definition = $this->dataset->getContext()->content();
        foreach ($definition->getFields() as $definitionField) {
            if ($definitionField['identifier'] == $field->getName()) {
                $fieldData = $this->dataset->getData($field->getName());
                if (empty($fieldData)){
                    return null;
                }
                switch ($definitionField['type']) {
                    case 'date':
                        $format = $definitionField['date_format'];
                        $date = \DateTime::createFromFormat(OCCustomSearchableField::convertMomentFormatToPhp($format), $fieldData);
                        return $date instanceof \DateTime ? ezfSolrDocumentFieldBase::convertTimestampToDate($date->format('U')) : null;

                    case 'datetime':
                        $format = $definitionField['datetime_format'];
                        $date = \DateTime::createFromFormat(OCCustomSearchableField::convertMomentFormatToPhp($format), $fieldData);
                        return $date instanceof \DateTime ? ezfSolrDocumentFieldBase::convertTimestampToDate($date->format('U')) : null;

                    case 'geo':
                        $latLng = OpendataDatasetDefinition::explodeGeoValue(
                            $definitionField,
                            $fieldData
                        );
                        return $latLng['longitude'] . ',' . $latLng['latitude'];

                    case 'number':
                        return OpendataDatasetDefinition::floatValue($fieldData);

                    case 'integer':
                        return (int)$fieldData;

                    default:
                        return $fieldData;
                }
            }
        }

        return null;
    }

    public function toArray()
    {
        return $this->dataset->jsonSerialize();
    }

    public function toCsv()
    {
        $data = $this->dataset->jsonSerialize();
        unset($data['_guid']);
        unset($data['_createdAt']);
        unset($data['_modifiedAt']);
        unset($data['_creator']);

        return $data;
    }

    public function jsonSerialize()
    {
        $data = $this->toArray();
        $canEdit = $this->dataset->getDefinition()->canTruncate();
        if (!$canEdit){
            $canEdit = $this->dataset->getDefinition()->canEdit() && $this->dataset->getCreator() == eZUser::currentUserID();
        }

        $data['_canEdit'] = $canEdit;

        return $data;
    }

    //ignore using OCCustomSearchableRepositoryObjectCreatorInterface
    public static function getFields()
    {
        return [];
    }

    //ignore using OCCustomSearchableRepositoryObjectCreatorInterface
    public static function fromArray($array)
    {
        return null;
    }

}
