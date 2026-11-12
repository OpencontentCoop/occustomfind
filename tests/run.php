<?php
/**
 * Test del core Solr scelto da occustomfind.
 * Esecuzione: php tests/run.php   (nessuna dipendenza, nessun kernel eZ: usa dei finti eZINI/eZSiteAccess/eZSolrBase)
 */

error_reporting(E_ALL);

// ---------------------------------------------------------------- finti del kernel eZ

class eZDebug
{
    public static $warnings = [];

    public static function writeWarning($message, $label = '')
    {
        self::$warnings[] = $message;
    }
}

class eZLog
{
    public static $lines = [];

    public static function write($message, $file = '')
    {
        self::$lines[] = [$file, $message];
    }
}

class eZINI
{
    /** @var array [file => [block => [var => value]]] */
    public static $data = [];
    public static $loaded = [];
    private $file;
    private $override;

    public function __construct($file, $override = null)
    {
        $this->file = $file;
        $this->override = $override;
        self::$loaded[] = $file . ($override ? "@$override" : '');
    }

    public static function instance($file)
    {
        return new self($file);
    }

    private function source()
    {
        if ($this->override !== null) {
            return isset(self::$data['siteaccess'][$this->override][$this->file])
                ? self::$data['siteaccess'][$this->override][$this->file]
                : (isset(self::$data[$this->file]) ? self::$data[$this->file] : []);
        }

        return isset(self::$data[$this->file]) ? self::$data[$this->file] : [];
    }

    public function hasVariable($block, $var)
    {
        $s = $this->source();

        return isset($s[$block]) && array_key_exists($var, $s[$block]);
    }

    public function variable($block, $var)
    {
        $s = $this->source();

        return $s[$block][$var];
    }
}

class eZSiteAccess
{
    public static $current = ['name' => 'frontend'];

    public static function current()
    {
        return self::$current;
    }

    public static function getIni($name, $file = 'site.ini')
    {
        return new eZINI($file, $name);
    }
}

class eZSolrBase
{
    public $uri;

    public function __construct($uri = false)
    {
        // come l'originale: URI esplicito oppure SearchServerURI del siteaccess corrente
        $this->uri = $uri !== false ? $uri : eZINI::instance('solr.ini')->variable('SolrBase', 'SearchServerURI');
    }
}

require __DIR__ . '/../classes/interface/OCCustomSearchableSolrResolver.php';

// ---------------------------------------------------------------- mini framework

$failures = 0;
$count = 0;

function check($name, $expected, $actual)
{
    global $failures, $count;
    $count++;
    if ($expected === $actual) {
        echo "  ok   $name\n";

        return;
    }
    $failures++;
    echo "  FAIL $name\n       atteso:  " . var_export($expected, true) . "\n       ottenuto: " . var_export($actual, true) . "\n";
}

function reset_env(array $data)
{
    eZINI::$data = $data;
    eZINI::$loaded = [];
    eZDebug::$warnings = [];
    eZLog::$lines = [];
    eZSiteAccess::$current = ['name' => 'frontend'];
    OCCustomSearchableSolrResolver::reset();
}

const MAIN = 'http://solr:8983/solr/main';
const GER = 'http://solr:8983/solr/main_ger';
const ENG = 'http://solr:8983/solr/main_eng';

$map = ['ita-IT' => 'main', 'ger-DE' => 'main_ger', 'eng-GB' => 'main_eng', 'ita-PA' => 'main'];
$shards = ['main' => MAIN, 'main_ger' => GER, 'main_eng' => ENG];

// ---------------------------------------------------------------- resolve(): logica pura

echo "resolve() senza MultiCore\n";
$r = OCCustomSearchableSolrResolver::resolve(false, [], [], null, null);
check('monocore: nessuna scelta', null, $r['uri']);
check('monocore: motivo', 'single-core', $r['reason']);
$r = OCCustomSearchableSolrResolver::resolve(false, $map, $shards, 'ger-DE', 'main_eng');
check('monocore: mappe e override ignorati', null, $r['uri']);

