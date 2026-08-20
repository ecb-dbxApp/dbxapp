<?php
namespace dbx\dbxShop_admin;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

/** Modulinterne Hilfe-Kontexte und CMS-Mediennutzungs-Kontext. */
trait dbxShopAdminHelpContentServiceTrait {

   private function shop_media_usage_content_id(): int {
      $configured = (int)dbx()->get_cfg('dbxShop', 'media_usage_content_id');
      if ($configured > 0 && $this->content_page_exists($configured, $this->shop_media_usage_content_dd())) {
         return $configured;
      }
      return $this->ensure_shop_media_usage_page();
   }

   private function content_dd(): string {
      return dbx()->lng_name('content');
   }

   private function shop_media_usage_lng(): string {
      return dbxContentMediaUsageScope::language(dbxContentLngSync::master_lng());
   }

   private function shop_media_usage_content_dd(): string {
      return dbxContentLng::dd_content($this->shop_media_usage_lng());
   }

   private function shop_media_usage_folder_dd(): string {
      return dbxContentLng::dd_folder($this->shop_media_usage_lng());
   }

   private function content_page_exists(int $content_id, string $dd = ''): bool {
      if ($content_id <= 0) {
         return false;
      }
      try {
         $row = $this->db()->select1($dd !== '' ? $dd : $this->content_dd(), $content_id, 'id', 0);
         return is_array($row) && (int)($row['id'] ?? 0) === $content_id;
      } catch (\Throwable $e) {
         return false;
      }
   }

   private function shop_channel_groups_help_context(): string { return 'channels--groups'; }
   private function shop_channels_help_context(): string { return 'channels'; }
   private function shop_product_groups_help_context(): string { return 'products--groups'; }
   private function shop_shipping_groups_help_context(): string { return 'settings--shipping_groups'; }
   private function shop_settings_help_context(): string { return 'settings'; }
   private function shop_orders_help_context(): string { return 'orders'; }
   private function shop_products_help_context(): string { return 'products'; }
   private function shop_product_channel_mapping_help_context(): string { return 'products--channel_mapping'; }
   private function shop_product_attributes_help_context(): string { return 'products--attributes'; }
   private function shop_media_help_context(): string { return 'products--media'; }

   private function shop_channel_provider_help_context(string $platform): string {
      $allowed = array('shop', 'amazon', 'ebay', 'kleinanzeigen', 'mobile', 'custom');
      return 'channels--' . (in_array($platform, $allowed, true) ? $platform : 'custom');
   }
}
