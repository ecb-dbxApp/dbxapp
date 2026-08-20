<?php
$messages = array();
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
