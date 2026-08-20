<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDBDataDefinitionTrait
{
/**
     * Liefert die installationsbezogenen Serverbindungen der DDs.
     *
     * Die Bindungen liegen absichtlich in der lokalen dbx-Konfiguration.
     * Release-DDs bleiben damit portabel und ein Update kann eine lokale
     * SQLite-/MySQL-Entscheidung nicht ueberschreiben.
     *
     * @return array<string,string>
     */
    protected function get_dd_server_bindings(): array {
        $config = dbx()->get_cfg('dbx');
        $bindings = $config['dd_server_bindings'] ?? array();

        return is_array($bindings) ? $bindings : array();
    }

/**
     * Sucht eine Bindung ohne von der Schreibweise des DD-Schluessels
     * abzuhaengen. Der verbindliche Schluessel ist `modul|dd`; der reine
     * DD-Name bleibt als Rueckwaertskompatibilitaet erhalten.
     */
    private function find_dd_server_binding(
        array $bindings,
        string $dd_module,
        string $dd_name
    ): array {
        $exact = $dd_module . '|' . $dd_name;
        $candidates = array($exact, $dd_name);

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $bindings)) {
                return array(
                    'key' => $candidate,
                    'server' => trim((string)$bindings[$candidate]),
                );
            }
        }

        $lower_candidates = array_map('strtolower', $candidates);
        foreach ($bindings as $key => $server) {
            $position = array_search(strtolower(trim((string)$key)), $lower_candidates, true);
            if ($position !== false) {
                return array(
                    'key' => (string)$key,
                    'server' => trim((string)$server),
                );
            }
        }

        return array('key' => '', 'server' => '');
    }

/**
     * Prüft eine lokale DD-Serverbindung, ohne eine Verbindung aufzubauen.
     *
     * DB3-Bindungen duerfen nur aus optionalem Modulnamen und Dateinamen
     * bestehen. SQL-Bindungen muessen auf einen aktiven, lokal konfigurierten
     * Server zeigen. Bei einer fehlerhaften Bindung gibt es keinen stillen
     * Fallback auf die im DD genannte Datenbank.
     */
    protected function is_valid_dd_server_binding(string $server): bool {
        $server = trim($server);
        if ($server === '') {
            return false;
        }

        if (preg_match('/\.(db3|sqlite|sqlite3)$/i', $server)) {
            $parts = strpos($server, '|') !== false
                ? explode('|', $server, 2)
                : array('', $server);
            $module = trim((string)($parts[0] ?? ''));
            $file = trim((string)($parts[1] ?? ''));

            return ($module === '' || preg_match('/^[A-Za-z0-9_]+$/', $module) === 1)
                && basename($file) === $file
                && preg_match('/^[A-Za-z0-9_.-]+\.(db3|sqlite|sqlite3)$/i', $file) === 1;
        }

        $config = dbx()->get_cfg('dbx');
        $db_config = $config['db'][$server] ?? null;

        return is_array($db_config)
            && $this->db_server_config_is_active($server, $db_config);
    }

/**
     * Liefert Herkunft und Ergebnis der Serveraufloesung einer DD.
     *
     * Jede DD kann unabhaengig gebunden werden. Dadurch sind auch innerhalb
     * eines Moduls beliebige Mischungen aus DB3- und SQL-Servern moeglich.
     *
     * @return array{
     *   dd:string,
     *   binding_key:string,
     *   declared_server:string,
     *   resolved_server:string,
     *   source:string,
     *   valid:bool
     * }
     */
    public function get_dd_server_binding_info(string $dd): array {
        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = (string)($dd_sys['dd_modul'] ?? '');
        $dd_name   = (string)($dd_sys['dd_name'] ?? '');

        if ($dd_status != 1) {
            return array(
                'dd' => $dd,
                'binding_key' => '',
                'declared_server' => '',
                'resolved_server' => '',
                'source' => 'missing-dd',
                'valid' => false,
            );
        }

        $cache = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name] ?? array();
        $declared = trim((string)(
            $cache['declared_server']
            ?? ($cache['table']['server'] ?? 'default')
        ));
        $binding = $this->find_dd_server_binding(
            $this->get_dd_server_bindings(),
            $dd_modul,
            $dd_name
        );
        $resolved = $binding['key'] !== '' ? $binding['server'] : $declared;
        $valid = $binding['key'] === ''
            ? $resolved !== ''
            : $this->is_valid_dd_server_binding($resolved);

        if (!$valid && $binding['key'] !== '') {
            dbx()->sys_msg(
                'error',
                'db',
                $dd_modul . '|' . $dd_name,
                'ungueltige lokale DD-Serverbindung',
                $binding['key'] . ' => ' . $resolved
            );
            $resolved = '';
        }

        return array(
            'dd' => $dd_modul . '|' . $dd_name,
            'binding_key' => $binding['key'],
            'declared_server' => $declared,
            'resolved_server' => $resolved,
            'source' => $binding['key'] !== '' ? 'local-binding' : 'dd-default',
            'valid' => $valid,
        );
    }

