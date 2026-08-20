<?php
$messages = array();
$messages['bar_title'] = 'Workflow instances';
$messages['bar_subtitle'] = 'Running and completed workflows';
$messages['module_bindings'] = 'Module bindings';
$messages['new_binding'] = 'New binding';
$messages['new_workflow'] = 'New workflow';
$messages['report_info'] = 'View and filter workflow instances.';
$messages['filter_applied'] = 'The filter was applied.';
$messages['validation_error'] = 'Please check your entries.';
$messages['column_start'] = 'Start';
$messages['column_workflow'] = 'Workflow';
$messages['column_goal'] = 'Goal';
$messages['column_status'] = 'Status';
$messages['column_task'] = 'Task';
$messages['column_message'] = 'Message';
$messages['column_action'] = 'Action';
$messages['status_running'] = 'Running';
$messages['status_finishing'] = 'Finishing';
$messages['status_paused'] = 'Paused';
$messages['status_finished'] = 'Finished';
$messages['status_canceled'] = 'Cancelled';
$messages['status_error'] = 'Error';
$messages['status_unknown'] = 'Unknown';
$messages['action_view'] = 'View';
$messages['action_continue'] = 'Continue';
$messages['action_title'] = '{action}: workflow #{id}';


$field['name']='dbx_rrows';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='50';
$field['label']='Number';
$field['rules']='int';
$field['options']='10=10&25=25&50=50&100=100';
$fields[]=$field;

$field['name']='dbx_rsort';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='create_date';
$field['label']='Sorting';
$field['rules']='parameter';
$field['options']='id=ID&create_date=Start&workflow_key=Workflow&result_label=Result&status=Status&current_need=Step&percent=Percent';
$fields[]=$field;

$field['name']='dbx_rdesc';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='DESC';
$field['label']='Direction';
$field['rules']='parameter';
$field['options']='ASC=Ascending&DESC=Descending';
$fields[]=$field;

$field['name']='dbx_rwhere';
$field['type']='varchar';
$field['tpl']='dbx|search';
$field['default']='';
$field['label']='Search';
$field['rules']='parameter';
$field['options']='';
$fields[]=$field;

$field['name']='dbx_rselect';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='0';
$field['label']='Selected';
$field['rules']='parameter';
$field['options']='0=All';
$fields[]=$field;
