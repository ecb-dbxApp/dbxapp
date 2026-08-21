<?php
require_once __DIR__ . '/dbxInertCode.class.php';
/**
 * @brief Deterministische Rendering-Engine für Variablen, Inclusions und Template-Bausteine.
 *
 * =========================================================
 * DBX TEMPLATE SYSTEM (dbxTPL)
 * =========================================================
 *
 * Überblick
 * ---------
 * Die Klasse dbxTPL ist die zentrale Rendering-Engine des DBX-Systems.
 * Sie ist bewusst einfach, deterministisch und performance-optimiert.
 *
 * Templates sind reine Textdateien ohne Template-Sprache.
 * Unterstützt werden nur:
 *
 *   {var}                  → Variablenersetzung
 *   [inc=...]...[/inc]     → bedingte Inhalte
 *   [tpl=modul|file]       → Template-Include (rekursiv)
 *
 *
 * =========================================================
 * HAUPTFUNKTION
 * =========================================================
 *
 * get_tpl($file, $data = '', $type = 'htm', $i = 0, $depth = 0)
 *
 * Zentrale Rendering-Funktion für Modul-Templates.
 *
 *
 * ---------------------------------------------------------
 * PIPELINE (Reihenfolge ist fix!)
 * ---------------------------------------------------------
 *
 * 1. Modul auflösen ('modul' → aktives Modul)
 * 2. Rohes Template laden (inkl. Cache + Sprach-Fallback)
 * 3. Aktuelle DBX-Systemvariablen ersetzen
 * 4. {var} ersetzen (replaces)
 * 5. [inc] verarbeiten
 * 6. [tpl] rekursiv verarbeiten
 * 7. Editor-Marker setzen (optional)
 * 8. Ergebnis zurückgeben
 *
 *
 * =========================================================
 * TEMPLATE STRUKTUR
 * =========================================================
 *
 * Modul Templates:
 *
 *   /dbx/modules/{modul}/tpl/{type}/{file}.htm
 *   /dbx/modules/{modul}/tpl/{type}/{file}_{lng}.htm
 *
 * Sprach-Fallback:
 *
 *   file_de.htm → bevorzugt
 *   file.htm    → fallback
 *
 *
 * =========================================================
 * CACHE SYSTEM
 * =========================================================
 *
 * Speicherort:
 *
 *   private $template_cache im aktuellen dbxTPL-Objekt/Request
 *
 * Struktur:
 *
 *   [modul|file|type|lng] = [
 *       'tpl'  => 'Roher Template-Inhalt',
 *       'path' => 'Dateipfad'
 *   ]
 *
 * Cache enthält:
 *   ✔ Template-Inhalt
 *   ✔ Dateipfad
 *
 * Cache enthält NICHT:
 *   ✘ replaces_dbx() Ergebnis
 *   ✘ $data replaces
 *   ✘ [inc]
 *   ✘ [tpl]
 *   ✘ Editor-Marker
 *
 * Ziel:
 *   Maximale Performance bei vielen Template-Aufrufen (z.B. Tabellen)
 *
 *
 * =========================================================
 * REPLACE SYSTEM
 * =========================================================
 *
 * 1. Data Replace (replaces)
 *
 *   {name} → $data['name']
 *
 * 2. DBX Replace (replaces_dbx) – wird bei jeder Ausgabe ausgeführt
 *
 *   {dbx:lng}
 *   {dbx:design}
 *   {dbx:page}
 *   {dbx:title}
 *   {dbx:perma}
 *   {dbx:meta_description}
 *   {dbx:meta_keywords}
 *   {dbx:canonical}
 *   {dbx:robots}
 *   {dbx:og_title}
 *   {dbx:og_description}
 *   {dbx:og_image}
 *   {dbx:og_url}
 *   {dbx:head_meta}
 *
 *
 * =========================================================
 * [inc] SYSTEM (Bedingungen)
 * =========================================================
 *
 * Syntax:
 *
 *   [inc=1]Text[/inc]
 *   [inc=0]Text[/inc]
 *
 * Verhalten:
 *
 *   1 → Inhalt bleibt
 *   0 → Inhalt wird entfernt
 *
 * Funktionen (Whitelist):
 *
 *   [inc=has_group('admin')]
 *
 * Wichtig:
 *   - {var} wird vorher ersetzt
 *   - Nur erlaubte Funktionen werden ausgeführt
 *
 *
 * =========================================================
 * [tpl] SYSTEM (Template Includes)
 * =========================================================
 *
 * Syntax:
 *
 *   [tpl=modul|file]
 *   [tpl=modul|file|type]
 *
 * Default:
 *
 *   type = 'htm'
 *
 * Verhalten:
 *
 *   → ruft intern get_tpl() auf
 *   → Ergebnis ersetzt den Tag
 *
 * Eigenschaften:
 *
 *   ✔ rekursiv
 *   ✔ $data wird weitergereicht
 *   ✔ verschachtelbar
 *
 * Loop-Schutz:
 *
 *   maxDepth = 10
 *
 *
 * =========================================================
 * EDITOR SYSTEM (Marker)
 * =========================================================
 *
 * Marker werden in Templates eingefügt:
 *
 *   <!-- DBX-TPL-START|modul|file|type|path -->
 *   ... content ...
 *   <!-- DBX-TPL-END -->
 *
 * Zweck:
 *
 *   - visuelles Inline-Editing (editor.js)
 *   - genaue Position im DOM
 *   - verschachtelte Templates erkennen
 *
 * Eigenschaften:
 *
 *   ✔ zerstört kein HTML
 *   ✔ funktioniert mit HTML-Listen und HTML-Tabellen
 *   ✔ unterstützt verschachtelte Templates
 *
 *
 * Steuerung über dbx_edit:
 *
 *   1 → nur Module ≠ dbx
 *   2 → nur Modul dbx
 *   3 → Level 1 + Level 2
 *   4 → FD Definitionen
 *   5 → DD Definitionen
 *   6 → Modul-/Include-Class
 *   7 → myX System-Class
 *   8 → config.php
 *   9 → alles
 *
 *
 * =========================================================
 * DESIGN TEMPLATES
 * =========================================================
 *
 * Funktion:
 *
 *   get_design_tpl($design, $page, $language, $type)
 *
 * Eigenschaften:
 *
 *   ✘ kein Cache (nur einmal geladen)
 *   ✔ nutzt replaces_dbx()
 *   ✔ unterstützt Editor-Marker
 *   ✔ setzt Systemzustand (SysVar)
 *
 * Pfad:
 *
 *   /dbx/design/{design}/{type}/{file}.htm
 *
 * Fallback:
 *
 *   default.htm
 *
 *
 * =========================================================
 * SICHERHEIT
 * =========================================================
 *
 * - Keine Template-Sprache
 * - Kein eval()
 * - Nur Whitelist-Funktionen bei [inc]
 *
 *
 * =========================================================
 * PERFORMANCE
 * =========================================================
 *
 * Optimiert für:
 *
 *   - große Tabellen (1000+ Zeilen)
 *   - häufige Template-Aufrufe
 *
 * Strategie:
 *
 *   - File IO nur einmal
 *   - rohe Templates im requestlokalen Laufzeitcache
 *   - aktuelle Systemvariablen werden erst bei der Ausgabe eingesetzt
 *
 *
 * =========================================================
 * DESIGN-PHILOSOPHIE
 * =========================================================
 *
 * Bewusst NICHT enthalten:
 *
 *   ✘ keine Loops
 *   ✘ keine komplexe Logik
 *   ✘ keine Template-Sprache
 *
 * Stattdessen:
 *
 *   ✔ einfache, kontrollierte Bausteine
 *   ✔ volle Kontrolle im PHP-Code
 *
 *
 * =========================================================
 * ERGEBNIS
 * =========================================================
 *
 * ✔ schnell
 * ✔ stabil
 * ✔ deterministisch
 * ✔ erweiterbar
 *
 * =========================================================
 */

