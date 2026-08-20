<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_product_group_map';
$table['datadic']='shopProductGroupMap';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['default_sort']='id ASC';
$table['read']='admin';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';

$add_field = function($name, $type, $index, $length, $default, $label, $rules, $tpl) use (&$fields) {
   $field=array();
   $field['name']=$name;
   $field['type']=$type;
   $field['index']=$index;
   $field['length']=$length;
   $field['default']=$default;
   $field['label']=$label;
   $field['rules']=$rules;
   $field['tooltip']='';
   $field['errormsg']='';
   $field['placeholder']='';
   $field['convert']='';
   $field['protect']='0';
   $field['group']='';
   $field['mask']='';
   $field['data']='';
   $field['options']='';
   $field['tpl']=$tpl;
   $fields[]=$field;
};

$add_field('id','int','PRI','11','','ID','int','hidden');
$add_field('product_id','int','MUL','11','0','Produkt','int','text-label');
$add_field('group_id','int','MUL','11','0','Gruppe','int','text-label');
$add_field('is_primary','int','','1','0','Primaere Gruppe','int','checkbox-label');
?>