/**
     * Ermittelt den lokal wirksamen Server einer Datenbeschreibung.
     *
     * Vorrang:
     * 1. `dd_server_bindings['modul|dd']` aus config.local.php
     * 2. Serverangabe des ausgelieferten DDs
     *
     * @return string Servername oder leer bei ungueltiger lokaler Bindung
     */
    public function get_dd_server(string $dd): string {
        $binding = $this->get_dd_server_binding_info($dd);
        return (string)($binding['resolved_server'] ?? '');
    }

/**
     * Gibt das CSV-Trennzeichen für eine DD zurück.
     *
     * Falls in der DD kein CSV-Separator definiert ist,
     * wird `;` verwendet.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string CSV-Trennzeichen
     */
    public function get_csv_separator($dd) {
        $csv_separator = ';';

        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1) {
            $csv_separator = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table']['csv'] ?? ';';
        }

        return $csv_separator;
    }

/**
     * Ermittelt den Primärschlüssel einer Datenbeschreibung (DD).
     *
     * Gibt den in der DD definierten Primärschlüssel zurück.
     * Falls keiner vorhanden ist, wird `id` verwendet.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string Primärschlüssel der DD oder `id`
     */
    public function get_dd_primary(string $dd): string {
        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1) {
            $primary = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table']['primary'] ?? '';

            if ($primary !== '') {
                return $primary;
            }
        }

        return 'id';
    }

/**
     * Platzhalter für Sortierfelder einer DD.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string Aktuell leer
     */
    public function get_dd_sort_flds($dd) {
        $fld = '';
        return $fld;
    }

/**
     * Platzhalter für Sortierrichtung einer DD.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string Standardmäßig `ASC`
     */
    public function get_dd_sort_desc($dd) {
        $desc = 'ASC';
        return $desc;
    }

