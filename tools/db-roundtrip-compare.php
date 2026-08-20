<?php
/**
 * Vergleicht zwei konfigurierte DB-Server logisch ueber dbxDB/dbxDD.
 *
 * Aufruf:
 *   php tools/db-roundtrip-compare.php <quelle> <ziel> <tabelle[,tabelle...]>
 *
 * Verglichen werden Tabellenbestand, Integritaet (SQLite), Felddefinitionen,
 * Indizes und kanonisch sortierte Nutzdaten. Unterschiedliche Dateihashes
 * einer neu aufgebauten SQLite-Datei sind erwartbar und deshalb kein
 * fachliches Vergleichskriterium.
 */
$base = dirname(__DIR__);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
$_SERVER['SCRIPT_FILENAME'] = $base . '/index.php';
$_SERVER['DOCUMENT_ROOT'] = dirname($base);

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

$source_server = (string)($argv[1] ?? '');
$target_server = (string)($argv[2] ?? '');
$tables_arg = (string)($argv[3] ?? '');
$tables = array_values(array_filter(array_map('trim', explode(',', $tables_arg))));

if ($source_server === '' || $target_server === '' || !$tables) {
    fwrite(STDERR, "Aufruf: php tools/db-roundtrip-compare.php <quelle> <ziel> <tabelle[,tabelle...]>\n");
    exit(2);
}

foreach ($tables as $table) {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
        fwrite(STDERR, "Ungueltiger Tabellenname: {$table}\n");
        exit(2);
    }
}

$db = dbx()->get_system_obj('dbxDB');
$dd = dbx()->get_system_obj('dbxDD');
if (!is_object($db) || !is_object($dd)) {
    fwrite(STDERR, "dbxDB/dbxDD nicht verfuegbar.\n");
    exit(2);
}

foreach ([$source_server, $target_server] as $server) {
    if ($db->connect_db_server($server) !== 1) {
        fwrite(STDERR, "DB-Server nicht erreichbar: {$server}\n");
        exit(2);
    }
}

/**
 * Entfernt rein darstellungsbedingte Array-Reihenfolgen.
 */
function dbx_roundtrip_normalize(array $items): array
{
    foreach ($items as &$item) {
        if (is_array($item)) {
            ksort($item);
        }
    }
    unset($item);

    usort($items, static function (array $left, array $right): int {
        return strcmp(
            json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    });

    return $items;
}

function dbx_roundtrip_quote_table($db, string $server, string $table): string
{
    return $db->get_db_type($server) === 'mysql'
        ? '`' . $table . '`'
        : '"' . $table . '"';
}

function dbx_roundtrip_table_snapshot($db, $dd, string $server, string $table): array
{
    $fields = $dd->get_db_fields($server, $table);
    $indexes = $dd->get_db_indexes($server, $table);
    $preferred_schema = $dd->get_preferred_table_schema($server, $table);
    $preferred_fields = is_array($preferred_schema['fields'] ?? null)
        ? $preferred_schema['fields']
        : [];
    $column_names = array_values(array_filter(array_map(
        static fn(array $field): string => (string)($field['name'] ?? ''),
        is_array($fields) ? $fields : []
    )));
    $field_types = [];
    foreach (is_array($fields) ? $fields : [] as $field) {
        $name = (string)($field['name'] ?? '');
        if ($name !== '') {
            $field_types[$name] = strtolower((string)($field['type'] ?? ''));
        }
    }
    foreach ($preferred_fields as $field) {
        $name = (string)($field['name'] ?? '');
        if ($name !== '') {
            $field_types[$name] = strtolower((string)($field['type'] ?? ($field_types[$name] ?? '')));
        }
    }

    $order_column = in_array('id', $column_names, true) ? 'id' : ($column_names[0] ?? '');
    $sql = 'SELECT * FROM ' . dbx_roundtrip_quote_table($db, $server, $table);
    if ($order_column !== '') {
        $sql .= ' ORDER BY ' . dbx_roundtrip_quote_table($db, $server, $order_column);
    }
    $rows = $db->select_query($server, $sql);
    $rows = is_array($rows) ? $rows : [];

    foreach ($rows as &$row) {
        $normalized_row = [];
        foreach ($column_names as $column_name) {
            $value = array_key_exists($column_name, $row) ? $row[$column_name] : null;
            $normalized_row[$column_name] = $value === null ? null : (string)$value;
        }
        $row = $normalized_row;
    }
    unset($row);

    $data_json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return [
        'fields' => dbx_roundtrip_normalize(is_array($fields) ? $fields : []),
        'indexes' => dbx_roundtrip_normalize(is_array($indexes) ? $indexes : []),
        'columns' => $column_names,
        'field_types' => $field_types,
        'rows' => count($rows),
        'data_sha256' => hash('sha256', (string)$data_json),
        'data' => $rows,
    ];
}

function dbx_roundtrip_integrity($db, string $server): string
{
    if ($db->get_db_type($server) !== 'sqlite') {
        return 'not-applicable';
    }

    $result = $db->select_query($server, 'PRAGMA integrity_check');
    if (!is_array($result) || !$result) {
        return 'failed';
    }

    return strtolower((string)reset($result[0]));
}

function dbx_roundtrip_value_shape($value): string
{
    if ($value === null) {
        return 'null';
    }
    if ($value === '') {
        return 'empty';
    }
    if (preg_match('/^-?[0-9]+$/', (string)$value)) {
        return 'integer';
    }
    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', (string)$value)) {
        return 'datetime';
    }
    return 'string';
}

