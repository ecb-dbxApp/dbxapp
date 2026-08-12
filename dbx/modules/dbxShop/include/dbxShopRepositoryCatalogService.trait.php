<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryCatalogServiceTrait {

   /**
    * Reichert eine Produktmenge mit einer kurzlebigen, gebuendelten Datensicht an.
    *
    * Die Sicht gilt nur fuer diesen Methodenaufruf. Dadurch bleiben Daten nach
    * Schreibzugriffen immer aktuell und es ist keine Cache-Invalidierung noetig.
    */
   private function decorateProducts(array $rows): array {
      if ($rows === array()) return array();

      $productIds = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['id'] ?? 0),
         $rows
      ))));
      if ($productIds === array()) return array();
      $productIdSql = implode(',', array_map('intval', $productIds));

      // Stammdaten und Zuordnungen werden je Operation genau einmal geladen.
      $productGroups = $this->db()->select(
         $this->dd('shopProductGroup'),
         'trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $productGroupById = $this->rowsById(is_array($productGroups) ? $productGroups : array());
      $productGroupMaps = $this->db()->select(
         $this->dd('shopProductGroupMap'),
         'product_id IN (' . $productIdSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $productGroupMapsByProduct = $this->rowsByIntKey(
         is_array($productGroupMaps) ? $productGroupMaps : array(),
         'product_id'
      );

      $shippingGroups = $this->db()->select(
         $this->dd('shopShippingGroup'),
         'trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $shippingGroupById = $this->rowsById(is_array($shippingGroups) ? $shippingGroups : array());
      $shippingMaps = $this->db()->select(
         $this->dd('shopProductShippingGroupMap'),
         'product_id IN (' . $productIdSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $shippingMapsByProduct = $this->rowsByIntKey(
         is_array($shippingMaps) ? $shippingMaps : array(),
         'product_id'
      );

      $channelGroups = $this->db()->select(
         $this->dd('shopChannelGroup'),
         'trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $channelGroupById = $this->rowsById(is_array($channelGroups) ? $channelGroups : array());
      $channelGroupMaps = $this->db()->select(
         $this->dd('shopProductChannelGroupMap'),
         'product_id IN (' . $productIdSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $channelGroupMaps = is_array($channelGroupMaps) ? $channelGroupMaps : array();
      $channelGroupMapsByProduct = $this->rowsByIntKey($channelGroupMaps, 'product_id');

      $channelIndex = array();
      foreach ($this->channels() as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key !== '') $channelIndex[$key] = $channel;
      }
      $directChannels = $this->db()->select(
         $this->dd('shopProductChannel'),
         'product_id IN (' . $productIdSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $directChannelsByProduct = $this->rowsByIntKey(
         is_array($directChannels) ? $directChannels : array(),
         'product_id'
      );
      $mappedChannelGroupIds = array_values(array_unique(array_filter(array_map(
         static fn($map) => (int)($map['channel_group_id'] ?? 0),
         $channelGroupMaps
      ))));
      $channelGroupChannelsByGroup = array();
      if ($mappedChannelGroupIds !== array()) {
         $channelGroupChannels = $this->db()->select(
            $this->dd('shopChannelGroupChannel'),
            'channel_group_id IN (' . implode(',', array_map('intval', $mappedChannelGroupIds)) . ') AND active = 1',
            '*',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $channelGroupChannelsByGroup = $this->rowsByIntKey(
            is_array($channelGroupChannels) ? $channelGroupChannels : array(),
            'channel_group_id'
         );
      }

      $definitions = $this->db()->select(
         $this->dd('shopAttributeDefinition'),
         'active = 1 AND trash = 0',
         '*',
         'sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $definitionsByGroup = $this->rowsByIntKey(
         is_array($definitions) ? $definitions : array(),
         'group_id'
      );
      $attributeValues = $this->db()->select(
         $this->dd('shopProductAttributeValue'),
         'product_id IN (' . $productIdSql . ') AND trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $attributeValuesByProduct = array();
      foreach ((is_array($attributeValues) ? $attributeValues : array()) as $value) {
         $productId = (int)($value['product_id'] ?? 0);
         $attributeId = (int)($value['attribute_id'] ?? 0);
         if ($productId > 0 && $attributeId > 0) {
            $attributeValuesByProduct[$productId][$attributeId] = $value;
         }
      }

      // Fuer Gruppenbilder reichen die Artikelgruppen der aktuellen Menge.
      $imageGroupIds = array();
      foreach ($rows as $row) {
         $productId = (int)($row['id'] ?? 0);
         $directGroupId = (int)($row['product_group_id'] ?? 0);
         if ($directGroupId > 0 && isset($productGroupById[$directGroupId])) {
            $imageGroupIds[$directGroupId] = $directGroupId;
            continue;
         }
         foreach ((array)($productGroupMapsByProduct[$productId] ?? array()) as $map) {
            $groupId = (int)($map['group_id'] ?? 0);
            if (isset($productGroupById[$groupId])) $imageGroupIds[$groupId] = $groupId;
         }
      }
      $imageWhere = 'trash = 0 AND active = 1 AND (product_id IN (' . $productIdSql . ')';
      if ($imageGroupIds !== array()) {
         $imageWhere .= ' OR group_id IN (' . implode(',', array_map('intval', $imageGroupIds)) . ')';
      }
      $imageWhere .= ')';
      $imageRows = $this->db()->select(
         $this->dd('shopProductImage'),
         $imageWhere,
         '*',
         'is_primary DESC, sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $imageRows = is_array($imageRows) ? $imageRows : array();
      $imagesByProduct = $this->rowsByIntKey($imageRows, 'product_id');

      foreach ($rows as &$row) {
         $productId = (int)($row['id'] ?? 0);

         $directGroupId = (int)($row['product_group_id'] ?? 0);
         if ($directGroupId > 0 && isset($productGroupById[$directGroupId])) {
            $row['groups'] = array($productGroupById[$directGroupId]);
         } else {
            $row['groups'] = $this->mappedGroupRows(
               (array)($productGroupMapsByProduct[$productId] ?? array()),
               $productGroupById,
               'group_id'
            );
         }
         $row['shipping_groups'] = $this->mappedGroupRows(
            (array)($shippingMapsByProduct[$productId] ?? array()),
            $shippingGroupById,
            'shipping_group_id'
         );
         $row['channel_groups'] = $this->mappedGroupRows(
            (array)($channelGroupMapsByProduct[$productId] ?? array()),
            $channelGroupById,
            'channel_group_id'
         );

         // Direkte Channel-Werte haben weiterhin Vorrang vor Vererbung.
         $channels = array();
         foreach ((array)($directChannelsByProduct[$productId] ?? array()) as $direct) {
            $key = (string)($direct['channel_key'] ?? '');
            $base = $channelIndex[$key] ?? array('title' => $key, 'sorter' => 9999);
            $channels[] = array(
               'channel_key' => $key,
               'title' => (string)($base['title'] ?? $key),
               'active' => (int)($direct['active'] ?? 0),
               'channel_sku' => (string)($direct['channel_sku'] ?? ''),
               'price_gross' => (float)($direct['price_gross'] ?? -1),
               'shipping_gross' => (float)($direct['shipping_gross'] ?? -1),
               '_sorter' => (int)($base['sorter'] ?? 9999),
            );
         }
         usort($channels, fn($a, $b) => ((int)($a['_sorter'] ?? 9999) <=> (int)($b['_sorter'] ?? 9999))
            ?: strcasecmp((string)($a['channel_key'] ?? ''), (string)($b['channel_key'] ?? '')));
         foreach ((array)($channelGroupMapsByProduct[$productId] ?? array()) as $groupMap) {
            $groupId = (int)($groupMap['channel_group_id'] ?? 0);
            foreach ((array)($channelGroupChannelsByGroup[$groupId] ?? array()) as $inherited) {
               $key = (string)($inherited['channel_key'] ?? '');
               $base = $channelIndex[$key] ?? array('title' => $key, 'sorter' => 9999);
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
         $row['channels'] = array();
         $seenChannels = array();
         foreach ($channels as $channel) {
            $key = (string)($channel['channel_key'] ?? '');
            if ($key === '' || isset($seenChannels[$key])) continue;
            $seenChannels[$key] = true;
            if ((int)($channel['active'] ?? 0) === 1) {
               unset($channel['_sorter']);
               $row['channels'][] = $channel;
            }
         }

         $groupIds = array();
         foreach ($row['groups'] as $group) {
            $groupId = (int)($group['id'] ?? 0);
            if ($groupId > 0) $groupIds[$groupId] = true;
         }
         $images = (array)($imagesByProduct[$productId] ?? array());
         foreach ($imageRows as $image) {
            if (isset($groupIds[(int)($image['group_id'] ?? 0)])) $images[] = $image;
         }
         $row['images'] = array();
         $seenImages = array();
         foreach ($images as $image) {
            $path = (string)($image['image_path'] ?? '');
            $mediaId = (int)($image['media_id'] ?? 0);
            $key = $mediaId > 0 ? 'm:' . $mediaId : 'p:' . $path;
            if (($mediaId <= 0 && $path === '') || isset($seenImages[$key])) continue;
            $seenImages[$key] = true;
            $row['images'][] = $image;
         }

         $row['attributes'] = array();
         $seenDefinitions = array();
         foreach ($row['groups'] as $group) {
            $groupId = (int)($group['id'] ?? 0);
            foreach ((array)($definitionsByGroup[$groupId] ?? array()) as $definition) {
               $attributeId = (int)($definition['id'] ?? 0);
               if ($attributeId <= 0 || isset($seenDefinitions[$attributeId])) continue;
               $seenDefinitions[$attributeId] = true;
               $value = $attributeValuesByProduct[$productId][$attributeId] ?? array();
               $definition['value_text'] = $value['value_text'] ?? '';
               $definition['value_num'] = $value['value_num'] ?? '';
               $definition['unit_override'] = $value['unit_override'] ?? '';
               $definition['value_active'] = $value['active'] ?? 0;
               $text = trim((string)$definition['value_text']);
               $unit = trim((string)$definition['unit_override'])
                  ?: trim((string)($definition['unit'] ?? ''));
               $definition['display_value'] = $text !== '' && $unit !== ''
                  ? $text . ' ' . $unit
                  : $text;
               $row['attributes'][] = $definition;
            }
         }

         $primary = $row['groups'][0] ?? array();
         $shipping = $row['shipping_groups'][0] ?? array();
         $row['effective_tax_rate'] = $this->taxRateForClass(
            (string)($primary['tax_class'] ?? ''),
            (float)($primary['default_tax_rate'] ?? 19)
         );
         $row['effective_shipping_gross'] = (string)($row['shipping_mode'] ?? 'group') === 'individual'
            && (float)($row['shipping_gross'] ?? -1) >= 0
            ? (float)$row['shipping_gross']
            : (float)($shipping['shipping_gross'] ?? $primary['default_shipping_gross'] ?? 0);
         $row['effective_shipping_way'] = (string)($shipping['shipping_way'] ?? '');
         $row['effective_delivery_time'] = trim((string)($row['delivery_time'] ?? '')) !== ''
            ? trim((string)$row['delivery_time'])
            : trim((string)($shipping['delivery_time'] ?? ''));
      }
      unset($row);
      return $rows;
   }

   private function decorateProduct(array $row): array {
      $row['groups'] = $this->groupsForProduct((int)$row['id']);
      $row['shipping_groups'] = $this->shippingGroupsForProduct((int)$row['id']);
      $row['channel_groups'] = $this->channelGroupsForProduct((int)$row['id']);
      $row['channels'] = $this->channelsForProduct((int)$row['id']);
      $row['images'] = $this->imagesForProduct((int)$row['id'], $row['groups']);
      $row['attributes'] = $this->attributesForProduct((int)$row['id']);
      $primary = $row['groups'][0] ?? array();
      $shipping = $row['shipping_groups'][0] ?? array();
      $row['effective_tax_rate'] = $this->taxRateForClass(
         (string)($primary['tax_class'] ?? ''),
         (float)($primary['default_tax_rate'] ?? 19)
      );
      $row['effective_shipping_gross'] = (string)($row['shipping_mode'] ?? 'group') === 'individual' && (float)($row['shipping_gross'] ?? -1) >= 0
         ? (float)$row['shipping_gross']
         : (float)($shipping['shipping_gross'] ?? $primary['default_shipping_gross'] ?? 0);
      $row['effective_shipping_way'] = (string)($shipping['shipping_way'] ?? '');
      $row['effective_delivery_time'] = trim((string)($row['delivery_time'] ?? '')) !== ''
         ? trim((string)$row['delivery_time'])
         : trim((string)($shipping['delivery_time'] ?? ''));
      return $row;
   }



   private function taxRatesConfig(): array {
      $fallback = array(
         'mwst1' => array('title' => 'MwSt. normal', 'rate' => '19'),
         'mwst2' => array('title' => 'MwSt. ermaessigt', 'rate' => '7'),
         'mwst3' => array('title' => 'MwSt. vorbereitet', 'rate' => '22'),
      );
      if (!function_exists('dbx')) {
         return $fallback;
      }
      $cfg = dbx()->get_cfg('dbxShop', 'tax_rates', $fallback);
      return is_array($cfg) && count($cfg) ? $cfg : $fallback;
   }



   private function taxRateForClass(string $taxClass, float $fallback): float {
      $taxClass = trim($taxClass);
      $rates = $this->taxRatesConfig();
      if ($taxClass !== '' && isset($rates[$taxClass]) && is_array($rates[$taxClass])) {
         return (float)($rates[$taxClass]['rate'] ?? $fallback);
      }
      $defaultClass = function_exists('dbx') ? (string)dbx()->get_cfg('dbxShop', 'default_tax_class', 'mwst1') : 'mwst1';
      if (isset($rates[$defaultClass]) && is_array($rates[$defaultClass])) {
         return (float)($rates[$defaultClass]['rate'] ?? $fallback);
      }
      return $fallback;
   }



   public function products(bool $activeOnly = true): array {
      $this->install();
      $where = $activeOnly ? 'active = 1 AND trash = 0' : 'trash = 0';
      $rows = $this->db()->select($this->dd('shopProduct'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      return $this->decorateProducts($rows);
   }

   /**
    * Liefert leichte, gebuendelt angereicherte Katalogzeilen.
    *
    * Suche und Filter benoetigen Gruppen, Attribute und den aktiven Channel,
    * aber noch keine Bilder, Versand- oder Channel-Gruppen. Diese Daten werden
    * erst fuer die sichtbare Report-Seite vollstaendig dekoriert.
    */
   public function catalogCandidates(string $channelKey = 'shop'): array {
      $this->install();
      $channelKey = trim($channelKey);
      $rows = $this->db()->select(
         $this->dd('shopProduct'),
         'active = 1 AND trash = 0',
         '*',
         'sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $rows = is_array($rows) ? $rows : array();
      if ($rows === array()) return array();

      $ids = array_values(array_filter(array_map(
         static fn($row) => (int)($row['id'] ?? 0),
         $rows
      )));
      if ($ids === array()) return array();
      $idSql = implode(',', array_map('intval', $ids));

      $groups = $this->groups();
      $groupById = array();
      foreach ($groups as $group) {
         $groupById[(int)($group['id'] ?? 0)] = $group;
      }
      $groupMaps = $this->db()->select(
         $this->dd('shopProductGroupMap'),
         'product_id IN (' . $idSql . ')',
         '*',
         'is_primary',
         'DESC',
         '',
         0,
         0,
         0
      );
      $groupMapsByProduct = array();
      foreach ((is_array($groupMaps) ? $groupMaps : array()) as $map) {
         $productId = (int)($map['product_id'] ?? 0);
         $groupId = (int)($map['group_id'] ?? 0);
         if ($productId > 0 && $groupId > 0) {
            $groupMapsByProduct[$productId][] = $map;
         }
      }

      $defs = $this->db()->select(
         $this->dd('shopAttributeDefinition'),
         'active = 1 AND trash = 0',
         '*',
         'sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $defById = array();
      foreach ((is_array($defs) ? $defs : array()) as $def) {
         $defById[(int)($def['id'] ?? 0)] = $def;
      }
      $attributeValues = $this->db()->select(
         $this->dd('shopProductAttributeValue'),
         'product_id IN (' . $idSql . ') AND trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $attributeValuesByProduct = array();
      foreach ((is_array($attributeValues) ? $attributeValues : array()) as $value) {
         $productId = (int)($value['product_id'] ?? 0);
         $attributeId = (int)($value['attribute_id'] ?? 0);
         if ($productId > 0 && isset($defById[$attributeId])) {
            $attributeValuesByProduct[$productId][$attributeId] = $value;
         }
      }

      // Direkte Channel-Zuordnungen haben wie in channelsForProduct()
      // Vorrang, auch wenn sie den geerbten Channel explizit deaktivieren.
      $directRows = $this->db()->select(
         $this->dd('shopProductChannel'),
         'product_id IN (' . $idSql . ') AND channel_key = ' . $this->sqlValue($channelKey),
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $directByProduct = array();
      foreach ((is_array($directRows) ? $directRows : array()) as $direct) {
         $directByProduct[(int)($direct['product_id'] ?? 0)] = $direct;
      }
      $channelGroupMaps = $this->db()->select(
         $this->dd('shopProductChannelGroupMap'),
         'product_id IN (' . $idSql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $channelGroupIds = array();
      foreach ((is_array($channelGroupMaps) ? $channelGroupMaps : array()) as $map) {
         $groupId = (int)($map['channel_group_id'] ?? 0);
         if ($groupId > 0) $channelGroupIds[$groupId] = $groupId;
      }
      $activeChannelGroups = array();
      if ($channelGroupIds) {
         $groupChannels = $this->db()->select(
            $this->dd('shopChannelGroupChannel'),
            'channel_group_id IN (' . implode(',', $channelGroupIds) . ')'
               . ' AND channel_key = ' . $this->sqlValue($channelKey)
               . ' AND active = 1',
            'channel_group_id',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         foreach ((is_array($groupChannels) ? $groupChannels : array()) as $groupChannel) {
            $activeChannelGroups[(int)($groupChannel['channel_group_id'] ?? 0)] = true;
         }
      }
      $inheritedByProduct = array();
      foreach ((is_array($channelGroupMaps) ? $channelGroupMaps : array()) as $map) {
         $productId = (int)($map['product_id'] ?? 0);
         $groupId = (int)($map['channel_group_id'] ?? 0);
         if ($productId > 0 && isset($activeChannelGroups[$groupId])) {
            $inheritedByProduct[$productId] = true;
         }
      }

      foreach ($rows as &$row) {
         $productId = (int)($row['id'] ?? 0);
         $rowGroups = array();
         $groupIds = array();
         $directGroupId = (int)($row['product_group_id'] ?? 0);
         if ($directGroupId > 0 && isset($groupById[$directGroupId])) {
            $rowGroups[] = $groupById[$directGroupId];
            $groupIds[$directGroupId] = true;
         } else {
            foreach ((array)($groupMapsByProduct[$productId] ?? array()) as $map) {
               $groupId = (int)($map['group_id'] ?? 0);
               if (isset($groupById[$groupId])) {
                  $rowGroups[] = $groupById[$groupId];
                  $groupIds[$groupId] = true;
               }
            }
         }
         $row['groups'] = $rowGroups;

         $row['attributes'] = array();
         foreach ((array)($attributeValuesByProduct[$productId] ?? array()) as $attributeId => $value) {
            $def = $defById[(int)$attributeId] ?? array();
            if (!$def || !isset($groupIds[(int)($def['group_id'] ?? 0)])) continue;
            $def['value_text'] = $value['value_text'] ?? '';
            $def['value_num'] = $value['value_num'] ?? '';
            $def['unit_override'] = $value['unit_override'] ?? '';
            $def['value_active'] = $value['active'] ?? 0;
            $text = trim((string)$def['value_text']);
            $unit = trim((string)$def['unit_override']) ?: trim((string)($def['unit'] ?? ''));
            $def['display_value'] = $text !== '' && $unit !== '' ? $text . ' ' . $unit : $text;
            $row['attributes'][] = $def;
         }

         $direct = $directByProduct[$productId] ?? null;
         $channelActive = is_array($direct)
            ? (int)($direct['active'] ?? 0) === 1
            : isset($inheritedByProduct[$productId]);
         $row['channels'] = $channelActive
            ? array(array('channel_key' => $channelKey, 'active' => 1))
            : array();
      }
      unset($row);
      return $rows;
   }

   /** Dekoriert nur die vom Report tatsaechlich sichtbaren Artikel. */
   public function productsByIds(array $ids): array {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      if ($ids === array()) return array();
      $rows = $this->db()->select(
         $this->dd('shopProduct'),
         'id IN (' . implode(',', $ids) . ') AND active = 1 AND trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $byId = array();
      foreach ($this->decorateProducts(is_array($rows) ? $rows : array()) as $row) {
         $byId[(int)($row['id'] ?? 0)] = $row;
      }
      $result = array();
      foreach ($ids as $id) {
         if (isset($byId[$id])) $result[] = $byId[$id];
      }
      return $result;
   }


   public function productBySku(string $sku, bool $activeOnly = true): ?array {
      $this->install();
      $where = 'sku = ' . $this->sqlValue($sku) . ' AND trash = 0';
      if ($activeOnly) {
         $where .= ' AND active = 1';
      }
      $row = $this->db()->select1($this->dd('shopProduct'), $where, '*', 0);
      return is_array($row) ? $this->decorateProduct($row) : null;
   }



   public function productById(int $id): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopProduct'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $this->decorateProduct($row) : null;
   }

   public function groupById(int $id): ?array {
      $this->install();
      if ($id <= 0) {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopProductGroup'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }

   private function stockEnabled(): bool {
      $cfg = function_exists('dbx') ? dbx()->get_cfg('dbxShop', '', array()) : array();
      return is_array($cfg) && !empty($cfg['stock_enabled']);
   }

   private function requiresStock(array $product): bool {
      return $this->stockEnabled() && (string)($product['product_type'] ?? '') === 'physical';
   }

   private function isPhysicalProduct(array $product): bool {
      return (string)($product['product_type'] ?? '') === 'physical';
   }

   public function stockIssuesForItems(array $items): array {
      $this->install();
      $issues = array();
      foreach ($items as $sku => $qty) {
         $qty = max(1, (int)$qty);
         $product = $this->productBySku((string)$sku);
         if (!$product || !$this->requiresStock($product)) {
            continue;
         }
         $stock = (int)($product['stock'] ?? 0);
         if ($stock < $qty) {
            $issues[] = array(
               'sku' => (string)($product['sku'] ?? $sku),
               'title' => (string)($product['title'] ?? $sku),
               'requested' => $qty,
               'stock' => $stock,
            );
         }
      }
      return $issues;
   }

   private function hasReservableStockSnapshots(array $snapshots): bool {
      if (!$this->stockEnabled()) {
         return false;
      }
      foreach ($snapshots as $item) {
         if ((int)($item['product_id'] ?? 0) > 0
            && (string)($item['product_type'] ?? '') === 'physical') return true;
      }
      return false;
   }

   private function reserveStockForSnapshots(array $snapshots): int {
      if (!$this->stockEnabled()) {
         return 0;
      }
      $reserved = 0;
      $db = $this->db();
      $server = $db->get_dd_server($this->dd('shopProduct'));
      $table = $db->get_dd_table($this->dd('shopProduct'));
      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      $now = date('Y-m-d H:i:s');
      foreach ($snapshots as $item) {
         $productId = (int)($item['product_id'] ?? 0);
         if ($productId <= 0 || (string)($item['product_type'] ?? '') !== 'physical') {
            continue;
         }
         $qty = max(1, (int)($item['qty'] ?? 1));
         $sql = 'UPDATE ' . $table
            . ' SET stock = stock - ' . $qty
            . ', update_date = ' . $this->sqlValue($now)
            . ', update_uid = ' . $uid
            . ' WHERE id = ' . $productId
            . ' AND trash = 0 AND stock >= ' . $qty;
         if ((int)$db->update_query($server, $sql) !== 1) {
            throw new \RuntimeException(
               'Nicht genuegend Lagerbestand fuer ' . (string)($item['title'] ?? $item['sku'] ?? 'Artikel') . '.'
            );
         }
         $reserved += $qty;
      }
      return $reserved;
   }

   private function releaseStockForOrder(array $order, string $reason): bool {
      $orderId = (int)($order['id'] ?? 0);
      if ($orderId <= 0 || (int)($order['stock_reserved'] ?? 0) !== 1 || (int)($order['stock_released'] ?? 0) === 1) {
         return false;
      }

      $db = $this->db();
      if ($db->begin($this->dd('shopOrder')) !== 1) {
         return false;
      }

      try {
         $now = date('Y-m-d H:i:s');
         if ($db->update($this->dd('shopOrder'), array(
            'stock_released' => 1,
            'stock_released_date' => $now,
            'update_date' => $now,
         ), 'id = ' . $orderId . ' AND trash = 0 AND stock_reserved = 1 AND stock_released = 0', 0) !== 1
            || (int)$db->_update_count !== 1) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }

         $released = 0;
         $server = $db->get_dd_server($this->dd('shopProduct'));
         $table = $db->get_dd_table($this->dd('shopProduct'));
         $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
         foreach ((array)($order['items'] ?? array()) as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            if ($productId <= 0) continue;
            $product = $db->select1(
               $this->dd('shopProduct'),
               'id = ' . $productId . ' AND trash = 0',
               'id,product_type',
               0
            );
            if (!is_array($product) || !$this->isPhysicalProduct($product)) continue;

            $qty = max(1, (int)($item['qty'] ?? 1));
            $sql = 'UPDATE ' . $table
               . ' SET stock = stock + ' . $qty
               . ', update_date = ' . $this->sqlValue($now)
               . ', update_uid = ' . $uid
               . ' WHERE id = ' . $productId . ' AND trash = 0';
            if ((int)$db->update_query($server, $sql) !== 1) {
               throw new \RuntimeException('stock_release_update_failed');
            }
            $released += $qty;
         }

         if ($released <= 0
            || !$this->addOrderHistory($orderId, 'stock_release', '', (string)$released, $reason)
            || $db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('stock_release_commit_failed');
         }
         return true;
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop stock release rollback order=(' . $orderId . ') error=(' . $e->getMessage() . ')');
         return false;
      }
   }



   private function channelExists(string $channelKey): bool {
      $channelKey = trim($channelKey);
      if ($channelKey === '') {
         return false;
      }
      return $this->db()->count($this->dd('shopChannel'), 'channel_key = ' . $this->sqlValue($channelKey) . ' AND trash = 0') > 0;
   }



   private function productGroupExists(int $groupId): bool {
      if ($groupId <= 0) {
         return false;
      }
      return $this->db()->count($this->dd('shopProductGroup'), 'id = ' . (int)$groupId . ' AND trash = 0') > 0;
   }



   public function deleteProducts(array $ids): int {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      if ($ids === array()) {
         return 0;
      }

      $where = 'id IN (' . implode(',', array_map('intval', $ids)) . ') AND trash = 0';
      $count = (int)$this->db()->count($this->dd('shopProduct'), $where);
      if ($count <= 0) {
         return 0;
      }
      $this->db()->update(
         $this->dd('shopProduct'),
         array('trash' => 1, 'active' => 0, 'update_date' => date('Y-m-d H:i:s')),
         $where,
         0
      );
      return $count;
   }



   public function addChannelToProducts(array $ids, string $channelKey): int {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      $channelKey = trim($channelKey);
      if ($ids === array() || !$this->channelExists($channelKey)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $ok = $this->db()->save(
            $this->dd('shopProductChannel'),
            array('product_id' => $id, 'channel_key' => $channelKey, 'active' => 1, 'channel_sku' => '', 'price_gross' => -1, 'shipping_gross' => -1),
            'product_id = ' . (int)$id . ' AND channel_key = ' . $this->sqlValue($channelKey),
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }



   public function removeChannelFromProducts(array $ids, string $channelKey): int {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      $channelKey = trim($channelKey);
      if ($ids === array() || !$this->channelExists($channelKey)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $ok = $this->db()->save(
            $this->dd('shopProductChannel'),
            array('product_id' => $id, 'channel_key' => $channelKey, 'active' => 0, 'channel_sku' => '', 'price_gross' => -1, 'shipping_gross' => -1),
            'product_id = ' . (int)$id . ' AND channel_key = ' . $this->sqlValue($channelKey),
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }



   public function setProductGroupForProducts(array $ids, int $groupId): int {
      $this->install();
      $ids = $this->normalizeProductIds($ids);
      if ($ids === array() || !$this->productGroupExists($groupId)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $this->db()->update($this->dd('shopProductGroupMap'), array('is_primary' => 0), 'product_id = ' . (int)$id, 0);
         $ok = $this->db()->save(
            $this->dd('shopProductGroupMap'),
            array('product_id' => $id, 'group_id' => $groupId, 'is_primary' => 1),
            'product_id = ' . (int)$id . ' AND group_id = ' . (int)$groupId,
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('product_group_id' => $groupId, 'update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }
}
