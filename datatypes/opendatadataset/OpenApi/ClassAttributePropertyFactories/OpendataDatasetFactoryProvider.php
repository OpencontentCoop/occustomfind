<?php

use Opencontent\OpenApi\SchemaFactory\ContentClassAttributePropertyFactory;

class OpendataDatasetFactoryProvider extends ContentClassAttributePropertyFactory
{
    protected $availableTypes = [];

    protected $availableViews = [];

    public function __construct(\eZContentClass $class, \eZContentClassAttribute $attribute)
    {
        parent::__construct($class, $attribute);

        $this->availableTypes = OpendataDatasetType::getTypes();
        $this->availableViews = OpendataDatasetType::getViews();
    }

    public function provideProperties()
    {
        $types = array_column($this->availableTypes, 'identifier');
        $defaultTypes = array_diff($types, ['select', 'checkbox', 'date', 'datetime']);
        return [
            'type' => 'object',
            'description' => $this->getPropertyDescription(),
            'properties' => [
                'itemName' => [
                    'type' => 'string',
                    'description' => ezpI18n::tr('opendatadataset', 'Item name'),
                ],
                'fields' => [
                    'description' => ezpI18n::tr('opendatadataset', 'Fields'),
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'oneOf' => [
                            [
                                'type' => 'object',
                                'properties' => [
                                    'identifier' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Identifier'),
                                    ],
                                    'label' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Label'),
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'enum' => $defaultTypes,
                                        'description' => ezpI18n::tr('opendatadataset', 'Type'),
                                        'default' => 'string',
                                    ],
                                    'required' => [
                                        'type' => 'boolean',
                                        'description' => ezpI18n::tr('opendatadataset', 'Is required?'),
                                    ],
                                    'default' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Default value'),
                                    ],
                                ],
                                'required' => [
                                    'identifier',
                                    'type',
                                    'required',
                                ]
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'identifier' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Identifier'),
                                    ],
                                    'label' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Label'),
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'enum' => ['select', 'checkbox'],
                                        'description' => ezpI18n::tr('opendatadataset', 'Type'),
                                        'default' => 'string',
                                    ],
                                    'required' => [
                                        'type' => 'boolean',
                                        'description' => ezpI18n::tr('opendatadataset', 'Is required?'),
                                    ],
                                    'enum' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Enum'),
                                    ],
                                    'default' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Default value'),
                                    ],
                                ],
                                'required' => [
                                    'identifier',
                                    'type',
                                    'required',
                                ]
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'identifier' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Identifier'),
                                    ],
                                    'label' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Label'),
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'enum' => ['date'],
                                        'description' => ezpI18n::tr('opendatadataset', 'Type'),
                                        'default' => 'string',
                                    ],
                                    'required' => [
                                        'type' => 'boolean',
                                        'description' => ezpI18n::tr('opendatadataset', 'Is required?'),
                                    ],
                                    'date_format' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Date format'),
                                        'default' => 'DD/MM/YYYY',
                                    ],
                                    'default' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Default value'),
                                    ],
                                ],
                                'required' => [
                                    'identifier',
                                    'type',
                                    'required',
                                    'date_format'
                                ]
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'identifier' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Identifier'),
                                    ],
                                    'label' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Label'),
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                        'enum' => ['datetime'],
                                        'description' => ezpI18n::tr('opendatadataset', 'Type'),
                                        'default' => 'string',
                                    ],
                                    'required' => [
                                        'type' => 'boolean',
                                        'description' => ezpI18n::tr('opendatadataset', 'Is required?'),
                                    ],
                                    'datetime_format' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Date format'),
                                        'default' => 'DD/MM/YYYY',
                                    ],
                                    'default' => [
                                        'type' => 'string',
                                        'description' => ezpI18n::tr('opendatadataset', 'Default value'),
                                    ],
                                ],
                                'required' => [
                                    'identifier',
                                    'type',
                                    'required',
                                    'datetime_format'
                                ]
                            ]
                        ],
                    ]
                ],
                'apiEnabled' => [
                    'description' => ezpI18n::tr('opendatadataset', 'API'),
                    'type' => 'boolean',
                ],
                'extraUsers' => [
                    'description' => ezpI18n::tr('opendatadataset', 'Users who can enter or modify data'),
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Name',
                            ],
                            'id' => [
                                'type' => 'integer',
                                'description' => 'Id',
                            ],
                        ],
                        'required' => [
                            'id',
                        ]
                    ]
                ],
                'views' => [
                    'description' => ezpI18n::tr('opendatadataset', 'Views'),
                    'type' => 'array',
                    'enum' => array_keys($this->availableViews),
                    'items' => [
                        'type' => 'string',
                    ]
                ],
                'facetsSettings' => [
                    'description' => ezpI18n::tr('opendatadataset', 'Show filters'),
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ]
                ],
                'calendarSettings' => [
                    'description' => ezpI18n::tr('opendatadataset', 'Calendar settings'),
                    'type' => 'object',
                    'properties' => [
                        'default_view' => [
                            'description' => ezpI18n::tr('opendatadataset', 'Default view'),
                            'type' => 'string',
                            'enum' => ['dayGridDay', 'dayGridWeek', 'dayGridMonth'],
                            'default' => 'dayGridWeek',
                        ],
                        'include_weekends' => [
                            'type' => 'boolean',
                            'default' => true,
                        ],
                        'start_date_field' => [
                            'description' => ezpI18n::tr('opendatadataset', 'Start date field'),
                            'type' => 'string',
                        ],
                        'end_date_field' => [
                            'description' => ezpI18n::tr('opendatadataset', 'End date field'),
                            'type' => 'string',
                        ],
                        'text_fields' => [
                            'description' => ezpI18n::tr('opendatadataset', 'Text event fields'),
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ]
                        ],
                        'text_labels' => [
                            'description' => ezpI18n::tr('opendatadataset', 'Show label'),
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ]
                        ],
                    ],
                    'required' => [
                        'default_view',
                        'start_date_field',
                    ]
                ],
                'tableSettings' => [
                    'description' => ezpI18n::tr('opendatadataset', 'Table settings'),
                    'type' => 'object',
                    'properties' => [
                        'show_fields' => [
                            'description' => ezpI18n::tr('opendatadataset', 'Show fields'),
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ]
                        ],
                    ]
                ],
                'chartSettings' => [
                    'description' => ezpI18n::tr('opendatadataset', 'Chart settings'),
                    'type' => 'object',
                    'properties' => [
                        'chart' => [
                            'description' => 'Chart',
                            'type' => 'object',
                            'properties' => [
                                'type' => [
                                    'description' => 'Chart type',
                                    'type' => 'string',
                                ]
                            ]
                        ],
                    ]
                ],
            ],
            'required' => [
                'itemName',
                'fields',
            ]
        ];
    }
}