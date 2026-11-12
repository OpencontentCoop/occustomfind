<?php

/**
 * Sceglie il core Solr su cui occustomfind indicizza e cerca.
 *
 * I documenti di occustomfind non hanno una dimensione lingua: ogni riga e' un solo documento. Senza MultiCore
 * esiste un solo core e il problema non si pone. Con MultiCore (un core per lingua) ogni siteaccess ha il proprio
 * SearchServerURI, quindi senza questa classe una riga finirebbe nel core del siteaccess che la scrive e sarebbe
 * invisibile dagli altri. Con MultiCore attivo si usa quindi sempre il core della lingua principale del sito.
 *
 * Ordine di scelta:
 *  1. MultiCore non attivo: nessuna scelta (si usa il SearchServerURI del siteaccess corrente, come sempre);
 *  2. override esplicito occustomfind.ini [Solr] MainCore = chiave di solr.ini [SolrBase] Shards[];
 *  3. lingua principale = prima SiteLanguageList del siteaccess DefaultAccess, mappata con LanguagesCoresMap e Shards;
 *  4. se la risoluzione non e' possibile: nessuna scelta (comportamento di sempre) e un warning nel log.
 *
 * resolve() e' pura (nessuna dipendenza dal kernel eZ) e si testa da sola; instance() e' il collegamento con eZ.
 */
class OCCustomSearchableSolrResolver
{
    const REASON_SINGLE_CORE = 'single-core';
    const REASON_OVERRIDE = 'override';
    const REASON_MAIN_LANGUAGE = 'main-language';
    const REASON_UNRESOLVED = 'unresolved';

    /** @var array|null */
    private static $cache;

    /**
     * @param bool $multiCore LanguageSearch.MultiCore == enabled
     * @param array $languagesCoresMap ezfind.ini LanguageSearch.LanguagesCoresMap (lingua => chiave di Shards)
     * @param array $shards solr.ini SolrBase.Shards (chiave => URI del core)
     * @param string|null $mainLanguage lingua principale del sito (null se non determinabile)
     * @param string|null $overrideShard chiave di Shards forzata da occustomfind.ini (null/'' se assente)
     *
     * @return array ['uri' => string|null, 'reason' => string, 'message' => string]
     *               uri null = non scegliere, usare il SearchServerURI del siteaccess corrente
     */
    public static function resolve($multiCore, array $languagesCoresMap, array $shards, $mainLanguage, $overrideShard = null)
    {
        if (!$multiCore) {
            return self::result(null, self::REASON_SINGLE_CORE, '');
        }

        $overrideShard = trim((string)$overrideShard);
        if ($overrideShard !== '') {
            $uri = self::shardUri($shards, $overrideShard);
            if ($uri === null) {
                return self::result(
                    null,
                    self::REASON_UNRESOLVED,
                    "occustomfind.ini [Solr] MainCore=$overrideShard non e' una chiave valida di solr.ini [SolrBase] Shards"
                );
            }

            return self::result($uri, self::REASON_OVERRIDE, '');
        }

        $mainLanguage = trim((string)$mainLanguage);
        if ($mainLanguage === '') {
            return self::result(
                null,
                self::REASON_UNRESOLVED,
                "lingua principale non determinabile (SiteLanguageList del siteaccess DefaultAccess vuota)"
            );
        }

        if (!isset($languagesCoresMap[$mainLanguage]) || trim((string)$languagesCoresMap[$mainLanguage]) === '') {
            return self::result(
                null,
                self::REASON_UNRESOLVED,
                "la lingua principale $mainLanguage non e' in ezfind.ini [LanguageSearch] LanguagesCoresMap"
            );
        }

        $shardKey = trim((string)$languagesCoresMap[$mainLanguage]);
        $uri = self::shardUri($shards, $shardKey);
        if ($uri === null) {
            return self::result(
                null,
                self::REASON_UNRESOLVED,
                "il core $shardKey della lingua principale $mainLanguage non e' in solr.ini [SolrBase] Shards"
            );
        }

        return self::result($uri, self::REASON_MAIN_LANGUAGE, '');
    }

    /**
     * Restituisce il client Solr da usare. Con MultiCore e core principale risolto e' quello del core principale,
     * altrimenti e' il client di default (SearchServerURI del siteaccess corrente).
     *
     * @return eZSolrBase
     */
    public static function solr()
    {
        $resolved = self::instance();

        return $resolved['uri'] === null ? new eZSolrBase() : new eZSolrBase($resolved['uri']);
    }

    /**
     * @return array risultato di resolve() per il siteaccess corrente, calcolato una sola volta per processo
     */
    public static function instance()
    {
        if (self::$cache === null) {
            $findIni = eZINI::instance('ezfind.ini');
            $solrIni = eZINI::instance('solr.ini');
            $multiCore = $findIni->hasVariable('LanguageSearch', 'MultiCore')
                && $findIni->variable('LanguageSearch', 'MultiCore') == 'enabled';

            $map = $multiCore && $findIni->hasVariable('LanguageSearch', 'LanguagesCoresMap')
                ? (array)$findIni->variable('LanguageSearch', 'LanguagesCoresMap') : [];
            $shards = $multiCore && $solrIni->hasVariable('SolrBase', 'Shards')
                ? (array)$solrIni->variable('SolrBase', 'Shards') : [];

            $override = null;
            $customIni = eZINI::instance('occustomfind.ini');
            if ($customIni->hasVariable('Solr', 'MainCore')) {
                $override = $customIni->variable('Solr', 'MainCore');
            }

            self::$cache = self::resolve(
                $multiCore,
                $map,
                $shards,
                $multiCore && trim((string)$override) === '' ? self::mainLanguageFromDefaultAccess() : null,
                $override
            );

            if (self::$cache['reason'] === self::REASON_UNRESOLVED) {
                $message = 'OCCustomSearchableSolrResolver: ' . self::$cache['message']
                    . ". Si usa il core del siteaccess corrente: le righe potrebbero non essere visibili dagli altri siteaccess.";
                eZDebug::writeWarning($message, __METHOD__);
                eZLog::write($message, 'occustomfind.log');
            }
        }

        return self::$cache;
    }

    /**
     * Solo per i test e per i processi che cambiano siteaccess: dimentica il risultato calcolato.
     */
    public static function reset()
    {
        self::$cache = null;
    }

    /**
     * @return string|null
     */
    private static function mainLanguageFromDefaultAccess()
    {
        $siteIni = eZINI::instance('site.ini');
        $defaultAccess = $siteIni->hasVariable('SiteSettings', 'DefaultAccess')
            ? trim((string)$siteIni->variable('SiteSettings', 'DefaultAccess'))
            : '';
        if ($defaultAccess === '') {
            return null;
        }

        $current = eZSiteAccess::current();
        if (is_array($current) && isset($current['name']) && $current['name'] === $defaultAccess) {
            $ini = $siteIni;
        } else {
            $ini = eZSiteAccess::getIni($defaultAccess, 'site.ini');
        }

        if (!$ini->hasVariable('RegionalSettings', 'SiteLanguageList')) {
            return null;
        }
        $languages = (array)$ini->variable('RegionalSettings', 'SiteLanguageList');

        return isset($languages[0]) ? $languages[0] : null;
    }

    private static function shardUri(array $shards, $key)
    {
        if (!isset($shards[$key])) {
            return null;
        }
        $uri = trim((string)$shards[$key]);

        return $uri === '' ? null : $uri;
    }

    private static function result($uri, $reason, $message)
    {
        return ['uri' => $uri, 'reason' => $reason, 'message' => $message];
    }
}
