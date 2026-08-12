<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;

require_once dirname(__DIR__, 2) . '/dbxContent/include/dbxContent_bootstrap_sync.php';

class dbxKiCmsHelpProvision {

   public const DEFAULT_TOPIC = 'content';
   public const CONFIG_KEY = 'cms_anleitung_provision_version';
   public const PROVISION_VERSION = 4;

   /** Backward-kompatible Konstanten: entsprechen dem Standard-Topic 'content'. */
   public const PERMALINK = 'dbxki-anleitung-content-seite-mit-ki';
   public const TITLE = 'Content-Seite mit ChatGPT oder DeepSeek erstellen';
   public const TPL = 'cms-anleitung-ki-content-seite';

   /**
    * Ein Topic je Themenbereich (Content, Modul, ...). Jedes Topic wird als
    * eigene CMS-Seite provisioniert und ueber sein eigenes Permalink
    * aufgeloest, damit der Kontext-Hilfe-Screen den fachlich passenden
    * Anleitungstext zeigt statt immer denselben Content-Text.
    */
   private const TOPICS = array(
      'content' => array(
         'permalink' => self::PERMALINK,
         'title' => self::TITLE,
         'tpl' => self::TPL,
         'description' => 'Schritt-fuer-Schritt-Anleitung: Neue CMS-Content-Seite mit ChatGPT, DeepSeek und dbxKi-Bundle erstellen.',
         'keywords' => 'dbxKi, ChatGPT, DeepSeek, CMS, Bundle, KI',
      ),
      'module' => array(
         'permalink' => 'dbxki-anleitung-modul-mit-ki',
         'title' => 'Modul mit ChatGPT oder DeepSeek entwickeln',
         'tpl' => 'cms-anleitung-ki-modul',
         'description' => 'Schritt-fuer-Schritt-Anleitung: Bestehendes oder neues Modul mit ChatGPT, DeepSeek und dbxKi-Bundle entwickeln.',
         'keywords' => 'dbxKi, ChatGPT, DeepSeek, Modul, Bundle, KI',
      ),
      'design' => array(
         'permalink' => 'dbxki-anleitung-design-mit-ki',
         'title' => 'Design mit ChatGPT oder DeepSeek entwickeln',
         'tpl' => 'cms-anleitung-ki-design',
         'description' => 'Schritt-fuer-Schritt-Anleitung: Bestehendes oder neues Design mit ChatGPT, DeepSeek und dbxKi-Bundle entwickeln.',
         'keywords' => 'dbxKi, ChatGPT, DeepSeek, Design, Bundle, KI',
      ),
   );

   private static function topic(string $topic): array {
      return self::TOPICS[$topic] ?? self::TOPICS[self::DEFAULT_TOPIC];
   }

   public static function topics(): array {
      return array_keys(self::TOPICS);
   }

   public static function run(): void {
      $version = (int) dbx()->get_cfg('dbxKi', self::CONFIG_KEY, 0);
      if ($version >= self::PROVISION_VERSION) {
         return;
      }
      $result = self::provisionAll();
      if (empty($result['errors'])) {
         self::markProvisioned();
      }
   }

   /** Provisioniert alle bekannten Topics (Content, Modul, ...). */
   public static function provisionAll(): array {
      $combined = array('created' => 0, 'updated' => 0, 'errors' => array(), 'topics' => array());
      foreach (self::topics() as $topic) {
         $result = self::provision($topic);
         $combined['topics'][$topic] = $result;
         $combined['created'] += (int) ($result['created'] ?? 0);
         $combined['updated'] += (int) ($result['updated'] ?? 0);
         if (!empty($result['errors'])) {
            $combined['errors'] = array_merge($combined['errors'], $result['errors']);
         }
      }
      return $combined;
   }

