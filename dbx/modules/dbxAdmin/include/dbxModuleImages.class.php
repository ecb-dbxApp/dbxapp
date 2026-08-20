<?php
namespace dbx\dbxAdmin;

/**
 * Modul-Bilder unter files/mod — Dateiname: {modul}__{run1}[__{run2}].ext
 */
class dbxModuleImages {

   public const REL_DIR = 'mod/';
   public const FILENAME_PARAM_SEP = '__';

   private $list_cache = array();
   private $image_items_cache = array();
   private $module_symbol_cache = array();

   private function image_extension_aliases(): array {
      return array(
         'jpeg' => 'jpg',
         'jpe'  => 'jpg',
         'tif'  => 'tiff',
      );
   }

   private function blocked_image_extensions(): array {
      return array('php', 'phtml', 'phar', 'svg', 'js', 'html', 'htm');
   }

   private function allowed_image_extensions(): array {
      return array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'tiff', 'tif', 'ico', 'heic', 'heif');
   }

   private function normalize_image_extension(string $ext, string $fallback = 'jpg'): string {
      $ext = strtolower(preg_replace('/[^a-z0-9]+/', '', $ext));
      $aliases = $this->image_extension_aliases();
      if (isset($aliases[$ext])) {
         $ext = $aliases[$ext];
      }
      if ($ext === '' || strlen($ext) > 8 || in_array($ext, $this->blocked_image_extensions(), true)) {
         return $fallback;
      }
      return $ext;
   }

   private function extension_from_image_path(string $path, string $fallback = 'jpg'): string {
      $path = (string) $path;
      if ($path !== '' && is_file($path) && is_readable($path)) {
         $info = @getimagesize($path);
         if (is_array($info)) {
            $mime = strtolower((string) ($info['mime'] ?? ''));
            $from_mime = array(
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
            if (isset($from_mime[$mime])) {
               return $from_mime[$mime];
            }

            $ext = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($path, PATHINFO_EXTENSION)));
            if ($ext !== '' && strlen($ext) <= 8) {
               return $ext;
            }
         }
      }

      return $this->normalize_image_extension(pathinfo($path, PATHINFO_EXTENSION), $fallback);
   }

   private function is_image_path(string $path): bool {
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

   private function mime_for_extension(string $ext): string {
      $ext = $this->normalize_image_extension($ext);
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

   public function abs_dir(): string {
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/' . self::REL_DIR;
      return dbx()->os_path($dir);
   }

   public function rel_path(string $filename): string {
      $filename = $this->sanitize_filename($filename);
      return $filename === '' ? '' : self::REL_DIR . $filename;
   }

   public function media_api_url(): string {
      return '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_media';
   }

   public function media_folders_api_url(): string {
      return '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_media_folders';
   }

   public function url_base(): string {
      return rtrim(dbx()->get_base_url(), '/\\') . '/files/' . self::REL_DIR;
   }

   public function module_symbol_dir(string $modul, bool $create = false): string {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '' || !$this->is_known_modul($modul)) {
         return '';
      }

      $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/tpl/img/');
      if ($create && !is_dir($dir)) {
         @mkdir($dir, 0777, true);
      }

      return is_dir($dir) ? rtrim($dir, '/\\') . DIRECTORY_SEPARATOR : $dir;
   }

   public function module_symbol_url_base(string $modul): string {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '') {
         return '';
      }

      return rtrim(dbx()->get_base_url(), '/\\') . '/dbx/modules/' . rawurlencode($modul) . '/tpl/img/';
   }

   public function module_symbol(string $modul): array {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '') {
         return array('url' => '', 'alt' => '', 'badge' => '');
      }
      if (isset($this->module_symbol_cache[$modul])) {
         return $this->module_symbol_cache[$modul];
      }

      $dir = $this->module_symbol_dir($modul, false);
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
            if (!$this->is_image_file(basename((string)$path), (string)$path)) {
               continue;
            }

            $file = basename((string)$path);
            $this->module_symbol_cache[$modul] = array(
               'url'   => $this->module_symbol_url_base($modul) . rawurlencode($file) . '?v=' . (int) @filemtime((string)$path),
               'alt'   => $modul,
               'badge' => 'tpl/img/' . $file,
               'file'  => $file,
               'path'  => 'dbx/modules/' . $modul . '/tpl/img/' . $file,
            );
            return $this->module_symbol_cache[$modul];
         }
      }

      $this->module_symbol_cache[$modul] = array(
         'url'   => '',
         'alt'   => $modul,
         'badge' => 'tpl/img/' . $modul . '.*',
         'file'  => '',
         'path'  => 'dbx/modules/' . $modul . '/tpl/img/' . $modul . '.*',
      );
      return $this->module_symbol_cache[$modul];
   }

   public function import_symbol_for_modul(string $modul, int $media_id = 0, string $source_rel = ''): ?array {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '' || !$this->is_known_modul($modul)) {
         return null;
      }

      $rel = '';
      if ($media_id > 0) {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return null;
         }
         $row = $db->select1('dbxMedia', $media_id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) {
            return null;
         }
         $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      } elseif ($source_rel !== '') {
         $rel = ltrim(str_replace('\\', '/', trim($source_rel)), '/');
      }

      if ($rel === '' || strpos($rel, '..') !== false) {
         return null;
      }

      $source = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel);
      if (!$this->is_image_path($source)) {
         return null;
      }

      $dir = $this->module_symbol_dir($modul, true);
      if ($dir === '' || !is_dir($dir)) {
         return null;
      }

      $ext = $this->extension_from_image_path($source);
      $target_file = $modul . '.' . $ext;
      $target = $dir . $target_file;
      $this->remove_module_symbol_variants($modul, $target_file);

      if ($source === $target && is_file($target)) {
         return $this->module_symbol($modul);
      }

      if (is_file($target)) {
         @unlink($target);
      }

      if (!@copy($source, $target)) {
         return null;
      }

      return $this->module_symbol($modul);
   }

   public function serve_file(string $filename): void {
      $filename = $this->sanitize_filename($filename);
      if ($filename === '' || !$this->is_image_file($filename)) {
         http_response_code(404);
         exit;
      }

      $dir = realpath($this->abs_dir());
      $file = realpath($this->abs_path($filename));
      if (!$dir || !$file || strpos($file, $dir) !== 0 || !$this->is_image_path($file)) {
         http_response_code(404);
         exit;
      }

      $mime = function_exists('mime_content_type') ? (string) @mime_content_type($file) : '';
      if ($mime === '' || strpos(strtolower($mime), 'image/') !== 0) {
         $mime = $this->mime_for_extension(pathinfo($filename, PATHINFO_EXTENSION));
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

   public function ensure_dir(): void {
      $dir = $this->abs_dir();
      if (!is_dir($dir)) {
         mkdir($dir, 0777, true);
      }
   }

   public function get_list(string $modul): array {
      return $this->scan_list($modul);
   }

   public function scan_list(string $modul): array {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '') {
         return array();
      }
      if (isset($this->list_cache[$modul])) {
         return $this->list_cache[$modul];
      }

      $this->ensure_dir();
      $dir = $this->abs_dir();
      if (!is_dir($dir) || !is_readable($dir)) {
         $this->list_cache[$modul] = array();
         return array();
      }

      $out = array();
      foreach (glob($dir . '*') ?: array() as $path) {
         if (!is_file($path) || !is_readable($path)) {
            continue;
         }
         $name = $this->sanitize_filename(basename($path));
         if ($name === '' || !$this->is_image_file($name, $path)) {
            continue;
         }
         if ($this->file_belongs_to_modul($modul, $name)) {
            $out[] = $name;
         }
      }

      $out = array_values(array_unique($out));
      sort($out, SORT_NATURAL | SORT_FLAG_CASE);
      $this->list_cache[$modul] = $out;
      return $this->list_cache[$modul];
   }

   public function save_list(string $modul, array $files): bool {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '') {
         return false;
      }

      $allowed = array_flip($this->scan_list($modul));
      foreach ($files as $file) {
         $name = $this->sanitize_filename((string)$file);
         if ($name !== '' && !isset($allowed[$name])) {
            return false;
         }
      }

      return true;
   }

   public function add_to_list(string $modul, string $filename): bool {
      $filename = $this->sanitize_filename($filename);
      return $filename !== ''
         && $this->file_exists($filename)
         && $this->file_belongs_to_modul($modul, $filename);
   }

   public function remove_from_list(string $modul, string $filename, bool $delete_physical = true): bool {
      $filename = $this->sanitize_filename($filename);
      if ($filename === '' || !$this->file_belongs_to_modul($modul, $filename)) {
         return false;
      }

      if ($delete_physical) {
         $path = $this->abs_path($filename);
         if (is_file($path)) {
            @unlink($path);
         }
      }

      return true;
   }

   public function import_for_modul(string $modul, int $media_id = 0, string $source_rel = '', string $run1 = '', string $run2 = ''): ?string {
      $modul = $this->sanitize_modul($modul);
      $run1 = $this->sanitize_run_part($run1);
      $run2 = $this->sanitize_run_part($run2);
      if ($modul === '' || $run1 === '') {
         return null;
      }

      $rel = '';
      if ($media_id > 0) {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return null;
         }
         $row = $db->select1('dbxMedia', $media_id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) {
            return null;
         }
         $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      } elseif ($source_rel !== '') {
         $rel = ltrim(str_replace('\\', '/', trim($source_rel)), '/');
      }

      if ($rel === '' || strpos($rel, '..') !== false) {
         return null;
      }

      $source = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      $source = dbx()->os_path($source);
      if (!is_file($source) || !is_readable($source)) {
         return null;
      }

      if (!$this->is_image_path($source)) {
         return null;
      }

      $ext = $this->extension_from_image_path($source);
      $target_name = $this->filename_for_runs($modul, $run1, $run2, $ext);
      if ($target_name === '') {
         return null;
      }

      $this->ensure_dir();
      $dest = $this->abs_path($target_name);
      if ($dest !== '' && $source === $dest && is_file($dest)) {
         return $target_name;
      }

      return $this->copy_from_media_rel($modul, $rel, $target_name);
   }

   public function save_from_upload(string $modul, string $run1, string $run2, array $file): ?string {
      $modul = $this->sanitize_modul($modul);
      $run1 = $this->sanitize_run_part($run1);
      $run2 = $this->sanitize_run_part($run2);
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

      if (!$this->is_image_path($tmp)) {
         return null;
      }

      $ext = $this->extension_from_image_path($tmp, $this->extension_from_image_path((string) ($file['name'] ?? '')));
      $target_name = $this->filename_for_runs($modul, $run1, $run2, $ext);
      if ($target_name === '') {
         return null;
      }

      $this->ensure_dir();
      $dest = $this->abs_path($target_name);
      if ($dest === '') {
         return null;
      }

      if (is_file($dest)) {
         @unlink($dest);
      }

      if (!@move_uploaded_file($tmp, $dest)) {
         return null;
      }

      return $target_name;
   }

   public function stem_for_runs(string $modul, string $run1, string $run2 = ''): string {
      $modul = $this->sanitize_modul($modul);
      $run1 = $this->sanitize_run_part($run1);
      $run2 = $this->sanitize_run_part($run2);
      if ($modul === '' || $run1 === '') {
         return $modul;
      }

      $stem = $modul . self::FILENAME_PARAM_SEP . $run1;
      if ($run2 !== '') {
         $stem .= self::FILENAME_PARAM_SEP . $run2;
      }

      return $stem;
   }

   public function filename_for_runs(string $modul, string $run1, string $run2 = '', string $ext = 'jpg'): string {
      $modul = $this->sanitize_modul($modul);
      $run1 = $this->sanitize_run_part($run1);
      $run2 = $this->sanitize_run_part($run2);
      $ext = $this->normalize_image_extension($ext);

      if ($modul === '' || $run1 === '') {
         return '';
      }

      return $this->stem_for_runs($modul, $run1, $run2) . '.' . $ext;
   }

   public function get_url(string $filename): string {
      $filename = $this->sanitize_filename($filename);
      if ($filename === '') {
         return '';
      }
      return $this->url_base() . rawurlencode($filename);
   }

   public function file_exists(string $filename): bool {
      $path = $this->abs_path($filename);
      return $path !== '' && is_file($path) && is_readable($path);
   }

   public function abs_path(string $filename): string {
      $filename = $this->sanitize_filename($filename);
      if ($filename === '') {
         return '';
      }
      return $this->abs_dir() . $filename;
   }

   public function copy_from_media_rel(string $modul, string $source_rel, string $target_name = ''): ?string {
      $modul = $this->sanitize_modul($modul);
      $source_rel = ltrim(str_replace('\\', '/', trim($source_rel)), '/');
      if ($modul === '' || $source_rel === '' || strpos($source_rel, '..') !== false) {
         return null;
      }

      $source = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $source_rel;
      $source = dbx()->os_path($source);
      if (!is_file($source) || !is_readable($source)) {
         return null;
      }

      if (!$this->is_image_path($source)) {
         return null;
      }

      if ($target_name === '') {
         $target_name = $this->suggest_filename($modul, basename($source));
      } else {
         $target_name = $this->sanitize_filename($target_name);
         if ($target_name !== '' && pathinfo($target_name, PATHINFO_EXTENSION) === '') {
            $target_name .= '.' . $this->extension_from_image_path($source);
         }
      }

      if ($target_name === '') {
         return null;
      }

      $this->ensure_dir();
      $dest = $this->abs_path($target_name);
      if ($dest === '') {
         return null;
      }

      if (is_file($dest) && $source !== $dest) {
         @unlink($dest);
      }

      if (!@copy($source, $dest)) {
         return null;
      }

      return $target_name;
   }

   public function copy_from_media_id(string $modul, int $media_id): ?string {
      return $this->import_for_modul($modul, $media_id, '');
   }

   public function suggest_filename(string $modul, string $source_name): string {
      $modul = $this->sanitize_modul($modul);
      $base = pathinfo(basename($source_name), PATHINFO_FILENAME);
      $base = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$base);
      $base = trim((string)$base, '-_');
      $ext = $this->normalize_image_extension(pathinfo($source_name, PATHINFO_EXTENSION));

      if ($base === '') {
         $base = 'image';
      }

      $prefix = $modul . self::FILENAME_PARAM_SEP;
      if (strpos($base, $prefix) !== 0 && $base !== $modul) {
         $base = $prefix . $base;
      }

      $name = $base . '.' . $ext;
      if (!$this->file_exists($name)) {
         return $name;
      }

      $i = 2;
      while ($i < 1000) {
         $candidate = $base . self::FILENAME_PARAM_SEP . $i . '.' . $ext;
         if (!$this->file_exists($candidate)) {
            return $candidate;
         }
         $i++;
      }

      return $base . self::FILENAME_PARAM_SEP . substr(md5((string)microtime(true)), 0, 6) . '.' . $ext;
   }

   public function resolve_from_filename(string $filename): array {
      $filename = $this->sanitize_filename($filename);
      if ($filename === '' || !$this->is_image_file($filename) || !$this->file_exists($filename)) {
         return array();
      }

      $modul = $this->resolve_modul_from_filename($filename);
      if ($modul === '') {
         return array();
      }

      $item = $this->catalog_item($modul, $filename);
      return is_array($item) ? $item : array();
   }

   public function resolve_modul_from_filename(string $filename): string {
      $filename = $this->sanitize_filename($filename);
      if ($filename === '') {
         return '';
      }

      $base = pathinfo($filename, PATHINFO_FILENAME);
      if ($base === '') {
         return '';
      }

      $sep = self::FILENAME_PARAM_SEP;
      if (strpos($base, $sep) !== false) {
         $modul = $this->sanitize_modul((string) strstr($base, $sep, true));
         if ($modul !== '' && $this->is_known_modul($modul)) {
            return $modul;
         }
         return '';
      }

      if ($this->is_known_modul($base)) {
         return $this->sanitize_modul($base);
      }

      return '';
   }

   public function catalog_for_modul(string $modul): array {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '') {
         return array();
      }

      $scan = $this->scan_runs($modul);
      $items = array();

      foreach ($this->get_list($modul) as $file) {
         $items[] = $this->catalog_item($modul, $file, $scan);
      }

      return $items;
   }

   public function catalog_item(string $modul, string $filename, array $scan = null): array {
      $modul = $this->sanitize_modul($modul);
      $filename = $this->sanitize_filename($filename);
      if ($modul === '' || $filename === '') {
         return array();
      }

      if (!is_array($scan)) {
         $scan = $this->scan_runs($modul);
      }

      $parsed = $this->parse_image_name($modul, $filename, $scan);
      $label = (string)($parsed['label'] ?? $filename);
      $params = (string)($parsed['default_params'] ?? '');
      $marker = '[modul=' . $modul . ']' . $params . '[/modul]';

      return array(
         'id'             => pathinfo($filename, PATHINFO_FILENAME),
         'file'           => $filename,
         'label'          => $label,
         'description'    => $marker,
         'url'            => $this->get_url($filename),
         'default_modul'  => $modul,
         'default_params' => $params,
         'default_alt'    => $modul . ': ' . $label,
         'run1'           => (string)($parsed['run1'] ?? ''),
         'run2'           => (string)($parsed['run2'] ?? ''),
      );
   }

   public function image_count(string $modul): int {
      return count($this->get_list($modul));
   }

   public function primary_graphic(string $modul, string $run1 = '', string $run2 = ''): array {
      $modul = $this->sanitize_modul($modul);
      $list = $this->get_list($modul);
      if (!$list) {
         return array('url' => '', 'alt' => $modul, 'badge' => '');
      }

      $scan = $this->scan_runs($modul);
      $candidates = array();
      if ($run1 !== '') {
         if ($run2 !== '') {
            $candidates[] = $this->stem_for_runs($modul, $run1, $run2);
         }
         $candidates[] = $this->stem_for_runs($modul, $run1, '');
      }
      $candidates[] = $modul;

      foreach ($candidates as $stem) {
         foreach ($list as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            if ($base === $stem) {
               return array(
                  'url'   => $this->get_url($file),
                  'alt'   => $modul,
                  'badge' => $file,
               );
            }
         }
      }

      $first = $list[0];
      return array(
         'url'   => $this->get_url($first),
         'alt'   => $modul,
         'badge' => $first,
      );
   }

   public function image_items(string $modul): array {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '') {
         return array();
      }
      if (isset($this->image_items_cache[$modul])) {
         return $this->image_items_cache[$modul];
      }

      $scan = $this->scan_runs($modul);
      $items = array();
      foreach ($this->get_list($modul) as $file) {
         $item = $this->catalog_item($modul, $file, $scan);
         if ($item) {
            $items[] = $item;
         }
      }
      $this->image_items_cache[$modul] = $items;
      return $this->image_items_cache[$modul];
   }

   public function media_browser_rows(string $modul_filter = ''): array {
      $modul_filter = $this->sanitize_modul($modul_filter);
      $this->ensure_dir();
      $dir = $this->abs_dir();
      if (!is_dir($dir) || !is_readable($dir)) {
         return array();
      }

      $rows = array();
      foreach (glob($dir . '*') ?: array() as $path) {
         if (!is_file($path) || !is_readable($path)) {
            continue;
         }
         $name = $this->sanitize_filename(basename($path));
         if ($name === '' || !$this->is_image_file($name, $path)) {
            continue;
         }
         if ($modul_filter !== '' && !$this->file_belongs_to_modul($modul_filter, $name)) {
            continue;
         }

         $rel = $this->rel_path($name);
         $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
         if ($mime === '' || strpos(strtolower($mime), 'image/') !== 0) {
            $mime = $this->mime_for_extension(pathinfo($name, PATHINFO_EXTENSION));
         }

         $title = pathinfo($name, PATHINFO_FILENAME);
         $rows[] = array(
            'id'           => abs(crc32($rel)),
            'file_name'    => $name,
            'file_path'    => $rel,
            'title'        => $title,
            'alt'          => $title,
            'url'          => $this->get_url($name),
            'thumb_url'    => $this->get_url($name),
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


   private function file_belongs_to_modul(string $modul, string $filename): bool {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '') {
         return false;
      }

      return $this->resolve_modul_from_filename($filename) === $modul;
   }

   private function remove_module_symbol_variants(string $modul, string $keep_file = ''): void {
      $modul = $this->sanitize_modul($modul);
      $keep_file = $this->sanitize_filename($keep_file);
      $dir = $this->module_symbol_dir($modul, false);
      if ($modul === '' || $dir === '' || !is_dir($dir)) {
         return;
      }

      foreach (glob($dir . $modul . '.*') ?: array() as $path) {
         $file = basename((string)$path);
         if ($file === $keep_file) {
            continue;
         }
         if (is_file($path)) {
            @unlink($path);
         }
      }
   }

   private function is_image_file(string $filename, string $abs_path = ''): bool {
      $filename = $this->sanitize_filename($filename);
      if ($filename === '') {
         return false;
      }

      if ($abs_path === '') {
         $abs_path = $this->abs_path($filename);
      }

      $ext = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($filename, PATHINFO_EXTENSION)));
      if ($ext === '' || strlen($ext) > 8 || in_array($ext, $this->blocked_image_extensions(), true)) {
         return false;
      }

      return in_array($ext, $this->allowed_image_extensions(), true);
   }

   private function scan_runs(string $modul): array {
      static $cache = array();
      if (isset($cache[$modul])) {
         return $cache[$modul];
      }

      $cache[$modul] = $this->scan_runs_inline($modul);
      return $cache[$modul];
   }

   private function scan_runs_inline(string $modul): array {
      $run1 = array();
      $uses_run2 = false;
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
            $uses_run2 = true;
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
         'uses_run2'    => $uses_run2,
         'run1_sorted'  => $run1List,
      );
   }

   private function parse_image_name(string $modul, string $filename, array $scan): array {
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
         return $this->build_parsed_runs($run1, $run2, $base);
      }

      return array(
         'run1'           => '',
         'run2'           => '',
         'default_params' => '',
         'label'          => $base,
      );
   }

   private function build_parsed_runs(string $run1, string $run2, string $fallback_label): array {
      $params = $this->build_params($run1, $run2);
      $label = $this->run_label($run1);
      if ($run2 !== '') {
         $label .= ' / ' . $this->run_label($run2);
      }
      if ($label === '') {
         $label = $fallback_label;
      }

      return array(
         'run1'           => $run1,
         'run2'           => $run2,
         'default_params' => $params,
         'label'          => $label,
      );
   }

   private function known_modul_names(): array {
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

   private function is_known_modul(string $modul): bool {
      $modul = $this->sanitize_modul($modul);
      if ($modul === '') {
         return false;
      }

      foreach ($this->known_modul_names() as $name) {
         if ($name === $modul) {
            return true;
         }
      }

      return is_file(dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/' . $modul . '.class.php'));
   }

   private function build_params(string $run1, string $run2 = ''): string {
      $params = array();
      if ($run1 !== '') {
         $params[] = 'dbx_run1=' . rawurlencode($run1);
      }
      if ($run2 !== '') {
         $params[] = 'dbx_run2=' . rawurlencode($run2);
      }
      return implode('&', $params);
   }

   private function run_label(string $action): string {
      $action = trim($action);
      if ($action === '') {
         return '';
      }
      return ucfirst(str_replace('_', ' ', $action));
   }

   private function sanitize_modul(string $modul): string {
      return preg_replace('/[^A-Za-z0-9_]+/', '', trim($modul));
   }

   private function sanitize_run_part(string $part): string {
      return preg_replace('/[^A-Za-z0-9_-]+/', '', trim($part));
   }

   private function sanitize_filename(string $filename): string {
      $filename = basename(str_replace('\\', '/', trim($filename)));
      $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $filename);
      return trim($filename, '-.');
   }
}
