<?php
$messages = array();
$messages['settings_title'] = 'Settings';
$messages['settings_subtitle'] = 'VAT, checkout, payment methods, shipping, and media';
$messages['settings_help'] = 'Help: Shop settings';
$messages['settings_payment_test'] = 'Test payment methods';
$messages['settings_save'] = 'Save';
$messages['channels_external'] = 'External channels';
$messages['channels_global_active'] = 'globally active';
$messages['channels_global_inactive'] = 'globally disabled';
$messages['channels_edit'] = 'Edit channels';
$messages['channels_disabled'] = 'Channels are globally disabled. External channels are therefore not actively used.';
$messages['channels_none'] = 'No active external channels are configured.';
$messages['column_channel'] = 'Channel';
$messages['column_platform'] = 'Platform';
$messages['column_connection'] = 'Connection';
$messages['column_export'] = 'Export';
$messages['column_import'] = 'Import';
$messages['column_test'] = 'Test';
$messages['export_off'] = 'Export off';
$messages['import_off'] = 'Import off';
$messages['not_tested'] = 'not tested';

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

$add_field('enabled','int','Shop active','int',$check,array('data'=>array('hint'=>'Basically unlocks the shop functions.')));
$add_field('demo_notice_enabled','int','Show demo shop notice','int',$check,array('data'=>array('hint'=>'Shows the multilingual “Demo shop – no actual purchase” notice in the catalog and checkout.')));
$add_field('default_channel','varchar','Standard channel','parameter|max=40','text-label',array('placeholder'=>'Shop','tooltip'=>'Standard technical sales channel for normal shop orders.'));
$add_field('default_currency','varchar','Default currency','parameter|max=3','text-label',array('placeholder'=>'EUR','tooltip'=>'ISO 3 letter warranty code.'));
$add_field('price_display','varchar','Price display','parameter|max=12','select-single-label',array('options'=>'gross=Gross&net=Net'));
$add_field('tax_display_enabled','int','VAT','int','select-single-label',array('options'=>'1=Specify VAT&0=VAT Not to be reported','tooltip'=>'Controls whether the VAT indication is displayed at prices.'));
$add_field('b2b_mode','int','B2B mode','int',$check,array('data'=>array('hint'=>'Prepared for net/business customer logic.')));
$add_field('stock_enabled','int','Use stock','int',$check,array('data'=>array('hint'=>'If active, the stock can later be used for saleability and indications.')));
$add_field('channels_enabled','int','Using Channels','int',$check,array('data'=>array('hint'=>'Displays channel functions for eBay, Amazon, classified ads, mobile.de and other platforms. Switch off for pure shop installations.')));

$add_field('default_tax_class','varchar','Standard VAT. Class','parameter|max=20','select-single-label',array('options'=>'mwst1=mwst1&mwst2=mwst2&mwst3=mwst3'));
$add_field('tax_title_mwst1','varchar','Name','*|max=80','text-label',array('placeholder'=>'Normal value added tax'));
$add_field('tax_rate_mwst1','decimal','Percent','decimal','text-label',array('placeholder'=>'19.00'));
$add_field('tax_title_mwst2','varchar','Name','*|max=80','text-label',array('placeholder'=>'VAT Determined'));
$add_field('tax_rate_mwst2','decimal','Percent','decimal','text-label',array('placeholder'=>'7.00'));
$add_field('tax_title_mwst3','varchar','Name','*|max=80','text-label',array('placeholder'=>'VAT prepared'));
$add_field('tax_rate_mwst3','decimal','Percent','decimal','text-label',array('placeholder'=>'22.00'));

$add_field('checkout_guest_allowed','int','Allow guest orders','int',$check,array('data'=>array('hint'=>'Allows orders without prior customer account.')));
$add_field('legal_snapshot_enabled','int','Save legal text snapshot','int',$check,array('data'=>array('hint'=>'Saves legal texts and cancellation policy at the time of purchase.')));
$add_field('withdrawal_button_enabled','int','Show revocation','int',$check,array('data'=>array('hint'=>'Displays the cancellation area in the shop. The content comes from the CMS page /shop revocation.')));
$add_field('mail_customer_enabled','int','Send customer mail','int',$check,array('data'=>array('hint'=>'Sends after order and revocation a confirmation to the customer.')));
$add_field('mail_admin_enabled','int','Send admin mail','int',$check,array('data'=>array('hint'=>'Sends an internal notification when ordering and revoking.')));
$add_field('mail_from','varchar','Mail senders','email|max=180','text-label',array('placeholder'=>'shop@example.org'));
$add_field('mail_admin_to','varchar','Admin email','email|max=180','text-label',array('placeholder'=>'admin@example.org'));

