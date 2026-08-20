<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDBSchemaTrait
{
/**
     * Prüft ohne Schema-Warnung, ob SQLite seine AUTOINCREMENT-Tabelle hat.
     *
     * `sqlite_sequence` existiert erst, nachdem in der Datenbank mindestens
     * eine Tabelle mit `AUTOINCREMENT` angelegt wurde. Ihr Fehlen ist deshalb
     * kein Datenbankfehler.
     */
    public function sqlite_sequence_exists(string $server): bool {
        if (strtolower((string)$this->get_db_type($server)) !== 'sqlite') {
            return false;
        }

        $rows = $this->select_query(
            $server,
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence' LIMIT 1"
        );

        return is_array($rows) && count($rows) > 0;
    }

/**
     * Ermittelt den erwarteten SQLite-Dateipfad eines Server-Eintrags.
     *
     * @param string $server Servername aus der DD
     * @return string Absoluter Pfad oder leer
     */
    private function resolve_sqlite_db_path(string $server): string {
        if (!preg_match('/\.(db3|sqlite|sqlite3)$/i', $server)) {
            return '';
        }

        $activ_modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
        $sqlite_modul = '';
        $sqlite_name  = '';

        if (strpos($server, '|') !== false) {
            $parts        = explode('|', $server, 2);
            $sqlite_modul = trim($parts[0]);
            $sqlite_name  = trim($parts[1]);

            if ($sqlite_modul === 'modul' || $sqlite_modul === '') {
                $sqlite_modul = $activ_modul;
            }
        } else {
            $sqlite_modul = $activ_modul;
            $sqlite_name  = $server;
        }

        $file1 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/db/' . $sqlite_name);
        $file2 = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $sqlite_modul . '/db/' . $sqlite_name);

        if (is_file($file1)) {
            return $file1;
        }
        if (is_file($file2)) {
            return $file2;
        }

        return $file2;
    }

/**
     * Schreibt Schema-/DB-Probleme einmal pro Request in SysMsg.
     *
     * @param string $key Dedup-Schluessel
     * @param string $rid Betroffener Server oder DD
     * @param string $why Kurzgrund fuer SysMsg
     * @param string $what Detail
     * @return void
     */
    private function log_db_schema_issue(string $key, string $rid, string $why, string $what): void {
        if ($key === '') {
            return;
        }

        $this->report_db_error('db', $rid, $why, $what, $key);
    }

/**
     * Liefert die Spaltennamen einer Tabelle.
     *
     * Ersetzt modulseitiges Roh-SQL wie `PRAGMA table_info(...)` oder
     * `SHOW COLUMNS FROM ...`: Fachmodule sollen Schema-Introspektion nur
     * ueber dbxDB anfordern, nicht per eigenem SQL-String je Datenbanktyp.
     *
     * @param string $server Datenbankserver
     * @param string $table Tabellenname (nur [A-Za-z0-9_])
     *
     * @return array Spaltennamen; leeres Array wenn Tabelle/Verbindung fehlt
     */
    public function get_table_columns(string $server, string $table): array {
        if (!$server || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            return array();
        }
        if (!$this->connect_db_server($server) || !isset($this->db[$server]) || !is_object($this->db[$server])) {
            return array();
        }

        $db_type = $this->get_db_type($server);
        $columns = array();

        try {
            switch ($db_type) {
                case 'sqlite':
                    $stmt = $this->db[$server]->query('PRAGMA table_info(' . $table . ')');
                    foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array() as $row) {
                        $columns[] = (string)($row['name'] ?? '');
                    }
                    break;

                case 'mysql':
                    $stmt = $this->db[$server]->prepare('SHOW COLUMNS FROM ' . $table);
                    $stmt->execute();
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $columns[] = (string)($row['Field'] ?? '');
                    }
                    break;

                default:
                    $stmt = $this->db[$server]->prepare(
                        'SELECT column_name FROM information_schema.columns WHERE table_name = ?'
                    );
                    $stmt->execute(array($table));
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $columns[] = (string)($row['column_name'] ?? '');
                    }
                    break;
            }
        } catch (PDOException $e) {
            return array();
        }

        return array_values(array_filter($columns, static fn($name) => $name !== ''));
    }

/**
     * Prüft, ob eine Tabelle die angegebene Spalte besitzt.
     *
     * @param string $server Datenbankserver
     * @param string $table Tabellenname
     * @param string $column Spaltenname
     *
     * @return bool
     */
    public function has_table_column(string $server, string $table, string $column): bool {
        if ($column === '') {
            return false;
        }
        $column = strtolower($column);
        foreach ($this->get_table_columns($server, $table) as $name) {
            if (strtolower($name) === $column) {
                return true;
            }
        }
        return false;
    }

