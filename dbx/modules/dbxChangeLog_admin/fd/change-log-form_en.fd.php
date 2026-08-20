<?php
require __DIR__ . '/change-log-form.fd.php';
$messages['form_title_new']='Create change-log entry';
$messages['form_title_edit']='Edit change-log entry';
$messages['form_subtitle']='One understandable description per completed change block.';
$messages['action_report']='Back to list';
$labels=array('change_date'=>'Date and time','actor'=>'Actor','summary'=>'What','details'=>'Why','resources'=>'Affected resources');
foreach ($fields as &$field) $field['label']=$labels[$field['name']] ?? $field['label'];
unset($field);
