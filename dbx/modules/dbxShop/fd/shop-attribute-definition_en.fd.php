<?php
$messages = array();
$add_field=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('group_id','int','Product group','int','text-label');
$add_field('attr_key','varchar','Attribute key','*|max=80','text-label');
$add_field('title','varchar','Title','*|max=160','text-label');
$add_field('input_type','varchar','Type','*|max=30','text-label',array('default'=>'text'));
$add_field('unit','varchar','Unit','max=40','text-label');
$add_field('options','text','Options','max=4000','textarea-label');
$add_field('required','int','Required','int','checkbox-label');
$add_field('filterable','int','Filterable','int','checkbox-label',array('default'=>'1'));
$add_field('comparable','int','Comparable','int','checkbox-label');
$add_field('active','int','Active','int','checkbox-label',array('default'=>'1'));
$add_field('sorter','int','Sorting','int','text-label',array('default'=>'100'));
?>
