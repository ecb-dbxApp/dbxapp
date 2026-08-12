<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='dbx|dbxPerformance.db3';
$table['table']='dbx_performance_timer';
$table['datadic']='dbxPerformanceTimer';
$table['primary']='';
$table['language']='';
$table['version']='2';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='request_id DESC, sort_order ASC';
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
foreach (array(
   array('id', 'int', 'PRI', '11', '', 'id', 'int'),
   array('create_date', 'datetime', '', '-1', '', 'create_date', 'datetime'),
   array('create_uid', 'int', '', '11', '0', 'create_uid', 'int'),
   array('update_date', 'datetime', '', '-1', '', 'update_date', 'datetime'),
   array('update_uid', 'int', '', '11', '0', 'update_uid', 'int'),
   array('owner', 'int', '', '11', '0', 'owner', 'int'),
   array('request_id', 'int', 'MU', '11', '0', 'Request', 'int'),
   array('request_date', 'datetime', 'MU', '-1', '', 'Request Datum', 'datetime'),
   array('sort_order', 'int', '', '11', '0', 'Sortierung', 'int'),
   array('section', 'varchar', 'MU', '80', '', 'Section', 'varchar'),
   array('fingerprint', 'varchar', 'MU', '32', '', 'Query-Fingerprint', 'varchar'),
   array('info', 'varchar', '', '160', '', 'Info', 'varchar'),
   array('time_ms', 'int', 'MU', '11', '0', 'Zeit ms', 'int'),
   array('memory_kb', 'int', '', '11', '0', 'Memory KB', 'int'),
   array('start_memory_kb', 'int', '', '11', '0', 'Start Memory KB', 'int'),
   array('end_memory_kb', 'int', '', '11', '0', 'End Memory KB', 'int'),
   array('query_count', 'int', '', '11', '0', 'Queries', 'int'),
   array('duplicate_count', 'int', 'MU', '11', '0', 'Duplikate', 'int'),
   array('max_time_ms', 'int', 'MU', '11', '0', 'Max. Zeit ms', 'int'),
   array('affected_rows', 'int', '', '11', '0', 'Betroffene Zeilen', 'int'),
   array('failure_count', 'int', '', '11', '0', 'Fehler', 'int'),
) as $def) {
   $field['name']=$def[0];
   $field['type']=$def[1];
   $field['index']=$def[2];
   $field['length']=$def[3];
   $field['default']=$def[4];
   $field['label']=$def[5];
   $field['rules']=$def[6];
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
}


/* =========================================================
   INDEXES
   ========================================================= */
$index['name']='pk_dbx_performance_timer';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

$index['name']='idx_dbx_performance_timer_request';
$index['type']='INDEX';
$index['fields']='request_id';
$index['unique']='0';
$index['comment']='request detail';
$indexes[]=$index;

$index['name']='idx_dbx_performance_timer_request_section';
$index['type']='INDEX';
$index['fields']='request_id,section';
$index['unique']='0';
$index['comment']='request detail by section';
$indexes[]=$index;

$index['name']='idx_dbx_performance_timer_date';
$index['type']='INDEX';
$index['fields']='request_date';
$index['unique']='0';
$index['comment']='history order';
$indexes[]=$index;

$index['name']='idx_dbx_performance_timer_section';
$index['type']='INDEX';
$index['fields']='section';
$index['unique']='0';
$index['comment']='section analysis';
$indexes[]=$index;

$index['name']='idx_dbx_performance_timer_fingerprint';
$index['type']='INDEX';
$index['fields']='fingerprint';
$index['unique']='0';
$index['comment']='query fingerprint analysis';
$indexes[]=$index;
