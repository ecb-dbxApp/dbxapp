<?php
$messages = array();
$messages['bar_title'] = 'Ocultar solicitud';
$messages['validation_error'] = 'Confirme que desea ocultar la solicitud.';
$messages['delete_success'] = 'La solicitud se eliminó de su vista. Se conserva el historial de soporte.';
$messages['delete_error'] = 'No se pudo ocultar la solicitud.';
$messages['back_to_requests'] = 'Volver a mis solicitudes';


$field=array();
$field['name']='confirm_delete';
$field['type']='int';
$field['length']='1';
$field['default']='0';
$field['label']='Ocultar la solicitud en mi vista';
$field['rules']='int|min=1';
$field['errormsg']='Por favor, confirme la eliminación.';
$field['options']='1=Sí';
$field['tpl']='checkbox-label';
$fields[]=$field;
