<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';
require_once __DIR__ . '/dbxContentTranslate.class.php';
require_once __DIR__ . '/dbxContent_permalink.class.php';

class dbxContentLngSync {

   public static function masterLng(): string {
      $lng = strtolower(trim((string) dbx()->get_config('dbx', 'default_lng', 'de')));
      if ($lng === '' || $lng === 'undef') {
         return 'de';
      }
      return $lng;
   }

   public static function accessibleLngs(): array {
      if (function_exists('dbx_accessible_lngs')) {
         return dbx_accessible_lngs();
      }

      return array(self::masterLng());
   }

   public static function isMasterLng(?string $lng = null): bool {
      if ($lng === null || $lng === '') {
         $lng = dbxContentLng::current();
      }

      return strtolower(trim((string) $lng)) === self::masterLng();
   }

   public static function newUid(string $prefix = 'p'): string {
      $prefix = preg_replace('/[^a-z]/', '', strtolower($prefix));
      if ($prefix === '') {
         $prefix = 'p';
      }

      try {
         $rand = bin2hex(random_bytes(8));
      } catch (\Throwable $e) {
         $rand = substr(sha1(uniqid((string) mt_rand(), true)), 0, 16);
      }

      return $prefix . '_' . $rand;
   }

   public static function ensureSchema($db): void {
      // Kompatibilitaetsmethode: Das Sprachschema ist in den Content-DDs
      // definiert und wird ausschliesslich ueber dbxDD synchronisiert.
      // Fachliche CMS-/KI-Aufrufe duerfen keine DDL-Aenderung ausloesen.
      return;
   }

   public static function backfillMasterLng($db): void {
      if (!is_object($db)) {
         return;
      }

      self::ensureSchema($db);
      $master = self::masterLng();
      self::backfillTableUids($db, dbxContentLng::ddContent($master), 'p', true);
      self::backfillTableUids($db, dbxContentLng::ddFolder($master), 'f', true);
   }

   public static function backfillUids($db): void {
      if (!is_object($db)) {
         return;
      }

      self::ensureSchema($db);

      foreach (self::accessibleLngs() as $lng) {
         $isMaster = $lng === self::masterLng();
         self::backfillTableUids($db, dbxContentLng::ddContent($lng), 'p', $isMaster);
         self::backfillTableUids($db, dbxContentLng::ddFolder($lng), 'f', $isMaster);
      }
   }

   public static function ensureRecordUid($db, string $dd, int $id, string $prefix = 'p'): string {
      $id = (int) $id;
      if ($id <= 0 || !is_object($db)) {
         return '';
      }

      $row = $db->select1($dd, $id, 'lng_uid,lng_sync,lng_rev,lng_synced_rev', 0);
      if (!is_array($row)) {
         return '';
      }

      $uid = trim((string) ($row['lng_uid'] ?? ''));
      if ($uid !== '') {
         return $uid;
      }

      $uid = self::newUid($prefix);
      $sync = self::isMasterLng(self::lngFromDd($dd)) ? 'auto' : 'manual';
      $db->update($dd, array(
         'lng_uid' => $uid,
         'lng_sync' => $sync,
         'lng_rev' => 1,
         'lng_synced_rev' => 0,
      ), $id, 0, 1, 1, 0);

      return $uid;
   }

   public static function afterPageSave($db, int $id, bool $isNew = false): void {
      $id = (int) $id;
      if ($id <= 0 || !is_object($db)) {
         return;
      }

      $dd = dbxContentLng::ddContent();
      self::ensureRecordUid($db, $dd, $id, 'p');

      if (!self::isMasterLng()) {
         self::markManual($db, $dd, $id);
         return;
      }

      if ($isNew) {
         $row = $db->select1($dd, $id, 'lng_rev,lng_sync', 0);
         $rev = (int) ($row['lng_rev'] ?? 0);
         if ($rev < 1) {
            $db->update($dd, array('lng_rev' => 1, 'lng_sync' => 'auto'), $id, 0, 1, 1, 0);
         }
      } else {
         $row = $db->select1($dd, $id, 'lng_rev', 0);
         $rev = max(1, (int) ($row['lng_rev'] ?? 1)) + 1;
         $db->update($dd, array('lng_rev' => $rev), $id, 0, 1, 1, 0);
      }
   }

   public static function afterFolderSave($db, int $id, bool $isNew = false): void {
      $id = (int) $id;
      if ($id <= 0 || !is_object($db)) {
         return;
      }

      $dd = dbxContentLng::ddFolder();
      self::ensureRecordUid($db, $dd, $id, 'f');

      if (!self::isMasterLng()) {
         self::markManual($db, $dd, $id);
         return;
      }

      if ($isNew) {
         $db->update($dd, array('lng_rev' => 1, 'lng_sync' => 'auto'), $id, 0, 1, 1, 0);
      } else {
         $row = $db->select1($dd, $id, 'lng_rev', 0);
         $rev = max(1, (int) ($row['lng_rev'] ?? 1)) + 1;
         $db->update($dd, array('lng_rev' => $rev), $id, 0, 1, 1, 0);
      }
   }

