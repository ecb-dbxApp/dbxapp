<?php
namespace dbx\dbxAdmin;

require_once __DIR__ . '/dbxModuleRegistry.class.php';
require_once __DIR__ . '/dbxModuleImages.class.php';
require_once __DIR__ . '/dbxReport_Modules.class.php';

Class dbxModules {

   /** @var \dbxForm|null Stabiler sprachabhängiger Textkontext der Modulverwaltung. */
   private $module_texts;

   private function texts() {
      if ($this->module_texts) {
         return $this->module_texts;
      }

      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('module-admin-texts');
      $texts->set_field_definition('dbxAdmin|module-admin');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->module_texts = $texts;

      return $this->module_texts;
   }

   private function registry(): dbxModuleRegistry {
      static $registry = null;
      if (!$registry instanceof dbxModuleRegistry) {
         $registry = new dbxModuleRegistry();
      }
      return $registry;
   }

   private function localized_group_options(array $options): array {
      $texts = $this->texts();
      $builtins = array(
         'admin' => 'group_admin',
         'guest' => 'group_guest',
         'member' => 'group_member',
         '*' => 'group_all',
      );
      foreach ($builtins as $key => $message_key) {
         if (array_key_exists($key, $options)) {
            $options[$key] = $texts->get_fd_message($message_key);
         }
      }
      return $options;
   }

   /**
    * Sprachabhängige Laufzeittexte für modulesAdmin.js.
    *
    * Die JavaScript-Komponente erhält damit dieselben Texte wie PHP und greift
    * nach AJAX-Aktionen nicht auf fest verdrahtete deutsche Rückfalltexte zurück.
    */
   private function client_messages_json(): string {
      $texts = $this->texts();
      $keys = array(
         'active',
         'inactive',
         'saving',
         'saved',
         'save_failed',
         'status_save_error',
         'no_module_images',
         'module_preview',
         'open_module',
         'remove_image',
         'run1_required',
         'image_import_error',
         'symbol_import_error',
         'confirm_unavailable',
         'image_delete_title',
         'image_delete_question',
         'image_delete_hint',
         'module_delete_title',
         'module_delete_question',
         'module_delete_hint',
         'delete_button',
         'cancel_button',
      );
      $messages = array();
      foreach ($keys as $key) {
         $messages[$key] = $texts->get_fd_message($key);
      }

      $json = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return dbx()->esc(is_string($json) ? $json : '{}');
   }

   private function module_help(): \dbx\dbxHelp\dbxModuleHelp {
      return dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function save_config_modul($modul, $data) {
      return dbx()->set_cfg($modul, $data);
   }

   private function modul_access() {
      $xmodul = (string)dbx()->get_modul_var('xmodul');
      $texts = $this->texts();
      if ($xmodul === '') {
         return $this->tpl()->get_tpl('dbx|alert-warning', array('msg' => $texts->get_fd_message('no_module')));
      }

      $data = dbx()->get_cfg($xmodul);
      if (!is_array($data)) {
         $data = array();
      }

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('form-modul-access', 'form-modul-access');
      $o_form->set_field_definition('dbxAdmin|module-admin');
      $o_form->load_fd_messages();
      $o_form->add_rep('bar_title', $texts->format_fd_message('access_title', array('module' => dbx()->esc($xmodul))));
      $o_form->add_obj(
         'bar_actions',
         'obj-value',
         '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save"></i> '
            . dbx()->esc($texts->get_fd_message('action_save')) . '</button>'
      );
      $o_form->set_data($data);
      $o_form->_msg_info = $texts->get_fd_message('access_info');

      $o_form->add_obj('xmodul', $xmodul);
      $o_form->add_fld(
         'groups',
         'multi-select',
         options: $this->localized_group_options($this->registry()->group_options()),
         rules: 'array|parameter',
         label: $texts->get_fd_message('label_groups'),
         errormsg: $texts->get_fd_message('invalid_groups')
      );

      $this->module_help()->attach_form($o_form, 'modules', 'modul_access', 'dbxAdmin');

      if ($o_form->submit()) {
         if (!$o_form->errors()) {
            $config = dbx()->get_cfg($xmodul);
            if (!is_array($config)) {
               $config = array();
            }
            if ($o_form->has_post_value('groups')) {
               $groups = $o_form->post_value('groups');
               $config['groups'] = is_array($groups) ? implode(',', $groups) : (string)$groups;
            }
            $ok = $this->save_config_modul($xmodul, $config);
            if ($ok) {
               $o_form->_msg_success = $texts->get_fd_message('access_saved');
            } else {
               $o_form->_msg_error = $texts->get_fd_message('access_save_error');
            }
         } else {
            $o_form->_msg_error = $texts->get_fd_message('check_input');
         }
      }

      $content = $o_form->run();
      return str_replace('{xmodul}', $xmodul, $content);
   }

   private function modul_help() {
      $xmodul = trim((string)dbx()->get_modul_var('xmodul'));
      if ($xmodul === '') {
         return $this->tpl()->get_tpl('dbx|alert-warning', array('msg' => $this->texts()->get_fd_message('no_module')));
      }

      $raw = $this->registry()->inspect($xmodul);
      $title = (string)($raw['title'] ?? $xmodul);
      $module_help_html = $this->registry()->render_module_help($xmodul, array(
         'title'  => $title,
         'xmodul' => $xmodul,
      ));

      return $this->tpl()->get_tpl('dbxAdmin|module-help-detail', array(
         'title'            => $title,
         'module_help_html' => $module_help_html,
      ));
   }



   private function prepare_module_record(array $record): array {
      return $record;
   }

   private function images(): dbxModuleImages {
      static $obj = null;
      if (!$obj instanceof dbxModuleImages) {
         $obj = new dbxModuleImages();
      }
      return $obj;
   }

   private function modul_images() {
      $xmodul = (string)dbx()->get_modul_var('xmodul');
      if ($xmodul === '') {
         return $this->tpl()->get_tpl('dbx|alert-warning', array('msg' => $this->texts()->get_fd_message('no_module')));
      }

      $images = $this->images();
      $items = $images->image_items($xmodul);
      $info = $this->registry()->inspect($xmodul);
      $report = new dbxReport_Modules();
      $gallery_html = $report->module_images_gallery_html(array(
         'xmodul'          => $xmodul,
         'image_items'     => $items,
         'default_run1'    => (string)($info['default_run1'] ?? 'run'),
         'default_run2'    => (string)($info['default_run2'] ?? ''),
         'placeholder_url' => (string)($info['placeholder_url'] ?? ''),
      ));
      $preview_html = $report->module_images_preview_html(array(
         'xmodul'          => $xmodul,
         'image_items'     => $items,
         'default_run1'    => (string)($info['default_run1'] ?? 'run'),
         'default_run2'    => (string)($info['default_run2'] ?? ''),
         'placeholder_url' => (string)($info['placeholder_url'] ?? ''),
      ));
      $target_html = $report->module_images_target_html($info);

      $data = array(
         'xmodul'              => dbx()->esc($xmodul),
         'gallery_html'        => $gallery_html,
         'images_preview_html' => $preview_html,
         'images_target_html'  => $target_html,
         'uses_run2'           => (string)($info['uses_run2'] ?? '0'),
         'placeholder_url'     => dbx()->esc((string)($info['placeholder_url'] ?? '')),
         'placeholder_alt'     => dbx()->esc($this->texts()->get_fd_message('placeholder_alt')),
         'image_count'         => count($items),
         'module_messages_json'=> $this->client_messages_json(),
         'media_api_url'       => dbx()->esc($this->images()->media_api_url() . '&images=1&media_type=image'),
         'images_add_url'      => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_add'),
         'images_upload_url'   => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload'),
         'images_remove_url'   => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_remove'),
         'media_folders_url'   => dbx()->esc($this->images()->media_folders_api_url()),
         'media_browser_forms' => dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->render_templates(
            '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload',
            'modules-media-upload'
         ),
      );

      return $this->tpl()->get_tpl('dbxAdmin|form-modul-images', $data);
   }

   private function modul_image_serve() {
      $file = trim((string)dbx()->get_modul_var('file', '', '*'));
      $this->images()->serve_file($file);
   }

   private function modul_images_media_json() {
      $xmodul = trim((string)dbx()->get_modul_var('xmodul', ''));
      dbx()->json_response(array(
         'ok'   => 1,
         'rows' => $this->images()->media_browser_rows($xmodul),
      ));
   }

   private function modul_images_media_folders_json() {
      $folders = array();
      $dir = $this->images()->abs_dir();
      if (is_dir($dir) && is_readable($dir)) {
         $folders[] = 'mod';
      }
      dbx()->json_response(array(
         'ok'      => 1,
         'success' => true,
         'folders' => $folders,
         'root'    => 'files/mod/',
      ));
   }

   private function modul_images_list_json() {
      $xmodul = (string)dbx()->get_modul_var('xmodul');
      if ($xmodul === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('no_module'), 'items' => array()));
      }
      dbx()->json_response(array(
         'ok'    => 1,
         'modul' => $xmodul,
         'items' => $this->images()->image_items($xmodul),
      ));
   }

   private function modul_images_save_json() {
      $payload = $this->read_json_body();
      $xmodul = (string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul'));
      $files = $payload['files'] ?? array();
      if ($xmodul === '' || !is_array($files)) {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('invalid_data')));
      }
      $ok = $this->images()->save_list($xmodul, $files);
      dbx()->json_response(array(
         'ok'    => $ok ? 1 : 0,
         'modul' => $xmodul,
         'items' => $this->images()->image_items($xmodul),
      ));
   }

   private function modul_images_add_json() {
      $payload = $this->read_json_body();
      $xmodul = (string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul'));
      if ($xmodul === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('no_module')));
      }

      $run1 = trim((string)($payload['dbx_run1'] ?? $payload['run1'] ?? ''));
      $run2 = trim((string)($payload['dbx_run2'] ?? $payload['run2'] ?? ''));
      if ($run1 === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('run1_required')));
      }

      $filename = null;
      $media_id = (int)($payload['media_id'] ?? 0);
      $file_path = trim((string)($payload['file_path'] ?? ''));

      if ($media_id > 0 || $file_path !== '') {
         $filename = $this->images()->import_for_modul($xmodul, $media_id, $file_path, $run1, $run2);
      }

      if ($filename === null || $filename === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('image_import_error')));
      }

      dbx()->json_response(array(
         'ok'       => 1,
         'modul'    => $xmodul,
         'filename' => $filename,
         'items'    => $this->images()->image_items($xmodul),
      ));
   }

   private function modul_symbol_add_json() {
      $payload = $this->read_json_body();
      $xmodul = (string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul'));
      if ($xmodul === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('no_module')));
      }

      $media_id = (int)($payload['media_id'] ?? 0);
      $file_path = trim((string)($payload['file_path'] ?? ''));
      if ($media_id <= 0 && $file_path === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('symbol_select_required')));
      }

      $symbol = $this->images()->import_symbol_for_modul($xmodul, $media_id, $file_path);
      if (!is_array($symbol) || empty($symbol['url'])) {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('symbol_import_error')));
      }

      dbx()->json_response(array(
         'ok'     => 1,
         'modul'  => $xmodul,
         'symbol' => $symbol,
      ));
   }

   private function modul_images_upload_json() {
      $form_state = dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->verify(
         'upload',
         '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload',
         'modules-media-upload'
      );
      $security = is_array($form_state['security'] ?? null) ? $form_state['security'] : array();
      $reply = static function(array $data) use ($security): void {
         $data['form_security'] = $security;
         dbx()->json_response($data);
      };
      if (empty($form_state['submitted'])) {
         $reply(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('form_token_invalid')));
      }

      $xmodul = trim((string)($_POST['xmodul'] ?? dbx()->get_modul_var('xmodul')));
      $run1 = trim((string)($_POST['dbx_run1'] ?? $_POST['run1'] ?? ''));
      $run2 = trim((string)($_POST['dbx_run2'] ?? $_POST['run2'] ?? ''));
      if ($xmodul === '') {
         $reply(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('no_module')));
      }
      if ($run1 === '') {
         $reply(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('run1_required')));
      }

      $file = $_FILES['file'] ?? null;
      if (!is_array($file)) {
         $reply(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('file_missing')));
      }

      $filename = $this->images()->save_from_upload($xmodul, $run1, $run2, $file);
      if ($filename === null || $filename === '') {
         $reply(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('upload_error')));
      }

      $reply(array(
         'ok'       => 1,
         'modul'    => $xmodul,
         'filename' => $filename,
         'items'    => $this->images()->image_items($xmodul),
      ));
   }

   private function modul_images_remove_json() {
      $payload = $this->read_json_body();
      $xmodul = (string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul'));
      $file = (string)($payload['file'] ?? '');
      $delete_file = !array_key_exists('delete_file', $payload) || !empty($payload['delete_file']);
      if ($xmodul === '' || $file === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('invalid_data')));
      }

      $ok = $this->images()->remove_from_list($xmodul, $file, $delete_file);
      dbx()->json_response(array(
         'ok'    => $ok ? 1 : 0,
         'modul' => $xmodul,
         'items' => $this->images()->image_items($xmodul),
      ));
   }

   private function modul_access_save_json() {
      $payload = $this->read_json_body();
      $xmodul = trim((string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul')));
      if ($xmodul === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('no_module')));
      }

      $groups = $payload['groups'] ?? array();
      if (!is_array($groups)) {
         $groups = preg_split('/\s*,\s*/', trim((string)$groups), -1, PREG_SPLIT_NO_EMPTY);
      }
      if (!is_array($groups)) {
         $groups = array();
      }

      $allowed = array_keys($this->registry()->group_options());
      $clean = array();
      foreach ($groups as $group) {
         $group = trim((string)$group);
         if ($group !== '' && in_array($group, $allowed, true)) {
            $clean[] = $group;
         }
      }
      $clean = array_values(array_unique($clean));

      $config = dbx()->get_cfg($xmodul);
      if (!is_array($config)) {
         $config = array();
      }
      $config['groups'] = implode(',', $clean);

      $ok = dbx()->set_cfg($xmodul, $config);
      $texts = $this->texts();
      dbx()->json_response(array(
         'ok'     => $ok ? 1 : 0,
         'modul'  => $xmodul,
         'groups' => $clean,
         'msg'    => $ok ? $texts->get_fd_message('access_saved') : $texts->get_fd_message('access_save_error'),
      ));
   }

   private function modul_active_toggle_json() {
      $texts = $this->texts();
      $payload = $this->read_json_body();
      $xmodul = trim((string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul')));
      if ($xmodul === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $xmodul)) {
         dbx()->json_response(array('ok' => 0, 'msg' => $texts->get_fd_message('no_module')));
      }

      $config = dbx()->get_cfg($xmodul);
      if (!is_array($config)) {
         $config = array();
      }

      $active = null;
      if (array_key_exists('active', $payload)) {
         $active = !empty($payload['active']) ? '1' : '0';
      } else {
         $current = '1';
         if (isset($config['activ'])) {
            $current = (string)$config['activ'] === '1' ? '1' : '0';
         } elseif (isset($config['active'])) {
            $current = (string)$config['active'] === '1' ? '1' : '0';
         }
         $active = $current === '1' ? '0' : '1';
      }

      $config['activ'] = $active;
      $config['active'] = $active;
      $ok = dbx()->set_cfg($xmodul, $config);
      dbx()->json_response(array(
         'ok'           => $ok ? 1 : 0,
         'modul'        => $xmodul,
         'active'       => $active,
         'active_label' => $active === '1' ? $texts->get_fd_message('active') : $texts->get_fd_message('inactive'),
         'msg'          => $ok ? $texts->get_fd_message('status_saved') : $texts->get_fd_message('status_save_error'),
      ));
   }

   private function modul_delete_json() {
      $texts = $this->texts();
      $payload = $this->read_json_body();
      $xmodul = trim((string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul')));
      if ($xmodul === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $xmodul)) {
         dbx()->json_response(array('ok' => 0, 'msg' => $texts->get_fd_message('no_module')));
      }
      if (!$this->registry()->can_delete_module($xmodul)) {
         dbx()->json_response(array('ok' => 0, 'msg' => $texts->get_fd_message('delete_not_allowed')));
      }

      $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $xmodul);
      $ok = $this->delete_module_directory($dir);
      if ($ok) {
         dbx()->get_system_obj('dbxConfigStore')->forget($xmodul);
      }

      dbx()->json_response(array(
         'ok'    => $ok ? 1 : 0,
         'modul' => $xmodul,
         'msg'   => $ok ? $texts->get_fd_message('module_deleted') : $texts->get_fd_message('module_delete_error'),
      ));
   }

   private function delete_module_directory(string $dir): bool {
      $dir = rtrim(dbx()->os_path($dir), '/\\');
      if ($dir === '' || !is_dir($dir)) {
         return false;
      }

      $items = new \RecursiveIteratorIterator(
         new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
         \RecursiveIteratorIterator::CHILD_FIRST
      );

      foreach ($items as $item) {
         $path = $item->getPathname();
         if ($item->isDir()) {
            if (!@rmdir($path)) {
               return false;
            }
         } elseif (!@unlink($path)) {
            return false;
         }
      }

      return @rmdir($dir);
   }

   private function read_json_body(): array {
      return dbx()->get_json_request();
   }

   private function modul_avatar() {
      $xmodul = (string)dbx()->get_modul_var('xmodul');
      $texts = $this->texts();

      if ($xmodul === '') {
         return $this->tpl()->get_tpl('dbx|alert-warning', array('msg' => $texts->get_fd_message('no_module')));
      }

      $path = dbx()->os_path(dbx()->get_base_dir() . "dbx/modules/$xmodul/tpl/img/");
      $url = dbx()->get_base_url() . "dbx/modules/$xmodul/tpl/img/";
      $modul_img = 'modul.gif';
      $path_img_ext = $path . $modul_img;
      $url_img_ext = $url . $modul_img;

      $data = array(
         'xmodul' => $xmodul,
         'avatar_upload' => $url_img_ext,
      );

      $o_form = dbx()->get_system_obj('dbxForm');
      $o_form->init('dbxModules_avatar', 'form-avatar');
      $o_form->set_field_definition('dbxAdmin|module-admin');
      $o_form->load_fd_messages();
      $o_form->set_data($data);
      $o_form->_msg_info = $texts->get_fd_message('avatar_info');
      $o_form->add_rep('avatar_upload_action', $texts->get_fd_message('avatar_upload_action'));
      $o_form->add_js_call('uploader_img', 'upload');

      if (!empty($_FILES)) {
         $o_upload = dbx()->get_system_obj('dbxUpload');
         $o_upload->upload($_FILES['upload_file']);
         $o_upload->allowed = array('image/*');
         $o_upload->file_new_name_body = 'modul';
         $o_upload->image_convert = 'gif';
         $o_upload->file_overwrite = true;
         $o_upload->image_resize = true;
         $o_upload->image_x = 200;
         $o_upload->image_y = 200;
         $o_upload->process($path);
         if ($o_upload->processed) {
            $o_upload->clean();
            $o_form->set_data_value('avatar_upload', $url . $o_upload->file_dst_name);
            $o_form->_msg_success = $texts->get_fd_message('avatar_upload_success');
         } else {
            $o_form->set_msg_error($texts->get_fd_message('avatar_upload_error'));
         }
      }

      $o_form->add_fld(
         'avatar_upload',
         'avatar_upload',
         rules: 'alphanum',
         label: $texts->get_fd_message('avatar_label'),
         data: 'msg=' . $texts->get_fd_message('avatar_help')
      );
      $content = $o_form->run();
      return str_replace('{xmodul}', $xmodul, $content);
   }

   Private function report_modules() {
      $texts = $this->texts();
      $all_modules = $this->registry()->inspect_all();
      $module_options = array('0' => $texts->get_fd_message('all_modules'));
      foreach ($all_modules as $module) {
         $name = (string)($module['xmodul'] ?? '');
         if ($name !== '') {
            $module_options[$name] = $name;
         }
      }

      $active_count = 0;
      foreach ($all_modules as $module) {
         if ((string)($module['active'] ?? '1') === '1') {
            $active_count++;
         }
      }

      $o_report = new dbxReport_Modules();
      $o_report->init('dbxModules', 'report-modules');
      $o_report->set_field_definition('dbxAdmin|module-admin');
      $o_report->load_fd_messages();
      $o_report->set_form_help_enabled(false);
      $o_report->set_action('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_list');
      $o_report->_pages = true;
      $o_report->_rrows = 20;
      $o_report->_but_pagination = 7;
      $o_report->_fld_id = 'rid';
      $o_report->_create_row_select = true;
      $o_report->_create_sel_flds = false;
      $o_report->set_data(array(
         'dbx_rmodul' => '0',
         'dbx_rwhere' => '',
         'dbx_rrows'  => 20,
         'dbx_rpos'   => 0,
      ));

      $o_report->add_fld('dbx_rmodul', 'select-single-label', label: $texts->get_fd_message('label_module'), rules: 'parameter', options: $module_options);
      $o_report->add_fld('dbx_rwhere', 'dbx|search', label: $texts->get_fd_message('label_search'), rules: 'sqlsearch|max=80');
      $o_report->add_fld('dbx_rrows', 'integer-label', label: $texts->get_fd_message('label_rows'), rules: 'int');

      $modul_filter = $o_report->get_fld_val('dbx_rmodul', '0', 'parameter');
      $search = $o_report->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=80');
      $rrows = (int)$o_report->get_fld_val('dbx_rrows', 20, 'int');
      $rpos = (int)$o_report->get_fld_val('dbx_rpos', 0, 'int');
      if ($rrows <= 0) {
         $rrows = 20;
      }

      $filtered = $this->filter_module_rows($all_modules, $modul_filter, $search);
      $filtered_count = count($filtered);
      if ($rpos < 0 || ($filtered_count > 0 && $rpos >= $filtered_count)) {
         $rpos = 0;
      }

      $o_report->_rrows = $rrows;
      $o_report->_rpos = $rpos;
      $o_report->set_report_counts($filtered_count, count($all_modules));
      $o_report->_rdata = $this->page_module_rows($filtered, $rpos, $rrows);

      $o_report->add_rep('module_count', count($all_modules));
      $o_report->add_rep('module_active_count', $active_count);
      $o_report->add_rep('module_filtered_count', $filtered_count);
      $o_report->add_rep(
         'report_extra_stats',
         '<span class="dbx-report-bar-stat"><strong>' . $active_count . '</strong> '
            . dbx()->esc($texts->get_fd_message('active_count_label')) . '</span>'
      );
      $o_report->add_rep('bar_title', $texts->get_fd_message('bar_title'));
      $o_report->add_rep('bar_subtitle', $texts->get_fd_message('bar_subtitle'));
      $o_report->add_rep('action_filter', $texts->get_fd_message('action_filter'));
      $o_report->add_rep('modules_label', $texts->get_fd_message('modules_label'));
      $o_report->add_rep('active_count_label', $texts->get_fd_message('active_count_label'));
      $o_report->add_rep('module_messages_json', $this->client_messages_json());
      $o_report->add_rep('modimg_media_url', dbx()->esc($this->images()->media_api_url() . '&images=1&media_type=image'));
      $o_report->add_rep('modimg_upload_url', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload'));
      $o_report->add_rep('modimg_mediafolders_url', dbx()->esc($this->images()->media_folders_api_url()));
      $o_report->add_rep('modimg_mediafoldercreate_url', '');
      $o_report->add_rep('modimg_mediafolderdelete_url', '');
      $o_report->add_rep('modimg_deletemedia_url', '');
      $o_report->add_rep('modimg_uploadmax', (string)(16 * 1024 * 1024));
      $o_report->add_rep('media_browser_forms',
         dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->render_templates(
            '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload',
            'modules-media-upload'
         )
      );

      return $o_report->run();
   }

   private function filter_module_rows(array $modules, string $modul_filter, string $search): array {
      $modul_filter = trim($modul_filter);
      $search = strtolower(trim($search));
      $rows = array();

      foreach ($modules as $module) {
         if (!is_array($module)) {
            continue;
         }

         $name = (string)($module['xmodul'] ?? '');
         if ($name === '') {
            continue;
         }

         if ($modul_filter !== '' && $modul_filter !== '0' && $name !== $modul_filter) {
            continue;
         }

         if ($search !== '') {
            $dd_list = $module['dd_list'] ?? array();
            if (!is_array($dd_list)) {
               $dd_list = array();
            }

            $haystack = strtolower(implode(' ', array(
               $name,
               (string)($module['description'] ?? ''),
               (string)($module['default_call'] ?? ''),
               (string)($module['groups_text'] ?? ''),
               (string)($module['version'] ?? ''),
               implode(' ', $dd_list),
            )));

            if (strpos($haystack, $search) === false) {
               continue;
            }
         }

         $module['rid'] = $name;
         $rows[] = $module;
      }

      return $rows;
   }

   private function page_module_rows(array $modules, int $rpos, int $rrows): array {
      if ($rpos < 0) {
         $rpos = 0;
      }
      if ($rrows <= 0) {
         $rrows = 20;
      }

      $slice = array_slice($modules, $rpos, $rrows);
      $rows = array();
      foreach ($slice as $module) {
         $rows[] = $this->prepare_module_record($module);
      }

      return $rows;
   }

   private function run_wizard() {
      $obj = dbx()->get_include_obj('dbxWizard');
      $run = dbx()->get_modul_var('run');
      return $obj->run($run);
   }

   public function run() {
      $modul = dbx()->get_modul_var('dbx_modul');
      $action = dbx()->get_modul_var('dbx_run1');
      $work = dbx()->get_modul_var('dbx_run2');
      $content = "dbxAdmin->dbxModules ($work) X<br>";

      switch ($work) {
         case 'modul_list':
            $content = $this->report_modules();
            break;

         case 'modul_avatar':
            $content = $this->modul_avatar();
            break;

         case 'avatar_upload':
            $content = $this->modul_avatar();
            break;

         case 'modul_new':
         case 'modul_edit':
            $content = $this->run_wizard();
            break;

         case 'modul_access':
            $content = $this->modul_access();
            break;

         case 'modul_access_save':
            $this->modul_access_save_json();
            break;

         case 'modul_active_toggle':
            $this->modul_active_toggle_json();
            break;

         case 'modul_delete':
            $this->modul_delete_json();
            break;

         case 'modul_help':
            $content = $this->modul_help();
            break;

         case 'modul_images':
            $content = $this->modul_images();
            break;

         case 'modul_image':
            $this->modul_image_serve();
            break;

         case 'modul_images_list':
            $this->modul_images_list_json();
            break;

         case 'modul_images_save':
            $this->modul_images_save_json();
            break;

         case 'modul_images_add':
            $this->modul_images_add_json();
            break;

         case 'modul_symbol_add':
            $this->modul_symbol_add_json();
            break;

         case 'modul_images_upload':
            $this->modul_images_upload_json();
            break;

         case 'modul_images_remove':
            $this->modul_images_remove_json();
            break;

         case 'modul_images_media':
            $this->modul_images_media_json();
            break;

         case 'modul_images_media_folders':
            $this->modul_images_media_folders_json();
            break;

         default:
            $msg['msg'] = "Modul=($modul) Action=($action) Work=($work) is undef!";
            $content = $this->tpl()->get_tpl('dbx|alert-warning', $msg);
      }

      return $content;
   }
}

?>
