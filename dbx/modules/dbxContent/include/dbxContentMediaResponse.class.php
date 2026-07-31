<?php
namespace dbx\dbxContent;

/**
 * Bedient geschuetzte CMS-Mediendateien direkt aus dem dbxContent-Modul.
 */
class dbxContentMediaResponse {

   /** Bedient den aktuellen Request, sofern er eine dbxContent-Medien-URL ist. */
   public function serve_request(): void {
      $modul = (string)($_GET['dbx_modul'] ?? '');
      $run1 = (string)($_GET['dbx_run1'] ?? '');
      $mid = isset($_GET['dbx_mid']) ? (int)$_GET['dbx_mid'] : 0;
      $thumb = isset($_GET['dbx_thumb']) && (string)$_GET['dbx_thumb'] === '1';
      if ($modul !== 'dbxContent' || $run1 !== 'media' || $mid <= 0) return;

      try {
         $db = dbx()->get_system_obj('dbxDB');
         $row = is_object($db)
            ? $db->select1('dbxMedia', array('id' => $mid, 'active' => 1), '*', 0)
            : array();
         if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
            $this->fail(404, $mid);
         }

         $relKey = $thumb && !empty($row['thumb_file_path']) ? 'thumb_file_path' : 'file_path';
         $rel = $this->safe_relative_path((string)($row[$relKey] ?? ''));
         if ($rel === null) {
            http_response_code(403);
            exit;
         }

         $root = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/');
         $base = realpath($root);
         $real = $this->readable_file($base, $root, $rel);
         $servedThumb = $thumb && $relKey === 'thumb_file_path';

         if ($real === null && $servedThumb && !empty($row['file_path'])) {
            $fallbackRel = $this->safe_relative_path((string)$row['file_path']);
            if ($fallbackRel !== null) {
               $fallbackReal = $this->readable_file($base, $root, $fallbackRel);
               if ($fallbackReal !== null) {
                  $real = $fallbackReal;
                  $rel = $fallbackRel;
                  $servedThumb = false;
               }
            }
         }

         if ($real === null) {
            $this->fail(404, $mid, $rel);
         }

         $mime = $servedThumb ? '' : trim((string)($row['mime'] ?? ''));
         if ($mime === '') {
            $detected = function_exists('mime_content_type') ? mime_content_type($real) : '';
            $mime = $detected ?: 'application/octet-stream';
         }

         $fileName = trim((string)($row['file_name'] ?? ''));
         if ($fileName === '') $fileName = basename($real);
         $this->stream_file($real, $mime, $fileName);
      } catch (\Throwable $e) {
         dbx()->write_php_error_log(get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
         http_response_code(500);
         exit;
      }
   }

   private function missing_key(int $mid, string $rel = ''): string {
      if (trim($rel) !== '') return ltrim(str_replace('\\', '/', $rel), '/');
      $uri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));
      return $uri !== '' ? $uri : 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $mid;
   }

   private function fail(int $code, int $mid, string $rel = ''): void {
      if ($code === 404) dbx()->log_missing($this->missing_key($mid, $rel));
      http_response_code($code);
      exit;
   }

   private function safe_relative_path(string $rel): ?string {
      $rel = ltrim(str_replace('\\', '/', rawurldecode($rel)), '/');
      if ($rel === '' || strpos($rel, '..') !== false || !preg_match('~^(media|dbxContent/media)/~i', $rel)) {
         return null;
      }
      return $rel;
   }

   private function readable_file($base, string $root, string $rel): ?string {
      if (!$base) return null;
      $real = realpath(dbx()->os_path($root . $rel));
      if (!$real || !is_file($real) || !is_readable($real)) return null;

      $basePath = rtrim(str_replace('\\', '/', (string)$base), '/') . '/';
      $realPath = str_replace('\\', '/', $real);
      if (!str_starts_with($realPath, $basePath)) return null;
      return $real;
   }

   private function stream_file(string $file, string $mime, string $fileName): void {
      while (ob_get_level() > 0) {
         if (!@ob_end_clean()) break;
      }
      if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

      $size = (int)filesize($file);
      $mtime = (int)(filemtime($file) ?: time());
      $etag = '"' . md5($file . '|' . $size . '|' . $mtime) . '"';
      $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
      $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
      $ifModifiedSince = trim((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
      if ($ifNoneMatch === $etag || ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime)) {
         http_response_code(304);
         header('ETag: ' . $etag);
         header('Last-Modified: ' . $lastModified);
         header('Cache-Control: private, no-cache');
         exit;
      }

      $start = 0;
      $end = $size > 0 ? $size - 1 : 0;
      $range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
      if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/i', $range, $match)) {
         if ($match[1] !== '') $start = max(0, (int)$match[1]);
         if ($match[2] !== '') $end = min($end, (int)$match[2]);
         if ($match[1] === '' && $match[2] !== '') {
            $start = max(0, $size - max(0, (int)$match[2]));
         }
         if ($start <= $end && $size > 0) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
         } else {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
         }
      }

      $fileName = str_replace('"', '', basename($fileName));
      header('Content-Type: ' . $mime);
      header('Content-Length: ' . max(0, $end - $start + 1));
      header('Content-Disposition: inline; filename="' . $fileName . '"');
      header('Accept-Ranges: bytes');
      header('ETag: ' . $etag);
      header('Last-Modified: ' . $lastModified);
      header('Cache-Control: private, no-cache');
      header('X-Content-Type-Options: nosniff');

      if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
         exit;
      }

      $out = fopen($file, 'rb');
      if ($out) {
         if ($start > 0) fseek($out, $start);
         $remaining = $end - $start + 1;
         while ($remaining > 0 && !feof($out)) {
            $chunk = fread($out, min(8192, $remaining));
            if ($chunk === false || $chunk === '') break;
            echo $chunk;
            $remaining -= strlen($chunk);
            if (function_exists('connection_aborted') && connection_aborted()) break;
         }
         fclose($out);
      }
      exit;
   }
}