   public static function markManual($db, string $dd, int $id): void {
      $id = (int) $id;
      if ($id <= 0 || !is_object($db)) {
         return;
      }

      $db->update($dd, array('lng_sync' => 'manual'), $id, 0, 1, 1, 0);
   }

   public static function resolveIdByUid($db, string $dd, string $lngUid, string $lng = ''): int {
      $lngUid = trim($lngUid);
      if ($lngUid === '' || !is_object($db)) {
         return 0;
      }

      if ($lng === '') {
         $lng = dbxContentLng::current();
      }

      $dd = self::ddForLng($dd, $lng);
      $rows = $db->select($dd, "lng_uid = '" . str_replace("'", "''", $lngUid) . "'", 'id', 'id', 'ASC', '', 1, 0, 0);
      if (!is_array($rows) || !isset($rows[0]['id'])) {
         return 0;
      }

      return (int) $rows[0]['id'];
   }

   public static function hasMissingSlaveLng($db, string $entity, int $masterId): bool {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $masterId = (int) $masterId;
      if ($masterId <= 0 || !is_object($db) || !self::isMasterLng()) {
         return false;
      }

      $slaveLngs = self::slaveLngs();
      if (!count($slaveLngs)) {
         return false;
      }

      $master = self::masterLng();
      $masterDd = $entity === 'folder' ? dbxContentLng::ddFolder($master) : dbxContentLng::ddContent($master);
      $masterRow = $db->select1($masterDd, $masterId, 'lng_uid', 0);
      $lngUid = trim((string) ($masterRow['lng_uid'] ?? ''));
      if ($lngUid === '') {
         return true;
      }

      $dd = $entity === 'folder' ? dbxContentLng::ddFolder() : dbxContentLng::ddContent();
      foreach ($slaveLngs as $lng) {
         if (self::resolveIdByUid($db, $dd, $lngUid, $lng) <= 0) {
            return true;
         }
      }

      return false;
   }

   public static function coverageForUid($db, string $entity, string $lngUid): array {
      $lngUid = trim($lngUid);
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $out = array(
         'lng_uid' => $lngUid,
         'entity' => $entity,
         'master_lng' => self::masterLng(),
         'current_lng' => dbxContentLng::current(),
         'languages' => array(),
      );

      if ($lngUid === '' || !is_object($db)) {
         return $out;
      }

      $dd = $entity === 'folder' ? dbxContentLng::ddFolder() : dbxContentLng::ddContent();

      foreach (self::accessibleLngs() as $lng) {
         $lngDd = self::ddForLng($dd, $lng);
         $row = self::selectByUid($db, $lngDd, $lngUid);
         $status = 'missing';
         $id = 0;
         $title = '';
         $sync = '';

         if (is_array($row)) {
            $id = (int) ($row['id'] ?? 0);
            $sync = strtolower(trim((string) ($row['lng_sync'] ?? 'auto')));
            if ($sync === '') {
               $sync = 'auto';
            }
            $title = $entity === 'folder'
               ? (string) ($row['name'] ?? '')
               : (string) ($row['title'] ?? '');
            $status = $lng === self::masterLng() ? 'master' : $sync;
         }

         $out['languages'][$lng] = array(
            'lng' => $lng,
            'status' => $status,
            'id' => $id,
            'title' => $title,
            'lng_sync' => $sync,
            'is_master' => $lng === self::masterLng() ? 1 : 0,
         );
      }

      return $out;
   }

