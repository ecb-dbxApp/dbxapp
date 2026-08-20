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
 * Medienzuweisung, Slots, Upload und externe Videoquellen.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsMediaAssignmentServiceTrait {


   private function is_no_hero_template($value) {
      return \dbx\dbxContent\dbxContentRuntime::is_no_hero($value);
   }



   private function normalize_hero_payload(array $data) {
      if ($this->is_no_hero_template($data['hero_template'] ?? '')) {
         $data['hero_template'] = 'none';
         $data['hero_image_id'] = '0';
      }
      return $data;
   }



   private function source_media_file($row) {
      $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return '';
      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/';
      $root = dbx()->os_path($root);
      $base = realpath(rtrim($root, '/\\'));
      if (!$base) return '';
      $file = $root . $rel;
      $file = dbx()->os_path($file);
      $real = realpath($file);
      return $real && strpos($real, $base) === 0 && is_file($real) && is_readable($real) ? $real : '';
   }






   private function set_media_slot_json() {
      $payload = $this->request_json();
      $usage_id = (int)($payload['usage_id'] ?? 0);
      $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      $slot = $this->valid_media_slot($payload['slot'] ?? 'gallery');
      if ($usage_id <= 0 && $id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Medien- oder Usage-ID erhalten.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      if ($usage_id <= 0) {
         $rows = $db->select($this->dd_media_usage, $this->usage_where($id), '*', 'id', 'DESC', '', 1, 0, 0);
         if (is_array($rows) && isset($rows[0])) $usage_id = (int)($rows[0]['id'] ?? 0);
      }
      if ($usage_id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine vorhandene Zuordnung gefunden.'));
      }

      $usage = $db->select1($this->dd_media_usage, $usage_id);
      if (!is_array($usage) || (int)($usage['active'] ?? 0) !== 1) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Zuordnung nicht gefunden.'));
      }
      if ($slot === 'hero') $this->deactivate_existing_hero_media($db, (int)($usage['content_id'] ?? 0), (int)($usage['folder_id'] ?? 0), (int)($usage['media_id'] ?? 0));
      $ok = $db->update($this->dd_media_usage, array('slot' => $slot), $usage_id);
      $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
      $row = $this->normalize_media_row($db->select1($this->dd_media, (int)($usage['media_id'] ?? 0)));
      if ($ok >= 0) {
         $this->flush_media_cache($db, (int)($usage['content_id'] ?? 0), (int)($usage['folder_id'] ?? 0));
      }
      $this->cms_json_response(array('ok' => ($ok >= 0 ? 1 : 0), 'success' => ($ok >= 0), 'row' => $row, 'usage' => $usage, 'msg' => 'Zuordnung aktualisiert.'));
   }



   private function assign_media_json() {
      $payload = $this->request_json();
      $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      $content_id = (int)($payload['content_id'] ?? 0);
      $folder_id = (int)($payload['folder_id'] ?? 0);
      $slot = $this->valid_media_slot($payload['slot'] ?? 'gallery');
      if ($id <= 0 || ($content_id <= 0 && !($folder_id > 0 && $slot === 'hero'))) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Zuordnung erhalten.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Medium nicht gefunden.'));
      }

      $usage_id = $this->create_media_usage(
         $db,
         $id,
         $content_id,
         $folder_id,
         $slot,
         (string)($payload['template'] ?? $row['template'] ?? ''),
         (string)($payload['caption'] ?? ''),
         is_array($payload['settings'] ?? null) ? json_encode($payload['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)($payload['settings'] ?? '')
      );
      if ($usage_id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Medium konnte nicht zugeordnet werden.'));
      $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
      $this->flush_media_cache($db, $content_id, $folder_id);
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'id' => $id, 'usage_id' => $usage_id, 'row' => $this->normalize_media_row($row), 'usage' => $usage, 'msg' => 'Zuordnung angelegt.'));
   }



   private function sort_media_json() {
      $payload = $this->request_json();
      $content_id = (int)($payload['content_id'] ?? 0);
      $folder_id = (int)($payload['folder_id'] ?? 0);
      $slot = $this->valid_media_slot($payload['slot'] ?? 'gallery');
      $ids = $payload['ids'] ?? array();
      if (($content_id <= 0 && $folder_id <= 0) || !is_array($ids)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Sortierung erhalten.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $sort = 10;
      foreach ($ids as $id) {
         $id = (int)$id;
         if ($id <= 0) continue;
         $where = dbxContentMediaUsageScope::with_language(
            "active = 1 AND slot = '" . str_replace("'", "''", $slot) . "'",
            dbxContentLng::current()
         );
         if ($content_id > 0) $where .= ' AND content_id = ' . $content_id;
         if ($folder_id > 0) $where .= ' AND folder_id = ' . $folder_id;
         $where .= ' AND (id = ' . $id . ' OR media_id = ' . $id . ')';
         $rows = $db->select($this->dd_media_usage, $where, '*', 'id', 'ASC', '', 1, 0, 0);
         if (!is_array($rows) || !isset($rows[0])) continue;
         $db->update($this->dd_media_usage, array('sorter' => sprintf('%04d', $sort)), (int)$rows[0]['id']);
         $sort += 10;
      }
      $this->flush_media_cache($db, $content_id, $folder_id);
      $this->cms_json_response(array('ok' => 1, 'success' => true));
   }



   private function filter_existing_media($db, array $rows) {
      $out = array();
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         if ($this->media_file_exists($row)) {
            $out[] = $row;
            continue;
         }
         $id = (int)($row['id'] ?? 0);
         if ($id > 0) {
            $db->update($this->dd_media, array('active' => 0), $id);
         }
      }
      return $out;
   }



   private function upload_json() {
      $content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
      $max_upload = $this->upload_max_bytes();
      if ($content_length > 0 && $max_upload > 0 && $content_length > $max_upload) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => 'Upload zu gross. Erlaubt sind maximal ' . round($max_upload / 1024 / 1024) . ' MB.'
         ));
      }
      if (!$this->verify_media_form('upload')) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => 'Ungueltiger oder abgelaufener Formular-Token.'
         ));
      }

      $file = $this->first_upload_file();
      if (empty($file) || !is_array($file)) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Keine Datei erhalten. Bitte Upload-Limit und Dateigroesse pruefen.'));
      }

      if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
         if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->cms_json_response(array('ok' => 0, 'msg' => 'Bitte zuerst eine Datei auswaehlen.'));
         }
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Upload fehlgeschlagen.'));
      }

      $name = basename((string)$file['name']);
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, array('jpg','jpeg','png','gif','webp','svg','pdf','mp4','webm','ogv','ogg','mov','m4v'), true)) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Dateityp nicht erlaubt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $content_id = (int)($_POST['content_id'] ?? 0);
      $folder_id = (int)($_POST['folder_id'] ?? 0);
      $slot = $this->valid_media_slot($_POST['slot'] ?? 'gallery');
      $probe_type = $this->media_type(array('file_name' => $name, 'mime' => (string)($file['type'] ?? '')));
      $media_folder = $this->clean_media_folder($_POST['media_folder'] ?? '', $probe_type);
      $media_rel_dir = $this->media_folder_rel_dir($media_folder, $probe_type);
      $dir = $this->cms_media_dir($media_folder);
      if (!is_dir($dir)) {
         mkdir($dir, 0777, true);
      }

      $dst_name = $this->unique_media_name($name);
      $dst = $dir . $dst_name;

      if (!move_uploaded_file($file['tmp_name'], $dst)) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Datei konnte nicht gespeichert werden.'));
      }

      $width = 0;
      $height = 0;
      $detected_mime = function_exists('mime_content_type') ? (string)@mime_content_type($dst) : '';
      $mime = $detected_mime !== '' ? $detected_mime : (string)($file['type'] ?? '');
      $img = @getimagesize($dst);
      if (is_array($img)) {
         $width = (int)($img[0] ?? 0);
         $height = (int)($img[1] ?? 0);
      }
      $max_image_size = max(800, min(6000, (int)($_POST['max_image_size'] ?? 2560)));
      if ($width > 0 && $height > 0 && $this->media_type(array('mime' => $mime, 'file_name' => $dst_name)) === 'image') {
         if ($this->resize_image_to_max($dst, $mime, $max_image_size)) {
            clearstatcache(true, $dst);
            $img = @getimagesize($dst);
            if (is_array($img)) {
               $width = (int)($img[0] ?? 0);
               $height = (int)($img[1] ?? 0);
            }
         }

         $webp = $this->convert_media_image_to_webp($dst, $dst_name, $mime);
         if (is_array($webp) && !empty($webp['file']) && !empty($webp['name'])) {
            $dst = (string)$webp['file'];
            $dst_name = (string)$webp['name'];
            $mime = (string)($webp['mime'] ?? 'image/webp');
            clearstatcache(true, $dst);
            $img = @getimagesize($dst);
            if (is_array($img)) {
               $width = (int)($img[0] ?? 0);
               $height = (int)($img[1] ?? 0);
            }
         }
      }

      $media_tpl = $this->clean_text($_POST['template'] ?? '', 80);
      $file_size = (int)@filesize($dst);
      $title = pathinfo($name, PATHINFO_FILENAME);
      $media_type = $this->media_type(array('mime' => $mime, 'file_name' => $dst_name));
      $media_folder = $this->clean_media_folder($media_folder, $media_type);
      if ($media_tpl === '') {
         $media_tpl = $media_type === 'video' ? ($slot === 'hero' ? 'video-hero' : 'video-gallery') : '';
      }

      $existing = $db->select(
         $this->dd_media,
         "active = 1 AND storage_type = 'local' AND media_folder = '" . str_replace("'", "''", $media_folder) . "' AND title = '" . str_replace("'", "''", $title) . "' AND size = " . $file_size,
         '*',
         'id',
         'DESC'
      );
      if (is_array($existing) && isset($existing[0]) && $this->media_file_exists($existing[0])) {
         @unlink($dst);
         $row = $this->normalize_media_row($existing[0]);
         $usage = array();
         if ($content_id > 0 || $folder_id > 0) {
            $usage_id = $this->create_media_usage($db, (int)($row['id'] ?? 0), $content_id, $folder_id, $slot, $media_tpl, '', '');
            if ($usage_id > 0) $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
         }
         $this->flush_media_cache($db, $content_id, $folder_id);
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'duplicate' => true,
            'row' => $row,
            'usage' => $usage,
            'files' => array($row['file_name'] ?? ''),
            'path' => '',
            'baseurl' => '',
         ));
      }

      $insert = array(
         'active' => 1,
         'content_id' => 0,
         'folder_id' => 0,
         'slot' => '',
         'usage' => '',
         'sorter' => '',
         'template' => $media_tpl,
         'title' => $title,
         'alt' => $title,
         'file_name' => $dst_name,
         'file_path' => $media_rel_dir . $dst_name,
         'mime' => $mime,
         'size' => $file_size,
         'width' => $width,
         'height' => $height,
         'media_type' => $media_type,
         'storage_type' => 'local',
         'media_folder' => $media_folder,
      );
      $thumb = $this->create_media_thumbnail($dst, $media_folder, $dst_name, $mime);
      if ($thumb) $insert = array_merge($insert, $thumb);
      $id = ($db->insert($this->dd_media, $insert) === 1) ? $db->get_insert_id() : 0;

      if ($id > 0) {
         $usage = array();
         if ($content_id > 0 || $folder_id > 0) {
            $usage_id = $this->create_media_usage($db, $id, $content_id, $folder_id, $slot, $media_tpl, '', '');
            if ($usage_id > 0) $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
         }
         $row = $this->normalize_media_row($db->select1($this->dd_media, $id));
         $baseurl = '';
         if (!empty($row['url'])) {
            $baseurl = preg_replace('~/[^/]*$~', '/', $row['url']);
         }
         $this->flush_media_cache($db, $content_id, $folder_id);
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'row' => $row,
            'usage' => $usage,
            'files' => array($row['file_name'] ?? $dst_name),
            'path' => '',
            'baseurl' => $baseurl,
         ));
      }

      $this->cms_json_response(array('ok' => 0, 'msg' => 'Mediendatensatz konnte nicht gespeichert werden.'));
   }



   private function remove_media_json() {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $usage_id = (int)dbx()->get_modul_var('usage_id', 0, 'int');
      $payload = array();
      if ($id <= 0 || $usage_id <= 0) {
         $payload = $this->request_json();
         if ($usage_id <= 0) $usage_id = (int)($payload['usage_id'] ?? 0);
         if ($id <= 0) $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      }
      if ($usage_id <= 0 && $id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Medien- oder Usage-ID erhalten.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $content_id = (int)($payload['content_id'] ?? dbx()->get_modul_var('content_id', 0, 'int'));
      $folder_id = (int)($payload['folder_id'] ?? dbx()->get_modul_var('folder_id', 0, 'int'));
      if ($usage_id > 0) {
         $usage_row = $db->select1($this->dd_media_usage, $usage_id);
         if (is_array($usage_row)) {
            if ($content_id <= 0) $content_id = (int)($usage_row['content_id'] ?? 0);
            if ($folder_id <= 0) $folder_id = (int)($usage_row['folder_id'] ?? 0);
            if ($id <= 0) $id = (int)($usage_row['media_id'] ?? 0);
         }
         $ok = $db->update($this->dd_media_usage, array('active' => 0), $usage_id);
      } else {
         $slot = trim((string)($payload['slot'] ?? dbx()->get_modul_var('slot', '', 'varchar')));
         if ($content_id <= 0 && $folder_id <= 0) {
            $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Seiten- oder Ordner-Zuordnung erhalten.'));
         }
         $where = 'media_id = ' . $id . ' AND active = 1';
         if ($content_id > 0) $where .= ' AND content_id = ' . $content_id;
         if ($folder_id > 0) $where .= ' AND folder_id = ' . $folder_id;
         if ($slot !== '') $where .= " AND slot = '" . str_replace("'", "''", $slot) . "'";
         $where = dbxContentMediaUsageScope::with_language($where, dbxContentLng::current());
         $ok = $db->update($this->dd_media_usage, array('active' => 0), $where, 0, 1, 1, 0);
      }
      $media = ($content_id > 0 || $folder_id > 0) ? $this->media_usage_rows_for_context($db, $content_id, $content_id > 0 ? 0 : $folder_id) : array();
      if ($ok >= 0) {
         $this->flush_media_cache($db, $content_id, $folder_id);
      }
      $this->cms_json_response(array('ok' => ($ok >= 0 ? 1 : 0), 'success' => ($ok >= 0), 'id' => $id, 'usage_id' => $usage_id, 'media' => $media, 'rows' => $media, 'msg' => 'Zuordnung entfernt.'));
   }



   private function delete_media_json() {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $force = (int)dbx()->get_modul_var('force', 0, 'int');
      if ($id <= 0) {
         $payload = $this->request_json();
         $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
         $force = (int)($payload['force'] ?? 0);
      }
      if ($id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Medien-ID erhalten.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $used = $db->select($this->dd_media_usage, $this->usage_where($id), '*', 'id', 'ASC', '', 1, 0, 0);
      if (is_array($used) && isset($used[0]) && !$force) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Medium wird noch verwendet.'));
      }
      $row = $db->select1($this->dd_media, $id);
      if (is_array($row)) {
         $this->flush_media_by_media_id($db, $id);
         if ($force) $db->update($this->dd_media_usage, array('active' => 0), 'media_id = ' . $id . ' AND active = 1', 0, 1, 1, 0);
         if (strtolower((string)($row['storage_type'] ?? 'local')) === 'local') {
            $file = $this->source_media_file($row);
            if ($file !== '') @unlink($file);
         } else {
            $file = $this->source_media_file($row);
            if ($file !== '' && preg_match('/\.json$/i', $file)) @unlink($file);
         }
         $thumb = $this->source_thumb_file($row);
         if ($thumb !== '') @unlink($thumb);
      }
      $ok = $db->update($this->dd_media, array('active' => 0), $id);
      $this->cms_json_response(array('ok' => ($ok >= 0 ? 1 : 0), 'success' => ($ok >= 0), 'id' => $id, 'msg' => 'Medium geloescht.'));
   }



   private function youtube_video_id($url) {
      $url = trim((string)$url);
      if ($url === '') return '';
      if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) return $url;
      $parts = @parse_url($url);
      if (!is_array($parts)) return '';
      $host = strtolower((string)($parts['host'] ?? ''));
      $path = trim((string)($parts['path'] ?? ''), '/');
      if (strpos($host, 'youtu.be') !== false && preg_match('~^([A-Za-z0-9_-]{11})~', $path, $m)) return $m[1];
      if (strpos($host, 'youtube.com') !== false) {
         parse_str((string)($parts['query'] ?? ''), $query);
         if (!empty($query['v']) && preg_match('~^[A-Za-z0-9_-]{11}$~', (string)$query['v'])) return (string)$query['v'];
         if (preg_match('~^(embed|shorts)/([A-Za-z0-9_-]{11})~', $path, $m)) return $m[2];
      }
      return '';
   }



   private function external_video_json() {
      if (!$this->verify_media_form('external')) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => 'Ungueltiger oder abgelaufener Formular-Token.'
         ));
      }
      $payload = $this->request_json();
      $provider = strtolower(trim((string)($payload['provider'] ?? 'youtube')));
      $external_url = trim((string)($payload['external_url'] ?? $payload['url'] ?? ''));
      if ($provider !== 'youtube') $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Provider nicht unterstuetzt.'));
      $video_id = $this->youtube_video_id($external_url);
      if ($video_id === '') $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'YouTube-URL nicht erkannt.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $content_id = (int)($payload['content_id'] ?? 0);
      $folder_id = (int)($payload['folder_id'] ?? 0);
      $slot = $this->valid_media_slot($payload['slot'] ?? 'gallery');
      $media_folder = $this->clean_media_folder($payload['media_folder'] ?? 'youtube', 'external_video');
      $title = $this->clean_text($payload['title'] ?? ('YouTube ' . $video_id), 160);
      $embed_url = 'https://www.youtube.com/embed/' . $video_id;
      $thumb_url = 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg';

      $dir = $this->cms_media_dir($media_folder);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      $rel = $this->media_folder_rel_dir($media_folder, 'external_video') . $video_id . '.json';
      $file = $this->file_from_media_rel($rel);
      if ($file !== '') {
         @file_put_contents($file, json_encode(array(
            'provider' => 'youtube',
            'provider_id' => $video_id,
            'external_url' => $external_url,
            'embed_url' => $embed_url,
            'title' => $title,
            'thumb_url' => $thumb_url,
         ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      }
      $existing = $db->select($this->dd_media, "active = 1 AND provider = 'youtube' AND provider_id = '" . str_replace("'", "''", $video_id) . "'", '*', 'id', 'DESC', '', 1, 0, 0);
      if (is_array($existing) && isset($existing[0])) {
         $row = $existing[0];
         $update = array(
            'media_folder' => $media_folder,
            'file_name' => $video_id . '.json',
            'file_path' => $rel,
            'mime' => 'application/json',
            'media_type' => 'external_video',
            'storage_type' => 'external',
            'external_url' => $external_url,
            'embed_url' => $embed_url,
            'title' => $title,
            'size' => $file !== '' && is_file($file) ? (int)@filesize($file) : 0,
         );
         $db->update($this->dd_media, $update, (int)($row['id'] ?? 0));
         $row = array_merge($row, $update);
      } else {
         $id = 0;
         if ($db->insert($this->dd_media, array(
            'active' => 1,
            'content_id' => 0,
            'folder_id' => 0,
            'slot' => '',
            'usage' => '',
            'sorter' => '',
            'template' => 'video-gallery',
            'title' => $title,
            'alt' => $title,
            'file_name' => $video_id . '.json',
            'file_path' => $rel,
            'mime' => 'application/json',
            'size' => $file !== '' && is_file($file) ? (int)@filesize($file) : 0,
            'width' => 0,
            'height' => 0,
            'media_type' => 'external_video',
            'storage_type' => 'external',
            'media_folder' => $media_folder,
            'provider' => 'youtube',
            'provider_id' => $video_id,
            'external_url' => $external_url,
            'embed_url' => $embed_url,
         )) === 1) {
            $id = $db->get_insert_id();
         }
         $row = $db->select1($this->dd_media, $id);
      }

      $usage = array();
      if (is_array($row) && ((int)($row['id'] ?? 0) > 0) && ($content_id > 0 || $folder_id > 0)) {
         $usage_id = $this->create_media_usage($db, (int)$row['id'], $content_id, $folder_id, $slot, 'video-gallery', '', '');
         if ($usage_id > 0) $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
      }
      $this->flush_media_cache($db, $content_id, $folder_id);
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'row' => $this->normalize_media_row($row), 'usage' => $usage, 'msg' => 'Externes Video gespeichert.'));
   }
}
