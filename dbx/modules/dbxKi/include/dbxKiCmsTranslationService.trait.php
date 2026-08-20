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

trait dbxKiCmsTranslationServiceTrait {

   private function sync_translate_folder(string $source_lng, string $target_lng, int $source_id, bool $update_existing, bool $skip_manual): array {
      $source_dd = dbxContentLng::dd_folder($source_lng);
      $target_dd = dbxContentLng::dd_folder($target_lng);
      $source = $this->db->select1($source_dd, $source_id);
      if (!is_array($source)) {
         throw new \RuntimeException('Quellordner nicht gefunden.');
      }

      $uid = dbxContentLngSync::ensure_record_uid($this->db, $source_dd, $source_id, 'f');
      if ($uid === '') {
         throw new \RuntimeException('Sprach-ID konnte nicht erzeugt werden.');
      }

      $target_id = dbxContentLngSync::resolve_id_by_uid($this->db, $target_dd, $uid, $target_lng);
      $target = $target_id > 0 ? $this->db->select1($target_dd, $target_id) : null;
      if (is_array($target) && !$update_existing) {
         return array('status' => 'skipped', 'reason' => 'exists', 'entity' => 'folder', 'source_id' => $source_id, 'target_lng' => $target_lng, 'target_id' => $target_id);
      }
      if (is_array($target) && $skip_manual && strtolower(trim((string)($target['lng_sync'] ?? ''))) === 'manual') {
         return array('status' => 'skipped', 'reason' => 'manual', 'entity' => 'folder', 'source_id' => $source_id, 'target_lng' => $target_lng, 'target_id' => $target_id);
      }

      $name = dbxContentTranslate::translate((string)($source['name'] ?? ''), $source_lng, $target_lng, 'folder_name');
      if ($name === '' && trim((string)($source['name'] ?? '')) !== '') {
         $name = (string)$source['name'];
      }
      if ($name === '') {
         $name = 'Ordner';
      }

      $data = $this->copy_folder_structure($source);
      $data['name'] = $this->clean($name, 120);
      $data['parent_id'] = $this->target_folder_id_from_source_parent($source_lng, $target_lng, (int)($source['parent_id'] ?? 0));
      $data['lng_uid'] = $uid;
      $data['lng_sync'] = 'auto';
      $data['lng_rev'] = is_array($target) ? max(1, (int)($target['lng_rev'] ?? 0) + 1) : 0;
      $data['lng_synced_rev'] = max(1, (int)($source['lng_rev'] ?? 1));

      if ($target_id > 0) {
         if ($this->db->update($target_dd, $data, $target_id) !== 1) {
            throw new \RuntimeException('Zielordner konnte nicht aktualisiert werden.');
         }
         $status = 'updated';
      } else {
         if ($this->db->insert($target_dd, $data) !== 1) {
            throw new \RuntimeException('Zielordner konnte nicht erstellt werden.');
         }
         $target_id = (int)$this->db->get_insert_id();
         $status = 'created';
      }

      $this->invalidate_folder($target_id);
      return array('status' => $status, 'entity' => 'folder', 'source_id' => $source_id, 'target_lng' => $target_lng, 'target_id' => $target_id, 'name' => $data['name']);
   }

