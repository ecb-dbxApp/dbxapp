<?php
$messages = array();
require dirname(__DIR__) . '/dd/shopChannel.dd.php';

$fd_labels = array(
    'create_date' => 'Created',
    'create_uid' => 'Created by',
    'update_date' => 'Updated',
    'update_uid' => 'Updated by',
    'description' => 'Description',
    'title' => 'Channel',
    'platform_type' => 'Platform',
    'connection_mode' => 'Connection',
    'api_base_url' => 'API base URL',
    'api_username' => 'API user',
    'api_password' => 'API password',
    'category_id' => 'Category ID',
    'notification_destination' => 'Notification destination',
    'export_enabled' => 'Export active',
    'order_import_enabled' => 'Order import active',
    'test_status' => 'Test status',
    'test_message' => 'Test message',
    'last_test_date' => 'Last test',
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
