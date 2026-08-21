<?php
namespace dbx\dbxContent;

require_once __DIR__ . '/dbxContentLng.class.php';
require_once __DIR__ . '/dbxEarlyPageCache.class.php';

class dbxContentPageCache {

   private static bool $dirs_ready = false;
   private const FULL_PAGE_CACHE_VERSION = 'v3';
   private const FULL_PAGE_GENERATION_FILE = '.generation';

   public static function is_config_enabled(): bool {
      $config_file = '';
      try {
         $config_file = rtrim((string)dbx()->get_base_dir(), '/\\') . '/dbx/modules/dbx/cfg/config.php';
      } catch (\Throwable $e) {
         $config_file = dirname(__DIR__, 2) . '/dbx/cfg/config.php';
      }
      if (is_file($config_file) && is_readable($config_file)) {
         $read_config = static function(string $path): array {
            $config = array();
            include $path;
            return is_array($config) ? $config : array();
         };
         $file_config = $read_config($config_file);
         if (array_key_exists('cache_content', $file_config)) {
            return (int)$file_config['cache_content'] === 1;
         }
      }

      $enabled = dbx()->get_cfg('dbx', 'cache_content');
      if ($enabled === 'undef' || $enabled === '' || $enabled === null) {
         return true;
      }

      return (int) $enabled === 1;
   }

