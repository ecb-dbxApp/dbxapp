<?php

class dbxContentPageCacheTestApi {
   public array $system = array();
   public array $request = array();
   public array $session = array();
   public array $config = array(
      'cache_content' => 1,
      'default_design_user' => 'dbxapp',
   );
   public int $effective_user = 0;
   public bool $demo_mode = false;
   private string $file_dir;
   private string $base_dir;

   public function __construct(string $file_dir) {
      $this->file_dir = rtrim($file_dir, '/\\') . DIRECTORY_SEPARATOR;
      $this->base_dir = $this->file_dir . 'app' . DIRECTORY_SEPARATOR;
      $this->writeConfigFile();
   }

   public function get_file_dir(): string {
      return $this->file_dir;
   }

   public function get_base_dir(): string {
      return $this->base_dir;
   }

   public function get_base_url(): string {
      return (string)($this->system['dbx_base_url'] ?? '');
   }

   public function os_path(string $path): string {
      return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
   }

   public function get_cfg(string $module, ?string $key = null) {
      if ($module !== 'dbx') {
         return $key === null ? array() : 'undef';
      }
      return $key === null ? $this->config : ($this->config[$key] ?? 'undef');
   }

   public function set_cfg(string $module, array $config): int {
      if ($module !== 'dbx') {
         return 0;
      }
      $this->config = $config;
      $this->writeConfigFile();
      return 1;
   }

   private function writeConfigFile(): void {
      $dir = $this->base_dir . 'dbx' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR
         . 'dbx' . DIRECTORY_SEPARATOR . 'cfg';
      if (!is_dir($dir)) {
         mkdir($dir, 0755, true);
      }
      file_put_contents(
         $dir . DIRECTORY_SEPARATOR . 'config.php',
         "<?php\n\$config['cache_content'] = " . (int)($this->config['cache_content'] ?? 1) . ";\n"
      );
   }

   public function get_request_var(string $key, $default = '', string $type = '') {
      $value = $this->request[$key] ?? $default;
      return $type === 'int' ? (int)$value : $value;
   }

   public function get_system_var(string $key, $default = '', string $type = '') {
      $value = $this->system[$key] ?? $default;
      return $type === 'int' ? (int)$value : $value;
   }

   public function set_system_var(string $key, $value): void {
      $this->system[$key] = $value;
   }

   public function get_session_var(string $key, $default = null, string $section = 'sys', string $module = 'modul') {
      return $this->session[$module][$section][$key] ?? $default;
   }

   public function set_session_var(string $key, $value, string $section = 'sys', string $module = 'modul'): void {
      $this->session[$module][$section][$key] = $value;
   }

   public function normalize_skin(string $skin): string {
      $skin = strtolower(trim($skin));
      return preg_match('/^[a-z0-9_-]+$/', $skin) ? $skin : 'blau';
   }

   public function is_design(string $design): bool {
      return in_array($design, array('dbxapp', 'flowers'), true);
   }

   public function get_system_obj(string $class): object {
      return $this;
   }

   public function user(): int {
      return $this->effective_user;
   }

   public function is_demo_mode(): bool {
      return $this->demo_mode;
   }
}

$test_root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
   . 'dbx-page-cache-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!mkdir($test_root, 0755, true) && !is_dir($test_root)) {
   fwrite(STDERR, "FAIL: Testverzeichnis konnte nicht erstellt werden.\n");
   exit(1);
}

$delete_tree = static function (string $path) use (&$delete_tree): void {
   $resolved = realpath($path);
   if ($resolved === false || !str_starts_with(basename($resolved), 'dbx-page-cache-test-')) {
      return;
   }
   $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
   );
   foreach ($iterator as $item) {
      $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
   }
   @rmdir($resolved);
};

