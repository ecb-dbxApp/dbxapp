<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_product_image';
$table['datadic']='shopProductImage';
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
$add_field('product_id','int','MUL','11','0','Artikel','int','text-label');
$add_field('group_id','int','MUL','11','0','Artikelgruppe','int','text-label');
$add_field('media_id','int','MUL','11','0','CMS-Medium','int','text-label');
$add_field('image_path','varchar','MUL','255','','Bildpfad','*|max=255','text-label');
$add_field('title','varchar','','180','','Titel','*|max=180','text-label');
$add_field('alt','varchar','','255','','Alt-Text','*|max=255','text-label');
$add_field('is_primary','int','MUL','1','0','Primaerbild','int','checkbox-label');
$add_field('active','int','MUL','1','1','Aktiv','int','checkbox-label');
$add_field('sorter','int','MUL','11','100','Sortierung','int','text-label');
?>
