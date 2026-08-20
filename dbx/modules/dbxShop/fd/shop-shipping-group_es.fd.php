<?php
$messages = array();
$add_field=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('group_key','varchar','Clave','parameter|min=2|max=80','text-label');
$add_field('title','varchar','Título','*|min=2|max=180','text-label');
$add_field('description','mediumtext','Descripción','*|max=2000','textarea-label',array('data'=>'rows=3'));
$add_field('shipping_way','varchar','Método de envío','*|max=180','text-label');
$add_field('delivery_time','varchar','Plazo de entrega','*|max=120','text-label');
$add_field('shipping_gross','decimal','Envío bruto','number','text-label');
$add_field('free_from_gross','decimal','Envío gratuito desde','number','text-label');
$add_field('active','int','Activo','int','checkbox-label',array('default'=>'1'));
$add_field('sorter','int','Orden','int','text-label');
?>
