<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContentLng.class.php';
require_once __DIR__ . '/dbxContentPageCache.class.php';
require_once __DIR__ . '/dbxContentRenderer.class.php';
require_once __DIR__ . '/dbxContent_permalink.class.php';

/**
 * Kompatibilitaets-Fassade fuer Permalinks.
 *
 * Es gibt keinen separaten Permalink-Cache mehr. Alle Lesezugriffe erfolgen
 * direkt auf der sprachabhaengigen Content-Tabelle. Die bisherigen Schreib-
 * methoden bleiben als No-op erhalten, damit bestehende Speicherroutinen nicht
 * angepasst werden muessen.
 */
class dbxContentPermalinkIndex {

   /** Legacy-Pfad, wird nur noch beim Leeren alter Cache-Dateien benoetigt. */
   public static function indexPath(string $lng = ''): string {
      $lng = self::safeLng($lng);
      return dbxContentPageCache::baseDir() . 'meta/permalinks_' . $lng . '.json';
   }

   /** Legacy-Pfad, wird nur noch beim Leeren alter Cache-Dateien benoetigt. */
   public static function homePath(string $lng = ''): string {
      $lng = self::safeLng($lng);
      return dbxContentPageCache::baseDir() . 'meta/home_' . $lng . '.json';
   }

   private static function safeLng(string $lng = ''): string {
      if ($lng === '') {
         $lng = trim((string) dbx()->get_system_var('dbx_lng', 'de'));
      }
      $lng = strtolower(trim($lng));
      return preg_match('/^[a-z]{2,3}$/', $lng) ? $lng : 'de';
   }

   public static function normalizePermalink(string $permalink): string {
      return dbxContent_permalink::normalize($permalink);
   }

   private static function pageRights(array $row, dbxContentRenderer $renderer): string {
      $folderRights = trim($renderer->getPublicFolderRights((int)($row['folder'] ?? 0)));
      return $folderRights !== '' ? $folderRights : '*';
   }

   private static function routeFromRow(array $row, dbxContentRenderer $renderer): ?array {
      $cid = (int)($row['id'] ?? 0);
      $storedPermalink = trim((string)($row['permalink'] ?? ''));
      if ($cid <= 0 || !dbxContent_permalink::isValid($storedPermalink)) {
         return null;
      }
      $permalink = self::normalizePermalink($storedPermalink);

      return array(
         'cid' => $cid,
         'rights' => self::pageRights($row, $renderer),
         'activ' => (int)($row['activ'] ?? 1),
         'permalink' => $permalink,
      );
   }

   private static function withLanguage(string $lng, callable $callback) {
      $lng = self::safeLng($lng);
      $previous = trim((string) dbx()->get_system_var('dbx_lng', 'de'));
      $changed = $previous !== $lng;
      if ($changed) {
         dbx()->set_system_var('dbx_lng', $lng);
      }

      try {
         return $callback($lng);
      } finally {
         if ($changed) {
            dbx()->set_system_var('dbx_lng', $previous !== '' ? $previous : 'de');
         }
      }
   }

   /** Liefert die aktuellen Permalinks direkt aus dbxContent. */
   public static function load(string $lng = ''): array {
      return (array) self::withLanguage($lng, static function(string $activeLng): array {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return array();
         }

         $rows = $db->select(
            dbxContentLng::ddContent($activeLng),
            '1=1',
            'id,permalink,folder,activ',
            'id',
            'ASC',
            '',
            0,
            0,
            0
         );
         if (!is_array($rows)) {
            return array();
         }

         $renderer = new dbxContentRenderer();
         $result = array();
         foreach ($rows as $row) {
            if (!is_array($row)) {
               continue;
            }
            $route = self::routeFromRow($row, $renderer);
            if (is_array($route)) {
               $result[$route['permalink']] = $route;
            }
         }
         return $result;
      });
   }

   /** Kein Persistieren: Permalinks liegen ausschliesslich in dbxContent. */
   public static function save(array $data, string $lng = ''): bool {
      return true;
   }

   public static function upsertPage(int $cid, string $permalink, string $rights, int $activ = 1, string $lng = ''): void {
      // Die Content-Speicherroutine hat den Datensatz bereits aktualisiert.
   }

   public static function removeByCid(int $cid, string $lng = ''): void {
      // Kein separater Permalink-Index vorhanden.
   }

   public static function removeByPermalink(string $permalink, string $lng = ''): void {
      // Kein separater Permalink-Index vorhanden.
   }

   /** Loest genau einen Permalink direkt aus der Content-Tabelle auf. */
   public static function resolve(string $permalink, string $lng = ''): ?array {
      $permalink = trim($permalink);
      if (!dbxContent_permalink::isValid($permalink)) {
         $permalink = dbxContent_permalink::canonicalFromLegacy($permalink);
         if (!dbxContent_permalink::isValid($permalink)) {
            return null;
         }
      }
      $permalink = self::normalizePermalink($permalink);

      return self::withLanguage($lng, static function(string $activeLng) use ($permalink): ?array {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return null;
         }

         $row = $db->select1(
            dbxContentLng::ddContent($activeLng),
            array('permalink' => $permalink),
            'id,permalink,folder,activ',
            0
         );
         if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
            return null;
         }

         return self::routeFromRow($row, new dbxContentRenderer());
      });
   }

   public static function writeHomeCid(int $cid, string $lng = ''): void {
      // Die sprachabhaengige Startseite wird bei Bedarf live aufgeloest.
   }

   public static function readHomeCid(string $lng = ''): int {
      return 0;
   }
}
