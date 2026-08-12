<?php
namespace dbx\dbxShop;

trait dbxShopServiceProductDisplayServiceTrait {

   private function mediaUrl(string $path): string {
      $path = trim(str_replace('\\', '/', $path));
      if ($path === '') {
         return '';
      }
      if (preg_match('~^https?://~i', $path) || substr($path, 0, 1) === '/') {
         return $path;
      }
      return dbx()->get_base_url() . ltrim($path, '/');
   }

   private function mediaItemUrl(array $image, bool $thumb = false): string {
      $mediaId = (int)($image['media_id'] ?? 0);
      if ($mediaId > 0) {
         $url = 'index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid=' . $mediaId;
         if ($thumb) {
            $url .= '&dbx_thumb=1';
         }
         return $url;
      }
      return $this->mediaUrl((string)($image['image_path'] ?? ''));
   }

   private function productImage(array $product): array {
      $images = $product['images'] ?? array();
      if (is_array($images) && isset($images[0]) && is_array($images[0])) {
         return $images[0];
      }
      return array(
         'image_path' => 'files/shop/img/software-dashboard.svg',
         'title' => $product['title'] ?? 'Artikel',
         'alt' => $product['title'] ?? 'Artikel',
      );
   }

   private function primaryGroup(array $product): array {
      return is_array($product['groups'] ?? null) ? (($product['groups'] ?? array())[0] ?? array()) : array();
   }

   private function templateName(string $value, string $fallback, string $prefix = ''): string {
      $value = preg_replace('~[^a-z0-9_-]+~i', '', trim($value));
      if ($value === '') {
         return $fallback;
      }
      if ($prefix !== '' && strpos($value, $prefix) !== 0) {
         return $fallback;
      }
      return $value;
   }

   private function shopTemplateExists(string $template): bool {
      $template = preg_replace('~[^a-z0-9_-]+~i', '', $template);
      if ($template === '') return false;
      return is_file(dirname(__DIR__) . '/tpl/htm/' . $template . '.htm');
   }

   /**
    * Liefert die im Shop-Template tatsaechlich verwendeten Replacement-Namen.
    *
    * Der Cache gilt nur fuer den aktuellen Request. Eigene Templates bleiben
    * kompatibel, weil jeder vorhandene bekannte Platzhalter erkannt wird.
    */
   private function shopTemplateFields(string $template): array {
      static $cache = array();
      $template = preg_replace('~[^a-z0-9_-]+~i', '', $template);
      if ($template === '') return array();
      if (isset($cache[$template])) return $cache[$template];

      $file = dirname(__DIR__) . '/tpl/htm/' . $template . '.htm';
      $source = is_file($file) ? file_get_contents($file) : '';
      $fields = array();
      if (is_string($source) && preg_match_all('~\\{([a-z][a-z0-9_]*)\\}~i', $source, $matches)) {
         foreach ($matches[1] as $field) {
            $fields[strtolower((string)$field)] = true;
         }
      }
      $cache[$template] = $fields;
      return $fields;
   }

   private function mediaTemplateExists(string $template): bool {
      $template = preg_replace('~[^a-z0-9_-]+~i', '', strtolower($template));
      if ($template === '') return false;
      return is_file(dirname(dirname(__DIR__)) . '/dbxContent/tpl/htm/media-' . $template . '.htm');
   }

   private function groupSetting(array $product, string $key, $fallback) {
      $group = $this->primaryGroup($product);
      $value = $group[$key] ?? $fallback;
      return $value === '' || $value === null ? $fallback : $value;
   }

   private function productVisual(array $product, string $class = ''): string {
      $image = $this->productImage($product);
      $src = $this->mediaItemUrl($image, true);
      $alt = (string)($image['alt'] ?? $image['title'] ?? $product['title'] ?? '');
      $count = count($product['images'] ?? array());
      $html = '<div class="dbx-shop-product-visual ' . $this->h($class) . '">';
      $html .= '<img class="dbx-shop-product-img" src="' . $this->h($src) . '" alt="' . $this->h($alt) . '" loading="lazy">';
      $html .= '<span class="dbx-shop-badge">' . $this->h($product['badge'] ?? 'Artikel') . '</span>';
      if ($count > 1) {
         $html .= '<span class="dbx-shop-image-count"><i class="bi bi-images"></i> ' . (int)$count . '</span>';
      }
      $html .= '</div>';
      return $html;
   }

   private function productGallery(array $product): string {
      $images = $product['images'] ?? array();
      if (!is_array($images) || $images === array()) {
         return $this->productVisual($product, 'dbx-shop-product-visual-large');
      }
      $overflow = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->groupSetting($product, 'gallery_overflow', 'grid')) ?: 'grid';
      $click = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->groupSetting($product, 'gallery_click', 'lightbox')) ?: 'lightbox';
      $visible = max(1, (int)$this->groupSetting($product, 'gallery_visible_count', 3));
      $imgSize = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->groupSetting($product, 'gallery_image_size', 'original')) ?: 'original';
      $lightboxWidth = preg_replace('~[^a-z0-9%._-]+~i', '', (string)$this->groupSetting($product, 'gallery_lightbox_width', '100vw')) ?: '100vw';
      $template = preg_replace('~[^a-z0-9_-]+~i', '', strtolower((string)$this->groupSetting($product, 'gallery_template', 'image-gallery'))) ?: 'image-gallery';
      if (!$this->mediaTemplateExists($template)) {
         $template = 'image-gallery';
      }
      $html = '<div class="dbx-shop-product-gallery dbx-content-media-gallery gallery-list gallery-template-' . $this->h($template) . '" data-dbx="lib=gallery|overflow=' . $this->h($overflow) . '|click=' . $this->h($click) . '|img-count=' . $visible . '|img-size=' . $this->h($imgSize) . '|lightbox-width=' . $this->h($lightboxWidth) . '">';
      foreach ($images as $image) {
         $url = $this->mediaItemUrl($image, false);
         $thumbUrl = $this->mediaItemUrl($image, true);
         if ($url === '') {
            continue;
         }
         $title = (string)($image['title'] ?? $product['title'] ?? '');
         $alt = (string)($image['alt'] ?? $title);
         $caption = $title;
         $html .= $this->tpl()->get_tpl('dbxContent|media-' . $template, array(
            'id' => (string)($image['media_id'] ?? ''),
            'url' => $this->h($url),
            'thumb_url' => $this->h($thumbUrl),
            'poster_url' => $this->h($thumbUrl),
            'media_type' => 'image',
            'title' => $this->h($title),
            'alt' => $this->h($alt),
            'caption' => $this->h($caption),
            'slot' => 'gallery',
            'mime' => '',
         ));
      }
      $html .= '</div>';
      return $html;
   }

   private function placeholder(string $headline, string $text, array $items = array()): string {
      $list = '';
      foreach ($items as $item) {
         $list .= '<li>' . $this->h($item) . '</li>';
      }

      return $this->tpl()->get_tpl('dbxShop|placeholder', array(
         'headline' => $this->h($headline),
         'text' => $this->h($text),
         'items' => $list !== '' ? '<ul>' . $list . '</ul>' : '',
      ));
   }
}
