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


$field['name']='server_path_order';
$field['type']='varchar';
$field['length']='128';
$field['default']='/order/';
$field['label']='server_path_order (sFTP)';
$field['rules']='*';
$field['tooltip']='Das ist der Pfad, zu dem nach dem sFTP Login, beim Labor-Web-Server auf dem FTP Server gewechselt wird. Für Anforderungen (order download).';
$field['errormsg']='';
$field['placeholder']='zB /order/';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['tpl'] ='text-label';
$fields[]=$field;


$field['name']='server_path_befund';
$field['type']='varchar';
$field['length']='128';
$field['default']='';
$field['label']='server_path_befund (sFTP)';
$field['rules']='*';
$field['tooltip']='Das ist der Pfad, zu dem nach dem sFTP Login, beim Labor-Web-Server auf dem FTP Server gewechselt wird. Für Befunde (befund upload).';
$field['errormsg']='';
$field['placeholder']='/befund/';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['tpl'] ='text-label';
$fields[]=$field;


$field['name']='path_anforderungen';
$field['type']='varchar';
$field['length']='128';
$field['default']='M:\\LABOR\\dbx\\xchange\\anforderungen\\';
$field['label']='path_anforderungen';
$field['rules']='*';
$field['tooltip']='Das ist der Pfad vom Server im Labor für anforderungen. (order download).';
$field['errormsg']='';
$field['placeholder']='zB M:\\LABOR\\dbx\\xchange\\anforderungen\\';
$field['convert']='';
$field['mask']='';
$field['data']='';
$field['tpl'] ='text-label';
$fields[]=$field;