   private function sync_translate_page(string $source_lng, string $target_lng, int $source_id, bool $update_existing, bool $skip_manual, bool $copy_media, bool $replace_media_usage): array {
      $source_dd = dbxContentLng::dd_content($source_lng);
      $target_dd = dbxContentLng::dd_content($target_lng);
      $source = $this->db->select1($source_dd, $source_id);
      if (!is_array($source)) {
         throw new \RuntimeException('Quellseite nicht gefunden.');
      }

      $uid = dbxContentLngSync::ensure_record_uid($this->db, $source_dd, $source_id, 'p');
      if ($uid === '') {
         throw new \RuntimeException('Sprach-ID konnte nicht erzeugt werden.');
      }

      $target_id = dbxContentLngSync::resolve_id_by_uid($this->db, $target_dd, $uid, $target_lng);
      $target = $target_id > 0 ? $this->db->select1($target_dd, $target_id) : null;
      if (is_array($target) && !$update_existing) {
         return array('status' => 'skipped', 'reason' => 'exists', 'entity' => 'page', 'source_id' => $source_id, 'target_lng' => $target_lng, 'target_id' => $target_id);
      }
      if (is_array($target) && $skip_manual && strtolower(trim((string)($target['lng_sync'] ?? ''))) === 'manual') {
         return array('status' => 'skipped', 'reason' => 'manual', 'entity' => 'page', 'source_id' => $source_id, 'target_lng' => $target_lng, 'target_id' => $target_id);
      }

      $title = dbxContentTranslate::translate((string)($source['title'] ?? ''), $source_lng, $target_lng, 'title');
      if ($title === '' && trim((string)($source['title'] ?? '')) !== '') {
         $title = (string)$source['title'];
      }
      if ($title === '') {
         throw new \RuntimeException('Übersetzter Titel ist leer.');
      }

      $target_folder = $this->target_folder_id_from_source_parent($source_lng, $target_lng, (int)($source['folder'] ?? 0));
      if ((int)($source['folder'] ?? 0) > 0 && $target_folder <= 0) {
         throw new \RuntimeException('Zielordner konnte nicht aufgelöst werden.');
      }

      $data = $this->copy_page_structure($source);
      $data['folder'] = $target_folder;
      $data['title'] = $this->clean($title, 254);
      $data['description'] = $this->clean(dbxContentTranslate::translate((string)($source['description'] ?? ''), $source_lng, $target_lng, 'description'), 254);
      $data['keywords'] = $this->clean(dbxContentTranslate::translate((string)($source['keywords'] ?? ''), $source_lng, $target_lng, 'keywords'), 254);
      $data['content'] = $this->normalize_content_inline_media_urls(dbxContentTranslate::translate((string)($source['content'] ?? ''), $source_lng, $target_lng, 'content'));
      foreach (array('seo_title', 'img_alt_1', 'img_alt_2', 'img_alt_3', 'img_des_1', 'img_des_2', 'img_des_3') as $field) {
         if (array_key_exists($field, $source)) {
            $max = $field === 'seo_title' || strpos($field, 'img_alt_') === 0 ? 254 : 0;
            $data[$field] = $this->clean(dbxContentTranslate::translate((string)($source[$field] ?? ''), $source_lng, $target_lng, $field), $max);
         }
      }
      $data['permalink'] = dbxContent_permalink::build($this->db, dbxContentLng::dd_folder($target_lng), $target_folder, $data['title']);
      $data['lng_uid'] = $uid;
      $data['lng_sync'] = 'auto';
      $data['lng_rev'] = is_array($target) ? max(1, (int)($target['lng_rev'] ?? 0) + 1) : 0;
      $data['lng_synced_rev'] = max(1, (int)($source['lng_rev'] ?? 1));

      if ($target_id > 0) {
         if ($this->db->update($target_dd, $data, $target_id) !== 1) {
            throw new \RuntimeException('Zielseite konnte nicht aktualisiert werden.');
         }
         $status = 'updated';
      } else {
         if ($this->db->insert($target_dd, $data) !== 1) {
            throw new \RuntimeException('Zielseite konnte nicht erstellt werden.');
         }
         $target_id = (int)$this->db->get_insert_id();
         $status = 'created';
      }

      $media_copied = 0;
      if ($copy_media) {
         if ($replace_media_usage) {
            $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::with_language('content_id = ' . $target_id . ' AND active = 1', $target_lng), 0, 1, 1, 1);
            $media_copied = $this->copy_media_usage($source_id, $target_id, $target_folder, $source_lng, $target_lng);
         } else {
            $media_copied = $this->copy_missing_media_usage($source_id, $target_id, $target_folder, $source_lng, $target_lng);
         }
      }

