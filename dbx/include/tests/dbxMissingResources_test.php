<?php

class dbxMissingResourcesTestApi {
   public array $system = array();
   public array $logged = array();

   public function get_system_var(string $key, $default = '', string $rules = '') {
      return $this->system[$key] ?? $default;
   }

   public function get_base_dir(): string {
      return rtrim(str_replace('\\', '/', dirname(__DIR__, 3)), '/') . '/';
   }

   public function log_missing($missing = ''): int {
      $this->logged[] = (string)$missing;
      return count($this->logged);
   }

   public function debug(...$values): void {
   }
}

$GLOBALS['dbxMissingResourcesTestApi'] = new dbxMissingResourcesTestApi();
function dbx(): dbxMissingResourcesTestApi {
   return $GLOBALS['dbxMissingResourcesTestApi'];
}

require_once dirname(__DIR__) . '/dbxWebApp.class.php';

$api = $GLOBALS['dbxMissingResourcesTestApi'];
$web = new dbxWebApp();

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$resourceExtensions = array(
   'js', 'mjs', 'css', 'map', 'json', 'wasm',
   'png', 'jpg', 'svg', 'webp', 'avif', 'ico',
   'woff', 'woff2', 'ttf', 'pdf', 'zip', 'mp3', 'mp4',
);
foreach ($resourceExtensions as $extension) {
   if ($web->get_is_resorce($extension) !== 1) {
      $fail("Ressourcenerweiterung .$extension wird nicht erkannt.", 1);
   }
}

foreach (array('', 'html', 'htm', 'php', 'dbx') as $extension) {
   if ($web->get_is_resorce($extension) !== 0) {
      $fail("Seiten-/Programmerweiterung .$extension wird faelschlich als Ressource erkannt.", 2);
   }
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$api->system['dbx_permalink'] = 'assets/__dbx-missing-resource-test__.svg';
http_response_code(200);
ob_start();
$handled = $web->check_missing();
$body = ob_get_clean();
if (!$handled || http_response_code() !== 404 || $body !== ''
   || $api->logged !== array('assets/__dbx-missing-resource-test__.svg')) {
   $fail('Fehlendes SVG wird nicht leer mit HTTP 404 protokolliert.', 3);
}

$api->system['dbx_permalink'] = 'assets/__dbx-missing-resource-test__%2Ewoff2';
http_response_code(200);
$handled = $web->check_missing();
if (!$handled || http_response_code() !== 404 || count($api->logged) !== 2) {
   $fail('URL-kodierte Ressourcenerweiterung wird nicht erkannt.', 4);
}

$_SERVER['REQUEST_METHOD'] = 'HEAD';
$api->system['dbx_permalink'] = 'assets/__dbx-missing-resource-test__.js';
http_response_code(200);
ob_start();
$handled = $web->check_missing();
$body = ob_get_clean();
if (!$handled || http_response_code() !== 404 || $body !== '' || count($api->logged) !== 3) {
   $fail('HEAD fuer eine fehlende Ressource ist nicht konsistent.', 5);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
foreach (array('shop/produkt', 'hilfe/seite.html', 'sitemap.xml', 'robots.txt') as $route) {
   $api->system['dbx_permalink'] = $route;
   http_response_code(200);
   if ($web->check_missing() || http_response_code() !== 200) {
      $fail("Normale/dynamische Route $route wird faelschlich als Ressource behandelt.", 6);
   }
}
if (count($api->logged) !== 3) {
   $fail('Normale Seiten oder Systemrouten wurden protokolliert.', 7);
}

echo "OK dbxMissing resources\n";
