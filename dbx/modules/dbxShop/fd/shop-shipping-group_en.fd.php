<?php
$messages = array();
$add_field=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('group_key','varchar','Key','parameter|min=2|max=80','text-label');
$add_field('title','varchar','Title','*|min=2|max=180','text-label');
$add_field('description','mediumtext','Description','*|max=2000','textarea-label',array('data'=>'rows=3'));
$add_field('shipping_way','varchar','Shipping method','*|max=180','text-label');
$add_field('delivery_time','varchar','Delivery time','*|max=120','text-label');
$add_field('shipping_gross','decimal','Gross shipping','number','text-label');
$add_field('free_from_gross','decimal','Free shipping from','number','text-label');
$add_field('active','int','Active','int','checkbox-label',array('default'=>'1'));
$add_field('sorter','int','Sorting','int','text-label');
?>
