<?php
declare(strict_types=1);

$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp-docs/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp-docs/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';
require_once $base . '/dbx/modules/dbxDocs/include/dbxDocsContentProvision.class.php';

$force = in_array('--force', $argv ?? array(), true);
$db = dbx()->get_system_obj('dbxDB');
$dd = dbx()->get_system_obj('dbxDD');

$schema = array();
foreach (array('content_de', 'content_en', 'content_es') as $name) {
    $dd->sync_dd_to_db('dbx', $name, 'reset');
    $state = array();
    for ($step = 0; $step < 1000; $step++) {
        $state = $dd->sync_dd_to_db('dbx', $name, 'apply');
        if (in_array((string)($state['status'] ?? ''), array('finished', 'error', 'cancelled'), true)) {
            break;
        }
    }
    $schema[$name] = $state;
}

$failed = array_filter($schema, static fn(array $state): bool => ($state['status'] ?? '') !== 'finished');
if ($failed !== array()) {
    fwrite(STDERR, json_encode(array('ok' => false, 'schema' => $schema), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}

$service = new \dbx\dbxDocs\dbxDocsContentProvision($db, $base, $force);
$result = $service->run();
echo json_encode(array('schema' => $schema, 'content' => $result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(!empty($result['ok']) ? 0 : 1);
