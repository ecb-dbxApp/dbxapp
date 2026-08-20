<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_order_item';
$table['datadic']='shopOrderItem';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='1';
$table['trace']='0';
$table['default_sort']='id ASC';
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
$add_field('create_date','datetime','','-1','','Erstellt','datetime','hidden',array('convert'=>'date_time'));
$add_field('create_uid','int','','11','0','Erstellt von','int','hidden');
$add_field('update_date','datetime','','-1','','Aktualisiert','datetime','hidden',array('convert'=>'date_time'));
$add_field('update_uid','int','','11','0','Aktualisiert von','int','hidden');
$add_field('owner','int','','11','0','Owner','int','hidden');
$add_field('trash','int','','1','0','Trash','int','hidden');
$add_field('order_id','int','MUL','11','0','Bestellung','int','text-label');
$add_field('product_id','int','MUL','11','0','Produkt','int','text-label');
$add_field('sku','varchar','MUL','80','','Artikelnummer','parameter|max=80','text-label');
$add_field('title','varchar','','180','','Titel','*|max=180','text-label');
$add_field('qty','int','','11','1','Menge','int','text-label');
$add_field('price_gross','decimal','','10,2','0','Einzelpreis brutto','number','text-label');
$add_field('tax_rate','decimal','','5,2','0','MwSt. %','number','text-label');
$add_field('shipping_gross','decimal','','10,2','0','Versand brutto','number','text-label');
$add_field('total_gross','decimal','','10,2','0','Zeilensumme brutto','number','text-label');
?>
