<?php
/**
 * Test di OpendataDatasetNumberNormalizer (tipi di campo "amount" e "percentage").
 * Logica pura, nessun kernel eZ. Run: php tests/test_number_normalizer.php
 */

require __DIR__ . '/../datatypes/opendatadataset/OpendataDatasetNumberNormalizer.php';

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
function run($type, $raw)
{
    try {
        return OpendataDatasetNumberNormalizer::normalize($type, $raw);
    } catch (InvalidArgumentException $e) {
        return 'EXC';
    }
}

// ================================================================ AMOUNT (formato ANAC n.nnn,dd)
$amount = [
    '10000' => '10.000,00', '2304' => '2.304,00', '0' => '0,00', '007' => '7,00', '999' => '999,00',
    '1000,20' => '1.000,20', '10000,20' => '10.000,20', '2304,5' => '2.304,50', '0,5' => '0,50', '1,55' => '1,55',
    '1.550,33' => '1.550,33', '24.400,00' => '24.400,00', '1.234.567,89' => '1.234.567,89', '0,00' => '0,00',
    '1.000' => '1.000,00', '2.304' => '2.304,00', '100.000' => '100.000,00',
    '100.000.000' => '100.000.000,00', '1.000.000' => '1.000.000,00',
    // formato inglese non ammesso: il formato e' sempre 100,00
    '1550.33' => 'EXC', '10000.20' => 'EXC', '2.30' => 'EXC', '1.5' => 'EXC', '100.00' => 'EXC',
    '1,550.33' => 'EXC', '1,234,567.89' => 'EXC',
    '€ 12.688,40' => '12.688,40', '€ 1.464,00' => '1.464,00', '1.464,00 €' => '1.464,00', '€1464' => '1.464,00',
    '10 000,50' => '10.000,50', "10\xc2\xa0000,50" => '10.000,50', '  500  ' => '500,00',
    '-500,5' => '-500,50', '-€ 1.000,00' => '-1.000,00', '-0,00' => '0,00',
    // non interpretabili
    '100.000.00' => 'EXC', '1.000,5,3' => 'EXC', '1000,2,3' => 'EXC', '1,550' => 'EXC', '1,550,000' => 'EXC',
    '1.550,333' => 'EXC', '2304,567' => 'EXC', '1.00.000' => 'EXC', '1000.000,50' => 'EXC',
    '1550.333' => 'EXC', '1234.5678' => 'EXC', '1,550.333' => 'EXC', '1.550.33' => 'EXC',
    'abc' => 'EXC', '' => 'EXC', '   ' => 'EXC', 'n.d.' => 'EXC', 'EUR 100' => 'EXC', '1e3' => 'EXC',
    '--5' => 'EXC', '5-' => 'EXC', '€' => 'EXC', ',50' => 'EXC', '.50' => 'EXC', '12,' => 'EXC', '12%' => 'EXC',
];
foreach ($amount as $input => $expected) {
    check("amount '" . $input . "'", $expected, run('amount', (string)$input));
    if ($expected !== 'EXC') {
        check("amount idempotenza '" . $input . "'", $expected, run('amount', $expected));
    }
}

// ================================================================ PERCENTAGE (numero con virgola, senza %)
$percentage = [
    // con e senza simbolo
    '12,5%' => '12,5', '12,5 %' => '12,5', '12.5%' => '12,5', '12,5' => '12,5', '12' => '12', '12%' => '12',
    "12\xc2\xa0%" => '12', ' 12 ' => '12', '  12,5  %  ' => '12,5',
    // zero, oltre 100, negativi
    '0' => '0', '0%' => '0', '0,0' => '0', '100' => '100', '150%' => '150', '1000,5%' => '1000,5',
    // negativi ammessi (anche segno U+2212); "-0" diventa "0"
    '-5,5%' => '-5,5', '-5' => '-5', '-0%' => '0', '-0,0' => '0', '-12,5 %' => '-12,5', '- 5' => '-5',
    "\xe2\x88\x925,5" => '-5,5', '-1.234,5%' => '-1234,5', '-1,234.5' => '-1234,5', '-33.33' => '-33,33',
    '--5' => 'EXC', '-' => 'EXC', '-%' => 'EXC', '5-' => 'EXC', '-5%%' => 'EXC', '-abc' => 'EXC', '+5' => 'EXC', '-12,12345' => 'EXC',
    // zeri finali tolti, fino a 4 decimali
    '12,50' => '12,5', '12,500' => '12,5', '12,0' => '12', '0,0001' => '0,0001', '12,1234' => '12,1234', '12,10%' => '12,1',
    // un solo separatore e' sempre il decimale
    '1.234' => '1,234', '1,234' => '1,234', '0.5' => '0,5', '33.33%' => '33,33', '99,99' => '99,99',
    // migliaia solo se c'e' una parte decimale
    '1.234,5%' => '1234,5', '1,234.5' => '1234,5', '12.345,67' => '12345,67',
    // non interpretabili
    '' => 'EXC', '   ' => 'EXC', '%' => 'EXC', '%12' => 'EXC', '12%%' => 'EXC', '12 % 5' => 'EXC',
    'abc' => 'EXC', 'dodici' => 'EXC', '12,5,5' => 'EXC', '12..5' => 'EXC', '1.2.3' => 'EXC', '12,' => 'EXC', ',5' => 'EXC',
    '.5' => 'EXC', '1e2' => 'EXC', '--5' => 'EXC', '12,12345' => 'EXC', '1.234.567' => 'EXC', '€ 12' => 'EXC', 'n.d.' => 'EXC',
];
foreach ($percentage as $input => $expected) {
    check("percentage '" . $input . "'", $expected, run('percentage', (string)$input));
    if ($expected !== 'EXC') {
        check("percentage idempotenza '" . $input . "'", $expected, run('percentage', $expected));
    }
}

