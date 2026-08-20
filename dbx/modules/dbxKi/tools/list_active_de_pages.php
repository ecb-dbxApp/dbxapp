<?php
/**
 * Read-only: listet alle aktiven, oeffentlich erreichbaren DE-Seiten
 * (gleiche Kriterien wie sitemap.xml: activ=1, kein noindex, oeffentliche
 * Ordnerrechte) mit id, title, permalink, folder, lng_uid, template.
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

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentRenderer;

$db = dbx()->get_system_obj('dbxDB');
$renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');

$lng = 'de';
$rows = $db->select(
   dbxContentLng::dd_content($lng),
   'activ = 1',
   'id,permalink,folder,title,template,lng_uid,meta_robots,update_date',
   'folder,sorter,id',
   'ASC',
   '',
   0,
   0,
   0
);

$out = array();
foreach ((is_array($rows) ? $rows : array()) as $row) {
   if (!is_array($row)) continue;
   $permalink = trim((string)($row['permalink'] ?? ''), '/');
   $meta = strtolower((string)($row['meta_robots'] ?? ''));
   $noindex = strpos($meta, 'noindex') !== false;
   $rights = $renderer->get_public_folder_rights((int)($row['folder'] ?? 0));
   $public = is_string($rights) ? (trim($rights) === '*' || trim($rights) === '') : (bool)$rights;
   $out[] = array(
      'id' => (int)$row['id'],
      'permalink' => $permalink,
      'title' => (string)($row['title'] ?? ''),
      'folder' => (int)($row['folder'] ?? 0),
      'template' => (string)($row['template'] ?? ''),
      'lng_uid' => (string)($row['lng_uid'] ?? ''),
      'noindex' => $noindex,
      'public_rights' => $public,
      'rights_raw' => $rights,
   );
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
