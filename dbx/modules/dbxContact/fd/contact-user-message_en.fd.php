<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';
$messages['bar_title'] = 'Add message';
$messages['bar_subtitle'] = 'Your message will be visible to support';
$messages['form_info'] = 'Add information or answer a question from support.';
$messages['validation_error'] = 'Please check the highlighted message.';
$messages['message_success'] = 'Your message was added to the ticket.';
$messages['message_error'] = 'The message could not be saved.';
$messages['ticket_closed'] = 'This ticket is closed. Please create a new request for a new issue.';


$field=array();
$field['name']='body';
$field['type']='mediumtext';
$field['length']='-1';
$field['default']='';
$field['label']='Your message';
$field['rules']='*|min=2|max=10000';
$field['errormsg']='Please enter a message.';
$field['placeholder']='Additional information or a question about this ticket';
$field['data']='rows=6';
$field['tpl']='textarea-label';
$fields[]=$field;
