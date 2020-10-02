<?php


class OpendataDatasetCsvImporter
{
    private $file;

    private $headers = [];

    private $values = [];

    public function __construct($file)
    {
        $this->file = $file;
        $this->parse();
    }

    private function parse()
    {
        $row = 1;
        if (($handle = fopen($this->file, "r")) !== false) {
            while (($data = fgetcsv($handle, 100000, ",")) !== false) {
                if ($row === 1) {
                    $this->headers = $data;
                } else {
                    $value = [];
                    for ($j = 0, $jMax = count($this->headers); $j < $jMax; ++$j) {
                        $value[$this->headers[$j]] = $data[$j];
                    }
                    $this->values[] = $value;
                }
                $row++;
            }
            fclose($handle);
        } else {
            throw new Exception("File not found");
        }
    }

    public function createDefinition()
    {
        $firstRow = $this->getValues(0);
        $fields = [];
        $index = 0;
        foreach ($firstRow as $header => $value) {
            $identifier = eZCharTransform::instance()->transformByGroup($header, 'identifier');
            if (empty($identifier)) {
                $identifier = 'header-' . $index;
            }
            $field = [
                'identifier' => $identifier,
                'label' => $header,
                'type' => 'string',
                'required' => 'false',
            ];

//            if (is_integer($value)) {
//                $field['type'] = 'integer';
//            } elseif (is_numeric($value)) {
//                $field['type'] = 'number';
//            }

            if (substr_count($value, '/') == 2) {
                $field['type'] = 'date';
                $field['date_format'] = 'DD/MM/YYYY';
            }

            if (mb_strlen($value) > 50) {
                $field['type'] = 'textarea';
            }
            $fields[] = $field;
            $index++;
        }

        return new OpendataDatasetDefinition(['fields' => $fields]);
    }

    /**
     * @param int $index
     * @return array
     */
    public function getValues($index = null)
    {
        if ($index !== null) {
            return $this->values[$index];
        }

        return $this->values;
    }

    public function checkHeaders(OpendataDatasetDefinition $definition)
    {
        $atLeastOne = false;
        foreach ($definition->getFields() as $field) {
            if ($field['required'] == "true" && !in_array($field['label'], $this->getHeaders()) && !in_array($field['identifier'], $this->getHeaders())) {
                throw new Exception($field['label'] . ' is required');
            }
            if (in_array($field['label'], $this->getHeaders()) || in_array($field['identifier'], $this->getHeaders())){
                $atLeastOne = true;
            }
        }
        if (!$atLeastOne){
            throw new Exception('Invalid csv headers');
        }
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    public function import(OpendataDatasetDefinition $definition, $context)
    {
        $identifierAndLabels = [];
        foreach ($definition->getFields() as $field) {
            $identifierAndLabels[$field['identifier']] = $field['label'];
        }

        foreach ($this->values as $row) {
            $item = [];
            foreach ($row as $key => $value) {
                if (in_array($key, $identifierAndLabels)) {
                    foreach ($identifierAndLabels as $identifier => $label) {
                        if ($label == $key) {
                            $item[$identifier] = $value;
                        }
                    }
                }
            }
            $dataset = $definition->create($item, $context);
            $definition->createDataset($dataset);
        }
    }

    public function cleanup()
    {
        @unlink($this->file);
    }
}