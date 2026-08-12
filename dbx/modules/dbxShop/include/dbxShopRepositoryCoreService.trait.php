<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryCoreServiceTrait {

   private function remember(string $key, callable $loader): array {
      if (!array_key_exists($key, $this->requestCache)) {
         $value = $loader();
         $this->requestCache[$key] = is_array($value) ? $value : array();
      }
      return $this->requestCache[$key];
   }

   private function clearRequestCache(): void {
      $this->requestCache = array();
   }

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }


   private function dd(string $name): string {
      return 'dbxShop|' . $name;
   }



   private function sqlValue($value): string {
      if ($value === null) {
         return "''";
      }
      return "'" . str_replace("'", "''", (string)$value) . "'";
   }



   private function sqlLikeValue(string $value): string {
      return $this->sqlValue('%' . $value . '%');
   }

   /** Gruppiert geladene Zeilen nach einem ganzzahligen Fremdschluessel. */
   private function rowsByIntKey(array $rows, string $field): array {
      $indexed = array();
      foreach ($rows as $row) {
         $key = (int)($row[$field] ?? 0);
         if ($key > 0) {
            $indexed[$key][] = $row;
         }
      }
      return $indexed;
   }

   /** Indexiert geladene Stammdaten nach ihrer ID. */
   private function rowsById(array $rows): array {
      $indexed = array();
      foreach ($rows as $row) {
         $id = (int)($row['id'] ?? 0);
         if ($id > 0) {
            $indexed[$id] = $row;
         }
      }
      return $indexed;
   }

   /**
    * Loest eine bereits gebuendelt geladene Gruppenzuordnung auf.
    *
    * Alle drei Zuordnungsarten verwenden damit dieselbe stabile Sortierung.
    */
   private function mappedGroupRows(array $maps, array $groupById, string $groupIdField): array {
      $rows = array();
      foreach ($maps as $map) {
         $groupId = (int)($map[$groupIdField] ?? 0);
         if (!isset($groupById[$groupId])) continue;
         $row = $groupById[$groupId];
         $row['_is_primary'] = (int)($map['is_primary'] ?? 0);
         $rows[] = $row;
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


   private function normalizeProductIds(array $ids): array {
      $clean = array();
      foreach ($ids as $id) {
         $id = (int)$id;
         if ($id > 0) {
            $clean[$id] = $id;
         }
      }
      return array_values($clean);
   }



   private function normalizeKey(string $value): string {
      $value = strtolower(trim($value));
      $value = preg_replace('~[^a-z0-9_-]+~', '-', $value);
      $value = trim((string)$value, '-_');
      return $value;
   }



   private function uniqueProductGroupKey(string $baseKey): string {
      return $this->uniqueKey('shop_product_group', $baseKey);
   }



   private function uniqueShippingGroupKey(string $baseKey): string {
      return $this->uniqueKey('shop_shipping_group', $baseKey);
   }



   private function uniqueChannelKey(string $baseKey): string {
      $key = $baseKey !== '' ? $baseKey : 'channel';
      $suffix = 2;
      while (true) {
         if ($this->db()->count($this->dd('shopChannel'), 'channel_key = ' . $this->sqlValue($key)) <= 0) return $key;
         $key = $baseKey . '-' . $suffix;
         $suffix++;
      }
   }



   private function uniqueChannelGroupKey(string $baseKey): string {
      return $this->uniqueKey('shop_channel_group', $baseKey);
   }



   private function uniqueKey(string $table, string $baseKey): string {
      $ddMap = array(
         'shop_product_group' => $this->dd('shopProductGroup'),
         'shop_shipping_group' => $this->dd('shopShippingGroup'),
         'shop_channel_group' => $this->dd('shopChannelGroup'),
      );
      if (!isset($ddMap[$table])) {
         return $baseKey;
      }
      $key = $baseKey !== '' ? $baseKey : 'gruppe';
      $suffix = 2;
      while (true) {
         if ($this->db()->count($ddMap[$table], 'group_key = ' . $this->sqlValue($key)) <= 0) return $key;
         $key = $baseKey . '-' . $suffix;
         $suffix++;
      }
   }
}