/**
     * Liefert alle Tabellen eines Servers mit Datensatzanzahl zurück.
     *
     * Hinweis
     * -------
     * Die Rückgabestruktur bleibt unverändert:
     * - `server`
     * - `name`
     * - `count`
     *
     * Falls das Zählen fehlschlägt, wird `count` auf `-1` gesetzt.
     *
     * @param string $server Datenbankserver
     * @param string $not Tabellenname, der ausgeschlossen werden soll
     *
     * @return array Liste der Tabellen
     */
    public function get_db_tables($server, $not = 'sqlite_sequence') {
        $tables = array();
        $ok = $this->connect_db_server($server);

        if ($ok) {
            $db_type = $this->get_db_type($server);
            $table_rows = array();

            switch ($db_type) {
                case 'mysql':
                    $sql = "SHOW TABLES";
                    $table_rows = $this->raw_query($server, $sql);
                    break;

                case 'sqlite':
                    $sql = "SELECT name FROM sqlite_master"
                         . " WHERE type='table' AND name NOT LIKE 'sqlite_%'";
                    $table_rows = $this->raw_query($server, $sql);
                    break;

                case 'oci':
                    $sql = "SELECT table_name FROM user_tables";
                    $table_rows = $this->raw_query($server, $sql);
                    break;

                case 'pgsql':
                    $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
                    $table_rows = $this->raw_query($server, $sql);
                    break;

                case 'sqlsrv':
                    $sql = "SELECT table_name FROM information_schema.tables";
                    $table_rows = $this->raw_query($server, $sql);
                    break;

                default:
                    $table_rows = array();
                    dbx()->sys_msg('warning', 'db', $server, "unsupported db type ($db_type)", 'get_db_tables');
                    break;
            }

            if (is_array($table_rows)) {
                foreach ($table_rows as $table_row) {
                    $table_name = reset($table_row);

                    if ($table_name != $not) {
                        $count = $this->count($table_name, '', $server);

                        if ($count < 0) {
                            $count = -1;
                        }

                        $tables[] = array(
                            'server' => $server,
                            'name'   => $table_name,
                            'count'  => $count
                        );
                    }
                }
            }
        }

        return $tables;
    }

