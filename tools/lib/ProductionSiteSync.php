<?php

declare(strict_types=1);

/**
 * Erstellt einen schlanken, lauffähigen Produktionsspiegel aus der lokalen
 * dbxApp-Entwicklungsinstallation.
 */
final class ProductionSiteSync
{
   /** @var array<string,true> */
   private const ROOT_FILES = array(
      '.htaccess' => true,
      'VERSION' => true,
      'index.php' => true,
      'favicon.ico' => true,
      'favicon.png' => true,
      'dbXapp-Logo.jpeg' => true,
      '62c9ae385f7d410bdbxappindexnow20260708.txt' => true,
   );

   /** @var array<string,true> */
   private const FILE_AREAS = array(
      'db' => true,
      'dbxContent' => true,
      'download' => true,
      'license' => true,
      'media' => true,
      'mod' => true,
      'shop' => true,
      'user' => true,
   );

   /** @var array<string,true> */
   private const SYS_AREAS = array(
      'cfg' => true,
      'csv' => true,
      'install' => true,
   );

   public static function is_deployable_file(string $relative): bool
   {
      $relative = self::normalize($relative);
      if ($relative === '' || str_contains($relative, '../')) {
         return false;
      }
      if (!str_contains($relative, '/')) {
         return isset(self::ROOT_FILES[$relative]);
      }

      $base = basename($relative);
      $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
      if (self::is_local_configuration($relative)
         || in_array($base, array('Doxyfile', '.DS_Store', 'Thumbs.db'), true)
         || in_array($extension, array(
            'log', 'tmp', 'cache', 'bak', 'backup', 'old', 'orig',
            'pem', 'key', 'p12', 'pfx',
         ), true)) {
         return false;
      }

      if (str_starts_with($relative, 'dbx/')) {
         return !str_starts_with($relative, 'dbx/modules/dbxDocs/')
            && !str_starts_with($relative, 'dbx/design/dbxdocs/')
            && !preg_match(
            '#/(?:tests?|tools|docs?|documentation|reference|coverage|'
            . 'backup|backups|_backup|\.backup|tmp|temp|cache)(?:/|$)#i',
            '/' . $relative
         );
      }

      if (!str_starts_with($relative, 'files/')) {
         return false;
      }
      if (str_starts_with($relative, 'files/db/dd-backup/')) {
         return false;
      }
      if (preg_match('#^files/sys/([^/]+)/#', $relative, $match)) {
         return isset(self::SYS_AREAS[$match[1]]);
      }
      if (!preg_match('#^files/([^/]+)/#', $relative, $match)) {
         return false;
      }
      return isset(self::FILE_AREAS[$match[1]]);
   }

   public static function is_preserved_target_file(string $relative): bool
   {
      $relative = self::normalize($relative);
      return self::is_local_configuration($relative)
         || str_starts_with($relative, '.well-known/');
   }

   /** @return array{copy:array<string,string>,delete:list<string>,unchanged:int,preserved:int} */
   public static function plan(string $source, string $target): array
   {
      $source = self::directory($source, 'Quellverzeichnis');
      $target = self::directory($target, 'Zielverzeichnis');
      if (strcasecmp($source, $target) === 0) {
         throw new RuntimeException('Quelle und Ziel dürfen nicht identisch sein.');
      }
      self::assert_source($source);
      self::assert_target($target);

      $source_files = self::source_files($source);
      $target_files = self::all_files($target);
      $copy = array();
      $unchanged = 0;
      $preserved = 0;

      foreach ($source_files as $relative => $absolute) {
         $destination = self::absolute($target, $relative);
         if (is_file($destination) && self::equivalent($absolute, $destination)) {
            $unchanged++;
         } else {
            $copy[$relative] = $absolute;
         }
      }

      $delete = array();
      foreach ($target_files as $relative => $_absolute) {
         if (self::is_preserved_target_file($relative)) {
            $preserved++;
            continue;
         }
         if (!isset($source_files[$relative])) {
            $delete[] = $relative;
         }
      }
      sort($delete, SORT_STRING);

      return compact('copy', 'delete', 'unchanged', 'preserved');
   }

