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
      dbxContentPageCache::invalidateContent($id);
      dbxContentPageCache::invalidateAllMenus();
      $previousLng = dbx()->get_system_var('dbx_lng', dbxContentLngSync::masterLng());
      dbx()->set_system_var('dbx_lng', $lng);
      $renderer = new dbxContentRenderer();
      $rights = $renderer->getPublicFolderRights((int)($row['folder'] ?? 0));
      if ((int)($row['activ'] ?? 1) === 1 && trim((string)($row['permalink'] ?? '')) !== '') {
         dbxContentPermalinkIndex::upsertPage($id, (string)$row['permalink'], $rights, 1, $lng);
      } else {
         dbxContentPermalinkIndex::removeByCid($id, $lng);
      }
      dbxContentHome::refreshHomeCache($this->db, $id, $lng);
      dbx()->set_system_var('dbx_lng', $previousLng);
   }

   private function invalidate_folder(int $id): void {
      dbxContentPageCache::invalidateFolderTree($this->db, $id);
      dbxContentPageCache::invalidateAllMenus();
   }

   private function invalidate_usage(array $usage): void {
      $content = (int)($usage['content_id'] ?? 0);
      $folder = (int)($usage['folder_id'] ?? 0);
      if ($content > 0) dbxContentPageCache::invalidateContent($content);
      if ($folder > 0) dbxContentPageCache::invalidateFolderTree($this->db, $folder);
      dbxContentPageCache::invalidateAllMenus();
   }

   private function invalidate_media_references(int $mediaId): void {
      $mediaId = (int)$mediaId;
      if ($mediaId <= 0) {
         return;
      }

      $rows = $this->db->select('dbxMediaUsage', 'media_id = ' . $mediaId . ' AND active = 1', '*', 'id', 'ASC', '', 0, 0, 0);
      foreach (is_array($rows) ? $rows : array() as $row) {
         if (is_array($row)) {
            $this->invalidate_usage($row);
         }
      }
   }
}
