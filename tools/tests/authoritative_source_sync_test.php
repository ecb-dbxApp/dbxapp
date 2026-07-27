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
   'VERSION',
   '07_DBXAPP_ARCHITEKTUR.md',
   'dbx/include/dbxApi.php',
   'dbx/modules/dbxContent_admin/files/og/dbxapp-og.png',
   'dbx/modules/dbxUser/img/avatar/avatar-0.png',
   'tools/sync-authoritative-source.php',
);
foreach ($allowed as $path) {
   sync_test_assert(
      AuthoritativeSourceSync::isManagedFile($path),
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
   'dbx/modules/dbxContent/tpl/pdf/internal.pdf',
   'dbx/modules/dbxUser/img/avatar/user-18.png',
   'dbx/modules/demo/cache/a.php',
   'dbx/modules/demo/config.local.php',
   'dbx/modules/demo/private.key',
);
foreach ($blocked as $path) {
   sync_test_assert(
      !AuthoritativeSourceSync::isManagedFile($path),
      'Lokale oder Release-eigene Datei wurde zugelassen: ' . $path
   );
}

echo "AuthoritativeSourceSync-Vertrag erfolgreich geprüft.\n";
