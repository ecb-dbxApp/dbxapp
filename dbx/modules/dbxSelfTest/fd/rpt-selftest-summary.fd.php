<?php

$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
$messages['bar_title'] = 'Testübersicht';
$messages['bar_subtitle'] = 'Einfache Liste aller Tests des letzten Laufs.';
$messages['column_name'] = 'Test';
$messages['column_category'] = 'Bereich';
$messages['column_status'] = 'Status';
$messages['column_duration'] = 'Dauer';
$messages['status_passed'] = 'Bestanden';
$messages['status_failed'] = 'Fehlgeschlagen';
$messages['status_skipped'] = 'Übersprungen';
$messages['summary_line'] =
    'Letzter Test am {date} — {passed} bestanden / {failed} nicht bestanden';
$messages['summary_line_with_skipped'] =
    'Letzter Test am {date} — {passed} bestanden / {failed} nicht bestanden'
    . ' / {skipped} übersprungen';
$messages['no_run'] = 'Es liegt noch kein protokollierter Testlauf vor.';
$messages['empty_result'] = 'Keine Tests gefunden.';
$messages['clear_history_label'] = 'Verlauf löschen';
$messages['clear_history_title'] = 'Testverlauf löschen';
$messages['clear_history_question'] = 'Alle protokollierten Testläufe wirklich löschen?';
$messages['clear_history_hint'] =
    'Dieser Vorgang kann nicht rückgängig gemacht werden. Ein gerade aktiv '
    . 'laufender Test bleibt erhalten.';
$messages['clear_history_success'] = 'Testverlauf gelöscht ({count} Protokolle entfernt).';

$field = array();
$field['name'] = 'dbx_rrows';
$field['type'] = 'int';
$field['tpl'] = 'select-single-label';
$field['default'] = '100';
$field['label'] = 'Anz. Seite';
$field['rules'] = 'int';
$field['options'] = '50=50&100=100&200=200&500=500';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rsort';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'status';
$field['label'] = 'Sortierung';
$field['rules'] = 'parameter';
$field['options'] = 'name=Test&category=Bereich&status=Status&duration_ms=Dauer';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rdesc';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'ASC';
$field['label'] = 'Richtung';
$field['rules'] = 'parameter';
$field['options'] = 'ASC=Aufsteigend&DESC=Absteigend';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rstatus';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'all';
$field['label'] = 'Status';
$field['rules'] = 'parameter|max=24';
$field['options'] = 'all=Alle&passed=Bestanden&failed=Fehlgeschlagen&skipped=Übersprungen';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rwhere';
$field['type'] = 'varchar';
$field['tpl'] = 'dbx|search';
$field['default'] = '';
$field['label'] = 'Suchen';
$field['rules'] = 'sqlsearch|max=64';
$fields[] = $field;

?>
