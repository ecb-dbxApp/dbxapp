<?php

$root = dirname(__DIR__, 2);
$modulesRoot = $root . '/modules';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

/**
 * Bewusste Ausnahmen vom zentralen Standardweg.
 *
 * Diese Aktionen besitzen keine dbxReport-/delete-/save-RID-Semantik oder
 * transportieren komplexe JSON-/Workflow-Kommandos. Darum bleiben Erzeugung
 * und Pruefung ihres fachlichen Scopes vorerst im jeweiligen Dienst.
 */
$manualScopeAllowlist = array(
   'dbxAdmin/include/dbxUser.class.php' =>
      'verify, lock, unlock und reset_password mit RID',
   'dbxContent_admin/include/dbxContentCmsCoreService.trait.php' =>
      'Implementierung des gemeinsamen Scopes fuer CMS-JSON, Upload, Medien- und Sprachaktionen',
   'dbxContent_admin/include/dbxContent_cms.class.php' =>
      'kleiner CMS-Einstieg ruft den gemeinsamen manuellen Scope-Guard vor dem Dispatch auf',
   'dbxContent_admin/include/dbxContent_seo.class.php' =>
      'SEO-JSON und gemeinsam genutzte CMS-Medienendpunkte',
   'dbxKi/include/dbxKiCmsCoreService.trait.php' =>
      'zweistufige plan/execute-API mit expliziter Bestaetigung',
   'dbxKi/include/dbxKiCmsDescribeService.trait.php' =>
      'zweistufige plan/execute-API mit expliziter Bestaetigung',
   'dbxKi/include/dbxKiCmsBundleService.trait.php' =>
      'zweistufige plan/execute-API mit expliziter Bestaetigung',
   'dbxKi/include/dbxKiModuleBriefingService.class.php' =>
      'zweistufige Modul-preview/execute-API und gebundene Browser-Vorschau',
   'dbxLogin/include/login.class.php' =>
      'Tokenausgabe fuer erneuten Versand der Registrierung',
   'dbxLogin/include/register.class.php' =>
      'Tokenpruefung fuer erneuten Versand der Registrierung',
   'dbxSetup/include/dbxInstall.class.php' =>
      'zustandsgebundene Schritte des Erstinstallationsassistenten',
   'dbxSelfTest/include/dbxSelfTestController.class.php' =>
      'JSON-Testorchestrierung mit eigenem, admininternem Aktionsscope',
   'dbxShop_admin/include/dbxShopAdmin.class.php' =>
      'Shop-Sammel-, Medien-, Installations- und Statusaktionen',
   'dbxShop_admin/include/dbxShopAdminProductActionService.trait.php' =>
      'Produkt-Medienaktionen erzeugen den bestehenden CMS-Scope fuer den gemeinsamen Medienendpunkt',
   'dbxWorkflow/include/dbxWorkflowEngine.class.php' =>
      'Workflow-Start und instanzgebundene Prozesskommandos',
   'dbxWorkflow_admin/include/dbxWorkflowAdmin.class.php' =>
      'Erzeugung instanzgebundener Workflow-Fortsetzungslinks',
);

$calls = array('action_token', 'check_action_token');
$iterator = new RecursiveIteratorIterator(
   new RecursiveDirectoryIterator($modulesRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
   if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
      continue;
   }

   $path = str_replace('\\', '/', $file->getPathname());
   if (strpos($path, '/tests/') !== false) {
      continue;
   }

   $relative = ltrim(substr($path, strlen(str_replace('\\', '/', $modulesRoot))), '/');
   $source = (string)file_get_contents($file->getPathname());
   $tokens = token_get_all($source);
   $found = array();

   foreach ($tokens as $token) {
      if (!is_array($token) || $token[0] !== T_STRING) {
         continue;
      }
      $name = strtolower((string)$token[1]);
      if (in_array($name, $calls, true)) {
         $found[$name] = true;
      }
   }

   if ($found && !isset($manualScopeAllowlist[$relative])) {
      $fail(
         'Nicht dokumentierte manuelle Action-Token-Logik in ' . $relative
         . ': ' . implode(', ', array_keys($found)),
         1
      );
   }

   if (strpos($source, 'enable_delete_tab(') !== false
       && isset($found['check_action_token'])) {
      $fail(
         'dbxReport delete_tab wird in ' . $relative
         . ' zusaetzlich im Modul geprueft.',
         2
      );
   }
}

foreach ($manualScopeAllowlist as $relative => $reason) {
   $file = $modulesRoot . '/' . $relative;
   if (!is_file($file)) {
      $fail('Dokumentierte Token-Ausnahme fehlt: ' . $relative, 3);
   }
}

$formSource = (string)file_get_contents($root . '/include/dbxForm.class.php');
if (strpos($formSource, 'substr($secure') !== false
    || strpos($formSource, 'substr($posted') !== false) {
   $fail('dbxForm protokolliert weiterhin Teile eines Security-Tokens.', 4);
}

echo 'OK dbx action token usage audit ('
   . count($manualScopeAllowlist)
   . " documented exceptions)\n";
