<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
$messages['bar_title'] = 'Workflow-Instanzen';
$messages['bar_subtitle'] = 'Laufende und abgeschlossene Workflows';
$messages['module_bindings'] = 'Modul-Bindings';
$messages['new_binding'] = 'Neues Binding';
$messages['new_workflow'] = 'Neuer Workflow';
$messages['report_info'] = 'Workflow-Instanzen anzeigen und filtern.';
$messages['filter_applied'] = 'Filter wurde angewendet.';
$messages['validation_error'] = 'Bitte prüfen Sie die Eingaben.';
$messages['column_start'] = 'Start';
$messages['column_workflow'] = 'Workflow';
$messages['column_goal'] = 'Ziel';
$messages['column_status'] = 'Status';
$messages['column_task'] = 'Aufgabe';
$messages['column_message'] = 'Meldung';
$messages['column_action'] = 'Aktion';
$messages['status_running'] = 'Laufend';
$messages['status_finishing'] = 'Wird abgeschlossen';
$messages['status_paused'] = 'Pausiert';
$messages['status_finished'] = 'Fertig';
$messages['status_canceled'] = 'Abgebrochen';
$messages['status_error'] = 'Fehler';
$messages['status_unknown'] = 'Unbekannt';
$messages['action_view'] = 'Ansehen';
$messages['action_continue'] = 'Fortsetzen';
$messages['action_title'] = '{action}: Workflow #{id}';


$field['name']='dbx_rrows';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='50';
$field['label']='Anz.Seite';
$field['rules']='int';
$field['options']='10=10&25=25&50=50&100=100';
$fields[]=$field;

$field['name']='dbx_rsort';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='create_date';
$field['label']='Sortierung';
$field['rules']='parameter';
$field['options']='id=ID&create_date=Start&workflow_key=Workflow&result_label=Ergebnis&status=Status&current_need=Schritt&percent=Prozent';
$fields[]=$field;

$field['name']='dbx_rdesc';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='DESC';
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
