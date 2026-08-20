<?php

/** Automatisch aus der stabilen Legacy-Fassade extrahierte interne Verantwortung. */
trait dbxDDIntrospectionTrait
{
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
    protected function map_db_type_to_dd_type(string $db_type, string $raw_type, string $length = ''): string
    {
        $db_type  = strtolower(trim($db_type));
        $raw_type = strtolower(trim($raw_type));
        $length  = trim($length);

        if (in_array($raw_type, ['bool', 'boolean'], true)) {
            return 'bool';
        }

        if (in_array($raw_type, ['tinyint', 'smallint', 'mediumint', 'bigint'], true)) {
            return $raw_type;
        }

        if (in_array($raw_type, ['int', 'integer', 'serial', 'number'], true)) {
            return 'int';
        }

        if (in_array($raw_type, ['bit'], true)) {
            return 'bit';
        }

        if (in_array($raw_type, ['date', 'time', 'year'], true)) {
            return $raw_type;
        }

        if (in_array($raw_type, ['datetime', 'timestamp'], true)) {
            return 'datetime';
        }

        if (in_array($raw_type, ['char', 'nchar'], true)) {
            return 'char';
        }

        if (in_array($raw_type, ['varchar', 'varchar2', 'nvarchar', 'string'], true)) {
            return 'varchar';
        }

        if (in_array($raw_type, ['tinytext', 'mediumtext', 'longtext'], true)) {
            return $raw_type;
        }

        if (in_array($raw_type, ['text', 'clob'], true)) {
            return 'text';
        }

        if (in_array($raw_type, ['decimal', 'numeric'], true)) {
            return 'decimal';
        }

        if (in_array($raw_type, ['float', 'real'], true)) {
            return 'float';
        }

        if (in_array($raw_type, ['double'], true)) {
            return 'double';
        }

        if (in_array($raw_type, ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'json', 'enum', 'set'], true)) {
            return $raw_type;
        }

