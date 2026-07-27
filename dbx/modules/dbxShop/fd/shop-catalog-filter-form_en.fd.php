<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';
$messages['bar_title'] = 'Catalog filters';
$messages['attributes_heading'] = 'Properties';
$messages['demo_title'] = 'Demo shop – no actual purchase';
$messages['demo_message'] = 'This shop is provided solely for demonstration and testing. You can complete the entire order process with test data; only a technical test transaction is processed. No actual purchase, payment or delivery takes place, and no purchase contract is formed.';
$messages['groups_aria'] = 'Product groups';
$messages['all_products'] = 'All products';
$messages['group_fallback'] = 'Product group';
$messages['all_option'] = 'All';
$messages['refine_filters'] = 'Refine filters';
$messages['column_products'] = 'Products';
$messages['no_products_title'] = 'No products found';
$messages['no_products_message'] = 'No active shop products currently match your search.';
$messages['catalog_group_subtitle'] = 'Product group and matching products.';
$messages['catalog_subtitle'] = 'Products, merchandise, services and digital packages.';
$messages['product_page_title'] = 'Product';
$messages['product_not_found_subtitle'] = 'The product was not found or is not active for this channel.';
$messages['product_not_found_title'] = 'Product not found';
$messages['product_not_found_message'] = 'The requested product does not exist or is not enabled for the selected channel.';
$messages['product_fallback'] = 'Product';
$messages['tax_label'] = 'VAT';
$messages['shipping_suffix'] = 'shipping';
$messages['free_shipping'] = 'free shipping';
$messages['delivery_time'] = 'Delivery time';
$messages['shipping_method'] = 'Shipping method';
$messages['shipping_costs'] = 'Shipping costs';
$messages['stock_out'] = 'Currently out of stock.';
$messages['stock_low'] = 'Only {count} left in stock.';
$messages['stock_available'] = 'Stock: available';


$field = array();
$field['name'] = 'q';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '128';
$field['default'] = '';
$field['label'] = 'Search';
$field['rules'] = 'sqlsearch|max=128';
$field['tooltip'] = 'Search the shop';
$field['errormsg'] = 'Please check the search term.';
$field['placeholder'] = 'Enter the search term';
$field['convert'] = '';
$field['protect'] = '0';
$field['mask'] = '';
$field['data'] = array(
   'input_class' => 'form-control-sm dbx-shop-search-input',
   'wrap_class' => 'dbx-shop-search-wrap',
   'data_role' => 'shop-search',
   'extra_attrs' => 'data-dbx-clear-submit',
);
$field['options'] = '';
$field['tpl'] = 'dbx|search';
$fields[] = $field;

?>
