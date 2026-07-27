<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='dbx|dbxPerformance.db3';
$table['table']='dbx_performance_request';
$table['datadic']='dbxPerformanceRequest';
$table['primary']='';
$table['language']='';
$table['version']='1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='request_date DESC';
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
   array('request_date', 'datetime', 'MU', '-1', '', 'Request Datum', 'datetime'),
   array('uid', 'int', 'MU', '11', '0', 'User', 'int'),
   array('session_id', 'varchar', '', '128', '', 'Session', 'varchar'),
   array('modul', 'varchar', 'MU', '80', '', 'Modul', 'varchar'),
   array('run1', 'varchar', '', '80', '', 'Run 1', 'varchar'),
   array('run2', 'varchar', '', '80', '', 'Run 2', 'varchar'),
   array('ajax', 'int', '', '11', '0', 'Ajax', 'int'),
   array('sync', 'int', '', '11', '0', 'Sync', 'int'),
   array('method', 'varchar', '', '12', '', 'Methode', 'varchar'),
   array('uri', 'text', '', '-1', '', 'URI', 'text'),
   array('total_time_ms', 'int', 'MU', '11', '0', 'Gesamtzeit ms', 'int'),
   array('total_memory_kb', 'int', '', '11', '0', 'Memory KB', 'int'),
   array('peak_memory_mb', 'int', '', '11', '0', 'End Memory MB', 'int'),
   array('timer_count', 'int', '', '11', '0', 'Timer', 'int'),
   array('sample_rate', 'int', '', '11', '1', 'Sample Rate', 'int'),
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
$index['name']='pk_dbx_performance_request';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

$index['name']='idx_dbx_performance_request_date';
$index['type']='INDEX';
$index['fields']='request_date';
$index['unique']='0';
$index['comment']='request order';
$indexes[]=$index;

$index['name']='idx_dbx_performance_request_modul';
$index['type']='INDEX';
$index['fields']='modul';
$index['unique']='0';
$index['comment']='module filter';
$indexes[]=$index;

$index['name']='idx_dbx_performance_request_total_time';
$index['type']='INDEX';
$index['fields']='total_time_ms';
$index['unique']='0';
$index['comment']='slow requests';
$indexes[]=$index;
