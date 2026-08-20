<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$form = dbx_test_module_source_bundle($root . '/include/dbxForm.class.php');
$tpl = (string)file_get_contents($root . '/include/dbxTPL.class.php');
$core = $root . '/modules/dbx/tpl/htm/';
$errors = array();
$check = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) $errors[] = $message;
};

foreach (array('field-input-default', 'field-select-default', 'field-textarea-default', 'field-checkbox-default') as $name) {
    $check(is_file($core . $name . '.htm'), 'Zentrales Feldtemplate fehlt: ' . $name);
}
foreach (array('alert-default', 'field-status-default', 'button-action-submit-default', 'button-action-link-default') as $name) {
    $check(is_file($core . $name . '.htm'), 'Zentrales UI-Template fehlt: ' . $name);
}
foreach (array('table_header_action', 'table_row_action') as $name) {
    $check(is_file($core . $name . '.htm'), 'Zentrales Report-Aktionstemplate fehlt: ' . $name);
}

$check(str_contains($form, 'normalize_standard_field_tpl'), 'Semantische Feldnormalisierung fehlt.');
$check(str_contains($tpl, 'normalize_core_ui_template'), 'Semantische UI-Normalisierung fehlt.');

$removed = array(
    'text-label', 'text', 'integer-label', 'date-label', 'password-label',
    'select-single-label', 'select-single', 'select-multiple-label',
    'multi-select-label', 'multi-select', 'multiselect2',
    'textarea-label', 'textarea', 'textarea-tpl', 'checkbox-label',
    'alert-info', 'alert-success', 'alert-warning', 'alert-danger',
    'fld-alert-info', 'fld-alert-success', 'fld-alert-warning', 'fld-alert-danger',
    'button-bar-save', 'button-bar-filter', 'button-bar-reload',
    'button-bar-reload-ajax', 'button-bar-delete', 'component-bar',
    'table_header_copy', 'table_header_delete', 'table_header_download',
    'table_header_edit', 'table_header_expand', 'table_header_expander',
    'table_header_export', 'table_header_import', 'table_header_print',
    'table_header_show', 'table_row_copy', 'table_row_delete',
    'table_row_download', 'table_row_edit', 'table_row_expand',
    'table_row_export', 'table_row_import', 'table_row_modal-edit',
    'table_row_modal-show', 'table_row_print', 'table_row_show',
);
foreach ($removed as $name) {
    foreach (array('', '_en', '_es') as $suffix) {
        $check(!is_file($core . $name . $suffix . '.htm'), 'Redundantes Template existiert noch: ' . $name . $suffix);
    }
}

$language_token_families = array(
    'action_button_delete', 'action_button_delete_tab', 'auth-password-label',
    'form-action-delete-hint', 'form-action-delete-title', 'form-footer-default',
    'form-message-delete-error', 'form-message-delete-success',
    'form-message-save-error', 'form-message-save-success',
    'form-message-validation-error', 'form-message-warning', 'pagination',
    'report-bar-default', 'report-footer-action-main', 'search',
);
foreach ($language_token_families as $name) {
    $source = is_file($core . $name . '.htm') ? (string)file_get_contents($core . $name . '.htm') : '';
    $check(str_contains($source, '{ui:'), 'Zentrales Sprach-Token fehlt: ' . $name);
    foreach (array('_en', '_es') as $suffix) {
        $check(!is_file($core . $name . $suffix . '.htm'), 'Sprachkopie existiert noch: ' . $name . $suffix);
    }
}
$check(str_contains($tpl, 'replace_core_ui_tokens'), 'Zentrale UI-Sprachauflösung fehlt.');

if ($errors !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "OK zentrale Feld-, Status-, Button- und Layouttemplates ohne redundante Markup-Familien.\n";
