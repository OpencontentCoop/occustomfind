<?php

abstract class OpendataDatasetAbstractImporter
{
    /**
     * Numero massimo di righe errate riportate nel messaggio di un import annullato.
     */
    const MAX_REPORTED_ERRORS = 20;

    protected $isParsed = false;

    protected $headers = [];

    protected $values = [];

    public function checkHeaders(OpendataDatasetDefinition $definition)
    {
        $this->parse();
        $atLeastOne = false;
        foreach ($definition->getFields() as $field) {
            if ($field['required'] == "true" && !in_array($field['label'], $this->getHeaders()) && !in_array($field['identifier'], $this->getHeaders())) {
                throw new Exception($field['label'] . ' is required');
            }
            if (in_array($field['label'], $this->getHeaders()) || in_array($field['identifier'], $this->getHeaders())) {
                $atLeastOne = true;
            }
        }
        if (!$atLeastOne) {
            throw new Exception('Invalid csv headers or empty data');
        }
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        $this->parse();
        return $this->headers;
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

        return new OpendataDatasetDefinition(['itemName' => 'item', 'fields' => $fields]);
    }

    /**
     * @param null $index
     * @return array
     * @throws Exception
     */
    public function getValues($index = null)
    {
        $this->parse();
        if ($index !== null) {
            return $this->values[$index];
        }

        return $this->values;
    }

    /**
     * Valida tutte le righe senza scrivere nulla. Va chiamato PRIMA di qualunque cancellazione dei dati esistenti:
     * se anche una sola riga e' errata lancia un'eccezione con i numeri di riga e i dati restano intatti.
     *
     * @param OpendataDatasetDefinition $definition
     * @param eZContentObjectAttribute $context
     * @throws Exception
     */
    public function validate(OpendataDatasetDefinition $definition, $context)
    {
        $this->parse();
        $fields = $definition->getFields();

        $errors = [];
        $line = 1; // la riga 1 e' l'intestazione, come nel foglio di calcolo
        foreach ($this->values as $row) {
            $line++;
            $item = $this->mapRowToItem($row, $fields);
            if ($this->isEmptyItem($item)) {
                continue;
            }
            try {
                $definition->create($item, $context);
            } catch (Exception $e) {
                $errors[] = 'Riga ' . $line . ': ' . $e->getMessage();
                if (count($errors) >= self::MAX_REPORTED_ERRORS) {
                    break;
                }
            }
        }
        if (!empty($errors)) {
            throw new Exception(
                'Importazione annullata, nessuna riga è stata importata e i dati esistenti non sono stati toccati. '
                . implode(' | ', $errors)
            );
        }
    }

    public function import(OpendataDatasetDefinition $definition, $context)
    {
        $this->parse();
        $fields = $definition->getFields();

        $recognizedHeaders = array_filter($this->getHeaders(), function ($header) use ($fields) {
            return $this->resolveFieldIdentifier($header, $fields) !== null;
        });
        if (count($recognizedHeaders) === 0) {
            throw new Exception('Invalid csv headers or empty data');
        }

        // Il contatore di occorrenza e' sicuro quando l'import sostituisce
        // integralmente il dataset (richiesto esplicitamente) O quando non
        // c'e' proprio nulla di preesistente con cui rischiare di confondersi
        // (es. primo import su un dataset appena creato).
        $enableOccurrenceCounter = $this->isFullReplaceImport() || $definition->isEmpty($context);

        // Prima passata: se anche una sola riga e' errata l'import si ferma prima di inserire, cosi' non restano
        // importazioni a meta' e il redattore vede quali righe correggere.
        $this->validate($definition, $context);

        $occurrenceByHash = [];
        foreach ($this->values as $row) {
            $item = $this->mapRowToItem($row, $fields);
            if ($this->isEmptyItem($item)) {
                continue;
            }
            $dataset = $definition->create($item, $context);

            $occurrence = null;
            if ($enableOccurrenceCounter) {
                $hash = md5(json_encode($dataset->getData()));
                $occurrenceByHash[$hash] = ($occurrenceByHash[$hash] ?? 0) + 1;
                $occurrence = $occurrenceByHash[$hash];
            }

            $definition->createDataset($dataset, $occurrence);
        }
    }

    /**
     * @param array $row riga grezza della sorgente (intestazione => valore)
     * @param array $fields campi della definizione
     * @return array identificatore di campo => valore
     */
    private function mapRowToItem($row, array $fields)
    {
        $item = [];
        foreach ($row as $key => $value) {
            $identifier = $this->resolveFieldIdentifier($key, $fields);
            if ($identifier !== null) {
                $item[$identifier] = $value;
            }
        }

        return $item;
    }

    /**
     * Risolve l'header della sorgente nell'identificatore del campo: prima per label, poi per identificatore
     * (coerente con checkHeaders, che accetta entrambi).
     *
     * @param string|int $header
     * @param array $fields
     * @return string|null
     */
    private function resolveFieldIdentifier($header, array $fields)
    {
        foreach ($fields as $field) {
            if ((string)$field['label'] === (string)$header) {
                return $field['identifier'];
            }
        }
        foreach ($fields as $field) {
            if ((string)$field['identifier'] === (string)$header) {
                return $field['identifier'];
            }
        }

        return null;
    }

    /**
     * Una riga e' vuota se nessun campo riconosciuto ha un valore (es. righe predisposte ma non compilate nel foglio).
     */
    private function isEmptyItem(array $item)
    {
        foreach ($item as $value) {
            if (is_array($value) ? !empty($value) : trim((string)$value) !== '') {
                return false;
            }
        }

        return true;
    }

    public function countValues()
    {
        $this->parse();
        return count($this->values);
    }

    /**
     * Se true, l'import corrente svuota il dataset prima di reimportare (es. delete_before):
     * solo in questo caso e' sicuro distinguere righe identiche con un contatore di occorrenza,
     * perche' non c'e' nessun GUID salvato da un import precedente con cui confondersi.
     */
    protected function isFullReplaceImport()
    {
        return false;
    }

    abstract public function cleanup();

    abstract public function delayImport(eZContentObjectAttribute $attribute);

    abstract protected function parse();
}
