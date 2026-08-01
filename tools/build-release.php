<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false) {
   fwrite(STDERR, "Projektwurzel wurde nicht gefunden.\n");
   exit(2);
}

$dryRun = in_array('--dry-run', $argv, true);
$versionFile = $root . DIRECTORY_SEPARATOR . 'VERSION';
$version = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '';

if ($dryRun) {
   if (!preg_match('/^\d+\.\d+\.\d+(?:-dev)?$/', $version)) {
      fwrite(STDERR, "Ungültige VERSION: {$version}\n");
      exit(3);
   }
} elseif (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
   fwrite(STDERR, "Release benötigt eine stabile VERSION ohne -dev: {$version}\n");
   exit(3);
}

$tag = trim((string)getenv('GITHUB_REF_NAME'));
if (!$dryRun && $tag !== '' && $tag !== 'v' . $version) {
   fwrite(STDERR, "Tag {$tag} stimmt nicht mit VERSION {$version} überein.\n");
   exit(4);
}

if (!$dryRun && !is_file($root . '/dbx/vendor/autoload.php')) {
   fwrite(STDERR, "Produktive Composer-Abhängigkeiten fehlen. Zuerst composer install ausführen.\n");
   exit(5);
}

/**
 * Entscheidet, ob eine Datei Bestandteil des installierbaren Release-ZIPs ist.
 */
function release_file_allowed(string $relative): bool
{
   $relative = str_replace('\\', '/', $relative);
   $base = basename($relative);
   $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

   if (preg_match('#^(?:\.git|\.github|dist|tools|files|output|tmp)/#', $relative)
      || str_starts_with($relative, 'dbx/files/')
      || preg_match('#/db/#i', '/' . $relative)
      || preg_match('#/(?:cache|tmp|work|backup|backups|_backup|\.backup|uploads)/#i', '/' . $relative)
      || preg_match('/^\d{2}_.*\.md$/', $base)
      || in_array($base, array('.gitignore', '.gitattributes', '.editorconfig'), true)
      || str_starts_with($base, 'RELEASE_NOTES_')
      || in_array($base, array('.env', '.env.local', 'config.local.php'), true)
      || in_array($extension, array('db3', 'sqlite', 'sqlite3', 'log', 'tmp', 'bak', 'backup', 'pem', 'key', 'p12', 'pfx'), true)) {
      return false;
   }

   return true;
}

$files = array();
$iterator = new RecursiveIteratorIterator(
   new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $item) {
   if (!$item->isFile()) {
      continue;
   }

   $absolute = $item->getPathname();
   $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
   if (release_file_allowed($relative)) {
      $files[$relative] = $absolute;
   }
}

ksort($files);
echo 'Release-Dateien: ' . (count($files) + 1) . PHP_EOL;

if ($dryRun) {
   echo "Dry-Run erfolgreich für VERSION {$version}\n";
   exit(0);
}

if (!class_exists('ZipArchive')) {
   fwrite(STDERR, "PHP-Erweiterung zip/ZipArchive fehlt.\n");
   exit(6);
}

$dist = $root . DIRECTORY_SEPARATOR . 'dist';
if (!is_dir($dist) && !mkdir($dist, 0775, true) && !is_dir($dist)) {
   fwrite(STDERR, "dist-Verzeichnis konnte nicht erzeugt werden.\n");
   exit(7);
}

$zipPath = $dist . DIRECTORY_SEPARATOR . 'dbxapp-' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
   fwrite(STDERR, "Release-ZIP konnte nicht geöffnet werden.\n");
   exit(8);
}

foreach ($files as $relative => $absolute) {
   if (!$zip->addFile($absolute, $relative)) {
      $zip->close();
      fwrite(STDERR, "Datei konnte nicht zum ZIP hinzugefügt werden: {$relative}\n");
      exit(9);
   }
}

$inventoryFiles = array();
foreach ($files as $relative => $absolute) {
   $fileHash = hash_file('sha256', $absolute);
   if (!is_string($fileHash) || $fileHash === '') {
      $zip->close();
      fwrite(STDERR, "Dateiprüfsumme konnte nicht berechnet werden: {$relative}\n");
      exit(10);
   }
   $inventoryFiles[$relative] = $fileHash;
}
$inventoryFiles['.dbx-release-files.json'] = null;
ksort($inventoryFiles);
$inventory = json_encode(array(
   'schema' => 1,
   'product' => 'dbxapp',
   'version' => $version,
   'files' => $inventoryFiles,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($inventory)
   || !$zip->addFromString('.dbx-release-files.json', $inventory . PHP_EOL)) {
   $zip->close();
   fwrite(STDERR, "Release-Datei-Inventar konnte nicht erzeugt werden.\n");
   exit(10);
}

if (!$zip->close()) {
   fwrite(STDERR, "Release-ZIP konnte nicht abgeschlossen werden.\n");
   exit(11);
}

$hash = hash_file('sha256', $zipPath);
if (!is_string($hash) || $hash === '') {
   fwrite(STDERR, "SHA-256 konnte nicht berechnet werden.\n");
   exit(12);
}

$checksumPath = $dist . DIRECTORY_SEPARATOR . 'SHA256SUMS';
$checksumLine = $hash . '  ' . basename($zipPath) . PHP_EOL;
if (file_put_contents($checksumPath, $checksumLine, LOCK_EX) === false) {
   fwrite(STDERR, "SHA256SUMS konnte nicht geschrieben werden.\n");
   exit(13);
}

$baseUrl = 'https://github.com/ecb-dbxApp/dbxapp';
$updateManifest = json_encode(array(
   'schema' => 1,
   'product' => 'dbxapp',
   'channel' => 'stable',
   'version' => $version,
   'release_url' => $baseUrl . '/releases/tag/v' . $version,
   'zip_url' => $baseUrl . '/releases/download/v' . $version
      . '/dbxapp-' . $version . '.zip',
   'sha256' => $hash,
   'size' => filesize($zipPath),
   'requires' => array(
      'php' => '>=8.2',
      'extensions' => array('curl', 'json', 'pdo', 'zip'),
   ),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$updateManifestPath = $dist . DIRECTORY_SEPARATOR . 'update.json';
if (!is_string($updateManifest)
   || file_put_contents($updateManifestPath, $updateManifest . PHP_EOL, LOCK_EX) === false) {
   fwrite(STDERR, "Update-Manifest konnte nicht geschrieben werden.\n");
   exit(14);
}

echo "Erstellt: {$zipPath}\n";
echo "SHA-256: {$hash}\n";
echo "Update-Manifest: {$updateManifestPath}\n";
