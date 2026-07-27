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
         'translation_sync_all' => array(
            'title' => 'Sprache komplett uebersetzen',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-translate',
            'subtitle' => 'CMS-Struktur in Zielsprachen uebertragen',
         ),
         'briefing_module' => array(
            'title' => 'Modul-KI',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-stars',
            'subtitle' => 'Auftrag fuer bestehendes Modul',
         ),
         'briefing_styles' => array(
            'title' => 'Schreibstile',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-type',
            'subtitle' => 'Ton und Stil fuer KI-Auftraege',
         ),
         'module_bundle' => array(
            'title' => 'Modul-Antwort importieren',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-upload',
            'subtitle' => 'Antwort-ZIP fuer Modul-KI',
         ),
         'module_bundle_preview' => array(
            'title' => 'Modul-Import',
            'help_title' => dbxKiCmsHelpProvision::TITLE,
            'icon' => 'bi-check2-square',
            'subtitle' => 'Modul-Antwort ausfuehren',
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
      if ($run1 === 'briefing' || $run1 === 'briefing_page_create' || $run1 === 'briefing_page_update'
         || $run1 === 'briefing_page_translate' || $run1 === 'briefing_styles' || $run1 === 'briefing_module'
         || $run1 === 'translation_sync_all') {
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
         'bar_class' => 'dbx-module-bar',
         'bar_title_class' => 'dbx-module-bar-titleblock',
         'bar_actions_class' => 'dbx-module-bar-actions',
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

   public function helpWindowBarTemplateData(string $screen = ''): array {
      if ($screen === '') {
         $screen = $this->resolveScreen();
      }
      $screens = $this->screens();
      if (!isset($screens[$screen])) {
         $screen = 'bundle';
      }
      $meta = $screens[$screen];

      return array(
         'bar_class' => 'dbx-module-bar dbx-help-context-bar',
         'bar_title_class' => 'dbx-module-bar-titleblock',
         'bar_actions_class' => 'dbx-module-bar-actions',
         'bar_title' => (string) ($meta['help_title'] ?? $meta['title'] ?? $screen),
         'bar_icon' => 'bi-question-circle',
         'bar_subtitle' => 'Kontext-Hilfe',
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_actions' => '',
         'bar_extra' => '',
         'bar_middle' => '',
      );
   }

   public function renderCmsHelpContent(): string {
      dbxKiCmsHelpProvision::run();

      $permalink = dbxKiCmsHelpProvision::PERMALINK;
      $cid = $this->resolveHelpCid($permalink);

      if ($cid <= 0) {
         $result = dbxKiCmsHelpProvision::provision();
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

      $content = $this->renderCmsHelpContent();
      if (trim(strip_tags((string) $content)) === '') {
         $content = $this->tpl()->get_tpl('dbx|alert-info', array(
            'msg' => 'Fuer diesen Bereich ist noch keine Hilfe hinterlegt.',
         ));
      }

      return $this->tpl()->get_tpl('dbxAdmin|admin-help-shell', array_merge(
         $this->helpWindowBarTemplateData($screen),
         array(
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
         )
      ));
   }
}
