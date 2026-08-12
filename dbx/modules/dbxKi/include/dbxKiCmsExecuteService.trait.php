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

trait dbxKiCmsExecuteServiceTrait {

   private function execute_folder_create(array $plan): array {
      $dd = dbxContentLng::ddFolder($plan['lng']);
      $data = $plan['data'];
      $data['sorter'] = $this->next_sorter($dd, 'parent_id', (int)$data['parent_id']);
      $data += $this->lng_fields('f', $plan['lng']);
      if ($this->db->insert($dd, $data) !== 1) throw new \RuntimeException('Ordner konnte nicht erstellt werden.');
      $id = $this->db->get_insert_id();
      $this->invalidate_folder($id);
      return array('id' => $id, 'row' => $this->db->select1($dd, $id));
   }

   private function execute_folder_update(array $plan): array {
      $dd = dbxContentLng::ddFolder($plan['lng']);
      $data = $plan['changes'];
      $data = $this->advance_revision($dd, $plan['id'], $data, $plan['lng']);
      if ($this->db->update($dd, $data, $plan['id']) !== 1) throw new \RuntimeException('Ordner konnte nicht aktualisiert werden.');
      $this->invalidate_folder($plan['id']);
      return array('id' => $plan['id'], 'row' => $this->db->select1($dd, $plan['id']));
   }

   private function execute_folder_delete(array $plan): array {
      $dd = dbxContentLng::ddFolder($plan['lng']);
      if ($this->db->delete($dd, $plan['id']) !== 1) throw new \RuntimeException('Ordner konnte nicht gelöscht werden.');
      $this->invalidate_folder($plan['id']);
      return array('deleted' => true, 'id' => $plan['id'], 'lng' => $plan['lng']);
   }

   private function execute_page_create(array $plan): array {
      $dd = dbxContentLng::ddContent($plan['lng']);
      $data = $plan['data'];
      if (trim((string)($data['sorter'] ?? '')) === '') {
         $data['sorter'] = $this->next_sorter($dd, 'folder', (int)$data['folder']);
      }
      $data += $this->lng_fields('p', $plan['lng']);
      if ($this->db->insert($dd, $data) !== 1) throw new \RuntimeException('Seite konnte nicht erstellt werden.');
      $id = $this->db->get_insert_id();
      $this->invalidate_page($id, $plan['lng'], $data);
      return array('id' => $id, 'row' => $this->db->select1($dd, $id));
   }

   private function execute_page_update(array $plan): array {
      $dd = dbxContentLng::ddContent($plan['lng']);
      $data = $this->advance_revision($dd, $plan['id'], $plan['changes'], $plan['lng']);
      if ($this->db->update($dd, $data, $plan['id']) !== 1) throw new \RuntimeException('Seite konnte nicht aktualisiert werden.');
      $mediaId = (int)($plan['package_media_id_applied'] ?? 0);
      if ($mediaId > 0) {
         $this->ensure_inline_media_usage((int)$plan['id'], $mediaId, (string)$plan['lng']);
      }
      $row = $this->db->select1($dd, $plan['id']);
      $this->invalidate_page($plan['id'], $plan['lng'], $row);
      $result = array('id' => $plan['id'], 'row' => $row);
      if ($mediaId > 0) {
         $result['package_media_id'] = $mediaId;
         $result = array_merge($result, $this->media_inline_payload($mediaId));
      }
      return $result;
   }

   private function execute_page_hero_replace_image(array $plan): array {
      $target = (string)$plan['target_file'];
      $this->render_image_variant_to_file($plan['source'], $target, (int)$plan['width'], (int)$plan['height'], (string)$plan['fit'], (string)$plan['mime'], (int)$plan['quality']);

      $mediaId = (int)($plan['media']['id'] ?? 0);
      $data = array(
         'size' => (int)@filesize($target),
         'width' => (int)$plan['width'],
         'height' => (int)$plan['height'],
         'mime' => (string)$plan['mime'],
      );
      if ($mediaId <= 0) throw new \RuntimeException('Hero-Medium konnte nicht aktualisiert werden.');
      $this->db->update('dbxMedia', $data, $mediaId);
      $this->invalidate_media_references($mediaId);
      $this->invalidate_page((int)$plan['id'], (string)$plan['lng'], $plan['page']);
      return array(
         'id' => (int)$plan['id'],
         'media_id' => $mediaId,
         'file' => str_replace('\\', '/', $target),
         'replaced' => true,
      );
   }