/**
     * Fügt ein neues Feld zu einer bestehenden Tabelle in der Datenbank hinzu.
     *
     * Zweck
     * -----
     * Erzeugt abhängig vom DB-Typ das passende SQL für `ALTER TABLE ... ADD COLUMN`.
     * Die Methode erzeugt aktuell das SQL und verbindet sich mit dem Server.
     * Zusätzliche Index-/Primary-SQLs werden geloggt.
     *
     * Hinweis
     * -------
     * Diese Funktion ist bewusst minimal gehalten und ändert die bestehende
     * DBX-Logik nicht grundsätzlich.
     *
     * @param string $server Der Name des Datenbankservers
     * @param string $table Der Name der Tabelle
     * @param array  $field Felddefinition:
     *                      - name
     *                      - type
     *                      - length
     *                      - default
     *                      - index
     *
     * @return bool True bei Erfolg, sonst false
     */
    public function add_db_fld($server, $table, $field) {
        $ok = $this->connect_db_server($server);

        if (!$ok) {
            return false;
        }

        $type_map = [
            'int'     => ['mysql' => 'INT', 'sqlite' => 'INTEGER', 'pgsql' => 'INTEGER', 'sqlsrv' => 'INT', 'oci' => 'NUMBER', 'firebird' => 'INTEGER', 'cubrid' => 'INTEGER', 'dblib' => 'INT', 'ibm' => 'INTEGER', 'informix' => 'INTEGER', 'odbc' => 'INTEGER'],
            'varchar' => ['mysql' => 'VARCHAR', 'sqlite' => 'TEXT', 'pgsql' => 'VARCHAR', 'sqlsrv' => 'VARCHAR', 'oci' => 'VARCHAR2', 'firebird' => 'VARCHAR', 'cubrid' => 'VARCHAR', 'dblib' => 'VARCHAR', 'ibm' => 'VARCHAR', 'informix' => 'VARCHAR', 'odbc' => 'VARCHAR'],
            'text'    => ['mysql' => 'TEXT', 'sqlite' => 'TEXT', 'pgsql' => 'TEXT', 'sqlsrv' => 'TEXT', 'oci' => 'CLOB', 'firebird' => 'BLOB', 'cubrid' => 'STRING', 'dblib' => 'TEXT', 'ibm' => 'CLOB', 'informix' => 'TEXT', 'odbc' => 'LONGVARCHAR'],
            'bool'    => ['mysql' => 'TINYINT(1)', 'sqlite' => 'INTEGER', 'pgsql' => 'BOOLEAN', 'sqlsrv' => 'BIT', 'oci' => 'NUMBER(1)', 'firebird' => 'SMALLINT', 'cubrid' => 'SMALLINT', 'dblib' => 'BIT', 'ibm' => 'SMALLINT', 'informix' => 'BOOLEAN', 'odbc' => 'BOOLEAN'],
            'date'    => ['mysql' => 'DATE', 'sqlite' => 'TEXT', 'pgsql' => 'DATE', 'sqlsrv' => 'DATE', 'oci' => 'DATE', 'firebird' => 'DATE', 'cubrid' => 'DATE', 'dblib' => 'DATE', 'ibm' => 'DATE', 'informix' => 'DATE', 'odbc' => 'DATE']
        ];

        $db_type = $this->get_db_type($server);
        $type   = $type_map[$field['type']][$db_type] ?? $field['type'];

        $sql = "ALTER TABLE $table ADD COLUMN {$field['name']} $type";

        if (!empty($field['length']) && in_array($field['type'], ['int', 'varchar'])) {
            $sql .= "({$field['length']})";
        }

        if (isset($field['default']) && ($field['default'] !== '' || $field['default'] === 0)) {
            $sql .= " DEFAULT '{$field['default']}'";
        }

        dbx()->debug("ADD-FLD SQL=($sql)");

        if (($field['index'] ?? '') === 'PRI') {
            $sql_index = "ALTER TABLE $table ADD PRIMARY KEY ({$field['name']})";
            dbx()->debug("ADD-PRIMARY-KEY SQL=($sql_index)");
        } elseif (($field['index'] ?? '') === 'MU') {
            $sql_index = "CREATE INDEX idx_{$field['name']} ON $table ({$field['name']})";
            dbx()->debug("ADD-INDEX SQL=($sql_index)");
        }

        return $ok;
    }

