<?php
namespace dbx\dbxShop;

class dbxShopPayPal {

   private function config(): array {
      $file = dirname(__DIR__) . '/cfg/payment.php';
      $paypal = array();
      if (!is_file($file)) {
         $paypal = array();
      } else {
         $cfg = include $file;
         $paypal = is_array($cfg) ? ($cfg['paypal'] ?? array()) : array();
      }

      if (function_exists('dbx')) {
         $shopCfg = dbx()->get_config('dbxShop');
         if (is_array($shopCfg)) {
            $paypal = array_merge($paypal, array(
               'enabled' => !empty($shopCfg['payment_paypal_enabled']),
               'mode' => (string)($shopCfg['payment_paypal_mode'] ?? ($paypal['mode'] ?? 'sandbox')),
               'client_id' => (string)($shopCfg['payment_paypal_client_id'] ?? ($paypal['client_id'] ?? '')),
               'client_secret' => (string)($shopCfg['payment_paypal_client_secret'] ?? ($paypal['client_secret'] ?? '')),
               'brand_name' => (string)($shopCfg['payment_paypal_brand_name'] ?? ($paypal['brand_name'] ?? 'dbXapp')),
               'currency' => (string)($shopCfg['payment_paypal_currency'] ?? $shopCfg['default_currency'] ?? ($paypal['currency'] ?? 'EUR')),
            ));
         }
      }

      return $paypal;
   }

   public function isConfigured(): bool {
      $cfg = $this->config();
      return !empty($cfg['enabled'])
         && trim((string)($cfg['client_id'] ?? '')) !== ''
         && trim((string)($cfg['client_secret'] ?? '')) !== '';
   }

   public function mode(): string {
      $cfg = $this->config();
      return (string)($cfg['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
   }

   public function configHint(): string {
      return 'PayPal ist vorbereitet. Aktivieren Sie PayPal unter Shop > Einstellungen und tragen Sie Client-ID und Secret ein.';
   }

   private function apiBase(): string {
      $cfg = $this->config();
      return (string)($cfg['mode'] ?? 'sandbox') === 'live'
         ? 'https://api-m.paypal.com'
         : 'https://api-m.sandbox.paypal.com';
   }

   private function request(string $method, string $path, array $headers = array(), ?array $payload = null, ?string $basicUser = null, ?string $basicPassword = null): array {
      $ch = curl_init($this->apiBase() . $path);
      $method = strtoupper($method);
      $body = $payload !== null ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
      $httpHeaders = $headers;

      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($ch, CURLOPT_TIMEOUT, 25);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
      if ($basicUser !== null) {
         curl_setopt($ch, CURLOPT_USERPWD, $basicUser . ':' . (string)$basicPassword);
      }
      if ($body !== null) {
         curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
      }

      $raw = curl_exec($ch);
      $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      $err = curl_error($ch);
      curl_close($ch);

      if ($raw === false || $err !== '') {
         throw new \RuntimeException('PayPal-Verbindung fehlgeschlagen: ' . $err);
      }

      $data = json_decode((string)$raw, true);
      if (!is_array($data)) {
         $data = array('raw' => (string)$raw);
      }
      if ($status < 200 || $status >= 300) {
         $msg = $data['message'] ?? $data['name'] ?? ('HTTP ' . $status);
         throw new \RuntimeException('PayPal-Fehler: ' . $msg);
      }
      return $data;
   }

   private function accessToken(): string {
      $cfg = $this->config();
      $clientId = trim((string)($cfg['client_id'] ?? ''));
      $secret = trim((string)($cfg['client_secret'] ?? ''));
      if ($clientId === '' || $secret === '') {
         throw new \RuntimeException('PayPal-Zugangsdaten fehlen.');
      }

      $ch = curl_init($this->apiBase() . '/v1/oauth2/token');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
      curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $secret);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
         'Accept: application/json',
         'Accept-Language: de_DE',
         'Content-Type: application/x-www-form-urlencoded',
      ));
      curl_setopt($ch, CURLOPT_TIMEOUT, 25);
      $raw = curl_exec($ch);
      $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      $err = curl_error($ch);
      curl_close($ch);

