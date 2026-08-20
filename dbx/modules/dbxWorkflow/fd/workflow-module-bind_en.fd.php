<?php
$messages = array();
$messages['validation_error'] = 'Please check your entries.';
$messages['bar_title'] = 'Workflow module bindings';
$messages['bar_subtitle'] = 'DD-based module connections';
$messages['module_bindings'] = 'Module bindings';
$messages['new_binding'] = 'New binding';
$messages['new_workflow'] = 'New workflow';
$messages['list_info'] = 'Manage module bindings. Workflows reference bind_ref (module|bind_key); modules do not know about workflows.';
$messages['column_module'] = 'Module';
$messages['column_bind_key'] = 'Bind key';
$messages['column_title'] = 'Title';
$messages['column_active'] = 'Active';
$messages['column_update'] = 'Updated';
$messages['column_reference'] = 'Reference';
$messages['column_action'] = 'Action';
$messages['form_new_title'] = 'New module binding';
$messages['form_edit_title'] = 'Edit module binding';
$messages['form_subtitle'] = 'Workflow module connection';
$messages['form_new_info'] = 'Create a new binding or generate one from a module DD.';
$messages['form_edit_info'] = 'Edit the module binding. Reference in workflows: bind_ref = module|bind_key';
$messages['list_label'] = 'List';
$messages['save_label'] = 'Save';
$messages['generator_module_label'] = 'Module';
$messages['generator_dd_label'] = 'Datadic (DD)';
$messages['generator_dd_select'] = '-- Select DD --';
$messages['generator_success'] = 'Binding proposal generated. Please review and save it.';
$messages['generator_error'] = 'No binding could be generated from the selected DD.';
$messages['generator_validation_error'] = 'Please select a module and Datadic.';
$messages['json_invalid'] = 'The binding JSON is invalid.';
$messages['duplicate_bind_key'] = 'Module + bind key already exists.';
$messages['binding_not_found'] = 'Module binding not found.';
$messages['default_binding_title'] = 'New module binding';
$messages['default_binding_description'] = 'Unified binding: dbxWorkflow uses the module DD, FD, TPL and configuration.';


$add_field = function($name, $type, $label, $rules, $tpl, $extra = array()) use (&$fields) {
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

$add_field('modul','varchar','Module','parameter|min=2|max=80','text-label',array('placeholder'=>'dbxContact'));
$add_field('bind_key','varchar','Bind Key','parameter|min=2|max=80','text-label',array('placeholder'=>'contact_reply'));
$add_field('title','varchar','Title','*|min=2|max=160','text-label');
$add_field('description','mediumtext','Description','*|max=3000','textarea-label',array('data'=>'rows=3'));
$add_field('bind_json','mediumtext','Binding JSON','*|min=2|max=30000','textarea-label',array('data'=>'rows=18'));
$add_field('active','int','Active','int','checkbox-label',array('default'=>'1'));

?>
