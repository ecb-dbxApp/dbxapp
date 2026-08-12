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
 * Bild-/Video-Konvertierung, Groessenbegrenzung und Vorschaubilder.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsMediaImageServiceTrait {


   private function first_upload_file() {
      if (empty($_FILES) || !is_array($_FILES)) return array();

      foreach ($_FILES as $file) {
         if (!is_array($file)) continue;

         if (isset($file['tmp_name']) && !is_array($file['tmp_name'])) {
            return $file;
         }

         if (!isset($file['tmp_name']) || !is_array($file['tmp_name'])) continue;

         $idx = array_key_first($file['tmp_name']);
         if ($idx === null) continue;

         return array(
            'name' => is_array($file['name'] ?? null) ? ($file['name'][$idx] ?? '') : ($file['name'] ?? ''),
            'type' => is_array($file['type'] ?? null) ? ($file['type'][$idx] ?? '') : ($file['type'] ?? ''),
            'tmp_name' => $file['tmp_name'][$idx] ?? '',
            'error' => is_array($file['error'] ?? null) ? ($file['error'][$idx] ?? UPLOAD_ERR_NO_FILE) : ($file['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => is_array($file['size'] ?? null) ? ($file['size'][$idx] ?? 0) : ($file['size'] ?? 0),
         );
      }

      return array();
   }



   private function load_gd_image($file, $mime) {
      if (!extension_loaded('gd')) return false;
      $mime = strtolower((string)$mime);
      if ((strpos($mime, 'jpeg') !== false || preg_match('/\.jpe?g$/i', $file)) && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($file);
      if ((strpos($mime, 'png') !== false || preg_match('/\.png$/i', $file)) && function_exists('imagecreatefrompng')) return @imagecreatefrompng($file);
      if (strpos($mime, 'webp') !== false || preg_match('/\.webp$/i', $file)) return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
      if ((strpos($mime, 'gif') !== false || preg_match('/\.gif$/i', $file)) && function_exists('imagecreatefromgif')) return @imagecreatefromgif($file);
      return false;
   }



   private function save_gd_image($img, $file, $mime) {
      if (!extension_loaded('gd')) return false;
      $mime = strtolower((string)$mime);
      if (strpos($mime, 'png') !== false || preg_match('/\.png$/i', $file)) {
         if (!function_exists('imagepng')) return false;
         imagealphablending($img, false);
         imagesavealpha($img, true);
         return @imagepng($img, $file, 6);
      }
      if ((strpos($mime, 'webp') !== false || preg_match('/\.webp$/i', $file)) && function_exists('imagewebp')) {
         return @imagewebp($img, $file, 86);
      }
      if (!function_exists('imagejpeg')) return false;
      return @imagejpeg($img, $file, 88);
   }



   private function convert_media_image_to_webp($file, $name, $mime = '') {
      if (!extension_loaded('gd') || !function_exists('imagewebp')) return false;
      if ($file === '' || !is_file($file) || !is_readable($file)) return false;
      if (preg_match('/\.(webp|gif|svg)$/i', (string)$name)) return false;

      $mime_l = strtolower((string)$mime);
      $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
      $is_supported = in_array($ext, array('jpg','jpeg','png'), true)
         || strpos($mime_l, 'jpeg') !== false
         || strpos($mime_l, 'png') !== false;
      if (!$is_supported) return false;

      $image = $this->load_gd_image($file, $mime);
      if (!$image) return false;

      imagepalettetotruecolor($image);
      imagealphablending($image, true);
      imagesavealpha($image, true);

      $webp_name = preg_replace('/\.[^.]+$/', '', basename((string)$name)) . '.webp';
      $webp_file = rtrim(dirname($file), '/\\') . DIRECTORY_SEPARATOR . $webp_name;
      if (is_file($webp_file)) {
         $webp_name = $this->unique_media_name($webp_name);
         $webp_file = rtrim(dirname($file), '/\\') . DIRECTORY_SEPARATOR . $webp_name;
      }

      $ok = @imagewebp($image, $webp_file, 86);
      imagedestroy($image);
      if (!$ok || !is_file($webp_file)) return false;

      @unlink($file);
      return array(
         'file' => $webp_file,
         'name' => $webp_name,
         'mime' => 'image/webp',
      );
   }



   private function resize_image_to_max($file, $mime, $max_long_side = 2560) {
      $max_long_side = max(800, min(6000, (int)$max_long_side));
      if ($file === '' || preg_match('/\.(svg|gif)$/i', (string)$file)) return false;

      $size = @getimagesize($file);
      if (!is_array($size)) return false;

      $src_w = (int)($size[0] ?? 0);
      $src_h = (int)($size[1] ?? 0);
      if ($src_w <= 0 || $src_h <= 0 || max($src_w, $src_h) <= $max_long_side) return false;

      $op = array('action' => 'resize');
      if ($src_w >= $src_h) {
         $op['width'] = $max_long_side;
         $op['height'] = 0;
      } else {
         $op['width'] = 0;
         $op['height'] = $max_long_side;
      }

      return $this->edit_image_file($file, $mime, $op);
   }



   private function create_media_thumbnail($src, $slot, $name, $mime = '') {
      $type = $this->media_type(array('mime' => $mime, 'file_name' => $name));
      if ($type === 'video') return $this->create_video_thumbnail($src, $slot, $name);
      if ($type !== 'image' || preg_match('/\.svg$/i', (string)$name) || !function_exists('imagecreatetruecolor')) return array();

      $image = $this->load_gd_image($src, $mime);
      if (!$image) return array();

      $src_w = imagesx($image);
      $src_h = imagesy($image);
      if ($src_w <= 0 || $src_h <= 0) {
         imagedestroy($image);
         return array();
      }

      $max_w = 640;
      $max_h = 480;
      $scale = min($max_w / $src_w, $max_h / $src_h, 1);
      $dst_w = max(1, (int)round($src_w * $scale));
      $dst_h = max(1, (int)round($src_h * $scale));
      $thumb = imagecreatetruecolor($dst_w, $dst_h);
      imagealphablending($thumb, false);
      imagesavealpha($thumb, true);
      imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 255, 255, 255, 127));
      imagecopyresampled($thumb, $image, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);

      $dir = $this->cms_media_thumb_dir($slot);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      $thumb_ext = function_exists('imagewebp') ? 'webp' : 'jpg';
      $thumb_name = preg_replace('/\.[^.]+$/', '', basename((string)$name)) . '.' . $thumb_ext;
      $thumb_name = $this->unique_media_name($thumb_name, 'thumb');
      $dst = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $thumb_name;
      $ok = $thumb_ext === 'webp' ? @imagewebp($thumb, $dst, 82) : @imagejpeg($thumb, $dst, 82);
      imagedestroy($thumb);
      imagedestroy($image);

      if (!$ok) return array();
      return array(
         'thumb_file_path' => $this->media_thumb_rel_dir($slot) . $thumb_name,
         'thumb_width' => $dst_w,
         'thumb_height' => $dst_h,
      );
   }



   private function find_executable_path(string $command): string {
      static $cache = array();

      $command = trim($command);
      if ($command === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $command)) {
         return '';
      }

      if (array_key_exists($command, $cache)) {
         return $cache[$command];
      }

      $lookup = stripos(PHP_OS_FAMILY, 'Windows') === 0
         ? 'where ' . $command . ' 2>NUL'
         : 'command -v ' . $command . ' 2>/dev/null';
      $result = trim((string)@shell_exec($lookup));
      $path = $result !== '' ? strtok($result, "\r\n") : '';
      $cache[$command] = is_string($path) ? trim($path) : '';

      return $cache[$command];
   }



   private function create_video_thumbnail($src, $slot, $name) {
      $ffmpeg = $this->find_executable_path('ffmpeg');

      $dir = $this->cms_media_thumb_dir($slot);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      $thumb_name = $this->unique_media_name(preg_replace('/\.[^.]+$/', '', basename((string)$name)) . '.jpg', 'video-thumb');
      $dst = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $thumb_name;

      if ($ffmpeg !== '') {
         $cmd = '"' . $ffmpeg . '" -y -ss 00:00:01 -i "' . $src . '" -frames:v 1 -vf "scale=640:-1" "' . $dst . '" 2>NUL';
         @shell_exec($cmd);
      }

      if (!is_file($dst) || !filesize($dst)) {
         $this->create_video_placeholder_thumbnail($dst, $name);
      }

      if (!is_file($dst) || !filesize($dst)) return array();
      $size = @getimagesize($dst);
      return array(
         'thumb_file_path' => $this->media_thumb_rel_dir($slot) . $thumb_name,
         'thumb_width' => is_array($size) ? (int)$size[0] : 0,
         'thumb_height' => is_array($size) ? (int)$size[1] : 0,
      );
   }



   private function create_video_placeholder_thumbnail($dst, $name) {
      if (!function_exists('imagecreatetruecolor')) return false;
      $w = 640;
      $h = 360;
      $img = imagecreatetruecolor($w, $h);
      $bg = imagecolorallocate($img, 30, 43, 60);
      $panel = imagecolorallocate($img, 52, 72, 96);
      $white = imagecolorallocate($img, 255, 255, 255);
      $muted = imagecolorallocate($img, 188, 202, 220);
      imagefill($img, 0, 0, $bg);
      imagefilledrectangle($img, 20, 20, $w - 20, $h - 20, $panel);
      $cx = (int)($w / 2);
      $cy = (int)($h / 2) - 12;
      imagefilledpolygon($img, array($cx - 38, $cy - 48, $cx - 38, $cy + 48, $cx + 52, $cy), 3, $white);
      $label = substr(basename((string)$name), 0, 54);
      imagestring($img, 3, 28, $h - 42, $label, $muted);
      $ok = @imagejpeg($img, $dst, 82);
      imagedestroy($img);
      return $ok;
   }



   private function source_thumb_file($row) {
      $rel = ltrim(str_replace('\\', '/', (string)($row['thumb_file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return '';
      $file = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      $file = dbx()->os_path($file);
      $base = realpath(rtrim(dbx()->get_file_dir(), '/\\'));
      $real = realpath($file);
      if (!$base || !$real || strpos($real, $base) !== 0 || !is_file($real) || !is_readable($real)) return '';
      return $real;
   }
}
