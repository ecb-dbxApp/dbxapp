<?php
namespace dbx\dbxAdmin;

require_once __DIR__ . '/dbxModuleRegistry.class.php';
require_once __DIR__ . '/dbxModuleImages.class.php';
require_once __DIR__ . '/dbxReport_Modules.class.php';

Class dbxModules {

   /** @var \dbxForm|null Stabiler sprachabhängiger Textkontext der Modulverwaltung. */
   private $moduleTexts;

   private function texts() {
      if ($this->moduleTexts) {
         return $this->moduleTexts;
      }

      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('module-admin-texts');
      $texts->_fd = 'dbxAdmin|module-admin';
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->moduleTexts = $texts;

      return $this->moduleTexts;
   }

   private function registry(): dbxModuleRegistry {
      static $registry = null;
      if (!$registry instanceof dbxModuleRegistry) {
         $registry = new dbxModuleRegistry();
      }
      return $registry;
   }

   private function localizedGroupOptions(array $options): array {
      $texts = $this->texts();
      $builtins = array(
         'admin' => 'group_admin',
         'guest' => 'group_guest',
         'member' => 'group_member',
         '*' => 'group_all',
      );
      foreach ($builtins as $key => $messageKey) {
         if (array_key_exists($key, $options)) {
            $options[$key] = $texts->get_fd_message($messageKey);
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
   private function clientMessagesJson(): string {
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

   private function adminHelp(): dbxAdminHelp {
      return dbx()->get_include_obj('dbxAdminHelp');
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
         return $this->tpl()->get_tpl('dbx', 'alert-warning', array('msg' => $texts->get_fd_message('no_module')));
      }

      $data = dbx()->get_cfg($xmodul);
      if (!is_array($data)) {
         $data = array();
      }

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('form-modul-access');
      $oForm->_fd = 'dbxAdmin|module-admin';
      $oForm->load_fd_messages();
      $oForm->add_rep('bar_title', $texts->format_fd_message('access_title', array('module' => dbx()->esc($xmodul))));
      $oForm->add_obj(
         'bar_actions',
         'obj-value',
         '<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-save"></i> '
            . dbx()->esc($texts->get_fd_message('action_save')) . '</button>'
      );
      $oForm->_data = $data;
      $oForm->_msg_info = $texts->get_fd_message('access_info');

      $oForm->add_obj('xmodul', $xmodul);
      $oForm->add_fld(
         'groups',
         'multi-select',
         options: $this->localizedGroupOptions($this->registry()->groupOptions()),
         rules: 'array|parameter',
         label: $texts->get_fd_message('label_groups'),
         errormsg: $texts->get_fd_message('invalid_groups')
      );

      $this->adminHelp()->attachForm($oForm, 'modules_access');

      if ($oForm->submit()) {
         if (!$oForm->errors()) {
            $config = dbx()->get_cfg($xmodul);
            if (!is_array($config)) {
               $config = array();
            }
            if (isset($oForm->_post['groups'])) {
               $groups = $oForm->_post['groups'];
               $config['groups'] = is_array($groups) ? implode(',', $groups) : (string)$groups;
            }
            $ok = $this->save_config_modul($xmodul, $config);
            if ($ok) {
               $oForm->_msg_success = $texts->get_fd_message('access_saved');
            } else {
               $oForm->_msg_error = $texts->get_fd_message('access_save_error');
            }
         } else {
            $oForm->_msg_error = $texts->get_fd_message('check_input');
         }
      }

      $content = $oForm->run();
      return str_replace('{xmodul}', $xmodul, $content);
   }

   private function modul_help() {
      $xmodul = trim((string)dbx()->get_modul_var('xmodul'));
      if ($xmodul === '') {
         return $this->tpl()->get_tpl('dbx', 'alert-warning', array('msg' => $this->texts()->get_fd_message('no_module')));
      }

      $raw = $this->registry()->inspect($xmodul);
      $title = (string)($raw['title'] ?? $xmodul);
      $moduleHelpHtml = $this->registry()->renderModuleHelp($xmodul, array(
         'title'  => $title,
         'xmodul' => $xmodul,
      ));

      return $this->tpl()->get_tpl('dbxAdmin|module-help-detail', array(
         'title'            => $title,
         'module_help_html' => $moduleHelpHtml,
      ));
   }



   private function prepareModuleRecord(array $record): array {
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
         return $this->tpl()->get_tpl('dbx', 'alert-warning', array('msg' => $this->texts()->get_fd_message('no_module')));
      }

      $images = $this->images();
      $items = $images->imageItems($xmodul);
      $info = $this->registry()->inspect($xmodul);
      $report = new dbxReport_Modules();
      $galleryHtml = $report->moduleImagesGalleryHtml(array(
         'xmodul'          => $xmodul,
         'image_items'     => $items,
         'default_run1'    => (string)($info['default_run1'] ?? 'run'),
         'default_run2'    => (string)($info['default_run2'] ?? ''),
         'placeholder_url' => (string)($info['placeholder_url'] ?? ''),
      ));
      $previewHtml = $report->moduleImagesPreviewHtml(array(
         'xmodul'          => $xmodul,
         'image_items'     => $items,
         'default_run1'    => (string)($info['default_run1'] ?? 'run'),
         'default_run2'    => (string)($info['default_run2'] ?? ''),
         'placeholder_url' => (string)($info['placeholder_url'] ?? ''),
      ));
      $targetHtml = $report->moduleImagesTargetHtml($info);

      $data = array(
         'xmodul'              => dbx()->esc($xmodul),
         'gallery_html'        => $galleryHtml,
         'images_preview_html' => $previewHtml,
         'images_target_html'  => $targetHtml,
         'uses_run2'           => (string)($info['uses_run2'] ?? '0'),
         'placeholder_url'     => dbx()->esc((string)($info['placeholder_url'] ?? '')),
         'placeholder_alt'     => dbx()->esc($this->texts()->get_fd_message('placeholder_alt')),
         'image_count'         => count($items),
         'module_messages_json'=> $this->clientMessagesJson(),
         'media_api_url'       => dbx()->esc($this->images()->mediaApiUrl() . '&images=1&media_type=image'),
         'images_add_url'      => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_add'),
         'images_upload_url'   => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload'),
         'images_remove_url'   => dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_remove'),
         'media_folders_url'   => dbx()->esc($this->images()->mediaFoldersApiUrl()),
         'media_browser_forms' => dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->renderTemplates(
            '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload',
            'modules-media-upload'
         ),
      );

      return $this->tpl()->get_tpl('dbxAdmin|form-modul-images', $data);
   }

   private function modul_image_serve() {
      $file = trim((string)dbx()->get_modul_var('file', '', '*'));
      $this->images()->serveFile($file);
   }

   private function modul_images_media_json() {
      $xmodul = trim((string)dbx()->get_modul_var('xmodul', ''));
      dbx()->json_response(array(
         'ok'   => 1,
         'rows' => $this->images()->mediaBrowserRows($xmodul),
      ));
   }

   private function modul_images_media_folders_json() {
      $folders = array();
      $dir = $this->images()->absDir();
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
         'items' => $this->images()->imageItems($xmodul),
      ));
   }

   private function modul_images_save_json() {
      $payload = $this->readJsonBody();
      $xmodul = (string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul'));
      $files = $payload['files'] ?? array();
      if ($xmodul === '' || !is_array($files)) {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('invalid_data')));
      }
      $ok = $this->images()->saveList($xmodul, $files);
      dbx()->json_response(array(
         'ok'    => $ok ? 1 : 0,
         'modul' => $xmodul,
         'items' => $this->images()->imageItems($xmodul),
      ));
   }

   private function modul_images_add_json() {
      $payload = $this->readJsonBody();
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
      $mediaId = (int)($payload['media_id'] ?? 0);
      $filePath = trim((string)($payload['file_path'] ?? ''));

      if ($mediaId > 0 || $filePath !== '') {
         $filename = $this->images()->importForModul($xmodul, $mediaId, $filePath, $run1, $run2);
      }

      if ($filename === null || $filename === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('image_import_error')));
      }

      dbx()->json_response(array(
         'ok'       => 1,
         'modul'    => $xmodul,
         'filename' => $filename,
         'items'    => $this->images()->imageItems($xmodul),
      ));
   }

   private function modul_symbol_add_json() {
      $payload = $this->readJsonBody();
      $xmodul = (string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul'));
      if ($xmodul === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('no_module')));
      }

      $mediaId = (int)($payload['media_id'] ?? 0);
      $filePath = trim((string)($payload['file_path'] ?? ''));
      if ($mediaId <= 0 && $filePath === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('symbol_select_required')));
      }

      $symbol = $this->images()->importSymbolForModul($xmodul, $mediaId, $filePath);
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
      $formState = dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->verify(
         'upload',
         '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload',
         'modules-media-upload'
      );
      $security = is_array($formState['security'] ?? null) ? $formState['security'] : array();
      $reply = static function(array $data) use ($security): void {
         $data['form_security'] = $security;
         dbx()->json_response($data);
      };
      if (empty($formState['submitted'])) {
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

      $filename = $this->images()->saveFromUpload($xmodul, $run1, $run2, $file);
      if ($filename === null || $filename === '') {
         $reply(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('upload_error')));
      }

      $reply(array(
         'ok'       => 1,
         'modul'    => $xmodul,
         'filename' => $filename,
         'items'    => $this->images()->imageItems($xmodul),
      ));
   }

   private function modul_images_remove_json() {
      $payload = $this->readJsonBody();
      $xmodul = (string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul'));
      $file = (string)($payload['file'] ?? '');
      $deleteFile = !array_key_exists('delete_file', $payload) || !empty($payload['delete_file']);
      if ($xmodul === '' || $file === '') {
         dbx()->json_response(array('ok' => 0, 'msg' => $this->texts()->get_fd_message('invalid_data')));
      }

      $ok = $this->images()->removeFromList($xmodul, $file, $deleteFile);
      dbx()->json_response(array(
         'ok'    => $ok ? 1 : 0,
         'modul' => $xmodul,
         'items' => $this->images()->imageItems($xmodul),
      ));
   }

   private function modul_access_save_json() {
      $payload = $this->readJsonBody();
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

      $allowed = array_keys($this->registry()->groupOptions());
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
      $payload = $this->readJsonBody();
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
      $payload = $this->readJsonBody();
      $xmodul = trim((string)($payload['xmodul'] ?? dbx()->get_modul_var('xmodul')));
      if ($xmodul === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $xmodul)) {
         dbx()->json_response(array('ok' => 0, 'msg' => $texts->get_fd_message('no_module')));
      }
      if (!$this->registry()->canDeleteModule($xmodul)) {
         dbx()->json_response(array('ok' => 0, 'msg' => $texts->get_fd_message('delete_not_allowed')));
      }

      $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $xmodul);
      $ok = $this->deleteModuleDirectory($dir);
      if ($ok) {
         if (isset($_SESSION['dbx']['config'][$xmodul])) {
            unset($_SESSION['dbx']['config'][$xmodul]);
         }
         if (isset($_SESSION['dbx']['config_file'][$xmodul])) {
            unset($_SESSION['dbx']['config_file'][$xmodul]);
         }
      }

      dbx()->json_response(array(
         'ok'    => $ok ? 1 : 0,
         'modul' => $xmodul,
         'msg'   => $ok ? $texts->get_fd_message('module_deleted') : $texts->get_fd_message('module_delete_error'),
      ));
   }

   private function deleteModuleDirectory(string $dir): bool {
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

   private function readJsonBody(): array {
      return dbx()->get_json_request();
   }

   private function modul_avatar() {
      $xmodul = (string)dbx()->get_modul_var('xmodul');
      $texts = $this->texts();

      if ($xmodul === '') {
         return $this->tpl()->get_tpl('dbx', 'alert-warning', array('msg' => $texts->get_fd_message('no_module')));
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

      $oForm = dbx()->get_system_obj('dbxForm');
      $oForm->init('dbxModules_avatar', 'form-avatar');
      $oForm->_fd = 'dbxAdmin|module-admin';
      $oForm->load_fd_messages();
      $oForm->_data = $data;
      $oForm->_msg_info = $texts->get_fd_message('avatar_info');
      $oForm->add_rep('avatar_upload_action', $texts->get_fd_message('avatar_upload_action'));
      $oForm->add_js_call('uploader_img', 'upload');

      if (!empty($_FILES)) {
         $oUpload = dbx()->get_system_obj('dbxUpload');
         $oUpload->upload($_FILES['upload_file']);
         $oUpload->allowed = array('image/*');
         $oUpload->file_new_name_body = 'modul';
         $oUpload->image_convert = 'gif';
         $oUpload->file_overwrite = true;
         $oUpload->image_resize = true;
         $oUpload->image_x = 200;
         $oUpload->image_y = 200;
         $oUpload->process($path);
         if ($oUpload->processed) {
            $oUpload->clean();
            $oForm->_data['avatar_upload'] = $url . $oUpload->file_dst_name;
            $oForm->_msg_success = $texts->get_fd_message('avatar_upload_success');
         } else {
            $oForm->set_msg_error($texts->get_fd_message('avatar_upload_error'));
         }
      }

      $oForm->add_fld(
         'avatar_upload',
         'avatar_upload',
         rules: 'alphanum',
         label: $texts->get_fd_message('avatar_label'),
         data: 'msg=' . $texts->get_fd_message('avatar_help')
      );
      $content = $oForm->run();
      return str_replace('{xmodul}', $xmodul, $content);
   }

   Private function report_modules() {
      $texts = $this->texts();
      $allModules = $this->registry()->inspectAll();
      $moduleOptions = array('0' => $texts->get_fd_message('all_modules'));
      foreach ($allModules as $module) {
         $name = (string)($module['xmodul'] ?? '');
         if ($name !== '') {
            $moduleOptions[$name] = $name;
         }
      }

      $activeCount = 0;
      foreach ($allModules as $module) {
         if ((string)($module['active'] ?? '1') === '1') {
            $activeCount++;
         }
      }

      $oReport = new dbxReport_Modules();
      $oReport->init('dbxModules', 'report-modules');
      $oReport->_fd = 'dbxAdmin|module-admin';
      $oReport->load_fd_messages();
      $oReport->set_form_help_enabled(false);
      $oReport->_action = '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_list';
      $oReport->_pages = true;
      $oReport->_rrows = 20;
      $oReport->_but_pagination = 7;
      $oReport->_fld_id = 'rid';
      $oReport->_create_row_select = true;
      $oReport->_create_sel_flds = false;
      $oReport->_data = array(
         'dbx_rmodul' => '0',
         'dbx_rwhere' => '',
         'dbx_rrows'  => 20,
         'dbx_rpos'   => 0,
      );

      $oReport->add_fld('dbx_rmodul', 'select-single-label', label: $texts->get_fd_message('label_module'), rules: 'parameter', options: $moduleOptions);
      $oReport->add_fld('dbx_rwhere', 'dbx|search', label: $texts->get_fd_message('label_search'), rules: 'sqlsearch|max=80');
      $oReport->add_fld('dbx_rrows', 'integer-label', label: $texts->get_fd_message('label_rows'), rules: 'int');

      $modulFilter = $oReport->get_fld_val('dbx_rmodul', '0', 'parameter');
      $search = $oReport->get_fld_val('dbx_rwhere', '', 'sqlsearch|max=80');
      $rrows = (int)$oReport->get_fld_val('dbx_rrows', 20, 'int');
      $rpos = (int)$oReport->get_fld_val('dbx_rpos', 0, 'int');
      if ($rrows <= 0) {
         $rrows = 20;
      }

      $filtered = $this->filterModuleRows($allModules, $modulFilter, $search);
      $filteredCount = count($filtered);
      if ($rpos < 0 || ($filteredCount > 0 && $rpos >= $filteredCount)) {
         $rpos = 0;
      }

      $oReport->_rrows = $rrows;
      $oReport->_rpos = $rpos;
      $oReport->_rcount = $filteredCount;
      $oReport->_rdata = $this->pageModuleRows($filtered, $rpos, $rrows);

      $oReport->add_rep('module_count', count($allModules));
      $oReport->add_rep('module_active_count', $activeCount);
      $oReport->add_rep('module_filtered_count', $filteredCount);
      $oReport->add_rep('bar_title', $texts->get_fd_message('bar_title'));
      $oReport->add_rep('bar_subtitle', $texts->get_fd_message('bar_subtitle'));
      $oReport->add_rep('action_filter', $texts->get_fd_message('action_filter'));
      $oReport->add_rep('modules_label', $texts->get_fd_message('modules_label'));
      $oReport->add_rep('active_count_label', $texts->get_fd_message('active_count_label'));
      $oReport->add_rep('module_messages_json', $this->clientMessagesJson());
      $oReport->add_rep('modimg_media_url', dbx()->esc($this->images()->mediaApiUrl() . '&images=1&media_type=image'));
      $oReport->add_rep('modimg_upload_url', dbx()->esc('?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload'));
      $oReport->add_rep('modimg_mediafolders_url', dbx()->esc($this->images()->mediaFoldersApiUrl()));
      $oReport->add_rep('modimg_mediafoldercreate_url', '');
      $oReport->add_rep('modimg_mediafolderdelete_url', '');
      $oReport->add_rep('modimg_deletemedia_url', '');
      $oReport->add_rep('modimg_uploadmax', (string)(16 * 1024 * 1024));
      $oReport->add_rep('media_browser_forms',
         dbx()->get_include_obj('dbxContentMediaForms', 'dbxContent_admin')->renderTemplates(
            '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_images_upload',
            'modules-media-upload'
         )
      );

      return $oReport->run();
   }

   private function filterModuleRows(array $modules, string $modulFilter, string $search): array {
      $modulFilter = trim($modulFilter);
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

         if ($modulFilter !== '' && $modulFilter !== '0' && $name !== $modulFilter) {
            continue;
         }

         if ($search !== '') {
            $ddList = $module['dd_list'] ?? array();
            if (!is_array($ddList)) {
               $ddList = array();
            }

            $haystack = strtolower(implode(' ', array(
               $name,
               (string)($module['description'] ?? ''),
               (string)($module['default_call'] ?? ''),
               (string)($module['groups_text'] ?? ''),
               (string)($module['version'] ?? ''),
               implode(' ', $ddList),
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

   private function pageModuleRows(array $modules, int $rpos, int $rrows): array {
      if ($rpos < 0) {
         $rpos = 0;
      }
      if ($rrows <= 0) {
         $rrows = 20;
      }

      $slice = array_slice($modules, $rpos, $rrows);
      $rows = array();
      foreach ($slice as $module) {
         $rows[] = $this->prepareModuleRecord($module);
      }

      return $rows;
   }

   public function modul_new() {
      $obj = dbx()->get_include_obj('dbxWizard');
      $run = dbx()->get_modul_var('run');
      return $obj->run($run);
   }

   public function modul_edit() {
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
            $content = $this->modul_new();
            break;

         case 'modul_edit':
            $content = $this->modul_edit();
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
            $content = $this->tpl()->get_tpl('dbx', 'alert-warning', $msg);
      }

      return $content;
   }
}

?>
