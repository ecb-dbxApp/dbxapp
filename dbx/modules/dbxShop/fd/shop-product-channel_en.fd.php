<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';

require dirname(__DIR__) . '/dd/shopProductChannel.dd.php';

$fdLabels = array(
    'product_id' => 'Product',
    'active' => 'Active',
    'price_gross' => 'Channel gross price',
    'shipping_gross' => 'Channel gross shipping',
    'external_listing_id' => 'External listing ID',
    'external_offer_id' => 'External offer ID',
    'export_status' => 'Export status',
    'export_message' => 'Export message',
    'export_payload' => 'Export data',
    'last_export_date' => 'Last export',
    'note' => 'Note',
);
foreach ($fields as &$fdField) {
    if (isset($fdLabels[$fdField['name']])) {
        $fdField['label'] = $fdLabels[$fdField['name']];
    }
}
unset($fdField, $fdLabels);

$messages['mapping_title'] = 'Channel mapping';
$messages['mapping_missing'] = 'The product or channel is missing.';
$messages['mapping_not_found'] = 'The product or channel was not found.';
$messages['mapping_saved'] = 'Channel mapping saved.';
$messages['mapping_help'] = 'Help: Product channel mapping';
$messages['mapping_save'] = 'Save';
$messages['mapping_export_title'] = 'Export channel';
$messages['mapping_export_confirm'] = 'Export this product now?';
$messages['mapping_export_label'] = 'Export';
$messages['mapping_intro'] = 'Product-specific values for this channel are maintained here. Global API credentials remain in channel administration.';
$messages['mapping_values_title'] = 'Channel values';
$messages['mapping_export_status_title'] = 'Export status';
$messages['mapping_not_exported'] = 'not exported';
$messages['mapping_current_selection'] = 'Current selection: {value}';
$messages['mapping_group_default'] = 'Product group default: {value}';
$messages['mapping_channel_default'] = 'Channel default: {value}';
$messages['mapping_ebay_category'] = 'eBay category';
$messages['mapping_ebay_category_hint'] = 'The list provides defaults. Additional IDs can be configured in shop settings or later through the eBay Taxonomy integration.';
$messages['mapping_category_software'] = 'Software / digital products';
$messages['mapping_category_clothing'] = 'Clothing & accessories';
$messages['mapping_category_electronics'] = 'Electronics';
$messages['mapping_category_home'] = 'Home & garden';
$messages['mapping_category_business'] = 'Business & industrial';
$messages['mapping_location_key'] = 'Location key';
$messages['mapping_location_placeholder'] = 'set in channel configuration';
$messages['mapping_location_hint'] = 'Comes from the eBay channel configuration.';
$messages['mapping_condition'] = 'Condition';
$messages['mapping_condition_new'] = 'New';
$messages['mapping_condition_used_excellent'] = 'Used – excellent';
$messages['mapping_condition_used_good'] = 'Used – good';
$messages['mapping_ebay_aspects'] = 'eBay aspects';
$messages['mapping_requirements'] = 'Requirements';
$messages['mapping_requirements_listing'] = 'Listing';
$messages['mapping_requirements_product'] = 'Product data';
$messages['mapping_requirements_offer'] = 'Offer';
$messages['mapping_brand'] = 'Brand';
$messages['mapping_amazon_attributes'] = 'Amazon attributes';
$messages['mapping_amazon_hint'] = 'Amazon requires different mandatory attributes for each product type. This form stores simple key=value values; the connector converts them into SP-API attributes.';
$messages['mapping_vehicle_model'] = 'Model';
$messages['mapping_vehicle_registration'] = 'First registration';
$messages['mapping_vehicle_mileage'] = 'Mileage';
$messages['mapping_vehicle_fuel'] = 'Fuel';
$messages['mapping_vehicle_power'] = 'Power kW';
$messages['mapping_vehicle_category'] = 'Vehicle type';
$messages['mapping_vehicle_extra'] = 'Additional mobile.de vehicle fields';
$messages['mapping_mobile_hint'] = 'mobile.de is intended only for vehicle listings. The connector deliberately blocks conventional shop products.';
$messages['mapping_classified_category'] = 'Category/partner category';
$messages['mapping_place'] = 'Place/location';
$messages['mapping_contact_name'] = 'Contact name';
$messages['mapping_phone'] = 'Phone';
$messages['mapping_classified_attributes'] = 'Classified ads/middleware attributes';
$messages['mapping_classified_hint'] = 'Without an approved partner or middleware API, no automatic upload to kleinanzeigen.de is performed. The data is then prepared for manual transfer.';
$messages['mapping_endpoint'] = 'Action/endpoint';
$messages['mapping_middleware_attributes'] = 'Middleware attributes';
?>
