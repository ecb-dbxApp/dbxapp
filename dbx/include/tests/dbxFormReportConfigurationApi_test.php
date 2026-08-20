<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$form = (string)file_get_contents($root . '/include/dbxForm.class.php');
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$report = dbx_test_module_source_bundle($root . '/include/dbxReport.class.php');
$reference = (string)file_get_contents(
    $root . '/modules/myInvoices/include/myInvoicesService.class.php'
);
$errors = array();

$required_form_methods = array(
    'set_data_source',
    'set_data_definition',
    'set_field_definition',
    'set_fd_messages',
    'get_fd_messages',
    'set_action',
    'set_mode',
    'set_data',
    'merge_data',
    'set_data_value',
    'unset_data_value',
    'get_data',
    'set_rid',
    'current_rid',
    'set_post_value',
    'unset_post_value',
    'has_post_value',
    'post_value',
    'validated_post',
);
foreach ($required_form_methods as $method) {
    if (!preg_match('/public function ' . preg_quote($method, '/') . '\s*\(/', $form)) {
        $errors[] = 'dbxForm-API fehlt: ' . $method;
    }
}

$required_report_methods = array(
    'set_table_actions',
    'set_pagination',
    'set_page_size',
    'set_report_fields',
    'set_report_result',
    'set_report_counts',
    'set_report_tpl',
    'set_report_bar_tpl',
    'set_report_footer_tpl',
);
foreach ($required_report_methods as $method) {
    if (!preg_match('/public function ' . preg_quote($method, '/') . '\s*\(/', $report)) {
        $errors[] = 'dbxReport-API fehlt: ' . $method;
    }
}

if (preg_match('/->\_(?:tpl|mode|action|rid|dd|fd|create_row|table_tpls|messages|post|data)\b/', $reference)) {
    $errors[] = 'Das Referenzmodul myInvoices umgeht noch die Konfigurations-API.';
}

if ($errors !== array()) {
    fwrite(STDERR, "FAIL Form-/Report-Konfigurations-API:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "OK einheitliche Form-/Report-Konfigurations-API und migriertes Referenzmodul.\n";
