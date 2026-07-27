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

   private function moduleBriefing() {
      return dbx()->get_include_obj('dbxKiModuleBriefingService', 'dbxKi');
   }

   private function design() {
      return dbx()->get_include_obj('dbxKiDesignService', 'dbxKi');
   }

   private function help() {
      return dbx()->get_include_obj('dbxKiHelp', 'dbxKi');
   }

   private function provision() {
      require_once __DIR__ . '/include/dbxKiCmsHelpProvision.class.php';
      return dbxKiCmsHelpProvision::provision();
   }

   private function html_start(): string {
      require_once __DIR__ . '/include/dbxKiCmsHelpProvision.class.php';
      dbxKiCmsHelpProvision::run();
      $anleitungUrl = dbxKiCmsHelpProvision::pageUrl();
      $briefing = '?dbx_modul=dbxKi&dbx_run1=briefing';
      $bundle = '?dbx_modul=dbxKi&dbx_run1=bundle';
      $design = '?dbx_modul=dbxKi&dbx_run1=briefing_design';
      $syncAll = '?dbx_modul=dbxKi&dbx_run1=translation_sync_all';
      $api = '?dbx_modul=dbxKi&dbx_run1=api';

      return '<div class="container py-4">'
         . '<h1>dbxKi</h1>'
         . '<p>Formular-basierte KI-Auftraege fuer das Content-CMS.</p>'
         . '<div class="d-flex flex-wrap gap-2 mb-3">'
         . '<a class="btn btn-primary" href="' . htmlspecialchars($briefing, ENT_QUOTES, 'UTF-8') . '">KI-Auftrag starten</a>'
         . '<a class="btn btn-outline-primary" href="' . htmlspecialchars($syncAll, ENT_QUOTES, 'UTF-8') . '">Sprache komplett uebersetzen</a>'
         . '<a class="btn btn-outline-primary" href="' . htmlspecialchars($bundle, ENT_QUOTES, 'UTF-8') . '">Bundle importieren</a>'
         . '<a class="btn btn-outline-primary" href="' . htmlspecialchars($design, ENT_QUOTES, 'UTF-8') . '">Design entwickeln</a>'
         . '<a class="btn btn-outline-secondary" href="' . htmlspecialchars($anleitungUrl, ENT_QUOTES, 'UTF-8') . '">Anleitung</a>'
         . '</div>'
         . '<p class="small text-muted">Ablauf: Formular → ZIP an ChatGPT → fertige ZIP importieren und ausfuehren.</p>'
         . '<p><strong>API:</strong> <code>' . htmlspecialchars($api, ENT_QUOTES, 'UTF-8') . '</code></p>'
         . '</div>';
   }

   private function esc($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function language_options(string $selected): string {
      $html = '';
      foreach (\dbx\dbxContent\dbxContentLngSync::accessibleLngs() as $lng) {
         $sel = $lng === $selected ? ' selected' : '';
         $html .= '<option value="' . $this->esc($lng) . '"' . $sel . '>' . $this->esc(strtoupper($lng)) . '</option>';
      }
      return $html;
   }

   private function target_language_checkboxes(string $sourceLng, array $selected): string {
      $html = '';
      foreach (\dbx\dbxContent\dbxContentLngSync::accessibleLngs() as $lng) {
         if ($lng === $sourceLng) {
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
      $sourceLng = strtolower(trim((string)dbx()->get_request_var('source_lng', \dbx\dbxContent\dbxContentLngSync::masterLng(), '*')));
      $targets = dbx()->get_request_var('target_lngs', array(), '*');
      if (is_string($targets)) {
         $targets = array($targets);
      }
      if (!is_array($targets)) {
         $targets = array();
      }
      return array(
         'source_lng' => $sourceLng,
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
      $form->_action = '?dbx_modul=dbxKi&dbx_run1=translation_sync_all';
      $form->_msg_info = '';
      foreach ($replacements as $key => $value) {
         $form->add_rep((string)$key, $value);
      }
      return $form;
   }

   private function render_translation_sync_all(): string {
      $params = $this->translation_sync_params_from_request();
      $form = $this->translation_sync_form();
      $hasPost = array_key_exists('sync_mode', $_POST);
      $hasSubmitted = (bool)$form->submit();
      if (!$hasSubmitted && !count($params['target_lngs'])) {
         foreach (\dbx\dbxContent\dbxContentLngSync::accessibleLngs() as $lng) {
            if ($lng !== $params['source_lng']) {
               $params['target_lngs'][] = $lng;
            }
         }
      }

      $mode = strtolower(trim((string)dbx()->get_request_var('sync_mode', '', '*')));
      $resultHtml = '';
      if ($hasPost && !$hasSubmitted) {
         $resultHtml = '<div class="alert alert-danger">Ungueltiger oder abgelaufener Formular-Token.</div>';
      } elseif ($hasSubmitted && ($mode === 'preview' || $mode === 'execute')) {
         try {
            $plan = $this->cms()->bundleBuildPlan('translation.sync_all', $params);
            if ($mode === 'execute') {
               $result = $this->cms()->bundleExecutePlan('translation.sync_all', $params, $plan);
               $resultHtml = $this->translation_sync_result_html($result, true);
            } else {
               $resultHtml = $this->translation_sync_plan_html($plan);
            }
         } catch (\Throwable $e) {
            $resultHtml = '<div class="alert alert-danger">' . $this->esc($e->getMessage()) . '</div>';
         }
      }

      $replacements = array_merge(array(
         'action' => '?dbx_modul=dbxKi&amp;dbx_run1=translation_sync_all',
         'language_options' => $this->language_options($params['source_lng']),
         'target_language_checkboxes' => $this->target_language_checkboxes($params['source_lng'], $params['target_lngs']),
         'root_folder_id' => $this->esc((string)$params['root_folder_id']),
         'update_existing_checked' => (int)$params['update_existing'] === 1 ? 'checked' : '',
         'skip_manual_checked' => (int)$params['skip_manual'] === 1 ? 'checked' : '',
         'copy_media_checked' => (int)$params['copy_media'] === 1 ? 'checked' : '',
         'replace_media_usage_checked' => (int)$params['replace_media_usage'] === 1 ? 'checked' : '',
         'result_html' => $resultHtml,
      ), $this->help()->moduleBarTemplateData(
         'translation_sync_all',
         '<a class="btn btn-outline-secondary btn-sm" href="?dbx_modul=dbxKi"><i class="bi bi-arrow-left"></i> Zurueck</a>'
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

   private function html_provision_result(array $result): string {
      $ok = empty($result['errors']);
      $class = $ok ? 'alert-success' : 'alert-danger';
      $msg = $ok ? 'CMS-Anleitung provisioniert.' : 'Provision fehlgeschlagen.';
      $pageUrl = dbxKiCmsHelpProvision::pageUrl();
      $back = '?dbx_modul=dbxKi';

      return '<div class="container py-4">'
         . '<div class="alert ' . $class . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>'
         . '<pre class="bg-light p-3 rounded">' . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</pre>'
         . '<div class="d-flex flex-wrap gap-2">'
         . '<a class="btn btn-primary" href="' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '">Zur Anleitung</a>'
         . '<a class="btn btn-outline-secondary" href="' . htmlspecialchars($back, ENT_QUOTES, 'UTF-8') . '">dbxKi Uebersicht</a>'
         . '</div></div>';
   }

   private function html_error(string $message, string $backUrl): string {
      return '<div class="container py-4"><div class="alert alert-danger">'
         . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
         . '</div><p><a class="btn btn-secondary" href="' . htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') . '">Zurueck</a></p></div>';
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
            return $this->briefing()->renderHub();

         case 'briefing_module':
            return $this->moduleBriefing()->renderBriefing();

         case 'briefing_module_export':
            $this->moduleBriefing()->handleExport();
            return '';

         case 'briefing_design':
            return $this->design()->renderBriefing();

         case 'briefing_design_export':
            try {
               $this->design()->handleExport();
            } catch (\Throwable $e) {
               dbx()->sys_msg('error', 'dbxKi', 'briefing_design_export', 'Design-Export fehlgeschlagen', $e->getMessage());
               return $this->html_error($e->getMessage(), '?dbx_modul=dbxKi&dbx_run1=briefing_design');
            }
            return '';

         case 'design_bundle_import':
            return $this->design()->handleImport();

         case 'design_bundle_apply':
            return $this->design()->handleApply();

         case 'module_bundle':
            return $this->moduleBriefing()->renderBundleStart();

         case 'module_bundle_import':
            return $this->moduleBriefing()->handleBundleImport();

         case 'module_api':
            $this->moduleBriefing()->handleApi();
            return '';

         case 'briefing_page_create':
            return $this->briefing()->renderPageCreateForm();

         case 'briefing_page_update':
            return $this->briefing()->renderPageUpdateForm();

         case 'briefing_page_update_preview':
            $this->briefing()->handlePageUpdatePreviewJson();
            return '';

         case 'briefing_page_translate':
            return $this->briefing()->renderPageTranslateForm();

         case 'translation_sync_all':
            return $this->render_translation_sync_all();

         case 'briefing_styles':
            return $this->briefing()->renderStylesAdmin();

         case 'briefing_styles_save':
            return $this->briefing()->handleStylesSave();

         case 'briefing_styles_reset':
            return $this->briefing()->handleStylesReset();

         case 'briefing_export':
            try {
               $this->briefing()->handleExport();
            } catch (\Throwable $e) {
               dbx()->sys_msg('error', 'dbxKi', 'briefing_export', 'Export fehlgeschlagen', $e->getMessage());
               $recipe = strtolower(trim((string) dbx()->get_request_var('recipe', '', '*')));
               return $this->html_error($e->getMessage(), $this->briefing()->exportBackUrl($recipe));
            }
            return '';

         case 'bundle':
            return $this->bundle()->renderStartPage();

         case 'bundle_import':
            return $this->bundle()->handleImport();

         case 'bundle_process':
            return $this->bundle()->handleProcess();

         case 'bundle_export':
            $this->bundle()->handleExport();
            return '';

         case 'bundle_describe':
            $this->bundle()->handleDescribeJson();
            return '';

         case 'help':
            return $this->help()->renderHelpWindow();

         case 'provision_anleitung':
            require_once __DIR__ . '/include/dbxKiCmsHelpProvision.class.php';
            $result = $this->provision();
            if (empty($result['errors'])) {
               $config = dbx()->get_config('dbxKi');
               if (!is_array($config)) {
                  $config = array();
               }
               $config[dbxKiCmsHelpProvision::CONFIG_KEY] = dbxKiCmsHelpProvision::PROVISION_VERSION;
               dbx()->set_config('dbxKi', $config);
            }
            return $this->html_provision_result($result);

         case 'start':
         default:
            require_once __DIR__ . '/include/dbxKiCmsHelpProvision.class.php';
            return $this->html_start();
      }
   }
}

?>