   private function execute_page_hero_create_image(array $plan): array {
      $media = $this->execute_media_create_image_variant($plan['media_plan']);
      $mediaId = (int)($media['id'] ?? 0);
      if ($mediaId <= 0) throw new \RuntimeException('Hero-Medium konnte nicht erstellt werden.');

      $data = array(
         'active' => 1,
         'media_id' => $mediaId,
         'content_id' => (int)$plan['id'],
         'folder_id' => 0,
         'content_lng' => dbxContentMediaUsageScope::language((string)$plan['lng']),
         'slot' => 'hero',
         'template' => '',
         'caption' => '',
         'settings' => '',
      );
      $where = dbxContentMediaUsageScope::withLanguage('content_id = ' . (int)$plan['id'] . " AND slot = 'hero' AND active = 1", (string)$plan['lng']);
      $this->db->update('dbxMediaUsage', array('active' => 0), $where, 0, 1, 1, 1);
      $data['sorter'] = $this->next_usage_sorter((int)$plan['id'], 0, 'hero', (string)$plan['lng']);
      if ($this->db->insert('dbxMediaUsage', $data) !== 1) {
         throw new \RuntimeException('Hero-Medienzuordnung konnte nicht erstellt werden.');
      }
      $usageId = $this->db->get_insert_id();
      $this->sync_hero_setting((string)$plan['lng'], $data);
      $this->invalidate_usage($data);
      $row = $this->db->select1(dbxContentLng::ddContent((string)$plan['lng']), (int)$plan['id']);
      return array(
         'id' => (int)$plan['id'],
         'media_id' => $mediaId,
         'usage_id' => $usageId,
         'row' => $row,
         'media' => $media['row'] ?? array(),
      );
   }

