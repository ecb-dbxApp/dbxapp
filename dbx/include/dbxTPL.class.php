<?php
/**
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
 *   $_SESSION['dbx']['cache']['tpl']
 *
 * Struktur:
 *
 *   [modul][file][type][lng] = [
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
 *   ✔ funktioniert mit <ul>, <table>, etc.
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
 *   - rohe Templates im Laufzeit-/Session-Cache
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
    private $maxDepth = 10;
    public $_modul = 'dbx';


    /* =========================================================
       BASIC REPLACE
    ========================================================= */

    /**
     * Ersetzt {var} durch Werte aus $data
     */
    function replaces(string $tpl, $replaces): string {

        if (!is_array($replaces)) {
            $replaces = dbx()->parse_url($replaces);
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
     * Ersetzt DBX-Systemvariablen (CACHEBAR!)
     */
    public function replaces_dbx(string $tpl): string {

        $api = dbx();
        $coreConfig = is_object($api) && method_exists($api, 'get_config')
            ? $api->get_config('dbx')
            : array();
        $coreConfig = is_array($coreConfig) ? $coreConfig : array();
        $brand = trim((string)($coreConfig['brand_name'] ?? ''));
        if ($brand === '') {
            $brand = trim((string)($coreConfig['site_title'] ?? ($coreConfig['page'] ?? 'dbxapp')));
        }
        $tagline = trim((string)($coreConfig['brand_tagline'] ?? ''));
        $pageTitle = trim((string)dbx()->get_system_var('dbx_title', ''));
        $documentTitle = $pageTitle;
        if ($documentTitle === '') {
            $documentTitle = $brand;
        } elseif ($brand !== '' && stripos($documentTitle, $brand) === false) {
            $documentTitle .= ' · ' . $brand;
        }

        $tpl = str_replace('{dbx:base_href}', dbx()->get_base_url(), $tpl);
        $tpl = str_replace('{dbx:design}'   , dbx()->get_system_var('dbx_activ_design', dbx()->get_system_var('dbx_design')), $tpl);
        $tpl = str_replace('{dbx:color}'    , dbx()->get_skin(), $tpl);
        $tpl = str_replace('{dbx:skin_css}' , dbx()->get_skin_css(), $tpl);
        $tpl = str_replace('{dbx:skin_class}', dbx()->get_skin_class(), $tpl);
        $tpl = str_replace('{dbx:page}'     , dbx()->get_system_var('dbx_activ_page', dbx()->get_system_var('dbx_page')), $tpl);
        $tpl = str_replace('{dbx:lng}'      , dbx()->get_system_var('dbx_activ_lng', dbx()->get_system_var('dbx_lng')), $tpl);
        $tpl = str_replace('{dbx:title}'    , htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'), $tpl);
        $tpl = str_replace('{dbx:document_title}', htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8'), $tpl);
        $tpl = str_replace('{dbx:brand}'    , htmlspecialchars($brand, ENT_QUOTES, 'UTF-8'), $tpl);
        $tpl = str_replace('{dbx:tagline}'  , htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8'), $tpl);
        $tpl = str_replace('{dbx:perma}'    , dbx()->get_system_var('dbx_perma'), $tpl);

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

        return $tpl;
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

        $jsonLd = trim((string)dbx()->get_system_var('dbx_json_ld', ''));
        if ($jsonLd !== '') {
            $lines[] = $jsonLd;
        }

        if (!$lines) {
            return '';
        }

        return "\n    " . implode("\n    ", $lines);
    }

    private function effective_robots_meta(): string {
        // Technische Routen sind niemals eigenstaendige Suchergebnisse.
        // Diese Regel hat Vorrang vor dem SEO-Wert eines eventuell zugleich
        // geladenen Content-Datensatzes.
        $technicalKeys = array(
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
        foreach ($technicalKeys as $key) {
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

        if (!isset($_SESSION['dbx']['cache']['tpl'])) {
            $_SESSION['dbx']['cache']['tpl'] = [];
        }

        $cache =& $_SESSION['dbx']['cache']['tpl'];

        $base = dbx()->get_base_dir() . "dbx/modules/$modul/tpl/$type/";

        $path = function_exists('dbx_lng_resolve_file')
            ? dbx_lng_resolve_file($base, $file, $type, '', true)
            : '';

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

        $relPath = dbx()->editor_file_path($path);
        $mtime = (int) @filemtime($path);
        $size = (int) @filesize($path);
        $cached = $cache[$modul][$file][$type][$lng] ?? null;

        // Eine Session darf geänderte oder neu angelegte Sprach-Templates nicht
        // dauerhaft verdecken. Der Cache bleibt schnell, validiert aber seine
        // konkrete Quelldatei bei jedem Lesezugriff.
        if (is_array($cached)
            && (string)($cached['path'] ?? '') === $relPath
            && (int)($cached['mtime'] ?? -1) === $mtime
            && (int)($cached['size'] ?? -1) === $size) {
            return (string)($cached['tpl'] ?? '');
        }

        $tpl = file_get_contents($path);
        if (!is_string($tpl)) {
            return "<div class='alert alert-danger'>TPL ($modul/$file.$type) not readable</div>";
        }

        // Cache speichern
        $cache[$modul][$file][$type][$lng] = [
            'tpl'   => $tpl,
            'path'  => $relPath,
            'mtime' => $mtime,
            'size'  => $size,
        ];

        return $tpl;
    }


    /* =========================================================
       EDITOR (MARKER SYSTEM)
    ========================================================= */

    /**
     * Prüft ob Template Editor Marker bekommt
     */
    private function has_editor($modul, $isDesign, $edit, $path = ''): bool {
        if ((int)dbx()->get_system_var('dbx_editor', 0, 'int') > 0) {
            return false;
        }

        if ((string)dbx()->get_system_var('dbx_page', '') === '_tpledit') {
            return false;
        }

        $isDbxModule = $this->is_dbx_template_path($path);

        if ($path === '') {
            $isDbxModule = strtolower(trim((string) $modul)) === 'dbx';
        }

        switch ($edit) {
            case 1: return !$isDesign && !$isDbxModule;
            case 2: return !$isDesign && $isDbxModule;
            case 3: return !$isDesign;
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
        $path = $_SESSION['dbx']['cache']['tpl'][$modul][$file][$type][$lng]['path'] ?? '';

        // Auch alte Session-Cache-Eintraege mit absoluten Windows-Pfaden bereinigen.
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

        if ($depth > $this->maxDepth) {
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
        if ($depth > $this->maxDepth) {
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

        // 🔥 FIX: Nur beim ROOT setzen (Kontext stabil halten)
        if ($depth === 0) {
            $this->_modul = $modul;
        }

        $edit     = dbx()->get_system_var('dbx_edit', 0);
        $isDesign = 0;

        // --- LOAD ---
        $tpl = $this->read_tpl($modul, $file, $type);

        // --- AKTUELLER SYSTEMZUSTAND ---
        // Nicht im Template-Cache ausführen: Design, Sprache, Seite und SEO-Werte
        // können sich innerhalb derselben Session oder sogar im Request ändern.
        $tpl = $this->replaces_dbx($tpl);

        // --- DATA ---
        $tpl = $this->replaces($tpl, $data);

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

        if ($edit && $this->has_editor($modul, $isDesign, $edit, $path)) {
            $tpl = $this->add_marker($tpl, $modul, $file, $type);
        }

        return $tpl;
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
            $config = dbx()->get_config('dbx');
            if ($design == 'admin') $design = $config['default_design_admin'];
            if ($design == 'user')  $design = $config['default_design_user'];
        }

        $page_content = '';
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

        return $page_content;
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

            $fragmentContent = '';
            $dirFile = $this->get_design_tpl_dir_file('htm', $design, $fragment, false);
            if ($dirFile && is_file($dirFile)) {
                $fragmentContent = (string)file_get_contents($dirFile);
                $fragmentContent = str_replace('{base_url}', dbx()->get_base_url(), $fragmentContent);
                $fragmentContent = str_replace('="../', '="dbx/design/' . $design . '/', $fragmentContent);
                $fragmentContent = $this->replaces_dbx($fragmentContent);
                // Ein Fragment darf weder einen zweiten Modulinhalt noch
                // weitere Design-Slots einschleusen. Verwaltete Designs
                // werden zusätzlich bereits beim Import strikt validiert.
                $fragmentContent = str_replace('[dbx:content]', '', $fragmentContent);
                $fragmentContent = str_replace(array_keys($slots), '', $fragmentContent);
                dbx()->register_editor_file('design', $dirFile);
            }

            $content = str_replace($marker, $fragmentContent, $content);
        }

        return $content;
    }

    function get_design_tpl_dir_file($type, $design, $file, $fallback = true) {

        // Basisverzeichnis
        $base = dbx()->get_base_dir() . "dbx/design/$design/$type/";

        // 1. gewünschte Datei (mit Sprachsuffix)
        $dir_file = function_exists('dbx_lng_resolve_file')
            ? dbx_lng_resolve_file($base, $file, $type, '', false)
            : '';

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
        $dir_file = function_exists('dbx_lng_resolve_file')
            ? dbx_lng_resolve_file($base, 'default', $type, '', false)
            : '';

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