   /** @return array{copied:int,deleted:int} */
   public static function apply(string $source, string $target, array $plan): array
   {
      $source = self::directory($source, 'Quellverzeichnis');
      $target = self::directory($target, 'Zielverzeichnis');
      self::assert_source($source);
      self::assert_target($target);

      $copied = 0;
      foreach (($plan['copy'] ?? array()) as $relative => $source_file) {
         $relative = self::normalize((string)$relative);
         if (!self::is_deployable_file($relative)
            || !is_file((string)$source_file)
            || !self::is_inside((string)$source_file, $source)) {
            throw new RuntimeException('Ungültiger Kopierplan: ' . $relative);
         }
         $destination = self::absolute($target, $relative);
         $directory = dirname($destination);
         if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Zielordner konnte nicht erstellt werden: ' . $directory);
         }
         $temporary = $destination . '.dbx-sync-' . bin2hex(random_bytes(6));
         if (!copy((string)$source_file, $temporary)) {
            throw new RuntimeException('Datei konnte nicht kopiert werden: ' . $relative);
         }
         if (is_file($destination) && !unlink($destination)) {
            @unlink($temporary);
            throw new RuntimeException('Zieldatei konnte nicht ersetzt werden: ' . $relative);
         }
         if (!rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Temporäre Zieldatei konnte nicht aktiviert werden: ' . $relative);
         }
         $source_mtime = filemtime((string)$source_file);
         if (is_int($source_mtime)) {
            @touch($destination, $source_mtime);
         }
         $copied++;
      }

      $deleted = 0;
      foreach (($plan['delete'] ?? array()) as $relative) {
         $relative = self::normalize((string)$relative);
         if ($relative === '' || self::is_preserved_target_file($relative)) {
            throw new RuntimeException('Ungültiger Löschplan: ' . $relative);
         }
         $destination = self::absolute($target, $relative);
         if (is_file($destination) && !unlink($destination)) {
            throw new RuntimeException('Veraltete Datei konnte nicht entfernt werden: ' . $relative);
         }
         if (!is_file($destination)) {
            $deleted++;
            self::remove_empty_parents(dirname($destination), $target);
         }
      }

      return compact('copied', 'deleted');
   }

   /** @return array<string,string> */
   private static function source_files(string $root): array
   {
      return self::collect($root, static fn(string $path): bool => self::is_deployable_file($path));
   }

   /** @return array<string,string> */
   private static function all_files(string $root): array
   {
      return self::collect($root, static fn(string $_path): bool => true);
   }

   /** @return array<string,string> */
   private static function collect(string $root, callable $accept): array
   {
      $result = array();
      $iterator = new RecursiveIteratorIterator(
         new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($iterator as $item) {
         if (!$item->isFile()) {
            continue;
         }
         $relative = self::normalize(substr($item->getPathname(), strlen($root) + 1));
         if ($accept($relative)) {
            $result[$relative] = $item->getPathname();
         }
      }
      ksort($result, SORT_STRING);
      return $result;
   }

   private static function is_local_configuration(string $relative): bool
   {
      $base = basename(self::normalize($relative));
      return in_array($base, array('.env', '.env.local', 'config.local.php'), true);
   }

   private static function assert_source(string $root): void
   {
      if (!is_file(self::absolute($root, 'dbx/include/dbxApi.php'))
         || !is_file(self::absolute($root, 'VERSION'))) {
         throw new RuntimeException('Die Quelle ist keine vollständige dbxApp-Installation.');
      }
   }

   private static function assert_target(string $root): void
   {
      if (!is_file(self::absolute($root, 'index.php'))
         || !is_dir(self::absolute($root, 'dbx'))) {
         throw new RuntimeException('Das Ziel ist keine vorbereitete dbxApp-Webinstallation.');
      }
   }

   private static function directory(string $path, string $label): string
   {
      $resolved = realpath($path);
      if ($resolved === false || !is_dir($resolved)) {
         throw new RuntimeException($label . ' wurde nicht gefunden: ' . $path);
      }
      return rtrim($resolved, '\\/');
   }

   private static function absolute(string $root, string $relative): string
   {
      return $root . DIRECTORY_SEPARATOR
         . str_replace('/', DIRECTORY_SEPARATOR, self::normalize($relative));
   }

   private static function normalize(string $path): string
   {
      return ltrim(str_replace('\\', '/', trim($path)), '/');
   }

   private static function equivalent(string $source, string $target): bool
   {
      $source_size = filesize($source);
      $target_size = filesize($target);
      if ($source_size !== $target_size) {
         return false;
      }
      if ($source_size !== false && filemtime($source) === filemtime($target)) {
         return true;
      }
      $source_hash = hash_file('sha256', $source);
      $target_hash = hash_file('sha256', $target);
      return is_string($source_hash) && is_string($target_hash)
         && hash_equals($source_hash, $target_hash);
   }

   private static function is_inside(string $path, string $root): bool
   {
      $resolved = realpath($path);
      return $resolved !== false && str_starts_with(
         strtolower(str_replace('\\', '/', $resolved)),
         strtolower(str_replace('\\', '/', $root)) . '/'
      );
   }

   private static function remove_empty_parents(string $directory, string $root): void
   {
      $normalized_root = rtrim(str_replace('\\', '/', $root), '/');
      while (is_dir($directory)) {
         $normalized = rtrim(str_replace('\\', '/', $directory), '/');
         if ($normalized === $normalized_root
            || !str_starts_with($normalized, $normalized_root . '/')) {
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
