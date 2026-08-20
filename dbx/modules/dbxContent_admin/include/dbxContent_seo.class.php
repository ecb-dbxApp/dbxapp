<?php
namespace dbx\dbxContent_admin;

use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

class dbxContent_seo {

   private const ACTION_TOKEN_SCOPE = 'dbxContent_admin.actions';

   private const OG_SOURCE = __DIR__ . '/../files/og/dbxapp-og.png';
   private const OG_MEDIA_REL = 'media/dbxContent/seo/dbxapp-og.png';
   private const CONFIG_OG_MEDIA_ID = 'default_seo_image_id';

   private $dd_media = 'dbxMedia';
   private $seo_texts = null;

   /**
    * Liefert einen stabilen, sprachabhängigen Textkontext für die SEO-Seite.
    *
    * Das eigene dbxForm-Objekt bleibt unabhängig von den eingebetteten
    * Medienformularen, die ihren jeweils eigenen FD-Kontext laden.
    */
   private function seo_texts() {
      if ($this->seo_texts) {
         return $this->seo_texts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('seo-page-texts');
      $texts->set_field_definition('dbxContent_admin|seo-page');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->seo_texts = $texts;
      return $this->seo_texts;
   }

   private function seo_js_messages(): string {
      $texts = $this->seo_texts();
      $keys = array(
         'save_error', 'og_empty', 'og_loading', 'og_not_image',
         'load_start', 'load_error', 'no_page_selected', 'save_start',
         'seo_saved', 'no_media_images', 'picker_title',
         'media_api_missing', 'og_selected_pending', 'media_load_error',
         'og_removed_pending',
      );
      $messages = array();
      foreach ($keys as $key) {
         $messages[$key] = $texts->get_fd_message($key);
      }
      return json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
   }

   public function run($action = 'seo') {
      \dbx\dbxContent\dbxContentRuntime::apply_requested_language();
      $texts = $this->seo_texts();
      $action = (string)$action;
      if ($action === 'seo_save' && !$this->check_action_token($action)) {
         http_response_code(403);
         $this->seo_json_response(array(
            'ok' => 0,
            'success' => false,
            'msg' => $texts->get_fd_message('security_token_error'),
         ));
         return '';
      }

      switch ($action) {
         case 'seo_page':
            $this->page_json();
            return '';
         case 'seo_save':
            $this->save_json();
            return '';
         case 'seo':
         default:
            $this->ensure_default_og_image();
            return $this->render_admin();
      }
   }

   private function seo_json_response(array $data): void {
      dbx()->json_response($data, true);
   }

   private function base_url($action, $params = array()) {
      $url = '?dbx_modul=dbxContent_admin&dbx_run1=' . rawurlencode((string)$action);
      if (in_array((string)$action, array('seo_save', 'cms_media', 'cms_upload', 'cms_external_video'), true)) {
         $params['dbx_token'] = dbx()->action_token(self::ACTION_TOKEN_SCOPE);
      }
      return dbx()->append_url_params($url, $params);
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
         'SEO-Aktion ohne gueltigen Token abgewiesen',
         'ip=' . (string)($_SERVER['REMOTE_ADDR'] ?? '')
      );
      return false;
   }

   private function connect_db($db) {
      if (!is_object($db)) {
         return;
      }
      $db->connect_db_server('dbx|dbxContent.db3');
      $db->connect_db_server('dbx|dbxMedia.db3');
   }

   private function request_json() {
      return dbx()->get_json_request(true);
   }

   private function meta_robots_values() {
      $texts = $this->seo_texts();
      return array(
         'index,follow' => $texts->get_fd_message('robots_default'),
         'noindex,follow' => 'noindex, follow',
         'index,nofollow' => 'index, nofollow',
         'noindex,nofollow' => 'noindex, nofollow',
      );
   }

