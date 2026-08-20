<?php

declare(strict_types=1);

$options = getopt('', array('summary:', 'resources:', 'details::', 'actor::'));
$summary = trim((string)($options['summary'] ?? ''));
$details = trim((string)($options['details'] ?? ''));
$actor = trim((string)($options['actor'] ?? 'Codex'));
$resources = array_values(array_unique(array_filter(array_map(
    static fn(string $resource): string => trim(str_replace('\\', '/', $resource)),
    preg_split('/[;\r\n]+/', (string)($options['resources'] ?? '')) ?: array()
))));

if ($summary === '' || mb_strlen($summary, 'UTF-8') > 255 || mb_strlen($details, 'UTF-8') < 3 || !$resources || $actor === '') {
    fwrite(STDERR, "Aufruf: php write-change-log.php --summary=\"...\" --resources=\"pfad1;pfad2\" [--details=\"...\"] [--actor=Codex]\n");
    exit(2);
}

try {
    $root = dirname(__DIR__, 4);
    $_SERVER['REQUEST_URI'] = '/dbxapp/?dbx_modul=dbxChangeLog_admin&dbx_run1=report';
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
    $_SERVER['REQUEST_METHOD'] = 'CLI';
    if (!defined('dbxSystem')) define('dbxSystem', 'dbxWebApp');
    if (!defined('dbxRunAsAdmin')) define('dbxRunAsAdmin', 1);
    require $root . '/dbx/vendor/autoload.php';
    require_once $root . '/dbx/include/dbxKernel.php';
    dbx()->set_system_var('dbx_activ_modul', 'dbxChangeLog_admin');
    dbx()->set_system_var('dbx_master_modul', 'dbxChangeLog_admin');
    dbx()->set_system_var('dbx_lng', 'de');
    $result = dbx()->get_include_obj('dbxChangeLogWriter', 'dbxChangeLog_admin')
        ->write($summary, $resources, $details, $actor);
    echo json_encode(array('ok' => 1, 'change_log' => $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(4);
}
