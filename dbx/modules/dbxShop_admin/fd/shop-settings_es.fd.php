<?php
$messages = array();
$messages['settings_title'] = 'Ajustes';
$messages['settings_subtitle'] = 'IVA, proceso de compra, formas de pago, envío y medios';
$messages['settings_help'] = 'Ayuda: Ajustes de la tienda';
$messages['settings_payment_test'] = 'Probar formas de pago';
$messages['settings_save'] = 'Guardar';
$messages['channels_external'] = 'Canales externos';
$messages['channels_global_active'] = 'activos globalmente';
$messages['channels_global_inactive'] = 'desactivados globalmente';
$messages['channels_edit'] = 'Editar canales';
$messages['channels_disabled'] = 'Los canales están desactivados globalmente. Por ello no se utilizan activamente canales externos.';
$messages['channels_none'] = 'No hay canales externos activos configurados.';
$messages['column_channel'] = 'Canal';
$messages['column_platform'] = 'Plataforma';
$messages['column_connection'] = 'Conexión';
$messages['column_export'] = 'Exportación';
$messages['column_import'] = 'Importación';
$messages['column_test'] = 'Prueba';
$messages['export_off'] = 'Exportación desactivada';
$messages['import_off'] = 'Importación desactivada';
$messages['not_tested'] = 'sin probar';

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

$add_field('enabled','int','Tienda activa','int',$check,array('data'=>array('hint'=>'Básicamente desbloquea las funciones de la tienda.')));
$add_field('demo_notice_enabled','int','Mostrar aviso de tienda demo','int',$check,array('data'=>array('hint'=>'Muestra el aviso multilingüe «Tienda de demostración – sin compra real» en el catálogo y en el proceso de compra.')));
$add_field('default_channel','varchar','Canal predeterminado','parameter|max=40','text-label',array('placeholder'=>'shop','tooltip'=>'Canal de venta técnico predeterminado para pedidos normales de la tienda.'));
$add_field('default_currency','varchar','Moneda predeterminada','parameter|max=3','text-label',array('placeholder'=>'EUR','tooltip'=>'Código ISO de moneda de tres letras.'));
$add_field('price_display','varchar','Visualización de precios','parameter|max=12','select-single-label',array('options'=>'gross=Bruto&net=Neto'));
$add_field('tax_display_enabled','int','IVA','int','select-single-label',array('options'=>'1=Mostrar IVA&0=No mostrar IVA','tooltip'=>'Controla si se muestra la indicación del IVA junto a los precios.'));
$add_field('b2b_mode','int','Modo B2B','int',$check,array('data'=>array('hint'=>'Preparado para la lógica de clientes empresariales y precios netos.')));
$add_field('stock_enabled','int','Usar existencias','int',$check,array('data'=>array('hint'=>'Permite utilizar las existencias para controlar la disponibilidad y mostrar avisos.')));
$add_field('channels_enabled','int','Usar canales','int',$check,array('data'=>array('hint'=>'Muestra funciones para eBay, Amazon, anuncios clasificados, mobile.de y otras plataformas. Desactívelo para instalaciones que solo utilicen la tienda.')));

$add_field('default_tax_class','varchar','Clase de IVA predeterminada','parameter|max=20','select-single-label',array('options'=>'mwst1=mwst1&mwst2=mwst2&mwst3=mwst3'));
$add_field('tax_title_mwst1','varchar','Nombre','*|max=80','text-label',array('placeholder'=>'IVA normal'));
$add_field('tax_rate_mwst1','decimal','Porcentaje','decimal','text-label',array('placeholder'=>'19.00'));
$add_field('tax_title_mwst2','varchar','Nombre','*|max=80','text-label',array('placeholder'=>'IVA reducido'));
$add_field('tax_rate_mwst2','decimal','Porcentaje','decimal','text-label',array('placeholder'=>'7.00'));
$add_field('tax_title_mwst3','varchar','Nombre','*|max=80','text-label',array('placeholder'=>'IVA preparado'));
$add_field('tax_rate_mwst3','decimal','Porcentaje','decimal','text-label',array('placeholder'=>'22.00'));

$add_field('checkout_guest_allowed','int','Permitir pedidos de invitados','int',$check,array('data'=>array('hint'=>'Permite pedidos sin cuenta previa del cliente.')));
$add_field('legal_snapshot_enabled','int','Guardar instantáneas de texto legal','int',$check,array('data'=>array('hint'=>'Guarda textos legales y política de cancelación en el momento de la compra.')));
$add_field('withdrawal_button_enabled','int','Mostrar desistimiento','int',$check,array('data'=>array('hint'=>'Muestra el área de desistimiento en la tienda. El contenido procede de la página del CMS /shop-widerruf.')));
$add_field('mail_customer_enabled','int','Enviar correo al cliente','int',$check,array('data'=>array('hint'=>'Envía una confirmación al cliente después del pedido y revocación.')));
$add_field('mail_admin_enabled','int','Enviar correo de administrador','int',$check,array('data'=>array('hint'=>'Envía una notificación interna al ordenar y revocar.')));
$add_field('mail_from','varchar','Remitente de correo','email|max=180','text-label',array('placeholder'=>'shop@example.org'));
$add_field('mail_admin_to','varchar','Correo del administrador','email|max=180','text-label',array('placeholder'=>'admin@example.org'));