        if ($db_type === 'sqlite') {
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
    public function get_db_fields($server, $table_name): array
    {
        $db_fields = [];

        if (!$this->get_table_exist($server, $table_name)) {
            return $db_fields;
        }

        $db_type = $this->get_db_type($server);

        switch ($db_type) {
            case 'mysql':
                $sql  = "SHOW COLUMNS FROM " . $this->quote_ident($server, $table_name);
                $rows = $this->raw_query($server, $sql);

                foreach ($rows as $row) {
                    $type_info = $this->parse_sql_type((string)($row['Type'] ?? ''));
                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['Field'] ?? ''),
                        $db_type,
                        (string)($type_info['type'] ?? 'varchar'),
                        (string)($type_info['length'] ?? ''),
                        ((string)($row['Null'] ?? 'YES') === 'YES') ? '1' : '0',
                        $row['Default'] ?? '',
                        (string)($row['Key'] ?? '')
                    );
                }
                break;

            case 'sqlite':
                $sql  = "PRAGMA table_info(" . $this->quote_ident($server, $table_name) . ")";
                $rows = $this->raw_query($server, $sql);

                foreach ($rows as $row) {
                    $type_info = $this->parse_sql_type((string)($row['type'] ?? ''));
                    $index    = ((int)($row['pk'] ?? 0) === 1) ? 'PRI' : '';

                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['name'] ?? ''),
                        $db_type,
                        (string)($type_info['type'] ?? 'text'),
                        (string)($type_info['length'] ?? ''),
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
                    WHERE table_name = " . $this->sql_quote($server, $table_name) . "
                    ORDER BY ordinal_position
                ";
                $rows = $this->raw_query($server, $sql);

                foreach ($rows as $row) {
                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['column_name'] ?? ''),
                        $db_type,
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
                    WHERE TABLE_NAME = " . $this->sql_quote($server, $table_name) . "
                    ORDER BY ORDINAL_POSITION
                ";
                $rows = $this->raw_query($server, $sql);

                foreach ($rows as $row) {
                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['COLUMN_NAME'] ?? ''),
                        $db_type,
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
                    WHERE table_name = UPPER(" . $this->sql_quote($server, $table_name) . ")
                    ORDER BY column_id
                ";
                $rows = $this->raw_query($server, $sql);

                foreach ($rows as $row) {
                    $db_fields[] = $this->build_dd_field_from_db_meta(
                        (string)($row['COLUMN_NAME'] ?? ''),
                        $db_type,
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
    public function get_db_indexes(string $server, string $table_name): array
    {
        $indexes = [];

        if (!$this->get_table_exist($server, $table_name)) {
            return $indexes;
        }

        $db_type = $this->get_db_type($server);

        switch ($db_type) {
            case 'mysql':
                $sql  = "SHOW INDEX FROM " . $this->quote_ident($server, $table_name);
                $rows = $this->raw_query($server, $sql);

                $grouped = [];
                foreach ($rows as $row) {
                    $key_name   = (string)($row['Key_name'] ?? '');
                    $col_name   = (string)($row['Column_name'] ?? '');
                    $non_unique = (int)($row['Non_unique'] ?? 1);

                    if (!isset($grouped[$key_name])) {
                        $grouped[$key_name] = [
                            'name'    => $key_name,
                            'type'    => ($key_name === 'PRIMARY') ? 'PRIMARY' : (($non_unique === 0) ? 'UNIQUE' : 'INDEX'),
                            'fields'  => [],
                            'unique'  => ($key_name === 'PRIMARY' || $non_unique === 0) ? '1' : '0',
                            'comment' => '',
                        ];
                    }

                    $grouped[$key_name]['fields'][] = $col_name;
                }

                foreach ($grouped as $idx) {
                    $idx['fields'] = implode(',', $idx['fields']);
                    $indexes[] = $idx;
                }
                break;

            case 'sqlite':
                $sql  = "PRAGMA index_list(" . $this->quote_ident($server, $table_name) . ")";
                $rows = $this->raw_query($server, $sql);

                foreach ($rows as $row) {
                    $name   = (string)($row['name'] ?? '');
                    $unique = ((int)($row['unique'] ?? 0) === 1) ? '1' : '0';
                    $origin = (string)($row['origin'] ?? '');

                    $sql2 = "PRAGMA index_info(" . $this->quote_ident($server, $name) . ")";
                    $cols = $this->raw_query($server, $sql2);

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
                    WHERE tablename = " . $this->sql_quote($server, $table_name);
                $rows = $this->raw_query($server, $sql);

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
        string $db_type,
        string $raw_type,
        string $length,
        string $is_null,
        mixed $default,
        string $index = ''
    ): array {
        $dd_type = $this->map_db_type_to_dd_type($db_type, $raw_type, $length);

        $field = [
            'name'        => $name,
            'type'        => $dd_type,
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
        $server_key = strtolower(trim($server));
        $table_key  = strtolower(trim($table));

        if ($server_key === '' || $table_key === '') {
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

            $dd_ref = $modul . '|' . $dd;
            $model = $this->get_dd_model($dd_ref);
            if (!$model) {
                continue;
            }

            $model_server = strtolower((string)($model['table']['server'] ?? ''));
            $model_table  = strtolower((string)($model['table']['table'] ?? ''));
            $server_match = ($model_server === $server_key);

            if (!$server_match && strpos($server_key, '|') !== false) {
                [$server_modul, $server_name] = array_pad(explode('|', $server_key, 2), 2, '');
                $server_match = ($server_modul === strtolower($modul))
                    && ($model_server === $server_name || $model_server === 'modul');
            }

            if ($server_match && $model_table === $table_key) {
                return [
                    'modul'  => $modul,
                    'dd'     => $dd,
                    'dd_ref' => $dd_ref,
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
    public function is_dd_field_compatible_with_db(array $dd_field, array $db_field, string $db_engine = ''): bool
    {
        $db_engine = strtolower(trim($db_engine));
        $dd_type = strtolower((string)($dd_field['type'] ?? ''));
        $db_type = strtolower((string)($db_field['type'] ?? ''));
        $dd_group = $this->schema_type_group($dd_type, (string)($dd_field['length'] ?? ''));
        $db_group = $this->schema_type_group($db_type, (string)($db_field['length'] ?? ''));

        if ($dd_type !== '' && $db_type !== '' && ($dd_type === $db_type || $this->is_semantic_type_match($dd_type, $db_type))) {
            return true;
        }

        if ($db_engine === 'sqlite') {
            if (in_array($dd_group, ['string', 'text', 'date', 'time', 'datetime', 'json', 'enum'], true)
                && in_array($db_group, ['string', 'text'], true)) {
                return true;
            }

            if (in_array($dd_group, ['integer', 'bool'], true) && in_array($db_group, ['integer', 'bool'], true)) {
                return true;
            }

            if (in_array($dd_group, ['decimal', 'float'], true) && in_array($db_group, ['decimal', 'float', 'integer'], true)) {
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
    protected function is_db_sync_field_match(string $db_engine, array $dd_field, array $db_field): bool
    {
        $db_engine = strtolower(trim($db_engine));
        $dd_type = strtolower((string)($dd_field['type'] ?? ''));
        $db_type = strtolower((string)($db_field['type'] ?? ''));

        if ($dd_type === '' || $db_type === '') {
            return false;
        }

        if ($db_engine === 'sqlite') {
            return $this->is_dd_field_compatible_with_db($dd_field, $db_field, $db_engine);
        }

        if ($dd_type === $db_type) {
            return true;
        }

        $dd_group = $this->schema_type_group($dd_type, (string)($dd_field['length'] ?? ''));
        $db_group = $this->schema_type_group($db_type, (string)($db_field['length'] ?? ''));

        if (in_array($dd_group, ['integer', 'bool'], true) && in_array($db_group, ['integer', 'bool'], true)) {
            return true;
        }

        if ($dd_group === 'decimal' && $db_group === 'decimal') {
            return true;
        }

        if ($dd_group === 'float' && in_array($db_group, ['float', 'decimal'], true)) {
            return true;
        }

        return false;
    }

/**
     * Führt ein vorhandenes DD-Feld mit einer DB-Felddefinition zusammen.
     *
     * @param array  $oldField Vorhandenes DD-Feld
     * @param array  $dbField DB-Feld
     * @param string $dbEngine Datenbanktyp
     *
     * @return array Gemergtes DD-Feld
     */
    public function merge_dd_field_with_db_field(array $old_field, array $db_field, string $db_engine = ''): array
    {
        $compatible = $this->is_dd_field_compatible_with_db($old_field, $db_field, $db_engine);
        $merged = $db_field;

        $preserve = $compatible
            ? ['type', 'index', 'length', 'default', 'label', 'rules', 'tooltip', 'errormsg', 'placeholder', 'convert', 'protect', 'group', 'mask', 'data', 'options', 'tpl', 'js']
            : ['label', 'tooltip', 'errormsg', 'placeholder', 'convert', 'protect', 'group', 'mask', 'data', 'js'];

        foreach ($preserve as $key) {
            if (isset($old_field[$key]) && $old_field[$key] !== '' && $old_field[$key] !== null) {
                $merged[$key] = $old_field[$key];
            }
        }

        $merged['name'] = $db_field['name'] ?? ($old_field['name'] ?? '');

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
}
