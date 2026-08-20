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
 * Medienordner, Verschieben, unbenutzte Medien und Bildbearbeitungsaktionen.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsMediaFolderServiceTrait {





   private function is_reserved_media_folder($folder) {
      $folder = $this->canonical_media_folder(strtolower(trim(str_replace('\\', '/', (string)$folder), '/')));
      if ($folder === 'module' || strpos($folder, 'module/') === 0) return true;
      if (preg_match('~^img/external(/|$)~', $folder)) return true;
      if (preg_match('~^img/video(/|$)~', $folder)) return true;
      return in_array($folder, $this->media_slots(), true);
   }



   private function media_folder_dir_is_listable($folder, $path, $base = '') {
      return is_dir($path) && is_readable($path);
   }






   private function collect_custom_media_root_folders(array &$folders) {
      $media_root = rtrim(dbx()->get_file_dir(), '/\\') . '/media/';
      $media_root = dbx()->os_path($media_root);
      if (!is_dir($media_root) || !is_readable($media_root)) return;
      $skip = array('.', '..', '_thumbs', 'img', 'video', 'videos', 'youtube', 'external', 'file', 'module');
      $skip = array_merge($skip, $this->media_slots());
      $root_norm = str_replace('\\', '/', rtrim($media_root, '/\\')) . '/';
      $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($media_root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
      foreach ($it as $item) {
         if (!$item->isDir()) continue;
         $path = str_replace('\\', '/', $item->getPathname());
         if (strpos($path . '/', $root_norm) !== 0) continue;
         $rel = trim(substr($path, strlen($root_norm)), '/');
         if ($rel === '') continue;
         $top = strtok($rel, '/');
         if ($top === false || in_array($top, $skip, true)) continue;
         if ($this->is_reserved_media_folder($rel)) continue;
         $folders[] = $rel;
      }
   }



   private function media_folders_json() {
      $bases = $this->media_standard_bases();
      $folders = array();
      foreach ($bases as $base => $type) {
         $root = $this->cms_media_base_dir($base);
         if (!is_dir($root) || !is_readable($root)) continue;
         if ($base === 'youtube' || $type === 'video') {
            $folders[] = $this->clean_media_folder($base, $type);
         }
         $root_norm = str_replace('\\', '/', rtrim($root, '/\\')) . '/';
         $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
         foreach ($it as $item) {
            if (!$item->isDir()) continue;
            $path = str_replace('\\', '/', $item->getPathname());
            if (strpos($path . '/', $root_norm) !== 0) continue;
            $rel = trim(substr($path, strlen($root_norm)), '/');
            if ($rel === '') continue;
            $folder = $this->clean_media_folder($base . '/' . $rel, $type);
            if ($this->is_reserved_media_folder($folder)) continue;
            if (!$this->media_folder_dir_is_listable($folder, $path, $base)) continue;
            $folders[] = $folder;
         }
      }
      $this->collect_custom_media_root_folders($folders);

      $folders = array_values(array_unique(array_map(array($this, 'canonical_media_folder'), $folders)));
      $verified = array();
      foreach ($folders as $folder) {
         $folder = $this->canonical_media_folder($folder);
         if ($folder === '' || $this->is_excluded_cms_media_folder($folder)) continue;
         $dir = $this->cms_media_dir($folder);
         if (!is_dir($dir) || !is_readable($dir)) continue;
         $verified[] = $folder;
      }
      $folders = $verified;
      sort($folders);
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'root' => 'files/media/', 'folders' => $folders));
   }



   private function media_folder_create_json() {
      $payload = $this->request_json();
      $type = $this->media_type(array('media_type' => ($payload['media_type'] ?? 'image')));
      $folder = $this->clean_media_folder($payload['media_folder'] ?? $payload['folder'] ?? '', $type);
      if ($this->is_excluded_cms_media_folder($folder)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Dieser Ordner ist im CMS-Medienbrowser nicht verfuegbar.'));
      }
      $dir = $this->cms_media_dir($folder);
      if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner konnte nicht angelegt werden.'));
      }
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'folder' => $folder, 'msg' => 'Ordner angelegt.'));
   }



   private function media_folder_delete_json() {
      $payload = $this->request_json();
      $raw_folder = $payload['media_folder'] ?? $payload['folder'] ?? '';
      $type = $this->media_type(array('media_type' => ($payload['media_type'] ?? $this->media_type_from_folder($raw_folder))));
      $folder = $this->clean_media_folder($raw_folder, $type);
      if ($this->is_excluded_cms_media_folder($folder)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Dieser Ordner ist im CMS-Medienbrowser nicht verfuegbar.'));
      }
      $dir = $this->cms_media_dir($folder);
      if (!is_dir($dir)) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner nicht gefunden.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $used = $db->select($this->dd_media, "active = 1 AND media_folder = '" . str_replace("'", "''", $folder) . "'", '*', 'id', 'ASC', '', 1, 0, 0);
      if (is_array($used) && isset($used[0])) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner enthaelt noch Medien.'));

      $items = @scandir($dir);
      $items = is_array($items) ? array_diff($items, array('.', '..')) : array();
      if (!empty($items)) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner ist nicht leer.'));
      if (!@rmdir($dir)) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner konnte nicht geloescht werden.'));
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'folder' => $folder, 'msg' => 'Ordner geloescht.'));
   }



   private function media_folder_rename_json() {
      $payload = $this->request_json();
      $from = $this->clean_media_folder($payload['from_folder'] ?? $payload['media_folder_from'] ?? $payload['from'] ?? '', 'image');
      $to_raw = trim((string)($payload['to_folder'] ?? $payload['media_folder_to'] ?? $payload['to'] ?? ''));
      if ($to_raw !== '' && strpos($to_raw, '/') === false && $from !== '') {
         $base = strtok($from, '/');
         $to_raw = ($base !== false && $base !== '' ? $base : 'img') . '/' . $to_raw;
      }
      $to = $this->clean_media_folder($to_raw, $this->media_type_from_folder($to_raw !== '' ? $to_raw : $from));
      if ($from === '' || $to === '' || $from === $to) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ungueltiger Ordner.'));
      }
      if ($this->is_excluded_cms_media_folder($from) || $this->is_excluded_cms_media_folder($to)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Dieser Ordner ist im CMS-Medienbrowser nicht verfuegbar.'));
      }
      $from_dir = $this->cms_media_dir($from);
      $to_dir = $this->cms_media_dir($to);
      if (!is_dir($from_dir) || !is_readable($from_dir)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Quellordner nicht gefunden.'));
      }
      if (is_dir($to_dir)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Zielordner existiert bereits.'));
      }
      if (!@rename($from_dir, $to_dir)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner konnte nicht umbenannt werden.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $from_prefix = 'media/' . $from . '/';
      $to_prefix = 'media/' . $to . '/';
      $thumb_from = 'media/_thumbs/' . $from . '/';
      $thumb_to = 'media/_thumbs/' . $to . '/';
      $thumb_from_dir = $this->file_from_media_rel($thumb_from);
      $thumb_to_dir = $this->file_from_media_rel($thumb_to);
      if ($thumb_from_dir !== '' && is_dir($thumb_from_dir) && $thumb_to_dir !== '' && !is_dir($thumb_to_dir)) {
         @rename($thumb_from_dir, $thumb_to_dir);
      }

      $rows = $db->select($this->dd_media, "active = 1 AND (media_folder = '" . str_replace("'", "''", $from) . "' OR file_path LIKE '" . str_replace("'", "''", $from_prefix) . "%')", '*', 'id');
      if (!is_array($rows)) $rows = array();
      $updated = 0;
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) continue;
         $path = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         $new_path = $path;
         if (strpos($path, $from_prefix) === 0) {
            $new_path = $to_prefix . substr($path, strlen($from_prefix));
         }
         $update = array(
            'media_folder' => $to,
            'file_path' => $new_path,
         );
         $thumb = ltrim(str_replace('\\', '/', (string)($row['thumb_file_path'] ?? '')), '/');
         if ($thumb !== '' && strpos($thumb, $thumb_from) === 0) {
            $update['thumb_file_path'] = $thumb_to . substr($thumb, strlen($thumb_from));
         }
         $db->update($this->dd_media, $update, $id);
         $updated++;
      }

      $this->cms_json_response(array(
         'ok' => 1,
         'success' => true,
         'from_folder' => $from,
         'to_folder' => $to,
         'updated' => $updated,
         'msg' => 'Ordner umbenannt.',
      ));
   }



   private function media_is_used($db, int $id): bool {
      if ($id <= 0) return true;
      $used = $db->select(
         $this->dd_media_usage,
         'media_id = ' . $id . ' AND active = 1',
         'id',
         'id',
         'ASC',
         '',
         1,
         0,
         0
      );
      if (is_array($used) && isset($used[0])) return true;

      foreach (dbxContentLngSync::accessible_lngs() as $lng) {
         $content_dd = dbxContentLng::dd_content((string)$lng);
         $folder_dd = dbxContentLng::dd_folder((string)$lng);
         if ($db->count($content_dd, 'hero_image_id = ' . $id . ' OR seo_image_id = ' . $id) > 0 || $db->count($folder_dd, 'hero_image_id = ' . $id) > 0) {
            return true;
         }
         $pages = $db->select($content_dd, '', 'content', 'id', 'ASC', '', 0, 0, 0);
         foreach (is_array($pages) ? $pages : array() as $page) {
            $content = (string)($page['content'] ?? '');
            if (preg_match('/data-cms-media-id=["\']?' . $id . '(?:["\'\s>]|$)/i', $content)
                || preg_match('/(?:dbx_mid|media_id)=' . $id . '(?:[^0-9]|$)/i', $content)) {
               return true;
            }
         }
      }
      return false;
   }



   private function move_media_record_to_folder($db, int $id, string $target_folder): array {
      if ($id <= 0) return array('ok' => 0, 'msg' => 'Keine Medien-ID erhalten.');
      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) return array('ok' => 0, 'msg' => 'Medium nicht gefunden.');

      $media_type = $this->media_type($row);
      $folder = $this->clean_media_folder($target_folder, $media_type);
      if ($this->is_excluded_cms_media_folder($folder)) {
         return array('ok' => 0, 'msg' => 'Dieser Zielordner ist im CMS-Medienbrowser nicht verfuegbar.');
      }
      $old_file = $this->source_media_file($row);
      $name = basename((string)($row['file_name'] ?? $row['file_path'] ?? ''));
      if ($name === '') return array('ok' => 0, 'msg' => 'Dateiname fehlt.');
      $dir = $this->cms_media_dir($folder);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      if (!is_dir($dir)) return array('ok' => 0, 'msg' => 'Zielordner konnte nicht angelegt werden.');
      $new_rel = $this->media_folder_rel_dir($folder, $media_type) . $name;
      $new_file = $this->file_from_media_rel($new_rel);
      if ($old_file !== '' && $new_file !== '' && $old_file !== $new_file) {
         if (is_file($new_file)) {
            $name = $this->unique_media_name($name);
            $new_rel = $this->media_folder_rel_dir($folder, $media_type) . $name;
            $new_file = $this->file_from_media_rel($new_rel);
         }
         if (!@rename($old_file, $new_file)) return array('ok' => 0, 'msg' => 'Medium konnte nicht verschoben werden.');
      }
      $old_thumb = $this->source_thumb_file($row);
      $mime = (string)($row['mime'] ?? '');
      $thumb = $this->create_media_thumbnail($new_file !== '' ? $new_file : $old_file, $folder, $name, $mime);
      if ($old_thumb !== '' && is_file($old_thumb) && $old_thumb !== $this->file_from_media_rel((string)($thumb['thumb_file_path'] ?? ''))) {
         @unlink($old_thumb);
      }
      $update = array('active' => 1, 'media_folder' => $folder, 'file_name' => $name, 'file_path' => $new_rel);
      if ($thumb) $update = array_merge($update, $thumb);
      $ok = $db->update($this->dd_media, $update, $id, 0, 1, 1, 0);
      if ($ok < 0) return array('ok' => 0, 'msg' => 'Mediendaten konnten nicht aktualisiert werden.');
      $row = $this->normalize_media_row($db->select1($this->dd_media, $id));
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || (string)($row['media_folder'] ?? '') !== $folder) {
         return array('ok' => 0, 'msg' => 'Mediendaten wurden nicht konsistent gespeichert.');
      }
      return array('ok' => 1, 'success' => true, 'row' => $row, 'msg' => 'Medium verschoben.');
   }



   private function media_move_json() {
      $payload = $this->request_json();
      $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      if ($id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Medien-ID erhalten.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $result = $this->move_media_record_to_folder($db, $id, (string)($payload['media_folder'] ?? $payload['folder'] ?? ''));
      $this->cms_json_response($result);
   }



   private function media_unused_json() {
      $payload = $this->request_json();
      $action = strtolower(trim((string)($payload['action'] ?? '')));
      if (!in_array($action, array('delete', 'move'), true)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ungueltige Aktion.'));
      }

      $source_raw = trim((string)($payload['source_folder'] ?? $payload['media_folder'] ?? 'all'));
      $source = strtolower($source_raw) === 'all' || $source_raw === ''
         ? 'all'
         : $this->clean_media_folder($source_raw, $this->media_type_from_folder($source_raw, 'image'));
      if ($source !== 'all' && $this->is_excluded_cms_media_folder($source)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Dieser Quellordner ist im CMS-Medienbrowser nicht verfuegbar.'));
      }

      $target = '';
      if ($action === 'move') {
         $target_raw = trim((string)($payload['target_folder'] ?? $payload['to_folder'] ?? ''));
         if ($target_raw === '') {
            $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Bitte einen Zielordner waehlen.'));
         }
         $target = $this->clean_media_folder($target_raw, 'image');
         if ($target === '' || $this->is_excluded_cms_media_folder($target)) {
            $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Bitte einen gueltigen Zielordner waehlen.'));
         }
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $this->sync_cms_media_files($db);

      $where = "active = 1"
         . $this->cms_media_exclude_sql()
         . " AND (mime LIKE 'image/%' OR file_name LIKE '%.jpg' OR file_name LIKE '%.jpeg' OR file_name LIKE '%.png' OR file_name LIKE '%.gif' OR file_name LIKE '%.webp' OR file_name LIKE '%.svg')";
      if ($source !== 'all') {
         $where .= " AND media_folder = '" . str_replace("'", "''", $source) . "'";
      }
      if ($action === 'move') {
         $where .= " AND media_folder <> '" . str_replace("'", "''", $target) . "'";
      }

      $rows = $db->select($this->dd_media, $where, '*', 'media_folder,title,id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) $rows = array();
      $rows = $this->filter_existing_media($db, $rows);

      $checked = 0;
      $affected = 0;
      $skipped_used = 0;
      $errors = array();
      $moved_rows = array();

      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) continue;
         $checked++;
         if ($this->media_is_used($db, $id)) {
            $skipped_used++;
            continue;
         }
         if ($action === 'delete') {
            $result = $this->delete_media_record($id);
            if ((int)($result['ok'] ?? 0) === 1) {
               $affected++;
            } else {
               $errors[] = '#' . $id . ': ' . implode(' ', (array)($result['errors'] ?? array('Loeschen fehlgeschlagen.')));
            }
            continue;
         }
         $result = $this->move_media_record_to_folder($db, $id, $target);
         if ((int)($result['ok'] ?? 0) === 1) {
            $affected++;
            if (!empty($result['row']) && is_array($result['row'])) $moved_rows[] = $result['row'];
         } else {
            $errors[] = '#' . $id . ': ' . (string)($result['msg'] ?? 'Verschieben fehlgeschlagen.');
         }
      }

      $label = $action === 'delete' ? 'geloescht' : 'verschoben';
      $msg = $affected . ' unbenutzte Bilder ' . $label . '.';
      if ($skipped_used > 0) $msg .= ' ' . $skipped_used . ' verwendete Bilder uebersprungen.';
      if (!empty($errors)) $msg .= ' ' . count($errors) . ' Fehler.';

      $this->cms_json_response(array(
         'ok' => empty($errors) ? 1 : ($affected > 0 ? 1 : 0),
         'success' => empty($errors) || $affected > 0,
         'action' => $action,
         'source_folder' => $source,
         'target_folder' => $target,
         'checked' => $checked,
         'affected' => $affected,
         'skipped_used' => $skipped_used,
         'errors' => $errors,
         'rows' => $moved_rows,
         'msg' => $msg,
      ));
   }



   private function edit_image_file($file, $mime, array $op) {
      if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) return false;
      $img = $this->load_gd_image($file, $mime);
      if (!$img) return false;

      $src_w = imagesx($img);
      $src_h = imagesy($img);
      if ($src_w <= 0 || $src_h <= 0) {
         imagedestroy($img);
         return false;
      }

      $action = strtolower((string)($op['action'] ?? 'resize'));
      if ($action === 'crop') {
         $x = max(0, min($src_w - 1, (int)($op['x'] ?? 0)));
         $y = max(0, min($src_h - 1, (int)($op['y'] ?? 0)));
         $w = max(1, min($src_w - $x, (int)($op['width'] ?? $src_w)));
         $h = max(1, min($src_h - $y, (int)($op['height'] ?? $src_h)));
         $dst = imagecreatetruecolor($w, $h);
         imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
         imagecopyresampled($dst, $img, 0, 0, $x, $y, $w, $h, $w, $h);
      } else {
         $w = (int)($op['width'] ?? 0);
         $h = (int)($op['height'] ?? 0);
         $ratio = !empty($op['ratio']);
         if ($w <= 0 && $h <= 0) {
            imagedestroy($img);
            return false;
         }
         if ($w <= 0) $w = max(1, (int)round($src_w * ($h / $src_h)));
         if ($h <= 0) $h = max(1, (int)round($src_h * ($w / $src_w)));
         if ($ratio && $w > 0 && $h > 0) {
            $scale = min($w / $src_w, $h / $src_h);
            $w = max(1, (int)round($src_w * $scale));
            $h = max(1, (int)round($src_h * $scale));
         }
         $dst = imagecreatetruecolor($w, $h);
         imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
         imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, $src_w, $src_h);
      }

      $ok = $this->save_gd_image($dst, $file, $mime);
      imagedestroy($dst);
      imagedestroy($img);
      return $ok;
   }



   private function edit_media_json() {
      try {
      $payload = $this->request_json();
      $ids = array();
      if (isset($payload['ids']) && is_array($payload['ids'])) {
         foreach ($payload['ids'] as $id) {
            $id = (int)$id;
            if ($id > 0) $ids[$id] = $id;
         }
      } else {
         $id = (int)($payload['id'] ?? 0);
         if ($id > 0) $ids[$id] = $id;
      }
      if (!$ids) $this->cms_json_response(array('ok' => 0, 'msg' => 'Keine Medien ausgewaehlt.'));

      $action = strtolower((string)($payload['action'] ?? 'resize'));
      if (!in_array($action, array('resize', 'crop'), true)) $action = 'resize';
      if ($action === 'crop' && count($ids) > 1) $this->cms_json_response(array('ok' => 0, 'msg' => 'Zuschneiden ist nur fuer ein Bild moeglich.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $done = array();
      foreach ($ids as $id) {
         $row = $db->select1($this->dd_media, $id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || $this->media_type($row) !== 'image') continue;
         if (preg_match('/\.svg$/i', (string)($row['file_name'] ?? ''))) continue;
         $file = $this->source_media_file($row);
         if ($file === '') continue;
         $mime = (string)($row['mime'] ?? '');
         if (!$this->edit_image_file($file, $mime, array(
            'action' => $action,
            'x' => (int)($payload['x'] ?? 0),
            'y' => (int)($payload['y'] ?? 0),
            'width' => (int)($payload['width'] ?? 0),
            'height' => (int)($payload['height'] ?? 0),
            'ratio' => !empty($payload['ratio']),
         ))) continue;

         $old_thumb = $this->source_thumb_file($row);
         if ($old_thumb !== '') @unlink($old_thumb);
         $size = @getimagesize($file);
         $update = array(
            'size' => (int)@filesize($file),
            'width' => is_array($size) ? (int)$size[0] : 0,
            'height' => is_array($size) ? (int)$size[1] : 0,
            'media_type' => 'image',
            'thumb_file_path' => '',
            'thumb_width' => 0,
            'thumb_height' => 0,
         );
         $media_folder = $this->canonical_media_folder(trim((string)($row['media_folder'] ?? '')) ?: $this->media_folder_from_path((string)($row['file_path'] ?? ''), $this->media_type($row)));
         $thumb = $this->create_media_thumbnail($file, $media_folder, (string)($row['file_name'] ?? basename($file)), $mime);
         if ($thumb) $update = array_merge($update, $thumb);
         $db->update($this->dd_media, $update, $id);
         $done[] = $this->normalize_media_row($db->select1($this->dd_media, $id));
      }

      if (!$done) $this->cms_json_response(array('ok' => 0, 'msg' => 'Keine bearbeitbaren Bilder gefunden.'));
      foreach ($ids as $id) {
         $this->flush_media_by_media_id($db, (int)$id);
      }
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'rows' => $done));
      } catch (\Throwable $e) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => 'Medienbearbeitung fehlgeschlagen: ' . $e->getMessage()
         ));
      }
   }
}
