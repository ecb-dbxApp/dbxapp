<?php
$messages = array();
$messages['form_info'] = 'Review and save the invoice header.';
$messages['form_title_new'] = 'New invoice';
$messages['form_title_edit'] = 'Edit invoice';
$messages['help_title'] = 'Invoice form';
$messages['not_found'] = 'Invoice not found.';
$messages['validation_error'] = 'Please check your entries.';

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
    'Invoice number',
    'parameter|min=2|max=40',
    array('length' => '40')
);
$add_field(
    'invoice_date',
    'date',
    'date-label',
    'Invoice date',
    'date',
    array('convert' => 'date')
);
$add_field(
    'customer',
    'varchar',
    'text-label',
    'Customer',
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
        'options' => 'draft=Draft&open=Open&paid=Paid',
    )
);

?>
