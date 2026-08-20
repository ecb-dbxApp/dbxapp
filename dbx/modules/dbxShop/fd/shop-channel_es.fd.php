<?php
$messages = array();
require dirname(__DIR__) . '/dd/shopChannel.dd.php';

$fd_labels = array(
    'create_date' => 'Creado',
    'create_uid' => 'Creado por',
    'update_date' => 'Actualizado',
    'update_uid' => 'Actualizado por',
    'owner' => 'Propietario',
    'trash' => 'Papelera',
    'channel_key' => 'Clave del canal',
    'title' => 'Canal',
    'description' => 'Descripción',
    'platform_type' => 'Plataforma',
    'connection_mode' => 'Conexión',
    'api_base_url' => 'URL base de API',
    'api_client_secret' => 'Secreto del cliente',
    'api_access_token' => 'Token de acceso',
    'api_refresh_token' => 'Token de actualización',
    'api_username' => 'Usuario de API',
    'api_password' => 'Contraseña de API',
    'category_id' => 'ID de categoría',
    'payment_policy_id' => 'Política de pago',
    'fulfillment_policy_id' => 'Política de envío',
    'return_policy_id' => 'Política de devolución',
    'notification_destination' => 'Destino de notificación',
    'notification_topic' => 'Tema de notificación',
    'api_scope' => 'Ámbitos de API',
    'webhook_secret' => 'Secreto de webhook',
    'export_enabled' => 'Exportación activa',
    'order_import_enabled' => 'Importación de pedidos activa',
    'test_status' => 'Estado de prueba',
    'test_message' => 'Mensaje de prueba',
    'last_test_date' => 'Última prueba',
    'active' => 'Activo',
    'sorter' => 'Orden',
);
foreach ($fields as &$fd_field) {
    if (isset($fd_labels[$fd_field['name']])) {
        $fd_field['label'] = $fd_labels[$fd_field['name']];
    }
}
unset($fd_field, $fd_labels);
?>
