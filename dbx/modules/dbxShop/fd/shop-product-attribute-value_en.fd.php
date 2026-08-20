<?php
$messages = array();
$add_field=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('product_id','int','Product','int','text-label');
$add_field('attribute_id','int','Attribute','int','text-label');
$add_field('value_text','varchar','Value','max=255','text-label');
$add_field('value_num','float','Numeric value','float','text-label');
$add_field('unit_override','varchar','Alternative unit','max=40','text-label');
$add_field('active','int','Active','int','checkbox-label',array('default'=>'1'));
?>
