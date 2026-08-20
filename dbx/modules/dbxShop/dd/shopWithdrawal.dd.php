<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_withdrawal';
$table['datadic']='shopWithdrawal';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='1';
$table['trace']='0';
$table['default_sort']='create_date DESC';
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
$add_field('order_no','varchar','MUL','40','','Bestellnummer','parameter|max=40','text-label');
$add_field('customer_name','varchar','','180','','Name','*|max=180','text-label');
$add_field('customer_email','varchar','','180','','E-Mail','email|max=180','text-label');
$add_field('customer_address','mediumtext','','-1','','Adresse','*|max=2000','textarea-label',array('data'=>'rows=4'));
$add_field('reason','mediumtext','','-1','','Nachricht','*|max=3000','textarea-label',array('data'=>'rows=5'));
$add_field('status','varchar','MUL','40','new','Status','parameter|max=40','select-single-label',array('options'=>'new=Neu&processing=In Bearbeitung&accepted=Angenommen&rejected=Abgelehnt&refunded=Erstattet&closed=Abgeschlossen'));
$add_field('admin_note','mediumtext','','-1','','Interne Notiz','*|max=3000','textarea-label',array('data'=>'rows=4'));
?>
