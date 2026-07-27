<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='dbxMissing.db3';
$table['table']='dbx_missing';
$table['datadic']='dbxMissing';
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
$field['default']='0';
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
$field['default']='0';
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

$field['name']='count';
$field['type']='int';
$field['index']='';
$field['length']='11';
$field['default']='';
$field['label']='count';
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

$field['name']='missing';
$field['type']='varchar';
$field['index']='MUL';
$field['length']='250';
$field['default']='';
$field['label']='missing';
$field['rules']='varchar';
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

$field['name']='request';
$field['type']='varchar';
$field['index']='';
$field['length']='250';
$field['default']='';
$field['label']='request';
$field['rules']='varchar';
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
$index['name']='pk_dbx_missing';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

$index['name']='idx_dbx_missing_missing';
$index['type']='INDEX';
$index['fields']='missing';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

