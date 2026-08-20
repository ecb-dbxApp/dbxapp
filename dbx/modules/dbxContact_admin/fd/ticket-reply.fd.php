<?php
$messages = array();
$messages['bar_title'] = 'Antwort oder interne Notiz';
$messages['bar_subtitle'] = 'Der gesamte Verlauf bleibt am Ticket erhalten';
$messages['form_info'] = 'Öffentliche Antworten können per E-Mail versendet werden. Interne Notizen bleiben nur in der Administration sichtbar.';
$messages['validation_error'] = 'Bitte prüfen Sie die markierten Eingaben.';
$messages['mail_warning'] = 'Nachricht und Status wurden gespeichert, die E-Mail konnte jedoch nicht versendet werden.';
$messages['internal_success'] = 'Interne Notiz und Status wurden gespeichert.';
$messages['reply_success'] = 'Antwort und Status wurden gespeichert.';
$messages['mail_success_suffix'] = ' Die E-Mail wurde versendet.';
$messages['message_error'] = 'Die Nachricht konnte nicht gespeichert werden.';


$field=array();
$field['name']='status';
$field['type']='varchar';
$field['length']='24';
$field['default']='answered';
$field['label']='Neuer Status';
$field['rules']='parameter|max=24';
$field['options']='open=Offen&in_progress=In Bearbeitung&waiting_customer=Rückfrage&answered=Beantwortet&closed=Geschlossen';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='priority';
$field['type']='varchar';
$field['length']='16';
$field['default']='normal';
$field['label']='Prioritaet';
$field['rules']='parameter|max=16';
$field['options']='low=Niedrig&normal=Normal&high=Hoch&urgent=Dringend';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='visibility';
$field['type']='varchar';
$field['length']='16';
$field['default']='public';
$field['label']='Nachrichtentyp';
$field['rules']='parameter|max=16';
$field['options']='public=Antwort an Anfragenden&internal=Interne Notiz';
$field['tpl']='select-single-label';
$fields[]=$field;

$field=array();
$field['name']='body';
$field['type']='mediumtext';
$field['length']='-1';
$field['default']='';
$field['label']='Antwort oder Notiz';
$field['rules']='*|min=2|max=10000';
$field['errormsg']='Bitte eine Nachricht eintragen.';
$field['placeholder']='Antwort an den Anfragenden oder interne Notiz';
$field['data']='rows=9';
$field['tpl']='textarea-label';
$fields[]=$field;

$field=array();
$field['name']='send_mail';
$field['type']='int';
$field['length']='1';
$field['default']='1';
$field['label']='Antwort als E-Mail senden';
$field['rules']='int';
$field['options']='1=Ja';
$field['tpl']='checkbox-label';
$fields[]=$field;
