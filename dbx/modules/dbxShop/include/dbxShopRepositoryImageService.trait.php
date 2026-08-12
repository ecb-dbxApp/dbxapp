<?php
namespace dbx\dbxShop;

trait dbxShopRepositoryImageServiceTrait {



   public function imagesForProduct(int $productId, array $groups = array()): array {
      $this->install();
      $images = array();
      if ($productId > 0) {
         $rows = $this->db()->select(
            $this->dd('shopProductImage'),
            'trash = 0 AND active = 1 AND product_id = ' . (int)$productId,
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

      $groupIds = array();
      foreach ($groups as $group) {
         $id = (int)($group['id'] ?? 0);
         if ($id > 0) $groupIds[] = $id;
      }
      if ($groupIds !== array()) {
         $rows = $this->db()->select(
            $this->dd('shopProductImage'),
            'trash = 0 AND active = 1 AND group_id IN (' . implode(',', array_map('intval', $groupIds)) . ')',
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
         $mediaId = (int)($image['media_id'] ?? 0);
         $key = $mediaId > 0 ? 'm:' . $mediaId : 'p:' . $path;
         if (($mediaId <= 0 && $path === '') || isset($seen[$key])) continue;
         $seen[$key] = true;
         $clean[] = $image;
      }
      return $clean;
   }



   public function productImageCounts(): array {
      $this->install();
      $counts = array();
      $products = $this->db()->select($this->dd('shopProduct'), 'trash = 0', 'id', '', 'ASC', '', 0, 0, 0);
      foreach ((is_array($products) ? $products : array()) as $product) {
         $productId = (int)($product['id'] ?? 0);
         if ($productId <= 0) continue;
         $groups = $this->groupsForProduct($productId);
         $counts[$productId] = count($this->imagesForProduct($productId, $groups));
      }
      return $counts;
   }



   public function allImages(): array {
      $this->install();
      $rows = $this->db()->select($this->dd('shopProductImage'), 'trash = 0', '*', 'active DESC, product_id DESC, group_id DESC, sorter ASC, title ASC', 'ASC', '', 0, 0, 0);
      $rows = is_array($rows) ? $rows : array();

      $productIds = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['product_id'] ?? 0),
         $rows
      ))));
      $groupIds = array_values(array_unique(array_filter(array_map(
         static fn($row) => (int)($row['group_id'] ?? 0),
         $rows
      ))));
      $productById = array();
      if ($productIds !== array()) {
         $products = $this->db()->select(
            $this->dd('shopProduct'),
            'id IN (' . implode(',', array_map('intval', $productIds)) . ')',
            'id,title',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $productById = $this->rowsById(is_array($products) ? $products : array());
      }
      $groupById = array();
      if ($groupIds !== array()) {
         $groups = $this->db()->select(
            $this->dd('shopProductGroup'),
            'id IN (' . implode(',', array_map('intval', $groupIds)) . ')',
            'id,title',
            '',
            'ASC',
            '',
            0,
            0,
            0
         );
         $groupById = $this->rowsById(is_array($groups) ? $groups : array());
      }

      foreach ($rows as &$row) {
         $productId = (int)($row['product_id'] ?? 0);
         $groupId = (int)($row['group_id'] ?? 0);
         $row['product_title'] = (string)($productById[$productId]['title'] ?? '');
         $row['group_title'] = (string)($groupById[$groupId]['title'] ?? '');
      }
      unset($row);
      return $rows;
   }


   public function updateImageMediaReference(int $imageId, int $mediaId, string $imagePath = ''): void {
      $this->install();
      $imageId = max(0, $imageId);
      $mediaId = max(0, $mediaId);
      if ($imageId <= 0 || $mediaId <= 0) {
         return;
      }

      $imagePath = trim(str_replace('\\', '/', $imagePath));
      if ($imagePath === '') {
         $imagePath = 'dbxmedia:' . $mediaId;
      }

      $this->db()->update(
         $this->dd('shopProductImage'),
         array('media_id' => $mediaId, 'image_path' => $imagePath),
         'id = ' . (int)$imageId,
         0
      );
   }



   public function saveImage(int $productId, int $groupId, string $imagePath, string $title, string $alt, int $isPrimary = 0, int $sorter = 100): void {
      $this->install();
      $productId = max(0, $productId);
      $groupId = max(0, $groupId);
      $imagePath = trim(str_replace('\\', '/', $imagePath));
      if ($imagePath === '' || ($productId <= 0 && $groupId <= 0)) {
         return;
      }
      if ($groupId > 0 && $productId <= 0) {
         $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), 'group_id = ' . (int)$groupId . ' AND product_id = 0 AND trash = 0', 0);
         $isPrimary = 1;
      }
      $this->db()->save(
         $this->dd('shopProductImage'),
         array(
            'product_id' => $productId,
            'group_id' => $groupId,
            'image_path' => $imagePath,
            'title' => $title,
            'alt' => $alt,
            'is_primary' => $isPrimary,
            'active' => 1,
            'sorter' => $sorter,
         ),
         'product_id = ' . (int)$productId . ' AND group_id = ' . (int)$groupId . ' AND image_path = ' . $this->sqlValue($imagePath),
         0
      );
   }



   public function saveMediaImage(int $productId, int $groupId, int $mediaId, string $title = '', string $alt = '', int $isPrimary = 0, int $sorter = 100): ?array {
      $this->install();
      $productId = max(0, $productId);
      $groupId = max(0, $groupId);
      $mediaId = max(0, $mediaId);
      if ($mediaId <= 0 || ($productId <= 0 && $groupId <= 0)) {
         return null;
      }
      if ($groupId > 0 && $productId <= 0) {
         $this->db()->update($this->dd('shopProductImage'), array('active' => 0, 'trash' => 1), 'group_id = ' . (int)$groupId . ' AND product_id = 0 AND trash = 0', 0);
         $isPrimary = 1;
      }

      $pathKey = 'dbxmedia:' . $mediaId;
      $this->db()->save(
         $this->dd('shopProductImage'),
         array(
            'product_id' => $productId,
            'group_id' => $groupId,
            'media_id' => $mediaId,
            'image_path' => $pathKey,
            'title' => $title,
            'alt' => $alt,
            'is_primary' => $isPrimary,
            'active' => 1,
            'sorter' => $sorter,
         ),
         'product_id = ' . (int)$productId . ' AND group_id = ' . (int)$groupId . ' AND image_path = ' . $this->sqlValue($pathKey),
         0
      );

      $data = $this->db()->select1(
         $this->dd('shopProductImage'),
         'product_id = ' . (int)$productId . ' AND group_id = ' . (int)$groupId . ' AND image_path = ' . $this->sqlValue($pathKey),
         '*',
         0
      );
      return is_array($data) ? $data : null;
   }

   public function primaryImageForGroup(int $groupId): ?array {
      $this->install();
      if ($groupId <= 0) {
         return null;
      }
      $rows = $this->db()->select(
         $this->dd('shopProductImage'),
         'trash = 0 AND active = 1 AND product_id = 0 AND group_id = ' . (int)$groupId,
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


   public function removeProductImageAssociation(int $imageId, int $productId = 0): bool {
      $this->install();
      $imageId = max(0, $imageId);
      $productId = max(0, $productId);
      if ($imageId <= 0) {
         return false;
      }

      $where = 'id = ' . (int)$imageId;
      if ($productId > 0) {
         $groupMaps = $this->db()->select($this->dd('shopProductGroupMap'), 'product_id = ' . (int)$productId, 'group_id', '', 'ASC', '', 0, 0, 0);
         $groupIds = array();
         foreach ((is_array($groupMaps) ? $groupMaps : array()) as $groupMap) {
            $groupId = (int)($groupMap['group_id'] ?? 0);
            if ($groupId > 0) $groupIds[$groupId] = $groupId;
         }
         $parts = array('product_id = ' . (int)$productId);
         if ($groupIds !== array()) {
            $parts[] = 'group_id IN (' . implode(',', array_map('intval', $groupIds)) . ')';
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
