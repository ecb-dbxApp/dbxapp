<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryCatalogServiceTrait {

   /**
    * Reichert eine Produktmenge mit einer kurzlebigen, gebuendelten Datensicht an.
    *
    * Die Sicht gilt nur fuer diesen Methodenaufruf. Dadurch bleiben Daten nach
    * Schreibzugriffen immer aktuell und es ist keine Cache-Invalidierung noetig.
    */
   private function decorate_products(array $rows): array {
      if ($rows === array()) return array();

      $product_ids = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['id'] ?? 0),
         $rows
      ))));
      if ($product_ids === array()) return array();
      $product_id_sql = implode(',', array_map('intval', $product_ids));

      $context = $this->load_product_decoration_context($rows, $product_id_sql);
      return $this->apply_product_decoration($rows, $context);
   }

   /** Lädt alle für eine Produktmenge benötigten Zuordnungen gebündelt. */
   private function load_product_decoration_context(array $rows, string $product_id_sql): array {

      // Stammdaten und Zuordnungen werden je Operation genau einmal geladen.
      $product_groups = $this->db()->select(
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
      $product_group_by_id = $this->rows_by_id(is_array($product_groups) ? $product_groups : array());
      $product_group_maps = $this->db()->select(
         $this->dd('shopProductGroupMap'),
         'product_id IN (' . $product_id_sql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $product_group_maps_by_product = $this->rows_by_int_key(
         is_array($product_group_maps) ? $product_group_maps : array(),
         'product_id'
      );

      $shipping_groups = $this->db()->select(
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
      $shipping_group_by_id = $this->rows_by_id(is_array($shipping_groups) ? $shipping_groups : array());
      $shipping_maps = $this->db()->select(
         $this->dd('shopProductShippingGroupMap'),
         'product_id IN (' . $product_id_sql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $shipping_maps_by_product = $this->rows_by_int_key(
         is_array($shipping_maps) ? $shipping_maps : array(),
         'product_id'
      );

      $channel_groups = $this->db()->select(
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
      $channel_group_by_id = $this->rows_by_id(is_array($channel_groups) ? $channel_groups : array());
      $channel_group_maps = $this->db()->select(
         $this->dd('shopProductChannelGroupMap'),
         'product_id IN (' . $product_id_sql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $channel_group_maps = is_array($channel_group_maps) ? $channel_group_maps : array();
      $channel_group_maps_by_product = $this->rows_by_int_key($channel_group_maps, 'product_id');

      $channel_index = array();
      foreach ($this->channels() as $channel) {
         $key = (string)($channel['channel_key'] ?? '');
         if ($key !== '') $channel_index[$key] = $channel;
      }
      $direct_channels = $this->db()->select(
         $this->dd('shopProductChannel'),
         'product_id IN (' . $product_id_sql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $direct_channels_by_product = $this->rows_by_int_key(
         is_array($direct_channels) ? $direct_channels : array(),
         'product_id'
      );
      $mapped_channel_group_ids = array_values(array_unique(array_filter(array_map(
         static fn($map) => (int)($map['channel_group_id'] ?? 0),
         $channel_group_maps
      ))));
      $channel_group_channels_by_group = array();
      if ($mapped_channel_group_ids !== array()) {
         $channel_group_channels = $this->db()->select(
            $this->dd('shopChannelGroupChannel'),
            'channel_group_id IN (' . implode(',', array_map('intval', $mapped_channel_group_ids)) . ') AND active = 1',
            '*',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $channel_group_channels_by_group = $this->rows_by_int_key(
            is_array($channel_group_channels) ? $channel_group_channels : array(),
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
      $definitions_by_group = $this->rows_by_int_key(
         is_array($definitions) ? $definitions : array(),
         'group_id'
      );
      $attribute_values = $this->db()->select(
         $this->dd('shopProductAttributeValue'),
         'product_id IN (' . $product_id_sql . ') AND trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $attribute_values_by_product = array();
      foreach ((is_array($attribute_values) ? $attribute_values : array()) as $value) {
         $product_id = (int)($value['product_id'] ?? 0);
         $attribute_id = (int)($value['attribute_id'] ?? 0);
         if ($product_id > 0 && $attribute_id > 0) {
            $attribute_values_by_product[$product_id][$attribute_id] = $value;
         }
      }

      // Fuer Gruppenbilder reichen die Artikelgruppen der aktuellen Menge.
      $image_group_ids = array();
      foreach ($rows as $row) {
         $product_id = (int)($row['id'] ?? 0);
         $direct_group_id = (int)($row['product_group_id'] ?? 0);
         if ($direct_group_id > 0 && isset($product_group_by_id[$direct_group_id])) {
            $image_group_ids[$direct_group_id] = $direct_group_id;
            continue;
         }
         foreach ((array)($product_group_maps_by_product[$product_id] ?? array()) as $map) {
            $group_id = (int)($map['group_id'] ?? 0);
            if (isset($product_group_by_id[$group_id])) $image_group_ids[$group_id] = $group_id;
         }
      }
      $image_where = 'trash = 0 AND active = 1 AND (product_id IN (' . $product_id_sql . ')';
      if ($image_group_ids !== array()) {
         $image_where .= ' OR group_id IN (' . implode(',', array_map('intval', $image_group_ids)) . ')';
      }
      $image_where .= ')';
      $image_rows = $this->db()->select(
         $this->dd('shopProductImage'),
         $image_where,
         '*',
         'is_primary DESC, sorter ASC, title ASC',
         'ASC',
         '',
         0,
         0,
         0
      );
      $image_rows = is_array($image_rows) ? $image_rows : array();
      $images_by_product = $this->rows_by_int_key($image_rows, 'product_id');

      return compact(
         'product_group_by_id',
         'product_group_maps_by_product',
         'shipping_group_by_id',
         'shipping_maps_by_product',
         'channel_group_by_id',
         'channel_group_maps_by_product',
         'channel_index',
         'direct_channels_by_product',
         'channel_group_channels_by_group',
         'definitions_by_group',
         'attribute_values_by_product',
         'image_rows',
         'images_by_product'
      );
   }

   /** Reichert die Produkte ausschließlich aus der zuvor geladenen Datensicht an. */
   private function apply_product_decoration(array $rows, array $context): array {
      foreach ($rows as &$row) {
         $row = $this->apply_product_decoration_row($row, $context);
      }
      unset($row);
      return $rows;
   }

   /** Reichert genau ein Produkt aus der gebündelten Datensicht an. */
   private function apply_product_decoration_row(array $row, array $context): array {
      extract($context, EXTR_SKIP);
      $product_id = (int)($row['id'] ?? 0);

         $direct_group_id = (int)($row['product_group_id'] ?? 0);
         if ($direct_group_id > 0 && isset($product_group_by_id[$direct_group_id])) {
            $row['groups'] = array($product_group_by_id[$direct_group_id]);
         } else {
            $row['groups'] = $this->mapped_group_rows(
               (array)($product_group_maps_by_product[$product_id] ?? array()),
               $product_group_by_id,
               'group_id'
            );
         }
         $row['shipping_groups'] = $this->mapped_group_rows(
            (array)($shipping_maps_by_product[$product_id] ?? array()),
            $shipping_group_by_id,
            'shipping_group_id'
         );
         $row['channel_groups'] = $this->mapped_group_rows(
            (array)($channel_group_maps_by_product[$product_id] ?? array()),
            $channel_group_by_id,
            'channel_group_id'
         );

         // Direkte Channel-Werte haben weiterhin Vorrang vor Vererbung.
         $channels = array();
         foreach ((array)($direct_channels_by_product[$product_id] ?? array()) as $direct) {
            $key = (string)($direct['channel_key'] ?? '');
            $base = $channel_index[$key] ?? array('title' => $key, 'sorter' => 9999);
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
         foreach ((array)($channel_group_maps_by_product[$product_id] ?? array()) as $group_map) {
            $group_id = (int)($group_map['channel_group_id'] ?? 0);
            foreach ((array)($channel_group_channels_by_group[$group_id] ?? array()) as $inherited) {
               $key = (string)($inherited['channel_key'] ?? '');
               $base = $channel_index[$key] ?? array('title' => $key, 'sorter' => 9999);
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
         $seen_channels = array();
         foreach ($channels as $channel) {
            $key = (string)($channel['channel_key'] ?? '');
            if ($key === '' || isset($seen_channels[$key])) continue;
            $seen_channels[$key] = true;
            if ((int)($channel['active'] ?? 0) === 1) {
               unset($channel['_sorter']);
               $row['channels'][] = $channel;
            }
         }

         $group_ids = array();
         foreach ($row['groups'] as $group) {
            $group_id = (int)($group['id'] ?? 0);
            if ($group_id > 0) $group_ids[$group_id] = true;
         }
         $images = (array)($images_by_product[$product_id] ?? array());
         foreach ($image_rows as $image) {
            if (isset($group_ids[(int)($image['group_id'] ?? 0)])) $images[] = $image;
         }
         $row['images'] = array();
         $seen_images = array();
         foreach ($images as $image) {
            $path = (string)($image['image_path'] ?? '');
            $media_id = (int)($image['media_id'] ?? 0);
            $key = $media_id > 0 ? 'm:' . $media_id : 'p:' . $path;
            if (($media_id <= 0 && $path === '') || isset($seen_images[$key])) continue;
            $seen_images[$key] = true;
            $row['images'][] = $image;
         }

         $row['attributes'] = array();
         $seen_definitions = array();
         foreach ($row['groups'] as $group) {
            $group_id = (int)($group['id'] ?? 0);
            foreach ((array)($definitions_by_group[$group_id] ?? array()) as $definition) {
               $attribute_id = (int)($definition['id'] ?? 0);
               if ($attribute_id <= 0 || isset($seen_definitions[$attribute_id])) continue;
               $seen_definitions[$attribute_id] = true;
               $value = $attribute_values_by_product[$product_id][$attribute_id] ?? array();
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
         $row['effective_tax_rate'] = $this->tax_rate_for_class(
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
      return $row;
   }

   private function decorate_product(array $row): array {
      $row['groups'] = $this->groups_for_product((int)$row['id']);
      $row['shipping_groups'] = $this->shipping_groups_for_product((int)$row['id']);
      $row['channel_groups'] = $this->channel_groups_for_product((int)$row['id']);
      $row['channels'] = $this->channels_for_product((int)$row['id']);
      $row['images'] = $this->images_for_product((int)$row['id'], $row['groups']);
      $row['attributes'] = $this->attributes_for_product((int)$row['id']);
      $primary = $row['groups'][0] ?? array();
      $shipping = $row['shipping_groups'][0] ?? array();
      $row['effective_tax_rate'] = $this->tax_rate_for_class(
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



   private function tax_rates_config(): array {
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



   private function tax_rate_for_class(string $tax_class, float $fallback): float {
      $tax_class = trim($tax_class);
      $rates = $this->tax_rates_config();
      if ($tax_class !== '' && isset($rates[$tax_class]) && is_array($rates[$tax_class])) {
         return (float)($rates[$tax_class]['rate'] ?? $fallback);
      }
      $default_class = function_exists('dbx') ? (string)dbx()->get_cfg('dbxShop', 'default_tax_class', 'mwst1') : 'mwst1';
      if (isset($rates[$default_class]) && is_array($rates[$default_class])) {
         return (float)($rates[$default_class]['rate'] ?? $fallback);
      }
      return $fallback;
   }



   public function products(bool $active_only = true): array {
      $this->install();
      $where = $active_only ? 'active = 1 AND trash = 0' : 'trash = 0';
      $rows = $this->db()->select($this->dd('shopProduct'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();
      return $this->decorate_products($rows);
   }

   /**
    * Liefert leichte, gebuendelt angereicherte Katalogzeilen.
    *
    * Suche und Filter benoetigen Gruppen, Attribute und den aktiven Channel,
    * aber noch keine Bilder, Versand- oder Channel-Gruppen. Diese Daten werden
    * erst fuer die sichtbare Report-Seite vollstaendig dekoriert.
    */
   public function catalog_candidates(string $channel_key = 'shop'): array {
      $this->install();
      $channel_key = trim($channel_key);
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
      $id_sql = implode(',', array_map('intval', $ids));

      $groups = $this->groups();
      $group_by_id = array();
      foreach ($groups as $group) {
         $group_by_id[(int)($group['id'] ?? 0)] = $group;
      }
      $group_maps = $this->db()->select(
         $this->dd('shopProductGroupMap'),
         'product_id IN (' . $id_sql . ')',
         '*',
         'is_primary',
         'DESC',
         '',
         0,
         0,
         0
      );
      $group_maps_by_product = array();
      foreach ((is_array($group_maps) ? $group_maps : array()) as $map) {
         $product_id = (int)($map['product_id'] ?? 0);
         $group_id = (int)($map['group_id'] ?? 0);
         if ($product_id > 0 && $group_id > 0) {
            $group_maps_by_product[$product_id][] = $map;
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
      $def_by_id = array();
      foreach ((is_array($defs) ? $defs : array()) as $def) {
         $def_by_id[(int)($def['id'] ?? 0)] = $def;
      }
      $attribute_values = $this->db()->select(
         $this->dd('shopProductAttributeValue'),
         'product_id IN (' . $id_sql . ') AND trash = 0',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $attribute_values_by_product = array();
      foreach ((is_array($attribute_values) ? $attribute_values : array()) as $value) {
         $product_id = (int)($value['product_id'] ?? 0);
         $attribute_id = (int)($value['attribute_id'] ?? 0);
         if ($product_id > 0 && isset($def_by_id[$attribute_id])) {
            $attribute_values_by_product[$product_id][$attribute_id] = $value;
         }
      }

      // Direkte Channel-Zuordnungen haben wie in channelsForProduct()
      // Vorrang, auch wenn sie den geerbten Channel explizit deaktivieren.
      $direct_rows = $this->db()->select(
         $this->dd('shopProductChannel'),
         'product_id IN (' . $id_sql . ') AND channel_key = ' . $this->sql_value($channel_key),
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $direct_by_product = array();
      foreach ((is_array($direct_rows) ? $direct_rows : array()) as $direct) {
         $direct_by_product[(int)($direct['product_id'] ?? 0)] = $direct;
      }
      $channel_group_maps = $this->db()->select(
         $this->dd('shopProductChannelGroupMap'),
         'product_id IN (' . $id_sql . ')',
         '*',
         '',
         'ASC',
         '',
         0,
         0,
         0
      );
      $channel_group_ids = array();
      foreach ((is_array($channel_group_maps) ? $channel_group_maps : array()) as $map) {
         $group_id = (int)($map['channel_group_id'] ?? 0);
         if ($group_id > 0) $channel_group_ids[$group_id] = $group_id;
      }
      $active_channel_groups = array();
      if ($channel_group_ids) {
         $group_channels = $this->db()->select(
            $this->dd('shopChannelGroupChannel'),
            'channel_group_id IN (' . implode(',', $channel_group_ids) . ')'
               . ' AND channel_key = ' . $this->sql_value($channel_key)
               . ' AND active = 1',
            'channel_group_id',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         foreach ((is_array($group_channels) ? $group_channels : array()) as $group_channel) {
            $active_channel_groups[(int)($group_channel['channel_group_id'] ?? 0)] = true;
         }
      }
      $inherited_by_product = array();
      foreach ((is_array($channel_group_maps) ? $channel_group_maps : array()) as $map) {
         $product_id = (int)($map['product_id'] ?? 0);
         $group_id = (int)($map['channel_group_id'] ?? 0);
         if ($product_id > 0 && isset($active_channel_groups[$group_id])) {
            $inherited_by_product[$product_id] = true;
         }
      }

      foreach ($rows as &$row) {
         $product_id = (int)($row['id'] ?? 0);
         $row_groups = array();
         $group_ids = array();
         $direct_group_id = (int)($row['product_group_id'] ?? 0);
         if ($direct_group_id > 0 && isset($group_by_id[$direct_group_id])) {
            $row_groups[] = $group_by_id[$direct_group_id];
            $group_ids[$direct_group_id] = true;
         } else {
            foreach ((array)($group_maps_by_product[$product_id] ?? array()) as $map) {
               $group_id = (int)($map['group_id'] ?? 0);
               if (isset($group_by_id[$group_id])) {
                  $row_groups[] = $group_by_id[$group_id];
                  $group_ids[$group_id] = true;
               }
            }
         }
         $row['groups'] = $row_groups;

         $row['attributes'] = array();
         foreach ((array)($attribute_values_by_product[$product_id] ?? array()) as $attribute_id => $value) {
            $def = $def_by_id[(int)$attribute_id] ?? array();
            if (!$def || !isset($group_ids[(int)($def['group_id'] ?? 0)])) continue;
            $def['value_text'] = $value['value_text'] ?? '';
            $def['value_num'] = $value['value_num'] ?? '';
            $def['unit_override'] = $value['unit_override'] ?? '';
            $def['value_active'] = $value['active'] ?? 0;
            $text = trim((string)$def['value_text']);
            $unit = trim((string)$def['unit_override']) ?: trim((string)($def['unit'] ?? ''));
            $def['display_value'] = $text !== '' && $unit !== '' ? $text . ' ' . $unit : $text;
            $row['attributes'][] = $def;
         }

         $direct = $direct_by_product[$product_id] ?? null;
         $channel_active = is_array($direct)
            ? (int)($direct['active'] ?? 0) === 1
            : isset($inherited_by_product[$product_id]);
         $row['channels'] = $channel_active
            ? array(array('channel_key' => $channel_key, 'active' => 1))
            : array();
      }
      unset($row);
      return $rows;
   }

   /** Dekoriert nur die vom Report tatsaechlich sichtbaren Artikel. */
   public function products_by_ids(array $ids): array {
      $this->install();
      $ids = $this->normalize_product_ids($ids);
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
      $by_id = array();
      foreach ($this->decorate_products(is_array($rows) ? $rows : array()) as $row) {
         $by_id[(int)($row['id'] ?? 0)] = $row;
      }
      $result = array();
      foreach ($ids as $id) {
         if (isset($by_id[$id])) $result[] = $by_id[$id];
      }
      return $result;
   }


   public function product_by_sku(string $sku, bool $active_only = true): ?array {
      $this->install();
      $where = 'sku = ' . $this->sql_value($sku) . ' AND trash = 0';
      if ($active_only) {
         $where .= ' AND active = 1';
      }
      $row = $this->db()->select1($this->dd('shopProduct'), $where, '*', 0);
      return is_array($row) ? $this->decorate_product($row) : null;
   }



   public function product_by_id(int $id): ?array {
      $this->install();
      $row = $this->db()->select1($this->dd('shopProduct'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $this->decorate_product($row) : null;
   }

   public function group_by_id(int $id): ?array {
      $this->install();
      if ($id <= 0) {
         return null;
      }
      $row = $this->db()->select1($this->dd('shopProductGroup'), 'id = ' . (int)$id . ' AND trash = 0', '*', 0);
      return is_array($row) ? $row : null;
   }

   private function stock_enabled(): bool {
      $cfg = function_exists('dbx') ? dbx()->get_cfg('dbxShop', '', array()) : array();
      return is_array($cfg) && !empty($cfg['stock_enabled']);
   }

   private function requires_stock(array $product): bool {
      return $this->stock_enabled() && (string)($product['product_type'] ?? '') === 'physical';
   }

   private function is_physical_product(array $product): bool {
      return (string)($product['product_type'] ?? '') === 'physical';
   }

   public function stock_issues_for_items(array $items): array {
      $this->install();
      $issues = array();
      foreach ($items as $sku => $qty) {
         $qty = max(1, (int)$qty);
         $product = $this->product_by_sku((string)$sku);
         if (!$product || !$this->requires_stock($product)) {
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

   private function has_reservable_stock_snapshots(array $snapshots): bool {
      if (!$this->stock_enabled()) {
         return false;
      }
      foreach ($snapshots as $item) {
         if ((int)($item['product_id'] ?? 0) > 0
            && (string)($item['product_type'] ?? '') === 'physical') return true;
      }
      return false;
   }

   private function reserve_stock_for_snapshots(array $snapshots): int {
      if (!$this->stock_enabled()) {
         return 0;
      }
      $reserved = 0;
      $db = $this->db();
      $server = $db->get_dd_server($this->dd('shopProduct'));
      $table = $db->get_dd_table($this->dd('shopProduct'));
      $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
      $now = date('Y-m-d H:i:s');
      foreach ($snapshots as $item) {
         $product_id = (int)($item['product_id'] ?? 0);
         if ($product_id <= 0 || (string)($item['product_type'] ?? '') !== 'physical') {
            continue;
         }
         $qty = max(1, (int)($item['qty'] ?? 1));
         $sql = 'UPDATE ' . $table
            . ' SET stock = stock - ' . $qty
            . ', update_date = ' . $this->sql_value($now)
            . ', update_uid = ' . $uid
            . ' WHERE id = ' . $product_id
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

   private function release_stock_for_order(array $order, string $reason): bool {
      $order_id = (int)($order['id'] ?? 0);
      if ($order_id <= 0 || (int)($order['stock_reserved'] ?? 0) !== 1 || (int)($order['stock_released'] ?? 0) === 1) {
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
         ), 'id = ' . $order_id . ' AND trash = 0 AND stock_reserved = 1 AND stock_released = 0', 0) !== 1
            || (int)$db->_update_count !== 1) {
            $db->rollback($this->dd('shopOrder'));
            return false;
         }

         $released = 0;
         $server = $db->get_dd_server($this->dd('shopProduct'));
         $table = $db->get_dd_table($this->dd('shopProduct'));
         $uid = function_exists('dbx') ? (int)dbx()->user() : 0;
         foreach ((array)($order['items'] ?? array()) as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            if ($product_id <= 0) continue;
            $product = $db->select1(
               $this->dd('shopProduct'),
               'id = ' . $product_id . ' AND trash = 0',
               'id,product_type',
               0
            );
            if (!is_array($product) || !$this->is_physical_product($product)) continue;

            $qty = max(1, (int)($item['qty'] ?? 1));
            $sql = 'UPDATE ' . $table
               . ' SET stock = stock + ' . $qty
               . ', update_date = ' . $this->sql_value($now)
               . ', update_uid = ' . $uid
               . ' WHERE id = ' . $product_id . ' AND trash = 0';
            if ((int)$db->update_query($server, $sql) !== 1) {
               throw new \RuntimeException('stock_release_update_failed');
            }
            $released += $qty;
         }

         if ($released <= 0
            || !$this->add_order_history($order_id, 'stock_release', '', (string)$released, $reason)
            || $db->commit($this->dd('shopOrder')) !== 1) {
            throw new \RuntimeException('stock_release_commit_failed');
         }
         return true;
      } catch (\Throwable $e) {
         $db->rollback($this->dd('shopOrder'));
         dbx()->debug('#Shop stock release rollback order=(' . $order_id . ') error=(' . $e->getMessage() . ')');
         return false;
      }
   }



   private function channel_exists(string $channel_key): bool {
      $channel_key = trim($channel_key);
      if ($channel_key === '') {
         return false;
      }
      return $this->db()->count($this->dd('shopChannel'), 'channel_key = ' . $this->sql_value($channel_key) . ' AND trash = 0') > 0;
   }



   private function product_group_exists(int $group_id): bool {
      if ($group_id <= 0) {
         return false;
      }
      return $this->db()->count($this->dd('shopProductGroup'), 'id = ' . (int)$group_id . ' AND trash = 0') > 0;
   }



   public function delete_products(array $ids): int {
      $this->install();
      $ids = $this->normalize_product_ids($ids);
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



   public function add_channel_to_products(array $ids, string $channel_key): int {
      $this->install();
      $ids = $this->normalize_product_ids($ids);
      $channel_key = trim($channel_key);
      if ($ids === array() || !$this->channel_exists($channel_key)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $ok = $this->db()->save(
            $this->dd('shopProductChannel'),
            array('product_id' => $id, 'channel_key' => $channel_key, 'active' => 1, 'channel_sku' => '', 'price_gross' => -1, 'shipping_gross' => -1),
            'product_id = ' . (int)$id . ' AND channel_key = ' . $this->sql_value($channel_key),
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }



   public function remove_channel_from_products(array $ids, string $channel_key): int {
      $this->install();
      $ids = $this->normalize_product_ids($ids);
      $channel_key = trim($channel_key);
      if ($ids === array() || !$this->channel_exists($channel_key)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $ok = $this->db()->save(
            $this->dd('shopProductChannel'),
            array('product_id' => $id, 'channel_key' => $channel_key, 'active' => 0, 'channel_sku' => '', 'price_gross' => -1, 'shipping_gross' => -1),
            'product_id = ' . (int)$id . ' AND channel_key = ' . $this->sql_value($channel_key),
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }



   public function set_product_group_for_products(array $ids, int $group_id): int {
      $this->install();
      $ids = $this->normalize_product_ids($ids);
      if ($ids === array() || !$this->product_group_exists($group_id)) {
         return 0;
      }

      $count = 0;
      foreach ($ids as $id) {
         $this->db()->update($this->dd('shopProductGroupMap'), array('is_primary' => 0), 'product_id = ' . (int)$id, 0);
         $ok = $this->db()->save(
            $this->dd('shopProductGroupMap'),
            array('product_id' => $id, 'group_id' => $group_id, 'is_primary' => 1),
            'product_id = ' . (int)$id . ' AND group_id = ' . (int)$group_id,
            0
         );
         if ($ok) {
            $this->db()->update($this->dd('shopProduct'), array('product_group_id' => $group_id, 'update_date' => date('Y-m-d H:i:s')), 'id = ' . (int)$id . ' AND trash = 0', 0);
            $count++;
         }
      }
      return $count;
   }
}
