<?php
namespace dbx\dbxContent_admin;

use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;
use dbx\dbxContent\dbxContentTranslate;
use dbx\dbxContent\dbxContent_permalink;

/**
 * Kanonische Medienpfade, Dateitypen, Metadaten und physische Dateiinventare.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsMediaFileServiceTrait {


   private function media_url($row, $thumb = false) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) return '';
      $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $id;
      if ($thumb && !empty($row['thumb_file_path'])) $url .= '&dbx_thumb=1';
      $v = (int)($row['size'] ?? 0) . '-' . (int)($row['width'] ?? 0) . 'x' . (int)($row['height'] ?? 0);
      if ($thumb) $v .= '-' . (int)($row['thumb_width'] ?? 0) . 'x' . (int)($row['thumb_height'] ?? 0);
      $url .= '&_v=' . rawurlencode($v);
      return $url;
   }



   private function external_video_thumb_url($row) {
      $provider = strtolower(trim((string)($row['provider'] ?? '')));
      $provider_id = trim((string)($row['provider_id'] ?? ''));
      if ($provider === 'youtube' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id)) {
         return 'https://img.youtube.com/vi/' . $provider_id . '/hqdefault.jpg';
      }
      return '';
   }



   private function external_video_json_meta($file) {
      $raw = @file_get_contents((string)$file);
      if ($raw === false || $raw === '') return array();
      $data = json_decode($raw, true);
      if (!is_array($data)) return array();
      $provider = strtolower(trim((string)($data['provider'] ?? 'youtube')));
      $provider_id = trim((string)($data['provider_id'] ?? pathinfo((string)$file, PATHINFO_FILENAME)));
      $title = trim((string)($data['title'] ?? ('YouTube ' . $provider_id)));
      $external_url = trim((string)($data['external_url'] ?? ''));
      $embed_url = trim((string)($data['embed_url'] ?? ''));
      if ($embed_url === '' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id)) {
         $embed_url = 'https://www.youtube.com/embed/' . $provider_id;
      }
      return array(
         'provider' => $provider,
         'provider_id' => $provider_id,
         'title' => $title,
         'alt' => $title,
         'external_url' => $external_url,
         'embed_url' => $embed_url,
      );
   }



   private function media_type($row) {
      $stored = strtolower(trim((string)($row['media_type'] ?? '')));
      if (in_array($stored, array('image','video','external_video','file'), true)) return $stored;
      $storage = strtolower(trim((string)($row['storage_type'] ?? '')));
      if ($storage === 'external' || trim((string)($row['provider'] ?? '')) !== '' || trim((string)($row['embed_url'] ?? '')) !== '') return 'external_video';
      $mime = strtolower((string)($row['mime'] ?? ''));
      $name = strtolower((string)($row['file_name'] ?? $row['file_path'] ?? ''));
      if (strpos($mime, 'video/') === 0 || preg_match('/\.(mp4|webm|ogv|ogg|mov|m4v)$/i', $name)) return 'video';
      if (strpos($mime, 'image/') === 0 || preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $name)) return 'image';
      return 'file';
   }



   private function media_file_exists($row) {
      $row = is_array($row) ? $row : array();
      if (strtolower((string)($row['storage_type'] ?? '')) === 'external') return trim((string)($row['embed_url'] ?? $row['external_url'] ?? '')) !== '';
      $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return false;

      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/';
      $root = dbx()->os_path($root);
      $base = realpath($root);
      if (!$base) return false;

      $file = $root . $rel;
      $file = dbx()->os_path($file);
      $real = realpath($file);
      return $real && strpos($real, $base) === 0 && is_file($real) && is_readable($real);
   }



   private function media_thumb_exists($row) {
      $row = is_array($row) ? $row : array();
      if (strtolower((string)($row['storage_type'] ?? '')) === 'external' && trim((string)($row['thumb_file_path'] ?? '')) === '') return false;
      $rel = ltrim(str_replace('\\', '/', (string)($row['thumb_file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return false;

      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/';
      $file = $root . $rel;
      $root = dbx()->os_path($root);
      $file = dbx()->os_path($file);

      $base = realpath($root);
      $real = realpath($file);
      return $base && $real && strpos($real, $base) === 0 && is_file($real) && is_readable($real);
   }



   private function media_thumbnail_supported($row): bool {
      $row = is_array($row) ? $row : array();
      $type = $this->media_type($row);
      if ($type === 'video') return true;
      if ($type !== 'image' || !function_exists('imagecreatetruecolor')) return false;
      $name = (string)($row['file_name'] ?? $row['file_path'] ?? '');
      $mime = strtolower(trim((string)($row['mime'] ?? '')));
      // SVG wird im Browser verlustfrei direkt dargestellt. Ein GD-Thumbnail
      // ist weder moeglich noch erforderlich und deshalb kein Wartungsfehler.
      return $mime !== 'image/svg+xml' && !preg_match('/\.svg$/i', $name);
   }



   /**
    * Eine vorhandene Datei allein reicht nicht: Nach Austausch, Umbenennung
    * oder Bildbearbeitung kann der DB-Eintrag noch auf ein altes Motiv zeigen.
    * Videos duerfen ein bewusst gesetztes Poster verwenden; lokale Rasterbilder
    * muessen dagegen zu Name, Zeitstand und erwarteter Groesse der Quelle passen.
    */
   private function media_thumbnail_is_current($row): bool {
      $row = is_array($row) ? $row : array();
      if (!$this->media_thumb_exists($row)) return false;
      if ($this->media_type($row) !== 'image') return true;
      if (!$this->media_thumbnail_supported($row)) return true;

      $source = $this->source_media_file($row);
      $thumb = $this->source_thumb_file($row);
      if ($source === '' || $thumb === '') return false;

      $source_time = @filemtime($source);
      $thumb_time = @filemtime($thumb);
      if ($source_time !== false && $thumb_time !== false && $source_time > $thumb_time) return false;

      $source_name = (string)($row['file_name'] ?? basename($source));
      $source_stem = strtolower((string)pathinfo($source_name, PATHINFO_FILENAME));
      $thumb_stem = strtolower((string)pathinfo(basename($thumb), PATHINFO_FILENAME));
      if ($source_stem !== '' && strpos($thumb_stem, $source_stem) === false) return false;

      $source_size = @getimagesize($source);
      $thumb_size = @getimagesize($thumb);
      if (!is_array($source_size) || !is_array($thumb_size)) return false;
      $source_w = (int)($source_size[0] ?? 0);
      $source_h = (int)($source_size[1] ?? 0);
      if ($source_w <= 0 || $source_h <= 0) return false;
      $scale = min(640 / $source_w, 480 / $source_h, 1);
      $expected_w = max(1, (int)round($source_w * $scale));
      $expected_h = max(1, (int)round($source_h * $scale));
      return abs((int)($thumb_size[0] ?? 0) - $expected_w) <= 1
         && abs((int)($thumb_size[1] ?? 0) - $expected_h) <= 1;
   }



   private function valid_media_slot($slot, $default = 'gallery') {
      $slot = strtolower(trim((string)$slot));
      $allowed = array('hero','gallery','inline','header','teaser','footer','shop');
      return in_array($slot, $allowed, true) ? $slot : $default;
   }






   private function video_media_base_folder() {
      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/media/';
      $root = dbx()->os_path($root);
      if (is_dir($root . 'videos')) return 'videos';
      if (is_dir($root . 'video')) return 'video';
      return 'videos';
   }



   private function media_standard_bases() {
      return array('img' => 'image', $this->video_media_base_folder() => 'video', 'youtube' => 'external_video', 'file' => 'file');
   }



   private function canonical_media_folder($folder) {
      $folder = strtolower(trim(str_replace('\\', '/', (string)$folder), '/'));
      if (preg_match('~^youtube/[a-z0-9_-]{11}$~', $folder)) return 'youtube';
      return $folder;
   }



   private function custom_media_root_exists($folder) {
      $folder = $this->canonical_media_folder($folder);
      $top = strtok($folder, '/');
      if ($top === false || $top === '') return false;
      if (in_array($top, array('_thumbs', 'img', 'video', 'videos', 'youtube', 'external', 'file', 'module'), true)) return false;
      if (in_array($top, $this->media_slots(), true)) return false;
      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/media/' . $top;
      return is_dir(dbx()->os_path($root));
   }



   private function clean_media_folder($folder, $media_type = 'image') {
      $media_type = $this->media_type(array('media_type' => $media_type));
      $folder = $this->canonical_media_folder($folder);
      $folder = strtolower(str_replace('\\', '/', trim((string)$folder)));
      $folder = preg_replace('~/+~', '/', $folder);
      $folder = trim($folder, '/');
      $parts = array();
      foreach (explode('/', $folder) as $part) {
         $part = preg_replace('/[^A-Za-z0-9_-]+/', '-', $part);
         $part = trim($part, '-_');
         if ($part !== '' && $part !== '.' && $part !== '..') $parts[] = $part;
      }
      $folder = implode('/', $parts);

      if ($folder === 'module') {
         return 'module';
      }

      if ($media_type === 'video') {
         $base = $this->video_media_base_folder();
         if ($folder === '' || $folder === 'video' || $folder === 'videos' || $folder === 'img' || $folder === 'img/video') return $base;
         if (substr($folder, 0, 10) === 'img/video/') return $base . '/' . substr($folder, 10);
         if (substr($folder, 0, 6) === 'video/') return $base . '/' . substr($folder, 6);
         if (substr($folder, 0, 7) === 'videos/') return $base . '/' . substr($folder, 7);
         return $base;
      }
      if ($media_type === 'external_video') {
         if ($folder === '' || $folder === 'youtube') return 'youtube';
         if (substr($folder, 0, 8) !== 'youtube/') return 'youtube';
         return $folder;
      }
      if ($media_type === 'file') {
         if ($folder === '' || $folder === 'file') return 'file/allgemein';
         if (substr($folder, 0, 5) !== 'file/') return 'file/allgemein';
         return $folder;
      }
      if ($folder === '' || $folder === 'img' || $folder === 'image') return 'img/allgemein';
      if (substr($folder, 0, 4) !== 'img/') {
         if ($this->custom_media_root_exists($folder)) return $folder;
         return 'img/allgemein';
      }
      return $folder;
   }



   private function media_type_from_folder($folder, $fallback = 'image') {
      $folder = $this->canonical_media_folder($folder);
      if (strpos($folder, 'img/video') === 0) return 'video';
      if (strpos($folder, 'videos/') === 0 || $folder === 'videos') return 'video';
      if (strpos($folder, 'video/') === 0 || $folder === 'video') return 'video';
      if (strpos($folder, 'youtube/') === 0 || $folder === 'youtube') return 'external_video';
      if (strpos($folder, 'file/') === 0 || $folder === 'file') return 'file';
      return $fallback;
   }



   private function media_folder_from_path($rel, $media_type = 'image') {
      $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
      if (preg_match('~^media/module/[^/]+$~i', $rel)) {
         return 'module';
      }
      if (preg_match('~^media/youtube/[^/]+\.json$~i', $rel)) {
         return 'youtube';
      }
      if (preg_match('~^media/(img|video|youtube|external|file)/([^/]+)/[^/]+$~i', $rel, $m)) {
         return $this->clean_media_folder($m[1] . '/' . $m[2], $media_type);
      }
      if (preg_match('~^media/(img|video|file)/([^/]+)/?$~i', $rel, $m)) {
         return $this->clean_media_folder($m[1] . '/' . $m[2], $media_type);
      }
      return $this->clean_media_folder('', $media_type);
   }



   private function media_folder_rel_dir($folder, $media_type = 'image') {
      return 'media/' . $this->clean_media_folder($folder, $media_type) . '/';
   }



   private function media_thumb_rel_dir($slot) {
      $slot_text = (string)$slot;
      $folder_type = 'image';
      if (preg_match('~^(youtube|external)(/|$)~', $slot_text)) $folder_type = 'external_video';
      elseif (preg_match('~^(videos|video|img/video)(/|$)~', $slot_text)) $folder_type = 'video';
      elseif (preg_match('~^file(/|$)~', $slot_text)) $folder_type = 'file';
      $folder = $this->clean_media_folder($slot, $folder_type);
      return 'media/_thumbs/' . $folder . '/';
   }



   private function cms_media_dir($slot = '') {
      $slot_text = (string)$slot;
      $folder_type = 'image';
      if (preg_match('~^(youtube|external)(/|$)~', $slot_text)) $folder_type = 'external_video';
      elseif (preg_match('~^(videos|video|img/video)(/|$)~', $slot_text)) $folder_type = 'video';
      elseif (preg_match('~^file(/|$)~', $slot_text)) $folder_type = 'file';
      $rel = $slot === '' ? 'media/' : $this->media_folder_rel_dir($slot, $folder_type);
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      return dbx()->os_path($dir);
   }



   private function cms_media_base_dir($base) {
      $base = strtolower(trim(str_replace('\\', '/', (string)$base), '/'));
      if (!in_array($base, array('img', 'video', 'videos', 'youtube', 'external', 'file', 'module'), true)) return '';
      $rel = 'media/' . $base . '/';
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      return dbx()->os_path($dir);
   }



   private function cms_media_thumb_dir($slot) {
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $this->media_thumb_rel_dir($slot);
      return dbx()->os_path($dir);
   }



   private function unique_media_name($name, $prefix = '') {
      $name = basename((string)$name);
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
      $prefix = $prefix !== '' ? rtrim($prefix, '-') . '-' : '';
      return date('YmdHis') . '-' . substr(md5($name . microtime(true)), 0, 8) . '-' . $prefix . $name;
   }



   private function media_slots() {
      return array('hero','gallery','inline','header','teaser','footer');
   }



   private function media_allowed_extensions() {
      return array('jpg','jpeg','png','gif','webp','svg','mp4','webm','ogv','ogg','mov','m4v');
   }



   private function file_from_media_rel($rel) {
      $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return '';
      $file = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      return dbx()->os_path($file);
   }



   private function relative_file_exists($rel) {
      $file = $this->file_from_media_rel($rel);
      return $file !== '' && is_file($file) && is_readable($file);
   }



   private function detect_media_mime($file, $ext) {
      $mime = function_exists('mime_content_type') ? (string)@mime_content_type($file) : '';
      if ($mime !== '') return $mime;
      $ext = strtolower((string)$ext);
      if (in_array($ext, array('mp4','webm','ogv','ogg','mov','m4v'), true)) {
         return $ext === 'webm' ? 'video/webm' : ($ext === 'mov' ? 'video/quicktime' : 'video/mp4');
      }
      return $ext === 'svg' ? 'image/svg+xml' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
   }



   private function media_file_meta($file, $name) {
      $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
      $mime = $this->detect_media_mime($file, $ext);
      $width = 0;
      $height = 0;
      $img = @getimagesize($file);
      if (is_array($img)) {
         $width = (int)($img[0] ?? 0);
         $height = (int)($img[1] ?? 0);
      }
      return array(
         'mime' => $mime,
         'size' => (int)@filesize($file),
         'width' => $width,
         'height' => $height,
         'media_type' => $this->media_type(array('mime' => $mime, 'file_name' => $name)),
      );
   }



   private function is_excluded_cms_media_folder($folder) {
      $folder = $this->canonical_media_folder(strtolower(trim(str_replace('\\', '/', (string)$folder), '/')));
      return $folder === 'module' || strpos($folder, 'module/') === 0;
   }



   private function cms_media_exclude_sql() {
      return " AND media_folder <> 'module' AND file_path NOT LIKE 'media/module/%'";
   }



   private function collect_media_files() {
      $files = array();
      $allowed = $this->media_allowed_extensions();

      foreach (array('img','video','youtube','file') as $base_folder) {
         $dir = $this->cms_media_base_dir($base_folder);
         if (!is_dir($dir) || !is_readable($dir)) continue;
         $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
         foreach ($it as $file) {
            if (!$file->isFile() || !$file->isReadable()) continue;
            $name = $file->getFilename();
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $is_external_base = $base_folder === 'youtube';
            if (!in_array($ext, $allowed, true) && !($is_external_base && $ext === 'json')) continue;
            $path = str_replace('\\', '/', $file->getPathname());
            $root = str_replace('\\', '/', rtrim(dbx()->get_file_dir(), '/\\')) . '/';
            if (strpos($path, $root) !== 0) continue;
            $rel = substr($path, strlen($root));
            $type = $base_folder === 'video' ? 'video' : ($is_external_base ? 'external_video' : ($base_folder === 'file' ? 'file' : 'image'));
            if ($ext === 'json' && $is_external_base) $type = 'external_video';
            $files[$rel] = array(
               'rel' => $rel,
               'slot' => 'gallery',
               'folder' => $this->media_folder_from_path($rel, $type),
               'file' => $file->getPathname(),
               'name' => $name,
            );
         }
      }
      return $files;
   }



   private function collect_media_thumb_files() {
      $files = array();
      $root = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/media/_thumbs/');
      if (!is_dir($root) || !is_readable($root)) return $files;
      $rootNormalized = str_replace('\\', '/', rtrim($root, '/\\')) . '/';
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::LEAVES_ONLY
      );
      foreach ($it as $file) {
         if (!$file->isFile() || !$file->isReadable()) continue;
         $path = str_replace('\\', '/', $file->getPathname());
         if (strpos($path, $rootNormalized) !== 0) continue;
         $rel = 'media/_thumbs/' . ltrim(substr($path, strlen($rootNormalized)), '/');
         $files[$rel] = array('rel' => $rel, 'file' => $file->getPathname());
      }
      return $files;
   }
}