   public static function provision(string $topic = self::DEFAULT_TOPIC): array {
      $meta = self::topic($topic);
      $permalink = $meta['permalink'];
      $title = $meta['title'];

      $result = array('created' => 0, 'updated' => 0, 'folder_id' => 0, 'page_id' => 0, 'permalink' => $permalink, 'errors' => array());

      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db) || !$db->connect_db_server('dbx|dbxContent.db3')) {
         $result['errors'][] = 'CMS-Datenbank nicht erreichbar.';
         return $result;
      }

      dbxContentLngSync::ensureSchema($db);

      $folderId = self::ensureHelpFolder($db, $result);
      if ($folderId <= 0) {
         return $result;
      }
      $result['folder_id'] = $folderId;

      $content = self::loadTemplateHtml($meta['tpl']);
      if ($content === '') {
         $result['errors'][] = 'Anleitungs-Template fehlt: ' . $meta['tpl'] . '.htm';
         return $result;
      }

      $cms = dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
      $lng = dbxContentLng::current();
      $dd = dbxContentLng::ddContent($lng);
      $existing = $db->select1($dd, array('permalink' => $permalink), 'id', 0);
      $existingId = is_array($existing) ? (int) ($existing['id'] ?? 0) : 0;

      try {
         if ($existingId > 0) {
            $params = array(
               'lng' => $lng,
               'id' => $existingId,
               'content' => $content,
               'title' => $title,
               'group_read' => 'admin',
            );
            $plan = $cms->bundleBuildPlan('page.update', $params);
            $exec = $cms->bundleExecutePlan('page.update', $params, $plan);
            $result['updated'] = 1;
            $result['page_id'] = (int) ($exec['id'] ?? $existingId);
         } else {
            $params = array(
               'lng' => $lng,
               'folder_id' => $folderId,
               'title' => $title,
               'permalink' => $permalink,
               'template' => 'parent',
               'activ' => 1,
               'group_read' => 'admin',
               'description' => $meta['description'],
               'keywords' => $meta['keywords'],
               'content' => $content,
            );
            $plan = $cms->bundleBuildPlan('page.create', $params);
            $exec = $cms->bundleExecutePlan('page.create', $params, $plan);
            $result['created'] = 1;
            $result['page_id'] = (int) ($exec['id'] ?? 0);
         }
      } catch (\Throwable $e) {
         $result['errors'][] = $e->getMessage();
         return $result;
      }

      if ($result['page_id'] > 0) {
         dbxContentPermalinkIndex::upsertPage($result['page_id'], $permalink, 'admin', 1, $lng);
      }

      dbxContentPageCache::invalidateAll();

      return $result;
   }

   public static function pageUrl(string $topic = self::DEFAULT_TOPIC): string {
      return self::topic($topic)['permalink'];
   }

   public static function permalink(string $topic = self::DEFAULT_TOPIC): string {
      return self::topic($topic)['permalink'];
   }

   public static function title(string $topic = self::DEFAULT_TOPIC): string {
      return self::topic($topic)['title'];
   }

   private static function markProvisioned(): void {
      $config = dbx()->get_cfg('dbxKi');
      if (!is_array($config)) {
         $config = array();
      }
      $config[self::CONFIG_KEY] = self::PROVISION_VERSION;
      dbx()->set_cfg('dbxKi', $config);
   }

   private static function loadTemplateHtml(string $tpl): string {
      $path = dirname(__DIR__) . '/tpl/htm/' . $tpl . '.htm';
      if (!is_file($path) || !is_readable($path)) {
         return '';
      }
      $html = file_get_contents($path);
      return is_string($html) ? trim($html) : '';
   }

   private static function ensureHelpFolder($db, array &$result): int {
      $outsideId = self::findFolderByName($db, 'outside', 0);
      if ($outsideId <= 0) {
         $outsideId = self::insertFolder($db, 'outside', 0, 'admin');
      }
      if ($outsideId <= 0) {
         $result['errors'][] = 'Ordner outside konnte nicht angelegt werden.';
         return 0;
      }

      $helpId = self::findFolderByName($db, 'help', $outsideId);
      if ($helpId <= 0) {
         $helpId = self::insertFolder($db, 'help', $outsideId, 'admin');
      }
      if ($helpId <= 0) {
         $result['errors'][] = 'Ordner help konnte nicht angelegt werden.';
         return 0;
      }

      return $helpId;
   }

   private static function findFolderByName($db, string $name, int $parentId): int {
      $dd = dbxContentLng::ddFolder();
      $where = "name = '" . str_replace("'", "''", trim($name)) . "' AND parent_id = " . (int) $parentId;
      $rows = $db->select($dd, $where, 'id', 'id', 'ASC', '', 1, 0, 0);
      if (!is_array($rows) || !isset($rows[0]['id'])) {
         return 0;
      }
      return (int) $rows[0]['id'];
   }

   private static function insertFolder($db, string $name, int $parentId, string $groupRead): int {
      $cms = dbx()->get_include_obj('dbxKiCmsService', 'dbxKi');
      $params = array(
         'lng' => dbxContentLng::current(),
         'name' => $name,
         'parent_id' => $parentId,
         'group_read' => $groupRead,
         'template' => $parentId > 0 ? 'parent' : 'c-content',
      );
      $plan = $cms->bundleBuildPlan('folder.create', $params);
      $exec = $cms->bundleExecutePlan('folder.create', $params, $plan);
      return (int) ($exec['id'] ?? 0);
   }
}
