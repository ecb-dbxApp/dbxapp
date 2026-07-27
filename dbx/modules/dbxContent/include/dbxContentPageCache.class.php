<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContentLng.class.php';

class dbxContentPageCache {

   private static bool $dirsReady = false;
   private const FULL_PAGE_CACHE_VERSION = 'v3';
   private const FULL_PAGE_GENERATION_FILE = '.generation';

   public static function isConfigEnabled(): bool {
      $configFile = '';
      try {
         $configFile = rtrim((string)dbx()->get_base_dir(), '/\\') . '/dbx/modules/dbx/cfg/config.php';
      } catch (\Throwable $e) {
         $configFile = dirname(__DIR__, 2) . '/dbx/cfg/config.php';
      }
      if (is_file($configFile) && is_readable($configFile)) {
         $readConfig = static function(string $path): array {
            $config = array();
            include $path;
            return is_array($config) ? $config : array();
         };
         $fileConfig = $readConfig($configFile);
         if (array_key_exists('cache_content', $fileConfig)) {
            return (int)$fileConfig['cache_content'] === 1;
         }
      }

      $enabled = dbx()->get_config('dbx', 'cache_content');
      if ($enabled === 'undef' || $enabled === '' || $enabled === null) {
         return true;
      }

      return (int) $enabled === 1;
   }

