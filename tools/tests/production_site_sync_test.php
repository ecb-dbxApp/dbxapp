<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/ProductionSiteSync.php';

$expected = array(
   'index.php' => true,
   'dbx/include/dbxApi.php' => true,
   'dbx/vendor/autoload.php' => true,
   'dbx/modules/myX/myX.class.php' => true,
   'dbx/modules/dbx/db/dbxContent.db3' => true,
   'files/media/img/logo.png' => true,
   'files/sys/cfg/runtime.json' => true,
);
$rejected = array(
   'tools/ci.php',
   'reference/current/index.html',
   'dbx/include/tests/example_test.php',
   'dbx/modules/dbxAdmin/tools/import.php',
   'dbx/modules/dbxDocs/dbxDocs.class.php',
   'files/doku/guide.md',
   'files/db/dd-backup/content.zip',
   'files/sys/backups/site.zip',
   'files/sys/cache/assets.json',
   'files/temp/upload.tmp',
   'README.md',
);

$errors = array();
foreach ($expected as $path => $value) {
   if (ProductionSiteSync::is_deployable_file($path) !== $value) {
      $errors[] = 'Sollte ausgeliefert werden: ' . $path;
   }
}
foreach ($rejected as $path) {
   if (ProductionSiteSync::is_deployable_file($path)) {
      $errors[] = 'Ballast wurde zugelassen: ' . $path;
   }
}
foreach (array('.env', 'dbx/modules/dbx/cfg/config.local.php', '.well-known/acme.txt') as $path) {
   if (!ProductionSiteSync::is_preserved_target_file($path)) {
      $errors[] = 'Lokale Zieldatei wird nicht bewahrt: ' . $path;
   }
}

if ($errors) {
   fwrite(STDERR, implode("\n", $errors) . "\n");
   exit(1);
}

echo "OK: Produktionsabgleich trennt Laufzeitstand, lokale Konfiguration und Ballast.\n";
