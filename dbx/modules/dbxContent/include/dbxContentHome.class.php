<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap_sync.php';

class dbxContentHome {

   private static function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   public static function master_cid(): int {
      $cid = dbx()->get_cfg('dbxHome', 'cid');
      if ($cid === 'undef' || $cid === '' || $cid === null) {
         return 0;
      }

      return (int) $cid;
   }

   public static function current_lng(): string {
      return dbx()->lng_current();
   }

   /**
    * Startseiten-CID fuer die aktive (oder uebergebene) Sprache.
    * Config-cid ist die Master-Sprache; Slaves werden per lng_uid aufgeloest.
    */
   public static function resolve_cid(string $lng = ''): int {
      $master_cid = self::master_cid();
      if ($master_cid <= 0) {
         return 0;
      }

      if ($lng === '') {
         $lng = self::current_lng();
      }
      $lng = strtolower(trim($lng));
      $master_lng = dbxContentLngSync::master_lng();

      if ($lng === $master_lng) {
         return $master_cid;
      }

      $db = self::db();
      if (!is_object($db)) {
         return 0;
      }

      $master_dd = dbxContentLng::dd_content($master_lng);
      $master_row = $db->select1($master_dd, $master_cid, 'lng_uid', 0);
      if (!is_array($master_row)) {
         return 0;
      }

      $lng_uid = trim((string) ($master_row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         return 0;
      }

      $slave_id = dbxContentLngSync::resolve_id_by_uid($db, dbxContentLng::dd_content(), $lng_uid, $lng);
      if ($slave_id <= 0) {
         return 0;
      }

      return $slave_id;
   }

   /**
    * Prüft, ob eine Seite die Startseite der angegebenen Sprache ist.
    */
   public static function is_home_page($db, int $cid, string $lng = ''): bool {
      $cid = (int) $cid;
      if ($cid <= 0) {
         return false;
      }

      if ($lng === '') {
         $lng = self::current_lng();
      }
      $lng = strtolower(trim($lng));

      return self::resolve_cid($lng) === $cid;
   }

   /**
    * Kompatibilitaetsmethode: Ein separater Home-Cache existiert nicht mehr.
    */
   public static function refresh_home_cache($db, int $cid, string $lng = ''): void {
      return;
   }
}
