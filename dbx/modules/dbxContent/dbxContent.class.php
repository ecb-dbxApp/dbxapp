<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/include/dbxContent_bootstrap.php';

class dbxContent {

  private function normalize_media_path($path) {
     $rel = rawurldecode((string)$path);
     $rel = str_replace('\\', '/', $rel);
     $rel = preg_replace('~^/?files/~i', '', $rel);
     $rel = ltrim($rel, '/');

     if ($rel === '' || strpos($rel, '..') !== false || preg_match('~(^|/)\.~', $rel)) {
        return '';
     }

     if (!preg_match('~^(media|dbxContent/media)/~i', $rel)) {
        return '';
     }

     return $rel;
  }

  private function files_path($path = '') {
     $base = rtrim(dbx()->get_file_dir(), '/\\') . '/';
     $full = $base . ltrim((string)$path, '/\\');
     return dbx()->os_path($full);
  }

  private function serve_media_file($rel, $mime = '', $file_name = '') {
     $rel = $this->normalize_media_path($rel);
     if ($rel === '') {
        http_response_code(403);
        return '';
     }

     $base = realpath($this->files_path());
     $file = realpath($this->files_path($rel));
     if (!$base || !$file || strpos($file, $base) !== 0 || !is_file($file) || !is_readable($file)) {
        dbx()->log_missing($rel);
        http_response_code(404);
        return '';
     }

     $mime = trim((string)$mime);
     if ($mime === '') {
        $detected = function_exists('mime_content_type') ? mime_content_type($file) : '';
        $mime = $detected ?: 'application/octet-stream';
     }

     $file_name = trim((string)$file_name);
     if ($file_name === '') $file_name = basename($file);
     $file_name = str_replace('"', '', basename($file_name));

     if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
     }

