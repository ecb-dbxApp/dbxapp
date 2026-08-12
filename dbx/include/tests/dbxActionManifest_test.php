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

$manifest = dbx()->get_system_obj('dbxActionManifest');
$actions = $manifest->module('dbxShop_admin');
$required = array('handler', 'methods', 'groups', 'mutation', 'response');
foreach ($actions as $name => $definition) {
    foreach ($required as $key) {
        if (!array_key_exists($key, $definition)) {
            fwrite(STDERR, "FAIL {$name}: {$key} fehlt.\n");
            exit(1);
        }
    }
}
if (count($actions) < 20 || ($actions['order_invoice_pdf']['response'] ?? '') !== 'file') {
    fwrite(STDERR, "FAIL Aktionsmanifest ist unvollständig.\n");
    exit(1);
}
echo 'OK deklaratives Shop-Aktionsmanifest: ' . count($actions) . " Routen.\n";
