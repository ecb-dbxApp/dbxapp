<?php

$root = dirname(__DIR__, 3);
chdir($root);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

if (!defined('dbxSystem')) {
    define('dbxSystem', 'dbxWebApp');
}
if (!defined('dbxRunAsAdmin')) {
    define('dbxRunAsAdmin', 1);
}

require_once $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';

$server = 'dbxContact|dbxDynamicReconnectTest.db3';
$database_file = $root . '/dbx/modules/dbxContact/db/dbxDynamicReconnectTest.db3';
if (!is_file($database_file)
    && file_put_contents($database_file, '') === false) {
    fwrite(STDERR, "FAIL: Isolierte Testdatenbank konnte nicht angelegt werden.\n");
    exit(1);
}
$db = dbx()->get_system_obj('dbxDB');
if (!is_object($db)) {
    fwrite(STDERR, "FAIL: dbxDB nicht verfuegbar.\n");
    exit(1);
}

if ($db->connect_db_server($server) !== 1) {
    fwrite(STDERR, "FAIL: Erste Verbindung zur dynamischen Modul-DB fehlgeschlagen.\n");
    exit(2);
}

$created = $db->exec(
    $server,
    'CREATE TABLE IF NOT EXISTS reconnect_probe (id INTEGER PRIMARY KEY)'
);
if ($created < 0) {
    fwrite(STDERR, "FAIL: Testtabelle konnte nicht über dbxDB angelegt werden.\n");
    exit(3);
}

if ($db->connect_db_server($server) !== 1) {
    fwrite(STDERR, "FAIL: Wiederverbindung zur dynamischen Modul-DB fehlgeschlagen.\n");
    exit(4);
}

$probe = $db->select_query(
    $server,
    "SELECT COUNT(*) AS table_count FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
);
if (!is_array($probe) || (int)($probe[0]['table_count'] ?? 0) < 1) {
    fwrite(STDERR, "FAIL: Abfrage nach der Wiederverbindung fehlgeschlagen.\n");
    exit(5);
}

echo "OK dbxDB dynamic reconnect\n";

// Die isolierte Testdatenbank darf nach einem Einzeltest weder den
// Quellbaum noch den späteren Release-Hygiene-Test verunreinigen.
unset($db->db[$server]);
$db->pdo = null;
foreach (array($database_file, $database_file . '-wal', $database_file . '-shm') as $test_file) {
    if (is_file($test_file) && !unlink($test_file)) {
        fwrite(STDERR, "FAIL: Isolierte Testdatenbank konnte nicht entfernt werden.\n");
        exit(6);
    }
}
