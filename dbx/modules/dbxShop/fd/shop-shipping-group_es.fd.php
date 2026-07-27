<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';

$addField=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('group_key','varchar','Clave','parameter|min=2|max=80','text-label');
$addField('title','varchar','Título','*|min=2|max=180','text-label');
$addField('description','mediumtext','Descripción','*|max=2000','textarea-label',array('data'=>'rows=3'));
$addField('shipping_way','varchar','Método de envío','*|max=180','text-label');
$addField('delivery_time','varchar','Plazo de entrega','*|max=120','text-label');
$addField('shipping_gross','decimal','Envío bruto','number','text-label');
$addField('free_from_gross','decimal','Envío gratuito desde','number','text-label');
$addField('active','int','Activo','int','checkbox-label',array('default'=>'1'));
$addField('sorter','int','Orden','int','text-label');
?>
