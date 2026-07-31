<?php

declare(strict_types=1);

/**
 * Builds and applies the public product mirror from the authoritative dbxApp
 * development directory.
 *
 * The allow-list deliberately separates product sources from runtime state.
 * GitHub-owned release files such as workflows, release notes and security
 * policy are not part of the managed set and therefore remain untouched.
 */
final class AuthoritativeSourceSync
{
   /** @var array<string,true> */
   private const ROOT_FILES = array(
      '.env.example' => true,
      '.htaccess' => true,
      'Doxyfile' => true,
      'VERSION' => true,
      'index.php' => true,
      'favicon.ico' => true,
      'favicon.png' => true,
      'logo.png' => true,
   );

   /** @var array<string,true> */
   private const SHARED_RELEASE_TOOLS = array(
      'tools/ci.php' => true,
      'tools/lib/AuthoritativeSourceSync.php' => true,
      'tools/sync-authoritative-source.php' => true,
      'tools/tests/authoritative_source_sync_test.php' => true,
   );

   /**
    * Returns whether a relative path belongs to the public source mirror.
    */
   public static function isManagedFile(string $relative): bool
   {
      $relative = self::normalizeRelative($relative);
      if ($relative === '' || str_contains($relative, '../')) {
         return false;
      }

      if (isset(self::ROOT_FILES[$relative])
         || isset(self::SHARED_RELEASE_TOOLS[$relative])
         || preg_match('/^\d{2}_[A-Za-z0-9_.-]+\.md$/', $relative)
         || preg_match('/^RELEASE_NOTES_[A-Za-z0-9_.-]+\.md$/', $relative)) {
         return true;
      }

      if (str_starts_with($relative, 'docs/')) {
         $base = basename($relative);
         $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
         return !preg_match('#/(?:cache|tmp|work|backup|backups|_backup|\.backup|uploads)/#i', '/' . $relative)
            && !in_array($base, array('.env', '.env.local', 'config.local.php'), true)
            && (in_array($base, array('LICENSE', 'README'), true)
               || in_array($extension, array(
                  'dox', 'md', 'txt', 'html', 'htm', 'css', 'js', 'php',
                  'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp',
               ), true));
      }

      if (!str_starts_with($relative, 'dbx/')) {
         return false;
      }

      $base = basename($relative);
      $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
      if (preg_match('#^dbx/vendor/#i', $relative)
         || preg_match('#^dbx/files/#i', $relative)
         || preg_match('#^dbx/modules/myLKW/#i', $relative)
         || preg_match('#/(?:cache|tmp|work|backup|backups|_backup|\.backup|uploads)/#i', '/' . $relative)
         || preg_match('#/db/#i', '/' . $relative)
         || in_array($base, array('.env', '.env.local', 'config.local.php'), true)
         || in_array($extension, array(
            'db3', 'sqlite', 'sqlite3', 'log', 'tmp', 'cache', 'bak',
            'backup', 'pdf', 'pem', 'key', 'p12', 'pfx',
         ), true)
         || preg_match('/\.(?:db3|sqlite|sqlite3)-(?:wal|shm|journal)$/i', $relative)) {
         return false;
      }

      if (preg_match('#^dbx/modules/dbxUser/(?:img|tpl/img)/avatar/#i', $relative)
         && basename($relative) !== 'avatar-0.png') {
         return false;
      }

      return true;
   }

   /**
    * Creates a deterministic copy/delete plan.
    *
    * @return array{
    *   copy:array<string,string>,
    *   delete:array<int,string>,
    *   unchanged:int,
    *   source_count:int,
    *   target_count:int
    * }
    */
   public static function plan(string $source, string $target): array
   {
      $source = self::realDirectory($source, 'Quellverzeichnis');
      $target = self::realDirectory($target, 'Zielverzeichnis');
      if (strcasecmp($source, $target) === 0) {
         throw new RuntimeException('Quelle und Ziel dürfen nicht identisch sein.');
      }

      self::assertSource($source);
      self::assertTarget($target);

      $sourceFiles = self::managedFiles($source);
      $targetFiles = self::managedFiles($target);
      $copy = array();
      $unchanged = 0;

      foreach ($sourceFiles as $relative => $absolute) {
         $targetFile = $target . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
         if (!is_file($targetFile)
            || !self::filesEquivalent($absolute, $targetFile)) {
            $copy[$relative] = $absolute;
         } else {
            $unchanged++;
         }
      }

      $delete = array_values(array_diff(array_keys($targetFiles), array_keys($sourceFiles)));
      sort($delete, SORT_STRING);

      return array(
         'copy' => $copy,
         'delete' => $delete,
         'unchanged' => $unchanged,
         'source_count' => count($sourceFiles),
         'target_count' => count($targetFiles),
      );
   }

