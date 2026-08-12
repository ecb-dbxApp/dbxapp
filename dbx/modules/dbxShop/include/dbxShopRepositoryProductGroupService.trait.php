<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryProductGroupServiceTrait {

   public function groupsForProduct(int $productId): array {
      $product = $this->db()->select1($this->dd('shopProduct'), 'id = ' . (int)$productId . ' AND trash = 0', 'product_group_id', 0);
      $directGroupId = (int)($product['product_group_id'] ?? 0);
      if ($directGroupId > 0) {
         $group = $this->groupById($directGroupId);
         if (is_array($group)) {
            return array($group);
         }
      }

      $maps = $this->db()->select($this->dd('shopProductGroupMap'), 'product_id = ' . (int)$productId, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $groupIds = array_values(array_unique(array_map(fn($row) => (int)($row['group_id'] ?? 0), $maps)));
      $groupIds = array_values(array_filter($groupIds, fn($id) => $id > 0));
      if ($groupIds === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopProductGroup'), 'id IN (' . implode(',', $groupIds) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $byId = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $byId[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['group_id'] ?? 0);
         if (isset($byId[$id])) {
            $row = $byId[$id];
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



   public function channelsForProduct(int $productId): array {
      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $channelIndex[(string)($channel['channel_key'] ?? '')] = $channel;
      }

      $channels = array();
      $direct = $this->db()->select($this->dd('shopProductChannel'), 'product_id = ' . (int)$productId, '*', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($direct) ? $direct : array()) as $row) {
         $key = (string)($row['channel_key'] ?? '');
         $base = $channelIndex[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
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

      $groupMaps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$productId, '*', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($groupMaps) ? $groupMaps : array()) as $groupMap) {
         $groupId = (int)($groupMap['channel_group_id'] ?? 0);
         if ($groupId <= 0) continue;
         $groupChannels = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . $groupId . ' AND active = 1', '*', '', 'ASC', '', 0, 0, 0);
         foreach ((is_array($groupChannels) ? $groupChannels : array()) as $row) {
            $key = (string)($row['channel_key'] ?? '');
            $base = $channelIndex[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
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



   public function productChannelOverrides(int $productId): array {
      $this->install();
      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $channelIndex[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $rows = $this->db()->select($this->dd('shopProductChannel'), 'product_id = ' . (int)$productId, '*', '', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      foreach ($rows as &$row) {
         $key = (string)($row['channel_key'] ?? '');
         $row['title'] = (string)($channelIndex[$key]['title'] ?? $key);
         $row['_sorter'] = (int)($channelIndex[$key]['sorter'] ?? 9999);
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



   public function inheritedChannelsForProduct(int $productId): array {
      $this->install();
      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $channelIndex[(string)($channel['channel_key'] ?? '')] = $channel;
      }
      $groupMaps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$productId, '*', '', 'ASC', '', 0, 0, 0);
      $rows = array();
      foreach ((is_array($groupMaps) ? $groupMaps : array()) as $groupMap) {
         $groupId = (int)($groupMap['channel_group_id'] ?? 0);
         if ($groupId <= 0) continue;
         $group = $this->db()->select1($this->dd('shopChannelGroup'), 'id = ' . $groupId . ' AND trash = 0 AND active = 1', '*', 0);
         if (!is_array($group)) continue;
         $groupChannels = $this->db()->select($this->dd('shopChannelGroupChannel'), 'channel_group_id = ' . $groupId . ' AND active = 1', '*', '', 'ASC', '', 0, 0, 0);
         foreach ((is_array($groupChannels) ? $groupChannels : array()) as $row) {
            $key = (string)($row['channel_key'] ?? '');
            $base = $channelIndex[$key] ?? array('channel_key' => $key, 'title' => $key, 'sorter' => 9999);
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
         $groupTitle = trim((string)($row['group_title'] ?? ''));
         if ($groupTitle !== '') {
            $out[$key]['group_titles'][$groupTitle] = $groupTitle;
         }
         unset($out[$key]['_sorter']);
      }
      return $out;
   }



   public function saveProductChannelOverrides(int $productId, array $activeChannelKeys): void {
      $this->install();
      $productId = max(0, $productId);
      if ($productId <= 0) {
         return;
      }

      $product = $this->productById($productId);
      if (!$product) {
         return;
      }

      $active = array();
      foreach ($activeChannelKeys as $key) {
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
            'product_id = ' . (int)$productId . ' AND channel_key = ' . $this->sqlValue($key),
            '*',
            0
         );
         $existing = is_array($existing) ? $existing : array();
         $channelSku = trim((string)($existing['channel_sku'] ?? ''));
         if ($channelSku === '') {
            $channelSku = (string)($product['sku'] ?? '');
         }
         $this->db()->save(
            $this->dd('shopProductChannel'),
            array(
               'product_id' => $productId,
               'channel_key' => $key,
               'active' => isset($active[$key]) ? 1 : 0,
               'channel_sku' => $channelSku,
               'price_gross' => (float)($existing['price_gross'] ?? -1),
               'shipping_gross' => (float)($existing['shipping_gross'] ?? -1),
               'external_listing_id' => (string)($existing['external_listing_id'] ?? ''),
               'external_offer_id' => (string)($existing['external_offer_id'] ?? ''),
               'export_status' => (string)($existing['export_status'] ?? ''),
               'export_message' => (string)($existing['export_message'] ?? ''),
               'export_payload' => (string)($existing['export_payload'] ?? ''),
               'last_export_date' => (string)($existing['last_export_date'] ?? ''),
            ),
            'product_id = ' . (int)$productId . ' AND channel_key = ' . $this->sqlValue($key),
            0
         );
      }

      $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$productId . ' AND trash = 0', 0);
   }



   public function shippingGroupsForProduct(int $productId): array {
      $maps = $this->db()->select($this->dd('shopProductShippingGroupMap'), 'product_id = ' . (int)$productId, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $ids = array_values(array_filter(array_unique(array_map(fn($row) => (int)($row['shipping_group_id'] ?? 0), $maps)), fn($id) => $id > 0));
      if ($ids === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopShippingGroup'), 'id IN (' . implode(',', $ids) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $byId = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $byId[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['shipping_group_id'] ?? 0);
         if (isset($byId[$id])) {
            $row = $byId[$id];
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



   public function channelGroupsForProduct(int $productId): array {
      $maps = $this->db()->select($this->dd('shopProductChannelGroupMap'), 'product_id = ' . (int)$productId, '*', 'is_primary DESC', 'ASC', '', 0, 0, 0);
      if (!is_array($maps) || $maps === array()) {
         return array();
      }
      $ids = array_values(array_filter(array_unique(array_map(fn($row) => (int)($row['channel_group_id'] ?? 0), $maps)), fn($id) => $id > 0));
      if ($ids === array()) {
         return array();
      }
      $groups = $this->db()->select($this->dd('shopChannelGroup'), 'id IN (' . implode(',', $ids) . ') AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $byId = array();
      foreach ((is_array($groups) ? $groups : array()) as $group) {
         $byId[(int)($group['id'] ?? 0)] = $group;
      }
      $rows = array();
      foreach ($maps as $map) {
         $id = (int)($map['channel_group_id'] ?? 0);
         if (isset($byId[$id])) {
            $row = $byId[$id];
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

   public function groupsByParent(int $parentId = 0, bool $activeOnly = true): array {
      $this->install();
      $where = 'trash = 0 AND parent_id = ' . max(0, (int)$parentId);
      if ($activeOnly) {
         $where .= ' AND active = 1';
      }
      $rows = $this->db()->select($this->dd('shopProductGroup'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }

   public function groupPath(int $groupId): array {
      $this->install();
      $path = array();
      $seen = array();
      $current = max(0, (int)$groupId);
      while ($current > 0 && !isset($seen[$current])) {
         $seen[$current] = true;
         $group = $this->groupById($current);
         if (!is_array($group)) {
            break;
         }
         array_unshift($path, $group);
         $current = (int)($group['parent_id'] ?? 0);
      }
      return $path;
   }

   private function wouldCreateGroupCycle(int $groupId, int $parentId): bool {
      if ($groupId <= 0 || $parentId <= 0) {
         return false;
      }
      if ($groupId === $parentId) {
         return true;
      }
      $seen = array($groupId => true);
      $current = $parentId;
      while ($current > 0 && !isset($seen[$current])) {
         $seen[$current] = true;
         $group = $this->groupById($current);
         if (!is_array($group)) {
            return false;
         }
         $current = (int)($group['parent_id'] ?? 0);
         if ($current === $groupId) {
            return true;
         }
      }
      return false;
   }

   private function nextGroupSorter(int $parentId): int {
      $rows = $this->db()->select(
         $this->dd('shopProductGroup'),
         'trash = 0 AND parent_id = ' . max(0, (int)$parentId),
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

   public function moveProductGroupParent(int $groupId, int $parentId): bool {
      $this->install();
      $groupId = max(0, (int)$groupId);
      $parentId = max(0, (int)$parentId);
      if ($groupId <= 0 || !is_array($this->groupById($groupId))) {
         return false;
      }
      if ($parentId > 0 && !is_array($this->groupById($parentId))) {
         return false;
      }
      if ($this->wouldCreateGroupCycle($groupId, $parentId)) {
         return false;
      }
      $this->db()->update(
         $this->dd('shopProductGroup'),
         array(
            'parent_id' => $parentId,
            'sorter' => $this->nextGroupSorter($parentId),
            'update_date' => date('Y-m-d H:i:s'),
         ),
         'id = ' . $groupId . ' AND trash = 0',
         0
      );
      $this->clearRequestCache();
      return true;
   }
}
