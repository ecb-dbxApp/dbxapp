<?php
include_once 'dbxDB.class.php';

/**
 * =========================================================
 * DBX DD SYSTEMCLASS (dbxDD)
 * =========================================================
 *
 * Zweck
 * -----
 * dbxDD ist die technische Infrastruktur für DD-Verwaltung,
 * DD-Datei-Erzeugung, Schema-Lesen, Schema-Vergleich,
 * Backup/Restore sowie DB <-> DD Synchronisationsprozesse.
 *
 * Wichtige Architekturregel
 * -------------------------
 * - dbxDD ist eine Erweiterung von dbxDB.
 * - Bestehende Funktionen aus dbxDB sollen genutzt werden,
 *   nicht parallel neu erfunden werden.
 * - dbxDD stellt nur die benötigte Infrastruktur bereit.
 * - Die eigentliche Prozesssteuerung, Benutzerentscheidungen,
 *   Feld-Zuordnungen und Konfliktauflösungen sollen später
 *   über eine Admin-Verwaltung und/oder externe Mapping-Dateien erfolgen.
 *
 * Mapping-Grundsatz
 * -----------------
 * - Es wird immer zuerst ein Auto-Mapping versucht.
 * - Benutzer-/Admin-Mapping oder externe Mapping-Dateien
 *   müssen dieses Auto-Mapping gezielt übersteuern können.
 * - Das DD ist das fachliche Soll-Modell und hat Vorrang.
 *
 * Typ-Grundsatz
 * -------------
 * - DD-Feldtypen sollen kanonisch und DB-unabhängig sein.
 * - Als kanonische Basis werden MySQL-nahe DD-Typen verwendet
 *   (`int`, `char`, `varchar`, `text`, `date`, `datetime`, ...)
 * - SQLite/db3-Roh-Typen wie `TEXT`, `INTEGER` usw. sollen
 *   nicht ungefiltert ins DD übernommen werden.
 * - Die konkrete Umsetzung auf die jeweilige Ziel-DB übernimmt dbxDB.
 *
 * Beispiel
 * --------
 * ```php
 * $oDD = dbx()->get_system_obj('dbxDD');
 *
 * // DD aus vorhandener Tabelle erzeugen
 * $oDD->create_dd('crm', 'kunden', 'crm|kunden.db3', 'kunden');
 *
 * // Sync-Plan prüfen
 * $plan = $oDD->sync_dd_to_db('crm', 'kunden', 'check');
 *
 * // DB -> DD synchronisieren
 * $res = $oDD->sync_db_to_dd('crm', 'kunden', 'merge');
 * ```
 */
class dbxDD extends dbxDB
{
    protected string $_remember_modul = 'dbx';
    protected float $_max_step_runtime = 3.0;
    protected int $_chunk_size = 500;

    /**
     * Initialisiert dbxDD.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    /* =====================================================
     * CONFIG
     * ===================================================== */

    /**
     * Setzt die maximale Laufzeit eines technischen Prozess-Schritts.
     *
     * @param float $seconds Maximale Schritt-Laufzeit in Sekunden
     *
     * @return void
     */
    public function set_step_runtime(float $seconds): void
    {
        if ($seconds > 0) {
            $this->_max_step_runtime = $seconds;
        }
    }

    /**
     * Gibt die maximale Laufzeit eines technischen Prozess-Schritts zurück.
     *
     * @return float Maximale Schritt-Laufzeit in Sekunden
     */
    public function get_step_runtime(): float
    {
        return $this->_max_step_runtime;
    }

    /**
     * Setzt die Standard-Chunk-Größe für technische Prozesse.
     *
     * @param int $chunk_size Chunk-Größe
     *
     * @return void
     */
    public function set_chunk_size(int $chunk_size): void
    {
        if ($chunk_size > 0) {
            $this->_chunk_size = $chunk_size;
        }
    }

    /**
     * Gibt die Standard-Chunk-Größe für technische Prozesse zurück.
     *
     * @return int Chunk-Größe
     */
    public function get_chunk_size(): int
    {
        return $this->_chunk_size;
    }


    /* =====================================================
     * DD LOAD / CACHE
     * ===================================================== */

    /**
     * Nutzt direkt die bestehende dbxDB-Logik.
     *
     * Wichtiger Hinweis:
     * - dbxDB::load_dd() liefert bereits `table`, `fields` und `indexes`
     *   im neuen namespaced DD-Cache.
     * - dbxDD ergänzt hier keine parallele Cache-Logik.
     *
     * @param string $dd DD-Name
     *
     * @return array DD-Systeminfo aus dbxDB::load_dd()
     */
    public function load_dd(string $dd): array
    {
        return parent::load_dd($dd);
    }

    /**
     * Ermittelt die aufgelöste Cache-Position einer DD.
     *
     * Diese Hilfsfunktion nutzt die bestehende parent::load_dd()-Logik
     * und liefert daraus Modul-/DD-Name für den neuen DD-Cache.
     *
     * @param string $dd DD-Name
     *
     * @return array{
     *     dd_status:int,
     *     dd_modul:string,
     *     dd_name:string
     * }
     */
    protected function get_dd_cache_info(string $dd): array
    {
        $dd_sys = $this->load_dd($dd);

        return [
            'dd_status' => $dd_sys['dd_status'] ?? 0,
            'dd_modul'  => $dd_sys['dd_modul'] ?? '',
            'dd_name'   => $dd_sys['dd_name'] ?? '',
        ];
    }

