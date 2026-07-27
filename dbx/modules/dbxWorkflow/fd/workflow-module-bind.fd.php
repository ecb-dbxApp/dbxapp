<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
$messages['validation_error'] = 'Bitte Eingaben prüfen.';
$messages['bar_title'] = 'Workflow-Modul-Bindings';
$messages['bar_subtitle'] = 'DD-basierte Modul-Anbindungen';
$messages['module_bindings'] = 'Modul-Bindings';
$messages['new_binding'] = 'Neues Binding';
$messages['new_workflow'] = 'Neuer Workflow';
$messages['list_info'] = 'Modul-Bindings verwalten. Workflows referenzieren bind_ref (modul|bind_key); Module kennen keinen Workflow.';
$messages['column_module'] = 'Modul';
$messages['column_bind_key'] = 'Bind Key';
$messages['column_title'] = 'Titel';
$messages['column_active'] = 'Aktiv';
$messages['column_update'] = 'Aktualisiert';
$messages['column_reference'] = 'Referenz';
$messages['column_action'] = 'Aktion';
$messages['form_new_title'] = 'Neues Modul-Binding';
$messages['form_edit_title'] = 'Modul-Binding bearbeiten';
$messages['form_subtitle'] = 'Workflow-Modul-Anbindung';
$messages['form_new_info'] = 'Neues Binding anlegen oder per Generator aus einer Modul-DD erzeugen.';
$messages['form_edit_info'] = 'Modul-Binding bearbeiten. Referenz in Workflows: bind_ref = modul|bind_key';
$messages['list_label'] = 'Liste';
$messages['save_label'] = 'Speichern';
$messages['generator_module_label'] = 'Modul';
$messages['generator_dd_label'] = 'Datadic (DD)';
$messages['generator_dd_select'] = '-- DD wählen --';
$messages['generator_success'] = 'Binding-Vorschlag wurde erzeugt. Bitte prüfen und speichern.';
$messages['generator_error'] = 'Aus der gewählten DD konnte kein Binding erzeugt werden.';
$messages['generator_validation_error'] = 'Bitte Modul und Datadic auswählen.';
$messages['json_invalid'] = 'Binding JSON ist ungültig.';
$messages['duplicate_bind_key'] = 'Modul + Bind Key existiert bereits.';
$messages['binding_not_found'] = 'Modul-Binding nicht gefunden.';
$messages['default_binding_title'] = 'Neues Modul-Binding';
$messages['default_binding_description'] = 'Einheitliches Binding: dbxWorkflow nutzt DD, FD, TPL und Config des Moduls.';


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

$addField('modul','varchar','Modul','parameter|min=2|max=80','text-label',array('placeholder'=>'dbxContact'));
$addField('bind_key','varchar','Bind Key','parameter|min=2|max=80','text-label',array('placeholder'=>'contact_reply'));
$addField('title','varchar','Titel','*|min=2|max=160','text-label');
$addField('description','mediumtext','Beschreibung','*|max=3000','textarea-label',array('data'=>'rows=3'));
$addField('bind_json','mediumtext','Binding JSON','*|min=2|max=30000','textarea-label',array('data'=>'rows=18'));
$addField('active','int','Aktiv','int','checkbox-label',array('default'=>'1'));

?>
