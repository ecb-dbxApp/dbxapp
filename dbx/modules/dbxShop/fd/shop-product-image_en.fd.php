<?php
$messages = array();
$add_field=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('product_id','int','Product','int','text-label');
$add_field('group_id','int','Product group','int','text-label');
$add_field('media_id','int','CMS medium','int','text-label',array('default'=>'0'));
$add_field('image_path','varchar','Image path','*|max=255','text-label');
$add_field('title','varchar','Title','*|max=180','text-label');
$add_field('alt','varchar','Alt text','*|max=255','text-label');
$add_field('is_primary','int','Primary image','int','checkbox-label');
$add_field('active','int','Active','int','checkbox-label',array('default'=>'1'));
$add_field('sorter','int','Sorting','int','text-label');
?>
