<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContent_bootstrap_sync.php';
require_once __DIR__ . '/dbxContentContextHelp.class.php';
require_once dirname(__DIR__, 2) . '/dbxAdmin/include/dbxAdminHelp.class.php';

class dbxContentContextHelpProvision {

   private const PROVISION_VERSION = 7;
   private const CONFIG_KEY = 'context_help_provision_version';

   public static function run(): void {
      $version = (int) dbx()->get_cfg('dbxContent', self::CONFIG_KEY);
      if ($version >= self::PROVISION_VERSION) {
         return;
      }

      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db)) {
         return;
      }

      $server = 'dbx|dbxContent.db3';
      if (!$db->connect_db_server($server)) {
         return;
      }

      $result = self::provisionAll($db);
      if (empty($result['errors'])) {
         self::markProvisioned();
      }
   }

   public static function provisionAll($db): array {
      $result = array('folders' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array());
      if (!is_object($db)) {
         $result['errors'][] = 'Keine DB-Verbindung.';
         return $result;
      }

      $helpFolderId = self::ensureHelpFolder($db, $result);
      if ($helpFolderId <= 0) {
         return $result;
      }

      $help = new \dbx\dbxAdmin\dbxAdminHelp();

      $contextHelp = new dbxContentContextHelp();
      $cms = dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
      if (!is_object($cms) || !method_exists($cms, 'bundleBuildPlan') || !method_exists($cms, 'bundleExecutePlan')) {
         $result['errors'][] = 'dbxKi CMS-Service ist nicht verfuegbar.';
         return $result;
      }
      $tplDir = dirname(__DIR__, 2) . '/dbxAdmin/tpl/htm/';
      $dd = dbxContentLng::ddContent();
      $sorter = self::nextContentSorter($db, $helpFolderId);

      foreach ($help->topics() as $topic => $meta) {
         if (!is_array($meta)) {
            continue;
         }

         $permalink = $contextHelp->permalinkForTopic((string) $topic);
         if ($permalink === '') {
            $result['errors'][] = 'Permalink fuer Topic ' . $topic . ' leer.';
            continue;
         }

         $tplName = trim((string) ($meta['tpl'] ?? ''));
         $title = trim((string) ($meta['title'] ?? $topic));
         $content = self::loadTemplateHtml($tplDir, $tplName);
         if ($content === '') {
            $result['errors'][] = 'Template fehlt: ' . $tplName;
            continue;
         }

         $marker = 'dbx-help-' . $contextHelp->topicSlug((string)$topic);
         $existing = self::findExistingPage(
            $db,
            $dd,
            $permalink,
            $contextHelp->legacyPermalinksForTopic((string)$topic),
            $marker,
            $title
         );
         $groupRead = in_array($topic, array('consent_privacy', 'impressum', 'workflow_use', 'workflow_run'), true) ? '*' : 'admin';
         $pageData = self::pageData($helpFolderId, $title, $permalink, $content, $sorter, $groupRead, $marker);

         if (is_array($existing)
            && (int) ($existing['id'] ?? 0) > 0
            && trim((string) ($existing['title'] ?? '')) === $title
            && trim((string) ($existing['permalink'] ?? '')) === $permalink
            && trim((string) ($existing['content'] ?? '')) === trim($content)
            && trim((string) ($existing['group_read'] ?? '')) === $groupRead
            && trim((string) ($existing['keywords'] ?? '')) === $marker) {
            $result['skipped']++;
            continue;
         }

         try {
            if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
               $id = (int) $existing['id'];
               // Ein vom Benutzer verschobenes Hilfedokument bleibt in seinem
               // neuen Ordner. Nur seine stabile Identitaet und sein Inhalt
               // werden aktualisiert.
               unset($pageData['sorter'], $pageData['folder']);
               $params = array(
                  'lng' => dbxContentLng::current(),
                  'id' => $id,
                  'patch' => $pageData,
               );
               $plan = $cms->bundleBuildPlan('page.update', $params);
               $cms->bundleExecutePlan('page.update', $params, $plan);
               $result['updated']++;
               continue;
            }

            $params = array_merge($pageData, array(
               'lng' => dbxContentLng::current(),
               'folder_id' => $helpFolderId,
            ));
            $plan = $cms->bundleBuildPlan('page.create', $params);
            $created = $cms->bundleExecutePlan('page.create', $params, $plan);
            $id = (int) ($created['id'] ?? 0);
            if ($id <= 0) {
               throw new \RuntimeException('Keine ID nach page.create.');
            }
            $result['created']++;
            $sorter = self::nextSorterAfter($sorter);
         } catch (\Throwable $e) {
            $result['errors'][] = 'dbxKi ' . $permalink . ': ' . $e->getMessage();
         }
      }

      dbxContentPageCache::invalidateAll();

      return $result;
   }

   private static function markProvisioned(): void {
      $config = dbx()->get_cfg('dbxContent');
      if (!is_array($config)) {
         $config = array();
      }
      $config[self::CONFIG_KEY] = self::PROVISION_VERSION;
      dbx()->set_cfg('dbxContent', $config);
   }

   private static function ensureHelpFolder($db, array &$result): int {
      $outsideId = self::findFolderByName($db, 'outside', 0);
      if ($outsideId <= 0) {
         $outsideId = self::insertFolder($db, 'outside', 0, 'admin', $result);
      }
      if ($outsideId <= 0) {
         $result['errors'][] = 'Ordner outside konnte nicht angelegt werden.';
         return 0;
      }

      $helpId = self::findFolderByName($db, 'help', $outsideId);
      if ($helpId <= 0) {
         $helpId = self::insertFolder($db, 'help', $outsideId, 'parent', $result);
      }
      if ($helpId <= 0) {
         $result['errors'][] = 'Ordner help konnte nicht angelegt werden.';
         return 0;
      }

      return $helpId;
   }

   private static function findFolderByName($db, string $name, int $parentId): int {
      $dd = dbxContentLng::ddFolder();
      $name = trim($name);
      $parentId = (int) $parentId;
      $where = "name = '" . str_replace("'", "''", $name) . "' AND parent_id = " . $parentId;
      $rows = $db->select($dd, $where, 'id', 'id', 'ASC', '', 1, 0, 0);
      if (!is_array($rows) || !isset($rows[0]['id'])) {
         return 0;
      }
      return (int) $rows[0]['id'];
   }

   private static function insertFolder($db, string $name, int $parentId, string $groupRead, array &$result): int {
      $cms = dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
      if (!is_object($cms) || !method_exists($cms, 'bundleBuildPlan') || !method_exists($cms, 'bundleExecutePlan')) {
         return 0;
      }

      $params = array(
         'lng' => dbxContentLng::current(),
         'name' => $name,
         'parent_id' => (int) $parentId,
         'group_read' => $groupRead,
         'template' => $parentId > 0 ? 'parent' : 'c-content',
         'hero_template' => $parentId > 0 ? 'parent' : 'image-hero',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
      );

      try {
         $plan = $cms->bundleBuildPlan('folder.create', $params);
         $created = $cms->bundleExecutePlan('folder.create', $params, $plan);
         $id = (int) ($created['id'] ?? 0);
      } catch (\Throwable $e) {
         return 0;
      }

      if ($id > 0) {
         $result['folders']++;
      }
      return $id;
   }

   private static function findExistingPage($db, string $dd, string $permalink, array $legacyPermalinks, string $marker, string $title): ?array {
      $fields = 'id,title,content,group_read,keywords,permalink,folder';
      $candidates = array_values(array_unique(array_merge(array($permalink), $legacyPermalinks)));
      foreach ($candidates as $candidate) {
         $candidate = trim((string)$candidate);
         if ($candidate === '') {
            continue;
         }
         $row = $db->select1($dd, array('permalink' => $candidate), $fields, 0);
         if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
            return $row;
         }
      }

      $escapedMarker = str_replace("'", "''", $marker);
      $rows = $db->select($dd, "keywords = '" . $escapedMarker . "'", $fields, 'id', 'ASC', '', 2, 0, 0);
      if (is_array($rows) && count($rows) === 1) {
         return $rows[0];
      }

      // Einmalige Uebernahme bereits verschobener Altseiten ohne Marker.
      $escapedTitle = str_replace("'", "''", $title);
      $rows = $db->select($dd, "title = '" . $escapedTitle . "'", $fields, 'id', 'ASC', '', 2, 0, 0);
      return is_array($rows) && count($rows) === 1 ? $rows[0] : null;
   }

   private static function pageData(int $folderId, string $title, string $permalink, string $content, string $sorter, string $groupRead = 'admin', string $marker = ''): array {
      return array(
         'activ' => 1,
         'folder' => $folderId,
         'title' => substr($title, 0, 254),
         'permalink' => substr($permalink, 0, 254),
         'description' => '',
         'keywords' => $marker,
         'group_read' => $groupRead,
         'sorter' => $sorter,
         'template' => 'parent',
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
         'content' => $content,
      );
   }

   private static function loadTemplateHtml(string $tplDir, string $tplName): string {
      $tplName = trim($tplName);
      if ($tplName === '') {
         return '';
      }
      $path = rtrim($tplDir, '/\\') . DIRECTORY_SEPARATOR . $tplName . '.htm';
      if (!is_file($path) || !is_readable($path)) {
         return '';
      }
      $html = file_get_contents($path);
      return is_string($html) ? trim($html) : '';
   }

   private static function nextContentSorter($db, int $folderId): string {
      $folderId = (int) $folderId;
      $rows = $db->select(dbxContentLng::ddContent(), 'folder = ' . $folderId, 'sorter', 'sorter,id', 'DESC', '', 1, 0, 0);
      $max = 0;
      if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
         $max = (int) ($rows[0]['sorter'] ?? 0);
      }
      return sprintf('%04d', $max + 10);
   }

   private static function nextSorterAfter(string $sorter): string {
      $num = (int) $sorter;
      return sprintf('%04d', $num + 10);
   }
}
