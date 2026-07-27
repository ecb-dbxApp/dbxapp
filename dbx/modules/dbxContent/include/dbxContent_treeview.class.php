<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';

class dbxContent_treeview {

   private function needsCmsRuntime() {
      return dbxContentLng::isCmsPermalinkMode();
   }

   private function render_frontend_view($cid, $pageContent) {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $i = dbx()->next_id();
      $title = $this->page_title($cid);

      return $tpl->get_tpl('dbxContent|content-page-frontend', array(
         'frame_id'      => 'dbx_content_view_' . $i,
         'cid'           => (string)(int)$cid,
         'frontend_head' => $tpl->get_tpl('dbxContent|content-frontend-head', array(
            'bar_title'               => $title,
            'bar_title_pre'           => $this->adminEditorBarHtml($cid, true),
            'bar_title_heading_attrs' => ' data-cms-page-title',
         )),
         'page_content'  => $pageContent,
      ));
   }

   private function treeToggleBarHtml() {
      if (!dbxContentLng::isCmsPermalinkMode()) {
         return '';
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxContent|content-view-bar-tree-toggle');
   }

   private function adminEditorBarHtml($cid = 0, $useWindow = false) {
      if (!dbx()->can('admin')) {
         return '';
      }

      $tpl = dbx()->get_system_obj('dbxTPL');
      $cid = (int)$cid;
      if ($useWindow && $cid > 0) {
         $url = $this->appUrl() . '?dbx_modul=dbxContent_admin&dbx_run1=cms&cid=' . $cid;
         return $tpl->get_tpl('dbxContent|content-view-bar-admin-win', array(
            'admin_url' => dbx()->esc($url),
         ));
      }

      return $tpl->get_tpl('dbxContent|content-view-bar-admin');
   }

   private function appUrl() {
      $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
      if ($script === '') return '';
      $dir = str_replace('\\', '/', dirname($script));
      if ($dir === '.' || $dir === '/' || $dir === '\\') return '/';
      return rtrim($dir, '/') . '/';
   }

   private function moduleFrameReplaces($barTitle, $i, $panelAttrs, $barAdmin = '', $barSubtitle = '', $barTreeToggle = '') {
      $tpl = dbx()->get_system_obj('dbxTPL');

      return array(
         'frame_id'              => 'dbx_content_tree_' . $i,
         'frame_panel_class'     => 'dbx-cms dbx-cms-view dbx-content-tree-view dbxReport',
         'frame_body_class'      => 'dbx-content-tree-body',
         'frame_panel_attrs'     => $panelAttrs,
         'frame_subbar'          => '',
         'frame_form_open'       => '',
         'frame_form_close'      => '',
         'frame_body_head'       => '',
         'frame_body_tail'       => '',
         'bar_class'             => 'dbx-module-bar dbx-cms-head',
         'bar_title_class'       => 'dbx-module-bar-titleblock',
         'bar_actions_class'     => 'dbx-module-bar-actions flex-nowrap',
         'bar_title'             => $barTitle,
         'bar_icon'              => 'bi-file-earmark-text',
         'bar_subtitle'          => $barSubtitle,
         'bar_title_pre'         => (string)$barAdmin . (string)$barTreeToggle,
         'bar_title_heading_attrs' => ' data-cms-page-title',
         'bar_middle'            => '',
         'bar_extra'             => '',
         'bar_actions'           => $tpl->get_tpl('dbxContent|content-view-bar-actions'),
      );
   }

   private function page_title($cid) {
      $cid = (int)$cid;
      if ($cid <= 0) {
         return 'Keine Seite ausgewaehlt';
      }

      $db = dbx()->get_system_obj('dbxDB');
      $row = $db->select1(dbxContentLng::ddContent(), $cid, 'title', 0);
      if (!is_array($row)) {
         return 'Seite #' . $cid;
      }

      $title = trim((string)($row['title'] ?? ''));
      return $title !== '' ? $title : ('Seite #' . $cid);
   }

   private function base_url($action, $params = array()) {
      $url = '?dbx_modul=dbxContent&dbx_run1=' . rawurlencode((string)$action);
      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      return $url;
   }

   private function root_id() {
      return dbx()->get_modul_var('root', 0, 'int');
   }

   private function user_has_access($groups) {
      $groups = trim((string)$groups);
      if ($groups === '') $groups = '*';
      return dbx()->can($groups);
   }

   private function dd_server($dd, $fallback) {
      $db = dbx()->get_system_obj('dbxDB');
      if (method_exists($db, 'load_dd')) {
         $sys = $db->load_dd($dd);
         $mod = $sys['dd_modul'] ?? 'dbx';
         $name = $sys['dd_name'] ?? '';
         if ($name && isset($_SESSION['dbx']['cache']['dd'][$mod][$name]['table']['server'])) {
            return $_SESSION['dbx']['cache']['dd'][$mod][$name]['table']['server'];
         }
      }
      return $fallback;
   }

   private function ensure_column($db, $server, $table, $name, $type) {
      $server = (string)$server;
      $table = preg_replace('/[^A-Za-z0-9_]+/', '', (string)$table);
      $name = preg_replace('/[^A-Za-z0-9_]+/', '', (string)$name);
      if ($server === '' || $table === '' || $name === '') return;

      $cols = $db->select_query($server, 'PRAGMA table_info(' . $table . ')');
      if (is_array($cols)) {
         foreach ($cols as $col) {
            if (isset($col['name']) && strtolower((string)$col['name']) === strtolower($name)) return;
         }
      }

      $db->exec($server, 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $type);
   }

   private function ensure_tree_schema($db) {
      static $done = false;
      if ($done) return;
      $done = true;

      $folder_server = $this->dd_server(dbxContentLng::ddFolder(), 'dbx|dbxContent.db3');
      $folder_table  = $db->get_dd_table(dbxContentLng::ddFolder());
      $this->ensure_column($db, $folder_server, $folder_table, 'sorter', "TEXT DEFAULT ''");
   }

   private function resolve_folder_rights($db, $folder_id, array $visited = array()) {
      $folder_id = (int)$folder_id;
      if ($folder_id <= 0) return '*';
      if (isset($visited[$folder_id])) return '*';
      $visited[$folder_id] = 1;

      $row = $db->select1(dbxContentLng::ddFolder(), $folder_id, '*', 0);
      if (!is_array($row)) return '*';

      $raw = trim((string)($row['group_read'] ?? ''));
      if ($raw === '') return '*';

      $parent_id = (int)($row['parent_id'] ?? 0);
      $parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
      $out = array();
      $uses_parent = false;

      foreach ($parts as $part) {
         $part = trim((string)$part);
         if ($part === '') continue;
         if (strtolower($part) === 'parent') {
            $uses_parent = true;
            continue;
         }
         $out[$part] = 1;
      }

      if ($uses_parent) {
         foreach (preg_split('/\s*,\s*/', $this->resolve_folder_rights($db, $parent_id, $visited), -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $part = trim((string)$part);
            if ($part !== '' && strtolower($part) !== 'parent') $out[$part] = 1;
         }
      }

      return count($out) ? implode(',', array_keys($out)) : '*';
   }

   private function resolve_page_rights($db, array $page) {
      return $this->resolve_folder_rights($db, (int)($page['folder'] ?? 0));
   }

   private function row_html(array $node) {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $type = ($node['_type'] ?? '') === 'folder' ? 'folder' : 'page';
      $title = dbx()->esc((string)($node['_title'] ?? ''));
      $data = array(
         '_type' => $type,
         '_id' => (string)(int)($node['_id'] ?? 0),
         '_parent' => (string)(int)($node['_parent'] ?? 0),
         'draggable_attr' => '',
         'icon' => $type === 'folder' ? '<i class="bi bi-folder2-open"></i>' : '<i class="bi bi-file-earmark-text"></i>',
         'folder_id_label' => '',
         'title_label' => $title,
         'rights_label' => '',
      );
      return $tpl->get_tpl('dbxContent|content-tree-row', $data);
   }

   private function decorate_nodes(array $nodes, array &$flat) {
      $db = dbx()->get_system_obj('dbxDB');
      $out = array();
      foreach ($nodes as $node) {
         if (!is_array($node)) continue;
         $type = ($node['_type'] ?? '') === 'folder' ? 'folder' : 'page';
         $id = (int)($node['_id'] ?? 0);
         if ($id <= 0) continue;

         if ($type === 'folder') {
            if (!$this->user_has_access($this->resolve_folder_rights($db, $id))) continue;
            $node['_children'] = $this->decorate_nodes(is_array($node['_children'] ?? null) ? $node['_children'] : array(), $flat);
            if (!count($node['_children'])) continue;
         } else {
            $page = $db->select1(dbxContentLng::ddContent(), $id, '*', 0);
            if (!is_array($page) || (int)($page['activ'] ?? 0) !== 1) continue;
            if (!$this->user_has_access($this->resolve_page_rights($db, $page))) continue;
            $node['_parent'] = (int)($page['folder'] ?? 0);
         }

         $node['_row_html'] = $this->row_html($node);
         $flat[] = $node;
         $out[] = $node;
      }
      return $out;
   }

   private function tree() {
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_tree_schema($db);
      $root = $this->root_id();
      if ($root > 0 && !$this->user_has_access($this->resolve_folder_rights($db, $root))) {
         return array(
            'nodes' => array(),
            'flat' => array(),
            'count_pages' => 0,
            'count_folders' => 0,
         );
      }

      $tree = $db->select_tree(dbxContentLng::ddFolder(), dbxContentLng::ddContent(), array(
         'root' => $root,
         'verify_access' => 0,
         'item_where' => 'activ = 1',
      ));
      $flat = array();
      $nodes = $this->decorate_nodes(is_array($tree['nodes'] ?? null) ? $tree['nodes'] : array(), $flat);

      return array(
         'nodes' => $nodes,
         'flat' => $flat,
         'count_pages' => count(array_filter($flat, function($node) { return ($node['_type'] ?? '') === 'page'; })),
         'count_folders' => count(array_filter($flat, function($node) { return ($node['_type'] ?? '') === 'folder'; })),
      );
   }

   private function render_page($id) {
      $id = (int)$id;
      if ($id <= 0) return '<div class="dbx-cms-empty">Bitte eine Seite im Content Tree waehlen.</div>';
      $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
      return $renderer->render($id);
   }

   private function render_view() {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $root = $this->root_id();
      $cid = (int)dbx()->get_modul_var('cid', 0, 'int');
      if (!$cid) $cid = (int)dbx()->get_modul_var('dbx_cid', 0, 'int');
      $isCms = dbxContentLng::isCmsPermalinkMode();
      $initial_content = $cid > 0
         ? $this->render_page($cid)
         : '<div class="dbx-cms-empty">Bitte eine Seite im Content Tree waehlen.</div>';

      if (!$this->needsCmsRuntime()) {
         return $this->render_frontend_view($cid, $initial_content);
      }

      $i = dbx()->next_id();
      $dataDbx = 'lib=cms|mode=view|id=dbx_content_tree_' . $i;
      if ($isCms) {
         $dataDbx .= '|tree=' . dbx()->esc($this->base_url('cms', array('dbx_run2' => 'tree', 'root' => $root)));
      }
      $dataDbx .= '|viewpage=' . dbx()->esc($this->base_url('cms', array('dbx_run2' => 'page', 'root' => $root)));
      $dataDbx .= '|cid=' . $cid;

      $panelAttrs = 'data-cms-initial-page-loaded="' . ($cid > 0 ? '1' : '0') . '" data-dbx="' . $dataDbx . '"';
      $frameTpl = $isCms ? 'content-view-frame-cms' : 'content-view-frame-content';
      $viewFrame = $tpl->get_tpl('dbxContent|' . $frameTpl, array(
         'initial_content' => $initial_content,
         'i'               => $i,
         'cms_tree_search' => $tpl->get_tpl('dbx|search', dbx()->search_defaults(array(
            'title'       => 'Tree durchsuchen',
            'extra_attrs' => 'data-cms-search',
            'data_role'   => '',
            'i'           => $i,
         ))),
      ));

      $data = array_merge(
         $this->moduleFrameReplaces(
            $this->page_title($cid),
            $i,
            $panelAttrs,
            $this->adminEditorBarHtml(),
            $isCms ? 'Content Tree' : '',
            $this->treeToggleBarHtml()
         ),
         array(
            'frame_panel_class' => 'dbx-cms dbx-cms-view ' . ($isCms ? 'dbx-content-tree-view' : 'dbx-content-show') . ' dbxReport',
            'frame_body_class'  => $isCms ? 'dbx-content-tree-body' : 'dbx-content-show-body',
            'i' => $i,
            'cid' => (string)$cid,
            'initial_loaded' => $cid > 0 ? '1' : '0',
            'initial_content' => $initial_content,
            'view_frame' => $viewFrame,
            'tree_url' => $isCms ? dbx()->esc($this->base_url('cms', array('dbx_run2' => 'tree', 'root' => $root))) : '',
            'view_page_url' => dbx()->esc($this->base_url('cms', array('dbx_run2' => 'page', 'root' => $root))),
         )
      );
      return $tpl->get_tpl('dbxContent|content-view', $data);
   }

   private function tree_json() {
      if (!dbxContentLng::isCmsPermalinkMode()) {
         dbx()->json_response(array('ok' => 0, 'msg' => 'Content Tree ist im Modus content deaktiviert.'), true);
      }

      dbx()->json_response(array('ok' => 1) + $this->tree(), true);
   }

   private function page_json() {
      if (!dbxContentLng::isCmsPermalinkMode()) {
         dbx()->json_response(array('ok' => 0, 'msg' => 'Content Tree ist im Modus content deaktiviert.'), true);
      }

      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $db = dbx()->get_system_obj('dbxDB');
      $row = $id > 0 ? $db->select1(dbxContentLng::ddContent(), $id, 'id,title', 0) : array();
      dbx()->json_response(array(
         'ok' => 1,
         'id' => $id,
         'title' => is_array($row) ? (string)($row['title'] ?? '') : '',
         'html' => $this->render_page($id),
      ), true);
   }

   public function run($action = 'view') {
      switch ($action) {
         case 'tree_view':
            return $this->tree_json();
         case 'tree_page':
            return $this->page_json();
         case 'view':
         default:
            return $this->render_view();
      }
   }
}

?>
