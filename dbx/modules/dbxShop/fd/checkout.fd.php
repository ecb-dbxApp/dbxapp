<?php
$messages = array();
$messages['page_title'] = 'Kasse';
$messages['bar_title'] = 'Bestellung abschließen';
$messages['page_subtitle'] = 'Kundendaten prüfen und den Demo-Bestellablauf testen.';
$messages['empty_title'] = 'Der Warenkorb ist leer';
$messages['empty_subtitle'] = 'Erst Artikel auswählen, dann bezahlen.';
$messages['empty_message'] = 'Wählen Sie im Katalog einen Artikel und legen Sie ihn in den Warenkorb.';
$messages['login_subtitle'] = 'Anmeldung erforderlich.';
$messages['login_message'] = '<strong>Anmeldung erforderlich.</strong><br>Gastbestellungen sind aktuell deaktiviert. Bitte melden Sie sich an, bevor Sie die Bestellung abschließen.';
$messages['form_info'] = 'Bitte Kundendaten, Zahlungsart und Rechtstext-Bestätigung prüfen.';
$messages['validation_error'] = 'Bitte prüfen Sie die rot markierten Pflichtfelder.';
$messages['no_payment'] = 'Es ist keine Zahlungsart verfügbar.';
$messages['select_payment'] = 'Bitte eine verfügbare Zahlungsart wählen.';
$messages['confirm_legal'] = 'Bitte Rechtstexte und Widerrufsbelehrung bestätigen.';
$messages['legal_field_error'] = 'Bitte die Rechtstexte bestätigen.';
$messages['withdrawal_field_error'] = 'Bitte die Widerrufsbelehrung bestätigen.';
$messages['order_error'] = 'Die Bestellung konnte nicht erstellt werden.';
$messages['technical_error'] = 'Die Bestellung konnte technisch nicht verarbeitet werden.';
$messages['demo_title'] = 'Demo-Shop – kein tatsächlicher Kauf';
$messages['demo_message'] = 'Dieser Shop dient ausschließlich Demonstrations- und Testzwecken. Der vollständige Bestellablauf kann mit Testdaten durchlaufen werden; dabei wird lediglich ein technischer Testvorgang verarbeitet. Es erfolgen kein tatsächlicher Kauf, keine Zahlung und keine Lieferung. Ein Kaufvertrag kommt nicht zustande.';
$messages['payment_bank_transfer'] = 'Vorkasse / Überweisung';
$messages['payment_invoice'] = 'Rechnung';
$messages['payment_none_help'] = 'Aktuell ist keine Zahlungsart aktiv oder vollständig konfiguriert.';
$messages['payment_bank_transfer_help'] = 'Die Bestellung wird gespeichert. Der Kunde überweist danach mit Bestellnummer als Verwendungszweck.';
$messages['payment_invoice_help'] = 'Die Bestellung wird gespeichert und später per Rechnung bezahlt.';
$messages['payment_paypal_help'] = 'Sie werden zu PayPal weitergeleitet. Nach der Freigabe wird die Zahlung automatisch bestätigt.';
$messages['payment_amazon_help'] = 'Sie werden zu Amazon Pay weitergeleitet. Sandbox und Live laufen über die in den Shop-Einstellungen gespeicherten Zugangsdaten.';
$messages['bank_transfer_default'] = 'Bitte überweisen Sie den Rechnungsbetrag unter Angabe der Bestellnummer.';
$messages['invoice_default'] = 'Sie erhalten eine Rechnung. Bitte zahlen Sie innerhalb der angegebenen Frist.';
$messages['paypal_default'] = 'Die Zahlung wird über PayPal autorisiert und nach erfolgreicher Rückmeldung automatisch aktualisiert.';
$messages['amazon_default'] = 'Die Zahlung wird über Amazon Pay autorisiert und nach erfolgreicher Rückmeldung automatisch aktualisiert.';
$messages['account_owner'] = 'Kontoinhaber';
$messages['purpose'] = 'Verwendungszweck';
$messages['column_product'] = 'Artikel';
$messages['column_quantity'] = 'Menge';
$messages['column_price'] = 'Preis';
$messages['column_shipping'] = 'Versand';
$messages['column_total'] = 'Summe';
$messages['amount_due'] = 'Zahlbetrag';
$messages['order_saved_title'] = 'Bestellung gespeichert';
$messages['order_number_text'] = 'Ihre Bestellnummer lautet';
$messages['payment_method_label'] = 'Zahlungsart';
$messages['status_label'] = 'Status';
$messages['order_waiting'] = 'Die Bestellung ist im Shop-Admin sichtbar und wartet auf Bearbeitung.';
$messages['total_label'] = 'Summe';
$messages['view_orders'] = 'Bestellungen ansehen';
$messages['continue_shopping'] = 'Weiter einkaufen';
$messages['thanks_title'] = 'Danke';
$messages['saved_snapshot_subtitle'] = 'Die Bestellung wurde als Snapshot gespeichert.';
$messages['payment_note'] = 'Zahlungshinweis';

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

