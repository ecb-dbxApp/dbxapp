<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/AuthoritativeSourceSync.php';

function sync_test_assert(bool $condition, string $message): void
{
   if (!$condition) {
      fwrite(STDERR, $message . PHP_EOL);
      exit(1);
   }
}

$allowed = array(
   'index.php',
   'AGENTS.md',
   'VERSION',
   'dbx.package.json',
   '07_DBXAPP_ARCHITEKTUR.md',
   'dbx/include/dbxApi.php',
   'dbx/marketplace/keys/dbxapp-market-2026.pem',
   'dbx/modules/dbxContent_admin/files/og/dbxapp-og.png',
   'dbx/modules/dbxUser/img/avatar/avatar-0.png',
   'docs/doxygen-generated-main.dox',
   'docs/generated/tutorials/example.webp',
   'docs/tools/doxygen_php_utf8_filter.php',
   'tools/build-marketplace.php',
   'tools/ci.php',
   'tools/lib/ProductionSiteSync.php',
   'tools/sync-authoritative-source.php',
   'tools/sync-production-site.php',
   'tools/tests/production_site_sync_test.php',
   'tools/tests/marketplace_publisher_sequence_contract_test.php',
);
foreach ($allowed as $path) {
   sync_test_assert(
      AuthoritativeSourceSync::is_managed_file($path),
      'Öffentliche Produktdatei wurde abgelehnt: ' . $path
   );
}

$blocked = array(
   '.env',
   'RELEASE_PROCESS.md',
   '.github/workflows/release.yml',
   'dbx/vendor/autoload.php',
   'dbx/files/session.db3',
   'dbx/modules/dbx/db/data.db3',
   'dbx/modules/dbx/db/.gitkeep',
   'dbx/modules/dbxKi/work/job.php',
   'dbx/modules/myLKW/myLKW.class.php',
   'dbx/modules/myX/myX.class.php',
   'dbx/modules/dbxMenu/tpl/htm/dbx-top-main.htm',
   'RELEASE_NOTES_v4.2.0.md',
   'dbx/modules/dbxContent/tpl/pdf/internal.pdf',
   'dbx/modules/dbxUser/img/avatar/user-18.png',
   'dbx/modules/demo/cache/a.php',
   'dbx/modules/demo/config.local.php',
   'dbx/modules/demo/private.key',
   'dbx/modules/demo/private.pem',
   'files/sys/marketplace/keys/dbxapp-market-2026-private.pem',
   'docs/private.key',
   'docs/backup/internal.md',
);
foreach ($blocked as $path) {
   sync_test_assert(
      !AuthoritativeSourceSync::is_managed_file($path),
      'Lokale oder Release-eigene Datei wurde zugelassen: ' . $path
   );
}

$test_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
   . 'dbx-source-sync-' . bin2hex(random_bytes(6));
$source_root = $test_root . DIRECTORY_SEPARATOR . 'source';
$target_root = $test_root . DIRECTORY_SEPARATOR . 'target';
$required_directories = array(
   $source_root . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'include',
   $target_root . DIRECTORY_SEPARATOR . '.git',
);
foreach ($required_directories as $directory) {
   if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
      fwrite(STDERR, 'Temporäres Testverzeichnis konnte nicht erstellt werden.' . PHP_EOL);
      exit(1);
   }
}
file_put_contents(
   $source_root . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR
      . 'include' . DIRECTORY_SEPARATOR . 'dbxApi.php',
   "<?php\r\n"
);
file_put_contents($source_root . DIRECTORY_SEPARATOR . 'index.php', "<?php\r\necho 1;\r\n");
file_put_contents($source_root . DIRECTORY_SEPARATOR . 'logo.png', "\x00A\r\nB");
file_put_contents($target_root . DIRECTORY_SEPARATOR . 'RELEASE_PROCESS.md', "# Test\n");
file_put_contents($target_root . DIRECTORY_SEPARATOR . 'index.php', "<?php\necho 1;\n");
file_put_contents($target_root . DIRECTORY_SEPARATOR . 'logo.png', "\x00A\nB");

$line_ending_plan = AuthoritativeSourceSync::plan($source_root, $target_root);
sync_test_assert(
   !isset($line_ending_plan['copy']['index.php']),
   'CRLF/LF-Unterschiede dürfen bei Textdateien keinen Kopierplan erzeugen.'
);
sync_test_assert(
   isset($line_ending_plan['copy']['logo.png']),
   'Binärdateien müssen weiterhin bytegenau verglichen werden.'
);

$cleanup = static function (string $path) use (&$cleanup): void {
   if (is_dir($path)) {
      $items = scandir($path);
      if (is_array($items)) {
         foreach (array_diff($items, array('.', '..')) as $item) {
            $cleanup($path . DIRECTORY_SEPARATOR . $item);
         }
      }
      rmdir($path);
   } elseif (is_file($path)) {
      unlink($path);
   }
};
$cleanup($test_root);

echo "AuthoritativeSourceSync-Vertrag erfolgreich geprüft.\n";
