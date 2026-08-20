<?php

$messages = array(
   'form_title' => 'Funktionaler Kern-Selbsttest',
   'form_info' => 'Prüft DD-, FD-, Formular- und Datenfluss gemeinsam.',
   'report_title' => 'Kontrollierter Komponentenreport',
   'column_probe_key' => 'Pruefschluessel',
   'column_label' => 'Bezeichnung',
   'column_quantity' => 'Menge',
);

$fields = array();
$fields[] = array(
   'name' => 'probe_key', 'type' => 'varchar', 'index' => '', 'length' => '40',
   'default' => '', 'label' => 'Pruefschluessel', 'rules' => 'parameter|min=2|max=40',
   'tooltip' => 'Stabiler Schluessel des kontrollierten Testdatensatzes.',
   'errormsg' => '', 'placeholder' => 'CORE-A', 'convert' => '', 'protect' => '0',
   'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'text-label',
);
$fields[] = array(
   'name' => 'label', 'type' => 'varchar', 'index' => '', 'length' => '120',
   'default' => '', 'label' => 'Bezeichnung', 'rules' => '*|min=2|max=120',
   'tooltip' => '', 'errormsg' => '', 'placeholder' => 'Alpha', 'convert' => '',
   'protect' => '0', 'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'text-label',
);
$fields[] = array(
   'name' => 'quantity', 'type' => 'int', 'index' => '', 'length' => '11',
   'default' => '0', 'label' => 'Menge', 'rules' => 'int', 'tooltip' => '',
   'errormsg' => '', 'placeholder' => '0', 'convert' => '', 'protect' => '0',
   'mask' => '', 'data' => '', 'options' => '', 'tpl' => 'integer-label',
);

?>
