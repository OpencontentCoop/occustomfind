<?php
/**
 * Test di OpendataDatasetFieldConverter (analisi delle righe per la conversione di un campo in importo/percentuale).
 * Logica pura, nessun kernel eZ. Run: php tests/test_field_converter.php
 */

require __DIR__ . '/../datatypes/opendatadataset/OpendataDatasetNumberNormalizer.php';
require __DIR__ . '/../datatypes/opendatadataset/OpendataDatasetFieldConverter.php';

$passed = 0;
$failed = 0;
function check($label, $expected, $actual)
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL $label\n  atteso:   " . var_export($expected, true) . "\n  ottenuto: " . var_export($actual, true) . "\n";
}

function rows(array $values, $field = 'importo')
{
    $rows = [];
    foreach ($values as $i => $v) {
        $rows['g' . $i] = ['anno' => '2025', 'beneficiario' => 'Beneficiario ' . $i, $field => $v];
    }

    return $rows;
}

function byGuid(array $analysis)
{
    $map = [];
    foreach ($analysis['items'] as $item) {
        $map[$item['guid']] = $item;
    }

    return $map;
}

// ---------------------------------------------------------------- classificazione importo
$values = ['€ 1.464,00', '1.000', '2500', 'n.d.', '', null, '12.688,40 €', '100.000.00', '1.234,5678', '1.550,33', '-500,5', 'abc'];
$a = OpendataDatasetFieldConverter::analyze(rows($values), 'importo', 'amount');
$m = byGuid($a);

check('g0 convertibile', 'convert', $m['g0']['status']);
check('g0 nuovo valore', '1.464,00', $m['g0']['new']);
check('g1 "1.000" convertibile', 'convert', $m['g1']['status']);
check('g1 nuovo valore', '1.000,00', $m['g1']['new']);
check('g1 cambia significato rispetto all\'export storico', true, $m['g1']['meaning_changed']);
check('g1 export storico', '1,00', $m['g1']['legacy']);
check('g2 "2500"', '2.500,00', $m['g2']['new']);
check('g2 non cambia significato', false, $m['g2']['meaning_changed']);
check('g3 "n.d." rifiutato', 'rejected', $m['g3']['status']);
check('g3 motivo presente', true, $m['g3']['reason'] !== '');
check('g3 oggi l\'export pubblicherebbe', '0,00', $m['g3']['legacy']);
check('g4 stringa vuota', 'empty', $m['g4']['status']);
check('g5 null', 'empty', $m['g5']['status']);
check('g6 "12.688,40 €"', '12.688,40', $m['g6']['new']);
check('g7 "100.000.00" rifiutato', 'rejected', $m['g7']['status']);
check('g8 4 decimali rifiutato', 'rejected', $m['g8']['status']);
check('g9 gia\' canonico', 'already', $m['g9']['status']);
check('g10 negativo tollerato', '-500,50', $m['g10']['new']);
check('g11 "abc" rifiutato', 'rejected', $m['g11']['status']);

check('conteggio righe', 12, $a['summary']['total']);
check('conteggio convertibili', 5, $a['summary']['convert']);   // g0 g1 g2 g6 g10
check('conteggio rifiutate', 4, $a['summary']['rejected']);
check('conteggio vuote', 2, $a['summary']['empty']);
check('conteggio gia\' ok', 1, $a['summary']['already']);
check('conteggio cambia significato', 1, $a['summary']['meaning_changed']);
check('non applicabile con rifiutate', false, $a['can_apply']);

// ---------------------------------------------------------------- empty_values (opt-in)
$a2 = OpendataDatasetFieldConverter::analyze(rows(['n.d.', 'N.D.', 'nd', '-', '1000', 'abc']), 'importo', 'amount', ['empty_values' => ['n.d.', 'nd', '-']]);
$m2 = byGuid($a2);
check('n.d. -> svuotato', 'emptied', $m2['g0']['status']);
check('N.D. (maiuscolo) -> svuotato', 'emptied', $m2['g1']['status']);
check('nd -> svuotato', 'emptied', $m2['g2']['status']);
check('- -> svuotato', 'emptied', $m2['g3']['status']);
check('svuotato: nuovo valore vuoto', '', $m2['g0']['new']);
check('1000 resta convertibile', 'convert', $m2['g4']['status']);
check('abc resta rifiutato anche con empty_values', 'rejected', $m2['g5']['status']);
check('conteggio svuotate', 4, $a2['summary']['emptied']);

// ---------------------------------------------------------------- applicabile senza rifiutate
$a3 = OpendataDatasetFieldConverter::analyze(rows(['1000', '€ 5,5', '', '1.234,56']), 'importo', 'amount');
check('senza rifiutate: applicabile', true, $a3['can_apply']);
check('updates: solo righe da riscrivere', ['g0' => '1.000,00', 'g1' => '5,50'], OpendataDatasetFieldConverter::updates($a3));
$a3b = OpendataDatasetFieldConverter::analyze(rows(['n.d.', '1000']), 'importo', 'amount', ['empty_values' => ['n.d.']]);
check('updates include le righe svuotate', ['g0' => '', 'g1' => '1.000,00'], OpendataDatasetFieldConverter::updates($a3b));

