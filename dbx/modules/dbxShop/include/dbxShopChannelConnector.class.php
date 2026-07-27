<?php
namespace dbx\dbxShop;

class dbxShopChannelConnector {

   public function test(array $channel): array {
      $platform = strtolower(trim((string)($channel['platform_type'] ?? $channel['channel_key'] ?? 'custom')));
      if ($platform === 'shop') {
         return array('ok' => true, 'message' => 'Interner Shop-Channel ist verfuegbar.');
      }
      if ($platform === 'ebay') {
         return $this->testEbay($channel);
      }
      if ($platform === 'amazon') {
         return $this->testAmazon($channel);
      }
      if ($platform === 'mobile') {
         return $this->testMobile($channel);
      }
      if ($platform === 'kleinanzeigen') {
         return $this->testKleinanzeigen($channel);
      }
      return $this->testGeneric($channel);
   }

   public function normalizeWebhookPayload(array $channel, array $payload): array {
      $platform = strtolower(trim((string)($channel['platform_type'] ?? $channel['channel_key'] ?? 'custom')));
      if ($platform === 'ebay') {
         return $this->normalizeEbayPayload($payload);
      }
      if ($platform === 'amazon') {
         return $this->normalizeAmazonPayload($payload);
      }
      if ($platform === 'mobile') {
         return $this->normalizeMobilePayload($payload);
      }
      return $payload;
   }

