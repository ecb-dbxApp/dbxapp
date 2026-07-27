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
$addField('attribute_id','int','Atributo','int','text-label');
$addField('value_text','varchar','Valor','max=255','text-label');
$addField('value_num','float','Valor numérico','float','text-label');
$addField('unit_override','varchar','Unidad alternativa','max=40','text-label');
$addField('active','int','Activo','int','checkbox-label',array('default'=>'1'));
?>
