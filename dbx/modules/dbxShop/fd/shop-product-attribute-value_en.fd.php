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
$addField('attribute_id','int','Attribute','int','text-label');
$addField('value_text','varchar','Value','max=255','text-label');
$addField('value_num','float','Numeric value','float','text-label');
$addField('unit_override','varchar','Alternative unit','max=40','text-label');
$addField('active','int','Active','int','checkbox-label',array('default'=>'1'));
?>
