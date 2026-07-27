<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';


$field=array();
$field['name']='confirm_delete';
$field['type']='int';
$field['length']='1';
$field['default']='0';
$field['label']='Permanently delete the ticket and its complete history';
$field['rules']='int|min=1';
$field['errormsg']='Please confirm the permanent deletion.';
$field['options']='1=Yes';
$field['tpl']='checkbox-label';
$fields[]=$field;
