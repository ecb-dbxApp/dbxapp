<?php
$messages = array();
require dirname(__DIR__) . '/dd/shopProductGroup.dd.php';

$fd_labels = array(
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
foreach ($fields as &$fd_field) {
    if (isset($fd_labels[$fd_field['name']])) {
        $fd_field['label'] = $fd_labels[$fd_field['name']];
    }
}
unset($fd_field, $fd_labels);
?>