/**
     * Lädt eine Datenbeschreibung (DD) aus Datei und speichert sie im Session-Cache.
     *
     * Zweck
     * -----
     * Zentraler DD-Resolver für alle Folgeoperationen wie:
     * - `select`
     * - `insert`
     * - `update`
     * - `delete`
     * - `save`
     * - Tabellen-/Feld-/Server-Abfragen
     *
     * Suchlogik
     * ---------
     * - `name`       → aktives Modul, danach Fallback `dbx`
     * - `modul|name` → explizit dieses Modul
     * - `modul|...`  → Platzhalter für aktives Modul
     *
     * Wichtige Stabilitätsregel
     * -------------------------
     * Ein expliziter Modulaufruf `modulX|dd` darf nicht durch einen bereits
     * vorhandenen `dbx`-Cacheeintrag derselben DD übersteuert werden.
     * Der `dbx`-Cache-Fallback wird daher nur bei nicht explizitem Modulaufruf verwendet.
     *
     * Rückgabe
     * --------
     * - `dd_status = 1`  → DD erfolgreich geladen
     * - `dd_status = 0`  → Datei nicht gefunden
     * - `dd_status = -1` → DD-Datei ungültig
     *
     * @param string $dd Name der Datenbeschreibung, optional mit Modulpräfix
     *
     * @return array{
     *     dd_status:int,
     *     dd_modul:string,
     *     dd_name:string
     * }
     */
    public function load_dd(string $dd): array {
        $dd_sys = array();

        $activ_modul      = dbx()->get_system_var('dbx_activ_modul', 'dbx');
        $is_explicit_modul = false;
        $active_language   = strtolower(trim((string) dbx()->lng_current()));

        if ($active_language === '' || !preg_match('/^[a-z]{2,3}$/', $active_language)) {
            $active_language = 'de';
        }

        $cache_matches_language = static function (array $cache, string $language): bool {
            $table = isset($cache['table']) && is_array($cache['table']) ? $cache['table'] : array();
            $dynamic = !empty($cache['language_dynamic']) || (($table['language'] ?? '') === '*');

            if (!$dynamic) {
                return true;
            }

            return strtolower(trim((string) ($table['language'] ?? ''))) === $language;
        };

        if (strpos($dd, '|') !== false) {
            $parts             = explode('|', $dd, 2);
            $dd_modul          = trim($parts[0]);
            $dd_name           = trim($parts[1]);
            $is_explicit_modul = true;

            if ($dd_modul === 'modul' || $dd_modul === '') {
                $dd_modul = $activ_modul;
            }
        } else {
            $dd_modul = $activ_modul;
            $dd_name  = $dd;
        }

        if (isset($_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name])) {
            $cached_dd = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name];
            if ($cache_matches_language($cached_dd, $active_language)) {
                $dd_sys['dd_status'] = 1;
                $dd_sys['dd_modul']  = $dd_modul;
                $dd_sys['dd_name']   = $dd_name;

                return $dd_sys;
            }

            unset($_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]);
        }

        if (!$is_explicit_modul && isset($_SESSION['dbx']['cache']['dd']['dbx'][$dd_name])) {
            $cached_dd = $_SESSION['dbx']['cache']['dd']['dbx'][$dd_name];
            if ($cache_matches_language($cached_dd, $active_language)) {
                $dd_sys['dd_status'] = 1;
                $dd_sys['dd_modul']  = 'dbx';
                $dd_sys['dd_name']   = $dd_name;

                return $dd_sys;
            }

            unset($_SESSION['dbx']['cache']['dd']['dbx'][$dd_name]);
        }

        $dd_file = '';

        $dd_file1 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/dd/' . $dd_name . '.dd.php');
        $dd_file2 = dbx()->os_path(dbx()->get_base_dir() . "dbx/modules/$dd_modul/dd/" . $dd_name . '.dd.php');

        if (file_exists($dd_file2)) {
            $dd_file = $dd_file2;
        } elseif (file_exists($dd_file1)) {
            $dd_file  = $dd_file1;
            $dd_modul = 'dbx';
        }

        $dd_sys['dd_modul'] = $dd_modul;
        $dd_sys['dd_name']  = $dd_name;

        dbx()->debug("##dbxDB-load_dd=($dd) modul=($dd_modul) dd=($dd_name) file=($dd_file) aktiv modul=($activ_modul)");

        if (empty($dd_file)) {
            dbx()->sys_msg('error', 'dd', $dd, 'missing', 'No dd Path');
            $dd_sys['dd_status'] = 0;
            return $dd_sys;
        }

        include $dd_file;

        if (!isset($table) || !is_array($table)) {
            $dd_sys['dd_status'] = -1;
            return $dd_sys;
        }

        /*
         * Sprachabhaengige DDs kennzeichnen die neutrale Definition mit
         * language="*". In diesem Fall muss der Loader selbst die DD der
         * aktiven Sprache verwenden. Andernfalls wuerde z. B.
         * dbxContentFolder auf die nicht vorhandene Tabelle content_folder
         * statt auf content_folder_de zeigen.
         */
        $language_dynamic = (($table['language'] ?? '') === '*');
        if ($language_dynamic) {
            $language = $active_language;
            $table_base = trim((string) ($table['table'] ?? ''));
            if ($table_base !== '') {
                $language_dd_name = $table_base . '_' . $language;
                $language_dd_file1 = dbx()->os_path(
                    dbx()->get_base_dir() . 'dbx/modules/dbx/dd/' . $language_dd_name . '.dd.php'
                );
                $language_dd_file2 = dbx()->os_path(
                    dbx()->get_base_dir() . 'dbx/modules/' . $dd_modul . '/dd/' . $language_dd_name . '.dd.php'
                );
                $language_dd_file = '';

                if (file_exists($language_dd_file2)) {
                    $language_dd_file = $language_dd_file2;
                } elseif (file_exists($language_dd_file1)) {
                    $language_dd_file = $language_dd_file1;
                }

                if ($language_dd_file !== '') {
                    unset($table, $fields, $indexes);
                    include $language_dd_file;
                    $dd_file = $language_dd_file;
                } else {
                    $table['table']    = $language_dd_name;
                    $table['datadic']  = $language_dd_name;
                    $table['language'] = $language;
                }
            }
        }

        dbx()->register_editor_file('dd', $dd_file);

        if (!isset($fields) || !is_array($fields)) {
            $fields = array();
        }

        if (!isset($indexes) || !is_array($indexes)) {
            $indexes = array();
        }

        if (isset($table['server'])
            && is_string($table['server'])
            && strpos($table['server'], '|') === false
            && preg_match('/\.(db3|sqlite|sqlite3)$/i', $table['server'])
        ) {
            $sqlite_file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $dd_modul . '/db/' . $table['server']);

            if ($dd_modul !== '' && file_exists($sqlite_file)) {
                $table['server'] = $dd_modul . '|' . $table['server'];
            }
        }

        $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name] = [
            'table'            => $table,
            'fields'           => $fields,
            'indexes'          => $indexes,
            'file'             => $this->normalize_editor_file_path($dd_file),
            'language_dynamic' => $language_dynamic,
            'declared_server'  => (string)($table['server'] ?? 'default'),
        ];

        //dbx()->debug("#session set dd ($dd) Modul=($dd_modul) Name=($dd_name)");

        $dd_sys['dd_status'] = 1;
        $dd_sys['dd_modul']  = $dd_modul;
        $dd_sys['dd_name']   = $dd_name;

        return $dd_sys;
    }

