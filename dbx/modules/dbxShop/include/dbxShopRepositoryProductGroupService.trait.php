<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryProductGroupServiceTrait {

   public function groups_for_product(int $product_id): array {
      $product = $this->db()->select1($this->dd('shopProduct'), 'id = ' . (int)$product_id . ' AND trash = 0', 'product_group_id', 0);
      $direct_group_id = (int)($product['product_group_id'] ?? 0);
      if ($direct_group_id > 0) {
         $group = $this->group_by_id($direct_group_id);
         if (is_array($group)) {
            return array($group);
         }
      }

      $maps = $this->db()->select($this->dd('shopProductGroupMap'), 'product_id = ' . (int)$product_id, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $group_ids = array_values(array_unique(array_map(fn($row) => (int)($row['group_id'] ?? 0), $maps)));
      $group_ids = array_values(array_filter($group_ids, fn($id) => $id > 0));
      if ($group_ids === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopProductGroup'), 'id IN (' . implode(',', $group_ids) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $by_id = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $by_id[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['group_id'] ?? 0);
         if (isset($by_id[$id])) {
            $row = $by_id[$id];
            $row['_is_primary'] = (int)($map['is_primary'] ?? 0);
            $rows[] = $row;
         }
      }
      usort($rows, fn($a, $b) => ((int)($b['_is_primary'] ?? 0) <=> (int)($a['_is_primary'] ?? 0))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_is_primary']);
      }
      unset($row);
      return $rows;
   }



   public function channels_for_product(int $product_id): array {
      $channel_index = array();
      foreach ($this->channels() as $channel) {
         $channel_index[(string)($channel['channel_key'] ?? '')] = $channel;
      }

      $channels = array();
      $direct = $this->db()->select($this->dd('shopProductChannel'), 'product_id = ' . (int)$product_id, '*', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($direct) ? $direct : array()) as $row) {
         $key = (string)($row['channel_key'] ?? '');
         $base = $channel_index[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
         $channels[] = array(
            'channel_key' => $key,
            'title' => (string)($base['title'] ?? $key),
            'active' => (int)($row['active'] ?? 0),
            'channel_sku' => (string)($row['channel_sku'] ?? ''),
            'price_gross' => (float)($row['price_gross'] ?? -1),
            'shipping_gross' => (float)($row['shipping_gross'] ?? -1),
            '_sorter' => (int)($base['sorter'] ?? 9999),
         );
      }
      usort($channels, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
         ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));

      $group_maps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$product_id, '*', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($group_maps) ? $group_maps : array()) as $group_map) {
         $group_id = (int)($group_map['channel_group_id'] ?? 0);
         if ($group_id <= 0) continue;
         $group_channels = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . $group_id . ' AND active = 1', '*', '', 'ASC', '', 0, 0, 0);
         foreach ((is_array($group_channels) ? $group_channels : array()) as $row) {
            $key = (string)($row['channel_key'] ?? '');
            $base = $channel_index[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
            $channels[] = array(
               'channel_key' => $key,
               'title' => (string)($base['title'] ?? $key),
               'active' => 1,
               'channel_sku' => '',
               'price_gross' => -1,
               'shipping_gross' => -1,
               '_sorter' => (int)($base['sorter'] ?? 9999),
            );
         }
      }

      $seen = array();
      $clean = array();
      foreach ($channels as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key === '' || isset($seen[$key])) continue;
         $seen[$key] = true;
         if ((int)($channel['active'] ?? 0) === 1) {
            unset($channel['_sorter']);
            $clean[] = $channel;
         }
      }
      return $clean;
   }



   public function product_channel_overrides(int $product_id): array {
      $this->install();
      $channel_index = array();
      foreach ($this->channels() as $channel) {
         $channel_index[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $rows = $this->db()->select($this->dd('shopProductChannel'), 'product_id = ' . (int)$product_id, '*', '', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $key = (string)($row['channel_key'] ?? '');
         $row['title'] = (string)($channel_index[$key]['title'] ?? $key);
         $row['_sorter'] = (int)($channel_index[$key]['sorter'] ?? 9999);
      }
      unset($row);
      usort($rows, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
         ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));
      $out = array();
      foreach ($rows as $row) {
         $key = (string)($row['channel_key'] ?? '');
         if ($key !== '') {
            unset($row['_sorter']);
            $out[$key] = $row;
         }
      }
      return $out;
   }



   public function inherited_channels_for_product(int $product_id): array {
      $this->install();
      $channel_index = array();
      foreach ($this->channels() as $channel) {
         $channel_index[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $group_maps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$product_id, '*', '', 'ASC', '', 0, 0, 0);
      $rows = array();
      foreach ((is_array($group_maps) ? $group_maps : array()) as $group_map) {
         $group_id = (int)($group_map['channel_group_id'] ?? 0);
         if ($group_id <= 0) continue;
         $group = $this->db()->select1($this->dd('shopChannelGroup'), 'id = ' . $group_id . ' AND trash = 0 AND active = 1', '*', 0);
         if (!is_array($group)) continue;
         $group_channels = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . $group_id . ' AND active = 1', '*', '', 'ASC', '', 0, 0, 0);
         foreach ((is_array($group_channels) ? $group_channels : array()) as $row) {
            $key = (string)($row['channel_key'] ?? '');
            $base = $channel_index[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
            $rows[] = array(
               'channel_key' => $key,
               'title' => (string)($base['title'] ?? $key),
               'active' => (int)($row['active'] ?? 0),
               'group_title' => (string)($group['title'] ?? ''),
               '_sorter' => (int)($base['sorter'] ?? 9999),
            );
         }
      }
      usort($rows, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
         ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));
      $out = array();
      foreach ($rows as $row) {
         $key = (string)($row['channel_key'] ?? '');
         if ($key === '') {
            continue;
         }
         if (!isset($out[$key])) {
            $out[$key] = $row;
            $out[$key]['group_titles'] = array();
         }
         $group_title = trim((string)($row['group_title'] ?? ''));
         if ($group_title !== '') {
            $out[$key]['group_titles'][$group_title] = $group_title;
         }
         unset($out[$key]['_sorter']);
      }
      return $out;
   }



   public function save_product_channel_overrides(int $product_id, array $active_channel_keys): void {
      $this->install();
      $product_id = max(0, $product_id);
      if ($product_id <= 0) {
         return;
      }

      $product = $this->product_by_id($product_id);
      if (!$product) {
         return;
      }

      $active = array();
      foreach ($active_channel_keys as $key) {
         $key = trim((string)$key);
         if ($key !== '') {
            $active[$key] = true;
         }
      }

      foreach ($this->channels() as $channel) {
         $key = trim((string)($channel['channel_key'] ?? ''));
         if ($key === '') {
            continue;
         }
         $existing = $this->db()->select1(
            $this->dd('shopProductChannel'),
            'product_id = ' . (int)$product_id . ' AND channel_key = ' . $this->sql_value($key),
            '*',
            0
         );
         $existing = is_array($existing) ? $existing : array();
         $channel_sku = trim((string)($existing['channel_sku'] ?? ''));
         if ($channel_sku === '') {
            $channel_sku = (string)($product['sku'] ?? '');
         }
         $this->db()->save(
            $this->dd('shopProductChannel'),
            array(
               'product_id' => $product_id,
               'channel_key' => $key,
               'active' => isset($active[$key]) ? 1 : 0,
               'channel_sku' => $channel_sku,
               'price_gross' => (float)($existing['price_gross'] ?? -1),
               'shipping_gross' => (float)($existing['shipping_gross'] ?? -1),
               'external_listing_id' => (string)($existing['external_listing_id'] ?? ''),
               'external_offer_id' => (string)($existing['external_offer_id'] ?? ''),
               'export_status' => (string)($existing['export_status'] ?? ''),
               'export_message' => (string)($existing['export_message'] ?? ''),
               'export_payload' => (string)($existing['export_payload'] ?? ''),
               'last_export_date' => (string)($existing['last_export_date'] ?? ''),
            ),
            'product_id = ' . (int)$product_id . ' AND channel_key = ' . $this->sql_value($key),
            0
         );
      }

      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$product_id . ' AND trash = 0', 0);
   }



   public function shipping_groups_for_product(int $product_id): array {
      $maps = $this->db()->select($this->dd('shopProductShippingGroupMap'), 'product_id = ' . (int)$product_id, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $ids = array_values(array_filter(array_unique(array_map(fn($row) => (int)($row['shipping_group_id'] ?? 0), $maps)), fn($id) => $id > 0));
      if ($ids === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopShippingGroup'), 'id IN (' . implode(',', $ids) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $by_id = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $by_id[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['shipping_group_id'] ?? 0);
         if (isset($by_id[$id])) {
            $row = $by_id[$id];
            $row['_is_primary'] = (int)($map['is_primary'] ?? 0);
            $rows[] = $row;
         }
      }
      usort($rows, fn($a, $b) => ((int)($b['_is_primary'] ?? 0) <=> (int)($a['_is_primary'] ?? 0))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_is_primary']);
      }
      unset($row);
      return $rows;
   }



   public function channel_groups_for_product(int $product_id): array {
      $maps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$product_id, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $ids = array_values(array_filter(array_unique(array_map(fn($row) => (int)($row['channel_group_id'] ?? 0), $maps)), fn($id) => $id > 0));
      if ($ids === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopChannelGroup'), 'id IN (' . implode(',', $ids) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $by_id = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $by_id[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['channel_group_id'] ?? 0);
         if (isset($by_id[$id])) {
            $row = $by_id[$id];
            $row['_is_primary'] = (int)($map['is_primary'] ?? 0);
            $rows[] = $row;
         }
      }
      usort($rows, fn($a, $b) => ((int)($b['_is_primary'] ?? 0) <=> (int)($a['_is_primary'] ?? 0))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($rows as &$row) {
         unset($row['_is_primary']);
      }
      unset($row);
      return $rows;
   }



   public function groups(): array {
      $this->install();
      return $this->remember('groups', function(): array {
         $rows = $this->db()->select($this->dd('shopProductGroup'), 'trash = 0', '*', 'parent_id ASC, sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
         return is_array($rows) ? $rows : array();
      });
   }

   public function groups_by_parent(int $parent_id = 0, bool $active_only = true): array {
      $this->install();
      $where = 'trash = 0 AND parent_id = ' . max(0, (int)$parent_id);
      if ($active_only) {
         $where .= ' AND active = 1';
      }
      $rows = $this->db()->select($this->dd('shopProductGroup'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   public function group_path(int $group_id): array {
      $this->install();
      $path = array();
      $seen = array();
      $current = max(0, (int)$group_id);
      while ($current > 0 && !isset($seen[$current])) {
         $seen[$current] = true;
         $group = $this->group_by_id($current);
         if (!is_array($group)) {
            break;
         }
         array_unshift($path, $group);
         $current = (int)($group['parent_id'] ?? 0);
      }
      return $path;
   }

   private function would_create_group_cycle(int $group_id, int $parent_id): bool {
      if ($group_id <= 0 || $parent_id <= 0) {
         return false;
      }
      if ($group_id === $parent_id) {
         return true;
      }
      $seen = array($group_id => true);
      $current = $parent_id;
      while ($current > 0 && !isset($seen[$current])) {
         $seen[$current] = true;
         $group = $this->group_by_id($current);
         if (!is_array($group)) {
            return false;
         }
         $current = (int)($group['parent_id'] ?? 0);
         if ($current === $group_id) {
            return true;
         }
      }
      return false;
   }

   private function next_group_sorter(int $parent_id): int {
      $rows = $this->db()->select(
         $this->dd('shopProductGroup'),
         'trash = 0 AND parent_id = ' . max(0, (int)$parent_id),
         'sorter',
         'sorter',
         'DESC',
         '',
         0,
         1,
         0
      );
      $max = is_array($rows) && isset($rows[0]) ? (int)($rows[0]['sorter'] ?? 0) : 0;
      return $max + 10;
   }

   public function move_product_group_parent(int $group_id, int $parent_id): bool {
      $this->install();
      $group_id = max(0, (int)$group_id);
      $parent_id = max(0, (int)$parent_id);
      if ($group_id <= 0 || !is_array($this->group_by_id($group_id))) {
         return false;
      }
      if ($parent_id > 0 && !is_array($this->group_by_id($parent_id))) {
         return false;
      }
      if ($this->would_create_group_cycle($group_id, $parent_id)) {
         return false;
      }
      $this->db()->update(
         $this->dd('shopProductGroup'),
         array(
            'parent_id' => $parent_id,
            'sorter' => $this->next_group_sorter($parent_id),
            'update_date' => date('Y-m-d H:i:s'),
         ),
         'id = ' . $group_id . ' AND trash = 0',
         0
      );
      $this->clear_request_cache();
      return true;
   }
}