     $size = filesize($file);
     $mtime = filemtime($file) ?: time();
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
     $status = 200;
     $range = isset($_SERVER['HTTP_RANGE']) ? trim((string)$_SERVER['HTTP_RANGE']) : '';
     if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/i', $range, $m)) {
        if ($m[1] !== '') $start = max(0, (int)$m[1]);
        if ($m[2] !== '') $end = min($end, (int)$m[2]);
        if ($m[1] === '' && $m[2] !== '') {
           $suffix = max(0, (int)$m[2]);
           $start = max(0, $size - $suffix);
        }
        if ($start <= $end && $size > 0) {
           $status = 206;
           http_response_code(206);
           header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        } else {
           http_response_code(416);
           header('Content-Range: bytes */' . $size);
           exit;
        }
     }

     header('Content-Type: ' . $mime);
     header('Content-Length: ' . max(0, $end - $start + 1));
     header('Content-Disposition: inline; filename="' . $file_name . '"');
     header('Accept-Ranges: bytes');
     header('X-Content-Type-Options: nosniff');
     header('ETag: ' . $etag);
     header('Last-Modified: ' . $lastModified);
     header('Cache-Control: private, no-cache');
     $out = fopen($file, 'rb');
     if ($out) {
        if ($start > 0) fseek($out, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($out)) {
           $chunk = fread($out, min(1048576, $remaining));
           if ($chunk === false || $chunk === '') break;
           echo $chunk;
           $remaining -= strlen($chunk);
           if (function_exists('connection_aborted') && connection_aborted()) break;
        }
        fclose($out);
     }
     exit;
  }

  private function media_missing_key($id, $reason, $extra = '') {
     if ($reason === 'file_missing' && trim((string)$extra) !== '') {
        return ltrim(str_replace('\\', '/', (string)$extra), '/');
     }
     $uri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));
     if ($uri !== '') return $uri;
     return 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . (int)$id;
  }

  private function media_serve_fail($code, $id, $reason, $extra = '') {
     dbx()->debug('serve_media', array('id' => (int)$id, 'code' => (int)$code, 'reason' => (string)$reason, 'extra' => (string)$extra));
     if ((int)$code === 404) {
        dbx()->log_missing($this->media_missing_key($id, $reason, $extra));
     }
     http_response_code((int)$code);
     return '';
  }

  private function media_row_type(array $row) {
     $stored = strtolower(trim((string)($row['media_type'] ?? '')));
     if (in_array($stored, array('image','video','external_video','file'), true)) return $stored;
     if (strtolower((string)($row['storage_type'] ?? '')) === 'external') return 'external_video';
     if (trim((string)($row['provider'] ?? '')) !== '' || trim((string)($row['embed_url'] ?? '')) !== '') return 'external_video';
     return 'file';
  }

  private function external_video_thumb_url(array $row) {
     $provider = strtolower(trim((string)($row['provider'] ?? '')));
     $provider_id = trim((string)($row['provider_id'] ?? ''));
     if ($provider === 'youtube' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id)) {
        return 'https://img.youtube.com/vi/' . $provider_id . '/hqdefault.jpg';
     }
     return '';
  }

  private function serve_external_media(array $row) {
     $want_thumb = (int)dbx()->get_modul_var('dbx_thumb', 0, 'int') === 1;
     if ($want_thumb && !empty($row['thumb_file_path'])) {
        return $this->serve_media_file(
           (string)($row['thumb_file_path'] ?? ''),
           'image/jpeg',
           'thumb-' . (string)($row['file_name'] ?? '')
        );
     }
     $thumb_url = $this->external_video_thumb_url($row);
     if ($thumb_url !== '') {
        header('Location: ' . $thumb_url, true, 302);
        exit;
     }
     return $this->media_serve_fail(404, (int)($row['id'] ?? 0), 'external_unavailable');
  }

  private function serve_media() {
     $id = (int)dbx()->get_modul_var('dbx_mid', 0, 'int');
     if ($id <= 0) {
        return $this->media_serve_fail(404, $id, 'invalid_id');
     }

     $db = dbx()->get_system_obj('dbxDB');
     $row = $db->select1('dbxMedia', $id);
     if (!is_array($row)) {
        return $this->media_serve_fail(404, $id, 'not_found');
     }
     if ((int)($row['active'] ?? 0) !== 1) {
        return $this->media_serve_fail(404, $id, 'inactive');
     }

     if ($this->media_row_type($row) === 'external_video') {
        return $this->serve_external_media($row);
     }

     $want_thumb = (int)dbx()->get_modul_var('dbx_thumb', 0, 'int') === 1;
     $rel = '';
     $mime = (string)($row['mime'] ?? '');
     $file_name = (string)($row['file_name'] ?? '');
     if ($want_thumb && !empty($row['thumb_file_path'])) {
        $rel = (string)$row['thumb_file_path'];
        $mime = preg_match('/\.webp$/i', $rel) ? 'image/webp' : 'image/jpeg';
        $file_name = 'thumb-' . $file_name;
     } else {
        $rel = (string)($row['file_path'] ?? '');
     }
     if ($rel === '') {
        return $this->media_serve_fail(404, $id, 'empty_path');
     }

     $rel_norm = $this->normalize_media_path($rel);
     if ($rel_norm === '') {
        return $this->media_serve_fail(403, $id, 'invalid_path', $rel);
     }

     $base = realpath($this->files_path());
     $file = realpath($this->files_path($rel_norm));
     if (!$base || !$file || strpos($file, $base) !== 0 || !is_file($file) || !is_readable($file)) {
        if ($want_thumb && !empty($row['file_path'])) {
           return $this->serve_media_file(
              (string)$row['file_path'],
              (string)($row['mime'] ?? ''),
              (string)($row['file_name'] ?? '')
           );
        }
        return $this->media_serve_fail(404, $id, 'file_missing', $rel);
     }

     return $this->serve_media_file($rel, $mime, $file_name);
  }

  private function runContentPage(): string {
     $obj = dbx()->get_include_obj('dbxContent_content');
     return $obj->run();
  }

  public function run($action='') {
     $uid   =dbx()->user();
     $mid   =dbx()->get_system_var('dbx_modul_id');
     $modul =dbx()->get_system_var('dbx_modul');
     //dbx()->set_system_var('dbx_page', 'content');

     $content="";
     if (!$action) $action=dbx()->get_modul_var('dbx_run1','show');

     switch ($action) {
       case 'cms':
           if (!dbxContentLng::isCmsPermalinkMode()) {
              $work = dbx()->get_modul_var('dbx_run2', 'show', 'parameter');
              if ($work === 'tree' || $work === 'page') {
                 $obj = dbx()->get_include_obj('dbxContent_treeview');
                 $content = $obj->run($work === 'tree' ? 'tree_view' : 'tree_page');
                 break;
              }
              $content = $this->runContentPage();
              break;
           }
           $work = dbx()->get_modul_var('dbx_run2', 'show', 'parameter');
           $obj=dbx()->get_include_obj('dbxContent_treeview');
           if ($work === 'tree') {
              $content=$obj->run('tree_view');
           } elseif ($work === 'page') {
              $content=$obj->run('tree_page');
           } else {
              $content=$obj->run('view');
           }
       break;

       case 'content':
           if (dbx()->get_modul_var('dbx_run2', '', 'parameter') === 'edit') {
              if (!dbxContentLng::isCmsPermalinkMode()) {
                 $content = $this->runContentPage();
                 break;
              }
              $obj=dbx()->get_include_obj('dbxContent_treeview');
              $content=$obj->run('view');
              break;
           }
           $obj=dbx()->get_include_obj('dbxContent_content');
           $content=$obj->run();
       break;

       case 'edit':
           if (!dbxContentLng::isCmsPermalinkMode()) {
              $content = $this->runContentPage();
              break;
           }
           $obj=dbx()->get_include_obj('dbxContent_treeview');
           $content=$obj->run('view');
       break;

       case 'tree_view':
       //case 'tree_page':
           $obj=dbx()->get_include_obj('dbxContent_treeview');
           $content=$obj->run($action);
       break;

       //case 'run':
       case 'show':
           $obj=dbx()->get_include_obj('dbxContent_content');
           $content=$obj->run();
       break;

       case 'media':
           $content=$this->serve_media();
       break;

       case 'help':
           $obj=dbx()->get_include_obj('dbxContentContextHelp');
           $content=is_object($obj) ? $obj->run() : '';
       break;

       case 'consent':
           $obj=dbx()->get_include_obj('dbxContentConsent');
           $content=is_object($obj) ? $obj->run() : '';
       break;

       case 'sitemap':
           dbxContentSitemap::serve();
       break;

       case 'robots':
           dbxContentSitemap::serveRobots();
       break;

       default:
         $content.="<span class='warning action_msg'>Modul=($modul) Action=($action) is undef.</span>";
     } // switch

     return $content;
  } // run()

} // class

?>
