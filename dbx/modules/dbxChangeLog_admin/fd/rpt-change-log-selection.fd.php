<?php
$messages=array();
$messages['report_title']='Change Log';
$messages['report_subtitle']='Nach Änderung, Ressource oder Akteur filtern.';
$messages['action_new']='Neuer Eintrag';
$messages['column_date']='Wann';
$messages['column_summary']='Was';
$messages['column_details']='Warum';
$messages['column_actor']='Akteur';
$messages['column_resources']='Ressourcen';
$messages['delete_success']='Change-Log-Eintrag wurde gelöscht.';
$messages['delete_error']='Change-Log-Eintrag konnte nicht gelöscht werden.';
$fields=array();
$field=array('name'=>'dbx_rrows','type'=>'int','tpl'=>'select-single-label','default'=>'20','label'=>'Zeilen','rules'=>'int','options'=>'10=10&20=20&50=50&100=100'); $fields[]=$field;
$field=array('name'=>'dbx_rsort','type'=>'varchar','tpl'=>'select-single-label','default'=>'change_date','label'=>'Sortierung','rules'=>'parameter','options'=>'change_date=Datum&summary=Änderung&actor=Akteur'); $fields[]=$field;
$field=array('name'=>'dbx_rdesc','type'=>'varchar','tpl'=>'select-single-label','default'=>'DESC','label'=>'Richtung','rules'=>'parameter','options'=>'DESC=Neueste zuerst&ASC=Älteste zuerst'); $fields[]=$field;
$field=array('name'=>'dbx_ractor','type'=>'varchar','tpl'=>'text-label','default'=>'','label'=>'Akteur','rules'=>'sqlsearch|max=80','options'=>''); $fields[]=$field;
$field=array('name'=>'dbx_rwhere','type'=>'varchar','tpl'=>'dbx|search','default'=>'','label'=>'Suchen','rules'=>'sqlsearch|max=128','options'=>''); $fields[]=$field;
