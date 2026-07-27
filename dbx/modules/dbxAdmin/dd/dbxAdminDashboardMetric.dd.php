<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='dbxAdmin|dbxAdmin.db3';
$table['table']='dbx_admin_dashboard_metric';
$table['datadic']='dbxAdminDashboardMetric';
$table['primary']='';
$table['language']='';
$table['version']='2';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='snapshot_date DESC';
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

$field['name']='bucket_key';
$field['type']='varchar';
$field['index']='MU';
$field['length']='16';
$field['default']='';
$field['label']='bucket_key';
$field['rules']='parameter';
$field['tooltip']='15-Minuten-Zeitschluessel';
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

$field['name']='snapshot_date';
$field['type']='datetime';
$field['index']='MU';
$field['length']='-1';
$field['default']='';
$field['label']='snapshot_date';
$field['rules']='datetime';
$field['tooltip']='Zeitpunkt der Messung';
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

foreach (array(
   'users'          => 'Benutzer',
   'online'         => 'Online',
   'modules'        => 'Module',
   'records'        => 'Datensaetze',
   'databases'      => 'Datenbanken',
   'health_percent' => 'Systemzustand',
   'active_users'   => 'Aktive Benutzer',
   'sessions'       => 'Sessions',
   'tables'         => 'Tabellen',
   'sysmsg_risk'    => 'Warnungen/Fehler',
   'missing'        => 'Missing',
   'request_runtime_ms' => 'Request ms',
   'memory_peak_mb'     => 'Memory MB',
   'memory_peak_kb'     => 'Memory KB',
) as $name => $label) {
   $field['name']=$name;
   $field['type']='int';
   $field['index']='';
   $field['length']='11';
   $field['default']='0';
   $field['label']=$label;
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
}


/* =========================================================
   INDEXES
   ========================================================= */
$index['name']='pk_dbx_admin_dashboard_metric';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

$index['name']='uidx_dbx_admin_dashboard_metric_bucket';
$index['type']='UNIQUE';
$index['fields']='bucket_key';
$index['unique']='1';
$index['comment']='one snapshot per bucket';
$indexes[]=$index;

$index['name']='idx_dbx_admin_dashboard_metric_snapshot_date';
$index['type']='INDEX';
$index['fields']='snapshot_date';
$index['unique']='0';
$index['comment']='history order';
$indexes[]=$index;