// ---------------------------------------------------------------- idempotenza: rianalizzare il risultato non cambia nulla
$converted = [];
foreach (rows(['€ 1.464,00', '2500', '1550,33', '', '12.688,40 €', '-500,5']) as $guid => $row) {
    $converted[$guid] = $row;
}
$first = OpendataDatasetFieldConverter::analyze($converted, 'importo', 'amount');
foreach (OpendataDatasetFieldConverter::updates($first) as $guid => $new) {
    $converted[$guid]['importo'] = $new;
}
$second = OpendataDatasetFieldConverter::analyze($converted, 'importo', 'amount');
check('seconda analisi: nulla da riscrivere', [], OpendataDatasetFieldConverter::updates($second));
check('seconda analisi: nessun rifiutato', 0, $second['summary']['rejected']);

// ---------------------------------------------------------------- percentuale
$ap = OpendataDatasetFieldConverter::analyze(rows(['12,5 %', '10%', '-20', '0', 'n.d.', '150', '33.333', ''], 'quota'), 'quota', 'percentage');
$mp = byGuid($ap);
check('perc: "12,5 %"', '12,5', $mp['g0']['new']);
check('perc: "10%"', '10', $mp['g1']['new']);
check('perc: -20 rifiutato (negativa)', 'rejected', $mp['g2']['status']);
check('perc: -20 motivo parla di negativa', true, strpos($mp['g2']['reason'], 'negativ') !== false);
check('perc: "0"', 'already', $mp['g3']['status']);
check('perc: n.d. rifiutato', 'rejected', $mp['g4']['status']);
check('perc: 150 gia\' ok', 'already', $mp['g5']['status']);
check('perc: "33.333" (un solo punto = decimale)', '33,333', $mp['g6']['new']);
check('perc: vuoto', 'empty', $mp['g7']['status']);
check('perc: nessun confronto con l\'export storico', null, $mp['g0']['legacy']);
check('perc: meaning_changed sempre falso', false, $mp['g0']['meaning_changed']);

// ---------------------------------------------------------------- identificazione della riga nel report
$rowsId = ['x1' => ['anno' => '2025', 'trimestre' => '1', 'beneficiario' => 'Soggetto estero', 'importo' => 'abc']];
$aid = OpendataDatasetFieldConverter::analyze($rowsId, 'importo', 'amount', ['identify_fields' => ['anno', 'trimestre', 'beneficiario']]);
check('etichetta riga con i campi indicati', 'anno=2025, trimestre=1, beneficiario=Soggetto estero', $aid['items'][0]['label']);
$aid2 = OpendataDatasetFieldConverter::analyze($rowsId, 'importo', 'amount');
check('etichetta di default: primi campi non vuoti, escluso il campo convertito', true, strpos($aid2['items'][0]['label'], 'importo') === false && $aid2['items'][0]['label'] !== '');

// ---------------------------------------------------------------- funzione storica ANAC (solo per il confronto)
$legacy = ['10000' => '10.000,00', '1.550,33' => '1.550,33', '1.000' => '1,00', '100.000.000' => '100,00', 'abc' => '0,00', '' => '0,00', '€ 12.688,40' => '12.688,40', '1550.33' => '1.550,33', '1,550.33' => '1.550,33'];
foreach ($legacy as $in => $out) {
    check("legacy '" . $in . "'", $out, OpendataDatasetFieldConverter::legacyAnacAmount((string)$in));
}

// ---------------------------------------------------------------- tipo non supportato
try {
    OpendataDatasetFieldConverter::analyze(rows(['1']), 'importo', 'string');
    check('tipo string non convertibile', true, false);
} catch (InvalidArgumentException $e) {
    check('tipo string non convertibile', true, true);
}

// ---------------------------------------------------------------- proprieta' su 3000 righe casuali
mt_srand(11);
$vals = [];
for ($i = 0; $i < 3000; $i++) {
    $int = (string)mt_rand(0, 99999999);
    $cents = str_pad((string)mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
    $forms = [$int . ',' . $cents, '€ ' . preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int) . ',' . $cents, $int . '.' . $cents];
    $vals[] = $forms[mt_rand(0, 2)];
}
$big = OpendataDatasetFieldConverter::analyze(rows($vals), 'importo', 'amount');
check('3000 righe: nessuna rifiutata', 0, $big['summary']['rejected']);
check('3000 righe: tutte applicabili', true, $big['can_apply']);
$rowsBig = rows($vals);
foreach (OpendataDatasetFieldConverter::updates($big) as $guid => $new) {
    $rowsBig[$guid]['importo'] = $new;
}
$bigAgain = OpendataDatasetFieldConverter::analyze($rowsBig, 'importo', 'amount');
check('3000 righe: idempotente', [], OpendataDatasetFieldConverter::updates($bigAgain));

echo "\n$passed controlli ok, $failed falliti\n";
exit($failed === 0 ? 0 : 1);