   public static function set_config_enabled(bool $enabled): bool {
      $config = dbx()->get_cfg('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $config['cache_content'] = $enabled ? 1 : 0;
      // Der Schalter steuert ausschliesslich neue Schreibvorgaenge. Bereits
      // vorhandene Treffer bleiben lesbar und werden nicht geloescht.
      return (int) dbx()->set_cfg('dbx', $config) > 0;
   }

   /** Abwaertskompatibel: "aktiv" bezeichnet das Schreiben neuer Seiten. */
   public static function is_enabled(): bool {
      return self::is_write_enabled();
   }

   /** Darf der aktuelle Request vorhandene Gastseiten aus dem Cache lesen? */
   public static function is_read_enabled(): bool {
      if (!self::is_guest_session() || self::has_personalized_guest_state()) {
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
   public static function is_write_enabled(): bool {
      return self::is_read_enabled() && self::is_config_enabled();
   }

   /** Nur der effektive Benutzerkontext entscheidet, nicht der rohe Session-Typ. */
   private static function is_guest_session(): bool {
      try {
         // user() beruecksichtigt auch dbxRunAsAdmin. Ohne diese Abfrage konnte
         // der Admin-Bypass als Gast HTML in den gemeinsamen Cache schreiben.
         return (int)dbx()->user() <= 0;
      } catch (\Throwable $e) {
         return true;
      }
   }

   /** Sessionabhaengige Frontend-Werte duerfen nicht in den Gast-Cache gelangen. */
   private static function has_personalized_guest_state(): bool {
      $cart = dbx()->get_session_var('cart', array(), 'state', 'dbxShop');
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

   public static function is_content_route(): bool {
      if (dbx()->get_system_var('dbx_modul') !== 'dbxContent') {
         return false;
      }

      $action = (string) dbx()->get_system_var('dbx_run1', '');
      return $action === 'show';
   }

   public static function is_permalink_page_request(): bool {
      if (!self::is_enabled()) {
         return false;
      }

      if ((int) dbx()->get_system_var('dbx_content_permalink_request', 0, 'int') !== 1) {
         return false;
      }

      return (int) dbx()->get_system_var('dbx_content_route_cid', 0, 'int') > 0;
   }

   /** Fruehe Pruefung ohne Permalink-Index und ohne Content-Datenbank. */
   private static function is_raw_permalink_request(): bool {
      // Lesen bleibt auch bei ausgeschaltetem Cache-Schreiben aktiv.
      if (!self::is_read_enabled()) {
         return false;
      }

      if (trim((string) dbx()->get_request_var('dbx_modul', '', 'parameter')) !== ''
          || trim((string) dbx()->get_request_var('dbx_run1', '', 'parameter')) !== ''
          || (int) dbx()->get_request_var('cid', 0, 'int') > 0
          || (int) dbx()->get_request_var('dbx_cid', 0, 'int') > 0) {
         return false;
      }

      $permalink = self::current_permalink();
      if ($permalink === '' || in_array($permalink, array('admin', 'sitemap', 'sitemap.xml', 'robots.txt'), true)) {
         return false;
      }

      // Auch reine Darstellungsparameter muessen durch den regulaeren Request
      // laufen: check_remember() persistiert Sprache, Design und Farbe in der
      // Session. Ein Full-Page-Hit wuerde vorher abbrechen und der naechste
      // parameterlose Aufruf faiele auf den alten Zustand zurueck.
      if ($_GET !== array()) {
         return false;
      }

      return true;
   }

   /**
    * Bereitet den Gast-Seiten-Cache nur aus dem bereits erkannten URL-Permalink
    * vor. Fuer einen Treffer ist deshalb keine Content-/CID-Datenbankabfrage
    * notwendig.
    */
   public static function prepare_full_page_request(): bool {
      dbx()->set_system_var('dbx_full_page_cache_prepared', 0);
      dbx()->set_system_var('dbx_full_page_cache_path', '');
      dbx()->set_system_var('dbx_full_page_cache_file', '');
      dbx()->set_system_var('dbx_full_page_cache_generation', '');
      dbx()->set_system_var('dbx_full_page_cache_cid', 0);
      dbx()->set_system_var('dbx_full_page_cache_lng', '');

      // Demo-Seiten enthalten bewusst die sichtbare Administration. Sie
      // duerfen nie als normale Gastseite in den Full-Page-Cache gelangen.
      if (dbx()->is_demo_mode()) {
         return false;
      }

      if (!self::is_raw_permalink_request()) {
         return false;
      }

      // Die leere Root-Route ist fachlich dieselbe Route wie /home. Dadurch
      // erzeugt bereits der erste Root-MISS exakt dieselbe fertige Seite.
      $raw_permalink = self::normalize_permalink((string)dbx()->get_system_var('dbx_permalink', ''));
      if ($raw_permalink === '') {
         dbx()->set_system_var('dbx_permalink', 'home');
         dbx()->set_system_var('dbx_self_url', 'home');
      }

      $permalink = self::current_permalink();
      $lng = self::safe_token(self::current_lng(), 'de');
      $design = self::current_design();
      $skin = dbx()->get_system_obj('dbxPresentation')->normalize_skin((string)dbx()->get_system_var('dbx_color', 'blau'));
      $generation = self::cache_generation();
      if ($generation === '') {
         return false;
      }
      $path = self::full_page_path_for_generation($permalink, $lng, $design, $skin, $generation);

      dbx()->set_system_var('dbx_full_page_cache_prepared', 1);
      dbx()->set_system_var('dbx_full_page_cache_path', $path);
      dbx()->set_system_var('dbx_full_page_cache_file', basename($path));
      dbx()->set_system_var('dbx_full_page_cache_permalink', $permalink);
      dbx()->set_system_var('dbx_full_page_cache_design', $design);
      dbx()->set_system_var('dbx_full_page_cache_lng', $lng);
      dbx()->set_system_var('dbx_full_page_cache_generation', $generation);

      return true;
   }

   /** Bindet nach einem MISS die live aufgeloeste Content-ID an den Schreibvorgang. */
   public static function attach_resolved_content_route(): bool {
      if (!self::is_prepared_full_page_request()) {
         return false;
      }

      // Ein sprachspezifischer Permalink kann die aktive Sprache erst bei der
      // Content-Aufloesung eindeutig bestimmen. Den vorbereiteten Schreibpfad
      // dann auf die erkannte Sprache umstellen, statt fremdsprachigen Inhalt
      // unter dem vorherigen Session-Sprachschluessel abzulegen.
      $prepared_lng = (string)dbx()->get_system_var('dbx_full_page_cache_lng', '');
      $current_lng = self::safe_token(self::current_lng(), 'de');
      if ($prepared_lng !== $current_lng && !self::prepare_full_page_request()) {
         return false;
      }

      // Ein ungueltiger Permalink kann die Home-Darstellung mit HTTP 404
      // verwenden. Seine Antwort darf weder unter dem Tippfehler noch als
      // Home-Cache geschrieben werden.
      $prepared_permalink = (string)dbx()->get_system_var('dbx_full_page_cache_permalink', '');
      if ((int)dbx()->get_system_var('dbx_content_not_found', 0, 'int') === 1
          || $prepared_permalink !== self::current_permalink()) {
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

   public static function is_prepared_full_page_request(): bool {
      return (int) dbx()->get_system_var('dbx_full_page_cache_prepared', 0, 'int') === 1
         && self::is_guest_session()
         && !self::has_personalized_guest_state()
         && self::current_permalink() !== ''
         && trim((string) dbx()->get_system_var('dbx_full_page_cache_path', '')) !== '';
   }

   /** Liefert ausschliesslich eine bereits komplett gerenderte HTML-Seite. */
   public static function read_full_page(): ?string {
      if (!self::is_prepared_full_page_request()) {
         return null;
      }

      $prepared_generation = (string)dbx()->get_system_var('dbx_full_page_cache_generation', '');
      if ($prepared_generation === '' || !hash_equals($prepared_generation, self::cache_generation())) {
         return null;
      }

      $path = (string) dbx()->get_system_var('dbx_full_page_cache_path', '');
      if (!is_file($path) || !is_readable($path)) {
         return null;
      }

      $html = file_get_contents($path);
      if (!is_string($html) || !self::is_complete_html($html) || !self::has_current_base_href($html)) {
         @unlink($path);
         @unlink(self::full_page_meta_path($path));
         return null;
      }

      // Eine Invalidierung waehrend des Lesens macht auch bereits gelesene
      // Bytes ungueltig. So wird nach einem Speichern kein alter Stand bedient.
      if (!hash_equals($prepared_generation, self::cache_generation())) {
         return null;
      }

      // file_get_contents liefert die unveraenderten Bytes. Kein Escaping,
      // keine Interpretation und keine Session-abhaengige Nachbearbeitung.
      // Bestehende Cache-Dateien werden beim ersten regulaeren Treffer in den
      // fruehen Lookup uebernommen; ein Cache-Leeren nach dem Update ist damit
      // nicht erforderlich.
      dbxEarlyPageCache::register($path, $prepared_generation);
      return $html;
   }

   /** Schreibt die finale Ausgabe nach Design, Modulen, Interpreter und Filtern. */
   public static function write_full_page(string $html): bool {
      if (!self::is_write_enabled()
          || !self::is_prepared_full_page_request()
          || http_response_code() !== 200
          || (string) dbx()->get_system_var('dbx_master_modul', '') !== 'dbxContent'
          || (int) dbx()->get_system_var('dbx_full_page_cache_cid', 0, 'int') <= 0
          || !self::is_complete_html($html)
          || !self::has_current_base_href($html)
          || preg_match(self::secure_input_pattern(), $html)) {
         return false;
      }

      $prepared_generation = (string)dbx()->get_system_var('dbx_full_page_cache_generation', '');
      if ($prepared_generation === '' || !hash_equals($prepared_generation, self::cache_generation())) {
         // Ein paralleler Speichervorgang hat den Cache inzwischen verworfen.
         // Der alte Request darf seinen inzwischen veralteten Stand nicht in
         // die neue Cache-Generation schreiben.
         return false;
      }

      self::ensure_dirs();
      $path = (string) dbx()->get_system_var('dbx_full_page_cache_path', '');
      if (!self::atomic_write($path, $html)) {
         return false;
      }

      // Der schlanke Frontcontroller-Lookup wird erst nach dem vollstaendigen,
      // validierten HTML geschrieben. Schlaegt er fehl, bleibt der regulaere
      // Page-Cache weiterhin korrekt und lesbar.
      dbxEarlyPageCache::register($path, $prepared_generation);
      return true;
   }

   public static function base_dir(): string {
      $dir = rtrim(dbx()->get_file_dir(), '/\\') . '/cache/';
      return dbx()->os_path($dir);
   }

   public static function menu_variant_flat(int $flat = 1): string {
      return 'flat-' . ((int) $flat === 0 ? 0 : 1);
   }

   public static function menu_variant_load(int $deep = 9, string $mode = '', string $label = ''): string {
      $deep = max(1, (int) $deep);
      $mode = strtolower(trim($mode));
      if ($mode === '') {
         $mode = 'default';
      }
      $mode = self::safe_token($mode, 'default');
      $label = trim((string) $label);
      $label_key = $label !== '' ? substr(sha1($label), 0, 8) : 'menu';

      return 'load-' . $deep . '-' . $mode . '-' . $label_key;
   }

   public static function current_lng(): string {
      $lng = trim((string) dbx()->get_system_var('dbx_lng', 'de'));
      return $lng !== '' ? $lng : 'de';
   }

   /** Das Design ist Bestandteil der vollstaendigen HTML-Ausgabe. */
   public static function current_design(): string {
      $config = dbx()->get_cfg('dbx');
      if (!is_array($config)) {
         $config = array();
      }

      $user_default = trim((string)($config['default_design_user'] ?? 'dbxapp'));
      if ($user_default === '') {
         $user_default = 'dbxapp';
      }

      $design = trim((string)dbx()->get_system_var('dbx_design', $user_default));
      // Der Full-Page-Cache gilt nur fuer Gaeste. Die Aliase user/admin werden
      // daher genauso wie in check_design auf das Frontend-Design aufgeloest.
      $presentation = dbx()->get_system_obj('dbxPresentation');
      if ($design === '' || $design === 'user' || $design === 'admin' || !$presentation->is_design($design)) {
         $design = $user_default;
      }
      if (!$presentation->is_design($design)) {
         $design = 'dbxapp';
      }

      return self::safe_token($design, 'dbxapp');
   }

   public static function ensure_dirs(): void {
      if (self::$dirs_ready) {
         return;
      }

      foreach (array('content', 'content/full-page', 'meta') as $sub) {
         $dir = self::base_dir() . $sub;
         if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
         }
      }

      self::run_generation_maintenance();
      self::$dirs_ready = true;
   }

   /** Führt teure Verzeichnisbereinigungen nur einmal je Cache-Generation aus. */
   private static function run_generation_maintenance(): void {
      $generation = self::cache_generation();
      if ($generation === '') {
         return;
      }

      $marker = self::base_dir() . 'meta/.maintenance-' . self::FULL_PAGE_CACHE_VERSION;
      $maintained_generation = is_file($marker) ? trim((string)file_get_contents($marker)) : '';
      if (hash_equals($generation, $maintained_generation)) {
         return;
      }

      self::purge_legacy_menu_cache();
      self::purge_legacy_page_cache();
      self::purge_stale_full_pages($generation);
      self::atomic_write($marker, $generation);
   }

   /** Entfernt die nicht mehr verwendeten Permalink-, Home- und Meta-Caches. */
   private static function purge_legacy_page_cache(): void {
      foreach (array(
         self::base_dir() . 'meta/pages/*.json',
         self::base_dir() . 'meta/permalinks_*.json',
         self::base_dir() . 'meta/home_*.json',
      ) as $pattern) {
         foreach (glob($pattern) ?: array() as $file) {
            @unlink($file);
         }
      }

      $content_dir = self::base_dir() . 'content/';
      foreach (array_merge(glob($content_dir . '*.htm') ?: array(), glob($content_dir . '*.html') ?: array()) as $file) {
         @unlink($file);
         @unlink(self::full_page_meta_path($file));
      }
      foreach (glob($content_dir . '*.html.meta.json') ?: array() as $meta_file) {
         $html_file = preg_replace('/\.meta\.json$/', '', $meta_file);
         if (!is_string($html_file) || !is_file($html_file)) {
            @unlink($meta_file);
         }
      }
      // Hash-Unterordner und Meta-Dateien des alten Full-Page-Caches werden
      // nicht mehr verwendet. Neue Dateien liegen direkt in full-page/.
      foreach (glob($content_dir . 'full-page/*.htm.meta.json') ?: array() as $meta_file) {
         @unlink($meta_file);
      }
      // V1 enthielt kein Design, V2 keinen kollisionsfreien Permalink-/Host-Key
      // und keine Generation. Nur die aktuelle V3 darf weiterverwendet werden.
      foreach (glob($content_dir . 'full-page/*.htm') ?: array() as $full_page_file) {
         if (!str_ends_with(strtolower(basename($full_page_file)), '_' . self::FULL_PAGE_CACHE_VERSION . '.htm')) {
            @unlink($full_page_file);
         }
      }
      foreach (glob($content_dir . 'full-page/*', GLOB_ONLYDIR) ?: array() as $legacy_dir) {
         foreach (glob($legacy_dir . '/*') ?: array() as $legacy_file) {
            if (is_file($legacy_file)) {
               @unlink($legacy_file);
            }
         }
         @rmdir($legacy_dir);
      }
   }

   /** Entfernt den nicht mehr verwendeten Menu-Cache. */
   public static function purge_legacy_menu_cache(): int {
      $dir = self::base_dir() . 'menu/';
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

   public static function content_path(int $cid, string $lng): string {
      $cid = (int) $cid;
      $lng = self::safe_token($lng, 'de');
      return self::base_dir() . 'content/cid-' . $cid . '.' . $lng . '.htm';
   }

   public static function full_page_path(string $permalink, string $lng, string $design = '', string $skin = ''): string {
      return self::full_page_path_for_generation($permalink, $lng, $design, $skin, self::cache_generation());
   }

   private static function full_page_path_for_generation(string $permalink, string $lng, string $design, string $skin, string $generation): string {
      $normalized_permalink = self::normalize_permalink($permalink);
      $permalink = self::permalink_file_token($normalized_permalink);
      $lng = self::safe_token($lng, 'de');
      $design = self::safe_token($design !== '' ? $design : self::current_design(), 'dbxapp');
      $skin = dbx()->get_system_obj('dbxPresentation')->normalize_skin(
         $skin !== '' ? $skin : (string)dbx()->get_system_var('dbx_color', 'blau')
      );
      $skin = self::safe_token($skin, 'blau');
      $generation = self::safe_token($generation, 'invalid');
      $origin = self::request_origin_token();

      return self::base_dir() . 'content/full-page/'
         . $permalink . '_' . $lng . '_' . $design . '_' . $skin . '_'
         . $origin . '_' . $generation . '_' . self::FULL_PAGE_CACHE_VERSION . '.htm';
   }

   public static function permalink_content_path(string $permalink, string $lng): string {
      $permalink = self::permalink_file_token($permalink);
      $lng = self::safe_token($lng, 'de');

      return self::base_dir() . 'content/' . $permalink . '.' . $lng . '.htm';
   }

   public static function menu_path(int $root, string $lng, string $variant = 'flat-1'): string {
      $root = (int) $root;
      $lng = self::safe_token($lng, 'de');
      $variant = self::safe_token($variant, 'flat-1');

      return self::base_dir() . 'menu/' . $root . '_' . $lng . '_' . $variant . '.html';
   }

   public static function page_meta_path(int $cid): string {
      return self::base_dir() . 'meta/pages/' . (int) $cid . '.json';
   }

   public static function read_content(int $cid, string $lng = ''): ?string {
      // Der alte dbxContent-Zwischencache ist bewusst deaktiviert.
      return null;
   }

   public static function read_permalink_content(string $permalink, string $lng = ''): ?string {
      // Ein separater Permalink-Cache existiert nicht mehr.
      return null;
   }

   public static function write_content(int $cid, string $html, array $meta = array(), string $lng = ''): bool {
      // Der alte dbxContent-Zwischencache ist bewusst deaktiviert.
      return false;
   }

   public static function read_menu(int $root, string $variant = 'flat-1', string $lng = ''): ?string {
      return null;
   }

   public static function write_menu(string $html, int $root, string $variant = 'flat-1', string $lng = ''): bool {
      return false;
   }

   public static function read_page_meta(int $cid): ?array {
      $meta = self::build_content_meta($cid, self::current_lng());
      return count($meta) ? $meta : null;
   }

   public static function write_page_meta(int $cid, array $meta): bool {
      // Seiten-Metadaten werden live aus dbxContent gelesen und nicht gecacht.
      return true;
   }

   public static function invalidate_content(int $cid, bool $invalidate_shared_caches = true): void {
      $cid = (int) $cid;
      if ($cid <= 0) {
         return;
      }

      // Der Full-Page-Dateiname enthaelt bewusst keine Content-ID. Bei einer
      // Inhaltsaenderung werden deshalb alle fertigen Gastseiten verworfen.
      if ($invalidate_shared_caches) {
         self::invalidate_all_full_pages();
      }

      // Permalink-, Menü- und Seiten-Metacaches sind deaktivierte Altformate.
      // Sie werden einmal je Cache-Generation zentral bereinigt und müssen bei
      // einer normalen Inhaltsänderung nicht erneut durchsucht werden.
      foreach (glob(self::base_dir() . 'content/cid-' . $cid . '.*.htm') ?: array() as $file) {
         @unlink($file);
      }
      foreach (glob(self::base_dir() . 'content/cid-' . $cid . '.*.full-*.html') ?: array() as $file) {
         @unlink($file);
      }
      foreach (glob(self::base_dir() . 'content/' . $cid . '_*.html') ?: array() as $file) {
         @unlink($file);
      }

      @unlink(self::page_meta_path($cid));
      if ($invalidate_shared_caches) {
         self::invalidate_sitemap();
      }
   }

   public static function invalidate_menu(int $root, bool $invalidate_full_pages = true): void {
      $root = (int) $root;
      $pattern = self::base_dir() . 'menu/' . $root . '_*.html';
      foreach (glob($pattern) ?: array() as $file) {
         @unlink($file);
      }
      if ($invalidate_full_pages) {
         self::invalidate_all_full_pages();
      }
   }

   public static function invalidate_all_menus(): void {
      foreach (glob(self::base_dir() . 'menu/*.html') ?: array() as $file) {
         @unlink($file);
      }
      self::invalidate_all_full_pages();
      self::invalidate_sitemap();
   }

   /** Menues sind Bestandteil jeder Full-Page-Ausgabe. */
   public static function invalidate_all_full_pages(): int {
      // Zuerst die Generation wechseln: Ab diesem Moment koennen neue Requests
      // weder alte Dateien lesen noch unter deren Namen schreiben. Das schliesst
      // die Race Condition "Speichern waehrend ein alter Request rendert".
      $generation = self::cache_generation(true);

      $removed = self::purge_stale_full_pages($generation);
      foreach (array('permalink-*.*.full-*.html', 'cid-*.*.full-*.html') as $pattern) {
         foreach (glob(self::base_dir() . 'content/' . $pattern) ?: array() as $file) {
            if (@unlink($file)) {
               $removed++;
            }
            @unlink(self::full_page_meta_path($file));
         }
      }
      foreach (glob(self::base_dir() . 'content/full-page/*/*.htm') ?: array() as $file) {
         if (@unlink($file)) {
            $removed++;
         }
         @unlink(self::full_page_meta_path($file));
         @rmdir(dirname($file));
      }
      foreach (glob(self::base_dir() . 'content/full-page/*.htm.meta.json') ?: array() as $meta_file) {
         @unlink($meta_file);
      }
      foreach (glob(self::base_dir() . 'content/*.html.meta.json') ?: array() as $meta_file) {
         @unlink($meta_file);
      }
      foreach (glob(self::base_dir() . 'content/full-page/*/*.htm.meta.json') ?: array() as $meta_file) {
         @unlink($meta_file);
         @rmdir(dirname($meta_file));
      }
      return $removed;
   }

   /**
    * Entfernt V3-Dateien alter Generationen. Das ist insbesondere nach einem
    * Deployment wichtig, bei dem die Generation-Datei neu angelegt wurde,
    * waehrend alte HTML-Dateien im persistenten Cache-Verzeichnis verblieben.
    */
   public static function purge_stale_full_pages(string $generation = ''): int {
      if ($generation === '') {
         $generation = self::cache_generation();
      }
      if (!preg_match('/^[a-f0-9]{24}$/', $generation)) {
         return 0;
      }

      $removed = 0;
      $current_suffix = '_' . $generation . '_' . self::FULL_PAGE_CACHE_VERSION . '.htm';
      foreach (glob(self::base_dir() . 'content/full-page/*.htm') ?: array() as $file) {
         if (str_ends_with(strtolower(basename($file)), $current_suffix)) {
            continue;
         }
         if (@unlink($file)) {
            $removed++;
         }
         @unlink(self::full_page_meta_path($file));
      }
      foreach (glob(self::base_dir() . 'content/full-page/*.tmp-*') ?: array() as $temporary) {
         if (is_file($temporary) && (int)@filemtime($temporary) < time() - 300) {
            @unlink($temporary);
         }
      }
      return $removed;
   }

   public static function invalidate_sitemap(): void {
      if (class_exists(__NAMESPACE__ . '\\dbxContentSitemap')) {
         dbxContentSitemap::invalidate();
         return;
      }

      @unlink(self::base_dir() . 'meta/sitemap.xml');
   }

   public static function invalidate_all(): array {
      $stats = array(
         'content' => 0,
         'menu' => 0,
         'meta' => 0,
      );

      foreach (array('*.htm', '*.html') as $pattern) {
         foreach (glob(self::base_dir() . 'content/' . $pattern) ?: array() as $file) {
            if (@unlink($file)) {
               $stats['content']++;
            }
         }
      }
      foreach (glob(self::base_dir() . 'content/*.html.meta.json') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['meta']++;
         }
      }
      $stats['content'] += self::invalidate_all_full_pages();

      foreach (glob(self::base_dir() . 'menu/*.html') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['menu']++;
         }
      }

      foreach (glob(self::base_dir() . 'meta/pages/*.json') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['meta']++;
         }
      }

      foreach (glob(self::base_dir() . 'meta/permalinks_*.json') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['meta']++;
         }
      }

      foreach (glob(self::base_dir() . 'meta/home_*.json') ?: array() as $file) {
         if (@unlink($file)) {
            $stats['meta']++;
         }
      }

      return $stats;
   }

   public static function cache_stats(): array {
      self::ensure_dirs();
      self::purge_stale_full_pages();
      $content = array_values(array_filter(
         glob(self::base_dir() . 'content/full-page/*.htm') ?: array(),
         static fn(string $path): bool => str_ends_with(strtolower(basename($path)), '_' . self::FULL_PAGE_CACHE_VERSION . '.htm')
      ));
      $sitemap_path = self::base_dir() . 'meta/sitemap.xml';

      return array(
         'content' => count($content),
         'menu' => 0,
         'meta' => 0,
         'permalinks' => 0,
         'home' => 0,
         'sitemap' => is_file($sitemap_path) ? 1 : 0,
         'base_dir' => self::base_dir(),
      );
   }

   public static function invalidate_folder_tree($db, int $folder_id): void {
      if (!is_object($db)) {
         return;
      }

      $folder_id = (int) $folder_id;
      $folder_ids = self::collect_folder_ids($db, $folder_id);
      foreach (array_unique(array_merge(array(0), $folder_ids)) as $id) {
         self::invalidate_menu((int) $id, false);
      }

      $pages = $db->select(dbxContentLng::dd_content(), 'folder IN (' . implode(',', array_map('intval', $folder_ids)) . ')', 'id', 'id', 'ASC', '', 0, 0, 0);
      if (is_array($pages)) {
         foreach ($pages as $page) {
            self::invalidate_content((int) ($page['id'] ?? 0), false);
         }
      }
      self::invalidate_all_full_pages();
      self::invalidate_sitemap();
   }

   private static function collect_folder_ids($db, int $folder_id): array {
      $folder_id = (int) $folder_id;
      $ids = array($folder_id);
      $queue = array($folder_id);

      $rows = $db->select(dbxContentLng::dd_folder(), '1=1', 'id,parent_id', 'id', 'ASC', '', 0, 0, 0);
      $children = array();
      foreach (is_array($rows) ? $rows : array() as $row) {
         $parent_id = (int)($row['parent_id'] ?? 0);
         $id = (int)($row['id'] ?? 0);
         if ($id > 0) {
            $children[$parent_id][] = $id;
         }
      }

      while (count($queue)) {
         $current = (int) array_shift($queue);
         foreach ($children[$current] ?? array() as $id) {
            if ($id > 0 && !in_array($id, $ids, true)) {
               $ids[] = $id;
               $queue[] = $id;
            }
         }
      }

      return $ids;
   }

   private static function build_content_meta(int $cid, string $lng = ''): array {
      $cid = (int) $cid;
      if ($cid <= 0) {
         return array();
      }

      $prev_lng = null;
      if ($lng !== '') {
         $current_lng = (string) dbx()->get_system_var('dbx_lng', 'de');
         if ($lng !== $current_lng) {
            $prev_lng = $current_lng;
            dbx()->set_system_var('dbx_lng', $lng);
         }
      }

      require_once __DIR__ . '/dbxContentRenderer.class.php';
      $db = dbx()->get_system_obj('dbxDB');
      if (!is_object($db)) {
         if ($prev_lng !== null) {
            dbx()->set_system_var('dbx_lng', $prev_lng);
         }
         return array();
      }

      $rec = $db->select1(dbxContentLng::dd_content(), $cid, 'permalink,activ,folder,title,seo_title,description,keywords,meta_robots,seo_image_id,update_date,lng_uid', 0);
      if (!is_array($rec)) {
         if ($prev_lng !== null) {
            dbx()->set_system_var('dbx_lng', $prev_lng);
         }
         return array();
      }

      $renderer = new dbxContentRenderer();
      $rights = $renderer->get_public_folder_rights((int)($rec['folder'] ?? 0));
      $meta = array(
         'cid' => $cid,
         'permalink' => (string)($rec['permalink'] ?? ''),
         'rights' => $rights,
         'activ' => (int)($rec['activ'] ?? 1),
         'seo' => dbxContentRenderer::seo_meta_from_record($rec),
      );
      if ($prev_lng !== null) {
         dbx()->set_system_var('dbx_lng', $prev_lng);
      }
      return $meta;
   }

   private static function current_permalink(): string {
      $permalink = self::normalize_permalink((string) dbx()->get_system_var('dbx_permalink', ''));
      return $permalink === '' ? 'home' : $permalink;
   }

   private static function normalize_permalink(string $permalink): string {
      $permalink = trim(str_replace('\\', '/', $permalink));
      if ($permalink === 'undef' || $permalink === '/') {
         return '';
      }

      return trim($permalink, '/');
   }

   private static function full_page_meta_path(string $html_path): string {
      return $html_path . '.meta.json';
   }

   private static function is_complete_html(string $html): bool {
      if (trim($html) === '') {
         return false;
      }

      return (bool) preg_match('/^\s*<!doctype\s+html\b/i', $html)
          && stripos($html, '</html>') !== false;
   }

   /**
    * Prüft, ob das gecachte Dokument genau die Basis-URL des aktuellen
    * Requests verwendet. Ein fehlendes oder abweichendes base-Element macht
    * den Cache ungueltig, damit relative URLs nicht auf einen anderen Host,
    * Port oder Installationspfad zeigen.
    */
   private static function has_current_base_href(string $html): bool {
      $expected = self::normalize_base_href((string)dbx()->get_base_url());
      if ($expected === '') {
         return false;
      }

      if (!preg_match('~<head\b[^>]*>(.*?)</head>~is', $html, $head_match)) {
         return false;
      }
      $head = preg_replace('~<!--.*?-->|<script\b.*?</script>|<style\b.*?</style>~is', '', (string)$head_match[1]) ?? '';

      if (!preg_match(
         '~<base\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i',
         $head,
         $base_match
      )) {
         return false;
      }

      $actual = (string)($base_match[1] !== ''
         ? $base_match[1]
         : (($base_match[2] ?? '') !== '' ? $base_match[2] : ($base_match[3] ?? '')));

      return self::normalize_base_href($actual) === $expected;
   }

   /** Normalisiert nur URL-Bestandteile, bei denen Grossschreibung egal ist. */
   private static function normalize_base_href(string $href): string {
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
      $default_port = ($scheme === 'https') ? 443 : (($scheme === 'http') ? 80 : 0);

      $normalized = $scheme . '://' . $host;
      if ($port > 0 && $port !== $default_port) {
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
   private static function secure_input_pattern(): string {
      return '/<input\b(?=[^>]*\bname\s*=\s*["\']?_[^"\'\s>]+)(?=[^>]*\bvalue\s*=\s*["\']?[a-f0-9]{64}["\']?)[^>]*>/i';
   }

   private static function atomic_write(string $path, string $html): bool {
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
   private static function cache_generation(bool $rotate = false): string {
      $dir = self::base_dir() . 'content/full-page/';
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
   private static function request_origin_token(): string {
      $forwarded_proto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
      if ($forwarded_proto === 'http' || $forwarded_proto === 'https') {
         $scheme = $forwarded_proto;
      } else {
         $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
         $scheme = ($https !== '' && $https !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
            ? 'https'
            : 'http';
      }

      $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'local')));
      $host = preg_replace('/[^a-z0-9.:[\]-]+/i', '', $host) ?: 'local';
      $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
      $install_path = trim(str_replace('\\', '/', dirname($script)), '/.');

      return substr(hash('sha256', $scheme . '://' . $host . '/' . $install_path), 0, 16);
   }

   private static function safe_token(string $value, string $fallback): string {
      $value = strtolower(trim($value));
      if ($value === '' || !preg_match('/^[a-z0-9_-]+$/', $value)) {
         return $fallback;
      }
      return $value;
   }

   private static function permalink_file_token(string $permalink): string {
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

}
