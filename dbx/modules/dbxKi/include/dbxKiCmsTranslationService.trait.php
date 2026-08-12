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

   private function sync_translate_folder(string $sourceLng, string $targetLng, int $sourceId, bool $updateExisting, bool $skipManual): array {
      $sourceDd = dbxContentLng::ddFolder($sourceLng);
      $targetDd = dbxContentLng::ddFolder($targetLng);
      $source = $this->db->select1($sourceDd, $sourceId);
      if (!is_array($source)) {
         throw new \RuntimeException('Quellordner nicht gefunden.');
      }

      $uid = dbxContentLngSync::ensureRecordUid($this->db, $sourceDd, $sourceId, 'f');
      if ($uid === '') {
         throw new \RuntimeException('Sprach-ID konnte nicht erzeugt werden.');
      }

      $targetId = dbxContentLngSync::resolveIdByUid($this->db, $targetDd, $uid, $targetLng);
      $target = $targetId > 0 ? $this->db->select1($targetDd, $targetId) : null;
      if (is_array($target) && !$updateExisting) {
         return array('status' => 'skipped', 'reason' => 'exists', 'entity' => 'folder', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId);
      }
      if (is_array($target) && $skipManual && strtolower(trim((string)($target['lng_sync'] ?? ''))) === 'manual') {
         return array('status' => 'skipped', 'reason' => 'manual', 'entity' => 'folder', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId);
      }

      $name = dbxContentTranslate::translate((string)($source['name'] ?? ''), $sourceLng, $targetLng, 'folder_name');
      if ($name === '' && trim((string)($source['name'] ?? '')) !== '') {
         $name = (string)$source['name'];
      }
      if ($name === '') {
         $name = 'Ordner';
      }

      $data = $this->copy_folder_structure($source);
      $data['name'] = $this->clean($name, 120);
      $data['parent_id'] = $this->target_folder_id_from_source_parent($sourceLng, $targetLng, (int)($source['parent_id'] ?? 0));
      $data['lng_uid'] = $uid;
      $data['lng_sync'] = 'auto';
      $data['lng_rev'] = is_array($target) ? max(1, (int)($target['lng_rev'] ?? 0) + 1) : 0;
      $data['lng_synced_rev'] = max(1, (int)($source['lng_rev'] ?? 1));

      if ($targetId > 0) {
         if ($this->db->update($targetDd, $data, $targetId) !== 1) {
            throw new \RuntimeException('Zielordner konnte nicht aktualisiert werden.');
         }
         $status = 'updated';
      } else {
         if ($this->db->insert($targetDd, $data) !== 1) {
            throw new \RuntimeException('Zielordner konnte nicht erstellt werden.');
         }
         $targetId = (int)$this->db->get_insert_id();
         $status = 'created';
      }

      $this->invalidate_folder($targetId);
      return array('status' => $status, 'entity' => 'folder', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId, 'name' => $data['name']);
   }

   private function sync_translate_page(string $sourceLng, string $targetLng, int $sourceId, bool $updateExisting, bool $skipManual, bool $copyMedia, bool $replaceMediaUsage): array {
      $sourceDd = dbxContentLng::ddContent($sourceLng);
      $targetDd = dbxContentLng::ddContent($targetLng);
      $source = $this->db->select1($sourceDd, $sourceId);
      if (!is_array($source)) {
         throw new \RuntimeException('Quellseite nicht gefunden.');
      }

      $uid = dbxContentLngSync::ensureRecordUid($this->db, $sourceDd, $sourceId, 'p');
      if ($uid === '') {
         throw new \RuntimeException('Sprach-ID konnte nicht erzeugt werden.');
      }

      $targetId = dbxContentLngSync::resolveIdByUid($this->db, $targetDd, $uid, $targetLng);
      $target = $targetId > 0 ? $this->db->select1($targetDd, $targetId) : null;
      if (is_array($target) && !$updateExisting) {
         return array('status' => 'skipped', 'reason' => 'exists', 'entity' => 'page', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId);
      }
      if (is_array($target) && $skipManual && strtolower(trim((string)($target['lng_sync'] ?? ''))) === 'manual') {
         return array('status' => 'skipped', 'reason' => 'manual', 'entity' => 'page', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId);
      }

      $title = dbxContentTranslate::translate((string)($source['title'] ?? ''), $sourceLng, $targetLng, 'title');
      if ($title === '' && trim((string)($source['title'] ?? '')) !== '') {
         $title = (string)$source['title'];
      }
      if ($title === '') {
         throw new \RuntimeException('Übersetzter Titel ist leer.');
      }

      $targetFolder = $this->target_folder_id_from_source_parent($sourceLng, $targetLng, (int)($source['folder'] ?? 0));
      if ((int)($source['folder'] ?? 0) > 0 && $targetFolder <= 0) {
         throw new \RuntimeException('Zielordner konnte nicht aufgelöst werden.');
      }

      $data = $this->copy_page_structure($source);
      $data['folder'] = $targetFolder;
      $data['title'] = $this->clean($title, 254);
      $data['description'] = $this->clean(dbxContentTranslate::translate((string)($source['description'] ?? ''), $sourceLng, $targetLng, 'description'), 254);
      $data['keywords'] = $this->clean(dbxContentTranslate::translate((string)($source['keywords'] ?? ''), $sourceLng, $targetLng, 'keywords'), 254);
      $data['content'] = $this->normalize_content_inline_media_urls(dbxContentTranslate::translate((string)($source['content'] ?? ''), $sourceLng, $targetLng, 'content'));
      foreach (array('seo_title', 'img_alt_1', 'img_alt_2', 'img_alt_3', 'img_des_1', 'img_des_2', 'img_des_3') as $field) {
         if (array_key_exists($field, $source)) {
            $max = $field === 'seo_title' || strpos($field, 'img_alt_') === 0 ? 254 : 0;
            $data[$field] = $this->clean(dbxContentTranslate::translate((string)($source[$field] ?? ''), $sourceLng, $targetLng, $field), $max);
         }
      }
      $data['permalink'] = dbxContent_permalink::build($this->db, dbxContentLng::ddFolder($targetLng), $targetFolder, $data['title']);
      $data['lng_uid'] = $uid;
      $data['lng_sync'] = 'auto';
      $data['lng_rev'] = is_array($target) ? max(1, (int)($target['lng_rev'] ?? 0) + 1) : 0;
      $data['lng_synced_rev'] = max(1, (int)($source['lng_rev'] ?? 1));

      if ($targetId > 0) {
         if ($this->db->update($targetDd, $data, $targetId) !== 1) {
            throw new \RuntimeException('Zielseite konnte nicht aktualisiert werden.');
         }
         $status = 'updated';
      } else {
         if ($this->db->insert($targetDd, $data) !== 1) {
            throw new \RuntimeException('Zielseite konnte nicht erstellt werden.');
         }
         $targetId = (int)$this->db->get_insert_id();
         $status = 'created';
      }

      $mediaCopied = 0;
      if ($copyMedia) {
         if ($replaceMediaUsage) {
            $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . ' AND active = 1', $targetLng), 0, 1, 1, 1);
            $mediaCopied = $this->copy_media_usage($sourceId, $targetId, $targetFolder, $sourceLng, $targetLng);
         } else {
            $mediaCopied = $this->copy_missing_media_usage($sourceId, $targetId, $targetFolder, $sourceLng, $targetLng);
         }
      }

      $row = $this->db->select1($targetDd, $targetId);
      $this->invalidate_page($targetId, $targetLng, $row);
      return array('status' => $status, 'entity' => 'page', 'source_id' => $sourceId, 'target_lng' => $targetLng, 'target_id' => $targetId, 'title' => $data['title'], 'media_copied' => $mediaCopied);
   }

   private function target_languages(array $params, string $sourceLng): array {
      $raw = $params['target_lngs'] ?? $params['target_lng'] ?? array();
      if (is_string($raw)) {
         $raw = array_values(array_filter(array_map('trim', explode(',', $raw))));
      } elseif (!is_array($raw)) {
         $raw = array();
      }
      if (!count($raw)) {
         $raw = dbxContentLngSync::accessibleLngs();
      }

      $out = array();
      foreach ($raw as $lng) {
         $lng = $this->language($lng);
         if ($lng === $sourceLng || in_array($lng, $out, true)) {
            continue;
         }
         $out[] = $lng;
      }
      return $out;
   }

   private function collect_folder_ids_for_lng(string $lng, int $rootFolderId = 0): array {
      $dd = dbxContentLng::ddFolder($lng);
      if ($rootFolderId <= 0) {
         $rows = $this->db->select($dd, '', 'id', 'parent_id,sorter,id', 'ASC', '', 0, 0, 0);
         return $this->ids_from_rows($rows);
      }

      $out = array();
      $seen = array();
      $queue = array($rootFolderId);
      while (count($queue)) {
         $id = (int)array_shift($queue);
         if ($id <= 0 || isset($seen[$id])) {
            continue;
         }
         $seen[$id] = 1;
         $out[] = $id;
         $rows = $this->db->select($dd, 'parent_id = ' . $id, 'id', 'sorter,id', 'ASC', '', 0, 0, 0);
         foreach ($this->ids_from_rows($rows) as $childId) {
            if (!isset($seen[$childId])) {
               $queue[] = $childId;
            }
         }
      }
      return $out;
   }

   private function collect_page_ids_for_lng(string $lng, int $rootFolderId, array $folderIds): array {
      $dd = dbxContentLng::ddContent($lng);
      if ($rootFolderId <= 0) {
         $rows = $this->db->select($dd, '', 'id', 'folder,sorter,id', 'ASC', '', 0, 0, 0);
         return $this->ids_from_rows($rows);
      }

      $folderIds = array_values(array_filter(array_map('intval', $folderIds), static function($id) {
         return $id > 0;
      }));
      if (!count($folderIds)) {
         return array();
      }
      $rows = $this->db->select($dd, 'folder IN (' . implode(',', $folderIds) . ')', 'id', 'folder,sorter,id', 'ASC', '', 0, 0, 0);
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

   private function target_folder_id_from_source_parent(string $sourceLng, string $targetLng, int $sourceFolderId): int {
      $sourceFolderId = (int)$sourceFolderId;
      if ($sourceFolderId <= 0) {
         return 0;
      }
      $sourceDd = dbxContentLng::ddFolder($sourceLng);
      $source = $this->db->select1($sourceDd, $sourceFolderId);
      if (!is_array($source)) {
         return 0;
      }

      $uid = dbxContentLngSync::ensureRecordUid($this->db, $sourceDd, $sourceFolderId, 'f');
      if ($uid === '') {
         return 0;
      }
      $targetDd = dbxContentLng::ddFolder($targetLng);
      $targetId = dbxContentLngSync::resolveIdByUid($this->db, $targetDd, $uid, $targetLng);
      if ($targetId > 0) {
         return $targetId;
      }

      try {
         $created = $this->sync_translate_folder($sourceLng, $targetLng, $sourceFolderId, true, false);
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

   private function copy_missing_media_usage(int $sourceId, int $targetId, int $targetFolder, string $sourceLng, string $targetLng): int {
      $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage("content_id = " . $sourceId . " AND active = 1 AND slot IN ('hero','gallery','inline','header','teaser','footer')", $sourceLng), '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      $count = 0;
      foreach (is_array($rows) ? $rows : array() as $row) {
         if (!is_array($row)) {
            continue;
         }
         $mediaId = (int)($row['media_id'] ?? 0);
         $slot = str_replace("'", "''", (string)($row['slot'] ?? ''));
         if ($mediaId <= 0 || $slot === '') {
            continue;
         }
         if ($slot === 'hero' && (int)$this->db->count('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . " AND slot = 'hero' AND active = 1", $targetLng)) > 0) {
            continue;
         }
         $exists = (int)$this->db->count('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . ' AND media_id = ' . $mediaId . " AND slot = '" . $slot . "' AND active = 1", $targetLng));
         if ($exists > 0) {
            continue;
         }
         $data = $this->whitelist($row, array('media_id', 'slot', 'sorter', 'template', 'caption', 'settings'));
         $data['active'] = 1;
         $data['content_id'] = $targetId;
         $data['folder_id'] = $targetFolder;
         $data['content_lng'] = dbxContentMediaUsageScope::language($targetLng);
         if ($this->db->insert('dbxMediaUsage', $data) === 1) {
            $count++;
         }
      }
      return $count;
   }

   private function copy_media_usage(int $sourceId, int $targetId, int $targetFolder, string $sourceLng, string $targetLng): int {
      $rows = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage("content_id = " . $sourceId . " AND active = 1 AND slot IN ('hero','gallery','inline','header','teaser','footer')", $sourceLng), '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      $count = 0;
      foreach (is_array($rows) ? $rows : array() as $row) {
         $data = $this->whitelist($row, array('media_id', 'slot', 'sorter', 'template', 'caption', 'settings'));
         $data['active'] = 1;
         $data['content_id'] = $targetId;
         $data['folder_id'] = $targetFolder;
         $data['content_lng'] = dbxContentMediaUsageScope::language($targetLng);
         if ($this->db->insert('dbxMediaUsage', $data) === 1) $count++;
      }
      return $count;
   }
}
