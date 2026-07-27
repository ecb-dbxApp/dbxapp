<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
$messages['bar_title'] = 'Anfrage ausblenden';
$messages['validation_error'] = 'Bitte bestätigen Sie das Ausblenden der Anfrage.';
$messages['delete_success'] = 'Die Anfrage wurde aus Ihrer Ansicht entfernt. Der Supportverlauf bleibt erhalten.';
$messages['delete_error'] = 'Die Anfrage konnte nicht ausgeblendet werden.';
$messages['back_to_requests'] = 'Zu meinen Anfragen';


$field=array();
$field['name']='confirm_delete';
$field['type']='int';
$field['length']='1';
$field['default']='0';
$field['label']='Anfrage aus meiner Ansicht entfernen';
$field['rules']='int|min=1';
$field['errormsg']='Bitte das Entfernen bestaetigen.';
$field['options']='1=Ja';
$field['tpl']='checkbox-label';
$fields[]=$field;
