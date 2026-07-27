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

$addField = function($name, $type, $index, $length, $default, $label, $rules, $tpl, $extra = array()) use (&$fields) {
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
$addField('create_uid','int','','11','0','Erstellt von','int','hidden');
$addField('update_date','datetime','','-1','','Aktualisiert','datetime','hidden',array('convert'=>'date_time'));
$addField('update_uid','int','','11','0','Aktualisiert von','int','hidden');
$addField('owner','int','','11','0','Owner','int','hidden');
$addField('trash','int','','1','0','Trash','int','hidden');
$addField('order_id','int','MUL','11','0','Bestellung','int','text-label');
$addField('product_id','int','MUL','11','0','Produkt','int','text-label');
$addField('sku','varchar','MUL','80','','Artikelnummer','parameter|max=80','text-label');
$addField('title','varchar','','180','','Titel','*|max=180','text-label');
$addField('qty','int','','11','1','Menge','int','text-label');
$addField('price_gross','decimal','','10,2','0','Einzelpreis brutto','number','text-label');
$addField('tax_rate','decimal','','5,2','0','MwSt. %','number','text-label');
$addField('shipping_gross','decimal','','10,2','0','Versand brutto','number','text-label');
$addField('total_gross','decimal','','10,2','0','Zeilensumme brutto','number','text-label');
?>
