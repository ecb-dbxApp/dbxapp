<?php
namespace dbx\dbxShop;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class dbxShopAmazonPay {

   private const ALGORITHM = 'AMZN-PAY-RSASSA-PSS-V2';

   private function config(): array {
      $cfg = function_exists('dbx') ? dbx()->get_cfg('dbxShop') : array();
      $cfg = is_array($cfg) ? $cfg : array();
      return array(
         'enabled' => !empty($cfg['payment_amazon_pay_enabled']),
         'mode' => (string)($cfg['payment_amazon_pay_mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox',
         'region' => in_array((string)($cfg['payment_amazon_pay_region'] ?? 'EU'), array('EU', 'UK', 'US', 'JP'), true) ? (string)$cfg['payment_amazon_pay_region'] : 'EU',
         'merchant_id' => trim((string)($cfg['payment_amazon_pay_merchant_id'] ?? '')),
         'store_id' => trim((string)($cfg['payment_amazon_pay_store_id'] ?? '')),
         'public_key_id' => trim((string)($cfg['payment_amazon_pay_public_key_id'] ?? '')),
         'private_key' => trim((string)($cfg['payment_amazon_pay_private_key'] ?? '')),
         'currency' => strtoupper(substr((string)($cfg['default_currency'] ?? 'EUR'), 0, 3)) ?: 'EUR',
         'sandbox_simulation_code' => trim((string)($cfg['payment_amazon_pay_sandbox_simulation_code'] ?? '')),
      );
   }

   public function isConfigured(): bool {
      $cfg = $this->config();
      return !empty($cfg['enabled'])
         && (string)$cfg['merchant_id'] !== ''
         && (string)$cfg['store_id'] !== ''
         && (string)$cfg['public_key_id'] !== ''
         && (string)$cfg['private_key'] !== '';
   }

   public function mode(): string {
      return (string)$this->config()['mode'];
   }

   public function regionCode(): string {
      $region = (string)$this->config()['region'];
      if ($region === 'US') return 'na';
      if ($region === 'JP') return 'jp';
      return 'eu';
   }

   private function host(): string {
      $region = (string)$this->config()['region'];
      if ($region === 'JP') return 'pay-api.amazon.jp';
      if ($region === 'EU' || $region === 'UK') return 'pay-api.amazon.eu';
      return 'pay-api.amazon.com';
   }

   private function apiBase(): string {
      $cfg = $this->config();
      return 'https://' . $this->host() . '/' . (string)$cfg['mode'] . '/v2';
   }

   private function normalizeHeaderValue(string $value): string {
      return trim(preg_replace('/\s+/', ' ', $value) ?: $value);
   }

   private function canonicalUri(string $path): string {
      $parts = explode('/', $path);
      foreach ($parts as &$part) {
         $part = rawurlencode(rawurldecode($part));
      }
      unset($part);
      return implode('/', $parts);
   }

   private function canonicalQuery(string $query): string {
      if ($query === '') {
         return '';
      }
      parse_str($query, $params);
      ksort($params, SORT_STRING);
      $pairs = array();
      foreach ($params as $key => $value) {
         if (is_array($value)) {
            sort($value, SORT_STRING);
            foreach ($value as $item) {
               $pairs[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$item);
            }
         } else {
            $pairs[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
         }
      }
      return implode('&', $pairs);
   }

   private function authorizationHeader(string $method, string $path, array $headers, string $body): string {
      $cfg = $this->config();
      $normalized = array();
      foreach ($headers as $name => $value) {
         $normalized[strtolower((string)$name)] = $this->normalizeHeaderValue((string)$value);
      }
      ksort($normalized, SORT_STRING);

      $canonicalHeaders = '';
      foreach ($normalized as $name => $value) {
         $canonicalHeaders .= $name . ':' . $value . "\n";
      }
      $signedHeaders = implode(';', array_keys($normalized));
      $pathParts = parse_url($path);
      $canonicalUri = $this->canonicalUri((string)($pathParts['path'] ?? $path));
      $canonicalQuery = $this->canonicalQuery((string)($pathParts['query'] ?? ''));
      $canonicalRequest = strtoupper($method) . "\n"
         . $canonicalUri . "\n"
         . $canonicalQuery . "\n"
         . $canonicalHeaders . "\n"
         . $signedHeaders . "\n"
         . hash('sha256', $body);

      $stringToSign = self::ALGORITHM . "\n" . hash('sha256', $canonicalRequest);
      $privateKey = PublicKeyLoader::loadPrivateKey((string)$cfg['private_key'])
         ->withHash('sha256')
         ->withMGFHash('sha256')
         ->withSaltLength(32)
         ->withPadding(RSA::SIGNATURE_PSS);
      $signature = base64_encode($privateKey->sign($stringToSign));

      return self::ALGORITHM
         . ' PublicKeyId=' . (string)$cfg['public_key_id']
         . ', SignedHeaders=' . $signedHeaders
         . ', Signature=' . $signature;
   }

   private function request(string $method, string $path, ?array $payload = null, string $idempotencyKey = ''): array {
      if (!$this->isConfigured()) {
         throw new \RuntimeException('Amazon Pay ist nicht vollstaendig konfiguriert.');
      }

      $cfg = $this->config();
      $body = $payload !== null ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
      if (!is_string($body)) {
         $body = '';
      }

      $headers = array(
         'accept' => 'application/json',
         'content-type' => 'application/json',
         'x-amz-pay-date' => gmdate('Ymd\THis\Z'),
         'x-amz-pay-host' => $this->host(),
         'x-amz-pay-region' => $this->regionCode(),
      );
      if ($idempotencyKey !== '') {
         $headers['x-amz-pay-idempotency-key'] = substr(hash('sha256', $idempotencyKey), 0, 32);
      }
      if ((string)$cfg['mode'] === 'sandbox' && (string)$cfg['sandbox_simulation_code'] !== '') {
         $headers['x-amz-simulation-code'] = (string)$cfg['sandbox_simulation_code'];
      }
      $headers['authorization'] = $this->authorizationHeader($method, '/' . (string)$cfg['mode'] . '/v2' . $path, $headers, $body);

      $httpHeaders = array();
      foreach ($headers as $name => $value) {
         $httpHeaders[] = $name . ': ' . $value;
      }

      $ch = curl_init($this->apiBase() . $path);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
      curl_setopt($ch, CURLOPT_TIMEOUT, 25);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
      if ($body !== '') {
         curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
      }

      $raw = curl_exec($ch);
      $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      $err = curl_error($ch);
      curl_close($ch);

      if ($raw === false || $err !== '') {
         throw new \RuntimeException('Amazon-Pay-Verbindung fehlgeschlagen: ' . $err);
      }

      $data = json_decode((string)$raw, true);
      if (!is_array($data)) {
         $data = array('raw' => (string)$raw);
      }
      $data['_http_status'] = $status;
      if ($status < 200 || $status >= 300) {
         $msg = $data['message'] ?? $data['reasonCode'] ?? $data['reasonDescription'] ?? ('HTTP ' . $status);
         throw new \RuntimeException('Amazon-Pay-Fehler: ' . $msg);
      }
      return $data;
   }

   public function createCheckoutSession(array $order, string $returnUrl, string $cancelUrl): array {
      $cfg = $this->config();
      $amount = number_format((float)($order['total_gross'] ?? 0), 2, '.', '');
      $currency = (string)($order['currency'] ?? $cfg['currency'] ?? 'EUR');
      $payload = array(
         'webCheckoutDetails' => array(
            'checkoutReviewReturnUrl' => $returnUrl,
            'checkoutResultReturnUrl' => $returnUrl,
            'checkoutCancelUrl' => $cancelUrl,
         ),
         'storeId' => (string)$cfg['store_id'],
         'scopes' => array('name', 'email', 'phoneNumber', 'billingAddress'),
         'paymentDetails' => array(
            'paymentIntent' => 'AuthorizeWithCapture',
            'canHandlePendingAuthorization' => false,
            'chargeAmount' => array(
               'amount' => $amount,
               'currencyCode' => $currency,
            ),
         ),
         'merchantMetadata' => array(
            'merchantReferenceId' => (string)($order['order_no'] ?? ''),
            'merchantStoreName' => (string)($cfg['merchant_id'] ?? ''),
            'noteToBuyer' => 'dbXapp Bestellung ' . (string)($order['order_no'] ?? ''),
         ),
      );
      return $this->request('POST', '/checkoutSessions', $payload, 'checkout|' . (string)($order['order_no'] ?? ''));
   }

   public function completeCheckoutSession(string $checkoutSessionId, array $order): array {
      $amount = number_format((float)($order['total_gross'] ?? 0), 2, '.', '');
      $currency = (string)($order['currency'] ?? $this->config()['currency'] ?? 'EUR');
      return $this->request('POST', '/checkoutSessions/' . rawurlencode($checkoutSessionId) . '/complete', array(
         'chargeAmount' => array(
            'amount' => $amount,
            'currencyCode' => $currency,
         ),
      ), 'complete|' . $checkoutSessionId);
   }

   /**
    * Verifiziert eine serverseitige Amazon-Pay-Antwort und normalisiert
    * ausschliesslich dokumentierte Erfolgszustaende.
    */
   public function validateCompletion(array $result, array $order, string $checkoutSessionId): string {
      $returnedId = (string)($result['checkoutSessionId'] ?? $result['id'] ?? '');
      if ($checkoutSessionId === '' || !hash_equals($checkoutSessionId, $returnedId)) {
         throw new \RuntimeException('Amazon-Pay-Antwort gehoert nicht zur erwarteten Checkout Session.');
      }

      $orderNo = (string)($order['order_no'] ?? '');
      $merchantReference = (string)($result['merchantMetadata']['merchantReferenceId'] ?? '');
      if ($orderNo === '' || $merchantReference === '' || !hash_equals($orderNo, $merchantReference)) {
         throw new \RuntimeException('Amazon-Pay-Antwort ist nicht an die lokale Bestellnummer gebunden.');
      }

      $amount = $result['paymentDetails']['chargeAmount'] ?? ($result['chargeAmount'] ?? array());
      $actualAmount = round((float)($amount['amount'] ?? 0), 2);
      $actualCurrency = strtoupper((string)($amount['currencyCode'] ?? ''));
      $expectedAmount = round((float)($order['total_gross'] ?? 0), 2);
      $expectedCurrency = strtoupper((string)($order['currency'] ?? 'EUR'));
      if ($actualCurrency !== $expectedCurrency || abs($actualAmount - $expectedAmount) > 0.001) {
         throw new \RuntimeException('Amazon-Pay-Betrag oder Waehrung stimmt nicht mit der Bestellung ueberein.');
      }

      $httpStatus = (int)($result['_http_status'] ?? 0);
      $state = strtolower((string)($result['statusDetails']['state'] ?? ''));
      if ($httpStatus === 202 && $state === 'authorizationinitiated') {
         return 'pending';
      }
      if ($httpStatus >= 200 && $httpStatus < 300 && $state === 'completed') {
         return 'completed';
      }
      throw new \RuntimeException('Amazon Pay hat keinen gueltigen Abschlussstatus geliefert.');
   }

   public function redirectUrl(array $checkoutSession): string {
      $details = is_array($checkoutSession['webCheckoutDetails'] ?? null) ? $checkoutSession['webCheckoutDetails'] : array();
      foreach (array('amazonPayRedirectUrl', 'checkoutUrl') as $key) {
         if (!empty($details[$key])) {
            return (string)$details[$key];
         }
      }
      return '';
   }

   public function testConnection(): array {
      if (!$this->isConfigured()) {
         return array(
            'ok' => false,
            'mode' => $this->mode(),
            'region' => $this->regionCode(),
            'message' => 'Amazon Pay ist nicht vollstaendig konfiguriert.',
         );
      }

      try {
         $this->authorizationHeader('GET', '/' . $this->mode() . '/v2/checkoutSessions/test', array(
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'x-amz-pay-date' => gmdate('Ymd\THis\Z'),
            'x-amz-pay-host' => $this->host(),
            'x-amz-pay-region' => $this->regionCode(),
         ), '');
         return array(
            'ok' => true,
            'mode' => $this->mode(),
            'region' => $this->regionCode(),
            'message' => 'Amazon-Pay-Konfiguration und RSA-PSS-Signatur sind lokal gueltig. Der Live-API-Test erfolgt bei einer echten Checkout Session.',
         );
      } catch (\Throwable $e) {
         return array(
            'ok' => false,
            'mode' => $this->mode(),
            'region' => $this->regionCode(),
            'message' => $e->getMessage(),
         );
      }
   }
}
?>
