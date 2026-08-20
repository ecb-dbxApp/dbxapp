<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;
use dbx\dbxContent\dbxContentTranslate;
use dbx\dbxContent\dbxContent_permalink;

trait dbxKiCmsImageServiceTrait {

   private function safe_file_name($value): string {
      $name = basename(str_replace('\\', '/', trim((string)$value)));
      $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
      return trim((string)$name, '.-');
   }

   private function decode_base64(string $raw): string {
      $raw = trim($raw);
      if (preg_match('~^data:[^;]+;base64,(.*)$~s', $raw, $match)) $raw = $match[1];
      $decoded = base64_decode(preg_replace('/\s+/', '', $raw), true);
      if ($decoded === false) throw new \InvalidArgumentException('data_base64 ist ungültig.');
      return $decoded;
   }

   private function detect_mime(string $bytes, string $name): string {
      if (class_exists('\finfo')) {
         $finfo = new \finfo(FILEINFO_MIME_TYPE);
         $mime = (string)$finfo->buffer($bytes);
         if ($mime !== '') return $mime;
      }
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf', 'txt' => 'text/plain');
      return $map[$ext] ?? 'application/octet-stream';
   }

   private function resolve_local_file(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      if ($path === '') return '';
      if (!preg_match('~^(?:[A-Za-z]:/|/)~', $path)) {
         $path = rtrim(str_replace('\\', '/', dbx()->get_base_dir()), '/') . '/' . ltrim($path, '/');
      }
      return dbx()->os_path($path);
   }

   private function media_local_file(array $media): string {
      if (($media['storage_type'] ?? 'local') !== 'local') return '';
      $file_path = trim((string)($media['file_path'] ?? ''));
      if ($file_path === '') return '';
      $file_path = preg_replace('~^files/~', '', str_replace('\\', '/', $file_path));
      if (strpos($file_path, 'media/') !== 0) return '';
      return dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/' . $file_path);
   }

   private function hero_media_for_page(string $lng, int $id): array {
      $page = $this->db->select1(dbxContentLng::dd_content($lng), $id);
      if (!is_array($page)) throw new \RuntimeException('Seite nicht gefunden.');

      $usage = array();
      $media_id = (int)($page['hero_image_id'] ?? 0);
      if ($media_id <= 0) {
         $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::with_language('content_id = ' . $id . " AND slot = 'hero' AND active = 1", $lng), '*', 'sorter,id', 'DESC', '', 1, 0, 0);
         if (is_array($rows) && is_array($rows[0] ?? null)) {
            $usage = $rows[0];
            $media_id = (int)($usage['media_id'] ?? 0);
         }
      }
      if ($media_id <= 0) throw new \RuntimeException('Die Seite hat kein bestehendes Hero-Bild.');

