<?php
namespace dbx\dbxHelp;

/** Rendert modulautonome Hilfe ohne CMS-, Datenbank- oder Topic-Abhängigkeit. */
class dbxModuleHelpWindow {

   private function tpl() {
      return dbx()->get_system_obj('dbxTPL');
   }

   private function alert(string $message, string $type = 'info'): string {
      return $this->tpl()->get_tpl('dbx|alert-' . $type, array(
         'msg' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
      ));
   }

   public function render_form_help(string $module, string $form, string $title = ''): string {
      $module = trim($module);
      $form = trim($form);
      if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $module)
         || !preg_match('/^[a-zA-Z0-9_.:-]+$/', $form)) {
         return $this->alert('Der Formular-Kontext ist ungültig.', 'danger');
      }
      if (!dbx()->has_module_access($module)) {
         return $this->alert('Für diese Hilfe fehlt die Zugriffsberechtigung.', 'danger');
      }
      $title = trim($title) !== '' ? trim($title) : ucwords(str_replace(array('-', '_', '.'), ' ', $form));
      $registry = dbx()->get_include_obj('dbxModuleRegistry', 'dbxAdmin');
      $detail = is_object($registry) && method_exists($registry, 'renderFormHelp')
         ? (string)$registry->render_form_help($module, $form, array(
            'modul' => htmlspecialchars($module, ENT_QUOTES, 'UTF-8'),
            'form' => htmlspecialchars($form, ENT_QUOTES, 'UTF-8'),
            'form_title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
         )) : '';
      $detail = str_ireplace(
         array('[modul=', '[/modul]', '[tpl=', '[dbx:'),
         array('&#91;modul=', '&#91;/modul]', '&#91;tpl=', '&#91;dbx:'),
         $detail
      );
      return $this->tpl()->get_tpl('dbxHelp|help-form', array(
         'form_title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
         'form_id' => htmlspecialchars($form, ENT_QUOTES, 'UTF-8'),
         'form_modul' => htmlspecialchars($module, ENT_QUOTES, 'UTF-8'),
         'form_detail' => $detail,
      ));
   }

   public function run(): string {
      dbx()->set_system_var('dbx_page', '_window');
      $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
      if (!is_object($help)) {
         return '';
      }

      $module = trim((string)dbx()->get_modul_var('help_modul', '', 'parameter'));
      $form = trim((string)dbx()->get_modul_var('help_form', '', 'parameter'));
      $title = trim((string)dbx()->get_modul_var('help_title', '', 'varchar'));
      if ($form !== '') {
         $content = $this->render_form_help($module, $form, $title);
         $bar = $help->form_help_window_bar_template_data($title);
         $frame_key = $module . '_form_' . $form;
      } else {
         $run1 = trim((string)dbx()->get_modul_var('help_run1', '', 'parameter'));
         $run2 = trim((string)dbx()->get_modul_var('help_run2', '', 'parameter'));
         $context = $help->context($module, $run1, $run2);
         $content = $context['module'] !== '' && dbx()->has_module_access($context['module'])
            ? $help->render($context['module'], $context['run1'], $context['run2'])
            : $this->alert('Für diese Hilfe fehlt die Zugriffsberechtigung.', 'danger');
         $bar = $help->help_window_bar_template_data($context['module'], $context['run1'], $context['run2'], $title);
         $frame_key = implode('_', $context);
      }

      if (trim(strip_tags((string)$content)) === '') {
         $content = $this->alert('Für diesen Bereich ist noch keine Hilfe hinterlegt.');
      }
      return $this->tpl()->get_tpl('dbxHelp|help-shell', array_merge($bar, array(
         'frame_id' => 'dbx_context_help_' . preg_replace('/[^a-z0-9_-]+/i', '_', $frame_key),
         'frame_panel_class' => 'dbx-admin-help py-3 dbx-context-help-preview',
         'frame_form_open' => '', 'frame_form_close' => '', 'frame_subbar' => '',
         'frame_body_class' => 'dbx-admin-help-body dbx-context-help-body',
         'frame_body_head' => '', 'frame_body_tail' => '', 'frame_panel_attrs' => '',
         'content' => $content,
      )));
   }
}
