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

trait dbxKiCmsCacheServiceTrait {

   private function invalidate_page(int $id, string $lng, array $row): void {
      dbxContentPageCache::invalidate_content($id);
      dbxContentPageCache::invalidate_all_menus();
      $previous_lng = dbx()->get_system_var('dbx_lng', dbxContentLngSync::master_lng());
      dbx()->set_system_var('dbx_lng', $lng);
      $renderer = new dbxContentRenderer();
      $rights = $renderer->get_public_folder_rights((int)($row['folder'] ?? 0));
      if ((int)($row['activ'] ?? 1) === 1 && trim((string)($row['permalink'] ?? '')) !== '') {
         dbxContentPermalinkIndex::upsert_page($id, (string)$row['permalink'], $rights, 1, $lng);
      } else {
         dbxContentPermalinkIndex::remove_by_cid($id, $lng);
      }
      dbxContentHome::refresh_home_cache($this->db, $id, $lng);
      dbx()->set_system_var('dbx_lng', $previous_lng);
   }

   private function invalidate_folder(int $id): void {
      dbxContentPageCache::invalidate_folder_tree($this->db, $id);
      dbxContentPageCache::invalidate_all_menus();
   }

   private function invalidate_usage(array $usage): void {
      $content = (int)($usage['content_id'] ?? 0);
      $folder = (int)($usage['folder_id'] ?? 0);
      if ($content > 0) dbxContentPageCache::invalidate_content($content);
      if ($folder > 0) dbxContentPageCache::invalidate_folder_tree($this->db, $folder);
      dbxContentPageCache::invalidate_all_menus();
   }

   private function invalidate_media_references(int $media_id): void {
      $media_id = (int)$media_id;
      if ($media_id <= 0) {
         return;
      }

      $rows = $this->db->select('dbxMediaUsage', 'media_id = ' . $media_id . ' AND active = 1', '*', 'id', 'ASC', '', 0, 0, 0);
      foreach (is_array($rows) ? $rows : array() as $row) {
         if (is_array($row)) {
            $this->invalidate_usage($row);
         }
      }
   }
}
