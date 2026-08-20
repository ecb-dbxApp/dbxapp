<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxEarlyPageCache.class.php';

use dbx\dbxContent\dbxEarlyPageCache;

$test_root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
   . 'dbx-early-page-cache-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
$full_page_dir = $test_root . '/files/cache/content/full-page';
if (!mkdir($full_page_dir, 0755, true) && !is_dir($full_page_dir)) {
   fwrite(STDERR, "FAIL: Testverzeichnis konnte nicht erstellt werden.\n");
   exit(1);
}

$delete_tree = static function(string $path) use (&$delete_tree): void {
   $resolved = realpath($path);
   if ($resolved === false || !str_starts_with(basename($resolved), 'dbx-early-page-cache-test-')) {
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

$fail = static function(string $message) use ($delete_tree, $test_root): void {
   $delete_tree($test_root);
   fwrite(STDERR, "FAIL: $message\n");
   exit(2);
};

$generation = '1234567890abcdef12345678';
$file = 'test-0123456789abcdef01234567_de_dbxapp_blau_0123456789abcdef_'
   . $generation . '_v3.htm';
$cache_path = $full_page_dir . '/' . $file;
$html = '<!doctype html><html><head><title>Cache</title></head><body>Early</body></html>';
file_put_contents($full_page_dir . '/.generation', $generation);
file_put_contents($cache_path, $html);

$_SERVER = array_merge($_SERVER, array(
   'REQUEST_METHOD' => 'GET',
   'REQUEST_URI' => '/dbxapp/test',
   'SCRIPT_NAME' => '/dbxapp/index.php',
   'HTTP_HOST' => 'localhost',
   'SERVER_PORT' => '80',
));
unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_AUTHORIZATION']);
$_GET = array();
$_COOKIE = array();

if (!dbxEarlyPageCache::register($cache_path, $generation)) {
   $fail('Sichere Gast-Route wurde nicht registriert.');
}
$response = dbxEarlyPageCache::find_response($test_root);
if (!is_array($response) || ($response['html'] ?? '') !== $html) {
   $fail('Registrierte Gastseite wurde nicht vor dem Bootstrap gefunden.');
}

$_COOKIE = array('DBXSESSIDABCDEF123456' => 'session');
if (dbxEarlyPageCache::find_response($test_root) !== null) {
   $fail('Request mit dbxApp-Session-Cookie wurde aus dem fruehen Cache bedient.');
}
$_COOKIE = array();

$_GET = array('dbx_edit' => '1');
$_SERVER['REQUEST_URI'] = '/dbxapp/test?dbx_edit=1';
if (dbxEarlyPageCache::find_response($test_root) !== null) {
   $fail('Personalisierender Steuerparameter wurde im fruehen Cache akzeptiert.');
}
$_GET = array();
$_SERVER['REQUEST_URI'] = '/dbxapp/test';

file_put_contents($full_page_dir . '/.generation', 'abcdef1234567890abcdef12');
if (dbxEarlyPageCache::find_response($test_root) !== null) {
   $fail('Route einer alten Cache-Generation wurde ausgeliefert.');
}

$delete_tree($test_root);
echo "OK dbxContent early page cache guest boundary and generation invalidation\n";