    /**
     * Löscht den DD-Cache für eine DD.
     *
     * Wichtig:
     * - nutzt die neue Cache-Struktur aus dbxDB
     * - löscht gezielt die aufgelöste DD-Position
     *
     * @param string $dd DD-Name
     *
     * @return void
     */
    public function clear_dd_cache(string $dd): void
    {
        $dd_sys    = $this->get_dd_cache_info($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_modul && $dd_name && isset($_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name])) {
            unset($_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]);
            return;
        }

        /**
         * Fallback:
         * Falls DD noch nicht geladen werden konnte, versuchen wir die
         * Zielposition trotzdem direkt über die Aufruflogik aufzulösen.
         */
        $activ_modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');

        if (strpos($dd, '|') !== false) {
            $parts    = explode('|', $dd, 2);
            $dd_modul = trim($parts[0]);
            $dd_name  = trim($parts[1]);

            if ($dd_modul === 'modul' || $dd_modul === '') {
                $dd_modul = $activ_modul;
            }
        } else {
            $dd_modul = $activ_modul;
            $dd_name  = $dd;
        }

        if (isset($_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name])) {
            unset($_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]);
        }
    }

    /**
     * Gibt die Indexdefinitionen einer DD zurück.
     *
     * @param string $dd DD-Name
     *
     * @return array DD-Indexdefinitionen
     */
    public function get_dd_indexes(string $dd): array
    {
        $dd_sys    = $this->get_dd_cache_info($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status <= 0) {
            return [];
        }

        return $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['indexes'] ?? [];
    }

    /**
     * Gibt das komplette DD-Modell zurück.
     *
     * Rückgabe:
     * - `table`
     * - `fields`
     * - `indexes`
     *
     * @param string $dd DD-Name
     *
     * @return array DD-Modell
     */
    public function get_dd_model(string $dd): array
    {
        $dd_sys    = $this->get_dd_cache_info($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status <= 0) {
            return [];
        }

        return [
            'table'   => $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table']   ?? [],
            'fields'  => $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['fields']  ?? [],
            'indexes' => $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['indexes'] ?? [],
        ];
    }

    /**
     * Prüft, ob eine DD-Datei existiert.
     *
     * Unterstützt:
     * - `dd`
     * - `modul|dd`
     * - `modul|dd` mit `modul` als Platzhalter
     *
     * @param string $dd DD-Name
     *
     * @return bool True wenn Datei existiert
     */
    public function get_dd_exist($dd): bool
    {
        $activ_modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');

        if (strpos($dd, '|') !== false) {
            $parts    = explode('|', $dd, 2);
            $dd_modul = trim($parts[0]);
            $dd_name  = trim($parts[1]);

            if ($dd_modul === 'modul' || $dd_modul === '') {
                $dd_modul = $activ_modul;
            }
        } else {
            $dd_modul = $activ_modul;
            $dd_name  = $dd;
        }

        $dd_file1 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $dd_modul . '/dd/' . $dd_name . '.dd.php');
        $dd_file2 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/dd/' . $dd_name . '.dd.php');

        return file_exists($dd_file1) || file_exists($dd_file2);
    }


    /* =====================================================
     * DD TEMPLATE RENDER
     * ===================================================== */

    /**
     * Ermittelt den absoluten Dateipfad eines DD-Templates.
     *
     * @param string $name Template-Name ohne `.php`
     *
     * @return string Absoluter Template-Dateipfad
     */
    protected function get_dd_template_file(string $name): string
    {
        $file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/tpl/dd/' . $name . '.php');
        if (file_exists($file)) {
            return $file;
        }

        return dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/tpl/php/' . $name . '.php');
    }

    protected function dd_template_value(mixed $value): string
    {
        return str_replace(
            ['\\', "'"],
            ['\\\\', "\\'"],
            (string)$value
        );
    }

    /**
     * Rendert ein DD-Template lokal als PHP-Quelltext.
     *
     * Sonderfall für DD:
     * - Template ist PHP-Datei
     * - wird lokal ausgeführt
     * - liefert den erzeugten DD-Quelltext als String zurück
     *
     * @param string $template_name Template-Name ohne `.php`
     * @param array  $vars Template-Variablen
     *
     * @return string Gerenderter DD-Template-Inhalt
     */
    protected function render_dd_template(string $template_name, array $vars = []): string
    {
        $file = $this->get_dd_template_file($template_name);

        if (!file_exists($file)) {
            dbx()->sys_msg('error', 'dd', $template_name, 'missing template', $file);
            return '';
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return '';
        }

        $replace = [];
        foreach ($vars as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $replace['{' . $k . '}'] = $this->dd_template_value($v);
                }
            } else {
                $replace['{' . $key . '}'] = (string)$value;
            }
        }

        return strtr($content, $replace);
    }

    /**
     * Ermittelt den Dateipfad einer DD-Datei.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     *
     * @return string Absoluter DD-Dateipfad
     */
    protected function get_dd_file_path(string $modul, string $dd): string
    {
        if (!$modul) {
            $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
        }

        return dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/dd/' . $dd . '.dd.php');
    }

    /**
     * Speichert eine DD-Datei und leert danach den DD-Cache.
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param array  $table Tabellenbereich
     * @param array  $fields Feldbereich
     * @param array  $indexes Indexbereich
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function save_dd(string $modul, string $dd, array $table, array $fields, array $indexes = []): int
    {
        $ok = $this->write_dd($modul, $dd, $table, $fields, $indexes);

        if ($ok) {
            $this->clear_dd_cache($modul . '|' . $dd);
        }

        return $ok;
    }

    /**
     * Schreibt eine DD-Datei anhand der DD-Templates.
     *
     * Wichtige Architekturregel:
     * - dbxDD erzeugt hier nur die technische DD-Datei
     * - keine Admin-/Mapping-Logik in dieser Funktion
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param array  $table Tabellenbereich
     * @param array  $fields Feldbereich
     * @param array  $indexes Indexbereich
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function write_dd(string $modul, string $dd, array $table, array $fields, array $indexes = []): int
    {
        $table = $this->normalize_table_record($dd, $table);

        $table_block = $this->render_dd_template('dd_table', ['table' => $table]);

        $fields_block = '';
        foreach ($fields as $field) {
            $field = $this->normalize_field_record($field);
            $fields_block .= $this->render_dd_template('dd_field', ['field' => $field]) . "\n";
        }
        $fields_block = trim($fields_block);

        $indexes_block = '';
        foreach ($indexes as $index) {
            $index = $this->normalize_index_record($index);
            $indexes_block .= $this->render_dd_template('dd_index', ['index' => $index]) . "\n";
        }
        $indexes_block = trim($indexes_block);

        $content = $this->render_dd_template('dd_file', [
            'table_block'   => $table_block,
            'fields_block'  => $fields_block,
            'indexes_block' => $indexes_block,
        ]);

        if (!$content) {
            dbx()->sys_msg('error', 'dd', $dd, 'write_dd', 'empty dd content');
            return 0;
        }

        $file = $this->get_dd_file_path($modul, $dd);
        $dir  = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $ok = file_put_contents($file, $content);
        if ($ok === false) {
            dbx()->sys_msg('error', 'dd', $dd, 'write_dd', $file);
            return 0;
        }

        return 1;
    }


    /* =====================================================
     * NORMALIZE / INFER
     * ===================================================== */

    public function dd_table_schema_keys(): array
    {
        return [
            'server',
            'table',
            'datadic',
            'primary',
            'language',
            'version',
            'autosync',
            'cache',
            'trash',
            'trace',
            'update_sql',
            'default_sort',
            'form-dd-table',
            'read',
            'create',
            'update',
            'delete',
            'read_owner',
            'create_owner',
            'update_owner',
            'delete_owner',
        ];
    }

    public function dd_field_schema_keys(): array
    {
        return [
            'name',
            'type',
            'index',
            'length',
            'default',
            'label',
            'rules',
            'tooltip',
            'errormsg',
            'placeholder',
            'convert',
            'protect',
            'group',
            'mask',
            'data',
            'options',
            'tpl',
            'js',
            'prompt',
        ];
    }

    public function dd_index_schema_keys(): array
    {
        return [
            'name',
            'type',
            'fields',
            'unique',
            'comment',
        ];
    }

    public function normalize_dd_field(array $field): array
    {
        return $this->normalize_field_record($field);
    }

    public function normalize_dd_index(array $index): array
    {
        return $this->normalize_index_record($index);
    }

    /**
     * Normalisiert einen DD-Tabellensatz.
     *
     * Ziel:
     * - sinnvolle Defaults setzen
     * - vorhandene Werte erhalten
     * - DD selbst bleibt fachliches Soll-Modell
     *
     * @param string $dd DD-Name
     * @param array  $table Tabellenbereich
     *
     * @return array Normalisierter Tabellenbereich
     */
    protected function normalize_table_record(string $dd, array $table): array
    {
        $defaults = [
            'server'       => $table['server'] ?? 'dbXsystem',
            'table'        => $table['table'] ?? $dd,
            'datadic'      => $dd,
            'primary'      => '',
            'language'     => '',
            'version'      => '1.0',
            'autosync'     => '0',
            'cache'        => '0',
            'trash'        => '0',
            'trace'        => '0',
            'update_sql'   => '',
            'default_sort' => '',
            'form-dd-table'=> '',

            'read'         => '*',
            'create'       => '*',
            'update'       => '*',
            'delete'       => '*',

            'read_owner'   => '*',
            'create_owner' => '*',
            'update_owner' => '*',
            'delete_owner' => '*',
        ];

        foreach ($defaults as $k => $v) {
            if (!isset($table[$k])) {
                $table[$k] = $v;
            }
        }

        $table['datadic'] = $dd;

        return $this->sort_schema_record($table, $this->dd_table_schema_keys());
    }

    /**
     * Normalisiert einen DD-Feldsatz.
     *
     * @param array $field Feldbereich
     *
     * @return array Normalisierter Feldbereich
     */
    protected function normalize_field_record(array $field): array
    {
        $defaults = [
            'name'        => '',
            'type'        => 'varchar',
            'index'       => '',
            'length'      => '',
            'default'     => '',
            'label'       => '',
            'rules'       => '',
            'tooltip'     => '',
            'errormsg'    => '',
            'placeholder' => '',
            'convert'     => '',
            'protect'     => '0',
            'group'       => '',
            'mask'        => '',
            'data'        => '',
            'options'     => '',
            'tpl'         => '',
            'js'          => '',
            'prompt'      => '',
        ];

        foreach ($defaults as $k => $v) {
            if (!isset($field[$k])) {
                $field[$k] = $v;
            }
        }

        return $field;
    }

    /**
     * Normalisiert einen DD-Indexsatz.
     *
     * @param array $index Indexbereich
     *
     * @return array Normalisierter Indexbereich
     */
    protected function normalize_index_record(array $index): array
    {
        $defaults = [
            'name'    => '',
            'type'    => 'INDEX',
            'fields'  => '',
            'unique'  => '0',
            'comment' => '',
        ];

        foreach ($defaults as $k => $v) {
            if (!isset($index[$k])) {
                $index[$k] = $v;
            }
        }

        return $index;
    }

    /**
     * Prüft, ob ein Feld ein DBX-Systemfeld ist.
     *
     * @param string $name Feldname
     *
     * @return bool True wenn Systemfeld
     */
    protected function is_system_field(string $name): bool
    {
        return in_array(strtolower($name), ['id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner'], true);
    }

    /**
     * Leitet ein Standard-Label aus dem Feldnamen ab.
     *
     * @param string $name Feldname
     *
     * @return string Abgeleitetes Label
     */
    protected function infer_label_from_name(string $name): string
    {
        if ($this->is_system_field($name)) {
            return str_replace('_', ' ', ucfirst($name));
        }

        return $name;
    }

    /**
     * Leitet eine Standard-Regel aus einem Feld ab.
     *
     * @param array $field Feldbereich
     *
     * @return string Regel-String
     */
    protected function infer_rules_from_field(array $field): string
    {
        $name = strtolower((string)($field['name'] ?? ''));
        $type = strtolower((string)($field['type'] ?? 'varchar'));
        $length = trim((string)($field['length'] ?? ''));

        if ($name === 'id') {
            return 'int';
        }

        if (in_array($type, ['bool', 'boolean', 'bit'], true)) {
            return 'int';
        }

        if (in_array($type, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint'], true)) {
            return 'int';
        }

        if (in_array($type, ['decimal', 'numeric'], true)) {
            return 'decimal';
        }

        if (in_array($type, ['float', 'double', 'real'], true)) {
            return 'decimal';
        }

        if (in_array($type, ['date', 'datetime', 'timestamp'], true)) {
            return $type;
        }

        if ($type === 'time' || $type === 'year') {
            return 'parameter';
        }

        if (($type === 'tinyint' || $type === 'int') && $length === '1') {
            return 'int';
        }

        return 'parameter';
    }

    /**
     * Leitet ein sinnvolles Form-Template aus einer Felddefinition ab.
     *
     * @param array $field Feldbereich
     *
     * @return string Template-Name
     */
    protected function infer_tpl_from_field(array $field): string
    {
        $type = strtolower((string)($field['type'] ?? 'varchar'));
        $length = trim((string)($field['length'] ?? ''));

        if (in_array($type, ['bool', 'boolean', 'bit'], true)
            || (in_array($type, ['tinyint', 'int', 'integer'], true) && $length === '1')) {
            return 'checkbox-label';
        }

        if (in_array($type, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint'], true)) {
            return 'integer-label';
        }

        if ($type === 'date') {
            return 'date-label';
        }

        if (in_array($type, ['datetime', 'timestamp'], true)) {
            return 'datetime-label';
        }

        if (in_array($type, ['text', 'mediumtext', 'longtext'], true)) {
            return 'textarea-label';
        }

        return 'text-label';
    }

    /**
     * Normalisiert einen Default-Wert aus DB-Metadaten.
     *
     * @param mixed $value Rohwert
     *
     * @return string Normalisierter Default-Wert
     */
    protected function normalize_default_value(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string)$value);
        $value = trim($value, " \t\n\r\0\x0B'\"");

        return $value;
    }

    /**
     * Zerlegt einen SQL-Typ in Basis-Typ und Länge.
     *
     * Beispiel:
     * - `varchar(28)` -> `varchar`, `28`
     * - `text` -> `text`, `(leer)`
     *
     * @param string $type Roh-Typ
     *
     * @return array{type:string,length:string}
     */
    protected function parse_sql_type(string $type): array
    {
        $type = trim($type);

        if (preg_match('/^([a-zA-Z0-9_]+)\s*(?:\((.*?)\))?/i', $type, $m)) {
            return [
                'type'   => strtolower($m[1]),
                'length' => (string)($m[2] ?? ''),
            ];
        }

        return [
            'type'   => strtolower($type),
            'length' => '',
        ];
    }

    /**
     * Mappt einen DB-Roh-Typ auf einen kanonischen DD-Typ.
     *
     * Wichtige Regel:
     * - DD-Typen sollen MySQL-nahe, kanonische Typen sein.
     * - SQLite/db3-Roh-Typen wie `TEXT` sollen nicht ungefiltert
     *   ins DD übernommen werden.
     * - Für SQLite/db3 wird nur dann `varchar`/`char` erkannt, wenn
     *   die Tabelle diese Typen so deklariert. Reines `TEXT` bleibt als
     *   kanonischer MySQL-DD-Typ `text`.
     *
     * Das DD bleibt das Soll-Modell. Feineres Mapping kann später
     * durch Admin-/Mappinglogik übersteuert werden.
     *
     * @param string $dbType Quell-DB-Typ
     * @param string $rawType Roh-Typ aus DB-Metadaten
     * @param string $length Roh-Länge
     *
     * @return string Kanonischer DD-Typ
     */
    protected function map_db_type_to_dd_type(string $dbType, string $rawType, string $length = ''): string
    {
        $dbType  = strtolower(trim($dbType));
        $rawType = strtolower(trim($rawType));
        $length  = trim($length);

        if (in_array($rawType, ['bool', 'boolean'], true)) {
            return 'bool';
        }

        if (in_array($rawType, ['tinyint', 'smallint', 'mediumint', 'bigint'], true)) {
            return $rawType;
        }

        if (in_array($rawType, ['int', 'integer', 'serial', 'number'], true)) {
            return 'int';
        }

        if (in_array($rawType, ['bit'], true)) {
            return 'bit';
        }

        if (in_array($rawType, ['date', 'time', 'year'], true)) {
            return $rawType;
        }

        if (in_array($rawType, ['datetime', 'timestamp'], true)) {
            return 'datetime';
        }

        if (in_array($rawType, ['char', 'nchar'], true)) {
            return 'char';
        }

        if (in_array($rawType, ['varchar', 'varchar2', 'nvarchar', 'string'], true)) {
            return 'varchar';
        }

        if (in_array($rawType, ['tinytext', 'mediumtext', 'longtext'], true)) {
            return $rawType;
        }

        if (in_array($rawType, ['text', 'clob'], true)) {
            return 'text';
        }

        if (in_array($rawType, ['decimal', 'numeric'], true)) {
            return 'decimal';
        }

        if (in_array($rawType, ['float', 'real'], true)) {
            return 'float';
        }

        if (in_array($rawType, ['double'], true)) {
            return 'double';
        }

        if (in_array($rawType, ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'json', 'enum', 'set'], true)) {
            return $rawType;
        }

        if ($dbType === 'sqlite') {
            return 'text';
        }

        return 'varchar';
    }


    /* =====================================================
     * DB -> DD READER
     * ===================================================== */

    /**
     * Liest die Feldstruktur einer DB-Tabelle und erzeugt daraus
     * kanonische DD-Felddefinitionen.
     *
     * Hinweis:
     * - Die erzeugten Feldtypen sollen DD-/MySQL-nah sein.
     * - SQLite/db3-Roh-Typen werden nicht 1:1 ins DD übernommen.
     *
     * @param string $server Servername
     * @param string $tableName Tabellenname
     *
     * @return array DD-Feldliste
     */
    public function get_db_fields($server, $tableName): array
    {
        $db_fields = [];

        if (!$this->get_table_exist($server, $tableName)) {
            return $db_fields;
        }

        $dbType = $this->get_db_type($server);

        switch ($dbType) {
            case 'mysql':
                $sql  = "SHOW COLUMNS FROM " . $this->quote_ident($server, $tableName);
                $rows = $this->rawQuery($server, $sql);

                foreach ($rows as $row) {
                    $typeInfo = $this->parse_sql_type((string)($row['Type'] ?? ''));
                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['Field'] ?? ''),
                        $dbType,
                        (string)($typeInfo['type'] ?? 'varchar'),
                        (string)($typeInfo['length'] ?? ''),
                        ((string)($row['Null'] ?? 'YES') === 'YES') ? '1' : '0',
                        $row['Default'] ?? '',
                        (string)($row['Key'] ?? '')
                    );
                }
                break;

            case 'sqlite':
                $sql  = "PRAGMA table_info(" . $this->quote_ident($server, $tableName) . ")";
                $rows = $this->rawQuery($server, $sql);

                foreach ($rows as $row) {
                    $typeInfo = $this->parse_sql_type((string)($row['type'] ?? ''));
                    $index    = ((int)($row['pk'] ?? 0) === 1) ? 'PRI' : '';

                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['name'] ?? ''),
                        $dbType,
                        (string)($typeInfo['type'] ?? 'text'),
                        (string)($typeInfo['length'] ?? ''),
                        ((int)($row['notnull'] ?? 0) === 1) ? '0' : '1',
                        $row['dflt_value'] ?? '',
                        $index
                    );
                }
                break;

            case 'pgsql':
                $sql = "
                    SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
                    FROM information_schema.columns
                    WHERE table_name = " . $this->sql_quote($server, $tableName) . "
                    ORDER BY ordinal_position
                ";
                $rows = $this->rawQuery($server, $sql);

                foreach ($rows as $row) {
                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['column_name'] ?? ''),
                        $dbType,
                        (string)($row['data_type'] ?? 'text'),
                        (string)($row['character_maximum_length'] ?? ''),
                        ((string)($row['is_nullable'] ?? 'YES') === 'YES') ? '1' : '0',
                        $row['column_default'] ?? '',
                        ''
                    );
                }
                break;

            case 'sqlsrv':
                $sql = "
                    SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = " . $this->sql_quote($server, $tableName) . "
                    ORDER BY ORDINAL_POSITION
                ";
                $rows = $this->rawQuery($server, $sql);

                foreach ($rows as $row) {
                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['COLUMN_NAME'] ?? ''),
                        $dbType,
                        (string)($row['DATA_TYPE'] ?? 'varchar'),
                        (string)($row['CHARACTER_MAXIMUM_LENGTH'] ?? ''),
                        ((string)($row['IS_NULLABLE'] ?? 'YES') === 'YES') ? '1' : '0',
                        $row['COLUMN_DEFAULT'] ?? '',
                        ''
                    );
                }
                break;

            case 'oci':
                $sql = "
                    SELECT column_name, data_type, data_length, nullable, data_default
                    FROM user_tab_columns
                    WHERE table_name = UPPER(" . $this->sql_quote($server, $tableName) . ")
                    ORDER BY column_id
                ";
                $rows = $this->rawQuery($server, $sql);

                foreach ($rows as $row) {
                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['COLUMN_NAME'] ?? ''),
                        $dbType,
                        (string)($row['DATA_TYPE'] ?? 'varchar2'),
                        (string)($row['DATA_LENGTH'] ?? ''),
                        ((string)($row['NULLABLE'] ?? 'Y') === 'Y') ? '1' : '0',
                        $row['DATA_DEFAULT'] ?? '',
                        ''
                    );
                }
                break;
        }

        return $db_fields;
    }

    /**
     * Liest die Indexstruktur einer DB-Tabelle.
     *
     * @param string $server Servername
     * @param string $tableName Tabellenname
     *
     * @return array DD-Indexliste
     */
    public function get_db_indexes(string $server, string $tableName): array
    {
        $indexes = [];

        if (!$this->get_table_exist($server, $tableName)) {
            return $indexes;
        }

        $dbType = $this->get_db_type($server);

        switch ($dbType) {
            case 'mysql':
                $sql  = "SHOW INDEX FROM " . $this->quote_ident($server, $tableName);
                $rows = $this->rawQuery($server, $sql);

                $grouped = [];
                foreach ($rows as $row) {
                    $keyName   = (string)($row['Key_name'] ?? '');
                    $colName   = (string)($row['Column_name'] ?? '');
                    $nonUnique = (int)($row['Non_unique'] ?? 1);

                    if (!isset($grouped[$keyName])) {
                        $grouped[$keyName] = [
                            'name'    => $keyName,
                            'type'    => ($keyName === 'PRIMARY') ? 'PRIMARY' : (($nonUnique === 0) ? 'UNIQUE' : 'INDEX'),
                            'fields'  => [],
                            'unique'  => ($keyName === 'PRIMARY' || $nonUnique === 0) ? '1' : '0',
                            'comment' => '',
                        ];
                    }

                    $grouped[$keyName]['fields'][] = $colName;
                }

                foreach ($grouped as $idx) {
                    $idx['fields'] = implode(',', $idx['fields']);
                    $indexes[] = $idx;
                }
                break;

            case 'sqlite':
                $sql  = "PRAGMA index_list(" . $this->quote_ident($server, $tableName) . ")";
                $rows = $this->rawQuery($server, $sql);

                foreach ($rows as $row) {
                    $name   = (string)($row['name'] ?? '');
                    $unique = ((int)($row['unique'] ?? 0) === 1) ? '1' : '0';
                    $origin = (string)($row['origin'] ?? '');

                    $sql2 = "PRAGMA index_info(" . $this->quote_ident($server, $name) . ")";
                    $cols = $this->rawQuery($server, $sql2);

                    $fields = [];
                    foreach ($cols as $col) {
                        $fields[] = (string)($col['name'] ?? '');
                    }

                    $type = 'INDEX';
                    if ($origin === 'pk') {
                        $type   = 'PRIMARY';
                        $unique = '1';
                    } elseif ($unique === '1') {
                        $type = 'UNIQUE';
                    }

                    $indexes[] = [
                        'name'    => $name,
                        'type'    => $type,
                        'fields'  => implode(',', $fields),
                        'unique'  => $unique,
                        'comment' => '',
                    ];
                }
                break;

            case 'pgsql':
                $sql = "
                    SELECT indexname, indexdef
                    FROM pg_indexes
                    WHERE tablename = " . $this->sql_quote($server, $tableName);
                $rows = $this->rawQuery($server, $sql);

                foreach ($rows as $row) {
                    $name = (string)($row['indexname'] ?? '');
                    $def  = (string)($row['indexdef'] ?? '');

                    preg_match('/\((.*?)\)/', $def, $m);
                    $fields = $m[1] ?? '';
                    $fields = str_replace(['"', ' '], '', $fields);

                    $unique = (stripos($def, 'UNIQUE INDEX') !== false) ? '1' : '0';
                    $type   = ($unique === '1') ? 'UNIQUE' : 'INDEX';

                    $indexes[] = [
                        'name'    => $name,
                        'type'    => $type,
                        'fields'  => $fields,
                        'unique'  => $unique,
                        'comment' => '',
                    ];
                }
                break;
        }

        return $indexes;
    }

    /**
     * Baut aus gelesenen DB-Metadaten einen DD-Feldsatz.
     *
     * @param string $name Feldname
     * @param string $dbType DB-Typ
     * @param string $rawType Roh-Typ
     * @param string $length Länge
     * @param string $isNull Null-Flag
     * @param mixed  $default Default-Wert
     * @param string $index Indexkennzeichen
     *
     * @return array DD-Feldsatz
     */
    protected function build_dd_field_from_db_meta(
        string $name,
        string $dbType,
        string $rawType,
        string $length,
        string $isNull,
        mixed $default,
        string $index = ''
    ): array {
        $ddType = $this->map_db_type_to_dd_type($dbType, $rawType, $length);

        $field = [
            'name'        => $name,
            'type'        => $ddType,
            'index'       => strtoupper((string)$index),
            'length'      => $length,
            'default'     => $this->normalize_default_value($default),
            'label'       => $this->infer_label_from_name($name),
            'rules'       => '',
            'tooltip'     => '',
            'errormsg'    => '',
            'placeholder' => '',
            'convert'     => '',
            'protect'     => $this->is_system_field($name) ? '2' : '0',
            'group'       => '',
            'mask'        => '',
            'data'        => '',
            'options'     => '',
            'tpl'         => '',
            'js'          => '',
        ];

        $field['rules'] = $this->infer_rules_from_field($field);
        $field['tpl']   = $this->infer_tpl_from_field($field);

        return $this->sort_schema_record($field, $this->dd_field_schema_keys());
    }


    /* =====================================================
     * DD -> DB SQL
     * ===================================================== */

    /**
     * Quotet einen SQL-Wert für Roh-SQL.
     *
     * @param string $server Servername
     * @param mixed  $value Wert
     *
     * @return string SQL-quoted String
     */
    protected function sql_quote(string $server, mixed $value): string
    {
        return "'" . $this->escape((string)$value, $server) . "'";
    }

    /**
     * Quotet einen SQL-Identifier abhängig vom DB-Typ.
     *
     * @param string $server Servername
     * @param string $name Identifier
     *
     * @return string Gequoteter Identifier
     */
    protected function quote_ident(string $server, string $name): string
    {
        $dbType = $this->get_db_type($server);

        return match ($dbType) {
            'mysql'  => '`' . str_replace('`', '``', $name) . '`',
            'sqlsrv' => '[' . str_replace(']', ']]', $name) . ']',
            default  => '"' . str_replace('"', '""', $name) . '"',
        };
    }

    /**
     * Mappt einen kanonischen DD-Typ auf einen konkreten SQL-Typ
     * des Zielsystems.
     *
     * Wichtig:
     * - DD bleibt kanonisch
     * - konkrete Dialekt-Umsetzung erfolgt hier
     *
     * @param string $dbType Ziel-DB-Typ
     * @param string $type DD-Typ
     * @param string $length Länge
     *
     * @return string SQL-Typ
     */
    protected function map_dd_type_to_sql_type(string $dbType, string $type, string $length = ''): string
    {
        $type = strtolower(trim($type));
        $len  = trim($length);

        switch ($dbType) {
            case 'sqlite':
                return match ($type) {
                    'bool',
                    'boolean',
                    'tinyint',
                    'smallint',
                    'mediumint',
                    'int',
                    'integer',
                    'bigint',
                    'bit'        => 'INTEGER',
                    'decimal',
                    'numeric',
                    'float',
                    'double',
                    'real'       => 'REAL',
                    'binary',
                    'varbinary',
                    'tinyblob',
                    'blob',
                    'mediumblob',
                    'longblob'   => 'BLOB',
                    default      => 'TEXT',
                };

            case 'mysql':
                return match ($type) {
                    'tinyint'    => 'TINYINT' . ($len !== '' ? '(' . $len . ')' : ''),
                    'smallint'   => 'SMALLINT' . ($len !== '' ? '(' . $len . ')' : ''),
                    'mediumint'  => 'MEDIUMINT' . ($len !== '' ? '(' . $len . ')' : ''),
                    'int',
                    'integer'    => 'INT' . ($len !== '' ? '(' . $len . ')' : '(11)'),
                    'bigint'     => 'BIGINT' . ($len !== '' ? '(' . $len . ')' : ''),
                    'decimal',
                    'numeric'    => 'DECIMAL' . ($len !== '' ? '(' . $len . ')' : '(10,2)'),
                    'float'      => 'FLOAT' . ($len !== '' ? '(' . $len . ')' : ''),
                    'double',
                    'real'       => 'DOUBLE' . ($len !== '' ? '(' . $len . ')' : ''),
                    'bit'        => 'BIT' . ($len !== '' ? '(' . $len . ')' : '(1)'),
                    'char'       => 'CHAR(' . ($len !== '' ? $len : '1') . ')',
                    'binary'     => 'BINARY(' . ($len !== '' ? $len : '1') . ')',
                    'varbinary'  => 'VARBINARY(' . ($len !== '' ? $len : '255') . ')',
                    'tinytext'   => 'TINYTEXT',
                    'text'       => 'TEXT',
                    'mediumtext' => 'MEDIUMTEXT',
                    'longtext'   => 'LONGTEXT',
                    'tinyblob'   => 'TINYBLOB',
                    'blob'       => 'BLOB',
                    'mediumblob' => 'MEDIUMBLOB',
                    'longblob'   => 'LONGBLOB',
                    'json'       => 'JSON',
                    'enum'       => ($len !== '' ? 'ENUM(' . $len . ')' : 'VARCHAR(255)'),
                    'set'        => ($len !== '' ? 'SET(' . $len . ')' : 'VARCHAR(255)'),
                    'date'       => 'DATE',
                    'time'       => 'TIME(6)',
                    'year'       => 'YEAR',
                    'datetime'   => 'DATETIME(6)',
                    'timestamp'  => 'TIMESTAMP(6)',
                    default      => 'VARCHAR(' . ($len !== '' ? $len : '255') . ')',
                };

            case 'pgsql':
                return match ($type) {
                    'int', 'integer', 'mediumint' => 'INTEGER',
                    'bigint'     => 'BIGINT',
                    'smallint',
                    'tinyint'    => 'SMALLINT',
                    'decimal',
                    'numeric'    => 'NUMERIC' . ($len !== '' ? '(' . $len . ')' : ''),
                    'float',
                    'real'       => 'REAL',
                    'double'     => 'DOUBLE PRECISION',
                    'date'       => 'DATE',
                    'datetime',
                    'timestamp'  => 'TIMESTAMP',
                    'text',
                    'mediumtext',
                    'longtext'   => 'TEXT',
                    'char'       => 'CHAR(' . ($len !== '' ? $len : '1') . ')',
                    default      => 'VARCHAR(' . ($len !== '' ? $len : '255') . ')',
                };

            default:
                return match ($type) {
                    'int', 'integer', 'mediumint' => 'INTEGER',
                    'bigint'     => 'BIGINT',
                    'smallint',
                    'tinyint'    => 'SMALLINT',
                    'decimal',
                    'numeric'    => 'DECIMAL' . ($len !== '' ? '(' . $len . ')' : ''),
                    'float'      => 'FLOAT',
                    'double',
                    'real'       => 'DOUBLE',
                    'date'           => 'DATE',
                    'datetime',
                    'timestamp'      => 'TIMESTAMP',
                    'text',
                    'mediumtext',
                    'longtext'       => 'TEXT',
                    default          => 'VARCHAR(' . ($len !== '' ? $len : '255') . ')',
                };
        }
    }

    /**
     * Baut einen DEFAULT-SQL-Teil für einen DD-Default.
     *
     * @param string $server Servername
     * @param mixed  $default Default-Wert
     * @param string $ddType DD-Typ
     *
     * @return string DEFAULT-SQL-Teil
     */
    protected function build_default_sql(string $server, mixed $default, string $ddType): string
    {
        $default = (string)$default;
        $u = strtoupper($default);

        if ($u === 'CURRENT_TIMESTAMP') {
            return 'DEFAULT CURRENT_TIMESTAMP';
        }

        $numericTypes = [
            'int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'bit',
            'decimal', 'numeric', 'float', 'double', 'real',
        ];

        if (in_array(strtolower($ddType), $numericTypes, true) && is_numeric($default)) {
            return 'DEFAULT ' . $default;
        }

        return 'DEFAULT ' . $this->sql_quote($server, $default);
    }

    /**
     * Baut eine SQL-Spaltendefinition aus einem DD-Feld.
     *
     * @param string $server Servername
     * @param array  $field DD-Feldsatz
     *
     * @return string SQL-Spaltendefinition
     */
    protected function build_sql_column_from_dd(string $server, array $field): string
    {
        $dbType  = $this->get_db_type($server);
        $name    = $field['name']    ?? '';
        $type    = strtolower((string)($field['type'] ?? 'varchar'));
        $length  = (string)($field['length'] ?? '');
        $index   = strtoupper((string)($field['index'] ?? ''));
        $default = $field['default'] ?? '';

        $sqlType = $this->map_dd_type_to_sql_type($dbType, $type, $length);
        $col     = $this->quote_ident($server, $name) . ' ' . $sqlType;

        if ($index === 'PRI' && strtolower($name) === 'id') {
            switch ($dbType) {
                case 'sqlite':
                    return $this->quote_ident($server, $name) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
                case 'mysql':
                case 'cubrid':
                    return $this->quote_ident($server, $name) . ' ' . $sqlType . ' AUTO_INCREMENT PRIMARY KEY';
                case 'pgsql':
                case 'firebird':
                case 'ibm':
                case 'odbc':
                    return $this->quote_ident($server, $name) . ' INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';
                case 'sqlsrv':
                case 'dblib':
                    return $this->quote_ident($server, $name) . ' INTEGER IDENTITY(1,1) PRIMARY KEY';
                case 'oci':
                    return $this->quote_ident($server, $name) . ' NUMBER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';
                case 'informix':
                    return $this->quote_ident($server, $name) . ' SERIAL PRIMARY KEY';
                default:
                    break;
            }
        }

        if ($default !== '' && $default !== null) {
            $col .= ' ' . $this->build_default_sql($server, $default, $type);
        }

        return $col;
    }

    /**
     * Erzwingt die globale Tabellenidentitaet fuer jede neue Fachtabelle.
     *
     * `id` steht immer an erster Stelle, ist Integer und der alleinige
     * Primaerschluessel. Die treiberspezifische Auto-ID-Syntax wird danach in
     * `build_sql_column_from_dd()` erzeugt. Eventuelle weitere PRI-Markierungen
     * werden entfernt; fachliche Eindeutigkeit gehoert in UNIQUE-Indizes.
     *
     * @param array $fields DD-kompatible Feldliste
     *
     * @return array Normalisierte Feldliste
     */
    protected function enforce_auto_increment_id(array $fields): array
    {
        $idField = array(
            'name'    => 'id',
            'type'    => 'int',
            'index'   => 'PRI',
            'length'  => '11',
            'default' => '',
        );
        $normalized = array();

        foreach ($fields as $field) {
            $name = strtolower(trim((string)($field['name'] ?? '')));

            if ($name === '') {
                continue;
            }

            if ($name === 'id') {
                $idField = array_merge($field, $idField);
                continue;
            }

            if (strtoupper((string)($field['index'] ?? '')) === 'PRI') {
                $field['index'] = '';
            }

            $normalized[] = $field;
        }

        array_unshift($normalized, $idField);
        return $normalized;
    }

    /**
     * Liefert, ob der Treiber die Auto-ID zusammen mit PRIMARY KEY direkt an
     * der Spalte definiert.
     */
    protected function uses_inline_auto_increment_id(string $dbType): bool
    {
        return in_array($dbType, array(
            'sqlite', 'mysql', 'pgsql', 'sqlsrv', 'oci', 'firebird',
            'cubrid', 'dblib', 'ibm', 'informix', 'odbc',
        ), true);
    }

    /**
     * Baut das CREATE-TABLE-SQL aus einem DD.
     *
     * @param string $dd DD-Name
     *
     * @return string CREATE TABLE SQL
     */
    public function build_create_table_sql(string $dd): string
    {
        $server = $this->get_dd_server($dd);
        $table  = $this->get_dd_table($dd);
        $fields = $this->enforce_auto_increment_id($this->get_dd_fields($dd));
        $dbType = $this->get_db_type($server);

        $parts = [];
        $primaryFields = [];

        foreach ($fields as $field) {
            $parts[] = $this->build_sql_column_from_dd($server, $field);
            if (strtoupper((string)($field['index'] ?? '')) === 'PRI') {
                $isInlinePrimary = strtolower((string)($field['name'] ?? '')) === 'id'
                    && $this->uses_inline_auto_increment_id($dbType);
                if (!$isInlinePrimary) {
                    $primaryFields[] = $field['name'];
                }
            }
        }

        if ($primaryFields) {
            $quoted = [];
            foreach ($primaryFields as $fld) {
                $quoted[] = $this->quote_ident($server, $fld);
            }
            $parts[] = 'PRIMARY KEY (' . implode(',', $quoted) . ')';
        }

        $sql  = 'CREATE TABLE ' . $this->quote_ident($server, $table) . " (\n";
        $sql .= '  ' . implode(",\n  ", $parts) . "\n";
        $sql .= ')';

        return $sql;
    }

    /**
     * Erzeugt einen DB-Index aus einer DD-Indexdefinition.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     * @param array  $index DD-Indexsatz
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function create_db_index(string $server, string $table, array $index): int
    {
        $name   = (string)($index['name'] ?? '');
        $type   = strtoupper((string)($index['type'] ?? 'INDEX'));
        $fields = array_filter(array_map('trim', explode(',', (string)($index['fields'] ?? ''))));

        if (!$name || !$fields) {
            return 0;
        }

        $quotedFields = [];
        foreach ($fields as $fld) {
            $quotedFields[] = $this->quote_ident($server, $fld);
        }

        $sql = '';
        if ($type === 'UNIQUE') {
            $sql = 'CREATE UNIQUE INDEX ' . $this->quote_ident($server, $name)
                 . ' ON ' . $this->quote_ident($server, $table)
                 . ' (' . implode(',', $quotedFields) . ')';
        } elseif ($type !== 'PRIMARY') {
            $sql = 'CREATE INDEX ' . $this->quote_ident($server, $name)
                 . ' ON ' . $this->quote_ident($server, $table)
                 . ' (' . implode(',', $quotedFields) . ')';
        }

        if (!$sql) {
            return ($type === 'PRIMARY') ? 1 : 0;
        }

        return $this->exec_query($server, $sql);
    }

    /**
     * Legt eine fehlende SQLite-Datei fuer ein DD an, damit die Tabelle
     * anschliessend ueber die bestehende DB-Logik erzeugt werden kann.
     *
     * @param string $dd DD-Name
     *
     * @return int 1 wenn kein Fehler vorliegt, sonst 0
     */
    protected function ensure_sqlite_db_file_for_dd(string $dd): int
    {
        $server = $this->get_dd_server($dd);

        if (!preg_match('/\.(db3|sqlite|sqlite3)$/i', $server)) {
            return 1;
        }

        $dd_sys = $this->get_dd_cache_info($dd);
        $modul  = $dd_sys['dd_modul'] ?? '';
        $name   = $server;

        if (strpos($server, '|') !== false) {
            $parts = explode('|', $server, 2);
            $modul = trim($parts[0]);
            $name  = trim($parts[1]);
        }

        if ($modul === '' || $modul === 'modul') {
            $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
        }

        if ($modul === '') {
            $modul = 'dbx';
        }

        $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/db/');
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            dbx()->sys_msg('error', 'db', $server, 'create sqlite dir failed', $dir);
            return 0;
        }

        $file = dbx()->os_path($dir . $name);
        if (!file_exists($file) && @file_put_contents($file, '') === false) {
            dbx()->sys_msg('error', 'db', $server, 'create sqlite file failed', $file);
            return 0;
        }

        $hostRel = dbx()->config_path_store($dir, true);

        $_SESSION['dbx']['config']['dbx']['db'][$server] = [
            'type'   => 'sqlite',
            'host'   => $hostRel,
            'dbname' => basename($file),
            'user'   => '',
            'pass'   => '',
            'port'   => ''
        ];

        if (!isset($this->db[$server]) && !$this->dbConnect($server, 'sqlite', $hostRel, basename($file))) {
            return 0;
        }

        return 1;
    }

    /**
     * Erzeugt eine Datenbanktabelle aus einem DD.
     *
     * @param string $dd DD-Name
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function create_db_tab(string $dd): int
    {
        $server = $this->get_dd_server($dd);
        $table  = $this->get_dd_table($dd);

        if (!$server || !$table) {
            return 0;
        }

        if (!$this->ensure_sqlite_db_file_for_dd($dd)) {
            return 0;
        }

        if ($this->get_table_exist($server, $table)) {
            return 1;
        }

        $sql = $this->build_create_table_sql($dd);
        $ok  = $this->exec_query($server, $sql);

        if (!$ok) {
            return 0;
        }

        $indexes = $this->get_dd_indexes($dd);
        foreach ($indexes as $index) {
            if (strtoupper((string)($index['type'] ?? 'INDEX')) === 'PRIMARY') {
                continue;
            }
            $this->create_db_index($server, $table, $index);
        }

        return $this->get_table_exist($server, $table) ? 1 : 0;
    }

    /**
     * Fügt ein Feld aus DD-Definition in eine bestehende DB-Tabelle ein.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     * @param array  $field DD-Feldsatz
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function add_db_field_from_dd(string $server, string $table, array $field): int
    {
        $sql = 'ALTER TABLE ' . $this->quote_ident($server, $table)
             . ' ADD COLUMN ' . $this->build_sql_column_from_dd($server, $field);

        return $this->exec_query($server, $sql);
    }

    /**
     * Löscht eine Datenbanktabelle.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function drop_db_tab(string $server, string $table): int
    {
        if (!$this->get_table_exist($server, $table)) {
            return 1;
        }

        $sql = 'DROP TABLE ' . $this->quote_ident($server, $table);
        return $this->exec_query($server, $sql);
    }

    /**
     * Erzeugt eine DB-Tabelle direkt aus Feld-/Index-Metadaten.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     * @param array  $fields DD-kompatible Feldliste
     * @param array  $indexes DD-kompatible Indexliste
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function create_db_tab_from_fields(string $server, string $table, array $fields, array $indexes = []): int
    {
        if (!$server || !$table || !$fields) {
            return 0;
        }

        $fields = $this->enforce_auto_increment_id($fields);

        if ($this->get_table_exist($server, $table)) {
            return 1;
        }

        $dbType = $this->get_db_type($server);
        $parts = [];
        $primaryFields = [];

        foreach ($fields as $field) {
            if (empty($field['name'])) {
                continue;
            }

            $parts[] = $this->build_sql_column_from_dd($server, $field);

            if (strtoupper((string)($field['index'] ?? '')) === 'PRI') {
                $isInlinePrimary = strtolower((string)($field['name'] ?? '')) === 'id'
                    && $this->uses_inline_auto_increment_id($dbType);
                if (!$isInlinePrimary) {
                    $primaryFields[] = $field['name'];
                }
            }
        }

        if (!$parts) {
            return 0;
        }

        if ($primaryFields) {
            $quoted = [];
            foreach ($primaryFields as $fld) {
                $quoted[] = $this->quote_ident($server, $fld);
            }
            $parts[] = 'PRIMARY KEY (' . implode(',', $quoted) . ')';
        }

        $sql  = 'CREATE TABLE ' . $this->quote_ident($server, $table) . " (\n";
        $sql .= '  ' . implode(",\n  ", $parts) . "\n";
        $sql .= ')';

        $ok = $this->exec_query($server, $sql);
        if (!$ok) {
            return 0;
        }

        foreach ($indexes as $index) {
            if (strtoupper((string)($index['type'] ?? 'INDEX')) === 'PRIMARY') {
                continue;
            }
            $this->create_db_index($server, $table, $index);
        }

        return $this->get_table_exist($server, $table) ? 1 : 0;
    }

    /**
     * Leert eine konkrete DB-Tabelle ohne DD-Aufloesung.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function empty_db_table(string $server, string $table): int
    {
        if (!$server || !$table || !$this->get_table_exist($server, $table)) {
            return 0;
        }

        $dbType = $this->get_db_type($server);
        $qTable = $this->quote_ident($server, $table);
        $ok = 0;

        switch ($dbType) {
            case 'mysql':
                $ok = $this->exec_query($server, 'TRUNCATE TABLE ' . $qTable);
                if ($ok) {
                    $this->exec_query($server, 'ALTER TABLE ' . $qTable . ' AUTO_INCREMENT = 1');
                }
                break;

            case 'sqlite':
                $ok = $this->exec_query($server, 'DELETE FROM ' . $qTable);
                if ($ok && $this->sqlite_sequence_exists($server)) {
                    $this->exec_query($server, 'DELETE FROM sqlite_sequence WHERE name=' . $this->sql_quote($server, $table));
                }
                break;

            case 'pgsql':
                $ok = $this->exec_query($server, 'TRUNCATE TABLE ' . $qTable . ' RESTART IDENTITY');
                break;

            default:
                $ok = $this->exec_query($server, 'DELETE FROM ' . $qTable);
                break;
        }

        return $ok ? 1 : 0;
    }

    /**
     * Sucht ein DD, das auf eine konkrete DB-Tabelle zeigt.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     *
     * @return array Treffer mit Modul, DD, Referenz und Modell
     */
    public function find_dd_for_db_table(string $server, string $table): array
    {
        $serverKey = strtolower(trim($server));
        $tableKey  = strtolower(trim($table));

        if ($serverKey === '' || $tableKey === '') {
            return [];
        }

        $base = str_replace('\\', '/', dbx()->get_base_dir()) . 'dbx/modules/*/dd/*.dd.php';
        foreach (glob($base) as $file) {
            $norm = str_replace('\\', '/', $file);
            if (!preg_match('#/dbx/modules/([^/]+)/dd/([^/]+)\.dd\.php$#', $norm, $match)) {
                continue;
            }

            $modul = $match[1];
            $dd = $match[2];
            if ($dd === 'new' || $dd === '') {
                continue;
            }

            $ddRef = $modul . '|' . $dd;
            $model = $this->get_dd_model($ddRef);
            if (!$model) {
                continue;
            }

            $modelServer = strtolower((string)($model['table']['server'] ?? ''));
            $modelTable  = strtolower((string)($model['table']['table'] ?? ''));
            $serverMatch = ($modelServer === $serverKey);

            if (!$serverMatch && strpos($serverKey, '|') !== false) {
                [$serverModul, $serverName] = array_pad(explode('|', $serverKey, 2), 2, '');
                $serverMatch = ($serverModul === strtolower($modul))
                    && ($modelServer === $serverName || $modelServer === 'modul');
            }

            if ($serverMatch && $modelTable === $tableKey) {
                return [
                    'modul'  => $modul,
                    'dd'     => $dd,
                    'dd_ref' => $ddRef,
                    'model'  => $model,
                ];
            }
        }

        return [];
    }

    /**
     * Liefert die beste Strukturquelle fuer eine DB-Tabelle.
     *
     * Wenn ein DD existiert, hat dessen kanonische Definition Vorrang.
     * Sonst werden die physischen DB-Metadaten gelesen.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     *
     * @return array Schema mit source, fields und indexes
     */
    public function get_preferred_table_schema(string $server, string $table): array
    {
        $match = $this->find_dd_for_db_table($server, $table);
        if ($match && !empty($match['model']['fields'])) {
            return [
                'source'  => 'dd',
                'dd_ref'  => $match['dd_ref'],
                'fields'  => $match['model']['fields'] ?? [],
                'indexes' => $match['model']['indexes'] ?? [],
            ];
        }

        return [
            'source'  => 'db',
            'dd_ref'  => '',
            'fields'  => $this->get_db_fields($server, $table),
            'indexes' => $this->get_db_indexes($server, $table),
        ];
    }

    /**
     * Transferiert eine DB-Tabelle serveruebergreifend als Schrittprozess.
     *
     * Ablauf:
     * - Zielstruktur bei Bedarf aus Quellschema erzeugen
     * - Ziel leeren
     * - Quelle sichern
     * - Sicherung in Ziel wiederherstellen
     *
     * @return array Prozessstatus
     */
    public function transfer_table(
        string $sourceServer,
        string $sourceTable,
        string $targetServer,
        string $targetTable = '',
        string $mode = 'step',
        int $createTarget = 1,
        int $truncateTarget = 1
    ): array {
        $targetTable = $targetTable ?: $sourceTable;
        $mode = strtolower((string)$mode);
        if ($mode === '') {
            $mode = 'step';
        }

        $key = $this->proc_key('transfer_table', [
            $sourceServer,
            $sourceTable,
            $targetServer,
            $targetTable,
            $createTarget,
            $truncateTarget,
        ]);

        if ($mode === 'reset') {
            $this->clear_proc_state($key);
            return [
                'proc_key' => $key,
                'status'   => 'reset',
                'message'  => 'transfer state cleared',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if ($mode === 'status') {
            $state = $this->get_proc_state($key);
            return $state ?: [
                'proc_key' => $key,
                'status'   => 'new',
                'message'  => 'no active state',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if (in_array($mode, ['pause', 'resume', 'continue', 'cancel'], true)) {
            return $this->control_proc_state($key, $mode);
        }

        if ($mode === 'restart') {
            $this->clear_proc_state($key);
            $mode = 'step';
        }

        $state = $this->get_proc_state($key);

        if (!$state) {
            if (!$sourceServer || !$sourceTable || !$targetServer || !$targetTable) {
                return $this->proc_error(['proc_key' => $key], 'source or target missing');
            }

            if (!$this->get_table_exist($sourceServer, $sourceTable)) {
                return $this->proc_error(['proc_key' => $key], 'source table not found');
            }

            $state = $this->init_proc_state('transfer_table', $key, [
                'source_server'   => $sourceServer,
                'source_table'    => $sourceTable,
                'target_server'   => $targetServer,
                'target_table'    => $targetTable,
                'create_target'   => $createTarget ? 1 : 0,
                'truncate_target' => $truncateTarget ? 1 : 0,
                'backup_file'     => $this->build_backup_file_name($sourceServer, $sourceTable, true),
                'phase'           => 'prepare_target',
                'percent'         => 0,
                'message'         => 'transfer initialized',
            ]);
        }

        if ($this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        switch ($state['phase']) {
            case 'prepare_target':
                if (!$this->get_table_exist($state['target_server'], $state['target_table'])) {
                    if (empty($state['create_target'])) {
                        $state = $this->proc_error($state, 'target table not found');
                        $this->set_proc_state($key, $state);
                        return $state;
                    }

                    $schema  = $this->get_preferred_table_schema($state['source_server'], $state['source_table']);
                    $fields  = $schema['fields'] ?? [];
                    $indexes = $schema['indexes'] ?? [];
                    $ok = $this->create_db_tab_from_fields($state['target_server'], $state['target_table'], $fields, $indexes);

                    if (!$ok) {
                        $state = $this->proc_error($state, 'create target table failed');
                        $this->set_proc_state($key, $state);
                        return $state;
                    }
                }

                if (!empty($state['truncate_target'])) {
                    $this->empty_db_table($state['target_server'], $state['target_table']);
                }

                $state['phase']   = 'backup_source';
                $state['percent'] = 10;
                $state['step_percent'] = 100;
                $state['message'] = 'target ready';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'backup_source':
                $backup = $this->backup($state['source_server'], $state['source_table'], $state['backup_file'], 1);

                if (($backup['status'] ?? '') === 'error') {
                    $state = $this->proc_error($state, 'backup source failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                if (($backup['status'] ?? '') !== 'finished') {
                    $state['percent'] = 10 + (int)floor((($backup['percent'] ?? 0) * 0.4));
                    $state['step_percent'] = (int)($backup['percent'] ?? 0);
                    $state['message'] = $backup['message'] ?? 'backup source';
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state['phase']   = 'restore_target';
                $state['percent'] = 55;
                $state['step_percent'] = 100;
                $state['message'] = 'backup finished';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'restore_target':
                $restore = $this->restore($state['target_server'], $state['target_table'], $state['backup_file'], [], 1);

                if (($restore['status'] ?? '') === 'error') {
                    $state = $this->proc_error($state, 'restore target failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                if (($restore['status'] ?? '') !== 'finished') {
                    $state['percent'] = 55 + (int)floor((($restore['percent'] ?? 0) * 0.4));
                    $state['step_percent'] = (int)($restore['percent'] ?? 0);
                    $state['message'] = $restore['message'] ?? 'restore target';
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state = $this->proc_finish($state, 'transfer finished');
                $this->set_proc_state($key, $state);
                return $state;
        }

        $state = $this->proc_error($state, 'unknown transfer phase');
        $this->set_proc_state($key, $state);
        return $state;
    }


    /* =====================================================
     * STEP STATE
     * ===================================================== */

    /**
     * Erzeugt einen technischen Prozessschlüssel.
     *
     * @param string $type Prozesstyp
     * @param array  $parts Schlüsselteile
     *
     * @return string Prozessschlüssel
     */
    protected function proc_key(string $type, array $parts): string
    {
        return 'dbxdd_' . $type . '_' . md5(json_encode($parts));
    }

    /**
     * Liest einen technischen Prozessstatus.
     *
     * @param string $key Prozessschlüssel
     *
     * @return array Prozessstatus
     */
    protected function get_proc_state(string $key): array
    {
        $state = dbx()->get_remember_var($key, [], $this->_remember_modul);
        return is_array($state) ? $state : [];
    }

    /**
     * Speichert einen technischen Prozessstatus.
     *
     * @param string $key Prozessschlüssel
     * @param array  $state Prozessstatus
     *
     * @return void
     */
    protected function set_proc_state(string $key, array $state): void
    {
        dbx()->set_remember_var($key, $state, $this->_remember_modul);
    }

    /**
     * Löscht einen technischen Prozessstatus.
     *
     * @param string $key Prozessschlüssel
     *
     * @return void
     */
    protected function clear_proc_state(string $key): void
    {
        dbx()->set_remember_var($key, [], $this->_remember_modul);
    }

    /**
     * Initialisiert einen technischen Prozessstatus.
     *
     * @param string $type Prozesstyp
     * @param string $key Prozessschlüssel
     * @param array  $state Startstatus
     *
     * @return array Initialisierter Prozessstatus
     */
    protected function init_proc_state(string $type, string $key, array $state): array
    {
        $state['proc_type']      = $type;
        $state['proc_key']       = $key;
        $state['status']         = $state['status']         ?? 'running';
        $state['message']        = $state['message']        ?? '';
        $state['percent']        = $state['percent']        ?? 0;
        $state['step_percent']   = $state['step_percent']   ?? 0;
        $state['chunk_size']     = $state['chunk_size']     ?? $this->_chunk_size;
        $state['step_maxsec']    = $state['step_maxsec']    ?? $this->_max_step_runtime;
        $state['started_at']     = $state['started_at']     ?? date('Y-m-d H:i:s');
        $state['updated_at']     = date('Y-m-d H:i:s');
        $this->set_proc_state($key, $state);
        return $state;
    }

    /**
     * Prueft, ob ein Prozess aktuell keine weitere Arbeit ausfuehren darf.
     *
     * @param array $state Prozessstatus
     *
     * @return bool True wenn der Prozess warten oder beendet ist
     */
    protected function proc_is_waiting(array $state): bool
    {
        return in_array(($state['status'] ?? ''), ['finished', 'error', 'paused', 'canceled'], true);
    }

    /**
     * Steuert einen vorhandenen Prozessstatus.
     *
     * @param string $key Prozessschluessel
     * @param string $cmd pause|resume|continue|cancel|restart
     *
     * @return array Prozessstatus
     */
    protected function control_proc_state(string $key, string $cmd): array
    {
        $cmd = strtolower(trim($cmd));

        if ($cmd === 'restart') {
            $this->clear_proc_state($key);
            return [
                'proc_key' => $key,
                'status'   => 'reset',
                'message'  => 'process restarted',
                'percent'  => 0,
                'step_percent' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        $state = $this->get_proc_state($key);
        if (!$state) {
            $state = [
                'proc_key' => $key,
                'status'   => 'new',
                'message'  => 'no active state',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        $status = $state['status'] ?? 'new';

        if ($cmd === 'pause') {
            if (!in_array($status, ['finished', 'error', 'canceled'], true)) {
                $state['status'] = 'paused';
                $state['paused_at'] = date('Y-m-d H:i:s');
                $state['message'] = 'process paused';
            }
        } elseif ($cmd === 'resume' || $cmd === 'continue') {
            if (in_array($status, ['paused', 'canceled', 'new'], true)) {
                $state['status'] = 'running';
                $state['resumed_at'] = date('Y-m-d H:i:s');
                $state['message'] = ($cmd === 'continue') ? 'process continued' : 'process resumed';
            }
        } elseif ($cmd === 'cancel') {
            if (!in_array($status, ['finished', 'error'], true)) {
                $state['status'] = 'canceled';
                $state['canceled_at'] = date('Y-m-d H:i:s');
                $state['message'] = 'process canceled';
            }
        }

        $state = $this->proc_response($state);
        $this->set_proc_state($key, $state);
        return $state;
    }

    /**
     * Aktualisiert einen Prozessstatus für Rückgabe.
     *
     * @param array $state Prozessstatus
     *
     * @return array Rückgabe-Prozessstatus
     */
    protected function proc_response(array $state): array
    {
        $state['updated_at'] = date('Y-m-d H:i:s');
        return $state;
    }

    /**
     * Erzeugt einen Fehlerstatus für einen Prozess.
     *
     * @param array  $state Prozessstatus
     * @param string $message Fehlermeldung
     *
     * @return array Fehlerstatus
     */
    protected function proc_error(array $state, string $message): array
    {
        $state['status']  = 'error';
        $state['message'] = $message;
        $state['percent'] = $state['percent'] ?? 0;
        $state['step_percent'] = $state['step_percent'] ?? 0;
        return $this->proc_response($state);
    }

    /**
     * Erzeugt einen Fertig-Status für einen Prozess.
     *
     * @param array  $state Prozessstatus
     * @param string $message Meldung
     *
     * @return array Fertig-Status
     */
    protected function proc_finish(array $state, string $message = 'finished'): array
    {
        $state['status']      = 'finished';
        $state['message']     = $message;
        $state['percent']     = 100;
        $state['step_percent'] = 100;
        $state['finished_at'] = date('Y-m-d H:i:s');
        return $this->proc_response($state);
    }

    /**
     * Liefert einen Step-Startzeitpunkt.
     *
     * @return float Startzeitpunkt
     */
    protected function step_start_time(): float
    {
        return microtime(true);
    }

    /**
     * Prüft, ob noch Laufzeit für den aktuellen Prozessschritt übrig ist.
     *
     * @param float $started_at Startzeitpunkt
     * @param float $max_seconds Maximale Laufzeit
     *
     * @return bool True wenn noch Zeit übrig
     */
    protected function step_time_left(float $started_at, float $max_seconds): bool
    {
        return (microtime(true) - $started_at) < $max_seconds;
    }


    /* =====================================================
     * SCHEMA MAPPING
     * ===================================================== */

    /**
     * Normalisiert einen Feldnamen fuer robuste Auto-Zuordnung.
     *
     * @param string $name Feldname
     *
     * @return string Normalisierter Vergleichsschluessel
     */
    protected function normalize_schema_field_key(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($name)));
    }

    /**
     * Baut einen sicheren Dateinamenbestandteil.
     *
     * @param string $value Rohwert
     *
     * @return string Sicherer Dateinamenbestandteil
     */
    protected function schema_mapping_file_part(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim($value));
        $value = trim((string)$value, '._-');

        return $value !== '' ? $value : 'default';
    }

    /**
     * Liefert das Verzeichnis fuer gespeicherte Schema-Mappings.
     *
     * @return string Absoluter Pfad
     */
    protected function schema_mapping_dir(): string
    {
        $dir = dbx()->os_path(dbx()->get_file_dir() . 'db/schema-mapping/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /**
     * Liefert den Pfad zur Mapping-Datei.
     *
     * @param string $kind Mapping-Art
     * @param array  $context Kontextdaten
     *
     * @return string Absoluter Dateipfad
     */
    public function schema_mapping_path(string $kind, array $context): string
    {
        $kind = strtolower(trim($kind));

        $parts = [
            $kind ?: 'schema',
            $context['modul'] ?? '',
            $context['dd'] ?? '',
            $context['source_server'] ?? ($context['server'] ?? ''),
            $context['source_table'] ?? ($context['table'] ?? ''),
            $context['target_server'] ?? '',
            $context['target_table'] ?? '',
        ];

        $parts = array_map(fn($v) => $this->schema_mapping_file_part((string)$v), $parts);

        return $this->schema_mapping_dir() . implode('__', $parts) . '.json';
    }

    /**
     * Baut einen Feldindex nach echtem Feldnamen.
     *
     * @param array $fields Feldliste
     *
     * @return array Feldindex
     */
    protected function schema_fields_by_name(array $fields): array
    {
        $index = [];

        foreach ($fields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name !== '') {
                $index[$name] = $field;
            }
        }

        return $this->sort_schema_record($index, $this->dd_index_schema_keys());
    }

    protected function sort_schema_record(array $record, array $keys): array
    {
        $sorted = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $record)) {
                $sorted[$key] = $record[$key];
                unset($record[$key]);
            }
        }

        foreach ($record as $key => $value) {
            $sorted[$key] = $value;
        }

        return $sorted;
    }

    /**
     * Normalisiert eine technische Source->Target-Zuordnung.
     *
     * @param array $mapping      Source->Target Mapping
     * @param array $sourceFields Quellfelder
     * @param array $targetFields Zielfelder
     *
     * @return array Valides Source->Target Mapping
     */
    public function normalize_schema_mapping(array $mapping, array $sourceFields, array $targetFields): array
    {
        $sourceByLower = [];
        foreach ($sourceFields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name !== '') {
                $sourceByLower[strtolower($name)] = $name;
            }
        }

        $targetByLower = [];
        foreach ($targetFields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name !== '') {
                $targetByLower[strtolower($name)] = $name;
            }
        }

        $clean = [];
        foreach ($mapping as $source => $target) {
            $sourceKey = strtolower(trim((string)$source));
            $targetKey = strtolower(trim((string)$target));

            if ($sourceKey === '' || $targetKey === '') {
                continue;
            }

            if (!isset($sourceByLower[$sourceKey]) || !isset($targetByLower[$targetKey])) {
                continue;
            }

            $sourceName = $sourceByLower[$sourceKey];
            $targetName = $targetByLower[$targetKey];

            foreach ($clean as $oldSource => $oldTarget) {
                if (strcasecmp($oldTarget, $targetName) === 0) {
                    unset($clean[$oldSource]);
                }
            }

            $clean[$sourceName] = $targetName;
        }

        ksort($clean);
        return $clean;
    }

    /**
     * Baut ein Auto-Mapping und uebersteuert es mit gespeicherter Zuordnung.
     *
     * @param array $sourceFields Quellfelder
     * @param array $targetFields Zielfelder
     * @param array $stored       Gespeichertes Mapping
     *
     * @return array Source->Target Mapping
     */
    protected function auto_schema_mapping(array $sourceFields, array $targetFields, array $stored = []): array
    {
        $sourceExact = [];
        $sourceNorm  = [];

        foreach ($sourceFields as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $sourceExact[strtolower($name)] = $name;
            $norm = $this->normalize_schema_field_key($name);
            if ($norm !== '' && !isset($sourceNorm[$norm])) {
                $sourceNorm[$norm] = $name;
            }
        }

        $mapping = [];
        $usedSource = [];

        foreach ($targetFields as $field) {
            $target = (string)($field['name'] ?? '');
            if ($target === '') {
                continue;
            }

            $source = $sourceExact[strtolower($target)] ?? '';
            if ($source !== '') {
                $mapping[$source] = $target;
                $usedSource[strtolower($source)] = true;
            }
        }

        foreach ($targetFields as $field) {
            $target = (string)($field['name'] ?? '');
            if ($target === '') {
                continue;
            }

            $alreadyMapped = false;
            foreach ($mapping as $mappedTarget) {
                if (strcasecmp($mappedTarget, $target) === 0) {
                    $alreadyMapped = true;
                    break;
                }
            }

            if ($alreadyMapped) {
                continue;
            }

            $norm = $this->normalize_schema_field_key($target);
            $source = $sourceNorm[$norm] ?? '';
            if ($source !== '' && empty($usedSource[strtolower($source)])) {
                $mapping[$source] = $target;
                $usedSource[strtolower($source)] = true;
            }
        }

        $stored = $this->normalize_schema_mapping($stored, $sourceFields, $targetFields);
        foreach ($stored as $source => $target) {
            foreach ($mapping as $oldSource => $oldTarget) {
                if (strcasecmp($oldSource, $source) === 0 || strcasecmp($oldTarget, $target) === 0) {
                    unset($mapping[$oldSource]);
                }
            }

            $mapping[$source] = $target;
        }

        ksort($mapping);
        return $mapping;
    }

    /**
     * Laedt ein gespeichertes Schema-Mapping.
     *
     * @param string $kind    Mapping-Art
     * @param array  $context Kontextdaten
     *
     * @return array Mapping-Dateiinhalt
     */
    public function load_schema_mapping(string $kind, array $context): array
    {
        $file = $this->schema_mapping_path($kind, $context);
        if (!is_file($file)) {
            return [
                'kind'       => strtolower(trim($kind)),
                'context'    => $context,
                'mapping'    => [],
                'file'       => $file,
                'updated_at' => '',
            ];
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            $data = [];
        }

        $data['kind']    = $data['kind'] ?? strtolower(trim($kind));
        $data['context'] = is_array($data['context'] ?? null) ? $data['context'] : $context;
        $data['mapping'] = is_array($data['mapping'] ?? null) ? $data['mapping'] : [];
        $data['file']    = $file;

        return $data;
    }

    /**
     * Gibt nur die gespeicherten Mapping-Werte zurueck.
     *
     * @param string $kind    Mapping-Art
     * @param array  $context Kontextdaten
     *
     * @return array Source->Target Mapping
     */
    public function get_schema_mapping_values(string $kind, array $context): array
    {
        $data = $this->load_schema_mapping($kind, $context);

        return is_array($data['mapping'] ?? null) ? $data['mapping'] : [];
    }

    /**
     * Baut die Mapping-Ansicht fuer Admin-UI und Prozesse.
     *
     * @param string $kind    dd_to_db|db_to_dd|transfer
     * @param array  $context Kontextdaten
     *
     * @return array Mapping-Modell
     */
    public function build_schema_mapping(string $kind, array $context): array
    {
        $kind = strtolower(trim($kind));
        if ($kind === '') {
            $kind = 'dd_to_db';
        }

        $modul = (string)($context['modul'] ?? '');
        $dd    = (string)($context['dd'] ?? '');
        $server = (string)($context['server'] ?? ($context['source_server'] ?? ''));
        $table  = (string)($context['table'] ?? ($context['source_table'] ?? ''));

        $sourceFields = [];
        $targetFields = [];
        $sourceLabel  = '';
        $targetLabel  = '';
        $ddRef        = ($modul && $dd && strpos($dd, '|') === false) ? $modul . '|' . $dd : $dd;

        if ($kind === 'db_to_dd') {
            if ($server && $table && $this->get_table_exist($server, $table)) {
                $sourceFields = $this->get_db_fields($server, $table);
            }

            $oldModel = [];
            if ($ddRef && ($this->get_dd_exist($ddRef) || $this->get_dd_exist($dd))) {
                $oldModel = $this->get_dd_model($ddRef);
                if (!$oldModel) {
                    $oldModel = $this->get_dd_model($dd);
                }
            }

            $targetFields = $oldModel['fields'] ?? $sourceFields;
            $sourceLabel  = $server . '|' . $table;
            $targetLabel  = ($modul ? $modul . '|' : '') . $dd;
        } elseif ($kind === 'transfer') {
            $sourceServer = (string)($context['source_server'] ?? $server);
            $sourceTable  = (string)($context['source_table'] ?? $table);
            $targetServer = (string)($context['target_server'] ?? '');
            $targetTable  = (string)($context['target_table'] ?? '');

            if ($sourceServer && $sourceTable && $this->get_table_exist($sourceServer, $sourceTable)) {
                $schema = $this->get_preferred_table_schema($sourceServer, $sourceTable);
                $sourceFields = $schema['fields'] ?? [];
            }
            if ($targetServer && $targetTable && $this->get_table_exist($targetServer, $targetTable)) {
                $targetFields = $this->get_db_fields($targetServer, $targetTable);
            } else {
                $targetFields = $sourceFields;
            }

            $sourceLabel = $sourceServer . '|' . $sourceTable;
            $targetLabel = $targetServer . '|' . $targetTable;
        } else {
            $kind = 'dd_to_db';
            $model = $ddRef ? $this->get_dd_model($ddRef) : [];
            if (!$model && $dd) {
                $model = $this->get_dd_model($dd);
            }

            $server = $server ?: (string)($model['table']['server'] ?? '');
            $table  = $table  ?: (string)($model['table']['table']  ?? '');

            if ($server && $table && $this->get_table_exist($server, $table)) {
                $sourceFields = $this->get_db_fields($server, $table);
            }

            $targetFields = $model['fields'] ?? [];
            $sourceLabel  = $server . '|' . $table;
            $targetLabel  = ($modul ? $modul . '|' : '') . $dd;
        }

        $context['modul'] = $modul;
        $context['dd'] = $dd;
        $context['server'] = $server;
        $context['table'] = $table;

        $storedData = $this->load_schema_mapping($kind, $context);
        $stored = is_array($storedData['mapping'] ?? null) ? $storedData['mapping'] : [];
        $mapping = $this->auto_schema_mapping($sourceFields, $targetFields, $stored);

        $sourceByName = $this->schema_fields_by_name($sourceFields);
        $usedSources  = [];
        $targetRows   = [];

        foreach ($targetFields as $target) {
            $targetName = (string)($target['name'] ?? '');
            if ($targetName === '') {
                continue;
            }

            $sourceName = '';
            foreach ($mapping as $source => $mappedTarget) {
                if (strcasecmp($mappedTarget, $targetName) === 0) {
                    $sourceName = $source;
                    $usedSources[strtolower($source)] = true;
                    break;
                }
            }

            $source = $sourceName !== '' && isset($sourceByName[$sourceName]) ? $sourceByName[$sourceName] : [];
            $status = 'new';
            if ($sourceName !== '') {
                $status = (strcasecmp($sourceName, $targetName) === 0) ? 'exact' : 'mapped';

                if ($source && $target && !$this->is_dd_field_compatible_with_db($target, $source, $server ? $this->get_db_type($server) : '')) {
                    $status = 'type_conflict';
                }
            }

            $targetRows[] = [
                'target' => $target,
                'source' => $source,
                'source_name' => $sourceName,
                'target_name' => $targetName,
                'status' => $status,
            ];
        }

        $unmappedSources = [];
        foreach ($sourceFields as $source) {
            $name = (string)($source['name'] ?? '');
            if ($name !== '' && empty($usedSources[strtolower($name)])) {
                $unmappedSources[] = $source;
            }
        }

        return [
            'kind'             => $kind,
            'context'          => $context,
            'source_label'     => $sourceLabel,
            'target_label'     => $targetLabel,
            'source_fields'    => $sourceFields,
            'target_fields'    => $targetFields,
            'target_rows'      => $targetRows,
            'unmapped_sources' => $unmappedSources,
            'mapping'          => $mapping,
            'stored_mapping'   => $stored,
            'file'             => $storedData['file'] ?? $this->schema_mapping_path($kind, $context),
            'updated_at'       => $storedData['updated_at'] ?? '',
        ];
    }

    /**
     * Speichert ein Schema-Mapping als wiederverwendbare Mapping-Datei.
     *
     * @param string $kind    Mapping-Art
     * @param array  $context Kontextdaten
     * @param array  $mapping Source->Target Mapping
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function save_schema_mapping(string $kind, array $context, array $mapping): int
    {
        $model = $this->build_schema_mapping($kind, $context);
        $clean = $this->normalize_schema_mapping($mapping, $model['source_fields'] ?? [], $model['target_fields'] ?? []);

        $data = [
            'kind'       => $model['kind'] ?? strtolower(trim($kind)),
            'context'    => $model['context'] ?? $context,
            'source'     => $model['source_label'] ?? '',
            'target'     => $model['target_label'] ?? '',
            'mapping'    => $clean,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $file = $model['file'] ?? $this->schema_mapping_path($kind, $context);
        $ok = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $ok === false ? 0 : 1;
    }


    /* =====================================================
     * CREATE DD
     * ===================================================== */

    /**
     * Erzeugt eine DD aus einer vorhandenen DB-Tabelle.
     *
     * Hinweis:
     * - dbxDD liefert hier nur die technische Infrastruktur
     * - spätere Admin-/Mapping-Logik kann dieses Ergebnis gezielt anpassen
     *
     * @param string $modul Zielmodul
     * @param string $dd DD-Name
     * @param string $server Servername
     * @param string $table Tabellenname
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    public function create_dd(string $modul, string $dd, string $server = 'dbXsystem', string $table = ''): int
    {
        if (!$table) {
            $table = $dd;
        }

        if (!$this->get_table_exist($server, $table)) {
            dbx()->sys_msg('error', 'dd', $dd, 'create_dd', 'table not found');
            return 0;
        }

        $tableMeta = $this->normalize_table_record($dd, [
            'server' => $server,
            'table'  => $table,
        ]);

        $fields  = $this->get_db_fields($server, $table);
        $indexes = $this->get_db_indexes($server, $table);

        return $this->save_dd($modul, $dd, $tableMeta, $fields, $indexes);
    }


    /* =====================================================
     * BACKUP
     * ===================================================== */

    /**
     * Baut einen Backup-Dateinamen.
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     * @param bool   $zip Zip-Flag
     *
     * @return string Absoluter Backup-Dateiname
     */
    protected function build_backup_file_name(string $server, string $table, bool $zip = false): string
    {
        $dir = dbx()->os_path(dbx()->get_file_dir() . 'db/dd-backup/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $name = $table . '_' . date('Ymd_His') . '.ddb';
        if ($zip) {
            $name .= '.zip';
        }

        return $dir . $name;
    }

    /**
     * Finalisiert eine Backup-Datei optional als ZIP.
     *
     * @param string $tmpFile Temporäre Datei
     * @param string $finalFile Zieldatei
     * @param bool   $zip Zip-Flag
     *
     * @return int 1 bei Erfolg, sonst 0
     */
    protected function finalize_backup_file(string $tmpFile, string $finalFile, bool $zip): int
    {
        if (!$zip) {
            @unlink($finalFile);
            return @rename($tmpFile, $finalFile) ? 1 : 0;
        }

        if (!class_exists('ZipArchive')) {
            $raw = preg_replace('/\.zip$/i', '', $finalFile);
            @unlink($raw);
            return @rename($tmpFile, $raw) ? 1 : 0;
        }

        $zipObj = new ZipArchive();
        if ($zipObj->open($finalFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return 0;
        }

        $inner = basename(preg_replace('/\.zip$/i', '', $finalFile));
        $zipObj->addFile($tmpFile, $inner);
        $zipObj->close();

        @unlink($tmpFile);
        return 1;
    }

    /**
     * Erstellt ein tabellenbasiertes Datenbackup.
     *
     * Format:
     * - kompakter JSON-Zeilenexport
     * - erste Zeilen enthalten Metadaten und Spaltenliste
     * - danach Records chunkweise
     *
     * Hinweis:
     * - bewusst kein SQL-INSERT-pro-Zeile-Backup
     * - für große Tabellen stepweise ausführbar
     *
     * @param string $server Servername
     * @param string $table Tabellenname
     * @param string $file Zieldatei
     * @param int    $zip Zip-Flag 0|1
     *
     * @return array Prozessstatus
     */
    public function backup($server, $table, $file = '', $zip = 0): array
    {
        $key   = $this->proc_key('backup', [$server, $table, $file, $zip]);
        $state = $this->get_proc_state($key);

        if ($state && $this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        if (!$state) {
            if (!$server || !$table) {
                return $this->proc_error(['proc_key' => $key], 'server or table missing');
            }

            if (!$this->get_table_exist($server, $table)) {
                return $this->proc_error(['proc_key' => $key], 'table not found');
            }

            $fields = $this->get_db_fields($server, $table);
            if (!$fields) {
                return $this->proc_error(['proc_key' => $key], 'no fields found');
            }

            $columns = [];
            $fieldTypes = [];
            $pk      = '';
            foreach ($fields as $f) {
                $columns[] = $f['name'];
                $fieldTypes[$f['name']] = strtolower((string)($f['type'] ?? ''));
                if (strtoupper((string)($f['index'] ?? '')) === 'PRI' && !$pk) {
                    $pk = $f['name'];
                }
            }

            $total = $this->count($table, '', $server);
            if ($total < 0) {
                return $this->proc_error(['proc_key' => $key], 'count failed');
            }

            if (!$file) {
                $file = $this->build_backup_file_name($server, $table, (bool)$zip);
            }

            $tmpFile = $file . '.part';
            @unlink($tmpFile);

            $meta = [
                'server'      => $server,
                'table'       => $table,
                'db_type'     => $this->get_db_type($server),
                'backup_date' => date('Y-m-d H:i:s'),
                'row_count'   => $total,
                'compact'     => 1,
                'zip'         => $zip ? 1 : 0,
            ];

            file_put_contents($tmpFile, json_encode(['meta' => $meta], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            file_put_contents($tmpFile, json_encode([
                'columns' => $columns,
                'field_types' => $fieldTypes,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);

            $state = $this->init_proc_state('backup', $key, [
                'server'   => $server,
                'table'    => $table,
                'file'     => $file,
                'tmp_file' => $tmpFile,
                'zip'      => $zip ? 1 : 0,
                'columns'  => $columns,
                'pk'       => $pk,
                'offset'   => 0,
                'done'     => 0,
                'total'    => $total,
                'message'  => 'backup initialized',
            ]);
        }

        $started = $this->step_start_time();

        while ($this->step_time_left($started, (float)$state['step_maxsec'])) {
            if ((int)$state['done'] >= (int)$state['total']) {
                $ok = $this->finalize_backup_file($state['tmp_file'], $state['file'], !empty($state['zip']));
                if (!$ok) {
                    $state = $this->proc_error($state, 'finalize backup failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                $state = $this->proc_finish($state, 'backup finished');
                $this->set_proc_state($key, $state);
                return $state;
            }

            $chunk = (int)$state['chunk_size'];
            $cols  = [];
            foreach ($state['columns'] as $col) {
                $cols[] = $this->quote_ident($state['server'], $col);
            }

            $order = '';
            if (!empty($state['pk'])) {
                $order = ' ORDER BY ' . $this->quote_ident($state['server'], $state['pk']);
            }

            $dbType = $this->get_db_type($state['server']);
            if ($dbType === 'mysql') {
                $limit = ' LIMIT ' . (int)$state['offset'] . ', ' . $chunk;
            } else {
                $limit = ' LIMIT ' . $chunk . ' OFFSET ' . (int)$state['offset'];
            }

            $sql  = 'SELECT ' . implode(',', $cols);
            $sql .= ' FROM ' . $this->quote_ident($state['server'], $state['table']);
            $sql .= $order . $limit;

            $rows = $this->rawQuery($state['server'], $sql);
            if (!is_array($rows)) {
                $state = $this->proc_error($state, 'backup select failed');
                $this->set_proc_state($key, $state);
                return $state;
            }

            if (!$rows) {
                $state['done']    = $state['total'];
                $state['percent'] = 100;
                $state['step_percent'] = 100;
                continue;
            }

            $records = [];
            foreach ($rows as $row) {
                $rec = [];
                foreach ($state['columns'] as $col) {
                    $rec[] = $row[$col] ?? null;
                }
                $records[] = $rec;
            }

            file_put_contents(
                $state['tmp_file'],
                json_encode(['records' => $records], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND
            );

            $count = count($rows);
            $state['offset']  += $count;
            $state['done']    += $count;
            $state['percent']  = ($state['total'] > 0) ? (int)floor(($state['done'] / $state['total']) * 100) : 100;
            $state['step_percent'] = $state['percent'];
            $state['message']  = 'backup rows ' . $state['done'] . ' / ' . $state['total'];
        }

        $this->set_proc_state($key, $state);
        return $this->proc_response($state);
    }


    /* =====================================================
     * RESTORE
     * ===================================================== */

    /**
     * Bereitet eine Restore-Quelldatei vor.
     *
     * @param string $file Quelldatei
     * @param bool   $zip Zip-Flag
     *
     * @return string Lesbarer Quelldateipfad oder leer
     */
    protected function prepare_restore_source_file(string $file, bool $zip): string
    {
        if (!$zip) {
            return $file;
        }

        if (!class_exists('ZipArchive')) {
            $raw = preg_replace('/\.zip$/i', '', $file);
            return file_exists($raw) ? $raw : '';
        }

        $tmpDir = dbx()->os_path(dbx()->get_file_dir() . 'temp/dd-restore/');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0777, true);
        }

        $tmpFile = $tmpDir . md5($file) . '.ddb';

        if (file_exists($tmpFile)) {
            return $tmpFile;
        }

        $zipObj = new ZipArchive();
        if ($zipObj->open($file) !== true) {
            return '';
        }

        if ($zipObj->numFiles < 1) {
            $zipObj->close();
            return '';
        }

        $content = $zipObj->getFromIndex(0);
        $zipObj->close();

        if ($content === false) {
            return '';
        }

        file_put_contents($tmpFile, $content);
        return $tmpFile;
    }

    /**
     * Stellt Daten aus einem kompakten Tabellenbackup wieder her.
     *
     * Mapping
     * -------
     * - Auto-Zuordnung erfolgt über identische Feldnamen
     * - zusätzlich kann ein externes Mapping übergeben werden
     * - dieses Mapping ist technische Infrastruktur, nicht Adminlogik
     *
     * Wichtiger Punkt
     * ---------------
     * Restore arbeitet tabellenbezogen und technisch.
     * Es nutzt bewusst keine DD-Workflowlogik wie Access/Trace/Validator.
     *
     * @param string $server Zielserver
     * @param string $table Zieltabelle
     * @param string $file Quelldatei
     * @param array  $mapping Optionales technisches Feldmapping
     * @param int    $zip Zip-Flag 0|1
     *
     * @return array Prozessstatus
     */
    public function restore($server, $table, $file, $mapping = [], $zip = 0): array
    {
        $key   = $this->proc_key('restore', [$server, $table, $file, $mapping, $zip]);
        $state = $this->get_proc_state($key);

        if ($state && $this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        if (!$state) {
            if (!$server || !$table || !$file) {
                return $this->proc_error(['proc_key' => $key], 'server, table or file missing');
            }

            if (!$this->get_table_exist($server, $table)) {
                return $this->proc_error(['proc_key' => $key], 'target table not found');
            }

            $sourceFile = $this->prepare_restore_source_file($file, (bool)$zip);
            if (!$sourceFile || !file_exists($sourceFile)) {
                return $this->proc_error(['proc_key' => $key], 'restore source file not readable');
            }

            $fh = fopen($sourceFile, 'rb');
            if (!$fh) {
                return $this->proc_error(['proc_key' => $key], 'restore open source failed');
            }

            $line1 = fgets($fh);
            $line2 = fgets($fh);

            $metaRec = json_decode((string)$line1, true);
            $colRec  = json_decode((string)$line2, true);

            if (!is_array($metaRec) || !isset($metaRec['meta']) || !is_array($colRec) || !isset($colRec['columns'])) {
                fclose($fh);
                return $this->proc_error(['proc_key' => $key], 'invalid backup format');
            }

            $filePos = ftell($fh);
            fclose($fh);

            $targetFields = $this->get_db_fields($server, $table);
            $targetLookup = [];
            $targetTypes = [];
            foreach ($targetFields as $f) {
                $targetLookup[$f['name']] = true;
                $targetTypes[$f['name']] = strtolower((string)($f['type'] ?? ''));
            }

            if (!$this->empty_db_table($server, $table)) {
                return $this->proc_error(['proc_key' => $key], 'target table not found after empty');
            }

            $state = $this->init_proc_state('restore', $key, [
                'server'         => $server,
                'table'          => $table,
                'file'           => $file,
                'source_file'    => $sourceFile,
                'zip'            => $zip ? 1 : 0,
                'mapping'        => is_array($mapping) ? $mapping : [],
                'source_columns' => $colRec['columns'],
                'source_types'   => is_array($colRec['field_types'] ?? null)
                    ? $colRec['field_types']
                    : [],
                'target_lookup'  => $targetLookup,
                'target_types'   => $targetTypes,
                'target_db_type' => $this->get_db_type($server),
                'file_pos'       => $filePos,
                'done'           => 0,
                'total'          => (int)($metaRec['meta']['row_count'] ?? 0),
                'message'        => 'restore initialized',
            ]);
        }

        if (!$this->connect_db_server($state['server'])) {
            $state = $this->proc_error($state, 'db connect failed');
            $this->set_proc_state($key, $state);
            return $state;
        }

        $started = $this->step_start_time();

        $fh = fopen($state['source_file'], 'rb');
        if (!$fh) {
            $state = $this->proc_error($state, 'restore open source failed');
            $this->set_proc_state($key, $state);
            return $state;
        }

        fseek($fh, (int)$state['file_pos']);

        while ($this->step_time_left($started, (float)$state['step_maxsec'])) {
            $line = fgets($fh);

            if ($line === false) {
                fclose($fh);

                if (!empty($state['zip']) && isset($state['source_file']) && $state['source_file'] !== $state['file']) {
                    @unlink($state['source_file']);
                }

                $state = $this->proc_finish($state, 'restore finished');
                $this->set_proc_state($key, $state);
                return $state;
            }

            $rec = json_decode(trim($line), true);
            if (!is_array($rec) || !isset($rec['records']) || !is_array($rec['records'])) {
                continue;
            }

            foreach ($rec['records'] as $rowValues) {
                $assoc = [];
                foreach ($state['source_columns'] as $i => $col) {
                    $assoc[$col] = $rowValues[$i] ?? null;
                }

                if (is_array($state['mapping'])) {
                    foreach ($state['mapping'] as $old => $new) {
                        if ((string)$old === (string)$new) {
                            continue;
                        }

                        if (array_key_exists($old, $assoc)) {
                            $assoc[$new] = $assoc[$old];
                            unset($assoc[$old]);
                        }
                    }
                }

                $dbRec = [];
                foreach ($assoc as $fld => $val) {
                    if (isset($state['target_lookup'][$fld])) {
                        $dbRec[$fld] = $this->normalize_restore_value(
                            (string)($state['target_db_type'] ?? ''),
                            (string)($state['target_types'][$fld] ?? ''),
                            $val,
                            (string)($state['source_types'][$fld] ?? '')
                        );
                    }
                }

                if (isset($dbRec['id']) && ($dbRec['id'] === '' || $dbRec['id'] === null || $dbRec['id'] === 0 || $dbRec['id'] === '0')) {
                    unset($dbRec['id']);
                }

                if (!$dbRec) {
                    continue;
                }

                $fields = array_keys($dbRec);
                $placeholders = array_fill(0, count($fields), '?');

                $sql = 'INSERT INTO ' . $this->quote_ident($state['server'], $state['table'])
                     . ' (' . implode(',', array_map(fn($f) => $this->quote_ident($state['server'], $f), $fields)) . ')'
                     . ' VALUES (' . implode(',', $placeholders) . ')';

                $stmt = $this->db[$state['server']]->prepare($sql);
                $stmt->execute(array_values($dbRec));

                $state['done']++;
            }

            $state['file_pos'] = ftell($fh);
            $state['percent']  = ($state['total'] > 0) ? (int)floor(($state['done'] / $state['total']) * 100) : 0;
            $state['step_percent'] = $state['percent'];
            $state['message']  = 'restore rows ' . $state['done'] . ' / ' . $state['total'];
        }

        fclose($fh);
        $this->set_proc_state($key, $state);
        return $this->proc_response($state);
    }

    /**
     * Normalisiert treiberspezifisch problematische Restore-Werte.
     *
     * MySQL/MariaDB interpretiert einen leeren String in DATE-/TIME-Spalten
     * je nach SQL-Modus als Zero-Date oder Fehler. Fachlich leere Zeitwerte
     * werden deshalb als NULL gespeichert. Andere Werte bleiben bytegetreu;
     * insbesondere werden gueltige Mikrosekunden nicht beschnitten.
     */
    protected function normalize_restore_value(
        string $targetDbType,
        string $targetFieldType,
        mixed $value,
        string $sourceFieldType = ''
    ): mixed {
        $targetDbType = strtolower(trim($targetDbType));
        $targetFieldType = strtolower(trim($targetFieldType));
        $sourceFieldType = strtolower(trim($sourceFieldType));
        $temporalTypes = ['date', 'time', 'datetime', 'timestamp', 'year'];
        $effectiveFieldType = $targetFieldType;

        // SQLite speichert Zeittypen technisch als TEXT. Beim Ruecktransfer
        // liefert deshalb der Quelltyp die noetige fachliche Information.
        if ($targetDbType === 'sqlite'
            && in_array($targetFieldType, ['', 'text', 'varchar'], true)
            && in_array($sourceFieldType, $temporalTypes, true)
        ) {
            $effectiveFieldType = $sourceFieldType;
        }

        if ($targetDbType === 'mysql'
            && $value === ''
            && in_array($effectiveFieldType, $temporalTypes, true)
        ) {
            return null;
        }

        if ($targetDbType === 'sqlite'
            && is_string($value)
            && in_array($effectiveFieldType, $temporalTypes, true)
            && preg_match('/^(.+)\.([0-9]{1,6})$/', $value, $match)
        ) {
            $fraction = rtrim($match[2], '0');
            if ($fraction !== '' && strlen($fraction) < 3) {
                $fraction = str_pad($fraction, 3, '0');
            }
            return $match[1] . ($fraction !== '' ? '.' . $fraction : '');
        }

        return $value;
    }


    /* =====================================================
     * DD -> DB SYNC
     * ===================================================== */

    /**
     * Prüft, ob zwei Typen semantisch kompatibel sind.
     *
     * Wichtig:
     * - dient nur als technische Infrastruktur
     * - fachliche Endentscheidung kann später durch Mapping/Admin kommen
     *
     * @param string $ddType DD-Typ
     * @param string $dbType DB-/DD-Typ
     *
     * @return bool True wenn semantisch kompatibel
     */
    protected function is_semantic_type_match(string $ddType, string $dbType): bool
    {
        $ddGroup = $this->schema_type_group($ddType);
        $dbGroup = $this->schema_type_group($dbType);

        if ($ddGroup === $dbGroup) {
            return true;
        }

        if (in_array($ddGroup, ['string', 'text'], true) && in_array($dbGroup, ['string', 'text'], true)) {
            return true;
        }

        if (in_array($ddGroup, ['integer', 'bool'], true) && in_array($dbGroup, ['integer', 'bool'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Gruppiert DD-/DB-Feldtypen fachlich.
     *
     * @param string $type Typname
     * @param string $length Optionale Länge
     *
     * @return string Typgruppe
     */
    protected function schema_type_group(string $type, string $length = ''): string
    {
        $type = strtolower(trim($type));
        $length = trim($length);

        if ($type === '') {
            return 'unknown';
        }

        if (in_array($type, ['bool', 'boolean'], true)) {
            return 'bool';
        }

        if ($type === 'bit') {
            return ($length === '' || $length === '1') ? 'bool' : 'integer';
        }

        if ($type === 'tinyint' && $length === '1') {
            return 'bool';
        }

        if (in_array($type, ['int', 'integer', 'smallint', 'mediumint', 'tinyint', 'bigint', 'serial', 'number'], true)) {
            return 'integer';
        }

        if (in_array($type, ['decimal', 'numeric'], true)) {
            return 'decimal';
        }

        if (in_array($type, ['float', 'double', 'real'], true)) {
            return 'float';
        }

        if ($type === 'date' || $type === 'year') {
            return 'date';
        }

        if ($type === 'time') {
            return 'time';
        }

        if (in_array($type, ['datetime', 'timestamp'], true)) {
            return 'datetime';
        }

        if (in_array($type, ['char', 'nchar', 'varchar', 'varchar2', 'nvarchar', 'string'], true)) {
            return 'string';
        }

        if (in_array($type, ['text', 'tinytext', 'mediumtext', 'longtext', 'clob'], true)) {
            return 'text';
        }

        if (in_array($type, ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'], true)) {
            return 'binary';
        }

        if ($type === 'json') {
            return 'json';
        }

        if (in_array($type, ['enum', 'set'], true)) {
            return 'enum';
        }

        return 'unknown';
    }

    /**
     * Prüft, ob ein DD-Feld fachlich zu einem DB-Feld passt.
     *
     * Diese Prüfung ist bewusst tolerant, weil sie für DB -> DD und Mapping
     * verwendet wird. Die strenge physische DB-Sync-Prüfung erfolgt separat.
     *
     * @param array  $ddField DD-Feld
     * @param array  $dbField DB-Feld in DD-kompatibler Form
     * @param string $dbEngine Datenbanktyp der physischen Tabelle
     *
     * @return bool True wenn kompatibel
     */
    public function is_dd_field_compatible_with_db(array $ddField, array $dbField, string $dbEngine = ''): bool
    {
        $dbEngine = strtolower(trim($dbEngine));
        $ddType = strtolower((string)($ddField['type'] ?? ''));
        $dbType = strtolower((string)($dbField['type'] ?? ''));
        $ddGroup = $this->schema_type_group($ddType, (string)($ddField['length'] ?? ''));
        $dbGroup = $this->schema_type_group($dbType, (string)($dbField['length'] ?? ''));

        if ($ddType !== '' && $dbType !== '' && ($ddType === $dbType || $this->is_semantic_type_match($ddType, $dbType))) {
            return true;
        }

        if ($dbEngine === 'sqlite') {
            if (in_array($ddGroup, ['string', 'text', 'date', 'time', 'datetime', 'json', 'enum'], true)
                && in_array($dbGroup, ['string', 'text'], true)) {
                return true;
            }

            if (in_array($ddGroup, ['integer', 'bool'], true) && in_array($dbGroup, ['integer', 'bool'], true)) {
                return true;
            }

            if (in_array($ddGroup, ['decimal', 'float'], true) && in_array($dbGroup, ['decimal', 'float', 'integer'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft, ob ein DB-Feld physisch synchron zu einem DD-Feld ist.
     *
     * @param string $dbEngine Datenbanktyp
     * @param array  $ddField DD-Feld
     * @param array  $dbField DB-Feld in DD-kompatibler Form
     *
     * @return bool True wenn physisch synchron
     */
    protected function is_db_sync_field_match(string $dbEngine, array $ddField, array $dbField): bool
    {
        $dbEngine = strtolower(trim($dbEngine));
        $ddType = strtolower((string)($ddField['type'] ?? ''));
        $dbType = strtolower((string)($dbField['type'] ?? ''));

        if ($ddType === '' || $dbType === '') {
            return false;
        }

        if ($dbEngine === 'sqlite') {
            return $this->is_dd_field_compatible_with_db($ddField, $dbField, $dbEngine);
        }

        if ($ddType === $dbType) {
            return true;
        }

        $ddGroup = $this->schema_type_group($ddType, (string)($ddField['length'] ?? ''));
        $dbGroup = $this->schema_type_group($dbType, (string)($dbField['length'] ?? ''));

        if (in_array($ddGroup, ['integer', 'bool'], true) && in_array($dbGroup, ['integer', 'bool'], true)) {
            return true;
        }

        if ($ddGroup === 'decimal' && $dbGroup === 'decimal') {
            return true;
        }

        if ($ddGroup === 'float' && in_array($dbGroup, ['float', 'decimal'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Fuehrt ein vorhandenes DD-Feld mit einer DB-Felddefinition zusammen.
     *
     * @param array  $oldField Vorhandenes DD-Feld
     * @param array  $dbField DB-Feld
     * @param string $dbEngine Datenbanktyp
     *
     * @return array Gemergtes DD-Feld
     */
    public function merge_dd_field_with_db_field(array $oldField, array $dbField, string $dbEngine = ''): array
    {
        $compatible = $this->is_dd_field_compatible_with_db($oldField, $dbField, $dbEngine);
        $merged = $dbField;

        $preserve = $compatible
            ? ['type', 'index', 'length', 'default', 'label', 'rules', 'tooltip', 'errormsg', 'placeholder', 'convert', 'protect', 'group', 'mask', 'data', 'options', 'tpl', 'js']
            : ['label', 'tooltip', 'errormsg', 'placeholder', 'convert', 'protect', 'group', 'mask', 'data', 'js'];

        foreach ($preserve as $key) {
            if (isset($oldField[$key]) && $oldField[$key] !== '' && $oldField[$key] !== null) {
                $merged[$key] = $oldField[$key];
            }
        }

        $merged['name'] = $dbField['name'] ?? ($oldField['name'] ?? '');

        if (empty($merged['label'])) {
            $merged['label'] = $this->infer_label_from_name((string)$merged['name']);
        }

        if (empty($merged['rules']) || !$compatible) {
            $merged['rules'] = $this->infer_rules_from_field($merged);
        }

        if (empty($merged['tpl']) || !$compatible) {
            $merged['tpl'] = $this->infer_tpl_from_field($merged);
        }

        return $this->normalize_field_record($merged);
    }

    /**
     * Baut einen technischen Sync-Plan DD -> DB.
     *
     * Wichtige Regel:
     * - DD ist das Soll-Modell
     * - bei SQLite/db3 sollen textartige physische Typen nicht automatisch
     *   als Konflikt gegen `varchar`/`char` gewertet werden
     *
     * @param string $dd DD-Name
     *
     * @return array Technischer Sync-Plan
     */
    protected function build_sync_plan_dd_to_db(string $dd): array
    {
        $model = $this->get_dd_model($dd);

        $plan = [
            'ok'             => 1,
            'dd'             => $dd,
            'server'         => $model['table']['server'] ?? '',
            'table'          => $model['table']['table']  ?? '',
            'table_exists'   => 0,
            'create_table'   => 0,
            'add_fields'     => [],
            'missing_in_dd'  => [],
            'type_conflicts' => [],
            'add_indexes'    => [],
            'rebuild_needed' => 0,
            'backup_file'    => '',
        ];

        if (!$model) {
            $plan['ok'] = 0;
            return $plan;
        }

        $server    = $plan['server'];
        $table     = $plan['table'];
        $ddFields  = $model['fields']  ?? [];
        $ddIndexes = $model['indexes'] ?? [];

        if (!$server || !$table) {
            $plan['ok'] = 0;
            return $plan;
        }

        $tableExists = $this->get_table_exist($server, $table) ? 1 : 0;
        $plan['table_exists'] = $tableExists;

        if (!$tableExists) {
            $plan['create_table'] = 1;
            return $plan;
        }

        $dbFields  = $this->get_db_fields($server, $table);
        $dbIndexes = $this->get_db_indexes($server, $table);

        $dbFieldsByName = [];
        foreach ($dbFields as $field) {
            $dbFieldsByName[strtolower($field['name'])] = $field;
        }

        $ddFieldsByName = [];
        foreach ($ddFields as $field) {
            $ddFieldsByName[strtolower($field['name'])] = $field;
        }

        $targetDbType = $this->get_db_type($server);

        foreach ($ddFields as $field) {
            $name = strtolower((string)$field['name']);
            if (!isset($dbFieldsByName[$name])) {
                $plan['add_fields'][] = $field;
                continue;
            }

            $dbf = $dbFieldsByName[$name];

            $ddType = strtolower((string)($field['type'] ?? ''));
            $dbType = strtolower((string)($dbf['type'] ?? ''));

            $ddLen = (string)($field['length'] ?? '');
            $dbLen = (string)($dbf['length'] ?? '');

            $typeEqual = $this->is_db_sync_field_match($targetDbType, $field, $dbf);
            $lenEqual  = ($ddLen === $dbLen) || ($ddLen === '' || $dbLen === '');

            /**
             * SQLite/db3:
             * physisch oft TEXT/INTEGER/REAL ohne echte MySQL-Präzision.
             * Die DD-Definition bleibt kanonisch und ist hier das Soll.
             */
            if ($targetDbType === 'sqlite') {
                if ($typeEqual) {
                    $lenEqual  = true;
                }
            }

            if (!$typeEqual || !$lenEqual) {
                $plan['type_conflicts'][] = [
                    'field'      => $field['name'],
                    'dd_type'    => $ddType,
                    'db_type'    => $dbType,
                    'dd_length'  => $ddLen,
                    'db_length'  => $dbLen,
                ];
            }
        }

        foreach ($dbFields as $field) {
            $name = strtolower((string)$field['name']);
            if (!isset($ddFieldsByName[$name])) {
                $plan['missing_in_dd'][] = $field;
            }
        }

        $dbIndexNames = [];
        foreach ($dbIndexes as $idx) {
            $dbIndexNames[strtolower((string)$idx['name'])] = true;
        }

        foreach ($ddIndexes as $idx) {
            $name = strtolower((string)($idx['name'] ?? ''));
            if (!$name) {
                continue;
            }

            if (strtoupper((string)($idx['type'] ?? 'INDEX')) === 'PRIMARY') {
                continue;
            }

            if (!isset($dbIndexNames[$name])) {
                $plan['add_indexes'][] = $idx;
            }
        }

        $plan['rebuild_needed'] = (!empty($plan['type_conflicts']) || !empty($plan['missing_in_dd'])) ? 1 : 0;

        return $plan;
    }

    /**
     * Synchronisiert ein DD technisch in Richtung DB.
     *
     * Modus
     * -----
     * - `check` / `plan`
     * - `apply`
     * - `force` / `rebuild`
     * - `status`
     * - `reset`
     *
     * Hinweis:
     * - dies ist reine Infrastruktur
     * - spätere Admin-/Mappinglogik entscheidet fachlich genauer,
     *   wie Konflikte wirklich behandelt werden
     *
     * @param string $modul Modulname
     * @param string $dd DD-Name
     * @param string $mode Modus
     *
     * @return array Prozessstatus / Plan
     */
    public function sync_dd_to_db($modul, $dd, $mode = 'step'): array
    {
        $mode = strtolower((string)$mode);
        if ($mode === '') {
            $mode = 'step';
        }
        if ($mode === 'step') {
            $mode = 'apply';
        }

        $ddRef = ($modul && strpos((string)$dd, '|') === false) ? $modul . '|' . $dd : (string)$dd;
        $key = $this->proc_key('sync_dd_to_db', [$modul, $dd]);

        if ($mode === 'reset') {
            $this->clear_proc_state($key);
            return [
                'proc_key' => $key,
                'status'   => 'reset',
                'message'  => 'sync state cleared',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if ($mode === 'status') {
            $state = $this->get_proc_state($key);
            return $state ?: [
                'proc_key' => $key,
                'status'   => 'new',
                'message'  => 'no active state',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if (in_array($mode, ['pause', 'resume', 'continue', 'cancel'], true)) {
            return $this->control_proc_state($key, $mode);
        }

        if ($mode === 'restart') {
            $this->clear_proc_state($key);
            $mode = 'apply';
        }

        $plan = $this->build_sync_plan_dd_to_db($ddRef);

        if (in_array($mode, ['check', 'plan'], true)) {
            $plan['status']  = 'finished';
            $plan['message'] = 'plan ready';
            $plan['percent'] = 100;
            $plan['step_percent'] = 100;
            return $plan;
        }

        $state = $this->get_proc_state($key);

        if (!$state) {
            $state = $this->init_proc_state('sync_dd_to_db', $key, [
                'mode'            => $mode,
                'dd'              => $dd,
                'dd_ref'          => $ddRef,
                'modul'           => $modul,
                'server'          => $plan['server'],
                'table'           => $plan['table'],
                'phase'           => 'prepare',
                'plan'            => $plan,
                'field_pos'       => 0,
                'index_pos'       => 0,
                'percent'         => 0,
                'message'         => 'sync initialized',
                'mapping'         => $this->get_schema_mapping_values('dd_to_db', [
                    'modul'  => $modul,
                    'dd'     => $dd,
                    'server' => $plan['server'],
                    'table'  => $plan['table'],
                ]),
                'backup_started'  => 0,
                'restore_started' => 0,
            ]);
        }

        if ($this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        $server = $state['server'];
        $table  = $state['table'];
        $plan   = $state['plan'];

        switch ($state['phase']) {
            case 'prepare':
                if (!empty($plan['create_table'])) {
                    $state['phase']   = 'create_table';
                    $state['message'] = 'create table';
                    $state['percent'] = 10;
                    $state['step_percent'] = 0;
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                if (!empty($plan['rebuild_needed'])) {
                    if (!in_array($state['mode'], ['force', 'rebuild'], true)) {
                        $state = $this->proc_error($state, 'rebuild needed; use mode force or rebuild');
                        $this->set_proc_state($key, $state);
                        return $state;
                    }

                    $state['phase']   = 'backup_old';
                    $state['message'] = 'backup old table';
                    $state['percent'] = 10;
                    $state['step_percent'] = 0;
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state['phase']   = 'add_fields';
                $state['message'] = 'add missing fields';
                $state['percent'] = 20;
                $state['step_percent'] = 0;
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'create_table':
                if ($this->create_db_tab($state['dd_ref'] ?? $ddRef)) {
                    $state['phase']   = 'add_indexes';
                    $state['message'] = 'create indexes';
                    $state['percent'] = 70;
                    $state['step_percent'] = 100;
                } else {
                    $state = $this->proc_error($state, 'create table failed');
                }
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'add_fields':
                $started = $this->step_start_time();
                $fields  = $plan['add_fields'] ?? [];

                while ($state['field_pos'] < count($fields) && $this->step_time_left($started, (float)$state['step_maxsec'])) {
                    $field = $fields[$state['field_pos']];
                    $ok = $this->add_db_field_from_dd($server, $table, $field);

                    if (!$ok) {
                        $state = $this->proc_error($state, 'add field failed: ' . ($field['name'] ?? ''));
                        $this->set_proc_state($key, $state);
                        return $state;
                    }

                    $state['field_pos']++;
                    $max = max(1, count($fields));
                    $state['percent'] = 20 + (int)floor(($state['field_pos'] / $max) * 40);
                    $state['step_percent'] = (int)floor(($state['field_pos'] / $max) * 100);
                    $state['message'] = 'add field ' . ($field['name'] ?? '');
                }

                if ($state['field_pos'] >= count($fields)) {
                    $state['phase']   = 'add_indexes';
                    $state['message'] = 'add indexes';
                    $state['percent'] = 70;
                    $state['step_percent'] = 100;
                }

                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'add_indexes':
                $started = $this->step_start_time();
                $indexes = $plan['add_indexes'] ?? [];

                while ($state['index_pos'] < count($indexes) && $this->step_time_left($started, (float)$state['step_maxsec'])) {
                    $idx = $indexes[$state['index_pos']];
                    $ok  = $this->create_db_index($server, $table, $idx);

                    if (!$ok) {
                        $state = $this->proc_error($state, 'add index failed: ' . ($idx['name'] ?? ''));
                        $this->set_proc_state($key, $state);
                        return $state;
                    }

                    $state['index_pos']++;
                    $max = max(1, count($indexes));
                    $state['percent'] = 70 + (int)floor(($state['index_pos'] / $max) * 20);
                    $state['step_percent'] = (int)floor(($state['index_pos'] / $max) * 100);
                    $state['message'] = 'add index ' . ($idx['name'] ?? '');
                }

                if ($state['index_pos'] >= count($indexes)) {
                    $state = $this->proc_finish($state, 'sync dd -> db finished');
                }

                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'backup_old':
                $backupFile = $state['plan']['backup_file'] ?? '';
                if (!$backupFile) {
                    $backupFile = $this->build_backup_file_name($server, $table, true);
                    $state['plan']['backup_file'] = $backupFile;
                }

                $bak = $this->backup($server, $table, $backupFile, 1);
                if (($bak['status'] ?? '') === 'error') {
                    $state = $this->proc_error($state, 'backup before rebuild failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                if (($bak['status'] ?? '') !== 'finished') {
                    $state['percent'] = 10 + (int)floor((($bak['percent'] ?? 0) * 0.3));
                    $state['step_percent'] = (int)($bak['percent'] ?? 0);
                    $state['message'] = 'backup old table';
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state['phase']   = 'rename_old';
                $state['percent'] = 45;
                $state['step_percent'] = 100;
                $state['message'] = 'rename old table';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'rename_old':
                $tmpOld = $table . '__dbxold_' . date('YmdHis');
                $sql = 'ALTER TABLE ' . $this->quote_ident($server, $table)
                     . ' RENAME TO ' . $this->quote_ident($server, $tmpOld);

                if (!$this->exec_query($server, $sql)) {
                    $state = $this->proc_error($state, 'rename old table failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                $state['old_table'] = $tmpOld;
                $state['phase']     = 'create_new';
                $state['percent']   = 55;
                $state['step_percent'] = 100;
                $state['message']   = 'create new table';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'create_new':
                if (!$this->create_db_tab($state['dd_ref'] ?? $ddRef)) {
                    $state = $this->proc_error($state, 'create new table failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                $state['phase']   = 'restore_new';
                $state['percent'] = 65;
                $state['step_percent'] = 100;
                $state['message'] = 'restore data into new table';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'restore_new':
                $mapping = is_array($state['mapping'] ?? null) ? $state['mapping'] : [];
                $restore = $this->restore($server, $table, $state['plan']['backup_file'], $mapping, 1);

                if (($restore['status'] ?? '') === 'error') {
                    $state = $this->proc_error($state, 'restore into new table failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                if (($restore['status'] ?? '') !== 'finished') {
                    $state['percent'] = 65 + (int)floor((($restore['percent'] ?? 0) * 0.25));
                    $state['step_percent'] = (int)($restore['percent'] ?? 0);
                    $state['message'] = 'restore data into new table';
                    $this->set_proc_state($key, $state);
                    return $this->proc_response($state);
                }

                $state['phase']   = 'drop_old';
                $state['percent'] = 92;
                $state['step_percent'] = 100;
                $state['message'] = 'drop old table';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'drop_old':
                if (!empty($state['old_table'])) {
                    $this->drop_db_tab($server, $state['old_table']);
                }

                $state = $this->proc_finish($state, 'sync dd -> db rebuild finished');
                $this->set_proc_state($key, $state);
                return $state;
        }

        $state = $this->proc_error($state, 'unknown sync phase');
        $this->set_proc_state($key, $state);
        return $state;
    }


    /* =====================================================
     * DB -> DD SYNC
     * ===================================================== */

    /**
     * Synchronisiert eine physische Datenbanktabelle technisch in Richtung DD.
     *
     * Der Ablauf liest Felder und Indizes aus der Datenbank, fuehrt sie mit
     * vorhandenen DD-Metadaten zusammen und schreibt daraus wieder eine
     * DD-Datei. Bestehende DD-Werte haben Vorrang: Typen, Laengen, Rechte,
     * Cache-/Trace-Flags und fachliche Metadaten werden nicht blind durch
     * physische SQLite/db3-Typen ueberschrieben. Das verhindert Struktur-Drift
     * ueber mehrere Sync-Durchlaeufe.
     *
     * Typische Modi:
     *
     * - `merge` oder `step`: schema lesen, Metadaten mergen, DD schreiben
     * - `restart`: vorhandenen Prozesszustand loeschen und neu starten
     * - `status`: aktuellen Prozesszustand lesen
     * - `reset`: Prozesszustand loeschen
     * - `pause`, `resume`, `continue`, `cancel`: Prozesssteuerung
     *
     * Beispiel:
     *
     * ```php
     * $dd = dbx()->get_system_obj('dbxDD');
     * $state = $dd->sync_db_to_dd(
     *     'dbxContact',
     *     'contactRequest',
     *     'merge',
     *     'dbXsystem',
     *     'contact_request'
     * );
     * ```
     *
     * Ergebnis:
     *
     * ```php
     * [
     *     'status' => 'running',
     *     'phase' => 'write_dd',
     *     'percent' => 70,
     * ]
     * ```
     *
     * @param string $modul Zielmodul, in dessen `dd/`-Verzeichnis geschrieben wird.
     * @param string $dd DD-Name ohne Dateiendung.
     * @param string $mode Sync-Modus oder Prozesssteuerung.
     * @param string $server Optionaler DB-Server; leer nutzt DD- oder System-Fallback.
     * @param string $table Optionaler physischer Tabellenname; leer nutzt DD- oder Namens-Fallback.
     *
     * @return array Prozessstatus mit `status`, `message`, `percent` und optional `phase`.
     */
    public function sync_db_to_dd($modul, $dd, $mode = 'step', string $server = '', string $table = ''): array
    {
        $mode = strtolower((string)$mode);
        if ($mode === '') {
            $mode = 'step';
        }
        if ($mode === 'step' || $mode === 'apply') {
            $mode = 'merge';
        }

        $server = trim($server);
        $table  = trim($table);
        $key = $this->proc_key('sync_db_to_dd', [$modul, $dd, $server, $table]);

        if ($mode === 'reset') {
            $this->clear_proc_state($key);
            return [
                'proc_key' => $key,
                'status'   => 'reset',
                'message'  => 'sync state cleared',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if ($mode === 'status') {
            $state = $this->get_proc_state($key);
            return $state ?: [
                'proc_key' => $key,
                'status'   => 'new',
                'message'  => 'no active state',
                'percent'  => 0,
                'step_percent' => 0,
            ];
        }

        if (in_array($mode, ['pause', 'resume', 'continue', 'cancel'], true)) {
            return $this->control_proc_state($key, $mode);
        }

        if ($mode === 'restart') {
            $this->clear_proc_state($key);
            $mode = 'merge';
        }

        $state = $this->get_proc_state($key);

        if (!$state) {
            $oldModel = [];
            $syncServer = $server ?: 'dbXsystem';
            $syncTable  = $table ?: $dd;

            if ($this->get_dd_exist($modul . '|' . $dd) || $this->get_dd_exist($dd)) {
                $oldModel = $this->get_dd_model($modul . '|' . $dd);
                if (!$oldModel) {
                    $oldModel = $this->get_dd_model($dd);
                }

                if (!$server) {
                    $syncServer = $oldModel['table']['server'] ?? $syncServer;
                }
                if (!$table) {
                    $syncTable  = $oldModel['table']['table']  ?? $syncTable;
                }
            }

            if (!$this->get_table_exist($syncServer, $syncTable)) {
                return $this->proc_error(['proc_key' => $key], 'table not found');
            }

            $state = $this->init_proc_state('sync_db_to_dd', $key, [
                'mode'      => $mode,
                'modul'     => $modul,
                'dd'        => $dd,
                'server'    => $syncServer,
                'table'     => $syncTable,
                'old_model' => $oldModel,
                'mapping'   => $this->get_schema_mapping_values('db_to_dd', [
                    'modul'  => $modul,
                    'dd'     => $dd,
                    'server' => $syncServer,
                    'table'  => $syncTable,
                ]),
                'phase'     => 'read_schema',
                'percent'   => 0,
                'message'   => 'sync initialized',
            ]);
        }

        if ($this->proc_is_waiting($state)) {
            return $this->proc_response($state);
        }

        switch ($state['phase']) {
            case 'read_schema':
                $state['db_fields']  = $this->get_db_fields($state['server'], $state['table']);
                $state['db_indexes'] = $this->get_db_indexes($state['server'], $state['table']);
                $state['phase']      = 'merge_meta';
                $state['percent']    = 30;
                $state['step_percent'] = 100;
                $state['message']    = 'schema loaded';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'merge_meta':
                $oldModel = $state['old_model'] ?? [];

                $newTable = $this->normalize_table_record($state['dd'], [
                    'server' => $state['server'],
                    'table'  => $state['table'],
                ]);

                if ($state['mode'] === 'merge' && $oldModel) {
                    foreach ([
                        'autosync','version','cache','trash','trace',
                        'read','create','update','delete',
                        'read_owner','create_owner','update_owner','delete_owner'
                    ] as $k) {
                        if (isset($oldModel['table'][$k])) {
                            $newTable[$k] = $oldModel['table'][$k];
                        }
                    }
                }

                $oldFieldsByName = [];
                foreach (($oldModel['fields'] ?? []) as $field) {
                    $oldFieldsByName[strtolower((string)($field['name'] ?? ''))] = $field;
                }

                $mapping = is_array($state['mapping'] ?? null) ? $state['mapping'] : [];
                $newFields = [];
                foreach (($state['db_fields'] ?? []) as $field) {
                    $name = $field['name'];
                    $metaName = $mapping[$name] ?? $name;
                    $metaKey = strtolower((string)$metaName);
                    if (!isset($oldFieldsByName[$metaKey])) {
                        $metaName = $name;
                        $metaKey = strtolower((string)$metaName);
                    }

                    if ($state['mode'] === 'merge' && isset($oldFieldsByName[$metaKey])) {
                        $newFields[] = $this->merge_dd_field_with_db_field(
                            $oldFieldsByName[$metaKey],
                            $field,
                            $this->get_db_type($state['server'])
                        );
                    } else {
                        $newFields[] = $this->normalize_field_record($field);
                    }
                }

                $oldIndexesByName = [];
                foreach (($oldModel['indexes'] ?? []) as $index) {
                    $oldIndexesByName[$index['name']] = $index;
                }

                $newIndexes = [];
                foreach (($state['db_indexes'] ?? []) as $index) {
                    $name = $index['name'];

                    if ($state['mode'] === 'merge' && isset($oldIndexesByName[$name])) {
                        $merged = $index;
                        foreach ($oldIndexesByName[$name] as $k => $v) {
                            if ($v !== '' && $v !== null) {
                                $merged[$k] = $v;
                            }
                        }
                        $newIndexes[] = $this->normalize_index_record($merged);
                    } else {
                        $newIndexes[] = $this->normalize_index_record($index);
                    }
                }

                $state['new_table']   = $newTable;
                $state['new_fields']  = $newFields;
                $state['new_indexes'] = $newIndexes;
                $state['phase']       = 'write_dd';
                $state['percent']     = 70;
                $state['step_percent'] = 100;
                $state['message']     = 'meta merged';
                $this->set_proc_state($key, $state);
                return $this->proc_response($state);

            case 'write_dd':
                $ok = $this->write_dd(
                    $state['modul'],
                    $state['dd'],
                    $state['new_table'] ?? [],
                    $state['new_fields'] ?? [],
                    $state['new_indexes'] ?? []
                );

                if (!$ok) {
                    $state = $this->proc_error($state, 'write dd failed');
                    $this->set_proc_state($key, $state);
                    return $state;
                }

                $this->clear_dd_cache($state['modul'] . '|' . $state['dd']);

                $state = $this->proc_finish($state, 'sync db -> dd finished');
                $this->set_proc_state($key, $state);
                return $state;
        }

        $state = $this->proc_error($state, 'unknown sync phase');
        $this->set_proc_state($key, $state);
        return $state;
    }

    /**
     * Kompatibilitaetsalias fuer `sync_db_to_dd()`.
     *
     * Der Name bleibt erhalten, damit bestehende Admin-Routen oder aeltere
     * Modulaufrufe weiter funktionieren. Neue Aufrufe sollen direkt
     * `sync_db_to_dd()` verwenden.
     *
     * Beispiel:
     *
     * ```php
     * $state = dbx()->get_system_obj('dbxDD')->dync_db_to_dd(
     *     'dbxContact',
     *     'contactRequest',
     *     'status',
     *     'dbXsystem',
     *     'contact_request'
     * );
     * ```
     *
     * @param string $modul Zielmodul.
     * @param string $dd DD-Name ohne Dateiendung.
     * @param string $mode Sync-Modus oder Prozesssteuerung.
     * @param string $server Optionaler DB-Server.
     * @param string $table Optionaler physischer Tabellenname.
     *
     * @return array Prozessstatus wie bei `sync_db_to_dd()`.
     */
    public function dync_db_to_dd($modul, $dd, $mode = 'step', string $server = '', string $table = ''): array
    {
        return $this->sync_db_to_dd($modul, $dd, $mode, $server, $table);
    }


    /* =====================================================
     * LIST
     * ===================================================== */

    /**
     * Liefert DD-Übersichtsdaten aus einem DD-Verzeichnis.
     *
     * Hinweis:
     * - dient als technische Infrastruktur
     * - aktuell liest diese Funktion die DD-Dateien des angegebenen Pfads
     * - für spätere Admin-Oberflächen geeignet
     *
     * @param string $path DD-Verzeichnispfad
     *
     * @return array DD-Tabellenübersicht
     */
    public function get_dd_tables($path = ''): array
    {
        $records = [];

        if (!$path) {
            $path = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/dd/');
        }

        if (!is_dir($path)) {
            return $records;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if (!str_ends_with($file, '.dd.php')) {
                continue;
            }

            $dd = str_replace('.dd.php', '', $file);
            if ($dd === 'new') {
                continue;
            }

            $dd_sys = $this->load_dd($dd);
            if (($dd_sys['dd_status'] ?? 0) <= 0) {
                continue;
            }

            $server = $this->get_dd_server($dd);
            $table  = $this->get_dd_table($dd);
            $exist  = $this->get_table_exist($server, $table) ? 1 : 0;
            $count  = $exist ? $this->count($dd) : -1;

            $records[] = [
                'datadic' => $dd,
                'server'  => $server,
                'table'   => $table,
                'exist'   => $exist,
                'count'   => $count,
                'sync'    => $this->get_dd_autosync($dd),
            ];
        }

        return $records;
    }
}
