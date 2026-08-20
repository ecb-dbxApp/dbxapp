<?php
$messages = array();
$messages['bar_title'] = 'Filtros del catálogo';
$messages['attributes_heading'] = 'Propiedades';
$messages['demo_title'] = 'Tienda de demostración – sin compra real';
$messages['demo_message'] = 'Esta tienda se ofrece exclusivamente con fines de demostración y prueba. Puede completar todo el proceso de pedido con datos de prueba; únicamente se procesa una operación técnica de prueba. No se realizan compras, pagos ni entregas reales y no se formaliza ningún contrato de compraventa.';
$messages['groups_aria'] = 'Grupos de productos';
$messages['all_products'] = 'Todos los productos';
$messages['group_fallback'] = 'Grupo de productos';
$messages['all_option'] = 'Todos';
$messages['refine_filters'] = 'Afinar filtros';
$messages['column_products'] = 'Productos';
$messages['no_products_title'] = 'No se han encontrado productos';
$messages['no_products_message'] = 'Actualmente no hay productos activos que coincidan con su búsqueda.';
$messages['catalog_group_subtitle'] = 'Grupo de productos y productos relacionados.';
$messages['catalog_subtitle'] = 'Productos, artículos promocionales, servicios y paquetes digitales.';
$messages['product_page_title'] = 'Producto';
$messages['product_not_found_subtitle'] = 'No se ha encontrado el producto o no está activo para este canal.';
$messages['product_not_found_title'] = 'Producto no encontrado';
$messages['product_not_found_message'] = 'El producto solicitado no existe o no está habilitado para el canal seleccionado.';
$messages['product_fallback'] = 'Producto';
$messages['tax_label'] = 'IVA';
$messages['shipping_suffix'] = 'envío';
$messages['free_shipping'] = 'envío gratuito';
$messages['delivery_time'] = 'Plazo de entrega';
$messages['shipping_method'] = 'Método de envío';
$messages['shipping_costs'] = 'Gastos de envío';
$messages['stock_out'] = 'Actualmente no está disponible.';
$messages['stock_low'] = 'Solo quedan {count} unidades.';
$messages['stock_available'] = 'Stock: disponible';


$field = array();
$field['name'] = 'q';
$field['type'] = 'varchar';
$field['index'] = '';
$field['length'] = '128';
$field['default'] = '';
$field['label'] = 'Búsqueda';
$field['rules'] = 'sqlsearch|max=128';
$field['tooltip'] = 'Buscar en la tienda';
$field['errormsg'] = 'Compruebe el término de búsqueda.';
$field['placeholder'] = 'Introduzca el término de búsqueda';
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