echo "resolve() con MultiCore\n";
$r = OCCustomSearchableSolrResolver::resolve(true, $map, $shards, 'ita-IT', null);
check('lingua principale ita -> core principale', MAIN, $r['uri']);
check('motivo main-language', 'main-language', $r['reason']);
$r = OCCustomSearchableSolrResolver::resolve(true, $map, $shards, 'ger-DE', null);
check('lingua principale tedesca (sito con ger principale)', GER, $r['uri']);
$r = OCCustomSearchableSolrResolver::resolve(true, $map, $shards, 'ita-PA', null);
check('lingua non principale mappata sul core principale', MAIN, $r['uri']);
$r = OCCustomSearchableSolrResolver::resolve(true, $map, $shards, 'ita-IT', 'main_eng');
check('override vince sulla lingua', ENG, $r['uri']);
check('override: motivo', 'override', $r['reason']);
$r = OCCustomSearchableSolrResolver::resolve(true, $map, $shards, null, 'main_ger');
check('override funziona anche senza lingua principale', GER, $r['uri']);
$r = OCCustomSearchableSolrResolver::resolve(true, $map, $shards, 'ita-IT', '  ');
check('override di soli spazi = assente', MAIN, $r['uri']);
$r = OCCustomSearchableSolrResolver::resolve(true, $map, $shards, 'ita-IT', ' main_ger ');
check('override con spazi attorno', GER, $r['uri']);

echo "resolve() casi non risolvibili (fallback + messaggio)\n";
foreach ([
    'override non in Shards' => [true, $map, $shards, 'ita-IT', 'inesistente'],
    'lingua principale assente' => [true, $map, $shards, null, null],
    'lingua principale stringa vuota' => [true, $map, $shards, '', null],
    'lingua non in LanguagesCoresMap' => [true, $map, $shards, 'fre-FR', null],
    'LanguagesCoresMap vuota' => [true, [], $shards, 'ita-IT', null],
    'valore mappa vuoto' => [true, ['ita-IT' => ''], $shards, 'ita-IT', null],
    'core non in Shards' => [true, ['ita-IT' => 'x'], $shards, 'ita-IT', null],
    'Shards vuoto' => [true, $map, [], 'ita-IT', null],
    'URI shard vuoto' => [true, $map, ['main' => ''], 'ita-IT', null],
] as $label => $args) {
    $r = call_user_func_array(['OCCustomSearchableSolrResolver', 'resolve'], $args);
    check("$label: uri null", null, $r['uri']);
    check("$label: motivo unresolved", 'unresolved', $r['reason']);
    check("$label: messaggio presente", true, $r['message'] !== '');
}

// ---------------------------------------------------------------- instance()/solr(): collegamento con eZ

/**
 * Costruisce la configurazione vista da un siteaccess.
 * $o: multicore(bool|null=assente), current (nome siteaccess corrente), currentUri (suo SearchServerURI),
 *     currentLangs (sua SiteLanguageList), defaultAccess, defaultLangs (SiteLanguageList del DefaultAccess),
 *     mainCore (override), map, shards
 */
function env(array $o)
{
    global $map, $shards;
    $o += [
        'multicore' => true, 'current' => 'frontend', 'currentUri' => MAIN, 'currentLangs' => ['ita-IT'],
        'defaultAccess' => 'frontend', 'defaultLangs' => ['ita-IT'], 'mainCore' => null,
        'map' => $map, 'shards' => $shards,
    ];
    $data = [
        'ezfind.ini' => ['LanguageSearch' => []],
        'solr.ini' => ['SolrBase' => ['SearchServerURI' => $o['currentUri'], 'Shards' => $o['shards']]],
        'site.ini' => [
            'SiteSettings' => ['DefaultAccess' => $o['defaultAccess']],
            'RegionalSettings' => ['SiteLanguageList' => $o['currentLangs']],
        ],
        'siteaccess' => [$o['defaultAccess'] => ['site.ini' => ['RegionalSettings' => ['SiteLanguageList' => $o['defaultLangs']]]]],
    ];
    if ($o['multicore'] !== null) {
        $data['ezfind.ini']['LanguageSearch']['MultiCore'] = $o['multicore'] ? 'enabled' : 'disabled';
        $data['ezfind.ini']['LanguageSearch']['LanguagesCoresMap'] = $o['map'];
    }
    if ($o['mainCore'] !== null) {
        $data['occustomfind.ini'] = ['Solr' => ['MainCore' => $o['mainCore']]];
    }
    reset_env($data);
    eZSiteAccess::$current = ['name' => $o['current']];
}

function readSiteaccessIni()
{
    foreach (eZINI::$loaded as $l) {
        if (strpos($l, '@') !== false) {
            return true;
        }
    }

    return false;
}

echo "solr() senza MultiCore (comportamento di sempre)\n";
env(['multicore' => false]);
check('monocore: usa il SearchServerURI corrente', MAIN, OCCustomSearchableSolrResolver::solr()->uri);
check('monocore: non legge ini di altri siteaccess', false, readSiteaccessIni());
check('monocore: nessun warning', [], eZDebug::$warnings);
env(['multicore' => null, 'currentUri' => 'http://solr:8983/solr/unico']);
check('ezfind.ini senza MultiCore: monocore', 'http://solr:8983/solr/unico', OCCustomSearchableSolrResolver::solr()->uri);
env(['multicore' => false, 'current' => 'ger_frontend', 'currentUri' => GER, 'currentLangs' => ['ger-DE', 'ita-IT']]);
check('MultiCore disabled su siteaccess tedesco: resta il suo URI', GER, OCCustomSearchableSolrResolver::solr()->uri);
env(['multicore' => false, 'mainCore' => 'main_eng']);
check('MultiCore disabled: MainCore ignorato', MAIN, OCCustomSearchableSolrResolver::solr()->uri);

