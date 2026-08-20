<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/**
 * Explizite Medienwartung und Shop-Medien-Usage ueber CMS/DD/dbxDB.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxShopAdminMediaUsageServiceTrait {


   private function ensure_shop_media_usage_page(): int {
      $db = $this->db();
      $content_dd = $this->shop_media_usage_content_dd();
      try {
         $row = $db->select1($content_dd, array('permalink' => 'shop-medienverwendung'), 'id', 0);
         if (!is_array($row)) {
            $row = $db->select1($content_dd, array('permalink' => 'outside/shop-media-usage'), 'id', 0);
            if ($this->maintenance_mode && is_array($row) && (int)($row['id'] ?? 0) > 0) {
               $db->update($content_dd, array('permalink' => 'shop-medienverwendung'), (int)$row['id']);
            }
         }
         if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            return (int)$row['id'];
         }

         if (!$this->maintenance_mode) {
            return 0;
         }

         $folder_id = 0;
         $folder = $db->select1($this->shop_media_usage_folder_dd(), array('name' => 'outside'), 'id', 0);
         if (is_array($folder)) {
            $folder_id = (int)($folder['id'] ?? 0);
         }

         $insert = array(
            'activ' => 0,
            'addmenu' => 0,
            'folder' => $folder_id,
            'group_read' => 'admin',
            'sorter' => '9999',
            'title' => 'Shop Medienverwendung',
            'permalink' => 'shop-medienverwendung',
            'description' => 'Interne Seite fuer Shop-Medienverwendung.',
            'keywords' => '',
            'template' => 'c-body1-footer',
            'content' => '<p>Interne Seite fuer Shop-Medienverwendung.</p>',
            'meta_robots' => 'noindex,nofollow',
         );
         $ok = (int)$db->insert($content_dd, $insert);
         if ($ok === 1) {
            $id = (int)$db->get_insert_id();
            return $id;
         }
      } catch (\Throwable $e) {
         if (function_exists('dbx')) {
            dbx()->debug('dbxShop media_usage page failed', $e->getMessage());
         }
      }

      return 0;
   }



   private function shop_media_usage_slot(): string {
      $slot = strtolower(trim((string)dbx()->get_cfg('dbxShop', 'media_usage_slot')));
      $allowed = array('shop','hero','gallery','inline','header','teaser','footer');
      return in_array($slot, $allowed, true) ? $slot : 'shop';
   }



   private function shop_media_usage_sorter($db, int $content_id, string $slot): string {
      $where = dbxContentMediaUsageScope::with_language(
         'content_id = ' . $content_id . " AND slot = '" . str_replace("'", "''", $slot) . "' AND active = 1",
         $this->shop_media_usage_lng()
      );
      $rows = $db->select('dbxMediaUsage', $where, 'sorter,id', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int)($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }



   private function shop_media_folder_path(): string {
      $base = dbx()->get_file_dir();
      return rtrim($base, '/\\') . '/media/img/shop';
   }



   private function normalize_shop_source_image_path(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      $path = preg_replace('~^https?://[^/]+/~i', '', $path) ?: $path;
      $path = preg_replace('~^/?dbxapp/~i', '', $path) ?: $path;
      return ltrim($path, '/');
   }



   private function file_path_for_shop_image(string $path): string {
      $path = $this->normalize_shop_source_image_path($path);
      if ($path === '' || strpos($path, '..') !== false || preg_match('~(^|/)\.~', $path)) {
         return '';
      }
      if (preg_match('~^files/(.+)$~i', $path, $m)) {
         return rtrim((string)dbx()->get_file_dir(), '/\\') . '/' . $m[1];
      }
      if (preg_match('~^(media|shop)/~i', $path)) {
         return rtrim((string)dbx()->get_file_dir(), '/\\') . '/' . $path;
      }
      return '';
   }



   private function media_mime(string $file): string {
      $mime = function_exists('mime_content_type') ? (string)@mime_content_type($file) : '';
      if ($mime !== '') {
         return $mime;
      }

      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
      $map = array(
         'jpg' => 'image/jpeg',
         'jpeg' => 'image/jpeg',
         'png' => 'image/png',
         'gif' => 'image/gif',
         'webp' => 'image/webp',
         'svg' => 'image/svg+xml',
      );
      return $map[$ext] ?? 'application/octet-stream';
   }



   private function ensure_media_record_for_shop_image(array $image): int {
      $media_id = (int)($image['media_id'] ?? 0);
      if ($media_id > 0) {
         return $media_id;
      }

      $source_path = $this->normalize_shop_source_image_path((string)($image['image_path'] ?? ''));
      if ($source_path === '' || stripos($source_path, 'dbxmedia:') === 0) {
         return 0;
      }

      $source_file = $this->file_path_for_shop_image($source_path);
      if ($source_file === '' || !is_file($source_file) || !is_readable($source_file)) {
         return 0;
      }

      $name = basename($source_file);
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name) ?: ('shop-image-' . (int)($image['id'] ?? 0));
      $target_dir = $this->shop_media_folder_path();
      if (!is_dir($target_dir)) {
         @mkdir($target_dir, 0775, true);
      }
      $target_file = rtrim($target_dir, '/\\') . '/' . $name;
      if (!is_file($target_file)) {
         @copy($source_file, $target_file);
      }
      if (!is_file($target_file)) {
         return 0;
      }

      $rel = 'media/img/shop/' . $name;
      $db = $this->db();
      $existing = $db->select1('dbxMedia', array('file_path' => $rel), 'id,active', 0);
      if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
         $media_id = (int)$existing['id'];
         if ((int)($existing['active'] ?? 0) !== 1) {
            $db->update('dbxMedia', array('active' => 1), $media_id);
         }
         $this->repo()->update_image_media_reference((int)($image['id'] ?? 0), $media_id, 'dbxmedia:' . $media_id);
         return $media_id;
      }

      $width = 0;
      $height = 0;
      $size = @getimagesize($target_file);
      if (is_array($size)) {
         $width = (int)($size[0] ?? 0);
         $height = (int)($size[1] ?? 0);
      }
      $title = trim((string)($image['title'] ?? ''));
      if ($title === '') {
         $title = pathinfo($name, PATHINFO_FILENAME);
      }
      $mime = $this->media_mime($target_file);
      $insert = array(
         'active' => 1,
         'content_id' => 0,
         'folder_id' => 0,
         'slot' => 'shop',
         'usage' => 'shop',
         'sorter' => '',
         'template' => '',
         'title' => $title,
         'alt' => (string)($image['alt'] ?? $title),
         'caption' => '',
         'file_name' => $name,
         'file_path' => $rel,
         'mime' => $mime,
         'size' => (int)@filesize($target_file),
         'width' => $width,
         'height' => $height,
         'tags' => 'shop',
         'media_type' => 'image',
         'storage_type' => 'local',
         'media_folder' => 'img/shop',
      );
      $ok = (int)$db->insert('dbxMedia', $insert);
      if ($ok !== 1) {
         return 0;
      }
      $media_id = (int)$db->get_insert_id();
      if ($media_id <= 0) {
         return 0;
      }
      if ($media_id > 0) {
         $this->repo()->update_image_media_reference((int)($image['id'] ?? 0), $media_id, 'dbxmedia:' . $media_id);
      }
      return $media_id;
   }



   private function migrate_existing_shop_images_to_media(): void {
      foreach ($this->repo()->all_images() as $image) {
         if ((int)($image['active'] ?? 0) !== 1) {
            continue;
         }
         $this->ensure_media_record_for_shop_image($image);
      }
   }



   private function sync_shop_media_usage(): void {
      $db = $this->db();
      $content_id = $this->shop_media_usage_content_id();
      if ($content_id <= 0) {
         return;
      }

      $slot = $this->shop_media_usage_slot();
      $source_needle = '%"source":"dbxShop"%';
      try {
         $this->migrate_existing_shop_images_to_media();

         // Die Shop-Tabelle ist die einzige Quelle. Alte Snapshots werden
         // physisch entfernt, damit jeder Lauf exakt dieselbe Nutzung erzeugt
         // und die Datenbank nicht durch inaktive Historie waechst.
         $db->delete(
            'dbxMediaUsage',
            "slot = 'shop' OR settings LIKE '" . str_replace("'", "''", $source_needle) . "'",
            1,
            0
         );

         $by_media = array();
         foreach ($this->repo()->all_images() as $image) {
            if ((int)($image['active'] ?? 0) !== 1) {
               continue;
            }
            $media_id = (int)($image['media_id'] ?? 0);
            if ($media_id <= 0) {
               continue;
            }
            if (!isset($by_media[$media_id])) {
               $by_media[$media_id] = array(
                  'media_id' => $media_id,
                  'title' => (string)($image['title'] ?? ''),
                  'product_ids' => array(),
                  'group_ids' => array(),
               );
            }
            $product_id = (int)($image['product_id'] ?? 0);
            $group_id = (int)($image['group_id'] ?? 0);
            if ($product_id > 0) {
               $by_media[$media_id]['product_ids'][$product_id] = $product_id;
            }
            if ($group_id > 0) {
               $by_media[$media_id]['group_ids'][$group_id] = $group_id;
            }
         }

         foreach ($by_media as $media_id => $info) {
            $settings = json_encode(array(
               'source' => 'dbxShop',
               'product_ids' => array_values($info['product_ids']),
               'group_ids' => array_values($info['group_ids']),
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $db->insert('dbxMediaUsage', array(
               'active' => 1,
               'media_id' => (int)$media_id,
               'content_id' => $content_id,
               'folder_id' => 0,
               'content_lng' => $this->shop_media_usage_lng(),
               'slot' => $slot,
               'sorter' => $this->shop_media_usage_sorter($db, $content_id, $slot),
               'template' => 'image-gallery',
               'caption' => (string)($info['title'] ?? ''),
               'settings' => $settings ?: '{"source":"dbxShop"}',
            ));
         }
      } catch (\Throwable $e) {
         if (function_exists('dbx')) {
            dbx()->debug('dbxShop media_usage sync failed', $e->getMessage());
         }
      }
   }



   /** Pflegt ausschließlich die CMS-Medienreferenzen des Shops. */
   private function maintain_shop_admin_content(): void {
      $this->ensure_cms_shop_media_folder();
      $this->ensure_shop_media_usage_page();
      $this->sync_shop_media_usage();
   }
}
