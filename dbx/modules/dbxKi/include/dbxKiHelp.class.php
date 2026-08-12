<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxKiCmsHelpProvision.class.php';

class dbxKiHelp {

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function h($value) {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }

   public function screens(): array {
      return array(
         'briefing' => array(
            'title' => 'KI-Auftrag',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-ui-checks',
            'subtitle' => 'Formular, Download, Ablauf',
         ),
         'briefing_page_create' => array(
            'title' => 'Neue Seite — Formular',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-file-earmark-plus',
            'subtitle' => 'Auftrag fuer neue Content-Seite',
         ),
         'briefing_page_update' => array(
            'title' => 'Seite aendern — Formular',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-pencil-square',
            'subtitle' => 'Auftrag fuer Seiten-Update',
         ),
         'briefing_page_translate' => array(
            'title' => 'Uebersetzung — Formular',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-translate',
            'subtitle' => 'Auftrag fuer Seiten-Uebersetzung',
         ),
         'briefing_content' => array(
            'title' => 'Content-KI',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-file-earmark-richtext',
            'subtitle' => 'Neue Seite, Änderung oder Übersetzung auswählen',
         ),
         'translation_sync_all' => array(
            'title' => 'Sprache komplett übersetzen',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-translate',
            'subtitle' => 'CMS-Struktur in Zielsprachen übertragen',
         ),
         'briefing_module' => array(
            'title' => 'Modul-KI',
            'help_title' => dbxKiCmsHelpProvision::title('module'),
            'help_topic' => 'module',
            'icon' => 'bi-stars',
            'subtitle' => 'Neues oder bestehendes Modul auswählen',
         ),
         'briefing_module_edit' => array(
            'title' => 'Modul-KI',
            'help_title' => dbxKiCmsHelpProvision::title('module'),
            'help_topic' => 'module',
            'icon' => 'bi-stars',
            'subtitle' => 'Auftrag fuer bestehendes Modul',
         ),
         'briefing_design' => array(
            'title' => 'Design-KI',
            'help_title' => dbxKiCmsHelpProvision::title('design'),
            'help_topic' => 'design',
            'icon' => 'bi-palette',
            'subtitle' => 'Neues oder bestehendes Design auswählen',
         ),
         'briefing_design_edit' => array(
            'title' => 'Design-KI',
            'help_title' => dbxKiCmsHelpProvision::title('design'),
            'help_topic' => 'design',
            'icon' => 'bi-palette',
            'subtitle' => 'Briefing, vollständiger Designkontext und sichere Antwort-ZIP',
         ),
         'briefing_styles' => array(
            'title' => 'Schreibstile',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-type',
            'subtitle' => 'Ton und Stil fuer KI-Auftraege',
         ),
         'module_bundle' => array(
            'title' => 'Modul-Antwort importieren',
            'help_title' => dbxKiCmsHelpProvision::title('module'),
            'help_topic' => 'module',
            'icon' => 'bi-upload',
            'subtitle' => 'Antwort-ZIP fuer Modul-KI',
         ),
         'module_bundle_preview' => array(
            'title' => 'Modul-Import',
            'help_title' => dbxKiCmsHelpProvision::title('module'),
            'help_topic' => 'module',
            'icon' => 'bi-check2-square',
            'subtitle' => 'Modul-Antwort ausfuehren',
         ),
         'design_bundle_import' => array(
            'title' => 'Design-Antwort importieren',
            'help_title' => dbxKiCmsHelpProvision::title('design'),
            'help_topic' => 'design',
            'icon' => 'bi-upload',
            'subtitle' => 'Antwort-ZIP fuer Design-KI',
         ),
         'design_bundle_apply' => array(
            'title' => 'Design-Import',
            'help_title' => dbxKiCmsHelpProvision::title('design'),
            'help_topic' => 'design',
            'icon' => 'bi-check2-square',
            'subtitle' => 'Design-Antwort pruefen und anwenden',
         ),
         'bundle' => array(
            'title' => 'Bundle importieren',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-box-arrow-in-down',
            'subtitle' => 'KI-Antwort ZIP hochladen',
         ),
         'bundle_preview' => array(
            'title' => 'KI-Import',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-eye',
            'subtitle' => 'Vorschau und Ausfuehrung',
         ),
         'bundle_process' => array(
            'title' => 'KI-Import',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-play-circle',
            'subtitle' => 'Bundle wird ausgefuehrt',
         ),
      );
   }

   public function resolveScreen(string $run1 = ''): string {
      if ($run1 === '') {
         $run1 = (string) dbx()->get_modul_var('dbx_run1', 'bundle', 'parameter');
      }
      $run1 = strtolower(trim($run1));
      $directScreens = array(
         'briefing', 'briefing_content', 'briefing_page_create', 'briefing_page_update',
         'briefing_page_translate', 'briefing_styles', 'briefing_module', 'briefing_module_edit',
         'briefing_design', 'briefing_design_edit', 'translation_sync_all',
         'design_bundle_import', 'design_bundle_apply',
      );
      if (in_array($run1, $directScreens, true)) {
         return $run1;
      }
      if ($run1 === 'module_bundle') {
         return 'module_bundle';
      }
      if ($run1 === 'module_bundle_import') {
         return 'module_bundle_preview';
      }
      if ($run1 === 'bundle_import') {
         return 'bundle_preview';
      }
      if ($run1 === 'bundle_process') {
         return 'bundle_process';
      }
      if ($run1 === 'bundle' || $run1 === 'start' || $run1 === '') {
         return 'bundle';
      }
      return 'bundle';
   }

   public function helpUrl(string $screen = ''): string {
      $screen = trim($screen);
      if ($screen === '') {
         $screen = $this->resolveScreen();
      }
      return '?dbx_modul=dbxKi&dbx_run1=help&screen=' . rawurlencode($screen);
   }

