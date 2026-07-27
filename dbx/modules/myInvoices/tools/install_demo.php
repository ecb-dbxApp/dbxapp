<?php
/**
 * CLI-Runner für Schema und idempotente myInvoices-Demo-Daten.
 *
 * Aufruf:
 *   php dbx/modules/myInvoices/tools/install_demo.php
 *   php dbx/modules/myInvoices/tools/install_demo.php --schema-only
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Dieses Werkzeug darf nur per CLI laufen.\n");
    exit(1);
}

$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';

dbx()->set_system_var('dbx_activ_modul', 'myInvoices');
dbx()->set_system_var('dbx_activ_modul_id', 1);
dbx()->set_system_var('dbx_activ_action', 'install');
dbx()->set_modul_var('dbx_modul', 'myInvoices');
dbx()->set_modul_var('dbx_run1', 'install');

$schemaOnly = in_array('--schema-only', $argv, true);
$fixtures = dbx()->get_include_obj(
    'myInvoicesFixtures',
    'myInvoices'
);
$result = $fixtures->install(!$schemaOnly);

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
exit((int)($result['ok'] ?? 0) === 1 ? 0 : 1);