echo "solr() con MultiCore: stesso core da ogni siteaccess\n";
foreach ([
    'frontend principale (DefaultAccess)' => ['current' => 'frontend', 'currentUri' => MAIN, 'currentLangs' => ['ita-IT']],
    'frontend tedesco' => ['current' => 'ger_frontend', 'currentUri' => GER, 'currentLangs' => ['ger-DE', 'ita-IT']],
    'frontend inglese' => ['current' => 'eng_frontend', 'currentUri' => ENG, 'currentLangs' => ['eng-GB', 'ita-IT']],
    'backend (lista lingue con principale per prima)' => ['current' => 'backend', 'currentUri' => MAIN, 'currentLangs' => ['ita-IT', 'eng-GB', 'ger-DE']],
    'backend con lista lingue in ordine diverso' => ['current' => 'backend', 'currentUri' => MAIN, 'currentLangs' => ['ger-DE', 'ita-IT']],
] as $label => $o) {
    env($o);
    check("$label: core principale", MAIN, OCCustomSearchableSolrResolver::solr()->uri);
    check("$label: nessun warning", [], eZDebug::$warnings);
}
env(['current' => 'frontend']);
OCCustomSearchableSolrResolver::solr();
check('siteaccess = DefaultAccess: non rilegge un altro siteaccess', false, readSiteaccessIni());
env(['current' => 'ger_frontend', 'currentUri' => GER]);
OCCustomSearchableSolrResolver::solr();
check('altro siteaccess: legge il DefaultAccess', true, readSiteaccessIni());

echo "solr() con MultiCore: sito con lingua principale tedesca\n";
env(['current' => 'ita_frontend', 'currentUri' => MAIN, 'defaultAccess' => 'ger_frontend', 'defaultLangs' => ['ger-DE', 'ita-IT']]);
check('DefaultAccess tedesco: core tedesco da ogni siteaccess', GER, OCCustomSearchableSolrResolver::solr()->uri);

echo "solr() con MultiCore: override MainCore\n";
env(['current' => 'ger_frontend', 'currentUri' => GER, 'mainCore' => 'main_eng']);
check('override forza il core', ENG, OCCustomSearchableSolrResolver::solr()->uri);
check('override: non serve leggere il DefaultAccess', false, readSiteaccessIni());
env(['current' => 'ger_frontend', 'currentUri' => GER, 'mainCore' => '']);
check('override vuoto = automatico', MAIN, OCCustomSearchableSolrResolver::solr()->uri);

echo "solr() con MultiCore: configurazioni incomplete = fallback + warning\n";
foreach ([
    'DefaultAccess vuoto' => ['defaultAccess' => ''],
    'lingua principale non mappata' => ['defaultLangs' => ['fre-FR']],
    'SiteLanguageList del DefaultAccess vuota' => ['defaultLangs' => []],
    'LanguagesCoresMap vuota' => ['map' => []],
    'Shards vuoto' => ['shards' => []],
    'override non valido' => ['mainCore' => 'inesistente'],
] as $label => $o) {
    env($o + ['current' => 'ger_frontend', 'currentUri' => GER, 'currentLangs' => ['ger-DE', 'ita-IT']]);
    check("$label: ripiega sul core corrente", GER, OCCustomSearchableSolrResolver::solr()->uri);
    OCCustomSearchableSolrResolver::solr();
    check("$label: un solo warning per processo", 1, count(eZDebug::$warnings));
    check("$label: scritto in occustomfind.log", 'occustomfind.log', isset(eZLog::$lines[0]) ? eZLog::$lines[0][0] : null);
}

echo "cache per processo\n";
env([]);
OCCustomSearchableSolrResolver::solr();
$loaded = count(eZINI::$loaded);
OCCustomSearchableSolrResolver::solr();
check('seconda chiamata non rilegge gli ini', $loaded, count(eZINI::$loaded));
OCCustomSearchableSolrResolver::reset();
OCCustomSearchableSolrResolver::solr();
check('dopo reset ricalcola', true, count(eZINI::$loaded) > $loaded);

echo "\n$count controlli, $failures falliti\n";
exit($failures === 0 ? 0 : 1);
