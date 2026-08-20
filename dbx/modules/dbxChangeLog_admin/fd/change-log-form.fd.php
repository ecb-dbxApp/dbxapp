<?php
$messages=array();
$messages['form_title_new']='Change-Log-Eintrag anlegen';
$messages['form_title_edit']='Change-Log-Eintrag bearbeiten';
$messages['form_subtitle']='Eine verständliche Beschreibung je abgeschlossenem Änderungsblock.';
$messages['action_report']='Zur Liste';
$fields=array();
foreach (array(
    array('change_date','datetime','datetime-label','Datum und Uhrzeit','datetime'),
    array('actor','varchar','text-label','Akteur','*|min=1|max=80'),
    array('summary','varchar','text-label','Änderung','*|min=3|max=255'),
    array('details','text','textarea-label','Warum','*|min=3|max=4000'),
    array('resources','text','textarea-label','Betroffene Ressourcen','*|max=8000'),
) as $definition) {
    $field=array();
    [$field['name'],$field['type'],$field['tpl'],$field['label'],$field['rules']]=$definition;
    $field['index']=''; $field['length']=''; $field['default']=''; $field['tooltip']='';
    $field['errormsg']=''; $field['placeholder']=''; $field['convert']=''; $field['protect']='0';
    $field['mask']=''; $field['data']=''; $field['options']=''; $fields[]=$field;
}
