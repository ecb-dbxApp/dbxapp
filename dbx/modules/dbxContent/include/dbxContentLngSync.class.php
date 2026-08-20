<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap.php';
require_once __DIR__ . '/dbxContentTranslate.class.php';
require_once __DIR__ . '/dbxContent_permalink.class.php';

class dbxContentLngSync {

   public static function master_lng(): string {
      $lng = strtolower(trim((string) dbx()->get_cfg('dbx', 'default_lng', 'de')));
      if ($lng === '' || $lng === 'undef') {
         return 'de';
      }
      return $lng;
   }

   public static function accessible_lngs(): array {
      return dbx()->accessible_lngs();
   }

   public static function is_master_lng(?string $lng = null): bool {
      if ($lng === null || $lng === '') {
         $lng = dbxContentLng::current();
      }

      return strtolower(trim((string) $lng)) === self::master_lng();
   }

   public static function new_uid(string $prefix = 'p'): string {
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

   /**
    * Liest die Sprach-UID eines Datensatzes ohne Seiteneffekt.
    *
    * Anzeige-, Routing- und Coverage-Abläufe dürfen keine Datenbankänderung
    * auslösen. Fehlende UIDs werden deshalb nur von ensureRecordUid() in
    * ausdrücklich schreibenden Speicher-/Provisionierungsabläufen ergänzt.
    */
   public static function record_uid($db, string $dd, int $id): string {
      $id = (int) $id;
      if ($id <= 0 || !is_object($db)) {
         return '';
      }

      $row = $db->select1($dd, $id, 'lng_uid', 0);
      return is_array($row) ? trim((string)($row['lng_uid'] ?? '')) : '';
   }

   public static function ensure_schema($db): void {
      // Kompatibilitaetsmethode: Das Sprachschema ist in den Content-DDs
      // definiert und wird ausschliesslich ueber dbxDD synchronisiert.
      // Fachliche CMS-/KI-Aufrufe duerfen keine DDL-Aenderung ausloesen.
      return;
   }

   public static function backfill_master_lng($db): void {
      if (!is_object($db)) {
         return;
      }

      self::ensure_schema($db);
      $master = self::master_lng();
      self::backfill_table_uids($db, dbxContentLng::dd_content($master), 'p', true);
      self::backfill_table_uids($db, dbxContentLng::dd_folder($master), 'f', true);
   }

   public static function backfill_uids($db): void {
      if (!is_object($db)) {
         return;
      }

      self::ensure_schema($db);

      foreach (self::accessible_lngs() as $lng) {
         $is_master = $lng === self::master_lng();
         self::backfill_table_uids($db, dbxContentLng::dd_content($lng), 'p', $is_master);
         self::backfill_table_uids($db, dbxContentLng::dd_folder($lng), 'f', $is_master);
      }
   }

   public static function ensure_record_uid($db, string $dd, int $id, string $prefix = 'p'): string {
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

      $uid = self::new_uid($prefix);
      $sync = self::is_master_lng(self::lng_from_dd($dd)) ? 'auto' : 'manual';
      $db->update($dd, array(
         'lng_uid' => $uid,
         'lng_sync' => $sync,
         'lng_rev' => 1,
         'lng_synced_rev' => 0,
      ), $id, 0, 1, 1, 0);

      return $uid;
   }

   public static function after_page_save($db, int $id, bool $is_new = false): void {
      $id = (int) $id;
      if ($id <= 0 || !is_object($db)) {
         return;
      }

      $dd = dbxContentLng::dd_content();
      self::ensure_record_uid($db, $dd, $id, 'p');

      if (!self::is_master_lng()) {
         self::mark_manual($db, $dd, $id);
         return;
      }

      if ($is_new) {
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

   public static function after_folder_save($db, int $id, bool $is_new = false): void {
      $id = (int) $id;
      if ($id <= 0 || !is_object($db)) {
         return;
      }

      $dd = dbxContentLng::dd_folder();
      self::ensure_record_uid($db, $dd, $id, 'f');

      if (!self::is_master_lng()) {
         self::mark_manual($db, $dd, $id);
         return;
      }

      if ($is_new) {
         $db->update($dd, array('lng_rev' => 1, 'lng_sync' => 'auto'), $id, 0, 1, 1, 0);
      } else {
         $row = $db->select1($dd, $id, 'lng_rev', 0);
         $rev = max(1, (int) ($row['lng_rev'] ?? 1)) + 1;
         $db->update($dd, array('lng_rev' => $rev), $id, 0, 1, 1, 0);
      }
   }

   public static function mark_manual($db, string $dd, int $id): void {
      $id = (int) $id;
      if ($id <= 0 || !is_object($db)) {
         return;
      }

      $db->update($dd, array('lng_sync' => 'manual'), $id, 0, 1, 1, 0);
   }

   public static function resolve_id_by_uid($db, string $dd, string $lng_uid, string $lng = ''): int {
      $lng_uid = trim($lng_uid);
      if ($lng_uid === '' || !is_object($db)) {
         return 0;
      }

      if ($lng === '') {
         $lng = dbxContentLng::current();
      }

      $dd = self::dd_for_lng($dd, $lng);
      $rows = $db->select($dd, "lng_uid = '" . str_replace("'", "''", $lng_uid) . "'", 'id', 'id', 'ASC', '', 1, 0, 0);
      if (!is_array($rows) || !isset($rows[0]['id'])) {
         return 0;
      }

      return (int) $rows[0]['id'];
   }

   public static function has_missing_slave_lng($db, string $entity, int $master_id): bool {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $master_id = (int) $master_id;
      if ($master_id <= 0 || !is_object($db) || !self::is_master_lng()) {
         return false;
      }

      $slave_lngs = self::slave_lngs();
      if (!count($slave_lngs)) {
         return false;
      }

      $master = self::master_lng();
      $master_dd = $entity === 'folder' ? dbxContentLng::dd_folder($master) : dbxContentLng::dd_content($master);
      $master_row = $db->select1($master_dd, $master_id, 'lng_uid', 0);
      $lng_uid = trim((string) ($master_row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         return true;
      }

      $dd = $entity === 'folder' ? dbxContentLng::dd_folder() : dbxContentLng::dd_content();
      foreach ($slave_lngs as $lng) {
         if (self::resolve_id_by_uid($db, $dd, $lng_uid, $lng) <= 0) {
            return true;
         }
      }

      return false;
   }

   public static function coverage_for_uid($db, string $entity, string $lng_uid): array {
      $lng_uid = trim($lng_uid);
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $out = array(
         'lng_uid' => $lng_uid,
         'entity' => $entity,
         'master_lng' => self::master_lng(),
         'current_lng' => dbxContentLng::current(),
         'languages' => array(),
      );

      if ($lng_uid === '' || !is_object($db)) {
         return $out;
      }

      $dd = $entity === 'folder' ? dbxContentLng::dd_folder() : dbxContentLng::dd_content();

      foreach (self::accessible_lngs() as $lng) {
         $lng_dd = self::dd_for_lng($dd, $lng);
         $row = self::select_by_uid($db, $lng_dd, $lng_uid);
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
            $status = $lng === self::master_lng() ? 'master' : $sync;
         }

         $out['languages'][$lng] = array(
            'lng' => $lng,
            'status' => $status,
            'id' => $id,
            'title' => $title,
            'lng_sync' => $sync,
            'is_master' => $lng === self::master_lng() ? 1 : 0,
         );
      }

      return $out;
   }

   public static function badges_html(array $coverage): string {
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
         $parts[] = '<span class="' . $class . '" data-lng="' . htmlspecialchars($lng, ENT_QUOTES, 'UTF-8') . '" data-dbx-tooltip="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(strtoupper($lng), ENT_QUOTES, 'UTF-8') . '</span>';
      }

      if (!count($parts)) {
         return '';
      }

      return '<span class="dbx-cms-lng-badges" aria-label="Sprachabdeckung">' . implode('', $parts) . '</span>';
   }

   public static function slave_lngs(): array {
      $master = self::master_lng();
      $out = array();
      foreach (self::accessible_lngs() as $lng) {
         if ($lng !== $master) {
            $out[] = $lng;
         }
      }
      return $out;
   }

   public static function resolve_folder_id_in_lng($db, int $master_folder_id, string $target_lng): int {
      $master_folder_id = (int) $master_folder_id;
      if ($master_folder_id <= 0 || !is_object($db)) {
         return 0;
      }

      $row = $db->select1(dbxContentLng::dd_folder(self::master_lng()), $master_folder_id, 'lng_uid', 0);
      if (!is_array($row)) {
         return 0;
      }

      $uid = trim((string) ($row['lng_uid'] ?? ''));
      if ($uid === '') {
         return 0;
      }

      return self::resolve_id_by_uid($db, dbxContentLng::dd_folder(), $uid, $target_lng);
   }

   /**
    * Ordner in Zielsprache aufloesen oder rekursiv aus Master-Struktur anlegen.
    */
   public static function ensure_folder_id_in_lng($db, int $master_folder_id, string $target_lng, int $depth = 0): int {
      $master_folder_id = (int) $master_folder_id;
      if ($master_folder_id <= 0 || !is_object($db) || $depth > 100) {
         return 0;
      }

      $target_lng = strtolower(trim($target_lng));
      $master = self::master_lng();
      if ($target_lng === '' || $target_lng === $master) {
         return $master_folder_id;
      }

      $existing = self::resolve_folder_id_in_lng($db, $master_folder_id, $target_lng);
      if ($existing > 0) {
         return $existing;
      }

      $master_dd = dbxContentLng::dd_folder($master);
      $master_row = $db->select1($master_dd, $master_folder_id);
      if (!is_array($master_row)) {
         return 0;
      }

      $lng_uid = trim((string) ($master_row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         $lng_uid = self::ensure_record_uid($db, $master_dd, $master_folder_id, 'f');
      }
      if ($lng_uid === '') {
         return 0;
      }

      $existing = self::resolve_id_by_uid($db, dbxContentLng::dd_folder(), $lng_uid, $target_lng);
      if ($existing > 0) {
         return $existing;
      }

      $parent_target_id = self::ensure_folder_id_in_lng($db, (int) ($master_row['parent_id'] ?? 0), $target_lng, $depth + 1);

      $name = dbxContentTranslate::translate((string) ($master_row['name'] ?? ''), $master, $target_lng, 'folder_name');
      if ($name === '' && trim((string) ($master_row['name'] ?? '')) !== '') {
         $name = (string) $master_row['name'];
      }
      if ($name === '') {
         $name = 'Ordner';
      }

      $master_rev = max(1, (int) ($master_row['lng_rev'] ?? 1));
      $data = self::copy_folder_structure($master_row);
      $data['name'] = $name;
      $data['parent_id'] = $parent_target_id;
      $data['lng_uid'] = $lng_uid;
      $data['lng_sync'] = 'auto';
      $data['lng_rev'] = 0;
      $data['lng_synced_rev'] = $master_rev;

      $target_dd = dbxContentLng::dd_folder($target_lng);
      if ($db->insert($target_dd, $data, 0, 1, 0, 1) !== 1) {
         return 0;
      }

      return (int) $db->get_insert_id();
   }

   public static function preview_provision($db, string $entity, int $master_id, array $lngs = array()): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $master_id = (int) $master_id;
      $master = self::master_lng();
      $out = array(
         'entity' => $entity,
         'master_id' => $master_id,
         'master_lng' => $master,
         'lng_uid' => '',
         'items' => array(),
      );

      if ($master_id <= 0 || !is_object($db) || !self::is_master_lng()) {
         return $out;
      }

      $master_dd = $entity === 'folder' ? dbxContentLng::dd_folder($master) : dbxContentLng::dd_content($master);
      $master_row = $db->select1($master_dd, $master_id);
      if (!is_array($master_row)) {
         return $out;
      }

      $lng_uid = trim((string) ($master_row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         $lng_uid = self::ensure_record_uid($db, $master_dd, $master_id, $entity === 'folder' ? 'f' : 'p');
      }
      $out['lng_uid'] = $lng_uid;

      if (!is_array($lngs) || !count($lngs)) {
         $lngs = self::slave_lngs();
      }

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || $lng === $master) {
            continue;
         }

         $target_dd = $entity === 'folder' ? dbxContentLng::dd_folder($lng) : dbxContentLng::dd_content($lng);
         $existing_id = self::resolve_id_by_uid($db, $target_dd, $lng_uid, $lng);
         $existing = $existing_id > 0 ? $db->select1($target_dd, $existing_id) : null;
         $item = array(
            'lng' => $lng,
            'exists' => $existing_id > 0 ? 1 : 0,
            'id' => $existing_id,
            'lng_sync' => is_array($existing) ? (string) ($existing['lng_sync'] ?? 'auto') : '',
            'enabled' => 1,
            'warnings' => array(),
         );

         if ($entity === 'folder') {
            $name = (string) ($master_row['name'] ?? '');
            $item['name'] = dbxContentTranslate::translate($name, $master, $lng, 'folder_name');
            if ($item['name'] === '' && $name !== '') {
               $item['name'] = $name;
            }
         } else {
            $title = (string) ($master_row['title'] ?? '');
            $item['title'] = dbxContentTranslate::translate($title, $master, $lng, 'title');
            if ($item['title'] === '' && $title !== '') {
               $item['title'] = $title;
            }
            $item['description'] = dbxContentTranslate::translate((string) ($master_row['description'] ?? ''), $master, $lng, 'description');
            $item['keywords'] = dbxContentTranslate::translate((string) ($master_row['keywords'] ?? ''), $master, $lng, 'keywords');
            $item['content'] = dbxContentTranslate::translate((string) ($master_row['content'] ?? ''), $master, $lng, 'content');

            $folder_id = self::resolve_folder_id_in_lng($db, (int) ($master_row['folder'] ?? 0), $lng);
            if ((int) ($master_row['folder'] ?? 0) > 0 && $folder_id <= 0) {
               $item['warnings'][] = 'Ordnerstruktur in ' . strtoupper($lng) . ' wird bei Uebernahme automatisch angelegt.';
            }
            $item['folder'] = $folder_id;
            $existing_permalink = is_array($existing) ? trim((string)($existing['permalink'] ?? '')) : '';
            $item['permalink'] = dbxContent_permalink::is_valid($existing_permalink)
               ? $existing_permalink
               : dbxContent_permalink::build(
                  $db,
                  dbxContentLng::dd_folder($lng),
                  $folder_id,
                  (string) $item['title'],
                  $existing_id
               );
         }

         $out['items'][] = $item;
      }

      return $out;
   }

   public static function provision_from_preview($db, string $entity, int $master_id, array $items): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $master_id = (int) $master_id;
      $result = array('ok' => 0, 'created' => array(), 'updated' => array(), 'errors' => array());

      if ($master_id <= 0 || !is_object($db) || !self::is_master_lng()) {
         $result['errors'][] = 'Nur in der Master-Sprache moeglich.';
         return $result;
      }

      $master = self::master_lng();
      $master_dd = $entity === 'folder' ? dbxContentLng::dd_folder($master) : dbxContentLng::dd_content($master);
      $master_row = $db->select1($master_dd, $master_id);
      if (!is_array($master_row)) {
         $result['errors'][] = 'Master-Datensatz nicht gefunden.';
         return $result;
      }

      $lng_uid = trim((string) ($master_row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         $lng_uid = self::ensure_record_uid($db, $master_dd, $master_id, $entity === 'folder' ? 'f' : 'p');
      }

      $master_rev = max(1, (int) ($master_row['lng_rev'] ?? 1));

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

         $target_dd = $entity === 'folder' ? dbxContentLng::dd_folder($lng) : dbxContentLng::dd_content($lng);
         $existing_id = self::resolve_id_by_uid($db, $target_dd, $lng_uid, $lng);

         if ($entity === 'folder') {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
               $result['errors'][] = 'Name fuer ' . strtoupper($lng) . ' fehlt.';
               continue;
            }
            $data = self::copy_folder_structure($master_row);
            $data['name'] = $name;
            $data['lng_uid'] = $lng_uid;
            $data['lng_sync'] = 'auto';
            $data['lng_rev'] = 0;
            $data['lng_synced_rev'] = $master_rev;
            $data['parent_id'] = self::ensure_folder_id_in_lng($db, (int) ($master_row['parent_id'] ?? 0), $lng);
         } else {
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
               $result['errors'][] = 'Titel fuer ' . strtoupper($lng) . ' fehlt.';
               continue;
            }
            $folder_id = (int) ($item['folder'] ?? 0);
            if ($folder_id <= 0) {
               $folder_id = self::ensure_folder_id_in_lng($db, (int) ($master_row['folder'] ?? 0), $lng);
            }
            if ((int) ($master_row['folder'] ?? 0) > 0 && $folder_id <= 0) {
               $result['errors'][] = 'Ordnerstruktur in ' . strtoupper($lng) . ' konnte nicht angelegt werden.';
               continue;
            }
            $data = self::copy_page_structure($master_row);
            $data['activ'] = 0;
            $data['folder'] = $folder_id;
            $data['title'] = $title;
            $data['menu_title'] = trim((string) ($item['menu_title'] ?? $title));
            $data['permalink'] = trim((string) ($item['permalink'] ?? ''));
            if ($data['permalink'] === '') {
               $data['permalink'] = dbxContent_permalink::build($db, dbxContentLng::dd_folder($lng), $folder_id, $title, $existing_id);
            } elseif (!dbxContent_permalink::is_valid($data['permalink'])) {
               $result['errors'][] = 'Permalink fuer ' . strtoupper($lng) . ' ist ungueltig.';
               continue;
            } elseif (dbxContent_permalink::exists($db, $target_dd, $data['permalink'], $existing_id)) {
               $result['errors'][] = 'Permalink fuer ' . strtoupper($lng) . ' ist bereits vergeben.';
               continue;
            }
            $data['description'] = (string) ($item['description'] ?? '');
            $data['keywords'] = (string) ($item['keywords'] ?? '');
            $data['content'] = (string) ($item['content'] ?? '');
            $data['lng_uid'] = $lng_uid;
            $data['lng_sync'] = 'auto';
            $data['lng_rev'] = 0;
            $data['lng_synced_rev'] = $master_rev;
         }

