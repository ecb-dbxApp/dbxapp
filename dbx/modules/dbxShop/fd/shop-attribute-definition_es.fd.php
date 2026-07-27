<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';

$addField=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('group_id','int','Grupo de artículos','int','text-label');
$addField('attr_key','varchar','Clave del atributo','*|max=80','text-label');
$addField('title','varchar','Título','*|max=160','text-label');
$addField('input_type','varchar','Tipo','*|max=30','text-label',array('default'=>'text'));
$addField('unit','varchar','Unidad','max=40','text-label');
$addField('options','text','Opciones','max=4000','textarea-label');
$addField('required','int','Obligatorio','int','checkbox-label');
$addField('filterable','int','Filtrable','int','checkbox-label',array('default'=>'1'));
$addField('comparable','int','Comparable','int','checkbox-label');
$addField('active','int','Activo','int','checkbox-label',array('default'=>'1'));
$addField('sorter','int','Orden','int','text-label',array('default'=>'100'));
?>