      $media = $this->db->select1('dbxMedia', $media_id);
      if (!is_array($media) || (int)($media['active'] ?? 0) !== 1) throw new \RuntimeException('Hero-Medium nicht gefunden.');
      if (!$usage) {
         $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::with_language('content_id = ' . $id . ' AND media_id = ' . $media_id . " AND slot = 'hero' AND active = 1", $lng), '*', 'sorter,id', 'DESC', '', 1, 0, 0);
         if (is_array($rows) && is_array($rows[0] ?? null)) $usage = $rows[0];
      }
      return array('page' => $page, 'media' => $media, 'usage' => $usage);
   }

   private function source_image_plan(array $params): array {
      $source = $this->resolve_local_file((string)($params['source_file'] ?? ''));
      if ($source === '' || !is_file($source) || !is_readable($source)) {
         throw new \InvalidArgumentException('source_file ist nicht lesbar.');
      }
      $info = @getimagesize($source);
      if (!is_array($info) || empty($info[0]) || empty($info[1])) {
         throw new \InvalidArgumentException('source_file ist kein lesbares Bild.');
      }
      $mime = (string)($info['mime'] ?? '');
      if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp', 'image/gif'), true)) {
         throw new \InvalidArgumentException('Nicht unterstützter Quellbildtyp: ' . $mime);
      }
      return array(
         'file' => $source,
         'sha256' => hash_file('sha256', $source),
         'mime' => $mime,
         'width' => (int)$info[0],
         'height' => (int)$info[1],
         'crop' => $this->image_crop_rect($params, (int)$info[0], (int)$info[1]),
         'tint' => $this->normalize_hex_color((string)($params['tint'] ?? '')),
         'tint_strength' => max(0.0, min(1.0, (float)($params['tint_strength'] ?? 0))),
      );
   }

   private function image_fit($value): string {
      $fit = strtolower(trim((string)$value));
      return in_array($fit, array('cover', 'contain'), true) ? $fit : 'cover';
   }

   private function image_quality($value): int {
      return min(100, max(1, (int)$value));
   }

   private function image_crop_rect(array $params, int $source_width, int $source_height): array {
      $source_width = max(1, $source_width);
      $source_height = max(1, $source_height);
      $x = (int)($params['crop_x'] ?? 0);
      $y = (int)($params['crop_y'] ?? 0);
      $width = (int)($params['crop_width'] ?? $source_width);
      $height = (int)($params['crop_height'] ?? $source_height);

      $x = max(0, min($x, $source_width - 1));
      $y = max(0, min($y, $source_height - 1));
      $width = max(1, min($width, $source_width - $x));
      $height = max(1, min($height, $source_height - $y));

      return array('x' => $x, 'y' => $y, 'width' => $width, 'height' => $height);
   }

   private function mime_from_file_name(string $name): string {
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      $map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
      return $map[$ext] ?? 'image/webp';
   }

   private function normalize_hex_color(string $value): string {
      $value = trim($value);
      if ($value === '') return '';
      if ($value[0] !== '#') $value = '#' . $value;
      return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtoupper($value) : '';
   }

   private function gd_load_image(string $file, string $mime) {
      switch ($mime) {
         case 'image/jpeg':
            $image = @imagecreatefromjpeg($file);
            break;
         case 'image/png':
            $image = @imagecreatefrompng($file);
            break;
         case 'image/webp':
            $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
            break;
         case 'image/gif':
            $image = @imagecreatefromgif($file);
            break;
         default:
            $image = false;
      }
      if (!$image) throw new \RuntimeException('Bild konnte nicht geladen werden.');
      imagealphablending($image, true);
      imagesavealpha($image, true);
      return $image;
   }

   private function render_image_variant_to_file(array $source, string $file, int $width, int $height, string $fit, string $mime, int $quality): void {
      if (!hash_equals((string)($source['sha256'] ?? ''), hash_file('sha256', (string)$source['file']))) {
         throw new \RuntimeException('Das Quellbild stimmt nicht mehr mit dem geprüften Plan überein.');
      }
      $src = $this->gd_load_image((string)$source['file'], (string)$source['mime']);
      $dst = imagecreatetruecolor($width, $height);
      imagealphablending($dst, false);
      imagesavealpha($dst, true);
      $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
      imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);

      $source_width = imagesx($src);
      $source_height = imagesy($src);
      $source_x = 0;
      $source_y = 0;
      $crop = is_array($source['crop'] ?? null) ? $source['crop'] : array();
      if ($crop) {
         $source_x = max(0, min((int)($crop['x'] ?? 0), $source_width - 1));
         $source_y = max(0, min((int)($crop['y'] ?? 0), $source_height - 1));
         $source_width = max(1, min((int)($crop['width'] ?? $source_width), imagesx($src) - $source_x));
         $source_height = max(1, min((int)($crop['height'] ?? $source_height), imagesy($src) - $source_y));
      }
      if ($fit === 'contain') {
         $scale = min($width / $source_width, $height / $source_height);
         $copy_width = max(1, (int)round($source_width * $scale));
         $copy_height = max(1, (int)round($source_height * $scale));
         imagecopyresampled($dst, $src, (int)floor(($width - $copy_width) / 2), (int)floor(($height - $copy_height) / 2), $source_x, $source_y, $copy_width, $copy_height, $source_width, $source_height);
      } else {
         $source_ratio = $source_width / $source_height;
         $target_ratio = $width / $height;
         if ($source_ratio > $target_ratio) {
            $crop_height = $source_height;
            $crop_width = (int)round($source_height * $target_ratio);
            $src_x = $source_x + (int)floor(($source_width - $crop_width) / 2);
            $src_y = $source_y;
         } else {
            $crop_width = $source_width;
            $crop_height = (int)round($source_width / $target_ratio);
            $src_x = $source_x;
            $src_y = $source_y + (int)floor(($source_height - $crop_height) / 2);
         }
         imagecopyresampled($dst, $src, 0, 0, $src_x, $src_y, $width, $height, $crop_width, $crop_height);
      }
      imagedestroy($src);
      $this->gd_apply_tint($dst, (string)($source['tint'] ?? ''), (float)($source['tint_strength'] ?? 0));
      $this->gd_save_image($dst, $file, $mime, $quality);
      imagedestroy($dst);
   }

   private function gd_apply_tint($image, string $hex, float $strength): void {
      if ($hex === '' || $strength <= 0) return;
      $r = hexdec(substr($hex, 1, 2));
      $g = hexdec(substr($hex, 3, 2));
      $b = hexdec(substr($hex, 5, 2));
      $overlay = imagecreatetruecolor(imagesx($image), imagesy($image));
      imagealphablending($overlay, false);
      imagesavealpha($overlay, true);
      $color = imagecolorallocate($overlay, $r, $g, $b);
      imagefilledrectangle($overlay, 0, 0, imagesx($overlay), imagesy($overlay), $color);
      imagecopymerge($image, $overlay, 0, 0, 0, 0, imagesx($image), imagesy($image), (int)round($strength * 100));
      imagedestroy($overlay);
   }

   private function gd_save_image($image, string $file, string $mime, int $quality): void {
      $ok = false;
      if ($mime === 'image/webp' && function_exists('imagewebp')) {
         $ok = @imagewebp($image, $file, $quality);
      } elseif ($mime === 'image/png') {
         $compression = (int)round((100 - $quality) / 100 * 9);
         $ok = @imagepng($image, $file, max(0, min(9, $compression)));
      } elseif ($mime === 'image/jpeg') {
         $white = imagecreatetruecolor(imagesx($image), imagesy($image));
         $bg = imagecolorallocate($white, 255, 255, 255);
         imagefilledrectangle($white, 0, 0, imagesx($white), imagesy($white), $bg);
         imagecopy($white, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
         $ok = @imagejpeg($white, $file, $quality);
         imagedestroy($white);
      }
      if (!$ok) throw new \RuntimeException('Bildvariante konnte nicht gespeichert werden.');
   }

   private function media_folder($value, string $type): string {
      $folder = trim(str_replace('\\', '/', (string)$value), '/');
      $folder = preg_replace('~[^A-Za-z0-9/_-]+~', '-', $folder);
      if ($type === 'video') {
         return 'img/video';
      }
      $root = $type === 'image' ? 'img' : 'file';
      if ($folder === '' || ($folder !== $root && strpos($folder, $root . '/') !== 0)) $folder = $type === 'image' ? 'img/images' : 'file/ki';
      return $folder;
   }

   private function unique_name(string $dir, string $name): string {
      $base = pathinfo($name, PATHINFO_FILENAME);
      $ext = pathinfo($name, PATHINFO_EXTENSION);
      $candidate = $name;
      $i = 1;
      while (is_file(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $candidate)) {
         $candidate = $base . '-' . $i++ . ($ext !== '' ? '.' . $ext : '');
      }
      return $candidate;
   }
}
