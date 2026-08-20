<?php

require_once dirname(__DIR__) . '/include/dbxWizard.class.php';

$wizard = new \dbx\dbxAdmin\dbxWizard();
$call = static function($object, $method, array $args = array()) {
   $reflection = new ReflectionMethod($object, $method);
   return $reflection->invokeArgs($object, $args);
};
$assert_contains = static function($needle, $haystack, $label) {
   if (strpos((string)$haystack, (string)$needle) === false) {
      fwrite(STDERR, "FAIL: $label\n");
      exit(1);
   }
};

$input = array(
   'xmodul' => 'wizardTest',
   'title' => 'Wizard Test',
   'default_run1' => 'run',
   'module_template' => 'form_report',
   'dd_mode' => 'new',
   'dd_name' => 'wizardTestData',
   'table_name' => 'wizardTestData',
   'db_file' => 'wizardTest.db3',
   'field_preset' => 'basic',
   'create_include' => 1,
   'create_form' => 1,
   'create_report' => 1,
   'create_templates' => 1,
);

$dd = $call($wizard, 'generate_dd', array($input));
$assert_contains("\$table['primary']='id';", $dd, 'DD primary key');
$assert_contains("\$field['name']='owner';", $dd, 'DD owner field');
$assert_contains("\$field['name']='trash';", $dd, 'DD trash field');

$form_template = $call($wizard, 'generate_form_template');
$assert_contains('[dbx:form]', $form_template, 'form field placeholder');
$assert_contains('{form:bar}', $form_template, 'form bar placeholder');
$assert_contains('{form:message}', $form_template, 'form message placeholder');
$assert_contains('{form:footer}', $form_template, 'form footer placeholder');

$service = $call($wizard, 'generate_service_class', array($input));
$assert_contains("init('wizardTest-form', 'wizardTest|wizardtest-form')", $service, 'individuelles Formular-Template');
$assert_contains("init('wizardTest-report')", $service, 'Report nutzt das Default-Template');
$assert_contains("set_table_actions(array('select', 'edit', 'show', 'delete'))", $service, 'deklarative Reportaktionen');
$assert_contains('add_module_bar_form_actions', $service, 'gemeinsame Formularaktionen');
$assert_contains("get_tpl('dbx|form-message-' . \$name)", $service, 'gemeinsame Standardmeldungen');

$source = (string)file_get_contents(dirname(__DIR__) . '/include/dbxWizard.class.php');
if (strpos($source, "namespace dbx\\__MODUL__;") !== false) {
   fwrite(STDERR, "FAIL: Service-Codevorlage ist noch in dbxWizard eingebettet\n");
   exit(1);
}
if (!is_file(dirname(__DIR__) . '/include/templates/module-service.template.php')) {
   fwrite(STDERR, "FAIL: externe Service-Codevorlage fehlt\n");
   exit(1);
}

$main = $call($wizard, 'generate_main_class', array($input));
$assert_contains("get_system_obj('dbxActionManifest')->action('wizardTest'", $main, 'deklaratives Modulrouting');
if (strpos($main, 'switch ($run)') !== false) {
   fwrite(STDERR, "FAIL: generierter Router enthaelt noch einen manuellen Switch\n");
   exit(1);
}

$actions_php = $call($wizard, 'generate_actions_manifest', array($input));
$package_json = $call($wizard, 'generate_package_manifest', array($input));
$package = json_decode($package_json, true);
if (!is_array($package) || ($package['id'] ?? '') !== 'local/module/wizardTest') {
   fwrite(STDERR, "FAIL: Paketmanifest des generierten Moduls ist ungueltig\n");
   exit(1);
}

$report_en = $call($wizard, 'generate_report_fd', array('en'));
$form_es = $call($wizard, 'generate_form_fd', array($input, 'es'));
$assert_contains("\$messages['report_title']='Records';", $report_en, 'englische Reporttexte');
$assert_contains("\$messages['form_title_new']='Nuevo registro';", $form_es, 'spanische Formulartexte');

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dbx-wizard-' . bin2hex(random_bytes(6));
if (!mkdir($tmp, 0777, true) && !is_dir($tmp)) {
   fwrite(STDERR, "FAIL: Temp-Verzeichnis konnte nicht erstellt werden\n");
   exit(1);
}
$files = array(
   'wizardTest.class.php' => $main,
   'wizardTestService.class.php' => $service,
   'actions.php' => $actions_php,
);
foreach ($files as $name => $contents) {
   $path = $tmp . DIRECTORY_SEPARATOR . $name;
   file_put_contents($path, $contents);
   exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path), $lint, $status);
   if ($status !== 0) {
      fwrite(STDERR, "FAIL: generierte Datei ist syntaktisch ungueltig: $name\n");
      exit(1);
   }
   $lint = array();
}
$actions = require $tmp . DIRECTORY_SEPARATOR . 'actions.php';
require_once $tmp . DIRECTORY_SEPARATOR . 'wizardTestService.class.php';
$service_reflection = new ReflectionClass('dbx\\wizardTest\\wizardTestService');
if (!is_array($actions) || !isset($actions['form'], $actions['report'], $actions['api'])
   || !$service_reflection->hasMethod('form') || !$service_reflection->hasMethod('report')) {
   fwrite(STDERR, "FAIL: generiertes Beispielmodul ist nicht funktionsfaehig aufgebaut\n");
   exit(1);
}
foreach (array_keys($files) as $name) {
   @unlink($tmp . DIRECTORY_SEPARATOR . $name);
}
@rmdir($tmp);

echo "OK dbxWizard generation\n";
