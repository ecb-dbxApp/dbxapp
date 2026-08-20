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

trait dbxKiCmsReadServiceTrait {

   private function snapshot(array $params): array {
      return array(
         'language' => $this->language($params['lng'] ?? ''),
         'folders' => $this->folder_list($params),
         'pages' => $this->page_list($params),
         'media' => $this->media_list(array('limit' => $this->limit($params, 100, 200))),
         'module_assets' => $this->module_assets(array('limit' => 200)),
      );
   }

   private function folder_list(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $where = '';
      if (array_key_exists('parent_id', $params)) {
         $where = 'parent_id = ' . max(0, (int)$params['parent_id']);
      }
      $rows = $this->db->select(dbxContentLng::dd_folder($lng), $where, '*', 'sorter,id', 'ASC', '', $this->limit($params), 0, 1);
      return array('lng' => $lng, 'rows' => is_array($rows) ? $rows : array());
   }

   private function folder_get(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $row = $this->db->select1(dbxContentLng::dd_folder($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Ordner nicht gefunden.');
      return array('lng' => $lng, 'row' => $row);
   }

   private function page_list(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $where = '';
      if (array_key_exists('folder_id', $params)) {
         $where = 'folder = ' . max(0, (int)$params['folder_id']);
      }
      $rows = $this->db->select(dbxContentLng::dd_content($lng), $where, '*', 'folder,sorter,id', 'ASC', '', $this->limit($params), 0, 1);
      return array('lng' => $lng, 'rows' => is_array($rows) ? $rows : array());
   }

   private function page_get(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = $this->id($params);
      $row = $this->db->select1(dbxContentLng::dd_content($lng), $id);
      if (!is_array($row)) throw new \RuntimeException('Seite nicht gefunden.');
      $usage = $this->db->select('dbxMediaUsage', dbxContentMediaUsageScope::with_language('content_id = ' . $id . ' AND active = 1', $lng), '*', 'slot,sorter,id', 'ASC');
      $hint = $this->package_page_hint($row);
      return array(
         'lng' => $lng,
         'row' => $row,
         'media_usage' => is_array($usage) ? $usage : array(),
         'package_hint' => $hint,
      );
   }

   private function media_list(array $params): array {
      $rows = $this->db->select('dbxMedia', 'active = 1', '*', 'id', 'DESC', '', $this->limit($params), 0, 1);
      $type = strtolower(trim((string)($params['media_type'] ?? '')));
      $folder = trim((string)($params['folder'] ?? ''));
      $rows = array_values(array_filter(is_array($rows) ? $rows : array(), static function($row) use ($type, $folder) {
         if ($type !== '' && strtolower((string)($row['media_type'] ?? '')) !== $type) return false;
         if ($folder !== '' && (string)($row['media_folder'] ?? '') !== $folder) return false;
         return true;
      }));
      return array('rows' => $rows);
   }

   private function media_get(array $params): array {
      $id = $this->id($params);
      $row = $this->db->select1('dbxMedia', $id);
      if (!is_array($row)) throw new \RuntimeException('Medium nicht gefunden.');
      $usage = $this->db->select('dbxMediaUsage', 'media_id = ' . $id . ' AND active = 1', '*', 'id', 'ASC');
      return array('row' => $row, 'usage' => is_array($usage) ? $usage : array());
   }

   private function module_assets(array $params): array {
      $base = rtrim(str_replace('\\', '/', dbx()->get_base_dir()), '/') . '/';
      $module_filter = strtolower(trim((string)($params['module'] ?? '')));
      $limit = $this->limit($params, 200, 500);
      $rows = array();
      $seen = array();

      $add = static function(string $file, string $source) use (&$rows, &$seen, $base, $module_filter): void {
         $path = str_replace('\\', '/', $file);
         if (!is_file($file)) {
            return;
         }
         if (!preg_match('/\.(svg|png|jpe?g|webp|gif)$/i', $path)) {
            return;
         }

         $rel = str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
         $name = basename($path);
         $module = '';
         $action = '';
         if (preg_match('#dbx/modules/([^/]+)/tpl/mod/([^/]+)\.[^.]+$#', $rel, $m)) {
            $module = (string)$m[1];
            $stem = (string)$m[2];
         } else {
            $stem = preg_replace('/\.[^.]+$/', '', $name);
            if (preg_match('/^([A-Za-z0-9_]+)__(.+)$/', (string)$stem, $m)) {
               $module = (string)$m[1];
               $action = (string)$m[2];
            }
         }
         if ($action === '' && $module !== '') {
            $prefix = $module . '_';
            $stem = preg_replace('/\.[^.]+$/', '', $name);
            $action = str_starts_with((string)$stem, $prefix) ? substr((string)$stem, strlen($prefix)) : (string)$stem;
         }
         if ($module_filter !== '' && strtolower($module) !== $module_filter) {
            return;
         }
         $key = $rel;
         if (isset($seen[$key])) {
            return;
         }
         $seen[$key] = true;
         $rows[] = array(
            'module' => $module,
            'action' => $action,
            'name' => $name,
            'source' => $source,
            'path' => $rel,
            'src' => $rel,
            'bytes' => filesize($file),
         );
      };

      foreach (glob(dbx()->get_base_dir() . 'dbx/modules/*/tpl/mod/*') ?: array() as $file) {
         $add($file, 'module_tpl_mod');
         if (count($rows) >= $limit) {
            break;
         }
      }
      if (count($rows) < $limit) {
         foreach (glob(dbx()->get_base_dir() . 'files/mod/*') ?: array() as $file) {
            $add($file, 'files_mod');
            if (count($rows) >= $limit) {
               break;
            }
         }
      }

      return array(
         'rows' => $rows,
         'usage' => 'Im Content als <img src=\"{src}\" ...> verwenden. Vorhandene Modulbilder bevorzugen, wenn Module visuell dargestellt werden.',
      );
   }

   private function translation_preview(array $params): array {
      $source_lng = $this->language($params['source_lng'] ?? '');
      $target_lng = $this->language($params['target_lng'] ?? '');
      if ($source_lng === $target_lng) throw new \InvalidArgumentException('Quell- und Zielsprache müssen verschieden sein.');
      $source_id = $this->id($params, 'source_id');
      $source_dd = dbxContentLng::dd_content($source_lng);
      $source = $this->db->select1($source_dd, $source_id);
      if (!is_array($source)) throw new \RuntimeException('Quellseite nicht gefunden.');
      $uid = trim((string)($source['lng_uid'] ?? ''));
      $target_id = $uid !== ''
         ? dbxContentLngSync::resolve_id_by_uid($this->db, dbxContentLng::dd_content($target_lng), $uid, $target_lng)
         : 0;
      $target = $target_id > 0 ? $this->db->select1(dbxContentLng::dd_content($target_lng), $target_id) : null;
      return array(
         'source_lng' => $source_lng,
         'target_lng' => $target_lng,
         'source' => $source,
         'target' => $target,
         'source_uid_missing' => $uid === '',
         'instruction' => array(
            'translate_fields' => array(
               'title', 'description', 'keywords', 'content', 'seo_title',
               'img_alt_1', 'img_alt_2', 'img_alt_3',
               'img_des_1', 'img_des_2', 'img_des_3'
            ),
            'preserve' => 'HTML-Struktur, Links, data-cms-media-id, Platzhalter, IDs, CSS-Klassen und technische Attribute unverändert lassen.',
            'do_not_translate' => 'Dateipfade, URLs, Modulnamen, Template-Namen, Shortcodes und Code.',
            'quality' => 'Natürlich, fachlich korrekt und zur Zielsprache passend übersetzen. Keine zusätzlichen Aussagen erfinden.',
            'next_action' => 'translation.apply mit translation-Objekt aufrufen.',
         ),
      );
   }
}
