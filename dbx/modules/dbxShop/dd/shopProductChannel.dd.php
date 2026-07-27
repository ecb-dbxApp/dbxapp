<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_product_channel';
$table['datadic']='shopProductChannel';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['default_sort']='channel_key ASC';
$table['read']='admin';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';

$addField = function($name, $type, $index, $length, $default, $label, $rules, $tpl, $extra = array()) use (&$fields) {
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
   $field['data']=$extra['data'] ?? '';
   $field['options']='';
   $field['tpl']=$tpl;
   $fields[]=$field;
};

$addField('id','int','PRI','11','','ID','int','hidden');
$addField('product_id','int','MUL','11','0','Produkt','int','text-label');
$addField('channel_key','varchar','MUL','80','','Channel','parameter|max=80','text-label');
$addField('active','int','MUL','1','1','Aktiv','int','checkbox-label');
$addField('channel_sku','varchar','','120','','Channel-SKU','parameter|max=120','text-label');
$addField('price_gross','decimal','','10,2','-1','Channel-Preis brutto','number','text-label');
$addField('shipping_gross','decimal','','10,2','-1','Channel-Versand brutto','number','text-label');
$addField('external_listing_id','varchar','MUL','180','','Externe Listing-ID','text|max=180','text-label');
$addField('external_offer_id','varchar','MUL','180','','Externe Offer-ID','text|max=180','text-label');
$addField('export_status','varchar','MUL','40','','Exportstatus','parameter|max=40','text-label');
$addField('export_message','mediumtext','','-1','','Exportmeldung','*|max=5000','textarea-label',array('data'=>'rows=4'));
$addField('export_payload','mediumtext','','-1','','Exportdaten','*','textarea-label',array('data'=>'rows=6'));
$addField('last_export_date','datetime','MUL','-1','','Letzter Export','datetime','text-label',array('convert'=>'date_time'));
$addField('note','mediumtext','','-1','','Notiz','*|max=2000','textarea-label',array('data'=>'rows=4'));
?>
