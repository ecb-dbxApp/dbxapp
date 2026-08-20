<?php
namespace dbx\dbxContent;

class dbxContent_permalink {

   public static function segment($text, $fallback = 'seite') {
      $text = trim((string)$text);
      $text = str_replace(array('\\', '/'), '-', $text);
      $text = str_replace(
         array('Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß'),
         array('Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss'),
         $text
      );
      if (function_exists('iconv')) {
         $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
         if (is_string($ascii) && $ascii !== '') {
            $text = $ascii;
         }
      }
      $text = strtolower($text);
      $text = preg_replace('/\s+/', '-', $text);
      $text = preg_replace('/[^a-z0-9-]+/', '-', $text);
      $text = preg_replace('/-+/', '-', $text);
      $text = trim($text, '-');

      if (strlen($text) > 254) {
         $text = rtrim(substr($text, 0, 254), '-');
      }

      return $text !== '' ? $text : $fallback;
   }

   public static function slug($text) {
      return self::segment($text, 'seite-' . date('YmdHis'));
   }

   public static function normalize($permalink) {
      return self::segment($permalink, '');
   }

   /**
    * Uebersetzt ausschliesslich alte, pfadartige CMS-Permalinks. Die Rueckgabe
    * dient als kompatibler Alias; gespeichert werden nur noch flache Werte.
    */
   public static function canonical_from_legacy($permalink): string {
      $legacy = strtolower(trim(str_replace('\\', '/', (string)$permalink), '/'));
      if ($legacy === '') {
         return '';
      }

      $known = array(
         'shop/help-channel-groups' => 'help-shop-channel-gruppen',
         'shop/help-channels' => 'help-shop-channels',
         'shop/help-channel-shop' => 'help-shop-channel-shop',
         'shop/help-channel-amazon' => 'help-shop-channel-amazon',
         'shop/help-channel-ebay' => 'help-shop-channel-ebay',
         'shop/help-channel-kleinanzeigen' => 'help-shop-channel-kleinanzeigen',
         'shop/help-channel-mobile' => 'help-shop-channel-mobile',
         'shop/help-shipping-groups' => 'help-shop-versandgruppen',
         'shop/help-groups' => 'help-shop-artikelgruppen',
         'shop/help-settings' => 'help-shop-einstellungen',
         'shop/help-orders' => 'help-shop-bestellungen',
         'shop/help-products' => 'help-shop-produkte',
         'shop/help-product-channel-mapping' => 'help-shop-produkt-channel-mapping',
         'shop/help-product-attributes' => 'help-shop-artikelattribute',
         'shop/help-media' => 'help-shop-medien',
         'shop/rechtstexte' => 'shop-rechtstexte',
         'shop/widerruf' => 'shop-widerruf',
         'outside/shop-media-usage' => 'shop-medienverwendung',
         'outside/shop-medienverwendung' => 'shop-medienverwendung',
         'help/admin-dashboard' => 'help-dashboard-admin',
      );

      if (isset($known[$legacy])) {
         return $known[$legacy];
      }

      return self::normalize($legacy);
   }

   /**
    * Liefert den kanonischen öffentlichen Pfad eines CMS-Permalinks.
    */
   public static function public_path($permalink): string {
      $permalink = strtolower(trim(str_replace('\\', '/', (string)$permalink), '/'));
      return $permalink;
   }

   public static function is_valid($permalink): bool {
      $permalink = (string)$permalink;
      if ($permalink === '' || strlen($permalink) > 254) {
         return false;
      }

      try {
         $validator = function_exists('dbx') ? dbx()->get_system_obj('dbxValidator') : new \dbxValidator();
         return is_object($validator) && $validator->validate($permalink, 'permalink|min=1|max=254');
      } catch (\Throwable $e) {
         return (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $permalink);
      }
   }

   public static function exists($db, string $content_dd, string $permalink, int $exclude_id = 0): bool {
      if (!is_object($db) || $content_dd === '' || !self::is_valid($permalink)) {
         return false;
      }

      $escaped = str_replace("'", "''", $permalink);
      $rows = $db->select($content_dd, "permalink = '" . $escaped . "'", 'id', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) {
         return false;
      }
      foreach ($rows as $row) {
         if ((int)($row['id'] ?? 0) !== $exclude_id) {
            return true;
         }
      }
      return false;
   }

   public static function unique($db, string $content_dd, string $permalink, int $exclude_id = 0): string {
      $base = self::normalize($permalink);
      if ($base === '') {
         $base = 'seite';
      }

      $candidate = $base;
      $number = 2;
      while (self::exists($db, $content_dd, $candidate, $exclude_id)) {
         $suffix = '-' . $number;
         $candidate = rtrim(substr($base, 0, 254 - strlen($suffix)), '-') . $suffix;
         $number++;
      }
      return $candidate;
   }

   public static function build($db, $folder_dd, $folder_id, $title, $exclude_id = 0) {
      // Der Ordner ist bewusst kein Bestandteil mehr. Dadurch bleibt der
      // Permalink beim Verschieben einer Seite unveraendert.
      $content_dd = self::content_dd_from_folder_dd((string)$folder_dd);
      return self::unique($db, $content_dd, self::slug($title), (int)$exclude_id);
   }

   private static function content_dd_from_folder_dd(string $folder_dd): string {
      if (strpos($folder_dd, 'content_folder_') === 0) {
         return 'content_' . substr($folder_dd, strlen('content_folder_'));
      }
      if (substr($folder_dd, -7) === '_folder') {
         return substr($folder_dd, 0, -7);
      }
      return $folder_dd;
   }
}

?>
