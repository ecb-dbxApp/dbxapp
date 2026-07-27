<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';
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

$addField = function($name, $type, $label, $rules, $tpl, $extra = array()) use (&$fields) {
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

$addField('enabled','int','Tienda activa','int',$check,array('data'=>array('hint'=>'Básicamente desbloquea las funciones de la tienda.')));
$addField('demo_notice_enabled','int','Mostrar aviso de tienda demo','int',$check,array('data'=>array('hint'=>'Muestra el aviso multilingüe «Tienda de demostración – sin compra real» en el catálogo y en el proceso de compra.')));
$addField('default_channel','varchar','Canal predeterminado','parameter|max=40','text-label',array('placeholder'=>'shop','tooltip'=>'Canal de venta técnico predeterminado para pedidos normales de la tienda.'));
$addField('default_currency','varchar','Moneda predeterminada','parameter|max=3','text-label',array('placeholder'=>'EUR','tooltip'=>'Código ISO de moneda de tres letras.'));
$addField('price_display','varchar','Visualización de precios','parameter|max=12','select-single-label',array('options'=>'gross=Bruto&net=Neto'));
$addField('tax_display_enabled','int','IVA','int','select-single-label',array('options'=>'1=Mostrar IVA&0=No mostrar IVA','tooltip'=>'Controla si se muestra la indicación del IVA junto a los precios.'));
$addField('b2b_mode','int','Modo B2B','int',$check,array('data'=>array('hint'=>'Preparado para la lógica de clientes empresariales y precios netos.')));
$addField('stock_enabled','int','Usar existencias','int',$check,array('data'=>array('hint'=>'Permite utilizar las existencias para controlar la disponibilidad y mostrar avisos.')));
$addField('channels_enabled','int','Usar canales','int',$check,array('data'=>array('hint'=>'Muestra funciones para eBay, Amazon, anuncios clasificados, mobile.de y otras plataformas. Desactívelo para instalaciones que solo utilicen la tienda.')));

$addField('default_tax_class','varchar','Clase de IVA predeterminada','parameter|max=20','select-single-label',array('options'=>'mwst1=mwst1&mwst2=mwst2&mwst3=mwst3'));
$addField('tax_title_mwst1','varchar','Nombre','*|max=80','text-label',array('placeholder'=>'IVA normal'));
$addField('tax_rate_mwst1','decimal','Porcentaje','decimal','text-label',array('placeholder'=>'19.00'));
$addField('tax_title_mwst2','varchar','Nombre','*|max=80','text-label',array('placeholder'=>'IVA reducido'));
$addField('tax_rate_mwst2','decimal','Porcentaje','decimal','text-label',array('placeholder'=>'7.00'));
$addField('tax_title_mwst3','varchar','Nombre','*|max=80','text-label',array('placeholder'=>'IVA preparado'));
$addField('tax_rate_mwst3','decimal','Porcentaje','decimal','text-label',array('placeholder'=>'22.00'));

$addField('checkout_guest_allowed','int','Permitir pedidos de invitados','int',$check,array('data'=>array('hint'=>'Permite pedidos sin cuenta previa del cliente.')));
$addField('legal_snapshot_enabled','int','Guardar instantáneas de texto legal','int',$check,array('data'=>array('hint'=>'Guarda textos legales y política de cancelación en el momento de la compra.')));
$addField('withdrawal_button_enabled','int','Mostrar desistimiento','int',$check,array('data'=>array('hint'=>'Muestra el área de desistimiento en la tienda. El contenido procede de la página del CMS /shop-widerruf.')));
$addField('mail_customer_enabled','int','Enviar correo al cliente','int',$check,array('data'=>array('hint'=>'Envía una confirmación al cliente después del pedido y revocación.')));
$addField('mail_admin_enabled','int','Enviar correo de administrador','int',$check,array('data'=>array('hint'=>'Envía una notificación interna al ordenar y revocar.')));
$addField('mail_from','varchar','Remitente de correo','email|max=180','text-label',array('placeholder'=>'shop@example.org'));
$addField('mail_admin_to','varchar','Correo del administrador','email|max=180','text-label',array('placeholder'=>'admin@example.org'));

$addField('payment_bank_transfer_enabled','int','Pago anticipado activo','int',$check,array('data'=>array('hint'=>'Activa el pago anticipado por transferencia bancaria como método de pago.')));
$addField('payment_bank_transfer_account_owner','varchar','Titular de la cuenta','*|max=160','text-label',array('placeholder'=>'Empresa Ejemplo S.L.'));
$addField('payment_bank_transfer_iban','varchar','IBAN','*|max=60','text-label',array('placeholder'=>'DE02120300000202051'));
$addField('payment_bank_transfer_bic','varchar','BIC','*|max=40','text-label',array('placeholder'=>'BYLADEM1001'));
$addField('payment_bank_transfer_bank_name','varchar','Banco','*|max=120','text-label',array('placeholder'=>'Banco modelo'));
$addField('payment_bank_transfer_instructions','mediumtext','Prepago','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Por favor, transfiera el importe de la factura especificando el número de pedido.'));
$addField('payment_invoice_enabled','int','Factura activa','int',$check,array('data'=>array('hint'=>'Activa la factura como método de pago. Útil especialmente para B2B o clientes liberados.')));
$addField('payment_invoice_instructions','mediumtext','Nota de facturación','*|max=2000','textarea-label',array('data'=>'rows=4','placeholder'=>'Recibirás una factura. Por favor, pague dentro del plazo especificado.'));
$addField('payment_paypal_enabled','int','PayPal activa','int',$check,array('data'=>array('hint'=>'PayPal sólo aparece en el checkout cuando el ID del cliente y el secreto están disponibles.')));
$addField('payment_paypal_mode','varchar','Modo PayPal','parameter|max=12','select-single-label',array('options'=>'sandbox=Sandbox&live=Producción'));
$addField('payment_paypal_brand_name','varchar','Nombre de la marca PayPal','*|max=120','text-label',array('placeholder'=>'dbXapp'));
$addField('payment_paypal_client_id','varchar','PayPal ID de cliente','*|max=255','text-label',array('placeholder'=>'Axxxxxxxxxxxxxxxxxxx'));
$addField('payment_paypal_client_secret','varchar','PayPal Client Secret','*|max=255','text-label',array('placeholder'=>'Exxxxxxxx_secret_xxxx'));
$addField('payment_amazon_pay_enabled','int','Amazon Pay activo','int',$check,array('data'=>array('hint'=>'Activa Amazon Pay como método de pago. La autorización de API real se conectará más tarde.')));
$addField('payment_amazon_pay_mode','varchar','Modo de Amazon Pay','parameter|max=12','select-single-label',array('options'=>'sandbox=Sandbox&live=Producción'));
$addField('payment_amazon_pay_region','varchar','Amazon Pay Region','parameter|max=12','select-single-label',array('options'=>'EU=UE&UK=UK&US=Estados Unidos'));
$addField('payment_amazon_pay_merchant_id','varchar','Amazon Merchant ID','*|max=160','text-label',array('placeholder'=>'A1BCD2EFGH3IJK'));
$addField('payment_amazon_pay_store_id','varchar','Amazon Store ID','*|max=160','text-label',array('placeholder'=>'amzn1.application-oa2-client.xxxxx'));
$addField('payment_amazon_pay_public_key_id','varchar','ID de clave pública','*|max=160','text-label',array('placeholder'=>'SANDBOX-AEUPKxxxx'));
$addField('payment_amazon_pay_private_key','mediumtext','Clave privada','*|max=6000','textarea-label',array('data'=>'rows=6','placeholder'=>"------"));
$addField('payment_amazon_pay_sandbox_simulation_code','varchar','simulación de sandbox','parameter|max=80','text-label',array('placeholder'=>'AmazonCanceled'));

$addField('delivery_digital_download_enabled','int','Descargas digitales activas','int',$check,array('data'=>array('hint'=>'Permite la provisión digital para productos adecuados.')));
$addField('delivery_flat_shipping_enabled','int','Envío de paquetes activos','int',$check,array('data'=>array('hint'=>'Activa los costos globales de envío como retroceso.')));
$addField('delivery_flat_shipping_gross_price','decimal','Envío bruto de tarifa plana','decimal','text-label',array('placeholder'=>'5.90'));
$addField('media_usage_slot','varchar','Ranura de medios del CMS','parameter|max=40','text-label',array('placeholder'=>'shop'));
?>
