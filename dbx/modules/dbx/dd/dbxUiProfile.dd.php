<?php

$table['server']='dbx|dbx.db3';
$table['table']='dbx_ui_profile';
$table['datadic']='dbxUiProfile';
$table['primary']='id';
$table['language']='0';
$table['version']='1.0';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='user_id ASC, context ASC';
$table['form-dd-table']='';
$table['read']='authenticated,admin';
$table['create']='authenticated,admin';
$table['update']='owner,admin';
$table['delete']='owner,admin';
$table['read_owner']='owner,admin';
$table['create_owner']='authenticated,admin';
$table['update_owner']='owner,admin';
$table['delete_owner']='owner,admin';

$field=array();
$field['name']='id'; $field['type']='int'; $field['index']='PRI'; $field['length']='11'; $field['default']='';
$field['label']='ID'; $field['rules']='int'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='hidden'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='user_id'; $field['type']='int'; $field['index']='MU'; $field['length']='11'; $field['default']='0';
$field['label']='Benutzer'; $field['rules']='int|min=1'; $field['tooltip']='Eigentuemer des UI-Profils.'; $field['errormsg']=''; $field['placeholder']='';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='number-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='context'; $field['type']='varchar'; $field['index']='MU'; $field['length']='20'; $field['default']='desktop';
$field['label']='Darstellungskontext'; $field['rules']='parameter|max=20'; $field['tooltip']='Getrennte Profile fuer Desktop und Mobile.'; $field['errormsg']=''; $field['placeholder']='';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='text-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='settings'; $field['type']='mediumtext'; $field['index']=''; $field['length']='-1'; $field['default']='{}';
$field['label']='UI-Einstellungen'; $field['rules']='json|max=131072'; $field['tooltip']='Sessionuebergreifend gespeichertes Benutzerprofil.'; $field['errormsg']=''; $field['placeholder']='{}';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='textarea-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='revision'; $field['type']='int'; $field['index']=''; $field['length']='11'; $field['default']='1';
$field['label']='Revision'; $field['rules']='int|min=1'; $field['tooltip']='Wird bei jeder Speicherung erhoeht.'; $field['errormsg']=''; $field['placeholder']='';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='number-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='create_date'; $field['type']='datetime'; $field['index']=''; $field['length']='-1'; $field['default']='';
$field['label']='Erstellt'; $field['rules']='datetime'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='';
$field['convert']='date_time'; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='datetime-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='create_uid'; $field['type']='int'; $field['index']=''; $field['length']='11'; $field['default']='0';
$field['label']='Erstellt von'; $field['rules']='int'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='number-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='update_date'; $field['type']='datetime'; $field['index']='MUL'; $field['length']='-1'; $field['default']='';
$field['label']='Geaendert'; $field['rules']='datetime'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='';
$field['convert']='date_time'; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='datetime-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='update_uid'; $field['type']='int'; $field['index']=''; $field['length']='11'; $field['default']='0';
$field['label']='Geaendert von'; $field['rules']='int'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='number-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='owner'; $field['type']='int'; $field['index']=''; $field['length']='11'; $field['default']='0';
$field['label']='Owner'; $field['rules']='int'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='number-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$index=array();
$index['name']='pk_dbx_ui_profile'; $index['type']='PRIMARY'; $index['fields']='id'; $index['unique']='1'; $index['comment']='Primaerschluessel'; $indexes[]=$index;

$index=array();
$index['name']='uidx_dbx_ui_profile_user_context'; $index['type']='UNIQUE'; $index['fields']='user_id,context'; $index['unique']='1'; $index['comment']='Ein Profil je Benutzer und Darstellungskontext'; $indexes[]=$index;

$index=array();
$index['name']='idx_dbx_ui_profile_update_date'; $index['type']='INDEX'; $index['fields']='update_date'; $index['unique']='0'; $index['comment']='Aenderungszeitpunkt'; $indexes[]=$index;