/**
     * Normalisiert und erweitert eine WHERE-Bedingung für SQL-Abfragen.
     *
     * Verhalten
     * ---------
     * - `'new'` oder `'0'` → leer
     * - Integer → `<primary_key> = value`
     * - optionaler Owner-Filter wird sicher mit Klammern ergänzt
     *
     * Wichtige Stabilitätsregel
     * -------------------------
     * Für numerische WHERE-Werte wird, wenn möglich, der Primärschlüssel
     * der übergebenen DD verwendet. Dadurch bleiben Aufrufe wie
     * `select('kunden', 15)` oder `update('kunde', [...], 15)` stabil,
     * auch wenn der Primärschlüssel nicht `id` heißt.
     *
     * Rückwärtskompatibilität
     * -----------------------
     * Die DD ist optional. Ohne DD bleibt der Fallback wie bisher
     * `$this->_fld_id` bzw. `id`.
     *
     * @param string      $dd DD-Name für die Primärschlüssel-Auflösung
     * @param mixed       $where WHERE-Bedingung
     * @param int         $owner 1 = Owner-Filter aktiv
     *
     * @return string Sichere WHERE-Bedingung
     */
    public function normalize_where(string $dd, $where = '', int $owner = 0): string {
        if (!is_array($where)) {
            return $this->check_where($where, $owner, $dd);
        }

        $conditions = array();
        $server     = $this->get_dd_server($dd);

        if (isset($where['raw'])) {
            if (!empty($where['trusted'])) {
                return $this->check_where((string) $where['raw'], $owner, $dd);
            }

            dbx()->sys_msg('warning', 'db', $dd, 'raw where blocked', (string) $where['raw']);
            return $this->check_where('', $owner, $dd);
        }

        if (isset($where['search']) && is_array($where['search'])) {
            $search = $where['search'];
            $built  = $this->build_search_where(
                $dd,
                $search['value'] ?? '',
                is_array($search['like'] ?? null) ? $search['like'] : array(),
                is_array($search['equal'] ?? null) ? $search['equal'] : array(),
                (string) ($search['mode'] ?? 'starts_with')
            );

            if ($built !== '') {
                $conditions[] = $built;
            }
        }

        foreach ($where as $field => $value) {
            if (in_array($field, array('search', 'raw', 'trusted'), true)) {
                continue;
            }

            $field = trim((string) $field);

            if (!$this->is_fld_name($dd, $field)) {
                continue;
            }

            if (is_array($value)) {
                $mode = (string) ($value['mode'] ?? 'starts_with');

                if (isset($value['like'])) {
                    $like_value = $this->escape_like((string) $value['like'], $server);
                    $pattern   = $like_value . '%';

                    if ($mode === 'contains') {
                        $pattern = '%' . $like_value . '%';
                    } elseif ($mode === 'ends_with') {
                        $pattern = '%' . $like_value;
                    } elseif ($mode === 'exact') {
                        $pattern = $like_value;
                    }

                    $conditions[] = "$field LIKE '$pattern' ESCAPE '\\'";
                    continue;
                }

                if (array_key_exists('value', $value)) {
                    $value = $value['value'];
                } else {
                    continue;
                }
            }

            if ($value === null) {
                $conditions[] = "$field IS NULL";
                continue;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            if ($this->is_integer_value($value)) {
                $conditions[] = "$field = " . (int) $value;
            } else {
                $conditions[] = "$field = '" . $this->escape((string) $value, $server) . "'";
            }
        }

        return $this->check_where(implode(' AND ', $conditions), $owner, $dd);
    }

/**
     * Escaped einen String für die sichere Verwendung in einer SQL-Abfrage.
     *
     * Hinweis
     * -------
     * Diese Methode dient nur zum Escapen einzelner Strings.
     * Für INSERT/UPDATE wird weiterhin mit Prepared Statements gearbeitet.
     *
     * @param string     $string Zu escapender String
     * @param string|int $server Serverindex
     *
     * @return string Escapeter String
     */
    public function escape($string, $server) {
        if (!$this->connect_db_server($server)) {
            return str_replace("'", "''", $string);
        }

        return substr($this->db[$server]->quote($string), 1, -1);
    }

/**
     * Escaped einen User-Suchwert fuer SQL-LIKE.
     *
     * Der Wert wird zuerst als LIKE-Pattern neutralisiert (%/_/\) und danach
     * fuer den konkreten Datenbankserver quoted. Die Rueckgabe ist ohne
     * aeussere Quotes und wird von build_search_where() eingefasst.
     *
     * @param string $value User-Suchwert
     * @param string $server Datenbankserver
     *
     * @return string Escapeter LIKE-Wert
     */
    public function escape_like($value, $server) {
        $value = (string) $value;
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('%', '\%', $value);
        $value = str_replace('_', '\_', $value);

        return $this->escape($value, $server);
    }

/**
     * Baut eine sichere Such-WHERE fuer einfache Report-Suchen.
     *
     * - validiert den Suchtext mit `sqlsearch`
     * - validiert alle Feldnamen gegen die DD
     * - escaped LIKE- und Exact-Werte zentral
     *
     * @param string $dd Datenbeschreibung
     * @param mixed  $search User-Suchwert
     * @param array  $likeFields Felder fuer LIKE
     * @param array  $equalFields Felder fuer exakte Suche
     * @param string $mode starts_with|contains|ends_with|exact
     *
     * @return string SQL-WHERE oder leerer String
     */
    public function build_search_where(string $dd, $search, array $like_fields, array $equal_fields = array(), string $mode = 'starts_with'): string {
        $search = trim((string) $search);

        if ($search === '') {
            return '';
        }

        if (!$this->o_validator->validate($search, 'sqlsearch|max=128', 'search')) {
            dbx()->sys_msg('warning', 'db', $dd, 'invalid search', $search);
            return '';
        }

        $server = $this->get_dd_server($dd);

        if (!$server) {
            return '';
        }

        $like_value  = $this->escape_like($search, $server);
        $exact_value = $this->escape($search, $server);
        $conditions = array();

        foreach ($like_fields as $field) {
            $field = trim((string) $field);

            if (!$this->is_fld_name($dd, $field)) {
                continue;
            }

            $pattern = $like_value;

            if ($mode === 'contains') {
                $pattern = '%' . $like_value . '%';
            } elseif ($mode === 'ends_with') {
                $pattern = '%' . $like_value;
            } elseif ($mode !== 'exact') {
                $pattern = $like_value . '%';
            }

            $conditions[] = "$field LIKE '$pattern' ESCAPE '\\'";
        }

        foreach ($equal_fields as $field) {
            $field = trim((string) $field);

            if (!$this->is_fld_name($dd, $field)) {
                continue;
            }

            $conditions[] = "$field = '$exact_value'";
        }

        if (!count($conditions)) {
            return '';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }
}
