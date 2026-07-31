<?php
declare(strict_types=1);

$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

$root = dirname(__DIR__, 4);
require $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';
require_once $root . '/dbx/modules/dbxContent/include/dbxContent_bootstrap_sync.php';

function marketingDeFail(string $message, int $code): void {
   fwrite(STDERR, "FAIL: {$message}\n");
   exit($code);
}

$migrationFile = $root . '/dbx/modules/dbxContent/tools/restructure_marketing_de.php';
$source = (string)file_get_contents($migrationFile);
if (strpos($source, "PHP_SAPI !== 'cli'") === false
   || strpos($source, "in_array('--apply'") === false
   || strpos($source, '$db->begin($contentDd)') === false
   || strpos($source, '$db->rollback($contentDd)') === false
   || strpos($source, '/backup') === false) {
   marketingDeFail('Trockenlauf, Backup oder dbxDB-Transaktion fehlen.', 1);
}
if (preg_match('/new\s+(?:PDO|SQLite3)\b|(?:PDO|SQLite3)::|->delete\s*\(/', $source)) {
   marketingDeFail('Migration enthaelt direkten Datenbankzugriff oder eine Loeschoperation.', 2);
}

$contentDatabase = $root . '/dbx/modules/dbx/db/dbxContent.db3';
if (!is_file($contentDatabase) || filesize($contentDatabase) === 0) {
   echo "OK: Statischer Marketing-Migrationsvertrag; lokale Inhaltsdaten sind nicht Teil des öffentlichen Checkouts.\n";
   exit(0);
}

$db = dbx()->get_system_obj('dbxDB');
$contentDd = \dbx\dbxContent\dbxContentLng::ddContent('de');
$folderDd = \dbx\dbxContent\dbxContentLng::ddFolder('de');
$rows = $db->select(
   $contentDd,
   '1=1',
   'id,permalink,title,description,content,template,folder,activ,addmenu,group_read,meta_robots',
   'id',
   'ASC',
   '',
   0,
   0,
   0
);
if (!is_array($rows)) {
   marketingDeFail('Deutsche Inhalte konnten nicht ueber dbxDB gelesen werden.', 3);
}

$byId = array();
$byPermalink = array();
foreach ($rows as $row) {
   $byId[(int)($row['id'] ?? 0)] = $row;
   $byPermalink[(string)($row['permalink'] ?? '')] = $row;
}

$targets = array(
   'home', 'loesungen', 'cms-website', 'shop-multichannel',
   'individuelle-anwendungen', 'intranet-portale', 'dbxki', 'plattform',
   'pakete', 'referenzen', 'demo', 'entwickler', 'dokumentation', 'kontakt',
);
foreach ($targets as $permalink) {
   $row = $byPermalink[$permalink] ?? array();
   $allowedTemplates = $permalink === 'home'
      ? array('c-marketing-body1-footer', 'c-title-hero_header-body1-footer')
      : array('c-marketing-body1-footer');
   if ((int)($row['id'] ?? 0) <= 0
      || (int)($row['activ'] ?? 0) !== 1
      || !in_array((string)($row['template'] ?? ''), $allowedTemplates, true)
      || (string)($row['meta_robots'] ?? '') !== 'index,follow') {
      marketingDeFail("Aktive deutsche Zielseite fehlt oder ist ungueltig: {$permalink}", 4);
   }
   $text = (string)($row['title'] ?? '') . ' '
      . (string)($row['description'] ?? '') . ' '
      . strip_tags((string)($row['content'] ?? ''));
   if (preg_match('/dbXapp|dbXApp|DBXapp/', $text)
      || preg_match('/\b(?:fuer|ueber|aender|oeffent|geschaeft|moeglich|koennen|muessen|waehlen|loeschen)\b/iu', $text)) {
      marketingDeFail("Schreibweise oder Umlaute sind auf {$permalink} inkonsistent.", 5);
   }
}

$archiveIds = array(
   2, 4, 5, 6, 8, 45, 47, 48, 51, 53, 56, 57, 58, 59, 60, 62,
   63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76,
   115, 116, 117, 118, 119, 120, 121, 122,
);
foreach ($archiveIds as $id) {
   $row = $byId[$id] ?? array();
   if ((int)($row['folder'] ?? 0) !== 19
      || (int)($row['activ'] ?? 1) !== 0
      || (int)($row['addmenu'] ?? 1) !== 0
      || (string)($row['group_read'] ?? '') !== 'admin'
      || (string)($row['meta_robots'] ?? '') !== 'noindex,nofollow') {
      marketingDeFail("Archivseite wurde nicht vollstaendig nach /trash verschoben: {$id}", 6);
   }
}

$trash = $db->select1($folderDd, 19, 'id,name,parent_id,group_read', 0);
if (!is_array($trash)
   || (string)($trash['name'] ?? '') !== 'trash'
   || (string)($trash['group_read'] ?? '') !== 'admin') {
   marketingDeFail('Der deutsche /trash-Ordner ist nicht admin-geschuetzt.', 7);
}

echo "OK: Deutsche Marketingstruktur ist aktiv; Altseiten liegen unveraendert als Datensaetze in /trash.\n";
