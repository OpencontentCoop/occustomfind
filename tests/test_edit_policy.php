<?php
/**
 * Test di OpendataDatasetEditPolicy::decide (chi può gestire le risorse di un dataset, anche se l'oggetto è locked).
 * Logica pura, nessun kernel eZ. Run: php tests/test_edit_policy.php
 */

require __DIR__ . '/../datatypes/opendatadataset/OpendataDatasetEditPolicy.php';

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

// decide(objectCanEdit, hasSubtreeRole, isLocked, hasDatasetEditAccess)
check('not_locked, ha content/edit: come oggi', true, OpendataDatasetEditPolicy::decide(true, false, false, false));
check('locked, ha ancora content/edit (es. amministratore)', true, OpendataDatasetEditPolicy::decide(true, false, true, false));
check('not_locked, ruolo subtree Dataset edit', true, OpendataDatasetEditPolicy::decide(false, true, false, false));
check('locked, ruolo subtree Dataset edit', true, OpendataDatasetEditPolicy::decide(false, true, true, false));
check('locked + opendatadataset/edit: nuovo caso autorizzato', true, OpendataDatasetEditPolicy::decide(false, false, true, true));
check('not_locked + solo opendatadataset/edit: nessuna regressione', false, OpendataDatasetEditPolicy::decide(false, false, false, true));
check('locked senza policy', false, OpendataDatasetEditPolicy::decide(false, false, true, false));
check('nessun permesso', false, OpendataDatasetEditPolicy::decide(false, false, false, false));

echo "\n$passed controlli ok, $failed falliti\n";
exit($failed === 0 ? 0 : 1);
