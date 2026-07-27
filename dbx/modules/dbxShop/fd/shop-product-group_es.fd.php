<?php
$messages = array();
$messages['save_success'] = 'Los datos se guardaron';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Los datos no se pudieron guardar';

require dirname(__DIR__) . '/dd/shopProductGroup.dd.php';

$fdLabels = array(
    'create_date' => 'Creado',
    'create_uid' => 'Creado por',
    'update_date' => 'Actualizado',
    'update_uid' => 'Actualizado por',
    'owner' => 'Propietario',
    'trash' => 'Papelera',
    'parent_id' => 'Grupo superior',
    'group_key' => 'Clave de grupo',
    'title' => 'Grupo',
    'description' => 'Descripción',
    'tax_class' => 'Tipo de IVA',
    'default_tax_rate' => 'IVA predeterminado %',
    'default_shipping_gross' => 'Envío bruto predeterminado',
    'display_variant' => 'Presentación',
    'card_template' => 'Plantilla de tarjeta',
    'detail_template' => 'Plantilla de detalle',
    'gallery_template' => 'Plantilla de galería',
    'gallery_visible_count' => 'Imágenes visibles de la galería',
    'gallery_image_size' => 'Tamaño de imagen de la galería',
    'gallery_lightbox_width' => 'Ancho de la caja de luz',
    'gallery_overflow' => 'Desbordamiento de galería',
    'gallery_click' => 'Clic en galería',
    'attribute_notes' => 'Notas de atributos',
    'ebay_category_id' => 'Categoría de eBay',
    'amazon_product_type' => 'Tipo de producto de Amazon',
    'kleinanzeigen_category_id' => 'Categoría de anuncios clasificados',
    'mobile_category_id' => 'Categoría de mobile.de',
    'active' => 'Activo',
    'sorter' => 'Orden',
);
foreach ($fields as &$fdField) {
    if (isset($fdLabels[$fdField['name']])) {
        $fdField['label'] = $fdLabels[$fdField['name']];
    }
}
unset($fdField, $fdLabels);
?>
