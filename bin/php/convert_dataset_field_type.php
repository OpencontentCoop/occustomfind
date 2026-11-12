<?php
/**
 * Converte un campo di un dataset esistente in "amount" (importo) o "percentage" (percentuale).
 *
 * DRY-RUN DI DEFAULT: senza --apply non scrive nulla (ne' database, ne' indice, ne' file tranne l'eventuale --report).
 * Mostra quali righe verrebbero accettate nel nuovo formato, con quale valore, e quali verrebbero rifiutate (da
 * correggere prima). Con --apply, e solo se non ci sono righe rifiutate, cambia il tipo del campo nella definizione di
 * ogni lingua, riscrive le righe, salva un backup per il ripristino (--restore) e reindicizza il dataset.
 *
 * Esempi:
 *   php extension/occustomfind/bin/php/convert_dataset_field_type.php -sbackend --object=1573 --field=importo --type=amount
 *   php extension/occustomfind/bin/php/convert_dataset_field_type.php -sbackend --object=1573 --field=importo --type=amount --report=/tmp/importo.csv
 *   php extension/occustomfind/bin/php/convert_dataset_field_type.php -sbackend --object=1573 --field=importo --type=amount --apply
 *   php extension/occustomfind/bin/php/convert_dataset_field_type.php -sbackend --restore=<file di backup>
 */
require 'autoload.php';

$script = eZScript::instance([
    'description' => "Converte un campo di un dataset esistente in importo o percentuale (dry-run di default)\n\n",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true,
]);

$script->startup();

$options = $script->getOptions(
    '[object:][attribute:][field:][type:][apply][restore:][backup-dir:][empty-values:][report:][identify:]',
    '',
    [
        'object' => "ID dell'oggetto che contiene il dataset",
        'attribute' => "Identificatore dell'attributo di tipo dataset (default: l'unico presente nella classe)",
        'field' => 'Identificatore del campo da convertire (per esempio importo)',
        'type' => 'Nuovo tipo del campo: amount oppure percentage',
        'apply' => 'Esegue la conversione. Senza questa opzione e\' un dry-run che non scrive nulla',
        'restore' => 'Ripristina da un file di backup creato da un --apply precedente',
        'backup-dir' => 'Cartella dove salvare il backup (default: var/<vardir>/dataset_conversion_backup)',
        'empty-values' => 'Testi, separati da virgola, da trasformare in campo vuoto (per esempio "n.d.,nd,-")',
        'report' => 'Percorso di un file CSV con l\'esito di ogni riga',
        'identify' => 'Identificatori dei campi che descrivono la riga nel report, separati da virgola',
    ]
);
$script->initialize();
$script->setUseDebugAccumulators(false);

$cli = eZCLI::instance();

function dsc_fail(eZScript $script, $message, $code = 1)
{
    $script->shutdown($code, $message);
    exit($code);
}

/**
 * Attributi dataset (uno per lingua) della versione corrente dell'oggetto.
 *
 * @return eZContentObjectAttribute[] indicizzati per codice lingua
 */
function dsc_find_attributes(eZContentObject $object, $attributeIdentifier)
{
    $version = (int)$object->attribute('current_version');
    $found = [];
    $identifiers = [];
    foreach ($object->availableLanguages() as $languageCode) {
        foreach ($object->contentObjectAttributes(true, $version, $languageCode) as $attribute) {
            if ($attribute->attribute('data_type_string') !== OpendataDatasetType::DATA_TYPE_STRING) {
                continue;
            }
            $identifier = $attribute->attribute('contentclass_attribute_identifier');
            $identifiers[$identifier] = true;
            if ($attributeIdentifier === null || $attributeIdentifier === $identifier) {
                $found[$identifier][$languageCode] = $attribute;
            }
        }
    }

    if (empty($found)) {
        $message = $attributeIdentifier === null
            ? "L'oggetto non ha attributi dataset"
            : "L'attributo $attributeIdentifier non esiste o non e' di tipo dataset";
        throw new Exception($message);
    }
    if (count($found) > 1) {
        throw new Exception(
            "L'oggetto ha piu' attributi dataset (" . implode(', ', array_keys($found)) . "): indicare --attribute"
        );
    }

    return array_shift($found);
}

