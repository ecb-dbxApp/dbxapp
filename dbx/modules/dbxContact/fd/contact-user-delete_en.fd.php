<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';
$messages['bar_title'] = 'Hide request';
$messages['validation_error'] = 'Please confirm that you want to hide the request.';
$messages['delete_success'] = 'The request was removed from your view. The support history is retained.';
$messages['delete_error'] = 'The request could not be hidden.';
$messages['back_to_requests'] = 'Back to my requests';


$field=array();
$field['name']='confirm_delete';
$field['type']='int';
$field['length']='1';
$field['default']='0';
$field['label']='Remove request from my view';
$field['rules']='int|min=1';
$field['errormsg']='Please confirm the removal.';
$field['options']='1=Yes';
$field['tpl']='checkbox-label';
$fields[]=$field;
