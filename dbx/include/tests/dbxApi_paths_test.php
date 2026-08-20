<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$normalize = static function (string $path): string {
   return str_replace('\\', '/', $path);
};

$expected_base = rtrim($normalize(realpath(dirname(__DIR__, 3))), '/') . '/';
$base = $normalize(dbx()->get_base_dir());
$files = $normalize(dbx()->get_file_dir());

if ($base !== $expected_base) {
   fwrite(STDERR, "FAIL: Basisverzeichnis ist nicht portabel: $base\n");
   exit(1);
}
if ($files !== $expected_base . 'files/') {
   fwrite(STDERR, "FAIL: Dateiverzeichnis ist nicht portabel: $files\n");
   exit(2);
}

$stored = dbx()->config_path_store($expected_base . 'files/test/', true);
if ($stored !== 'files/test/') {
   fwrite(STDERR, "FAIL: Projektpfad wurde nicht relativ gespeichert: $stored\n");
   exit(3);
}

$resolved = $normalize(dbx()->config_path_resolve($stored));
if ($resolved !== $expected_base . 'files/test/') {
   fwrite(STDERR, "FAIL: Projektpfad wurde nicht korrekt aufgeloest: $resolved\n");
   exit(4);
}

$config = array('db' => array(
   'main' => array('type' => 'mysql', 'dbname' => 'main'),
   'module' => array('type' => 'sqlite', 'dbname' => 'module.db3'),
   'dbx|other.db3' => array('type' => '', 'dbname' => 'other.db3'),
));
$clean_config = dbx()->get_system_obj('dbxConfigStore')->normalize_for_store($config);
if (array_keys($clean_config['db'] ?? array()) !== array('main')) {
   fwrite(STDERR, "FAIL: Dynamische Moduldatenbanken wurden nicht entfernt.\n");
   exit(5);
}

$runtime = dbx()->get_system_obj('dbxRuntime');
if ($runtime->error_type(E_WARNING) !== 'E_WARNING' || $runtime->error_type(-123) !== 'E_UNKNOWN') {
   fwrite(STDERR, "FAIL: PHP-Fehlertypen werden nicht korrekt abgebildet.\n");
   exit(6);
}

foreach (array(1, 0, -2) as $not_denied) {
   if (dbx()->is_access_denied($not_denied)) {
      fwrite(STDERR, "FAIL: is_access_denied() meldet faelschlich Zugriff verweigert fuer $not_denied.\n");
      exit(7);
   }
}
if (!dbx()->is_access_denied(-1)) {
   fwrite(STDERR, "FAIL: is_access_denied() erkennt -1 nicht als Zugriff verweigert.\n");
   exit(8);
}
foreach (array(1, 0, -1) as $not_db_error) {
   if (dbx()->is_db_error($not_db_error)) {
      fwrite(STDERR, "FAIL: is_db_error() meldet faelschlich einen Datenbankfehler fuer $not_db_error.\n");
      exit(9);
   }
}
if (!dbx()->is_db_error(-2)) {
   fwrite(STDERR, "FAIL: is_db_error() erkennt -2 nicht als Datenbankfehler.\n");
   exit(10);
}

echo "OK dbxApi paths\n";
