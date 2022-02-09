<?php

use Opencontent\Ocopendata\Forms\Connectors\AbstractBaseConnector;
use Opencontent\Opendata\Api\Exception\ForbiddenException;

class OpendataDatasetConnector extends AbstractBaseConnector
{
    /**
     * @var eZContentObjectAttribute
     */
    protected $attribute;

    /**
     * @var OpendataDatasetDefinition
     */
    protected $datasetDefinition;

    protected $availableTypes = [];

    protected $availableViews = [];

    /**
     * @var OpendataDataset
     */
    protected $currentDataset;

    protected $view;

    public function __construct($identifier)
    {
        $this->availableTypes = OpendataDatasetType::getTypes();
        $this->availableViews = OpendataDatasetType::getViews();

        parent::__construct($identifier);
    }

    protected function load()
    {
        if ($this->attribute === null) {
            $this->attribute = eZContentObjectAttribute::fetch(
                (int)$this->getHelper()->getParameter('id'),
                (int)$this->getHelper()->getParameter('version')
            );
            if (!$this->attribute instanceof eZContentObjectAttribute) {
                throw new Exception('Attribute not found');
            }

            $this->datasetDefinition = $this->attribute->content();
        }
    }

    public function runService($serviceIdentifier)
    {
        $this->load();
        if ($this->getHelper()->hasParameter('guid')){
            $this->currentDataset = $this->datasetDefinition->getDataset(
                $this->getHelper()->getParameter('guid'),
                $this->attribute
            );
        }
        if ($this->getHelper()->hasParameter('viewmode')){
            $this->view = $this->getHelper()->getParameter('viewmode');
        }
        if (($serviceIdentifier == 'action' || $serviceIdentifier == 'upload') && !$this->datasetDefinition->canEdit()) {
            throw new ForbiddenException($this->datasetDefinition->getItemName(), 'edit');
        }

        return parent::runService($serviceIdentifier);
    }

    protected function getData()
    {
        if ($this->currentDataset instanceof OpendataDataset){
            $data = $this->currentDataset->jsonSerialize();
            foreach ($this->datasetDefinition->getFields() as $definitionField) {
                if ($definitionField['type'] === 'geo' && isset($data[$definitionField['identifier']])){
                    $data[$definitionField['identifier']] = OpendataDatasetDefinition::explodeGeoValue(
                        $definitionField,
                        $data[$definitionField['identifier']]
                    );
                }
            }

            return $data;
        }

        return null;
    }

    protected function getSchema()
    {
        $schema = [
            'title' => $this->datasetDefinition->getItemName(),
            'type' => 'object',
            'properties' => []
        ];

        foreach ($this->datasetDefinition->getFields() as $field) {
            $property = [
                'title' => $field['label'],
                'required' => $field['required'] == "true",
                'default' => isset($field['default']) ? $field['default'] : null,
            ];
            if ($this->view === 'display'){
                unset($property['required']);
            }
            if (!empty($field['enum']) && $this->view === null) {
                $property['enum'] = OpendataDatasetDefinition::parseEnumConfiguration($field['enum']);
                if ($property['required'] && empty($property['default'])) {
                    $property['default'] = $property['enum'][0];
                }
            }
            $property = array_merge($property, $this->getBaseSchemaForType($field['type']));
            $schema['properties'][$field['identifier']] = $property;
        }

        return $schema;
    }

    private function getBaseSchemaForType($type)
    {
        foreach ($this->availableTypes as $item) {
            if ($item['identifier'] == $type && isset($item['schema'])) {
                return $item['schema'];
            }
        }

        return [];
    }

    protected function getOptions()
    {
        $options = [
            'form' => [
                'attributes' => [
                    'action' => $this->getHelper()->getServiceUrl('action', $this->getHelper()->getParameters()),
                    'method' => 'post',
                    'enctype' => 'multipart/form-data'
                ],
            ],
            'fields' => [],
        ];

        foreach ($this->datasetDefinition->getFields() as $field) {
            $option = $this->getBaseOptionsForType($field['type']);
            if (!empty($options)) {
                $options['fields'][$field['identifier']] = $option;
            }
            if (!empty($field['date_format'])) {
                $options['fields'][$field['identifier']]['dateFormat'] = $field['date_format'];
                $options['fields'][$field['identifier']]['picker']['format'] = $field['date_format'];
            }
            if (!empty($field['datetime_format'])) {
                $options['fields'][$field['identifier']]['dateFormat'] = $field['datetime_format'];
                $options['fields'][$field['identifier']]['picker']['format'] = $field['datetime_format'];
            }
        }

        return $options;
    }

    private function getBaseOptionsForType($type)
    {
        foreach ($this->availableTypes as $item) {
            if ($item['identifier'] == $type && isset($item['options'])) {
                return $item['options'];
            }
        }

        return [];
    }

    protected function getView()
    {
        $parent = 'bootstrap-create';
        if ($this->getData() !== null){
            $parent = 'bootstrap-edit';
        }
        if ($this->view === 'display'){
            $parent = 'bootstrap-display';
        }
        return [
            'parent' => $parent,
            'locale' => $this->getAlpacaLocale()
        ];
    }

    protected function getAlpacaLocale()
    {
        $localeMap = [
            'eng-GB' => false,
            'chi-CN' => 'zh_CN',
            'cze-CZ' => 'cs_CZ',
            'cro-HR' => 'hr_HR',
            'dut-NL' => 'nl_BE',
            'fin-FI' => 'fi_FI',
            'fre-FR' => 'fr_FR',
            'ger-DE' => 'de_DE',
            'ell-GR' => 'el_GR',
            'ita-IT' => 'it_IT',
            'jpn-JP' => 'ja_JP',
            'nor-NO' => 'nb_NO',
            'pol-PL' => 'pl_PL',
            'por-BR' => 'pt_BR',
            'esl-ES' => 'es_ES',
            'swe-SE' => 'sv_SE',
        ];

        $currentLanguage = $this->getHelper()->getSetting('language');

        return isset($localeMap[$currentLanguage]) ? $localeMap[$currentLanguage] : 'it_IT';
    }

    protected function submit()
    {
        $data = $_POST;
        foreach ($this->datasetDefinition->getFields() as $definitionField) {
            if ($definitionField['type'] === 'geo' && isset($data[$definitionField['identifier']])){
                $data[$definitionField['identifier']] = OpendataDatasetDefinition::implodeGeoValue(
                    $definitionField,
                    $data[$definitionField['identifier']]
                );
            }
        }
        $dataset = $this->datasetDefinition->create($data, $this->attribute);

        if ($this->currentDataset instanceof OpendataDataset){
            $dataset->setGuid($this->currentDataset->getGuid());
            $dataset->setCreatedAt($this->currentDataset->getCreatedAt());
            $dataset->setCreator($this->currentDataset->getCreator());

            return $this->datasetDefinition->updateDataset($dataset);
        }else{
            return $this->datasetDefinition->createDataset($dataset);
        }
    }

    protected function upload()
    {
        throw new Exception('Not allowed');
    }

}
