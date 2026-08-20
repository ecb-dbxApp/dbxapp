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
 * CMS-Seitenrahmen, Modul-/Sprachendpunkte und kontrollierte Datensatzloeschung.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsPageShellServiceTrait {


   private function render_cms() {
      $o_tpl = dbx()->get_system_obj('dbxTPL');
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $texts = $this->cms_texts();
      // Der eigentliche Content-Baum wird ueber cms_tree erst auf Anforderung
      // geladen. Fuer die Kopfleiste reichen drei kleine COUNT-Abfragen.
      $page_count = (int)$db->count(dbxContentLng::dd_content(), '');
      $folder_count = (int)$db->count(dbxContentLng::dd_folder(), '');
      $active_count = $db->count(dbxContentLng::dd_content(), 'activ = 1');
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
          'tree_loading' => $texts->get_fd_message('tree_loading'),
          'new_folder_button_title' => $texts->get_fd_message('new_folder_button_title'),
          'new_page_button_title' => $texts->get_fd_message('new_page_button_title'),
          'media_filter_label' => $texts->get_fd_message('media_filter_label'),
          'media_filter_title' => $texts->get_fd_message('media_filter_title'),
          'media_filter_all' => $texts->get_fd_message('media_filter_all'),
          'media_filter_gallery' => $texts->get_fd_message('media_filter_gallery'),
          'media_filter_hero' => $texts->get_fd_message('media_filter_hero'),
          'media_filter_inline' => $texts->get_fd_message('media_filter_inline'),
          'media_filter_shop' => $texts->get_fd_message('media_filter_shop'),
          'media_not_loaded' => $texts->get_fd_message('media_not_loaded'),
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
         'master_lng' => dbxContentLngSync::master_lng(),
         'lng_bar' => dbxContentLngSync::render_lng_bar(),
         'template_options' => $this->cms_options()->template_options(),
         'rights_options' => $this->cms_options()->rights_options(),
         'media_template_options' => $this->cms_options()->media_template_options(),
         'hero_template_options' => $this->cms_options()->hero_template_options(),
         'hero_variant_options' => $this->cms_options()->hero_variant_options(),
         'gallery_template_options' => $this->cms_options()->gallery_template_options(),
         'gallery_overflow_options' => $this->cms_options()->gallery_overflow_options(),
         'gallery_click_options' => $this->cms_options()->gallery_click_options(),
         'page_form' => $this->render_page_form(),
         'folder_form' => $this->render_folder_form(),
         'settings_form' => $this->render_settings_form(),
         'media_browser_forms' => $this->media_forms()->render_templates(
            $this->base_url('cms_upload'),
            'cms-media-upload',
            $this->base_url('cms_external_video'),
            'cms-external-video'
         ),
         'dbx_search' => $o_tpl->get_tpl('dbx|search', dbx()->get_system_obj('dbxSearchDefaults')->build(array(
             'title' => $texts->get_fd_message('tree_search'),
            'extra_attrs' => 'data-cms-search',
            'data_role' => '',
         ))),
      );

      $data = array_merge(
         dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp')->vars('content'),
         $data
      );
      $data['bar_class'] = 'dbx-bar--module dbx-cms-head';
      $data['bar_title_class'] = 'dbx-bar-title';
      $data['bar_actions_class'] = 'dbx-bar-actions flex-wrap';
      if (trim((string)($data['bar_subtitle'] ?? '')) === '' && trim((string)($data['subtitle'] ?? '')) !== '') {
         $data['bar_subtitle'] = (string)$data['subtitle'];
      }

      return $o_tpl->get_tpl('dbxContent_admin|cms-admin', $data);
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
            $count = $images->image_count($name);
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

      $out = $images->catalog_for_modul($modul);
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
      $lng_uid = trim((string) dbx()->get_modul_var('lng_uid', ''));
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

       if ($lng_uid === '' && $id > 0) {
          $dd = $type === 'folder' ? dbxContentLng::dd_folder() : dbxContentLng::dd_content();
          $lng_uid = dbxContentLngSync::record_uid($db, $dd, $id);
       }

      $coverage = dbxContentLngSync::coverage_for_uid($db, $type, $lng_uid);
      $this->cms_json_response(array('ok' => 1, 'coverage' => $coverage));
   }



   private function lng_preview_json() {
      if (!dbxContentLngSync::is_master_lng()) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Vorschau nur in der Master-Sprache moeglich.'));
      }

      $payload = $this->request_json();
      $type = (($payload['type'] ?? 'page') === 'folder') ? 'folder' : 'page';
      $id = (int) ($payload['id'] ?? 0);
      $lngs = is_array($payload['lngs'] ?? null) ? $payload['lngs'] : array();
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      dbxContentTranslate::clear_warnings();
      $preview = dbxContentLngSync::preview_provision($db, $type, $id, $lngs);
      $this->cms_json_response(array(
         'ok' => 1,
         'preview' => $preview,
         'provider' => dbxContentTranslate::provider(),
         'translate_warnings' => dbxContentTranslate::warnings(),
      ));
   }



   private function lng_provision_json() {
      if (!dbxContentLngSync::is_master_lng()) {
         $this->cms_json_response(array('ok' => 0, 'msg' => 'Uebertragung nur in der Master-Sprache moeglich.'));
      }

      $payload = $this->request_json();
      $type = (($payload['type'] ?? 'page') === 'folder') ? 'folder' : 'page';
      $id = (int) ($payload['id'] ?? 0);
      $items = is_array($payload['items'] ?? null) ? $payload['items'] : array();
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      dbxContentTranslate::clear_warnings();
      $result = dbxContentLngSync::provision_from_preview($db, $type, $id, $items);
      if ((int) ($result['ok'] ?? 0) === 1) {
         $result['media_copied'] = $this->apply_lng_provision_media($db, $type, $id, $result);
         $this->flush_menu_cache();
      }
      $result['translate_warnings'] = dbxContentTranslate::warnings();
      $this->cms_json_response(array('ok' => (int) ($result['ok'] ?? 0), 'result' => $result));
   }



   private function lng_provision_tree_json() {
      if (!dbxContentLngSync::is_master_lng()) {
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
      dbxContentTranslate::clear_warnings();
      $result = dbxContentLngSync::provision_folder_tree($db, $id, $lngs);
      if ((int) ($result['ok'] ?? 0) === 1) {
         $media_copied = 0;
         foreach (is_array($result['pages'] ?? null) ? $result['pages'] : array() as $page_item) {
            if (!is_array($page_item)) {
               continue;
            }
            $master_page_id = (int) ($page_item['master_id'] ?? 0);
            $prov = is_array($page_item['result'] ?? null) ? $page_item['result'] : array();
            if ($master_page_id > 0) {
               $media_copied += $this->apply_lng_provision_media($db, 'page', $master_page_id, $prov);
            }
         }
         $result['media_copied'] = $media_copied;
         $this->flush_menu_cache();
      }
      $result['translate_warnings'] = dbxContentTranslate::warnings();
      $this->cms_json_response(array('ok' => (int) ($result['ok'] ?? 0), 'result' => $result));
   }



   private function lng_reset_sync_json() {
      if (!dbxContentLngSync::is_master_lng()) {
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
      $result = dbxContentLngSync::reset_sync_to_auto($db, $type, $id, $lngs);
      $this->cms_json_response(array('ok' => count($result['updated'] ?? array()) ? 1 : 0, 'result' => $result));
   }



   private function lng_delete_preview_json() {
      if (!dbxContentLngSync::is_master_lng()) {
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
      $preview = dbxContentLngSync::preview_delete($db, $type, $id);
      $this->cms_json_response(array('ok' => 1, 'preview' => $preview));
   }



   private function normalize_delete_lngs(array $payload): array {
      $current = dbxContentLng::current();
      $raw = $payload['delete_lngs'] ?? null;
      if (!is_array($raw) || !count($raw)) {
         return array($current);
      }
      if (!dbxContentLngSync::is_master_lng()) {
         return array($current);
      }

      $allowed = dbxContentLngSync::accessible_lngs();
      $out = array();
      foreach ($raw as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng !== '' && in_array($lng, $allowed, true) && !in_array($lng, $out, true)) {
            $out[] = $lng;
         }
      }

      return count($out) ? $out : array($current);
   }



   private function delete_page_in_lngs($db, int $id, array $delete_lngs): array {
      return $this->persistence($db)->delete_page($id, $delete_lngs);
   }



   public function delete_page_record(int $id): array {
      if ($id <= 0) {
         return array('ok' => 0, 'deleted' => array(), 'errors' => array('Keine Seite gewaehlt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $result = $this->delete_page_in_lngs($db, $id, array(dbxContentLng::current()));
      return $result;
   }



   public function delete_folder_record(int $id): array {
      if ($id <= 0) {
         return array('ok' => 0, 'deleted' => array(), 'errors' => array('Kein Ordner gewaehlt.'));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);
      $result = $this->delete_folder_in_lngs($db, $id, array(dbxContentLng::current()));
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

      foreach (dbxContentLngSync::accessible_lngs() as $lng) {
         $content_dd = dbxContentLng::dd_content((string)$lng);
         $folder_dd = dbxContentLng::dd_folder((string)$lng);
         if ($db->count($content_dd, 'hero_image_id = ' . $id) > 0 || $db->count($folder_dd, 'hero_image_id = ' . $id) > 0) {
            return array('ok' => 0, 'errors' => array('Medium wird noch verwendet.'));
         }
         $pages = $db->select($content_dd, '', 'content', 'id', 'ASC', '', 0, 0, 0);
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
}
