<?php

$messages = array(
   'form_title' => 'Functional core self-test',
   'form_info' => 'Checks DD, FD, form and data flow together.',
   'report_title' => 'Controlled component report',
   'column_probe_key' => 'Probe key',
   'column_label' => 'Label',
   'column_quantity' => 'Quantity',
);

$fields = array();
$fields[] = array('name' => 'probe_key', 'type' => 'varchar', 'index' => '', 'length' => '40', 'default' => '', 'label' => 'Probe key', 'rules' => 'parameter|min=2|max=40', 'tooltip' => 'Stable key of the controlled test record.', 'errormsg' => '', 'placeholder' => 'CORE-A', 'convert' => '', 'protect' => '0', 'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'text-label');
$fields[] = array('name' => 'label', 'type' => 'varchar', 'index' => '', 'length' => '120', 'default' => '', 'label' => 'Label', 'rules' => '*|min=2|max=120', 'tooltip' => '', 'errormsg' => '', 'placeholder' => 'Alpha', 'convert' => '', 'protect' => '0', 'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'text-label');
$fields[] = array('name' => 'quantity', 'type' => 'int', 'index' => '', 'length' => '11', 'default' => '0', 'label' => 'Quantity', 'rules' => 'int', 'tooltip' => '', 'errormsg' => '', 'placeholder' => '0', 'convert' => '', 'protect' => '0', 'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'integer-label');

?>
