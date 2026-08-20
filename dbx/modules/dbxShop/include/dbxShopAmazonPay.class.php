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

   public function is_configured(): bool {
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

   public function region_code(): string {
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

   private function api_base(): string {
      $cfg = $this->config();
      return 'https://' . $this->host() . '/' . (string)$cfg['mode'] . '/v2';
   }

   private function normalize_header_value(string $value): string {
      return trim(preg_replace('/\s+/', ' ', $value) ?: $value);
   }

   private function canonical_uri(string $path): string {
      $parts = explode('/', $path);
      foreach ($parts as &$part) {
         $part = rawurlencode(rawurldecode($part));
      }
      unset($part);
      return implode('/', $parts);
   }

   private function canonical_query(string $query): string {
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

   private function authorization_header(string $method, string $path, array $headers, string $body): string {
      $cfg = $this->config();
      $normalized = array();
      foreach ($headers as $name => $value) {
         $normalized[strtolower((string)$name)] = $this->normalize_header_value((string)$value);
      }
      ksort($normalized, SORT_STRING);

      $canonical_headers = '';
      foreach ($normalized as $name => $value) {
         $canonical_headers .= $name . ':' . $value . "\n";
      }
      $signed_headers = implode(';', array_keys($normalized));
      $path_parts = parse_url($path);
      $canonical_uri = $this->canonical_uri((string)($path_parts['path'] ?? $path));
      $canonical_query = $this->canonical_query((string)($path_parts['query'] ?? ''));
      $canonical_request = strtoupper($method) . "\n"
         . $canonical_uri . "\n"
         . $canonical_query . "\n"
         . $canonical_headers . "\n"
         . $signed_headers . "\n"
         . hash('sha256', $body);

      $string_to_sign = self::ALGORITHM . "\n" . hash('sha256', $canonical_request);
      $private_key = PublicKeyLoader::loadPrivateKey((string)$cfg['private_key'])
         ->withHash('sha256')
         ->withMGFHash('sha256')
         ->withSaltLength(32)
         ->withPadding(RSA::SIGNATURE_PSS);
      $signature = base64_encode($private_key->sign($string_to_sign));

      return self::ALGORITHM
         . ' PublicKeyId=' . (string)$cfg['public_key_id']
         . ', SignedHeaders=' . $signed_headers
         . ', Signature=' . $signature;
   }

   private function request(string $method, string $path, ?array $payload = null, string $idempotency_key = ''): array {
      if (!$this->is_configured()) {
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
         'x-amz-pay-region' => $this->region_code(),
      );
      if ($idempotency_key !== '') {
         $headers['x-amz-pay-idempotency-key'] = substr(hash('sha256', $idempotency_key), 0, 32);
      }
      if ((string)$cfg['mode'] === 'sandbox' && (string)$cfg['sandbox_simulation_code'] !== '') {
         $headers['x-amz-simulation-code'] = (string)$cfg['sandbox_simulation_code'];
      }
      $headers['authorization'] = $this->authorization_header($method, '/' . (string)$cfg['mode'] . '/v2' . $path, $headers, $body);

      $http_headers = array();
      foreach ($headers as $name => $value) {
         $http_headers[] = $name . ': ' . $value;
      }

      $ch = curl_init($this->api_base() . $path);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
      curl_setopt($ch, CURLOPT_TIMEOUT, 25);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
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

   public function create_checkout_session(array $order, string $return_url, string $cancel_url): array {
      $cfg = $this->config();
      $amount = number_format((float)($order['total_gross'] ?? 0), 2, '.', '');
      $currency = (string)($order['currency'] ?? $cfg['currency'] ?? 'EUR');
      $payload = array(
         'webCheckoutDetails' => array(
            'checkoutReviewReturnUrl' => $return_url,
            'checkoutResultReturnUrl' => $return_url,
            'checkoutCancelUrl' => $cancel_url,
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

   public function complete_checkout_session(string $checkout_session_id, array $order): array {
      $amount = number_format((float)($order['total_gross'] ?? 0), 2, '.', '');
      $currency = (string)($order['currency'] ?? $this->config()['currency'] ?? 'EUR');
      return $this->request('POST', '/checkoutSessions/' . rawurlencode($checkout_session_id) . '/complete', array(
         'chargeAmount' => array(
            'amount' => $amount,
            'currencyCode' => $currency,
         ),
      ), 'complete|' . $checkout_session_id);
   }

   /**
    * Verifiziert eine serverseitige Amazon-Pay-Antwort und normalisiert
    * ausschliesslich dokumentierte Erfolgszustaende.
    */
   public function validate_completion(array $result, array $order, string $checkout_session_id): string {
      $returned_id = (string)($result['checkoutSessionId'] ?? $result['id'] ?? '');
      if ($checkout_session_id === '' || !hash_equals($checkout_session_id, $returned_id)) {
         throw new \RuntimeException('Amazon-Pay-Antwort gehoert nicht zur erwarteten Checkout Session.');
      }

      $order_no = (string)($order['order_no'] ?? '');
      $merchant_reference = (string)($result['merchantMetadata']['merchantReferenceId'] ?? '');
      if ($order_no === '' || $merchant_reference === '' || !hash_equals($order_no, $merchant_reference)) {
         throw new \RuntimeException('Amazon-Pay-Antwort ist nicht an die lokale Bestellnummer gebunden.');
      }

      $amount = $result['paymentDetails']['chargeAmount'] ?? ($result['chargeAmount'] ?? array());
      $actual_amount = round((float)($amount['amount'] ?? 0), 2);
      $actual_currency = strtoupper((string)($amount['currencyCode'] ?? ''));
      $expected_amount = round((float)($order['total_gross'] ?? 0), 2);
      $expected_currency = strtoupper((string)($order['currency'] ?? 'EUR'));
      if ($actual_currency !== $expected_currency || abs($actual_amount - $expected_amount) > 0.001) {
         throw new \RuntimeException('Amazon-Pay-Betrag oder Waehrung stimmt nicht mit der Bestellung ueberein.');
      }

      $http_status = (int)($result['_http_status'] ?? 0);
      $state = strtolower((string)($result['statusDetails']['state'] ?? ''));
      if ($http_status === 202 && $state === 'authorizationinitiated') {
         return 'pending';
      }
      if ($http_status >= 200 && $http_status < 300 && $state === 'completed') {
         return 'completed';
      }
      throw new \RuntimeException('Amazon Pay hat keinen gueltigen Abschlussstatus geliefert.');
   }

   public function redirect_url(array $checkout_session): string {
      $details = is_array($checkout_session['webCheckoutDetails'] ?? null) ? $checkout_session['webCheckoutDetails'] : array();
      foreach (array('amazonPayRedirectUrl', 'checkoutUrl') as $key) {
         if (!empty($details[$key])) {
            return (string)$details[$key];
         }
      }
      return '';
   }

   public function test_connection(): array {
      if (!$this->is_configured()) {
         return array(
            'ok' => false,
            'mode' => $this->mode(),
            'region' => $this->region_code(),
            'message' => 'Amazon Pay ist nicht vollstaendig konfiguriert.',
         );
      }

      try {
         $this->authorization_header('GET', '/' . $this->mode() . '/v2/checkoutSessions/test', array(
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'x-amz-pay-date' => gmdate('Ymd\THis\Z'),
            'x-amz-pay-host' => $this->host(),
            'x-amz-pay-region' => $this->region_code(),
         ), '');
         return array(
            'ok' => true,
            'mode' => $this->mode(),
            'region' => $this->region_code(),
            'message' => 'Amazon-Pay-Konfiguration und RSA-PSS-Signatur sind lokal gueltig. Der Live-API-Test erfolgt bei einer echten Checkout Session.',
         );
      } catch (\Throwable $e) {
         return array(
            'ok' => false,
            'mode' => $this->mode(),
            'region' => $this->region_code(),
            'message' => $e->getMessage(),
         );
      }
   }
}
?>
