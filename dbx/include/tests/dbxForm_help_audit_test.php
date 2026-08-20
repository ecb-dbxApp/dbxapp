<?php

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxCssTestReader.php';
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$modules = $root . DIRECTORY_SEPARATOR . 'modules';
$errors = array();
$checked = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modules, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
   if (!$file->isFile() || strtolower($file->getExtension()) !== 'htm') {
      continue;
   }
   $html = (string)file_get_contents($file->getPathname());
   if (!preg_match('/<form\b/i', $html)) {
      continue;
   }

   $has_bar = preg_match('/\[tpl=dbx\|(module-bar|panel-shell-head|frame-head)\]/i', $html)
      || preg_match('/class="[^"]*(dbx-bar|dbx-auth-panel-head)[^"]*"/i', $html);
   if (!$has_bar) {
      continue;
   }
   $checked++;

   $uses_standard_bar = preg_match('/\[tpl=dbx\|(module-bar|panel-shell-head|frame-head)\]/i', $html);
   $has_help_slot = strpos($html, '{bar_extra}') !== false
      || strpos($html, '{help_button}') !== false
      || strpos($html, '{obj:help_button}') !== false;
   if (!$uses_standard_bar && !$has_help_slot) {
      $errors[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
   }
}

if ($checked === 0) {
   fwrite(STDERR, "Keine Formularleisten gefunden.\n");
   exit(1);
}

$form_class = dbx_test_module_source_bundle($root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxForm.class.php');
$report_class = dbx_test_module_source_bundle($root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxReport.class.php');
$fallback_template = $modules . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'form-bar-default.htm';
if (strpos($form_class, 'ensure_form_bar($content)') === false
   || strpos($report_class, 'prepare_report_chrome_replaces()') === false
   || strpos($report_class, "public \$_tpl_report_bar = 'dbx|report-bar-default'") === false
   || strpos($report_class, "add_rep('report:bar', \$bar)") === false
   || strpos($report_class, "add_rep('report:footer', \$footer)") === false
   || strpos($form_class, "public \$_tpl_form_bar = 'dbx|form-bar-default'") === false
   || strpos($form_class, "public \$_tpl_form_footer = 'dbx|form-footer-default'") === false
   || strpos($form_class, "add_rep('form:bar', \$bar)") === false
   || strpos($form_class, "add_rep('form:footer', \$footer)") === false
   || strpos($form_class, 'set_form_help_enabled(bool $enabled = true)') === false
   || strpos($form_class, "add_obj('help_button', \$help->button") !== false
   || !preg_match("/add_obj\\(\\s*'help_button'\\s*,\\s*'obj-value'/", $form_class)
   || !is_file($fallback_template)) {
   fwrite(STDERR, "Zentraler Form-Bar-/Footer-Vertrag fuer dbxForm/dbxReport fehlt.\n");
   exit(1);
}
if ($errors) {
   fwrite(STDERR, "Formularleisten ohne Help-Slot:\n - " . implode("\n - ", $errors) . "\n");
   exit(1);
}

$help_button_template = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxHelp' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'help-button.htm');
$dbxapp_theme = dbx_test_read_css($root . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'dbxapp' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'theme.css');
$flowers_theme = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'flowers' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'theme.css');
$flowers_form = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'flowers' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'c-form.css');
$shop_channel_template = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxShop_admin' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'shop-channel-form.htm');
$dashboard_class = dbx_test_module_source_bundle($modules . DIRECTORY_SEPARATOR . 'dbxAdmin' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxDashboard.class.php');
$dashboard_cache_actions = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxAdmin' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'admin-dashboard-content-cache-actions.htm');
$context_help_class = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxHelp' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxModuleHelpWindow.class.php');
$help_class = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxHelp' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxModuleHelp.class.php');
if (strpos($help_button_template, 'dbx-help-action') === false
   || strpos($dbxapp_theme, '.dbx-help-action') === false
   || strpos($flowers_theme, '.dbx-help-action') === false
   || strpos($flowers_form, '.dbx-auth-panel-actions') === false
   || strpos($shop_channel_template, '{channel_edit_button}{channel_help_button}') === false
   || strpos($dashboard_class, "add_rep('bar_extra', \$this->help_action('cache'))") === false
   || substr_count($dashboard_class, 'set_form_help_enabled(false)') < 7
   || strpos($dashboard_cache_actions, "</form>\n{bar_extra}") === false) {
   fwrite(STDERR, "Help-Buttons sind nicht in allen Designs und Sonderleisten als letzte rechte Aktion abgesichert.\n");
   exit(1);
}

if (strpos($context_help_class, "\$help->render(\$context['module'], \$context['run1'], \$context['run2'])") === false
   || strpos($help_class, "get_help_tpl") === false
   || strpos($help_class, "cfg/help.php") !== false
   || strpos($help_class, "['run1'] . '--' . \$context['run2']") === false) {
   fwrite(STDERR, "Kontext-Hilfe wird nicht aus dem autonomen Modulvertrag gerendert.\n");
   exit(1);
}

echo "OK: {$checked} Formular-Templates mit Leiste besitzen einen zentralen oder eigenen Help-Slot.\n";
