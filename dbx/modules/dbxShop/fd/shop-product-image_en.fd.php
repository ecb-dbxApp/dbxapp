<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';

$addField=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('product_id','int','Product','int','text-label');
$addField('group_id','int','Product group','int','text-label');
$addField('media_id','int','CMS medium','int','text-label',array('default'=>'0'));
$addField('image_path','varchar','Image path','*|max=255','text-label');
$addField('title','varchar','Title','*|max=180','text-label');
$addField('alt','varchar','Alt text','*|max=255','text-label');
$addField('is_primary','int','Primary image','int','checkbox-label');
$addField('active','int','Active','int','checkbox-label',array('default'=>'1'));
$addField('sorter','int','Sorting','int','text-label');
?>
