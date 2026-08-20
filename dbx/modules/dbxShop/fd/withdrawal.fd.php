<?php
$messages = array();
$messages['page_title'] = 'Widerruf';
$messages['page_subtitle'] = 'Widerrufsbelehrung lesen und den Widerruf direkt senden.';
$messages['empty_content'] = 'Die CMS-Seite ist leer.';
$messages['bar_title'] = 'Widerruf senden';
$messages['bar_subtitle'] = 'Bestellung eindeutig zuordnen';
$messages['form_info'] = 'Bitte Bestellnummer und Kontaktdaten eintragen, damit der Widerruf zugeordnet werden kann.';
$messages['validation_error'] = 'Bitte prüfen Sie die rot markierten Pflichtfelder.';
$messages['withdrawal_success'] = 'Ihr Widerruf wurde gespeichert. Wir prüfen die Zuordnung zur Bestellung.';
$messages['withdrawal_error'] = 'Der Widerruf konnte nicht gespeichert werden.';

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

$add_field('order_no','varchar','Bestellnummer','parameter|max=40','text-label',array('placeholder'=>'S20260710123456-1234'));
$add_field('customer_name','varchar','Name','*|min=2|max=180','text-label',array('placeholder'=>'Ihr Name'));
$add_field('customer_email','varchar','E-Mail','email|max=180','text-label',array('placeholder'=>'name@example.org'));
$add_field('customer_address','mediumtext','Adresse','*|min=8|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>"Name\nStraße und Hausnummer\nPLZ Ort\nLand"));
$add_field('reason','mediumtext','Nachricht','*|max=3000','textarea-label',array('data'=>'rows=5','placeholder'=>'Hiermit widerrufe ich meine Bestellung. Optional: betroffene Artikel oder Rückfrage.'));
?>
