<?php
namespace dbx\dbxDesign_admin;

/**
 * UI-Controller für Designübersicht und geführte Design-Erstellung.
 *
 * Der Controller nutzt dbxTPL für alle Seiten und dbxForm für sämtliche
 * Schreibvorgänge. Die Dateiarbeit liegt vollständig in dbxDesignService.
 */
class dbxDesignAdmin {

   /** Dateibasierte Design-Domainlogik. */
   private $design_service;
   private $texts;

   public function __construct() {
      $this->design_service = dbx()->get_include_obj('dbxDesignService', 'dbxDesign_admin');
   }

   private function url(string $run1 = 'list', array $params = array()): string {
      $params = array_filter($params, static fn($value): bool => $value !== '');
      return dbx()->append_url_params('?dbx_modul=dbxDesign_admin&dbx_run1=' . rawurlencode($run1), $params);
   }

   private function h($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('design-admin-texts');
      $texts->set_field_definition('dbxDesign_admin|design-admin');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->texts = $texts;
      return $this->texts;
   }

   private function design_options(): array {
      $items = array();
      foreach ($this->design_service->list_designs() as $name => $design) {
         $items[$name] = $design['title'] . ' (' . $name . ')';
      }
      return $items;
   }

   /**
    * Rendert die Designübersicht aus den dateibasierten Design-Metadaten.
    */
   public function render_list(): string {
      $texts = $this->texts();
      $cards = '';
      $config = dbx()->get_cfg('dbx');
      $default_user = is_array($config) ? (string)($config['default_design_user'] ?? '') : '';
      $default_admin = is_array($config) ? (string)($config['default_design_admin'] ?? '') : '';
      foreach ($this->design_service->list_designs() as $name => $design) {
         $badges = '';
         if ($name === $default_user) {
            $badges .= '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('badge_frontend_default')) . '</span>';
         }
         if ($name === $default_admin) {
            $badges .= '<span class="badge text-bg-dark">' . $this->h($texts->get_fd_message('badge_admin_default')) . '</span>';
         }
         if (!empty($design['managed'])) {
            $badges .= '<span class="badge text-bg-success">' . $this->h($texts->get_fd_message('badge_wizard')) . '</span>';
         }
         if (empty($design['valid'])) {
            $badges .= '<span class="badge text-bg-warning">' . $this->h($texts->get_fd_message('badge_check')) . '</span>';
         }
         $cards .= dbx()->get_system_obj('dbxTPL')->get_tpl('dbxDesign_admin|design-card', array(
            'name' => $this->h($name),
            'title' => $this->h($design['title']),
            'description' => $this->h($design['description'] !== '' ? $design['description'] : $texts->get_fd_message('description_fallback')),
            'layout' => $this->h($design['layout']),
            'badges' => $badges,
            'preview_url' => $this->h('?dbx_design=' . rawurlencode($name)),
            'wizard_url' => $this->h($this->url('wizard', array('source_design' => $name))),
            'ki_url' => $this->h('?dbx_modul=dbxKi&dbx_run1=briefing_design_edit&design=' . rawurlencode($name)),
            'download_url' => $this->h($this->url('download', array('design' => $name))),
            'action_personalize' => $this->h($texts->get_fd_message('action_personalize')),
            'action_ai' => $this->h($texts->get_fd_message('action_ai')),
            'action_preview' => $this->h($texts->get_fd_message('action_preview')),
            'action_backup' => $this->h($texts->get_fd_message('action_backup')),
         ));
      }

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxDesign_admin|design-list', array(
         'cards' => $cards,
         'wizard_url' => $this->h($this->url('wizard')),
         'ki_url' => $this->h('?dbx_modul=dbxKi&dbx_run1=briefing_design_edit'),
         'bar_title' => $texts->get_fd_message('list_title'),
         'bar_subtitle' => $texts->get_fd_message('list_subtitle'),
         'bar_icon' => 'bi-palette',
         'bar_actions' => '<a class="btn btn-outline-primary btn-sm" href="' . $this->h('?dbx_modul=dbxKi&dbx_run1=briefing_design_edit') . '"><i class="bi bi-stars"></i> ' . $this->h($texts->get_fd_message('action_ai_design')) . '</a> '
            . '<a class="btn btn-primary btn-sm" href="' . $this->h($this->url('wizard')) . '"><i class="bi bi-magic"></i> ' . $this->h($texts->get_fd_message('action_wizard')) . '</a>',
         'bar_class' => 'dbx-bar--module',
         'bar_title_class' => 'dbx-bar-title',
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_middle' => '',
         'bar_extra' => '',
         'bar_actions_class' => 'dbx-bar-actions',
      ));
   }

   /**
    * Zeigt und verarbeitet den dbxForm-basierten Design-Wizard.
    */
   public function render_wizard(): string {
      $texts = $this->texts();
      $source = $this->design_service->normalize_name((string)dbx()->get_request_var('source_design', 'dbxapp', 'parameter'));
      if (!isset($this->design_service->list_designs()[$source])) {
         $source = 'dbxapp';
      }
      $defaults = $this->design_service->defaults($source);
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('design-admin-wizard', 'design-wizard');
      $form->set_field_definition('dbxDesign_admin|design-admin');
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $form->set_action($this->url('wizard'));
      $form->_msg_info = $texts->get_fd_message('wizard_info');
      $form->merge_data($defaults);

      $form->add_rep('list_url', $this->h($this->url('list')));
      $form->add_rep('ki_url', $this->h('?dbx_modul=dbxKi&dbx_run1=briefing_design_edit&design=' . rawurlencode($source)));
      $form->add_rep('bar_title', $texts->get_fd_message('wizard_title'));
      $form->add_rep('bar_subtitle', $texts->get_fd_message('wizard_subtitle'));
      $form->add_rep('bar_icon', 'bi-magic');
      $form->add_rep('bar_actions', '<a class="btn btn-outline-primary btn-sm" href="' . $this->h('?dbx_modul=dbxKi&dbx_run1=briefing_design_edit&design=' . rawurlencode($source)) . '"><i class="bi bi-stars"></i> ' . $this->h($texts->get_fd_message('action_with_ai')) . '</a> '
         . '<a class="btn btn-outline-secondary btn-sm" href="' . $this->h($this->url('list')) . '"><i class="bi bi-arrow-left"></i> ' . $this->h($texts->get_fd_message('action_designs')) . '</a>');
      foreach (array(
         'bar_class' => 'dbx-bar--module',
         'bar_title_class' => 'dbx-bar-title',
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_middle' => '',
         'bar_extra' => '',
         'bar_actions_class' => 'dbx-bar-actions',
      ) as $key => $value) {
         $form->add_rep($key, $value);
      }
      $form->add_fld('source_design', 'select-single-label', label: $texts->get_fd_message('label_source_design'), rules: 'parameter|max=63', options: $this->design_options(), dd: '');
      $form->add_fld('target_design', 'text-label', label: $texts->get_fd_message('label_target_design'), rules: 'parameter+_-|min=2|max=63', placeholder: $texts->get_fd_message('placeholder_target_design'), tooltip: $texts->get_fd_message('tooltip_target_design'), dd: '');
      $form->add_fld('title', 'text-label', label: $texts->get_fd_message('label_title'), rules: 'varchar|max=120', placeholder: $texts->get_fd_message('placeholder_title'), dd: '');
      $form->add_fld('description', 'textarea-label', label: $texts->get_fd_message('label_description'), rules: 'varchar|max=500', data: 'rows=2', dd: '');
      $form->add_fld('layout', 'select-single-label', label: $texts->get_fd_message('label_layout'), rules: 'parameter', options: array(
         'top' => $texts->get_fd_message('layout_top'),
         'sidebar' => $texts->get_fd_message('layout_sidebar'),
         'hybrid' => $texts->get_fd_message('layout_hybrid'),
      ), dd: '');
      $form->add_fld('menu_style', 'select-single-label', label: $texts->get_fd_message('label_menu_style'), rules: 'parameter', options: array(
         'tabs' => $texts->get_fd_message('menu_tabs'),
         'pills' => $texts->get_fd_message('menu_pills'),
         'compact' => $texts->get_fd_message('menu_compact'),
      ), dd: '');
      $form->add_fld('content_width', 'select-single-label', label: $texts->get_fd_message('label_content_width'), rules: 'parameter', options: array(
         'compact' => $texts->get_fd_message('width_compact'),
         'wide' => $texts->get_fd_message('width_wide'),
         'full' => $texts->get_fd_message('width_full'),
      ), dd: '');
      $form->add_fld('footer_mode', 'select-single-label', label: $texts->get_fd_message('label_footer_mode'), rules: 'parameter', options: array(
         'full' => $texts->get_fd_message('footer_full'),
         'minimal' => $texts->get_fd_message('footer_minimal'),
         'none' => $texts->get_fd_message('footer_none'),
      ), dd: '');
      $form->add_fld('brand_name', 'text-label', label: $texts->get_fd_message('label_brand_name'), rules: 'varchar|min=1|max=120', dd: '');
      $form->add_fld('tagline', 'text-label', label: $texts->get_fd_message('label_tagline'), rules: 'varchar|max=160', dd: '');
      $form->add_fld('logo_mode', 'select-single-label', label: $texts->get_fd_message('label_logo_mode'), rules: 'parameter', options: array(
         'icon' => $texts->get_fd_message('logo_icon'),
         'none' => $texts->get_fd_message('logo_none'),
      ), dd: '');
      $form->add_fld('logo_icon', 'text-label', label: $texts->get_fd_message('label_logo_icon'), rules: 'parameter|max=50', placeholder: 'bi-stars', dd: '');
      $form->add_fld('primary_color', 'text-label', label: $texts->get_fd_message('label_primary_color'), rules: 'varchar|max=7', dd: '');
      $form->add_fld('secondary_color', 'text-label', label: $texts->get_fd_message('label_secondary_color'), rules: 'varchar|max=7', dd: '');
      $form->add_fld('accent_color', 'text-label', label: $texts->get_fd_message('label_accent_color'), rules: 'varchar|max=7', dd: '');
      $form->add_fld('background_color', 'text-label', label: $texts->get_fd_message('label_background_color'), rules: 'varchar|max=7', dd: '');
      $form->add_fld('surface_color', 'text-label', label: $texts->get_fd_message('label_surface_color'), rules: 'varchar|max=7', dd: '');
      $form->add_fld('text_color', 'text-label', label: $texts->get_fd_message('label_text_color'), rules: 'varchar|max=7', dd: '');
      $form->add_fld('font_family', 'select-single-label', label: $texts->get_fd_message('label_font_family'), rules: 'parameter', options: array(
         'system' => $texts->get_fd_message('font_system'),
         'modern' => $texts->get_fd_message('font_modern'),
         'editorial' => $texts->get_fd_message('font_editorial'),
         'rounded' => $texts->get_fd_message('font_rounded'),
      ), dd: '');
      $form->add_fld('radius', 'select-single-label', label: $texts->get_fd_message('label_radius'), rules: 'parameter', options: array(
         'square' => $texts->get_fd_message('radius_square'),
         'soft' => $texts->get_fd_message('radius_soft'),
         'round' => $texts->get_fd_message('radius_round'),
      ), dd: '');
      $form->add_fld('footer_text', 'text-label', label: $texts->get_fd_message('label_footer_text'), rules: 'varchar|max=180', dd: '');
      $form->add_fld('legal_links', 'checkbox-label', label: $texts->get_fd_message('label_legal_links'), rules: 'int', dd: '');
      $form->add_fld('set_default', 'checkbox-label', label: $texts->get_fd_message('label_set_default'), rules: 'int', dd: '');

      $result_html = '';
      $has_post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
      if ($form->submit()) {
         $input = $this->collect_form_input($form);
         try {
            $result = $this->design_service->create_from_wizard($input, is_array($_FILES['logo_file'] ?? null) ? $_FILES['logo_file'] : array());
            $form->_msg_success = $texts->get_fd_message('wizard_created');
            $result_html = dbx()->get_system_obj('dbxTPL')->get_tpl('dbxDesign_admin|design-result', array(
               'title' => $this->h($input['title'] !== '' ? $input['title'] : $result['name']),
               'name' => $this->h($result['name']),
               'preview_url' => $this->h('?dbx_design=' . rawurlencode($result['name'])),
               'list_url' => $this->h($this->url('list')),
               'ki_url' => $this->h('?dbx_modul=dbxKi&dbx_run1=briefing_design_edit&design=' . rawurlencode($result['name']) . '&mode=update'),
               'default_note' => !empty($result['default_changed']) ? '<span class="badge text-bg-primary">' . $this->h($texts->get_fd_message('wizard_default_set')) . '</span>' : '',
            ));
         } catch (\Throwable $e) {
            $form->_msg_error = $e->getMessage();
         }
      } elseif ($has_post) {
         $form->_msg_error = $texts->get_fd_message('wizard_validation_error');
      }

      return $form->run() . $result_html;
   }

   /**
    * Sendet ein unverändertes ZIP-Backup eines Designs.
    */
   public function send_download(): void {
      $name = $this->design_service->normalize_name((string)dbx()->get_request_var('design', '', 'parameter'));
      $file = $this->design_service->backup_design($name);
      header('Content-Type: application/zip');
      header('Content-Disposition: attachment; filename="dbx-design-' . $name . '-' . date('Ymd-His') . '.zip"');
      header('Content-Length: ' . filesize($file));
      readfile($file);
      exit;
   }

   private function collect_form_input($form): array {
      $rules = array(
         'source_design' => 'parameter|max=63',
         'target_design' => 'parameter+_-|min=2|max=63',
         'title' => 'varchar|max=120',
         'description' => 'varchar|max=500',
         'layout' => 'parameter',
         'menu_style' => 'parameter',
         'content_width' => 'parameter',
         'footer_mode' => 'parameter',
         'brand_name' => 'varchar|max=120',
         'tagline' => 'varchar|max=160',
         'logo_mode' => 'parameter',
         'logo_icon' => 'parameter|max=50',
         'primary_color' => 'varchar|max=7',
         'secondary_color' => 'varchar|max=7',
         'accent_color' => 'varchar|max=7',
         'background_color' => 'varchar|max=7',
         'surface_color' => 'varchar|max=7',
         'text_color' => 'varchar|max=7',
         'font_family' => 'parameter',
         'radius' => 'parameter',
         'footer_text' => 'varchar|max=180',
         'legal_links' => 'int',
         'set_default' => 'int',
      );
      $out = array();
      foreach ($rules as $name => $rule) {
         $out[$name] = $form->get_fld_value($name, '', $rule);
      }
      return $out;
   }

   /**
    * Modulrouter. Navigation bleibt GET-basiert; nur der Wizard schreibt per
    * dbxForm-POST.
    */
   public function run(): string {
      $run1 = dbx()->get_modul_var('dbx_run1', 'list', 'parameter');
      switch ($run1) {
         case 'wizard':
         case 'personalize':
            return $this->render_wizard();
         case 'download':
            $this->send_download();
            return '';
         case 'list':
         case 'start':
         default:
            return $this->render_list();
      }
   }
}
?>
