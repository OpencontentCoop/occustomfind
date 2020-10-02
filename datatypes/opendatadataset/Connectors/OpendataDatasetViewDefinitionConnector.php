<?php

class OpendataDatasetViewDefinitionConnector extends OpendataDatasetConnector
{
    private $fields = [];

    private $dateFields = [];

    private $facetsFields = [];

    private $firstTextField;

    private $firstGeoField;

    public function runService($serviceIdentifier)
    {
        $this->load();
        foreach ($this->datasetDefinition->getFields() as $field) {
            $this->fields[$field['identifier']] = $field['label'];
            if ($field['type'] == 'date' || $field['type'] == 'datetime') {
                $this->dateFields[$field['identifier']] = $field['label'];
            }
            if ($field['type'] == 'string' || $field['type'] == 'checkbox' || $field['type'] == 'select') {
                $this->facetsFields[$field['identifier']] = $field['label'];
            }
            if ($this->firstTextField === null && ($field['type'] == 'string' || $field['type'] == 'textarea')) {
                $this->firstTextField = $field['identifier'];
            }
            if ($this->firstGeoField === null && $field['type'] == 'geo') {
                $this->firstGeoField = $field['identifier'];
            }
        }

        if ($this->firstGeoField === null) {
            unset($this->availableViews['map']);
        }
        if (empty($this->dateFields)) {
            unset($this->availableViews['calendar']);
        }
        return parent::runService($serviceIdentifier);
    }


    protected function getData()
    {
        $data = [];
        $data['views'] = $this->datasetDefinition->getViews();
        $data['apiEnabled'] = $this->datasetDefinition->isApiEnabled();
        $data['extraUsers'] = $this->datasetDefinition->getExtraUsers();
        $data['calendarSettings'] = $this->datasetDefinition->getCalendarSettings();
        $data['tableSettings'] = $this->datasetDefinition->getTableSettings();
        $data['chartSettings'] = $this->datasetDefinition->getChartSettings();
        $data['facetsSettings'] = $this->datasetDefinition->getFacetsSettings();

        return $data;
    }

