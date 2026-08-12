<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryChannelServiceTrait {


   public function channels(): array {
      $this->install();
      return $this->remember('channels', function(): array {
         $rows = $this->db()->select($this->dd('shopChannel'), 'trash = 0', '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
         return is_array($rows) ? $rows : array();
      });
   }



   public function channelById(int $id): ?array {
      $this->install();
      if ($id <= 0) {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopChannel'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }



   public function channelByKey(string $key): ?array {
      $this->install();
      $key = trim($key);
      if ($key === '') {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopChannel'), 'channel_key = ' . $this->sqlValue($key) . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }



   public function updateChannel(int $id, array $data): void {
      $this->install();
      $secretFields = array('api_client_secret', 'api_access_token', 'api_refresh_token', 'api_password', 'webhook_secret');
      $existing = $id > 0 ? ($this->channelById($id) ?: array()) : array();
      $values = array(
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'platform_type' => (string)($data['platform_type'] ?? 'custom'),
         'connection_mode' => (string)($data['connection_mode'] ?? 'manual'),
         'api_base_url' => (string)($data['api_base_url'] ?? ''),
         'api_client_id' => (string)($data['api_client_id'] ?? ''),
         'api_username' => (string)($data['api_username'] ?? ''),
         'marketplace_id' => (string)($data['marketplace_id'] ?? ''),
         'seller_id' => (string)($data['seller_id'] ?? ''),
         'account_id' => (string)($data['account_id'] ?? ''),
         'location_key' => (string)($data['location_key'] ?? ''),
         'category_id' => (string)($data['category_id'] ?? ''),
         'payment_policy_id' => (string)($data['payment_policy_id'] ?? ''),
         'fulfillment_policy_id' => (string)($data['fulfillment_policy_id'] ?? ''),
         'return_policy_id' => (string)($data['return_policy_id'] ?? ''),
         'notification_destination' => (string)($data['notification_destination'] ?? ''),
         'notification_topic' => (string)($data['notification_topic'] ?? ''),
         'api_scope' => (string)($data['api_scope'] ?? ''),
         'export_enabled' => !empty($data['export_enabled']) ? 1 : 0,
         'order_import_enabled' => !empty($data['order_import_enabled']) ? 1 : 0,
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      foreach ($secretFields as $field) {
         $posted = (string)($data[$field] ?? '');
         $values[$field] = ($id > 0 && $posted === '') ? (string)($existing[$field] ?? '') : $posted;
      }

      if ($id <= 0) {
         $channelKey = $this->normalizeKey((string)($data['channel_key'] ?? ''));
         if ($channelKey === '') {
            $channelKey = $this->normalizeKey((string)($data['title'] ?? 'channel'));
         }
         if ($channelKey === '') $channelKey = 'channel';
         $channelKey = $this->uniqueChannelKey($channelKey);
         $values['channel_key'] = $channelKey;
         $this->db()->insert($this->dd('shopChannel'), $values, 0);
         $this->clearRequestCache();
         return;
      }

      $this->db()->update($this->dd('shopChannel'), $values, 'id = ' . (int)$id, 0);
      $this->clearRequestCache();
   }



   public function deleteChannel(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      $channel = $this->channelById($id);
      if (!$channel) {
         return 0;
      }

      $updated = (int)$this->db()->update(
         $this->dd('shopChannel'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         'id = ' . (int)$id . ' AND trash = 0',
         0
      );
      if ($updated !== 1) {
         return 0;
      }

      $this->db()->update(
         $this->dd('shopProductChannel'),
         array('active' => 0),
         'channel_key = ' . $this->sqlValue((string)($channel['channel_key'] ?? '')),
         0
      );
      $this->clearRequestCache();
      return 1;
   }



   public function testChannelConnection(int $id): array {
      $this->install();
      $channel = $this->channelById($id);
      if (!$channel) {
         return array('ok' => false, 'message' => 'Channel wurde nicht gefunden.');
      }

      $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
      $result = is_object($connector) && method_exists($connector, 'test')
         ? (array)$connector->test($channel)
         : array('ok' => false, 'message' => 'Channel-Connector konnte nicht geladen werden.');

      $ok = !empty($result['ok']);
      $message = (string)($result['message'] ?? '');
      $this->saveChannelTestResult($id, $ok, $message);
      return array('ok' => $ok, 'message' => $message);
   }

   private function productHasActiveChannel(array $product, string $channelKey): bool {
      foreach ((array)($product['channels'] ?? array()) as $channel) {
         if ((string)($channel['channel_key'] ?? '') === $channelKey && (int)($channel['active'] ?? 0) === 1) {
            return true;
         }
      }
      return false;
   }

   private function productChannelRowForExport(array $product, string $channelKey): array {
      $productId = (int)($product['id'] ?? 0);
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         '*',
         0
      );
      if (is_array($row)) {
         return $row;
      }

      $values = array(
         'product_id' => $productId,
         'channel_key' => $channelKey,
         'active' => 1,
         'channel_sku' => (string)($product['sku'] ?? ''),
         'price_gross' => -1,
         'shipping_gross' => -1,
         'export_status' => 'ready',
      );
      $this->db()->save(
         $this->dd('shopProductChannel'),
         $values,
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         0
      );
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         '*',
         0
      );
      return is_array($row) ? $row : $values;
   }

   public function productChannelMapping(int $productId, string $channelKey): ?array {
      $this->install();
      $product = $this->productById($productId);
      if (!$product) {
         return null;
      }
      $channel = $this->channelByKey($channelKey);
      if (!$channel) {
         return null;
      }
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         '*',
         0
      );
      if (!is_array($row)) {
         $row = array(
            'product_id' => $productId,
            'channel_key' => $channelKey,
            'active' => $this->productHasActiveChannel($product, $channelKey) ? 1 : 0,
            'channel_sku' => (string)($product['sku'] ?? ''),
            'price_gross' => -1,
            'shipping_gross' => -1,
            'note' => '',
         );
      }
      $note = trim((string)($row['note'] ?? ''));
      $mapping = $note !== '' ? json_decode($note, true) : array();
      if (!is_array($mapping)) {
         $mapping = array();
      }
      return array(
         'product' => $product,
         'channel' => $channel,
         'product_channel' => $row,
         'mapping' => $mapping,
      );
   }

   public function saveProductChannelMapping(int $productId, string $channelKey, array $data): void {
      $this->install();
      $product = $this->productById($productId);
      $channel = $this->channelByKey($channelKey);
      if (!$product || !$channel) {
         return;
      }

      $mapping = is_array($data['mapping'] ?? null) ? $data['mapping'] : array();
      $note = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      if ($note === false) {
         $note = '';
      }

      $existing = $this->productChannelRowForExport($product, $channelKey);
      $this->db()->save(
         $this->dd('shopProductChannel'),
         array(
            'product_id' => $productId,
            'channel_key' => $channelKey,
            'active' => !empty($data['active']) ? 1 : 0,
            'channel_sku' => trim((string)($data['channel_sku'] ?? $product['sku'] ?? '')),
            'price_gross' => (float)($data['price_gross'] ?? -1),
            'shipping_gross' => (float)($data['shipping_gross'] ?? -1),
            'external_listing_id' => trim((string)($data['external_listing_id'] ?? $existing['external_listing_id'] ?? '')),
            'external_offer_id' => trim((string)($data['external_offer_id'] ?? $existing['external_offer_id'] ?? '')),
            'export_status' => (string)($existing['export_status'] ?? ''),
            'export_message' => (string)($existing['export_message'] ?? ''),
            'export_payload' => (string)($existing['export_payload'] ?? ''),
            'last_export_date' => (string)($existing['last_export_date'] ?? ''),
            'note' => $note,
         ),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         0
      );
      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . $productId . ' AND trash = 0', 0);
   }

   private function saveProductChannelExportResult(int $productId, string $channelKey, array $result): void {
      $payload = $result['payload'] ?? array();
      $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if ($payloadJson === false) {
         $payloadJson = '';
      }
      $this->db()->save(
         $this->dd('shopProductChannel'),
         array(
            'product_id' => $productId,
            'channel_key' => $channelKey,
            'active' => 1,
            'external_listing_id' => (string)($result['external_listing_id'] ?? ''),
            'external_offer_id' => (string)($result['external_offer_id'] ?? ''),
            'export_status' => (string)($result['status'] ?? (!empty($result['ok']) ? 'exported' : 'failed')),
            'export_message' => (string)($result['message'] ?? ''),
            'export_payload' => $payloadJson,
            'last_export_date' => date('Y-m-d H:i:s'),
         ),
         'product_id = ' . $productId . ' AND channel_key = ' . $this->sqlValue($channelKey),
         0
      );
      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . $productId . ' AND trash = 0', 0);
   }

   public function exportProductToChannel(int $productId, string $channelKey): array {
      $this->install();
      $channelKey = trim($channelKey);
      $product = $this->productById($productId);
      if (!$product) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Artikel wurde nicht gefunden.');
      }
      $channel = $this->channelByKey($channelKey);
      if (!$channel || (int)($channel['active'] ?? 0) !== 1) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Channel ist nicht aktiv oder wurde nicht gefunden.');
      }
      if ((int)($channel['export_enabled'] ?? 0) !== 1) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Export ist fuer diesen Channel nicht aktiv.');
      }
      if (!$this->productHasActiveChannel($product, $channelKey)) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Artikel ist diesem Channel nicht aktiv zugeordnet.');
      }

      $productChannel = $this->productChannelRowForExport($product, $channelKey);
      $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
      $result = is_object($connector) && method_exists($connector, 'exportProduct')
         ? (array)$connector->exportProduct($channel, $product, $productChannel)
         : array('ok' => false, 'status' => 'failed', 'message' => 'Channel-Export-Connector konnte nicht geladen werden.');
      $this->saveProductChannelExportResult((int)$product['id'], $channelKey, $result);
      return $result;
   }

   public function exportProductsToChannel(array $ids, string $channelKey): array {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      $summary = array('total' => count($ids), 'ok' => 0, 'failed' => 0, 'messages' => array());
      foreach ($ids as $id) {
         $result = $this->exportProductToChannel($id, $channelKey);
         if (!empty($result['ok'])) {
            $summary['ok']++;
         } else {
            $summary['failed']++;
         }
         $message = trim((string)($result['message'] ?? ''));
         if ($message !== '') {
            $summary['messages'][] = '#' . $id . ': ' . $message;
         }
      }
      return $summary;
   }

   private function saveChannelTestResult(int $id, bool $ok, string $message): void {
      $now = date('Y-m-d H:i:s');
      $this->db()->update(
         $this->dd('shopChannel'),
         array(
            'test_status' => $ok ? 'ok' : 'error',
            'test_message' => $message,
            'last_test_date' => $now,
            'update_date' => $now,
         ),
         'id = ' . (int)$id,
         0
      );
   }



   public function shippingGroups(): array {
      $this->install();
      $rows = $this->db()->select($this->dd('shopShippingGroup'), 'trash = 0', '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }



   public function channelGroups(): array {
      $this->install();
      $groups = $this->db()->select($this->dd('shopChannelGroup'), 'trash = 0', '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $groups = is_array($groups) ? $groups : array();
      foreach ($groups as &$group) {
         $group['channels'] = $this->channelsForChannelGroup((int)$group['id']);
      }
      unset($group);
      return $groups;
   }



   public function channelsForChannelGroup(int $channelGroupId): array {
      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $channelIndex[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $rows = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . (int)$channelGroupId, '*', '', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $key = (string)($row['channel_key'] ?? '');
         $row['title'] = (string)($channelIndex[$key]['title'] ?? $key);
         $row['_sorter'] = (int)($channelIndex[$key]['sorter'] ?? 9999);
      }
      unset($row);
      usort($rows, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
         ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_sorter']);
      }
      unset($row);
      return $rows;
   }



   public function updateProductGroup(int $id, array $data): void {
      $this->install();
      $parentId = max(0, (int)($data['parent_id'] ?? 0));
      if ($id > 0 && $parentId === $id) {
         $parentId = 0;
      }
      $values = array(
         'parent_id' => $parentId,
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'tax_class' => (string)($data['tax_class'] ?? 'mwst1'),
         'default_tax_rate' => $this->taxRateForClass((string)($data['tax_class'] ?? 'mwst1'), (float)($data['default_tax_rate'] ?? 19)),
         'display_variant' => (string)($data['display_variant'] ?? 'gallery_grid'),
         'card_template' => (string)($data['card_template'] ?? 'product-card-default'),
         'detail_template' => (string)($data['detail_template'] ?? 'product-detail-default'),
         'gallery_template' => (string)($data['gallery_template'] ?? 'image-gallery'),
         'gallery_visible_count' => max(1, (int)($data['gallery_visible_count'] ?? 3)),
         'gallery_image_size' => (string)($data['gallery_image_size'] ?? 'original'),
         'gallery_lightbox_width' => (string)($data['gallery_lightbox_width'] ?? '100vw'),
         'gallery_overflow' => (string)($data['gallery_overflow'] ?? 'grid'),
         'gallery_click' => (string)($data['gallery_click'] ?? 'lightbox'),
         'attribute_notes' => (string)($data['attribute_notes'] ?? ''),
         'ebay_category_id' => (string)($data['ebay_category_id'] ?? ''),
         'amazon_product_type' => (string)($data['amazon_product_type'] ?? ''),
         'kleinanzeigen_category_id' => (string)($data['kleinanzeigen_category_id'] ?? ''),
         'mobile_category_id' => (string)($data['mobile_category_id'] ?? ''),
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id <= 0) {
         $groupKey = $this->normalizeKey((string)($data['group_key'] ?? ''));
         if ($groupKey === '') {
            $groupKey = $this->normalizeKey((string)($data['title'] ?? 'artikelgruppe'));
         }
         if ($groupKey === '') $groupKey = 'artikelgruppe';
         $groupKey = $this->uniqueProductGroupKey($groupKey);
         $values['group_key'] = $groupKey;
         $this->db()->insert($this->dd('shopProductGroup'), $values, 0);
         $this->clearRequestCache();
         return;
      }

      $this->db()->update($this->dd('shopProductGroup'), $values, 'id = ' . (int)$id, 0);
      $this->clearRequestCache();
   }



   public function updateShippingGroup(int $id, array $data): void {
      $this->install();
      $values = array(
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'shipping_way' => (string)($data['shipping_way'] ?? ''),
         'delivery_time' => (string)($data['delivery_time'] ?? ''),
         'shipping_gross' => (float)($data['shipping_gross'] ?? 0),
         'free_from_gross' => (float)($data['free_from_gross'] ?? -1),
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id <= 0) {
         $groupKey = $this->normalizeKey((string)($data['group_key'] ?? ''));
         if ($groupKey === '') {
            $groupKey = $this->normalizeKey((string)($data['title'] ?? 'versandgruppe'));
         }
         if ($groupKey === '') $groupKey = 'versandgruppe';
         $groupKey = $this->uniqueShippingGroupKey($groupKey);
         $values['group_key'] = $groupKey;
         $this->db()->insert($this->dd('shopShippingGroup'), $values, 0);
         return;
      }

      $this->db()->update($this->dd('shopShippingGroup'), $values, 'id = ' . (int)$id, 0);
   }



   public function updateChannelGroup(int $id, array $data, array $channelKeys): void {
      $this->install();
      $values = array(
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id <= 0) {
         $groupKey = $this->normalizeKey((string)($data['group_key'] ?? ''));
         if ($groupKey === '') {
            $groupKey = $this->normalizeKey((string)($data['title'] ?? 'channel-gruppe'));
         }
         if ($groupKey === '') $groupKey = 'channel-gruppe';
         $groupKey = $this->uniqueChannelGroupKey($groupKey);
         $values['group_key'] = $groupKey;
         if ($values['title'] === '') {
            $values['title'] = $groupKey;
         }
         $this->db()->insert($this->dd('shopChannelGroup'), $values, 0);
         $row = $this->db()->select1($this->dd('shopChannelGroup'), 'group_key = ' . $this->sqlValue($groupKey), 'id', 0);
         $id = (int)($row['id'] ?? 0);
      } else {
         $this->db()->update($this->dd('shopChannelGroup'), $values, 'id = ' . (int)$id, 0);
      }

      if ($id <= 0) {
         return;
      }
      $channels = $this->channels();
      foreach ($channels as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key === '') continue;
         $active = in_array($key, $channelKeys, true) ? 1 : 0;
         $this->db()->save(
            $this->dd('shopChannelGroupChannel'),
            array('channel_group_id' => $id, 'channel_key' => $key, 'active' => $active),
            'channel_group_id = ' . (int)$id . ' AND channel_key = ' . $this->sqlValue($key),
            0
         );
      }
   }



   public function deleteChannelGroup(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      $updated = (int)$this->db()->update(
         $this->dd('shopChannelGroup'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         'id = ' . (int)$id . ' AND trash = 0',
         0
      );
      if ($updated !== 1) {
         return 0;
      }

      $this->db()->update($this->dd('shopChannelGroupChannel'), array('active' => 0), 'channel_group_id = ' . (int)$id, 0);
      return 1;
   }



   public function deleteProductGroup(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      $updated = (int)$this->db()->update(
         $this->dd('shopProductGroup'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         'id = ' . (int)$id . ' AND trash = 0',
         0
      );
      $this->clearRequestCache();
      return $updated;
   }



   public function deleteShippingGroup(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      return (int)$this->db()->update(
         $this->dd('shopShippingGroup'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         'id = ' . (int)$id . ' AND trash = 0',
         0
      );
   }
}
