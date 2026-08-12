<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap_sync.php';

class dbxContentHome {

   private static function db() {
      return dbx()->get_system_obj('dbxDB');
   }

   public static function masterCid(): int {
      $cid = dbx()->get_cfg('dbxHome', 'cid');
      if ($cid === 'undef' || $cid === '' || $cid === null) {
         return 0;
      }

      return (int) $cid;
   }

   public static function currentLng(): string {
      return dbx()->lng_current();
   }

   /**
    * Startseiten-CID fuer die aktive (oder uebergebene) Sprache.
    * Config-cid ist die Master-Sprache; Slaves werden per lng_uid aufgeloest.
    */
   public static function resolveCid(string $lng = ''): int {
      $masterCid = self::masterCid();
      if ($masterCid <= 0) {
         return 0;
      }

      if ($lng === '') {
         $lng = self::currentLng();
      }
      $lng = strtolower(trim($lng));
      $masterLng = dbxContentLngSync::masterLng();

      if ($lng === $masterLng) {
         return $masterCid;
      }

      $db = self::db();
      if (!is_object($db)) {
         return 0;
      }

      $masterDd = dbxContentLng::ddContent($masterLng);
      $masterRow = $db->select1($masterDd, $masterCid, 'lng_uid', 0);
      if (!is_array($masterRow)) {
         return 0;
      }

      $lngUid = trim((string) ($masterRow['lng_uid'] ?? ''));
      if ($lngUid === '') {
         return 0;
      }

      $slaveId = dbxContentLngSync::resolveIdByUid($db, dbxContentLng::ddContent(), $lngUid, $lng);
      if ($slaveId <= 0) {
         return 0;
      }

      return $slaveId;
   }

   /**
    * Prueft, ob eine Seite die Startseite der angegebenen Sprache ist.
    */
   public static function isHomePage($db, int $cid, string $lng = ''): bool {
      $cid = (int) $cid;
      if ($cid <= 0) {
         return false;
      }

      if ($lng === '') {
         $lng = self::currentLng();
      }
      $lng = strtolower(trim($lng));

      return self::resolveCid($lng) === $cid;
   }

   /**
    * Kompatibilitaetsmethode: Ein separater Home-Cache existiert nicht mehr.
    */
   public static function refreshHomeCache($db, int $cid, string $lng = ''): void {
      return;
   }
}
