<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
$messages['bar_title'] = 'Nachricht hinzufügen';
$messages['bar_subtitle'] = 'Ihre Nachricht wird dem Support angezeigt';
$messages['form_info'] = 'Ergänzen Sie Informationen oder beantworten Sie eine Rückfrage des Supports.';
$messages['validation_error'] = 'Bitte prüfen Sie die markierte Nachricht.';
$messages['message_success'] = 'Ihre Nachricht wurde dem Ticket hinzugefügt.';
$messages['message_error'] = 'Die Nachricht konnte nicht gespeichert werden.';
$messages['ticket_closed'] = 'Dieses Ticket ist geschlossen. Für ein neues Anliegen legen Sie bitte eine neue Anfrage an.';


$field=array();
$field['name']='body';
$field['type']='mediumtext';
$field['length']='-1';
$field['default']='';
$field['label']='Ihre Nachricht';
$field['rules']='*|min=2|max=10000';
$field['errormsg']='Bitte eine Nachricht eintragen.';
$field['placeholder']='Ergänzung oder Rückfrage zu diesem Ticket';
$field['data']='rows=6';
$field['tpl']='textarea-label';
$fields[]=$field;
