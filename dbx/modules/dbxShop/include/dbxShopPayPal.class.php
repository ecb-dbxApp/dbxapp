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
         $shop_cfg = dbx()->get_cfg('dbxShop');
         if (is_array($shop_cfg)) {
            $paypal = array_merge($paypal, array(
               'enabled' => !empty($shop_cfg['payment_paypal_enabled']),
               'mode' => (string)($shop_cfg['payment_paypal_mode'] ?? ($paypal['mode'] ?? 'sandbox')),
               'client_id' => (string)($shop_cfg['payment_paypal_client_id'] ?? ($paypal['client_id'] ?? '')),
               'client_secret' => (string)($shop_cfg['payment_paypal_client_secret'] ?? ($paypal['client_secret'] ?? '')),
               'brand_name' => (string)($shop_cfg['payment_paypal_brand_name'] ?? ($paypal['brand_name'] ?? 'dbXapp')),
               'currency' => (string)($shop_cfg['payment_paypal_currency'] ?? $shop_cfg['default_currency'] ?? ($paypal['currency'] ?? 'EUR')),
            ));
         }
      }

      return $paypal;
   }

   public function is_configured(): bool {
      $cfg = $this->config();
      return !empty($cfg['enabled'])
         && trim((string)($cfg['client_id'] ?? '')) !== ''
         && trim((string)($cfg['client_secret'] ?? '')) !== '';
   }

   public function mode(): string {
      $cfg = $this->config();
      return (string)($cfg['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox';
   }

   public function config_hint(): string {
      return 'PayPal ist vorbereitet. Aktivieren Sie PayPal unter Shop > Einstellungen und tragen Sie Client-ID und Secret ein.';
   }

   private function api_base(): string {
      $cfg = $this->config();
      return (string)($cfg['mode'] ?? 'sandbox') === 'live'
         ? 'https://api-m.paypal.com'
         : 'https://api-m.sandbox.paypal.com';
   }

   private function request(string $method, string $path, array $headers = array(), ?array $payload = null, ?string $basic_user = null, ?string $basic_password = null): array {
      $ch = curl_init($this->api_base() . $path);
      $method = strtoupper($method);
      $body = $payload !== null ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
      $http_headers = $headers;

      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($ch, CURLOPT_TIMEOUT, 25);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $http_headers);
      if ($basic_user !== null) {
         curl_setopt($ch, CURLOPT_USERPWD, $basic_user . ':' . (string)$basic_password);
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

   private function access_token(): string {
      $cfg = $this->config();
      $client_id = trim((string)($cfg['client_id'] ?? ''));
      $secret = trim((string)($cfg['client_secret'] ?? ''));
      if ($client_id === '' || $secret === '') {
         throw new \RuntimeException('PayPal-Zugangsdaten fehlen.');
      }

      $ch = curl_init($this->api_base() . '/v1/oauth2/token');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
      curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $secret);
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

   public function create_order(array $order, string $return_url, string $cancel_url): array {
      $cfg = $this->config();
      $token = $this->access_token();
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
                  'return_url' => $return_url,
                  'cancel_url' => $cancel_url,
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

   public function capture(string $paypal_order_id): array {
      $token = $this->access_token();
      return $this->request('POST', '/v2/checkout/orders/' . rawurlencode($paypal_order_id) . '/capture', array(
         'Content-Type: application/json',
         'Authorization: Bearer ' . $token,
         'Prefer: return=representation',
         'PayPal-Request-Id: dbx-' . substr(hash('sha256', 'capture|' . $paypal_order_id), 0, 32),
      ));
   }

   /**
    * Verifiziert die serverseitige Capture-Antwort gegen die lokale Bestellung.
    *
    * Ein erfolgreicher HTTP-Status allein ist kein Zahlungsnachweis: Referenz,
    * Bestellnummer, Capture-Status, Betrag und Waehrung muessen uebereinstimmen.
    */
   public function validate_capture(array $capture, array $order, string $paypal_order_id): void {
      if ($paypal_order_id === '' || !hash_equals($paypal_order_id, (string)($capture['id'] ?? ''))) {
         throw new \RuntimeException('PayPal-Capture gehoert nicht zur erwarteten Zahlungsreferenz.');
      }
      if (strtoupper((string)($capture['status'] ?? '')) !== 'COMPLETED') {
         throw new \RuntimeException('PayPal hat die Zahlung nicht als abgeschlossen bestaetigt.');
      }

      $order_no = (string)($order['order_no'] ?? '');
      $expected_currency = strtoupper((string)($order['currency'] ?? 'EUR'));
      $expected_amount = round((float)($order['total_gross'] ?? 0), 2);
      $matched_unit = false;
      $captured_amount = 0.0;
      $captured_currency = '';

      foreach ((array)($capture['purchase_units'] ?? array()) as $unit) {
         if (!is_array($unit)) continue;
         $captures = (array)($unit['payments']['captures'] ?? array());
         $unit_matches = (string)($unit['reference_id'] ?? '') === $order_no
            || (string)($unit['invoice_id'] ?? '') === $order_no;
         foreach ($captures as $item) {
            if (!is_array($item) || strtoupper((string)($item['status'] ?? '')) !== 'COMPLETED') continue;
            if ((string)($item['invoice_id'] ?? '') === $order_no) {
               $unit_matches = true;
            }
            if (!$unit_matches) continue;
            $captured_amount += (float)($item['amount']['value'] ?? 0);
            $currency = strtoupper((string)($item['amount']['currency_code'] ?? ''));
            if ($captured_currency !== '' && $currency !== $captured_currency) {
               throw new \RuntimeException('PayPal-Capture enthaelt unterschiedliche Waehrungen.');
            }
            $captured_currency = $currency;
            $matched_unit = true;
         }
      }

      if (!$matched_unit || $order_no === '') {
         throw new \RuntimeException('PayPal-Capture ist nicht an die lokale Bestellnummer gebunden.');
      }
      if ($captured_currency !== $expected_currency
         || abs(round($captured_amount, 2) - $expected_amount) > 0.001) {
         throw new \RuntimeException('PayPal-Capture-Betrag oder Waehrung stimmt nicht mit der Bestellung ueberein.');
      }
   }

   public function approval_url(array $paypal_order): string {
      foreach (($paypal_order['links'] ?? array()) as $link) {
         if (($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
            return (string)$link['href'];
         }
      }
      return '';
   }

   public function test_connection(): array {
      if (!$this->is_configured()) {
         return array(
            'ok' => false,
            'mode' => $this->mode(),
            'message' => 'PayPal ist nicht vollstaendig konfiguriert.',
         );
      }

      try {
         $token = $this->access_token();
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
