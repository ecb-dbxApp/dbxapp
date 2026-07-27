<?php
declare(strict_types=1);

use dbx\dbxAdmin\dbxAdminHelp;
use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContent_permalink;

if (PHP_SAPI !== 'cli') {
   fwrite(STDERR, "Dieses Werkzeug darf nur auf der Kommandozeile laufen.\n");
   exit(1);
}

$base = dirname(__DIR__, 4);
chdir($base);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $base . '/dbx/vendor/autoload.php';
require_once $base . '/dbx/include/dbxKernel.php';
require_once $base . '/dbx/modules/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once $base . '/dbx/modules/dbxAdmin/include/dbxAdminHelp.class.php';

$dbFile = $base . '/dbx/modules/dbx/db/dbxContent.db3';
if (!is_file($dbFile)) {
   fwrite(STDERR, "Content-Datenbank nicht gefunden: {$dbFile}\n");
   exit(1);
}

$backupDir = dirname($dbFile) . '/backup';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
   fwrite(STDERR, "Backup-Verzeichnis konnte nicht angelegt werden.\n");
   exit(1);
}
$backupFile = $backupDir . '/dbxContent-before-flat-permalinks-' . date('Ymd-His') . '.db3';
if (!copy($dbFile, $backupFile)) {
   fwrite(STDERR, "Datenbank-Backup konnte nicht erstellt werden.\n");
   exit(1);
}

$db = dbx()->get_system_obj('dbxDB');
if (!is_object($db) || !$db->connect_db_server('dbx|dbxContent.db3')) {
   fwrite(STDERR, "Content-Datenbank konnte ueber dbxDB nicht verbunden werden.\n");
   exit(1);
}

$help = new dbxAdminHelp();
$helpLegacy = array();
foreach ($help->topics() as $topic => $meta) {
   $slug = str_replace('_', '-', strtolower((string)$topic));
   $canonical = trim((string)($meta['permalink'] ?? ''));
   if (!dbxContent_permalink::isValid($canonical)) {
      throw new RuntimeException('Ungueltiger Hilfe-Permalink fuer ' . $topic . ': ' . $canonical);
   }
   $helpLegacy['outside/help/' . $slug] = $canonical;
   $helpLegacy['help/' . $slug] = $canonical;
}

$languages = dbxContentLngSync::accessibleLngs();
if ($languages === array()) {
   $languages = array(dbxContentLngSync::masterLng());
}
$transactionDd = dbxContentLng::ddContent((string)$languages[0]);
$result = array('backup' => $backupFile, 'tables' => array(), 'updated' => 0, 'links' => 0);

if ($db->begin($transactionDd) !== 1) {
   fwrite(STDERR, "Transaktion konnte nicht gestartet werden.\nBackup: {$backupFile}\n");
   exit(1);
}

try {
   foreach ($languages as $lng) {
      $dd = dbxContentLng::ddContent((string)$lng);
      $table = $db->get_dd_table($dd);
      $rows = $db->select($dd, '', 'id,permalink', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) {
         continue;
      }

      $used = array();
      $changes = array();
      foreach ($rows as $row) {
         $id = (int)($row['id'] ?? 0);
         $old = trim((string)($row['permalink'] ?? ''));
         $legacyKey = strtolower(trim(str_replace('\\', '/', $old), '/'));
         if (dbxContent_permalink::isValid($old)) {
            $candidate = $old;
         } elseif (isset($helpLegacy[$legacyKey])) {
            $candidate = $helpLegacy[$legacyKey];
         } else {
            $candidate = dbxContent_permalink::canonicalFromLegacy($old);
         }
         if ($candidate === '') {
            $candidate = 'seite-' . $id;
         }

         $baseCandidate = $candidate;
         $number = 2;
         while (isset($used[$candidate]) && $used[$candidate] !== $id) {
            $suffix = '-' . $number;
            $candidate = rtrim(substr($baseCandidate, 0, 254 - strlen($suffix)), '-') . $suffix;
            $number++;
         }
         if (!dbxContent_permalink::isValid($candidate)) {
            throw new RuntimeException("Migration erzeugt ungueltigen Permalink {$candidate} fuer {$table}#{$id}.");
         }
         $used[$candidate] = $id;

         if ($candidate !== $old) {
            if ($db->update($dd, array('permalink' => $candidate), $id, 0) !== 1) {
               throw new RuntimeException("Permalink konnte nicht aktualisiert werden: {$table}#{$id}.");
            }
            $changes[$old] = $candidate;
            $result['updated']++;
         }
      }

      if ($changes !== array()) {
         uksort($changes, static function(string $left, string $right): int {
            return strlen($right) <=> strlen($left);
         });
         $contentRows = $db->select($dd, '', 'id,content', 'id', 'ASC', '', 0, 0, 0);
         foreach ((is_array($contentRows) ? $contentRows : array()) as $contentRow) {
            $before = (string)($contentRow['content'] ?? '');
            if ($before === '') {
               continue;
            }
            $after = str_replace(array_keys($changes), array_values($changes), $before);
            if ($after !== $before) {
               if ($db->update($dd, array('content' => $after), (int)$contentRow['id'], 0) !== 1) {
                  throw new RuntimeException("Content-Links konnten nicht aktualisiert werden: {$table}#{$contentRow['id']}.");
               }
               $result['links']++;
            }
         }
      }

      $result['tables'][$table] = array('rows' => count($rows), 'changed' => count($changes));
   }

   if ($db->commit($transactionDd) !== 1) {
      throw new RuntimeException('Transaktion konnte nicht abgeschlossen werden.');
   }
} catch (Throwable $e) {
   $db->rollback($transactionDd);
   fwrite(STDERR, $e->getMessage() . "\nBackup: {$backupFile}\n");
   exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
