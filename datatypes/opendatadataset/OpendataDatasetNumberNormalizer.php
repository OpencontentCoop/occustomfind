<?php

/**
 * Normalizzazione dei tipi di campo dataset "amount" (importo) e "percentage" (percentuale).
 *
 * Il dato salvato e' sempre una stringa in formato italiano, senza simboli:
 *  - amount:     formato ANAC n.nnn,dd (punto delle migliaia, virgola decimale, sempre due decimali), es. "10.000,20"
 *  - percentage: numero con virgola decimale, senza migliaia, senza zeri finali, fino a 4 decimali, es. "12,5"
 * Il simbolo (euro, %) e' solo di visualizzazione: non entra mai nel dato.
 *
 * In ingresso si accettano solo forme non ambigue, altrimenti InvalidArgumentException (l'errore arriva al redattore
 * dal form, dall'API e dall'import, che passano tutti da OpendataDatasetDefinition::create()).
 *
 * amount (dopo aver tolto euro e spazi, segno meno opzionale):
 *   1550   1550,33   1.550,33          italiano
 *   1.550  1.000.000                   solo migliaia (punto + gruppi di 3 cifre: "2.304" = 2304, come eZ con ita-IT)
 *   1550.33   1,550.33                 inglese, 1-2 decimali
 *   Non ammessi: 3+ decimali, gruppi sbagliati ("100.000.00"), un solo "1,550", testo, vuoto.
 *
 * percentage (il % finale e' opzionale, i negativi non sono ammessi; l'importo invece tollera il segno meno):
 *   12   12,5   12.5   12,5%   12,5 %   un solo separatore e' sempre il decimale ("1.234" = 1,234)
 *   1.234,5   1,234.5                  migliaia ammesse solo con parte decimale
 *   Non ammessi: piu' di 4 decimali, %% o % non finale, testo, vuoto.
 *
 * Il calcolo e' sulle stringhe, senza float, tranne toFloat() che serve solo all'indicizzazione Solr.
 */
class OpendataDatasetNumberNormalizer
{
    const TYPE_AMOUNT = 'amount';
    const TYPE_PERCENTAGE = 'percentage';

    /**
     * @param string $type
     * @return bool
     */
    public static function supports($type)
    {
        return $type === self::TYPE_AMOUNT || $type === self::TYPE_PERCENTAGE;
    }

    /**
     * @param string $type amount|percentage
     * @param mixed $raw
     * @return string
     * @throws InvalidArgumentException
     */
    public static function normalize($type, $raw)
    {
        switch ($type) {
            case self::TYPE_AMOUNT:
                return self::normalizeAmount($raw);

            case self::TYPE_PERCENTAGE:
                return self::normalizePercentage($raw);
        }

        throw new InvalidArgumentException("Type $type is not a number type");
    }

    /**
     * @param mixed $raw
     * @return string
     * @throws InvalidArgumentException
     */
    public static function normalizeAmount($raw)
    {
        $value = str_replace(["\xe2\x82\xac", "\xc2\xa0", ' ', "\t"], '', trim((string)$raw));

        $negative = false;
        if (substr($value, 0, 1) === '-') {
            $negative = true;
            $value = substr($value, 1);
        }

        $int = null;
        $dec = '';
        if ($value === '') {
            // vuoto o solo simboli
        } elseif (preg_match('/^\d+$/', $value)) {
            $int = $value;
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            $int = str_replace('.', '', $value);
        } elseif (preg_match('/^(\d+|\d{1,3}(\.\d{3})+),(\d{1,2})$/', $value, $m)) {
            $int = str_replace('.', '', $m[1]);
            $dec = $m[3];
        } elseif (preg_match('/^(\d+|\d{1,3}(,\d{3})+)\.(\d{1,2})$/', $value, $m)) {
            $int = str_replace(',', '', $m[1]);
            $dec = $m[3];
        }

        if ($int === null) {
            throw new InvalidArgumentException(
                "'" . (string)$raw . "' non è un importo valido (massimo 2 decimali, "
                . "per esempio 1550, 1550,33 o 1.550,33)"
            );
        }

        $int = ltrim($int, '0');
        if ($int === '') {
            $int = '0';
        }
        $dec = str_pad($dec, 2, '0');
        if ($int === '0' && $dec === '00') {
            $negative = false;
        }

        return ($negative ? '-' : '') . preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int) . ',' . $dec;
    }

    /**
     * @param mixed $raw
     * @return string
     * @throws InvalidArgumentException
     */
    public static function normalizePercentage($raw)
    {
        $value = str_replace("\xc2\xa0", ' ', trim((string)$raw));
        if (substr($value, -1) === '%') {
            $value = rtrim(substr($value, 0, -1));
        }

        if (preg_match('/^\s*[-\x{2212}]/u', $value)) {
            throw new InvalidArgumentException(
                "'" . (string)$raw . "' non è una percentuale valida: non può essere negativa "
                . "(per esempio 12, 12,5 o 12,5%)"
            );
        }

        $int = null;
        $dec = '';
        if (preg_match('/^\d+$/', $value)) {
            $int = $value;
        } elseif (preg_match('/^(\d+)[.,](\d{1,4})$/', $value, $m)) {
            $int = $m[1];
            $dec = $m[2];
        } elseif (preg_match('/^(\d{1,3}(?:\.\d{3})+),(\d{1,4})$/', $value, $m)) {
            $int = str_replace('.', '', $m[1]);
            $dec = $m[2];
        } elseif (preg_match('/^(\d{1,3}(?:,\d{3})+)\.(\d{1,4})$/', $value, $m)) {
            $int = str_replace(',', '', $m[1]);
            $dec = $m[2];
        }

        if ($int === null) {
            throw new InvalidArgumentException(
                "'" . (string)$raw . "' non è una percentuale valida (massimo 4 decimali, "
                . "per esempio 12, 12,5 o 12,5%)"
            );
        }

        $int = ltrim($int, '0');
        if ($int === '') {
            $int = '0';
        }
        $dec = rtrim($dec, '0');

        return $int . ($dec !== '' ? ',' . $dec : '');
    }

    /**
     * Valore salvato -> numero per l'indicizzazione. Accetta anche i dati scritti prima che il campo avesse questo tipo
     * (per esempio "€ 24.400,00"); null se vuoto o non interpretabile.
     *
     * @param string $type amount|percentage
     * @param mixed $stored
     * @return float|null
     */
    public static function toFloat($type, $stored)
    {
        if ($stored === null || trim((string)$stored) === '' || !self::supports($type)) {
            return null;
        }

        try {
            $canonical = self::normalize($type, $stored);
        } catch (InvalidArgumentException $e) {
            return null;
        }

        return (float)str_replace(',', '.', str_replace('.', '', $canonical));
    }
}
