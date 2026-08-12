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

$db = dbx()->get_system_obj('dbxDB');
$dd = dbx()->get_system_obj('dbxDD');
if ($dd instanceof dbxDB) {
    fwrite(STDERR, "FAIL dbxDD erbt weiterhin dbxDB.\n");
    exit(1);
}
if ($dd->database() !== $db) {
    fwrite(STDERR, "FAIL dbxDD verwendet nicht den zentralen dbxDB-Service.\n");
    exit(1);
}
$dd->db['composition_probe'] = 'shared';
if (($db->db['composition_probe'] ?? '') !== 'shared') {
    fwrite(STDERR, "FAIL Verbindungspool wird nicht geteilt.\n");
    exit(1);
}
unset($dd->db['composition_probe']);
$model = $dd->load_dd('dbx|dbxUser');
if (!is_array($model)
    || (int)($model['dd_status'] ?? 0) !== 1
    || $dd->get_dd_table('dbx|dbxUser') !== 'dbx_user') {
    fwrite(STDERR, "FAIL delegiertes DD-Laden.\n");
    exit(1);
}
echo "OK dbxDD komponiert dbxDB und teilt den Verbindungspool.\n";