   /**
    * Applies a previously created plan.
    *
    * The target must be a clean Git checkout. This makes every replacement
    * recoverable and prevents accidental mixing with hand-edited mirror files.
    *
    * @return array{copied:int,deleted:int}
    */
   public static function apply(string $source, string $target, array $plan): array
   {
      $source = self::realDirectory($source, 'Quellverzeichnis');
      $target = self::realDirectory($target, 'Zielverzeichnis');
      self::assertGitClean($target);

      $copied = 0;
      foreach (($plan['copy'] ?? array()) as $relative => $sourceFile) {
         if (!self::isManagedFile((string)$relative)
            || !is_file((string)$sourceFile)
            || !self::isInside((string)$sourceFile, $source)) {
            throw new RuntimeException('Ungültiger Kopierplan für ' . $relative);
         }

         $destination = $target . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, (string)$relative);
         $directory = dirname($destination);
         if (!is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)) {
            throw new RuntimeException('Zielverzeichnis konnte nicht erstellt werden: ' . $directory);
         }
         if (!copy((string)$sourceFile, $destination)) {
            throw new RuntimeException('Datei konnte nicht gespiegelt werden: ' . $relative);
         }
         $copied++;
      }

      $deleted = 0;
      foreach (($plan['delete'] ?? array()) as $relative) {
         $relative = self::normalizeRelative((string)$relative);
         if (!self::isManagedFile($relative)) {
            throw new RuntimeException('Ungültiger Löschplan für ' . $relative);
         }
         $destination = $target . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
         if (is_file($destination) && !unlink($destination)) {
            throw new RuntimeException('Veraltete Spiegeldatei konnte nicht entfernt werden: ' . $relative);
         }
         if (!is_file($destination)) {
            $deleted++;
            self::removeEmptyParents(dirname($destination), $target);
         }
      }

      return array('copied' => $copied, 'deleted' => $deleted);
   }

   /**
    * @return array<string,string>
    */
   private static function managedFiles(string $root): array
   {
      $files = array();
      $iterator = new RecursiveIteratorIterator(
         new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($iterator as $item) {
         if (!$item->isFile()) {
            continue;
         }
         $relative = self::normalizeRelative(substr($item->getPathname(), strlen($root) + 1));
         if (self::isManagedFile($relative)) {
            $files[$relative] = $item->getPathname();
         }
      }
      ksort($files, SORT_STRING);
      return $files;
   }

   private static function assertSource(string $source): void
   {
      if (!is_file($source . DIRECTORY_SEPARATOR . 'dbx'
         . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxApi.php')) {
         throw new RuntimeException('Die Quelle ist keine vollständige dbxApp-Entwicklungsinstanz.');
      }
   }

   private static function assertTarget(string $target): void
   {
      if (!is_dir($target . DIRECTORY_SEPARATOR . '.git')
         || !is_file($target . DIRECTORY_SEPARATOR . 'RELEASE_PROCESS.md')) {
         throw new RuntimeException('Das Ziel ist kein vorbereiteter dbxApp-GitHub-Spiegel.');
      }
   }

   private static function assertGitClean(string $target): void
   {
      $command = 'git -C ' . escapeshellarg($target) . ' status --porcelain';
      $output = array();
      $code = 0;
      exec($command, $output, $code);
      if ($code !== 0) {
         throw new RuntimeException('Der Git-Status des Zielverzeichnisses konnte nicht geprüft werden.');
      }
      if ($output !== array()) {
         throw new RuntimeException(
            'Der GitHub-Spiegel enthält lokale Änderungen. Erst committen oder bereinigen.'
         );
      }
   }

   private static function realDirectory(string $path, string $label): string
   {
      $resolved = realpath($path);
      if ($resolved === false || !is_dir($resolved)) {
         throw new RuntimeException($label . ' wurde nicht gefunden: ' . $path);
      }
      return rtrim($resolved, '\\/');
   }

   private static function normalizeRelative(string $path): string
   {
      return ltrim(str_replace('\\', '/', trim($path)), '/');
   }

   /**
    * Compares binary files byte-for-byte and text files independent of the
    * CRLF/LF checkout convention used by the two Windows directories.
    */
   private static function filesEquivalent(string $source, string $target): bool
   {
      $sourceHash = hash_file('sha256', $source);
      $targetHash = hash_file('sha256', $target);
      if (is_string($sourceHash)
         && $sourceHash !== ''
         && hash_equals($sourceHash, (string)$targetHash)) {
         return true;
      }

      $sourceContent = file_get_contents($source);
      $targetContent = file_get_contents($target);
      if (!is_string($sourceContent) || !is_string($targetContent)) {
         return false;
      }

      // NUL bytes identify binary payloads in the managed tree. Their hashes
      // must match exactly; no byte sequence is normalized.
      if (str_contains($sourceContent, "\0")
         || str_contains($targetContent, "\0")) {
         return false;
      }

      $normalize = static fn(string $content): string =>
         str_replace(array("\r\n", "\r"), "\n", $content);

      return hash_equals(
         hash('sha256', $normalize($sourceContent)),
         hash('sha256', $normalize($targetContent))
      );
   }

   private static function isInside(string $path, string $root): bool
   {
      $resolved = realpath($path);
      return $resolved !== false
         && str_starts_with(
            strtolower(str_replace('\\', '/', $resolved)),
            strtolower(str_replace('\\', '/', $root)) . '/'
         );
   }

   private static function removeEmptyParents(string $directory, string $root): void
   {
      $root = rtrim(str_replace('\\', '/', $root), '/');
      while (is_dir($directory)) {
         $normalized = rtrim(str_replace('\\', '/', $directory), '/');
         if ($normalized === $root || !str_starts_with($normalized, $root . '/')) {
            return;
         }
         $items = scandir($directory);
         if ($items === false || array_diff($items, array('.', '..')) !== array()) {
            return;
         }
         if (!rmdir($directory)) {
            return;
         }
         $directory = dirname($directory);
      }
   }
}