   public static function badgesHtml(array $coverage): string {
      $languages = is_array($coverage['languages'] ?? null) ? $coverage['languages'] : array();
      if (!count($languages)) {
         return '';
      }

      $parts = array();
      foreach ($languages as $lng => $item) {
         if (!is_array($item)) {
            continue;
         }
         $status = (string) ($item['status'] ?? 'missing');
         $class = 'dbx-cms-lng-badge is-' . preg_replace('/[^a-z]/', '', $status);
         $title = strtoupper($lng) . ': ' . $status;
         if (!empty($item['title'])) {
            $title .= ' — ' . $item['title'];
         }
         $parts[] = '<span class="' . $class . '" data-lng="' . htmlspecialchars($lng, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(strtoupper($lng), ENT_QUOTES, 'UTF-8') . '</span>';
      }

      if (!count($parts)) {
         return '';
      }

      return '<span class="dbx-cms-lng-badges" aria-label="Sprachabdeckung">' . implode('', $parts) . '</span>';
   }

   public static function slaveLngs(): array {
      $master = self::masterLng();
      $out = array();
      foreach (self::accessibleLngs() as $lng) {
         if ($lng !== $master) {
            $out[] = $lng;
         }
      }
      return $out;
   }

   public static function resolveFolderIdInLng($db, int $masterFolderId, string $targetLng): int {
      $masterFolderId = (int) $masterFolderId;
      if ($masterFolderId <= 0 || !is_object($db)) {
         return 0;
      }

      $row = $db->select1(dbxContentLng::ddFolder(self::masterLng()), $masterFolderId, 'lng_uid', 0);
      if (!is_array($row)) {
         return 0;
      }

      $uid = trim((string) ($row['lng_uid'] ?? ''));
      if ($uid === '') {
         return 0;
      }

      return self::resolveIdByUid($db, dbxContentLng::ddFolder(), $uid, $targetLng);
   }

   /**
    * Ordner in Zielsprache aufloesen oder rekursiv aus Master-Struktur anlegen.
    */
   public static function ensureFolderIdInLng($db, int $masterFolderId, string $targetLng, int $depth = 0): int {
      $masterFolderId = (int) $masterFolderId;
      if ($masterFolderId <= 0 || !is_object($db) || $depth > 100) {
         return 0;
      }

      $targetLng = strtolower(trim($targetLng));
      $master = self::masterLng();
      if ($targetLng === '' || $targetLng === $master) {
         return $masterFolderId;
      }

      $existing = self::resolveFolderIdInLng($db, $masterFolderId, $targetLng);
      if ($existing > 0) {
         return $existing;
      }

      $masterDd = dbxContentLng::ddFolder($master);
      $masterRow = $db->select1($masterDd, $masterFolderId);
      if (!is_array($masterRow)) {
         return 0;
      }

      $lngUid = trim((string) ($masterRow['lng_uid'] ?? ''));
      if ($lngUid === '') {
         $lngUid = self::ensureRecordUid($db, $masterDd, $masterFolderId, 'f');
      }
      if ($lngUid === '') {
         return 0;
      }

      $existing = self::resolveIdByUid($db, dbxContentLng::ddFolder(), $lngUid, $targetLng);
      if ($existing > 0) {
         return $existing;
      }

      $parentTargetId = self::ensureFolderIdInLng($db, (int) ($masterRow['parent_id'] ?? 0), $targetLng, $depth + 1);

      $name = dbxContentTranslate::translate((string) ($masterRow['name'] ?? ''), $master, $targetLng, 'folder_name');
      if ($name === '' && trim((string) ($masterRow['name'] ?? '')) !== '') {
         $name = (string) $masterRow['name'];
      }
      if ($name === '') {
         $name = 'Ordner';
      }

      $masterRev = max(1, (int) ($masterRow['lng_rev'] ?? 1));
      $data = self::copyFolderStructure($masterRow);
      $data['name'] = $name;
      $data['parent_id'] = $parentTargetId;
      $data['lng_uid'] = $lngUid;
      $data['lng_sync'] = 'auto';
      $data['lng_rev'] = 0;
      $data['lng_synced_rev'] = $masterRev;

      $targetDd = dbxContentLng::ddFolder($targetLng);
      if ($db->insert($targetDd, $data, 0, 1, 0, 1) !== 1) {
         return 0;
      }

      return (int) $db->get_insert_id();
   }

   public static function previewProvision($db, string $entity, int $masterId, array $lngs = array()): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $masterId = (int) $masterId;
      $master = self::masterLng();
      $out = array(
         'entity' => $entity,
         'master_id' => $masterId,
         'master_lng' => $master,
         'lng_uid' => '',
         'items' => array(),
      );

      if ($masterId <= 0 || !is_object($db) || !self::isMasterLng()) {
         return $out;
      }

      $masterDd = $entity === 'folder' ? dbxContentLng::ddFolder($master) : dbxContentLng::ddContent($master);
      $masterRow = $db->select1($masterDd, $masterId);
      if (!is_array($masterRow)) {
         return $out;
      }

      $lngUid = trim((string) ($masterRow['lng_uid'] ?? ''));
      if ($lngUid === '') {
         $lngUid = self::ensureRecordUid($db, $masterDd, $masterId, $entity === 'folder' ? 'f' : 'p');
      }
      $out['lng_uid'] = $lngUid;

      if (!is_array($lngs) || !count($lngs)) {
         $lngs = self::slaveLngs();
      }

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || $lng === $master) {
            continue;
         }

         $targetDd = $entity === 'folder' ? dbxContentLng::ddFolder($lng) : dbxContentLng::ddContent($lng);
         $existingId = self::resolveIdByUid($db, $targetDd, $lngUid, $lng);
         $existing = $existingId > 0 ? $db->select1($targetDd, $existingId) : null;
         $item = array(
            'lng' => $lng,
            'exists' => $existingId > 0 ? 1 : 0,
            'id' => $existingId,
            'lng_sync' => is_array($existing) ? (string) ($existing['lng_sync'] ?? 'auto') : '',
            'enabled' => 1,
            'warnings' => array(),
         );

