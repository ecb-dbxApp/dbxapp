<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='dbXsystem';
$table['table']='dbx_my_testdata';
$table['datadic']='dd_my_testdata';
$table['primary']='';
$table['language']='';
$table['version']='1.0';
$table['autosync']='1';
$table['cache']='1';
$table['trash']='1';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='';
$table['form-dd-table']='';
$table['read']='admin';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';
$table['read_owner']='admin,owner';
$table['create_owner']='admin,owner';
$table['update_owner']='admin,owner';
$table['delete_owner']='admin,owner';


/* =========================================================
   FIELDS
   ========================================================= */
$field['name']='id';
$field['type']='int';
$field['index']='PRI';
$field['length']='11';
$field['default']='';
$field['label']='id';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='create_date';
$field['type']='datetime';
$field['index']='';
$field['length']='-1';
$field['default']='';
$field['label']='create_date';
$field['rules']='datetime';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='create_uid';
$field['type']='int';
$field['index']='';
$field['length']='11';
$field['default']='';
$field['label']='create_uid';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='update_date';
$field['type']='datetime';
$field['index']='';
$field['length']='-1';
$field['default']='';
$field['label']='update_date';
$field['rules']='datetime';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='update_uid';
$field['type']='int';
$field['index']='';
$field['length']='11';
$field['default']='';
$field['label']='update_uid';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='owner';
$field['type']='int';
$field['index']='';
$field['length']='11';
$field['default']='';
$field['label']='owner';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='';
$field['js']='';
$field['prompt']='';
$fields[]=$field;


/* =========================================================
   INDEXES
   ========================================================= */
$index['name']='pk_dbx_my_testdata';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

