<?php
require __DIR__ . '/rpt-change-log-selection.fd.php';
$messages['report_title']='Change Log';
$messages['report_subtitle']='Filter by change, resource or actor.';
$messages['action_new']='New entry';
$messages['column_date']='When';
$messages['column_summary']='What';
$messages['column_details']='Why';
$messages['column_actor']='Actor';
$messages['column_resources']='Resources';
$messages['delete_success']='Change-log entry deleted.';
$messages['delete_error']='Change-log entry could not be deleted.';
$labels=array('dbx_rrows'=>'Rows','dbx_rsort'=>'Sort','dbx_rdesc'=>'Direction','dbx_ractor'=>'Actor','dbx_rwhere'=>'Search');
foreach ($fields as &$field) {
    $field['label']=$labels[$field['name']] ?? $field['label'];
    if ($field['name']==='dbx_rsort') $field['options']='change_date=Date&summary=Change&actor=Actor';
    if ($field['name']==='dbx_rdesc') $field['options']='DESC=Newest first&ASC=Oldest first';
}
unset($field);
