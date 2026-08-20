<?php
namespace dbx\dbxShop;

require_once __DIR__ . '/dbxShopMediaUrl.class.php';

trait dbxShopServiceProductDisplayServiceTrait {



   private function product_image(array $product): array {
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

   private function primary_group(array $product): array {
      return is_array($product['groups'] ?? null) ? (($product['groups'] ?? array())[0] ?? array()) : array();
   }

   private function template_name(string $value, string $fallback, string $prefix = ''): string {
      $value = preg_replace('~[^a-z0-9_-]+~i', '', trim($value));
      if ($value === '') {
         return $fallback;
      }
      if ($prefix !== '' && strpos($value, $prefix) !== 0) {
         return $fallback;
      }
      return $value;
   }

   private function shop_template_exists(string $template): bool {
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
   private function shop_template_fields(string $template): array {
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

   private function media_template_exists(string $template): bool {
      $template = preg_replace('~[^a-z0-9_-]+~i', '', strtolower($template));
      if ($template === '') return false;
      return is_file(dirname(dirname(__DIR__)) . '/dbxContent/tpl/htm/media-' . $template . '.htm');
   }

   private function group_setting(array $product, string $key, $fallback) {
      $group = $this->primary_group($product);
      $value = $group[$key] ?? $fallback;
      return $value === '' || $value === null ? $fallback : $value;
   }

   private function product_visual(array $product, string $class = ''): string {
      $image = $this->product_image($product);
      $src = dbxShopMediaUrl::item($image, true);
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

   private function product_gallery(array $product): string {
      $images = $product['images'] ?? array();
      if (!is_array($images) || $images === array()) {
         return $this->product_visual($product, 'dbx-shop-product-visual-large');
      }
      $overflow = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->group_setting($product, 'gallery_overflow', 'grid')) ?: 'grid';
      $click = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->group_setting($product, 'gallery_click', 'lightbox')) ?: 'lightbox';
      $visible = max(1, (int)$this->group_setting($product, 'gallery_visible_count', 3));
      $img_size = preg_replace('~[^a-z0-9_-]~i', '', (string)$this->group_setting($product, 'gallery_image_size', 'original')) ?: 'original';
      $lightbox_width = preg_replace('~[^a-z0-9%._-]+~i', '', (string)$this->group_setting($product, 'gallery_lightbox_width', '100vw')) ?: '100vw';
      $template = preg_replace('~[^a-z0-9_-]+~i', '', strtolower((string)$this->group_setting($product, 'gallery_template', 'image-gallery'))) ?: 'image-gallery';
      if (!$this->media_template_exists($template)) {
         $template = 'image-gallery';
      }
      $html = '<div class="dbx-shop-product-gallery dbx-content-media-gallery gallery-list gallery-template-' . $this->h($template) . '" data-dbx="lib=gallery|module=dbxContent|overflow=' . $this->h($overflow) . '|click=' . $this->h($click) . '|img-count=' . $visible . '|img-size=' . $this->h($img_size) . '|lightbox-width=' . $this->h($lightbox_width) . '">';
      foreach ($images as $image) {
         $url = dbxShopMediaUrl::item($image, false);
         $thumb_url = dbxShopMediaUrl::item($image, true);
         if ($url === '') {
            continue;
         }
         $title = (string)($image['title'] ?? $product['title'] ?? '');
         $alt = (string)($image['alt'] ?? $title);
         $caption = $title;
         $html .= $this->tpl()->get_tpl('dbxContent|media-' . $template, array(
            'id' => (string)($image['media_id'] ?? ''),
            'url' => $this->h($url),
            'thumb_url' => $this->h($thumb_url),
            'poster_url' => $this->h($thumb_url),
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
