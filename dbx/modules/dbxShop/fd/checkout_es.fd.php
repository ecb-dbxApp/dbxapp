<?php
$messages = array();
$messages['page_title'] = 'Finalizar compra';
$messages['bar_title'] = 'Completar pedido';
$messages['page_subtitle'] = 'Revise los datos del cliente y pruebe el proceso de pedido de demostración.';
$messages['empty_title'] = 'El carrito está vacío';
$messages['empty_subtitle'] = 'Seleccione artículos antes de finalizar la compra.';
$messages['empty_message'] = 'Seleccione un artículo del catálogo y añádalo al carrito.';
$messages['login_subtitle'] = 'Inicio de sesión obligatorio.';
$messages['login_message'] = '<strong>Inicio de sesión obligatorio.</strong><br>Los pedidos como invitado están desactivados. Inicie sesión antes de completar el pedido.';
$messages['form_info'] = 'Revise los datos del cliente, el método de pago y la aceptación de los textos legales.';
$messages['validation_error'] = 'Revise los campos obligatorios resaltados.';
$messages['no_payment'] = 'No hay ningún método de pago disponible.';
$messages['select_payment'] = 'Seleccione un método de pago disponible.';
$messages['confirm_legal'] = 'Acepte los textos legales y confirme que ha leído la política de desistimiento.';
$messages['legal_field_error'] = 'Acepte los textos legales.';
$messages['withdrawal_field_error'] = 'Confirme que ha leído la política de desistimiento.';
$messages['order_error'] = 'No se pudo crear el pedido.';
$messages['technical_error'] = 'No se pudo procesar el pedido debido a un error técnico.';
$messages['demo_title'] = 'Tienda de demostración – sin compra real';
$messages['demo_message'] = 'Esta tienda se ofrece exclusivamente con fines de demostración y prueba. Puede completar todo el proceso de pedido con datos de prueba; únicamente se procesa una operación técnica de prueba. No se realizan compras, pagos ni entregas reales y no se formaliza ningún contrato de compraventa.';
$messages['payment_bank_transfer'] = 'Pago anticipado / transferencia bancaria';
$messages['payment_invoice'] = 'Factura';
$messages['payment_none_help'] = 'Actualmente no hay ningún método de pago activo o completamente configurado.';
$messages['payment_bank_transfer_help'] = 'El pedido se guarda. A continuación, el cliente transfiere el importe indicando el número de pedido como concepto.';
$messages['payment_invoice_help'] = 'El pedido se guarda y se paga más tarde mediante factura.';
$messages['payment_paypal_help'] = 'Se le redirigirá a PayPal. El pago se actualiza automáticamente después de la autorización.';
$messages['payment_amazon_help'] = 'Se le redirigirá a Amazon Pay. Los modos sandbox y real usan las credenciales guardadas en la configuración de la tienda.';
$messages['bank_transfer_default'] = 'Transfiera el importe de la factura e indique el número de pedido.';
$messages['invoice_default'] = 'Recibirá una factura. Pague dentro del plazo indicado.';
$messages['paypal_default'] = 'El pago se autoriza mediante PayPal y se actualiza automáticamente tras una respuesta correcta.';
$messages['amazon_default'] = 'El pago se autoriza mediante Amazon Pay y se actualiza automáticamente tras una respuesta correcta.';
$messages['account_owner'] = 'Titular de la cuenta';
$messages['purpose'] = 'Concepto';
$messages['column_product'] = 'Producto';
$messages['column_quantity'] = 'Cantidad';
$messages['column_price'] = 'Precio';
$messages['column_shipping'] = 'Envío';
$messages['column_total'] = 'Total';
$messages['amount_due'] = 'Importe a pagar';
$messages['order_saved_title'] = 'Pedido guardado';
$messages['order_number_text'] = 'Su número de pedido es';
$messages['payment_method_label'] = 'Método de pago';
$messages['status_label'] = 'Estado';
$messages['order_waiting'] = 'El pedido está visible en la administración de la tienda y está pendiente de tramitación.';
$messages['total_label'] = 'Total';
$messages['view_orders'] = 'Ver pedidos';
$messages['continue_shopping'] = 'Seguir comprando';
$messages['thanks_title'] = 'Gracias';
$messages['saved_snapshot_subtitle'] = 'El pedido se guardó como instantánea.';
$messages['payment_note'] = 'Información de pago';

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

$add_field('customer_name','varchar','Nombre','*|min=2|max=180','text-label',array('placeholder'=>'Su nombre'));
$add_field('customer_email','varchar','E-mail','email|max=180','text-label',array('placeholder'=>'name@example.org'));
$add_field('customer_phone','varchar','Teléfono','*|max=80','text-label',array('placeholder'=>'+49 30 123456'));
$add_field('shipping_address','mediumtext','Dirección de entrega','*|min=8|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>"Nombre\nCalle y número\nCódigo postal y localidad\nPaís"));
$add_field('note','mediumtext','Nota','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Nota opcional sobre el pedido'));
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
   $payment_options['bank_transfer'] = 'Pago anticipado / transferencia bancaria';
}
if ($cfg_bool('payment_invoice_enabled', false)) {
   $payment_options['invoice'] = 'Factura';
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
$add_field('payment_method','varchar','Método de pago','parameter','select-single-label',array('options'=>$option_string,'default'=>array_key_first($payment_options) ?: ''));
$add_field('accept_legal','int','He leído y acepto los <a href="?dbx_modul=dbxShop&amp;dbx_run1=legal" target="_blank" rel="noopener">textos legales, las condiciones generales y la información de pago y envío</a>.','int','dbxShop|shop-checkout-check');
$add_field('accept_withdrawal','int','He leído la <a href="?dbx_modul=dbxShop&amp;dbx_run1=withdrawal" target="_blank" rel="noopener">política de desistimiento</a>.','int','dbxShop|shop-checkout-check');
?>
