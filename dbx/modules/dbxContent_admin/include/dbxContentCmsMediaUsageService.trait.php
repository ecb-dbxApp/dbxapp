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
use dbx\dbxContent\dbxContent_permalink;

/**
 * Medien-Usage, verwaiste Referenzen, Abgleich und DB-Persistenz ausschliesslich ueber dbxDB.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsMediaUsageServiceTrait {


   private function active_media_usage_map($db) {
      $map = array();
      if (!is_object($db)) return $map;
      $rows = $db->select($this->dd_media_usage, 'active = 1', 'media_id,content_id,folder_id,content_lng,slot', 'id', 'ASC', '', 0, 0, 0);
      if (is_array($rows)) {
         foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $media_id = (int)($row['media_id'] ?? 0);
            if ($media_id <= 0) continue;
            if (!isset($map[$media_id])) $map[$media_id] = array();
            $map[$media_id][] = $row;
         }
      }

      foreach ($this->ordered_media_languages() as $lng) {
         $content_rows = $db->select(dbxContentLng::ddContent($lng), '', 'id,folder,hero_image_id,seo_image_id,content', 'id');
         foreach (is_array($content_rows) ? $content_rows : array() as $row) {
            if (!is_array($row)) continue;
            $content_id = (int)($row['id'] ?? 0);
            $folder_id = (int)($row['folder'] ?? 0);
            $direct = array(
               'hero' => (int)($row['hero_image_id'] ?? 0),
               'seo' => (int)($row['seo_image_id'] ?? 0),
            );
            foreach ($direct as $slot => $media_id) {
               if ($media_id <= 0) continue;
               if (!isset($map[$media_id])) $map[$media_id] = array();
               $map[$media_id][] = array('media_id' => $media_id, 'content_id' => $content_id, 'folder_id' => $folder_id, 'content_lng' => $lng, 'slot' => $slot);
            }
            foreach ($this->inline_media_ids($row['content'] ?? '') as $media_id) {
               $media_id = (int)$media_id;
               if ($media_id <= 0) continue;
               if (!isset($map[$media_id])) $map[$media_id] = array();
               $map[$media_id][] = array('media_id' => $media_id, 'content_id' => $content_id, 'folder_id' => $folder_id, 'content_lng' => $lng, 'slot' => 'inline');
            }
         }
         $folder_rows = $db->select(dbxContentLng::ddFolder($lng), '', 'id,hero_image_id', 'id');
         foreach (is_array($folder_rows) ? $folder_rows : array() as $row) {
            $media_id = is_array($row) ? (int)($row['hero_image_id'] ?? 0) : 0;
            $folder_id = is_array($row) ? (int)($row['id'] ?? 0) : 0;
            if ($media_id <= 0 || $folder_id <= 0) continue;
            if (!isset($map[$media_id])) $map[$media_id] = array();
            $map[$media_id][] = array('media_id' => $media_id, 'content_id' => 0, 'folder_id' => $folder_id, 'content_lng' => $lng, 'slot' => 'hero');
         }
      }
      return $map;
   }



   /**
    * Ermittelt ausschliesslich Medienzuordnungen, deren Verweise nicht mehr
    * aufloesbar sind. Eine Seitenzuordnung darf zusaetzlich folder_id tragen;
    * in diesem Fall entscheidet die vorhandene Seite ueber die Gueltigkeit.
    *
    * @return array<int,array{row:array,reason:string}>
    */
   private function orphan_media_usage_rows($db): array {
      if (!is_object($db)) return array();

      $media_ids = array();
      $media_rows = $db->select($this->dd_media, '', 'id', 'id', 'ASC', '', 0, 0, 0);
      if (is_array($media_rows)) {
         foreach ($media_rows as $row) {
            $id = is_array($row) ? (int)($row['id'] ?? 0) : 0;
            if ($id > 0) $media_ids[$id] = 1;
         }
      }

      $content_ids = array();
      $folder_ids = array();
      foreach (dbxContentLngSync::accessibleLngs() as $lng) {
         try {
            $content_rows = $db->select(dbxContentLng::ddContent((string)$lng), '', 'id', 'id', 'ASC', '', 0, 0, 0);
            if (is_array($content_rows)) {
               foreach ($content_rows as $row) {
                  $id = is_array($row) ? (int)($row['id'] ?? 0) : 0;
                  if ($id > 0) $content_ids[$lng . ':' . $id] = 1;
               }
            }

            $folder_rows = $db->select(dbxContentLng::ddFolder((string)$lng), '', 'id', 'id', 'ASC', '', 0, 0, 0);
            if (is_array($folder_rows)) {
               foreach ($folder_rows as $row) {
                  $id = is_array($row) ? (int)($row['id'] ?? 0) : 0;
                  if ($id > 0) $folder_ids[$lng . ':' . $id] = 1;
               }
            }
         } catch (\Throwable $e) {
            dbx()->debug('dbxContent media usage check skipped lng=' . (string)$lng, $e->getMessage());
         }
      }

      $orphans = array();
      $usage_rows = $db->select($this->dd_media_usage, '', '*', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($usage_rows)) return $orphans;

      foreach ($usage_rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) continue;

         $media_id = (int)($row['media_id'] ?? 0);
         $content_id = (int)($row['content_id'] ?? 0);
         $folder_id = (int)($row['folder_id'] ?? 0);
         $content_lng = dbxContentMediaUsageScope::language((string)($row['content_lng'] ?? ''));
         $reason = '';

         if ($media_id <= 0 || !isset($media_ids[$media_id])) {
            $reason = 'media_missing';
         } elseif ($content_id > 0) {
            if (!isset($content_ids[$content_lng . ':' . $content_id])) $reason = 'content_missing';
         } elseif ($folder_id > 0) {
            if (!isset($folder_ids[$content_lng . ':' . $folder_id])) $reason = 'folder_missing';
         } else {
            $reason = 'target_missing';
         }

         if ($reason !== '') {
            $orphans[$id] = array('row' => $row, 'reason' => $reason);
         }
      }

      return $orphans;
   }



   private function cleanup_orphan_media_usage($db): array {
      $orphans = $this->orphan_media_usage_rows($db);
      $removed = 0;
      $reasons = array();
      $cache_targets = array();

      foreach ($orphans as $id => $item) {
         $row = is_array($item['row'] ?? null) ? $item['row'] : array();
         $reason = (string)($item['reason'] ?? 'unknown');
         $content_id = (int)($row['content_id'] ?? 0);
         $folder_id = (int)($row['folder_id'] ?? 0);
         $cache_targets[$content_id . ':' . $folder_id] = array($content_id, $folder_id);

         // Wartungsbereinigung ohne Trash-/Trace-Kopie: Der Datensatz ist
         // bereits ungueltig und soll durch VACUUM wirklich freigegeben werden.
         if ((int)$db->delete($this->dd_media_usage, (int)$id, 1, 0) === 1) {
            $removed++;
            $reasons[$reason] = (int)($reasons[$reason] ?? 0) + 1;
         }
      }

      foreach ($cache_targets as $target) {
         $this->flush_media_cache($db, (int)$target[0], (int)$target[1]);
      }

      return array(
         'found' => count($orphans),
         'removed' => $removed,
         'reasons' => $reasons,
      );
   }



   private function ordered_media_languages(): array {
      $languages = array_values(array_unique(array_map('strval', dbxContentLngSync::accessibleLngs())));
      $current = strtolower(trim((string)dbx()->get_system_var('dbx_lng', dbxContentLngSync::masterLng())));
      $ordered = array();
      if ($current !== '' && in_array($current, $languages, true)) $ordered[] = $current;
      foreach ($languages as $language) {
         $language = strtolower(trim($language));
         if ($language !== '' && !in_array($language, $ordered, true)) $ordered[] = $language;
      }
      return $ordered;
   }



   private function add_expected_media_usage(array &$expected, int $media_id, int $content_id, int $folder_id, string $slot, string $template = '', string $content_lng = '', string $caption = '', string $settings = ''): void {
      if ($media_id <= 0 || ($content_id <= 0 && $folder_id <= 0)) return;
      $content_lng = dbxContentMediaUsageScope::language($content_lng);
      $key = dbxContentMediaUsageMaintenance::usageKey($media_id, $content_id, $folder_id, $slot, $content_lng);
      if (!isset($expected[$key])) {
         $expected[$key] = array(
            'media_id' => $media_id,
            'content_id' => $content_id,
            'folder_id' => $folder_id,
            'slot' => $slot,
            'content_lng' => $content_lng,
            'template' => $template,
            'caption' => $caption,
            'settings' => $settings,
            'valid_folders' => array(),
         );
      }
      if ($content_id > 0 && $folder_id > 0) {
         $expected[$key]['valid_folders'][$folder_id] = 1;
      }
   }



   /**
    * Baut die Soll-Nutzung aus den tatsaechlichen Seiten- und Ordnerfeldern
    * aller verfuegbaren Sprachen. Shop-Zuordnungen werden aus der
    * autoritativen Artikelbild-Tabelle rekonstruiert.
    */
   private function media_usage_snapshot($db): array {
      $media_rows = $db->select($this->dd_media, '', '*', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($media_rows)) $media_rows = array();
      $valid_media_ids = array();
      foreach ($media_rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id > 0 && (int)($row['active'] ?? 0) === 1 && $this->media_file_exists($row)) {
            $valid_media_ids[$id] = 1;
         }
      }

      $expected = array();
      $content_folders = array();
      $folder_ids = array();
      $seo_media_ids = array();
      $seo_references = 0;
      foreach ($this->ordered_media_languages() as $lng) {
         try {
            $pages = $db->select(
               dbxContentLng::ddContent($lng),
               '',
               'id,folder,hero_image_id,seo_image_id,content',
               'id',
               'ASC',
               '',
               0,
               0,
               0
            );
            foreach (is_array($pages) ? $pages : array() as $page) {
               if (!is_array($page)) continue;
               $content_id = (int)($page['id'] ?? 0);
               $folder_id = (int)($page['folder'] ?? 0);
               if ($content_id <= 0) continue;
               $content_key = $lng . ':' . $content_id;
               if (!isset($content_folders[$content_key])) $content_folders[$content_key] = array();
               if ($folder_id > 0) $content_folders[$content_key][$folder_id] = 1;

               $hero_id = (int)($page['hero_image_id'] ?? 0);
               if (isset($valid_media_ids[$hero_id])) {
                  $this->add_expected_media_usage($expected, $hero_id, $content_id, $folder_id, 'hero', 'image-hero', $lng);
               }
               $seo_id = (int)($page['seo_image_id'] ?? 0);
               if (isset($valid_media_ids[$seo_id])) {
                  $seo_media_ids[$seo_id] = 1;
                  $seo_references++;
               }
               foreach ($this->inline_media_ids((string)($page['content'] ?? '')) as $media_id) {
                  $media_id = (int)$media_id;
                  if (isset($valid_media_ids[$media_id])) {
                     $this->add_expected_media_usage($expected, $media_id, $content_id, $folder_id, 'inline', 'image-inline', $lng);
                  }
               }
            }

            $folders = $db->select(
               dbxContentLng::ddFolder($lng),
               '',
               'id,hero_image_id',
               'id',
               'ASC',
               '',
               0,
               0,
               0
            );
            foreach (is_array($folders) ? $folders : array() as $folder) {
               if (!is_array($folder)) continue;
               $folder_id = (int)($folder['id'] ?? 0);
               if ($folder_id <= 0) continue;
               $folder_ids[$lng . ':' . $folder_id] = 1;
               $hero_id = (int)($folder['hero_image_id'] ?? 0);
               if (isset($valid_media_ids[$hero_id])) {
                  $this->add_expected_media_usage($expected, $hero_id, 0, $folder_id, 'hero', 'image-hero', $lng);
               }
            }
         } catch (\Throwable $e) {
            dbx()->debug('dbxContent media usage snapshot skipped lng=' . $lng, $e->getMessage());
         }
      }

      $controlled_slots = array('hero', 'inline');
      try {
         $master_lng = dbxContentMediaUsageScope::language(dbxContentLngSync::masterLng());
         $shop_page = $db->select1(
            dbxContentLng::ddContent($master_lng),
            array('permalink' => 'shop-medienverwendung'),
            'id,folder',
            0
         );
         if (!is_array($shop_page)) {
            $shop_page = $db->select1(
               dbxContentLng::ddContent($master_lng),
               array('permalink' => 'outside/shop-media-usage'),
               'id,folder',
               0
            );
         }
         $shop_content_id = is_array($shop_page) ? (int)($shop_page['id'] ?? 0) : 0;
         $shop_folder_id = is_array($shop_page) ? (int)($shop_page['folder'] ?? 0) : 0;
         $shop_rows = $db->select(
            'dbxShop|shopProductImage',
            'active = 1 AND trash = 0 AND media_id > 0',
            'media_id,title,product_id,group_id',
            'media_id,id',
            'ASC',
            '',
            0,
            0,
            0
         );
         if ($shop_content_id > 0 && is_array($shop_rows)) {
            $content_key = $master_lng . ':' . $shop_content_id;
            if (!isset($content_folders[$content_key])) $content_folders[$content_key] = array();
            if ($shop_folder_id > 0) $content_folders[$content_key][$shop_folder_id] = 1;
            $shop_by_media = array();
            foreach ($shop_rows as $shop_row) {
               if (!is_array($shop_row)) continue;
               $media_id = (int)($shop_row['media_id'] ?? 0);
               if (!isset($valid_media_ids[$media_id])) continue;
               if (!isset($shop_by_media[$media_id])) {
                  $shop_by_media[$media_id] = array(
                     'title' => (string)($shop_row['title'] ?? ''),
                     'product_ids' => array(),
                     'group_ids' => array(),
                  );
               }
               $product_id = (int)($shop_row['product_id'] ?? 0);
               $group_id = (int)($shop_row['group_id'] ?? 0);
               if ($product_id > 0) $shop_by_media[$media_id]['product_ids'][$product_id] = $product_id;
               if ($group_id > 0) $shop_by_media[$media_id]['group_ids'][$group_id] = $group_id;
            }
            foreach ($shop_by_media as $media_id => $shop_info) {
               $settings = json_encode(array(
                  'source' => 'dbxShop',
                  'product_ids' => array_values($shop_info['product_ids']),
                  'group_ids' => array_values($shop_info['group_ids']),
               ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
               $this->add_expected_media_usage(
                  $expected,
                  (int)$media_id,
                  $shop_content_id,
                  $shop_folder_id,
                  'shop',
                  'image-gallery',
                  $master_lng,
                  (string)$shop_info['title'],
                  $settings ?: '{"source":"dbxShop"}'
               );
            }
            $controlled_slots[] = 'shop';
         }
      } catch (\Throwable $e) {
         dbx()->debug('dbxContent shop media usage snapshot skipped', $e->getMessage());
      }

      return array(
         'media_rows' => $media_rows,
         'valid_media_ids' => $valid_media_ids,
         'expected' => $expected,
         'content_folders' => $content_folders,
         'folder_ids' => $folder_ids,
         'seo_media_ids' => $seo_media_ids,
         'seo_references' => $seo_references,
         'controlled_slots' => $controlled_slots,
      );
   }



   private function physical_delete_ids($db, string $dd, array $ids): int {
      $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
      $removed = 0;
      foreach (array_chunk($ids, 250) as $chunk) {
         $where = 'id IN (' . implode(',', $chunk) . ')';
         $before = (int)$db->count($dd, $where);
         if ($before <= 0) continue;
         $db->delete($dd, $where, 1, 0);
         $after = (int)$db->count($dd, $where);
         $removed += max(0, $before - $after);
      }
      return $removed;
   }



   private function reconcile_media_usage($db): array {
      $snapshot = $this->media_usage_snapshot($db);
      $usage_rows = $db->select($this->dd_media_usage, '', '*', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($usage_rows)) $usage_rows = array();
      $plan = dbxContentMediaUsageMaintenance::plan(
         $usage_rows,
         $snapshot['valid_media_ids'],
         $snapshot['expected'],
         $snapshot['content_folders'],
         $snapshot['folder_ids'],
         array('hero', 'gallery', 'inline', 'header', 'teaser', 'footer', 'shop'),
         $snapshot['controlled_slots']
      );

      // SQLite ist bei vielen einzelnen Schreibvorgaengen ohne Transaktion
      // unverhaeltnismaessig langsam. Die komplette Korrektur ist eine
      // atomare Wartungsoperation und wird deshalb gemeinsam committed.
      // Gleichzeitig dient die vorhandene Nutzung als Sorter-Seed, damit
      // neue Soll-Zuordnungen keine SELECT-Abfrage pro Datensatz benoetigen.
      $sorter_max = array();
      foreach ($usage_rows as $row) {
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) continue;
         $sorter_key = (int)($row['content_id'] ?? 0) . ':'
            . (int)($row['folder_id'] ?? 0) . ':'
            . dbxContentMediaUsageScope::language((string)($row['content_lng'] ?? '')) . ':'
            . $this->valid_media_slot($row['slot'] ?? 'gallery');
         $sorter_max[$sorter_key] = max(
            (int)($sorter_max[$sorter_key] ?? 0),
            (int)($row['sorter'] ?? 0)
         );
      }

      $cache_targets = array();
      foreach ($usage_rows as $row) {
         $id = is_array($row) ? (int)($row['id'] ?? 0) : 0;
         if ($id <= 0 || !isset($plan['delete'][$id])) continue;
         $content_id = (int)($row['content_id'] ?? 0);
         $folder_id = (int)($row['folder_id'] ?? 0);
         $cache_targets[$content_id . ':' . $folder_id] = array($content_id, $folder_id);
      }
      $removed = 0;
      $updated = 0;
      $added = 0;
      $transaction_started = (int)$db->begin($this->dd_media_usage) === 1;
      try {
         $removed = $this->physical_delete_ids($db, $this->dd_media_usage, array_keys($plan['delete']));

         foreach ($plan['update'] as $id => $data) {
            if ((int)$db->update($this->dd_media_usage, $data, (int)$id, 0, 1, 1, 0) >= 0) $updated++;
         }

         foreach ($plan['insert'] as $reference) {
            $content_id = (int)($reference['content_id'] ?? 0);
            $folder_id = (int)($reference['folder_id'] ?? 0);
            $slot = $this->valid_media_slot($reference['slot'] ?? 'gallery');
            $content_lng = dbxContentMediaUsageScope::language((string)($reference['content_lng'] ?? ''));
            $sorter_key = $content_id . ':' . $folder_id . ':' . $content_lng . ':' . $slot;
            $sorter_max[$sorter_key] = (int)($sorter_max[$sorter_key] ?? 0) + 10;
            $insert = array(
               'active' => 1,
               'media_id' => (int)($reference['media_id'] ?? 0),
               'content_id' => $content_id,
               'folder_id' => $folder_id,
               'content_lng' => $content_lng,
               'slot' => $slot,
               'sorter' => sprintf('%04d', $sorter_max[$sorter_key]),
               'template' => (string)($reference['template'] ?? ''),
               'caption' => (string)($reference['caption'] ?? ''),
               'settings' => (string)($reference['settings'] ?? ''),
            );
            if ($db->insert($this->dd_media_usage, $insert, 0, 1, 1, 0) === 1) {
               $added++;
               $cache_targets[$content_id . ':' . $folder_id] = array($content_id, $folder_id);
            }
         }

         if ($transaction_started && (int)$db->commit($this->dd_media_usage) !== 1) {
            throw new \RuntimeException('media_usage_commit_failed');
         }
      } catch (\Throwable $e) {
         if ($transaction_started) $db->rollback($this->dd_media_usage);
         throw $e;
      }

      foreach ($cache_targets as $target) {
         $this->flush_media_cache($db, (int)$target[0], (int)$target[1]);
      }

      return array(
         'analyzed' => (int)$plan['analyzed'],
         'actual_references' => (int)$plan['kept'] + $added + (int)($snapshot['seo_references'] ?? 0),
         'kept' => (int)$plan['kept'],
         'added' => $added,
         'updated' => $updated,
         'removed' => $removed,
         'planned_removed' => count($plan['delete']),
         'reasons' => $plan['reasons'],
      );
   }



   private function cleanup_invalid_structured_media_references($db): array {
      $snapshot = $this->media_usage_snapshot($db);
      $valid = $snapshot['valid_media_ids'];
      $refs = 0;
      $pages = 0;
      $folders_count = 0;
      $previous_lng = (string)dbx()->get_system_var('dbx_lng', dbxContentLngSync::masterLng());

      try {
         foreach ($this->ordered_media_languages() as $lng) {
            dbx()->set_system_var('dbx_lng', $lng);
            $content_dd = dbxContentLng::ddContent($lng);
            $page_rows = $db->select($content_dd, '', 'id,hero_image_id,seo_image_id', 'id', 'ASC', '', 0, 0, 0);
            foreach (is_array($page_rows) ? $page_rows : array() as $row) {
               if (!is_array($row)) continue;
               $id = (int)($row['id'] ?? 0);
               if ($id <= 0) continue;
               $update = array();
               $hero_id = (int)($row['hero_image_id'] ?? 0);
               $seo_id = (int)($row['seo_image_id'] ?? 0);
               if ($hero_id > 0 && !isset($valid[$hero_id])) $update['hero_image_id'] = '0';
               if ($seo_id > 0 && !isset($valid[$seo_id])) $update['seo_image_id'] = 0;
               if (!$update) continue;
               if ((int)$db->update($content_dd, $update, $id, 0, 1, 1, 0) >= 0) {
                  $refs += count($update);
                  $pages++;
                  $this->flush_saved_page_cache($db, $id);
               }
            }

            $folder_dd = dbxContentLng::ddFolder($lng);
            $folder_rows = $db->select($folder_dd, '', 'id,hero_image_id', 'id', 'ASC', '', 0, 0, 0);
            foreach (is_array($folder_rows) ? $folder_rows : array() as $row) {
               if (!is_array($row)) continue;
               $id = (int)($row['id'] ?? 0);
               $hero_id = (int)($row['hero_image_id'] ?? 0);
               if ($id <= 0 || $hero_id <= 0 || isset($valid[$hero_id])) continue;
               if ((int)$db->update($folder_dd, array('hero_image_id' => 'parent'), $id, 0, 1, 1, 0) >= 0) {
                  $refs++;
                  $folders_count++;
               }
            }
         }
      } finally {
         dbx()->set_system_var('dbx_lng', $previous_lng);
      }

      return array('refs' => $refs, 'pages' => $pages, 'folders' => $folders_count);
   }



   private function purge_invalid_media_records($db): array {
      $rows = $db->select($this->dd_media, 'active <> 1', '*', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) $rows = array();
      $ids = array();
      $deleted_thumbs = 0;
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         $path = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         if ($id <= 0 || $this->is_excluded_cms_media_folder((string)($row['media_folder'] ?? '')) || strpos($path, 'media/module/') === 0) continue;
         $ids[] = $id;
         $thumb = $this->source_thumb_file($row);
         if ($thumb !== '' && is_file($thumb) && @unlink($thumb)) $deleted_thumbs++;
      }
      return array(
         'found' => count($ids),
         'removed' => $this->physical_delete_ids($db, $this->dd_media, $ids),
         'deleted_thumbs' => $deleted_thumbs,
      );
   }



   private function find_relocated_media_row(array $existing, string $rel, string $name, array $meta, string $media_folder, array $usage_map = array()) {
      $name_l = strtolower(trim($name));
      if ($name_l === '') return null;
      $size = (int)($meta['size'] ?? 0);
      $width = (int)($meta['width'] ?? 0);
      $height = (int)($meta['height'] ?? 0);
      $media_folder = $this->canonical_media_folder($media_folder);
      $best = null;
      $best_score = 0;
      foreach ($existing as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         $storage = trim((string)($row['storage_type'] ?? ''));
         if ($storage === 'external' || (string)($row['media_type'] ?? '') === 'external_video') continue;
         if (strtolower((string)($row['file_name'] ?? '')) !== $name_l) continue;
         $existing_path = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         if ($existing_path === $rel) continue;
         if ($existing_path !== '' && $this->relative_file_exists($existing_path)) continue;
         $active = (int)($row['active'] ?? 0) === 1;
         $used = $id > 0 && !empty($usage_map[$id]);
         if (!$active && !$used) continue;
         $score = 1;
         if ($used) $score += 10;
         if ($active) $score += 3;
         if ($size > 0 && (int)($row['size'] ?? 0) === $size) $score += 2;
         if ($width > 0 && $height > 0 && (int)($row['width'] ?? 0) === $width && (int)($row['height'] ?? 0) === $height) $score += 2;
         $old_folder = $this->canonical_media_folder(trim((string)($row['media_folder'] ?? '')));
         if ($old_folder !== '' && $old_folder === $media_folder) $score += 1;
         if ($score > $best_score) {
            $best_score = $score;
            $best = $row;
         }
      }
      return $best;
   }



   private function sync_cms_media_files($db) {
      $existing = $db->select($this->dd_media, '', '*', 'id');
      if (!is_array($existing)) $existing = array();
      $usage_map = $this->active_media_usage_map($db);

      $by_path = array();
      foreach ($existing as $row) {
         if (!is_array($row)) continue;
         $path = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         if ($path !== '') $by_path[$path] = $row;
      }

      $seen = array();
      $relocated_ids = array();
      foreach ($this->collect_media_files() as $rel => $file_info) {
         $file = (string)($file_info['file'] ?? '');
         if ($file === '' || !is_file($file) || !is_readable($file)) continue;
         $name = basename($file);
         $slot = $this->valid_media_slot($file_info['slot'] ?? $this->slot_from_media_rel($rel));
         $seen[$rel] = 1;
         $meta = $this->media_file_meta($file, $name);
         $media_type = $meta['media_type'];
         if ($media_type === 'file' && preg_match('~^media/youtube/~', $rel)) $media_type = 'external_video';
         $media_folder = $this->media_folder_from_path($rel, $media_type);
         $storage_type = $media_type === 'external_video' ? 'external' : 'local';
         $external_meta = $media_type === 'external_video' ? $this->external_video_json_meta($file) : array();

         $existing_key = $rel;
         if (!isset($by_path[$existing_key])) {
         }

         $row = isset($by_path[$existing_key]) ? $by_path[$existing_key] : null;
         if (!is_array($row)) {
            $row = $this->find_relocated_media_row($existing, $rel, $name, $meta, $media_folder, $usage_map);
         }
         if (is_array($row)) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;

            $needs_thumb = $storage_type === 'local' && (empty($row['thumb_file_path']) || !$this->media_thumb_exists($row));
            $thumb = $needs_thumb ? $this->create_media_thumbnail($file, $media_folder, $name, $meta['mime']) : array();
            $update = array(
               'active' => 1,
               'mime' => $meta['mime'],
               'size' => $meta['size'],
               'width' => $meta['width'],
               'height' => $meta['height'],
               'media_type' => $media_type,
               'storage_type' => $storage_type,
               'media_folder' => $media_folder,
               'file_name' => $name,
               'file_path' => $rel,
            );
            if ($external_meta) $update = array_merge($update, $external_meta);
            if ($thumb) $update = array_merge($update, $thumb);
            $db->update($this->dd_media, $update, $id);
            if (($row['file_path'] ?? '') !== $rel) $this->flush_media_by_media_id($db, $id);
            $by_path[$rel] = array_merge($row, $update);
            if (($row['file_path'] ?? '') !== $rel) $relocated_ids[$id] = 1;
            continue;
         }

         $thumb = $storage_type === 'local' ? $this->create_media_thumbnail($file, $media_folder, $name, $meta['mime']) : array();
         $title = pathinfo($name, PATHINFO_FILENAME);
         if (!empty($external_meta['title'])) $title = (string)$external_meta['title'];
         $insert = array(
            'active' => 1,
            'content_id' => 0,
            'folder_id' => 0,
            'slot' => $slot,
            'usage' => $slot,
            'sorter' => $this->next_media_sorter($db, 0, $slot),
            'template' => '',
            'title' => $title,
            'alt' => $title,
            'file_name' => $name,
            'file_path' => $rel,
            'mime' => $meta['mime'],
            'size' => $meta['size'],
            'width' => $meta['width'],
            'height' => $meta['height'],
            'media_type' => $media_type,
            'storage_type' => $storage_type,
            'media_folder' => $media_folder,
         );
         if ($external_meta) $insert = array_merge($insert, $external_meta);
         if ($thumb) $insert = array_merge($insert, $thumb);
         $id = ($db->insert($this->dd_media, $insert) === 1) ? $db->get_insert_id() : 0;
      }

      foreach ($existing as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         $path = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         if ($id <= 0 || (int)($row['active'] ?? 0) !== 1) continue;
         if (isset($relocated_ids[$id])) continue;
         if (preg_match('~^media/(hero|gallery|inline|header|teaser|footer)/~', $path) && !isset($seen[$path])) {
            $db->update($this->dd_media, array('active' => 0), $id);
            continue;
         }
         if (!isset($seen[$path]) && !$this->relative_file_exists($path)) {
            $db->update($this->dd_media, array('active' => 0), $id);
         }
         if ((int)($row['active'] ?? 0) === 1 && ($this->is_excluded_cms_media_folder((string)($row['media_folder'] ?? '')) || preg_match('~^media/module/~', $path))) {
            $db->update($this->dd_media, array('active' => 0), $id);
         }
      }
   }



   private function normalize_media_row($row) {
      $row = is_array($row) ? $row : array();
      $file_path = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      $row['url'] = $this->media_url($row);
      $row['thumb_url'] = $this->media_url($row, true);
      $row['media_type'] = $this->media_type($row);
      $row['storage_type'] = trim((string)($row['storage_type'] ?? '')) ?: ($row['media_type'] === 'external_video' ? 'external' : 'local');
      $row['media_folder'] = $this->canonical_media_folder(trim((string)($row['media_folder'] ?? '')) ?: $this->media_folder_from_path((string)($row['file_path'] ?? ''), $row['media_type']));
      if ($row['media_type'] === 'external_video') {
         $row['url'] = (string)($row['embed_url'] ?? $row['external_url'] ?? '');
         $row['thumb_url'] = $this->external_video_thumb_url($row);
      }
      return $row;
   }



   private function normalize_usage_row($row) {
      $row = is_array($row) ? $row : array();
      $row['id'] = (int)($row['id'] ?? 0);
      $row['media_id'] = (int)($row['media_id'] ?? 0);
      $row['content_id'] = (int)($row['content_id'] ?? 0);
      $row['folder_id'] = (int)($row['folder_id'] ?? 0);
      $row['content_lng'] = dbxContentMediaUsageScope::language((string)($row['content_lng'] ?? ''));
      $row['slot'] = $this->valid_media_slot($row['slot'] ?? 'gallery');
      return $row;
   }



   private function usage_where($media_id = 0, $content_id = 0, $folder_id = 0, $slot = '', $content_lng = '') {
      $where = 'active = 1';
      if ((int)$media_id > 0) $where .= ' AND media_id = ' . (int)$media_id;
      if ((int)$content_id > 0) $where .= ' AND content_id = ' . (int)$content_id;
      if ((int)$folder_id > 0) $where .= ' AND folder_id = ' . (int)$folder_id;
      if ((string)$slot !== '') $where .= " AND slot = '" . str_replace("'", "''", $this->valid_media_slot($slot)) . "'";
      if ((int)$content_id > 0 || (int)$folder_id > 0) {
         $where = dbxContentMediaUsageScope::withLanguage($where, $content_lng);
      }
      return $where;
   }



   private function next_media_usage_sorter($db, $content_id, $folder_id, $slot, $content_lng = '') {
      $where = $this->usage_where(0, $content_id, $folder_id, $slot, $content_lng);
      $rows = $db->select($this->dd_media_usage, $where, '*', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) $max = (int)($rows[0]['sorter'] ?? 0);
      return sprintf('%04d', $max + 10);
   }



   private function create_media_usage($db, $media_id, $content_id, $folder_id, $slot, $template = '', $caption = '', $settings = '', $content_lng = '') {
      $media_id = (int)$media_id;
      $content_id = (int)$content_id;
      $folder_id = (int)$folder_id;
      $slot = $this->valid_media_slot($slot);
      $content_lng = dbxContentMediaUsageScope::language($content_lng);
      if ($media_id <= 0 || ($content_id <= 0 && $folder_id <= 0)) return 0;
      $existingWhere = 'media_id = ' . $media_id
         . ' AND content_id = ' . $content_id
         . ' AND folder_id = ' . $folder_id
         . " AND slot = '" . str_replace("'", "''", $slot) . "'";
      $existingWhere = dbxContentMediaUsageScope::withLanguage($existingWhere, $content_lng);
      $existing = $db->select($this->dd_media_usage, $existingWhere, '*', 'active DESC,id DESC', 'ASC', '', 1, 0, 0);
      if (is_array($existing) && isset($existing[0]['id']) && (int)($existing[0]['active'] ?? 0) === 1) {
         return (int)$existing[0]['id'];
      }
      if ($slot === 'hero') {
         if ($content_id > 0) $db->update($this->dd_media_usage, array('active' => 0), dbxContentMediaUsageScope::withLanguage("content_id = " . $content_id . " AND slot = 'hero' AND active = 1", $content_lng), 0, 1, 1, 0);
         elseif ($folder_id > 0) $db->update($this->dd_media_usage, array('active' => 0), dbxContentMediaUsageScope::withLanguage("folder_id = " . $folder_id . " AND slot = 'hero' AND active = 1", $content_lng), 0, 1, 1, 0);
      }
      if (is_array($existing) && isset($existing[0]['id'])) {
         $existingId = (int)$existing[0]['id'];
         $db->update($this->dd_media_usage, array(
            'active' => 1,
            'template' => $this->clean_text($template, 80),
            'caption' => $this->clean_text($caption, 0),
            'settings' => $this->clean_text($settings, 0),
         ), $existingId, 0, 1, 1, 0);
         return $existingId;
      }
      return ($db->insert($this->dd_media_usage, array(
         'active' => 1,
         'media_id' => $media_id,
         'content_id' => $content_id,
         'folder_id' => $folder_id,
         'content_lng' => $content_lng,
         'slot' => $slot,
         'sorter' => $this->next_media_usage_sorter($db, $content_id, $folder_id, $slot, $content_lng),
         'template' => $this->clean_text($template, 80),
         'caption' => $this->clean_text($caption, 0),
         'settings' => $this->clean_text($settings, 0),
      )) === 1) ? $db->get_insert_id() : 0;
   }
}
