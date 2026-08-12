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

/**
 * Einheitliche Persistenzgrenze des Content-CMS.
 *
 * Alle Seiten- und Ordnermutationen laufen hier ueber dbxDB, innerhalb einer
 * Transaktion und unabhaengig vom verwendeten PDO-Treiber. Cache und
 * Permalink-Index werden erst nach einem erfolgreichen Commit aktualisiert.
 * Der Controller bleibt dadurch fuer Request-Validierung und JSON-Ausgabe
 * zustaendig, nicht fuer Datenbank-Lebenszyklen.
 */
final class dbxContentCmsPersistenceService {

   public function __construct(
      private object $db,
      private string $mediaDd = 'dbxMedia',
      private string $mediaUsageDd = 'dbxMediaUsage'
   ) {}

   /** Fuehrt eine Mutation atomar aus und lokalisiert Fehler im Systemlog. */
   private function transaction(string $dd, string $action, string $label, callable $work): mixed {
      if ((int)$this->db->begin($dd) !== 1) {
         throw new \RuntimeException($label . ': transaction begin failed');
      }
      try {
         $result = $work();
         if ((int)$this->db->commit($dd) !== 1) {
            throw new \RuntimeException($label . ': transaction commit failed');
         }
         return $result;
      } catch (\Throwable $e) {
         $this->db->rollback($dd);
         dbx()->sys_msg('error', 'dbxContent_admin', $action, $label, $e->getMessage());
         throw $e;
      }
   }

   /** @return array{saved_id:int,sync_result:array,inline_sync:array} */
   public function savePage(
      array $data,
      int $id,
      array $payload,
      callable $syncHero,
      callable $syncInline,
      callable $syncLanguageMedia
   ): array {
      $dd = dbxContentLng::ddContent();
      $result = $this->transaction($dd, 'cms_save_page', 'CMS-Seite konnte nicht atomar gespeichert werden', function() use (
         $dd, $data, $id, $payload, $syncHero, $syncInline, $syncLanguageMedia
      ): array {
         $ok = $id > 0 ? $this->db->update($dd, $data, $id) : $this->db->insert($dd, $data);
         $savedId = $id > 0 ? $id : (($ok === 1) ? (int)$this->db->get_insert_id() : 0);
         if ($ok !== 1 || $savedId <= 0) throw new \RuntimeException('content write failed');

         $syncHero($this->db, $savedId, 0, $data['hero_image_id'] ?? 'parent');
         dbxContentLngSync::afterPageSave($this->db, $savedId, $id <= 0);
         $syncResult = array('updated' => array(), 'skipped' => array(), 'errors' => array());
         if ($id > 0 && dbxContentLngSync::isMasterLng()) {
            dbxContentTranslate::clearWarnings();
            $syncResult = dbxContentLngSync::syncSlavesFromMaster($this->db, 'page', $savedId);
            $syncResult['media_copied'] = (int)$syncLanguageMedia($this->db, $savedId, $syncResult);
            $syncResult['translate_warnings'] = dbxContentTranslate::warnings();
         }
         $inlineProvided = array_key_exists('inline_media_ids', $payload) && is_array($payload['inline_media_ids']);
         $inlineSync = $syncInline(
            $this->db,
            $savedId,
            (string)($data['content'] ?? ''),
            (int)($data['folder'] ?? 0),
            $inlineProvided ? $payload['inline_media_ids'] : null,
            $inlineProvided
         );
         return array('saved_id' => $savedId, 'sync_result' => $syncResult, 'inline_sync' => $inlineSync);
      });

      $this->flushLngSyncCache((array)$result['sync_result'], false);
      $this->flushSavedPageCache((int)$result['saved_id']);
      return $result;
   }

   public function createPage(array $data): int {
      $dd = dbxContentLng::ddContent();
      $id = (int)$this->transaction($dd, 'cms_new_page', 'CMS-Seite konnte nicht atomar angelegt werden', function() use ($dd, $data): int {
         if ($this->db->insert($dd, $data, 0, 1, 0, 1) !== 1) throw new \RuntimeException('page insert failed');
         $id = (int)$this->db->get_insert_id();
         if ($id <= 0) throw new \RuntimeException('page insert id missing');
         dbxContentLngSync::afterPageSave($this->db, $id, true);
         return $id;
      });
      $this->flushSavedPageCache($id);
      return $id;
   }