   private function execute_page_delete(array $plan): array {
      $dd = dbxContentLng::ddContent($plan['lng']);
      if ($this->db->delete($dd, $plan['id']) !== 1) throw new \RuntimeException('Seite konnte nicht gelöscht werden.');
      $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::withLanguage('content_id = ' . (int)$plan['id'] . ' AND active = 1', (string)$plan['lng']), 0, 1, 1, 1);
      dbxContentPageCache::invalidateContent($plan['id']);
      dbxContentPageCache::invalidateAllMenus();
      dbxContentPermalinkIndex::removeByCid($plan['id'], $plan['lng']);
      return array('deleted' => true, 'id' => $plan['id'], 'lng' => $plan['lng']);
   }

   private function execute_media_create(array $params, array $plan): array {
      $bytes = $this->decode_base64((string)$params['data_base64']);
      if (!hash_equals((string)$plan['sha256'], hash('sha256', $bytes))) {
         throw new \RuntimeException('Der Medieninhalt stimmt nicht mit dem geprüften Plan überein.');
      }
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/media/' . $plan['media_folder'];
      $dir = dbx()->os_path($dir);
      if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) throw new \RuntimeException('Medienordner konnte nicht erstellt werden.');
      $name = $this->unique_name($dir, $plan['file_name']);
      $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
      if (file_put_contents($file, $bytes) === false) throw new \RuntimeException('Mediendatei konnte nicht geschrieben werden.');
      $relative = 'media/' . trim(str_replace('\\', '/', $plan['media_folder']), '/') . '/' . $name;
      $width = 0;
      $height = 0;
      $size = @getimagesize($file);
      if (is_array($size)) {
         $width = (int)($size[0] ?? 0);
         $height = (int)($size[1] ?? 0);
      }
      $data = array_merge($plan['metadata'], array(
         'active' => 1,
         'file_name' => $name,
         'file_path' => $relative,
         'mime' => $plan['mime'],
         'size' => strlen($bytes),
         'width' => $width,
         'height' => $height,
         'media_type' => $plan['media_type'],
         'storage_type' => 'local',
         'media_folder' => $plan['media_folder'],
      ));
      if ($this->db->insert('dbxMedia', $data) !== 1) {
         @unlink($file);
         throw new \RuntimeException('Medium konnte nicht registriert werden.');
      }
      $id = $this->db->get_insert_id();
      return array_merge(array('id' => $id, 'row' => $this->db->select1('dbxMedia', $id)), $this->media_inline_payload($id));
   }

   private function execute_media_create_image_variant(array $plan): array {
      $source = (string)($plan['source_file'] ?? '');
      if (!is_file($source) || !is_readable($source)) {
         throw new \RuntimeException('Quellbild ist nicht lesbar.');
      }
      if (!hash_equals((string)($plan['source_sha256'] ?? ''), hash_file('sha256', $source))) {
         throw new \RuntimeException('Das Quellbild stimmt nicht mehr mit dem geprüften Plan überein.');
      }

      $src = $this->gd_load_image($source, (string)$plan['source_mime']);
      $width = max(1, (int)$plan['width']);
      $height = max(1, (int)$plan['height']);
      $dst = imagecreatetruecolor($width, $height);
      imagealphablending($dst, false);
      imagesavealpha($dst, true);
      $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
      imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);

      $sourceWidth = imagesx($src);
      $sourceHeight = imagesy($src);
      $sourceX = 0;
      $sourceY = 0;
      $crop = is_array($plan['crop'] ?? null) ? $plan['crop'] : array();
      if ($crop) {
         $sourceX = max(0, min((int)($crop['x'] ?? 0), $sourceWidth - 1));
         $sourceY = max(0, min((int)($crop['y'] ?? 0), $sourceHeight - 1));
         $sourceWidth = max(1, min((int)($crop['width'] ?? $sourceWidth), imagesx($src) - $sourceX));
         $sourceHeight = max(1, min((int)($crop['height'] ?? $sourceHeight), imagesy($src) - $sourceY));
      }
      $fit = (string)($plan['fit'] ?? 'cover');
      if ($fit === 'contain') {
         $scale = min($width / $sourceWidth, $height / $sourceHeight);
         $copyWidth = max(1, (int)round($sourceWidth * $scale));
         $copyHeight = max(1, (int)round($sourceHeight * $scale));
         $dstX = (int)floor(($width - $copyWidth) / 2);
         $dstY = (int)floor(($height - $copyHeight) / 2);
         imagecopyresampled($dst, $src, $dstX, $dstY, $sourceX, $sourceY, $copyWidth, $copyHeight, $sourceWidth, $sourceHeight);
      } else {
         $sourceRatio = $sourceWidth / $sourceHeight;
         $targetRatio = $width / $height;
         if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int)round($sourceHeight * $targetRatio);
            $srcX = $sourceX + (int)floor(($sourceWidth - $cropWidth) / 2);
            $srcY = $sourceY;
         } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int)round($sourceWidth / $targetRatio);
            $srcX = $sourceX;
            $srcY = $sourceY + (int)floor(($sourceHeight - $cropHeight) / 2);
         }
         imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $width, $height, $cropWidth, $cropHeight);
      }
      imagedestroy($src);

      $this->gd_apply_tint($dst, (string)($plan['tint'] ?? ''), (float)($plan['tint_strength'] ?? 0));

      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/media/' . $plan['media_folder'];
      $dir = dbx()->os_path($dir);
      if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) throw new \RuntimeException('Medienordner konnte nicht erstellt werden.');
      $name = $this->unique_name($dir, $plan['file_name']);
      $file = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
      $this->gd_save_image($dst, $file, (string)$plan['mime'], (int)$plan['quality']);
      imagedestroy($dst);

      $relative = 'media/' . trim(str_replace('\\', '/', $plan['media_folder']), '/') . '/' . $name;
      $data = array_merge($plan['metadata'], array(
         'active' => 1,
         'file_name' => $name,
         'file_path' => $relative,
         'mime' => $plan['mime'],
         'size' => (int)@filesize($file),
         'width' => $width,
         'height' => $height,
         'media_type' => 'image',
         'storage_type' => 'local',
         'media_folder' => $plan['media_folder'],
      ));
      if ($this->db->insert('dbxMedia', $data) !== 1) {
         @unlink($file);
         throw new \RuntimeException('Medium konnte nicht registriert werden.');
      }
      $id = $this->db->get_insert_id();
      return array_merge(array('id' => $id, 'row' => $this->db->select1('dbxMedia', $id)), $this->media_inline_payload($id));
   }

   private function execute_media_update(array $plan): array {
      if ($this->db->update('dbxMedia', $plan['changes'], $plan['id']) !== 1) throw new \RuntimeException('Medium konnte nicht aktualisiert werden.');
      $this->invalidate_media_references((int)$plan['id']);
      return array('id' => $plan['id'], 'row' => $this->db->select1('dbxMedia', $plan['id']));
   }

   private function execute_media_assign(array $plan): array {
      $data = $plan['data'];
      $data['content_lng'] = dbxContentMediaUsageScope::language((string)($plan['lng'] ?? ''));
      if ($data['slot'] === 'hero') {
         $where = $data['content_id'] > 0
            ? 'content_id = ' . (int)$data['content_id']
            : 'folder_id = ' . (int)$data['folder_id'];
         $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::withLanguage($where . " AND slot = 'hero' AND active = 1", $data['content_lng']), 0, 1, 1, 1);
      }
      $data['sorter'] = $this->next_usage_sorter($data['content_id'], $data['folder_id'], $data['slot'], $data['content_lng']);
      if ($this->db->insert('dbxMediaUsage', $data) !== 1) throw new \RuntimeException('Medienzuordnung konnte nicht erstellt werden.');
      $id = $this->db->get_insert_id();
      if ($data['slot'] === 'hero') {
         $this->sync_hero_setting((string)($plan['lng'] ?? ''), $data);
      }
      $this->invalidate_usage($data);
      return array('usage_id' => $id, 'row' => $this->db->select1('dbxMediaUsage', $id));
   }

   private function sync_hero_setting(string $lng, array $usage): void {
      $mediaId = (int)($usage['media_id'] ?? 0);
      $contentId = (int)($usage['content_id'] ?? 0);
      $folderId = (int)($usage['folder_id'] ?? 0);
      if ($mediaId <= 0) {
         return;
      }
      if ($lng === '') {
         $lng = dbxContentLng::current();
      }

      if ($contentId > 0) {
         $dd = dbxContentLng::ddContent($lng);
         $page = $this->db->select1($dd, $contentId);
         if (!is_array($page)) {
            return;
         }
         $patch = array('hero_image_id' => (string)$mediaId);
         $heroTemplate = trim((string)($page['hero_template'] ?? ''));
         if ($heroTemplate === '' || $heroTemplate === 'parent') {
            $patch['hero_template'] = 'image-hero';
         }
         if ($this->db->update($dd, $patch, $contentId) !== 1) {
            return;
         }
         $row = $this->db->select1($dd, $contentId);
         if (is_array($row)) {
            $this->invalidate_page($contentId, $lng, $row);
         }
         return;
      }

      if ($folderId > 0) {
         $dd = dbxContentLng::ddFolder($lng);
         $folder = $this->db->select1($dd, $folderId);
         if (!is_array($folder)) {
            return;
         }
         $patch = array('hero_image_id' => (string)$mediaId);
         $heroTemplate = trim((string)($folder['hero_template'] ?? ''));
         if ($heroTemplate === '' || $heroTemplate === 'parent') {
            $patch['hero_template'] = 'image-hero';
         }
         if ($this->db->update($dd, $patch, $folderId) === 1) {
            $this->invalidate_folder($folderId);
         }
      }
   }

   private function execute_media_unassign(array $plan): array {
      if ($this->db->update('dbxMediaUsage', array('active' => 0), $plan['id']) !== 1) throw new \RuntimeException('Medienzuordnung konnte nicht entfernt werden.');
      $this->invalidate_usage($plan['before']);
      return array('unassigned' => true, 'usage_id' => $plan['id']);
   }

   private function execute_media_delete(array $plan): array {
      $cms = dbx()->get_include_obj('dbxContent_cms', 'dbxContent_admin');
      $result = $cms->delete_media_record((int)$plan['id']);
      if ((int)($result['ok'] ?? 0) !== 1) {
         throw new \RuntimeException(implode(' ', is_array($result['errors'] ?? null) ? $result['errors'] : array('Medium konnte nicht gelöscht werden.')));
      }
      return $result;
   }

   private function execute_translation_apply(array $params, array $plan): array {
      $source = $plan['source'];
      $targetLng = $plan['target_lng'];
      $targetDd = dbxContentLng::ddContent($targetLng);
      $sourceUid = trim((string)($source['lng_uid'] ?? ''));
      if ($sourceUid === '') {
         $sourceUid = dbxContentLngSync::ensureRecordUid(
            $this->db,
            dbxContentLng::ddContent($plan['source_lng']),
            (int)$source['id'],
            'p'
         );
      }
      $targetFolder = dbxContentLngSync::ensureFolderIdInLng($this->db, (int)($source['folder'] ?? 0), $targetLng);
      $data = $this->copy_page_structure($source);
      $data = array_merge($data, $plan['translation']);
      $data['folder'] = $targetFolder;
      $data['permalink'] = dbxContent_permalink::build($this->db, dbxContentLng::ddFolder($targetLng), $targetFolder, $data['title']);
      $data['lng_uid'] = $sourceUid;
      $data['lng_sync'] = 'manual';
      $data['lng_rev'] = max(1, (int)($plan['target']['lng_rev'] ?? 0) + 1);
      $data['lng_synced_rev'] = (int)($source['lng_rev'] ?? 1);

      $targetId = (int)($plan['target']['id'] ?? 0);
      if ($targetId > 0) {
         if ($this->db->update($targetDd, $data, $targetId) !== 1) throw new \RuntimeException('Übersetzung konnte nicht aktualisiert werden.');
      } else {
         if ($this->db->insert($targetDd, $data) !== 1) throw new \RuntimeException('Übersetzung konnte nicht erstellt werden.');
         $targetId = $this->db->get_insert_id();
      }

      $mediaCopied = 0;
      if ($plan['copy_media']) {
         $this->db->update(
            'dbxMediaUsage',
            array('active' => 0),
            dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . ' AND active = 1', $targetLng),
            0,
            1,
            1,
            1
         );
         $mediaCopied = $this->copy_media_usage((int)$source['id'], $targetId, $targetFolder, (string)$plan['source_lng'], $targetLng);
      }
      $row = $this->db->select1($targetDd, $targetId);
      $this->invalidate_page($targetId, $targetLng, $row);
      return array('id' => $targetId, 'lng' => $targetLng, 'row' => $row, 'media_copied' => $mediaCopied);
   }

   private function execute_translation_sync_all(array $plan): array {
      $sourceLng = (string)($plan['source_lng'] ?? '');
      $targetLngs = is_array($plan['target_lngs'] ?? null) ? $plan['target_lngs'] : array();
      $updateExisting = (bool)($plan['update_existing'] ?? true);
      $skipManual = (bool)($plan['skip_manual'] ?? false);
      $copyMedia = (bool)($plan['copy_media'] ?? true);
      $replaceMediaUsage = (bool)($plan['replace_media_usage'] ?? false);
      $sourceIds = is_array($plan['source_ids'] ?? null) ? $plan['source_ids'] : array();
      $folderIds = is_array($sourceIds['folders'] ?? null) ? array_map('intval', $sourceIds['folders']) : array();
      $pageIds = is_array($sourceIds['pages'] ?? null) ? array_map('intval', $sourceIds['pages']) : array();

      dbxContentTranslate::clearWarnings();

      $result = array(
         'source_lng' => $sourceLng,
         'target_lngs' => $targetLngs,
         'provider' => dbxContentTranslate::provider(),
         'folders' => array('created' => array(), 'updated' => array(), 'skipped' => array()),
         'pages' => array('created' => array(), 'updated' => array(), 'skipped' => array()),
         'media_copied' => 0,
         'errors' => array(),
         'warnings' => array(),
      );

      foreach ($targetLngs as $targetLng) {
         $targetLng = $this->language($targetLng);
         foreach ($folderIds as $folderId) {
            try {
               $item = $this->sync_translate_folder($sourceLng, $targetLng, $folderId, $updateExisting, $skipManual);
               $bucket = (string)($item['status'] ?? 'skipped');
               $result['folders'][$bucket === 'created' ? 'created' : ($bucket === 'updated' ? 'updated' : 'skipped')][] = $item;
            } catch (\Throwable $e) {
               $result['errors'][] = 'Ordner #' . $folderId . ' nach ' . strtoupper($targetLng) . ': ' . $e->getMessage();
            }
         }

         foreach ($pageIds as $pageId) {
            try {
               $item = $this->sync_translate_page($sourceLng, $targetLng, $pageId, $updateExisting, $skipManual, $copyMedia, $replaceMediaUsage);
               $bucket = (string)($item['status'] ?? 'skipped');
               $result['pages'][$bucket === 'created' ? 'created' : ($bucket === 'updated' ? 'updated' : 'skipped')][] = $item;
               $result['media_copied'] += (int)($item['media_copied'] ?? 0);
            } catch (\Throwable $e) {
               $result['errors'][] = 'Seite #' . $pageId . ' nach ' . strtoupper($targetLng) . ': ' . $e->getMessage();
            }
         }
      }

      $result['warnings'] = dbxContentTranslate::warnings();
      return $result;
   }
}
