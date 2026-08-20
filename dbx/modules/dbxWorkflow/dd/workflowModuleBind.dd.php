<?php

$table['server']='dbxWorkflow|dbxWorkflow.db3';
$table['table']='workflow_module_bind';
$table['datadic']='workflowModuleBind';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='1';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='modul ASC, bind_key ASC';
$table['read']='admin';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';

$add_field = function($name, $type, $index, $length, $default, $label, $rules, $tpl, $extra = array()) use (&$fields) {
   $field=array();
   $field['name']=$name;
   $field['type']=$type;
   $field['index']=$index;
   $field['length']=$length;
   $field['default']=$default;
   $field['label']=$label;
   $field['rules']=$rules;
   $field['tooltip']=$extra['tooltip'] ?? '';
   $field['errormsg']=$extra['errormsg'] ?? '';
   $field['placeholder']=$extra['placeholder'] ?? '';
   $field['convert']=$extra['convert'] ?? '';
   $field['protect']=$extra['protect'] ?? '0';
   $field['group']=$extra['group'] ?? '';
   $field['mask']=$extra['mask'] ?? '';
   $field['data']=$extra['data'] ?? '';
   $field['options']=$extra['options'] ?? '';
   $field['tpl']=$tpl;
   $field['js']=$extra['js'] ?? '';
   $field['prompt']=$extra['prompt'] ?? '';
   $fields[]=$field;
};

$add_field('id','int','PRI','11','','ID','int','hidden');
$add_field('create_date','datetime','','-1','','Erstellt','datetime','hidden',array('convert'=>'date_time'));
$add_field('create_uid','int','MUL','11','0','Erstellt von','int','hidden');
$add_field('update_date','datetime','','-1','','Aktualisiert','datetime','hidden',array('convert'=>'date_time'));
$add_field('update_uid','int','MUL','11','0','Aktualisiert von','int','hidden');
$add_field('owner','int','MUL','11','0','Owner','int','hidden');
$add_field('trash','int','MUL','1','0','Trash','int','hidden');

$add_field('modul','varchar','MUL','80','','Modul','parameter|min=2|max=80','text-label',array(
   'placeholder'=>'dbxContact',
   'tooltip'=>'Modulname ohne Workflow-Bezug. dbxWorkflow nutzt nur DD/FD/TPL/Config des Moduls.'
));
$add_field('bind_key','varchar','MUL','80','','Bind Key','parameter|min=2|max=80','text-label',array(
   'placeholder'=>'contact_reply',
   'tooltip'=>'Technischer Schluessel, Referenz in Workflow-Definitionen als bind_ref.'
));
$add_field('title','varchar','MUL','160','','Titel','*|min=2|max=160','text-label');
$add_field('description','mediumtext','','-1','','Beschreibung','*|max=3000','textarea-label',array('data'=>'rows=3'));
$add_field('bind_json','mediumtext','','-1','','Binding JSON','*|min=2|max=30000','textarea-label',array(
   'data'=>'rows=18',
   'tooltip'=>'Einheitliches Binding-Schema: record, context, needs, finish.'
));
$add_field('active','int','MUL','1','1','Aktiv','int','checkbox-label');

?>
