<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='dbxTrace.db3';
$table['table']='dbx_trace';
$table['datadic']='dbxTrace';
$table['primary']='';
$table['language']='';
$table['version']='1.0';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='';
$table['form-dd-table']='';
$table['read']='admin';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';
$table['read_owner']='*';
$table['create_owner']='*';
$table['update_owner']='*';
$table['delete_owner']='*';


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
$field['tooltip']='wird automatisch vergeben';
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
$field['index']='MU';
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
$field['index']='MU';
$field['length']='11';
$field['default']='0';
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

$field['name']='action';
$field['type']='varchar';
$field['index']='MU';
$field['length']='16';
$field['default']='';
$field['label']='action';
$field['rules']='varchar';
$field['tooltip']='insert | update | delete';
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

$field['name']='dd';
$field['type']='varchar';
$field['index']='MU';
$field['length']='64';
$field['default']='';
$field['label']='dd';
$field['rules']='varchar';
$field['tooltip']='betroffene Tabelle';
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

$field['name']='record_id';
$field['type']='int';
$field['index']='MU';
$field['length']='11';
$field['default']='0';
$field['label']='record_id';
$field['rules']='int';
$field['tooltip']='Primärschlüssel des Datensatzes';
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

$field['name']='data_json';
$field['type']='longtext';
$field['index']='';
$field['length']='9600';
$field['default']='';
$field['label']='data_json';
$field['rules']='longtext';
$field['tooltip']='before / delta / after';
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
$index['name']='pk_dbx_trace';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

$index['name']='idx_dbx_trace_create_date';
$index['type']='INDEX';
$index['fields']='create_date';
$index['unique']='0';
$index['comment']='from field index MU';
$indexes[]=$index;

$index['name']='idx_dbx_trace_owner';
$index['type']='INDEX';
$index['fields']='owner';
$index['unique']='0';
$index['comment']='from field index MU';
$indexes[]=$index;

$index['name']='idx_dbx_trace_action';
$index['type']='INDEX';
$index['fields']='action';
$index['unique']='0';
$index['comment']='from field index MU';
$indexes[]=$index;

$index['name']='idx_dbx_trace_dd';
$index['type']='INDEX';
$index['fields']='dd';
$index['unique']='0';
$index['comment']='from field index MU';
$indexes[]=$index;

$index['name']='idx_dbx_trace_record_id';
$index['type']='INDEX';
$index['fields']='record_id';
$index['unique']='0';
$index['comment']='from field index MU';
$indexes[]=$index;

