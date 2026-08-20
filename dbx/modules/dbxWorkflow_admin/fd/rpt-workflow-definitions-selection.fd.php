<?php
$messages = array();
$messages['bar_title'] = 'Workflow-Definitionen';
$messages['bar_subtitle'] = 'Workflow-Definitionen verwalten';
$messages['module_bindings'] = 'Modul-Bindings';
$messages['new_binding'] = 'Neues Binding';
$messages['new_workflow'] = 'Neuer Workflow';
$messages['report_info'] = 'Workflow-Definitionen verwalten, filtern und bearbeiten.';
$messages['filter_applied'] = 'Filter wurde angewendet.';
$messages['validation_error'] = 'Bitte prüfen Sie die Eingaben.';
$messages['column_key'] = 'Key';
$messages['column_title'] = 'Titel';
$messages['column_goal'] = 'Ziel';
$messages['column_active'] = 'Aktiv';
$messages['column_updated'] = 'Aktualisiert';
$messages['column_action'] = 'Aktion';


$field['name']='dbx_rrows';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='30';
$field['label']='Anz.Seite';
$field['rules']='int';
$field['options']='10=10&15=15&20=20&30=30&50=50&100=100';
$fields[]=$field;

$field['name']='dbx_rsort';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='title';
$field['label']='Sortierung';
$field['rules']='parameter';
$field['options']='id=ID&workflow_key=Key&title=Titel&result_label=Ergebnis&active=Aktiv&update_date=Update';
$fields[]=$field;

$field['name']='dbx_rdesc';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='ASC';
$field['label']='Auf/Ab';
$field['rules']='parameter';
$field['options']='ASC=Aufsteigend&DESC=Absteigend';
$fields[]=$field;

$field['name']='dbx_rwhere';
$field['type']='varchar';
$field['tpl']='dbx|search';
$field['default']='';
$field['label']='Suchen';
$field['rules']='parameter';
$field['options']='';
$fields[]=$field;

$field['name']='dbx_rselect';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='0';
$field['label']='Ausgewaehlte';
$field['rules']='parameter';
$field['options']='0=Alle';
$fields[]=$field;
