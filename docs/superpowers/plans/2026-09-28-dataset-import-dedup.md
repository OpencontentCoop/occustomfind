# Deduplicazione errata all'import di dataset — piano

> Issue di riferimento: [cms#476](https://gitlab.com/opencity-labs/sito-istituzionale/cms/-/work_items/476) — "Distinguere pagamenti con valori identici senza colonna ID a carico del redattore"

**Goal:** far sì che righe legittimamente identiche su tutti i campi (es. più pagamenti con stesso importo/beneficiario generico) sopravvivano a un import di dataset, senza chiedere al redattore di compilare a mano un identificativo per ogni riga.

**Non-goal (deliberatamente fuori scope, discusso ed escluso):** filtrare il campo `identifier` dall'export CSV/JSON generico del dataset. Quell'export è generico (usato da qualunque dataset, non solo quelli con vincoli ANAC) e mostra correttamente tutte le colonne configurate — non è un bug lì. Il problema è a monte, nei dati salvati, non nella loro esposizione: anche il nostro export ANAC dedicato (`art.4-bis`, in `openpa_bootstrapitalia`) leggerebbe lo stesso dato incompleto, perché la perdita di righe avviene *prima* di qualunque export.

## Il problema

`OpendataDatasetDefinition::generateDatasetGuid()` calcola l'identità di ogni riga così:

```php
foreach ($dataset->getDefinition()->getFields() as $field) {
    if ($field['type'] == 'identifier') {
        $fieldName = $field['identifier'];
    }
}
$key = $fieldName ? md5($dataset->getData($fieldName)) : md5(json_encode($dataset->getData()));
return $dataset->getContext()->attribute('contentclassattribute_id')
    . '_' . $dataset->getContext()->attribute('contentobject_id')
    . '_' . $key;
```

Se non è configurato un campo `type: identifier`, la chiave è l'hash dell'intera riga. Il salvataggio (`OpendataDatasetDBStorage::createDataset()` → `OcOpendataDataset`, chiave primaria composita `repository + guid`) è un upsert: stesso GUID = stessa riga, sovrascritta. Righe con valori identici su tutti i campi producono lo stesso hash, quindi lo stesso GUID, quindi si sovrascrivono a vicenda durante l'import — di N righe identiche ne sopravvive una sola.

Il workaround oggi indicato ai clienti (Freshdesk #30824, #30310) è aggiungere a mano una colonna ID e configurarla come campo `identifier`. Funziona (dà a ogni riga un'identità stabile), ma la issue lo esclude esplicitamente come soluzione: "il redattore non deve compilare identificativi a mano".

**Un problema collegato, preesistente e non introdotto da questo lavoro:** l'identità basata sull'hash del contenuto fa sì che *qualunque* correzione a una riga (es. un importo sbagliato corretto) cambi l'hash → la riga vecchia, con il valore sbagliato, non viene mai ripulita, perché nessun import successivo calcola più il suo GUID. Rilevante perché la soluzione proposta sotto lo risolve come effetto collaterale.

## Soluzione proposta

Automatizzare quello che oggi fa il workaround manuale, senza chiedere nulla al redattore. Due parti, necessarie insieme — nessuna delle due da sola risolve il problema (vedi "Perché servono entrambe" sotto):

### 1. Import a sostituzione integrale

`OpendataDatasetImportGoogleSpreadsheetConnector` ha già un flag `delete_before` ("Remove existing data before each update", righe 121-124), oggi una checkbox spenta di default nel form di import. Se attivo, `OpendataDatasetGoogleSpreadsheetImporter::import()` (righe 53-62) chiama `$definition->truncate($context)` prima di reimportare. Nessuno sviluppo qui — va solo **abilitato** per i dataset che ne hanno bisogno (decisione di configurazione, non di codice). Il flag sopravvive già agli import schedulati (`OpendataDatasetImporterRegistry::addPendingImport()`/`executePendingAction()`).

### 2. Identità di riga generata automaticamente durante l'import

In `OpendataDatasetAbstractImporter::import()` (righe 90-112), il ciclo che processa le righe non tiene traccia di quante volte una stessa combinazione di valori è già comparsa. Va aggiunto un contatore locale al singolo import (es. un array `$occurrenceByHash` inizializzato a inizio metodo, incrementato per ogni riga), passato esplicitamente lungo la catena di chiamate fino a `generateDatasetGuid()` (vedi "Assunzioni esplicite" sotto per il come) — usato nel calcolo del GUID quando non c'è un campo `identifier` configurato: `md5(json_encode($data)) . '_' . $occorrenza`.

**Importante**: dato che il punto 1 garantisce che la tabella riparta sempre vuota, questo contatore non deve essere confrontato con lo stato di import precedenti — serve solo a distinguere tra loro le righe **dello stesso import**. Questo è esattamente il vincolo che rende sicura questa soluzione.

### Perché servono entrambe le parti insieme

- Solo il contatore (punto 2), senza svuotare prima (punto 1): un import successivo in cui una delle righe duplicate è stata rimossa dal foglio lascia un residuo orfano dal giro precedente (la numerazione delle righe rimaste si sposta, non corrisponde più a quella salvata prima) — esattamente il difetto già noto e scartato dell'ipotesi "contatore posizionale puro" discussa nella issue originale.
- Solo `delete_before` (punto 1), senza il contatore (punto 2): righe duplicate nello stesso import continuano a collidere sullo stesso GUID (hash dell'intera riga, nessuna distinzione), quindi collassano comunque in una sola riga anche ripartendo da una tabella vuota — il problema originale non si risolve.

### Vincolo di sicurezza per la distribuzione: il contatore va attivato SOLO quando `delete_before` è attivo per quell'import

Cambiare `generateDatasetGuid()` in modo incondizionato (sempre aggiungere il contatore quando manca un campo `identifier`, a prescindere da `delete_before`) è **pericoloso da distribuire**: cambia il formato del GUID per ogni riga di ogni dataset che non ha un campo `identifier` configurato, non solo per le righe duplicate. Un dataset che oggi gira **senza** `delete_before` (import incrementale, upsert riga per riga) ha righe salvate con GUID = solo hash (es. `hash(A)`). Dopo il fix, lo stesso import calcolerebbe GUID nuovi con suffisso (es. `hash(A)_1`) — diversi da quelli salvati, quindi non riconosciuti come "stessa riga": ogni riga (non solo quelle duplicate) verrebbe reinserita come nuova, lasciando la vecchia come residuo orfano per sempre. Risultato: dataset con **tutte le righe raddoppiate**, un regression più grave del bug originale, su qualunque dataset con questo pattern, non solo "Dati sui pagamenti".

**Il fix quindi deve essere condizionale**: il calcolo con il contatore si attiva solo quando l'import in corso ha `delete_before` attivo. Se `delete_before` non è attivo, `generateDatasetGuid()` continua a comportarsi esattamente come oggi (solo hash, nessun contatore) — zero cambiamento di comportamento per chi non ha ancora abilitato la sostituzione integrale. Questo rende la distribuzione del fix sicura per costruzione: chi non ha `delete_before` non vede alcun effetto (il bug delle duplicate resta irrisolto per loro, ma nulla di nuovo si rompe); solo chi lo attiva ottiene il comportamento corretto, ripartendo da una tabella vuota dove il nuovo formato di GUID non ha nulla di vecchio con cui confondersi.

**Caso limite da tenere a mente, non bloccante**: se un dataset passa da `delete_before` attivo a disattivato in un secondo momento, l'import successivo tornerebbe a calcolare GUID senza suffisso, diversi da quelli (con suffisso) salvati nel frattempo — stesso tipo di duplicazione, ma nella direzione opposta. Scenario meno probabile (si disattiva una protezione già attiva), non gestito esplicitamente in questo piano.

## Assunzioni esplicite (non più punti aperti)

- **Inserimento manuale di un singolo record**: non è un caso d'uso da far convivere con `delete_before`. Chi attiva la sincronizzazione automatica da Google Sheet accetta che quella sia l'unica fonte — non serve nessuna gestione speciale in UI, è la conseguenza naturale di avere una fonte automatica.
- **Identità di riga**: hash del contenuto + contatore di occorrenza (non solo il contatore da solo). Nessun problema di performance noto — il contatore vive in memoria per la durata di un singolo import, costo trascurabile.
- **Collegamento Importer → Definition**: il contatore viene passato **esplicitamente** lungo la catena di chiamate (`create($item, $context, $occorrenza)` → il dataset lo porta con sé → `createDataset()` → `generateDatasetGuid()` lo legge), non tenuto come stato interno su `OpendataDatasetDefinition`. Motivo: quell'oggetto può essere riusato tra import diversi nello stesso processo PHP (es. `executePendingImports()`, che ne esegue più di uno in sequenza) — uno stato "appiccicato" lì rischierebbe di confondersi tra un import e l'altro.

## Rollout su "Dati sui pagamenti" (dataset che ha aperto la issue)

Deciso: niente cambio di default generale per tutti i dataset Google Sheet, e niente migrazione generica. Il fix (contatore, condizionato a `delete_before`) da solo non aiuta nessuno finché `delete_before` non è attivo sulla loro sincronizzazione — quindi per il dataset concreto dietro la issue #476 ("Dati sui pagamenti", schema ANAC `art.4-bis`) va abilitato `delete_before` mirata su **tutti i tenant che hanno quel dataset sincronizzato via Google Sheet** (non su chi lo alimenta a mano o via CSV — per loro non si applica).

Non esiste un'API né un metodo di update in-place per farlo: l'unico meccanismo di dominio, sia da admin che da codice, è `OpendataDatasetImporterRegistry::addScheduledImport()`, che cancella sempre il record esistente (`removeScheduledImport()`) e ne crea uno nuovo (righe 83-100) — lo stesso identico percorso che gira quando il redattore risalva il form a mano. Risalvataggio manuale e script fanno quindi la stessa identica operazione: cambia solo chi la invoca.

Dato il numero di tenant coinvolti, la strada è uno script, non 600 risalvataggi manuali. Esiste già un meccanismo per questo tipo di operazione cross-tenant, usato in precedenza per un caso molto simile (`utils/cct_update_pagamenti_rif_normativi.php`, ha toccato lo stesso nodo "Dati sui pagamenti" su ~170 istanze reali il 2026-05-29):

- **Repo**: `saasopenpa-distribution-prod`, cartella `utils/` — **non `occustomfind`**. Lo script vero e proprio va pianificato/scritto lì, non in questo piano (repo diverso, ciclo di rilascio diverso).
- **Pattern esistente da riusare**: script `eZScript`, dry-run di default, flag `--apply` per scrivere davvero, idempotente — eseguito con GNU `parallel` su una lista di istanze in `cron/` (vedi `utils/README.md`).
- **Logica per-istanza**: per ogni tenant, verificare se il dataset "Dati sui pagamenti" ha uno scheduled import Google Sheet attivo (query su `sqliimport_scheduled`, handler `opendatadataset_google_import`, per l'attributo di quel dataset); se sì e `delete_before` non è già `true`, richiamare `addScheduledImport()` con `delete_before` forzato a `true`; altrimenti no-op (non ha Google Sheet attivo, o ce l'ha già). Non serve una lista pre-filtrata di tenant: lo script stessa applica la condizione e salta dove non si applica, stesso schema degli script già in `utils/`.
- **Ordine**: questo script va eseguito **dopo** che il fix di `occustomfind` (contatore condizionato) è stato deployato — prima non avrebbe alcun effetto sul problema originale, limiterebbe solo la ri-scrittura della tabella ad ogni sync.

## Punti aperti, da decidere prima o durante l'implementazione

1. **Rischio di import parziale con `delete_before`** (rilevato, discusso, **deliberatamente rimandato** su richiesta di Marco — non affrontato in questo piano): `truncate()` e il ciclo di reimport in `OpendataDatasetImporterRegistry::executePendingAction()` (righe 151-184) non sono in un'unica transazione — un errore a metà ciclo lascia il dataset pubblico svuotato e reinserito solo parzialmente, senza rollback automatico, con l'azione segnata "fallita" ma i dati già a metà scritti. Non gestito qui: da riprendere come lavoro separato se/quando si decide di procedere.

## Fuori scope (deciso)

- **Import da CSV**: stesso repo (`occustomfind`) — `OpendataDatasetCsvImporter` estende la stessa `OpendataDatasetAbstractImporter`, quindi il fix del contatore si applicherebbe automaticamente anche lì. Ma l'importer da CSV oggi non ha affatto il concetto di `delete_before`, e la issue #476 riguarda specificamente l'import da Google Sheet — tenuto fuori da questo lavoro.
- **Cambio di default generale del checkbox `delete_before`** per tutti i dataset Google Sheet (non solo "Dati sui pagamenti"): non deciso qui, non necessario per risolvere la issue — il rollout mirato sopra basta per il caso concreto.

## Dove tocca il codice

**Repo `occustomfind`** (il fix vero e proprio):
- `datatypes/opendatadataset/Importer/OpendataDatasetAbstractImporter.php` — metodo `import()` (righe 90-112): il contatore vive qui (un solo ciclo, condiviso da tutti gli importer concreti che estendono questa classe), passato esplicitamente a `$definition->create()`.
- `datatypes/opendatadataset/OpendataDatasetDefinition.php` — metodo `create()`/`createDataset()` e `generateDatasetGuid()` (righe 195-208): far arrivare l'occorrenza fino al calcolo del GUID, usarla quando non c'è un campo `identifier` **e** `delete_before` è attivo per l'import in corso.
- Nessuna modifica prevista a `CustomFind/OpendataDatasetSearchableRepository.php`/`OpendataDatasetSearchableObject.php` (l'export generico, per la decisione presa sopra di non toccarlo).
- Nessuna modifica al connector Google Sheet (`OpendataDatasetImportGoogleSpreadsheetConnector.php`): il default del checkbox resta invariato, per decisione presa sopra.

**Repo `saasopenpa-distribution-prod`** (rollout, lavoro separato, da pianificare a parte quando questo fix è pronto):
- Nuovo script in `utils/` per abilitare `delete_before` sulle sincronizzazioni Google Sheet esistenti di "Dati sui pagamenti", secondo il pattern descritto sopra.
