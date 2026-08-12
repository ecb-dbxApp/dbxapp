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
if (strpos($repo, 'private function decorateProducts(array $rows): array') === false
   || strpos($repo, 'return $this->decorateProducts($rows);') === false
   || strpos($repo, 'foreach ($this->decorateProducts(is_array($rows) ? $rows : array()) as $row)') === false) {
   $fail('Produktlisten verwenden nicht einheitlich die gebuendelte Repository-Datensicht.', 11);
}
if (strpos($service, 'private function shopTemplateFields(string $template): array') === false
   || strpos($service, '$this->productTemplateData($product, $channel, false, $this->shopTemplateFields($template))') === false
   || strpos($service, '$this->productTemplateData(' . "\n" . '         $product,' . "\n" . '         $channel,' . "\n" . '         true,') === false) {
   $fail('Produktkarten und -details berechnen nicht nur die vom Template verwendeten Werte.', 12);
}
$allImages = preg_match('/public function allImages\\(\\): array \\{([\\s\\S]*?)\\n   \\}/', $repo, $match)
   ? $match[1]
   : '';
if (strpos($allImages, '$productById = $this->rowsById') === false
   || strpos($allImages, '$groupById = $this->rowsById') === false
   || strpos($allImages, '->select1(') !== false) {
   $fail('Die Admin-Bildliste laedt Produkt- und Gruppentitel nicht gebuendelt.', 13);
}
if (strpos($repo, 'claimOrderPayment') === false
   || strpos($repo, "'payment_status' => 'processing'") === false
   || strpos($repo, '(int)$db->_update_count !== 1') === false) {
   $fail('Provider-Abschluesse sind nicht gegen parallele Wiederholung beansprucht.', 4);
}
if (strpos($repo, 'isStalePaymentProcessing') === false
   || strpos($repo, 'payment_processing_retry_seconds') === false
   || strpos($repo, "' AND update_date = '") === false) {
   $fail('Verwaiste processing-Claims besitzen keinen zeitlich und atomar begrenzten Wiederanlauf.', 9);
}
if (strpos($service, "providerReturnOrder('paypal'") === false
   || strpos($service, "providerReturnOrder('amazon_pay'") === false
   || strpos($service, 'validateCapture(') === false
   || strpos($service, 'validateCompletion(') === false) {
   $fail('Provider-Ruecklaeufe werden nicht vollstaendig gebunden und verifiziert.', 5);
}
if (strpos($service, 'checkoutRequestOrder($requestId)') === false
   || strpos($service, 'rememberCheckoutRequest($requestId, $order)') === false) {
   $fail('Wiederholte Checkout-POSTs sind nicht an eine idempotente Request-ID gebunden.', 6);
}

$paypalCancel = preg_match('/public function paypalCancel\\(\\): string \\{([\\s\\S]*?)\\n   \\}/', $service, $match)
   ? $match[1]
   : '';
$amazonCancel = preg_match('/public function amazonPayCancel\\(\\): string \\{([\\s\\S]*?)\\n   \\}/', $service, $match)
   ? $match[1]
   : '';
if (strpos($paypalCancel, 'updateOrderPayment') !== false || strpos($amazonCancel, 'updateOrderPayment') !== false) {
   $fail('Ein nicht verifizierter Browser-Cancel veraendert weiterhin den Zahlungsstatus.', 7);
}

if (strpos($service, "if (\$secret === '')") === false
   || strpos($service, 'Webhook-Authentifizierung ist nicht konfiguriert.') === false
   || strpos($service, "\$_GET['secret']") !== false) {
   $fail('Der oeffentliche Channel-Webhook ist nicht fail-closed oder akzeptiert Secrets aus GET-URLs.', 8);
}

echo "OK shop integrity\n";
