<?php
declare(strict_types=1);

/** Vertrag: DB-Performance wird zentral, sparsam und ohne Parameterwerte erfasst. */

$base = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $relative) use ($base): string {
    $file = $base . '/' . ltrim($relative, '/');
    return is_file($file) ? (string)file_get_contents($file) : '';
};

$db = dbx_test_module_source_bundle($base . '/include/dbxDB.class.php');
$api = dbx_test_module_source_bundle($base . '/include/dbxApi.php');
$pipeline = $read('include/dbxRequestPipeline.class.php');
$timer = $read('modules/dbx/include/dbxPerformanceTimer.class.php');
$request_dd = $read('modules/dbx/dd/dbxPerformanceRequest.dd.php');
$timer_dd = $read('modules/dbx/dd/dbxPerformanceTimer.dd.php');
$config = $read('modules/dbx/cfg/config.php');
$config_dd = $read('modules/dbx/cfg/config.dd.php');
$dashboard = dbx_test_module_source_bundle($base . '/modules/dbxAdmin/include/dbxDashboard.class.php');

$assert(
    str_contains($db, 'private function record_performance_query(')
        && str_contains($db, 'public function performance_query_snapshot(): array')
        && substr_count($db, '$this->record_performance_query(') >= 8,
    'dbxDB erfasst nicht alle zentralen Query-, Exec-, Insert- und Update-Pfade.'
);
$assert(
    str_contains($db, 'normalize_performance_sql(string $sql)')
        && str_contains($db, "preg_replace(\"/'(?:''|\\\\\\\\.|[^'])*'/s\", '?'")
        && str_contains($db, "hash('sha256'")
        && !str_contains($db, "'params'        =>")
        && !str_contains($db, "'params' => \$params"),
    'Query-Fingerprints sind nicht normalisiert oder koennten Parameterwerte speichern.'
);
$assert(
    str_contains($db, "dbx_performance_timer_store")
        && str_contains($timer, "dbx()->set_system_var('dbx_performance_timer_store', 1)")
        && str_contains($timer, '$query_snapshot = method_exists($db, \'performance_query_snapshot\')')
        && strpos($timer, '$query_snapshot =') < strpos($timer, "dbx()->set_system_var('dbx_performance_timer_store', 1)"),
    'Die Persistierung ist nicht gegen rekursive Eigenmessung geschuetzt.'
);
$assert(
    !str_contains($timer, "if ((int) dbx()->get_system_var('dbx_ajax'")
        && !str_contains($timer, "if ((int) dbx()->get_request_var('dbx_sync'")
        && str_contains($pipeline, '$runtime->store_performance_timer();')
        && !str_contains($api, "if (\$sync_request && (int)\$this->get_system_var('dbx_ajax'")
        && str_contains($timer, "private const SCHEMA_MARKER_VERSION = 'v2-contract2'")
        && str_contains($timer, 'public function ensure_schema(): bool')
        && str_contains($timer, 'PRAGMA table_info(')
        && str_contains($timer, 'idx_dbx_performance_timer_fingerprint')
        && str_contains($timer, 'private function query_timers(array $snapshot): array'),
    'Ajax-/Sync-Requests oder Query-Details werden nicht konsistent erfasst.'
);

foreach (array(
    'query_count',
    'query_unique_count',
    'query_duplicate_count',
    'slow_query_count',
    'failed_query_count',
    'query_time_ms',
) as $field) {
    $assert(str_contains($request_dd, "array('" . $field . "'"), 'Request-DD-Feld fehlt: ' . $field);
}
foreach (array('fingerprint', 'query_count', 'duplicate_count', 'max_time_ms', 'failure_count') as $field) {
    $assert(str_contains($timer_dd, "array('" . $field . "'"), 'Timer-DD-Feld fehlt: ' . $field);
}
$assert(
    str_contains($timer_dd, 'idx_dbx_performance_timer_fingerprint')
        && str_contains($timer_dd, 'idx_dbx_performance_timer_request_section')
        && str_contains($dashboard, "AND d.section = 'db-total'")
        && str_contains($config, "performance_timer_slow_query_ms")
        && str_contains($config_dd, "'performance_timer_slow_query_ms'"),
    'Fingerprint-Index oder konfigurierbare Slow-Query-Schwelle fehlt.'
);
$assert(
    str_contains($dashboard, 'AVG(CASE WHEN query_count > 0 THEN query_count END) AS avg_query_count')
        && str_contains($dashboard, 'AVG(CASE WHEN query_count > 0 THEN query_duplicate_count END) AS avg_query_duplicate_count')
        && str_contains($dashboard, 'AVG(CASE WHEN r.query_count > 0 THEN r.query_count END) AS avg_query_count')
        && str_contains($dashboard, "'avg_query_count' =>")
        && str_contains($dashboard, "'avg_duplicate_count' =>")
        && str_contains($dashboard, "'bi-database-check'"),
    'Das Admin-Dashboard wertet Query-Anzahl und Duplikate nicht in der aktiven Modulansicht aus.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK central query profiling is private, indexed and visible in the dashboard.\n";