/**
     * Liefert den Dateipfad einer geladenen DD fuer Editor-Marker.
     *
     * @param string $dd Name der Datenbeschreibung
     * @return string Projekt-relativer DD-Pfad oder leer
     */
    public function get_dd_file(string $dd): string {
        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status != 1 || $dd_modul === '' || $dd_name === '') {
            return '';
        }

        return $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['file'] ?? '';
    }

/**
     * Normalisiert absolute Projektpfade fuer den Editor.
     *
     * @param string $file Absoluter Dateipfad
     * @return string Projekt-relativer Pfad
     */
    private function normalize_editor_file_path(string $file): string {
        return dbx()->editor_file_path($file);
    }

/**
     * Gibt die komplette Table-Definition einer DD zurück.
     *
     * Bei fehlender oder ungültiger DD wird `0` zurückgegeben.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return mixed Table-Definition oder 0
     */
    public function get_dd_table_def($dd) {
        $table = 0;
        dbx()->debug("F-Load dd=($dd)");

        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1) {
            $table = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['table'] ?? 0;
        }

        return $table;
    }

/**
     * Gibt die Felddefinitionen einer DD zurück.
     *
     * Optional können die Felder als Key/Label-Liste zurückgegeben werden.
     *
     * - `label = 0` → komplette Felddefinitionen
     * - `label = 1` → Array `feldname => label`
     *
     * Die Funktion arbeitet defensiv:
     * - bei ungültiger DD oder fehlenden Felddefinitionen wird `0` zurückgegeben
     * - bei `label = 1` werden fehlende Labels sauber auf den Feldnamen zurückgeführt
     *
     * @param string $dd Name der Datenbeschreibung
     * @param int    $label 0 = komplette Felddefinitionen, 1 = Name => Label
     *
     * @return mixed Felddefinitionen oder 0
     */
    public function get_dd_fields($dd, $label = 0) {
        $fields = 0;

        $dd_sys    = $this->load_dd($dd);
        $dd_status = $dd_sys['dd_status'] ?? 0;
        $dd_modul  = $dd_sys['dd_modul'] ?? '';
        $dd_name   = $dd_sys['dd_name'] ?? '';

        if ($dd_status == 1) {
            $fields = $_SESSION['dbx']['cache']['dd'][$dd_modul][$dd_name]['fields'];
        }

        if ($label && $dd_status == 1) {
            $xfields = array();

            if (is_array($fields)) {
                foreach ($fields as $no => $field) {
                    $xname  = $field['name'] ?? '';
                    $xlabel = $xname;

                    if ($xname === '') {
                        continue;
                    }

                    if ($label == 1 && isset($field['label']) && $field['label'] !== '') {
                        $xlabel = $field['label'];
                    }

                    $xfields[$xname] = $xlabel;
                }
            }

            $fields = $xfields;
        }

        return $fields;
    }

