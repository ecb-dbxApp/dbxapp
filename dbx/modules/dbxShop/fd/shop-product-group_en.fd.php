<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';

require dirname(__DIR__) . '/dd/shopProductGroup.dd.php';

$fdLabels = array(
    'create_date' => 'Created',
    'create_uid' => 'Created by',
    'update_date' => 'Updated',
    'update_uid' => 'Updated by',
    'parent_id' => 'Parent group',
    'group_key' => 'Group key',
    'title' => 'Group',
    'description' => 'Description',
    'tax_class' => 'VAT rate',
    'default_tax_rate' => 'Default VAT %',
    'default_shipping_gross' => 'Default gross shipping',
    'display_variant' => 'Display',
    'card_template' => 'Card template',
    'detail_template' => 'Detail template',
    'gallery_template' => 'Gallery template',
    'gallery_visible_count' => 'Visible gallery images',
    'gallery_image_size' => 'Gallery image size',
    'gallery_lightbox_width' => 'Lightbox width',
    'gallery_overflow' => 'Gallery overflow',
    'gallery_click' => 'Gallery click',
    'attribute_notes' => 'Attribute notes',
    'ebay_category_id' => 'eBay category',
    'kleinanzeigen_category_id' => 'Classified ads category',
    'mobile_category_id' => 'mobile.de category',
    'active' => 'Active',
    'sorter' => 'Sort order',
);
foreach ($fields as &$fdField) {
    if (isset($fdLabels[$fdField['name']])) {
        $fdField['label'] = $fdLabels[$fdField['name']];
    }
}
unset($fdField, $fdLabels);
?>
