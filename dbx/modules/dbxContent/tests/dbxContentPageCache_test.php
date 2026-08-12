<?php

class dbxContentPageCacheTestApi {
   public array $system = array();
   public array $request = array();
   public array $config = array(
      'cache_content' => 1,
      'default_design_user' => 'dbxapp',
   );
   public int $effectiveUser = 0;
   public bool $demoMode = false;
   private string $fileDir;
   private string $baseDir;

   public function __construct(string $fileDir) {
      $this->fileDir = rtrim($fileDir, '/\\') . DIRECTORY_SEPARATOR;
      $this->baseDir = $this->fileDir . 'app' . DIRECTORY_SEPARATOR;
      $this->writeConfigFile();
   }

   public function get_file_dir(): string {
      return $this->fileDir;
   }

   public function get_base_dir(): string {
      return $this->baseDir;
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
      $dir = $this->baseDir . 'dbx' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR
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

   public function normalize_skin(string $skin): string {
      $skin = strtolower(trim($skin));
      return preg_match('/^[a-z0-9_-]+$/', $skin) ? $skin : 'blau';
   }

   public function is_design(string $design): bool {
      return in_array($design, array('dbxapp', 'flowers'), true);
   }

   public function user(): int {
      return $this->effectiveUser;
   }

   public function is_demo_mode(): bool {
      return $this->demoMode;
   }
}

$testRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
   . 'dbx-page-cache-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!mkdir($testRoot, 0755, true) && !is_dir($testRoot)) {
   fwrite(STDERR, "FAIL: Testverzeichnis konnte nicht erstellt werden.\n");
   exit(1);
}

