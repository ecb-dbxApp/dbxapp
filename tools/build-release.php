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

   if (preg_match('#^(?:\.git|\.github|dist|docs|tools|output|tmp)/#', $relative)
      || preg_match('#/tests/#', '/' . $relative)
      || preg_match('#/(?:backup|_backup|\.backup|work)/#i', '/' . $relative)
      || preg_match('/^\d{2}_.*\.md$/', $base)
      || in_array($base, array('.gitignore', '.gitattributes', '.editorconfig', 'Doxyfile'), true)
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
echo 'Release-Dateien: ' . count($files) . PHP_EOL;

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

if (!$zip->close()) {
   fwrite(STDERR, "Release-ZIP konnte nicht abgeschlossen werden.\n");
   exit(10);
}

$hash = hash_file('sha256', $zipPath);
if (!is_string($hash) || $hash === '') {
   fwrite(STDERR, "SHA-256 konnte nicht berechnet werden.\n");
   exit(11);
}

$checksumPath = $dist . DIRECTORY_SEPARATOR . 'SHA256SUMS';
$checksumLine = $hash . '  ' . basename($zipPath) . PHP_EOL;
if (file_put_contents($checksumPath, $checksumLine, LOCK_EX) === false) {
   fwrite(STDERR, "SHA256SUMS konnte nicht geschrieben werden.\n");
   exit(12);
}

echo "Erstellt: {$zipPath}\n";
echo "SHA-256: {$hash}\n";
