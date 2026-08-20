<?php

$root = dirname(__DIR__, 2);
$modules = $root . DIRECTORY_SEPARATOR . 'modules';
$errors = array();
$module_count = 0;
$template_count = 0;

foreach (glob($modules . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: array() as $module_dir) {
   $module = basename($module_dir);
   $module_count++;
   $help_dir = $module_dir . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'help';
   if (!is_dir($help_dir)) {
      $errors[] = $module . ': tpl/help fehlt';
      continue;
   }
   if (!is_file($help_dir . DIRECTORY_SEPARATOR . 'modul.htm')) {
      $errors[] = $module . ': tpl/help/modul.htm fehlt';
   }
   $legacy_dir = $module_dir . DIRECTORY_SEPARATOR . 'tpl' . DIRECTORY_SEPARATOR . 'htm';
   if ($module !== 'dbxMenu') {
      foreach (glob($legacy_dir . DIRECTORY_SEPARATOR . 'modul-help*.htm') ?: array() as $legacy) {
         if (basename($legacy) !== 'modul-help-fallback.htm'
            && basename($legacy) !== 'modul-help-fallback_en.htm'
            && basename($legacy) !== 'modul-help-fallback_es.htm') {
            $errors[] = $module . ': altes Modulhilfe-Template ' . basename($legacy);
         }
      }
      foreach (glob($legacy_dir . DIRECTORY_SEPARATOR . 'form-help-*.htm') ?: array() as $legacy) {
         $errors[] = $module . ': altes Formularhilfe-Template ' . basename($legacy);
      }
   }

   if (is_file($module_dir . DIRECTORY_SEPARATOR . 'cfg' . DIRECTORY_SEPARATOR . 'help.php')) {
      $errors[] = $module . ': redundantes cfg/help.php vorhanden';
   }
   foreach (glob($help_dir . DIRECTORY_SEPARATOR . '*.htm') ?: array() as $template) {
      $name = preg_replace('/_(en|es)$/', '', pathinfo($template, PATHINFO_FILENAME));
      if (!preg_match('/^[a-z0-9][a-z0-9_]*(?:--[a-z0-9][a-z0-9_]*)?$/', (string)$name)
         && !str_starts_with((string)$name, 'form-')) {
         $errors[] = $module . ': nicht semantischer Hilfedateiname ' . basename($template);
      }
      $template_count++;
   }
}

$admin_help = (string)file_get_contents($modules . '/dbxHelp/include/dbxModuleHelp.class.php');
$context_help = (string)file_get_contents($modules . '/dbxHelp/include/dbxModuleHelpWindow.class.php');
$ki_help = (string)file_get_contents($modules . '/dbxKi/include/dbxKiHelp.class.php');
$shop_help = (string)file_get_contents($modules . '/dbxShop_admin/include/dbxShopAdminHelpContentService.trait.php');
$combined = $admin_help . $context_help . $ki_help . $shop_help;
foreach (array('dbxContentContextHelp', 'dbxKiCmsHelpProvision', 'ensureShopAdminHelpPage', 'renderPage($cid') as $forbidden) {
   if (strpos($combined, $forbidden) !== false) {
      $errors[] = 'Veraltete CMS-Hilfe-Abhängigkeit: ' . $forbidden;
   }
}
if (strpos($admin_help, '?dbx_modul=dbxHelp&dbx_run1=context') === false
   || strpos($admin_help, "['run1'] . '--' . \$context['run2']") === false
   || strpos($admin_help, "\$names[] = 'modul'") === false) {
   $errors[] = 'Neue Hilfe-URL zeigt nicht auf den systemweiten Renderer.';
}

if ($errors) {
   fwrite(STDERR, "Modulhilfe-Vertrag verletzt:\n - " . implode("\n - ", $errors) . "\n");
   exit(1);
}
echo "OK: {$module_count} Module mit tpl/help, {$template_count} konventionsbasierte Hilfetemplates.\n";