$fail = static function (string $message, int $code = 2) use ($delete_tree, $test_root): void {
   $delete_tree($test_root);
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$GLOBALS['dbxContentPageCacheTestApi'] = new dbxContentPageCacheTestApi($test_root);
function dbx(): dbxContentPageCacheTestApi {
   return $GLOBALS['dbxContentPageCacheTestApi'];
}

require_once dirname(__DIR__) . '/include/dbxContentPageCache.class.php';

use dbx\dbxContent\dbxContentPageCache;

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
$_SERVER['SERVER_PORT'] = '80';
unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
$_GET = array();
$_SESSION = array('dbx' => array('current_user' => array('id' => 0)));

$api = dbx();
$api->system = array(
   'dbx_permalink' => 'abc/def',
   'dbx_lng' => 'de',
   'dbx_design' => 'dbxapp',
   'dbx_color' => 'blau',
   'dbx_edit' => 0,
   'dbx_ajax' => 0,
   'dbx_window' => 0,
   'dbx_base_url' => 'http://localhost/dbxapp/',
);

$slash_path = dbxContentPageCache::full_page_path('abc/def', 'de', 'dbxapp', 'blau');
$dash_path = dbxContentPageCache::full_page_path('abc-def', 'de', 'dbxapp', 'blau');
if ($slash_path === $dash_path) {
   $fail('abc/def und abc-def kollidieren weiterhin im Dateinamen.');
}
if (!preg_match('/-[a-f0-9]{24}_de_dbxapp_blau_[a-f0-9]{16}_[a-f0-9]{24}_v3\.htm$/', basename($slash_path))) {
   $fail('Der V3-Dateiname enthaelt nicht alle erwarteten Hash-/Variantenanteile.');
}

$_SERVER['HTTP_HOST'] = 'dbxapp.de';
$public_host_path = dbxContentPageCache::full_page_path('abc/def', 'de', 'dbxapp', 'blau');
$_SERVER['HTTP_HOST'] = 'localhost';
if ($public_host_path === $slash_path) {
   $fail('Caches verschiedener Hosts verwenden denselben Dateinamen.');
}

$legacy_v2 = dirname($slash_path) . DIRECTORY_SEPARATOR . 'legacy_de_dbxapp_blau_v2.htm';
file_put_contents($legacy_v2, '<!doctype html><html></html>');
dbxContentPageCache::ensure_dirs();
if (is_file($legacy_v2)) {
   $fail('Unsichere V2-Datei wurde nicht entfernt.');
}

$stale_v3 = preg_replace(
   '/_[a-f0-9]{24}_v3\.htm$/',
   '_000000000000000000000000_v3.htm',
   $slash_path
);
if (!is_string($stale_v3) || $stale_v3 === $slash_path) {
   $fail('Testdatei einer alten Cache-Generation konnte nicht abgeleitet werden.');
}
file_put_contents($stale_v3, '<!doctype html><html></html>');
if (dbxContentPageCache::purge_stale_full_pages() !== 1 || is_file($stale_v3)) {
   $fail('V3-Datei einer alten Cache-Generation wurde nicht bereinigt.');
}

foreach (array('dbx_design' => 'flowers', 'dbx_color' => 'dunkel', 'dbx_lng' => 'en') as $name => $value) {
   $_GET = array($name => $value);
   if (dbxContentPageCache::prepare_full_page_request()) {
      $fail('Zustandsschalter ' . $name . ' wurde vor seiner Session-Persistierung aus dem Full-Page-Cache bedient.');
   }
}
$_GET = array();

if (!dbxContentPageCache::prepare_full_page_request()) {
   $fail('Gueltiger Gast-Permalink wurde nicht fuer den Cache vorbereitet.');
}
$api->system['dbx_content_permalink_request'] = 1;
$api->system['dbx_content_route_cid'] = 51;
$api->system['dbx_master_modul'] = 'dbxContent';
if (!dbxContentPageCache::attach_resolved_content_route()) {
   $fail('Aufgeloeste Content-Route wurde nicht an den Cache gebunden.');
}

$html = "<!doctype html><html><head><base href=\"http://localhost/dbxapp/\"/></head><body>Unveraendert: &amp; <script>window.x = '<tag>';</script></body></html>";
http_response_code(200);
if (!dbxContentPageCache::write_full_page($html)) {
   $fail('Vollstaendige Gastseite wurde nicht atomar gespeichert.');
}
if (dbxContentPageCache::read_full_page() !== $html) {
   $fail('Cache-Lesen veraendert oder escaped die gespeicherten Bytes.');
}

$base_checked_path = (string)$api->system['dbx_full_page_cache_path'];
$wrong_base_html = str_replace(
   'http://localhost/dbxapp/',
   'https://dbxapp.de/',
   $html
);
file_put_contents($base_checked_path, $wrong_base_html);
if (dbxContentPageCache::read_full_page() !== null || is_file($base_checked_path)) {
   $fail('Cache-Datei mit falschem base href wurde gelesen oder nicht geloescht.');
}
if (dbxContentPageCache::write_full_page($wrong_base_html)) {
   $fail('HTML mit falschem base href wurde erneut in den Cache geschrieben.');
}
if (!dbxContentPageCache::write_full_page($html) || dbxContentPageCache::read_full_page() !== $html) {
   $fail('Nach ungueltigem base href wurde die korrekte Seite nicht neu gecacht.');
}

$old_path = (string)$api->system['dbx_full_page_cache_path'];
$old_generation = (string)$api->system['dbx_full_page_cache_generation'];
dbxContentPageCache::invalidate_all_full_pages();
if (dbxContentPageCache::write_full_page($html)) {
   $fail('Ein vor der Invalidierung gestarteter Request konnte veraltetes HTML nachschreiben.');
}

if (!dbxContentPageCache::prepare_full_page_request()) {
   $fail('Cache konnte nach Generationwechsel nicht neu vorbereitet werden.');
}
$api->system['dbx_content_permalink_request'] = 1;
$api->system['dbx_content_route_cid'] = 51;
$api->system['dbx_master_modul'] = 'dbxContent';
dbxContentPageCache::attach_resolved_content_route();
$new_path = (string)$api->system['dbx_full_page_cache_path'];
$new_generation = (string)$api->system['dbx_full_page_cache_generation'];
if ($old_path === $new_path || $old_generation === $new_generation) {
   $fail('Invalidierung hat die Cache-Generation nicht gewechselt.');
}
if (!dbxContentPageCache::write_full_page($html)) {
   $fail('Neue Cache-Generation konnte nicht geschrieben werden.');
}

if (!dbxContentPageCache::set_config_enabled(false)) {
   $fail('Cache-Konfiguration konnte im Test nicht ausgeschaltet werden.');
}
if (!is_file($new_path) || dbxContentPageCache::read_full_page() !== $html) {
   $fail('Ausschalten des Cache-Schreibens hat einen vorhandenen Treffer entfernt oder deaktiviert.');
}
if (dbxContentPageCache::is_write_enabled()) {
   $fail('Ausgeschaltetes Cache-Schreiben wird weiterhin als aktiv gemeldet.');
}
if (dbxContentPageCache::write_full_page(str_replace('Unveraendert', 'Neu', $html))) {
   $fail('Bei ausgeschaltetem Cache-Schreiben wurde eine Seite gespeichert.');
}
if ((string)file_get_contents($new_path) !== $html) {
   $fail('Vorhandene Cache-Datei wurde bei ausgeschaltetem Schreiben veraendert.');
}
$after_toggle_path = dbxContentPageCache::full_page_path('abc/def', 'de', 'dbxapp', 'blau');
if ($after_toggle_path !== $new_path) {
   $fail('Ausschalten des Cache-Schreibens hat die bestehende Generation gewechselt.');
}

// Fachliche Aenderungen invalidieren auch bei pausiertem Schreiben weiterhin
// vorhandene Treffer. Danach bleibt der MISS live und wird nicht gespeichert.
dbxContentPageCache::invalidate_all_full_pages();
if (is_file($new_path)) {
   $fail('Inhaltsinvalidierung hat bei pausiertem Schreiben eine alte Seite behalten.');
}
$after_invalidation_path = dbxContentPageCache::full_page_path('abc/def', 'de', 'dbxapp', 'blau');
if ($after_invalidation_path === $new_path) {
   $fail('Inhaltsinvalidierung hat die Generation bei pausiertem Schreiben nicht gewechselt.');
}
if (!dbxContentPageCache::prepare_full_page_request()) {
   $fail('Lesepfad konnte bei pausiertem Schreiben nicht vorbereitet werden.');
}
$api->system['dbx_content_permalink_request'] = 1;
$api->system['dbx_content_route_cid'] = 51;
$api->system['dbx_master_modul'] = 'dbxContent';
dbxContentPageCache::attach_resolved_content_route();
if (dbxContentPageCache::read_full_page() !== null || dbxContentPageCache::write_full_page($html)) {
   $fail('Cache-MISS wurde bei pausiertem Schreiben nicht ausschliesslich live behandelt.');
}

if (!dbxContentPageCache::set_config_enabled(true) || !dbxContentPageCache::is_write_enabled()) {
   $fail('Cache-Schreiben konnte nicht wieder eingeschaltet werden.');
}

$api->effective_user = 1;
if (dbxContentPageCache::is_enabled()) {
   $fail('Effektiver Admin-/Bypass-Benutzer wird als Gast behandelt.');
}
$api->effective_user = 7;
if (dbxContentPageCache::is_enabled()) {
   $fail('Numerische Benutzer-ID als String wird als Gast behandelt.');
}
$api->effective_user = 0;
$api->set_session_var('cart', array(12 => 2), 'state', 'dbxShop');
if (dbxContentPageCache::is_enabled()) {
   $fail('Gast mit Warenkorb wuerde weiterhin einen gemeinsamen Cache verwenden.');
}
$api->set_session_var('cart', array(), 'state', 'dbxShop');

$api->demo_mode = true;
if (dbxContentPageCache::prepare_full_page_request()) {
   $fail('Eine Demo-Seite wurde fuer den Gastseiten-Cache vorbereitet.');
}

$delete_tree($test_root);
echo "OK dbxContent full-page cache\n";
