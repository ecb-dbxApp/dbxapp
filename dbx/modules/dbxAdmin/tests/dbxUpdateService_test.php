<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxUpdateService.class.php';

use dbx\dbxAdmin\dbxUpdateService;

function update_test_assert(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

function update_test_write(string $file, string $content): void
{
   $directory = dirname($file);
   if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
      throw new RuntimeException('Testverzeichnis konnte nicht erstellt werden.');
   }
   if (file_put_contents($file, $content) === false) {
      throw new RuntimeException('Testdatei konnte nicht geschrieben werden.');
   }
}

function update_test_remove_tree(string $directory): void
{
   if (!is_dir($directory)) {
      return;
   }
   $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
   );
   foreach ($iterator as $item) {
      if ($item->isDir() && !$item->isLink()) {
         rmdir($item->getPathname());
      } else {
         unlink($item->getPathname());
      }
   }
   rmdir($directory);
}

function update_test_inventory(string $version, array $contents): string
{
   $files = array();
   foreach ($contents as $path => $content) {
      $files[$path] = hash('sha256', $content);
   }
   $files['.dbx-release-files.json'] = null;
   ksort($files);
   return json_encode(array(
      'schema' => 1,
      'product' => 'dbxapp',
      'version' => $version,
      'files' => $files,
   ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function update_test_package(
   string $zipFile,
   string $version,
   array $contents,
   string $extraPath = ''
): void {
   $zip = new ZipArchive();
   if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
      throw new RuntimeException('Test-ZIP konnte nicht erzeugt werden.');
   }
   foreach ($contents as $path => $content) {
      $zip->addFromString($path, $content);
   }
   $zip->addFromString(
      '.dbx-release-files.json',
      update_test_inventory($version, $contents)
   );
   if ($extraPath !== '') {
      $zip->addFromString($extraPath, 'unsafe');
   }
   $zip->close();
}

function update_test_stage(
   string $work,
   string $zipFile,
   array $manifest,
   array $package
): string {
   $staging = $work . DIRECTORY_SEPARATOR . 'staging'
      . DIRECTORY_SEPARATOR . (string)$manifest['version'];
   if (!is_dir($staging)) {
      mkdir($staging, 0775, true);
   }
   $zip = new ZipArchive();
   $zip->open($zipFile);
   $zip->extractTo($staging);
   $zip->close();
   update_test_write(
      $work . DIRECTORY_SEPARATOR . 'staged.json',
      json_encode(array(
         'schema' => 1,
         'staged_at' => gmdate('c'),
         'zip_file' => $zipFile,
         'staging_directory' => $staging,
         'manifest' => $manifest,
         'files' => $package['files'],
      ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
   );
   return $staging;
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
   . 'dbx-update-service-' . bin2hex(random_bytes(6));

try {
   $oldContents = array(
      'VERSION' => "4.0.1\n",
      'index.php' => "<?php echo 'old';\n",
      'dbx/include/dbxApi.php' => "<?php // old api\n",
      'obsolete.php' => "<?php // obsolete\n",
   );
   foreach ($oldContents as $path => $content) {
      update_test_write(
         $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path),
         $content
      );
   }
   update_test_write(
      $root . DIRECTORY_SEPARATOR . '.dbx-release-files.json',
      update_test_inventory('4.0.1', $oldContents)
   );
   update_test_write($root . DIRECTORY_SEPARATOR . '.env', "LOCAL=1\n");
   update_test_write(
      $root . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'keep.txt',
      'runtime'
   );
   update_test_write(
      $root . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'modules'
         . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'cfg'
         . DIRECTORY_SEPARATOR . 'config.local.php',
      "<?php // local\n"
   );

   $newContents = array(
      'VERSION' => "4.0.2\n",
      'index.php' => "<?php echo 'new';\n",
      'dbx/include/dbxApi.php' => "<?php // new api\n",
      'new.php' => "<?php // new file\n",
   );
   $work = $root . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'update';
   $zipFile = $work . DIRECTORY_SEPARATOR . 'downloads'
      . DIRECTORY_SEPARATOR . 'dbxapp-4.0.2.zip';
   if (!is_dir(dirname($zipFile))) {
      mkdir(dirname($zipFile), 0775, true);
   }
   update_test_package($zipFile, '4.0.2', $newContents);

   $manifest = array(
      'schema' => 1,
      'product' => 'dbxapp',
      'channel' => 'stable',
      'version' => '4.0.2',
      'release_url' => 'https://github.com/ecb-dbxApp/dbxapp/releases/tag/v4.0.2',
      'zip_url' => 'https://github.com/ecb-dbxApp/dbxapp/releases/download/v4.0.2/dbxapp-4.0.2.zip',
      'sha256' => hash_file('sha256', $zipFile),
      'requires' => array(
         'php' => '>=8.2',
         'extensions' => array('json', 'pdo', 'zip'),
      ),
   );

   $service = new dbxUpdateService($root);
   $package = $service->inspectPackage($zipFile, $manifest);
   update_test_assert(
      in_array('new.php', $package['files'], true),
      'Gültige Paketdatei fehlt in der Prüfung.'
   );

   $badManifest = $manifest;
   $badManifest['zip_url'] = 'https://github.com/other/project/releases/download/v4.0.2/dbxapp-4.0.2.zip';
   try {
      $service->validateManifest($badManifest);
      throw new RuntimeException('Fremde GitHub-Release-URL wurde zugelassen.');
   } catch (RuntimeException $exception) {
      update_test_assert(
         str_contains($exception->getMessage(), 'Vertrauensgrenze'),
         'Falscher Fehler für fremde Release-URL.'
      );
   }

   $unsafeZip = $work . DIRECTORY_SEPARATOR . 'downloads'
      . DIRECTORY_SEPARATOR . 'unsafe.zip';
   update_test_package($unsafeZip, '4.0.2', $newContents, '../escape.php');
   try {
      $service->inspectPackage($unsafeZip, $manifest);
      throw new RuntimeException('ZIP-Pfad-Traversal wurde zugelassen.');
   } catch (RuntimeException $exception) {
      update_test_assert(
         str_contains($exception->getMessage(), 'Pfad-Traversal'),
         'Falscher Fehler für ZIP-Pfad-Traversal.'
      );
   }

   $staging = update_test_stage($work, $zipFile, $manifest, $package);
   update_test_assert(
      !empty($service->status()['stop_available']),
      'Vorbereitetes Update kann laut Status nicht gestoppt werden.'
   );
   $stopped = $service->cancel();
   update_test_assert($stopped['version'] === '4.0.2', 'Gestoppte Version ist falsch.');
   update_test_assert(!is_file($work . '/staged.json'), 'Staging-Status wurde beim Stoppen nicht entfernt.');
   update_test_assert(!is_dir($staging), 'Staging-Verzeichnis wurde beim Stoppen nicht entfernt.');
   update_test_assert(!is_file($zipFile), 'Update-ZIP wurde beim Stoppen nicht entfernt.');
   update_test_assert(
      empty($service->status()['stop_available']),
      'Status bietet Stoppen nach dem Abbruch weiterhin an.'
   );

   $outsideGuard = $root . DIRECTORY_SEPARATOR . 'outside-stop-guard.txt';
   update_test_write($outsideGuard, 'protected');
   update_test_write(
      $work . DIRECTORY_SEPARATOR . 'staged.json',
      json_encode(array(
         'schema' => 1,
         'staged_at' => gmdate('c'),
         'zip_file' => $work . DIRECTORY_SEPARATOR . 'downloads'
            . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
            . 'outside-stop-guard.txt',
         'staging_directory' => $work . DIRECTORY_SEPARATOR . 'staging'
            . DIRECTORY_SEPARATOR . 'missing',
         'manifest' => $manifest,
         'files' => array(),
      ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
   );
   try {
      $service->cancel();
      throw new RuntimeException('Manipulierter Stop-Pfad wurde zugelassen.');
   } catch (RuntimeException $exception) {
      update_test_assert(
         str_contains($exception->getMessage(), 'Status ist ungültig'),
         'Falscher Fehler für manipulierten Stop-Pfad.'
      );
   }
   update_test_assert(
      is_file($outsideGuard),
      'Stop-Pfad hat eine Datei außerhalb des Updatebereichs entfernt.'
   );
   unlink($work . DIRECTORY_SEPARATOR . 'staged.json');
   unlink($outsideGuard);

   update_test_package($zipFile, '4.0.2', $newContents);
   $manifest['sha256'] = hash_file('sha256', $zipFile);
   $staging = update_test_stage($work, $zipFile, $manifest, $package);
   $installed = $service->install();
   update_test_assert(trim((string)file_get_contents($root . '/VERSION')) === '4.0.2', 'VERSION wurde nicht aktualisiert.');
   update_test_assert(!is_file($root . '/obsolete.php'), 'Veraltete Datei wurde nicht entfernt.');
   update_test_assert(is_file($root . '/new.php'), 'Neue Datei wurde nicht installiert.');
   update_test_assert((string)file_get_contents($root . '/files/keep.txt') === 'runtime', 'Laufzeitdatei wurde verändert.');
   update_test_assert((string)file_get_contents($root . '/.env') === "LOCAL=1\n", '.env wurde verändert.');
   update_test_assert(
      is_dir((string)$installed['backup_directory']),
      'Dateisicherung wurde nicht erzeugt.'
   );
   update_test_assert(!is_file($work . '/staged.json'), 'Staging-Status blieb nach Installation bestehen.');
   update_test_assert(!is_dir($staging), 'Staging-Verzeichnis blieb nach Installation bestehen.');
   update_test_assert(!is_file($zipFile), 'Update-ZIP blieb nach Installation bestehen.');

   $service->rollback();
   update_test_assert(trim((string)file_get_contents($root . '/VERSION')) === '4.0.1', 'Rollback hat VERSION nicht wiederhergestellt.');
   update_test_assert(is_file($root . '/obsolete.php'), 'Rollback hat veraltete Datei nicht wiederhergestellt.');
   update_test_assert(!is_file($root . '/new.php'), 'Rollback hat neue Datei nicht entfernt.');
   update_test_assert(
      (string)file_get_contents($root . '/dbx/modules/demo/cfg/config.local.php') === "<?php // local\n",
      'Lokale Modulkonfiguration wurde verändert.'
   );

   echo "dbxUpdateService: Paketprüfung, Stop, Installation und Rollback erfolgreich.\n";
} finally {
   update_test_remove_tree($root);
}
