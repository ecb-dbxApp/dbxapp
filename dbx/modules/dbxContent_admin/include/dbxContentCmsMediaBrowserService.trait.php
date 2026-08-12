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
 * Paginierter Medienbrowser, aktuelle Usage und Inline-/Hero-Zuordnung.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsMediaBrowserServiceTrait {


   private function media_json() {
      $content_id = (int)dbx()->get_modul_var('content_id', 0, 'int');
      $folder_id = (int)dbx()->get_modul_var('folder_id', 0, 'int');
      $images = (int)dbx()->get_modul_var('images', 0, 'int');
      $sync = (int)dbx()->get_modul_var('sync', 0, 'int');
      $media_type = strtolower(trim((string)dbx()->get_modul_var('media_type', '', 'varchar')));
      $raw_media_folder = trim((string)dbx()->get_modul_var('media_folder', '', 'varchar'));
      $media_folder = $raw_media_folder !== '' && strtolower($raw_media_folder) !== 'all'
         ? $this->clean_media_folder($raw_media_folder, $media_type ?: $this->media_type_from_folder($raw_media_folder))
         : '';
      $slot = trim((string)dbx()->get_modul_var('slot', '', 'varchar'));
      $query = trim((string)dbx()->get_modul_var('query', '', 'varchar'));
      $usage_only = (int)dbx()->get_modul_var('usage', 0, 'int');
      $limit = max(0, min(200, (int)dbx()->get_modul_var('limit', 0, 'int')));
      $offset = max(0, (int)dbx()->get_modul_var('offset', 0, 'int'));
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      if ($sync) $this->sync_cms_media_files($db);
      $where = 'active = 1' . $this->cms_media_exclude_sql();
      if ($images) {
         $where .= " AND (mime LIKE 'image/%' OR file_name LIKE '%.jpg' OR file_name LIKE '%.jpeg' OR file_name LIKE '%.png' OR file_name LIKE '%.gif' OR file_name LIKE '%.webp' OR file_name LIKE '%.svg')";
      }
      if (in_array($media_type, array('image','video','external_video','file'), true)) {
         $where .= " AND media_type = '" . str_replace("'", "''", $media_type) . "'";
      }
      if ($media_folder !== '') {
         $where .= " AND media_folder = '" . str_replace("'", "''", $media_folder) . "'";
      }
      if ($query !== '') {
         $q = str_replace("'", "''", $query);
         $where .= " AND (title LIKE '%$q%' OR alt LIKE '%$q%' OR caption LIKE '%$q%' OR tags LIKE '%$q%' OR file_name LIKE '%$q%')";
      }
      $select_limit = $limit > 0 ? $limit + 1 : 0;
      $rows = $db->select($this->dd_media, $where, '*', 'media_folder,title,id', 'ASC', '', $select_limit, $offset, 0);
      if (!is_array($rows)) $rows = array();
      $has_more = $limit > 0 && count($rows) > $limit;
      if ($has_more) $rows = array_slice($rows, 0, $limit);
      $page_row_count = count($rows);
      if ($limit <= 0) $rows = $this->filter_existing_media($db, $rows);

      $usage_where = 'active = 1';
      if ($limit > 0) {
         $page_media_ids = array_values(array_filter(array_map(function($row) {
            return (int)($row['id'] ?? 0);
         }, $rows)));
         $usage_where .= $page_media_ids
            ? ' AND media_id IN (' . implode(',', $page_media_ids) . ')'
            : ' AND 1 = 0';
      }
      $usage_rows = $db->select($this->dd_media_usage, $usage_where, '*', 'media_id,id', 'ASC', '', 0, 0, 0);
      $usage_count = array();
      $usage_pages = $this->media_usage_page_map($db, $usage_rows);
      $current_usage = array();
      $current_usage_row = array();
      $has_usage_context = $content_id > 0 || $folder_id > 0 || $slot !== '';
      if (is_array($usage_rows)) {
         foreach ($usage_rows as $usage) {
            if (!is_array($usage)) continue;
            $mid = (int)($usage['media_id'] ?? 0);
            if ($mid <= 0) continue;
            $usage_count[$mid] = ($usage_count[$mid] ?? 0) + 1;
            if ($has_usage_context
                && dbxContentMediaUsageScope::language((string)($usage['content_lng'] ?? '')) === dbxContentLng::current()
                && ($content_id <= 0 || (int)($usage['content_id'] ?? 0) === $content_id)
                && ($folder_id <= 0 || (int)($usage['folder_id'] ?? 0) === $folder_id)
                && ($slot === '' || (string)($usage['slot'] ?? '') === $slot)) {
               $current_usage[$mid] = (int)($usage['id'] ?? 0);
               $current_usage_row[$mid] = $usage;
            }
         }
      }
      $rows = array_map(function($row) use ($usage_count, $usage_pages, $current_usage, $current_usage_row) {
         $row = $this->normalize_media_row($row);
         $id = (int)($row['id'] ?? 0);
         $row['used_count'] = (int)($usage_count[$id] ?? 0);
         $row['usage_pages'] = $usage_pages[$id] ?? array();
         $row['current_usage_id'] = (int)($current_usage[$id] ?? 0);
         if (!empty($current_usage_row[$id])) {
            $usage = $current_usage_row[$id];
            $row['usage_id'] = (int)($usage['id'] ?? 0);
            $row['slot'] = (string)($usage['slot'] ?? $row['slot'] ?? 'gallery');
            $row['sorter'] = (string)($usage['sorter'] ?? $row['sorter'] ?? '');
            if (!empty($usage['template'])) $row['template'] = $usage['template'];
            if (!empty($usage['caption'])) $row['caption'] = $usage['caption'];
         }
         return $row;
      }, $rows);
      if ($usage_only && ($content_id > 0 || $folder_id > 0 || $slot !== '')) {
         $rows = array_values(array_filter($rows, function($row) {
            return (int)($row['current_usage_id'] ?? 0) > 0;
         }));
      }
      $this->cms_json_response(array(
         'ok' => 1,
         'rows' => $rows,
         'has_more' => $has_more ? 1 : 0,
         'next_offset' => $limit > 0 ? $offset + $page_row_count : 0,
      ));
   }



   private function media_usage_rows_for_context($db, $content_id = 0, $folder_id = 0, $slot = '', $content_lng = '') {
      $content_id = (int)$content_id;
      $folder_id = (int)$folder_id;
      $where = dbxContentMediaUsageScope::withLanguage('active = 1', $content_lng);
      if ($content_id > 0) $where .= ' AND content_id = ' . $content_id;
      if ($folder_id > 0) $where .= ' AND folder_id = ' . $folder_id;
      if ($slot !== '') $where .= " AND slot = '" . str_replace("'", "''", (string)$slot) . "'";

      $usage_rows = $db->select($this->dd_media_usage, $where, '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      if (!is_array($usage_rows)) return array();

      $media_ids = array();
      foreach ($usage_rows as $usage) {
         if (!is_array($usage)) continue;
         $media_id = (int)($usage['media_id'] ?? 0);
         if ($media_id > 0) $media_ids[$media_id] = $media_id;
      }
      $media_by_id = $this->rows_by_ids($db, $this->dd_media, array_values($media_ids));

      $rows = array();
      foreach ($usage_rows as $usage) {
         if (!is_array($usage)) continue;
         $media_id = (int)($usage['media_id'] ?? 0);
         if ($media_id <= 0) continue;
         $row = $media_by_id[$media_id] ?? null;
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) continue;
         if (!$this->media_file_exists($row)) continue;
         $row = $this->normalize_media_row($row);
         $row['usage_id'] = (int)($usage['id'] ?? 0);
         $row['current_usage_id'] = (int)($usage['id'] ?? 0);
         $row['slot'] = (string)($usage['slot'] ?? $row['slot'] ?? 'gallery');
         $row['sorter'] = (string)($usage['sorter'] ?? $row['sorter'] ?? '');
         if (!empty($usage['template'])) $row['template'] = $usage['template'];
         if (!empty($usage['caption'])) $row['caption'] = $usage['caption'];
         $rows[] = $row;
      }

      return $rows;
   }



   private function inline_media_block_has_content($inner) {
      $inner = trim((string)$inner);
      if ($inner === '') return false;
      if (preg_match('/<(img|video|iframe|source)\b/i', $inner)) return true;
      if (preg_match('/\bdbx-cms-inline-video-thumb\b/i', $inner)) return true;
      if (preg_match('/\bdbx-cms-inline-video-empty\b/i', $inner)) return true;
      if (preg_match('/\bdbx-cms-inline-media-missing\b/i', $inner)) return true;
      return false;
   }



   private function strip_empty_inline_media_wrappers($html) {
      $html = (string)$html;
      $pattern = '/<(p|figure|div)\b([^>]*(?:\bdbx-cms-inline-media\b|\bdata-cms-media-id=)[^>]*)>([\s\S]*?)<\/\1>/i';
      do {
         $next = preg_replace_callback(
            $pattern,
            function($m) {
               if ($this->inline_media_block_has_content($m[3] ?? '')) return $m[0];
               return '';
            },
            $html
         );
         if ($next === null || $next === $html) break;
         $html = $next;
      } while (true);
      return $html;
   }



   private function inline_media_ids($html) {
      $html = preg_replace('/<!--[\s\S]*?-->/', '', (string)$html);
      $ids = array();

      if (preg_match_all('/<(p|figure|div)\b([^>]*(?:\bdbx-cms-inline-media\b|\bdata-cms-media-id=)[^>]*)>([\s\S]*?)<\/\1>/i', $html, $blocks, PREG_SET_ORDER)) {
         foreach ($blocks as $block) {
            $inner = (string)($block[3] ?? '');
            if (!$this->inline_media_block_has_content($inner)) continue;
            if (preg_match_all('/<(?:img|video|source|iframe)\b[^>]*\bsrc=["\'][^"\']*dbx_mid=([0-9]+)/i', $inner, $inner_ids)) {
               foreach ($inner_ids[1] as $id) {
                  $id = (int)$id;
                  if ($id > 0) $ids[$id] = $id;
               }
               continue;
            }
            if (preg_match('/\bdata-cms-media-id=["\']?([0-9]+)/i', (string)($block[0] ?? ''), $id_match)) {
               $id = (int)$id_match[1];
               if ($id > 0) $ids[$id] = $id;
            }
         }
      }
      if (preg_match_all('/<(?:img|video|source|iframe)\b[^>]*\bdata-cms-media-id=["\']?([0-9]+)/i', $html, $m)) {
         foreach ($m[1] as $id) {
            $id = (int)$id;
            if ($id > 0) $ids[$id] = $id;
         }
      }
      if (preg_match_all('/<(?:img|video|source|iframe)\b[^>]*\bsrc=["\'][^"\']*dbx_mid=([0-9]+)/i', $html, $m)) {
         foreach ($m[1] as $id) {
            $id = (int)$id;
            if ($id > 0) $ids[$id] = $id;
         }
      }
      return array_values($ids);
   }



   private function resolve_inline_media_ids($html, $client_ids = null, $client_ids_provided = false) {
      $html_ids = array();
      foreach ($this->inline_media_ids($html) as $id) {
         $id = (int)$id;
         if ($id > 0) $html_ids[$id] = $id;
      }
      return array_values($html_ids);
   }



   private function sync_inline_media_usage($db, $content_id, $html, $folder_id = 0, $client_ids = null, $client_ids_provided = false, $content_lng = '') {
      $content_id = (int)$content_id;
      $folder_id = (int)$folder_id;
      $content_lng = dbxContentMediaUsageScope::language((string)$content_lng);
      $result = array('added' => 0, 'removed' => 0, 'kept' => 0);
      if ($content_id <= 0) return $result;

      $wanted = array();
      foreach ($this->resolve_inline_media_ids($html, $client_ids, $client_ids_provided) as $id) {
         $id = (int)$id;
         if ($id > 0) $wanted[$id] = $id;
      }

      $rows = $db->select(
         $this->dd_media_usage,
         dbxContentMediaUsageScope::withLanguage('content_id = ' . $content_id . " AND active = 1 AND slot = 'inline'", $content_lng),
         '*',
         'id'
      );
      if (is_array($rows)) {
         foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $usage_id = (int)($row['id'] ?? 0);
            $media_id = (int)($row['media_id'] ?? 0);
            if ($usage_id > 0 && $media_id > 0 && !isset($wanted[$media_id])) {
               $db->update($this->dd_media_usage, array('active' => 0), $usage_id, 0, 1, 1, 0);
               $result['removed']++;
            }
         }
      }

      foreach ($wanted as $id) {
         $row = $db->select1($this->dd_media, $id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) continue;

         $usages = $db->select(
            $this->dd_media_usage,
            dbxContentMediaUsageScope::withLanguage('media_id = ' . $id . ' AND content_id = ' . $content_id . " AND slot = 'inline'", $content_lng),
            '*', 'active DESC, id DESC', '', 0, 0, 0
         );

         $active_usage = null;
         $inactive_usage = null;
         if (is_array($usages)) {
            foreach ($usages as $usage) {
               if (!is_array($usage)) continue;
               if ((int)($usage['active'] ?? 0) === 1) {
                  if (!$active_usage) {
                     $active_usage = $usage;
                  } else {
                     $dup_id = (int)($usage['id'] ?? 0);
                     if ($dup_id > 0) {
                        $db->update($this->dd_media_usage, array('active' => 0), $dup_id, 0, 1, 1, 0);
                        $result['removed']++;
                     }
                  }
                  continue;
               }
               if (!$inactive_usage) $inactive_usage = $usage;
            }
         }

         if ($active_usage) {
            $usage_id = (int)($active_usage['id'] ?? 0);
            if ($usage_id > 0 && (int)($active_usage['folder_id'] ?? 0) !== $folder_id) {
               $db->update($this->dd_media_usage, array('folder_id' => $folder_id), $usage_id, 0, 1, 1, 0);
            }
            $result['kept']++;
            continue;
         }

         if ($inactive_usage) {
            $usage_id = (int)($inactive_usage['id'] ?? 0);
            if ($usage_id > 0) {
               $db->update($this->dd_media_usage, array('active' => 1, 'folder_id' => $folder_id), $usage_id, 0, 1, 1, 0);
               $result['added']++;
            }
            continue;
         }

         if ($this->create_media_usage($db, $id, $content_id, $folder_id, 'inline', 'image-inline', '', '', $content_lng) > 0) {
            $result['added']++;
         }
      }

      return $result;
   }



   private function next_media_sorter($db, $content_id, $slot) {
      $content_id = (int)$content_id;
      $slot = str_replace("'", "''", (string)$slot);
      $rows = $db->select($this->dd_media, "content_id = " . $content_id . " AND slot = '" . $slot . "' AND active = 1", '*', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int)($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }



   private function deactivate_existing_hero_media($db, $content_id, $folder_id, $except_id = 0, $content_lng = '') {
      $content_id = (int)$content_id;
      $folder_id = (int)$folder_id;
      $except_id = (int)$except_id;
      $content_lng = dbxContentMediaUsageScope::language((string)$content_lng);
      $except = $except_id > 0 ? ' AND media_id <> ' . $except_id : '';
      if ($content_id > 0) {
         $db->update($this->dd_media_usage, array('active' => 0), dbxContentMediaUsageScope::withLanguage("content_id = " . $content_id . " AND slot = 'hero' AND active = 1" . $except, $content_lng), 0, 1, 1, 0);
      } elseif ($folder_id > 0) {
         $db->update($this->dd_media_usage, array('active' => 0), dbxContentMediaUsageScope::withLanguage("folder_id = " . $folder_id . " AND slot = 'hero' AND active = 1" . $except, $content_lng), 0, 1, 1, 0);
      }
   }



   private function sync_saved_hero_media_usage($db, $content_id, $folder_id, $hero_image_id) {
      $content_id = (int)$content_id;
      $folder_id = (int)$folder_id;
      $hero_id = (int)$hero_image_id;
      if ($content_id <= 0 && $folder_id <= 0) return;
      if ($hero_id > 0) {
         $this->deactivate_existing_hero_media($db, $content_id, $folder_id, $hero_id);
         return;
      }
      $this->deactivate_existing_hero_media($db, $content_id, $folder_id, 0);
   }
}
