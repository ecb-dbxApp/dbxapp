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
 * Seiten-/Ordnerbaum, Sprachabdeckung und schemaunabhaengige Baumdarstellung.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsTreeServiceTrait {


   private function resolve_cms_page_id(): int {
      $id = (int)dbx()->get_modul_var('cid', 0, 'int');
      if ($id <= 0) {
         $id = (int)dbx()->get_modul_var('dbx_cid', 0, 'int');
      }
      if ($id <= 0) {
         $id = (int)dbx()->get_modul_var('rid', 0, 'int');
      }
      if ($id <= 0) {
         $id = (int)dbx()->get_modul_var('id', 0, 'int');
      }
      if ($id > 0) {
         return $id;
      }

      $raw_permalink = trim((string)dbx()->get_request_var('permalink', ''));
      $permalink = dbxContent_permalink::is_valid($raw_permalink)
         ? $raw_permalink
         : dbxContent_permalink::canonical_from_legacy($raw_permalink);
      $db = dbx()->get_system_obj('dbxDB');
      if ($permalink !== '') {
         $rec = $db->select1(dbxContentLng::dd_content(), array('permalink' => $permalink), 'id', 0);
         $permalink_id = is_array($rec) ? (int)($rec['id'] ?? 0) : 0;
         if ($permalink_id > 0) return $permalink_id;
      }

      // Eine initiale Seite wird mit einer kleinen Einzelabfrage bestimmt.
      // Dafuer muss der vollstaendige Content-Baum nicht aufgebaut werden.
      $rows = $db->select(
         dbxContentLng::dd_content(),
         '',
         'id',
         'sorter,title,id',
         'ASC',
         '',
         1,
         0,
         0
      );
      return is_array($rows) && isset($rows[0]) ? (int)($rows[0]['id'] ?? 0) : 0;
   }



   private function attach_unreachable_tree_nodes(array $tree): array {
      $reachable_page_ids = array();
      $reachable_folder_ids = array();
      foreach (is_array($tree['flat'] ?? null) ? $tree['flat'] : array() as $node) {
         if (!is_array($node)) {
            continue;
         }
         if (($node['_type'] ?? '') === 'page') {
            $reachable_page_ids[(int)($node['_id'] ?? 0)] = true;
         } elseif (($node['_type'] ?? '') === 'folder') {
            $reachable_folder_ids[(int)($node['_id'] ?? 0)] = true;
         }
      }

      $items = is_array($tree['items'] ?? null) ? $tree['items'] : array();
      $orphan_pages = array();
      foreach ($items as $row) {
         if (!is_array($row)) {
            continue;
         }
         $id = (int)($row['id'] ?? 0);
         if ($id > 0 && !isset($reachable_page_ids[$id])) {
            $orphan_pages[] = $row;
         }
      }
      if (!count($orphan_pages)) {
         return $tree;
      }

      $children = array();
      foreach ($orphan_pages as $row) {
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }
         $parent = (int)($row['folder'] ?? 0);
         $children[] = $row + array(
            '_node_id' => 'page-' . $id,
            '_type' => 'page',
            '_id' => $id,
            '_parent' => $parent,
            '_title' => (string)($row['title'] ?? ('Seite ' . $id)),
            '_rights' => (string)($row['group_read'] ?? ''),
            '_children' => array(),
            '_level' => 0,
         );
      }

      if (!count($children)) {
         return $tree;
      }

      $orphan_folder = array(
         'id' => -999001,
         'name' => 'Nicht im Baum',
         '_node_id' => 'folder--999001',
         '_type' => 'folder',
         '_id' => -999001,
         '_parent' => 0,
         '_title' => 'Nicht im Baum',
         '_rights' => '',
         '_children' => $children,
         '_level' => 0,
      );

      $tree['nodes'] = array_merge(is_array($tree['nodes'] ?? null) ? $tree['nodes'] : array(), array($orphan_folder));
      foreach ($children as $child) {
         $tree['flat'][] = $child;
      }
      $tree['flat'][] = $orphan_folder;

      return $tree;
   }



   private function cms_tree() {
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $tree = $db->select_tree(dbxContentLng::dd_folder(), dbxContentLng::dd_content(), array(
         'folder_order' => 'sorter,name,id',
         'item_order' => 'sorter,title,id',
         'verify_access' => 0,
      ));
      $tree = $this->attach_unreachable_tree_nodes($tree);
      return $this->decorate_tree($tree);
   }



   /**
    * Kompatibilitaetsmethode fuer bestehende Aufrufer.
    *
    * Das CMS-Schema ist vollstaendig in den DD-Dateien beschrieben. Ein
    * normaler Fachrequest darf keine DDL- oder Datenmigration ausloesen;
    * DD->DB-Synchronisation erfolgt ueber dbxAdmin/dbxDD.
    */
   public function ensure_schema($db) {
      return;
   }



   private function ensure_cms_schema($db) {
      return;
   }



   private function normalize_gallery_row(array $row) {
      $defaults = array(
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
      );

      foreach ($defaults as $field => $value) {
         $current = trim((string)($row[$field] ?? ''));
         if ($current === '' || strtolower($current) === 'parent') {
            $row[$field] = $value;
         }
      }

      return $row;
   }



   private function tree_records_by_id($db, string $dd, string $fields): array {
      $rows = $db->select($dd, '', $fields, 'id', 'ASC', '', 0, 0, 0);
      $out = array();
      foreach (is_array($rows) ? $rows : array() as $row) {
         $id = (int)($row['id'] ?? 0);
         if ($id > 0) $out[$id] = $row;
      }
      return $out;
   }



   private function tree_lng_coverage_rows($db): array {
      $out = array('folder' => array(), 'page' => array());
      foreach (dbxContentLngSync::accessible_lngs() as $lng) {
         foreach (array(
            'folder' => array(dbxContentLng::dd_folder($lng), 'id,lng_uid,lng_sync,name', 'name'),
            'page' => array(dbxContentLng::dd_content($lng), 'id,lng_uid,lng_sync,title', 'title'),
         ) as $entity => $definition) {
            [$dd, $fields, $title_field] = $definition;
            $rows = $db->select($dd, '', $fields, 'id', 'ASC', '', 0, 0, 0);
            foreach (is_array($rows) ? $rows : array() as $row) {
               $lng_uid = trim((string)($row['lng_uid'] ?? ''));
               if ($lng_uid === '') continue;
               $out[$entity][$lng_uid][$lng] = array(
                  'id' => (int)($row['id'] ?? 0),
                  'title' => (string)($row[$title_field] ?? ''),
                  'lng_sync' => strtolower(trim((string)($row['lng_sync'] ?? 'auto'))) ?: 'auto',
               );
            }
         }
      }
      return $out;
   }



   private function tree_lng_coverage(string $type, string $lng_uid, array $coverage_rows): array {
      $master = dbxContentLngSync::master_lng();
      $coverage = array(
         'lng_uid' => $lng_uid,
         'entity' => $type,
         'master_lng' => $master,
         'current_lng' => dbxContentLng::current(),
         'languages' => array(),
      );
      foreach (dbxContentLngSync::accessible_lngs() as $lng) {
         $row = $coverage_rows[$type][$lng_uid][$lng] ?? null;
         $sync = is_array($row) ? (string)($row['lng_sync'] ?? 'auto') : '';
         $coverage['languages'][$lng] = array(
            'lng' => $lng,
            'status' => is_array($row) ? ($lng === $master ? 'master' : ($sync ?: 'auto')) : 'missing',
            'id' => is_array($row) ? (int)($row['id'] ?? 0) : 0,
            'title' => is_array($row) ? (string)($row['title'] ?? '') : '',
            'lng_sync' => $sync,
            'is_master' => $lng === $master ? 1 : 0,
         );
      }
      return $coverage;
   }



   private function attach_lng_coverage(array $node, $db, array &$coverage_rows): array {
      $type = ($node['_type'] ?? '') === 'folder' ? 'folder' : 'page';
      $id = (int)($node['_id'] ?? 0);
      $lng_uid = trim((string)($node['lng_uid'] ?? $node['_lng_uid'] ?? ''));

      $node['_lng_uid'] = $lng_uid;
      if ($lng_uid !== '') {
         $current_lng = dbxContentLng::current();
         if (!isset($coverage_rows[$type][$lng_uid][$current_lng])) {
            $coverage_rows[$type][$lng_uid][$current_lng] = array(
               'id' => $id,
               'title' => (string)($node['_title'] ?? ''),
               'lng_sync' => strtolower(trim((string)($node['lng_sync'] ?? 'auto'))) ?: 'auto',
            );
         }
         $coverage = $this->tree_lng_coverage($type, $lng_uid, $coverage_rows);
         $node['_lng_coverage'] = $coverage;
         $node['_lng_badges'] = dbxContentLngSync::badges_html($coverage);
      }

      return $node;
   }



   private function decorate_tree_nodes(array $nodes, array &$flat, $db, array $folder_rows, array $page_rows, array &$coverage_rows) {
      $out = array();
      foreach ($nodes as $node) {
         if (!is_array($node)) continue;
         if (($node['_type'] ?? '') === 'folder' && (int)($node['_id'] ?? 0) > 0) {
            $full = $folder_rows[(int)$node['_id']] ?? null;
            if (is_array($full)) {
               foreach (array('template','group_read','hero_template','hero_image_id','hero_margin_top','hero_height','hero_variant','hero_sticky','hero_scroll_layer') as $key) {
                  if (array_key_exists($key, $full)) {
                     $node[$key] = $full[$key];
                     $node['_' . $key] = $full[$key];
                  }
               }
               $node['_template'] = $full['template'] ?? ($node['_template'] ?? '');
               $node['_rights'] = $full['group_read'] ?? ($node['_rights'] ?? '');
               $node['lng_uid'] = $full['lng_uid'] ?? '';
               $node['lng_sync'] = $full['lng_sync'] ?? 'auto';
            }
         }
         if (($node['_type'] ?? '') === 'page' && (int)($node['_id'] ?? 0) > 0) {
            $full = $page_rows[(int)$node['_id']] ?? null;
            if (is_array($full)) {
               $node['lng_uid'] = $full['lng_uid'] ?? '';
               $node['lng_sync'] = $full['lng_sync'] ?? 'auto';
            }
         }
         $node = $this->attach_lng_coverage($node, $db, $coverage_rows);
         $flat[] = $node;
         if (isset($node['_children']) && is_array($node['_children'])) {
            $node['_children'] = $this->decorate_tree_nodes($node['_children'], $flat, $db, $folder_rows, $page_rows, $coverage_rows);
         }
         $out[] = $node;
      }
      return $out;
   }



   private function decorate_tree(array $tree) {
      $db = dbx()->get_system_obj('dbxDB');
      $folder_rows = $this->tree_records_by_id(
         $db,
         dbxContentLng::dd_folder(),
         'id,template,group_read,hero_template,hero_image_id,hero_margin_top,hero_height,hero_variant,hero_sticky,hero_scroll_layer,lng_uid,lng_sync'
      );
      $page_rows = $this->tree_records_by_id($db, dbxContentLng::dd_content(), 'id,lng_uid,lng_sync');
      $coverage_rows = $this->tree_lng_coverage_rows($db);
      $flat = array();
      $tree['nodes'] = $this->decorate_tree_nodes(
         is_array($tree['nodes'] ?? null) ? $tree['nodes'] : array(),
         $flat,
         $db,
         $folder_rows,
         $page_rows,
         $coverage_rows
      );
      $tree['flat'] = $flat;
      return $tree;
   }
}
