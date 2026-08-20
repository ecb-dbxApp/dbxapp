<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_attribute_definition';
$table['datadic']='shopAttributeDefinition';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='1';
$table['trace']='0';
$table['default_sort']='sorter ASC, title ASC';
$table['read']='*';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';

$add_field=function($name,$type,$index,$length,$default,$label,$rules,$tpl,$extra=array()) use (&$fields){
   $field=array();
   $field['name']=$name;
   $field['type']=$type;
   $field['index']=$index;
   $field['length']=$length;
   $field['default']=$default;
   $field['label']=$label;
   $field['rules']=$rules;
   $field['tooltip']=$extra['tooltip'] ?? '';
   $field['errormsg']=$extra['errormsg'] ?? '';
   $field['placeholder']=$extra['placeholder'] ?? '';
   $field['convert']=$extra['convert'] ?? '';
   $field['protect']=$extra['protect'] ?? '0';
   $field['group']=$extra['group'] ?? '';
   $field['mask']=$extra['mask'] ?? '';
   $field['data']=$extra['data'] ?? '';
   $field['options']=$extra['options'] ?? '';
   $field['tpl']=$tpl;
   $fields[]=$field;
};

$add_field('id','int','PRI','11','','ID','int','hidden');
$add_field('create_date','datetime','','-1','','Erstellt','datetime','hidden',array('convert'=>'date_time'));
$add_field('create_uid','int','MUL','11','0','Erstellt von','int','hidden');
$add_field('update_date','datetime','','-1','','Aktualisiert','datetime','hidden',array('convert'=>'date_time'));
$add_field('update_uid','int','MUL','11','0','Aktualisiert von','int','hidden');
$add_field('owner','int','MUL','11','0','Owner','int','hidden');
$add_field('trash','int','MUL','1','0','Trash','int','hidden');
$add_field('group_id','int','MUL','11','0','Artikelgruppe','int','text-label');
$add_field('attr_key','varchar','MUL','80','','Attribut-Key','*|max=80','text-label');
$add_field('title','varchar','','160','','Titel','*|max=160','text-label');
$add_field('input_type','varchar','','30','text','Typ','*|max=30','text-label');
$add_field('unit','varchar','','40','','Einheit','max=40','text-label');
$add_field('options','text','','-1','','Optionen','max=4000','textarea-label');
$add_field('required','int','MUL','1','0','Pflicht','int','checkbox-label');
$add_field('filterable','int','MUL','1','1','Filterbar','int','checkbox-label');
$add_field('comparable','int','MUL','1','0','Vergleichbar','int','checkbox-label');
$add_field('active','int','MUL','1','1','Aktiv','int','checkbox-label');
$add_field('sorter','int','MUL','11','100','Sortierung','int','text-label');
?>