   public static function setConfigEnabled(bool $enabled): bool {
      $config = dbx()->get_config('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $config['cache_content'] = $enabled ? 1 : 0;
      // Der Schalter steuert ausschliesslich neue Schreibvorgaenge. Bereits
      // vorhandene Treffer bleiben lesbar und werden nicht geloescht.
      return (int) dbx()->set_config('dbx', $config) > 0;
   }

   /** Abwaertskompatibel: "aktiv" bezeichnet das Schreiben neuer Seiten. */
   public static function isEnabled(): bool {
      return self::isWriteEnabled();
   }

   /** Darf der aktuelle Request vorhandene Gastseiten aus dem Cache lesen? */
   public static function isReadEnabled(): bool {
      if (!self::isGuestSession() || self::hasPersonalizedGuestState()) {
         return false;
      }

      $method = strtoupper(trim((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')));
      if ($method !== 'GET' && $method !== 'HEAD') {
         return false;
      }

      if ((int) dbx()->get_request_var('dbx_sync', 1, 'int') !== 1) {
         return false;
      }

      if ((int) dbx()->get_system_var('dbx_edit', 0, 'int') > 0) {
         return false;
      }

      if ((int) dbx()->get_system_var('dbx_ajax', 0, 'int')) {
         return false;
      }

      if ((int) dbx()->get_system_var('dbx_window', 0, 'int')) {
         return false;
      }

      return true;
   }

   /** Darf der aktuelle Request eine neue Gastseite in den Cache schreiben? */
   public static function isWriteEnabled(): bool {
      return self::isReadEnabled() && self::isConfigEnabled();
   }

   /** Nur der effektive Benutzerkontext entscheidet, nicht der rohe Session-Typ. */
   private static function isGuestSession(): bool {
      try {
         // user() beruecksichtigt auch dbxRunAsAdmin. Ohne diese Abfrage konnte
         // der Admin-Bypass als Gast HTML in den gemeinsamen Cache schreiben.
         return (int)dbx()->user() <= 0;
      } catch (\Throwable $e) {
         return (int)($_SESSION['dbx']['current_user']['id'] ?? 0) <= 0;
      }
   }

   /** Sessionabhaengige Frontend-Werte duerfen nicht in den Gast-Cache gelangen. */
   private static function hasPersonalizedGuestState(): bool {
      $cart = $_SESSION['dbxShop_cart'] ?? array();
      if (!is_array($cart)) {
         return false;
      }

      foreach ($cart as $quantity) {
         if ((int)$quantity > 0) {
            return true;
         }
      }

      return false;
   }

   public static function isContentRoute(): bool {
      if (dbx()->get_system_var('dbx_modul') !== 'dbxContent') {
         return false;
      }

      $action = (string) dbx()->get_system_var('dbx_run1', '');
      return $action === 'show';
   }

   public static function isPermalinkPageRequest(): bool {
      if (!self::isEnabled()) {
         return false;
      }

      if ((int) dbx()->get_system_var('dbx_content_permalink_request', 0, 'int') !== 1) {
         return false;
      }

      return (int) dbx()->get_system_var('dbx_content_route_cid', 0, 'int') > 0;
   }

   /** Fruehe Pruefung ohne Permalink-Index und ohne Content-Datenbank. */
   private static function isRawPermalinkRequest(): bool {
      // Lesen bleibt auch bei ausgeschaltetem Cache-Schreiben aktiv.
      if (!self::isReadEnabled()) {
         return false;
      }

      if (trim((string) dbx()->get_request_var('dbx_modul', '', 'parameter')) !== ''
          || trim((string) dbx()->get_request_var('dbx_run1', '', 'parameter')) !== ''
          || (int) dbx()->get_request_var('cid', 0, 'int') > 0
          || (int) dbx()->get_request_var('dbx_cid', 0, 'int') > 0) {
         return false;
      }

      $permalink = self::currentPermalink();
      if ($permalink === '' || in_array($permalink, array('admin', 'sitemap', 'sitemap.xml', 'robots.txt'), true)) {
         return false;
      }

      // Ein Dateiname pro Permalink/Sprache/Design/Skin ist nur fuer reine
      // Seitenaufrufe eindeutig. Andere Parameter koennten den Inhalt aendern.
      foreach (array_keys($_GET) as $name) {
         if (!in_array((string)$name, array('dbx_lng', 'dbx_design', 'dbx_color'), true)) {
            return false;
         }
      }

      return true;
   }

   /**
    * Bereitet den Gast-Seiten-Cache nur aus dem bereits erkannten URL-Permalink
    * vor. Fuer einen Treffer ist deshalb keine Content-/CID-Datenbankabfrage
    * notwendig.
    */
   public static function prepareFullPageRequest(): bool {
      dbx()->set_system_var('dbx_full_page_cache_prepared', 0);
      dbx()->set_system_var('dbx_full_page_cache_path', '');
      dbx()->set_system_var('dbx_full_page_cache_file', '');
      dbx()->set_system_var('dbx_full_page_cache_generation', '');
      dbx()->set_system_var('dbx_full_page_cache_cid', 0);

      if (!self::isRawPermalinkRequest()) {
         return false;
      }

      // Die leere Root-Route ist fachlich dieselbe Route wie /home. Dadurch
      // erzeugt bereits der erste Root-MISS exakt dieselbe fertige Seite.
      $rawPermalink = self::normalizePermalink((string)dbx()->get_system_var('dbx_permalink', ''));
      if ($rawPermalink === '') {
         dbx()->set_system_var('dbx_permalink', 'home');
         dbx()->set_system_var('dbx_self_url', 'home');
      }

      $permalink = self::currentPermalink();
      $lng = self::safeToken(self::currentLng(), 'de');
      $design = self::currentDesign();
      $skin = dbx()->normalize_skin((string) dbx()->get_system_var('dbx_color', 'blau'));
      $generation = self::cacheGeneration();
      if ($generation === '') {
         return false;
      }
      $path = self::fullPagePathForGeneration($permalink, $lng, $design, $skin, $generation);

      dbx()->set_system_var('dbx_full_page_cache_prepared', 1);
      dbx()->set_system_var('dbx_full_page_cache_path', $path);
      dbx()->set_system_var('dbx_full_page_cache_file', basename($path));
      dbx()->set_system_var('dbx_full_page_cache_permalink', $permalink);
      dbx()->set_system_var('dbx_full_page_cache_design', $design);
      dbx()->set_system_var('dbx_full_page_cache_generation', $generation);

      return true;
   }

   /** Bindet nach einem MISS die live aufgeloeste Content-ID an den Schreibvorgang. */
   public static function attachResolvedContentRoute(): bool {
      if (!self::isPreparedFullPageRequest()) {
         return false;
      }

      // Ein ungueltiger Permalink kann die Home-Darstellung mit HTTP 404
      // verwenden. Seine Antwort darf weder unter dem Tippfehler noch als
      // Home-Cache geschrieben werden.
      $preparedPermalink = (string)dbx()->get_system_var('dbx_full_page_cache_permalink', '');
      if ((int)dbx()->get_system_var('dbx_content_not_found', 0, 'int') === 1
          || $preparedPermalink !== self::currentPermalink()) {
         dbx()->set_system_var('dbx_full_page_cache_prepared', 0);
         dbx()->set_system_var('dbx_full_page_cache_path', '');
         dbx()->set_system_var('dbx_full_page_cache_file', '');
         return false;
      }

      $cid = (int) dbx()->get_system_var('dbx_content_route_cid', 0, 'int');
      if ((int) dbx()->get_system_var('dbx_content_permalink_request', 0, 'int') !== 1 || $cid <= 0) {
         dbx()->set_system_var('dbx_full_page_cache_prepared', 0);
         dbx()->set_system_var('dbx_full_page_cache_path', '');
         dbx()->set_system_var('dbx_full_page_cache_file', '');
         return false;
      }

      dbx()->set_system_var('dbx_full_page_cache_cid', $cid);
      return true;
   }

   public static function isPreparedFullPageRequest(): bool {
      return (int) dbx()->get_system_var('dbx_full_page_cache_prepared', 0, 'int') === 1
         && self::isGuestSession()
         && !self::hasPersonalizedGuestState()
         && self::currentPermalink() !== ''
         && trim((string) dbx()->get_system_var('dbx_full_page_cache_path', '')) !== '';
   }

   /** Liefert ausschliesslich eine bereits komplett gerenderte HTML-Seite. */
   public static function readFullPage(): ?string {
      if (!self::isPreparedFullPageRequest()) {
         return null;
      }

      $preparedGeneration = (string)dbx()->get_system_var('dbx_full_page_cache_generation', '');
      if ($preparedGeneration === '' || !hash_equals($preparedGeneration, self::cacheGeneration())) {
         return null;
      }

      $path = (string) dbx()->get_system_var('dbx_full_page_cache_path', '');
      if (!is_file($path) || !is_readable($path)) {
         return null;
      }

      $html = file_get_contents($path);
      if (!is_string($html) || !self::isCompleteHtml($html) || !self::hasCurrentBaseHref($html)) {
         @unlink($path);
         @unlink(self::fullPageMetaPath($path));
         return null;
      }

      // Eine Invalidierung waehrend des Lesens macht auch bereits gelesene
      // Bytes ungueltig. So wird nach einem Speichern kein alter Stand bedient.
      if (!hash_equals($preparedGeneration, self::cacheGeneration())) {
         return null;
      }

      // file_get_contents liefert die unveraenderten Bytes. Kein Escaping,
      // keine Interpretation und keine Session-abhaengige Nachbearbeitung.
      return $html;
   }

   /** Schreibt die finale Ausgabe nach Design, Modulen, Interpreter und Filtern. */
   public static function writeFullPage(string $html): bool {
      if (!self::isWriteEnabled()
          || !self::isPreparedFullPageRequest()
          || http_response_code() !== 200
          || (string) dbx()->get_system_var('dbx_master_modul', '') !== 'dbxContent'
          || (int) dbx()->get_system_var('dbx_full_page_cache_cid', 0, 'int') <= 0
          || !self::isCompleteHtml($html)
          || !self::hasCurrentBaseHref($html)
          || preg_match(self::secureInputPattern(), $html)) {
         return false;
      }

      $preparedGeneration = (string)dbx()->get_system_var('dbx_full_page_cache_generation', '');
      if ($preparedGeneration === '' || !hash_equals($preparedGeneration, self::cacheGeneration())) {
         // Ein paralleler Speichervorgang hat den Cache inzwischen verworfen.
         // Der alte Request darf seinen inzwischen veralteten Stand nicht in
         // die neue Cache-Generation schreiben.
         return false;
      }

      self::ensureDirs();
      $path = (string) dbx()->get_system_var('dbx_full_page_cache_path', '');
      return self::atomicWrite($path, $html);
   }

   public static function baseDir(): string {
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/cache/';
      return dbx()->os_path($dir);
   }

   public static function menuVariantFlat(int $flat = 1): string {
      return 'flat-' . ((int) $flat === 0 ? 0 : 1);
   }

   public static function menuVariantLoad(int $deep = 9, string $mode = '', string $label = ''): string {
      $deep = max(1, (int) $deep);
      $mode = strtolower(trim($mode));
      if ($mode === '') {
         $mode = 'default';
      }
      $mode = self::safeToken($mode, 'default');
      $label = trim((string) $label);
      $labelKey = $label !== '' ? substr(sha1($label), 0, 8) : 'menu';

      return 'load-' . $deep . '-' . $mode . '-' . $labelKey;
   }

   public static function currentLng(): string {
      $lng = trim((string) dbx()->get_system_var('dbx_lng', 'de'));
      return $lng !== '' ? $lng : 'de';
   }

   /** Das Design ist Bestandteil der vollstaendigen HTML-Ausgabe. */
   public static function currentDesign(): string {
      $config = dbx()->get_config('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $userDefault = trim((string)($config['default_design_user'] ?? 'dbxapp'));
      if ($userDefault === '') {
         $userDefault = 'dbxapp';
      }

      $design = trim((string)dbx()->get_system_var('dbx_design', $userDefault));
      // Der Full-Page-Cache gilt nur fuer Gaeste. Die Aliase user/admin werden
      // daher genauso wie in check_design auf das Frontend-Design aufgeloest.
      if ($design === '' || $design === 'user' || $design === 'admin' || !dbx()->is_design($design)) {
         $design = $userDefault;
      }
      if (!dbx()->is_design($design)) {
         $design = 'dbxapp';
      }

      return self::safeToken($design, 'dbxapp');
   }

   public static function ensureDirs(): void {
      if (self::$dirsReady) {
         return;
      }

      foreach (array('content', 'content/full-page', 'meta') as $sub) {
         $dir = self::baseDir() . $sub;
         if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
         }
      }

      self::purgeLegacyMenuCache();
      self::purgeLegacyPageCache();
      self::$dirsReady = true;
   }

   /** Entfernt die nicht mehr verwendeten Permalink-, Home- und Meta-Caches. */
   private static function purgeLegacyPageCache(): void {
      foreach (array(
         self::baseDir() . 'meta/pages/*.json',
         self::baseDir() . 'meta/permalinks_*.json',
         self::baseDir() . 'meta/home_*.json',
      ) as $pattern) {
         foreach (glob($pattern) ?: array() as $file) {
            @unlink($file);
         }
      }

      $contentDir = self::baseDir() . 'content/';
      foreach (array_merge(glob($contentDir . '*.htm') ?: array(), glob($contentDir . '*.html') ?: array()) as $file) {
         @unlink($file);
         @unlink(self::fullPageMetaPath($file));
      }
      foreach (glob($contentDir . '*.html.meta.json') ?: array() as $metaFile) {
         $htmlFile = preg_replace('/\.meta\.json$/', '', $metaFile);
         if (!is_string($htmlFile) || !is_file($htmlFile)) {
            @unlink($metaFile);
         }
      }
      // Hash-Unterordner und Meta-Dateien des alten Full-Page-Caches werden
      // nicht mehr verwendet. Neue Dateien liegen direkt in full-page/.
      foreach (glob($contentDir . 'full-page/*.htm.meta.json') ?: array() as $metaFile) {
         @unlink($metaFile);
      }
      // V1 enthielt kein Design, V2 keinen kollisionsfreien Permalink-/Host-Key
      // und keine Generation. Nur die aktuelle V3 darf weiterverwendet werden.
      foreach (glob($contentDir . 'full-page/*.htm') ?: array() as $fullPageFile) {
         if (!str_ends_with(strtolower(basename($fullPageFile)), '_' . self::FULL_PAGE_CACHE_VERSION . '.htm')) {
            @unlink($fullPageFile);
         }
      }
      foreach (glob($contentDir . 'full-page/*', GLOB_ONLYDIR) ?: array() as $legacyDir) {
         foreach (glob($legacyDir . '/*') ?: array() as $legacyFile) {
            if (is_file($legacyFile)) {
               @unlink($legacyFile);
            }
         }
         @rmdir($legacyDir);
      }
   }

   /** Entfernt den nicht mehr verwendeten Menu-Cache. */
   public static function purgeLegacyMenuCache(): int {
      $dir = self::baseDir() . 'menu/';
      if (!is_dir($dir)) {
         return 0;
      }

      $removed = 0;
      foreach (glob($dir . '*.html') ?: array() as $path) {
         if (@unlink($path)) {
            $removed++;
         }
      }

      return $removed;
   }

   public static function contentPath(int $cid, string $lng): string {
      $cid = (int) $cid;
      $lng = self::safeToken($lng, 'de');
      return self::baseDir() . 'content/cid-' . $cid . '.' . $lng . '.htm';
   }

   public static function fullPagePath(string $permalink, string $lng, string $design = '', string $skin = ''): string {
      return self::fullPagePathForGeneration($permalink, $lng, $design, $skin, self::cacheGeneration());
   }

   private static function fullPagePathForGeneration(string $permalink, string $lng, string $design, string $skin, string $generation): string {
      $normalizedPermalink = self::normalizePermalink($permalink);
      $permalink = self::permalinkFileToken($normalizedPermalink);
      $lng = self::safeToken($lng, 'de');
      $design = self::safeToken($design !== '' ? $design : self::currentDesign(), 'dbxapp');
      $skin = dbx()->normalize_skin($skin !== '' ? $skin : (string)dbx()->get_system_var('dbx_color', 'blau'));
      $skin = self::safeToken($skin, 'blau');
      $generation = self::safeToken($generation, 'invalid');
      $origin = self::requestOriginToken();

      return self::baseDir() . 'content/full-page/'
         . $permalink . '_' . $lng . '_' . $design . '_' . $skin . '_'
         . $origin . '_' . $generation . '_' . self::FULL_PAGE_CACHE_VERSION . '.htm';
   }

   public static function permalinkContentPath(string $permalink, string $lng): string {
      $permalink = self::permalinkFileToken($permalink);
      $lng = self::safeToken($lng, 'de');

      return self::baseDir() . 'content/' . $permalink . '.' . $lng . '.htm';
   }

   public static function menuPath(int $root, string $lng, string $variant = 'flat-1'): string {
      $root = (int) $root;
      $lng = self::safeToken($lng, 'de');
      $variant = self::safeToken($variant, 'flat-1');

      return self::baseDir() . 'menu/' . $root . '_' . $lng . '_' . $variant . '.html';
   }

   public static function pageMetaPath(int $cid): string {
      return self::baseDir() . 'meta/pages/' . (int) $cid . '.json';
   }

   public static function readContent(int $cid, string $lng = ''): ?string {
      // Der alte dbxContent-Zwischencache ist bewusst deaktiviert.
      return null;
   }

   public static function readPermalinkContent(string $permalink, string $lng = ''): ?string {
      // Ein separater Permalink-Cache existiert nicht mehr.
      return null;
   }

   public static function writeContent(int $cid, string $html, array $meta = array(), string $lng = ''): bool {
      // Der alte dbxContent-Zwischencache ist bewusst deaktiviert.
      return false;
   }

   public static function readMenu(int $root, string $variant = 'flat-1', string $lng = ''): ?string {
      return null;
   }

   public static function writeMenu(string $html, int $root, string $variant = 'flat-1', string $lng = ''): bool {
      return false;
   }

   public static function readPageMeta(int $cid): ?array {
      $meta = self::buildContentMeta($cid, self::currentLng());
      return count($meta) ? $meta : null;
   }

   private static function readPageMetaFile(int $cid): ?array {
      $path = self::pageMetaPath($cid);
      if (!is_file($path) || !is_readable($path)) {
         return null;
      }

      $json = file_get_contents($path);
      if (!is_string($json) || $json === '') {
         return null;
      }

      $data = json_decode($json, true);
      return is_array($data) ? $data : null;
   }

   public static function writePageMeta(int $cid, array $meta): bool {
      // Seiten-Metadaten werden live aus dbxContent gelesen und nicht gecacht.
      return true;
   }

   public static function invalidateContent(int $cid): void {
      $cid = (int) $cid;
      if ($cid <= 0) {
         return;
      }

      // Der Full-Page-Dateiname enthaelt bewusst keine Content-ID. Bei einer
      // Inhaltsaenderung werden deshalb alle fertigen Gastseiten verworfen.
      self::invalidateAllFullPages();

      $permalinks = array();
      $meta = self::readPageMetaFile($cid);
      if (is_array($meta)) {
         $permalink = trim((string) ($meta['permalink'] ?? ''));
         $lng = trim((string) ($meta['lng'] ?? ''));
         if ($permalink !== '') {
            $permalinks[] = array('permalink' => $permalink, 'lng' => $lng);
         }
      }

      foreach (glob(self::baseDir() . 'meta/permalinks_*.json') ?: array() as $indexFile) {
         $json = file_get_contents($indexFile);
         if (!is_string($json) || $json === '') {
            continue;
         }
         $index = json_decode($json, true);
         if (!is_array($index)) {
            continue;
         }
         $lng = '';
         if (preg_match('/^permalinks_([a-z]{2,3})\.json$/i', basename($indexFile), $match)) {
            $lng = strtolower($match[1]);
         }
         foreach ($index as $permalink => $row) {
            if (is_array($row) && (int) ($row['cid'] ?? 0) === $cid) {
               $permalinks[] = array('permalink' => (string) $permalink, 'lng' => $lng);
            }
         }
      }

      $seen = array();
      foreach ($permalinks as $item) {
         $permalink = trim((string) ($item['permalink'] ?? ''));
         if ($permalink === '') {
            continue;
         }
         $lng = trim((string) ($item['lng'] ?? ''));
         $token = self::permalinkFileToken($permalink);
         $key = $token . '|' . $lng;
         if (isset($seen[$key])) {
            continue;
         }
         $seen[$key] = 1;
         $legacyToken = self::legacyPermalinkFileToken($permalink);
         $pattern = $lng !== ''
            ? self::baseDir() . 'content/' . $token . '.' . self::safeToken($lng, 'de') . '.htm'
            : self::baseDir() . 'content/' . $token . '.*.htm';
         foreach (glob($pattern) ?: array() as $file) {
            @unlink($file);
         }
         foreach (array(
            self::baseDir() . 'content/' . $legacyToken . '.*.htm',
            self::baseDir() . 'content/' . $legacyToken . '__*.html',
         ) as $legacyPattern) {
            foreach (glob($legacyPattern) ?: array() as $file) {
               @unlink($file);
            }
         }
      }

      foreach (glob(self::baseDir() . 'content/cid-' . $cid . '.*.htm') ?: array() as $file) {
         @unlink($file);
      }
      foreach (glob(self::baseDir() . 'content/cid-' . $cid . '.*.full-*.html') ?: array() as $file) {
         @unlink($file);
      }
      foreach (glob(self::baseDir() . 'content/full-page/*/*.htm.meta.json') ?: array() as $metaFile) {
         $cacheMeta = self::readJsonFile($metaFile);
         if (!is_array($cacheMeta) || (int)($cacheMeta['cid'] ?? 0) !== $cid) {
            continue;
         }
         $htmlFile = preg_replace('/\.meta\.json$/', '', $metaFile);
         if (is_string($htmlFile)) {
            @unlink($htmlFile);
         }
         @unlink($metaFile);
      }
      foreach (glob(self::baseDir() . 'content/' . $cid . '_*.html') ?: array() as $file) {
         @unlink($file);
      }

      @unlink(self::pageMetaPath($cid));
      if (class_exists(__NAMESPACE__ . '\\dbxContentSitemap')) {
         dbxContentSitemap::invalidate();
      }
   }

   public static function invalidateMenu(int $root): void {
      $root = (int) $root;
      $pattern = self::baseDir() . 'menu/' . $root . '_*.html';
      foreach (glob($pattern) ?: array() as $file) {
         @unlink($file);
      }
      self::invalidateAllFullPages();
   }

   public static function invalidateAllMenus(): void {
      foreach (glob(self::baseDir() . 'menu/*.html') ?: array() as $file) {
         @unlink($file);
      }
      self::invalidateAllFullPages();
      self::invalidateSitemap();
   }

   /** Menues sind Bestandteil jeder Full-Page-Ausgabe. */
   public static function invalidateAllFullPages(): int {
      // Zuerst die Generation wechseln: Ab diesem Moment koennen neue Requests
      // weder alte Dateien lesen noch unter deren Namen schreiben. Das schliesst
      // die Race Condition "Speichern waehrend ein alter Request rendert".
      self::cacheGeneration(true);

      $removed = 0;
      foreach (array('permalink-*.*.full-*.html', 'cid-*.*.full-*.html') as $pattern) {
         foreach (glob(self::baseDir() . 'content/' . $pattern) ?: array() as $file) {
            if (@unlink($file)) {
               $removed++;
            }
            @unlink(self::fullPageMetaPath($file));
         }
      }
      foreach (glob(self::baseDir() . 'content/full-page/*/*.htm') ?: array() as $file) {
         if (@unlink($file)) {
            $removed++;
         }
         @unlink(self::fullPageMetaPath($file));
         @rmdir(dirname($file));
      }
      foreach (glob(self::baseDir() . 'content/full-page/*.htm') ?: array() as $file) {
         if (@unlink($file)) {
            $removed++;
         }
      }
      foreach (glob(self::baseDir() . 'content/full-page/*.htm.meta.json') ?: array() as $metaFile) {
         @unlink($metaFile);
      }
      foreach (glob(self::baseDir() . 'content/*.html.meta.json') ?: array() as $metaFile) {
         @unlink($metaFile);
      }
      foreach (glob(self::baseDir() . 'content/full-page/*/*.htm.meta.json') ?: array() as $metaFile) {
         @unlink($metaFile);
         @rmdir(dirname($metaFile));
      }
      return $removed;
   }

   public static function invalidateSitemap(): void {
      if (class_exists(__NAMESPACE__ . '\\dbxContentSitemap')) {
         dbxContentSitemap::invalidate();
         return;
      }

      @unlink(self::baseDir() . 'meta/sitemap.xml');
   }

   public static function invalidateAll(): array {
      $stats = array(
         'content' => 0,
         'menu' => 0,
         'meta' => 0,
      );

      foreach (array('*.htm', '*.html') as $pattern) {
         foreach (glob(self::baseDir() . 'content/' . $pattern) ?: array() as $file) {
            if (@unlink($file)) {
               $stats['content']++;
            }
         }
      }
      foreach (glob(self::baseDir() . 'content/*.html.meta.json') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['meta']++;
         }
      }
      $stats['content'] += self::invalidateAllFullPages();

      foreach (glob(self::baseDir() . 'menu/*.html') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['menu']++;
         }
      }

      foreach (glob(self::baseDir() . 'meta/pages/*.json') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['meta']++;
         }
      }

      foreach (glob(self::baseDir() . 'meta/permalinks_*.json') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['meta']++;
         }
      }

      foreach (glob(self::baseDir() . 'meta/home_*.json') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['meta']++;
         }
      }

