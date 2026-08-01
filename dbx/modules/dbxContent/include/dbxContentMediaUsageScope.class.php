<?php
namespace dbx\dbxContent;

/**
 * Zentrale Sprachabgrenzung fuer die globale Medien-Nutzungstabelle.
 *
 * Content- und Ordner-IDs sind nur innerhalb einer Sprache eindeutig. Jede
 * Abfrage auf dbxMediaUsage, die ein Content-Ziel betrifft, muss deshalb die
 * Sprache einschliessen.
 */
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

   public static function withLanguage(string $where = '', string $lng = ''): string {
      $scope = self::sql($lng);
      $where = trim($where);
      return $where === '' ? $scope : '(' . $where . ') AND ' . $scope;
   }

   public static function content(int $contentId, string $lng = ''): string {
      return self::withLanguage('content_id = ' . max(0, $contentId), $lng);
   }

   public static function folder(int $folderId, string $lng = ''): string {
      return self::withLanguage('folder_id = ' . max(0, $folderId), $lng);
   }

   public static function targetKey(int $contentId, int $folderId, string $lng = ''): string {
      $prefix = self::language($lng) . ':';
      return $contentId > 0
         ? $prefix . 'content:' . $contentId
         : $prefix . 'folder:' . max(0, $folderId);
   }
}