         if ($entity === 'folder') {
            $name = (string) ($masterRow['name'] ?? '');
            $item['name'] = dbxContentTranslate::translate($name, $master, $lng, 'folder_name');
            if ($item['name'] === '' && $name !== '') {
               $item['name'] = $name;
            }
         } else {
            $title = (string) ($masterRow['title'] ?? '');
            $item['title'] = dbxContentTranslate::translate($title, $master, $lng, 'title');
            if ($item['title'] === '' && $title !== '') {
               $item['title'] = $title;
            }
            $item['description'] = dbxContentTranslate::translate((string) ($masterRow['description'] ?? ''), $master, $lng, 'description');
            $item['keywords'] = dbxContentTranslate::translate((string) ($masterRow['keywords'] ?? ''), $master, $lng, 'keywords');
            $item['content'] = dbxContentTranslate::translate((string) ($masterRow['content'] ?? ''), $master, $lng, 'content');

            $folderId = self::resolveFolderIdInLng($db, (int) ($masterRow['folder'] ?? 0), $lng);
            if ((int) ($masterRow['folder'] ?? 0) > 0 && $folderId <= 0) {
               $item['warnings'][] = 'Ordnerstruktur in ' . strtoupper($lng) . ' wird bei Uebernahme automatisch angelegt.';
            }
            $item['folder'] = $folderId;
            $existingPermalink = is_array($existing) ? trim((string)($existing['permalink'] ?? '')) : '';
            $item['permalink'] = dbxContent_permalink::isValid($existingPermalink)
               ? $existingPermalink
               : dbxContent_permalink::build(
                  $db,
                  dbxContentLng::ddFolder($lng),
                  $folderId,
                  (string) $item['title'],
                  $existingId
               );
         }

