<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';
$messages['bar_title'] = 'Añadir mensaje';
$messages['bar_subtitle'] = 'Su mensaje será visible para el equipo de soporte';
$messages['form_info'] = 'Añada información o responda a una pregunta del equipo de soporte.';
$messages['validation_error'] = 'Revise el mensaje resaltado.';
$messages['message_success'] = 'Su mensaje se añadió al ticket.';
$messages['message_error'] = 'No se pudo guardar el mensaje.';
$messages['ticket_closed'] = 'Este ticket está cerrado. Cree una nueva solicitud para otro asunto.';


$field=array();
$field['name']='body';
$field['type']='mediumtext';
$field['length']='-1';
$field['default']='';
$field['label']='Su mensaje';
$field['rules']='*|min=2|max=10000';
$field['errormsg']='Por favor, escriba un mensaje.';
$field['placeholder']='Información adicional o una pregunta sobre este ticket';
$field['data']='rows=6';
$field['tpl']='textarea-label';
$fields[]=$field;
