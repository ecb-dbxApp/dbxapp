<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_product_shipping_group_map';
$table['datadic']='shopProductShippingGroupMap';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['default_sort']='id ASC';
$table['read']='*';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';

$addField=function($name,$type,$index,$length,$default,$label,$rules,$tpl) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>$index,'length'=>$length,'default'=>$default,'label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','group'=>'','mask'=>'','data'=>'','options'=>'','tpl'=>$tpl);
   $fields[]=$field;
};
$addField('id','int','PRI','11','','ID','int','hidden');
$addField('product_id','int','MUL','11','0','Artikel','int','text-label');
$addField('shipping_group_id','int','MUL','11','0','Versandgruppe','int','text-label');
$addField('is_primary','int','','1','0','Primaer','int','checkbox-label');
?>
