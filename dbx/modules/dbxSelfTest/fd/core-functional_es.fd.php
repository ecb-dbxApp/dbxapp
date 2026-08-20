<?php

$messages = array(
   'form_title' => 'Autoprueba funcional del núcleo',
   'form_info' => 'Comprueba conjuntamente DD, FD, formulario y flujo de datos.',
   'report_title' => 'Informe controlado de componentes',
   'column_probe_key' => 'Clave de prueba',
   'column_label' => 'Nombre',
   'column_quantity' => 'Cantidad',
);

$fields = array();
$fields[] = array('name' => 'probe_key', 'type' => 'varchar', 'index' => '', 'length' => '40', 'default' => '', 'label' => 'Clave de prueba', 'rules' => 'parameter|min=2|max=40', 'tooltip' => 'Clave estable del registro de prueba controlado.', 'errormsg' => '', 'placeholder' => 'CORE-A', 'convert' => '', 'protect' => '0', 'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'text-label');
$fields[] = array('name' => 'label', 'type' => 'varchar', 'index' => '', 'length' => '120', 'default' => '', 'label' => 'Nombre', 'rules' => '*|min=2|max=120', 'tooltip' => '', 'errormsg' => '', 'placeholder' => 'Alpha', 'convert' => '', 'protect' => '0', 'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'text-label');
$fields[] = array('name' => 'quantity', 'type' => 'int', 'index' => '', 'length' => '11', 'default' => '0', 'label' => 'Cantidad', 'rules' => 'int', 'tooltip' => '', 'errormsg' => '', 'placeholder' => '0', 'convert' => '', 'protect' => '0', 'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'integer-label');

?>
