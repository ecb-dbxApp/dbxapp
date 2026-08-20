<?php

require_once dirname(__DIR__, 3) . '/include/tests/dbxModuleSourceBundle.php';

$file = dirname(__DIR__) . '/include/dbxShopRepository.class.php';
$repo = dbx_test_module_source_bundle($file);

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

if (!is_string($repo)) {
   $fail('Shop-Repository konnte nicht gelesen werden.', 1);
}

if (strpos($repo, 'public function install(bool $maintenance = false): void') === false
   || strpos($repo, 'if (!$maintenance || $maintenance_done)') === false
   || strpos($repo, '$this->sync_shop_schema_from_dd();') === false) {
   $fail('Schema-/Defaultpflege ist nicht explizit auf den Wartungsmodus begrenzt.', 2);
}

if (strpos($repo, 'private array $request_cache = array();') === false
   || strpos($repo, 'private function remember(string $key, callable $loader): array') === false
   || strpos($repo, 'private function clear_request_cache(): void') === false) {
   $fail('Der request-lokale Referenzcache fehlt.', 3);
}

foreach (array('attribute_definitions', 'attribute_filter_definitions', 'groups', 'channels') as $key) {
   if (strpos($repo, "\$this->remember('" . $key . "'") === false) {
      $fail('Referenzliste wird nicht request-lokal wiederverwendet: ' . $key, 4);
   }
}

if (substr_count($repo, '$this->clear_request_cache();') < 8) {
   $fail('Der Request-Cache wird nach Repository-Mutationen nicht ausreichend invalidiert.', 5);
}

echo "OK shop repository request cache\n";
