<?php
namespace dbx\dbxAdmin;
dbx()->use_system_class('dbxForm');

class dbxAdminHelp {

   private $texts;

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      $texts = new \dbxForm();
      // Nur den sprachabhängigen FD-Meldungsvertrag laden. Ein vollständiges
      // Formular-init() würde während der Formular-Hilfe erneut dbxAdminHelp
      // aufrufen und damit eine Rekursion erzeugen.
      $texts->set_form_help_enabled(false);
      $texts->_fd = 'dbxAdmin|admin-help-ui';
      $texts->load_fd_messages();
      $this->texts = $texts;
      return $this->texts;
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function h($value) {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   public function topics() {
      $topics = array(
         'dashboard'            => array('title' => 'Admin Dashboard', 'tpl' => 'admin-help-dashboard', 'permalink' => 'help-dashboard-admin'),
         'cache'                => array('title' => 'Page-Cache', 'tpl' => 'admin-help-cache', 'permalink' => 'help-cache-content'),
         'dd_list'              => array('title' => 'DataDic Sync', 'tpl' => 'admin-help-dd-list', 'permalink' => 'help-datadic-sync'),
         'dd_fields'            => array('title' => 'DD Feldvergleich', 'tpl' => 'admin-help-dd-fields', 'permalink' => 'help-datadic-felder'),
         'dd_backup'            => array('title' => 'DB Backup & Restore', 'tpl' => 'admin-help-dd-backup', 'permalink' => 'help-datenbank-backup'),
         'db_list'              => array('title' => 'DB Sync', 'tpl' => 'admin-help-db-list', 'permalink' => 'help-datenbank-sync'),
         'edit_dd'              => array('title' => 'DD Editor', 'tpl' => 'admin-help-edit-dd', 'permalink' => 'help-datadic-editor'),
         'edit_fd'              => array('title' => 'FD Editor', 'tpl' => 'admin-help-edit-fd', 'permalink' => 'help-formular-editor'),
         'server'               => array('title' => 'Datenbank-Server', 'tpl' => 'admin-help-server', 'permalink' => 'help-datenbank-server'),
         'server_tables'        => array('title' => 'Server-Tabellen', 'tpl' => 'admin-help-server-tables', 'permalink' => 'help-server-tabellen'),
         'modules_list'         => array('title' => 'Module verwalten', 'tpl' => 'admin-help-modules-list', 'permalink' => 'help-module-verwalten'),
         'modules_new'          => array('title' => 'Neues Modul', 'tpl' => 'admin-help-modules-new', 'permalink' => 'help-modul-erstellen'),
         'modules_access'       => array('title' => 'Modul-Zugriff', 'tpl' => 'admin-help-modules-access', 'permalink' => 'help-modul-zugriff'),
         'config'               => array('title' => 'System-Konfiguration', 'tpl' => 'admin-help-config', 'permalink' => 'help-system-konfiguration'),
         'session'              => array('title' => 'Sessions', 'tpl' => 'admin-help-session', 'permalink' => 'help-sessions-admin'),
         'trace'                => array('title' => 'Trace / Historie', 'tpl' => 'admin-help-trace', 'permalink' => 'help-historie-admin'),
         'sysmsg'               => array('title' => 'System-Meldungen', 'tpl' => 'admin-help-sysmsg', 'permalink' => 'help-systemmeldungen-admin'),
         'missing'              => array('title' => 'Missing Files', 'tpl' => 'admin-help-missing', 'permalink' => 'help-fehlende-ressourcen'),
         'contact'              => array('title' => 'Kontaktanfragen', 'tpl' => 'admin-help-contact', 'permalink' => 'help-kontaktanfragen-admin'),
         'user'                 => array('title' => 'Benutzer & Gruppen', 'tpl' => 'admin-help-user', 'permalink' => 'help-benutzer-gruppen'),
         'export_sql'           => array('title' => 'SQL Export', 'tpl' => 'admin-help-export-sql', 'permalink' => 'help-sql-export'),
         'datadic'              => array('title' => 'DataDic Uebersicht', 'tpl' => 'admin-help-datadic', 'permalink' => 'help-datadic-uebersicht'),
         'workflow_list'        => array('title' => 'Workflow Definitionen', 'tpl' => 'admin-help-workflow-list', 'permalink' => 'help-workflow-definitionen'),
         'workflow_edit'        => array('title' => 'Workflow bearbeiten', 'tpl' => 'admin-help-workflow-edit', 'permalink' => 'help-workflow-bearbeiten'),
         'workflow_binds'       => array('title' => 'Modul-Bindings', 'tpl' => 'admin-help-workflow-binds', 'permalink' => 'help-workflow-bindings'),
         'workflow_bind_edit'   => array('title' => 'Binding bearbeiten', 'tpl' => 'admin-help-workflow-bind-edit', 'permalink' => 'help-workflow-binding-bearbeiten'),
         'workflow_instances'   => array('title' => 'Workflow Instanzen', 'tpl' => 'admin-help-workflow-instances', 'permalink' => 'help-workflow-instanzen'),
         'workflow_install'     => array('title' => 'Workflow Install', 'tpl' => 'admin-help-workflow-install', 'permalink' => 'help-workflow-installation'),
         'workflow_use'         => array('title' => 'Workflow starten', 'tpl' => 'admin-help-workflow-use', 'permalink' => 'help-workflow-starten'),
         'workflow_run'         => array('title' => 'Workflow ausführen', 'tpl' => 'admin-help-workflow-run', 'permalink' => 'help-workflow-ausfuehren'),
         'content'              => array('title' => 'Content CMS — Benutzerhandbuch', 'tpl' => 'admin-help-content', 'permalink' => 'help-content-cms'),
         'content_lng'          => array('title' => 'Sprachen & Uebersetzung — Benutzerhandbuch', 'tpl' => 'admin-help-content-lng', 'permalink' => 'help-content-sprachen'),
         'user_admin'           => array('title' => 'Benutzer-Administration', 'tpl' => 'admin-help-user-admin', 'permalink' => 'help-benutzer-administration'),
         'user_profil'          => array('title' => 'Mein Profil', 'tpl' => 'admin-help-user-profil', 'permalink' => 'help-benutzer-profil'),
         'user_group_form'      => array('title' => 'Benutzergruppe', 'tpl' => 'admin-help-user-group', 'permalink' => 'help-benutzergruppe'),
         'consent_privacy'      => array('title' => 'Datenschutz-Einstellungen', 'tpl' => 'admin-help-consent-privacy', 'permalink' => 'help-datenschutz-einstellungen'),
         'impressum'            => array('title' => 'Impressum', 'tpl' => 'admin-help-impressum', 'permalink' => 'help-impressum'),
      );
      $texts = $this->texts();
      foreach ($topics as $topic => &$meta) {
         $meta['title'] = $texts->get_fd_message('title_' . $topic);
      }
      unset($meta);
      return $topics;
   }

   public function barMeta($topic = '') {
      if ($topic === '') {
         $topic = $this->resolveTopic();
      }
      $topic = trim((string)$topic);
      $topics = $this->topics();
      if ($topic === '' || !isset($topics[$topic])) {
         return null;
      }

      $icons = array(
         'dashboard'            => array('icon' => 'bi-speedometer2', 'subtitle' => 'Systemstatus, Datenbanken und Aktivitaeten'),
         'cache'                => array('icon' => 'bi-lightning-charge', 'subtitle' => 'Statische Content-Seiten beschleunigen'),
         'dd_list'              => array('icon' => 'bi-journal-code', 'subtitle' => 'DD-Dateien mit Datenbankstruktur abgleichen'),
         'dd_fields'            => array('icon' => 'bi-list-columns', 'subtitle' => 'Felddefinitionen vergleichen'),
         'dd_backup'            => array('icon' => 'bi-archive', 'subtitle' => 'Datenbank sichern und wiederherstellen'),
         'db_list'              => array('icon' => 'bi-database', 'subtitle' => 'Tabellen und Datensaetze verwalten'),
         'edit_dd'              => array('icon' => 'bi-journal-code', 'subtitle' => 'Tabellen-Metadaten und Felder pflegen'),
         'edit_fd'              => array('icon' => 'bi-input-cursor-text', 'subtitle' => 'Formularfelddefinitionen bearbeiten'),
         'server'               => array('icon' => 'bi-hdd-network', 'subtitle' => 'Datenbank-Server verwalten'),
         'server_tables'        => array('icon' => 'bi-table', 'subtitle' => 'Tabellen des Servers'),
         'modules_list'         => array('icon' => 'bi-boxes', 'subtitle' => 'Installierte Module verwalten'),
         'modules_new'          => array('icon' => 'bi-plus-square', 'subtitle' => 'Neues Modul anlegen'),
         'modules_access'       => array('icon' => 'bi-shield-lock', 'subtitle' => 'Zugriffsrechte pro Modul'),
         'config'               => array('icon' => 'bi-gear', 'subtitle' => 'System-Konfiguration'),
         'session'              => array('icon' => 'bi-person-badge', 'subtitle' => 'Aktive Benutzer-Sessions'),
         'trace'                => array('icon' => 'bi-clock-history', 'subtitle' => 'Aenderungshistorie'),
         'sysmsg'               => array('icon' => 'bi-bell', 'subtitle' => 'System-Meldungen'),
         'missing'              => array('icon' => 'bi-file-earmark-x', 'subtitle' => 'Fehlende Dateien'),
         'contact'              => array('icon' => 'bi-envelope-check', 'subtitle' => 'Kontaktanfragen verwalten'),
         'user'                 => array('icon' => 'bi-people', 'subtitle' => 'Benutzer und Gruppen'),
         'export_sql'           => array('icon' => 'bi-filetype-sql', 'subtitle' => 'SQL-Dump exportieren'),
         'datadic'              => array('icon' => 'bi-journal-code', 'subtitle' => 'DataDic Uebersicht'),
         'workflow_list'        => array('icon' => 'bi-diagram-3', 'subtitle' => 'Workflow-Definitionen'),
         'workflow_edit'        => array('icon' => 'bi-diagram-3', 'subtitle' => 'Workflow bearbeiten'),
         'workflow_binds'       => array('icon' => 'bi-plug', 'subtitle' => 'Modul-Bindings verwalten'),
         'workflow_bind_edit'   => array('icon' => 'bi-plug', 'subtitle' => 'Binding JSON bearbeiten'),
         'workflow_instances'   => array('icon' => 'bi-collection', 'subtitle' => 'Laufende Workflow-Durchlaeufe'),
         'workflow_install'     => array('icon' => 'bi-database-gear', 'subtitle' => 'Workflow-Tabellen installieren'),
         'workflow_use'         => array('icon' => 'bi-play-circle', 'subtitle' => 'Ziel prüfen und neuen Durchlauf starten'),
         'workflow_run'         => array('icon' => 'bi-list-check', 'subtitle' => 'Schritte bearbeiten und kontrolliert abschließen'),
         'content'              => array('icon' => 'bi-file-earmark-richtext', 'subtitle' => 'Seiten, Ordner, Medien und Mehrsprachigkeit'),
         'content_lng'          => array('icon' => 'bi-translate', 'subtitle' => 'Master-Sprache, CMS-Sync, Provider und Startseite'),
         'user_admin'           => array('icon' => 'bi-person-lines-fill', 'subtitle' => 'Benutzer, Rollen und Gruppen'),
         'user_profil'          => array('icon' => 'bi-person-circle', 'subtitle' => 'Eigene Stammdaten und Oberflaeche'),
         'user_group_form'      => array('icon' => 'bi-people', 'subtitle' => 'Rollenname, Beschreibung und Aktivstatus'),
         'consent_privacy'      => array('icon' => 'bi-shield-check', 'subtitle' => 'Cookies und externe Medien'),
         'impressum'            => array('icon' => 'bi-building', 'subtitle' => 'Anbieterkennzeichnung'),
      );

      $extra = $icons[$topic] ?? array('icon' => 'bi-grid', 'subtitle' => '');
      return array(
         'title'    => (string)($topics[$topic]['title'] ?? $topic),
         'icon'     => (string)($extra['icon'] ?? 'bi-grid'),
         'subtitle' => $this->texts()->get_fd_message('subtitle_' . $topic),
      );
   }

   public function url($topic) {
      return '?dbx_modul=dbxContent&dbx_run1=help&topic=' . rawurlencode((string)$topic);
   }

   public function formUrl(string $modul, string $form, string $title = ''): string {
      $url = '?dbx_modul=dbxContent&dbx_run1=help&topic=form'
         . '&help_modul=' . rawurlencode($modul)
         . '&help_form=' . rawurlencode($form);
      if (trim($title) !== '') {
         $url .= '&help_title=' . rawurlencode($title);
      }
      return $url;
   }

   public function topicPermalink(string $topic): string {
      $topic = trim($topic);
      $topics = $this->topics();
      $permalink = trim((string)($topics[$topic]['permalink'] ?? ''));
      if ($permalink !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $permalink)) {
         return $permalink;
      }
      $slug = preg_replace('/-+/', '-', preg_replace('/[^a-z0-9-]+/', '-', str_replace('_', '-', strtolower($topic))));
      return trim('help-' . trim((string)$slug, '-'), '-');
   }

