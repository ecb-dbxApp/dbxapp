<?php

require_once dirname(__DIR__) . '/include/dbxWizard.class.php';

$wizard = new \dbx\dbxAdmin\dbxWizard();
$call = static function($object, $method, array $args = array()) {
   $reflection = new ReflectionMethod($object, $method);
   return $reflection->invokeArgs($object, $args);
};
$assertContains = static function($needle, $haystack, $label) {
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
$assertContains("\$table['primary']='id';", $dd, 'DD primary key');
$assertContains("\$field['name']='owner';", $dd, 'DD owner field');
$assertContains("\$field['name']='trash';", $dd, 'DD trash field');

$formTemplate = $call($wizard, 'generate_form_template');
$assertContains('[dbx:form]', $formTemplate, 'form field placeholder');
$assertContains('{obj:form_msg}', $formTemplate, 'form message placeholder');

$reportTemplate = $call($wizard, 'generate_report_template');
$assertContains('[tpl=dbx|report-shell-head]', $reportTemplate, 'report shell header');
$assertContains('[rpt:row]', $reportTemplate, 'report row placeholder');

$service = $call($wizard, 'generate_service_class', array($input));
$assertContains("add_rep('bar_title', 'Datensaetze')", $service, 'report title setup');
$assertContains('Felder ausfuellen und mit Speichern uebernehmen.', $service, 'form help text');

echo "OK dbxWizard generation\n";
