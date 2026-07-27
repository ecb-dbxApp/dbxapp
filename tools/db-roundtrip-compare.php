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

$sourceServer = (string)($argv[1] ?? '');
$targetServer = (string)($argv[2] ?? '');
$tablesArg = (string)($argv[3] ?? '');
$tables = array_values(array_filter(array_map('trim', explode(',', $tablesArg))));

if ($sourceServer === '' || $targetServer === '' || !$tables) {
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

foreach ([$sourceServer, $targetServer] as $server) {
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
    $preferredSchema = $dd->get_preferred_table_schema($server, $table);
    $preferredFields = is_array($preferredSchema['fields'] ?? null)
        ? $preferredSchema['fields']
        : [];
    $columnNames = array_values(array_filter(array_map(
        static fn(array $field): string => (string)($field['name'] ?? ''),
        is_array($fields) ? $fields : []
    )));
    $fieldTypes = [];
    foreach (is_array($fields) ? $fields : [] as $field) {
        $name = (string)($field['name'] ?? '');
        if ($name !== '') {
            $fieldTypes[$name] = strtolower((string)($field['type'] ?? ''));
        }
    }
    foreach ($preferredFields as $field) {
        $name = (string)($field['name'] ?? '');
        if ($name !== '') {
            $fieldTypes[$name] = strtolower((string)($field['type'] ?? ($fieldTypes[$name] ?? '')));
        }
    }

    $orderColumn = in_array('id', $columnNames, true) ? 'id' : ($columnNames[0] ?? '');
    $sql = 'SELECT * FROM ' . dbx_roundtrip_quote_table($db, $server, $table);
    if ($orderColumn !== '') {
        $sql .= ' ORDER BY ' . dbx_roundtrip_quote_table($db, $server, $orderColumn);
    }
    $rows = $db->select_query($server, $sql);
    $rows = is_array($rows) ? $rows : [];

    foreach ($rows as &$row) {
        $normalizedRow = [];
        foreach ($columnNames as $columnName) {
            $value = array_key_exists($columnName, $row) ? $row[$columnName] : null;
            $normalizedRow[$columnName] = $value === null ? null : (string)$value;
        }
        $row = $normalizedRow;
    }
    unset($row);

    $dataJson = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return [
        'fields' => dbx_roundtrip_normalize(is_array($fields) ? $fields : []),
        'indexes' => dbx_roundtrip_normalize(is_array($indexes) ? $indexes : []),
        'columns' => $columnNames,
        'field_types' => $fieldTypes,
        'rows' => count($rows),
        'data_sha256' => hash('sha256', (string)$dataJson),
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

function dbx_roundtrip_semantic_value($value, string $fieldType): mixed
{
    $temporalTypes = ['date', 'time', 'datetime', 'timestamp', 'year'];
    if (!in_array(strtolower($fieldType), $temporalTypes, true)) {
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

function dbx_roundtrip_semantic_data(array $snapshot, array $sharedTypes = []): array
{
    $rows = $snapshot['data'] ?? [];
    foreach ($rows as &$row) {
        foreach ($row as $column => &$value) {
            $fieldType = (string)($sharedTypes[$column]
                ?? ($snapshot['field_types'][$column] ?? ''));
            $value = dbx_roundtrip_semantic_value($value, $fieldType);
        }
        unset($value);
    }
    unset($row);
    return $rows;
}

$sourceTables = array_column($db->get_db_tables($sourceServer), 'name');
$targetTables = array_column($db->get_db_tables($targetServer), 'name');
sort($sourceTables);
sort($targetTables);

$success = true;
$report = [
    'source_server' => $sourceServer,
    'target_server' => $targetServer,
    'source_integrity' => dbx_roundtrip_integrity($db, $sourceServer),
    'target_integrity' => dbx_roundtrip_integrity($db, $targetServer),
    'requested_tables' => $tables,
    'source_tables' => $sourceTables,
    'target_tables' => $targetTables,
    'tables' => [],
];

if ($report['source_integrity'] === 'failed' || $report['target_integrity'] === 'failed') {
    $success = false;
}

foreach ($tables as $table) {
    $sourceExists = in_array($table, $sourceTables, true);
    $targetExists = in_array($table, $targetTables, true);
    $entry = [
        'source_exists' => $sourceExists,
        'target_exists' => $targetExists,
    ];

    if (!$sourceExists || !$targetExists) {
        $entry['equal'] = false;
        $success = false;
        $report['tables'][$table] = $entry;
        continue;
    }

    $source = dbx_roundtrip_table_snapshot($db, $dd, $sourceServer, $table);
    $target = dbx_roundtrip_table_snapshot($db, $dd, $targetServer, $table);
    $entry['source_rows'] = $source['rows'];
    $entry['target_rows'] = $target['rows'];
    $entry['source_data_sha256'] = $source['data_sha256'];
    $entry['target_data_sha256'] = $target['data_sha256'];
    $entry['columns_equal'] = $source['columns'] === $target['columns'];
    $entry['fields_equal'] = $source['fields'] === $target['fields'];
    $entry['indexes_equal'] = $source['indexes'] === $target['indexes'];
    $entry['data_equal'] = $source['data'] === $target['data'];
    $sharedTypes = array_merge($source['field_types'], $target['field_types']);
    $sourceSemanticData = dbx_roundtrip_semantic_data($source, $sharedTypes);
    $targetSemanticData = dbx_roundtrip_semantic_data($target, $sharedTypes);
    $entry['data_semantic_equal'] = $sourceSemanticData === $targetSemanticData;
    $entry['source_semantic_sha256'] = hash(
        'sha256',
        json_encode($sourceSemanticData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $entry['target_semantic_sha256'] = hash(
        'sha256',
        json_encode($targetSemanticData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    if (!$entry['data_equal']) {
        $differences = [];
        $rowCount = max(count($source['data']), count($target['data']));
        for ($rowNo = 0; $rowNo < $rowCount; $rowNo++) {
            $sourceRow = $source['data'][$rowNo] ?? [];
            $targetRow = $target['data'][$rowNo] ?? [];
            foreach (array_unique(array_merge(array_keys($sourceRow), array_keys($targetRow))) as $column) {
                $sourceValue = array_key_exists($column, $sourceRow) ? $sourceRow[$column] : null;
                $targetValue = array_key_exists($column, $targetRow) ? $targetRow[$column] : null;
                if ($sourceValue === $targetValue) {
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
                if ($sourceValue === '' && $targetValue === null) {
                    $differences[$column]['empty_to_null']++;
                } elseif ($sourceValue === null && $targetValue === '') {
                    $differences[$column]['null_to_empty']++;
                }
                $shape = dbx_roundtrip_value_shape($sourceValue)
                    . '_to_'
                    . dbx_roundtrip_value_shape($targetValue);
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
