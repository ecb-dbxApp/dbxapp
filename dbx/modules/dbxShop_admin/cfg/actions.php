<?php

declare(strict_types=1);

$page = static fn(string $handler): array => array(
    'handler' => $handler,
    'methods' => array('GET', 'HEAD', 'POST'),
    'groups' => array('admin'),
    'mutation' => false,
    'response' => 'html',
);

return array(
    'dashboard' => $page('dashboard'),
    'start' => $page('dashboard'),
    'install' => $page('install'),
    'products' => $page('products'),
    'product_edit' => $page('productEdit'),
    'product_tree_move' => $page('productTreeMove'),
    'product_channel_mapping' => $page('productChannelMapping'),
    'products_help' => $page('productsHelp'),
    'groups' => $page('groups'),
    'attributes' => $page('attributes'),
    'product_attributes' => $page('productAttributes'),
    'shipping_groups' => $page('shippingGroups'),
    'channel_groups' => $page('channelGroups'),
    'channels' => $page('channels'),
    'media' => $page('media'),
    'assign_media' => $page('assignMedia'),
    'orders' => $page('orders'),
    'order_detail' => $page('orderDetail'),
    'order_invoice' => $page('orderInvoice'),
    'order_invoice_pdf' => array(
        'handler' => 'orderInvoicePdf', 'methods' => array('GET', 'HEAD'),
        'groups' => array('admin'), 'mutation' => false, 'response' => 'file',
    ),
    'legal' => $page('legalPage'),
    'returns' => $page('returns'),
    'settings' => $page('settings'),
    'payment_test' => $page('paymentTest'),
);
