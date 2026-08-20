<?php

$table['server']='dbxChangeLog_admin|dbxChangeLog.db3';
$table['table']='dbxChangeLog';
$table['datadic']='dbxChangeLog';
$table['primary']='id';
$table['language']='0';
$table['version']='1.0';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='change_date DESC, id DESC';
$table['form-dd-table']='';
$table['read']='*';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';
$table['read_owner']='*';
$table['create_owner']='admin';
$table['update_owner']='admin';
$table['delete_owner']='admin';

$field=array();
$field['name']='id'; $field['type']='int'; $field['index']='PRI'; $field['length']='11'; $field['default']='';
$field['label']='ID'; $field['rules']='int'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='hidden'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='change_date'; $field['type']='datetime'; $field['index']='MUL'; $field['length']='-1'; $field['default']='';
$field['label']='Datum und Uhrzeit'; $field['rules']='datetime'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='';
$field['convert']='date_time'; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='datetime-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='actor'; $field['type']='varchar'; $field['index']='MUL'; $field['length']='80'; $field['default']='Codex';
$field['label']='Akteur'; $field['rules']='*|min=1|max=80'; $field['tooltip']=''; $field['errormsg']=''; $field['placeholder']='Codex oder dbxKi';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='text-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='summary'; $field['type']='varchar'; $field['index']='MUL'; $field['length']='255'; $field['default']='';
$field['label']='Änderung'; $field['rules']='*|min=3|max=255'; $field['tooltip']='Ein verständlicher Eintrag je abgeschlossenem Änderungsblock.'; $field['errormsg']=''; $field['placeholder']='dbxReport Anzeige Gesamt-Datensätze korrigiert';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='text-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='details'; $field['type']='text'; $field['index']=''; $field['length']='-1'; $field['default']='';
$field['label']='Warum'; $field['rules']='*|min=3|max=4000'; $field['tooltip']='Verständliche Begründung für die Änderung.'; $field['errormsg']=''; $field['placeholder']='Warum war diese Änderung erforderlich?';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='textarea-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;

$field=array();
$field['name']='resources'; $field['type']='text'; $field['index']=''; $field['length']='-1'; $field['default']='';
$field['label']='Betroffene Ressourcen'; $field['rules']='*|max=8000'; $field['tooltip']='Eine Datei, DD, Datenbank oder andere Ressource pro Zeile.'; $field['errormsg']=''; $field['placeholder']='dbx/include/dbxReportChrome.trait.php';
$field['convert']=''; $field['protect']='0'; $field['group']=''; $field['mask']=''; $field['data']='';
$field['options']=''; $field['tpl']='textarea-label'; $field['js']=''; $field['prompt']=''; $fields[]=$field;