class dbxTPL extends \dbxObj {

    /**
     * Maximale Rekursionstiefe für [tpl]
     */
    private $max_depth = 10;
    public $_modul = 'dbx';

    /** Rohcache der in diesem Request bereits gelesenen Templates. */
    private array $template_cache = array();
    private ?dbxInertCode $inert_code = null;

    private function inert_code(): dbxInertCode {
        return $this->inert_code ??= new dbxInertCode();
    }

    /**
     * Leert den Rohcache gezielt nach einem In-Request-Editor-Schreibvorgang.
     * Normale HTTP-Requests benötigen keinen Dateifingerprint: eine neue
     * dbxTPL-Instanz beginnt immer mit einem leeren Cache.
     */
    public function clear_raw_cache(): void {
        $this->template_cache = array();
    }

    /** Normalisiert URL-Parameterdaten fuer die Template-Ersetzung. */
    private function normalize_replacements($data): array {
        if (is_array($data)) return $data;
        if (!is_string($data) || $data === '' || $data[0] === '=' || strpos($data, '=') === false) {
            return array();
        }
        parse_str($data, $parsed);
        return is_array($parsed) ? $parsed : array();
    }


    /* =========================================================
       BASIC REPLACE
    ========================================================= */

    /**
     * Ersetzt {var} durch Werte aus $data
     */
    function replaces(string $tpl, $replaces): string {

        if (!is_array($replaces)) {
            $replaces = $this->normalize_replacements($replaces);
        }

        if (!is_array($replaces) || !$replaces) {
            return $tpl;
        }

        $map = array();
        foreach ($replaces as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = '';
            }

            $map['{' . $key . '}'] = (string) ($value ?? '');
        }

