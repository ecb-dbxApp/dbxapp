<?php

$table['server']='dbxWorkflow|dbxWorkflow.db3';
$table['table']='workflow_definition';
$table['datadic']='workflowDefinition';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='1';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='title ASC';
$table['read']='admin';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';

$addField = function($name, $type, $index, $length, $default, $label, $rules, $tpl, $extra = array()) use (&$fields) {
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

$addField('id','int','PRI','11','','ID','int','hidden');
$addField('create_date','datetime','','-1','','Erstellt','datetime','hidden',array('convert'=>'date_time'));
$addField('create_uid','int','MUL','11','0','Erstellt von','int','hidden');
$addField('update_date','datetime','','-1','','Aktualisiert','datetime','hidden',array('convert'=>'date_time'));
$addField('update_uid','int','MUL','11','0','Aktualisiert von','int','hidden');
$addField('owner','int','MUL','11','0','Owner','int','hidden');
$addField('trash','int','MUL','1','0','Trash','int','hidden');

$addField('workflow_key','varchar','UNI','80','','Workflow Key','parameter|min=2|max=80','text-label',array(
   'placeholder'=>'invoice_demo',
   'tooltip'=>'Eindeutiger technischer Name des Workflows.'
));
$addField('title','varchar','MUL','160','','Name','*|min=2|max=160','text-label',array(
   'placeholder'=>'Artikel bei eBay veroeffentlichen'
));
$addField('result_label','varchar','','160','','Ziel','*|min=2|max=160','text-label',array(
   'placeholder'=>'Bestehender Artikel ist bei eBay veroeffentlicht'
));
$addField('description','mediumtext','','-1','','Beschreibung','*|max=3000','textarea-label',array(
   'data'=>'rows=4',
   'placeholder'=>'Worum geht es und wann ist das Ziel erreicht?'
));
$addField('definition_json','mediumtext','','-1','','Definition','*|min=2|max=20000','textarea-label',array(
   'data'=>'rows=14',
   'placeholder'=>'JSON-Definition oder Need-Zeilen'
));
$addField('active','int','MUL','1','1','Aktiv','int','checkbox-label');

?>