function dsc_load_rows($repositoryIdentifier)
{
    $rows = [];
    $offset = 0;
    $limit = 500;
    do {
        $list = OcOpendataDataset::fetchObjectList(
            OcOpendataDataset::definition(),
            null,
            ['repository' => $repositoryIdentifier],
            ['created_at' => 'asc', 'guid' => 'asc'],
            ['offset' => $offset, 'length' => $limit]
        );
        foreach ($list as $row) {
            $rows[$row->attribute('guid')] = (array)json_decode($row->attribute('data'), true);
        }
        $offset += $limit;
    } while (count($list) == $limit);

    return $rows;
}

function dsc_clear_caches($objectId)
{
    eZContentCacheManager::clearContentCacheIfNeeded($objectId);
    eZCache::clearByTag('content');
    eZCache::clearByID(['rest']);
    if (class_exists('\Opencontent\OpenApi\Loader')) {
        \Opencontent\OpenApi\Loader::instance()->getSchemaBuilder()->clearCache();
    }
}

/**
 * Ricrea l'indice Solr del dataset, leggendo la definizione appena salvata.
 *
 * @return int numero di righe indicizzate
 */
function dsc_reindex(eZContentObjectAttribute $anyAttribute, eZCLI $cli)
{
    eZContentObject::clearCache($anyAttribute->attribute('contentobject_id'));
    $fresh = eZContentObjectAttribute::fetch($anyAttribute->attribute('id'), $anyAttribute->attribute('version'));
    $repository = new OpendataDatasetSearchableRepository($fresh);
    $repository->truncate();
    $indexed = 0;
    $offset = 0;
    $length = 50;
    do {
        $items = $repository->fetchSearchableObjectList($length, $offset);
        foreach ($items as $item) {
            // un solo commit alla fine: con un commit per riga un dataset da migliaia di righe impiega minuti
            if (!$repository->index($item, false)) {
                throw new Exception('Indicizzazione fallita per la riga ' . $item->getGuid());
            }
            $indexed++;
        }
        $offset += $length;
    } while (count($items) == $length);
    OCCustomSearchableSolrResolver::solr()->commit();

    return $indexed;
}

/**
 * Cambia il tipo di un campo nella definizione salvata in data_text (resto del JSON invariato).
 */
