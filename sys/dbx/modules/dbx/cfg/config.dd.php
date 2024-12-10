<?php

$field['name']='version';
$field['type']='int';
$field['length']='4';
$field['default']='1';
$field['label']='Version Nr.';
$field['rules']='int';
$field['tooltip']='Gegen Sie bitte die Version Nr. an';
$field['errormsg']='Version muss eine Zahl sein';
$field['placeholder']='Version Nr';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['tpl'] ='text-label';
$fields[]=$field;

$field['name']='activ';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='Aktiv';
$field['rules']='*';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;


$field['name']='groups';
$field['type']='varchar';
$field['length']='256';
$field['default']='';
$field['label']='Zugriff';
$field['rules']='*';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='sql:dbx_user_groups|name|description|active = 1|name ASC|88';
$field['tpl'] ='select-multible-label';
$fields[]=$field;


$field['name']='default_lng';
$field['type']='varchar';
$field['length']='3';
$field['default']='de';
$field['label']='default_lng';
$field['rules']='*';
$field['tooltip']='Standart Sprache vom System. Benutzer können ihre eigene Sprache auswählen.';
$field['errormsg']='';
$field['placeholder']='de';
$field['convert']='';
$field['mask']='';
$field['data']='de=Deutsch&en=English';
$field['tpl'] ='select-single-label';
$fields[]=$field;


$field['name']='accessible_lng';
$field['type']='varchar';
$field['length']='3';
$field['default']='de';
$field['label']='accessible_lng';
$field['rules']='*';
$field['tooltip']='Verfügbare Sprachen. Benutzer können ihre eigene Sprache auswählen.';
$field['errormsg']='';
$field['placeholder']='de';
$field['convert']='';
$field['mask']='';
$field['data']='de=Deutsch&en=English';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='default_color';
$field['type']='varchar';
$field['length']='16';
$field['default']='blue';
$field['label']='default_color';
$field['rules']='*';
$field['tooltip']='Verfügbare Farb Chema.';
$field['errormsg']='';
$field['placeholder']='blue';
$field['convert']='';
$field['mask']='';
$field['data']='blue=Blau&green=Grün&red=Rot&black=Schwarz';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='default_design_user';
$field['type']='varchar';
$field['length']='16';
$field['default']='lda';
$field['label']='default_design_user';
$field['rules']='*';
$field['tooltip']='Default Design für den Benutzer';
$field['errormsg']='';
$field['placeholder']='blue';
$field['convert']='';
$field['mask']='';
$field['data']='lda=LabOrgemeinschaft&dbxapp=dbXapp&_admin=Admin&_construct=Construct&_install=Install';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='default_design_admin';
$field['type']='varchar';
$field['length']='16';
$field['default']='admin';
$field['label']='default_design_admin';
$field['rules']='*';
$field['tooltip']='Default Design für den Admin';
$field['errormsg']='';
$field['placeholder']='blue';
$field['convert']='';
$field['mask']='';
$field['data']='lda=LabOrgemeinschaft&dbxapp=dbXapp&_admin=Admin&_construct=Construct&_install=Install';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='session_db';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='session_db';
$field['rules']='*';
$field['tooltip']='Sollen die Sessions auch in der db gespeichert werrden ?';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='cache';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='cache';
$field['rules']='*';
$field['tooltip']='Sollen TPLs und CFGs gecahed werden ?';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;


$field['name']='intro';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='intro';
$field['rules']='*';
$field['tooltip']='Sollen ein Intro angezeigt werden ?';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='construct';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='construct';
$field['rules']='*';
$field['tooltip']='Befindest sie die Anwendung online in Überarbeitung ?';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;


$field['name']='install';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='install';
$field['rules']='*';
$field['tooltip']='Befindest sie die Anwendung online im Setup ?';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='crypt_cfg';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='install';
$field['rules']='*';
$field['tooltip']='Config Dateien verschlüsseln ?';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;



$field['name']='secure';
$field['type']='varchar';
$field['length']='128';
$field['default']='secure';
$field['label']='secure';
$field['rules']='*';
$field['tooltip']='Key für die Verschlüsselung';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['tpl'] ='text-label';
$fields[]=$field;


$field['name']='default_server';
$field['type']='varchar';
$field['length']='16';
$field['default']='dbxSystem';
$field['label']='default_server';
$field['rules']='*';
$field['tooltip']='Falls ein Server nicht vorhanden ist wird eine Verbindung über den default Server versucht.';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['tpl'] ='text-label';
$fields[]=$field;

$field['name']='path_sqlite_db';
$field['type']='varchar';
$field['length']='64';
$field['default']='';
$field['label']='path_sqlite_db';
$field['rules']='*';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['tpl'] ='select-single-label';
$fields[]=$field;





