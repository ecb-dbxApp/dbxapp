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
      private string $media_dd = 'dbxMedia',
      private string $media_usage_dd = 'dbxMediaUsage'
   ) {}

   /** Führt eine Mutation atomar aus und lokalisiert Fehler im Systemlog. */
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
   public function save_page(
      array $data,
      int $id,
      array $payload,
      callable $sync_hero,
      callable $sync_inline,
      callable $sync_language_media
   ): array {
      $dd = dbxContentLng::dd_content();
      $result = $this->transaction($dd, 'cms_save_page', 'CMS-Seite konnte nicht atomar gespeichert werden', function() use (
         $dd, $data, $id, $payload, $sync_hero, $sync_inline, $sync_language_media
      ): array {
         $ok = $id > 0 ? $this->db->update($dd, $data, $id) : $this->db->insert($dd, $data);
         $saved_id = $id > 0 ? $id : (($ok === 1) ? (int)$this->db->get_insert_id() : 0);
         if ($ok !== 1 || $saved_id <= 0) throw new \RuntimeException('content write failed');

         $sync_hero($this->db, $saved_id, 0, $data['hero_image_id'] ?? 'parent');
         dbxContentLngSync::after_page_save($this->db, $saved_id, $id <= 0);
         $sync_result = array('updated' => array(), 'skipped' => array(), 'errors' => array());
         if ($id > 0 && dbxContentLngSync::is_master_lng()) {
            dbxContentTranslate::clear_warnings();
            $sync_result = dbxContentLngSync::sync_slaves_from_master($this->db, 'page', $saved_id);
            $sync_result['media_copied'] = (int)$sync_language_media($this->db, $saved_id, $sync_result);
            $sync_result['translate_warnings'] = dbxContentTranslate::warnings();
         }
         $inline_provided = array_key_exists('inline_media_ids', $payload) && is_array($payload['inline_media_ids']);
         $inline_sync = $sync_inline(
            $this->db,
            $saved_id,
            (string)($data['content'] ?? ''),
            (int)($data['folder'] ?? 0),
            $inline_provided ? $payload['inline_media_ids'] : null,
            $inline_provided
         );
         return array('saved_id' => $saved_id, 'sync_result' => $sync_result, 'inline_sync' => $inline_sync);
      });

      $this->flush_lng_sync_cache((array)$result['sync_result'], false);
      $this->flush_saved_page_cache((int)$result['saved_id']);
      return $result;
   }

   public function create_page(array $data): int {
      $dd = dbxContentLng::dd_content();
      $id = (int)$this->transaction($dd, 'cms_new_page', 'CMS-Seite konnte nicht atomar angelegt werden', function() use ($dd, $data): int {
         if ($this->db->insert($dd, $data, 0, 1, 0, 1) !== 1) throw new \RuntimeException('page insert failed');
         $id = (int)$this->db->get_insert_id();
         if ($id <= 0) throw new \RuntimeException('page insert id missing');
         dbxContentLngSync::after_page_save($this->db, $id, true);
         return $id;
      });
      $this->flush_saved_page_cache($id);
      return $id;
   }

   /** @return array{new_id:int,media_copied:int,inline_sync:array} */
   public function duplicate_page(array $copy, callable $copy_media, callable $sync_inline): array {
      $dd = dbxContentLng::dd_content();
      $result = $this->transaction($dd, 'cms_duplicate_page', 'CMS-Seite konnte nicht atomar dupliziert werden', function() use (
         $dd, $copy, $copy_media, $sync_inline
      ): array {
         if ($this->db->insert($dd, $copy, 0, 1, 0, 1) !== 1) throw new \RuntimeException('page duplicate insert failed');
         $new_id = (int)$this->db->get_insert_id();
         if ($new_id <= 0) throw new \RuntimeException('page duplicate id missing');
         dbxContentLngSync::after_page_save($this->db, $new_id, true);
         $media_copied = (int)$copy_media($this->db, $new_id);
         $inline_sync = $sync_inline($this->db, $new_id);
         return array('new_id' => $new_id, 'media_copied' => $media_copied, 'inline_sync' => $inline_sync);
      });
      $this->flush_saved_page_cache((int)$result['new_id']);
      return $result;
   }

   /** @return array{saved_id:int,sync_result:array} */
   public function save_folder(array $data, int $id, callable $sync_hero): array {
      $dd = dbxContentLng::dd_folder();
      $result = $this->transaction($dd, 'cms_save_folder', 'CMS-Ordner konnte nicht atomar gespeichert werden', function() use (
         $dd, $data, $id, $sync_hero
      ): array {
         $ok = $id > 0 ? $this->db->update($dd, $data, $id) : $this->db->insert($dd, $data);
         $saved_id = $id > 0 ? $id : (($ok === 1) ? (int)$this->db->get_insert_id() : 0);
         if ($ok !== 1 || $saved_id <= 0) throw new \RuntimeException('folder write failed');
         $sync_hero($this->db, 0, $saved_id, $data['hero_image_id'] ?? 'parent');
         dbxContentLngSync::after_folder_save($this->db, $saved_id, $id <= 0);
         $sync_result = array('updated' => array(), 'skipped' => array(), 'errors' => array());
         if ($id > 0 && dbxContentLngSync::is_master_lng()) {
            dbxContentTranslate::clear_warnings();
            $sync_result = dbxContentLngSync::sync_slaves_from_master($this->db, 'folder', $saved_id);
            $sync_result['translate_warnings'] = dbxContentTranslate::warnings();
         }
         return array('saved_id' => $saved_id, 'sync_result' => $sync_result);
      });
      $this->flush_lng_sync_cache((array)$result['sync_result'], false);
      return $result;
   }

   public function create_folder(array $data, int $parent_id): int {
      $dd = dbxContentLng::dd_folder();
      $id = (int)$this->transaction($dd, 'cms_new_folder', 'CMS-Ordner konnte nicht atomar angelegt werden', function() use ($dd, $data): int {
         if ($this->db->insert($dd, $data) !== 1) throw new \RuntimeException('folder insert failed');
         $id = (int)$this->db->get_insert_id();
         if ($id <= 0) throw new \RuntimeException('folder insert id missing');
         dbxContentLngSync::after_folder_save($this->db, $id, true);
         return $id;
      });
      $this->flush_saved_folder_cache($id, $parent_id);
      return $id;
   }

   /** Loescht alle gewaehlten Sprachversionen gemeinsam oder keine. */
   public function delete_page(int $id, array $languages): array {
      $targets = dbxContentLngSync::resolve_delete_ids($this->db, 'page', $id, $languages);
      if (!$targets) return array('ok' => 0, 'deleted' => array(), 'errors' => array('Keine loeschbare Sprachversion gefunden.'));
      try {
         $deleted = $this->transaction(dbxContentLng::dd_content(), 'cms_delete_page', 'CMS-Seite konnte nicht atomar geloescht werden', function() use ($targets): array {
            $deleted = array();
            foreach ($targets as $target) {
               $lng = (string)($target['lng'] ?? '');
               $target_id = (int)($target['id'] ?? 0);
               if ($target_id <= 0) continue;
               if ($this->db->delete(dbxContentLng::dd_content($lng), 'id = ' . $target_id, 1, 0) !== 1) {
                  throw new \RuntimeException('delete page ' . $target_id . ' in ' . $lng . ' failed');
               }
               if ($this->db->update($this->media_dd, array('active' => 1, 'content_id' => 0, 'folder_id' => 0), 'content_id = ' . $target_id, 0, 1, 1, 0) !== 1) {
                  throw new \RuntimeException('release page media ' . $target_id . ' failed');
               }
               if ($this->db->update(
                  $this->media_usage_dd,
                  array('active' => 0),
                  dbxContentMediaUsageScope::with_language('content_id = ' . $target_id . ' AND active = 1', $lng),
                  0, 1, 1, 0
               ) !== 1) {
                  throw new \RuntimeException('deactivate page media usage ' . $target_id . ' failed');
               }
               $deleted[] = array('lng' => $lng, 'id' => $target_id);
            }
            if (!$deleted) throw new \RuntimeException('no page deleted');
            return $deleted;
         });
      } catch (\Throwable $e) {
         return array('ok' => 0, 'deleted' => array(), 'errors' => array('Loeschen fehlgeschlagen: ' . $e->getMessage()));
      }
      foreach ($deleted as $target) $this->flush_deleted_page_cache((int)$target['id'], (string)$target['lng'], false);
      dbxContentPageCache::invalidate_all_menus();
      return array('ok' => 1, 'deleted' => $deleted, 'errors' => array());
   }

   /** Loescht nur vollstaendig leere Ordner und alle Sprachen atomar. */
   public function delete_folder(int $id, array $languages): array {
      $targets = dbxContentLngSync::resolve_delete_ids($this->db, 'folder', $id, $languages);
      $errors = array();
      foreach ($targets as $target) {
         $lng = (string)($target['lng'] ?? '');
         $target_id = (int)($target['id'] ?? 0);
         if ($target_id <= 0) continue;
         $check = dbxContentLngSync::folder_deletable($this->db, $lng, $target_id);
         if ((int)($check['deletable'] ?? 0) !== 1) {
            $reason = trim((string)($check['reason'] ?? ''));
            $errors[] = strtoupper($lng) . ': ' . ($reason !== '' ? $reason : 'Ordner kann nicht geloescht werden.');
         }
      }
      if ($errors || !$targets) return array('ok' => 0, 'deleted' => array(), 'errors' => $errors ?: array('Keine loeschbare Sprachversion gefunden.'));
      try {
         $deleted = $this->transaction(dbxContentLng::dd_folder(), 'cms_delete_folder', 'CMS-Ordner konnte nicht atomar geloescht werden', function() use ($targets): array {
            $deleted = array();
            foreach ($targets as $target) {
               $lng = (string)($target['lng'] ?? '');
               $target_id = (int)($target['id'] ?? 0);
               if ($target_id <= 0) continue;
               if ($this->db->delete(dbxContentLng::dd_folder($lng), 'id = ' . $target_id, 1, 0) !== 1) {
                  throw new \RuntimeException('delete folder ' . $target_id . ' in ' . $lng . ' failed');
               }
               if ($this->db->update(
                  $this->media_usage_dd,
                  array('active' => 0),
                  dbxContentMediaUsageScope::with_language('folder_id = ' . $target_id . ' AND content_id = 0 AND active = 1', $lng),
                  0, 1, 1, 0
               ) !== 1) {
                  throw new \RuntimeException('deactivate folder media usage ' . $target_id . ' failed');
               }
               $deleted[] = array('lng' => $lng, 'id' => $target_id);
            }
            if (!$deleted) throw new \RuntimeException('no folder deleted');
            return $deleted;
         });
      } catch (\Throwable $e) {
         return array('ok' => 0, 'deleted' => array(), 'errors' => array('Loeschen fehlgeschlagen: ' . $e->getMessage()));
      }
      foreach ($deleted as $target) $this->flush_deleted_folder_cache((int)$target['id'], (string)$target['lng'], false);
      dbxContentPageCache::invalidate_all_menus();
      return array('ok' => 1, 'deleted' => $deleted, 'errors' => array());
   }

   /** @return array{id:int,type:string,target_folder:int,sorter_updates:int} */
   public function move_node(string $type, int $id, int $target, int $before_id = 0, int $after_id = 0): array {
      $dd = $type === 'folder' ? dbxContentLng::dd_folder() : dbxContentLng::dd_content();
      $result = $this->transaction($dd, 'cms_move_node', 'Tree-Verschiebung fehlgeschlagen', function() use (
         $type, $id, $target, $before_id, $after_id
      ): array {
         if ($type === 'folder' && $this->folder_is_descendant($target, $id)) {
            throw new \InvalidArgumentException('Ordner kann nicht in einen eigenen Unterordner verschoben werden.');
         }
         $is_folder = $type === 'folder';
         $dd = $is_folder ? dbxContentLng::dd_folder() : dbxContentLng::dd_content();
         $parent_field = $is_folder ? 'parent_id' : 'folder';
         $current = $this->db->select1($dd, $id, 'id,' . $parent_field . ',sorter', 0);
         if (!is_array($current)) throw new \RuntimeException('Tree-Eintrag nicht gefunden.');
         $old_parent = (int)($current[$parent_field] ?? 0);
         $data = array();
         if ($old_parent !== $target) {
            $data[$parent_field] = $target;
            if ($before_id <= 0 && $after_id <= 0) $data['sorter'] = $this->next_sorter($dd, $parent_field, $target, $is_folder ? 'name' : 'title');
         }
         if ($data && $this->db->update($dd, $data, $id) !== 1) throw new \RuntimeException('Tree-Eintrag konnte nicht gespeichert werden.');
         $changed = ($before_id > 0 || $after_id > 0)
            ? $this->reorder_siblings($dd, $parent_field, $target, $is_folder ? 'name' : 'title', $id, $before_id, $after_id)
            : array();
         return array('id' => $id, 'type' => $type, 'target_folder' => $target, 'old_parent' => $old_parent, 'changed' => $changed);
      });
      $this->flush_tree_move_cache($type, $id, $target, (int)$result['old_parent'], (array)$result['changed']);
      return array('id' => $id, 'type' => $type, 'target_folder' => $target, 'sorter_updates' => count($result['changed']));
   }

   private function folder_is_descendant(int $folder_id, int $ancestor_id): bool {
      for ($guard = 0; $folder_id > 0 && $guard < 100; $guard++) {
         if ($folder_id === $ancestor_id) return true;
         $row = $this->db->select1(dbxContentLng::dd_folder(), $folder_id, 'parent_id', 0);
         if (!is_array($row)) return false;
         $folder_id = (int)($row['parent_id'] ?? 0);
      }
      return false;
   }

   private function next_sorter(string $dd, string $parent_field, int $parent_id, string $title_field): string {
      $rows = $this->db->select($dd, $parent_field . ' = ' . $parent_id, 'sorter,' . $title_field, 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = is_array($rows) && isset($rows[0]) ? (int)($rows[0]['sorter'] ?? 0) : 0;
      return sprintf('%04d', $max + 10);
   }

   private function reorder_siblings(
      string $dd,
      string $parent_field,
      int $parent_id,
      string $title_field,
      int $moved_id,
      int $before_id,
      int $after_id
   ): array {
      $rows = $this->db->select($dd, $parent_field . ' = ' . $parent_id, 'id,sorter,' . $title_field, 'sorter,' . $title_field . ',id', 'ASC', '', 0, 0, 0);
      $plan = dbxContentTreeOrder::plan(is_array($rows) ? $rows : array(), $moved_id, $before_id, $after_id);
      foreach ($plan['updates'] as $row_id => $sorter) {
         if ($this->db->update($dd, array('sorter' => $sorter), (int)$row_id) !== 1) throw new \RuntimeException('Tree-Sortierung fehlgeschlagen.');
      }
      return array_map('intval', array_keys($plan['updates']));
   }

   public function language_save_response(string $entity, int $id, array $sync_result): array {
      $is_master = dbxContentLngSync::is_master_lng();
      return array(
         'lng_forked' => $is_master ? 0 : 1,
         'lng_synced' => count($sync_result['updated'] ?? array()),
         'lng_media_copied' => (int)($sync_result['media_copied'] ?? 0),
         'lng_translate_provider' => dbxContentTranslate::provider(),
         'lng_sync_targets' => $is_master ? dbxContentLngSync::slave_lngs() : array(),
         'lng_sync_updated' => is_array($sync_result['updated'] ?? null) ? $sync_result['updated'] : array(),
         'lng_sync_skipped' => is_array($sync_result['skipped'] ?? null) ? $sync_result['skipped'] : array(),
         'lng_sync_errors' => is_array($sync_result['errors'] ?? null) ? $sync_result['errors'] : array(),
         'translate_warnings' => is_array($sync_result['translate_warnings'] ?? null) ? $sync_result['translate_warnings'] : array(),
         'open_lng_provision' => $this->language_provision_open_flag($entity, $id),
      );
   }

   public function language_provision_open_flag(string $entity, int $id): int {
      if (!dbxContentLngSync::is_master_lng() || count(dbxContentLngSync::slave_lngs()) <= 0) return 0;
      return $id <= 0 || dbxContentLngSync::has_missing_slave_lng($this->db, $entity, $id) ? 1 : 0;
   }

   private function load_cache_classes(): void { dbx()->load_content_cache_classes(); }

   private function sync_permalink_index(int $cid, string $lng = ''): void {
      if ($cid <= 0) return;
      $this->load_cache_classes();
      $lng = strtolower(trim($lng));
      $previous = null;
      if ($lng !== '' && $lng !== dbxContentLng::current()) {
         $previous = dbx()->get_system_var('dbx_lng', 'de');
         dbx()->set_system_var('dbx_lng', $lng);
      }
      try {
         $record = $this->db->select1(dbxContentLng::dd_content(), $cid, 'permalink,activ,folder,title,seo_title,description,keywords,meta_robots,seo_image_id,update_date,lng_uid', 0);
         if (!is_array($record)) return;
         if ((int)($record['activ'] ?? 0) !== 1) {
            dbxContentPermalinkIndex::remove_by_cid($cid, dbxContentLng::current());
            if (class_exists('dbx\\dbxContent\\dbxContentSitemap')) \dbx\dbxContent\dbxContentSitemap::invalidate();
            return;
         }
         $renderer = new dbxContentRenderer();
         $rights = $renderer->get_public_folder_rights((int)($record['folder'] ?? 0));
         $permalink = (string)($record['permalink'] ?? '');
         dbxContentPageCache::write_page_meta($cid, array(
            'cid' => $cid,
            'permalink' => $permalink,
            'rights' => $rights,
            'activ' => (int)($record['activ'] ?? 1),
            'saved_at' => date('c'),
            'seo' => dbxContentRenderer::seo_meta_from_record($record),
         ));
         if (trim($permalink) !== '') dbxContentPermalinkIndex::upsert_page($cid, $permalink, $rights, (int)($record['activ'] ?? 1), dbxContentLng::current());
         dbxContentHome::refresh_home_cache($this->db, $cid, dbxContentLng::current());
         if (class_exists('dbx\\dbxContent\\dbxContentSitemap')) \dbx\dbxContent\dbxContentSitemap::invalidate();
      } finally {
         if ($previous !== null) dbx()->set_system_var('dbx_lng', $previous);
      }
   }

   public function flush_lng_sync_cache(array $sync_result, bool $flush_menus = true): void {
      $updated = is_array($sync_result['updated'] ?? null) ? $sync_result['updated'] : array();
      if (!$updated) return;
      $this->load_cache_classes();
      $folder_changed = false;
      foreach ($updated as $item) {
         $id = (int)($item['id'] ?? 0);
         if ($id <= 0) continue;
         if (($item['entity'] ?? 'page') === 'page') {
            dbxContentPageCache::invalidate_content($id);
            if (!empty($item['lng'])) $this->sync_permalink_index($id, (string)$item['lng']);
         } else {
            $folder_changed = true;
         }
      }
      if ($flush_menus && ($folder_changed || $updated)) dbxContentPageCache::invalidate_all_menus();
   }

   public function flush_saved_page_cache(int $cid): void {
      if ($cid <= 0) return;
      $this->load_cache_classes();
      dbxContentPageCache::invalidate_content($cid);
      dbxContentPageCache::invalidate_all_menus();
      $this->sync_permalink_index($cid);
   }

   public function flush_deleted_page_cache(int $cid, string $lng = '', bool $flush_menus = true): void {
      if ($cid <= 0) return;
      $this->load_cache_classes();
      $lng = strtolower(trim($lng)) ?: dbxContentLng::current();
      dbxContentPageCache::invalidate_content($cid);
      if ($flush_menus) dbxContentPageCache::invalidate_all_menus();
      dbxContentPermalinkIndex::remove_by_cid($cid, $lng);
   }

   public function flush_deleted_folder_cache(int $folder_id, string $lng = '', bool $flush_menus = true): void {
      if ($folder_id < 0) return;
      $this->load_cache_classes();
      $previous = null;
      $lng = strtolower(trim($lng));
      if ($lng !== '' && $lng !== dbxContentLng::current()) {
         $previous = dbx()->get_system_var('dbx_lng', 'de');
         dbx()->set_system_var('dbx_lng', $lng);
      }
      try {
         dbxContentPageCache::invalidate_folder_tree($this->db, $folder_id);
         if ($flush_menus) dbxContentPageCache::invalidate_all_menus();
      } finally {
         if ($previous !== null) dbx()->set_system_var('dbx_lng', $previous);
      }
   }

   public function flush_folder_cache(int $folder_id): void {
      if ($folder_id < 0) return;
      $this->load_cache_classes();
      dbxContentPageCache::invalidate_folder_tree($this->db, $folder_id);
      dbxContentPageCache::invalidate_all_menus();
   }

   public function flush_saved_folder_cache(int ...$folder_ids): void {
      $ids = array_values(array_unique(array_filter(array_map('intval', $folder_ids), static fn(int $id): bool => $id >= 0)));
      $this->load_cache_classes();
      foreach ($ids as $folder_id) dbxContentPageCache::invalidate_folder_tree($this->db, $folder_id);
      dbxContentPageCache::invalidate_all_menus();
   }

   public function flush_menu_cache(): void {
      $this->load_cache_classes();
      dbxContentPageCache::invalidate_all_menus();
   }

   public function flush_tree_move_cache(string $type, int $id, int $target, int $old_parent, array $changed): void {
      $this->load_cache_classes();
      if ($type === 'page') {
         foreach (array_values(array_unique(array_filter(array_merge(array($id), array_map('intval', $changed))))) as $content_id) {
            dbxContentPageCache::invalidate_content((int)$content_id);
         }
      } else {
         foreach (array_values(array_unique(array_filter(array($id, $target, $old_parent), static fn($folder_id): bool => (int)$folder_id >= 0))) as $folder_id) {
            dbxContentPageCache::invalidate_folder_tree($this->db, (int)$folder_id);
         }
      }
      dbxContentPageCache::invalidate_all_menus();
   }

   public function flush_media_cache(int $content_id = 0, int $folder_id = 0): void {
      if ($content_id <= 0 && $folder_id <= 0) return;
      $this->load_cache_classes();
      if ($content_id > 0) dbxContentPageCache::invalidate_content($content_id);
      if ($folder_id > 0) dbxContentPageCache::invalidate_folder_tree($this->db, $folder_id);
      dbxContentPageCache::invalidate_all_menus();
   }

   public function flush_media_by_media_id(int $media_id): void {
      if ($media_id <= 0) return;
      $rows = $this->db->select($this->media_usage_dd, 'media_id = ' . $media_id . ' AND active = 1', '*', 'id', 'ASC', '', 0, 0, 0);
      foreach (is_array($rows) ? $rows : array() as $usage) {
         $this->flush_media_cache((int)($usage['content_id'] ?? 0), (int)($usage['folder_id'] ?? 0));
      }
   }
}
