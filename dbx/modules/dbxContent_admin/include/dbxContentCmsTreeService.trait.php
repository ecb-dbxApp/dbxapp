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

      $rawPermalink = trim((string)dbx()->get_request_var('permalink', ''));
      $permalink = dbxContent_permalink::isValid($rawPermalink)
         ? $rawPermalink
         : dbxContent_permalink::canonicalFromLegacy($rawPermalink);
      $db = dbx()->get_system_obj('dbxDB');
      if ($permalink !== '') {
         $rec = $db->select1(dbxContentLng::ddContent(), array('permalink' => $permalink), 'id', 0);
         $permalinkId = is_array($rec) ? (int)($rec['id'] ?? 0) : 0;
         if ($permalinkId > 0) return $permalinkId;
      }

      // Eine initiale Seite wird mit einer kleinen Einzelabfrage bestimmt.
      // Dafuer muss der vollstaendige Content-Baum nicht aufgebaut werden.
      $rows = $db->select(
         dbxContentLng::ddContent(),
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
      $reachablePageIds = array();
      $reachableFolderIds = array();
      foreach (is_array($tree['flat'] ?? null) ? $tree['flat'] : array() as $node) {
         if (!is_array($node)) {
            continue;
         }
         if (($node['_type'] ?? '') === 'page') {
            $reachablePageIds[(int)($node['_id'] ?? 0)] = true;
         } elseif (($node['_type'] ?? '') === 'folder') {
            $reachableFolderIds[(int)($node['_id'] ?? 0)] = true;
         }
      }

      $items = is_array($tree['items'] ?? null) ? $tree['items'] : array();
      $orphanPages = array();
      foreach ($items as $row) {
         if (!is_array($row)) {
            continue;
         }
         $id = (int)($row['id'] ?? 0);
         if ($id > 0 && !isset($reachablePageIds[$id])) {
            $orphanPages[] = $row;
         }
      }
      if (!count($orphanPages)) {
         return $tree;
      }

      $children = array();
      foreach ($orphanPages as $row) {
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

      $orphanFolder = array(
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

      $tree['nodes'] = array_merge(is_array($tree['nodes'] ?? null) ? $tree['nodes'] : array(), array($orphanFolder));
      foreach ($children as $child) {
         $tree['flat'][] = $child;
      }
      $tree['flat'][] = $orphanFolder;

      return $tree;
   }



   private function cms_tree() {
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $tree = $db->select_tree(dbxContentLng::ddFolder(), dbxContentLng::ddContent(), array(
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
      foreach (dbxContentLngSync::accessibleLngs() as $lng) {
         foreach (array(
            'folder' => array(dbxContentLng::ddFolder($lng), 'id,lng_uid,lng_sync,name', 'name'),
            'page' => array(dbxContentLng::ddContent($lng), 'id,lng_uid,lng_sync,title', 'title'),
         ) as $entity => $definition) {
            [$dd, $fields, $titleField] = $definition;
            $rows = $db->select($dd, '', $fields, 'id', 'ASC', '', 0, 0, 0);
            foreach (is_array($rows) ? $rows : array() as $row) {
               $lngUid = trim((string)($row['lng_uid'] ?? ''));
               if ($lngUid === '') continue;
               $out[$entity][$lngUid][$lng] = array(
                  'id' => (int)($row['id'] ?? 0),
                  'title' => (string)($row[$titleField] ?? ''),
                  'lng_sync' => strtolower(trim((string)($row['lng_sync'] ?? 'auto'))) ?: 'auto',
               );
            }
         }
      }
      return $out;
   }



   private function tree_lng_coverage(string $type, string $lngUid, array $coverageRows): array {
      $master = dbxContentLngSync::masterLng();
      $coverage = array(
         'lng_uid' => $lngUid,
         'entity' => $type,
         'master_lng' => $master,
         'current_lng' => dbxContentLng::current(),
         'languages' => array(),
      );
      foreach (dbxContentLngSync::accessibleLngs() as $lng) {
         $row = $coverageRows[$type][$lngUid][$lng] ?? null;
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



   private function attach_lng_coverage(array $node, $db, array &$coverageRows): array {
      $type = ($node['_type'] ?? '') === 'folder' ? 'folder' : 'page';
      $id = (int)($node['_id'] ?? 0);
      $lngUid = trim((string)($node['lng_uid'] ?? $node['_lng_uid'] ?? ''));

      $node['_lng_uid'] = $lngUid;
      if ($lngUid !== '') {
         $currentLng = dbxContentLng::current();
         if (!isset($coverageRows[$type][$lngUid][$currentLng])) {
            $coverageRows[$type][$lngUid][$currentLng] = array(
               'id' => $id,
               'title' => (string)($node['_title'] ?? ''),
               'lng_sync' => strtolower(trim((string)($node['lng_sync'] ?? 'auto'))) ?: 'auto',
            );
         }
         $coverage = $this->tree_lng_coverage($type, $lngUid, $coverageRows);
         $node['_lng_coverage'] = $coverage;
         $node['_lng_badges'] = dbxContentLngSync::badgesHtml($coverage);
      }

      return $node;
   }



   private function decorate_tree_nodes(array $nodes, array &$flat, $db, array $folderRows, array $pageRows, array &$coverageRows) {
      $out = array();
      foreach ($nodes as $node) {
         if (!is_array($node)) continue;
         if (($node['_type'] ?? '') === 'folder' && (int)($node['_id'] ?? 0) > 0) {
            $full = $folderRows[(int)$node['_id']] ?? null;
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
            $full = $pageRows[(int)$node['_id']] ?? null;
            if (is_array($full)) {
               $node['lng_uid'] = $full['lng_uid'] ?? '';
               $node['lng_sync'] = $full['lng_sync'] ?? 'auto';
            }
         }
         $node = $this->attach_lng_coverage($node, $db, $coverageRows);
         $flat[] = $node;
         if (isset($node['_children']) && is_array($node['_children'])) {
            $node['_children'] = $this->decorate_tree_nodes($node['_children'], $flat, $db, $folderRows, $pageRows, $coverageRows);
         }
         $out[] = $node;
      }
      return $out;
   }



   private function decorate_tree(array $tree) {
      $db = dbx()->get_system_obj('dbxDB');
      $folderRows = $this->tree_records_by_id(
         $db,
         dbxContentLng::ddFolder(),
         'id,template,group_read,hero_template,hero_image_id,hero_margin_top,hero_height,hero_variant,hero_sticky,hero_scroll_layer,lng_uid,lng_sync'
      );
      $pageRows = $this->tree_records_by_id($db, dbxContentLng::ddContent(), 'id,lng_uid,lng_sync');
      $coverageRows = $this->tree_lng_coverage_rows($db);
      $flat = array();
      $tree['nodes'] = $this->decorate_tree_nodes(
         is_array($tree['nodes'] ?? null) ? $tree['nodes'] : array(),
         $flat,
         $db,
         $folderRows,
         $pageRows,
         $coverageRows
      );
      $tree['flat'] = $flat;
      return $tree;
   }
}
