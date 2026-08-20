<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryAttributeServiceTrait {



   public function attribute_definitions_for_group(int $group_id, bool $active_only = true): array {
      $this->install();
      $where = 'trash = 0 AND group_id = ' . (int)$group_id;
      if ($active_only) {
         $where .= ' AND active = 1';
      }
      $rows = $this->db()->select($this->dd('shopAttributeDefinition'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }



   public function attribute_definitions_for_product(int $product_id, bool $active_only = true): array {
      $defs = array();
      $seen = array();
      foreach ($this->groups_for_product($product_id) as $group) {
         foreach ($this->attribute_definitions_for_group((int)($group['id'] ?? 0), $active_only) as $definition) {
            $id = (int)($definition['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) continue;
            $seen[$id] = true;
            $defs[] = $definition;
         }
      }
      return $defs;
   }



   public function attributes_for_product(int $product_id): array {
      $this->install();
      $values = $this->db()->select($this->dd('shopProductAttributeValue'), 'product_id = ' . (int)$product_id . ' AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $value_by_attribute = array();
      foreach ((is_array($values) ? $values : array()) as $value) {
         $value_by_attribute[(int)($value['attribute_id'] ?? 0)] = $value;
      }

      $rows = array();
      foreach ($this->attribute_definitions_for_product($product_id, true) as $definition) {
         $attribute_id = (int)($definition['id'] ?? 0);
         if ($attribute_id <= 0) {
            continue;
         }
         $value = $value_by_attribute[$attribute_id] ?? array();
         $definition['value_text'] = $value['value_text'] ?? '';
         $definition['value_num'] = $value['value_num'] ?? '';
         $definition['unit_override'] = $value['unit_override'] ?? '';
         $definition['value_active'] = $value['active'] ?? 0;
         $rows[] = $definition;
      }
      foreach ($rows as &$row) {
         $value = trim((string)($row['value_text'] ?? ''));
         $unit = trim((string)($row['unit_override'] ?? '')) ?: trim((string)($row['unit'] ?? ''));
         $row['display_value'] = $value !== '' && $unit !== '' ? $value . ' ' . $unit : $value;
      }
      unset($row);
      return $rows;
   }



   public function all_attribute_definitions(): array {
      $this->install();
      return $this->remember('attribute_definitions', function(): array {
      $groups = $this->groups();
      $group_by_id = array();
      foreach ($groups as $group) {
         $group_by_id[(int)($group['id'] ?? 0)] = $group;
      }
      $defs = $this->db()->select($this->dd('shopAttributeDefinition'), 'trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $defs = is_array($defs) ? $defs : array();
      foreach ($defs as &$def) {
         $group = $group_by_id[(int)($def['group_id'] ?? 0)] ?? array();
         $def['group_title'] = (string)($group['title'] ?? '');
         $def['group_key'] = (string)($group['group_key'] ?? '');
         $def['_group_sorter'] = (int)($group['sorter'] ?? 9999);
      }
      unset($def);
      usort($defs, fn($a, $b) => ((int)($a['_group_sorter'] ?? 9999) <=> (int)($b['_group_sorter'] ?? 9999))
         ?: ((int)($a['sorter'] ?? 0) <=> (int)($b['sorter'] ?? 0))
         ?: strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
      foreach ($defs as &$def) {
         unset($def['_group_sorter']);
      }
      unset($def);
      return $defs;
      });
   }

   public function attribute_filter_definitions(): array {
      $this->install();
      return $this->remember('attribute_filter_definitions', function(): array {
      $defs = array_values(array_filter(
         $this->all_attribute_definitions(),
         fn($def) => (int)($def['active'] ?? 0) === 1 && (int)($def['filterable'] ?? 0) === 1
      ));
      $products = $this->db()->select($this->dd('shopProduct'), 'trash = 0 AND active = 1', 'id', '', 'ASC', '', 0, 0, 0);
      $active_products = array();
      foreach ((is_array($products) ? $products : array()) as $product) {
         $active_products[(int)($product['id'] ?? 0)] = true;
      }
      $definition_ids = array_values(array_filter(array_map(
         static fn($def) => (int)($def['id'] ?? 0),
         $defs
      )));
      $values_by_definition = array();
      if ($definition_ids && $active_products) {
         $rows = $this->db()->select(
            $this->dd('shopProductAttributeValue'),
            'attribute_id IN (' . implode(',', $definition_ids) . ')'
               . ' AND product_id IN (' . implode(',', array_keys($active_products)) . ')'
               . ' AND trash = 0 AND active = 1',
            'attribute_id,product_id,value_text',
            'value_text',
            'ASC',
            '',
            0,
            0,
            0
         );
         foreach ((is_array($rows) ? $rows : array()) as $row) {
            $attribute_id = (int)($row['attribute_id'] ?? 0);
            $value = trim((string)($row['value_text'] ?? ''));
            if ($attribute_id > 0 && $value !== '') {
               $values_by_definition[$attribute_id][$value] = $value;
            }
         }
      }
      foreach ($defs as &$def) {
         $values = array_values($values_by_definition[(int)($def['id'] ?? 0)] ?? array());
         natcasesort($values);
         $values = array_values($values);
         if ($values === array() && trim((string)($def['options'] ?? '')) !== '') {
            $values = preg_split('~[|;\r\n]+~', (string)$def['options']) ?: array();
            $values = array_values(array_filter(array_map('trim', $values), fn($v) => $v !== ''));
         }
         $def['values'] = $values;
      }
      unset($def);
      return $defs;
      });
   }


   public function save_attribute_definition(array $data): void {
      $this->install();
      $id = (int)($data['id'] ?? 0);
      $group_id = (int)($data['group_id'] ?? 0);
      $key = strtolower(trim(preg_replace('~[^a-z0-9_\\-]+~i', '_', (string)($data['attr_key'] ?? ''))));
      $title = trim((string)($data['title'] ?? ''));
      if ($group_id <= 0 || $key === '' || $title === '') {
         return;
      }
      $type = (string)($data['input_type'] ?? 'text');
      if (!in_array($type, array('text', 'select', 'number'), true)) {
         $type = 'text';
      }
      $values = array(
         'group_id' => $group_id,
         'attr_key' => $key,
         'title' => $title,
         'input_type' => $type,
         'unit' => trim((string)($data['unit'] ?? '')),
         'options' => trim((string)($data['options'] ?? '')),
         'required' => !empty($data['required']) ? 1 : 0,
         'filterable' => !empty($data['filterable']) ? 1 : 0,
         'comparable' => !empty($data['comparable']) ? 1 : 0,
         'active' => !empty($data['active']) ? 1 : 0,
         'sorter' => (int)($data['sorter'] ?? 100),
      );
      if ($id > 0) {
         $this->db()->update($this->dd('shopAttributeDefinition'), $values, 'id = ' . (int)$id, 0);
         $this->clear_request_cache();
         return;
      }
      $this->db()->save(
         $this->dd('shopAttributeDefinition'),
         $values,
         'group_id = ' . (int)$group_id . ' AND attr_key = ' . $this->sql_value($key),
         0
      );
      $this->clear_request_cache();
   }



   public function save_product_attribute_value(int $product_id, int $attribute_id, string $value): void {
      $this->install();
      if ($product_id <= 0 || $attribute_id <= 0) {
         return;
      }
      $value = trim($value);
      $num = $this->value_num($value);
      $this->db()->save(
         $this->dd('shopProductAttributeValue'),
         array(
            'product_id' => $product_id,
            'attribute_id' => $attribute_id,
            'value_text' => $value,
            'value_num' => $num,
            'active' => $value !== '' ? 1 : 0,
         ),
         'product_id = ' . (int)$product_id . ' AND attribute_id = ' . (int)$attribute_id,
         0
      );
      $this->clear_request_cache();
   }



   public function save_product_attribute_values(int $product_id, array $values): void {
      foreach ($values as $attribute_id => $value) {
         $this->save_product_attribute_value($product_id, (int)$attribute_id, (string)$value);
      }
   }
}
