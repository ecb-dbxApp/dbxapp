<?php
require __DIR__ . '/change-log-form.fd.php';
$messages['form_title_new']='Crear entrada del registro de cambios';
$messages['form_title_edit']='Editar entrada del registro de cambios';
$messages['form_subtitle']='Una descripción comprensible por cada bloque de cambios terminado.';
$messages['action_report']='Volver a la lista';
$labels=array('change_date'=>'Fecha y hora','actor'=>'Actor','summary'=>'Qué','details'=>'Por qué','resources'=>'Recursos afectados');
foreach ($fields as &$field) $field['label']=$labels[$field['name']] ?? $field['label'];
unset($field);
