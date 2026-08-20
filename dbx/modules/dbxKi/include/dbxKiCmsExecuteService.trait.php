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
      $dd = dbxContentLng::dd_folder($plan['lng']);
      $data = $plan['data'];
      $data['sorter'] = $this->next_sorter($dd, 'parent_id', (int)$data['parent_id']);
      $data += $this->lng_fields('f', $plan['lng']);
      if ($this->db->insert($dd, $data) !== 1) throw new \RuntimeException('Ordner konnte nicht erstellt werden.');
      $id = $this->db->get_insert_id();
      $this->invalidate_folder($id);
      return array('id' => $id, 'row' => $this->db->select1($dd, $id));
   }

   private function execute_folder_update(array $plan): array {
      $dd = dbxContentLng::dd_folder($plan['lng']);
      $data = $plan['changes'];
      $data = $this->advance_revision($dd, $plan['id'], $data, $plan['lng']);
      if ($this->db->update($dd, $data, $plan['id']) !== 1) throw new \RuntimeException('Ordner konnte nicht aktualisiert werden.');
      $this->invalidate_folder($plan['id']);
      return array('id' => $plan['id'], 'row' => $this->db->select1($dd, $plan['id']));
   }

   private function execute_folder_delete(array $plan): array {
      $dd = dbxContentLng::dd_folder($plan['lng']);
      if ($this->db->delete($dd, $plan['id']) !== 1) throw new \RuntimeException('Ordner konnte nicht gelöscht werden.');
      $this->invalidate_folder($plan['id']);
      return array('deleted' => true, 'id' => $plan['id'], 'lng' => $plan['lng']);
   }

   private function execute_page_create(array $plan): array {
      $dd = dbxContentLng::dd_content($plan['lng']);
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
      $dd = dbxContentLng::dd_content($plan['lng']);
      $data = $this->advance_revision($dd, $plan['id'], $plan['changes'], $plan['lng']);
      if ($this->db->update($dd, $data, $plan['id']) !== 1) throw new \RuntimeException('Seite konnte nicht aktualisiert werden.');
      $media_id = (int)($plan['package_media_id_applied'] ?? 0);
      if ($media_id > 0) {
         $this->ensure_inline_media_usage((int)$plan['id'], $media_id, (string)$plan['lng']);
      }
      $row = $this->db->select1($dd, $plan['id']);
      $this->invalidate_page($plan['id'], $plan['lng'], $row);
      $result = array('id' => $plan['id'], 'row' => $row);
      if ($media_id > 0) {
         $result['package_media_id'] = $media_id;
         $result = array_merge($result, $this->media_inline_payload($media_id));
      }
      return $result;
   }

   private function execute_page_hero_replace_image(array $plan): array {
      $target = (string)$plan['target_file'];
      $this->render_image_variant_to_file($plan['source'], $target, (int)$plan['width'], (int)$plan['height'], (string)$plan['fit'], (string)$plan['mime'], (int)$plan['quality']);

      $media_id = (int)($plan['media']['id'] ?? 0);
      $data = array(
         'size' => (int)@filesize($target),
         'width' => (int)$plan['width'],
         'height' => (int)$plan['height'],
         'mime' => (string)$plan['mime'],
      );
      if ($media_id <= 0) throw new \RuntimeException('Hero-Medium konnte nicht aktualisiert werden.');
      $this->db->update('dbxMedia', $data, $media_id);
      $this->invalidate_media_references($media_id);
      $this->invalidate_page((int)$plan['id'], (string)$plan['lng'], $plan['page']);
      return array(
         'id' => (int)$plan['id'],
         'media_id' => $media_id,
         'file' => str_replace('\\', '/', $target),
         'replaced' => true,
      );
   }

   private function execute_page_hero_create_image(array $plan): array {
      $media = $this->execute_media_create_image_variant($plan['media_plan']);
      $media_id = (int)($media['id'] ?? 0);
      if ($media_id <= 0) throw new \RuntimeException('Hero-Medium konnte nicht erstellt werden.');

      $data = array(
         'active' => 1,
         'media_id' => $media_id,
         'content_id' => (int)$plan['id'],
         'folder_id' => 0,
         'content_lng' => dbxContentMediaUsageScope::language((string)$plan['lng']),
         'slot' => 'hero',
         'template' => '',
         'caption' => '',
         'settings' => '',
      );
      $where = dbxContentMediaUsageScope::with_language('content_id = ' . (int)$plan['id'] . " AND slot = 'hero' AND active = 1", (string)$plan['lng']);
      $this->db->update('dbxMediaUsage', array('active' => 0), $where, 0, 1, 1, 1);
      $data['sorter'] = $this->next_usage_sorter((int)$plan['id'], 0, 'hero', (string)$plan['lng']);
      if ($this->db->insert('dbxMediaUsage', $data) !== 1) {
         throw new \RuntimeException('Hero-Medienzuordnung konnte nicht erstellt werden.');
      }
      $usage_id = $this->db->get_insert_id();
      $this->sync_hero_setting((string)$plan['lng'], $data);
      $this->invalidate_usage($data);
      $row = $this->db->select1(dbxContentLng::dd_content((string)$plan['lng']), (int)$plan['id']);
      return array(
         'id' => (int)$plan['id'],
         'media_id' => $media_id,
         'usage_id' => $usage_id,
         'row' => $row,
         'media' => $media['row'] ?? array(),
      );
   }

   private function execute_page_delete(array $plan): array {
      $dd = dbxContentLng::dd_content($plan['lng']);
      if ($this->db->delete($dd, $plan['id']) !== 1) throw new \RuntimeException('Seite konnte nicht gelöscht werden.');
      $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::with_language('content_id = ' . (int)$plan['id'] . ' AND active = 1', (string)$plan['lng']), 0, 1, 1, 1);
      dbxContentPageCache::invalidate_content($plan['id']);
      dbxContentPageCache::invalidate_all_menus();
      dbxContentPermalinkIndex::remove_by_cid($plan['id'], $plan['lng']);
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

      $source_width = imagesx($src);
      $source_height = imagesy($src);
      $source_x = 0;
      $source_y = 0;
      $crop = is_array($plan['crop'] ?? null) ? $plan['crop'] : array();
      if ($crop) {
         $source_x = max(0, min((int)($crop['x'] ?? 0), $source_width - 1));
         $source_y = max(0, min((int)($crop['y'] ?? 0), $source_height - 1));
         $source_width = max(1, min((int)($crop['width'] ?? $source_width), imagesx($src) - $source_x));
         $source_height = max(1, min((int)($crop['height'] ?? $source_height), imagesy($src) - $source_y));
      }
      $fit = (string)($plan['fit'] ?? 'cover');
      if ($fit === 'contain') {
         $scale = min($width / $source_width, $height / $source_height);
         $copy_width = max(1, (int)round($source_width * $scale));
         $copy_height = max(1, (int)round($source_height * $scale));
         $dst_x = (int)floor(($width - $copy_width) / 2);
         $dst_y = (int)floor(($height - $copy_height) / 2);
         imagecopyresampled($dst, $src, $dst_x, $dst_y, $source_x, $source_y, $copy_width, $copy_height, $source_width, $source_height);
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
         $this->db->update('dbxMediaUsage', array('active' => 0), dbxContentMediaUsageScope::with_language($where . " AND slot = 'hero' AND active = 1", $data['content_lng']), 0, 1, 1, 1);
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
      $media_id = (int)($usage['media_id'] ?? 0);
      $content_id = (int)($usage['content_id'] ?? 0);
      $folder_id = (int)($usage['folder_id'] ?? 0);
      if ($media_id <= 0) {
         return;
      }
      if ($lng === '') {
         $lng = dbxContentLng::current();
      }

      if ($content_id > 0) {
         $dd = dbxContentLng::dd_content($lng);
         $page = $this->db->select1($dd, $content_id);
         if (!is_array($page)) {
            return;
         }
         $patch = array('hero_image_id' => (string)$media_id);
         $hero_template = trim((string)($page['hero_template'] ?? ''));
         if ($hero_template === '' || $hero_template === 'parent') {
            $patch['hero_template'] = 'image-hero';
         }
         if ($this->db->update($dd, $patch, $content_id) !== 1) {
            return;
         }
         $row = $this->db->select1($dd, $content_id);
         if (is_array($row)) {
            $this->invalidate_page($content_id, $lng, $row);
         }
         return;
      }

      if ($folder_id > 0) {
         $dd = dbxContentLng::dd_folder($lng);
         $folder = $this->db->select1($dd, $folder_id);
         if (!is_array($folder)) {
            return;
         }
         $patch = array('hero_image_id' => (string)$media_id);
         $hero_template = trim((string)($folder['hero_template'] ?? ''));
         if ($hero_template === '' || $hero_template === 'parent') {
            $patch['hero_template'] = 'image-hero';
         }
         if ($this->db->update($dd, $patch, $folder_id) === 1) {
            $this->invalidate_folder($folder_id);
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
      $target_lng = $plan['target_lng'];
      $target_dd = dbxContentLng::dd_content($target_lng);
      $source_uid = trim((string)($source['lng_uid'] ?? ''));
      if ($source_uid === '') {
         $source_uid = dbxContentLngSync::ensure_record_uid(
            $this->db,
            dbxContentLng::dd_content($plan['source_lng']),
            (int)$source['id'],
            'p'
         );
      }
      $target_folder = dbxContentLngSync::ensure_folder_id_in_lng($this->db, (int)($source['folder'] ?? 0), $target_lng);
      $data = $this->copy_page_structure($source);
      $data = array_merge($data, $plan['translation']);
      $data['folder'] = $target_folder;
      $data['permalink'] = dbxContent_permalink::build($this->db, dbxContentLng::dd_folder($target_lng), $target_folder, $data['title']);
      $data['lng_uid'] = $source_uid;
      $data['lng_sync'] = 'manual';
      $data['lng_rev'] = max(1, (int)($plan['target']['lng_rev'] ?? 0) + 1);
      $data['lng_synced_rev'] = (int)($source['lng_rev'] ?? 1);

      $target_id = (int)($plan['target']['id'] ?? 0);
      if ($target_id > 0) {
         if ($this->db->update($target_dd, $data, $target_id) !== 1) throw new \RuntimeException('Übersetzung konnte nicht aktualisiert werden.');
      } else {
         if ($this->db->insert($target_dd, $data) !== 1) throw new \RuntimeException('Übersetzung konnte nicht erstellt werden.');
         $target_id = $this->db->get_insert_id();
      }

      $media_copied = 0;
      if ($plan['copy_media']) {
         $this->db->update(
            'dbxMediaUsage',
            array('active' => 0),
            dbxContentMediaUsageScope::with_language('content_id = ' . $target_id . ' AND active = 1', $target_lng),
            0,
            1,
            1,
            1
         );
         $media_copied = $this->copy_media_usage((int)$source['id'], $target_id, $target_folder, (string)$plan['source_lng'], $target_lng);
      }
      $row = $this->db->select1($target_dd, $target_id);
      $this->invalidate_page($target_id, $target_lng, $row);
      return array('id' => $target_id, 'lng' => $target_lng, 'row' => $row, 'media_copied' => $media_copied);
   }

   private function execute_translation_sync_all(array $plan): array {
      $source_lng = (string)($plan['source_lng'] ?? '');
      $target_lngs = is_array($plan['target_lngs'] ?? null) ? $plan['target_lngs'] : array();
      $update_existing = (bool)($plan['update_existing'] ?? true);
      $skip_manual = (bool)($plan['skip_manual'] ?? false);
      $copy_media = (bool)($plan['copy_media'] ?? true);
      $replace_media_usage = (bool)($plan['replace_media_usage'] ?? false);
      $source_ids = is_array($plan['source_ids'] ?? null) ? $plan['source_ids'] : array();
      $folder_ids = is_array($source_ids['folders'] ?? null) ? array_map('intval', $source_ids['folders']) : array();
      $page_ids = is_array($source_ids['pages'] ?? null) ? array_map('intval', $source_ids['pages']) : array();

      dbxContentTranslate::clear_warnings();

      $result = array(
         'source_lng' => $source_lng,
         'target_lngs' => $target_lngs,
         'provider' => dbxContentTranslate::provider(),
         'folders' => array('created' => array(), 'updated' => array(), 'skipped' => array()),
         'pages' => array('created' => array(), 'updated' => array(), 'skipped' => array()),
         'media_copied' => 0,
         'errors' => array(),
         'warnings' => array(),
      );

      foreach ($target_lngs as $target_lng) {
         $target_lng = $this->language($target_lng);
         foreach ($folder_ids as $folder_id) {
            try {
               $item = $this->sync_translate_folder($source_lng, $target_lng, $folder_id, $update_existing, $skip_manual);
               $bucket = (string)($item['status'] ?? 'skipped');
               $result['folders'][$bucket === 'created' ? 'created' : ($bucket === 'updated' ? 'updated' : 'skipped')][] = $item;
            } catch (\Throwable $e) {
               $result['errors'][] = 'Ordner #' . $folder_id . ' nach ' . strtoupper($target_lng) . ': ' . $e->getMessage();
            }
         }

         foreach ($page_ids as $page_id) {
            try {
               $item = $this->sync_translate_page($source_lng, $target_lng, $page_id, $update_existing, $skip_manual, $copy_media, $replace_media_usage);
               $bucket = (string)($item['status'] ?? 'skipped');
               $result['pages'][$bucket === 'created' ? 'created' : ($bucket === 'updated' ? 'updated' : 'skipped')][] = $item;
               $result['media_copied'] += (int)($item['media_copied'] ?? 0);
            } catch (\Throwable $e) {
               $result['errors'][] = 'Seite #' . $page_id . ' nach ' . strtoupper($target_lng) . ': ' . $e->getMessage();
            }
         }
      }

      $result['warnings'] = dbxContentTranslate::warnings();
      return $result;
   }
}