/**
     * Ermittelt die Report-Felder einer DD für eine übergebene Feldliste.
     *
     * @param string $dd Name der Datenbeschreibung
     * @param string $flds Feldliste oder `*`
     * @param int    $label 1 = Label verwenden
     *
     * @return array Feldliste für Reports
     */
    public function get_rpt_fields($dd, $flds, $label = 1) {
        $fields = $this->get_dd_fields($dd, $label);

        if ($flds != '*') {
            $cols = array();
            $flds = explode(",", $flds);

            if (is_array($flds)) {
                foreach ($flds as $no => $field) {
                    $field = trim($field);
                    $cols[$field] = $field;
                }

                $flds = $cols;
            }
        } else {
            $flds = $fields;
        }

        return $flds;
    }

/**
     * Gibt alle DD-Spaltennamen als CSV-String zurück.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string CSV-Liste der Spalten
     */
    public function get_dd_cols(string $dd): string {
        $fields = $this->get_dd_fields($dd);

        if (!$fields || !is_array($fields)) {
            return '';
        }

        $cols = [];

        foreach ($fields as $f) {
            if (empty($f['name'])) {
                continue;
            }

            $cols[] = $f['name'];
        }

        return implode(',', $cols);
    }

/**
     * Erzeugt die neue Grid-Column-Syntax aus einer DD.
     *
     * Syntax
     * ------
     * - `name[label]:type`
     * - optional `:p` / `:!v`
     * - optional `@gruppe`
     *
     * Die Funktion arbeitet defensiv bei unvollständigen DD-Felddefinitionen.
     *
     * @param string $dd Name der Datenbeschreibung
     *
     * @return string Kommagetrennte Grid-Definition
     */
    public function get_dd_grid_cols(string $dd): string {
        $fields = $this->get_dd_fields($dd);

        if (!$fields || !is_array($fields)) {
            return '';
        }

        $cols = [];

        foreach ($fields as $f) {
            if (empty($f['name'])) {
                continue;
            }

            $name = $f['name'];

            if (str_starts_with($name, '_')) {
                continue;
            }

            $dd_type   = $f['type'] ?? 'text';
            $grid_type = $this->map_dd_type_to_grid_type($dd_type);

            $label = '';
            if (!empty($f['label']) && is_string($f['label'])) {
                $label = trim($f['label']);
            }

            $label = str_replace([':', '[', ']'], '-', $label);

            if (!$label) {
                $label = $name;
            }

            $field_with_label = $name . '[' . $label . ']';

            $protect = isset($f['protect']) ? (string) $f['protect'] : '0';

            $group = '';
            if (!empty($f['group']) && is_string($f['group'])) {
                $group = '@' . trim($f['group']);
            }

            if ($protect === '2') {
                $cols[] = $field_with_label . ':' . $grid_type . ':!v' . $group;
                continue;
            }

            if ($protect === '1') {
                $cols[] = $field_with_label . ':' . $grid_type . ':p' . $group;
                continue;
            }

            $cols[] = $field_with_label . ':' . $grid_type . $group;
        }

        return implode(',', $cols);
    }

/**
     * Mappt DD-Feldtypen auf Grid-Feldtypen.
     *
     * @param string $ddType DD-Typ
     *
     * @return string Grid-Typ
     */
    public function map_dd_type_to_grid_type(string $dd_type): string {
        $t = strtolower(trim($dd_type));

        switch ($t) {
            case 'int':
            case 'integer':
            case 'bigint':
            case 'smallint':
            case 'mediumint':
            case 'tinyint':
            case 'float':
            case 'double':
            case 'decimal':
            case 'numeric':
            case 'real':
                return 'number';

            case 'date':
            case 'datetime':
            case 'timestamp':
                return 'date';

            case 'lookup':
            case 'select':
                return 'lookup';

            case 'varchar':
            case 'char':
            case 'text':
            case 'string':
            default:
                return 'text';
        }
    }

/**
     * Prüft, ob ein Feldname in der DD existiert.
     *
     * Die Prüfung erfolgt defensiv über die DD-Felddefinitionen.
     * Bei ungültiger oder leerer DD-Feldliste wird `false` zurückgegeben.
     *
     * @param string $dd Datenstrukturdefinition
     * @param mixed  $xfield Zu prüfender Feldname
     *
     * @return bool True wenn Feld vorhanden
     */
    private function is_fld_name(string $dd, $xfield) {
        $db_fields = $this->get_dd_fields($dd);

        if (!is_array($db_fields) || empty($db_fields) || !is_string($xfield) || $xfield === '') {
            return false;
        }

        foreach ($db_fields as $field) {
            if (!isset($field['name'])) {
                continue;
            }

            if ($field['name'] === $xfield) {
                return true;
            }
        }

        return false;
    }
}
