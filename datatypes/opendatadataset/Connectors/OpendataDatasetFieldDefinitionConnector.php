<?php

class OpendataDatasetFieldDefinitionConnector extends OpendataDatasetConnector
{
    const DEFAULT_GEO_FORMAT = '%latitude,%longitude';

    protected function getData()
    {
        $data = [];
        $itemName = $this->datasetDefinition->getItemName();
        if (!empty($itemName)) {
            $data['itemName'] = $itemName;
        }else{
            $data['itemName'] = 'item';
        }
        $data['fields'] = $this->fixGeoDefinitions($this->datasetDefinition->getFields());

        return $data;
    }

    private function fixGeoDefinitions($fields)
    {
        foreach ($fields as $index => $definition) {
            if ($definition['type'] === 'geo') {
                if (!isset($definition['geo_format'])){
                    $fields[$index]['geo_format'] = self::DEFAULT_GEO_FORMAT;
                }
                if (isset($definition['geo_separator'])) {
                    $fields[$index]['geo_format'] = "%longitude{$definition['geo_separator']}%latitude";
                    unset($fields[$index]['geo_separator']);
                }
            }
        }
        return $fields;
    }

    protected function getSchema()
    {
        return [
            'type' => 'object',
            'properties' => [
                'itemName' => [
                    'type' => 'string',
                    'title' => ezpI18n::tr('opendatadataset', 'Item name'),
                    'default' => 'item',
                    'required' => true,
                ],
                'fields' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Fields'),
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'identifier' => [
                                'type' => 'string',
                                'title' => ezpI18n::tr('opendatadataset', 'Identifier'),
                                'required' => true,
                            ],
                            'label' => [
                                'type' => 'string',
                                'title' => ezpI18n::tr('opendatadataset', 'Label'),
                                //'required' => true,
                            ],
                            'type' => [
                                'type' => 'string',
                                'enum' => array_column($this->availableTypes, 'identifier'),
                                'title' => ezpI18n::tr('opendatadataset', 'Type'),
                                'default' => 'string',
                                'required' => true,
                            ],
                            'required' => [
                                'type' => 'boolean',
                                //'title' => 'Required',
                            ],
                            'enum' => [
                                'type' => 'string',
                                'title' => ezpI18n::tr('opendatadataset', 'Enum'),
                            ],
                            'date_format' => [
                                'type' => 'string',
                                'title' => ezpI18n::tr('opendatadataset', 'Date format'),
                                'default' => 'DD/MM/YYYY',
                                'required' => true,
                            ],
                            'datetime_format' => [
                                'type' => 'string',
                                'title' => ezpI18n::tr('opendatadataset', 'Date time format'),
                                'default' => 'DD/MM/YYYY HH:mm',
                                'required' => true,
                            ],
                            'geo_format' => [
                                'type' => 'string',
                                'title' => ezpI18n::tr('opendatadataset', 'Geo point format'),
                                'default' => self::DEFAULT_GEO_FORMAT,
                            ],
                            'default' => [
                                'type' => 'string',
                                'title' => ezpI18n::tr('opendatadataset', 'Default value'),
                            ],
                        ],
                        'dependencies' => [
                            'enum' => ['type'],
                            'date_format' => ['type'],
                            'datetime_format' => ['type'],
                            'geo_format' => ['type'],
                        ],
                    ]
                ],
//                'views' => [
//                    'title' => ezpI18n::tr('opendatadataset', 'Views'),
//                    'type' => 'array',
//                    'enum' => array_values($this->availableViews),
//                ],
//                'apiEnabled' => [
//                    'title' => ezpI18n::tr('opendatadataset', 'API'),
//                    'type' => 'boolean',
//                ],
            ]
        ];
    }

    protected function getOptions()
    {
        return [
            'form' => [
                'attributes' => [
                    'action' => $this->getHelper()->getServiceUrl('action', $this->getHelper()->getParameters()),
                    'method' => 'post',
                    'enctype' => 'multipart/form-data'
                ],
                'buttons' => [
                    'submit' => []
                ]
            ],
            'fields' => [
                'fields' => [
//                    'type' => 'table',
                    'items' => [
                        'fields' => [
                            'type' => [
                                'optionLabels' => array_column($this->availableTypes, 'label'),
                            ],
                            'required' => [
                                'type' => 'checkbox',
                                'rightLabel' => ezpI18n::tr('opendatadataset', 'Is required?'),
                            ],
                            'enum' => [
                                'type' => 'textarea',
                                'helper' => ezpI18n::tr('opendatadataset', 'One item per line'),
                                'dependencies' => [
                                    'type' => ['select', 'checkbox']
                                ],
                            ],
                            'date_format' => [
                                'helper' => ezpI18n::tr('opendatadataset', 'MomentJS date format (es: DD/MM/YYYY)'),
                                'dependencies' => [
                                    'type' => ['date']
                                ],
                            ],
                            'datetime_format' => [
                                'helper' => ezpI18n::tr('opendatadataset', 'MomentJS datetime format (es: DD/MM/YYYY HH:mm)'),
                                'dependencies' => [
                                    'type' => ['datetime']
                                ],
                            ],
                            'geo_format' => [
                                'helper' => ezpI18n::tr('opendatadataset', 'The latitude and longitude format: you can use %latitude and %longitude placeholders, the %latitude and %longitude expecetd values must be float values (with dot as decimal separator, e.g. 43.1234)'),
                                'dependencies' => [
                                    'type' => ['geo']
                                ],
                            ],
                        ]
                    ]
                ],
//                'views' => [
//                    'type' => 'checkbox',
//                    'optionLabels' => array_keys($this->availableViews),
//                ],
//                'apiEnabled' => [
//                    'type' => 'checkbox',
//                    'rightLabel' => ezpI18n::tr('opendatadataset', 'Enable CRUD?'),
//                ],
            ]
        ];
    }

    protected function submit()
    {
        $definition = $this->datasetDefinition->merge($_POST);
        $this->attribute->setAttribute('data_text', json_encode($definition));
        $this->attribute->store();

        return $definition;
    }
}
