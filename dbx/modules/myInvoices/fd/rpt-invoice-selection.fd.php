<?php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
$messages['filter_error'] = 'Bitte Filtereingaben prüfen.';
$messages['report_title'] = 'Rechnungsbericht';
$messages['column_invoice_no'] = 'Rechnung';
$messages['column_invoice_date'] = 'Datum';
$messages['column_customer'] = 'Kunde';
$messages['column_status'] = 'Status';
$messages['column_action'] = 'Aktion';
$messages['column_total'] = 'Summe';
$messages['column_position_no'] = 'Pos.';
$messages['column_article_no'] = 'Artikelnummer';
$messages['column_article'] = 'Artikel';
$messages['column_quantity'] = 'Menge';
$messages['column_unit_price'] = 'Einzelpreis';
$messages['status_draft'] = 'Entwurf';
$messages['status_open'] = 'Offen';
$messages['status_paid'] = 'Bezahlt';
$messages['delete_title'] = 'Rechnung löschen';
$messages['delete_question'] = 'Rechnung #{id} samt Positionen löschen?';
$messages['delete_hint'] = 'Kopf und Positionen werden gemeinsam gelöscht.';
$messages['delete_invalid'] = 'Die Rechnung konnte nicht bestimmt werden.';
$messages['transaction_error'] = 'Transaktion konnte nicht starten.';
$messages['delete_error'] = 'Rechnung konnte nicht vollständig gelöscht werden.';
$messages['delete_success'] = 'Rechnung und Positionen wurden gelöscht.';

/**
 * Filter- und Sortierfelder des Rechnungsreports.
 */

$fields = array();

$field = array();
$field['name'] = 'dbx_rrows';
$field['type'] = 'int';
$field['tpl'] = 'select-single-label';
$field['default'] = '20';
$field['label'] = 'Pro Seite';
$field['rules'] = 'int';
$field['options'] = '10=10&20=20&30=30&50=50';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rsort';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'invoice_date';
$field['label'] = 'Sortierung';
$field['rules'] = 'parameter';
$field['options'] =
    'invoice_no=Rechnungsnummer&invoice_date=Datum'
    . '&customer=Kunde&status=Status&total_gross=Summe';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rdesc';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'DESC';
$field['label'] = 'Richtung';
$field['rules'] = 'parameter';
$field['options'] = 'ASC=Aufsteigend&DESC=Absteigend';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rstatus';
$field['type'] = 'varchar';
$field['tpl'] = 'select-single-label';
$field['default'] = 'all';
$field['label'] = 'Status';
$field['rules'] = 'parameter|max=24';
$field['options'] = 'all=Alle&draft=Entwurf&open=Offen&paid=Bezahlt';
$fields[] = $field;

$field = array();
$field['name'] = 'dbx_rwhere';
$field['type'] = 'varchar';
$field['tpl'] = 'dbx|search';
$field['default'] = '';
$field['label'] = 'Suchen';
$field['rules'] = 'sqlsearch|max=64';
$fields[] = $field;

?>
