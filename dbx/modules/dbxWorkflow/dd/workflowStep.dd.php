<?php

$table['server']='dbxWorkflow|dbxWorkflow.db3';
$table['table']='workflow_step';
$table['datadic']='workflowStep';
$table['primary']='id';
$table['language']='0';
$table['version']='0.1';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='1';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='instance_id ASC, step_pos ASC';
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

$add_field('instance_id','int','MUL','11','0','Instanz','int','hidden');
$add_field('step_pos','int','MUL','11','0','Position','int','text-label');
$add_field('need_key','varchar','MUL','80','','Need','parameter|max=80','text-label');
$add_field('action','varchar','MUL','40','','Aktion','parameter|max=40','text-label');
$add_field('status','varchar','MUL','24','open','Status','parameter|max=24','text-label');
$add_field('value_json','mediumtext','','-1','','Wert','*|max=50000','textarea-label',array('data'=>'rows=6'));
$add_field('message','varchar','','500','','Meldung','*|max=500','text-label');

?>
