<?php
$messages = array();
$messages['settings_title'] = 'Einstellungen';
$messages['settings_subtitle'] = 'MwSt., Checkout, Zahlungsarten, Versand und Medien';
$messages['settings_help'] = 'Hilfe: Shop-Einstellungen';
$messages['settings_payment_test'] = 'Zahlungsarten testen';
$messages['settings_save'] = 'Speichern';
$messages['channels_external'] = 'Externe Channels';
$messages['channels_global_active'] = 'global aktiv';
$messages['channels_global_inactive'] = 'global deaktiviert';
$messages['channels_edit'] = 'Channels bearbeiten';
$messages['channels_disabled'] = 'Channels sind global ausgeschaltet. Externe Channels werden dadurch nicht aktiv genutzt.';
$messages['channels_none'] = 'Keine aktiven externen Channels eingerichtet.';
$messages['column_channel'] = 'Channel';
$messages['column_platform'] = 'Plattform';
$messages['column_connection'] = 'Verbindung';
$messages['column_export'] = 'Export';
$messages['column_import'] = 'Import';
$messages['column_test'] = 'Test';
$messages['export_off'] = 'Export aus';
$messages['import_off'] = 'Import aus';
$messages['not_tested'] = 'nicht getestet';

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

$check = 'dbxShop_admin|shop-settings-check';

$add_field('enabled','int','Shop aktiv','int',$check,array('data'=>array('hint'=>'Schaltet die Shop-Funktionen grundsaetzlich frei.')));
$add_field('demo_notice_enabled','int','Demo-Shop-Hinweis anzeigen','int',$check,array('data'=>array('hint'=>'Blendet den mehrsprachigen Hinweisblock „Demo-Shop – kein tatsächlicher Kauf“ im Katalog und Checkout ein.')));
$add_field('default_channel','varchar','Standard-Channel','parameter|max=40','text-label',array('placeholder'=>'shop','tooltip'=>'Technischer Standard-Verkaufskanal fuer normale Shop-Bestellungen.'));
$add_field('default_currency','varchar','Standard-Waehrung','parameter|max=3','text-label',array('placeholder'=>'EUR','tooltip'=>'ISO-Waehrungscode mit drei Buchstaben.'));
$add_field('price_display','varchar','Preisanzeige','parameter|max=12','select-single-label',array('options'=>'gross=Brutto&net=Netto'));
$add_field('tax_display_enabled','int','MwSt.','int','select-single-label',array('options'=>'1=MwSt. ausweisen&0=MwSt. nicht ausweisen','tooltip'=>'Steuert, ob der MwSt.-Hinweis bei Preisen angezeigt wird.'));
$add_field('b2b_mode','int','B2B-Modus','int',$check,array('data'=>array('hint'=>'Vorbereitet fuer Netto-/Geschaeftskundenlogik.')));
$add_field('stock_enabled','int','Lagerbestand nutzen','int',$check,array('data'=>array('hint'=>'Wenn aktiv, kann der Lagerbestand spaeter fuer Verkaufbarkeit und Hinweise genutzt werden.')));
$add_field('channels_enabled','int','Channels nutzen','int',$check,array('data'=>array('hint'=>'Blendet Channel-Funktionen fuer eBay, Amazon, Kleinanzeigen, mobile.de und weitere Plattformen ein. Ausschalten fuer reine Shop-Installationen.')));

$add_field('default_tax_class','varchar','Standard-MwSt.-Klasse','parameter|max=20','select-single-label',array('options'=>'mwst1=mwst1&mwst2=mwst2&mwst3=mwst3'));
$add_field('tax_title_mwst1','varchar','Name','*|max=80','text-label',array('placeholder'=>'MwSt. normal'));
$add_field('tax_rate_mwst1','decimal','Prozent','decimal','text-label',array('placeholder'=>'19.00'));
$add_field('tax_title_mwst2','varchar','Name','*|max=80','text-label',array('placeholder'=>'MwSt. ermaessigt'));
$add_field('tax_rate_mwst2','decimal','Prozent','decimal','text-label',array('placeholder'=>'7.00'));
$add_field('tax_title_mwst3','varchar','Name','*|max=80','text-label',array('placeholder'=>'MwSt. vorbereitet'));
$add_field('tax_rate_mwst3','decimal','Prozent','decimal','text-label',array('placeholder'=>'22.00'));

$add_field('checkout_guest_allowed','int','Gastbestellung erlauben','int',$check,array('data'=>array('hint'=>'Erlaubt Bestellungen ohne vorheriges Kundenkonto.')));
$add_field('legal_snapshot_enabled','int','Rechtstext-Snapshot speichern','int',$check,array('data'=>array('hint'=>'Speichert Rechtstexte und Widerrufsbelehrung zum Kaufzeitpunkt.')));
$add_field('withdrawal_button_enabled','int','Widerruf anzeigen','int',$check,array('data'=>array('hint'=>'Blendet den Widerrufsbereich im Shop ein. Der Inhalt kommt aus der CMS-Seite /shop-widerruf.')));
$add_field('mail_customer_enabled','int','Kunden-Mail senden','int',$check,array('data'=>array('hint'=>'Sendet nach Bestellung und Widerruf eine Bestaetigung an den Kunden.')));
$add_field('mail_admin_enabled','int','Admin-Mail senden','int',$check,array('data'=>array('hint'=>'Sendet bei Bestellung und Widerruf eine interne Benachrichtigung.')));
$add_field('mail_from','varchar','Shop-Absender','email|max=180','text-label',array(
   'placeholder'=>'shop@example.org',
   'data'=>array('hint'=>'Eigene From-Adresse für Bestellungen, Statusmeldungen und Widerrufe.'),
));
$add_field('mail_admin_to','varchar','Admin-E-Mail','email|max=180','text-label',array('placeholder'=>'admin@example.org'));

