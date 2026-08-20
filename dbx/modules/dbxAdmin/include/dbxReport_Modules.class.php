<?php
namespace dbx\dbxAdmin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_Modules extends \dbxReport {

   private $registry;

   public function __construct() {
      parent::__construct();
      $this->registry = new dbxModuleRegistry();
      $this->set_field_definition('dbxAdmin|module-admin');
      $this->load_fd_messages();
      $this->set_form_help_enabled(false);
   }

   public function render_card(array $record): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $active = (string)($record['active'] ?? '1') === '1';
      $record['active_label'] = $this->get_fd_message($active ? 'active' : 'inactive');
      $record['toggle_title'] = $this->get_fd_message('toggle_panel');
      $record['graphic_html'] = $this->render_module_images($record);
      $record['groups_html'] = $this->render_groups($record);
      $record['dd_html'] = $this->render_dd_list($record);
      $record['card_state_class'] = ((string)($record['active'] ?? '1') === '0') ? ' dbx-module-row-inactive' : '';
      $record['install_button_html'] = $this->render_install_button($record);
      $record['active_toggle_button_html'] = $this->render_active_toggle_button($record);
      $record['delete_button_html'] = $this->render_delete_button($record);
      $record['actions_html'] = $tpl->get_tpl('dbxAdmin|module-card-actions', $record);
      $record['access_inline_html'] = $this->render_access_inline($record);
      $record['select_html'] = $this->_create_row_select
         ? $this->render_table_row_select($record, 'dbx-module-row-select')
         : '';
      return $tpl->get_tpl('dbxAdmin|module-card', $record);
   }

   public function render_dd_html(array $record): string {
      return $this->render_dd_list($record);
   }

   public function run_body($content) {
      $record = is_array($this->_record) ? $this->_record : array();
      $card = $this->render_card($record);
      $content = (string)$content;

      if (strpos($content, '[rpt:row]') !== false) {
         $content = str_replace('[rpt:row]', $card, $content);
      } else {
         $content = $card;
      }

      return $this->forward_run_body($content);
   }

   public function module_images_target_html(array $record): string {
      return $this->build_module_images_target_html($record);
   }

   public function module_images_gallery_html(array $record): string {
      return $this->build_module_images_gallery_html($record);
   }

   private function render_module_images(array $record): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $default_run1 = trim((string)($record['default_run1'] ?? 'run'));
      if ($default_run1 === '') {
         $default_run1 = 'run';
      }
      $default_run2 = trim((string)($record['default_run2'] ?? ''));
      $record['symbol_preview_html'] = $this->build_module_symbol_preview_html($record);
      $record['images_gallery_html'] = $this->build_module_images_gallery_html($record);
      $record['default_run1'] = dbx()->esc($default_run1);
      $record['default_run2'] = dbx()->esc($default_run2);
      $record['image_count'] = (string)count($record['image_items'] ?? array());
      $record['images_add_url'] = dbx()->esc((string)($record['images_add_url'] ?? ''));
      $record['images_upload_url'] = dbx()->esc((string)($record['images_upload_url'] ?? ''));
      $record['images_remove_url'] = dbx()->esc((string)($record['images_remove_url'] ?? ''));
      $record['symbol_add_url'] = dbx()->esc((string)($record['symbol_add_url'] ?? '?dbx_modul=dbxAdmin&dbx_run1=modules&dbx_run2=modul_symbol_add'));
      $record['symbol_media_url'] = dbx()->esc((string)($record['symbol_media_url'] ?? '?dbx_modul=dbxContent_admin&dbx_run1=cms_media&images=1&media_type=image'));
      $record['symbol_upload_url'] = dbx()->esc((string)($record['symbol_upload_url'] ?? '?dbx_modul=dbxContent_admin&dbx_run1=cms_upload'));
      $record['symbol_mediafolders_url'] = dbx()->esc((string)($record['symbol_mediafolders_url'] ?? '?dbx_modul=dbxContent_admin&dbx_run1=cms_media_folders'));
      $record['symbol_mediafoldercreate_url'] = dbx()->esc((string)($record['symbol_mediafoldercreate_url'] ?? '?dbx_modul=dbxContent_admin&dbx_run1=cms_media_folder_create'));
      $record['symbol_mediafolderdelete_url'] = dbx()->esc((string)($record['symbol_mediafolderdelete_url'] ?? '?dbx_modul=dbxContent_admin&dbx_run1=cms_media_folder_delete'));
      $record['symbol_deletemedia_url'] = dbx()->esc((string)($record['symbol_deletemedia_url'] ?? '?dbx_modul=dbxContent_admin&dbx_run1=cms_delete_media'));
      $record['placeholder_url'] = dbx()->esc((string)($record['placeholder_url'] ?? ''));
      $record['placeholder_alt'] = dbx()->esc($this->module_images_placeholder_alt());
      $run1List = $this->build_module_images_run1_datalist($record, $default_run1);
      $record['run1_list_id'] = dbx()->esc((string)($run1List['id'] ?? ''));
      $record['run1_options_html'] = (string)($run1List['options_html'] ?? '');
      return $tpl->get_tpl('dbxAdmin|module-card-images', $record);
   }

   private function build_module_images_run1_datalist(array $record, string $default_run1): array {
      $modul = (string)($record['xmodul'] ?? 'mod');
      $list_id = 'dbx_modimg_run1_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $modul);
      $cases = $record['run_cases'] ?? array();
      if (!is_array($cases)) {
         $cases = array();
      }
      if (!$cases && $default_run1 !== '') {
         $cases = array($default_run1);
      }

      $options = '';
      $seen = array();
      foreach ($cases as $run1) {
         $run1 = trim((string)$run1);
         if ($run1 === '' || isset($seen[$run1])) {
            continue;
         }
         $seen[$run1] = 1;
         $options .= '<option value="' . dbx()->esc($run1) . '"></option>';
      }

      return array(
         'id'           => $list_id,
         'options_html' => $options,
      );
   }

   private function build_module_symbol_preview_html(array $record): string {
      $modul = dbx()->esc((string)($record['xmodul'] ?? ''));
      $url = dbx()->esc((string)($record['graphic_url'] ?? ''));
      $alt = dbx()->esc((string)($record['graphic_alt'] ?? $modul));

      if ($url !== '') {
         return '<img src="' . $url . '" alt="' . $alt . '" class="dbx-module-symbol-img" loading="lazy">';
      }

      $placeholder = dbx()->esc($this->module_images_placeholder_url($record));
      if ($placeholder !== '') {
         return '<img src="' . $placeholder . '" alt="' . dbx()->esc($this->module_images_placeholder_alt()) . '" class="dbx-module-symbol-img is-placeholder" loading="lazy">';
      }

      return '<span class="dbx-module-images-placeholder-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>';
   }

   private function module_images_placeholder_url(array $record): string {
      return $this->registry->module_image_empty_placeholder_url();
   }

   private function module_images_placeholder_alt(): string {
      return $this->get_fd_message('placeholder_alt');
   }

   private function build_module_images_preview_html(array $record): string {
      $modul = dbx()->esc((string)($record['xmodul'] ?? ''));
      $items = $record['image_items'] ?? array();
      if (is_array($items) && isset($items[0]) && is_array($items[0])) {
         $first = $items[0];
         $url = dbx()->esc((string)($first['url'] ?? ''));
         $label = dbx()->esc((string)($first['label'] ?? ($first['file'] ?? $modul)));
         if ($url !== '') {
            return '<img src="' . $url . '" alt="' . $label . '" class="dbx-module-images-preview-img" loading="lazy">';
         }
      }

      $placeholder = dbx()->esc($this->module_images_placeholder_url($record));
      if ($placeholder !== '') {
         $alt = dbx()->esc($this->module_images_placeholder_alt());
         return '<img src="' . $placeholder . '" alt="' . $alt . '" class="dbx-module-images-preview-img is-placeholder" loading="lazy">';
      }

      return '<span class="dbx-module-images-placeholder-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>';
   }

   private function build_module_images_target_html(array $record): string {
      $modul = dbx()->esc((string)($record['xmodul'] ?? ''));
      $default_run1 = trim((string)($record['default_run1'] ?? 'run'));
      if ($default_run1 === '') {
         $default_run1 = 'run';
      }
      $default_run2 = trim((string)($record['default_run2'] ?? ''));
      $uses_run2 = (string)($record['uses_run2'] ?? '0') === '1';
      $cases = $record['run_cases'] ?? array();
      if (!is_array($cases)) {
         $cases = array();
      }
      if (!$cases) {
         $cases = array($default_run1);
      }

      $list_id = 'dbx_modimg_run1_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string)($record['xmodul'] ?? 'mod'));
      $options = '';
      $seen = array();
      foreach ($cases as $run1) {
         $run1 = trim((string)$run1);
         if ($run1 === '' || isset($seen[$run1])) {
            continue;
         }
         $seen[$run1] = 1;
         $selected = ($run1 === $default_run1) ? ' selected' : '';
         $options .= '<option value="' . dbx()->esc($run1) . '"' . $selected . '>' . dbx()->esc($run1) . '</option>';
      }

      $run2Class = 'dbx-module-images-run2-wrap';
      $run2Disabled = '';

      $images = new dbxModuleImages();
      $preview_name = dbx()->esc($images->stem_for_runs((string)($record['xmodul'] ?? ''), $default_run1, $default_run2) . '.*');

      return '<div class="dbx-module-images-target">'
         . '<label class="dbx-module-images-target-field">'
         . '<span>dbx_run1</span>'
         . '<input type="text" class="form-control form-control-sm dbx-module-images-run1" list="' . $list_id . '" value="' . dbx()->esc($default_run1) . '" placeholder="' . dbx()->esc($this->get_fd_message('run1_placeholder')) . '" required>'
         . '<datalist id="' . $list_id . '">' . $options . '</datalist>'
         . '</label>'
         . '<label class="' . $run2Class . ' dbx-module-images-target-field">'
         . '<span>dbx_run2</span>'
         . '<input type="text" class="form-control form-control-sm dbx-module-images-run2" value="' . dbx()->esc($default_run2) . '" placeholder="' . dbx()->esc($this->get_fd_message('optional')) . '"' . $run2Disabled . '>'
         . '</label>'
         . '<div class="dbx-module-images-filename">'
         . '<span>' . dbx()->esc($this->get_fd_message('filename')) . '</span>'
         . '<code class="dbx-module-images-filename-preview" data-modul="' . $modul . '">' . $preview_name . '</code>'
         . '</div>'
         . '</div>';
   }

   private function resolve_image_preview_runs(array $item, array $record): array {
      $run1 = trim((string)($item['run1'] ?? ''));
      $run2 = trim((string)($item['run2'] ?? ''));

      if ($run1 === '' || $run2 === '') {
         $params = trim((string)($item['default_params'] ?? ''));
         if ($params !== '') {
            if ($run1 === '' && preg_match('/(?:^|&)dbx_run1=([^&]+)/', $params, $m)) {
               $run1 = rawurldecode($m[1]);
            }
            if ($run2 === '' && preg_match('/(?:^|&)dbx_run2=([^&]+)/', $params, $m)) {
               $run2 = rawurldecode($m[1]);
            }
         }
      }

      if ($run1 === '') {
         $run1 = trim((string)($record['default_run1'] ?? ''));
      }
      if ($run2 === '') {
         $run2 = trim((string)($record['default_run2'] ?? ''));
      }

      return array($run1, $run2);
   }

   private function build_module_images_gallery_html(array $record): string {
      $items = $record['image_items'] ?? array();
      $xmodul = (string)($record['xmodul'] ?? '');
      if (!is_array($items) || !$items) {
         return '<div class="dbx-module-images-empty-list text-muted small">' . dbx()->esc($this->get_fd_message('no_module_images')) . '</div>';
      }

      $html = '';
      foreach ($items as $i => $item) {
         if (!is_array($item)) {
            continue;
         }
         $file = dbx()->esc((string)($item['file'] ?? ''));
         $url = dbx()->esc((string)($item['url'] ?? ''));
         $label = dbx()->esc((string)($item['label'] ?? $file));
         $params = trim((string)($item['default_params'] ?? ''));
         $call = 'dbx_modul=' . rawurlencode($xmodul);
         if ($params !== '') {
            $call .= '&' . $params;
         }
         $call = dbx()->esc($call);
         list($run1, $run2) = $this->resolve_image_preview_runs($item, $record);
         $run1Esc = dbx()->esc($run1);
         $run2Esc = dbx()->esc($run2);
         $preview_url = dbx()->esc($this->registry->modul_url($xmodul, $run1, $run2));
         $preview_title = dbx()->esc($this->format_fd_message('module_preview', array('module' => $xmodul)));
         $active = ($i === 0) ? ' is-active' : '';
         $preview_btn = '';
         if ($run1 !== '' || $params !== '') {
            $preview_btn = '<a class="btn btn-outline-secondary btn-sm dbx-win dbx-module-images-preview" href="' . $preview_url . '" data-url="' . $preview_url . '" data-title="' . $preview_title . '" data-width="88%" data-height="88%" data-dbx-tooltip="' . dbx()->esc($this->get_fd_message('open_module')) . '"><i class="bi bi-box-arrow-up-right"></i></a>';
         }
         $html .= '<div class="dbx-module-images-item' . $active . '" data-file="' . $file . '" data-params="' . dbx()->esc($params) . '" data-url="' . $url . '" data-run1="' . $run1Esc . '" data-run2="' . $run2Esc . '">'
            . '<span class="dbx-module-images-thumb">'
            . '<img src="' . $url . '" alt="' . $label . '" loading="lazy">'
            . '</span>'
            . '<span class="dbx-module-images-meta">'
            . '<code class="dbx-module-images-call">' . $call . '</code>'
            . '<span class="dbx-module-images-file">' . $file . '</span>'
            . '</span>'
            . '<span class="dbx-module-images-actions">'
            . $preview_btn
            . '<button type="button" class="btn btn-outline-danger btn-sm dbx-module-images-remove" data-dbx-tooltip="' . dbx()->esc($this->get_fd_message('remove_image')) . '"><i class="bi bi-trash"></i></button>'
            . '</span>'
            . '</div>';
      }

      return $html !== '' ? $html : '<div class="dbx-module-images-empty-list text-muted small">' . dbx()->esc($this->get_fd_message('no_module_images')) . '</div>';
   }

   public function module_images_preview_html(array $record): string {
      return $this->build_module_images_preview_html($record);
   }


   private function render_install_button(array $record): string {
      if (empty($record['install_url'])) {
         return '';
      }

      $url = dbx()->esc((string)$record['install_url']);
      $title = (string)($record['title'] ?? '');
      $dialog_title = dbx()->esc($this->format_fd_message('install_title', array('module' => $title)));

      return '<a class="btn btn-outline-secondary dbx-win" href="' . $url . '" data-url="' . $url . '" data-title="' . $dialog_title . '" data-width="82%" data-height="82%" data-dbx-tooltip="' . dbx()->esc($this->get_fd_message('install_module')) . '">'
         . '<i class="bi bi-database-gear"></i> ' . dbx()->esc($this->get_fd_message('install_module')) . '</a>';
   }

   private function render_active_toggle_button(array $record): string {
      $modul = dbx()->esc((string)($record['xmodul'] ?? ''));
      $url = dbx()->esc((string)($record['active_toggle_url'] ?? ''));
      if ($modul === '' || $url === '') {
         return '';
      }
      $active = (string)($record['active'] ?? '1') === '1';
      $btn_class = $active ? 'btn-success' : 'btn-outline-secondary';
      $icon = $active ? 'bi-toggle-on' : 'bi-toggle-off';
      $label = $this->get_fd_message($active ? 'active' : 'inactive');
      $state = $active ? '1' : '0';

      return '<button type="button" class="btn ' . $btn_class . ' dbx-module-active-toggle" data-modul="' . $modul
         . '" data-active="' . $state . '" data-toggle-url="' . $url . '" data-dbx-tooltip="' . dbx()->esc($this->get_fd_message('toggle_module')) . '">'
         . '<i class="bi ' . $icon . '"></i> ' . dbx()->esc($label) . '</button>';
   }

   private function render_delete_button(array $record): string {
      if ((string)($record['can_delete'] ?? '0') !== '1') {
         return '';
      }
      $modul = dbx()->esc((string)($record['xmodul'] ?? ''));
      $title = dbx()->esc((string)($record['title'] ?? ''));
      $url = dbx()->esc((string)($record['delete_url'] ?? ''));
      if ($modul === '' || $url === '') {
         return '';
      }

      return '<button type="button" class="btn btn-outline-danger dbx-module-delete-btn" data-modul="' . $modul
         . '" data-delete-url="' . $url . '" data-title="' . $title . '" data-dbx-tooltip="' . dbx()->esc($this->get_fd_message('delete_module')) . '">'
         . '<i class="bi bi-trash"></i> ' . dbx()->esc($this->get_fd_message('delete_button')) . '</button>';
   }

   private function render_access_inline(array $record): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $groups = $record['groups'] ?? array();
      if (!is_array($groups)) {
         $groups = array();
      }
      $selected = array_flip($groups);
      $options = '';
      foreach ($this->registry->group_options() as $value => $label) {
         $sel = isset($selected[$value]) ? ' selected' : '';
         $options .= '<option value="' . dbx()->esc($value) . '"' . $sel . '>' . dbx()->esc($this->localized_group_label((string)$value, (string)$label)) . '</option>';
      }

      $data = array(
         'xmodul'              => dbx()->esc((string)($record['xmodul'] ?? '')),
         'access_save_url'     => dbx()->esc((string)($record['access_save_url'] ?? '')),
         'groups_options_html' => $options,
      );

      return $tpl->get_tpl('dbxAdmin|module-card-access-inline', $data);
   }

   private function render_groups(array $record): string {
      $groups = $record['groups'] ?? array();
      if (!is_array($groups)) {
         $groups = array();
      }

      $labels = $this->registry->group_options();
      $html = '';
      foreach ($groups as $group) {
         $group = trim((string)$group);
         if ($group === '') {
            continue;
         }
         $label = $this->localized_group_label($group, (string)($labels[$group] ?? $group));
         $html .= '<span class="badge rounded-pill text-bg-light border">' . dbx()->esc($label) . '</span>';
      }

      if ($html === '') {
         $html = '<span class="text-muted small">' . dbx()->esc($this->get_fd_message('no_group')) . '</span>';
      }

      return $html;
   }

   private function render_dd_list(array $record): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $items = $record['dd_items'] ?? array();
      if (!is_array($items) || !$items) {
         return $tpl->get_tpl('dbxAdmin|module-card-dd-empty', $record);
      }

      $gallery = '';
      $select_id = (string)($record['dd_select_id'] ?? '');
      foreach ($items as $i => $item) {
         if (!is_array($item)) {
            continue;
         }
         $url = dbx()->esc((string)($item['edit_url'] ?? ''));
         $title = dbx()->esc($this->format_fd_message('edit_dd_title', array(
            'module' => (string)($record['xmodul'] ?? ''),
            'dd' => (string)($item['label'] ?? ''),
         )));
         $label = dbx()->esc((string)($item['label'] ?? ''));
         $active = ($i === 0) ? ' is-active' : '';
         $stripe = ($i % 2 === 0) ? ' odd' : ' even';
         $gallery .= '<button type="button" class="dbx-module-dd-item' . $active . $stripe . '" role="option"'
            . ' data-edit-url="' . $url . '" data-edit-title="' . $title . '"'
            . ' aria-selected="' . ($i === 0 ? 'true' : 'false') . '" data-dbx-tooltip="' . $title . '">'
            . '<span class="dbx-module-dd-item-name">' . $label . '</span>'
            . '</button>';
      }

      $record['dd_gallery_html'] = $gallery;
      return $tpl->get_tpl('dbxAdmin|module-card-dd', $record);
   }

   private function localized_group_label(string $value, string $fallback): string {
      $message_keys = array(
         'admin' => 'group_admin',
         'guest' => 'group_guest',
         'member' => 'group_member',
         '*' => 'group_all',
      );
      return isset($message_keys[$value]) ? $this->get_fd_message($message_keys[$value]) : $fallback;
   }
}