   /** @return array{new_id:int,media_copied:int,inline_sync:array} */
   public function duplicatePage(array $copy, callable $copyMedia, callable $syncInline): array {
      $dd = dbxContentLng::ddContent();
      $result = $this->transaction($dd, 'cms_duplicate_page', 'CMS-Seite konnte nicht atomar dupliziert werden', function() use (
         $dd, $copy, $copyMedia, $syncInline
      ): array {
         if ($this->db->insert($dd, $copy, 0, 1, 0, 1) !== 1) throw new \RuntimeException('page duplicate insert failed');
         $newId = (int)$this->db->get_insert_id();
         if ($newId <= 0) throw new \RuntimeException('page duplicate id missing');
         dbxContentLngSync::afterPageSave($this->db, $newId, true);
         $mediaCopied = (int)$copyMedia($this->db, $newId);
         $inlineSync = $syncInline($this->db, $newId);
         return array('new_id' => $newId, 'media_copied' => $mediaCopied, 'inline_sync' => $inlineSync);
      });
      $this->flushSavedPageCache((int)$result['new_id']);
      return $result;
   }

   /** @return array{saved_id:int,sync_result:array} */
   public function saveFolder(array $data, int $id, callable $syncHero): array {
      $dd = dbxContentLng::ddFolder();
      $result = $this->transaction($dd, 'cms_save_folder', 'CMS-Ordner konnte nicht atomar gespeichert werden', function() use (
         $dd, $data, $id, $syncHero
      ): array {
         $ok = $id > 0 ? $this->db->update($dd, $data, $id) : $this->db->insert($dd, $data);
         $savedId = $id > 0 ? $id : (($ok === 1) ? (int)$this->db->get_insert_id() : 0);
         if ($ok !== 1 || $savedId <= 0) throw new \RuntimeException('folder write failed');
         $syncHero($this->db, 0, $savedId, $data['hero_image_id'] ?? 'parent');
         dbxContentLngSync::afterFolderSave($this->db, $savedId, $id <= 0);
         $syncResult = array('updated' => array(), 'skipped' => array(), 'errors' => array());
         if ($id > 0 && dbxContentLngSync::isMasterLng()) {
            dbxContentTranslate::clearWarnings();
            $syncResult = dbxContentLngSync::syncSlavesFromMaster($this->db, 'folder', $savedId);
            $syncResult['translate_warnings'] = dbxContentTranslate::warnings();
         }
         return array('saved_id' => $savedId, 'sync_result' => $syncResult);
      });
      $this->flushLngSyncCache((array)$result['sync_result'], false);
      return $result;
   }

   public function createFolder(array $data, int $parentId): int {
      $dd = dbxContentLng::ddFolder();
      $id = (int)$this->transaction($dd, 'cms_new_folder', 'CMS-Ordner konnte nicht atomar angelegt werden', function() use ($dd, $data): int {
         if ($this->db->insert($dd, $data) !== 1) throw new \RuntimeException('folder insert failed');
         $id = (int)$this->db->get_insert_id();
         if ($id <= 0) throw new \RuntimeException('folder insert id missing');
         dbxContentLngSync::afterFolderSave($this->db, $id, true);
         return $id;
      });
      $this->flushSavedFolderCache($id, $parentId);
      return $id;
   }

