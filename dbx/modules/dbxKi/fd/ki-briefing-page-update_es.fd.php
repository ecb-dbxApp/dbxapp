<?php
$fields = array();
$messages = array(
   'save_success' => 'Los datos se guardaron',
   'save_succeass' => 'Los datos se guardaron',
   'save_error' => 'Los datos no se pudieron guardar',
);

$addComponentField = function ($name, $label, $use) use (&$fields) {
   $field = array();
   $field['name'] = $name;
   $field['type'] = 'int';
   $field['index'] = '';
   $field['length'] = '';
   $field['default'] = '0';
   $field['label'] = $label;
   $field['rules'] = 'int';
   $field['tooltip'] = '';
   $field['errormsg'] = '';
   $field['placeholder'] = '';
   $field['convert'] = '';
   $field['protect'] = '0';
   $field['mask'] = '';
   $field['data'] = array('ui_persist' => 1, 'use_text' => $use);
   $field['options'] = '';
   $field['tpl'] = 'dbxKi|ki-checkbox-component';
   $fields[] = $field;
};

$addComponentField('comp_alert', 'Aviso', 'Caja breve de aviso, información o éxito.');
$addComponentField('comp_card', 'Tarjetas', 'Teasers, cajas de servicios o mosaicos de paquetes/funciones.');
$addComponentField('comp_list_group', 'Lista', 'Listas compactas de beneficios, pasos o funciones.');
$addComponentField('comp_badges', 'Insignias', 'Estado, categorías, pequeños destacados.');
$addComponentField('comp_buttons', 'Botones', 'Enlaces CTA sin JavaScript propio.');
$addComponentField('comp_table', 'Tabla', 'Comparativas o resúmenes de precios/datos.');
$addComponentField('comp_accordion', 'Acordeón', 'FAQ o secciones de detalle desplegables.');
$addComponentField('comp_tabs', 'Pestañas', 'Vistas alternativas del mismo contenido.');
?>
