<?php
$table['server']='dbxShop|dbxShop.db3';
$table['table']='shop_channel_group_channel';
$table['datadic']='shopChannelGroupChannel';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['default_sort']='channel_key ASC';
$table['read']='*';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';

$add_field=function($name,$type,$index,$length,$default,$label,$rules,$tpl) use (&$fields){
   $field=array('name'=>$name,'type'=>$type,'index'=>$index,'length'=>$length,'default'=>$default,'label'=>$label,'rules'=>$rules,'tooltip'=>'','errormsg'=>'','placeholder'=>'','convert'=>'','protect'=>'0','group'=>'','mask'=>'','data'=>'','options'=>'','tpl'=>$tpl);
   $fields[]=$field;
};
$add_field('id','int','PRI','11','','ID','int','hidden');
$add_field('channel_group_id','int','MUL','11','0','Channel-Gruppe','int','text-label');
$add_field('channel_key','varchar','MUL','80','','Channel','parameter|min=2|max=80','text-label');
$add_field('active','int','','1','1','Aktiv','int','checkbox-label');
?>
