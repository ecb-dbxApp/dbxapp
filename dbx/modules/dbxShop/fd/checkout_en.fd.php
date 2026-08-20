<?php
$messages = array();
$messages['page_title'] = 'Checkout';
$messages['bar_title'] = 'Complete order';
$messages['page_subtitle'] = 'Review the customer details and test the demo order process.';
$messages['empty_title'] = 'Your cart is empty';
$messages['empty_subtitle'] = 'Select items before proceeding to checkout.';
$messages['empty_message'] = 'Select an item in the catalog and add it to your cart.';
$messages['login_subtitle'] = 'Sign-in required.';
$messages['login_message'] = '<strong>Sign-in required.</strong><br>Guest orders are currently disabled. Please sign in before completing the order.';
$messages['form_info'] = 'Review the customer details, payment method and acceptance of the legal texts.';
$messages['validation_error'] = 'Please check the highlighted required fields.';
$messages['no_payment'] = 'No payment method is available.';
$messages['select_payment'] = 'Select an available payment method.';
$messages['confirm_legal'] = 'Please accept the legal texts and acknowledge the withdrawal policy.';
$messages['legal_field_error'] = 'Please accept the legal texts.';
$messages['withdrawal_field_error'] = 'Please acknowledge the withdrawal policy.';
$messages['order_error'] = 'The order could not be created.';
$messages['technical_error'] = 'The order could not be processed due to a technical error.';
$messages['demo_title'] = 'Demo shop – no actual purchase';
$messages['demo_message'] = 'This shop is provided solely for demonstration and testing. You can complete the entire order process with test data; only a technical test transaction is processed. No actual purchase, payment or delivery takes place, and no purchase contract is formed.';
$messages['payment_bank_transfer'] = 'Prepayment / bank transfer';
$messages['payment_invoice'] = 'Invoice';
$messages['payment_none_help'] = 'No payment method is currently active or fully configured.';
$messages['payment_bank_transfer_help'] = 'The order is saved. The customer then transfers the amount using the order number as the payment reference.';
$messages['payment_invoice_help'] = 'The order is saved and paid later by invoice.';
$messages['payment_paypal_help'] = 'You will be redirected to PayPal. The payment is updated automatically after approval.';
$messages['payment_amazon_help'] = 'You will be redirected to Amazon Pay. Sandbox and live mode use the credentials saved in the shop settings.';
$messages['bank_transfer_default'] = 'Please transfer the invoice amount and include the order number.';
$messages['invoice_default'] = 'You will receive an invoice. Please pay within the stated period.';
$messages['paypal_default'] = 'The payment is authorized through PayPal and updated automatically after a successful response.';
$messages['amazon_default'] = 'The payment is authorized through Amazon Pay and updated automatically after a successful response.';
$messages['account_owner'] = 'Account holder';
$messages['purpose'] = 'Payment reference';
$messages['column_product'] = 'Product';
$messages['column_quantity'] = 'Quantity';
$messages['column_price'] = 'Price';
$messages['column_shipping'] = 'Shipping';
$messages['column_total'] = 'Total';
$messages['amount_due'] = 'Amount due';
$messages['order_saved_title'] = 'Order saved';
$messages['order_number_text'] = 'Your order number is';
$messages['payment_method_label'] = 'Payment method';
$messages['status_label'] = 'Status';
$messages['order_waiting'] = 'The order is visible in shop administration and is awaiting processing.';
$messages['total_label'] = 'Total';
$messages['view_orders'] = 'View orders';
$messages['continue_shopping'] = 'Continue shopping';
$messages['thanks_title'] = 'Thank you';
$messages['saved_snapshot_subtitle'] = 'The order was saved as a snapshot.';
$messages['payment_note'] = 'Payment information';

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

$add_field('customer_name','varchar','Name','*|min=2|max=180','text-label',array('placeholder'=>'Your name'));
$add_field('customer_email','varchar','E-mail','email|max=180','text-label',array('placeholder'=>'name@example.org'));
$add_field('customer_phone','varchar','Telephone','*|max=80','text-label',array('placeholder'=>'+49 30 123456'));
$add_field('shipping_address','mediumtext','Shipping address','*|min=8|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>"Name\nStreet and house number\nPostal code and city\nCountry"));
$add_field('note','mediumtext','Note','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Optional note on ordering'));
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
   $payment_options['bank_transfer'] = 'Prepayment / bank transfer';
}
if ($cfg_bool('payment_invoice_enabled', false)) {
   $payment_options['invoice'] = 'Invoice';
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
$add_field('payment_method','varchar','Payment method','parameter','select-single-label',array('options'=>$option_string,'default'=>array_key_first($payment_options) ?: ''));
$add_field('accept_legal','int','I have read and accept the <a href="?dbx_modul=dbxShop&amp;dbx_run1=legal" target="_blank" rel="noopener">legal texts, terms and conditions, and payment and shipping information</a>.','int','dbxShop|shop-checkout-check');
$add_field('accept_withdrawal','int','I have read the <a href="?dbx_modul=dbxShop&amp;dbx_run1=withdrawal" target="_blank" rel="noopener">withdrawal policy</a>.','int','dbxShop|shop-checkout-check');
?>
