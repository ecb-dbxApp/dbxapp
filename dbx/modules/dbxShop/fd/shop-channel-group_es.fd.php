<?php
$messages = array();
$add_field=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('group_key','varchar','Clave','parameter|min=2|max=80','text-label');
$add_field('title','varchar','Título','*|min=2|max=180','text-label');
$add_field('description','mediumtext','Descripción','*|max=2000','textarea-label',array('data'=>'rows=3'));
$add_field('active','int','Activo','int','checkbox-label',array('default'=>'1'));
$add_field('sorter','int','Orden','int','text-label');
?>
