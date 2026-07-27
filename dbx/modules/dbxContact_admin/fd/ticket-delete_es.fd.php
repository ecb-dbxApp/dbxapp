<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';


$field=array();
$field['name']='confirm_delete';
$field['type']='int';
$field['length']='1';
$field['default']='0';
$field['label']='Eliminar definitivamente el ticket y todo su historial';
$field['rules']='int|min=1';
$field['errormsg']='Confirme la eliminación definitiva.';
$field['options']='1=Sí';
$field['tpl']='checkbox-label';
$fields[]=$field;
