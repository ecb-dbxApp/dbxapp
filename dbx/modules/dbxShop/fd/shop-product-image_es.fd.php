<?php
$messages = array();
$add_field=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('product_id','int','Artículo','int','text-label');
$add_field('group_id','int','Grupo de artículos','int','text-label');
$add_field('media_id','int','CMS medium','int','text-label',array('default'=>'0'));
$add_field('image_path','varchar','Ruta de imagen','*|max=255','text-label');
$add_field('title','varchar','Título','*|max=180','text-label');
$add_field('alt','varchar','Texto de Alt','*|max=255','text-label');
$add_field('is_primary','int','Imagen principal','int','checkbox-label');
$add_field('active','int','Activo','int','checkbox-label',array('default'=>'1'));
$add_field('sorter','int','Orden','int','text-label');
?>
