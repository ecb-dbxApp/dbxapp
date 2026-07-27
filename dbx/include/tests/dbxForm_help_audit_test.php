<?php

$root = dirname(__DIR__, 2);
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

   $hasBar = preg_match('/\[tpl=dbx\|(module-bar|form-shell-head|frame-head)\]/i', $html)
      || preg_match('/class="[^"]*(dbx-bar|dbx-auth-panel-head)[^"]*"/i', $html);
   if (!$hasBar) {
      continue;
   }
   $checked++;

   $usesStandardBar = preg_match('/\[tpl=dbx\|(module-bar|form-shell-head|frame-head)\]/i', $html);
   $hasHelpSlot = strpos($html, '{bar_extra}') !== false
      || strpos($html, '{help_button}') !== false
      || strpos($html, '{obj:help_button}') !== false;
   if (!$usesStandardBar && !$hasHelpSlot) {
      $errors[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
   }
}

if ($checked === 0) {
   fwrite(STDERR, "Keine Formularleisten gefunden.\n");
   exit(1);
}

$formClass = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxForm.class.php');
$reportClass = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxReport.class.php');
$fallbackTemplate = $modules . DIRECTORY_SEPARATOR . 'dbx' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'form-help-bar.htm';
if (strpos($formClass, 'ensureFormHelpBar($content)') === false
   || strpos($reportClass, 'ensureFormHelpBar($report_tpl)') === false
   || strpos($formClass, 'set_form_help_enabled(bool $enabled = true)') === false
   || !is_file($fallbackTemplate)) {
   fwrite(STDERR, "Zentrale Help-Leiste fuer dbxForm/dbxReport-Templates ohne eigenen Bar-Slot fehlt.\n");
   exit(1);
}
if ($errors) {
   fwrite(STDERR, "Formularleisten ohne Help-Slot:\n - " . implode("\n - ", $errors) . "\n");
   exit(1);
}

$helpButtonTemplate = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxAdmin' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'admin-help-button.htm');
$dbxappTheme = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'dbxapp' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'theme.css');
$flowersTheme = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'flowers' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'theme.css');
$flowersForm = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'flowers' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'c-form.css');
$shopChannelTemplate = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxShop_admin' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'shop-channel-form.htm');
$dashboardClass = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxAdmin' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxDashboard.class.php');
$dashboardCacheActions = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxAdmin' . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm' . DIRECTORY_SEPARATOR . 'admin-dashboard-content-cache-actions.htm');
$contextHelpClass = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxContent' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxContentContextHelp.class.php');
$contentClass = (string)file_get_contents($modules . DIRECTORY_SEPARATOR . 'dbxContent' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxContent_content.class.php');
if (strpos($helpButtonTemplate, 'dbx-help-action') === false
   || strpos($dbxappTheme, '.dbx-help-action') === false
   || strpos($flowersTheme, '.dbx-help-action') === false
   || strpos($flowersForm, '.dbx-auth-panel-actions') === false
   || strpos($shopChannelTemplate, '{channel_edit_button}{channel_help_button}') === false
   || strpos($dashboardClass, "add_rep('bar_extra', \$this->help_action('cache'))") === false
   || substr_count($dashboardClass, 'set_form_help_enabled(false)') < 7
   || strpos($dashboardCacheActions, "</form>\n{bar_extra}") === false) {
   fwrite(STDERR, "Help-Buttons sind nicht in allen Designs und Sonderleisten als letzte rechte Aktion abgesichert.\n");
   exit(1);
}

if (strpos($contextHelpClass, "'template' => 'c-content-help'") === false
   || strpos($contextHelpClass, "'wrap' => false") === false
   || strpos($contentClass, "\$renderOptions['template'] = \$forcedTemplate") === false) {
   fwrite(STDERR, "Kontext-Hilfe wird nicht mit dem medienfreien Hilfe-Template gerendert.\n");
   exit(1);
}

echo "OK: {$checked} Formular-Templates mit Leiste besitzen einen zentralen oder eigenen Help-Slot.\n";
