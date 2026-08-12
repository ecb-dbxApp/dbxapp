<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
define('dbxSystem', 'dbxWebApp');

require_once $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$_SESSION['dbx']['tmp'][0]['dbx']['session_probe'] = 'must-not-leak';
dbx()->set_system_var('request_probe', 'request');
$assert(dbx()->get_system_var('request_probe') === 'request', 'Systemwert fehlt im RequestContext.');
$assert(dbx()->get_system_var('session_probe', 'clean') === 'clean', 'Alter Sessionzustand ist in den Request gelangt.');
$assert(!isset($_SESSION['dbx']['tmp'][0]['dbx']['request_probe']), 'Neuer Systemwert wurde in die Session geschrieben.');

dbx()->set_system_var('dbx_activ_modul_id', 7);
dbx()->set_system_var('dbx_activ_modul', 'probe');
dbx()->set_modul_var('value', 'isolated');
$assert(dbx()->get_modul_var('value', '') === 'isolated', 'Modulwert fehlt im RequestContext.');
$assert(!isset($_SESSION['dbx']['tmp'][7]['probe']['value']), 'Neuer Modulwert wurde in die Session geschrieben.');

$snapshot = dbx()->request_context()->snapshot();
dbx()->set_system_var('request_probe', 'nested');
dbx()->set_modul_var('value', 'nested', false);
dbx()->set_modul_var('nested_only', 'temporary', false);
dbx()->request_context()->restore($snapshot);
$assert(dbx()->get_system_var('request_probe') === 'request', 'System-Snapshot wurde nicht restauriert.');
$assert(dbx()->get_modul_var('value', '') === 'isolated', 'Modul-Snapshot wurde nicht restauriert.');
$assert(dbx()->get_modul_var('nested_only', '') === '', 'Verschachtelte Modulwerte sind nach außen gelangt.');

$db1 = dbx()->get_system_obj('dbxDB');
$db2 = dbx()->get_system_obj('dbxDB');
$form1 = dbx()->get_system_obj('dbxForm');
$form2 = dbx()->get_system_obj('dbxForm');
$report1 = dbx()->get_system_obj('dbxReport');
$report2 = dbx()->get_system_obj('dbxReport');
$assert($db1 === $db2, 'dbxDB muss requestweiter Singleton bleiben.');
$assert($form1 !== $form2, 'dbxForm-Builder dürfen keinen Zustand teilen.');
$assert($report1 !== $report2, 'dbxReport-Builder dürfen keinen Zustand teilen.');

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK RequestContext isoliert Laufzeitwerte und Service-Lebenszyklen.\n";