        return $map ? strtr($tpl, $map) : $tpl;
    }


    /**
     * Ersetzt DBX-Systemvariablen (CACHEBAR!).
     *
     * Unterstuetzte globale Laufzeitwerte umfassen unter anderem
     * `{dbx:version}` und `{dbx:asset_version}` fuer die Version der aktuellen Installation.
     */
    public function replaces_dbx(string $tpl): string {

        $api = dbx();
        $core_config = is_object($api) && method_exists($api, 'get_cfg')
            ? $api->get_cfg('dbx')
            : array();
        $core_config = is_array($core_config) ? $core_config : array();
        $brand = trim((string)($core_config['brand_name'] ?? ''));
        if ($brand === '') {
            $brand = trim((string)($core_config['site_title'] ?? ($core_config['page'] ?? 'dbxapp')));
        }
        $tagline = trim((string)($core_config['brand_tagline'] ?? ''));
        $page_title = trim((string)dbx()->get_system_var('dbx_title', ''));
        $seo_title = trim((string)dbx()->get_system_var('dbx_seo_title', ''));
        $document_title = $seo_title !== '' ? $seo_title : $page_title;
        if ($document_title === '') {
            $document_title = $brand;
        } elseif ($brand !== '' && stripos($document_title, $brand) === false) {
            $document_title .= ' · ' . $brand;
        }

        $tpl = str_replace('{dbx:base_href}', dbx()->get_base_url(), $tpl);
        $tpl = str_replace('{dbx:design}'   , dbx()->get_system_var('dbx_activ_design', dbx()->get_system_var('dbx_design')), $tpl);
        $presentation = dbx()->get_system_obj('dbxPresentation');
        $tpl = str_replace('{dbx:color}'    , $presentation->get_skin(), $tpl);
        $tpl = str_replace('{dbx:skin_css}' , $presentation->get_skin_css(), $tpl);
        $tpl = str_replace('{dbx:skin_class}', $presentation->get_skin_class(), $tpl);
        $tpl = str_replace('{dbx:page}'     , dbx()->get_system_var('dbx_activ_page', dbx()->get_system_var('dbx_page')), $tpl);
        $tpl = str_replace('{dbx:lng}'      , dbx()->get_system_var('dbx_activ_lng', dbx()->get_system_var('dbx_lng')), $tpl);
        $tpl = str_replace('{dbx:edit}'     , (string)max(0, min(9, (int)dbx()->get_system_var('dbx_edit', 0, 'int'))), $tpl);
        $tpl = str_replace('{dbx:title}'    , htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'), $tpl);
        $tpl = str_replace('{dbx:document_title}', htmlspecialchars($document_title, ENT_QUOTES, 'UTF-8'), $tpl);
        $tpl = str_replace('{dbx:brand}'    , htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'), $tpl);
        $tpl = str_replace('{dbx:tagline}'  , htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8'), $tpl);
        $tpl = str_replace('{dbx:perma}'    , dbx()->get_system_var('dbx_perma'), $tpl);
        $raw_version = is_object($api) && method_exists($api, 'get_version') ? $api->get_version() : '';
        $version = htmlspecialchars($raw_version, ENT_QUOTES, 'UTF-8');
        $asset_version = htmlspecialchars($this->asset_version($raw_version), ENT_QUOTES, 'UTF-8');
        $tpl = str_replace('{dbx:version}', $version, $tpl);
        $tpl = str_replace('{dbx:asset_version}', $asset_version, $tpl);

        $meta_description = (string)dbx()->get_system_var('dbx_meta_description', '');
        $meta_keywords    = (string)dbx()->get_system_var('dbx_meta_keywords', '');
        $canonical        = (string)dbx()->get_system_var('dbx_canonical', '');
        $robots           = $this->effective_robots_meta();
        $og_title         = (string)dbx()->get_system_var('dbx_og_title', '');
        $og_description   = (string)dbx()->get_system_var('dbx_og_description', '');
        $og_url           = (string)dbx()->get_system_var('dbx_og_url', '');
        $og_image         = (string)dbx()->get_system_var('dbx_og_image', '');

        $tpl = str_replace('{dbx:meta_description}', $this->seo_attr_esc($meta_description), $tpl);
        $tpl = str_replace('{dbx:meta_keywords}'   , $this->seo_attr_esc($meta_keywords), $tpl);
        $tpl = str_replace('{dbx:canonical}'       , $this->seo_attr_esc($canonical), $tpl);
        $tpl = str_replace('{dbx:robots}'          , $this->seo_attr_esc($robots), $tpl);
        $tpl = str_replace('{dbx:og_title}'       , $this->seo_attr_esc($og_title), $tpl);
        $tpl = str_replace('{dbx:og_description}' , $this->seo_attr_esc($og_description), $tpl);
        $tpl = str_replace('{dbx:og_url}'          , $this->seo_attr_esc($og_url), $tpl);
        $tpl = str_replace('{dbx:og_image}'        , $this->seo_attr_esc($og_image), $tpl);
        $tpl = str_replace('{dbx:head_meta}'       , $this->build_head_meta_block(), $tpl);
        $tpl = str_replace('{dbx:module_assets}'   , $this->build_module_assets_block(), $tpl);

        return $tpl;
    }

    /**
     * Liefert eine stabile Asset-Version pro Quellstand.
     *
     * Die Produktversion allein darf hier nicht verwendet werden: CSS- und
     * JavaScript-Aenderungen waehrend der Entwicklung wuerden sonst unter der
     * unveraenderten URL aus dem Browser-Cache geladen. 171 Asset-Dateien sind
     * klein genug fuer einen periodischen Scan. Das Ergebnis wird zwei Sekunden
     * pro Installation zwischengespeichert; normale Requests lesen dadurch nur
     * eine kleine JSON-Datei statt den gesamten Design-/JS-/Modulbaum erneut.
     */
    private function asset_version(string $version): string {
        static $versions = array();

        if (isset($versions[$version])) {
            return $versions[$version];
        }

        $base = trim($version) !== '' ? trim($version) : '0';
        $cache_dir = dirname(__DIR__, 2) . '/files/sys/cache';
        $cache_file = $cache_dir . '/asset-version.json';
        $cached = is_file($cache_file) ? json_decode((string)@file_get_contents($cache_file), true) : null;
        if (is_array($cached)
            && (string)($cached['base'] ?? '') === $base
            && (int)($cached['scanned_at'] ?? 0) >= time() - 2
            && (int)($cached['latest_mtime'] ?? 0) > 0
        ) {
            return $versions[$version] = $base . '.' . (int)$cached['latest_mtime'];
        }

        $dbx_dir = dirname(__DIR__);
        $latest_mtime = 0;
        foreach (array($dbx_dir . '/design', $dbx_dir . '/js', $dbx_dir . '/modules') as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $extension = strtolower((string)$file->getExtension());
                if ($extension !== 'css' && $extension !== 'js') {
                    continue;
                }
                $latest_mtime = max($latest_mtime, (int)$file->getMTime());
            }
        }

        if (!is_dir($cache_dir)) @mkdir($cache_dir, 0775, true);
        if (is_dir($cache_dir) && is_writable($cache_dir)) {
            $payload = json_encode(array(
                'base' => $base,
                'latest_mtime' => $latest_mtime,
                'scanned_at' => time(),
            ), JSON_UNESCAPED_SLASHES);
            if (is_string($payload)) {
                $temporary = $cache_file . '.' . getmypid() . '.tmp';
                if (@file_put_contents($temporary, $payload, LOCK_EX) !== false) {
                    if (is_file($cache_file)) @unlink($cache_file);
                    if (!@rename($temporary, $cache_file)) @unlink($temporary);
                }
            }
        }

        $versions[$version] = $latest_mtime > 0 ? $base . '.' . $latest_mtime : $base;
        return $versions[$version];
    }

    private function seo_attr_esc($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    private function build_head_meta_block(): string {
        $lines = array();

        $description = trim((string)dbx()->get_system_var('dbx_meta_description', ''));
        if ($description !== '') {
            $lines[] = '<meta name="description" content="' . $this->seo_attr_esc($description) . '">';
        }

        $keywords = trim((string)dbx()->get_system_var('dbx_meta_keywords', ''));
        if ($keywords !== '') {
            $lines[] = '<meta name="keywords" content="' . $this->seo_attr_esc($keywords) . '">';
        }

        $canonical = trim((string)dbx()->get_system_var('dbx_canonical', ''));
        if ($canonical !== '') {
            $lines[] = '<link rel="canonical" href="' . $this->seo_attr_esc($canonical) . '">';
        }

        $robots = $this->effective_robots_meta();
        if ($robots !== '') {
            $lines[] = '<meta name="robots" content="' . $this->seo_attr_esc($robots) . '">';
        }

        $og_title = trim((string)dbx()->get_system_var('dbx_og_title', ''));
        if ($og_title !== '') {
            $lines[] = '<meta property="og:title" content="' . $this->seo_attr_esc($og_title) . '">';
        }

        $og_description = trim((string)dbx()->get_system_var('dbx_og_description', ''));
        if ($og_description !== '') {
            $lines[] = '<meta property="og:description" content="' . $this->seo_attr_esc($og_description) . '">';
        }

        $og_url = trim((string)dbx()->get_system_var('dbx_og_url', ''));
        if ($og_url !== '') {
            $lines[] = '<meta property="og:url" content="' . $this->seo_attr_esc($og_url) . '">';
        }

        $og_image = trim((string)dbx()->get_system_var('dbx_og_image', ''));
        if ($og_image !== '') {
            $lines[] = '<meta property="og:image" content="' . $this->seo_attr_esc($og_image) . '">';
        }

        if ($og_title !== '' || $og_description !== '' || $og_url !== '' || $og_image !== '') {
            $lines[] = '<meta property="og:type" content="website">';
        }

        $hreflang = (string)dbx()->get_system_var('dbx_hreflang', '');
        if (trim($hreflang) !== '') {
            $lines[] = ltrim($hreflang);
        }

        $json_ld = trim((string)dbx()->get_system_var('dbx_json_ld', ''));
        if ($json_ld !== '') {
            $lines[] = $json_ld;
        }

        if (!$lines) {
            return '';
        }

        return "\n    " . implode("\n    ", $lines);
    }

    /**
     * Baut das Nachlade-Skript fuer im dbxAssetRegistry registrierte
     * Modul-Assets. Die Dateien werden nicht direkt verlinkt, sondern ueber
     * core.js (dbx.add_css/dbx.add_js) nachgeladen, damit dbxapp einen
     * einzigen Lade-/Cache-Weg fuer alle Design- und Modul-Assets behaelt.
     */
    private function build_module_assets_block(): string {
        $ui_defaults_script = '';
        try {
            $ui_defaults = dbx()->get_system_obj('dbxUiSettingsService')->load_defaults();
            $ui_defaults['desktop'] = (object)($ui_defaults['desktop'] ?? array());
            $ui_defaults['mobile'] = (object)($ui_defaults['mobile'] ?? array());
            $ui_defaults_json = json_encode(
                $ui_defaults,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
            );
            if (is_string($ui_defaults_json)) {
                $ui_defaults_script = "\n<script>if(window.dbx){dbx.uiDefaultPayload="
                    . $ui_defaults_json . ";}</script>";
            }
        } catch (Throwable $exception) {
            $ui_defaults_script = '';
        }

        $assets = dbx()->get_system_obj('dbxAssetRegistry');
        $css = $assets->get_assets('css');
        $js  = $assets->get_assets('js');
        if (!$css && !$js) {
            return $ui_defaults_script;
        }

        $calls = array();
        foreach ($css as $path) {
            $calls[] = 'dbx.add_css("root", ' . json_encode((string)$path, JSON_UNESCAPED_SLASHES) . ');';
        }
        foreach ($js as $path) {
            $calls[] = 'dbx.add_js("root", ' . json_encode((string)$path, JSON_UNESCAPED_SLASHES) . ');';
        }

        return $ui_defaults_script . "\n<script>(function () { if (window.dbx && dbx.add_css && dbx.add_js) {\n"
            . implode("\n", $calls) . "\n} })();</script>";
    }

    private function effective_robots_meta(): string {
        // Technische Routen sind niemals eigenstaendige Suchergebnisse.
        // Diese Regel hat Vorrang vor dem SEO-Wert eines eventuell zugleich
        // geladenen Content-Datensatzes.
        $technical_keys = array(
            'dbx_modul',
            'dbx_run1',
            'dbx_run2',
            'dbx_run3',
            'dbx_action',
            'dbx_do',
            'action',
            'dbx_edit',
            'dbx_token',
        );
        foreach ($technical_keys as $key) {
            if (trim((string)dbx()->get_request_var($key, '', '*')) !== '') {
                return 'noindex,follow';
            }
        }

        $ajax = (int)dbx()->get_system_var('dbx_ajax', 0, 'int');
        $window = (int)dbx()->get_system_var('dbx_window', 0, 'int');
        if ($ajax > 0 || $window > 0) {
            return 'noindex,follow';
        }

        $robots = trim((string)dbx()->get_system_var('dbx_robots', ''));
        if ($robots !== '') {
            return $robots;
        }

        return '';
    }

    /**
     * Entfernt optionale Template-Slots, die bis zur finalen Ausgabe
     * absichtlich unverarbeitet bleiben duerfen.
     *
     * `[dbx:js]` wird von Form/Report zuerst mit geschuetztem JavaScript
     * belegt. Bleibt der Marker danach noch stehen, existiert fuer diese
     * Ausgabe kein JavaScript. `{bar_middle}` ist ein optionaler Bar-Slot.
     */
    public function cleanup_optional_placeholders(string $content): string {
        return str_replace(
            array('{bar_middle}', '[dbx:js]'),
            '',
            $content
        );
    }


    /* =========================================================
       TEMPLATE LOADER (MIT CACHE + LANGUAGE)
    ========================================================= */

    /**
     * Lädt und cached ausschließlich den rohen Template-Inhalt.
     *
     * DBX-Systemvariablen werden bewusst erst in get_tpl() eingesetzt. Damit
     * bleiben Design, Sprache, Seite und andere Laufzeitwerte auch dann aktuell,
     * wenn der Template-Cache über mehrere Requests einer Session erhalten bleibt.
     */
    function read_tpl($modul, $file, $type = 'htm') {
        //dbx_debug("## read_tpl  Modul=($modul) file=($file) typ=($type)");
        $file = strtolower($file);
        $lng  = dbx()->get_system_var('dbx_lng', '');

        $base = dbx()->get_base_dir() . "dbx/modules/$modul/tpl/$type/";

        $path = dbx()->lng_resolve_file($base, $file, $type, '', true);

        if ($path === '') {
            $file_lng = $base . $file . '_' . $lng . '.' . $type;
            $file_def = $base . $file . '.' . $type;

            if ($lng && file_exists($file_lng)) {
                $path = $file_lng;
            } elseif (file_exists($file_def)) {
                $path = $file_def;
            }
        }

        if (!$path) {
            return "<div class='alert alert-danger'>TPL ($modul/$file.$type) not found</div>";
        }

        $rel_path = dbx()->editor_file_path($path);
        $cache_key = $modul . '|' . $file . '|' . $type . '|' . $lng;
        $cached = $this->template_cache[$cache_key] ?? null;
        if (is_array($cached)
            && (string)($cached['path'] ?? '') === $rel_path) {
            return (string)($cached['tpl'] ?? '');
        }

        $tpl = file_get_contents($path);
        if (!is_string($tpl)) {
            return "<div class='alert alert-danger'>TPL ($modul/$file.$type) not readable</div>";
        }

        // Cache speichern
        $this->template_cache[$cache_key] = [
            'tpl'   => $tpl,
            'path'  => $rel_path,
        ];

        return $tpl;
    }

    /**
     * Rendert eine modulgebundene Hilfe aus tpl/help.
     *
     * Hilfetexte sind damit versionierte Bestandteile ihres Moduls und weder
     * CMS-Inhalt noch Eigentum eines zentralen Admin-Moduls. Sprachvarianten
     * folgen demselben Vertrag wie normale Templates (name_en.htm usw.).
     */
    public function get_help_tpl(string $modul, string $file, $data = '', int $depth = 0): string {
        if ($depth > $this->max_depth
            || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $modul)
            || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $file)) {
            return '';
        }

        $base = dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/tpl/help/';
        $path = dbx()->lng_resolve_file($base, $file, 'htm', '', true);
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $tpl = file_get_contents($path);
        if (!is_string($tpl)) {
            return '';
        }

        dbx()->register_editor_file('tpl', $path);
        $inert_code = $this->inert_code();
        $inert_blocks = array();
        $tpl = $inert_code->protect($tpl, $inert_blocks, false);
        $tpl = $this->replaces_dbx($tpl);
        $tpl = $this->replaces($tpl, $data);
        $tpl = $inert_code->protect($tpl, $inert_blocks);
        if (strpos($tpl, '[inc=') !== false) {
            $tpl = $this->process_inc($tpl);
        }
        if (strpos($tpl, '[tpl=') !== false) {
            $tpl = $this->process_tpl($tpl, $data, $depth);
        }
        $tpl = $this->cleanup_optional_placeholders($tpl);
        return $inert_code->restore($tpl, $inert_blocks);
    }


    /* =========================================================
       EDITOR (MARKER SYSTEM)
    ========================================================= */

    /**
     * Prüft ob Template Editor Marker bekommt
     */
    private function has_editor($modul, $is_design, $edit, $path = ''): bool {
        if ((int)dbx()->get_system_var('dbx_editor', 0, 'int') > 0) {
            return false;
        }

        if ((string)dbx()->get_system_var('dbx_page', '') === '_tpledit') {
            return false;
        }

        $is_dbx_module = $this->is_dbx_template_path($path);

        if ($path === '') {
            $is_dbx_module = strtolower(trim((string) $modul)) === 'dbx';
        }

        switch ($edit) {
            case 1: return !$is_design && !$is_dbx_module;
            case 2: return !$is_design && $is_dbx_module;
            case 3: return !$is_design;
            case 9: return true;
        }

        return false;
    }

    private function is_dbx_template_path($path): bool {
        $path = strtolower(str_replace('\\', '/', trim((string) $path)));
        return strpos($path, 'dbx/modules/dbx/tpl/') === 0;
    }

    private function get_cached_tpl_path($modul, $file, $type): string {
        $lng = dbx()->get_system_var('dbx_lng', '');
        $cache_key = $modul . '|' . strtolower((string)$file) . '|' . $type . '|' . $lng;
        $path = $this->template_cache[$cache_key]['path'] ?? '';

        // Auch absolute Windows-Pfade aus dem requestlokalen Rohcache normalisieren.
        return $path !== '' ? dbx()->editor_file_path($path) : '';
    }


    /**
     * Fügt Marker für editor.js ein
     */
    private function add_marker($tpl, $modul, $file, $type) {
        $path = $this->get_cached_tpl_path($modul, $file, $type);

        if ($path) {
            dbx()->register_editor_file('tpl', $path);
        }

        if (!$path) {
            return $tpl;
        }

        // Tabellen-Zellen: Marker IN <td>/<th>, nicht als Geschwister davor/dahinter.
        // Sonst zerlegt der Browser die Zuordnung in <tr> und die Markierung stimmt nicht.
        if (preg_match('/^<t([dh])\b([^>]*)>(.*)<\/t\1>\s*$/is', $tpl, $m)) {
            return '<t' . $m[1] . $m[2] . '><!-- DBX-TPL-START|' . $path . ' -->'
                . $m[3] . '<!-- DBX-TPL-END --></t' . $m[1] . '>';
        }

        return "<!-- DBX-TPL-START|$path -->" . $tpl . "<!-- DBX-TPL-END -->";
    }


    /* =========================================================
       INC SYSTEM
    ========================================================= */

    /**
     * Verarbeitet [inc=...] Blöcke
     */
    private function process_inc($tpl) {

        $allowed = ['has_group', 'is_dbx_edit'];

        return preg_replace_callback('/\[inc=([^\]]+)\](.*?)\[\/inc\]/s', function ($m) use ($allowed) {

            $cond    = trim($m[1]);
            $content = $m[2];

            if ($cond === '1') return $content;
            if ($cond === '0') return '';

            // Funktion prüfen
            if (preg_match('/(\w+)\((.*?)\)/', $cond, $f)) {

                $fn   = $f[1];
                $args = array_map('trim', explode(',', $f[2]));

                // Template-Quotes entfernen:
                // has_group('admin') -> admin
                // has_group("admin") -> admin
                $args = array_map(function ($arg) {
                    $arg = trim($arg);

                    if (
                        (substr($arg, 0, 1) === "'" && substr($arg, -1) === "'") ||
                        (substr($arg, 0, 1) === '"' && substr($arg, -1) === '"')
                    ) {
                        $arg = substr($arg, 1, -1);
                    }

                    return $arg;
                }, $args);

                if (in_array($fn, $allowed, true)) {

                    $dbx = dbx();

                    if (is_object($dbx) && is_callable([$dbx, $fn])) {
                        return call_user_func_array([$dbx, $fn], $args) ? $content : '';
                    }

                    if (function_exists($fn)) {
                        return call_user_func_array($fn, $args) ? $content : '';
                    }
                }
            }

            return '';
        }, $tpl);
    }


    /* =========================================================
       TPL SYSTEM (REKURSIV)
    ========================================================= */

    /**
     * Verarbeitet [tpl=...] rekursiv
     */
    private function process_tpl($tpl, $data, $depth) {

        if ($depth > $this->max_depth) {
            return "<!-- MAX DEPTH -->";
        }

        return preg_replace_callback('/\[tpl=([^\]]+)\]/', function ($m) use ($data, $depth) {

            $parts = explode('|', $m[1], 3);

            $modul = $this->_modul;
            $file  = $m[1];

            if (count($parts) > 1) {
                $modul = $parts[0];
                $file  = $parts[1];
            }

            $type = $parts[2] ?? 'htm';

            return $this->get_tpl($modul . '|' . $file, $data, $type, 0, $depth + 1);

        }, $tpl);
    }


    /* =========================================================
       MAIN ENTRY
    ========================================================= */

    /** Liefert kleine, zentrale UI-Texte fuer gemeinsame Controls. */
    private function core_ui_text(string $key): string {
        $lng = (string)dbx()->get_system_var('dbx_lng', 'de');
        $lng = in_array($lng, array('en', 'es'), true) ? $lng : 'de';
        $texts = array(
            'de' => array(
                'save' => 'Speichern', 'filter' => 'Filter anwenden', 'reload' => 'Neu laden',
                'delete' => 'Löschen', 'delete_question' => 'Wirklich löschen?',
                'delete_undo_hint' => 'Dieser Vorgang kann nicht rückgängig gemacht werden.',
                'delete_rows' => 'Zeilen löschen', 'show_password' => 'Passwort anzeigen',
                'form_actions' => 'Formularaktionen', 'delete_record_title' => 'Datensatz löschen',
                'message_delete_error' => 'Der Datensatz konnte nicht gelöscht werden.',
                'message_delete_success' => 'Der Datensatz wurde gelöscht.',
                'message_save_error' => 'Die Daten konnten nicht gespeichert werden.',
                'message_save_success' => 'Die Daten wurden gespeichert.',
                'message_validation_error' => 'Bitte prüfen Sie die markierten Eingaben.',
                'message_warning' => 'Bitte prüfen Sie die Eingaben.',
                'report_values' => 'Report Werte', 'report_statistics' => 'Report Kennzahlen',
                'report_filters' => 'Report-Filter', 'filters' => 'Filter',
                'total' => 'Gesamt', 'filtered' => 'Selektiert', 'selected' => 'Ausgewählt',
                'pagination' => 'Seitennavigation', 'first' => 'Erste', 'previous' => 'Vorherige',
                'next' => 'Nächste', 'last' => 'Letzte', 'action' => 'Aktion',
                'choose' => 'Bitte wählen …', 'execute' => 'Ausführen',
                'execute_action' => 'Aktion ausführen', 'reset_search' => 'Suche zurücksetzen',
            ),
            'en' => array(
                'save' => 'Save', 'filter' => 'Apply filter', 'reload' => 'Reload',
                'delete' => 'Delete', 'delete_question' => 'Really delete?',
                'delete_undo_hint' => 'This action cannot be undone.',
                'delete_rows' => 'Delete rows', 'show_password' => 'Show password',
                'form_actions' => 'Form actions', 'delete_record_title' => 'Delete record',
                'message_delete_error' => 'The record could not be deleted.',
                'message_delete_success' => 'The record was deleted.',
                'message_save_error' => 'The data could not be saved.',
                'message_save_success' => 'The data was saved.',
                'message_validation_error' => 'Please check the highlighted fields.',
                'message_warning' => 'Please check your input.',
                'report_values' => 'Report values', 'report_statistics' => 'Report statistics',
                'report_filters' => 'Report filters', 'filters' => 'Filters',
                'total' => 'Total', 'filtered' => 'Filtered', 'selected' => 'Selected',
                'pagination' => 'Pagination', 'first' => 'First', 'previous' => 'Previous',
                'next' => 'Next', 'last' => 'Last', 'action' => 'Action',
                'choose' => 'Please choose …', 'execute' => 'Execute',
                'execute_action' => 'Execute action', 'reset_search' => 'Reset the search',
            ),
            'es' => array(
                'save' => 'Guardar', 'filter' => 'Aplicar filtro', 'reload' => 'Recargar',
                'delete' => 'Eliminar', 'delete_question' => '¿Eliminar realmente?',
                'delete_undo_hint' => 'Esta acción no se puede deshacer.',
                'delete_rows' => 'Eliminar filas', 'show_password' => 'Mostrar contraseña',
                'form_actions' => 'Acciones del formulario', 'delete_record_title' => 'Eliminar registro',
                'message_delete_error' => 'El registro no pudo ser eliminado.',
                'message_delete_success' => 'El registro fue eliminado.',
                'message_save_error' => 'Los datos no se pudieron guardar.',
                'message_save_success' => 'Los datos se guardaron.',
                'message_validation_error' => 'Revise los campos marcados.',
                'message_warning' => 'Revise los datos introducidos.',
                'report_values' => 'Valores del informe', 'report_statistics' => 'Estadísticas del informe',
                'report_filters' => 'Filtros del informe', 'filters' => 'Filtros',
                'total' => 'Total', 'filtered' => 'Filtrados', 'selected' => 'Seleccionados',
                'pagination' => 'Paginación', 'first' => 'Primera', 'previous' => 'Anterior',
                'next' => 'Siguiente', 'last' => 'Última', 'action' => 'Acción',
                'choose' => 'Seleccione …', 'execute' => 'Ejecutar',
                'execute_action' => 'Ejecutar acción', 'reset_search' => 'Restablecer la búsqueda',
            ),
        );
        return (string)($texts[$lng][$key] ?? $key);
    }

    /** Ersetzt kleine sprachabhängige UI-Texte ohne dupliziertes HTML. */
    private function replace_core_ui_tokens(string $tpl): string {
        if (strpos($tpl, '{ui:') === false) return $tpl;

        return (string)preg_replace_callback(
            '/\{ui:([a-z0-9_-]+)\}/i',
            fn(array $match): string => $this->core_ui_text(strtolower($match[1])),
            $tpl
        );
    }

    /** Normalisiert semantische Core-Namen auf gemeinsame UI-Templates. */
    private function normalize_core_ui_template(string &$file, &$data, string $type): void {
        if (strtolower($type) !== 'htm') return;
        if (!is_array($data)) $data = $this->normalize_replacements($data);
        if (!is_array($data)) $data = array();

        $alerts = array(
            'alert-info' => 'info', 'alert-success' => 'success',
            'alert-warning' => 'warning', 'alert-danger' => 'danger',
        );
        if (isset($alerts[$file])) {
            $data['alert_tone'] = $alerts[$file];
            $file = 'alert-default';
            return;
        }

        $field_states = array(
            'fld-alert-info' => 'info', 'fld-alert-success' => 'success',
            'fld-alert-warning' => 'warning', 'fld-alert-danger' => 'danger',
        );
        if (isset($field_states[$file])) {
            $data['field_status_tone'] = $field_states[$file];
            $file = 'field-status-default';
            return;
        }

        if ($file === 'button-bar-save' || $file === 'button-bar-filter') {
            $filter = $file === 'button-bar-filter';
            $data += array(
                'button_form_attr' => $filter ? '' : ' form="' . (string)($data['bar_form_id'] ?? '') . '"',
                'button_class' => 'btn btn-primary btn-sm ' . ($filter ? 'dbx-report-filter' : 'dbx-form-save'),
                'button_icon' => $filter ? 'bi-funnel-fill' : 'bi-save',
                'button_label' => $this->core_ui_text($filter ? 'filter' : 'save'),
                'button_label_class' => $filter ? 'dbx-report-bar-go' : 'visually-hidden',
                'button_attrs' => '',
            );
            $file = 'button-action-submit-default';
            return;
        }

        if (in_array($file, array('button-bar-reload', 'button-bar-reload-ajax', 'button-bar-delete'), true)) {
            $delete = $file === 'button-bar-delete';
            $ajax = $file === 'button-bar-reload-ajax';
            $label = $delete
                ? (string)($data['bar_delete_title'] ?? $this->core_ui_text('delete'))
                : $this->core_ui_text('reload');
            $href = $delete
                ? (string)($data['bar_delete_url'] ?? '')
                : (string)($data[$ajax ? 'bar_reload_href' : 'bar_reload_url'] ?? '');
            $attrs = '';
            if ($ajax) {
                $attrs = ' data-target="' . (string)($data['bar_reload_target'] ?? '')
                    . '" data-replace="' . (string)($data['bar_reload_replace'] ?? '') . '"';
            } elseif ($delete) {
                $attrs = ' data-confirm-title="<i class=\'bi bi-trash\'></i> ' . $label
                    . '" data-confirm-question="' . $this->core_ui_text('delete_question')
                    . '" data-confirm-hint="<small>' . (string)($data['bar_delete_hint'] ?? '')
                    . '</small>" data-confirm-buttons="yesno"';
            }
            $data += array(
                'button_href' => $href,
                'button_class' => 'btn btn-sm ' . ($delete ? 'btn-outline-danger dbxConfirm' : 'btn-outline-secondary' . ($ajax ? ' dbxAjax' : '')),
                'button_icon' => $delete ? 'bi-trash' : 'bi-arrow-clockwise',
                'button_label' => $label,
                'button_label_class' => 'visually-hidden',
                'button_attrs' => $attrs,
            );
            $file = 'button-action-link-default';
        }
    }

    /**
     * Zentrale Template-Funktion
     *
     * Pipeline:
     * 1. Modul auflösen
     * 2. Rohes Template laden (Cache)
     * 3. Aktuelle DBX-Systemvariablen einsetzen
     * 4. replaces($data)
     * 5. [inc]
     * 6. [tpl] rekursiv
     * 7. Marker (optional)
     */
    public function get_tpl($file, $data = '', $type = 'htm', $i = 0, $depth = 0) {
        if ($depth > $this->max_depth) {
            return "<!-- MAX DEPTH -->";
        }

        // "modul|file" Shortcut
        $modul = 'dbx';
        $parts = explode('|', $file);

        if (count($parts) > 1) {
            $modul = $parts[0];
            $file  = $parts[1];
        }

        // Modul Alias
        if ($modul === 'modul') {
            $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
        }

        if ($modul === 'dbx') {
            $this->normalize_core_ui_template($file, $data, (string)$type);
        }

        // 🔥 FIX: Nur beim ROOT setzen (Kontext stabil halten)
        if ($depth === 0) {
            $this->_modul = $modul;
        }

        $edit     = dbx()->get_system_var('dbx_edit', 0);
        $is_design = 0;

        // --- LOAD ---
        $tpl = $this->read_tpl($modul, $file, $type);
        $inert_code = $this->inert_code();
        $inert_blocks = array();
        $tpl = $inert_code->protect($tpl, $inert_blocks, false);

        // --- AKTUELLER SYSTEMZUSTAND ---
        // Nicht im Template-Cache ausführen: Design, Sprache, Seite und SEO-Werte
        // können sich innerhalb derselben Session oder sogar im Request ändern.
        $tpl = $this->replaces_dbx($tpl);
        $tpl = $this->replace_core_ui_tokens($tpl);

        // --- DATA ---
        $tpl = $this->replaces($tpl, $data);
        $tpl = $inert_code->protect($tpl, $inert_blocks);

        // --- INC ---
        if (strpos($tpl, '[inc=') !== false) {
            $tpl = $this->process_inc($tpl);
        }

        // --- TPL ---
        if (strpos($tpl, '[tpl=') !== false) {
            $tpl = $this->process_tpl($tpl, $data, $depth);
        }

        // --- MARKER ---
        $path = $this->get_cached_tpl_path($modul, $file, $type);

        if ($edit && $this->has_editor($modul, $is_design, $edit, $path)) {
            $tpl = $this->add_marker($tpl, $modul, $file, $type);
        }

        return $inert_code->restore($tpl, $inert_blocks);
    }

    // design template
    /**
     * Lädt das Design-Template für eine bestimmte Seite und Sprache.
     *
     * @param string $design        Das Design, das geladen werden soll.
     * @param string $page          Die Seite, die geladen werden soll.
     * @param string $language      Die Sprache, die verwendet werden soll.
     * @param string $type          Der Typ des Templates, z. B. 'htm' oder 'php'. Standard ist 'htm'.
     * @param int    $repurl        Bestimmt, ob URLs ersetzt werden sollen (1 = Ja, 0 = Nein). Standard ist 1.
     *
     * @return string               Der Inhalt des Templates.
     *
     * @par Beispiel
     * @code{.php}
     * $page_content = $this->get_design_tpl('user', 'home', 'de', 'htm');
     * @endcode
     */
    function get_design_tpl($design, $page, $language = 'de', $type = 'htm', $repurl = 1) {

        if (!$page) $page = 'default';
        if (substr($page, 0, 1) == "_") $design = 'admin';

        // Design Mapping (user/admin)
        if ($design == 'admin' || $design == 'user') {
            $config = dbx()->get_cfg('dbx');
            if ($design == 'admin') $design = $config['default_design_admin'];
            if ($design == 'user')  $design = $config['default_design_user'];
        }

        $page_content = '';
        $inert_code = $this->inert_code();
        $inert_blocks = array();
        $requested_page = $page;
        $dir_file = $this->get_design_tpl_dir_file($type, $design, $page, false);

        if (!$dir_file) {
            if ($requested_page != 'default') {
                dbx()->sys_msg('info', "Page ($requested_page) not exist. Use (default)");
            }

            $dir_file = $this->get_design_tpl_dir_file($type, $design, 'default', false);

            if ($dir_file) {
                $page = 'default';
            } else {
                dbx()->sys_msg('error', "Default page (default) not exist");
            }
        }

        $home_url = dbx()->get_base_url();

        if ($dir_file && file_exists($dir_file)) {

            $dir_file = dbx()->os_path($dir_file);

            // --- LOAD ---
            $page_content = file_get_contents($dir_file);
            $page_content = $inert_code->protect((string)$page_content, $inert_blocks, false);
            
            // --- SET SYSTEM STATE ---
            dbx()->set_system_var('dbx_activ_design', $design);
            dbx()->set_system_var('dbx_activ_page', $page);
            dbx()->set_system_var('dbx_activ_lng', $language);
            dbx()->register_editor_file('design', $dir_file);

            // --- URL FIX ---
            if ($repurl == 1 && $type == 'htm') {
                $url = "dbx/design/$design/";
                $new_url = '="' . $url;
                $i = dbx()->next_id();               $page_content = str_replace("<head>", "<head>\n <base href=\"" . $home_url . "\"/>", $page_content);
                $page_content = str_replace('{base_url}', $home_url, $page_content);
                $page_content = str_replace('="../', $new_url, $page_content);
                $page_content = str_replace('{i}', $i, $page_content);

            }

            // --- DBX REPLACE ---
            $page_content = $this->replaces_dbx($page_content);
            $page_content = $this->replace_design_slots($page_content, $design);
            $page_content = $inert_code->protect($page_content, $inert_blocks);

            // Bedingungen müssen die soeben eingesetzten DBX-Systemwerte sehen.
            if (strpos($page_content, '[inc=') !== false) {
                $page_content = $this->process_inc($page_content);
            }

            if ($repurl == 1 && $type == 'htm' && strpos($page_content, '[tpl=') !== false) {
                $page_content = $this->process_tpl($page_content, [], 1);
            }

            // =====================================================
            // 🔥 DESIGN EDITOR (NEU)
            // =====================================================
            $edit = dbx()->get_system_var('dbx_edit', 0);

            if (false && $edit == 4) {

                $url =
                    '?dbx_modul=dbxAdmin' .
                    '&dbx_run1=_edittpl_file' .
                    '&file=' . urlencode($dir_file);

                $icon =
                    '<a href="' . $url . '" class="dbx-win" ' .
                    'data-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" data-title="Template bearbeiten" data-width="900" data-height="80%" ' .
                    'style="
                        position:absolute;
                        top:10px;
                        left:10px;
                        z-index:9999;
                        background:rgba(0,0,0,0.7);
                        color:#fff;
                        font-size:12px;
                        padding:2px 5px;
                        border-radius:3px;
                        text-decoration:none;
                    ">✎</a>';

                // 👉 direkt nach <body> einfügen
                $page_content = preg_replace(
                    '/<body([^>]*)>/i',
                    '<body$1>' . "\n" . $icon,
                    $page_content,
                    1
                );
            }
        }

        // --- PHP TEMPLATE ---
        if ($type == 'php') {
            $dir_file = $this->get_design_tpl_dir_file('php', $design, $page, false);
            if ($dir_file && file_exists($dir_file)) {
                include $dir_file;
            }
        }

        return $inert_code->restore((string)$page_content, $inert_blocks);
    }

    /**
     * Setzt optionale, dateibasierte Bereiche einer Designschale ein.
     *
     * Ein Design kann in seinen Seiten-Templates die Marker
     * `[dbx:logo]`, `[dbx:branding]` und `[dbx:footer]` verwenden. Der Inhalt
     * wird aus `htm/logo.htm`, `htm/branding.htm` beziehungsweise
     * `htm/footer.htm` desselben Designs gelesen. Fehlt eine Fragmentdatei,
     * wird nur der betreffende Marker entfernt. Designs ohne diese Marker
     * bleiben vollständig unverändert.
     *
     * Die Fragmentdateien durchlaufen dieselben dbxTPL-Systemersetzungen wie
     * die Designschale. `[inc=...]` und `[tpl=...]` werden anschließend durch
     * den normalen Ablauf von get_design_tpl() verarbeitet.
     *
     * @param string $content Bereits geladenes Design-Template.
     * @param string $design  Aufgelöster technischer Designname.
     *
     * @return string Template mit eingesetzten Designfragmenten.
     */
    public function replace_design_slots(string $content, string $design): string {
        $slots = array(
            '[dbx:logo]' => 'logo',
            '[dbx:branding]' => 'branding',
            '[dbx:footer]' => 'footer',
        );

        foreach ($slots as $marker => $fragment) {
            if (strpos($content, $marker) === false) {
                continue;
            }

            $fragment_content = '';
            $dir_file = $this->get_design_tpl_dir_file('htm', $design, $fragment, false);
            if ($dir_file && is_file($dir_file)) {
                $fragment_content = (string)file_get_contents($dir_file);
                $fragment_content = str_replace('{base_url}', dbx()->get_base_url(), $fragment_content);
                $fragment_content = str_replace('="../', '="dbx/design/' . $design . '/', $fragment_content);
                $fragment_content = $this->replaces_dbx($fragment_content);
                // Ein Fragment darf weder einen zweiten Modulinhalt noch
                // weitere Design-Slots einschleusen. Verwaltete Designs
                // werden zusätzlich bereits beim Import strikt validiert.
                $fragment_content = str_replace('[dbx:content]', '', $fragment_content);
                $fragment_content = str_replace(array_keys($slots), '', $fragment_content);
                dbx()->register_editor_file('design', $dir_file);
            }

            $content = str_replace($marker, $fragment_content, $content);
        }

        return $content;
    }

    function get_design_tpl_dir_file($type, $design, $file, $fallback = true) {

        // Basisverzeichnis
        $base = dbx()->get_base_dir() . "dbx/design/$design/$type/";

        // 1. gewünschte Datei (mit Sprachsuffix)
        $dir_file = dbx()->lng_resolve_file($base, $file, $type, '', false);

        if ($dir_file === '' && file_exists($base . $file . '.' . $type)) {
            $dir_file = $base . $file . '.' . $type;
        }

        if ($dir_file) {
            return $dir_file;
        }

        if (!$fallback) {
            return '';
        }

        // 2. fallback: default (mit Sprachsuffix)
        $dir_file = dbx()->lng_resolve_file($base, 'default', $type, '', false);

        if ($dir_file === '' && file_exists($base . 'default.' . $type)) {
            $dir_file = $base . 'default.' . $type;
        }

        if ($dir_file) {
            return $dir_file;
        }

        // 3. nichts gefunden
        return '';
    }

}





