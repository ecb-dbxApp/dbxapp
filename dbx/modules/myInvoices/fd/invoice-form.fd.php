<?php
$messages = array();
$messages['form_info'] = 'Rechnungskopf prüfen und speichern.';
$messages['form_title_new'] = 'Neue Rechnung';
$messages['form_title_edit'] = 'Rechnung bearbeiten';
$messages['help_title'] = 'Rechnungsformular';
$messages['not_found'] = 'Rechnung nicht gefunden.';
$messages['validation_error'] = 'Bitte Eingaben prüfen.';

/**
 * Sichtbare und editierbare Felder des Rechnungskopfs.
 */

$fields = array();

$add_field = function (
    string $name,
    string $type,
    string $tpl,
    string $label,
    string $rules,
    array $extra = array()
) use (&$fields): void {
    $field = array();
    $field['name'] = $name;
    $field['type'] = $type;
    $field['index'] = '';
    $field['length'] = $extra['length'] ?? '';
    $field['default'] = $extra['default'] ?? '';
    $field['label'] = $label;
    $field['rules'] = $rules;
    $field['tooltip'] = $extra['tooltip'] ?? '';
    $field['errormsg'] = $extra['errormsg'] ?? '';
    $field['placeholder'] = $extra['placeholder'] ?? '';
    $field['convert'] = $extra['convert'] ?? '';
    $field['protect'] = '0';
    $field['mask'] = '';
    $field['data'] = $extra['data'] ?? '';
    $field['options'] = $extra['options'] ?? '';
    $field['tpl'] = $tpl;
    $fields[] = $field;
};

$add_field(
    'invoice_no',
    'varchar',
    'text-label',
    'Rechnungsnummer',
    'parameter|min=2|max=40',
    array('length' => '40')
);
$add_field(
    'invoice_date',
    'date',
    'date-label',
    'Rechnungsdatum',
    'date',
    array('convert' => 'date')
);
$add_field(
    'customer',
    'varchar',
    'text-label',
    'Kunde',
    '*|min=2|max=180',
    array('length' => '180')
);
$add_field(
    'status',
    'varchar',
    'select-single-label',
    'Status',
    'parameter|max=24',
    array(
        'length' => '24',
        'default' => 'draft',
        'options' => 'draft=Entwurf&open=Offen&paid=Bezahlt',
    )
);

?>