      $row = $this->db->select1($target_dd, $target_id);
      $this->invalidate_page($target_id, $target_lng, $row);
      return array('status' => $status, 'entity' => 'page', 'source_id' => $source_id, 'target_lng' => $target_lng, 'target_id' => $target_id, 'title' => $data['title'], 'media_copied' => $media_copied);
   }

   private function target_languages(array $params, string $source_lng): array {
      $raw = $params['target_lngs'] ?? $params['target_lng'] ?? array();
      if (is_string($raw)) {
         $raw = array_values(array_filter(array_map('trim', explode(',', $raw))));
      } elseif (!is_array($raw)) {
         $raw = array();
      }
      if (!count($raw)) {
         $raw = dbxContentLngSync::accessible_lngs();
      }

      $out = array();
      foreach ($raw as $lng) {
         $lng = $this->language($lng);
         if ($lng === $source_lng || in_array($lng, $out, true)) {
            continue;
         }
         $out[] = $lng;
      }
      return $out;
   }

   private function collect_folder_ids_for_lng(string $lng, int $root_folder_id = 0): array {
      $dd = dbxContentLng::dd_folder($lng);
      if ($root_folder_id <= 0) {
         $rows = $this->db->select($dd, '', 'id', 'parent_id,sorter,id', 'ASC', '', 0, 0, 0);
         return $this->ids_from_rows($rows);
      }

      $out = array();
      $seen = array();
      $queue = array($root_folder_id);
      while (count($queue)) {
         $id = (int)array_shift($queue);
         if ($id <= 0 || isset($seen[$id])) {
            continue;
         }
         $seen[$id] = 1;
         $out[] = $id;
         $rows = $this->db->select($dd, 'parent_id = ' . $id, 'id', 'sorter,id', 'ASC', '', 0, 0, 0);
         foreach ($this->ids_from_rows($rows) as $child_id) {
            if (!isset($seen[$child_id])) {
               $queue[] = $child_id;
            }
         }
      }
      return $out;
   }

   private function collect_page_ids_for_lng(string $lng, int $root_folder_id, array $folder_ids): array {
      $dd = dbxContentLng::dd_content($lng);
      if ($root_folder_id <= 0) {
         $rows = $this->db->select($dd, '', 'id', 'folder,sorter,id', 'ASC', '', 0, 0, 0);
         return $this->ids_from_rows($rows);
      }

      $folder_ids = array_values(array_filter(array_map('intval', $folder_ids), static function($id) {
         return $id > 0;
      }));
      if (!count($folder_ids)) {
         return array();
      }
      $rows = $this->db->select($dd, 'folder IN (' . implode(',', $folder_ids) . ')', 'id', 'folder,sorter,id', 'ASC', '', 0, 0, 0);
      return $this->ids_from_rows($rows);
   }

   private function ids_from_rows($rows): array {
      $out = array();
      foreach (is_array($rows) ? $rows : array() as $row) {
         if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            $out[] = (int)$row['id'];
         }
      }
      return $out;
   }

   private function target_folder_id_from_source_parent(string $source_lng, string $target_lng, int $source_folder_id): int {
      $source_folder_id = (int)$source_folder_id;
      if ($source_folder_id <= 0) {
         return 0;
      }
      $source_dd = dbxContentLng::dd_folder($source_lng);
      $source = $this->db->select1($source_dd, $source_folder_id);
      if (!is_array($source)) {
         return 0;
      }

      $uid = dbxContentLngSync::ensure_record_uid($this->db, $source_dd, $source_folder_id, 'f');
      if ($uid === '') {
         return 0;
      }
      $target_dd = dbxContentLng::dd_folder($target_lng);
      $target_id = dbxContentLngSync::resolve_id_by_uid($this->db, $target_dd, $uid, $target_lng);
      if ($target_id > 0) {
         return $target_id;
      }

      try {
         $created = $this->sync_translate_folder($source_lng, $target_lng, $source_folder_id, true, false);
         return (int)($created['target_id'] ?? 0);
      } catch (\Throwable $e) {
         return 0;
      }
   }

   private function copy_page_structure(array $source): array {
      $skip = array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'title', 'permalink', 'description', 'keywords', 'content', 'lng_uid', 'lng_sync', 'lng_rev', 'lng_synced_rev');
      $data = array();
      foreach ($source as $key => $value) {
         if (!in_array($key, $skip, true)) {
            $data[$key] = $value;
         }
      }
      return $data;
   }

   private function copy_folder_structure(array $source): array {
      $skip = array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'name', 'parent_id', 'lng_uid', 'lng_sync', 'lng_rev', 'lng_synced_rev');
      $data = array();
      foreach ($source as $key => $value) {
         if (!in_array($key, $skip, true)) {
            $data[$key] = $value;
         }
      }
      return $data;
   }

   private function copy_missing_media_usage(int $source_id, int $target_id, int $target_folder, string $source_lng, string $target_lng): int {
      $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::with_language("content_id = " . $source_id . " AND active = 1 AND slot IN ('hero','gallery','inline','header','teaser','footer')", $source_lng), '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      $count = 0;
      foreach (is_array($rows) ? $rows : array() as $row) {
         if (!is_array($row)) {
            continue;
         }
         $media_id = (int)($row['media_id'] ?? 0);
         $slot = str_replace("'", "''", (string)($row['slot'] ?? ''));
         if ($media_id <= 0 || $slot === '') {
            continue;
         }
         if ($slot === 'hero' && (int)$this->db->count('dbxMediaUsage', dbxContentMediaUsageScope::with_language('content_id = ' . $target_id . " AND slot = 'hero' AND active = 1", $target_lng)) > 0) {
            continue;
         }
         $exists = (int)$this->db->count('dbxMediaUsage', dbxContentMediaUsageScope::with_language('content_id = ' . $target_id . ' AND media_id = ' . $media_id . " AND slot = '" . $slot . "' AND active = 1", $target_lng));
         if ($exists > 0) {
            continue;
         }
         $data = $this->whitelist($row, array('media_id', 'slot', 'sorter', 'template', 'caption', 'settings'));
         $data['active'] = 1;
         $data['content_id'] = $target_id;
         $data['folder_id'] = $target_folder;
         $data['content_lng'] = dbxContentMediaUsageScope::language($target_lng);
         if ($this->db->insert('dbxMediaUsage', $data) === 1) {
            $count++;
         }
      }
      return $count;
   }

   private function copy_media_usage(int $source_id, int $target_id, int $target_folder, string $source_lng, string $target_lng): int {
      $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::with_language("content_id = " . $source_id . " AND active = 1 AND slot IN ('hero','gallery','inline','header','teaser','footer')", $source_lng), '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      $count = 0;
      foreach (is_array($rows) ? $rows : array() as $row) {
         $data = $this->whitelist($row, array('media_id', 'slot', 'sorter', 'template', 'caption', 'settings'));
         $data['active'] = 1;
         $data['content_id'] = $target_id;
         $data['folder_id'] = $target_folder;
         $data['content_lng'] = dbxContentMediaUsageScope::language($target_lng);
         if ($this->db->insert('dbxMediaUsage', $data) === 1) $count++;
      }
      return $count;
   }
}
