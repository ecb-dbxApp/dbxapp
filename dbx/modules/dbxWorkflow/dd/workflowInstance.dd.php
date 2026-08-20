<?php

$table['server']='dbxWorkflow|dbxWorkflow.db3';
$table['table']='workflow_instance';
$table['datadic']='workflowInstance';
$table['primary']='id';
$table['language']='0';
$table['version']='0.2';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='1';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='create_date DESC';
$table['read']='admin';
$table['create']='*';
$table['update']='owner,admin';
$table['delete']='admin';
$table['read_owner']='owner,admin';
$table['update_owner']='owner,admin';

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

$add_field('workflow_key','varchar','MUL','80','','Workflow','parameter|max=80','text-label');
$add_field('result_label','varchar','','160','','Ergebnis','*|max=160','text-label');
$add_field('status','varchar','MUL','24','running','Status','parameter|max=24','select-single-label',array(
   'options'=>'running=Laeuft&finishing=Wird abgeschlossen&paused=Angehalten&canceled=Abgebrochen&finished=Fertig&error=Fehler'
));
$add_field('current_need','varchar','MUL','80','','Aktueller Schritt','parameter|max=80','text-label');
$add_field('percent','int','','3','0','Fortschritt','int','text-label');
$add_field('step_percent','int','','3','0','Schritt','int','text-label');
$add_field('message','varchar','','500','','Meldung','*|max=500','text-label');
$add_field('definition_json','mediumtext','','-1','','Definition beim Start','*|max=200000','textarea-label',array(
   'data'=>'rows=10',
   'tooltip'=>'Unveraenderliche Definitionsbasis dieser Instanz. Spaetere Admin-Aenderungen gelten nur fuer neue Starts.'
));
$add_field('data_json','mediumtext','','-1','','Daten','*|max=50000','textarea-label',array('data'=>'rows=10'));

?>