      if ($raw === false || $err !== '') {
         throw new \RuntimeException('PayPal-Token konnte nicht geladen werden: ' . $err);
      }
      $data = json_decode((string)$raw, true);
      if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['access_token'])) {
         throw new \RuntimeException('PayPal-Token wurde abgelehnt.');
      }
      return (string)$data['access_token'];
   }

   public function createOrder(array $order, string $returnUrl, string $cancelUrl): array {
      $cfg = $this->config();
      $token = $this->accessToken();
      $currency = (string)($cfg['currency'] ?? $order['currency'] ?? 'EUR');
      $amount = number_format((float)($order['total_gross'] ?? 0), 2, '.', '');

      $payload = array(
         'intent' => 'CAPTURE',
         'purchase_units' => array(array(
            'reference_id' => (string)($order['order_no'] ?? ''),
            'description' => 'dbXapp Bestellung ' . (string)($order['order_no'] ?? ''),
            'invoice_id' => (string)($order['order_no'] ?? ''),
            'amount' => array(
               'currency_code' => $currency,
               'value' => $amount,
            ),
         )),
         'payment_source' => array(
            'paypal' => array(
               'experience_context' => array(
                  'brand_name' => (string)($cfg['brand_name'] ?? 'dbXapp'),
                  'locale' => 'de-DE',
                  'shipping_preference' => 'NO_SHIPPING',
                  'user_action' => 'PAY_NOW',
                  'return_url' => $returnUrl,
                  'cancel_url' => $cancelUrl,
               ),
            ),
         ),
      );

      return $this->request('POST', '/v2/checkout/orders', array(
         'Content-Type: application/json',
         'Authorization: Bearer ' . $token,
         'Prefer: return=representation',
         'PayPal-Request-Id: dbx-' . substr(hash('sha256', 'create|' . (string)($order['order_no'] ?? '')), 0, 32),
      ), $payload);
   }

   public function capture(string $paypalOrderId): array {
      $token = $this->accessToken();
      return $this->request('POST', '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture', array(
         'Content-Type: application/json',
         'Authorization: Bearer ' . $token,
         'Prefer: return=representation',
         'PayPal-Request-Id: dbx-' . substr(hash('sha256', 'capture|' . $paypalOrderId), 0, 32),
      ));
   }

   /**
    * Verifiziert die serverseitige Capture-Antwort gegen die lokale Bestellung.
    *
    * Ein erfolgreicher HTTP-Status allein ist kein Zahlungsnachweis: Referenz,
    * Bestellnummer, Capture-Status, Betrag und Waehrung muessen uebereinstimmen.
    */
   public function validateCapture(array $capture, array $order, string $paypalOrderId): void {
      if ($paypalOrderId === '' || !hash_equals($paypalOrderId, (string)($capture['id'] ?? ''))) {
         throw new \RuntimeException('PayPal-Capture gehoert nicht zur erwarteten Zahlungsreferenz.');
      }
      if (strtoupper((string)($capture['status'] ?? '')) !== 'COMPLETED') {
         throw new \RuntimeException('PayPal hat die Zahlung nicht als abgeschlossen bestaetigt.');
      }

      $orderNo = (string)($order['order_no'] ?? '');
      $expectedCurrency = strtoupper((string)($order['currency'] ?? 'EUR'));
      $expectedAmount = round((float)($order['total_gross'] ?? 0), 2);
      $matchedUnit = false;
      $capturedAmount = 0.0;
      $capturedCurrency = '';

      foreach ((array)($capture['purchase_units'] ?? array()) as $unit) {
         if (!is_array($unit)) continue;
         $captures = (array)($unit['payments']['captures'] ?? array());
         $unitMatches = (string)($unit['reference_id'] ?? '') === $orderNo
            || (string)($unit['invoice_id'] ?? '') === $orderNo;
         foreach ($captures as $item) {
            if (!is_array($item) || strtoupper((string)($item['status'] ?? '')) !== 'COMPLETED') continue;
            if ((string)($item['invoice_id'] ?? '') === $orderNo) {
               $unitMatches = true;
            }
            if (!$unitMatches) continue;
            $capturedAmount += (float)($item['amount']['value'] ?? 0);
            $currency = strtoupper((string)($item['amount']['currency_code'] ?? ''));
            if ($capturedCurrency !== '' && $currency !== $capturedCurrency) {
               throw new \RuntimeException('PayPal-Capture enthaelt unterschiedliche Waehrungen.');
            }
            $capturedCurrency = $currency;
            $matchedUnit = true;
         }
      }

      if (!$matchedUnit || $orderNo === '') {
         throw new \RuntimeException('PayPal-Capture ist nicht an die lokale Bestellnummer gebunden.');
      }
      if ($capturedCurrency !== $expectedCurrency
         || abs(round($capturedAmount, 2) - $expectedAmount) > 0.001) {
         throw new \RuntimeException('PayPal-Capture-Betrag oder Waehrung stimmt nicht mit der Bestellung ueberein.');
      }
   }

   public function approvalUrl(array $paypalOrder): string {
      foreach (($paypalOrder['links'] ?? array()) as $link) {
         if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
            return (string)$link['href'];
         }
      }
      return '';
   }

   public function testConnection(): array {
      if (!$this->isConfigured()) {
         return array(
            'ok' => false,
            'mode' => $this->mode(),
            'message' => 'PayPal ist nicht vollstaendig konfiguriert.',
         );
      }

      try {
         $token = $this->accessToken();
         return array(
            'ok' => $token !== '',
            'mode' => $this->mode(),
            'message' => $token !== ''
               ? 'PayPal OAuth Token wurde erfolgreich geladen.'
               : 'PayPal OAuth Token ist leer.',
         );
      } catch (\Throwable $e) {
         return array(
            'ok' => false,
            'mode' => $this->mode(),
            'message' => $e->getMessage(),
         );
      }
   }
}
?>
