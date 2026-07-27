<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';
$messages['page_title'] = 'Withdrawal';
$messages['page_subtitle'] = 'Read the withdrawal policy and submit a withdrawal directly.';
$messages['empty_content'] = 'The CMS page is empty.';
$messages['bar_title'] = 'Submit withdrawal';
$messages['bar_subtitle'] = 'Assign the request to the correct order';
$messages['form_info'] = 'Enter the order number and contact details so that the withdrawal can be assigned.';
$messages['validation_error'] = 'Please check the highlighted required fields.';
$messages['withdrawal_success'] = 'Your withdrawal was saved. We will check its assignment to the order.';
$messages['withdrawal_error'] = 'The withdrawal could not be saved.';

$addField = function($name, $type, $label, $rules, $tpl, $extra = array()) use (&$fields) {
   $field=array();
   $field['name']=$name;
   $field['type']=$type;
   $field['index']='';
   $field['length']=$extra['length'] ?? '';
   $field['default']=$extra['default'] ?? '';
   $field['label']=$label;
   $field['rules']=$rules;
   $field['tooltip']=$extra['tooltip'] ?? '';
   $field['errormsg']=$extra['errormsg'] ?? '';
   $field['placeholder']=$extra['placeholder'] ?? '';
   $field['convert']='';
   $field['protect']='0';
   $field['mask']='';
   $field['data']=$extra['data'] ?? '';
   $field['options']=$extra['options'] ?? '';
   $field['tpl']=$tpl;
   $fields[]=$field;
};

$addField('order_no','varchar','Order number','parameter|max=40','text-label',array('placeholder'=>'S20260710123456-1234'));
$addField('customer_name','varchar','Name','*|min=2|max=180','text-label',array('placeholder'=>'Your name'));
$addField('customer_email','varchar','E-mail','email|max=180','text-label',array('placeholder'=>'name@example.org'));
$addField('customer_address','mediumtext','Address','*|min=8|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>"Name\nStreet and house number\nPostal code and city\nCountry"));
$addField('reason','mediumtext','Message','*|max=3000','textarea-label',array('data'=>'rows=5','placeholder'=>'I hereby withdraw from my order. Optional: affected items or a question.'));
?>
