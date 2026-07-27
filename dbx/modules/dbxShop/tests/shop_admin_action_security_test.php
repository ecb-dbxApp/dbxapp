<?php

$shopRoot = dirname(__DIR__);
$adminFile = dirname(__DIR__, 2) . '/dbxShop_admin/include/dbxShopAdmin.class.php';
$admin = file_get_contents($adminFile);

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

if (!is_string($admin)) {
   $fail('Shop-Admin-Quelle konnte nicht gelesen werden.', 1);
}

if (strpos($admin, "private const ACTION_TOKEN_SCOPE = 'dbxShop_admin.actions'") === false
   || strpos($admin, 'action_token(self::ACTION_TOKEN_SCOPE)') === false
   || strpos($admin, 'check_action_token(self::ACTION_TOKEN_SCOPE, $token)') === false) {
   $fail('Die zentrale Shop-Admin-Tokenbehandlung fehlt.', 2);
}

$protectedActions = array(
   'assign_media',
   'product_tree_move',
   'install',
   'remove_image',
   'export_channel',
   'product_report_action',
   'delete_order',
   'withdrawal_status',
   'order_quick_action',
   'send_status_mail',
   'order_invoice_pdf',
);
foreach ($protectedActions as $action) {
   if (strpos($admin, "checkActionToken('" . $action . "')") === false) {
      $fail('Shop-Admin-Aktion ist nicht tokengeprueft: ' . $action, 3);
   }
}

if (strpos($admin, "'install_url' => \$this->actionUrl(") === false
   || strpos($admin, '$treeMoveUrl = str_replace(\'&\', \'&amp;\', $this->actionUrl(') === false
   || strpos($admin, '$invoicePdfUrl = $this->actionUrl(') === false
   || strpos($admin, "\$report->_action = \$this->actionUrl('?dbx_modul=dbxShop_admin&dbx_run1=products')") === false) {
   $fail('Mindestens ein schreibender Shop-Admin-Link wird ohne Token erzeugt.', 4);
}

if (strpos($admin, 'private bool $maintenanceMode = false;') === false
   || strpos($admin, 'private function maintainShopAdminContent(): void') === false
   || strpos($admin, '$this->maintainShopAdminContent();') === false
   || substr_count($admin, 'if (!$this->maintenanceMode)') < 2) {
   $fail('Shop-Hilfen oder Medienpflege sind nicht auf den Wartungslauf begrenzt.', 5);
}

if (substr_count($admin, '$this->ensureCmsShopMediaFolder();') !== 1
   || substr_count($admin, '$this->syncShopMediaUsage();') !== 3) {
   $fail('Ein normaler Shop-Admin-GET fuehrt weiterhin Medienpflege aus.', 6);
}

echo "OK shop admin action security\n";
