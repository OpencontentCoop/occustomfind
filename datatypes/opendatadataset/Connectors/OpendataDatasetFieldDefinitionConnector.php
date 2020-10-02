<?php

class OpendataDatasetFieldDefinitionConnector extends OpendataDatasetConnector
{
    protected function getData()
    {
        $data = [];
        $data['itemName'] = $this->datasetDefinition->getItemName();
        $data['fields'] = $this->datasetDefinition->getFields();

        return $data;
    }

    protected function getSchema()
    {
        return [
            'type' => 'object',
            'properties' => [
                'itemName' => [
                    'type' => 'string',
                    'title' => ezpI18n::tr('opendatadataset', 'Item name'),
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
                            'default' => [
                                'type' => 'string',
                                'title' => ezpI18n::tr('opendatadataset', 'Default value'),
                            ],
                        ],
                        'dependencies' => [
                            'enum' => ['type'],
                            'date_format' => ['type'],
                            'datetime_format' => ['type'],
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
