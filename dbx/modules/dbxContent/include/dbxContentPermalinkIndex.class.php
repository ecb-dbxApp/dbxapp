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
   public static function index_path(string $lng = ''): string {
      $lng = self::safe_lng($lng);
      return dbxContentPageCache::base_dir() . 'meta/permalinks_' . $lng . '.json';
   }

   /** Legacy-Pfad, wird nur noch beim Leeren alter Cache-Dateien benoetigt. */
   public static function home_path(string $lng = ''): string {
      $lng = self::safe_lng($lng);
      return dbxContentPageCache::base_dir() . 'meta/home_' . $lng . '.json';
   }

   private static function safe_lng(string $lng = ''): string {
      if ($lng === '') {
         $lng = trim((string) dbx()->get_system_var('dbx_lng', 'de'));
      }
      $lng = strtolower(trim($lng));
      return preg_match('/^[a-z]{2,3}$/', $lng) ? $lng : 'de';
   }

   public static function normalize_permalink(string $permalink): string {
      return dbxContent_permalink::normalize($permalink);
   }

   private static function page_rights(array $row, dbxContentRenderer $renderer): string {
      $folder_rights = trim($renderer->get_public_folder_rights((int)($row['folder'] ?? 0)));
      return $folder_rights !== '' ? $folder_rights : '*';
   }

   private static function route_from_row(array $row, dbxContentRenderer $renderer): ?array {
      $cid = (int)($row['id'] ?? 0);
      $stored_permalink = trim((string)($row['permalink'] ?? ''));
      if ($cid <= 0 || !dbxContent_permalink::is_valid($stored_permalink)) {
         return null;
      }
      $permalink = self::normalize_permalink($stored_permalink);

      return array(
         'cid' => $cid,
         'rights' => self::page_rights($row, $renderer),
         'activ' => (int)($row['activ'] ?? 1),
         'permalink' => $permalink,
         'meta_robots' => trim((string)($row['meta_robots'] ?? '')),
      );
   }

   private static function with_language(string $lng, callable $callback) {
      $lng = self::safe_lng($lng);
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
      return (array) self::with_language($lng, static function(string $active_lng): array {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return array();
         }

         $rows = $db->select(
            dbxContentLng::dd_content($active_lng),
            '1=1',
            'id,permalink,folder,activ,meta_robots',
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
            $route = self::route_from_row($row, $renderer);
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

   public static function upsert_page(int $cid, string $permalink, string $rights, int $activ = 1, string $lng = ''): void {
      // Die Content-Speicherroutine hat den Datensatz bereits aktualisiert.
   }

   public static function remove_by_cid(int $cid, string $lng = ''): void {
      // Kein separater Permalink-Index vorhanden.
   }

   public static function remove_by_permalink(string $permalink, string $lng = ''): void {
      // Kein separater Permalink-Index vorhanden.
   }

   /** Löst genau einen Permalink direkt aus der Content-Tabelle auf. */
   public static function resolve(string $permalink, string $lng = ''): ?array {
      $permalink = trim($permalink);
      if (!dbxContent_permalink::is_valid($permalink)) {
         $permalink = dbxContent_permalink::canonical_from_legacy($permalink);
         if (!dbxContent_permalink::is_valid($permalink)) {
            return null;
         }
      }
      $permalink = self::normalize_permalink($permalink);

      return self::with_language($lng, static function(string $active_lng) use ($permalink): ?array {
         $db = dbx()->get_system_obj('dbxDB');
         if (!is_object($db)) {
            return null;
         }

         $row = $db->select1(
            dbxContentLng::dd_content($active_lng),
            array('permalink' => $permalink),
            'id,permalink,folder,activ,meta_robots',
            0
         );
         if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
            return null;
         }

         return self::route_from_row($row, new dbxContentRenderer());
      });
   }

   public static function write_home_cid(int $cid, string $lng = ''): void {
      // Die sprachabhaengige Startseite wird bei Bedarf live aufgeloest.
   }

   public static function read_home_cid(string $lng = ''): int {
      return 0;
   }
}
