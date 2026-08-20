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



   public function channel_by_id(int $id): ?array {
      $this->install();
      if ($id <= 0) {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopChannel'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }



   public function channel_by_key(string $key): ?array {
      $this->install();
      $key = trim($key);
      if ($key === '') {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopChannel'), 'channel_key = ' . $this->sql_value($key) . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }



   public function update_channel(int $id, array $data): void {
      $this->install();
      $secret_fields = array('api_client_secret', 'api_access_token', 'api_refresh_token', 'api_password', 'webhook_secret');
      $existing = $id > 0 ? ($this->channel_by_id($id) ?: array()) : array();
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
      foreach ($secret_fields as $field) {
         $posted = (string)($data[$field] ?? '');
         $values[$field] = ($id > 0 && $posted === '') ? (string)($existing[$field] ?? '') : $posted;
      }

      if ($id <= 0) {
         $channel_key = $this->normalize_key((string)($data['channel_key'] ?? ''));
         if ($channel_key === '') {
            $channel_key = $this->normalize_key((string)($data['title'] ?? 'channel'));
         }
         if ($channel_key === '') $channel_key = 'channel';
         $channel_key = $this->unique_channel_key($channel_key);
         $values['channel_key'] = $channel_key;
         $this->db()->insert($this->dd('shopChannel'), $values, 0);
         $this->clear_request_cache();
         return;
      }

      $this->db()->update($this->dd('shopChannel'), $values, 'id = ' . (int)$id, 0);
      $this->clear_request_cache();
   }



   public function delete_channel(int $id): int {
      $this->install();
      if ($id <= 0) {
         return 0;
      }

      $channel = $this->channel_by_id($id);
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
         'channel_key = ' . $this->sql_value((string)($channel['channel_key'] ?? '')),
         0
      );
      $this->clear_request_cache();
      return 1;
   }



   public function test_channel_connection(int $id): array {
      $this->install();
      $channel = $this->channel_by_id($id);
      if (!$channel) {
         return array('ok' => false, 'message' => 'Channel wurde nicht gefunden.');
      }

      $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
      $result = is_object($connector) && method_exists($connector, 'test')
         ? (array)$connector->test($channel)
         : array('ok' => false, 'message' => 'Channel-Connector konnte nicht geladen werden.');

      $ok = !empty($result['ok']);
      $message = (string)($result['message'] ?? '');
      $this->save_channel_test_result($id, $ok, $message);
      return array('ok' => $ok, 'message' => $message);
   }

   private function product_has_active_channel(array $product, string $channel_key): bool {
      foreach ((array)($product['channels'] ?? array()) as $channel) {
         if ((string)($channel['channel_key'] ?? '') === $channel_key && (int)($channel['active'] ?? 0) === 1) {
            return true;
         }
      }
      return false;
   }

   private function product_channel_row_for_export(array $product, string $channel_key): array {
      $product_id = (int)($product['id'] ?? 0);
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $product_id . ' AND channel_key = ' . $this->sql_value($channel_key),
         '*',
         0
      );
      if (is_array($row)) {
         return $row;
      }

      $values = array(
         'product_id' => $product_id,
         'channel_key' => $channel_key,
         'active' => 1,
         'channel_sku' => (string)($product['sku'] ?? ''),
         'price_gross' => -1,
         'shipping_gross' => -1,
         'export_status' => 'ready',
      );
      $this->db()->save(
         $this->dd('shopProductChannel'),
         $values,
         'product_id = ' . $product_id . ' AND channel_key = ' . $this->sql_value($channel_key),
         0
      );
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $product_id . ' AND channel_key = ' . $this->sql_value($channel_key),
         '*',
         0
      );
      return is_array($row) ? $row : $values;
   }

   public function product_channel_mapping(int $product_id, string $channel_key): ?array {
      $this->install();
      $product = $this->product_by_id($product_id);
      if (!$product) {
         return null;
      }
      $channel = $this->channel_by_key($channel_key);
      if (!$channel) {
         return null;
      }
      $row = $this->db()->select1(
         $this->dd('shopProductChannel'),
         'product_id = ' . $product_id . ' AND channel_key = ' . $this->sql_value($channel_key),
         '*',
         0
      );
      if (!is_array($row)) {
         $row = array(
            'product_id' => $product_id,
            'channel_key' => $channel_key,
            'active' => $this->product_has_active_channel($product, $channel_key) ? 1 : 0,
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

   public function save_product_channel_mapping(int $product_id, string $channel_key, array $data): void {
      $this->install();
      $product = $this->product_by_id($product_id);
      $channel = $this->channel_by_key($channel_key);
      if (!$product || !$channel) {
         return;
      }

      $mapping = is_array($data['mapping'] ?? null) ? $data['mapping'] : array();
      $note = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      if ($note === false) {
         $note = '';
      }

      $existing = $this->product_channel_row_for_export($product, $channel_key);
      $this->db()->save(
         $this->dd('shopProductChannel'),
         array(
            'product_id' => $product_id,
            'channel_key' => $channel_key,
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
         'product_id = ' . $product_id . ' AND channel_key = ' . $this->sql_value($channel_key),
         0
      );
      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . $product_id . ' AND trash = 0', 0);
   }

   private function save_product_channel_export_result(int $product_id, string $channel_key, array $result): void {
      $payload = $result['payload'] ?? array();
      $payload_json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if ($payload_json === false) {
         $payload_json = '';
      }
      $this->db()->save(
         $this->dd('shopProductChannel'),
         array(
            'product_id' => $product_id,
            'channel_key' => $channel_key,
            'active' => 1,
            'external_listing_id' => (string)($result['external_listing_id'] ?? ''),
            'external_offer_id' => (string)($result['external_offer_id'] ?? ''),
            'export_status' => (string)($result['status'] ?? (!empty($result['ok']) ? 'exported' : 'failed')),
            'export_message' => (string)($result['message'] ?? ''),
            'export_payload' => $payload_json,
            'last_export_date' => date('Y-m-d H:i:s'),
         ),
         'product_id = ' . $product_id . ' AND channel_key = ' . $this->sql_value($channel_key),
         0
      );
      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . $product_id . ' AND trash = 0', 0);
   }

   public function export_product_to_channel(int $product_id, string $channel_key): array {
      $this->install();
      $channel_key = trim($channel_key);
      $product = $this->product_by_id($product_id);
      if (!$product) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Artikel wurde nicht gefunden.');
      }
      $channel = $this->channel_by_key($channel_key);
      if (!$channel || (int)($channel['active'] ?? 0) !== 1) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Channel ist nicht aktiv oder wurde nicht gefunden.');
      }
      if ((int)($channel['export_enabled'] ?? 0) !== 1) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Export ist fuer diesen Channel nicht aktiv.');
      }
      if (!$this->product_has_active_channel($product, $channel_key)) {
         return array('ok' => false, 'status' => 'failed', 'message' => 'Artikel ist diesem Channel nicht aktiv zugeordnet.');
      }

      $product_channel = $this->product_channel_row_for_export($product, $channel_key);
      $connector = dbx()->get_include_obj('dbxShopChannelConnector', 'dbxShop');
      $result = is_object($connector) && method_exists($connector, 'exportProduct')
         ? (array)$connector->export_product($channel, $product, $product_channel)
         : array('ok' => false, 'status' => 'failed', 'message' => 'Channel-Export-Connector konnte nicht geladen werden.');
      $this->save_product_channel_export_result((int)$product['id'], $channel_key, $result);
      return $result;
   }

   public function export_products_to_channel(array $ids, string $channel_key): array {
      $this->install();
      $ids = $this->normalize_product_ids($ids);
      $summary = array('total' => count($ids), 'ok' => 0, 'failed' => 0, 'messages' => array());
      foreach ($ids as $id) {
         $result = $this->export_product_to_channel($id, $channel_key);
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

   private function save_channel_test_result(int $id, bool $ok, string $message): void {
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



   public function shipping_groups(): array {
      $this->install();
      $rows = $this->db()->select($this->dd('shopShippingGroup'), 'trash = 0', '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }



   public function channel_groups(): array {
      $this->install();
      $groups = $this->db()->select($this->dd('shopChannelGroup'), 'trash = 0', '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $groups = is_array($groups) ? $groups : array();
      foreach ($groups as &$group) {
         $group['channels'] = $this->channels_for_channel_group((int)$group['id']);
      }
      unset($group);
      return $groups;
   }



   public function channels_for_channel_group(int $channel_group_id): array {
      $channel_index = array();
      foreach ($this->channels() as $channel) {
         $channel_index[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $rows = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . (int)$channel_group_id, '*', '', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $key = (string)($row['channel_key'] ?? '');
         $row['title'] = (string)($channel_index[$key]['title'] ?? $key);
         $row['_sorter'] = (int)($channel_index[$key]['sorter'] ?? 9999);
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



   public function update_product_group(int $id, array $data): void {
      $this->install();
      $parent_id = max(0, (int)($data['parent_id'] ?? 0));
      if ($id > 0 && $parent_id === $id) {
         $parent_id = 0;
      }
      $values = array(
         'parent_id' => $parent_id,
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'tax_class' => (string)($data['tax_class'] ?? 'mwst1'),
         'default_tax_rate' => $this->tax_rate_for_class((string)($data['tax_class'] ?? 'mwst1'), (float)($data['default_tax_rate'] ?? 19)),
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
         $group_key = $this->normalize_key((string)($data['group_key'] ?? ''));
         if ($group_key === '') {
            $group_key = $this->normalize_key((string)($data['title'] ?? 'artikelgruppe'));
         }
         if ($group_key === '') $group_key = 'artikelgruppe';
         $group_key = $this->unique_product_group_key($group_key);
         $values['group_key'] = $group_key;
         $this->db()->insert($this->dd('shopProductGroup'), $values, 0);
         $this->clear_request_cache();
         return;
      }

      $this->db()->update($this->dd('shopProductGroup'), $values, 'id = ' . (int)$id, 0);
      $this->clear_request_cache();
   }



   public function update_shipping_group(int $id, array $data): void {
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
         $group_key = $this->normalize_key((string)($data['group_key'] ?? ''));
         if ($group_key === '') {
            $group_key = $this->normalize_key((string)($data['title'] ?? 'versandgruppe'));
         }
         if ($group_key === '') $group_key = 'versandgruppe';
         $group_key = $this->unique_shipping_group_key($group_key);
         $values['group_key'] = $group_key;
         $this->db()->insert($this->dd('shopShippingGroup'), $values, 0);
         return;
      }

      $this->db()->update($this->dd('shopShippingGroup'), $values, 'id = ' . (int)$id, 0);
   }



   public function update_channel_group(int $id, array $data, array $channel_keys): void {
      $this->install();
      $values = array(
         'title' => (string)($data['title'] ?? ''),
         'description' => (string)($data['description'] ?? ''),
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id <= 0) {
         $group_key = $this->normalize_key((string)($data['group_key'] ?? ''));
         if ($group_key === '') {
            $group_key = $this->normalize_key((string)($data['title'] ?? 'channel-gruppe'));
         }
         if ($group_key === '') $group_key = 'channel-gruppe';
         $group_key = $this->unique_channel_group_key($group_key);
         $values['group_key'] = $group_key;
         if ($values['title'] === '') {
            $values['title'] = $group_key;
         }
         $this->db()->insert($this->dd('shopChannelGroup'), $values, 0);
         $row = $this->db()->select1($this->dd('shopChannelGroup'), 'group_key = ' . $this->sql_value($group_key), 'id', 0);
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
         $active = in_array($key, $channel_keys, true) ? 1 : 0;
         $this->db()->save(
            $this->dd('shopChannelGroupChannel'),
            array('channel_group_id' => $id, 'channel_key' => $key, 'active' => $active),
            'channel_group_id = ' . (int)$id . ' AND channel_key = ' . $this->sql_value($key),
            0
         );
      }
   }



   public function delete_channel_group(int $id): int {
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



   public function delete_product_group(int $id): int {
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
      $this->clear_request_cache();
      return $updated;
   }



   public function delete_shipping_group(int $id): int {
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
