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

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxContentMediaUsageMaintenance.class.php';

class dbxContent_cms extends \dbxObj {

   private const ACTION_TOKEN_SCOPE = 'dbxContent_admin.actions';

   private $dd_media   = 'dbxMedia';
   private $dd_media_usage = 'dbxMediaUsage';
   private $cms_form_security = array();
   private $cmsTexts = null;
   private $contentTemplateNames = null;

   /**
    * Sprachabhängige CMS-Texte aus der zentralen FD.
    *
    * Das getrennte Objekt verhindert, dass die drei auf derselben CMS-Seite
    * gerenderten dbxForm-Instanzen sich gegenseitig den aktiven FD-Kontext
    * überschreiben.
    */
   private function cms_texts() {
      if ($this->cmsTexts) return $this->cmsTexts;
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('cms-page-texts');
      $texts->_fd = 'dbxContent_admin|cms-page';
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->cmsTexts = $texts;
      return $this->cmsTexts;
   }

   private function cms_js_messages(): string {
      $texts = $this->cms_texts();
      $keys = array(
         'page_select_first', 'page_loading', 'page_load_error',
         'page_saved', 'page_save_error', 'page_deleted', 'page_delete_error',
         'page_duplicating', 'page_duplicated', 'page_duplicate_error',
         'folder_selected', 'folder_edit', 'folder_saved', 'folder_save_error',
         'folder_delete_error', 'folder_select_first', 'folder_name_required',
         'unsaved', 'saved', 'unsaved_title', 'saved_title',
         'page_save_title', 'folder_save_title', 'page_delete_title',
         'folder_delete_title', 'duplicate_title', 'duplicate_select_title',
         'selection_label', 'tree_show', 'tree_hide', 'right_show', 'right_hide',
         'editor_marker_tooltip', 'editor_media_tooltip', 'editor_module_tooltip',
         'editor_bootstrap_tooltip', 'editor_text_format', 'editor_horizontal_rule',
         'editor_columns_two', 'editor_columns_three',
         'editor_columns_first', 'editor_columns_second', 'editor_columns_third',
         'editor_columns_new', 'editor_columns_stacked', 'editor_columns_responsive',
         'editor_column_add', 'editor_columns_dissolve',
         'editor_context_menu', 'editor_context_undo', 'editor_context_redo',
          'editor_context_select_all', 'editor_context_block_up', 'editor_context_block_down',
          'editor_context_module', 'editor_context_video', 'editor_image_edit', 'editor_image_remove',
          'editor_context_copy',
         'editor_context_cut', 'editor_context_paste', 'editor_context_delete',
         'editor_save_all', 'editor_bold', 'editor_italic', 'editor_underline',
         'editor_strike', 'editor_align_left', 'editor_align_center',
         'editor_align_right', 'editor_align_justify', 'editor_print_break',
         'hero_parent_empty',
          'media_inline_empty', 'media_inline_focus', 'media_inline_edit',
          'media_inline_remove', 'media_inline_removed', 'media_gallery_empty', 'media_hero_empty',
         'media_area_empty', 'media_all_folders', 'media_folders_title',
         'media_no_folders', 'media_folder_empty', 'media_back',
         'media_folder_instruction', 'media_folder_label', 'media_count',
         'media_view', 'media_view_small', 'media_view_medium', 'media_view_large',
      );
      $messages = array();
      foreach ($keys as $key) $messages[$key] = $texts->get_fd_message($key);
      return json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   private function cms_json_response(array $data): void {
      if ($this->cms_form_security) {
         $data['form_security'] = $this->cms_form_security;
      }
      dbx()->json_response($data, true);
   }

   /**
    * Liefert die gemeinsame dbxForm-Fabrik fuer den CMS-Medienbrowser.
    */
   private function media_forms(): dbxContentMediaForms {
      return dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin');
   }

   /**
    * Prueft den dbxForm-Token eines Medienbrowser-POSTs.
    *
    * Der von dbxForm rotierte Folgetoken wird an cms_json_response()
    * weitergereicht, damit cms.js Mehrfachuploads ohne Seitenreload fortsetzen
    * kann.
    */
   private function verify_media_form(string $kind): bool {
      $action = $this->base_url($kind === 'external' ? 'cms_external_video' : 'cms_upload');
      $state = $this->media_forms()->verify($kind, $action);
      $this->cms_form_security = is_array($state['security'] ?? null) ? $state['security'] : array();
      return !empty($state['submitted']);
   }

   private function lng_provision_open_flag($db, string $entity, int $id): int {
      if (!dbxContentLngSync::isMasterLng() || !is_object($db)) {
         return 0;
      }

      if (count(dbxContentLngSync::slaveLngs()) <= 0) {
         return 0;
      }

      if ($id <= 0) {
         return 1;
      }

      return dbxContentLngSync::hasMissingSlaveLng($db, $entity, $id) ? 1 : 0;
   }

   private function apply_cms_lng_context(): void {
      $lng = strtolower(trim((string) dbx()->get_request_var('dbx_lng', '')));
      if ($lng === '') {
         return;
      }

      $allowed = dbxContentLngSync::accessibleLngs();
      if (!in_array($lng, $allowed, true)) {
         return;
      }

      dbx()->set_system_var('dbx_lng', $lng);
      dbx()->set_remember_var('dbx_lng', $lng, 'dbx');
   }

   private function ini_size_bytes($value) {
      $value = trim((string)$value);
      if ($value === '') return 0;
      $unit = strtolower(substr($value, -1));
      $num = (float)$value;
      if ($unit === 'g') $num *= 1024 * 1024 * 1024;
      elseif ($unit === 'm') $num *= 1024 * 1024;
      elseif ($unit === 'k') $num *= 1024;
      return (int)$num;
   }

   private function upload_max_bytes() {
      $upload = $this->ini_size_bytes(ini_get('upload_max_filesize'));
      $post = $this->ini_size_bytes(ini_get('post_max_size'));
      if ($upload <= 0) return $post;
      if ($post <= 0) return $upload;
      return min($upload, $post);
   }

   private function base_url($action, $params = array()) {
      $url = $this->app_url() . '?dbx_modul=dbxContent_admin&dbx_run1=' . rawurlencode((string)$action);
      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      if ($this->tokenized_action((string)$action)) {
         $url .= '&dbx_token=' . rawurlencode(dbx()->action_token(self::ACTION_TOKEN_SCOPE));
      }
      return $url;
   }

   /**
    * Aktionen, deren gerenderte Endpunkte einen Token benoetigen.
    *
    * cms_media ist grundsaetzlich lesend, kann mit sync=1 jedoch Dateien
    * einlesen und Datensaetze anlegen. Deshalb bekommt auch dieser gemischte
    * Endpunkt einen Token; serverseitig wird er nur fuer sync=1 verlangt.
    */
   private function tokenized_action(string $action): bool {
      return in_array($action, array(
         'cms_lng_provision',
         'cms_lng_provision_tree',
         'cms_lng_reset_sync',
         'cms_save',
         'cms_new_page',
         'cms_duplicate_page',
         'cms_new_folder',
         'cms_save_folder',
         'cms_delete_folder',
         'cms_delete_page',
         'cms_move_node',
         'cms_media',
         'cms_media_process',
         'cms_upload',
         'cms_external_video',
         'cms_media_folder_create',
         'cms_media_folder_delete',
         'cms_media_folder_rename',
         'cms_media_move',
         'cms_remove_media',
         'cms_delete_media',
         'cms_edit_media',
         'cms_set_media_slot',
         'cms_assign_media',
         'cms_sort_media',
      ), true);
   }

   private function action_requires_token(string $action): bool {
      if ($action === 'cms_media') {
         return (int)dbx()->get_modul_var('sync', 0, 'int') === 1;
      }
      return $this->tokenized_action($action);
   }

   private function check_action_token(string $action): bool {
      $token = (string)dbx()->get_modul_var('dbx_token', '', 'varchar|max=128');
      if (dbx()->check_action_token(self::ACTION_TOKEN_SCOPE, $token)) {
         return true;
      }

      dbx()->sys_msg(
         'security',
         'dbxContent_admin',
         $action,
         'CMS-Aktion ohne gueltigen Token abgewiesen',
         'ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
      );
      return false;
   }

   private function reject_action_token(string $action) {
      http_response_code(403);
      $message = 'Die Aktion wurde aus Sicherheitsgruenden abgewiesen. Bitte laden Sie das CMS neu.';
      if ($action === 'cms_media_process') {
         return '<div class="alert alert-danger m-3">' . dbx()->esc($message) . '</div>';
      }
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $message));
      return '';
   }

   private function app_url(): string {
      $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
      if ($script === '') {
         return '';
      }

      $dir = str_replace('\\', '/', dirname($script));
      if ($dir === '.' || $dir === '/' || $dir === '\\') {
         return '/';
      }

      return rtrim($dir, '/') . '/';
   }

   private function load_page_cache_classes(): void {
      dbx()->load_content_cache_classes();
   }

   private function sync_permalink_index($db, int $cid, string $lng = ''): void {
      $cid = (int) $cid;
      if ($cid <= 0 || !is_object($db)) {
         return;
      }

      $this->load_page_cache_classes();

      $lng = strtolower(trim($lng));
      $prevLng = null;
      if ($lng !== '' && $lng !== dbxContentLng::current()) {
         $prevLng = dbx()->get_system_var('dbx_lng', 'de');
         dbx()->set_system_var('dbx_lng', $lng);
      }

      $rec = $db->select1(dbxContentLng::ddContent(), $cid, 'permalink,activ,folder,title,seo_title,description,keywords,meta_robots,seo_image_id,update_date,lng_uid', 0);
      if (!is_array($rec)) {
         if ($prevLng !== null) {
            dbx()->set_system_var('dbx_lng', $prevLng);
         }
         return;
      }

      if ((int)($rec['activ'] ?? 0) !== 1) {
         dbxContentPermalinkIndex::removeByCid($cid, dbxContentLng::current());
         if (class_exists('dbx\\dbxContent\\dbxContentSitemap')) {
            \dbx\dbxContent\dbxContentSitemap::invalidate();
         }
         if ($prevLng !== null) {
            dbx()->set_system_var('dbx_lng', $prevLng);
         }
         return;
      }

      $renderer = new dbxContentRenderer();
      $rights = $renderer->getPublicFolderRights((int)($rec['folder'] ?? 0));
      $permalink = (string)($rec['permalink'] ?? '');

      dbxContentPageCache::writePageMeta($cid, array(
         'cid' => $cid,
         'permalink' => $permalink,
         'rights' => $rights,
         'activ' => (int)($rec['activ'] ?? 1),
         'saved_at' => date('c'),
         'seo' => dbxContentRenderer::seoMetaFromRecord($rec),
      ));

      if (trim($permalink) !== '') {
         dbxContentPermalinkIndex::upsertPage(
            $cid,
            $permalink,
            $rights,
            (int)($rec['activ'] ?? 1),
            dbxContentLng::current()
         );
      }

      dbxContentHome::refreshHomeCache($db, $cid, dbxContentLng::current());

      if (class_exists('dbx\\dbxContent\\dbxContentSitemap')) {
         \dbx\dbxContent\dbxContentSitemap::invalidate();
      }

      if ($prevLng !== null) {
         dbx()->set_system_var('dbx_lng', $prevLng);
      }
   }

   private function flush_lng_sync_cache($db, array $syncResult): void {
      if (!is_object($db) || !is_array($syncResult)) {
         return;
      }

      $updated = is_array($syncResult['updated'] ?? null) ? $syncResult['updated'] : array();
      if (!count($updated)) {
         return;
      }

      $this->load_page_cache_classes();

      $menuFlushed = false;
      foreach ($updated as $item) {
         if (!is_array($item)) {
            continue;
         }

         $id = (int) ($item['id'] ?? 0);
         $lng = trim((string) ($item['lng'] ?? ''));
         $entity = (($item['entity'] ?? 'page') === 'folder') ? 'folder' : 'page';
         if ($id <= 0) {
            continue;
         }

         if ($entity === 'page') {
            dbxContentPageCache::invalidateContent($id);
            if ($lng !== '') {
               $this->sync_permalink_index($db, $id, $lng);
            }
         } elseif (!$menuFlushed) {
            dbxContentPageCache::invalidateAllMenus();
            $menuFlushed = true;
         }
      }

      if (!$menuFlushed) {
         dbxContentPageCache::invalidateAllMenus();
      }
   }

   private function flush_saved_page_cache($db, int $cid): void {
      $cid = (int) $cid;
      if ($cid <= 0) {
         return;
      }

      $this->load_page_cache_classes();
      dbxContentPageCache::invalidateContent($cid);
      dbxContentPageCache::invalidateAllMenus();
      $this->sync_permalink_index($db, $cid);
   }

   private function flush_deleted_page_cache(int $cid, string $lng = ''): void {
      $cid = (int) $cid;
      if ($cid <= 0) {
         return;
      }

      $this->load_page_cache_classes();

      $lng = strtolower(trim($lng));
      if ($lng === '') {
         $lng = dbxContentLng::current();
      }

      dbxContentPageCache::invalidateContent($cid);
      dbxContentPageCache::invalidateAllMenus();
      dbxContentPermalinkIndex::removeByCid($cid, $lng);
   }

   private function copy_page_media_usage(
      $db,
      int $sourceCid,
      int $targetCid,
      int $targetFolderId,
      bool $replace = true,
      string $sourceLng = '',
      string $targetLng = ''
   ): int {
      $sourceCid = (int) $sourceCid;
      $targetCid = (int) $targetCid;
      $targetFolderId = (int) $targetFolderId;
      $sourceLng = dbxContentMediaUsageScope::language($sourceLng);
      $targetLng = dbxContentMediaUsageScope::language($targetLng);
      if ($sourceCid <= 0 || $targetCid <= 0 || !is_object($db)) {
         return 0;
      }

      $copySlots = array('hero', 'gallery', 'header', 'teaser', 'footer');
      if ($replace) {
         foreach ($copySlots as $slot) {
            $db->update(
               $this->dd_media_usage,
               array('active' => 0),
               dbxContentMediaUsageScope::withLanguage(
                  "content_id = " . $targetCid . " AND slot = '" . str_replace("'", "''", $slot) . "' AND active = 1",
                  $targetLng
               ),
               0,
               1,
               1,
               0
            );
         }
      }

      $rows = $db->select(
         $this->dd_media_usage,
         dbxContentMediaUsageScope::withLanguage(
            "content_id = " . $sourceCid . " AND active = 1 AND slot IN ('hero','gallery','header','teaser','footer')",
            $sourceLng
         ),
         '*',
         'slot,sorter,id',
         'ASC',
         '',
         0,
         0,
         0
      );
      if (!is_array($rows)) {
         return 0;
      }

      $copied = 0;
      foreach ($rows as $usage) {
         if (!is_array($usage)) {
            continue;
         }
         $mediaId = (int) ($usage['media_id'] ?? 0);
         if ($mediaId <= 0) {
            continue;
         }
         $media = $db->select1($this->dd_media, $mediaId, 'active', 0);
         if (!is_array($media) || (int) ($media['active'] ?? 0) !== 1) {
            continue;
         }

         $slot = $this->valid_media_slot($usage['slot'] ?? 'gallery');
         if ($slot === 'inline') {
            continue;
         }

         $usageId = $this->create_media_usage(
            $db,
            $mediaId,
            $targetCid,
            $targetFolderId,
            $slot,
            (string) ($usage['template'] ?? ''),
            (string) ($usage['caption'] ?? ''),
            (string) ($usage['settings'] ?? ''),
            $targetLng
         );
         if ($usageId > 0) {
            $copied++;
         }
      }

      return $copied;
   }

   private function sync_lng_page_media_from_master($db, int $masterCid, int $slaveCid, string $lng): int {
      $masterCid = (int) $masterCid;
      $slaveCid = (int) $slaveCid;
      $lng = strtolower(trim($lng));
      if ($masterCid <= 0 || $slaveCid <= 0 || $lng === '' || !is_object($db)) {
         return 0;
      }

      $slaveRow = $db->select1(dbxContentLng::ddContent($lng), $slaveCid, 'content,folder', 0);
      if (!is_array($slaveRow)) {
         return 0;
      }

      $slaveFolder = (int) ($slaveRow['folder'] ?? 0);
      $copied = $this->copy_page_media_usage(
         $db,
         $masterCid,
         $slaveCid,
         $slaveFolder,
         true,
         dbxContentLngSync::masterLng(),
         $lng
      );
      $this->sync_inline_media_usage($db, $slaveCid, (string) ($slaveRow['content'] ?? ''), $slaveFolder, null, false, $lng);
      return $copied;
   }

   private function apply_lng_sync_media($db, int $masterCid, array $syncResult): int {
      $master = dbxContentLngSync::masterLng();
      $copied = 0;
      foreach (($syncResult['updated'] ?? array()) as $item) {
         if (!is_array($item) || (($item['entity'] ?? 'page') !== 'page')) {
            continue;
         }
         $lng = strtolower(trim((string) ($item['lng'] ?? '')));
         $slaveId = (int) ($item['id'] ?? 0);
         if ($slaveId <= 0 || $lng === '' || $lng === $master) {
            continue;
         }
         $copied += $this->sync_lng_page_media_from_master($db, $masterCid, $slaveId, $lng);
      }
      return $copied;
   }

   private function apply_lng_provision_media($db, string $type, int $masterId, array $result): int {
      if ($type !== 'page' || !is_object($db)) {
         return 0;
      }

      $copied = 0;
      $items = array_merge(
         is_array($result['created'] ?? null) ? $result['created'] : array(),
         is_array($result['updated'] ?? null) ? $result['updated'] : array()
      );
      foreach ($items as $item) {
         if (!is_array($item)) {
            continue;
         }
         $lng = strtolower(trim((string) ($item['lng'] ?? '')));
         $slaveId = (int) ($item['id'] ?? 0);
         if ($slaveId <= 0 || $lng === '') {
            continue;
         }
         $copied += $this->sync_lng_page_media_from_master($db, $masterId, $slaveId, $lng);
      }
      return $copied;
   }

   private function flush_deleted_folder_cache($db, int $folderId, string $lng = ''): void {
      $folderId = (int) $folderId;
      if ($folderId < 0 || !is_object($db)) {
         return;
      }

      $this->load_page_cache_classes();

      $lng = strtolower(trim($lng));
      $prevLng = null;
      if ($lng !== '' && $lng !== dbxContentLng::current()) {
         $prevLng = dbx()->get_system_var('dbx_lng', 'de');
         dbx()->set_system_var('dbx_lng', $lng);
      }

      dbxContentPageCache::invalidateFolderTree($db, $folderId);
      dbxContentPageCache::invalidateAllMenus();

      if ($prevLng !== null) {
         dbx()->set_system_var('dbx_lng', $prevLng);
      }
   }

   private function flush_folder_cache($db, int $folderId): void {
      $folderId = (int) $folderId;
      if ($folderId < 0 || !is_object($db)) {
         return;
      }

      $this->load_page_cache_classes();
      dbxContentPageCache::invalidateFolderTree($db, $folderId);
      dbxContentPageCache::invalidateAllMenus();
   }

   private function flush_menu_cache(): void {
      $this->load_page_cache_classes();
      dbxContentPageCache::invalidateAllMenus();
   }

   private function flush_media_cache($db, int $content_id = 0, int $folder_id = 0): void {
      if ($content_id <= 0 && $folder_id <= 0) {
         return;
      }

      $this->load_page_cache_classes();
      if ($content_id > 0) {
         dbxContentPageCache::invalidateContent($content_id);
      }
      if ($folder_id > 0 && is_object($db)) {
         dbxContentPageCache::invalidateFolderTree($db, $folder_id);
      }
      dbxContentPageCache::invalidateAllMenus();
   }

   private function flush_media_by_media_id($db, int $media_id): void {
      $media_id = (int) $media_id;
      if ($media_id <= 0 || !is_object($db)) {
         return;
      }

      $usage_rows = $db->select($this->dd_media_usage, 'media_id = ' . $media_id . ' AND active = 1', '*', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($usage_rows)) {
         return;
      }

      foreach ($usage_rows as $usage) {
         if (!is_array($usage)) {
            continue;
         }
         $this->flush_media_cache(
            $db,
            (int)($usage['content_id'] ?? 0),
            (int)($usage['folder_id'] ?? 0)
         );
      }
   }

   private function request_json() {
      $data = array();

      $raw = file_get_contents('php://input');
      if (is_string($raw) && trim($raw) !== '') {
         $decoded = json_decode($raw, true);
         if (is_array($decoded)) {
            $data = $decoded;
         }
      }

      if (!count($data) && !empty($_POST) && is_array($_POST)) {
         $data = $_POST;
      }

      return $data;
   }

   private function normalize_content_media_urls($html) {
      $html = (string)$html;
      $html = preg_replace_callback('/<figure\b([^>]*dbx-cms-inline-video-block[^>]*)>[\s\S]*?<\/figure>/i', function($m) {
         $attrs = $m[1];
         if (!preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $attrs, $id_match)
            && !preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $m[0], $id_match)
            && !preg_match('/dbx_mid=([0-9]+)/i', $m[0], $id_match)) return $m[0];
         $id = (int)$id_match[1];
         $placeholder = $this->inline_media_placeholder_html($id);
         if ($placeholder === '') return $m[0];
         $attrs = preg_replace('/\scontenteditable=(["\']).*?\1/i', '', $attrs);
         $attrs = preg_replace('/\sdata-cms-media-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);
         $attrs .= ' data-cms-media-id="' . $id . '"';
         if (stripos($attrs, 'data-cms-media-slot=') === false) $attrs .= ' data-cms-media-slot="inline"';
         return '<figure' . $attrs . '>' . $placeholder . '</figure>';
      }, $html);
      /*
       * Alte CMS-Seiten konnten Videos als freies <video><source ...></video>
       * enthalten. Der Editor arbeitet inzwischen mit stabilen, über die
       * Medien-ID gerenderten Platzhaltern. Die Konvertierung hält bestehende
       * Inhalte beim nächsten Speichern kompatibel.
       */
      $html = preg_replace_callback('/<video\b([^>]*)>([\s\S]*?)<\/video>/i', function($m) {
         $attrs = (string)($m[1] ?? '');
         $inner = (string)($m[2] ?? '');
         $source = $attrs . ' ' . $inner;
         $id = 0;
         if (preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $source, $id_match)
            || preg_match('/dbx_mid=([0-9]+)/i', $source, $id_match)) {
            $id = (int)($id_match[1] ?? 0);
         }
         if ($id <= 0) return $m[0];

         $placeholder = $this->inline_media_placeholder_html($id);
         if ($placeholder === '') return $m[0];

         $video_attrs = ' class="dbx-cms-inline-media dbx-cms-inline-video-block"'
            . ' data-cms-media-id="' . $id . '" data-cms-media-slot="inline"';
         foreach (array('autoplay', 'loop', 'muted') as $option) {
            $enabled = preg_match('/(?:^|\s)' . $option . '(?:\s|=|$)/i', $attrs) === 1;
            $video_attrs .= ' data-cms-video-' . $option . '="' . ($enabled ? '1' : '0') . '"';
         }
         return '<figure' . $video_attrs . '>' . $placeholder . '</figure>';
      }, $html);
      $html = preg_replace('/<(video|iframe|source)\b[^>]*data-cms-media-id=["\']?([0-9]+)[^>]*>(?:\s*<\/\1>)?/i', '', $html);
      $html = preg_replace_callback('/<(img|video|iframe|source)\b([^>]*?)>/i', function($m) {
         $tag = $m[0];
         $tag_name = strtolower((string)($m[1] ?? ''));
         $id = 0;
         if (preg_match('/dbx_mid=([0-9]+)/i', $tag, $id_match)) {
            $id = (int)$id_match[1];
            $tag = preg_replace('/\sdata-cms-media-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $tag);
            $tag = preg_match('/\/\s*>$/', $tag)
               ? preg_replace('/\s*\/\s*>$/', ' data-cms-media-id="' . $id . '" />', $tag)
               : preg_replace('/\s*>$/', ' data-cms-media-id="' . $id . '">', $tag);
         } elseif (preg_match('/data-cms-media-id=["\']?([0-9]+)/i', $tag, $id_match)) {
            $id = (int)$id_match[1];
         }
         if ($tag_name === 'img' && $id > 0 && !$this->inline_media_available($id)) {
            return $this->inline_media_missing_html($id);
         }
         if (stripos($tag, 'data-cms-media-slot=') === false && $id > 0) {
            $tag = preg_match('/\/\s*>$/', $tag)
               ? preg_replace('/\s*\/\s*>$/', ' data-cms-media-slot="inline" />', $tag)
               : preg_replace('/\s*>$/', ' data-cms-media-slot="inline">', $tag);
         }
         return $tag;
      }, $html);
      $html = preg_replace_callback('/<(p|figure|div)\b([^>]*\bclass\s*=\s*(?:"[^"]*\bdbx-cms-inline-media\b[^"]*"|\'[^\']*\bdbx-cms-inline-media\b[^\']*\')[^>]*)>([\s\S]*?)<\/\1>/i', function($m) {
         $tag = (string)($m[1] ?? 'p');
         $attrs = (string)($m[2] ?? '');
         $inner = (string)($m[3] ?? '');
         if (stripos($attrs, 'dbx-cms-inline-video-block') !== false) {
            return $m[0];
         }
         if (!preg_match('/<img\b[^>]*\bsrc\s*=\s*(?:"[^"]*dbx_mid=([0-9]+)[^"]*"|\'[^\']*dbx_mid=([0-9]+)[^\']*\')/i', $inner, $id_match)) {
            return $m[0];
         }
         $id = (int)(($id_match[1] ?? 0) ?: ($id_match[2] ?? 0));
         if ($id <= 0) return $m[0];
         $attrs = preg_replace('/\sdata-cms-media-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);
         if (stripos($attrs, 'data-cms-media-slot=') === false) $attrs .= ' data-cms-media-slot="inline"';
         $inner = preg_replace_callback('/<img\b([^>]*)>/i', function($img) use ($id) {
            $img_attrs = (string)($img[1] ?? '');
            if (!preg_match('/\bsrc\s*=\s*(?:"[^"]*dbx_mid=' . $id . '\b[^"]*"|\'[^\']*dbx_mid=' . $id . '\b[^\']*\')/i', $img_attrs)) {
               return $img[0];
            }
            $img_attrs = preg_replace('/\sdata-cms-media-id\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $img_attrs);
            if (stripos($img_attrs, 'data-cms-media-slot=') === false) $img_attrs .= ' data-cms-media-slot="inline"';
            return '<img' . $img_attrs . ' data-cms-media-id="' . $id . '">';
         }, $inner, 1);
         return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
      }, $html);
      $html = $this->strip_empty_inline_media_wrappers($html);
      $html = str_replace(
         array(
            '?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=',
            '?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=',
            'index.phpindex.php?',
         ),
         array(
            'index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=',
            'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=',
            'index.php?',
         ),
         $html
      );
      return $html;
   }

   private function content_node_has_visible_value($node): bool {
      foreach ($node->childNodes as $child) {
         if ($child instanceof \DOMText) {
            $value = preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', (string)$child->nodeValue);
            if ($value !== '') return true;
            continue;
         }

         if (!($child instanceof \DOMElement)) continue;
         $tag = strtolower($child->tagName);
         if (in_array($tag, array('br', 'wbr'), true)) continue;
         if (in_array($tag, array('img', 'video', 'audio', 'iframe', 'object', 'embed', 'table', 'hr', 'svg', 'canvas', 'input', 'textarea', 'select', 'button'), true)) {
            return true;
         }
         if ($this->content_node_has_visible_value($child)) return true;
      }
      return false;
   }

   private function strip_empty_content_paragraphs_fallback(string $html): string {
      $pattern = '#<p\b[^>]*>(?:\s|&nbsp;|&#0*160;|&#x0*a0;|<br\b[^>]*>|<wbr\b[^>]*>|<!--[\s\S]*?-->)*</p>#iu';
      do {
         $clean = preg_replace($pattern, '', $html);
         if ($clean === null || $clean === $html) break;
         $html = $clean;
      } while (true);
      return $html;
   }

   private function safe_content_style(string $style, string $tag, string $className = ''): string {
      $tag = strtolower(trim($tag));
      $className = strtolower(trim($className));
      $isMediaLayout = in_array($tag, array('img', 'video', 'iframe', 'figure'), true)
         || preg_match('/(?:^|\s)dbx-cms-inline-(?:media|image|video|video-block)(?:\s|$)/', $className);
      $safe = array();

      foreach (explode(';', $style) as $declaration) {
         if (strpos($declaration, ':') === false) continue;
         list($property, $value) = array_map('trim', explode(':', $declaration, 2));
         $property = strtolower($property);
         $value = strtolower($value);
         if ($property === '' || $value === '') continue;

         if ($property === 'text-align' && in_array($value, array('left', 'right', 'center', 'justify', 'start', 'end'), true)) {
            $safe[$property] = $value;
            continue;
         }

         if (!$isMediaLayout) continue;
         if ($property === 'float' && in_array($value, array('left', 'right', 'none'), true)) {
            $safe[$property] = $value;
            continue;
         }
         if ($property === 'display' && in_array($value, array('block', 'inline-block'), true)) {
            $safe[$property] = $value;
            continue;
         }
         if (in_array($property, array('width', 'height', 'max-width', 'max-height'), true)
            && preg_match('/^(?:auto|0|(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)(?:px|%|vw|vh|rem|em))$/', $value)) {
            $safe[$property] = $value;
            continue;
         }
         if (in_array($property, array('margin-left', 'margin-right'), true)
            && preg_match('/^(?:auto|0|(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)(?:px|%|rem|em))$/', $value)) {
            $safe[$property] = $value;
         }
      }

      $result = '';
      foreach ($safe as $property => $value) {
         $result .= $property . ': ' . $value . '; ';
      }
      return trim($result);
   }

   private function sanitize_content_html($html) {
      $html = (string)$html;
      if ($html === '') {
         return '';
      }

      $html = preg_replace('#<(script|style|object|embed|base|meta|link)\b[^>]*>.*?</\1>#is', '', $html);
      $html = preg_replace('#<(script|style|object|embed|base|meta|link)\b[^>]*\/?>#is', '', $html);

      if (!class_exists('\DOMDocument')) {
         $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/is', '', $html);
         $html = preg_replace('/\s+style\s*=\s*(["\']).*?\1/is', '', $html);
         $html = preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/is', '', $html);
         return $this->strip_empty_content_paragraphs_fallback($html);
      }

      $previous = libxml_use_internal_errors(true);
      $doc = new \DOMDocument('1.0', 'UTF-8');
      $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="dbx_cms_sanitize_root">' . $html . '</div></body></html>';
      $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      libxml_clear_errors();
      libxml_use_internal_errors($previous);

      $root = $doc->getElementById('dbx_cms_sanitize_root');
      if (!$root) {
         return $html;
      }

       $walker = function($node) use (&$walker) {
          if ($node instanceof \DOMElement) {
             $remove = array();
             $safeStyle = '';
             foreach ($node->attributes as $attr) {
                $name = strtolower($attr->name);
                $value = trim((string)$attr->value);
                if ($name === 'style') {
                   $safeStyle = $this->safe_content_style(
                      $value,
                      (string)$node->tagName,
                      (string)$node->getAttribute('class')
                   );
                   $remove[] = $attr->name;
                   continue;
                }
               if (strpos($name, 'on') === 0) {
                  $remove[] = $attr->name;
                  continue;
               }
               if (($name === 'href' || $name === 'src' || $name === 'xlink:href') && preg_match('/^\s*javascript:/i', $value)) {
                  $remove[] = $attr->name;
                  continue;
               }
               if ($name === 'data-dbx') {
                  if (stripos($value, 'lib=openWin') === false || stripos($value, 'javascript:') !== false) {
                     $remove[] = $attr->name;
                  }
               }
            }
            foreach ($remove as $name) {
               $node->removeAttribute($name);
            }
             if ($safeStyle !== '') {
                $node->setAttribute('style', $safeStyle);
             }
          }

         foreach (iterator_to_array($node->childNodes) as $child) {
            $walker($child);
         }
      };
      $walker($root);

      $emptyParagraphs = array();
      foreach ($root->getElementsByTagName('p') as $paragraph) {
         if (!$this->content_node_has_visible_value($paragraph)) {
            $emptyParagraphs[] = $paragraph;
         }
      }
      foreach ($emptyParagraphs as $paragraph) {
         if ($paragraph->parentNode) $paragraph->parentNode->removeChild($paragraph);
      }

      $out = '';
      foreach ($root->childNodes as $child) {
         $out .= $doc->saveHTML($child);
      }
      return $out;
   }

   private function normalize_and_sanitize_content($html) {
      return $this->sanitize_content_html($this->normalize_content_media_urls($html));
   }

   private function inline_media_available($id) {
      $id = (int)$id;
      if ($id <= 0) return false;
      $db = dbx()->get_system_obj('dbxDB');
      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) return false;
      return $this->media_file_exists($row);
   }

   private function inline_media_missing_html($id) {
      $id = (int)$id;
      if ($id <= 0) return '';
      $label = 'Mediendatei #' . $id . ' nicht verfuegbar';
      return '<p class="dbx-cms-inline-media dbx-cms-inline-media-missing-wrap" data-cms-media-id="' . $id . '" data-cms-media-slot="inline" contenteditable="false" tabindex="0" title="Fehlende Mediendatei auswählen, Entf zum Löschen"><span class="dbx-cms-inline-media-missing" aria-hidden="true">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></p>';
   }

   private function inline_media_placeholder_html($id) {
      $id = (int)$id;
      if ($id <= 0) return '';
      $db = dbx()->get_system_obj('dbxDB');
      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) return '';
      $row = $this->normalize_media_row($row);
      $title = htmlspecialchars((string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? 'Video'), ENT_QUOTES, 'UTF-8');
      $attr = ' data-cms-media-id="' . $id . '" data-cms-media-slot="inline"';
      if (!in_array($this->media_type($row), array('video', 'external_video'), true)) return '';
      $thumb = htmlspecialchars((string)($row['thumb_url'] ?? ''), ENT_QUOTES, 'UTF-8');
      if ($thumb !== '') return '<img class="dbx-cms-inline-video-thumb" src="' . $thumb . '" alt="' . $title . '" title="' . $title . '"' . $attr . '><span class="dbx-cms-inline-video-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>';
      return '<span class="dbx-cms-inline-video-empty"' . $attr . '>' . $title . '</span><span class="dbx-cms-inline-video-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>';
   }

   private function inline_media_embed_html(array $row) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) return '';
      $row = $this->normalize_media_row($row);
      $attr = ' data-cms-media-id="' . $id . '" data-cms-media-slot="inline" contenteditable="false" tabindex="0"';
      if (in_array($this->media_type($row), array('video', 'external_video'), true)) {
         $inner = $this->inline_media_placeholder_html($id);
         return $inner !== '' ? '<figure class="dbx-cms-inline-media dbx-cms-inline-video-block"' . $attr . '>' . $inner . '</figure><p></p>' : '';
      }
      $url = htmlspecialchars((string)($row['url'] ?? $this->media_url($row)), ENT_QUOTES, 'UTF-8');
      if ($url === '') return '';
      $alt = htmlspecialchars((string)($row['alt'] ?? $row['title'] ?? $row['file_name'] ?? ''), ENT_QUOTES, 'UTF-8');
      $title = htmlspecialchars((string)($row['title'] ?? $row['alt'] ?? $row['file_name'] ?? ''), ENT_QUOTES, 'UTF-8');
      return '<p class="dbx-cms-inline-media"' . $attr . '><img class="dbx-cms-inline-image" src="' . $url . '" alt="' . $alt . '" title="' . $title . '"' . $attr . '></p><p></p>';
   }

   private function bool_int($value, $default = 1) {
      if ($value === '' || $value === null) return $default;
      return (int)((string)$value === '1' || $value === 1 || $value === true || $value === 'on');
   }

   private function clean_text($value, $max = 254) {
      $value = trim((string)$value);
      if ($max > 0 && strlen($value) > $max) {
         $value = substr($value, 0, $max);
      }
      return $value;
   }

   private function slug($text) {
      return dbxContent_permalink::slug($text);
   }

   private function page_permalink($db, $folder_id, $title, $permalink, int $excludeId = 0) {
      $permalink = trim($this->clean_text($permalink, 254));
      if ($permalink === '') {
         return dbxContent_permalink::build($db, dbxContentLng::ddFolder(), $folder_id, $title, $excludeId);
      }
      if (!dbxContent_permalink::isValid($permalink)) {
         throw new \InvalidArgumentException('Permalink: nur Kleinbuchstaben, Zahlen und einzelne Bindestriche sind erlaubt.');
      }
      if (dbxContent_permalink::exists($db, dbxContentLng::ddContent(), $permalink, $excludeId)) {
         throw new \InvalidArgumentException('Dieser Permalink wird bereits von einer anderen Seite verwendet.');
      }
      return $permalink;
   }

   private function page_permalink_exists($db, string $permalink, int $excludeId = 0): bool {
      $permalink = dbxContent_permalink::normalize($permalink);
      return dbxContent_permalink::exists($db, dbxContentLng::ddContent(), $permalink, $excludeId);
   }

   private function duplicate_page_permalink($db, int $folderId, string $title, string $permalink): string {
      $base = dbxContent_permalink::normalize($this->clean_text($permalink, 254));
      if ($base === '') {
         $base = dbxContent_permalink::build($db, dbxContentLng::ddFolder(), $folderId, $title);
      }
      if ($base === '') {
         $base = 'seite';
      }

      $copyNumber = 1;
      do {
         $suffix = $copyNumber === 1 ? '-kopie' : '-kopie-' . $copyNumber;
         $maxBaseLength = max(1, 254 - strlen($suffix));
         $candidateBase = rtrim(substr($base, 0, $maxBaseLength), '/-');
         if ($candidateBase === '') {
            $candidateBase = 'seite';
         }
         $candidate = dbxContent_permalink::normalize($candidateBase . $suffix);
         $copyNumber++;
      } while ($this->page_permalink_exists($db, $candidate));

      return $candidate;
   }

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

   private function clear_cms_tpl_cache() {
      if (isset($_SESSION['dbx']['cache']['tpl']['dbxContent_admin']) && is_array($_SESSION['dbx']['cache']['tpl']['dbxContent_admin'])) {
         foreach (array('cms-admin', 'cms-admin-frame', 'cms-admin-header', 'cms-admin-left', 'cms-admin-middle', 'cms-admin-right', 'cms-admin-media-panel', 'cms-admin-page-form', 'cms-admin-folder-form', 'cms-admin-settings-panels') as $tpl) {
            unset($_SESSION['dbx']['cache']['tpl']['dbxContent_admin'][$tpl]);
         }
      }
      if (isset($_SESSION['dbx']['cache']['tpl']['dbx']) && is_array($_SESSION['dbx']['cache']['tpl']['dbx'])) {
         foreach (array('module-bar-cms', 'bar-cms', 'bar-title-cms', 'bar-middle-cms', 'bar-actions-cms') as $tpl) {
            unset($_SESSION['dbx']['cache']['tpl']['dbx'][$tpl]);
         }
      }
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
      $dd = $type === 'folder' ? dbxContentLng::ddFolder() : dbxContentLng::ddContent();
      $lngUid = trim((string)($node['lng_uid'] ?? $node['_lng_uid'] ?? ''));

      if ($lngUid === '' && $id > 0) {
         $lngUid = dbxContentLngSync::ensureRecordUid($db, $dd, $id, $type === 'folder' ? 'f' : 'p');
      }

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

   private function media_url($row, $thumb = false) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) return '';
      $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $id;
      if ($thumb && !empty($row['thumb_file_path'])) $url .= '&dbx_thumb=1';
      $v = (int)($row['size'] ?? 0) . '-' . (int)($row['width'] ?? 0) . 'x' . (int)($row['height'] ?? 0);
      if ($thumb) $v .= '-' . (int)($row['thumb_width'] ?? 0) . 'x' . (int)($row['thumb_height'] ?? 0);
      $url .= '&_v=' . rawurlencode($v);
      return $url;
   }

   private function external_video_thumb_url($row) {
      $provider = strtolower(trim((string)($row['provider'] ?? '')));
      $provider_id = trim((string)($row['provider_id'] ?? ''));
      if ($provider === 'youtube' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id)) {
         return 'https://img.youtube.com/vi/' . $provider_id . '/hqdefault.jpg';
      }
      return '';
   }

   private function external_video_json_meta($file) {
      $raw = @file_get_contents((string)$file);
      if ($raw === false || $raw === '') return array();
      $data = json_decode($raw, true);
      if (!is_array($data)) return array();
      $provider = strtolower(trim((string)($data['provider'] ?? 'youtube')));
      $provider_id = trim((string)($data['provider_id'] ?? pathinfo((string)$file, PATHINFO_FILENAME)));
      $title = trim((string)($data['title'] ?? ('YouTube ' . $provider_id)));
      $external_url = trim((string)($data['external_url'] ?? ''));
      $embed_url = trim((string)($data['embed_url'] ?? ''));
      if ($embed_url === '' && preg_match('~^[A-Za-z0-9_-]{11}$~', $provider_id)) {
         $embed_url = 'https://www.youtube.com/embed/' . $provider_id;
      }
      return array(
         'provider' => $provider,
         'provider_id' => $provider_id,
         'title' => $title,
         'alt' => $title,
         'external_url' => $external_url,
         'embed_url' => $embed_url,
      );
   }

   private function media_type($row) {
      $stored = strtolower(trim((string)($row['media_type'] ?? '')));
      if (in_array($stored, array('image','video','external_video','file'), true)) return $stored;
      $storage = strtolower(trim((string)($row['storage_type'] ?? '')));
      if ($storage === 'external' || trim((string)($row['provider'] ?? '')) !== '' || trim((string)($row['embed_url'] ?? '')) !== '') return 'external_video';
      $mime = strtolower((string)($row['mime'] ?? ''));
      $name = strtolower((string)($row['file_name'] ?? $row['file_path'] ?? ''));
      if (strpos($mime, 'video/') === 0 || preg_match('/\.(mp4|webm|ogv|ogg|mov|m4v)$/i', $name)) return 'video';
      if (strpos($mime, 'image/') === 0 || preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $name)) return 'image';
      return 'file';
   }

   private function media_file_exists($row) {
      $row = is_array($row) ? $row : array();
      if (strtolower((string)($row['storage_type'] ?? '')) === 'external') return trim((string)($row['embed_url'] ?? $row['external_url'] ?? '')) !== '';
      $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return false;

      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/';
      $root = dbx()->os_path($root);
      $base = realpath($root);
      if (!$base) return false;

      $file = $root . $rel;
      $file = dbx()->os_path($file);
      $real = realpath($file);
      return $real && strpos($real, $base) === 0 && is_file($real) && is_readable($real);
   }

   private function media_thumb_exists($row) {
      $row = is_array($row) ? $row : array();
      if (strtolower((string)($row['storage_type'] ?? '')) === 'external' && trim((string)($row['thumb_file_path'] ?? '')) === '') return false;
      $rel = ltrim(str_replace('\\', '/', (string)($row['thumb_file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return false;

      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/';
      $file = $root . $rel;
      $root = dbx()->os_path($root);
      $file = dbx()->os_path($file);

      $base = realpath($root);
      $real = realpath($file);
      return $base && $real && strpos($real, $base) === 0 && is_file($real) && is_readable($real);
   }

   private function media_thumbnail_supported($row): bool {
      $row = is_array($row) ? $row : array();
      $type = $this->media_type($row);
      if ($type === 'video') return true;
      if ($type !== 'image' || !function_exists('imagecreatetruecolor')) return false;
      $name = (string)($row['file_name'] ?? $row['file_path'] ?? '');
      $mime = strtolower(trim((string)($row['mime'] ?? '')));
      // SVG wird im Browser verlustfrei direkt dargestellt. Ein GD-Thumbnail
      // ist weder moeglich noch erforderlich und deshalb kein Wartungsfehler.
      return $mime !== 'image/svg+xml' && !preg_match('/\.svg$/i', $name);
   }

   /**
    * Eine vorhandene Datei allein reicht nicht: Nach Austausch, Umbenennung
    * oder Bildbearbeitung kann der DB-Eintrag noch auf ein altes Motiv zeigen.
    * Videos duerfen ein bewusst gesetztes Poster verwenden; lokale Rasterbilder
    * muessen dagegen zu Name, Zeitstand und erwarteter Groesse der Quelle passen.
    */
   private function media_thumbnail_is_current($row): bool {
      $row = is_array($row) ? $row : array();
      if (!$this->media_thumb_exists($row)) return false;
      if ($this->media_type($row) !== 'image') return true;
      if (!$this->media_thumbnail_supported($row)) return true;

      $source = $this->source_media_file($row);
      $thumb = $this->source_thumb_file($row);
      if ($source === '' || $thumb === '') return false;

      $source_time = @filemtime($source);
      $thumb_time = @filemtime($thumb);
      if ($source_time !== false && $thumb_time !== false && $source_time > $thumb_time) return false;

      $source_name = (string)($row['file_name'] ?? basename($source));
      $source_stem = strtolower((string)pathinfo($source_name, PATHINFO_FILENAME));
      $thumb_stem = strtolower((string)pathinfo(basename($thumb), PATHINFO_FILENAME));
      if ($source_stem !== '' && strpos($thumb_stem, $source_stem) === false) return false;

      $source_size = @getimagesize($source);
      $thumb_size = @getimagesize($thumb);
      if (!is_array($source_size) || !is_array($thumb_size)) return false;
      $source_w = (int)($source_size[0] ?? 0);
      $source_h = (int)($source_size[1] ?? 0);
      if ($source_w <= 0 || $source_h <= 0) return false;
      $scale = min(640 / $source_w, 480 / $source_h, 1);
      $expected_w = max(1, (int)round($source_w * $scale));
      $expected_h = max(1, (int)round($source_h * $scale));
      return abs((int)($thumb_size[0] ?? 0) - $expected_w) <= 1
         && abs((int)($thumb_size[1] ?? 0) - $expected_h) <= 1;
   }

   private function valid_media_slot($slot, $default = 'gallery') {
      $slot = strtolower(trim((string)$slot));
      $allowed = array('hero','gallery','inline','header','teaser','footer','shop');
      return in_array($slot, $allowed, true) ? $slot : $default;
   }

   private function media_rel_dir($slot) {
      return 'media/' . $this->valid_media_slot($slot) . '/';
   }

   private function video_media_base_folder() {
      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/media/';
      $root = dbx()->os_path($root);
      if (is_dir($root . 'videos')) return 'videos';
      if (is_dir($root . 'video')) return 'video';
      return 'videos';
   }

   private function media_standard_bases() {
      return array('img' => 'image', $this->video_media_base_folder() => 'video', 'youtube' => 'external_video', 'file' => 'file');
   }

   private function canonical_media_folder($folder) {
      $folder = strtolower(trim(str_replace('\\', '/', (string)$folder), '/'));
      if (preg_match('~^youtube/[a-z0-9_-]{11}$~', $folder)) return 'youtube';
      return $folder;
   }

   private function clean_media_folder($folder, $media_type = 'image') {
      $media_type = $this->media_type(array('media_type' => $media_type));
      $folder = $this->canonical_media_folder($folder);
      $folder = strtolower(str_replace('\\', '/', trim((string)$folder)));
      $folder = preg_replace('~/+~', '/', $folder);
      $folder = trim($folder, '/');
      $parts = array();
      foreach (explode('/', $folder) as $part) {
         $part = preg_replace('/[^A-Za-z0-9_-]+/', '-', $part);
         $part = trim($part, '-_');
         if ($part !== '' && $part !== '.' && $part !== '..') $parts[] = $part;
      }
      $folder = implode('/', $parts);

      if ($folder === 'module') {
         return 'module';
      }

      if ($media_type === 'video') {
         $base = $this->video_media_base_folder();
         if ($folder === '' || $folder === 'video' || $folder === 'videos' || $folder === 'img' || $folder === 'img/video') return $base;
         if (substr($folder, 0, 10) === 'img/video/') return $base . '/' . substr($folder, 10);
         if (substr($folder, 0, 6) === 'video/') return $base . '/' . substr($folder, 6);
         if (substr($folder, 0, 7) === 'videos/') return $base . '/' . substr($folder, 7);
         return $base;
      }
      if ($media_type === 'external_video') {
         if ($folder === '' || $folder === 'youtube') return 'youtube';
         if (substr($folder, 0, 8) !== 'youtube/') return 'youtube';
         return $folder;
      }
      if ($media_type === 'file') {
         if ($folder === '' || $folder === 'file') return 'file/allgemein';
         if (substr($folder, 0, 5) !== 'file/') return 'file/allgemein';
         return $folder;
      }
      if ($folder === '' || $folder === 'img' || $folder === 'image') return 'img/allgemein';
      if (substr($folder, 0, 4) !== 'img/') return 'img/allgemein';
      return $folder;
   }

   private function media_type_from_folder($folder, $fallback = 'image') {
      $folder = $this->canonical_media_folder($folder);
      if (strpos($folder, 'img/video') === 0) return 'video';
      if (strpos($folder, 'videos/') === 0 || $folder === 'videos') return 'video';
      if (strpos($folder, 'video/') === 0 || $folder === 'video') return 'video';
      if (strpos($folder, 'youtube/') === 0 || $folder === 'youtube') return 'external_video';
      if (strpos($folder, 'file/') === 0 || $folder === 'file') return 'file';
      return $fallback;
   }

   private function media_folder_from_path($rel, $media_type = 'image') {
      $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
      if (preg_match('~^media/module/[^/]+$~i', $rel)) {
         return 'module';
      }
      if (preg_match('~^media/youtube/[^/]+\.json$~i', $rel)) {
         return 'youtube';
      }
      if (preg_match('~^media/(img|video|youtube|external|file)/([^/]+)/[^/]+$~i', $rel, $m)) {
         return $this->clean_media_folder($m[1] . '/' . $m[2], $media_type);
      }
      if (preg_match('~^media/(img|video|file)/([^/]+)/?$~i', $rel, $m)) {
         return $this->clean_media_folder($m[1] . '/' . $m[2], $media_type);
      }
      return $this->clean_media_folder('', $media_type);
   }

   private function media_folder_rel_dir($folder, $media_type = 'image') {
      return 'media/' . $this->clean_media_folder($folder, $media_type) . '/';
   }

   private function media_thumb_rel_dir($slot) {
      $slot_text = (string)$slot;
      $folder_type = 'image';
      if (preg_match('~^(youtube|external)(/|$)~', $slot_text)) $folder_type = 'external_video';
      elseif (preg_match('~^(videos|video|img/video)(/|$)~', $slot_text)) $folder_type = 'video';
      elseif (preg_match('~^file(/|$)~', $slot_text)) $folder_type = 'file';
      $folder = $this->clean_media_folder($slot, $folder_type);
      return 'media/_thumbs/' . $folder . '/';
   }

   private function cms_media_dir($slot = '') {
      $slot_text = (string)$slot;
      $folder_type = 'image';
      if (preg_match('~^(youtube|external)(/|$)~', $slot_text)) $folder_type = 'external_video';
      elseif (preg_match('~^(videos|video|img/video)(/|$)~', $slot_text)) $folder_type = 'video';
      elseif (preg_match('~^file(/|$)~', $slot_text)) $folder_type = 'file';
      $rel = $slot === '' ? 'media/' : $this->media_folder_rel_dir($slot, $folder_type);
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      return dbx()->os_path($dir);
   }

   private function cms_media_base_dir($base) {
      $base = strtolower(trim(str_replace('\\', '/', (string)$base), '/'));
      if (!in_array($base, array('img', 'video', 'videos', 'youtube', 'external', 'file', 'module'), true)) return '';
      $rel = 'media/' . $base . '/';
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      return dbx()->os_path($dir);
   }

   private function cms_media_thumb_dir($slot) {
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $this->media_thumb_rel_dir($slot);
      return dbx()->os_path($dir);
   }

   private function unique_media_name($name, $prefix = '') {
      $name = basename((string)$name);
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
      $prefix = $prefix !== '' ? rtrim($prefix, '-') . '-' : '';
      return date('YmdHis') . '-' . substr(md5($name . microtime(true)), 0, 8) . '-' . $prefix . $name;
   }

   private function media_slots() {
      return array('hero','gallery','inline','header','teaser','footer');
   }

   private function media_allowed_extensions() {
      return array('jpg','jpeg','png','gif','webp','svg','mp4','webm','ogv','ogg','mov','m4v');
   }

   private function file_from_media_rel($rel) {
      $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return '';
      $file = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      return dbx()->os_path($file);
   }

   private function relative_file_exists($rel) {
      $file = $this->file_from_media_rel($rel);
      return $file !== '' && is_file($file) && is_readable($file);
   }

   private function detect_media_mime($file, $ext) {
      $mime = function_exists('mime_content_type') ? (string)@mime_content_type($file) : '';
      if ($mime !== '') return $mime;
      $ext = strtolower((string)$ext);
      if (in_array($ext, array('mp4','webm','ogv','ogg','mov','m4v'), true)) {
         return $ext === 'webm' ? 'video/webm' : ($ext === 'mov' ? 'video/quicktime' : 'video/mp4');
      }
      return $ext === 'svg' ? 'image/svg+xml' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
   }

   private function media_file_meta($file, $name) {
      $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
      $mime = $this->detect_media_mime($file, $ext);
      $width = 0;
      $height = 0;
      $img = @getimagesize($file);
      if (is_array($img)) {
         $width = (int)($img[0] ?? 0);
         $height = (int)($img[1] ?? 0);
      }
      return array(
         'mime' => $mime,
         'size' => (int)@filesize($file),
         'width' => $width,
         'height' => $height,
         'media_type' => $this->media_type(array('mime' => $mime, 'file_name' => $name)),
      );
   }

   private function is_excluded_cms_media_folder($folder) {
      $folder = $this->canonical_media_folder(strtolower(trim(str_replace('\\', '/', (string)$folder), '/')));
      return $folder === 'module' || strpos($folder, 'module/') === 0;
   }

   private function cms_media_exclude_sql() {
      return " AND media_folder <> 'module' AND file_path NOT LIKE 'media/module/%'";
   }

   private function collect_media_files() {
      $files = array();
      $allowed = $this->media_allowed_extensions();

      foreach (array('img','video','youtube','file') as $base_folder) {
         $dir = $this->cms_media_base_dir($base_folder);
         if (!is_dir($dir) || !is_readable($dir)) continue;
         $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
         foreach ($it as $file) {
            if (!$file->isFile() || !$file->isReadable()) continue;
            $name = $file->getFilename();
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $is_external_base = $base_folder === 'youtube';
            if (!in_array($ext, $allowed, true) && !($is_external_base && $ext === 'json')) continue;
            $path = str_replace('\\', '/', $file->getPathname());
            $root = str_replace('\\', '/', rtrim(dbx()->get_file_dir(), '/\\')) . '/';
            if (strpos($path, $root) !== 0) continue;
            $rel = substr($path, strlen($root));
            $type = $base_folder === 'video' ? 'video' : ($is_external_base ? 'external_video' : ($base_folder === 'file' ? 'file' : 'image'));
            if ($ext === 'json' && $is_external_base) $type = 'external_video';
            $files[$rel] = array(
               'rel' => $rel,
               'slot' => 'gallery',
               'folder' => $this->media_folder_from_path($rel, $type),
               'file' => $file->getPathname(),
               'name' => $name,
            );
         }
      }
      return $files;
   }

   private function collect_media_thumb_files() {
      $files = array();
      $root = dbx()->os_path(rtrim(dbx()->get_file_dir(), '/\\') . '/media/_thumbs/');
      if (!is_dir($root) || !is_readable($root)) return $files;
      $rootNormalized = str_replace('\\', '/', rtrim($root, '/\\')) . '/';
      $it = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::LEAVES_ONLY
      );
      foreach ($it as $file) {
         if (!$file->isFile() || !$file->isReadable()) continue;
         $path = str_replace('\\', '/', $file->getPathname());
         if (strpos($path, $rootNormalized) !== 0) continue;
         $rel = 'media/_thumbs/' . ltrim(substr($path, strlen($rootNormalized)), '/');
         $files[$rel] = array('rel' => $rel, 'file' => $file->getPathname());
      }
      return $files;
   }

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

   private function get_media_process_state($token) {
      $token = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)$token);
      if ($token === '') return array();
      return $_SESSION['dbx']['dbxContent_admin']['media_process'][$token] ?? array();
   }

   private function set_media_process_state($token, array $state) {
      $token = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)$token);
      if ($token === '') return;
      if (!isset($_SESSION['dbx']) || !is_array($_SESSION['dbx'])) $_SESSION['dbx'] = array();
      if (!isset($_SESSION['dbx']['dbxContent_admin']) || !is_array($_SESSION['dbx']['dbxContent_admin'])) $_SESSION['dbx']['dbxContent_admin'] = array();
      if (!isset($_SESSION['dbx']['dbxContent_admin']['media_process']) || !is_array($_SESSION['dbx']['dbxContent_admin']['media_process'])) $_SESSION['dbx']['dbxContent_admin']['media_process'] = array();
      $_SESSION['dbx']['dbxContent_admin']['media_process'][$token] = $state;
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
      if ((int)($state['database_optimized'] ?? 0) > 0) $parts[] = 'Datenbanken optimiert ' . (int)$state['database_optimized'];
      if ((int)($state['errors'] ?? 0) > 0) $parts[] = 'Fehler ' . (int)$state['errors'];
      $msg = empty($parts) ? 'Keine Aenderungen.' : implode(' | ', $parts);
      if ($current !== '') $msg .= ' | ' . $current;
      return $msg;
   }

   private function content_inline_cleanup_needed($db) {
      foreach ($this->ordered_media_languages() as $lng) {
         $rows = $db->select(dbxContentLng::ddContent($lng), "content LIKE '%dbx_mid=%' OR content LIKE '%data-cms-media-id=%'", 'id,content', 'id');
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
      $rows = $db->select(dbxContentLng::ddContent(), "content LIKE '%dbx_mid=%' OR content LIKE '%data-cms-media-id=%'", 'id,content', 'id');
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
      foreach (dbxContentLngSync::accessibleLngs() as $lng) {
         $rows = $db->select(dbxContentLng::ddContent((string)$lng), "content LIKE '%dbx-cms-inline-media-missing%'", 'id,content', 'id');
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

      foreach (dbxContentLngSync::accessibleLngs() as $lng) {
         $lng = (string)$lng;
         dbx()->set_system_var('dbx_lng', $lng);
         $dd = dbxContentLng::ddContent($lng);
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
      $previous_lng = (string)dbx()->get_system_var('dbx_lng', dbxContentLngSync::masterLng());
      try {
         foreach ($this->ordered_media_languages() as $lng) {
            dbx()->set_system_var('dbx_lng', $lng);
            $content_dd = dbxContentLng::ddContent($lng);
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
         foreach (array($this->dd_media_usage, dbxContentLng::ddContent()) as $dd) {
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
         . '<button type="button" class="btn btn-warning btn-sm" data-process-action="pause" data-process-visible="running" title="Anhalten"><i class="bi bi-pause-fill"></i></button>'
         . '<button type="button" class="btn btn-primary btn-sm" data-process-action="resume" data-process-visible="paused" title="Weiter"><i class="bi bi-play-fill"></i></button>'
         . '<button type="button" class="btn btn-secondary btn-sm" data-process-action="restart" data-process-visible="paused,canceled,error,finished" title="Neu starten"><i class="bi bi-arrow-clockwise"></i></button>'
         . '<button type="button" class="btn btn-danger btn-sm" data-process-action="cancel" data-process-visible="running,paused" title="Abbrechen"><i class="bi bi-x-lg"></i></button>'
         . '</div>'
         . '</div>';
   }

   private function append_url_params($url, $params = array()) {
      foreach ($params as $key => $value) {
         $url .= (strpos($url, '?') === false ? '?' : '&') . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }
      return $url;
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

   private function first_upload_file() {
      if (empty($_FILES) || !is_array($_FILES)) return array();

      foreach ($_FILES as $file) {
         if (!is_array($file)) continue;

         if (isset($file['tmp_name']) && !is_array($file['tmp_name'])) {
            return $file;
         }

         if (!isset($file['tmp_name']) || !is_array($file['tmp_name'])) continue;

         $idx = array_key_first($file['tmp_name']);
         if ($idx === null) continue;

         return array(
            'name' => is_array($file['name'] ?? null) ? ($file['name'][$idx] ?? '') : ($file['name'] ?? ''),
            'type' => is_array($file['type'] ?? null) ? ($file['type'][$idx] ?? '') : ($file['type'] ?? ''),
            'tmp_name' => $file['tmp_name'][$idx] ?? '',
            'error' => is_array($file['error'] ?? null) ? ($file['error'][$idx] ?? UPLOAD_ERR_NO_FILE) : ($file['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => is_array($file['size'] ?? null) ? ($file['size'][$idx] ?? 0) : ($file['size'] ?? 0),
         );
      }

      return array();
   }

   private function load_gd_image($file, $mime) {
      if (!extension_loaded('gd')) return false;
      $mime = strtolower((string)$mime);
      if ((strpos($mime, 'jpeg') !== false || preg_match('/\.jpe?g$/i', $file)) && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($file);
      if ((strpos($mime, 'png') !== false || preg_match('/\.png$/i', $file)) && function_exists('imagecreatefrompng')) return @imagecreatefrompng($file);
      if (strpos($mime, 'webp') !== false || preg_match('/\.webp$/i', $file)) return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
      if ((strpos($mime, 'gif') !== false || preg_match('/\.gif$/i', $file)) && function_exists('imagecreatefromgif')) return @imagecreatefromgif($file);
      return false;
   }

   private function save_gd_image($img, $file, $mime) {
      if (!extension_loaded('gd')) return false;
      $mime = strtolower((string)$mime);
      if (strpos($mime, 'png') !== false || preg_match('/\.png$/i', $file)) {
         if (!function_exists('imagepng')) return false;
         imagealphablending($img, false);
         imagesavealpha($img, true);
         return @imagepng($img, $file, 6);
      }
      if ((strpos($mime, 'webp') !== false || preg_match('/\.webp$/i', $file)) && function_exists('imagewebp')) {
         return @imagewebp($img, $file, 86);
      }
      if (!function_exists('imagejpeg')) return false;
      return @imagejpeg($img, $file, 88);
   }

   private function convert_media_image_to_webp($file, $name, $mime = '') {
      if (!extension_loaded('gd') || !function_exists('imagewebp')) return false;
      if ($file === '' || !is_file($file) || !is_readable($file)) return false;
      if (preg_match('/\.(webp|gif|svg)$/i', (string)$name)) return false;

      $mime_l = strtolower((string)$mime);
      $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
      $is_supported = in_array($ext, array('jpg','jpeg','png'), true)
         || strpos($mime_l, 'jpeg') !== false
         || strpos($mime_l, 'png') !== false;
      if (!$is_supported) return false;

      $image = $this->load_gd_image($file, $mime);
      if (!$image) return false;

      imagepalettetotruecolor($image);
      imagealphablending($image, true);
      imagesavealpha($image, true);

      $webp_name = preg_replace('/\.[^.]+$/', '', basename((string)$name)) . '.webp';
      $webp_file = rtrim(dirname($file), '/\\') . DIRECTORY_SEPARATOR . $webp_name;
      if (is_file($webp_file)) {
         $webp_name = $this->unique_media_name($webp_name);
         $webp_file = rtrim(dirname($file), '/\\') . DIRECTORY_SEPARATOR . $webp_name;
      }

      $ok = @imagewebp($image, $webp_file, 86);
      imagedestroy($image);
      if (!$ok || !is_file($webp_file)) return false;

      @unlink($file);
      return array(
         'file' => $webp_file,
         'name' => $webp_name,
         'mime' => 'image/webp',
      );
   }

   private function resize_image_to_max($file, $mime, $max_long_side = 2560) {
      $max_long_side = max(800, min(6000, (int)$max_long_side));
      if ($file === '' || preg_match('/\.(svg|gif)$/i', (string)$file)) return false;

      $size = @getimagesize($file);
      if (!is_array($size)) return false;

      $src_w = (int)($size[0] ?? 0);
      $src_h = (int)($size[1] ?? 0);
      if ($src_w <= 0 || $src_h <= 0 || max($src_w, $src_h) <= $max_long_side) return false;

      $op = array('action' => 'resize');
      if ($src_w >= $src_h) {
         $op['width'] = $max_long_side;
         $op['height'] = 0;
      } else {
         $op['width'] = 0;
         $op['height'] = $max_long_side;
      }

      return $this->edit_image_file($file, $mime, $op);
   }

   private function create_media_thumbnail($src, $slot, $name, $mime = '') {
      $type = $this->media_type(array('mime' => $mime, 'file_name' => $name));
      if ($type === 'video') return $this->create_video_thumbnail($src, $slot, $name);
      if ($type !== 'image' || preg_match('/\.svg$/i', (string)$name) || !function_exists('imagecreatetruecolor')) return array();

      $image = $this->load_gd_image($src, $mime);
      if (!$image) return array();

      $src_w = imagesx($image);
      $src_h = imagesy($image);
      if ($src_w <= 0 || $src_h <= 0) {
         imagedestroy($image);
         return array();
      }

      $max_w = 640;
      $max_h = 480;
      $scale = min($max_w / $src_w, $max_h / $src_h, 1);
      $dst_w = max(1, (int)round($src_w * $scale));
      $dst_h = max(1, (int)round($src_h * $scale));
      $thumb = imagecreatetruecolor($dst_w, $dst_h);
      imagealphablending($thumb, false);
      imagesavealpha($thumb, true);
      imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 255, 255, 255, 127));
      imagecopyresampled($thumb, $image, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);

      $dir = $this->cms_media_thumb_dir($slot);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      $thumb_ext = function_exists('imagewebp') ? 'webp' : 'jpg';
      $thumb_name = preg_replace('/\.[^.]+$/', '', basename((string)$name)) . '.' . $thumb_ext;
      $thumb_name = $this->unique_media_name($thumb_name, 'thumb');
      $dst = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $thumb_name;
      $ok = $thumb_ext === 'webp' ? @imagewebp($thumb, $dst, 82) : @imagejpeg($thumb, $dst, 82);
      imagedestroy($thumb);
      imagedestroy($image);

      if (!$ok) return array();
      return array(
         'thumb_file_path' => $this->media_thumb_rel_dir($slot) . $thumb_name,
         'thumb_width' => $dst_w,
         'thumb_height' => $dst_h,
      );
   }

   private function find_executable_path(string $command): string {
      static $cache = array();

      $command = trim($command);
      if ($command === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $command)) {
         return '';
      }

      if (array_key_exists($command, $cache)) {
         return $cache[$command];
      }

      $lookup = stripos(PHP_OS_FAMILY, 'Windows') === 0
         ? 'where ' . $command . ' 2>NUL'
         : 'command -v ' . $command . ' 2>/dev/null';
      $result = trim((string)@shell_exec($lookup));
      $path = $result !== '' ? strtok($result, "\r\n") : '';
      $cache[$command] = is_string($path) ? trim($path) : '';

      return $cache[$command];
   }

   private function create_video_thumbnail($src, $slot, $name) {
      $ffmpeg = $this->find_executable_path('ffmpeg');

      $dir = $this->cms_media_thumb_dir($slot);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      $thumb_name = $this->unique_media_name(preg_replace('/\.[^.]+$/', '', basename((string)$name)) . '.jpg', 'video-thumb');
      $dst = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $thumb_name;

      if ($ffmpeg !== '') {
         $cmd = '"' . $ffmpeg . '" -y -ss 00:00:01 -i "' . $src . '" -frames:v 1 -vf "scale=640:-1" "' . $dst . '" 2>NUL';
         @shell_exec($cmd);
      }

      if (!is_file($dst) || !filesize($dst)) {
         $this->create_video_placeholder_thumbnail($dst, $name);
      }

      if (!is_file($dst) || !filesize($dst)) return array();
      $size = @getimagesize($dst);
      return array(
         'thumb_file_path' => $this->media_thumb_rel_dir($slot) . $thumb_name,
         'thumb_width' => is_array($size) ? (int)$size[0] : 0,
         'thumb_height' => is_array($size) ? (int)$size[1] : 0,
      );
   }

   private function create_video_placeholder_thumbnail($dst, $name) {
      if (!function_exists('imagecreatetruecolor')) return false;
      $w = 640;
      $h = 360;
      $img = imagecreatetruecolor($w, $h);
      $bg = imagecolorallocate($img, 30, 43, 60);
      $panel = imagecolorallocate($img, 52, 72, 96);
      $white = imagecolorallocate($img, 255, 255, 255);
      $muted = imagecolorallocate($img, 188, 202, 220);
      imagefill($img, 0, 0, $bg);
      imagefilledrectangle($img, 20, 20, $w - 20, $h - 20, $panel);
      $cx = (int)($w / 2);
      $cy = (int)($h / 2) - 12;
      imagefilledpolygon($img, array($cx - 38, $cy - 48, $cx - 38, $cy + 48, $cx + 52, $cy), 3, $white);
      $label = substr(basename((string)$name), 0, 54);
      imagestring($img, 3, 28, $h - 42, $label, $muted);
      $ok = @imagejpeg($img, $dst, 82);
      imagedestroy($img);
      return $ok;
   }

   private function source_thumb_file($row) {
      $rel = ltrim(str_replace('\\', '/', (string)($row['thumb_file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return '';
      $file = rtrim(dbx()->get_file_dir(), '/\\') . '/' . $rel;
      $file = dbx()->os_path($file);
      $base = realpath(rtrim(dbx()->get_file_dir(), '/\\'));
      $real = realpath($file);
      if (!$base || !$real || strpos($real, $base) !== 0 || !is_file($real) || !is_readable($real)) return '';
      return $real;
   }

   private function render_cms() {
      $this->clear_cms_tpl_cache();
      $oTPL = dbx()->get_system_obj('dbxTPL');
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $texts = $this->cms_texts();
      // Der eigentliche Content-Baum wird ueber cms_tree erst auf Anforderung
      // geladen. Fuer die Kopfleiste reichen drei kleine COUNT-Abfragen.
      $page_count = (int)$db->count(dbxContentLng::ddContent(), '');
      $folder_count = (int)$db->count(dbxContentLng::ddFolder(), '');
      $active_count = $db->count(dbxContentLng::ddContent(), 'activ = 1');
      if ($page_count < 0) $page_count = 0;
      if ($folder_count < 0) $folder_count = 0;
      if ($active_count < 0) $active_count = 0;

      $cms_cid = $this->resolve_cms_page_id();
      $cms_fid = (int)dbx()->get_modul_var('fid', 0, 'int');
      if ($cms_fid <= 0) {
         $cms_fid = (int)dbx()->get_modul_var('dbx_fid', 0, 'int');
      }

      $data = array(
         'i' => dbx()->next_id(),
         'cms_cid' => (string)$cms_cid,
         'cms_fid' => (string)$cms_fid,
         'title' => $texts->get_fd_message('bar_title'),
         'subtitle' => $texts->get_fd_message('bar_subtitle'),
         'bar_title' => $texts->get_fd_message('bar_title'),
         'bar_subtitle' => $texts->get_fd_message('bar_subtitle'),
         'right_panel_hint' => $texts->get_fd_message('right_panel_hint'),
         'inline_media_title' => $texts->get_fd_message('inline_media_title'),
         'inline_media_assign_title' => $texts->get_fd_message('inline_media_assign_title'),
         'media_inline_empty' => $texts->get_fd_message('media_inline_empty'),
         'selection_label' => $texts->get_fd_message('selection_label'),
         'right_show' => $texts->get_fd_message('right_show'),
         'right_hide' => $texts->get_fd_message('right_hide'),
         'cms_messages' => dbx()->esc($this->cms_js_messages()),
         'count_pages' => (string)$page_count,
         'count_folders' => (string)$folder_count,
         'count_active' => (string)$active_count,
         'tree_url' => dbx()->esc($this->base_url('cms_tree')),
         'page_url' => dbx()->esc($this->base_url('cms_page')),
         'save_url' => dbx()->esc($this->base_url('cms_save')),
         'delete_page_url' => dbx()->esc($this->base_url('cms_delete_page')),
         'new_page_url' => dbx()->esc($this->base_url('cms_new_page')),
         'duplicate_page_url' => dbx()->esc($this->base_url('cms_duplicate_page')),
         'new_folder_url' => dbx()->esc($this->base_url('cms_new_folder')),
         'media_url' => dbx()->esc($this->base_url('cms_media')),
         'media_process_url' => dbx()->esc($this->base_url('cms_media_process')),
         'media_folders_url' => dbx()->esc($this->base_url('cms_media_folders')),
         'media_folder_create_url' => dbx()->esc($this->base_url('cms_media_folder_create')),
         'media_folder_delete_url' => dbx()->esc($this->base_url('cms_media_folder_delete')),
         'media_folder_rename_url' => dbx()->esc($this->base_url('cms_media_folder_rename')),
         'media_move_url' => dbx()->esc($this->base_url('cms_media_move')),
         'media_unused_url' => dbx()->esc($this->base_url('cms_media_unused')),
         'upload_url' => dbx()->esc($this->base_url('cms_upload')),
         'external_video_url' => dbx()->esc($this->base_url('cms_external_video')),
         'upload_max_bytes' => (string)$this->upload_max_bytes(),
         'remove_media_url' => dbx()->esc($this->base_url('cms_remove_media')),
         'delete_media_url' => dbx()->esc($this->base_url('cms_delete_media')),
         'edit_media_url' => dbx()->esc($this->base_url('cms_edit_media')),
         'set_media_slot_url' => dbx()->esc($this->base_url('cms_set_media_slot')),
         'assign_media_url' => dbx()->esc($this->base_url('cms_assign_media')),
         'sort_media_url' => dbx()->esc($this->base_url('cms_sort_media')),
         'mod_catalog_url' => dbx()->esc($this->base_url('cms_mod_catalog')),
         'mod_modules_url' => dbx()->esc($this->base_url('cms_mod_modules')),
         'folder_save_url' => dbx()->esc($this->base_url('cms_save_folder')),
         'folder_delete_url' => dbx()->esc($this->base_url('cms_delete_folder')),
         'move_node_url' => dbx()->esc($this->base_url('cms_move_node')),
         'lng_coverage_url' => dbx()->esc($this->base_url('cms_lng_coverage')),
         'lng_preview_url' => dbx()->esc($this->base_url('cms_lng_preview')),
         'lng_provision_url' => dbx()->esc($this->base_url('cms_lng_provision')),
         'lng_provision_tree_url' => dbx()->esc($this->base_url('cms_lng_provision_tree')),
         'lng_reset_sync_url' => dbx()->esc($this->base_url('cms_lng_reset_sync')),
         'lng_delete_preview_url' => dbx()->esc($this->base_url('cms_lng_delete_preview')),
          'current_lng' => dbxContentLng::current(),
         'master_lng' => dbxContentLngSync::masterLng(),
         'lng_bar' => dbxContentLngSync::renderLngBar(),
         'template_options' => $this->template_options(),
         'rights_options' => $this->rights_options(),
         'media_template_options' => $this->media_template_options(),
         'hero_template_options' => $this->hero_template_options(),
         'hero_variant_options' => $this->hero_variant_options(),
         'gallery_template_options' => $this->gallery_template_options(),
         'gallery_overflow_options' => $this->gallery_overflow_options(),
         'gallery_click_options' => $this->gallery_click_options(),
         'page_form' => $this->render_page_form(),
         'folder_form' => $this->render_folder_form(),
         'settings_form' => $this->render_settings_form(),
         'media_browser_forms' => $this->media_forms()->renderTemplates(
            $this->base_url('cms_upload'),
            'cms-media-upload',
            $this->base_url('cms_external_video'),
            'cms-external-video'
         ),
         'dbx_search' => $oTPL->get_tpl('dbx|search', dbx()->search_defaults(array(
             'title' => $texts->get_fd_message('tree_search'),
            'extra_attrs' => 'data-cms-search',
            'data_role' => '',
         ))),
      );

      $data = array_merge(
         dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin')->vars('content'),
         $data
      );
      $data['bar_class'] = 'dbx-module-bar dbx-cms-head';
      $data['bar_title_class'] = 'dbx-module-bar-titleblock';
      $data['bar_actions_class'] = 'dbx-module-bar-actions flex-wrap';
      if (trim((string)($data['bar_subtitle'] ?? '')) === '' && trim((string)($data['subtitle'] ?? '')) !== '') {
         $data['bar_subtitle'] = (string)$data['subtitle'];
      }

      return $oTPL->get_tpl('dbxContent_admin|cms-admin', $data);
   }

   private function mod_class_files($modul) {
      $modul = preg_replace('/[^A-Za-z0-9_]+/', '', (string)$modul);
      if ($modul === '') return array();
      $base = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/');
      $files = array();
      $main = $base . $modul . '.class.php';
      if (is_file($main)) $files[] = $main;
      $inc = $base . 'include' . DIRECTORY_SEPARATOR;
      if (is_dir($inc)) {
         foreach (glob($inc . '*.class.php') ?: array() as $path) {
            if (is_file($path)) $files[] = $path;
         }
      }
      return $files;
   }

   private function mod_scan_runs($modul) {
      $run1 = array();
      $run2 = array();
      $uses_run2 = false;
      foreach ($this->mod_class_files($modul) as $file) {
         $src = @file_get_contents($file);
         if (!is_string($src) || $src === '') continue;
         if (preg_match("/get_modul_var\s*\(\s*['\"]dbx_run2['\"]/", $src)) {
            $uses_run2 = true;
         }
         if (preg_match_all("/case\s+['\"]([^'\"]+)['\"]\s*:/", $src, $matches)) {
            foreach ($matches[1] as $case) {
               $case = trim((string)$case);
               if ($case === '' || $case === 'default') continue;
               $run1[$case] = true;
            }
         }
         if (preg_match_all('/\$(?:run|action|work)\s*===?\s*[\'"]([^\'"]+)[\'"]/', $src, $ifs)) {
            foreach ($ifs[1] as $case) {
               $case = trim((string)$case);
               if ($case === '') continue;
               $run1[$case] = true;
            }
         }
         if (preg_match_all("/get_modul_var\s*\(\s*['\"]dbx_run1['\"][^)]*['\"]([a-zA-Z0-9_]+)['\"]/", $src, $defaults)) {
            foreach ($defaults[1] as $case) {
               $case = trim((string)$case);
               if ($case === '' || $case === 'parameter') continue;
               $run1[$case] = true;
            }
         }
         if (preg_match_all("/get_modul_var\s*\(\s*['\"]dbx_run2['\"][^)]*['\"]([^'\"]+)['\"]/", $src, $defaults)) {
            foreach ($defaults[1] as $val) {
               $val = trim((string)$val);
               if ($val !== '') $run2[$val] = true;
            }
         }
      }
      $run1_list = array_keys($run1);
      usort($run1_list, function($a, $b) {
         return strlen($b) <=> strlen($a);
      });
      return array(
         'run1' => array_values(array_keys($run1)),
         'run2' => array_values(array_keys($run2)),
         'uses_run2' => $uses_run2,
         'run1_sorted' => $run1_list,
      );
   }

   private function mod_modules_json() {
      $base = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxAdmin/include/');
      require_once $base . 'dbxModuleImages.class.php';
      $images = new \dbx\dbxAdmin\dbxModuleImages();

      $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/');
      $items = array();
      if (is_dir($dir)) {
         foreach (glob($dir . '*', GLOB_ONLYDIR) ?: array() as $path) {
            $name = basename(str_replace('\\', '/', $path));
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) continue;
            $class_file = dbx()->os_path($path . DIRECTORY_SEPARATOR . $name . '.class.php');
            if (!is_file($class_file)) continue;
            $scan = $this->mod_scan_runs($name);
            $count = $images->imageCount($name);
            if ($count <= 0) {
               continue;
            }
            $items[] = array(
               'id' => $name,
               'label' => $name,
               'image_count' => $count,
               'uses_run2' => !empty($scan['uses_run2']) ? 1 : 0,
               'run1_actions' => array_slice($scan['run1'], 0, 24),
            );
         }
      }
      usort($items, function($a, $b) {
         return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
      });
      $this->cms_json_response(array('ok' => 1, 'items' => $items));
   }

   private function mod_catalog_json() {
      $base = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxAdmin/include/');
      require_once $base . 'dbxModuleImages.class.php';
      $images = new \dbx\dbxAdmin\dbxModuleImages();

      $modul = preg_replace('/[^A-Za-z0-9_]+/', '', (string)dbx()->get_modul_var('modul', '', 'parameter'));
      if ($modul === '') {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Modul fehlt.', 'items' => array()));
         return;
      }
      $class_file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/' . $modul . '.class.php');
      if (!is_file($class_file)) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Modul nicht gefunden.', 'items' => array()));
         return;
      }

      $out = $images->catalogForModul($modul);
      $scan = $this->mod_scan_runs($modul);

      $this->cms_json_response(array(
         'ok' => 1,
         'modul' => $modul,
         'uses_run2' => !empty($scan['uses_run2']) ? 1 : 0,
         'run1_actions' => $scan['run1'],
         'items' => is_array($out) ? $out : array(),
      ));
   }

   private function tree_json() {
      $this->cms_json_response(array('ok' => 1) + $this->cms_tree());
   }

   private function lng_coverage_json() {
      $type = trim((string) dbx()->get_modul_var('type', 'page'));
      $type = $type === 'folder' ? 'folder' : 'page';
      $id = (int) dbx()->get_modul_var('id', 0, 'int');
      $lngUid = trim((string) dbx()->get_modul_var('lng_uid', ''));
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      if ($lngUid === '' && $id > 0) {
         $dd = $type === 'folder' ? dbxContentLng::ddFolder() : dbxContentLng::ddContent();
         $lngUid = dbxContentLngSync::ensureRecordUid($db, $dd, $id, $type === 'folder' ? 'f' : 'p');
      }

      $coverage = dbxContentLngSync::coverageForUid($db, $type, $lngUid);
      $this->cms_json_response(array('ok' => 1, 'coverage' => $coverage));
   }

   private function lng_preview_json() {
      if (!dbxContentLngSync::isMasterLng()) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Vorschau nur in der Master-Sprache moeglich.'));
      }

      $payload = $this->request_json();
      $type = (($payload['type'] ?? 'page') === 'folder') ? 'folder' : 'page';
      $id = (int) ($payload['id'] ?? 0);
      $lngs = is_array($payload['lngs'] ?? null) ? $payload['lngs'] : array();
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      dbxContentTranslate::clearWarnings();
      $preview = dbxContentLngSync::previewProvision($db, $type, $id, $lngs);
      $this->cms_json_response(array(
         'ok' => 1,
         'preview' => $preview,
         'provider' => dbxContentTranslate::provider(),
         'translate_warnings' => dbxContentTranslate::warnings(),
      ));
   }

   private function lng_provision_json() {
      if (!dbxContentLngSync::isMasterLng()) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Uebertragung nur in der Master-Sprache moeglich.'));
      }

      $payload = $this->request_json();
      $type = (($payload['type'] ?? 'page') === 'folder') ? 'folder' : 'page';
      $id = (int) ($payload['id'] ?? 0);
      $items = is_array($payload['items'] ?? null) ? $payload['items'] : array();
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      dbxContentTranslate::clearWarnings();
      $result = dbxContentLngSync::provisionFromPreview($db, $type, $id, $items);
      if ((int) ($result['ok'] ?? 0) === 1) {
         $result['media_copied'] = $this->apply_lng_provision_media($db, $type, $id, $result);
         $this->flush_menu_cache();
      }
      $result['translate_warnings'] = dbxContentTranslate::warnings();
      $this->cms_json_response(array('ok' => (int) ($result['ok'] ?? 0), 'result' => $result));
   }

   private function lng_provision_tree_json() {
      if (!dbxContentLngSync::isMasterLng()) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Unterbaum-Uebertragung nur in der Master-Sprache moeglich.'));
      }

      $payload = $this->request_json();
      $id = (int) ($payload['id'] ?? 0);
      $lngs = is_array($payload['lngs'] ?? null) ? $payload['lngs'] : array();
      if ($id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Kein Ordner gewaehlt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      dbxContentTranslate::clearWarnings();
      $result = dbxContentLngSync::provisionFolderTree($db, $id, $lngs);
      if ((int) ($result['ok'] ?? 0) === 1) {
         $mediaCopied = 0;
         foreach (is_array($result['pages'] ?? null) ? $result['pages'] : array() as $pageItem) {
            if (!is_array($pageItem)) {
               continue;
            }
            $masterPageId = (int) ($pageItem['master_id'] ?? 0);
            $prov = is_array($pageItem['result'] ?? null) ? $pageItem['result'] : array();
            if ($masterPageId > 0) {
               $mediaCopied += $this->apply_lng_provision_media($db, 'page', $masterPageId, $prov);
            }
         }
         $result['media_copied'] = $mediaCopied;
         $this->flush_menu_cache();
      }
      $result['translate_warnings'] = dbxContentTranslate::warnings();
      $this->cms_json_response(array('ok' => (int) ($result['ok'] ?? 0), 'result' => $result));
   }

   private function lng_reset_sync_json() {
      if (!dbxContentLngSync::isMasterLng()) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Auto-Sync nur in der Master-Sprache setzbar.'));
      }

      $payload = $this->request_json();
      $type = (($payload['type'] ?? 'page') === 'folder') ? 'folder' : 'page';
      $id = (int) ($payload['id'] ?? 0);
      $lngs = is_array($payload['lngs'] ?? null) ? $payload['lngs'] : array();
      if ($id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Kein Datensatz gewaehlt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $result = dbxContentLngSync::resetSyncToAuto($db, $type, $id, $lngs);
      $this->cms_json_response(array('ok' => count($result['updated'] ?? array()) ? 1 : 0, 'result' => $result));
   }

   private function lng_delete_preview_json() {
      if (!dbxContentLngSync::isMasterLng()) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Mehrsprachige Loesch-Vorschau nur in der Master-Sprache moeglich.'));
      }

      $payload = $this->request_json();
      $type = (($payload['type'] ?? 'page') === 'folder') ? 'folder' : 'page';
      $id = (int) ($payload['id'] ?? 0);
      if ($id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Kein Datensatz gewaehlt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $preview = dbxContentLngSync::previewDelete($db, $type, $id);
      $this->cms_json_response(array('ok' => 1, 'preview' => $preview));
   }

   private function normalize_delete_lngs(array $payload): array {
      $current = dbxContentLng::current();
      $raw = $payload['delete_lngs'] ?? null;
      if (!is_array($raw) || !count($raw)) {
         return array($current);
      }
      if (!dbxContentLngSync::isMasterLng()) {
         return array($current);
      }

      $allowed = dbxContentLngSync::accessibleLngs();
      $out = array();
      foreach ($raw as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng !== '' && in_array($lng, $allowed, true) && !in_array($lng, $out, true)) {
            $out[] = $lng;
         }
      }

      return count($out) ? $out : array($current);
   }

   private function delete_page_in_lngs($db, int $id, array $deleteLngs): array {
      $targets = dbxContentLngSync::resolveDeleteIds($db, 'page', $id, $deleteLngs);
      $deleted = array();
      $errors = array();

      foreach ($targets as $target) {
         if (!is_array($target)) {
            continue;
         }
         $lng = (string) ($target['lng'] ?? '');
         $targetId = (int) ($target['id'] ?? 0);
         if ($targetId <= 0) {
            continue;
         }

         $targetDd = dbxContentLng::ddContent($lng);
         $ok = $db->delete($targetDd, 'id = ' . $targetId, 1, 0);
         if ($ok >= 0) {
            $db->update($this->dd_media, array('active' => 1, 'content_id' => 0, 'folder_id' => 0), 'content_id = ' . $targetId, 0, 1, 1, 0);
            $db->update(
               $this->dd_media_usage,
               array('active' => 0),
               dbxContentMediaUsageScope::withLanguage('content_id = ' . $targetId . ' AND active = 1', $lng),
               0,
               1,
               1,
               0
            );
            $this->flush_deleted_page_cache($targetId, $lng);
            $deleted[] = array('lng' => $lng, 'id' => $targetId);
         } else {
            $errors[] = 'Loeschen in ' . strtoupper($lng) . ' fehlgeschlagen.';
         }
      }

      return array(
         'ok' => count($deleted) ? 1 : 0,
         'deleted' => $deleted,
         'errors' => $errors,
      );
   }

   public function delete_page_record(int $id): array {
      if ($id <= 0) {
         return array('ok' => 0, 'deleted' => array(), 'errors' => array('Keine Seite gewaehlt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $result = $this->delete_page_in_lngs($db, $id, array(dbxContentLng::current()));

      if ((int)($result['ok'] ?? 0) === 1) {
         $this->flush_menu_cache();
      }

      return $result;
   }

   public function delete_folder_record(int $id): array {
      if ($id <= 0) {
         return array('ok' => 0, 'deleted' => array(), 'errors' => array('Kein Ordner gewaehlt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $result = $this->delete_folder_in_lngs($db, $id, array(dbxContentLng::current()));

      if ((int)($result['ok'] ?? 0) === 1) {
         $this->flush_menu_cache();
      }

      return $result;
   }

   public function delete_media_record(int $id): array {
      if ($id <= 0) {
         return array('ok' => 0, 'errors' => array('Keine Medien-ID erhalten.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $used = $db->select(
         $this->dd_media_usage,
         'media_id = ' . $id . ' AND active = 1',
         'id',
         'id',
         'ASC',
         '',
         1,
         0,
         0
      );
      if (is_array($used) && isset($used[0])) {
         return array('ok' => 0, 'errors' => array('Medium wird noch verwendet.'));
      }

      foreach (dbxContentLngSync::accessibleLngs() as $lng) {
         $contentDd = dbxContentLng::ddContent((string)$lng);
         $folderDd = dbxContentLng::ddFolder((string)$lng);
         if ($db->count($contentDd, 'hero_image_id = ' . $id) > 0 || $db->count($folderDd, 'hero_image_id = ' . $id) > 0) {
            return array('ok' => 0, 'errors' => array('Medium wird noch verwendet.'));
         }
         $pages = $db->select($contentDd, '', 'content', 'id', 'ASC', '', 0, 0, 0);
         foreach (is_array($pages) ? $pages : array() as $page) {
            if (preg_match('/data-cms-media-id=["\']?' . $id . '(?:["\'\s>]|$)/i', (string)($page['content'] ?? ''))) {
               return array('ok' => 0, 'errors' => array('Medium wird noch verwendet.'));
            }
         }
      }

      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) {
         return array('ok' => 0, 'errors' => array('Medium nicht gefunden.'));
      }

      if (strtolower((string)($row['storage_type'] ?? 'local')) === 'local') {
         $file = $this->source_media_file($row);
         if ($file !== '') {
            @unlink($file);
         }
      } else {
         $file = $this->source_media_file($row);
         if ($file !== '' && preg_match('/\.json$/i', $file)) {
            @unlink($file);
         }
      }
      $thumb = $this->source_thumb_file($row);
      if ($thumb !== '') {
         @unlink($thumb);
      }

      $ok = $db->update($this->dd_media, array('active' => 0), $id);
      return array(
         'ok' => $ok >= 0 ? 1 : 0,
         'errors' => $ok >= 0 ? array() : array('Medium konnte nicht geloescht werden.'),
      );
   }

   private function delete_folder_in_lngs($db, int $id, array $deleteLngs): array {
      $targets = dbxContentLngSync::resolveDeleteIds($db, 'folder', $id, $deleteLngs);
      $deleted = array();
      $errors = array();

      foreach ($targets as $target) {
         if (!is_array($target)) {
            continue;
         }
         $lng = (string) ($target['lng'] ?? '');
         $targetId = (int) ($target['id'] ?? 0);
         if ($targetId <= 0) {
            continue;
         }

         $check = dbxContentLngSync::folderDeletable($db, $lng, $targetId);
         if ((int) ($check['deletable'] ?? 0) !== 1) {
            $reason = trim((string) ($check['reason'] ?? ''));
            $errors[] = strtoupper($lng) . ($reason !== '' ? ': ' . $reason : ': Ordner kann nicht geloescht werden.');
            continue;
         }

         $targetDd = dbxContentLng::ddFolder($lng);
         $ok = $db->delete($targetDd, 'id = ' . $targetId, 1, 0);
         if ($ok >= 0) {
            $db->update(
               $this->dd_media_usage,
               array('active' => 0),
               dbxContentMediaUsageScope::withLanguage('folder_id = ' . $targetId . ' AND content_id = 0 AND active = 1', $lng),
               0,
               1,
               1,
               0
            );
            $this->flush_deleted_folder_cache($db, $targetId, $lng);
            $deleted[] = array('lng' => $lng, 'id' => $targetId);
         } else {
            $errors[] = 'Loeschen in ' . strtoupper($lng) . ' fehlgeschlagen.';
         }
      }

      return array(
         'ok' => count($deleted) ? 1 : 0,
         'deleted' => $deleted,
         'errors' => $errors,
      );
   }

   private function page_json() {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      if ($id <= 0) {
         $id = $this->resolve_cms_page_id();
      }
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $row = $id > 0 ? $db->select1(dbxContentLng::ddContent(), $id) : $db->empty_record(dbxContentLng::ddContent())[0];
      if (is_array($row) && isset($row['content'])) {
         $row['content'] = $this->normalize_content_media_urls($row['content']);
      }
      if (is_array($row)) {
         $row = $this->normalize_gallery_row($row);
      }
      if ($id > 0 && is_array($row)) {
         $this->sync_inline_media_usage($db, $id, (string)($row['content'] ?? ''), (int)($row['folder'] ?? 0));
      }
      $media = $id > 0 ? $this->media_usage_rows_for_context($db, $id, 0) : array();
      $this->cms_json_response(array(
         'ok' => 1,
         'row' => $row,
         'media' => $media,
         'hero_preview_media' => $this->hero_preview_media($db, $row),
         'hero_parent_preview_media' => $this->inherited_hero_preview_media($db, (int)($row['folder'] ?? 0)),
         'seo_preview_media' => $this->seo_preview_media($db, $row),
      ));
   }

   private function hero_preview_media($db, $row) {
      if (!is_array($row)) return array();
      $hero_id = (int)($row['hero_image_id'] ?? 0);
      if ($hero_id <= 0) return array();
      $hero = $db->select1($this->dd_media, $hero_id);
      if (!is_array($hero) || (int)($hero['active'] ?? 0) !== 1) return array();
      if (!$this->media_file_exists($hero)) return array();
      return $this->normalize_media_row($hero);
   }

   private function inherited_hero_preview_media($db, $folder_id) {
      $folder_id = (int)$folder_id;
      $seen = array();
      while ($folder_id > 0 && !isset($seen[$folder_id])) {
         $seen[$folder_id] = 1;
         $folder = $db->select1(dbxContentLng::ddFolder(), $folder_id, '*', 0);
         if (!is_array($folder)) return array();
         $hero_template = trim((string)($folder['hero_template'] ?? 'parent'));
         if ($this->is_no_hero_template($hero_template)) return array();
         $hero_value = trim((string)($folder['hero_image_id'] ?? 'parent'));
         $hero_id = (int)$hero_value;
         if ($hero_id > 0) {
            $hero = $db->select1($this->dd_media, $hero_id);
            if (!is_array($hero) || (int)($hero['active'] ?? 0) !== 1 || !$this->media_file_exists($hero)) return array();
            $hero = $this->normalize_media_row($hero);
            $hero['parent_folder_id'] = $folder_id;
            $hero['parent_folder_name'] = (string)($folder['name'] ?? '');
            return $hero;
         }
         if ($hero_value === '0' || strtolower($hero_value) === 'none') return array();
         $folder_id = (int)($folder['parent_id'] ?? 0);
      }
      return array();
   }

   private function seo_preview_media($db, $row) {
      if (!is_array($row)) return array();
      $seo_id = (int)($row['seo_image_id'] ?? 0);
      if ($seo_id <= 0) return array();
      $media = $db->select1($this->dd_media, $seo_id);
      if (!is_array($media) || (int)($media['active'] ?? 0) !== 1) return array();
      if (!$this->media_file_exists($media)) return array();
      return $this->normalize_media_row($media);
   }

   private function save_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      $title = $this->clean_text($payload['title'] ?? '', 254);
      $folder = (int)($payload['folder'] ?? 0);
      $keywordsProvided = array_key_exists('keywords', $payload);
      try {
         $permalink = $this->page_permalink($db, $folder, $title, $payload['permalink'] ?? '', $id);
      } catch (\InvalidArgumentException $e) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'field' => 'permalink',
            'msg' => $e->getMessage(),
         ));
      }
      $data = array(
         'activ' => $this->bool_int($payload['activ'] ?? 1, 1),
         'folder' => $folder,
         'title' => $title,
         'menu_title' => $this->clean_text($payload['menu_title'] ?? '', 96),
         'permalink' => $permalink,
         'description' => $this->clean_text($payload['description'] ?? '', 254),
         'template' => $this->clean_text($payload['template'] ?? 'parent', 254),
         'hero_template' => $this->clean_text($payload['hero_template'] ?? 'parent', 80),
         'hero_image_id' => $this->clean_text($payload['hero_image_id'] ?? 'parent', 32),
         'hero_margin_top' => $this->clean_text($payload['hero_margin_top'] ?? 'parent', 32),
         'hero_height' => $this->clean_text($payload['hero_height'] ?? 'parent', 32),
         'hero_variant' => $this->clean_text($payload['hero_variant'] ?? 'parent', 32),
         'hero_sticky' => $this->clean_text($payload['hero_sticky'] ?? 'parent', 32),
         'hero_scroll_layer' => $this->clean_text($payload['hero_scroll_layer'] ?? 'parent', 32),
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => $this->clean_text($payload['gallery_image_size'] ?? 'original', 32),
         'gallery_lightbox_width' => $this->clean_text($payload['gallery_lightbox_width'] ?? '100vw', 32),
         'gallery_overflow' => $this->clean_text($payload['gallery_overflow'] ?? 'grid', 32),
         'gallery_click_behavior' => $this->clean_text($payload['gallery_click_behavior'] ?? 'lightbox', 32),
         'content' => $this->normalize_and_sanitize_content($payload['content'] ?? ''),
      );
      $data = $this->normalize_hero_payload($data);

      if ($id > 0) {
         $existingSeo = $db->select1(dbxContentLng::ddContent(), $id, 'keywords,meta_robots,seo_title,seo_image_id', 0);
         $data['keywords'] = $keywordsProvided
            ? $this->clean_text($payload['keywords'] ?? '', 254)
            : (is_array($existingSeo) ? (string)($existingSeo['keywords'] ?? '') : '');
         $data['meta_robots'] = is_array($existingSeo) ? (string)($existingSeo['meta_robots'] ?? 'index,follow') : 'index,follow';
         $data['seo_title'] = is_array($existingSeo) ? (string)($existingSeo['seo_title'] ?? '') : '';
         $data['seo_image_id'] = is_array($existingSeo) ? max(0, (int)($existingSeo['seo_image_id'] ?? 0)) : 0;
      } else {
         $data['keywords'] = $keywordsProvided ? $this->clean_text($payload['keywords'] ?? '', 254) : '';
         $data['meta_robots'] = 'index,follow';
         $data['seo_title'] = '';
         $data['seo_image_id'] = 0;
      }

      if ($id > 0) {
         $ok = $db->update(dbxContentLng::ddContent(), $data, $id);
         $saved_id = $id;
      } else {
         $ok = $db->insert(dbxContentLng::ddContent(), $data);
         $saved_id = ($ok === 1) ? $db->get_insert_id() : 0;
      }

      if ($ok === 1) {
         $this->sync_saved_hero_media_usage($db, $saved_id, 0, $data['hero_image_id']);
         dbxContentLngSync::afterPageSave($db, $saved_id, $id <= 0);
         $syncResult = array('updated' => array(), 'skipped' => array(), 'errors' => array());
         if ($id > 0 && dbxContentLngSync::isMasterLng()) {
            dbxContentTranslate::clearWarnings();
            $syncResult = dbxContentLngSync::syncSlavesFromMaster($db, 'page', $saved_id);
            $syncResult['media_copied'] = $this->apply_lng_sync_media($db, $saved_id, $syncResult);
            $syncResult['translate_warnings'] = dbxContentTranslate::warnings();
            $this->flush_lng_sync_cache($db, $syncResult);
         }
         $inline_provided = array_key_exists('inline_media_ids', $payload) && is_array($payload['inline_media_ids']);
         $inline_sync = $this->sync_inline_media_usage(
            $db,
            $saved_id,
            $data['content'],
            $data['folder'],
            $inline_provided ? $payload['inline_media_ids'] : null,
            $inline_provided
         );
         $this->flush_saved_page_cache($db, $saved_id);
         $media = $this->media_usage_rows_for_context($db, $saved_id, 0);
               $saved_row = $db->select1(dbxContentLng::ddContent(), $saved_id);
               if (is_array($saved_row)) {
                  if (isset($saved_row['content'])) {
                     $saved_row['content'] = $this->normalize_content_media_urls($saved_row['content']);
                  }
                  $saved_row = $this->normalize_gallery_row($saved_row);
               }
               $this->cms_json_response(array(
                  'ok' => 1,
                  'success' => true,
                  'id' => $saved_id,
                  'row' => $saved_row,
                  'media' => $media,
                  'inline_media_sync' => $inline_sync,
                  'hero_preview_media' => $this->hero_preview_media($db, $saved_row),
                  'hero_parent_preview_media' => $this->inherited_hero_preview_media($db, (int)($saved_row['folder'] ?? 0)),
                  'seo_preview_media' => $this->seo_preview_media($db, $saved_row),
                  'lng_forked' => dbxContentLngSync::isMasterLng() ? 0 : 1,
                  'lng_synced' => count($syncResult['updated'] ?? array()),
                  'lng_media_copied' => (int) ($syncResult['media_copied'] ?? 0),
                  'lng_translate_provider' => dbxContentTranslate::provider(),
                  'lng_sync_targets' => dbxContentLngSync::isMasterLng() ? dbxContentLngSync::slaveLngs() : array(),
                  'lng_sync_updated' => is_array($syncResult['updated'] ?? null) ? $syncResult['updated'] : array(),
                  'lng_sync_skipped' => is_array($syncResult['skipped'] ?? null) ? $syncResult['skipped'] : array(),
                  'lng_sync_errors' => is_array($syncResult['errors'] ?? null) ? $syncResult['errors'] : array(),
                  'translate_warnings' => is_array($syncResult['translate_warnings'] ?? null) ? $syncResult['translate_warnings'] : array(),
                  'open_lng_provision' => $this->lng_provision_open_flag($db, 'page', $saved_id),
               ));
      }

      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_save_error'), 'db' => $ok));
   }

   private function new_page_json() {
      $texts = $this->cms_texts();
      $folder = (int)dbx()->get_modul_var('folder', 0, 'int');
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $title = $texts->format_fd_message('new_page_title', array('time' => date('H:i')));
      $sorter = $this->next_content_sorter($db, $folder);
      $id = 0;
      if ($db->insert(dbxContentLng::ddContent(), array(
         'activ' => 1,
         'folder' => $folder,
         'title' => $title,
         'menu_title' => '',
         'permalink' => dbxContent_permalink::build($db, dbxContentLng::ddFolder(), $folder, $title),
         'template' => 'parent',
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
         'description' => '',
         'keywords' => '',
         'meta_robots' => 'index,follow',
         'seo_title' => '',
         'seo_image_id' => 0,
         'sorter' => $sorter,
         'content' => '<p>' . $texts->get_fd_message('new_page_content') . '</p>',
      ), 0, 1, 0, 1) === 1) {
         $id = $db->get_insert_id();
      }
      if ($id > 0) {
         dbxContentLngSync::afterPageSave($db, $id, true);
         $this->flush_menu_cache();
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'id' => $id,
            'row' => $db->select1(dbxContentLng::ddContent(), $id),
            'open_lng_provision' => $this->lng_provision_open_flag($db, 'page', $id),
         ));
      }
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_create_error'), 'db' => $id));
   }

   private function duplicate_page_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $sourceId = (int)($payload['id'] ?? dbx()->get_modul_var('id', 0, 'int'));
      if ($sourceId <= 0) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_select_first')));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $contentDd = dbxContentLng::ddContent();
      if ($db->begin($contentDd) !== 1) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_duplicate_lock_error')));
      }

      $source = $db->select1($contentDd, $sourceId, '*', 0);
      if (!is_array($source) || (int)($source['id'] ?? 0) !== $sourceId) {
         $db->rollback($contentDd);
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_not_found')));
      }

      $copy = $source;
      foreach (array(
         'id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner',
         'hits', 'xvote', 'vote', 'vote1', 'vote2', 'vote3', 'vote4', 'vote5', 'lastuservote'
      ) as $field) {
         unset($copy[$field]);
      }

      $folderId = (int)($source['folder'] ?? 0);
      $title = (string)($source['title'] ?? 'Unbenannte Seite');
      $copy['sorter'] = $this->next_content_sorter($db, $folderId);
      $copy['permalink'] = $this->duplicate_page_permalink(
         $db,
         $folderId,
         $title,
         (string)($source['permalink'] ?? '')
      );
      $copy['lng_uid'] = '';
      $copy['lng_sync'] = dbxContentLngSync::isMasterLng() ? 'auto' : 'manual';
      $copy['lng_rev'] = 1;
      $copy['lng_synced_rev'] = 0;

      $inserted = $db->insert($contentDd, $copy, 0, 1, 0, 1);
      $newId = $inserted === 1 ? (int)$db->get_insert_id() : 0;
      if ($newId <= 0) {
         $db->rollback($contentDd);
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_duplicate_error'), 'db' => $inserted));
      }

      dbxContentLngSync::afterPageSave($db, $newId, true);
      if ($db->commit($contentDd) !== 1) {
         $db->rollback($contentDd);
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_duplicate_commit_error')));
      }

      $mediaCopied = $this->copy_page_media_usage(
         $db,
         $sourceId,
         $newId,
         $folderId,
         true,
         dbxContentLng::current(),
         dbxContentLng::current()
      );
      $inlineSync = $this->sync_inline_media_usage(
         $db,
         $newId,
         (string)($source['content'] ?? ''),
         $folderId
      );
      $this->flush_saved_page_cache($db, $newId);

      $row = $db->select1($contentDd, $newId);
      if (is_array($row) && isset($row['content'])) {
         $row['content'] = $this->normalize_content_media_urls($row['content']);
         $row = $this->normalize_gallery_row($row);
      }

      $this->cms_json_response(array(
         'ok' => 1,
         'success' => true,
         'id' => $newId,
         'source_id' => $sourceId,
         'row' => $row,
         'permalink' => (string)($row['permalink'] ?? $copy['permalink']),
         'media_copied' => $mediaCopied,
         'inline_media_sync' => $inlineSync,
         'open_lng_provision' => $this->lng_provision_open_flag($db, 'page', $newId),
         'msg' => $texts->get_fd_message('page_duplicated'),
      ));
   }

   private function next_content_sorter($db, $folder_id) {
      $folder_id = (int)$folder_id;
      $rows = $db->select(dbxContentLng::ddContent(), 'folder = ' . $folder_id, '*', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int)($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }

   private function new_folder_json() {
      $texts = $this->cms_texts();
      $parent = (int)dbx()->get_modul_var('parent', 0, 'int');
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $sorter = $this->next_folder_sorter($db, $parent);
      $id = 0;
      if ($db->insert(dbxContentLng::ddFolder(), array(
         'name' => $texts->format_fd_message('new_folder_title', array('time' => date('H:i'))),
         'parent_id' => $parent,
         'sorter' => $sorter,
         'group_read' => $parent > 0 ? 'parent' : '*',
         'template' => $parent > 0 ? 'parent' : 'c-content',
         'hero_template' => $parent > 0 ? 'parent' : 'image-hero',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
      )) === 1) {
         $id = $db->get_insert_id();
      }
      if ($id > 0) {
         dbxContentLngSync::afterFolderSave($db, $id, true);
         $this->flush_menu_cache();
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'id' => $id,
            'row' => $db->select1(dbxContentLng::ddFolder(), $id),
            'open_lng_provision' => $this->lng_provision_open_flag($db, 'folder', $id),
         ));
      }
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_create_error')));
   }

   private function save_folder_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      $name = $this->clean_text($payload['name'] ?? '', 120);
      $parent_id = (int)($payload['parent_id'] ?? 0);
      if ($name === '') {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_name_required')));
      }
      if ($parent_id < 0) $parent_id = 0;
      if ($id > 0 && $parent_id === $id) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_self_parent')));
      }
      if ($id > 0 && $this->folder_is_descendant($db, $parent_id, $id)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_descendant_parent')));
      }

      $rights = $this->clean_text($payload['group_read'] ?? '', 512);
      if ($rights === '') $rights = $parent_id > 0 ? 'parent' : '*';

      $old = $id > 0 ? $db->select1(dbxContentLng::ddFolder(), $id, '*', 0) : array();
      $data = array(
         'name' => $name,
         'parent_id' => $parent_id,
         'group_read' => $rights,
         'template' => $this->clean_text($payload['template'] ?? 'parent', 254),
         'hero_template' => $this->clean_text($payload['hero_template'] ?? 'parent', 80),
         'hero_image_id' => $this->clean_text($payload['hero_image_id'] ?? 'parent', 32),
         'hero_margin_top' => $this->clean_text($payload['hero_margin_top'] ?? 'parent', 32),
         'hero_height' => $this->clean_text($payload['hero_height'] ?? 'parent', 32),
         'hero_variant' => $this->clean_text($payload['hero_variant'] ?? 'parent', 32),
         'hero_sticky' => $this->clean_text($payload['hero_sticky'] ?? 'parent', 32),
         'hero_scroll_layer' => $this->clean_text($payload['hero_scroll_layer'] ?? 'parent', 32),
      );
      $data = $this->normalize_hero_payload($data);
      if ($id <= 0 || (is_array($old) && (int)($old['parent_id'] ?? 0) !== $parent_id)) {
         $data['sorter'] = $this->next_folder_sorter($db, $parent_id);
      }

      if ($id > 0) {
         $ok = $db->update(dbxContentLng::ddFolder(), $data, $id);
         $saved_id = $id;
      } else {
         $ok = $db->insert(dbxContentLng::ddFolder(), $data);
         $saved_id = ($ok === 1) ? $db->get_insert_id() : 0;
      }

      if ($ok === 1 && $saved_id > 0) {
         $this->sync_saved_hero_media_usage($db, 0, $saved_id, $data['hero_image_id']);
         dbxContentLngSync::afterFolderSave($db, $saved_id, $id <= 0);
         $syncResult = array('updated' => array(), 'skipped' => array(), 'errors' => array());
         if ($id > 0 && dbxContentLngSync::isMasterLng()) {
            dbxContentTranslate::clearWarnings();
            $syncResult = dbxContentLngSync::syncSlavesFromMaster($db, 'folder', $saved_id);
            $syncResult['translate_warnings'] = dbxContentTranslate::warnings();
            $this->flush_lng_sync_cache($db, $syncResult);
         }
         $this->flush_folder_cache($db, $saved_id);
         if (is_array($old) && (int)($old['parent_id'] ?? 0) !== $parent_id) {
            $this->flush_folder_cache($db, (int)($old['parent_id'] ?? 0));
         }
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'id' => $saved_id,
            'row' => $db->select1(dbxContentLng::ddFolder(), $saved_id),
            'lng_forked' => dbxContentLngSync::isMasterLng() ? 0 : 1,
            'lng_synced' => count($syncResult['updated'] ?? array()),
            'lng_translate_provider' => dbxContentTranslate::provider(),
            'lng_sync_targets' => dbxContentLngSync::isMasterLng() ? dbxContentLngSync::slaveLngs() : array(),
            'lng_sync_updated' => is_array($syncResult['updated'] ?? null) ? $syncResult['updated'] : array(),
            'lng_sync_skipped' => is_array($syncResult['skipped'] ?? null) ? $syncResult['skipped'] : array(),
            'lng_sync_errors' => is_array($syncResult['errors'] ?? null) ? $syncResult['errors'] : array(),
            'translate_warnings' => is_array($syncResult['translate_warnings'] ?? null) ? $syncResult['translate_warnings'] : array(),
            'open_lng_provision' => $this->lng_provision_open_flag($db, 'folder', $saved_id),
         ));
      }
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_save_error')));
   }

   private function delete_folder_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      if ($id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('folder_none_selected')));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $deleteLngs = $this->normalize_delete_lngs($payload);
      $result = $this->delete_folder_in_lngs($db, $id, $deleteLngs);

      if ((int)($result['ok'] ?? 0) === 1) {
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'id' => $id,
            'deleted' => $result['deleted'] ?? array(),
            'warnings' => $result['errors'] ?? array(),
         ));
      }

      $msg = count($result['errors'] ?? array()) ? implode(' ', $result['errors']) : $texts->get_fd_message('folder_delete_error');
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $msg));
   }

   private function delete_page_json() {
      $texts = $this->cms_texts();
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? dbx()->get_modul_var('id', 0, 'int'));
      if ($id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $texts->get_fd_message('page_none_selected')));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $deleteLngs = $this->normalize_delete_lngs($payload);
      $result = $this->delete_page_in_lngs($db, $id, $deleteLngs);

      if ((int)($result['ok'] ?? 0) === 1) {
         $this->flush_menu_cache();
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'id' => $id,
            'deleted' => $result['deleted'] ?? array(),
            'warnings' => $result['errors'] ?? array(),
         ));
      }

      $msg = count($result['errors'] ?? array()) ? implode(' ', $result['errors']) : $texts->get_fd_message('page_delete_error');
      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => $msg));
   }

   private function move_node_json() {
      $payload = $this->request_json();
      $type = $this->clean_text($payload['type'] ?? '', 16);
      $id = (int)($payload['id'] ?? 0);
      $target = (int)($payload['target_folder'] ?? 0);
      $before_id = (int)($payload['before_id'] ?? 0);
      $after_id = (int)($payload['after_id'] ?? 0);

      if ($id <= 0 || $target < 0 || !in_array($type, array('folder', 'page'), true)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ungueltige Tree-Verschiebung.'));
      }

      if ($type === 'folder' && $id === $target) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner kann nicht in sich selbst verschoben werden.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      if ($type === 'folder') {
         if ($this->folder_is_descendant($db, $target, $id)) {
            $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner kann nicht in einen eigenen Unterordner verschoben werden.'));
         }
         $data = array('parent_id' => $target);
         if ($before_id > 0 || $after_id > 0) {
            $data['sorter'] = $this->next_folder_sorter($db, $target);
         } else {
            $current = $db->select1(dbxContentLng::ddFolder(), $id, '*', 0);
            if (is_array($current) && (int)($current['parent_id'] ?? 0) !== $target) {
               $data['sorter'] = $this->next_folder_sorter($db, $target);
            }
         }
         $ok = $db->update(dbxContentLng::ddFolder(), $data, $id);
         if ($ok >= 0 && ($before_id > 0 || $after_id > 0)) {
            $this->reorder_folders($db, $target, $id, $before_id, $after_id);
         }
      } else {
         $data = array('folder' => $target);
         if ($before_id <= 0 && $after_id <= 0) {
            $current = $db->select1(dbxContentLng::ddContent(), $id, '*', 0);
            if (is_array($current) && (int)($current['folder'] ?? 0) !== $target) {
               $data['sorter'] = $this->next_content_sorter($db, $target);
            }
         }
         $ok = $db->update(dbxContentLng::ddContent(), $data, $id);
         if ($ok >= 0 && ($before_id > 0 || $after_id > 0)) {
            $this->reorder_content_pages($db, $target, $id, $before_id, $after_id);
         }
      }

      if ($ok >= 0) {
         if ($type === 'page') {
            $this->flush_saved_page_cache($db, $id);
         } else {
            $this->flush_folder_cache($db, $id);
            $this->flush_folder_cache($db, $target);
         }
         $this->cms_json_response(array('ok' => 1, 'success' => true, 'id' => $id, 'type' => $type, 'target_folder' => $target));
      }

      $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Tree-Eintrag konnte nicht verschoben werden.'));
   }

   private function folder_is_descendant($db, $folder_id, $ancestor_id) {
      $folder_id = (int)$folder_id;
      $ancestor_id = (int)$ancestor_id;
      $guard = 0;
      while ($folder_id > 0 && $guard < 100) {
         if ($folder_id === $ancestor_id) return true;
         $row = $db->select1(dbxContentLng::ddFolder(), $folder_id);
         if (!is_array($row)) return false;
         $folder_id = (int)($row['parent_id'] ?? 0);
         $guard++;
      }
      return false;
   }

   private function next_folder_sorter($db, $parent_id) {
      $parent_id = (int)$parent_id;
      $rows = $db->select(dbxContentLng::ddFolder(), 'parent_id = ' . $parent_id, '*', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int)($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }

   private function reorder_folders($db, $parent_id, $moved_id, $before_id = 0, $after_id = 0) {
      $parent_id = (int)$parent_id;
      $moved_id = (int)$moved_id;
      $rows = $db->select(dbxContentLng::ddFolder(), 'parent_id = ' . $parent_id, '*', 'sorter,name,id', 'ASC');
      if (!is_array($rows)) return;

      $ordered = array();
      $moved = null;
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $rid = (int)($row['id'] ?? 0);
         if ($rid === $moved_id) {
            $moved = $row;
            continue;
         }
         $ordered[] = $row;
      }
      if (!$moved) return;

      $inserted = false;
      $out = array();
      foreach ($ordered as $row) {
         $rid = (int)($row['id'] ?? 0);
         if ($before_id > 0 && $rid === $before_id) {
            $out[] = $moved;
            $inserted = true;
         }
         $out[] = $row;
         if ($after_id > 0 && $rid === $after_id) {
            $out[] = $moved;
            $inserted = true;
         }
      }
      if (!$inserted) $out[] = $moved;

      $sort = 10;
      foreach ($out as $row) {
         $rid = (int)($row['id'] ?? 0);
         if ($rid <= 0) continue;
         $db->update(dbxContentLng::ddFolder(), array('sorter' => sprintf('%04d', $sort)), $rid);
         $sort += 10;
      }
   }

   private function reorder_content_pages($db, $folder_id, $moved_id, $before_id = 0, $after_id = 0) {
      $folder_id = (int)$folder_id;
      $moved_id = (int)$moved_id;
      $rows = $db->select(dbxContentLng::ddContent(), 'folder = ' . $folder_id, '*', 'sorter,title,id', 'ASC');
      if (!is_array($rows)) return;

      $ordered = array();
      $moved = null;
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $rid = (int)($row['id'] ?? 0);
         if ($rid === $moved_id) {
            $moved = $row;
            continue;
         }
         $ordered[] = $row;
      }
      if (!$moved) return;

      $inserted = false;
      $out = array();
      foreach ($ordered as $row) {
         $rid = (int)($row['id'] ?? 0);
         if ($before_id > 0 && $rid === $before_id) {
            $out[] = $moved;
            $inserted = true;
         }
         $out[] = $row;
         if ($after_id > 0 && $rid === $after_id) {
            $out[] = $moved;
            $inserted = true;
         }
      }
      if (!$inserted) $out[] = $moved;

      $sort = 10;
      $changed = array();
      foreach ($out as $row) {
         $rid = (int)($row['id'] ?? 0);
         if ($rid <= 0) continue;
         $sorter = sprintf('%04d', $sort);
         if ((string)($row['sorter'] ?? '') !== $sorter) {
            $db->update(dbxContentLng::ddContent(), array('sorter' => $sorter), $rid);
            $changed[] = $rid;
         }
         $sort += 10;
      }
      foreach ($changed as $rid) {
         dbxContentPageCache::invalidateContent((int)$rid);
      }
      if (count($changed)) {
         dbxContentPageCache::invalidateAllMenus();
      }
   }

   private function media_usage_page_map($db, $usage_rows) {
      $map = array();
      if (!is_array($usage_rows)) return $map;

      $page_cache = array();
      $folder_cache = array();

      foreach ($usage_rows as $usage) {
         if (!is_array($usage)) continue;
         $media_id = (int)($usage['media_id'] ?? 0);
         $content_id = (int)($usage['content_id'] ?? 0);
         $content_lng = dbxContentMediaUsageScope::language((string)($usage['content_lng'] ?? ''));
         if ($media_id <= 0 || $content_id <= 0) continue;

         $page_key = $content_lng . ':' . $content_id;
         if (!array_key_exists($page_key, $page_cache)) {
            $page_cache[$page_key] = $db->select1(dbxContentLng::ddContent($content_lng), $content_id, 'id,folder,title', 0);
         }
         $page = is_array($page_cache[$page_key]) ? $page_cache[$page_key] : array();
         $folder_id = (int)($page['folder'] ?? ($usage['folder_id'] ?? 0));

         $folder_key = $content_lng . ':' . $folder_id;
         if ($folder_id > 0 && !array_key_exists($folder_key, $folder_cache)) {
            $folder_cache[$folder_key] = $db->select1(dbxContentLng::ddFolder($content_lng), $folder_id, '*', 0);
         }
         $folder = ($folder_id > 0 && is_array($folder_cache[$folder_key] ?? null)) ? $folder_cache[$folder_key] : array();

         if (!isset($map[$media_id])) $map[$media_id] = array();
         if (!isset($map[$media_id][$page_key])) {
            $map[$media_id][$page_key] = array(
               'id' => $content_id,
               'content_id' => $content_id,
               'content_lng' => $content_lng,
               'title' => (string)($page['title'] ?? ''),
               'folder_id' => $folder_id,
               'folder_title' => (string)($folder['name'] ?? $folder['title'] ?? ''),
               'slots' => array(),
            );
         }

         $slot = trim((string)($usage['slot'] ?? ''));
         if ($slot !== '' && !in_array($slot, $map[$media_id][$page_key]['slots'], true)) {
            $map[$media_id][$page_key]['slots'][] = $slot;
         }
      }

      foreach ($map as $media_id => $items) {
         ksort($items, SORT_NUMERIC);
         $map[$media_id] = array_values($items);
      }

      return $map;
   }

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

      $rows = array();
      foreach ($usage_rows as $usage) {
         if (!is_array($usage)) continue;
         $media_id = (int)($usage['media_id'] ?? 0);
         if ($media_id <= 0) continue;
         $row = $db->select1($this->dd_media, $media_id);
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

   private function is_no_hero_template($value) {
      $value = strtolower(trim((string)$value));
      return in_array($value, array('none', 'no-hero', '0', 'off'), true);
   }

   private function normalize_hero_payload(array $data) {
      if ($this->is_no_hero_template($data['hero_template'] ?? '')) {
         $data['hero_template'] = 'none';
         $data['hero_image_id'] = '0';
      }
      return $data;
   }

   private function source_media_file($row) {
      $rel = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
      if ($rel === '' || strpos($rel, '..') !== false) return '';
      $root = rtrim(dbx()->get_file_dir(), '/\\') . '/';
      $root = dbx()->os_path($root);
      $base = realpath(rtrim($root, '/\\'));
      if (!$base) return '';
      $file = $root . $rel;
      $file = dbx()->os_path($file);
      $real = realpath($file);
      return $real && strpos($real, $base) === 0 && is_file($real) && is_readable($real) ? $real : '';
   }

   private function copy_media_to_slot($db, array $row, $slot) {
      return 0;
      $slot = $this->valid_media_slot($slot);
      $src = $this->source_media_file($row);
      if ($src === '') return 0;

      $dir = $this->cms_media_dir($slot);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      if (!is_dir($dir)) return 0;

      $name = $this->unique_media_name((string)($row['file_name'] ?? basename($src)), $slot);
      $dst = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
      if (!copy($src, $dst)) return 0;

      $rel = $this->media_rel_dir($slot) . $name;
      $mime = (string)($row['mime'] ?? '');
      $width = 0;
      $height = 0;
      $img = @getimagesize($dst);
      if (is_array($img)) {
         $width = (int)($img[0] ?? 0);
         $height = (int)($img[1] ?? 0);
      }

      $insert = array(
         'active' => 1,
         'content_id' => (int)($row['content_id'] ?? 0),
         'folder_id' => (int)($row['folder_id'] ?? 0),
         'slot' => $slot,
         'usage' => $slot,
         'sorter' => $this->next_media_sorter($db, (int)($row['content_id'] ?? 0), $slot),
         'template' => (string)($row['template'] ?? ''),
         'title' => (string)($row['title'] ?? pathinfo($name, PATHINFO_FILENAME)),
         'alt' => (string)($row['alt'] ?? $row['title'] ?? ''),
         'caption' => (string)($row['caption'] ?? ''),
         'file_name' => $name,
         'file_path' => $rel,
         'mime' => $mime,
         'size' => (int)@filesize($dst),
         'width' => $width,
         'height' => $height,
         'tags' => (string)($row['tags'] ?? ''),
         'media_type' => $this->media_type(array('mime' => $mime, 'file_name' => $name)),
      );
      $thumb = $this->create_media_thumbnail($dst, $slot, $name, $mime);
      if ($thumb) $insert = array_merge($insert, $thumb);
      return ($db->insert($this->dd_media, $insert) === 1) ? $db->get_insert_id() : 0;
   }

   private function set_media_slot_json() {
      $payload = $this->request_json();
      $usage_id = (int)($payload['usage_id'] ?? 0);
      $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      $slot = $this->valid_media_slot($payload['slot'] ?? 'gallery');
      if ($usage_id <= 0 && $id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Medien- oder Usage-ID erhalten.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      if ($usage_id <= 0) {
         $rows = $db->select($this->dd_media_usage, $this->usage_where($id), '*', 'id', 'DESC', '', 1, 0, 0);
         if (is_array($rows) && isset($rows[0])) $usage_id = (int)($rows[0]['id'] ?? 0);
      }
      if ($usage_id <= 0) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine vorhandene Zuordnung gefunden.'));
      }

      $usage = $db->select1($this->dd_media_usage, $usage_id);
      if (!is_array($usage) || (int)($usage['active'] ?? 0) !== 1) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Zuordnung nicht gefunden.'));
      }
      if ($slot === 'hero') $this->deactivate_existing_hero_media($db, (int)($usage['content_id'] ?? 0), (int)($usage['folder_id'] ?? 0), (int)($usage['media_id'] ?? 0));
      $ok = $db->update($this->dd_media_usage, array('slot' => $slot), $usage_id);
      $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
      $row = $this->normalize_media_row($db->select1($this->dd_media, (int)($usage['media_id'] ?? 0)));
      if ($ok >= 0) {
         $this->flush_media_cache($db, (int)($usage['content_id'] ?? 0), (int)($usage['folder_id'] ?? 0));
      }
      $this->cms_json_response(array('ok' => ($ok >= 0 ? 1 : 0), 'success' => ($ok >= 0), 'row' => $row, 'usage' => $usage, 'msg' => 'Zuordnung aktualisiert.'));
   }

   private function assign_media_json() {
      $payload = $this->request_json();
      $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      $content_id = (int)($payload['content_id'] ?? 0);
      $folder_id = (int)($payload['folder_id'] ?? 0);
      $slot = $this->valid_media_slot($payload['slot'] ?? 'gallery');
      if ($id <= 0 || ($content_id <= 0 && !($folder_id > 0 && $slot === 'hero'))) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Zuordnung erhalten.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Medium nicht gefunden.'));
      }

      $usage_id = $this->create_media_usage(
         $db,
         $id,
         $content_id,
         $folder_id,
         $slot,
         (string)($payload['template'] ?? $row['template'] ?? ''),
         (string)($payload['caption'] ?? ''),
         is_array($payload['settings'] ?? null) ? json_encode($payload['settings'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)($payload['settings'] ?? '')
      );
      if ($usage_id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Medium konnte nicht zugeordnet werden.'));
      $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
      $this->flush_media_cache($db, $content_id, $folder_id);
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'id' => $id, 'usage_id' => $usage_id, 'row' => $this->normalize_media_row($row), 'usage' => $usage, 'msg' => 'Zuordnung angelegt.'));
   }

   private function sort_media_json() {
      $payload = $this->request_json();
      $content_id = (int)($payload['content_id'] ?? 0);
      $folder_id = (int)($payload['folder_id'] ?? 0);
      $slot = $this->valid_media_slot($payload['slot'] ?? 'gallery');
      $ids = $payload['ids'] ?? array();
      if (($content_id <= 0 && $folder_id <= 0) || !is_array($ids)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Sortierung erhalten.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $sort = 10;
      foreach ($ids as $id) {
         $id = (int)$id;
         if ($id <= 0) continue;
         $where = dbxContentMediaUsageScope::withLanguage(
            "active = 1 AND slot = '" . str_replace("'", "''", $slot) . "'",
            dbxContentLng::current()
         );
         if ($content_id > 0) $where .= ' AND content_id = ' . $content_id;
         if ($folder_id > 0) $where .= ' AND folder_id = ' . $folder_id;
         $where .= ' AND (id = ' . $id . ' OR media_id = ' . $id . ')';
         $rows = $db->select($this->dd_media_usage, $where, '*', 'id', 'ASC', '', 1, 0, 0);
         if (!is_array($rows) || !isset($rows[0])) continue;
         $db->update($this->dd_media_usage, array('sorter' => sprintf('%04d', $sort)), (int)$rows[0]['id']);
         $sort += 10;
      }
      $this->flush_media_cache($db, $content_id, $folder_id);
      $this->cms_json_response(array('ok' => 1, 'success' => true));
   }

   private function filter_existing_media($db, array $rows) {
      $out = array();
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         if ($this->media_file_exists($row)) {
            $out[] = $row;
            continue;
         }
         $id = (int)($row['id'] ?? 0);
         if ($id > 0) {
            $db->update($this->dd_media, array('active' => 0), $id);
         }
      }
      return $out;
   }

   private function upload_json() {
      $content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
      $max_upload = $this->upload_max_bytes();
      if ($content_length > 0 && $max_upload > 0 && $content_length > $max_upload) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => 'Upload zu gross. Erlaubt sind maximal ' . round($max_upload / 1024 / 1024) . ' MB.'
         ));
      }
      if (!$this->verify_media_form('upload')) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => 'Ungueltiger oder abgelaufener Formular-Token.'
         ));
      }

      $file = $this->first_upload_file();
      if (empty($file) || !is_array($file)) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Keine Datei erhalten. Bitte Upload-Limit und Dateigroesse pruefen.'));
      }

      if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
         if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->cms_json_response(array('ok' => 0, 'msg' => 'Bitte zuerst eine Datei auswaehlen.'));
         }
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Upload fehlgeschlagen.'));
      }

      $name = basename((string)$file['name']);
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, array('jpg','jpeg','png','gif','webp','svg','pdf','mp4','webm','ogv','ogg','mov','m4v'), true)) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Dateityp nicht erlaubt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $content_id = (int)($_POST['content_id'] ?? 0);
      $folder_id = (int)($_POST['folder_id'] ?? 0);
      $slot = $this->valid_media_slot($_POST['slot'] ?? 'gallery');
      $probe_type = $this->media_type(array('file_name' => $name, 'mime' => (string)($file['type'] ?? '')));
      $media_folder = $this->clean_media_folder($_POST['media_folder'] ?? '', $probe_type);
      $media_rel_dir = $this->media_folder_rel_dir($media_folder, $probe_type);
      $dir = $this->cms_media_dir($media_folder);
      if (!is_dir($dir)) {
         mkdir($dir, 0777, true);
      }

      $dstName = $this->unique_media_name($name);
      $dst = $dir . $dstName;

      if (!move_uploaded_file($file['tmp_name'], $dst)) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Datei konnte nicht gespeichert werden.'));
      }

      $width = 0;
      $height = 0;
      $detected_mime = function_exists('mime_content_type') ? (string)@mime_content_type($dst) : '';
      $mime = $detected_mime !== '' ? $detected_mime : (string)($file['type'] ?? '');
      $img = @getimagesize($dst);
      if (is_array($img)) {
         $width = (int)($img[0] ?? 0);
         $height = (int)($img[1] ?? 0);
      }
      $max_image_size = max(800, min(6000, (int)($_POST['max_image_size'] ?? 2560)));
      if ($width > 0 && $height > 0 && $this->media_type(array('mime' => $mime, 'file_name' => $dstName)) === 'image') {
         if ($this->resize_image_to_max($dst, $mime, $max_image_size)) {
            clearstatcache(true, $dst);
            $img = @getimagesize($dst);
            if (is_array($img)) {
               $width = (int)($img[0] ?? 0);
               $height = (int)($img[1] ?? 0);
            }
         }

         $webp = $this->convert_media_image_to_webp($dst, $dstName, $mime);
         if (is_array($webp) && !empty($webp['file']) && !empty($webp['name'])) {
            $dst = (string)$webp['file'];
            $dstName = (string)$webp['name'];
            $mime = (string)($webp['mime'] ?? 'image/webp');
            clearstatcache(true, $dst);
            $img = @getimagesize($dst);
            if (is_array($img)) {
               $width = (int)($img[0] ?? 0);
               $height = (int)($img[1] ?? 0);
            }
         }
      }

      $media_tpl = $this->clean_text($_POST['template'] ?? '', 80);
      $file_size = (int)@filesize($dst);
      $title = pathinfo($name, PATHINFO_FILENAME);
      $media_type = $this->media_type(array('mime' => $mime, 'file_name' => $dstName));
      $media_folder = $this->clean_media_folder($media_folder, $media_type);
      if ($media_tpl === '') {
         $media_tpl = $media_type === 'video' ? ($slot === 'hero' ? 'video-hero' : 'video-gallery') : '';
      }

      $existing = $db->select(
         $this->dd_media,
         "active = 1 AND storage_type = 'local' AND media_folder = '" . str_replace("'", "''", $media_folder) . "' AND title = '" . str_replace("'", "''", $title) . "' AND size = " . $file_size,
         '*',
         'id',
         'DESC'
      );
      if (is_array($existing) && isset($existing[0]) && $this->media_file_exists($existing[0])) {
         @unlink($dst);
         $row = $this->normalize_media_row($existing[0]);
         $usage = array();
         if ($content_id > 0 || $folder_id > 0) {
            $usage_id = $this->create_media_usage($db, (int)($row['id'] ?? 0), $content_id, $folder_id, $slot, $media_tpl, '', '');
            if ($usage_id > 0) $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
         }
         $this->flush_media_cache($db, $content_id, $folder_id);
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'duplicate' => true,
            'row' => $row,
            'usage' => $usage,
            'files' => array($row['file_name'] ?? ''),
            'path' => '',
            'baseurl' => '',
         ));
      }

      $insert = array(
         'active' => 1,
         'content_id' => 0,
         'folder_id' => 0,
         'slot' => '',
         'usage' => '',
         'sorter' => '',
         'template' => $media_tpl,
         'title' => $title,
         'alt' => $title,
         'file_name' => $dstName,
         'file_path' => $media_rel_dir . $dstName,
         'mime' => $mime,
         'size' => $file_size,
         'width' => $width,
         'height' => $height,
         'media_type' => $media_type,
         'storage_type' => 'local',
         'media_folder' => $media_folder,
      );
      $thumb = $this->create_media_thumbnail($dst, $media_folder, $dstName, $mime);
      if ($thumb) $insert = array_merge($insert, $thumb);
      $id = ($db->insert($this->dd_media, $insert) === 1) ? $db->get_insert_id() : 0;

      if ($id > 0) {
         $usage = array();
         if ($content_id > 0 || $folder_id > 0) {
            $usage_id = $this->create_media_usage($db, $id, $content_id, $folder_id, $slot, $media_tpl, '', '');
            if ($usage_id > 0) $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
         }
         $row = $this->normalize_media_row($db->select1($this->dd_media, $id));
         $baseurl = '';
         if (!empty($row['url'])) {
            $baseurl = preg_replace('~/[^/]*$~', '/', $row['url']);
         }
         $this->flush_media_cache($db, $content_id, $folder_id);
         $this->cms_json_response(array(
            'ok' => 1,
            'success' => true,
            'row' => $row,
            'usage' => $usage,
            'files' => array($row['file_name'] ?? $dstName),
            'path' => '',
            'baseurl' => $baseurl,
         ));
      }

      $this->cms_json_response(array('ok' => 0, 'msg' => 'Mediendatensatz konnte nicht gespeichert werden.'));
   }

   private function remove_media_json() {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $usage_id = (int)dbx()->get_modul_var('usage_id', 0, 'int');
      $payload = array();
      if ($id <= 0 || $usage_id <= 0) {
         $payload = $this->request_json();
         if ($usage_id <= 0) $usage_id = (int)($payload['usage_id'] ?? 0);
         if ($id <= 0) $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      }
      if ($usage_id <= 0 && $id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Medien- oder Usage-ID erhalten.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $content_id = (int)($payload['content_id'] ?? dbx()->get_modul_var('content_id', 0, 'int'));
      $folder_id = (int)($payload['folder_id'] ?? dbx()->get_modul_var('folder_id', 0, 'int'));
      if ($usage_id > 0) {
         $usage_row = $db->select1($this->dd_media_usage, $usage_id);
         if (is_array($usage_row)) {
            if ($content_id <= 0) $content_id = (int)($usage_row['content_id'] ?? 0);
            if ($folder_id <= 0) $folder_id = (int)($usage_row['folder_id'] ?? 0);
            if ($id <= 0) $id = (int)($usage_row['media_id'] ?? 0);
         }
         $ok = $db->update($this->dd_media_usage, array('active' => 0), $usage_id);
      } else {
         $slot = trim((string)($payload['slot'] ?? dbx()->get_modul_var('slot', '', 'varchar')));
         if ($content_id <= 0 && $folder_id <= 0) {
            $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Seiten- oder Ordner-Zuordnung erhalten.'));
         }
         $where = 'media_id = ' . $id . ' AND active = 1';
         if ($content_id > 0) $where .= ' AND content_id = ' . $content_id;
         if ($folder_id > 0) $where .= ' AND folder_id = ' . $folder_id;
         if ($slot !== '') $where .= " AND slot = '" . str_replace("'", "''", $slot) . "'";
         $where = dbxContentMediaUsageScope::withLanguage($where, dbxContentLng::current());
         $ok = $db->update($this->dd_media_usage, array('active' => 0), $where, 0, 1, 1, 0);
      }
      $media = ($content_id > 0 || $folder_id > 0) ? $this->media_usage_rows_for_context($db, $content_id, $content_id > 0 ? 0 : $folder_id) : array();
      if ($ok >= 0) {
         $this->flush_media_cache($db, $content_id, $folder_id);
      }
      $this->cms_json_response(array('ok' => ($ok >= 0 ? 1 : 0), 'success' => ($ok >= 0), 'id' => $id, 'usage_id' => $usage_id, 'media' => $media, 'rows' => $media, 'msg' => 'Zuordnung entfernt.'));
   }

   private function delete_media_json() {
      $id = (int)dbx()->get_modul_var('id', 0, 'int');
      $force = (int)dbx()->get_modul_var('force', 0, 'int');
      if ($id <= 0) {
         $payload = $this->request_json();
         $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
         $force = (int)($payload['force'] ?? 0);
      }
      if ($id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Medien-ID erhalten.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $used = $db->select($this->dd_media_usage, $this->usage_where($id), '*', 'id', 'ASC', '', 1, 0, 0);
      if (is_array($used) && isset($used[0]) && !$force) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Medium wird noch verwendet.'));
      }
      $row = $db->select1($this->dd_media, $id);
      if (is_array($row)) {
         $this->flush_media_by_media_id($db, $id);
         if ($force) $db->update($this->dd_media_usage, array('active' => 0), 'media_id = ' . $id . ' AND active = 1', 0, 1, 1, 0);
         if (strtolower((string)($row['storage_type'] ?? 'local')) === 'local') {
            $file = $this->source_media_file($row);
            if ($file !== '') @unlink($file);
         } else {
            $file = $this->source_media_file($row);
            if ($file !== '' && preg_match('/\.json$/i', $file)) @unlink($file);
         }
         $thumb = $this->source_thumb_file($row);
         if ($thumb !== '') @unlink($thumb);
      }
      $ok = $db->update($this->dd_media, array('active' => 0), $id);
      $this->cms_json_response(array('ok' => ($ok >= 0 ? 1 : 0), 'success' => ($ok >= 0), 'id' => $id, 'msg' => 'Medium geloescht.'));
   }

   private function youtube_video_id($url) {
      $url = trim((string)$url);
      if ($url === '') return '';
      if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) return $url;
      $parts = @parse_url($url);
      if (!is_array($parts)) return '';
      $host = strtolower((string)($parts['host'] ?? ''));
      $path = trim((string)($parts['path'] ?? ''), '/');
      if (strpos($host, 'youtu.be') !== false && preg_match('~^([A-Za-z0-9_-]{11})~', $path, $m)) return $m[1];
      if (strpos($host, 'youtube.com') !== false) {
         parse_str((string)($parts['query'] ?? ''), $query);
         if (!empty($query['v']) && preg_match('~^[A-Za-z0-9_-]{11}$~', (string)$query['v'])) return (string)$query['v'];
         if (preg_match('~^(embed|shorts)/([A-Za-z0-9_-]{11})~', $path, $m)) return $m[2];
      }
      return '';
   }

   private function external_video_json() {
      if (!$this->verify_media_form('external')) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => 'Ungueltiger oder abgelaufener Formular-Token.'
         ));
      }
      $payload = $this->request_json();
      $provider = strtolower(trim((string)($payload['provider'] ?? 'youtube')));
      $external_url = trim((string)($payload['external_url'] ?? $payload['url'] ?? ''));
      if ($provider !== 'youtube') $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Provider nicht unterstuetzt.'));
      $video_id = $this->youtube_video_id($external_url);
      if ($video_id === '') $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'YouTube-URL nicht erkannt.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $content_id = (int)($payload['content_id'] ?? 0);
      $folder_id = (int)($payload['folder_id'] ?? 0);
      $slot = $this->valid_media_slot($payload['slot'] ?? 'gallery');
      $media_folder = $this->clean_media_folder($payload['media_folder'] ?? 'youtube', 'external_video');
      $title = $this->clean_text($payload['title'] ?? ('YouTube ' . $video_id), 160);
      $embed_url = 'https://www.youtube.com/embed/' . $video_id;
      $thumb_url = 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg';

      $dir = $this->cms_media_dir($media_folder);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      $rel = $this->media_folder_rel_dir($media_folder, 'external_video') . $video_id . '.json';
      $file = $this->file_from_media_rel($rel);
      if ($file !== '') {
         @file_put_contents($file, json_encode(array(
            'provider' => 'youtube',
            'provider_id' => $video_id,
            'external_url' => $external_url,
            'embed_url' => $embed_url,
            'title' => $title,
            'thumb_url' => $thumb_url,
         ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      }
      $existing = $db->select($this->dd_media, "active = 1 AND provider = 'youtube' AND provider_id = '" . str_replace("'", "''", $video_id) . "'", '*', 'id', 'DESC', '', 1, 0, 0);
      if (is_array($existing) && isset($existing[0])) {
         $row = $existing[0];
         $update = array(
            'media_folder' => $media_folder,
            'file_name' => $video_id . '.json',
            'file_path' => $rel,
            'mime' => 'application/json',
            'media_type' => 'external_video',
            'storage_type' => 'external',
            'external_url' => $external_url,
            'embed_url' => $embed_url,
            'title' => $title,
            'size' => $file !== '' && is_file($file) ? (int)@filesize($file) : 0,
         );
         $db->update($this->dd_media, $update, (int)($row['id'] ?? 0));
         $row = array_merge($row, $update);
      } else {
         $id = 0;
         if ($db->insert($this->dd_media, array(
            'active' => 1,
            'content_id' => 0,
            'folder_id' => 0,
            'slot' => '',
            'usage' => '',
            'sorter' => '',
            'template' => 'video-gallery',
            'title' => $title,
            'alt' => $title,
            'file_name' => $video_id . '.json',
            'file_path' => $rel,
            'mime' => 'application/json',
            'size' => $file !== '' && is_file($file) ? (int)@filesize($file) : 0,
            'width' => 0,
            'height' => 0,
            'media_type' => 'external_video',
            'storage_type' => 'external',
            'media_folder' => $media_folder,
            'provider' => 'youtube',
            'provider_id' => $video_id,
            'external_url' => $external_url,
            'embed_url' => $embed_url,
         )) === 1) {
            $id = $db->get_insert_id();
         }
         $row = $db->select1($this->dd_media, $id);
      }

      $usage = array();
      if (is_array($row) && ((int)($row['id'] ?? 0) > 0) && ($content_id > 0 || $folder_id > 0)) {
         $usage_id = $this->create_media_usage($db, (int)$row['id'], $content_id, $folder_id, $slot, 'video-gallery', '', '');
         if ($usage_id > 0) $usage = $this->normalize_usage_row($db->select1($this->dd_media_usage, $usage_id));
      }
      $this->flush_media_cache($db, $content_id, $folder_id);
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'row' => $this->normalize_media_row($row), 'usage' => $usage, 'msg' => 'Externes Video gespeichert.'));
   }

   private function media_dir_has_content($dir) {
      if (!is_dir($dir) || !is_readable($dir)) return false;
      $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
      foreach ($it as $item) {
         if ($item->isFile()) return true;
      }
      return false;
   }

   private function is_reserved_media_folder($folder) {
      $folder = $this->canonical_media_folder(strtolower(trim(str_replace('\\', '/', (string)$folder), '/')));
      if ($folder === 'module' || strpos($folder, 'module/') === 0) return true;
      if (preg_match('~^img/external(/|$)~', $folder)) return true;
      if (preg_match('~^img/video(/|$)~', $folder)) return true;
      return in_array($folder, $this->media_slots(), true);
   }

   private function media_folder_dir_is_listable($folder, $path, $base = '') {
      return is_dir($path) && is_readable($path);
   }

   private function media_folder_is_listable($folder) {
      $folder = $this->canonical_media_folder($folder);
      if ($folder === '' || $folder === 'module') return false;
      if ($this->is_reserved_media_folder($folder)) return false;
      $dir = $this->cms_media_dir($folder);
      return is_dir($dir) && is_readable($dir);
   }

   private function collect_custom_media_root_folders(array &$folders) {
      $mediaRoot = rtrim(dbx()->get_file_dir(), '/\\') . '/media/';
      $mediaRoot = dbx()->os_path($mediaRoot);
      if (!is_dir($mediaRoot) || !is_readable($mediaRoot)) return;
      $skip = array('.', '..', '_thumbs', 'img', 'video', 'videos', 'youtube', 'external', 'file', 'module');
      $skip = array_merge($skip, $this->media_slots());
      $root_norm = str_replace('\\', '/', rtrim($mediaRoot, '/\\')) . '/';
      $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($mediaRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
      foreach ($it as $item) {
         if (!$item->isDir()) continue;
         $path = str_replace('\\', '/', $item->getPathname());
         if (strpos($path . '/', $root_norm) !== 0) continue;
         $rel = trim(substr($path, strlen($root_norm)), '/');
         if ($rel === '') continue;
         $top = strtok($rel, '/');
         if ($top === false || in_array($top, $skip, true)) continue;
         if ($this->is_reserved_media_folder($rel)) continue;
         $folders[] = $rel;
      }
   }

   private function media_folders_json() {
      $bases = $this->media_standard_bases();
      $folders = array();
      foreach ($bases as $base => $type) {
         $root = $this->cms_media_base_dir($base);
         if (!is_dir($root) || !is_readable($root)) continue;
         if ($base === 'youtube' || $type === 'video') {
            $folders[] = $this->clean_media_folder($base, $type);
         }
         $root_norm = str_replace('\\', '/', rtrim($root, '/\\')) . '/';
         $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
         foreach ($it as $item) {
            if (!$item->isDir()) continue;
            $path = str_replace('\\', '/', $item->getPathname());
            if (strpos($path . '/', $root_norm) !== 0) continue;
            $rel = trim(substr($path, strlen($root_norm)), '/');
            if ($rel === '') continue;
            $folder = $this->clean_media_folder($base . '/' . $rel, $type);
            if ($this->is_reserved_media_folder($folder)) continue;
            if (!$this->media_folder_dir_is_listable($folder, $path, $base)) continue;
            $folders[] = $folder;
         }
      }
      $this->collect_custom_media_root_folders($folders);

      $folders = array_values(array_unique(array_map(array($this, 'canonical_media_folder'), $folders)));
      $verified = array();
      foreach ($folders as $folder) {
         $folder = $this->canonical_media_folder($folder);
         if ($folder === '' || $this->is_excluded_cms_media_folder($folder)) continue;
         $dir = $this->cms_media_dir($folder);
         if (!is_dir($dir) || !is_readable($dir)) continue;
         $verified[] = $folder;
      }
      $folders = $verified;
      sort($folders);
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'root' => 'files/media/', 'folders' => $folders));
   }

   private function media_folder_create_json() {
      $payload = $this->request_json();
      $type = $this->media_type(array('media_type' => ($payload['media_type'] ?? 'image')));
      $folder = $this->clean_media_folder($payload['media_folder'] ?? $payload['folder'] ?? '', $type);
      if ($this->is_excluded_cms_media_folder($folder)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Dieser Ordner ist im CMS-Medienbrowser nicht verfuegbar.'));
      }
      $dir = $this->cms_media_dir($folder);
      if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner konnte nicht angelegt werden.'));
      }
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'folder' => $folder, 'msg' => 'Ordner angelegt.'));
   }

   private function media_folder_delete_json() {
      $payload = $this->request_json();
      $raw_folder = $payload['media_folder'] ?? $payload['folder'] ?? '';
      $type = $this->media_type(array('media_type' => ($payload['media_type'] ?? $this->media_type_from_folder($raw_folder))));
      $folder = $this->clean_media_folder($raw_folder, $type);
      if ($this->is_excluded_cms_media_folder($folder)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Dieser Ordner ist im CMS-Medienbrowser nicht verfuegbar.'));
      }
      $dir = $this->cms_media_dir($folder);
      if (!is_dir($dir)) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner nicht gefunden.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $used = $db->select($this->dd_media, "active = 1 AND media_folder = '" . str_replace("'", "''", $folder) . "'", '*', 'id', 'ASC', '', 1, 0, 0);
      if (is_array($used) && isset($used[0])) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner enthaelt noch Medien.'));

      $items = @scandir($dir);
      $items = is_array($items) ? array_diff($items, array('.', '..')) : array();
      if (!empty($items)) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner ist nicht leer.'));
      if (!@rmdir($dir)) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner konnte nicht geloescht werden.'));
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'folder' => $folder, 'msg' => 'Ordner geloescht.'));
   }

   private function media_folder_rename_json() {
      $payload = $this->request_json();
      $from = $this->clean_media_folder($payload['from_folder'] ?? $payload['media_folder_from'] ?? $payload['from'] ?? '', 'image');
      $to_raw = trim((string)($payload['to_folder'] ?? $payload['media_folder_to'] ?? $payload['to'] ?? ''));
      if ($to_raw !== '' && strpos($to_raw, '/') === false && $from !== '') {
         $base = strtok($from, '/');
         $to_raw = ($base !== false && $base !== '' ? $base : 'img') . '/' . $to_raw;
      }
      $to = $this->clean_media_folder($to_raw, $this->media_type_from_folder($to_raw !== '' ? $to_raw : $from));
      if ($from === '' || $to === '' || $from === $to) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ungueltiger Ordner.'));
      }
      if ($this->is_excluded_cms_media_folder($from) || $this->is_excluded_cms_media_folder($to)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Dieser Ordner ist im CMS-Medienbrowser nicht verfuegbar.'));
      }
      $from_dir = $this->cms_media_dir($from);
      $to_dir = $this->cms_media_dir($to);
      if (!is_dir($from_dir) || !is_readable($from_dir)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Quellordner nicht gefunden.'));
      }
      if (is_dir($to_dir)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Zielordner existiert bereits.'));
      }
      if (!@rename($from_dir, $to_dir)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ordner konnte nicht umbenannt werden.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $from_prefix = 'media/' . $from . '/';
      $to_prefix = 'media/' . $to . '/';
      $thumb_from = 'media/_thumbs/' . $from . '/';
      $thumb_to = 'media/_thumbs/' . $to . '/';
      $thumb_from_dir = $this->file_from_media_rel($thumb_from);
      $thumb_to_dir = $this->file_from_media_rel($thumb_to);
      if ($thumb_from_dir !== '' && is_dir($thumb_from_dir) && $thumb_to_dir !== '' && !is_dir($thumb_to_dir)) {
         @rename($thumb_from_dir, $thumb_to_dir);
      }

      $rows = $db->select($this->dd_media, "active = 1 AND (media_folder = '" . str_replace("'", "''", $from) . "' OR file_path LIKE '" . str_replace("'", "''", $from_prefix) . "%')", '*', 'id');
      if (!is_array($rows)) $rows = array();
      $updated = 0;
      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) continue;
         $path = ltrim(str_replace('\\', '/', (string)($row['file_path'] ?? '')), '/');
         $new_path = $path;
         if (strpos($path, $from_prefix) === 0) {
            $new_path = $to_prefix . substr($path, strlen($from_prefix));
         }
         $update = array(
            'media_folder' => $to,
            'file_path' => $new_path,
         );
         $thumb = ltrim(str_replace('\\', '/', (string)($row['thumb_file_path'] ?? '')), '/');
         if ($thumb !== '' && strpos($thumb, $thumb_from) === 0) {
            $update['thumb_file_path'] = $thumb_to . substr($thumb, strlen($thumb_from));
         }
         $db->update($this->dd_media, $update, $id);
         $updated++;
      }

      $this->cms_json_response(array(
         'ok' => 1,
         'success' => true,
         'from_folder' => $from,
         'to_folder' => $to,
         'updated' => $updated,
         'msg' => 'Ordner umbenannt.',
      ));
   }

   private function media_is_used($db, int $id): bool {
      if ($id <= 0) return true;
      $used = $db->select(
         $this->dd_media_usage,
         'media_id = ' . $id . ' AND active = 1',
         'id',
         'id',
         'ASC',
         '',
         1,
         0,
         0
      );
      if (is_array($used) && isset($used[0])) return true;

      foreach (dbxContentLngSync::accessibleLngs() as $lng) {
         $contentDd = dbxContentLng::ddContent((string)$lng);
         $folderDd = dbxContentLng::ddFolder((string)$lng);
         if ($db->count($contentDd, 'hero_image_id = ' . $id . ' OR seo_image_id = ' . $id) > 0 || $db->count($folderDd, 'hero_image_id = ' . $id) > 0) {
            return true;
         }
         $pages = $db->select($contentDd, '', 'content', 'id', 'ASC', '', 0, 0, 0);
         foreach (is_array($pages) ? $pages : array() as $page) {
            $content = (string)($page['content'] ?? '');
            if (preg_match('/data-cms-media-id=["\']?' . $id . '(?:["\'\s>]|$)/i', $content)
                || preg_match('/(?:dbx_mid|media_id)=' . $id . '(?:[^0-9]|$)/i', $content)) {
               return true;
            }
         }
      }
      return false;
   }

   private function move_media_record_to_folder($db, int $id, string $target_folder): array {
      if ($id <= 0) return array('ok' => 0, 'msg' => 'Keine Medien-ID erhalten.');
      $row = $db->select1($this->dd_media, $id);
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1) return array('ok' => 0, 'msg' => 'Medium nicht gefunden.');

      $media_type = $this->media_type($row);
      $folder = $this->clean_media_folder($target_folder, $media_type);
      if ($this->is_excluded_cms_media_folder($folder)) {
         return array('ok' => 0, 'msg' => 'Dieser Zielordner ist im CMS-Medienbrowser nicht verfuegbar.');
      }
      $old_file = $this->source_media_file($row);
      $name = basename((string)($row['file_name'] ?? $row['file_path'] ?? ''));
      if ($name === '') return array('ok' => 0, 'msg' => 'Dateiname fehlt.');
      $dir = $this->cms_media_dir($folder);
      if (!is_dir($dir)) @mkdir($dir, 0777, true);
      if (!is_dir($dir)) return array('ok' => 0, 'msg' => 'Zielordner konnte nicht angelegt werden.');
      $new_rel = $this->media_folder_rel_dir($folder, $media_type) . $name;
      $new_file = $this->file_from_media_rel($new_rel);
      if ($old_file !== '' && $new_file !== '' && $old_file !== $new_file) {
         if (is_file($new_file)) {
            $name = $this->unique_media_name($name);
            $new_rel = $this->media_folder_rel_dir($folder, $media_type) . $name;
            $new_file = $this->file_from_media_rel($new_rel);
         }
         if (!@rename($old_file, $new_file)) return array('ok' => 0, 'msg' => 'Medium konnte nicht verschoben werden.');
      }
      $old_thumb = $this->source_thumb_file($row);
      $mime = (string)($row['mime'] ?? '');
      $thumb = $this->create_media_thumbnail($new_file !== '' ? $new_file : $old_file, $folder, $name, $mime);
      if ($old_thumb !== '' && is_file($old_thumb) && $old_thumb !== $this->file_from_media_rel((string)($thumb['thumb_file_path'] ?? ''))) {
         @unlink($old_thumb);
      }
      $update = array('active' => 1, 'media_folder' => $folder, 'file_name' => $name, 'file_path' => $new_rel);
      if ($thumb) $update = array_merge($update, $thumb);
      $ok = $db->update($this->dd_media, $update, $id, 0, 1, 1, 0);
      if ($ok < 0) return array('ok' => 0, 'msg' => 'Mediendaten konnten nicht aktualisiert werden.');
      $row = $this->normalize_media_row($db->select1($this->dd_media, $id));
      if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || (string)($row['media_folder'] ?? '') !== $folder) {
         return array('ok' => 0, 'msg' => 'Mediendaten wurden nicht konsistent gespeichert.');
      }
      return array('ok' => 1, 'success' => true, 'row' => $row, 'msg' => 'Medium verschoben.');
   }

   private function media_move_json() {
      $payload = $this->request_json();
      $id = (int)($payload['media_id'] ?? $payload['id'] ?? 0);
      if ($id <= 0) $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Keine Medien-ID erhalten.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $result = $this->move_media_record_to_folder($db, $id, (string)($payload['media_folder'] ?? $payload['folder'] ?? ''));
      $this->cms_json_response($result);
   }

   private function media_unused_json() {
      $payload = $this->request_json();
      $action = strtolower(trim((string)($payload['action'] ?? '')));
      if (!in_array($action, array('delete', 'move'), true)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Ungueltige Aktion.'));
      }

      $source_raw = trim((string)($payload['source_folder'] ?? $payload['media_folder'] ?? 'all'));
      $source = strtolower($source_raw) === 'all' || $source_raw === ''
         ? 'all'
         : $this->clean_media_folder($source_raw, $this->media_type_from_folder($source_raw, 'image'));
      if ($source !== 'all' && $this->is_excluded_cms_media_folder($source)) {
         $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Dieser Quellordner ist im CMS-Medienbrowser nicht verfuegbar.'));
      }

      $target = '';
      if ($action === 'move') {
         $target_raw = trim((string)($payload['target_folder'] ?? $payload['to_folder'] ?? ''));
         if ($target_raw === '') {
            $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Bitte einen Zielordner waehlen.'));
         }
         $target = $this->clean_media_folder($target_raw, 'image');
         if ($target === '' || $this->is_excluded_cms_media_folder($target)) {
            $this->cms_json_response(array('ok' => 0, 'success' => false, 'msg' => 'Bitte einen gueltigen Zielordner waehlen.'));
         }
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $this->sync_cms_media_files($db);

      $where = "active = 1"
         . $this->cms_media_exclude_sql()
         . " AND (mime LIKE 'image/%' OR file_name LIKE '%.jpg' OR file_name LIKE '%.jpeg' OR file_name LIKE '%.png' OR file_name LIKE '%.gif' OR file_name LIKE '%.webp' OR file_name LIKE '%.svg')";
      if ($source !== 'all') {
         $where .= " AND media_folder = '" . str_replace("'", "''", $source) . "'";
      }
      if ($action === 'move') {
         $where .= " AND media_folder <> '" . str_replace("'", "''", $target) . "'";
      }

      $rows = $db->select($this->dd_media, $where, '*', 'media_folder,title,id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) $rows = array();
      $rows = $this->filter_existing_media($db, $rows);

      $checked = 0;
      $affected = 0;
      $skipped_used = 0;
      $errors = array();
      $moved_rows = array();

      foreach ($rows as $row) {
         if (!is_array($row)) continue;
         $id = (int)($row['id'] ?? 0);
         if ($id <= 0) continue;
         $checked++;
         if ($this->media_is_used($db, $id)) {
            $skipped_used++;
            continue;
         }
         if ($action === 'delete') {
            $result = $this->delete_media_record($id);
            if ((int)($result['ok'] ?? 0) === 1) {
               $affected++;
            } else {
               $errors[] = '#' . $id . ': ' . implode(' ', (array)($result['errors'] ?? array('Loeschen fehlgeschlagen.')));
            }
            continue;
         }
         $result = $this->move_media_record_to_folder($db, $id, $target);
         if ((int)($result['ok'] ?? 0) === 1) {
            $affected++;
            if (!empty($result['row']) && is_array($result['row'])) $moved_rows[] = $result['row'];
         } else {
            $errors[] = '#' . $id . ': ' . (string)($result['msg'] ?? 'Verschieben fehlgeschlagen.');
         }
      }

      $label = $action === 'delete' ? 'geloescht' : 'verschoben';
      $msg = $affected . ' unbenutzte Bilder ' . $label . '.';
      if ($skipped_used > 0) $msg .= ' ' . $skipped_used . ' verwendete Bilder uebersprungen.';
      if (!empty($errors)) $msg .= ' ' . count($errors) . ' Fehler.';

      $this->cms_json_response(array(
         'ok' => empty($errors) ? 1 : ($affected > 0 ? 1 : 0),
         'success' => empty($errors) || $affected > 0,
         'action' => $action,
         'source_folder' => $source,
         'target_folder' => $target,
         'checked' => $checked,
         'affected' => $affected,
         'skipped_used' => $skipped_used,
         'errors' => $errors,
         'rows' => $moved_rows,
         'msg' => $msg,
      ));
   }

   private function edit_image_file($file, $mime, array $op) {
      if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) return false;
      $img = $this->load_gd_image($file, $mime);
      if (!$img) return false;

      $src_w = imagesx($img);
      $src_h = imagesy($img);
      if ($src_w <= 0 || $src_h <= 0) {
         imagedestroy($img);
         return false;
      }

      $action = strtolower((string)($op['action'] ?? 'resize'));
      if ($action === 'crop') {
         $x = max(0, min($src_w - 1, (int)($op['x'] ?? 0)));
         $y = max(0, min($src_h - 1, (int)($op['y'] ?? 0)));
         $w = max(1, min($src_w - $x, (int)($op['width'] ?? $src_w)));
         $h = max(1, min($src_h - $y, (int)($op['height'] ?? $src_h)));
         $dst = imagecreatetruecolor($w, $h);
         imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
         imagecopyresampled($dst, $img, 0, 0, $x, $y, $w, $h, $w, $h);
      } else {
         $w = (int)($op['width'] ?? 0);
         $h = (int)($op['height'] ?? 0);
         $ratio = !empty($op['ratio']);
         if ($w <= 0 && $h <= 0) {
            imagedestroy($img);
            return false;
         }
         if ($w <= 0) $w = max(1, (int)round($src_w * ($h / $src_h)));
         if ($h <= 0) $h = max(1, (int)round($src_h * ($w / $src_w)));
         if ($ratio && $w > 0 && $h > 0) {
            $scale = min($w / $src_w, $h / $src_h);
            $w = max(1, (int)round($src_w * $scale));
            $h = max(1, (int)round($src_h * $scale));
         }
         $dst = imagecreatetruecolor($w, $h);
         imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
         imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, $src_w, $src_h);
      }

      $ok = $this->save_gd_image($dst, $file, $mime);
      imagedestroy($dst);
      imagedestroy($img);
      return $ok;
   }

   private function edit_media_json() {
      try {
      $payload = $this->request_json();
      $ids = array();
      if (isset($payload['ids']) && is_array($payload['ids'])) {
         foreach ($payload['ids'] as $id) {
            $id = (int)$id;
            if ($id > 0) $ids[$id] = $id;
         }
      } else {
         $id = (int)($payload['id'] ?? 0);
         if ($id > 0) $ids[$id] = $id;
      }
      if (!$ids) $this->cms_json_response(array('ok' => 0, 'msg' => 'Keine Medien ausgewaehlt.'));

      $action = strtolower((string)($payload['action'] ?? 'resize'));
      if (!in_array($action, array('resize', 'crop'), true)) $action = 'resize';
      if ($action === 'crop' && count($ids) > 1) $this->cms_json_response(array('ok' => 0, 'msg' => 'Zuschneiden ist nur fuer ein Bild moeglich.'));

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $done = array();
      foreach ($ids as $id) {
         $row = $db->select1($this->dd_media, $id);
         if (!is_array($row) || (int)($row['active'] ?? 0) !== 1 || $this->media_type($row) !== 'image') continue;
         if (preg_match('/\.svg$/i', (string)($row['file_name'] ?? ''))) continue;
         $file = $this->source_media_file($row);
         if ($file === '') continue;
         $mime = (string)($row['mime'] ?? '');
         if (!$this->edit_image_file($file, $mime, array(
            'action' => $action,
            'x' => (int)($payload['x'] ?? 0),
            'y' => (int)($payload['y'] ?? 0),
            'width' => (int)($payload['width'] ?? 0),
            'height' => (int)($payload['height'] ?? 0),
            'ratio' => !empty($payload['ratio']),
         ))) continue;

         $old_thumb = $this->source_thumb_file($row);
         if ($old_thumb !== '') @unlink($old_thumb);
         $size = @getimagesize($file);
         $update = array(
            'size' => (int)@filesize($file),
            'width' => is_array($size) ? (int)$size[0] : 0,
            'height' => is_array($size) ? (int)$size[1] : 0,
            'media_type' => 'image',
            'thumb_file_path' => '',
            'thumb_width' => 0,
            'thumb_height' => 0,
         );
         $media_folder = $this->canonical_media_folder(trim((string)($row['media_folder'] ?? '')) ?: $this->media_folder_from_path((string)($row['file_path'] ?? ''), $this->media_type($row)));
         $thumb = $this->create_media_thumbnail($file, $media_folder, (string)($row['file_name'] ?? basename($file)), $mime);
         if ($thumb) $update = array_merge($update, $thumb);
         $db->update($this->dd_media, $update, $id);
         $done[] = $this->normalize_media_row($db->select1($this->dd_media, $id));
      }

      if (!$done) $this->cms_json_response(array('ok' => 0, 'msg' => 'Keine bearbeitbaren Bilder gefunden.'));
      foreach ($ids as $id) {
         $this->flush_media_by_media_id($db, (int)$id);
      }
      $this->cms_json_response(array('ok' => 1, 'success' => true, 'rows' => $done));
      } catch (\Throwable $e) {
         $this->cms_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => 'Medienbearbeitung fehlgeschlagen: ' . $e->getMessage()
         ));
      }
   }

   /**
    * Liefert die gemeinsam verwendeten c-*-Layoutnamen.
    *
    * Contentdaten sind sprachabhängig, die Layoutstruktur dagegen nicht.
    * Deshalb wird bewusst das physische Basisverzeichnis gelesen und weder
    * dbx_lng_resolve_file() noch eine sprachabhängige Templateauflösung
    * verwendet. Deutsch, Englisch und Spanisch erhalten exakt dieselbe Liste.
    *
    * @return array<int,string>
    */
   private function content_template_names(): array {
      if (is_array($this->contentTemplateNames)) {
         return $this->contentTemplateNames;
      }

      $dir = dbx()->os_path(
         dbx()->get_base_dir() . 'dbx/modules/dbxContent/tpl/htm/'
      );
      $files = is_dir($dir) ? glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'c-*.htm') : array();
      $names = array();

      if (is_array($files)) {
         foreach ($files as $file) {
            if (!is_file($file)) continue;
            $names[] = basename($file, '.htm');
         }
      }

      $names = array_values(array_unique($names));
      sort($names, SORT_NATURAL | SORT_FLAG_CASE);

      $this->contentTemplateNames = $names ?: array('c-content');
      return $this->contentTemplateNames;
   }

   private function template_options() {
      $html = '';
      foreach ($this->content_template_names() as $name) {
         $html .= '<option value="' . dbx()->esc($name) . '">' . dbx()->esc($name) . '</option>';
      }

      return $html;
   }

   private function rights_options() {
      $values = $this->rights_values();
      $html = '';
      foreach ($values as $value => $label) {
         $html .= '<option value="' . dbx()->esc($value) . '">' . dbx()->esc($label) . '</option>';
      }
      return $html;
   }

   private function rights_values() {
      $texts = $this->cms_texts();
      $values = array(
         'parent' => $texts->get_fd_message('option_parent'),
         '*' => '*',
      );
      $db = dbx()->get_system_obj('dbxDB');
      $rows = $db->select('dbxUser_groups', '', '*', 'name');
      if (is_array($rows)) {
         foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') $values[$name] = $name;
         }
      }
      return $values;
   }

   private function media_template_options() {
      $values = array(
         'image-hero' => 'Bild Hero',
         'image-gallery' => 'Bild Galerie',
         'image-teaser' => 'Bild Header',
         'video-hero' => 'Video Hero',
         'video-gallery' => 'Video Galerie',
         'file-gallery' => 'Datei Download',
      );
      $html = '';
      foreach ($values as $value => $label) {
         $html .= '<option value="' . dbx()->esc($value) . '">' . dbx()->esc($label) . '</option>';
      }
      return $html;
   }

   private function options_html(array $values, $selected = '') {
      $html = '';
      foreach ($values as $value => $label) {
         $sel = ((string)$value === (string)$selected) ? ' selected' : '';
         $html .= '<option value="' . dbx()->esc($value) . '"' . $sel . '>' . dbx()->esc($label) . '</option>';
      }
      return $html;
   }

   private function content_template_options($with_parent = true) {
      $html = $with_parent ? '<option value="parent">parent - vom Parent</option>' : '';
      $html .= $this->template_options();
      return $html;
   }

   private function hero_template_values() {
      $texts = $this->cms_texts();
      return array(
         'parent' => $texts->get_fd_message('option_parent'),
         'none' => $texts->get_fd_message('option_no_hero'),
         'image-hero' => 'Bild Hero',
         'video-hero' => 'Video Hero',
      );
   }

   private function hero_template_options() {
      return $this->options_html($this->hero_template_values());
   }

   private function hero_variant_values() {
      $texts = $this->cms_texts();
      return array(
         'parent' => $texts->get_fd_message('option_parent'),
         'original' => 'Original',
         'yellow' => 'gelblich',
         'green' => 'gruenlich',
         'blue' => 'blaeulich',
         'red' => 'roetlich',
         'light' => 'hell',
         'dark' => 'dunkel',
         'blackwhite' => 'schwarz/weiss',
         'monochrome' => 'monochrom',
      );
   }

   private function hero_variant_options() {
      return $this->options_html($this->hero_variant_values());
   }

   private function hero_sticky_values() {
      $texts = $this->cms_texts();
      return array(
         'parent' => $texts->get_fd_message('option_parent'),
         '0' => $texts->get_fd_message('option_not_sticky'),
         '1' => $texts->get_fd_message('option_sticky'),
      );
   }

   private function hero_scroll_layer_values() {
      $texts = $this->cms_texts();
      return array(
         'parent' => $texts->get_fd_message('option_parent'),
         'under' => $texts->get_fd_message('option_scroll_under'),
         'over' => $texts->get_fd_message('option_scroll_over'),
      );
   }

   private function gallery_template_values() {
      return array(
         'image-gallery' => 'Bildergalerie',
         'video-gallery' => 'Video Galerie',
         'carousel3' => 'Carousel 3',
         'cols3' => '3 Spalten',
      );
   }

   private function gallery_template_options() {
      return $this->options_html($this->gallery_template_values());
   }

   private function gallery_image_size_values() {
      return array(
         '800x600' => '4:3 - Standard (800 x 600)',
         '1200x900' => '4:3 - gross (1200 x 900)',
         '1024x768' => '4:3 - klassisch (1024 x 768)',
         '1600x1200' => '4:3 - hochaufloesend (1600 x 1200)',
         '1280x720' => '16:9 - breit (1280 x 720)',
         '1920x1080' => '16:9 - Full HD (1920 x 1080)',
         '1200x675' => '16:9 - Web (1200 x 675)',
         '1080x1080' => '1:1 - Quadrat (1080 x 1080)',
         '1200x1200' => '1:1 - Quadrat gross (1200 x 1200)',
         '1080x1350' => '4:5 - Portrait (1080 x 1350)',
         '900x1200' => '3:4 - Portrait (900 x 1200)',
         '1600x900' => '16:9 - gross (1600 x 900)',
         '2560x1440' => '16:9 - QHD (2560 x 1440)',
      );
   }

   private function gallery_lightbox_width_values() {
      return array(
         '60%' => '60% - kompakt',
         '70%' => '70% - mittel',
         '80%' => '80% - Standard',
         '90%' => '90% - breit',
         '95%' => '95% - sehr breit',
         '70vw' => '70vw - Viewport mittel',
         '80vw' => '80vw - Viewport Standard',
         '90vw' => '90vw - Viewport breit',
         '960px' => '960px - kleine Desktopbreite',
         '1200px' => '1200px - Desktop',
         '1440px' => '1440px - grosser Desktop',
      );
   }

   private function gallery_overflow_values() {
      return array(
         'grid' => 'Grid',
         'scroll' => 'scroll',
         'laufband' => 'laufband',
         'slider' => 'slider',
         'tutorial' => 'Tutorial Slideshow',
      );
   }

   private function gallery_overflow_options() {
      return $this->options_html($this->gallery_overflow_values());
   }

   private function gallery_click_values() {
      return array(
         'lightbox' => 'Lightbox',
         'swiper-coverflow' => 'Swiper Coverflow',
         'swiper-cube' => 'Swiper Cube',
         'swiper-cards' => 'Swiper Cards',
         'swiper-3d' => 'Swiper 3D-Slider',
         'viewerjs' => 'Viewer.js',
         'blueimp' => 'blueimp Gallery',
         'photoswipe' => 'PhotoSwipe',
         'deepzoom' => 'Deep-Zoom-Viewer',
         'link' => 'Link',
         'newtab' => 'Neues Fenster',
         'none' => 'Kein Klick',
      );
   }

   private function gallery_click_options() {
      return $this->options_html($this->gallery_click_values());
   }

   private function meta_robots_values() {
      return array(
         'index,follow' => 'index, follow (Standard)',
         'noindex,follow' => 'noindex, follow',
         'index,nofollow' => 'index, nofollow',
         'noindex,nofollow' => 'noindex, nofollow',
      );
   }

   private function render_page_form() {
      $texts = $this->cms_texts();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('cms-page', 'cms-admin-page-form');
      $form->_dd = dbxContentLng::ddContent();
      $form->_fd = 'dbxContent_admin|cms-page';
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $form->_data = array(
         'activ' => 1,
         'template' => 'parent',
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
         'keywords' => '',
         'meta_robots' => 'index,follow',
         'seo_title' => '',
         'seo_image_id' => 0,
         'menu_title' => '',
      );
      $form->add_fld('id', 'dbxContent_admin|cms-field-hidden', data: array('cms_field' => 'id'));
      $form->add_fld('folder', 'dbxContent_admin|cms-field-hidden', data: array('cms_field' => 'folder'));
      $form->add_fld('title', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_title'), rules: 'varchar|min=1', data: array('cms_field' => 'title'));
      $form->add_fld('menu_title', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_menu_title'), rules: 'varchar|max=96', data: array('cms_field' => 'menu_title'), placeholder: $texts->get_fd_message('placeholder_menu_title'));
      $form->add_fld('permalink', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_permalink'), rules: 'permalink|max=254', data: array('cms_field' => 'permalink'), tooltip: $texts->get_fd_message('tooltip_permalink'));
      $form->add_fld('activ', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_status'), rules: 'int', data: array('cms_field' => 'activ'), options: array('1' => $texts->get_fd_message('option_active'), '0' => $texts->get_fd_message('option_inactive')));
      $form->add_fld('template', 'dbxContent_admin|cms-field-content-template-select', label: $texts->get_fd_message('label_template'), rules: 'varchar', data: array('cms_field' => 'template'), options: $this->content_template_values());
      $form->add_fld('description', 'dbxContent_admin|cms-field-textarea', label: $texts->get_fd_message('label_description'), rules: 'varchar', data: array('cms_field' => 'description'), placeholder: $texts->get_fd_message('placeholder_description'));
      $form->add_fld('keywords', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_keywords'), rules: 'varchar', data: array('cms_field' => 'keywords'), placeholder: $texts->get_fd_message('placeholder_keywords'));
      $form->add_fld('content', 'dbxContent_admin|cms-field-textarea-hidden', label: $texts->get_fd_message('label_content'), rules: 'text', data: array('cms_field' => 'content'));
      return $form->run();
   }

   private function content_template_values() {
      $values = array('parent' => $this->cms_texts()->get_fd_message('option_parent'));
      foreach ($this->content_template_names() as $name) {
         $values[$name] = $name;
      }

      return $values;
   }

   private function render_folder_form() {
      $texts = $this->cms_texts();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('cms-folder', 'cms-admin-folder-form');
      $form->_dd = dbxContentLng::ddFolder();
      $form->_fd = 'dbxContent_admin|cms-page';
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $form->_data = array(
         'parent_id' => 0,
         'group_read' => 'parent',
         'template' => 'parent',
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
      );
      $form->add_fld('id', 'dbxContent_admin|cms-field-folder-hidden', data: array('cms_field' => 'id'));
      $form->add_fld('name', 'dbxContent_admin|cms-field-folder-text', label: $texts->get_fd_message('label_name'), rules: 'varchar|min=1', data: array('cms_field' => 'name'));
      $form->add_fld('parent_id', 'dbxContent_admin|cms-field-folder-select', label: $texts->get_fd_message('label_assignment'), rules: 'int', data: array('cms_field' => 'parent_id'), options: array('0' => $texts->get_fd_message('option_root')));
      $form->add_fld('template', 'dbxContent_admin|cms-field-folder-content-template-select', label: $texts->get_fd_message('label_template'), rules: 'varchar', data: array('cms_field' => 'template'), options: $this->content_template_values());
      $form->add_fld('group_read', 'dbxContent_admin|cms-field-folder-rights', label: $texts->get_fd_message('label_read_rights'), rules: 'varchar', data: array('cms_field' => 'group_read'), options: $this->rights_values());
      return $form->run();
   }

   private function render_settings_form() {
      $texts = $this->cms_texts();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('cms-settings', 'cms-admin-settings-panels');
      $form->_dd = dbxContentLng::ddContent();
      $form->_fd = 'dbxContent_admin|cms-page';
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $form->_data = array(
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
      );
      $form->add_fld('hero_template', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_hero_template'), rules: 'varchar', data: array('cms_field' => 'hero_template'), options: $this->hero_template_values());
      $form->add_fld('hero_image_id', 'dbxContent_admin|cms-field-hidden', rules: 'varchar', data: array('cms_field' => 'hero_image_id'));
      $form->add_fld('hero_margin_top', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_hero_margin_top'), rules: 'varchar', data: array('cms_field' => 'hero_margin_top'));
      $form->add_fld('hero_height', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_hero_height'), rules: 'varchar', data: array('cms_field' => 'hero_height'));
      $form->add_fld('hero_variant', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_hero_variant'), rules: 'varchar', data: array('cms_field' => 'hero_variant'), options: $this->hero_variant_values());
      $form->add_fld('hero_sticky', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_hero_sticky'), rules: 'varchar', data: array('cms_field' => 'hero_sticky'), options: $this->hero_sticky_values());
      $form->add_fld('hero_scroll_layer', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_hero_scroll_layer'), rules: 'varchar', data: array('cms_field' => 'hero_scroll_layer'), options: $this->hero_scroll_layer_values());
      $form->add_fld('gallery_image_size', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_gallery_image_size'), rules: 'varchar', data: array('cms_field' => 'gallery_image_size'), options: $this->gallery_image_size_values());
      $form->add_fld('gallery_lightbox_width', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_gallery_lightbox_width'), rules: 'varchar', data: array('cms_field' => 'gallery_lightbox_width'), options: $this->gallery_lightbox_width_values());
      $form->add_fld('gallery_overflow', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_gallery_overflow'), rules: 'varchar', data: array('cms_field' => 'gallery_overflow'), options: $this->gallery_overflow_values());
      $form->add_fld('gallery_click_behavior', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_gallery_click'), rules: 'varchar', data: array('cms_field' => 'gallery_click_behavior'), options: $this->gallery_click_values());
      return $form->run();
   }

   public function run($action = 'cms') {
      $this->apply_cms_lng_context();
      $action = (string)$action;
      if ($this->action_requires_token($action) && !$this->check_action_token($action)) {
         return $this->reject_action_token($action);
      }
      switch ($action) {
         case 'cms_tree':
            return $this->tree_json();
         case 'cms_lng_coverage':
            return $this->lng_coverage_json();
         case 'cms_lng_preview':
            return $this->lng_preview_json();
         case 'cms_lng_provision':
            return $this->lng_provision_json();
         case 'cms_lng_provision_tree':
            return $this->lng_provision_tree_json();
         case 'cms_lng_reset_sync':
            return $this->lng_reset_sync_json();
         case 'cms_lng_delete_preview':
            return $this->lng_delete_preview_json();
         case 'cms_page':
            return $this->page_json();
         case 'cms_save':
            return $this->save_json();
         case 'cms_new_page':
            return $this->new_page_json();
         case 'cms_duplicate_page':
            return $this->duplicate_page_json();
         case 'cms_new_folder':
            return $this->new_folder_json();
         case 'cms_save_folder':
            return $this->save_folder_json();
         case 'cms_delete_folder':
            return $this->delete_folder_json();
         case 'cms_delete_page':
            return $this->delete_page_json();
         case 'cms_move_node':
            return $this->move_node_json();
         case 'cms_media':
            return $this->media_json();
         case 'cms_media_process':
            return $this->media_process();
         case 'cms_upload':
            return $this->upload_json();
         case 'cms_external_video':
            return $this->external_video_json();
         case 'cms_media_folders':
            return $this->media_folders_json();
         case 'cms_media_folder_create':
            return $this->media_folder_create_json();
         case 'cms_media_folder_delete':
            return $this->media_folder_delete_json();
         case 'cms_media_folder_rename':
            return $this->media_folder_rename_json();
         case 'cms_media_move':
            return $this->media_move_json();
         case 'cms_media_unused':
            return $this->media_unused_json();
         case 'cms_remove_media':
            return $this->remove_media_json();
         case 'cms_delete_media':
            return $this->delete_media_json();
         case 'cms_edit_media':
            return $this->edit_media_json();
         case 'cms_set_media_slot':
            return $this->set_media_slot_json();
         case 'cms_assign_media':
            return $this->assign_media_json();
         case 'cms_sort_media':
            return $this->sort_media_json();
         case 'cms_mod_catalog':
            return $this->mod_catalog_json();
         case 'cms_mod_modules':
            return $this->mod_modules_json();
         case 'cms':
         default:
            return $this->render_cms();
      }
   }
}

?>
