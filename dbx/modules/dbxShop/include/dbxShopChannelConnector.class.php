<?php
namespace dbx\dbxShop;

class dbxShopChannelConnector {

   public function test(array $channel): array {
      $platform = strtolower(trim((string)($channel['platform_type'] ?? $channel['channel_key'] ?? 'custom')));
      if ($platform === 'shop') {
         return array('ok' => true, 'message' => 'Interner Shop-Channel ist verfuegbar.');
      }
      if ($platform === 'ebay') {
         return $this->test_ebay($channel);
      }
      if ($platform === 'amazon') {
         return $this->test_amazon($channel);
      }
      if ($platform === 'mobile') {
         return $this->test_mobile($channel);
      }
      if ($platform === 'kleinanzeigen') {
         return $this->test_kleinanzeigen($channel);
      }
      return $this->test_generic($channel);
   }

   public function normalize_webhook_payload(array $channel, array $payload): array {
      $platform = strtolower(trim((string)($channel['platform_type'] ?? $channel['channel_key'] ?? 'custom')));
      if ($platform === 'ebay') {
         return $this->normalize_ebay_payload($payload);
      }
      if ($platform === 'amazon') {
         return $this->normalize_amazon_payload($payload);
      }
      if ($platform === 'mobile') {
         return $this->normalize_mobile_payload($payload);
      }
      return $payload;
   }

   public function export_product(array $channel, array $product, array $product_channel = array()): array {
      $platform = strtolower(trim((string)($channel['platform_type'] ?? $channel['channel_key'] ?? 'custom')));
      if ($platform === 'shop') {
         return array(
            'ok' => true,
            'status' => 'ready',
            'message' => 'Interner Shop-Channel: kein externer Export notwendig.',
            'payload' => $this->standard_product_payload($channel, $product, $product_channel),
         );
      }
      if ($platform === 'ebay') {
         return $this->export_ebay_product($channel, $product, $product_channel);
      }
      if ($platform === 'amazon') {
         return $this->export_amazon_product($channel, $product, $product_channel);
      }
      if ($platform === 'mobile') {
         return $this->export_mobile_product($channel, $product, $product_channel);
      }
      if ($platform === 'kleinanzeigen') {
         return $this->export_kleinanzeigen_product($channel, $product, $product_channel);
      }
      return $this->export_middleware_product($channel, $product, $product_channel, 'custom');
   }

   private function missing(array $channel, array $fields): array {
      $missing = array();
      foreach ($fields as $field => $label) {
         if (trim((string)($channel[$field] ?? '')) === '') {
            $missing[] = $label;
         }
      }
      return $missing;
   }

