## OpenContent Custom Find

L'estensione permette di indicizzare in solr contenuti custom che non sono eZContentObject.
E' utile per creare visualizzazioni ed effettuare ricerche su tabelle esterne.

### Installation

Abilita l'estensione. Rigenera gli autoloads. Pulisci la cache.


### Esempio di utilizzo

Si vuole, ad esempio, indicizzare un elenco telefonico che si possiede in un file csv e che non si intende importare come ogggetti ez
ma i cui si vuole poter effettuare delle ricerche.

#### Abilitare il repository in `occustomfind.ini.append.php` del tuo siteaccess o di override

Si abilita inserendo identificatore e classe php del repository.
```
[Settings]
AvailableRepositories[elenco_telefonico]=ElencoTelefonicoSearchableRepository
```

Prima di creare il repository creiamo la classe che rappresenta un elemento dell'elenco telefonico

#### Creare la classe `ElencoTelefonicoSearchableObject`
La classe implementa `OCCustomSearchableObjectInterface` ed è la rappresentazione di un elemento dell'elenco telefonico

```php
class ElencoTelefonicoSearchableObject implements OCCustomSearchableObjectInterface
{
    private $id;
    private $nome;
    private $cognome;
    private $numeriDiTelefono;
    private $note;

    /**
     * Il costruttore è liberamente definibile perché l'interfaccia non lo contempla.
     *
     * @param $id
     * @param $nome
     * @param $cognome
     * @param $numeriDiTelefono
     * @param $note
     */
    public function __construct($id, $nome, $cognome, $numeriDiTelefono, $note)
    {
        $this->nome = $nome;
        $this->cognome = $cognome;
        $this->numeriDiTelefono = $numeriDiTelefono;
        $this->note = $note;
    }

    /**
     * Questo meteodo deve resituire una stringa (bada bene non un numero) che identifica il documento univocamente in solr
     *
     * @return string
     */
    public function getGuid()
    {
        return return 'elenco-telefonico-' . $this->id;
    }

    /**
     * Questo metodo serve a definire i campi che solr deve indicizzare
     * Deve resituire un array di OCCustomSearchableFieldInterface per comodità conviene usare OCCustomSearchableField
     *
     * OCCustomSearchableField::create è una scorciatoia per
     * $field = new OCCustomSearchableField;
     * $field->setName($name)->setType($type)->isMultiValue($multiValue);
     *
     * @return OCCustomSearchableFieldInterface[]
     */
    public static function getFields()
    {
        return array(
            OCCustomSearchableField::create('id', 'int'),

            OCCustomSearchableField::create('cognome', 'string'),

            OCCustomSearchableField::create('nome', 'text'),

            // scorciatoia per isMultiValue vedi OCCustomSearchableField::setType
            OCCustomSearchableField::create('numeriDiTelefono', 'string[]'),

            OCCustomSearchableField::create('note', 'text'),
        );
    }

    /**
     * Restituisce il valore del campo presente in $field
     *
     * @param OCCustomSearchableFieldInterface $field
     *
     * @return mixed
     */
    public function getFieldValue(OCCustomSearchableFieldInterface $field)
    {
        if ($field->getName() == 'id'){
            return $this->id;

        }elseif ($field->getName() == 'cognome') {
            return $this->cognome;

        }elseif ($field->getName() == 'nome') {
            return $this->nome;

        }elseif ($field->getName() == 'numeriDiTelefono') {
            return $this->numeriDiTelefono;

        }elseif ($field->getName() == 'note') {
            return $this->note;
        }

        return null;
    }

    /**
     * Restiruisce la rappresentazione dell'oggetto come array
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'id' => $this->id,
            'cognome' => $this->cognome,
            'nome' => $this->nome,
            'numeriDiTelefono' => $this->numeriDiTelefono,
            'note' => $this->note,
        );
    }

    /**
     * Crea l'oggetto a partire da un array
     *
     * @param $array
     *
     * @return ElencoTelefonicoSearchableObject
     */
    public static function fromArray($array)
    {
        extract($array);
        return new ElencoTelefonicoSearchableObject($id, $nome, $cognome, $numeriDiTelefono, $note);
    }

}
```

Tuttavia se si ha già una rappresentazione dell'oggetto in array chiave -> valore è possibile usare la classe astratta `OCCustomSearchableObjectAbstract`
Il risultato sarà più veloce

```php
class ElencoTelefonicoSearchableObject extends OCCustomSearchableObjectAbstract
{

    public function getGuid()
    {
        return 'elenco-telefonico-' . $this->attributes['id'];
    }

    public static function getFields()
    {
        return array(
            OCCustomSearchableField::create('id', 'int'),
            OCCustomSearchableField::create('cognome', 'string'),
            OCCustomSearchableField::create('nome', 'text'),
            OCCustomSearchableField::create('numeriDiTelefono', 'string[]'),
            OCCustomSearchableField::create('note', 'text'),
        );
    }
}
```


#### Creare la classe `ElencoTelefonicoSearchableRepository`
La classe deve implemetare l'interfaccia `OCCustomSearchableRepositoryInterface`, ma tutto il lavoro sporco lo fa già
la classe `OCCustomSearchableRepositoryAbstract` quindi per non rifare cose conviene estendere quest'ultima ma anche darne un'occhiata al codice...

