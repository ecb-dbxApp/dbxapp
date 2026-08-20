<?php
namespace dbx\dbxKi;

class dbxKiHelp {

   private const HELP_TITLES = array(
      'content' => 'Content-Seite mit ChatGPT oder DeepSeek erstellen',
      'module' => 'Modul mit ChatGPT oder DeepSeek entwickeln',
      'design' => 'Design mit ChatGPT oder DeepSeek entwickeln',
   );

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
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-ui-checks',
            'subtitle' => 'Formular, Download, Ablauf',
         ),
         'briefing_page_create' => array(
            'title' => 'Neue Seite — Formular',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-file-earmark-plus',
            'subtitle' => 'Auftrag fuer neue Content-Seite',
         ),
         'briefing_page_update' => array(
            'title' => 'Seite aendern — Formular',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-pencil-square',
            'subtitle' => 'Auftrag fuer Seiten-Update',
         ),
         'briefing_page_translate' => array(
            'title' => 'Uebersetzung — Formular',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-translate',
            'subtitle' => 'Auftrag fuer Seiten-Uebersetzung',
         ),
         'briefing_content' => array(
            'title' => 'Content-KI',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-file-earmark-richtext',
            'subtitle' => 'Neue Seite, Änderung oder Übersetzung auswählen',
         ),
         'translation_sync_all' => array(
            'title' => 'Sprache komplett übersetzen',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-translate',
            'subtitle' => 'CMS-Struktur in Zielsprachen übertragen',
         ),
         'briefing_module' => array(
            'title' => 'Modul-KI',
            'help_title' => self::HELP_TITLES['module'],
            'help_topic' => 'module',
            'icon' => 'bi-stars',
            'subtitle' => 'Neues oder bestehendes Modul auswählen',
         ),
         'briefing_module_edit' => array(
            'title' => 'Modul-KI',
            'help_title' => self::HELP_TITLES['module'],
            'help_topic' => 'module',
            'icon' => 'bi-stars',
            'subtitle' => 'Auftrag fuer bestehendes Modul',
         ),
         'briefing_design' => array(
            'title' => 'Design-KI',
            'help_title' => self::HELP_TITLES['design'],
            'help_topic' => 'design',
            'icon' => 'bi-palette',
            'subtitle' => 'Neues oder bestehendes Design auswählen',
         ),
         'briefing_design_edit' => array(
            'title' => 'Design-KI',
            'help_title' => self::HELP_TITLES['design'],
            'help_topic' => 'design',
            'icon' => 'bi-palette',
            'subtitle' => 'Briefing, vollständiger Designkontext und sichere Antwort-ZIP',
         ),
         'briefing_styles' => array(
            'title' => 'Schreibstile',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-type',
            'subtitle' => 'Ton und Stil fuer KI-Auftraege',
         ),
         'module_bundle' => array(
            'title' => 'Modul-Antwort importieren',
            'help_title' => self::HELP_TITLES['module'],
            'help_topic' => 'module',
            'icon' => 'bi-upload',
            'subtitle' => 'Antwort-ZIP fuer Modul-KI',
         ),
         'module_bundle_preview' => array(
            'title' => 'Modul-Import',
            'help_title' => self::HELP_TITLES['module'],
            'help_topic' => 'module',
            'icon' => 'bi-check2-square',
            'subtitle' => 'Modul-Antwort ausfuehren',
         ),
         'design_bundle_import' => array(
            'title' => 'Design-Antwort importieren',
            'help_title' => self::HELP_TITLES['design'],
            'help_topic' => 'design',
            'icon' => 'bi-upload',
            'subtitle' => 'Antwort-ZIP fuer Design-KI',
         ),
         'design_bundle_apply' => array(
            'title' => 'Design-Import',
            'help_title' => self::HELP_TITLES['design'],
            'help_topic' => 'design',
            'icon' => 'bi-check2-square',
            'subtitle' => 'Design-Antwort pruefen und anwenden',
         ),
         'bundle' => array(
            'title' => 'Bundle importieren',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-box-arrow-in-down',
            'subtitle' => 'KI-Antwort ZIP hochladen',
         ),
         'bundle_preview' => array(
            'title' => 'KI-Import',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-eye',
            'subtitle' => 'Vorschau und Ausfuehrung',
         ),
         'bundle_process' => array(
            'title' => 'KI-Import',
            'help_title' => self::HELP_TITLES['content'],
            'icon' => 'bi-play-circle',
            'subtitle' => 'Bundle wird ausgefuehrt',
         ),
      );
   }

   public function resolve_screen(string $run1 = ''): string {
      if ($run1 === '') {
         $run1 = (string) dbx()->get_modul_var('dbx_run1', 'bundle', 'parameter');
      }
      $run1 = strtolower(trim($run1));
      $direct_screens = array(
         'briefing', 'briefing_content', 'briefing_page_create', 'briefing_page_update',
         'briefing_page_translate', 'briefing_styles', 'briefing_module', 'briefing_module_edit',
         'briefing_design', 'briefing_design_edit', 'translation_sync_all',
         'design_bundle_import', 'design_bundle_apply',
      );
      if (in_array($run1, $direct_screens, true)) {
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

   public function help_url(string $screen = ''): string {
      $screen = trim($screen);
      if ($screen === '') {
         $screen = $this->resolve_screen();
      }
      return '?dbx_modul=dbxKi&dbx_run1=help&screen=' . rawurlencode($screen);
   }

   public function button(string $screen = ''): string {
      $screen = trim($screen);
      if ($screen === '') {
         $screen = $this->resolve_screen();
      }
      $screens = $this->screens();
      if (!isset($screens[$screen])) {
         $screen = 'bundle';
      }
      $meta = $screens[$screen];
      return $this->tpl()->get_tpl('dbxHelp|help-button', array(
         'help_url' => $this->h($this->help_url($screen)),
         'help_title' => $this->h('Hilfe: ' . ($meta['help_title'] ?? $meta['title'] ?? $screen)),
      ));
   }

   public function module_bar_template_data(string $screen = '', string $actions_html = '', string $title = '', string $icon = '', string $subtitle = ''): array {
      if ($screen === '') {
         $screen = $this->resolve_screen();
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
         'bar_actions' => (string) $actions_html,
         'bar_extra' => $this->button($screen),
         'bar_middle' => '',
      );
   }

   public function render_cms_help_content(string $topic = ''): string {
      $topic = isset(self::HELP_TITLES[$topic]) ? $topic : 'content';
      return $this->tpl()->get_help_tpl('dbxKi', $topic);
   }

   public function render_help_window(string $screen = ''): string {
      dbx()->set_system_var('dbx_page', 'window');

      if ($screen === '') {
         $screen = trim((string) dbx()->get_modul_var('screen', '', 'parameter'));
      }
      if ($screen === '') {
         $screen = $this->resolve_screen();
      }

      $screens = $this->screens();
      $topic = (string) ($screens[$screen]['help_topic'] ?? 'content');

      $content = $this->render_cms_help_content($topic);
      if (trim(strip_tags((string) $content)) === '') {
         $content = $this->tpl()->get_tpl('dbx|alert-info', array(
            'msg' => 'Fuer diesen Bereich ist noch keine Hilfe hinterlegt.',
         ));
      }

      // Eigene Shell statt dbxHelp|help-shell: das Popup-Fenster
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
