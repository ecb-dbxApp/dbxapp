<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDDModelTrait
{
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
        return $this->database->load_dd($dd);
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

        $table = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table'] ?? [];
        if (is_array($table)) {
            $table['server'] = $this->get_dd_server($dd);
        }

        return [
            'table'   => $table,
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
     * Speichert eine DD-Datei und synchronisiert sie verbindlich in die DB.
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
            $ok = $this->synchronize_saved_dd($modul, $dd);
        }

        return $ok;
    }

    /** Fuehrt eine DD->DB-Synchronisierung bis zum Endstatus aus. */
    private function synchronize_saved_dd(string $modul, string $dd): int
    {
        $plan = $this->sync_dd_to_db($modul, $dd, 'plan');
        $mode = !empty($plan['rebuild_needed']) ? 'force' : 'apply';
        $this->sync_dd_to_db($modul, $dd, 'reset');

        $state = array();
        for ($step = 0; $step < 512; $step++) {
            $state = $this->sync_dd_to_db($modul, $dd, $mode);
            $status = strtolower((string)($state['status'] ?? ''));
            if ($status === 'finished') {
                $this->clear_dd_cache($modul . '|' . $dd);
                return 1;
            }
            if (in_array($status, array('error', 'canceled', 'cancelled', 'paused'), true)) {
                break;
            }
        }

        dbx()->sys_msg(
            'error',
            'dd',
            $dd,
            'sync_dd_to_db',
            (string)($state['message'] ?? 'DD wurde geschrieben, DB-Synchronisierung nicht abgeschlossen')
        );
        return 0;
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
    private function write_dd(string $modul, string $dd, array $table, array $fields, array $indexes = []): int
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
}
