<?php

/**
 * Analisi (e calcolo delle modifiche) per convertire un campo di un dataset esistente in "amount" o "percentage".
 *
 * Logica pura, senza accesso a eZ o al database: riceve le righe (guid => dati) e restituisce, per ogni riga, se il
 * valore verrebbe accettato nel nuovo formato, con quale nuovo valore, o perche' verrebbe rifiutato. Lo usa lo script
 * bin/php/convert_dataset_field_type.php per il dry-run e per l'apply.
 *
 * Stati di una riga:
 *  - already: il valore e' gia' nel formato del nuovo tipo, non va riscritto
 *  - convert: il valore viene accettato e riscritto in formato canonico
 *  - empty:   il campo e' vuoto, non va riscritto
 *  - emptied: testo noto (opzione empty_values, per esempio "n.d.") che diventa campo vuoto
 *  - rejected: il valore non sarebbe accettato nel nuovo formato: va corretto prima dell'apply
 *
 * Per gli importi si segnala anche meaning_changed: il valore nuovo e' diverso da quello che la vecchia funzione di
 * normalizzazione dell'export ANAC pubblicava per lo stesso testo (per esempio "1.000", che la vecchia funzione leggeva
 * come 1,00). Dopo la conversione l'export pubblichera' il valore nuovo.
 */
class OpendataDatasetFieldConverter
{
    const STATUS_ALREADY = 'already';
    const STATUS_CONVERT = 'convert';
    const STATUS_EMPTY = 'empty';
    const STATUS_EMPTIED = 'emptied';
    const STATUS_REJECTED = 'rejected';

    /**
     * @param array $rows guid => array dei dati della riga
     * @param string $field identificatore del campo da convertire
     * @param string $type amount|percentage
     * @param array $options empty_values: testi (senza distinzione di maiuscole) da trasformare in campo vuoto;
     *                       identify_fields: identificatori dei campi che descrivono la riga nel report
     * @return array summary (conteggi), items (una voce per riga), can_apply
     * @throws InvalidArgumentException
     */
    public static function analyze(array $rows, $field, $type, array $options = [])
    {
        if (!OpendataDatasetNumberNormalizer::supports($type)) {
            throw new InvalidArgumentException("Il tipo $type non e' convertibile: usare amount o percentage");
        }

        $emptyValues = [];
        if (!empty($options['empty_values'])) {
            foreach ((array)$options['empty_values'] as $text) {
                $emptyValues[] = mb_strtolower(trim((string)$text), 'UTF-8');
            }
        }
        $identifyFields = !empty($options['identify_fields']) ? (array)$options['identify_fields'] : null;

        $summary = [
            'total' => 0,
            self::STATUS_ALREADY => 0,
            self::STATUS_CONVERT => 0,
            self::STATUS_EMPTY => 0,
            self::STATUS_EMPTIED => 0,
            self::STATUS_REJECTED => 0,
            'meaning_changed' => 0,
        ];
        $items = [];

        foreach ($rows as $guid => $row) {
            $summary['total']++;
            $raw = isset($row[$field]) ? $row[$field] : null;
            $item = [
                'guid' => (string)$guid,
                'label' => self::label($row, $field, $identifyFields),
                'old' => $raw,
                'new' => null,
                'status' => null,
                'reason' => '',
                'legacy' => null,
                'meaning_changed' => false,
            ];

            $text = $raw === null ? '' : trim((string)$raw);
            if ($text === '') {
                $item['status'] = self::STATUS_EMPTY;
            } elseif (in_array(mb_strtolower($text, 'UTF-8'), $emptyValues, true)) {
                $item['status'] = self::STATUS_EMPTIED;
                $item['new'] = '';
            } else {
                if ($type === OpendataDatasetNumberNormalizer::TYPE_AMOUNT) {
                    $item['legacy'] = self::legacyAnacAmount($text);
                }
                try {
                    $normalized = OpendataDatasetNumberNormalizer::normalize($type, $text);
                    $item['new'] = $normalized;
                    $item['status'] = ((string)$raw === $normalized) ? self::STATUS_ALREADY : self::STATUS_CONVERT;
                    if ($item['legacy'] !== null && $item['legacy'] !== $normalized) {
                        $item['meaning_changed'] = true;
                        $summary['meaning_changed']++;
                    }
                } catch (InvalidArgumentException $e) {
                    $item['status'] = self::STATUS_REJECTED;
                    $item['reason'] = $e->getMessage();
                }
            }

            $summary[$item['status']]++;
            $items[] = $item;
        }

        return [
            'summary' => $summary,
            'items' => $items,
            'can_apply' => $summary[self::STATUS_REJECTED] === 0,
        ];
    }

    /**
     * Righe da riscrivere: guid => nuovo valore del campo (convertibili e svuotate).
     *
     * @param array $analysis risultato di analyze()
     * @return array
     */
    public static function updates(array $analysis)
    {
        $updates = [];
        foreach ($analysis['items'] as $item) {
            if ($item['status'] === self::STATUS_CONVERT || $item['status'] === self::STATUS_EMPTIED) {
                $updates[$item['guid']] = $item['new'];
            }
        }

        return $updates;
    }

    /**
     * Copia della funzione di normalizzazione degli importi dell'export ANAC com'era prima della correzione
     * (Art4BisSerializer::normalizeImporto). Serve solo a mostrare nel dry-run che cosa pubblicherebbe oggi un export
     * non ancora aggiornato per lo stesso testo: non usarla per altro.
     *
     * @param string $raw
     * @return string
     */
    public static function legacyAnacAmount($raw)
    {
        $value = trim(str_replace(["\xe2\x82\xac", ' '], '', $raw));

        $hasComma = strpos($value, ',') !== false;
        $hasDot = strpos($value, '.') !== false;

        if ($hasComma && $hasDot) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        return number_format((float)$value, 2, ',', '.');
    }

    private static function label(array $row, $field, $identifyFields)
    {
        $parts = [];
        if ($identifyFields !== null) {
            foreach ($identifyFields as $identifier) {
                if (isset($row[$identifier]) && trim((string)$row[$identifier]) !== '') {
                    $parts[] = $identifier . '=' . trim((string)$row[$identifier]);
                }
            }

            return implode(', ', $parts);
        }

        foreach ($row as $identifier => $value) {
            if ($identifier === $field || is_array($value) || trim((string)$value) === '') {
                continue;
            }
            $parts[] = $identifier . '=' . trim((string)$value);
            if (count($parts) >= 3) {
                break;
            }
        }

        return implode(', ', $parts);
    }
}
