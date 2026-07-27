<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';
$messages['page_title'] = 'Desistimiento';
$messages['page_subtitle'] = 'Lea la política de desistimiento y envíe el desistimiento directamente.';
$messages['empty_content'] = 'La página del CMS está vacía.';
$messages['bar_title'] = 'Enviar desistimiento';
$messages['bar_subtitle'] = 'Asignar la solicitud al pedido correcto';
$messages['form_info'] = 'Introduzca el número de pedido y los datos de contacto para poder asignar el desistimiento.';
$messages['validation_error'] = 'Revise los campos obligatorios resaltados.';
$messages['withdrawal_success'] = 'Su desistimiento se guardó. Comprobaremos su asignación al pedido.';
$messages['withdrawal_error'] = 'No se pudo guardar el desistimiento.';

$addField = function($name, $type, $label, $rules, $tpl, $extra = array()) use (&$fields) {
   $field=array();
   $field['name']=$name;
   $field['type']=$type;
   $field['index']='';
   $field['length']=$extra['length'] ?? '';
   $field['default']=$extra['default'] ?? '';
   $field['label']=$label;
   $field['rules']=$rules;
   $field['tooltip']=$extra['tooltip'] ?? '';
   $field['errormsg']=$extra['errormsg'] ?? '';
   $field['placeholder']=$extra['placeholder'] ?? '';
   $field['convert']='';
   $field['protect']='0';
   $field['mask']='';
   $field['data']=$extra['data'] ?? '';
   $field['options']=$extra['options'] ?? '';
   $field['tpl']=$tpl;
   $fields[]=$field;
};

$addField('order_no','varchar','Número de pedido','parameter|max=40','text-label',array('placeholder'=>'S20260710123456-1234'));
$addField('customer_name','varchar','Nombre','*|min=2|max=180','text-label',array('placeholder'=>'Su nombre'));
$addField('customer_email','varchar','E-mail','email|max=180','text-label',array('placeholder'=>'name@example.org'));
$addField('customer_address','mediumtext','Dirección','*|min=8|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>"Nombre\nCalle y número\nCódigo postal y localidad\nPaís"));
$addField('reason','mediumtext','Mensaje','*|max=3000','textarea-label',array('data'=>'rows=5','placeholder'=>'Por la presente desisto de mi pedido. Opcional: artículos afectados o una pregunta.'));
?>
