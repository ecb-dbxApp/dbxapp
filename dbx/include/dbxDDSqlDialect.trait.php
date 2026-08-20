<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDDSqlDialectTrait
{
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
        $db_type = $this->get_db_type($server);

        return match ($db_type) {
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
    protected function map_dd_type_to_sql_type(string $db_type, string $type, string $length = ''): string
    {
        $type = strtolower(trim($type));
        $len  = trim($length);

        switch ($db_type) {
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
    protected function build_default_sql(string $server, mixed $default, string $dd_type): string
    {
        $default = (string)$default;
        $u = strtoupper($default);

        if ($u === 'CURRENT_TIMESTAMP') {
            return 'DEFAULT CURRENT_TIMESTAMP';
        }

        $numeric_types = [
            'int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'bit',
            'decimal', 'numeric', 'float', 'double', 'real',
        ];

        if (in_array(strtolower($dd_type), $numeric_types, true) && is_numeric($default)) {
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
        $db_type  = $this->get_db_type($server);
        $name    = $field['name']    ?? '';
        $type    = strtolower((string)($field['type'] ?? 'varchar'));
        $length  = (string)($field['length'] ?? '');
        $index   = strtoupper((string)($field['index'] ?? ''));
        $default = $field['default'] ?? '';

        $sql_type = $this->map_dd_type_to_sql_type($db_type, $type, $length);
        $col     = $this->quote_ident($server, $name) . ' ' . $sql_type;

        if ($index === 'PRI' && strtolower($name) === 'id') {
            switch ($db_type) {
                case 'sqlite':
                    return $this->quote_ident($server, $name) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
                case 'mysql':
                case 'cubrid':
                    return $this->quote_ident($server, $name) . ' ' . $sql_type . ' AUTO_INCREMENT PRIMARY KEY';
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
        $id_field = array(
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
                $id_field = array_merge($field, $id_field);
                continue;
            }

            if (strtoupper((string)($field['index'] ?? '')) === 'PRI') {
                $field['index'] = '';
            }

            $normalized[] = $field;
        }

        array_unshift($normalized, $id_field);
        return $normalized;
    }

/**
     * Liefert, ob der Treiber die Auto-ID zusammen mit PRIMARY KEY direkt an
     * der Spalte definiert.
     */
    protected function uses_inline_auto_increment_id(string $db_type): bool
    {
        return in_array($db_type, array(
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
        $db_type = $this->get_db_type($server);

        $parts = [];
        $primary_fields = [];

        foreach ($fields as $field) {
            $parts[] = $this->build_sql_column_from_dd($server, $field);
            if (strtoupper((string)($field['index'] ?? '')) === 'PRI') {
                $is_inline_primary = strtolower((string)($field['name'] ?? '')) === 'id'
                    && $this->uses_inline_auto_increment_id($db_type);
                if (!$is_inline_primary) {
                    $primary_fields[] = $field['name'];
                }
            }
        }

        if ($primary_fields) {
            $quoted = [];
            foreach ($primary_fields as $fld) {
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

        $quoted_fields = [];
        foreach ($fields as $fld) {
            $quoted_fields[] = $this->quote_ident($server, $fld);
        }

        $sql = '';
        if ($type === 'UNIQUE') {
            $sql = 'CREATE UNIQUE INDEX ' . $this->quote_ident($server, $name)
                 . ' ON ' . $this->quote_ident($server, $table)
                 . ' (' . implode(',', $quoted_fields) . ')';
        } elseif ($type !== 'PRIMARY') {
            $sql = 'CREATE INDEX ' . $this->quote_ident($server, $name)
                 . ' ON ' . $this->quote_ident($server, $table)
                 . ' (' . implode(',', $quoted_fields) . ')';
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
        // file_exists()/is_file() koennen den vorherigen Negativtreffer bis
        // zur unmittelbar folgenden Tabellenpruefung cachen. Ohne gezielte
        // Invalidierung wird eine gerade erzeugte SQLite-Datei einmalig als
        // fehlend protokolliert.
        clearstatcache(true, $file);

        $host_rel = dbx()->config_path_store($dir, true);

        $_SESSION['dbx']['config']['dbx']['db'][$server] = [
            'type'   => 'sqlite',
            'host'   => $host_rel,
            'dbname' => basename($file),
            'user'   => '',
            'pass'   => '',
            'port'   => ''
        ];

        if (!isset($this->database->db[$server]) && !$this->database->db_connect($server, 'sqlite', $host_rel, basename($file))) {
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

        // Eine noch nicht vorhandene Tabelle ist hier der erwartete
        // Ausgangszustand und darf keine produktive SysMsg erzeugen.
        if ($this->get_table_exist($server, $table, false)) {
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

        $db_type = $this->get_db_type($server);
        $parts = [];
        $primary_fields = [];

        foreach ($fields as $field) {
            if (empty($field['name'])) {
                continue;
            }

            $parts[] = $this->build_sql_column_from_dd($server, $field);

            if (strtoupper((string)($field['index'] ?? '')) === 'PRI') {
                $is_inline_primary = strtolower((string)($field['name'] ?? '')) === 'id'
                    && $this->uses_inline_auto_increment_id($db_type);
                if (!$is_inline_primary) {
                    $primary_fields[] = $field['name'];
                }
            }
        }

        if (!$parts) {
            return 0;
        }

        if ($primary_fields) {
            $quoted = [];
            foreach ($primary_fields as $fld) {
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

        $db_type = $this->get_db_type($server);
        $q_table = $this->quote_ident($server, $table);
        $ok = 0;

        switch ($db_type) {
            case 'mysql':
                $ok = $this->exec_query($server, 'TRUNCATE TABLE ' . $q_table);
                if ($ok) {
                    $this->exec_query($server, 'ALTER TABLE ' . $q_table . ' AUTO_INCREMENT = 1');
                }
                break;

            case 'sqlite':
                $ok = $this->exec_query($server, 'DELETE FROM ' . $q_table);
                if ($ok && $this->sqlite_sequence_exists($server)) {
                    $this->exec_query($server, 'DELETE FROM sqlite_sequence WHERE name=' . $this->sql_quote($server, $table));
                }
                break;

            case 'pgsql':
                $ok = $this->exec_query($server, 'TRUNCATE TABLE ' . $q_table . ' RESTART IDENTITY');
                break;

            default:
                $ok = $this->exec_query($server, 'DELETE FROM ' . $q_table);
                break;
        }

        return $ok ? 1 : 0;
    }
}