// ================================================================ tipo sconosciuto e is-supported
check('tipo non gestito', 'EXC', run('string', '12'));
check('supports amount', true, OpendataDatasetNumberNormalizer::supports('amount'));
check('supports percentage', true, OpendataDatasetNumberNormalizer::supports('percentage'));
check('supports number (non gestito)', false, OpendataDatasetNumberNormalizer::supports('number'));
check('supports string (non gestito)', false, OpendataDatasetNumberNormalizer::supports('string'));

// ================================================================ toFloat: valore salvato -> numero per Solr
$floats = [
    ['amount', '10.000,20', 10000.2], ['amount', '2.304,00', 2304.0], ['amount', '0,00', 0.0], ['amount', '-1.000,50', -1000.5],
    ['amount', '1.234.567,89', 1234567.89], ['amount', '5,00', 5.0],
    ['percentage', '12,5', 12.5], ['percentage', '12', 12.0], ['percentage', '0', 0.0],
    ['percentage', '1000,5', 1000.5], ['percentage', '0,0001', 0.0001],
];
foreach ($floats as $f) {
    check("toFloat {$f[0]} '{$f[1]}'", $f[2], OpendataDatasetNumberNormalizer::toFloat($f[0], $f[1]));
}
check('toFloat percentuale negativa: indicizzata', -5.5, OpendataDatasetNumberNormalizer::toFloat('percentage', '-5,5'));
check('toFloat importo negativo: indicizzato', -500.5, OpendataDatasetNumberNormalizer::toFloat('amount', '-500,50'));
check('toFloat vuoto', null, OpendataDatasetNumberNormalizer::toFloat('amount', ''));
check('toFloat null', null, OpendataDatasetNumberNormalizer::toFloat('percentage', null));
check('toFloat testo non valido (dato legacy)', null, OpendataDatasetNumberNormalizer::toFloat('amount', 'n.d.'));
check('toFloat dato legacy con euro', 24400.0, OpendataDatasetNumberNormalizer::toFloat('amount', '€ 24.400,00'));

// ================================================================ proprieta': stessa cifra in piu' forme => stesso valore, round trip con toFloat
mt_srand(7);
for ($i = 0; $i < 3000; $i++) {
    $digits = mt_rand(1, 12);
    $int = (string)mt_rand(0, 9) . ($digits > 1 ? substr((string)mt_rand(), 0, $digits - 1) : '');
    $int = ltrim($int, '0') === '' ? '0' : ltrim($int, '0');
    $cents = mt_rand(0, 99);
    $dec = str_pad((string)$cents, 2, '0', STR_PAD_LEFT);
    $anac = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int) . ',' . $dec;
    $it = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int);
    $en = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $int);
    $variants = [$int . ',' . $dec, $it . ',' . $dec, '€ ' . $it . ',' . $dec, $it . ',' . $dec . ' €'];
    // formato inglese sempre rifiutato
    foreach ([$int . '.' . $dec, $en . '.' . $dec] as $english) {
        run('amount', $english) === 'EXC' ? $passed++ : check("amount inglese rifiutato '" . $english . "'", 'EXC', run('amount', $english));
    }
    if ($cents === 0) {
        $variants[] = $int;
        $variants[] = $it;
    }
    foreach ($variants as $variant) {
        $got = run('amount', $variant);
        $got === $anac ? $passed++ : check("amount proprieta' '" . $variant . "'", $anac, $got);
    }
    // round trip: il numero indicizzato corrisponde alla cifra digitata (entro 2 decimali)
    $expectedFloat = round((float)($int . '.' . $dec), 2);
    $gotFloat = OpendataDatasetNumberNormalizer::toFloat('amount', $anac);
    abs($gotFloat - $expectedFloat) < 0.005 ? $passed++ : check("amount toFloat '" . $anac . "'", $expectedFloat, $gotFloat);
}
for ($i = 0; $i < 2000; $i++) {
    $int = (string)mt_rand(0, 100000);
    $decimals = mt_rand(0, 4);
    $dec = $decimals ? str_pad((string)mt_rand(0, (int)pow(10, $decimals) - 1), $decimals, '0', STR_PAD_LEFT) : '';
    $canonical = $int . ($dec !== '' && (int)$dec !== 0 ? ',' . rtrim($dec, '0') : '');
    foreach ([$int . ($dec !== '' ? ',' . $dec : ''), $int . ($dec !== '' ? '.' . $dec : '') . '%', $int . ($dec !== '' ? ',' . $dec : '') . ' %'] as $variant) {
        // un intero che sembra migliaia con un solo punto non e' generato: usiamo sempre la virgola o il punto con decimali
        $got = run('percentage', $variant);
        $got === $canonical ? $passed++ : check("percentage proprieta' '" . $variant . "'", $canonical, $got);
    }
    $expectedFloat = (float)($int . '.' . ($dec === '' ? '0' : $dec));
    $gotFloat = OpendataDatasetNumberNormalizer::toFloat('percentage', $canonical);
    abs($gotFloat - $expectedFloat) < 0.000001 ? $passed++ : check("percentage toFloat '" . $canonical . "'", $expectedFloat, $gotFloat);
}

