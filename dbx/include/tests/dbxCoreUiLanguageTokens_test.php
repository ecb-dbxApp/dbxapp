<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

dbx()->set_system_var('dbx_modul', 'dbx');
dbx()->set_system_var('dbx_activ_modul', 'dbx');
dbx()->set_system_var('dbx_run1', 'test');
dbx()->set_system_var('dbx_run2', '');

$tpl = dbx()->get_system_obj('dbxTPL');
$failures = array();
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$expectations = array(
    'de' => array('Die Daten wurden gespeichert.', 'Suche zurücksetzen'),
    'en' => array('The data was saved.', 'Reset the search'),
    'es' => array('Los datos se guardaron.', 'Restablecer la búsqueda'),
);

foreach ($expectations as $language => $phrases) {
    dbx()->set_system_var('dbx_lng', $language);
    $html = $tpl->get_tpl('dbx|form-message-save-success', array())
        . $tpl->get_tpl('dbx|search', array(
            'wrap_class' => '', 'data_role' => 'test', 'wrap_style' => '',
            'name' => 'q', 'i' => '1', 'input_class' => '', 'class' => '',
            'placeholder' => '', 'value' => '', 'errormsg' => '', 'extra_attrs' => '',
        ));

    foreach ($phrases as $phrase) {
        $check(str_contains($html, $phrase), $language . ': UI-Text fehlt: ' . $phrase);
    }
    $check(!str_contains($html, '{ui:'), $language . ': nicht aufgeloester UI-Token.');
}

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK zentrale UI-Templates rendern Deutsch, Englisch und Spanisch ohne Markup-Kopien.\n";