$add_field('payment_bank_transfer_enabled','int','Pago anticipado activo','int',$check,array('data'=>array('hint'=>'Activa el pago anticipado por transferencia bancaria como método de pago.')));
$add_field('payment_bank_transfer_account_owner','varchar','Titular de la cuenta','*|max=160','text-label',array('placeholder'=>'Empresa Ejemplo S.L.'));
$add_field('payment_bank_transfer_iban','varchar','IBAN','*|max=60','text-label',array('placeholder'=>'DE02120300000202051'));
$add_field('payment_bank_transfer_bic','varchar','BIC','*|max=40','text-label',array('placeholder'=>'BYLADEM1001'));
$add_field('payment_bank_transfer_bank_name','varchar','Banco','*|max=120','text-label',array('placeholder'=>'Banco modelo'));
$add_field('payment_bank_transfer_instructions','mediumtext','Prepago','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Por favor, transfiera el importe de la factura especificando el número de pedido.'));
$add_field('payment_invoice_enabled','int','Factura activa','int',$check,array('data'=>array('hint'=>'Activa la factura como método de pago. Útil especialmente para B2B o clientes liberados.')));
$add_field('payment_invoice_instructions','mediumtext','Nota de facturación','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Recibirás una factura. Por favor, pague dentro del plazo especificado.'));
$add_field('payment_paypal_enabled','int','PayPal activa','int',$check,array('data'=>array('hint'=>'PayPal sólo aparece en el checkout cuando el ID del cliente y el secreto están disponibles.')));
$add_field('payment_paypal_mode','varchar','Modo PayPal','parameter|max=12','select-single-label',array('options'=>'sandbox=Sandbox&live=Producción'));
$add_field('payment_paypal_brand_name','varchar','Nombre de la marca PayPal','*|max=120','text-label',array('placeholder'=>'dbXapp'));
$add_field('payment_paypal_client_id','varchar','PayPal ID de cliente','*|max=255','text-label',array('placeholder'=>'Axxxxxxxxxxxxxxxxxxx'));
$add_field('payment_paypal_client_secret','varchar','PayPal Client Secret','*|max=255','text-label',array('placeholder'=>'Exxxxxxxx_secret_xxxx'));
$add_field('payment_amazon_pay_enabled','int','Amazon Pay activo','int',$check,array('data'=>array('hint'=>'Activa Amazon Pay como método de pago. La autorización de API real se conectará más tarde.')));
$add_field('payment_amazon_pay_mode','varchar','Modo de Amazon Pay','parameter|max=12','select-single-label',array('options'=>'sandbox=Sandbox&live=Producción'));
$add_field('payment_amazon_pay_region','varchar','Amazon Pay Region','parameter|max=12','select-single-label',array('options'=>'EU=UE&UK=UK&US=Estados Unidos'));
$add_field('payment_amazon_pay_merchant_id','varchar','Amazon Merchant ID','*|max=160','text-label',array('placeholder'=>'A1BCD2EFGH3IJK'));
$add_field('payment_amazon_pay_store_id','varchar','Amazon Store ID','*|max=160','text-label',array('placeholder'=>'amzn1.application-oa2-client.xxxxx'));
$add_field('payment_amazon_pay_public_key_id','varchar','ID de clave pública','*|max=160','text-label',array('placeholder'=>'SANDBOX-AEUPKxxxx'));
$add_field('payment_amazon_pay_private_key','mediumtext','Clave privada','*|max=6000','textarea-label',array('data'=>'rows=6','placeholder'=>"------"));
$add_field('payment_amazon_pay_sandbox_simulation_code','varchar','simulación de sandbox','parameter|max=80','text-label',array('placeholder'=>'AmazonCanceled'));

$add_field('delivery_digital_download_enabled','int','Descargas digitales activas','int',$check,array('data'=>array('hint'=>'Permite la provisión digital para productos adecuados.')));
$add_field('delivery_flat_shipping_enabled','int','Envío de paquetes activos','int',$check,array('data'=>array('hint'=>'Activa los costos globales de envío como retroceso.')));
$add_field('delivery_flat_shipping_gross_price','decimal','Envío bruto de tarifa plana','decimal','text-label',array('placeholder'=>'5.90'));
$add_field('media_usage_slot','varchar','Ranura de medios del CMS','parameter|max=40','text-label',array('placeholder'=>'shop'));
?>