         if ($existing_id > 0) {
            $ok = $db->update($target_dd, $data, $existing_id, 0, 1, 1, 0);
            if ($ok === 1) {
               $result['updated'][] = array('lng' => $lng, 'id' => $existing_id);
            } else {
               $result['errors'][] = 'Update in ' . strtoupper($lng) . ' fehlgeschlagen.';
            }
         } else {
            $ok = $db->insert($target_dd, $data, 0, 1, 0, 1);
            if ($ok === 1) {
               $new_id = (int) $db->get_insert_id();
               $result['created'][] = array('lng' => $lng, 'id' => $new_id);
            } else {
               $result['errors'][] = 'Anlage in ' . strtoupper($lng) . ' fehlgeschlagen.';
            }
         }
      }

      $result['ok'] = (count($result['created']) || count($result['updated'])) ? 1 : 0;
      return $result;
   }

   public static function sync_slaves_from_master($db, string $entity, int $master_id): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $master_id = (int) $master_id;
      $result = array('updated' => array(), 'skipped' => array(), 'errors' => array());

      if ($master_id <= 0 || !is_object($db) || !self::is_master_lng()) {
         return $result;
      }

      dbxContentTranslate::clear_warnings();

      $master = self::master_lng();
      $master_dd = $entity === 'folder' ? dbxContentLng::dd_folder($master) : dbxContentLng::dd_content($master);
      $master_row = $db->select1($master_dd, $master_id);
      if (!is_array($master_row)) {
         $result['errors'][] = 'Master-Datensatz nicht gefunden.';
         return $result;
      }

      $lng_uid = trim((string) ($master_row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         return $result;
      }

      $master_rev = max(1, (int) ($master_row['lng_rev'] ?? 1));

      foreach (self::slave_lngs() as $lng) {
         $target_dd = $entity === 'folder' ? dbxContentLng::dd_folder($lng) : dbxContentLng::dd_content($lng);
         $slave_id = self::resolve_id_by_uid($db, $target_dd, $lng_uid, $lng);

         if ($slave_id <= 0) {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'missing');
            continue;
         }

         $slave_row = $db->select1($target_dd, $slave_id);
         if (!is_array($slave_row)) {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'not_found');
            continue;
         }

         $sync = strtolower(trim((string) ($slave_row['lng_sync'] ?? 'auto')));
         if ($sync !== 'auto') {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'manual');
            continue;
         }

         $synced_rev = (int) ($slave_row['lng_synced_rev'] ?? 0);
         if ($synced_rev >= $master_rev) {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'up_to_date');
            continue;
         }

         if ($entity === 'folder') {
            $name = dbxContentTranslate::translate((string) ($master_row['name'] ?? ''), $master, $lng, 'folder_name');
            if ($name === '' && trim((string) ($master_row['name'] ?? '')) !== '') {
               $name = (string) $master_row['name'];
            }
            if ($name === '') {
               $result['errors'][] = 'Name fuer ' . strtoupper($lng) . ' leer.';
               continue;
            }

            $data = self::copy_folder_structure($master_row);
            $data['name'] = $name;
            $data['parent_id'] = self::ensure_folder_id_in_lng($db, (int) ($master_row['parent_id'] ?? 0), $lng);
            $data['lng_synced_rev'] = $master_rev;
         } else {
            $title = dbxContentTranslate::translate((string) ($master_row['title'] ?? ''), $master, $lng, 'title');
            if ($title === '' && trim((string) ($master_row['title'] ?? '')) !== '') {
               $title = (string) $master_row['title'];
            }
            if ($title === '') {
               $result['errors'][] = 'Titel fuer ' . strtoupper($lng) . ' leer.';
               continue;
            }

            $folder_id = self::ensure_folder_id_in_lng($db, (int) ($master_row['folder'] ?? 0), $lng);
            if ((int) ($master_row['folder'] ?? 0) > 0 && $folder_id <= 0) {
               $result['skipped'][] = array('lng' => $lng, 'reason' => 'folder_missing');
               continue;
            }

            $data = self::copy_page_structure($master_row);
            $data['activ'] = (int) ($slave_row['activ'] ?? 0);
            $data['folder'] = $folder_id;
            $data['title'] = $title;
            $data['menu_title'] = dbxContentTranslate::translate(
               (string) ($master_row['menu_title'] ?? $master_row['title'] ?? ''),
               $master,
               $lng,
               'menu_title'
            );
            $data['description'] = dbxContentTranslate::translate((string) ($master_row['description'] ?? ''), $master, $lng, 'description');
            $data['keywords'] = dbxContentTranslate::translate((string) ($master_row['keywords'] ?? ''), $master, $lng, 'keywords');
            $data['content'] = dbxContentTranslate::translate((string) ($master_row['content'] ?? ''), $master, $lng, 'content');
            $existing_permalink = trim((string)($slave_row['permalink'] ?? ''));
            $data['permalink'] = dbxContent_permalink::is_valid($existing_permalink)
               ? $existing_permalink
               : dbxContent_permalink::build($db, dbxContentLng::dd_folder($lng), $folder_id, $title, $slave_id);
            $data['lng_synced_rev'] = $master_rev;
         }

         $ok = $db->update($target_dd, $data, $slave_id, 0, 1, 1, 0);
         if ($ok === 1) {
            $result['updated'][] = array('lng' => $lng, 'id' => $slave_id, 'entity' => $entity);
         } else {
            $result['errors'][] = 'Sync nach ' . strtoupper($lng) . ' fehlgeschlagen.';
         }
      }

      return $result;
   }

   public static function folder_deletable($db, string $lng, int $folder_id): array {
      $folder_id = (int) $folder_id;
      $out = array('deletable' => 0, 'reason' => '');

      if ($folder_id <= 0 || !is_object($db)) {
         $out['reason'] = 'Ungueltiger Ordner.';
         return $out;
      }

      $folder_dd = dbxContentLng::dd_folder($lng);
      $content_dd = dbxContentLng::dd_content($lng);
      $child_folders = (int) $db->count($folder_dd, 'parent_id = ' . $folder_id);
      $child_pages = (int) $db->count($content_dd, 'folder = ' . $folder_id);

      if ($child_folders > 0 || $child_pages > 0) {
         $parts = array();
         if ($child_folders > 0) {
            $parts[] = $child_folders . ' Unterordner';
         }
         if ($child_pages > 0) {
            $parts[] = $child_pages . ' Seite(n)';
         }
         $out['reason'] = implode(' und ', $parts) . ' vorhanden.';
         return $out;
      }

      $out['deletable'] = 1;
      return $out;
   }

   public static function preview_delete($db, string $entity, int $id): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $id = (int) $id;
      $master = self::master_lng();
      $current = dbxContentLng::current();
      $out = array(
         'entity' => $entity,
         'id' => $id,
         'lng_uid' => '',
         'master_lng' => $master,
         'current_lng' => $current,
         'is_master' => self::is_master_lng() ? 1 : 0,
         'items' => array(),
      );

      if ($id <= 0 || !is_object($db)) {
         return $out;
      }

      $dd = $entity === 'folder' ? dbxContentLng::dd_folder() : dbxContentLng::dd_content();
      $row = $db->select1($dd, $id);
      if (!is_array($row)) {
         return $out;
      }

      $lng_uid = trim((string) ($row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         $lng_uid = self::ensure_record_uid($db, $dd, $id, $entity === 'folder' ? 'f' : 'p');
      }
      $out['lng_uid'] = $lng_uid;

      foreach (self::accessible_lngs() as $lng) {
         $target_dd = $entity === 'folder' ? dbxContentLng::dd_folder($lng) : dbxContentLng::dd_content($lng);
         $target_id = ($lng === $current) ? $id : self::resolve_id_by_uid($db, $target_dd, $lng_uid, $lng);
         if ($target_id <= 0) {
            continue;
         }

         $target_row = $db->select1($target_dd, $target_id);
         if (!is_array($target_row)) {
            continue;
         }

         $sync = strtolower(trim((string) ($target_row['lng_sync'] ?? 'auto')));
         if ($sync === '') {
            $sync = 'auto';
         }

         $label = $entity === 'folder'
            ? (string) ($target_row['name'] ?? '')
            : (string) ($target_row['title'] ?? '');
         if ($label === '') {
            $label = $entity === 'folder' ? 'Ordner #' . $target_id : 'Seite #' . $target_id;
         }

         $item = array(
            'lng' => $lng,
            'id' => $target_id,
            'label' => $label,
            'lng_sync' => $sync,
            'is_master' => $lng === $master ? 1 : 0,
            'checked' => ($lng === $master || $sync === 'auto') ? 1 : 0,
            'deletable' => 1,
            'block_reason' => '',
         );

         if ($entity === 'folder') {
            $check = self::folder_deletable($db, $lng, $target_id);
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

   public static function resolve_delete_ids($db, string $entity, int $id, array $delete_lngs): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $id = (int) $id;
      $out = array();

      if ($id <= 0 || !is_object($db) || !count($delete_lngs)) {
         return $out;
      }

      $current = dbxContentLng::current();
      $dd = $entity === 'folder' ? dbxContentLng::dd_folder() : dbxContentLng::dd_content();
      $row = $db->select1($dd, $id, 'lng_uid', 0);
      if (!is_array($row)) {
         return $out;
      }

      $lng_uid = trim((string) ($row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         $lng_uid = self::ensure_record_uid($db, $dd, $id, $entity === 'folder' ? 'f' : 'p');
      }

      foreach ($delete_lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || !in_array($lng, self::accessible_lngs(), true)) {
            continue;
         }

         $target_dd = $entity === 'folder' ? dbxContentLng::dd_folder($lng) : dbxContentLng::dd_content($lng);
         $target_id = ($lng === $current) ? $id : self::resolve_id_by_uid($db, $target_dd, $lng_uid, $lng);
         if ($target_id > 0) {
            $out[] = array('lng' => $lng, 'id' => $target_id, 'entity' => $entity);
         }
      }

      return $out;
   }

   public static function reset_sync_to_auto($db, string $entity, int $master_id, array $lngs = array()): array {
      $entity = $entity === 'folder' ? 'folder' : 'page';
      $master_id = (int) $master_id;
      $result = array('updated' => array(), 'skipped' => array(), 'errors' => array());

      if ($master_id <= 0 || !is_object($db) || !self::is_master_lng()) {
         $result['errors'][] = 'Nur in der Master-Sprache moeglich.';
         return $result;
      }

      $master = self::master_lng();
      $master_dd = $entity === 'folder' ? dbxContentLng::dd_folder($master) : dbxContentLng::dd_content($master);
      $master_row = $db->select1($master_dd, $master_id, 'lng_uid', 0);
      if (!is_array($master_row)) {
         $result['errors'][] = 'Master-Datensatz nicht gefunden.';
         return $result;
      }

      $lng_uid = trim((string) ($master_row['lng_uid'] ?? ''));
      if ($lng_uid === '') {
         $lng_uid = self::ensure_record_uid($db, $master_dd, $master_id, $entity === 'folder' ? 'f' : 'p');
      }

      if (!is_array($lngs) || !count($lngs)) {
         $lngs = self::slave_lngs();
      }

      foreach ($lngs as $lng) {
         $lng = strtolower(trim((string) $lng));
         if ($lng === '' || $lng === $master) {
            continue;
         }

         $target_dd = $entity === 'folder' ? dbxContentLng::dd_folder($lng) : dbxContentLng::dd_content($lng);
         $slave_id = self::resolve_id_by_uid($db, $target_dd, $lng_uid, $lng);
         if ($slave_id <= 0) {
            $result['skipped'][] = array('lng' => $lng, 'reason' => 'missing');
            continue;
         }

         $ok = $db->update($target_dd, array('lng_sync' => 'auto'), $slave_id, 0, 1, 1, 0);
         if ($ok === 1) {
            $result['updated'][] = array('lng' => $lng, 'id' => $slave_id, 'entity' => $entity);
         } else {
            $result['errors'][] = 'Auto-Sync fuer ' . strtoupper($lng) . ' fehlgeschlagen.';
         }
      }

      return $result;
   }

   public static function collect_folder_subtree_ids($db, int $root_folder_id): array {
      $root_folder_id = (int) $root_folder_id;
      if ($root_folder_id <= 0 || !is_object($db)) {
         return array();
      }

      $master = self::master_lng();
      $dd = dbxContentLng::dd_folder($master);
      $ordered = array();
      $seen = array();
      $queue = array($root_folder_id);

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

   public static function collect_page_ids_in_folders($db, array $folder_ids): array {
      if (!is_object($db) || !count($folder_ids)) {
         return array();
      }

      $folder_ids = array_values(array_filter(array_map('intval', $folder_ids), function ($id) {
         return $id > 0;
      }));
      if (!count($folder_ids)) {
         return array();
      }

      $master = self::master_lng();
      $dd = dbxContentLng::dd_content($master);
      $rows = $db->select($dd, 'folder IN (' . implode(',', $folder_ids) . ')', 'id', 'sorter,id', 'ASC', '', 0, 0, 0);
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

   public static function provision_folder_tree($db, int $master_folder_id, array $lngs = array()): array {
      $master_folder_id = (int) $master_folder_id;
      $result = array(
         'ok' => 0,
         'master_folder_id' => $master_folder_id,
         'folders' => array(),
         'pages' => array(),
         'errors' => array(),
      );

      if ($master_folder_id <= 0 || !is_object($db) || !self::is_master_lng()) {
         $result['errors'][] = 'Nur in der Master-Sprache moeglich.';
         return $result;
      }

      $folder_ids = self::collect_folder_subtree_ids($db, $master_folder_id);
      if (!count($folder_ids)) {
         $result['errors'][] = 'Ordner nicht gefunden.';
         return $result;
      }

      foreach ($folder_ids as $folder_id) {
         $preview = self::preview_provision($db, 'folder', (int) $folder_id, $lngs);
         $items = array();
         foreach (is_array($preview['items'] ?? null) ? $preview['items'] : array() as $item) {
            if (!is_array($item)) {
               continue;
            }
            $item['enabled'] = 1;
            $items[] = $item;
         }
         $prov = self::provision_from_preview($db, 'folder', (int) $folder_id, $items);
         $result['folders'][] = array(
            'master_id' => (int) $folder_id,
            'result' => $prov,
         );
         if (is_array($prov['errors'] ?? null)) {
            $result['errors'] = array_merge($result['errors'], $prov['errors']);
         }
      }

      $page_ids = self::collect_page_ids_in_folders($db, $folder_ids);
      foreach ($page_ids as $page_id) {
         $preview = self::preview_provision($db, 'page', (int) $page_id, $lngs);
         $items = array();
         foreach (is_array($preview['items'] ?? null) ? $preview['items'] : array() as $item) {
            if (!is_array($item)) {
               continue;
            }
            $item['enabled'] = 1;
            $items[] = $item;
         }
         $prov = self::provision_from_preview($db, 'page', (int) $page_id, $items);
         $result['pages'][] = array(
            'master_id' => (int) $page_id,
            'result' => $prov,
         );
         if (is_array($prov['errors'] ?? null)) {
            $result['errors'] = array_merge($result['errors'], $prov['errors']);
         }
      }

      $has_work = count($result['folders']) || count($result['pages']);
      $result['ok'] = $has_work ? 1 : 0;
      return $result;
   }

   private static function copy_page_structure(array $master_row): array {
      $skip = array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'title', 'menu_title', 'permalink', 'description', 'keywords', 'content', 'lng_uid', 'lng_sync', 'lng_rev', 'lng_synced_rev');
      $data = array();
      foreach ($master_row as $key => $value) {
         if (in_array($key, $skip, true)) {
            continue;
         }
         $data[$key] = $value;
      }
      return $data;
   }

   private static function copy_folder_structure(array $master_row): array {
      $skip = array('id', 'create_date', 'create_uid', 'update_date', 'update_uid', 'owner', 'name', 'parent_id', 'lng_uid', 'lng_sync', 'lng_rev', 'lng_synced_rev');
      $data = array();
      foreach ($master_row as $key => $value) {
         if (in_array($key, $skip, true)) {
            continue;
         }
         $data[$key] = $value;
      }
      return $data;
   }

   public static function render_lng_bar(): string {
      $tpl = dbx()->get_system_obj('dbxTPL');
      $master = self::master_lng();
      $current = dbxContentLng::current();
      $items = array();

      foreach (self::accessible_lngs() as $lng) {
         $active = $lng === $current ? ' is-active' : '';
         $is_master = $lng === $master ? ' is-master' : '';
         $label = strtoupper($lng);
         if ($lng === $master) {
            $label .= ' *';
         }
         $url = '?dbx_modul=dbxContent_admin&dbx_run1=cms&dbx_lng=' . rawurlencode($lng);
         $items[] = '<a class="dbx-cms-lng-tab' . $active . $is_master . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" data-cms-lng="' . htmlspecialchars($lng, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
      }

      return $tpl->get_tpl('dbxContent_admin|cms-admin-lng-bar', array(
         'lng_bar_items' => implode('', $items),
         'master_lng' => $master,
         'current_lng' => $current,
      ));
   }

   private static function backfill_table_uids($db, string $dd, string $prefix, bool $is_master = false): void {
      $rows = $db->select($dd, "(lng_uid IS NULL OR TRIM(lng_uid) = '')", 'id', 'id', 'ASC', '', 0, 0, 0);
      if (!is_array($rows)) {
         return;
      }

      $sync = $is_master ? 'auto' : 'manual';

      foreach ($rows as $row) {
         if (!is_array($row)) {
            continue;
         }
         $id = (int) ($row['id'] ?? 0);
         if ($id <= 0) {
            continue;
         }
         $db->update($dd, array(
            'lng_uid' => self::new_uid($prefix),
            'lng_sync' => $sync,
            'lng_rev' => 1,
            'lng_synced_rev' => 0,
         ), $id, 0, 1, 1, 0);
      }
   }

   private static function select_by_uid($db, string $dd, string $lng_uid): ?array {
      $lng_uid = str_replace("'", "''", trim($lng_uid));
      if ($lng_uid === '') {
         return null;
      }

      $rows = $db->select($dd, "lng_uid = '" . $lng_uid . "'", '*', 'id', 'ASC', '', 1, 0, 0);
      if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) {
         return null;
      }

      return $rows[0];
   }

   private static function dd_for_lng(string $dd, string $lng): string {
      if (strpos($dd, 'content_folder_') === 0 || $dd === dbxContentLng::dd_folder()) {
         return dbxContentLng::dd_folder($lng);
      }

      return dbxContentLng::dd_content($lng);
   }

   private static function lng_from_dd(string $dd): string {
      if (preg_match('/_(de|en|es|[a-z]{2})$/', $dd, $m)) {
         return $m[1];
      }

      return dbxContentLng::current();
   }

}
