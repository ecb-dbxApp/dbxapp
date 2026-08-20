<?php
namespace dbx\dbxKi;

require_once dirname(__DIR__) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

class dbxKi {

   private function cms() {
      return dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
   }

   private function bundle() {
      return dbx()->get_include_obj('dbxKiBundleService', 'dbxKi');
   }

   private function briefing() {
      return dbx()->get_include_obj('dbxKiBriefingService', 'dbxKi');
   }

   private function module_briefing() {
      return dbx()->get_include_obj('dbxKiModuleBriefingService', 'dbxKi');
   }

   private function design() {
      return dbx()->get_include_obj('dbxKiDesignService', 'dbxKi');
   }

   private function help() {
      return dbx()->get_include_obj('dbxKiHelp', 'dbxKi');
   }

   private $overview_texts;

   private function overview_texts() {
      if ($this->overview_texts) {
         return $this->overview_texts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->init('ki-overview-texts');
      $texts->set_field_definition('dbxKi|overview');
      $texts->load_fd_messages();
      $texts->set_form_help_enabled(false);
      $this->overview_texts = $texts;
      return $this->overview_texts;
   }

   private function overview_message(string $key): string {
      return $this->overview_texts()->get_fd_message($key);
   }

   private function overview_url(string $run1): string {
      return '?dbx_modul=dbxKi&dbx_run1=' . rawurlencode($run1);
   }

   private function html_start(): string {
      $keys = array(
         'hero_eyebrow', 'hero_title', 'hero_subtitle',
         'areas_title', 'areas_subtitle',
         'area_design_title', 'area_design_text', 'area_design_cta',
         'area_content_title', 'area_content_text', 'area_content_cta',
         'area_module_title', 'area_module_text', 'area_module_cta',
         'flow_title', 'flow_subtitle',
         'step1_title', 'step1_text', 'step2_title', 'step2_text',
         'step3_title', 'step3_text', 'step4_title', 'step4_text',
         'step5_title', 'step5_text', 'step6_title', 'step6_text',
         'quick_actions_title', 'action_bundle', 'action_translate', 'action_guide',
      );
      $data = array();
      foreach ($keys as $key) {
         $data[$key] = $this->overview_message($key);
      }

      $data['design_url'] = $this->esc($this->overview_url('briefing_design'));
      $data['content_url'] = $this->esc($this->overview_url('briefing_content'));
      $data['module_url'] = $this->esc($this->overview_url('briefing_module'));
      $data['bundle_url'] = $this->esc($this->overview_url('bundle'));
      $data['sync_url'] = $this->esc($this->overview_url('translation_sync_all'));
      $data['guide_url'] = $this->esc($this->help()->help_url('briefing_content'));

      return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxKi|ki-overview', $data);
   }

   private function esc($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function language_options(string $selected): string {
      $html = '';
      foreach (\dbx\dbxContent\dbxContentLngSync::accessible_lngs() as $lng) {
         $sel = $lng === $selected ? ' selected' : '';
         $html .= '<option value="' . $this->esc($lng) . '"' . $sel . '>' . $this->esc(strtoupper($lng)) . '</option>';
      }
      return $html;
   }

   private function target_language_checkboxes(string $source_lng, array $selected): string {
      $html = '';
      foreach (\dbx\dbxContent\dbxContentLngSync::accessible_lngs() as $lng) {
         if ($lng === $source_lng) {
            continue;
         }
         $checked = in_array($lng, $selected, true) ? ' checked' : '';
         $html .= '<label class="form-check form-check-inline">'
            . '<input class="form-check-input" type="checkbox" name="target_lngs[]" value="' . $this->esc($lng) . '"' . $checked . '>'
            . '<span class="form-check-label">' . $this->esc(strtoupper($lng)) . '</span>'
            . '</label>';
      }
      return $html !== '' ? $html : '<span class="text-muted">Keine weitere aktive Sprache vorhanden.</span>';
   }

   private function translation_sync_params_from_request(): array {
      $source_lng = strtolower(trim((string)dbx()->get_request_var('source_lng', \dbx\dbxContent\dbxContentLngSync::master_lng(), '*')));
      $targets = dbx()->get_request_var('target_lngs', array(), '*');
      if (is_string($targets)) {
         $targets = array($targets);
      }
      if (!is_array($targets)) {
         $targets = array();
      }
      return array(
         'source_lng' => $source_lng,
         'target_lngs' => array_values(array_filter(array_map(static function($lng) {
            return strtolower(trim((string)$lng));
         }, $targets))),
         'root_folder_id' => max(0, (int)dbx()->get_request_var('root_folder_id', 0, '*')),
         'update_existing' => !array_key_exists('sync_mode', $_REQUEST) || dbx()->get_request_var('update_existing', '0', '*') === '1' ? 1 : 0,
         'skip_manual' => dbx()->get_request_var('skip_manual', '0', '*') === '1' ? 1 : 0,
         'copy_media' => !array_key_exists('sync_mode', $_REQUEST) || dbx()->get_request_var('copy_media', '0', '*') === '1' ? 1 : 0,
         'replace_media_usage' => dbx()->get_request_var('replace_media_usage', '0', '*') === '1' ? 1 : 0,
      );
   }

   /**
    * Erzeugt das zentral geschuetzte Formular fuer die Komplettuebersetzung.
    *
    * Das Formular wird sowohl fuer die Anzeige als auch fuer die Verarbeitung
    * mit derselben ID initialisiert. Dadurch akzeptiert dbxKi weder Vorschau
    * noch Ausfuehrung ohne den von dbxForm verwalteten Security-Token.
    *
    * @param array $replacements Templatewerte fuer die Anzeige
    *
    * @return \dbxForm
    */
   private function translation_sync_form(array $replacements = array()) {
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('ki-translation-sync-all', 'ki-translation-sync-all');
      $form->set_action('?dbx_modul=dbxKi&dbx_run1=translation_sync_all');
      $form->_msg_info = '';
      foreach ($replacements as $key => $value) {
         $form->add_rep((string)$key, $value);
      }
      return $form;
   }

   private function render_translation_sync_all(): string {
      $params = $this->translation_sync_params_from_request();
      $form = $this->translation_sync_form();
      $has_post = array_key_exists('sync_mode', $_POST);
      $has_submitted = (bool)$form->submit();
      if (!$has_submitted && !count($params['target_lngs'])) {
         foreach (\dbx\dbxContent\dbxContentLngSync::accessible_lngs() as $lng) {
            if ($lng !== $params['source_lng']) {
               $params['target_lngs'][] = $lng;
            }
         }
      }

      $mode = strtolower(trim((string)dbx()->get_request_var('sync_mode', '', '*')));
      $result_html = '';
      if ($has_post && !$has_submitted) {
         $result_html = '<div class="alert alert-danger">Ungueltiger oder abgelaufener Formular-Token.</div>';
      } elseif ($has_submitted && ($mode === 'preview' || $mode === 'execute')) {
         try {
            $plan = $this->cms()->bundle_build_plan('translation.sync_all', $params);
            if ($mode === 'execute') {
               $result = $this->cms()->bundle_execute_plan('translation.sync_all', $params, $plan);
               $result_html = $this->translation_sync_result_html($result, true);
            } else {
               $result_html = $this->translation_sync_plan_html($plan);
            }
         } catch (\Throwable $e) {
            $result_html = '<div class="alert alert-danger">' . $this->esc($e->getMessage()) . '</div>';
         }
      }

      $root_folder_id = (int)$params['root_folder_id'];
      $replacements = array_merge(array(
         'action' => '?dbx_modul=dbxKi&amp;dbx_run1=translation_sync_all',
         'tree_url' => $this->esc('?dbx_modul=dbxContent_admin&dbx_run1=cms_tree'),
         'source_lng' => $this->esc($params['source_lng']),
         'language_options' => $this->language_options($params['source_lng']),
         'target_language_checkboxes' => $this->target_language_checkboxes($params['source_lng'], $params['target_lngs']),
         'root_folder_id' => $this->esc((string)$root_folder_id),
         'root_folder_label' => $this->esc($root_folder_id > 0 ? ('Ordner #' . $root_folder_id) : 'Komplette Sprache (kein Ordner gewählt)'),
         'update_existing_checked' => (int)$params['update_existing'] === 1 ? 'checked' : '',
         'skip_manual_checked' => (int)$params['skip_manual'] === 1 ? 'checked' : '',
         'copy_media_checked' => (int)$params['copy_media'] === 1 ? 'checked' : '',
         'replace_media_usage_checked' => (int)$params['replace_media_usage'] === 1 ? 'checked' : '',
         'result_html' => $result_html,
      ), $this->help()->module_bar_template_data(
         'translation_sync_all',
         '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxKi"><i class="bi bi-arrow-left"></i> Zurück</a>'
      ));

      foreach ($replacements as $key => $value) {
         $form->add_rep((string)$key, $value);
      }
      return $form->run();
   }

   private function translation_sync_plan_html(array $plan): string {
      $counts = is_array($plan['counts'] ?? null) ? $plan['counts'] : array();
      $targets = is_array($plan['target_lngs'] ?? null) ? implode(', ', array_map('strtoupper', $plan['target_lngs'])) : '';
      return '<div class="alert alert-info">'
         . '<strong>Vorschau:</strong> '
         . $this->esc((string)($counts['folders'] ?? 0)) . ' Ordner und '
         . $this->esc((string)($counts['pages'] ?? 0)) . ' Seiten von '
         . $this->esc(strtoupper((string)($plan['source_lng'] ?? ''))) . ' nach '
         . $this->esc($targets) . '. Provider: '
         . $this->esc((string)($plan['provider'] ?? '')) . '.'
         . '</div>';
   }

   private function translation_sync_result_html(array $result, bool $executed): string {
      $folders = is_array($result['folders'] ?? null) ? $result['folders'] : array();
      $pages = is_array($result['pages'] ?? null) ? $result['pages'] : array();
      $errors = is_array($result['errors'] ?? null) ? $result['errors'] : array();
      $warnings = is_array($result['warnings'] ?? null) ? $result['warnings'] : array();
      $class = count($errors) ? 'alert-warning' : 'alert-success';
      $html = '<div class="alert ' . $class . '"><strong>' . ($executed ? 'Ausgefuehrt.' : 'Ergebnis.') . '</strong> '
         . 'Ordner: ' . count($folders['created'] ?? array()) . ' neu, ' . count($folders['updated'] ?? array()) . ' aktualisiert. '
         . 'Seiten: ' . count($pages['created'] ?? array()) . ' neu, ' . count($pages['updated'] ?? array()) . ' aktualisiert. '
         . 'Medienzuordnungen: ' . (int)($result['media_copied'] ?? 0) . '.</div>';
      if (count($errors)) {
         $html .= '<div class="card p-3 mb-3"><h2 class="h6">Fehler</h2><ul class="mb-0">';
         foreach ($errors as $error) {
            $html .= '<li>' . $this->esc($error) . '</li>';
         }
         $html .= '</ul></div>';
      }
      if (count($warnings)) {
         $html .= '<div class="card p-3 mb-3"><h2 class="h6">Hinweise</h2><ul class="mb-0">';
         foreach ($warnings as $warning) {
            $msg = is_array($warning) ? (($warning['message'] ?? '') . ' ' . ($warning['lng'] ?? '')) : (string)$warning;
            $html .= '<li>' . $this->esc(trim($msg)) . '</li>';
         }
         $html .= '</ul></div>';
      }
      return $html;
   }

   private function html_error(string $message, string $back_url): string {
      return '<div class="container py-4"><div class="alert alert-danger">'
         . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
         . '</div><p><a class="btn btn-secondary" href="' . htmlspecialchars($back_url, ENT_QUOTES, 'UTF-8') . '">Zurueck</a></p></div>';
   }

   public function run($action = '') {
      if ($action === '') {
         $action = dbx()->get_modul_var('dbx_run1', 'start', 'parameter');
      }

      switch ($action) {
         case 'api':
         case 'describe':
         case 'preview':
         case 'execute':
            $this->cms()->handle($action);
            return '';

         case 'briefing':
            return $this->briefing()->render_hub();

         case 'briefing_content':
            return $this->briefing()->render_content_hub();

         case 'briefing_module':
            return $this->briefing()->render_module_hub();

         case 'briefing_module_edit':
            return $this->module_briefing()->render_briefing();

         case 'briefing_module_export':
            $this->module_briefing()->handle_export();
            return '';

         case 'briefing_design':
            return $this->briefing()->render_design_hub();

         case 'briefing_design_edit':
            return $this->design()->render_briefing();

         case 'briefing_design_export':
            try {
               $this->design()->handle_export();
            } catch (\Throwable $e) {
               dbx()->sys_msg('error', 'dbxKi', 'briefing_design_export', 'Design-Export fehlgeschlagen', $e->getMessage());
               return $this->html_error($e->getMessage(), '?dbx_modul=dbxKi&dbx_run1=briefing_design_edit');
            }
            return '';

         case 'design_bundle_import':
            return $this->design()->handle_import();

         case 'design_bundle_discard':
            $this->design()->handle_discard();
            return '';

         case 'design_bundle_apply':
            return $this->design()->handle_apply();

         case 'module_bundle':
            return $this->module_briefing()->render_bundle_start();

         case 'module_bundle_import':
            return $this->module_briefing()->handle_bundle_import();

         case 'module_bundle_discard':
            $this->module_briefing()->handle_discard_preview();
            return '';

         case 'module_api':
            $this->module_briefing()->handle_api();
            return '';

         case 'briefing_page_create':
            return $this->briefing()->render_page_create_form();

         case 'briefing_page_update':
            return $this->briefing()->render_page_update_form();

         case 'briefing_page_update_preview':
            $this->briefing()->handle_page_update_preview_json();
            return '';

         case 'briefing_page_translate':
            return $this->briefing()->render_page_translate_form();

         case 'translation_sync_all':
            return $this->render_translation_sync_all();

         case 'briefing_styles':
            return $this->briefing()->render_styles_admin();

         case 'briefing_styles_save':
            return $this->briefing()->handle_styles_save();

         case 'briefing_styles_reset':
            return $this->briefing()->handle_styles_reset();

         case 'briefing_export':
            try {
               $this->briefing()->handle_export();
            } catch (\Throwable $e) {
               dbx()->sys_msg('error', 'dbxKi', 'briefing_export', 'Export fehlgeschlagen', $e->getMessage());
               $recipe = strtolower(trim((string) dbx()->get_request_var('recipe', '', '*')));
               return $this->html_error($e->getMessage(), $this->briefing()->export_back_url($recipe));
            }
            return '';

         case 'bundle':
            return $this->bundle()->render_start_page();

         case 'bundle_import':
            return $this->bundle()->handle_import();

         case 'bundle_discard':
            $this->bundle()->handle_discard();
            return '';

         case 'bundle_process':
            return $this->bundle()->handle_process();

         case 'bundle_export':
            $this->bundle()->handle_export();
            return '';

         case 'bundle_describe':
            $this->bundle()->handle_describe_json();
            return '';

         case 'help':
            return $this->help()->render_help_window();

         case 'start':
         default:
            return $this->html_start();
      }
   }
}

?>