function dbx_roundtrip_semantic_value($value, string $field_type): mixed
{
    $temporal_types = ['date', 'time', 'datetime', 'timestamp', 'year'];
    if (!in_array(strtolower($field_type), $temporal_types, true)) {
        return $value;
    }
    if ($value === null || $value === '') {
        return null;
    }

    $value = (string)$value;
    if (preg_match(
        '/^([0-9]{4}-[0-9]{2}-[0-9]{2}(?: [0-9]{2}:[0-9]{2}:[0-9]{2})?)(?:\.([0-9]{1,6}))?$/',
        $value,
        $match
    )) {
        $fraction = rtrim((string)($match[2] ?? ''), '0');
        return $match[1] . ($fraction !== '' ? '.' . $fraction : '');
    }

    return $value;
}

function dbx_roundtrip_semantic_data(array $snapshot, array $shared_types = []): array
{
    $rows = $snapshot['data'] ?? [];
    foreach ($rows as &$row) {
        foreach ($row as $column => &$value) {
            $field_type = (string)($shared_types[$column]
                ?? ($snapshot['field_types'][$column] ?? ''));
            $value = dbx_roundtrip_semantic_value($value, $field_type);
        }
        unset($value);
    }
    unset($row);
    return $rows;
}

$source_tables = array_column($db->get_db_tables($source_server), 'name');
$target_tables = array_column($db->get_db_tables($target_server), 'name');
sort($source_tables);
sort($target_tables);

$success = true;
$report = [
    'source_server' => $source_server,
    'target_server' => $target_server,
    'source_integrity' => dbx_roundtrip_integrity($db, $source_server),
    'target_integrity' => dbx_roundtrip_integrity($db, $target_server),
    'requested_tables' => $tables,
    'source_tables' => $source_tables,
    'target_tables' => $target_tables,
    'tables' => [],
];

if ($report['source_integrity'] === 'failed' || $report['target_integrity'] === 'failed') {
    $success = false;
}

foreach ($tables as $table) {
    $source_exists = in_array($table, $source_tables, true);
    $target_exists = in_array($table, $target_tables, true);
    $entry = [
        'source_exists' => $source_exists,
        'target_exists' => $target_exists,
    ];

    if (!$source_exists || !$target_exists) {
        $entry['equal'] = false;
        $success = false;
        $report['tables'][$table] = $entry;
        continue;
    }

    $source = dbx_roundtrip_table_snapshot($db, $dd, $source_server, $table);
    $target = dbx_roundtrip_table_snapshot($db, $dd, $target_server, $table);
    $entry['source_rows'] = $source['rows'];
    $entry['target_rows'] = $target['rows'];
    $entry['source_data_sha256'] = $source['data_sha256'];
    $entry['target_data_sha256'] = $target['data_sha256'];
    $entry['columns_equal'] = $source['columns'] === $target['columns'];
    $entry['fields_equal'] = $source['fields'] === $target['fields'];
    $entry['indexes_equal'] = $source['indexes'] === $target['indexes'];
    $entry['data_equal'] = $source['data'] === $target['data'];
    $shared_types = array_merge($source['field_types'], $target['field_types']);
    $source_semantic_data = dbx_roundtrip_semantic_data($source, $shared_types);
    $target_semantic_data = dbx_roundtrip_semantic_data($target, $shared_types);
    $entry['data_semantic_equal'] = $source_semantic_data === $target_semantic_data;
    $entry['source_semantic_sha256'] = hash(
        'sha256',
        json_encode($source_semantic_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $entry['target_semantic_sha256'] = hash(
        'sha256',
        json_encode($target_semantic_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    if (!$entry['data_equal']) {
        $differences = [];
        $row_count = max(count($source['data']), count($target['data']));
        for ($row_no = 0; $row_no < $row_count; $row_no++) {
            $source_row = $source['data'][$row_no] ?? [];
            $target_row = $target['data'][$row_no] ?? [];
            foreach (array_unique(array_merge(array_keys($source_row), array_keys($target_row))) as $column) {
                $source_value = array_key_exists($column, $source_row) ? $source_row[$column] : null;
                $target_value = array_key_exists($column, $target_row) ? $target_row[$column] : null;
                if ($source_value === $target_value) {
                    continue;
                }
                if (!isset($differences[$column])) {
                    $differences[$column] = [
                        'count' => 0,
                        'empty_to_null' => 0,
                        'null_to_empty' => 0,
                        'shapes' => [],
                    ];
                }
                $differences[$column]['count']++;
                if ($source_value === '' && $target_value === null) {
                    $differences[$column]['empty_to_null']++;
                } elseif ($source_value === null && $target_value === '') {
                    $differences[$column]['null_to_empty']++;
                }
                $shape = dbx_roundtrip_value_shape($source_value)
                    . '_to_'
                    . dbx_roundtrip_value_shape($target_value);
                $differences[$column]['shapes'][$shape] = ($differences[$column]['shapes'][$shape] ?? 0) + 1;
            }
        }
        foreach ($differences as &$difference) {
            ksort($difference['shapes']);
        }
        unset($difference);
        ksort($differences);
        $entry['data_differences'] = $differences;
    }
    $entry['equal'] = $entry['columns_equal'] && $entry['data_semantic_equal'];

    if (!$entry['equal']) {
        $success = false;
    }
    $report['tables'][$table] = $entry;
}

$report['equal'] = $success;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit($success ? 0 : 1);
