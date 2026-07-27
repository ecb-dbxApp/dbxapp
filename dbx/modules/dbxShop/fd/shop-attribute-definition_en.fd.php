<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';

$addField=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('group_id','int','Product group','int','text-label');
$addField('attr_key','varchar','Attribute key','*|max=80','text-label');
$addField('title','varchar','Title','*|max=160','text-label');
$addField('input_type','varchar','Type','*|max=30','text-label',array('default'=>'text'));
$addField('unit','varchar','Unit','max=40','text-label');
$addField('options','text','Options','max=4000','textarea-label');
$addField('required','int','Required','int','checkbox-label');
$addField('filterable','int','Filterable','int','checkbox-label',array('default'=>'1'));
$addField('comparable','int','Comparable','int','checkbox-label');
$addField('active','int','Active','int','checkbox-label',array('default'=>'1'));
$addField('sorter','int','Sorting','int','text-label',array('default'=>'100'));
?>
