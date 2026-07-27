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
$addField('group_id','int','Artikelgruppe','int','text-label');
$addField('media_id','int','CMS-Medium','int','text-label',array('default'=>'0'));
$addField('image_path','varchar','Bildpfad','*|max=255','text-label');
$addField('title','varchar','Titel','*|max=180','text-label');
$addField('alt','varchar','Alt-Text','*|max=255','text-label');
$addField('is_primary','int','Primaerbild','int','checkbox-label');
$addField('active','int','Aktiv','int','checkbox-label',array('default'=>'1'));
$addField('sorter','int','Sortierung','int','text-label');
?>
