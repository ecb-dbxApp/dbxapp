<?php

$field['name']='dayshift_enabled';
$field['type']='int';
$field['length']='1';
$field['default']='0';
$field['label']='Tagesverschiebung';
$field['rules']='int';
$field['tooltip']='Verschiebt vorhandene Dispositionsdaten um einen Arbeitstag. Für die Demo bleibt diese Funktion deaktiviert.';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='0=Aus&1=Ein';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='daily_csv_import';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='Täglicher CSV-Import';
$field['rules']='int';
$field['tooltip']='Liest die Fuhrpark-CSV beim ersten Modulaufruf eines Kalendertages neu ein.';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;

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
$field['options']='';
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
$field['data']='';
$field['options']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='autologin';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='autologin';
$field['rules']='*';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='0=Nein&1=Ja';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='praxis';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='Praxis-ID';
$field['rules']='*';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl'] ='text-label';
$fields[]=$field;



$field['name']='arzt';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='arzt';
$field['rules']='*';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='0=Ohne&1=Arzt ID=1&2=Arzt ID=2&3=Arzt ID=3';
$field['tpl'] ='select-single-label';
$fields[]=$field;


$field['name']='groups';
$field['type']='varchar';
$field['length']='256';
$field['default']='';
$field['label']='Zugriff';
$field['rules']='*';
$field['tooltip']='Benutzer Gruppe(n) mit Zugriff auf das Modul';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='sql:dbxUser_groups|name|description|active = 1|name ASC|88';
$field['tpl'] ='select-multiple-label';
$fields[]=$field;


$field['name']='medisoft';
$field['type']='varchar';
$field['length']='256';
$field['default']='medistar';
$field['label']='Praxis Software';
$field['rules']='*';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='medistar=Medistar&turbomed=TurboMed&duria2=Duria2';
$field['tpl'] ='select-single-label';
$fields[]=$field;

$field['name']='system';
$field['type']='varchar';
$field['length']='256';
$field['default']='hybrid';
$field['label']='LabConn System';
$field['rules']='*';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='hybrid=Server und Client&server=Server&client=Client&labserv=Laborserver';
$field['tpl'] ='select-single-label';
$fields[]=$field;


$field['name']='path_medisoft';
$field['type']='varchar';
$field['length']='128';
$field['default']='D:\\Medistar\\hdaten\\labdat.186';
$field['label']='path_medisoft';
$field['rules']='*';
$field['tooltip']='Gegen Sie bitte den Pfad mit Dateiname an. In diese Datei werden die Befund LDT Ergebnisse von LabConn exportiert. Für das Einlesen der Befunde in die Arzt-Software';
$field['errormsg']='';
$field['placeholder']='zB D:\\Medistar\\hdaten\\labdat.186';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl'] ='text-label';
$fields[]=$field;


$field['name']='import_pat';
$field['type']='varchar';
$field['length']='128';
$field['default']='';
$field['label']='import_pat';
$field['rules']='*';
$field['tooltip']='Gegen Sie bitte den Pfad ohne Deiname an. In diesem Verzeichnis wird vom client die pat.txt kopiert. Der Server liest aus diesem Verzeichnis die Patientendaten für eine neue Anforderung';
$field['errormsg']='';
$field['placeholder']='zB D:\\LabConn3\\pat\\';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl'] ='text-label';
$fields[]=$field;



