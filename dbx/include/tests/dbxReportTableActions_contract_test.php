<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once __DIR__ . '/dbxModuleSourceBundle.php';
$source = dbx_test_module_source_bundle($root . '/include/dbxReport.class.php');
$header = (string)file_get_contents($root . '/modules/dbx/tpl/htm/table_header_action.htm');
$row = (string)file_get_contents($root . '/modules/dbx/tpl/htm/table_row_action.htm');
$failures = array();
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

foreach (array('edit', 'copy', 'show', 'export', 'import', 'download', 'delete', 'print') as $type) {
    $check(
        str_contains($source, "'type'       => '" . $type . "'")
            && str_contains($source, "'" . $type . "'")
            && str_contains($source, 'table_header_action')
            && str_contains($source, 'table_row_action'),
        'Zentrale Definition fehlt fuer Report-Aktion: ' . $type
    );
}
foreach (array('{header_class}', '{icon}', '{title}', '{tooltip}') as $token) {
    $check(str_contains($header, $token), 'Header-Token fehlt: ' . $token);
}
foreach (array('{cell_class}', '{link_class}', '{href}', '{link_attributes}', '{icon}', '{accessible_title}') as $token) {
    $check(str_contains($row, $token), 'Row-Token fehlt: ' . $token);
}
$check(str_contains($source, 'function set_table_action_options'), 'API fuer abweichende Aktionsdarstellung fehlt.');
$check(str_contains($source, 'function prepare_table_header_action_data'), 'Header-Normalisierung fehlt.');
$check(str_contains($source, 'function prepare_table_row_action_data'), 'Row-Normalisierung fehlt.');
$check(str_contains($source, 'dbxAjax dbxConfirm'), 'Sichere Loeschbestaetigung fehlt.');

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK einheitliche parametrisierte Tabellenaktionen mit Modul-Overrides.\n";