```php
class ElencoTelefonicoSearchableRepository extends OCCustomSearchableRepositoryAbstract
{
    private $csvFile;

    private $csvRows;

    /**
     * Nel costruttore salvo il nome del file csv da usare
     * Questo è solo un esempio, immagina che il nome del file csv venga caricato tramite ini
     * Tuttavia il costruttore non può avere argomenti (non abbiamo DependyInjection qui...)
     */
    public function __construct()
    {
        $this->csvFile = 'elenco_telefonico.csv';
    }

    /**
     * Parsa il file e restituisce le righe
     * @return array
     */
    private function getCsvRows()
    {
        if ($this->csvRows === null) {
            // il metodo parseFile deve parsare il file e restuire un array di righe
            // in questo esempio non è implementato
            $this->csvRows = $this->parseFile($this->csvFile);
        }

        return $this->csvRows;
    }

    /**
     * Questo metodo deve restituire la stringa dell'identificativo del repository, meglio usare quello definito nella chiave dell'ini
     */
    public function getIdentifier()
    {
        return 'elenco_telefonico';
    }

    /**
     * Questo campo deve restituire il FQCN della classe che si vuole indicizzare (creata sopra)
     */
    public function availableForClass()
    {
        return ElencoTelefonicoSearchableObject::class;
    }

    /**
     * Ritorna il numero totale di oggetti indicizzabili
     * Vedi il file bin/php/updatecustomsearchindex.php
     */
    public function countSearchableObjects()
    {
        return count($this->getCsvRows());
    }

    /**
     * Restiruisce un array di ElencoTelefonicoSearchableObject
     * Vedi il file bin/php/updatecustomsearchindex.php
     *
     * Il repository deve essere paginato: in questo esempio viene simulata la paginazione
     *
     * I metodi richiamati non sono implemetati
     *
     * @param int $limit
     * @param int $offset
     *
     * @return ElencoTelefonicoSearchableObject[]
     */
    public function fetchSearchableObjectList($limit, $offset)
    {
        $data = array();
        foreach($this->getCsvRows() as $index => $row) {

            if ($index < $offset){
                continue;
            }

            if (count($data) == $limit) {
                break;
            }

            $data[] = new ElencoTelefonicoSearchableObject(
                $this->getIdFromRow($row),
                $this->getNomeFromRow($row),
                $this->getCognomeFromRow($row),
                $this->getNumeriDiTelefonoFromRow($row),
                $this->getNoteFromRow($row)
            );

            // se invece usiamo l'approccio ad array si farà qualcosa di simile
            // $data[] = new ElencoTelefonicoSearchableObject($this->getArrayFromRow($row));
        }

        return $data;
    }

}
```


#### Indicizzare il repository da script

```bash
php extension/occustomfind/bin/php/updatecustomsearchindex.php -sbackend --repository=elenco_telefonico
```

#### Eseguire una ricerca
Per eseguire una ricerca da php occorre usare il metodo `find` del repository a cui passare un oggetto di classe `OCCustomSearchParameters`

Il risultato è un array in cui valori sono:
 - `totalCount` il totale di tutti i ElencoTelefonicoSearchableObject contemplati dalla ricerca
 - `searchHits` un array di ElencoTelefonicoSearchableObject
 - `facets` un array con chiave il campo e valore l'hash nome=>conteggio

```
$parameters = OCCustomSearchParameters::instance()

    // la ricerca libera funziona sui campi di tipo text, se sono string occorre cercare per tutta la stringa
    ->setQuery('amico di Topolino')

    // i filtri accettano array o array di array come i filters di eZFind
    // i nomi dei campi sono quelli definiti nel ElencoTelefonicoSearchableObject::getFields
    ->setFilters(array(
        array(
            'and',
            array('nome' => 'Paolino'),
            array('cognome' => 'Paperino')
        )
    ))

    // anche nelle faccette come nei filtri i nomi dei campi sono quelli definiti nel ElencoTelefonicoSearchableObject::getFields
    ->setFacets(array(array('field' => 'cognome')))

    // ordinamento
    // come sopra per i nomi dei campi
    ->setSort(array('cognome' => 'asc', 'nome' => 'asc'))

    // limite
    ->setLimit(10)

    // offset
    ->setOffset(0);

$repository = new ElencoTelefonicoSearchableRepository();
$result = $repository->find($parameters);
```

Per eseguire una ricerca via http occorre usare il modulo customfind, un po' limitato perché al momento non sono gestiti i fitri con 'or'

```
http://www.example.com/debug/customfind/elenco_telefonico?query=amico di Topolino&filters[nome]=Paolino&filters[cognome]=Paperino&facets[]=cognome&sort[cognome]=asc&sort[nome]=asc&limit10&offset=0
```


#### Per re-indicizzare il repository da script

```bash
php extension/occustomfind/bin/php/updatecustomsearchindex.php -sbackend --repository=elenco_telefonico --clean
```

#### Per svuotare il repository da script

```bash
php extension/occustomfind/bin/php/trucate.php -sbackend --repository=elenco_telefonico
```

#### Per indicizzare tutti i repository

```bash
php extension/occustomfind/bin/php/updatecustomsearchindex.php -sbackend --clean
```

#### Per re-indicizzare tutti i repository

```bash
php extension/occustomfind/bin/php/updatecustomsearchindex.php -sbackend --clean
```

#### Per svuotare tutti i repository

```bash
php extension/occustomfind/bin/php/truncate.php -sbackend
```

### Classi Dummy
In occustomfind.ini ci sono due repository di esempio che indicizzano 10 contentuti per tipo.
Per provarli occorre abilitarli
