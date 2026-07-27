<?php

declare(strict_types=1);

namespace dbx\dbxAdmin;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Secure, file-based dbxApp release updater.
 *
 * The service never accesses a database. DD-controlled schema changes remain
 * the responsibility of dbxDB when the updated application uses them.
 * Runtime data and local configuration are explicitly excluded from packages.
 */
class dbxUpdateService
{
   private const PRODUCT = 'dbxapp';
   private const CHANNEL = 'stable';
   private const OWNER_REPOSITORY = 'ecb-dbxApp/dbxapp';
   private const MAX_MANIFEST_BYTES = 1048576;
   private const MAX_PACKAGE_BYTES = 268435456;
   private const MAX_EXTRACTED_BYTES = 536870912;
   private const MAX_PACKAGE_FILES = 20000;

   private string $root;
   private string $workDirectory;
   private string $manifestUrl;
   private int $cacheTtl;

   public function __construct(
      string $root = '',
      string $manifestUrl = '',
      int $cacheTtl = 21600
   ) {
      $resolvedRoot = realpath($root !== '' ? $root : dirname(__DIR__, 4));
      if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
         throw new RuntimeException('dbxApp-Projektwurzel wurde nicht gefunden.');
      }

      $this->root = rtrim($resolvedRoot, '\\/');
      $this->workDirectory = $this->root . DIRECTORY_SEPARATOR
         . 'files' . DIRECTORY_SEPARATOR . 'update';
      $this->manifestUrl = $manifestUrl !== ''
         ? $manifestUrl
         : 'https://github.com/' . self::OWNER_REPOSITORY
            . '/releases/latest/download/update.json';
      $this->cacheTtl = max(300, $cacheTtl);
   }

   /**
    * Returns current, cached and staged update information.
    *
    * @return array<string,mixed>
    */
   public function status(): array
   {
      $cached = $this->readJson($this->workDirectory . DIRECTORY_SEPARATOR . 'check.json');
      $staged = $this->readJson($this->workDirectory . DIRECTORY_SEPARATOR . 'staged.json');
      $installed = $this->readJson($this->workDirectory . DIRECTORY_SEPARATOR . 'installed.json');
      $manifest = is_array($cached['manifest'] ?? null) ? $cached['manifest'] : array();
      $current = $this->currentVersion();

      return array(
         'current_version' => $current,
         'available_version' => (string)($manifest['version'] ?? ''),
         'update_available' => isset($manifest['version'])
            && version_compare((string)$manifest['version'], $current, '>'),
         'checked_at' => (string)($cached['checked_at'] ?? ''),
         'staged_version' => (string)($staged['manifest']['version'] ?? ''),
         'staged_at' => (string)($staged['staged_at'] ?? ''),
         'rollback_available' => is_array($installed)
            && empty($installed['rolled_back_at'])
            && is_dir((string)($installed['backup_directory'] ?? '')),
         'last_installed_version' => (string)($installed['to_version'] ?? ''),
         'last_installed_at' => (string)($installed['installed_at'] ?? ''),
         'release_url' => (string)($manifest['release_url'] ?? ''),
      );
   }

   /**
    * Fetches and validates the trusted stable release manifest.
    *
    * @return array<string,mixed>
    */
   public function check(bool $force = false): array
   {
      $cacheFile = $this->workDirectory . DIRECTORY_SEPARATOR . 'check.json';
      $cached = $this->readJson($cacheFile);
      $checkedAt = strtotime((string)($cached['checked_at'] ?? '')) ?: 0;
      if (!$force
         && is_array($cached['manifest'] ?? null)
         && $checkedAt >= time() - $this->cacheTtl) {
         return $this->validateManifest($cached['manifest']);
      }

      $json = $this->downloadText($this->manifestUrl, self::MAX_MANIFEST_BYTES);
      $decoded = json_decode($json, true);
      if (!is_array($decoded)) {
         throw new RuntimeException('Das Update-Manifest ist kein gültiges JSON-Dokument.');
      }
      $manifest = $this->validateManifest($decoded);
      $this->writeJson($cacheFile, array(
         'checked_at' => gmdate('c'),
         'manifest' => $manifest,
      ));
      return $manifest;
   }

   /**
    * Validates the release contract and its fixed GitHub trust boundary.
    *
    * @param array<string,mixed> $manifest
    * @return array<string,mixed>
    */
   public function validateManifest(array $manifest): array
   {
      if ((int)($manifest['schema'] ?? 0) !== 1
         || (string)($manifest['product'] ?? '') !== self::PRODUCT
         || (string)($manifest['channel'] ?? '') !== self::CHANNEL) {
         throw new RuntimeException('Das Update-Manifest gehört nicht zum stabilen dbxApp-Kanal.');
      }

      $version = trim((string)($manifest['version'] ?? ''));
      if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
         throw new RuntimeException('Das Update-Manifest enthält keine stabile Version.');
      }

      $hash = strtolower(trim((string)($manifest['sha256'] ?? '')));
      if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
         throw new RuntimeException('Das Update-Manifest enthält keine gültige SHA-256-Prüfsumme.');
      }

      $base = 'https://github.com/' . self::OWNER_REPOSITORY;
      $expectedZip = $base . '/releases/download/v' . $version
         . '/dbxapp-' . $version . '.zip';
      $expectedRelease = $base . '/releases/tag/v' . $version;
      if ((string)($manifest['zip_url'] ?? '') !== $expectedZip
         || (string)($manifest['release_url'] ?? '') !== $expectedRelease) {
         throw new RuntimeException('Die Release-URLs verlassen die feste dbxApp-GitHub-Vertrauensgrenze.');
      }

      $requires = is_array($manifest['requires'] ?? null)
         ? $manifest['requires']
         : array();
      $phpRequirement = trim((string)($requires['php'] ?? '>=8.2'));
      if (!preg_match('/^>=\d+\.\d+(?:\.\d+)?$/', $phpRequirement)) {
         throw new RuntimeException('Die PHP-Anforderung im Manifest ist ungültig.');
      }
      $minimumPhp = substr($phpRequirement, 2);
      if (version_compare(PHP_VERSION, $minimumPhp, '<')) {
         throw new RuntimeException(
            'Das Update benötigt PHP ' . $phpRequirement
            . '; installiert ist PHP ' . PHP_VERSION . '.'
         );
      }

      $extensions = is_array($requires['extensions'] ?? null)
         ? $requires['extensions']
         : array();
      foreach ($extensions as $extension) {
         $extension = strtolower(trim((string)$extension));
         if ($extension === '' || !preg_match('/^[a-z0-9_-]+$/', $extension)) {
            throw new RuntimeException('Das Manifest enthält eine ungültige PHP-Erweiterung.');
         }
         if (!extension_loaded($extension)) {
            throw new RuntimeException('Für das Update fehlt die PHP-Erweiterung ' . $extension . '.');
         }
      }

      $manifest['version'] = $version;
      $manifest['sha256'] = $hash;
      $manifest['requires'] = array(
         'php' => $phpRequirement,
         'extensions' => array_values($extensions),
      );
      return $manifest;
   }

   /**
    * Downloads, hashes and extracts the newest release into isolated staging.
    *
    * @return array<string,mixed>
    */
   public function stage(): array
   {
      $manifest = $this->check(false);
      if (!version_compare($manifest['version'], $this->currentVersion(), '>')) {
         throw new RuntimeException('Es ist kein neueres stabiles Update verfügbar.');
      }

      $downloads = $this->workDirectory . DIRECTORY_SEPARATOR . 'downloads';
      $this->ensureDirectory($downloads);
      $zipFile = $downloads . DIRECTORY_SEPARATOR
         . 'dbxapp-' . $manifest['version'] . '.zip';
      $temporary = $zipFile . '.part';
      if (is_file($temporary)) {
         unlink($temporary);
      }

      $this->downloadFile($manifest['zip_url'], $temporary, self::MAX_PACKAGE_BYTES);
      $actualHash = hash_file('sha256', $temporary);
      if (!is_string($actualHash)
         || !hash_equals($manifest['sha256'], strtolower($actualHash))) {
         @unlink($temporary);
         throw new RuntimeException('Die SHA-256-Prüfsumme des Update-Pakets stimmt nicht.');
      }
      if (is_file($zipFile) && !unlink($zipFile)) {
         @unlink($temporary);
         throw new RuntimeException('Ein altes Update-Paket konnte nicht ersetzt werden.');
      }
      if (!rename($temporary, $zipFile)) {
         @unlink($temporary);
         throw new RuntimeException('Das geprüfte Update-Paket konnte nicht bereitgestellt werden.');
      }

      $stagingRoot = $this->workDirectory . DIRECTORY_SEPARATOR . 'staging';
      $this->ensureDirectory($stagingRoot);
      $staging = $stagingRoot . DIRECTORY_SEPARATOR . $manifest['version'];
      if (is_dir($staging)) {
         $this->removeTree($staging, $stagingRoot);
      }
      $this->ensureDirectory($staging);

      $package = $this->inspectPackage($zipFile, $manifest);
      $zip = new ZipArchive();
      if ($zip->open($zipFile) !== true || !$zip->extractTo($staging)) {
         if ($zip instanceof ZipArchive) {
            $zip->close();
         }
         $this->removeTree($staging, $stagingRoot);
         throw new RuntimeException('Das Update-Paket konnte nicht isoliert entpackt werden.');
      }
      $zip->close();
      $this->verifyExtractedFiles($staging, $package['hashes']);

      $state = array(
         'schema' => 1,
         'staged_at' => gmdate('c'),
         'zip_file' => $zipFile,
         'staging_directory' => $staging,
         'manifest' => $manifest,
         'files' => $package['files'],
      );
      $this->writeJson(
         $this->workDirectory . DIRECTORY_SEPARATOR . 'staged.json',
         $state
      );
      return $state;
   }

   /**
    * Validates ZIP paths, symlinks, inventory and package hashes.
    *
    * @param array<string,mixed> $manifest
    * @return array{files:array<int,string>,hashes:array<string,string>}
    */
   public function inspectPackage(string $zipFile, array $manifest): array
   {
      if (!class_exists(ZipArchive::class)) {
         throw new RuntimeException('Für Updates wird die PHP-Erweiterung zip benötigt.');
      }
      if (!is_file($zipFile)) {
         throw new RuntimeException('Das Update-Paket wurde nicht gefunden.');
      }
      $manifest = $this->validateManifest($manifest);

      $zip = new ZipArchive();
      if ($zip->open($zipFile) !== true) {
         throw new RuntimeException('Das Update-Paket ist kein lesbares ZIP-Archiv.');
      }

      try {
         if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_PACKAGE_FILES) {
            throw new RuntimeException('Das Update-Paket enthält eine unzulässige Dateianzahl.');
         }

         $files = array();
         $caseMap = array();
         $totalSize = 0;
         for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat)) {
               throw new RuntimeException('Ein ZIP-Eintrag konnte nicht geprüft werden.');
            }
            $raw = (string)($stat['name'] ?? '');
            $isDirectory = str_ends_with($raw, '/');
            $relative = $this->normalizeArchivePath($raw);
            if ($relative === '') {
               if ($isDirectory) {
                  continue;
               }
               throw new RuntimeException('Das Update-Paket enthält einen leeren Dateinamen.');
            }

            $attributes = 0;
            $operations = 0;
            if ($zip->getExternalAttributesIndex($index, $operations, $attributes)
               && (($attributes >> 16) & 0170000) === 0120000) {
               throw new RuntimeException('Symbolische Links sind in Update-Paketen nicht erlaubt.');
            }
            if ($isDirectory) {
               continue;
            }
            if ($this->isProtectedPath($relative)) {
               throw new RuntimeException('Das Update-Paket enthält lokale Laufzeitdaten: ' . $relative);
            }

            $caseKey = strtolower($relative);
            if (isset($caseMap[$caseKey])) {
               throw new RuntimeException('Das Update-Paket enthält kollidierende Dateinamen.');
            }
            $caseMap[$caseKey] = true;
            $totalSize += max(0, (int)($stat['size'] ?? 0));
            if ($totalSize > self::MAX_EXTRACTED_BYTES) {
               throw new RuntimeException('Das entpackte Update-Paket ist zu groß.');
            }
            $files[] = $relative;
         }

         foreach (array('VERSION', 'index.php', 'dbx/include/dbxApi.php', '.dbx-release-files.json') as $required) {
            if (!in_array($required, $files, true)) {
               throw new RuntimeException('Im Update-Paket fehlt ' . $required . '.');
            }
         }

         $version = trim((string)$zip->getFromName('VERSION'));
         if ($version !== $manifest['version']) {
            throw new RuntimeException('VERSION im Update-Paket stimmt nicht mit dem Manifest überein.');
         }

         $inventoryJson = $zip->getFromName('.dbx-release-files.json');
         $inventory = json_decode((string)$inventoryJson, true);
         if (!is_array($inventory)
            || (int)($inventory['schema'] ?? 0) !== 1
            || (string)($inventory['product'] ?? '') !== self::PRODUCT
            || (string)($inventory['version'] ?? '') !== $manifest['version']
            || !is_array($inventory['files'] ?? null)) {
            throw new RuntimeException('Das Update-Paket enthält kein gültiges Datei-Inventar.');
         }

         $hashes = array();
         foreach ($inventory['files'] as $relative => $expectedHash) {
            $relative = $this->normalizeArchivePath((string)$relative);
            if ($relative === '.dbx-release-files.json') {
               continue;
            }
            if (!in_array($relative, $files, true)
               || !is_string($expectedHash)
               || !preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
               throw new RuntimeException('Das Datei-Inventar ist unvollständig oder ungültig.');
            }
            $content = $zip->getFromName($relative);
            if (!is_string($content)
               || !hash_equals($expectedHash, hash('sha256', $content))) {
               throw new RuntimeException('Dateiprüfsumme stimmt nicht: ' . $relative);
            }
            $hashes[$relative] = $expectedHash;
         }

         $inventoryPaths = array_keys($inventory['files']);
         sort($inventoryPaths, SORT_STRING);
         sort($files, SORT_STRING);
         if ($inventoryPaths !== $files) {
            throw new RuntimeException('ZIP-Inhalt und Datei-Inventar sind nicht identisch.');
         }
         return array('files' => $files, 'hashes' => $hashes);
      } finally {
         $zip->close();
      }
   }

   /**
    * Installs the staged package with a complete changed-file backup.
    *
    * @return array<string,mixed>
    */
   public function install(): array
   {
      $stateFile = $this->workDirectory . DIRECTORY_SEPARATOR . 'staged.json';
      $state = $this->readJson($stateFile);
      if (!is_array($state['manifest'] ?? null)
         || !is_array($state['files'] ?? null)) {
         throw new RuntimeException('Es ist kein geprüftes Update bereitgestellt.');
      }

      $manifest = $this->validateManifest($state['manifest']);
      $zipFile = (string)($state['zip_file'] ?? '');
      $staging = (string)($state['staging_directory'] ?? '');
      if (!$this->isInside($zipFile, $this->workDirectory)
         || !$this->isInside($staging, $this->workDirectory)
         || !is_dir($staging)) {
         throw new RuntimeException('Der Update-Stagingbereich ist ungültig.');
      }
      $actualHash = is_file($zipFile) ? hash_file('sha256', $zipFile) : false;
      if (!is_string($actualHash)
         || !hash_equals($manifest['sha256'], strtolower($actualHash))) {
         throw new RuntimeException('Das bereitgestellte Update-Paket wurde verändert.');
      }

      $package = $this->inspectPackage($zipFile, $manifest);
      $this->verifyExtractedFiles($staging, $package['hashes']);
      $current = $this->currentVersion();
      if (!version_compare($manifest['version'], $current, '>')) {
         throw new RuntimeException('Das bereitgestellte Update ist nicht neuer als die Installation.');
      }

      $newFiles = $package['files'];
      $oldFiles = $this->installedInventory();
      $obsolete = array_values(array_diff($oldFiles, $newFiles));
      $backupRoot = $this->workDirectory . DIRECTORY_SEPARATOR . 'backups';
      $this->ensureDirectory($backupRoot);
      $backup = $backupRoot . DIRECTORY_SEPARATOR
         . preg_replace('/[^0-9A-Za-z._-]+/', '-', $current . '-to-' . $manifest['version'])
         . '-' . gmdate('Ymd-His');
      $backupFiles = $backup . DIRECTORY_SEPARATOR . 'files';
      $this->ensureDirectory($backupFiles);

      $entries = array();
      foreach (array_values(array_unique(array_merge($newFiles, $obsolete))) as $relative) {
         $relative = $this->normalizeArchivePath($relative);
         if ($relative === '' || $this->isProtectedPath($relative)) {
            continue;
         }
         $destination = $this->destination($relative);
         $existed = is_file($destination);
         if ($existed) {
            $backupFile = $backupFiles . DIRECTORY_SEPARATOR
               . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $this->ensureDirectory(dirname($backupFile));
            if (!copy($destination, $backupFile)) {
               throw new RuntimeException('Sicherung fehlgeschlagen: ' . $relative);
            }
         }
         $entries[] = array(
            'path' => $relative,
            'existed' => $existed,
            'obsolete' => in_array($relative, $obsolete, true),
         );
      }

      $backupState = array(
         'schema' => 1,
         'from_version' => $current,
         'to_version' => $manifest['version'],
         'created_at' => gmdate('c'),
         'backup_directory' => $backup,
         'entries' => $entries,
      );
      $this->writeJson($backup . DIRECTORY_SEPARATOR . 'backup.json', $backupState);

      try {
         foreach ($obsolete as $relative) {
            $relative = $this->normalizeArchivePath($relative);
            if ($relative === '' || $this->isProtectedPath($relative)) {
               continue;
            }
            $destination = $this->destination($relative);
            if (is_file($destination) && !unlink($destination)) {
               throw new RuntimeException('Veraltete Datei konnte nicht entfernt werden: ' . $relative);
            }
         }

         foreach ($newFiles as $relative) {
            $relative = $this->normalizeArchivePath($relative);
            $source = $staging . DIRECTORY_SEPARATOR
               . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $destination = $this->destination($relative);
            if (!is_file($source)) {
               throw new RuntimeException('Staging-Datei fehlt: ' . $relative);
            }
            $this->ensureDirectory(dirname($destination));
            if (!copy($source, $destination)) {
               throw new RuntimeException('Update-Datei konnte nicht installiert werden: ' . $relative);
            }
         }

         if ($this->currentVersion() !== $manifest['version']) {
            throw new RuntimeException('Die installierte VERSION konnte nicht bestätigt werden.');
         }
      } catch (Throwable $exception) {
         $this->restoreBackup($backupState);
         throw new RuntimeException(
            'Update fehlgeschlagen und wurde zurückgerollt: ' . $exception->getMessage(),
            0,
            $exception
         );
      }

      $installed = $backupState + array('installed_at' => gmdate('c'));
      $this->writeJson(
         $this->workDirectory . DIRECTORY_SEPARATOR . 'installed.json',
         $installed
      );
      if (function_exists('opcache_reset')) {
         @opcache_reset();
      }
      return $installed;
   }

   /**
    * Restores the complete changed-file backup of the last installation.
    *
    * @return array<string,mixed>
    */
   public function rollback(): array
   {
      $stateFile = $this->workDirectory . DIRECTORY_SEPARATOR . 'installed.json';
      $state = $this->readJson($stateFile);
      if (!is_array($state['entries'] ?? null)
         || !is_dir((string)($state['backup_directory'] ?? ''))
         || !empty($state['rolled_back_at'])) {
         throw new RuntimeException('Es ist keine rückrollbare Aktualisierung vorhanden.');
      }
      if ($this->currentVersion() !== (string)($state['to_version'] ?? '')) {
         throw new RuntimeException('Die installierte Version wurde seit dem Update verändert.');
      }

      $this->restoreBackup($state);
      $state['rolled_back_at'] = gmdate('c');
      $this->writeJson($stateFile, $state);
      if (function_exists('opcache_reset')) {
         @opcache_reset();
      }
      return $state;
   }

   /**
    * @param array<string,mixed> $state
    */
   private function restoreBackup(array $state): void
   {
      $backup = (string)($state['backup_directory'] ?? '');
      if (!$this->isInside($backup, $this->workDirectory)) {
         throw new RuntimeException('Das Sicherungsverzeichnis liegt außerhalb des Updatebereichs.');
      }
      $entries = is_array($state['entries'] ?? null) ? $state['entries'] : array();
      foreach (array_reverse($entries) as $entry) {
         $relative = $this->normalizeArchivePath((string)($entry['path'] ?? ''));
         if ($relative === '' || $this->isProtectedPath($relative)) {
            continue;
         }
         $destination = $this->destination($relative);
         if (!empty($entry['existed'])) {
            $source = $backup . DIRECTORY_SEPARATOR . 'files'
               . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_file($source)) {
               throw new RuntimeException('Sicherungsdatei fehlt: ' . $relative);
            }
            $this->ensureDirectory(dirname($destination));
            if (!copy($source, $destination)) {
               throw new RuntimeException('Sicherungsdatei konnte nicht wiederhergestellt werden: ' . $relative);
            }
         } elseif (is_file($destination) && !unlink($destination)) {
            throw new RuntimeException('Neu installierte Datei konnte nicht entfernt werden: ' . $relative);
         }
      }
   }

   /**
    * @return array<int,string>
    */
   private function installedInventory(): array
   {
      $inventory = $this->readJson(
         $this->root . DIRECTORY_SEPARATOR . '.dbx-release-files.json'
      );
      if ((int)($inventory['schema'] ?? 0) !== 1
         || (string)($inventory['product'] ?? '') !== self::PRODUCT
         || !is_array($inventory['files'] ?? null)) {
         return array();
      }

      $files = array();
      foreach (array_keys($inventory['files']) as $relative) {
         try {
            $relative = $this->normalizeArchivePath((string)$relative);
         } catch (Throwable) {
            continue;
         }
         if ($relative !== '' && !$this->isProtectedPath($relative)) {
            $files[] = $relative;
         }
      }
      return array_values(array_unique($files));
   }

   /**
    * @param array<string,string> $hashes
    */
   private function verifyExtractedFiles(string $staging, array $hashes): void
   {
      foreach ($hashes as $relative => $expected) {
         $file = $staging . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
         $actual = is_file($file) ? hash_file('sha256', $file) : false;
         if (!is_string($actual) || !hash_equals($expected, strtolower($actual))) {
            throw new RuntimeException('Staging-Datei wurde verändert: ' . $relative);
         }
      }
   }

   private function currentVersion(): string
   {
      $versionFile = $this->root . DIRECTORY_SEPARATOR . 'VERSION';
      $version = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '';
      if (!preg_match('/^\d+\.\d+\.\d+(?:-dev)?$/', $version)) {
         throw new RuntimeException('Die lokal installierte VERSION ist ungültig.');
      }
      return $version;
   }

   private function destination(string $relative): string
   {
      $relative = $this->normalizeArchivePath($relative);
      if ($relative === '' || $this->isProtectedPath($relative)) {
         throw new RuntimeException('Ungültiger Installationspfad: ' . $relative);
      }
      return $this->root . DIRECTORY_SEPARATOR
         . str_replace('/', DIRECTORY_SEPARATOR, $relative);
   }

   private function normalizeArchivePath(string $path): string
   {
      if (str_contains($path, "\0") || str_contains($path, '\\')) {
         throw new RuntimeException('Das Update-Paket enthält einen unsicheren Pfad.');
      }
      $path = trim($path);
      if ($path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
         return '';
      }
      $parts = explode('/', rtrim($path, '/'));
      foreach ($parts as $part) {
         if ($part === '' || $part === '.' || $part === '..') {
            throw new RuntimeException('Das Update-Paket enthält Pfad-Traversal.');
         }
      }
      return implode('/', $parts);
   }

   private function isProtectedPath(string $relative): bool
   {
      $relative = ltrim(str_replace('\\', '/', $relative), '/');
      $base = basename($relative);
      $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
      if ($base === '.gitkeep') {
         return false;
      }
      return $relative === '.env'
         || $relative === '.env.local'
         || str_starts_with($relative, '.git/')
         || str_starts_with($relative, 'files/')
         || str_starts_with($relative, 'dbx/files/')
         || preg_match('#/db/#i', '/' . $relative)
         || preg_match('#/(?:cache|tmp|work|backup|backups|_backup|\.backup|uploads)/#i', '/' . $relative)
         || in_array($base, array('config.local.php'), true)
         || in_array($extension, array(
            'db3', 'sqlite', 'sqlite3', 'log', 'tmp', 'bak', 'backup',
            'pem', 'key', 'p12', 'pfx',
         ), true);
   }

   private function downloadText(string $url, int $maximumBytes): string
   {
      $content = '';
      $this->curlRequest($url, static function ($handle, string $chunk) use (&$content, $maximumBytes): int {
         if (strlen($content) + strlen($chunk) > $maximumBytes) {
            return 0;
         }
         $content .= $chunk;
         return strlen($chunk);
      });
      return $content;
   }

   private function downloadFile(string $url, string $target, int $maximumBytes): void
   {
      $directory = dirname($target);
      $this->ensureDirectory($directory);
      $stream = fopen($target, 'wb');
      if (!is_resource($stream)) {
         throw new RuntimeException('Die Update-Download-Datei konnte nicht geöffnet werden.');
      }
      $written = 0;
      try {
         $this->curlRequest($url, static function ($handle, string $chunk) use ($stream, &$written, $maximumBytes): int {
            $length = strlen($chunk);
            if ($written + $length > $maximumBytes) {
               return 0;
            }
            $result = fwrite($stream, $chunk);
            if ($result !== $length) {
               return 0;
            }
            $written += $length;
            return $length;
         });
      } finally {
         fclose($stream);
      }
   }

   /**
    * @param callable $writer CURLOPT_WRITEFUNCTION-compatible callback
    */
   private function curlRequest(string $url, callable $writer): void
   {
      if (!extension_loaded('curl')) {
         throw new RuntimeException('Für die Update-Prüfung wird die PHP-Erweiterung curl benötigt.');
      }
      $this->assertTrustedUrl($url, false);
      $curl = curl_init($url);
      if ($curl === false) {
         throw new RuntimeException('Die HTTPS-Verbindung konnte nicht initialisiert werden.');
      }

      $options = array(
         CURLOPT_FOLLOWLOCATION => true,
         CURLOPT_MAXREDIRS => 5,
         CURLOPT_CONNECTTIMEOUT => 15,
         CURLOPT_TIMEOUT => 180,
         CURLOPT_USERAGENT => 'dbxApp-Updater/' . $this->currentVersion(),
         CURLOPT_SSL_VERIFYPEER => true,
         CURLOPT_SSL_VERIFYHOST => 2,
         CURLOPT_FAILONERROR => false,
         CURLOPT_WRITEFUNCTION => $writer,
      );
      if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
         $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
      }
      if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
         $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
      }
      curl_setopt_array($curl, $options);
      $ok = curl_exec($curl);
      $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
      $effective = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
      $error = curl_error($curl);
      curl_close($curl);

      if ($ok !== true || $status < 200 || $status >= 300) {
         throw new RuntimeException(
            'Update-Download fehlgeschlagen'
            . ($status > 0 ? ' (HTTP ' . $status . ')' : '')
            . ($error !== '' ? ': ' . $error : '.')
         );
      }
      $this->assertTrustedUrl($effective, true);
   }

   private function assertTrustedUrl(string $url, bool $allowAssetHost): void
   {
      $parts = parse_url($url);
      $scheme = strtolower((string)($parts['scheme'] ?? ''));
      $host = strtolower((string)($parts['host'] ?? ''));
      if ($scheme !== 'https') {
         throw new RuntimeException('Updates dürfen ausschließlich über HTTPS geladen werden.');
      }
      $hosts = array('github.com');
      if ($allowAssetHost) {
         $hosts[] = 'objects.githubusercontent.com';
         $hosts[] = 'release-assets.githubusercontent.com';
      }
      if (!in_array($host, $hosts, true)) {
         throw new RuntimeException('Der Update-Download wurde auf einen nicht vertrauten Host umgeleitet.');
      }
      if ($host === 'github.com') {
         $path = '/' . ltrim((string)($parts['path'] ?? ''), '/');
         if (!str_starts_with(strtolower($path), '/' . strtolower(self::OWNER_REPOSITORY) . '/releases/')) {
            throw new RuntimeException('Die Update-URL gehört nicht zum dbxApp-Releasebereich.');
         }
      }
   }

   /**
    * @return array<string,mixed>
    */
   private function readJson(string $file): array
   {
      if (!is_file($file)) {
         return array();
      }
      $json = file_get_contents($file);
      $decoded = is_string($json) ? json_decode($json, true) : null;
      return is_array($decoded) ? $decoded : array();
   }

   /**
    * @param array<string,mixed> $data
    */
   private function writeJson(string $file, array $data): void
   {
      $this->ensureDirectory(dirname($file));
      $json = json_encode(
         $data,
         JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
      );
      if (!is_string($json)) {
         throw new RuntimeException('Update-Status konnte nicht serialisiert werden.');
      }
      $temporary = $file . '.tmp';
      if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
         throw new RuntimeException('Update-Status konnte nicht geschrieben werden.');
      }
      if (is_file($file) && !unlink($file)) {
         @unlink($temporary);
         throw new RuntimeException('Alter Update-Status konnte nicht ersetzt werden.');
      }
      if (!rename($temporary, $file)) {
         @unlink($temporary);
         throw new RuntimeException('Update-Status konnte nicht aktiviert werden.');
      }
   }

   private function ensureDirectory(string $directory): void
   {
      if (!is_dir($directory)
         && !mkdir($directory, 0775, true)
         && !is_dir($directory)) {
         throw new RuntimeException('Verzeichnis konnte nicht erstellt werden: ' . $directory);
      }
   }

   private function isInside(string $path, string $root): bool
   {
      $path = str_replace('\\', '/', rtrim($path, '\\/'));
      $root = str_replace('\\', '/', rtrim($root, '\\/'));
      return $path !== $root
         && str_starts_with(strtolower($path), strtolower($root) . '/');
   }

   private function removeTree(string $directory, string $allowedRoot): void
   {
      if (!$this->isInside($directory, $allowedRoot) || !is_dir($directory)) {
         throw new RuntimeException('Unsicheres temporäres Löschziel wurde abgelehnt.');
      }
      $iterator = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($iterator as $item) {
         if ($item->isDir() && !$item->isLink()) {
            if (!rmdir($item->getPathname())) {
               throw new RuntimeException('Temporäres Verzeichnis konnte nicht entfernt werden.');
            }
         } elseif (!unlink($item->getPathname())) {
            throw new RuntimeException('Temporäre Datei konnte nicht entfernt werden.');
         }
      }
      if (!rmdir($directory)) {
         throw new RuntimeException('Temporärer Stagingbereich konnte nicht entfernt werden.');
      }
   }
}
