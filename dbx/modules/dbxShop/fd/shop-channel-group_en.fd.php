<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';

$addField=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('group_key','varchar','Key','parameter|min=2|max=80','text-label');
$addField('title','varchar','Title','*|min=2|max=180','text-label');
$addField('description','mediumtext','Description','*|max=2000','textarea-label',array('data'=>'rows=3'));
$addField('active','int','Active','int','checkbox-label',array('default'=>'1'));
$addField('sorter','int','Sorting','int','text-label');
?>
