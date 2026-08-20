<?php
$messages = array();
$messages['bar_title'] = 'Reply or internal note';
$messages['bar_subtitle'] = 'The complete history remains attached to the ticket';
$messages['form_info'] = 'Public replies can be sent by email. Internal notes remain visible only in administration.';
$messages['validation_error'] = 'Please check the highlighted entries.';
$messages['mail_warning'] = 'The message and status were saved, but the email could not be sent.';
$messages['internal_success'] = 'The internal note and status were saved.';
$messages['reply_success'] = 'The reply and status were saved.';
$messages['mail_success_suffix'] = ' The email was sent.';
$messages['message_error'] = 'The message could not be saved.';


$field=array();
$field['name']='status';
$field['type']='varchar';
$field['length']='24';
$field['default']='answered';
$field['label']='New status';
$field['rules']='parameter|max=24';
$field['options']='open=Open&in_progress=In progress&waiting_customer=Waiting for customer&answered=Answered&closed=Closed';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='priority';
$field['type']='varchar';
$field['length']='16';
$field['default']='normal';
$field['label']='Priority';
$field['rules']='parameter|max=16';
$field['options']='low=Low&normal=Normal&high=High&urgent=Urgent';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='visibility';
$field['type']='varchar';
$field['length']='16';
$field['default']='public';
$field['label']='Message type';
$field['rules']='parameter|max=16';
$field['options']='public=Reply to requester&internal=Internal note';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='body';
$field['type']='mediumtext';
$field['length']='-1';
$field['default']='';
$field['label']='Answer or note';
$field['rules']='*|min=2|max=10000';
$field['errormsg']='Please enter a message.';
$field['placeholder']='Response to the requestor or internal note';
$field['data']='rows=9';
$field['tpl']='textarea-label';
$fields[]=$field;

$field=array();
$field['name']='send_mail';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='Send a reply as an email';
$field['rules']='int';
$field['options']='1=Yes';
$field['tpl']='checkbox-label';
$fields[]=$field;
