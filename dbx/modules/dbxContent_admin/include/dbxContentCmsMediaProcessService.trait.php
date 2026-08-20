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
 * Fortsetzbare Medienwartung mit kleinen Tasks, Status und kontrollierten Prozessschritten.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsMediaProcessServiceTrait {


   private function get_media_process_state($token) {
      $token = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)$token);
      if ($token === '') return array();
      return dbxContentAdminSessionState::media_process($token);
   }



   private function set_media_process_state($token, array $state) {
      $token = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)$token);
      if ($token === '') return;
      dbxContentAdminSessionState::set_media_process($token, $state);
   }



   private function slot_from_media_rel($rel, $fallback = 'gallery') {
      $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
      if (preg_match('~^media/(hero|gallery|inline|header|teaser|footer)/~', $rel, $m)) {
         return $this->valid_media_slot($m[1], $fallback);
      }
      return $this->valid_media_slot($fallback);
   }



   private function build_media_process_state($db, $token) {
      $rows = $db->select($this->dd_media, '', '*', 'id');
      if (!is_array($rows)) $rows = array();

      $by_path = array();
      $referenced_thumbs = array();
      $tasks = array();
      $needs_content_cleanup = false;

      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         $path = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         if ($path !== '') $by_path[$path] = $id;
         $thumb = ltrim(str_replace('\\', '/', (string)($row['thumb_file_path'] ?? '')), '/');
         if ($thumb !== '') $referenced_thumbs[$thumb] = 1;
         $is_external = strtolower((string)($row['storage_type'] ?? '')) === 'external'
            || $this->media_type($row) === 'external_video';
         $is_excluded = $this->is_excluded_cms_media_folder((string)($row['media_folder'] ?? ''))
            || strpos($path, 'media/module/') === 0;
         $is_legacy_path = (bool)preg_match('~^media/(hero|gallery|inline|header|teaser|footer)/~', $path);
         $file_exists = $this->media_file_exists($row);
         $needs_record_check = $is_legacy_path
            || (!$is_external && !$is_excluded && (((int)($row['active'] ?? 0) !== 1 && $file_exists) || ((int)($row['active'] ?? 0) === 1 && !$file_exists)))
            || ($is_external && (int)($row['active'] ?? 0) === 1 && !$file_exists);
         if ($id > 0 && $needs_record_check) {
            $tasks[] = array('type' => 'record', 'id' => $id);
            if (!$file_exists) $needs_content_cleanup = true;
         }
         if ($id > 0
             && !$needs_record_check
             && (int)($row['active'] ?? 0) === 1
             && $this->media_file_exists($row)
             && $this->media_thumbnail_supported($row)
             && preg_match('~^media/(img|video|file|module)/~', $path)
             && !$this->media_thumbnail_is_current($row)) {
            $tasks[] = array('type' => 'thumb', 'id' => $id);
         }
         if ($id > 0 && (int)($row['active'] ?? 0) !== 1) $needs_content_cleanup = true;
      }

      foreach ($this->collect_media_files() as $rel => $file) {
         if (!isset($by_path[$rel])) {
            $tasks[] = array('type' => 'import', 'rel' => $rel, 'slot' => $file['slot']);
         }
      }

      foreach ($this->collect_media_thumb_files() as $rel => $file) {
         if (!isset($referenced_thumbs[$rel])) {
            $tasks[] = array('type' => 'delete_thumb', 'rel' => $rel);
         }
      }

      if ($needs_content_cleanup || $this->content_inline_cleanup_needed($db)) {
         $tasks[] = array('type' => 'content_cleanup');
      }

      if ($this->content_inline_placeholder_repair_needed($db)) {
         $tasks[] = array('type' => 'content_placeholder_repair');
      }

      $tasks[] = array('type' => 'structured_reference_cleanup');
      $tasks[] = array('type' => 'usage_reconcile');
      $tasks[] = array('type' => 'media_record_purge');
      $tasks[] = array('type' => 'folder_sort_normalize');

      $cache_content_ids = $this->content_media_reference_ids($db);
      if ($cache_content_ids) {
         $tasks[] = array('type' => 'content_cache_flush', 'ids' => $cache_content_ids);
      }

      // Bei SQLite optimiert dies die komplette dbxMedia-Datenbank mit
      // VACUUM, bei MySQL die Tabelle mit OPTIMIZE TABLE.
      $tasks[] = array('type' => 'database_optimize');

      return array(
         'proc_key' => $token,
         'proc_type' => 'media_maintenance',
         'status' => empty($tasks) ? 'finished' : 'running',
         'phase' => 'media_prepare',
         'message' => empty($tasks) ? 'Keine Wartung notwendig.' : 'Medienwartung vorbereitet.',
         'tasks' => $tasks,
         'pos' => 0,
         'total' => count($tasks),
         'created_thumbs' => 0,
         'imported_media' => 0,
         'relinked_media' => 0,
         'deleted_thumbs' => 0,
         'deactivated_media' => 0,
         'cleaned_inline_refs' => 0,
         'cleaned_content_pages' => 0,
         'repaired_inline_refs' => 0,
         'flushed_content_pages' => 0,
         'removed_orphan_usage' => 0,
         'usage_rows_analyzed' => 0,
         'actual_media_references' => 0,
         'usage_rows_added' => 0,
         'usage_rows_updated' => 0,
         'usage_rows_removed' => 0,
         'usage_inactive_removed' => 0,
         'usage_stale_removed' => 0,
         'usage_duplicate_removed' => 0,
         'cleaned_structured_refs' => 0,
         'purged_media_records' => 0,
         'folder_sorters_checked' => 0,
         'folder_sorters_updated' => 0,
         'folder_sort_parents' => 0,
         'folder_sort_languages' => 0,
         'database_optimized' => 0,
         'errors' => 0,
         'percent' => empty($tasks) ? 100 : 0,
         'step_percent' => empty($tasks) ? 100 : 0,
         'updated_at' => date('H:i:s'),
      );
   }



   private function update_media_process_percent(array &$state) {
      $total = max(1, (int)($state['total'] ?? 0));
      $pos = max(0, min($total, (int)($state['pos'] ?? 0)));
      $percent = (int)floor(($pos / $total) * 100);
      if (($state['status'] ?? '') === 'finished') $percent = 100;
      $state['percent'] = max(0, min(100, $percent));
      $state['step_percent'] = $state['percent'];
   }



   private function media_process_message(array $state, $current = '') {
      $parts = array();
      if ((int)($state['created_thumbs'] ?? 0) > 0) $parts[] = 'Thumbs +' . (int)$state['created_thumbs'];
      if ((int)($state['imported_media'] ?? 0) > 0) $parts[] = 'Import +' . (int)$state['imported_media'];
      if ((int)($state['relinked_media'] ?? 0) > 0) $parts[] = 'Zuordnung repariert ' . (int)$state['relinked_media'];
      if ((int)($state['deleted_thumbs'] ?? 0) > 0) $parts[] = 'Thumbs weg ' . (int)$state['deleted_thumbs'];
      if ((int)($state['deactivated_media'] ?? 0) > 0) $parts[] = 'Medien aus ' . (int)$state['deactivated_media'];
      if ((int)($state['cleaned_inline_refs'] ?? 0) > 0) $parts[] = 'Content bereinigt ' . (int)$state['cleaned_inline_refs'];
      if ((int)($state['repaired_inline_refs'] ?? 0) > 0) $parts[] = 'Inline repariert ' . (int)$state['repaired_inline_refs'];
      if ((int)($state['flushed_content_pages'] ?? 0) > 0) $parts[] = 'Cache aktualisiert ' . (int)$state['flushed_content_pages'];
      if ((int)($state['usage_rows_added'] ?? 0) > 0) $parts[] = 'Nutzung ergaenzt ' . (int)$state['usage_rows_added'];
      if ((int)($state['usage_rows_updated'] ?? 0) > 0) $parts[] = 'Nutzung korrigiert ' . (int)$state['usage_rows_updated'];
      if ((int)($state['usage_rows_removed'] ?? 0) > 0) $parts[] = 'Ungueltige Zuordnungen entfernt ' . (int)$state['usage_rows_removed'];
      if ((int)($state['cleaned_structured_refs'] ?? 0) > 0) $parts[] = 'Defekte Inhaltsverweise bereinigt ' . (int)$state['cleaned_structured_refs'];
      if ((int)($state['purged_media_records'] ?? 0) > 0) $parts[] = 'Ungueltige Medien-Datensaetze entfernt ' . (int)$state['purged_media_records'];
      if ((int)($state['folder_sorters_updated'] ?? 0) > 0) $parts[] = 'Ordner-Sortierungen normalisiert ' . (int)$state['folder_sorters_updated'];
      if ((int)($state['database_optimized'] ?? 0) > 0) $parts[] = 'Datenbanken optimiert ' . (int)$state['database_optimized'];
      if ((int)($state['errors'] ?? 0) > 0) $parts[] = 'Fehler ' . (int)$state['errors'];
      $msg = empty($parts) ? 'Keine Aenderungen.' : implode(' | ', $parts);
      if ($current !== '') $msg .= ' | ' . $current;
      return $msg;
   }



   private function content_inline_cleanup_needed($db) {
      foreach ($this->ordered_media_languages() as $lng) {
         $rows = $db->select(dbxContentLng::dd_content($lng), "content LIKE '%dbx_mid=%' OR content LIKE '%data-cms-media-id=%'", 'id,content', 'id');
         if (!is_array($rows)) continue;
         foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($this->inline_media_ids($row['content'] ?? '') as $id) {
               $media = $db->select1($this->dd_media, $id);
               if (!is_array($media) || (int)($media['active'] ?? 0) !== 1 || !$this->media_file_exists($media)) return true;
            }
         }
      }
      return false;
   }



   private function content_media_reference_ids($db) {
      $ids = array();
      $rows = $db->select(dbxContentLng::dd_content(), "content LIKE '%dbx_mid=%' OR content LIKE '%data-cms-media-id=%'", 'id,content', 'id');
      if (!is_array($rows)) return $ids;
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $content_id = (int)($row['id'] ?? 0);
         if ($content_id <= 0) continue;
         if (!$this->inline_media_ids($row['content'] ?? '')) continue;
         $ids[$content_id] = $content_id;
      }
      return array_values($ids);
   }



   private function content_inline_placeholder_repair_needed($db) {
      foreach (dbxContentLngSync::accessible_lngs() as $lng) {
         $rows = $db->select(dbxContentLng::dd_content((string)$lng), "content LIKE '%dbx-cms-inline-media-missing%'", 'id,content', 'id');
         if (!is_array($rows)) continue;
         foreach ($rows as $row) {
            if (!is_array($row)) continue;
            if (preg_match('/\bdbx-cms-inline-media-missing\b/i', (string)($row['content'] ?? ''))) return true;
         }
      }
      return false;
   }



   private function repair_content_inline_placeholders($db) {
      $pages = 0;
      $refs = 0;
      $prev_lng = dbxContentLng::current();

      foreach (dbxContentLngSync::accessible_lngs() as $lng) {
         $lng = (string)$lng;
         dbx()->set_system_var('dbx_lng', $lng);
         $dd = dbxContentLng::dd_content($lng);
         $rows = $db->select($dd, "content LIKE '%dbx-cms-inline-media-missing%'", 'id,folder,content', 'id');
         if (!is_array($rows)) continue;

         foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $content_id = (int)($row['id'] ?? 0);
            if ($content_id <= 0) continue;
            $html = (string)($row['content'] ?? '');
            if (stripos($html, 'dbx-cms-inline-media-missing') === false) continue;

            $changed = 0;
            $clean = preg_replace_callback(
               '/<(p|figure|div)\b([^>]*(?:\bdbx-cms-inline-media-missing-wrap\b|\bdbx-cms-inline-media\b|\bdata-cms-media-id=)[^>]*)>([\s\S]*?\bdbx-cms-inline-media-missing\b[\s\S]*?)<\/\1>/i',
               function($m) use ($db, &$changed) {
                  $block = (string)($m[0] ?? '');
                  if (!preg_match('/\bdata-cms-media-id=["\']?([0-9]+)/i', $block, $id_match)) return $block;
                  $id = (int)$id_match[1];
                  if ($id <= 0) return $block;
                  $media = $db->select1($this->dd_media, $id);
                  if (!is_array($media) || (int)($media['active'] ?? 0) !== 1 || !$this->media_file_exists($media)) return $block;
                  $replacement = $this->inline_media_embed_html($media);
                  if ($replacement === '') return $block;
                  $changed++;
                  return $replacement;
               },
               $html
            );
            if ($clean === null || $clean === $html || $changed <= 0) continue;
            $ok = $db->update($dd, array('content' => $clean), $content_id, 0, 0, 0, 0);
            if ($ok < 0) continue;
            $this->sync_inline_media_usage($db, $content_id, $clean, (int)($row['folder'] ?? 0));
            $this->flush_saved_page_cache($db, $content_id);
            $pages++;
            $refs += $changed;
         }
      }

      dbx()->set_system_var('dbx_lng', $prev_lng);

      return array('pages' => $pages, 'refs' => $refs);
   }



   private function remove_inline_media_ids_from_html($html, array $ids, &$removed = 0) {
      $html = (string)$html;
      $removed = 0;
      $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
      if (!$ids || trim($html) === '') return $html;

      if (class_exists('\DOMDocument')) {
         $doc = new \DOMDocument('1.0', 'UTF-8');
         $prev = libxml_use_internal_errors(true);
         $wrapped = '<div id="dbx-cms-clean-root">' . $html . '</div>';
         $ok = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
         libxml_clear_errors();
         libxml_use_internal_errors($prev);
         if ($ok) {
            $xpath = new \DOMXPath($doc);
            $root_nodes = $xpath->query("//*[@id='dbx-cms-clean-root']");
            $root = $root_nodes && $root_nodes->length ? $root_nodes->item(0) : null;
            foreach ($ids as $id) {
               $query = "//*[@data-cms-media-id='" . $id . "' or contains(@src,'dbx_mid=" . $id . "') or contains(@href,'dbx_mid=" . $id . "')]";
               $nodes = $xpath->query($query);
               $targets = array();
               if ($nodes) {
                  foreach ($nodes as $node) {
                     $target = $node;
                     for ($p = $node; $p && $p !== $root; $p = $p->parentNode) {
                        if ($p->nodeType !== XML_ELEMENT_NODE) continue;
                        $tag = strtolower($p->nodeName);
                        $class = ' ' . (string)$p->getAttribute('class') . ' ';
                        if ($tag === 'figure' || strpos($class, ' dbx-cms-inline-media ') !== false) {
                           $target = $p;
                        }
                     }
                     if ($target && $target->parentNode) $targets[spl_object_hash($target)] = $target;
                  }
               }
               foreach ($targets as $target) {
                  if ($target->parentNode) {
                     $target->parentNode->removeChild($target);
                     $removed++;
                  }
               }
            }
            if ($root) {
               $out = '';
               foreach (iterator_to_array($root->childNodes) as $child) {
                  $out .= $doc->saveHTML($child);
               }
               return $out;
            }
         }
      }

      foreach ($ids as $id) {
         $before = $html;
         $id_re = preg_quote((string)$id, '~');
         $html = preg_replace('~<(p|figure|div)\b[^>]*(?:data-cms-media-id=["\']?' . $id_re . '\b|class=["\'][^"\']*dbx-cms-inline-media[^"\']*["\'][^>]*>.*?(?:dbx_mid=' . $id_re . '\b|data-cms-media-id=["\']?' . $id_re . '\b))[\s\S]*?</\1>~i', '', $html);
         $html = preg_replace('~<(img|video|iframe|source)\b[^>]*(?:dbx_mid=' . $id_re . '\b|data-cms-media-id=["\']?' . $id_re . '\b)[^>]*>(?:</\1>)?~i', '', $html);
         if ($html !== $before) $removed++;
      }
      return $html;
   }



   private function clean_content_inline_media($db) {
      $pages = 0;
      $refs = 0;
      $previous_lng = (string)dbx()->get_system_var('dbx_lng', dbxContentLngSync::master_lng());
      try {
         foreach ($this->ordered_media_languages() as $lng) {
            dbx()->set_system_var('dbx_lng', $lng);
            $content_dd = dbxContentLng::dd_content($lng);
            $rows = $db->select($content_dd, "content LIKE '%dbx_mid=%' OR content LIKE '%data-cms-media-id=%'", 'id,folder,content', 'id');
            if (!is_array($rows)) continue;
            foreach ($rows as $row) {
               if (!is_array($row)) continue;
               $content_id = (int)($row['id'] ?? 0);
               if ($content_id <= 0) continue;
               $bad = array();
               foreach ($this->inline_media_ids($row['content'] ?? '') as $id) {
                  $media = $db->select1($this->dd_media, $id);
                  if (!is_array($media) || (int)($media['active'] ?? 0) !== 1 || !$this->media_file_exists($media)) $bad[] = $id;
               }
               if (!$bad) continue;
               $removed = 0;
               $clean = $this->remove_inline_media_ids_from_html((string)($row['content'] ?? ''), $bad, $removed);
               if ($clean !== (string)($row['content'] ?? '')) {
                  $db->update($content_dd, array('content' => $clean), $content_id);
                  $this->sync_inline_media_usage($db, $content_id, $clean, (int)($row['folder'] ?? 0));
                  $this->flush_saved_page_cache($db, $content_id);
                  $pages++;
                  $refs += max($removed, count($bad));
               }
            }
         }
      } finally {
         dbx()->set_system_var('dbx_lng', $previous_lng);
      }
      return array('pages' => $pages, 'refs' => $refs);
   }



   private function import_media_file($db, $rel, $slot, &$relinked = null) {
      $relinked = false;
      $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
      $slot = $this->valid_media_slot($slot);
      $file = $this->file_from_media_rel($rel);
      if ($file === '' || !is_file($file) || !is_readable($file)) return 0;

      $name = basename($file);
      $meta = $this->media_file_meta($file, $name);
      $media_folder = $this->media_folder_from_path($rel, $meta['media_type']);
      $existing = $db->select($this->dd_media, '', '*', 'id');
      if (!is_array($existing)) $existing = array();
      $relocated = $this->find_relocated_media_row($existing, $rel, $name, $meta, $media_folder, $this->active_media_usage_map($db));
      if (is_array($relocated)) {
         $id = (int)($relocated['id'] ?? 0);
         if ($id > 0) {
            $thumb = (empty($relocated['thumb_file_path']) || !$this->media_thumb_exists($relocated))
               ? $this->create_media_thumbnail($file, $media_folder, $name, $meta['mime'])
               : array();
            $update = array(
               'active' => 1,
               'file_path' => $rel,
               'file_name' => $name,
               'media_folder' => $media_folder,
               'mime' => $meta['mime'],
               'size' => $meta['size'],
               'width' => $meta['width'],
               'height' => $meta['height'],
               'media_type' => $meta['media_type'],
               'storage_type' => 'local',
            );
            if ($thumb) $update = array_merge($update, $thumb);
            $db->update($this->dd_media, $update, $id);
            $this->flush_media_by_media_id($db, $id);
            $relinked = true;
            return $id;
         }
      }

      $title = pathinfo($name, PATHINFO_FILENAME);
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
         'media_type' => $meta['media_type'],
         'media_folder' => $media_folder,
         'storage_type' => 'local',
      );
      $thumb = $this->create_media_thumbnail($file, $media_folder, $name, $meta['mime']);
      if ($thumb) $insert = array_merge($insert, $thumb);
      return ($db->insert($this->dd_media, $insert) === 1) ? $db->get_insert_id() : 0;
   }



   private function normalize_content_folder_sorters($db): array {
      $result = array(
         'checked' => 0,
         'updated' => 0,
         'parents' => 0,
         'languages' => 0,
         'errors' => 0,
      );

      foreach (dbxContentLngSync::accessible_lngs() as $lng) {
         $lng = strtolower(trim((string)$lng));
         if ($lng === '') continue;
         $dd = dbxContentLng::dd_folder($lng);
         $rows = $db->select($dd, '', 'id,parent_id,sorter,name', 'parent_id,sorter,name,id', 'ASC', '', 0, 0, 0);
         if (!is_array($rows) || empty($rows)) continue;

         $by_parent = array();
         foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) continue;
            $parent_id = max(0, (int)($row['parent_id'] ?? 0));
            if (!isset($by_parent[$parent_id])) $by_parent[$parent_id] = array();
            $by_parent[$parent_id][] = $row;
         }
         if (empty($by_parent)) continue;

         $transaction_started = (int)$db->begin($dd) === 1;
         if (!$transaction_started) {
            $result['errors']++;
            continue;
         }

         $language_updated = 0;
         try {
            foreach ($by_parent as $siblings) {
               $result['parents']++;
               $position = 10;
               foreach ($siblings as $row) {
                  $id = (int)($row['id'] ?? 0);
                  $wanted = sprintf('%04d', $position);
                  $result['checked']++;
                  if ((string)($row['sorter'] ?? '') !== $wanted) {
                     if ((int)$db->update($dd, array('sorter' => $wanted), $id, 0, 1, 1, 0) < 0) {
                        throw new \RuntimeException('folder_sort_update_failed');
                     }
                     $language_updated++;
                  }
                  $position += 10;
               }
            }
            if ((int)$db->commit($dd) !== 1) {
               throw new \RuntimeException('folder_sort_commit_failed');
            }
            $result['updated'] += $language_updated;
            $result['languages']++;
         } catch (\Throwable $e) {
            $db->rollback($dd);
            $result['errors']++;
         }
      }

      if ($result['updated'] > 0) $this->flush_menu_cache();
      return $result;
   }



   private function run_media_process_task($db, array &$state, array $task) {
      $type = (string)($task['type'] ?? '');

      if ($type === 'delete_thumb') {
         $rel = ltrim(str_replace('\\', '/', (string)($task['rel'] ?? '')), '/');
         $file = $this->file_from_media_rel($rel);
         if ($file !== '' && is_file($file) && @unlink($file)) {
            $state['deleted_thumbs'] = (int)($state['deleted_thumbs'] ?? 0) + 1;
         }
         $state['phase'] = 'media_cleanup';
         return basename($rel);
      }

      if ($type === 'thumb') {
         $id = (int)($task['id'] ?? 0);
         $row = $id > 0 ? $db->select1($this->dd_media, $id) : array();
         if (!is_array($row)) return '#' . $id;
         $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         if (!$this->media_thumbnail_supported($row)) {
            $state['phase'] = 'media_thumbs';
            return basename($rel);
         }
         $file = $this->file_from_media_rel($rel);
         if ($file === '' || !is_file($file) || !is_readable($file)) {
            $state['errors'] = (int)($state['errors'] ?? 0) + 1;
            return basename($rel);
         }
         $name = basename($file);
         $meta = $this->media_file_meta($file, $name);
         $media_folder = $this->media_folder_from_path($rel, $meta['media_type']);
         $old_thumb = $this->source_thumb_file($row);
         $thumb = $this->create_media_thumbnail($file, $media_folder, $name, $meta['mime']);
         if ($thumb) {
            $db->update($this->dd_media, $thumb, $id);
            $state['created_thumbs'] = (int)($state['created_thumbs'] ?? 0) + 1;
            $new_thumb = $this->file_from_media_rel((string)($thumb['thumb_file_path'] ?? ''));
            if ($old_thumb !== '' && $old_thumb !== $new_thumb && is_file($old_thumb) && @unlink($old_thumb)) {
               $state['deleted_thumbs'] = (int)($state['deleted_thumbs'] ?? 0) + 1;
            }
         } else {
            $state['errors'] = (int)($state['errors'] ?? 0) + 1;
         }
         $state['phase'] = 'media_thumbs';
         return basename($rel);
      }

      if ($type === 'import') {
         $rel = ltrim(str_replace('\\', '/', (string)($task['rel'] ?? '')), '/');
         $slot = $this->valid_media_slot($task['slot'] ?? $this->slot_from_media_rel($rel));
         $relinked = false;
         $id = $this->import_media_file($db, $rel, $slot, $relinked);
         if ($id > 0) {
            if ($relinked) {
               $state['relinked_media'] = (int)($state['relinked_media'] ?? 0) + 1;
            } else {
               $state['imported_media'] = (int)($state['imported_media'] ?? 0) + 1;
            }
            $row = $db->select1($this->dd_media, $id);
            if (is_array($row) && !empty($row['thumb_file_path'])) {
               $state['created_thumbs'] = (int)($state['created_thumbs'] ?? 0) + 1;
            }
         } else {
            $state['errors'] = (int)($state['errors'] ?? 0) + 1;
         }
         $state['phase'] = 'media_import';
         return basename($rel);
      }

      if ($type === 'content_cleanup') {
         $done = $this->clean_content_inline_media($db);
         $state['cleaned_inline_refs'] = (int)($state['cleaned_inline_refs'] ?? 0) + (int)($done['refs'] ?? 0);
         $state['cleaned_content_pages'] = (int)($state['cleaned_content_pages'] ?? 0) + (int)($done['pages'] ?? 0);
         $state['phase'] = 'content_cleanup';
         return 'Content';
      }

      if ($type === 'content_placeholder_repair') {
         $done = $this->repair_content_inline_placeholders($db);
         $state['repaired_inline_refs'] = (int)($state['repaired_inline_refs'] ?? 0) + (int)($done['refs'] ?? 0);
         $state['cleaned_content_pages'] = (int)($state['cleaned_content_pages'] ?? 0) + (int)($done['pages'] ?? 0);
         $state['phase'] = 'content_repair';
         return 'Inline';
      }

      if ($type === 'content_cache_flush') {
         $ids = is_array($task['ids'] ?? null) ? $task['ids'] : array();
         $count = 0;
         foreach ($ids as $content_id) {
            $content_id = (int)$content_id;
            if ($content_id <= 0) continue;
            $this->flush_saved_page_cache($db, $content_id);
            $count++;
         }
         $state['flushed_content_pages'] = (int)($state['flushed_content_pages'] ?? 0) + $count;
         $state['phase'] = 'content_cache';
         return 'Cache';
      }

      if ($type === 'structured_reference_cleanup') {
         $done = $this->cleanup_invalid_structured_media_references($db);
         $state['cleaned_structured_refs'] = (int)($state['cleaned_structured_refs'] ?? 0) + (int)($done['refs'] ?? 0);
         $state['cleaned_content_pages'] = (int)($state['cleaned_content_pages'] ?? 0) + (int)($done['pages'] ?? 0);
         $state['phase'] = 'media_reference_cleanup';
         return 'Inhaltsverweise';
      }

      if ($type === 'usage_reconcile') {
         $done = $this->reconcile_media_usage($db);
         $state['usage_rows_analyzed'] = (int)($state['usage_rows_analyzed'] ?? 0) + (int)($done['analyzed'] ?? 0);
         $state['actual_media_references'] = (int)($done['actual_references'] ?? 0);
         $state['usage_rows_added'] = (int)($state['usage_rows_added'] ?? 0) + (int)($done['added'] ?? 0);
         $state['usage_rows_updated'] = (int)($state['usage_rows_updated'] ?? 0) + (int)($done['updated'] ?? 0);
         $state['usage_rows_removed'] = (int)($state['usage_rows_removed'] ?? 0) + (int)($done['removed'] ?? 0);
         $state['removed_orphan_usage'] = (int)($state['removed_orphan_usage'] ?? 0) + (int)($done['removed'] ?? 0);
         $reasons = is_array($done['reasons'] ?? null) ? $done['reasons'] : array();
         $state['usage_inactive_removed'] = (int)($state['usage_inactive_removed'] ?? 0) + (int)($reasons['inactive'] ?? 0);
         $state['usage_duplicate_removed'] = (int)($state['usage_duplicate_removed'] ?? 0) + (int)($reasons['duplicate'] ?? 0);
         $state['usage_stale_removed'] = (int)($state['usage_stale_removed'] ?? 0)
            + max(0, (int)($done['removed'] ?? 0) - (int)($reasons['inactive'] ?? 0) - (int)($reasons['duplicate'] ?? 0));
         if ((int)($done['removed'] ?? 0) < (int)($done['planned_removed'] ?? 0)) {
            $state['errors'] = (int)($state['errors'] ?? 0) + ((int)$done['planned_removed'] - (int)$done['removed']);
         }
         $state['phase'] = 'media_usage_reconcile';
         return 'Nutzungsanalyse';
      }

      if ($type === 'media_record_purge') {
         $done = $this->purge_invalid_media_records($db);
         $state['purged_media_records'] = (int)($state['purged_media_records'] ?? 0) + (int)($done['removed'] ?? 0);
         $state['deleted_thumbs'] = (int)($state['deleted_thumbs'] ?? 0) + (int)($done['deleted_thumbs'] ?? 0);
         if ((int)($done['removed'] ?? 0) < (int)($done['found'] ?? 0)) {
            $state['errors'] = (int)($state['errors'] ?? 0) + ((int)$done['found'] - (int)$done['removed']);
         }
         $state['phase'] = 'media_record_purge';
         return 'Medien-Datenbank';
      }

      if ($type === 'folder_sort_normalize') {
         $done = $this->normalize_content_folder_sorters($db);
         $state['folder_sorters_checked'] = (int)($state['folder_sorters_checked'] ?? 0) + (int)($done['checked'] ?? 0);
         $state['folder_sorters_updated'] = (int)($state['folder_sorters_updated'] ?? 0) + (int)($done['updated'] ?? 0);
         $state['folder_sort_parents'] = (int)($state['folder_sort_parents'] ?? 0) + (int)($done['parents'] ?? 0);
         $state['folder_sort_languages'] = (int)($state['folder_sort_languages'] ?? 0) + (int)($done['languages'] ?? 0);
         $state['errors'] = (int)($state['errors'] ?? 0) + (int)($done['errors'] ?? 0);
         $state['phase'] = 'folder_sort_normalize';
         return 'Ordner-Reihenfolge';
      }

      if ($type === 'usage_cleanup') {
         $done = $this->cleanup_orphan_media_usage($db);
         $state['removed_orphan_usage'] = (int)($state['removed_orphan_usage'] ?? 0) + (int)($done['removed'] ?? 0);
         if ((int)($done['removed'] ?? 0) < (int)($done['found'] ?? 0)) {
            $state['errors'] = (int)($state['errors'] ?? 0) + ((int)$done['found'] - (int)$done['removed']);
         }
         $state['phase'] = 'media_usage_cleanup';
         return 'Zuordnungen';
      }

      if ($type === 'database_optimize') {
         $optimized = 0;
         foreach (array($this->dd_media_usage, dbxContentLng::dd_content()) as $dd) {
            if ((int)$db->optimize_tab($dd) === 1) $optimized++;
            else $state['errors'] = (int)($state['errors'] ?? 0) + 1;
         }
         $state['database_optimized'] = $optimized;
         $state['phase'] = 'database_optimize';
         return 'Datenbanken';
      }

      if ($type === 'record') {
         $id = (int)($task['id'] ?? 0);
         $row = $id > 0 ? $db->select1($this->dd_media, $id) : array();
         if (!is_array($row)) return '#' . $id;

         if (strtolower((string)($row['storage_type'] ?? '')) === 'external' || $this->media_type($row) === 'external_video') {
            if (!$this->media_file_exists($row) && (int)($row['active'] ?? 0) !== 0) {
               $db->update($this->dd_media, array('active' => 0), $id);
               $state['deactivated_media'] = (int)($state['deactivated_media'] ?? 0) + 1;
            }
            $state['phase'] = 'media_cleanup';
            return (string)($row['title'] ?? ('#' . $id));
         }

         $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         $file = $this->file_from_media_rel($rel);
         if ($file === '' || !is_file($file) || !is_readable($file)) {
            $thumb = $this->source_thumb_file($row);
            if ($thumb !== '' && @unlink($thumb)) {
               $state['deleted_thumbs'] = (int)($state['deleted_thumbs'] ?? 0) + 1;
            }
            if ((int)($row['active'] ?? 0) !== 0 || !empty($row['thumb_file_path'])) {
               $db->update($this->dd_media, array(
                  'active' => 0,
                  'thumb_file_path' => '',
                  'thumb_width' => 0,
                  'thumb_height' => 0,
               ), $id);
               $state['deactivated_media'] = (int)($state['deactivated_media'] ?? 0) + 1;
            }
            $state['phase'] = 'media_cleanup';
            return basename($rel);
         }

         $slot = $this->slot_from_media_rel($rel, (string)($row['slot'] ?? 'gallery'));
         $name = basename($file);
         $meta = $this->media_file_meta($file, $name);
         $update = array();
         if ((int)($row['active'] ?? 0) !== 1
             || (string)($row['slot'] ?? '') !== $slot
             || (string)($row['mime'] ?? '') !== $meta['mime']
             || (int)($row['size'] ?? 0) !== $meta['size']
             || (string)($row['media_type'] ?? '') !== $meta['media_type']) {
            $update = array(
               'active' => 1,
               'slot' => $slot,
               'usage' => $slot,
               'mime' => $meta['mime'],
               'size' => $meta['size'],
               'width' => $meta['width'],
               'height' => $meta['height'],
               'media_type' => $meta['media_type'],
            );
         }

         if (!$this->media_thumbnail_is_current($row)) {
            $old_thumb = $this->source_thumb_file($row);
            $thumb = $this->create_media_thumbnail($file, $slot, $name, $meta['mime']);
            if ($thumb) {
               $update = array_merge($update, $thumb);
               $state['created_thumbs'] = (int)($state['created_thumbs'] ?? 0) + 1;
               $new_thumb = $this->file_from_media_rel((string)($thumb['thumb_file_path'] ?? ''));
               if ($old_thumb !== '' && $old_thumb !== $new_thumb && is_file($old_thumb) && @unlink($old_thumb)) {
                  $state['deleted_thumbs'] = (int)($state['deleted_thumbs'] ?? 0) + 1;
               }
            }
         }

         if (!empty($update)) $db->update($this->dd_media, $update, $id);
         $state['phase'] = 'media_thumbs';
         return basename($rel);
      }

      return '';
   }



   private function tick_media_process($db, array &$state) {
      if (($state['status'] ?? '') !== 'running') return;
      $started = microtime(true);
      $limit = 8;
      $processed = 0;
      $current = '';
      $total = (int)($state['total'] ?? 0);

      while ((int)($state['pos'] ?? 0) < $total && $processed < $limit && (microtime(true) - $started) < 3.2) {
         $idx = (int)$state['pos'];
         $task = is_array($state['tasks'][$idx] ?? null) ? $state['tasks'][$idx] : array();
         $current = $this->run_media_process_task($db, $state, $task);
         $state['pos'] = $idx + 1;
         $processed++;
      }

      if ((int)($state['pos'] ?? 0) >= $total) {
         $state['status'] = 'finished';
         $state['phase'] = 'media_done';
         $state['tasks'] = array();
         $current = '';
      }

      $this->update_media_process_percent($state);
      $state['message'] = $this->media_process_message($state, $current);
      if (($state['status'] ?? '') === 'finished') $state['message'] = 'Fertig. ' . $state['message'];
      $state['updated_at'] = date('H:i:s');
   }



   private function render_media_process(array $state, $next_url) {
      $status = (string)($state['status'] ?? 'running');
      $percent = max(0, min(100, (int)($state['percent'] ?? 0)));
      $step_percent = max(0, min(100, (int)($state['step_percent'] ?? $percent)));
      $status_labels = array(
         'running' => 'Laeuft',
         'paused' => 'Angehalten',
         'canceled' => 'Abgebrochen',
         'finished' => 'Fertig',
         'error' => 'Fehler',
      );
      $status_class = $status === 'finished' ? 'bg-success' : ($status === 'error' || $status === 'canceled' ? 'bg-danger' : ($status === 'paused' ? 'bg-warning text-dark' : 'bg-primary'));
      $status_icon = $status === 'finished' ? 'bi bi-check-lg' : ($status === 'error' || $status === 'canceled' ? 'bi bi-exclamation-triangle' : ($status === 'paused' ? 'bi bi-pause-fill' : 'bi bi-play-fill'));
      $token = (string)($state['proc_key'] ?? '');
      $restart_url = $this->append_url_params($next_url, array('reset' => 1, 'proc_cmd' => 'restart'));
      $cancel_url = $this->append_url_params($next_url, array('proc_cmd' => 'cancel'));
      $resume_url = $this->append_url_params($next_url, array('proc_cmd' => 'resume'));
      $pause_url = $this->append_url_params($next_url, array('proc_cmd' => 'pause'));
      $autostart = ($status === 'running' && $next_url !== '') ? 1 : 0;
      $target_id = 'dbx_cms_media_process_' . substr(md5($token ?: 'media'), 0, 14);
      $report = '<div class="dbx-cms-media-maintenance-report" aria-label="Ergebnis der Medienwartung">'
         . '<div><strong>' . (int)($state['usage_rows_analyzed'] ?? 0) . '</strong><span>DB-Zuordnungen geprueft</span></div>'
         . '<div><strong>' . (int)($state['actual_media_references'] ?? 0) . '</strong><span>Echte Verwendungen erkannt</span></div>'
         . '<div><strong>' . (int)($state['usage_rows_added'] ?? 0) . '</strong><span>Zuordnungen ergaenzt</span></div>'
         . '<div><strong>' . (int)($state['usage_rows_updated'] ?? 0) . '</strong><span>Zuordnungen korrigiert</span></div>'
         . '<div><strong>' . (int)($state['usage_rows_removed'] ?? 0) . '</strong><span>Alt-/Fehleintraege entfernt</span></div>'
         . '<div><strong>' . (int)($state['purged_media_records'] ?? 0) . '</strong><span>Medien-Datensaetze entfernt</span></div>'
         . '<div><strong>' . (int)($state['cleaned_structured_refs'] ?? 0) . '</strong><span>Defekte Inhaltsverweise entfernt</span></div>'
         . '<div><strong>' . (int)($state['folder_sorters_updated'] ?? 0) . '</strong><span>Ordner-Sortierungen normalisiert</span></div>'
         . '<div><strong>' . (int)($state['database_optimized'] ?? 0) . '</strong><span>Datenbanken optimiert</span></div>'
         . '</div>';

      return '<div id="' . dbx()->esc($target_id) . '"'
         . ' class="container-fluid dbx-process dbx-cms-media-process"'
         . ' data-dbx="lib=process|id=' . dbx()->esc($target_id) . '|url=' . dbx()->esc($next_url) . '|interval=900|autostart=' . $autostart . '"'
         . ' data-process-status="' . dbx()->esc($status) . '"'
         . ' data-process-percent="' . $percent . '"'
         . ' data-process-step-percent="' . $step_percent . '"'
         . ' data-process-next-url="' . dbx()->esc($next_url) . '"'
         . ' data-process-pause-url="' . dbx()->esc($pause_url) . '"'
         . ' data-process-resume-url="' . dbx()->esc($resume_url) . '"'
         . ' data-process-cancel-url="' . dbx()->esc($cancel_url) . '"'
         . ' data-process-restart-url="' . dbx()->esc($restart_url) . '"'
         . ' data-process-autostart="' . $autostart . '"'
         . ' data-process-interval="900">'
         . '<div class="dbx-process-header">'
         . '<h3 class="dbx-process-title">Medienwartung</h3>'
         . '<span class="dbx-process-status badge ' . dbx()->esc($status_class) . '"><i class="' . dbx()->esc($status_icon) . '"></i> ' . dbx()->esc($status_labels[$status] ?? $status) . '</span>'
         . '</div>'
         . '<div class="dbx-process-grid">'
         . '<div class="dbx-process-progress"><div class="dbx-process-progress-head"><span class="dbx-process-progress-label">Gesamt</span><span class="dbx-process-progress-value" data-process-percent="overall">' . $percent . '%</span></div><div class="dbx-process-bar" role="progressbar" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"><div class="dbx-process-bar-fill" data-process-bar="overall" style="width:' . $percent . '%;"></div></div></div>'
         . '<div class="dbx-process-progress"><div class="dbx-process-progress-head"><span class="dbx-process-progress-label">Medien, Zuordnungen und Datenbank</span><span class="dbx-process-progress-value" data-process-percent="step">' . $step_percent . '%</span></div><div class="dbx-process-bar" role="progressbar" aria-valuenow="' . $step_percent . '" aria-valuemin="0" aria-valuemax="100"><div class="dbx-process-bar-fill" data-process-bar="step" style="width:' . $step_percent . '%;"></div></div></div>'
         . '<div class="dbx-process-message" data-process-message>' . dbx()->esc((string)($state['message'] ?? '')) . '</div>'
         . '<div class="dbx-process-meta"><span>Eintraege: ' . (int)($state['pos'] ?? 0) . ' / ' . (int)($state['total'] ?? 0) . '</span><span>Aktualisiert: ' . dbx()->esc((string)($state['updated_at'] ?? '')) . '</span></div>'
         . '</div>'
         . $report
         . '<div class="dbx-process-actions">'
         . '<button type="button" class="btn btn-warning btn-sm" data-process-action="pause" data-process-visible="running" data-dbx-tooltip="Anhalten"><i class="bi bi-pause-fill"></i></button>'
         . '<button type="button" class="btn btn-primary btn-sm" data-process-action="resume" data-process-visible="paused" data-dbx-tooltip="Weiter"><i class="bi bi-play-fill"></i></button>'
         . '<button type="button" class="btn btn-secondary btn-sm" data-process-action="restart" data-process-visible="paused,canceled,error,finished" data-dbx-tooltip="Neu starten"><i class="bi bi-arrow-clockwise"></i></button>'
         . '<button type="button" class="btn btn-danger btn-sm" data-process-action="cancel" data-process-visible="running,paused" data-dbx-tooltip="Abbrechen"><i class="bi bi-x-lg"></i></button>'
         . '</div>'
         . '</div>';
   }



   private function append_url_params($url, $params = array()) {
      return dbx()->append_url_params((string)$url, $params);
   }



   private function media_process() {
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      $token = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)($_GET['proc_key'] ?? ''));
      if ($token === '') $token = substr(md5(session_id() . microtime(true)), 0, 16);
      $reset = (int)($_GET['reset'] ?? 0);
      $cmd = strtolower(preg_replace('/[^a-z_]+/', '', (string)($_GET['proc_cmd'] ?? '')));

      if ($reset || $cmd === 'restart') {
         $state = $this->build_media_process_state($db, $token);
      } else {
         $state = $this->get_media_process_state($token);
         if (empty($state)) $state = $this->build_media_process_state($db, $token);
      }

      if ($cmd === 'pause' && ($state['status'] ?? '') === 'running') {
         $state['status'] = 'paused';
         $state['message'] = 'Medienwartung angehalten.';
      } elseif ($cmd === 'resume' && ($state['status'] ?? '') === 'paused') {
         $state['status'] = 'running';
         $state['message'] = 'Medienwartung fortgesetzt.';
      } elseif ($cmd === 'cancel') {
         $state['status'] = 'canceled';
         $state['message'] = 'Medienwartung abgebrochen.';
      }

      $this->tick_media_process($db, $state);
      $this->set_media_process_state($token, $state);

      $next = $this->base_url('cms_media_process', array('proc_key' => $token));
      return $this->render_media_process($state, $next);
   }
}
