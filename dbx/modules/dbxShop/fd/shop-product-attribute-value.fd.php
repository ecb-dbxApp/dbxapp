<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';

$addField=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('product_id','int','Artikel','int','text-label');
$addField('attribute_id','int','Attribut','int','text-label');
$addField('value_text','varchar','Wert','max=255','text-label');
$addField('value_num','float','Zahlenwert','float','text-label');
$addField('unit_override','varchar','Einheit abweichend','max=40','text-label');
$addField('active','int','Aktiv','int','checkbox-label',array('default'=>'1'));
?>
