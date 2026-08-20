<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryCoreServiceTrait {

   private function remember(string $key, callable $loader): array {
      if (!array_key_exists($key, $this->request_cache)) {
         $value = $loader();
         $this->request_cache[$key] = is_array($value) ? $value : array();
      }
      return $this->request_cache[$key];
   }

   private function clear_request_cache(): void {
      $this->request_cache = array();
   }

   private function db() {
      return dbx()->get_system_obj('dbxDB');
   }


   private function dd(string $name): string {
      return 'dbxShop|' . $name;
   }



   private function sql_value($value): string {
      if ($value === null) {
         return "''";
      }
      return "'" . str_replace("'", "''", (string)$value) . "'";
   }



   private function sql_like_value(string $value): string {
      return $this->sql_value('%' . $value . '%');
   }

   /** Gruppiert geladene Zeilen nach einem ganzzahligen Fremdschluessel. */
   private function rows_by_int_key(array $rows, string $field): array {
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
   private function rows_by_id(array $rows): array {
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
    * Löst eine bereits gebuendelt geladene Gruppenzuordnung auf.
    *
    * Alle drei Zuordnungsarten verwenden damit dieselbe stabile Sortierung.
    */
   private function mapped_group_rows(array $maps, array $group_by_id, string $group_id_field): array {
      $rows = array();
      foreach ($maps as $map) {
         $group_id = (int)($map[$group_id_field] ?? 0);
         if (!isset($group_by_id[$group_id])) continue;
         $row = $group_by_id[$group_id];
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


   private function normalize_product_ids(array $ids): array {
      $clean = array();
      foreach ($ids as $id) {
         $id = (int)$id;
         if ($id > 0) {
            $clean[$id] = $id;
         }
      }
      return array_values($clean);
   }



   private function normalize_key(string $value): string {
      $value = strtolower(trim($value));
      $value = preg_replace('~[^a-z0-9_-]+~', '-', $value);
      $value = trim((string)$value, '-_');
      return $value;
   }



   private function unique_product_group_key(string $base_key): string {
      return $this->unique_key('shop_product_group', $base_key);
   }



   private function unique_shipping_group_key(string $base_key): string {
      return $this->unique_key('shop_shipping_group', $base_key);
   }



   private function unique_channel_key(string $base_key): string {
      $key = $base_key !== '' ? $base_key : 'channel';
      $suffix = 2;
      while (true) {
         if ($this->db()->count($this->dd('shopChannel'), 'channel_key = ' . $this->sql_value($key)) <= 0) return $key;
         $key = $base_key . '-' . $suffix;
         $suffix++;
      }
   }



   private function unique_channel_group_key(string $base_key): string {
      return $this->unique_key('shop_channel_group', $base_key);
   }



   private function unique_key(string $table, string $base_key): string {
      $dd_map = array(
         'shop_product_group' => $this->dd('shopProductGroup'),
         'shop_shipping_group' => $this->dd('shopShippingGroup'),
         'shop_channel_group' => $this->dd('shopChannelGroup'),
      );
      if (!isset($dd_map[$table])) {
         return $base_key;
      }
      $key = $base_key !== '' ? $base_key : 'gruppe';
      $suffix = 2;
      while (true) {
         if ($this->db()->count($dd_map[$table], 'group_key = ' . $this->sql_value($key)) <= 0) return $key;
         $key = $base_key . '-' . $suffix;
         $suffix++;
      }
   }
}
