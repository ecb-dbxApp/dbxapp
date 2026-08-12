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


   private function ensureShopMediaUsagePage(): int {
      $db = $this->db();
      $contentDd = $this->shopMediaUsageContentDd();
      try {
         $row = $db->select1($contentDd, array('permalink' => 'shop-medienverwendung'), 'id', 0);
         if (!is_array($row)) {
            $row = $db->select1($contentDd, array('permalink' => 'outside/shop-media-usage'), 'id', 0);
            if ($this->maintenanceMode && is_array($row) && (int)($row['id'] ?? 0) > 0) {
               $db->update($contentDd, array('permalink' => 'shop-medienverwendung'), (int)$row['id']);
            }
         }
         if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            return (int)$row['id'];
         }

         if (!$this->maintenanceMode) {
            return 0;
         }

         $folderId = 0;
         $folder = $db->select1($this->shopMediaUsageFolderDd(), array('name' => 'outside'), 'id', 0);
         if (is_array($folder)) {
            $folderId = (int)($folder['id'] ?? 0);
         }

         $insert = array(
            'activ' => 0,
            'addmenu' => 0,
            'folder' => $folderId,
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
         $ok = (int)$db->insert($contentDd, $insert);
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



   private function shopMediaUsageSlot(): string {
      $slot = strtolower(trim((string)dbx()->get_cfg('dbxShop', 'media_usage_slot')));
      $allowed = array('shop','hero','gallery','inline','header','teaser','footer');
      return in_array($slot, $allowed, true) ? $slot : 'shop';
   }



   private function shopMediaUsageSorter($db, int $contentId, string $slot): string {
      $where = dbxContentMediaUsageScope::withLanguage(
         'content_id = ' . $contentId . " AND slot = '" . str_replace("'", "''", $slot) . "' AND active = 1",
         $this->shopMediaUsageLng()
      );
      $rows = $db->select('dbxMediaUsage', $where, 'sorter,id', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int)($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }



   private function shopMediaFolderPath(): string {
      $base = dbx()->get_file_dir();
      return rtrim($base, '/\\') . '/media/img/shop';
   }



   private function normalizeShopSourceImagePath(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      $path = preg_replace('~^https?://[^/]+/~i', '', $path) ?: $path;
      $path = preg_replace('~^/?dbxapp/~i', '', $path) ?: $path;
      return ltrim($path, '/');
   }



   private function filePathForShopImage(string $path): string {
      $path = $this->normalizeShopSourceImagePath($path);
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



   private function mediaMime(string $file): string {
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



   private function ensureMediaRecordForShopImage(array $image): int {
      $mediaId = (int)($image['media_id'] ?? 0);
      if ($mediaId > 0) {
         return $mediaId;
      }

      $sourcePath = $this->normalizeShopSourceImagePath((string)($image['image_path'] ?? ''));
      if ($sourcePath === '' || stripos($sourcePath, 'dbxmedia:') === 0) {
         return 0;
      }

      $sourceFile = $this->filePathForShopImage($sourcePath);
      if ($sourceFile === '' || !is_file($sourceFile) || !is_readable($sourceFile)) {
         return 0;
      }

      $name = basename($sourceFile);
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name) ?: ('shop-image-' . (int)($image['id'] ?? 0));
      $targetDir = $this->shopMediaFolderPath();
      if (!is_dir($targetDir)) {
         @mkdir($targetDir, 0775, true);
      }
      $targetFile = rtrim($targetDir, '/\\') . '/' . $name;
      if (!is_file($targetFile)) {
         @copy($sourceFile, $targetFile);
      }
      if (!is_file($targetFile)) {
         return 0;
      }

      $rel = 'media/img/shop/' . $name;
      $db = $this->db();
      $existing = $db->select1('dbxMedia', array('file_path' => $rel), 'id,active', 0);
      if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
         $mediaId = (int)$existing['id'];
         if ((int)($existing['active'] ?? 0) !== 1) {
            $db->update('dbxMedia', array('active' => 1), $mediaId);
         }
         $this->repo()->updateImageMediaReference((int)($image['id'] ?? 0), $mediaId, 'dbxmedia:' . $mediaId);
         return $mediaId;
      }

      $width = 0;
      $height = 0;
      $size = @getimagesize($targetFile);
      if (is_array($size)) {
         $width = (int)($size[0] ?? 0);
         $height = (int)($size[1] ?? 0);
      }
      $title = trim((string)($image['title'] ?? ''));
      if ($title === '') {
         $title = pathinfo($name, PATHINFO_FILENAME);
      }
      $mime = $this->mediaMime($targetFile);
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
         'size' => (int)@filesize($targetFile),
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
      $mediaId = (int)$db->get_insert_id();
      if ($mediaId <= 0) {
         return 0;
      }
      if ($mediaId > 0) {
         $this->repo()->updateImageMediaReference((int)($image['id'] ?? 0), $mediaId, 'dbxmedia:' . $mediaId);
      }
      return $mediaId;
   }



   private function migrateExistingShopImagesToMedia(): void {
      foreach ($this->repo()->allImages() as $image) {
         if ((int)($image['active'] ?? 0) !== 1) {
            continue;
         }
         $this->ensureMediaRecordForShopImage($image);
      }
   }



   private function syncShopMediaUsage(): void {
      $db = $this->db();
      $contentId = $this->shopMediaUsageContentId();
      if ($contentId <= 0) {
         return;
      }

      $slot = $this->shopMediaUsageSlot();
      $sourceNeedle = '%"source":"dbxShop"%';
      try {
         $this->migrateExistingShopImagesToMedia();

         // Die Shop-Tabelle ist die einzige Quelle. Alte Snapshots werden
         // physisch entfernt, damit jeder Lauf exakt dieselbe Nutzung erzeugt
         // und die Datenbank nicht durch inaktive Historie waechst.
         $db->delete(
            'dbxMediaUsage',
            "slot = 'shop' OR settings LIKE '" . str_replace("'", "''", $sourceNeedle) . "'",
            1,
            0
         );

         $byMedia = array();
         foreach ($this->repo()->allImages() as $image) {
            if ((int)($image['active'] ?? 0) !== 1) {
               continue;
            }
            $mediaId = (int)($image['media_id'] ?? 0);
            if ($mediaId <= 0) {
               continue;
            }
            if (!isset($byMedia[$mediaId])) {
               $byMedia[$mediaId] = array(
                  'media_id' => $mediaId,
                  'title' => (string)($image['title'] ?? ''),
                  'product_ids' => array(),
                  'group_ids' => array(),
               );
            }
            $productId = (int)($image['product_id'] ?? 0);
            $groupId = (int)($image['group_id'] ?? 0);
            if ($productId > 0) {
               $byMedia[$mediaId]['product_ids'][$productId] = $productId;
            }
            if ($groupId > 0) {
               $byMedia[$mediaId]['group_ids'][$groupId] = $groupId;
            }
         }

         foreach ($byMedia as $mediaId => $info) {
            $settings = json_encode(array(
               'source' => 'dbxShop',
               'product_ids' => array_values($info['product_ids']),
               'group_ids' => array_values($info['group_ids']),
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $db->insert('dbxMediaUsage', array(
               'active' => 1,
               'media_id' => (int)$mediaId,
               'content_id' => $contentId,
               'folder_id' => 0,
               'content_lng' => $this->shopMediaUsageLng(),
               'slot' => $slot,
               'sorter' => $this->shopMediaUsageSorter($db, $contentId, $slot),
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



   /**
    * Provisioniert Shop-Hilfen und Medienreferenzen bewusst nur im
    * administrativen Wartungslauf.
    */
   private function maintainShopAdminContent(): void {
      $this->ensureCmsShopMediaFolder();
      $this->ensureShopMediaUsagePage();
      $this->ensureShopChannelHelpPage();
      $this->ensureShopChannelsHelpPage();
      foreach (array('shop', 'amazon', 'ebay', 'kleinanzeigen', 'mobile', 'custom') as $platform) {
         $this->ensureShopChannelProviderHelpPage($platform);
      }
      $this->ensureShopProductGroupsHelpPage();
      $this->ensureShopShippingGroupsHelpPage();
      $this->ensureShopSettingsHelpPage();
      $this->ensureShopOrdersHelpPage();
      $this->ensureShopProductsHelpPage();
      $this->ensureShopProductChannelMappingHelpPage();
      $this->ensureShopProductAttributesHelpPage();
      $this->ensureShopMediaHelpPage();
      $this->syncShopMediaUsage();
   }
}
