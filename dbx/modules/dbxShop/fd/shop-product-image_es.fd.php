<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';

$addField=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('product_id','int','Artículo','int','text-label');
$addField('group_id','int','Grupo de artículos','int','text-label');
$addField('media_id','int','CMS medium','int','text-label',array('default'=>'0'));
$addField('image_path','varchar','Ruta de imagen','*|max=255','text-label');
$addField('title','varchar','Título','*|max=180','text-label');
$addField('alt','varchar','Texto de Alt','*|max=255','text-label');
$addField('is_primary','int','Imagen principal','int','checkbox-label');
$addField('active','int','Activo','int','checkbox-label',array('default'=>'1'));
$addField('sorter','int','Orden','int','text-label');
?>