   public function exportProduct(array $channel, array $product, array $productChannel = array()): array {
      $platform = strtolower(trim((string)($channel['platform_type'] ?? $channel['channel_key'] ?? 'custom')));
      if ($platform === 'shop') {
         return array(
            'ok' => true,
            'status' => 'ready',
            'message' => 'Interner Shop-Channel: kein externer Export notwendig.',
            'payload' => $this->standardProductPayload($channel, $product, $productChannel),
         );
      }
      if ($platform === 'ebay') {
         return $this->exportEbayProduct($channel, $product, $productChannel);
      }
      if ($platform === 'amazon') {
         return $this->exportAmazonProduct($channel, $product, $productChannel);
      }
      if ($platform === 'mobile') {
         return $this->exportMobileProduct($channel, $product, $productChannel);
      }
      if ($platform === 'kleinanzeigen') {
         return $this->exportKleinanzeigenProduct($channel, $product, $productChannel);
      }
      return $this->exportMiddlewareProduct($channel, $product, $productChannel, 'custom');
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

   private function baseUrl(array $channel, string $fallback): string {
      $baseUrl = trim((string)($channel['api_base_url'] ?? ''));
      return rtrim($baseUrl !== '' ? $baseUrl : $fallback, '/');
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

   private function jsonRequest(string $method, string $url, array $headers, array $payload, ?string $user = null, ?string $password = null): array {
      $headers[] = 'Content-Type: application/json';
      $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if ($body === false) {
         throw new \RuntimeException('Payload konnte nicht als JSON erzeugt werden.');
      }
      return $this->curl($method, $url, $headers, $body, $user, $password);
   }

   private function productSku(array $product, array $productChannel): string {
      $sku = trim((string)($productChannel['channel_sku'] ?? ''));
      if ($sku === '') {
         $sku = trim((string)($product['sku'] ?? ''));
      }
      return $sku;
   }

   private function channelPrice(array $product, array $productChannel): float {
      $price = (float)($productChannel['price_gross'] ?? -1);
      return $price >= 0 ? $price : (float)($product['price_gross'] ?? 0);
   }

   private function channelShipping(array $product, array $productChannel): float {
      $shipping = (float)($productChannel['shipping_gross'] ?? -1);
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

   private function imageUrls(array $product): array {
      $base = function_exists('dbx') ? rtrim((string)dbx()->get_base_url(), '/') . '/' : '';
      $urls = array();
      foreach ((array)($product['images'] ?? array()) as $image) {
         $mediaId = (int)($image['media_id'] ?? 0);
         if ($mediaId > 0 && $base !== '') {
            $urls[] = $base . 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $mediaId;
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

   private function productChannelNoteData(array $productChannel): array {
      $note = trim((string)($productChannel['note'] ?? ''));
      if ($note === '') {
         return array();
      }
      $data = json_decode($note, true);
      return is_array($data) ? $data : array();
   }

   private function productGroupChannelDefaults(string $platform, array $product): array {
      $group = is_array(($product['groups'][0] ?? null)) ? $product['groups'][0] : array();
      if ($group === array()) {
         return array();
      }
      if ($platform === 'ebay') {
         $category = trim((string)($group['ebay_category_id'] ?? ''));
         return $category !== '' ? array('category_id' => $category) : array();
      }
      if ($platform === 'amazon') {
         $productType = trim((string)($group['amazon_product_type'] ?? ''));
         return $productType !== '' ? array('productType' => $productType) : array();
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

   private function mergeDefaults(array $defaults, array $values): array {
      foreach ($defaults as $key => $value) {
         if (is_array($value)) {
            $current = is_array($values[$key] ?? null) ? $values[$key] : array();
            $values[$key] = $this->mergeDefaults($value, $current);
            continue;
         }
         if (!array_key_exists($key, $values) || trim((string)$values[$key]) === '') {
            $values[$key] = $value;
         }
      }
      return $values;
   }

   private function resolvedChannelNoteData(string $platform, array $product, array $productChannel): array {
      return $this->mergeDefaults($this->productGroupChannelDefaults($platform, $product), $this->productChannelNoteData($productChannel));
   }

   private function standardProductPayload(array $channel, array $product, array $productChannel): array {
      $sku = $this->productSku($product, $productChannel);
      $platform = strtolower(trim((string)($channel['platform_type'] ?? $channel['channel_key'] ?? 'custom')));
      $noteData = $this->resolvedChannelNoteData($platform, $product, $productChannel);
      $categoryId = (string)($noteData['category_id'] ?? $noteData['mobile_vehicle']['category'] ?? $channel['category_id'] ?? '');
      return array(
         'sku' => $sku,
         'title' => (string)($product['title'] ?? $sku),
         'summary' => (string)($product['summary'] ?? ''),
         'description' => (string)($product['description'] ?? $product['summary'] ?? ''),
         'price_gross' => $this->channelPrice($product, $productChannel),
         'shipping_gross' => $this->channelShipping($product, $productChannel),
         'currency' => $this->currency($product),
         'quantity' => $this->quantity($product),
         'product_type' => (string)($product['product_type'] ?? ''),
         'category' => (string)($product['category'] ?? ''),
         'category_id' => $categoryId,
         'images' => $this->imageUrls($product),
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

   private function testEbay(array $channel): array {
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
            $token = $this->ebayRefreshAccessToken($channel, $scopes);
         }
         $base = $this->baseUrl($channel, 'https://api.ebay.com');
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

   private function ebayRefreshAccessToken(array $channel, array $scopes): string {
      $base = $this->baseUrl($channel, 'https://api.ebay.com');
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

   private function ebayAccessToken(array $channel): string {
      $token = trim((string)($channel['api_access_token'] ?? ''));
      if ($token !== '') {
         return $token;
      }
      return $this->ebayRefreshAccessToken($channel, $this->scopes((string)($channel['api_scope'] ?? '')));
   }

   private function exportEbayProduct(array $channel, array $product, array $productChannel): array {
      $sku = $this->productSku($product, $productChannel);
      $noteData = $this->resolvedChannelNoteData('ebay', $product, $productChannel);
      $categoryId = trim((string)($noteData['category_id'] ?? $channel['category_id'] ?? ''));
      $locationKey = trim((string)($channel['location_key'] ?? ''));
      $paymentPolicyId = trim((string)($noteData['payment_policy_id'] ?? $channel['payment_policy_id'] ?? ''));
      $fulfillmentPolicyId = trim((string)($noteData['fulfillment_policy_id'] ?? $channel['fulfillment_policy_id'] ?? ''));
      $returnPolicyId = trim((string)($noteData['return_policy_id'] ?? $channel['return_policy_id'] ?? ''));
      $checkChannel = array_replace($channel, array(
         'category_id' => $categoryId,
         'location_key' => $locationKey,
         'payment_policy_id' => $paymentPolicyId,
         'fulfillment_policy_id' => $fulfillmentPolicyId,
         'return_policy_id' => $returnPolicyId,
      ));
      $missing = $this->missing($checkChannel, array(
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
      $images = $this->imageUrls($product);
      if ($images === array()) {
         $missing[] = 'mindestens ein oeffentlich erreichbares Artikelbild';
      }
      if ($missing !== array()) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'eBay-Export nicht moeglich: ' . implode(', ', array_unique($missing)) . '.',
            'payload' => $this->standardProductPayload($channel, $product, $productChannel),
         );
      }

      try {
         $token = $this->ebayAccessToken($channel);
         $base = $this->baseUrl($channel, 'https://api.ebay.com');
         $headers = array(
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Language: de-DE',
         );
         $quantity = $this->quantity($product);
         $price = number_format($this->channelPrice($product, $productChannel), 2, '.', '');
         $shipping = $this->channelShipping($product, $productChannel);
         $currency = $this->currency($product);
         $description = trim((string)($product['description'] ?? $product['summary'] ?? ''));
         if ($description === '') {
            $description = (string)($product['title'] ?? $sku);
         }
         $inventoryPayload = array(
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
         if (is_array($noteData['aspects'] ?? null)) {
            foreach ($noteData['aspects'] as $aspectName => $aspectValue) {
               $aspectName = trim((string)$aspectName);
               if ($aspectName === '') continue;
               $values = is_array($aspectValue) ? $aspectValue : array($aspectValue);
               $values = array_values(array_filter(array_map('strval', $values), fn($v) => trim($v) !== ''));
               if ($values !== array()) {
                  $aspects[$aspectName] = $values;
               }
            }
         }
         if ($aspects !== array()) {
            $inventoryPayload['product']['aspects'] = $aspects;
         }
         if (trim((string)($noteData['condition'] ?? '')) !== '') {
            $inventoryPayload['condition'] = trim((string)$noteData['condition']);
         }
         $this->jsonRequest('PUT', $base . '/sell/inventory/v1/inventory_item/' . rawurlencode($sku), $headers, $inventoryPayload);

         $offerPayload = array(
            'sku' => $sku,
            'marketplaceId' => (string)$channel['marketplace_id'],
            'format' => 'FIXED_PRICE',
            'availableQuantity' => max(0, $quantity),
            'categoryId' => $categoryId,
            'merchantLocationKey' => $locationKey,
            'listingPolicies' => array(
               'fulfillmentPolicyId' => $fulfillmentPolicyId,
               'paymentPolicyId' => $paymentPolicyId,
               'returnPolicyId' => $returnPolicyId,
            ),
            'pricingSummary' => array(
               'price' => array('value' => $price, 'currency' => $currency),
            ),
         );
         $offerPayload['listingDescription'] = $this->cut($description, 4000);
         $offerId = trim((string)($productChannel['external_offer_id'] ?? ''));
         if ($offerId !== '') {
            $this->jsonRequest('PUT', $base . '/sell/inventory/v1/offer/' . rawurlencode($offerId), $headers, $offerPayload);
         } else {
            $created = $this->jsonRequest('POST', $base . '/sell/inventory/v1/offer', $headers, $offerPayload);
            $offerId = (string)($created['json']['offerId'] ?? $created['json']['offer']['offerId'] ?? '');
         }
         if ($offerId === '') {
            throw new \RuntimeException('eBay hat keine Offer-ID geliefert.');
         }

         $published = $this->jsonRequest('POST', $base . '/sell/inventory/v1/offer/' . rawurlencode($offerId) . '/publish', $headers, array());
         $listingId = (string)($published['json']['listingId'] ?? $published['json']['listing']['listingId'] ?? $productChannel['external_listing_id'] ?? '');
         return array(
            'ok' => true,
            'status' => 'published',
            'message' => 'eBay-Angebot wurde exportiert und veroeffentlicht' . ($listingId !== '' ? ' (Listing ' . $listingId . ')' : '') . '.',
            'external_offer_id' => $offerId,
            'external_listing_id' => $listingId,
            'payload' => array('inventory' => $inventoryPayload, 'offer' => $offerPayload, 'publish' => $published['json']),
         );
      } catch (\Throwable $e) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'eBay-Export fehlgeschlagen: ' . $e->getMessage(),
            'payload' => $this->standardProductPayload($channel, $product, $productChannel),
         );
      }
   }

   private function testAmazon(array $channel): array {
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
         $token = $this->amazonAccessToken($channel);
         $base = $this->baseUrl($channel, 'https://sellingpartnerapi-eu.amazon.com');
         $createdAfter = gmdate('Y-m-d\TH:i:s\Z', time() - 86400 * 7);
         $url = $base . '/orders/v0/orders?MarketplaceIds=' . rawurlencode((string)$channel['marketplace_id']) . '&CreatedAfter=' . rawurlencode($createdAfter);
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

   private function amazonAccessToken(array $channel): string {
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

   private function amazonProductType(array $channel, array $productChannel): string {
      $note = $this->productChannelNoteData($productChannel);
      $fromNote = trim((string)($note['productType'] ?? $note['product_type'] ?? ''));
      if ($fromNote !== '') {
         return $fromNote;
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

   private function resolvedAmazonProductType(array $channel, array $product, array $productChannel): string {
      $note = $this->resolvedChannelNoteData('amazon', $product, $productChannel);
      $fromNote = trim((string)($note['productType'] ?? $note['product_type'] ?? ''));
      if ($fromNote !== '') {
         return $fromNote;
      }
      return $this->amazonProductType($channel, $productChannel);
   }

   private function exportAmazonProduct(array $channel, array $product, array $productChannel): array {
      $sku = $this->productSku($product, $productChannel);
      $productType = $this->resolvedAmazonProductType($channel, $product, $productChannel);
      $missing = $this->missing($channel, array(
         'api_client_id' => 'LWA Client-ID',
         'api_client_secret' => 'LWA Client-Secret',
         'api_refresh_token' => 'LWA Refresh-Token',
         'seller_id' => 'Seller-ID',
         'marketplace_id' => 'Marketplace-ID',
      ));
      if ($sku === '') $missing[] = 'Channel-SKU/Artikelnummer';
      if ($productType === '') $missing[] = 'Amazon Product Type';
      if ($missing !== array()) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'Amazon-Export nicht moeglich: ' . implode(', ', $missing) . '. Product Type und Pflichtattribute muessen zum Amazon-Schema passen.',
            'payload' => $this->standardProductPayload($channel, $product, $productChannel),
         );
      }

      $noteData = $this->resolvedChannelNoteData('amazon', $product, $productChannel);
      $quantity = $this->quantity($product);
      $currency = $this->currency($product);
      $price = number_format($this->channelPrice($product, $productChannel), 2, '.', '');
      $attributes = is_array($noteData['attributes'] ?? null) ? $noteData['attributes'] : array();
      if (is_array($noteData['simple_attributes'] ?? null)) {
         foreach ($noteData['simple_attributes'] as $attrKey => $attrValue) {
            $attrKey = trim((string)$attrKey);
            $attrValue = trim((string)$attrValue);
            if ($attrKey !== '' && $attrValue !== '' && !isset($attributes[$attrKey])) {
               $attributes[$attrKey] = array(array('value' => $attrValue, 'marketplace_id' => (string)$channel['marketplace_id']));
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
      if ($this->imageUrls($product) !== array()) {
         $attributes['main_product_image_locator'] = array(array('media_location' => $this->imageUrls($product)[0], 'marketplace_id' => (string)$channel['marketplace_id']));
      }
      $payload = array(
         'productType' => $productType,
         'requirements' => (string)($noteData['requirements'] ?? 'LISTING'),
         'attributes' => $attributes,
      );

      try {
         $token = $this->amazonAccessToken($channel);
         $base = $this->baseUrl($channel, 'https://sellingpartnerapi-eu.amazon.com');
         $url = $base . '/listings/2021-08-01/items/' . rawurlencode((string)$channel['seller_id']) . '/' . rawurlencode($sku)
            . '?marketplaceIds=' . rawurlencode((string)$channel['marketplace_id']) . '&issueLocale=de_DE';
         $result = $this->jsonRequest('PUT', $url, array(
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

   private function testMobile(array $channel): array {
      $missing = $this->missing($channel, array(
         'api_username' => 'API-Benutzer',
         'api_password' => 'API-Passwort',
      ));
      if ($missing !== array()) {
         return array('ok' => false, 'message' => 'mobile.de-Konfiguration unvollstaendig: ' . implode(', ', $missing) . '.');
      }

      try {
         $base = $this->baseUrl($channel, 'https://services.mobile.de');
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

   private function testKleinanzeigen(array $channel): array {
      $mode = strtolower((string)($channel['connection_mode'] ?? 'manual'));
      if ($mode === 'manual') {
         return array(
            'ok' => true,
            'message' => 'Kleinanzeigen ist als manueller/Partner-Channel konfiguriert. Eine frei nutzbare Standard-API wird hier nicht vorausgesetzt.',
         );
      }
      $baseUrl = trim((string)($channel['api_base_url'] ?? ''));
      $hasCredentials = trim((string)($channel['api_client_id'] ?? $channel['api_username'] ?? '')) !== '';
      if ($baseUrl === '' || !$hasCredentials) {
         return array(
            'ok' => false,
            'message' => 'Fuer Kleinanzeigen-API/Partnerbetrieb fehlen Middleware-URL und Zugangsdaten. Ohne vertraglich freigegebene Schnittstelle bitte Verbindung auf Manuell stellen.',
         );
      }
      return $this->testGeneric($channel);
   }

   private function testGeneric(array $channel): array {
      $baseUrl = trim((string)($channel['api_base_url'] ?? ''));
      if ($baseUrl === '') {
         return array('ok' => false, 'message' => 'Keine API-Basis-URL hinterlegt.');
      }
      if (!preg_match('~^https?://~i', $baseUrl)) {
         return array('ok' => false, 'message' => 'API-Basis-URL muss mit http:// oder https:// beginnen.');
      }
      try {
         $headers = array('Accept: application/json');
         $token = trim((string)($channel['api_access_token'] ?? ''));
         if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
         }
         $result = $this->curl('GET', $baseUrl, $headers);
         $ok = $result['status'] >= 200 && $result['status'] < 500;
         return array('ok' => $ok, 'message' => $ok ? 'API-URL erreichbar, HTTP ' . $result['status'] . '.' : 'API-URL nicht erreichbar, HTTP ' . $result['status'] . '.');
      } catch (\Throwable $e) {
         return array('ok' => false, 'message' => 'API-Test fehlgeschlagen: ' . $e->getMessage());
      }
   }

   private function exportMobileProduct(array $channel, array $product, array $productChannel): array {
      $noteData = $this->resolvedChannelNoteData('mobile', $product, $productChannel);
      $category = strtolower((string)($product['category'] ?? '') . ' ' . (string)($channel['category_id'] ?? '') . ' ' . (string)($noteData['mobile_vehicle']['category'] ?? '') . ' ' . (string)($product['product_type'] ?? ''));
      $isVehicle = preg_match('~fahrzeug|vehicle|auto|car|motorbike|motorrad|commercial~i', $category) === 1;
      $baseUrl = trim((string)($channel['api_base_url'] ?? ''));
      if (!$isVehicle && !empty($noteData['mobile_vehicle'])) {
         $isVehicle = true;
      }
      if (!$isVehicle && stripos($baseUrl, 'services.mobile.de') !== false) {
         return array(
            'ok' => false,
            'status' => 'failed',
            'message' => 'mobile.de exportiert nur Fahrzeuganzeigen. Dieser Artikel ist nicht als Fahrzeug markiert. Fuer nicht fahrzeugbezogene Daten bitte eine eigene Middleware-URL verwenden.',
            'payload' => $this->standardProductPayload($channel, $product, $productChannel),
         );
      }
      if ($isVehicle && $baseUrl !== '' && stripos($baseUrl, 'services.mobile.de') !== false) {
         $payload = $noteData['mobile_vehicle'] ?? $noteData;
         if (!is_array($payload) || $payload === array()) {
            return array(
               'ok' => false,
               'status' => 'failed',
               'message' => 'mobile.de Fahrzeugdaten fehlen. Bitte im Channel-Hinweis JSON mit mobile.de Fahrzeugfeldern hinterlegen oder eine Middleware nutzen.',
               'payload' => $this->standardProductPayload($channel, $product, $productChannel),
            );
         }
         $payload += array(
            'sellerInventoryKey' => $this->productSku($product, $productChannel),
            'price' => array('consumerPriceGross' => $this->channelPrice($product, $productChannel), 'type' => 'FIXED'),
         );
         try {
            $base = $this->baseUrl($channel, 'https://services.mobile.de');
            if (preg_match('~/seller-api$~', $base)) {
               $base = substr($base, 0, -11);
            }
            $sellerId = trim((string)($channel['account_id'] ?? $channel['seller_id'] ?? ''));
            if ($sellerId === '') {
               throw new \RuntimeException('mobileSellerId/Account-ID fehlt.');
            }
            $result = $this->jsonRequest('POST', $base . '/seller-api/sellers/' . rawurlencode($sellerId) . '/ads', array(
               'Accept: application/vnd.de.mobile.api+json',
            ), $payload, trim((string)($channel['api_username'] ?? '')), trim((string)($channel['api_password'] ?? '')));
            $listingId = (string)($result['json']['adId'] ?? $result['json']['mobileAdId'] ?? $result['json']['id'] ?? $this->productSku($product, $productChannel));
            return array(
               'ok' => $result['status'] >= 200 && $result['status'] < 300,
               'status' => $result['status'] >= 200 && $result['status'] < 300 ? 'published' : 'failed',
               'message' => 'mobile.de Seller API antwortet mit HTTP ' . $result['status'] . '.',
               'external_listing_id' => $listingId,
               'payload' => array('request' => $payload, 'response' => $result['json']),
            );
         } catch (\Throwable $e) {
            return array('ok' => false, 'status' => 'failed', 'message' => 'mobile.de-Export fehlgeschlagen: ' . $e->getMessage(), 'payload' => $payload);
         }
      }
      return $this->exportMiddlewareProduct($channel, $product, $productChannel, 'mobile');
   }

   private function exportKleinanzeigenProduct(array $channel, array $product, array $productChannel): array {
      $baseUrl = trim((string)($channel['api_base_url'] ?? ''));
      if ($baseUrl === '' || stripos($baseUrl, 'freigegebener Schnittstelle') !== false || !preg_match('~^https?://~i', $baseUrl)) {
         return array(
            'ok' => true,
            'status' => 'manual_ready',
            'message' => 'Kleinanzeigen ist wichtig, aber ohne freigegebene Partner-/Middleware-API wird kein automatischer Upload ausgefuehrt. Artikel ist fuer manuelle oder externe Uebergabe vorbereitet.',
            'payload' => $this->standardProductPayload($channel, $product, $productChannel),
         );
      }
      return $this->exportMiddlewareProduct($channel, $product, $productChannel, 'kleinanzeigen');
   }

   private function exportMiddlewareProduct(array $channel, array $product, array $productChannel, string $provider): array {
      $baseUrl = trim((string)($channel['api_base_url'] ?? ''));
      $payload = $this->standardProductPayload($channel, $product, $productChannel);
      $payload['provider'] = $provider;
      $payload['channel_note'] = $this->resolvedChannelNoteData($provider, $product, $productChannel);
      if ($baseUrl === '' || !preg_match('~^https?://~i', $baseUrl)) {
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
         $result = $this->jsonRequest('POST', $baseUrl, $headers, $payload, $user !== '' ? $user : null, $user !== '' ? $password : null);
         $listingId = (string)($result['json']['listing_id'] ?? $result['json']['ad_id'] ?? $result['json']['id'] ?? '');
         return array(
            'ok' => $result['status'] >= 200 && $result['status'] < 300,
            'status' => $result['status'] >= 200 && $result['status'] < 300 ? 'exported' : 'failed',
            'message' => ucfirst($provider) . '-Middleware antwortet mit HTTP ' . $result['status'] . '.',
            'external_listing_id' => $listingId,
            'payload' => array('request' => $payload, 'response' => $result['json']),
         );
      } catch (\Throwable $e) {
         return array('ok' => false, 'status' => 'failed', 'message' => ucfirst($provider) . '-Export fehlgeschlagen: ' . $e->getMessage(), 'payload' => $payload);
      }
   }

   private function normalizeEbayPayload(array $payload): array {
      $order = is_array($payload['order'] ?? null) ? $payload['order'] : $payload;
      $externalId = (string)($order['orderId'] ?? $order['order_id'] ?? $order['external_order_id'] ?? $order['id'] ?? $payload['external_order_id'] ?? $payload['order_id'] ?? $payload['resourceId'] ?? '');
      $lineItems = is_array($order['lineItems'] ?? null) ? $order['lineItems'] : (is_array($order['items'] ?? null) ? $order['items'] : array());
      $items = array();
      foreach ($lineItems as $item) {
         if (!is_array($item)) continue;
         $items[] = array(
            'sku' => (string)($item['sku'] ?? $item['legacyItemId'] ?? $item['itemId'] ?? ''),
            'title' => (string)($item['title'] ?? $item['lineItemId'] ?? ''),
            'quantity' => (int)($item['quantity'] ?? 1),
            'price_gross' => (float)($item['lineItemCost']['value'] ?? $item['total']['value'] ?? $item['price'] ?? 0),
            'shipping_gross' => (float)($item['deliveryCost']['shippingCost']['value'] ?? 0),
         );
      }
      if ($externalId !== '') {
         $payload['external_order_id'] = $externalId;
      }
      $payload['payment_status'] = (string)($order['orderPaymentStatus'] ?? $order['paymentSummary']['payments'][0]['paymentStatus'] ?? $payload['payment_status'] ?? 'completed');
      if ($items !== array()) $payload['items'] = $items;
      return $payload;
   }

   private function normalizeAmazonPayload(array $payload): array {
      $change = is_array($payload['OrderChangeNotification'] ?? null) ? $payload['OrderChangeNotification'] : array();
      $order = is_array($payload['Order'] ?? null) ? $payload['Order'] : $payload;
      $externalId = (string)($payload['AmazonOrderId'] ?? $change['AmazonOrderId'] ?? $order['AmazonOrderId'] ?? $payload['external_order_id'] ?? $payload['order_id'] ?? '');
      if ($externalId !== '') {
         $payload['external_order_id'] = $externalId;
      }
      $payload['payment_status'] = (string)($payload['payment_status'] ?? $order['OrderStatus'] ?? 'pending');
      return $payload;
   }

   private function normalizeMobilePayload(array $payload): array {
      $externalId = (string)($payload['leadId'] ?? $payload['eventId'] ?? $payload['external_order_id'] ?? $payload['order_id'] ?? $payload['id'] ?? '');
      if ($externalId !== '') {
         $payload['external_order_id'] = $externalId;
      }
      $payload['payment_status'] = (string)($payload['payment_status'] ?? 'pending');
      return $payload;
   }
}
?>