// ================================================================ messaggi d'errore
try {
    OpendataDatasetNumberNormalizer::normalize('amount', '100.000.00');
    check('eccezione attesa (amount)', true, false);
} catch (InvalidArgumentException $e) {
    check('amount: messaggio contiene il valore', true, strpos($e->getMessage(), '100.000.00') !== false);
    check('amount: messaggio contiene un esempio', true, strpos($e->getMessage(), '1.550,33') !== false);
}
try {
    OpendataDatasetNumberNormalizer::normalize('percentage', 'abc');
    check('eccezione attesa (percentage)', true, false);
} catch (InvalidArgumentException $e) {
    check('percentage: messaggio contiene il valore', true, strpos($e->getMessage(), 'abc') !== false);
    check('percentage: messaggio contiene un esempio', true, strpos($e->getMessage(), '12,5') !== false);
}

// ================================================================ PATTERN DEL FORM (controllo mentre si digita)
// Il pattern e' JavaScript; qui si traduce l'unica sequenza diversa (\u2212) per provarlo con PCRE.
// Regola: il pattern non deve mai accettare cio' che normalize() rifiuta, e deve accettare tutto cio' che
// normalize() restituisce e ogni forma documentata.
$toPcre = function ($type) {
    return '/' . str_replace('/', '\\/', str_replace('\\u2212', '\\x{2212}', OpendataDatasetNumberNormalizer::formPattern($type))) . '/u';
};
mt_srand(11);
$alphabet = ['0', '1', '2', '5', '9', '.', ',', '-', ' ', '%', "\xe2\x82\xac", "\xe2\x88\x92", 'a', '+'];
foreach (['amount', 'percentage'] as $type) {
    $re = $toPcre($type);
    $tooLoose = 0;
    $tooStrictOnDocumented = 0;
    for ($i = 0; $i < 20000; $i++) {
        $s = '';
        for ($j = mt_rand(0, 9); $j > 0; $j--) {
            $s .= $alphabet[mt_rand(0, count($alphabet) - 1)];
        }
        if (preg_match($re, $s) && run($type, $s) !== 'EXC') {
            continue;
        }
        if (preg_match($re, $s)) {
            $tooLoose++;
        }
    }
    check("pattern $type: mai piu' largo del server", 0, $tooLoose);
}
$documented = [
    'amount' => ['1550', '1550,33', '1.550,33', '1.550', '€ 1.550,33', '-500,50', '-€ 500,50', '€ -500,50', '10 000,50', "1\xc2\xa0550", ' 12 ', '0', '0,5'],
    'percentage' => ['12', '12,5', '12.5', '12,5%', '12,5 %', '-5,5%', "\xe2\x88\x925", '1.234,5', '1,234.5', '0', '-0', ' 12 % '],
];
foreach ($documented as $type => $values) {
    foreach ($values as $v) {
        check("pattern $type accetta '$v'", 1, preg_match($toPcre($type), $v));
        check("normalize $type accetta '$v'", true, run($type, $v) !== 'EXC');
    }
}
foreach (['amount' => ['', 'abc', '100.00', '1550.33', '1,550.33', '100.000.00', '1,550', '12,345', '--5', '5-', '€', '1,2,3', '12%'], 'percentage' => ['', 'abc', '12,12345', '12%%', '%12', '--5', '5-', '1.2.3', '12,5,5', '€12']] as $type => $values) {
    foreach ($values as $v) {
        check("pattern $type rifiuta '$v'", 0, preg_match($toPcre($type), $v));
    }
}

echo "\n$passed controlli ok, $failed falliti\n";
exit($failed === 0 ? 0 : 1);