$add_field('payment_bank_transfer_enabled','int','Vorkasse aktiv','int',$check,array('data'=>array('hint'=>'Aktiviert Vorkasse per Bankueberweisung als Checkout-Zahlungsart.')));
$add_field('payment_bank_transfer_account_owner','varchar','Kontoinhaber','*|max=160','text-label',array('placeholder'=>'Muster GmbH'));
$add_field('payment_bank_transfer_iban','varchar','IBAN','*|max=60','text-label',array('placeholder'=>'DE02120300000000202051'));
$add_field('payment_bank_transfer_bic','varchar','BIC','*|max=40','text-label',array('placeholder'=>'BYLADEM1001'));
$add_field('payment_bank_transfer_bank_name','varchar','Bank','*|max=120','text-label',array('placeholder'=>'Musterbank'));
$add_field('payment_bank_transfer_instructions','mediumtext','Vorkasse-Hinweis','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Bitte ueberweisen Sie den Rechnungsbetrag unter Angabe der Bestellnummer.'));
$add_field('payment_invoice_enabled','int','Rechnung aktiv','int',$check,array('data'=>array('hint'=>'Aktiviert Rechnung als Checkout-Zahlungsart. Sinnvoll vor allem fuer B2B oder freigegebene Kunden.')));
$add_field('payment_invoice_instructions','mediumtext','Rechnungs-Hinweis','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Sie erhalten eine Rechnung. Bitte zahlen Sie innerhalb der angegebenen Frist.'));
$add_field('payment_paypal_enabled','int','PayPal aktiv','int',$check,array('data'=>array('hint'=>'PayPal erscheint erst im Checkout, wenn Client-ID und Secret vorhanden sind.')));
$add_field('payment_paypal_mode','varchar','PayPal Modus','parameter|max=12','select-single-label',array('options'=>'sandbox=Sandbox&live=Live'));
$add_field('payment_paypal_brand_name','varchar','PayPal Brand Name','*|max=120','text-label',array('placeholder'=>'dbXapp'));
$add_field('payment_paypal_client_id','varchar','PayPal Client-ID','*|max=255','text-label',array('placeholder'=>'Axxxxxxxxxxxxxxxx'));
$add_field('payment_paypal_client_secret','varchar','PayPal Client-Secret','*|max=255','text-label',array('placeholder'=>'Exxxxxxxx_secret_xxxx'));
$add_field('payment_amazon_pay_enabled','int','Amazon Pay aktiv','int',$check,array('data'=>array('hint'=>'Aktiviert Amazon Pay als Checkout-Zahlungsart. Die echte API-Autorisierung wird spaeter angebunden.')));
$add_field('payment_amazon_pay_mode','varchar','Amazon Pay Modus','parameter|max=12','select-single-label',array('options'=>'sandbox=Sandbox&live=Live'));
$add_field('payment_amazon_pay_region','varchar','Amazon Pay Region','parameter|max=12','select-single-label',array('options'=>'EU=EU&UK=UK&US=US'));
$add_field('payment_amazon_pay_merchant_id','varchar','Amazon Merchant-ID','*|max=160','text-label',array('placeholder'=>'A1BCD2EFGH3IJK'));
$add_field('payment_amazon_pay_store_id','varchar','Amazon Store-ID','*|max=160','text-label',array('placeholder'=>'amzn1.application-oa2-client.xxxxx'));
$add_field('payment_amazon_pay_public_key_id','varchar','Public-Key-ID','*|max=160','text-label',array('placeholder'=>'SANDBOX-AEUPKxxxx'));
$add_field('payment_amazon_pay_private_key','mediumtext','Private Key','*|max=6000','textarea-label',array('data'=>'rows=6','placeholder'=>"-----BEGIN PRIVATE KEY-----\nkeyxxxxx\n-----END PRIVATE KEY-----"));
$add_field('payment_amazon_pay_sandbox_simulation_code','varchar','Sandbox Simulation','parameter|max=80','text-label',array('placeholder'=>'AmazonCanceled'));

$add_field('delivery_digital_download_enabled','int','Digitale Downloads aktiv','int',$check,array('data'=>array('hint'=>'Erlaubt digitale Bereitstellung fuer passende Produkte.')));
$add_field('delivery_flat_shipping_enabled','int','Pauschalversand aktiv','int',$check,array('data'=>array('hint'=>'Aktiviert globale Versandkosten als Fallback.')));
$add_field('delivery_flat_shipping_gross_price','decimal','Pauschalversand brutto','decimal','text-label',array('placeholder'=>'5.90'));
$add_field('media_usage_slot','varchar','CMS-Media Slot','parameter|max=40','text-label',array('placeholder'=>'shop'));
?>