   private function render_admin() {
      $texts = $this->seo_texts();
      $db = dbx()->get_system_obj('dbxDB');
      $this->ensure_cms_schema($db);

      $pages = $db->select(
         dbxContentLng::dd_content(),
         '',
         'id,title,permalink,activ',
         'title',
         'ASC',
         '',
         0,
         0,
         0
      );
      if (!is_array($pages)) {
         $pages = array();
      }

      $cid = (int)dbx()->get_modul_var('cid', 0, 'int');
      if ($cid <= 0 && !empty($pages[0]['id'])) {
         $cid = (int)$pages[0]['id'];
      }

      $options = '';
      foreach ($pages as $page) {
         if (!is_array($page)) continue;
         $id = (int)($page['id'] ?? 0);
         if ($id <= 0) continue;
         $label = trim((string)($page['title'] ?? ''));
         if ($label === '') {
            $label = $texts->format_fd_message('page_fallback', array('id' => $id));
         }
         $permalink = trim((string)($page['permalink'] ?? ''));
         if ($permalink !== '') $label .= ' (' . $permalink . ')';
         $selected = ($id === $cid) ? ' selected' : '';
         $options .= '<option value="' . $id . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
      }

      $form = $this->render_seo_form();
      $content = dbx()->get_system_obj('dbxTPL')->get_tpl('dbxContent_admin|seo-admin', array(
         'page_options' => $options,
         'cid' => $cid,
         'seo_form' => $form,
         'seo_messages' => dbx()->esc($this->seo_js_messages()),
         'page_label' => $texts->get_fd_message('page_label'),
         'page_description_hint' => $texts->get_fd_message('page_description_hint'),
         'save_button' => $texts->get_fd_message('save_button'),
         'label_og_image' => $texts->get_fd_message('label_og_image'),
         'og_empty' => $texts->get_fd_message('og_empty'),
         'og_select_title' => $texts->get_fd_message('og_select_title'),
         'og_select' => $texts->get_fd_message('og_select'),
         'og_remove_title' => $texts->get_fd_message('og_remove_title'),
         'og_remove' => $texts->get_fd_message('og_remove'),
         'page_url' => dbx()->esc($this->base_url('seo_page')),
         'save_url' => dbx()->esc($this->base_url('seo_save')),
         'media_url' => dbx()->esc($this->base_url('cms_media')),
         'media_folders_url' => dbx()->esc($this->base_url('cms_media_folders')),
         'upload_url' => dbx()->esc($this->base_url('cms_upload')),
         'external_video_url' => dbx()->esc($this->base_url('cms_external_video')),
         'upload_max_bytes' => (string)(16 * 1024 * 1024),
         'media_browser_forms' => dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->render_templates(
            $this->base_url('cms_upload'),
            'cms-media-upload',
            $this->base_url('cms_external_video'),
            'cms-external-video'
         ),
      ));

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxContent_admin|content-admin-section', array(
         'title' => $texts->get_fd_message('bar_title'),
         'subtitle' => $texts->get_fd_message('bar_subtitle'),
         'content' => $content,
         'bar_actions' => '<a class="btn btn-outline-primary btn-sm" href="?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=edit"><i class="bi bi-pencil-square"></i><span>' . $texts->get_fd_message('cms_action') . '</span></a>',
      ));
   }

   private function render_seo_form() {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('seo-page', 'seo-admin-form');
      $form->set_field_definition('dbxContent_admin|seo-page');
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $form->set_data_definition(dbxContentLng::dd_content());
      $form->set_data(array(
         'id' => 0,
         'keywords' => '',
         'meta_robots' => 'index,follow',
         'seo_title' => '',
         'seo_image_id' => 0,
      ));
      $texts = $this->seo_texts();
      $form->add_fld('id', 'dbxContent_admin|cms-field-hidden', data: array('cms_field' => 'id', 'cms_field_scope' => 'seo', 'seo_field' => 'id'));
      $form->add_fld('keywords', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_keywords'), rules: 'varchar', data: array('cms_field' => 'keywords', 'cms_field_scope' => 'seo', 'seo_field' => 'keywords'), placeholder: $texts->get_fd_message('placeholder_keywords'));
      $form->add_fld('meta_robots', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_robots'), rules: 'varchar', data: array('cms_field' => 'meta_robots', 'cms_field_scope' => 'seo', 'seo_field' => 'meta_robots'), options: $this->meta_robots_values());
      $form->add_fld('seo_title', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_seo_title'), rules: 'varchar', data: array('cms_field' => 'seo_title', 'cms_field_scope' => 'seo', 'seo_field' => 'seo_title'), placeholder: $texts->get_fd_message('placeholder_seo_title'));
      $form->add_fld('seo_image_id', 'dbxContent_admin|cms-field-hidden', rules: 'int', data: array('cms_field' => 'seo_image_id', 'cms_field_scope' => 'seo', 'seo_field' => 'seo_image_id'));
      return $form->run();
   }

   private function page_json() {
      $cid = (int)dbx()->get_modul_var('id', 0, 'int');
      if ($cid <= 0) {
         $cid = (int)dbx()->get_modul_var('cid', 0, 'int');
      }
      if ($cid <= 0) {
         $this->seo_json_response(array('ok' => 0, 'error' => $this->seo_texts()->get_fd_message('page_id_missing')));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->connect_db($db);
      $this->ensure_cms_schema($db);
      $row = $db->select1(dbxContentLng::dd_content(), $cid, 'id,title,permalink,keywords,meta_robots,seo_title,seo_image_id,description', 0);
      if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
         $this->seo_json_response(array('ok' => 0, 'error' => $this->seo_texts()->get_fd_message('page_not_found')));
      }

      $this->seo_json_response(array(
         'ok' => 1,
         'row' => $row,
         'seo_preview_media' => $this->seo_preview_media($db, $row),
      ));
   }

   private function save_json() {
      $payload = $this->request_json();
      $id = (int)($payload['id'] ?? 0);
      if ($id <= 0) {
         $this->seo_json_response(array('ok' => 0, 'error' => $this->seo_texts()->get_fd_message('page_id_missing')));
      }

      $db = dbx()->get_system_obj('dbxDB');
      $this->connect_db($db);
      $this->ensure_cms_schema($db);

      $data = array(
         'keywords' => $this->clean_text($payload['keywords'] ?? '', 254),
         'meta_robots' => $this->clean_text($payload['meta_robots'] ?? 'index,follow', 32),
         'seo_title' => $this->clean_text($payload['seo_title'] ?? '', 254),
         'seo_image_id' => max(0, (int)($payload['seo_image_id'] ?? 0)),
      );

      if ($db->update(dbxContentLng::dd_content(), $data, $id) !== 1) {
         $this->seo_json_response(array('ok' => 0, 'error' => $this->seo_texts()->get_fd_message('save_error')));
      }

      dbxContentLngSync::after_page_save($db, $id, false);
      dbxContentPageCache::invalidate_content($id);
      dbxContentPageCache::invalidate_all_menus();
      $this->sync_page_meta($db, $id);

      $row = $db->select1(dbxContentLng::dd_content(), $id, 'id,title,permalink,keywords,meta_robots,seo_title,seo_image_id,description', 0);
      $this->seo_json_response(array(
         'ok' => 1,
         'row' => is_array($row) ? $row : array(),
         'seo_preview_media' => $this->seo_preview_media($db, is_array($row) ? $row : array()),
      ));
   }

   private function sync_page_meta($db, int $cid) {
      $cid = (int)$cid;
      if ($cid <= 0) {
         return;
      }

      $rec = $db->select1(dbxContentLng::dd_content(), $cid, 'permalink,activ,folder,title,seo_title,description,keywords,meta_robots,seo_image_id,update_date,lng_uid', 0);
      if (!is_array($rec)) {
         return;
      }

      $renderer = new dbxContentRenderer();
      $rights = $renderer->get_public_folder_rights((int)($rec['folder'] ?? 0));
      dbxContentPageCache::write_page_meta($cid, array(
         'cid' => $cid,
         'permalink' => (string)($rec['permalink'] ?? ''),
         'rights' => $rights,
         'activ' => (int)($rec['activ'] ?? 1),
         'saved_at' => date('c'),
         'seo' => dbxContentRenderer::seo_meta_from_record($rec),
      ));

      $permalink = trim((string)($rec['permalink'] ?? ''));
      if ($permalink !== '' && (int)($rec['activ'] ?? 0) === 1) {
         dbxContentPermalinkIndex::upsert_page($cid, $permalink, $rights, 1, dbxContentLng::current());
      }

      if (class_exists('dbx\\dbxContent\\dbxContentSitemap')) {
         \dbx\dbxContent\dbxContentSitemap::invalidate();
      }
   }

   private function seo_preview_media($db, $row) {
      if (!is_array($row)) return array();
      $seo_id = (int)($row['seo_image_id'] ?? 0);
      if ($seo_id <= 0) return array();
      $media = $db->select1($this->dd_media, $seo_id);
      if (!is_array($media) || (int)($media['id'] ?? 0) <= 0) return array();
      if ((int)($media['active'] ?? 0) !== 1) return array();
      return $this->media_row_json($media);
   }

   private function media_row_json(array $media) {
      $id = (int)($media['id'] ?? 0);
      $url = $id > 0 ? 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $id : '';
      $thumb = $url;
      if ($id > 0 && !empty($media['thumb_file_path'])) {
         $thumb .= '&dbx_thumb=1';
      }
      return array(
         'id' => $id,
         'title' => (string)($media['title'] ?? ''),
         'mime' => (string)($media['mime'] ?? ''),
         'media_type' => (string)($media['media_type'] ?? ''),
         'url' => $url,
         'thumb_url' => $thumb,
         'preview_url' => $thumb,
      );
   }

   private function clean_text($value, int $max = 0) {
      return \dbx\dbxContent\dbxContentRuntime::clean_text($value, $max);
   }

   private function ensure_cms_schema($db) {
      $cms = dbx()->get_include_obj('dbxContent_cms');
      if (is_object($cms) && method_exists($cms, 'ensure_schema')) {
         $cms->ensure_schema($db);
      }
   }

   public function ensure_default_og_image() {
      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db)) return 0;

      $db->connect_db_server('dbx|dbxContent.db3');
      $db->connect_db_server('dbx|dbxMedia.db3');
      $this->ensure_cms_schema($db);
      $media_id = (int)dbx()->get_cfg('dbxContent', self::CONFIG_OG_MEDIA_ID);
      if ($media_id > 0) {
         $exists = $db->select1($this->dd_media, $media_id, 'id,active', 0);
         if (is_array($exists) && (int)($exists['id'] ?? 0) > 0 && (int)($exists['active'] ?? 0) === 1) {
            $this->assign_home_og_image($db, $media_id);
            return $media_id;
         }
      }

      if (!is_file(self::OG_SOURCE)) {
         return 0;
      }

      $target_dir = rtrim(dbx()->get_file_dir(), '/\\') . '/media/dbxContent/seo/';
      $target_dir = dbx()->os_path($target_dir);
      if (!is_dir($target_dir)) {
         @mkdir($target_dir, 0775, true);
      }

      $target_file = $target_dir . 'dbxapp-og.png';
      if (!is_file($target_file) || filemtime(self::OG_SOURCE) > filemtime($target_file)) {
         @copy(self::OG_SOURCE, $target_file);
      }
      if (!is_file($target_file)) {
         return 0;
      }

      $rel = self::OG_MEDIA_REL;
      $existing = $db->select1($this->dd_media, array('file_path' => $rel), 'id,active', 0);
      if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
         $media_id = (int)$existing['id'];
         $db->update($this->dd_media, array('active' => 1, 'title' => 'dbXapp OG', 'alt' => 'dbXapp'), $media_id);
      } else {
         $size = (int)@filesize($target_file);
         $info = @getimagesize($target_file);
         $insert = array(
            'active' => 1,
            'content_id' => 0,
            'folder_id' => 0,
            'slot' => 'seo',
            'usage' => 'seo',
            'sorter' => 1,
            'template' => '',
            'title' => 'dbXapp OG',
            'alt' => 'dbXapp',
            'file_name' => 'dbxapp-og.png',
            'file_path' => $rel,
            'mime' => 'image/png',
            'size' => $size,
            'width' => is_array($info) ? (int)($info[0] ?? 0) : 0,
            'height' => is_array($info) ? (int)($info[1] ?? 0) : 0,
            'media_type' => 'image',
            'storage_type' => 'local',
            'media_folder' => 'dbxContent/seo',
         );
         if ($db->insert($this->dd_media, $insert) !== 1) {
            return 0;
         }
         $media_id = (int)$db->get_insert_id();
      }

      if ($media_id <= 0) {
         return 0;
      }

      $config = dbx()->get_cfg('dbxContent');
      if (!is_array($config)) $config = array();
      $config[self::CONFIG_OG_MEDIA_ID] = $media_id;
      dbx()->set_cfg('dbxContent', $config);

      $this->assign_home_og_image($db, $media_id);
      return $media_id;
   }

   private function assign_home_og_image($db, int $media_id) {
      $home_cid = dbxContentHome::resolve_cid();
      if ($home_cid <= 0) {
         $home_cid = dbxContentHome::master_cid();
      }
      if ($home_cid <= 0) {
         return;
      }

      $row = $db->select1(dbxContentLng::dd_content(), $home_cid, 'seo_image_id', 0);
      if (!is_array($row)) {
         return;
      }
      if ((int)($row['seo_image_id'] ?? 0) > 0) {
         return;
      }

      $db->update(dbxContentLng::dd_content(), array('seo_image_id' => $media_id), $home_cid);
      dbxContentPageCache::invalidate_content($home_cid);
      dbxContentPageCache::invalidate_all_menus();
      $this->sync_page_meta($db, $home_cid);
   }
}

?>
