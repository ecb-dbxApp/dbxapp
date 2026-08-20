<?php
$messages = array();
$field=array();
$field['name']='confirm_delete';
$field['type']='int';
$field['length']='1';
$field['default']='0';
$field['label']='Ticket und gesamten Verlauf endgültig löschen';
$field['rules']='int|min=1';
$field['errormsg']='Bitte die endgültige Löschung bestätigen.';
$field['options']='1=Ja';
$field['tpl']='checkbox-label';
$fields[]=$field;
