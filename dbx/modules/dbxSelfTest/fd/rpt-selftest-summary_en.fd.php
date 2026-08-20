<?php

$messages = array();
$messages['bar_title'] = 'Test overview';
$messages['bar_subtitle'] = 'Simple list of all tests from the latest run.';
$messages['column_name'] = 'Test';
$messages['column_category'] = 'Area';
$messages['column_status'] = 'Status';
$messages['column_duration'] = 'Duration';
$messages['status_passed'] = 'Passed';
$messages['status_failed'] = 'Failed';
$messages['status_skipped'] = 'Skipped';
$messages['summary_line'] =
    'Last test on {date} — {passed} passed / {failed} failed';
$messages['summary_line_with_skipped'] =
    'Last test on {date} — {passed} passed / {failed} failed'
    . ' / {skipped} skipped';
$messages['no_run'] = 'No logged test run is available yet.';
$messages['empty_result'] = 'No tests found.';
$messages['clear_history_label'] = 'Clear history';
$messages['clear_history_title'] = 'Clear test history';
$messages['clear_history_question'] = 'Really delete all logged test runs?';
$messages['clear_history_hint'] =
    'This cannot be undone. A test that is currently running is kept.';
$messages['clear_history_success'] = 'Test history cleared ({count} logs removed).';

$field = array();
$field['name'] = 'dbx_rrows';
$field['type'] = 'int';
$field['tpl'] = 'select-single-label';
$field['default'] = '100';
$field['label'] = 'Rows per page';
$field['rules'] = 'int';
$field['options'] = '50=50&100=100&200=200&500=500';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rsort';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'status';
$field['label'] = 'Sort by';
$field['rules'] = 'parameter';
$field['options'] = 'name=Test&category=Area&status=Status&duration_ms=Duration';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rdesc';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'ASC';
$field['label'] = 'Direction';
$field['rules'] = 'parameter';
$field['options'] = 'ASC=Ascending&DESC=Descending';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rstatus';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'all';
$field['label'] = 'Status';
$field['rules'] = 'parameter|max=24';
$field['options'] = 'all=All&passed=Passed&failed=Failed&skipped=Skipped';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rwhere';
$field['type'] = 'varchar';
$field['tpl'] = 'dbx|search';
$field['default'] = '';
$field['label'] = 'Search';
$field['rules'] = 'sqlsearch|max=64';
$fields[] = $field;

?>
