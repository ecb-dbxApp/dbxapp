<?php
$fields = array();
$messages = array(
);

$add_component_field = function ($name, $label, $use) use (&$fields) {
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

$add_component_field('comp_alert', 'Aviso', 'Caja breve de aviso, información o éxito.');
$add_component_field('comp_card', 'Tarjetas', 'Teasers, cajas de servicios o mosaicos de paquetes/funciones.');
$add_component_field('comp_list_group', 'Lista', 'Listas compactas de beneficios, pasos o funciones.');
$add_component_field('comp_badges', 'Insignias', 'Estado, categorías, pequeños destacados.');
$add_component_field('comp_buttons', 'Botones', 'Enlaces CTA sin JavaScript propio.');
$add_component_field('comp_table', 'Tabla', 'Comparativas o resúmenes de precios/datos.');
$add_component_field('comp_accordion', 'Acordeón', 'FAQ o secciones de detalle desplegables.');
$add_component_field('comp_tabs', 'Pestañas', 'Vistas alternativas del mismo contenido.');
?>
