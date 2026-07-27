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

$addField=function($name,$type,$index,$length,$default,$label,$rules,$tpl,$extra=array()) use (&$fields){
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

$addField('id','int','PRI','11','','ID','int','hidden');
$addField('create_date','datetime','','-1','','Erstellt','datetime','hidden',array('convert'=>'date_time'));
$addField('create_uid','int','MUL','11','0','Erstellt von','int','hidden');
$addField('update_date','datetime','','-1','','Aktualisiert','datetime','hidden',array('convert'=>'date_time'));
$addField('update_uid','int','MUL','11','0','Aktualisiert von','int','hidden');
$addField('owner','int','MUL','11','0','Owner','int','hidden');
$addField('trash','int','MUL','1','0','Trash','int','hidden');
$addField('group_id','int','MUL','11','0','Artikelgruppe','int','text-label');
$addField('attr_key','varchar','MUL','80','','Attribut-Key','*|max=80','text-label');
$addField('title','varchar','','160','','Titel','*|max=160','text-label');
$addField('input_type','varchar','','30','text','Typ','*|max=30','text-label');
$addField('unit','varchar','','40','','Einheit','max=40','text-label');
$addField('options','text','','-1','','Optionen','max=4000','textarea-label');
$addField('required','int','MUL','1','0','Pflicht','int','checkbox-label');
$addField('filterable','int','MUL','1','1','Filterbar','int','checkbox-label');
$addField('comparable','int','MUL','1','0','Vergleichbar','int','checkbox-label');
$addField('active','int','MUL','1','1','Aktiv','int','checkbox-label');
$addField('sorter','int','MUL','11','100','Sortierung','int','text-label');
?>