$add_field('payment_bank_transfer_enabled','int','Prepayment actively','int',$check,array('data'=>array('hint'=>'Activates prepayment by bank transfer as a checkout payment method.')));
$add_field('payment_bank_transfer_account_owner','varchar','Account holders','*|max=160','text-label',array('placeholder'=>'Muster GmbH'));
$add_field('payment_bank_transfer_iban','varchar','IBAN','*|max=60','text-label',array('placeholder'=>'DE02120300000000202051'));
$add_field('payment_bank_transfer_bic','varchar','BIC','*|max=40','text-label',array('placeholder'=>'BYLADEM1001'));
$add_field('payment_bank_transfer_bank_name','varchar','Bank','*|max=120','text-label',array('placeholder'=>'Model bank'));
$add_field('payment_bank_transfer_instructions','mediumtext','Prepayment notice','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Please transfer the invoice amount by specifying the order number.'));
$add_field('payment_invoice_enabled','int','Invoice active','int',$check,array('data'=>array('hint'=>'Activates invoice as a checkout payment method. Useful especially for B2B or released customers.')));
$add_field('payment_invoice_instructions','mediumtext','Invoice note','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'You will receive an invoice. Please pay within the specified period.'));
$add_field('payment_paypal_enabled','int','PayPal active','int',$check,array('data'=>array('hint'=>'PayPal only appears in the checkout when client ID and secret are available.')));
$add_field('payment_paypal_mode','varchar','PayPal mode','parameter|max=12','select-single-label',array('options'=>'sandbox=Sandbox&live=Live'));
$add_field('payment_paypal_brand_name','varchar','PayPal Brand Name','*|max=120','text-label',array('placeholder'=>'dbXapp'));
$add_field('payment_paypal_client_id','varchar','PayPal Client ID','*|max=255','text-label',array('placeholder'=>'Axxxxxxxxxxxxxxxx'));
$add_field('payment_paypal_client_secret','varchar','PayPal Client Secret','*|max=255','text-label',array('placeholder'=>'Exxxxxxxx_secret_xxxx'));
$add_field('payment_amazon_pay_enabled','int','Amazon Pay active','int',$check,array('data'=>array('hint'=>'Activates Amazon Pay as a checkout payment method. The real API authorization will be connected later.')));
$add_field('payment_amazon_pay_mode','varchar','Amazon Pay mode','parameter|max=12','select-single-label',array('options'=>'sandbox=Sandbox&live=Live'));
$add_field('payment_amazon_pay_region','varchar','Amazon Pay Region','parameter|max=12','select-single-label',array('options'=>'EU=EU&UK=UK&US=US'));
$add_field('payment_amazon_pay_merchant_id','varchar','Amazon Merchant ID','*|max=160','text-label',array('placeholder'=>'A1BCD2EFGH3IJK'));
$add_field('payment_amazon_pay_store_id','varchar','Amazon Store ID','*|max=160','text-label',array('placeholder'=>'amzn1.application-oa2-client.xxxxx'));
$add_field('payment_amazon_pay_public_key_id','varchar','Public key ID','*|max=160','text-label',array('placeholder'=>'SANDBOX-AEUPKxxxx'));
$add_field('payment_amazon_pay_private_key','mediumtext','Private Key','*|max=6000','textarea-label',array('data'=>'rows=6','placeholder'=>"--------------------------------------------------------"));
$add_field('payment_amazon_pay_sandbox_simulation_code','varchar','Sandbox simulation','parameter|max=80','text-label',array('placeholder'=>'AmazonCanceled'));

$add_field('delivery_digital_download_enabled','int','Active digital downloads','int',$check,array('data'=>array('hint'=>'Allows digital provision for suitable products.')));
$add_field('delivery_flat_shipping_enabled','int','Active package shipping','int',$check,array('data'=>array('hint'=>'Activates global shipping costs as fallback.')));
$add_field('delivery_flat_shipping_gross_price','decimal','Gross flat-rate shipment','decimal','text-label',array('placeholder'=>'5.90'));
$add_field('media_usage_slot','varchar','CMS media slot','parameter|max=40','text-label',array('placeholder'=>'shop'));
?>
