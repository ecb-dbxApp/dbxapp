<?php
namespace dbx\dbxHelp;

/**
 * Konventionsbasierte, modulautonome Kontexthilfe.
 *
 * Ein Modul braucht nur noch Templates unter tpl/help:
 *   <run1>--<run2>.htm -> <run1>.htm -> modul.htm
 * Die Standardaktionen '', run, start und show verwenden direkt modul.htm.
 * Es gibt bewusst weder ein zentrales Topic-Verzeichnis noch Modul-Manifeste.
 */
class dbxModuleHelp {

   private $texts;

   private function texts() {
      if ($this->texts) {
         return $this->texts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->set_form_help_enabled(false);
      $texts->set_field_definition('dbxHelp|help-ui');
      $texts->load_fd_messages();
      return $this->texts = $texts;
   }

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function h($value): string {
      return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
   }

   private function valid_module(string $module): bool {
      return (bool)preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $module);
   }

   private function action(string $value): string {
      $value = strtolower(trim($value));
      return preg_match('/^[a-z0-9_]+$/', $value) ? $value : '';
   }

   /** Liefert den bereinigten Hilfe-Kontext der aktuellen oder angegebenen Route. */
   public function context(string $module = '', string $run1 = '', string $run2 = ''): array {
      $module = trim($module !== '' ? $module : (string)dbx()->get_system_var('dbx_modul', ''));
      $run1 = $this->action($run1 !== '' ? $run1 : (string)dbx()->get_modul_var('dbx_run1', '', 'parameter'));
      $run2 = $this->action($run2 !== '' ? $run2 : (string)dbx()->get_modul_var('dbx_run2', '', 'parameter'));
      if (in_array($run1, array('', 'run', 'start', 'show'), true)) {
         $run1 = 'modul';
         $run2 = '';
      }
      return array('module' => $this->valid_module($module) ? $module : '', 'run1' => $run1, 'run2' => $run2);
   }

   /** Kandidaten in fester, dokumentierter Reihenfolge. */
   public function candidates(string $module = '', string $run1 = '', string $run2 = ''): array {
      $context = $this->context($module, $run1, $run2);
      $names = array();
      if ($context['run1'] !== 'modul' && $context['run2'] !== '') {
         $names[] = $context['run1'] . '--' . $context['run2'];
      }
      if ($context['run1'] !== '') {
         $names[] = $context['run1'];
      }
      $names[] = 'modul';
      return array_values(array_unique($names));
   }

   /** Ermittelt das tatsächlich vorhandene Template, ohne es zu rendern. */
   public function template_name(string $module = '', string $run1 = '', string $run2 = ''): string {
      $context = $this->context($module, $run1, $run2);
      if ($context['module'] === '') {
         return '';
      }
      $base = dbx()->get_base_dir() . 'dbx/modules/' . $context['module'] . '/tpl/help/';
      foreach ($this->candidates($context['module'], $context['run1'], $context['run2']) as $candidate) {
         $path = dbx()->lng_resolve_file($base, $candidate, 'htm', '', true);
         if ($path !== '' && is_file($path)) {
            return $candidate;
         }
      }
      return '';
   }

   public function has(string $module = '', string $run1 = '', string $run2 = ''): bool {
      return $this->template_name($module, $run1, $run2) !== '';
   }

   public function render(string $module = '', string $run1 = '', string $run2 = '', array $data = array()): string {
      $context = $this->context($module, $run1, $run2);
      $template = $this->template_name($context['module'], $context['run1'], $context['run2']);
      if ($template === '' || !dbx()->has_module_access($context['module'])) {
         return '';
      }
      return $this->tpl()->get_help_tpl($context['module'], $template, $data);
   }

   private function default_title(array $context): string {
      $name = $context['run2'] !== '' ? $context['run2'] : $context['run1'];
      if ($name === '' || $name === 'modul') {
         $name = preg_replace('/_admin$/', '', $context['module']);
         $name = preg_replace('/^dbx/', '', (string)$name);
      }
      $name = trim((string)preg_replace('/\s+/', ' ', str_replace(array('_', '-'), ' ', (string)$name)));
      return $name !== '' ? ucwords($name) : $this->texts()->get_fd_message('context_help');
   }

   public function url(string $module = '', string $run1 = '', string $run2 = '', string $title = ''): string {
      $context = $this->context($module, $run1, $run2);
      if ($context['module'] === '') {
         return '';
      }
      $url = '?dbx_modul=dbxHelp&dbx_run1=context'
         . '&help_modul=' . rawurlencode($context['module'])
         . '&help_run1=' . rawurlencode($context['run1'])
         . '&help_run2=' . rawurlencode($context['run2']);
      return trim($title) === '' ? $url : $url . '&help_title=' . rawurlencode(trim($title));
   }

   public function button(string $module = '', string $run1 = '', string $run2 = '', string $title = ''): string {
      $context = $this->context($module, $run1, $run2);
      if (!$this->has($context['module'], $context['run1'], $context['run2'])) {
         return '';
      }
      $title = trim($title) !== '' ? trim($title) : $this->default_title($context);
      $url = $this->url($context['module'], $context['run1'], $context['run2'], $title);
      return $this->tpl()->get_tpl('dbxHelp|help-button', array(
         'help_url' => $this->h($url),
         'help_title' => $this->h($this->texts()->format_fd_message('help_prefix', array('title' => $title))),
      ));
   }

