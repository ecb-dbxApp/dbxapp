<?php
/**
 * Read-only: gibt eine CMS-Seite (eine Sprache) vollstaendig als JSON aus.
 * Aufruf: php dbxki_get_page.php <lng> <id>
 */
declare(strict_types=1);

$base = dirname(__DIR__, 4);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';
require_once $base . '/dbx/modules/dbxContent/include/dbxContent_bootstrap_sync.php';

use dbx\dbxContent\dbxContentLng;

$lng = $argv[1] ?? '';
$id = (int)($argv[2] ?? 0);
if ($lng === '' || $id <= 0) {
   fwrite(STDERR, "Usage: php dbxki_get_page.php <lng> <id>\n");
   exit(1);
}

$db = dbx()->get_system_obj('dbxDB');
$row = $db->select1(dbxContentLng::dd_content($lng), $id);
if (!is_array($row)) {
   fwrite(STDERR, "Seite nicht gefunden: $lng #$id\n");
   exit(1);
}

echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
