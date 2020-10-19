<?php


trait OpendataDatasetFilterTrait
{
    /**
     * @param OpendataDatasetSearchableObject[]|OpendataDataset[] $datasets
     */
    protected function filterDatasetList(array $datasets)
    {
        $list = [];
        foreach ($datasets as $dataset){
            $list[] = $this->filterDataset($dataset);
        }

        return $list;
    }

    /**
     * @param OpendataDatasetSearchableObject|OpendataDataset $dataset
     * @return array
     */
    protected function filterDataset($dataset)
    {
        if ($dataset instanceof OpendataDatasetSearchableObject){
            $dataset = $dataset->getDataset();
        }
        $value = $dataset->jsonSerialize();

        $value['_createdAt'] = date('c', $value['_createdAt']);
        $value['_modifiedAt'] = date('c', $value['_modifiedAt']);

        foreach ($dataset->getDefinition()->getFields() as $field){
            if ($field['type'] == 'date'){
                $date = \DateTime::createFromFormat(OCCustomSearchableField::convertMomentFormatToPhp($field['date_format']), $value[$field['identifier']]);
                $value[$field['identifier']] = $date instanceof \DateTime ? $date->format('o-m-d') : null;
            }
            if ($field['type'] == 'datetime'){
                $date = \DateTime::createFromFormat(OCCustomSearchableField::convertMomentFormatToPhp($field['datetime_format']), $value[$field['identifier']]);
                $value[$field['identifier']] = $date instanceof \DateTime ? $date->format('c') : null;
            }
        }

        return $value;
    }

    protected function filterPayload(array $data, OpendataDatasetDefinition $definition)
    {
        foreach ($definition->getFields() as $field){
            if (isset($data[$field['identifier']]) && !empty($data[$field['identifier']])) {
                if ($field['type'] == 'date') {
                    $data[$field['identifier']] = date(
                        OCCustomSearchableField::convertMomentFormatToPhp($field['date_format']),
                        strtotime($data[$field['identifier']])
                    );
                }
                if ($field['type'] == 'datetime') {
                    $data[$field['identifier']] = date(
                        OCCustomSearchableField::convertMomentFormatToPhp($field['datetime_format']),
                        strtotime($data[$field['identifier']])
                    );
                }
            }
        }

        return $data;
    }
}