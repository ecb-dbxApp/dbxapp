<?php

require_once dirname(__DIR__, 3) . '/include/tests/dbxModuleSourceBundle.php';

$root = dirname(__DIR__);
$repo = dbx_test_module_source_bundle($root . '/include/dbxShopRepository.class.php');
$service = dbx_test_module_source_bundle($root . '/include/dbxShopService.class.php');

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

if (!is_string($repo) || !is_string($service)) {
   $fail('Shop-Quellen konnten nicht gelesen werden.', 1);
}
if (strpos($repo, 'AND trash = 0 AND stock >= ') === false
   || strpos($repo, 'stock = stock - ') === false) {
   $fail('Bestand wird nicht atomar und ohne Unterlauf reserviert.', 2);
}
if (substr_count($repo, "begin(\$this->dd('shopOrder'))") < 3
   || strpos($repo, 'order_item_insert_failed') === false
   || strpos($repo, 'order_history_insert_failed') === false) {
   $fail('Bestellung, Positionen, Bestand und Historie bilden keine Transaktion.', 3);
}
if (strpos($repo, 'channel_import_lock_failed') === false
   || strpos($repo, 'Zweite Idempotenzpruefung nach dem serialisierenden Channel-Lock.') === false) {
   $fail('Parallele Channel-Importe werden nicht datenbankweit vor der Duplikatpruefung serialisiert.', 10);
}
if (strpos($repo, 'private function decorate_products(array $rows): array') === false
   || strpos($repo, 'return $this->decorate_products($rows);') === false
   || strpos($repo, 'foreach ($this->decorate_products(is_array($rows) ? $rows : array()) as $row)') === false) {
   $fail('Produktlisten verwenden nicht einheitlich die gebuendelte Repository-Datensicht.', 11);
}
if (strpos($service, 'private function shop_template_fields(string $template): array') === false
   || strpos($service, '$this->product_template_data($product, $channel, false, $this->shop_template_fields($template))') === false
   || strpos($service, '$this->product_template_data(' . "\n" . '         $product,' . "\n" . '         $channel,' . "\n" . '         true,') === false) {
   $fail('Produktkarten und -details berechnen nicht nur die vom Template verwendeten Werte.', 12);
}
$all_images = preg_match('/public function all_images\\(\\): array \\{([\\s\\S]*?)\\n   \\}/', $repo, $match)
   ? $match[1]
   : '';
if (strpos($all_images, '$product_by_id = $this->rows_by_id') === false
   || strpos($all_images, '$group_by_id = $this->rows_by_id') === false
   || strpos($all_images, '->select1(') !== false) {
   $fail('Die Admin-Bildliste laedt Produkt- und Gruppentitel nicht gebuendelt.', 13);
}
if (strpos($repo, 'claim_order_payment') === false
   || strpos($repo, "'payment_status' => 'processing'") === false
   || strpos($repo, '(int)$db->_update_count !== 1') === false) {
   $fail('Provider-Abschluesse sind nicht gegen parallele Wiederholung beansprucht.', 4);
}
if (strpos($repo, 'is_stale_payment_processing') === false
   || strpos($repo, 'payment_processing_retry_seconds') === false
   || strpos($repo, "' AND update_date = '") === false) {
   $fail('Verwaiste processing-Claims besitzen keinen zeitlich und atomar begrenzten Wiederanlauf.', 9);
}
if (strpos($service, "provider_return_order('paypal'") === false
   || strpos($service, "provider_return_order('amazon_pay'") === false
   || strpos($service, 'validate_capture(') === false
   || strpos($service, 'validate_completion(') === false) {
   $fail('Provider-Ruecklaeufe werden nicht vollstaendig gebunden und verifiziert.', 5);
}
if (strpos($service, 'checkout_request_order($request_id)') === false
   || strpos($service, 'remember_checkout_request($request_id, $order)') === false) {
   $fail('Wiederholte Checkout-POSTs sind nicht an eine idempotente Request-ID gebunden.', 6);
}

$paypal_cancel = preg_match('/public function paypal_cancel\\(\\): string \\{([\\s\\S]*?)\\n   \\}/', $service, $match)
   ? $match[1]
   : '';
$amazon_cancel = preg_match('/public function amazon_pay_cancel\\(\\): string \\{([\\s\\S]*?)\\n   \\}/', $service, $match)
   ? $match[1]
   : '';
if (strpos($paypal_cancel, 'update_order_payment') !== false || strpos($amazon_cancel, 'update_order_payment') !== false) {
   $fail('Ein nicht verifizierter Browser-Cancel veraendert weiterhin den Zahlungsstatus.', 7);
}

if (strpos($service, "if (\$secret === '')") === false
   || strpos($service, 'Webhook-Authentifizierung ist nicht konfiguriert.') === false
   || strpos($service, "\$_GET['secret']") !== false) {
   $fail('Der oeffentliche Channel-Webhook ist nicht fail-closed oder akzeptiert Secrets aus GET-URLs.', 8);
}

echo "OK shop integrity\n";
