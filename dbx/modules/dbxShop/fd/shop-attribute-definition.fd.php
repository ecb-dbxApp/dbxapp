<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';

$addField=function($name,$type,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>'','length'=>$extra['length'] ?? '','default'=>$extra['default'] ?? '','label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','mask'=>'','data'=>$extra['data'] ?? '','options'=>$extra['options'] ?? '','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('group_id','int','Artikelgruppe','int','text-label');
$addField('attr_key','varchar','Attribut-Key','*|max=80','text-label');
$addField('title','varchar','Titel','*|max=160','text-label');
$addField('input_type','varchar','Typ','*|max=30','text-label',array('default'=>'text'));
$addField('unit','varchar','Einheit','max=40','text-label');
$addField('options','text','Optionen','max=4000','textarea-label');
$addField('required','int','Pflicht','int','checkbox-label');
$addField('filterable','int','Filterbar','int','checkbox-label',array('default'=>'1'));
$addField('comparable','int','Vergleichbar','int','checkbox-label');
$addField('active','int','Aktiv','int','checkbox-label',array('default'=>'1'));
$addField('sorter','int','Sortierung','int','text-label',array('default'=>'100'));
?>
