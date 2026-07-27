<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';
$messages['bar_title'] = 'Respuesta o nota interna';
$messages['bar_subtitle'] = 'El historial completo permanece asociado al ticket';
$messages['form_info'] = 'Las respuestas públicas pueden enviarse por correo electrónico. Las notas internas solo son visibles en la administración.';
$messages['validation_error'] = 'Revise los datos resaltados.';
$messages['mail_warning'] = 'El mensaje y el estado se guardaron, pero no se pudo enviar el correo electrónico.';
$messages['internal_success'] = 'La nota interna y el estado se guardaron.';
$messages['reply_success'] = 'La respuesta y el estado se guardaron.';
$messages['mail_success_suffix'] = ' Se envió el correo electrónico.';
$messages['message_error'] = 'No se pudo guardar el mensaje.';


$field=array();
$field['name']='status';
$field['type']='varchar';
$field['length']='24';
$field['default']='answered';
$field['label']='Nuevo estado';
$field['rules']='parameter|max=24';
$field['options']='open=Abierta&in_progress=En curso&waiting_customer=Esperando al cliente&answered=Respondida&closed=Cerrada';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='priority';
$field['type']='varchar';
$field['length']='16';
$field['default']='normal';
$field['label']='Prioridad';
$field['rules']='parameter|max=16';
$field['options']='low=Baja&normal=Normal&high=Alta&urgent=Urgente';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='visibility';
$field['type']='varchar';
$field['length']='16';
$field['default']='public';
$field['label']='Tipo de mensaje';
$field['rules']='parameter|max=16';
$field['options']='public=Respuesta al solicitante&internal=Nota interna';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='body';
$field['type']='mediumtext';
$field['length']='-1';
$field['default']='';
$field['label']='Respuesta o nota';
$field['rules']='*|min=2|max=10000';
$field['errormsg']='Por favor, escriba un mensaje.';
$field['placeholder']='Respuesta al solicitante o nota interna';
$field['data']='rows=9';
$field['tpl']='textarea-label';
$fields[]=$field;

$field=array();
$field['name']='send_mail';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='Enviar una respuesta como correo electrónico';
$field['rules']='int';
$field['options']='1=Sí';
$field['tpl']='checkbox-label';
$fields[]=$field;
