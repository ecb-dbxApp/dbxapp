<?php

require_once dirname(__DIR__) . '/include/dbxShopPayPal.class.php';
require_once dirname(__DIR__) . '/include/dbxShopAmazonPay.class.php';

use dbx\dbxShop\dbxShopAmazonPay;
use dbx\dbxShop\dbxShopPayPal;

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$order = array(
   'id' => 7,
   'order_no' => 'S-SECURITY-1',
   'total_gross' => 119.90,
   'currency' => 'EUR',
);

$paypal_result = array(
   'id' => 'PAYPAL-ORDER-1',
   'status' => 'COMPLETED',
   'purchase_units' => array(array(
      'reference_id' => 'S-SECURITY-1',
      'invoice_id' => 'S-SECURITY-1',
      'payments' => array('captures' => array(array(
         'id' => 'CAPTURE-1',
         'status' => 'COMPLETED',
         'amount' => array('value' => '119.90', 'currency_code' => 'EUR'),
      ))),
   )),
);

$paypal = new dbxShopPayPal();
try {
   $paypal->validate_capture($paypal_result, $order, 'PAYPAL-ORDER-1');
} catch (Throwable $e) {
   $fail('Gueltiger PayPal-Capture wurde abgelehnt: ' . $e->getMessage(), 1);
}

$bad_pay_pal = $paypal_result;
$bad_pay_pal['purchase_units'][0]['payments']['captures'][0]['amount']['value'] = '19.90';
try {
   $paypal->validate_capture($bad_pay_pal, $order, 'PAYPAL-ORDER-1');
   $fail('PayPal-Betragsabweichung wurde akzeptiert.', 2);
} catch (RuntimeException $e) {
   // Erwartet.
}

$amazon_result = array(
   '_http_status' => 200,
   'checkoutSessionId' => 'AMAZON-SESSION-1',
   'statusDetails' => array('state' => 'Completed'),
   'merchantMetadata' => array('merchantReferenceId' => 'S-SECURITY-1'),
   'paymentDetails' => array(
      'chargeAmount' => array('amount' => '119.90', 'currencyCode' => 'EUR'),
   ),
);

$amazon = new dbxShopAmazonPay();
try {
   $status = $amazon->validate_completion($amazon_result, $order, 'AMAZON-SESSION-1');
   if ($status !== 'completed') $fail('Amazon-Pay-Status wurde falsch normalisiert.', 3);
} catch (Throwable $e) {
   $fail('Gueltige Amazon-Pay-Antwort wurde abgelehnt: ' . $e->getMessage(), 4);
}

$bad_amazon = $amazon_result;
$bad_amazon['merchantMetadata']['merchantReferenceId'] = 'ANDERE-BESTELLUNG';
try {
   $amazon->validate_completion($bad_amazon, $order, 'AMAZON-SESSION-1');
   $fail('Amazon-Pay-Antwort einer anderen Bestellung wurde akzeptiert.', 5);
} catch (RuntimeException $e) {
   // Erwartet.
}

$paypal_source = file_get_contents(dirname(__DIR__) . '/include/dbxShopPayPal.class.php');
$amazon_source = file_get_contents(dirname(__DIR__) . '/include/dbxShopAmazonPay.class.php');
if (strpos((string)$paypal_source, 'PayPal-Request-Id: dbx-') === false
   || strpos((string)$amazon_source, "'checkout|' . (string)(\$order['order_no'] ?? '')") === false) {
   $fail('Provider-Create/Capture-Aufrufe besitzen keine stabile Idempotenz-ID.', 6);
}

echo "OK shop payment validation\n";