$add_field('customer_name','varchar','Name','*|min=2|max=180','text-label',array('placeholder'=>'Ihr Name'));
$add_field('customer_email','varchar','E-Mail','email|max=180','text-label',array('placeholder'=>'name@example.org'));
$add_field('customer_phone','varchar','Telefon','*|max=80','text-label',array('placeholder'=>'+49 30 123456'));
$add_field('shipping_address','mediumtext','Lieferadresse','*|min=8|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>"Name\nStrasse und Hausnummer\nPLZ Ort\nLand"));
$add_field('note','mediumtext','Hinweis','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Optionaler Hinweis zur Bestellung'));
$add_field('checkout_request_id','varchar','','parameter|max=64','hidden');

$cfg = array();
if (function_exists('dbx')) {
   $raw_cfg = dbx()->get_cfg('dbxShop');
   $cfg = is_array($raw_cfg) ? $raw_cfg : array();
}
$cfg_bool = function(string $key, bool $default = false) use ($cfg): bool {
   if (!array_key_exists($key, $cfg)) {
      return $default;
   }
   $value = $cfg[$key];
   if (is_bool($value)) return $value;
   return in_array(strtolower(trim((string)$value)), array('1', 'true', 'yes', 'on'), true);
};
$payment_options = array();
if ($cfg_bool('payment_bank_transfer_enabled', true)) {
   $payment_options['bank_transfer'] = 'Vorkasse / Ueberweisung';
}
if ($cfg_bool('payment_invoice_enabled', false)) {
   $payment_options['invoice'] = 'Rechnung';
}
if (
   $cfg_bool('payment_paypal_enabled', false)
   && trim((string)($cfg['payment_paypal_client_id'] ?? '')) !== ''
   && trim((string)($cfg['payment_paypal_client_secret'] ?? '')) !== ''
) {
   $payment_options['paypal'] = 'PayPal';
}
if (
   $cfg_bool('payment_amazon_pay_enabled', false)
   && trim((string)($cfg['payment_amazon_pay_merchant_id'] ?? '')) !== ''
   && trim((string)($cfg['payment_amazon_pay_store_id'] ?? '')) !== ''
   && trim((string)($cfg['payment_amazon_pay_public_key_id'] ?? '')) !== ''
   && trim((string)($cfg['payment_amazon_pay_private_key'] ?? '')) !== ''
) {
   $payment_options['amazon_pay'] = 'Amazon Pay';
}
$option_string = '';
foreach ($payment_options as $value => $label) {
   $option_string .= ($option_string !== '' ? '&' : '') . $value . '=' . $label;
}
$add_field('payment_method','varchar','Zahlungsart','parameter','select-single-label',array('options'=>$option_string,'default'=>array_key_first($payment_options) ?: ''));
$add_field('accept_legal','int','Ich habe die <a href="?dbx_modul=dbxShop&amp;dbx_run1=legal" target="_blank" rel="noopener">Rechtstexte, AGB, Zahlungs- und Versandhinweise</a> gelesen und akzeptiere sie.','int','dbxShop|shop-checkout-check');
$add_field('accept_withdrawal','int','Ich habe die <a href="?dbx_modul=dbxShop&amp;dbx_run1=withdrawal" target="_blank" rel="noopener">Widerrufsbelehrung</a> gelesen.','int','dbxShop|shop-checkout-check');
?>