   /** Loescht alle gewaehlten Sprachversionen gemeinsam oder keine. */
   public function deletePage(int $id, array $languages): array {
      $targets = dbxContentLngSync::resolveDeleteIds($this->db, 'page', $id, $languages);
      if (!$targets) return array('ok' => 0, 'deleted' => array(), 'errors' => array('Keine loeschbare Sprachversion gefunden.'));
      try {
         $deleted = $this->transaction(dbxContentLng::ddContent(), 'cms_delete_page', 'CMS-Seite konnte nicht atomar geloescht werden', function() use ($targets): array {
            $deleted = array();
            foreach ($targets as $target) {
               $lng = (string)($target['lng'] ?? '');
               $targetId = (int)($target['id'] ?? 0);
               if ($targetId <= 0) continue;
               if ($this->db->delete(dbxContentLng::ddContent($lng), 'id = ' . $targetId, 1, 0) !== 1) {
                  throw new \RuntimeException('delete page ' . $targetId . ' in ' . $lng . ' failed');
               }
               if ($this->db->update($this->mediaDd, array('active' => 1, 'content_id' => 0, 'folder_id' => 0), 'content_id = ' . $targetId, 0, 1, 1, 0) !== 1) {
                  throw new \RuntimeException('release page media ' . $targetId . ' failed');
               }
               if ($this->db->update(
                  $this->mediaUsageDd,
                  array('active' => 0),
                  dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . ' AND active = 1', $lng),
                  0, 1, 1, 0
               ) !== 1) {
                  throw new \RuntimeException('deactivate page media usage ' . $targetId . ' failed');
               }
               $deleted[] = array('lng' => $lng, 'id' => $targetId);
            }
            if (!$deleted) throw new \RuntimeException('no page deleted');
            return $deleted;
         });
      } catch (\Throwable $e) {
         return array('ok' => 0, 'deleted' => array(), 'errors' => array('Loeschen fehlgeschlagen: ' . $e->getMessage()));
      }
      foreach ($deleted as $target) $this->flushDeletedPageCache((int)$target['id'], (string)$target['lng'], false);
      dbxContentPageCache::invalidateAllMenus();
      return array('ok' => 1, 'deleted' => $deleted, 'errors' => array());
   }

   /** Loescht nur vollstaendig leere Ordner und alle Sprachen atomar. */
   public function deleteFolder(int $id, array $languages): array {
      $targets = dbxContentLngSync::resolveDeleteIds($this->db, 'folder', $id, $languages);
      $errors = array();
      foreach ($targets as $target) {
         $lng = (string)($target['lng'] ?? '');
         $targetId = (int)($target['id'] ?? 0);
         if ($targetId <= 0) continue;
         $check = dbxContentLngSync::folderDeletable($this->db, $lng, $targetId);
         if ((int)($check['deletable'] ?? 0) !== 1) {
            $reason = trim((string)($check['reason'] ?? ''));
            $errors[] = strtoupper($lng) . ': ' . ($reason !== '' ? $reason : 'Ordner kann nicht geloescht werden.');
         }
      }
      if ($errors || !$targets) return array('ok' => 0, 'deleted' => array(), 'errors' => $errors ?: array('Keine loeschbare Sprachversion gefunden.'));
      try {
         $deleted = $this->transaction(dbxContentLng::ddFolder(), 'cms_delete_folder', 'CMS-Ordner konnte nicht atomar geloescht werden', function() use ($targets): array {
            $deleted = array();
            foreach ($targets as $target) {
               $lng = (string)($target['lng'] ?? '');
               $targetId = (int)($target['id'] ?? 0);
               if ($targetId <= 0) continue;
               if ($this->db->delete(dbxContentLng::ddFolder($lng), 'id = ' . $targetId, 1, 0) !== 1) {
                  throw new \RuntimeException('delete folder ' . $targetId . ' in ' . $lng . ' failed');
               }
               if ($this->db->update(
                  $this->mediaUsageDd,
                  array('active' => 0),
                  dbxContentMediaUsageScope::withLanguage('folder_id = ' . $targetId . ' AND content_id = 0 AND active = 1', $lng),
                  0, 1, 1, 0
               ) !== 1) {
                  throw new \RuntimeException('deactivate folder media usage ' . $targetId . ' failed');
               }
               $deleted[] = array('lng' => $lng, 'id' => $targetId);
            }
            if (!$deleted) throw new \RuntimeException('no folder deleted');
            return $deleted;
         });
      } catch (\Throwable $e) {
         return array('ok' => 0, 'deleted' => array(), 'errors' => array('Loeschen fehlgeschlagen: ' . $e->getMessage()));
      }
      foreach ($deleted as $target) $this->flushDeletedFolderCache((int)$target['id'], (string)$target['lng'], false);
      dbxContentPageCache::invalidateAllMenus();
      return array('ok' => 1, 'deleted' => $deleted, 'errors' => array());
   }

   /** @return array{id:int,type:string,target_folder:int,sorter_updates:int} */
   public function moveNode(string $type, int $id, int $target, int $beforeId = 0, int $afterId = 0): array {
      $dd = $type === 'folder' ? dbxContentLng::ddFolder() : dbxContentLng::ddContent();
      $result = $this->transaction($dd, 'cms_move_node', 'Tree-Verschiebung fehlgeschlagen', function() use (
         $type, $id, $target, $beforeId, $afterId
      ): array {
         if ($type === 'folder' && $this->folderIsDescendant($target, $id)) {
            throw new \InvalidArgumentException('Ordner kann nicht in einen eigenen Unterordner verschoben werden.');
         }
         $isFolder = $type === 'folder';
         $dd = $isFolder ? dbxContentLng::ddFolder() : dbxContentLng::ddContent();
         $parentField = $isFolder ? 'parent_id' : 'folder';
         $current = $this->db->select1($dd, $id, 'id,' . $parentField . ',sorter', 0);
         if (!is_array($current)) throw new \RuntimeException('Tree-Eintrag nicht gefunden.');
         $oldParent = (int)($current[$parentField] ?? 0);
         $data = array();
         if ($oldParent !== $target) {
            $data[$parentField] = $target;
            if ($beforeId <= 0 && $afterId <= 0) $data['sorter'] = $this->nextSorter($dd, $parentField, $target, $isFolder ? 'name' : 'title');
         }
         if ($data && $this->db->update($dd, $data, $id) !== 1) throw new \RuntimeException('Tree-Eintrag konnte nicht gespeichert werden.');
         $changed = ($beforeId > 0 || $afterId > 0)
            ? $this->reorderSiblings($dd, $parentField, $target, $isFolder ? 'name' : 'title', $id, $beforeId, $afterId)
            : array();
         return array('id' => $id, 'type' => $type, 'target_folder' => $target, 'old_parent' => $oldParent, 'changed' => $changed);
      });
      $this->flushTreeMoveCache($type, $id, $target, (int)$result['old_parent'], (array)$result['changed']);
      return array('id' => $id, 'type' => $type, 'target_folder' => $target, 'sorter_updates' => count($result['changed']));
   }

   private function folderIsDescendant(int $folderId, int $ancestorId): bool {
      for ($guard = 0; $folderId > 0 && $guard < 100; $guard++) {
         if ($folderId === $ancestorId) return true;
         $row = $this->db->select1(dbxContentLng::ddFolder(), $folderId, 'parent_id', 0);
         if (!is_array($row)) return false;
         $folderId = (int)($row['parent_id'] ?? 0);
      }
      return false;
   }

   private function nextSorter(string $dd, string $parentField, int $parentId, string $titleField): string {
      $rows = $this->db->select($dd, $parentField . ' = ' . $parentId, 'sorter,' . $titleField, 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = is_array($rows) && isset($rows[0]) ? (int)($rows[0]['sorter'] ?? 0) : 0;
      return sprintf('%04d', $max + 10);
   }

   private function reorderSiblings(
      string $dd,
      string $parentField,
      int $parentId,
      string $titleField,
      int $movedId,
      int $beforeId,
      int $afterId
   ): array {
      $rows = $this->db->select($dd, $parentField . ' = ' . $parentId, 'id,sorter,' . $titleField, 'sorter,' . $titleField . ',id', 'ASC', '', 0, 0, 0);
      $plan = dbxContentTreeOrder::plan(is_array($rows) ? $rows : array(), $movedId, $beforeId, $afterId);
      foreach ($plan['updates'] as $rowId => $sorter) {
         if ($this->db->update($dd, array('sorter' => $sorter), (int)$rowId) !== 1) throw new \RuntimeException('Tree-Sortierung fehlgeschlagen.');
      }
      return array_map('intval', array_keys($plan['updates']));
   }

   public function languageSaveResponse(string $entity, int $id, array $syncResult): array {
      $isMaster = dbxContentLngSync::isMasterLng();
      return array(
         'lng_forked' => $isMaster ? 0 : 1,
         'lng_synced' => count($syncResult['updated'] ?? array()),
         'lng_media_copied' => (int)($syncResult['media_copied'] ?? 0),
         'lng_translate_provider' => dbxContentTranslate::provider(),
         'lng_sync_targets' => $isMaster ? dbxContentLngSync::slaveLngs() : array(),
         'lng_sync_updated' => is_array($syncResult['updated'] ?? null) ? $syncResult['updated'] : array(),
         'lng_sync_skipped' => is_array($syncResult['skipped'] ?? null) ? $syncResult['skipped'] : array(),
         'lng_sync_errors' => is_array($syncResult['errors'] ?? null) ? $syncResult['errors'] : array(),
         'translate_warnings' => is_array($syncResult['translate_warnings'] ?? null) ? $syncResult['translate_warnings'] : array(),
         'open_lng_provision' => $this->languageProvisionOpenFlag($entity, $id),
      );
   }

   public function languageProvisionOpenFlag(string $entity, int $id): int {
      if (!dbxContentLngSync::isMasterLng() || count(dbxContentLngSync::slaveLngs()) <= 0) return 0;
      return $id <= 0 || dbxContentLngSync::hasMissingSlaveLng($this->db, $entity, $id) ? 1 : 0;
   }

   private function loadCacheClasses(): void { dbx()->load_content_cache_classes(); }

   private function syncPermalinkIndex(int $cid, string $lng = ''): void {
      if ($cid <= 0) return;
      $this->loadCacheClasses();
      $lng = strtolower(trim($lng));
      $previous = null;
      if ($lng !== '' && $lng !== dbxContentLng::current()) {
         $previous = dbx()->get_system_var('dbx_lng', 'de');
         dbx()->set_system_var('dbx_lng', $lng);
      }
      try {
         $record = $this->db->select1(dbxContentLng::ddContent(), $cid, 'permalink,activ,folder,title,seo_title,description,keywords,meta_robots,seo_image_id,update_date,lng_uid', 0);
         if (!is_array($record)) return;
         if ((int)($record['activ'] ?? 0) !== 1) {
            dbxContentPermalinkIndex::removeByCid($cid, dbxContentLng::current());
            if (class_exists('dbx\\dbxContent\\dbxContentSitemap')) \dbx\dbxContent\dbxContentSitemap::invalidate();
            return;
         }
         $renderer = new dbxContentRenderer();
         $rights = $renderer->getPublicFolderRights((int)($record['folder'] ?? 0));
         $permalink = (string)($record['permalink'] ?? '');
         dbxContentPageCache::writePageMeta($cid, array(
            'cid' => $cid,
            'permalink' => $permalink,
            'rights' => $rights,
            'activ' => (int)($record['activ'] ?? 1),
            'saved_at' => date('c'),
            'seo' => dbxContentRenderer::seoMetaFromRecord($record),
         ));
         if (trim($permalink) !== '') dbxContentPermalinkIndex::upsertPage($cid, $permalink, $rights, (int)($record['activ'] ?? 1), dbxContentLng::current());
         dbxContentHome::refreshHomeCache($this->db, $cid, dbxContentLng::current());
         if (class_exists('dbx\\dbxContent\\dbxContentSitemap')) \dbx\dbxContent\dbxContentSitemap::invalidate();
      } finally {
         if ($previous !== null) dbx()->set_system_var('dbx_lng', $previous);
      }
   }

   public function flushLngSyncCache(array $syncResult, bool $flushMenus = true): void {
      $updated = is_array($syncResult['updated'] ?? null) ? $syncResult['updated'] : array();
      if (!$updated) return;
      $this->loadCacheClasses();
      $folderChanged = false;
      foreach ($updated as $item) {
         $id = (int)($item['id'] ?? 0);
         if ($id <= 0) continue;
         if (($item['entity'] ?? 'page') === 'page') {
            dbxContentPageCache::invalidateContent($id);
            if (!empty($item['lng'])) $this->syncPermalinkIndex($id, (string)$item['lng']);
         } else {
            $folderChanged = true;
         }
      }
      if ($flushMenus && ($folderChanged || $updated)) dbxContentPageCache::invalidateAllMenus();
   }

   public function flushSavedPageCache(int $cid): void {
      if ($cid <= 0) return;
      $this->loadCacheClasses();
      dbxContentPageCache::invalidateContent($cid);
      dbxContentPageCache::invalidateAllMenus();
      $this->syncPermalinkIndex($cid);
   }

   public function flushDeletedPageCache(int $cid, string $lng = '', bool $flushMenus = true): void {
      if ($cid <= 0) return;
      $this->loadCacheClasses();
      $lng = strtolower(trim($lng)) ?: dbxContentLng::current();
      dbxContentPageCache::invalidateContent($cid);
      if ($flushMenus) dbxContentPageCache::invalidateAllMenus();
      dbxContentPermalinkIndex::removeByCid($cid, $lng);
   }

   public function flushDeletedFolderCache(int $folderId, string $lng = '', bool $flushMenus = true): void {
      if ($folderId < 0) return;
      $this->loadCacheClasses();
      $previous = null;
      $lng = strtolower(trim($lng));
      if ($lng !== '' && $lng !== dbxContentLng::current()) {
         $previous = dbx()->get_system_var('dbx_lng', 'de');
         dbx()->set_system_var('dbx_lng', $lng);
      }
      try {
         dbxContentPageCache::invalidateFolderTree($this->db, $folderId);
         if ($flushMenus) dbxContentPageCache::invalidateAllMenus();
      } finally {
         if ($previous !== null) dbx()->set_system_var('dbx_lng', $previous);
      }
   }

   public function flushFolderCache(int $folderId): void {
      if ($folderId < 0) return;
      $this->loadCacheClasses();
      dbxContentPageCache::invalidateFolderTree($this->db, $folderId);
      dbxContentPageCache::invalidateAllMenus();
   }

   public function flushSavedFolderCache(int ...$folderIds): void {
      $ids = array_values(array_unique(array_filter(array_map('intval', $folderIds), static fn(int $id): bool => $id >= 0)));
      $this->loadCacheClasses();
      foreach ($ids as $folderId) dbxContentPageCache::invalidateFolderTree($this->db, $folderId);
      dbxContentPageCache::invalidateAllMenus();
   }

   public function flushMenuCache(): void {
      $this->loadCacheClasses();
      dbxContentPageCache::invalidateAllMenus();
   }

   public function flushTreeMoveCache(string $type, int $id, int $target, int $oldParent, array $changed): void {
      $this->loadCacheClasses();
      if ($type === 'page') {
         foreach (array_values(array_unique(array_filter(array_merge(array($id), array_map('intval', $changed))))) as $contentId) {
            dbxContentPageCache::invalidateContent((int)$contentId);
         }
      } else {
         foreach (array_values(array_unique(array_filter(array($id, $target, $oldParent), static fn($folderId): bool => (int)$folderId >= 0))) as $folderId) {
            dbxContentPageCache::invalidateFolderTree($this->db, (int)$folderId);
         }
      }
      dbxContentPageCache::invalidateAllMenus();
   }

   public function flushMediaCache(int $contentId = 0, int $folderId = 0): void {
      if ($contentId <= 0 && $folderId <= 0) return;
      $this->loadCacheClasses();
      if ($contentId > 0) dbxContentPageCache::invalidateContent($contentId);
      if ($folderId > 0) dbxContentPageCache::invalidateFolderTree($this->db, $folderId);
      dbxContentPageCache::invalidateAllMenus();
   }

   public function flushMediaByMediaId(int $mediaId): void {
      if ($mediaId <= 0) return;
      $rows = $this->db->select($this->mediaUsageDd, 'media_id = ' . $mediaId . ' AND active = 1', '*', 'id', 'ASC', '', 0, 0, 0);
      foreach (is_array($rows) ? $rows : array() as $usage) {
         $this->flushMediaCache((int)($usage['content_id'] ?? 0), (int)($usage['folder_id'] ?? 0));
      }
   }
}
