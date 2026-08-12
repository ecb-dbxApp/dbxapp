<?php
namespace dbx\dbxAdmin;

/**
 * Modul-Bilder unter files/mod — Dateiname: {modul}__{run1}[__{run2}].ext
 */
class dbxModuleImages {

   public const REL_DIR = 'mod/';
   public const FILENAME_PARAM_SEP = '__';

   private $listCache = array();
   private $imageItemsCache = array();
   private $moduleSymbolCache = array();

   private function imageExtensionAliases(): array {
      return array(
         'jpeg' => 'jpg',
         'jpe'  => 'jpg',
         'tif'  => 'tiff',
      );
   }

   private function blockedImageExtensions(): array {
      return array('php', 'phtml', 'phar', 'svg', 'js', 'html', 'htm');
   }

   private function allowedImageExtensions(): array {
      return array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'tiff', 'tif', 'ico', 'heic', 'heif');
   }

   private function normalizeImageExtension(string $ext, string $fallback = 'jpg'): string {
      $ext = strtolower(preg_replace('/[^a-z0-9]+/', '', $ext));
      $aliases = $this->imageExtensionAliases();
      if (isset($aliases[$ext])) {
         $ext = $aliases[$ext];
      }
      if ($ext === '' || strlen($ext) > 8 || in_array($ext, $this->blockedImageExtensions(), true)) {
         return $fallback;
      }
      return $ext;
   }

   private function extensionFromImagePath(string $path, string $fallback = 'jpg'): string {
      $path = (string) $path;
      if ($path !== '' && is_file($path) && is_readable($path)) {
         $info = @getimagesize($path);
         if (is_array($info)) {
            $mime = strtolower((string) ($info['mime'] ?? ''));
            $fromMime = array(
               'image/jpeg'                => 'jpg',
               'image/png'                 => 'png',
               'image/gif'                 => 'gif',
               'image/webp'                => 'webp',
               'image/bmp'                 => 'bmp',
               'image/x-ms-bmp'            => 'bmp',
               'image/avif'                => 'avif',
               'image/tiff'                => 'tiff',
               'image/x-icon'              => 'ico',
               'image/vnd.microsoft.icon'  => 'ico',
               'image/heic'                => 'heic',
               'image/heif'                => 'heif',
            );
            if (isset($fromMime[$mime])) {
               return $fromMime[$mime];
            }

            $ext = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($path, PATHINFO_EXTENSION)));
            if ($ext !== '' && strlen($ext) <= 8) {
               return $ext;
            }
         }
      }

      return $this->normalizeImageExtension(pathinfo($path, PATHINFO_EXTENSION), $fallback);
   }

   private function isImagePath(string $path): bool {
      if ($path === '' || !is_file($path) || !is_readable($path)) {
         return false;
      }

      $info = @getimagesize($path);
      if (!is_array($info) || (int) ($info[0] ?? 0) <= 0 || (int) ($info[1] ?? 0) <= 0) {
         return false;
      }

      $mime = strtolower((string) ($info['mime'] ?? ''));
      if ($mime === '' || strpos($mime, 'image/') !== 0) {
         return false;
      }

      return $mime !== 'image/svg+xml';
   }

   private function mimeForExtension(string $ext): string {
      $ext = $this->normalizeImageExtension($ext);
      $map = array(
         'jpg'   => 'image/jpeg',
         'png'   => 'image/png',
         'gif'   => 'image/gif',
         'webp'  => 'image/webp',
         'bmp'   => 'image/bmp',
         'avif'  => 'image/avif',
         'tiff'  => 'image/tiff',
         'ico'   => 'image/x-icon',
         'heic'  => 'image/heic',
         'heif'  => 'image/heif',
      );

      return $map[$ext] ?? ('image/' . $ext);
   }

   public function absDir(): string {
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/' . self::REL_DIR;
      return dbx()->os_path($dir);
   }

   public function relPath(string $filename): string {
      $filename = $this->sanitizeFilename($filename);
      return $filename === '' ? '' : self::REL_DIR . $filename;
   }

   public function mediaApiUrl(): string {
      return '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_media';
   }

   public function mediaFoldersApiUrl(): string {
      return '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_media_folders';
   }

   public function urlBase(): string {
      return rtrim(dbx()->get_base_url(), '/\\') . '/files/' . self::REL_DIR;
   }

   public function moduleSymbolDir(string $modul, bool $create = false): string {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '' || !$this->isKnownModul($modul)) {
         return '';
      }

      $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/tpl/img/');
      if ($create && !is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }

      return is_dir($dir) ? rtrim($dir, '/\\') . DIRECTORY_SEPARATOR : $dir;
   }

   public function moduleSymbolUrlBase(string $modul): string {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '') {
         return '';
      }

      return rtrim(dbx()->get_base_url(), '/\\') . '/dbx/modules/' . rawurlencode($modul) . '/tpl/img/';
   }

   public function moduleSymbol(string $modul): array {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '') {
         return array('url' => '', 'alt' => '', 'badge' => '');
      }
      if (isset($this->moduleSymbolCache[$modul])) {
         return $this->moduleSymbolCache[$modul];
      }

      $dir = $this->moduleSymbolDir($modul, false);
      if ($dir !== '' && is_dir($dir) && is_readable($dir)) {
         $matches = glob($dir . $modul . '.*') ?: array();
         usort($matches, function ($a, $b) {
            $prio = array('jpg' => 1, 'jpeg' => 2, 'png' => 3, 'webp' => 4, 'gif' => 5);
            $ea = strtolower(pathinfo((string)$a, PATHINFO_EXTENSION));
            $eb = strtolower(pathinfo((string)$b, PATHINFO_EXTENSION));
            $pa = $prio[$ea] ?? 99;
            $pb = $prio[$eb] ?? 99;
            return ($pa <=> $pb) ?: strnatcasecmp((string)$a, (string)$b);
         });

         foreach ($matches as $path) {
            if (!$this->isImageFile(basename((string)$path), (string)$path)) {
               continue;
            }

            $file = basename((string)$path);
            $this->moduleSymbolCache[$modul] = array(
               'url'   => $this->moduleSymbolUrlBase($modul) . rawurlencode($file) . '?v=' . (int) @filemtime((string)$path),
               'alt'   => $modul,
               'badge' => 'tpl/img/' . $file,
               'file'  => $file,
               'path'  => 'dbx/modules/' . $modul . '/tpl/img/' . $file,
            );
            return $this->moduleSymbolCache[$modul];
         }
      }

      $this->moduleSymbolCache[$modul] = array(
         'url'   => '',
         'alt'   => $modul,
         'badge' => 'tpl/img/' . $modul . '.*',
         'file'  => '',
         'path'  => 'dbx/modules/' . $modul . '/tpl/img/' . $modul . '.*',
      );
      return $this->moduleSymbolCache[$modul];
   }

   public function importSymbolForModul(string $modul, int $mediaId = 0, string $sourceRel = ''): ?array {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '' || !$this->isKnownModul($modul)) {
         return null;
      }

      $rel = '';
      if ($mediaId > 0) {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return null;
         }
         $row = $db->select1('dbxMedia', $mediaId);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) {
            return null;
         }
         $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      } elseif ($sourceRel !== '') {
         $rel = ltrim(str_replace('\\', '/', trim($sourceRel)), '/');
      }

      if ($rel === '' || strpos($rel, '..') !== false) {
         return null;
      }

      $source = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel);
      if (!$this->isImagePath($source)) {
         return null;
      }

      $dir = $this->moduleSymbolDir($modul, true);
      if ($dir === '' || !is_dir($dir)) {
         return null;
      }

      $ext = $this->extensionFromImagePath($source);
      $targetFile = $modul . '.' . $ext;
      $target = $dir . $targetFile;
      $this->removeModuleSymbolVariants($modul, $targetFile);

      if ($source === $target && is_file($target)) {
         return $this->moduleSymbol($modul);
      }

      if (is_file($target)) {
         @unlink($target);
      }

      if (!@copy($source, $target)) {
         return null;
      }

      return $this->moduleSymbol($modul);
   }

   public function serveFile(string $filename): void {
      $filename = $this->sanitizeFilename($filename);
      if ($filename === '' || !$this->isImageFile($filename)) {
         http_response_code(404);
         exit;
      }

      $dir = realpath($this->absDir());
      $file = realpath($this->absPath($filename));
      if (!$dir || !$file || strpos($file, $dir) !== 0 || !$this->isImagePath($file)) {
         http_response_code(404);
         exit;
      }

      $mime = function_exists('mime_content_type') ? (string) @mime_content_type($file) : '';
      if ($mime === '' || strpos(strtolower($mime), 'image/') !== 0) {
         $mime = $this->mimeForExtension(pathinfo($filename, PATHINFO_EXTENSION));
      }

      if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
         session_write_close();
      }

      $size = (int)@filesize($file);
      header('Content-Type: ' . $mime);
      header('Content-Length: ' . max(0, $size));
      header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
      header('Cache-Control: public, max-age=86400');
      header('X-Content-Type-Options: nosniff');
      readfile($file);
      exit;
   }

   public function ensureDir(): void {
      $dir = $this->absDir();
      if (!is_dir($dir)) {
         mkdir($dir, 0777, true);
      }
   }

   public function getList(string $modul): array {
      return $this->scanList($modul);
   }

   public function scanList(string $modul): array {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '') {
         return array();
      }
      if (isset($this->listCache[$modul])) {
         return $this->listCache[$modul];
      }

      $this->ensureDir();
      $dir = $this->absDir();
      if (!is_dir($dir) || !is_readable($dir)) {
         $this->listCache[$modul] = array();
         return array();
      }

      $out = array();
      foreach (glob($dir . '*') ?: array() as $path) {
         if (!is_file($path) || !is_readable($path)) {
            continue;
         }
         $name = $this->sanitizeFilename(basename($path));
         if ($name === '' || !$this->isImageFile($name, $path)) {
            continue;
         }
         if ($this->fileBelongsToModul($modul, $name)) {
            $out[] = $name;
         }
      }

      $out = array_values(array_unique($out));
      sort($out, SORT_NATURAL | SORT_FLAG_CASE);
      $this->listCache[$modul] = $out;
      return $this->listCache[$modul];
   }

   public function saveList(string $modul, array $files): bool {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '') {
         return false;
      }

      $allowed = array_flip($this->scanList($modul));
      foreach ($files as $file) {
         $name = $this->sanitizeFilename((string)$file);
         if ($name !== '' && !isset($allowed[$name])) {
            return false;
         }
      }

      return true;
   }

   public function addToList(string $modul, string $filename): bool {
      $filename = $this->sanitizeFilename($filename);
      return $filename !== ''
         && $this->fileExists($filename)
         && $this->fileBelongsToModul($modul, $filename);
   }

   public function removeFromList(string $modul, string $filename, bool $deletePhysical = true): bool {
      $filename = $this->sanitizeFilename($filename);
      if ($filename === '' || !$this->fileBelongsToModul($modul, $filename)) {
         return false;
      }

      if ($deletePhysical) {
         $path = $this->absPath($filename);
         if (is_file($path)) {
            @unlink($path);
         }
      }

      return true;
   }

   public function importForModul(string $modul, int $mediaId = 0, string $sourceRel = '', string $run1 = '', string $run2 = ''): ?string {
      $modul = $this->sanitizeModul($modul);
      $run1 = $this->sanitizeRunPart($run1);
      $run2 = $this->sanitizeRunPart($run2);
      if ($modul === '' || $run1 === '') {
         return null;
      }

      $rel = '';
      if ($mediaId > 0) {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return null;
         }
         $row = $db->select1('dbxMedia', $mediaId);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) {
            return null;
         }
         $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      } elseif ($sourceRel !== '') {
         $rel = ltrim(str_replace('\\', '/', trim($sourceRel)), '/');
      }

      if ($rel === '' || strpos($rel, '..') !== false) {
         return null;
      }

      $source = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      $source = dbx()->os_path($source);
      if (!is_file($source) || !is_readable($source)) {
         return null;
      }

      if (!$this->isImagePath($source)) {
         return null;
      }

      $ext = $this->extensionFromImagePath($source);
      $targetName = $this->filenameForRuns($modul, $run1, $run2, $ext);
      if ($targetName === '') {
         return null;
      }

      $this->ensureDir();
      $dest = $this->absPath($targetName);
      if ($dest !== '' && $source === $dest && is_file($dest)) {
         return $targetName;
      }

      return $this->copyFromMediaRel($modul, $rel, $targetName);
   }

   public function saveFromUpload(string $modul, string $run1, string $run2, array $file): ?string {
      $modul = $this->sanitizeModul($modul);
      $run1 = $this->sanitizeRunPart($run1);
      $run2 = $this->sanitizeRunPart($run2);
      if ($modul === '' || $run1 === '' || empty($file) || !is_array($file)) {
         return null;
      }
      if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
         return null;
      }

      $tmp = (string)($file['tmp_name'] ?? '');
      if ($tmp === '' || !is_uploaded_file($tmp)) {
         return null;
      }

      if (!$this->isImagePath($tmp)) {
         return null;
      }

      $ext = $this->extensionFromImagePath($tmp, $this->extensionFromImagePath((string) ($file['name'] ?? '')));
      $targetName = $this->filenameForRuns($modul, $run1, $run2, $ext);
      if ($targetName === '') {
         return null;
      }

      $this->ensureDir();
      $dest = $this->absPath($targetName);
      if ($dest === '') {
         return null;
      }

      if (is_file($dest)) {
         @unlink($dest);
      }

      if (!@move_uploaded_file($tmp, $dest)) {
         return null;
      }

      return $targetName;
   }

   public function stemForRuns(string $modul, string $run1, string $run2 = ''): string {
      $modul = $this->sanitizeModul($modul);
      $run1 = $this->sanitizeRunPart($run1);
      $run2 = $this->sanitizeRunPart($run2);
      if ($modul === '' || $run1 === '') {
         return $modul;
      }

      $stem = $modul . self::FILENAME_PARAM_SEP . $run1;
      if ($run2 !== '') {
         $stem .= self::FILENAME_PARAM_SEP . $run2;
      }

      return $stem;
   }

   public function filenameForRuns(string $modul, string $run1, string $run2 = '', string $ext = 'jpg'): string {
      $modul = $this->sanitizeModul($modul);
      $run1 = $this->sanitizeRunPart($run1);
      $run2 = $this->sanitizeRunPart($run2);
      $ext = $this->normalizeImageExtension($ext);

      if ($modul === '' || $run1 === '') {
         return '';
      }

      return $this->stemForRuns($modul, $run1, $run2) . '.' . $ext;
   }

   public function getUrl(string $filename): string {
      $filename = $this->sanitizeFilename($filename);
      if ($filename === '') {
         return '';
      }
      return $this->urlBase() . rawurlencode($filename);
   }

   public function fileExists(string $filename): bool {
      $path = $this->absPath($filename);
      return $path !== '' && is_file($path) && is_readable($path);
   }

   public function absPath(string $filename): string {
      $filename = $this->sanitizeFilename($filename);
      if ($filename === '') {
         return '';
      }
      return $this->absDir() . $filename;
   }

   public function copyFromMediaRel(string $modul, string $sourceRel, string $targetName = ''): ?string {
      $modul = $this->sanitizeModul($modul);
      $sourceRel = ltrim(str_replace('\\', '/', trim($sourceRel)), '/');
      if ($modul === '' || $sourceRel === '' || strpos($sourceRel, '..') !== false) {
         return null;
      }

      $source = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $sourceRel;
      $source = dbx()->os_path($source);
      if (!is_file($source) || !is_readable($source)) {
         return null;
      }

      if (!$this->isImagePath($source)) {
         return null;
      }

      if ($targetName === '') {
         $targetName = $this->suggestFilename($modul, basename($source));
      } else {
         $targetName = $this->sanitizeFilename($targetName);
         if ($targetName !== '' && pathinfo($targetName, PATHINFO_EXTENSION) === '') {
            $targetName .= '.' . $this->extensionFromImagePath($source);
         }
      }

      if ($targetName === '') {
         return null;
      }

      $this->ensureDir();
      $dest = $this->absPath($targetName);
      if ($dest === '') {
         return null;
      }

      if (is_file($dest) && $source !== $dest) {
         @unlink($dest);
      }

      if (!@copy($source, $dest)) {
         return null;
      }

      return $targetName;
   }

   public function copyFromMediaId(string $modul, int $mediaId): ?string {
      return $this->importForModul($modul, $mediaId, '');
   }

   public function suggestFilename(string $modul, string $sourceName): string {
      $modul = $this->sanitizeModul($modul);
      $base = pathinfo(basename($sourceName), PATHINFO_FILENAME);
      $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$base);
      $base = trim((string)$base, '-_');
      $ext = $this->normalizeImageExtension(pathinfo($sourceName, PATHINFO_EXTENSION));

      if ($base === '') {
         $base = 'image';
      }

      $prefix = $modul . self::FILENAME_PARAM_SEP;
      if (strpos($base, $prefix) !== 0 && $base !== $modul) {
         $base = $prefix . $base;
      }

      $name = $base . '.' . $ext;
      if (!$this->fileExists($name)) {
         return $name;
      }

      $i = 2;
      while ($i < 1000) {
         $candidate = $base . self::FILENAME_PARAM_SEP . $i . '.' . $ext;
         if (!$this->fileExists($candidate)) {
            return $candidate;
         }
         $i++;
      }

      return $base . self::FILENAME_PARAM_SEP . substr(md5((string)microtime(true)), 0, 6) . '.' . $ext;
   }

   public function resolveFromFilename(string $filename): array {
      $filename = $this->sanitizeFilename($filename);
      if ($filename === '' || !$this->isImageFile($filename) || !$this->fileExists($filename)) {
         return array();
      }

      $modul = $this->resolveModulFromFilename($filename);
      if ($modul === '') {
         return array();
      }

      $item = $this->catalogItem($modul, $filename);
      return is_array($item) ? $item : array();
   }

   public function resolveModulFromFilename(string $filename): string {
      $filename = $this->sanitizeFilename($filename);
      if ($filename === '') {
         return '';
      }

      $base = pathinfo($filename, PATHINFO_FILENAME);
      if ($base === '') {
         return '';
      }

      $sep = self::FILENAME_PARAM_SEP;
      if (strpos($base, $sep) !== false) {
         $modul = $this->sanitizeModul((string) strstr($base, $sep, true));
         if ($modul !== '' && $this->isKnownModul($modul)) {
            return $modul;
         }
         return '';
      }

      if ($this->isKnownModul($base)) {
         return $this->sanitizeModul($base);
      }

      return '';
   }

   public function catalogForModul(string $modul): array {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '') {
         return array();
      }

      $scan = $this->scanRuns($modul);
      $items = array();

      foreach ($this->getList($modul) as $file) {
         $items[] = $this->catalogItem($modul, $file, $scan);
      }

      return $items;
   }

   public function catalogItem(string $modul, string $filename, array $scan = null): array {
      $modul = $this->sanitizeModul($modul);
      $filename = $this->sanitizeFilename($filename);
      if ($modul === '' || $filename === '') {
         return array();
      }

      if (!is_array($scan)) {
         $scan = $this->scanRuns($modul);
      }

      $parsed = $this->parseImageName($modul, $filename, $scan);
      $label = (string)($parsed['label'] ?? $filename);
      $params = (string)($parsed['default_params'] ?? '');
      $marker = '[modul=' . $modul . ']' . $params . '[/modul]';

      return array(
         'id'             => pathinfo($filename, PATHINFO_FILENAME),
         'file'           => $filename,
         'label'          => $label,
         'description'    => $marker,
         'url'            => $this->getUrl($filename),
         'default_modul'  => $modul,
         'default_params' => $params,
         'default_alt'    => $modul . ': ' . $label,
         'run1'           => (string)($parsed['run1'] ?? ''),
         'run2'           => (string)($parsed['run2'] ?? ''),
      );
   }

   public function imageCount(string $modul): int {
      return count($this->getList($modul));
   }

   public function primaryGraphic(string $modul, string $run1 = '', string $run2 = ''): array {
      $modul = $this->sanitizeModul($modul);
      $list = $this->getList($modul);
      if (!$list) {
         return array('url' => '', 'alt' => $modul, 'badge' => '');
      }

      $scan = $this->scanRuns($modul);
      $candidates = array();
      if ($run1 !== '') {
         if ($run2 !== '') {
            $candidates[] = $this->stemForRuns($modul, $run1, $run2);
         }
         $candidates[] = $this->stemForRuns($modul, $run1, '');
      }
      $candidates[] = $modul;

      foreach ($candidates as $stem) {
         foreach ($list as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            if ($base === $stem) {
               return array(
                  'url'   => $this->getUrl($file),
                  'alt'   => $modul,
                  'badge' => $file,
               );
            }
         }
      }

      $first = $list[0];
      return array(
         'url'   => $this->getUrl($first),
         'alt'   => $modul,
         'badge' => $first,
      );
   }

   public function imageItems(string $modul): array {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '') {
         return array();
      }
      if (isset($this->imageItemsCache[$modul])) {
         return $this->imageItemsCache[$modul];
      }

      $scan = $this->scanRuns($modul);
      $items = array();
      foreach ($this->getList($modul) as $file) {
         $item = $this->catalogItem($modul, $file, $scan);
         if ($item) {
            $items[] = $item;
         }
      }
      $this->imageItemsCache[$modul] = $items;
      return $this->imageItemsCache[$modul];
   }

   public function mediaBrowserRows(string $modulFilter = ''): array {
      $modulFilter = $this->sanitizeModul($modulFilter);
      $this->ensureDir();
      $dir = $this->absDir();
      if (!is_dir($dir) || !is_readable($dir)) {
         return array();
      }

      $rows = array();
      foreach (glob($dir . '*') ?: array() as $path) {
         if (!is_file($path) || !is_readable($path)) {
            continue;
         }
         $name = $this->sanitizeFilename(basename($path));
         if ($name === '' || !$this->isImageFile($name, $path)) {
            continue;
         }
         if ($modulFilter !== '' && !$this->fileBelongsToModul($modulFilter, $name)) {
            continue;
         }

         $rel = $this->relPath($name);
         $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
         if ($mime === '' || strpos(strtolower($mime), 'image/') !== 0) {
            $mime = $this->mimeForExtension(pathinfo($name, PATHINFO_EXTENSION));
         }

         $title = pathinfo($name, PATHINFO_FILENAME);
         $rows[] = array(
            'id'           => abs(crc32($rel)),
            'file_name'    => $name,
            'file_path'    => $rel,
            'title'        => $title,
            'alt'          => $title,
            'url'          => $this->getUrl($name),
            'thumb_url'    => $this->getUrl($name),
            'mime'         => $mime,
            'media_type'   => 'image',
            'media_folder' => 'mod',
            'slot'         => 'gallery',
            'size'         => (int)@filesize($path),
         );
      }

      usort($rows, function ($a, $b) {
         return strnatcasecmp((string)($a['file_name'] ?? ''), (string)($b['file_name'] ?? ''));
      });

      return $rows;
   }


   private function fileBelongsToModul(string $modul, string $filename): bool {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '') {
         return false;
      }

      return $this->resolveModulFromFilename($filename) === $modul;
   }

   private function removeModuleSymbolVariants(string $modul, string $keepFile = ''): void {
      $modul = $this->sanitizeModul($modul);
      $keepFile = $this->sanitizeFilename($keepFile);
      $dir = $this->moduleSymbolDir($modul, false);
      if ($modul === '' || $dir === '' || !is_dir($dir)) {
         return;
      }

      foreach (glob($dir . $modul . '.*') ?: array() as $path) {
         $file = basename((string)$path);
         if ($file === $keepFile) {
            continue;
         }
         if (is_file($path)) {
            @unlink($path);
         }
      }
   }

   private function isImageFile(string $filename, string $absPath = ''): bool {
      $filename = $this->sanitizeFilename($filename);
      if ($filename === '') {
         return false;
      }

      if ($absPath === '') {
         $absPath = $this->absPath($filename);
      }

      $ext = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($filename, PATHINFO_EXTENSION)));
      if ($ext === '' || strlen($ext) > 8 || in_array($ext, $this->blockedImageExtensions(), true)) {
         return false;
      }

      return in_array($ext, $this->allowedImageExtensions(), true);
   }

   private function scanRuns(string $modul): array {
      static $cache = array();
      if (isset($cache[$modul])) {
         return $cache[$modul];
      }

      $cache[$modul] = $this->scanRunsInline($modul);
      return $cache[$modul];
   }

   private function scanRunsInline(string $modul): array {
      $run1 = array();
      $usesRun2 = false;
      $base = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . DIRECTORY_SEPARATOR);
      $files = array();
      $main = $base . $modul . '.class.php';
      if (is_file($main)) {
         $files[] = $main;
      }
      $inc = $base . 'include' . DIRECTORY_SEPARATOR;
      if (is_dir($inc)) {
         foreach (glob($inc . '*.class.php') ?: array() as $path) {
            if (is_file($path)) {
               $files[] = $path;
            }
         }
      }

      foreach ($files as $file) {
         $src = @file_get_contents($file);
         if (!is_string($src) || $src === '') {
            continue;
         }
         if (preg_match("/get_modul_var\s*\(\s*['\"]dbx_run2['\"]/", $src)) {
            $usesRun2 = true;
         }
         if (preg_match_all("/case\s+['\"]([^'\"]+)['\"]\s*:/", $src, $matches)) {
            foreach ($matches[1] as $case) {
               $case = trim((string)$case);
               if ($case !== '' && $case !== 'default') {
                  $run1[$case] = true;
               }
            }
         }
      }

      $run1List = array_keys($run1);
      usort($run1List, function ($a, $b) {
         return strlen($b) <=> strlen($a);
      });

      return array(
         'run1'         => array_values(array_keys($run1)),
         'uses_run2'    => $usesRun2,
         'run1_sorted'  => $run1List,
      );
   }

   private function parseImageName(string $modul, string $filename, array $scan): array {
      $base = pathinfo($filename, PATHINFO_FILENAME);
      if ($base === $modul) {
         return array(
            'run1'           => '',
            'run2'           => '',
            'default_params' => '',
            'label'          => $modul,
         );
      }

      $sep = self::FILENAME_PARAM_SEP;
      $prefix = $modul . $sep;
      if (strpos($base, $prefix) === 0) {
         $rest = substr($base, strlen($prefix));
         $parts = explode($sep, $rest, 2);
         $run1 = trim((string)($parts[0] ?? ''));
         $run2 = trim((string)($parts[1] ?? ''));
         return $this->buildParsedRuns($run1, $run2, $base);
      }

      return array(
         'run1'           => '',
         'run2'           => '',
         'default_params' => '',
         'label'          => $base,
      );
   }

   private function buildParsedRuns(string $run1, string $run2, string $fallbackLabel): array {
      $params = $this->buildParams($run1, $run2);
      $label = $this->runLabel($run1);
      if ($run2 !== '') {
         $label .= ' / ' . $this->runLabel($run2);
      }
      if ($label === '') {
         $label = $fallbackLabel;
      }

      return array(
         'run1'           => $run1,
         'run2'           => $run2,
         'default_params' => $params,
         'label'          => $label,
      );
   }

   private function knownModulNames(): array {
      static $names = null;
      if (is_array($names)) {
         return $names;
      }

      $names = array();
      $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');
      if (!is_dir($dir)) {
         return $names;
      }

      foreach (glob($dir . '*', GLOB_ONLYDIR) ?: array() as $path) {
         $name = basename(str_replace('\\', '/', $path));
         if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            continue;
         }
         $class = dbx()->os_path($path . DIRECTORY_SEPARATOR . $name . '.class.php');
         if (!is_file($class)) {
            continue;
         }
         $names[] = $name;
      }

      usort($names, function ($a, $b) {
         return strlen($b) <=> strlen($a);
      });

      return $names;
   }

   private function isKnownModul(string $modul): bool {
      $modul = $this->sanitizeModul($modul);
      if ($modul === '') {
         return false;
      }

      foreach ($this->knownModulNames() as $name) {
         if ($name === $modul) {
            return true;
         }
      }

      return is_file(dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/' . $modul . '.class.php'));
   }

   private function buildParams(string $run1, string $run2 = ''): string {
      $params = array();
      if ($run1 !== '') {
         $params[] = 'dbx_run1=' . rawurlencode($run1);
      }
      if ($run2 !== '') {
         $params[] = 'dbx_run2=' . rawurlencode($run2);
      }
      return implode('&', $params);
   }

   private function runLabel(string $action): string {
      $action = trim($action);
      if ($action === '') {
         return '';
      }
      return ucfirst(str_replace('_', ' ', $action));
   }

   private function sanitizeModul(string $modul): string {
      return preg_replace('/[^A-Za-z0-9_]+/', '', trim($modul));
   }

   private function sanitizeRunPart(string $part): string {
      return preg_replace('/[^A-Za-z0-9_-]+/', '', trim($part));
   }

   private function sanitizeFilename(string $filename): string {
      $filename = basename(str_replace('\\', '/', trim($filename)));
      $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $filename);
      return trim($filename, '-.');
   }
}
