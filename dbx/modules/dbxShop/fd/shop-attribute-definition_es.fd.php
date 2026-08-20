<?php
$messages = array();
$add_field=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('group_id','int','Grupo de artículos','int','text-label');
$add_field('attr_key','varchar','Clave del atributo','*|max=80','text-label');
$add_field('title','varchar','Título','*|max=160','text-label');
$add_field('input_type','varchar','Tipo','*|max=30','text-label',array('default'=>'text'));
$add_field('unit','varchar','Unidad','max=40','text-label');
$add_field('options','text','Opciones','max=4000','textarea-label');
$add_field('required','int','Obligatorio','int','checkbox-label');
$add_field('filterable','int','Filtrable','int','checkbox-label',array('default'=>'1'));
$add_field('comparable','int','Comparable','int','checkbox-label');
$add_field('active','int','Activo','int','checkbox-label',array('default'=>'1'));
$add_field('sorter','int','Orden','int','text-label',array('default'=>'100'));
?>
