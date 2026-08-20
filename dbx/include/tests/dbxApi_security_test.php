<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';
require_once dirname(__DIR__) . '/dbxPasswordPolicy.class.php';

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$password = dbxPasswordPolicy::generate(96, '-_!');
if (strlen($password) !== 96 || preg_match('/^[a-zA-Z0-9_!\-]+$/', $password) !== 1) {
   $fail('PasswordPolicy liefert keine gueltige Laenge oder Zeichenmenge.', 1);
}

$web_app_file = dirname(__DIR__) . '/dbxWebApp.class.php';
$web_app_source = file_get_contents($web_app_file);
if (!is_string($web_app_source) || preg_match('/\\blogin\\s*\\(\\s*2\\s*\\)/', $web_app_source) === 1) {
   $fail('Ein Request-Parameter kann weiterhin den API-Benutzer anmelden.', 2);
}

$load_config = static function (string $file): array {
   $config = array();
   require $file;
   return is_array($config) ? $config : array();
};
$base_file = dirname(__DIR__, 2) . '/modules/dbx/cfg/config.php';
$local_file = dirname(__DIR__, 2) . '/modules/dbx/cfg/config.local.php';
$base = $load_config($base_file);
$local = is_file($local_file) ? $load_config($local_file) : array();

$secret_paths = array(
   array('db', 'dbxApp', 'pass'),
   array('db', 'dbxTestCodex', 'pass'),
   array('ftp', 'web', 'sftp_pass'),
   array('mail', 'dbxApp', 'pass'),
);
$read_path = static function (array $data, array $path) {
   foreach ($path as $part) {
      if (!is_array($data) || !array_key_exists($part, $data)) {
         return null;
      }
      $data = $data[$part];
   }
   return $data;
};

foreach ($secret_paths as $path) {
   if ((string)$read_path($base, $path) !== '') {
      $fail('Ein Geheimnis steht weiterhin in der versionierten config.php.', 3);
   }
}

if ($local) {
   $runtime = dbx()->get_cfg('dbx');
   foreach ($secret_paths as $path) {
      $local_value = $read_path($local, $path);
      if ($local_value !== null && $read_path($runtime, $path) !== $local_value) {
         $fail('config.local.php wird nicht korrekt ueber die Basis gelegt.', 4);
      }
   }
}

echo "OK dbxApi security\n";
