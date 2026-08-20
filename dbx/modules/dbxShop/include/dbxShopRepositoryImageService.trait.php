<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryImageServiceTrait {



   public function images_for_product(int $product_id, array $groups = array()): array {
      $this->install();
      $images = array();
      if ($product_id > 0) {
         $rows = $this->db()->select(
            $this->dd('shopProductImage'),
            'trash = 0 AND active = 1 AND product_id = ' . (int)$product_id,
            '*',
            'is_primary DESC, sorter ASC, title ASC',
            'ASC',
            '',
            0,
            0,
            0
         );
         $images = is_array($rows) ? $rows : array();
      }

      $group_ids = array();
      foreach ($groups as $group) {
         $id = (int)($group['id'] ?? 0);
         if ($id > 0) $group_ids[] = $id;
      }
      if ($group_ids !== array()) {
         $rows = $this->db()->select(
            $this->dd('shopProductImage'),
            'trash = 0 AND active = 1 AND group_id IN (' . implode(',', array_map('intval', $group_ids)) . ')',
            '*',
            'is_primary DESC, sorter ASC, title ASC',
            'ASC',
            '',
            0,
            0,
            0
         );
         $images = array_merge($images, is_array($rows) ? $rows : array());
      }

      $seen = array();
      $clean = array();
      foreach ($images as $image) {
         $path = (string)($image['image_path'] ?? '');
         $media_id = (int)($image['media_id'] ?? 0);
         $key = $media_id > 0 ? 'm:' . $media_id : 'p:' . $path;
         if (($media_id <= 0 && $path === '') || isset($seen[$key])) continue;
         $seen[$key] = true;
         $clean[] = $image;
      }
      return $clean;
   }



   public function product_image_counts(): array {
      $this->install();
      $counts = array();
      $products = $this->db()->select($this->dd('shopProduct'), 'trash = 0', 'id', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($products) ? $products : array()) as $product) {
         $product_id = (int)($product['id'] ?? 0);
         if ($product_id <= 0) continue;
         $groups = $this->groups_for_product($product_id);
         $counts[$product_id] = count($this->images_for_product($product_id, $groups));
      }
      return $counts;
   }



   public function all_images(): array {
      $this->install();
      $rows = $this->db()->select($this->dd('shopProductImage'), 'trash = 0', '*', 'active DESC, product_id DESC, group_id DESC, sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();

      $product_ids = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['product_id'] ?? 0),
         $rows
      ))));
      $group_ids = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['group_id'] ?? 0),
         $rows
      ))));
      $product_by_id = array();
      if ($product_ids !== array()) {
         $products = $this->db()->select(
            $this->dd('shopProduct'),
            'id IN (' . implode(',', array_map('intval', $product_ids)) . ')',
            'id,title',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $product_by_id = $this->rows_by_id(is_array($products) ? $products : array());
      }
      $group_by_id = array();
      if ($group_ids !== array()) {
         $groups = $this->db()->select(
            $this->dd('shopProductGroup'),
            'id IN (' . implode(',', array_map('intval', $group_ids)) . ')',
            'id,title',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $group_by_id = $this->rows_by_id(is_array($groups) ? $groups : array());
      }

      foreach ($rows as &$row) {
         $product_id = (int)($row['product_id'] ?? 0);
         $group_id = (int)($row['group_id'] ?? 0);
         $row['product_title'] = (string)($product_by_id[$product_id]['title'] ?? '');
         $row['group_title'] = (string)($group_by_id[$group_id]['title'] ?? '');
      }
      unset($row);
      return $rows;
   }


   public function update_image_media_reference(int $image_id, int $media_id, string $image_path = ''): void {
      $this->install();
      $image_id = max(0, $image_id);
      $media_id = max(0, $media_id);
      if ($image_id <= 0 || $media_id <= 0) {
         return;
      }

      $image_path = trim(str_replace('\\', '/', $image_path));
      if ($image_path === '') {
         $image_path = 'dbxmedia:' . $media_id;
      }

      $this->db()->update(
         $this->dd('shopProductImage'),
         array('media_id' => $media_id, 'image_path' => $image_path),
         'id = ' . (int)$image_id,
         0
      );
   }



   public function save_image(int $product_id, int $group_id, string $image_path, string $title, string $alt, int $is_primary = 0, int $sorter = 100): void {
      $this->install();
      $product_id = max(0, $product_id);
      $group_id = max(0, $group_id);
      $image_path = trim(str_replace('\\', '/', $image_path));
      if ($image_path === '' || ($product_id <= 0 && $group_id <= 0)) {
         return;
      }
      if ($group_id > 0 && $product_id <= 0) {
         $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), 'group_id = ' . (int)$group_id . ' AND product_id = 0 AND trash = 0', 0);
         $is_primary = 1;
      }
      $this->db()->save(
         $this->dd('shopProductImage'),
         array(
            'product_id' => $product_id,
            'group_id' => $group_id,
            'image_path' => $image_path,
            'title' => $title,
            'alt' => $alt,
            'is_primary' => $is_primary,
            'active' => 1,
            'sorter' => $sorter,
         ),
         'product_id = ' . (int)$product_id . ' AND group_id = ' . (int)$group_id . ' AND image_path = ' . $this->sql_value($image_path),
         0
      );
   }



   public function save_media_image(int $product_id, int $group_id, int $media_id, string $title = '', string $alt = '', int $is_primary = 0, int $sorter = 100): ?array {
      $this->install();
      $product_id = max(0, $product_id);
      $group_id = max(0, $group_id);
      $media_id = max(0, $media_id);
      if ($media_id <= 0 || ($product_id <= 0 && $group_id <= 0)) {
         return null;
      }
      if ($group_id > 0 && $product_id <= 0) {
         $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), 'group_id = ' . (int)$group_id . ' AND product_id = 0 AND trash = 0', 0);
         $is_primary = 1;
      }

      $path_key = 'dbxmedia:' . $media_id;
      $this->db()->save(
         $this->dd('shopProductImage'),
         array(
            'product_id' => $product_id,
            'group_id' => $group_id,
            'media_id' => $media_id,
            'image_path' => $path_key,
            'title' => $title,
            'alt' => $alt,
            'is_primary' => $is_primary,
            'active' => 1,
            'sorter' => $sorter,
         ),
         'product_id = ' . (int)$product_id . ' AND group_id = ' . (int)$group_id . ' AND image_path = ' . $this->sql_value($path_key),
         0
      );

      $data = $this->db()->select1(
         $this->dd('shopProductImage'),
         'product_id = ' . (int)$product_id . ' AND group_id = ' . (int)$group_id . ' AND image_path = ' . $this->sql_value($path_key),
         '*',
         0
      );
      return is_array($data) ? $data : null;
   }

   public function primary_image_for_group(int $group_id): ?array {
      $this->install();
      if ($group_id <= 0) {
         return null;
      }
      $rows = $this->db()->select(
         $this->dd('shopProductImage'),
         'trash = 0 AND active = 1 AND product_id = 0 AND group_id = ' . (int)$group_id,
         '*',
         'is_primary DESC, sorter ASC, title ASC',
         'ASC',
         '',
         0,
         1,
         0
      );
      $row = is_array($rows) && isset($rows[0]) ? $rows[0] : array();
      return is_array($row) ? $row : null;
   }


   public function remove_product_image_association(int $image_id, int $product_id = 0): bool {
      $this->install();
      $image_id = max(0, $image_id);
      $product_id = max(0, $product_id);
      if ($image_id <= 0) {
         return false;
      }

      $where = 'id = ' . (int)$image_id;
      if ($product_id > 0) {
         $group_maps = $this->db()->select($this->dd('shopProductGroupMap'), 'product_id = ' . (int)$product_id, 'group_id', '', 'ASC', '', 0, 0, 0);
         $group_ids = array();
         foreach ((is_array($group_maps) ? $group_maps : array()) as $group_map) {
            $group_id = (int)($group_map['group_id'] ?? 0);
            if ($group_id > 0) $group_ids[$group_id] = $group_id;
         }
         $parts = array('product_id = ' . (int)$product_id);
         if ($group_ids !== array()) {
            $parts[] = 'group_id IN (' . implode(',', array_map('intval', $group_ids)) . ')';
         }
         $where .= ' AND (' . implode(' OR ', $parts) . ')';
      }

      if ($this->db()->count($this->dd('shopProductImage'), $where) <= 0) {
         return false;
      }
      $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), $where, 0);
      return true;
   }
}
