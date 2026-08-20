<?php

$shop_root = dirname(__DIR__);
$admin_file = dirname(__DIR__, 2) . '/dbxShop_admin/include/dbxShopAdmin.class.php';
require_once dirname(__DIR__, 3) . '/include/tests/dbxModuleSourceBundle.php';
$admin = dbx_test_module_source_bundle($admin_file);

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

$protected_actions = array(
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
foreach ($protected_actions as $action) {
   if (strpos($admin, "check_action_token('" . $action . "')") === false) {
      $fail('Shop-Admin-Aktion ist nicht tokengeprueft: ' . $action, 3);
   }
}

if (strpos($admin, "'install_url' => \$this->action_url(") === false
   || strpos($admin, '$tree_move_url = str_replace(\'&\', \'&amp;\', $this->action_url(') === false
   || strpos($admin, '$invoice_pdf_url = $this->action_url(') === false
   || strpos($admin, "\$report->set_action(\$this->action_url('?dbx_modul=dbxShop_admin&dbx_run1=products'))") === false) {
   $fail('Mindestens ein schreibender Shop-Admin-Link wird ohne Token erzeugt.', 4);
}

if (strpos($admin, 'private bool $maintenance_mode = false;') === false
   || strpos($admin, 'private function maintain_shop_admin_content(): void') === false
   || strpos($admin, '$this->maintain_shop_admin_content();') === false
   || substr_count($admin, 'if (!$this->maintenance_mode)') < 1
   || strpos($admin, 'ensureShopAdminHelpPage') !== false) {
   $fail('Shop-Medienpflege ist nicht auf den Wartungslauf begrenzt oder alte CMS-Hilfe ist noch aktiv.', 5);
}

if (substr_count($admin, '$this->ensure_cms_shop_media_folder();') !== 1
   || substr_count($admin, '$this->sync_shop_media_usage();') !== 3) {
   $fail('Ein normaler Shop-Admin-GET fuehrt weiterhin Medienpflege aus.', 6);
}

echo "OK shop admin action security\n";