function dsc_change_definition_type($dataText, $field, $newType)
{
    $definition = json_decode($dataText, true);
    if (!is_array($definition) || !isset($definition['fields']) || !is_array($definition['fields'])) {
        throw new Exception('Definizione del dataset non leggibile');
    }
    $changed = false;
    foreach ($definition['fields'] as $index => $definitionField) {
        if (isset($definitionField['identifier']) && $definitionField['identifier'] === $field) {
            $definition['fields'][$index]['type'] = $newType;
            $changed = true;
        }
    }
    if (!$changed) {
        throw new Exception("Il campo $field non esiste nella definizione");
    }

    return json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function dsc_current_type($dataText, $field)
{
    $definition = json_decode($dataText, true);
    if (is_array($definition) && isset($definition['fields']) && is_array($definition['fields'])) {
        foreach ($definition['fields'] as $definitionField) {
            if (isset($definitionField['identifier']) && $definitionField['identifier'] === $field) {
                return isset($definitionField['type']) ? $definitionField['type'] : null;
            }
        }
    }

    return null;
}

function dsc_write_csv($path, array $analysis, $field)
{
    $handle = fopen($path, 'w');
    if ($handle === false) {
        throw new Exception("Impossibile scrivere il report in $path");
    }
    fputcsv($handle, ['esito', 'id_riga', 'riga', "valore_attuale_$field", 'nuovo_valore', 'motivo', 'cambia_significato', 'oggi_export_anac_pubblica']);
    foreach ($analysis['items'] as $item) {
        fputcsv($handle, [
            $item['status'],
            $item['guid'],
            $item['label'],
            $item['old'],
            $item['new'],
            $item['reason'],
            $item['meaning_changed'] ? 'si' : '',
            $item['legacy'],
        ]);
    }
    fclose($handle);
}

try {
    $user = eZUser::fetchByName('admin');
    if ($user instanceof eZUser) {
        eZUser::setCurrentlyLoggedInUser($user, $user->attribute('contentobject_id'));
    }

    // ------------------------------------------------------------------------------------------ RESTORE
    if ($options['restore']) {
        $file = $options['restore'];
        if (!is_readable($file)) {
            dsc_fail($script, "File di backup non leggibile: $file");
        }
        $backup = json_decode(file_get_contents($file), true);
        if (!is_array($backup) || !isset($backup['object_id'], $backup['repository'], $backup['field'], $backup['attributes'], $backup['rows'])) {
            dsc_fail($script, 'File di backup non valido');
        }

        $cli->warning("Ripristino del campo {$backup['field']} dell'oggetto {$backup['object_id']} da $file");
        $db = eZDB::instance();
        $db->begin();
        try {
            foreach ($backup['attributes'] as $saved) {
                $attribute = eZContentObjectAttribute::fetch((int)$saved['id'], (int)$saved['version']);
                if (!$attribute instanceof eZContentObjectAttribute) {
                    throw new Exception("Attributo {$saved['id']} non trovato");
                }
                $attribute->setAttribute('data_text', $saved['data_text']);
                $attribute->store();
            }
            $restored = 0;
            foreach ($backup['rows'] as $guid => $oldValue) {
                $row = OcOpendataDataset::fetchObject(
                    OcOpendataDataset::definition(),
                    null,
                    ['repository' => $backup['repository'], 'guid' => $guid]
                );
                if (!$row instanceof OcOpendataDataset) {
                    continue;
                }
                $data = (array)json_decode($row->attribute('data'), true);
                if ($oldValue === null) {
                    unset($data[$backup['field']]);
                } else {
                    $data[$backup['field']] = $oldValue;
                }
                $row->setAttribute('data', json_encode($data));
                $row->store();
                $restored++;
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }

        $object = eZContentObject::fetch((int)$backup['object_id']);
        dsc_clear_caches((int)$backup['object_id']);
        $attributes = dsc_find_attributes($object, null);
        $indexed = dsc_reindex(reset($attributes), $cli);
        $cli->notice("Ripristino completato: $restored righe ripristinate, $indexed righe reindicizzate.");
        $script->shutdown();
        exit(0);
    }

    // ------------------------------------------------------------------------------------------ ANALISI / APPLY
    foreach (['object', 'field', 'type'] as $required) {
        if (!$options[$required]) {
            dsc_fail($script, "Manca l'opzione --$required (vedi --help)");
        }
    }
    $objectId = (int)$options['object'];
    $field = $options['field'];
    $newType = $options['type'];
    if (!OpendataDatasetNumberNormalizer::supports($newType)) {
        dsc_fail($script, "Tipo non valido: $newType (usare amount o percentage)");
    }

    $object = eZContentObject::fetch($objectId);
    if (!$object instanceof eZContentObject) {
        dsc_fail($script, "Oggetto $objectId non trovato");
    }
    $attributes = dsc_find_attributes($object, $options['attribute'] ?: null);
    $firstAttribute = reset($attributes);
    $attributeIdentifier = $firstAttribute->attribute('contentclass_attribute_identifier');
    $repositoryIdentifier = 'dataset-' . $attributeIdentifier . '-' . $objectId;

    // controlli sulla definizione di ogni lingua
    $allowedFrom = ['string', 'textarea', 'identifier', 'number', 'integer', $newType];
    foreach ($attributes as $languageCode => $attribute) {
        $currentType = dsc_current_type($attribute->attribute('data_text'), $field);
        if ($currentType === null) {
            dsc_fail($script, "Il campo $field non esiste nella definizione ($languageCode)");
        }
        if (!in_array($currentType, $allowedFrom, true)) {
            dsc_fail($script, "Il campo $field e' di tipo $currentType ($languageCode): si convertono solo campi di testo o numerici");
        }
    }

    $cli->output('');
    $cli->output(sprintf(
        "Dataset: oggetto %d \"%s\", attributo %s, campo %s -> %s",
        $objectId,
        $object->attribute('name'),
        $attributeIdentifier,
        $field,
        $newType
    ));
    $typesByLanguage = [];
    foreach ($attributes as $languageCode => $attribute) {
        $typesByLanguage[] = $languageCode . ': ' . dsc_current_type($attribute->attribute('data_text'), $field);
    }
    $cli->output('Tipo attuale per lingua -> ' . implode(', ', $typesByLanguage));

    $drafts = $object->versions(true, ['conditions' => ['status' => eZContentObjectVersion::STATUS_DRAFT]]);
    if (is_array($drafts) && count($drafts) > 0) {
        $cli->warning('Attenzione: esistono ' . count($drafts) . ' versioni in bozza. La loro definizione non viene modificata.');
    }

    $rows = dsc_load_rows($repositoryIdentifier);
    $analysis = OpendataDatasetFieldConverter::analyze($rows, $field, $newType, [
        'empty_values' => $options['empty-values'] ? explode(',', $options['empty-values']) : [],
        'identify_fields' => $options['identify'] ? array_map('trim', explode(',', $options['identify'])) : [],
    ]);
    $s = $analysis['summary'];

    $cli->output('');
    $cli->output("Righe totali:                        {$s['total']}");
    $cli->output("  gia' nel formato giusto:           {$s['already']}");
    $cli->output("  da convertire:                     {$s['convert']}");
    $cli->output("  vuote (nessuna modifica):          {$s['empty']}");
    $cli->output("  testo trasformato in vuoto:        {$s['emptied']}");
    $cli->output("  RIFIUTATE (da correggere):         {$s['rejected']}");
    if ($newType === OpendataDatasetNumberNormalizer::TYPE_AMOUNT) {
        $cli->output("  cambiano significato rispetto all'export ANAC storico: {$s['meaning_changed']}");
    }

    $shown = 0;
    foreach ($analysis['items'] as $item) {
        if ($item['status'] !== OpendataDatasetFieldConverter::STATUS_REJECTED) {
            continue;
        }
        if ($shown === 0) {
            $cli->output('');
            $cli->warning('Righe che NON verrebbero accettate:');
        }
        if ($shown < 50) {
            $cli->output(sprintf(
                "  - %s | %s = %s | %s%s",
                $item['guid'],
                $field,
                json_encode($item['old'], JSON_UNESCAPED_UNICODE),
                $item['label'],
                $item['legacy'] !== null ? " | oggi l'export ANAC pubblica {$item['legacy']}" : ''
            ));
            $cli->output('      ' . $item['reason']);
        }
        $shown++;
    }
    if ($shown > 50) {
        $cli->output('  ... e altre ' . ($shown - 50) . ' (vedi --report)');
    }

    $changed = 0;
    foreach ($analysis['items'] as $item) {
        if (!$item['meaning_changed']) {
            continue;
        }
        if ($changed === 0) {
            $cli->output('');
            $cli->warning("Righe che vengono accettate ma con un valore DIVERSO da quello che l'export ANAC storico pubblicava:");
        }
        if ($changed < 20) {
            $cli->output(sprintf(
                "  - %s | %s = %s -> nuovo %s (export storico: %s) | %s",
                $item['guid'],
                $field,
                json_encode($item['old'], JSON_UNESCAPED_UNICODE),
                $item['new'],
                $item['legacy'],
                $item['label']
            ));
        }
        $changed++;
    }
    if ($changed > 20) {
        $cli->output('  ... e altre ' . ($changed - 20) . ' (vedi --report)');
    }

    $examples = 0;
    foreach ($analysis['items'] as $item) {
        if ($item['status'] === OpendataDatasetFieldConverter::STATUS_CONVERT && !$item['meaning_changed']) {
            if ($examples === 0) {
                $cli->output('');
                $cli->output('Esempi di conversione:');
            }
            $cli->output(sprintf('  %s -> %s', json_encode($item['old'], JSON_UNESCAPED_UNICODE), $item['new']));
            if (++$examples >= 5) {
                break;
            }
        }
    }

    if ($options['report']) {
        dsc_write_csv($options['report'], $analysis, $field);
        $cli->output('');
        $cli->output('Report scritto in ' . $options['report']);
    }

    // ------------------------------------------------------------------------------------------ DRY RUN: finisce qui
    if (!$options['apply']) {
        $cli->output('');
        if ($analysis['can_apply']) {
            $cli->notice('DRY-RUN: nessuna modifica effettuata. Nessuna riga da correggere: aggiungere --apply per convertire.');
            $script->shutdown();
            exit(0);
        }
        $cli->warning("DRY-RUN: nessuna modifica effettuata. Correggere le {$s['rejected']} righe rifiutate (o usare --empty-values per i testi noti) e ripetere.");
        dsc_fail($script, '', 2);
    }

    // ------------------------------------------------------------------------------------------ APPLY
    if (!$analysis['can_apply']) {
        dsc_fail($script, "Conversione NON eseguita: ci sono {$s['rejected']} righe rifiutate. Nessuna modifica effettuata.", 2);
    }

    $updates = OpendataDatasetFieldConverter::updates($analysis);

    $alreadyTyped = true;
    foreach ($attributes as $attribute) {
        $alreadyTyped = $alreadyTyped && dsc_current_type($attribute->attribute('data_text'), $field) === $newType;
    }
    if ($alreadyTyped && empty($updates)) {
        $cli->notice("Niente da fare: il campo $field e' gia' di tipo $newType in tutte le lingue e nessuna riga va riscritta.");
        $script->shutdown();
        exit(0);
    }

    $backupDir = $options['backup-dir'] ?: eZSys::varDirectory() . '/dataset_conversion_backup';
    eZDir::mkdir($backupDir, false, true);
    $backupFile = rtrim($backupDir, '/') . '/' . $repositoryIdentifier . '-' . $field . '-' . date('Ymd-His') . '.json';
    $backup = [
        'created_at' => date('c'),
        'object_id' => $objectId,
        'repository' => $repositoryIdentifier,
        'field' => $field,
        'new_type' => $newType,
        'attributes' => [],
        'rows' => [],
    ];
    foreach ($attributes as $languageCode => $attribute) {
        $backup['attributes'][] = [
            'id' => (int)$attribute->attribute('id'),
            'version' => (int)$attribute->attribute('version'),
            'language' => $languageCode,
            'data_text' => $attribute->attribute('data_text'),
        ];
    }
    foreach ($updates as $guid => $newValue) {
        $backup['rows'][$guid] = array_key_exists($field, $rows[$guid]) ? $rows[$guid][$field] : null;
    }
    if (file_put_contents($backupFile, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
        dsc_fail($script, "Impossibile scrivere il backup in $backupFile: conversione NON eseguita");
    }
    $cli->output('');
    $cli->output("Backup salvato in $backupFile");

    $db = eZDB::instance();
    $db->begin();
    try {
        foreach ($attributes as $attribute) {
            $attribute->setAttribute(
                'data_text',
                dsc_change_definition_type($attribute->attribute('data_text'), $field, $newType)
            );
            $attribute->store();
        }
        foreach ($updates as $guid => $newValue) {
            $row = OcOpendataDataset::fetchObject(
                OcOpendataDataset::definition(),
                null,
                ['repository' => $repositoryIdentifier, 'guid' => $guid]
            );
            if (!$row instanceof OcOpendataDataset) {
                throw new Exception("Riga $guid non trovata");
            }
            $data = (array)json_decode($row->attribute('data'), true);
            $data[$field] = $newValue;
            $row->setAttribute('data', json_encode($data));
            $row->store();
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        throw new Exception('Conversione annullata, nessuna modifica salvata: ' . $e->getMessage());
    }
    $cli->output(sprintf(
        'Definizione aggiornata in %d lingue (%s) e %d righe riscritte.',
        count($attributes),
        implode(', ', array_keys($attributes)),
        count($updates)
    ));

    dsc_clear_caches($objectId);
    $indexed = dsc_reindex($firstAttribute, $cli);
    $cli->output("Reindicizzate $indexed righe.");

    // verifica finale: rileggo tutto e riclassifico
    $after = OpendataDatasetFieldConverter::analyze(dsc_load_rows($repositoryIdentifier), $field, $newType);
    $left = count(OpendataDatasetFieldConverter::updates($after));
    if ($after['summary']['rejected'] === 0 && $left === 0 && $indexed === $after['summary']['total']) {
        $cli->notice("Verifica finale: OK ({$after['summary']['total']} righe, nessuna da riscrivere).");
        $cli->output("Per annullare: php extension/occustomfind/bin/php/convert_dataset_field_type.php -s<siteaccess> --restore=$backupFile");
    } else {
        $cli->error("Verifica finale NON superata: da riscrivere $left, rifiutate {$after['summary']['rejected']}, indicizzate $indexed su {$after['summary']['total']}.");
        $cli->output("Per annullare: --restore=$backupFile");
        dsc_fail($script, '', 1);
    }

    $script->shutdown();
} catch (Exception $e) {
    $errCode = $e->getCode();
    $errCode = $errCode != 0 ? $errCode : 1;
    $script->shutdown($errCode, $e->getMessage());
}
