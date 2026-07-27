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

$fixture = $root . '/dbx/modules/dbxContact/db/dbxContact.db3';
$fixtureCreated = false;
$runtimeDb = null;
if (!is_file($fixture)) {
    if (!extension_loaded('pdo_sqlite')) {
        fwrite(STDERR, "FAIL: pdo_sqlite fehlt für die isolierte Test-Fixture.\n");
        exit(1);
    }
    $fixtureDir = dirname($fixture);
    if (!is_dir($fixtureDir) && !mkdir($fixtureDir, 0775, true) && !is_dir($fixtureDir)) {
        fwrite(STDERR, "FAIL: Fixture-Verzeichnis konnte nicht angelegt werden.\n");
        exit(1);
    }
    // Ausschließlich Test-Fixture; produktiver Zugriff bleibt in dbxDB.
    $fixtureDb = new PDO('sqlite:' . $fixture);
    $fixtureDb->exec('CREATE TABLE ci_probe (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
    $fixtureDb = null;
    $fixtureCreated = true;
}

register_shutdown_function(static function () use ($fixture, $fixtureCreated, &$runtimeDb): void {
    if (!$fixtureCreated) {
        return;
    }
    if (is_object($runtimeDb)) {
        foreach ($runtimeDb->db as $server => $connection) {
            $runtimeDb->db[$server] = null;
        }
        $runtimeDb->pdo = null;
    }
    foreach (array($fixture, $fixture . '-wal', $fixture . '-shm', $fixture . '-journal') as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
});

require_once $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';

$server = 'dbxContact|dbxContact.db3';
$db = dbx()->get_system_obj('dbxDB');
if (!is_object($db)) {
    fwrite(STDERR, "FAIL: dbxDB nicht verfuegbar.\n");
    exit(1);
}
$runtimeDb = $db;

if ($db->connect_db_server($server) !== 1) {
    fwrite(STDERR, "FAIL: Erste Verbindung zur dynamischen Modul-DB fehlgeschlagen.\n");
    exit(2);
}

if ($db->connect_db_server($server) !== 1) {
    fwrite(STDERR, "FAIL: Wiederverbindung zur dynamischen Modul-DB fehlgeschlagen.\n");
    exit(3);
}

$probe = $db->select_query(
    $server,
    "SELECT COUNT(*) AS table_count FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
);
if (!is_array($probe) || (int)($probe[0]['table_count'] ?? 0) < 1) {
    fwrite(STDERR, "FAIL: Abfrage nach der Wiederverbindung fehlgeschlagen.\n");
    exit(4);
}

echo "OK dbxDB dynamic reconnect\n";
