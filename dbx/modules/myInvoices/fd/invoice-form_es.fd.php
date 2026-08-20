<?php
$messages = array();
$messages['form_info'] = 'Revise y guarde los datos de la factura.';
$messages['form_title_new'] = 'Nueva factura';
$messages['form_title_edit'] = 'Editar factura';
$messages['help_title'] = 'Formulario de factura';
$messages['not_found'] = 'No se encontró la factura.';
$messages['validation_error'] = 'Revise los datos introducidos.';

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
    'Número de factura',
    'parameter|min=2|max=40',
    array('length' => '40')
);
$add_field(
    'invoice_date',
    'date',
    'date-label',
    'Fecha de factura',
    'date',
    array('convert' => 'date')
);
$add_field(
    'customer',
    'varchar',
    'text-label',
    'Cliente',
    '*|min=2|max=180',
    array('length' => '180')
);
$add_field(
    'status',
    'varchar',
    'select-single-label',
    'Estado',
    'parameter|max=24',
    array(
        'length' => '24',
        'default' => 'draft',
        'options' => 'draft=Borrador&open=Abierta&paid=Pagada',
    )
);

?>
