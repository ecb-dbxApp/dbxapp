<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';

require dirname(__DIR__) . '/dd/shopProductChannel.dd.php';

$fdLabels = array(
    'product_id' => 'Producto',
    'channel_key' => 'Canal',
    'active' => 'Activo',
    'channel_sku' => 'SKU del canal',
    'price_gross' => 'Precio bruto del canal',
    'shipping_gross' => 'Envío bruto del canal',
    'external_listing_id' => 'ID de anuncio externo',
    'external_offer_id' => 'ID de oferta externa',
    'export_status' => 'Estado de exportación',
    'export_message' => 'Mensaje de exportación',
    'export_payload' => 'Datos de exportación',
    'last_export_date' => 'Última exportación',
    'note' => 'Nota',
);
foreach ($fields as &$fdField) {
    if (isset($fdLabels[$fdField['name']])) {
        $fdField['label'] = $fdLabels[$fdField['name']];
    }
}
unset($fdField, $fdLabels);

$messages['mapping_title'] = 'Mapeo de canal';
$messages['mapping_missing'] = 'Falta el producto o el canal.';
$messages['mapping_not_found'] = 'No se encontró el producto o el canal.';
$messages['mapping_saved'] = 'Se guardó el mapeo de canal.';
$messages['mapping_help'] = 'Ayuda: Mapeo de canal por producto';
$messages['mapping_save'] = 'Guardar';
$messages['mapping_export_title'] = 'Exportar canal';
$messages['mapping_export_confirm'] = '¿Desea exportar este producto ahora?';
$messages['mapping_export_label'] = 'Exportar';
$messages['mapping_intro'] = 'Aquí se mantienen los valores del producto para este canal. Las credenciales globales de API permanecen en la administración de canales.';
$messages['mapping_values_title'] = 'Valores del canal';
$messages['mapping_export_status_title'] = 'Estado de exportación';
$messages['mapping_not_exported'] = 'sin exportar';
$messages['mapping_current_selection'] = 'Selección actual: {value}';
$messages['mapping_group_default'] = 'Valor del grupo de productos: {value}';
$messages['mapping_channel_default'] = 'Valor predeterminado del canal: {value}';
$messages['mapping_ebay_category'] = 'Categoría de eBay';
$messages['mapping_ebay_category_hint'] = 'La lista proporciona valores predeterminados. Se pueden configurar más identificadores en los ajustes de la tienda o posteriormente mediante la integración con eBay Taxonomy.';
$messages['mapping_category_software'] = 'Software / productos digitales';
$messages['mapping_category_clothing'] = 'Ropa y accesorios';
$messages['mapping_category_electronics'] = 'Electrónica';
$messages['mapping_category_home'] = 'Hogar y jardín';
$messages['mapping_category_business'] = 'Negocios e industria';
$messages['mapping_location_key'] = 'Clave de ubicación';
$messages['mapping_location_placeholder'] = 'configurar en el canal';
$messages['mapping_location_hint'] = 'Procede de la configuración del canal de eBay.';
$messages['mapping_condition'] = 'Estado';
$messages['mapping_condition_new'] = 'Nuevo';
$messages['mapping_condition_used_excellent'] = 'Usado – excelente';
$messages['mapping_condition_used_good'] = 'Usado – bueno';
$messages['mapping_ebay_aspects'] = 'Aspectos de eBay';
$messages['mapping_requirements'] = 'Requisitos';
$messages['mapping_requirements_listing'] = 'Anuncio';
$messages['mapping_requirements_product'] = 'Datos del producto';
$messages['mapping_requirements_offer'] = 'Oferta';
$messages['mapping_brand'] = 'Marca';
$messages['mapping_amazon_attributes'] = 'Atributos de Amazon';
$messages['mapping_amazon_hint'] = 'Amazon exige distintos atributos obligatorios según el tipo de producto. Este formulario guarda valores simples key=value; el conector los convierte en atributos de SP-API.';
$messages['mapping_vehicle_model'] = 'Modelo';
$messages['mapping_vehicle_registration'] = 'Primera matriculación';
$messages['mapping_vehicle_mileage'] = 'Kilometraje';
$messages['mapping_vehicle_fuel'] = 'Combustible';
$messages['mapping_vehicle_power'] = 'Potencia kW';
$messages['mapping_vehicle_category'] = 'Tipo de vehículo';
$messages['mapping_vehicle_extra'] = 'Otros campos de vehículos de mobile.de';
$messages['mapping_mobile_hint'] = 'mobile.de está previsto exclusivamente para anuncios de vehículos. El conector bloquea deliberadamente los productos de tienda convencionales.';
$messages['mapping_classified_category'] = 'Categoría/categoría de socio';
$messages['mapping_place'] = 'Localidad/ubicación';
$messages['mapping_contact_name'] = 'Nombre de contacto';
$messages['mapping_phone'] = 'Teléfono';
$messages['mapping_classified_attributes'] = 'Atributos de anuncios clasificados/middleware';
$messages['mapping_classified_hint'] = 'Sin una API de socio o middleware autorizada no se realiza ninguna carga automática a kleinanzeigen.de. Los datos quedan preparados para una transferencia manual.';
$messages['mapping_endpoint'] = 'Acción/endpoint';
$messages['mapping_middleware_attributes'] = 'Atributos de middleware';
?>
