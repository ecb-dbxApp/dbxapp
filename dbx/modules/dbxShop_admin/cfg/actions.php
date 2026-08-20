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
    'product_edit' => $page('product_edit'),
    'product_tree_move' => $page('product_tree_move'),
    'product_channel_mapping' => $page('product_channel_mapping'),
    'products_help' => $page('products_help'),
    'groups' => $page('groups'),
    'attributes' => $page('attributes'),
    'product_attributes' => $page('product_attributes'),
    'shipping_groups' => $page('shipping_groups'),
    'channel_groups' => $page('channel_groups'),
    'channels' => $page('channels'),
    'media' => $page('media'),
    'assign_media' => $page('assign_media'),
    'orders' => $page('orders'),
    'order_detail' => $page('order_detail'),
    'order_invoice' => $page('order_invoice'),
    'order_invoice_pdf' => array(
        'handler' => 'order_invoice_pdf', 'methods' => array('GET', 'HEAD'),
        'groups' => array('admin'), 'mutation' => false, 'response' => 'file',
    ),
    'legal' => $page('legal_page'),
    'returns' => $page('returns'),
    'settings' => $page('settings'),
    'payment_test' => $page('payment_test'),
);
