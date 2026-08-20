<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_order_history';
$table['datadic']='shopOrderHistory';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='1';
$table['trace']='0';
$table['default_sort']='create_date DESC, id DESC';
$table['read']='admin';
$table['create']='*';
$table['update']='admin';
$table['delete']='admin';
$table['read_owner']='owner,admin';

$add_field = function($name, $type, $index, $length, $default, $label, $rules, $tpl, $extra = array()) use (&$fields) {
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
$add_field('create_date','datetime','MUL','-1','','Erstellt','datetime','hidden',array('convert'=>'date_time'));
$add_field('create_uid','int','MUL','11','0','Erstellt von','int','hidden');
$add_field('update_date','datetime','','-1','','Aktualisiert','datetime','hidden',array('convert'=>'date_time'));
$add_field('update_uid','int','','11','0','Aktualisiert von','int','hidden');
$add_field('owner','int','','11','0','Owner','int','hidden');
$add_field('trash','int','MUL','1','0','Trash','int','hidden');
$add_field('order_id','int','MUL','11','0','Bestellung','int','text-label');
$add_field('event_type','varchar','MUL','40','note','Ereignis','parameter|max=40','text-label');
$add_field('old_value','varchar','','255','','Alter Wert','*|max=255','text-label');
$add_field('new_value','varchar','','255','','Neuer Wert','*|max=255','text-label');
$add_field('message','mediumtext','','-1','','Nachricht','*|max=3000','textarea-label',array('data'=>'rows=3'));
?>
