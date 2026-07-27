<?php
$messages = array();
$messages['save_success'] = 'Data was saved';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Data could not be saved';

require dirname(__DIR__) . '/dd/shopChannel.dd.php';

$fdLabels = array(
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
foreach ($fields as &$fdField) {
    if (isset($fdLabels[$fdField['name']])) {
        $fdField['label'] = $fdLabels[$fdField['name']];
    }
}
unset($fdField, $fdLabels);
?>