         $out['items'][] = $item;
      }

      return $out;
   }

   public static function provisionFromPreview($db, string $entity, int $masterId, array $items): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $masterId = (int) $masterId;
      $result = array('ok' => 0, 'created' => array(), 'updated' => array(), 'errors' => array());

      if ($masterId <= 0 || !is_object($db) || !self::isMasterLng()) {
         $result['errors'][] = 'Nur in der Master-Sprache moeglich.';
         return $result;
      }

      $master = self::masterLng();
      $masterDd = $entity === 'folder' ? dbxContentLng::ddFolder($master) : dbxContentLng::ddContent($master);
      $masterRow = $db->select1($masterDd, $masterId);
      if (!is_array($masterRow)) {
         $result['errors'][] = 'Master-Datensatz nicht gefunden.';
         return $result;
      }

      $lngUid = trim((string) ($masterRow['lng_uid'] ?? ''));
      if ($lngUid === '') {
         $lngUid = self::ensureRecordUid($db, $masterDd, $masterId, $entity === 'folder' ? 'f' : 'p');
      }

      $masterRev = max(1, (int) ($masterRow['lng_rev'] ?? 1));

      foreach ($items as $item) {
         if (!is_array($item)) {
            continue;
         }
         if ((int) ($item['enabled'] ?? 0) !== 1) {
            continue;
         }

         $lng = strtolower(trim((string) ($item['lng'] ?? '')));
         if ($lng === '' || $lng === $master) {
            continue;
         }

         $targetDd = $entity === 'folder' ? dbxContentLng::ddFolder($lng) : dbxContentLng::ddContent($lng);
         $existingId = self::resolveIdByUid($db, $targetDd, $lngUid, $lng);

         if ($entity === 'folder') {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
               $result['errors'][] = 'Name fuer ' . strtoupper($lng) . ' fehlt.';
               continue;
            }
            $data = self::copyFolderStructure($masterRow);
            $data['name'] = $name;
            $data['lng_uid'] = $lngUid;
            $data['lng_sync'] = 'auto';
            $data['lng_rev'] = 0;
            $data['lng_synced_rev'] = $masterRev;
            $data['parent_id'] = self::ensureFolderIdInLng($db, (int) ($masterRow['parent_id'] ?? 0), $lng);
         } else {
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
               $result['errors'][] = 'Titel fuer ' . strtoupper($lng) . ' fehlt.';
               continue;
            }
            $folderId = (int) ($item['folder'] ?? 0);
            if ($folderId <= 0) {
               $folderId = self::ensureFolderIdInLng($db, (int) ($masterRow['folder'] ?? 0), $lng);
            }
            if ((int) ($masterRow['folder'] ?? 0) > 0 && $folderId <= 0) {
               $result['errors'][] = 'Ordnerstruktur in ' . strtoupper($lng) . ' konnte nicht angelegt werden.';
               continue;
            }
            $data = self::copyPageStructure($masterRow);
            $data['activ'] = 0;
            $data['folder'] = $folderId;
            $data['title'] = $title;
            $data['permalink'] = trim((string) ($item['permalink'] ?? ''));
            if ($data['permalink'] === '') {
               $data['permalink'] = dbxContent_permalink::build($db, dbxContentLng::ddFolder($lng), $folderId, $title, $existingId);
            } elseif (!dbxContent_permalink::isValid($data['permalink'])) {
               $result['errors'][] = 'Permalink fuer ' . strtoupper($lng) . ' ist ungueltig.';
               continue;
            } elseif (dbxContent_permalink::exists($db, $targetDd, $data['permalink'], $existingId)) {
               $result['errors'][] = 'Permalink fuer ' . strtoupper($lng) . ' ist bereits vergeben.';
               continue;
            }
            $data['description'] = (string) ($item['description'] ?? '');
            $data['keywords'] = (string) ($item['keywords'] ?? '');
            $data['content'] = (string) ($item['content'] ?? '');
            $data['lng_uid'] = $lngUid;
            $data['lng_sync'] = 'auto';
            $data['lng_rev'] = 0;
            $data['lng_synced_rev'] = $masterRev;
         }

         if ($existingId > 0) {
            $ok = $db->update($targetDd, $data, $existingId, 0, 1, 1, 0);
            if ($ok === 1) {
               $result['updated'][] = array('lng' => $lng, 'id' => $existingId);
            } else {
               $result['errors'][] = 'Update in ' . strtoupper($lng) . ' fehlgeschlagen.';
            }
         } else {
            $ok = $db->insert($targetDd, $data, 0, 1, 0, 1);
            if ($ok === 1) {
               $newId = (int) $db->get_insert_id();
               $result['created'][] = array('lng' => $lng, 'id' => $newId);
            } else {
               $result['errors'][] = 'Anlage in ' . strtoupper($lng) . ' fehlgeschlagen.';
            }
         }
      }

      $result['ok'] = (count($result['created']) || count($result['updated'])) ? 1 : 0;
      return $result;
   }

   public static function syncSlavesFromMaster($db, string $entity, int $masterId): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $masterId = (int) $masterId;
      $result = array('updated' => array(), 'skipped' => array(), 'errors' => array());

      if ($masterId <= 0 || !is_object($db) || !self::isMasterLng()) {
         return $result;
      }

      dbxContentTranslate::clearWarnings();

      $master = self::masterLng();
      $masterDd = $entity === 'folder' ? dbxContentLng::ddFolder($master) : dbxContentLng::ddContent($master);
      $masterRow = $db->select1($masterDd, $masterId);
      if (!is_array($masterRow)) {
         $result['errors'][] = 'Master-Datensatz nicht gefunden.';
         return $result;
      }

      $lngUid = trim((string) ($masterRow['lng_uid'] ?? ''));
      if ($lngUid === '') {
         return $result;
      }

      $masterRev = max(1, (int) ($masterRow['lng_rev'] ?? 1));

      foreach (self::slaveLngs() as $lng) {
         $targetDd = $entity === 'folder' ? dbxContentLng::ddFolder($lng) : dbxContentLng::ddContent($lng);
         $slaveId = self::resolveIdByUid($db, $targetDd, $lngUid, $lng);

         if ($slaveId <= 0) {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'missing');
            continue;
         }

         $slaveRow = $db->select1($targetDd, $slaveId);
         if (!is_array($slaveRow)) {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'not_found');
            continue;
         }

         $sync = strtolower(trim((string) ($slaveRow['lng_sync'] ?? 'auto')));
         if ($sync !== 'auto') {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'manual');
            continue;
         }

         $syncedRev = (int) ($slaveRow['lng_synced_rev'] ?? 0);
         if ($syncedRev >= $masterRev) {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'up_to_date');
            continue;
         }

         if ($entity === 'folder') {
            $name = dbxContentTranslate::translate((string) ($masterRow['name'] ?? ''), $master, $lng, 'folder_name');
            if ($name === '' && trim((string) ($masterRow['name'] ?? '')) !== '') {
               $name = (string) $masterRow['name'];
            }
            if ($name === '') {
               $result['errors'][] = 'Name fuer ' . strtoupper($lng) . ' leer.';
               continue;
            }

            $data = self::copyFolderStructure($masterRow);
            $data['name'] = $name;
            $data['parent_id'] = self::ensureFolderIdInLng($db, (int) ($masterRow['parent_id'] ?? 0), $lng);
            $data['lng_synced_rev'] = $masterRev;
         } else {
            $title = dbxContentTranslate::translate((string) ($masterRow['title'] ?? ''), $master, $lng, 'title');
            if ($title === '' && trim((string) ($masterRow['title'] ?? '')) !== '') {
               $title = (string) $masterRow['title'];
            }
            if ($title === '') {
               $result['errors'][] = 'Titel fuer ' . strtoupper($lng) . ' leer.';
               continue;
            }

            $folderId = self::ensureFolderIdInLng($db, (int) ($masterRow['folder'] ?? 0), $lng);
            if ((int) ($masterRow['folder'] ?? 0) > 0 && $folderId <= 0) {
               $result['skipped'][] = array('lng' => $lng, 'reason' => 'folder_missing');
               continue;
            }

            $data = self::copyPageStructure($masterRow);
            $data['activ'] = (int) ($slaveRow['activ'] ?? 0);
            $data['folder'] = $folderId;
            $data['title'] = $title;
            $data['description'] = dbxContentTranslate::translate((string) ($masterRow['description'] ?? ''), $master, $lng, 'description');
            $data['keywords'] = dbxContentTranslate::translate((string) ($masterRow['keywords'] ?? ''), $master, $lng, 'keywords');
            $data['content'] = dbxContentTranslate::translate((string) ($masterRow['content'] ?? ''), $master, $lng, 'content');
            $existingPermalink = trim((string)($slaveRow['permalink'] ?? ''));
            $data['permalink'] = dbxContent_permalink::isValid($existingPermalink)
               ? $existingPermalink
               : dbxContent_permalink::build($db, dbxContentLng::ddFolder($lng), $folderId, $title, $slaveId);
            $data['lng_synced_rev'] = $masterRev;
         }

         $ok = $db->update($targetDd, $data, $slaveId, 0, 1, 1, 0);
         if ($ok === 1) {
            $result['updated'][] = array('lng' => $lng, 'id' => $slaveId, 'entity' => $entity);
         } else {
            $result['errors'][] = 'Sync nach ' . strtoupper($lng) . ' fehlgeschlagen.';
         }
      }

      return $result;
   }

   public static function folderDeletable($db, string $lng, int $folderId): array {
      $folderId = (int) $folderId;
      $out = array('deletable' => 0, 'reason' => '');

      if ($folderId <= 0 || !is_object($db)) {
         $out['reason'] = 'Ungueltiger Ordner.';
         return $out;
      }

      $folderDd = dbxContentLng::ddFolder($lng);
      $contentDd = dbxContentLng::ddContent($lng);
      $childFolders = (int) $db->count($folderDd, 'parent_id = ' . $folderId);
      $childPages = (int) $db->count($contentDd, 'folder = ' . $folderId);

      if ($childFolders > 0 || $childPages > 0) {
         $parts = array();
         if ($childFolders > 0) {
            $parts[] = $childFolders . ' Unterordner';
         }
         if ($childPages > 0) {
            $parts[] = $childPages . ' Seite(n)';
         }
         $out['reason'] = implode(' und ', $parts) . ' vorhanden.';
         return $out;
      }

      $out['deletable'] = 1;
      return $out;
   }

   public static function previewDelete($db, string $entity, int $id): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $id = (int) $id;
      $master = self::masterLng();
      $current = dbxContentLng::current();
      $out = array(
         'entity' => $entity,
         'id' => $id,
         'lng_uid' => '',
         'master_lng' => $master,
         'current_lng' => $current,
         'is_master' => self::isMasterLng() ? 1 : 0,
         'items' => array(),
      );

      if ($id <= 0 || !is_object($db)) {
         return $out;
      }

      $dd = $entity === 'folder' ? dbxContentLng::ddFolder() : dbxContentLng::ddContent();
      $row = $db->select1($dd, $id);
      if (!is_array($row)) {
         return $out;
      }

      $lngUid = trim((string) ($row['lng_uid'] ?? ''));
      if ($lngUid === '') {
         $lngUid = self::ensureRecordUid($db, $dd, $id, $entity === 'folder' ? 'f' : 'p');
      }
      $out['lng_uid'] = $lngUid;

      foreach (self::accessibleLngs() as $lng) {
         $targetDd = $entity === 'folder' ? dbxContentLng::ddFolder($lng) : dbxContentLng::ddContent($lng);
         $targetId = ($lng === $current) ? $id : self::resolveIdByUid($db, $targetDd, $lngUid, $lng);
         if ($targetId <= 0) {
            continue;
         }

         $targetRow = $db->select1($targetDd, $targetId);
         if (!is_array($targetRow)) {
            continue;
         }

         $sync = strtolower(trim((string) ($targetRow['lng_sync'] ?? 'auto')));
         if ($sync === '') {
            $sync = 'auto';
         }

         $label = $entity === 'folder'
            ? (string) ($targetRow['name'] ?? '')
            : (string) ($targetRow['title'] ?? '');
         if ($label === '') {
            $label = $entity === 'folder' ? 'Ordner #' . $targetId : 'Seite #' . $targetId;
         }

         $item = array(
            'lng' => $lng,
            'id' => $targetId,
            'label' => $label,
            'lng_sync' => $sync,
            'is_master' => $lng === $master ? 1 : 0,
            'checked' => ($lng === $master || $sync === 'auto') ? 1 : 0,
            'deletable' => 1,
            'block_reason' => '',
         );

         if ($entity === 'folder') {
            $check = self::folderDeletable($db, $lng, $targetId);
            $item['deletable'] = (int) ($check['deletable'] ?? 0);
            $item['block_reason'] = (string) ($check['reason'] ?? '');
            if ($item['deletable'] !== 1) {
               $item['checked'] = 0;
            }
         }

         $out['items'][] = $item;
      }

      return $out;
   }

   public static function resolveDeleteIds($db, string $entity, int $id, array $deleteLngs): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $id = (int) $id;
      $out = array();

      if ($id <= 0 || !is_object($db) || !count($deleteLngs)) {
         return $out;
      }

      $current = dbxContentLng::current();
      $dd = $entity === 'folder' ? dbxContentLng::ddFolder() : dbxContentLng::ddContent();
      $row = $db->select1($dd, $id, 'lng_uid', 0);
      if (!is_array($row)) {
         return $out;
      }

      $lngUid = trim((string) ($row['lng_uid'] ?? ''));
      if ($lngUid === '') {
         $lngUid = self::ensureRecordUid($db, $dd, $id, $entity === 'folder' ? 'f' : 'p');
      }

      foreach ($deleteLngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || !in_array($lng, self::accessibleLngs(), true)) {
            continue;
         }

         $targetDd = $entity === 'folder' ? dbxContentLng::ddFolder($lng) : dbxContentLng::ddContent($lng);
         $targetId = ($lng === $current) ? $id : self::resolveIdByUid($db, $targetDd, $lngUid, $lng);
         if ($targetId > 0) {
            $out[] = array('lng' => $lng, 'id' => $targetId, 'entity' => $entity);
         }
      }

      return $out;
   }

   public static function resetSyncToAuto($db, string $entity, int $masterId, array $lngs = array()): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $masterId = (int) $masterId;
      $result = array('updated' => array(), 'skipped' => array(), 'errors' => array());

      if ($masterId <= 0 || !is_object($db) || !self::isMasterLng()) {
         $result['errors'][] = 'Nur in der Master-Sprache moeglich.';
         return $result;
      }

      $master = self::masterLng();
      $masterDd = $entity === 'folder' ? dbxContentLng::ddFolder($master) : dbxContentLng::ddContent($master);
      $masterRow = $db->select1($masterDd, $masterId, 'lng_uid', 0);
      if (!is_array($masterRow)) {
         $result['errors'][] = 'Master-Datensatz nicht gefunden.';
         return $result;
      }

      $lngUid = trim((string) ($masterRow['lng_uid'] ?? ''));
      if ($lngUid === '') {
         $lngUid = self::ensureRecordUid($db, $masterDd, $masterId, $entity === 'folder' ? 'f' : 'p');
      }

      if (!is_array($lngs) || !count($lngs)) {
         $lngs = self::slaveLngs();
      }

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || $lng === $master) {
            continue;
         }

         $targetDd = $entity === 'folder' ? dbxContentLng::ddFolder($lng) : dbxContentLng::ddContent($lng);
         $slaveId = self::resolveIdByUid($db, $targetDd, $lngUid, $lng);
         if ($slaveId <= 0) {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'missing');
            continue;
         }

         $ok = $db->update($targetDd, array('lng_sync' => 'auto'), $slaveId, 0, 1, 1, 0);
         if ($ok === 1) {
            $result['updated'][] = array('lng' => $lng, 'id' => $slaveId, 'entity' => $entity);
         } else {
            $result['errors'][] = 'Auto-Sync fuer ' . strtoupper($lng) . ' fehlgeschlagen.';
         }
      }

      return $result;
   }

   public static function collectFolderSubtreeIds($db, int $rootFolderId): array {
      $rootFolderId = (int) $rootFolderId;
      if ($rootFolderId <= 0 || !is_object($db)) {
         return array();
      }

      $master = self::masterLng();
      $dd = dbxContentLng::ddFolder($master);
      $ordered = array();
      $seen = array();
      $queue = array($rootFolderId);

      while (count($queue)) {
         $current = (int) array_shift($queue);
         if ($current <= 0 || isset($seen[$current])) {
            continue;
         }
         $seen[$current] = 1;
         $ordered[] = $current;

         $rows = $db->select($dd, 'parent_id = ' . $current, 'id', 'sorter,id', 'ASC', '', 0, 0, 0);
         if (!is_array($rows)) {
            continue;
         }
         foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && !isset($seen[$id])) {
               $queue[] = $id;
            }
         }
      }

      return $ordered;
   }

   public static function collectPageIdsInFolders($db, array $folderIds): array {
      if (!is_object($db) || !count($folderIds)) {
         return array();
      }

      $folderIds = array_values(array_filter(array_map('intval', $folderIds), function ($id) {
         return $id > 0;
      }));
      if (!count($folderIds)) {
         return array();
      }

      $master = self::masterLng();
      $dd = dbxContentLng::ddContent($master);
      $rows = $db->select($dd, 'folder IN (' . implode(',', $folderIds) . ')', 'id', 'sorter,id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) {
         return array();
      }

      $out = array();
      foreach ($rows as $row) {
         $id = (int) ($row['id'] ?? 0);
         if ($id > 0) {
            $out[] = $id;
         }
      }
      return $out;
   }

   public static function provisionFolderTree($db, int $masterFolderId, array $lngs = array()): array {
      $masterFolderId = (int) $masterFolderId;
      $result = array(
         'ok' => 0,
         'master_folder_id' => $masterFolderId,
         'folders' => array(),
         'pages' => array(),
         'errors' => array(),
      );

      if ($masterFolderId <= 0 || !is_object($db) || !self::isMasterLng()) {
         $result['errors'][] = 'Nur in der Master-Sprache moeglich.';
         return $result;
      }

      $folderIds = self::collectFolderSubtreeIds($db, $masterFolderId);
      if (!count($folderIds)) {
         $result['errors'][] = 'Ordner nicht gefunden.';
         return $result;
      }

      foreach ($folderIds as $folderId) {
         $preview = self::previewProvision($db, 'folder', (int) $folderId, $lngs);
         $items = array();
         foreach (is_array($preview['items'] ?? null) ? $preview['items'] : array() as $item) {
            if (!is_array($item)) {
               continue;
            }
            $item['enabled'] = 1;
            $items[] = $item;
         }
         $prov = self::provisionFromPreview($db, 'folder', (int) $folderId, $items);
         $result['folders'][] = array(
            'master_id' => (int) $folderId,
            'result' => $prov,
         );
         if (is_array($prov['errors'] ?? null)) {
            $result['errors'] = array_merge($result['errors'], $prov['errors']);
         }
      }

      $pageIds = self::collectPageIdsInFolders($db, $folderIds);
      foreach ($pageIds as $pageId) {
         $preview = self::previewProvision($db, 'page', (int) $pageId, $lngs);
         $items = array();
         foreach (is_array($preview['items'] ?? null) ? $preview['items'] : array() as $item) {
            if (!is_array($item)) {
               continue;
            }
            $item['enabled'] = 1;
            $items[] = $item;
         }
         $prov = self::provisionFromPreview($db, 'page', (int) $pageId, $items);
         $result['pages'][] = array(
            'master_id' => (int) $pageId,
            'result' => $prov,
         );
         if (is_array($prov['errors'] ?? null)) {
            $result['errors'] = array_merge($result['errors'], $prov['errors']);
         }
      }

      $hasWork = count($result['folders']) || count($result['pages']);
      $result['ok'] = $hasWork ? 1 : 0;
      return $result;
   }

   private static function copyPageStructure(array $masterRow): array {
      $skip = array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'title', 'permalink', 'description', 'keywords', 'content', 'lng_uid', 'lng_sync', 'lng_rev', 'lng_synced_rev');
      $data = array();
      foreach ($masterRow as $key => $value) {
         if (in_array($key, $skip, true)) {
            continue;
         }
         $data[$key] = $value;
      }
      return $data;
   }

   private static function copyFolderStructure(array $masterRow): array {
      $skip = array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'name', 'parent_id', 'lng_uid', 'lng_sync', 'lng_rev', 'lng_synced_rev');
      $data = array();
      foreach ($masterRow as $key => $value) {
         if (in_array($key, $skip, true)) {
            continue;
         }
         $data[$key] = $value;
      }
      return $data;
   }

   public static function renderLngBar(): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $master = self::masterLng();
      $current = dbxContentLng::current();
      $items = array();

      foreach (self::accessibleLngs() as $lng) {
         $active = $lng === $current ? ' is-active' : '';
         $isMaster = $lng === $master ? ' is-master' : '';
         $label = strtoupper($lng);
         if ($lng === $master) {
            $label .= ' *';
         }
         $url = '?dbx_modul=dbxContent_admin&dbx_run1=cms&dbx_lng=' . rawurlencode($lng);
         $items[] = '<a class="dbx-cms-lng-tab' . $active . $isMaster . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" data-cms-lng="' . htmlspecialchars($lng, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
      }

      return $tpl->get_tpl('dbxContent_admin|cms-admin-lng-bar', array(
         'lng_bar_items' => implode('', $items),
         'master_lng' => $master,
         'current_lng' => $current,
      ));
   }

   private static function backfillTableUids($db, string $dd, string $prefix, bool $isMaster = false): void {
      $rows = $db->select($dd, "(lng_uid IS NULL OR TRIM(lng_uid) = '')", 'id', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) {
         return;
      }

      $sync = $isMaster ? 'auto' : 'manual';

      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $id = (int) ($row['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }
         $db->update($dd, array(
            'lng_uid' => self::newUid($prefix),
            'lng_sync' => $sync,
            'lng_rev' => 1,
            'lng_synced_rev' => 0,
         ), $id, 0, 1, 1, 0);
      }
   }

   private static function selectByUid($db, string $dd, string $lngUid): ?array {
      $lngUid = str_replace("'", "''", trim($lngUid));
      if ($lngUid === '') {
         return null;
      }

      $rows = $db->select($dd, "lng_uid = '" . $lngUid . "'", '*', 'id', 'ASC', '', 1, 0, 0);
      if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) {
         return null;
      }

      return $rows[0];
   }

   private static function ddForLng(string $dd, string $lng): string {
      if (strpos($dd, 'content_folder_') === 0 || $dd === dbxContentLng::ddFolder()) {
         return dbxContentLng::ddFolder($lng);
      }

      return dbxContentLng::ddContent($lng);
   }

   private static function lngFromDd(string $dd): string {
      if (preg_match('/_(de|en|es|[a-z]{2})$/', $dd, $m)) {
         return $m[1];
      }

      return dbxContentLng::current();
   }

}