   public function button($topic) {
      $topic = trim((string)$topic);
      $topics = $this->topics();
      if ($topic === '' || !isset($topics[$topic])) {
         return '';
      }

      $meta = $topics[$topic];
      return $this->tpl()->get_tpl('dbxAdmin|admin-help-button', array(
         'help_url' => $this->h($this->url($topic)),
         'help_title' => $this->h($this->texts()->format_fd_message('help_prefix', array('title' => $meta['title'] ?? $topic))),
      ));
   }

   /**
    * Help-Button fuer ein konkretes dbxForm-Formular.
    *
    * Formulare ohne festes CMS-Hilfethema erhalten damit trotzdem eine
    * verlaessliche Hilfe. Die Zielseite verwendet zuerst einen optionalen
    * formularspezifischen Hilfstext und danach die Modulhilfe als Fallback.
    */
   public function formButton(string $modul, string $form, string $title = ''): string {
      $modul = trim($modul);
      $form = trim($form);
      $title = trim($title);
      if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $modul)
         || $form === ''
         || !preg_match('/^[a-zA-Z0-9_.:-]+$/', $form)) {
         return '';
      }

      if ($title === '') {
         $title = ucwords(str_replace(array('-', '_', '.'), ' ', $form));
      }

      $url = $this->formUrl($modul, $form, $title);
      return $this->tpl()->get_tpl('dbxAdmin|admin-help-button', array(
         'help_url' => $this->h($url),
         'help_title' => $this->h($this->texts()->format_fd_message('help_prefix', array('title' => $title))),
      ));
   }

   public function renderModuleBar($topic = '', $actionsHtml = '', $title = '', $icon = '', $subtitle = '') {
      $data = $this->moduleBarTemplateData($topic, $actionsHtml, $title, $icon, $subtitle);
      if (!$data) {
         return '';
      }

      return trim($this->tpl()->get_tpl('dbx|module-bar', $data));
   }

   public function moduleBarTemplateData($topic = '', $actionsHtml = '', $title = '', $icon = '', $subtitle = '') {
      if ($topic === '') {
         $topic = $this->resolveTopic();
      }
      $topic = trim((string)$topic);
      if ($topic === '') {
         return array();
      }

      $meta = $this->barMeta($topic);
      if (!is_array($meta)) {
         return array();
      }

      if ($title === '') {
         $title = (string)($meta['title'] ?? $topic);
      }
      if ($icon === '') {
         $icon = (string)($meta['icon'] ?? 'bi-grid');
      }
      if ($subtitle === '') {
         $subtitle = (string)($meta['subtitle'] ?? '');
      }

      return array(
         'bar_class'         => 'dbx-module-bar',
         'bar_title_class'   => 'dbx-module-bar-titleblock',
         'bar_actions_class' => 'dbx-module-bar-actions',
         'bar_title'         => (string)$title,
         'bar_icon'          => (string)$icon,
         'bar_subtitle'      => (string)$subtitle,
         'bar_title_pre'     => '',
         'bar_title_heading_attrs' => '',
         'bar_actions'       => (string)$actionsHtml,
         'bar_extra'         => $this->button($topic),
      );
   }

   /**
    * Bar-Daten fuer Kontext-Hilfe im Hilfe-Fenster (ohne Help-Button, ohne bar_middle).
    */
   public function helpWindowBarTemplateData($topic = '') {
      $topic = trim((string)$topic);
      if ($topic === '') {
         return array();
      }

      $meta = $this->barMeta($topic);
      if (!is_array($meta)) {
         return array();
      }

      return array(
         'bar_class'               => 'dbx-module-bar dbx-help-context-bar',
         'bar_title_class'         => 'dbx-module-bar-titleblock',
         'bar_actions_class'       => 'dbx-module-bar-actions',
         'bar_title'               => (string)($meta['title'] ?? $topic),
         'bar_icon'                => (string)($meta['icon'] ?? 'bi-question-circle'),
         'bar_subtitle'            => $this->texts()->get_fd_message('context_help'),
         'bar_title_pre'           => '',
         'bar_title_heading_attrs' => '',
         'bar_actions'             => '',
         'bar_extra'               => '',
         'bar_middle'              => '',
      );
   }

   public function formHelpWindowBarTemplateData(string $title = ''): array {
      $title = trim($title) !== '' ? trim($title) : $this->texts()->get_fd_message('form_help');
      return array(
         'bar_class'               => 'dbx-module-bar dbx-help-context-bar',
         'bar_title_class'         => 'dbx-module-bar-titleblock',
         'bar_actions_class'       => 'dbx-module-bar-actions',
         'bar_title'               => $this->h($title),
         'bar_icon'                => 'bi-question-circle',
         'bar_subtitle'            => $this->texts()->get_fd_message('form_help_subtitle'),
         'bar_title_pre'           => '',
         'bar_title_heading_attrs' => '',
         'bar_actions'             => '',
         'bar_extra'               => '',
         'bar_middle'              => '',
      );
   }

   public function vars($topic) {
      $data = $this->moduleBarTemplateData($topic);
      if (!$data) {
      return array(
         'help_button' => '',
         'bar_title' => '',
         'bar_icon' => 'bi-grid',
         'bar_subtitle' => '',
         'bar_title_pre' => '',
         'bar_title_heading_attrs' => '',
         'bar_class' => 'dbx-module-bar',
            'bar_title_class' => 'dbx-module-bar-titleblock',
            'bar_actions_class' => 'dbx-module-bar-actions',
            'bar_actions' => '',
            'bar_extra' => '',
         );
      }

      return array_merge($data, array(
         'help_button' => (string)($data['bar_extra'] ?? ''),
      ));
   }

   public function attachForm($oForm, $topic = '') {
      if (!is_object($oForm) || !method_exists($oForm, 'add_obj')) {
         return;
      }
      if ($topic === '') {
         $topic = $this->resolveTopic();
      }
      if ($topic === '') {
         return;
      }
      $oForm->add_obj('help_button', $this->button($topic));
      if (method_exists($oForm, 'add_module_bar')) {
         $barMeta = $this->barMeta($topic);
         if (is_array($barMeta) && $barMeta) {
            $oForm->add_module_bar(
               (string)($barMeta['title'] ?? ''),
               (string)($barMeta['icon'] ?? 'bi-grid'),
               (string)($barMeta['subtitle'] ?? '')
            );
         }
      }
   }

   public function resolveTopic($modul = '', $run1 = '', $run2 = '') {
      if ($modul === '') {
         $modul = (string)dbx()->get_system_var('dbx_modul', '');
      }
      if ($run1 === '') {
         $run1 = (string)dbx()->get_modul_var('dbx_run1', '', 'parameter');
      }
      if ($run2 === '') {
         $run2 = (string)dbx()->get_modul_var('dbx_run2', '', 'parameter');
      }

      $run1 = strtolower(trim($run1));
      $run2 = strtolower(trim($run2));

      if ($modul === 'dbxAdmin') {
         if ($run1 === 'run' || $run1 === 'dashboard' || $run1 === '') {
            return 'dashboard';
         }
         if ($run1 === 'cache') {
            return 'cache';
         }
         if ($run1 === 'dd') {
            if (strpos($run2, 'field') !== false) {
               return 'dd_fields';
            }
            if ($run2 === 'backup_db' || $run2 === 'restore_db') {
               return 'dd_backup';
            }
            return 'dd_list';
         }
         if ($run1 === 'db') {
            return 'db_list';
         }
         if ($run1 === 'edit_dd') {
            return 'edit_dd';
         }
         if ($run1 === 'edit_fd') {
            return 'edit_fd';
         }
         if ($run1 === 'server') {
            return ($run2 === 'list_tables') ? 'server_tables' : 'server';
         }
         if ($run1 === 'modules') {
            if ($run2 === 'modul_new') {
               return 'modules_new';
            }
            if ($run2 === 'modul_access') {
               return 'modules_access';
            }
            return 'modules_list';
         }
         if ($run1 === 'config') {
            $xmodul = strtolower(trim((string)dbx()->get_modul_var('xmodul', '', 'parameter')));
            if ($xmodul === 'dbxcontent') {
               return 'content_lng';
            }
            return 'config';
         }
         if ($run1 === 'session') {
            return 'session';
         }
         if ($run1 === 'trace') {
            return 'trace';
         }
         if ($run1 === 'sysmsg') {
            return 'sysmsg';
         }
         if ($run1 === 'missing') {
            return 'missing';
         }
         if ($run1 === 'contact') {
            return 'contact';
         }
         if ($run1 === 'user') {
            return 'user';
         }
         if ($run1 === 'export_sql') {
            return 'export_sql';
         }
         if ($run1 === 'datadic') {
            return 'datadic';
         }
      }

      if ($modul === 'dbxWorkflow_admin') {
         if ($run1 === 'edit') {
            return 'workflow_edit';
         }
         if ($run1 === 'edit_bind') {
            return 'workflow_bind_edit';
         }
         if ($run1 === 'binds') {
            return 'workflow_binds';
         }
         if ($run1 === 'instances') {
            return 'workflow_instances';
         }
         if ($run1 === 'install') {
            return 'workflow_install';
         }
         return 'workflow_list';
      }

      if ($modul === 'dbxWorkflow') {
         if ($run1 === 'run') {
            return 'workflow_run';
         }
         return 'workflow_use';
      }

      if ($modul === 'dbxContent_admin') {
         if ($run1 === 'content' && $run2 === 'config') {
            return 'content_lng';
         }
         return 'content';
      }

      if ($modul === 'dbxUser_admin') {
         if ($run1 === 'user' && ($run2 === 'new_group' || $run2 === 'edit_group')) {
            return 'user_group_form';
         }
         return 'user_admin';
      }

      if ($modul === 'dbxUser') {
         if ($run1 === 'user' && ($run2 === 'edit_profil' || $run2 === 'profil')) {
            return 'user_profil';
         }
      }

      return '';
   }

   public function run() {
      $obj = dbx()->get_include_obj('dbxContentContextHelp', 'dbxContent');
      if (is_object($obj) && method_exists($obj, 'run')) {
         return $obj->run();
      }
      return '';
   }
}
