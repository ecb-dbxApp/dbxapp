<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';
$messages['bar_title'] = 'Workflow definitions';
$messages['bar_subtitle'] = 'Manage workflow definitions';
$messages['module_bindings'] = 'Module bindings';
$messages['new_binding'] = 'New binding';
$messages['new_workflow'] = 'New workflow';
$messages['report_info'] = 'Manage, filter and edit workflow definitions.';
$messages['filter_applied'] = 'The filter was applied.';
$messages['validation_error'] = 'Please check your entries.';
$messages['column_key'] = 'Key';
$messages['column_title'] = 'Title';
$messages['column_goal'] = 'Goal';
$messages['column_active'] = 'Active';
$messages['column_updated'] = 'Updated';
$messages['column_action'] = 'Action';


$field['name']='dbx_rrows';
$field['type']='int';
$field['tpl']='select-single-label';
$field['default']='30';
$field['label']='Number';
$field['rules']='int';
$field['options']='10=10&15=15&20=20&30=30&50=50&100=100';
$fields[]=$field;

$field['name']='dbx_rsort';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='title';
$field['label']='Sorting';
$field['rules']='parameter';
$field['options']='id=ID&workflow_key=Key&title=Title&result_label=Result&active=Active&update_date=Update';
$fields[]=$field;

$field['name']='dbx_rdesc';
$field['type']='varchar';
$field['tpl']='select-single-label';
$field['default']='ASC';
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