      return $stats;
   }

   public static function cacheStats(): array {
      self::ensureDirs();
      $content = array_values(array_filter(
         glob(self::baseDir() . 'content/full-page/*.htm') ?: array(),
         static fn(string $path): bool => str_ends_with(strtolower(basename($path)), '_' . self::FULL_PAGE_CACHE_VERSION . '.htm')
      ));
      $sitemapPath = self::baseDir() . 'meta/sitemap.xml';

      return array(
         'content' => count($content),
         'menu' => 0,
         'meta' => 0,
         'permalinks' => 0,
         'home' => 0,
         'sitemap' => is_file($sitemapPath) ? 1 : 0,
         'base_dir' => self::baseDir(),
      );
   }

   public static function invalidateFolderTree($db, int $folderId): void {
      if (!is_object($db)) {
         return;
      }

      $folderId = (int) $folderId;
      self::invalidateMenu($folderId);
      self::invalidateMenu(0);

      $folderIds = self::collectFolderIds($db, $folderId);
      foreach ($folderIds as $id) {
         self::invalidateMenu((int) $id);
      }

      $pages = $db->select(dbxContentLng::ddContent(), 'folder IN (' . implode(',', array_map('intval', $folderIds)) . ')', 'id', 'id', 'ASC', '', 0, 0, 0);
      if (is_array($pages)) {
         foreach ($pages as $page) {
            self::invalidateContent((int) ($page['id'] ?? 0));
         }
      }
   }

   private static function collectFolderIds($db, int $folderId): array {
      $folderId = (int) $folderId;
      $ids = array($folderId);
      $queue = array($folderId);

      while (count($queue)) {
         $current = (int) array_shift($queue);
         $rows = $db->select(dbxContentLng::ddFolder(), 'parent_id = ' . $current, 'id', 'id', 'ASC', '', 0, 0, 0);
         if (!is_array($rows)) {
            continue;
         }
         foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && !in_array($id, $ids, true)) {
               $ids[] = $id;
               $queue[] = $id;
            }
         }
      }

      return $ids;
   }

   private static function buildContentMeta(int $cid, string $lng = ''): array {
      $cid = (int) $cid;
      if ($cid <= 0) {
         return array();
      }

      $prevLng = null;
      if ($lng !== '') {
         $currentLng = (string) dbx()->get_system_var('dbx_lng', 'de');
         if ($lng !== $currentLng) {
            $prevLng = $currentLng;
            dbx()->set_system_var('dbx_lng', $lng);
         }
      }

      require_once __DIR__ . '/dbxContentRenderer.class.php';
      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db)) {
         if ($prevLng !== null) {
            dbx()->set_system_var('dbx_lng', $prevLng);
         }
         return array();
      }

      $rec = $db->select1(dbxContentLng::ddContent(), $cid, 'permalink,activ,folder,title,seo_title,description,keywords,meta_robots,seo_image_id,update_date,lng_uid', 0);
      if (!is_array($rec)) {
         if ($prevLng !== null) {
            dbx()->set_system_var('dbx_lng', $prevLng);
         }
         return array();
      }

      $renderer = new dbxContentRenderer();
      $rights = $renderer->getPublicFolderRights((int)($rec['folder'] ?? 0));
      $meta = array(
         'cid' => $cid,
         'permalink' => (string)($rec['permalink'] ?? ''),
         'rights' => $rights,
         'activ' => (int)($rec['activ'] ?? 1),
         'seo' => dbxContentRenderer::seoMetaFromRecord($rec),
      );
      if ($prevLng !== null) {
         dbx()->set_system_var('dbx_lng', $prevLng);
      }
      return $meta;
   }

   private static function currentPermalink(): string {
      $permalink = self::normalizePermalink((string) dbx()->get_system_var('dbx_permalink', ''));
      return $permalink === '' ? 'home' : $permalink;
   }

   private static function normalizePermalink(string $permalink): string {
      $permalink = trim(str_replace('\\', '/', $permalink));
      if ($permalink === 'undef' || $permalink === '/') {
         return '';
      }

      return trim($permalink, '/');
   }

   private static function fullPageMetaPath(string $htmlPath): string {
      return $htmlPath . '.meta.json';
   }

   private static function readJsonFile(string $path): ?array {
      if (!is_file($path) || !is_readable($path)) {
         return null;
      }

      $json = file_get_contents($path);
      if (!is_string($json) || $json === '') {
         return null;
      }

      $data = json_decode($json, true);
      return is_array($data) ? $data : null;
   }

   private static function isCompleteHtml(string $html): bool {
      if (trim($html) === '') {
         return false;
      }

      return (bool) preg_match('/^\s*<!doctype\s+html\b/i', $html)
          && stripos($html, '</html>') !== false;
   }

   /**
    * Prueft, ob das gecachte Dokument genau die Basis-URL des aktuellen
    * Requests verwendet. Ein fehlendes oder abweichendes base-Element macht
    * den Cache ungueltig, damit relative URLs nicht auf einen anderen Host,
    * Port oder Installationspfad zeigen.
    */
   private static function hasCurrentBaseHref(string $html): bool {
      $expected = self::normalizeBaseHref((string)dbx()->get_base_url());
      if ($expected === '') {
         return false;
      }

      if (!preg_match('~<head\b[^>]*>(.*?)</head>~is', $html, $headMatch)) {
         return false;
      }
      $head = preg_replace('~<!--.*?-->|<script\b.*?</script>|<style\b.*?</style>~is', '', (string)$headMatch[1]) ?? '';

      if (!preg_match(
         '~<base\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i',
         $head,
         $baseMatch
      )) {
         return false;
      }

      $actual = (string)($baseMatch[1] !== ''
         ? $baseMatch[1]
         : (($baseMatch[2] ?? '') !== '' ? $baseMatch[2] : ($baseMatch[3] ?? '')));

      return self::normalizeBaseHref($actual) === $expected;
   }

   /** Normalisiert nur URL-Bestandteile, bei denen Grossschreibung egal ist. */
   private static function normalizeBaseHref(string $href): string {
      $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $href = str_replace('\\', '/', preg_replace('/[\x00-\x1F\x7F]/', '', $href) ?? '');
      if ($href === '') {
         return '';
      }

      $parts = parse_url($href);
      if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
         return '';
      }

      $scheme = strtolower((string)$parts['scheme']);
      $host = strtolower((string)$parts['host']);
      $port = isset($parts['port']) ? (int)$parts['port'] : 0;
      $defaultPort = ($scheme === 'https') ? 443 : (($scheme === 'http') ? 80 : 0);

      $normalized = $scheme . '://' . $host;
      if ($port > 0 && $port !== $defaultPort) {
         $normalized .= ':' . $port;
      }
      $normalized .= (string)($parts['path'] ?? '');
      if (isset($parts['query'])) {
         $normalized .= '?' . $parts['query'];
      }
      if (isset($parts['fragment'])) {
         $normalized .= '#' . $parts['fragment'];
      }

      return $normalized;
   }

   /** Sessiongebundene Formular-Tokens duerfen nicht unveraendert geteilt werden. */
   private static function secureInputPattern(): string {
      return '/<input\b(?=[^>]*\bname\s*=\s*["\']?_[^"\'\s>]+)(?=[^>]*\bvalue\s*=\s*["\']?[a-f0-9]{64}["\']?)[^>]*>/i';
   }

   private static function atomicWrite(string $path, string $html): bool {
      if ($path === '') {
         return false;
      }

      $dir = dirname($path);
      if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
         return false;
      }

      try {
         $suffix = bin2hex(random_bytes(6));
      } catch (\Throwable $e) {
         $suffix = str_replace('.', '', uniqid('', true));
      }

      $tmp = $path . '.tmp-' . $suffix;
      $bytes = @file_put_contents($tmp, $html, LOCK_EX);
      if ($bytes === false || $bytes !== strlen($html)) {
         @unlink($tmp);
         return false;
      }

      if (@rename($tmp, $path)) {
         return true;
      }

      // Ein paralleler Request kann denselben vollstaendigen Cache bereits
      // gewonnen haben. Dessen Datei bleibt dann unangetastet.
      if (is_file($path) && is_readable($path)) {
         @unlink($tmp);
         return true;
      }

      @unlink($tmp);
      return false;
   }

   /**
    * Liefert bzw. rotiert die Cache-Generation.
    *
    * Normale Cache-Hits teilen sich eine Lesesperre. Eine exklusive Sperre ist
    * nur fuer Initialisierung und Invalidierung erforderlich.
    */
   private static function cacheGeneration(bool $rotate = false): string {
      $dir = self::baseDir() . 'content/full-page/';
      if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
         return '';
      }

      $handle = @fopen($dir . self::FULL_PAGE_GENERATION_FILE, 'c+b');
      if (!is_resource($handle)) {
         return '';
      }
      if (!@flock($handle, $rotate ? LOCK_EX : LOCK_SH)) {
         @fclose($handle);
         return '';
      }

      @rewind($handle);
      $generation = trim((string)stream_get_contents($handle));
      if (!$rotate && !preg_match('/^[a-f0-9]{24}$/', $generation)) {
         // Upgrade ohne festgehaltene SH-Sperre, danach unter EX erneut lesen.
         @flock($handle, LOCK_UN);
         if (!@flock($handle, LOCK_EX)) {
            @fclose($handle);
            return '';
         }
         @rewind($handle);
         $generation = trim((string)stream_get_contents($handle));
      }

      if ($rotate || !preg_match('/^[a-f0-9]{24}$/', $generation)) {
         try {
            $generation = bin2hex(random_bytes(12));
         } catch (\Throwable $e) {
            $generation = substr(hash('sha256', uniqid('', true) . microtime(true)), 0, 24);
         }

         $written = @ftruncate($handle, 0)
            && @rewind($handle)
            && @fwrite($handle, $generation) === strlen($generation)
            && @fflush($handle);
         if (!$written) {
            $generation = '';
         }
      }

      @flock($handle, LOCK_UN);
      @fclose($handle);
      return $generation;
   }

   /** Trennt Cache-Dateien verschiedener Hosts, Protokolle und Installationspfade. */
   private static function requestOriginToken(): string {
      $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
      if ($forwardedProto === 'http' || $forwardedProto === 'https') {
         $scheme = $forwardedProto;
      } else {
         $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
         $scheme = ($https !== '' && $https !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
            ? 'https'
            : 'http';
      }

      $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'local')));
      $host = preg_replace('/[^a-z0-9.:[\]-]+/i', '', $host) ?: 'local';
      $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
      $installPath = trim(str_replace('\\', '/', dirname($script)), '/.');

      return substr(hash('sha256', $scheme . '://' . $host . '/' . $installPath), 0, 16);
   }

   private static function safeToken(string $value, string $fallback): string {
      $value = strtolower(trim($value));
      if ($value === '' || !preg_match('/^[a-z0-9_-]+$/', $value)) {
         return $fallback;
      }
      return $value;
   }

   private static function permalinkFileToken(string $permalink): string {
      $permalink = strtolower(trim(str_replace('\\', '/', $permalink), '/'));
      if ($permalink === '') {
         $permalink = 'home';
      }

      $token = preg_replace('/[^a-z0-9_-]+/i', '-', $permalink);
      $token = trim((string) $token, '-_');
      if ($token === '') {
         $token = 'page';
      }

      if (strlen($token) > 72) {
         $token = substr($token, 0, 72);
      }

      // Der lesbare Slug allein kollidiert z. B. bei abc/def und abc-def.
      // Der Hash wird aus dem vollstaendigen normalisierten Permalink gebildet.
      return strtolower($token . '-' . substr(hash('sha256', $permalink), 0, 24));
   }

   private static function legacyPermalinkFileToken(string $permalink): string {
      $permalink = strtolower(trim(str_replace('\\', '/', $permalink), '/'));
      if ($permalink === '') {
         return 'home';
      }

      $token = preg_replace('/[^a-z0-9_-]+/i', '-', $permalink);
      $token = trim((string) $token, '-_');
      if ($token === '') {
         $token = 'page';
      }

      $hash = substr(sha1($permalink), 0, 8);
      if (strlen($token) > 80) {
         $token = substr($token, 0, 80);
      }

      return strtolower($token . '-' . $hash);
   }
}
