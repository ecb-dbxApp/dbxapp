<?php

require_once dirname(__DIR__) . '/include/dbxShopRepository.class.php';

$repo = new \dbx\dbxShop\dbxShopRepository();
$method = new ReflectionMethod($repo, 'isStalePaymentProcessing');
$method->setAccessible(true);
$now = strtotime('2026-07-24 12:00:00');

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

if ($method->invoke($repo, array(
   'payment_status' => 'created',
   'update_date' => '2026-07-24 11:00:00',
), 300, $now)) {
   $fail('Ein nicht laufender Status wurde als verwaister Claim bewertet.', 1);
}

if ($method->invoke($repo, array(
   'payment_status' => 'processing',
   'update_date' => '2026-07-24 11:59:01',
), 300, $now)) {
   $fail('Ein frischer processing-Claim wurde zu frueh freigegeben.', 2);
}

if (!$method->invoke($repo, array(
   'payment_status' => 'processing',
   'update_date' => '2026-07-24 11:55:00',
), 300, $now)) {
   $fail('Ein abgelaufener processing-Claim wurde nicht freigegeben.', 3);
}

if (!$method->invoke($repo, array(
   'payment_status' => 'PROCESSING',
   'update_date' => '2026-07-24 11:59:00',
), 1, $now)) {
   $fail('Die Mindest-Leasezeit von 60 Sekunden ist nicht wirksam.', 4);
}

if ($method->invoke($repo, array(
   'payment_status' => 'processing',
   'update_date' => '',
), 300, $now)) {
   $fail('Ein Claim ohne belastbaren Zeitstempel wurde freigegeben.', 5);
}

echo "OK shop payment claim policy\n";