$deleteTree = static function (string $path) use (&$deleteTree): void {
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

$fail = static function (string $message, int $code = 2) use ($deleteTree, $testRoot): void {
   $deleteTree($testRoot);
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$GLOBALS['dbxContentPageCacheTestApi'] = new dbxContentPageCacheTestApi($testRoot);
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

$slashPath = dbxContentPageCache::fullPagePath('abc/def', 'de', 'dbxapp', 'blau');
$dashPath = dbxContentPageCache::fullPagePath('abc-def', 'de', 'dbxapp', 'blau');
if ($slashPath === $dashPath) {
   $fail('abc/def und abc-def kollidieren weiterhin im Dateinamen.');
}
if (!preg_match('/-[a-f0-9]{24}_de_dbxapp_blau_[a-f0-9]{16}_[a-f0-9]{24}_v3\.htm$/', basename($slashPath))) {
   $fail('Der V3-Dateiname enthaelt nicht alle erwarteten Hash-/Variantenanteile.');
}

$_SERVER['HTTP_HOST'] = 'dbxapp.de';
$publicHostPath = dbxContentPageCache::fullPagePath('abc/def', 'de', 'dbxapp', 'blau');
$_SERVER['HTTP_HOST'] = 'localhost';
if ($publicHostPath === $slashPath) {
   $fail('Caches verschiedener Hosts verwenden denselben Dateinamen.');
}

$legacyV2 = dirname($slashPath) . DIRECTORY_SEPARATOR . 'legacy_de_dbxapp_blau_v2.htm';
file_put_contents($legacyV2, '<!doctype html><html></html>');
dbxContentPageCache::ensureDirs();
if (is_file($legacyV2)) {
   $fail('Unsichere V2-Datei wurde nicht entfernt.');
}

$staleV3 = preg_replace(
   '/_[a-f0-9]{24}_v3\.htm$/',
   '_000000000000000000000000_v3.htm',
   $slashPath
);
if (!is_string($staleV3) || $staleV3 === $slashPath) {
   $fail('Testdatei einer alten Cache-Generation konnte nicht abgeleitet werden.');
}
file_put_contents($staleV3, '<!doctype html><html></html>');
if (dbxContentPageCache::purgeStaleFullPages() !== 1 || is_file($staleV3)) {
   $fail('V3-Datei einer alten Cache-Generation wurde nicht bereinigt.');
}

if (!dbxContentPageCache::prepareFullPageRequest()) {
   $fail('Gueltiger Gast-Permalink wurde nicht fuer den Cache vorbereitet.');
}
$api->system['dbx_content_permalink_request'] = 1;
$api->system['dbx_content_route_cid'] = 51;
$api->system['dbx_master_modul'] = 'dbxContent';
if (!dbxContentPageCache::attachResolvedContentRoute()) {
   $fail('Aufgeloeste Content-Route wurde nicht an den Cache gebunden.');
}

$html = "<!doctype html><html><head><base href=\"http://localhost/dbxapp/\"/></head><body>Unveraendert: &amp; <script>window.x = '<tag>';</script></body></html>";
http_response_code(200);
if (!dbxContentPageCache::writeFullPage($html)) {
   $fail('Vollstaendige Gastseite wurde nicht atomar gespeichert.');
}
if (dbxContentPageCache::readFullPage() !== $html) {
   $fail('Cache-Lesen veraendert oder escaped die gespeicherten Bytes.');
}

$baseCheckedPath = (string)$api->system['dbx_full_page_cache_path'];
$wrongBaseHtml = str_replace(
   'http://localhost/dbxapp/',
   'https://dbxapp.de/',
   $html
);
file_put_contents($baseCheckedPath, $wrongBaseHtml);
if (dbxContentPageCache::readFullPage() !== null || is_file($baseCheckedPath)) {
   $fail('Cache-Datei mit falschem base href wurde gelesen oder nicht geloescht.');
}
if (dbxContentPageCache::writeFullPage($wrongBaseHtml)) {
   $fail('HTML mit falschem base href wurde erneut in den Cache geschrieben.');
}
if (!dbxContentPageCache::writeFullPage($html) || dbxContentPageCache::readFullPage() !== $html) {
   $fail('Nach ungueltigem base href wurde die korrekte Seite nicht neu gecacht.');
}

$oldPath = (string)$api->system['dbx_full_page_cache_path'];
$oldGeneration = (string)$api->system['dbx_full_page_cache_generation'];
dbxContentPageCache::invalidateAllFullPages();
if (dbxContentPageCache::writeFullPage($html)) {
   $fail('Ein vor der Invalidierung gestarteter Request konnte veraltetes HTML nachschreiben.');
}

if (!dbxContentPageCache::prepareFullPageRequest()) {
   $fail('Cache konnte nach Generationwechsel nicht neu vorbereitet werden.');
}
$api->system['dbx_content_permalink_request'] = 1;
$api->system['dbx_content_route_cid'] = 51;
$api->system['dbx_master_modul'] = 'dbxContent';
dbxContentPageCache::attachResolvedContentRoute();
$newPath = (string)$api->system['dbx_full_page_cache_path'];
$newGeneration = (string)$api->system['dbx_full_page_cache_generation'];
if ($oldPath === $newPath || $oldGeneration === $newGeneration) {
   $fail('Invalidierung hat die Cache-Generation nicht gewechselt.');
}
if (!dbxContentPageCache::writeFullPage($html)) {
   $fail('Neue Cache-Generation konnte nicht geschrieben werden.');
}

if (!dbxContentPageCache::setConfigEnabled(false)) {
   $fail('Cache-Konfiguration konnte im Test nicht ausgeschaltet werden.');
}
if (!is_file($newPath) || dbxContentPageCache::readFullPage() !== $html) {
   $fail('Ausschalten des Cache-Schreibens hat einen vorhandenen Treffer entfernt oder deaktiviert.');
}
if (dbxContentPageCache::isWriteEnabled()) {
   $fail('Ausgeschaltetes Cache-Schreiben wird weiterhin als aktiv gemeldet.');
}
if (dbxContentPageCache::writeFullPage(str_replace('Unveraendert', 'Neu', $html))) {
   $fail('Bei ausgeschaltetem Cache-Schreiben wurde eine Seite gespeichert.');
}
if ((string)file_get_contents($newPath) !== $html) {
   $fail('Vorhandene Cache-Datei wurde bei ausgeschaltetem Schreiben veraendert.');
}
$afterTogglePath = dbxContentPageCache::fullPagePath('abc/def', 'de', 'dbxapp', 'blau');
if ($afterTogglePath !== $newPath) {
   $fail('Ausschalten des Cache-Schreibens hat die bestehende Generation gewechselt.');
}

// Fachliche Aenderungen invalidieren auch bei pausiertem Schreiben weiterhin
// vorhandene Treffer. Danach bleibt der MISS live und wird nicht gespeichert.
dbxContentPageCache::invalidateAllFullPages();
if (is_file($newPath)) {
   $fail('Inhaltsinvalidierung hat bei pausiertem Schreiben eine alte Seite behalten.');
}
$afterInvalidationPath = dbxContentPageCache::fullPagePath('abc/def', 'de', 'dbxapp', 'blau');
if ($afterInvalidationPath === $newPath) {
   $fail('Inhaltsinvalidierung hat die Generation bei pausiertem Schreiben nicht gewechselt.');
}
if (!dbxContentPageCache::prepareFullPageRequest()) {
   $fail('Lesepfad konnte bei pausiertem Schreiben nicht vorbereitet werden.');
}
$api->system['dbx_content_permalink_request'] = 1;
$api->system['dbx_content_route_cid'] = 51;
$api->system['dbx_master_modul'] = 'dbxContent';
dbxContentPageCache::attachResolvedContentRoute();
if (dbxContentPageCache::readFullPage() !== null || dbxContentPageCache::writeFullPage($html)) {
   $fail('Cache-MISS wurde bei pausiertem Schreiben nicht ausschliesslich live behandelt.');
}

if (!dbxContentPageCache::setConfigEnabled(true) || !dbxContentPageCache::isWriteEnabled()) {
   $fail('Cache-Schreiben konnte nicht wieder eingeschaltet werden.');
}

$api->effectiveUser = 1;
if (dbxContentPageCache::isEnabled()) {
   $fail('Effektiver Admin-/Bypass-Benutzer wird als Gast behandelt.');
}
$api->effectiveUser = 7;
$_SESSION['dbx']['current_user']['id'] = '7';
if (dbxContentPageCache::isEnabled()) {
   $fail('Numerische Benutzer-ID als String wird als Gast behandelt.');
}
$api->effectiveUser = 0;
$_SESSION['dbx']['current_user']['id'] = 0;
$_SESSION['dbxShop_cart'] = array(12 => 2);
if (dbxContentPageCache::isEnabled()) {
   $fail('Gast mit Warenkorb wuerde weiterhin einen gemeinsamen Cache verwenden.');
}
$_SESSION['dbxShop_cart'] = array();

$api->demoMode = true;
if (dbxContentPageCache::prepareFullPageRequest()) {
   $fail('Eine Demo-Seite wurde fuer den Gastseiten-Cache vorbereitet.');
}

$deleteTree($testRoot);
echo "OK dbxContent full-page cache\n";