   public function form_url(string $module, string $form, string $title = ''): string {
      $url = '?dbx_modul=dbxHelp&dbx_run1=context&help_form=' . rawurlencode($form)
         . '&help_modul=' . rawurlencode($module);
      return trim($title) === '' ? $url : $url . '&help_title=' . rawurlencode($title);
   }

   public function form_button(string $module, string $form, string $title = ''): string {
      $module = trim($module);
      $form = trim($form);
      if (!$this->valid_module($module) || !preg_match('/^[a-zA-Z0-9_.:-]+$/', $form)) {
         return '';
      }
      $title = trim($title) !== '' ? trim($title) : ucwords(str_replace(array('-', '_', '.'), ' ', $form));
      return $this->tpl()->get_tpl('dbxHelp|help-button', array(
         'help_url' => $this->h($this->form_url($module, $form, $title)),
         'help_title' => $this->h($this->texts()->format_fd_message('help_prefix', array('title' => $title))),
      ));
   }

   public function module_bar_template_data(string $run1 = '', string $actions_html = '', string $title = '', string $icon = '', string $subtitle = '', string $module = '', string $run2 = ''): array {
      $context = $this->context($module, $run1, $run2);
      if (!$this->has($context['module'], $context['run1'], $context['run2'])) {
         return array();
      }
      $title = $title !== '' ? $title : $this->default_title($context);
      return array(
         'bar_class' => 'dbx-bar--module', 'bar_title_class' => 'dbx-bar-title',
         'bar_actions_class' => 'dbx-bar-actions', 'bar_title' => $title,
         'bar_icon' => $icon !== '' ? $icon : 'bi-grid', 'bar_subtitle' => $subtitle,
         'bar_title_pre' => '', 'bar_title_heading_attrs' => '',
         'bar_actions' => $actions_html,
         'bar_extra' => $this->button($context['module'], $context['run1'], $context['run2'], $title),
      );
   }

   public function render_module_bar(string $run1 = '', string $actions_html = '', string $title = '', string $icon = '', string $subtitle = '', string $module = '', string $run2 = ''): string {
      $data = $this->module_bar_template_data($run1, $actions_html, $title, $icon, $subtitle, $module, $run2);
      return $data ? trim($this->tpl()->get_tpl('dbx|module-bar', $data)) : '';
   }

   public function help_window_bar_template_data(string $module, string $run1 = '', string $run2 = '', string $title = ''): array {
      $context = $this->context($module, $run1, $run2);
      $title = trim($title) !== '' ? trim($title) : $this->default_title($context);
      return array(
         'bar_class' => 'dbx-bar--module dbx-help-context-bar', 'bar_title_class' => 'dbx-bar-title',
         'bar_actions_class' => 'dbx-bar-actions', 'bar_title' => $this->h($title),
         'bar_icon' => 'bi-question-circle', 'bar_subtitle' => $this->texts()->get_fd_message('context_help'),
         'bar_title_pre' => '', 'bar_title_heading_attrs' => '', 'bar_actions' => '',
         'bar_extra' => '', 'bar_middle' => '',
      );
   }

   public function form_help_window_bar_template_data(string $title = ''): array {
      $title = trim($title) !== '' ? trim($title) : $this->texts()->get_fd_message('form_help');
      return array(
         'bar_class' => 'dbx-bar--module dbx-help-context-bar', 'bar_title_class' => 'dbx-bar-title',
         'bar_actions_class' => 'dbx-bar-actions', 'bar_title' => $this->h($title),
         'bar_icon' => 'bi-question-circle', 'bar_subtitle' => $this->texts()->get_fd_message('form_help_subtitle'),
         'bar_title_pre' => '', 'bar_title_heading_attrs' => '', 'bar_actions' => '',
         'bar_extra' => '', 'bar_middle' => '',
      );
   }

   public function vars(string $run1 = '', string $run2 = '', string $module = ''): array {
      $data = $this->module_bar_template_data($run1, '', '', '', '', $module, $run2);
      if (!$data) {
         return array('help_button' => '', 'bar_title' => '', 'bar_icon' => 'bi-grid',
            'bar_subtitle' => '', 'bar_title_pre' => '', 'bar_title_heading_attrs' => '',
            'bar_class' => 'dbx-bar--module', 'bar_title_class' => 'dbx-bar-title',
            'bar_actions_class' => 'dbx-bar-actions', 'bar_actions' => '', 'bar_extra' => '');
      }
      return array_merge($data, array('help_button' => (string)($data['bar_extra'] ?? '')));
   }

   public function attach_form($form, string $run1 = '', string $run2 = '', string $module = ''): void {
      if (!is_object($form) || !method_exists($form, 'add_obj')) {
         return;
      }
      $context = $this->context($module, $run1, $run2);
      $title = $this->default_title($context);
      $form->add_obj('help_button', $this->button($context['module'], $context['run1'], $context['run2'], $title));
      if (method_exists($form, 'add_module_bar')) {
         $form->add_module_bar($title, 'bi-grid', '');
      }
   }

   public function run(): string {
      $obj = dbx()->get_include_obj('dbxModuleHelpWindow', 'dbxHelp');
      return is_object($obj) && method_exists($obj, 'run') ? (string)$obj->run() : '';
   }
}
