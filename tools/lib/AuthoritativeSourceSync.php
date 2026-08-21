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
      'AGENTS.md' => true,
      '.htaccess' => true,
      'Doxyfile' => true,
      'VERSION' => true,
      'dbx.package.json' => true,
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
   public static function is_managed_file(string $relative): bool
   {
      $relative = self::normalize_relative($relative);
      if ($relative === '' || str_contains($relative, '../')) {
         return false;
      }

      if (isset(self::ROOT_FILES[$relative])
         || isset(self::SHARED_RELEASE_TOOLS[$relative])
         || preg_match('/^\d{2}_[A-Za-z0-9_.-]+\.md$/', $relative)) {
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
         || preg_match('#^dbx/modules/myX/#i', $relative)
         || preg_match('#^dbx/modules/dbxMenu/tpl/htm/#i', $relative)
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
      $source = self::real_directory($source, 'Quellverzeichnis');
      $target = self::real_directory($target, 'Zielverzeichnis');
      if (strcasecmp($source, $target) === 0) {
         throw new RuntimeException('Quelle und Ziel dürfen nicht identisch sein.');
      }

      self::assert_source($source);
      self::assert_target($target);

      $source_files = self::managed_files($source);
      $target_files = self::managed_files($target);
      $copy = array();
      $unchanged = 0;

      foreach ($source_files as $relative => $absolute) {
         $target_file = $target . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
         if (!is_file($target_file)
            || !self::files_equivalent($absolute, $target_file)) {
            $copy[$relative] = $absolute;
         } else {
            $unchanged++;
         }
      }

      $delete = array_values(array_diff(array_keys($target_files), array_keys($source_files)));
      sort($delete, SORT_STRING);

      return array(
         'copy' => $copy,
         'delete' => $delete,
         'unchanged' => $unchanged,
         'source_count' => count($source_files),
         'target_count' => count($target_files),
      );
   }

   /**
    * Applies a previously created plan.
    *
    * Managed target files are replaced from the authoritative source. Unmanaged
    * GitHub release and policy files remain untouched.
    *
    * @return array{copied:int,deleted:int}
    */
   public static function apply(string $source, string $target, array $plan): array
   {
      $source = self::real_directory($source, 'Quellverzeichnis');
      $target = self::real_directory($target, 'Zielverzeichnis');
      $copied = 0;
      foreach (($plan['copy'] ?? array()) as $relative => $source_file) {
         if (!self::is_managed_file((string)$relative)
            || !is_file((string)$source_file)
            || !self::is_inside((string)$source_file, $source)) {
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
         if (!copy((string)$source_file, $destination)) {
            throw new RuntimeException('Datei konnte nicht gespiegelt werden: ' . $relative);
         }
         $copied++;
      }

      $deleted = 0;
      foreach (($plan['delete'] ?? array()) as $relative) {
         $relative = self::normalize_relative((string)$relative);
         if (!self::is_managed_file($relative)) {
            throw new RuntimeException('Ungültiger Löschplan für ' . $relative);
         }
         $destination = $target . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
         if (is_file($destination) && !unlink($destination)) {
            throw new RuntimeException('Veraltete Spiegeldatei konnte nicht entfernt werden: ' . $relative);
         }
         if (!is_file($destination)) {
            $deleted++;
            self::remove_empty_parents(dirname($destination), $target);
         }
      }

      return array('copied' => $copied, 'deleted' => $deleted);
   }

   /**
    * @return array<string,string>
    */
   private static function managed_files(string $root): array
   {
      $files = array();
      $iterator = new RecursiveIteratorIterator(
         new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($iterator as $item) {
         if (!$item->isFile()) {
            continue;
         }
         $relative = self::normalize_relative(substr($item->getPathname(), strlen($root) + 1));
         if (self::is_managed_file($relative)) {
            $files[$relative] = $item->getPathname();
         }
      }
      ksort($files, SORT_STRING);
      return $files;
   }

   private static function assert_source(string $source): void
   {
      if (!is_file($source . DIRECTORY_SEPARATOR . 'dbx'
         . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxApi.php')) {
         throw new RuntimeException('Die Quelle ist keine vollständige dbxApp-Entwicklungsinstanz.');
      }
   }

   private static function assert_target(string $target): void
   {
      $git_marker = $target . DIRECTORY_SEPARATOR . '.git';
      if ((!is_dir($git_marker) && !is_file($git_marker))
         || !is_file($target . DIRECTORY_SEPARATOR . 'RELEASE_PROCESS.md')) {
         throw new RuntimeException('Das Ziel ist kein vorbereiteter dbxApp-GitHub-Spiegel.');
      }
   }

   private static function real_directory(string $path, string $label): string
   {
      $resolved = realpath($path);
      if ($resolved === false || !is_dir($resolved)) {
         throw new RuntimeException($label . ' wurde nicht gefunden: ' . $path);
      }
      return rtrim($resolved, '\\/');
   }

   private static function normalize_relative(string $path): string
   {
      return ltrim(str_replace('\\', '/', trim($path)), '/');
   }

   /**
    * Compares binary files byte-for-byte and text files independent of the
    * CRLF/LF checkout convention used by the two Windows directories.
    */
   private static function files_equivalent(string $source, string $target): bool
   {
      $source_hash = hash_file('sha256', $source);
      $target_hash = hash_file('sha256', $target);
      if (is_string($source_hash)
         && $source_hash !== ''
         && hash_equals($source_hash, (string)$target_hash)) {
         return true;
      }

      $source_content = file_get_contents($source);
      $target_content = file_get_contents($target);
      if (!is_string($source_content) || !is_string($target_content)) {
         return false;
      }

      // NUL bytes identify binary payloads in the managed tree. Their hashes
      // must match exactly; no byte sequence is normalized.
      if (str_contains($source_content, "\0")
         || str_contains($target_content, "\0")) {
         return false;
      }

      $normalize = static fn(string $content): string =>
         str_replace(array("\r\n", "\r"), "\n", $content);

      return hash_equals(
         hash('sha256', $normalize($source_content)),
         hash('sha256', $normalize($target_content))
      );
   }

   private static function is_inside(string $path, string $root): bool
   {
      $resolved = realpath($path);
      return $resolved !== false
         && str_starts_with(
            strtolower(str_replace('\\', '/', $resolved)),
            strtolower(str_replace('\\', '/', $root)) . '/'
         );
   }

   private static function remove_empty_parents(string $directory, string $root): void
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