    protected function getSchema()
    {
        return [
            'type' => 'object',
            'properties' => [
                'apiEnabled' => [
                    'title' => ezpI18n::tr('opendatadataset', 'API'),
                    'type' => 'boolean',
                ],
                'extraUsers' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Users who can enter or modify data'),
                    'type' => 'array'
                ],
                'views' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Views'),
                    'type' => 'array',
                    'enum' => array_keys($this->availableViews)
                ],
                'facetsSettings' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Show filters'),
                    'type' => 'array',
                    'enum' => array_keys($this->facetsFields),
                ],
                'calendarSettings' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Calendar settings'),
                    'type' => 'object',
                    'properties' => [
                        'default_view' => [
                            'title' => ezpI18n::tr('opendatadataset', 'Default view'),
                            'enum' => ['dayGridDay', 'dayGridWeek', 'dayGridMonth'],
                            'default' => 'dayGridWeek',
                        ],
                        'include_weekends' => [
                            'type' => 'boolean',
                            'default' => true,
                        ],
                        'start_date_field' => [
                            'title' => ezpI18n::tr('opendatadataset', 'Start date field'),
                            'enum' => array_keys($this->dateFields),
                            'default' => count($this->dateFields) > 0 ? array_keys($this->dateFields)[0] : false,
                        ],
                        'end_date_field' => [
                            'title' => ezpI18n::tr('opendatadataset', 'End date field'),
                            'enum' => array_keys($this->dateFields),
                        ],
                        'text_fields' => [
                            'title' => ezpI18n::tr('opendatadataset', 'Text event fields'),
                            'enum' => array_keys($this->fields),
                            'type' => 'array',
                            'default' => $this->firstTextField ? [$this->firstTextField] : false,
                        ],
                        'text_labels' => [
                            'title' => ezpI18n::tr('opendatadataset', 'Show label'),
                            'type' => 'array',
                            'enum' => array_keys($this->fields),
                        ],
                    ]
                ],
                'tableSettings' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Table settings'),
                    'type' => 'object',
                    'properties' => [
                        'show_fields' => [
                            'title' => ezpI18n::tr('opendatadataset', 'Show fields'),
                            'type' => 'array',
                            'enum' => array_keys($this->fields),
                            'default' => array_keys($this->fields),
                        ],
                    ]
                ],
                'chartSettings' => [
                    'title' => ezpI18n::tr('opendatadataset', 'Chart settings'),
                ],
            ],
            'dependencies' => [
                'calendarSettings' => ['views'],
                'tableSettings' => ['views'],
                'chartSettings' => ['views'],
                'extraUsers' => ['apiEnabled'],
            ],
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
                'views' => [
                    'type' => 'checkbox',
                    'optionLabels' => array_values($this->availableViews),
                ],
                'apiEnabled' => [
                    'type' => 'checkbox',
                    'rightLabel' => ezpI18n::tr('opendatadataset', 'Enable create/edit/remove single item?'),
                ],
                'extraUsers' => [
                    'helper' => ezpI18n::tr('opendatadataset', 'Selects additional users who can edit elements of this dataset in addition to editors'),
                    'type' => intval($this->attribute->object()->mainNodeID()) > 0 ? 'relationbrowse' : 'hidden',
                    'browse' => [
                        'classes' => eZUser::fetchUserClassNames(),
                        'addCloseButton' => true,
                        'allowAllBrowse' => false,
                        'subtree' => (int)eZINI::instance()->variable("UserSettings", "DefaultUserPlacement")
                    ],
                ],
                'facetsSettings' => [
                    'optionLabels' => array_values($this->facetsFields),
                    'type' => 'checkbox',
                    'hideNone' => true,
                ],
                'calendarSettings' => [
                    'fields' => [
                        'default_view' => [
                            'optionLabels' => [ezpI18n::tr('opendatadataset', 'Day'), ezpI18n::tr('opendatadataset', 'Week'), ezpI18n::tr('opendatadataset', 'Month')],
                            'type' => 'select',
                            'hideNone' => true,
                        ],
                        'start_date_field' => [
                            'optionLabels' => array_values($this->dateFields),
                            'type' => 'select',
                            'hideNone' => true,
                        ],
                        'end_date_field' => [
                            'optionLabels' => array_values($this->dateFields),
                            'type' => 'select',
                        ],
                        'text_fields' => [
                            'optionLabels' => array_values($this->fields),
                            'type' => 'checkbox',
                            'hideNone' => true,
                        ],
                        'text_labels' => [
                            'optionLabels' => array_values($this->fields),
                            'type' => 'checkbox',
                            'hideNone' => true,
                        ],
                        'include_weekends' => [
                            'type' => 'checkbox',
                            'rightLabel' => ezpI18n::tr('opendatadataset', 'Include weekends?'),
                        ],
                    ],
                    'dependencies' => [
                        'views' => ['calendar']
                    ],
                ],
                'tableSettings' => [
                    'fields' => [
                        'show_fields' => [
                            'optionLabels' => array_values($this->fields),
                            'type' => 'checkbox',
                            'hideNone' => true,
                        ]
                    ],
                    'dependencies' => [
                        'views' => ['table']
                    ],
                ],
                'chartSettings' => [
                    'type' => 'chart',
                    'dependencies' => [
                        'views' => ['chart']
                    ],
                    'chart' => [
                        'dataUrl' => '/customexport/dataset-' . $this->attribute->attribute('contentclass_attribute_identifier') . '-' . $this->attribute->attribute('contentobject_id')
                    ]
                ]
            ]
        ];
    }

    protected function submit()
    {
        $definition = $this->datasetDefinition->merge($_POST);
        $this->attribute->setAttribute('data_text', json_encode($definition));
        $this->attribute->store();

        $extraUsers = (array)$_POST['extraUsers'];
        $definition->grantPermissions($extraUsers, $this->attribute->object()->mainNodeID());

        return $definition;
    }
}