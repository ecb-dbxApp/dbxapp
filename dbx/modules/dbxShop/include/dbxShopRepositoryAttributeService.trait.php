<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryAttributeServiceTrait {



   public function attributeDefinitionsForGroup(int $groupId, bool $activeOnly = true): array {
      $this->install();
      $where = 'trash = 0 AND group_id = ' . (int)$groupId;
      if ($activeOnly) {
         $where .= ' AND active = 1';
      }
      $rows = $this->db()->select($this->dd('shopAttributeDefinition'), $where, '*', 'sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      return is_array($rows) ? $rows : array();
   }



   public function attributeDefinitionsForProduct(int $productId, bool $activeOnly = true): array {
      $defs = array();
      $seen = array();
      foreach ($this->groupsForProduct($productId) as $group) {
         foreach ($this->attributeDefinitionsForGroup((int)($group['id'] ?? 0), $activeOnly) as $definition) {
            $id = (int)($definition['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) continue;
            $seen[$id] = true;
            $defs[] = $definition;
         }
      }
      return $defs;
   }



   public function attributesForProduct(int $productId): array {
      $this->install();
      $values = $this->db()->select($this->dd('shopProductAttributeValue'), 'product_id = ' . (int)$productId . ' AND trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $valueByAttribute = array();
      foreach ((is_array($values) ? $values : array()) as $value) {
         $valueByAttribute[(int)($value['attribute_id'] ?? 0)] = $value;
      }

      $rows = array();
      foreach ($this->attributeDefinitionsForProduct($productId, true) as $definition) {
         $attributeId = (int)($definition['id'] ?? 0);
         if ($attributeId <= 0) {
            continue;
         }
         $value = $valueByAttribute[$attributeId] ?? array();
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



   public function allAttributeDefinitions(): array {
      $this->install();
      return $this->remember('attribute_definitions', function(): array {
      $groups = $this->groups();
      $groupById = array();
      foreach ($groups as $group) {
         $groupById[(int)($group['id'] ?? 0)] = $group;
      }
      $defs = $this->db()->select($this->dd('shopAttributeDefinition'), 'trash = 0', '*', '', 'ASC', '', 0, 0, 0);
      $defs = is_array($defs) ? $defs : array();
      foreach ($defs as &$def) {
         $group = $groupById[(int)($def['group_id'] ?? 0)] ?? array();
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

   public function attributeFilterDefinitions(): array {
      $this->install();
      return $this->remember('attribute_filter_definitions', function(): array {
      $defs = array_values(array_filter(
         $this->allAttributeDefinitions(),
         fn($def) => (int)($def['active'] ?? 0) === 1 && (int)($def['filterable'] ?? 0) === 1
      ));
      $products = $this->db()->select($this->dd('shopProduct'), 'trash = 0 AND active = 1', 'id', '', 'ASC', '', 0, 0, 0);
      $activeProducts = array();
      foreach ((is_array($products) ? $products : array()) as $product) {
         $activeProducts[(int)($product['id'] ?? 0)] = true;
      }
      $definitionIds = array_values(array_filter(array_map(
         static fn($def) => (int)($def['id'] ?? 0),
         $defs
      )));
      $valuesByDefinition = array();
      if ($definitionIds && $activeProducts) {
         $rows = $this->db()->select(
            $this->dd('shopProductAttributeValue'),
            'attribute_id IN (' . implode(',', $definitionIds) . ')'
               . ' AND product_id IN (' . implode(',', array_keys($activeProducts)) . ')'
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
            $attributeId = (int)($row['attribute_id'] ?? 0);
            $value = trim((string)($row['value_text'] ?? ''));
            if ($attributeId > 0 && $value !== '') {
               $valuesByDefinition[$attributeId][$value] = $value;
            }
         }
      }
      foreach ($defs as &$def) {
         $values = array_values($valuesByDefinition[(int)($def['id'] ?? 0)] ?? array());
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


   public function saveAttributeDefinition(array $data): void {
      $this->install();
      $id = (int)($data['id'] ?? 0);
      $groupId = (int)($data['group_id'] ?? 0);
      $key = strtolower(trim(preg_replace('~[^a-z0-9_\\-]+~i', '_', (string)($data['attr_key'] ?? ''))));
      $title = trim((string)($data['title'] ?? ''));
      if ($groupId <= 0 || $key === '' || $title === '') {
         return;
      }
      $type = (string)($data['input_type'] ?? 'text');
      if (!in_array($type, array('text', 'select', 'number'), true)) {
         $type = 'text';
      }
      $values = array(
         'group_id' => $groupId,
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
         $this->clearRequestCache();
         return;
      }
      $this->db()->save(
         $this->dd('shopAttributeDefinition'),
         $values,
         'group_id = ' . (int)$groupId . ' AND attr_key = ' . $this->sqlValue($key),
         0
      );
      $this->clearRequestCache();
   }



   public function saveProductAttributeValue(int $productId, int $attributeId, string $value): void {
      $this->install();
      if ($productId <= 0 || $attributeId <= 0) {
         return;
      }
      $value = trim($value);
      $num = $this->valueNum($value);
      $this->db()->save(
         $this->dd('shopProductAttributeValue'),
         array(
            'product_id' => $productId,
            'attribute_id' => $attributeId,
            'value_text' => $value,
            'value_num' => $num,
            'active' => $value !== '' ? 1 : 0,
         ),
         'product_id = ' . (int)$productId . ' AND attribute_id = ' . (int)$attributeId,
         0
      );
      $this->clearRequestCache();
   }



   public function saveProductAttributeValues(int $productId, array $values): void {
      foreach ($values as $attributeId => $value) {
         $this->saveProductAttributeValue($productId, (int)$attributeId, (string)$value);
      }
   }
}