   public function button(string $screen = ''): string {
      $screen = trim($screen);
      if ($screen === '') {
         $screen = $this->resolveScreen();
      }
      $screens = $this->screens();
      if (!isset($screens[$screen])) {
         $screen = 'bundle';
      }
      $meta = $screens[$screen];
      return $this->tpl()->get_tpl('dbxAdmin|admin-help-button', array(
         'help_url' => $this->h($this->helpUrl($screen)),
         'help_title' => $this->h('Hilfe: ' . ($meta['help_title'] ?? $meta['title'] ?? $screen)),
      ));
   }

   public function moduleBarTemplateData(string $screen = '', string $actionsHtml = '', string $title = '', string $icon = '', string $subtitle = ''): array {
      if ($screen === '') {
         $screen = $this->resolveScreen();
      }
      $screens = $this->screens();
      if (!isset($screens[$screen])) {
         $screen = 'bundle';
      }
      $meta = $screens[$screen];

      if ($title === '') {
         $title = (string) ($meta['title'] ?? $screen);
      }
      if ($icon === '') {
         $icon = (string) ($meta['icon'] ?? 'bi-grid');
      }
      if ($subtitle === '') {
         $subtitle = (string) ($meta['subtitle'] ?? '');
      }

      return array(
         'bar_class' => 'dbx-bar--module',
         'bar_title_class' => 'dbx-bar-title',
         'bar_actions_class' => 'dbx-bar-actions',
         'bar_title' => $title,
         'bar_icon' => $icon,
         'bar_subtitle' => $subtitle,
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_actions' => (string) $actionsHtml,
         'bar_extra' => $this->button($screen),
         'bar_middle' => '',
      );
   }

   public function renderCmsHelpContent(string $topic = ''): string {
      if ($topic === '') {
         $topic = dbxKiCmsHelpProvision::DEFAULT_TOPIC;
      }
      dbxKiCmsHelpProvision::run();

      $permalink = dbxKiCmsHelpProvision::permalink($topic);
      $cid = $this->resolveHelpCid($permalink);

      if ($cid <= 0) {
         $result = dbxKiCmsHelpProvision::provision($topic);
         if (empty($result['errors'])) {
            dbxKiCmsHelpProvision::run();
            $cid = (int) ($result['page_id'] ?? 0);
            if ($cid <= 0) {
               $cid = $this->resolveHelpCid($permalink);
            }
         }
      }

      if ($cid <= 0) {
         return $this->tpl()->get_tpl('dbx|alert-info', array(
            'msg' => 'Hilfe-Seite noch nicht angelegt. Bitte '
               . '<a href="?dbx_modul=dbxKi&amp;dbx_run1=provision_anleitung">Anleitung provisionieren</a>.',
         ));
      }

      $contentObj = dbx()->get_include_obj('dbxContent_content', 'dbxContent');
      if (!is_object($contentObj) || !method_exists($contentObj, 'renderPage')) {
         return '';
      }

      return $contentObj->renderPage($cid, array(
         'admin_help' => true,
         'skip_hits' => true,
         'skip_cache' => true,
      ));
   }

   private function resolveHelpCid(string $permalink): int {
      $permalink = trim($permalink);
      if ($permalink === '') {
         return 0;
      }

      $contextHelp = dbx()->get_include_obj('dbxContentContextHelp', 'dbxContent');
      if (is_object($contextHelp) && method_exists($contextHelp, 'resolveCidByPermalink')) {
         $cid = (int) $contextHelp->resolveCidByPermalink($permalink);
         if ($cid > 0) {
            return $cid;
         }
      }

      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db)) {
         return 0;
      }

      $dd = dbxContentLng::ddContent();
      $rec = $db->select1($dd, array('permalink' => $permalink), 'id', 0);
      if (is_array($rec) && (int) ($rec['id'] ?? 0) > 0) {
         return (int) $rec['id'];
      }

      return 0;
   }

   public function renderHelpWindow(string $screen = ''): string {
      dbx()->set_system_var('dbx_page', 'window');

      if ($screen === '') {
         $screen = trim((string) dbx()->get_modul_var('screen', '', 'parameter'));
      }
      if ($screen === '') {
         $screen = $this->resolveScreen();
      }

      $screens = $this->screens();
      $topic = (string) ($screens[$screen]['help_topic'] ?? dbxKiCmsHelpProvision::DEFAULT_TOPIC);

      $content = $this->renderCmsHelpContent($topic);
      if (trim(strip_tags((string) $content)) === '') {
         $content = $this->tpl()->get_tpl('dbx|alert-info', array(
            'msg' => 'Fuer diesen Bereich ist noch keine Hilfe hinterlegt.',
         ));
      }

      // Eigene Shell statt dbxAdmin|admin-help-shell: das Popup-Fenster
      // (openWin) traegt bereits den Hilfetitel in seiner eigenen
      // Titelleiste (siehe button()/help_title) - die zusaetzliche
      // dbx|module-bar-help-Leiste innerhalb des Inhalts zeigte denselben
      // Titel ein zweites Mal an.
      return $this->tpl()->get_tpl('dbxKi|ki-help-shell', array(
         'frame_id' => 'dbx_ki_help_' . preg_replace('/[^a-z0-9_-]+/i', '_', $screen),
         'frame_panel_class' => 'dbx-admin-help py-3 dbx-context-help-preview',
         'frame_form_open' => '',
         'frame_form_close' => '',
         'frame_subbar' => '',
         'frame_body_class' => 'dbx-admin-help-body dbx-context-help-body',
         'frame_body_head' => '',
         'frame_body_tail' => '',
         'frame_panel_attrs' => '',
         'content' => $content,
      ));
   }
}