   private function scopes(string $value): array {
      return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $value) ?: array())));
   }

   private function base_url(array $channel, string $fallback): string {
      $base_url = trim((string)($channel['api_base_url'] ?? ''));
      return rtrim($base_url !== '' ? $base_url : $fallback, '/');
   }

   private function curl(string $method, string $url, array $headers = array(), ?string $body = null, ?string $user = null, ?string $password = null): array {
      if (!function_exists('curl_init')) {
         throw new \RuntimeException('cURL ist in PHP nicht verfuegbar.');
      }
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
      curl_setopt($ch, CURLOPT_TIMEOUT, 25);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_USERAGENT, 'dbxShop Channel Connector');
      if ($user !== null) {
         curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . (string)$password);
      }
      if ($body !== null) {
         curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
      }
      $raw = curl_exec($ch);
      $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      $err = (string)curl_error($ch);
      curl_close($ch);
      if ($raw === false || $err !== '') {
         throw new \RuntimeException($err !== '' ? $err : 'HTTP-Aufruf fehlgeschlagen.');
      }
      $json = json_decode((string)$raw, true);
      return array('status' => $status, 'raw' => (string)$raw, 'json' => is_array($json) ? $json : array());
   }

   private function json_request(string $method, string $url, array $headers, array $payload, ?string $user = null, ?string $password = null): array {
      $headers[] = 'Content-Type: application/json';
      $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if ($body === false) {
         throw new \RuntimeException('Payload konnte nicht als JSON erzeugt werden.');
      }
      return $this->curl($method, $url, $headers, $body, $user, $password);
   }

   private function product_sku(array $product, array $product_channel): string {
      $sku = trim((string)($product_channel['channel_sku'] ?? ''));
      if ($sku === '') {
         $sku = trim((string)($product['sku'] ?? ''));
      }
      return $sku;
   }

   private function channel_price(array $product, array $product_channel): float {
      $price = (float)($product_channel['price_gross'] ?? -1);
      return $price >= 0 ? $price : (float)($product['price_gross'] ?? 0);
   }

   private function channel_shipping(array $product, array $product_channel): float {
      $shipping = (float)($product_channel['shipping_gross'] ?? -1);
      return $shipping >= 0 ? $shipping : (float)($product['effective_shipping_gross'] ?? $product['shipping_gross'] ?? 0);
   }

   private function quantity(array $product): int {
      $type = (string)($product['product_type'] ?? '');
      if ($type === 'physical') {
         return max(0, (int)($product['stock'] ?? 0));
      }
      return 999;
   }

   private function currency(array $product): string {
      $currency = strtoupper(substr((string)($product['currency'] ?? 'EUR'), 0, 3));
      return $currency !== '' ? $currency : 'EUR';
   }

   private function image_urls(array $product): array {
      $base = function_exists('dbx') ? rtrim((string)dbx()->get_base_url(), '/') . '/' : '';
      $urls = array();
      foreach ((array)($product['images'] ?? array()) as $image) {
         $media_id = (int)($image['media_id'] ?? 0);
         if ($media_id > 0 && $base !== '') {
            $urls[] = $base . 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $media_id;
            continue;
         }
         $path = trim(str_replace('\\', '/', (string)($image['image_path'] ?? '')));
         if ($path === '') {
            continue;
         }
         if (preg_match('~^https?://~i', $path)) {
            $urls[] = $path;
         } elseif ($base !== '') {
            $urls[] = $base . ltrim($path, '/');
         }
      }
      return array_values(array_unique($urls));
   }

   private function aspects(array $product): array {
      $aspects = array();
      foreach ((array)($product['attributes'] ?? array()) as $attribute) {
         $key = trim((string)($attribute['title'] ?? $attribute['attr_key'] ?? ''));
         $value = trim((string)($attribute['display_value'] ?? $attribute['value_text'] ?? ''));
         if ($key !== '' && $value !== '') {
            $aspects[$key] = array($value);
         }
      }
      return $aspects;
   }

   private function product_channel_note_data(array $product_channel): array {
      $note = trim((string)($product_channel['note'] ?? ''));
      if ($note === '') {
         return array();
      }
      $data = json_decode($note, true);
      return is_array($data) ? $data : array();
   }

   private function product_group_channel_defaults(string $platform, array $product): array {
      $group = is_array(($product['groups'][0] ?? null)) ? $product['groups'][0] : array();
      if ($group === array()) {
         return array();
      }
      if ($platform === 'ebay') {
         $category = trim((string)($group['ebay_category_id'] ?? ''));
         return $category !== '' ? array('category_id' => $category) : array();
      }
      if ($platform === 'amazon') {
         $product_type = trim((string)($group['amazon_product_type'] ?? ''));
         return $product_type !== '' ? array('productType' => $product_type) : array();
      }
      if ($platform === 'kleinanzeigen') {
         $category = trim((string)($group['kleinanzeigen_category_id'] ?? ''));
         return $category !== '' ? array('category_id' => $category) : array();
      }
      if ($platform === 'mobile') {
         $category = trim((string)($group['mobile_category_id'] ?? ''));
         return $category !== '' ? array('mobile_vehicle' => array('category' => $category)) : array();
      }
      return array();
   }

   private function merge_defaults(array $defaults, array $values): array {
      foreach ($defaults as $key => $value) {
         if (is_array($value)) {
            $current = is_array($values[$key] ?? null) ? $values[$key] : array();
            $values[$key] = $this->merge_defaults($value, $current);
            continue;
         }
         if (!array_key_exists($key, $values) || trim((string)$values[$key]) === '') {
            $values[$key] = $value;
         }
      }
      return $values;
   }

   private function resolved_channel_note_data(string $platform, array $product, array $product_channel): array {
      return $this->merge_defaults($this->product_group_channel_defaults($platform, $product), $this->product_channel_note_data($product_channel));
   }

   private function standard_product_payload(array $channel, array $product, array $product_channel): array {
      $sku = $this->product_sku($product, $product_channel);
      $platform = strtolower(trim((string)($channel['platform_type'] ?? $channel['channel_key'] ?? 'custom')));
      $note_data = $this->resolved_channel_note_data($platform, $product, $product_channel);
      $category_id = (string)($note_data['category_id'] ?? $note_data['mobile_vehicle']['category'] ?? $channel['category_id'] ?? '');
      return array(
         'sku' => $sku,
         'title' => (string)($product['title'] ?? $sku),
         'summary' => (string)($product['summary'] ?? ''),
         'description' => (string)($product['description'] ?? $product['summary'] ?? ''),
         'price_gross' => $this->channel_price($product, $product_channel),
         'shipping_gross' => $this->channel_shipping($product, $product_channel),
         'currency' => $this->currency($product),
         'quantity' => $this->quantity($product),
         'product_type' => (string)($product['product_type'] ?? ''),
         'category' => (string)($product['category'] ?? ''),
         'category_id' => $category_id,
         'images' => $this->image_urls($product),
         'attributes' => $this->aspects($product),
         'channel_key' => (string)($channel['channel_key'] ?? ''),
      );
   }

   private function cut(string $value, int $length): string {
      if (function_exists('mb_substr')) {
         return mb_substr($value, 0, $length);
      }
      return substr($value, 0, $length);
   }

   private function test_ebay(array $channel): array {
      $missing = $this->missing($channel, array(
         'api_client_id' => 'Client-ID/App-ID',
         'api_client_secret' => 'Client-Secret/Cert-ID',
         'marketplace_id' => 'Marketplace-ID',
         'location_key' => 'Location-Key',
         'category_id' => 'Kategorie-ID',
         'payment_policy_id' => 'Payment-Policy',
         'fulfillment_policy_id' => 'Fulfillment-Policy',
         'return_policy_id' => 'Return-Policy',
      ));
      if (trim((string)($channel['api_refresh_token'] ?? '')) === '' && trim((string)($channel['api_access_token'] ?? '')) === '') {
         $missing[] = 'Refresh-Token oder Access-Token';
      }
      $scopes = $this->scopes((string)($channel['api_scope'] ?? ''));
      foreach (array('sell.inventory', 'sell.fulfillment') as $required) {
         $found = false;
         foreach ($scopes as $scope) {
            if (strpos($scope, $required) !== false) {
               $found = true;
               break;
            }
         }
         if (!$found) {
            $missing[] = 'Scope ' . $required;
         }
      }
      if ($missing !== array()) {
         return array('ok' => false, 'message' => 'eBay-Konfiguration unvollstaendig: ' . implode(', ', array_unique($missing)) . '.');
      }

      try {
         $token = trim((string)($channel['api_access_token'] ?? ''));
         if ($token === '') {
            $token = $this->ebay_refresh_access_token($channel, $scopes);
         }
         $base = $this->base_url($channel, 'https://api.ebay.com');
         $result = $this->curl('GET', $base . '/sell/inventory/v1/inventory_item?limit=1', array(
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Language: de-DE',
         ));
         $ok = $result['status'] >= 200 && $result['status'] < 300;
         return array(
            'ok' => $ok,
            'message' => $ok
               ? 'eBay Sell API erreichbar. Inventory/Fulfillment-Zugang ist grundsaetzlich nutzbar.'
               : 'eBay API antwortet mit HTTP ' . $result['status'] . '.',
         );
      } catch (\Throwable $e) {
         return array('ok' => false, 'message' => 'eBay-Test fehlgeschlagen: ' . $e->getMessage());
      }
   }

   private function ebay_refresh_access_token(array $channel, array $scopes): string {
      $base = $this->base_url($channel, 'https://api.ebay.com');
      $body = http_build_query(array(
         'grant_type' => 'refresh_token',
         'refresh_token' => trim((string)($channel['api_refresh_token'] ?? '')),
         'scope' => implode(' ', $scopes),
      ));
      $result = $this->curl('POST', $base . '/identity/v1/oauth2/token', array(
         'Content-Type: application/x-www-form-urlencoded',
         'Accept: application/json',
      ), $body, trim((string)($channel['api_client_id'] ?? '')), trim((string)($channel['api_client_secret'] ?? '')));
      if ($result['status'] < 200 || $result['status'] >= 300 || empty($result['json']['access_token'])) {
         throw new \RuntimeException('OAuth Refresh wurde abgelehnt, HTTP ' . $result['status'] . '.');
      }
      return (string)$result['json']['access_token'];
   }

   private function ebay_access_token(array $channel): string {
      $token = trim((string)($channel['api_access_token'] ?? ''));
      if ($token !== '') {
         return $token;
      }
      return $this->ebay_refresh_access_token($channel, $this->scopes((string)($channel['api_scope'] ?? '')));
   }

   private function export_ebay_product(array $channel, array $product, array $product_channel): array {
      $sku = $this->product_sku($product, $product_channel);
      $note_data = $this->resolved_channel_note_data('ebay', $product, $product_channel);
      $category_id = trim((string)($note_data['category_id'] ?? $channel['category_id'] ?? ''));
      $location_key = trim((string)($channel['location_key'] ?? ''));
      $payment_policy_id = trim((string)($note_data['payment_policy_id'] ?? $channel['payment_policy_id'] ?? ''));
      $fulfillment_policy_id = trim((string)($note_data['fulfillment_policy_id'] ?? $channel['fulfillment_policy_id'] ?? ''));
      $return_policy_id = trim((string)($note_data['return_policy_id'] ?? $channel['return_policy_id'] ?? ''));
      $check_channel = array_replace($channel, array(
         'category_id' => $category_id,
         'location_key' => $location_key,
         'payment_policy_id' => $payment_policy_id,
         'fulfillment_policy_id' => $fulfillment_policy_id,
         'return_policy_id' => $return_policy_id,
      ));
      $missing = $this->missing($check_channel, array(
         'api_client_id' => 'Client-ID/App-ID',
         'api_client_secret' => 'Client-Secret/Cert-ID',
         'marketplace_id' => 'Marketplace-ID',
         'location_key' => 'Location-Key',
         'category_id' => 'Kategorie-ID',
         'payment_policy_id' => 'Payment-Policy',
         'fulfillment_policy_id' => 'Fulfillment-Policy',
         'return_policy_id' => 'Return-Policy',
      ));
      if ($sku === '') $missing[] = 'Channel-SKU/Artikelnummer';
      if (trim((string)($channel['api_refresh_token'] ?? '')) === '' && trim((string)($channel['api_access_token'] ?? '')) === '') {
         $missing[] = 'Refresh-Token oder Access-Token';
      }
      $images = $this->image_urls($product);
      if ($images === array()) {
         $missing[] = 'mindestens ein oeffentlich erreichbares Artikelbild';
      }
      if ($missing !== array()) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'eBay-Export nicht moeglich: ' . implode(', ', array_unique($missing)) . '.',
            'payload' => $this->standard_product_payload($channel, $product, $product_channel),
         );
      }

      try {
         $token = $this->ebay_access_token($channel);
         $base = $this->base_url($channel, 'https://api.ebay.com');
         $headers = array(
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Language: de-DE',
         );
         $quantity = $this->quantity($product);
         $price = number_format($this->channel_price($product, $product_channel), 2, '.', '');
         $shipping = $this->channel_shipping($product, $product_channel);
         $currency = $this->currency($product);
         $description = trim((string)($product['description'] ?? $product['summary'] ?? ''));
         if ($description === '') {
            $description = (string)($product['title'] ?? $sku);
         }
         $inventory_payload = array(
            'availability' => array(
               'shipToLocationAvailability' => array('quantity' => max(0, $quantity)),
            ),
            'condition' => 'NEW',
            'product' => array(
               'title' => $this->cut((string)($product['title'] ?? $sku), 80),
               'description' => $this->cut($description, 4000),
               'imageUrls' => $images,
            ),
         );
         $aspects = $this->aspects($product);
         if (is_array($note_data['aspects'] ?? null)) {
            foreach ($note_data['aspects'] as $aspect_name => $aspect_value) {
               $aspect_name = trim((string)$aspect_name);
               if ($aspect_name === '') continue;
               $values = is_array($aspect_value) ? $aspect_value : array($aspect_value);
               $values = array_values(array_filter(array_map('strval', $values), fn($v) => trim($v) !== ''));
               if ($values !== array()) {
                  $aspects[$aspect_name] = $values;
               }
            }
         }
         if ($aspects !== array()) {
            $inventory_payload['product']['aspects'] = $aspects;
         }
         if (trim((string)($note_data['condition'] ?? '')) !== '') {
            $inventory_payload['condition'] = trim((string)$note_data['condition']);
         }
         $this->json_request('PUT', $base . '/sell/inventory/v1/inventory_item/' . rawurlencode($sku), $headers, $inventory_payload);

         $offer_payload = array(
            'sku' => $sku,
            'marketplaceId' => (string)$channel['marketplace_id'],
            'format' => 'FIXED_PRICE',
            'availableQuantity' => max(0, $quantity),
            'categoryId' => $category_id,
            'merchantLocationKey' => $location_key,
            'listingPolicies' => array(
               'fulfillmentPolicyId' => $fulfillment_policy_id,
               'paymentPolicyId' => $payment_policy_id,
               'returnPolicyId' => $return_policy_id,
            ),
            'pricingSummary' => array(
               'price' => array('value' => $price, 'currency' => $currency),
            ),
         );
         $offer_payload['listingDescription'] = $this->cut($description, 4000);
         $offer_id = trim((string)($product_channel['external_offer_id'] ?? ''));
         if ($offer_id !== '') {
            $this->json_request('PUT', $base . '/sell/inventory/v1/offer/' . rawurlencode($offer_id), $headers, $offer_payload);
         } else {
            $created = $this->json_request('POST', $base . '/sell/inventory/v1/offer', $headers, $offer_payload);
            $offer_id = (string)($created['json']['offerId'] ?? $created['json']['offer']['offerId'] ?? '');
         }
         if ($offer_id === '') {
            throw new \RuntimeException('eBay hat keine Offer-ID geliefert.');
         }

         $published = $this->json_request('POST', $base . '/sell/inventory/v1/offer/' . rawurlencode($offer_id) . '/publish', $headers, array());
         $listing_id = (string)($published['json']['listingId'] ?? $published['json']['listing']['listingId'] ?? $product_channel['external_listing_id'] ?? '');
         return array(
            'ok' => true,
            'status' => 'published',
            'message' => 'eBay-Angebot wurde exportiert und veroeffentlicht' . ($listing_id !== '' ? ' (Listing ' . $listing_id . ')' : '') . '.',
            'external_offer_id' => $offer_id,
            'external_listing_id' => $listing_id,
            'payload' => array('inventory' => $inventory_payload, 'offer' => $offer_payload, 'publish' => $published['json']),
         );
      } catch (\Throwable $e) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'eBay-Export fehlgeschlagen: ' . $e->getMessage(),
            'payload' => $this->standard_product_payload($channel, $product, $product_channel),
         );
      }
   }

   private function test_amazon(array $channel): array {
      $missing = $this->missing($channel, array(
         'api_client_id' => 'LWA Client-ID',
         'api_client_secret' => 'LWA Client-Secret',
         'api_refresh_token' => 'LWA Refresh-Token',
         'seller_id' => 'Seller-ID',
         'marketplace_id' => 'Marketplace-ID',
      ));
      if ($missing !== array()) {
         return array('ok' => false, 'message' => 'Amazon-SP-API-Konfiguration unvollstaendig: ' . implode(', ', $missing) . '.');
      }

      try {
         $token = $this->amazon_access_token($channel);
         $base = $this->base_url($channel, 'https://sellingpartnerapi-eu.amazon.com');
         $created_after = gmdate('Y-m-d\TH:i:s\Z', time() - 86400 * 7);
         $url = $base . '/orders/v0/orders?MarketplaceIds=' . rawurlencode((string)$channel['marketplace_id']) . '&CreatedAfter=' . rawurlencode($created_after);
         $result = $this->curl('GET', $url, array(
            'Accept: application/json',
            'x-amz-access-token: ' . $token,
         ));
         $ok = $result['status'] >= 200 && $result['status'] < 300;
         return array(
            'ok' => $ok,
            'message' => $ok
               ? 'Amazon SP-API erreichbar. LWA Token und Orders-Zugriff sind grundsaetzlich nutzbar.'
               : 'Amazon SP-API antwortet mit HTTP ' . $result['status'] . '.',
         );
      } catch (\Throwable $e) {
         return array('ok' => false, 'message' => 'Amazon-SP-API-Test fehlgeschlagen: ' . $e->getMessage());
      }
   }

   private function amazon_access_token(array $channel): string {
      $body = http_build_query(array(
         'grant_type' => 'refresh_token',
         'refresh_token' => trim((string)($channel['api_refresh_token'] ?? '')),
         'client_id' => trim((string)($channel['api_client_id'] ?? '')),
         'client_secret' => trim((string)($channel['api_client_secret'] ?? '')),
      ));
      $result = $this->curl('POST', 'https://api.amazon.com/auth/o2/token', array(
         'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
         'Accept: application/json',
      ), $body);
      if ($result['status'] < 200 || $result['status'] >= 300 || empty($result['json']['access_token'])) {
         throw new \RuntimeException('LWA Token wurde abgelehnt, HTTP ' . $result['status'] . '.');
      }
      return (string)$result['json']['access_token'];
   }

   private function amazon_product_type(array $channel, array $product_channel): string {
      $note = $this->product_channel_note_data($product_channel);
      $from_note = trim((string)($note['productType'] ?? $note['product_type'] ?? ''));
      if ($from_note !== '') {
         return $from_note;
      }
      $category = trim((string)($channel['category_id'] ?? ''));
      if (stripos($category, 'productType:') === 0) {
         $category = trim(substr($category, strlen('productType:')));
      }
      if (strpos($category, '/') !== false) {
         $category = trim((string)explode('/', $category)[0]);
      }
      return strtoupper(trim($category));
   }

   private function resolved_amazon_product_type(array $channel, array $product, array $product_channel): string {
      $note = $this->resolved_channel_note_data('amazon', $product, $product_channel);
      $from_note = trim((string)($note['productType'] ?? $note['product_type'] ?? ''));
      if ($from_note !== '') {
         return $from_note;
      }
      return $this->amazon_product_type($channel, $product_channel);
   }

   private function export_amazon_product(array $channel, array $product, array $product_channel): array {
      $sku = $this->product_sku($product, $product_channel);
      $product_type = $this->resolved_amazon_product_type($channel, $product, $product_channel);
      $missing = $this->missing($channel, array(
         'api_client_id' => 'LWA Client-ID',
         'api_client_secret' => 'LWA Client-Secret',
         'api_refresh_token' => 'LWA Refresh-Token',
         'seller_id' => 'Seller-ID',
         'marketplace_id' => 'Marketplace-ID',
      ));
      if ($sku === '') $missing[] = 'Channel-SKU/Artikelnummer';
      if ($product_type === '') $missing[] = 'Amazon Product Type';
      if ($missing !== array()) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'Amazon-Export nicht moeglich: ' . implode(', ', $missing) . '. Product Type und Pflichtattribute muessen zum Amazon-Schema passen.',
            'payload' => $this->standard_product_payload($channel, $product, $product_channel),
         );
      }

      $note_data = $this->resolved_channel_note_data('amazon', $product, $product_channel);
      $quantity = $this->quantity($product);
      $currency = $this->currency($product);
      $price = number_format($this->channel_price($product, $product_channel), 2, '.', '');
      $attributes = is_array($note_data['attributes'] ?? null) ? $note_data['attributes'] : array();
      if (is_array($note_data['simple_attributes'] ?? null)) {
         foreach ($note_data['simple_attributes'] as $attr_key => $attr_value) {
            $attr_key = trim((string)$attr_key);
            $attr_value = trim((string)$attr_value);
            if ($attr_key !== '' && $attr_value !== '' && !isset($attributes[$attr_key])) {
               $attributes[$attr_key] = array(array('value' => $attr_value, 'marketplace_id' => (string)$channel['marketplace_id']));
            }
         }
      }
      $attributes += array(
         'item_name' => array(array('value' => (string)($product['title'] ?? $sku), 'marketplace_id' => (string)$channel['marketplace_id'])),
         'product_description' => array(array('value' => (string)($product['description'] ?? $product['summary'] ?? ''), 'marketplace_id' => (string)$channel['marketplace_id'])),
         'bullet_point' => array(array('value' => (string)($product['summary'] ?? $product['title'] ?? $sku), 'marketplace_id' => (string)$channel['marketplace_id'])),
         'condition_type' => array(array('value' => 'new_new', 'marketplace_id' => (string)$channel['marketplace_id'])),
         'purchasable_offer' => array(array(
            'currency' => $currency,
            'our_price' => array(array('schedule' => array(array('value_with_tax' => (float)$price)))),
            'marketplace_id' => (string)$channel['marketplace_id'],
         )),
         'fulfillment_availability' => array(array(
            'fulfillment_channel_code' => 'DEFAULT',
            'quantity' => max(0, $quantity),
            'marketplace_id' => (string)$channel['marketplace_id'],
         )),
      );
      if ($this->image_urls($product) !== array()) {
         $attributes['main_product_image_locator'] = array(array('media_location' => $this->image_urls($product)[0], 'marketplace_id' => (string)$channel['marketplace_id']));
      }
      $payload = array(
         'productType' => $product_type,
         'requirements' => (string)($note_data['requirements'] ?? 'LISTING'),
         'attributes' => $attributes,
      );

      try {
         $token = $this->amazon_access_token($channel);
         $base = $this->base_url($channel, 'https://sellingpartnerapi-eu.amazon.com');
         $url = $base . '/listings/2021-08-01/items/' . rawurlencode((string)$channel['seller_id']) . '/' . rawurlencode($sku)
            . '?marketplaceIds=' . rawurlencode((string)$channel['marketplace_id']) . '&issueLocale=de_DE';
         $result = $this->json_request('PUT', $url, array(
            'Accept: application/json',
            'x-amz-access-token: ' . $token,
         ), $payload);
         $ok = $result['status'] >= 200 && $result['status'] < 300;
         return array(
            'ok' => $ok,
            'status' => $ok ? 'exported' : 'failed',
            'message' => $ok ? 'Amazon Listing wurde an die SP-API uebergeben. Amazon validiert die Product-Type-Pflichtattribute asynchron.' : 'Amazon SP-API antwortet mit HTTP ' . $result['status'] . '.',
            'external_listing_id' => $sku,
            'payload' => array('request' => $payload, 'response' => $result['json']),
         );
      } catch (\Throwable $e) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'Amazon-Export fehlgeschlagen: ' . $e->getMessage(),
            'payload' => $payload,
         );
      }
   }

   private function test_mobile(array $channel): array {
      $missing = $this->missing($channel, array(
         'api_username' => 'API-Benutzer',
         'api_password' => 'API-Passwort',
      ));
      if ($missing !== array()) {
         return array('ok' => false, 'message' => 'mobile.de-Konfiguration unvollstaendig: ' . implode(', ', $missing) . '.');
      }

      try {
         $base = $this->base_url($channel, 'https://services.mobile.de');
         if (preg_match('~/seller-api$~', $base)) {
            $base = substr($base, 0, -11);
         }
         $result = $this->curl('GET', $base . '/seller-api/sellers', array(
            'Accept: application/vnd.de.mobile.api+json',
         ), null, trim((string)($channel['api_username'] ?? '')), trim((string)($channel['api_password'] ?? '')));
         $ok = $result['status'] >= 200 && $result['status'] < 300;
         return array(
            'ok' => $ok,
            'message' => $ok
               ? 'mobile.de Seller API erreichbar. Basic-Auth-Zugang ist nutzbar.'
               : 'mobile.de Seller API antwortet mit HTTP ' . $result['status'] . '.',
         );
      } catch (\Throwable $e) {
         return array('ok' => false, 'message' => 'mobile.de-Test fehlgeschlagen: ' . $e->getMessage());
      }
   }

   private function test_kleinanzeigen(array $channel): array {
      $mode = strtolower((string)($channel['connection_mode'] ?? 'manual'));
      if ($mode === 'manual') {
         return array(
            'ok' => true,
            'message' => 'Kleinanzeigen ist als manueller/Partner-Channel konfiguriert. Eine frei nutzbare Standard-API wird hier nicht vorausgesetzt.',
         );
      }
      $base_url = trim((string)($channel['api_base_url'] ?? ''));
      $has_credentials = trim((string)($channel['api_client_id'] ?? $channel['api_username'] ?? '')) !== '';
      if ($base_url === '' || !$has_credentials) {
         return array(
            'ok' => false,
            'message' => 'Fuer Kleinanzeigen-API/Partnerbetrieb fehlen Middleware-URL und Zugangsdaten. Ohne vertraglich freigegebene Schnittstelle bitte Verbindung auf Manuell stellen.',
         );
      }
      return $this->test_generic($channel);
   }

   private function test_generic(array $channel): array {
      $base_url = trim((string)($channel['api_base_url'] ?? ''));
      if ($base_url === '') {
         return array('ok' => false, 'message' => 'Keine API-Basis-URL hinterlegt.');
      }
      if (!preg_match('~^https?://~i', $base_url)) {
         return array('ok' => false, 'message' => 'API-Basis-URL muss mit http:// oder https:// beginnen.');
      }
      try {
         $headers = array('Accept: application/json');
         $token = trim((string)($channel['api_access_token'] ?? ''));
         if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
         }
         $result = $this->curl('GET', $base_url, $headers);
         $ok = $result['status'] >= 200 && $result['status'] < 500;
         return array('ok' => $ok, 'message' => $ok ? 'API-URL erreichbar, HTTP ' . $result['status'] . '.' : 'API-URL nicht erreichbar, HTTP ' . $result['status'] . '.');
      } catch (\Throwable $e) {
         return array('ok' => false, 'message' => 'API-Test fehlgeschlagen: ' . $e->getMessage());
      }
   }

   private function export_mobile_product(array $channel, array $product, array $product_channel): array {
      $note_data = $this->resolved_channel_note_data('mobile', $product, $product_channel);
      $category = strtolower((string)($product['category'] ?? '') . ' ' . (string)($channel['category_id'] ?? '') . ' ' . (string)($note_data['mobile_vehicle']['category'] ?? '') . ' ' . (string)($product['product_type'] ?? ''));
      $is_vehicle = preg_match('~fahrzeug|vehicle|auto|car|motorbike|motorrad|commercial~i', $category) === 1;
      $base_url = trim((string)($channel['api_base_url'] ?? ''));
      if (!$is_vehicle && !empty($note_data['mobile_vehicle'])) {
         $is_vehicle = true;
      }
      if (!$is_vehicle && stripos($base_url, 'services.mobile.de') !== false) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'mobile.de exportiert nur Fahrzeuganzeigen. Dieser Artikel ist nicht als Fahrzeug markiert. Fuer nicht fahrzeugbezogene Daten bitte eine eigene Middleware-URL verwenden.',
            'payload' => $this->standard_product_payload($channel, $product, $product_channel),
         );
      }
      if ($is_vehicle && $base_url !== '' && stripos($base_url, 'services.mobile.de') !== false) {
         $payload = $note_data['mobile_vehicle'] ?? $note_data;
         if (!is_array($payload) || $payload === array()) {
            return array(
               'ok' => false,
               'status' => 'failed',
               'message' => 'mobile.de Fahrzeugdaten fehlen. Bitte im Channel-Hinweis JSON mit mobile.de Fahrzeugfeldern hinterlegen oder eine Middleware nutzen.',
               'payload' => $this->standard_product_payload($channel, $product, $product_channel),
            );
         }
         $payload += array(
            'sellerInventoryKey' => $this->product_sku($product, $product_channel),
            'price' => array('consumerPriceGross' => $this->channel_price($product, $product_channel), 'type' => 'FIXED'),
         );
         try {
            $base = $this->base_url($channel, 'https://services.mobile.de');
            if (preg_match('~/seller-api$~', $base)) {
               $base = substr($base, 0, -11);
            }
            $seller_id = trim((string)($channel['account_id'] ?? $channel['seller_id'] ?? ''));
            if ($seller_id === '') {
               throw new \RuntimeException('mobileSellerId/Account-ID fehlt.');
            }
            $result = $this->json_request('POST', $base . '/seller-api/sellers/' . rawurlencode($seller_id) . '/ads', array(
               'Accept: application/vnd.de.mobile.api+json',
            ), $payload, trim((string)($channel['api_username'] ?? '')), trim((string)($channel['api_password'] ?? '')));
            $listing_id = (string)($result['json']['adId'] ?? $result['json']['mobileAdId'] ?? $result['json']['id'] ?? $this->product_sku($product, $product_channel));
            return array(
               'ok' => $result['status'] >= 200 && $result['status'] < 300,
               'status' => $result['status'] >= 200 && $result['status'] < 300 ? 'published' : 'failed',
               'message' => 'mobile.de Seller API antwortet mit HTTP ' . $result['status'] . '.',
               'external_listing_id' => $listing_id,
               'payload' => array('request' => $payload, 'response' => $result['json']),
            );
         } catch (\Throwable $e) {
            return array('ok' => false, 'status' => 'failed', 'message' => 'mobile.de-Export fehlgeschlagen: ' . $e->getMessage(), 'payload' => $payload);
         }
      }
      return $this->export_middleware_product($channel, $product, $product_channel, 'mobile');
   }

   private function export_kleinanzeigen_product(array $channel, array $product, array $product_channel): array {
      $base_url = trim((string)($channel['api_base_url'] ?? ''));
      if ($base_url === '' || stripos($base_url, 'freigegebener Schnittstelle') !== false || !preg_match('~^https?://~i', $base_url)) {
         return array(
            'ok' => true,
            'status' => 'manual_ready',
            'message' => 'Kleinanzeigen ist wichtig, aber ohne freigegebene Partner-/Middleware-API wird kein automatischer Upload ausgefuehrt. Artikel ist fuer manuelle oder externe Uebergabe vorbereitet.',
            'payload' => $this->standard_product_payload($channel, $product, $product_channel),
         );
      }
      return $this->export_middleware_product($channel, $product, $product_channel, 'kleinanzeigen');
   }

   private function export_middleware_product(array $channel, array $product, array $product_channel, string $provider): array {
      $base_url = trim((string)($channel['api_base_url'] ?? ''));
      $payload = $this->standard_product_payload($channel, $product, $product_channel);
      $payload['provider'] = $provider;
      $payload['channel_note'] = $this->resolved_channel_note_data($provider, $product, $product_channel);
      if ($base_url === '' || !preg_match('~^https?://~i', $base_url)) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Keine gueltige Middleware/API-URL fuer den Export hinterlegt.', 'payload' => $payload);
      }
      try {
         $headers = array('Accept: application/json');
         $token = trim((string)($channel['api_access_token'] ?? ''));
         if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
         }
         $user = trim((string)($channel['api_username'] ?? ''));
         $password = trim((string)($channel['api_password'] ?? ''));
         $result = $this->json_request('POST', $base_url, $headers, $payload, $user !== '' ? $user : null, $user !== '' ? $password : null);
         $listing_id = (string)($result['json']['listing_id'] ?? $result['json']['ad_id'] ?? $result['json']['id'] ?? '');
         return array(
            'ok' => $result['status'] >= 200 && $result['status'] < 300,
            'status' => $result['status'] >= 200 && $result['status'] < 300 ? 'exported' : 'failed',
            'message' => ucfirst($provider) . '-Middleware antwortet mit HTTP ' . $result['status'] . '.',
            'external_listing_id' => $listing_id,
            'payload' => array('request' => $payload, 'response' => $result['json']),
         );
      } catch (\Throwable $e) {
         return array('ok' => false, 'status' => 'failed', 'message' => ucfirst($provider) . '-Export fehlgeschlagen: ' . $e->getMessage(), 'payload' => $payload);
      }
   }

   private function normalize_ebay_payload(array $payload): array {
      $order = is_array($payload['order'] ?? null) ? $payload['order'] : $payload;
      $external_id = (string)($order['orderId'] ?? $order['order_id'] ?? $order['external_order_id'] ?? $order['id'] ?? $payload['external_order_id'] ?? $payload['order_id'] ?? $payload['resourceId'] ?? '');
      $line_items = is_array($order['lineItems'] ?? null) ? $order['lineItems'] : (is_array($order['items'] ?? null) ? $order['items'] : array());
      $items = array();
      foreach ($line_items as $item) {
         if (!is_array($item)) continue;
         $items[] = array(
            'sku' => (string)($item['sku'] ?? $item['legacyItemId'] ?? $item['itemId'] ?? ''),
            'title' => (string)($item['title'] ?? $item['lineItemId'] ?? ''),
            'quantity' => (int)($item['quantity'] ?? 1),
            'price_gross' => (float)($item['lineItemCost']['value'] ?? $item['total']['value'] ?? $item['price'] ?? 0),
            'shipping_gross' => (float)($item['deliveryCost']['shippingCost']['value'] ?? 0),
         );
      }
      if ($external_id !== '') {
         $payload['external_order_id'] = $external_id;
      }
      $payload['payment_status'] = (string)($order['orderPaymentStatus'] ?? $order['paymentSummary']['payments'][0]['paymentStatus'] ?? $payload['payment_status'] ?? 'completed');
      if ($items !== array()) $payload['items'] = $items;
      return $payload;
   }

   private function normalize_amazon_payload(array $payload): array {
      $change = is_array($payload['OrderChangeNotification'] ?? null) ? $payload['OrderChangeNotification'] : array();
      $order = is_array($payload['Order'] ?? null) ? $payload['Order'] : $payload;
      $external_id = (string)($payload['AmazonOrderId'] ?? $change['AmazonOrderId'] ?? $order['AmazonOrderId'] ?? $payload['external_order_id'] ?? $payload['order_id'] ?? '');
      if ($external_id !== '') {
         $payload['external_order_id'] = $external_id;
      }
      $payload['payment_status'] = (string)($payload['payment_status'] ?? $order['OrderStatus'] ?? 'pending');
      return $payload;
   }

   private function normalize_mobile_payload(array $payload): array {
      $external_id = (string)($payload['leadId'] ?? $payload['eventId'] ?? $payload['external_order_id'] ?? $payload['order_id'] ?? $payload['id'] ?? '');
      if ($external_id !== '') {
         $payload['external_order_id'] = $external_id;
      }
      $payload['payment_status'] = (string)($payload['payment_status'] ?? 'pending');
      return $payload;
   }
}
?>
