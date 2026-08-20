<?php
namespace dbx\dbxContent;

/**
 * Zentrale Sprachabgrenzung fuer die globale Medien-Nutzungstabelle.
 *
 * Content- und Ordner-IDs sind nur innerhalb einer Sprache eindeutig. Jede
 * Abfrage auf dbxMediaUsage, die ein Content-Ziel betrifft, muss deshalb die
 * Sprache einschliessen.
 */
if (!class_exists(dbxContentMediaUsageScope::class, false)) {
final class dbxContentMediaUsageScope {

   public static function language(string $lng = ''): string {
      $lng = strtolower(trim($lng));
      if ($lng === '' || $lng === 'undef') {
         $lng = class_exists(dbxContentLng::class) ? dbxContentLng::current() : 'de';
      }
      $lng = preg_replace('/[^a-z0-9_-]+/', '', $lng) ?: 'de';
      return substr($lng, 0, 12);
   }

   public static function sql(string $lng = ''): string {
      return "content_lng = '" . str_replace("'", "''", self::language($lng)) . "'";
   }

   public static function with_language(string $where = '', string $lng = ''): string {
      $scope = self::sql($lng);
      $where = trim($where);
      return $where === '' ? $scope : '(' . $where . ') AND ' . $scope;
   }

   public static function content(int $content_id, string $lng = ''): string {
      return self::with_language('content_id = ' . max(0, $content_id), $lng);
   }

   public static function folder(int $folder_id, string $lng = ''): string {
      return self::with_language('folder_id = ' . max(0, $folder_id), $lng);
   }

   public static function target_key(int $content_id, int $folder_id, string $lng = ''): string {
      $prefix = self::language($lng) . ':';
      return $content_id > 0
         ? $prefix . 'content:' . $content_id
         : $prefix . 'folder:' . max(0, $folder_id);
   }
}
}
